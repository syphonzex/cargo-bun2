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
use pocketmine\level\particle\HugeExplodeParticle;
use pocketmine\level\particle\FlameParticle;
use pocketmine\level\particle\InstantEnchantParticle;
use pocketmine\level\particle\CriticalParticle;
use pocketmine\level\particle\PortalParticle;
use pocketmine\level\particle\LavaDripParticle;
use pocketmine\level\particle\RedstoneParticle;
use pocketmine\level\particle\EnchantParticle;
use pocketmine\level\sound\FizzSound;
use pocketmine\level\sound\ExplodeSound;
use pocketmine\level\sound\AnvilUseSound;
use pocketmine\level\sound\BlazeShootSound;
use pocketmine\level\sound\GhastShootSound;
use pocketmine\level\sound\EndermanTeleportSound;
use pocketmine\network\protocol\AddEntityPacket;
use OnePiece\NPC\NPCEntity;
use OnePieceTrades\Factory\FactoryEntity;

class SoruSoru extends BaseFruit {

    private static $homieTasks = [];

    public function getId() { return "soru_soru"; }
    public function getDisplayName() { return "Soul-Soul Fruit"; }
    public function getDescription() { return "Command the elements and manipulate the souls of the living."; }
    public function getType() { return "paramecia"; }
    public function getRarity() { return "mythical"; }

    public function getAbilityNames() {
        return ["ability1" => "Maser Cannon", "ability2" => "Zeus Sky-Ride"];
    }

    public function getAbilityCooldowns() {
        return ["ability1" => 14.0, "ability2" => 25.0];
    }

    public function useAbility(Player $player, $ability) {
        $name = $player->getName();
        if (!isset(self::$homieTasks[$name]) || !self::$homieTasks[$name]->active) {
            $task = new SoulPassiveTask($this->plugin, $player);
            $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($task, 1);
            self::$homieTasks[$name] = $task;
        }
        $task = self::$homieTasks[$name];
        switch ($ability) {
            case "ability1": return $this->maserCannon($player, $task);
            case "ability2": return $this->zeusFlight($player, $task);
        }
        return 0;
    }

    private function maserCannon(Player $player, SoulPassiveTask $homies) {
        $mult   = min(1.5, $this->getHakiMultiplier($player));
        $damage = min(12.0, 6.0 * $mult);
        $radius = $this->getMasteryRange($player, 8.0);
        $task = new MaserCannonTask($this->plugin, $player, $damage, $radius, $homies);
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($task, 1);
        $player->sendTip(TextFormat::GOLD . TextFormat::BOLD . "MASER CANNON!");
        return $this->getAbilityCooldowns()["ability1"];
    }

    private function zeusFlight(Player $player, SoulPassiveTask $homies) {
        $mult   = min(1.5, $this->getHakiMultiplier($player));
        $damage = min(9.0, 3.5 * $mult);
        $duration = (int)$this->getMasteryDuration($player, 120);
        $task = new ZeusFlightTask($this->plugin, $player, $damage, $duration, $homies);
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($task, 1);
        $player->sendTip(TextFormat::AQUA . TextFormat::BOLD . "ZEUS SKY-RIDE!");
        return $this->getAbilityCooldowns()["ability2"];
    }

    public function onEquip(Player $player) {
        $this->onUnequip($player);
        $player->sendMessage(TextFormat::GOLD . "=== Soru-Soru no Mi ===");
        $player->sendMessage(TextFormat::YELLOW . "Soul-Soul Fruit - Empress of the Sea (Mythical)");
    }

    public function onUnequip(Player $player) {
        $name = $player->getName();
        if (isset(self::$homieTasks[$name])) {
            self::$homieTasks[$name]->stop();
            unset(self::$homieTasks[$name]);
        }
    }
}

class SoulPassiveTask extends Task {
    public $plugin, $player, $level, $zeusEid, $promEid, $active = true, $override = false;
    public $zPos, $pPos;
    private $initTicks = 0;
    private $worldName;
    private $lastPlayerPos;
    private $resyncTimer = 0;

