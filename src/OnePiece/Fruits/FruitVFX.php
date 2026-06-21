<?php

namespace OnePiece\Fruits;

use pocketmine\Player;
use pocketmine\math\Vector3;
use pocketmine\network\protocol\ExplodePacket;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\network\protocol\RemoveEntityPacket;
use pocketmine\level\particle\SmokeParticle;
use pocketmine\level\particle\FlameParticle;
use pocketmine\level\particle\LavaParticle;
use pocketmine\level\particle\LavaDripParticle;
use pocketmine\level\particle\CriticalParticle;
use pocketmine\level\particle\EnchantParticle;
use pocketmine\level\particle\EnchantmentTableParticle;
use pocketmine\level\particle\ExplodeParticle;
use pocketmine\level\particle\HugeExplodeParticle;
use pocketmine\level\particle\RedstoneParticle;
use pocketmine\level\particle\DustParticle;
use pocketmine\level\particle\SpellParticle;
use pocketmine\level\particle\MobSpellParticle;
use pocketmine\level\particle\InstantEnchantParticle;
use pocketmine\level\particle\PortalParticle;
use pocketmine\level\particle\AngryVillagerParticle;
use pocketmine\level\particle\HappyVillagerParticle;
use pocketmine\level\particle\HeartParticle;
use pocketmine\level\particle\LargeExplodeParticle;
use pocketmine\level\particle\SplashParticle;
use pocketmine\level\particle\SporeParticle;
use pocketmine\level\particle\InkParticle;
use pocketmine\level\particle\WhiteSmokeParticle;
use pocketmine\level\particle\WaterParticle;
use pocketmine\level\particle\WaterDripParticle;
use pocketmine\level\particle\BubbleParticle;
use pocketmine\level\particle\EntityFlameParticle;
use pocketmine\level\particle\GenericParticle;
use pocketmine\level\particle\Particle;
use pocketmine\level\sound\ExplodeSound;
use pocketmine\level\sound\FizzSound;
use pocketmine\level\sound\BlazeShootSound;
use pocketmine\level\sound\GhastSound;
use pocketmine\level\sound\GhastShootSound;
use pocketmine\level\sound\AnvilFallSound;
use pocketmine\level\sound\AnvilUseSound;
use pocketmine\level\sound\ClickSound;
use pocketmine\level\sound\PopSound;
use pocketmine\level\sound\BatSound;
use pocketmine\level\sound\LaunchSound;
use pocketmine\level\sound\DoorCrashSound;
use pocketmine\level\sound\EndermanTeleportSound;
use pocketmine\level\sound\GenericSound;
use pocketmine\network\protocol\LevelEventPacket;

class FruitVFX {

    private $plugin;
    private $eid = 900000;

    const ENTITY_LIGHTNING = 93;

    const COL_FIRE_R = 255; const COL_FIRE_G = 100; const COL_FIRE_B = 0;
    const COL_FIRE_CORE_R = 255; const COL_FIRE_CORE_G = 220; const COL_FIRE_CORE_B = 100;
    const COL_LAVA_R = 200; const COL_LAVA_G = 40; const COL_LAVA_B = 0;
    const COL_ELEC_R = 180; const COL_ELEC_G = 220; const COL_ELEC_B = 255;
    const COL_ELEC2_R = 255; const COL_ELEC2_G = 255; const COL_ELEC2_B = 100;
    const COL_ELEC_CORE_R = 255; const COL_ELEC_CORE_G = 255; const COL_ELEC_CORE_B = 255;
    const COL_SAND_R = 220; const COL_SAND_G = 190; const COL_SAND_B = 120;
    const COL_SMOKE_R = 80; const COL_SMOKE_G = 80; const COL_SMOKE_B = 80;
    const COL_CYAN_R = 0; const COL_CYAN_G = 200; const COL_CYAN_B = 255;
    const COL_BLUE_R = 0; const COL_BLUE_G = 100; const COL_BLUE_B = 255;
    const COL_GOLD_R = 255; const COL_GOLD_G = 200; const COL_GOLD_B = 0;
    const COL_GREEN_R = 0; const COL_GREEN_G = 200; const COL_GREEN_B = 50;
    const COL_RED_R = 200; const COL_RED_G = 0; const COL_RED_B = 0;
    const COL_PURPLE_R = 150; const COL_PURPLE_G = 0; const COL_PURPLE_B = 200;
    const COL_WHITE_R = 255; const COL_WHITE_G = 255; const COL_WHITE_B = 255;
    const COL_BLACK_R = 20; const COL_BLACK_G = 20; const COL_BLACK_B = 20;
    const COL_DARK_R = 100; const COL_DARK_G = 0; const COL_DARK_B = 0;
    const COL_PHOENIX_R = 0; const COL_PHOENIX_G = 180; const COL_PHOENIX_B = 255;
    const COL_HAKI_R = 30; const COL_HAKI_G = 0; const COL_HAKI_B = 50;
    const COL_CONQUEROR_R = 180; const COL_CONQUEROR_G = 0; const COL_CONQUEROR_B = 220;

    const EV_SPLASH = 2002;
    const EV_CRIT = 2006;
    const COL_SPLASH_RED = 16733525;
    const COL_SPLASH_ORANGE = 16753920;
    const COL_SPLASH_YELLOW = 16766720;
    const COL_SPLASH_CYAN = 65535;
    const COL_SPLASH_BLUE = 3694022;
    const COL_SPLASH_PURPLE = 8339378;
    const COL_SPLASH_WHITE = 16777215;
    const COL_SPLASH_BLACK = 1118481;
    const COL_SPLASH_GOLD = 16766464;
    const COL_SPLASH_DARK_RED = 11141120;
    const COL_SPLASH_ELECTRIC = 8388863;
    const COL_SPLASH_SAND = 14787072;
    const COL_SPLASH_GREEN = 5635925;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    private function v($x, $y, $z) {
        return new Vector3($x, $y, $z);
    }

    public function boom($lv, $x, $y, $z, $r) {
        $pk = new ExplodePacket();
        $pk->x = (float)$x;
        $pk->y = (float)$y;
        $pk->z = (float)$z;
        $pk->radius = (float)$r;
        $pk->records = [];
        foreach ($lv->getPlayers() as $pl) {
            $pl->dataPacket($pk);
        }
    }

    public function lightning($lv, $x, $y, $z) {
        $eid = $this->eid++;
        if ($this->eid > 999999) $this->eid = 900000;

        $pk = new AddEntityPacket();
        $pk->eid = $eid;
        $pk->type = self::ENTITY_LIGHTNING;
        $pk->x = (float)$x;
        $pk->y = (float)$y;
        $pk->z = (float)$z;
        $pk->speedX = 0;
        $pk->speedY = 0;
        $pk->speedZ = 0;
        $pk->yaw = 0;
        $pk->pitch = 0;
        $pk->metadata = [];
        foreach ($lv->getPlayers() as $pl) {
            $pl->dataPacket($pk);
        }

        $this->plugin->getServer()->getScheduler()->scheduleDelayedTask(
            new RemoveLightningTask($this->plugin, $lv, $eid), 15
        );
    }

    private function splash($lv, $x, $y, $z, $col) {
        $pk = new LevelEventPacket();
        $pk->evid = self::EV_SPLASH;
        $pk->data = $col;
        $pk->x = (float)$x;
        $pk->y = (float)$y;
        $pk->z = (float)$z;
        foreach ($lv->getPlayers() as $pl) {
            $pl->dataPacket($pk);
        }
    }

    public function ring($lv, $cx, $cy, $cz, $r, $pts, $particleFunc) {
        $step = M_PI * 2 / max(1, $pts);
        for ($i = 0; $i < $pts; $i++) {
            $a = $i * $step;
            $lv->addParticle($particleFunc($this->v($cx + cos($a) * $r, $cy, $cz + sin($a) * $r)));
        }
    }

    public function spiral($lv, $cx, $cy, $cz, $h, $r, $turns, $pts, $particleFunc) {
        for ($i = 0; $i < $pts; $i++) {
            $t = $i / $pts;
            $a = $t * M_PI * 2 * $turns;
            $lv->addParticle($particleFunc($this->v($cx + cos($a) * $r, $cy + $t * $h, $cz + sin($a) * $r)));
        }
    }

    public function spiralExpanding($lv, $cx, $cy, $cz, $h, $startR, $endR, $turns, $pts, $particleFunc) {
        for ($i = 0; $i < $pts; $i++) {
            $t = $i / $pts;
            $a = $t * M_PI * 2 * $turns;
            $r = $startR + ($endR - $startR) * $t;
            $lv->addParticle($particleFunc($this->v($cx + cos($a) * $r, $cy + $t * $h, $cz + sin($a) * $r)));
        }
    }

    public function beam($lv, $x, $baseY, $z, $h, $particleFunc) {
        for ($i = 0; $i < $h; $i++) {
            $lv->addParticle($particleFunc($this->v($x, $baseY + $i, $z)));
        }
    }

    public function beamDense($lv, $x, $baseY, $z, $h, $density, $particleFunc) {
        $step = 1.0 / $density;
        for ($i = 0; $i < $h * $density; $i++) {
            $lv->addParticle($particleFunc($this->v($x, $baseY + $i * $step, $z)));
        }
    }

    public function line($lv, $fx, $fy, $fz, $tx, $ty, $tz, $steps, $particleFunc) {
        $dx = ($tx - $fx) / $steps;
        $dy = ($ty - $fy) / $steps;
        $dz = ($tz - $fz) / $steps;
        for ($i = 0; $i <= $steps; $i++) {
            $lv->addParticle($particleFunc($this->v($fx + $dx * $i, $fy + $dy * $i, $fz + $dz * $i)));
        }
    }

    public function scatter($lv, $x, $y, $z, $r, $n, $particleFunc) {
        for ($i = 0; $i < $n; $i++) {
            $lv->addParticle($particleFunc($this->v(
                $x + mt_rand((int)(-$r * 10), (int)($r * 10)) / 10,
                $y + mt_rand(0, (int)($r * 8)) / 10,
                $z + mt_rand((int)(-$r * 10), (int)($r * 10)) / 10
            )));
        }
    }

    public function scatterSphere($lv, $x, $y, $z, $r, $n, $particleFunc) {
        for ($i = 0; $i < $n; $i++) {
            $theta = mt_rand(0, 314) / 50;
            $phi = mt_rand(0, 628) / 100;
            $rr = $r * mt_rand(5, 10) / 10;
            $lv->addParticle($particleFunc($this->v(
                $x + sin($theta) * cos($phi) * $rr,
                $y + cos($theta) * $rr,
                $z + sin($theta) * sin($phi) * $rr
            )));
        }
    }

    public function shockwave($lv, $cx, $cy, $cz, $maxR, $rings, $pts, $particleFunc) {
        for ($r = 1; $r <= $rings; $r++) {
            $this->ring($lv, $cx, $cy, $cz, ($r / $rings) * $maxR, $pts, $particleFunc);
        }
    }

    public function shockwaveRising($lv, $cx, $cy, $cz, $maxR, $maxH, $rings, $pts, $particleFunc) {
        for ($r = 1; $r <= $rings; $r++) {
            $frac = $r / $rings;
            $this->ring($lv, $cx, $cy + $frac * $maxH, $cz, $frac * $maxR, $pts, $particleFunc);
        }
    }

    public function sphere($lv, $cx, $cy, $cz, $r, $rings, $ppr, $particleFunc) {
        for ($i = 0; $i <= $rings; $i++) {
            $phi = ($i / $rings) * M_PI;
            $rr = sin($phi) * $r;
            if ($rr > 0.15) $this->ring($lv, $cx, $cy + cos($phi) * $r, $cz, $rr, $ppr, $particleFunc);
        }
    }

    public function dome($lv, $cx, $cy, $cz, $r, $rings, $ppr, $particleFunc) {
        for ($i = 0; $i <= $rings; $i++) {
            $phi = ($i / $rings) * M_PI * 0.5;
            $rr = sin($phi) * $r;
            $this->ring($lv, $cx, $cy + cos($phi) * $r, $cz, $rr, $ppr, $particleFunc);
        }
    }

    public function cone($lv, $ox, $oy, $oz, $dx, $dy, $dz, $len, $spread, $n, $particleFunc) {
        for ($i = 0; $i < $n; $i++) {
            $d = mt_rand((int)($len * 3), (int)($len * 10)) / 10;
            $s = ($d / $len) * $spread;
            $rx = mt_rand((int)(-$s * 10), (int)($s * 10)) / 10;
            $ry = mt_rand((int)(-$s * 4), (int)($s * 4)) / 10;
            $rz = mt_rand((int)(-$s * 10), (int)($s * 10)) / 10;
            $lv->addParticle($particleFunc($this->v($ox + $dx * $d + $rx, $oy + $dy * $d + $ry, $oz + $dz * $d + $rz)));
        }
    }

