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
use pocketmine\level\particle\FlameParticle;
use pocketmine\level\particle\PortalParticle;
use pocketmine\level\sound\AnvilUseSound;
use pocketmine\level\sound\PopSound;
use pocketmine\level\sound\ClickSound;
use pocketmine\level\sound\FizzSound;
use pocketmine\level\sound\EndermanTeleportSound;
use OnePiece\Devil\BlockEffects;
use pocketmine\network\protocol\LevelEventPacket;
use OnePiece\NPC\NPCEntity;
use OnePieceTrades\Factory\FactoryEntity;

class PikaPika extends BaseFruit {

    private $lightZones = [];
    private $zoneTaskIds = [];

    const COL_GOLD_R = 255;
    const COL_GOLD_G = 220;
    const COL_GOLD_B = 50;
    const COL_WHITE_R = 255;
    const COL_WHITE_G = 255;
    const COL_WHITE_B = 200;
    const COL_YELLOW_R = 255;
    const COL_YELLOW_G = 255;
    const COL_YELLOW_B = 100;
    const VIEW_RANGE = 50;
    const EV_SPLASH = 2002;
    const COL_SPLASH_GOLD = 16776960;

    public function getId() { return "pika_pika"; }
    public function getDisplayName() { return "Light-Light Fruit"; }
    public function getDescription() { return "Light Fruit - Kizaru's blinding speed, laser devastation."; }
    public function getType() { return "logia"; }
    public function getRarity() { return "legendary"; }

    public function getAbilityNames() {
        return [
            "ability1" => "Yasakani / Light Ray",
            "ability2" => "Sacred Yata Mirror"
        ];
    }

    public function getAbilityCooldowns() {
        return [
            "ability1" => 10.0,
            "ability2" => 22.0
        ];
    }

    public function useAbility(Player $player, $ability) {
        switch ($ability) {
            case "ability1":
                return $this->handleTap($player);
            case "ability2":
                return $this->handleSneakTap($player);
        }
        return 0;
    }

    private function handleTap(Player $player) {
        if ($this->hasActiveZone($player)) {
            return $this->lightRay($player);
        }
        return $this->yasakani($player);
    }

    private function handleSneakTap(Player $player) {
        return $this->sacredYata($player);
    }

private function yasakani(Player $player) {
    $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
    $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

    $name = $player->getName();
    $mult = min(1.5, $this->getHakiMultiplier($player));

    $baseRadius = 14.0;
    $bonusRadius = 5.0 * ($mult - 1.0);
    $radius = $baseRadius + $bonusRadius;

    $duration = 3;
    $baseDamage = min(8.5, 3.0 * $mult);

    $this->destroyZone($player, false);

    $pos = $player->getPosition();
    $durationTicks = $duration * 20;

    $this->lightZones[$name] = [
        "x" => $pos->x,
        "y" => $pos->y,
        "z" => $pos->z,
        "radius" => $radius,
        "level" => $player->getLevel()->getName(),
        "endTime" => microtime(true) + $duration,
        "owner" => $name,
        "damage" => $baseDamage
    ];

    $this->spawnYasakaniInitial($player, $radius);

    $barrageTask = new LightBarrageTask(
        $this->plugin,
        $player->getLevel(),
        $pos->x, $pos->y, $pos->z,
        $radius,
        $name,
        $durationTicks,
        $baseDamage
    );
    $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($barrageTask, 1);
    $this->zoneTaskIds[$name] = $barrageTask->getTaskId();

    $debrisTask = new LightDebrisTask(
        $this->plugin,
        $player->getLevel(),
        $pos->x,
        $pos->y,
        $pos->z,
        $radius
    );
    $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($debrisTask, 1);

    $dir = $player->getDirectionVector();
    $hits = 0;

    foreach ($this->getNearbyTargets($player, $radius) as $t) {
        $tp = $t->getPosition();
        $dist = $pos->distance($tp);
        if ($dist <= 0) continue;

        if ($t instanceof Player) {
            if (!$this->plugin->canTargetPlayer($player->getName(), $t)) {
                $reason = $this->plugin->getTargetBlockReason($player->getName(), $t);
                if ($reason !== null) $player->sendTip($reason);
                continue;
            }
        }

        $to = $tp->subtract($pos);
        $norm = new Vector3($to->x / $dist, 0, $to->z / $dist);
        $dot = $dir->x * $norm->x + $dir->z * $norm->z;

        if ($dot > 0.2) {
            $this->dealAbilityDamage($player, $t, $damage);

            $blind = Effect::getEffect(Effect::BLINDNESS);
            $blind->setAmplifier(2);
            $blind->setDuration(60);
            $blind->setVisible(false);
            $this->safeAddEffect($player, $t, $blind);

            if ($t instanceof Player) {
                $t->sendTip(TextFormat::YELLOW . "LASER BARRAGE!");
            }
            $hits++;

            $this->spawnLaserStrike($player->getLevel(), $tp->x, $tp->y, $tp->z);
        }
    }

    $player->sendTip(TextFormat::YELLOW . TextFormat::BOLD . "YASAKANI NO MAGATAMA!");
    $player->sendMessage(TextFormat::YELLOW . "Light barrage! Hit " . $hits . " targets!");
    $player->sendMessage(TextFormat::GRAY . "[Tap] Light Ray (3s) - [Sneak+Tap] Sacred Yata Mirror");

    $this->plugin->getServer()->getScheduler()->scheduleDelayedTask(
        new LightZoneExpireTask($this, $name, $this->lightZones[$name]["endTime"]),
        $durationTicks
    );

    return $this->getAbilityCooldowns()["ability1"];
}

