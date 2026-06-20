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
use pocketmine\level\sound\ExplodeSound;
use pocketmine\level\sound\AnvilFallSound;
use pocketmine\level\sound\AnvilUseSound;
use pocketmine\level\sound\EndermanTeleportSound;
use pocketmine\level\sound\BlazeShootSound;
use pocketmine\level\sound\FizzSound;
use pocketmine\level\sound\SplashSound;
use OnePiece\NPC\NPCEntity;
use OnePieceTrades\Factory\FactoryEntity;

class DarkXQuake extends BaseFruit {

    private static $passiveTasks = [];
    public static $invulnerable = [];

    public function getId() { return "dark_x_quake"; }
    public function getDisplayName() { return "Dark-X-Quake Fruit"; }
    public function getDescription() { return "The duality of Darkness and Destruction."; }
    public function getType() { return "paramecia"; }
    public function getRarity() { return "god"; }

    public function getAbilityNames() {
        return ["ability1" => "Abyssal Shatter", "ability2" => "Shadow Tsunami"];
    }

    public function getAbilityCooldowns() {
        return ["ability1" => 10.0, "ability2" => 25.0];
    }

    public function useAbility(Player $player, $ability) {
        $name = $player->getName();
        if (!isset(self::$passiveTasks[$name]) || !self::$passiveTasks[$name]->active) {
            $this->onEquip($player);
        }
        $task = self::$passiveTasks[$name];

        switch ($ability) {
            case "ability1": return $this->abyssalShatter($player, $task);
            case "ability2": return $this->shadowTsunami($player, $task);
        }
        return 0;
    }

    private function abyssalShatter(Player $player, DarkQuakePassiveTask $homies) {
        $mult   = min(1.5, $this->getHakiMultiplier($player));
        $damage = 20.0 * $mult;
        $target = $this->findFrontTarget($player, 15);
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask(new AbyssalShatterTask($this->plugin, $player, $damage, $target, $homies), 1);
        return $this->getAbilityCooldowns()["ability1"];
    }

    private function shadowTsunami(Player $player, DarkQuakePassiveTask $homies) {
        $mult   = min(1.5, $this->getHakiMultiplier($player));
        $damage = 12.0 * $mult;
        $radius = $this->getMasteryRange($player, 18.0);
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask(new ShadowTsunamiTask($this->plugin, $player, $damage, $radius, $homies), 1);
        $player->sendTip(TextFormat::DARK_GRAY . TextFormat::BOLD . "SHADOW TSUNAMI!");
        return $this->getAbilityCooldowns()["ability2"];
    }

    public function onEquip(Player $player) {
        $this->onUnequip($player);
        $task = new DarkQuakePassiveTask($this->plugin, $player);
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($task, 1);
        self::$passiveTasks[$player->getName()] = $task;
        $player->sendMessage(TextFormat::DARK_PURPLE . "=== Dark-X-Quake no Mi ===");
        $player->sendMessage(TextFormat::WHITE . "Mythical - The Dual Emperor");
    }

    public function onUnequip(Player $player) {
        if (isset(self::$passiveTasks[$player->getName()])) {
            self::$passiveTasks[$player->getName()]->stop();
            unset(self::$passiveTasks[$player->getName()]);
        }
    }
}

class DarkQuakePassiveTask extends Task {
    public $plugin, $player, $level, $active = true, $world, $shardEid, $blockId = 49;
    private $state = 0, $timer = 0, $angle = 0;
    private $lastPlayerPos;
    private $resyncTimer = 0;

    public function __construct($plugin, Player $player) {
        $this->plugin = $plugin;
        $this->player = $player;
        $this->level = $player->getLevel();
        $this->world = $this->level->getName();
        $this->shardEid = BlockEffects::newEid();
        BlockEffects::sendSpawn($this->level, $this->shardEid, 49, 0, $player->x, $player->y + 2, $player->z);
        $this->lastPlayerPos = $player->getPosition();
    }