    public function coneLayered($lv, $ox, $oy, $oz, $dx, $dy, $dz, $len, $spread, $layers, $ppl, $particleFunc) {
        for ($l = 1; $l <= $layers; $l++) {
            $d = ($l / $layers) * $len;
            $s = ($d / $len) * $spread;
            for ($i = 0; $i < $ppl; $i++) {
                $a = ($i / $ppl) * M_PI * 2;
                $perpX = -$dz;
                $perpZ = $dx;
                $len2 = sqrt($perpX * $perpX + $perpZ * $perpZ);
                if ($len2 > 0) {
                    $perpX /= $len2;
                    $perpZ /= $len2;
                }
                $offX = cos($a) * $s * $perpX + sin($a) * $s * 0.5;
                $offZ = cos($a) * $s * $perpZ;
                $offY = sin($a) * $s * 0.5;
                $lv->addParticle($particleFunc($this->v($ox + $dx * $d + $offX, $oy + $dy * $d + $offY, $oz + $dz * $d + $offZ)));
            }
        }
    }

    public function boltDown($lv, $x, $z, $topY, $botY, $particleFunc) {
        $ox = 0.0;
        $oz = 0.0;
        $steps = (int)(($topY - $botY) / 0.4);
        for ($i = 0; $i < $steps; $i++) {
            $cy = $topY - $i * 0.4;
            $ox += sin($i * 1.4) * 0.22;
            $oz += cos($i * 1.1) * 0.22;
            $lv->addParticle($particleFunc($this->v($x + $ox, $cy, $z + $oz)));
        }
    }

    public function boltDownBranching($lv, $x, $z, $topY, $botY, $particleFunc, $branches = 2) {
        $this->boltDown($lv, $x, $z, $topY, $botY, $particleFunc);
        for ($b = 0; $b < $branches; $b++) {
            $branchY = $topY - mt_rand(3, 8);
            $bx = $x + mt_rand(-15, 15) / 10;
            $bz = $z + mt_rand(-15, 15) / 10;
            $this->boltDown($lv, $bx, $bz, $branchY, $botY, $particleFunc);
        }
    }

    public function cross($lv, $cx, $cy, $cz, $size, $particleFunc) {
        for ($i = -$size; $i <= $size; $i++) {
            $lv->addParticle($particleFunc($this->v($cx + $i * 0.5, $cy, $cz)));
            $lv->addParticle($particleFunc($this->v($cx, $cy + $i * 0.5, $cz)));
            $lv->addParticle($particleFunc($this->v($cx, $cy, $cz + $i * 0.5)));
        }
    }

    public function star($lv, $cx, $cy, $cz, $r, $points, $particleFunc) {
        for ($i = 0; $i < $points; $i++) {
            $a = ($i / $points) * M_PI * 2;
            $this->line($lv, $cx, $cy, $cz, $cx + cos($a) * $r, $cy, $cz + sin($a) * $r, 5, $particleFunc);
        }
    }

    public function startPersistent($lv, $cx, $cy, $cz, $radius, $type, $durationTicks, $intervalTicks = 2) {
        $task = new PersistentVFXTask($this->plugin, $lv, $cx, $cy, $cz, $radius, $type, (int)($durationTicks / $intervalTicks));
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($task, $intervalTicks);
    }

    private function pFlame() { return function($v) { return new FlameParticle($v); }; }
    private function pLava() { return function($v) { return new LavaParticle($v); }; }
    private function pSmoke($s = 0) { return function($v) use ($s) { return new SmokeParticle($v, $s); }; }
    private function pBigSmoke() { return function($v) { return new SmokeParticle($v, 0); }; }
    private function pCrit() { return function($v) { return new CriticalParticle($v); }; }
    private function pEnchant() { return function($v) { return new EnchantParticle($v); }; }
    private function pDust($r, $g, $b) { return function($v) use ($r, $g, $b) { return new DustParticle($v, $r, $g, $b); }; }
    private function pSpell($r, $g, $b) { return function($v) use ($r, $g, $b) { return new SpellParticle($v, $r, $g, $b); }; }
    private function pMobSpell($r, $g, $b) { return function($v) use ($r, $g, $b) { return new MobSpellParticle($v, $r, $g, $b); }; }
    private function pInstant() { return function($v) { return new InstantEnchantParticle($v); }; }
    private function pRedstone() { return function($v) { return new RedstoneParticle($v, 1); }; }
    private function pPortal() { return function($v) { return new PortalParticle($v); }; }
    private function pExplode() { return function($v) { return new ExplodeParticle($v); }; }
    private function pLargeExplode() { return function($v) { return new LargeExplodeParticle($v); }; }
    private function pHeart() { return function($v) { return new HeartParticle($v); }; }
    private function pAngry() { return function($v) { return new AngryVillagerParticle($v); }; }
    private function pHappy() { return function($v) { return new HappyVillagerParticle($v); }; }
    private function pInk() { return function($v) { return new InkParticle($v); }; }
    private function pWater() { return function($v) { return new WaterParticle($v); }; }
    private function pBubble() { return function($v) { return new BubbleParticle($v); }; }
    private function pLavaDrip() { return function($v) { return new LavaDripParticle($v); }; }
    private function pEntityFlame() { return function($v) { return new EntityFlameParticle($v); }; }
    private function pSpore() { return function($v) { return new SporeParticle($v); }; }
    private function pWhiteSmoke() { return function($v) { return new WhiteSmokeParticle($v); }; }

    // ==================== ENHANCED MERA MERA (FIRE) VFX ====================

    public function spawnHiken($player, $range) {
        $dir = $player->getDirectionVector();
        $pos = $player->getPosition();
        $lv = $player->getLevel();
        $x = $pos->x;
        $y = $pos->y + 1.2;
        $z = $pos->z;

        $this->scatter($lv, $x, $y, $z, 1.0, 8, $this->pFlame());
        $this->ring($lv, $x, $y, $z, 1.5, 10, $this->pLava());

        for ($wave = 0; $wave < 4; $wave++) {
            $waveRange = $range - $wave * 1.5;
            $waveSpread = 2.0 + $wave * 0.4;
            $this->cone($lv, $x, $y, $z, $dir->x, 0.0, $dir->z, $waveRange, $waveSpread, 25, $this->pFlame());
        }

        $this->coneLayered($lv, $x, $y, $z, $dir->x, 0.0, $dir->z, $range * 0.8, 1.5, 6, 8, $this->pLava());

        for ($i = 1; $i <= 5; $i++) {
            $d = $i * ($range / 5);
            $ex = $x + $dir->x * $d;
            $ez = $z + $dir->z * $d;
            $this->ring($lv, $ex, $y, $ez, 1.2 + $i * 0.25, 8, $this->pFlame());
            if ($i % 2 == 0) {
                $this->scatter($lv, $ex, $y, $ez, 0.8, 4, $this->pLava());
            }
        }

        $ex = $x + $dir->x * $range;
        $ez = $z + $dir->z * $range;

        $this->sphere($lv, $ex, $y, $ez, 2.5, 4, 10, $this->pFlame());
        $this->sphere($lv, $ex, $y, $ez, 1.5, 3, 6, $this->pDust(self::COL_FIRE_CORE_R, self::COL_FIRE_CORE_G, self::COL_FIRE_CORE_B));
        $this->shockwave($lv, $ex, $y, $ez, 4.5, 3, 14, $this->pFlame());
        $this->shockwaveRising($lv, $ex, $y, $ez, 3.0, 2.5, 3, 10, $this->pLava());

        $this->boom($lv, $ex, $y, $ez, 4.5);
        $this->boom($lv, $x + $dir->x * ($range * 0.5), $y, $z + $dir->z * ($range * 0.5), 2.5);

        $this->splash($lv, $x, $y, $z, self::COL_SPLASH_ORANGE);
        $this->splash($lv, $ex, $y, $ez, self::COL_SPLASH_RED);
        $this->splash($lv, $ex, $y + 1.5, $ez, self::COL_SPLASH_ORANGE);

        $lv->addParticle(new HugeExplodeParticle($this->v($ex, $y, $ez)));
        $lv->addSound(new BlazeShootSound($this->v($x, $y, $z)));
        $lv->addSound(new ExplodeSound($this->v($ex, $y, $ez)));
    }

    public function spawnEntei($player, $radius) {
        $pos = $player->getPosition();
        $lv = $player->getLevel();
        $x = $pos->x;
        $y = $pos->y;
        $z = $pos->z;

        $this->beamDense($lv, $x, $y, $z, 15, 2, $this->pFlame());
        $this->beamDense($lv, $x + 0.3, $y, $z + 0.3, 12, 2, $this->pLava());
        $this->beamDense($lv, $x - 0.3, $y, $z - 0.3, 12, 2, $this->pLava());

        for ($r = 1; $r <= (int)$radius; $r++) {
            $this->ring($lv, $x, $y + 0.2, $z, $r, 14 + $r, $this->pFlame());
            if ($r % 2 == 0) {
                $this->ring($lv, $x, $y + 0.5, $z, $r * 0.8, 10, $this->pLava());
            }
        }

        $this->spiralExpanding($lv, $x, $y, $z, 8, 1.0, $radius, 5, 30, $this->pFlame());
        $this->spiralExpanding($lv, $x, $y, $z, 6, 0.5, $radius * 0.7, 4, 20, $this->pLava());

        for ($i = 0; $i < 8; $i++) {
            $a = ($i / 8) * M_PI * 2;
            $bx = $x + cos($a) * ($radius * 0.7);
            $bz = $z + sin($a) * ($radius * 0.7);
            $this->beamDense($lv, $bx, $y, $bz, 5 + mt_rand(0, 3), 1.5, $this->pFlame());
        }

        $this->dome($lv, $x, $y + 2, $z, $radius * 0.6, 5, 12, $this->pFlame());
        $this->sphere($lv, $x, $y + 4, $z, 3, 5, 10, $this->pDust(self::COL_FIRE_CORE_R, self::COL_FIRE_CORE_G, self::COL_FIRE_CORE_B));

        $this->shockwave($lv, $x, $y, $z, $radius + 2, 5, 20, $this->pFlame());
        $this->scatter($lv, $x, $y + 3, $z, $radius * 0.8, 20, $this->pLava());

        $this->boom($lv, $x, $y + 3, $z, 6.0);
        for ($i = 0; $i < 6; $i++) {
            $a = ($i / 6) * M_PI * 2;
            $this->boom($lv, $x + cos($a) * ($radius * 0.6), $y + 1, $z + sin($a) * ($radius * 0.6), 3.0);
        }

        $this->splash($lv, $x, $y + 2, $z, self::COL_SPLASH_RED);
        $this->splash($lv, $x, $y + 5, $z, self::COL_SPLASH_ORANGE);
        $this->splash($lv, $x, $y + 8, $z, self::COL_SPLASH_YELLOW);

        $lv->addParticle(new HugeExplodeParticle($this->v($x, $y + 2, $z)));
        $lv->addParticle(new HugeExplodeParticle($this->v($x, $y + 5, $z)));
        $lv->addSound(new ExplodeSound($this->v($x, $y, $z)));
        $lv->addSound(new BlazeShootSound($this->v($x, $y + 3, $z)));
        $lv->addSound(new AnvilFallSound($this->v($x, $y, $z)));

        $this->spawnFireDomain($player, $radius * 0.7, 200);
    }

    public function spawnFireLine($player, $range) {
        $this->spawnHiken($player, $range);
    }

    public function spawnFireDome($player, $radius) {
        $this->spawnEntei($player, $radius);
    }

    // ==================== ENHANCED GORO GORO (LIGHTNING) VFX ====================

    public function spawnElThor($lv, $x, $y, $z) {
        $this->lightning($lv, $x, $y, $z);

        for ($i = 0; $i < 4; $i++) {
            $ox = mt_rand(-8, 8) / 10;
            $oz = mt_rand(-8, 8) / 10;
            $this->boltDownBranching($lv, $x + $ox, $z + $oz, $y + 30, $y, $this->pDust(self::COL_ELEC_R, self::COL_ELEC_G, self::COL_ELEC_B), 1);
        }

        $this->beamDense($lv, $x, $y, $z, 25, 3, $this->pDust(self::COL_ELEC_CORE_R, self::COL_ELEC_CORE_G, self::COL_ELEC_CORE_B));

        for ($r = 1; $r <= 5; $r++) {
            $this->ring($lv, $x, $y + 0.1 * $r, $z, $r * 1.2, 14, $this->pInstant());
            $this->ring($lv, $x, $y + 0.2 * $r, $z, $r * 0.8, 10, $this->pDust(self::COL_ELEC2_R, self::COL_ELEC2_G, self::COL_ELEC2_B));
        }

        $this->sphere($lv, $x, $y + 1, $z, 2.5, 5, 12, $this->pDust(self::COL_ELEC_R, self::COL_ELEC_G, self::COL_ELEC_B));
        $this->sphere($lv, $x, $y + 1, $z, 1.5, 3, 8, $this->pDust(self::COL_ELEC_CORE_R, self::COL_ELEC_CORE_G, self::COL_ELEC_CORE_B));

        $this->star($lv, $x, $y + 0.5, $z, 4, 8, $this->pInstant());

        $this->splash($lv, $x, $y, $z, self::COL_SPLASH_YELLOW);
        $this->splash($lv, $x, $y + 2, $z, self::COL_SPLASH_ELECTRIC);
        $this->splash($lv, $x, $y + 4, $z, self::COL_SPLASH_WHITE);

        $this->boom($lv, $x, $y, $z, 5.0);
        $lv->addParticle(new HugeExplodeParticle($this->v($x, $y + 1, $z)));
        $lv->addSound(new ExplodeSound($this->v($x, $y, $z)));
        $lv->addSound(new AnvilFallSound($this->v($x, $y, $z)));
    }

