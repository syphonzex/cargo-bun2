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
use pocketmine\level\particle\InstantEnchantParticle;
use pocketmine\level\particle\EnchantParticle;
use pocketmine\level\particle\FlameParticle;
use pocketmine\level\particle\LargeExplodeParticle;
use pocketmine\level\particle\SmokeParticle;
use pocketmine\level\sound\AnvilUseSound;
use pocketmine\level\sound\PopSound;
use pocketmine\level\sound\ExplodeSound;
use pocketmine\level\sound\BlazeShootSound;
use pocketmine\level\sound\GhastSound;
use pocketmine\level\sound\FizzSound;
use pocketmine\level\sound\BatSound;
use pocketmine\level\sound\ClickSound;
use OnePiece\Devil\BlockEffects;
use pocketmine\network\protocol\LevelEventPacket;
use OnePiece\NPC\NPCEntity;
use OnePieceTrades\Factory\FactoryEntity;

class TrexTrex extends BaseFruit {

    const COL_GREEN_R  = 0;   const COL_GREEN_G  = 255; const COL_GREEN_B  = 80;
    const COL_NEON_R   = 80;  const COL_NEON_G   = 255; const COL_NEON_B   = 100;
    const COL_BLACK_R  = 15;  const COL_BLACK_G  = 15;  const COL_BLACK_B  = 15;
    const COL_DARK_R   = 0;   const COL_DARK_G   = 80;  const COL_DARK_B   = 30;
    const EV_SPLASH    = 2002;
    const COL_SPLASH_NEON  = 65484;   // neon green
    const COL_SPLASH_BLACK = 1118481; // near-black

    public function getId()          { return "trex_trex"; }
    public function getDisplayName() { return "T-Rex T-Rex Fruit"; }
    public function getDescription() { return "Ancient Zoan - Transform into the apex predator. Raw power that rivals even the mammoth."; }
    public function getType()        { return "zoan"; }
    public function getRarity()      { return "mythical"; }

    public function getAbilityNames() {
        return [
            "ability1" => "Rex Pull",
            "ability2" => "Primal Charge"
        ];
    }

    public function getAbilityCooldowns() {
        return [
            "ability1" => 8.0,
            "ability2" => 6.0
        ];
    }

    public function useAbility(Player $player, $ability) {
        switch ($ability) {
            case "ability1": return $this->primalSlash($player);
            case "ability2": return $this->rexRoar($player);
        }
        return 0;
    }

    // ── Ability 1: Primal Slash ───────────────────────────────────────────
    // Bloxfruit T-Rex M1: fast forward lunge + neon green claw slashes
    // Three rapid hits, each applying neon claw VFX
        private function primalSlash(Player $player) {
        $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
        $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

        $mult   = min(1.5, $this->getHakiMultiplier($player));
        $pullDamage = min(5.0, 3.0 * $mult);
        $roarDamage = min(5.0, 3.0 * $mult);
        $coneRange  = 12.0;

        $pullTask = new TrexPullRoarTask(
            $this->plugin,
            $player,
            $pullDamage,
            $roarDamage,
            $coneRange,
            $toggle
        );
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($pullTask, 1);

        $player->sendTip(TextFormat::DARK_GREEN . TextFormat::BOLD . "REX PULL!");

        return $this->getAbilityCooldowns()["ability1"];
    }

    // ── Ability 2: Rex Roar ───────────────────────────────────────────────
    // Bloxfruit T-Rex X: massive AoE stomp-roar, launches all nearby enemies
    // + spawns persistent neon dome domain
        private function rexRoar(Player $player) {
        $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
        $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

        $mult   = min(1.5, $this->getHakiMultiplier($player));
        $damage = min(12.0, 4.5 * $mult);

        $chargeTask = new TrexChargeTask($this->plugin, $player, $damage, $toggle);
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($chargeTask, 1);

        $player->sendTip(TextFormat::DARK_GREEN . TextFormat::BOLD . "PRIMAL CHARGE!");

        return $this->getAbilityCooldowns()["ability2"];
    }

    // ── VFX ──────────────────────────────────────────────────────────────

    

    

    

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
        $player->sendMessage(TextFormat::GREEN . "=== T-Rex T-Rex Fruit ===");
        $player->sendMessage(TextFormat::WHITE . "Ancient Apex Predator - Mythical Zoan");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::GREEN . "[Tap]: " . TextFormat::WHITE . "Rex Pull");
        $player->sendMessage(TextFormat::GRAY  . "  Pull enemies from a cone, then roar and repel");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::GREEN . "[Sneak+Tap]: " . TextFormat::WHITE . "Primal Charge");
        $player->sendMessage(TextFormat::GRAY  . "  Charge forward as a T-Rex, tearing everything in your path");
        $player->sendMessage(TextFormat::GREEN . "========================");
    }

    public function onUnequip(Player $player) {
        $player->sendMessage(TextFormat::GRAY . "Ancient power fades...");
    }
}

