<?php

namespace OnePiece\Devil;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use pocketmine\item\Item;

class FruitCommand extends Command {

    private $plugin;

    public function __construct(Main $plugin, $name, $description, $usage) {
        parent::__construct($name, $description, $usage);
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, $label, array $args) {
        if (!($sender instanceof Player)) {
            $sender->sendMessage(TextFormat::RED . "This command can only be used in-game.");
            return true;
        }

        $cmd = $this->getName();

        if ($cmd === "fruits") {
            $page = 1;
            if (isset($args[0]) && is_numeric($args[0])) {
                $page = max(1, (int)$args[0]);
            }
            return $this->handleList($sender, $page);
        }

        if ($cmd === "fruit") {
            if (count($args) === 0) {
                return $this->handleHelp($sender);
            }

            $sub = strtolower($args[0]);

            switch ($sub) {
                case "help":
                    return $this->handleHelp($sender);
                case "info":
                    return $this->handleInfo($sender);
                case "remove":
                    return $this->handleRemove($sender);
                case "give":
                    return $this->handleGive($sender, $args);
                case "spawn":
                    return $this->handleSpawn($sender, $args);
                case "revoke":
                    return $this->handleRevoke($sender, $args);
                case "awaken":
                    return $this->handleAwakenToggle($sender, $args);
                default:
                    $sender->sendMessage(TextFormat::RED . "Unknown subcommand. Use /fruit help");
                    return true;
            }
        }

        return true;
    }

    private function handleHelp(Player $sender) {
        $sender->sendMessage(TextFormat::GOLD . "═══════════════════════");
        $sender->sendMessage(TextFormat::GOLD . "  Devil Fruit Commands");
        $sender->sendMessage(TextFormat::GOLD . "═══════════════════════");
        $sender->sendMessage(TextFormat::YELLOW . "/fruit info " . TextFormat::GRAY . "- View your current fruit");
        $sender->sendMessage(TextFormat::YELLOW . "/fruit remove " . TextFormat::GRAY . "- Remove your fruit");
        $sender->sendMessage(TextFormat::YELLOW . "/fruit awaken <1|2> " . TextFormat::GRAY . "- Toggle awakened/base");
        $sender->sendMessage(TextFormat::YELLOW . "/fruits [page] " . TextFormat::GRAY . "- List all available fruits");
        if ($sender->isOp()) {
            $sender->sendMessage(TextFormat::RED . "/fruit give <player> <fruitId> " . TextFormat::GRAY . "- Give fruit (OP)");
            $sender->sendMessage(TextFormat::RED . "/fruit spawn <player> <fruitId> " . TextFormat::GRAY . "- Spawn fruit item (OP)");
            $sender->sendMessage(TextFormat::RED . "/fruit revoke <player> " . TextFormat::GRAY . "- Remove player's fruit (OP)");
        }
        $sender->sendMessage(TextFormat::GOLD . "═══════════════════════");
        $sender->sendMessage(TextFormat::GRAY . "How to eat: Hold a named Golden Apple and eat it!");
        $sender->sendMessage(TextFormat::GRAY . "How to use: Hold Blaze Rod and tap (or sneak+tap)");
        return true;
    }

    private function handleInfo(Player $sender) {
        $fruitManager = $this->plugin->getFruitManager();
        if (!$fruitManager->playerHasFruit($sender)) {
            $sender->sendMessage(TextFormat::GRAY . "You don't have a Devil Fruit.");
            return true;
        }

        $fruit = $fruitManager->getPlayerFruit($sender);
        if ($fruit === null) {
            $sender->sendMessage(TextFormat::RED . "Error loading your fruit data.");
            return true;
        }

        $sender->sendMessage(TextFormat::GOLD . "═══════════════════════");
        $sender->sendMessage($fruit->getRarityColor() . "  " . $fruit->getDisplayName());
        $sender->sendMessage(TextFormat::GOLD . "═══════════════════════");
        $sender->sendMessage(TextFormat::WHITE . $fruit->getDescription());
        $sender->sendMessage(TextFormat::GRAY . "Type: " . ucfirst($fruit->getType()));
        $sender->sendMessage(TextFormat::GRAY . "Rarity: " . $fruit->getRarityColor() . ucfirst($fruit->getRarity()));
        $sender->sendMessage("");

        $abilities = $fruit->getAbilityNames();
        $cooldowns = $fruit->getAbilityCooldowns();

        $sender->sendMessage(TextFormat::YELLOW . "Abilities:");
        if (isset($abilities["ability1"])) {
            $cd1 = isset($cooldowns["ability1"]) ? $cooldowns["ability1"] : "?";
            $sender->sendMessage(TextFormat::WHITE . "  1. " . $abilities["ability1"] . TextFormat::GRAY . " (Tap Blaze Rod) [" . $cd1 . "s CD]");
        }
        if (isset($abilities["ability2"])) {
            $cd2 = isset($cooldowns["ability2"]) ? $cooldowns["ability2"] : "?";
            $sender->sendMessage(TextFormat::WHITE . "  2. " . $abilities["ability2"] . TextFormat::GRAY . " (Sneak + Blaze Rod) [" . $cd2 . "s CD]");
        }

        $sender->sendMessage(TextFormat::GOLD . "═══════════════════════");
        return true;
    }

