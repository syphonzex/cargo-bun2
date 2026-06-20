<?php

namespace OnePiece\Devil;

use pocketmine\Player;
use pocketmine\utils\Config;

class MasteryManager {

    private $plugin;
    private $dataPath;
    private $cache = [];
    private $expCooldown = [];

    const LEVELS = [
        1   => 0,
        2   => 30,
        3   => 65,
        4   => 105,
        5   => 150,
        6   => 200,
        7   => 255,
        8   => 315,
        9   => 380,
        10  => 450,
        11  => 525,
        12  => 605,
        13  => 690,
        14  => 780,
        15  => 875,
        16  => 975,
        17  => 1080,
        18  => 1190,
        19  => 1305,
        20  => 1425,
        21  => 1550,
        22  => 1680,
        23  => 1815,
        24  => 1955,
        25  => 2100,
        26  => 2250,
        27  => 2405,
        28  => 2565,
        29  => 2730,
        30  => 2900,
        31  => 3075,
        32  => 3255,
        33  => 3440,
        34  => 3630,
        35  => 3825,
        36  => 4025,
        37  => 4230,
        38  => 4440,
        39  => 4655,
        40  => 4875,
        41  => 5100,
        42  => 5330,
        43  => 5565,
        44  => 5805,
        45  => 6050,
        46  => 6300,
        47  => 6555,
        48  => 6815,
        49  => 7080,
        50  => 7350,
    ];

    const ABILITY_UNLOCK = [
        "ability1"        => 1,
        "ability2"        => 15,
        "logia_transform" => 30,
        "zoan_transform"  => 30,
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
        $this->dataPath = $plugin->getDataFolder() . "players/";
        @mkdir($this->dataPath);
    }

    private function getPlayerFile($name) {
        return $this->dataPath . strtolower($name) . ".yml";
    }

    private function getFruitDefault() {
        return [
            "exp"    => 0,
            "level"  => 1,
            "kills"  => 0,
            "hits"   => 0,
            "uses"   => 0,
        ];
    }

    private function getPlayerDefault() {
        return [
            "active_fruit" => null,
            "fruits" => []
        ];
    }

    private function loadPlayer($name) {
        $name = strtolower($name);
        if (isset($this->cache[$name])) {
            return;
        }
        $file = $this->getPlayerFile($name);
        if (file_exists($file)) {
            $config = new Config($file, Config::YAML);
            $data = $config->getAll();
            if (isset($data["fruit"]) && !isset($data["fruits"])) {
                $oldFruit = $data["fruit"];
                $migrated = $this->getPlayerDefault();
                $migrated["active_fruit"] = $oldFruit;
                if ($oldFruit !== null) {
                    $migrated["fruits"][$oldFruit] = [
                        "exp"   => isset($data["exp"]) ? $data["exp"] : 0,
                        "level" => isset($data["level"]) ? $data["level"] : 1,
                        "kills" => isset($data["kills"]) ? $data["kills"] : 0,
                        "hits"  => isset($data["hits"]) ? $data["hits"] : 0,
                        "uses"  => isset($data["uses"]) ? $data["uses"] : 0,
                    ];
                }
                $this->cache[$name] = $migrated;
                $this->savePlayer($name);
            } else {
                $this->cache[$name] = array_merge($this->getPlayerDefault(), $data);
            }
        }
    }

    public function savePlayer($name) {
        $name = strtolower($name);
        if (!isset($this->cache[$name])) {
            return;
        }
        $file = $this->getPlayerFile($name);
        $config = new Config($file, Config::YAML);
        $config->setAll($this->cache[$name]);
        $config->save();
    }

    public function save() {
        foreach ($this->cache as $name => $info) {
            $this->savePlayer($name);
        }
    }

    public function initPlayer($name, $fruitId) {
        $name = strtolower($name);
        $this->loadPlayer($name);
        if (!isset($this->cache[$name])) {
            $this->cache[$name] = $this->getPlayerDefault();
        }
        $this->cache[$name]["active_fruit"] = $fruitId;
        if (!isset($this->cache[$name]["fruits"][$fruitId])) {
            $this->cache[$name]["fruits"][$fruitId] = $this->getFruitDefault();
        }
        $this->savePlayer($name);
    }

    public function resetPlayer($name) {
        $name = strtolower($name);
        $this->loadPlayer($name);
        if (isset($this->cache[$name])) {
            $this->cache[$name]["active_fruit"] = null;
            $this->savePlayer($name);
        }
    }

