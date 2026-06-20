<?php

namespace OnePiece\Fruits;

use pocketmine\scheduler\PluginTask;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\level\particle\FlameParticle;
use pocketmine\level\particle\LavaParticle;
use pocketmine\level\particle\SmokeParticle;
use pocketmine\level\particle\EnchantParticle;
use pocketmine\level\particle\CriticalParticle;
use pocketmine\level\particle\InstantEnchantParticle;
use pocketmine\level\particle\RedstoneParticle;
use pocketmine\level\particle\DustParticle;
use pocketmine\level\particle\MobSpellParticle;
use pocketmine\level\particle\LavaDripParticle;
use pocketmine\level\particle\ExplodeParticle;
use pocketmine\level\particle\HugeExplodeParticle;
use pocketmine\network\protocol\LevelEventPacket;

class PersistentVFXTask extends PluginTask {

    private $plugin;
    private $level;
    private $cx, $cy, $cz, $radius;
    private $phase = 0.0;
    private $type;
    private $totalTicks, $ticksRan = 0;
    private $nearbyPlayers = [];

    const TYPE_ROOM = "room";
    const TYPE_QUAKE = "quake";
    const TYPE_STORM = "storm";
    const TYPE_FIRE_DOME = "fire_dome";
    const TYPE_TORNADO = "tornado";
    const TYPE_PHOENIX = "phoenix";
    const TYPE_SAND = "sand";
    const TYPE_SLASH = "slash";
    const TYPE_RUBBER = "rubber";
    const TYPE_ICE = "ice";
    const TYPE_DARKNESS = "darkness";
    const TYPE_LIGHT = "light";
    const TYPE_SOUND = "sound";
    const TYPE_GEAR4 = "gear4";

    const VIEW_RANGE = 48;

    const COL_CYAN_R = 0; const COL_CYAN_G = 200; const COL_CYAN_B = 255;
    const COL_BLUE_R = 0; const COL_BLUE_G = 100; const COL_BLUE_B = 255;
    const COL_ELEC_R = 180; const COL_ELEC_G = 220; const COL_ELEC_B = 255;
    const COL_ELEC2_R = 255; const COL_ELEC2_G = 255; const COL_ELEC2_B = 100;
    const COL_ELEC_CORE_R = 255; const COL_ELEC_CORE_G = 255; const COL_ELEC_CORE_B = 255;
    const COL_SAND_R = 220; const COL_SAND_G = 190; const COL_SAND_B = 120;
    const COL_GOLD_R = 255; const COL_GOLD_G = 200; const COL_GOLD_B = 0;
    const COL_RED_R = 200; const COL_RED_G = 0; const COL_RED_B = 0;
    const COL_DARK_R = 100; const COL_DARK_G = 0; const COL_DARK_B = 0;
    const COL_WHITE_R = 255; const COL_WHITE_G = 255; const COL_WHITE_B = 255;
    const COL_PHOENIX_R = 0; const COL_PHOENIX_G = 180; const COL_PHOENIX_B = 255;
    const COL_FIRE_R = 255; const COL_FIRE_G = 100; const COL_FIRE_B = 0;
    const COL_FIRE_CORE_R = 255; const COL_FIRE_CORE_G = 220; const COL_FIRE_CORE_B = 100;
    const COL_PURPLE_R = 150; const COL_PURPLE_G = 0; const COL_PURPLE_B = 200;
    const COL_ICE_R = 150; const COL_ICE_G = 220; const COL_ICE_B = 255;
    const COL_DARK_PURPLE_R = 40; const COL_DARK_PURPLE_G = 0; const COL_DARK_PURPLE_B = 60;

    const EV_SPLASH = 2002;
    const COL_SPLASH_RED = 16733525;
    const COL_SPLASH_ORANGE = 16753920;
    const COL_SPLASH_YELLOW = 16766720;
    const COL_SPLASH_CYAN = 65535;
    const COL_SPLASH_BLUE = 3694022;
    const COL_SPLASH_GOLD = 16766464;
    const COL_SPLASH_WHITE = 16777215;
    const COL_SPLASH_SAND = 14787072;
    const COL_SPLASH_DARK_RED = 11141120;
    const COL_SPLASH_PURPLE = 8339378;
    const COL_SPLASH_BLACK = 1118481;

    public function __construct(Main $plugin, Level $level, $cx, $cy, $cz, $radius, $type, $totalDrawCalls) {
        parent::__construct($plugin);
        $this->plugin = $plugin;
        $this->level = $level;
        $this->cx = (float)$cx;
        $this->cy = (float)$cy;
        $this->cz = (float)$cz;
        $this->radius = (float)$radius;
        $this->type = $type;
        $this->totalTicks = (int)$totalDrawCalls;
    }

