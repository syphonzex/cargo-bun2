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
use pocketmine\level\particle\CriticalParticle;
use pocketmine\level\particle\ExplodeParticle;
use pocketmine\level\particle\LargeExplodeParticle;
use pocketmine\level\particle\InstantEnchantParticle;
use pocketmine\level\particle\EnchantParticle;
use pocketmine\level\particle\FlameParticle;
use pocketmine\level\particle\SmokeParticle;
use pocketmine\level\particle\RedstoneParticle;
use pocketmine\level\sound\PopSound;
use pocketmine\level\sound\ClickSound;
use pocketmine\level\sound\FizzSound;
use pocketmine\level\sound\AnvilUseSound;
use pocketmine\level\sound\ExplodeSound;
use pocketmine\level\sound\GhastSound;
use pocketmine\level\sound\BlazeShootSound;
use pocketmine\level\sound\EndermanTeleportSound;
use pocketmine\level\sound\BatSound;
use pocketmine\level\sound\NoteblockSound;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\network\protocol\MoveEntityPacket;
use pocketmine\network\protocol\RemoveEntityPacket;
use pocketmine\network\protocol\LevelEventPacket;
use OnePiece\NPC\NPCEntity;
use OnePieceTrades\Factory\FactoryEntity;
use OnePiece\Devil\BlockEffects;

class SoundSound extends BaseFruit {

    const COL_PINK_R   = 255; const COL_PINK_G   = 50;  const COL_PINK_B   = 180;
    const COL_GOLD_R   = 255; const COL_GOLD_G   = 200; const COL_GOLD_B   = 0;
    const COL_PURPLE_R = 160; const COL_PURPLE_G = 0;   const COL_PURPLE_B = 255;
    const COL_BLACK_R  = 10;  const COL_BLACK_G  = 0;   const COL_BLACK_B  = 20;
    const COL_GREEN_R  = 0;   const COL_GREEN_G  = 255; const COL_GREEN_B  = 120;
    const COL_WHITE_R  = 255; const COL_WHITE_G  = 255; const COL_WHITE_B  = 255;
    const EV_NOTE      = 2000;
    const EV_SPLASH    = 2002;
    const COL_SPLASH_PINK   = 16714930;
    const COL_SPLASH_PURPLE = 10485760;
    const COL_SPLASH_GOLD   = 16763904;

    public function getId()          { return "sound_sound"; }
    public function getDisplayName() { return "Sound-Sound Fruit"; }
    public function getDescription() { return "Paramecia - Weaponize sound itself into devastating musical attacks."; }
    public function getType()        { return "paramecia"; }
    public function getRarity()      { return "legendary"; }

    public function getAbilityNames() {
        return [
            "ability1" => "Sonic Crescendo",
            "ability2" => "Resonance Collapse"
        ];
    }

    public function getAbilityCooldowns() {
        return [
            "ability1" => 8.0,
            "ability2" => 20.0
        ];
    }

    public function useAbility(Player $player, $ability) {
        switch ($ability) {
            case "ability1": return $this->sonicCrescendo($player);
            case "ability2": return $this->resonanceCollapse($player);
        }
        return 0;
    }

    // ── Ability 1: Sonic Crescendo ────────────────────────────────────────
    // Large cone of music notes + pink wave ribbons fanning forward
    // Deals AoE damage + knockback to everything in front cone
    private function sonicCrescendo(Player $player) {
        $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
        $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

        $mult      = min(1.5, $this->getHakiMultiplier($player));
        $damage    = min(12.0, 4.5 * $mult);
        $coneRange = 14.0;

        $task = new SonicCrescendoTask($this->plugin, $player, $damage, $coneRange, $toggle);
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($task, 1);

        $vfx = $this->getVFX();
        if ($vfx !== null && $vfx->getFruitVFX() !== null) {
            $vfx->getFruitVFX()->spawnSoundDomain($player, 8.0, 60);
        }

        $player->sendTip(TextFormat::LIGHT_PURPLE . TextFormat::BOLD . "SONIC CRESCENDO!");
        return $this->getAbilityCooldowns()["ability1"];
    }

    // ── Ability 2: Resonance Collapse ─────────────────────────────────────
    // 5 small orb projectiles + 1 large orb launch forward
    // Large orb explodes on impact → black hole that pulls + damages
    private function resonanceCollapse(Player $player) {
        $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
        $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

        $mult        = min(1.5, $this->getHakiMultiplier($player));
        $orbDamage   = min(8.0, 3.0 * $mult);
        $bigDamage   = min(14.0, 5.5 * $mult);
        $holeDamage  = min(6.0, 2.5 * $mult);

        $task = new ResonanceCollapseTask($this->plugin, $player, $orbDamage, $bigDamage, $holeDamage, $toggle);
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($task, 1);

        $vfx = $this->getVFX();
        if ($vfx !== null && $vfx->getFruitVFX() !== null) {
            $vfx->getFruitVFX()->spawnSoundDomain($player, 10.0, 160);
        }

        $player->sendTip(TextFormat::LIGHT_PURPLE . TextFormat::BOLD . "RESONANCE COLLAPSE!");
        return $this->getAbilityCooldowns()["ability2"];
    }

    private function sendNote($lv, $x, $y, $z, $pitch) {
        $pk = new LevelEventPacket();
        $pk->evid = self::EV_NOTE;
        $pk->data = (int)$pitch;
        $pk->x = (float)$x; $pk->y = (float)$y; $pk->z = (float)$z;
        foreach ($lv->getPlayers() as $pl) { $pl->dataPacket($pk); }
    }

    private function sendSplash($lv, $x, $y, $z, $col) {
        $pk = new LevelEventPacket();
        $pk->evid = self::EV_SPLASH;
        $pk->data = $col;
        $pk->x = (float)$x; $pk->y = (float)$y; $pk->z = (float)$z;
        foreach ($lv->getPlayers() as $pl) { $pl->dataPacket($pk); }
    }

    private function getVFX() {
        return $this->plugin->getServer()->getPluginManager()->getPlugin("OnePieceFruits");
    }

    public function onEquip(Player $player) {
        $player->sendMessage(TextFormat::LIGHT_PURPLE . "=== Sound-Sound Fruit ===");
        $player->sendMessage(TextFormat::WHITE . "Legendary Paramecia - Music as a weapon");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::LIGHT_PURPLE . "[Tap]: " . TextFormat::WHITE . "Sonic Crescendo");
        $player->sendMessage(TextFormat::GRAY . "  Unleash a cone of musical destruction");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::LIGHT_PURPLE . "[Sneak+Tap]: " . TextFormat::WHITE . "Resonance Collapse");
        $player->sendMessage(TextFormat::GRAY . "  Launch orbs then collapse into a black hole");
        $player->sendMessage(TextFormat::LIGHT_PURPLE . "========================");
    }

    public function onUnequip(Player $player) {
        $player->sendMessage(TextFormat::GRAY . "The music fades...");
    }
}

