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
use pocketmine\level\particle\FlameParticle;
use pocketmine\level\particle\SmokeParticle;
use pocketmine\level\particle\HugeExplodeParticle;
use pocketmine\level\sound\FizzSound;
use pocketmine\level\sound\ExplodeSound;
use pocketmine\level\sound\GhastShootSound;
use pocketmine\level\sound\GhastSound;
use OnePiece\NPC\NPCEntity;
use OnePieceTrades\Factory\FactoryEntity;

class MaguMagu extends BaseFruit {

    public function getId() { return "magu_magu"; }
    public function getDisplayName() { return "Magma-Magma Fruit"; }
    public function getDescription() { return "Absolute lethality! Burn the world to ashes."; }
    public function getType() { return "logia"; }
    public function getRarity() { return "legendary"; }

    public function getAbilityNames() {
        return [
            "ability1" => "Magma Fist",
            "ability2" => "Magma Hound"
        ];
    }

    public function getAbilityCooldowns() {
        return [
            "ability1" => 10.0,
            "ability2" => 25.0
        ];
    }

    public function useAbility(Player $player, $ability) {
        switch ($ability) {
            case "ability1":
                return $this->magmaFist($player);
            case "ability2":
                return $this->magmaHound($player);
        }
        return 0;
    }

    private function magmaFist(Player $player) {
        $mult = $this->getCombinedMultiplier($player);
        $damage = min(10, 5.0 * $mult);
        $radius = $this->getMasteryRange($player, 6.0);

        $task = new MagmaFistTask($this->plugin, $player, $damage, $radius);
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($task, 1);

        $player->sendTip(TextFormat::RED . TextFormat::BOLD . "DAI FUNKA!");
        $player->getLevel()->addSound(new GhastShootSound($player->getPosition()));

        return $this->getAbilityCooldowns()["ability1"];
    }

    private function magmaHound(Player $player) {
        $mult = $this->getCombinedMultiplier($player);
        $damage = min(10, 2.5 * $mult);
        $radius = $this->getMasteryRange($player, 10.0);
        $duration = (int)$this->getMasteryDuration($player, 80); // Scales up to 6s

        $task = new MagmaHoundTask($this->plugin, $player, $damage, $radius, $duration);
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($task, 1);

        $player->sendTip(TextFormat::RED . TextFormat::BOLD . "INUGAMI GUREN!");
        $player->getLevel()->addSound(new GhastSound($player->getPosition()));

        return $this->getAbilityCooldowns()["ability2"];
    }

    public function onEquip(Player $player) {
        $player->sendMessage(TextFormat::RED . "=== Magu-Magu no Mi ===");
        $player->sendMessage(TextFormat::GOLD . "Magma-Magma Fruit - Absolute Lethality (Legendary)");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::RED . "[Ability 1]: " . TextFormat::WHITE . "MAGMA FIST");
        $player->sendMessage(TextFormat::GRAY . "  Fires a giant magma fist leaving a burning puddle");
        $player->sendMessage(TextFormat::RED . "[Ability 2]: " . TextFormat::WHITE . "MAGMA HOUND");
        $player->sendMessage(TextFormat::GRAY . "  Transform into a magma beast, dragging enemies into an orbit");
        $player->sendMessage(TextFormat::RED . "========================");
    }

    public function onUnequip(Player $player) {
        $player->sendMessage(TextFormat::GRAY . "The magma cools into solid rock...");
    }
}

class MagmaFistTask extends Task {

    private $plugin, $player, $level, $damage, $radius, $ticksRan = 0, $maxTicks = 35;
    private $x, $y, $z, $dirX, $dirY, $dirZ, $fistEids = [];

    public function __construct($plugin, Player $player, $damage, $radius) {
        $this->plugin = $plugin;
        $this->player = $player;
        $this->level = $player->getLevel();
        $this->damage = $damage;
        $this->radius = $radius;
        $pos = $player->getPosition();
        $dir = $player->getDirectionVector()->normalize();
        $this->x = $pos->x + $dir->x * 1.5; $this->y = $pos->y + $player->getEyeHeight(); $this->z = $pos->z + $dir->z * 1.5;
        $speed = 1.6 + ($this->plugin->getMasteryManager()->getLevel($player->getName()) / 100);
        $this->dirX = $dir->x * $speed; $this->dirY = $dir->y * $speed; $this->dirZ = $dir->z * $speed;
        $blocks = [291, 415]; 
        for ($i = 0; $i < 3; $i++) {
            $eid = BlockEffects::newEid();
            BlockEffects::sendSpawn($this->level, $eid, $blocks[array_rand($blocks)], 0, $this->x, $this->y, $this->z);
            $this->fistEids[] = $eid;
        }
    }