    public function onRun($currentTick) {
        if (!$this->active || !$this->player->isOnline() || $this->player->getLevel()->getName() !== $this->world) {
            $this->stop();
            return;
        }
        
        $currentPos = $this->player->getPosition();
        $this->resyncTimer++;

        $this->timer++;
        $this->angle += 0.08;
        $radius = 1.8;
        $baseX = $this->player->x + cos($this->angle) * $radius;
        $baseZ = $this->player->z + sin($this->angle) * $radius;
        $baseY = $this->player->y + 1.2 + sin($this->timer * 0.1) * 0.3;
        $shardPos = new Vector3($baseX, $baseY, $baseZ);

        if ($this->lastPlayerPos->distance($currentPos) > 30 || $this->resyncTimer >= 200) {
            $this->resyncTimer = 0;
            $this->resyncShard($baseX, $baseY, $baseZ);
        }
        $this->lastPlayerPos = $currentPos;

        switch ($this->state) {
            case 0:
                BlockEffects::sendMove($this->level, $this->shardEid, $baseX, $baseY, $baseZ, $this->timer * 5);
                if ($this->timer > mt_rand(100, 200)) {
                    $this->state = 1;
                    $this->timer = 0;
                }
                break;
            case 1:
                $jx = $baseX + (mt_rand(-2, 2) / 10);
                $jz = $baseZ + (mt_rand(-2, 2) / 10);
                BlockEffects::sendMove($this->level, $this->shardEid, $jx, $baseY, $jz, $this->timer * 20);
                $this->level->addParticle(new CriticalParticle(new Vector3($jx, $baseY, $jz)));
                if ($this->timer % 5 === 0) $this->level->addSound(new FizzSound(new Vector3($jx, $baseY, $jz)));
                if ($this->timer > 30) {
                    $this->blockId = ($this->blockId == 49) ? 246 : 49;
                    $this->resyncShard($baseX, $baseY, $baseZ);
                    for ($i = 0; $i < 8; $i++) {
                        $this->level->addParticle(new CriticalParticle($shardPos->add(mt_rand(-5, 5) / 10, mt_rand(-5, 5) / 10, mt_rand(-5, 5) / 10)));
                    }
                    $this->level->addParticle(new PortalParticle($shardPos));
                    $this->level->addParticle(new DustParticle($shardPos, 0, 0, 0));
                    $this->level->addSound(new EndermanTeleportSound($shardPos));
                    $this->state = 0;
                    $this->timer = 0;
                }
                break;
        }

        if ($this->blockId == 49) {
            $this->level->addParticle(new DustParticle($shardPos, 0, 0, 0));
            if ($currentTick % 2 === 0) $this->level->addParticle(new SmokeParticle($shardPos));
        } else {
            $this->level->addParticle(new RedstoneParticle($shardPos));
        }

        if ($currentTick % 2 === 0) {
            $pPos = new Vector3($this->player->x, $this->player->y + 0.1, $this->player->z);
            $this->level->addParticle(new DustParticle($pPos, 0, 0, 0));
            $this->level->addParticle(new PortalParticle($pPos));
        }
    }
    
    private function resyncShard($x, $y, $z) {
        BlockEffects::sendRemove($this->shardEid);
        $this->shardEid = BlockEffects::newEid();
        BlockEffects::sendSpawn($this->level, $this->shardEid, $this->blockId, 0, $x, $y, $z);
    }

    public function stop() {
        if (!$this->active) return;
        $this->active = false;
        BlockEffects::sendRemove($this->shardEid);
        $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
    }
}

class AbyssalShatterTask extends Task {
    private $plugin, $player, $level, $damage, $ticks = 0, $target, $homies;
    public function __construct($plugin, Player $player, $damage, $target, DarkQuakePassiveTask $homies) { 
        $this->plugin = $plugin; $this->player = $player; $this->level = $player->getLevel(); $this->damage = $damage; $this->target = $target; $this->homies = $homies;
    }
    public function onRun($currentTick) {
        $this->ticks++;
        if (!$this->player->isOnline() || $this->player->closed) { $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId()); return; }
        
        $mode = ($this->homies->blockId === 49) ? "dark" : "red";