    public function __construct($plugin, Player $player) {
        $this->plugin = $plugin;
        $this->player = $player;
        $this->level = $player->getLevel();
        $this->worldName = $this->level->getName();
        $this->zeusEid = BlockEffects::newEid();
        $this->promEid = BlockEffects::newEid();
        $initialPos = $player->getPosition()->add(0, 1.8, 0);
        $this->zPos = clone $initialPos;
        $this->pPos = clone $initialPos;
        $this->lastPlayerPos = $player->getPosition();
    }

    public function onRun($currentTick) {
        if (!$this->active || $this->player->closed || !$this->player->isOnline()) {
            $this->stop();
            return;
        }

        if ($this->player->getLevel()->getName() !== $this->worldName) {
            $this->stop();
            return;
        }

        if ($this->initTicks < 3) {
            $this->initTicks++;
            if ($this->initTicks === 2) {
                BlockEffects::sendSpawn($this->level, $this->zeusEid, 155, 0, $this->player->x, $this->player->y + 1.8, $this->player->z);
                BlockEffects::sendSpawn($this->level, $this->promEid, 89, 0, $this->player->x, $this->player->y + 1.8, $this->player->z);
            }
            return;
        }

        if ($this->override) return;

        $currentPlayerPos = $this->player->getPosition();
        $this->resyncTimer++;

        if ($this->lastPlayerPos->distance($currentPlayerPos) > 30 || $this->resyncTimer >= 200) {
            $this->resyncTimer = 0;
            $this->resyncHomies();
        }
        $this->lastPlayerPos = $currentPlayerPos;

        $dir = $this->player->getDirectionVector();
        $rx = -$dir->z;
        $rz = $dir->x;
        $y = $this->player->y + 1.8 + sin($currentTick * 0.1) * 0.2;

        $this->zPos = new Vector3($this->player->x + $rx * 1.8, $y, $this->player->z + $rz * 1.8);
        $this->pPos = new Vector3($this->player->x - $rx * 1.8, $y, $this->player->z - $rz * 1.8);

        BlockEffects::sendMove($this->level, $this->zeusEid, $this->zPos->x, $this->zPos->y, $this->zPos->z);
        BlockEffects::sendMove($this->level, $this->promEid, $this->pPos->x, $this->pPos->y, $this->pPos->z);

        $this->level->addParticle(new InstantEnchantParticle($this->zPos));
        $this->level->addParticle(new FlameParticle($this->pPos));

        if ($currentTick % 3 === 0) {
            $this->level->addParticle(new DustParticle($this->zPos, 200, 220, 255));
            $this->level->addParticle(new CriticalParticle($this->zPos));
            $this->level->addParticle(new DustParticle($this->pPos, 255, 150, 50));
            $this->level->addParticle(new LavaDripParticle($this->pPos));
        }
    }
    
    private function resyncHomies() {
        if (!$this->level instanceof Level) return;
        BlockEffects::sendRemove($this->zeusEid);
        BlockEffects::sendRemove($this->promEid);
        $this->zeusEid = BlockEffects::newEid();
        $this->promEid = BlockEffects::newEid();
        BlockEffects::sendSpawn($this->level, $this->zeusEid, 155, 0, $this->zPos->x, $this->zPos->y, $this->zPos->z);
        BlockEffects::sendSpawn($this->level, $this->promEid, 89, 0, $this->pPos->x, $this->pPos->y, $this->pPos->z);
    }

    public function stop() {
        $this->active = false;
        if($this->level instanceof Level) BlockEffects::voidAndRemove($this->plugin, $this->level, [$this->zeusEid, $this->promEid]);
        $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
    }
}

