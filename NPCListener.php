<?php

namespace OnePiece\NPC;

use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use pocketmine\level\particle\SmokeParticle;
use pocketmine\level\particle\WhiteSmokeParticle;
use pocketmine\level\particle\PortalParticle;
use pocketmine\level\particle\FlameParticle;
use pocketmine\level\particle\LavaDripParticle;
use pocketmine\level\particle\WaterDripParticle;
use pocketmine\level\particle\InstantEnchantParticle;
use pocketmine\level\particle\DustParticle;
use pocketmine\level\particle\EnchantmentTableParticle;
use pocketmine\level\particle\BubbleParticle;
use pocketmine\level\particle\InkParticle;
use pocketmine\level\sound\FizzSound;
use pocketmine\level\sound\AnvilUseSound;
use pocketmine\level\sound\PopSound;
use pocketmine\math\Vector3;
use pocketmine\scheduler\Task;

class NPCListener implements Listener {

    private $plugin;
    private $lastDamager = [];
    private $damageTracking = [];

    // Universal animated damage counter for all Player -> NPC damage.
    private $damageCounter = [];
    private $damageCounterLast = [];
    private $damageAnimTask = [];
    private $damageAnimShown = [];

    // Groups same-tick / same-burst AoE hits and counts only the highest single target damage.
    private $damageBurstMax = [];
    private $damageBurstTask = [];

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function onEntityDamage(EntityDamageEvent $event) {
        if (!($event instanceof EntityDamageByEntityEvent)) return;

        $entity = $event->getEntity();
        $damager = $event->getDamager();

        if ($entity instanceof NPCEntity && $damager instanceof Player) {
            $this->lastDamager[$entity->getId()] = $damager->getName();
            $this->scalePlayerAttack($event, $damager, $entity);

            if (!$event->isCancelled()) {
                $finalDamage = method_exists($event, "getFinalDamage") ? $event->getFinalDamage() : $event->getDamage();

                // Universal counter for all Player -> NPC damage: fruits, swords, styles, haki, and melee.
                // queueStackedDamage groups same-burst multi-NPC hits and only counts the highest single hit.
                $this->queueStackedDamage($damager, $finalDamage);

                $id = $entity->getId();
                $pName = $damager->getName();

                if (!isset($this->damageTracking[$id])) {
                    $this->damageTracking[$id] = [];
                }

                if (!isset($this->damageTracking[$id][$pName])) {
                    $this->damageTracking[$id][$pName] = 0;
                }

                $this->damageTracking[$id][$pName] += $finalDamage;
            }

            return;
        }

        if ($entity instanceof Player && $damager instanceof NPCEntity) {
            $this->scaleNPCAttack($event, $damager, $entity);
            return;
        }
    }

    private function scalePlayerAttack(EntityDamageByEntityEvent $event, Player $player, NPCEntity $npc) {
        $baseDamage = $event->getDamage();
        $multiplier = 1.0;

        $statsPlugin = $this->getStatsPlugin();
        if ($statsPlugin !== null) {
            $statManager = $statsPlugin->getStatManager();
            $statScaler = $statsPlugin->getStatScaler();
            $sp = $statManager->getStatPlayer($player);
            if ($sp !== null) {
                $strengthStat = $sp->getStat("strength");
                $multiplier *= $statScaler->getAttackMultiplier($strengthStat);
            }
        }

        $hakiPlugin = $this->getHakiPlugin();
        if ($hakiPlugin !== null) {
            $armament = $hakiPlugin->getArmament();
            if ($armament->isActive($player)) {
                $multiplier *= $armament->getDamageBoost($player);
                $hakiPlugin->getHakiManager()->addExp($player->getName(), "armament", 2);
                $player->hakiActive = true;
            } else {
                $player->hakiActive = false;
            }
        }

        $devilPlugin = $this->getDevilPlugin();
        if ($devilPlugin !== null) {
            if ($devilPlugin->isAbilityActive($player)) {
                if ($statsPlugin !== null) {
                    $statManager = $statsPlugin->getStatManager();
                    $statScaler = $statsPlugin->getStatScaler();
                    $sp = $statManager->getStatPlayer($player);
                    if ($sp !== null) {
                        $hakiStat = $sp->getStat("haki");
                        $multiplier *= $statScaler->getHakiMultiplier($hakiStat);
                    }
                }
            }
        }

        $xdmg = $baseDamage * $multiplier;
        $finalDamage = 10.0 + $xdmg;

        $event->setDamage($finalDamage);
    }

