<?php
namespace OnePiece\Devil;

use pocketmine\Player;
use pocketmine\Server;
use pocketmine\math\Vector3;
use pocketmine\math\Math;
use pocketmine\block\Block;
use pocketmine\level\particle\DustParticle;
use pocketmine\level\particle\BubbleParticle;
use pocketmine\level\particle\MobSpellParticle;
use pocketmine\level\particle\SplashParticle;
use pocketmine\level\particle\SmokeParticle;

class IceWalkManager {

    private $plugin;
    private $frozenBlocks  = [];
    private $lastPos       = [];
    private $vfxTick       = [];

    const MELT_TICKS    = 40;
    const VFX_INTERVAL  = 6;
    const FREEZE_RADIUS = 1;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function tick() {
        foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) {
            $name = $player->getName();

            if (!$this->plugin->getFruitManager()->playerHasFruit($player)) {
                if (isset($this->frozenBlocks[$name])) $this->cleanupPlayer($name);
                continue;
            }

            $fruitId = $this->plugin->getFruitManager()->getPlayerFruitId($player);
            if ($fruitId !== 'hie_hie') {
                if (isset($this->frozenBlocks[$name])) $this->cleanupPlayer($name);
                continue;
            }

            if (!$this->plugin->isInOPWorld($player)) {
                if (isset($this->frozenBlocks[$name])) $this->cleanupPlayer($name);
                continue;
            }

            if (!isset($this->frozenBlocks[$name])) $this->frozenBlocks[$name] = [];

            $this->processPlayer($player);
        }

