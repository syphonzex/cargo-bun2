<?php
namespace OnePiece\Devil\fruits;

use OnePiece\Devil\BaseFruit;
use OnePiece\Devil\BlockEffects;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\math\Vector3;
use pocketmine\entity\Effect;
use pocketmine\utils\TextFormat;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\scheduler\Task;
use pocketmine\level\Level;
use pocketmine\level\particle\DustParticle;
use pocketmine\level\particle\LargeExplodeParticle;
use pocketmine\level\particle\ExplodeParticle;
use pocketmine\level\particle\CriticalParticle;
use pocketmine\level\particle\SmokeParticle;
use pocketmine\level\particle\FlameParticle;
use pocketmine\level\sound\AnvilUseSound;
use pocketmine\level\sound\ExplodeSound;
use pocketmine\level\sound\PopSound;
use pocketmine\level\sound\FizzSound;
use pocketmine\block\Block;

class MochiMochi extends BaseFruit {

    public function getId()          { return "mochi_mochi"; }
    public function getDisplayName() { return "Dough-Dough Fruit"; }
    public function getDescription() { return "Dough Fruit - The unstoppable sticky power."; }
    public function getType()        { return "logia"; }
    public function getRarity()      { return "mythical"; }

    public function getAbilityNames() {
        return [
            "ability1" => "Dough Roll Crash",
            "ability2" => "Scorching Mochi Wheel"
        ];
    }

    public function getAbilityCooldowns() {
        return [
            "ability1" => 14.0,
            "ability2" => 30.0
        ];
    }

    public function useAbility(Player $player, $ability) {
        switch ($ability) {
            case "ability1":
                if (!$this->checkMastery($player, "ability1")) return 0;
                return $this->doughRollCrash($player);
            case "ability2":
                if (!$this->checkMastery($player, "ability2")) return 0;
                return $this->scorchingMochiWheel($player);
        }
        return 0;
    }

