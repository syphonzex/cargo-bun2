<?php
namespace OnePiece\Devil\fruits;

use OnePiece\Devil\BaseFruit;
use OnePiece\Devil\Main;
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
use pocketmine\level\particle\FlameParticle;
use pocketmine\level\particle\SmokeParticle;
use pocketmine\level\particle\LavaParticle;
use pocketmine\level\particle\CriticalParticle;
use pocketmine\level\particle\ExplodeParticle;
use pocketmine\level\particle\HugeExplodeParticle;
use pocketmine\level\particle\DustParticle;
use pocketmine\level\sound\ExplodeSound;
use pocketmine\level\sound\BlazeShootSound;

class GearFourth extends BaseFruit {

    private $gatlingActive = [];
    public static $gatlingSuppressed = [];

    public function getId()          { return "gear_fourth"; }
    public function getDisplayName() { return "RubberV2"; }
    public function getDescription() { return "Boundman - Compressed rubber power, devastating in close range."; }
    public function getType()        { return "zoan"; }
    public function getRarity()      { return "legendary"; }

    public function getAbilityNames() {
        return ["ability1" => "Red Hawk", "ability2" => "Jet Gatling"];
    }
    public function getAbilityCooldowns() {
        return ["ability1" => 6.0, "ability2" => 18.0];
    }

    public function useAbility(Player $player, $ability) {
        switch ($ability) {
            case "ability1": return $this->redHawk($player);
            case "ability2": return $this->jetGatling($player);
        }
        return 0;
    }

    private function redHawk(Player $player) {
        if (!$this->checkMastery($player, "ability1")) return 0;

        $mult   = min(1.5, $this->getHakiMultiplier($player));
        $damage = 5.5 * $mult;
        $plugin = $this->plugin;
        $fruit  = $this;

        $player->sendTip(TextFormat::RED . "RED HAWK - Winding up...");
        $this->spawnRedHawkWindup($player);

        Server::getInstance()->getScheduler()->scheduleDelayedTask(
            new class($player, $damage, $plugin, $fruit) extends Task {
                private $player, $damage, $plugin, $fruit;
                public function __construct($p, $d, $pl, $fr) {
                    $this->player = $p; $this->damage = $d;
                    $this->plugin = $pl; $this->fruit = $fr;
                }
                public function onRun($currentTick) {
                    $player = $this->player;
                    if (!$player->isOnline()) return;
                    $dir = $player->getDirectionVector();
                    $player->setMotion(new Vector3($dir->x * 1.6, 0.3, $dir->z * 1.6));
                    $this->fruit->spawnRedHawkLunge($player);

                    Server::getInstance()->getScheduler()->scheduleDelayedTask(
                        new class($player, $this->damage, $this->plugin, $this->fruit, $dir) extends Task {
                            private $player, $damage, $plugin, $fruit, $dir;
                            public function __construct($p, $d, $pl, $fr, $dir) {
                                $this->player = $p; $this->damage = $d;
                                $this->plugin = $pl; $this->fruit = $fr;
                                $this->dir = $dir;
                            }
                            public function onRun($currentTick) {
                                $player = $this->player;
                                if (!$player->isOnline()) return;

                                $target = null; $bestDist = 6.0;
                                $start = $player->add(0, $player->getEyeHeight(), 0);
                                $dir   = $this->dir;

                                foreach ($player->getLevel()->getPlayers() as $t) {
                                    if ($t->getName() === $player->getName()) continue;
                                    $tp = $t->add(0, 1, 0); $dist = $start->distance($tp);
                                    if ($dist > 6.0 || $dist <= 0) continue;
                                    $to = $tp->subtract($start); $l = $dist;
                                    $dot = ($dir->x*$to->x + $dir->y*$to->y + $dir->z*$to->z) / $l;
                                    if ($dot > 0.30 && $dist < $bestDist) { $bestDist = $dist; $target = $t; }
                                }

                                if ($target === null) {
                                    foreach ($player->getLevel()->getEntities() as $e) {
                                        if ($e instanceof Player || $e->closed || !$e->isAlive()) continue;
                                        $npcC = "OnePiece\\NPC\\NPCEntity"; $facC = "OnePieceTrades\\Factory\\FactoryEntity";
                                        $shkC = "OnePiece\\SeaEvent\\SeaSharkEntity"; $bstC = "OnePiece\\SeaEvent\\SeaBeastEntity";
                                        if (!($e instanceof $npcC) && !($e instanceof $facC) && !($e instanceof $shkC) && !($e instanceof $bstC)) continue;
                                        $tp = $e->add(0, 1, 0); $dist = $start->distance($tp);
                                        if ($dist > 6.0 || $dist <= 0) continue;
                                        $to = $tp->subtract($start); $l = $dist;
                                        $dot = ($dir->x*$to->x + $dir->y*$to->y + $dir->z*$to->z) / $l;
                                        if ($dot > 0.30 && $dist < $bestDist) { $bestDist = $dist; $target = $e; }
                                    }
                                }

                                $this->fruit->spawnRedHawkImpact($player);

                                if ($target !== null) {
                                    if ($target instanceof Player && !BaseFruit::pvpAllowed($player, $target)) {
                                        $player->sendTip(TextFormat::RED . "RED HAWK! ..."); return;
                                    }
                                    $this->plugin->setAbilityDamage($player->getName(), $this->damage);
                                    $ev = new EntityDamageByEntityEvent($player, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->damage);
                                    $target->attack($this->damage, $ev);
                                    if ($target instanceof Player) {
                                         BaseFruit::staticSafeSetOnFire($player, $target, 4);
                                         BaseFruit::staticSafeSetMotion($player, $target, new Vector3($dir->x * 2.0, 0.7, $dir->z * 2.0));
                                        $target->sendTip(TextFormat::RED . "RED HAWK! Flame fist!");
                                    } else {
                                         BaseFruit::staticSafeSetOnFire($player, $target, 4);
                                         BaseFruit::staticSafeSetMotion($player, $target, new Vector3($dir->x * 2.2, 0.5, $dir->z * 2.2));
                                    }
                                    $player->sendTip(TextFormat::RED . "RED HAWK! GEAR FOUR!");

                                    $tx = $target->x;
                                    $ty = $target->y;
                                    $tz = $target->z;
                                    $level = $player->getLevel();
                                    $debris = BlockEffects::spawnDebris(
                                        $this->plugin, $level, $tx, $ty, $tz,
                                        5, 0.4, 0.8, 20,
                                        [["id" => 87, "damage" => 0], ["id" => 4, "damage" => 0]]
                                    );
                                    Server::getInstance()->getScheduler()->scheduleRepeatingTask(
                                        new RedHawkDebrisTask($this->plugin, $level, $debris, $ty),
                                        1
                                    );
                                } else {
                                    $player->sendTip(TextFormat::RED . "RED HAWK! ...");
                                }
                                $this->fruit->grantMasteryExpPublic($player);
                            }
                        }, 5
                    );
                }
            }, 8
        );

        return $this->getAbilityCooldowns()["ability1"];
    }

