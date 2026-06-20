<?php
namespace OnePiece\Devil\fruits;

use OnePiece\Devil\BaseFruit;
use OnePiece\Devil\BlockEffects;
use OnePiece\Devil\Main;
use OnePiece\NPC\NPCEntity;
use OnePieceTrades\Factory\FactoryEntity;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\math\Vector3;
use pocketmine\entity\Effect;
use pocketmine\entity\Entity;
use pocketmine\utils\TextFormat;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\scheduler\Task;
use pocketmine\level\Level;
use pocketmine\level\particle\MobSpellParticle;
use pocketmine\level\particle\PortalParticle;
use pocketmine\level\particle\InstantEnchantParticle;
use pocketmine\level\particle\EnchantParticle;
use pocketmine\level\particle\ExplodeParticle;
use pocketmine\level\particle\LargeExplodeParticle;
use pocketmine\level\particle\HugeExplodeParticle;
use pocketmine\level\particle\SmokeParticle;
use pocketmine\level\particle\CriticalParticle;
use pocketmine\level\particle\SporeParticle;
use pocketmine\level\particle\DustParticle;
use pocketmine\level\sound\EndermanTeleportSound;
use pocketmine\level\sound\AnvilUseSound;
use pocketmine\level\sound\PopSound;
use pocketmine\level\sound\FizzSound;
use pocketmine\level\sound\ClickSound;

class GravGrav extends BaseFruit {

    const COL_R = 180;
    const COL_G = 0;
    const COL_B = 255;

    private $riftEids    = [];
    private $riftTaskIds = [];

    public function getId()          { return "gravity_gravity"; }
    public function getDisplayName() { return "Gravity-Gravity Fruit"; }
    public function getDescription() { return "Paramecia - Control gravity itself, crushing all who oppose you."; }
    public function getType()        { return "paramecia"; }
    public function getRarity()      { return "mythical"; }

    public function getAbilityNames() {
        return [
            "ability1" => "Orbital Chain",
            "ability2" => "Planetary Devastation"
        ];
    }

    public function getAbilityCooldowns() {
        return [
            "ability1" => 15.0,
            "ability2" => 40.0
        ];
    }

public function useAbility(Player $player, $ability) {
    $this->ensurePassiveActive($player);

    switch ($ability) {
        case "ability1": return $this->orbitalChain($player);
        case "ability2": return $this->planetaryDevastation($player);
    }
    return 0;
}

    public function onEquip(Player $player) {
        $this->startRift($player);
        $player->sendMessage(TextFormat::LIGHT_PURPLE . "=== Gravity-Gravity Fruit ===");
        $player->sendMessage(TextFormat::WHITE . "Command over gravity itself");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::LIGHT_PURPLE . "[Tap]: " . TextFormat::WHITE . "Orbital Chain");
        $player->sendMessage(TextFormat::GRAY . "  Gravity orb travels forward, crushing targets");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::LIGHT_PURPLE . "[Sneak+Tap]: " . TextFormat::WHITE . "Planetary Devastation");
        $player->sendMessage(TextFormat::GRAY . "  Black hole pulls all matter then detonates");
        $player->sendMessage(TextFormat::LIGHT_PURPLE . "============================");
    }

    public function onUnequip(Player $player) {
        $this->stopRift($player);
        $player->sendMessage(TextFormat::GRAY . "Gravity returns to normal...");
    }

public function startRift(Player $player) {
    $this->stopRift($player);
    $name = $player->getName();

    $eid = BlockEffects::newEid();
    $pos = $player->getPosition();
    BlockEffects::sendSpawn($player->getLevel(), $eid, 49, 0, $pos->x + 2, $pos->y + 1.5, $pos->z);
    $this->riftEids[$name] = $eid;

    $task = new GravRiftTask($this->plugin, $player, $eid);
    $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($task, 1);
    $this->riftTaskIds[$name] = $task->getTaskId();
}

public function stopRift(Player $player) {
    $name = $player->getName();
    if (isset($this->riftTaskIds[$name])) {
        try { $this->plugin->getServer()->getScheduler()->cancelTask($this->riftTaskIds[$name]); } catch (\Exception $e) {}
        unset($this->riftTaskIds[$name]);
    }
    if (isset($this->riftEids[$name])) {
        BlockEffects::sendRemove($this->riftEids[$name]);
        unset($this->riftEids[$name]);
    }
}

private function orbitalChain(Player $player) {
    $mult = min(1.5, $this->getCombinedMultiplier($player));
    $name = $player->getName();

    if (!isset($this->riftEids[$name]) || !isset($this->riftTaskIds[$name])) {
        $this->ensurePassiveActive($player);
        $player->sendTip(TextFormat::RED . "Passive rift not ready. Try again.");
        return 0;
    }

    $orbEid = $this->riftEids[$name];
    try { $this->plugin->getServer()->getScheduler()->cancelTask($this->riftTaskIds[$name]); } catch (\Exception $e) {}
    unset($this->riftTaskIds[$name]);
    unset($this->riftEids[$name]);

    $player->sendTip(TextFormat::LIGHT_PURPLE . TextFormat::BOLD . "ORBITAL CHAIN!");

    $task = new OrbitalChainTask($this->plugin, $player, $orbEid, $mult, $this);
    $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($task, 1);
    $tid = $task->getTaskId();

    $fruit  = $this;
    $plugin = $this->plugin;
    $endTask = new class($plugin, $tid, $fruit, $name, $task) extends Task {
        private $plugin, $tid, $fruit, $name, $mainTask;
        public function __construct($p, $t, $f, $n, $mt) {
            $this->plugin = $p; $this->tid = $t; $this->fruit = $f;
            $this->name = $n; $this->mainTask = $mt;
        }
        public function onRun($ct) {
            try { $this->plugin->getServer()->getScheduler()->cancelTask($this->tid); } catch (\Exception $e) {}
            if (!$this->mainTask->isDone()) $this->mainTask->cleanup();

            $p = $this->plugin->getServer()->getPlayerExact($this->name);
            if ($p !== null && $p->isOnline()) {
                $p->sendTip(TextFormat::GRAY . "Gravity returns to passive state.");
                $this->fruit->startRift($p);
            }
        }
    };

    $durationTicks = 130;
    $this->plugin->getServer()->getScheduler()->scheduleDelayedTask($endTask, $durationTicks);

    return $this->getAbilityCooldowns()["ability1"];
}

