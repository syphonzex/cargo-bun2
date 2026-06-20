<?php

namespace OnePiece\Devil\fruits;

use OnePiece\Devil\BaseFruit;
use OnePiece\Devil\Main;
use OnePiece\Devil\BlockEffects;
use pocketmine\Player;
use pocketmine\Server;
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
use pocketmine\level\particle\PortalParticle;
use pocketmine\level\particle\HugeExplodeParticle;
use pocketmine\level\sound\FizzSound;
use pocketmine\level\sound\ExplodeSound;
use pocketmine\level\sound\AnvilUseSound;
use pocketmine\level\sound\BlazeShootSound;
use OnePiece\NPC\NPCEntity;
use OnePieceTrades\Factory\FactoryEntity;

class YamiV2 extends BaseFruit {

    public function getId() { return "yami_v2"; }
    public function getDisplayName() { return "Dark v2"; }
    public function getDescription() { return "The absolute void... devouring all existence."; }
    public function getType() { return "logia"; }
    public function getRarity() { return "legendary"; }

    public function getAbilityNames() {
        return [
            "ability1" => "Black Hole Vortex",
            "ability2" => "Dark Star Liberation"
        ];
    }



    public function getAbilityCooldowns() {
        return ["ability1" => 32.0, "ability2" => 25.0];
    }

    public function useAbility(Player $player, $ability) {
        switch ($ability) {
            case "ability1":
                return $this->blackHoleVortex($player);
            case "ability2":
                return $this->darkStar($player);
        }
        return 0;
    }

    private function blackHoleVortex(Player $player) {
        $mm = $this->plugin->getMasteryManager();
        $mastery = ($mm !== null) ? $mm->getLevel($player->getName()) : 0;
        $bonusSec = (min(50, $mastery) / 50) * 3.0;
        $totalTicks = (int)((5.0 + $bonusSec) * 20);
        $mult = $this->getCombinedMultiplier($player);
        $damage = 0.5 * $mult;
        $task = new DarkVortexReworkTask($this->plugin, $player, $damage, $totalTicks);
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($task, 1);
        $player->sendTip(TextFormat::DARK_PURPLE . TextFormat::BOLD . "BLACK HOLE VORTEX");
        return $this->getAbilityCooldowns()["ability1"];
    }

    private function darkStar(Player $player) {
        $mult = $this->getCombinedMultiplier($player);
        $damage = 2.5 * $mult;
        $task = new DarkStarTask($this->plugin, $player, $damage);
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($task, 1);
        $player->sendTip(TextFormat::DARK_PURPLE . TextFormat::BOLD . "DARK STAR LIBERATION");
        return $this->getAbilityCooldowns()["ability2"];
    }

    public function onEquip(Player $player) {
        $player->sendMessage(TextFormat::DARK_PURPLE . "=== Dark v2 ===");
        $player->sendMessage(TextFormat::WHITE . "Darkness Fruit - Absolute Void");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::DARK_PURPLE . "[Ability 1]: " . TextFormat::WHITE . "BLACK HOLE VORTEX");
        $player->sendMessage(TextFormat::GRAY . "  Safety void that devours everything nearby");
        $player->sendMessage(TextFormat::DARK_PURPLE . "[Ability 2]: " . TextFormat::WHITE . "DARK STAR LIBERATION");
        $player->sendMessage(TextFormat::GRAY . "  Massive singularity blast with God Mode aiming");
        $player->sendMessage(TextFormat::DARK_PURPLE . "========================");
    }

    public function onUnequip(Player $player) {
        $player->sendMessage(TextFormat::GRAY . "The shadows retreat...");
    }
}

class DarkVortexReworkTask extends Task {

    private $plugin, $player, $level, $damage, $totalTicks;
    private $ticksRan = 0;
    private $cx, $cy, $cz;
    private $attachedBlocks = []; 
    private $envDebris = []; 
    private $angle = 0.0;

    public function __construct($plugin, Player $player, $damage, $totalTicks) {
        $this->plugin = $plugin;
        $this->player = $player;
        $this->level = $player->getLevel();
        $this->damage = $damage;
        $this->totalTicks = $totalTicks;
        $this->cx = $player->x;
        $this->cy = $player->y;
        $this->cz = $player->z;
        $this->level->addSound(new AnvilUseSound($player));
    }