    private function playerHasDevilFruit(Player $player) {
        $devilPlugin = $this->getDevilPlugin();
        if ($devilPlugin === null) return false;
        try {
            return $devilPlugin->getFruitManager()->playerHasFruit($player);
        } catch (\Exception $e) {}
        return false;
    }

    private function isFightPluginDamage(Player $player, EntityDamageByEntityEvent $event) {
        $fight = $this->plugin->getServer()->getPluginManager()->getPlugin("OnePieceFight");
        if ($fight === null || !$fight->isEnabled()) return false;

        try {
            $mode = $fight->getPlayerMode($player);
            if ($mode === "sword") {
                $sword = $fight->getSwordManager()->getPlayerSword($player);
                return $sword !== null;
            }

            if ($mode === "style") {
                $style = $fight->getStyleManager()->getPlayerStyle($player);
                return $style !== null;
            }
        } catch (\Exception $e) {}

        return false;
    }

    private function isDevilAbilityHit(Player $player, EntityDamageByEntityEvent $event) {
        $devilPlugin = $this->getDevilPlugin();
        if ($devilPlugin === null) {
            return false;
        }

        try {
            if ($devilPlugin->getAbilityDamage($player->getName()) !== null) {
                return true;
            }
        } catch (\Exception $e) {}

        try {
            if ($devilPlugin->isAbilityActive($player)) {
                return true;
            }
        } catch (\Exception $e) {}

        return $event->getCause() === EntityDamageEvent::CAUSE_MAGIC;
    }

    private function queueStackedDamage(Player $player, $damage) {
        $name = strtolower($player->getName());
        $damage = (float)$damage;

        // During one tiny burst window, keep only the biggest single NPC hit.
        // Example: AoE hits 3 NPCs for 10 each -> visible counter adds 10, not 30.
        if (!isset($this->damageBurstMax[$name]) || $damage > $this->damageBurstMax[$name]) {
            $this->damageBurstMax[$name] = $damage;
        }

        if (!isset($this->damageBurstTask[$name])) {
            $this->damageBurstTask[$name] = true;
            $this->plugin->getServer()->getScheduler()->scheduleDelayedTask(
                new DamageCounterBurstTask($this, $player->getName()),
                2
            );
        }
    }

    public function flushDamageBurst($playerName) {
        $name = strtolower($playerName);
        $player = $this->plugin->getServer()->getPlayerExact($playerName);

        if ($player === null || !$player->isOnline()) {
            unset($this->damageBurstMax[$name]);
            unset($this->damageBurstTask[$name]);
            return;
        }

        if (!isset($this->damageBurstMax[$name])) {
            unset($this->damageBurstTask[$name]);
            return;
        }

        $damage = $this->damageBurstMax[$name];
        unset($this->damageBurstMax[$name]);
        unset($this->damageBurstTask[$name]);

        $this->showStackedDamage($player, $damage);
    }

    private function showStackedDamage(Player $player, $damage) {
        $name = strtolower($player->getName());
        $now = microtime(true);

        // Player-wide total counter: accurate total damage dealt by this player in the stack window.
        if (!isset($this->damageCounterLast[$name]) || ($now - $this->damageCounterLast[$name]) > 2.0) {
            $this->damageCounter[$name] = 0;
            $this->damageAnimShown[$name] = 0;
        }

        if (!isset($this->damageCounter[$name])) {
            $this->damageCounter[$name] = 0;
        }

        $this->damageCounter[$name] += (float)$damage;
        $this->damageCounterLast[$name] = $now;

        $this->startDamageAnimation($player);
    }

    private function startDamageAnimation(Player $player) {
        $name = strtolower($player->getName());

        if (isset($this->damageAnimTask[$name])) {
            $this->plugin->getServer()->getScheduler()->cancelTask($this->damageAnimTask[$name]);
            unset($this->damageAnimTask[$name]);
        }

        $task = new DamageCounterAnimationTask($this, $player->getName());
        $handler = $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($task, 2);
        $this->damageAnimTask[$name] = $handler->getTaskId();
    }

