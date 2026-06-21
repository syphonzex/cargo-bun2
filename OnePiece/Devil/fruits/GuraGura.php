<?php
namespace OnePiece\Devil\fruits;

use OnePiece\Devil\BaseFruit;
use OnePiece\Devil\Main;
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
use pocketmine\level\particle\ExplodeParticle;
use pocketmine\level\particle\SplashParticle;
use pocketmine\level\particle\CriticalParticle;
use pocketmine\level\sound\SplashSound;
use pocketmine\level\sound\PopSound;
use pocketmine\level\sound\AnvilUseSound;
use OnePiece\Devil\BlockEffects;
use OnePiece\NPC\NPCEntity;
use OnePieceTrades\Factory\FactoryEntity;

class GuraGura extends BaseFruit {

    public function getId() { return "gura_gura"; }
    public function getDisplayName() { return "Quake-Quake Fruit"; }
    public function getDescription() { return "Quake Fruit - the power to destroy the world itself."; }
    public function getType() { return "paramecia"; }
    public function getRarity() { return "mythical"; }

    public function getAbilityNames() {
        return ["ability1" => "Quake Punch", "ability2" => "Shima Yurashi"];
    }

    public function getAbilityCooldowns() {
        return ["ability1" => 5.0, "ability2" => 22.0];
    }

    public function useAbility(Player $player, $ability) {
        switch ($ability) {
            case "ability1": return $this->quakePunch($player);
            case "ability2": return $this->shimaYurashi($player);
        }
        return 0;
    }

    private function quakePunch(Player $player) {
        $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
        $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

        $target = $this->findFrontTarget($player, 10);
        $vfx = $this->getVFX();

        if ($target === null) {
            if ($vfx !== null && $vfx->getFruitVFX() !== null) {
                $vfx->getFruitVFX()->spawnEarthquake($player, 3.0);
            }
            $this->spawnAirCrack($player);
            $player->sendTip(TextFormat::YELLOW . "QUAKE PUNCH! - air crack!");
            return 3.0;
        }

        if ($target instanceof Player) {
            if (!$this->plugin->canTargetPlayer($player->getName(), $target)) {
                if ($vfx !== null && $vfx->getFruitVFX() !== null) {
                    $vfx->getFruitVFX()->spawnEarthquake($player, 3.0);
                }
                $this->spawnAirCrack($player);
                $player->sendTip(TextFormat::YELLOW . "QUAKE PUNCH! - air crack!");
                return 3.0;
            }
        }

        $mult   = min(1.5, $this->getHakiMultiplier($player));
        $damage = min(12.0, 6.5 * $mult);
        //$damage = 6.5 * $mult;

        $ev = new EntityDamageByEntityEvent($player, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage);
        $this->dealAbilityDamage($player, $target, $damage);

        $dir = $player->getDirectionVector();
        BaseFruit::staticSafeSetMotion($player, $target, new Vector3($dir->x * 3.5, 1.0, $dir->z * 3.5));

        $nausea = Effect::getEffect(Effect::NAUSEA);
        $nausea->setAmplifier(2);
        $nausea->setDuration(60);
        $nausea->setVisible(false);
        BaseFruit::staticSafeAddEffect($player, $target, $nausea);

        $player->sendTip(TextFormat::YELLOW . "QUAKE PUNCH!");
        if ($target instanceof Player) {
            $target->sendTip(TextFormat::RED . "QUAKE PUNCH! World is shaking!");
        }

        if ($vfx !== null && $vfx->getFruitVFX() !== null) {
            $vfx->getFruitVFX()->spawnEarthquake($player, 4.0);
        }

        $tPos = $target->getPosition();
        $debrisTask = new GuraDebrisTask(
            $this->plugin,
            $target->getLevel(),
            $tPos->x,
            $tPos->y,
            $tPos->z
        );
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($debrisTask, 1);

        return $this->getAbilityCooldowns()["ability1"];
    }

private function shimaYurashi(Player $player) {
    $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
    $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

        $mult   = min(1.5, $this->getHakiMultiplier($player));
    $baseRadius = 18.0;
    $bonusRadius = 8.0 * ($mult - 1.0);
    $radius = $baseRadius + $bonusRadius;
    $damage = min(10.0, 6.5 * $mult);
    //$damage = 6.5 * $mult;

    $player->sendTip(TextFormat::YELLOW . TextFormat::BOLD . "SHIMA YURASHI!");
    $player->sendMessage(TextFormat::AQUA . "Tsunami waves incoming!");

    $pos = $player->getPosition();
    $lv = $player->getLevel();

    $this->spawnInitialQuake($player, $radius);

    $vfx = $this->getVFX();
    if ($vfx !== null && $vfx->getFruitVFX() !== null) {
        $vfx->getFruitVFX()->spawnEarthquake($player, $radius * 0.4);
    }

    $res = Effect::getEffect(Effect::DAMAGE_RESISTANCE);
    $res->setAmplifier(1);
    $res->setDuration(100);
    $res->setVisible(false);
    $player->addEffect($res);

    $tsunamiTask = new TsunamiWaveTask(
        $this->plugin,
        $lv,
        $pos->x,
        $pos->y,
        $pos->z,
        $radius,
        $damage,
        $player->getName(),
        $toggle
    );
    $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($tsunamiTask, 1);

    return $this->getAbilityCooldowns()["ability2"];
}

