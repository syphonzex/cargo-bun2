<?php

namespace OnePiece\Devil\fruits;

use OnePiece\Devil\BaseFruit;
use OnePiece\Devil\Main;
use OnePiece\Devil\BlockEffects;
use pocketmine\Player;
use pocketmine\math\Vector3;
use pocketmine\entity\Entity;
use pocketmine\entity\Effect;
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
use pocketmine\level\particle\FlameParticle;
use pocketmine\level\particle\RedstoneParticle;
use pocketmine\level\sound\ExplodeSound;
use pocketmine\level\sound\AnvilFallSound;
use pocketmine\level\sound\AnvilUseSound;
use pocketmine\level\sound\EndermanTeleportSound;
use pocketmine\level\sound\BlazeShootSound;
use pocketmine\level\sound\FizzSound;
use pocketmine\level\sound\BatSound;
use OnePiece\NPC\NPCEntity;
use OnePieceTrades\Factory\FactoryEntity;

class ShadowShadow extends BaseFruit {

    private static $passiveTasks = [];
    public static $invulnerable = [];

    public function getId() { return "shadow_shadow"; }
    public function getDisplayName() { return "Shadow-Shadow Fruit"; }
    public function getDescription() { return "Tactical shadow manipulation and life-leeching."; }
    public function getType() { return "paramecia"; }
    public function getRarity() { return "mythical"; }

    public function getAbilityNames() {
        return ["ability1" => "Umbra Execution", "ability2" => "Shadow Singularity"];
    }

    public function getAbilityCooldowns() {
        return ["ability1" => 14.0, "ability2" => 30.0];
    }

    public function useAbility(Player $player, $ability) {
        if (!isset(self::$passiveTasks[$player->getName()])) $this->onEquip($player);
        
        switch ($ability) {
            case "ability1": return $this->umbraExecution($player);
            case "ability2": return $this->shadowSingularity($player);
        }
        return 0;
    }

    private function umbraExecution(Player $player) {
        $mult = $this->getCombinedMultiplier($player);
        $damage = min(10.0, 5.5 * $mult);
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask(new UmbraExecutionTask($this->plugin, $player, $damage), 1);
        return $this->getAbilityCooldowns()["ability1"];
    }

    private function shadowSingularity(Player $player) {
        $mult = $this->getCombinedMultiplier($player);
        $damage = min(8.0, 3.5 * $mult);
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask(new ShadowSingularityTask($this->plugin, $player, $damage), 1);
        return $this->getAbilityCooldowns()["ability2"];
    }

    public function onEquip(Player $player) {
        $this->onUnequip($player);
        $task = new ShadowPassiveTask($this->plugin, $player);
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($task, 1);
        self::$passiveTasks[$player->getName()] = $task;
        $player->sendMessage(TextFormat::DARK_PURPLE . "=== Kage-Kage no Mi ===");
        $player->sendMessage(TextFormat::WHITE . "Mythical - Ruler of the Abyss");
    }

    public function onUnequip(Player $player) {
        if (isset(self::$passiveTasks[$player->getName()])) {
            self::$passiveTasks[$player->getName()]->stop();
            unset(self::$passiveTasks[$player->getName()]);
        }
    }
}

class ShadowPassiveTask extends Task {
    private $plugin, $player, $level, $active = true;
    public function __construct($plugin, Player $player) { $this->plugin = $plugin; $this->player = $player; $this->level = $player->getLevel(); }
    public function onRun($currentTick) {
        if (!$this->active || !$this->player->isOnline()) { $this->stop(); return; }
        if ($currentTick % 2 === 0) {
            $p = new Vector3($this->player->x, $this->player->y, $this->player->z);
            $this->level->addParticle(new DustParticle($p->add(0, 0.1, 0), 0, 0, 0));
            $this->level->addParticle(new PortalParticle($p->add(0, 0.2, 0)));
            if ($currentTick % 10 === 0) $this->level->addParticle(new InstantEnchantParticle($p->add(0, 1, 0)));
        }
    }
    public function stop() { $this->active = false; $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId()); }
}