class MaserCannonTask extends Task {
    private $plugin, $player, $level, $damage, $radius, $ticks = 0, $targetPos, $homies, $hitEntity = false;
    public function __construct($plugin, Player $player, $damage, $radius, SoulPassiveTask $homies) {
        $this->plugin = $plugin; $this->player = $player; $this->level = $player->getLevel();
        $this->damage = $damage; $this->radius = $radius; $this->homies = $homies;
        $target = $player->getTargetBlock(40);
        $this->targetPos = ($target !== null) ? new Vector3($target->x + 0.5, $target->y + 0.5, $target->z + 0.5) : $player->add($player->getDirectionVector()->x*40, $player->getDirectionVector()->y*40, $player->getDirectionVector()->z*40);
    }
    public function onRun($currentTick) {
        $this->ticks++;
        if ($this->ticks > 20 || $this->player->closed || $this->hitEntity) { 
            $this->explode(); 
            return; 
        }
        $this->player->setMotion(new Vector3(0, 0, 0));
        $prog = $this->ticks / 20;

        if ($this->ticks <= 10) {
            $chargeIntensity = $this->ticks / 10;
            for ($i = 0; $i < 5; $i++) {
                $angle = ($currentTick + $i) * 0.5;
                $r = 1.5 * (1 - $chargeIntensity);
                $this->level->addParticle(new DustParticle(
                    new Vector3($this->homies->zPos->x + cos($angle) * $r, $this->homies->zPos->y, $this->homies->zPos->z + sin($angle) * $r),
                    150, 200, 255
                ));
                $this->level->addParticle(new DustParticle(
                    new Vector3($this->homies->pPos->x + cos($angle) * $r, $this->homies->pPos->y, $this->homies->pPos->z + sin($angle) * $r),
                    255, 100, 50
                ));
            }
            if ($this->ticks % 3 === 0) {
                $this->level->addSound(new BlazeShootSound($this->homies->zPos));
            }
        }

        $zx = $this->homies->zPos->x + ($this->targetPos->x - $this->homies->zPos->x) * $prog;
        $zy = $this->homies->zPos->y + ($this->targetPos->y - $this->homies->zPos->y) * $prog;
        $zz = $this->homies->zPos->z + ($this->targetPos->z - $this->homies->zPos->z) * $prog;
        
        $px = $this->homies->pPos->x + ($this->targetPos->x - $this->homies->pPos->x) * $prog;
        $py = $this->homies->pPos->y + ($this->targetPos->y - $this->homies->pPos->y) * $prog;
        $pz = $this->homies->pPos->z + ($this->targetPos->z - $this->homies->pPos->z) * $prog;

        for ($i = 0; $i < 3; $i++) {
            $offset = $i * 0.3;
            $spiralAngle = ($this->ticks + $offset) * 0.8;
            $spiralRadius = 0.4;
            
            $this->level->addParticle(new DustParticle(
                new Vector3($zx + cos($spiralAngle) * $spiralRadius, $zy + sin($spiralAngle) * $spiralRadius, $zz),
                180, 200, 255
            ));
            $this->level->addParticle(new DustParticle(
                new Vector3($zx, $zy, $zz),
                200, 220, 255
            ));
            $this->level->addParticle(new CriticalParticle(new Vector3($zx, $zy, $zz)));

            $this->level->addParticle(new DustParticle(
                new Vector3($px + cos($spiralAngle) * $spiralRadius, $py + sin($spiralAngle) * $spiralRadius, $pz),
                255, 120, 50
            ));
            $this->level->addParticle(new DustParticle(
                new Vector3($px, $py, $pz),
                255, 100, 30
            ));
            $this->level->addParticle(new FlameParticle(new Vector3($px, $py, $pz)));
        }

        if ($this->ticks % 2 === 0) {
            $this->level->addParticle(new InstantEnchantParticle(new Vector3($zx, $zy, $zz)));
            $this->level->addParticle(new LavaDripParticle(new Vector3($px, $py, $pz)));
        }

        foreach ($this->level->getEntities() as $e) {
            if ($e === $this->player || !$e->isAlive()) continue;
            $hitZeus = $e->distance(new Vector3($zx, $zy, $zz)) < 2.0;
            $hitProm = $e->distance(new Vector3($px, $py, $pz)) < 2.0;
            
            if ($hitZeus || $hitProm) {
                if ($e instanceof Player) {
                    if (!$this->plugin->canTargetPlayer($this->player->getName(), $e)) continue;
                } elseif (!($e instanceof NPCEntity) && !($e instanceof FactoryEntity)) continue;
                
                $this->targetPos = new Vector3($e->x, $e->y + 1, $e->z);
                $this->hitEntity = true;
                return;
            }
            
            if ($e->distance(new Vector3($px, $py, $pz)) < 3.5 || $e->distance(new Vector3($zx, $zy, $zz)) < 3.5) {
                if ($e instanceof Player) {
                    if (!$this->plugin->canTargetPlayer($this->player->getName(), $e)) continue;
                } elseif (!($e instanceof NPCEntity) && !($e instanceof FactoryEntity)) continue;
                $e->setMotion(new Vector3(0, 0, 0));
            }
        }
    }
    private function explode() {
        $this->level->addSound(new ExplodeSound($this->targetPos));
        $this->level->addSound(new EndermanTeleportSound($this->targetPos));
        
        for ($i = 0; $i < 8; $i++) {
            $angle = ($i / 8) * M_PI * 2;
            $rad = mt_rand(10, 20) / 10;
            $xOff = cos($angle) * $rad;
            $zOff = sin($angle) * $rad;
            $this->level->addParticle(new HugeExplodeParticle(new Vector3($this->targetPos->x + $xOff, $this->targetPos->y, $this->targetPos->z + $zOff)));
        }
        
        for ($i = 0; $i < 25; $i++) {
            $angle = mt_rand(0, 628) / 100;
            $rad = mt_rand(0, 30) / 10;
            $yOff = mt_rand(-10, 15) / 10;
            $this->level->addParticle(new DustParticle(
                new Vector3($this->targetPos->x + cos($angle) * $rad, $this->targetPos->y + $yOff, $this->targetPos->z + sin($angle) * $rad),
                255, mt_rand(100, 200), 50
            ));
        }

        for ($i = 0; $i < 15; $i++) {
            $angle = mt_rand(0, 628) / 100;
            $rad = mt_rand(0, 25) / 10;
            $this->level->addParticle(new FlameParticle(
                new Vector3($this->targetPos->x + cos($angle) * $rad, $this->targetPos->y + mt_rand(0, 10) / 10, $this->targetPos->z + sin($angle) * $rad)
            ));
            $this->level->addParticle(new SmokeParticle(
                new Vector3($this->targetPos->x + cos($angle) * $rad * 0.7, $this->targetPos->y + mt_rand(5, 15) / 10, $this->targetPos->z + sin($angle) * $rad * 0.7)
            ));
        }

        $found = BlockEffects::scanBlocks($this->level, $this->targetPos->x, $this->targetPos->y, $this->targetPos->z, 6, 10);
        $debris = [];
        foreach ($found as $i => $b) {
            $deid = BlockEffects::newEid(); $a = ($i / 10) * M_PI * 2;
            BlockEffects::sendSpawn($this->level, $deid, $b["id"], $b["damage"], $this->targetPos->x, $this->targetPos->y + 0.5, $this->targetPos->z);
            $debris[$deid] = ["eid" => $deid, "x" => $this->targetPos->x, "y" => $this->targetPos->y + 0.5, "z" => $this->targetPos->z, "vx" => cos($a)*1.4, "vy" => 0.9, "vz" => sin($a)*1.4, "life" => 35, "tick" => 0];
        }
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask(new SoulImpactDebrisTask($this->plugin, $this->level, $debris), 1);
        
        foreach ($this->level->getEntities() as $e) {
            if ($e === $this->player || !$e->isAlive() || $e->distance($this->targetPos) > $this->radius) continue;
            if ($e instanceof Player) {
                if (!$this->plugin->canTargetPlayer($this->player->getName(), $e)) continue;
            } elseif (!($e instanceof NPCEntity) && !($e instanceof FactoryEntity)) continue;
            $e->attack($this->damage, new EntityDamageByEntityEvent($this->player, $e, EntityDamageEvent::CAUSE_MAGIC, $this->damage));
            $e->setOnFire(6);
        }
        $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
    }
}