// ── PrimalSlashTask ───────────────────────────────────────────────────────────
// 3-hit rapid claw combo, each hit with neon VFX

// ── TrexRoarDomainTask ────────────────────────────────────────────────────────
// Persistent neon green pulsing dome — NOT copying phoenix structure
// Uses a ground-crawling ring pattern that surges upward in spikes

class TrexChargeTask extends Task {

    private $plugin;
    private $player;
    private $damage;
    private $toggle;
    private $tick         = 0;
    private $dirX;
    private $dirZ;
    private $chargeStep   = 0;
    private $hitTargets   = [];

    private $debris       = [];
    private $cleaned      = false;

    const PHASE_WINDUP = 6;
    const PHASE_CHARGE = 20;
    const PHASE_SLAM   = 28;
    const VIEW_RANGE        = 50;


    public function __construct($plugin, Player $player, $damage, $toggle) {
        $this->plugin = $plugin;
        $this->player = $player;
        $this->damage = $damage;
        $this->toggle = $toggle;
        $dir = $player->getDirectionVector();
        $len = sqrt($dir->x * $dir->x + $dir->z * $dir->z);
        $this->dirX = $len > 0 ? $dir->x / $len : 0;
        $this->dirZ = $len > 0 ? $dir->z / $len : 0;
        $res = Effect::getEffect(Effect::DAMAGE_RESISTANCE);
        $res->setAmplifier(4); $res->setDuration(35); $res->setVisible(false);
        $player->addEffect($res);
    }

    public function onRun($currentTick) {
        if ($this->player->closed || !$this->player->isAlive()) {
            $this->cleanupDebris();
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
            return;
        }
        $this->tick++;
        $lv = $this->player->getLevel();
        $players = $this->getNearby($lv);

        if ($this->tick <= self::PHASE_WINDUP) {
            $this->doWindup($lv, $players);
        } elseif ($this->tick <= self::PHASE_CHARGE) {
            $this->doCharge($lv, $players);
        } elseif ($this->tick <= self::PHASE_SLAM) {
            $this->doSlam($lv, $players);
        } else {
            $this->cleanupDebris();
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
        }

        // Always tick debris physics
        $this->tickDebris($players);
    }

    private function getNearby($lv) {
        $out = [];
        $px = $this->player->x; $pz = $this->player->z;
        foreach ($lv->getPlayers() as $p) {
            if (abs($p->x - $px) <= self::VIEW_RANGE && abs($p->z - $pz) <= self::VIEW_RANGE) $out[] = $p;
        }
        return $out;
    }

    // ── WINDUP: ground cracks, small debris start rising ─────────────────
    private function doWindup($lv, $players) {
        $px = $this->player->x; $py = $this->player->y; $pz = $this->player->z;
        $t  = $this->tick;

        // Ground crack rings expanding
        $r = $t * 0.4;
        $pts = 8 + $t * 3;
        for ($i = 0; $i < $pts; $i++) {
            $a = ($i / $pts) * M_PI * 2;
            $lv->addParticle(new DustParticle(new Vector3($px + cos($a) * $r, $py + 0.05, $pz + sin($a) * $r), 80, 80, 80));
        }
        // Early debris flickers
        if ($t >= 3) {
            for ($i = 0; $i < 3; $i++) {
                $lv->addParticle(new CriticalParticle(new Vector3($px + (mt_rand(-10,10)/10), $py + 0.5, $pz + (mt_rand(-10,10)/10))));
            }
        }
        // Player floats slightly
        if ($t === 1) $this->player->setMotion(new Vector3(0, 0.15, 0));
        elseif ($t <= 5) $this->player->setMotion(new Vector3(0, 0.03, 0));
    }