    public function onRun($currentTick) {
        $this->ticksRan++;

        if ($this->ticksRan > $this->totalTicks) {
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
            return;
        }

        if ($this->plugin->getServer()->getLevelByName($this->level->getName()) === null) {
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
            return;
        }

        $this->nearbyPlayers = $this->getNearbyPlayers();

        if (empty($this->nearbyPlayers)) {
            return;
        }

        $this->phase += 0.18;

        switch ($this->type) {
            case self::TYPE_ROOM: $this->drawRoom(); break;
            case self::TYPE_QUAKE: $this->drawQuake(); break;
            case self::TYPE_STORM: $this->drawStorm(); break;
            case self::TYPE_FIRE_DOME: $this->drawFireDome(); break;
            case self::TYPE_TORNADO: $this->drawTornado(); break;
            case self::TYPE_PHOENIX: $this->drawPhoenix(); break;
            case self::TYPE_SAND: $this->drawSand(); break;
            case self::TYPE_SLASH: $this->drawSlash(); break;
            case self::TYPE_RUBBER: $this->drawRubber(); break;
            case self::TYPE_ICE: $this->drawIce(); break;
            case self::TYPE_DARKNESS: $this->drawDarkness(); break;
            case self::TYPE_LIGHT: $this->drawLight(); break;
            case self::TYPE_SOUND: $this->drawSound(); break;
            case self::TYPE_GEAR4: $this->drawGear4(); break;
        }
    }

    private function getNearbyPlayers() {
        $players = [];
        $cx = $this->cx;
        $cz = $this->cz;

        foreach ($this->level->getPlayers() as $p) {
            $dx = abs($p->x - $cx);
            $dz = abs($p->z - $cz);
            if ($dx <= self::VIEW_RANGE && $dz <= self::VIEW_RANGE) {
                $players[] = $p;
            }
        }
        return $players;
    }

    private function sendParticle($particle) {
        $pk = $particle->encode();
        foreach ($this->nearbyPlayers as $pl) {
            $pl->dataPacket($pk);
        }
    }

    private function sendSplash($x, $y, $z, $col) {
        $pk = new LevelEventPacket();
        $pk->evid = self::EV_SPLASH;
        $pk->data = $col;
        $pk->x = (float)$x;
        $pk->y = (float)$y;
        $pk->z = (float)$z;
        foreach ($this->nearbyPlayers as $pl) {
            $pl->dataPacket($pk);
        }
    }

    private function v($x, $y, $z) {
        return new Vector3($x, $y, $z);
    }

    private function drawRoom() {
        $t = $this->phase;
        $r = $this->radius;
        $cx = $this->cx; $cy = $this->cy; $cz = $this->cz;

        for ($ring = 0; $ring < 3; $ring++) {
            $phi = M_PI * 0.25 + $ring * M_PI * 0.25;
            $rr = cos($phi) * $r;
            $oy = $cy + sin($phi) * $r * (($ring % 2 == 0) ? 1 : -1);
            for ($i = 0; $i < 12; $i++) {
                $a = ($i / 12) * M_PI * 2 + $t * (0.3 + $ring * 0.1);
                $this->sendParticle(new MobSpellParticle($this->v($cx + cos($a) * $rr, $oy, $cz + sin($a) * $rr), self::COL_CYAN_R, self::COL_CYAN_G, self::COL_CYAN_B));
            }
        }

        for ($i = 0; $i < 12; $i++) {
            $phi = ($i / 12) * M_PI * 2;
            $this->sendParticle(new DustParticle(
                $this->v($cx + cos($t * 0.5) * cos($phi) * $r, $cy + sin($phi) * $r, $cz + sin($t * 0.5) * cos($phi) * $r),
                self::COL_CYAN_R, self::COL_CYAN_G, self::COL_CYAN_B
            ));
        }

        for ($i = 0; $i < 10; $i++) {
            $a = ($i / 10) * M_PI * 2 + $t * 1.2;
            $orbR = $r * 0.6;
            $this->sendParticle(new EnchantParticle($this->v($cx + cos($a) * $orbR, $cy + sin($t * 3 + $i) * 0.3, $cz + sin($a) * $orbR)));
        }

        for ($i = 0; $i < 4; $i++) {
            $a = ($i / 4) * M_PI * 2 + $t * 0.2;
            $this->sendParticle(new DustParticle(
                $this->v($cx + cos($a) * $r * 0.3, $cy, $cz + sin($a) * $r * 0.3),
                self::COL_BLUE_R, self::COL_BLUE_G, self::COL_BLUE_B
            ));
        }

        if ($this->ticksRan % 20 === 0) {
            $this->sendSplash($cx, $cy + $r * 0.5, $cz, self::COL_SPLASH_CYAN);
        }
    }

