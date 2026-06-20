<?php

namespace OnePiece\Devil;

use OnePiece\Devil\HitEffects;
use pocketmine\Player;
use pocketmine\math\Vector3;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;

abstract class BaseFruit {

    /** @var Main */
    protected $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    abstract public function getId();
    abstract public function getDisplayName();
    abstract public function getDescription();
    abstract public function getType();
    abstract public function getRarity();
    abstract public function useAbility(Player $player, $ability);
    abstract public function getAbilityNames();
    abstract public function getAbilityCooldowns();

    public function onEquip(Player $player) {}
    public function onUnequip(Player $player) {}

    protected function dealAbilityDamage(Player $attacker, $target, $intendedDamage) {
        if ($target instanceof Player) {
            $reason = $this->getInvalidTargetReason($attacker, $target);
            if ($reason !== null) {
                $attacker->sendTip($reason);
                return;
            }
        }
        $this->plugin->setAbilityDamage($attacker->getName(), $intendedDamage);
        $ev = new EntityDamageByEntityEvent($attacker, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $intendedDamage);
        $target->attack($intendedDamage, $ev);

        if ($target instanceof Player) {
            HitEffects::onHit($attacker, $target, $this->getId(), $this->getRarity());
        }
    }

    protected function isValidPlayerTarget(Player $attacker, Player $target) {
        return $this->getInvalidTargetReason($attacker, $target) === null;
    }

protected function getInvalidTargetReason(Player $attacker, Player $target) {
    $raidPlugin = \pocketmine\Server::getInstance()->getPluginManager()->getPlugin("OnePieceRaid");
    if ($raidPlugin !== null && $raidPlugin->isEnabled()) {
        try {
            $am = $raidPlugin->getAwakenManager();
            if ($am !== null) {
                if ($am->isPlayerInAwakenWorld($attacker) || $am->isPlayerInAwakenWorld($target)) {
                    return "§c§l[ABILITY] §r§cPvP is disabled in Awaken Raids!";
                }
            }
        } catch (\Exception $e) {}
    }

    $miscPlugin = \pocketmine\Server::getInstance()->getPluginManager()->getPlugin("OnePieceMISC");
    if ($miscPlugin !== null && $miscPlugin->isEnabled()) {
        try {
            $allyManager = $miscPlugin->getAllyManager();
            $crewManager = $miscPlugin->getCrewManager();

            if ($allyManager !== null && $allyManager->areAllies($attacker->getName(), $target->getName())) {
                if (!self::areInFriendlyPvPTogether($attacker->getName(), $target->getName())) {
                    return "§c§l[ABILITY] §r§cYou cannot use abilities on your ally!";
                }
            }

            if ($crewManager !== null && $crewManager->areCrewmates($attacker->getName(), $target->getName())) {
                if (!self::areInFriendlyPvPTogether($attacker->getName(), $target->getName())) {
                    return "§c§l[ABILITY] §r§cYou cannot use abilities on your crewmate!";
                }
            }
        } catch (\Exception $e) {}
    }

    $combatPlugin = $this->plugin->getCombatPlugin();
    if ($combatPlugin === null || !$combatPlugin->isEnabled()) return null;

    $toggle = $combatPlugin->getCombatToggle();

    if (!$toggle->canPvP($attacker->getName())) {
        return "§c§l[ABILITY] §r§cYou have PvP disabled!";
    }

    if (!$toggle->canPvP($target->getName())) {
        return "§c§l[ABILITY] §r§cThis player has PvP disabled!";
    }

    $statsPlugin = $this->plugin->getStatsPlugin();
    if ($statsPlugin !== null && $statsPlugin->isEnabled()) {
        $spA = $statsPlugin->getStatManager()->getStatPlayer($attacker);
        if ($spA !== null && $spA->getLevel() < 10) {
            return "§c§l[ABILITY] §r§cYou must be Level 10 to use abilities in PvP!";
        }
        $spT = $statsPlugin->getStatManager()->getStatPlayer($target);
        if ($spT !== null && $spT->getLevel() < 10) {
            return "§c§l[ABILITY] §r§cThis player is below Level 10!";
        }
    }

    return null;
}

