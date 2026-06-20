<?php
namespace OnePiece\Fruits;

use pocketmine\Player;
use pocketmine\math\Vector3;
use pocketmine\level\particle\DustParticle;
use pocketmine\level\particle\MobSpellParticle;
use pocketmine\level\particle\CriticalParticle;
use pocketmine\level\particle\ExplodeParticle;
use pocketmine\level\particle\InstantEnchantParticle;
use pocketmine\level\sound\AnvilUseSound;
use pocketmine\level\sound\PopSound;

class MammothZoanVFX {

    private $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function spawnTransformEffect(Player $player) {
        $lv = $player->getLevel();
        $px = $player->x;
        $py = $player->y + 1;
        $pz = $player->z;

        for ($ring = 1; $ring <= 4; $ring++) {
            $rr  = $ring * 0.7;
            $pts = 10 + $ring * 4;
            for ($i = 0; $i < $pts; $i++) {
                $a = ($i / $pts) * M_PI * 2;
                $lv->addParticle(new DustParticle(
                    new Vector3($px + cos($a) * $rr, $py, $pz + sin($a) * $rr),
                    180, 120, 60
                ));
            }
        }

        for ($i = 0; $i < 16; $i++) {
            $a  = mt_rand(0, 628) / 100;
            $d  = mt_rand(5, 25) / 10;
            $lv->addParticle(new CriticalParticle(
                new Vector3($px + cos($a) * $d, $py + mt_rand(0, 15) / 10, $pz + sin($a) * $d)
            ));
        }

        $lv->addParticle(new ExplodeParticle(new Vector3($px, $py, $pz)));
        $lv->addParticle(new ExplodeParticle(new Vector3($px, $py + 1, $pz)));
        $lv->addSound(new AnvilUseSound(new Vector3($px, $py, $pz)));
        $lv->addSound(new AnvilUseSound(new Vector3($px, $py, $pz)));
    }

    public function spawnHitEffect(Player $victim) {
        $lv = $victim->getLevel();
        $vx = $victim->x;
        $vy = $victim->y + 1;
        $vz = $victim->z;

        for ($i = 0; $i < 12; $i++) {
            $a = ($i / 12) * M_PI * 2;
            $lv->addParticle(new DustParticle(
                new Vector3($vx + cos($a) * 1.5, $vy, $vz + sin($a) * 1.5),
                180, 120, 60
            ));
        }

        for ($i = 0; $i < 8; $i++) {
            $lv->addParticle(new CriticalParticle(new Vector3(
                $vx + (mt_rand(-12, 12) / 10),
                $vy + mt_rand(0, 12) / 10,
                $vz + (mt_rand(-12, 12) / 10)
            )));
        }

        $lv->addParticle(new ExplodeParticle(new Vector3($vx, $vy, $vz)));
        $lv->addSound(new AnvilUseSound(new Vector3($vx, $vy, $vz)));
    }

    public function spawnStampedeTrail(Player $player, $range) {
        $lv  = $player->getLevel();
        $px  = $player->x;
        $py  = $player->y + 0.5;
        $pz  = $player->z;
        $dir = $player->getDirectionVector();

        for ($i = 0; $i <= 10; $i++) {
            $prog = $i / 10;
            for ($side = -1; $side <= 1; $side += 2) {
                $lv->addParticle(new DustParticle(
                    new Vector3(
                        $px + $dir->x * $range * $prog - $dir->z * $side * 1.5,
                        $py + (mt_rand(0, 5) / 10),
                        $pz + $dir->z * $range * $prog + $dir->x * $side * 1.5
                    ),
                    180, 120, 60
                ));
            }
        }

        $lv->addSound(new AnvilUseSound(new Vector3($px, $py, $pz)));
    }
}