    private function spawnAirCrack(Player $player) {
        $lv = $player->getLevel();
        $dir = $player->getDirectionVector();
        $pos = $player->add($dir->x * 2, $player->getEyeHeight(), $dir->z * 2);

        for ($i = 0; $i < 8; $i++) {
            $a = ($i / 8) * M_PI * 2;
            $r = 0.8 + (mt_rand(0, 5) / 10);
            $lv->addParticle(new DustParticle(
                new Vector3($pos->x + cos($a) * $r, $pos->y + sin($a) * $r * 0.5, $pos->z + sin($a) * $r),
                255, 255, 255
            ));
        }

        for ($i = 0; $i < 6; $i++) {
            $lv->addParticle(new CriticalParticle(new Vector3(
                $pos->x + (mt_rand(-10, 10) / 10),
                $pos->y + (mt_rand(-5, 5) / 10),
                $pos->z + (mt_rand(-10, 10) / 10)
            )));
        }

        for ($i = 0; $i < 3; $i++) {
            $off = ($i - 1) * 0.5;
            $lv->addParticle(new ExplodeParticle(new Vector3(
                $pos->x + $dir->x * $off,
                $pos->y,
                $pos->z + $dir->z * $off
            )));
        }

        $lv->addSound(new AnvilUseSound($pos));
    }

    private function spawnInitialQuake(Player $player, $radius) {
        $lv = $player->getLevel();
        $pos = $player->getPosition();
        $cx = $pos->x;
        $cy = $pos->y;
        $cz = $pos->z;

        for ($ring = 0; $ring < 3; $ring++) {
            $rr = 2.0 + $ring * 1.5;
            $pts = 12 + $ring * 4;
            for ($i = 0; $i < $pts; $i++) {
                $a = ($i / $pts) * M_PI * 2;
                $lv->addParticle(new DustParticle(
                    new Vector3($cx + cos($a) * $rr, $cy + 0.2, $cz + sin($a) * $rr),
                    139, 90, 43
                ));
            }
        }

        for ($i = 0; $i < 8; $i++) {
            $a = ($i / 8) * M_PI * 2;
            $d = 1.5 + mt_rand(0, 20) / 10;
            $lv->addParticle(new ExplodeParticle(new Vector3(
                $cx + cos($a) * $d,
                $cy + 0.5,
                $cz + sin($a) * $d
            )));
        }

        for ($i = 0; $i < 12; $i++) {
            $a = mt_rand(0, 628) / 100;
            $d = mt_rand(10, 40) / 10;
            $lv->addParticle(new CriticalParticle(new Vector3(
                $cx + cos($a) * $d,
                $cy + mt_rand(0, 15) / 10,
                $cz + sin($a) * $d
            )));
        }

        $lv->addSound(new AnvilUseSound(new Vector3($cx, $cy, $cz)));
    }