    private function doughRollCrash(Player $player) {
        $mult   = min(1.5, $this->getHakiMultiplier($player));
        $trapDmg  = min(6.0, 1.5 * $mult);
        $smashDmg = min(6.0, 4.5 * $mult);
        $range    = $this->getMasteryRange($player, 9.0);
        $plugin   = $this->plugin;
        $fruit    = $this;

        $player->sendTip(TextFormat::LIGHT_PURPLE . "DOUGH ROLL CRASH!");

        $lv = $player->getLevel();
        if ($lv !== null) {
            $lv->addSound(new AnvilUseSound(new Vector3($player->x, $player->y, $player->z)));
            self::spawnRollAura($lv, $player->x, $player->y, $player->z);
        }

        Server::getInstance()->getScheduler()->scheduleRepeatingTask(
            new class($player, $range, $trapDmg, $smashDmg, $plugin, $fruit) extends Task {

                private $player, $range, $trapDmg, $smashDmg, $plugin, $fruit;
                private $tick        = 0;
                private $phase       = 0;
                private $trapped     = null;
                private $done        = false;
                private $rollRing    = [];
                private $trapShell   = [];
                private $swingMass   = [];

                const PHASE_ROLL     = 8;
                const PHASE_TRAP     = 20;
                const PHASE_SWING    = 52;
                const PHASE_SMASH    = 72;

                public function __construct($player, $range, $trapDmg, $smashDmg, $plugin, $fruit) {
                    $this->player   = $player;
                    $this->range    = $range;
                    $this->trapDmg  = $trapDmg;
                    $this->smashDmg = $smashDmg;
                    $this->plugin   = $plugin;
                    $this->fruit    = $fruit;
                }

                public function onRun($currentTick) {
                    if ($this->done) {
                        Server::getInstance()->getScheduler()->cancelTask($this->getTaskId());
                        return;
                    }

                    $attacker = $this->player;
                    if (!$attacker->isOnline()) {
                        Server::getInstance()->getScheduler()->cancelTask($this->getTaskId());
                        return;
                    }

                    $this->tick++;
                    $lv = $attacker->getLevel();

                    if ($this->tick <= self::PHASE_ROLL) {
                        $this->doRollPhase($attacker, $lv);

                    } elseif ($this->tick <= self::PHASE_TRAP) {
                        if ($this->tick === self::PHASE_ROLL + 1) {
                            $this->doTrap($attacker, $lv);
                        }
                        $this->doTrapHoldVFX($attacker, $lv);

                    } elseif ($this->tick <= self::PHASE_SWING) {
                        $this->doSwingPhase($attacker, $lv);

                    } elseif ($this->tick <= self::PHASE_SMASH) {
                        if ($this->tick === self::PHASE_SWING + 1) {
                            $this->doSmash($attacker, $lv);
                        }
                        $this->doSmashVFX($attacker, $lv);

                    } else {
                        $this->done = true;
                        $this->fruit->grantMasteryExpPublic($attacker);
                        Server::getInstance()->getScheduler()->cancelTask($this->getTaskId());
                    }
                }

                private function doRollPhase(Player $attacker, $lv) {
                    $t = $this->tick;
                    $ax = $attacker->x;
                    $ay = $attacker->y;
                    $az = $attacker->z;

                    if ($t === 1) {
                        $this->rollRing = MochiMochi::spawnBlockRing($lv, $ax, $ay + 0.9, $az, 1.8, 16, MochiMochi::getMochiBlocks());
                    }

                    foreach ($this->rollRing as $eid => $d) {
                        $angle = $d["angle"] + $t * 0.42;
                        $y = $ay + 0.9 + sin($angle * 2) * 0.25;
                        BlockEffects::sendMove($lv, $eid, $ax + cos($angle) * 1.8, $y, $az + sin($angle) * 1.8, $t * 25, $t * 15);
                    }

                    $pts = 16;
                    $r   = 1.4;
                    for ($i = 0; $i < $pts; $i++) {
                        $a = ($i / $pts) * M_PI * 2 + $t * 0.6;
                        $lv->addParticle(new DustParticle(
                            new Vector3(
                                $ax + cos($a) * $r,
                                $ay + 0.5 + sin($a * 2) * 0.4,
                                $az + sin($a) * $r
                            ),
                            155, 100, 200
                        ));
                    }

                    $inner = 8;
                    for ($i = 0; $i < $inner; $i++) {
                        $a = ($i / $inner) * M_PI * 2 - $t * 0.8;
                        $lv->addParticle(new DustParticle(
                            new Vector3(
                                $ax + cos($a) * 0.7,
                                $ay + 0.8,
                                $az + sin($a) * 0.7
                            ),
                            230, 230, 255
                        ));
                    }

                    if ($t % 2 === 0) {
                        $lv->addParticle(new SmokeParticle(
                            new Vector3(
                                $ax + (mt_rand(-8, 8) / 10),
                                $ay + 0.3,
                                $az + (mt_rand(-8, 8) / 10)
                            )
                        ));
                    }
                }

                private function doTrap(Player $attacker, $lv) {
                    $target = $this->fruit->findNearestPublic($attacker, $this->range);
                    if ($target === null) return;

                    if ($target instanceof Player && !BaseFruit::pvpAllowed($attacker, $target)) return;

                    $this->trapped = $target;

                    $this->plugin->setAbilityDamage($attacker->getName(), $this->trapDmg);
                    $ev = new EntityDamageByEntityEvent(
                        $attacker, $target,
                        EntityDamageEvent::CAUSE_ENTITY_ATTACK,
                        $this->trapDmg
                    );
                    $target->attack($this->trapDmg, $ev);

                    $slow = Effect::getEffect(Effect::SLOWNESS);
                    $slow->setAmplifier(10);
                    $slow->setDuration(50);
                    $slow->setVisible(false);
                    BaseFruit::staticSafeAddEffect($attacker, $target, $slow);

                    BaseFruit::staticSafeSetMotion($attacker, $target, new Vector3(0, 0.1, 0));

                    $tx = $target->x;
                    $ty = $target->y;
                    $tz = $target->z;
                    $this->trapShell = MochiMochi::spawnTrapShell($lv, $tx, $ty, $tz, MochiMochi::getMochiBlocks());

                    $spikes = 12;
                    for ($i = 0; $i < $spikes; $i++) {
                        $a = ($i / $spikes) * M_PI * 2;
                        $r = 0.8 + mt_rand(0, 5) / 10.0;
                        $lv->addParticle(new DustParticle(
                            new Vector3(
                                $tx + cos($a) * $r,
                                $ty + 0.5 + mt_rand(0, 15) / 10.0,
                                $tz + sin($a) * $r
                            ),
                            200, 180, 255
                        ));
                    }
                    $lv->addParticle(new ExplodeParticle(new Vector3($tx, $ty + 1, $tz)));
                    $lv->addSound(new PopSound(new Vector3($tx, $ty, $tz)));
                    $lv->addSound(new AnvilUseSound(new Vector3($tx, $ty, $tz)));

                    if ($target instanceof Player) {
                        $target->sendTip(TextFormat::LIGHT_PURPLE . "Trapped in dough!");
                    }
                    $attacker->sendTip(TextFormat::LIGHT_PURPLE . "DOUGH ROLL CRASH! Swinging...");
                }

                private function doTrapHoldVFX(Player $attacker, $lv) {
                    if ($this->trapped === null || $this->trapped->closed) return;
                    $t  = $this->tick;
                    $tx = $this->trapped->x;
                    $ty = $this->trapped->y;
                    $tz = $this->trapped->z;

                    foreach ($this->trapShell as $eid => $d) {
                        $spin = $t * 0.18;
                        $ox = $d["ox"];
                        $oz = $d["oz"];
                        $rx = $ox * cos($spin) - $oz * sin($spin);
                        $rz = $ox * sin($spin) + $oz * cos($spin);
                        BlockEffects::sendMove($lv, $eid, $tx + $rx, $ty + $d["oy"], $tz + $rz, $t * 20, $t * 10);
                    }

                    $pts = 10;
                    for ($i = 0; $i < $pts; $i++) {
                        $a = ($i / $pts) * M_PI * 2 + $t * 0.4;
                        $lv->addParticle(new DustParticle(
                            new Vector3(
                                $tx + cos($a) * 0.9,
                                $ty + 1.0,
                                $tz + sin($a) * 0.9
                            ),
                            180, 140, 230
                        ));
                    }

                    BaseFruit::staticSafeSetMotion($attacker, $this->trapped, new Vector3(0, 0.05, 0));
                }

                private function doSwingPhase(Player $attacker, $lv) {
                    $t  = $this->tick - self::PHASE_TRAP;
                    $ax = $attacker->x;
                    $ay = $attacker->y;
                    $az = $attacker->z;
                    $swingRadius = 4.0;
                    $swingAngle  = $t * 0.28;

                    $sx = $ax + cos($swingAngle) * $swingRadius;
                    $sz = $az + sin($swingAngle) * $swingRadius;
                    $sy = $ay + 2.5 + sin($swingAngle * 0.5) * 1.5;

                    if ($t === 1) {
                        $this->swingMass = MochiMochi::spawnSwingMass($lv, $sx, $sy, $sz, MochiMochi::getMochiBlocks());
                    }

                    foreach ($this->swingMass as $eid => $d) {
                        $rot = $t * 0.22;
                        $ox = $d["ox"];
                        $oz = $d["oz"];
                        $rx = $ox * cos($rot) - $oz * sin($rot);
                        $rz = $ox * sin($rot) + $oz * cos($rot);
                        BlockEffects::sendMove($lv, $eid, $sx + $rx, $sy + $d["oy"], $sz + $rz, $t * 25, $t * 20);
                    }

                    $outerPts = 14;
                    for ($i = 0; $i < $outerPts; $i++) {
                        $a = ($i / $outerPts) * M_PI * 2 + $t * 0.5;
                        $lv->addParticle(new DustParticle(
                            new Vector3(
                                $sx + cos($a) * 1.0,
                                $sy + sin($a) * 0.4,
                                $sz + sin($a) * 1.0
                            ),
                            220, 200, 255
                        ));
                    }

                    $lv->addParticle(new DustParticle(
                        new Vector3($sx, $sy, $sz),
                        255, 80, 100
                    ));

                    if ($t % 3 === 0) {
                        $lv->addParticle(new CriticalParticle(
                            new Vector3(
                                $sx + (mt_rand(-5, 5) / 10),
                                $sy + (mt_rand(0, 8) / 10),
                                $sz + (mt_rand(-5, 5) / 10)
                            )
                        ));
                    }

                    if ($this->trapped !== null && !$this->trapped->closed) {
                        BaseFruit::staticSafeSetMotion($attacker, $this->trapped, new Vector3(0, 0.1, 0));

                        $range = 5.5;
                        foreach ($attacker->getLevel()->getPlayers() as $other) {
                            if ($other->getName() === $attacker->getName()) continue;
                            if ($other->getName() === $this->trapped->getName()) continue;
                            if (!BaseFruit::pvpAllowed($attacker, $other)) continue;
                            if ($attacker->distance($other) > $range) continue;

                            $this->plugin->setAbilityDamage($attacker->getName(), $this->trapDmg * 0.6);
                            $ev2 = new EntityDamageByEntityEvent(
                                $attacker, $other,
                                EntityDamageEvent::CAUSE_ENTITY_ATTACK,
                                $this->trapDmg * 0.6
                            );
                            $other->attack($this->trapDmg * 0.6, $ev2);
                            BaseFruit::staticSafeSetMotion($attacker, $other,
                                new Vector3(
                                    (mt_rand(-5, 5) / 10) * 1.2,
                                    0.5,
                                    (mt_rand(-5, 5) / 10) * 1.2
                                )
                            );
                        }

                        $npcClass     = "OnePiece\\NPC\\NPCEntity";
                        $factoryClass = "OnePieceTrades\\Factory\\FactoryEntity";
                        $sharkClass   = "OnePiece\\SeaEvent\\SeaSharkEntity";
                        $beastClass   = "OnePiece\\SeaEvent\\SeaBeastEntity";
                        foreach ($attacker->getLevel()->getEntities() as $entity) {
                            if ($entity instanceof Player) continue;
                            if ($entity->closed || !$entity->isAlive()) continue;
                            if (!($entity instanceof $npcClass) && !($entity instanceof $factoryClass)
                                && !($entity instanceof $sharkClass) && !($entity instanceof $beastClass)) continue;
                            if ($attacker->distance($entity) > $range) continue;
                            $this->plugin->setAbilityDamage($attacker->getName(), $this->trapDmg * 0.6);
                            $ev2 = new EntityDamageByEntityEvent(
                                $attacker, $entity,
                                EntityDamageEvent::CAUSE_ENTITY_ATTACK,
                                $this->trapDmg * 0.6
                            );
                            $entity->attack($this->trapDmg * 0.6, $ev2);
                        }
                    }
                }

                private function doSmash(Player $attacker, $lv) {
                    $ax  = $attacker->x;
                    $ay  = $attacker->y;
                    $az  = $attacker->z;
                    $aoe = 6.0;

                    $attacker->sendTip(TextFormat::LIGHT_PURPLE . "DOUGH ROLL CRASH! SMASH!");
                    $lv->addSound(new ExplodeSound(new Vector3($ax, $ay, $az)));
                    $lv->addSound(new AnvilUseSound(new Vector3($ax, $ay, $az)));

                    $blastPts = 24;
                    for ($i = 0; $i < $blastPts; $i++) {
                        $a = ($i / $blastPts) * M_PI * 2;
                        $r = 1.0 + mt_rand(0, 30) / 10.0;
                        $lv->addParticle(new DustParticle(
                            new Vector3(
                                $ax + cos($a) * $r,
                                $ay + 0.5,
                                $az + sin($a) * $r
                            ),
                            255, 255, 255
                        ));
                    }
                    $lv->addParticle(new LargeExplodeParticle(new Vector3($ax, $ay + 0.5, $az)));
                    $lv->addParticle(new ExplodeParticle(new Vector3($ax, $ay + 1.0, $az)));

                    $targets = [];
                    foreach ($attacker->getLevel()->getPlayers() as $t) {
                        if ($t->getName() === $attacker->getName()) continue;
                        if (!BaseFruit::pvpAllowed($attacker, $t)) continue;
                        if ($attacker->distance($t) <= $aoe) $targets[] = $t;
                    }
                    $npcClass     = "OnePiece\\NPC\\NPCEntity";
                    $factoryClass = "OnePieceTrades\\Factory\\FactoryEntity";
                    $sharkClass   = "OnePiece\\SeaEvent\\SeaSharkEntity";
                    $beastClass   = "OnePiece\\SeaEvent\\SeaBeastEntity";
                    foreach ($attacker->getLevel()->getEntities() as $entity) {
                        if ($entity instanceof Player) continue;
                        if ($entity->closed || !$entity->isAlive()) continue;
                        if (!($entity instanceof $npcClass) && !($entity instanceof $factoryClass)
                            && !($entity instanceof $sharkClass) && !($entity instanceof $beastClass)) continue;
                        if ($attacker->distance($entity) <= $aoe) $targets[] = $entity;
                    }

                    foreach ($targets as $t) {
                        $dist = $attacker->distance($t);
                        $scale = max(0.5, 1.0 - ($dist / $aoe) * 0.5);
                        $dmg = $this->smashDmg * $scale;

                        if ($t === $this->trapped) {
                            $dmg *= 1.4;
                        }

                        $this->plugin->setAbilityDamage($attacker->getName(), $dmg);
                        $ev = new EntityDamageByEntityEvent(
                            $attacker, $t,
                            EntityDamageEvent::CAUSE_ENTITY_ATTACK,
                            $dmg
                        );
                        $t->attack($dmg, $ev);

                        $dx = $t->x - $ax;
                        $dz = $t->z - $az;
                        $len = sqrt($dx * $dx + $dz * $dz);
                        $kbx = $len > 0 ? ($dx / $len) * 1.8 : 0;
                        $kbz = $len > 0 ? ($dz / $len) * 1.8 : 0;

                        BaseFruit::staticSafeSetMotion($attacker, $t, new Vector3($kbx, 0.9, $kbz));

                        if ($t instanceof Player) {
                            $t->sendTip(TextFormat::LIGHT_PURPLE . "DOUGH ROLL CRASH!");
                        }
                    }

                    BaseFruit::staticSafeSetMotion($attacker, $attacker, new Vector3(0, 0.7, 0));

                    BlockEffects::removeAll(array_keys($this->rollRing));
                    BlockEffects::removeAll(array_keys($this->trapShell));
                    BlockEffects::removeAll(array_keys($this->swingMass));

                    $debris = BlockEffects::spawnDebris(
                        $this->plugin, $lv, $ax, $ay, $az,
                        14, 0.35, 0.75, 24, MochiMochi::getMochiBlocks()
                    );
                    Server::getInstance()->getScheduler()->scheduleRepeatingTask(
                        new MochiDebrisTask($this->plugin, $lv, $debris, $ay),
                        1
                    );
                }

                private function doSmashVFX(Player $attacker, $lv) {
                    $t  = $this->tick - self::PHASE_SWING;
                    $ax = $attacker->x;
                    $ay = $attacker->y;
                    $az = $attacker->z;

                    $pts = 12;
                    $r   = 1.5 + $t * 0.25;
                    for ($i = 0; $i < $pts; $i++) {
                        $a = ($i / $pts) * M_PI * 2 + $t * 0.3;
                        $lv->addParticle(new DustParticle(
                            new Vector3(
                                $ax + cos($a) * $r,
                                $ay + 0.1,
                                $az + sin($a) * $r
                            ),
                            200, 160, 255
                        ));
                    }

                    if ($t % 3 === 0) {
                        $lv->addParticle(new SmokeParticle(
                            new Vector3(
                                $ax + (mt_rand(-15, 15) / 10),
                                $ay + 0.3,
                                $az + (mt_rand(-15, 15) / 10)
                            )
                        ));
                    }
                }
            }, 1
        );

        return $this->getMasteryCooldown($player, $this->getAbilityCooldowns()["ability1"]);
    }

