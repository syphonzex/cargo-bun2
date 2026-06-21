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
use pocketmine\level\particle\MobSpellParticle;
use pocketmine\level\particle\EnchantParticle;
use pocketmine\level\particle\InstantEnchantParticle;
use pocketmine\level\particle\SmokeParticle;
use pocketmine\level\particle\PortalParticle;
use pocketmine\level\sound\AnvilUseSound;
use pocketmine\level\sound\PopSound;
use pocketmine\level\sound\ClickSound;
use pocketmine\level\sound\FizzSound;
use pocketmine\level\sound\EndermanTeleportSound;
use OnePiece\Devil\BlockEffects;
use pocketmine\network\protocol\LevelEventPacket;
use OnePiece\NPC\NPCEntity;

class IroIro extends BaseFruit {

    private $stringWebs = [];
    private $webTaskIds = [];

    const COL_PINK_R = 255;
    const COL_PINK_G = 100;
    const COL_PINK_B = 200;
    const COL_WHITE_R = 255;
    const COL_WHITE_G = 220;
    const COL_WHITE_B = 255;
    const COL_DARK_R = 180;
    const COL_DARK_G = 50;
    const COL_DARK_B = 150;
    const VIEW_RANGE = 50;
    const EV_SPLASH = 2002;
    const COL_SPLASH_PINK = 16711935;

    public function getId() { return "iro_iro"; }
    public function getDisplayName() { return "String-String Fruit"; }
    public function getDescription() { return "String Fruit - Doflamingo's threads of absolute control."; }
    public function getType() { return "paramecia"; }
    public function getRarity() { return "legendary"; }

    public function getAbilityNames() {
        return [
            "ability1" => "Fulbright / Parasite",
            "ability2" => "Ever White"
        ];
    }

    public function getAbilityCooldowns() {
        return [
            "ability1" => 6.0,
            "ability2" => 18.0
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
        if ($this->hasActiveWeb($player)) {
            return $this->parasite($player);
        }
        return $this->fulbright($player);
    }

    private function handleSneakTap(Player $player) {
        return $this->everWhite($player);
    }

    private function fulbright(Player $player) {
        $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
        $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

        $name = $player->getName();
        $mult = min(1.5, $this->getHakiMultiplier($player));

        $baseRadius = 14.0;
        $bonusRadius = 6.0 * ($mult - 1.0);
        $radius = $baseRadius + $bonusRadius;

        $baseDuration = 20;
        $bonusDuration = (int)(10 * ($mult - 1.0));
        $duration = $baseDuration + $bonusDuration;

        $this->destroyWeb($player, false);

        $pos = $player->getPosition();
        $durationTicks = $duration * 20;

        $this->stringWebs[$name] = [
            "x" => $pos->x,
            "y" => $pos->y,
            "z" => $pos->z,
            "radius" => $radius,
            "level" => $player->getLevel()->getName(),
            "endTime" => microtime(true) + $duration,
            "owner" => $name
        ];

        $this->spawnWebInitial($player, $radius);

        $vfxTask = new StringWebTask($this->plugin, $player->getLevel(), $pos->x, $pos->y + 1, $pos->z, $radius, $name, (int)($durationTicks / 2));
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($vfxTask, 2);
        $this->webTaskIds[$name] = $vfxTask->getTaskId();

        $debrisTask = new StringDebrisTask(
            $this->plugin,
            $player->getLevel(),
            $pos->x,
            $pos->y,
            $pos->z,
            $radius
        );
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($debrisTask, 1);

        $player->sendTip(TextFormat::LIGHT_PURPLE . TextFormat::BOLD . "FULBRIGHT!");
        $player->sendMessage(TextFormat::LIGHT_PURPLE . "String web deployed!");
        $player->sendMessage(TextFormat::GRAY . "Radius: " . round($radius, 1) . " blocks - Duration: " . $duration . "s");
        $player->sendMessage(TextFormat::GRAY . "[Tap] Parasite - [Sneak+Tap] Ever White");

        $damage = min(9.0, 4.0 * $mult);
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
                    $reason = $this->plugin->getTargetBlockReason($player->getName(), $t);
                    if ($reason !== null) $player->sendTip($reason);
                    continue;
                }
            }

