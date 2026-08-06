<?php

namespace OnePieceRaid\Awaken\fruits;

use pocketmine\Player;
use pocketmine\Server;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\entity\Effect;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\scheduler\Task;
use pocketmine\level\Level;
use pocketmine\level\particle\DustParticle;
use pocketmine\level\particle\CriticalParticle;
use pocketmine\level\particle\SmokeParticle;
use pocketmine\level\particle\HugeExplodeParticle;
use pocketmine\level\particle\LargeExplodeParticle;
use pocketmine\level\sound\AnvilUseSound;
use pocketmine\level\sound\FizzSound;
use pocketmine\level\sound\ExplodeSound;
use OnePiece\NPC\NPCEntity;
use OnePieceTrades\Factory\FactoryEntity;
use OnePiece\Devil\BlockEffects;

class AwakenDough extends AwakenBaseFruit {

    public function getFruitId()          { return "mochi_mochi"; }
    public function getAbility1Name()     { return "Elastic Lasso"; }
    public function getAbility2Name()     { return "Lotus Dough Combo"; }
    public function getAbility1Cooldown() { return 14.0; }
    public function getAbility2Cooldown() { return 24.0; }

    public function onAwaken1(Player $player) {
        $player->sendMessage("§d[AWAKEN] §fElastic Lasso awakened!");
        $player->sendMessage("§7Fling a colossal, ground-shattering mochi arm to pull, crush, and blast targets away.");
    }

    public function onAwaken2(Player $player) {
        $player->sendMessage("§d[AWAKEN] §fLotus Dough Combo awakened!");
        $player->sendMessage("§7Summon a storm of Haki-armored punches to launch enemies in a zig-zag aerial combo before slamming them down.");
    }

    /**
     * Helper to spawn decoupled, self-ticking physics debris that continues 
     * to animate and roll even after the main skill task terminates.
     */
    public static function createPhysicsDebris($plugin, Level $level, $cx, $cy, $cz, $count, $minSpeed, $maxSpeed, $life, $customBlocks = null) {
        $debris = BlockEffects::spawnDebris($plugin, $level, $cx, $cy, $cz, $count, $minSpeed, $maxSpeed, $life, $customBlocks);
        $plugin->getServer()->getScheduler()->scheduleRepeatingTask(
            new class($plugin, $level, $debris, $cy) extends Task {
                private $plugin, $level, $debris, $groundY, $tick = 0;
                public function __construct($pl, $lv, $deb, $gy) {
                    $this->plugin = $pl; $this->level = $lv; $this->debris = $deb; $this->groundY = $gy;
                }
                public function onRun($currentTick) {
                    $this->tick++;
                    if (empty($this->debris) || $this->tick > 45) {
                        foreach (array_keys($this->debris) as $eid) {
                            BlockEffects::sendRemove($eid);
                        }
                        $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
                        return;
                    }
                    $removed = BlockEffects::tickDebris($this->debris, $this->level, $this->groundY);
                    foreach ($removed as $eid) {
                        unset($this->debris[$eid]);
                    }
                }
            },
            1
        );
    }

    /**
     * Helper to spawn decoupled, self-ticking spiral cast debris using a custom theme palette.
     */
    public static function createThematicSpiralDebris($plugin, Level $level, $cx, $cy, $cz, $count, $radius, $life, $customBlocks) {
        $debris = [];
        for ($i = 0; $i < $count; $i++) {
            $angle = ($i / $count) * M_PI * 2;
            $blockData = $customBlocks[$i % count($customBlocks)];
            $eid = BlockEffects::newEid();
            BlockEffects::sendSpawn($level, $eid, $blockData["id"], $blockData["damage"], $cx + cos($angle) * $radius, $cy, $cz + sin($angle) * $radius);
            $debris[$eid] = [
                "eid" => $eid,
                "angle" => $angle,
                "radius" => $radius,
                "baseY" => $cy,
                "life" => $life,
                "tick" => 0
            ];
        }
        $plugin->getServer()->getScheduler()->scheduleRepeatingTask(
            new class($plugin, $level, $debris, $cx, $cy, $cz) extends Task {
                private $plugin, $level, $debris, $cx, $cy, $cz, $tick = 0;
                public function __construct($pl, $lv, $deb, $x, $y, $z) {
                    $this->plugin = $pl; $this->level = $lv; $this->debris = $deb;
                    $this->cx = $x; $this->cy = $y; $this->cz = $z;
                }
                public function onRun($currentTick) {
                    $this->tick++;
                    if (empty($this->debris) || $this->tick > 45) {
                        foreach (array_keys($this->debris) as $eid) {
                            BlockEffects::sendRemove($eid);
                        }
                        $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
                        return;
                    }
                    $removed = BlockEffects::tickSpiralDebris($this->debris, $this->level, $this->cx, $this->cz, 0.20, 0.10, 0.02);
                    foreach ($removed as $eid) {
                        unset($this->debris[$eid]);
                    }
                }
            },
            1
        );
    }

