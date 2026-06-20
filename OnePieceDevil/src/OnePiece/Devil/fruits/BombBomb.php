<?php

namespace OnePiece\Devil\fruits;

use OnePiece\Devil\BaseFruit;
use pocketmine\Player;
use pocketmine\math\Vector3;
use pocketmine\level\particle\HugeExplodeParticle;
use pocketmine\level\particle\FlameParticle;
use pocketmine\level\particle\LavaParticle;
use pocketmine\level\sound\ExplodeSound;

class BombBomb extends BaseFruit {

    public function getId() { 
        return "bomb_bomb"; 
    }

    public function getDisplayName() { 
        return "Bomb-Bomb Fruit"; 
    }

    public function getDescription() { 
        return "Turn any part of your body into a bomb!"; 
    }

    public function getType() { 
        return "paramecia"; 
    }

    public function getRarity() { 
        return "rare"; 
    }

    public function getAbilityNames() {
        return ["ability1" => "Nose Fancy Cannon", "ability2" => "Full Body Detonation"];
    }

    public function getAbilityCooldowns() {
        return ["ability1" => 5.0, "ability2" => 15.0];
    }

    public function useAbility(Player $player, $ability) {
        switch ($ability) {
            case "ability1": return $this->noseFancyCannon($player);
            case "ability2": return $this->fullBodyDetonation($player);
        }
        return 0;
    }

    private function noseFancyCannon(Player $player) {
        $mult   = min(1.5, $this->getHakiMultiplier($player));
        $damage = min(5, 3.5 * $mult);
        
        $range = $this->getMasteryRange($player, 15.0);
        $target = $this->findFrontTarget($player, $range);

        if ($target !== null) {
            $pos = $target->getPosition();
            $player->getLevel()->addParticle(new HugeExplodeParticle($pos));
            $player->getLevel()->addSound(new ExplodeSound($pos));
            
            for ($i = 0; $i < 12; $i++) {
                $v = new Vector3($pos->x + (lcg_value() - 0.5) * 3, $pos->y + (lcg_value() - 0.5) * 3 + 1, $pos->z + (lcg_value() - 0.5) * 3);
                $player->getLevel()->addParticle(new FlameParticle($v));
            }

            $this->dealAbilityDamage($player, $target, $damage);

            $dx = $target->x - $player->x;
            $dz = $target->z - $player->z;
            $len = sqrt($dx * $dx + $dz * $dz);
            if ($len > 0) {
                $force = 0.8 + (lcg_value() * 0.4);
                $this->safeSetMotion($player, $target, new Vector3($dx / $len * $force, 0.35, $dz / $len * $force));
            }
            $player->sendTip("§cNOSE FANCY CANNON!");
        } else {
            $dir = $player->getDirectionVector();
            $pos = $player->add($dir->x * 4, $dir->y * 4 + 1, $dir->z * 4);
            $player->getLevel()->addParticle(new HugeExplodeParticle($pos));
            $player->getLevel()->addSound(new ExplodeSound($pos));
            $player->sendTip("§cNOSE FANCY CANNON! §7(Missed)");
        }

        return $this->getMasteryCooldown($player, $this->getAbilityCooldowns()["ability1"]);
    }

    private function fullBodyDetonation(Player $player) {
        $mult   = min(1.5, $this->getHakiMultiplier($player));
        $damage = min(6.0, 4.5 * $mult);
        
        $radius = $this->getMasteryRange($player, 6.0);
        $pos = $player->getPosition();

        $player->getLevel()->addParticle(new HugeExplodeParticle($pos));
        $player->getLevel()->addSound(new ExplodeSound($pos));
        
        $hits = 0;
        foreach ($this->getNearbyTargets($player, $radius) as $t) {
            $this->dealAbilityDamage($player, $t, $damage);
            $dx = $t->x - $pos->x; $dz = $t->z - $pos->z;
            $len = sqrt($dx * $dx + $dz * $dz);
            if ($len > 0) {
                $force = 1.2 - (($len / $radius) * 0.5);
                $this->safeSetMotion($player, $t, new Vector3($dx / $len * max(0.7, $force), 0.35, $dz / $len * max(0.7, $force)));
            }
            $hits++;
        }

        $player->sendTip("§cFULL BODY DETONATION!");
        return $this->getMasteryCooldown($player, $this->getAbilityCooldowns()["ability2"]);
    }

    public function onEquip(Player $player) {
        $player->sendMessage("§cExplosive powers! §7(Nose Fancy Cannon | Full Body Detonation)");
    }

    public function onUnequip(Player $player) {
        $player->sendMessage("§7Explosive powers deactivated...");
    }
}