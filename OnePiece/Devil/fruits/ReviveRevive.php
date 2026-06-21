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
use pocketmine\level\particle\SporeParticle;
use pocketmine\level\particle\EnchantParticle;
use pocketmine\level\particle\HappyVillagerParticle;
use pocketmine\level\particle\HugeExplodeParticle;
use pocketmine\level\sound\FizzSound;
use pocketmine\level\sound\EndermanTeleportSound;
use pocketmine\level\sound\PopSound;
use pocketmine\level\sound\ExplodeSound;
use pocketmine\network\protocol\LevelEventPacket;
use OnePiece\NPC\NPCEntity;
use OnePieceTrades\Factory\FactoryEntity;

class ReviveRevive extends BaseFruit {

    const COL_GHOST_R = 50;
    const COL_GHOST_G = 255;
    const COL_GHOST_B = 100;
    const EV_SPLASH = 2002;
    const COL_SPLASH_GREEN = 3342180;

    public function getId() { return "revive_revive"; }
    public function getDisplayName() { return "Revive-Revive Fruit"; }
    public function getDescription() { return "Control the souls of the underworld!"; }
    public function getType() { return "paramecia"; }
    public function getRarity() { return "legendary"; }

    public function getAbilityNames() {
        return [
            "ability1" => "Wandering Souls",
            "ability2" => "Ghost Hurricane"
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
                return $this->wanderingSouls($player);
            case "ability2":
                return $this->ghostHurricane($player);
        }
        return 0;
    }

    private function wanderingSouls(Player $player) {
        $mult = $this->getCombinedMultiplier($player);
        $baseDamage = min(9.0, 4.5 * $mult);
        $maxTicks = (int)$this->getMasteryDuration($player, 35);

        $task = new WanderingSoulsTask($this->plugin, $player, $baseDamage, $maxTicks);
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($task, 1);
        $player->sendTip(TextFormat::GREEN . TextFormat::BOLD . "WANDERING SOULS!");
        $player->getLevel()->addSound(new EndermanTeleportSound($player->getPosition()));
        
        // FIXED COOLDOWN
        return $this->getAbilityCooldowns()["ability1"];
    }

    private function ghostHurricane(Player $player) {
        $mult = $this->getCombinedMultiplier($player);
        $baseDamage = min(10.0, 2.5 * $mult);
        $maxRadius = $this->getMasteryRange($player, 8.0);
        $maxTicks = (int)$this->getMasteryDuration($player, 60);

        $task = new GhostHurricaneTask($this->plugin, $player, $baseDamage, $maxRadius, $maxTicks);
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($task, 1);
        $player->sendTip(TextFormat::GREEN . TextFormat::BOLD . "GHOST HURRICANE!");
        $player->getLevel()->addSound(new EndermanTeleportSound($player->getPosition()));
        
        // FIXED COOLDOWN
        return $this->getAbilityCooldowns()["ability2"];
    }

    public static function sendSplash(Level $lv, $x, $y, $z) {
        $pk = new LevelEventPacket();
        $pk->evid = self::EV_SPLASH;
        $pk->data = self::COL_SPLASH_GREEN;
        $pk->x = (float)$x;
        $pk->y = (float)$y;
        $pk->z = (float)$z;
        foreach ($lv->getPlayers() as $pl) { $pl->dataPacket($pk); }
    }

    public function onEquip(Player $player) {
        $player->sendMessage(TextFormat::GREEN . "=== Yomi-Yomi no Mi ===");
        $player->sendMessage(TextFormat::WHITE . "Revive-Revive Fruit - Master of Souls (Legendary)");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::GREEN . "[Ability 1]: " . TextFormat::WHITE . "WANDERING SOULS");
        $player->sendMessage(TextFormat::GRAY . "  Piercing ghost wave that erupts on hit");
        $player->sendMessage(TextFormat::GREEN . "[Ability 2]: " . TextFormat::WHITE . "GHOST HURRICANE");
        $player->sendMessage(TextFormat::GRAY . "  Trapping tornado - Lift and orbit enemies");
        $player->sendMessage(TextFormat::GREEN . "========================");
    }

    public function onUnequip(Player $player) {
        $player->sendMessage(TextFormat::GRAY . "The spirits fade away...");
    }
}

