<?php

namespace OnePiece\Devil;

use pocketmine\Player;

class FruitCooldown {

    /** @var Main */
    private $plugin;

    /**
     * Format: [playerName => [abilityKey => expireTimestamp]]
     */
    private $cooldowns = [];

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    /**
     * Set a cooldown
     */
    public function setCooldown(Player $player, $abilityKey, $seconds) {
        $name = $player->getName();
        $this->cooldowns[$name][$abilityKey] = microtime(true) + $seconds;
    }

    /**
     * Check if ability is on cooldown
     */
    public function hasCooldown(Player $player, $abilityKey) {
        $name = $player->getName();
        if (!isset($this->cooldowns[$name][$abilityKey])) {
            return false;
        }
        if (microtime(true) >= $this->cooldowns[$name][$abilityKey]) {
            unset($this->cooldowns[$name][$abilityKey]);
            return false;
        }
        return true;
    }

    /**
     * Get remaining cooldown
     */
    public function getRemainingCooldown(Player $player, $abilityKey) {
        $name = $player->getName();
        if (!isset($this->cooldowns[$name][$abilityKey])) {
            return 0;
        }
        $remaining = $this->cooldowns[$name][$abilityKey] - microtime(true);
        return max(0, $remaining);
    }

    /**
     * Clear all cooldowns for a player
     */
    public function clearAllCooldowns(Player $player) {
        $name = $player->getName();
        unset($this->cooldowns[$name]);
    }

    /**
     * Cleanup expired cooldowns
     */
    public function cleanupExpired() {
        $now = microtime(true);
        foreach ($this->cooldowns as $playerName => $abilities) {
            foreach ($abilities as $key => $expireTime) {
                if ($now >= $expireTime) {
                    unset($this->cooldowns[$playerName][$key]);
                }
            }
            if (empty($this->cooldowns[$playerName])) {
                unset($this->cooldowns[$playerName]);
            }
        }
    }
}