    public function spawnRedHawkWindup(Player $player) {
        $level = $player->getLevel();
        $pos = $player->getPosition();
        $px = $pos->x;
        $py = $pos->y + 1;
        $pz = $pos->z;

        for ($i = 0; $i < 8; $i++) {
            $a = ($i / 8) * M_PI * 2;
            $level->addParticle(new FlameParticle(new Vector3(
                $px + cos($a) * 0.6,
                $py + sin($a) * 0.3,
                $pz + sin($a) * 0.6
            )));
        }

        for ($i = 0; $i < 4; $i++) {
            $level->addParticle(new SmokeParticle(new Vector3(
                $px + (mt_rand(-5, 5) / 10),
                $py + (mt_rand(0, 10) / 10),
                $pz + (mt_rand(-5, 5) / 10)
            )));
        }

        for ($i = 0; $i < 6; $i++) {
            $a = ($i / 6) * M_PI * 2;
            $level->addParticle(new DustParticle(
                new Vector3($px + cos($a) * 0.8, $py, $pz + sin($a) * 0.8),
                255, 80, 0
            ));
        }

        $level->addSound(new BlazeShootSound($pos));
    }

    public function spawnRedHawkLunge(Player $player) {
        $level = $player->getLevel();
        $pos = $player->getPosition();
        $dir = $player->getDirectionVector();
        $px = $pos->x;
        $py = $pos->y + 1;
        $pz = $pos->z;

        for ($i = 0; $i < 6; $i++) {
            $t = $i / 6;
            $level->addParticle(new FlameParticle(new Vector3(
                $px + $dir->x * $t * 2,
                $py + $dir->y * $t * 2,
                $pz + $dir->z * $t * 2
            )));
        }

        for ($i = 0; $i < 4; $i++) {
            $a = ($i / 4) * M_PI * 2;
            $level->addParticle(new DustParticle(
                new Vector3($px + cos($a) * 0.4, $py, $pz + sin($a) * 0.4),
                255, 100, 0
            ));
        }
    }