    public function tickDamageAnimation($playerName) {
        $name = strtolower($playerName);
        $player = $this->plugin->getServer()->getPlayerExact($playerName);

        if ($player === null || !$player->isOnline()) {
            $this->clearDamageAnimation($name);
            return false;
        }

        if (!isset($this->damageCounter[$name])) {
            $this->clearDamageAnimation($name);
            return false;
        }

        $target = $this->damageCounter[$name];
        $shown = isset($this->damageAnimShown[$name]) ? $this->damageAnimShown[$name] : 0;
        $diff = $target - $shown;

        if ($diff <= 0.05) {
            $shown = $target;
        } else {
            $shown += max(1.0, $diff * 0.35);
            if ($shown > $target) $shown = $target;
        }

        $this->damageAnimShown[$name] = $shown;

        $progress = $target > 0 ? ($shown / $target) : 1.0;
        if ($progress < 0.35) {
            $color = "§f"; // beginning - white
        } elseif ($progress < 0.70) {
            $color = "§e"; // middle - yellow
        } elseif ($progress < 1.0) {
            $color = "§6"; // close to final - orange/gold
        } else {
            $color = "§c"; // final - red
        }

        $player->sendTip($color . "+" . round($shown, 1) . " DMG");

        if ($shown >= $target && isset($this->damageCounterLast[$name])) {
            if (microtime(true) - $this->damageCounterLast[$name] > 2.0) {
                $player->sendTip(" ");
                $this->clearDamageAnimation($name);
                return false;
            }
        }

        return true;
    }

    private function clearDamageAnimation($name) {
        unset($this->damageCounter[$name]);
        unset($this->damageCounterLast[$name]);
        unset($this->damageAnimShown[$name]);
        unset($this->damageBurstMax[$name]);
        unset($this->damageBurstTask[$name]);

        if (isset($this->damageAnimTask[$name])) {
            $this->plugin->getServer()->getScheduler()->cancelTask($this->damageAnimTask[$name]);
            unset($this->damageAnimTask[$name]);
        }
    }

    private function scaleNPCAttack(EntityDamageByEntityEvent $event, NPCEntity $npc, Player $player) {
        $devilPlugin = $this->getDevilPlugin();
        if ($devilPlugin !== null) {
            $fm = $devilPlugin->getFruitManager();
            $fruitId = $fm->getPlayerFruitId($player);
            if ($fruitId !== null) {
                $fruit = $fm->getFruit($fruitId);
                if ($fruit !== null && strtolower($fruit->getType()) === "logia") {
                    if (!$npc->npcHakiArmament) {
                        $event->setCancelled(true);
                        $this->spawnLogiaVFX($player, $fruitId);
                        return;
                    }
                }
            }
        }

        $hakiPlugin = $this->getHakiPlugin();
        if ($hakiPlugin !== null) {
            $observation = $hakiPlugin->getObservation();
            if ($observation->isActive($player)) {
                if ($observation->tryDodge($player)) {
                    $event->setCancelled(true);
                    $player->sendTip("§bDodged!");
                    $hakiPlugin->getHakiManager()->addExp($player->getName(), "observation", 5);
                    return;
                }
                $hakiPlugin->getHakiManager()->addExp($player->getName(), "observation", 1);
            }
        }

        $baseDamage = min(5.0, $event->getDamage());
        $defenseDiv = 1.0;
        $statsPlugin = $this->getStatsPlugin();
        if ($statsPlugin !== null) {
            $statManager = $statsPlugin->getStatManager();
            $statScaler = $statsPlugin->getStatScaler();
            $sp = $statManager->getStatPlayer($player);
            if ($sp !== null) {
                $defenseStat = $sp->getStat("defense");
                $defenseDiv *= $statScaler->getDefenseMultiplier($defenseStat);
            }
        }

        if ($hakiPlugin !== null) {
            $armament = $hakiPlugin->getArmament();
            if ($armament->isActive($player)) {
                $defenseDiv *= $armament->getDefenseBoost($player);
                $hakiPlugin->getHakiManager()->addExp($player->getName(), "armament", 1);
            }
        }

        $finalDamage = max(0.5, $baseDamage / $defenseDiv);
        $event->setDamage($finalDamage);
    }