class WanderingSoulsTask extends Task {

    private $plugin, $player, $level, $baseDamage, $ticksRan = 0, $maxTicks;
    private $hitCounts = [];
    private $ghostX, $ghostY, $ghostZ, $dirX, $dirY, $dirZ;
    private $debris = [], $isFading = false;

    public function __construct($plugin, Player $player, $baseDamage, $maxTicks) {
        $this->plugin = $plugin;
        $this->player = $player;
        $this->level = $player->getLevel();
        $this->baseDamage = $baseDamage;
        $this->maxTicks = $maxTicks;
        $pos = $player->getPosition();
        $this->ghostX = $pos->x;
        $this->ghostY = $pos->y + $player->getEyeHeight();
        $this->ghostZ = $pos->z;
        $dir = $player->getDirectionVector()->normalize();
        $this->dirX = $dir->x * 1.5;
        $this->dirY = $dir->y * 1.5;
        $this->dirZ = $dir->z * 1.5;
    }

    public function onRun($currentTick) {
        if ($this->player === null || !$this->player->isOnline() || !$this->player->isAlive()) { $this->cleanup(); return; }
        if ($this->isFading) {
            if (!empty($this->debris)) {
                $toRemove = BlockEffects::tickDebris($this->debris, $this->level, $this->ghostY - 50, 0.05, 0.95);
                foreach ($toRemove as $eid) unset($this->debris[$eid]);
            } else { $this->cleanup(); }
            return;
        }
        $this->ticksRan++;
        if ($this->ticksRan > $this->maxTicks) { $this->isFading = true; return; }
        $this->ghostX += $this->dirX; $this->ghostY += $this->dirY; $this->ghostZ += $this->dirZ;
        $this->spawnGhostVFX();
        $this->checkHits();
        if ($this->ticksRan % 4 === 0) $this->level->addSound(new FizzSound(new Vector3($this->ghostX, $this->ghostY, $this->ghostZ)));
        if (!empty($this->debris)) {
            $toRemove = BlockEffects::tickDebris($this->debris, $this->level, $this->ghostY - 50, 0.05, 0.95);
            foreach ($toRemove as $eid) unset($this->debris[$eid]);
        }
    }

    private function spawnGhostVFX() {
        for ($i = 0; $i < 10; $i++) {
            $ox = (mt_rand(-15, 15) / 10); $oy = (mt_rand(-15, 15) / 10); $oz = (mt_rand(-15, 15) / 10);
            $this->level->addParticle(new DustParticle(new Vector3($this->ghostX + $ox, $this->ghostY + $oy, $this->ghostZ + $oz), 100, 255, 150));
        }
        $this->level->addParticle(new HappyVillagerParticle(new Vector3($this->ghostX, $this->ghostY, $this->ghostZ)));
        $this->level->addParticle(new SmokeParticle(new Vector3($this->ghostX, $this->ghostY, $this->ghostZ)));
    }

    private function checkHits() {
        $radius = 3.5;
        $ghostVec = new Vector3($this->ghostX, $this->ghostY, $this->ghostZ);
        foreach ($this->level->getEntities() as $entity) {
            if ($entity === $this->player || !$entity->isAlive()) continue;
            if ($entity instanceof Player) { if (!$this->plugin->canTargetPlayer($this->player->getName(), $entity)) continue; }
            elseif (!($entity instanceof NPCEntity) && !($entity instanceof FactoryEntity)) continue;
            if ($entity->distance($ghostVec) <= $radius) {
                $eid = $entity->getId();
                if (!isset($this->hitCounts[$eid])) $this->hitCounts[$eid] = 0;
                if ($this->hitCounts[$eid] > 4) continue; 
                $actualDamage = max(1.5, $this->baseDamage - ($this->hitCounts[$eid] * 1.0));
                $ev = new EntityDamageByEntityEvent($this->player, $entity, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $actualDamage);
                $entity->attack($actualDamage, $ev);
                if ($this->hitCounts[$eid] === 0) {
                    $found = BlockEffects::scanBlocks($this->level, $entity->x, $entity->y, $entity->z, 2, 5);
                    $newDebris = [];
                    foreach($found as $b) {
                        $eDebris = BlockEffects::newEid();
                        BlockEffects::sendSpawn($this->level, $eDebris, $b["id"], $b["damage"], $entity->x, $entity->y - 0.5, $entity->z);
                        $newDebris[$eDebris] = ["eid" => $eDebris, "x" => $entity->x, "y" => $entity->y - 0.5, "z" => $entity->z, "vx" => (mt_rand(-10,10)/20), "vy" => 0.5, "vz" => (mt_rand(-10,10)/20), "life" => 20, "tick" => 0];
                    }
                    foreach ($newDebris as $did => $d) $this->debris[$did] = $d;
                }
                $this->hitCounts[$eid]++;
                ReviveRevive::sendSplash($this->level, $entity->x, $entity->y + 1, $entity->z);
            }
        }
    }