        if ($this->ticks <= 40) {
            $this->player->setMotion(new Vector3(0, 0, 0));
            $this->player->sendTip(TextFormat::DARK_PURPLE . TextFormat::BOLD . "CHARGING ABYSS...");
            $angle = $this->ticks * 0.5;
            $center = new Vector3($this->player->x, $this->player->y + 1, $this->player->z);
            for ($i = 0; $i < 4; $i++) {
                $a = $angle + ($i * M_PI * 2 / 4);
                $p = new Vector3($center->x + cos($a) * 1.6, $center->y + sin($this->ticks * 0.1) * 0.6, $center->z + sin($a) * 1.6);
                if ($mode === "dark") {
                    $this->level->addParticle(new PortalParticle($p));
                    $this->level->addParticle(new DustParticle($p, 0, 0, 0));
                    $this->level->addParticle(new SmokeParticle($p));
                } else {
                    $this->level->addParticle(new RedstoneParticle($p));
                    $this->level->addParticle(new CriticalParticle($p));
                    $this->level->addParticle(new FlameParticle($p));
                }
            }
            return;
        }

        if ($this->ticks === 41) {
            $dir = $this->player->getDirectionVector();
            $crackPos = new Vector3($this->player->x + $dir->x * 2.5, $this->player->y + 1.5, $this->player->z + $dir->z * 2.5);
            $this->spawnJaggedCrack($crackPos, $mode);
            $this->level->addSound(new AnvilUseSound($crackPos));
            if ($this->target === null) {
                $this->level->addParticle(new HugeExplodeParticle($crackPos));
                $this->level->addSound(new ExplodeSound($crackPos));
                $debris = BlockEffects::spawnDebris($this->plugin, $this->level, $crackPos->x, $crackPos->y, $crackPos->z, 6, 0.5, 1.2);
                $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask(new DarkQuakeDebrisTask($this->plugin, $this->level, $debris), 1);
                $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
                return;
            }
        }

        if ($this->ticks > 41 && $this->ticks <= 65) {
            if ($this->target === null || !$this->target->isAlive()) { $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId()); return; }
            $this->target->setMotion(new Vector3(0, 0, 0));
            $this->player->sendTip(TextFormat::DARK_RED . TextFormat::BOLD . "VOID SHATTERING!");
            