    public function resetFruitMastery($name, $fruitId) {
        $name = strtolower($name);
        $this->loadPlayer($name);
        if (isset($this->cache[$name]) && isset($this->cache[$name]["fruits"][$fruitId])) {
            unset($this->cache[$name]["fruits"][$fruitId]);
            $this->savePlayer($name);
        }
    }

    public function resetAllMastery($name) {
        $name = strtolower($name);
        unset($this->cache[$name]);
        $file = $this->getPlayerFile($name);
        if (file_exists($file)) {
            unlink($file);
        }
    }

    public function getData($name) {
        $name = strtolower($name);
        $this->loadPlayer($name);
        if (!isset($this->cache[$name])) return null;
        $activeFruit = $this->cache[$name]["active_fruit"];
        if ($activeFruit === null) return null;
        if (!isset($this->cache[$name]["fruits"][$activeFruit])) return null;
        $fruitData = $this->cache[$name]["fruits"][$activeFruit];
        $fruitData["fruit"] = $activeFruit;
        return $fruitData;
    }

    public function getAllMastery($name) {
        $name = strtolower($name);
        $this->loadPlayer($name);
        if (!isset($this->cache[$name])) return [];
        return isset($this->cache[$name]["fruits"]) ? $this->cache[$name]["fruits"] : [];
    }

    public function getActiveFruit($name) {
        $name = strtolower($name);
        $this->loadPlayer($name);
        if (!isset($this->cache[$name])) return null;
        return $this->cache[$name]["active_fruit"];
    }

    private function getActiveFruitData($name) {
        $name = strtolower($name);
        $this->loadPlayer($name);
        if (!isset($this->cache[$name])) return null;
        $activeFruit = $this->cache[$name]["active_fruit"];
        if ($activeFruit === null) return null;
        if (!isset($this->cache[$name]["fruits"][$activeFruit])) return null;
        return $this->cache[$name]["fruits"][$activeFruit];
    }

    public function getLevel($name) {
        $data = $this->getActiveFruitData($name);
        return $data !== null ? $data["level"] : 0;
    }

    public function getExp($name) {
        $data = $this->getActiveFruitData($name);
        return $data !== null ? $data["exp"] : 0;
    }

    public function getFruitLevel($name, $fruitId) {
        $name = strtolower($name);
        $this->loadPlayer($name);
        if (!isset($this->cache[$name])) return 0;
        if (!isset($this->cache[$name]["fruits"][$fruitId])) return 0;
        return $this->cache[$name]["fruits"][$fruitId]["level"];
    }

    public function getFruitExp($name, $fruitId) {
        $name = strtolower($name);
        $this->loadPlayer($name);
        if (!isset($this->cache[$name])) return 0;
        if (!isset($this->cache[$name]["fruits"][$fruitId])) return 0;
        return $this->cache[$name]["fruits"][$fruitId]["exp"];
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
        $name = strtolower($name);
        $this->loadPlayer($name);
        if (!isset($this->cache[$name])) return false;
        $activeFruit = $this->cache[$name]["active_fruit"];
        if ($activeFruit === null) return false;
        if (!isset($this->cache[$name]["fruits"][$activeFruit])) return false;
        if ($this->isMaxLevel($name)) return false;

        $now = microtime(true);
        $key = $name . "_" . $reason;
        if (isset($this->expCooldown[$key])) {
            if ($now - $this->expCooldown[$key] < self::EXP_COOLDOWN) {
                return false;
            }
        }
        $this->expCooldown[$key] = $now;

        $oldLevel = $this->cache[$name]["fruits"][$activeFruit]["level"];
        $this->cache[$name]["fruits"][$activeFruit]["exp"] += $amount;

        switch ($reason) {
            case "use":  $this->cache[$name]["fruits"][$activeFruit]["uses"]++; break;
            case "hit":  $this->cache[$name]["fruits"][$activeFruit]["hits"]++; break;
            case "kill": $this->cache[$name]["fruits"][$activeFruit]["kills"]++; break;
        }

        $this->checkLevelUp($name, $oldLevel);

        return true;
    }

    public function onAbilityUse(Player $player) {
        $this->addExp($player->getName(), self::EXP_ABILITY_USE, "use");
    }

    public function onAbilityHitPlayer(Player $player) {
        $this->addExp($player->getName(), self::EXP_ABILITY_HIT, "hit");
    }

    public function onAbilityKill(Player $player) {
        $this->addExp($player->getName(), self::EXP_ABILITY_KILL, "kill");
    }

    public function onNpcHit(Player $player) {
        $this->addExp($player->getName(), self::EXP_NPC_HIT, "npc_hit");
    }