    public function onRun($currentTick) {
        if ($this->player === null || !$this->player->isOnline()) { $this->cleanup(); return; }
        $this->ticksRan++;
        if ($this->ticksRan > $this->maxTicks) { $this->explode(); return; }
        $this->x += $this->dirX; $this->y += $this->dirY; $this->z += $this->dirZ;
        if (count($this->fistEids) === 3) {
            BlockEffects::sendMove($this->level, $this->fistEids[0], $this->x, $this->y, $this->z);
            BlockEffects::sendMove($this->level, $this->fistEids[1], $this->x + 0.4, $this->y + 0.2, $this->z - 0.4);
            BlockEffects::sendMove($this->level, $this->fistEids[2], $this->x - 0.4, $this->y - 0.2, $this->z + 0.4);
        }
        for ($i = 0; $i < 4; $i++) {
            $v = new Vector3($this->x + (mt_rand(-10, 10)/10), $this->y + (mt_rand(-10, 10)/10), $this->z + (mt_rand(-10, 10)/10));
            $this->level->addParticle(new DustParticle($v, 255, 100, 0));
            $this->level->addParticle(new FlameParticle($v));
        }
        if ($this->checkCollision()) { $this->explode(); return; }
    }

private function checkCollision() {
    $posVec = new Vector3($this->x, $this->y, $this->z);
    $block = $this->level->getBlock($posVec);
    if ($block->getId() !== 0 && $block->getId() !== 8 && $block->getId() !== 9) return true;
    foreach ($this->level->getEntities() as $e) {
        if ($e === $this->player || !$e->isAlive()) continue;
        if ($e instanceof Player) { if (!$this->plugin->canTargetPlayer($this->player->getName(), $e)) continue; }
        elseif (!($e instanceof NPCEntity) && !($e instanceof FactoryEntity)) continue;
        if ($e->distance($posVec) <= 2.5) return true;
    }
    return false;
}

    private function explode() {
        if (!empty($this->fistEids)) BlockEffects::voidAndRemove($this->plugin, $this->level, $this->fistEids);
        $pos = new Vector3($this->x, $this->y, $this->z);
        $this->level->addSound(new ExplodeSound($pos));
        $this->level->addParticle(new HugeExplodeParticle($pos));
        $groundY = $this->y;
        while ($groundY > 0) {
            if ($this->level->getBlockIdAt((int)$this->x, (int)$groundY - 1, (int)$this->z) !== 0) break;
            $groundY--;
        }
        $blocks = [291, 415];
        $debris = [];
        for ($i = 0; $i < 15; $i++) {
            $deid = BlockEffects::newEid(); $angle = ($i / 15) * M_PI * 2;
            BlockEffects::sendSpawn($this->level, $deid, $blocks[array_rand($blocks)], 0, $this->x, $this->y + 0.5, $this->z);
            $debris[$deid] = ["eid" => $deid, "x" => $this->x, "y" => $this->y + 0.5, "z" => $this->z, "vx" => cos($angle)*1.2, "vy" => 0.6, "vz" => sin($angle)*1.2, "life" => 25, "tick" => 0];
        }
        foreach ($this->level->getEntities() as $e) {
            if ($e === $this->player || !$e->isAlive() || $e->distance($pos) > $this->radius) continue;
            if ($e instanceof Player) { if (!$this->plugin->canTargetPlayer($this->player->getName(), $e)) continue; }
            elseif (!($e instanceof NPCEntity) && !($e instanceof FactoryEntity)) continue;
            $e->attack($this->damage, new EntityDamageByEntityEvent($this->player, $e, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->damage));
            $e->setOnFire(5);
            $dx = $e->x - $this->x; $dz = $e->z - $this->z; $len = sqrt($dx*$dx+$dz*$dz);
            if ($len > 0) BaseFruit::staticSafeSetMotion($this->player, $e, new Vector3($dx/$len*1.5, 0.6, $dz/$len*1.5));
        }
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask(new MagmaPuddleTask($this->plugin, $this->player, $this->x, $groundY, $this->z, $this->damage*0.3, $debris), 1);
        $this->cleanup();
    }

    private function cleanup() {
        if (!empty($this->fistEids)) BlockEffects::voidAndRemove($this->plugin, $this->level, $this->fistEids);
        $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
    }
}

