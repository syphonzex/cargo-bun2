<?php

namespace OnePiece\Fruits;

use pocketmine\scheduler\PluginTask;

class FruitsTickTask extends PluginTask {

    /** @var Main */
    private $plugin;

    public function __construct(Main $plugin) {
        parent::__construct($plugin);
        $this->plugin = $plugin;
    }

    /**
     * Runs every 10 ticks (0.5 seconds)
     */
    public function onRun($currentTick) {
        $this->plugin->getLogiaSystem()->tick();
        $this->plugin->getZoanSystem()->tick();
        $this->plugin->getAwakeningSystem()->tick();
    }
}