    private function handleRemove(Player $sender) {
        if (!$this->plugin->isInOPWorld($sender)) {
            $sender->sendMessage(TextFormat::RED . "You can only remove fruits in the LightPiece!");
            return true;
        }

        $fruitManager = $this->plugin->getFruitManager();
        if (!$fruitManager->playerHasFruit($sender)) {
            $sender->sendMessage(TextFormat::RED . "You don't have a Devil Fruit to remove.");
            return true;
        }

        $fruitName = $fruitManager->getPlayerFruitName($sender);

        $inv = $sender->getInventory();
        for ($i = 0; $i < $inv->getSize(); $i++) {
            $item = $inv->getItem($i);
            if ($item->getId() === Item::BLAZE_ROD || $item->getId() === Item::FEATHER) {
                $inv->setItem($i, Item::get(Item::AIR));
            }
        }

        $fruitManager->removeFruitFromPlayer($sender);
        $sender->sendMessage(TextFormat::GREEN . "Your " . $fruitName . " has been removed.");
        $sender->sendMessage(TextFormat::GRAY . "You can now eat a new Devil Fruit.");
        return true;
    }

    private function handleGive(Player $sender, array $args) {
        if (!$sender->isOp()) {
            $sender->sendMessage(TextFormat::RED . "You need to be OP to use this command!");
            return true;
        }

        if (count($args) < 3) {
            $sender->sendMessage(TextFormat::RED . "Usage: /fruit give <player> <fruitId>");
            return true;
        }

        $targetName = $args[1];
        $fruitId = strtolower($args[2]);

        $target = $this->plugin->getServer()->getPlayerExact($targetName);
        if ($target === null) {
            $sender->sendMessage(TextFormat::RED . "Player not found or not online.");
            return true;
        }

        $fruitManager = $this->plugin->getFruitManager();
        if (!$fruitManager->fruitExists($fruitId)) {
            $sender->sendMessage(TextFormat::RED . "Fruit '" . $fruitId . "' does not exist!");
            $sender->sendMessage(TextFormat::GRAY . "Use /fruits to see available fruits.");
            return true;
        }

        $fruit = $fruitManager->getFruit($fruitId);
        $fruitManager->giveFruitToPlayer($target, $fruitId);
        $sender->sendMessage(TextFormat::GREEN . "# Gave " . $fruit->getDisplayName() . " to " . $targetName);
        $target->sendMessage(TextFormat::GOLD . "> You received the " . $fruit->getDisplayName() . "!");
        $target->sendMessage(TextFormat::RED . "! You are now weak to water!");
        return true;
    }

    private function handleSpawn(Player $sender, array $args) {
        if (!$sender->isOp()) {
            $sender->sendMessage(TextFormat::RED . "You need to be OP to use this command!");
            return true;
        }

        if (count($args) < 3) {
            $sender->sendMessage(TextFormat::RED . "Usage: /fruit spawn <player> <fruitId>");
            return true;
        }

        $targetName = $args[1];
        $fruitId = strtolower($args[2]);

        $target = $this->plugin->getServer()->getPlayerExact($targetName);
        if ($target === null) {
            $sender->sendMessage(TextFormat::RED . "Player not found or not online.");
            return true;
        }

        $fruitManager = $this->plugin->getFruitManager();
        if (!$fruitManager->fruitExists($fruitId)) {
            $sender->sendMessage(TextFormat::RED . "Fruit '" . $fruitId . "' does not exist!");
            return true;
        }

        $fruit = $fruitManager->getFruit($fruitId);
        $item = Item::get(Item::GOLDEN_APPLE, 0, 1);
        $item->setCustomName($fruit->getRarityColor() . $fruit->getDisplayName());
        $target->getInventory()->addItem($item);
        $target->sendMessage(TextFormat::GREEN . "# Given " . $fruit->getDisplayName() . " in your inventory!");
        return true;
    }