    private static function areInFriendlyPvPTogether($nameA, $nameB) {
        $combatPlugin = \pocketmine\Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
        if ($combatPlugin === null || !$combatPlugin->isEnabled()) return false;
        try {
            $manager = $combatPlugin->getFriendlyPvPManager();
            if ($manager === null) return false;
            if (!$manager->isInFriendlyPvP($nameA) || !$manager->isInFriendlyPvP($nameB)) return false;
            $opponent = $manager->getFriendlyOpponent($nameA);
            return $opponent !== null && strtolower($opponent) === strtolower($nameB);
        } catch (\Exception $e) {
            return false;
        }
    }

public static function pvpAllowed(Player $attacker, Player $target) {
    $raidPlugin = \pocketmine\Server::getInstance()->getPluginManager()->getPlugin("OnePieceRaid");
    if ($raidPlugin !== null && $raidPlugin->isEnabled()) {
        try {
            $am = $raidPlugin->getAwakenManager();
            if ($am !== null) {
                if ($am->isPlayerInAwakenWorld($attacker) || $am->isPlayerInAwakenWorld($target)) {
                    return false;
                }
            }
        } catch (\Exception $e) {}
    }

    $miscPlugin = \pocketmine\Server::getInstance()->getPluginManager()->getPlugin("OnePieceMISC");
    if ($miscPlugin !== null && $miscPlugin->isEnabled()) {
        try {
            $allyManager = $miscPlugin->getAllyManager();
            $crewManager = $miscPlugin->getCrewManager();
            $inFPvP = self::areInFriendlyPvPTogether($attacker->getName(), $target->getName());

            if (!$inFPvP) {
                if ($allyManager !== null && $allyManager->areAllies($attacker->getName(), $target->getName())) return false;
                if ($crewManager !== null && $crewManager->areCrewmates($attacker->getName(), $target->getName())) return false;
            }
        } catch (\Exception $e) {}
    }

    $combatPlugin = \pocketmine\Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
    if ($combatPlugin === null || !$combatPlugin->isEnabled()) return true;
    $toggle = $combatPlugin->getCombatToggle();
    if (!$toggle->canPvP($attacker->getName())) return false;
    if (!$toggle->canPvP($target->getName())) return false;
    $statsPlugin = \pocketmine\Server::getInstance()->getPluginManager()->getPlugin("OnePieceStats");
    if ($statsPlugin !== null && $statsPlugin->isEnabled()) {
        $spA = $statsPlugin->getStatManager()->getStatPlayer($attacker);
        if ($spA !== null && $spA->getLevel() < 10) return false;
        $spT = $statsPlugin->getStatManager()->getStatPlayer($target);
        if ($spT !== null && $spT->getLevel() < 10) return false;
    }
    return true;
}

public static function staticSafeSetMotion($attacker, $target, $motion) {
    if ($target instanceof Player) {
        if ($attacker === null || !($attacker instanceof Player)) return;
        if (!self::pvpAllowed($attacker, $target)) return;
    }

    $target->motionX = $motion->x;
    $target->motionY = $motion->y;
    $target->motionZ = $motion->z;
    $target->setMotion($motion);

    if ($target instanceof Player) {
        $target->x = $target->x;
        $target->y = $target->y;
        $target->z = $target->z;
    }
}

    public static function staticSafeSetOnFire($attacker, $target, $seconds) {
        if ($target instanceof Player) {
            if ($attacker === null || !($attacker instanceof Player)) return;
            if (!self::pvpAllowed($attacker, $target)) return;
        }
        $target->setOnFire($seconds);
    }

    public static function staticSafeAddEffect($attacker, $target, $effect) {
        if ($target instanceof Player) {
            if ($attacker === null || !($attacker instanceof Player)) return;
            if (!self::pvpAllowed($attacker, $target)) return;
        }
        $target->addEffect($effect);
    }

    protected function canAffectPlayer(Player $attacker, Player $target) {
        return $this->getInvalidTargetReason($attacker, $target) === null;
    }

    protected function safeAddEffect($attacker, $target, $effect) {
        if ($target instanceof Player) {
            if ($attacker === null || !($attacker instanceof Player)) return;
            if (!$this->canAffectPlayer($attacker, $target)) return;
        }
        $target->addEffect($effect);
    }

    protected function safeSetOnFire($attacker, $target, $seconds) {
        if ($target instanceof Player) {
            if ($attacker === null || !($attacker instanceof Player)) return;
            if (!$this->canAffectPlayer($attacker, $target)) return;
        }
        $target->setOnFire($seconds);
    }

protected function safeSetMotion($attacker, $target, $motion) {
    if ($target instanceof Player) {
        if ($attacker === null || !($attacker instanceof Player)) return;
        if (!$this->canAffectPlayer($attacker, $target)) return;
    }

    $target->motionX = $motion->x;
    $target->motionY = $motion->y;
    $target->motionZ = $motion->z;
    $target->setMotion($motion);

    if ($target instanceof Player) {
        $target->x = $target->x;
        $target->y = $target->y;
        $target->z = $target->z;
    }
}