    public function onRun($currentTick) {
        if ($this->player === null || !$this->player->isOnline() || $this->player->closed) { $this->cleanup(); return; }
        $this->ticksRan++;
        if ($this->ticksRan > $this->totalTicks) { $this->cleanup(); return; }
        $this->player->setMotion(new Vector3(0, 0, 0));
        $this->angle += 0.25;
        $this->cx = $this->player->x;
        $this->cy = $this->player->y;
        $this->cz = $this->player->z;
        $this->drawDenseVortex();
        $this->processEntities();
        $this->processEnvironment();
    }

    private function drawDenseVortex() {
        for ($i = 0; $i < 30; $i++) {
            $r = mt_rand(0, 80) / 10; $a = mt_rand(0, 628) / 100;
            $v = new Vector3($this->cx + cos($a)*$r, $this->cy + (mt_rand(0, 20)/100), $this->cz + sin($a)*$r);
            $this->level->addParticle(new SmokeParticle($v));
            if ($i % 3 === 0) { $this->level->addParticle(new PortalParticle($v)); $this->level->addParticle(new DustParticle($v, 10, 0, 20)); }
        }
        if ($this->ticksRan % 10 === 0) $this->level->addSound(new FizzSound(new Vector3($this->cx, $this->cy, $this->cz)));
    }

    private function processEntities() {
        $center = new Vector3($this->cx, $this->cy, $this->cz);
        $currentEids = [];
        foreach ($this->level->getEntities() as $e) {
            if ($e === $this->player || !$e->isAlive()) continue;
            if ($e instanceof Player) { if (!BaseFruit::pvpAllowed($this->player, $e)) continue; }
            elseif (!($e instanceof NPCEntity) && !($e instanceof FactoryEntity)) continue;
            
            $dist = $e->distance($center);
            if ($dist <= 12.0) {
                $currentEids[$e->getId()] = true;
                if (!isset($this->attachedBlocks[$e->getId()])) {
                    $beid = BlockEffects::newEid();
                    BlockEffects::sendSpawn($this->level, $beid, 49, 0, $e->x, $e->y + 1, $e->z);
                    $this->attachedBlocks[$e->getId()] = $beid;
                } else { BlockEffects::sendMove($this->level, $this->attachedBlocks[$e->getId()], $e->x, $e->y + 0.8, $e->z); }
                
                $dx = $this->cx - $e->x; $dz = $this->cz - $e->z;
                $len = sqrt($dx*$dx + $dz*$dz);
                $hoverY = $this->cy + 1.2; $motY = ($e->y < $hoverY) ? 0.22 : -0.15;
                $e->setMotion(new Vector3(0, 0, 0));

                if ($len > 6.2) { 
                    $pull = 0.04; BaseFruit::staticSafeSetMotion($this->player, $e, new Vector3(($dx/($len > 0 ? $len : 1))*$pull, $motY, ($dz/($len > 0 ? $len : 1))*$pull));
                } elseif ($len < 5.8) {
                    $repelForce = ($len < 1.0) ? 1.5 : 0.6;
                    $vecX = ($len > 0.1) ? -($dx / $len) : (mt_rand(-10, 10)/10);
                    $vecZ = ($len > 0.1) ? -($dz / $len) : (mt_rand(-10, 10)/10);
                    BaseFruit::staticSafeSetMotion($this->player, $e, new Vector3($vecX * $repelForce, $motY, $vecZ * $repelForce));
                } else { 
                    BaseFruit::staticSafeSetMotion($this->player, $e, new Vector3(0, $motY, 0));
                }
                
                if ($this->ticksRan % 20 === 0) {
                    $e->attack($this->damage, new EntityDamageByEntityEvent($this->player, $e, EntityDamageEvent::CAUSE_MAGIC, $this->damage));
                }
            }
        }
        foreach ($this->attachedBlocks as $tid => $beid) { if (!isset($currentEids[$tid])) { BlockEffects::voidAndRemove($this->plugin, $this->level, [$beid]); unset($this->attachedBlocks[$tid]); } }
    }