    private function handleRevoke(Player $sender, array $args) {
        if (!$sender->isOp()) {
            $sender->sendMessage(TextFormat::RED . "You need to be OP to use this command!");
            return true;
        }

        if (count($args) < 2) {
            $sender->sendMessage(TextFormat::RED . "Usage: /fruit revoke <player>");
            return true;
        }

        $targetName = $args[1];
        $target = $this->plugin->getServer()->getPlayerExact($targetName);
        if ($target === null) {
            $sender->sendMessage(TextFormat::RED . "Player not found or not online.");
            return true;
        }

        $fruitManager = $this->plugin->getFruitManager();
        if (!$fruitManager->playerHasFruit($target)) {
            $sender->sendMessage(TextFormat::RED . $targetName . " doesn't have a Devil Fruit.");
            return true;
        }

        $fruitName = $fruitManager->getPlayerFruitName($target);
        $fruitManager->removeFruitFromPlayer($target);
        $sender->sendMessage(TextFormat::GREEN . "# Revoked " . $fruitName . " from " . $targetName);
        $target->sendMessage(TextFormat::RED . "! Your " . $fruitName . " was removed!");
        return true;
    }

    private function handleAwakenToggle(Player $sender, array $args) {
        if (!isset($args[1]) || !in_array($args[1], ["1", "2"])) {
            $sender->sendMessage(TextFormat::RED . "Usage: /fruit awaken <1|2>");
            return true;
        }

        $fruitManager = $this->plugin->getFruitManager();
        if (!$fruitManager->playerHasFruit($sender)) {
            $sender->sendMessage(TextFormat::RED . "You don't have a Devil Fruit.");
            return true;
        }

        $raidPlugin = $this->plugin->getServer()->getPluginManager()->getPlugin("OnePieceRaid");
        if ($raidPlugin === null) {
            $sender->sendMessage(TextFormat::RED . "Awakening system is not available.");
            return true;
        }

        $am = $raidPlugin->getAwakenManager();
        $fruitId = $fruitManager->getPlayerFruitId($sender);
        $raidKey = $am->getRaidKeyForFruit($fruitId);

        if ($raidKey === null) {
            $sender->sendMessage(TextFormat::RED . "Your fruit does not have an awakened form.");
            return true;
        }

        $slot = "ability" . $args[1];
        $name = $sender->getName();

        if (!$am->hasAbilityAwakened($name, $raidKey, $slot)) {
            $sender->sendMessage(TextFormat::RED . "You have not unlocked Awakening for ability " . $args[1] . " yet.");
            return true;
        }

        $currentMode = $am->getAbilityMode($name, $raidKey, $slot);
        $newMode = ($currentMode === "awakened") ? "base" : "awakened";
        $am->setAbilityMode($name, $raidKey, $slot, $newMode);

        $label = ($args[1] === "1") ? "Ability 1" : "Ability 2";
        if ($newMode === "awakened") {
            $sender->sendMessage(TextFormat::GOLD . $label . " switched to Awakened.");
        } else {
            $sender->sendMessage(TextFormat::GRAY . $label . " switched to Base.");
        }

        return true;
    }

    private function handleList(Player $sender, $page = 1) {
        $fruitManager = $this->plugin->getFruitManager();
        $allFruits = $fruitManager->getAllFruits();

        if (empty($allFruits)) {
            $sender->sendMessage(TextFormat::GRAY . "No fruits registered yet.");
            return true;
        }

        $fruits = array_values($allFruits);
        $perPage = 6;
        $totalPages = (int)ceil(count($fruits) / $perPage);
        $page = max(1, min($page, $totalPages));
        $start = ($page - 1) * $perPage;
        $pageFruits = array_slice($fruits, $start, $perPage);

        $sender->sendMessage(TextFormat::GOLD . "═══════════════════════");
        $sender->sendMessage(TextFormat::GOLD . "  Devil Fruits §7(Page " . $page . "/" . $totalPages . ")");
        $sender->sendMessage(TextFormat::GOLD . "═══════════════════════");

        foreach ($pageFruits as $i => $fruit) {
            $num = $start + $i + 1;
            $color = $fruit->getRarityColor();
            $type = ucfirst($fruit->getType());
            $rarity = ucfirst($fruit->getRarity());
            $sender->sendMessage(
                TextFormat::GRAY . $num . ". " .
                $color . $fruit->getDisplayName() .
                TextFormat::DARK_GRAY . " [" . $type . "] " .
                $color . $rarity
            );
        }

        $sender->sendMessage(TextFormat::GOLD . "═══════════════════════");
        if ($page < $totalPages) {
            $sender->sendMessage(TextFormat::GRAY . "Use /fruits " . ($page + 1) . " for the next page.");
        }

        return true;
    }
}