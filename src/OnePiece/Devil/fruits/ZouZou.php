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
use pocketmine\level\particle\MobSpellParticle;
use pocketmine\level\particle\CriticalParticle;
use pocketmine\level\particle\ExplodeParticle;
use pocketmine\level\particle\InstantEnchantParticle;
use pocketmine\level\particle\EnchantParticle;
use pocketmine\level\particle\SmokeParticle;
use pocketmine\level\sound\AnvilUseSound;
use pocketmine\level\sound\PopSound;
use pocketmine\level\sound\ClickSound;
use pocketmine\level\sound\FizzSound;
use OnePiece\Devil\BlockEffects;
use pocketmine\network\protocol\LevelEventPacket;
use OnePiece\NPC\NPCEntity;
use OnePieceTrades\Factory\FactoryEntity;

class ZouZou extends BaseFruit {

    const COL_BROWN_R = 139;
    const COL_BROWN_G = 90;
    const COL_BROWN_B = 43;
    const COL_TAN_R = 210;
    const COL_TAN_G = 180;
    const COL_TAN_B = 140;
    const COL_DUST_R = 180;
    const COL_DUST_G = 150;
    const COL_DUST_B = 100;
    const COL_DARK_R = 80;
    const COL_DARK_G = 50;
    const COL_DARK_B = 30;
    const VIEW_RANGE = 50;
    const EV_SPLASH = 2002;
    const COL_SPLASH_BROWN = 9127187;

    public function getId() { return "zou_zou"; }
    public function getDisplayName() { return "Mammoth-Mammoth Fruit"; }
    public function getDescription() { return "Mammoth Fruit - Jack's ancient extinction-class power."; }
    public function getType() { return "zoan"; }
    public function getRarity() { return "mythical"; }

    public function getAbilityNames() {
        return [
            "ability1" => "Tusk Impale",
            "ability2" => "Extinction Stomp"
        ];
    }

    public function getAbilityCooldowns() {
        return [
            "ability1" => 15.0,
            "ability2" => 22.0
        ];
    }

    public function useAbility(Player $player, $ability) {
        switch ($ability) {
            case "ability1": return $this->tuskImpale($player);
            case "ability2": return $this->extinctionStomp($player);
        }
        return 0;
    }

    private function tuskImpale(Player $player) {
        $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
        $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

        $mult   = min(1.5, $this->getHakiMultiplier($player));
        $grabDamage = min(2.0, 2.0 * $mult);
        $slamDamage = min(2.0, 1.5 * $mult);
        $throwDamage = min(5.0, 2.5 * $mult);
      //  $grabDamage = 1.5 * $mult;
      //  $slamDamage = 1.5 * $mult;
     //   $throwDamage = 2.5 * $mult;
        $grabRange = 8.0;

        $target = $this->findFrontTarget($player, $grabRange);

        if ($target === null) {
            $player->sendTip(TextFormat::GOLD . "TUSK IMPALE... no target!");
            $this->spawnMissVFX($player);
            return 3.0;
        }

        if ($target instanceof Player) {
            if (!$this->plugin->canTargetPlayer($player->getName(), $target)) {
                $reason = $this->plugin->getTargetBlockReason($player->getName(), $target);
                if ($reason !== null) $player->sendTip($reason);
                return 3.0;
            }
        }

        $this->spawnGrabVFX($player, $target);

        $target->attack($grabDamage, new EntityDamageByEntityEvent(
            $player,
            $target,
            EntityDamageEvent::CAUSE_ENTITY_ATTACK,
            $grabDamage
        ));

        $impaleTask = new TuskImpaleTask(
            $this->plugin,
            $player,
            $target,
            $slamDamage,
            $throwDamage
        );
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($impaleTask, 1);

        $player->sendTip(TextFormat::GOLD . TextFormat::BOLD . "TUSK IMPALE!");
        $player->sendMessage(TextFormat::GOLD . "Grabbed target with tusks!");

        if ($target instanceof Player) {
            $target->sendTip(TextFormat::DARK_RED . TextFormat::BOLD . "IMPALED!");
            $target->sendMessage(TextFormat::DARK_RED . "You've been grabbed by mammoth tusks!");
        }

        return $this->getAbilityCooldowns()["ability1"];
    }

    private function extinctionStomp(Player $player) {
        $mult   = min(1.5, $this->getHakiMultiplier($player));
        $baseDamage = min(8.0, 4.5 * $mult);
        $radius = 10.0;

        $this->spawnJumpStartVFX($player);

        $stompTask = new ExtinctionStompTask(
            $this->plugin,
            $player,
            $baseDamage,
            $radius
        );
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($stompTask, 1);

        $player->sendTip(TextFormat::GOLD . TextFormat::BOLD . "EXTINCTION STOMP!");

        return $this->getAbilityCooldowns()["ability2"];
    }

    private function spawnMissVFX(Player $player) {
        $lv = $player->getLevel();
        $px = $player->x;
        $py = $player->y + 1;
        $pz = $player->z;
        $dir = $player->getDirectionVector();

        for ($i = 0; $i < 8; $i++) {
            $d = 1 + $i * 0.5;
            $lv->addParticle(new DustParticle(
                new Vector3($px + $dir->x * $d, $py, $pz + $dir->z * $d),
                self::COL_DUST_R, self::COL_DUST_G, self::COL_DUST_B
            ));
        }

        $lv->addSound(new ClickSound(new Vector3($px, $py, $pz)));
    }