    private function drawQuake() {
        $t = $this->phase;
        $r = $this->radius;
        $cx = $this->cx; $cy = $this->cy; $cz = $this->cz;
        $pulse = sin($t * 1.5) * 0.5 + 0.5;
        $shake = sin($t * 8) * 0.1;

        $outerR = $r * (0.75 + $pulse * 0.25);
        for ($i = 0; $i < 16; $i++) {
            $a = ($i / 16) * M_PI * 2 + $t * 0.1;
            $this->sendParticle(new DustParticle($this->v($cx + cos($a) * $outerR + $shake, $cy, $cz + sin($a) * $outerR), self::COL_WHITE_R, self::COL_WHITE_G, self::COL_WHITE_B));
        }

        for ($c = 0; $c < 6; $c++) {
            $a = ($c / 6) * M_PI * 2 + $t * 0.08;
            $d1 = $r * 0.25;
            $d2 = $r * (0.7 + $pulse * 0.2);
            for ($s = 0; $s < 4; $s++) {
                $frac = $s / 3;
                $dist = $d1 + ($d2 - $d1) * $frac;
                $this->sendParticle(new InstantEnchantParticle($this->v($cx + cos($a) * $dist, $cy + $shake, $cz + sin($a) * $dist)));
            }
        }

        for ($i = 0; $i < 5; $i++) {
            $a = $t * 0.9 + $i * M_PI * 2 / 5;
            $dr = $r * (0.35 + ($i % 2) * 0.2);
            $oy = sin($t * 2.5 + $i) * 0.5;
            $this->sendParticle(new DustParticle($this->v($cx + cos($a) * $dr, $cy + 0.7 + $oy, $cz + sin($a) * $dr), 200, 40, 0));
        }

        for ($i = 0; $i < 3; $i++) {
            $a = $t * 0.5 + $i * M_PI * 2 / 3;
            $this->sendParticle(new ExplodeParticle($this->v($cx + cos($a) * $r * 0.5, $cy + 0.2, $cz + sin($a) * $r * 0.5)));
        }

        if ($this->ticksRan % 15 === 0) {
            $this->sendSplash($cx, $cy + 0.5, $cz, self::COL_SPLASH_WHITE);
        }
    }

    private function drawStorm() {
        $t = $this->phase;
        $r = $this->radius;
        $cx = $this->cx; $cy = $this->cy; $cz = $this->cz;
        $cloudY = $cy + $r * 0.9;

        for ($layer = 0; $layer < 2; $layer++) {
            $layerR = $r * (0.85 + $layer * 0.12);
            for ($i = 0; $i < 10; $i++) {
                $a = ($i / 10) * M_PI * 2 + $t * (0.5 + $layer * 0.2);
                $ry = sin($t * 2.5 + $i * 0.9 + $layer) * 0.4;
                $this->sendParticle(new SmokeParticle($this->v($cx + cos($a) * $layerR, $cloudY + $ry - $layer * 0.5, $cz + sin($a) * $layerR), 0));
            }
        }

        for ($i = 0; $i < 8; $i++) {
            $a = ($i / 8) * M_PI * 2 + $t * 3;
            $orbR = $r * 0.5;
            $this->sendParticle(new DustParticle(
                $this->v($cx + cos($a) * $orbR, $cloudY - sin($t * 4 + $i) * 0.5, $cz + sin($a) * $orbR),
                self::COL_ELEC_R, self::COL_ELEC_G, self::COL_ELEC_B
            ));
        }

        if ($this->ticksRan % 5 === 0) {
            $ba = mt_rand(0, 628) / 100.0;
            $bd = $r * mt_rand(2, 9) / 10.0;
            $bx = $cx + cos($ba) * $bd;
            $bz = $cz + sin($ba) * $bd;
            $midY = $cy + ($cloudY - $cy) * 0.5;

            $this->sendParticle(new DustParticle($this->v($bx, $cloudY, $bz), self::COL_ELEC2_R, self::COL_ELEC2_G, self::COL_ELEC2_B));
            $this->sendParticle(new DustParticle($this->v($bx + sin($t) * 0.4, $midY, $bz + cos($t) * 0.4), self::COL_ELEC_CORE_R, self::COL_ELEC_CORE_G, self::COL_ELEC_CORE_B));
            $this->sendParticle(new DustParticle($this->v($bx + sin($t * 1.5) * 0.2, $midY * 0.6, $bz + cos($t * 1.5) * 0.2), self::COL_ELEC2_R, self::COL_ELEC2_G, self::COL_ELEC2_B));
            $this->sendParticle(new DustParticle($this->v($bx, $cy + 0.2, $bz), self::COL_ELEC_R, self::COL_ELEC_G, self::COL_ELEC_B));
            $this->sendParticle(new ExplodeParticle($this->v($bx, $cy, $bz)));
        }

        for ($i = 0; $i < 8; $i++) {
            $a = ($i / 8) * M_PI * 2 - $t * 0.6;
            $this->sendParticle(new DustParticle(
                $this->v($cx + cos($a) * $r * 0.75, $cy + 0.15, $cz + sin($a) * $r * 0.75),
                self::COL_ELEC_R, self::COL_ELEC_G, self::COL_ELEC_B
            ));
        }

        if ($this->ticksRan % 22 === 0) {
            $this->sendSplash($cx, $cloudY, $cz, self::COL_SPLASH_YELLOW);
        }
    }