    // ── CHARGE: white explosion burst → expanding neon sphere + forward charge
    private function doCharge($lv, $players) {
        $px = $this->player->x; $py = $this->player->y; $pz = $this->player->z;
        $step = $this->tick - self::PHASE_WINDUP; // 1..14
        $this->chargeStep++;

        // ── Ticks 1-5: EXPLOSION BURST (white/grey rocks scatter) ─────────
        if ($step <= 5) {
            if ($step === 1) {
                $this->spawnExplosionDebris($lv, $px, $py, $pz, $players, 10);
                // White flash burst
                for ($i = 0; $i < 16; $i++) {
                    $a = ($i / 16) * M_PI * 2;
                    $lv->addParticle(new DustParticle(new Vector3($px + cos($a) * 0.8, $py + 1.0, $pz + sin($a) * 0.8), 255, 255, 255));
                }
                $lv->addParticle(new ExplodeParticle(new Vector3($px, $py + 0.5, $pz)));
                $lv->addParticle(new LargeExplodeParticle(new Vector3($px, $py + 0.5, $pz)));
                $pk = new LevelEventPacket(); $pk->evid = 2002; $pk->data = 16777215;
                $pk->x = (float)$px; $pk->y = (float)($py+0.5); $pk->z = (float)$pz;
                foreach ($players as $pl) { $pl->dataPacket($pk); }
                $lv->addSound(new ExplodeSound(new Vector3($px, $py, $pz)));
            }
            // Continued white streaks
            for ($i = 0; $i < 6; $i++) {
                $a = ($i / 6) * M_PI * 2 + $step * 0.3;
                $d = 0.5 + $step * 0.5;
                $lv->addParticle(new DustParticle(new Vector3($px + cos($a) * $d, $py + 1.0 + (mt_rand(-5,5)/10), $pz + sin($a) * $d), 200, 200, 200));
            }
            $lv->addParticle(new CriticalParticle(new Vector3($px, $py + 1.0, $pz)));
        }

        // ── Ticks 3-14: EXPANDING NEON GREEN SPHERE ───────────────────────
        if ($step >= 3) {
            $sphereProgress = ($step - 3) / 11.0; // 0..1
            $sphereR = 1.5 + $sphereProgress * 4.5; // grows 1.5 → 6.0

            // Equator ring
            $eqPts = 18;
            for ($i = 0; $i < $eqPts; $i++) {
                $a = ($i / $eqPts) * M_PI * 2;
                $lv->addParticle(new DustParticle(new Vector3($px + cos($a) * $sphereR, $py + 0.8, $pz + sin($a) * $sphereR), TrexTrex::COL_NEON_R, TrexTrex::COL_NEON_G, TrexTrex::COL_NEON_B));
            }
            // Upper/lower hemisphere arcs
            foreach ([0.45, -0.45, 0.8, -0.8] as $phi) {
                $arcR = cos($phi) * $sphereR;
                $arcY = $py + 0.8 + sin($phi) * $sphereR;
                $arcPts = max(8, (int)($eqPts * abs(cos($phi))));
                for ($i = 0; $i < $arcPts; $i++) {
                    $a = ($i / $arcPts) * M_PI * 2;
                    $lv->addParticle(new DustParticle(new Vector3($px + cos($a) * $arcR, $arcY, $pz + sin($a) * $arcR), TrexTrex::COL_NEON_R, TrexTrex::COL_NEON_G, TrexTrex::COL_NEON_B));
                }
            }
            // Neon green claw streaks — 6 spokes radiating outward
            if ($step % 2 === 0) {
                for ($spoke = 0; $spoke < 6; $spoke++) {
                    $sa = ($spoke / 6) * M_PI * 2 + $step * 0.4;
                    for ($d = 0; $d < 5; $d++) {
                        $dist = ($d / 4) * $sphereR;
                        $lv->addParticle(new InstantEnchantParticle(new Vector3($px + cos($sa) * $dist, $py + 0.8 + sin($d * 0.5) * 0.5, $pz + sin($sa) * $dist)));
                    }
                    // Claw tip flash at sphere edge
                    $lv->addParticle(new CriticalParticle(new Vector3($px + cos($sa) * $sphereR, $py + 0.8, $pz + sin($sa) * $sphereR)));
                }
            }
            // Inner dark core swirl
            $innerR = $sphereR * 0.3;
            for ($i = 0; $i < 4; $i++) {
                $a = ($i / 4) * M_PI * 2 + $step * 1.5;
                $lv->addParticle(new DustParticle(new Vector3($px + cos($a) * $innerR, $py + 0.8 + sin($a) * $innerR * 0.5, $pz + sin($a) * $innerR), TrexTrex::COL_BLACK_R, TrexTrex::COL_BLACK_G, TrexTrex::COL_BLACK_B));
            }
        }

        // ── Ticks 7+: player rockets forward through the sphere ────────────
        if ($step >= 10) {
            $this->player->setMotion(new Vector3($this->dirX * 0.85, 0.06, $this->dirZ * 0.85));
            $this->hitEntities($lv, 2.5);
        } else {
            // Keep player still during burst
            $this->player->setMotion(new Vector3(0, 0.03, 0));
        }
    }