            $to = $tp->subtract($pos);
            $norm = new Vector3($to->x / $dist, 0, $to->z / $dist);
            $dot = $dir->x * $norm->x + $dir->z * $norm->z;

            if ($dot > 0.3) {
                $ev = new EntityDamageByEntityEvent($player, $t, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage);
                //$t->attack($damage, $ev);
                $this->dealAbilityDamage($player, $t, $damage);

                $slow = Effect::getEffect(Effect::SLOWNESS);
                $slow->setAmplifier(3);
                $slow->setDuration(80);
                $slow->setVisible(false);
                $this->safeAddEffect($player, $t, $slow);

                if ($t instanceof Player) {
                    $t->sendTip(TextFormat::LIGHT_PURPLE . "Caught in strings!");
                }
                $hits++;

                $this->spawnStringHitVFX($player->getLevel(), $pos, $tp);
            }
        }

        if ($hits > 0) {
            $player->sendMessage(TextFormat::LIGHT_PURPLE . "Strings hit " . $hits . " targets!");
        }

        $this->plugin->getServer()->getScheduler()->scheduleDelayedTask(
            new StringExpireTask($this, $name, $this->stringWebs[$name]["endTime"]),
            $durationTicks
        );

        return $this->getAbilityCooldowns()["ability1"];
    }

    private function parasite(Player $player) {
        $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
        $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

        $target = $this->findTargetInWeb($player);

        if ($target === null) {
            $player->sendTip(TextFormat::LIGHT_PURPLE . "PARASITE... no targets in web.");
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
        $damage = min(9.0, 4.0 * $mult);

        $pPos = $player->getPosition();
        $tPos = $target->getPosition();

        $dx = $pPos->x - $tPos->x;
        $dz = $pPos->z - $tPos->z;
        $dist = sqrt($dx * $dx + $dz * $dz);

        if ($dist > 0.5) {
            $pullStrength = 1.5;
            $this->safeSetMotion($player, $target, new Vector3(
                ($dx / $dist) * $pullStrength,
                0.4,
                ($dz / $dist) * $pullStrength
            ));
        }

        $this->dealAbilityDamage($player, $target, $damage);

        $slow = Effect::getEffect(Effect::SLOWNESS);
        $slow->setAmplifier(4);
        $slow->setDuration(60);
        $slow->setVisible(false);
        $this->safeAddEffect($player, $target, $slow);

        $mining = Effect::getEffect(Effect::MINING_FATIGUE);
        $mining->setAmplifier(2);
        $mining->setDuration(60);
        $mining->setVisible(false);
        $this->safeAddEffect($player, $target, $mining);

        $this->spawnParasiteVFX($player, $target);

        $parasiteDebris = new StringDebrisTask(
            $this->plugin,
            $target->getLevel(),
            $tPos->x,
            $tPos->y,
            $tPos->z,
            3.0
        );
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($parasiteDebris, 1);

        $targetName = ($target instanceof Player) ? $target->getName() : "target";
        $player->sendTip(TextFormat::LIGHT_PURPLE . TextFormat::BOLD . "PARASITE!");
        $player->sendMessage(TextFormat::LIGHT_PURPLE . "Controlling " . $targetName . "!");

        if ($target instanceof Player) {
            $target->sendTip(TextFormat::DARK_PURPLE . TextFormat::BOLD . "PARASITE!");
            $target->sendMessage(TextFormat::DARK_PURPLE . "You're being controlled by " . $player->getName() . "!");
        }

        return 4.0;
    }

    private function everWhite(Player $player) {
        $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
        $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

        $mult = min(1.5, $this->getHakiMultiplier($player));
        $damage = min(12.0, 5.0 * $mult);
        $radius = 13.0;
        $hits = 0;
        $pos = $player->getPosition();

$this->spawnEverWhiteVFX($player, $radius);

foreach ($this->getNearbyTargets($player, $radius) as $t) {
            $dist = $pos->distance($t->getPosition());
            if ($dist <= 0) continue;

            if ($t instanceof Player) {
                if (!$this->plugin->canTargetPlayer($player->getName(), $t)) {
                    $reason = $this->plugin->getTargetBlockReason($player->getName(), $t);
                    if ($reason !== null) $player->sendTip($reason);
                    $reason = $this->plugin->getTargetBlockReason($player->getName(), $t);
                    if ($reason !== null) $player->sendTip($reason);
                    continue;
                }
            }

            $scaled = $damage * (1 - ($dist / $radius) * 0.2);
            $ev = new EntityDamageByEntityEvent($player, $t, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $scaled);
            $t->attack($scaled, $ev);

            $slow = Effect::getEffect(Effect::SLOWNESS);
            $slow->setAmplifier(4);
            $slow->setDuration(100);
            $slow->setVisible(false);
            $this->safeAddEffect($player, $t, $slow);

            $mining = Effect::getEffect(Effect::MINING_FATIGUE);
            $mining->setAmplifier(3);
            $mining->setDuration(80);
            $mining->setVisible(false);
            $this->safeAddEffect($player, $t, $mining);

            $blind = Effect::getEffect(Effect::BLINDNESS);
            $blind->setAmplifier(1);
            $blind->setDuration(60);
            $blind->setVisible(false);
            $this->safeAddEffect($player, $t, $blind);

            $dx = $t->x - $pos->x;
            $dz = $t->z - $pos->z;
            $len = sqrt($dx * $dx + $dz * $dz);
            if ($len > 0) {
                $this->safeSetMotion($player, $t, new Vector3($dx / $len * 2.0, 0.8, $dz / $len * 2.0));
            }

            if ($t instanceof Player) {
                $t->sendTip(TextFormat::LIGHT_PURPLE . TextFormat::BOLD . "EVER WHITE!");
                $t->sendMessage(TextFormat::LIGHT_PURPLE . "Trapped in the string cage!");
            }
            $hits++;
        }

        $player->sendTip(TextFormat::LIGHT_PURPLE . TextFormat::BOLD . "EVER WHITE!");
        $player->sendMessage(TextFormat::LIGHT_PURPLE . "String cage hit " . $hits . " targets!");

        return $this->getAbilityCooldowns()["ability2"];
    }

    private function spawnWebInitial(Player $player, $radius) {
        $lv = $player->getLevel();
        $pos = $player->getPosition();
        $cx = $pos->x;
        $cy = $pos->y + 1;
        $cz = $pos->z;

        for ($ring = 0; $ring < 4; $ring++) {
            $rr = $radius * (0.25 + $ring * 0.25);
            $pts = 12 + $ring * 6;
            for ($i = 0; $i < $pts; $i++) {
                $a = ($i / $pts) * M_PI * 2;
                $lv->addParticle(new DustParticle(
                    new Vector3($cx + cos($a) * $rr, $cy, $cz + sin($a) * $rr),
                    self::COL_PINK_R, self::COL_PINK_G, self::COL_PINK_B
                ));
            }
        }

        for ($i = 0; $i < 8; $i++) {
            $a = ($i / 8) * M_PI * 2;
            for ($d = 0; $d < 10; $d++) {
                $dist = ($d / 9) * $radius;
                $lv->addParticle(new DustParticle(
                    new Vector3($cx + cos($a) * $dist, $cy + sin($d * 0.5) * 0.5, $cz + sin($a) * $dist),
                    self::COL_WHITE_R, self::COL_WHITE_G, self::COL_WHITE_B
                ));
            }
        }

        for ($h = -2; $h <= 4; $h++) {
            for ($i = 0; $i < 6; $i++) {
                $a = ($i / 6) * M_PI * 2 + $h * 0.3;
                $lv->addParticle(new MobSpellParticle(
                    new Vector3($cx + cos($a) * 0.5, $cy + $h * 0.5, $cz + sin($a) * 0.5),
                    self::COL_PINK_R, self::COL_PINK_G, self::COL_PINK_B
                ));
            }
        }

        for ($i = 0; $i < 16; $i++) {
            $lv->addParticle(new EnchantParticle(new Vector3(
                $cx + (mt_rand(-30, 30) / 10),
                $cy + (mt_rand(-10, 30) / 10),
                $cz + (mt_rand(-30, 30) / 10)
            )));
        }

        $this->sendSplash($lv, $cx, $cy, $cz, self::COL_SPLASH_PINK);
        $lv->addSound(new ClickSound(new Vector3($cx, $cy, $cz)));
        $lv->addSound(new EndermanTeleportSound(new Vector3($cx, $cy, $cz)));
    }

    private function spawnStringHitVFX($lv, $from, $to) {
        $fx = $from->x;
        $fy = $from->y + 1.2;
        $fz = $from->z;
        $tx = $to->x;
        $ty = $to->y + 1;
        $tz = $to->z;

        for ($i = 0; $i <= 12; $i++) {
            $prog = $i / 12;
            $x = $fx + ($tx - $fx) * $prog;
            $y = $fy + ($ty - $fy) * $prog + sin($prog * M_PI) * 0.5;
            $z = $fz + ($tz - $fz) * $prog;

            $lv->addParticle(new DustParticle(
                new Vector3($x + (mt_rand(-2, 2) / 10), $y, $z + (mt_rand(-2, 2) / 10)),
                self::COL_PINK_R, self::COL_PINK_G, self::COL_PINK_B
            ));
        }

        for ($i = 0; $i < 6; $i++) {
            $a = ($i / 6) * M_PI * 2;
            $lv->addParticle(new MobSpellParticle(
                new Vector3($tx + cos($a) * 0.8, $ty, $tz + sin($a) * 0.8),
                self::COL_WHITE_R, self::COL_WHITE_G, self::COL_WHITE_B
            ));
        }

        $lv->addParticle(new CriticalParticle(new Vector3($tx, $ty, $tz)));
    }

    private function spawnParasiteVFX(Player $player, Entity $target) {
        $lv = $player->getLevel();
        $pPos = $player->getPosition();
        $tPos = $target->getPosition();

        $px = $pPos->x;
        $py = $pPos->y + 1.5;
        $pz = $pPos->z;
        $tx = $tPos->x;
        $ty = $tPos->y + 1;
        $tz = $tPos->z;

        for ($s = 0; $s < 5; $s++) {
            $offsetY = ($s - 2) * 0.3;
            for ($i = 0; $i <= 15; $i++) {
                $prog = $i / 15;
                $wave = sin($prog * M_PI * 3 + $s) * 0.3;
                $x = $px + ($tx - $px) * $prog + $wave;
                $y = $py + ($ty - $py) * $prog + $offsetY;
                $z = $pz + ($tz - $pz) * $prog + $wave;

                $lv->addParticle(new DustParticle(
                    new Vector3($x, $y, $z),
                    self::COL_PINK_R, self::COL_PINK_G, self::COL_PINK_B
                ));
            }
        }

        for ($layer = 0; $layer < 3; $layer++) {
            $layerY = $ty + $layer * 0.6;
            for ($i = 0; $i < 10; $i++) {
                $a = ($i / 10) * M_PI * 2 + $layer * 0.5;
                $r = 1.0 - $layer * 0.2;
                $lv->addParticle(new MobSpellParticle(
                    new Vector3($tx + cos($a) * $r, $layerY, $tz + sin($a) * $r),
                    self::COL_DARK_R, self::COL_DARK_G, self::COL_DARK_B
                ));
            }
        }

        for ($i = 0; $i < 8; $i++) {
            $lv->addParticle(new InstantEnchantParticle(new Vector3(
                $tx + (mt_rand(-10, 10) / 10),
                $ty + (mt_rand(0, 20) / 10),
                $tz + (mt_rand(-10, 10) / 10)
            )));
        }

        for ($i = 0; $i < 6; $i++) {
            $a = ($i / 6) * M_PI * 2;
            $lv->addParticle(new PortalParticle(new Vector3(
                $tx + cos($a) * 1.5,
                $ty + 1,
                $tz + sin($a) * 1.5
            )));
        }

        $this->sendSplash($lv, $tx, $ty, $tz, self::COL_SPLASH_PINK);
        $lv->addSound(new ClickSound(new Vector3($tx, $ty, $tz)));
        $lv->addSound(new PopSound(new Vector3($px, $py, $pz)));
    }

    private function spawnEverWhiteVFX(Player $player, $radius) {
        $lv = $player->getLevel();
        $px = $player->x;
        $py = $player->y + 0.5;
        $pz = $player->z;

        for ($h = 0; $h < 8; $h++) {
            $hy = $py + $h * 1.5;
            $hr = $radius * (1.0 - $h * 0.08);
            $pts = 20 - $h;
            for ($i = 0; $i < $pts; $i++) {
                $a = ($i / $pts) * M_PI * 2 + $h * 0.4;
                $lv->addParticle(new DustParticle(
                    new Vector3($px + cos($a) * $hr, $hy, $pz + sin($a) * $hr),
                    self::COL_WHITE_R, self::COL_WHITE_G, self::COL_WHITE_B
                ));
            }
        }

        for ($v = 0; $v < 12; $v++) {
            $a = ($v / 12) * M_PI * 2;
            for ($h = 0; $h < 6; $h++) {
                $hy = $py + $h * 2;
                $hr = $radius * (1.0 - $h * 0.1);
                $lv->addParticle(new DustParticle(
                    new Vector3($px + cos($a) * $hr, $hy, $pz + sin($a) * $hr),
                    self::COL_PINK_R, self::COL_PINK_G, self::COL_PINK_B
                ));
            }
        }

        for ($ring = 1; $ring <= 5; $ring++) {
            $rr = $radius * ($ring / 5);
            $pts = 14 + $ring * 4;
            for ($i = 0; $i < $pts; $i++) {
                $a = ($i / $pts) * M_PI * 2;
                $lv->addParticle(new MobSpellParticle(
                    new Vector3($px + cos($a) * $rr, $py, $pz + sin($a) * $rr),
                    self::COL_PINK_R, self::COL_PINK_G, self::COL_PINK_B
                ));
            }
        }

        for ($i = 0; $i < 24; $i++) {
            $a = mt_rand(0, 628) / 100;
            $d = mt_rand(10, (int)($radius * 10)) / 10;
            $h = mt_rand(0, 80) / 10;
            $lv->addParticle(new EnchantParticle(new Vector3(
                $px + cos($a) * $d,
                $py + $h,
                $pz + sin($a) * $d
            )));
        }

        for ($i = 0; $i < 16; $i++) {
            $lv->addParticle(new CriticalParticle(new Vector3(
                $px + (mt_rand(-100, 100) / 10),
                $py + (mt_rand(0, 60) / 10),
                $pz + (mt_rand(-100, 100) / 10)
            )));
        }

        for ($i = 0; $i < 8; $i++) {
            $a = ($i / 8) * M_PI * 2;
            for ($s = 0; $s < 4; $s++) {
                $d = ($s + 1) * ($radius / 4);
                $lv->addParticle(new InstantEnchantParticle(new Vector3(
                    $px + cos($a) * $d,
                    $py + 1,
                    $pz + sin($a) * $d
                )));
            }
        }

        $lv->addParticle(new ExplodeParticle(new Vector3($px, $py, $pz)));
        $lv->addParticle(new ExplodeParticle(new Vector3($px, $py + 4, $pz)));

        $this->sendSplash($lv, $px, $py, $pz, self::COL_SPLASH_PINK);
        $this->sendSplash($lv, $px, $py + 4, $pz, self::COL_SPLASH_PINK);

        $lv->addSound(new AnvilUseSound(new Vector3($px, $py, $pz)));
        $lv->addSound(new FizzSound(new Vector3($px, $py, $pz)));
        $lv->addSound(new EndermanTeleportSound(new Vector3($px, $py, $pz)));
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

    public function hasActiveWeb(Player $player) {
        $name = $player->getName();

        if (!isset($this->stringWebs[$name])) {
            return false;
        }

        if (microtime(true) >= $this->stringWebs[$name]["endTime"]) {
            $this->expireWeb($name);
            return false;
        }

        return true;
    }

    public function getWebData(Player $player) {
        $name = $player->getName();
        return isset($this->stringWebs[$name]) ? $this->stringWebs[$name] : null;
    }

    public function isInsideWeb(Player $owner, $target) {
        $web = $this->getWebData($owner);
        if ($web === null) return false;

        if ($target instanceof Entity) {
            $targetPos = $target->getPosition();
            $targetLevel = $target->getLevel()->getName();
        } elseif ($target instanceof Vector3) {
            $targetPos = $target;
            $targetLevel = $owner->getLevel()->getName();
        } else {
            return false;
        }

        if ($targetLevel !== $web["level"]) return false;

        $dx = $targetPos->x - $web["x"];
        $dy = $targetPos->y - $web["y"];
        $dz = $targetPos->z - $web["z"];
        $distance = sqrt($dx * $dx + $dy * $dy + $dz * $dz);

        return $distance <= $web["radius"];
    }

    public function getEntitiesInWeb(Player $owner) {
        $web = $this->getWebData($owner);
        if ($web === null) return [];

        $level = $this->plugin->getServer()->getLevelByName($web["level"]);
        if ($level === null) return [];

        $entities = [];
        $webCenter = new Vector3($web["x"], $web["y"], $web["z"]);

        foreach ($level->getEntities() as $entity) {
            if ($entity instanceof Player && $entity->getName() === $owner->getName()) continue;
            if (!$entity->isAlive()) continue;
            if (!($entity instanceof Player) && !($entity instanceof NPCEntity)) continue;

            $distance = $webCenter->distance($entity->getPosition());
            if ($distance <= $web["radius"]) {
                $entities[] = $entity;
            }
        }

        return $entities;
    }

    private function findTargetInWeb(Player $player) {
        $entities = $this->getEntitiesInWeb($player);
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

    private function destroyWeb(Player $player, $notify = true) {
        $name = $player->getName();

        if (isset($this->webTaskIds[$name])) {
            try {
                $this->plugin->getServer()->getScheduler()->cancelTask($this->webTaskIds[$name]);
            } catch (\Exception $e) {}
            unset($this->webTaskIds[$name]);
        }

        if (!isset($this->stringWebs[$name])) return false;

        unset($this->stringWebs[$name]);

        if ($notify && $player->isOnline()) {
            $player->sendMessage(TextFormat::GRAY . "String web dissipated.");
        }

        return true;
    }

    public function expireWeb($playerName, $endTime = null) {
        if (isset($this->stringWebs[$playerName])) {
            if ($endTime !== null && $this->stringWebs[$playerName]["endTime"] !== $endTime) {
                return;
            }
        } else {
            return;
        }

        if (isset($this->webTaskIds[$playerName])) {
            try {
                $this->plugin->getServer()->getScheduler()->cancelTask($this->webTaskIds[$playerName]);
            } catch (\Exception $e) {}
            unset($this->webTaskIds[$playerName]);
        }

        unset($this->stringWebs[$playerName]);

        $player = $this->plugin->getServer()->getPlayerExact($playerName);
        if ($player !== null && $player->isOnline()) {
            $player->sendTip(TextFormat::GRAY . "Strings faded.");
            $player->sendMessage(TextFormat::GRAY . "Your string web has expired.");
        }
    }

    public function onEquip(Player $player) {
        $player->sendMessage(TextFormat::LIGHT_PURPLE . "=== Ito-Ito no Mi ===");
        $player->sendMessage(TextFormat::WHITE . "String-String Fruit - Doflamingo's Absolute Control");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::LIGHT_PURPLE . "[Tap] No Web: " . TextFormat::WHITE . "FULBRIGHT");
        $player->sendMessage(TextFormat::GRAY . "  Deploy string web, damage and trap targets");
        $player->sendMessage(TextFormat::LIGHT_PURPLE . "[Tap] In Web: " . TextFormat::WHITE . "PARASITE");
        $player->sendMessage(TextFormat::GRAY . "  Pull and control target with strings");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::LIGHT_PURPLE . "[Sneak+Tap]: " . TextFormat::WHITE . "EVER WHITE");
        $player->sendMessage(TextFormat::GRAY . "  Massive string cage explosion");
        $player->sendMessage(TextFormat::LIGHT_PURPLE . "=======================");
    }

    public function onUnequip(Player $player) {
        $this->destroyWeb($player, false);
        $player->sendMessage(TextFormat::GRAY . "The strings retract into nothingness...");
    }
}