    private function lightRay(Player $player) {
        $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
        $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

        $target = $this->findTargetInZone($player);

        if ($target === null) {
            $player->sendTip(TextFormat::YELLOW . "LIGHT RAY... no target in zone.");
            return 3.0;
        }

        if ($target instanceof Player) {
            if (!$this->plugin->canTargetPlayer($player->getName(), $target)) {
                $reason = $this->plugin->getTargetBlockReason($player->getName(), $target);
                if ($reason !== null) $player->sendTip($reason);
                return 3.0;
            }
        }

        $mult = min(1.5, $this->getHakiMultiplier($player));
        $damage = min(9.0, 3.5 * $mult);

        $pPos = $player->getPosition();
        $tPos = $target->getPosition();

        $this->dealAbilityDamage($player, $target, $damage);

        $blind = Effect::getEffect(Effect::BLINDNESS);
        $blind->setAmplifier(2);
        $blind->setDuration(50);
        $blind->setVisible(false);
        $this->safeAddEffect($player, $target, $blind);

        $dx = $tPos->x - $pPos->x;
        $dz = $tPos->z - $pPos->z;
        $len = sqrt($dx * $dx + $dz * $dz);
        if ($len > 0) {
            $this->safeSetMotion($player, $target, new Vector3($dx / $len * 1.2, 0.4, $dz / $len * 1.2));
        }

        $this->spawnLightRayVFX($player, $target);

        $targetName = ($target instanceof Player) ? $target->getName() : "target";
        $player->sendTip(TextFormat::YELLOW . TextFormat::BOLD . "LIGHT RAY!");
        $player->sendMessage(TextFormat::YELLOW . "Laser pierced " . $targetName . "!");

        if ($target instanceof Player) {
            $target->sendTip(TextFormat::YELLOW . TextFormat::BOLD . "LIGHT RAY!");
        }

        return 4.0;
    }

    private function sacredYata(Player $player) {
        $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
        $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

        $mult = min(1.5, $this->getHakiMultiplier($player));
        $damagePerBounce = min(6.0, 2.5 * $mult);
        $finalDamage = min(10.0, 4.0 * $mult);
        $maxBounces = 6;
        $bounceRange = 12.0;

        $targets = [];
        foreach ($this->getNearbyTargets($player, 20.0) as $t) {
            if ($t instanceof Player) {
                if (!$this->plugin->canTargetPlayer($player->getName(), $t)) {
                $reason = $this->plugin->getTargetBlockReason($player->getName(), $t);
                if ($reason !== null) $player->sendTip($reason);
                    continue;
                }
            }
            $targets[] = $t;
        }

        if (empty($targets)) {
            $player->sendTip(TextFormat::YELLOW . "SACRED YATA MIRROR... no targets!");
            return 3.0;
        }

        $hitTargets = [];
        $bounceChain = [];

        $currentPos = $player->getPosition();
        $currentTarget = $this->findNearestFromList($currentPos, $targets, $hitTargets);

        if ($currentTarget === null) {
            $player->sendTip(TextFormat::YELLOW . "SACRED YATA MIRROR... no valid target!");
            return 3.0;
        }

        $bounceChain[] = ["from" => $currentPos, "to" => $currentTarget->getPosition(), "target" => $currentTarget];
        $hitTargets[$currentTarget->getId()] = true;

        for ($i = 1; $i < $maxBounces; $i++) {
            $lastTarget = $bounceChain[count($bounceChain) - 1]["target"];
            $lastPos = $lastTarget->getPosition();

            $nextTarget = null;
            $nearestDist = $bounceRange + 1;

            foreach ($targets as $t) {
                if (isset($hitTargets[$t->getId()])) continue;

                $dist = $lastPos->distance($t->getPosition());
                if ($dist < $nearestDist && $dist <= $bounceRange && $dist > 0.5) {
                    $nearestDist = $dist;
                    $nextTarget = $t;
                }
            }

            if ($nextTarget === null) break;

            $bounceChain[] = ["from" => $lastPos, "to" => $nextTarget->getPosition(), "target" => $nextTarget];
            $hitTargets[$nextTarget->getId()] = true;
        }

        $mirrorTask = new MirrorBounceTask(
            $this->plugin,
            $player,
            $bounceChain,
            $damagePerBounce,
            $finalDamage
        );
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($mirrorTask, 2);

        $player->sendTip(TextFormat::YELLOW . TextFormat::BOLD . "SACRED YATA MIRROR!");
        $player->sendMessage(TextFormat::YELLOW . "Light bouncing through " . count($bounceChain) . " targets!");

        return $this->getAbilityCooldowns()["ability2"];
    }