private function planetaryDevastation(Player $player) {
    $mult = min(1.5, $this->getCombinedMultiplier($player));
    $pos  = $player->getPosition();
    $lv   = $player->getLevel();

    $ox = $pos->x;
    $oy = $pos->y + 10;
    $oz = $pos->z;

    $orbEid = BlockEffects::newEid();
    BlockEffects::sendSpawn($lv, $orbEid, 49, 0, $ox, $oy, $oz);

    $player->sendTip(TextFormat::DARK_PURPLE . TextFormat::BOLD . "PLANETARY DEVASTATION!");
    $player->sendMessage(TextFormat::LIGHT_PURPLE . "A black hole forms above...");

    $pullTask = new PlanetaryPullTask($this->plugin, $player, $orbEid, $ox, $oy, $oz, $mult);
    $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($pullTask, 1);
    $pullTid = $pullTask->getTaskId();

    $name   = $player->getName();
    $plugin = $this->plugin;
    $fruit  = $this;

    $phase2 = new class($plugin, $player, $orbEid, $pullTid, $mult, $ox, $oy, $oz, $fruit, $name, $pullTask) extends Task {
        private $plugin, $player, $orbEid, $pullTid, $mult, $ox, $oy, $oz, $fruit, $name, $pullTask;
        public function __construct($plugin, $player, $orbEid, $pullTid, $mult, $ox, $oy, $oz, $fruit, $name, $pullTask) {
            $this->plugin   = $plugin;  $this->player  = $player;
            $this->orbEid   = $orbEid;  $this->pullTid = $pullTid;
            $this->mult     = $mult;    $this->ox      = $ox;
            $this->oy       = $oy;      $this->oz      = $oz;
            $this->fruit    = $fruit;   $this->name    = $name;
            $this->pullTask = $pullTask;
        }
        public function onRun($currentTick) {
            try { $this->plugin->getServer()->getScheduler()->cancelTask($this->pullTid); } catch (\Exception $e) {}
            $this->pullTask->cleanupOrbiters();

            $p  = $this->plugin->getServer()->getPlayerExact($this->name);
            $lv = ($p !== null && $p->isOnline()) ? $p->getLevel() : $this->plugin->getServer()->getDefaultLevel();

            BlockEffects::sendRemove($this->orbEid);

            if ($p !== null && $p->isOnline()) {
                $p->sendTip(TextFormat::DARK_PURPLE . TextFormat::BOLD . "THE METEOR FORMS...");
            }

            $sphereTask = new SphereFormTask(
                $this->plugin, $lv, $this->ox, $this->oy, $this->oz,
                $this->mult, $this->name, $this->fruit
            );
            $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($sphereTask, 1);
        }
    };

    $this->plugin->getServer()->getScheduler()->scheduleDelayedTask($phase2, 80);

    return $this->getAbilityCooldowns()["ability2"];
}

public function canAffectPublic(Player $attacker, Player $target) {
    return $this->canAffectPlayer($attacker, $target);
}

public function dealDamagePublic(Player $attacker, $target, $damage) {
    $this->dealAbilityDamage($attacker, $target, $damage);
}

    public function spawnExplosionVFX(Level $lv, $x, $y, $z) {
        $lv->addParticle(new HugeExplodeParticle(new Vector3($x, $y, $z)));
        $lv->addParticle(new HugeExplodeParticle(new Vector3($x, $y + 2, $z)));
        $lv->addParticle(new HugeExplodeParticle(new Vector3($x - 1, $y + 1, $z + 1)));
        $lv->addParticle(new LargeExplodeParticle(new Vector3($x, $y - 2, $z)));
        $lv->addParticle(new LargeExplodeParticle(new Vector3($x + 2, $y, $z - 2)));

        for ($ring = 0; $ring < 6; $ring++) {
            $r   = 2.0 + $ring * 2.8;
            $pts = 20 + $ring * 6;
            for ($i = 0; $i < $pts; $i++) {
                $a = ($i / $pts) * M_PI * 2;
                $lv->addParticle(new MobSpellParticle(
                    new Vector3($x + cos($a) * $r, $y + (mt_rand(-10, 10) / 10), $z + sin($a) * $r),
                    180, 0, 255
                ));
            }
        }

        for ($i = 0; $i < 40; $i++) {
            $lv->addParticle(new InstantEnchantParticle(new Vector3(
                $x + (mt_rand(-50, 50) / 10),
                $y + (mt_rand(-25, 25) / 10),
                $z + (mt_rand(-50, 50) / 10)
            )));
        }

        for ($i = 0; $i < 20; $i++) {
            $lv->addParticle(new PortalParticle(new Vector3(
                $x + (mt_rand(-30, 30) / 10),
                $y + (mt_rand(-15, 15) / 10),
                $z + (mt_rand(-30, 30) / 10)
            )));
        }

        $lv->addSound(new AnvilUseSound(new Vector3($x, $y, $z)));
        $lv->addSound(new FizzSound(new Vector3($x, $y, $z)));
        $lv->addSound(new EndermanTeleportSound(new Vector3($x, $y, $z)));
    }


private function ensurePassiveActive(Player $player) {
    $name = $player->getName();
    if (!isset($this->riftTaskIds[$name])) {
        $this->startRift($player);
    }
}
}

class GravRiftTask extends Task {

    const COL_R = 180;
    const COL_G = 0;
    const COL_B = 255;

    private $plugin;
    private $playerName;
    private $level;
    private $eid;
    private $tick       = 0;
    private $idleTick   = 0;
    private $baseY      = 0.0;
    private $waitTicks  = 20;
    private $teleporting = false;
    private $teleportTimer = 0;

    private $lastPlayerPos;
    private $resyncTimer = 0;

    public function __construct($plugin, Player $player, $eid) {
        $this->plugin        = $plugin;
        $this->playerName    = $player->getName();
        $this->eid           = $eid;
        $this->level         = $player->getLevel();
        $this->baseY         = $player->getY() + 1.5;
        $this->lastPlayerPos = $player->getPosition();
    }

    public function onRun($currentTick) {
        $this->tick++;
        $player = $this->plugin->getServer()->getPlayerExact($this->playerName);
        if ($player === null || !$player->isOnline() || $player->getLevel()->getName() !== $this->level->getName()) {
            BlockEffects::sendRemove($this->eid);
            try { $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId()); } catch (\Exception $e) {}
            return;
        }

        $currentPlayerPos = $player->getPosition();
        $this->resyncTimer++;

        if ($this->lastPlayerPos->distance($currentPlayerPos) > 40 || $this->resyncTimer >= 200) {
            $this->resyncRift($player);
            $this->resyncTimer = 0;
            $this->lastPlayerPos = $currentPlayerPos;
            return;
        }
        $this->lastPlayerPos = $currentPlayerPos;

        $lv  = $player->getLevel();
        $pos = $player->getPosition();

        if ($this->teleporting) {
            $this->teleportTimer++;
            if ($this->teleportTimer >= 8) {
                $a  = (mt_rand(0, 628) / 100);
                $r  = 1.8 + mt_rand(0, 12) / 10;
                $nx = $pos->x + cos($a) * $r;
                $ny = $pos->y + 1.2 + mt_rand(-3, 8) / 10;
                $nz = $pos->z + sin($a) * $r;

                BlockEffects::sendMove($lv, $this->eid, $nx, $ny, $nz, 0, 0);
                $this->spawnAppearVFX($lv, $nx, $ny, $nz);
                $this->baseY       = $ny;
                $this->teleporting = false;
                $this->teleportTimer = 0;
                $this->idleTick    = 0;
            }
            return;
        }

        $this->idleTick++;

        $bobY  = $this->baseY + sin($this->tick * 0.18) * 0.3;
        $yaw   = ($this->tick * 8) % 360;

        $entity = BlockEffects::getEntity($this->eid);
        if ($entity !== null && !$entity->closed) {
            BlockEffects::sendMove($lv, $this->eid, $entity->x, $bobY, $entity->z, $yaw, 0);
        }