    private function spawnGrabVFX(Player $player, Entity $target) {
        $lv = $player->getLevel();
        $pPos = $player->getPosition();
        $tPos = $target->getPosition();

        $px = $pPos->x;
        $py = $pPos->y + 1.2;
        $pz = $pPos->z;
        $tx = $tPos->x;
        $ty = $tPos->y + 1;
        $tz = $tPos->z;

        for ($tusk = 0; $tusk < 2; $tusk++) {
            $offset = ($tusk === 0) ? -0.4 : 0.4;
            $dir = $player->getDirectionVector();
            $perpX = -$dir->z * $offset;
            $perpZ = $dir->x * $offset;

            for ($i = 0; $i <= 10; $i++) {
                $prog = $i / 10;
                $x = $px + $perpX + ($tx - $px) * $prog;
                $y = $py + ($ty - $py) * $prog + sin($prog * M_PI) * 0.5;
                $z = $pz + $perpZ + ($tz - $pz) * $prog;

                $lv->addParticle(new DustParticle(
                    new Vector3($x, $y, $z),
                    self::COL_TAN_R, self::COL_TAN_G, self::COL_TAN_B
                ));
            }
        }

        for ($i = 0; $i < 8; $i++) {
            $a = ($i / 8) * M_PI * 2;
            $lv->addParticle(new DustParticle(
                new Vector3($tx + cos($a) * 1.0, $ty, $tz + sin($a) * 1.0),
                self::COL_BROWN_R, self::COL_BROWN_G, self::COL_BROWN_B
            ));
        }

        for ($i = 0; $i < 6; $i++) {
            $lv->addParticle(new CriticalParticle(new Vector3(
                $tx + (mt_rand(-8, 8) / 10),
                $ty + (mt_rand(0, 10) / 10),
                $tz + (mt_rand(-8, 8) / 10)
            )));
        }

        $this->sendSplash($lv, $tx, $ty, $tz, self::COL_SPLASH_BROWN);
        $lv->addSound(new ClickSound(new Vector3($tx, $ty, $tz)));
        $lv->addSound(new PopSound(new Vector3($px, $py, $pz)));
    }

    private function spawnJumpStartVFX(Player $player) {
        $lv = $player->getLevel();
        $px = $player->x;
        $py = $player->y + 0.3;
        $pz = $player->z;

        for ($ring = 0; $ring < 2; $ring++) {
            $rr = 1.0 + $ring * 0.8;
            for ($i = 0; $i < 10; $i++) {
                $a = ($i / 10) * M_PI * 2;
                $lv->addParticle(new DustParticle(
                    new Vector3($px + cos($a) * $rr, $py, $pz + sin($a) * $rr),
                    self::COL_DUST_R, self::COL_DUST_G, self::COL_DUST_B
                ));
            }
        }

        for ($i = 0; $i < 8; $i++) {
            $lv->addParticle(new SmokeParticle(new Vector3(
                $px + (mt_rand(-12, 12) / 10),
                $py + (mt_rand(0, 8) / 10),
                $pz + (mt_rand(-12, 12) / 10)
            )));
        }

        $lv->addSound(new PopSound(new Vector3($px, $py, $pz)));
    }

    private function sendSplash($lv, $x, $y, $z, $col) {
        $pk = new LevelEventPacket();
        $pk->evid = self::EV_SPLASH;
        $pk->data = $col;
        $pk->x = (float)$x;
        $pk->y = (float)$y;
        $pk->z = (float)$z;
        foreach ($lv->getPlayers() as $pl) {
            $pl->dataPacket($pk);
        }
    }

    public function onEquip(Player $player) {
        $player->sendMessage(TextFormat::GOLD . "=== Zou-Zou no Mi, Model: Mammoth ===");
        $player->sendMessage(TextFormat::WHITE . "Ancient Zoan - Jack the Drought's Power");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::GOLD . "[Tap]: " . TextFormat::WHITE . "TUSK IMPALE");
        $player->sendMessage(TextFormat::GRAY . "  Grab enemy with tusks, slam and throw");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::GOLD . "[Sneak+Tap]: " . TextFormat::WHITE . "EXTINCTION STOMP");
        $player->sendMessage(TextFormat::GRAY . "  Leap high and crash down with quake waves");
        $player->sendMessage(TextFormat::GOLD . "=====================================");
    }

    public function onUnequip(Player $player) {
        $player->sendMessage(TextFormat::GRAY . "The ancient beast returns to slumber...");
    }
}

class TuskImpaleTask extends Task {

    private $plugin;
    private $player;
    private $target;
    private $slamDamage;
    private $throwDamage;
    private $ticksRan = 0;
    private $phase = 0;
    private $cleaned = false;

    const PHASE_LIFT = 0;
    const PHASE_HOLD = 1;
    const PHASE_SLAM = 2;
    const PHASE_THROW = 3;
    const PHASE_DONE = 4;

    const LIFT_TICKS = 15;
    const HOLD_TICKS = 20;
    const SLAM_TICKS = 6;
    const THROW_TICKS = 4;

    const COL_BROWN_R = 139;
    const COL_BROWN_G = 90;
    const COL_BROWN_B = 43;
    const COL_TAN_R = 210;
    const COL_TAN_G = 180;
    const COL_TAN_B = 140;
    const COL_DUST_R = 180;
    const COL_DUST_G = 150;
    const COL_DUST_B = 100;
    const COL_DARK_R = 80;
    const COL_DARK_G = 50;
    const COL_DARK_B = 30;
    const EV_SPLASH = 2002;
    const COL_SPLASH_BROWN = 9127187;