    private function findNearestFromList($pos, $targets, $exclude) {
        $nearest = null;
        $nearestDist = PHP_INT_MAX;

        foreach ($targets as $t) {
            if (isset($exclude[$t->getId()])) continue;

            $dist = $pos->distance($t->getPosition());
            if ($dist < $nearestDist && $dist > 0.5) {
                $nearestDist = $dist;
                $nearest = $t;
            }
        }

        return $nearest;
    }

    private function spawnYasakaniInitial(Player $player, $radius) {
        $lv = $player->getLevel();
        $pos = $player->getPosition();
        $cx = $pos->x;
        $cy = $pos->y + 1;
        $cz = $pos->z;

        for ($wave = 0; $wave < 4; $wave++) {
            $waveR = $radius * (0.25 + $wave * 0.25);
            $pts = 12 + $wave * 4;
            for ($i = 0; $i < $pts; $i++) {
                $a = ($i / $pts) * M_PI * 2;
                $lv->addParticle(new DustParticle(
                    new Vector3($cx + cos($a) * $waveR, $cy, $cz + sin($a) * $waveR),
                    self::COL_GOLD_R, self::COL_GOLD_G, self::COL_GOLD_B
                ));
            }
        }

        for ($i = 0; $i < 8; $i++) {
            $a = ($i / 8) * M_PI * 2;
            $d = $radius * 0.6;
            $sx = $cx + cos($a) * $d;
            $sz = $cz + sin($a) * $d;

            for ($h = 0; $h < 8; $h++) {
                $lv->addParticle(new DustParticle(
                    new Vector3($sx + (mt_rand(-3, 3) / 10), $cy + 8 - $h, $sz + (mt_rand(-3, 3) / 10)),
                    self::COL_YELLOW_R, self::COL_YELLOW_G, self::COL_YELLOW_B
                ));
            }
            $lv->addParticle(new ExplodeParticle(new Vector3($sx, $cy, $sz)));
        }

        for ($i = 0; $i < 20; $i++) {
            $lv->addParticle(new InstantEnchantParticle(new Vector3(
                $cx + (mt_rand(-50, 50) / 10),
                $cy + (mt_rand(0, 40) / 10),
                $cz + (mt_rand(-50, 50) / 10)
            )));
        }

        $this->sendSplash($lv, $cx, $cy, $cz, self::COL_SPLASH_GOLD);
        $this->sendSplash($lv, $cx, $cy + 5, $cz, self::COL_SPLASH_GOLD);
        $lv->addSound(new AnvilUseSound(new Vector3($cx, $cy, $cz)));
        $lv->addSound(new EndermanTeleportSound(new Vector3($cx, $cy, $cz)));
    }

    private function spawnLaserStrike($lv, $x, $y, $z) {
        for ($h = 0; $h < 10; $h++) {
            $lv->addParticle(new DustParticle(
                new Vector3($x + (mt_rand(-4, 4) / 10), $y + 10 - $h, $z + (mt_rand(-4, 4) / 10)),
                self::COL_YELLOW_R, self::COL_YELLOW_G, self::COL_YELLOW_B
            ));
        }

        for ($i = 0; $i < 8; $i++) {
            $a = ($i / 8) * M_PI * 2;
            $lv->addParticle(new DustParticle(
                new Vector3($x + cos($a) * 1.2, $y + 0.5, $z + sin($a) * 1.2),
                self::COL_GOLD_R, self::COL_GOLD_G, self::COL_GOLD_B
            ));
        }

        for ($i = 0; $i < 6; $i++) {
            $lv->addParticle(new InstantEnchantParticle(new Vector3(
                $x + (mt_rand(-10, 10) / 10),
                $y + (mt_rand(0, 15) / 10),
                $z + (mt_rand(-10, 10) / 10)
            )));
        }

        $lv->addParticle(new ExplodeParticle(new Vector3($x, $y + 0.5, $z)));
        $lv->addSound(new ClickSound(new Vector3($x, $y, $z)));
    }