    public function spawnRaigo($center, $radius) {
        $pos = $center->getPosition();
        $lv = $center->getLevel();
        $x = $pos->x;
        $y = $pos->y;
        $z = $pos->z;
        $cloudY = $y + $radius * 1.2;

        $this->sphere($lv, $x, $cloudY, $z, $radius * 0.7, 6, 14, $this->pBigSmoke());
        $this->sphere($lv, $x, $cloudY, $z, $radius * 0.5, 4, 10, $this->pDust(self::COL_ELEC_R, self::COL_ELEC_G, self::COL_ELEC_B));

        for ($i = 0; $i < 12; $i++) {
            $a = ($i / 12) * M_PI * 2;
            $d = $radius * (0.3 + ($i % 3) * 0.25);
            $bx = $x + cos($a) * $d;
            $bz = $z + sin($a) * $d;
            $this->lightning($lv, $bx, $y, $bz);
            $this->boltDownBranching($lv, $bx, $bz, $cloudY, $y, $this->pDust(self::COL_ELEC2_R, self::COL_ELEC2_G, self::COL_ELEC2_B), 1);
        }

        $this->lightning($lv, $x, $y, $z);
        $this->beamDense($lv, $x, $y, $z, (int)($cloudY - $y), 2, $this->pDust(self::COL_ELEC_CORE_R, self::COL_ELEC_CORE_G, self::COL_ELEC_CORE_B));

        $this->shockwave($lv, $x, $y, $z, $radius + 3, 6, 24, $this->pInstant());
        $this->shockwave($lv, $x, $y + 1, $z, $radius, 4, 18, $this->pDust(self::COL_ELEC_R, self::COL_ELEC_G, self::COL_ELEC_B));

        for ($i = 0; $i < 8; $i++) {
            $a = ($i / 8) * M_PI * 2;
            $bx = $x + cos($a) * $radius;
            $bz = $z + sin($a) * $radius;
            $this->boom($lv, $bx, $y, $bz, 2.5);
        }
        $this->boom($lv, $x, $y + 2, $z, 6.0);
        $this->boom($lv, $x, $cloudY, $z, 4.0);

        $this->splash($lv, $x, $y + 2, $z, self::COL_SPLASH_YELLOW);
        $this->splash($lv, $x, $cloudY, $z, self::COL_SPLASH_ELECTRIC);

        $lv->addParticle(new HugeExplodeParticle($this->v($x, $y + 2, $z)));
        $lv->addParticle(new HugeExplodeParticle($this->v($x, $cloudY, $z)));
        $lv->addSound(new ExplodeSound($this->v($x, $y, $z)));
        $lv->addSound(new ExplodeSound($this->v($x, $cloudY, $z)));
        $lv->addSound(new AnvilFallSound($this->v($x, $y, $z)));

        $this->startPersistent($lv, $x, $y, $z, $radius, PersistentVFXTask::TYPE_STORM, 100, 3);
    }

    public function spawnLightningStrike($lv, $x, $y, $z) {
        $this->spawnElThor($lv, $x, $y, $z);
    }

    public function spawnRaigoStorm($center, $radius, $count) {
        $this->spawnRaigo($center, $radius);
    }

    // ==================== ENHANCED GOMU GOMU (RUBBER) VFX ====================

    public function spawnGomuPunch($lv, $x, $y, $z, $big = false) {
        $this->scatter($lv, $x, $y, $z, 0.6, 10, $this->pCrit());
        $this->ring($lv, $x, $y, $z, 1.2, 10, $this->pSmoke());

        if ($big) {
            $this->shockwave($lv, $x, $y - 0.5, $z, 4.0, 4, 16, $this->pCrit());
            $this->shockwave($lv, $x, $y, $z, 3.0, 3, 12, $this->pSmoke());
            $this->sphere($lv, $x, $y, $z, 1.5, 3, 8, $this->pDust(self::COL_RED_R, self::COL_RED_G, self::COL_RED_B));
            $this->splash($lv, $x, $y, $z, self::COL_SPLASH_RED);
            $this->boom($lv, $x, $y, $z, 3.5);
            $lv->addParticle(new HugeExplodeParticle($this->v($x, $y, $z)));
            $lv->addSound(new AnvilFallSound($this->v($x, $y, $z)));
        } else {
            $lv->addSound(new PopSound($this->v($x, $y, $z)));
        }
    }

    public function spawnGear2Effect($player) {
        $pos = $player->getPosition();
        $lv = $player->getLevel();
        $x = $pos->x;
        $y = $pos->y;
        $z = $pos->z;

        for ($i = 0; $i < 3; $i++) {
            $this->ring($lv, $x, $y + $i * 0.8, $z, 1.5 - $i * 0.3, 10, $this->pDust(self::COL_RED_R, self::COL_RED_G, self::COL_RED_B));
        }
        $this->spiral($lv, $x, $y, $z, 4, 1.2, 3, 18, $this->pSmoke());
        $this->scatter($lv, $x, $y + 1, $z, 1.5, 10, $this->pDust(255, 150, 150));
        $this->splash($lv, $x, $y + 2, $z, self::COL_SPLASH_RED);
        $this->boom($lv, $x, $y + 1, $z, 2.0);
        $lv->addSound(new FizzSound($this->v($x, $y, $z)));
    }

    public function spawnGear3Effect($player) {
        $pos = $player->getPosition();
        $lv = $player->getLevel();
        $x = $pos->x;
        $y = $pos->y;
        $z = $pos->z;

        $this->sphere($lv, $x, $y + 2, $z, 3, 6, 14, $this->pSmoke());
        $this->sphere($lv, $x, $y + 2, $z, 2, 4, 10, $this->pDust(self::COL_WHITE_R, self::COL_WHITE_G, self::COL_WHITE_B));
        $this->shockwave($lv, $x, $y, $z, 5, 4, 18, $this->pSmoke());
        $this->splash($lv, $x, $y + 3, $z, self::COL_SPLASH_WHITE);
        $this->boom($lv, $x, $y + 2, $z, 4.0);
        $lv->addParticle(new HugeExplodeParticle($this->v($x, $y + 2, $z)));
        $lv->addSound(new ExplodeSound($this->v($x, $y, $z)));
    }

    public function spawnGear4Effect($player) {
        $pos = $player->getPosition();
        $lv = $player->getLevel();
        $x = $pos->x;
        $y = $pos->y;
        $z = $pos->z;

        $this->beamDense($lv, $x, $y, $z, 10, 2, $this->pDust(self::COL_RED_R, self::COL_RED_G, self::COL_RED_B));
        for ($i = 0; $i < 5; $i++) {
            $this->ring($lv, $x, $y + $i * 1.5, $z, 2.5 - $i * 0.3, 14, $this->pDust(self::COL_RED_R, self::COL_RED_G, self::COL_RED_B));
            $this->ring($lv, $x, $y + $i * 1.5 + 0.5, $z, 2.0 - $i * 0.2, 10, $this->pSmoke());
        }
        $this->spiral($lv, $x, $y, $z, 8, 2, 4, 24, $this->pDust(self::COL_RED_R, self::COL_RED_G, self::COL_RED_B));
        $this->sphere($lv, $x, $y + 3, $z, 2.5, 5, 12, $this->pDust(self::COL_HAKI_R, self::COL_HAKI_G, self::COL_HAKI_B));
        $this->shockwave($lv, $x, $y, $z, 6, 5, 20, $this->pSmoke());
        $this->splash($lv, $x, $y + 2, $z, self::COL_SPLASH_RED);
        $this->splash($lv, $x, $y + 5, $z, self::COL_SPLASH_BLACK);
        $this->boom($lv, $x, $y + 2, $z, 5.0);
        $lv->addParticle(new HugeExplodeParticle($this->v($x, $y + 3, $z)));
        $lv->addSound(new ExplodeSound($this->v($x, $y, $z)));
        $lv->addSound(new AnvilFallSound($this->v($x, $y, $z)));
    }

    public function spawnKongGun($player, $range) {
        $dir = $player->getDirectionVector();
        $pos = $player->getPosition();
        $lv = $player->getLevel();
        $x = $pos->x;
        $y = $pos->y + 1.5;
        $z = $pos->z;

        $this->sphere($lv, $x, $y, $z, 2, 4, 10, $this->pSmoke());

        for ($i = 0; $i < 3; $i++) {
            $d = ($i + 1) * ($range / 3);
            $px = $x + $dir->x * $d;
            $pz = $z + $dir->z * $d;
            $this->ring($lv, $px, $y, $pz, 2 - $i * 0.3, 10, $this->pSmoke());
            $this->scatter($lv, $px, $y, $pz, 1.5, 6, $this->pCrit());
        }

        $ex = $x + $dir->x * $range;
        $ez = $z + $dir->z * $range;

        $this->sphere($lv, $ex, $y, $ez, 3.5, 6, 14, $this->pSmoke());
        $this->sphere($lv, $ex, $y, $ez, 2, 4, 10, $this->pDust(self::COL_HAKI_R, self::COL_HAKI_G, self::COL_HAKI_B));
        $this->shockwave($lv, $ex, $y - 1, $ez, 6, 5, 20, $this->pCrit());
        $this->star($lv, $ex, $y, $ez, 5, 8, $this->pSmoke());

        $this->boom($lv, $ex, $y, $ez, 6.0);
        $this->splash($lv, $ex, $y, $ez, self::COL_SPLASH_RED);
        $this->splash($lv, $ex, $y + 2, $ez, self::COL_SPLASH_BLACK);

        $lv->addParticle(new HugeExplodeParticle($this->v($ex, $y, $ez)));
        $lv->addSound(new ExplodeSound($this->v($ex, $y, $ez)));
        $lv->addSound(new AnvilFallSound($this->v($ex, $y, $ez)));
    }

    public function spawnGomuImpact($lv, $x, $y, $z, $big) {
        $this->spawnGomuPunch($lv, $x, $y, $z, $big);
    }

    // ==================== ENHANCED OPE OPE (ROOM) VFX ====================

    public function spawnRoom($player, $radius, $durationTicks) {
        $pos = $player->getPosition();
        $lv = $player->getLevel();
        $x = $pos->x;
        $y = $pos->y + 1;
        $z = $pos->z;

        $this->sphere($lv, $x, $y, $z, $radius, 8, 14, $this->pMobSpell(self::COL_CYAN_R, self::COL_CYAN_G, self::COL_CYAN_B));
        $this->sphere($lv, $x, $y, $z, $radius * 0.95, 6, 10, $this->pDust(self::COL_CYAN_R, self::COL_CYAN_G, self::COL_CYAN_B));

        for ($i = 0; $i < 4; $i++) {
            $this->ring($lv, $x, $y + ($i - 1.5) * ($radius * 0.4), $z, $radius * (0.6 + $i * 0.1), 16, $this->pMobSpell(self::COL_CYAN_R, self::COL_CYAN_G, self::COL_CYAN_B));
        }

        $this->cross($lv, $x, $y, $z, (int)$radius, $this->pDust(self::COL_CYAN_R, self::COL_CYAN_G, self::COL_CYAN_B));

        $this->splash($lv, $x, $y, $z, self::COL_SPLASH_CYAN);
        $this->splash($lv, $x, $y + $radius, $z, self::COL_SPLASH_BLUE);

        $lv->addSound(new ClickSound($this->v($x, $y, $z)));
        $lv->addSound(new AnvilUseSound($this->v($x, $y, $z)));

        $this->startPersistent($lv, $x, $y, $z, $radius, PersistentVFXTask::TYPE_ROOM, $durationTicks, 2);
    }

    public function spawnGammaKnife($target) {
        $pos = $target->getPosition();
        $lv = $target->getLevel();
        $x = $pos->x;
        $y = $pos->y + 1;
        $z = $pos->z;

        $this->cross($lv, $x, $y, $z, 4, $this->pMobSpell(self::COL_CYAN_R, self::COL_CYAN_G, self::COL_CYAN_B));

        for ($i = 0; $i < 6; $i++) {
            $a = ($i / 6) * M_PI * 2;
            $this->line($lv, $x, $y, $z, $x + cos($a) * 2.5, $y, $z + sin($a) * 2.5, 5, $this->pDust(self::COL_CYAN_R, self::COL_CYAN_G, self::COL_CYAN_B));
        }

        $this->sphere($lv, $x, $y, $z, 2, 5, 10, $this->pMobSpell(self::COL_CYAN_R, self::COL_CYAN_G, self::COL_CYAN_B));
        $this->sphere($lv, $x, $y, $z, 1.2, 3, 6, $this->pDust(self::COL_BLUE_R, self::COL_BLUE_G, self::COL_BLUE_B));

        $this->scatter($lv, $x, $y, $z, 1.5, 14, $this->pInstant());
        $this->shockwave($lv, $x, $y, $z, 3, 3, 12, $this->pMobSpell(self::COL_CYAN_R, self::COL_CYAN_G, self::COL_CYAN_B));

        $this->splash($lv, $x, $y, $z, self::COL_SPLASH_CYAN);
        $this->splash($lv, $x, $y + 1, $z, self::COL_SPLASH_BLUE);

        $this->boom($lv, $x, $y, $z, 2.0);
        $lv->addSound(new ClickSound($this->v($x, $y, $z)));
        $lv->addSound(new FizzSound($this->v($x, $y, $z)));
        $lv->addSound(new AnvilUseSound($this->v($x, $y, $z)));
    }