        $this->spawnIdleVFX($lv, $entity);

        if ($this->idleTick >= $this->waitTicks) {
            $this->spawnDisappearVFX($lv, $entity);
            $offscreen = new Vector3($pos->x + 9999, $pos->y, $pos->z);
            BlockEffects::sendMove($lv, $this->eid, $offscreen->x, $offscreen->y, $offscreen->z, 0, 0);
            $this->teleporting   = true;
            $this->teleportTimer = 0;
        }
    }

    private function resyncRift(Player $player) {
        BlockEffects::sendRemove($this->eid);
        $pos = $player->getPosition();
        $a = (mt_rand(0, 628) / 100);
        $r = 1.8 + mt_rand(0, 12) / 10;
        $nx = $pos->x + cos($a) * $r;
        $ny = $pos->y + 1.2 + mt_rand(-3, 8) / 10;
        $nz = $pos->z + sin($a) * $r;
        BlockEffects::sendSpawn($this->level, $this->eid, 49, 0, $nx, $ny, $nz);
        $this->spawnAppearVFX($this->level, $nx, $ny, $nz);

        $this->baseY = $ny;
        $this->teleporting = false;
        $this->teleportTimer = 0;
        $this->idleTick = 0;
        $this->tick = 0;
    }

    private function spawnIdleVFX(Level $lv, $entity) {
        if ($entity === null || $entity->closed) return;
        $x = $entity->x; $y = $entity->y; $z = $entity->z;

        for ($i = 0; $i < 4; $i++) {
            $a = ($i / 4) * M_PI * 2 + $this->tick * 0.15;
            $lv->addParticle(new PortalParticle(new Vector3(
                $x + cos($a) * 0.9,
                $y + sin($this->tick * 0.2 + $i) * 0.2,
                $z + sin($a) * 0.9
            )));
        }

        if ($this->tick % 3 === 0) {
            $lv->addParticle(new MobSpellParticle(
                new Vector3(
                    $x + (mt_rand(-6, 6) / 10),
                    $y + (mt_rand(0, 6) / 10),
                    $z + (mt_rand(-6, 6) / 10)
                ),
                self::COL_R, self::COL_G, self::COL_B
            ));
        }
    }

    private function spawnDisappearVFX(Level $lv, $entity) {
        if ($entity === null || $entity->closed) return;
        $x = $entity->x; $y = $entity->y; $z = $entity->z;

        for ($i = 0; $i < 14; $i++) {
            $lv->addParticle(new MobSpellParticle(
                new Vector3(
                    $x + (mt_rand(-10, 10) / 10),
                    $y + (mt_rand(0, 10) / 10),
                    $z + (mt_rand(-10, 10) / 10)
                ),
                self::COL_R, self::COL_G, self::COL_B
            ));
        }
        for ($i = 0; $i < 8; $i++) {
            $lv->addParticle(new PortalParticle(new Vector3(
                $x + (mt_rand(-8, 8) / 10),
                $y + (mt_rand(0, 8) / 10),
                $z + (mt_rand(-8, 8) / 10)
            )));
        }
        $lv->addSound(new EndermanTeleportSound(new Vector3($x, $y, $z)));
    }

    private function spawnAppearVFX(Level $lv, $x, $y, $z) {
        for ($i = 0; $i < 16; $i++) {
            $lv->addParticle(new MobSpellParticle(
                new Vector3(
                    $x + (mt_rand(-12, 12) / 10),
                    $y + (mt_rand(0, 12) / 10),
                    $z + (mt_rand(-12, 12) / 10)
                ),
                self::COL_R, self::COL_G, self::COL_B
            ));
        }
        for ($i = 0; $i < 8; $i++) {
            $a = ($i / 8) * M_PI * 2;
            $lv->addParticle(new PortalParticle(new Vector3(
                $x + cos($a) * 1.0, $y, $z + sin($a) * 1.0
            )));
        }
        $lv->addParticle(new InstantEnchantParticle(new Vector3($x, $y + 0.5, $z)));
        $lv->addSound(new EndermanTeleportSound(new Vector3($x, $y, $z)));
    }
}

class OrbitalChainTask extends Task {

    const ACCRETE_END = 30;
    const FIRE_END    = 110;

    const COL_R = 180;
    const COL_G = 0;
    const COL_B = 255;

    private $plugin;
    private $fruit;
    private $playerName;
    private $orbEid;
    private $mult;
    private $tick = 0;

    private $accretionEids = [];
    private $slugs         = [];
    private $done          = false;

    public function __construct($plugin, Player $player, $orbEid, $mult, $fruit) {
        $this->plugin     = $plugin;
        $this->playerName = $player->getName();
        $this->orbEid     = $orbEid;
        $this->mult       = $mult;
        $this->fruit      = $fruit;

        $this->initAccretion($player);
    }

    public function isDone() {
        return $this->done;
    }

    private function initAccretion(Player $player) {
        $lv = $player->getLevel();
        $pos = $player->getPosition();
        $blocks = BlockEffects::scanBlocks($lv, $pos->x, $pos->y, $pos->z, 8, 5);

        for ($i = 0; $i < 5; $i++) {
            $eid = BlockEffects::newEid();
            $block = $blocks[array_rand($blocks)];

            $a = (mt_rand(0, 628) / 100);
            $r = 3.0 + mt_rand(0, 20) / 10;
            $sx = $pos->x + cos($a) * $r;
            $sy = $pos->y + mt_rand(-10, 20) / 10;
            $sz = $pos->z + sin($a) * $r;

            BlockEffects::sendSpawn($lv, $eid, $block["id"], $block["damage"], $sx, $sy, $sz);
            $this->accretionEids[$eid] = [
                "x" => $sx, "y" => $sy, "z" => $sz, "tick" => 0
            ];
        }
        $lv->addSound(new FizzSound($pos));
    }

    public function onRun($currentTick) {
        if ($this->done) {
            try { $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId()); } catch (\Exception $e) {}
            return;
        }

        $this->tick++;
        $player = $this->plugin->getServer()->getPlayerExact($this->playerName);

        if ($player === null || !$player->isOnline()) {
            $this->cleanup();
            return;
        }

