<?php

namespace OnePiece\Devil\fruits;

use OnePiece\Devil\BaseFruit;
use OnePiece\Devil\Main;
use OnePiece\Devil\BlockEffects;
use pocketmine\Player;
use pocketmine\math\Vector3;
use pocketmine\entity\Entity;
use pocketmine\utils\TextFormat;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\scheduler\Task;
use pocketmine\level\Level;
use pocketmine\level\particle\DustParticle;
use pocketmine\level\particle\SmokeParticle;
use pocketmine\level\particle\HugeExplodeParticle;
use pocketmine\level\particle\PortalParticle;
use pocketmine\level\particle\InstantEnchantParticle;
use pocketmine\level\particle\CriticalParticle;
use pocketmine\level\particle\FlameParticle;
use pocketmine\level\particle\RedstoneParticle;
use pocketmine\level\sound\GhastShootSound;
use pocketmine\level\sound\ExplodeSound;
use pocketmine\level\sound\AnvilFallSound;
use pocketmine\level\sound\EndermanTeleportSound;
use pocketmine\level\sound\BlazeShootSound;
use pocketmine\level\sound\FizzSound;
use OnePiece\NPC\NPCEntity;
use OnePieceTrades\Factory\FactoryEntity;

class OkuchiOkuchi extends BaseFruit {

    private static $passiveTasks = [];
    public static $invulnerable = [];

    public function getId() { return "okuchi_okuchi"; }
    public function getDisplayName() { return "Okuchi-Okuchi Fruit"; }
    public function getDescription() { return "Harness the power of the Guardian Wolf, Okuchi no Makami."; }
    public function getType() { return "zoan"; }
    public function getRarity() { return "mythical"; }

    public function getAbilityNames() {
        return ["ability1" => "Makami Whirlwind", "ability2" => "Divine Glacial Slam"];
    }

    public function getAbilityCooldowns() {
        return ["ability1" => 12.0, "ability2" => 22.0];
    }

    public function useAbility(Player $player, $ability) {
        if (!isset(self::$passiveTasks[$player->getName()])) $this->onEquip($player);
        $task = self::$passiveTasks[$player->getName()];
        
        switch ($ability) {
            case "ability1": return $this->makamiWhirlwind($player, $task);
            case "ability2": return $this->divineSlam($player, $task);
        }
        return 0;
    }

    private function makamiWhirlwind(Player $player, OkuchiPassiveTask $homies) {
        $mult   = min(1.5, $this->getHakiMultiplier($player));
        $range = $this->getMasteryRange($player, 6.5);
        $damage = min(9.0, 4.5 * $mult);
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask(new MakamiWhirlwindTask($this->plugin, $player, $damage, $range, $homies), 1);
        $player->sendTip(TextFormat::AQUA . TextFormat::BOLD . "MAKAMI WHIRLWIND!");
        return $this->getAbilityCooldowns()["ability1"];
    }

    private function divineSlam(Player $player, OkuchiPassiveTask $homies) {
        $mult   = min(1.5, $this->getHakiMultiplier($player));
        $damage = min(12.0, 6.0 * $mult);
        $mastery = $this->plugin->getMasteryManager()->getLevel($player->getName());
        $range = 25 + (int)($mastery / 15);
        $roarRadius = $this->getMasteryRange($player, 8.5);
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask(new DivineSlamTask($this->plugin, $player, $damage, $range, $roarRadius, $homies), 1);
        return $this->getAbilityCooldowns()["ability2"];
    }

    public function onEquip(Player $player) {
        $this->onUnequip($player);
        $task = new OkuchiPassiveTask($this->plugin, $player);
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($task, 1);
        self::$passiveTasks[$player->getName()] = $task;
        $player->sendMessage(TextFormat::AQUA . "=== Okuchi-Okuchi Fruit ===");
        $player->sendMessage(TextFormat::WHITE . "Mythical Zoan - Guardian of the Mountain");
    }

    public function onUnequip(Player $player) {
        if (isset(self::$passiveTasks[$player->getName()])) {
            self::$passiveTasks[$player->getName()]->stop();
            unset(self::$passiveTasks[$player->getName()]);
        }
    }

    public static function isInvulnerable(Player $player) {
        return isset(self::$invulnerable[$player->getName()]);
    }
}