// ── SonicCrescendoTask ────────────────────────────────────────────────────────
// Phase 1 (ticks 1-3):  Gold burst at player origin + resistance
// Phase 2 (ticks 4-22): Pink wave ribbons fan forward in cone,
//                        note particles float up along the wave,
//                        hit detection sweeps forward
// Phase 3 (ticks 23-28): Notes linger and dissipate

class SonicCrescendoTask extends Task {

    private $plugin;
    private $player;
    private $damage;
    private $coneRange;
    private $toggle;
    private $tick      = 0;
    private $hitTargets = [];
    private $dirX; private $dirZ;

    const VIEW_RANGE = 50;

    public function __construct($plugin, Player $player, $damage, $coneRange, $toggle) {
        $this->plugin     = $plugin;
        $this->player     = $player;
        $this->damage     = $damage;
        $this->coneRange  = $coneRange;
        $this->toggle     = $toggle;
        $dir = $player->getDirectionVector();
        $len = sqrt($dir->x * $dir->x + $dir->z * $dir->z);
        $this->dirX = $len > 0 ? $dir->x / $len : 0;
        $this->dirZ = $len > 0 ? $dir->z / $len : 0;
        // Damage resistance for duration
        $res = Effect::getEffect(Effect::DAMAGE_RESISTANCE);
        $res->setAmplifier(4); $res->setDuration(32); $res->setVisible(false);
        $player->addEffect($res);
    }

    public function onRun($currentTick) {
        if ($this->player->closed || !$this->player->isAlive()) {
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
            return;
        }
        $this->tick++;
        $lv      = $this->player->getLevel();
        $players = $this->getNearby($lv);
        $px = $this->player->x; $py = $this->player->y; $pz = $this->player->z;

        if ($this->tick <= 3) {
            // Gold origin burst
            $r = $this->tick * 0.5;
            for ($i = 0; $i < 10; $i++) {
                $a = ($i / 10) * M_PI * 2;
                $lv->addParticle(new DustParticle(new Vector3($px + cos($a) * $r, $py + 0.8, $pz + sin($a) * $r), SoundSound::COL_GOLD_R, SoundSound::COL_GOLD_G, SoundSound::COL_GOLD_B));
            }
            $lv->addParticle(new FlameParticle(new Vector3($px, $py + 0.8, $pz)));
            if ($this->tick === 1) {
                // Ascending piano scale: C D E F (pitches 0 3 7 10)
                foreach ([0, 3, 7, 10] as $i => $pitch) {
                    $lv->addSound(new \pocketmine\level\sound\NoteblockSound(
                        new Vector3($px, $py, $pz),
                        \pocketmine\level\sound\NoteblockSound::INSTRUMENT_PIANO,
                        $pitch
                    ));
                }
            } elseif ($this->tick === 2) {
                // Continue scale: G A (pitches 14 17)
                foreach ([14, 17] as $pitch) {
                    $lv->addSound(new \pocketmine\level\sound\NoteblockSound(
                        new Vector3($px, $py, $pz),
                        \pocketmine\level\sound\NoteblockSound::INSTRUMENT_PIANO,
                        $pitch
                    ));
                }
            } elseif ($this->tick === 3) {
                // Peak: high C (pitch 24) + bass drum hit
                $lv->addSound(new \pocketmine\level\sound\NoteblockSound(
                    new Vector3($px, $py, $pz),
                    \pocketmine\level\sound\NoteblockSound::INSTRUMENT_PIANO,
                    24
                ));
                $lv->addSound(new \pocketmine\level\sound\NoteblockSound(
                    new Vector3($px, $py, $pz),
                    \pocketmine\level\sound\NoteblockSound::INSTRUMENT_BASS_DRUM,
                    0
                ));
            }
        } elseif ($this->tick <= 22) {
            $step     = $this->tick - 3; // 1..19
            $progress = $step / 19.0;

            // Pink wave ribbons — 4 wavy arcs fanning across cone
            $coneA = atan2($this->dirZ, $this->dirX);
            $fanWidth = M_PI * 0.55; // ~99 degree cone

            for ($ribbon = 0; $ribbon < 4; $ribbon++) {
                $ribbonAngle = $coneA + ($ribbon / 3.0 - 0.5) * $fanWidth * 2;
                $pts = 12;
                for ($p = 0; $p < $pts; $p++) {
                    $prog2 = $p / ($pts - 1);
                    $dist  = $prog2 * $this->coneRange * $progress;
                    $wave  = sin($prog2 * M_PI * 3 + $step * 0.4 + $ribbon) * 0.6;
                    $wx    = $px + cos($ribbonAngle) * $dist;
                    $wy    = $py + 0.9 + $wave + $prog2 * 1.2;
                    $wz    = $pz + sin($ribbonAngle) * $dist;
                    // Alternate pink and purple along ribbon
                    if ($p % 2 === 0) {
                        $lv->addParticle(new DustParticle(new Vector3($wx, $wy, $wz), SoundSound::COL_PINK_R, SoundSound::COL_PINK_G, SoundSound::COL_PINK_B));
                    } else {
                        $lv->addParticle(new DustParticle(new Vector3($wx, $wy, $wz), SoundSound::COL_PURPLE_R, SoundSound::COL_PURPLE_G, SoundSound::COL_PURPLE_B));
                    }
                }
            }

            // Floating note particles + actual noteblock sounds along cone
            // Chromatic scale across the wave steps (steps 1-19 map to pitches 0-24)
            if ($step % 2 === 0) {
                // Musical scale: pentatonic C D E G A repeating
                $scale     = [0, 3, 7, 12, 17, 19, 22, 24];
                $scaleIdx  = (int)($step / 2) % count($scale);
                $notePitch = $scale[$scaleIdx];
                for ($n = 0; $n < 4; $n++) {
                    $noteA    = $coneA + (mt_rand(-50, 50) / 100.0) * $fanWidth * 2;
                    $noteDist = (mt_rand(3, (int)($this->coneRange * 8)) / 10.0) * $progress;
                    $nx = $px + cos($noteA) * $noteDist;
                    $nz = $pz + sin($noteA) * $noteDist;
                    // Note event particle (floating music note visual)
                    $pk = new LevelEventPacket();
                    $pk->evid = 2000;
                    $pk->data = $notePitch;
                    $pk->x = (float)$nx; $pk->y = (float)($py + 1.2 + mt_rand(0, 10) / 10.0); $pk->z = (float)$nz;
                    foreach ($players as $pl) { $pl->dataPacket($pk); }
                }
                // Play the matching noteblock sound at the wave front
                $waveFrontX = $px + $this->dirX * $this->coneRange * $progress;
                $waveFrontZ = $pz + $this->dirZ * $this->coneRange * $progress;
                $lv->addSound(new \pocketmine\level\sound\NoteblockSound(
                    new Vector3($waveFrontX, $py + 1, $waveFrontZ),
                    \pocketmine\level\sound\NoteblockSound::INSTRUMENT_PIANO,
                    $notePitch
                ));
                // Every 4 steps add a bass drum beat for rhythm
                if ($step % 4 === 0) {
                    $lv->addSound(new \pocketmine\level\sound\NoteblockSound(
                        new Vector3($px, $py, $pz),
                        \pocketmine\level\sound\NoteblockSound::INSTRUMENT_BASS_DRUM,
                        0
                    ));
                }
            }

            // Gold sparkle origin
            $lv->addParticle(new FlameParticle(new Vector3($px + (mt_rand(-3,3)/10), $py + 0.8, $pz + (mt_rand(-3,3)/10))));
            $lv->addParticle(new InstantEnchantParticle(new Vector3($px, $py + 0.9, $pz)));

            // Sounds: clicking notes every 3 ticks, big whoosh on step 1
            if ($step === 1) {
                $lv->addSound(new GhastSound(new Vector3($px, $py, $pz)));
                $lv->addSound(new FizzSound(new Vector3($px, $py, $pz)));
                $pk2 = new LevelEventPacket(); $pk2->evid = 2002; $pk2->data = SoundSound::COL_SPLASH_GOLD;
                $pk2->x = (float)$px; $pk2->y = (float)($py + 0.8); $pk2->z = (float)$pz;
                foreach ($players as $pl) { $pl->dataPacket($pk2); }
            }
            if ($step % 3 === 0) {
                // Tabour/click rhythm
                $lv->addSound(new \pocketmine\level\sound\NoteblockSound(
                    new Vector3($px + $this->dirX * 3, $py + 1, $pz + $this->dirZ * 3),
                    \pocketmine\level\sound\NoteblockSound::INSTRUMENT_TABOUR,
                    $step % 12
                ));
            }

            // Hit detection: sweep forward as wave progresses
            $hitRange = $this->coneRange * $progress;
            $this->hitConTargets($lv, $px, $py, $pz, $hitRange);

        } else {
            // Dissipate: descending scale fading out
            $fadeStep  = $this->tick - 22;
            // Descending: 24 22 19 17 12 7 3 0
            $descScale = [24, 22, 19, 17, 12, 7, 3, 0];
            $descPitch = $descScale[min($fadeStep - 1, count($descScale) - 1)];
            for ($i = 0; $i < 3; $i++) {
                $a = ($i / 3) * M_PI * 2 + $fadeStep * 0.4;
                $d = mt_rand(2, 8);
                $pk3 = new LevelEventPacket(); $pk3->evid = 2000; $pk3->data = $descPitch;
                $pk3->x = (float)($px + cos($a) * $d); $pk3->y = (float)($py + 1.5); $pk3->z = (float)($pz + sin($a) * $d);
                foreach ($players as $pl) { $pl->dataPacket($pk3); }
                $lv->addParticle(new EnchantParticle(new Vector3($px + cos($a) * $d, $py + 1.5, $pz + sin($a) * $d)));
            }
            if ($fadeStep <= count($descScale)) {
                $lv->addSound(new \pocketmine\level\sound\NoteblockSound(
                    new Vector3($px, $py + 1, $pz),
                    \pocketmine\level\sound\NoteblockSound::INSTRUMENT_PIANO,
                    $descPitch
                ));
            }
            if ($this->tick >= 28) {
                $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
            }
        }
    }