            if ($this->ticks % 4 === 0) {
                $startPos = new Vector3($this->player->x, $this->player->y + 1.5, $this->player->z);
                $endPos = new Vector3($this->target->x, $this->target->y + 1, $this->target->z);
                $this->spawnTripleBeam($startPos, $endPos, $mode);
                $this->target->attack($this->damage / 4, new EntityDamageByEntityEvent($this->player, $this->target, EntityDamageEvent::CAUSE_MAGIC, $this->damage / 4));
                $this->level->addSound(new FizzSound(new Vector3($this->target->x, $this->target->y, $this->target->z)));
            }
            return;
        }

        if ($this->ticks > 65) {
            if ($this->target !== null && $this->target->isAlive()) {
                $dirVector = $this->player->getDirectionVector();
                $this->target->setMotion(new Vector3($dirVector->x * 3.2, 0.75, $dirVector->z * 3.2));
                $this->level->addSound(new ExplodeSound(new Vector3($this->target->x, $this->target->y, $this->target->z)));
                $this->level->addParticle(new HugeExplodeParticle(new Vector3($this->target->x, $this->target->y, $this->target->z)));
                for($i=0; $i<10; $i++) $this->level->addParticle(new DustParticle($this->target->getPosition()->add(mt_rand(-2,2), mt_rand(0,3), mt_rand(-2,2)), 0, 0, 0));
                $debris = BlockEffects::spawnDebris($this->plugin, $this->level, $this->target->x, $this->target->y, $this->target->z, 8, 0.6, 1.4);
                $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask(new DarkQuakeDebrisTask($this->plugin, $this->level, $debris), 1);
            }
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
        }
    }

    private function spawnJaggedCrack(Vector3 $pos, $mode) {
        for ($i = 0; $i < 8; $i++) {
            $angle = ($i / 8) * M_PI * 2;
            $currentPos = clone $pos;
            for ($step = 0; $step < 10; $step++) {
                $jit = new Vector3(mt_rand(-6, 6) / 10, mt_rand(-6, 6) / 10, mt_rand(-6, 6) / 10);
                $next = $currentPos->add((new Vector3(cos($angle), sin($angle * 0.6), sin($angle)))->multiply(0.5))->add($jit);
                $this->level->addParticle(new DustParticle($next, 255, 255, 255));
                if ($mode === "dark") {
                    $this->level->addParticle(new PortalParticle($next));
                    $this->level->addParticle(new DustParticle($next, 0, 0, 0));
                } else {
                    $this->level->addParticle(new RedstoneParticle($next));
                    $this->level->addParticle(new CriticalParticle($next));
                }
                $currentPos = $next;
            }
        }
    }

    private function spawnTripleBeam(Vector3 $start, Vector3 $end, $mode) {
        $distance = $start->distance($end);
        if($distance < 0.1) return;
        $beamDir = $end->subtract($start)->normalize();
        for ($i = 0; $i < $distance; $i += 0.4) {
            $point = $start->add($beamDir->multiply($i));
            if ($mode === "dark") {
                $this->level->addParticle(new PortalParticle($point));
                $this->level->addParticle(new DustParticle($point, 0, 0, 0));
                $this->level->addParticle(new SmokeParticle($point));
            } else {
                $this->level->addParticle(new RedstoneParticle($point));
                $this->level->addParticle(new FlameParticle($point));
                $this->level->addParticle(new CriticalParticle($point));
            }
            if (mt_rand(0, 1) === 1) $this->level->addParticle(new DustParticle($point, 255, 255, 255));
        }
    }
}

class ShadowTsunamiTask extends Task {
    private $plugin, $player, $level, $damage, $maxRadius, $ticks = 0, $waves = [], $homies;
    private $waveSpeed = 0.65;
    private $hitEntities = [];
    private $allSpawnedEids = [];
    private $originalTime = 6000;

    public function __construct($plugin, Player $player, $damage, $maxRadius, DarkQuakePassiveTask $homies) {
        $this->plugin = $plugin; $this->player = $player; $this->level = $player->getLevel(); $this->damage = $damage; $this->maxRadius = $maxRadius; $this->homies = $homies;
        $this->originalTime = $this->level->getTime();
        DarkXQuake::$invulnerable[$player->getName()] = true;
    }