    public function __construct($plugin, Player $player, Entity $target, $slamDamage, $throwDamage) {
        $this->plugin = $plugin;
        $this->player = $player;
        $this->target = $target;
        $this->slamDamage = $slamDamage;
        $this->throwDamage = $throwDamage;
    }

    public function onRun($currentTick) {
        if ($this->cleaned) return;

        if ($this->player === null || !$this->player->isOnline() || $this->target === null || !$this->target->isAlive() || $this->target->closed) {
            $this->cleanup();
            return;
        }

        if ($this->target instanceof Player) {
            if (!$this->plugin->canTargetPlayer($this->player->getName(), $this->target)) {
                BaseFruit::staticSafeSetMotion($this->player, $this->target, new Vector3(0, 0, 0));
                $this->cleanup();
                return;
            }
        }

        $this->ticksRan++;

        switch ($this->phase) {
            case self::PHASE_LIFT:
                $this->doLift();
                break;
            case self::PHASE_HOLD:
                $this->doHold();
                break;
            case self::PHASE_SLAM:
                $this->doSlam();
                break;
            case self::PHASE_THROW:
                $this->doThrow();
                break;
            case self::PHASE_DONE:
                $this->cleanup();
                break;
        }
    }

    private function doLift() {
        $pPos = $this->player->getPosition();
        $dir = $this->player->getDirectionVector();

        $targetX = $pPos->x + $dir->x * 2.5;
        $targetZ = $pPos->z + $dir->z * 2.5;
        $liftProgress = min(1.0, $this->ticksRan / self::LIFT_TICKS);
        $targetY = $pPos->y + 2.5 * $liftProgress;

        $this->target->teleport(new Vector3($targetX, $targetY, $targetZ));
        BaseFruit::staticSafeSetMotion($this->player, $this->target, new Vector3(0, 0.1, 0));

        $lv = $this->player->getLevel();
        $tx = $this->target->x;
        $ty = $this->target->y;
        $tz = $this->target->z;

        if ($this->ticksRan % 2 === 0) {
            for ($i = 0; $i < 4; $i++) {
                $lv->addParticle(new DustParticle(
                    new Vector3($tx + (mt_rand(-5, 5) / 10), $ty + (mt_rand(-5, 10) / 10), $tz + (mt_rand(-5, 5) / 10)),
                    self::COL_TAN_R, self::COL_TAN_G, self::COL_TAN_B
                ));
            }
        }

        if ($this->ticksRan >= self::LIFT_TICKS) {
            $this->phase = self::PHASE_HOLD;
            $this->ticksRan = 0;

            if ($this->target instanceof Player) {
                $this->target->sendTip(TextFormat::DARK_RED . "LIFTED!");
            }
        }
    }

    private function doHold() {
        $pPos = $this->player->getPosition();
        $dir = $this->player->getDirectionVector();

        $targetX = $pPos->x + $dir->x * 2.5;
        $targetY = $pPos->y + 2.5;
        $targetZ = $pPos->z + $dir->z * 2.5;

        $this->target->teleport(new Vector3($targetX, $targetY, $targetZ));
        BaseFruit::staticSafeSetMotion($this->player, $this->target, new Vector3(0, 0, 0));

        $lv = $this->player->getLevel();

        if ($this->ticksRan % 3 === 0) {
            $lv->addParticle(new CriticalParticle(new Vector3(
                $targetX + (mt_rand(-8, 8) / 10),
                $targetY + (mt_rand(-5, 10) / 10),
                $targetZ + (mt_rand(-8, 8) / 10)
            )));
        }

        if ($this->ticksRan >= self::HOLD_TICKS) {
            $this->phase = self::PHASE_SLAM;
            $this->ticksRan = 0;
        }
    }

    private function doSlam() {
        $pPos = $this->player->getPosition();
        $dir = $this->player->getDirectionVector();

        $slamProgress = min(1.0, $this->ticksRan / self::SLAM_TICKS);
        $targetX = $pPos->x + $dir->x * 2.5;
        $targetY = $pPos->y + 2.5 * (1 - $slamProgress);
        $targetZ = $pPos->z + $dir->z * 2.5;

        $this->target->teleport(new Vector3($targetX, $targetY, $targetZ));
            BaseFruit::staticSafeSetMotion($this->player, $this->target, new Vector3(0, -0.5, 0));

        if ($this->ticksRan >= self::SLAM_TICKS) {
            $this->target->attack($this->slamDamage, new EntityDamageByEntityEvent(
                $this->player,
                $this->target,
                EntityDamageEvent::CAUSE_ENTITY_ATTACK,
                $this->slamDamage
            ));

            $this->spawnSlamVFX();

            $nausea = Effect::getEffect(Effect::NAUSEA);
            $nausea->setAmplifier(2);
            $nausea->setDuration(80);
            $nausea->setVisible(false);
            BaseFruit::staticSafeAddEffect($this->player, $this->target, $nausea);

            $slow = Effect::getEffect(Effect::SLOWNESS);
            $slow->setAmplifier(4);
            $slow->setDuration(60);
            $slow->setVisible(false);
            BaseFruit::staticSafeAddEffect($this->player, $this->target, $slow);

            if ($this->target instanceof Player) {
                $this->target->sendTip(TextFormat::DARK_RED . TextFormat::BOLD . "SLAMMED!");
            }

            $this->phase = self::PHASE_THROW;
            $this->ticksRan = 0;
        }
    }