        if ($this->tick <= self::ACCRETE_END) {
            $this->tickAccretion($player);
        } else if ($this->tick <= self::FIRE_END) {
            $this->tickFire($player);
        } else {
            $this->finalShot($player);
            $this->cleanup();
        }
    }

    private function tickAccretion(Player $player) {
        $player->setMotion(new Vector3(0, 0, 0));
        $lv = $player->getLevel();
        $targetPos = $player->getPosition()->add(0, 1.4, 0);

        BlockEffects::sendMove($lv, $this->orbEid, $targetPos->x, $targetPos->y, $targetPos->z, ($this->tick * 20) % 360, 0);
        $progress = $this->tick / self::ACCRETE_END;

        $toRemove = [];
        foreach ($this->accretionEids as $eid => &$chunk) {
            $dx = $targetPos->x - $chunk["x"];
            $dy = $targetPos->y - $chunk["y"];
            $dz = $targetPos->z - $chunk["z"];

            $chunk["x"] += $dx * 0.15;
            $chunk["y"] += $dy * 0.15;
            $chunk["z"] += $dz * 0.15;
            BlockEffects::sendMove($lv, $eid, $chunk["x"], $chunk["y"], $chunk["z"], ($chunk["tick"] * 30) % 360, 0);

            $lv->addParticle(new MobSpellParticle(new Vector3($chunk["x"], $chunk["y"], $chunk["z"]), self::COL_R, self::COL_G, self::COL_B));

            if ($targetPos->distance(new Vector3($chunk["x"], $chunk["y"], $chunk["z"])) < 0.5) {
                $toRemove[] = $eid;
                BlockEffects::sendRemove($eid);
                $lv->addSound(new PopSound(new Vector3($chunk["x"], $chunk["y"], $chunk["z"])));
            }
        }
        foreach($toRemove as $eid) unset($this->accretionEids[$eid]);

        for ($i = 0; $i < 8; $i++) {
            $a = (mt_rand(0, 628) / 100);
            $r = 1.0 + (1.0 - $progress) * 4.0;
            $sx = $targetPos->x + cos($a) * $r;
            $sy = $targetPos->y + (mt_rand(-10, 10) / 10);
            $sz = $targetPos->z + sin($a) * $r;
            $lv->addParticle(new PortalParticle(new Vector3($sx, $sy, $sz)));
        }
        if ($this->tick === self::ACCRETE_END) {
            $lv->addSound(new AnvilUseSound($player->getPosition()));
        }
    }

    private function tickFire(Player $player) {
        $lv = $player->getLevel();
        $dir = $player->getDirectionVector();
        $start = $player->getPosition()->add(0, 1.4, 0);

        BlockEffects::sendMove($lv, $this->orbEid, $start->x, $start->y, $start->z, ($this->tick * 25) % 360, 0);

        for ($d = 1; $d <= 25; $d += 0.8) {
            $pos = $start->add($dir->x * $d, $dir->y * $d, $dir->z * $d);
            $lv->addParticle(new MobSpellParticle($pos, self::COL_R, self::COL_G, self::COL_B));
            if ($d % 4 < 0.8) {
                $lv->addParticle(new PortalParticle($pos));
                $lv->addParticle(new DustParticle($pos, 150, 0, 200));
            }
        }

        if ($this->tick % 5 === 0) {
            $eid = BlockEffects::newEid();
            BlockEffects::sendSpawn($lv, $eid, 95, 10, $start->x, $start->y, $start->z);
            $this->slugs[$eid] = ["pos" => $start, "dir" => $dir, "life" => 0, "dealt" => []];
            $lv->addSound(new ClickSound($start));
        }
        $this->tickSlugs($player, $lv);
    }

private function tickSlugs(Player $owner, Level $lv) {
    $toRemove = [];
    foreach ($this->slugs as $eid => &$slug) {
        $slug["life"]++;
        $slug["pos"] = $slug["pos"]->add($slug["dir"]->multiply(1.4));
        if ($slug["life"] > 22) {
            BlockEffects::sendRemove($eid);
            $toRemove[] = $eid;
            continue;
        }

        BlockEffects::sendMove($lv, $eid, $slug["pos"]->x, $slug["pos"]->y, $slug["pos"]->z, ($slug["life"]*40)%360, 0);
        
        foreach ($lv->getEntities() as $entity) {
            if (!$entity->isAlive() || $entity->closed) continue;
            if (!($entity instanceof Player) && !($entity instanceof NPCEntity) && !($entity instanceof FactoryEntity)) continue;
            if ($entity->getId() === $owner->getId()) continue;
            if (isset($slug["dealt"][$entity->getId()])) continue;
            if ($entity->distance($slug["pos"]) > 1.8) continue;

            if ($entity instanceof Player) {
                if (!$this->fruit->canAffectPublic($owner, $entity)) continue;
            }

            $damage = min(3.0, 0.75 * $this->mult);
            $this->fruit->dealDamagePublic($owner, $entity, $damage); // CORRECTED
            $entity->setMotion(new Vector3(0, 0, 0));
            $slug["dealt"][$entity->getId()] = true;

            $this->spawnHitVFX($slug["pos"], $lv);
            BlockEffects::sendRemove($eid);
            $toRemove[] = $eid;
            break;
        }
    }
    foreach($toRemove as $eid) unset($this->slugs[$eid]);
}

private function finalShot(Player $player) {
    $start = $player->getPosition()->add(0, 1.4, 0);
    $dir = $player->getDirectionVector();
    $eid = $this->orbEid;
    $this->orbEid = null;

    $task = new class($this->plugin, $player, $eid, $start, $dir, $this->mult, $this->fruit) extends Task {
        private $plugin, $owner, $eid, $pos, $dir, $mult, $fruit, $life = 0;
        public function __construct($pl, $p, $eid, $pos, $dir, $m, $fr) {
            $this->plugin = $pl; $this->owner = $p; $this->eid = $eid;
            $this->pos = $pos; $this->dir = $dir; $this->mult = $m; $this->fruit = $fr;
        }
        public function onRun($ct) {
            $this->life++;
            if ($this->life > 20 || !$this->owner->isOnline()) {
                $this->explode();
                return;
            }
            $this->pos = $this->pos->add($this->dir->multiply(1.2));
            BlockEffects::sendMove($this->owner->getLevel(), $this->eid, $this->pos->x, $this->pos->y, $this->pos->z, ($this->life*30)%360, 0);

            foreach($this->owner->getLevel()->getEntities() as $e) {
                if($e->getId() !== $this->owner->getId() && $e->distance($this->pos) < 2.0) {
                     if (($e instanceof Player || $e instanceof NPCEntity || $e instanceof FactoryEntity)) {
                         $this->explode();
                         return;
                     }
                }
            }
        }
        private function explode() {
            BlockEffects::sendRemove($this->eid);
            if (!$this->owner->isOnline()) {
                try { $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId()); } catch (\Exception $e) {}
                return;
            }
            $lv = $this->owner->getLevel();
            $lv->addParticle(new HugeExplodeParticle($this->pos));
            $lv->addSound(new AnvilUseSound($this->pos));
            $damage = min(7.5, 4.0 * $this->mult);
            foreach($lv->getEntities() as $e) {
                if ($e->getId() !== $this->owner->getId() && $e->distance($this->pos) < 4.0) {
                    if (($e instanceof Player || $e instanceof NPCEntity || $e instanceof FactoryEntity)) {
                         if ($e instanceof Player && !$this->fruit->canAffectPublic($this->owner, $e)) continue;
                         $this->fruit->dealDamagePublic($this->owner, $e, $damage); // CORRECTED
                         $dx = $e->x - $this->pos->x; $dz = $e->z - $this->pos->z; $len = sqrt($dx*$dx+$dz*$dz);
                         if($len > 0) $e->setMotion(new Vector3(($dx/$len)*0.8, 0.4, ($dz/$len)*0.8));
                    }
                }
            }
            try { $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId()); } catch (\Exception $e) {}
        }
    };
    $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($task, 1);
}

    private function spawnHitVFX(Vector3 $pos, Level $lv) {
        $lv->addParticle(new ExplodeParticle($pos));
        for ($i = 0; $i < 8; $i++) {
            $lv->addParticle(new MobSpellParticle($pos->add(mt_rand(-5, 5)/10, mt_rand(-5, 5)/10, mt_rand(-5, 5)/10), self::COL_R, self::COL_G, self::COL_B));
        }
        $lv->addSound(new PopSound($pos));
    }

    public function cleanup() {
        if ($this->done) return;
        $this->done = true;

        if ($this->orbEid !== null) BlockEffects::sendRemove($this->orbEid);
        foreach ($this->accretionEids as $eid => $c) BlockEffects::sendRemove($eid);
        foreach ($this->slugs as $eid => $s) BlockEffects::sendRemove($eid);
        $this->accretionEids = [];
        $this->slugs = [];
    }
}