    private function spawnLightRayVFX(Player $player, Entity $target) {
        $lv = $player->getLevel();
        $pPos = $player->getPosition();
        $tPos = $target->getPosition();

        $px = $pPos->x;
        $py = $pPos->y + 1.5;
        $pz = $pPos->z;
        $tx = $tPos->x;
        $ty = $tPos->y + 1;
        $tz = $tPos->z;

        $dir = $player->getDirectionVector();
        $hx = $px + $dir->x * 0.8;
        $hy = $py;
        $hz = $pz + $dir->z * 0.8;

        for ($i = 0; $i < 8; $i++) {
            $a = ($i / 8) * M_PI * 2;
            $lv->addParticle(new MobSpellParticle(
                new Vector3($hx + cos($a) * 0.3, $hy + sin($a) * 0.3, $hz),
                self::COL_GOLD_R, self::COL_GOLD_G, self::COL_GOLD_B
            ));
        }

        for ($layer = 0; $layer < 3; $layer++) {
            $offset = ($layer - 1) * 0.12;
            for ($i = 0; $i <= 25; $i++) {
                $prog = $i / 25;
                $x = $hx + ($tx - $hx) * $prog;
                $y = $hy + ($ty - $hy) * $prog + $offset;
                $z = $hz + ($tz - $hz) * $prog;

                $lv->addParticle(new DustParticle(
                    new Vector3($x, $y, $z),
                    self::COL_YELLOW_R, self::COL_YELLOW_G, self::COL_YELLOW_B
                ));
            }
        }

        for ($i = 0; $i < 12; $i++) {
            $prog = $i / 11;
            $spiralA = $prog * M_PI * 8;
            $spiralR = 0.35 * (1 - $prog * 0.6);
            $x = $hx + ($tx - $hx) * $prog;
            $y = $hy + ($ty - $hy) * $prog;
            $z = $hz + ($tz - $hz) * $prog;

            $lv->addParticle(new InstantEnchantParticle(new Vector3(
                $x + cos($spiralA) * $spiralR,
                $y + sin($spiralA) * $spiralR,
                $z
            )));
        }

        for ($ring = 0; $ring < 3; $ring++) {
            $rr = 1.5 - $ring * 0.4;
            for ($i = 0; $i < 12; $i++) {
                $a = ($i / 12) * M_PI * 2;
                $lv->addParticle(new DustParticle(
                    new Vector3($tx + cos($a) * $rr, $ty + $ring * 0.25, $tz + sin($a) * $rr),
                    self::COL_GOLD_R, self::COL_GOLD_G, self::COL_GOLD_B
                ));
            }
        }

        for ($i = 0; $i < 12; $i++) {
            $lv->addParticle(new InstantEnchantParticle(new Vector3(
                $tx + (mt_rand(-15, 15) / 10),
                $ty + (mt_rand(0, 20) / 10),
                $tz + (mt_rand(-15, 15) / 10)
            )));
        }

        $lv->addParticle(new ExplodeParticle(new Vector3($tx, $ty, $tz)));
        $this->sendSplash($lv, $tx, $ty, $tz, self::COL_SPLASH_GOLD);
        $lv->addSound(new ClickSound(new Vector3($hx, $hy, $hz)));
        $lv->addSound(new FizzSound(new Vector3($tx, $ty, $tz)));
        $lv->addSound(new PopSound(new Vector3($tx, $ty, $tz)));
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

    public function hasActiveZone(Player $player) {
        $name = $player->getName();

        if (!isset($this->lightZones[$name])) {
            return false;
        }

        if (microtime(true) >= $this->lightZones[$name]["endTime"]) {
            $this->expireZone($name);
            return false;
        }

        return true;
    }

    public function getZoneData(Player $player) {
        $name = $player->getName();
        return isset($this->lightZones[$name]) ? $this->lightZones[$name] : null;
    }

    public function isInsideZone(Player $owner, $target) {
        $zone = $this->getZoneData($owner);
        if ($zone === null) return false;

        if ($target instanceof Entity) {
            $targetPos = $target->getPosition();
            $targetLevel = $target->getLevel()->getName();
        } elseif ($target instanceof Vector3) {
            $targetPos = $target;
            $targetLevel = $owner->getLevel()->getName();
        } else {
            return false;
        }

        if ($targetLevel !== $zone["level"]) return false;

        $dx = $targetPos->x - $zone["x"];
        $dy = $targetPos->y - $zone["y"];
        $dz = $targetPos->z - $zone["z"];
        $distance = sqrt($dx * $dx + $dy * $dy + $dz * $dz);

        return $distance <= $zone["radius"];
    }

    public function getEntitiesInZone(Player $owner) {
        $zone = $this->getZoneData($owner);
        if ($zone === null) return [];

        $level = $this->plugin->getServer()->getLevelByName($zone["level"]);
        if ($level === null) return [];

        $entities = [];
        $zoneCenter = new Vector3($zone["x"], $zone["y"], $zone["z"]);

        foreach ($level->getEntities() as $entity) {
            if ($entity instanceof Player && $entity->getName() === $owner->getName()) continue;
            if (!$entity->isAlive()) continue;
            if (!($entity instanceof Player) && !($entity instanceof NPCEntity) && !($entity instanceof FactoryEntity)) continue;

            $distance = $zoneCenter->distance($entity->getPosition());
            if ($distance <= $zone["radius"]) {
                $entities[] = $entity;
            }
        }

        return $entities;
    }

    private function findTargetInZone(Player $player) {
        $entities = $this->getEntitiesInZone($player);
        if (empty($entities)) return null;

        $dir = $player->getDirectionVector();
        $start = $player->add(0, $player->getEyeHeight(), 0);

        $best = null;
        $bestScore = -1;

        foreach ($entities as $entity) {
            $tp = $entity->add(0, 1, 0);
            $dist = $start->distance($tp);
            if ($dist <= 0.5) continue;

            $to = $tp->subtract($start);
            $norm = new Vector3($to->x / $dist, $to->y / $dist, $to->z / $dist);
            $dot = $dir->x * $norm->x + $dir->y * $norm->y + $dir->z * $norm->z;

            $score = $dot / ($dist * 0.1 + 1);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $entity;
            }
        }

        if ($best === null && !empty($entities)) {
            $nearest = null;
            $nearestDist = PHP_INT_MAX;
            foreach ($entities as $entity) {
                $dist = $start->distance($entity->getPosition());
                if ($dist < $nearestDist && $dist > 0.5) {
                    $nearestDist = $dist;
                    $nearest = $entity;
                }
            }
            $best = $nearest;
        }

        return $best;
    }