    private function getVFX() {
        return $this->plugin->getServer()->getPluginManager()->getPlugin("OnePieceFruits");
    }

    public function onEquip(Player $player) {
        $player->sendMessage(TextFormat::YELLOW . "=== Gura-Gura no Mi ===");
        $player->sendMessage(TextFormat::WHITE . "The Power to Destroy the World");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::YELLOW . "[Tap]: " . TextFormat::WHITE . "Quake Punch");
        $player->sendMessage(TextFormat::GRAY . "  Devastating punch with ground shatter");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::YELLOW . "[Sneak+Tap]: " . TextFormat::WHITE . "Shima Yurashi");
        $player->sendMessage(TextFormat::GRAY . "  Tsunami waves crash inward");
        $player->sendMessage(TextFormat::YELLOW . "========================");
    }

    public function onUnequip(Player $player) {
        $player->sendMessage(TextFormat::GRAY . "Quake powers fade...");
    }
}

class GuraDebrisTask extends Task {

    private $plugin;
    private $level;
    private $cx;
    private $cy;
    private $cz;
    private $debris = [];
    private $ticksRan = 0;
    private $maxTicks = 50;
    private $cleaned = false;




    public function __construct($plugin, Level $level, $cx, $cy, $cz) {
        $this->plugin = $plugin;
        $this->level = $level;
        $this->cx = (float)$cx;
        $this->cy = (float)$cy;
        $this->cz = (float)$cz;

        $this->spawnDebris();
    }


    private function spawnDebris() {
        $this->debris = BlockEffects::spawnDebris(
            $this->plugin, $this->level, $this->cx, $this->cy, $this->cz,
            mt_rand(4, 6), 0.6, 1.0, 28
        );

        for ($i = 0; $i < 10; $i++) {
            $da = ($i / 10) * M_PI * 2;
            $dd = 0.5 + mt_rand(0, 15) / 10;
            $this->level->addParticle(new DustParticle(
                new Vector3($this->cx + cos($da) * $dd, $this->cy + 0.2, $this->cz + sin($da) * $dd),
                139, 90, 43
            ));
        }

        $this->level->addSound(new AnvilUseSound(new Vector3($this->cx, $this->cy, $this->cz)));
    }


    public function onRun($currentTick) {
        if ($this->cleaned) return;

        $this->ticksRan++;

        if ($this->ticksRan > $this->maxTicks || empty($this->debris)) {
            $this->forceCleanup();
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
            return;
        }

$toRemove = BlockEffects::tickDebris($this->debris, $this->level, $this->cy - 1.5, 0.05);
        foreach ($this->debris as $eid => $d) {
            if ($d["tick"] < 8 && $d["tick"] % 2 === 0) {
                $this->level->addParticle(new DustParticle(
                    new Vector3($d["x"], $d["y"], $d["z"]),
                    139, 90, 43
                ));
            }
        }
        foreach ($toRemove as $eid) {
            unset($this->debris[$eid]);
        }
    }

    public function forceCleanup() {
        if ($this->cleaned) return;
        $this->cleaned = true;
        BlockEffects::voidAndRemove($this->plugin, $this->level, array_keys($this->debris));
        $this->debris = [];
    }
}

class TsunamiWaveTask extends Task {

    private $plugin;
    private $level;
    private $cx;
    private $cy;
    private $cz;
    private $maxRadius;
    private $damage;
    private $ownerName;
    private $toggle;

    private $ticksRan = 0;
    private $maxTicks = 120;
    private $cleaned = false;

    private $waves = [];
    private $waveCount = 2;
    private $waveSpacing = 6.0;
    private $waveSpeed = 0.3;
    private $waveHeight = 4.0;
    private $blocksPerRing = 16;

    private $hitPlayers = [];
    private $allSpawnedEids = [];
    private static $nextEid = 500000;