    protected function checkMastery(Player $player, $abilitySlot) {
        $mm = $this->plugin->getMasteryManager();
        if ($mm === null) return true;

        $name = $player->getName();

        if (!$mm->canUseAbility($name, $abilitySlot)) {
            $required = MasteryManager::ABILITY_UNLOCK[$abilitySlot];
            $current = $mm->getLevel($name);
            $tier = $mm->getTierName($name);

            $player->sendMessage("§c§l[LOCKED] §r§7This move requires Mastery Lv.§e" . $required);
            $player->sendMessage("§7Your mastery: " . $tier . " §7(Lv.§f" . $current . "§7)");

            $toNext = $mm->getExpToNextLevel($name);
            if ($toNext > 0) {
                $player->sendMessage("§a" . $mm->getProgressBar($name));
                $player->sendMessage("§7§oKeep using your moves to level up!");
            }
            return false;
        }
        return true;
    }

    protected function grantMasteryExp(Player $player) {
        $mm = $this->plugin->getMasteryManager();
        if ($mm === null) return;
        $mm->onAbilityUse($player);
    }

    protected function grantMasteryHitExp(Player $player) {
        $mm = $this->plugin->getMasteryManager();
        if ($mm === null) return;
        $mm->onAbilityHitPlayer($player);
    }

    protected function grantMasteryNpcExp(Player $player) {
        $mm = $this->plugin->getMasteryManager();
        if ($mm === null) return;
        $mm->onNpcHit($player);
    }

    protected function getMasteryDamage(Player $player, $baseDamage) {
        $mm = $this->plugin->getMasteryManager();
        if ($mm === null) return $baseDamage;
        return $mm->getScaledDamage($player->getName(), $baseDamage);
    }

    protected function getMasteryCooldown(Player $player, $baseCooldown) {
        $mm = $this->plugin->getMasteryManager();
        if ($mm === null) return $baseCooldown;
        return $mm->getScaledCooldown($player->getName(), $baseCooldown);
    }

    protected function getMasteryRange(Player $player, $baseRange) {
        $mm = $this->plugin->getMasteryManager();
        if ($mm === null) return $baseRange;
        return $mm->getScaledRange($player->getName(), $baseRange);
    }

    protected function getMasteryDuration(Player $player, $baseDuration) {
        $mm = $this->plugin->getMasteryManager();
        if ($mm === null) return $baseDuration;
        return $mm->getScaledDuration($player->getName(), $baseDuration);
    }

    protected function getCombinedMultiplier(Player $player) {
        $haki = $this->getHakiMultiplier($player);

        $mm = $this->plugin->getMasteryManager();
        if ($mm === null) return $haki;

        $mastery = $mm->getDamageMultiplier($player->getName());
        return $haki * $mastery;
    }

    protected function showMasteryGain(Player $player, $reason = "") {
        $mm = $this->plugin->getMasteryManager();
        if ($mm === null) return;

        $name = $player->getName();
        $level = $mm->getLevel($name);
        $bar = $mm->getProgressBar($name);
        $toNext = $mm->getExpToNextLevel($name);

        if ($toNext > 0) {
            $player->sendPopup("§eMastery Lv." . $level . " " . $bar . " §7(" . $toNext . " to next)");
        }
    }

    protected function getHakiMultiplier(Player $player) {
        $statsPlugin = $this->plugin->getStatsPlugin();
        if ($statsPlugin === null) return 1.0;

        $statManager = $statsPlugin->getStatManager();
        $sp = $statManager->getStatPlayer($player);
        if ($sp === null) return 1.0;

        $hakiStat = $sp->getStat("haki");
        return $statsPlugin->getStatScaler()->getHakiMultiplier($hakiStat);
    }

    public function getRarityColor() {
        switch ($this->getRarity()) {
            case "common":    return "§a";
            case "rare":      return "§9";
            case "legendary": return "§6";
            case "mythical":  return "§d";
            case "god":       return "§c";
            default:          return "§f";
        }
    }

