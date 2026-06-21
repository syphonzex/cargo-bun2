<?php
namespace OnePiece\Devil;

use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\entity\Entity;

class BlockEffects {

    const SKIP_IDS = [
        0,8,9,10,11,26,30,31,32,37,38,39,40,50,51,55,59,
        63,64,65,68,69,70,71,72,75,76,77,83,90,93,94,96,
        104,105,106,115,127,131,132,141,142,143,144,147,148,
        149,150,154,157,167,171,175,176,177,178,183,184,185,
        186,187,193,194,195,196,197
    ];

    const FALLBACK_BLOCKS = [
        ["id" => 4, "damage" => 0],
        ["id" => 1, "damage" => 0],
        ["id" => 3, "damage" => 0]
    ];

    private static $nextEid = 900000;
    private static $entityMap = [];

    public static function registerEntity() {
        Entity::registerEntity(BlockEffectEntity::class, true, ["BlockEffect"]);
    }

    public static function newEid() {
        $eid = self::$nextEid++;
        if (self::$nextEid > 999999) {
            self::$nextEid = 900000;
        }
        return $eid;
    }

    public static function sendSpawn(Level $level, $eid, $blockId, $blockDamage, $x, $y, $z) {
        $entity = BlockEffectEntity::create($level, $x, $y, $z, $blockId, $blockDamage);
        if ($entity !== null) {
            $entity->spawnToAll();
            self::$entityMap[$eid] = $entity;
        }
    }

    public static function sendMove(Level $level, $eid, $x, $y, $z, $yaw = 0.0, $pitch = 0.0) {
        if (!isset(self::$entityMap[$eid])) return;
        $entity = self::$entityMap[$eid];
        if ($entity === null || $entity->closed) {
            unset(self::$entityMap[$eid]);
            return;
        }
        $entity->x = (float)$x;
        $entity->y = (float)$y;
        $entity->z = (float)$z;
        $entity->yaw = (float)$yaw;
        $entity->pitch = (float)$pitch;
        $entity->updateMovement();
    }

    public static function sendRemove($eid) {
        if (!isset(self::$entityMap[$eid])) return;
        $entity = self::$entityMap[$eid];
        if ($entity !== null && !$entity->closed) {
            $entity->close();
        }
        unset(self::$entityMap[$eid]);
    }

    public static function voidAndRemove($plugin, Level $level, array $eids) {
        foreach ($eids as $eid) {
            self::sendRemove($eid);
        }
    }

    public static function removeFromMap($eid) {
        if (isset(self::$entityMap[$eid])) {
            $entity = self::$entityMap[$eid];
            if ($entity !== null && !$entity->closed) {
                $entity->close();
            }
            unset(self::$entityMap[$eid]);
        }
    }

    public static function getEntity($eid) {
        return isset(self::$entityMap[$eid]) ? self::$entityMap[$eid] : null;
    }

    public static function removeAll(array $eids) {
        foreach ($eids as $eid) {
            self::sendRemove($eid);
        }
    }

    public static function scanBlocks(Level $level, $cx, $cy, $cz, $radius = 6, $maxBlocks = 6) {
        $found = [];
        $scanR = min((int)$radius, 8);
        for ($x = -$scanR; $x <= $scanR; $x += 2) {
            for ($z = -$scanR; $z <= $scanR; $z += 2) {
                for ($y = -3; $y <= 3; $y++) {
                    $block = $level->getBlock(new Vector3((int)($cx + $x), (int)($cy + $y), (int)($cz + $z)));
                    $id = $block->getId();
                    $dmg = $block->getDamage();
                    if (in_array($id, self::SKIP_IDS)) continue;
                    $key = $id . ":" . $dmg;
                    if (!isset($found[$key])) {
                        $found[$key] = ["id" => $id, "damage" => $dmg];
                    }
                    if (count($found) >= $maxBlocks) {
                        return array_values($found);
                    }
                }
            }
        }
        if (empty($found)) {
            return self::FALLBACK_BLOCKS;
        }
        return array_values($found);
    }

    public static function spawnDebris($plugin, Level $level, $cx, $cy, $cz, $count, $minSpeed, $maxSpeed, $life = 30, $customBlocks = null) {
        $blocks = ($customBlocks !== null) ? $customBlocks : self::scanBlocks($level, $cx, $cy, $cz, 6, $count);
        $debris = [];
        for ($i = 0; $i < $count; $i++) {
            $angle = ($i / $count) * M_PI * 2 + (mt_rand(-30, 30) / 100);
            $speed = $minSpeed + (mt_rand(0, 100) / 100) * ($maxSpeed - $minSpeed);
            $blockData = $blocks[$i % count($blocks)];
            $eid = self::newEid();
            self::sendSpawn($level, $eid, $blockData["id"], $blockData["damage"], $cx, $cy + 0.5, $cz);
            $debris[$eid] = [
                "eid" => $eid,
                "x" => (float)$cx,
                "y" => (float)($cy + 0.5),
                "z" => (float)$cz,
                "vx" => cos($angle) * $speed,
                "vy" => 0.5 + mt_rand(0, 30) / 100,
                "vz" => sin($angle) * $speed,
                "life" => $life + mt_rand(0, 10),
                "tick" => 0
            ];
        }
        return $debris;
    }