    const VIEW_RANGE = 70;


    private static function newEid() {
        $eid = self::$nextEid++;
        if (self::$nextEid > 599999) {
            self::$nextEid = 500000;
        }
        return $eid;
    }

    public function __construct($plugin, Level $level, $cx, $cy, $cz, $maxRadius, $damage, $ownerName, $toggle) {
        $this->plugin = $plugin;
        $this->level = $level;
        $this->cx = (float)$cx;
        $this->cy = (float)$cy;
        $this->cz = (float)$cz;
        $this->maxRadius = (float)$maxRadius;
        $this->damage = $damage;
        $this->ownerName = $ownerName;
        $this->toggle = $toggle;

        $this->initializeWaves();
    }

    private function initializeWaves() {
        $lv = $this->level;

        for ($w = 0; $w < $this->waveCount; $w++) {
            $waveRadius = $this->maxRadius + ($w * $this->waveSpacing);

            $this->waves[$w] = [
                "radius" => $waveRadius,
                "startRadius" => $waveRadius,
                "blocks" => [],
                "active" => true
            ];

            $this->spawnWaveWall($w);
        }

        for ($i = 0; $i < 16; $i++) {
            $a = ($i / 16) * M_PI * 2;
            $lv->addParticle(new DustParticle(
                new Vector3($this->cx + cos($a) * $this->maxRadius, $this->cy + 2, $this->cz + sin($a) * $this->maxRadius),
                30, 144, 255
            ));
        }

        $lv->addSound(new SplashSound(new Vector3($this->cx, $this->cy, $this->cz)));
        $lv->addSound(new SplashSound(new Vector3($this->cx, $this->cy + 1, $this->cz)));
    }

    private function spawnWaveWall($waveIndex) {
        $waveData = &$this->waves[$waveIndex];
        $baseRadius = $waveData["radius"];
        $players = $this->level->getPlayers();

        for ($ring = 0; $ring < 3; $ring++) {
            $ringHeight = $ring * 1.4;
            $ringOffset = $ring * 0.3;

            for ($i = 0; $i < $this->blocksPerRing; $i++) {
                $angle = ($i / $this->blocksPerRing) * M_PI * 2;

                $blockRadius = $baseRadius - $ringOffset;
                $x = $this->cx + cos($angle) * $blockRadius;
                $z = $this->cz + sin($angle) * $blockRadius;
                $y = $this->cy + $ringHeight;

                $eid = self::newEid();
                $this->allSpawnedEids[$eid] = true;

                $waveData["blocks"][$eid] = [
                    "eid" => $eid,
                    "angle" => $angle,
                    "ring" => $ring,
                    "baseHeight" => $ringHeight,
                    "baseOffset" => $ringOffset,
                    "x" => $x,
                    "y" => $y,
                    "z" => $z
                ];

                BlockEffects::sendSpawn($this->level, $eid, 35, 3, $x, $y, $z);
            }
        }
    }

    public function onRun($currentTick) {
        if ($this->cleaned) return;

        $this->ticksRan++;

        if ($this->ticksRan > $this->maxTicks) {
            $this->forceCleanup();
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
            return;
        }

        if ($this->plugin->getServer()->getLevelByName($this->level->getName()) === null) {
            $this->forceCleanup();
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
            return;
        }

        $allPlayers = $this->level->getPlayers();
        $nearbyPlayers = $this->getNearbyPlayers();
        if (empty($nearbyPlayers)) return;

        $allWavesDone = true;

        foreach ($this->waves as $wIndex => &$waveData) {
            if (!$waveData["active"]) continue;

            $waveData["radius"] -= $this->waveSpeed;

            if ($waveData["radius"] <= 2.5) {
                $this->crashWave($wIndex, $allPlayers);
                $waveData["active"] = false;
                continue;
            }

            $allWavesDone = false;

            $this->updateWaveWall($wIndex, $allPlayers);
            $this->checkWaveHits($wIndex);
        }
        unset($waveData);

        if ($this->ticksRan % 4 === 0) {
            $this->drawParticles();
        }

        if ($allWavesDone) {
            $this->spawnFinalCrash();
            $this->forceCleanup();
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
        }
    }