    public function onRun($currentTick) {
        $this->ticks++;
        if (!$this->player->isOnline() || $this->player->closed) { $this->forceCleanup(); return; }

        if ($this->ticks < 20) {
            $this->player->setMotion(new Vector3(0, 0, 0));
            if ($this->ticks === 1) {
                // World Darkness Logic
                if ($this->level !== null) $this->level->setTime(18000);
                
                $pos = $this->player->getPosition()->add(0, 1.5, 0);
                $this->spawnAirTriggerCrack($pos);
                $this->level->addSound(new AnvilFallSound($pos));
                foreach ($this->level->getEntities() as $e) {
                    if ($e === $this->player || !$e->isAlive() || $e->distance($pos) > 6) continue;
                    if ($e instanceof Player && !$this->plugin->canTargetPlayer($this->player->getName(), $e)) continue;
                    elseif (!($e instanceof NPCEntity) && !($e instanceof FactoryEntity)) continue;
                    $e->attack($this->damage / 3, new EntityDamageByEntityEvent($this->player, $e, EntityDamageEvent::CAUSE_MAGIC, $this->damage / 3));
                    $e->setMotion(new Vector3(0, 0.5, 0));
                }
            }
            return;
        }

        if ($this->ticks === 20) {
            $modeMeta = ($this->homies->blockId === 49) ? 15 : 14;
            for ($w = 0; $w < 2; $w++) {
                $r = $this->maxRadius + ($w * 6.5);
                $waveBlocks = [];
                for ($i = 0; $i < 18; $i++) {
                    $angle = ($i / 18) * M_PI * 2;
                    for ($ring = 0; $ring < 3; $ring++) {
                        $eid = BlockEffects::newEid();
                        $x = $this->player->x + cos($angle) * ($r - ($ring * 0.3));
                        $z = $this->player->z + sin($angle) * ($r - ($ring * 0.3));
                        $y = $this->player->y + ($ring * 1.2);
                        $meta = ($i % 3 === 0) ? $modeMeta : 3;
                        BlockEffects::sendSpawn($this->level, $eid, 35, $meta, $x, $y, $z);
                        $waveBlocks[$eid] = ["angle" => (float)$angle, "radius" => (float)$r, "ring" => $ring, "x" => (float)$x, "y" => (float)$y, "z" => (float)$z];
                        $this->allSpawnedEids[$eid] = true;
                    }
                }
                $this->waves[$w] = ["radius" => (float)$r, "startRadius" => (float)$r, "blocks" => $waveBlocks, "active" => true];
            }
            $this->level->addSound(new SplashSound($this->player->getPosition()));
        }

        if ($this->ticks > 20) {
            $this->player->setMotion(new Vector3(0, 0, 0));
            $allDone = true;

            foreach ($this->waves as $wIndex => &$waveData) {
                if (!$waveData["active"]) continue;
                $waveData["radius"] -= $this->waveSpeed;

                if ($waveData["radius"] <= 2.5) {
                    BlockEffects::voidAndRemove($this->plugin, $this->level, array_keys($waveData["blocks"]));
                    $waveData["blocks"] = [];
                    $waveData["active"] = false;
                    $this->level->addSound(new SplashSound(new Vector3($this->player->x, $this->player->y, $this->player->z)));
                    continue;
                }

                $allDone = false;
                $progress = 1.0 - ($waveData["radius"] / $waveData["startRadius"]);

                foreach ($waveData["blocks"] as $eid => &$blockData) {
                    $ring = $blockData["ring"];
                    $curl = $ring * $progress * 0.8;
                    $nx = $this->player->x + cos($blockData["angle"]) * ($waveData["radius"] - $curl);
                    $nz = $this->player->z + sin($blockData["angle"]) * ($waveData["radius"] - $curl);
                    $ny = $this->player->y + ($ring * 1.2) + sin($this->ticks * 0.3 + $blockData["angle"]) * 0.4;
                    BlockEffects::sendMove($this->level, $eid, $nx, $ny, $nz, $this->ticks * 20);
                    $blockData["x"] = $nx; $blockData["y"] = $ny; $blockData["z"] = $nz;
                }
                unset($blockData);

                $innerRadius = $waveData["radius"] - 4.0;
                $outerRadius = $waveData["radius"] + 2.0;

                foreach ($this->level->getEntities() as $e) {
                    if ($e === $this->player || !$e->isAlive() || $e->closed) continue;
                    if ($e instanceof Player) { if (!$this->plugin->canTargetPlayer($this->player->getName(), $e)) continue; }
                    elseif (!($e instanceof NPCEntity) && !($e instanceof FactoryEntity)) continue;

                    $dx = $e->x - $this->player->x;
                    $dz = $e->z - $this->player->z;
                    $dist = sqrt($dx*$dx + $dz*$dz);

                    $hitKey = $wIndex . "_" . $e->getId();
                    if ($dist >= $innerRadius && $dist <= $outerRadius && !isset($this->hitEntities[$hitKey])) {
                        $this->hitEntities[$hitKey] = true;
                        $e->attack($this->damage * 0.6, new EntityDamageByEntityEvent($this->player, $e, EntityDamageEvent::CAUSE_MAGIC, $this->damage * 0.6));
                        $len = $dist > 0 ? $dist : 1;
                        $e->setMotion(new Vector3(-($dx/$len)*0.5, 0.3, -($dz/$len)*0.5));
                        if ($e instanceof Player) $e->sendTip(TextFormat::DARK_GRAY . TextFormat::BOLD . "SHADOW TSUNAMI!");
                    }
                }

                if ($this->ticks % 3 === 0) {
                    for ($i = 0; $i < 6; $i++) {
                        $a = mt_rand(0, 628) / 100;
                        $this->level->addParticle(new DustParticle(new Vector3($this->player->x + cos($a) * $waveData["radius"], $this->player->y + 0.5, $this->player->z + sin($a) * $waveData["radius"]), 255, 255, 255));
                        $this->level->addParticle(new PortalParticle(new Vector3($this->player->x + cos($a) * $waveData["radius"], $this->player->y + 1.5, $this->player->z + sin($a) * $waveData["radius"])));
                    }
                }
            }
            unset($waveData);

            if ($allDone || $this->ticks > 135) { $this->explode(); return; }
        }
    }