    private function cleanup() {
        if (!empty($this->debris)) BlockEffects::voidAndRemove($this->plugin, $this->level, array_keys($this->debris));
        $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
    }
}

class GhostHurricaneTask extends Task {

    private $plugin, $player, $level, $damage, $ticksRan = 0, $maxRadius, $maxTicks;
    private $angle = 0.0, $trappedEntities = [], $debris = [], $isExploded = false, $postExplosionTicks = 0;

    public function __construct($plugin, Player $player, $damage, $maxRadius, $maxTicks) {
        $this->plugin = $plugin;
        $this->player = $player;
        $this->level = $player->getLevel();
        $this->damage = $damage;
        $this->maxRadius = $maxRadius;
        $this->maxTicks = $maxTicks;
    }

    public function onRun($currentTick) {
        if ($this->player === null || !$this->player->isOnline() || !$this->player->isAlive()) { $this->cleanup(); return; }
        $pos = $this->player->getPosition();
        if ($this->isExploded) {
            $this->postExplosionTicks++;
            if ($this->postExplosionTicks > 35) { $this->cleanup(); return; }
            if (!empty($this->debris)) {
                $toRemove = BlockEffects::tickDebris($this->debris, $this->level, -50, 0.12, 0.85);
                foreach ($toRemove as $eid) unset($this->debris[$eid]);
            }
            return;
        }
        $this->ticksRan++;
        $res = Effect::getEffect(Effect::DAMAGE_RESISTANCE);
        $res->setAmplifier(5); $res->setDuration(10); $res->setVisible(false);
        $this->player->addEffect($res);
        if ($this->ticksRan > $this->maxTicks) { $this->finalBurst($pos); $this->isExploded = true; return; }
        $this->angle += 0.5;
        $currentRadius = min($this->maxRadius, 2.0 + ($this->ticksRan * 0.15));
        $this->spawnHurricaneVFX($pos, $currentRadius);
        $this->trapAndOrbitEntities($pos, $this->maxRadius + 4.0); 
        if ($this->ticksRan % 4 === 0) {
            $found = BlockEffects::scanBlocks($this->level, $pos->x, $pos->y, $pos->z, 10, 1);
            $b = $found[0];
            $eid = BlockEffects::newEid();
            BlockEffects::sendSpawn($this->level, $eid, $b["id"], $b["damage"], $pos->x + cos($this->angle)*$currentRadius, $pos->y - 0.5, $pos->z + sin($this->angle)*$currentRadius);
            $this->debris[$eid] = ["eid" => $eid, "angle" => $this->angle, "radius" => $currentRadius, "baseY" => $pos->y, "life" => 15, "tick" => 0];
        }
        if (!empty($this->debris)) {
            $toRemove = BlockEffects::tickSpiralDebris($this->debris, $this->level, $pos->x, $pos->z, 0.35, 0.2, 0.05);
            foreach ($toRemove as $eid) unset($this->debris[$eid]);
        }
    }

    private function spawnHurricaneVFX(Vector3 $pos, $radius) {
        for ($i = 0; $i < 6; $i++) {
            $offsetAngle = $this->angle + ($i * M_PI / 3);
            $x = $pos->x + cos($offsetAngle) * $radius;
            $z = $pos->z + sin($offsetAngle) * $radius;
            $y = $pos->y + ($this->ticksRan % 30) * 0.2;
            $this->level->addParticle(new DustParticle(new Vector3($x, $y, $z), 50, 255, 100));
            $this->level->addParticle(new SporeParticle(new Vector3($x, $y + 0.5, $z)));
        }
    }