    private function getNearbyPlayers() {
        $players = [];
        foreach ($this->level->getPlayers() as $p) {
            $dx = abs($p->x - $this->cx);
            $dz = abs($p->z - $this->cz);
            if ($dx <= self::VIEW_RANGE && $dz <= self::VIEW_RANGE) {
                $players[] = $p;
            }
        }
        return $players;
    }

    private function updateWaveWall($waveIndex, $allPlayers) {
        $waveData = &$this->waves[$waveIndex];
        $currentRadius = $waveData["radius"];
        $startRadius = $waveData["startRadius"];

        $progress = 1.0 - ($currentRadius / $startRadius);
        $wavePhase = $this->ticksRan * 0.1;

        foreach ($waveData["blocks"] as $eid => &$blockData) {
            $angle = $blockData["angle"];
            $ring = $blockData["ring"];
            $baseHeight = $blockData["baseHeight"];
            $baseOffset = $blockData["baseOffset"];

            $curlOffset = $baseOffset + ($progress * $ring * 0.8);
            $blockRadius = $currentRadius - $curlOffset;

            $x = $this->cx + cos($angle) * $blockRadius;
            $z = $this->cz + sin($angle) * $blockRadius;

            $waveMotion = sin($wavePhase + $angle * 3) * 0.3;
            $crashDrop = $progress * $ring * 0.5;

            $y = $this->cy + $baseHeight + $waveMotion - $crashDrop;

            if ($y < $this->cy + 0.3) {
                $y = $this->cy + 0.3;
            }

            $blockData["x"] = $x;
            $blockData["y"] = $y;
            $blockData["z"] = $z;

            BlockEffects::sendMove($this->level, $eid, $x, $y, $z);
        }
        unset($blockData);
    }

    private function checkWaveHits($waveIndex) {
        $waveData = &$this->waves[$waveIndex];
        $currentRadius = $waveData["radius"];
        $innerRadius = $currentRadius - 4.0;
        $outerRadius = $currentRadius + 2.0;

        $owner = $this->plugin->getServer()->getPlayerExact($this->ownerName);

        foreach ($this->level->getEntities() as $entity) {
            if (!$entity->isAlive()) continue;
            if ($entity->closed) continue;

            $isValidTarget = false;

            if ($entity instanceof Player) {
                if ($entity->getName() === $this->ownerName) continue;

                if ($this->toggle !== null) {
                    if (!$this->plugin->canTargetPlayer($this->ownerName, $entity)) {
                        continue;
                    }
                }

                $hitKey = $waveIndex . "_p_" . $entity->getName();
                if (isset($this->hitPlayers[$hitKey])) continue;

                $isValidTarget = true;
            } elseif ($entity instanceof NPCEntity) {
                if ($this->ownerName === "npc_" . $entity->getId()) continue;
                $hitKey = $waveIndex . "_n_" . $entity->getId();
                if (isset($this->hitPlayers[$hitKey])) continue;

                $isValidTarget = true;
            } elseif ($entity instanceof FactoryEntity) {
                $hitKey = $waveIndex . "_f_" . $entity->getId();
                if (isset($this->hitPlayers[$hitKey])) continue;

                $isValidTarget = true;
            }

            if (!$isValidTarget) continue;

            $dx = $entity->x - $this->cx;
            $dz = $entity->z - $this->cz;
            $dist = sqrt($dx * $dx + $dz * $dz);

            if ($dist >= $innerRadius && $dist <= $outerRadius) {
                $this->hitPlayers[$hitKey] = true;

                $scaledDamage = min(16.0, $this->damage * (0.85 + ($waveIndex * 0.2)));

                if ($owner !== null) {
                    $ev = new EntityDamageByEntityEvent($owner, $entity, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $scaledDamage);
                    $entity->attack($scaledDamage, $ev);
                } else {
                    $ev = new EntityDamageEvent($entity, EntityDamageEvent::CAUSE_MAGIC, $scaledDamage);
                    $entity->attack($scaledDamage, $ev);
                }

                $len = $dist > 0 ? $dist : 1;
                $knockX = -($dx / $len) * 0.5;
                $knockZ = -($dz / $len) * 0.5;
                BaseFruit::staticSafeSetMotion($owner, $entity, new Vector3($knockX, 0.3, $knockZ));

                if ($entity instanceof Player) {
                    $slow = Effect::getEffect(Effect::SLOWNESS);
                    $slow->setAmplifier(2);
                    $slow->setDuration(60);
                    $slow->setVisible(false);
                    BaseFruit::staticSafeAddEffect($owner, $entity, $slow);

                    $nausea = Effect::getEffect(Effect::NAUSEA);
                    $nausea->setAmplifier(1);
                    $nausea->setDuration(80);
                    $nausea->setVisible(false);
                    BaseFruit::staticSafeAddEffect($owner, $entity, $nausea);

                    $entity->sendTip(TextFormat::AQUA . TextFormat::BOLD . "TSUNAMI!");
                }

                $this->level->addParticle(new SplashParticle(new Vector3($entity->x, $entity->y + 1, $entity->z)));
                $this->level->addSound(new SplashSound(new Vector3($entity->x, $entity->y, $entity->z)));
            }
        }
    }

