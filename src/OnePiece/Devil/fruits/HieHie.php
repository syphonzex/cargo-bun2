<?php
namespace OnePiece\Devil\fruits;

use OnePiece\Devil\BaseFruit;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\math\Vector3;
use pocketmine\entity\Effect;
use pocketmine\entity\Entity;
use pocketmine\utils\TextFormat;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\level\particle\DustParticle;
use pocketmine\level\particle\LargeExplodeParticle;
use pocketmine\level\particle\CriticalParticle;
use pocketmine\level\sound\AnvilUseSound;
use pocketmine\level\sound\FizzSound;
use pocketmine\network\protocol\LevelEventPacket;
use pocketmine\scheduler\Task;
use OnePiece\Devil\BlockEffects;

class HieHie extends BaseFruit {

    public static $frozenPlayers = [];

    const EV_SPLASH     = 2002;
    const COL_ICE_CYAN  = 65535;
    const COL_ICE_WHITE = 16777215;
    const COL_ICE_BLUE  = 3694022;

    public function getId() { return "hie_hie"; }
    public function getDisplayName() { return "Ice-Ice Fruit"; }
    public function getDescription() { return "Legendary Logia with supreme freezing control."; }
    public function getType() { return "logia"; }
    public function getRarity() { return "legendary"; }

    public function getAbilityNames() {
        return [
            "ability1" => "Ice Time",
            "ability2" => "Ice Age"
        ];
    }

    public function getAbilityCooldowns() {
        return [
            "ability1" => 9.0,
            "ability2" => 24.0
        ];
    }

    public function useAbility(Player $player, $ability) {
        switch ($ability) {
            case "ability1":
                if (!$this->checkMastery($player, "ability1")) return 0;
                return $this->iceTime($player);
            case "ability2":
                if (!$this->checkMastery($player, "ability2")) return 0;
                return $this->iceAge($player);
        }
        return 0;
    }

    private function iceTime(Player $player) {
        $range = $this->getMasteryRange($player, 15.0);
        $mult = min(1.5, $this->getCombinedMultiplier($player));
        $damage = 6.0 * $mult;
        $freezeTicks = (int)$this->getMasteryDuration($player, 70);

        $target = $this->findFrontTarget($player, $range);

        if ($target === null) {
            $player->sendTip(TextFormat::AQUA . "Ice Time missed");
            $this->spawnIceLineVFX($player, $range);
            $this->grantMasteryExp($player);
            return 2.5;
        }

        if ($target instanceof Player && !$this->isValidPlayerTarget($player, $target)) {
            $reason = $this->getInvalidTargetReason($player, $target);
            if ($reason !== null) $player->sendTip($reason);
            return 2.5;
        }

        $this->dealAbilityDamage($player, $target, $damage);

        $slow = Effect::getEffect(Effect::SLOWNESS);
        $slow->setAmplifier(4);
        $slow->setDuration($freezeTicks);
        $slow->setVisible(false);
        $this->safeAddEffect($player, $target, $slow);

        $mining = Effect::getEffect(Effect::MINING_FATIGUE);
        $mining->setAmplifier(2);
        $mining->setDuration(max(40, $freezeTicks - 10));
        $mining->setVisible(false);
        $this->safeAddEffect($player, $target, $mining);

        $this->safeSetMotion($player, $target, new Vector3(0, 0, 0));

        if ($target instanceof Player) {
            self::$frozenPlayers[strtolower($target->getName())] = true;
            $this->scheduleUnfreeze($target, $freezeTicks);
            $target->sendTip(TextFormat::AQUA . "Ice Time");
        }

        $player->sendTip(TextFormat::AQUA . "Ice Time");
        $this->spawnIceTimeVFX($player, $target);
        $this->grantMasteryExp($player);
        $this->grantMasteryHitExp($player);

        return $this->getMasteryCooldown($player, $this->getAbilityCooldowns()["ability1"]);
    }