    protected function getNearbyTargets(Player $player, $radius) {
        $targets = [];
        $pos = $player->getPosition();
        $radiusSq = $radius * $radius;

        foreach ($player->getLevel()->getPlayers() as $t) {
            if ($t->getName() === $player->getName()) continue;
            if ($this->getInvalidTargetReason($player, $t) !== null) continue;
            if ($player->distanceSquared($t) <= $radiusSq) {
                $targets[] = $t;
            }
        }

        foreach ($player->getLevel()->getEntities() as $e) {
            if ($e instanceof Player) continue;
            if ($e->closed || !$e->isAlive()) continue;
            $npcClass     = "OnePiece\\NPC\\NPCEntity";
            $factoryClass = "OnePieceTrades\\Factory\\FactoryEntity";
            $sharkClass   = "OnePiece\\SeaEvent\\SeaSharkEntity";
            $beastClass   = "OnePiece\\SeaEvent\\SeaBeastEntity";
            if (($e instanceof $npcClass) || ($e instanceof $factoryClass) || ($e instanceof $sharkClass) || ($e instanceof $beastClass)) {
                if ($player->distanceSquared($e) <= $radiusSq) {
                    $targets[] = $e;
                }
            }
        }

        return $targets;
    }

    protected function findFrontTarget(Player $player, $maxDist) {
        $dir = $player->getDirectionVector();
        $start = $player->add(0, $player->getEyeHeight(), 0);
        $best = null;
        $bestDist = $maxDist + 1;

        foreach ($player->getLevel()->getPlayers() as $t) {
            if ($t->getName() === $player->getName()) continue;
            if ($this->getInvalidTargetReason($player, $t) !== null) continue;
            $tp = $t->add(0, 1, 0);
            $dist = $start->distance($tp);
            if ($dist > $maxDist || $dist <= 0) continue;
            $to = $tp->subtract($start);
            $norm = new Vector3($to->x / $dist, $to->y / $dist, $to->z / $dist);
            $dot = $dir->x * $norm->x + $dir->y * $norm->y + $dir->z * $norm->z;
            if ($dot > 0.45 && $dist < $bestDist) {
                $bestDist = $dist;
                $best = $t;
            }
        }

        foreach ($player->getLevel()->getEntities() as $e) {
            if ($e instanceof Player) continue;
            if ($e->closed || !$e->isAlive()) continue;
            $npcClass     = "OnePiece\\NPC\\NPCEntity";
            $factoryClass = "OnePieceTrades\\Factory\\FactoryEntity";
            $sharkClass   = "OnePiece\\SeaEvent\\SeaSharkEntity";
            $beastClass   = "OnePiece\\SeaEvent\\SeaBeastEntity";
            if (($e instanceof $npcClass) || ($e instanceof $factoryClass) || ($e instanceof $sharkClass) || ($e instanceof $beastClass)) {
                $tp = $e->add(0, 1, 0);
                $dist = $start->distance($tp);
                if ($dist > $maxDist || $dist <= 0) continue;
                $to = $tp->subtract($start);
                $norm = new Vector3($to->x / $dist, $to->y / $dist, $to->z / $dist);
                $dot = $dir->x * $norm->x + $dir->y * $norm->y + $dir->z * $norm->z;
                if ($dot > 0.45 && $dist < $bestDist) {
                    $bestDist = $dist;
                    $best = $e;
                }
            }
        }

        return $best;
    }

    protected function findNearestTarget(Player $player, $maxDist) {
        $best = null;
        $bestDist = $maxDist + 1;

        foreach ($player->getLevel()->getPlayers() as $t) {
            if ($t->getName() === $player->getName()) continue;
            if ($this->getInvalidTargetReason($player, $t) !== null) continue;
            $d = $player->distance($t);
            if ($d <= $maxDist && $d < $bestDist) {
                $bestDist = $d;
                $best = $t;
            }
        }

        foreach ($player->getLevel()->getEntities() as $e) {
            if ($e instanceof Player) continue;
            if ($e->closed || !$e->isAlive()) continue;
            $npcClass     = "OnePiece\\NPC\\NPCEntity";
            $factoryClass = "OnePieceTrades\\Factory\\FactoryEntity";
            $sharkClass   = "OnePiece\\SeaEvent\\SeaSharkEntity";
            $beastClass   = "OnePiece\\SeaEvent\\SeaBeastEntity";
            if (($e instanceof $npcClass) || ($e instanceof $factoryClass) || ($e instanceof $sharkClass) || ($e instanceof $beastClass)) {
                $d = $player->distance($e);
                if ($d <= $maxDist && $d < $bestDist) {
                    $bestDist = $d;
                    $best = $e;
                }
            }
        }

        return $best;
    }
}