    // ── SLAM: sphere collapses inward + final 3 large claw marks ─────────
    private function doSlam($lv, $players) {
        if ($this->tick !== self::PHASE_CHARGE + 1) return;
        $this->player->setMotion(new Vector3(0, 0, 0));
        $px = $this->player->x; $py = $this->player->y; $pz = $this->player->z;

        // Sphere collapse: rings shrinking inward
        for ($ring = 5; $ring >= 1; $ring--) {
            $rr  = $ring * 1.1;
            $pts = 14;
            for ($i = 0; $i < $pts; $i++) {
                $a = ($i / $pts) * M_PI * 2;
                $lv->addParticle(new DustParticle(new Vector3($px + cos($a) * $rr, $py + 0.8, $pz + sin($a) * $rr), TrexTrex::COL_NEON_R, TrexTrex::COL_NEON_G, TrexTrex::COL_NEON_B));
            }
        }
        // Final 3 big neon claw marks forward
        for ($claw = 0; $claw < 3; $claw++) {
            $baseA = ($claw / 3) * M_PI * 1.4 - M_PI * 0.7;
            for ($i = 0; $i < 8; $i++) {
                $prog = $i / 7.0; $a = $baseA + ($prog - 0.5) * 0.5;
                $lv->addParticle(new DustParticle(new Vector3($px + $this->dirX * $prog * 3.0 + cos($a) * 0.5, $py + 1.2 - $prog * 0.3, $pz + $this->dirZ * $prog * 3.0 + sin($a) * 0.5), TrexTrex::COL_NEON_R, TrexTrex::COL_NEON_G, TrexTrex::COL_NEON_B));
            }
            $lv->addParticle(new InstantEnchantParticle(new Vector3($px + $this->dirX * ($claw + 1) * 0.9, $py + 1.0, $pz + $this->dirZ * ($claw + 1) * 0.9)));
        }
        // Ground shockwave
        for ($i = 0; $i < 16; $i++) {
            $a = ($i / 16) * M_PI * 2;
            $lv->addParticle(new DustParticle(new Vector3($px + cos($a) * 3.0, $py + 0.05, $pz + sin($a) * 3.0), TrexTrex::COL_BLACK_R, TrexTrex::COL_BLACK_G, TrexTrex::COL_BLACK_B));
        }
        $lv->addParticle(new LargeExplodeParticle(new Vector3($px, $py + 0.5, $pz)));

        // Slam damage
        foreach ($lv->getEntities() as $entity) {
            if (!$entity->isAlive() || $entity->closed || $entity === $this->player) continue;
            $isValid = false;
            if ($entity instanceof Player) {
                if ($this->toggle !== null && !$this->plugin->canTargetPlayer($this->player->getName(), $entity)) continue;
                $isValid = true;
            } elseif ($entity instanceof \OnePiece\NPC\NPCEntity) { $isValid = true; }
            elseif ($entity instanceof \OnePieceTrades\Factory\FactoryEntity) { $isValid = true; }
            if (!$isValid) continue;
            if (sqrt(($entity->x - $px) ** 2 + ($entity->z - $pz) ** 2) > 3.5) continue;
            $ev = new EntityDamageByEntityEvent($this->player, $entity, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->damage);
            $entity->attack($this->damage, $ev);
            $dx = $entity->x - $px; $dz = $entity->z - $pz;
            $len = sqrt($dx*$dx + $dz*$dz);
            if ($len > 0) BaseFruit::staticSafeSetMotion($this->player, $entity, new Vector3($dx/$len * 2.2, 0.9, $dz/$len * 2.2));
            if ($entity instanceof Player) $entity->sendTip(TextFormat::DARK_GREEN . TextFormat::BOLD . "SLAMMED!");
        }

        $lv->addSound(new GhastSound(new Vector3($px, $py, $pz)));
        $lv->addSound(new ExplodeSound(new Vector3($px, $py, $pz)));
        $lv->addSound(new AnvilUseSound(new Vector3($px, $py, $pz)));
        $pk = new LevelEventPacket(); $pk->evid = 2002; $pk->data = TrexTrex::COL_SPLASH_BLACK;
        $pk->x = (float)$px; $pk->y = (float)$py; $pk->z = (float)$pz;
        foreach ($lv->getPlayers() as $pl) { $pl->dataPacket($pk); }
    }

    private function spawnExplosionDebris($lv, $cx, $cy, $cz, $players, $count) {
        $new = BlockEffects::spawnDebris($this->plugin, $lv, $cx, $cy, $cz, $count, 0.55, 2.0, 35);
        foreach ($new as $eid => $d) { $this->debris[$eid] = $d; }
    }

private function tickDebris($players) {
    if (empty($this->debris)) return;
    $lv = $this->player->getLevel();
    if ($lv === null) return;
    $toRemove = BlockEffects::tickDebris($this->debris, $lv, $this->player->y - 1);
    foreach ($toRemove as $eid) { unset($this->debris[$eid]); }
}

    private function cleanupDebris() {
        if ($this->cleaned) return;
        $this->cleaned = true;
        try { $lv = $this->player->getLevel(); } catch (\Exception $e) { return; }
        BlockEffects::voidAndRemove($this->plugin, $lv, array_keys($this->debris));
        $this->debris = [];
    }