    private function drawFireDome() {
        $t = $this->phase;
        $r = $this->radius;
        $cx = $this->cx; $cy = $this->cy; $cz = $this->cz;

        for ($p = 0; $p < 4; $p++) {
            $a = ($p / 4) * M_PI * 2 + $t * 0.4;
            $px = $cx + cos($a) * $r * 0.7;
            $pz = $cz + sin($a) * $r * 0.7;
            $sway = sin($t * 3.5 + $p) * 0.15;
            $this->sendParticle(new DustParticle($this->v($px + $sway, $cy, $pz + $sway), 200, 40, 0));
            $this->sendParticle(new FlameParticle($this->v($px + $sway, $cy + 1.5, $pz + $sway)));
        }

        for ($i = 0; $i < 5; $i++) {
            $prog = $i / 5.0;
            $a = $prog * M_PI * 5 + $t * 1.8;
            $rr = $r * 0.55 * (1 - $prog * 0.35);
            $this->sendParticle(new FlameParticle($this->v($cx + cos($a) * $rr, $cy + $prog * $r * 0.85, $cz + sin($a) * $rr)));
        }

        for ($i = 0; $i < 6; $i++) {
            $a = ($i / 6) * M_PI * 2 + $t * 0.15;
            $this->sendParticle(new DustParticle($this->v($cx + cos($a) * $r * 0.9, $cy + 0.1, $cz + sin($a) * $r * 0.9), 200, 40, 0));
        }

        if ($this->ticksRan % 20 === 0) {
            $this->sendSplash($cx, $cy + 1.5, $cz, self::COL_SPLASH_ORANGE);
        }
    }

    private function drawTornado() {
        $t = $this->phase;
        $r = $this->radius * 0.55;
        $cx = $this->cx; $cy = $this->cy; $cz = $this->cz;
        $h = $this->radius * 1.4;

        for ($strand = 0; $strand < 2; $strand++) {
            for ($i = 0; $i < 14; $i++) {
                $frac = $i / 14.0;
                $a = $frac * M_PI * 5 + $t * 2.2 + $strand * M_PI;
                $rr = $r * (1 - $frac * 0.6);
                if ($i < 5) {
                    $this->sendParticle(new DustParticle($this->v($cx + cos($a) * $rr, $cy + $frac * $h, $cz + sin($a) * $rr), 200, 40, 0));
                } else {
                    $this->sendParticle(new FlameParticle($this->v($cx + cos($a) * $rr, $cy + $frac * $h, $cz + sin($a) * $rr)));
                }
            }
        }

        for ($i = 0; $i < 8; $i++) {
            $a = ($i / 8) * M_PI * 2 + $t * 1.5;
            $dr = $this->radius * (0.55 + sin($t * 2.5 + $i) * 0.15);
            $dy = abs(sin($t * 3.5 + $i)) * 0.7;
            $this->sendParticle(new FlameParticle($this->v($cx + cos($a) * $dr, $cy + $dy, $cz + sin($a) * $dr)));
        }

        $topY = $cy + $h;
        for ($i = 0; $i < 6; $i++) {
            $a = ($i / 6) * M_PI * 2 - $t * 2.8;
            $this->sendParticle(new FlameParticle($this->v($cx + cos($a) * $r * 0.35, $topY + sin($t * 1.5 + $i) * 0.25, $cz + sin($a) * $r * 0.35)));
        }

        if ($this->ticksRan % 16 === 0) {
            $this->sendSplash($cx, $cy + 1.5, $cz, self::COL_SPLASH_DARK_RED);
        }
    }