    private function doThrow() {
        if ($this->ticksRan === 1) {
            $dir = $this->player->getDirectionVector();

            BaseFruit::staticSafeSetMotion($this->player, $this->target, new Vector3($dir->x * 3.5, 1.2, $dir->z * 3.5));

            $this->target->attack($this->throwDamage, new EntityDamageByEntityEvent(
                $this->player,
                $this->target,
                EntityDamageEvent::CAUSE_ENTITY_ATTACK,
                $this->throwDamage
            ));

            $this->spawnThrowVFX();

            $this->player->sendMessage(TextFormat::GOLD . "Target thrown!");

            if ($this->target instanceof Player) {
                $this->target->sendTip(TextFormat::DARK_RED . TextFormat::BOLD . "THROWN!");
            }
        }

        if ($this->ticksRan >= self::THROW_TICKS) {
            $this->phase = self::PHASE_DONE;
        }
    }

    private function spawnSlamVFX() {
        $lv = $this->player->getLevel();
        $tx = $this->target->x;
        $ty = $this->target->y;
        $tz = $this->target->z;

        for ($ring = 0; $ring < 4; $ring++) {
            $rr = 1.0 + $ring * 0.8;
            for ($i = 0; $i < 12; $i++) {
                $a = ($i / 12) * M_PI * 2;
                $lv->addParticle(new DustParticle(
                    new Vector3($tx + cos($a) * $rr, $ty + 0.2, $tz + sin($a) * $rr),
                    self::COL_DUST_R, self::COL_DUST_G, self::COL_DUST_B
                ));
            }
        }

        for ($crack = 0; $crack < 12; $crack++) {
            $a = ($crack / 12) * M_PI * 2;
            for ($d = 0; $d < 6; $d++) {
                $dist = 0.3 + $d * 0.6;
                $lv->addParticle(new DustParticle(
                    new Vector3(
                        $tx + cos($a) * $dist + (mt_rand(-3, 3) / 10),
                        $ty + 0.1,
                        $tz + sin($a) * $dist + (mt_rand(-3, 3) / 10)
                    ),
                    self::COL_DARK_R, self::COL_DARK_G, self::COL_DARK_B
                ));
            }
        }

        for ($i = 0; $i < 20; $i++) {
            $lv->addParticle(new SmokeParticle(new Vector3(
                $tx + (mt_rand(-25, 25) / 10),
                $ty + (mt_rand(0, 15) / 10),
                $tz + (mt_rand(-25, 25) / 10)
            )));
        }

        for ($i = 0; $i < 14; $i++) {
            $lv->addParticle(new CriticalParticle(new Vector3(
                $tx + (mt_rand(-20, 20) / 10),
                $ty + (mt_rand(0, 20) / 10),
                $tz + (mt_rand(-20, 20) / 10)
            )));
        }

        $lv->addParticle(new ExplodeParticle(new Vector3($tx, $ty, $tz)));
        $lv->addParticle(new ExplodeParticle(new Vector3($tx, $ty + 0.5, $tz)));

        $this->sendSplash($lv, $tx, $ty, $tz);
        $this->sendSplash($lv, $tx, $ty + 0.5, $tz);

        $lv->addSound(new AnvilUseSound(new Vector3($tx, $ty, $tz)));
        $lv->addSound(new AnvilUseSound(new Vector3($tx, $ty, $tz)));
        $lv->addSound(new PopSound(new Vector3($tx, $ty, $tz)));

        $debrisTask = new MammothDebrisTask(
            $this->plugin,
            $lv,
            $tx,
            $ty,
            $tz,
            4.0
        );
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($debrisTask, 1);
    }

    private function spawnThrowVFX() {
        $lv = $this->player->getLevel();
        $pPos = $this->player->getPosition();
        $tPos = $this->target->getPosition();
        $dir = $this->player->getDirectionVector();

        $px = $pPos->x;
        $py = $pPos->y + 1.5;
        $pz = $pPos->z;

        for ($i = 0; $i < 10; $i++) {
            $d = 0.5 + $i * 0.6;
            $lv->addParticle(new DustParticle(
                new Vector3(
                    $px + $dir->x * $d + (mt_rand(-3, 3) / 10),
                    $py + (mt_rand(-3, 3) / 10),
                    $pz + $dir->z * $d + (mt_rand(-3, 3) / 10)
                ),
                self::COL_TAN_R, self::COL_TAN_G, self::COL_TAN_B
            ));
        }

        for ($i = 0; $i < 8; $i++) {
            $lv->addParticle(new CriticalParticle(new Vector3(
                $tPos->x + (mt_rand(-10, 10) / 10),
                $tPos->y + 1 + (mt_rand(-5, 10) / 10),
                $tPos->z + (mt_rand(-10, 10) / 10)
            )));
        }

        $lv->addSound(new PopSound(new Vector3($px, $py, $pz)));
        $lv->addSound(new FizzSound(new Vector3($tPos->x, $tPos->y, $tPos->z)));
    }