class ZeusFlightTask extends Task {
    private $plugin, $player, $level, $damage, $ticks = 0, $maxTicks, $homies, $targetY;
    private $cloudEids = [];
    private $cloudOffsets = [
        [0, 0, 0],
        [0.55, 0.25, 0],
        [-0.55, 0.25, 0],
        [0.28, -0.25, 0.4],
        [-0.28, -0.25, -0.4]
    ];

    public function __construct($plugin, Player $player, $damage, $maxTicks, SoulPassiveTask $homies) {
        $this->plugin = $plugin;
        $this->player = $player;
        $this->level = $player->getLevel();
        $this->damage = $damage;
        $this->maxTicks = $maxTicks;
        $this->homies = $homies;
        $this->homies->override = true;
        $this->targetY = $player->y + 10;
        $player->teleport(new Vector3($player->x, $this->targetY, $player->z));
        $this->level->addSound(new EndermanTeleportSound($player));

        $zeusBase = new Vector3($player->x, $player->y - 0.8, $player->z);
        foreach ($this->cloudOffsets as $o) {
            $eid = BlockEffects::newEid();
            BlockEffects::sendSpawn($this->level, $eid, 155, 0, $zeusBase->x + $o[0], $zeusBase->y + $o[1], $zeusBase->z + $o[2]);
            $this->cloudEids[] = $eid;
        }

        BlockEffects::voidAndRemove($this->plugin, $this->level, [$this->homies->zeusEid]);
    }