    private function scorchingMochiWheel(Player $player) {
        $mult   = min(1.5, $this->getHakiMultiplier($player));
        $wheelDmg  = min(3.0, 1.5 * $mult);
        $slamDmg   = min(6.0, 5.5 * $mult);
        $plugin    = $this->plugin;
        $fruit     = $this;

        $player->sendTip(TextFormat::GOLD . "SCORCHING MOCHI WHEEL!");

        $lv = $player->getLevel();
        if ($lv !== null) {
            $lv->addSound(new AnvilUseSound(new Vector3($player->x, $player->y, $player->z)));
            $lv->addSound(new FizzSound(new Vector3($player->x, $player->y, $player->z)));
        }

Server::getInstance()->getScheduler()->scheduleRepeatingTask(
    new class($player, $wheelDmg, $slamDmg, $plugin, $fruit) extends Task {

        private $player, $wheelDmg, $slamDmg, $plugin, $fruit;
        private $tick = 0;
        private $done = false;
        private $armBlocks = [];
        private $caught = [];
        private $stunnedTargets = [];

        const SPIN_TICKS = 54;
        const LOOK_UP_TICKS = 74;
        const SLAM_TICK = 75;
        const ARM_LENGTH = 7;
        const STUN_RADIUS = 10.0;

        public function __construct($player, $wheelDmg, $slamDmg, $plugin, $fruit) {
            $this->player   = $player;
            $this->wheelDmg = $wheelDmg;
            $this->slamDmg  = $slamDmg;
            $this->plugin   = $plugin;
            $this->fruit    = $fruit;
        }

        public function onRun($currentTick) {
            if ($this->done) {
                Server::getInstance()->getScheduler()->cancelTask($this->getTaskId());
                return;
            }

            $attacker = $this->player;
            if (!$attacker->isOnline()) {
                $this->releaseAllStunned();
                $this->cleanup();
                Server::getInstance()->getScheduler()->cancelTask($this->getTaskId());
                return;
            }

            $lv = $attacker->getLevel();
            if ($lv === null) {
                $this->releaseAllStunned();
                $this->cleanup();
                Server::getInstance()->getScheduler()->cancelTask($this->getTaskId());
                return;
            }

            $this->tick++;

            if ($this->tick < self::SLAM_TICK) {
                $this->collectStunTargets($attacker, $lv);
                $this->applyStunToAll($attacker);
            }

            if ($this->tick === 1) {
                $this->spawnArm($attacker, $lv);
            }

            if ($this->tick <= self::SPIN_TICKS) {
                $this->updateArmSpin($attacker, $lv);
                $this->damageArmReach($attacker, $lv);
                $this->applyStunToAll($attacker);
            } elseif ($this->tick <= self::LOOK_UP_TICKS) {
                $this->updateArmLookUp($attacker, $lv);
                $this->applyStunToAll($attacker);
            } elseif ($this->tick === self::SLAM_TICK) {
                $this->releaseAllStunned();
                $this->doFinalSlam($attacker, $lv);
                $this->cleanup();
                $this->done = true;
                $this->fruit->grantMasteryExpPublic($attacker);
                Server::getInstance()->getScheduler()->cancelTask($this->getTaskId());
            }
        }

        private function collectStunTargets(Player $attacker, Level $lv) {
            $this->addStunnedTarget($attacker);

            foreach ($lv->getPlayers() as $t) {
                if ($t->getName() === $attacker->getName()) continue;
                if (!BaseFruit::pvpAllowed($attacker, $t)) continue;
                if ($attacker->distance($t) > self::STUN_RADIUS) continue;
                $this->addStunnedTarget($t);
            }

            $npcClass     = "OnePiece\\NPC\\NPCEntity";
            $factoryClass = "OnePieceTrades\\Factory\\FactoryEntity";
            $sharkClass   = "OnePiece\\SeaEvent\\SeaSharkEntity";
            $beastClass   = "OnePiece\\SeaEvent\\SeaBeastEntity";

            foreach ($lv->getEntities() as $entity) {
                if ($entity instanceof Player) continue;
                if ($entity->closed || !$entity->isAlive()) continue;
                if (!($entity instanceof $npcClass) && !($entity instanceof $factoryClass)
                    && !($entity instanceof $sharkClass) && !($entity instanceof $beastClass)) continue;
                if ($attacker->distance($entity) > self::STUN_RADIUS) continue;
                $this->addStunnedTarget($entity);
            }
        }

        private function addStunnedTarget($entity) {
            $eid = $entity->getId();
            if (isset($this->stunnedTargets[$eid])) return;

            $this->stunnedTargets[$eid] = [
                "entity" => $entity,
                "lockX" => $entity->x,
                "lockY" => $entity->y,
                "lockZ" => $entity->z
            ];

            if ($entity instanceof Player) {
                $slow = Effect::getEffect(Effect::SLOWNESS);
                $slow->setAmplifier(255);
                $slow->setDuration(10);
                $slow->setVisible(false);
                if ($entity === $this->player) {
                    $entity->addEffect($slow);
                } else {
                    BaseFruit::staticSafeAddEffect($this->player, $entity, $slow);
                }
            }
        }

        private function applyStunToAll(Player $attacker) {
            foreach ($this->stunnedTargets as $eid => $data) {
                $entity = $data["entity"];
                if ($entity === null || $entity->closed || !$entity->isAlive()) {
                    unset($this->stunnedTargets[$eid]);
                    continue;
                }

                if ($entity !== $attacker && $entity instanceof Player && !BaseFruit::pvpAllowed($attacker, $entity)) {
                    unset($this->stunnedTargets[$eid]);
                    continue;
                }

                if ($attacker->distance($entity) > self::STUN_RADIUS && $entity !== $attacker) {
                    unset($this->stunnedTargets[$eid]);
                    continue;
                }

                BaseFruit::staticSafeSetMotion($attacker, $entity, new Vector3(0, 0, 0));
                $entity->teleport(new Vector3($data["lockX"], $data["lockY"], $data["lockZ"]));

                if ($entity instanceof Player) {
                    $slow = Effect::getEffect(Effect::SLOWNESS);
                    $slow->setAmplifier(255);
                    $slow->setDuration(10);
                    $slow->setVisible(false);
                    if ($entity === $attacker) {
                        $entity->addEffect($slow);
                    } else {
                        BaseFruit::staticSafeAddEffect($attacker, $entity, $slow);
                    }
                }
            }
        }

        private function releaseAllStunned() {
            foreach ($this->stunnedTargets as $eid => $data) {
                $entity = $data["entity"];
                if ($entity === null || $entity->closed || !$entity->isAlive()) continue;
                if ($entity instanceof Player) {
                    $entity->removeEffect(Effect::SLOWNESS);
                }
            }
            $this->stunnedTargets = [];
        }

        private function spawnArm(Player $attacker, $lv) {
            $blocks = MochiMochi::getMochiBlocks();

            for ($i = 1; $i <= self::ARM_LENGTH; $i++) {
                $block = $blocks[($i - 1) % count($blocks)];
                $eid = BlockEffects::newEid();
                BlockEffects::sendSpawn($lv, $eid, $block["id"], $block["damage"], $attacker->x, $attacker->y + 1.2, $attacker->z);
                $this->armBlocks[$eid] = [
                    "eid" => $eid,
                    "segment" => $i
                ];
            }
        }

        private function updateArmSpin(Player $attacker, $lv) {
            $ax = $attacker->x;
            $ay = $attacker->y + 1.2;
            $az = $attacker->z;

            $spin = $this->tick * 0.38;
            $verticalWave = sin($this->tick * 0.12) * 0.12;

            foreach ($this->armBlocks as $eid => $data) {
                $segment = $data["segment"];
                $dist = $segment * 0.9;
                $angle = $spin - ($segment * 0.18);

                $x = $ax + cos($angle) * $dist;
                $z = $az + sin($angle) * $dist;
                $y = $ay + $verticalWave + sin($angle * 1.2) * 0.08;

                BlockEffects::sendMove($lv, $eid, $x, $y, $z, $this->tick * 30, $this->tick * 18);

                if ($segment >= 2) {
                    $lv->addParticle(new DustParticle(
                        new Vector3($x, $y, $z),
                        251, 162, 98
                    ));
                }
            }

            if ($this->tick % 3 === 0) {
                $lv->addSound(new FizzSound(new Vector3($ax, $ay, $az)));
            }
        }

        private function updateArmLookUp(Player $attacker, $lv) {
            $ax = $attacker->x;
            $ay = $attacker->y + 1.2;
            $az = $attacker->z;

            $t = $this->tick - self::SPIN_TICKS;
            $progress = min(1.0, $t / 5.0);
            $lastSpin = self::SPIN_TICKS * 0.38;

            foreach ($this->armBlocks as $eid => $data) {
                $segment = $data["segment"];
                $dist = $segment * 0.9;

                $fx = $ax;
                $fy = $ay + $dist;
                $fz = $az;

                $angle = $lastSpin - ($segment * 0.18);
                $sx = $ax + cos($angle) * $dist;
                $sz = $az + sin($angle) * $dist;
                $sy = $ay + sin(self::SPIN_TICKS * 0.12) * 0.12 + sin($angle * 1.2) * 0.08;

                $x = $sx + ($fx - $sx) * $progress;
                $y = $sy + ($fy - $sy) * $progress;
                $z = $sz + ($fz - $sz) * $progress;

                BlockEffects::sendMove($lv, $eid, $x, $y, $z, 0, 0);

                if ($t % 2 === 0) {
                    $lv->addParticle(new DustParticle(new Vector3($x, $y, $z), 255, 100, 100));
                }
            }
        }

        private function damageArmReach(Player $attacker, $lv) {
            $ax = $attacker->x;
            $ay = $attacker->y + 1.2;
            $az = $attacker->z;

            $spin = $this->tick * 0.38;
            $verticalWave = sin($this->tick * 0.12) * 0.12;

            $hitPositions = [];
            foreach ($this->armBlocks as $eid => $data) {
                $segment = $data["segment"];
                $dist = $segment * 0.9;
                $angle = $spin - ($segment * 0.18);

                $x = $ax + cos($angle) * $dist;
                $z = $az + sin($angle) * $dist;
                $y = $ay + $verticalWave + sin($angle * 1.2) * 0.08;

                $hitPositions[] = new Vector3($x, $y, $z);
            }

            foreach ($attacker->getLevel()->getPlayers() as $t) {
                if ($t->getName() === $attacker->getName()) continue;
                if (!BaseFruit::pvpAllowed($attacker, $t)) continue;

                foreach ($hitPositions as $pos) {
                    if ($t->distance($pos) <= 1.35) {
                        $this->plugin->setAbilityDamage($attacker->getName(), $this->wheelDmg);
                        $ev = new EntityDamageByEntityEvent(
                            $attacker, $t,
                            EntityDamageEvent::CAUSE_ENTITY_ATTACK,
                            $this->wheelDmg
                        );
                        $t->attack($this->wheelDmg, $ev);
                        BaseFruit::staticSafeSetOnFire($attacker, $t, 3);
                        $this->caught[strtolower($t->getName())] = $t;
                        $this->addStunnedTarget($t);
                        break;
                    }
                }
            }

            $npcClass     = "OnePiece\\NPC\\NPCEntity";
            $factoryClass = "OnePieceTrades\\Factory\\FactoryEntity";
            $sharkClass   = "OnePiece\\SeaEvent\\SeaSharkEntity";
            $beastClass   = "OnePiece\\SeaEvent\\SeaBeastEntity";

            foreach ($attacker->getLevel()->getEntities() as $entity) {
                if ($entity instanceof Player) continue;
                if ($entity->closed || !$entity->isAlive()) continue;
                if (!($entity instanceof $npcClass) && !($entity instanceof $factoryClass)
                    && !($entity instanceof $sharkClass) && !($entity instanceof $beastClass)) continue;

                foreach ($hitPositions as $pos) {
                    if ($entity->distance($pos) <= 1.35) {
                        $this->plugin->setAbilityDamage($attacker->getName(), $this->wheelDmg);
                        $ev2 = new EntityDamageByEntityEvent(
                            $attacker, $entity,
                            EntityDamageEvent::CAUSE_ENTITY_ATTACK,
                            $this->wheelDmg
                        );
                        $entity->attack($this->wheelDmg, $ev2);
                        $entity->setOnFire(3);
                        $this->caught[spl_object_hash($entity)] = $entity;
                        $this->addStunnedTarget($entity);
                        break;
                    }
                }
            }
        }

        private function doFinalSlam(Player $attacker, $lv) {
            $ax  = $attacker->x;
            $ay  = $attacker->y;
            $az  = $attacker->z;
            $aoe = self::STUN_RADIUS;

            $attacker->sendTip(TextFormat::RED . "SCORCHING MOCHI WHEEL! CONQUEROR SLAM!");
            $lv->addSound(new ExplodeSound(new Vector3($ax, $ay, $az)));
            $lv->addSound(new AnvilUseSound(new Vector3($ax, $ay, $az)));

            $bolts = 7;
            for ($b = 0; $b < $bolts; $b++) {
                $angle = ($b / $bolts) * M_PI * 2 + (mt_rand(-15, 15) / 10.0);
                $hx = $ax;
                $hy = $ay + 0.5;
                $hz = $az;
                for ($step = 0; $step < 10; $step++) {
                    $hx += cos($angle) * (1.2 + (mt_rand(-3, 3) / 10.0));
                    $hz += sin($angle) * (1.2 + (mt_rand(-3, 3) / 10.0));
                    $hy += (mt_rand(-3, 3) / 10.0);
                    $angle += (mt_rand(-8, 8) / 10.0);

                    for ($p = 0; $p < 4; $p++) {
                        $lv->addParticle(new DustParticle(
                            new Vector3($hx + (mt_rand(-3, 3) / 10.0), $hy + (mt_rand(-3, 3) / 10.0), $hz + (mt_rand(-3, 3) / 10.0)),
                            255, 0, 0
                        ));
                    }
                    $lv->addParticle(new FlameParticle(new Vector3($hx, $hy, $hz)));
                }
            }

            $lv->addParticle(new LargeExplodeParticle(new Vector3($ax, $ay + 0.5, $az)));
            $lv->addParticle(new ExplodeParticle(new Vector3($ax, $ay + 1.2, $az)));

            foreach ($attacker->getLevel()->getPlayers() as $t) {
                if ($t->getName() === $attacker->getName()) continue;
                if (!BaseFruit::pvpAllowed($attacker, $t)) continue;
                if ($attacker->distance($t) > $aoe) continue;

                $dist = $attacker->distance($t);
                $scale = max(0.5, 1.0 - ($dist / $aoe) * 0.45);
                $dmg = $this->slamDmg * $scale;

                $this->plugin->setAbilityDamage($attacker->getName(), $dmg);
                $ev = new EntityDamageByEntityEvent(
                    $attacker, $t,
                    EntityDamageEvent::CAUSE_ENTITY_ATTACK,
                    $dmg
                );
                $t->attack($dmg, $ev);
                BaseFruit::staticSafeSetOnFire($attacker, $t, 6);

                $dx = $t->x - $ax;
                $dz = $t->z - $az;
                $len = sqrt($dx * $dx + $dz * $dz);
                $kbx = $len > 0 ? ($dx / $len) * 2.2 : 0;
                $kbz = $len > 0 ? ($dz / $len) * 2.2 : 0;
                BaseFruit::staticSafeSetMotion($attacker, $t, new Vector3($kbx, 1.0, $kbz));
            }

            $npcClass     = "OnePiece\\NPC\\NPCEntity";
            $factoryClass = "OnePieceTrades\\Factory\\FactoryEntity";
            $sharkClass   = "OnePiece\\SeaEvent\\SeaSharkEntity";
            $beastClass   = "OnePiece\\SeaEvent\\SeaBeastEntity";

            foreach ($attacker->getLevel()->getEntities() as $entity) {
                if ($entity instanceof Player) continue;
                if ($entity->closed || !$entity->isAlive()) continue;
                if (!($entity instanceof $npcClass) && !($entity instanceof $factoryClass)
                    && !($entity instanceof $sharkClass) && !($entity instanceof $beastClass)) continue;
                if ($attacker->distance($entity) > $aoe) continue;

                $dist = $attacker->distance($entity);
                $scale = max(0.5, 1.0 - ($dist / $aoe) * 0.45);
                $dmg = $this->slamDmg * $scale;

                $this->plugin->setAbilityDamage($attacker->getName(), $dmg);
                $ev2 = new EntityDamageByEntityEvent(
                    $attacker, $entity,
                    EntityDamageEvent::CAUSE_ENTITY_ATTACK,
                    $dmg
                );
                $entity->attack($dmg, $ev2);
                $entity->setOnFire(6);

                $dx = $entity->x - $ax;
                $dz = $entity->z - $az;
                $len = sqrt($dx * $dx + $dz * $dz);
                $kbx = $len > 0 ? ($dx / $len) * 2.2 : 0;
                $kbz = $len > 0 ? ($dz / $len) * 2.2 : 0;
                BaseFruit::staticSafeSetMotion($attacker, $entity, new Vector3($kbx, 1.0, $kbz));
            }

            $debris = BlockEffects::spawnDebris(
                $this->plugin, $lv, $ax, $ay, $az,
                16, 0.4, 0.75, 26, MochiMochi::getMochiBlocks()
            );
            Server::getInstance()->getScheduler()->scheduleRepeatingTask(
                new MochiDebrisTask($this->plugin, $lv, $debris, $ay),
                1
            );
        }

        private function cleanup() {
            $this->releaseAllStunned();
            BlockEffects::removeAll(array_keys($this->armBlocks));
            $this->armBlocks = [];
            $this->caught = [];
        }
    }, 1
);

        return $this->getMasteryCooldown($player, $this->getAbilityCooldowns()["ability2"]);
    }