    private function trapAndOrbitEntities(Vector3 $pos, $pullRadius) {
        foreach ($this->level->getEntities() as $entity) {
            if ($entity === $this->player || !$entity->isAlive()) continue;
            if ($entity instanceof Player) { if (!$this->plugin->canTargetPlayer($this->player->getName(), $entity)) continue; }
            elseif (!($entity instanceof NPCEntity) && !($entity instanceof FactoryEntity)) continue;
            $dist = $entity->distance($pos);
            if ($dist <= $pullRadius) {
                $this->trappedEntities[$entity->getId()] = $entity;
                $dx = $pos->x - $entity->x; $dz = $pos->z - $entity->z; $len = sqrt($dx * $dx + $dz * $dz);
                $hoverY = $pos->y + 2.5; $motY = ($entity->y < $hoverY) ? 0.22 : -0.1;
                
                // STUN: Force Motion to 0 before applying vortex forces
                $entity->setMotion(new Vector3(0, 0, 0));

                if ($len > 0.5) {
                    $pullX = ($dx / $len) * 0.45;
                    $pullZ = ($dz / $len) * 0.45;
                    $orbX = (-$dz / $len) * 0.65;
                    $orbZ = ($dx / $len) * 0.65;
                    BaseFruit::staticSafeSetMotion($this->player, $entity, new Vector3($pullX + $orbX, $motY, $pullZ + $orbZ));
                } else { BaseFruit::staticSafeSetMotion($this->player, $entity, new Vector3(0, $motY, 0)); }
                if ($this->ticksRan % 10 === 0 && $dist <= $this->maxRadius) {
                    $ev = new EntityDamageByEntityEvent($this->player, $entity, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->damage * 0.4);
                    $entity->attack($this->damage * 0.4, $ev);
                    ReviveRevive::sendSplash($this->level, $entity->x, $entity->y + 1, $entity->z);
                }
            }
        }
    }

    private function finalBurst(Vector3 $pos) {
        $this->level->addSound(new ExplodeSound($pos));
        $this->level->addParticle(new HugeExplodeParticle($pos));
        if (!empty($this->debris)) { BlockEffects::voidAndRemove($this->plugin, $this->level, array_keys($this->debris)); $this->debris = []; }
        $found = BlockEffects::scanBlocks($this->level, $pos->x, $pos->y, $pos->z, 6, 8);
        foreach ($found as $i => $b) {
            $angle = ($i / 8) * M_PI * 2 + (mt_rand(-30, 30) / 100);
            $speed = 1.0 + (mt_rand(0, 100) / 100) * 1.5;
            $eid = BlockEffects::newEid();
            BlockEffects::sendSpawn($this->level, $eid, $b["id"], $b["damage"], $pos->x, $pos->y + 0.5, $pos->z);
            $this->debris[$eid] = ["eid" => $eid, "x" => $pos->x, "y" => $pos->y + 0.5, "z" => $pos->z, "vx" => cos($angle) * $speed, "vy" => 0.6, "vz" => sin($angle) * $speed, "life" => 30, "tick" => 0];
        }
        foreach ($this->trappedEntities as $entity) {
            if ($entity !== null && $entity->isAlive() && !$entity->closed && $entity->distance($pos) <= 10.0) {
                $ev = new EntityDamageByEntityEvent($this->player, $entity, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->damage * 1.5);
                $entity->attack($this->damage * 1.5, $ev);
                $dx = $entity->x - $pos->x; $dz = $entity->z - $pos->z; $len = sqrt($dx * $dx + $dz * $dz);
                if ($len > 0) BaseFruit::staticSafeSetMotion($this->player, $entity, new Vector3($dx / $len * 2.0, 0.8, $dz / $len * 2.0));
            }
        }
    }

    private function cleanup() {
        if (!empty($this->debris)) BlockEffects::voidAndRemove($this->plugin, $this->level, array_keys($this->debris));
        if ($this->player !== null && $this->player->isOnline()) $this->player->removeEffect(Effect::DAMAGE_RESISTANCE);
        $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
    }
}