    private function processEnvironment() {
        if ($this->ticksRan % 8 === 0) {
            $sx = $this->cx + (mt_rand(-120, 120) / 10); $sz = $this->cz + (mt_rand(-120, 120) / 10);
            if (sqrt(($sx - $this->cx)**2 + ($sz - $this->cz)**2) >= 5) {
                $eid = BlockEffects::newEid();
                $floorY = (int)$this->cy;
                while($floorY > 0){
                    $bId = $this->level->getBlockIdAt((int)$sx, $floorY, (int)$sz);
                    if($bId !== 0 && $bId !== 8 && $bId !== 9) break;
                    $floorY--;
                }
                $bId = $this->level->getBlockIdAt((int)$sx, $floorY, (int)$sz);
                $bDm = $this->level->getBlockDataAt((int)$sx, $floorY, (int)$sz);
                if ($bId == 0) { $bId = 3; $bDm = 0; }
                BlockEffects::sendSpawn($this->level, $eid, $bId, $bDm, $sx, $floorY, $sz);
                $this->envDebris[$eid] = ["x" => $sx, "y" => (float)$floorY, "z" => $sz, "vy" => 0.45, "life" => 60, "stage" => 0, "accel" => 0.1, "baseY" => (float)$this->cy];
            }
        }
        foreach ($this->envDebris as $eid => &$data) {
            $data["life"]--; $dx = $this->cx - $data["x"]; $dz = $this->cz - $data["z"]; $len = sqrt($dx*$dx + $dz*$dz);
            if ($data["stage"] === 0) { 
                $data["y"] += $data["vy"]; $data["vy"] -= 0.04; 
                if ($data["y"] >= $data["baseY"] + 1.8) $data["stage"] = 1; 
            } else { 
                if ($len > 0.5) { 
                    $data["accel"] += 0.15; $ps = min(1.8, $data["accel"]); 
                    $data["x"] += ($dx / $len) * $ps; $data["z"] += ($dz / $len) * $ps; $data["y"] -= 0.1; 
                } 
            }
            if ($data["life"] <= 0 || $data["y"] < $data["baseY"] - 3.0 || $len < 1.0) { BlockEffects::sendRemove($eid); unset($this->envDebris[$eid]); }
            else { BlockEffects::sendMove($this->level, $eid, $data["x"], $data["y"], $data["z"], $data["life"]*25, $data["life"]*15); }
        }
    }

    private function cleanup() {
        foreach ($this->attachedBlocks as $eid) BlockEffects::voidAndRemove($this->plugin, $this->level, [$eid]);
        foreach ($this->envDebris as $eid => $d) BlockEffects::sendRemove($eid);
        $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
    }
}

class DarkStarTask extends Task {

    private $plugin, $player, $level, $damage, $ticksRan = 0, $chargeTicks = 40, $launched = false, $launchTicks = 0;
    private $x, $y, $z, $starEids = [], $currentRadius = 0.1, $targetPos = null, $dirX, $dirY, $dirZ;

    public function __construct($plugin, Player $player, $damage) {
        $this->plugin = $plugin; $this->player = $player; $this->level = $player->getLevel(); $this->damage = $damage;
        for ($i = 0; $i < 8; $i++) {
            $eid = BlockEffects::newEid();
            BlockEffects::sendSpawn($this->level, $eid, 49, 0, $player->x, $player->y + 1.5, $player->z);
            $this->starEids[] = $eid;
        }
    }