    private function crashWave($waveIndex, $allPlayers) {
        $waveData = $this->waves[$waveIndex];

        BlockEffects::voidAndRemove($this->plugin, $this->level, array_keys($waveData["blocks"]));

        $this->waves[$waveIndex]["blocks"] = [];

        $this->level->addSound(new SplashSound(new Vector3($this->cx, $this->cy, $this->cz)));
        $this->level->addSound(new SplashSound(new Vector3($this->cx, $this->cy + 1, $this->cz)));

        for ($i = 0; $i < 8; $i++) {
            $a = ($i / 8) * M_PI * 2;
            $this->level->addParticle(new SplashParticle(new Vector3(
                $this->cx + cos($a) * 2,
                $this->cy + 0.5,
                $this->cz + sin($a) * 2
            )));
        }
    }

    private function drawParticles() {
        $lv = $this->level;

        foreach ($this->waves as $waveData) {
            if (!$waveData["active"]) continue;

            $waveRadius = $waveData["radius"];

            for ($i = 0; $i < 8; $i++) {
                $angle = ($i / 8) * M_PI * 2 + ($this->ticksRan * 0.08);

                $x = $this->cx + cos($angle) * $waveRadius;
                $z = $this->cz + sin($angle) * $waveRadius;

                $lv->addParticle(new DustParticle(
                    new Vector3($x, $this->cy + 3.5, $z),
                    255, 255, 255
                ));

                $lv->addParticle(new DustParticle(
                    new Vector3($x, $this->cy + 1.5, $z),
                    30, 144, 255
                ));
            }
        }

        if ($this->ticksRan % 8 === 0) {
            foreach ($this->waves as $waveData) {
                if (!$waveData["active"]) continue;

                $angle = mt_rand(0, 628) / 100;
                $waveRadius = $waveData["radius"];

                $this->level->addParticle(new SplashParticle(new Vector3(
                    $this->cx + cos($angle) * $waveRadius,
                    $this->cy + 1,
                    $this->cz + sin($angle) * $waveRadius
                )));

                $this->level->addSound(new SplashSound(new Vector3(
                    $this->cx + cos($angle) * $waveRadius,
                    $this->cy,
                    $this->cz + sin($angle) * $waveRadius
                )));
            }
        }
    }