    public static function spawnRollAura($lv, $ax, $ay, $az) {
        $pts = 16;
        for ($i = 0; $i < $pts; $i++) {
            $a = ($i / $pts) * M_PI * 2;
            $r = 1.6;
            $lv->addParticle(new DustParticle(
                new Vector3(
                    $ax + cos($a) * $r,
                    $ay + 0.5,
                    $az + sin($a) * $r
                ),
                155, 100, 200
            ));
        }
        $lv->addParticle(new ExplodeParticle(new Vector3($ax, $ay + 0.5, $az)));
        $ring = self::spawnBlockRing($lv, $ax, $ay + 0.8, $az, 1.7, 14, self::getMochiBlocks());
        Server::getInstance()->getScheduler()->scheduleRepeatingTask(
            new MochiRingTask($lv, $ring, $ax, $ay + 0.8, $az, 1.7, 8, 0.55),
            1
        );
    }

    public function findNearestPublic(Player $player, $range) {
        return $this->findNearestTarget($player, $range);
    }

    public function grantMasteryExpPublic(Player $player) {
        $this->grantMasteryExp($player);
    }

    public static function getMochiBlocks() {
        return [
            ["id" => 155, "damage" => 0],
            ["id" => 42, "damage" => 0],
            ["id" => 35, "damage" => 0]
        ];
    }