    private function destroyZone(Player $player, $notify = true) {
        $name = $player->getName();

        if (isset($this->zoneTaskIds[$name])) {
            try {
                $this->plugin->getServer()->getScheduler()->cancelTask($this->zoneTaskIds[$name]);
            } catch (\Exception $e) {}
            unset($this->zoneTaskIds[$name]);
        }

        if (!isset($this->lightZones[$name])) return false;

        unset($this->lightZones[$name]);

        if ($notify && $player->isOnline()) {
            $player->sendMessage(TextFormat::GRAY . "Light barrage faded.");
        }

        return true;
    }

    public function expireZone($playerName, $endTime = null) {
        if (isset($this->lightZones[$playerName])) {
            if ($endTime !== null && $this->lightZones[$playerName]["endTime"] !== $endTime) {
                return;
            }
        } else {
            return;
        }

        if (isset($this->zoneTaskIds[$playerName])) {
            try {
                $this->plugin->getServer()->getScheduler()->cancelTask($this->zoneTaskIds[$playerName]);
            } catch (\Exception $e) {}
            unset($this->zoneTaskIds[$playerName]);
        }

        unset($this->lightZones[$playerName]);

        $player = $this->plugin->getServer()->getPlayerExact($playerName);
        if ($player !== null && $player->isOnline()) {
            $player->sendTip(TextFormat::GRAY . "Light barrage ended.");
            $player->sendMessage(TextFormat::GRAY . "Your light barrage zone has expired.");
        }
    }

    public function onEquip(Player $player) {
        $player->sendMessage(TextFormat::YELLOW . "=== Pika-Pika no Mi ===");
        $player->sendMessage(TextFormat::WHITE . "Light-Light Fruit - Kizaru's Speed of Light");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::YELLOW . "[Tap] No Zone: " . TextFormat::WHITE . "YASAKANI NO MAGATAMA");
        $player->sendMessage(TextFormat::GRAY . "  Rain of lasers from the sky");
        $player->sendMessage(TextFormat::YELLOW . "[Tap] In Zone: " . TextFormat::WHITE . "LIGHT RAY");
        $player->sendMessage(TextFormat::GRAY . "  Focused laser beam pierces target");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::YELLOW . "[Sneak+Tap]: " . TextFormat::WHITE . "SACRED YATA MIRROR");
        $player->sendMessage(TextFormat::GRAY . "  Light bounces between enemies");
        $player->sendMessage(TextFormat::YELLOW . "========================");
    }

    public function onUnequip(Player $player) {
        $this->destroyZone($player, false);
        $player->sendMessage(TextFormat::GRAY . "The light dims into darkness...");
    }
}

class LightZoneExpireTask extends Task {

    private $fruit;
    private $playerName;
    private $endTime;

    public function __construct(PikaPika $fruit, $playerName, $endTime) {
        $this->fruit = $fruit;
        $this->playerName = $playerName;
        $this->endTime = $endTime;
    }

    public function onRun($currentTick) {
        $this->fruit->expireZone($this->playerName, $this->endTime);
    }
}

class LightBarrageTask extends Task {

    private $plugin;
    private $level;
    private $cx;
    private $cy;
    private $cz;
    private $radius;
    private $ownerName;
    private $totalTicks;
    private $ticksRan = 0;
    private $damage;
    private $phase = 0.0;

    const COL_GOLD_R = 255;
    const COL_GOLD_G = 220;
    const COL_GOLD_B = 50;
    const COL_YELLOW_R = 255;
    const COL_YELLOW_G = 255;
    const COL_YELLOW_B = 100;
    const VIEW_RANGE = 50;
const STRIKE_INTERVAL = 12;
    const EV_SPLASH = 2002;
    const COL_SPLASH_GOLD = 16776960;

    public function __construct($plugin, Level $level, $cx, $cy, $cz, $radius, $ownerName, $totalTicks, $damage) {
        $this->plugin = $plugin;
        $this->level = $level;
        $this->cx = (float)$cx;
        $this->cy = (float)$cy;
        $this->cz = (float)$cz;
        $this->radius = (float)$radius;
        $this->ownerName = $ownerName;
        $this->totalTicks = (int)$totalTicks;
        $this->damage = $damage;
    }

    public function onRun($currentTick) {
        $this->ticksRan++;

        if ($this->ticksRan > $this->totalTicks) {
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
            return;
        }

        if ($this->plugin->getServer()->getLevelByName($this->level->getName()) === null) {
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
            return;
        }

        $this->phase += 0.15;

        if ($this->ticksRan % 4 === 0) {
            $this->spawnAmbientVFX();
        }

        if ($this->ticksRan % self::STRIKE_INTERVAL === 0) {
            $this->strikeRandomTarget();
        }

        if ($this->ticksRan % 8 === 0) {
            $this->spawnRandomLaserBeam();
        }

        if ($this->ticksRan % 20 === 0) {
            $this->spawnFlashPulse();
        }
    }

