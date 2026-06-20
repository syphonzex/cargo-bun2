<?php

namespace OnePiece\Devil;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use pocketmine\item\Item;

class Main extends PluginBase implements Listener {

    private $opWorlds = ["OP", "Sea2", "sea3"];
    const ABILITY_DAMAGE_CAP = 8.0;

    private $fruitManager;
    private $fruitStorage;
    private $fruitCooldown;
    private $waterWeakness;
    private $masteryManager;
    private $iceWalkManager;
    private $combatPlugin = null;
    private $statsPlugin = null;
    private $abilityActive = [];
    private $abilityDamageCache = [];

    public function onEnable() {
        $this->getLogger()->info(TextFormat::GREEN . "One Piece Devil Fruit System loaded!");

        @mkdir($this->getDataFolder());

        $this->fruitStorage = new FruitStorage($this);
        $this->fruitCooldown = new FruitCooldown($this);
        $this->fruitManager = new FruitManager($this);
        $this->waterWeakness = new WaterWeakness($this);
        $this->masteryManager = new MasteryManager($this);
        $this->iceWalkManager = new IceWalkManager($this);

        $this->combatPlugin = $this->getServer()->getPluginManager()->getPlugin("OnePieceCombat");
        $this->statsPlugin = $this->getServer()->getPluginManager()->getPlugin("OnePieceStats");

        $this->fruitManager->registerFruit(new fruits\BariBari($this));
        $this->fruitManager->registerFruit(new fruits\GomuGomu($this));
        $this->fruitManager->registerFruit(new fruits\OpeOpe($this));
        $this->fruitManager->registerFruit(new fruits\GuraGura($this));
        $this->fruitManager->registerFruit(new fruits\MokuMoku($this));
        $this->fruitManager->registerFruit(new fruits\SunaSuna($this));
        $this->fruitManager->registerFruit(new fruits\MeraMera($this));
        $this->fruitManager->registerFruit(new fruits\GoroGoro($this));
        $this->fruitManager->registerFruit(new fruits\InuInu($this));
        $this->fruitManager->registerFruit(new fruits\NekoNeko($this));
        $this->fruitManager->registerFruit(new fruits\ToriTori($this));
        $this->fruitManager->registerFruit(new fruits\UoUo($this));
        $this->fruitManager->registerFruit(new fruits\IroIro($this));
        $this->fruitManager->registerFruit(new fruits\BaraBara($this));
        $this->fruitManager->registerFruit(new fruits\HieHie($this));
        $this->fruitManager->registerFruit(new fruits\PikaPika($this));
        $this->fruitManager->registerFruit(new fruits\ZouZou($this));
        $this->fruitManager->registerFruit(new fruits\ToriToriModel($this));
        $this->fruitManager->registerFruit(new fruits\GiroGiro($this));
        $this->fruitManager->registerFruit(new fruits\TrexTrex($this));
        $this->fruitManager->registerFruit(new fruits\SoundSound($this));
        $this->fruitManager->registerFruit(new fruits\GearFourth($this));
        $this->fruitManager->registerFruit(new fruits\YamiYami($this));
        $this->fruitManager->registerFruit(new fruits\MochiMochi($this));
        $this->fruitManager->registerFruit(new fruits\BombBomb($this));
        $this->fruitManager->registerFruit(new fruits\ReviveRevive($this));
        $this->fruitManager->registerFruit(new fruits\MaguMagu($this));
        $this->fruitManager->registerFruit(new fruits\LoveLove($this));
        $this->fruitManager->registerFruit(new fruits\YamiV2($this));
        $this->fruitManager->registerFruit(new fruits\SoruSoru($this));
        $this->fruitManager->registerFruit(new fruits\OkuchiOkuchi($this));
        $this->fruitManager->registerFruit(new fruits\DarkXQuake($this));
        $this->fruitManager->registerFruit(new fruits\ShadowShadow($this));
        $this->fruitManager->registerFruit(new fruits\GravGrav($this));
        $this->fruitManager->registerFruit(new fruits\MagnetMagnet($this));
        $this->fruitManager->registerFruit(new fruits\GasGas($this));
        $this->fruitManager->registerFruit(new fruits\Dragonv2($this));
        $this->fruitManager->registerFruit(new fruits\NikaNika($this));

        BlockEffects::registerEntity();

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getServer()->getPluginManager()->registerEvents(new MasteryListener($this), $this);

        $map = $this->getServer()->getCommandMap();
        $map->register("onepiece", new FruitCommand($this, "fruit", "Devil Fruit commands", "/fruit help"));
        $map->register("onepiece", new FruitCommand($this, "fruits", "List all fruits", "/fruits"));
        $map->register("onepiece", new MasteryCommand($this));

        $this->getServer()->getScheduler()->scheduleRepeatingTask(new DevilTickTask($this), 20);
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new IceWalkTickTask($this), 2);
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new MasterySaveTask($this), 20 * 60 * 5);

        foreach ($this->getServer()->getOnlinePlayers() as $player) {
            $this->fruitManager->loadPlayerFruit($player);
        }
    }

    public function onDisable() {
        if ($this->masteryManager !== null) {
            $this->masteryManager->save();
        }
        $this->getLogger()->info(TextFormat::RED . "One Piece Devil Fruit System disabled!");
    }