    private function iceAge(Player $player) {
        $radius = $this->getMasteryRange($player, 10.0);
        $mult = min(1.5, $this->getCombinedMultiplier($player));
        $baseDamage = 5.0 * $mult;
        $freezeTicks = (int)$this->getMasteryDuration($player, 85);
        $hits = 0;
        $pos = $player->getPosition();

        foreach ($this->getNearbyTargets($player, $radius) as $t) {
            $dist = $pos->distance($t->getPosition());
            if ($dist > $radius) continue;

            if ($t instanceof Player && !$this->isValidPlayerTarget($player, $t)) {
                continue;
            }

            $scale = 1 - (($dist / $radius) * 0.30);
            $damage = max(3.0, $baseDamage * $scale);

            $this->dealAbilityDamage($player, $t, $damage);

            $slow = Effect::getEffect(Effect::SLOWNESS);
            $slow->setAmplifier(3);
            $slow->setDuration($freezeTicks);
            $slow->setVisible(false);
            $this->safeAddEffect($player, $t, $slow);

            $mining = Effect::getEffect(Effect::MINING_FATIGUE);
            $mining->setAmplifier(2);
            $mining->setDuration(max(50, $freezeTicks - 15));
            $mining->setVisible(false);
            $this->safeAddEffect($player, $t, $mining);

            $this->safeSetMotion($player, $t, new Vector3(0, 0, 0));

            if ($t instanceof Player) {
                self::$frozenPlayers[strtolower($t->getName())] = true;
                $this->scheduleUnfreeze($t, $freezeTicks);
                $t->sendTip(TextFormat::AQUA . "Ice Age");
            }

            $hits++;
        }

        $player->sendTip(TextFormat::AQUA . "Ice Age");
        $this->spawnIceAgeVFX($player, $radius);
        $this->spawnIceAgeDebris($player, 4);
        $this->grantMasteryExp($player);
        if ($hits > 0) $this->grantMasteryHitExp($player);

        return $this->getMasteryCooldown($player, $this->getAbilityCooldowns()["ability2"]);
    }

    private function scheduleUnfreeze($target, $ticks) {
        $ref = $target;
        Server::getInstance()->getScheduler()->scheduleDelayedTask(new class($ref) extends Task {
            private $target;
            public function __construct($target) {
                $this->target = $target;
            }
            public function onRun($currentTick) {
                if ($this->target->closed) return;
                if ($this->target instanceof Player && !$this->target->isOnline()) return;
                unset(HieHie::$frozenPlayers[strtolower($this->target->getName())]);
            }
        }, $ticks);
    }

    public static function spawnSplash($level, $x, $y, $z, $color) {
        $pk = new LevelEventPacket();
        $pk->evid = self::EV_SPLASH;
        $pk->data = $color;
        $pk->x = (float)$x;
        $pk->y = (float)$y;
        $pk->z = (float)$z;

        foreach ($level->getPlayers() as $p) {
            $p->dataPacket($pk);
        }
    }

    private function spawnIceLineVFX(Player $player, $range) {
        $level = $player->getLevel();
        $dir = $player->getDirectionVector();
        $sx = $player->x;
        $sy = $player->y + 1.25;
        $sz = $player->z;

        for ($i = 1; $i <= 8; $i++) {
            $t = $i / 8;
            $x = $sx + $dir->x * $range * $t;
            $z = $sz + $dir->z * $range * $t;

            $level->addParticle(new DustParticle(new Vector3($x, $sy, $z), 170, 230, 255));

            if (($i % 2) === 0) {
                self::spawnSplash($level, $x, $sy, $z, self::COL_ICE_CYAN);
            }
        }

        $level->addSound(new FizzSound(new Vector3($sx, $sy, $sz)));
    }

    private function spawnIceTimeVFX(Player $player, $target) {
        $level = $player->getLevel();
        $px = $player->x;
        $py = $player->y + 1.2;
        $pz = $player->z;
        $tx = $target->x;
        $ty = $target->y + 1.0;
        $tz = $target->z;

        for ($i = 0; $i <= 10; $i++) {
            $p = $i / 10;
            $x = $px + ($tx - $px) * $p;
            $y = $py + ($ty - $py) * $p;
            $z = $pz + ($tz - $pz) * $p;

            $level->addParticle(new DustParticle(new Vector3($x, $y, $z), 175, 235, 255));

            if (($i % 2) === 0) {
                self::spawnSplash($level, $x, $y, $z, self::COL_ICE_CYAN);
            }
        }

        for ($h = 0; $h < 4; $h++) {
            $points = 8 + $h;
            $ry = $ty + ($h * 0.35);

            for ($i = 0; $i < $points; $i++) {
                $a = (M_PI * 2 * $i) / $points;
                $level->addParticle(new DustParticle(
                    new Vector3($tx + cos($a) * 0.7, $ry, $tz + sin($a) * 0.7),
                    185, 240, 255
                ));
            }
        }

        for ($i = 0; $i < 5; $i++) {
            self::spawnSplash(
                $level,
                $tx + (mt_rand(-6, 6) / 10),
                $ty + (mt_rand(0, 12) / 10),
                $tz + (mt_rand(-6, 6) / 10),
                ($i % 2 === 0 ? self::COL_ICE_CYAN : self::COL_ICE_WHITE)
            );
        }

        for ($i = 0; $i < 4; $i++) {
            $level->addParticle(new CriticalParticle(new Vector3(
                $tx + (mt_rand(-5, 5) / 10),
                $ty + (mt_rand(0, 10) / 10),
                $tz + (mt_rand(-5, 5) / 10)
            )));
        }

        $level->addParticle(new LargeExplodeParticle(new Vector3($tx, $ty, $tz)));
        $level->addSound(new FizzSound(new Vector3($tx, $ty, $tz)));
        $level->addSound(new AnvilUseSound(new Vector3($tx, $ty, $tz)));
    }

