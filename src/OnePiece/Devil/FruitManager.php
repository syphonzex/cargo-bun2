<?php

namespace OnePiece\Devil;

use pocketmine\Player;
use pocketmine\utils\TextFormat;
use pocketmine\entity\Entity;
use pocketmine\entity\Attribute;

class FruitManager {

    private $plugin;
    private $fruits = [];
    private $playerFruits = [];
    
    const DATA_SCALE = 38;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function registerFruit(BaseFruit $fruit) {
        $id = strtolower($fruit->getId());
        $this->fruits[$id] = $fruit;
        $this->plugin->getLogger()->info("Registered fruit: " . $fruit->getDisplayName() . " (" . $id . ")");
    }

    public function getFruit($fruitId) {
        $id = strtolower($fruitId);
        return isset($this->fruits[$id]) ? $this->fruits[$id] : null;
    }

    public function getAllFruits() { return $this->fruits; }

    public function fruitExists($fruitId) {
        return isset($this->fruits[strtolower($fruitId)]);
    }

    public function loadPlayerFruit(Player $player) {
        $name = $player->getName();
        $data = $this->plugin->getFruitStorage()->loadPlayerFruit($name);

        if ($data !== null && isset($data["fruitId"])) {
            $fruitId = $data["fruitId"];
            if ($this->fruitExists($fruitId)) {
                $this->playerFruits[$name] = $fruitId;

                $mm = $this->plugin->getMasteryManager();
                if ($mm !== null && $mm->getData($name) === null) {
                    $mm->initPlayer($name, $fruitId);
                }
            }
        }
    }

public function giveFruitToPlayer(Player $player, $fruitId) {
    $fruitId = strtolower($fruitId);
    $name = $player->getName();

    if (!$this->fruitExists($fruitId)) return false;

    if ($this->playerHasFruit($player)) {
        $this->removeFruitFromPlayer($player);
    }

    $this->playerFruits[$name] = $fruitId;

    $this->plugin->getFruitStorage()->savePlayerFruit($name, [
        "fruitId" => $fruitId,
        "obtainedAt" => time()
    ]);

    $mm = $this->plugin->getMasteryManager();
    if ($mm !== null) {
        $mm->initPlayer($name, $fruitId);

        $player->sendMessage(TextFormat::GOLD . "===============");
        $player->sendMessage(TextFormat::YELLOW . TextFormat::BOLD . "  Your Mastery!");
        $player->sendMessage(TextFormat::GOLD . "===============");
        $player->sendMessage(TextFormat::GRAY . "You start at " . TextFormat::WHITE . "Mastery Lv.1");
        $player->sendMessage(TextFormat::GRAY . "Abilities are " . TextFormat::RED . "weak" . TextFormat::GRAY . " at first.");
        $player->sendMessage(TextFormat::GRAY . "Use them to gain EXP and grow stronger!");
        $player->sendMessage(TextFormat::GRAY . "Use " . TextFormat::WHITE . "/mastery " . TextFormat::GRAY . "to check progress.");
        $player->sendMessage(TextFormat::GOLD . "===============");
    }

    $fruit = $this->getFruit($fruitId);
    if ($fruit !== null) {
        $fruit->onEquip($player);
    }

    $invPlugin = $this->plugin->getServer()->getPluginManager()->getPlugin("OnePieceInventory");
    if ($invPlugin !== null && $invPlugin->isEnabled()) {
        $invPlugin->invalidateCache($player);
    }

    return true;
}

public function removeFruitFromPlayer(Player $player) {
    $name = $player->getName();

    if (!isset($this->playerFruits[$name])) return false;

    $fruitId = $this->playerFruits[$name];
    $fruit = $this->getFruit($fruitId);

    if ($fruit !== null) $fruit->onUnequip($player);

    $this->deactivateTransformations($player);

    $this->cleanupPlayerState($player);

    unset($this->playerFruits[$name]);

    $this->plugin->getFruitStorage()->deletePlayerFruit($name);
    $this->plugin->getFruitCooldown()->clearAllCooldowns($player);

    $mm = $this->plugin->getMasteryManager();
    if ($mm !== null) {
        $mm->resetPlayer($name);
    }

    $player->sendMessage(TextFormat::GRAY . "Your devil fruit powers have faded...");

    $invPlugin = $this->plugin->getServer()->getPluginManager()->getPlugin("OnePieceInventory");
    if ($invPlugin !== null && $invPlugin->isEnabled()) {
        $invPlugin->invalidateCache($player);
    }

    return true;
}

    private function deactivateTransformations(Player $player) {
        $fruitsPlugin = $this->plugin->getServer()->getPluginManager()->getPlugin("OnePieceFruits");
        
        if ($fruitsPlugin === null) return;
        
        try {
            $logiaSystem = $fruitsPlugin->getLogiaSystem();
            if ($logiaSystem !== null && $logiaSystem->isActive($player)) {
                $logiaSystem->deactivate($player);
            }
        } catch (\Exception $ex) {}
        
        try {
            $zoanSystem = $fruitsPlugin->getZoanSystem();
            if ($zoanSystem !== null && $zoanSystem->isTransformed($player)) {
                $zoanSystem->deactivate($player);
            }
        } catch (\Exception $ex) {}
        
        try {
            $skinManager = $fruitsPlugin->getSkinManager();
            if ($skinManager !== null) {
                $skinManager->restoreOriginalSkin($player);
            }
        } catch (\Exception $ex) {}
    }

    private function cleanupPlayerState(Player $player) {
        $player->removeAllEffects();
        
        $player->setDataFlag(Entity::DATA_FLAGS, Entity::DATA_FLAG_INVISIBLE, false);
        
        $player->setMaxHealth(20);
        if ($player->getHealth() > 20) {
            $player->setHealth(20);
        }
        
        $attr = $player->getAttributeMap()->getAttribute(Attribute::MOVEMENT_SPEED);
        if ($attr !== null) {
            $attr->setValue(0.1);
        }
        
       // $player->setNameTag($player->getName());
        
        $player->setDataProperty(self::DATA_SCALE, Entity::DATA_TYPE_FLOAT, 1.0);
    }

    public function playerHasFruit(Player $player) {
        return isset($this->playerFruits[$player->getName()]);
    }

    public function getPlayerFruitId(Player $player) {
        $name = $player->getName();
        return isset($this->playerFruits[$name]) ? $this->playerFruits[$name] : null;
    }

    public function getPlayerFruitName(Player $player) {
        $fruitId = $this->getPlayerFruitId($player);
        if ($fruitId === null) return null;
        $fruit = $this->getFruit($fruitId);
        return ($fruit !== null) ? $fruit->getDisplayName() : $fruitId;
    }

    public function getPlayerFruit(Player $player) {
        $fruitId = $this->getPlayerFruitId($player);
        if ($fruitId === null) return null;
        return $this->getFruit($fruitId);
    }
}