    private function hitConTargets($lv, $px, $py, $pz, $hitRange) {
        foreach ($lv->getEntities() as $entity) {
            if (!$entity->isAlive() || $entity->closed || $entity === $this->player) continue;
            $eId = $entity->getId();
            if (isset($this->hitTargets[$eId])) continue;
            $isValid = false;
            if ($entity instanceof Player) {
                if ($this->toggle !== null && !$this->plugin->canTargetPlayer($this->player->getName(), $entity)) continue;
                $isValid = true;
            } elseif ($entity instanceof NPCEntity) { $isValid = true; }
            elseif ($entity instanceof FactoryEntity) { $isValid = true; }
            if (!$isValid) continue;
            $dx = $entity->x - $px; $dz = $entity->z - $pz;
            $dist = sqrt($dx * $dx + $dz * $dz);
            if ($dist > $hitRange || $dist <= 0) continue;
            $dot = ($dx / $dist) * $this->dirX + ($dz / $dist) * $this->dirZ;
            if ($dot < 0.45) continue;
            $this->hitTargets[$eId] = true;
            $scale = 1 - ($dist / $this->coneRange) * 0.25;
            $dmg   = $this->damage * $scale;
            $ev = new EntityDamageByEntityEvent($this->player, $entity, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $dmg);
            $entity->attack($dmg, $ev);
            // Knockback away
            $entity->setMotion(new Vector3($dx / $dist * 2.0, 0.7, $dz / $dist * 2.0));
            if ($entity instanceof Player) $entity->sendTip(TextFormat::LIGHT_PURPLE . "Hit by sonic wave!");
            // Note burst on hit
            $pk4 = new LevelEventPacket(); $pk4->evid = 2000; $pk4->data = mt_rand(0, 24);
            $pk4->x = (float)$entity->x; $pk4->y = (float)($entity->y + 1.5); $pk4->z = (float)$entity->z;
            foreach ($lv->getPlayers() as $pl) { $pl->dataPacket($pk4); }
            $lv->addParticle(new ExplodeParticle(new Vector3($entity->x, $entity->y + 1, $entity->z)));
        }
    }

    private function getNearby($lv) {
        $out = [];
        $px = $this->player->x; $pz = $this->player->z;
        foreach ($lv->getPlayers() as $p) {
            if (abs($p->x - $px) <= self::VIEW_RANGE && abs($p->z - $pz) <= self::VIEW_RANGE) $out[] = $p;
        }
        return $out;
    }
}

class ResonanceCollapseTask extends Task {

    private $plugin;
    private $player;
    private $orbDamage;
    private $bigDamage;
    private $holeDamage;
    private $toggle;
    private $tick = 0;
    private $cleaned = false;
    private $dirX;
    private $dirZ;

