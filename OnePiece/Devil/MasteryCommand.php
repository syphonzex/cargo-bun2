<?php

namespace OnePiece\Devil;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class MasteryCommand extends Command {

    private $plugin;

    public function __construct(Main $plugin) {
        parent::__construct("mastery", "Devil Fruit Mastery", "/mastery [player]");
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, $label, array $args) {
        if (!($sender instanceof Player)) {
            if (!isset($args[0])) {
                $sender->sendMessage(TextFormat::RED . "Usage: /mastery <player>");
                return true;
            }
            $target = $this->plugin->getServer()->getPlayerExact($args[0]);
            if ($target === null) {
                $sender->sendMessage(TextFormat::RED . "Player not found.");
                return true;
            }
            $this->showMastery($sender, $target);
            return true;
        }

        if (!isset($args[0])) {
            $this->showMastery($sender, $sender);
            return true;
        }

        $sub = strtolower($args[0]);

        switch ($sub) {
            case "reset":
                return $this->handleReset($sender, $args);
            case "set":
                return $this->handleSet($sender, $args);
            default:
                if ($sender->isOp()) {
                    $target = $this->plugin->getServer()->getPlayerExact($args[0]);
                    if ($target !== null) {
                        $this->showMastery($sender, $target);
                        return true;
                    }
                }
                $this->showMastery($sender, $sender);
                return true;
        }
    }

    private function showMastery(CommandSender $viewer, Player $target) {
        $mm = $this->plugin->getMasteryManager();
        $name = $target->getName();

        $data = $mm->getData($name);
        if ($data === null || $data["fruit"] === null) {
            $fruitId = null;
            $fm = $this->plugin->getFruitManager();
            if ($fm !== null) {
                $fruitId = $fm->getPlayerFruitId($target);
            }
            if ($fruitId !== null) {
                $mm->initPlayer($name, $fruitId);
                $data = $mm->getData($name);
            }
            if ($data === null || $data["fruit"] === null) {
                $viewer->sendMessage(TextFormat::RED . $name . " has no Devil Fruit. Eat a fruit first!");
                return;
            }
        }

        $level   = $mm->getLevel($name);
        $exp     = $mm->getExp($name);
        $toNext  = $mm->getExpToNextLevel($name);
        $tier    = $mm->getTierName($name);
        $bar     = $mm->getProgressBar($name);
        $dmgMult = round($mm->getDamageMultiplier($name) * 100);
        $cdRed   = round($mm->getCooldownReduction($name) * 100);
        $range   = round($mm->getRangeMultiplier($name), 2);

        $viewer->sendMessage(TextFormat::GOLD . "═══════════════════════");
        $viewer->sendMessage(TextFormat::GOLD . "  " . $target->getName() . "'s Mastery");
        $viewer->sendMessage(TextFormat::GOLD . "═══════════════════════");
        $viewer->sendMessage(TextFormat::YELLOW . "Fruit: " . TextFormat::WHITE . $data["fruit"]);
        $viewer->sendMessage(TextFormat::YELLOW . "Tier:  " . $tier);
        $viewer->sendMessage(TextFormat::YELLOW . "Level: " . TextFormat::WHITE . $level . TextFormat::GRAY . " / " . $mm->getMaxLevel());
        $viewer->sendMessage(TextFormat::YELLOW . "EXP:   " . TextFormat::WHITE . $exp . TextFormat::GRAY . " (+" . $toNext . " to next)");
        $viewer->sendMessage(TextFormat::YELLOW . "Progress: " . $bar);
        $viewer->sendMessage("");
        $viewer->sendMessage(TextFormat::YELLOW . "Damage:    " . TextFormat::WHITE . $dmgMult . "%");
        $viewer->sendMessage(TextFormat::YELLOW . "Cooldown:  " . TextFormat::WHITE . "-" . $cdRed . "%");
        $viewer->sendMessage(TextFormat::YELLOW . "Range:     " . TextFormat::WHITE . $range . "x");

        $unlocked = $mm->getUnlockedAbilities($name);
        $labels = [
            "ability1"        => "Ability 1",
            "ability2"        => "Ability 2",
            "logia_transform" => "Logia/Zoan Transform",
            "zoan_transform"  => null,
        ];
        $viewer->sendMessage("");
        $viewer->sendMessage(TextFormat::YELLOW . "Unlocks:");
        foreach ($unlocked as $ability => $isUnlocked) {
            if (!isset($labels[$ability]) || $labels[$ability] === null) continue;
            $req = MasteryManager::ABILITY_UNLOCK[$ability];
            $labelText = $labels[$ability];
            if ($isUnlocked) {
                $viewer->sendMessage(TextFormat::GREEN . "  # " . $labelText);
            } else {
                $viewer->sendMessage(TextFormat::RED . "  X " . $labelText . TextFormat::GRAY . " (Lv." . $req . ")");
            }
        }

        $viewer->sendMessage("");
        $viewer->sendMessage(TextFormat::GRAY . "Hits: " . ($data["hits"] ?? 0) .
            "  Kills: " . ($data["kills"] ?? 0) .
            "  Uses: " . ($data["uses"] ?? 0));
        $viewer->sendMessage(TextFormat::GOLD . "═══════════════════════");
    }

    private function handleReset(Player $sender, array $args) {
        if (!$sender->isOp()) {
            $sender->sendMessage(TextFormat::RED . "OP only.");
            return true;
        }
        $targetName = isset($args[1]) ? $args[1] : $sender->getName();
        $this->plugin->getMasteryManager()->resetPlayer($targetName);
        $sender->sendMessage(TextFormat::GREEN . "Reset mastery for " . $targetName . ".");
        return true;
    }

    private function handleSet(Player $sender, array $args) {
        if (!$sender->isOp()) {
            $sender->sendMessage(TextFormat::RED . "OP only.");
            return true;
        }
        if (!isset($args[1]) || !isset($args[2]) || !is_numeric($args[2])) {
            $sender->sendMessage(TextFormat::RED . "Usage: /mastery set <player> <level>");
            return true;
        }
        $targetName = $args[1];
        $level = max(1, min((int)$args[2], $this->plugin->getMasteryManager()->getMaxLevel()));

        $mm = $this->plugin->getMasteryManager();
        $data = $mm->getData($targetName);
        if ($data === null) {
            $sender->sendMessage(TextFormat::RED . "No mastery data for " . $targetName . ".");
            return true;
        }

        $levels = MasteryManager::LEVELS;
        $exp = isset($levels[$level]) ? $levels[$level] : 0;
        $currentExp = $mm->getExp($targetName);
        $diff = $exp - $currentExp;
        if ($diff > 0) {
            $mm->addExp($targetName, $diff, "admin");
        } elseif ($diff < 0) {
            $name = strtolower($targetName);
            $data = $mm->getData($name);
            if ($data !== null) {
                $mm->resetPlayer($name);
                $mm->initPlayer($name, $data["fruit"]);
                if ($exp > 0) {
                    $mm->addExp($name, $exp, "admin");
                }
            }
        }
        $sender->sendMessage(TextFormat::GREEN . "Set " . $targetName . " mastery to level " . $level . ".");
        return true;
    }
}