class PlanetaryPullTask extends Task {

    const COL_R = 180;
    const COL_G = 0;
    const COL_B = 255;

    private $plugin;
    private $playerName;
    private $orbEid;
    private $ox;
    private $oy;
    private $oz;
    private $mult;
    private $tick          = 0;
    private $orbiterEids   = [];
    private $orbiterAngles = [];
    private $orbiterRadii  = [];
    private $orbYaw        = 0.0;

    public function __construct($plugin, Player $player, $orbEid, $ox, $oy, $oz, $mult) {
        $this->plugin     = $plugin;
        $this->playerName = $player->getName();
        $this->orbEid     = $orbEid;
        $this->ox         = $ox;
        $this->oy         = $oy;
        $this->oz         = $oz;
        $this->mult       = $mult;

        $this->spawnOrbiterRing($player->getLevel());
    }

    private function spawnOrbiterRing(Level $lv) {
        $count  = 7;
        $blocks = BlockEffects::scanBlocks($lv, $this->ox, $this->oy - 8, $this->oz, 8, 3);
        for ($i = 0; $i < $count; $i++) {
            $block = $blocks[array_rand($blocks)];
            $eid   = BlockEffects::newEid();
            $angle = ($i / $count) * M_PI * 2;
            $r     = 2.2 + (mt_rand(0, 8) / 10);
            $sx    = $this->ox + cos($angle) * $r;
            $sz    = $this->oz + sin($angle) * $r;
            BlockEffects::sendSpawn($lv, $eid, $block["id"], $block["damage"], $sx, $this->oy, $sz);
            $this->orbiterEids[]   = $eid;
            $this->orbiterAngles[] = $angle;
            $this->orbiterRadii[]  = $r;
        }
    }

    public function onRun($currentTick) {
        $this->tick++;
        $player = $this->plugin->getServer()->getPlayerExact($this->playerName);
        $lv     = ($player !== null && $player->isOnline()) ? $player->getLevel() : $this->plugin->getServer()->getDefaultLevel();

        $this->orbYaw = ($this->orbYaw + 12) % 360;
        BlockEffects::sendMove($lv, $this->orbEid, $this->ox, $this->oy + sin($this->tick * 0.15) * 0.4, $this->oz, $this->orbYaw, 0);

        $this->tickOrbiters($lv);
        $this->spawnVortexVFX($lv);

        if ($this->tick % 4 === 0) {
            $this->spawnRisingChunk($lv);
        }

        $pullRadius = 14.0;
        foreach ($lv->getEntities() as $entity) {
            if (!$entity->isAlive() || $entity->closed) continue;
            if (!($entity instanceof Player) && !($entity instanceof NPCEntity) && !($entity instanceof FactoryEntity)) continue;
            if ($entity instanceof Player && $entity->getName() === $this->playerName) continue;

            $dist = $entity->distance(new Vector3($this->ox, $this->oy, $this->oz));
            if ($dist > $pullRadius || $dist < 0.5) continue;

            $dx  = $this->ox - $entity->x;
            $dy  = $this->oy - $entity->y;
            $dz  = $this->oz - $entity->z;
            $len = sqrt($dx * $dx + $dy * $dy + $dz * $dz);
            if ($len <= 0) continue;

            $strength = 0.32 * (1.0 - $dist / $pullRadius);
            $entity->setMotion(new Vector3(
                ($dx / $len) * $strength,
                ($dy / $len) * $strength * 0.6,
                ($dz / $len) * $strength
            ));
        }
    }

    private function tickOrbiters(Level $lv) {
        $bob = sin($this->tick * 0.12) * 0.35;
        for ($i = 0; $i < count($this->orbiterEids); $i++) {
            $this->orbiterAngles[$i] += 0.065;
            $eid = $this->orbiterEids[$i];
            $r   = $this->orbiterRadii[$i];
            $nx  = $this->ox + cos($this->orbiterAngles[$i]) * $r;
            $ny  = $this->oy + $bob + ($i % 2 === 0 ? 0.3 : -0.3);
            $nz  = $this->oz + sin($this->orbiterAngles[$i]) * $r;
            $yaw = ($this->tick * 15 + $i * 40) % 360;
            BlockEffects::sendMove($lv, $eid, $nx, $ny, $nz, $yaw, 0);
        }
    }

    private function spawnRisingChunk(Level $lv) {
        $a  = (mt_rand(0, 628) / 100);
        $r  = 3.0 + mt_rand(0, 50) / 10;
        $sx = $this->ox + cos($a) * $r;
        $sy = $this->oy - 6.0;
        $sz = $this->oz + sin($a) * $r;

        $blocks = BlockEffects::scanBlocks($lv, $this->ox, $this->oy - 8, $this->oz, 8, 3);
        $block  = $blocks[array_rand($blocks)];
        $eid    = BlockEffects::newEid();
        BlockEffects::sendSpawn($lv, $eid, $block["id"], $block["damage"], $sx, $sy, $sz);

        $plugin   = $this->plugin;
        $ox       = $this->ox;
        $oy       = $this->oy;
        $oz       = $this->oz;
        $lvName   = $lv->getName();

        $riseTask = new class($plugin, $eid, $sx, $sy, $sz, $ox, $oy, $oz, $lvName, $a, $r) extends Task {
            private $plugin, $eid, $x, $y, $z, $ox, $oy, $oz, $lvName, $angle, $radius, $tick = 0;
            public function __construct($pl, $eid, $x, $y, $z, $ox, $oy, $oz, $lvn, $a, $r) {
                $this->plugin = $pl; $this->eid = $eid;
                $this->x = $x; $this->y = $y; $this->z = $z;
                $this->ox = $ox; $this->oy = $oy; $this->oz = $oz;
                $this->lvName = $lvn; $this->angle = $a; $this->radius = $r;
            }
            public function onRun($ct) {
                $this->tick++;
                $lv = $this->plugin->getServer()->getLevelByName($this->lvName);
                if ($lv === null) {
                    BlockEffects::sendRemove($this->eid);
                    try { $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId()); } catch (\Exception $e) {}
                    return;
                }

                $this->radius = max(0.1, $this->radius - 0.18);
                $this->angle  += 0.2;
                $this->y      += 0.35;

                $nx = $this->ox + cos($this->angle) * $this->radius;
                $nz = $this->oz + sin($this->angle) * $this->radius;
                BlockEffects::sendMove($lv, $this->eid, $nx, $this->y, $nz, $this->tick * 30, 0);

                $lv->addParticle(new MobSpellParticle(
                    new Vector3($nx, $this->y, $nz), 180, 0, 255
                ));

                if ($this->tick >= 22 || ($this->radius <= 0.15 && $this->y >= $this->oy)) {
                    BlockEffects::sendRemove($this->eid);
                    try { $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId()); } catch (\Exception $e) {}
                }
            }
        };
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($riseTask, 1);
    }

    private function spawnVortexVFX(Level $lv) {
        for ($ring = 0; $ring < 4; $ring++) {
            $r   = 3.0 + $ring * 2.2;
            $pts = 12 + $ring * 5;
            $rot = $this->tick * 0.14 + $ring * 0.8;
            for ($i = 0; $i < $pts; $i++) {
                $a = ($i / $pts) * M_PI * 2 + $rot;
                $lv->addParticle(new MobSpellParticle(
                    new Vector3(
                        $this->ox + cos($a) * $r,
                        $this->oy + sin($this->tick * 0.12 + $i) * 0.6,
                        $this->oz + sin($a) * $r
                    ),
                    self::COL_R, self::COL_G, self::COL_B
                ));
            }
        }

        if ($this->tick % 2 === 0) {
            for ($i = 0; $i < 3; $i++) {
                $lv->addParticle(new PortalParticle(new Vector3(
                    $this->ox + (mt_rand(-20, 20) / 10),
                    $this->oy + (mt_rand(-10, 10) / 10),
                    $this->oz + (mt_rand(-20, 20) / 10)
                )));
            }
        }

        if ($this->tick % 5 === 0) {
            $lv->addParticle(new InstantEnchantParticle(new Vector3(
                $this->ox + (mt_rand(-15, 15) / 10),
                $this->oy + (mt_rand(-8, 8) / 10),
                $this->oz + (mt_rand(-15, 15) / 10)
            )));
        }
    }

    public function cleanupOrbiters() {
        foreach ($this->orbiterEids as $eid) {
            BlockEffects::sendRemove($eid);
        }
        $this->orbiterEids   = [];
        $this->orbiterAngles = [];
        $this->orbiterRadii  = [];
    }
}

