<?php

namespace OnePiece\Fruits;

use pocketmine\Player;
use pocketmine\utils\Config;

class MasteryManager {

    private $plugin;
    private $data;
    private $cache = [];
    private $expCooldown = [];

    const LEVELS = [
        1   => 0,
        2   => 25,
        3   => 50,
        4   => 80,
        5   => 120,
        6   => 160,
        7   => 200,
        8   => 250,
        9   => 310,
        10  => 380,
        11  => 450,
        12  => 530,
        13  => 620,
        14  => 720,
        15  => 830,
        16  => 950,
        17  => 1080,
        18  => 1220,
        19  => 1380,
        20  => 1550,
        21  => 1750,
        22  => 1960,
        23  => 2200,
        24  => 2460,
        25  => 2750,
        26  => 3100,
        27  => 3500,
        28  => 3950,
        29  => 4450,
        30  => 5000,
    ];

    const ABILITY_UNLOCK = [
        "ability1" => 1,
        "ability2" => 5,
        "ability3" => 10,
        "ability4" => 15,
        "ability5" => 20,
    ];

    const EXP_ABILITY_USE    = 3;
    const EXP_ABILITY_HIT    = 5;
    const EXP_ABILITY_KILL   = 15;
    const EXP_NPC_HIT        = 2;
    const EXP_NPC_KILL        = 8;
    const EXP_TRANSFORM      = 4;
    const EXP_DODGE          = 2;

    const EXP_COOLDOWN = 2;

    const DAMAGE_SCALE_MIN = 0.4;
    const DAMAGE_SCALE_MAX = 1.6;

    const COOLDOWN_REDUCTION_MAX = 0.20;

    const RANGE_SCALE_MIN = 0.6;
    const RANGE_SCALE_MAX = 1.3;