    private function spawnLogiaVFX(Player $player, $fruitId) {
        $lv = $player->getLevel();
        $pos = $player->getPosition()->add(0, 1.2, 0);
        $count = 12;

        switch ($fruitId) {
            case "mochi_mochi":
                for ($i = 0; $i < $count; $i++) {
                    $lv->addParticle(new WhiteSmokeParticle($pos->add(mt_rand(-5, 5) / 10, mt_rand(-5, 5) / 10, mt_rand(-5, 5) / 10)));
                    if ($i % 3 === 0) $lv->addParticle(new BubbleParticle($pos));
                    if ($i % 4 === 0) $lv->addParticle(new DustParticle($pos, 240, 240, 220));
                }
                $lv->addSound(new FizzSound($pos));
                break;

            case "yami_yami":
            case "yami_v2":
            case "dark_x_quake":
                $density = ($fruitId === "yami_v2" || $fruitId === "dark_x_quake") ? $count * 1.8 : $count;
                for ($i = 0; $i < $density; $i++) {
                    $lv->addParticle(new InkParticle($pos->add(mt_rand(-6, 6) / 10, mt_rand(-6, 6) / 10, mt_rand(-6, 6) / 10)));
                    $lv->addParticle(new PortalParticle($pos));
                    if ($i % 2 === 0) {
                        $lv->addParticle(new SmokeParticle($pos->add(mt_rand(-3, 3) / 10, mt_rand(-3, 3) / 10, mt_rand(-3, 3) / 10)));
                    }
                }
                $lv->addSound(new AnvilUseSound($pos));
                break;

            case "mera_mera":
            case "magu_magu":
                for ($i = 0; $i < $count; $i++) {
                    $lv->addParticle(new FlameParticle($pos->add(mt_rand(-5, 5) / 10, mt_rand(-5, 5) / 10, mt_rand(-5, 5) / 10)));
                    if ($i % 3 === 0) $lv->addParticle(new LavaDripParticle($pos));
                }
                $lv->addSound(new FizzSound($pos));
                break;

            case "moku_moku":
                for ($i = 0; $i < $count; $i++) {
                    $lv->addParticle(new SmokeParticle($pos->add(mt_rand(-6, 6) / 10, mt_rand(-6, 6) / 10, mt_rand(-6, 6) / 10)));
                    $lv->addParticle(new WhiteSmokeParticle($pos->add(mt_rand(-4, 4) / 10, mt_rand(-4, 4) / 10, mt_rand(-4, 4) / 10)));
                }
                $lv->addSound(new PopSound($pos));
                break;

            case "hie_hie":
                for ($i = 0; $i < $count; $i++) {
                    $lv->addParticle(new WhiteSmokeParticle($pos->add(mt_rand(-5, 5) / 10, mt_rand(-5, 5) / 10, mt_rand(-5, 5) / 10)));
                    if ($i % 2 === 0) $lv->addParticle(new WaterDripParticle($pos));
                }
                $lv->addSound(new FizzSound($pos));
                break;

            case "pika_pika":
                for ($i = 0; $i < $count; $i++) {
                    $lv->addParticle(new InstantEnchantParticle($pos->add(mt_rand(-5, 5) / 10, mt_rand(-5, 5) / 10, mt_rand(-5, 5) / 10)));
                    if ($i % 3 === 0) $lv->addParticle(new EnchantmentTableParticle($pos));
                }
                $lv->addSound(new AnvilUseSound($pos));
                break;

            case "suna_suna":
                for ($i = 0; $i < $count; $i++) {
                    $lv->addParticle(new DustParticle($pos->add(mt_rand(-7, 7) / 10, mt_rand(-7, 7) / 10, mt_rand(-7, 7) / 10), 180, 150, 100));
                }
                $lv->addSound(new AnvilUseSound($pos));
                break;

            case "goro_goro":
                for ($i = 0; $i < $count; $i++) {
                    $lv->addParticle(new PortalParticle($pos->add(mt_rand(-6, 6) / 10, mt_rand(-6, 6) / 10, mt_rand(-6, 6) / 10)));
                    if ($i % 2 === 0) $lv->addParticle(new InstantEnchantParticle($pos));
                }
                $lv->addSound(new PopSound($pos));
                break;

            default:
                for ($i = 0; $i < $count; $i++) {
                    $lv->addParticle(new SmokeParticle($pos->add(mt_rand(-5, 5) / 10, mt_rand(-5, 5) / 10, mt_rand(-5, 5) / 10)));
                    $lv->addParticle(new PortalParticle($pos));
                }
                $lv->addSound(new PopSound($pos));
                break;
        }
    }