    public function spawnRedHawkImpact(Player $player) {
        $level = $player->getLevel();
        $dir = $player->getDirectionVector();
        $px = $player->x + $dir->x * 1.5;
        $py = $player->y + 1.2;
        $pz = $player->z + $dir->z * 1.5;

        $level->addParticle(new HugeExplodeParticle(new Vector3($px, $py, $pz)));

        for ($ring = 0; $ring < 3; $ring++) {
            $r = 0.5 + $ring * 0.4;
            for ($i = 0; $i < 10; $i++) {
                $a = ($i / 10) * M_PI * 2;
                $level->addParticle(new FlameParticle(new Vector3(
                    $px + cos($a) * $r,
                    $py + $ring * 0.3,
                    $pz + sin($a) * $r
                )));
            }
        }

        for ($i = 0; $i < 12; $i++) {
            $a = mt_rand(0, 628) / 100;
            $d = mt_rand(3, 15) / 10;
            $level->addParticle(new FlameParticle(new Vector3(
                $px + cos($a) * $d,
                $py + mt_rand(0, 15) / 10,
                $pz + sin($a) * $d
            )));
        }

        for ($i = 0; $i < 6; $i++) {
            $level->addParticle(new SmokeParticle(new Vector3(
                $px + (mt_rand(-10, 10) / 10),
                $py + (mt_rand(5, 15) / 10),
                $pz + (mt_rand(-10, 10) / 10)
            )));
        }

        for ($i = 0; $i < 8; $i++) {
            $a = ($i / 8) * M_PI * 2;
            $level->addParticle(new DustParticle(
                new Vector3($px + cos($a) * 1.2, $py, $pz + sin($a) * 1.2),
                255, 60, 0
            ));
        }

        $level->addSound(new ExplodeSound(new Vector3($px, $py, $pz)));
    }

private function jetGatling(Player $player) {
    if (!$this->checkMastery($player, "ability2")) return 0;

    $name = $player->getName();
    if (isset($this->gatlingActive[$name])) return 0;

    $mult        = min(1.5, $this->getHakiMultiplier($player));
    $hitDamage   = 1.2 * $mult;
    $range       = 4.5;
    $totalPulses = 25;
    $interval    = 4;

    $this->gatlingActive[$name] = true;

    $plugin = $this->plugin;
    $fruit  = $this;

    $this->spawnJetGatlingStart($player);
    $player->sendTip(TextFormat::RED . "JET GATLING!");

    $shared = new \stdClass();
    $shared->stunnedTargets = [];

    $lockTask = new class($player, $fruit, $name, $shared) extends Task {
        private $player, $fruit, $playerName, $shared;
        public function __construct($player, $fruit, $name, $shared) {
            $this->player = $player;
            $this->fruit = $fruit;
            $this->playerName = $name;
            $this->shared = $shared;
        }
        public function onRun($currentTick) {
            if (!$this->player->isOnline() || !$this->fruit->isGatlingActive($this->playerName)) {
                Server::getInstance()->getScheduler()->cancelTask($this->getTaskId());
                return;
            }
            $this->player->setMotion(new Vector3(0, 0, 0));

            $dir = $this->player->getDirectionVector();
            $px = $this->player->x;
            $py = $this->player->y;
            $pz = $this->player->z;
            $lockDist = 2.5;

            $count = count($this->shared->stunnedTargets);
            $index = 0;

            foreach ($this->shared->stunnedTargets as $eid => $data) {
                $entity = $data["entity"];
                if ($entity === null || !$entity->isAlive() || $entity->closed) {
                    unset($this->shared->stunnedTargets[$eid]);
                    continue;
                }

                $spread = 0.0;
                if ($count > 1) {
                    $spread = (($index / ($count - 1)) - 0.5) * 1.2;
                }

                $perpX = -$dir->z * $spread;
                $perpZ = $dir->x * $spread;

                $lockX = $px + $dir->x * $lockDist + $perpX;
                $lockY = $py;
                $lockZ = $pz + $dir->z * $lockDist + $perpZ;

                $entity->teleport(new Vector3($lockX, $lockY, $lockZ));
                $entity->setMotion(new Vector3(0, 0, 0));

                $index++;
            }
        }
    };
    Server::getInstance()->getScheduler()->scheduleRepeatingTask($lockTask, 1);

    Server::getInstance()->getScheduler()->scheduleRepeatingTask(
        new class($player, $hitDamage, $range, $plugin, $fruit, $totalPulses, $name, $shared) extends Task {
            private $player, $hitDamage, $range, $plugin, $fruit;
            private $pulsesLeft, $playerName, $pulse = 0;
            private $shared;

            public function __construct($player, $hitDamage, $range, $plugin, $fruit, $totalPulses, $playerName, $shared) {
                $this->player = $player;
                $this->hitDamage = $hitDamage;
                $this->range = $range;
                $this->plugin = $plugin;
                $this->fruit = $fruit;
                $this->pulsesLeft = $totalPulses;
                $this->playerName = $playerName;
                $this->shared = $shared;
            }

            private function addStunned($entity) {
                $eid = $entity->getId();
                if (isset($this->shared->stunnedTargets[$eid])) return;

                $this->shared->stunnedTargets[$eid] = [
                    "entity" => $entity
                ];

                if ($entity instanceof Player) {
                    GearFourth::$gatlingSuppressed[strtolower($entity->getName())] = true;
                    $entity->sendTip(TextFormat::DARK_RED . TextFormat::BOLD . "STUNNED!");
                }
            }

            public function onRun($currentTick) {
                $player = $this->player;

                if (!$player->isOnline() || $this->pulsesLeft <= 0) {
                    $this->endGatling();
                    return;
                }

                $this->pulsesLeft--;
                $this->pulse++;

                $pos = $player->getPosition();
                $dir = $player->getDirectionVector();

                foreach ($player->getLevel()->getPlayers() as $t) {
                    if ($t->getName() === $player->getName()) continue;
                    if ($pos->distance($t->getPosition()) > $this->range) continue;

                    $to = $t->add(0, 1, 0)->subtract($pos->add(0, 1, 0));
                    $len = $to->length();
                    if ($len <= 0) continue;
                    if (($dir->x * $to->x + $dir->y * $to->y + $dir->z * $to->z) / $len < 0.25) continue;

                    if (!BaseFruit::pvpAllowed($player, $t)) continue;

                    $this->addStunned($t);

                    $this->plugin->setAbilityDamage($player->getName(), $this->hitDamage);
                    $ev = new EntityDamageByEntityEvent($player, $t, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->hitDamage);
                    $t->attack($this->hitDamage, $ev);

                    if ($this->pulse % 4 === 0) {
                        $t->sendTip(TextFormat::RED . "JET GATLING!");
                    }

                    $this->fruit->spawnJetGatlingImpact($t);
                }

                foreach ($player->getLevel()->getEntities() as $e) {
                    if ($e instanceof Player || $e->closed || !$e->isAlive()) continue;
                    $npcC = "OnePiece\\NPC\\NPCEntity";
                    $facC = "OnePieceTrades\\Factory\\FactoryEntity";
                    $shkC = "OnePiece\\SeaEvent\\SeaSharkEntity";
                    $bstC = "OnePiece\\SeaEvent\\SeaBeastEntity";
                    if (!($e instanceof $npcC) && !($e instanceof $facC) && !($e instanceof $shkC) && !($e instanceof $bstC)) continue;
                    if ($pos->distance($e->getPosition()) > $this->range) continue;

                    $to = $e->add(0, 1, 0)->subtract($pos->add(0, 1, 0));
                    $len = $to->length();
                    if ($len <= 0) continue;
                    if (($dir->x * $to->x + $dir->y * $to->y + $dir->z * $to->z) / $len < 0.25) continue;

                    $this->addStunned($e);

                    $this->plugin->setAbilityDamage($player->getName(), $this->hitDamage);
                    $ev = new EntityDamageByEntityEvent($player, $e, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->hitDamage);
                    $e->attack($this->hitDamage, $ev);

                    $this->fruit->spawnJetGatlingImpact($e);
                }

                $this->fruit->spawnJetGatlingPulse($player, $this->pulse);

                $total = $this->pulse + $this->pulsesLeft;
                $bars = $total > 0 ? (int)(($this->pulse / $total) * 10) : 10;
                $player->sendTip(TextFormat::RED . "JET GATLING! [" . str_repeat("|", $bars) . str_repeat(".", 10 - $bars) . "]");

                if ($this->pulsesLeft <= 0) {
                    $this->endGatling();
                }
            }

            private function endGatling() {
                $dir = $this->player->isOnline() ? $this->player->getDirectionVector() : new Vector3(0, 0.5, 0);

                foreach ($this->shared->stunnedTargets as $eid => $data) {
                    $entity = $data["entity"];
                    if ($entity === null || !$entity->isAlive() || $entity->closed) continue;

                    if ($entity instanceof Player) {
                        $entity->sendTip(TextFormat::GREEN . "Released!");
                        unset(GearFourth::$gatlingSuppressed[strtolower($entity->getName())]);
                    }

                    BaseFruit::staticSafeSetMotion($this->player, $entity, new Vector3($dir->x * 2.5, 0.8, $dir->z * 2.5));
                }
                $this->shared->stunnedTargets = [];
                $this->fruit->clearGatling($this->playerName);
                Server::getInstance()->getScheduler()->cancelTask($this->getTaskId());
            }
        }, $interval
    );

    $this->grantMasteryExpPublic($player);
    return $this->getAbilityCooldowns()["ability2"];
}