    public function onRun($currentTick) {
        $this->ticks++;
        if ($this->ticks > $this->maxTicks || $this->player->closed || !$this->player->isAlive()) {
            $this->stop();
            return;
        }

        $dir = $this->player->getDirectionVector();
        $speed = 0.45;
        $this->player->setMotion(new Vector3($dir->x * $speed, 0, $dir->z * $speed));
        if (abs($this->player->y - $this->targetY) > 0.5) {
            $this->player->teleport(new Vector3($this->player->x, $this->targetY, $this->player->z));
        }

$zeusBase = new Vector3($this->player->x + $dir->x * 2, $this->player->y - 0.8, $this->player->z + $dir->z * 2);
$promPos = new Vector3($this->player->x + $dir->x * 2, $this->player->y + 2.2, $this->player->z + $dir->z * 2);

        foreach ($this->cloudEids as $i => $eid) {
            $o = $this->cloudOffsets[$i];
            BlockEffects::sendMove($this->level, $eid, $zeusBase->x + $o[0], $zeusBase->y + $o[1], $zeusBase->z + $o[2]);
        }
        BlockEffects::sendMove($this->level, $this->homies->promEid, $promPos->x, $promPos->y, $promPos->z);

        $this->homies->zPos = $zeusBase;
        $this->homies->pPos = $promPos;

        for ($i = 0; $i < 3; $i++) {
            $angle = ($this->ticks + $i * 2) * 0.3;
            $r = 1.2;
            $this->level->addParticle(new DustParticle(
                new Vector3($zeusBase->x + cos($angle) * $r, $zeusBase->y - 0.3, $zeusBase->z + sin($angle) * $r),
                220, 220, 255
            ));
            $this->level->addParticle(new PortalParticle(
                new Vector3($zeusBase->x + cos($angle) * $r * 0.5, $zeusBase->y, $zeusBase->z + sin($angle) * $r * 0.5)
            ));
        }

        for ($i = 0; $i < 2; $i++) {
            $angle = ($this->ticks + $i * 3) * 0.4;
            $r = 0.8;
            $this->level->addParticle(new FlameParticle(
                new Vector3($promPos->x + cos($angle) * $r, $promPos->y + 0.2, $promPos->z + sin($angle) * $r)
            ));
            $this->level->addParticle(new DustParticle(
                new Vector3($promPos->x + cos($angle) * $r, $promPos->y + 0.3, $promPos->z + sin($angle) * $r),
                255, 150, 50
            ));
        }

        $this->level->addParticle(new InstantEnchantParticle($zeusBase));
        $this->level->addParticle(new CriticalParticle($zeusBase));
        $this->level->addParticle(new FlameParticle($promPos));
        $this->level->addParticle(new LavaDripParticle($promPos));

        if ($this->ticks % 3 === 0) {
            $backDir = $dir->multiply(-1);
            for ($i = 1; $i <= 3; $i++) {
                $trailPos = $this->player->add($backDir->x * $i * 0.5, 0, $backDir->z * $i * 0.5);
                $this->level->addParticle(new SmokeParticle($trailPos));
                $this->level->addParticle(new PortalParticle($trailPos));
            }
        }

        if ($this->ticks % 10 === 0) {
            $this->level->addSound(new GhastShootSound($this->player));
            foreach ($this->level->getEntities() as $e) {
                if ($e === $this->player || !$e->isAlive() || abs($e->x - $this->player->x) > 7 || abs($e->z - $this->player->z) > 7) continue;
                if ($e->y < $this->player->y) {
                    if ($e instanceof Player) {
                        if (!$this->plugin->canTargetPlayer($this->player->getName(), $e)) continue;
                    } elseif (!($e instanceof NPCEntity) && !($e instanceof FactoryEntity)) continue;

                    $pk = new AddEntityPacket();
                    $pk->eid = BlockEffects::newEid();
                    $pk->type = 93;
                    $pk->x = $e->x;
                    $pk->y = $e->y;
                    $pk->z = $e->z;
                    foreach ($this->level->getPlayers() as $p) $p->dataPacket($pk);

                    for ($i = 0; $i < 15; $i++) {
                        $angle = mt_rand(0, 628) / 100;
                        $rad = mt_rand(0, 15) / 10;
                        $this->level->addParticle(new DustParticle(
                            new Vector3($e->x + cos($angle) * $rad, $e->y + mt_rand(0, 20) / 10, $e->z + sin($angle) * $rad),
                            200, 200, 255
                        ));
                        $this->level->addParticle(new CriticalParticle(
                            new Vector3($e->x + cos($angle) * $rad * 0.5, $e->y + mt_rand(5, 15) / 10, $e->z + sin($angle) * $rad * 0.5)
                        ));
                    }
                    $this->level->addSound(new AnvilUseSound($e));
                    $e->attack($this->damage, new EntityDamageByEntityEvent($this->player, $e, EntityDamageEvent::CAUSE_MAGIC, $this->damage));
                }
            }
        }
    }

    private function stop() {
        if (!empty($this->cloudEids)) BlockEffects::voidAndRemove($this->plugin, $this->level, $this->cloudEids);
        BlockEffects::sendSpawn($this->level, $this->homies->zeusEid, 155, 0, $this->homies->zPos->x, $this->homies->zPos->y, $this->homies->zPos->z);
        $this->homies->override = false;
        $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
    }
}

class SoulImpactDebrisTask extends Task {
    private $plugin, $level, $debris;
    public function __construct($plugin, Level $level, array $debris) { 
        $this->plugin = $plugin; 
        $this->level = $level; 
        $this->debris = $debris; 
    }
    public function onRun($currentTick) {
        if (empty($this->debris)) { 
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId()); 
            return; 
        }
        $toRemove = BlockEffects::tickDebris($this->debris, $this->level, -50, 0.1, 0.92);
        foreach ($toRemove as $eid) unset($this->debris[$eid]);
    }
}