    // ── Hit entities in forward cone during charge ────────────────────────
    private function hitEntities($lv, $radius) {
        $px = $this->player->x; $py = $this->player->y; $pz = $this->player->z;
        foreach ($lv->getEntities() as $entity) {
            if (!$entity->isAlive() || $entity->closed || $entity === $this->player) continue;
            $eId = $entity->getId();
            if (isset($this->hitTargets[$eId])) continue;
            $isValid = false;
            if ($entity instanceof Player) {
                if ($this->toggle !== null && !$this->plugin->canTargetPlayer($this->player->getName(), $entity)) continue;
                $isValid = true;
            } elseif ($entity instanceof \OnePiece\NPC\NPCEntity) { $isValid = true; }
            elseif ($entity instanceof \OnePieceTrades\Factory\FactoryEntity) { $isValid = true; }
            if (!$isValid) continue;
            $dx = $entity->x - $px; $dz = $entity->z - $pz;
            $dot = $dx * $this->dirX + $dz * $this->dirZ;
            if ($dot > 0 && sqrt($dx*$dx + $dz*$dz) <= $radius) {
                $this->hitTargets[$eId] = true;
                $dmg = $this->damage * 0.6;
                $ev  = new EntityDamageByEntityEvent($this->player, $entity, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $dmg);
                $entity->attack($dmg, $ev);
                $ex = $entity->x; $ey = $entity->y + 1; $ez = $entity->z;
                for ($s = 0; $s < 3; $s++) {
                    $sa = ($s/3) * M_PI * 2;
                    $lv->addParticle(new DustParticle(new Vector3($ex+cos($sa)*0.9, $ey+sin($sa*2)*0.4, $ez+sin($sa)*0.9), TrexTrex::COL_NEON_R, TrexTrex::COL_NEON_G, TrexTrex::COL_NEON_B));
                }
                $lv->addParticle(new CriticalParticle(new Vector3($ex, $ey, $ez)));
                $lv->addParticle(new ExplodeParticle(new Vector3($ex, $ey, $ez)));
                $pk = new LevelEventPacket(); $pk->evid = 2002; $pk->data = TrexTrex::COL_SPLASH_NEON;
                $pk->x = (float)$ex; $pk->y = (float)$ey; $pk->z = (float)$ez;
                foreach ($lv->getPlayers() as $pl) { $pl->dataPacket($pk); }
                if ($entity instanceof Player) $entity->sendTip(TextFormat::DARK_GREEN . TextFormat::BOLD . "CLAWED!");
            }
        }
    }
}


class TrexPullRoarTask extends Task {

    private $plugin;
    private $player;
    private $pullDamage;
    private $roarDamage;
    private $coneRange;
    private $toggle;
    private $tick      = 0;
    private $cleaned   = false;
    private $debris    = [];
    private $pulledTargets = [];
    private $roaredTargets = [];

    const PHASE_PULL  = 20;
    const PHASE_ROAR  = 30;
    const VIEW_RANGE        = 60;


    public function __construct($plugin, Player $player, $pullDamage, $roarDamage, $coneRange, $toggle) {
        $this->plugin      = $plugin;
        $this->player      = $player;
        $this->pullDamage  = $pullDamage;
        $this->roarDamage  = $roarDamage;
        $this->coneRange   = $coneRange;
        $this->toggle      = $toggle;
        $res = Effect::getEffect(Effect::DAMAGE_RESISTANCE);
        $res->setAmplifier(4); $res->setDuration(35); $res->setVisible(false);
        $player->addEffect($res);
    }

    public function onRun($currentTick) {
        if ($this->player->closed || !$this->player->isAlive()) {
            $this->cleanup();
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
            return;
        }
        $this->tick++;
        $lv      = $this->player->getLevel();
        $players = $this->getNearby($lv);

        if ($this->tick <= self::PHASE_PULL) {
            $this->doPull($lv, $players);
        } elseif ($this->tick <= self::PHASE_ROAR) {
            $this->doRoar($lv, $players);
        } else {
            $this->cleanup();
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
            return;
        }
        $this->tickDebris($players);
    }

    private function getNearby($lv) {
        $out = [];
        $px = $this->player->x; $pz = $this->player->z;
        foreach ($lv->getPlayers() as $p) {
            if (abs($p->x - $px) <= self::VIEW_RANGE && abs($p->z - $pz) <= self::VIEW_RANGE) $out[] = $p;
        }
        return $out;
    }