    private function drawPhoenix() {
        $t = $this->phase;
        $r = $this->radius;
        $cx = $this->cx; $cy = $this->cy + 1.2; $cz = $this->cz;

        for ($wing = 0; $wing < 2; $wing++) {
            $wingBase = $wing * M_PI + $t * 0.5;
            for ($f = 0; $f < 9; $f++) {
                $frac = $f / 8.0;
                $arcA = $wingBase + ($frac - 0.5) * M_PI * 0.7;
                $wr = $r * 0.8;
                $wy = $cy + sin($frac * M_PI) * $r * 0.45;
                if ($f % 2 === 0) {
                    $this->sendParticle(new MobSpellParticle($this->v($cx + cos($arcA) * $wr, $wy, $cz + sin($arcA) * $wr), self::COL_CYAN_R, self::COL_CYAN_G, self::COL_CYAN_B));
                } else {
                    $this->sendParticle(new DustParticle($this->v($cx + cos($arcA) * $wr, $wy, $cz + sin($arcA) * $wr), self::COL_PHOENIX_R, self::COL_PHOENIX_G, self::COL_PHOENIX_B));
                }
            }
        }

        $orbR = 0.45 + sin($t * 2.5) * 0.18;
        for ($i = 0; $i < 6; $i++) {
            $a = ($i / 6) * M_PI * 2 + $t * 3.5;
            $this->sendParticle(new MobSpellParticle($this->v($cx + cos($a) * $orbR, $cy + cos($t * 2.5 + $i) * 0.3, $cz + sin($a) * $orbR), self::COL_PHOENIX_R, self::COL_PHOENIX_G, self::COL_PHOENIX_B));
        }

        for ($i = 0; $i < 4; $i++) {
            $a = $t * 0.9 + $i * M_PI * 2 / 4;
            $fy = $cy - 0.6 - $i * 0.25;
            if ($fy > $this->cy - 2.5) {
                $this->sendParticle(new DustParticle($this->v($cx + cos($a) * $r * 0.35, $fy, $cz + sin($a) * $r * 0.35), self::COL_PHOENIX_R, self::COL_PHOENIX_G, self::COL_PHOENIX_B));
            }
        }

        if ($this->ticksRan % 22 === 0) {
            $this->sendSplash($cx, $cy, $cz, self::COL_SPLASH_BLUE);
        }
    }

    private function drawSand() {
        $t = $this->phase;
        $r = $this->radius * 0.65;
        $cx = $this->cx; $cy = $this->cy; $cz = $this->cz;
        $h = $this->radius * 1.5;

        for ($strand = 0; $strand < 3; $strand++) {
            for ($i = 0; $i < 10; $i++) {
                $frac = $i / 10.0;
                $a = $frac * M_PI * 3 + $t * 2.2 + $strand * M_PI * 2 / 3;
                $rr = $r * (1 - $frac * 0.55);
                $this->sendParticle(new DustParticle($this->v($cx + cos($a) * $rr, $cy + $frac * $h, $cz + sin($a) * $rr), self::COL_SAND_R, self::COL_SAND_G, self::COL_SAND_B));
            }
        }

        for ($i = 0; $i < 10; $i++) {
            $a = ($i / 10) * M_PI * 2 + $t * 1.8;
            $dr = $r * (1.05 + sin($t * 2.5 + $i) * 0.18);
            $this->sendParticle(new SmokeParticle($this->v($cx + cos($a) * $dr, $cy + 0.15, $cz + sin($a) * $dr)));
        }

        for ($i = 0; $i < 8; $i++) {
            $a = ($i / 8) * M_PI * 2 + $t * 0.4;
            $this->sendParticle(new DustParticle($this->v($cx + cos($a) * $this->radius * 0.9, $cy + 0.6, $cz + sin($a) * $this->radius * 0.9), self::COL_SAND_R, self::COL_SAND_G, self::COL_SAND_B));
        }

        if ($this->ticksRan % 18 === 0) {
            $this->sendSplash($cx, $cy + 2.5, $cz, self::COL_SPLASH_SAND);
        }
    }