    public function spawnShambles($lv, $x1, $y1, $z1, $x2, $y2, $z2) {
        $this->sphere($lv, $x1, $y1 + 1, $z1, 2, 5, 10, $this->pMobSpell(self::COL_CYAN_R, self::COL_CYAN_G, self::COL_CYAN_B));
        $this->ring($lv, $x1, $y1, $z1, 2.5, 14, $this->pDust(self::COL_CYAN_R, self::COL_CYAN_G, self::COL_CYAN_B));
        $this->spiral($lv, $x1, $y1, $z1, 3, 1.5, 2, 12, $this->pInstant());
        $this->splash($lv, $x1, $y1 + 1, $z1, self::COL_SPLASH_CYAN);

        $this->sphere($lv, $x2, $y2 + 1, $z2, 2, 5, 10, $this->pMobSpell(self::COL_BLUE_R, self::COL_BLUE_G, self::COL_BLUE_B));
        $this->ring($lv, $x2, $y2, $z2, 2.5, 14, $this->pDust(self::COL_BLUE_R, self::COL_BLUE_G, self::COL_BLUE_B));
        $this->spiral($lv, $x2, $y2, $z2, 3, 1.5, 2, 12, $this->pInstant());
        $this->splash($lv, $x2, $y2 + 1, $z2, self::COL_SPLASH_BLUE);

        $this->line($lv, $x1, $y1 + 1, $z1, $x2, $y2 + 1, $z2, 16, $this->pDust(self::COL_CYAN_R, self::COL_CYAN_G, self::COL_CYAN_B));
        $this->line($lv, $x1, $y1 + 1.3, $z1, $x2, $y2 + 1.3, $z2, 12, $this->pInstant());

        $lv->addSound(new EndermanTeleportSound($this->v($x1, $y1, $z1)));
        $lv->addSound(new EndermanTeleportSound($this->v($x2, $y2, $z2)));
    }

    // ==================== ENHANCED HAKI VFX ====================

    public function spawnHakiHitEffect($target) {
        $pos = $target->getPosition();
        $lv = $target->getLevel();
        $x = $pos->x;
        $y = $pos->y + 1;
        $z = $pos->z;

        $this->scatter($lv, $x, $y, $z, 1.0, 16, $this->pCrit());
        $this->ring($lv, $x, $y - 0.5, $z, 2.5, 14, $this->pSmoke());
        $this->ring($lv, $x, $y, $z, 1.8, 10, $this->pDust(self::COL_HAKI_R, self::COL_HAKI_G, self::COL_HAKI_B));
        $this->shockwave($lv, $x, $y - 1, $z, 3.5, 3, 14, $this->pSmoke());

        $this->star($lv, $x, $y, $z, 2.5, 6, $this->pCrit());

        $this->splash($lv, $x, $y, $z, self::COL_SPLASH_BLACK);
        $this->splash($lv, $x, $y + 1, $z, self::COL_SPLASH_PURPLE);

        $this->boom($lv, $x, $y, $z, 2.5);
        $lv->addSound(new AnvilFallSound($this->v($x, $y, $z)));
    }

    public function spawnConquerorHaki($player, $radius) {
        $pos = $player->getPosition();
        $lv = $player->getLevel();
        $x = $pos->x;
        $y = $pos->y;
        $z = $pos->z;

        $this->beamDense($lv, $x, $y, $z, 20, 2, $this->pDust(self::COL_CONQUEROR_R, self::COL_CONQUEROR_G, self::COL_CONQUEROR_B));

        for ($i = 0; $i < 6; $i++) {
            $this->ring($lv, $x, $y + $i * 0.5, $z, $radius * (1 - $i * 0.1), 18 - $i * 2, $this->pDust(self::COL_CONQUEROR_R, self::COL_CONQUEROR_G, self::COL_CONQUEROR_B));
        }

        $this->shockwave($lv, $x, $y, $z, $radius + 3, 6, 24, $this->pDust(self::COL_HAKI_R, self::COL_HAKI_G, self::COL_HAKI_B));
        $this->shockwave($lv, $x, $y + 0.5, $z, $radius, 4, 18, $this->pSmoke());

        for ($i = 0; $i < 8; $i++) {
            $a = ($i / 8) * M_PI * 2;
            $this->line($lv, $x, $y + 1, $z, $x + cos($a) * $radius, $y + 1, $z + sin($a) * $radius, 8, $this->pDust(self::COL_CONQUEROR_R, self::COL_CONQUEROR_G, self::COL_CONQUEROR_B));
        }

        $this->lightning($lv, $x, $y, $z);
        for ($i = 0; $i < 4; $i++) {
            $a = ($i / 4) * M_PI * 2;
            $lx = $x + cos($a) * ($radius * 0.5);
            $lz = $z + sin($a) * ($radius * 0.5);
            $this->lightning($lv, $lx, $y, $lz);
        }

        $this->splash($lv, $x, $y + 2, $z, self::COL_SPLASH_PURPLE);
        $this->splash($lv, $x, $y + 5, $z, self::COL_SPLASH_BLACK);

        $this->boom($lv, $x, $y + 1, $z, 6.0);
        $lv->addParticle(new HugeExplodeParticle($this->v($x, $y + 2, $z)));
        $lv->addSound(new ExplodeSound($this->v($x, $y, $z)));
        $lv->addSound(new AnvilFallSound($this->v($x, $y, $z)));
        $lv->addSound(new GhastSound($this->v($x, $y, $z)));
    }

    // ==================== KEEP ALL EXISTING METHODS ====================
    // (spawnLogiaActivateEffect, spawnZoanTransformEffect, etc. - keeping them but enhanced)

    public function spawnLogiaActivateEffect($player) {
        $fid = $this->getFruitId($player);
        $pos = $player->getPosition();
        $lv = $player->getLevel();
        $x = $pos->x; $y = $pos->y; $z = $pos->z;

        switch ($fid) {
            case "moku_moku":
                for ($i = 0; $i < 4; $i++) {
                    $this->ring($lv, $x, $y + $i * 0.7, $z, 2.0 - $i * 0.25, 10, $this->pSmoke());
                }
                $this->spiral($lv, $x, $y, $z, 5, 1.8, 3, 18, $this->pSmoke());
                $this->scatter($lv, $x, $y + 2, $z, 2.5, 12, $this->pSmoke());
                $this->boom($lv, $x, $y + 1, $z, 2.0);
                $this->splash($lv, $x, $y + 2, $z, self::COL_SPLASH_WHITE);
                $lv->addSound(new FizzSound($this->v($x, $y, $z)));
                break;

            case "suna_suna":
                $this->spiralExpanding($lv, $x, $y, $z, 7, 0.5, 3.0, 4, 24, $this->pDust(self::COL_SAND_R, self::COL_SAND_G, self::COL_SAND_B));
                $this->spiral($lv, $x, $y, $z, 5, 2.0, 3, 16, $this->pSmoke());
                for ($i = 0; $i < 4; $i++) {
                    $this->ring($lv, $x, $y + $i * 1.2, $z, 2.5 - $i * 0.35, 14, $this->pDust(self::COL_SAND_R, self::COL_SAND_G, self::COL_SAND_B));
                }
                $this->splash($lv, $x, $y + 3, $z, self::COL_SPLASH_SAND);
                $lv->addSound(new FizzSound($this->v($x, $y, $z)));
                break;

            case "mera_mera":
                for ($i = 0; $i < 7; $i++) {
                    $this->ring($lv, $x, $y + $i * 0.7, $z, 2.6 - $i * 0.25, 16, $this->pFlame());
                    if ($i < 5) $this->ring($lv, $x, $y + $i * 0.7 + 0.35, $z, 1.8 - $i * 0.18, 10, $this->pLava());
                }
                $this->spiralExpanding($lv, $x, $y, $z, 8, 0.5, 2.5, 5, 24, $this->pFlame());
                $this->scatter($lv, $x, $y + 5, $z, 2.5, 12, $this->pFlame());
                $this->splash($lv, $x, $y + 1, $z, self::COL_SPLASH_ORANGE);
                $this->splash($lv, $x, $y + 5, $z, self::COL_SPLASH_RED);
                $this->boom($lv, $x, $y + 1, $z, 4.0);
                $lv->addParticle(new HugeExplodeParticle($this->v($x, $y + 2, $z)));
                $lv->addSound(new BlazeShootSound($this->v($x, $y, $z)));
                break;

            case "goro_goro":
                $this->lightning($lv, $x, $y, $z);
                $this->boltDownBranching($lv, $x, $z, $y + 25, $y, $this->pDust(self::COL_ELEC_R, self::COL_ELEC_G, self::COL_ELEC_B), 3);
                for ($b = 0; $b < 5; $b++) {
                    $ba = ($b / 5) * M_PI * 2;
                    $bx = $x + cos($ba) * 4;
                    $bz = $z + sin($ba) * 4;
                    $this->lightning($lv, $bx, $y, $bz);
                    $this->boltDown($lv, $bx, $bz, $y + 16, $y, $this->pDust(self::COL_ELEC2_R, self::COL_ELEC2_G, self::COL_ELEC2_B));
                }
                $this->sphere($lv, $x, $y + 1.5, $z, 3, 6, 12, $this->pDust(self::COL_ELEC_R, self::COL_ELEC_G, self::COL_ELEC_B));
                $this->shockwave($lv, $x, $y, $z, 6, 5, 20, $this->pInstant());
                $this->splash($lv, $x, $y + 1, $z, self::COL_SPLASH_YELLOW);
                $this->splash($lv, $x, $y + 1, $z, self::COL_SPLASH_ELECTRIC);
                $this->boom($lv, $x, $y, $z, 4.0);
                $lv->addParticle(new HugeExplodeParticle($this->v($x, $y + 1, $z)));
                $lv->addSound(new ExplodeSound($this->v($x, $y, $z)));
                $lv->addSound(new AnvilFallSound($this->v($x, $y, $z)));
                break;

            default:
                $this->spiral($lv, $x, $y, $z, 5, 2.5, 4, 18, $this->pEnchant());
                $this->splash($lv, $x, $y + 1, $z, self::COL_SPLASH_ORANGE);
                $lv->addSound(new FizzSound($this->v($x, $y, $z)));
        }
    }

    public function spawnAwakeningEffect($player) {
        $pos = $player->getPosition();
        $lv = $player->getLevel();
        $x = $pos->x; $y = $pos->y; $z = $pos->z;

        for ($i = 0; $i < 3; $i++) {
            $ox = ($i - 1) * 0.3;
            $this->beamDense($lv, $x + $ox, $y, $z + $ox, 25, 2, $this->pEnchant());
        }

        for ($r = 1; $r <= 8; $r++) {
            $this->ring($lv, $x, $y + $r, $z, $r * 1.0, 20, $this->pDust(self::COL_PURPLE_R, self::COL_PURPLE_G, self::COL_PURPLE_B));
        }

        $this->spiralExpanding($lv, $x, $y, $z, 16, 1, 4, 7, 35, $this->pFlame());
        $this->spiralExpanding($lv, $x, $y, $z, 14, 0.5, 3.5, 5, 28, $this->pEnchant());
        $this->sphere($lv, $x, $y + 5, $z, 4, 7, 14, $this->pDust(self::COL_GOLD_R, self::COL_GOLD_G, self::COL_GOLD_B));
        $this->shockwave($lv, $x, $y, $z, 12, 7, 28, $this->pDust(self::COL_WHITE_R, self::COL_WHITE_G, self::COL_WHITE_B));

        $this->splash($lv, $x, $y + 2, $z, self::COL_SPLASH_PURPLE);
        $this->splash($lv, $x, $y + 6, $z, self::COL_SPLASH_RED);
        $this->splash($lv, $x, $y + 10, $z, self::COL_SPLASH_GOLD);
        $this->splash($lv, $x, $y + 14, $z, self::COL_SPLASH_WHITE);

        $this->boom($lv, $x, $y + 3, $z, 7.0);
        $this->boom($lv, $x, $y + 8, $z, 4.5);
        for ($i = 0; $i < 10; $i++) {
            $a = ($i / 10) * M_PI * 2;
            $this->boom($lv, $x + cos($a) * 6, $y + 2, $z + sin($a) * 6, 3.0);
        }

        $lv->addParticle(new HugeExplodeParticle($this->v($x, $y + 3, $z)));
        $lv->addParticle(new HugeExplodeParticle($this->v($x, $y + 8, $z)));
        $lv->addSound(new ExplodeSound($this->v($x, $y, $z)));
        $lv->addSound(new AnvilFallSound($this->v($x, $y, $z)));
        $lv->addSound(new GhastSound($this->v($x, $y, $z)));
    }