    public static function spawnBlockRing(Level $level, $cx, $cy, $cz, $radius, $points, array $blocks) {
        $ring = [];
        for ($i = 0; $i < $points; $i++) {
            $angle = ($i / $points) * M_PI * 2;
            $block = $blocks[$i % count($blocks)];
            $eid = BlockEffects::newEid();
            $x = $cx + cos($angle) * $radius;
            $z = $cz + sin($angle) * $radius;
            BlockEffects::sendSpawn($level, $eid, $block["id"], $block["damage"], $x, $cy, $z);
            $ring[$eid] = [
                "eid" => $eid,
                "angle" => $angle,
                "radius" => $radius,
                "y" => $cy,
                "block" => $block
            ];
        }
        return $ring;
    }

    public static function spawnLayeredWheel(Level $level, $cx, $cy, $cz, $radius, array $blocks) {
        $all = [];
        $layers = [
            ["r" => $radius, "y" => $cy],
            ["r" => $radius - 0.55, "y" => $cy + 0.7],
            ["r" => $radius - 1.0, "y" => $cy + 1.4]
        ];
        foreach ($layers as $layerIndex => $layer) {
            $points = $layerIndex === 0 ? 16 : 12;
            for ($i = 0; $i < $points; $i++) {
                $angle = ($i / $points) * M_PI * 2;
                $block = $blocks[($i + $layerIndex) % count($blocks)];
                $eid = BlockEffects::newEid();
                $x = $cx + cos($angle) * $layer["r"];
                $z = $cz + sin($angle) * $layer["r"];
                BlockEffects::sendSpawn($level, $eid, $block["id"], $block["damage"], $x, $layer["y"], $z);
                $all[$eid] = [
                    "eid" => $eid,
                    "angle" => $angle,
                    "radius" => $layer["r"],
                    "yOffset" => $layer["y"] - $cy
                ];
            }
        }
        return $all;
    }