class StringExpireTask extends Task {

    private $fruit;
    private $playerName;
    private $endTime;

    public function __construct(IroIro $fruit, $playerName, $endTime) {
        $this->fruit = $fruit;
        $this->playerName = $playerName;
        $this->endTime = $endTime;
    }

    public function onRun($currentTick) {
        $this->fruit->expireWeb($this->playerName, $this->endTime);
    }
}

class StringDebrisTask extends Task {

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
            mt_rand(4, 6), 0.4, 1.0, 25
        );

        for ($i = 0; $i < 8; $i++) {
            $da = ($i / 8) * M_PI * 2;
            $dd = 0.5 + mt_rand(0, 15) / 10;
            $this->level->addParticle(new DustParticle(
                new Vector3($this->cx + cos($da) * $dd, $this->cy + 0.2, $this->cz + sin($da) * $dd),
                255, 150, 220
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
        $toRemove = BlockEffects::tickDebris($this->debris, $this->level, $this->cy - 1.5, 0.04, 0.97);
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

class StringWebTask extends Task {

    private $plugin;
    private $level;
    private $cx;
    private $cy;
    private $cz;
    private $radius;
    private $ownerName;
    private $totalTicks;
    private $ticksRan = 0;
    private $phase = 0.0;

    const COL_PINK_R = 255;
    const COL_PINK_G = 100;
    const COL_PINK_B = 200;
    const COL_WHITE_R = 255;
    const COL_WHITE_G = 220;
    const COL_WHITE_B = 255;
    const COL_DARK_R = 180;
    const COL_DARK_G = 50;
    const COL_DARK_B = 150;
    const VIEW_RANGE = 50;

    public function __construct($plugin, Level $level, $cx, $cy, $cz, $radius, $ownerName, $totalTicks) {
        $this->plugin = $plugin;
        $this->level = $level;
        $this->cx = (float)$cx;
        $this->cy = (float)$cy;
        $this->cz = (float)$cz;
        $this->radius = (float)$radius;
        $this->ownerName = $ownerName;
        $this->totalTicks = (int)$totalTicks;
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

        $players = $this->getNearbyPlayers();
        if (empty($players)) return;

        $this->phase += 0.15;
        $this->drawWeb($players);
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

    private function drawWeb($players) {
        $t = $this->phase;
        $r = $this->radius;
        $cx = $this->cx;
        $cy = $this->cy;
        $cz = $this->cz;

        $pulse = 1.0 + sin($t * 0.5) * 0.03;
        $pr = $r * $pulse;

        $eqPts = 14;
        for ($i = 0; $i < $eqPts; $i++) {
            $a = ($i / $eqPts) * M_PI * 2 + $t * 0.3;
            $this->sendParticle($players, new DustParticle(
                new Vector3($cx + cos($a) * $pr, $cy, $cz + sin($a) * $pr),
                self::COL_PINK_R, self::COL_PINK_G, self::COL_PINK_B
            ));
        }

        $stringCount = 6;
        for ($s = 0; $s < $stringCount; $s++) {
            $baseAngle = ($s / $stringCount) * M_PI * 2;
            $rotAngle = $baseAngle + $t * 0.4;
            $wave = sin($t * 2 + $s) * 0.5;

            for ($d = 0; $d < 8; $d++) {
                $dist = ($d / 7) * $pr;
                $wy = $cy + sin($d * 0.8 + $t) * 0.4 + $wave;
                $this->sendParticle($players, new DustParticle(
                    new Vector3($cx + cos($rotAngle) * $dist, $wy, $cz + sin($rotAngle) * $dist),
                    self::COL_WHITE_R, self::COL_WHITE_G, self::COL_WHITE_B
                ));
            }
        }

        $verticalStrings = 4;
        for ($v = 0; $v < $verticalStrings; $v++) {
            $va = ($v / $verticalStrings) * M_PI * 2 + $t * 0.2;
            $vr = $pr * 0.7;
            for ($h = 0; $h < 5; $h++) {
                $vy = $cy - 1 + $h * 1.0 + sin($t + $v) * 0.3;
                $this->sendParticle($players, new MobSpellParticle(
                    new Vector3($cx + cos($va) * $vr, $vy, $cz + sin($va) * $vr),
                    self::COL_PINK_R, self::COL_PINK_G, self::COL_PINK_B
                ));
            }
        }

        $crossLen = $r * 0.3;
        $crossRot = $t * 0.6;
        $cosR = cos($crossRot);
        $sinR = sin($crossRot);
        for ($c = -3; $c <= 3; $c++) {
            $off = ($c / 3) * $crossLen;
            $this->sendParticle($players, new DustParticle(
                new Vector3($cx + $off * $cosR, $cy + 0.5, $cz + $off * $sinR),
                self::COL_DARK_R, self::COL_DARK_G, self::COL_DARK_B
            ));
            $this->sendParticle($players, new DustParticle(
                new Vector3($cx - $off * $sinR, $cy + 0.5, $cz + $off * $cosR),
                self::COL_DARK_R, self::COL_DARK_G, self::COL_DARK_B
            ));
        }

        $innerPts = 6;
        $innerR = $r * 0.25;
        for ($i = 0; $i < $innerPts; $i++) {
            $a = ($i / $innerPts) * M_PI * 2 + $t * 2;
            $oy = sin($t * 3 + $i) * 0.4;
            $this->sendParticle($players, new EnchantParticle(
                new Vector3($cx + cos($a) * $innerR, $cy + $oy + 1, $cz + sin($a) * $innerR)
            ));
        }

        if ($this->ticksRan % 4 === 0) {
            $fa = mt_rand(0, 628) / 100;
            $fd = mt_rand(10, (int)($r * 8)) / 10;
            $this->sendParticle($players, new InstantEnchantParticle(
                new Vector3($cx + cos($fa) * $fd, $cy + (mt_rand(-10, 20) / 10), $cz + sin($fa) * $fd)
            ));
        }

        if ($this->ticksRan % 6 === 0) {
            $connA1 = mt_rand(0, 628) / 100;
            $connA2 = $connA1 + M_PI * (0.3 + mt_rand(0, 14) / 10);
            $connR = $pr * (0.4 + mt_rand(0, 40) / 100);

            $x1 = $cx + cos($connA1) * $connR;
            $z1 = $cz + sin($connA1) * $connR;
            $x2 = $cx + cos($connA2) * $connR;
            $z2 = $cz + sin($connA2) * $connR;

            for ($p = 0; $p < 5; $p++) {
                $prog = $p / 4;
                $this->sendParticle($players, new DustParticle(
                    new Vector3(
                        $x1 + ($x2 - $x1) * $prog,
                        $cy + sin($prog * M_PI) * 0.5,
                        $z1 + ($z2 - $z1) * $prog
                    ),
                    self::COL_WHITE_R, self::COL_WHITE_G, self::COL_WHITE_B
                ));
            }
        }
    }

    private function sendParticle($players, $particle) {
        $pk = $particle->encode();
        foreach ($players as $pl) {
            $pl->dataPacket($pk);
        }
    }
}

class StringCageDebrisTask extends Task {

    private $plugin;
    private $level;
    private $cx;
    private $cy;
    private $cz;
    private $radius;
    private $debris   = [];
    private $ticksRan = 0;
    private $maxTicks = 70;
    private $cleaned  = false;

    public function __construct($plugin, Level $level, $cx, $cy, $cz, $radius) {
        $this->plugin  = $plugin;
        $this->level   = $level;
        $this->cx      = (float)$cx;
        $this->cy      = (float)$cy;
        $this->cz      = (float)$cz;
        $this->radius  = (float)$radius;

        $this->spawnDebris();
    }

    private function spawnDebris() {
        $count = mt_rand(8, 12);

        $this->debris = BlockEffects::spawnSpiralDebris(
            $this->plugin,
            $this->level,
            $this->cx, $this->cy, $this->cz,
            $count,
            $this->radius * 0.6,
            35
        );

        $pts = 12;
        for ($i = 0; $i < $pts; $i++) {
            $a = ($i / $pts) * M_PI * 2;
            $d = $this->radius * 0.6;
            $this->level->addParticle(new DustParticle(
                new Vector3(
                    $this->cx + cos($a) * $d,
                    $this->cy + 0.3,
                    $this->cz + sin($a) * $d
                ),
                255, 150, 220
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

        $toRemove = BlockEffects::tickSpiralDebris(
            $this->debris,
            $this->level,
            $this->cx,
            $this->cz,
            0.055,
            0.12,
            0.018
        );

        foreach ($this->debris as $eid => $d) {
            if ($this->ticksRan % 4 === 0) {
                $this->level->addParticle(new DustParticle(
                    new Vector3($d["x"], $d["y"], $d["z"]),
                    255, 100, 200
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