class OkuchiPassiveTask extends Task {
    public $plugin, $player, $level, $active = true, $orbitEids = [], $override = false;
    private $lastPlayerPos;
    private $resyncTimer = 0;

    public function __construct($plugin, Player $player) { 
        $this->plugin = $plugin; 
        $this->player = $player; 
        $this->level = $player->getLevel(); 
        $this->lastPlayerPos = $player->getPosition();
    }

    public function onRun($currentTick) {
        if (!$this->active || !$this->player->isOnline()) { $this->stop(); return; }
        if ($this->override) return;
        
        $currentPlayerPos = $this->player->getPosition();
        $this->resyncTimer++;

        if ($this->lastPlayerPos->distance($currentPlayerPos) > 30 || $this->resyncTimer >= 200) {
            $this->resyncTimer = 0;
            if (!empty($this->orbitEids)) {
                $this->resyncOrbits();
            }
        }
        $this->lastPlayerPos = $currentPlayerPos;

        $fruits = $this->plugin->getServer()->getPluginManager()->getPlugin("OnePieceFruits");
        if ($fruits !== null) {
            $zoan = $fruits->getZoanSystem();
            if ($zoan !== null && $zoan->isTransformed($this->player)) {
                if (empty($this->orbitEids)) {
                    $orbits = BlockEffects::spawnOrbitBlocks($this->level, $this->player->x, $this->player->y, $this->player->z, 174, 0, 3, 1.8);
                    foreach ($orbits as $o) $this->orbitEids[$o["eid"]] = $o;
                }
                
                $mastery = $this->plugin->getMasteryManager()->getLevel($this->player->getName());
                $speed = 0.1 + ($mastery / 2000);
                
                foreach ($this->orbitEids as $eid => &$o) {
                    $o["angle"] += $speed;
                    $o["x"] = $this->player->x + cos($o["angle"]) * 1.8;
                    $o["y"] = $this->player->y + 0.8 + sin($currentTick * 0.1) * 0.2;
                    $o["z"] = $this->player->z + sin($o["angle"]) * 1.8;
                    BlockEffects::sendMove($this->level, $eid, $o["x"], $o["y"], $o["z"], $o["angle"] * 50);
                    if ($currentTick % 2 === 0) {
                        $this->level->addParticle(new PortalParticle(new Vector3($o["x"], $o["y"], $o["z"])));
                        $this->level->addParticle(new DustParticle(new Vector3($o["x"], $o["y"], $o["z"]), 200, 240, 255));
                    }
                }
            } else {
                if (!empty($this->orbitEids)) {
                    BlockEffects::voidAndRemove($this->plugin, $this->level, array_keys($this->orbitEids));
                    $this->orbitEids = [];
                }
            }
        }
    }

    private function resyncOrbits() {
        $newOrbitEids = [];
        foreach ($this->orbitEids as $oldEid => $orbData) {
            BlockEffects::sendRemove($oldEid);
            $newEid = BlockEffects::newEid();
            BlockEffects::sendSpawn($this->level, $newEid, 174, 0, $orbData["x"], $orbData["y"], $orbData["z"]);
            $orbData["eid"] = $newEid;
            $newOrbitEids[$newEid] = $orbData;
        }
        $this->orbitEids = $newOrbitEids;
    }

    public function stop() { 
        $this->active = false; 
        if (!empty($this->orbitEids)) BlockEffects::voidAndRemove($this->plugin, $this->level, array_keys($this->orbitEids));
        $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId()); 
    }
}