class SphereFormTask extends Task {

    const COL_R = 180;
    const COL_G = 0;
    const COL_B = 255;

    const FORM_TICKS    = 25;
    const HOLD_TICKS    = 15;
    const EXPLODE_TICK  = 40;

    private $plugin;
    private $level;
    private $ox, $oy, $oz;
    private $mult;
    private $ownerName;
    private $fruit;
    private $tick = 0;

    private $sphereEids    = [];
    private $sphereTargets = [];
    private $sphereStart   = [];

    public function __construct($plugin, Level $level, $ox, $oy, $oz, $mult, $ownerName, $fruit) {
        $this->plugin    = $plugin;
        $this->level     = $level;
        $this->ox        = $ox;
        $this->oy        = $oy;
        $this->oz        = $oz;
        $this->mult      = $mult;
        $this->ownerName = $ownerName;
        $this->fruit     = $fruit;

        $this->buildSphere();
    }

    private function buildSphere() {
        $positions = $this->getSpherePositions();
        $scatter   = 8.0;

        foreach ($positions as $i => $tgt) {
            $eid     = BlockEffects::newEid();
            $blockId = ($i % 3 === 0) ? 35 : 49;
            $blockDmg = ($blockId === 35) ? 10 : 0;

            $a  = (mt_rand(0, 628) / 100);
            $r  = $scatter + mt_rand(0, 30) / 10;
            $sx = $this->ox + cos($a) * $r;
            $sy = $this->oy + (mt_rand(-30, 30) / 10);
            $sz = $this->oz + sin($a) * $r;

            BlockEffects::sendSpawn($this->level, $eid, $blockId, $blockDmg, $sx, $sy, $sz);

            $this->sphereEids[]    = $eid;
            $this->sphereTargets[] = $tgt;
            $this->sphereStart[]   = ["x" => $sx, "y" => $sy, "z" => $sz];
        }
    }

    private function getSpherePositions() {
        $positions = [];
        $r         = 2.2;

        $equatorial = 8;
        for ($i = 0; $i < $equatorial; $i++) {
            $a           = ($i / $equatorial) * M_PI * 2;
            $positions[] = [
                "x" => $this->ox + cos($a) * $r,
                "y" => $this->oy,
                "z" => $this->oz + sin($a) * $r
            ];
        }

        $upper = 5;
        for ($i = 0; $i < $upper; $i++) {
            $a           = ($i / $upper) * M_PI * 2;
            $positions[] = [
                "x" => $this->ox + cos($a) * ($r * 0.65),
                "y" => $this->oy + 1.6,
                "z" => $this->oz + sin($a) * ($r * 0.65)
            ];
        }

        $lower = 5;
        for ($i = 0; $i < $lower; $i++) {
            $a           = ($i / $lower) * M_PI * 2;
            $positions[] = [
                "x" => $this->ox + cos($a) * ($r * 0.65),
                "y" => $this->oy - 1.6,
                "z" => $this->oz + sin($a) * ($r * 0.65)
            ];
        }

        $positions[] = ["x" => $this->ox, "y" => $this->oy + $r, "z" => $this->oz];
        $positions[] = ["x" => $this->ox, "y" => $this->oy - $r, "z" => $this->oz];

        return $positions;
    }

