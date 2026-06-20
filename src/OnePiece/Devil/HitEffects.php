<?php
namespace OnePiece\Devil;

use pocketmine\entity\Effect;
use pocketmine\level\particle\DustParticle;
use pocketmine\level\particle\FlameParticle;
use pocketmine\level\particle\InstantEnchantParticle;
use pocketmine\level\particle\PortalParticle;
use pocketmine\level\particle\SmokeParticle;
use pocketmine\level\particle\SpellParticle;
use pocketmine\level\particle\CriticalParticle;
use pocketmine\level\particle\EnchantParticle;
use pocketmine\level\particle\HappyVillagerParticle;
use pocketmine\level\sound\ExplodeSound;
use pocketmine\level\sound\FizzSound;
use pocketmine\math\Vector3;
use pocketmine\Player;

class HitEffects {

    // Called when an ability hits a valid player target
    public static function onHit(Player $attacker, Player $target, $fruitId, $rarity) {
        $level = $target->getLevel();
        $pos   = new Vector3($target->x, $target->y + 1, $target->z);

        switch ($fruitId) {
            case "hie_hie":
              //  self::iceHit($level, $pos, $rarity);
                break;
            case "pika_pika":
               // self::lightHit($level, $pos, $rarity);
                break;
            case "iro_iro":
               // self::stringHit($level, $pos, $rarity);
                break;
            case "ope_ope":
               // self::opeHit($level, $pos, $rarity);
                break;
            case "mera_mera":
               // self::flameHit($level, $pos, $rarity);
                break;
            // Zoans
            case "inu_inu":
               // self::zoanHit($level, $pos, "common", 0, 180, 50);   // grey-blue
                break;
            case "neko_neko":
               // self::zoanHit($level, $pos, "rare", 255, 165, 0);    // orange
                break;
            case "tori_tori_falcon":
               // self::zoanHit($level, $pos, "common", 150, 200, 255);// sky blue
                break;
            case "tori_tori":
                //self::zoanHit($level, $pos, "legendary", 0, 200, 150);// phoenix teal
                break;
            case "uo_uo":
               // self::zoanHit($level, $pos, "mythical", 200, 0, 255); // dragon purple
                break;
            case "zou_zou":
                //self::zoanHit($level, $pos, "mythical", 180, 180, 180);// mammoth grey
                break;
        }
    }

    private static function iceHit($level, $pos, $rarity) {
        $count = $rarity === "mythical" ? 12 : ($rarity === "legendary" ? 8 : 5);
        for ($i = 0; $i < $count; $i++) {
            $a   = ($i / $count) * M_PI * 2;
            $r   = 0.5;
            $px  = $pos->x + cos($a) * $r;
            $pz  = $pos->z + sin($a) * $r;
            $py  = $pos->y + ($i % 3) * 0.3;
            $level->addParticle(new DustParticle(new Vector3($px, $py, $pz), 150, 220, 255));
        }
        $level->addSound(new FizzSound($pos));
    }

    private static function lightHit($level, $pos, $rarity) {
        $count = $rarity === "legendary" ? 10 : 6;
        for ($i = 0; $i < $count; $i++) {
            $a  = ($i / $count) * M_PI * 2;
            $px = $pos->x + cos($a) * 0.4;
            $pz = $pos->z + sin($a) * 0.4;
            $level->addParticle(new InstantEnchantParticle(new Vector3($px, $pos->y, $pz)));
        }
        $level->addParticle(new EnchantParticle($pos));
    }

    private static function stringHit($level, $pos, $rarity) {
        $count = $rarity === "legendary" ? 8 : 5;
        for ($i = 0; $i < $count; $i++) {
            $py = $pos->y + ($i * 0.25);
            $level->addParticle(new DustParticle(new Vector3($pos->x, $py, $pos->z), 200, 200, 200));
        }
        $level->addParticle(new CriticalParticle($pos));
    }

    private static function opeHit($level, $pos, $rarity) {
        $count = $rarity === "legendary" ? 8 : 5;
        for ($i = 0; $i < $count; $i++) {
            $a  = ($i / $count) * M_PI * 2;
            $px = $pos->x + cos($a) * 0.6;
            $pz = $pos->z + sin($a) * 0.6;
            $level->addParticle(new SpellParticle(new Vector3($px, $pos->y + 0.5, $pz)));
        }
    }

    private static function flameHit($level, $pos, $rarity) {
        $count = $rarity === "legendary" ? 8 : 5;
        for ($i = 0; $i < $count; $i++) {
            $a  = ($i / $count) * M_PI * 2;
            $px = $pos->x + cos($a) * 0.4;
            $pz = $pos->z + sin($a) * 0.4;
            $py = $pos->y + ($i % 3) * 0.3;
            $level->addParticle(new FlameParticle(new Vector3($px, $py, $pz)));
        }
        $level->addParticle(new DustParticle($pos, 255, 80, 0));
    }

    private static function zoanHit($level, $pos, $rarity, $r, $g, $b) {
        switch ($rarity) {
            case "mythical":  $count = 12; break;
            case "legendary": $count = 8;  break;
            case "rare":      $count = 6;  break;
            default:          $count = 4;  break;
        }
        for ($i = 0; $i < $count; $i++) {
            $a  = ($i / $count) * M_PI * 2;
            $px = $pos->x + cos($a) * 0.5;
            $pz = $pos->z + sin($a) * 0.5;
            $py = $pos->y + ($i % 2) * 0.4;
            $level->addParticle(new DustParticle(new Vector3($px, $py, $pz), $r, $g, $b));
        }
        if ($rarity === "mythical") {
            $level->addParticle(new PortalParticle($pos));
        }
    }
}