    private function drawSlash() {
        $t = $this->phase;
        $r = $this->radius * 0.75;
        $cx = $this->cx; $cy = $this->cy + 1.0; $cz = $this->cz;

        for ($arc = 0; $arc < 4; $arc++) {
            $baseA = ($arc / 4) * M_PI * 2 + $t * 3.2;
            for ($s = 0; $s < 4; $s++) {
                $a = $baseA + ($s - 1.5) * 0.18;
                $oy = sin($t * 4.5 + $arc) * 0.4;
                $this->sendParticle(new CriticalParticle($this->v($cx + cos($a) * $r, $cy + $oy, $cz + sin($a) * $r)));
            }
            $trailA = $baseA - 0.6;
            $this->sendParticle(new DustParticle($this->v($cx + cos($trailA) * $r * 0.92, $cy, $cz + sin($trailA) * $r * 0.92), self::COL_GOLD_R, self::COL_GOLD_G, self::COL_GOLD_B));
        }

        for ($i = 0; $i < 8; $i++) {
            $a = ($i / 8) * M_PI * 2 + $t * 4.5;
            $this->sendParticle(new EnchantParticle($this->v($cx + cos($a) * $r * 0.45, $cy - 0.35, $cz + sin($a) * $r * 0.45)));
        }

        if ($this->ticksRan % 14 === 0) {
            $this->sendParticle(new ExplodeParticle($this->v($cx, $cy, $cz)));
        }
        if ($this->ticksRan % 28 === 0) {
            $this->sendSplash($cx, $cy, $cz, self::COL_SPLASH_GOLD);
        }
    }

    private function drawRubber() {
        $t = $this->phase;
        $r = $this->radius;
        $cx = $this->cx; $cy = $this->cy + 0.6; $cz = $this->cz;

        for ($ring = 0; $ring < 4; $ring++) {
            $ringPhase = $t * 1.8 + $ring * M_PI / 2;
            $ringR = $r * (0.25 + (sin($ringPhase) * 0.5 + 0.5) * 0.75);
            for ($i = 0; $i < 10; $i++) {
                $a = ($i / 10) * M_PI * 2;
                $this->sendParticle(new DustParticle(
                    $this->v($cx + cos($a) * $ringR, $cy + sin($t * 1.8 + $ring) * 0.3, $cz + sin($a) * $ringR),
                    self::COL_RED_R, self::COL_RED_G, self::COL_RED_B
                ));
            }
        }

        $vR = $r * (0.45 + (sin($t * 1.4) * 0.5 + 0.5) * 0.5);
        for ($i = 0; $i < 8; $i++) {
            $a = ($i / 8) * M_PI * 2;
            $this->sendParticle(new DustParticle($this->v($cx + cos($a) * $vR * 0.45, $cy + sin($a) * $vR, $cz), self::COL_RED_R, self::COL_RED_G, self::COL_RED_B));
        }

        for ($i = 0; $i < 4; $i++) {
            $a = $t * 2 + $i * M_PI / 2;
            $this->sendParticle(new SmokeParticle($this->v($cx + cos($a) * $r * 0.3, $cy + 0.2, $cz + sin($a) * $r * 0.3)));
        }

        if ($this->ticksRan % 18 === 0) {
            $this->sendSplash($cx, $cy, $cz, self::COL_SPLASH_RED);
        }
    }

    private function drawIce() {
        $t = $this->phase;
        $r = $this->radius;
        $cx = $this->cx; $cy = $this->cy; $cz = $this->cz;

        $breathe = 0.92 + sin($t * 0.8) * 0.08;
        for ($i = 0; $i < 8; $i++) {
            $a = ($i / 8) * M_PI * 2 + $t * 0.25;
            $this->sendParticle(new DustParticle(
                $this->v($cx + cos($a) * $r * $breathe, $cy + 0.1, $cz + sin($a) * $r * $breathe),
                self::COL_ICE_R, self::COL_ICE_G, self::COL_ICE_B
            ));
        }

        for ($i = 0; $i < 4; $i++) {
            $a = ($i / 4) * M_PI * 2 - $t * 0.5;
            $spike = sin($t * 2.5 + $i * 1.57) * 0.4 + 0.6;
            $this->sendParticle(new DustParticle(
                $this->v($cx + cos($a) * $r * 0.55, $cy + $spike, $cz + sin($a) * $r * 0.55),
                self::COL_WHITE_R, self::COL_WHITE_G, self::COL_WHITE_B
            ));
            $this->sendParticle(new DustParticle(
                $this->v($cx + cos($a) * $r * 0.55, $cy + $spike + 0.5, $cz + sin($a) * $r * 0.55),
                self::COL_ICE_R, self::COL_ICE_G, self::COL_ICE_B
            ));
        }

        if ($this->ticksRan % 5 === 0) {
            $a = $t * 1.3 + ($this->ticksRan * 0.9);
            $drift = $r * (0.2 + (($this->ticksRan % 7) / 7.0) * 0.6);
            $this->sendParticle(new DustParticle(
                $this->v($cx + cos($a) * $drift, $cy + sin($t * 1.8) * 0.5 + 0.8, $cz + sin($a) * $drift),
                self::COL_WHITE_R, self::COL_WHITE_G, self::COL_WHITE_B
            ));
        }

        if ($this->ticksRan % 18 === 0) {
            $this->sendSplash($cx, $cy + 0.5, $cz, self::COL_SPLASH_CYAN);
        }
    }