    // ── PULL phase: white wind spiraling inward + drag targets ────────────
    private function doPull($lv, $players) {
        $px  = $this->player->x; $py = $this->player->y; $pz = $this->player->z;
        $t   = $this->tick;
        $dir = $this->player->getDirectionVector();
        $dirLen = sqrt($dir->x * $dir->x + $dir->z * $dir->z);
        $dirX = $dirLen > 0 ? $dir->x / $dirLen : 0;
        $dirZ = $dirLen > 0 ? $dir->z / $dirLen : 0;

        // White wind spiral: curves sweep inward from cone toward player
        $spiralArms = 3;
        for ($arm = 0; $arm < $spiralArms; $arm++) {
            $armPhase = ($arm / $spiralArms) * M_PI * 2;
            $steps    = 10;
            for ($s = 0; $s < $steps; $s++) {
                $prog   = $s / ($steps - 1); // 1=far, 0=near player
                $dist   = (1 - $prog) * $this->coneRange * 0.8;
                // Spiral inward: angle sweeps as distance decreases
                $swirl  = $armPhase - $prog * M_PI * 1.8 + $t * 0.35;
                $coneA  = atan2($dirZ, $dirX); // forward direction angle
                $spread = ($prog * 0.7); // cone wider at far end
                $finalA = $coneA + cos($swirl) * $spread;
                $wx     = $px + cos($finalA) * $dist;
                $wy     = $py + 0.8 + sin($swirl * 2) * 0.5 + (1 - $prog) * 1.5;
                $wz     = $pz + sin($finalA) * $dist;
                // White for far, slight neon tinge near player
                if ($prog < 0.3) {
                    $lv->addParticle(new DustParticle(new Vector3($wx, $wy, $wz), TrexTrex::COL_NEON_R, TrexTrex::COL_NEON_G, TrexTrex::COL_NEON_B));
                } else {
                    $lv->addParticle(new DustParticle(new Vector3($wx, $wy, $wz), 240, 255, 240));
                }
                if ($s % 3 === 0) {
                    $lv->addParticle(new InstantEnchantParticle(new Vector3($wx, $wy + 0.1, $wz)));
                }
            }
        }

        // Spawn debris on tick 1 — rocks flying inward from cone
        if ($t === 1) {
            $this->spawnPullDebris($lv, $px, $py, $pz, $players, $dirX, $dirZ, 10);
            $lv->addSound(new FizzSound(new Vector3($px, $py, $pz)));
            $lv->addSound(new FizzSound(new Vector3($px, $py + 0.5, $pz)));
        }

        // Pull targets in frontal cone toward player every 4 ticks
        if ($t % 4 === 0) {
            $this->pullTargets($lv, $px, $py, $pz, $dirX, $dirZ);
            $lv->addSound(new BatSound(new Vector3($px + $dirX * 4, $py + 1, $pz + $dirZ * 4)));
        }
        if ($t % 6 === 0) {
            $lv->addSound(new ClickSound(new Vector3($px, $py + 1, $pz)));
        }

        // Tight inner swirl near player
        for ($i = 0; $i < 5; $i++) {
            $a = ($i / 5) * M_PI * 2 - $t * 0.8;
            $r = 0.6 + sin($t * 0.3 + $i) * 0.2;
            $lv->addParticle(new DustParticle(new Vector3($px + cos($a) * $r, $py + 0.9 + sin($a * 2) * 0.3, $pz + sin($a) * $r), 220, 255, 220));
        }
    }

    private function pullTargets($lv, $px, $py, $pz, $dirX, $dirZ) {
        foreach ($lv->getEntities() as $entity) {
            if (!$entity->isAlive() || $entity->closed) continue;
            if ($entity === $this->player) continue;
            $isValid = false;
            if ($entity instanceof Player) {
                if ($this->toggle !== null && !$this->plugin->canTargetPlayer($this->player->getName(), $entity)) continue;
                $isValid = true;
            } elseif ($entity instanceof \OnePiece\NPC\NPCEntity) { $isValid = true; }
            elseif ($entity instanceof \OnePieceTrades\Factory\FactoryEntity) { $isValid = true; }
            if (!$isValid) continue;

            $dx   = $entity->x - $px; $dz = $entity->z - $pz;
            $dist = sqrt($dx * $dx + $dz * $dz);
            if ($dist <= 0 || $dist > $this->coneRange) continue;

            // Cone check: must be in frontal 120 degree cone
            $dot = ($dx / $dist) * $dirX + ($dz / $dist) * $dirZ;
            if ($dot < 0.5) continue;

            // Pull toward player
            $pullStrength = 0.45 + ($dist / $this->coneRange) * 0.3;
            BaseFruit::staticSafeSetMotion($this->player, $entity, new Vector3(-($dx / $dist) * $pullStrength, 0.15, -($dz / $dist) * $pullStrength));

            // Damage on first pull contact
            $eId = $entity->getId();
            if (!isset($this->pulledTargets[$eId])) {
                $this->pulledTargets[$eId] = true;
                $ev = new EntityDamageByEntityEvent($this->player, $entity, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->pullDamage);
                $entity->attack($this->pullDamage, $ev);
                if ($entity instanceof Player) $entity->sendTip(TextFormat::DARK_GREEN . "Pulled in by ancient wind!");
            }
        }
    }