class MakamiWhirlwindTask extends Task {
    private $plugin, $player, $level, $damage, $range, $ticks = 0, $trapped = [], $debris = [], $homies;
    public function __construct($plugin, Player $player, $damage, $range, OkuchiPassiveTask $homies) { 
        $this->plugin = $plugin; $this->player = $player; $this->level = $player->getLevel(); $this->damage = $damage; $this->range = $range; $this->homies = $homies;
        OkuchiOkuchi::$invulnerable[$player->getName()] = true;
        $this->homies->override = true;
        
        $fruits = $this->plugin->getServer()->getPluginManager()->getPlugin("OnePieceFruits");
        if ($fruits === null || !$fruits->getZoanSystem()->isTransformed($player)) {
            $this->debris = BlockEffects::spawnSpiralDebris($this->plugin, $this->level, $player->x, $player->y, $player->z, 4, 3.5, 60);
        }
    }
    public function onRun($currentTick) {
        $this->ticks++;
        if ($this->ticks > 60 || !$this->player->isOnline()) { $this->fling(); return; }
        
        $this->player->setMotion(new Vector3(0, 0, 0));
        if ($this->ticks % 5 === 0) $this->level->addSound(new FizzSound($this->player));

        if (!empty($this->debris)) {
            BlockEffects::tickSpiralDebris($this->debris, $this->level, $this->player->x, $this->player->z, 0.45, 0.1, 0.02);
        } else {
            foreach ($this->homies->orbitEids as $eid => &$o) {
                $o["angle"] += 0.45;
                $radValSize = 3.2;
                $o["x"] = $this->player->x + cos($o["angle"]) * $radValSize;
                $o["y"] = $this->player->y + 0.5 + sin($this->ticks * 0.2) * 0.5;
                $o["z"] = $this->player->z + sin($o["angle"]) * $radValSize;
                BlockEffects::sendMove($this->level, $eid, $o["x"], $o["y"], $o["z"], $o["angle"] * 80);
                $this->level->addParticle(new DustParticle(new Vector3($o["x"], $o["y"], $o["z"]), 220, 240, 255));
            }
        }

        $angle = $this->ticks * 0.6;
        for ($i = 0; $i < 4; $i++) {
            $a = $angle + ($i * M_PI / 2);
            $r = 1.5 + ($this->ticks * 0.05);
            $p = new Vector3($this->player->x + cos($a)*$r, $this->player->y + ($i * 0.5), $this->player->z + sin($a)*$r);
            $this->level->addParticle(new DustParticle($p, 180, 220, 255));
            $this->level->addParticle(new CriticalParticle($p));
        }

        foreach ($this->level->getEntities() as $e) {
            if ($e === $this->player || !$e->isAlive() || $e->distance($this->player) > $this->range) continue;
            if ($e instanceof Player) {
                if (!$this->plugin->canTargetPlayer($this->player->getName(), $e)) continue;
            } elseif (!($e instanceof NPCEntity) && !($e instanceof FactoryEntity)) continue;

            $this->trapped[$e->getId()] = $e;
            $e->setMotion(new Vector3(0, 0, 0));
            $target = $this->player->add(cos($this->ticks)*2, 1, sin($this->ticks)*2);
            $e->teleport($target);
            
            if ($this->ticks % 8 === 0) {
                $e->attack($this->damage, new EntityDamageByEntityEvent($this->player, $e, EntityDamageEvent::CAUSE_MAGIC, $this->damage));
            }
        }
    }
    private function fling() {
        unset(OkuchiOkuchi::$invulnerable[$this->player->getName()]);
        $this->homies->override = false;
        $dir = $this->player->getDirectionVector()->multiply(2.2);
        $dir->y = 0.9;
        
        $idx = 0;
        foreach ($this->homies->orbitEids as $eid => &$o) {
            $o["angle"] = ($idx / 3) * M_PI * 2;
            $idx++;
        }
        
        foreach ($this->trapped as $e) {
            if ($e->isAlive()) $e->setMotion($dir);
        }
        $this->level->addSound(new GhastShootSound($this->player));
        if (!empty($this->debris)) BlockEffects::voidAndRemove($this->plugin, $this->level, array_keys($this->debris));
        $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
    }
}