    public static function spawnDebrisDirectional($plugin, Level $level, $cx, $cy, $cz, $dirX, $dirZ, $count, $spread, $speed, $life = 30) {
        $blocks = self::scanBlocks($level, $cx, $cy, $cz, 6, $count);
        $debris = [];
        for ($i = 0; $i < $count; $i++) {
            $angleOffset = (mt_rand(-100, 100) / 100) * $spread;
            $baseAngle = atan2($dirZ, $dirX);
            $angle = $baseAngle + $angleOffset;
            $spd = $speed * (0.7 + mt_rand(0, 60) / 100);
            $blockData = $blocks[$i % count($blocks)];
            $eid = self::newEid();
            self::sendSpawn($level, $eid, $blockData["id"], $blockData["damage"], $cx, $cy + 0.5, $cz);
            $debris[$eid] = [
                "eid" => $eid,
                "x" => (float)$cx,
                "y" => (float)($cy + 0.5),
                "z" => (float)$cz,
                "vx" => cos($angle) * $spd,
                "vy" => 0.4 + mt_rand(0, 25) / 100,
                "vz" => sin($angle) * $spd,
                "life" => $life + mt_rand(0, 8),
                "tick" => 0
            ];
        }
        return $debris;
    }

    public static function tickDebris(&$debris, Level $level, $groundY, $gravity = 0.06, $drag = 0.98) {
        $toRemove = [];
        foreach ($debris as $eid => &$d) {
            $d["tick"]++;
            if ($d["tick"] >= $d["life"]) {
                self::sendRemove($eid);
                $toRemove[] = $eid;
                continue;
            }
            $d["vy"] -= $gravity;
            $d["vx"] *= $drag;
            $d["vz"] *= $drag;
            $d["x"] += $d["vx"];
            $d["y"] += $d["vy"];
            $d["z"] += $d["vz"];
            if ($d["y"] < $groundY) {
                $d["y"] = $groundY;
                $d["vy"] = 0;
                $d["vx"] *= 0.5;
                $d["vz"] *= 0.5;
            }
            self::sendMove($level, $eid, $d["x"], $d["y"], $d["z"], $d["tick"] * 20, $d["tick"] * 15);
        }
        unset($d);
        return $toRemove;
    }

    public static function spawnSpiralDebris($plugin, Level $level, $cx, $cy, $cz, $count, $radius, $life = 40) {
        $blocks = self::scanBlocks($level, $cx, $cy, $cz, 6, $count);
        $debris = [];
        for ($i = 0; $i < $count; $i++) {
            $angle = ($i / $count) * M_PI * 2;
            $blockData = $blocks[$i % count($blocks)];
            $eid = self::newEid();
            self::sendSpawn($level, $eid, $blockData["id"], $blockData["damage"], $cx + cos($angle) * $radius, $cy, $cz + sin($angle) * $radius);
            $debris[$eid] = [
                "eid" => $eid,
                "angle" => $angle,
                "radius" => $radius,
                "baseY" => $cy,
                "life" => $life,
                "tick" => 0
            ];
        }
        return $debris;
    }

    public static function tickSpiralDebris(&$debris, Level $level, $cx, $cz, $angularSpeed = 0.15, $riseSpeed = 0.1, $radiusShrink = 0.02) {
        $toRemove = [];
        foreach ($debris as $eid => &$d) {
            $d["tick"]++;
            if ($d["tick"] >= $d["life"]) {
                self::sendRemove($eid);
                $toRemove[] = $eid;
                continue;
            }
            $d["angle"] += $angularSpeed;
            $d["baseY"] += $riseSpeed;
            $d["radius"] = max(0.3, $d["radius"] - $radiusShrink);
            $x = $cx + cos($d["angle"]) * $d["radius"];
            $z = $cz + sin($d["angle"]) * $d["radius"];
            self::sendMove($level, $eid, $x, $d["baseY"], $z, $d["tick"] * 25, $d["tick"] * 20);
        }
        unset($d);
        return $toRemove;
    }

    public static function spawnOrbitBlocks(Level $level, $cx, $cy, $cz, $blockId, $blockDamage, $count, $radius) {
        $entities = [];
        for ($i = 0; $i < $count; $i++) {
            $angle = ($i / $count) * M_PI * 2;
            $x = $cx + cos($angle) * $radius;
            $z = $cz + sin($angle) * $radius;
            $eid = self::newEid();
            self::sendSpawn($level, $eid, $blockId, $blockDamage, $x, $cy, $z);
            $entities[$eid] = [
                "eid" => $eid,
                "angle" => $angle,
                "orbitSpeed" => 0.04 + mt_rand(0, 20) / 1000,
                "heightOffset" => (mt_rand(-10, 10) / 10) * 0.5,
                "x" => $x,
                "y" => $cy,
                "z" => $z
            ];
        }
        return $entities;
    }

    public static function spawnTsunamiWall(Level $level, $cx, $cy, $cz, $radius, $rings = 3, $blocksPerRing = 16, $blockId = 35, $blockDamage = 3) {
        $entities = [];
        for ($ring = 0; $ring < $rings; $ring++) {
            $ringHeight = $ring * 1.4;
            $ringOffset = $ring * 0.3;
            for ($i = 0; $i < $blocksPerRing; $i++) {
                $angle = ($i / $blocksPerRing) * M_PI * 2;
                $blockRadius = $radius - $ringOffset;
                $x = $cx + cos($angle) * $blockRadius;
                $z = $cz + sin($angle) * $blockRadius;
                $y = $cy + $ringHeight;
                $eid = self::newEid();
                self::sendSpawn($level, $eid, $blockId, $blockDamage, $x, $y, $z);
                $entities[$eid] = [
                    "eid" => $eid,
                    "angle" => $angle,
                    "ring" => $ring,
                    "baseHeight" => $ringHeight,
                    "baseOffset" => $ringOffset,
                    "x" => $x,
                    "y" => $y,
                    "z" => $z
                ];
            }
        }
        return $entities;
    }
}