class UmbraExecutionTask extends Task {
    private $plugin, $player, $level, $damage, $ticks = 0, $state = 0, $target = null;
    private $batEids = [], $batPositions = [], $batTargetPos, $dashDebris = [], $stayTicks = 0;

    public function __construct($plugin, Player $player, $damage) {
        $this->plugin = $plugin; $this->player = $player; $this->level = $player->getLevel(); $this->damage = $damage;
        ShadowShadow::$invulnerable[$player->getName()] = true;
    }

    public function onRun($currentTick) {
        $this->ticks++;
        if (!$this->player->isOnline() || $this->player->closed) { $this->cleanup(); return; }

        if (!empty($this->dashDebris)) {
            $toRemove = BlockEffects::tickDebris($this->dashDebris, $this->level, $this->player->y - 1);
            foreach ($toRemove as $rid) unset($this->dashDebris[$rid]);
        }

        switch ($this->state) {
            case 0:
                $dir = $this->player->getDirectionVector();
                $this->player->setMotion(new Vector3($dir->x * 2.2, 0, $dir->z * 2.2));
                $pPos = new Vector3($this->player->x, $this->player->y + 1, $this->player->z);
                $this->level->addParticle(new PortalParticle($pPos));
                $this->level->addParticle(new DustParticle($pPos, 0, 0, 0));
                
                if ($this->ticks % 3 === 0) {
                    $groundDebris = BlockEffects::spawnDebris($this->plugin, $this->level, $this->player->x, $this->player->y, $this->player->z, 1, 0.3, 0.6, 20);
                    foreach ($groundDebris as $eid => $dat) $this->dashDebris[$eid] = $dat;
                }

                foreach ($this->level->getEntities() as $e) {
                    if ($e === $this->player || !$e->isAlive()) continue;
                    if ($e instanceof Player) {
                        if (!$this->plugin->canTargetPlayer($this->player->getName(), $e)) continue;
                    } elseif (!($e instanceof NPCEntity) && !($e instanceof FactoryEntity)) continue;
                    
                    $dist = $e->distance($this->player);
                    if ($dist < 5.0) {
                        $this->target = $e;
                        $this->state = 1; $this->ticks = 0;
                        $pVec = new Vector3($this->player->x, $this->player->y, $this->player->z);
                        $tVec = new Vector3($e->x, $e->y, $e->z);
                        $dirToTarget = $tVec->subtract($pVec)->normalize();
                        $stopPos = $tVec->subtract($dirToTarget->multiply(3.0));
                        $this->player->teleport(new Vector3($stopPos->x, $this->player->y, $stopPos->z));
                        $this->player->setMotion(new Vector3(0, 0, 0));
                        $pDir = $this->player->getDirectionVector();
                        $targetFront = new Vector3($this->player->x + $pDir->x * 3.0, $this->target->y, $this->player->z + $pDir->z * 3.0);
                        $this->target->teleport($targetFront);
                        $this->target->setMotion(new Vector3(0, 0, 0));
                        $this->level->addSound(new EndermanTeleportSound($pVec));
                        $impactDebris = BlockEffects::spawnDebris($this->plugin, $this->level, $this->target->x, $this->target->y, $this->target->z, 4, 0.5, 0.8, 25);
                        foreach ($impactDebris as $eid => $dat) $this->dashDebris[$eid] = $dat;
                        return;
                    }
                }
                if ($this->ticks > 20) $this->cleanup();
                break;

            case 1:
                $this->player->setMotion(new Vector3(0, 0, 0));
                $this->target->setMotion(new Vector3(0, 0, 0));
                if ($this->ticks % 4 === 0) {
                    $tPos = new Vector3($this->target->x, $this->target->y + 1, $this->target->z);
                    $this->spawnSlashVisual($tPos);
                    $this->level->addSound(new AnvilUseSound($tPos));
                    $this->target->attack($this->damage / 6, new EntityDamageByEntityEvent($this->player, $this->target, EntityDamageEvent::CAUSE_MAGIC, $this->damage / 6));
                }
                if ($this->ticks >= 20) {
                    $this->state = 2; $this->ticks = 0;
                    $this->player->setMotion(new Vector3(0, 1.4, 0));
                    $this->batTargetPos = new Vector3($this->target->x, $this->target->y, $this->target->z);
                    for ($i = 0; $i < 3; $i++) {
                        $eid = BlockEffects::newEid();
                        $bPos = new Vector3($this->player->x + mt_rand(-1, 1), $this->player->y + 2, $this->player->z + mt_rand(-1, 1));
                        BlockEffects::sendSpawn($this->level, $eid, 173, 0, $bPos->x, $bPos->y, $bPos->z);
                        $this->batEids[] = $eid;
                        $this->batPositions[] = $bPos;
                    }
                }
                break;

            case 2:
                if ($this->ticks > 60) { $this->cleanup(); return; }
                $reached = false;
                foreach ($this->batPositions as $i => &$pos) {
                    if (isset($this->batEids[$i])) {
                        $moveVec = $this->batTargetPos->add(0, 1, 0)->subtract($pos)->normalize()->multiply(0.9);
                        if ($pos->distance($this->batTargetPos) > 1.2) {
                            $pos = $pos->add($moveVec);
                            BlockEffects::sendMove($this->level, $this->batEids[$i], $pos->x, $pos->y, $pos->z, $this->ticks * 30);
                        } else { $reached = true; }
                        $this->level->addParticle(new PortalParticle($pos));
                        $this->level->addParticle(new DustParticle($pos, 0, 0, 0));
                        if($this->ticks % 2 === 0) $this->level->addParticle(new InstantEnchantParticle($pos));
                    }
                }
                if ($reached && $this->stayTicks === 0) {
                    $this->stayTicks = 1;
                    $finalTPos = new Vector3($this->target->x, $this->target->y, $this->target->z);
                    $this->level->addSound(new BatSound($finalTPos));
                    $this->level->addSound(new ExplodeSound($finalTPos));
                    $this->target->attack($this->damage / 2, new EntityDamageByEntityEvent($this->player, $this->target, EntityDamageEvent::CAUSE_MAGIC, $this->damage / 2));
                    $pVec = new Vector3($this->player->x, $this->player->y, $this->player->z);
                    $knockDir = $finalTPos->subtract($pVec)->normalize();
                    $this->target->setMotion(new Vector3($knockDir->x * 1.6, 0.4, $knockDir->z * 1.6));
                }
                if ($this->stayTicks > 0) {
                    $this->stayTicks++;
                    if ($this->stayTicks > 20) $this->cleanup();
                }
                break;
        }
    }