class DivineSlamTask extends Task {
    private $plugin, $player, $level, $damage, $range, $roarRadius, $ticks = 0, $targetPos, $homies, $startY;
    public function __construct($plugin, Player $player, $damage, $range, $roarRadius, OkuchiPassiveTask $homies) { 
        $this->plugin = $plugin; $this->player = $player; $this->level = $player->getLevel(); $this->damage = $damage; $this->range = $range; $this->roarRadius = $roarRadius; $this->homies = $homies;
        $targetBlock = $player->getTargetBlock($this->range);
        $this->targetPos = $targetBlock !== null ? new Vector3($targetBlock->x + 0.5, $player->y, $targetBlock->z + 0.5) : $player->add($player->getDirectionVector()->multiply($this->range));
        $this->targetPos->y = $player->y;
        $this->startY = $player->y;
        OkuchiOkuchi::$invulnerable[$player->getName()] = true;
    }
    public function onRun($currentTick) {
        $this->ticks++;
        if ($this->ticks <= 10) {
            $this->player->setMotion(new Vector3(0, 0, 0));
            return;
        }
        if ($this->ticks === 11) {
            $this->player->setDataFlag(Entity::DATA_FLAGS, Entity::DATA_FLAG_INVISIBLE, true);
            $this->level->addSound(new EndermanTeleportSound($this->player));
        }
        if ($this->ticks < 22) {
            $dir = $this->player->getDirectionVector();
            $checkBlock = $this->level->getBlock($this->player->add($dir->x * 1.2, 0, $dir->z * 1.2));
            if ($checkBlock->isSolid()) {
                $this->targetPos = $this->player->getPosition();
                $this->explode();
                return;
            }

            foreach($this->level->getEntities() as $e) {
                if ($e === $this->player || !$e->isAlive() || $e->distance($this->player) > 3.0) continue;
                if (abs($e->y - $this->player->y) > 2.0) continue;
                if ($e instanceof Player) {
                    if (!$this->plugin->canTargetPlayer($this->player->getName(), $e)) continue;
                } elseif (!($e instanceof NPCEntity) && !($e instanceof FactoryEntity)) continue;
                $this->targetPos = $e->getPosition();
                $this->targetPos->y = $this->startY;
                $this->explode();
                return;
            }
            $vec = $this->targetPos->subtract($this->player);
            $vec->y = 0;
            $vec = $vec->normalize();
            $this->player->setMotion($vec->multiply(2.2));
            if(abs($this->player->y - $this->startY) > 0.5) $this->player->teleport(new Vector3($this->player->x, $this->startY, $this->player->z));
            
            $this->level->addParticle(new PortalParticle($this->player));
            $this->level->addParticle(new DustParticle($this->player, 220, 240, 255));

            foreach ($this->homies->orbitEids as $eid => $o) {
                $ent = BlockEffects::getEntity($eid);
                if ($ent !== null) {
                    $this->level->addParticle(new DustParticle($ent, 200, 240, 255));
                    $this->level->addParticle(new PortalParticle($ent));
                }
            }
            if ($this->player->distance($this->targetPos) < 1.5) { $this->explode(); return; }
            return;
        }
        $this->explode();
    }
    private function explode() {
        $this->player->setDataFlag(Entity::DATA_FLAGS, Entity::DATA_FLAG_INVISIBLE, false);
        $this->player->teleport($this->targetPos);
        
        $isZoan = false;
        $fruits = $this->plugin->getServer()->getPluginManager()->getPlugin("OnePieceFruits");
        if ($fruits !== null && $fruits->getZoanSystem()->isTransformed($this->player)) $isZoan = true;

        $targetCount = 0;
        foreach ($this->level->getEntities() as $e) {
            if ($e === $this->player || !$e->isAlive() || $e->distance($this->targetPos) > $this->roarRadius) continue;
            if ($e instanceof Player) {
                if (!$this->plugin->canTargetPlayer($this->player->getName(), $e)) continue;
            } elseif (!($e instanceof NPCEntity) && !($e instanceof FactoryEntity)) continue;
            $targetCount++;
        }

        if ($isZoan && $targetCount > 0) {
            $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask(new MakamiRoarTask($this->plugin, $this->player, $this->damage, $this->roarRadius, $this->targetPos, $this->homies), 1);
        } else {
            unset(OkuchiOkuchi::$invulnerable[$this->player->getName()]);
            $this->level->addSound(new ExplodeSound($this->targetPos));
            $this->level->addSound(new AnvilFallSound($this->targetPos));
            $this->level->addSound(new BlazeShootSound($this->targetPos));
            $this->level->addParticle(new HugeExplodeParticle($this->targetPos));
            
            for ($i = 0; $i < 8; $i++) $this->spawnIcyZigZag($this->targetPos, ($i / 8) * M_PI * 2);

            $impactDebris = BlockEffects::spawnDebris($this->plugin, $this->level, $this->targetPos->x, $this->targetPos->y, $this->targetPos->z, 8, 0.8, 1.8);
            $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask(new RoarShockwaveTask($this->plugin, $this->level, $impactDebris), 1);
            
            for($i = 0; $i < 36; $i++){
                $a = ($i / 36) * M_PI * 2;
                $radSizeCheckVal = ($i % 2 === 0) ? 4.5 : 2.5;
                $v = $this->targetPos->add(cos($a) * $radSizeCheckVal, 0.2, sin($a) * $radSizeCheckVal);
                $this->level->addParticle(new DustParticle($v, 200, 240, 255));
                $this->level->addParticle(new PortalParticle($v));
                if($i % 4 === 0) $this->level->addParticle(new SmokeParticle($v->add(0, 0.8, 0)));
            }

            foreach ($this->level->getEntities() as $e) {
                if ($e === $this->player || !$e->isAlive() || $e->distance($this->targetPos) > 6.5) continue;
                if ($e instanceof Player) {
                    if (!$this->plugin->canTargetPlayer($this->player->getName(), $e)) continue;
                } elseif (!($e instanceof NPCEntity) && !($e instanceof FactoryEntity)) continue;
                $e->attack($this->damage, new EntityDamageByEntityEvent($this->player, $e, EntityDamageEvent::CAUSE_MAGIC, $this->damage));
                $e->setMotion(new Vector3(0, 1.4, 0));
            }
        }
        $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
    }