public function isInOPWorld(Player $player) {
    return in_array($player->getLevel()->getName(), $this->opWorlds);
}

    public function getFruitManager()   { return $this->fruitManager; }
    public function getFruitStorage()   { return $this->fruitStorage; }
    public function getFruitCooldown()  { return $this->fruitCooldown; }
    public function getWaterWeakness()  { return $this->waterWeakness; }
    public function getMasteryManager() { return $this->masteryManager; }
    public function getIceWalkManager() { return $this->iceWalkManager; }
    public function getCombatPlugin()   { return $this->combatPlugin; }
    public function getStatsPlugin()    { return $this->statsPlugin; }

    public function canTargetPlayer($attackerName, $targetPlayer) {
        $raidPlugin = $this->getServer()->getPluginManager()->getPlugin("OnePieceRaid");
        if ($raidPlugin !== null && $raidPlugin->isEnabled()) {
            try {
                $am = $raidPlugin->getAwakenManager();
                if ($am !== null) {
                    if ($am->isPlayerInAwakenWorld($targetPlayer)) return false;
                    $attacker = $this->getServer()->getPlayerExact($attackerName);
                    if ($attacker !== null && $am->isPlayerInAwakenWorld($attacker)) return false;
                }
            } catch (\Exception $e) {}
        }
        if ($this->combatPlugin !== null && $this->combatPlugin->isEnabled()) {
            $toggle = $this->combatPlugin->getCombatToggle();
            if (!$toggle->canPvP($attackerName)) return true;
            if (!$toggle->canPvP($targetPlayer->getName())) return false;
        }
        if ($this->statsPlugin !== null && $this->statsPlugin->isEnabled()) {
            $sp = $this->statsPlugin->getStatManager()->getStatPlayer($targetPlayer);
            if ($sp !== null && $sp->getLevel() < 10) return false;
        }
        return true;
    }

    public function getTargetBlockReason($attackerName, $targetPlayer) {
        if ($this->combatPlugin !== null && $this->combatPlugin->isEnabled()) {
            $toggle = $this->combatPlugin->getCombatToggle();
            if ($toggle->canPvP($targetPlayer->getName()) && !$toggle->canPvP($targetPlayer->getName())) {
                return "§c§l[ABILITY] §r§cThis player has PvP disabled!";
            }
        }
        if ($this->statsPlugin !== null && $this->statsPlugin->isEnabled()) {
            $sp = $this->statsPlugin->getStatManager()->getStatPlayer($targetPlayer);
            if ($sp !== null && $sp->getLevel() < 10) {
                return "§c§l[ABILITY] §r§cThis player is below Level 10!";
            }
        }
        return null;
    }

    public function isAbilityActive(Player $player) {
        return isset($this->abilityActive[$player->getName()]);
    }

    public function setAbilityDamage($name, $damage) {
        $this->abilityDamageCache[strtolower($name)] = $damage;
    }

    public function getAbilityDamage($name) {
        $name = strtolower($name);
        return isset($this->abilityDamageCache[$name]) ? $this->abilityDamageCache[$name] : null;
    }

    /**
     * @EventHandler
     */
    public function onPlayerJoin(PlayerJoinEvent $event) {
        $player = $event->getPlayer();
        $this->fruitManager->loadPlayerFruit($player);
        $fruitId = $this->fruitManager->getPlayerFruitId($player);
        if ($fruitId !== null) {
            $this->masteryManager->initPlayer($player->getName(), $fruitId);
        }
    }

    /**
     * @EventHandler
     */
    public function onPlayerQuit(PlayerQuitEvent $event) {
        $player = $event->getPlayer();
        $this->fruitCooldown->clearAllCooldowns($player);
        $this->waterWeakness->cleanup($player);
        $this->masteryManager->savePlayer($player->getName());
        $this->iceWalkManager->cleanup($player->getName());
        unset($this->abilityActive[$player->getName()]);
        unset($this->abilityDamageCache[strtolower($player->getName())]);
    }

    /**
     * @EventHandler
     */
    public function onPlayerMove(PlayerMoveEvent $event) {
        $this->iceWalkManager->onPlayerMove($event->getPlayer());
    }

    /**
     * @EventHandler
     */
    public function onItemConsume(PlayerItemConsumeEvent $event) {
        $player = $event->getPlayer();
        $item = $event->getItem();

        if (!$this->isInOPWorld($player)) return;

        if ($item->getId() === Item::GOLDEN_APPLE) {
            $event->setCancelled(true);

            if (!$item->hasCustomName()) {
                $event->setCancelled(false);
                return;
            }

            if ($this->fruitManager->playerHasFruit($player)) {
                $currentFruit = $this->fruitManager->getPlayerFruitName($player);
                $player->sendMessage(TextFormat::RED . "You already have the " . $currentFruit . "!");
                $player->sendMessage(TextFormat::GRAY . "Use /fruit remove to remove it first.");
                return;
            }

            $fruitId = $this->getFruitIdFromItem($item);
            if ($fruitId === null) {
                $player->sendMessage(TextFormat::RED . "This is not a valid Devil Fruit!");
                return;
            }

            $fruit = $this->fruitManager->getFruit($fruitId);
            if ($fruit === null) {
                $player->sendMessage(TextFormat::RED . "Unknown Devil Fruit!");
                return;
            }

            $this->fruitManager->giveFruitToPlayer($player, $fruitId);

            $heldItem = $player->getInventory()->getItemInHand();
            $heldItem->setCount($heldItem->getCount() - 1);
            $player->getInventory()->setItemInHand($heldItem);

            $player->sendMessage(TextFormat::GOLD . "You ate the " . $fruit->getDisplayName() . "!");
            $player->sendMessage(TextFormat::YELLOW . $fruit->getDescription());
            $player->sendMessage(TextFormat::RED . "You are now weak to water!");

            $this->getServer()->broadcastMessage(
                TextFormat::GOLD . $player->getName() . " ate the " . $fruit->getDisplayName() . "!"
            );
        }
    }

    private function getFruitIdFromItem($item) {
        if (!$item->hasCustomName()) return null;

        $customName = $item->getCustomName();
        $lines = explode("\n", $customName);
        $firstLine = $lines[0];
        $clean = trim(TextFormat::clean($firstLine));

        foreach ($this->fruitManager->getAllFruits() as $fruit) {
            $fruitDisplayClean = trim(TextFormat::clean($fruit->getDisplayName()));

            if (strtolower($clean) === strtolower($fruitDisplayClean)) return $fruit->getId();
            if (stripos($clean, $fruitDisplayClean) !== false) return $fruit->getId();
            if (stripos($fruitDisplayClean, $clean) !== false) return $fruit->getId();
        }

        $asId = strtolower(str_replace(" ", "_", $clean));
        if ($this->fruitManager->fruitExists($asId)) return $asId;

        $asId = str_replace(["_no_mi", "_fruit"], "", $asId);
        if ($this->fruitManager->fruitExists($asId)) return $asId;

        return null;
    }

    /**
     * @EventHandler
     */
    public function onPlayerInteract(PlayerInteractEvent $event) {
        $player = $event->getPlayer();
        $item = $event->getItem();

        if (!$this->isInOPWorld($player)) return;

        if ($item->getId() !== Item::BLAZE_ROD) return;

        if (!$this->fruitManager->playerHasFruit($player)) {
            $player->sendMessage(TextFormat::RED . "You don't have a Devil Fruit!");
            return;
        }

        if ($this->waterWeakness->isWeakened($player)) {
            $player->sendMessage(TextFormat::AQUA . "You're too weak from water to use abilities!");
            return;
        }

        $fruitId = $this->fruitManager->getPlayerFruitId($player);
        $fruit = $this->fruitManager->getFruit($fruitId);
        if ($fruit === null) return;

        $name = $player->getName();
        $ability = $player->isSneaking() ? "ability2" : "ability1";

        if (!$this->masteryManager->canUseAbility($name, $ability)) {
            $required = MasteryManager::ABILITY_UNLOCK[$ability];
            $current = $this->masteryManager->getLevel($name);
            $tier = $this->masteryManager->getTierName($name);
            $bar = $this->masteryManager->getProgressBar($name);

            $player->sendMessage("§c§l[LOCKED] §r§7This move requires Mastery Lv.§e" . $required);
            $player->sendMessage("§7Your mastery: " . $tier . " §7(Lv.§f" . $current . "§7)");
            $player->sendMessage("§7" . $bar);
            $player->sendMessage("§7§oKeep using your unlocked moves to level up!");
            return;
        }

        $cooldownKey = $fruitId . "_" . $ability;
        if ($this->fruitCooldown->hasCooldown($player, $cooldownKey)) {
            $remaining = $this->fruitCooldown->getRemainingCooldown($player, $cooldownKey);
            $player->sendTip(TextFormat::YELLOW . "Cooldown: " . round($remaining, 1) . "s");
            return;
        }

        $this->abilityActive[$name] = true;

        $cooldownTime = 0;
        $usedAwakened = false;

        $raidPlugin = $this->getServer()->getPluginManager()->getPlugin("OnePieceRaid");
        if ($raidPlugin !== null) {
            $am = $raidPlugin->getAwakenManager();
            $raidKey = $am->getRaidKeyForFruit($fruitId);
            if ($raidKey !== null && $am->hasAbilityAwakened($name, $raidKey, $ability)) {
                if ($am->getAbilityMode($name, $raidKey, $ability) === "awakened") {
                    $awakenClass = $am->getAwakenFruitClass($raidKey);
                    if ($awakenClass !== null && class_exists($awakenClass)) {
                        $awakenObj = new $awakenClass();
                        if ($ability === "ability1") {
                            $awakenObj->useAbility1($player);
                            $cooldownTime = $awakenObj->getAbility1Cooldown();
                        } else {
                            $awakenObj->useAbility2($player);
                            $cooldownTime = $awakenObj->getAbility2Cooldown();
                        }
                        $usedAwakened = true;
                    }
                }
            }
        }

        if (!$usedAwakened) {
            $cooldownTime = $fruit->useAbility($player, $ability);
        }

        unset($this->abilityActive[$name]);
        unset($this->abilityDamageCache[strtolower($name)]);

        $this->masteryManager->onAbilityUse($player);

        $level = $this->masteryManager->getLevel($name);
        $bar = $this->masteryManager->getProgressBar($name);
        $toNext = $this->masteryManager->getExpToNextLevel($name);
        if ($toNext > 0) {
            $player->sendPopup("§eMastery Lv." . $level . " " . $bar . " §7(" . $toNext . " to next)");
        }

        if ($cooldownTime > 0) {
            $scaledCooldown = $this->masteryManager->getScaledCooldown($name, $cooldownTime);
            $this->fruitCooldown->setCooldown($player, $cooldownKey, $scaledCooldown);
        }
    }