    private function spawnFinalCrash() {
        $lv = $this->level;

        for ($i = 0; $i < 16; $i++) {
            $a = ($i / 16) * M_PI * 2;
            $d = mt_rand(5, 30) / 10;
            $lv->addParticle(new SplashParticle(new Vector3(
                $this->cx + cos($a) * $d,
                $this->cy + 0.5,
                $this->cz + sin($a) * $d
            )));
        }

        for ($i = 0; $i < 12; $i++) {
            $a = ($i / 12) * M_PI * 2;
            $d = mt_rand(3, 20) / 10;
            $lv->addParticle(new DustParticle(
                new Vector3($this->cx + cos($a) * $d, $this->cy + mt_rand(5, 25) / 10, $this->cz + sin($a) * $d),
                30, 144, 255
            ));
        }

        for ($i = 0; $i < 8; $i++) {
            $lv->addParticle(new ExplodeParticle(new Vector3(
                $this->cx + (mt_rand(-20, 20) / 10),
                $this->cy + 0.5,
                $this->cz + (mt_rand(-20, 20) / 10)
            )));
        }

        for ($i = 0; $i < 4; $i++) {
            $a = ($i / 4) * M_PI * 2;
            $lv->addSound(new SplashSound(new Vector3(
                $this->cx + cos($a) * 2,
                $this->cy,
                $this->cz + sin($a) * 2
            )));
        }
        $lv->addSound(new SplashSound(new Vector3($this->cx, $this->cy, $this->cz)));

        $owner = $this->plugin->getServer()->getPlayerExact($this->ownerName);

        foreach ($this->level->getEntities() as $entity) {
            if (!$entity->isAlive()) continue;
            if ($entity->closed) continue;

            $isValidTarget = false;

            if ($entity instanceof Player) {
                if ($entity->getName() === $this->ownerName) continue;

                if ($this->toggle !== null) {
                    if (!$this->plugin->canTargetPlayer($this->ownerName, $entity)) {
                        continue;
                    }
                }

                $isValidTarget = true;
            } elseif ($entity instanceof NPCEntity) {
                if ($this->ownerName === "npc_" . $entity->getId()) continue;
                $isValidTarget = true;
            } elseif ($entity instanceof FactoryEntity) {
                $isValidTarget = true;
            } elseif ($entity instanceof FactoryEntity) {
                $isValidTarget = true;
            }

            if (!$isValidTarget) continue;

            $dx = $entity->x - $this->cx;
            $dz = $entity->z - $this->cz;
            $dist = sqrt($dx * $dx + $dz * $dz);

            if ($dist <= 5.0) {
                $finalDamage = min(18.0, $this->damage * 1.5);

                if ($owner !== null) {
                    $ev = new EntityDamageByEntityEvent($owner, $entity, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $finalDamage);
                    $entity->attack($finalDamage, $ev);
                } else {
                    $ev = new EntityDamageEvent($entity, EntityDamageEvent::CAUSE_MAGIC, $finalDamage);
                    $entity->attack($finalDamage, $ev);
                }

                BaseFruit::staticSafeSetMotion($owner, $entity, new Vector3(0, 0.4, 0));

                if ($entity instanceof Player) {
                    $slow = Effect::getEffect(Effect::SLOWNESS);
                    $slow->setAmplifier(3);
                    $slow->setDuration(60);
                    $slow->setVisible(false);
                    BaseFruit::staticSafeAddEffect($owner, $entity, $slow);

                    $entity->sendTip(TextFormat::AQUA . TextFormat::BOLD . "TSUNAMI CRASH!");
                }

                $this->level->addParticle(new SplashParticle(new Vector3($entity->x, $entity->y + 1, $entity->z)));
                $this->level->addSound(new SplashSound(new Vector3($entity->x, $entity->y, $entity->z)));
            }
        }
    }

    public function forceCleanup() {
        if ($this->cleaned) return;
        $this->cleaned = true;

        $eidsToRemove = array_keys($this->allSpawnedEids);
        foreach ($this->waves as $waveData) {
            foreach (array_keys($waveData["blocks"]) as $eid) {
                $eidsToRemove[] = $eid;
            }
        }

        BlockEffects::voidAndRemove($this->plugin, $this->level, array_unique($eidsToRemove));
        $this->waves = [];
        $this->allSpawnedEids = [];
    }
}