    public function spawnJetGatlingStart(Player $player) {
        $level = $player->getLevel();
        $pos = $player->getPosition();
        $px = $pos->x;
        $py = $pos->y + 1;
        $pz = $pos->z;

        for ($ring = 0; $ring < 2; $ring++) {
            $r = 0.8 + $ring * 0.5;
            for ($i = 0; $i < 12; $i++) {
                $a = ($i / 12) * M_PI * 2;
                $level->addParticle(new DustParticle(
                    new Vector3($px + cos($a) * $r, $py + $ring * 0.3, $pz + sin($a) * $r),
                    255, 50, 50
                ));
            }
        }

        $level->addParticle(new ExplodeParticle(new Vector3($px, $py, $pz)));
        $level->addSound(new BlazeShootSound($pos));
    }

    public function spawnJetGatlingPulse(Player $player, $pulse) {
        $level = $player->getLevel();
        $dir = $player->getDirectionVector();
        $px = $player->x + $dir->x * 1.0;
        $py = $player->y + 1.2;
        $pz = $player->z + $dir->z * 1.0;

        $side = ($pulse % 2 === 0) ? 1 : -1;
        $perpX = -$dir->z * 0.3 * $side;
        $perpZ = $dir->x * 0.3 * $side;

        for ($i = 0; $i < 3; $i++) {
            $t = $i / 3;
            $level->addParticle(new CriticalParticle(new Vector3(
                $px + $perpX + $dir->x * $t * 2,
                $py + (mt_rand(-3, 3) / 10),
                $pz + $perpZ + $dir->z * $t * 2
            )));
        }

        if ($pulse % 3 === 0) {
            $level->addParticle(new DustParticle(
                new Vector3($px + $perpX, $py, $pz + $perpZ),
                255, 100, 100
            ));
        }
    }

