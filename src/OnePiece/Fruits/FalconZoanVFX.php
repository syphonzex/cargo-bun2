<?php
namespace OnePiece\Fruits;

use pocketmine\Player;
use pocketmine\math\Vector3;
use pocketmine\level\particle\DustParticle;
use pocketmine\level\particle\MobSpellParticle;
use pocketmine\level\particle\CriticalParticle;
use pocketmine\level\particle\ExplodeParticle;
use pocketmine\level\particle\InstantEnchantParticle;
use pocketmine\level\sound\PopSound;
use pocketmine\level\sound\ClickSound;
use pocketmine\level\sound\AnvilUseSound;

class FalconZoanVFX {

    private $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function spawnTransformEffect(Player $player) {
        $lv = $player->getLevel();
        $px = $player->x;
        $py = $player->y + 1;
        $pz = $player->z;

        for ($i = 0; $i < 20; $i++) {
            $a = ($i / 20) * M_PI * 2;
            $lv->addParticle(new DustParticle(
                new Vector3($px + cos($a) * 1.5, $py, $pz + sin($a) * 1.5),
                255, 255, 200
            ));
        }

        for ($h = 0; $h < 5; $h++) {
            $pts = 8 + $h * 2;
            $rr  = 0.5 + $h * 0.3;
            for ($i = 0; $i < $pts; $i++) {
                $a = ($i / $pts) * M_PI * 2;
                $lv->addParticle(new InstantEnchantParticle(
                    new Vector3($px + cos($a) * $rr, $py + $h * 0.4, $pz + sin($a) * $rr)
                ));
            }
        }

        $lv->addParticle(new ExplodeParticle(new Vector3($px, $py + 2, $pz)));
        $lv->addSound(new PopSound(new Vector3($px, $py, $pz)));
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
                255, 255, 200
            ));
        }

        for ($i = 0; $i < 6; $i++) {
            $lv->addParticle(new CriticalParticle(new Vector3(
                $vx + (mt_rand(-6, 6) / 10),
                $vy + mt_rand(0, 10) / 10,
                $vz + (mt_rand(-6, 6) / 10)
            )));
        }

        $lv->addParticle(new ExplodeParticle(new Vector3($vx, $vy + 1, $vz)));
        $lv->addSound(new ClickSound(new Vector3($vx, $vy, $vz)));
    }

    public function spawnDiveEffect(Player $player, $target) {
        $lv = $player->getLevel();
        $sx = $player->x;
        $sy = $player->y + 1.2;
        $sz = $player->z;
        $tx = $target->x;
        $ty = $target->y + 1;
        $tz = $target->z;

        for ($i = 0; $i <= 10; $i++) {
            $prog = $i / 10;
            $arc  = sin($prog * M_PI) * 1.5;
            $lv->addParticle(new DustParticle(
                new Vector3(
                    $sx + ($tx - $sx) * $prog,
                    $sy + ($ty - $sy) * $prog + $arc,
                    $sz + ($tz - $sz) * $prog
                ),
                255, 255, 200
            ));
        }

        $lv->addSound(new ClickSound(new Vector3($tx, $ty, $tz)));
    }
}