    /**
     * Locates a target within a 3D cone in front of the player.
     */
    private function getTarget(Player $player, float $maxDistance = 16.0) {
        $target = null;
        $minCosAngle = 0.68;
        $level = $player->getLevel();
        $eyePos = $player->getPosition()->add(0, $player->getEyeHeight(), 0);
        $dir = $player->getDirectionVector()->normalize();

        foreach ($level->getEntities() as $entity) {
            if ($entity === $player || !$entity->isAlive()) continue;
            if (!($entity instanceof Player) && !($entity instanceof NPCEntity) && !($entity instanceof FactoryEntity)) continue;

            $dist = $entity->distance($player);
            if ($dist > $maxDistance) continue;

            $toEntity = $entity->getPosition()->add(0, $entity->getEyeHeight() / 2, 0)->subtract($eyePos)->normalize();
            $dot = $dir->dot($toEntity);

            if ($dot > $minCosAngle) {
                if ($target === null || $dist < $target->distance($player)) {
                    $target = $entity;
                }
            }
        }
        return $target;
    }

    /**
     * Checks if target is currently blocking.
     */
    private function isBlocking($entity): bool {
        if ($entity instanceof Player) {
            if ($entity->isSneaking()) return true;
            $item = $entity->getInventory()->getItemInHand();
            if ($item instanceof Item && in_array($item->getId(), [Item::WOODEN_SWORD, Item::STONE_SWORD, Item::IRON_SWORD, Item::GOLDEN_SWORD, Item::DIAMOND_SWORD])) {
                return true;
            }
        }
        return false;
    }