class MagmaPuddleTask extends Task {
    private $plugin, $player, $level, $cx, $cy, $cz, $damage, $ticksRan = 0, $debris, $puddleEids = [];
    public function __construct($plugin, Player $player, $cx, $cy, $cz, $damage, $debris) {
        $this->plugin = $plugin; $this->player = $player; $this->level = $player->getLevel();
        $this->cx = $cx; $this->cy = $cy; $this->cz = $cz; $this->damage = $damage; $this->debris = $debris;
        $blocks = [291, 415]; 
        for ($i = 0; $i < 12; $i++) {
            $eid = BlockEffects::newEid(); $angle = ($i / 12) * M_PI * 2; $r = mt_rand(5, 35)/10;
            BlockEffects::sendSpawn($this->level, $eid, $blocks[array_rand($blocks)], 0, $cx + cos($angle)*$r, $cy, $cz + sin($angle)*$r);
            $this->puddleEids[] = $eid;
        }
    }
    public function onRun($currentTick) {
        $this->ticksRan++;
        if ($this->ticksRan > 100) { $this->cleanup(); return; }
        if (!empty($this->debris)) {
            $toRemove = BlockEffects::tickDebris($this->debris, $this->level, -50, 0.12, 0.90);
            foreach ($toRemove as $eid) unset($this->debris[$eid]);
        }
        for ($i = 0; $i < 3; $i++) {
            $a = mt_rand(0, 628)/100; $r = mt_rand(0, 40)/10;
            $this->level->addParticle(new DustParticle(new Vector3($this->cx + cos($a)*$r, $this->cy + 0.5, $this->cz + sin($a)*$r), 255, 100, 0));
        }
        if ($this->ticksRan % 10 === 0) {
            $this->level->addSound(new FizzSound(new Vector3($this->cx, $this->cy, $this->cz)));
foreach ($this->level->getEntities() as $e) {
    if ($e === $this->player || !$e->isAlive() || $e->distance(new Vector3($this->cx, $this->cy, $this->cz)) > 4.5) continue;
    if ($e instanceof Player) { if (!$this->plugin->canTargetPlayer($this->player->getName(), $e)) continue; }
    elseif (!($e instanceof NPCEntity) && !($e instanceof FactoryEntity)) continue;
    $e->attack($this->damage, new EntityDamageByEntityEvent($this->player, $e, EntityDamageEvent::CAUSE_MAGIC, $this->damage));
    $e->setOnFire(3);
}
        }
    }
    private function cleanup() {
        if (!empty($this->debris)) BlockEffects::voidAndRemove($this->plugin, $this->level, array_keys($this->debris));
        if (!empty($this->puddleEids)) BlockEffects::voidAndRemove($this->plugin, $this->level, $this->puddleEids);
        $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
    }
}

class MagmaHoundTask extends Task {
    private $plugin, $player, $level, $damage, $radius, $ticksRan = 0, $maxTicks, $debris = [], $isExploded = false, $postTicks = 0;
    private $angle = 0.0;

    public function __construct($plugin, Player $player, $damage, $radius, $maxTicks) {
        $this->plugin = $plugin; $this->player = $player; $this->level = $player->getLevel();
        $this->damage = $damage; $this->radius = $radius; $this->maxTicks = $maxTicks;
    }

    public function onRun($currentTick) {
        if ($this->player === null || !$this->player->isOnline()) { $this->cleanup(); return; }
        $pos = $this->player->getPosition();
        if ($this->isExploded) {
            $this->postTicks++;
            if ($this->postTicks > 35) { $this->cleanup(); return; }
            if (!empty($this->debris)) {
                $toRemove = BlockEffects::tickDebris($this->debris, $this->level, -50, 0.12, 0.85);
                foreach ($toRemove as $eid) unset($this->debris[$eid]);
            }
            return;
        }
        $this->ticksRan++;
        $this->player->addEffect(Effect::getEffect(Effect::DAMAGE_RESISTANCE)->setAmplifier(5)->setDuration(10)->setVisible(false));
        if ($this->ticksRan > $this->maxTicks) { $this->finalBurst($pos); $this->isExploded = true; return; }
        
        $this->angle += 0.5;
        $this->spawnVFX($pos); 
        $this->trapAndOrbitEntities($pos);

        if ($this->ticksRan % 4 === 0) {
            $blocks = [291, 415]; 
            $eid = BlockEffects::newEid();
            BlockEffects::sendSpawn($this->level, $eid, $blocks[array_rand($blocks)], 0, $pos->x + cos($this->angle)*3, $pos->y - 0.5, $pos->z + sin($this->angle)*3);
            $this->debris[$eid] = ["eid" => $eid, "angle" => $this->angle, "radius" => 3.0, "baseY" => $pos->y, "life" => 15, "tick" => 0];
        }
        if (!empty($this->debris)) {
            $toRemove = BlockEffects::tickSpiralDebris($this->debris, $this->level, $pos->x, $pos->z, 0.35, 0.2, 0.05);
            foreach ($toRemove as $eid) unset($this->debris[$eid]);
        }
    }