    private function spawnIceAgeVFX(Player $player, $radius) {
        $level = $player->getLevel();
        $px = $player->x;
        $py = $player->y;
        $pz = $player->z;

        for ($ring = 1; $ring <= 4; $ring++) {
            $rr = $radius * ($ring / 4);
            $points = 10 + ($ring * 5);

            for ($i = 0; $i < $points; $i++) {
                $a = (M_PI * 2 * $i) / $points;
                $x = $px + cos($a) * $rr;
                $z = $pz + sin($a) * $rr;

                $level->addParticle(new DustParticle(new Vector3($x, $py + 0.15, $z), 165, 230, 255));

                if (($i % 3) === 0) {
                    self::spawnSplash($level, $x, $py + 0.1, $z, self::COL_ICE_CYAN);
                }
            }
        }

        for ($i = 0; $i < 12; $i++) {
            $a = mt_rand(0, 628) / 100;
            $d = mt_rand(2, (int)($radius * 10)) / 10;
            $x = $px + cos($a) * $d;
            $z = $pz + sin($a) * $d;

            $level->addParticle(new DustParticle(
                new Vector3($x, $py + mt_rand(3, 14) / 10, $z),
                210, 245, 255
            ));
        }

        $level->addParticle(new LargeExplodeParticle(new Vector3($px, $py + 0.5, $pz)));
        $level->addSound(new AnvilUseSound(new Vector3($px, $py, $pz)));
        $level->addSound(new FizzSound(new Vector3($px, $py, $pz)));
    }

    private function spawnIceAgeDebris(Player $player, $count = 4) {
        $level = $player->getLevel();
        $px = $player->x; $py = $player->y; $pz = $player->z;
        $debris = BlockEffects::spawnDebris(
            $this->plugin, $level, $px, $py, $pz,
            $count, 0.25, 0.5, 10,
            [["id" => 79, "damage" => 0]]
        );
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask(
            new HieHieShardTask($this->plugin, $level, $debris, $py),
            1
        );
    }

    public function onEquip(Player $player) {
        $player->sendMessage(TextFormat::AQUA . "=== Hie-Hie no Mi ===");
        $player->sendMessage(TextFormat::WHITE . "Legendary Logia of freezing control");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::AQUA . "[Tap]: " . TextFormat::WHITE . "Ice Time");
        $player->sendMessage(TextFormat::GRAY . "  Fast freezing beam for single target control");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::AQUA . "[Sneak+Tap]: " . TextFormat::WHITE . "Ice Age");
        $player->sendMessage(TextFormat::GRAY . "  Freezes a wide area around you");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::AQUA . "[Passive]: " . TextFormat::WHITE . "Walk on water with ice");
        $player->sendMessage(TextFormat::AQUA . "=====================");
    }

    public function onUnequip(Player $player) {
        unset(self::$frozenPlayers[strtolower($player->getName())]);
        $player->sendMessage(TextFormat::GRAY . "The cold fades away");
    }
}

class HieHieShardTask extends Task {

    private $plugin;
    private $level;
    private $debris;
    private $groundY;
    private $tick = 0;
    private $maxTicks = 15;
    private $cleaned = false;

    public function __construct($plugin, $level, array $debris, $groundY) {
        $this->plugin  = $plugin;
        $this->level   = $level;
        $this->debris  = $debris;
        $this->groundY = $groundY - 0.5;
    }

public function onRun($currentTick) {
    if ($this->cleaned) return;
    $this->tick++;

    if ($this->tick > $this->maxTicks || empty($this->debris)) {
        $this->cleanup();
        $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
        return;
    }

    foreach ($this->debris as $eid => $d) {
        if ($this->tick % 2 === 0) {
            $this->level->addParticle(new \pocketmine\level\particle\DustParticle(
                new \pocketmine\math\Vector3($d["x"], $d["y"] + 0.1, $d["z"]),
                180, 230, 255
            ));
        }
    }

    $toRemove = BlockEffects::tickDebris($this->debris, $this->level, $this->groundY, 0.05, 0.96);
    foreach ($toRemove as $eid) { unset($this->debris[$eid]); }
}

    private function cleanup() {
        if ($this->cleaned) return;
        $this->cleaned = true;
        BlockEffects::voidAndRemove($this->plugin, $this->level, array_keys($this->debris));
        $this->debris = [];
    }
}