    private function spawnSlashVisual(Vector3 $pos) {
        for ($i = 0; $i < 12; $i++) {
            $v = new Vector3(mt_rand(-12, 12) / 10, mt_rand(-12, 12) / 10, mt_rand(-12, 12) / 10);
            $this->level->addParticle(new DustParticle($pos->add($v), 0, 0, 0));
            $this->level->addParticle(new InstantEnchantParticle($pos->add($v)));
            $this->level->addParticle(new PortalParticle($pos->add($v)));
        }
    }

    private function cleanup() {
        unset(ShadowShadow::$invulnerable[$this->player->getName()]);
        if (!empty($this->batEids)) BlockEffects::voidAndRemove($this->plugin, $this->level, $this->batEids);
        if (!empty($this->dashDebris)) BlockEffects::voidAndRemove($this->plugin, $this->level, array_keys($this->dashDebris));
        $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
    }
}

class ShadowSingularityTask extends Task {
    private $plugin, $player, $level, $damage, $ticks = 0, $debris = [], $sphereEids = [];
    public function __construct($plugin, Player $player, $damage) {
        $this->plugin = $plugin; $this->player = $player; $this->level = $player->getLevel(); $this->damage = $damage;
        ShadowShadow::$invulnerable[$player->getName()] = true;
    }
    public function onRun($currentTick) {
        $this->ticks++;
        if (!$this->player->isOnline() || $this->player->closed) { $this->cleanup(); return; }
        if (!empty($this->debris)) {
            $toRemove = BlockEffects::tickDebris($this->debris, $this->level, $this->player->y - 1);
            foreach ($toRemove as $rid) unset($this->debris[$rid]);
        }
        if ($this->ticks <= 45) {
            $this->player->setMotion(new Vector3(0, 0, 0));
            $this->player->sendTip(TextFormat::DARK_PURPLE . TextFormat::BOLD . "SHADOW INFLATION...");
            $size = $this->ticks / 5;
            $center = new Vector3($this->player->x, $this->player->y + 1, $this->player->z);
            if ($this->ticks === 5) {
                // Replaced Obsidian orbit with themed particle burst logic
                $orbits = BlockEffects::spawnOrbitBlocks($this->level, $this->player->x, $this->player->y + 1, $this->player->z, 173, 0, 12, 1.0);
                foreach ($orbits as $o) { $o["radius"] = 1.0; $this->sphereEids[$o["eid"]] = $o; }
            }
            foreach ($this->sphereEids as $eid => &$o) {
                $o["angle"] += 0.35; $o["radius"] += 0.18;
                $ox = $this->player->x + cos($o["angle"]) * $o["radius"];
                $oz = $this->player->z + sin($o["angle"]) * $o["radius"];
                BlockEffects::sendMove($this->level, $eid, $ox, $this->player->y + 1 + sin($this->ticks * 0.25), $oz, $this->ticks * 45);
            }
            for ($i = 0; $i < 16; $i++) {
                $a = ($i / 16) * M_PI * 2 + ($this->ticks * 0.45);
                $p = $center->add(cos($a) * $size, mt_rand(-30, 30) / 10, sin($a) * $size);
                $this->level->addParticle(new DustParticle($p, 0, 0, 0));
                $this->level->addParticle(new PortalParticle($p));
                if ($i % 3 === 0) $this->level->addParticle(new InstantEnchantParticle($p));
            }
            if ($this->ticks % 8 === 0) $this->level->addSound(new BlazeShootSound($center));
            return;
        }
        if ($this->ticks === 46) { $this->explode(); }
        if ($this->ticks > 46 && $this->ticks < 80) return;
        $this->cleanup();
    }
    private function explode() {
        $pos = new Vector3($this->player->x, $this->player->y, $this->player->z);
        $this->level->addSound(new ExplodeSound($pos));
        $this->level->addParticle(new HugeExplodeParticle($pos));
        $this->debris = BlockEffects::spawnDebris($this->plugin, $this->level, $pos->x, $pos->y, $pos->z, 20, 1.0, 2.5, 40);
        foreach ($this->level->getEntities() as $e) {
            if ($e === $this->player || !$e->isAlive() || $e->distance($this->player) > 15) continue;
            if ($e instanceof Player) {
                if (!$this->plugin->canTargetPlayer($this->player->getName(), $e)) continue;
                $nausea = Effect::getEffect(Effect::NAUSEA);
                $nausea->setAmplifier(1); $nausea->setDuration(180); $nausea->setVisible(false);
                $e->addEffect($nausea);
            } elseif (!($e instanceof NPCEntity) && !($e instanceof FactoryEntity)) continue;
            $e->attack($this->damage, new EntityDamageByEntityEvent($this->player, $e, EntityDamageEvent::CAUSE_MAGIC, $this->damage));
        }
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask(new ShadowBatSwarmTask($this->plugin, $this->player, $pos, $this->damage / 6), 1);
    }
    private function cleanup() {
        unset(ShadowShadow::$invulnerable[$this->player->getName()]);
        if (!empty($this->sphereEids)) BlockEffects::voidAndRemove($this->plugin, $this->level, array_keys($this->sphereEids));
        if (!empty($this->debris)) BlockEffects::voidAndRemove($this->plugin, $this->level, array_keys($this->debris));
        $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
    }
}

