<?php
namespace OnePiece\Fruits;

use pocketmine\Player;
use pocketmine\math\Vector3;
use pocketmine\level\particle\DustParticle;
use pocketmine\level\particle\CriticalParticle;
use pocketmine\level\particle\ExplodeParticle;
use pocketmine\level\particle\InstantEnchantParticle;
use pocketmine\level\sound\AnvilUseSound;
use pocketmine\level\sound\ClickSound;
use pocketmine\level\sound\PopSound;

class ChopFruitVFX {

    private $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function spawnActivateEffect(Player $player) {
        $lv = $player->getLevel();
        $px = $player->x;
        $py = $player->y + 1;
        $pz = $player->z;

        for ($i = 0; $i < 16; $i++) {
            $a = ($i / 16) * M_PI * 2;
            $lv->addParticle(new DustParticle(
                new Vector3($px + cos($a) * 1.5, $py, $pz + sin($a) * 1.5),
                255, 80, 0
            ));
        }

        for ($i = 0; $i < 10; $i++) {
            $a  = mt_rand(0, 628) / 100;
            $d  = mt_rand(3, 18) / 10;
            $lv->addParticle(new CriticalParticle(
                new Vector3($px + cos($a) * $d, $py + mt_rand(0, 15) / 10, $pz + sin($a) * $d)
            ));
        }

        $lv->addParticle(new ExplodeParticle(new Vector3($px, $py, $pz)));
        $lv->addSound(new ClickSound(new Vector3($px, $py, $pz)));
    }

    public function spawnHitEffect(Player $victim) {
        $lv = $victim->getLevel();
        $vx = $victim->x;
        $vy = $victim->y + 1;
        $vz = $victim->z;

        for ($i = 0; $i < 10; $i++) {
            $a = ($i / 10) * M_PI * 2;
            $lv->addParticle(new DustParticle(
                new Vector3($vx + cos($a) * 1.0, $vy, $vz + sin($a) * 1.0),
                255, 80, 0
            ));
        }

        for ($i = 0; $i < 8; $i++) {
            $lv->addParticle(new CriticalParticle(new Vector3(
                $vx + (mt_rand(-8, 8) / 10),
                $vy + mt_rand(0, 10) / 10,
                $vz + (mt_rand(-8, 8) / 10)
            )));
        }

        $lv->addParticle(new ExplodeParticle(new Vector3($vx, $vy, $vz)));
        $lv->addSound(new PopSound(new Vector3($vx, $vy, $vz)));
    }

    public function spawnCannonTrail(Player $player, $range) {
        $lv  = $player->getLevel();
        $px  = $player->x;
        $py  = $player->y + 1.2;
        $pz  = $player->z;
        $dir = $player->getDirectionVector();

        for ($i = 0; $i <= 12; $i++) {
            $prog = $i / 12;
            $lv->addParticle(new DustParticle(
                new Vector3(
                    $px + $dir->x * $range * $prog + (mt_rand(-3, 3) / 10),
                    $py,
                    $pz + $dir->z * $range * $prog + (mt_rand(-3, 3) / 10)
                ),
                255, 80, 0
            ));
        }

        $lv->addSound(new ClickSound(new Vector3($px, $py, $pz)));
    }
}