    private function spawnVFX(Vector3 $pos) {
        for ($i = 0; $i < 8; $i++) {
            $v = new Vector3($pos->x + mt_rand(-20, 20)/10, $pos->y + mt_rand(0, 30)/10, $pos->z + mt_rand(-20, 20)/10);
            $this->level->addParticle(new DustParticle($v, 255, 100, 0));
            $this->level->addParticle(new FlameParticle($v));
        }
        if ($this->ticksRan % 10 === 0) $this->level->addSound(new FizzSound($pos));
    }

    private function trapAndOrbitEntities(Vector3 $pos) {
        foreach ($this->level->getEntities() as $e) {
            if ($e === $this->player || !$e->isAlive() || $e->distance($pos) > $this->radius + 2.0) continue;
            if ($e instanceof Player) { if (!$this->plugin->canTargetPlayer($this->player->getName(), $e)) continue; }
            elseif (!($e instanceof NPCEntity) && !($e instanceof FactoryEntity)) continue;
            
            $dx = $pos->x - $e->x; $dz = $pos->z - $e->z; $len = sqrt($dx*$dx+$dz*$dz);
            $hoverY = $pos->y + 2.5; $motY = ($e->y < $hoverY) ? 0.22 : -0.1;
            
            // STUN: Clear motion first
            $e->setMotion(new Vector3(0, 0, 0));

            if ($len > 1.5) {
                $pullX = ($dx / $len) * 0.45;
                $pullZ = ($dz / $len) * 0.45;
                $orbX = (-$dz / $len) * 0.65;
                $orbZ = ($dx / $len) * 0.65;
                BaseFruit::staticSafeSetMotion($this->player, $e, new Vector3($pullX + $orbX, $motY, $pullZ + $orbZ));
            } else { 
                BaseFruit::staticSafeSetMotion($this->player, $e, new Vector3(0, $motY, 0)); 
            }

            if ($this->ticksRan % 10 === 0) {
                $e->attack($this->damage * 0.5, new EntityDamageByEntityEvent($this->player, $e, EntityDamageEvent::CAUSE_MAGIC, $this->damage * 0.5));
                $e->setOnFire(3);
            }
        }
    }

    private function finalBurst(Vector3 $pos) {
        $this->level->addSound(new ExplodeSound($pos));
        $this->level->addParticle(new HugeExplodeParticle($pos));
        if (!empty($this->debris)) { BlockEffects::voidAndRemove($this->plugin, $this->level, array_keys($this->debris)); $this->debris = []; }
        $blocks = [291, 415];
        for ($i = 0; $i < 20; $i++) {
            $eid = BlockEffects::newEid(); $a = mt_rand(0, 628)/100;
            BlockEffects::sendSpawn($this->level, $eid, $blocks[array_rand($blocks)], 0, $pos->x, $pos->y + 0.5, $pos->z);
            $this->debris[$eid] = ["eid" => $eid, "x" => $pos->x, "y" => $pos->y + 0.5, "z" => $pos->z, "vx" => cos($a)*1.5, "vy" => 0.8, "vz" => sin($a)*1.5, "life" => 30, "tick" => 0];
        }
foreach ($this->level->getEntities() as $e) {
    if ($e === $this->player || !$e->isAlive() || $e->distance($pos) > 10) continue;
    if ($e instanceof Player) { if (!$this->plugin->canTargetPlayer($this->player->getName(), $e)) continue; }
    elseif (!($e instanceof NPCEntity) && !($e instanceof FactoryEntity)) continue;
    $e->attack($this->damage * 1.8, new EntityDamageByEntityEvent($this->player, $e, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->damage * 1.8));
    $dx = $e->x - $pos->x; $dz = $e->z - $pos->z; $len = sqrt($dx*$dx+$dz*$dz);
    if ($len > 0) BaseFruit::staticSafeSetMotion($this->player, $e, new Vector3($dx/$len*2.5, 1.0, $dz/$len*2.5));
}
    }

    private function cleanup() {
        if (!empty($this->debris)) BlockEffects::voidAndRemove($this->plugin, $this->level, array_keys($this->debris));
        if ($this->player !== null && $this->player->isOnline()) $this->player->removeEffect(Effect::DAMAGE_RESISTANCE);
        $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
    }
}