    private function spawnAmbientVFX() {
        $a = mt_rand(0, 628) / 100;
        $d = mt_rand(10, (int)($this->radius * 10)) / 10;
        $x = $this->cx + cos($a) * $d;
        $z = $this->cz + sin($a) * $d;

        $this->level->addParticle(new InstantEnchantParticle(new Vector3(
            $x,
            $this->cy + (mt_rand(0, 30) / 10),
            $z
        )));

        if (mt_rand(0, 2) === 0) {
            $this->level->addParticle(new EnchantParticle(new Vector3(
                $this->cx + (mt_rand(-30, 30) / 10),
                $this->cy + (mt_rand(10, 50) / 10),
                $this->cz + (mt_rand(-30, 30) / 10)
            )));
        }
    }

    private function spawnRandomLaserBeam() {
        $a = mt_rand(0, 628) / 100;
        $d = mt_rand(20, (int)($this->radius * 9)) / 10;
        $x = $this->cx + cos($a) * $d;
        $z = $this->cz + sin($a) * $d;

        for ($h = 0; $h < 12; $h++) {
            $this->level->addParticle(new DustParticle(
                new Vector3($x + (mt_rand(-3, 3) / 10), $this->cy + 12 - $h, $z + (mt_rand(-3, 3) / 10)),
                self::COL_YELLOW_R, self::COL_YELLOW_G, self::COL_YELLOW_B
            ));
        }

        for ($i = 0; $i < 6; $i++) {
            $ba = ($i / 6) * M_PI * 2;
            $this->level->addParticle(new DustParticle(
                new Vector3($x + cos($ba) * 0.8, $this->cy + 0.3, $z + sin($ba) * 0.8),
                self::COL_GOLD_R, self::COL_GOLD_G, self::COL_GOLD_B
            ));
        }

        $this->level->addParticle(new InstantEnchantParticle(new Vector3($x, $this->cy + 0.5, $z)));
        $this->level->addSound(new ClickSound(new Vector3($x, $this->cy, $z)));
    }

    private function spawnFlashPulse() {
        $pulseR = $this->radius * (0.5 + sin($this->phase) * 0.3);
        $pts = 16;

        for ($i = 0; $i < $pts; $i++) {
            $a = ($i / $pts) * M_PI * 2;
            $this->level->addParticle(new MobSpellParticle(
                new Vector3($this->cx + cos($a) * $pulseR, $this->cy + 0.5, $this->cz + sin($a) * $pulseR),
                self::COL_GOLD_R, self::COL_GOLD_G, self::COL_GOLD_B
            ));
        }

        $this->sendSplash($this->cx, $this->cy + 1, $this->cz, self::COL_SPLASH_GOLD);
    }

private function strikeRandomTarget() {
    $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
    $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

    $targets = [];
    $zoneCenter = new Vector3($this->cx, $this->cy, $this->cz);

    foreach ($this->level->getEntities() as $entity) {
        if (!$entity->isAlive()) continue;

        if ($entity instanceof Player) {
            if ($entity->getName() === $this->ownerName) continue;
            if (!$this->plugin->canTargetPlayer($this->ownerName, $entity)) continue;
        } elseif (!($entity instanceof NPCEntity) && !($entity instanceof FactoryEntity)) {
            continue;
        }

        $distance = $zoneCenter->distance($entity->getPosition());
        if ($distance <= $this->radius) {
            $targets[] = $entity;
        }
    }

    if (empty($targets)) return;

    $target = $targets[mt_rand(0, count($targets) - 1)];
    $tPos = $target->getPosition();

    $owner = $this->plugin->getServer()->getPlayerExact($this->ownerName);
    if ($owner !== null) {
        $ev = new EntityDamageByEntityEvent($owner, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->damage * 0.6);
        $target->attack($this->damage * 0.6, $ev);
    } else {
        $target->attack($this->damage * 0.6, new EntityDamageEvent(
            $target,
            EntityDamageEvent::CAUSE_MAGIC,
            $this->damage * 0.6
        ));
    }

    $blind = Effect::getEffect(Effect::BLINDNESS);
    $blind->setAmplifier(1);
    $blind->setDuration(30);
    $blind->setVisible(false);
    BaseFruit::staticSafeAddEffect($owner, $target, $blind);

    if ($target instanceof Player) {
        $target->sendTip(TextFormat::YELLOW . "LASER STRIKE!");
    }

    $this->spawnStrikeVFX($tPos->x, $tPos->y, $tPos->z);
}