        foreach (array_keys($this->frozenBlocks) as $name) {
            if ($this->plugin->getServer()->getPlayerExact($name) === null) {
                $this->cleanupPlayer($name);
            }
        }
    }

    public function onPlayerMove(Player $player) {
        $name = $player->getName();
        if (!$this->plugin->getFruitManager()->playerHasFruit($player)) return;
        if ($this->plugin->getFruitManager()->getPlayerFruitId($player) !== 'hie_hie') return;
        if (!$this->plugin->isInOPWorld($player)) return;

        if (!isset($this->frozenBlocks[$name])) $this->frozenBlocks[$name] = [];

        $this->freezeAroundPlayer($player);
    }

    private function processPlayer(Player $player) {
        static $tickCount = 0;
        $tickCount++;

        $this->freezeAroundPlayer($player);

        $name = $player->getName();

        if ($tickCount % self::VFX_INTERVAL === 0) {
            $this->tickAmbientVFX($name, $player);
        }

        $this->meltOldBlocks($name);
    }

    private function freezeAroundPlayer(Player $player) {
        $name  = $player->getName();
        $level = $player->getLevel();

        $px = (float)$player->x;
        $py = (int)floor($player->y);
        $pz = (float)$player->z;

        $bx = Math::floorFloat($px);
        $bz = Math::floorFloat($pz);

        $isFalling = isset($this->lastPos[$name]) && ($player->y < $this->lastPos[$name]['y'] - 0.1);

        $dirX = 0;
        $dirZ = 0;
        if (isset($this->lastPos[$name])) {
            $dx = $px - $this->lastPos[$name]['x'];
            $dz = $pz - $this->lastPos[$name]['z'];
            if (abs($dx) > 0.04) $dirX = $dx > 0 ? 1 : -1;
            if (abs($dz) > 0.04) $dirZ = $dz > 0 ? 1 : -1;
        }
        $this->lastPos[$name] = ['x' => $px, 'y' => $player->y, 'z' => $pz];

        $levelName = $level->getName();
        $now       = $this->getTick();

        $toCheck = [];

        for ($ox = -self::FREEZE_RADIUS; $ox <= self::FREEZE_RADIUS; $ox++) {
            for ($oz = -self::FREEZE_RADIUS; $oz <= self::FREEZE_RADIUS; $oz++) {
                $toCheck[] = [$bx + $ox, $bz + $oz];
            }
        }

        if ($dirX !== 0 || $dirZ !== 0) {
            for ($look = 1; $look <= 3; $look++) {
                $lx = $bx + $dirX * $look;
                $lz = $bz + $dirZ * $look;
                for ($ox = -1; $ox <= 1; $ox++) {
                    for ($oz = -1; $oz <= 1; $oz++) {
                        $toCheck[] = [$lx + $ox, $lz + $oz];
                    }
                }
            }
        }

        foreach ($toCheck as $coord) {
            $cx = $coord[0];
            $cz = $coord[1];

            $surfaceY = $this->findWaterSurface($level, $cx, $py, $cz, $isFalling);
            if ($surfaceY === null) continue;

            $key = $cx . ':' . $surfaceY . ':' . $cz . ':' . $levelName;

            if (!isset($this->frozenBlocks[$name][$key])) {
                $level->setBlock(new Vector3($cx, $surfaceY, $cz), Block::get(Block::ICE), false, false);
                $this->frozenBlocks[$name][$key] = [
                    'x'     => $cx,
                    'y'     => $surfaceY,
                    'z'     => $cz,
                    'level' => $levelName,
                    'tick'  => $now,
                ];
                $this->spawnFreezeVFX($level, $cx, $surfaceY, $cz);
            } else {
                $this->frozenBlocks[$name][$key]['tick'] = $now;
            }
        }
    }

    private function findWaterSurface($level, $x, $startY, $z, $isFalling = false) {
        $scanDown = $isFalling ? 40 : 6;
        for ($y = $startY + 2; $y >= $startY - $scanDown; $y--) {
            $block = $level->getBlock(new Vector3($x, $y, $z));
            $id    = $block->getId();

            if ($id === Block::WATER || $id === Block::STILL_WATER) {
                $above   = $level->getBlock(new Vector3($x, $y + 1, $z));
                $aboveId = $above->getId();
                if ($aboveId === Block::AIR || $aboveId === Block::ICE) {
                    return $y;
                }
            }

            if ($id === Block::ICE) {
                return $y;
            }

            if (!$isFalling && $id !== Block::AIR && $id !== Block::WATER && $id !== Block::STILL_WATER) {
                break;
            }
        }
        return null;
    }

    private function spawnFreezeVFX($level, $x, $y, $z) {
        $cx = $x + 0.5;
        $cy = $y + 1.0;
        $cz = $z + 0.5;

        $level->addParticle(new DustParticle(new Vector3($cx, $cy, $cz), 180, 230, 255));

        for ($i = 0; $i < 3; $i++) {
            $ox = mt_rand(-4, 4) / 10;
            $oz = mt_rand(-4, 4) / 10;
            $level->addParticle(new DustParticle(
                new Vector3($cx + $ox, $cy + mt_rand(0, 3) / 10, $cz + $oz),
                150, 215, 255
            ));
        }

        if (mt_rand(0, 2) === 0) {
            $level->addParticle(new BubbleParticle(new Vector3($cx, $cy + 0.1, $cz)));
        }
    }

    private function tickAmbientVFX($name, Player $player) {
        if (!isset($this->frozenBlocks[$name])) return;

        $count = count($this->frozenBlocks[$name]);
        if ($count === 0) return;

        $keys = array_keys($this->frozenBlocks[$name]);
        shuffle($keys);
        $max = min($count, 5);
        $level = $player->getLevel();

        for ($i = 0; $i < $max; $i++) {
            $data = $this->frozenBlocks[$name][$keys[$i]];
            if ($data['level'] !== $level->getName()) continue;

            $cx = $data['x'] + 0.5;
            $cy = $data['y'] + 1.0;
            $cz = $data['z'] + 0.5;

            switch (mt_rand(0, 3)) {
                case 0:
                    $level->addParticle(new DustParticle(
                        new Vector3($cx + mt_rand(-3, 3) / 10, $cy + mt_rand(0, 5) / 10, $cz + mt_rand(-3, 3) / 10),
                        180, 235, 255
                    ));
                    break;
                case 1:
                    $level->addParticle(new DustParticle(
                        new Vector3($cx + mt_rand(-4, 4) / 10, $cy + mt_rand(2, 8) / 10, $cz + mt_rand(-4, 4) / 10),
                        220, 245, 255
                    ));
                    break;
                case 2:
                    $level->addParticle(new MobSpellParticle(
                        new Vector3($cx + mt_rand(-3, 3) / 10, $cy + 0.1, $cz + mt_rand(-3, 3) / 10),
                        100, 200, 255
                    ));
                    break;
                case 3:
                    $level->addParticle(new BubbleParticle(
                        new Vector3($cx + mt_rand(-2, 2) / 10, $cy + mt_rand(0, 3) / 10, $cz + mt_rand(-2, 2) / 10)
                    ));
                    break;
            }
        }
    }

    private function meltOldBlocks($name) {
        if (!isset($this->frozenBlocks[$name])) return;

        $now      = $this->getTick();
        $toRemove = [];

        foreach ($this->frozenBlocks[$name] as $key => $data) {
            if ($now - $data['tick'] < self::MELT_TICKS) continue;

            $lv = $this->plugin->getServer()->getLevelByName($data['level']);
            if ($lv !== null) {
                $block = $lv->getBlock(new Vector3($data['x'], $data['y'], $data['z']));
                if ($block->getId() === Block::ICE) {
                    $lv->setBlock(new Vector3($data['x'], $data['y'], $data['z']), Block::get(Block::STILL_WATER), false, false);
                    $this->spawnMeltVFX($lv, $data['x'], $data['y'], $data['z']);
                }
            }
            $toRemove[] = $key;
        }

        foreach ($toRemove as $key) {
            unset($this->frozenBlocks[$name][$key]);
        }
    }

    private function spawnMeltVFX($level, $x, $y, $z) {
        $cx = $x + 0.5;
        $cy = $y + 1.0;
        $cz = $z + 0.5;

        $level->addParticle(new SplashParticle(new Vector3($cx, $cy, $cz)));
        for ($i = 0; $i < 3; $i++) {
            $level->addParticle(new DustParticle(
                new Vector3($cx + mt_rand(-3, 3) / 10, $cy + mt_rand(0, 4) / 10, $cz + mt_rand(-3, 3) / 10),
                100, 180, 255
            ));
        }
        $level->addParticle(new SmokeParticle(new Vector3($cx, $cy + 0.3, $cz)));
    }

    private function getTick() {
        return $this->plugin->getServer()->getTick();
    }

    public function cleanup($playerName) {
        $this->cleanupPlayer($playerName);
    }

    private function cleanupPlayer($name) {
        if (isset($this->frozenBlocks[$name])) {
            foreach ($this->frozenBlocks[$name] as $data) {
                $lv = $this->plugin->getServer()->getLevelByName($data['level']);
                if ($lv !== null) {
                    $block = $lv->getBlock(new Vector3($data['x'], $data['y'], $data['z']));
                    if ($block->getId() === Block::ICE) {
                        $lv->setBlock(new Vector3($data['x'], $data['y'], $data['z']), Block::get(Block::STILL_WATER), false, false);
                        $this->spawnMeltVFX($lv, $data['x'], $data['y'], $data['z']);
                    }
                }
            }
            unset($this->frozenBlocks[$name]);
        }
        unset($this->lastPos[$name]);
        unset($this->vfxTick[$name]);
    }
}