    public function useAbility1(Player $player) {
        $plugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceDevil");
        if ($plugin === null) return;

        $target = $this->getTarget($player, 16.0);
        if ($target === null) {
            $player->sendTip("§d§lELASTIC LASSO - §7No Target Found");
            return;
        }

        $pullDamage = 4.8;
        $finishDamage = 9.6;
        $level = $player->getLevel();
        $isBlocked = $this->isBlocking($target);

        // Pure Mochi Block Palettes (Quartz + Pink Wool)
        $pureMochiBlocks = [
            ["id" => 155, "damage" => 0], // Quartz
            ["id" => 35, "damage" => 6]   // Pink Wool
        ];

        // Decoupled cast debris using 100% pure mochi blocks (Zero environmental clutter)
        self::createThematicSpiralDebris($plugin, $level, $player->x, $player->y, $player->z, 8, 2.2, 25, $pureMochiBlocks);
        self::createPhysicsDebris($plugin, $level, $player->x, $player->y, $player->z, 8, 0.35, 0.75, 20, $pureMochiBlocks);

        $lassoEids = [];
        for ($i = 0; $i < 24; $i++) {
            $eid = BlockEffects::newEid();
            $lassoEids[] = $eid;
            $blockId = ($i >= 16) ? 35 : 155;
            $meta = ($blockId === 35) ? 6 : 0;
            BlockEffects::sendSpawn($level, $eid, $blockId, $meta, $player->x, $player->y + 1.2, $player->z);
        }

        $player->sendTip("§d§lELASTIC LASSO!");
        $level->addSound(new FizzSound($player));

        $plugin->getServer()->getScheduler()->scheduleRepeatingTask(
            new class($plugin, $player, $target, $pullDamage, $finishDamage, $lassoEids, $level, $isBlocked, $pureMochiBlocks) extends Task {
                private $plugin, $player, $target, $pullDamage, $finishDamage, $lassoEids, $level, $isBlocked;
                private $tick = 0;
                private $state = 0; 
                private $shatterData = [];
                private $pureMochiBlocks;

                public function __construct($pl, $p, $t, $pd, $fd, $le, $lv, $ib, $pmb) {
                    $this->plugin = $pl; $this->player = $p; $this->target = $t;
                    $this->pullDamage = $pd; $this->finishDamage = $fd; $this->lassoEids = $le; $this->level = $lv;
                    $this->isBlocked = $ib; $this->pureMochiBlocks = $pmb;
                }

                public function onRun($currentTick) {
                    $this->tick++;

                    if (!$this->player->isOnline() || $this->tick > 90) {
                        $this->cleanup();
                        return;
                    }

                    if ($this->state === 2) {
                        foreach ($this->shatterData as $idx => &$data) {
                            $data["pos"] = $data["pos"]->add($data["vel"]);
                            $data["vel"] = $data["vel"]->multiply(0.92)->subtract(new Vector3(0, 0.06, 0));

                            BlockEffects::sendMove($this->level, $data["eid"], $data["pos"]->x, $data["pos"]->y, $data["pos"]->z, (int)($this->tick * 15), 0.0);
                            $this->level->addParticle(new DustParticle($data["pos"], 255, 192, 203));
                        }
                        if ($this->tick > 25) { 
                            $this->cleanup();
                        }
                        return;
                    }

                    if (!$this->target->isAlive()) {
                        $this->cleanup();
                        return;
                    }

                    $pPos = $this->player->getPosition()->add(0, 1.2, 0);
                    $tPos = $this->target->getPosition()->add(0, 0.9, 0);

                    $playerDir = $this->player->getDirectionVector()->normalize();
                    $toTarget = $tPos->subtract($pPos)->normalize();
                    $dot = $playerDir->dot($toTarget);

                    if ($dot < -0.15) { 
                        $this->state = 2;
                        $this->tick = 0;
                        $this->player->sendTip("§c§lLasso Snapped!");
                        $this->level->addSound(new AnvilUseSound($this->player));

                        foreach ($this->lassoEids as $idx => $eid) {
                            $segmentRatio = $idx / count($this->lassoEids);
                            $currentPos = $pPos->add($tPos->subtract($pPos)->multiply($segmentRatio));
                            
                            $explodeVel = new Vector3(
                                mt_rand(-55, 55) / 100 + ($playerDir->x * -0.35),
                                mt_rand(30, 75) / 100,
                                mt_rand(-55, 55) / 100 + ($playerDir->z * -0.35)
                            );
                            $this->shatterData[] = ["eid" => $eid, "pos" => $currentPos, "vel" => $explodeVel];
                        }
                        return;
                    }

                    $direction = $tPos->subtract($pPos);
                    $dist = $direction->length();
                    if ($dist <= 0.1) return;

                    $dirNormalized = $direction->normalize();
                    if (abs($dirNormalized->x) > 0.9) {
                        $ortho1 = (new Vector3(0, 1, 0))->cross($dirNormalized)->normalize();
                    } else {
                        $ortho1 = (new Vector3(1, 0, 0))->cross($dirNormalized)->normalize();
                    }
                    $ortho2 = $dirNormalized->cross($ortho1)->normalize();

                    if ($this->state === 0) {
                        $pct = min(1.0, $this->tick * 0.14);

                        foreach ($this->lassoEids as $idx => $eid) {
                            $nodeRatio = ($idx / count($this->lassoEids)) * $pct;
                            $nodeCenter = $pPos->add($direction->multiply($nodeRatio));

                            if ($idx >= 16) {
                                $headIdx = $idx - 16;
                                $angle = ($headIdx * (2 * M_PI / 8)) + ($this->tick * 0.6);
                                $radius = 1.45;
                                $nodePos = $nodeCenter->add($ortho1->multiply(cos($angle) * $radius))->add($ortho2->multiply(sin($angle) * $radius));
                            } else {
                                $sideMultiplier = ($idx % 2 === 0) ? 1 : -1;
                                $angle = ($nodeRatio * 18.0) + ($this->tick * 0.45) + ($sideMultiplier * M_PI);
                                $radius = 0.65 + (sin($nodeRatio * M_PI) * 0.35);

                                $nodePos = $nodeCenter->add($ortho1->multiply(cos($angle) * $radius))->add($ortho2->multiply(sin($angle) * $radius));
                            }

                            BlockEffects::sendMove($this->level, $eid, $nodePos->x, $nodePos->y, $nodePos->z, (int)($angle * 12), 0.0);
                            
                            $this->level->addParticle(new DustParticle($nodePos, 255, 255, 255));
                            if ($idx >= 16) {
                                $this->level->addParticle(new DustParticle($nodePos, 255, 105, 180));
                            }
                        }

                        if ($pct >= 1.0) {
                            $this->state = 1;
                            $this->tick = 0;

                            $ev = new EntityDamageByEntityEvent($this->player, $this->target, EntityDamageEvent::CAUSE_MAGIC, $this->pullDamage);
                            $this->target->attack($this->pullDamage, $ev);

                            $slowness = Effect::getEffect(Effect::SLOWNESS)->setDuration(50)->setAmplifier(7)->setVisible(true);
                            $this->target->addEffect($slowness);

                            if ($this->isBlocked) {
                                $this->player->sendTip("§e§lTARGET BLOCKED - Lasso Shattered!");
                                $this->level->addSound(new AnvilUseSound($this->target));
                                
                                $this->state = 2;
                                foreach ($this->lassoEids as $idx => $eid) {
                                    $this->shatterData[] = [
                                        "eid" => $eid, 
                                        "pos" => $tPos->add(mt_rand(-5,5)/10, mt_rand(-5,5)/10, mt_rand(-5,5)/10), 
                                        "vel" => new Vector3(mt_rand(-40,40)/100, mt_rand(25,55)/100, mt_rand(-40,40)/100)
                                    ];
                                }
                                return;
                            } else {
                                $this->level->addSound(new FizzSound($this->target));
                                // Target Hit Debris - 100% Pure Mochi themed blocks (Lifespan 20 ticks)
                                AwakenDough::createPhysicsDebris($this->plugin, $this->level, $tPos->x, $tPos->y, $tPos->z, 12, 0.50, 0.95, 20, $this->pureMochiBlocks);
                            }
                        }
                    }

                    if ($this->state === 1) {
                        $pullDir = $pPos->subtract($tPos);
                        $remainingDist = $pullDir->length();

                        if ($remainingDist > 2.0) {
                            $this->target->setMotion($pullDir->normalize()->multiply(1.45));

                            $wrapAngle = $this->tick * 0.75;
                            foreach ($this->lassoEids as $idx => $eid) {
                                $angle = $wrapAngle + ($idx * (2 * M_PI / count($this->lassoEids)));
                                $r = 0.70;
                                $nodeX = $this->target->x + cos($angle) * $r;
                                $nodeY = $this->target->y + 0.2 + (sin($this->tick * 0.35 + $idx) * 0.3);
                                $nodeZ = $this->target->z + sin($angle) * $r;

                                BlockEffects::sendMove($this->level, $eid, $nodeX, $nodeY, $nodeZ, (int)($angle * 12), 0.0);
                                $this->level->addParticle(new DustParticle(new Vector3($nodeX, $nodeY, $nodeZ), 255, 220, 230));
                            }
                        } else {
                            $ev = new EntityDamageByEntityEvent($this->player, $this->target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->finishDamage);
                            $this->target->attack($this->finishDamage, $ev);

                            $kbVector = $this->target->getPosition()->subtract($this->player->getPosition());
                            $kbVector->y = 0; 
                            if ($kbVector->length() > 0) {
                                $kbVector = $kbVector->normalize();
                            } else {
                                $kbVector = $this->player->getDirectionVector(); 
                            }
                            
                            $launchMotion = $kbVector->multiply(1.6);
                            $launchMotion->y = 0.75;
                            $this->target->setMotion($launchMotion);

                            $this->level->addParticle(new HugeExplodeParticle($this->target));
                            for ($i = 0; $i < 18; $i++) {
                                $offset = new Vector3(mt_rand(-10, 10)/10, mt_rand(0, 15)/10, mt_rand(-10, 10)/10);
                                $this->level->addParticle(new CriticalParticle($this->target->getPosition()->add($offset)));
                                $this->level->addParticle(new DustParticle($this->target->getPosition()->add($offset), 255, 218, 224));
                            }

                            // Combo Finisher blast: Blends clean mochi with physical ground shattering!
                            // 1. Spawns 6 physical ground blocks scanned dynamically from the floor (Lifespan 15 ticks)
                            AwakenDough::createPhysicsDebris($this->plugin, $this->level, $this->target->x, $this->target->y, $this->target->z, 6, 0.35, 0.75, 15);
                            // 2. Spawns 6 sweet pure mochi shards (Lifespan 15 ticks)
                            AwakenDough::createPhysicsDebris($this->plugin, $this->level, $this->target->x, $this->target->y, $this->target->z, 6, 0.40, 0.85, 15, $this->pureMochiBlocks);

                            $this->level->addSound(new AnvilUseSound($this->target));
                            $this->player->sendTip("§d§lCOMBO FINISHER! §7Mochi Blast!");
                            
                            $this->cleanup();
                        }
                    }
                }

                private function cleanup() {
                    foreach ($this->lassoEids as $eid) BlockEffects::sendRemove($eid);
                    $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
                }
            },
            1
        );
    }