    public function spawnJetGatlingImpact($target) {
        $level = $target->getLevel();
        $tx = $target->x;
        $ty = $target->y + 1;
        $tz = $target->z;

        for ($i = 0; $i < 4; $i++) {
            $level->addParticle(new CriticalParticle(new Vector3(
                $tx + (mt_rand(-6, 6) / 10),
                $ty + (mt_rand(0, 10) / 10),
                $tz + (mt_rand(-6, 6) / 10)
            )));
        }

        $level->addParticle(new DustParticle(
            new Vector3($tx, $ty, $tz),
            255, 80, 80
        ));
    }

    public function clearGatling($name) {
        unset($this->gatlingActive[$name]);
    }

    public function isGatlingActive($name) {
        return isset($this->gatlingActive[$name]);
    }

    public function grantMasteryExpPublic(Player $player) {
        $this->grantMasteryExp($player);
    }

    public function onEquip(Player $player) {
        $player->sendMessage(TextFormat::RED . "GEAR FOURTH - BOUNDMAN! " . TextFormat::GRAY . "(Red Hawk | Jet Gatling)");
    }

    public function onUnequip(Player $player) {
        unset($this->gatlingActive[$player->getName()]);
        unset(GearFourth::$gatlingSuppressed[strtolower($player->getName())]);
        $player->sendMessage(TextFormat::GRAY . "Gear Fourth deactivated...");
    }
}

class RedHawkDebrisTask extends Task {

    private $plugin;
    private $level;
    private $debris;
    private $groundY;
    private $tick = 0;
    private $maxTicks = 25;
    private $cleaned = false;

    public function __construct($plugin, Level $level, array $debris, $groundY) {
        $this->plugin = $plugin;
        $this->level = $level;
        $this->debris = $debris;
        $this->groundY = $groundY - 0.5;
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
                $this->level->addParticle(new FlameParticle(
                    new Vector3($d["x"], $d["y"] + 0.2, $d["z"])
                ));
            }
            if ($this->tick % 3 === 0) {
                $this->level->addParticle(new SmokeParticle(
                    new Vector3($d["x"], $d["y"] + 0.4, $d["z"])
                ));
            }
        }

        $toRemove = BlockEffects::tickDebris($this->debris, $this->level, $this->groundY, 0.07, 0.94);
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