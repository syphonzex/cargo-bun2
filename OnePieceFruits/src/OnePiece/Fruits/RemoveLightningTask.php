<?php

namespace OnePiece\Fruits;

use pocketmine\scheduler\PluginTask;
use pocketmine\level\Level;
use pocketmine\network\protocol\RemoveEntityPacket;

class RemoveLightningTask extends PluginTask {

    /** @var Level */
    private $level;
    /** @var int */
    private $eid;

    public function __construct(Main $plugin, Level $level, $eid) {
        parent::__construct($plugin);
        $this->level = $level;
        $this->eid   = $eid;
    }

    public function onRun($currentTick) {
        $rpk      = new RemoveEntityPacket();
        $rpk->eid = $this->eid;
        foreach ($this->level->getPlayers() as $pl) {
            $pl->dataPacket($rpk);
        }
    }
}