    public function useAbility2(Player $player) {
        $plugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceDevil");
        if ($plugin === null) return;

        $target = $this->getTarget($player, 11.0);
        if ($target === null) {
            $player->sendTip("§d§lLOTUS DOUGH COMBO - §7No Target Found");
            return;
        }

        $punchDamage = 1.3;  
        $comboDamage = 1.0;  
        $slamDamage = 3.7;  
        $level = $player->getLevel();

        $fistEids = [];
        for ($i = 0; $i < 18; $i++) {
            $eid = BlockEffects::newEid();
            $fistEids[] = $eid;
            $blockId = ($i >= 8) ? 173 : 155; 
            BlockEffects::sendSpawn($level, $eid, $blockId, 0, $player->x, $player->y + 1, $player->z);
        }

        $player->sendTip("§d§lLOTUS DOUGH COMBO!");
        $level->addSound(new FizzSound($player));

        $plugin->getServer()->getScheduler()->scheduleRepeatingTask(
            new class($plugin, $player, $target, $punchDamage, $comboDamage, $slamDamage, $fistEids, $level) extends Task {
                private $plugin, $player, $target, $punchDamage, $comboDamage, $slamDamage, $fistEids, $level;
                private $tick = 0;
                private $state = 0; 
                private $comboFistEids = [];
                private $peakY = 0.0;
                private $startY = 0.0;

                private $viewRight;
                private $viewForward;

                private $startPos;
                private $p1; 
                private $p2; 
                private $p3; 
                private $p4; 

                private $trackedEids = [];

                private $thematicMochiBlocks = [
                    ["id" => 155, "damage" => 0], // Quartz
                    ["id" => 35, "damage" => 6],  // Pink Wool
                    ["id" => 173, "damage" => 0]  // Haki coal
                ];

                public function __construct($pl, $p, $t, $pd, $cd, $sd, $fe, $lv) {
                    $this->plugin = $pl; $this->player = $p; $this->target = $t;
                    $this->punchDamage = $pd; $this->comboDamage = $cd; $this->slamDamage = $sd;
                    $this->fistEids = $fe; $this->level = $lv;
                    $this->startY = $t->y;

                    foreach ($fe as $eid) {
                        $this->trackedEids[$eid] = $eid;
                    }

                    $dir = $p->getDirectionVector();
                    $dir->y = 0;
                    $this->viewForward = $dir->normalize();
                    $this->viewRight = (new Vector3(-$this->viewForward->z, 0, $this->viewForward->x))->normalize();

                    $this->startPos = $t->getPosition();
                    
                    $this->p1 = $this->startPos->add($this->viewRight->multiply(2.8))
                                               ->add($this->viewForward->multiply(1.0))
                                               ->add(new Vector3(0, 4.0, 0));

                    $this->p2 = $this->startPos->subtract($this->viewRight->multiply(2.8))
                                               ->add($this->viewForward->multiply(2.0))
                                               ->add(new Vector3(0, 7.5, 0));

                    $this->p3 = $this->startPos->add($this->viewRight->multiply(2.8))
                                               ->add($this->viewForward->multiply(2.5))
                                               ->add(new Vector3(0, 10.5, 0));

                    $this->p4 = $this->startPos->add($this->viewForward->multiply(3.0))
                                               ->add(new Vector3(0, 13.0, 0));

                    $this->peakY = $this->p4->y;
                }

                private function spawnTrackedBlock($blockId, $meta, Vector3 $pos) {
                    $eid = BlockEffects::newEid();
                    BlockEffects::sendSpawn($this->level, $eid, $blockId, $meta, $pos->x, $pos->y, $pos->z);
                    $this->trackedEids[$eid] = $eid;
                    return $eid;
                }

                private function spawnThematicDebris($cx, $cy, $cz, $count, $minSpeed, $maxSpeed, $life) {
                    $debris = BlockEffects::spawnDebris($this->plugin, $this->level, $cx, $cy, $cz, $count, $minSpeed, $maxSpeed, $life, $this->thematicMochiBlocks);
                    foreach ($debris as $eid => $data) {
                        $this->trackedEids[$eid] = $eid;
                    }
                    return $debris;
                }

                public function onRun($currentTick) {
                    $this->tick++;

                    if (!$this->player->isOnline() || !$this->target->isAlive() || $this->tick > 160) {
                        $this->cleanup();
                        return;
                    }

                    $this->target->setMotion(new Vector3(0, 0, 0));
                    $pPos = $this->player->getPosition()->add(0, 1.0, 0);

                    // --- STATE 0: THE INITIAL LAUNCH ---
                    if ($this->state === 0) {
                        $pct = min(1.0, $this->tick * 0.16); 
                        $direction = $this->target->getPosition()->subtract($pPos);

                        $targetEasedY = sin($pct * M_PI_2);
                        $currentLocation = $this->startPos->add($this->p1->subtract($this->startPos)->multiply($targetEasedY));
                        $this->target->setPosition($currentLocation);

                        foreach ($this->fistEids as $idx => $eid) {
                            $ratio = ($idx / count($this->fistEids)) * $pct;
                            $center = $pPos->add($direction->multiply($ratio));
                            
                            $r = ($idx >= 8) ? 0.95 : 0.45;
                            $angle = $idx * (2 * M_PI / 10);
                            $nodeX = $center->x + cos($angle) * $r;
                            $nodeY = $center->y + sin($this->tick * 0.3) * 0.15;
                            $nodeZ = $center->z + sin($angle) * $r;

                            BlockEffects::sendMove($this->level, $eid, $nodeX, $nodeY, $nodeZ, (int)($angle * 10), 0.0);
                            $this->level->addParticle(new DustParticle(new Vector3($nodeX, $nodeY, $nodeZ), 45, 45, 45));
                        }

                        if ($pct >= 1.0) {
                            $this->state = 1;
                            $this->tick = 0;

                            $ev = new EntityDamageByEntityEvent($this->player, $this->target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->punchDamage);
                            $this->target->attack($this->punchDamage, $ev);

                            $this->level->addSound(new AnvilUseSound($this->target));
                            $this->level->addParticle(new HugeExplodeParticle($this->target));

                            // Launch sweet thematic mochi fragments on initial punch impact
                            $launchDebris = $this->spawnThematicDebris($this->target->x, $this->target->y, $this->target->z, 10, 0.45, 0.90, 20);
                            $this->activePhysicsDebris = array_merge($this->activePhysicsDebris, $launchDebris);
                        }
                    }

                    // --- STATE 1: SNAPPY RELATIVE ZIG-ZAG & MORPH PORTAL ---
                    if ($this->state === 1) {
                        $portalCenter = $this->player->getPosition()->add(0, 3.2, 0)->subtract($this->viewForward->multiply(1.5));
                        
                        foreach ($this->fistEids as $idx => $eid) {
                            $angle = ($idx / count($this->fistEids)) * 2 * M_PI + ($this->tick * 0.14);
                            $r = 1.65;
                            $bx = $portalCenter->x + cos($angle) * $r * $this->viewRight->x;
                            $by = $portalCenter->y + sin($angle) * $r;
                            $bz = $portalCenter->z + cos($angle) * $r * $this->viewRight->z;

                            BlockEffects::sendMove($this->level, $eid, $bx, $by, $bz, (int)($angle * 10), 0.0);
                            
                            if ($this->tick % 3 === 0) {
                                $this->level->addParticle(new DustParticle(new Vector3($bx, $by, $bz), 255, 182, 193)); 
                                $this->level->addParticle(new DustParticle(new Vector3($bx, $by, $bz), 255, 255, 255)); 
                            }
                        }

                        // --- Zig 1 Strike ---
                        if ($this->tick < 15) {
                            $subPct = $this->tick / 15;
                            $driftPos = $this->p1->add(new Vector3(0, $subPct * 0.5, 0));
                            $this->target->setPosition($driftPos);
                        }

                        if ($this->tick === 15) {
                            $spawnFistPos = $this->target->getPosition()->add($this->viewRight->multiply(4.0));
                            $vel = $this->viewRight->multiply(-1.5); 
                            $this->triggerTempPunch($spawnFistPos, $vel);

                            for ($i = 0; $i <= 10; $i++) {
                                $pctLine = $i / 10;
                                $posLine = $portalCenter->add($this->target->getPosition()->subtract($portalCenter)->multiply($pctLine));
                                $this->level->addParticle(new DustParticle($posLine, 255, 255, 255));
                            }
                        }

                        if ($this->tick >= 15 && $this->tick <= 18) {
                            $snapPct = ($this->tick - 15) / 3;
                            $snapPos = $this->p1->add($this->p2->subtract($this->p1)->multiply($snapPct));
                            $this->target->setPosition($snapPos);
                        }

                        // --- Zag 2 Strike ---
                        if ($this->tick > 18 && $this->tick < 35) {
                            $subPct = ($this->tick - 18) / 17;
                            $driftPos = $this->p2->add(new Vector3(0, $subPct * 0.5, 0));
                            $this->target->setPosition($driftPos);
                        }

                        if ($this->tick === 35) {
                            $spawnFistPos = $this->target->getPosition()->subtract($this->viewRight->multiply(4.0));
                            $vel = $this->viewRight->multiply(1.5); 
                            $this->triggerTempPunch($spawnFistPos, $vel);

                            for ($i = 0; $i <= 10; $i++) {
                                $pctLine = $i / 10;
                                $posLine = $portalCenter->add($this->target->getPosition()->subtract($portalCenter)->multiply($pctLine));
                                $this->level->addParticle(new DustParticle($posLine, 255, 105, 180));
                            }
                        }

                        if ($this->tick >= 35 && $this->tick <= 38) {
                            $snapPct = ($this->tick - 35) / 3;
                            $snapPos = $this->p2->add($this->p3->subtract($this->p2)->multiply($snapPct));
                            $this->target->setPosition($snapPos);
                        }

                        // --- Realignment Center Strike ---
                        if ($this->tick > 38 && $this->tick < 55) {
                            $subPct = ($this->tick - 38) / 17;
                            $driftPos = $this->p3->add(new Vector3(0, $subPct * 0.5, 0));
                            $this->target->setPosition($driftPos);
                        }

                        if ($this->tick === 55) {
                            $spawnFistPos = $this->target->getPosition()->add($this->viewForward->multiply(4.0));
                            $vel = $this->viewForward->multiply(-1.5); 
                            $this->triggerTempPunch($spawnFistPos, $vel);

                            for ($i = 0; $i <= 10; $i++) {
                                $pctLine = $i / 10;
                                $posLine = $portalCenter->add($this->target->getPosition()->subtract($portalCenter)->multiply($pctLine));
                                $this->level->addParticle(new DustParticle($posLine, 45, 45, 45)); 
                            }
                        }

                        if ($this->tick >= 55 && $this->tick <= 58) {
                            $snapPct = ($this->tick - 55) / 3;
                            $snapPos = $this->p3->add($this->p4->subtract($this->p3)->multiply($snapPct));
                            $this->target->setPosition($snapPos);
                        }

                        $this->tickTempPunches();

                        if ($this->tick >= 62) {
                            $this->state = 2;
                            $this->tick = 0;
                            $this->clearTempPunches();
                        }
                    }

                    // --- STATE 2: GIANT SEISMIC HAMMER ASSEMBLY ---
                    if ($this->state === 2) {
                        $this->target->setPosition($this->p4);

                        $fistTargetPos = new Vector3($this->target->x, $this->peakY + 4.8, $this->target->z);
                        
                        foreach ($this->fistEids as $idx => $eid) {
                            $angle = ($idx * (2 * M_PI / count($this->fistEids))) + ($this->tick * 0.35); 
                            $r = ($idx >= 8) ? 1.55 : 0.70;
                            
                            $bx = $fistTargetPos->x + cos($angle) * $r;
                            $by = $fistTargetPos->y + ($idx * 0.15) - 1.0;
                            $bz = $fistTargetPos->z + sin($angle) * $r;

                            BlockEffects::sendMove($this->level, $eid, $bx, $by, $bz, (int)($angle * 10), 0.0);
                            
                            if ($this->tick % 2 === 0) {
                                $this->level->addParticle(new DustParticle(new Vector3($bx, $by, $bz), 30, 30, 30));
                                $this->level->addParticle(new SmokeParticle(new Vector3($bx, $by, $bz)));
                            }
                        }

                        if ($this->tick === 15) {
                            $this->state = 3;
                            $this->tick = 0;
                        }
                    }

                    // --- STATE 3: TERMINAL HAMMER SLAM ---
                    if ($this->state === 3) {
                        $fallPct = min(1.0, $this->tick * 0.18);
                        $currentY = $this->peakY - ($fallPct * ($this->peakY - $this->startY));
                        $this->target->setPosition(new Vector3($this->p4->x, $currentY, $this->p4->z));

                        $slamY = ($this->peakY + 4.8) - ($this->tick * 1.8);
                        $fistHeight = max($this->target->y + 0.5, $slamY);

                        foreach ($this->fistEids as $idx => $eid) {
                            $angle = ($idx * (2 * M_PI / count($this->fistEids))) + ($this->tick * 0.6);
                            $r = ($idx >= 8) ? 1.55 : 0.70;
                            
                            $bx = $this->target->x + cos($angle) * $r;
                            $by = $fistHeight + ($idx * 0.1);
                            $bz = $this->target->z + sin($angle) * $r;

                            BlockEffects::sendMove($this->level, $eid, $bx, $by, $bz, (int)($angle * 10), 0.0);
                            $this->level->addParticle(new DustParticle(new Vector3($bx, $by, $bz), 45, 45, 45));
                        }

                        if ($this->target->y <= $this->startY + 0.55 || $fallPct >= 1.0) {
                            $this->target->setPosition(new Vector3($this->p4->x, $this->startY, $this->p4->z));

                            $ev = new EntityDamageByEntityEvent($this->player, $this->target, EntityDamageEvent::CAUSE_ENTITY_EXPLOSION, $this->slamDamage);
                            $this->target->attack($this->slamDamage, $ev);

                            $pos = $this->target->getPosition();

                            // Final Ground Slam debris impact explosion (Scans ground + spawns thematic mochi)
                            // 1. Spawns 18 physical scanned ground blocks vanishing after 30 ticks
                            AwakenDough::createPhysicsDebris($this->plugin, $this->level, $pos->x, $this->startY, $pos->z, 18, 0.65, 1.45, 30);
                            
                            // 2. Spawns 12 sweet pink/white mochi and Haki shards vanishing after 25 ticks
                            AwakenDough::createPhysicsDebris($this->plugin, $this->level, $pos->x, $this->startY, $pos->z, 12, 0.75, 1.65, 25, $this->thematicMochiBlocks);

                            for ($i = 0; $i < 24; $i++) {
                                $angle = ($i / 24) * 2 * M_PI;
                                $px = $pos->x + cos($angle) * 3.5;
                                $pz = $pos->z + sin($angle) * 3.5;
                                $this->level->addParticle(new DustParticle(new Vector3($px, $pos->y + 0.1, $pz), 20, 20, 20));
                                $this->level->addParticle(new SmokeParticle(new Vector3($px, $pos->y + 0.2, $pz)));
                            }

                            $this->level->addParticle(new HugeExplodeParticle($pos));
                            $this->level->addParticle(new LargeExplodeParticle($pos));
                            $this->level->addSound(new ExplodeSound($pos));
                            $this->level->addSound(new AnvilUseSound($pos));

                            $this->player->sendTip("§d§lLOTUS COMBO - §fEarthquake Smash!");
                            $this->cleanup();
                        }
                    }
                }

                private function triggerTempPunch(Vector3 $spawnPos, Vector3 $vel) {
                    $fists = [];
                    for ($i = 0; $i < 6; $i++) {
                        $eid = $this->spawnTrackedBlock(($i >= 3) ? 173 : 155, 0, $spawnPos);
                        $fists[] = $eid;
                    }

                    $this->comboFistEids[] = [
                        "eids" => $fists,
                        "pos" => $spawnPos,
                        "vel" => $vel->multiply(0.20), 
                        "life" => 0
                    ];

                    $this->level->addSound(new FizzSound($this->target));
                }

                private function tickTempPunches() {
                    foreach ($this->comboFistEids as $key => &$punch) {
                        $punch["life"]++;
                        $punch["pos"] = $punch["pos"]->add($punch["vel"]);

                        foreach ($punch["eids"] as $idx => $eid) {
                            $angle = $idx * (2 * M_PI / count($punch["eids"]));
                            $r = 0.55;
                            $bx = $punch["pos"]->x + cos($angle) * $r;
                            $by = $punch["pos"]->y + ($idx * 0.08);
                            $bz = $punch["pos"]->z + sin($angle) * $r;

                            BlockEffects::sendMove($this->level, $eid, $bx, $by, $bz, (int)($angle * 10), 0.0);
                            $this->level->addParticle(new DustParticle(new Vector3($bx, $by, $bz), 40, 40, 40));
                        }

                        if ($punch["life"] === 5) {
                            $ev = new EntityDamageByEntityEvent($this->player, $this->target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->comboDamage);
                            $this->target->attack($this->comboDamage, $ev);

                            $pos = $this->target->getPosition();

                            $this->level->addParticle(new HugeExplodeParticle($pos));
                            for ($i = 0; $i < 8; $i++) {
                                $this->level->addParticle(new CriticalParticle($pos->add(mt_rand(-5,5)/10, mt_rand(0,10)/10, mt_rand(-5,5)/10)));
                            }
                            $this->level->addSound(new AnvilUseSound($pos));

                            // Mid-air strikes debris lifespan lowered to 15 ticks
                            AwakenDough::createPhysicsDebris($this->plugin, $this->level, $pos->x, $pos->y, $pos->z, 8, 0.45, 0.95, 15, $this->thematicMochiBlocks);

                            foreach ($punch["eids"] as $eid) {
                                BlockEffects::sendRemove($eid);
                                unset($this->trackedEids[$eid]);
                            }
                            unset($this->comboFistEids[$key]);
                        }
                    }
                    unset($punch);
                }

                private function clearTempPunches() {
                    foreach ($this->comboFistEids as $punch) {
                        foreach ($punch["eids"] as $eid) {
                            BlockEffects::sendRemove($eid);
                            unset($this->trackedEids[$eid]);
                        }
                    }
                    $this->comboFistEids = [];
                }

                private function cleanup() {
                    $this->clearTempPunches();
                    
                    foreach ($this->trackedEids as $eid) {
                        BlockEffects::sendRemove($eid);
                    }
                    $this->trackedEids = [];

                    $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
                }
            },
            1
        );
    }
}