    private $smallOrbs = [];
    private $smallOrbSpawned = false;
    private $bigOrb = null;
    private $bigOrbSpawned = false;
    private $bigOrbX;
    private $bigOrbY;
    private $bigOrbZ;
    private $holeX;
    private $holeY;
    private $holeZ;
    private $holeActive = false;
    private $hitInHole = [];
    private $holePulsePhase = 0.0;

    private $noteBlocks = [];
    private $noteBlocksSpawned = false;

    const ORB_TYPE = 86;
    const VIEW_RANGE = 60;
    const SMALL_ORB_COUNT = 5;
    const NOTEBLOCK_COUNT = 8;
    const NOTEBLOCK_ID = 25;

    private static $nextEid = 300000;

    private static function newEid() {
        $e = self::$nextEid++;
        if (self::$nextEid > 399999) self::$nextEid = 300000;
        return $e;
    }

    private static $ORB_COLORS = [
        [50, 100, 255],
        [255, 50, 50],
        [255, 220, 0],
        [0, 255, 100],
        [180, 0, 255],
    ];

    public function __construct($plugin, Player $player, $orbDamage, $bigDamage, $holeDamage, $toggle) {
        $this->plugin = $plugin;
        $this->player = $player;
        $this->orbDamage = $orbDamage;
        $this->bigDamage = $bigDamage;
        $this->holeDamage = $holeDamage;
        $this->toggle = $toggle;
        $dir = $player->getDirectionVector();
        $len = sqrt($dir->x * $dir->x + $dir->z * $dir->z);
        $this->dirX = $len > 0 ? $dir->x / $len : 0;
        $this->dirZ = $len > 0 ? $dir->z / $len : 0;
        $res = Effect::getEffect(Effect::DAMAGE_RESISTANCE);
        $res->setAmplifier(4);
        $res->setDuration(25);
        $res->setVisible(false);
        $player->addEffect($res);
    }

    public function onRun($currentTick) {
        if ($this->player->closed || !$this->player->isAlive()) {
            $this->cleanup();
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
            return;
        }
        $this->tick++;
        $lv = $this->player->getLevel();
        $players = $this->getNearby($lv);

        if ($this->tick <= 10) {
            $this->phaseOrbit($lv, $players);
        } elseif ($this->tick <= 20) {
            $this->phaseLaunch($lv, $players);
        } elseif ($this->tick <= 50) {
            $this->phaseTravel($lv, $players);
        } elseif ($this->tick <= 130) {
            $this->phaseBlackHole($lv, $players);
        } elseif ($this->tick <= 160) {
            $this->phaseFinale($lv, $players);
        } else {
            $this->cleanup();
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
        }
    }

    private function phaseOrbit($lv, $players) {
        $px = $this->player->x;
        $py = $this->player->y;
        $pz = $this->player->z;
        $t = $this->tick;

        if (!$this->smallOrbSpawned) {
            $this->spawnSmallOrbs($lv, $players, $px, $py, $pz);
            $this->spawnBigOrb($lv, $players, $px, $py, $pz);
            $this->smallOrbSpawned = true;
            $this->bigOrbSpawned = true;
            $lv->addSound(new EndermanTeleportSound(new Vector3($px, $py, $pz)));
        }

        foreach ($this->smallOrbs as $idx => &$orb) {
            $a = ($idx / self::SMALL_ORB_COUNT) * M_PI * 2 + $t * 0.35;
            $ox = $px + cos($a) * 1.8;
            $oz = $pz + sin($a) * 1.8;
            $oy = $py + 0.7 + sin($t * 0.5 + $idx) * 0.2;
            $orb["x"] = $ox;
            $orb["y"] = $oy;
            $orb["z"] = $oz;
            $pk = new MoveEntityPacket();
            $pk->eid = $orb["eid"];
            $pk->x = (float)$ox;
            $pk->y = (float)$oy;
            $pk->z = (float)$oz;
            $pk->yaw = $t * 20;
            $pk->pitch = 0;
            $pk->headYaw = $t * 20;
            foreach ($players as $pl) {
                $pl->dataPacket($pk);
            }
            $col = self::$ORB_COLORS[$idx];
            $lv->addParticle(new DustParticle(new Vector3($ox, $oy, $oz), $col[0], $col[1], $col[2]));
        }
        unset($orb);

        if ($this->bigOrb !== null) {
            $bx = $px + $this->dirX * 1.5;
            $by = $py + 0.8 + sin($t * 0.6) * 0.15;
            $bz = $pz + $this->dirZ * 1.5;
            $this->bigOrbX = $bx;
            $this->bigOrbY = $by;
            $this->bigOrbZ = $bz;
            $pk2 = new MoveEntityPacket();
            $pk2->eid = $this->bigOrb;
            $pk2->x = (float)$bx;
            $pk2->y = (float)$by;
            $pk2->z = (float)$bz;
            $pk2->yaw = $t * 10;
            $pk2->pitch = 0;
            $pk2->headYaw = $t * 10;
            foreach ($players as $pl) {
                $pl->dataPacket($pk2);
            }
            $lv->addParticle(new DustParticle(new Vector3($bx, $by, $bz), SoundSound::COL_GOLD_R, SoundSound::COL_GOLD_G, SoundSound::COL_GOLD_B));
            $lv->addParticle(new InstantEnchantParticle(new Vector3($bx, $by, $bz)));
        }

        $arpChord = [2, 5, 9, 14];
        $arpPitch = $arpChord[($t - 1) % count($arpChord)];
        if ($t % 2 === 0) {
            $pk3 = new LevelEventPacket();
            $pk3->evid = 2000;
            $pk3->data = $arpPitch;
            $pk3->x = (float)$px;
            $pk3->y = (float)($py + 1.5);
            $pk3->z = (float)$pz;
            foreach ($players as $pl) {
                $pl->dataPacket($pk3);
            }
            $lv->addSound(new NoteblockSound(new Vector3($px, $py + 1, $pz), NoteblockSound::INSTRUMENT_PIANO, $arpPitch));
        }
        if ($t % 3 === 0) {
            $lv->addSound(new NoteblockSound(new Vector3($px, $py, $pz), NoteblockSound::INSTRUMENT_BASS, 2));
        }
    }