    // Keep other existing methods...
    public function spawnLogiaPassiveEffect($player) {
        $fid = $this->getFruitId($player);
        $pos = $player->getPosition();
        $lv  = $player->getLevel();
        $x = $pos->x; $y = $pos->y; $z = $pos->z;
        $t = microtime(true);

        switch ($fid) {
            case "moku_moku":
                $a = $t * 1.4;
                $lv->addParticle(new WhiteSmokeParticle($this->v($x + cos($a) * 0.6, $y + 0.5, $z + sin($a) * 0.6)));
                $lv->addParticle(new WhiteSmokeParticle($this->v($x + cos($a + M_PI) * 0.5, $y + 0.9, $z + sin($a + M_PI) * 0.5)));
                if (mt_rand(0, 2) === 0) {
                    $lv->addParticle(new SmokeParticle($this->v($x + mt_rand(-5,5)/10, $y + 1.2, $z + mt_rand(-5,5)/10), 0));
                }
                break;

            case "suna_suna":
                $a = $t * 2.2;
                $lv->addParticle(new InstantEnchantParticle($this->v($x + cos($a) * 0.8, $y + 0.7, $z + sin($a) * 0.8)));
                if (mt_rand(0, 1) === 0) {
                    $lv->addParticle(new InstantEnchantParticle($this->v($x + cos($a + 2.09) * 0.65, $y + 1.0, $z + sin($a + 2.09) * 0.65)));
                }
                if (mt_rand(0, 3) === 0) {
                    $lv->addParticle(new EnchantParticle($this->v($x + mt_rand(-6,6)/10, $y + 0.9, $z + mt_rand(-6,6)/10)));
                }
                break;

            case "mera_mera":
                for ($i = 0; $i < 2; $i++) {
                    $a = $t * 2.5 + $i * M_PI;
                    $lv->addParticle(new FlameParticle($this->v($x + cos($a) * 0.7, $y + 0.5 + sin($t * 3 + $i) * 0.4, $z + sin($a) * 0.7)));
                    $lv->addParticle(new EntityFlameParticle($this->v($x + cos($a + 0.4) * 0.5, $y + 0.9, $z + sin($a + 0.4) * 0.5)));
                }
                if (mt_rand(0, 2) === 0) {
                    $lv->addParticle(new LavaDripParticle($this->v($x + mt_rand(-4,4)/10, $y + 1.4, $z + mt_rand(-4,4)/10)));
                }
                break;

            case "goro_goro":
                $orbCount = 3;
                for ($i = 0; $i < $orbCount; $i++) {
                    $orbA = $t * 4.5 + ($i / $orbCount) * M_PI * 2;
                    $orbY = $y + 0.9 + sin($t * 3.0 + $i * 1.2) * 0.45;
                    $lv->addParticle(new InstantEnchantParticle($this->v($x + cos($orbA) * 0.85, $orbY, $z + sin($orbA) * 0.85)));
                    $lv->addParticle(new EnchantParticle($this->v($x + cos($orbA - 0.25) * 0.85, $orbY + 0.05, $z + sin($orbA - 0.25) * 0.85)));
                }
                if (mt_rand(0, 2) === 0) {
                    $lv->addParticle(new EnchantParticle($this->v($x + mt_rand(-7,7)/10, $y + mt_rand(5,18)/10, $z + mt_rand(-7,7)/10)));
                }
                break;

            case "hie_hie":
                $a = $t * 1.8;
                $lv->addParticle(new EnchantParticle($this->v($x + cos($a) * 0.8, $y + 0.7, $z + sin($a) * 0.8)));
                $lv->addParticle(new EnchantParticle($this->v($x + cos($a + M_PI) * 0.6, $y + 1.1, $z + sin($a + M_PI) * 0.6)));
                if (mt_rand(0, 3) === 0) {
                    $lv->addParticle(new InstantEnchantParticle($this->v($x + mt_rand(-6,6)/10, $y + 1.3, $z + mt_rand(-6,6)/10)));
                }
                break;

            case "sound_sound":
                // Two pink note-arcs orbiting at different heights + note particle bursts
                $a = $t * 3.2;
                $lv->addParticle(new DustParticle($this->v($x + cos($a) * 0.85, $y + 0.8 + sin($t * 2.5) * 0.25, $z + sin($a) * 0.85), 255, 50, 180));
                $lv->addParticle(new DustParticle($this->v($x + cos($a + M_PI) * 0.7, $y + 1.1 + sin($t * 2.5 + M_PI) * 0.2, $z + sin($a + M_PI) * 0.7), 160, 0, 255));
                if (mt_rand(0, 2) === 0) {
                    $lv->addParticle(new InstantEnchantParticle($this->v($x + cos($a + M_PI * 0.5) * 0.6, $y + 0.9, $z + sin($a + M_PI * 0.5) * 0.6)));
                }
                // Floating note event particle every ~3 calls
                if (mt_rand(0, 2) === 0) {
                    $pk = new \pocketmine\network\protocol\LevelEventPacket();
                    $pk->evid = 2000; $pk->data = mt_rand(0, 24);
                    $pk->x = (float)($x + mt_rand(-8,8)/10); $pk->y = (float)($y + 1.8); $pk->z = (float)($z + mt_rand(-8,8)/10);
                    foreach ($lv->getPlayers() as $pl) { $pl->dataPacket($pk); }
                }
                break;

            case "pika_pika":
                $a = $t * 5.0;
                $lv->addParticle(new InstantEnchantParticle($this->v($x + cos($a) * 0.9, $y + 1.0, $z + sin($a) * 0.9)));
                $lv->addParticle(new InstantEnchantParticle($this->v($x + cos($a + M_PI * 0.5) * 0.6, $y + 0.7, $z + sin($a + M_PI * 0.5) * 0.6)));
                if (mt_rand(0, 1) === 0) {
                    $lv->addParticle(new EnchantParticle($this->v($x + cos($a + M_PI) * 0.75, $y + 1.2, $z + sin($a + M_PI) * 0.75)));
                }
                if (mt_rand(0, 3) === 0) {
                    $lv->addParticle(new InstantEnchantParticle($this->v($x + mt_rand(-8,8)/10, $y + mt_rand(8,20)/10, $z + mt_rand(-8,8)/10)));
                }
                break;
        }
    }
    public function spawnHitEffect($target, $fruitType, $rarity) {
        $pos = $target->getPosition();
        $lv = $target->getLevel();
        $x = $pos->x; $y = $pos->y + 1; $z = $pos->z;
        $big = ($rarity === "legendary" || $rarity === "mythical");

        switch ($fruitType) {
            case "paramecia":
                /*$this->scatter($lv, $x, $y, $z, 1.0, 10, $this->pDust(self::COL_PURPLE_R, self::COL_PURPLE_G, self::COL_PURPLE_B));
                if ($big) {
                    $this->splash($lv, $x, $y, $z, self::COL_SPLASH_PURPLE);
                    $this->ring($lv, $x, $y - 0.5, $z, 2.0, 12, $this->pEnchant());
                    $this->shockwave($lv, $x, $y, $z, 2.5, 2, 10, $this->pDust(self::COL_PURPLE_R, self::COL_PURPLE_G, self::COL_PURPLE_B));
                    $this->boom($lv, $x, $y, $z, 2.0);*/
                    $lv->addSound(new AnvilFallSound($this->v($x, $y, $z)));
                break;
            case "logia":
                /*$this->scatter($lv, $x, $y, $z, 1.0, 10, $this->pFlame());
                $this->scatter($lv, $x, $y - 0.5, $z, 0.6, 6, $this->pLava());
                if ($big) {
                    $this->splash($lv, $x, $y, $z, self::COL_SPLASH_ORANGE);
                    $this->ring($lv, $x, $y, $z, 2.0, 12, $this->pFlame());
                    $this->shockwave($lv, $x, $y, $z, 3.0, 3, 12, $this->pLava());
                    $this->boom($lv, $x, $y, $z, 2.5);*/
                    $lv->addSound(new BlazeShootSound($this->v($x, $y, $z)));
                break;
            case "zoan":
                /*$this->scatter($lv, $x, $y, $z, 0.8, 10, $this->pCrit());
                if ($big) {
                    $this->splash($lv, $x, $y, $z, self::COL_SPLASH_GREEN);
                    $this->ring($lv, $x, $y, $z, 2.0, 12, $this->pSmoke());
                    $this->shockwave($lv, $x, $y, $z, 3.0, 3, 12, $this->pCrit());
                    $this->boom($lv, $x, $y, $z, 2.0);*/
                    $lv->addSound(new ExplodeSound($this->v($x, $y, $z)));
                break;
        }
        $lv->addSound(new ClickSound($this->v($x, $y, $z)));
    }

    public function spawnLogiaPassEffect($player) {
        $fid = $this->getFruitId($player);
        $pos = $player->getPosition();
        $lv = $player->getLevel();
        $x = $pos->x; $y = $pos->y; $z = $pos->z;

        switch ($fid) {
            case "moku_moku":
                $this->scatter($lv, $x, $y + 0.5, $z, 1.0, 10, $this->pSmoke());
                $this->ring($lv, $x, $y, $z, 1.5, 8, $this->pSmoke());
                break;
            case "suna_suna":
                $this->scatter($lv, $x, $y + 0.5, $z, 1.0, 12, $this->pDust(self::COL_SAND_R, self::COL_SAND_G, self::COL_SAND_B));
                $this->ring($lv, $x, $y, $z, 1.5, 10, $this->pDust(self::COL_SAND_R, self::COL_SAND_G, self::COL_SAND_B));
                break;
            case "mera_mera":
                $this->scatter($lv, $x, $y + 0.5, $z, 1.0, 12, $this->pFlame());
                $this->ring($lv, $x, $y, $z, 1.5, 10, $this->pFlame());
                $this->scatter($lv, $x, $y + 1, $z, 0.5, 4, $this->pLava());
                break;
            case "goro_goro":
                $this->scatter($lv, $x, $y + 0.5, $z, 1.0, 10, $this->pDust(self::COL_ELEC_R, self::COL_ELEC_G, self::COL_ELEC_B));
                $this->ring($lv, $x, $y, $z, 1.5, 8, $this->pInstant());
                if (mt_rand(0, 1) === 0) $this->lightning($lv, $x + mt_rand(-20, 20) / 10, $y, $z + mt_rand(-20, 20) / 10);
                break;
            default:
                $this->scatter($lv, $x, $y + 0.5, $z, 1.0, 8, $this->pSpell(self::COL_WHITE_R, self::COL_WHITE_G, self::COL_WHITE_B));
        }
        $lv->addSound(new FizzSound($this->v($x, $y, $z)));
    }

    // ==================== ENHANCED ZOAN VFX ====================