    private function drawDarkness() {
        $t = $this->phase;
        $r = $this->radius;
        $cx = $this->cx; $cy = $this->cy; $cz = $this->cz;

        for ($i = 0; $i < 14; $i++) {
            $a = ($i / 14) * M_PI * 2 + $t * 0.4;
            $pull = sin($t * 2 + $i) * 0.15;
            $this->sendParticle(new DustParticle(
                $this->v($cx + cos($a) * ($r * 0.85 - $pull), $cy + 0.3, $cz + sin($a) * ($r * 0.85 - $pull)),
                self::COL_DARK_PURPLE_R, self::COL_DARK_PURPLE_G, self::COL_DARK_PURPLE_B
            ));
        }

        for ($i = 0; $i < 8; $i++) {
            $a = $t * 1.2 + $i * M_PI / 4;
            $dr = $r * (0.3 + $i * 0.08);
            $this->sendParticle(new DustParticle(
                $this->v($cx + cos($a) * $dr, $cy + sin($t * 3 + $i) * 0.4 + 0.5, $cz + sin($a) * $dr),
                self::COL_PURPLE_R, self::COL_PURPLE_G, self::COL_PURPLE_B
            ));
        }

        if ($this->ticksRan % 16 === 0) {
            $this->sendSplash($cx, $cy + 0.5, $cz, self::COL_SPLASH_PURPLE);
        }
    }


    private function drawSound() {
        $t  = $this->phase;
        $r  = $this->radius;
        $cx = $this->cx; $cy = $this->cy + 0.5; $cz = $this->cz;

        // Rotating pink ribbon arcs — 2 wide sweeping curves at different heights
        for ($arc = 0; $arc < 2; $arc++) {
            $arcBase = $t * 0.6 + $arc * M_PI;
            $pts = 10;
            for ($p = 0; $p < $pts; $p++) {
                $prog = $p / ($pts - 1);
                $a    = $arcBase + ($prog - 0.5) * M_PI * 1.2;
                $wy   = $cy + 0.4 + sin($prog * M_PI) * $r * 0.5;
                $wr   = $r * (0.5 + sin($prog * M_PI) * 0.4);
                $this->sendParticle(new DustParticle(
                    $this->v($cx + cos($a) * $wr, $wy, $cz + sin($a) * $wr),
                    255, 50, 180
                ));
            }
        }

        // Floating note event particles at random positions around ring
        if ($this->ticksRan % 4 === 0) {
            $noteA = mt_rand(0, 628) / 100.0;
            $noteR = $r * (0.4 + mt_rand(0, 5) / 10.0);
            $pk = new \pocketmine\network\protocol\LevelEventPacket();
            $pk->evid = 2000;
            $pk->data = (int)(($this->ticksRan * 7) % 25);
            $pk->x = (float)($cx + cos($noteA) * $noteR);
            $pk->y = (float)($cy + 1.2 + mt_rand(0, 8) / 10.0);
            $pk->z = (float)($cz + sin($noteA) * $noteR);
            foreach ($this->getNearbyPlayers() as $pl) { $pl->dataPacket($pk); }
        }

        // Purple/gold orbiting sparkles at mid-radius
        $orbR = $r * 0.55;
        for ($i = 0; $i < 3; $i++) {
            $a = $t * 2.5 + ($i / 3) * M_PI * 2;
            $oy = $cy + sin($t * 1.8 + $i * 1.2) * 0.4;
            $this->sendParticle(new InstantEnchantParticle($this->v($cx + cos($a) * $orbR, $oy, $cz + sin($a) * $orbR)));
            if ($i % 2 === 0) {
                $this->sendParticle(new DustParticle(
                    $this->v($cx + cos($a + 0.4) * $orbR * 0.8, $oy + 0.2, $cz + sin($a + 0.4) * $orbR * 0.8),
                    160, 0, 255
                ));
            }
        }

        // Outer gold ring pulse every 20 ticks
        if ($this->ticksRan % 20 === 0) {
            $pts2 = 14;
            for ($i = 0; $i < $pts2; $i++) {
                $a = ($i / $pts2) * M_PI * 2;
                $this->sendParticle(new DustParticle(
                    $this->v($cx + cos($a) * $r, $cy, $cz + sin($a) * $r),
                    255, 200, 0
                ));
            }
        }

        // Splash every 30 ticks
        if ($this->ticksRan % 30 === 0) {
            $this->sendSplash($cx, $cy + 0.5, $cz, 16714930);
        }
    }
    private function drawLight() {
        $t = $this->phase;
        $r = $this->radius;
        $cx = $this->cx; $cy = $this->cy + 1; $cz = $this->cz;

        for ($beam = 0; $beam < 6; $beam++) {
            $a = ($beam / 6) * M_PI * 2 + $t * 0.8;
            for ($h = 0; $h < 4; $h++) {
                $this->sendParticle(new DustParticle(
                    $this->v($cx + cos($a) * $r * 0.1 * $h, $cy + $h * 0.8, $cz + sin($a) * $r * 0.1 * $h),
                    self::COL_GOLD_R, self::COL_GOLD_G, self::COL_GOLD_B
                ));
            }
        }

        for ($i = 0; $i < 10; $i++) {
            $a = ($i / 10) * M_PI * 2 + $t * 2;
            $this->sendParticle(new InstantEnchantParticle($this->v($cx + cos($a) * $r * 0.6, $cy + sin($t * 3 + $i) * 0.3, $cz + sin($a) * $r * 0.6)));
        }

        if ($this->ticksRan % 14 === 0) {
            $this->sendSplash($cx, $cy + 1, $cz, self::COL_SPLASH_GOLD);
        }
    }