    public function onRun($currentTick) {
        $this->tick++;

        if ($this->tick <= self::FORM_TICKS) {
            $this->tickFormation();
            return;
        }

        if ($this->tick <= self::EXPLODE_TICK) {
            $this->tickHold();
            return;
        }

        $this->detonate();
        try { $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId()); } catch (\Exception $e) {}
    }

    private function tickFormation() {
        $t = $this->tick / self::FORM_TICKS;

        for ($i = 0; $i < count($this->sphereEids); $i++) {
            $eid = $this->sphereEids[$i];
            $tgt = $this->sphereTargets[$i];
            $src = $this->sphereStart[$i];

            $cx  = $src["x"] + ($tgt["x"] - $src["x"]) * $t;
            $cy  = $src["y"] + ($tgt["y"] - $src["y"]) * $t;
            $cz  = $src["z"] + ($tgt["z"] - $src["z"]) * $t;
            $yaw = ($this->tick * 12 + $i * 20) % 360;

            BlockEffects::sendMove($this->level, $eid, $cx, $cy, $cz, $yaw, 0);

            if ($this->tick % 3 === 0) {
                $this->level->addParticle(new MobSpellParticle(
                    new Vector3($cx, $cy, $cz),
                    self::COL_R, self::COL_G, self::COL_B
                ));
            }
        }

        for ($ring = 0; $ring < 3; $ring++) {
            $r   = 1.0 + $ring * 1.2;
            $pts = 10 + $ring * 4;
            $rot = $this->tick * 0.2 + $ring;
            for ($i = 0; $i < $pts; $i++) {
                $a = ($i / $pts) * M_PI * 2 + $rot;
                $this->level->addParticle(new MobSpellParticle(
                    new Vector3(
                        $this->ox + cos($a) * $r,
                        $this->oy + sin($this->tick * 0.25 + $i) * 0.5,
                        $this->oz + sin($a) * $r
                    ),
                    self::COL_R, self::COL_G, self::COL_B
                ));
            }
        }

        for ($i = 0; $i < 4; $i++) {
            $this->level->addParticle(new PortalParticle(new Vector3(
                $this->ox + (mt_rand(-20, 20) / 10),
                $this->oy + (mt_rand(-10, 10) / 10),
                $this->oz + (mt_rand(-20, 20) / 10)
            )));
        }
    }

    private function tickHold() {
        $holdTick = $this->tick - self::FORM_TICKS;
        $yawBase  = $this->tick * 14;

        for ($i = 0; $i < count($this->sphereEids); $i++) {
            $eid = $this->sphereEids[$i];
            $tgt = $this->sphereTargets[$i];
            $yaw = ($yawBase + $i * 26) % 360;
            BlockEffects::sendMove($this->level, $eid, $tgt["x"], $tgt["y"], $tgt["z"], $yaw, 0);
        }

        for ($ring = 0; $ring < 4; $ring++) {
            $r   = 2.5 + $ring * 1.0;
            $pts = 14 + $ring * 4;
            $rot = $this->tick * 0.28 + $ring * 0.9;
            for ($i = 0; $i < $pts; $i++) {
                $a = ($i / $pts) * M_PI * 2 + $rot;
                $this->level->addParticle(new MobSpellParticle(
                    new Vector3(
                        $this->ox + cos($a) * $r,
                        $this->oy + sin($this->tick * 0.2 + $i) * 0.4,
                        $this->oz + sin($a) * $r
                    ),
                    self::COL_R, self::COL_G, self::COL_B
                ));
            }
        }

        if ($holdTick % 3 === 0) {
            for ($i = 0; $i < 3; $i++) {
                $this->level->addParticle(new InstantEnchantParticle(new Vector3(
                    $this->ox + (mt_rand(-25, 25) / 10),
                    $this->oy + (mt_rand(-15, 15) / 10),
                    $this->oz + (mt_rand(-25, 25) / 10)
                )));
            }
        }

        if ($holdTick === 8) {
            $p = $this->plugin->getServer()->getPlayerExact($this->ownerName);
            if ($p !== null && $p->isOnline()) {
                $p->sendTip(TextFormat::DARK_PURPLE . TextFormat::BOLD . "DETONATING...");
            }
            $this->level->addSound(new AnvilUseSound(new Vector3($this->ox, $this->oy, $this->oz)));
        }
    }

private function detonate() {
    foreach ($this->sphereEids as $eid) {
        BlockEffects::sendRemove($eid);
    }
    $this->sphereEids = [];

    $this->fruit->spawnExplosionVFX($this->level, $this->ox, $this->oy, $this->oz);

    $p = $this->plugin->getServer()->getPlayerExact($this->ownerName);
    if ($p !== null && $p->isOnline()) {
        $p->sendTip(TextFormat::DARK_PURPLE . TextFormat::BOLD . "IMPACT!");
    }

    $baseDmg = min(24.0, 15.0 * $this->mult); // Damage increased from 5.0 to 15.0
    $radius  = 18.0;
    $attacker = $this->plugin->getServer()->getPlayerExact($this->ownerName);

    foreach ($this->level->getEntities() as $entity) {
        if (!$entity->isAlive() || $entity->closed) continue;
        if (!($entity instanceof Player) && !($entity instanceof NPCEntity) && !($entity instanceof FactoryEntity)) continue;
        if ($entity instanceof Player && $entity->getName() === $this->ownerName) continue;

        $dist = $entity->distance(new Vector3($this->ox, $this->oy, $this->oz));
        if ($dist > $radius) continue;
        if ($attacker === null) continue;

        if ($entity instanceof Player) {
            if (!$this->fruit->canAffectPublic($attacker, $entity)) continue;
        }

        $scaled = $baseDmg * (1.0 - ($dist / $radius) * 0.35);
        $this->fruit->dealDamagePublic($attacker, $entity, $scaled);
        $entity->setMotion(new Vector3(0, 0, 0));
    }

    $meteorTask = new MeteorRainTask(
        $this->plugin, $this->level,
        $this->ox, $this->oy, $this->oz,
        $this->mult, $this->ownerName
    );
    $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($meteorTask, 1);
    $meteorTid = $meteorTask->getTaskId();

    $plugin    = $this->plugin;
    $endClean  = new class($plugin, $meteorTid, $meteorTask) extends Task {
        private $plugin, $tid, $task;
        public function __construct($p, $t, $mt) { $this->plugin = $p; $this->tid = $t; $this->task = $mt; }
        public function onRun($ct) {
            try { $this->plugin->getServer()->getScheduler()->cancelTask($this->tid); } catch (\Exception $e) {}
            $this->task->cleanup();
        }
    };
    $this->plugin->getServer()->getScheduler()->scheduleDelayedTask($endClean, 180);
}
}

class MeteorRainTask extends Task {

    const COL_R = 180;
    const COL_G = 0;
    const COL_B = 255;

    const MAX_METEORS = 20;
    const SPAWN_RATE  = 7;

    private $plugin;
    private $level;
    private $cx, $cy, $cz;
    private $mult;
    private $ownerName;
    private $tick    = 0;
    private $meteors = [];
    private $spawned = 0;

    public function __construct($plugin, Level $level, $cx, $cy, $cz, $mult, $ownerName) {
        $this->plugin    = $plugin;
        $this->level     = $level;
        $this->cx        = $cx;
        $this->cy        = $cy;
        $this->cz        = $cz;
        $this->mult      = $mult;
        $this->ownerName = $ownerName;

        $this->spawnInitialFragments();
    }

    private function spawnInitialFragments() {
        $count = 10;
        for ($i = 0; $i < $count; $i++) {
            $this->spawnMeteor(true);
        }
    }

