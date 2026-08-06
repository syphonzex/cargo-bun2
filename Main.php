<?php

namespace OnePiece\Fight;

use OnePiece\Fight\Swords\SwordManager;
use OnePiece\Fight\Swords\SwordMastery;
use OnePiece\Fight\Swords\SwordCommand;
use OnePiece\Fight\Swords\swords\Katana;
use OnePiece\Fight\Swords\swords\WadoIchimonji;
use OnePiece\Fight\Swords\swords\Shusui;
use OnePiece\Fight\Swords\swords\Enma;
use OnePiece\Fight\Swords\swords\Yoru;
use OnePiece\Fight\Swords\swords\Trident;
use OnePiece\Fight\FightingStyles\StyleManager;
use OnePiece\Fight\FightingStyles\StyleMastery;
use OnePiece\Fight\FightingStyles\StyleCommand;
use OnePiece\Fight\FightingStyles\styles\FishmanKarate;
use OnePiece\Fight\FightingStyles\styles\BlackLeg;
use OnePiece\Fight\FightingStyles\styles\DragonBreath;
use OnePiece\Fight\FightingStyles\styles\Superhuman;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\Player;
use pocketmine\item\Item;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\scheduler\PluginTask;

class Main extends PluginBase implements Listener {

    const STYLE_ITEM_ID = Item::BONE;

    private $opWorlds = ["OP", "Sea2", "sea3", "Tournament"];

    private $swordManager;
    private $swordMastery;
    private $styleManager;
    private $styleMastery;

    private $combatPlugin = null;
    private $statsPlugin  = null;

    private $modeConfig;
    private $playerModes = [];

    private $damageCounter = [];
    private $damageCounterLast = [];
    private $damageAnimTask = [];
    private $damageAnimShown = [];

    private static $swordItems = [
        Item::WOODEN_SWORD  => true,
        Item::STONE_SWORD   => true,
        Item::IRON_SWORD    => true,
        Item::GOLD_SWORD    => true,
        Item::DIAMOND_SWORD => true,
        Item::IRON_AXE      => true,
        Item::GOLD_HOE      => true,
        Item::IRON_SHOVEL   => true,
    ];

    private static $rarityMeleeBase = [
        "common"    => 3.0,
        "rare"      => 4.5,
        "legendary" => 6.5,
        "mythical"  => 8.0,
    ];

    public function onEnable() {
        $this->getLogger()->info(TextFormat::GREEN . "One Piece Fight System loaded!");

        @mkdir($this->getDataFolder());

        $this->combatPlugin = $this->getServer()->getPluginManager()->getPlugin("OnePieceCombat");
        $this->statsPlugin  = $this->getServer()->getPluginManager()->getPlugin("OnePieceStats");

        $this->modeConfig = new Config($this->getDataFolder() . "mode.yml", Config::YAML);

        $this->swordMastery = new SwordMastery($this);
        $this->swordManager = new SwordManager($this);
        $this->styleMastery = new StyleMastery($this);
        $this->styleManager = new StyleManager($this);

        $this->swordManager->register(new Katana($this));
        $this->swordManager->register(new WadoIchimonji($this));
        $this->swordManager->register(new Shusui($this));
        $this->swordManager->register(new Enma($this));
        $this->swordManager->register(new Yoru($this));
        $this->swordManager->register(new Trident($this));

        $this->styleManager->register(new FishmanKarate($this));
        $this->styleManager->register(new BlackLeg($this));
        $this->styleManager->register(new DragonBreath($this));
        $this->styleManager->register(new Superhuman($this));

        $map = $this->getServer()->getCommandMap();
        $map->register("onepiece", new SwordCommand($this));
        $map->register("onepiece", new StyleCommand($this));
        $map->register("onepiece", new EquipCommand($this));

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new FightTickTask($this), 20);

