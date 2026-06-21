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
use pocketmine\level\sound\FizzSound;
use pocketmine\level\sound\PopSound;

class IceFruitVFX {

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
                100, 200, 255
            ));
        }

        for ($ring = 1; $ring <= 3; $ring++) {
            $rr  = $ring * 0.5;
            $pts = 8 + $ring * 4;
            for ($i = 0; $i < $pts; $i++) {
                $a = ($i / $pts) * M_PI * 2;
                $lv->addParticle(new MobSpellParticle(
                    new Vector3($px + cos($a) * $rr, $py + $ring * 0.4, $pz + sin($a) * $rr),
                    100, 200, 255
                ));
            }
        }

        $lv->addParticle(new ExplodeParticle(new Vector3($px, $py, $pz)));
        $lv->addSound(new FizzSound(new Vector3($px, $py, $pz)));
    }

    public function spawnHitEffect(Player $victim) {
        $lv = $victim->getLevel();
        $vx = $victim->x;
        $vy = $victim->y + 1;
        $vz = $victim->z;

        for ($i = 0; $i < 12; $i++) {
            $a = ($i / 12) * M_PI * 2;
            $lv->addParticle(new DustParticle(
                new Vector3($vx + cos($a) * 1.2, $vy, $vz + sin($a) * 1.2),
                100, 200, 255
            ));
        }

        for ($i = 0; $i < 6; $i++) {
            $lv->addParticle(new MobSpellParticle(
                new Vector3(
                    $vx + (mt_rand(-8, 8) / 10),
                    $vy + mt_rand(0, 10) / 10,
                    $vz + (mt_rand(-8, 8) / 10)
                ),
                150, 230, 255
            ));
        }

        $lv->addSound(new FizzSound(new Vector3($vx, $vy, $vz)));
    }

    public function spawnIcePlatformEffect($level, $x, $y, $z) {
        for ($i = 0; $i < 8; $i++) {
            $a = ($i / 8) * M_PI * 2;
            $level->addParticle(new DustParticle(
                new Vector3($x + cos($a) * 0.6, $y + 1, $z + sin($a) * 0.6),
                150, 220, 255
            ));
        }
        $level->addParticle(new MobSpellParticle(
            new Vector3($x + 0.5, $y + 1, $z + 0.5),
            100, 200, 255
        ));
    }

    public function spawnFreezeEffect(Player $victim) {
        $lv = $victim->getLevel();
        $vx = $victim->x;
        $vy = $victim->y + 1;
        $vz = $victim->z;

        for ($h = 0; $h < 6; $h++) {
            $pts = 8 + $h * 2;
            for ($i = 0; $i < $pts; $i++) {
                $a = ($i / $pts) * M_PI * 2;
                $lv->addParticle(new DustParticle(
                    new Vector3($vx + cos($a) * 0.8, $vy + $h * 0.3, $vz + sin($a) * 0.8),
                    100, 200, 255
                ));
            }
        }

        $lv->addSound(new FizzSound(new Vector3($vx, $vy, $vz)));
        $lv->addSound(new AnvilUseSound(new Vector3($vx, $vy, $vz)));
    }
}