    public function onRun($currentTick) {
        if ($this->player === null || !$this->player->isOnline()) { $this->cleanup(); return; }
        $this->ticksRan++;
        if (!$this->launched) {
            $this->player->addEffect(Effect::getEffect(Effect::DAMAGE_RESISTANCE)->setAmplifier(5)->setDuration(10)->setVisible(false));
            $riseProg = min(1.0, $this->ticksRan / $this->chargeTicks);
            $this->x = $this->player->x;
            $this->y = $this->player->y + 1.5 + ($riseProg * 2.5); 
            $this->z = $this->player->z;
            $this->currentRadius = min(2.5, 0.1 + ($this->ticksRan * 0.08));
            foreach ($this->starEids as $i => $eid) {
                $phi = acos(-1 + (2 * $i) / 8); $theta = sqrt(8 * M_PI) * $phi + ($this->ticksRan * 0.35);
                $bx = $this->x + $this->currentRadius * sin($phi) * cos($theta);
                $by = $this->y + $this->currentRadius * cos($phi);
                $bz = $this->z + $this->currentRadius * sin($phi) * sin($theta);
                BlockEffects::sendMove($this->level, $eid, $bx, $by, $bz);
                if ($this->ticksRan % 3 === 0) $this->level->addParticle(new SmokeParticle(new Vector3($bx, $by, $bz)));
            }
            if ($this->ticksRan >= $this->chargeTicks) {
                $lookDir = $this->player->getDirectionVector()->normalize();
                $targetBlock = $this->player->getTargetBlock(60);
                if ($targetBlock !== null) { $this->targetPos = new Vector3($targetBlock->x + 0.5, $targetBlock->y + 0.5, $targetBlock->z + 0.5); }
                else { $this->targetPos = $this->player->add($lookDir->x * 60, $lookDir->y * 60, $lookDir->z * 60); }
                if ($this->player->pitch > 70 || $this->targetPos->distance($this->player) < 4) {
                    $this->x = $this->player->x + $lookDir->x * 2; $this->y = $this->player->y; $this->z = $this->player->z + $lookDir->z * 2;
                    $this->triggerVoidImpact(); return;
                }
                $diff = $this->targetPos->subtract(new Vector3($this->x, $this->y, $this->z)); $dist = $diff->length();
                if ($dist > 0) { $this->dirX = ($diff->x / $dist) * 3.2; $this->dirY = ($diff->y / $dist) * 3.2; $this->dirZ = ($diff->z / $dist) * 3.2; }
                $this->launched = true; $this->level->addSound(new BlazeShootSound($this->player));
            }
            return;
        }
        $this->launchTicks++; $this->applyMagneticGuidance();
        $this->x += $this->dirX; $this->y += $this->dirY; $this->z += $this->dirZ;
        foreach ($this->starEids as $i => $eid) {
            $phi = acos(-1 + (2 * $i) / 8); $theta = sqrt(8 * M_PI) * $phi + ($this->launchTicks * 0.6); $rad = 0.9; 
            BlockEffects::sendMove($this->level, $eid, $this->x + $rad*sin($phi)*cos($theta), $this->y + $rad*cos($phi), $this->z + $rad*sin($phi)*sin($theta), $this->launchTicks * 50);
        }
        for ($i = 0; $i < 6; $i++) { $v = new Vector3($this->x + mt_rand(-12,12)/10, $this->y + mt_rand(-12,12)/10, $this->z + mt_rand(-12,12)/10); $this->level->addParticle(new SmokeParticle($v)); $this->level->addParticle(new PortalParticle($v)); }
        if ($this->launchTicks > 4 && ($this->checkCollision() || $this->launchTicks > 120)) { $this->triggerVoidImpact(); }
    }

    private function applyMagneticGuidance() {
        $currentPos = new Vector3($this->x, $this->y, $this->z);
        foreach ($this->level->getEntities() as $e) {
            if ($e === $this->player || !$e->isAlive()) continue;
            if ($e instanceof Player) { if (!BaseFruit::pvpAllowed($this->player, $e)) continue; }
            elseif (!($e instanceof NPCEntity) && !($e instanceof FactoryEntity)) continue;
            if ($e->distance($currentPos) < 5.5) {
                $targetDir = $e->getPosition()->add(0, 1, 0)->subtract($currentPos)->normalize();
                $this->dirX = ($this->dirX * 0.8) + ($targetDir->x * 0.7); $this->dirY = ($this->dirY * 0.8) + ($targetDir->y * 0.7); $this->dirZ = ($this->dirZ * 0.8) + ($targetDir->z * 0.7);
                break;
            }
        }
    }

    private function checkCollision() {
        $pos = new Vector3($this->x, $this->y, $this->z);
        if ($this->level->getBlock($pos)->getId() !== 0 && $this->level->getBlock($pos)->getId() !== 8 && $this->level->getBlock($pos)->getId() !== 9) return true;
        foreach ($this->level->getEntities() as $e) {
            if ($e === $this->player || !$e->isAlive()) continue;
            if ($e instanceof Player) { if (!BaseFruit::pvpAllowed($this->player, $e)) continue; }
            elseif (!($e instanceof NPCEntity) && !($e instanceof FactoryEntity)) continue;
            if ($e->distance($pos) <= 3.8) return true;
        }
        return false;
    }