class ShadowBatSwarmTask extends Task {
    private $plugin, $player, $center, $damage, $ticks = 0, $coalBatEids = [];
    private $targetLastHit = [];

    public function __construct($plugin, Player $player, Vector3 $center, $damage) { 
        $this->plugin = $plugin; $this->player = $player; $this->center = $center; $this->damage = $damage; 
        $lv = $player->getLevel();
        for($i=0; $i < 10; $i++){
            $eid = BlockEffects::newEid();
            BlockEffects::sendSpawn($lv, $eid, 173, 0, $center->x, $center->y + 1, $center->z);
            $this->coalBatEids[$eid] = [
                "x" => $center->x, "y" => $center->y + 1, "z" => $center->z,
                "angle" => mt_rand(0, 628)/100, 
                "radius" => mt_rand(4, 12), 
                "yOffset" => mt_rand(0, 45)/10,
                "target" => null
            ];
        }
    }

    public function onRun($currentTick) {
        $this->ticks++;
        if ($this->ticks > 160 || !$this->player->isOnline()) { 
            if(!empty($this->coalBatEids)) BlockEffects::voidAndRemove($this->plugin, $this->player->getLevel(), array_keys($this->coalBatEids));
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId()); 
            return; 
        }
        $level = $this->player->getLevel();
        $targets = [];
        foreach($level->getEntities() as $e){
            if($e === $this->player || !$e->isAlive() || $e->distance($this->center) > 14) continue;
            if($e instanceof Player && !$this->plugin->canTargetPlayer($this->player->getName(), $e)) continue;
            if(!($e instanceof Player) && !($e instanceof NPCEntity) && !($e instanceof FactoryEntity)) continue;
            $targets[] = $e;
        }