    public function onEntityDeath(EntityDeathEvent $event) {
        $entity = $event->getEntity();
        if (!($entity instanceof NPCEntity)) return;

        $nm = $this->plugin->getNPCManager();
        $npcId = $entity->npcId;
        $id = $entity->getId();

        if ($npcId === "" || !$nm->exists($npcId)) return;

        $data = $nm->get($npcId);
        $lastHitterName = isset($this->lastDamager[$id]) ? $this->lastDamager[$id] : null;
        $contributors = isset($this->damageTracking[$id]) ? $this->damageTracking[$id] : [];

        unset($this->lastDamager[$id]);
        unset($this->damageTracking[$id]);

        $eligibleKillers = [];
        if ($lastHitterName !== null) {
            $eligibleKillers[$lastHitterName] = true;
        }

        if ($data["category"] === "boss") {
            $threshold = $entity->getMaxHealth() * 0.1;
            foreach ($contributors as $name => $damage) {
                if ($damage >= $threshold) {
                    $eligibleKillers[$name] = true;
                }
            }
        }

        foreach (array_keys($eligibleKillers) as $playerName) {
            $player = $this->plugin->getServer()->getPlayerExact($playerName);
            if ($player === null || !$player->isOnline()) continue;

            $catColor = "§7";
            switch ($data["category"]) {
                case "bandit": $catColor = "§a"; break;
                case "commander": $catColor = "§6"; break;
                case "boss": $catColor = "§c"; break;
            }

            $player->sendMessage($catColor . "Defeated " . $data["name"] . " §7[Lv." . $data["level"] . "]!");

            if ($data["category"] === "boss" && $playerName === $lastHitterName) {
                $this->plugin->getServer()->broadcastMessage(
                    "§6" . $player->getName() . " defeated " . $catColor . $data["name"] . " §6[Lv." . $data["level"] . "]!"
                );
            }

            $this->giveRewards($player, $data);
            $this->tryDropDevilFruit($player, $data);
            $this->grantExp($player, $data);
            $this->grantHakiExp($player, $data);
            $this->grantBerries($player, $data);
            $this->plugin->getQuestManager()->onNPCKill($player, $data["category"], $data["level"]);

            if ($entity->isPartyNPC) {
                $em = $this->plugin->getEventManager();
                if ($em->isEligibleKiller($player, $data["level"])) {
                    $berryPlugin = $this->plugin->getServer()->getPluginManager()->getPlugin("OnePieceBerry");
                    if ($berryPlugin !== null && $berryPlugin->isEnabled()) {
                        $phm = $berryPlugin->getPartyHatManager();
                        $amount = $em->getPartyHatReward();
                        $phm->add($player->getName(), $amount);
                        $player->sendMessage("§6§l[Summer] §r§e+" . $amount . " Sunglasses" . ($amount > 1 ? "s" : "") . "!");
                    }
                }
                $em->clearPartyNPC($entity->npcId, $entity->npcPosNum);
            }
        }

        $nm->clearActiveEntity($npcId, $entity->npcPosNum);
        $nm->scheduleRespawn($npcId, $entity->npcPosNum);
    }

    private function giveRewards(Player $player, array $data) {
        if (!isset($data["rewards"]) || empty($data["rewards"])) return;

        foreach ($data["rewards"] as $reward) {
            $roll = mt_rand(1, 10000) / 100;
            if ($roll <= $reward["chance"]) {
                $item = Item::get($reward["item_id"], 0, 1);
                if ($reward["item_name"] !== "") {
                    $item->setCustomName(TextFormat::RESET . $reward["item_name"]);
                }

                if ($player->getInventory()->canAddItem($item)) {
                    $player->getInventory()->addItem($item);
                } else {
                    $player->getLevel()->dropItem($player, $item);
                    $player->sendMessage("§eInventory full! Item dropped on the ground!");
                }

                $player->sendMessage("§a# Received: §f" . $reward["item_name"]);
            }
        }
    }