    private function phaseLaunch($lv, $players) {
        $px = $this->player->x;
        $py = $this->player->y;
        $pz = $this->player->z;
        $step = $this->tick - 10;

        $orbIndex = (int)(($step - 1) / 2);
        if ($orbIndex < self::SMALL_ORB_COUNT && isset($this->smallOrbs[$orbIndex]) && !isset($this->smallOrbs[$orbIndex]["launched"])) {
            $this->smallOrbs[$orbIndex]["launched"] = true;
            $fanA = atan2($this->dirZ, $this->dirX) + ($orbIndex - 2) * 0.2;
            $this->smallOrbs[$orbIndex]["vx"] = cos($fanA) * 1.2;
            $this->smallOrbs[$orbIndex]["vz"] = sin($fanA) * 1.2;
            $this->smallOrbs[$orbIndex]["vy"] = 0.0;
            $lv->addSound(new BlazeShootSound(new Vector3($px, $py, $pz)));
        }

        foreach ($this->smallOrbs as $idx => &$orb) {
            if (!isset($orb["launched"])) continue;
            $orb["x"] += $orb["vx"];
            $orb["y"] += $orb["vy"];
            $orb["z"] += $orb["vz"];
            $pk = new MoveEntityPacket();
            $pk->eid = $orb["eid"];
            $pk->x = (float)$orb["x"];
            $pk->y = (float)$orb["y"];
            $pk->z = (float)$orb["z"];
            $pk->yaw = $this->tick * 30;
            $pk->pitch = 0;
            $pk->headYaw = $this->tick * 30;
            foreach ($players as $pl) {
                $pl->dataPacket($pk);
            }
            $col = self::$ORB_COLORS[$idx];
            $lv->addParticle(new DustParticle(new Vector3($orb["x"] - $orb["vx"] * 0.5, $orb["y"] + 0.3, $orb["z"] - $orb["vz"] * 0.5), $col[0], $col[1], $col[2]));
            $lv->addParticle(new InstantEnchantParticle(new Vector3($orb["x"], $orb["y"], $orb["z"])));
            $this->checkOrbHit($lv, $orb["x"], $orb["y"], $orb["z"], $this->orbDamage, $col, $idx);
        }
        unset($orb);

        if ($step === 9 && $this->bigOrb !== null) {
            $lv->addSound(new GhastSound(new Vector3($px, $py, $pz)));
            $lv->addSound(new ExplodeSound(new Vector3($px, $py, $pz)));
            $pk4 = new LevelEventPacket();
            $pk4->evid = 2002;
            $pk4->data = SoundSound::COL_SPLASH_GOLD;
            $pk4->x = (float)$px;
            $pk4->y = (float)$py;
            $pk4->z = (float)$pz;
            foreach ($players as $pl) {
                $pl->dataPacket($pk4);
            }
        }
    }

    private function phaseTravel($lv, $players) {
        $step = $this->tick - 20;
        if ($this->bigOrb === null) return;

        if ($step === 1) {
            $this->bigOrbX = $this->player->x + $this->dirX * 1.5;
            $this->bigOrbY = $this->player->y + 0.8;
            $this->bigOrbZ = $this->player->z + $this->dirZ * 1.5;
        }

        $speed = 1.4;
        $this->bigOrbX += $this->dirX * $speed;
        $this->bigOrbZ += $this->dirZ * $speed;

        $lv->addParticle(new DustParticle(new Vector3($this->bigOrbX, $this->bigOrbY, $this->bigOrbZ), SoundSound::COL_GOLD_R, SoundSound::COL_GOLD_G, SoundSound::COL_GOLD_B));
        for ($i = 0; $i < 3; $i++) {
            $a = ($i / 3) * M_PI * 2 + $step * 0.5;
            $lv->addParticle(new DustParticle(new Vector3($this->bigOrbX + cos($a) * 0.4, $this->bigOrbY + sin($a) * 0.4, $this->bigOrbZ), SoundSound::COL_PINK_R, SoundSound::COL_PINK_G, SoundSound::COL_PINK_B));
        }
        $lv->addParticle(new InstantEnchantParticle(new Vector3($this->bigOrbX, $this->bigOrbY, $this->bigOrbZ)));

        $glidePitch = min(24, ($step - 1) * 3);
        if ($step % 2 === 0) {
            $pk = new LevelEventPacket();
            $pk->evid = 2000;
            $pk->data = $glidePitch;
            $pk->x = (float)$this->bigOrbX;
            $pk->y = (float)($this->bigOrbY + 0.5);
            $pk->z = (float)$this->bigOrbZ;
            foreach ($players as $pl) {
                $pl->dataPacket($pk);
            }
            $lv->addSound(new NoteblockSound(new Vector3($this->bigOrbX, $this->bigOrbY, $this->bigOrbZ), NoteblockSound::INSTRUMENT_PIANO, $glidePitch));
        }

        $pk2 = new MoveEntityPacket();
        $pk2->eid = $this->bigOrb;
        $pk2->x = (float)$this->bigOrbX;
        $pk2->y = (float)$this->bigOrbY;
        $pk2->z = (float)$this->bigOrbZ;
        $pk2->yaw = $step * 15;
        $pk2->pitch = 0;
        $pk2->headYaw = $step * 15;
        foreach ($players as $pl) {
            $pl->dataPacket($pk2);
        }

        $dist = sqrt(($this->bigOrbX - $this->player->x) ** 2 + ($this->bigOrbZ - $this->player->z) ** 2);
        $hit = $this->checkOrbHit($lv, $this->bigOrbX, $this->bigOrbY, $this->bigOrbZ, $this->bigDamage, [255, 200, 0], -1);

        if ($hit || $dist >= 42.0 || $step >= 30) {
            $this->triggerExplosion($lv, $players);
        }
    }