    private function spawnIcyZigZag(Vector3 $start, $angle) {
        $curr = clone $start;
        $dirV = new Vector3(cos($angle), 0, sin($angle));
        for ($s = 0; $s < 10; $s++) {
            $jit = new Vector3(mt_rand(-7, 7) / 10, mt_rand(0, 4) / 10, mt_rand(-7, 7) / 10);
            $nextP = $curr->add($dirV->multiply(0.7))->add($jit);
            $this->level->addParticle(new PortalParticle($nextP));
            $this->level->addParticle(new DustParticle($nextP, 150, 200, 255));
            $this->level->addParticle(new InstantEnchantParticle($nextP));
            $curr = $nextP;
        }
    }
}

class MakamiRoarTask extends Task {
    private $plugin, $player, $level, $damage, $roarRadius, $center, $ticks = 0, $targetMapping = [], $homies;
    public function __construct($plugin, Player $player, $damage, $roarRadius, Vector3 $center, OkuchiPassiveTask $homies) {
        $this->plugin = $plugin; $this->player = $player; $this->level = $player->getLevel(); $this->damage = $damage; $this->roarRadius = $roarRadius; $this->center = $center; $this->homies = $homies;
        $this->homies->override = true;
        
        $i = 0;
        $blockEids = array_keys($this->homies->orbitEids);
        $max = min(3, count($blockEids));
        foreach ($this->level->getEntities() as $e) {
            if ($e === $this->player || !$e->isAlive() || $e->distance($this->center) > $this->roarRadius || $i >= $max) continue;
            if ($e instanceof Player) {
                if (!$this->plugin->canTargetPlayer($this->player->getName(), $e)) continue;
            } elseif (!($e instanceof NPCEntity) && !($e instanceof FactoryEntity)) continue;
            $this->targetMapping[$blockEids[$i]] = $e;
            $i++;
        }
    }
    public function onRun($currentTick) {
        if (!$this->player->isOnline() || $this->ticks >= 80) {
            unset(OkuchiOkuchi::$invulnerable[$this->player->getName()]);
            $this->homies->override = false;
            $idxCount = 0;
            foreach ($this->homies->orbitEids as $eid => &$o) {
                $o["angle"] = ($idxCount / 3) * M_PI * 2;
                $o["x"] = $this->player->x + cos($o["angle"]) * 1.8;
                $o["y"] = $this->player->y + 0.8;
                $o["z"] = $this->player->z + sin($o["angle"]) * 1.8;
                $idxCount++;
            }
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
            return;
        }
        $this->ticks++;
        $this->player->setMotion(new Vector3(0, 0, 0));
        $this->player->teleport($this->center);

        if ($this->ticks === 1) {
            $this->level->addSound(new ExplodeSound($this->center));
            $this->level->addParticle(new HugeExplodeParticle($this->center));
            $roarDebris = BlockEffects::spawnDebris($this->plugin, $this->level, $this->center->x, $this->center->y, $this->center->z, 12, 1.2, 2.5, 40, [["id" => 174, "damage" => 0], ["id" => 80, "damage" => 0]]);
            $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask(new RoarShockwaveTask($this->plugin, $this->level, $roarDebris), 1);
        }

        foreach ($this->homies->orbitEids as $eid => &$o) {
            if (isset($this->targetMapping[$eid]) && $this->targetMapping[$eid]->isAlive()) {
                $e = $this->targetMapping[$eid];
                $o["x"] = $e->x; $o["y"] = $e->y + 1; $o["z"] = $e->z;
                BlockEffects::sendMove($this->level, $eid, $o["x"], $o["y"], $o["z"], $this->ticks * 20);
                $this->level->addParticle(new InstantEnchantParticle($e));
            } else {
                $o["angle"] += 0.1;
                $o["x"] = $this->center->x + cos($o["angle"]) * 2.5;
                $o["y"] = $this->center->y + 0.8;
                $o["z"] = $this->center->z + sin($o["angle"]) * 2.5;
                BlockEffects::sendMove($this->level, $eid, $o["x"], $o["y"], $o["z"], $o["angle"] * 50);
            }
        }

        if ($this->ticks % 5 === 0) {
            for ($i = 0; $i < 8; $i++) $this->spawnZigZagHaki($this->center, ($i / 8) * M_PI * 2);
            $this->level->addSound(new BlazeShootSound($this->center));
        }

        foreach ($this->level->getEntities() as $e) {
            if ($e === $this->player || !$e->isAlive() || $e->distance($this->center) > $this->roarRadius) continue;
            if ($e instanceof Player) {
                if (!$this->plugin->canTargetPlayer($this->player->getName(), $e)) continue;
            } elseif (!($e instanceof NPCEntity) && !($e instanceof FactoryEntity)) continue;

            $diffVect = $e->getPosition()->subtract($this->center);
            $diffVect->y = 0;
            $distVal = $diffVect->length();
            if ($distVal > 0) {
                $dir = $diffVect->normalize();
                if ($distVal < 2.0) {
                    $tPos = $this->center->add($dir->multiply(4.0));
                    $tPos->y = $e->y;
                    $e->teleport($tPos);
                }
            }
            $e->setMotion(new Vector3(0, 0, 0));

            if ($this->ticks % 20 === 0) {
                $e->attack($this->damage * 0.25, new EntityDamageByEntityEvent($this->player, $e, EntityDamageEvent::CAUSE_MAGIC, $this->damage * 0.25));
            }
        }
        if ($this->ticks % 10 === 0) $this->player->sendTip(TextFormat::WHITE . TextFormat::BOLD . "ROAR...");
    }