    private function tryDropDevilFruit(Player $player, array $data) {
        if (!isset($data["devil_fruit"]) || $data["devil_fruit"] === "none" || $data["devil_fruit"] === "") return;

        $fruitId = $data["devil_fruit"];
        $devilPlugin = $this->getDevilPlugin();
        if ($devilPlugin === null) return;

        $fm = $devilPlugin->getFruitManager();
        if ($fm === null) return;

        $fruit = $fm->getFruit($fruitId);
        if ($fruit === null) return;

        $chance = 1;
        switch ($data["category"]) {
            case "bandit": $chance = 0; break;
            case "commander": $chance = 0; break;
            case "boss": $chance = 5; break;
        }

        $roll = mt_rand(1, 10000);
        if ($roll > $chance) return;

        $item = Item::get(Item::GOLDEN_APPLE, 0, 1);
        $item->setCustomName($fruit->getRarityColor() . $fruit->getDisplayName());

        if ($player->getInventory()->canAddItem($item)) {
            $player->getInventory()->addItem($item);
        } else {
            $player->getLevel()->dropItem($player, $item);
            $player->sendMessage("§eInventory full! Devil Fruit dropped on the ground!");
        }

        $player->sendMessage("§d§l DEVIL FRUIT DROP! §r§f" . $fruit->getDisplayName());
        $this->plugin->getServer()->broadcastMessage(
            "§d" . $player->getName() . " obtained " . $fruit->getRarityColor() . $fruit->getDisplayName() . " §dfrom " . $data["name"] . "!"
        );
    }

    private function grantExp(Player $player, array $data) {
        $statsPlugin = $this->getStatsPlugin();
        if ($statsPlugin === null) return;

        $statManager = $statsPlugin->getStatManager();
        $baseExp = 5;
        switch ($data["category"]) {
            case "bandit": $baseExp = 5; break;
            case "commander": $baseExp = 15; break;
            case "boss": $baseExp = 40; break;
        }

        $exp = $baseExp + ($data["level"] * 2);
        $statManager->addExp($player, $exp);
        $player->sendMessage("§a+" . $exp . " EXP");
    }

    private function grantHakiExp(Player $player, array $data) {
        $hakiPlugin = $this->getHakiPlugin();
        if ($hakiPlugin === null) return;

        $hakiManager = $hakiPlugin->getHakiManager();
        $name = $player->getName();
        $baseExp = 1;
        switch ($data["category"]) {
            case "bandit": $baseExp = 1; break;
            case "commander": $baseExp = 3; break;
            case "boss": $baseExp = 8; break;
        }

        $armament = $hakiPlugin->getArmament();
        if ($armament->isActive($player)) {
            $hakiManager->addExp($name, "armament", $baseExp);
        }

        $observation = $hakiPlugin->getObservation();
        if ($observation->isActive($player)) {
            $hakiManager->addExp($name, "observation", $baseExp);
        }
    }

    private function grantBerries(Player $player, array $data) {
        $berryPlugin = $this->plugin->getServer()->getPluginManager()->getPlugin("OnePieceBerry");
        if ($berryPlugin === null || !$berryPlugin->isEnabled()) return;

        $bm = $berryPlugin->getBerryManager();
        $baseBerry = 10;
        switch ($data["category"]) {
            case "bandit": $baseBerry = 10; break;
            case "commander": $baseBerry = 50; break;
            case "boss": $baseBerry = 200; break;
        }

        $berry = $baseBerry + ($data["level"] * 5);
        $berry += mt_rand(0, (int)($berry * 0.2));
        $bm->add($player->getName(), $berry);
        $player->sendMessage("§6+" . $bm->format($berry) . " Berries");
    }

    private function getStatsPlugin() {
        $plugin = $this->plugin->getServer()->getPluginManager()->getPlugin("OnePieceStats");
        if ($plugin !== null && $plugin->isEnabled()) return $plugin;
        return null;
    }

    private function getHakiPlugin() {
        $plugin = $this->plugin->getServer()->getPluginManager()->getPlugin("OnePieceHaki");
        if ($plugin !== null && $plugin->isEnabled()) return $plugin;
        return null;
    }

    private function getDevilPlugin() {
        $plugin = $this->plugin->getServer()->getPluginManager()->getPlugin("OnePieceDevil");
        if ($plugin !== null && $plugin->isEnabled()) return $plugin;
        return null;
    }
}

class DamageCounterBurstTask extends Task {

    private $listener;
    private $playerName;

    public function __construct(NPCListener $listener, $playerName) {
        $this->listener = $listener;
        $this->playerName = $playerName;
    }

    public function onRun($currentTick) {
        $this->listener->flushDamageBurst($this->playerName);
    }
}

class DamageCounterAnimationTask extends Task {

    private $listener;
    private $playerName;

    public function __construct(NPCListener $listener, $playerName) {
        $this->listener = $listener;
        $this->playerName = $playerName;
    }

    public function onRun($currentTick) {
        $this->listener->tickDamageAnimation($this->playerName);
    }
}