    private function triggerExplosion($lv, $players) {
        if ($this->bigOrb !== null) {
            $mv = new MoveEntityPacket();
            $mv->eid = $this->bigOrb;
            $mv->x = 0.0;
            $mv->y = -100.0;
            $mv->z = 0.0;
            $mv->yaw = 0.0;
            $mv->pitch = 0.0;
            $mv->headYaw = 0.0;
            foreach ($players as $pl) {
                try { $pl->dataPacket($mv); } catch (\Exception $e) {}
            }
            $this->plugin->getServer()->getScheduler()->scheduleDelayedTask(new SoundDelayedRemoveTask($this->plugin, $lv, [$this->bigOrb]), 40);
            $this->bigOrb = null;
        }

        $smallEids = [];
        foreach ($this->smallOrbs as $orb) {
            $mv = new MoveEntityPacket();
            $mv->eid = $orb["eid"];
            $mv->x = 0.0;
            $mv->y = -100.0;
            $mv->z = 0.0;
            $mv->yaw = 0.0;
            $mv->pitch = 0.0;
            $mv->headYaw = 0.0;
            foreach ($players as $pl) {
                try { $pl->dataPacket($mv); } catch (\Exception $e) {}
            }
            $smallEids[] = $orb["eid"];
        }
        if (!empty($smallEids)) {
            $this->plugin->getServer()->getScheduler()->scheduleDelayedTask(new SoundDelayedRemoveTask($this->plugin, $lv, $smallEids), 40);
        }
        $this->smallOrbs = [];

        $ex = $this->bigOrbX;
        $ey = $this->bigOrbY;
        $ez = $this->bigOrbZ;

        for ($i = 0; $i < 20; $i++) {
            $a = ($i / 20) * M_PI * 2;
            $lv->addParticle(new DustParticle(new Vector3($ex + cos($a) * 2.0, $ey + 0.5, $ez + sin($a) * 2.0), SoundSound::COL_GREEN_R, SoundSound::COL_GREEN_G, SoundSound::COL_GREEN_B));
        }
        for ($i = 0; $i < 8; $i++) {
            $a = ($i / 8) * M_PI * 2;
            for ($d = 1; $d <= 4; $d++) {
                $lv->addParticle(new InstantEnchantParticle(new Vector3($ex + cos($a) * $d, $ey + 0.5 + $d * 0.3, $ez + sin($a) * $d)));
            }
        }
        $lv->addParticle(new LargeExplodeParticle(new Vector3($ex, $ey + 0.5, $ez)));
        $lv->addParticle(new ExplodeParticle(new Vector3($ex, $ey + 0.5, $ez)));

        $pk = new LevelEventPacket();
        $pk->evid = 2002;
        $pk->data = SoundSound::COL_SPLASH_GOLD;
        $pk->x = (float)$ex;
        $pk->y = (float)$ey;
        $pk->z = (float)$ez;
        foreach ($players as $pl) {
            $pl->dataPacket($pk);
        }

        $lv->addSound(new GhastSound(new Vector3($ex, $ey, $ez)));
        $lv->addSound(new ExplodeSound(new Vector3($ex, $ey, $ez)));
        $lv->addSound(new AnvilUseSound(new Vector3($ex, $ey, $ez)));

        $this->holeX = $ex;
        $this->holeY = $ey;
        $this->holeZ = $ez;
        $this->holeActive = true;
        $this->tick = 50;
    }

    private function spawnNoteBlocks($lv) {
        $hx = $this->holeX;
        $hy = $this->holeY;
        $hz = $this->holeZ;

        for ($i = 0; $i < self::NOTEBLOCK_COUNT; $i++) {
            $angle = ($i / self::NOTEBLOCK_COUNT) * M_PI * 2;
            $radius = 3.5 + ($i % 3) * 0.8;
            $heightOffset = 1.0 + ($i % 4) * 0.6;
            $orbitSpeed = 0.08 + ($i % 3) * 0.02;
            $bobSpeed = 0.15 + ($i % 2) * 0.05;
            $bobAmount = 0.3 + ($i % 3) * 0.1;

            $x = $hx + cos($angle) * $radius;
            $y = $hy + $heightOffset;
            $z = $hz + sin($angle) * $radius;

            $eid = BlockEffects::newEid();
            BlockEffects::sendSpawn($lv, $eid, self::NOTEBLOCK_ID, 0, $x, $y, $z);

            $this->noteBlocks[$eid] = [
                "eid" => $eid,
                "angle" => $angle,
                "radius" => $radius,
                "heightOffset" => $heightOffset,
                "orbitSpeed" => $orbitSpeed,
                "bobSpeed" => $bobSpeed,
                "bobAmount" => $bobAmount,
                "x" => $x,
                "y" => $y,
                "z" => $z
            ];
        }

        $this->noteBlocksSpawned = true;
    }

    private function updateNoteBlocks($lv, $t) {
        $hx = $this->holeX;
        $hy = $this->holeY;
        $hz = $this->holeZ;

        foreach ($this->noteBlocks as $eid => &$nb) {
            $nb["angle"] += $nb["orbitSpeed"];

            $bob = sin($t * $nb["bobSpeed"]) * $nb["bobAmount"];

            $nb["x"] = $hx + cos($nb["angle"]) * $nb["radius"];
            $nb["y"] = $hy + $nb["heightOffset"] + $bob;
            $nb["z"] = $hz + sin($nb["angle"]) * $nb["radius"];

            $yaw = rad2deg($nb["angle"]) + $t * 5;
            $pitch = sin($t * 0.1) * 10;

            BlockEffects::sendMove($lv, $eid, $nb["x"], $nb["y"], $nb["z"], $yaw, $pitch);

            if ($t % 4 === 0) {
                $lv->addParticle(new DustParticle(
                    new Vector3($nb["x"], $nb["y"] + 0.5, $nb["z"]),
                    SoundSound::COL_PINK_R, SoundSound::COL_PINK_G, SoundSound::COL_PINK_B
                ));
            }

            if ($t % 6 === 0) {
                $lv->addParticle(new InstantEnchantParticle(new Vector3($nb["x"], $nb["y"] + 0.3, $nb["z"])));
            }
        }
        unset($nb);
    }

    private function removeNoteBlocks($lv) {
        if (empty($this->noteBlocks)) return;
        BlockEffects::voidAndRemove($this->plugin, $lv, array_keys($this->noteBlocks));
        $this->noteBlocks = [];
        $this->noteBlocksSpawned = false;
    }

    private function phaseBlackHole($lv, $players) {
        if (!$this->holeActive) return;

        $this->holePulsePhase += 0.2;
        $hx = $this->holeX;
        $hy = $this->holeY;
        $hz = $this->holeZ;
        $t = $this->tick - 50;
        $progress = $t / 79.0;

        if (!$this->noteBlocksSpawned) {
            $this->spawnNoteBlocks($lv);
        }

        $this->updateNoteBlocks($lv, $t);

        $coreR = min(3.5, 0.5 + $t * 0.08);
        $pts = 14;
        for ($i = 0; $i < $pts; $i++) {
            $a = ($i / $pts) * M_PI * 2 - $this->holePulsePhase;
            $lv->addParticle(new DustParticle(new Vector3($hx + cos($a) * $coreR, $hy + 0.1, $hz + sin($a) * $coreR), SoundSound::COL_BLACK_R, SoundSound::COL_BLACK_G, SoundSound::COL_BLACK_B));
        }

        for ($i = 0; $i < 10; $i++) {
            $a = ($i / 10) * M_PI * 2 + $this->holePulsePhase * 0.7;
            $r = $coreR + 0.8;
            $lv->addParticle(new DustParticle(new Vector3($hx + cos($a) * $r, $hy + 0.2, $hz + sin($a) * $r), SoundSound::COL_PURPLE_R, SoundSound::COL_PURPLE_G, SoundSound::COL_PURPLE_B));
        }

        if ($t % 2 === 0) {
            $lv->addParticle(new SmokeParticle(new Vector3($hx + (mt_rand(-5, 5) / 10), $hy + 0.3, $hz + (mt_rand(-5, 5) / 10))));
        }

        if ($t % 3 === 0) {
            $suckPitch = max(0, 24 - ($t % 25));
            $noteA = mt_rand(0, 628) / 100.0;
            $noteD = 3.0 + mt_rand(0, 20) / 10.0;
            $pk = new LevelEventPacket();
            $pk->evid = 2000;
            $pk->data = $suckPitch;
            $pk->x = (float)($hx + cos($noteA) * $noteD);
            $pk->y = (float)($hy + 1.0);
            $pk->z = (float)($hz + sin($noteA) * $noteD);
            foreach ($players as $pl) {
                $pl->dataPacket($pk);
            }
            $lv->addSound(new NoteblockSound(new Vector3($hx, $hy, $hz), NoteblockSound::INSTRUMENT_BASS, $suckPitch % 13));
        }

        if ($t % 8 === 0) {
            $lv->addSound(new NoteblockSound(new Vector3($hx, $hy, $hz), NoteblockSound::INSTRUMENT_BASS_DRUM, 0));
        }

        if ($t % 15 === 0) {
            $this->tickHoleDamage($lv, $hx, $hy, $hz);
        }
    }