    private function sendSplash($lv, $x, $y, $z) {
        $pk = new LevelEventPacket();
        $pk->evid = self::EV_SPLASH;
        $pk->data = self::COL_SPLASH_BROWN;
        $pk->x = (float)$x;
        $pk->y = (float)$y;
        $pk->z = (float)$z;
        foreach ($lv->getPlayers() as $pl) {
            $pl->dataPacket($pk);
        }
    }

    private function cleanup() {
        if ($this->cleaned) return;
        $this->cleaned = true;
        $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
    }
}

class ExtinctionStompTask extends Task {

    private $plugin;
    private $player;
    private $baseDamage;
    private $radius;
    private $ticksRan = 0;
    private $phase = 0;
    private $startX;
    private $startY;
    private $startZ;
    private $impactX;
    private $impactY;
    private $impactZ;
    private $hitTargets = [];
    private $stunnedTargets = [];
    private $waveCount = 0;
    private $cleaned = false;

    const PHASE_JUMP = 0;
    const PHASE_HANG = 1;
    const PHASE_FALL = 2;
    const PHASE_IMPACT = 3;
    const PHASE_WAVES = 4;
    const PHASE_DONE = 5;

    const JUMP_TICKS = 10;
    const HANG_TICKS = 12;
    const FALL_TICKS = 8;
    const WAVE_INTERVAL = 6;
    const TOTAL_WAVES = 5;
    const JUMP_HEIGHT = 12.0;
    const STUN_DURATION = 80;

    const COL_BROWN_R = 139;
    const COL_BROWN_G = 90;
    const COL_BROWN_B = 43;
    const COL_TAN_R = 210;
    const COL_TAN_G = 180;
    const COL_TAN_B = 140;
    const COL_DUST_R = 180;
    const COL_DUST_G = 150;
    const COL_DUST_B = 100;
    const COL_DARK_R = 80;
    const COL_DARK_G = 50;
    const COL_DARK_B = 30;
    const COL_ORANGE_R = 255;
    const COL_ORANGE_G = 140;
    const COL_ORANGE_B = 50;
    const EV_SPLASH = 2002;
    const COL_SPLASH_BROWN = 9127187;

    public function __construct($plugin, Player $player, $baseDamage, $radius) {
        $this->plugin = $plugin;
        $this->player = $player;
        $this->baseDamage = $baseDamage;
        $this->radius = $radius;
        $this->startX = $player->x;
        $this->startY = $player->y;
        $this->startZ = $player->z;
    }

    public function onRun($currentTick) {
        if ($this->cleaned) return;

        if ($this->player === null || !$this->player->isOnline()) {
            $this->releaseAllStunned();
            $this->cleanup();
            return;
        }

        $this->ticksRan++;

        $this->applyStunToAll();

        switch ($this->phase) {
            case self::PHASE_JUMP:
                $this->doJump();
                break;
            case self::PHASE_HANG:
                $this->doHang();
                break;
            case self::PHASE_FALL:
                $this->doFall();
                break;
            case self::PHASE_IMPACT:
                $this->doImpact();
                break;
            case self::PHASE_WAVES:
                $this->doWaves();
                break;
            case self::PHASE_DONE:
                $this->releaseAllStunned();
                $this->cleanup();
                break;
        }
    }

    private function applyStunToAll() {
        foreach ($this->stunnedTargets as $eid => $data) {
            $entity = $data["entity"];
            if ($entity === null || !$entity->isAlive() || $entity->closed) {
                unset($this->stunnedTargets[$eid]);
                continue;
            }
            $entity->setMotion(new Vector3(0, 0, 0));
            $entity->teleport(new Vector3($data["lockX"], $data["lockY"], $data["lockZ"]));
        }
    }

    private function addStunnedTarget(Entity $entity) {
        $eid = $entity->getId();
        if (isset($this->stunnedTargets[$eid])) return;

        $this->stunnedTargets[$eid] = [
            "entity" => $entity,
            "lockX" => $entity->x,
            "lockY" => $entity->y,
            "lockZ" => $entity->z
        ];

        if ($entity instanceof Player) {
            $slow = Effect::getEffect(Effect::SLOWNESS);
            $slow->setAmplifier(255);
            $slow->setDuration(self::STUN_DURATION);
            $slow->setVisible(false);
            BaseFruit::staticSafeAddEffect($this->player, $entity, $slow);

            $entity->sendTip(TextFormat::DARK_RED . TextFormat::BOLD . "STUNNED!");
        }
    }

    private function releaseAllStunned() {
        foreach ($this->stunnedTargets as $eid => $data) {
            $entity = $data["entity"];
            if ($entity === null || !$entity->isAlive() || $entity->closed) continue;

            if ($entity instanceof Player) {
                $entity->removeEffect(Effect::SLOWNESS);
                $entity->sendTip(TextFormat::GREEN . "Released!");
            }
        }
        $this->stunnedTargets = [];
    }

    private function doJump() {
        $progress = min(1.0, $this->ticksRan / self::JUMP_TICKS);
        $easeOut = 1 - pow(1 - $progress, 3);
        $targetY = $this->startY + self::JUMP_HEIGHT * $easeOut;

        $this->player->teleport(new Vector3($this->startX, $targetY, $this->startZ));
        $this->player->setMotion(new Vector3(0, 0, 0));

        $lv = $this->player->getLevel();
        $px = $this->player->x;
        $py = $this->player->y;
        $pz = $this->player->z;

        if ($this->ticksRan % 2 === 0) {
            for ($i = 0; $i < 4; $i++) {
                $lv->addParticle(new DustParticle(
                    new Vector3(
                        $px + (mt_rand(-8, 8) / 10),
                        $py - 0.5 - (mt_rand(0, 10) / 10),
                        $pz + (mt_rand(-8, 8) / 10)
                    ),
                    self::COL_DUST_R, self::COL_DUST_G, self::COL_DUST_B
                ));
            }
        }

        if ($this->ticksRan >= self::JUMP_TICKS) {
            $this->phase = self::PHASE_HANG;
            $this->ticksRan = 0;
            $this->player->sendTip(TextFormat::GOLD . "...");
        }
    }