    // ── ROAR phase: neon green sphere burst + repel ───────────────────────
    private function doRoar($lv, $players) {
        $px  = $this->player->x; $py = $this->player->y; $pz = $this->player->z;
        $step = $this->tick - self::PHASE_PULL; // 1..10
        $sphereR = $step * 0.9; // grows 0.9 → 9.0

        // Announce roar on first tick
        if ($step === 1) {
            $this->player->sendTip(TextFormat::DARK_GREEN . TextFormat::BOLD . "REX ROAR!");
            // White flash
            for ($i = 0; $i < 16; $i++) {
                $a = ($i / 16) * M_PI * 2;
                $lv->addParticle(new DustParticle(new Vector3($px + cos($a) * 0.6, $py + 1.0, $pz + sin($a) * 0.6), 255, 255, 255));
            }
            $lv->addParticle(new ExplodeParticle(new Vector3($px, $py + 0.8, $pz)));
            $lv->addParticle(new LargeExplodeParticle(new Vector3($px, $py + 0.8, $pz)));
            $pk = new LevelEventPacket(); $pk->evid = 2002; $pk->data = 16777215;
            $pk->x = (float)$px; $pk->y = (float)($py + 0.8); $pk->z = (float)$pz;
            foreach ($players as $pl) { $pl->dataPacket($pk); }
            $lv->addSound(new GhastSound(new Vector3($px, $py, $pz)));
            $lv->addSound(new ExplodeSound(new Vector3($px, $py, $pz)));
            $lv->addSound(new AnvilUseSound(new Vector3($px, $py, $pz)));
            $lv->addSound(new AnvilUseSound(new Vector3($px + 1, $py, $pz)));

            // Repel all pulled targets
            $this->repelTargets($lv, $px, $py, $pz);
        }

        // Expanding neon green sphere
        $eqPts = 16;
        for ($i = 0; $i < $eqPts; $i++) {
            $a = ($i / $eqPts) * M_PI * 2;
            $lv->addParticle(new DustParticle(new Vector3($px + cos($a) * $sphereR, $py + 0.8, $pz + sin($a) * $sphereR), TrexTrex::COL_NEON_R, TrexTrex::COL_NEON_G, TrexTrex::COL_NEON_B));
        }
        foreach ([0.5, -0.5, 0.85, -0.85] as $phi) {
            $arcR = cos($phi) * $sphereR; $arcY = $py + 0.8 + sin($phi) * $sphereR;
            $arcPts = max(6, (int)($eqPts * abs(cos($phi))));
            for ($i = 0; $i < $arcPts; $i++) {
                $a = ($i / $arcPts) * M_PI * 2;
                $lv->addParticle(new DustParticle(new Vector3($px + cos($a) * $arcR, $arcY, $pz + sin($a) * $arcR), TrexTrex::COL_NEON_R, TrexTrex::COL_NEON_G, TrexTrex::COL_NEON_B));
            }
        }
        // Neon claw spokes radiating outward
        if ($step % 2 === 0) {
            for ($spoke = 0; $spoke < 6; $spoke++) {
                $sa = ($spoke / 6) * M_PI * 2 + $step * 0.5;
                for ($d = 0; $d < 5; $d++) {
                    $dist = ($d / 4) * $sphereR;
                    $lv->addParticle(new InstantEnchantParticle(new Vector3($px + cos($sa) * $dist, $py + 0.8 + sin($d * 0.5) * 0.4, $pz + sin($sa) * $dist)));
                }
                $lv->addParticle(new CriticalParticle(new Vector3($px + cos($sa) * $sphereR, $py + 0.8, $pz + sin($sa) * $sphereR)));
            }
        }
        // Splash
        if ($step <= 3) {
            $pk = new LevelEventPacket(); $pk->evid = 2002; $pk->data = TrexTrex::COL_SPLASH_NEON;
            $pk->x = (float)$px; $pk->y = (float)$py; $pk->z = (float)$pz;
            foreach ($players as $pl) { $pl->dataPacket($pk); }
        }
    }