    public function spawnZoanTransformEffect($player) {
        $fid = $this->getFruitId($player);
        $pos = $player->getPosition();
        $lv = $player->getLevel();
        $x = $pos->x; $y = $pos->y; $z = $pos->z;

        switch ($fid) {
            case "inu_inu":
                for ($i = 0; $i < 4; $i++) {
                    $this->ring($lv, $x, $y + $i * 0.8, $z, 2.2 - $i * 0.35, 14, $this->pSmoke());
                    $this->ring($lv, $x, $y + $i * 0.8 + 0.4, $z, 1.6 - $i * 0.25, 10, $this->pDust(self::COL_SMOKE_R, self::COL_SMOKE_G, self::COL_SMOKE_B));
                }
                $this->spiral($lv, $x, $y, $z, 5, 2, 3, 18, $this->pSmoke());
                $this->scatter($lv, $x, $y + 1.5, $z, 3, 16, $this->pSmoke());
                $this->splash($lv, $x, $y + 2, $z, self::COL_SPLASH_WHITE);
                $lv->addParticle(new HugeExplodeParticle($this->v($x, $y + 1, $z)));
                $this->boom($lv, $x, $y + 1, $z, 3.0);
                $lv->addSound(new GhastSound($this->v($x, $y, $z)));
                break;

            case "neko_neko":
                for ($i = 0; $i < 10; $i++) {
                    $a = ($i / 10) * M_PI * 2;
                    $this->line($lv, $x, $y + 1, $z, $x + cos($a) * 4, $y + 1, $z + sin($a) * 4, 8, $this->pCrit());
                    $this->line($lv, $x, $y + 1.5, $z, $x + cos($a + 0.15) * 3, $y + 1.5, $z + sin($a + 0.15) * 3, 6, $this->pDust(self::COL_GOLD_R, self::COL_GOLD_G, self::COL_GOLD_B));
                }
                $this->sphere($lv, $x, $y + 1.2, $z, 2, 5, 10, $this->pEnchant());
                $this->scatter($lv, $x, $y + 1, $z, 2.5, 14, $this->pEnchant());
                $this->shockwave($lv, $x, $y, $z, 4, 3, 14, $this->pCrit());
                $this->splash($lv, $x, $y + 1, $z, self::COL_SPLASH_GOLD);
                $lv->addParticle(new HugeExplodeParticle($this->v($x, $y + 1, $z)));
                $this->boom($lv, $x, $y + 1, $z, 3.5);
                $lv->addSound(new BlazeShootSound($this->v($x, $y, $z)));
                break;

            case "tori_tori":
                for ($side = -1; $side <= 1; $side += 2) {
                    for ($i = 0; $i < 14; $i++) {
                        $d = $i * 0.3;
                        $wy = $y + 1.5 + sin($i * 0.4) * 0.8;
                        $lv->addParticle(new DustParticle($this->v($x + $side * $d, $wy, $z), self::COL_PHOENIX_R, self::COL_PHOENIX_G, self::COL_PHOENIX_B));
                        $lv->addParticle(new MobSpellParticle($this->v($x + $side * $d, $wy - 0.25, $z + 0.25), self::COL_CYAN_R, self::COL_CYAN_G, self::COL_CYAN_B));
                    }
                }
                $this->beamDense($lv, $x, $y, $z, 12, 2, $this->pDust(self::COL_PHOENIX_R, self::COL_PHOENIX_G, self::COL_PHOENIX_B));
                for ($r = 1; $r <= 5; $r++) {
                    $this->ring($lv, $x, $y + $r * 0.8, $z, 4 - $r * 0.5, 16, $this->pMobSpell(self::COL_CYAN_R, self::COL_CYAN_G, self::COL_CYAN_B));
                }
                $this->sphere($lv, $x, $y + 2.5, $z, 2.5, 6, 10, $this->pDust(self::COL_PHOENIX_R, self::COL_PHOENIX_G, self::COL_PHOENIX_B));
                $this->splash($lv, $x, $y + 1, $z, self::COL_SPLASH_CYAN);
                $this->splash($lv, $x, $y + 5, $z, self::COL_SPLASH_BLUE);
                $this->boom($lv, $x, $y + 1, $z, 3.5);
                $lv->addSound(new BatSound($this->v($x, $y, $z)));
                break;

            case "uo_uo":
                $this->spiralExpanding($lv, $x, $y, $z, 14, 1, 4.5, 6, 35, $this->pFlame());
                $this->spiralExpanding($lv, $x, $y, $z, 12, 0.5, 3.5, 5, 28, $this->pLava());
                for ($i = 0; $i < 10; $i++) {
                    $a = ($i / 10) * M_PI * 2;
                    $this->beamDense($lv, $x + cos($a) * 3.5, $y, $z + sin($a) * 3.5, 8, 1.5, $this->pFlame());
                }
                $this->sphere($lv, $x, $y + 5, $z, 4, 6, 14, $this->pFlame());
                $this->sphere($lv, $x, $y + 5, $z, 2.5, 4, 10, $this->pLava());
                $this->shockwave($lv, $x, $y, $z, 8, 6, 24, $this->pFlame());
                $this->scatter($lv, $x, $y + 3, $z, 5, 20, $this->pLava());
                $this->splash($lv, $x, $y + 2, $z, self::COL_SPLASH_RED);
                $this->splash($lv, $x, $y + 7, $z, self::COL_SPLASH_DARK_RED);
                $this->boom($lv, $x, $y + 3, $z, 6.0);
                for ($i = 0; $i < 8; $i++) {
                    $a = ($i / 8) * M_PI * 2;
                    $this->boom($lv, $x + cos($a) * 5, $y + 1, $z + sin($a) * 5, 3.0);
                }
                $lv->addParticle(new HugeExplodeParticle($this->v($x, $y + 3, $z)));
                $lv->addParticle(new HugeExplodeParticle($this->v($x, $y + 6, $z)));
                $lv->addSound(new ExplodeSound($this->v($x, $y, $z)));
                $lv->addSound(new AnvilFallSound($this->v($x, $y, $z)));
                break;

            default:
                $this->spiral($lv, $x, $y, $z, 5, 2.5, 4, 18, $this->pSpell(self::COL_WHITE_R, self::COL_WHITE_G, self::COL_WHITE_B));
                $lv->addParticle(new HugeExplodeParticle($this->v($x, $y + 1, $z)));
                $this->boom($lv, $x, $y + 1, $z, 2.5);
                $lv->addSound(new FizzSound($this->v($x, $y, $z)));
        }
    }

    public function spawnZoanPassiveEffect($player) {
        $fid = $this->getFruitId($player);
        $pos = $player->getPosition();
        $lv  = $player->getLevel();
        $x = $pos->x; $y = $pos->y; $z = $pos->z;
        $t = microtime(true);

        $yawRad = deg2rad($player->getYaw());
        $rightX =  cos($yawRad);
        $rightZ =  sin($yawRad);
        $fwdX   = -sin($yawRad);
        $fwdZ   =  cos($yawRad);

        switch ($fid) {

            case "inu_inu":
                $a = $t * 2.8;
                $lv->addParticle(new EnchantParticle($this->v($x + cos($a) * 0.8, $y + 0.7, $z + sin($a) * 0.8)));
                $lv->addParticle(new EnchantParticle($this->v($x + cos($a + M_PI) * 0.65, $y + 1.0, $z + sin($a + M_PI) * 0.65)));
                if (mt_rand(0, 3) === 0) {
                    $lv->addParticle(new CriticalParticle($this->v($x + mt_rand(-5,5)/10, $y + 1.1, $z + mt_rand(-5,5)/10)));
                }
                break;

            case "neko_neko":
                $a = $t * 5.5;
                $lv->addParticle(new EnchantParticle($this->v($x + cos($a) * 0.75, $y + 0.8, $z + sin($a) * 0.75)));
                $lv->addParticle(new EnchantParticle($this->v($x + cos($a + M_PI * 0.7) * 0.6, $y + 1.1, $z + sin($a + M_PI * 0.7) * 0.6)));
                if (mt_rand(0, 2) === 0) {
                    $lv->addParticle(new CriticalParticle($this->v($x + mt_rand(-5,5)/10, $y + mt_rand(8,16)/10, $z + mt_rand(-5,5)/10)));
                }
                if (mt_rand(0, 4) === 0) {
                    $lv->addParticle(new InstantEnchantParticle($this->v($x + cos($a + M_PI) * 0.9, $y + 0.6, $z + sin($a + M_PI) * 0.9)));
                }
                break;

            case "tori_tori":
                for ($i = 0; $i < 2; $i++) {
                    $a = $t * 1.5 + $i * M_PI;
                    $lv->addParticle(new EnchantParticle($this->v($x + cos($a) * 0.9, $y + 1.1 + sin($t * 2 + $i) * 0.35, $z + sin($a) * 0.9)));
                    $lv->addParticle(new InstantEnchantParticle($this->v($x + cos($a + 0.4) * 0.7, $y + 0.8, $z + sin($a + 0.4) * 0.7)));
                }
                if (mt_rand(0, 3) === 0) {
                    $lv->addParticle(new EnchantParticle($this->v($x + mt_rand(-6,6)/10, $y + mt_rand(10,18)/10, $z + mt_rand(-6,6)/10)));
                }
                break;

            case "tori_tori_falcon":
                $a = $t * 7.0;
                $lv->addParticle(new CriticalParticle($this->v($x + cos($a) * 0.85, $y + 1.0, $z + sin($a) * 0.85)));
                $lv->addParticle(new CriticalParticle($this->v($x + cos($a + M_PI * 0.5) * 0.65, $y + 1.3, $z + sin($a + M_PI * 0.5) * 0.65)));
                if (mt_rand(0, 1) === 0) {
                    $lv->addParticle(new InstantEnchantParticle($this->v($x + cos($a + M_PI) * 0.75, $y + 0.9, $z + sin($a + M_PI) * 0.75)));
                }
                if (mt_rand(0, 2) === 0) {
                    $lv->addParticle(new EnchantParticle($this->v($x + $fwdX * mt_rand(3,8)/10, $y + 1.2, $z + $fwdZ * mt_rand(3,8)/10)));
                }
                break;

            case "zou_zou":
                $a = $t * 0.9;
                for ($i = 0; $i < 3; $i++) {
                    $oa = $a + ($i / 3) * M_PI * 2;
                    if ($i % 2 === 0) {
                        $lv->addParticle(new CriticalParticle($this->v($x + cos($oa) * 1.1, $y + 0.5 + sin($oa * 0.5) * 0.15, $z + sin($oa) * 1.1)));
                    } else {
                        $lv->addParticle(new EnchantParticle($this->v($x + cos($oa) * 1.0, $y + 0.55, $z + sin($oa) * 1.0)));
                    }
                }
                if (mt_rand(0, 3) === 0) {
                    for ($tusk = 0; $tusk < 2; $tusk++) {
                        $side = ($tusk === 0) ? -1.0 : 1.0;
                        for ($p = 0; $p < 3; $p++) {
                            $prog = $p / 2.0;
                            $tx = $x + $fwdX * (0.4 + $prog * 1.0) + $rightX * $side * (0.35 + $prog * 0.25);
                            $ty = $y + 1.4 + $prog * 0.4;
                            $tz = $z + $fwdZ * (0.4 + $prog * 1.0) + $rightZ * $side * (0.35 + $prog * 0.25);
                            $lv->addParticle(new InstantEnchantParticle($this->v($tx, $ty, $tz)));
                        }
                    }
                }
                if (mt_rand(0, 5) === 0) {
                    $lv->addParticle(new CriticalParticle($this->v($x + mt_rand(-14,14)/10, $y + 0.2, $z + mt_rand(-14,14)/10)));
                }
                break;

            case "trex_trex":
                $a = $t * 6.5;
                $lv->addParticle(new CriticalParticle($this->v($x + cos($a) * 0.85, $y + 0.9 + sin($t * 4) * 0.35, $z + sin($a) * 0.85)));
                $lv->addParticle(new CriticalParticle($this->v($x + cos($a + M_PI) * 0.7, $y + 1.1, $z + sin($a + M_PI) * 0.7)));
                if (mt_rand(0, 1) === 0) {
                    $lv->addParticle(new InstantEnchantParticle($this->v($x + cos($a + M_PI * 0.5) * 0.6, $y + 0.8, $z + sin($a + M_PI * 0.5) * 0.6)));
                }
                if (mt_rand(0, 3) === 0) {
                    $spikeA = $t * 3.0 + mt_rand(0, 628) / 100.0;
                    $lv->addParticle(new EnchantParticle($this->v($x + cos($spikeA) * 0.9, $y + 0.2, $z + sin($spikeA) * 0.9)));
                    $lv->addParticle(new CriticalParticle($this->v($x + cos($spikeA + 0.5) * 1.1, $y + 0.4, $z + sin($spikeA + 0.5) * 1.1)));
                }
                break;

            case "uo_uo":
                $fireIdx = (int)($t * 3) % 3;
                $a = $t * 1.8 + $fireIdx * M_PI * 2 / 3;
                $lv->addParticle(new FlameParticle($this->v($x + cos($a) * 1.2, $y + 0.5 + sin($t * 2.5 + $fireIdx) * 0.7, $z + sin($a) * 1.2)));
                if (mt_rand(0, 2) === 0) {
                    $a2 = $t * 1.8 + (($fireIdx + 1) % 3) * M_PI * 2 / 3;
                    $lv->addParticle(new EntityFlameParticle($this->v($x + cos($a2) * 0.9, $y + 0.4, $z + sin($a2) * 0.9)));
                }
                $eyeX = $x + $fwdX * 0.25 - $rightX * 0.28;
                $lv->addParticle(new RedstoneParticle($this->v($eyeX, $y + 1.55, $z + $fwdZ * 0.25 - $rightZ * 0.28), 1));
                if (mt_rand(0, 2) === 0) {
                    $spokeA = $t * 7.0 + mt_rand(0, 314) / 100.0;
                    for ($seg = 0; $seg < 3; $seg++) {
                        $dist = ($seg + 1) * 0.44;
                        $zz   = ($seg % 2 === 0) ? 0.15 : -0.15;
                        $nx   = cos($spokeA) * $dist - sin($spokeA) * $zz;
                        $nz   = sin($spokeA) * $dist + cos($spokeA) * $zz;
                        $lv->addParticle(new EnchantParticle($this->v($x + $nx, $y + 0.85 + $seg * 0.06, $z + $nz)));
                    }
                    if (mt_rand(0, 2) === 0) {
                        $spokeA2 = $spokeA + M_PI * 0.65;
                        for ($seg = 0; $seg < 2; $seg++) {
                            $dist = ($seg + 1) * 0.44;
                            $zz   = ($seg % 2 === 0) ? -0.12 : 0.12;
                            $nx   = cos($spokeA2) * $dist - sin($spokeA2) * $zz;
                            $nz   = sin($spokeA2) * $dist + cos($spokeA2) * $zz;
                            $lv->addParticle(new CriticalParticle($this->v($x + $nx, $y + 0.75 + $seg * 0.06, $z + $nz)));
                        }
                    }
                }
                break;

            case "gear_fourth":
                $a1 = $t * 2.8;
                $a2 = $t * 2.8 + M_PI;
                $lv->addParticle(new DustParticle($this->v($x + cos($a1) * 0.9, $y + 0.8 + sin($t * 3) * 0.25, $z + sin($a1) * 0.9), self::COL_RED_R, self::COL_RED_G, self::COL_RED_B));
                $lv->addParticle(new DustParticle($this->v($x + cos($a2) * 0.9, $y + 0.8 + sin($t * 3 + M_PI) * 0.25, $z + sin($a2) * 0.9), self::COL_RED_R, self::COL_RED_G, self::COL_RED_B));
                if (mt_rand(0, 2) === 0) {
                    $lv->addParticle(new FlameParticle($this->v($x + cos($a1 + 0.5) * 0.7, $y + 0.5, $z + sin($a1 + 0.5) * 0.7)));
                }
                if (mt_rand(0, 3) === 0) {
                    $lv->addParticle(new CriticalParticle($this->v($x + mt_rand(-8, 8) / 10.0, $y + mt_rand(5, 15) / 10.0, $z + mt_rand(-8, 8) / 10.0)));
                }
                if (mt_rand(0, 5) === 0) {
                    $lv->addParticle(new SmokeParticle($this->v($x + cos($a2 - 0.3) * 0.6, $y + 1.2, $z + sin($a2 - 0.3) * 0.6)));
                }
                break;
        }
    }
    // ==================== ENHANCED DOMAIN/PERSISTENT VFX ====================