    public static function spawnTrapShell(Level $level, $cx, $cy, $cz, array $blocks) {
        $shell = [];
        $points = [
            [0.0, 0.2, 0.0],
            [0.8, 0.3, 0.0],
            [-0.8, 0.3, 0.0],
            [0.0, 0.3, 0.8],
            [0.0, 0.3, -0.8],
            [0.65, 1.0, 0.65],
            [-0.65, 1.0, 0.65],
            [0.65, 1.0, -0.65],
            [-0.65, 1.0, -0.65],
            [0.0, 1.5, 0.0]
        ];
        foreach ($points as $i => $p) {
            $block = $blocks[$i % count($blocks)];
            $eid = BlockEffects::newEid();
            BlockEffects::sendSpawn($level, $eid, $block["id"], $block["damage"], $cx + $p[0], $cy + $p[1], $cz + $p[2]);
            $shell[$eid] = [
                "eid" => $eid,
                "ox" => $p[0],
                "oy" => $p[1],
                "oz" => $p[2]
            ];
        }
        return $shell;
    }

    public static function spawnSwingMass(Level $level, $cx, $cy, $cz, array $blocks) {
        $mass = [];
        $points = [
            [0.0, 0.0, 0.0],
            [0.8, 0.0, 0.0],
            [-0.8, 0.0, 0.0],
            [0.0, 0.0, 0.8],
            [0.0, 0.0, -0.8],
            [0.5, 0.8, 0.5],
            [-0.5, 0.8, 0.5],
            [0.5, 0.8, -0.5],
            [-0.5, 0.8, -0.5]
        ];
        foreach ($points as $i => $p) {
            $block = $blocks[$i % count($blocks)];
            $eid = BlockEffects::newEid();
            BlockEffects::sendSpawn($level, $eid, $block["id"], $block["damage"], $cx + $p[0], $cy + $p[1], $cz + $p[2]);
            $mass[$eid] = [
                "eid" => $eid,
                "ox" => $p[0],
                "oy" => $p[1],
                "oz" => $p[2]
            ];
        }
        return $mass;
    }

