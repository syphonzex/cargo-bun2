<?php

namespace OnePiece\Devil;

use pocketmine\scheduler\PluginTask;

class IceWalkTickTask extends PluginTask {

    private $plugin;

    public function __construct(Main $plugin) {
        parent::__construct($plugin);
        $this->plugin = $plugin;
    }

    public function onRun($currentTick) {
        $this->plugin->getIceWalkManager()->tick();
    }
}