    private function spawnZigZagHaki(Vector3 $start, $angle) {
        $current = clone $start;
        $hakiDir = new Vector3(cos($angle), 0, sin($angle));
        for ($step = 0; $step < 12; $step++) {
            $jitterVec = new Vector3(mt_rand(-8, 8) / 10, mt_rand(0, 5) / 10, mt_rand(-8, 8) / 10);
            $nextPos = $current->add($hakiDir->multiply(0.8))->add($jitterVec);
            $this->level->addParticle(new FlameParticle($nextPos));
            $this->level->addParticle(new RedstoneParticle($nextPos));
            $current = $nextPos;
        }
    }
}

class RoarShockwaveTask extends Task {
    private $plugin, $level, $debris;
    public function __construct($plugin, $level, $debris) { $this->plugin = $plugin; $this->level = $level; $this->debris = $debris; }
    public function onRun($currentTick) {
        $toRemove = BlockEffects::tickDebris($this->debris, $this->level, -50, 0.08, 0.95);
        foreach ($toRemove as $eid) unset($this->debris[$eid]);
        if (empty($this->debris)) $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
    }
}

class MakamiStunTask extends Task {
    private $entity, $ticks;
    public function __construct($entity, $duration) { $this->entity = $entity; $this->ticks = $duration; }
    public function onRun($currentTick) {
        if (!$this->entity->isAlive() || $this->ticks-- <= 0) {
            \pocketmine\Server::getInstance()->getScheduler()->cancelTask($this->getTaskId());
            return;
        }
        $this->entity->setMotion(new Vector3(0, 0, 0));
    }
}