    public function onEquip(Player $player) {
        $player->sendMessage(TextFormat::LIGHT_PURPLE . "=== Mochi-Mochi no Mi ===");
        $player->sendMessage(TextFormat::WHITE . "Katakuri's unstoppable sticky dough power");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::LIGHT_PURPLE . "[Tap]: " . TextFormat::WHITE . "Dough Roll Crash");
        $player->sendMessage(TextFormat::GRAY . "  Roll in, trap enemy, swing them into the ground");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::RED . "[Sneak+Tap]: " . TextFormat::WHITE . "Scorching Mochi Wheel");
        $player->sendMessage(TextFormat::GRAY . "  Flaming dough wheel, conqueror slam, extreme AOE stun");
        $player->sendMessage(TextFormat::LIGHT_PURPLE . "=========================");
    }

    public function onUnequip(Player $player) {
        $player->sendMessage(TextFormat::GRAY . "The dough retracts...");
    }
}

class MochiDebrisTask extends Task {

    private $plugin;
    private $level;
    private $debris;
    private $groundY;
    private $tick    = 0;
    private $maxTicks = 28;
    private $cleaned = false;

    public function __construct($plugin, Level $level, array $debris, $groundY) {
        $this->plugin   = $plugin;
        $this->level    = $level;
        $this->debris   = $debris;
        $this->groundY  = $groundY - 0.5;
    }

