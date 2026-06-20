<?php

namespace OnePiece\Devil;

use pocketmine\Player;
use pocketmine\block\Block;
use pocketmine\entity\Effect;
use pocketmine\utils\TextFormat;

class WaterWeakness {

    /** @var Main */
    private $plugin;

    /**
     * Track which players are currently weakened
     * Format: [playerName => bool]
     */
    private $weakened = [];

    /** Damage per second while in water */
    const WATER_DAMAGE = 2.0;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    /**
     * Check all fruit users for water contact
     */
    public function checkAllPlayers() {
        $fruitManager = $this->plugin->getFruitManager();

        foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) {
            if (!$this->plugin->isInOPWorld($player)) {
                if ($this->isWeakened($player)) {
                    $this->removeWeakness($player);
                }
                continue;
            }

            // Only affect fruit users
            if (!$fruitManager->playerHasFruit($player)) {
                if ($this->isWeakened($player)) {
                    $this->removeWeakness($player);
                }
                continue;
            }

            // Skip if riding a boat
            if ($this->isRidingBoat($player)) {
                if ($this->isWeakened($player)) {
                    $this->removeWeakness($player);
                }
                continue;
            }

            // Check if player is in water
            if ($this->isInWater($player)) {
                $this->applyWeakness($player);
            } else {
                if ($this->isWeakened($player)) {
                    $this->removeWeakness($player);
                }
            }
        }
    }

    /**
     * Check if player is riding a boat entity
     */
    private function isRidingBoat(Player $player) {
        // Method 1: Check linked entity
        $linked = $player->getLinkedEntity();
        if ($linked !== null) {
            // Boat entity network ID is 90
            if ($linked instanceof \pocketmine\entity\Entity) {
                $name = get_class($linked);
                if (stripos($name, "Boat") !== false) {
                    return true;
                }
                // Also check by entity type name
                if ($linked->getSaveId() === "Boat") {
                    return true;
                }
            }
            // If riding anything, assume safe
            return true;
        }

        // Method 2: Check if boat entity is very close beneath player
        $level = $player->getLevel();
        foreach ($level->getEntities() as $entity) {
            if ($entity instanceof $player) {
                continue;
            }

            $entityClass = get_class($entity);
            if (stripos($entityClass, "Boat") === false) {
                continue;
            }

            // Check if player is sitting on this boat
            $dist = $player->distance($entity);
            if ($dist < 2.0 && abs($player->y - $entity->y) < 1.5) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if player is touching water
     */
    public function isInWater(Player $player) {
        $block = $player->getLevel()->getBlock($player->floor());
        $blockAbove = $player->getLevel()->getBlock($player->floor()->add(0, 1, 0));

        $waterIds = [Block::WATER, Block::STILL_WATER];

        if (in_array($block->getId(), $waterIds) || in_array($blockAbove->getId(), $waterIds)) {
            return true;
        }

        $feetBlock = $player->getLevel()->getBlock($player->add(0, 0.4, 0));
        if (in_array($feetBlock->getId(), $waterIds)) {
            return true;
        }

        return false;
    }

    /**
     * Apply water weakness effects
     */
    public function applyWeakness(Player $player) {
        $name = $player->getName();
        $wasWeakened = isset($this->weakened[$name]) && $this->weakened[$name];

        $this->weakened[$name] = true;

        $slow = Effect::getEffect(Effect::SLOWNESS);
        $slow->setAmplifier(2);
        $slow->setDuration(60);
        $slow->setVisible(false);
        $player->addEffect($slow);

        $weak = Effect::getEffect(Effect::WEAKNESS);
        $weak->setAmplifier(1);
        $weak->setDuration(60);
        $weak->setVisible(false);
        $player->addEffect($weak);

        $fatigue = Effect::getEffect(Effect::MINING_FATIGUE);
        $fatigue->setAmplifier(2);
        $fatigue->setDuration(60);
        $fatigue->setVisible(false);
        $player->addEffect($fatigue);

        $player->attack(self::WATER_DAMAGE, new \pocketmine\event\entity\EntityDamageEvent(
            $player,
            \pocketmine\event\entity\EntityDamageEvent::CAUSE_DROWNING,
            self::WATER_DAMAGE
        ));

        if (!$wasWeakened) {
            $player->sendMessage(TextFormat::AQUA . "> The sea drains your power! Get out of the water!");
        }
        $player->sendTip(TextFormat::AQUA . "> WATER WEAKNESS! <");
    }

    /**
     * Remove water weakness effects
     */
    public function removeWeakness(Player $player) {
        $name = $player->getName();

        if (!isset($this->weakened[$name]) || !$this->weakened[$name]) {
            return;
        }

        $this->weakened[$name] = false;

        if ($player->hasEffect(Effect::SLOWNESS)) {
            $player->removeEffect(Effect::SLOWNESS);
        }
        if ($player->hasEffect(Effect::WEAKNESS)) {
            $player->removeEffect(Effect::WEAKNESS);
        }
        if ($player->hasEffect(Effect::MINING_FATIGUE)) {
            $player->removeEffect(Effect::MINING_FATIGUE);
        }

        $player->sendMessage(TextFormat::GREEN . "# Power restored.");
    }

    /**
     * Check if a player is currently weakened
     */
    public function isWeakened(Player $player) {
        $name = $player->getName();
        return isset($this->weakened[$name]) && $this->weakened[$name];
    }

    /**
     * Cleanup player data
     */
    public function cleanup(Player $player) {
        $name = $player->getName();
        unset($this->weakened[$name]);
    }
}