    private function tickHoleDamage($lv, $hx, $hy, $hz) {
        $owner = $this->plugin->getServer()->getPlayerExact($this->player->getName());
        foreach ($lv->getEntities() as $entity) {
            if (!$entity->isAlive() || $entity->closed || $entity === $this->player) continue;
            $isValid = false;
            if ($entity instanceof Player) {
                if ($this->toggle !== null && !$this->plugin->canTargetPlayer($this->player->getName(), $entity)) continue;
                $isValid = true;
            } elseif ($entity instanceof NPCEntity) {
                $isValid = true;
            } elseif ($entity instanceof FactoryEntity) {
                $isValid = true;
            }
            if (!$isValid) continue;
            $dx = $hx - $entity->x;
            $dy = $hy - $entity->y;
            $dz = $hz - $entity->z;
            $dist = sqrt($dx * $dx + $dy * $dy + $dz * $dz);
            if ($dist > 8.0 || $dist <= 0) continue;
            $pull = 0.5 + ($dist / 8.0) * 0.4;
            $entity->setMotion(new Vector3(($dx / $dist) * $pull, 0.1, ($dz / $dist) * $pull));
            $eKey = $entity->getId();
            if (!isset($this->hitInHole[$eKey])) {
                $this->hitInHole[$eKey] = 0;
            }
            $this->hitInHole[$eKey]++;
            if ($owner !== null) {
                $ev = new EntityDamageByEntityEvent($owner, $entity, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->holeDamage);
                $entity->attack($this->holeDamage, $ev);
            } else {
                $ev = new EntityDamageEvent($entity, EntityDamageEvent::CAUSE_MAGIC, $this->holeDamage);
                $entity->attack($this->holeDamage, $ev);
            }
            if ($entity instanceof Player) {
                $entity->sendTip(TextFormat::DARK_PURPLE . "Pulled into resonance void!");
            }
        }
    }

    private function phaseFinale($lv, $players) {
        $hx = $this->holeX;
        $hy = $this->holeY;
        $hz = $this->holeZ;
        $step = $this->tick - 130;

        if ($step === 1) {
            $this->removeNoteBlocks($lv);

            $chordPitches = [0, 4, 7, 12, 16, 19, 24, 12, 7, 4, 0, 7, 12, 19, 24, 0];
            for ($i = 0; $i < 16; $i++) {
                $a = ($i / 16) * M_PI * 2;
                $pk = new LevelEventPacket();
                $pk->evid = 2000;
                $pk->data = $chordPitches[$i];
                $pk->x = (float)($hx + cos($a) * 1.5);
                $pk->y = (float)($hy + 1.5);
                $pk->z = (float)($hz + sin($a) * 1.5);
                foreach ($players as $pl) {
                    $pl->dataPacket($pk);
                }
                $lv->addSound(new NoteblockSound(new Vector3($hx + cos($a) * 1.5, $hy + 1, $hz + sin($a) * 1.5), NoteblockSound::INSTRUMENT_PIANO, $chordPitches[$i]));
            }

            $lv->addSound(new NoteblockSound(new Vector3($hx, $hy, $hz), NoteblockSound::INSTRUMENT_BASS_DRUM, 0));

            for ($i = 0; $i < 8; $i++) {
                $a = ($i / 8) * M_PI * 2;
                $lv->addParticle(new FlameParticle(new Vector3($hx + cos($a) * 1.2, $hy + 0.5, $hz + sin($a) * 1.2)));
            }

            for ($i = 0; $i < 16; $i++) {
                $a = ($i / 16) * M_PI * 2;
                $lv->addParticle(new DustParticle(new Vector3($hx + cos($a) * 2.5, $hy + 0.3, $hz + sin($a) * 2.5), SoundSound::COL_PINK_R, SoundSound::COL_PINK_G, SoundSound::COL_PINK_B));
            }

            $pk2 = new LevelEventPacket();
            $pk2->evid = 2002;
            $pk2->data = SoundSound::COL_SPLASH_PINK;
            $pk2->x = (float)$hx;
            $pk2->y = (float)$hy;
            $pk2->z = (float)$hz;
            foreach ($players as $pl) {
                $pl->dataPacket($pk2);
            }

            $lv->addSound(new FizzSound(new Vector3($hx, $hy, $hz)));
            $lv->addSound(new PopSound(new Vector3($hx, $hy, $hz)));
        }

        if ($step % 3 === 0) {
            $fadeScale = [24, 19, 12, 7, 0];
            $fadePitch = $fadeScale[min((int)($step / 6), count($fadeScale) - 1)];
            $pk3 = new LevelEventPacket();
            $pk3->evid = 2000;
            $pk3->data = $fadePitch;
            $pk3->x = (float)($hx + (mt_rand(-15, 15) / 10));
            $pk3->y = (float)($hy + 1.0 + $step * 0.1);
            $pk3->z = (float)($hz + (mt_rand(-15, 15) / 10));
            foreach ($players as $pl) {
                $pl->dataPacket($pk3);
            }
            $lv->addParticle(new EnchantParticle(new Vector3($hx, $hy + 1.0 + $step * 0.1, $hz)));
            $lv->addSound(new NoteblockSound(new Vector3($hx, $hy + 1, $hz), NoteblockSound::INSTRUMENT_PIANO, $fadePitch));
        }
    }