/**
 * @EventHandler
 * @priority HIGHEST
 */
public function onEntityDamage(EntityDamageEvent $event) {
    if (!($event instanceof EntityDamageByEntityEvent)) return;
    $attacker = $event->getDamager();
    $victim = $event->getEntity();

    if (!($attacker instanceof Player)) return;
    if (!$this->isInOPWorld($attacker)) return;

    if ($victim instanceof Player) {
        if ($this->combatPlugin !== null && $this->combatPlugin->isEnabled()) {
            $toggle = $this->combatPlugin->getCombatToggle();
            $attackerName = $attacker->getName();
            $victimName = $victim->getName();

            if (!$toggle->isEnabled($attackerName)) {
                $event->setCancelled(true);
                $attacker->sendTip(TextFormat::GRAY . "Use /combat enable to PvP!");
                return;
            }

            if ($toggle->isInWarmup($attackerName)) {
                $event->setCancelled(true);
                $remaining = round($toggle->getWarmupRemaining($attackerName), 1);
                $attacker->sendTip(TextFormat::AQUA . "Warmup: " . $remaining . "s remaining...");
                return;
            }

            if ($toggle->hasDeathProtection($attackerName)) {
                $event->setCancelled(true);
                $remaining = round($toggle->getDeathProtectionRemaining($attackerName) / 60, 1);
                $attacker->sendTip(TextFormat::GREEN . "Death Protection: " . $remaining . "m remaining");
                return;
            }

            if (!$toggle->isEnabled($victimName)) {
                $event->setCancelled(true);
                $attacker->sendTip(TextFormat::GRAY . "This player has combat disabled!");
                return;
            }

            if ($toggle->isInWarmup($victimName)) {
                $event->setCancelled(true);
                $attacker->sendTip(TextFormat::GRAY . "This player is still warming up!");
                return;
            }

            if ($toggle->hasDeathProtection($victimName)) {
                $event->setCancelled(true);
                $attacker->sendTip(TextFormat::GREEN . "This player has death protection!");
                return;
            }
        }

        if ($this->statsPlugin !== null && $this->statsPlugin->isEnabled()) {
            $sm = $this->statsPlugin->getStatManager();
            if ($sm !== null) {
                $attackerLevel = 1;
                $victimLevel = 1;

                if ($sm->isLoaded($attacker)) {
                    $sp = $sm->getStatPlayer($attacker);
                    if ($sp !== null) {
                        $attackerLevel = $sp->getLevel();
                    }
                }

                if ($sm->isLoaded($victim)) {
                    $sp = $sm->getStatPlayer($victim);
                    if ($sp !== null) {
                        $victimLevel = $sp->getLevel();
                    }
                }

                if ($attackerLevel < 10) {
                    $event->setCancelled(true);
                    $attacker->sendTip(TextFormat::GRAY . "You must be Level 10 to PvP!");
                    return;
                }

                if ($victimLevel < 10) {
                    $event->setCancelled(true);
                    $attacker->sendTip(TextFormat::GRAY . "This player is below Level 10!");
                    return;
                }
            }
        }
    }

    if ($event->isCancelled()) return;

    if (!$this->isAbilityActive($attacker)) return;

    $name = $attacker->getName();
    $mm = $this->masteryManager;
    $multiplier = $mm->getDamageMultiplier($name);

    $cachedDamage = $this->getAbilityDamage($name);

    if ($cachedDamage !== null) {
        $finalDamage = $cachedDamage * $multiplier;
    } else {
        $cappedBase = min($event->getDamage(), 4.0);
        $finalDamage = $cappedBase * $multiplier;
    }

    $fruitsPlugin = $this->getServer()->getPluginManager()->getPlugin("OnePieceFruits");
    if ($fruitsPlugin !== null && $fruitsPlugin->isEnabled()) {
        $zoan = $fruitsPlugin->getZoanSystem();
        if ($zoan !== null && $zoan->isTransformed($attacker)) {
            $finalDamage *= $zoan->getDamageBoost($attacker);
        }

        $logia = $fruitsPlugin->getLogiaSystem();
        if ($logia !== null && $logia->isActive($attacker)) {
            $finalDamage *= $logia->getDamageBoost($attacker);
        }
    }

    $finalDamage = min($finalDamage, self::ABILITY_DAMAGE_CAP);
    $event->setDamage($finalDamage);

    if ($victim instanceof Player) {
        $mm->onAbilityHitPlayer($attacker);
    } else {
        $mm->onNpcHit($attacker);
    }
}

    /**
     * @EventHandler
     */
    public function onInventoryTransaction(InventoryTransactionEvent $event) {
        $transaction = $event->getTransaction();
        $player      = $transaction->getPlayer();
        if (!($player instanceof Player)) return;
        if (!fruits\GiroGiro::hasOpenMenu($player)) return;

        $clickedSlot = -1;
        foreach ($transaction->getTransactions() as $trans) {
            $inv = $trans->getInventory();
            if ($inv instanceof fruits\PortalMenuInventory) {
                $clickedSlot = $trans->getSlot();
                break;
            }
        }

        if ($clickedSlot < 0) return;

        $event->setCancelled(true);
        $player->getFloatingInventory()->clearAll();
        $player->getInventory()->sendContents($player);

        fruits\GiroGiro::handleMenuClick($player, $clickedSlot, $this);
    }

    /**
     * @EventHandler
     */
    public function onInventoryClose(InventoryCloseEvent $event) {
        $inv = $event->getInventory();
        if ($inv instanceof fruits\PortalMenuInventory) {
            fruits\GiroGiro::closeMenu($event->getPlayer());
        }
    }
}