    private function spawnStrikeVFX($x, $y, $z) {
        for ($h = 0; $h < 15; $h++) {
            $this->level->addParticle(new DustParticle(
                new Vector3($x + (mt_rand(-5, 5) / 10), $y + 15 - $h, $z + (mt_rand(-5, 5) / 10)),
                self::COL_YELLOW_R, self::COL_YELLOW_G, self::COL_YELLOW_B
            ));
        }

        for ($ring = 0; $ring < 3; $ring++) {
            $rr = 1.0 + $ring * 0.5;
            for ($i = 0; $i < 10; $i++) {
                $a = ($i / 10) * M_PI * 2;
                $this->level->addParticle(new DustParticle(
                    new Vector3($x + cos($a) * $rr, $y + 0.3 + $ring * 0.2, $z + sin($a) * $rr),
                    self::COL_GOLD_R, self::COL_GOLD_G, self::COL_GOLD_B
                ));
            }
        }

        for ($i = 0; $i < 10; $i++) {
            $this->level->addParticle(new InstantEnchantParticle(new Vector3(
                $x + (mt_rand(-15, 15) / 10),
                $y + (mt_rand(0, 20) / 10),
                $z + (mt_rand(-15, 15) / 10)
            )));
        }

        for ($i = 0; $i < 6; $i++) {
            $this->level->addParticle(new CriticalParticle(new Vector3(
                $x + (mt_rand(-10, 10) / 10),
                $y + (mt_rand(0, 15) / 10),
                $z + (mt_rand(-10, 10) / 10)
            )));
        }

        $this->level->addParticle(new ExplodeParticle(new Vector3($x, $y + 0.5, $z)));
        $this->level->addParticle(new ExplodeParticle(new Vector3($x, $y + 1.5, $z)));
        $this->sendSplash($x, $y + 0.5, $z, self::COL_SPLASH_GOLD);
        $this->level->addSound(new PopSound(new Vector3($x, $y, $z)));
        $this->level->addSound(new ClickSound(new Vector3($x, $y, $z)));
        $this->level->addSound(new FizzSound(new Vector3($x, $y, $z)));
    }

    private function sendSplash($x, $y, $z, $col) {
        $pk = new LevelEventPacket();
        $pk->evid = self::EV_SPLASH;
        $pk->data = $col;
        $pk->x = (float)$x;
        $pk->y = (float)$y;
        $pk->z = (float)$z;
        foreach ($this->level->getPlayers() as $pl) {
            $pl->dataPacket($pk);
        }
    }
}

class MirrorBounceTask extends Task {

    private $plugin;
    private $owner;
    private $bounceChain;
    private $damagePerBounce;
    private $finalDamage;
    private $currentBounce = 0;
    private $ticksRan = 0;
    private $bounceDelay = 6;

    const COL_GOLD_R = 255;
    const COL_GOLD_G = 220;
    const COL_GOLD_B = 50;
    const COL_YELLOW_R = 255;
    const COL_YELLOW_G = 255;
    const COL_YELLOW_B = 100;
    const COL_WHITE_R = 255;
    const COL_WHITE_G = 255;
    const COL_WHITE_B = 220;
    const EV_SPLASH = 2002;
    const COL_SPLASH_GOLD = 16776960;

    public function __construct($plugin, Player $owner, $bounceChain, $damagePerBounce, $finalDamage) {
        $this->plugin = $plugin;
        $this->owner = $owner;
        $this->bounceChain = $bounceChain;
        $this->damagePerBounce = $damagePerBounce;
        $this->finalDamage = $finalDamage;
    }

public function onRun($currentTick) {
    $this->ticksRan++;

    if ($this->currentBounce >= count($this->bounceChain)) {
        $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
        return;
    }

    if ($this->ticksRan % $this->bounceDelay !== 0) {
        return;
    }

    $bounce = $this->bounceChain[$this->currentBounce];
    $from = $bounce["from"];
    $to = $bounce["to"];
    $target = $bounce["target"];

    $isFinal = ($this->currentBounce === count($this->bounceChain) - 1);

    if ($target !== null && $target->isAlive() && !$target->closed) {
        $damage = $isFinal ? $this->finalDamage : $this->damagePerBounce;

        if ($this->owner !== null && $this->owner->isOnline()) {
            $ev = new EntityDamageByEntityEvent($this->owner, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage);
            $target->attack($damage, $ev);
        } else {
            $target->attack($damage, new EntityDamageEvent(
                $target,
                EntityDamageEvent::CAUSE_MAGIC,
                $damage
            ));
        }

        $blind = Effect::getEffect(Effect::BLINDNESS);
        $blind->setAmplifier($isFinal ? 2 : 1);
        $blind->setDuration($isFinal ? 80 : 40);
        $blind->setVisible(false);
        BaseFruit::staticSafeAddEffect($this->owner, $target, $blind);

        if ($isFinal) {
            $slow = Effect::getEffect(Effect::SLOWNESS);
            $slow->setAmplifier(3);
            $slow->setDuration(60);
            $slow->setVisible(false);
            BaseFruit::staticSafeAddEffect($this->owner, $target, $slow);
        }

        if ($target instanceof Player) {
            $target->sendTip(TextFormat::YELLOW . ($isFinal ? "MIRROR FINALE!" : "MIRROR BOUNCE!"));
        }
    }

    $this->spawnBounceVFX($from, $to, $isFinal);

    $this->currentBounce++;
}