    private function checkOrbHit($lv, $ox, $oy, $oz, $damage, $color, $orbIdx) {
        foreach ($lv->getEntities() as $entity) {
            if (!$entity->isAlive() || $entity->closed || $entity === $this->player) continue;
            $key = ($orbIdx >= 0 ? "s{$orbIdx}_" : "big_") . $entity->getId();
            if (isset($this->hitInHole[$key])) continue;
            $isValid = false;
            if ($entity instanceof Player) {
                if ($this->toggle !== null && !$this->plugin->canTargetPlayer($this->player->getName(), $entity)) continue;
                $isValid = true;
            } elseif ($entity instanceof NPCEntity) {
                $isValid = true;
            } elseif ($entity instanceof FactoryEntity) {
                $isValid = true;
            }
            if (!$isValid) continue;
            $dx = $entity->x - $ox;
            $dy = $entity->y - $oy;
            $dz = $entity->z - $oz;
            if (sqrt($dx * $dx + $dy * $dy + $dz * $dz) > 2.0) continue;
            $this->hitInHole[$key] = true;
            $ev = new EntityDamageByEntityEvent($this->player, $entity, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage);
            $entity->attack($damage, $ev);
            $lv->addParticle(new ExplodeParticle(new Vector3($entity->x, $entity->y + 1, $entity->z)));
            $lv->addParticle(new DustParticle(new Vector3($entity->x, $entity->y + 1, $entity->z), $color[0], $color[1], $color[2]));
            if ($entity instanceof Player) {
                $entity->sendTip(TextFormat::LIGHT_PURPLE . "Hit by resonance orb!");
            }
            $lv->addSound(new PopSound(new Vector3($entity->x, $entity->y, $entity->z)));
            if ($orbIdx === -1) return true;
        }
        return false;
    }

    private function spawnSmallOrbs($lv, $players, $px, $py, $pz) {
        for ($i = 0; $i < self::SMALL_ORB_COUNT; $i++) {
            $eid = self::newEid();
            $a = ($i / self::SMALL_ORB_COUNT) * M_PI * 2;
            $ox = $px + cos($a) * 1.8;
            $oy = $py + 0.7;
            $oz = $pz + sin($a) * 1.8;
            $this->smallOrbs[$i] = ["eid" => $eid, "x" => $ox, "y" => $oy, "z" => $oz, "vx" => 0, "vy" => 0, "vz" => 0];
            $pk = new AddEntityPacket();
            $pk->eid = $eid;
            $pk->type = self::ORB_TYPE;
            $pk->x = (float)$ox;
            $pk->y = (float)$oy;
            $pk->z = (float)$oz;
            $pk->speedX = 0.0;
            $pk->speedY = 0.0;
            $pk->speedZ = 0.0;
            $pk->yaw = 0.0;
            $pk->pitch = 0.0;
            $pk->metadata = [Entity::DATA_FLAGS => [Entity::DATA_TYPE_BYTE, 0], Entity::DATA_NO_AI => [Entity::DATA_TYPE_BYTE, 1]];
            foreach ($players as $pl) {
                $pl->dataPacket($pk);
            }
        }
    }

    private function spawnBigOrb($lv, $players, $px, $py, $pz) {
        $eid = self::newEid();
        $bx = $px + $this->dirX * 1.5;
        $by = $py + 0.8;
        $bz = $pz + $this->dirZ * 1.5;
        $this->bigOrb = $eid;
        $this->bigOrbX = $bx;
        $this->bigOrbY = $by;
        $this->bigOrbZ = $bz;
        $pk = new AddEntityPacket();
        $pk->eid = $eid;
        $pk->type = self::ORB_TYPE;
        $pk->x = (float)$bx;
        $pk->y = (float)$by;
        $pk->z = (float)$bz;
        $pk->speedX = 0.0;
        $pk->speedY = 0.0;
        $pk->speedZ = 0.0;
        $pk->yaw = 0.0;
        $pk->pitch = 0.0;
        $pk->metadata = [Entity::DATA_FLAGS => [Entity::DATA_TYPE_BYTE, 0], Entity::DATA_NO_AI => [Entity::DATA_TYPE_BYTE, 1]];
        foreach ($players as $pl) {
            $pl->dataPacket($pk);
        }
    }

    private function cleanup() {
        if ($this->cleaned) return;
        $this->cleaned = true;

        try {
            $lv = $this->player->getLevel();
            $this->removeNoteBlocks($lv);
        } catch (\Exception $e) {}

        $eids = [];
        foreach ($this->smallOrbs as $orb) {
            $eids[] = $orb["eid"];
        }
        if ($this->bigOrb !== null) {
            $eids[] = $this->bigOrb;
            $this->bigOrb = null;
        }
        if (empty($eids)) return;

        try {
            $lv = $this->player->getLevel();
            foreach ($eids as $eid) {
                $mv = new MoveEntityPacket();
                $mv->eid = $eid;
                $mv->x = 0.0;
                $mv->y = -100.0;
                $mv->z = 0.0;
                $mv->yaw = 0.0;
                $mv->pitch = 0.0;
                $mv->headYaw = 0.0;
                foreach ($lv->getPlayers() as $pl) {
                    try { $pl->dataPacket($mv); } catch (\Exception $e) {}
                }
            }
            $this->plugin->getServer()->getScheduler()->scheduleDelayedTask(new SoundDelayedRemoveTask($this->plugin, $lv, $eids), 40);
        } catch (\Exception $e) {}

        $this->smallOrbs = [];
    }

    private function getNearby($lv) {
        $out = [];
        $px = $this->player->x;
        $pz = $this->player->z;
        foreach ($lv->getPlayers() as $p) {
            if (abs($p->x - $px) <= self::VIEW_RANGE && abs($p->z - $pz) <= self::VIEW_RANGE) {
                $out[] = $p;
            }
        }
        return $out;
    }
}

// ── SoundDelayedRemoveTask ────────────────────────────────────────────────────
class SoundDelayedRemoveTask extends Task {
    private $plugin; private $level; private $eids;
    public function __construct($plugin, Level $level, array $eids) {
        $this->plugin = $plugin; $this->level = $level; $this->eids = $eids;
    }
    public function onRun($currentTick) {
        $all = [];
        try { if ($this->level !== null) { foreach ($this->level->getPlayers() as $pl) { $all[spl_object_hash($pl)] = $pl; } } } catch (\Exception $e) {}
        try { foreach ($this->plugin->getServer()->getOnlinePlayers() as $pl) { $all[spl_object_hash($pl)] = $pl; } } catch (\Exception $e) {}
        foreach ($this->eids as $eid) {
            $mv = new MoveEntityPacket(); $mv->eid = $eid; $mv->x = 0.0; $mv->y = 0.0; $mv->z = 0.0;
            $mv->yaw = 0.0; $mv->pitch = 0.0; $mv->headYaw = 0.0;
            $rm = new RemoveEntityPacket(); $rm->eid = $eid;
            foreach ($all as $pl) { try { $pl->dataPacket($mv); $pl->dataPacket($rm); } catch (\Exception $e) {} }
        }
    }
}