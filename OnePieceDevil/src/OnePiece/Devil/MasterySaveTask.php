<?php

namespace OnePiece\Devil;

use pocketmine\scheduler\PluginTask;

class MasterySaveTask extends PluginTask {

    public function onRun($tick) {
        $plugin = $this->getOwner();
        if ($plugin instanceof Main) {
            $plugin->getMasteryManager()->save();
        }
    }
}