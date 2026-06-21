<?php

namespace OnePiece\Devil;

use pocketmine\scheduler\PluginTask;

class DevilTickTask extends PluginTask {

    /** @var Main */
    private $plugin;

    public function __construct(Main $plugin) {
        parent::__construct($plugin);
        $this->plugin = $plugin;
    }

    /**
     * Runs every 20 ticks (1 second)
     */
    public function onRun($currentTick) {
        // Check water weakness for all fruit users
        $this->plugin->getWaterWeakness()->checkAllPlayers();

        // Cleanup expired cooldowns
        $this->plugin->getFruitCooldown()->cleanupExpired();
    }
}