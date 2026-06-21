<?php

namespace OnePiece\Fruits;

use pocketmine\scheduler\PluginTask;

class SkinSpawnTask extends PluginTask {

    private $mainPlugin;
    private $playerName;

    public function __construct(Main $plugin, $playerName) {
        parent::__construct($plugin);
        $this->mainPlugin = $plugin;
        $this->playerName = $playerName;
    }

    public function onRun($currentTick) {
        $this->mainPlugin->getSkinManager()->spawnToViewers($this->playerName);
    }
}