    private function doHang() {
        $this->player->teleport(new Vector3($this->startX, $this->startY + self::JUMP_HEIGHT, $this->startZ));
        $this->player->setMotion(new Vector3(0, 0, 0));

        $lv = $this->player->getLevel();
        $px = $this->player->x;
        $py = $this->player->y;
        $pz = $this->player->z;

        if ($this->ticksRan % 3 === 0) {
            $lv->addParticle(new CriticalParticle(new Vector3(
                $px + (mt_rand(-10, 10) / 10),
                $py + (mt_rand(-5, 5) / 10),
                $pz + (mt_rand(-10, 10) / 10)
            )));

            for ($i = 0; $i < 3; $i++) {
                $a = ($i / 3) * M_PI * 2 + $this->ticksRan * 0.3;
                $lv->addParticle(new DustParticle(
                    new Vector3($px + cos($a) * 1.5, $py - 0.5, $pz + sin($a) * 1.5),
                    self::COL_ORANGE_R, self::COL_ORANGE_G, self::COL_ORANGE_B
                ));
            }
        }

        if ($this->ticksRan >= self::HANG_TICKS) {
            $this->phase = self::PHASE_FALL;
            $this->ticksRan = 0;
            $this->player->sendTip(TextFormat::DARK_RED . TextFormat::BOLD . "EXTINCTION!");
        }
    }

    private function doFall() {
        $progress = min(1.0, $this->ticksRan / self::FALL_TICKS);
        $easeIn = pow($progress, 2.5);
        $targetY = $this->startY + self::JUMP_HEIGHT * (1 - $easeIn);

        $this->player->teleport(new Vector3($this->startX, $targetY, $this->startZ));
        $this->player->setMotion(new Vector3(0, 0, 0));

        $lv = $this->player->getLevel();
        $px = $this->player->x;
        $py = $this->player->y;
        $pz = $this->player->z;

        for ($i = 0; $i < 6; $i++) {
            $lv->addParticle(new DustParticle(
                new Vector3(
                    $px + (mt_rand(-10, 10) / 10),
                    $py + 0.5 + (mt_rand(0, 15) / 10),
                    $pz + (mt_rand(-10, 10) / 10)
                ),
                self::COL_ORANGE_R, self::COL_ORANGE_G, self::COL_ORANGE_B
            ));
        }

        for ($i = 0; $i < 4; $i++) {
            $lv->addParticle(new SmokeParticle(new Vector3(
                $px + (mt_rand(-8, 8) / 10),
                $py + 1 + (mt_rand(0, 10) / 10),
                $pz + (mt_rand(-8, 8) / 10)
            )));
        }

        if ($this->ticksRan >= self::FALL_TICKS) {
            $this->impactX = $this->startX;
            $this->impactY = $this->startY;
            $this->impactZ = $this->startZ;
            $this->player->teleport(new Vector3($this->impactX, $this->impactY, $this->impactZ));
            $this->phase = self::PHASE_IMPACT;
            $this->ticksRan = 0;
        }
    }

    private function doImpact() {
        $this->player->setMotion(new Vector3(0, 0, 0));
        $this->spawnImpactVFX();

        $lv = $this->player->getLevel();
        $impactPos = new Vector3($this->impactX, $this->impactY, $this->impactZ);

        foreach ($lv->getEntities() as $entity) {
            if ($entity === $this->player) continue;
            if (!$entity->isAlive()) continue;

            $isValid = false;
            if ($entity instanceof Player) {
                if (!$this->plugin->canTargetPlayer($this->player->getName(), $entity)) continue;
                $isValid = true;
            } elseif ($entity instanceof NPCEntity) {
                $isValid = true;
            } elseif ($entity instanceof FactoryEntity) {
                $isValid = true;
            }

            if (!$isValid) continue;

            $dist = $impactPos->distance($entity->getPosition());
            if ($dist <= $this->radius) {
                $this->hitTargets[$entity->getId()] = ["entity" => $entity, "hits" => 0];
                $this->addStunnedTarget($entity);
            }
        }

        foreach ($this->stunnedTargets as $eid => $data) {
            $entity = $data["entity"];
            if ($entity === null || !$entity->isAlive()) continue;

            $dist = $impactPos->distance($entity->getPosition());
            if ($dist <= 4.0) {
                $this->hitTargets[$eid]["hits"]++;

                $entity->attack($this->baseDamage, new EntityDamageByEntityEvent(
                    $this->player,
                    $entity,
                    EntityDamageEvent::CAUSE_ENTITY_ATTACK,
                    $this->baseDamage
                ));

                if ($entity instanceof Player) {
                    $entity->sendTip(TextFormat::DARK_RED . TextFormat::BOLD . "QUAKE!");
                }
            }
        }

        $debrisTask = new MammothDebrisTask(
            $this->plugin,
            $lv,
            $this->impactX,
            $this->impactY,
            $this->impactZ,
            6.0
        );
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($debrisTask, 1);

        $this->phase = self::PHASE_WAVES;
        $this->ticksRan = 0;
        $this->waveCount = 0;
    }