    public function onNpcKill(Player $player) {
        $this->addExp($player->getName(), self::EXP_NPC_KILL, "npc_kill");
    }

public function onTransform(Player $player) {
    $name = $player->getName();
    $fruitId = $this->getActiveFruit($name);
    if ($fruitId === null) return;
    $fruit = $this->plugin->getFruitManager()->getFruit($fruitId);
    if ($fruit === null) return;
    $type = $fruit->getType();
    if ($type !== "zoan" && $type !== "logia") return;
    $this->addExp($name, self::EXP_TRANSFORM, "transform");
}

    public function onLogiaDodge(Player $player) {
        $this->addExp($player->getName(), self::EXP_DODGE, "dodge");
    }

    private function checkLevelUp($name, $oldLevel) {
        $name = strtolower($name);
        $activeFruit = $this->cache[$name]["active_fruit"];
        $currentExp = $this->cache[$name]["fruits"][$activeFruit]["exp"];
        $newLevel = $oldLevel;

        foreach (self::LEVELS as $lvl => $required) {
            if ($currentExp >= $required) {
                $newLevel = $lvl;
            }
        }

        if ($newLevel > $oldLevel) {
            $this->cache[$name]["fruits"][$activeFruit]["level"] = $newLevel;
            $this->savePlayer($name);

            $player = $this->plugin->getServer()->getPlayer($name);
            if ($player !== null) {
                $this->sendLevelUpMessage($player, $oldLevel, $newLevel);
            }
        }
    }

    private function sendLevelUpMessage(Player $player, $oldLevel, $newLevel) {
        $name = strtolower($player->getName());

        $player->sendMessage("§6═══════════════════════");
        $player->sendMessage("§e§l  MASTERY LEVEL UP!");
        $player->sendMessage("§6═══════════════════════");
        $player->sendMessage("§7Level: §c" . $oldLevel . " §7-> §a" . $newLevel);

        foreach (self::ABILITY_UNLOCK as $ability => $reqLevel) {
            if ($oldLevel < $reqLevel && $newLevel >= $reqLevel) {
                switch ($ability) {
                    case "ability2":
                        $player->sendMessage("§a§l  ABILITY 2 UNLOCKED!");
                        $player->sendMessage("§7Your second move is now available!");
                        break;
                    case "logia_transform":
                    case "zoan_transform":
                        $player->sendMessage("§b§l  TRANSFORMATION UNLOCKED!");
                        $player->sendMessage("§7Sneak + Feather to transform!");
                        break;
                }
            }
        }

        $dmgScale = $this->getDamageMultiplier($name);
        $cdReduce = $this->getCooldownReduction($name);
        $player->sendMessage("§7Damage: §f" . round($dmgScale * 100) . "%");
        $player->sendMessage("§7Cooldown: §f-" . round($cdReduce * 100) . "%");

        $nextUnlock = $this->getNextUnlock($newLevel);
        if ($nextUnlock !== null) {
            $player->sendMessage("§7Next: §e" . $nextUnlock["name"] . " §7at Lv." . $nextUnlock["level"]);
        }

        $player->sendMessage("§6═══════════════════════");
    }

    private function getNextUnlock($currentLevel) {
        $labels = [
            "ability1"        => "Ability 1",
            "ability2"        => "Ability 2",
            "logia_transform" => "Logia/Zoan Transform",
            "zoan_transform"  => null,
        ];

        foreach (self::ABILITY_UNLOCK as $ability => $reqLevel) {
            if ($currentLevel < $reqLevel && isset($labels[$ability]) && $labels[$ability] !== null) {
                return [
                    "name"  => $labels[$ability],
                    "level" => $reqLevel,
                ];
            }
        }
        return null;
    }

    public function canUseAbility($name, $abilitySlot) {
        $name = strtolower($name);
        $this->loadPlayer($name);
        if (!isset($this->cache[$name])) return false;
        $level = $this->getLevel($name);
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
        if ($level >= 50) return "§4§lLEGEND";
        if ($level >= 40) return "§d§lGrandmaster";
        if ($level >= 30) return "§6§lMaster";
        if ($level >= 20) return "§c§lExpert";
        if ($level >= 15) return "§e§lSkilled";
        if ($level >= 8)  return "§a§lTrained";
        return "§7§lBeginner";
    }

    public function getTierColor($name) {
        $level = $this->getLevel($name);
        if ($level >= 50) return "§4";
        if ($level >= 40) return "§d";
        if ($level >= 30) return "§6";
        if ($level >= 20) return "§c";
        if ($level >= 15) return "§e";
        if ($level >= 8)  return "§a";
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