    private function spawnAirTriggerCrack(Vector3 $pos) {
        for ($i = 0; $i < 8; $i++) {
            $angle = ($i / 8) * M_PI * 2;
            $curr = clone $pos;
            for ($s = 0; $s < 8; $s++) {
                $jit = new Vector3(mt_rand(-5, 5) / 10, mt_rand(-5, 5) / 10, mt_rand(-5, 5) / 10);
                $next = $curr->add((new Vector3(cos($angle), sin($angle), 0))->multiply(0.6))->add($jit);
                $this->level->addParticle(new PortalParticle($next));
                $this->level->addParticle(new RedstoneParticle($next));
                $curr = $next;
            }
        }
    }

    private function explode() {
        $pos = new Vector3($this->player->x, $this->player->y, $this->player->z);
        $this->level->addSound(new ExplodeSound($pos));
        $this->level->addSound(new AnvilFallSound($pos));
        $this->level->addParticle(new HugeExplodeParticle($pos));
        $debris = BlockEffects::spawnDebris($this->plugin, $this->level, $pos->x, $pos->y, $pos->z, 12, 0.8, 2.0);
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask(new DarkQuakeDebrisTask($this->plugin, $this->level, $debris), 1);
        for ($i = 0; $i < 60; $i++) {
            $offset = new Vector3(mt_rand(-5, 5), mt_rand(0, 6), mt_rand(-5, 5));
            $this->level->addParticle(new PortalParticle($pos->add($offset->x, $offset->y, $offset->z)));
            $this->level->addParticle(new DustParticle($pos->add($offset->x, $offset->y, $offset->z), 0, 0, 0));
            if ($i % 2 === 0) $this->level->addParticle(new RedstoneParticle($pos->add($offset->x, $offset->y, $offset->z)));
        }
        foreach ($this->level->getEntities() as $e) {
            if ($e === $this->player || !$e->isAlive() || $e->distance($pos) > 10) continue;
            if ($e instanceof Player) { if (!$this->plugin->canTargetPlayer($this->player->getName(), $e)) continue; }
            elseif (!($e instanceof NPCEntity) && !($e instanceof FactoryEntity)) continue;
            $e->attack($this->damage, new EntityDamageByEntityEvent($this->player, $e, EntityDamageEvent::CAUSE_MAGIC, $this->damage));
            $e->setMotion(new Vector3(0, 1.8, 0));
        }
        $this->forceCleanup();
    }

    private function forceCleanup() {
        // Time Restoration Logic
        if ($this->level !== null) $this->level->setTime(6000);
        
        unset(DarkXQuake::$invulnerable[$this->player->getName()]);
        if (!empty($this->allSpawnedEids)) BlockEffects::voidAndRemove($this->plugin, $this->level, array_keys($this->allSpawnedEids));
        $this->waves = [];
        $this->allSpawnedEids = [];
        $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
    }
}

class DarkQuakeDebrisTask extends Task {
    private $plugin, $level, $debris;
    public function __construct($plugin, Level $level, array $debris) { $this->plugin = $plugin; $this->level = $level; $this->debris = $debris; }
    public function onRun($currentTick) {
        $toRemove = BlockEffects::tickDebris($this->debris, $this->level, -50, 0.08, 0.95);
        foreach ($toRemove as $eid) unset($this->debris[$eid]);
        if (empty($this->debris)) $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
    }
}