    private function doWaves() {
        $this->player->teleport(new Vector3($this->impactX, $this->impactY, $this->impactZ));
        $this->player->setMotion(new Vector3(0, 0, 0));

        if ($this->ticksRan % self::WAVE_INTERVAL === 0 && $this->waveCount < self::TOTAL_WAVES) {
            $this->waveCount++;
            $this->spawnWave($this->waveCount);
            $this->damageInWave($this->waveCount);
        }

        if ($this->waveCount >= self::TOTAL_WAVES && $this->ticksRan > self::TOTAL_WAVES * self::WAVE_INTERVAL + 10) {
            $this->spawnFinalVFX();
            $this->phase = self::PHASE_DONE;
        }
    }

    private function spawnWave($waveNum) {
        $lv = $this->player->getLevel();
        $waveRadius = 2.0 + $waveNum * 2.2;
        $pts = 14 + $waveNum * 4;

        for ($i = 0; $i < $pts; $i++) {
            $a = ($i / $pts) * M_PI * 2;
            $x = $this->impactX + cos($a) * $waveRadius;
            $z = $this->impactZ + sin($a) * $waveRadius;

            $lv->addParticle(new DustParticle(
                new Vector3($x, $this->impactY + 0.3, $z),
                self::COL_DUST_R, self::COL_DUST_G, self::COL_DUST_B
            ));

            if ($i % 2 === 0) {
                $lv->addParticle(new DustParticle(
                    new Vector3($x, $this->impactY + 0.8, $z),
                    self::COL_BROWN_R, self::COL_BROWN_G, self::COL_BROWN_B
                ));
            }
        }

        for ($crack = 0; $crack < 8; $crack++) {
            $a = ($crack / 8) * M_PI * 2 + $waveNum * 0.2;
            $innerR = $waveRadius * 0.7;
            $outerR = $waveRadius * 1.1;

            for ($d = 0; $d < 4; $d++) {
                $dist = $innerR + ($outerR - $innerR) * ($d / 3);
                $lv->addParticle(new DustParticle(
                    new Vector3(
                        $this->impactX + cos($a) * $dist + (mt_rand(-3, 3) / 10),
                        $this->impactY + 0.1,
                        $this->impactZ + sin($a) * $dist + (mt_rand(-3, 3) / 10)
                    ),
                    self::COL_DARK_R, self::COL_DARK_G, self::COL_DARK_B
                ));
            }
        }

        for ($i = 0; $i < 6; $i++) {
            $a = mt_rand(0, 628) / 100;
            $d = $waveRadius * (0.8 + mt_rand(0, 40) / 100);
            $lv->addParticle(new SmokeParticle(new Vector3(
                $this->impactX + cos($a) * $d,
                $this->impactY + (mt_rand(3, 12) / 10),
                $this->impactZ + sin($a) * $d
            )));
        }

        for ($i = 0; $i < 5; $i++) {
            $a = mt_rand(0, 628) / 100;
            $d = $waveRadius * (0.6 + mt_rand(0, 50) / 100);
            $lv->addParticle(new CriticalParticle(new Vector3(
                $this->impactX + cos($a) * $d,
                $this->impactY + (mt_rand(5, 18) / 10),
                $this->impactZ + sin($a) * $d
            )));
        }

        if ($waveNum <= 3) {
            $smallDebris = new MammothDebrisTask(
                $this->plugin,
                $lv,
                $this->impactX + cos($waveNum) * $waveRadius * 0.5,
                $this->impactY,
                $this->impactZ + sin($waveNum) * $waveRadius * 0.5,
                2.0
            );
            $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($smallDebris, 1);
        }

        $this->sendSplash($lv, $this->impactX, $this->impactY + 0.5, $this->impactZ);
        $lv->addSound(new AnvilUseSound(new Vector3($this->impactX, $this->impactY, $this->impactZ)));
    }

    private function damageInWave($waveNum) {
        $waveDamage = $this->baseDamage;

        foreach ($this->stunnedTargets as $eid => $data) {
            $entity = $data["entity"];
            if ($entity === null || !$entity->isAlive() || $entity->closed) continue;

            if (isset($this->hitTargets[$eid])) {
                $this->hitTargets[$eid]["hits"]++;
            }

            $entity->attack($waveDamage, new EntityDamageByEntityEvent(
                $this->player,
                $entity,
                EntityDamageEvent::CAUSE_ENTITY_ATTACK,
                $waveDamage
            ));

            $entity->setMotion(new Vector3(0, 0, 0));

            if ($entity instanceof Player) {
                $entity->sendTip(TextFormat::GOLD . "WAVE " . $waveNum . "!");
            }
        }
    }