    private function drawGear4() {
        $t  = $this->phase;
        $r  = $this->radius;
        $cx = $this->cx; $cy = $this->cy + 0.8; $cz = $this->cz;

        for ($i = 0; $i < 4; $i++) {
            $a   = ($i / 4) * M_PI * 2 + $t * 2.5;
            $orb = $r * (0.5 + sin($t * 1.8 + $i * 1.6) * 0.2);
            $oy  = $cy + sin($t * 3 + $i) * 0.4;
            $this->sendParticle(new DustParticle($this->v($cx + cos($a) * $orb, $oy, $cz + sin($a) * $orb), self::COL_RED_R, self::COL_RED_G, self::COL_RED_B));
            if ($i % 2 === 0) {
                $this->sendParticle(new FlameParticle($this->v($cx + cos($a) * $orb * 0.8, $oy + 0.3, $cz + sin($a) * $orb * 0.8)));
            }
        }

        $breathe = 0.9 + sin($t * 1.2) * 0.1;
        for ($i = 0; $i < 10; $i++) {
            $a = ($i / 10) * M_PI * 2 + $t * 0.4;
            $this->sendParticle(new DustParticle($this->v($cx + cos($a) * $r * $breathe, $cy - 0.4, $cz + sin($a) * $r * $breathe), 255, 80, 0));
        }

        for ($i = 0; $i < 3; $i++) {
            $a  = $t * 3.5 + ($i / 3) * M_PI * 2;
            $sr = $r * 0.35;
            $this->sendParticle(new CriticalParticle($this->v($cx + cos($a) * $sr, $cy + abs(sin($t * 2 + $i)) * 0.6, $cz + sin($a) * $sr)));
        }

        if ($this->ticksRan % 5 === 0) {
            $ra = mt_rand(0, 628) / 100.0;
            $rd = $r * (0.2 + mt_rand(0, 5) / 10.0);
            $this->sendParticle(new CriticalParticle($this->v($cx + cos($ra) * $rd, $cy + mt_rand(0, 10) / 10.0, $cz + sin($ra) * $rd)));
        }

        if ($this->ticksRan % 3 === 0) {
            $ra = mt_rand(0, 628) / 100.0;
            $rd = $r * 0.25;
            $this->sendParticle(new FlameParticle($this->v($cx + cos($ra) * $rd, $cy - 0.2 + mt_rand(0, 5) / 10.0, $cz + sin($ra) * $rd)));
        }

        if ($this->ticksRan % 20 === 0) {
            $this->sendSplash($cx, $cy + 0.5, $cz, self::COL_SPLASH_RED);
        }
    }
}