    private function triggerVoidImpact() {
        $groundY = $this->y;
        while ($groundY > 0) {
            if ($this->level->getBlockIdAt((int)$this->x, (int)$groundY - 1, (int)$this->z) !== 0) break;
            $groundY--;
        }
        $pos = new Vector3($this->x, $groundY, $this->z);
        $found = BlockEffects::scanBlocks($this->level, $pos->x, $pos->y, $pos->z, 6, 10);
        $debris = [];
        foreach ($found as $i => $b) {
            $deid = BlockEffects::newEid(); $angle = ($i / 10) * M_PI * 2;
            BlockEffects::sendSpawn($this->level, $deid, $b["id"], $b["damage"], $pos->x, $pos->y + 0.5, $pos->z);
            $debris[$deid] = ["eid" => $deid, "x" => $pos->x, "y" => $pos->y + 0.5, "z" => $pos->z, "vx" => cos($angle)*1.4, "vy" => 0.9, "vz" => sin($angle)*1.4, "life" => 45, "tick" => 0];
        }
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask(new DarkImpactDebrisTask($this->plugin, $this->level, $debris), 1);
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask(new VoidSingularityTask($this->plugin, $this->player, $pos, $this->damage), 1);
        $this->cleanup();
    }

    private function cleanup() {
        if (!empty($this->starEids)) BlockEffects::voidAndRemove($this->plugin, $this->level, $this->starEids);
        if ($this->player !== null && $this->player->isOnline()) $this->player->removeEffect(Effect::DAMAGE_RESISTANCE);
        $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
    }
}

class DarkImpactDebrisTask extends Task {
    private $plugin, $level, $debris;
    public function __construct($plugin, Level $level, array $debris) { $this->plugin = $plugin; $this->level = $level; $this->debris = $debris; }
    public function onRun($currentTick) {
        if (empty($this->debris)) { $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId()); return; }
        $toRemove = BlockEffects::tickDebris($this->debris, $this->level, -50, 0.1, 0.92);
        foreach ($toRemove as $eid) unset($this->debris[$eid]);
    }
}

class VoidSingularityTask extends Task {
    private $plugin, $player, $pos, $damage, $ticks = 0;
    public function __construct($plugin, Player $player, $pos, $damage) { $this->plugin = $plugin; $this->player = $player; $this->pos = $pos; $this->damage = $damage; }
    public function onRun($currentTick) {
        $this->ticks++;
        if ($this->ticks > 60 || $this->player === null) { $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId()); return; }
        for ($i = 0; $i < 15; $i++) {
            $a = mt_rand(0, 628)/100; $r = mt_rand(0, 70)/10;
            $v = new Vector3($this->pos->x + cos($a)*$r, $this->pos->y + mt_rand(0, 25)/10, $this->pos->z + sin($a)*$r);
            $this->player->getLevel()->addParticle(new SmokeParticle($v));
            if ($i % 3 === 0) $this->player->getLevel()->addParticle(new PortalParticle($v));
        }
        foreach ($this->player->getLevel()->getEntities() as $e) {
            if ($e === $this->player || !$e->isAlive() || $e->distance($this->pos) > 8) continue;
            if ($e instanceof Player) { if (!BaseFruit::pvpAllowed($this->player, $e)) continue; }
            elseif (!($e instanceof NPCEntity) && !($e instanceof FactoryEntity)) continue;
            $e->setMotion(new Vector3(0, 0, 0));
            if ($this->ticks % 10 === 0) {
                $e->attack($this->damage * 0.25, new EntityDamageByEntityEvent($this->player, $e, EntityDamageEvent::CAUSE_MAGIC, $this->damage * 0.25));
                $e->addEffect(Effect::getEffect(Effect::SLOWNESS)->setAmplifier(5)->setDuration(30));
            }
        }
        if ($this->ticks % 10 === 0) $this->player->getLevel()->addSound(new FizzSound($this->pos));
    }
}