    public function onRun($currentTick) {
        $this->tick++;

        if ($this->spawned < self::MAX_METEORS && $this->tick % self::SPAWN_RATE === 0) {
            $this->spawnMeteor(false);
        }

        $groundY  = $this->cy - 10.0;
        $toRemove = [];

        foreach ($this->meteors as $eid => &$m) {
            $m["tick"]++;

            if ($m["landed"]) {
                if ($m["tick"] >= $m["landedAt"] + 10) {
                    BlockEffects::sendRemove($eid);
                    $toRemove[] = $eid;
                }
                continue;
            }

            if ($m["tick"] > 140) {
                BlockEffects::sendRemove($eid);
                $toRemove[] = $eid;
                continue;
            }

            $m["vy"] -= 0.065;
            $m["vx"] *= 0.995;
            $m["vz"] *= 0.995;
            $m["x"]  += $m["vx"];
            $m["y"]  += $m["vy"];
            $m["z"]  += $m["vz"];

            $blockY = $this->level->getHighestBlockAt((int)$m["x"], (int)$m["z"]);
            if ($m["y"] <= $blockY + 0.5) {
                $m["y"]      = (float)($blockY + 0.5);
                $m["landed"] = true;
                $m["landedAt"] = $m["tick"];
                $this->onMeteorLand($eid, $m);
                continue;
            }

            $m["yaw"] = ($m["yaw"] + 22) % 360;
            BlockEffects::sendMove($this->level, $eid, $m["x"], $m["y"], $m["z"], $m["yaw"], 30);

            for ($i = 0; $i < 5; $i++) {
                $this->level->addParticle(new MobSpellParticle(
                    new Vector3(
                        $m["x"] + (mt_rand(-5, 5) / 10),
                        $m["y"] + (mt_rand(0, 8) / 10),
                        $m["z"] + (mt_rand(-5, 5) / 10)
                    ),
                    self::COL_R, self::COL_G, self::COL_B
                ));
            }

            $this->level->addParticle(new \pocketmine\level\particle\FlameParticle(new Vector3(
                $m["x"] + (mt_rand(-3, 3) / 10),
                $m["y"] + (mt_rand(0, 5) / 10),
                $m["z"] + (mt_rand(-3, 3) / 10)
            )));

            $this->level->addParticle(new SmokeParticle(new Vector3(
                $m["x"] + (mt_rand(-4, 4) / 10),
                $m["y"] + (mt_rand(2, 8) / 10),
                $m["z"] + (mt_rand(-4, 4) / 10)
            )));

            $this->level->addParticle(new PortalParticle(new Vector3(
                $m["x"] + (mt_rand(-4, 4) / 10),
                $m["y"] + (mt_rand(0, 6) / 10),
                $m["z"] + (mt_rand(-4, 4) / 10)
            )));
        }
        unset($m);
        foreach ($toRemove as $eid) unset($this->meteors[$eid]);

        if ($this->spawned >= self::MAX_METEORS && empty($this->meteors)) {
            try { $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId()); } catch (\Exception $e) {}
        }
    }

    private function spawnMeteor($isFragment) {
        $eid     = BlockEffects::newEid();
        $blockId = ($isFragment) ? (mt_rand(0, 1) === 0 ? 49 : 35) : 49;
        $blockDmg = ($blockId === 35) ? 10 : 0;

        if ($isFragment) {
            $a   = (mt_rand(0, 628) / 100);
            $r   = 0.5 + mt_rand(0, 15) / 10;
            $sx  = $this->cx + cos($a) * $r;
            $sy  = $this->cy;
            $sz  = $this->cz + sin($a) * $r;

            $outX = cos($a) * (0.3 + mt_rand(0, 20) / 100);
            $outZ = sin($a) * (0.3 + mt_rand(0, 20) / 100);
            $outY = 0.4 + mt_rand(0, 30) / 100;

            BlockEffects::sendSpawn($this->level, $eid, $blockId, $blockDmg, $sx, $sy, $sz);

            $this->meteors[$eid] = [
                "x"        => (float)$sx,
                "y"        => (float)$sy,
                "z"        => (float)$sz,
                "vx"       => $outX,
                "vy"       => $outY,
                "vz"       => $outZ,
                "tick"     => 0,
                "yaw"      => mt_rand(0, 360),
                "landed"   => false,
                "landedAt" => 0,
                "dealt"    => [],
                "fragment" => true
            ];
        } else {
            $a  = (mt_rand(0, 628) / 100);
            $r  = 1.0 + mt_rand(0, 80) / 10;
            $sx = $this->cx + cos($a) * $r;
            $sy = $this->cy + 24.0 + mt_rand(0, 80) / 10;
            $sz = $this->cz + sin($a) * $r;

            $tx  = $this->cx + (mt_rand(-80, 80) / 10);
            $tz  = $this->cz + (mt_rand(-80, 80) / 10);
            $dx  = $tx - $sx;
            $dz  = $tz - $sz;
            $len = sqrt($dx * $dx + $dz * $dz);
            if ($len > 0) { $dx /= $len; $dz /= $len; }

            BlockEffects::sendSpawn($this->level, $eid, $blockId, $blockDmg, $sx, $sy, $sz);

            $this->meteors[$eid] = [
                "x"        => (float)$sx,
                "y"        => (float)$sy,
                "z"        => (float)$sz,
                "vx"       => $dx * 0.35,
                "vy"       => -0.25,
                "vz"       => $dz * 0.35,
                "tick"     => 0,
                "yaw"      => mt_rand(0, 360),
                "landed"   => false,
                "landedAt" => 0,
                "dealt"    => [],
                "fragment" => false
            ];
        }

        $this->spawned++;
    }

private function onMeteorLand($eid, &$m) {
    $lx = $m["x"]; $ly = $m["y"]; $lz = $m["z"];

    $this->level->addParticle(new HugeExplodeParticle(new Vector3($lx, $ly + 0.5, $lz)));
    $this->level->addParticle(new LargeExplodeParticle(new Vector3($lx, $ly + 0.5, $lz)));
    $this->level->addParticle(new ExplodeParticle(new Vector3($lx, $ly + 1.0, $lz)));

    for ($i = 0; $i < 12; $i++) {
        $this->level->addParticle(new MobSpellParticle(
            new Vector3(
                $lx + (mt_rand(-12, 12) / 10),
                $ly + (mt_rand(0, 10) / 10),
                $lz + (mt_rand(-12, 12) / 10)
            ),
            self::COL_R, self::COL_G, self::COL_B
        ));
    }

    for ($i = 0; $i < 6; $i++) {
        $this->level->addParticle(new \pocketmine\level\particle\FlameParticle(new Vector3(
            $lx + (mt_rand(-8, 8) / 10),
            $ly + (mt_rand(0, 8) / 10),
            $lz + (mt_rand(-8, 8) / 10)
        )));
    }

    for ($i = 0; $i < 6; $i++) {
        $a = ($i / 6) * M_PI * 2;
        $this->level->addParticle(new PortalParticle(new Vector3(
            $lx + cos($a) * 1.5, $ly + 0.5, $lz + sin($a) * 1.5
        )));
    }

    $this->level->addSound(new AnvilUseSound(new Vector3($lx, $ly, $lz)));
    $this->level->addSound(new PopSound(new Vector3($lx, $ly, $lz)));

    $attacker = $this->plugin->getServer()->getPlayerExact($this->ownerName);
    if ($attacker === null) return;

    $landDmg = $m["fragment"] ? min(6.0, 2.4 * $this->mult) : min(10.5, 4.5 * $this->mult);
    $hitRadius = $m["fragment"] ? 2.0 : 3.0;

    foreach ($this->level->getEntities() as $entity) {
        if (!$entity->isAlive() || $entity->closed) continue;
        if (!($entity instanceof Player) && !($entity instanceof NPCEntity) && !($entity instanceof FactoryEntity)) continue;
        if ($entity instanceof Player && $entity->getName() === $this->ownerName) continue;
        if (isset($m["dealt"][$entity->getId()])) continue;
        if ($entity->distance(new Vector3($lx, $ly, $lz)) > $hitRadius) continue;

        if ($entity instanceof Player) {
            if (!$this->plugin->canTargetPlayer($attacker->getName(), $entity)) continue;
        }

        $this->plugin->setAbilityDamage($this->ownerName, $landDmg);
        $ev = new EntityDamageByEntityEvent($attacker, $entity, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $landDmg);
        $entity->attack($landDmg, $ev);
        $entity->setMotion(new Vector3(0, 0, 0));
        $m["dealt"][$entity->getId()] = true;
    }
}

    public function cleanup() {
        foreach ($this->meteors as $eid => $m) {
            BlockEffects::sendRemove($eid);
        }
        $this->meteors = [];
    }
}
?>