    public function onRun($currentTick) {
        if ($this->cleaned) return;
        $this->tick++;

        if ($this->tick > $this->maxTicks || empty($this->debris)) {
            $this->cleanup();
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
            return;
        }

        foreach ($this->debris as $eid => $d) {
            if ($this->tick % 2 === 0) {
                $this->level->addParticle(new DustParticle(
                    new \pocketmine\math\Vector3($d["x"], $d["y"] + 0.1, $d["z"]),
                    180, 140, 230
                ));
            }
        }

        $toRemove = BlockEffects::tickDebris($this->debris, $this->level, $this->groundY, 0.055, 0.97);
        foreach ($toRemove as $eid) {
            unset($this->debris[$eid]);
        }
    }

    private function cleanup() {
        if ($this->cleaned) return;
        $this->cleaned = true;
        BlockEffects::voidAndRemove($this->plugin, $this->level, array_keys($this->debris));
        $this->debris = [];
    }
}

class MochiRingTask extends Task {

    private $level;
    private $ring;
    private $cx;
    private $cy;
    private $cz;
    private $radius;
    private $maxTicks;
    private $speed;
    private $tick = 0;

    public function __construct(Level $level, array $ring, $cx, $cy, $cz, $radius, $maxTicks, $speed) {
        $this->level = $level;
        $this->ring = $ring;
        $this->cx = $cx;
        $this->cy = $cy;
        $this->cz = $cz;
        $this->radius = $radius;
        $this->maxTicks = $maxTicks;
        $this->speed = $speed;
    }

    public function onRun($currentTick) {
        $this->tick++;
        if ($this->tick > $this->maxTicks || empty($this->ring)) {
            BlockEffects::removeAll(array_keys($this->ring));
            Server::getInstance()->getScheduler()->cancelTask($this->getTaskId());
            return;
        }

        foreach ($this->ring as $eid => $d) {
            $angle = $d["angle"] + $this->tick * $this->speed;
            $y = $this->cy + sin($angle * 2) * 0.25;
            BlockEffects::sendMove($this->level, $eid, $this->cx + cos($angle) * $this->radius, $y, $this->cz + sin($angle) * $this->radius, $this->tick * 20, $this->tick * 14);
        }
    }
}
?>