    private function repelTargets($lv, $px, $py, $pz) {
        foreach ($lv->getEntities() as $entity) {
            if (!$entity->isAlive() || $entity->closed || $entity === $this->player) continue;

            // Only repel entities that were actually pulled
            $eId = $entity->getId();
            if (!isset($this->pulledTargets[$eId])) continue;

            $isValid = false;
            if ($entity instanceof Player) {
                if ($this->toggle !== null && !$this->plugin->canTargetPlayer($this->player->getName(), $entity)) continue;
                $isValid = true;
            } elseif ($entity instanceof \OnePiece\NPC\NPCEntity) { $isValid = true; }
            elseif ($entity instanceof \OnePieceTrades\Factory\FactoryEntity) { $isValid = true; }
            if (!$isValid) continue;

            $dx = $entity->x - $px; $dz = $entity->z - $pz;
            $dist = sqrt($dx * $dx + $dz * $dz);
            $ev = new EntityDamageByEntityEvent($this->player, $entity, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->roarDamage);
            $entity->attack($this->roarDamage, $ev);
            $len = $dist > 0 ? $dist : 1;
            BaseFruit::staticSafeSetMotion($this->player, $entity, new Vector3($dx / $len * 2.5, 1.0, $dz / $len * 2.5));
            $slow = Effect::getEffect(Effect::SLOWNESS);
            $slow->setAmplifier(2); $slow->setDuration(60); $slow->setVisible(false);
            if ($entity instanceof Player) {
                BaseFruit::staticSafeAddEffect($this->player, $entity, $slow);
                $entity->sendTip(TextFormat::DARK_GREEN . TextFormat::BOLD . "REX ROAR! Blown away!");
            }
        }
    }

private function spawnPullDebris($lv, $cx, $cy, $cz, $players, $dirX, $dirZ, $count) {
    $count = mt_rand(2, 3);
    $spawnDist = 8.0;
    $blocks = BlockEffects::scanBlocks($lv, (int)($cx + $dirX * $spawnDist), (int)$cy, (int)($cz + $dirZ * $spawnDist), 6, $count);
    $debris = [];
    for ($i = 0; $i < $count; $i++) {
        $offset = ($i - ($count - 1) / 2) * 1.5;
        $perpX = -$dirZ;
        $perpZ = $dirX;
        $sx = $cx + $dirX * $spawnDist + $perpX * $offset + (mt_rand(-10, 10) / 10);
        $sy = $cy - 0.5 + (mt_rand(0, 5) / 10);
        $sz = $cz + $dirZ * $spawnDist + $perpZ * $offset + (mt_rand(-10, 10) / 10);
        $blockData = $blocks[$i % count($blocks)];
        $eid = BlockEffects::newEid();
        BlockEffects::sendSpawn($lv, $eid, $blockData["id"], $blockData["damage"], $sx, $sy, $sz);
        $pullSpeed = 0.35 + (mt_rand(0, 20) / 100);
        $totalLife = 25 + mt_rand(0, 8);
        $targetY = $cy + 0.8;
        $yDiff = $targetY - $sy;
        $debris[$eid] = [
            "eid" => $eid,
            "x" => (float)$sx,
            "y" => (float)$sy,
            "z" => (float)$sz,
            "vx" => -$dirX * $pullSpeed,
            "vy" => 0.0,
            "vz" => -$dirZ * $pullSpeed,
            "life" => $totalLife,
            "tick" => 0,
            "startY" => (float)$sy,
            "targetY" => (float)$targetY,
            "pull" => true
        ];
    }
    foreach ($debris as $eid => $d) { $this->debris[$eid] = $d; }
}

private function tickDebris($players) {
    if (empty($this->debris)) return;
    $lv = $this->player->getLevel();
    if ($lv === null) return;
    $pullRemove = [];
    foreach ($this->debris as $eid => &$d) {
        if (isset($d["pull"]) && $d["pull"] === true) {
            $d["tick"]++;
            if ($d["tick"] >= $d["life"]) {
                BlockEffects::sendRemove($eid);
                $pullRemove[] = $eid;
                continue;
            }
            $prog = min(1.0, $d["tick"] / $d["life"]);
            $ease = sin($prog * M_PI * 0.5);
            $d["vx"] *= 0.97;
            $d["vz"] *= 0.97;
            $d["x"] += $d["vx"];
            $d["z"] += $d["vz"];
            $d["y"] = $d["startY"] + ($d["targetY"] - $d["startY"]) * $ease;
            BlockEffects::sendMove($lv, $eid, $d["x"], $d["y"], $d["z"], $d["tick"] * 12, $d["tick"] * 8);
            continue;
        }
    }
    unset($d);
    foreach ($pullRemove as $eid) { unset($this->debris[$eid]); }
    $normalDebris = [];
    foreach ($this->debris as $eid => &$d) {
        if (!isset($d["pull"]) || $d["pull"] !== true) {
            $normalDebris[$eid] = &$d;
        }
    }
    unset($d);
    if (!empty($normalDebris)) {
        $toRemove = BlockEffects::tickDebris($normalDebris, $lv, $this->player->y - 1);
        foreach ($toRemove as $eid) { unset($this->debris[$eid]); }
    }
}

    private function cleanup() {
        if ($this->cleaned) return;
        $this->cleaned = true;
        try { $lv = $this->player->getLevel(); } catch (\Exception $e) { return; }
        BlockEffects::voidAndRemove($this->plugin, $lv, array_keys($this->debris));
        $this->debris = [];
    }
}