        foreach($this->coalBatEids as $eid => &$dat){
            if($dat["target"] !== null && (!$dat["target"]->isAlive() || $dat["target"]->distance($this->center) > 15)){
                $dat["target"] = null;
            }
            if($dat["target"] === null && !empty($targets) && mt_rand(0, 5) === 0){
                $dat["target"] = $targets[array_rand($targets)];
            }

            if($dat["target"] === null){
                $dat["angle"] += 0.25;
                $dat["x"] = $this->center->x + cos($dat["angle"]) * $dat["radius"];
                $dat["z"] = $this->center->z + sin($dat["angle"]) * $dat["radius"];
                $dat["y"] = $this->center->y + $dat["yOffset"] + sin($this->ticks * 0.2);
            } else {
                $tPos = $dat["target"]->getPosition()->add(0, 1, 0);
                $dir = $tPos->subtract(new Vector3($dat["x"], $dat["y"], $dat["z"]))->normalize()->multiply(0.6);
                $dat["x"] += $dir->x; $dat["y"] += $dir->y; $dat["z"] += $dir->z;
                
                if($tPos->distance(new Vector3($dat["x"], $dat["y"], $dat["z"])) < 1.5){
                    $targetId = $dat["target"]->getId();
                    if(!isset($this->targetLastHit[$targetId]) || ($this->ticks - $this->targetLastHit[$targetId]) >= 10){
                        $dat["target"]->attack($this->damage, new EntityDamageByEntityEvent($this->player, $dat["target"], EntityDamageEvent::CAUSE_MAGIC, $this->damage));
                        $this->player->setHealth(min($this->player->getMaxHealth(), $this->player->getHealth() + 0.5));
                        $level->addSound(new BatSound($tPos));
                        $this->targetLastHit[$targetId] = $this->ticks;
                    }
                    $dat["target"] = null;
                }
            }
            BlockEffects::sendMove($level, $eid, $dat["x"], $dat["y"], $dat["z"], $this->ticks * 40);
            if($this->ticks % 4 === 0) $level->addParticle(new PortalParticle(new Vector3($dat["x"], $dat["y"], $dat["z"])));
        }

        for ($i = 0; $i < 15; $i++) {
            $a = mt_rand(0, 628) / 100; $r = mt_rand(0, 140) / 10;
            $p = $this->center->add(cos($a) * $r, mt_rand(-15, 70) / 10, sin($a) * $r);
            $level->addParticle(new PortalParticle($p));
            $level->addParticle(new DustParticle($p, 0, 0, 0));
            if ($i % 5 === 0) $level->addParticle(new InstantEnchantParticle($p));
        }

        foreach ($targets as $e) {
            $ePos = new Vector3($e->x, $e->y, $e->z);
            $pull = $this->center->subtract($ePos)->normalize()->multiply(0.12);
            $e->setMotion($pull);
        }
    }
}