    const DURATION_SCALE_MIN = 0.5;
    const DURATION_SCALE_MAX = 1.4;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->data = new Config(
            $plugin->getDataFolder() . "mastery.yml",
            Config::YAML,
            []
        );
        $this->loadAll();
    }

    private function loadAll() {
        foreach ($this->data->getAll() as $name => $info) {
            $this->cache[$name] = [
                "fruit"  => $info["fruit"] ?? null,
                "exp"    => $info["exp"] ?? 0,
                "level"  => $info["level"] ?? 1,
                "kills"  => $info["kills"] ?? 0,
                "hits"   => $info["hits"] ?? 0,
                "uses"   => $info["uses"] ?? 0,
            ];
        }
    }

    public function save() {
        foreach ($this->cache as $name => $info) {
            $this->data->set($name, $info);
        }
        $this->data->save();
    }

    public function savePlayer($name) {
        if (isset($this->cache[$name])) {
            $this->data->set($name, $this->cache[$name]);
            $this->data->save();
        }
    }

    public function initPlayer($name, $fruitId) {
        if (isset($this->cache[$name]) && $this->cache[$name]["fruit"] !== $fruitId) {
            $this->cache[$name] = [
                "fruit"  => $fruitId,
                "exp"    => 0,
                "level"  => 1,
                "kills"  => 0,
                "hits"   => 0,
                "uses"   => 0,
            ];
        } elseif (!isset($this->cache[$name])) {
            $this->cache[$name] = [
                "fruit"  => $fruitId,
                "exp"    => 0,
                "level"  => 1,
                "kills"  => 0,
                "hits"   => 0,
                "uses"   => 0,
            ];
        }
        $this->savePlayer($name);
    }

    public function resetPlayer($name) {
        unset($this->cache[$name]);
        $this->data->remove($name);
        $this->data->save();
    }

    public function getData($name) {
        return isset($this->cache[$name]) ? $this->cache[$name] : null;
    }

    public function getLevel($name) {
        return isset($this->cache[$name]) ? $this->cache[$name]["level"] : 0;
    }

    public function getExp($name) {
        return isset($this->cache[$name]) ? $this->cache[$name]["exp"] : 0;
    }

    public function getExpToNextLevel($name) {
        $level = $this->getLevel($name);
        $nextLevel = $level + 1;
        if (!isset(self::LEVELS[$nextLevel])) return 0;
        $currentExp = $this->getExp($name);
        return max(0, self::LEVELS[$nextLevel] - $currentExp);
    }

    public function getMaxLevel() {
        return max(array_keys(self::LEVELS));
    }

    public function isMaxLevel($name) {
        return $this->getLevel($name) >= $this->getMaxLevel();
    }

    public function addExp($name, $amount, $reason = "use") {
        if (!isset($this->cache[$name])) return false;
        if ($this->isMaxLevel($name)) return false;

        $now = microtime(true);
        $key = $name . "_" . $reason;
        if (isset($this->expCooldown[$key])) {
            if ($now - $this->expCooldown[$key] < self::EXP_COOLDOWN) {
                return false;
            }
        }
        $this->expCooldown[$key] = $now;

        $oldLevel = $this->cache[$name]["level"];
        $this->cache[$name]["exp"] += $amount;

        switch ($reason) {
            case "use":  $this->cache[$name]["uses"]++; break;
            case "hit":  $this->cache[$name]["hits"]++; break;
            case "kill": $this->cache[$name]["kills"]++; break;
        }

        $this->checkLevelUp($name, $oldLevel);

        return true;
    }

    public function onAbilityUse(Player $player) {
        $this->addExp($player->getName(), self::EXP_ABILITY_USE, "use");
    }

    public function onAbilityHitPlayer(Player $player) {
        $name = $player->getName();
        $this->addExp($name, self::EXP_ABILITY_HIT, "hit");
    }

    public function onAbilityKill(Player $player) {
        $name = $player->getName();
        $this->addExp($name, self::EXP_ABILITY_KILL, "kill");
    }

    public function onNpcHit(Player $player) {
        $this->addExp($player->getName(), self::EXP_NPC_HIT, "npc_hit");
    }

    public function onNpcKill(Player $player) {
        $this->addExp($player->getName(), self::EXP_NPC_KILL, "npc_kill");
    }

    public function onTransform(Player $player) {
        $fruitType = $this->plugin->getPlayerFruitType($player);
        if ($fruitType !== "zoan" && $fruitType !== "logia") {
            return;
        }
        $this->addExp($player->getName(), self::EXP_TRANSFORM, "transform");
    }

    public function onLogiaDodge(Player $player) {
        $this->addExp($player->getName(), self::EXP_DODGE, "dodge");
    }

    private function checkLevelUp($name, $oldLevel) {
        $currentExp = $this->cache[$name]["exp"];
        $newLevel = $oldLevel;

        foreach (self::LEVELS as $lvl => $required) {
            if ($currentExp >= $required) {
                $newLevel = $lvl;
            }
        }

        if ($newLevel > $oldLevel) {
            $this->cache[$name]["level"] = $newLevel;
            $this->savePlayer($name);

            $player = $this->plugin->getServer()->getPlayer($name);
            if ($player !== null) {
                $this->sendLevelUpMessage($player, $oldLevel, $newLevel);
            }
        }
    }

    private function sendLevelUpMessage(Player $player, $oldLevel, $newLevel) {
        $name = $player->getName();

        $player->sendMessage("§6═══════════════════════");
        $player->sendMessage("§e§l  MASTERY LEVEL UP!");
        $player->sendMessage("§6═══════════════════════");
        $player->sendMessage("§7Level: §c" . $oldLevel . " §7-> §a" . $newLevel);

        foreach (self::ABILITY_UNLOCK as $ability => $reqLevel) {
            if ($oldLevel < $reqLevel && $newLevel >= $reqLevel) {
                $num = str_replace("ability", "", $ability);
                $player->sendMessage("§a  New Move Unlocked: §f" . ucfirst($ability) . " (Slot " . $num . ")");
            }
        }

        $fruitType = $this->plugin->getPlayerFruitType($player);
        if ($fruitType === "zoan" || $fruitType === "logia") {
            $durScale = $this->getDurationMultiplier($name);
            $player->sendMessage("§7Transform Duration: §f" . round($durScale * 100) . "%");
        }

        $dmgScale = $this->getDamageMultiplier($name);
        $cdReduce = $this->getCooldownReduction($name);
        $player->sendMessage("§7Damage: §f" . round($dmgScale * 100) . "%");
        $player->sendMessage("§7Cooldown: §f-" . round($cdReduce * 100) . "%");

        $nextUnlock = $this->getNextUnlock($newLevel);
        if ($nextUnlock !== null) {
            $player->sendMessage("§7Next Unlock: §e" . $nextUnlock["name"] . " §7at Lv." . $nextUnlock["level"]);
        }

        $player->sendMessage("§6═══════════════════════");

        $this->plugin->getFruitVFX()->sound(
            $player->getLevel(),
            $player->x, $player->y, $player->z,
            1021
        );
    }

    private function getNextUnlock($currentLevel) {
        foreach (self::ABILITY_UNLOCK as $ability => $reqLevel) {
            if ($currentLevel < $reqLevel) {
                return [
                    "name"  => ucfirst($ability),
                    "level" => $reqLevel,
                ];
            }
        }
        return null;
    }

    public function canUseAbility($name, $abilitySlot) {
        if (!isset($this->cache[$name])) return false;
        $level = $this->cache[$name]["level"];
        $required = isset(self::ABILITY_UNLOCK[$abilitySlot]) ? self::ABILITY_UNLOCK[$abilitySlot] : 1;
        return $level >= $required;
    }

    public function getUnlockedAbilities($name) {
        $result = [];
        $level = $this->getLevel($name);
        foreach (self::ABILITY_UNLOCK as $ability => $reqLevel) {
            $result[$ability] = ($level >= $reqLevel);
        }
        return $result;
    }

    public function getUnlockedSlotCount($name) {
        $count = 0;
        foreach ($this->getUnlockedAbilities($name) as $unlocked) {
            if ($unlocked) $count++;
        }
        return $count;
    }

    public function getDamageMultiplier($name) {
        $level = $this->getLevel($name);
        $maxLevel = $this->getMaxLevel();
        $progress = ($level - 1) / max(1, $maxLevel - 1);
        return self::DAMAGE_SCALE_MIN + $progress * (self::DAMAGE_SCALE_MAX - self::DAMAGE_SCALE_MIN);
    }

    public function getCooldownReduction($name) {
        $level = $this->getLevel($name);
        $maxLevel = $this->getMaxLevel();
        $progress = ($level - 1) / max(1, $maxLevel - 1);
        return $progress * self::COOLDOWN_REDUCTION_MAX;
    }

    public function getScaledCooldown($name, $baseCooldown) {
        $reduction = $this->getCooldownReduction($name);
        return max(1, (int)round($baseCooldown * (1.0 - $reduction)));
    }

    public function getRangeMultiplier($name) {
        $level = $this->getLevel($name);
        $maxLevel = $this->getMaxLevel();
        $progress = ($level - 1) / max(1, $maxLevel - 1);
        return self::RANGE_SCALE_MIN + $progress * (self::RANGE_SCALE_MAX - self::RANGE_SCALE_MIN);
    }

    public function getDurationMultiplier($name) {
        $level = $this->getLevel($name);
        $maxLevel = $this->getMaxLevel();
        $progress = ($level - 1) / max(1, $maxLevel - 1);
        return self::DURATION_SCALE_MIN + $progress * (self::DURATION_SCALE_MAX - self::DURATION_SCALE_MIN);
    }

    public function getScaledDamage($name, $baseDamage) {
        return $baseDamage * $this->getDamageMultiplier($name);
    }

    public function getScaledRange($name, $baseRange) {
        return $baseRange * $this->getRangeMultiplier($name);
    }

    public function getScaledDuration($name, $baseDuration) {
        return max(1, (int)round($baseDuration * $this->getDurationMultiplier($name)));
    }

    public function getTierName($name) {
        $level = $this->getLevel($name);
        if ($level >= 25) return "§d§lGrandmaster";
        if ($level >= 20) return "§6§lMaster";
        if ($level >= 15) return "§c§lExpert";
        if ($level >= 10) return "§e§lSkilled";
        if ($level >= 5)  return "§a§lTrained";
        return "§7§lBeginner";
    }

    public function getTierColor($name) {
        $level = $this->getLevel($name);
        if ($level >= 25) return "§d";
        if ($level >= 20) return "§6";
        if ($level >= 15) return "§c";
        if ($level >= 10) return "§e";
        if ($level >= 5)  return "§a";
        return "§7";
    }

    public function getProgressBar($name) {
        $level = $this->getLevel($name);
        $exp = $this->getExp($name);
        $nextLevel = $level + 1;

        if (!isset(self::LEVELS[$nextLevel])) {
            return "§a||||||||||||||||||||§7 MAX";
        }

        $currentReq = self::LEVELS[$level];
        $nextReq = self::LEVELS[$nextLevel];
        $range = $nextReq - $currentReq;
        $progress = $exp - $currentReq;
        $percent = ($range > 0) ? min(1.0, $progress / $range) : 1.0;

        $totalBars = 20;
        $filled = (int)round($percent * $totalBars);
        $empty = $totalBars - $filled;

        $color = $this->getTierColor($name);
        return $color . str_repeat("|", $filled) . "§8" . str_repeat("|", $empty);
    }
}