        foreach ($this->getServer()->getOnlinePlayers() as $player) {
            $this->loadPlayerData($player);
        }
    }

    public function onDisable() {
        foreach ($this->getServer()->getOnlinePlayers() as $player) {
            $this->savePlayerData($player);
        }
    }

    private function loadPlayerData(Player $player) {
        $name = $player->getName();
        $this->swordMastery->load($name);
        $this->styleMastery->load($name);
        $this->loadMode($player);
    }

    private function savePlayerData(Player $player) {
        $name = $player->getName();
        $this->swordMastery->save($name);
        $this->styleMastery->save($name);
    }

    public function loadMode(Player $player) {
        $name = $player->getName();
        $mode = $this->modeConfig->get($name, null);
        if ($mode === null) {
            if ($this->swordMastery->getEquipped($name) !== null) {
                $mode = "sword";
            } elseif ($this->styleMastery->getEquipped($name) !== null) {
                $mode = "style";
            } else {
                $mode = "sword";
            }
        }
        $this->playerModes[$name] = $mode;
    }

    public function getPlayerMode(Player $player) {
        $name = $player->getName();
        return isset($this->playerModes[$name]) ? $this->playerModes[$name] : "sword";
    }

    public function setPlayerMode(Player $player, $mode) {
        $name = $player->getName();
        $this->playerModes[$name] = $mode;
        $this->modeConfig->set($name, $mode);
        $this->modeConfig->save();
    }

    public function updateWeaponSlot(Player $player) {
        $mode = $this->getPlayerMode($player);
        $item = Item::get(Item::AIR);

        if ($mode === "sword") {
            $sword = $this->swordManager->getPlayerSword($player);
            if ($sword !== null) {
                $item = Item::get($sword->getItemId(), 0, 1);
                $item->setCustomName("§r" . $sword->getRarityColor() . $sword->getDisplayName());
                $nbt = $item->getNamedTag();
                $nbt->Unbreakable = new ByteTag("Unbreakable", 1);
                $item->setNamedTag($nbt);
            }
        } elseif ($mode === "style") {
            $style = $this->styleManager->getPlayerStyle($player);
            if ($style !== null) {
                $item = Item::get($style->getItemId(), 0, 1);
                $item->setCustomName("§r" . $style->getRarityColor() . $style->getDisplayName() . TextFormat::GRAY . " Style");
                $nbt = $item->getNamedTag();
                $nbt->Unbreakable = new ByteTag("Unbreakable", 1);
                $item->setNamedTag($nbt);
            }
        }

        $player->getInventory()->setItem(0, $item);
    }

    public function isInOPWorld(Player $player) {
        return in_array($player->getLevel()->getName(), $this->opWorlds);
    }

    public function isSwordItem($itemId) {
        return isset(self::$swordItems[$itemId]);
    }

    public function getSwordManager()  { return $this->swordManager; }
    public function getSwordMastery()  { return $this->swordMastery; }
    public function getStyleManager()  { return $this->styleManager; }
    public function getStyleMastery()  { return $this->styleMastery; }
    public function getCombatPlugin()  { return $this->combatPlugin; }
    public function getStatsPlugin()   { return $this->statsPlugin; }

    public function getStrengthMultiplier(Player $player) {
        if ($this->statsPlugin === null) return 1.0;
        try {
            $sm = $this->statsPlugin->getStatManager();
            if ($sm !== null && $sm->isLoaded($player)) {
                $sp = $sm->getStatPlayer($player);
                if ($sp !== null) {
                    return $this->statsPlugin->getStatScaler()->getAttackMultiplier($sp->getStat("strength"));
                }
            }
        } catch (\Exception $e) {}
        return 1.0;
    }

    public function getSwordStatMultiplier(Player $player) {
        if ($this->statsPlugin === null) return 1.0;
        try {
            $sm = $this->statsPlugin->getStatManager();
            if ($sm !== null && $sm->isLoaded($player)) {
                $sp = $sm->getStatPlayer($player);
                if ($sp !== null) {
                    return $this->statsPlugin->getStatScaler()->getSwordMultiplier($sp->getStat("sword"));
                }
            }
        } catch (\Exception $e) {}
        return 1.0;
    }

    public function getHakiMultiplier(Player $player) {
        if ($this->statsPlugin === null) return 1.0;
        try {
            $sm = $this->statsPlugin->getStatManager();
            if ($sm !== null && $sm->isLoaded($player)) {
                $sp = $sm->getStatPlayer($player);
                if ($sp !== null) {
                    return $this->statsPlugin->getStatScaler()->getHakiMultiplier($sp->getStat("haki"));
                }
            }
        } catch (\Exception $e) {}
        return 1.0;
    }

    public function applyDefenseReduction(Player $victim, $damage) {
        if ($this->statsPlugin === null) return $damage;
        try {
            $sm = $this->statsPlugin->getStatManager();
            if ($sm !== null && $sm->isLoaded($victim)) {
                $sp = $sm->getStatPlayer($victim);
                if ($sp !== null) {
                    return $this->statsPlugin->getStatScaler()->calculatePvPDamage($damage, $sp->getStat("defense"));
                }
            }
        } catch (\Exception $e) {}
        return $damage;
    }

    public function capTournamentDamage(Player $attacker, Player $victim, $type, $rarity, $damage) {
        $tournament = $this->getServer()->getPluginManager()->getPlugin("OnePieceTournament");
        if ($tournament !== null && $tournament->isEnabled() && method_exists($tournament, "capExternalDamage")) {
            return $tournament->capExternalDamage($attacker, $victim, $type, $rarity, $damage);
        }
        return $damage;
    }

    public function canPvP(Player $attacker, Player $victim) {
        if ($this->combatPlugin !== null && $this->combatPlugin->isEnabled()) {
            $toggle = $this->combatPlugin->getCombatToggle();
            if ($toggle !== null) {
                if (!$toggle->canPvP($attacker->getName())) return false;
                if (!$toggle->canPvP($victim->getName())) return false;
            }
        }

        $miscPlugin = $this->getServer()->getPluginManager()->getPlugin("OnePieceMISC");
        if ($miscPlugin !== null && $miscPlugin->isEnabled()) {
            try {
                $ally = $miscPlugin->getAllyManager();
                $crew = $miscPlugin->getCrewManager();
                if ($ally !== null && $ally->areAllies($attacker->getName(), $victim->getName())) return false;
                if ($crew !== null && $crew->areCrewmates($attacker->getName(), $victim->getName())) return false;
            } catch (\Exception $e) {}
        }

        if ($this->statsPlugin !== null && $this->statsPlugin->isEnabled()) {
            $sm = $this->statsPlugin->getStatManager();
            $spA = $sm->getStatPlayer($attacker);
            $spV = $sm->getStatPlayer($victim);
            if ($spA !== null && $spA->getLevel() < 10) return false;
            if ($spV !== null && $spV->getLevel() < 10) return false;
        }

        return true;
    }

    public function canTargetPlayer($attackerName, Player $victim) {
        $attacker = $this->getServer()->getPlayerExact($attackerName);
        if ($attacker === null) return false;
        return $this->canPvP($attacker, $victim);
    }

    private function getHakiBoost(Player $player) {
        $hakiMult = 1.0;
        $hakiPlugin = $this->getServer()->getPluginManager()->getPlugin("OnePieceHaki");
        if ($hakiPlugin !== null && $hakiPlugin->isEnabled()) {
            try {
                $arm = $hakiPlugin->getArmament();
                if ($arm !== null && $arm->isActive($player)) {
                    $hakiMult = $arm->getDamageBoost($player);
                }
            } catch (\Exception $e) {}
        }
        return $hakiMult;
    }

    private function getTierMult($mastLevel) {
        if ($mastLevel < 50) {
            return 0.55 + ($mastLevel / 50) * 0.20;
        } elseif ($mastLevel < 150) {
            return 0.75 + (($mastLevel - 50) / 100) * 0.15;
        } elseif ($mastLevel < 300) {
            return 0.90 + (($mastLevel - 150) / 150) * 0.10;
        }
        return 1.0;
    }

    public function onPlayerJoin(PlayerJoinEvent $event) {
        $player = $event->getPlayer();
        $this->loadPlayerData($player);
    }

    public function onPlayerQuit(PlayerQuitEvent $event) {
        $player = $event->getPlayer();
        $this->savePlayerData($player);
        unset($this->playerModes[$player->getName()]);
        $this->clearDamageAnimation(strtolower($player->getName()));
    }

    public function onPlayerInteract(PlayerInteractEvent $event) {
        $player = $event->getPlayer();
        $item   = $event->getItem();

        if (!$this->isInOPWorld($player)) return;

        $itemId = $item->getId();
        $mode   = $this->getPlayerMode($player);

        if ($mode === "sword" && $this->isSwordItem($itemId)) {
            if (!$this->swordManager->playerHasSword($player)) return;
            $sword = $this->swordManager->getPlayerSword($player);
            if ($sword === null) return;
            if ($sword->getItemId() !== $itemId) return;

            $ability = $player->isSneaking() ? "ability2" : "ability1";
            $cdKey   = $sword->getId() . "_" . $ability;

            if ($this->swordMastery->isCooldown($player->getName(), $cdKey)) {
                $rem = $this->swordMastery->getCooldownRemaining($player->getName(), $cdKey);
                $player->sendTip(TextFormat::YELLOW . "Cooldown: " . round($rem, 1) . "s");
                $event->setCancelled(true);
                return;
            }

            if (!$this->swordMastery->canUseAbility($player->getName(), $ability)) {
                $req = SwordMastery::ABILITY_UNLOCK[$ability];
                $player->sendMessage(TextFormat::RED . "[LOCKED] This move requires Sword Mastery Lv." . $req);
                $event->setCancelled(true);
                return;
            }

            $event->setCancelled(true);
            $cd = $sword->useAbility($player, $ability);
            if ($cd > 0) {
                $scaled = $this->swordMastery->getScaledCooldown($player->getName(), $cd);
                $this->swordMastery->setCooldown($player->getName(), $cdKey, $scaled);
            }
            $this->swordMastery->onUse($player->getName());
            return;
        }

        if ($mode === "style" && $itemId === self::STYLE_ITEM_ID) {
            if (!$this->styleManager->playerHasStyle($player)) return;
            $style = $this->styleManager->getPlayerStyle($player);
            if ($style === null) return;

            $ability = $player->isSneaking() ? "ability2" : "ability1";
            $cdKey   = $style->getId() . "_" . $ability;

            if ($this->styleMastery->isCooldown($player->getName(), $cdKey)) {
                $rem = $this->styleMastery->getCooldownRemaining($player->getName(), $cdKey);
                $player->sendTip(TextFormat::YELLOW . "Cooldown: " . round($rem, 1) . "s");
                $event->setCancelled(true);
                return;
            }

            if (!$this->styleMastery->canUseAbility($player->getName(), $ability)) {
                $req = StyleMastery::ABILITY_UNLOCK[$ability];
                $player->sendMessage(TextFormat::RED . "[LOCKED] This move requires Style Mastery Lv." . $req);
                $event->setCancelled(true);
                return;
            }

            $event->setCancelled(true);
            $cd = $style->useAbility($player, $ability);
            if ($cd > 0) {
                $scaled = $this->styleMastery->getScaledCooldown($player->getName(), $cd);
                $this->styleMastery->setCooldown($player->getName(), $cdKey, $scaled);
            }
            $this->styleMastery->onUse($player->getName());
            return;
        }
    }

    /**
     * @priority HIGHEST
     */
    public function onEntityDamage(EntityDamageEvent $event) {
        if (!($event instanceof EntityDamageByEntityEvent)) return;
        if ($event->isCancelled()) return;

if ($event->getCause() !== EntityDamageEvent::CAUSE_ENTITY_ATTACK) {
    return;
}

        $damager = $event->getDamager();
        $victim  = $event->getEntity();

        // Non-player damagers (NPCs, projectiles, raid abilities) must be
        // filtered out before any Player-only method calls. getDamager() can
        // return null or a non-Player Entity.
        if (!($damager instanceof Player)) return;

        // A Player object can still exist in memory after the player has
        // disconnected or been closed (e.g. during world unload). Calling
        // getInventory() or getLevel() on a closed Player returns null and
        // causes "Call to a member function getItemInHand() on null".
        if ($damager->closed || !$damager->isOnline()) return;

        if (!$this->isInOPWorld($damager)) return;

        if ($victim instanceof Player) {
            // Same guard for the victim — they may also be in a closing state.
            if ($victim->closed || !$victim->isOnline()) return;

            $raidPlugin = $this->getServer()->getPluginManager()->getPlugin("OnePieceRaid");
            if ($raidPlugin !== null && $raidPlugin->isEnabled()) {
                try {
                    $am = $raidPlugin->getAwakenManager();
                    if ($am !== null) {
                        if ($am->isPlayerInAwakenWorld($damager)) {
                            $event->setCancelled(true);
                            $damager->sendTip(TextFormat::RED . "PvP is disabled in Awaken Raids!");
                            return;
                        }
                        if ($am->isPlayerInAwakenWorld($victim)) {
                            $event->setCancelled(true);
                            $damager->sendTip(TextFormat::RED . "This player is in an Awaken Raid!");
                            return;
                        }
                    }
                } catch (\Exception $e) {}
            }
        }

        $mode   = $this->getPlayerMode($damager);
        $heldId = $damager->getInventory()->getItemInHand()->getId();

        if ($mode === "sword" && $this->isSwordItem($heldId)) {
            if (!$this->swordManager->playerHasSword($damager)) return;

            $sword = $this->swordManager->getPlayerSword($damager);
            if ($sword === null) return;
            if ($sword->getItemId() !== $heldId) return;

            if ($victim instanceof Player) {
                if (!$this->canPvP($damager, $victim)) {
                    $event->setCancelled(true);
                    return;
                }
            }

            $base      = $sword->getMeleeDamage();
            $mastMult  = $this->swordMastery->getDamageMultiplier($damager->getName());
            $mastLevel = $this->swordMastery->getLevel($damager->getName());
            $tierMult  = $this->getTierMult($mastLevel);
            $swordStat = $this->getSwordStatMultiplier($damager);
            $hakiMult  = $this->getHakiBoost($damager);

            $masteryDamage = $base * 0.3 * $tierMult * $mastMult;
            $statDamage    = $base * 0.5 * $swordStat;
            $damage        = ($masteryDamage + $statDamage) * $hakiMult;

            if ($victim instanceof Player) {
                $damage = $this->applyDefenseReduction($victim, $damage);
                $damage = $this->capTournamentDamage($damager, $victim, "fight", $sword->getRarity(), $damage);
                if ($damage <= 0) {
                    $event->setCancelled(true);
                    return;
                }
            }

            $damage = max(0.5, $damage);
            $event->setDamage($damage);

            if ($this->combatPlugin !== null && $victim instanceof Player) {
                $this->combatPlugin->getCombatTag()->tagPlayer($damager);
                $this->combatPlugin->getCombatTag()->tagPlayer($victim);
            }

            $this->swordMastery->onMeleeHit($damager->getName());

            $this->showDamageCounter($damager, $damage);
            if ($victim instanceof Player) {
                $victim->sendTip(TextFormat::RED . "- Took " . round($damage, 1) . " damage!");
            }
            return;
        }

        if ($mode === "style" && $heldId === self::STYLE_ITEM_ID) {
            if (!$this->styleManager->playerHasStyle($damager)) return;

            $style = $this->styleManager->getPlayerStyle($damager);
            if ($style === null) return;

            if ($victim instanceof Player) {
                if (!$this->canPvP($damager, $victim)) {
                    $event->setCancelled(true);
                    return;
                }
            }

            $rarity    = $style->getRarity();
            $base      = isset(self::$rarityMeleeBase[$rarity]) ? self::$rarityMeleeBase[$rarity] : 3.0;
            $mastMult  = $this->styleMastery->getDamageMultiplier($damager->getName());
            $mastLevel = $this->styleMastery->getLevel($damager->getName());
            $tierMult  = $this->getTierMult($mastLevel);
            $strMult   = $this->getStrengthMultiplier($damager);
            $hakiMult  = $this->getHakiBoost($damager);

            $masteryDamage = $base * 0.3 * $tierMult * $mastMult;
            $statDamage    = $base * 0.45 * $strMult;
            $damage        = ($masteryDamage + $statDamage) * $hakiMult;

            if ($victim instanceof Player) {
                $damage = $this->applyDefenseReduction($victim, $damage);
                $damage = $this->capTournamentDamage($damager, $victim, "fight", $style->getRarity(), $damage);
                if ($damage <= 0) {
                    $event->setCancelled(true);
                    return;
                }
            }

            $damage = max(0.45, $damage);
            $event->setDamage($damage);

            if ($this->combatPlugin !== null && $victim instanceof Player) {
                $this->combatPlugin->getCombatTag()->tagPlayer($damager);
                $this->combatPlugin->getCombatTag()->tagPlayer($victim);
            }

            $this->styleMastery->onUse($damager->getName());

            $this->showDamageCounter($damager, $damage);
            if ($victim instanceof Player) {
                $victim->sendTip(TextFormat::RED . "- Took " . round($damage, 1) . " damage!");
            }
        }
    }

    public function showDamageCounter(Player $player, $damage) {
        $name = strtolower($player->getName());
        $now = microtime(true);

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
            $this->getServer()->getScheduler()->cancelTask($this->damageAnimTask[$name]);
            unset($this->damageAnimTask[$name]);
        }

        $task = new FightDamageCounterAnimationTask($this, $player->getName());
        $handler = $this->getServer()->getScheduler()->scheduleRepeatingTask($task, 2);
        $this->damageAnimTask[$name] = $handler->getTaskId();
    }

    public function tickDamageAnimation($playerName) {
        $name = strtolower($playerName);
        $player = $this->getServer()->getPlayerExact($playerName);
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
            $color = "§f";
        } elseif ($progress < 0.70) {
            $color = "§e";
        } elseif ($progress < 1.0) {
            $color = "§6";
        } else {
            $color = "§c";
        }

        $player->sendTip($color . "§r" . round($shown, 1) . " DMG");

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
        if (isset($this->damageAnimTask[$name])) {
            $this->getServer()->getScheduler()->cancelTask($this->damageAnimTask[$name]);
            unset($this->damageAnimTask[$name]);
        }
    }

}

class FightDamageCounterAnimationTask extends PluginTask {
    private $plugin;
    private $playerName;

    public function __construct(Main $plugin, $playerName) {
        parent::__construct($plugin);
        $this->plugin = $plugin;
        $this->playerName = $playerName;
    }

    public function onRun($currentTick) {
        $this->plugin->tickDamageAnimation($this->playerName);
    }
}