    private function spawnBounceVFX($from, $to, $isFinal) {
        $lv = $this->owner->getLevel();

        $fx = $from->x;
        $fy = $from->y + 1;
        $fz = $from->z;
        $tx = $to->x;
        $ty = $to->y + 1;
        $tz = $to->z;

        for ($layer = 0; $layer < 2; $layer++) {
            $offset = ($layer - 0.5) * 0.15;
            for ($i = 0; $i <= 20; $i++) {
                $prog = $i / 20;
                $x = $fx + ($tx - $fx) * $prog;
                $y = $fy + ($ty - $fy) * $prog + sin($prog * M_PI) * 0.8 + $offset;
                $z = $fz + ($tz - $fz) * $prog;

                $lv->addParticle(new DustParticle(
                    new Vector3($x, $y, $z),
                    self::COL_YELLOW_R, self::COL_YELLOW_G, self::COL_YELLOW_B
                ));
            }
        }

        for ($i = 0; $i < 8; $i++) {
            $prog = $i / 7;
            $x = $fx + ($tx - $fx) * $prog;
            $y = $fy + ($ty - $fy) * $prog + sin($prog * M_PI) * 0.8;
            $z = $fz + ($tz - $fz) * $prog;

            $lv->addParticle(new InstantEnchantParticle(new Vector3($x, $y + 0.3, $z)));
        }

        if ($isFinal) {
            for ($ring = 0; $ring < 4; $ring++) {
                $rr = 1.5 + $ring * 0.6;
                for ($i = 0; $i < 14; $i++) {
                    $a = ($i / 14) * M_PI * 2;
                    $lv->addParticle(new DustParticle(
                        new Vector3($tx + cos($a) * $rr, $ty + $ring * 0.3, $tz + sin($a) * $rr),
                        self::COL_GOLD_R, self::COL_GOLD_G, self::COL_GOLD_B
                    ));
                }
            }

            for ($i = 0; $i < 16; $i++) {
                $lv->addParticle(new InstantEnchantParticle(new Vector3(
                    $tx + (mt_rand(-20, 20) / 10),
                    $ty + (mt_rand(0, 30) / 10),
                    $tz + (mt_rand(-20, 20) / 10)
                )));
            }

            for ($i = 0; $i < 10; $i++) {
                $lv->addParticle(new CriticalParticle(new Vector3(
                    $tx + (mt_rand(-15, 15) / 10),
                    $ty + (mt_rand(0, 20) / 10),
                    $tz + (mt_rand(-15, 15) / 10)
                )));
            }

            $lv->addParticle(new ExplodeParticle(new Vector3($tx, $ty, $tz)));
            $lv->addParticle(new ExplodeParticle(new Vector3($tx, $ty + 1, $tz)));
            $lv->addParticle(new ExplodeParticle(new Vector3($tx, $ty + 2, $tz)));

            $this->sendSplash($lv, $tx, $ty, $tz);
            $this->sendSplash($lv, $tx, $ty + 1.5, $tz);

            $lv->addSound(new AnvilUseSound(new Vector3($tx, $ty, $tz)));
            $lv->addSound(new FizzSound(new Vector3($tx, $ty, $tz)));
        } else {
            for ($i = 0; $i < 10; $i++) {
                $a = ($i / 10) * M_PI * 2;
                $lv->addParticle(new DustParticle(
                    new Vector3($tx + cos($a) * 1.0, $ty, $tz + sin($a) * 1.0),
                    self::COL_GOLD_R, self::COL_GOLD_G, self::COL_GOLD_B
                ));
            }

            for ($i = 0; $i < 6; $i++) {
                $lv->addParticle(new InstantEnchantParticle(new Vector3(
                    $tx + (mt_rand(-10, 10) / 10),
                    $ty + (mt_rand(0, 15) / 10),
                    $tz + (mt_rand(-10, 10) / 10)
                )));
            }

            $lv->addParticle(new ExplodeParticle(new Vector3($tx, $ty, $tz)));
            $this->sendSplash($lv, $tx, $ty, $tz);
        }

        $lv->addSound(new ClickSound(new Vector3($tx, $ty, $tz)));
        $lv->addSound(new PopSound(new Vector3($fx, $fy, $fz)));
        $lv->addSound(new EndermanTeleportSound(new Vector3($tx, $ty, $tz)));
    }

    private function sendSplash($lv, $x, $y, $z) {
        $pk = new LevelEventPacket();
        $pk->evid = self::EV_SPLASH;
        $pk->data = self::COL_SPLASH_GOLD;
        $pk->x = (float)$x;
        $pk->y = (float)$y;
        $pk->z = (float)$z;
        foreach ($lv->getPlayers() as $pl) {
            $pl->dataPacket($pk);
        }
    }
}

class LightDebrisTask extends Task {

    private $plugin;
    private $level;
    private $cx;
    private $cy;
    private $cz;
    private $radius;
    private $debris = [];
    private $ticksRan = 0;
    private $maxTicks = 50;
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
            mt_rand(5, 8), 0.5, 1.0, 28
        );

        for ($i = 0; $i < 12; $i++) {
            $da = ($i / 12) * M_PI * 2;
            $dd = 0.6 + mt_rand(0, 25) / 10;
            $this->level->addParticle(new DustParticle(
                new Vector3($this->cx + cos($da) * $dd, $this->cy + 0.3, $this->cz + sin($da) * $dd),
                255, 220, 50
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

        $players = $this->level->getPlayers();
        $toRemove = BlockEffects::tickDebris($this->debris, $this->level, $this->cy - 1.5, 0.042, 0.97);
        foreach ($toRemove as $eid) { unset($this->debris[$eid]); }
    }

    private function removeOneDebris($players, $eid, $d) {
        BlockEffects::voidAndRemove($this->plugin, $this->level, [$eid]);
    }


    public function forceCleanup() {
        if ($this->cleaned) return;
        $this->cleaned = true;
        BlockEffects::voidAndRemove($this->plugin, $this->level, array_keys($this->debris));
        $this->debris = [];
    }
}