    private function spawnImpactVFX() {
        $lv = $this->player->getLevel();

        for ($ring = 0; $ring < 5; $ring++) {
            $rr = 0.8 + $ring * 0.8;
            for ($i = 0; $i < 14; $i++) {
                $a = ($i / 14) * M_PI * 2;
                $lv->addParticle(new DustParticle(
                    new Vector3($this->impactX + cos($a) * $rr, $this->impactY + 0.2, $this->impactZ + sin($a) * $rr),
                    self::COL_ORANGE_R, self::COL_ORANGE_G, self::COL_ORANGE_B
                ));
            }
        }

        for ($crack = 0; $crack < 16; $crack++) {
            $a = ($crack / 16) * M_PI * 2;
            for ($d = 0; $d < 8; $d++) {
                $dist = 0.3 + $d * 0.5;
                $lv->addParticle(new DustParticle(
                    new Vector3(
                        $this->impactX + cos($a) * $dist + (mt_rand(-4, 4) / 10),
                        $this->impactY + 0.1,
                        $this->impactZ + sin($a) * $dist + (mt_rand(-4, 4) / 10)
                    ),
                    self::COL_DARK_R, self::COL_DARK_G, self::COL_DARK_B
                ));
            }
        }

        for ($i = 0; $i < 30; $i++) {
            $lv->addParticle(new SmokeParticle(new Vector3(
                $this->impactX + (mt_rand(-35, 35) / 10),
                $this->impactY + (mt_rand(0, 25) / 10),
                $this->impactZ + (mt_rand(-35, 35) / 10)
            )));
        }

        for ($i = 0; $i < 20; $i++) {
            $lv->addParticle(new CriticalParticle(new Vector3(
                $this->impactX + (mt_rand(-30, 30) / 10),
                $this->impactY + (mt_rand(0, 30) / 10),
                $this->impactZ + (mt_rand(-30, 30) / 10)
            )));
        }

        $lv->addParticle(new ExplodeParticle(new Vector3($this->impactX, $this->impactY, $this->impactZ)));
        $lv->addParticle(new ExplodeParticle(new Vector3($this->impactX, $this->impactY + 1, $this->impactZ)));
        $lv->addParticle(new ExplodeParticle(new Vector3($this->impactX, $this->impactY + 2, $this->impactZ)));

        $this->sendSplash($lv, $this->impactX, $this->impactY, $this->impactZ);
        $this->sendSplash($lv, $this->impactX, $this->impactY + 1, $this->impactZ);
        $this->sendSplash($lv, $this->impactX, $this->impactY + 2, $this->impactZ);

        $lv->addSound(new AnvilUseSound(new Vector3($this->impactX, $this->impactY, $this->impactZ)));
        $lv->addSound(new AnvilUseSound(new Vector3($this->impactX, $this->impactY, $this->impactZ)));
        $lv->addSound(new AnvilUseSound(new Vector3($this->impactX, $this->impactY, $this->impactZ)));
    }

    private function spawnFinalVFX() {
        $lv = $this->player->getLevel();

        for ($ring = 0; $ring < 3; $ring++) {
            $rr = $this->radius * (0.6 + $ring * 0.2);
            for ($i = 0; $i < 20; $i++) {
                $a = ($i / 20) * M_PI * 2;
                $lv->addParticle(new DustParticle(
                    new Vector3($this->impactX + cos($a) * $rr, $this->impactY + 0.5 + $ring * 0.3, $this->impactZ + sin($a) * $rr),
                    self::COL_BROWN_R, self::COL_BROWN_G, self::COL_BROWN_B
                ));
            }
        }

        $totalHits = 0;
        foreach ($this->hitTargets as $data) {
            $totalHits += $data["hits"];
        }

        $this->player->sendMessage(TextFormat::GOLD . "Extinction Stomp complete! " . count($this->hitTargets) . " targets hit " . $totalHits . " times!");
    }

    private function sendSplash($lv, $x, $y, $z) {
        $pk = new LevelEventPacket();
        $pk->evid = self::EV_SPLASH;
        $pk->data = self::COL_SPLASH_BROWN;
        $pk->x = (float)$x;
        $pk->y = (float)$y;
        $pk->z = (float)$z;
        foreach ($lv->getPlayers() as $pl) {
            $pl->dataPacket($pk);
        }
    }

    private function cleanup() {
        if ($this->cleaned) return;
        $this->cleaned = true;
        $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
    }
}

class MammothDebrisTask extends Task {

    private $plugin;
    private $level;
    private $cx;
    private $cy;
    private $cz;
    private $radius;
    private $debris = [];
    private $ticksRan = 0;
    private $maxTicks = 45;
    private $cleaned = false;

    public function __construct($plugin, Level $level, $cx, $cy, $cz, $radius) {
        $this->plugin = $plugin;
        $this->level = $level;
        $this->cx = (float)$cx;
        $this->cy = (float)$cy;
        $this->cz = (float)$cz;
        $this->radius = (float)$radius;
        $this->spawnDebris();
    }

    private function spawnDebris() {
        $this->debris = BlockEffects::spawnDebris(
            $this->plugin, $this->level, $this->cx, $this->cy, $this->cz,
            mt_rand(4, 7), 0.4, 1.0, 22
        );

        for ($i = 0; $i < 8; $i++) {
            $da = ($i / 8) * M_PI * 2;
            $dd = 0.4 + mt_rand(0, 15) / 10;
            $this->level->addParticle(new DustParticle(
                new Vector3($this->cx + cos($da) * $dd, $this->cy + 0.2, $this->cz + sin($da) * $dd),
                139, 90, 43
            ));
        }
    }

    public function onRun($currentTick) {
        if ($this->cleaned) return;

        $this->ticksRan++;

        if ($this->ticksRan > $this->maxTicks || empty($this->debris)) {
            $this->forceCleanup();
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
            return;
        }

        $toRemove = BlockEffects::tickDebris($this->debris, $this->level, $this->cy - 1.5, 0.045);
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