    public function spawnFireDomain($player, $radius, $durationTicks) {
        $pos = $player->getPosition();
        $lv = $player->getLevel();
        $x = $pos->x; $y = $pos->y; $z = $pos->z;

        $this->shockwave($lv, $x, $y, $z, $radius, 4, 18, $this->pFlame());
        $this->ring($lv, $x, $y + 0.5, $z, $radius * 0.8, 14, $this->pLava());

        $this->startPersistent($lv, $x, $y, $z, $radius, PersistentVFXTask::TYPE_FIRE_DOME, $durationTicks, 2);
        $lv->addSound(new BlazeShootSound($this->v($x, $y, $z)));
        $this->splash($lv, $x, $y + 2, $z, self::COL_SPLASH_ORANGE);
    }

    public function spawnStormDomain($player, $radius, $durationTicks) {
        $pos = $player->getPosition();
        $lv = $player->getLevel();
        $x = $pos->x; $y = $pos->y; $z = $pos->z;

        $this->shockwave($lv, $x, $y, $z, $radius, 4, 18, $this->pInstant());
        $this->sphere($lv, $x, $y + $radius * 0.8, $z, $radius * 0.5, 5, 12, $this->pBigSmoke());

        $this->startPersistent($lv, $x, $y, $z, $radius, PersistentVFXTask::TYPE_STORM, $durationTicks, 2);
        $lv->addSound(new ExplodeSound($this->v($x, $y, $z)));
        $this->splash($lv, $x, $y + $radius, $z, self::COL_SPLASH_YELLOW);
    }

    public function spawnSandDomain($player, $radius, $durationTicks) {
        $pos = $player->getPosition();
        $lv = $player->getLevel();
        $x = $pos->x; $y = $pos->y; $z = $pos->z;

        $this->spiralExpanding($lv, $x, $y, $z, 6, 0.5, $radius, 4, 24, $this->pDust(self::COL_SAND_R, self::COL_SAND_G, self::COL_SAND_B));
        $this->shockwave($lv, $x, $y, $z, $radius, 4, 18, $this->pSmoke());

        $this->startPersistent($lv, $x, $y, $z, $radius, PersistentVFXTask::TYPE_SAND, $durationTicks, 2);
        $lv->addSound(new FizzSound($this->v($x, $y, $z)));
        $this->splash($lv, $x, $y + 2, $z, self::COL_SPLASH_SAND);
    }

    public function spawnPhoenixDomain($player, $radius, $durationTicks) {
        $pos = $player->getPosition();
        $lv = $player->getLevel();
        $x = $pos->x; $y = $pos->y; $z = $pos->z;

        $this->sphere($lv, $x, $y + 1.5, $z, $radius * 0.6, 5, 12, $this->pMobSpell(self::COL_CYAN_R, self::COL_CYAN_G, self::COL_CYAN_B));
        $this->shockwave($lv, $x, $y, $z, $radius, 4, 18, $this->pDust(self::COL_PHOENIX_R, self::COL_PHOENIX_G, self::COL_PHOENIX_B));

        $this->startPersistent($lv, $x, $y, $z, $radius, PersistentVFXTask::TYPE_PHOENIX, $durationTicks, 2);
        $lv->addSound(new BatSound($this->v($x, $y, $z)));
        $this->splash($lv, $x, $y + 2, $z, self::COL_SPLASH_CYAN);
    }


    public function spawnSoundDomain($player, $radius, $durationTicks) {
        $pos = $player->getPosition();
        $lv  = $player->getLevel();
        $x = $pos->x; $y = $pos->y; $z = $pos->z;

        // Initial burst: pink ribbon rings expand outward
        for ($ring = 0; $ring < 3; $ring++) {
            $rr  = $radius * (0.3 + $ring * 0.35);
            $pts = 14 + $ring * 4;
            for ($i = 0; $i < $pts; $i++) {
                $a = ($i / $pts) * M_PI * 2;
                $lv->addParticle(new DustParticle(
                    $this->v($x + cos($a) * $rr, $y + 0.5, $z + sin($a) * $rr),
                    255, 50, 180
                ));
            }
        }
        // Note burst in all directions
        for ($i = 0; $i < 12; $i++) {
            $a = ($i / 12) * M_PI * 2;
            $d = $radius * 0.6;
            $pk = new \pocketmine\network\protocol\LevelEventPacket();
            $pk->evid = 2000; $pk->data = ($i * 2) % 25;
            $pk->x = (float)($x + cos($a) * $d); $pk->y = (float)($y + 1.5); $pk->z = (float)($z + sin($a) * $d);
            foreach ($lv->getPlayers() as $pl) { $pl->dataPacket($pk); }
        }

        $this->startPersistent($lv, $x, $y, $z, $radius, \OnePiece\Fruits\PersistentVFXTask::TYPE_SOUND, $durationTicks, 2);
        $lv->addSound(new \pocketmine\level\sound\FizzSound($this->v($x, $y, $z)));
        $lv->addSound(new \pocketmine\level\sound\BlazeShootSound($this->v($x, $y, $z)));
        $this->splash($lv, $x, $y + 1, $z, 16714930);
    }
    public function spawnSlashDomain($player, $radius, $durationTicks) {
        $pos = $player->getPosition();
        $lv = $player->getLevel();
        $x = $pos->x; $y = $pos->y; $z = $pos->z;

        $this->star($lv, $x, $y + 1, $z, $radius, 8, $this->pCrit());
        $this->shockwave($lv, $x, $y, $z, $radius, 3, 14, $this->pCrit());

        $this->startPersistent($lv, $x, $y, $z, $radius, PersistentVFXTask::TYPE_SLASH, $durationTicks, 2);
        $lv->addSound(new ClickSound($this->v($x, $y, $z)));
        $this->splash($lv, $x, $y + 1, $z, self::COL_SPLASH_GOLD);
    }

    public function spawnRubberDomain($player, $radius, $durationTicks) {
        $pos = $player->getPosition();
        $lv = $player->getLevel();
        $x = $pos->x; $y = $pos->y; $z = $pos->z;

        for ($i = 0; $i < 3; $i++) {
            $this->ring($lv, $x, $y + $i * 0.5, $z, $radius * (0.6 + $i * 0.15), 12, $this->pDust(self::COL_RED_R, self::COL_RED_G, self::COL_RED_B));
        }

        $this->startPersistent($lv, $x, $y, $z, $radius, PersistentVFXTask::TYPE_RUBBER, $durationTicks, 2);
        $lv->addSound(new FizzSound($this->v($x, $y, $z)));
        $this->splash($lv, $x, $y + 1, $z, self::COL_SPLASH_RED);
    }

    public function spawnQuakeDomain($player, $radius, $durationTicks) {
        $pos = $player->getPosition();
        $lv = $player->getLevel();
        $x = $pos->x; $y = $pos->y; $z = $pos->z;

        $this->shockwave($lv, $x, $y, $z, $radius, 5, 20, $this->pDust(self::COL_WHITE_R, self::COL_WHITE_G, self::COL_WHITE_B));
        $this->star($lv, $x, $y + 0.5, $z, $radius, 8, $this->pInstant());

        $this->startPersistent($lv, $x, $y, $z, $radius, PersistentVFXTask::TYPE_QUAKE, $durationTicks, 2);
        $lv->addSound(new AnvilFallSound($this->v($x, $y, $z)));
        $this->splash($lv, $x, $y + 1, $z, self::COL_SPLASH_WHITE);
    }

    public function spawnTornadoDomain($player, $radius, $durationTicks) {
        $pos = $player->getPosition();
        $lv = $player->getLevel();
        $x = $pos->x; $y = $pos->y; $z = $pos->z;

        $this->spiralExpanding($lv, $x, $y, $z, $radius * 1.5, 0.5, $radius * 0.6, 5, 24, $this->pFlame());

        $this->startPersistent($lv, $x, $y, $z, $radius, PersistentVFXTask::TYPE_TORNADO, $durationTicks, 2);
        $lv->addSound(new ExplodeSound($this->v($x, $y, $z)));
        $this->splash($lv, $x, $y + 2, $z, self::COL_SPLASH_RED);
    }

    // ==================== MISC ENHANCED VFX ====================

    public function spawnBarrierEffect($player) {
        $pos = $player->getPosition();
        $lv = $player->getLevel();
        $dir = $player->getDirectionVector();
        $x = $pos->x; $y = $pos->y; $z = $pos->z;
        $pX = -$dir->z; $pZ = $dir->x;
        $fx = $x + $dir->x * 1.8; $fz = $z + $dir->z * 1.8;

        for ($wy = 0; $wy < 5; $wy++) {
            for ($w = -4; $w <= 4; $w++) {
                $wx = $fx + $pX * $w * 0.4;
                $wz = $fz + $pZ * $w * 0.4;
                $lv->addParticle(new DustParticle($this->v($wx, $y + $wy * 0.7, $wz), self::COL_GOLD_R, self::COL_GOLD_G, self::COL_GOLD_B));
                if ($wy < 4 && abs($w) >= 3) $lv->addParticle(new EnchantParticle($this->v($wx, $y + $wy * 0.7, $wz)));
            }
        }
        $this->ring($lv, $fx, $y + 2.5, $fz, 2.2, 14, $this->pDust(self::COL_GOLD_R, self::COL_GOLD_G, self::COL_GOLD_B));
        $this->splash($lv, $fx, $y + 2, $fz, self::COL_SPLASH_YELLOW);
        $lv->addSound(new AnvilUseSound($this->v($x, $y, $z)));
    }

    public function spawnPhoenixFlames($center) {
        $pos = $center->getPosition();
        $lv = $center->getLevel();
        $x = $pos->x; $y = $pos->y; $z = $pos->z;

        for ($side = -1; $side <= 1; $side += 2) {
            for ($i = 0; $i < 10; $i++) {
                $d = $i * 0.35;
                $lv->addParticle(new DustParticle($this->v($x + $side * $d, $y + 1.5 + sin($i * 0.45) * 0.7, $z), self::COL_PHOENIX_R, self::COL_PHOENIX_G, self::COL_PHOENIX_B));
                $lv->addParticle(new MobSpellParticle($this->v($x + $side * $d, $y + 1.2, $z + 0.25), self::COL_CYAN_R, self::COL_CYAN_G, self::COL_CYAN_B));
            }
        }

        $this->spiralExpanding($lv, $x, $y, $z, 7, 0.5, 3, 5, 24, $this->pDust(self::COL_PHOENIX_R, self::COL_PHOENIX_G, self::COL_PHOENIX_B));
        for ($r = 0; $r < 5; $r++) {
            $this->ring($lv, $x, $y + $r * 0.7, $z, 2.5 - $r * 0.3, 14, $this->pMobSpell(self::COL_CYAN_R, self::COL_CYAN_G, self::COL_CYAN_B));
        }
        $this->scatter($lv, $x, $y + 1.5, $z, 3, 18, $this->pDust(self::COL_PHOENIX_R, self::COL_PHOENIX_G, self::COL_PHOENIX_B));
        $this->sphere($lv, $x, $y + 2.5, $z, 2.5, 5, 10, $this->pMobSpell(self::COL_CYAN_R, self::COL_CYAN_G, self::COL_CYAN_B));
        $this->splash($lv, $x, $y + 3, $z, self::COL_SPLASH_CYAN);
        $this->splash($lv, $x, $y + 1, $z, self::COL_SPLASH_BLUE);
        $this->boom($lv, $x, $y + 1, $z, 3.0);
        $lv->addSound(new BatSound($this->v($x, $y, $z)));
    }

    public function spawnSmokeCloud($center, $radius) {
        $pos = $center->getPosition();
        $lv = $center->getLevel();
        $x = $pos->x; $y = $pos->y; $z = $pos->z;

        for ($i = 0; $i < 5; $i++) {
            $this->ring($lv, $x, $y + $i * 0.8, $z, $radius * (1 - $i * 0.12), 10, $this->pSmoke());
        }
        $this->spiral($lv, $x, $y, $z, 5, $radius * 0.7, 3, 18, $this->pSmoke());
        $this->scatter($lv, $x, $y + 2.5, $z, $radius * 0.8, 14, $this->pSmoke());
        $this->splash($lv, $x, $y + 2, $z, self::COL_SPLASH_WHITE);
        $lv->addSound(new FizzSound($this->v($x, $y, $z)));
    }

