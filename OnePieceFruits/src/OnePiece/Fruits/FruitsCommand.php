<?php

namespace OnePiece\Fruits;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class FruitsCommand extends Command {

    /** @var Main */
    private $plugin;

    public function __construct(Main $plugin, $name, $description, $usage) {
        parent::__construct($name, $description, $usage);
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, $label, array $args) {
        if (!($sender instanceof Player)) {
            $sender->sendMessage(TextFormat::RED . "In-game only.");
            return true;
        }

        if (count($args) === 0) {
            return $this->showStatus($sender);
        }

        $sub = strtolower($args[0]);

        switch ($sub) {
            case "help":
                return $this->showHelp($sender);
            case "awaken":
                return $this->handleAwaken($sender);
            case "status":
                return $this->showStatus($sender);
            case "grant":
                return $this->handleGrant($sender, $args);
            default:
                return $this->showHelp($sender);
        }
    }

    private function showHelp(Player $sender) {
        $sender->sendMessage(TextFormat::GOLD . "═══════════════════════");
        $sender->sendMessage(TextFormat::GOLD . "  Transform Commands");
        $sender->sendMessage(TextFormat::GOLD . "═══════════════════════");
        $sender->sendMessage(TextFormat::YELLOW . "/transform" . TextFormat::GRAY . " - View status");
        $sender->sendMessage(TextFormat::YELLOW . "/transform awaken" . TextFormat::GRAY . " - Unlock awakening");
        $sender->sendMessage("");
        $sender->sendMessage(TextFormat::YELLOW . "How to use:");
        $sender->sendMessage(TextFormat::GRAY . "Logia/Zoan form: Sneak + Feather");
        $sender->sendMessage(TextFormat::GRAY . "Awakening: Sneak + Ghast Tear");
        $sender->sendMessage(TextFormat::GRAY . "Fruit abilities: Blaze Rod (tap/sneak+tap)");
        if ($sender->isOp()) {
            $sender->sendMessage(TextFormat::RED . "/transform grant <player>" . TextFormat::GRAY . " - Admin unlock");
        }
        $sender->sendMessage(TextFormat::GOLD . "═══════════════════════");
        return true;
    }

    private function showStatus(Player $sender) {
        $fruitType = $this->plugin->getPlayerFruitType($sender);
        $rarity = $this->plugin->getPlayerFruitRarity($sender);

        $sender->sendMessage(TextFormat::GOLD . "═══════════════════════");
        $sender->sendMessage(TextFormat::GOLD . "  Transformation Status");
        $sender->sendMessage(TextFormat::GOLD . "═══════════════════════");

        if ($fruitType === null) {
            $sender->sendMessage(TextFormat::GRAY . "No Devil Fruit equipped.");
            $sender->sendMessage(TextFormat::GOLD . "═══════════════════════");
            return true;
        }

        $sender->sendMessage(TextFormat::YELLOW . "Fruit Type: " . TextFormat::WHITE . ucfirst($fruitType));
        $sender->sendMessage(TextFormat::YELLOW . "Rarity: " . TextFormat::WHITE . ucfirst($rarity));
        $sender->sendMessage("");

        if ($fruitType === "logia") {
            $active = $this->plugin->getLogiaSystem()->isActive($sender) ? "§aON" : "§cOFF";
            $sender->sendMessage(TextFormat::GOLD . "Logia Form: " . $active);
            $sender->sendMessage(TextFormat::GRAY . "  85% intangibility");
        }

        if ($fruitType === "zoan") {
            $active = $this->plugin->getZoanSystem()->isTransformed($sender) ? "§aON" : "§cOFF";
            $sender->sendMessage(TextFormat::GREEN . "Zoan Form: " . $active);
            $sender->sendMessage(TextFormat::GRAY . "  +40% DMG, +30% DEF, +10 HP");
        }

        $sender->sendMessage("");
        if ($this->plugin->getAwakeningSystem()->hasUnlockedAwakening($sender)) {
            $active = $this->plugin->getAwakeningSystem()->isAwakened($sender) ? "§aON" : "§cOFF";
            $sender->sendMessage(TextFormat::LIGHT_PURPLE . "Awakening: " . $active);
        } else {
            $sender->sendMessage(TextFormat::GRAY . "Awakening: LOCKED");
            $sender->sendMessage(TextFormat::GRAY . "  Need: Lv." . AwakeningSystem::REQUIRED_LEVEL .
                ", " . AwakeningSystem::REQUIRED_HAKI_STAT . " Haki, " .
                number_format(AwakeningSystem::REQUIRED_BOUNTY) . " Bounty");
        }

        $sender->sendMessage(TextFormat::GOLD . "═══════════════════════");
        return true;
    }

    private function handleAwaken(Player $sender) {
        if (!$this->plugin->isInOPWorld($sender)) {
            $sender->sendMessage(TextFormat::RED . "Only in OP world!");
            return true;
        }

        $result = $this->plugin->getAwakeningSystem()->tryUnlock($sender);

        if ($result === true) {
            $sender->sendMessage(TextFormat::LIGHT_PURPLE . "═══════════════════════");
            $sender->sendMessage(TextFormat::LIGHT_PURPLE . "  AWAKENING UNLOCKED!");
            $sender->sendMessage(TextFormat::LIGHT_PURPLE . "═══════════════════════");
            $sender->sendMessage(TextFormat::GRAY . "Use: Sneak + Ghast Tear to activate!");

            $this->plugin->getServer()->broadcastMessage(
                TextFormat::LIGHT_PURPLE . $sender->getName() . " unlocked Devil Fruit Awakening!"
            );

            $this->plugin->getFruitVFX()->spawnAwakeningEffect($sender);
        } else {
            $sender->sendMessage(TextFormat::RED . "Cannot unlock: " . $result);
        }

        return true;
    }

    private function handleGrant(Player $sender, array $args) {
        if (!$sender->isOp()) {
            $sender->sendMessage(TextFormat::RED . "OP only!");
            return true;
        }

        if (!isset($args[1])) {
            $sender->sendMessage(TextFormat::RED . "Usage: /transform grant <player>");
            return true;
        }

        $target = $this->plugin->getServer()->getPlayerExact($args[1]);
        if ($target === null) {
            $sender->sendMessage(TextFormat::RED . "Player not online.");
            return true;
        }

        $this->plugin->getAwakeningSystem()->forceUnlock($target->getName());

        $sender->sendMessage(TextFormat::GREEN . "Granted Awakening to " . $target->getName());
        $target->sendMessage(TextFormat::LIGHT_PURPLE . "Awakening granted by admin!");

        return true;
    }
}