    public function spawnSandstorm($center, $radius) {
        $pos = $center->getPosition();
        $lv = $center->getLevel();
        $x = $pos->x; $y = $pos->y; $z = $pos->z;

        $this->spiralExpanding($lv, $x, $y, $z, 8, 0.5, $radius, 5, 28, $this->pDust(self::COL_SAND_R, self::COL_SAND_G, self::COL_SAND_B));
        $this->spiral($lv, $x, $y, $z, 6, $radius * 0.7, 4, 20, $this->pSmoke());
        for ($i = 0; $i < 4; $i++) {
            $this->ring($lv, $x, $y + $i * 1.3, $z, $radius * (1 - $i * 0.18), 16, $this->pDust(self::COL_SAND_R, self::COL_SAND_G, self::COL_SAND_B));
        }
        $this->scatter($lv, $x, $y + 4, $z, $radius * 0.7, 14, $this->pDust(self::COL_SAND_R, self::COL_SAND_G, self::COL_SAND_B));
        $this->splash($lv, $x, $y + 3, $z, self::COL_SPLASH_SAND);
        $lv->addSound(new FizzSound($this->v($x, $y, $z)));
        $this->startPersistent($lv, $x, $y, $z, $radius, PersistentVFXTask::TYPE_SAND, 60, 3);
    }

    public function spawnEarthquake($center, $radius) {
        $pos = $center->getPosition();
        $lv = $center->getLevel();
        $x = $pos->x; $y = $pos->y; $z = $pos->z;

        $this->shockwave($lv, $x, $y, $z, $radius, 7, 26, $this->pDust(self::COL_WHITE_R, self::COL_WHITE_G, self::COL_WHITE_B));
        $this->shockwave($lv, $x, $y + 0.4, $z, $radius * 0.75, 5, 18, $this->pSmoke());
        $this->scatter($lv, $x, $y + 0.5, $z, $radius * 0.7, 16, $this->pLava());

        for ($i = 0; $i < 10; $i++) {
            $a = ($i / 10) * M_PI * 2;
            $this->line($lv, $x, $y + 1.5, $z, $x + cos($a) * $radius, $y + 1.5, $z + sin($a) * $radius, 8, $this->pInstant());
        }

        for ($i = 0; $i < 7; $i++) {
            $a = ($i / 7) * M_PI * 2;
            $this->boom($lv, $x + cos($a) * ($radius * 0.55), $y, $z + sin($a) * ($radius * 0.55), 3.0);
        }
        $this->boom($lv, $x, $y + 2, $z, 6.0);
        $this->splash($lv, $x, $y + 2, $z, self::COL_SPLASH_WHITE);
        $lv->addParticle(new HugeExplodeParticle($this->v($x, $y + 1, $z)));
        $lv->addSound(new AnvilFallSound($this->v($x, $y, $z)));
        $lv->addSound(new ExplodeSound($this->v($x, $y, $z)));
        $this->startPersistent($lv, $x, $y, $z, $radius, PersistentVFXTask::TYPE_QUAKE, 70, 3);
    }

    public function spawnWolfSlash($lv, $x, $y, $z, $dirX, $dirZ) {
        for ($i = -2; $i <= 2; $i++) {
            $offset = $i * 0.3;
            $pX = -$dirZ; $pZ = $dirX;
            $sx = $x + $pX * $offset; $sz = $z + $pZ * $offset;
            $this->line($lv, $sx, $y + 1, $sz, $sx + $dirX * 3.5, $y + 1, $sz + $dirZ * 3.5, 7, $this->pCrit());
        }
        $this->scatter($lv, $x, $y + 1, $z, 1.2, 10, $this->pSmoke());
        $this->splash($lv, $x + $dirX * 2, $y + 1, $z + $dirZ * 2, self::COL_SPLASH_WHITE);
        $lv->addSound(new PopSound($this->v($x, $y, $z)));
    }

    public function spawnLeopardSlash($lv, $x, $y, $z) {
        for ($i = 0; $i < 8; $i++) {
            $a = ($i / 8) * M_PI * 2;
            $this->line($lv, $x, $y + 1, $z, $x + cos($a) * 3.5, $y + 1, $z + sin($a) * 3.5, 7, $this->pCrit());
        }
        $this->scatter($lv, $x, $y + 1, $z, 1.5, 12, $this->pDust(self::COL_GOLD_R, self::COL_GOLD_G, self::COL_GOLD_B));
        $this->ring($lv, $x, $y + 1, $z, 2, 10, $this->pCrit());
        $this->splash($lv, $x, $y + 1, $z, self::COL_SPLASH_GOLD);
        $lv->addSound(new ClickSound($this->v($x, $y, $z)));
    }

    public function spawnAwakeningPassiveEffect($player) {
        $pos = $player->getPosition();
        $lv = $player->getLevel();
        $x = $pos->x; $y = $pos->y; $z = $pos->z;
        $t = microtime(true);

        for ($i = 0; $i < 5; $i++) {
            $a = $t * 2 + $i * M_PI * 2 / 5;
            $px = $x + cos($a) * 1.4;
            $py = $y + 0.6 + sin($t * 3 + $i) * 0.8;
            $pz = $z + sin($a) * 1.4;
            $lv->addParticle(new DustParticle($this->v($px, $py, $pz), self::COL_GOLD_R, self::COL_GOLD_G, self::COL_GOLD_B));
            $lv->addParticle(new EnchantParticle($this->v($px, $py + 0.3, $pz)));
        }
        if (mt_rand(0, 2) === 0) $lv->addParticle(new FlameParticle($this->v($x + mt_rand(-10, 10) / 10, $y + 0.2, $z + mt_rand(-10, 10) / 10)));
    }

    private function getFruitId(Player $player) {
        $dp = $this->plugin->getDevilPlugin();
        if ($dp === null) return null;
        try {
            $fm = $dp->getFruitManager();
            return $fm ? $fm->getPlayerFruitId($player) : null;
        } catch (\Exception $e) { return null; }
    }

    public function spawnRedHawkWindup(Player $player) {
        $pos = $player->getPosition();
        $lv  = $player->getLevel();
        $x = $pos->x; $y = $pos->y + 1.2; $z = $pos->z;

        $this->ring($lv, $x, $y, $z, 1.2, 12, $this->pDust(self::COL_RED_R, self::COL_RED_G, self::COL_RED_B));
        $this->ring($lv, $x, $y + 0.5, $z, 0.8, 8, $this->pDust(255, 80, 0));
        $this->scatter($lv, $x, $y, $z, 1.0, 8, $this->pFlame());
        $lv->addParticle(new SmokeParticle($this->v($x, $y + 0.3, $z)));
        $this->splash($lv, $x, $y + 1, $z, self::COL_SPLASH_ORANGE);
        $lv->addSound(new BlazeShootSound($this->v($x, $y, $z)));
    }

    public function spawnRedHawkLunge(Player $player) {
        $pos = $player->getPosition();
        $dir = $player->getDirectionVector();
        $lv  = $player->getLevel();
        $x = $pos->x; $y = $pos->y + 1.0; $z = $pos->z;

        for ($i = 1; $i <= 5; $i++) {
            $tx = $x + $dir->x * $i * 0.9;
            $tz = $z + $dir->z * $i * 0.9;
            $lv->addParticle(new FlameParticle($this->v($tx, $y + 0.2, $tz)));
            $lv->addParticle(new DustParticle($this->v($tx, $y, $tz), self::COL_RED_R, self::COL_RED_G, self::COL_RED_B));
            if ($i % 2 === 0) $lv->addParticle(new SmokeParticle($this->v($tx, $y + 0.5, $tz)));
        }

        $this->splash($lv, $x, $y, $z, self::COL_SPLASH_ORANGE);
    }

    public function spawnRedHawkImpact(Player $player) {
        $pos = $player->getPosition();
        $dir = $player->getDirectionVector();
        $lv  = $player->getLevel();
        $ix  = $pos->x + $dir->x * 3;
        $iy  = $pos->y + 1.2;
        $iz  = $pos->z + $dir->z * 3;

        $this->sphere($lv, $ix, $iy, $iz, 2.0, 4, 12, $this->pFlame());
        $this->ring($lv, $ix, $iy - 0.8, $iz, 2.5, 14, $this->pDust(self::COL_RED_R, self::COL_RED_G, self::COL_RED_B));
        $this->shockwave($lv, $ix, $iy - 1, $iz, 3.0, 3, 12, $this->pDust(255, 80, 0));
        $this->scatter($lv, $ix, $iy, $iz, 1.5, 8, $this->pLava());

        $this->splash($lv, $ix, $iy, $iz, self::COL_SPLASH_ORANGE);
        $this->splash($lv, $ix, $iy + 1.5, $iz, self::COL_SPLASH_RED);
        $lv->addParticle(new HugeExplodeParticle($this->v($ix, $iy, $iz)));
        $lv->addSound(new ExplodeSound($this->v($ix, $iy, $iz)));
        $lv->addSound(new BlazeShootSound($this->v($ix, $iy, $iz)));
    }

    public function spawnJetGatlingStart(Player $player) {
        $pos = $player->getPosition();
        $dir = $player->getDirectionVector();
        $lv  = $player->getLevel();
        $x = $pos->x; $y = $pos->y + 1.0; $z = $pos->z;

        $this->sphere($lv, $x, $y, $z, 1.8, 4, 12, $this->pSmoke());
        $this->ring($lv, $x, $y, $z, 2.2, 16, $this->pDust(self::COL_RED_R, self::COL_RED_G, self::COL_RED_B));
        $this->ring($lv, $x, $y + 0.5, $z, 1.5, 10, $this->pDust(255, 80, 0));
        $this->scatter($lv, $x, $y, $z, 1.4, 8, $this->pCrit());

        for ($i = 0; $i < 5; $i++) {
            $d = ($i + 1) * 0.7;
            $px = $x + $dir->x * $d;
            $pz = $z + $dir->z * $d;
            $this->ring($lv, $px, $y, $pz, 1.2 - $i * 0.15, 8, $this->pSmoke());
            $this->scatter($lv, $px, $y, $pz, 0.8, 4, $this->pCrit());
        }

        $this->splash($lv, $x, $y + 1, $z, self::COL_SPLASH_RED);
        $this->splash($lv, $x, $y + 0.2, $z, self::COL_SPLASH_BLACK);
        $lv->addSound(new AnvilFallSound($this->v($x, $y, $z)));
        $lv->addSound(new ExplodeSound($this->v($x, $y, $z)));
    }

    public function spawnJetGatlingPulse(Player $player, $pulse) {
        $pos = $player->getPosition();
        $dir = $player->getDirectionVector();
        $lv  = $player->getLevel();
        $x = $pos->x; $y = $pos->y + 1.0; $z = $pos->z;

        $side  = ($pulse % 2 === 0) ? -0.4 : 0.4;
        $perpX = -$dir->z * $side;
        $perpZ =  $dir->x * $side;

        $px = $x + $dir->x * 1.8 + $perpX;
        $pz = $z + $dir->z * 1.8 + $perpZ;

        $this->sphere($lv, $px, $y, $pz, 0.9, 3, 6, $this->pSmoke());
        $this->scatter($lv, $px, $y, $pz, 0.7, 4, $this->pCrit());
        $lv->addParticle(new DustParticle($this->v($px, $y, $pz), self::COL_RED_R, self::COL_RED_G, self::COL_RED_B));

        if ($pulse % 3 === 0) {
            $ex = $x + $dir->x * 3.2 + $perpX;
            $ez = $z + $dir->z * 3.2 + $perpZ;
            $this->ring($lv, $ex, $y, $ez, 1.0, 8, $this->pSmoke());
            $this->scatter($lv, $ex, $y, $ez, 0.6, 4, $this->pCrit());
            $lv->addParticle(new DustParticle($this->v($ex, $y + 0.2, $ez), self::COL_HAKI_R, self::COL_HAKI_G, self::COL_HAKI_B));
        }

        if ($pulse % 5 === 0) {
            $this->splash($lv, $px, $y, $pz, self::COL_SPLASH_RED);
        }
    }

    public function spawnJetGatlingImpact($target) {
        $pos = $target->getPosition();
        $lv  = $target->getLevel();
        $x = $pos->x; $y = $pos->y + 1.0; $z = $pos->z;

        $this->sphere($lv, $x, $y, $z, 0.8, 2, 5, $this->pSmoke());
        $this->scatter($lv, $x, $y, $z, 0.6, 3, $this->pCrit());
        $lv->addParticle(new DustParticle($this->v($x, $y + 0.3, $z), self::COL_RED_R, self::COL_RED_G, self::COL_RED_B));
        $lv->addParticle(new DustParticle($this->v($x, $y + 0.3, $z), self::COL_HAKI_R, self::COL_HAKI_G, self::COL_HAKI_B));
    }

    public function spawnGearFourthDomain(Player $player, $radius, $durationTicks) {
        $pos = $player->getPosition();
        $lv  = $player->getLevel();
        $x = $pos->x; $y = $pos->y; $z = $pos->z;

        $this->ring($lv, $x, $y + 0.2, $z, $radius * 0.7, 14, $this->pDust(self::COL_RED_R, self::COL_RED_G, self::COL_RED_B));
        $this->ring($lv, $x, $y + 0.8, $z, $radius * 0.5, 10, $this->pDust(255, 80, 0));
        $this->shockwave($lv, $x, $y, $z, $radius, 4, 18, $this->pCrit());

        $this->startPersistent($lv, $x, $y, $z, $radius, PersistentVFXTask::TYPE_GEAR4, $durationTicks, 2);
        $lv->addSound(new AnvilFallSound($this->v($x, $y, $z)));
        $lv->addSound(new BlazeShootSound($this->v($x, $y, $z)));
        $this->splash($lv, $x, $y + 1.5, $z, self::COL_SPLASH_RED);
    }
}