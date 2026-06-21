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
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\scheduler\Task;
use pocketmine\level\Level;
use pocketmine\block\Block;
use pocketmine\level\particle\DustParticle;
use pocketmine\level\particle\MobSpellParticle;
use pocketmine\level\particle\InstantEnchantParticle;
use pocketmine\level\particle\EnchantParticle;
use pocketmine\level\particle\ExplodeParticle;
use pocketmine\level\particle\CriticalParticle;
use pocketmine\level\particle\SmokeParticle;
use pocketmine\level\sound\EndermanTeleportSound;
use pocketmine\level\sound\ClickSound;
use pocketmine\level\sound\FizzSound;
use pocketmine\level\sound\AnvilUseSound;
use pocketmine\level\sound\PopSound;
use OnePiece\Devil\BlockEffects;
use pocketmine\network\protocol\LevelEventPacket;
use OnePiece\NPC\NPCEntity;
use OnePieceTrades\Factory\FactoryEntity;

class OpeOpe extends BaseFruit {

    private $activeRooms = [];
    private $roomTaskIds = [];
    private $blockTasks = [];

    const COL_CYAN_R = 0;
    const COL_CYAN_G = 200;
    const COL_CYAN_B = 255;
    const COL_BLUE_R = 0;
    const COL_BLUE_G = 100;
    const COL_BLUE_B = 255;
    const COL_WHITE_R = 200;
    const COL_WHITE_G = 240;
    const COL_WHITE_B = 255;
    const VIEW_RANGE = 50;
    const EV_SPLASH = 2002;
    const COL_SPLASH_CYAN = 65535;
    const COL_SPLASH_BLUE = 3694022;

    public function getId() { return "ope_ope"; }
    public function getDisplayName() { return "Ope-Ope Fruit"; }
    public function getDescription() { return "Operation Fruit - create a Room, the perfect sphere of absolute control."; }
    public function getType() { return "paramecia"; }
    public function getRarity() { return "legendary"; }

    public function getAbilityNames() {
        return [
            "ability1" => "ROOM / Shambles",
            "ability2" => "Gamma Knife"
        ];
    }

    public function getAbilityCooldowns() {
        return [
            "ability1" => 7.0,
            "ability2" => 12.0
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
        if ($this->hasActiveRoom($player) && $this->isOwnerInsideRoom($player)) {
            return $this->shambles($player);
        }
        return $this->activateRoom($player);
    }

    private function handleSneakTap(Player $player) {
        if (!$this->hasActiveRoom($player)) {
            $player->sendTip(TextFormat::RED . "No ROOM active!");
            $player->sendMessage(TextFormat::GRAY . "Use Tap to create ROOM first.");
            return 2.0;
        }

        if (!$this->isOwnerInsideRoom($player)) {
            $player->sendTip(TextFormat::RED . "You left your ROOM!");
            $player->sendMessage(TextFormat::GRAY . "Return to ROOM or Tap to create new one.");
            return 2.0;
        }

        return $this->gammaKnife($player);
    }

    private function activateRoom(Player $player) {
        $name = $player->getName();
        $mult = min(1.5, $this->getHakiMultiplier($player));

        $baseRadius = 9.0;
        $bonusRadius = 5.0 * ($mult - 1.0);
        $radius = $baseRadius + $bonusRadius;

        $baseDuration = 25;
        $bonusDuration = (int)(12 * ($mult - 1.0));
        $duration = $baseDuration + $bonusDuration;

        $this->destroyRoom($player, false);

        $pos = $player->getPosition();
        $durationTicks = $duration * 20;

        $this->activeRooms[$name] = [
            "x" => $pos->x,
            "y" => $pos->y,
            "z" => $pos->z,
            "radius" => $radius,
            "level" => $player->getLevel()->getName(),
            "endTime" => microtime(true) + $duration,
            "owner" => $name
        ];

        $this->spawnRoomInitial($player, $radius);

        $vfxTask = new RoomVFXTask($this->plugin, $player->getLevel(), $pos->x, $pos->y + 1, $pos->z, $radius, (int)($durationTicks / 2));
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($vfxTask, 2);
        $this->roomTaskIds[$name] = $vfxTask->getTaskId();

        $blocks = $this->scanEnvironmentBlocks($player->getLevel(), $pos->x, $pos->y, $pos->z, $radius);
        $blockTask = new RoomBlockTask(
            $this->plugin,
            $player->getLevel(),
            $pos->x, $pos->y + 1, $pos->z,
            $radius,
            $name,
            $blocks,
            $durationTicks
        );
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($blockTask, 1);
        $this->blockTasks[$name] = $blockTask;

        $player->sendTip(TextFormat::AQUA . TextFormat::BOLD . "ROOM!");
        $player->sendMessage(TextFormat::AQUA . "ROOM activated!");
        $player->sendMessage(TextFormat::GRAY . "Radius: " . round($radius, 1) . " blocks - Duration: " . $duration . "s");
        $player->sendMessage(TextFormat::GRAY . "[Tap] Shambles - [Sneak+Tap] Gamma Knife");

        $this->plugin->getServer()->getScheduler()->scheduleDelayedTask(
            new RoomExpireTask($this, $name, $this->activeRooms[$name]["endTime"]),
            $durationTicks
        );

        return 9.0;
    }

    private function scanEnvironmentBlocks(Level $level, $cx, $cy, $cz, $radius) {
        $found = [];
        $skip = [0, 8, 9, 10, 11, 26, 30, 31, 32, 37, 38, 39, 40, 50, 51, 55, 59, 63, 64, 65, 68, 69, 70, 71, 72, 75, 76, 77, 83, 90, 93, 94, 96, 104, 105, 106, 115, 127, 131, 132, 141, 142, 143, 144, 147, 148, 149, 150, 154, 157, 167, 171, 175, 176, 177, 178, 183, 184, 185, 186, 187, 193, 194, 195, 196, 197];

        $scanR = min((int)$radius, 8);

        for ($x = -$scanR; $x <= $scanR; $x += 2) {
            for ($z = -$scanR; $z <= $scanR; $z += 2) {
                for ($y = -3; $y <= 3; $y++) {
                    $bx = (int)($cx + $x);
                    $by = (int)($cy + $y);
                    $bz = (int)($cz + $z);
                    $block = $level->getBlock(new Vector3($bx, $by, $bz));
                    $id = $block->getId();
                    $dmg = $block->getDamage();

                    if (in_array($id, $skip)) continue;

                    $key = $id . ":" . $dmg;
                    if (!isset($found[$key])) {
                        $found[$key] = ["id" => $id, "damage" => $dmg];
                    }

                    if (count($found) >= 6) {
                        return array_values($found);
                    }
                }
            }
        }

        if (empty($found)) {
            $found[] = ["id" => 1, "damage" => 0];
            $found[] = ["id" => 4, "damage" => 0];
            $found[] = ["id" => 3, "damage" => 0];
        }

        return array_values($found);
    }

    private function spawnRoomInitial(Player $player, $radius) {
        $pos = $player->getPosition();
        $lv = $player->getLevel();
        $cx = $pos->x;
        $cy = $pos->y + 1;
        $cz = $pos->z;

        for ($ring = 0; $ring < 3; $ring++) {
            $rr = $radius * (0.3 + $ring * 0.35);
            $pts = 16 + $ring * 8;
            for ($i = 0; $i < $pts; $i++) {
                $a = ($i / $pts) * M_PI * 2;
                $lv->addParticle(new DustParticle(
                    new Vector3($cx + cos($a) * $rr, $cy, $cz + sin($a) * $rr),
                    self::COL_CYAN_R, self::COL_CYAN_G, self::COL_CYAN_B
                ));
            }
        }

        for ($ring = 1; $ring <= 4; $ring++) {
            $phi = ($ring / 5) * M_PI * 0.5;
            $rr = cos($phi) * $radius;
            $ry = sin($phi) * $radius;
            $ringPts = max(10, (int)(20 * cos($phi)));
            for ($i = 0; $i < $ringPts; $i++) {
                $a = ($i / $ringPts) * M_PI * 2;
                $lv->addParticle(new DustParticle(
                    new Vector3($cx + cos($a) * $rr, $cy + $ry, $cz + sin($a) * $rr),
                    self::COL_CYAN_R, self::COL_CYAN_G, self::COL_CYAN_B
                ));
                $lv->addParticle(new DustParticle(
                    new Vector3($cx + cos($a) * $rr, $cy - $ry, $cz + sin($a) * $rr),
                    self::COL_CYAN_R, self::COL_CYAN_G, self::COL_CYAN_B
                ));
            }
        }

        for ($i = 0; $i < 4; $i++) {
            $a = ($i / 4) * M_PI * 2;
            for ($h = 0; $h < 6; $h++) {
                $py = $cy - $radius + ($h / 5) * $radius * 2;
                $lv->addParticle(new InstantEnchantParticle(
                    new Vector3($cx + cos($a) * 0.3, $py, $cz + sin($a) * 0.3)
                ));
            }
        }

        $crossLen = $radius * 0.4;
        for ($c = -4; $c <= 4; $c++) {
            $off = ($c / 4) * $crossLen;
            $lv->addParticle(new MobSpellParticle(
                new Vector3($cx + $off, $cy, $cz),
                self::COL_WHITE_R, self::COL_WHITE_G, self::COL_WHITE_B
            ));
            $lv->addParticle(new MobSpellParticle(
                new Vector3($cx, $cy, $cz + $off),
                self::COL_WHITE_R, self::COL_WHITE_G, self::COL_WHITE_B
            ));
        }

        $lv->addParticle(new MobSpellParticle(new Vector3($cx, $cy + $radius, $cz), self::COL_WHITE_R, self::COL_WHITE_G, self::COL_WHITE_B));
        $lv->addParticle(new MobSpellParticle(new Vector3($cx, $cy - $radius, $cz), self::COL_WHITE_R, self::COL_WHITE_G, self::COL_WHITE_B));

        for ($i = 0; $i < 12; $i++) {
            $lv->addParticle(new EnchantParticle(new Vector3(
                $cx + (mt_rand(-30, 30) / 10),
                $cy + (mt_rand(-10, 20) / 10),
                $cz + (mt_rand(-30, 30) / 10)
            )));
        }

        $this->sendSplash($lv, $cx, $cy, $cz, self::COL_SPLASH_CYAN);
        $this->sendSplash($lv, $cx, $cy + 1, $cz, self::COL_SPLASH_BLUE);
        $lv->addSound(new AnvilUseSound(new Vector3($cx, $cy, $cz)));
        $lv->addSound(new EndermanTeleportSound(new Vector3($cx, $cy, $cz)));
    }

    private function shambles(Player $player) {
        $target = $this->findTargetInRoom($player);

        if ($target === null) {
            $player->sendTip(TextFormat::AQUA . "SHAMBLES... no targets in ROOM.");
            return 3.0;
        }

        if ($target instanceof Player) {
            if (!$this->plugin->canTargetPlayer($player->getName(), $target)) {
                $reason = $this->plugin->getTargetBlockReason($player->getName(), $target);
                if ($reason !== null) $player->sendTip($reason);
                $player->sendMessage(TextFormat::RED . "Cannot swap - PvP restrictions");
                return 3.0;
            }
        }

        $pPos = clone $player->getPosition();
        $tPos = clone $target->getPosition();

        $this->spawnShamblesVFX($player->getLevel(), $pPos->x, $pPos->y, $pPos->z, $tPos->x, $tPos->y, $tPos->z);

        $player->teleport($tPos);
        $target->teleport($pPos);

        $mult = min(1.5, $this->getHakiMultiplier($player));
        $damage = min(12.0, 4.5 * $mult);

        $this->dealAbilityDamage($player, $target, $damage);

        $this->safeAddEffect($player, $target, Effect::NAUSEA, 2, 50);
        $this->safeAddEffect($player, $target, Effect::SLOWNESS, 1, 35);

        $targetName = ($target instanceof Player) ? $target->getName() : "target";
        $player->sendTip(TextFormat::AQUA . "SHAMBLES!");
        $player->sendMessage(TextFormat::AQUA . "Swapped positions with " . $targetName . "!");

        if ($target instanceof Player) {
            $target->sendTip(TextFormat::AQUA . "SHAMBLES!");
            $target->sendMessage(TextFormat::AQUA . "Position swapped by " . $player->getName() . "!");
        }

        return $this->getAbilityCooldowns()["ability1"];
    }

    private function spawnShamblesVFX($lv, $x1, $y1, $z1, $x2, $y2, $z2) {
        $y1 += 1;
        $y2 += 1;

        for ($i = 0; $i < 14; $i++) {
            $a = ($i / 14) * M_PI * 2;
            $r1 = 1.2 + sin($a * 3) * 0.3;
            $r2 = 1.2 + cos($a * 3) * 0.3;
            $lv->addParticle(new MobSpellParticle(
                new Vector3($x1 + cos($a) * $r1, $y1 + sin($a * 4) * 0.4, $z1 + sin($a) * $r1),
                self::COL_CYAN_R, self::COL_CYAN_G, self::COL_CYAN_B
            ));
            $lv->addParticle(new MobSpellParticle(
                new Vector3($x2 + cos($a) * $r2, $y2 + sin($a * 4) * 0.4, $z2 + sin($a) * $r2),
                self::COL_BLUE_R, self::COL_BLUE_G, self::COL_BLUE_B
            ));
        }

        for ($v = 0; $v < 8; $v++) {
            $vy = ($v / 7) * 2.0;
            $va = ($v / 7) * M_PI * 4;
            $vr = 0.6 + sin($va) * 0.3;
            $lv->addParticle(new DustParticle(
                new Vector3($x1 + cos($va) * $vr, $y1 - 0.5 + $vy, $z1 + sin($va) * $vr),
                self::COL_CYAN_R, self::COL_CYAN_G, self::COL_CYAN_B
            ));
            $lv->addParticle(new DustParticle(
                new Vector3($x2 + cos($va + M_PI) * $vr, $y2 - 0.5 + $vy, $z2 + sin($va + M_PI) * $vr),
                self::COL_BLUE_R, self::COL_BLUE_G, self::COL_BLUE_B
            ));
        }

        for ($i = 0; $i < 12; $i++) {
            $prog = $i / 11;
            $a = $prog * M_PI * 5;
            $px = $x1 + ($x2 - $x1) * $prog;
            $py = $y1 + ($y2 - $y1) * $prog + sin($a) * 0.5;
            $pz = $z1 + ($z2 - $z1) * $prog + cos($a) * 0.5;
            $lv->addParticle(new DustParticle(
                new Vector3($px, $py, $pz),
                self::COL_CYAN_R, self::COL_CYAN_G, self::COL_CYAN_B
            ));
        }

        for ($i = 0; $i < 12; $i++) {
            $prog = $i / 11;
            $a = $prog * M_PI * 5 + M_PI;
            $px = $x2 + ($x1 - $x2) * $prog;
            $py = $y2 + ($y1 - $y2) * $prog + sin($a) * 0.5;
            $pz = $z2 + ($z1 - $z2) * $prog + cos($a) * 0.5;
            $lv->addParticle(new DustParticle(
                new Vector3($px, $py, $pz),
                self::COL_BLUE_R, self::COL_BLUE_G, self::COL_BLUE_B
            ));
        }

        for ($i = 0; $i < 8; $i++) {
            $lv->addParticle(new InstantEnchantParticle(new Vector3(
                $x1 + (mt_rand(-10, 10) / 10),
                $y1 + (mt_rand(0, 18) / 10),
                $z1 + (mt_rand(-10, 10) / 10)
            )));
            $lv->addParticle(new InstantEnchantParticle(new Vector3(
                $x2 + (mt_rand(-10, 10) / 10),
                $y2 + (mt_rand(0, 18) / 10),
                $z2 + (mt_rand(-10, 10) / 10)
            )));
        }

        $this->sendSplash($lv, $x1, $y1, $z1, self::COL_SPLASH_CYAN);
        $this->sendSplash($lv, $x2, $y2, $z2, self::COL_SPLASH_BLUE);
        $this->sendSplash($lv, ($x1 + $x2) / 2, ($y1 + $y2) / 2, ($z1 + $z2) / 2, self::COL_SPLASH_CYAN);

        $lv->addSound(new EndermanTeleportSound(new Vector3($x1, $y1, $z1)));
        $lv->addSound(new EndermanTeleportSound(new Vector3($x2, $y2, $z2)));
        $lv->addSound(new PopSound(new Vector3($x1, $y1, $z1)));
        $lv->addSound(new PopSound(new Vector3($x2, $y2, $z2)));
    }

    private function gammaKnife(Player $player) {
        $target = $this->findFrontTargetInRoom($player, 10);

        if ($target === null) {
            $player->sendTip(TextFormat::AQUA . "GAMMA KNIFE... no target in sight.");
            return 3.0;
        }

        if ($target instanceof Player) {
            if (!$this->plugin->canTargetPlayer($player->getName(), $target)) {
                $reason = $this->plugin->getTargetBlockReason($player->getName(), $target);
                if ($reason !== null) $player->sendTip($reason);
                $player->sendMessage(TextFormat::RED . "Cannot attack - PvP restrictions");
                return 3.0;
            }
        }

        $mult = min(1.5, $this->getHakiMultiplier($player));
        $damage = min(18.0, 6.0 * $mult);

        $this->dealAbilityDamage($player, $target, $damage);

        $this->safeAddEffect($player, $target, Effect::WITHER, 2, 100);
        $this->safeAddEffect($player, $target, Effect::POISON, 2, 100);
        $this->safeAddEffect($player, $target, Effect::SLOWNESS, 4, 80);
        $this->safeAddEffect($player, $target, Effect::NAUSEA, 2, 100);
        $this->safeAddEffect($player, $target, Effect::MINING_FATIGUE, 2, 80);
        $this->safeAddEffect($player, $target, Effect::WEAKNESS, 2, 80);

        $this->spawnGammaKnifeVFX($player, $target);

        $player->sendTip(TextFormat::AQUA . TextFormat::BOLD . "GAMMA KNIFE!");
        $player->sendMessage(TextFormat::AQUA . "Internal organs destroyed!");

        if ($target instanceof Player) {
            $target->sendTip(TextFormat::DARK_RED . TextFormat::BOLD . "GAMMA KNIFE!");
            $target->sendMessage(TextFormat::DARK_RED . "Your insides are being shredded!");
            $target->sendMessage(TextFormat::GRAY . "Wither, Poison, Weakness, Slowness applied!");
        }

        return $this->getAbilityCooldowns()["ability2"];
    }

    private function spawnGammaKnifeVFX(Player $player, Entity $target) {
        $lv = $player->getLevel();
        $pPos = $player->getPosition();
        $tPos = $target->getPosition();

        $px = $pPos->x;
        $py = $pPos->y + 1.2;
        $pz = $pPos->z;
        $tx = $tPos->x;
        $ty = $tPos->y + 1;
        $tz = $tPos->z;

        $dir = $player->getDirectionVector();
        $hx = $px + $dir->x * 0.8;
        $hy = $py + $dir->y * 0.8;
        $hz = $pz + $dir->z * 0.8;

        for ($i = 0; $i < 6; $i++) {
            $a = ($i / 6) * M_PI * 2;
            $lv->addParticle(new MobSpellParticle(
                new Vector3($hx + cos($a) * 0.4, $hy + sin($a) * 0.4, $hz),
                self::COL_CYAN_R, self::COL_CYAN_G, self::COL_CYAN_B
            ));
        }

        $steps = 12;
        for ($i = 0; $i <= $steps; $i++) {
            $prog = $i / $steps;
            $bx = $hx + ($tx - $hx) * $prog;
            $by = $hy + ($ty - $hy) * $prog;
            $bz = $hz + ($tz - $hz) * $prog;
            $lv->addParticle(new DustParticle(
                new Vector3($bx, $by, $bz),
                self::COL_CYAN_R, self::COL_CYAN_G, self::COL_CYAN_B
            ));
            if ($i % 2 === 0) {
                $spiralA = $prog * M_PI * 6;
                $spiralR = 0.3 * (1.0 - $prog * 0.5);
                $lv->addParticle(new InstantEnchantParticle(new Vector3(
                    $bx + cos($spiralA) * $spiralR,
                    $by + sin($spiralA) * $spiralR,
                    $bz + sin($spiralA) * $spiralR
                )));
            }
        }

        $crossSize = 2.8;
        for ($i = -5; $i <= 5; $i++) {
            $off = $i * ($crossSize / 5);
            $glow = abs($i) <= 2 ? 255 : 200;
            $lv->addParticle(new MobSpellParticle(
                new Vector3($tx + $off, $ty, $tz),
                self::COL_CYAN_R, $glow, self::COL_CYAN_B
            ));
            $lv->addParticle(new MobSpellParticle(
                new Vector3($tx, $ty + $off * 0.5, $tz),
                self::COL_CYAN_R, $glow, self::COL_CYAN_B
            ));
            $lv->addParticle(new MobSpellParticle(
                new Vector3($tx, $ty, $tz + $off),
                self::COL_CYAN_R, $glow, self::COL_CYAN_B
            ));
        }

        for ($i = 0; $i < 10; $i++) {
            $a = ($i / 10) * M_PI * 2;
            $lv->addParticle(new DustParticle(
                new Vector3($tx + cos($a) * 2.0, $ty, $tz + sin($a) * 2.0),
                self::COL_CYAN_R, self::COL_CYAN_G, self::COL_CYAN_B
            ));
        }

        for ($i = 0; $i < 6; $i++) {
            $a = ($i / 6) * M_PI * 2;
            $lv->addParticle(new DustParticle(
                new Vector3($tx + cos($a) * 1.0, $ty, $tz + sin($a) * 1.0),
                self::COL_BLUE_R, self::COL_BLUE_G, self::COL_BLUE_B
            ));
        }

        for ($i = 0; $i < 14; $i++) {
            $lv->addParticle(new InstantEnchantParticle(new Vector3(
                $tx + (mt_rand(-15, 15) / 10),
                $ty + (mt_rand(-10, 15) / 10),
                $tz + (mt_rand(-15, 15) / 10)
            )));
        }

        for ($i = 0; $i < 6; $i++) {
            $a = mt_rand(0, 628) / 100;
            $d = mt_rand(3, 12) / 10;
            $lv->addParticle(new SmokeParticle(new Vector3(
                $tx + cos($a) * $d,
                $ty + (mt_rand(-5, 5) / 10),
                $tz + sin($a) * $d
            )));
        }

        $lv->addParticle(new ExplodeParticle(new Vector3($tx, $ty, $tz)));

        $this->sendSplash($lv, $tx, $ty, $tz, self::COL_SPLASH_CYAN);
        $this->sendSplash($lv, $tx, $ty + 0.5, $tz, self::COL_SPLASH_BLUE);
        $this->sendSplash($lv, $hx, $hy, $hz, self::COL_SPLASH_CYAN);

        $lv->addSound(new ClickSound(new Vector3($tx, $ty, $tz)));
        $lv->addSound(new FizzSound(new Vector3($tx, $ty, $tz)));
        $lv->addSound(new AnvilUseSound(new Vector3($tx, $ty, $tz)));
        $lv->addSound(new PopSound(new Vector3($hx, $hy, $hz)));
    }

protected function safeAddEffect($attacker, $target, $effect) {
    if (!$target->isAlive() || $target->closed) return;
    
    if (is_int($effect)) {
        $effectId = $effect;
        $amplifier = 0;
        $duration = 100;
    } elseif ($effect instanceof Effect) {
        parent::safeAddEffect($attacker, $target, $effect);
        return;
    } else {
        return;
    }
    
    $existing = $target->getEffect($effectId);
    if ($existing !== null) {
        $existingLevel = $existing->getAmplifier();
        $existingDuration = $existing->getDuration();
        
        if ($existingLevel >= $amplifier && $existingDuration >= $duration) {
            return;
        }
    }
    
    $effectObj = Effect::getEffect($effectId);
    if ($effectObj !== null) {
        $effectObj = clone $effectObj;
        $effectObj->setAmplifier($amplifier);
        $effectObj->setDuration($duration);
        $effectObj->setVisible(false);
        $target->addEffect($effectObj);
    }
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

    private function destroyRoom(Player $player, $notify = true) {
        $name = $player->getName();

        if (isset($this->roomTaskIds[$name])) {
            try {
                $this->plugin->getServer()->getScheduler()->cancelTask($this->roomTaskIds[$name]);
            } catch (\Exception $e) {}
            unset($this->roomTaskIds[$name]);
        }

        if (isset($this->blockTasks[$name])) {
            $this->blockTasks[$name]->forceCleanup();
            try {
                $this->plugin->getServer()->getScheduler()->cancelTask($this->blockTasks[$name]->getTaskId());
            } catch (\Exception $e) {}
            unset($this->blockTasks[$name]);
        }

        if (!isset($this->activeRooms[$name])) {
            return false;
        }

        unset($this->activeRooms[$name]);

        if ($notify && $player->isOnline()) {
            $player->sendMessage(TextFormat::GRAY . "ROOM collapsed.");
        }

        return true;
    }

    public function expireRoom($playerName, $endTime = null) {
        if (isset($this->activeRooms[$playerName])) {
            if ($endTime !== null && $this->activeRooms[$playerName]["endTime"] !== $endTime) {
                return;
            }
        } else {
            return;
        }

        if (isset($this->roomTaskIds[$playerName])) {
            try {
                $this->plugin->getServer()->getScheduler()->cancelTask($this->roomTaskIds[$playerName]);
            } catch (\Exception $e) {}
            unset($this->roomTaskIds[$playerName]);
        }

        if (isset($this->blockTasks[$playerName])) {
            $this->blockTasks[$playerName]->forceCleanup();
            try {
                $this->plugin->getServer()->getScheduler()->cancelTask($this->blockTasks[$playerName]->getTaskId());
            } catch (\Exception $e) {}
            unset($this->blockTasks[$playerName]);
        }

        unset($this->activeRooms[$playerName]);

        $player = $this->plugin->getServer()->getPlayerExact($playerName);
        if ($player !== null && $player->isOnline()) {
            $player->sendTip(TextFormat::GRAY . "ROOM expired.");
            $player->sendMessage(TextFormat::GRAY . "Your ROOM has expired.");
        }
    }

    public function hasActiveRoom(Player $player) {
        $name = $player->getName();

        if (!isset($this->activeRooms[$name])) {
            return false;
        }

        if (microtime(true) >= $this->activeRooms[$name]["endTime"]) {
            $this->expireRoom($name);
            return false;
        }

        return true;
    }

    public function getRoomData(Player $player) {
        $name = $player->getName();
        return isset($this->activeRooms[$name]) ? $this->activeRooms[$name] : null;
    }

    public function isInsideRoom(Player $owner, $target) {
        $room = $this->getRoomData($owner);
        if ($room === null) {
            return false;
        }

        if ($target instanceof Entity) {
            $targetPos = $target->getPosition();
            $targetLevel = $target->getLevel()->getName();
        } elseif ($target instanceof Vector3) {
            $targetPos = $target;
            $targetLevel = $owner->getLevel()->getName();
        } else {
            return false;
        }

        if ($targetLevel !== $room["level"]) {
            return false;
        }

        $dx = $targetPos->x - $room["x"];
        $dy = $targetPos->y - $room["y"];
        $dz = $targetPos->z - $room["z"];
        $distance = sqrt($dx * $dx + $dy * $dy + $dz * $dz);

        return $distance <= $room["radius"];
    }

    public function isOwnerInsideRoom(Player $player) {
        return $this->isInsideRoom($player, $player);
    }

    public function getEntitiesInRoom(Player $owner) {
        $room = $this->getRoomData($owner);
        if ($room === null) {
            return [];
        }

        $level = $this->plugin->getServer()->getLevelByName($room["level"]);
        if ($level === null) {
            return [];
        }

        $entities = [];
        $roomCenter = new Vector3($room["x"], $room["y"], $room["z"]);

        foreach ($level->getEntities() as $entity) {
            if ($entity instanceof Player && $entity->getName() === $owner->getName()) {
                continue;
            }

            if (!$entity->isAlive()) {
                continue;
            }

            if (!($entity instanceof Player) && !($entity instanceof NPCEntity) && !($entity instanceof FactoryEntity)) {
                continue;
            }

            $distance = $roomCenter->distance($entity->getPosition());
            if ($distance <= $room["radius"]) {
                $entities[] = $entity;
            }
        }

        return $entities;
    }

    public function getPlayersInRoom(Player $owner) {
        $entities = $this->getEntitiesInRoom($owner);
        $players = [];

        foreach ($entities as $entity) {
            if ($entity instanceof Player) {
                $players[] = $entity;
            }
        }

        return $players;
    }

    private function findTargetInRoom(Player $player) {
        $entities = $this->getEntitiesInRoom($player);

        if (empty($entities)) {
            return null;
        }

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

    private function findFrontTargetInRoom(Player $player, $maxDist) {
        $dir = $player->getDirectionVector();
        $start = $player->add(0, $player->getEyeHeight(), 0);

        $entities = $this->getEntitiesInRoom($player);
        $best = null;
        $bestDist = $maxDist + 1;

        foreach ($entities as $entity) {
            if ($entity instanceof Player) {
                if (!$this->plugin->canTargetPlayer($player->getName(), $entity)) {
                    continue;
                }
            }

            $tp = $entity->add(0, 1, 0);
            $dist = $start->distance($tp);

            if ($dist > $maxDist || $dist <= 0) continue;

            $to = $tp->subtract($start);
            $norm = new Vector3($to->x / $dist, $to->y / $dist, $to->z / $dist);
            $dot = $dir->x * $norm->x + $dir->y * $norm->y + $dir->z * $norm->z;

            if ($dot > 0.4 && $dist < $bestDist) {
                $bestDist = $dist;
                $best = $entity;
            }
        }

        return $best;
    }

    public function getRoomTimeRemaining(Player $player) {
        $room = $this->getRoomData($player);
        if ($room === null) {
            return 0;
        }

        $remaining = $room["endTime"] - microtime(true);
        return max(0, $remaining);
    }

    public function onEquip(Player $player) {
        $player->sendMessage(TextFormat::AQUA . "=== Ope-Ope no Mi ===");
        $player->sendMessage(TextFormat::WHITE . "The Ultimate Devil Fruit - Surgeon of Death");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::AQUA . "[Tap] No ROOM: " . TextFormat::WHITE . "Create ROOM");
        $player->sendMessage(TextFormat::AQUA . "[Tap] In ROOM: " . TextFormat::WHITE . "SHAMBLES");
        $player->sendMessage(TextFormat::GRAY . "  Swap positions with target");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::AQUA . "[Sneak+Tap]: " . TextFormat::WHITE . "GAMMA KNIFE");
        $player->sendMessage(TextFormat::GRAY . "  Destroy target internal organs");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::GRAY . "  Blocks orbit and attack enemies in ROOM");
        $player->sendMessage(TextFormat::AQUA . "========================");
    }

    public function onUnequip(Player $player) {
        $this->destroyRoom($player, false);
        $player->sendMessage(TextFormat::GRAY . "The ROOM fades... your power dissipates.");
    }
}

class RoomExpireTask extends Task {

    private $fruit;
    private $playerName;
    private $endTime;

    public function __construct(OpeOpe $fruit, $playerName, $endTime) {
        $this->fruit = $fruit;
        $this->playerName = $playerName;
        $this->endTime = $endTime;
    }

    public function onRun($currentTick) {
        $this->fruit->expireRoom($this->playerName, $this->endTime);
    }
}

class RoomVFXTask extends Task {

    private $plugin;
    private $level;
    private $cx;
    private $cy;
    private $cz;
    private $radius;
    private $totalTicks;
    private $ticksRan = 0;
    private $phase = 0.0;

    const COL_CYAN_R = 0;
    const COL_CYAN_G = 200;
    const COL_CYAN_B = 255;
    const COL_BLUE_R = 0;
    const COL_BLUE_G = 100;
    const COL_BLUE_B = 255;
    const COL_WHITE_R = 200;
    const COL_WHITE_G = 240;
    const COL_WHITE_B = 255;
    const VIEW_RANGE = 50;
    const EV_SPLASH = 2002;
    const COL_SPLASH_CYAN = 65535;

    public function __construct($plugin, Level $level, $cx, $cy, $cz, $radius, $totalTicks) {
        $this->plugin = $plugin;
        $this->level = $level;
        $this->cx = (float)$cx;
        $this->cy = (float)$cy;
        $this->cz = (float)$cz;
        $this->radius = (float)$radius;
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
        if (empty($players)) {
            return;
        }

        $this->phase += 0.12;
        $this->drawRoom($players);
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

    private function drawRoom($players) {
        $t = $this->phase;
        $r = $this->radius;
        $cx = $this->cx;
        $cy = $this->cy;
        $cz = $this->cz;

        $pulse = 1.0 + sin($t * 0.6) * 0.02;
        $pr = $r * $pulse;

        $eqPts = 16;
        for ($i = 0; $i < $eqPts; $i++) {
            $a = ($i / $eqPts) * M_PI * 2;
            $this->sendParticle($players, new DustParticle(
                new Vector3($cx + cos($a) * $pr, $cy, $cz + sin($a) * $pr),
                self::COL_CYAN_R, self::COL_CYAN_G, self::COL_CYAN_B
            ));
        }

        $latPts = 10;
        $latOffsets = [0.3, 0.6, 0.85, -0.3, -0.6, -0.85];
        foreach ($latOffsets as $latOff) {
            $latY = $cy + $latOff * $pr;
            $latR = sqrt(max(0, 1 - $latOff * $latOff)) * $pr;
            for ($i = 0; $i < $latPts; $i++) {
                $a = ($i / $latPts) * M_PI * 2;
                $this->sendParticle($players, new DustParticle(
                    new Vector3($cx + cos($a) * $latR, $latY, $cz + sin($a) * $latR),
                    self::COL_CYAN_R, self::COL_CYAN_G, self::COL_CYAN_B
                ));
            }
        }

        $this->sendParticle($players, new MobSpellParticle(
            new Vector3($cx, $cy + $pr, $cz),
            self::COL_WHITE_R, self::COL_WHITE_G, self::COL_WHITE_B
        ));
        $this->sendParticle($players, new MobSpellParticle(
            new Vector3($cx, $cy - $pr, $cz),
            self::COL_WHITE_R, self::COL_WHITE_G, self::COL_WHITE_B
        ));

        $meridianPts = 12;
        for ($m = 0; $m < 3; $m++) {
            $rotAngle = $t * 0.35 + $m * (M_PI * 2 / 3);
            for ($i = 0; $i < $meridianPts; $i++) {
                $phi = ($i / $meridianPts) * M_PI;
                $sinPhi = sin($phi);
                $cosPhi = cos($phi);
                $this->sendParticle($players, new MobSpellParticle(
                    new Vector3(
                        $cx + $sinPhi * cos($rotAngle) * $pr,
                        $cy + $cosPhi * $pr,
                        $cz + $sinPhi * sin($rotAngle) * $pr
                    ),
                    self::COL_CYAN_R, self::COL_CYAN_G, self::COL_CYAN_B
                ));
            }
        }

        $crossLen = $r * 0.25;
        $crossRot = $t * 0.5;
        $cosR = cos($crossRot);
        $sinR = sin($crossRot);
        for ($c = -3; $c <= 3; $c++) {
            $off = ($c / 3) * $crossLen;
            $this->sendParticle($players, new DustParticle(
                new Vector3($cx + $off * $cosR, $cy, $cz + $off * $sinR),
                self::COL_BLUE_R, self::COL_BLUE_G, self::COL_BLUE_B
            ));
            $this->sendParticle($players, new DustParticle(
                new Vector3($cx - $off * $sinR, $cy, $cz + $off * $cosR),
                self::COL_BLUE_R, self::COL_BLUE_G, self::COL_BLUE_B
            ));
        }

        $crossVLen = $r * 0.15;
        for ($c = -2; $c <= 2; $c++) {
            $off = ($c / 2) * $crossVLen;
            $this->sendParticle($players, new DustParticle(
                new Vector3($cx, $cy + $off, $cz),
                self::COL_WHITE_R, self::COL_WHITE_G, self::COL_WHITE_B
            ));
        }

        $innerPts = 8;
        $innerR = $r * 0.3;
        for ($i = 0; $i < $innerPts; $i++) {
            $a = ($i / $innerPts) * M_PI * 2 + $t * 1.5;
            $oy = sin($t * 2.0 + $i * 0.8) * 0.3;
            $this->sendParticle($players, new EnchantParticle(
                new Vector3($cx + cos($a) * $innerR, $cy + $oy, $cz + sin($a) * $innerR)
            ));
        }

        if ($this->ticksRan % 6 === 0) {
            $fa = mt_rand(0, 628) / 100;
            $fd = mt_rand(10, (int)($r * 8)) / 10;
            $this->sendParticle($players, new EnchantParticle(
                new Vector3($cx + cos($fa) * $fd, $cy + (mt_rand(-10, 10) / 10), $cz + sin($fa) * $fd)
            ));
        }

        $floorR = $r * 0.85;
        $floorPts = 8;
        $floorRot = $t * 0.2;
        $floorY = $cy - $r + 0.5;
        for ($i = 0; $i < $floorPts; $i++) {
            $a = ($i / $floorPts) * M_PI * 2 + $floorRot;
            $this->sendParticle($players, new DustParticle(
                new Vector3($cx + cos($a) * $floorR, $floorY, $cz + sin($a) * $floorR),
                self::COL_BLUE_R, self::COL_BLUE_G, self::COL_BLUE_B
            ));
        }

        if ($this->ticksRan % 40 === 0) {
            $this->sendSplash($players, $cx, $cy, $cz, self::COL_SPLASH_CYAN);
        }
    }

    private function sendParticle($players, $particle) {
        $pk = $particle->encode();
        foreach ($players as $pl) {
            $pl->dataPacket($pk);
        }
    }

    private function sendSplash($players, $x, $y, $z, $col) {
        $pk = new LevelEventPacket();
        $pk->evid = self::EV_SPLASH;
        $pk->data = $col;
        $pk->x = (float)$x;
        $pk->y = (float)$y;
        $pk->z = (float)$z;
        foreach ($players as $pl) {
            $pl->dataPacket($pk);
        }
    }
}

class RoomBlockTask extends Task {

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
    private $cleaned = false;

    private $blockEntities = [];
    private $blockCount = 3;
    private $spawned = false;

    private $chargingBlocks = [];
    private $breakStates = [];

    const HIT_DAMAGE_BASE = 3.5;
    const HIT_DAMAGE_MAX = 7.0;
    const VIEW_RANGE = 50;
    const STRIKE_INTERVAL = 80;
    const ORBIT_HEIGHT = 4.0;
    const CHARGE_SPEED = 0.8;
    const CHARGE_STEPS = 10;
    const EXILE_Y = -200.0;

    public function __construct($plugin, Level $level, $cx, $cy, $cz, $radius, $ownerName, $blocks, $totalTicks) {
        $this->plugin = $plugin;
        $this->level = $level;
        $this->cx = (float)$cx;
        $this->cy = (float)$cy;
        $this->cz = (float)$cz;
        $this->radius = (float)$radius;
        $this->ownerName = $ownerName;
        $this->totalTicks = (int)$totalTicks;

        $this->blockCount = min(3, max(2, (int)($radius / 4)));

        for ($i = 0; $i < $this->blockCount; $i++) {
            $blockData = $blocks[$i % count($blocks)];
            $eid = BlockEffects::newEid();

            $this->blockEntities[$i] = [
                "eid" => $eid,
                "blockId" => $blockData["id"],
                "blockDamage" => $blockData["damage"],
                "angle" => ($i / $this->blockCount) * M_PI * 2,
                "heightOffset" => self::ORBIT_HEIGHT + (mt_rand(-10, 10) / 10),
                "orbitSpeed" => 0.08 + (mt_rand(0, 40) / 1000),
                "x" => $this->cx,
                "y" => $this->cy + self::ORBIT_HEIGHT,
                "z" => $this->cz
            ];

            $this->breakStates[$i] = 0;
            $this->chargingBlocks[$i] = null;
        }
    }

    private function exileEntity($eid) {
        BlockEffects::sendMove($this->level->getPlayers(), $eid, 0.0, BlockEffects::VOID_Y, 0.0);
    }

    private function exileAndRemove($eid) {
        BlockEffects::voidAndRemove($this->plugin, $this->level, [$eid]);
    }

    public function forceCleanup() {
        if ($this->cleaned) return;
        $this->cleaned = true;
        $eids = [];
        foreach ($this->blockEntities as $b) { $eids[] = $b["eid"]; }
        BlockEffects::voidAndRemove($this->plugin, $this->level, $eids);
        $this->blockEntities = [];
    }

    public function onRun($currentTick) {
        if ($this->cleaned) return;

        $this->ticksRan++;

        if ($this->ticksRan > $this->totalTicks) {
            $this->forceCleanup();
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
            return;
        }

        if ($this->plugin->getServer()->getLevelByName($this->level->getName()) === null) {
            $this->forceCleanup();
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
            return;
        }

        $players = $this->getNearbyPlayers();
        if (empty($players)) {
            return;
        }

        if (!$this->spawned) {
            $this->spawnAllBlocks($players);
            $this->spawned = true;
        }

        $this->phase += 0.1;

        $this->updateChargingBlocks($players);
        $this->orbitBlocks($players);
        $this->updateBreakStates($players);

        if ($this->ticksRan % self::STRIKE_INTERVAL === 0) {
            $this->startCharge($players);
        }

        if ($this->ticksRan % 100 === 0) {
            $owner = $this->plugin->getServer()->getPlayerExact($this->ownerName);
            if ($owner !== null && $owner->isOnline()) {
                $pos = $owner->getPosition();
                foreach ($this->level->getEntities() as $e) {
                    if (!$e->isAlive() || $e->distance($pos) > $this->radius) continue;
                    if ($e instanceof Player && $e->getName() === $this->ownerName) continue;
                    if (!($e instanceof Player) && !($e instanceof NPCEntity) && !($e instanceof FactoryEntity)) continue;
                    
                    $e->attack(1.0, new EntityDamageEvent($e, EntityDamageEvent::CAUSE_MAGIC, 1.0));
                    $this->level->addParticle(new ExplodeParticle($e->getPosition()->add(0, 1, 0)));
                }
            }
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

    private function spawnOneBlock($players, $i) {
        if ($this->cleaned) return;

        $oldEid = $this->blockEntities[$i]["eid"];
        $this->exileAndRemove($oldEid);

        $newEid = BlockEffects::newEid();
        $this->blockEntities[$i]["eid"] = $newEid;

        $block = $this->blockEntities[$i];
        $a = $block["angle"];
        $orbitR = $this->radius * 0.7;
        $x = $this->cx + cos($a) * $orbitR;
        $y = $this->cy + $block["heightOffset"];
        $z = $this->cz + sin($a) * $orbitR;

        $this->blockEntities[$i]["x"] = $x;
        $this->blockEntities[$i]["y"] = $y;
        $this->blockEntities[$i]["z"] = $z;

        BlockEffects::sendSpawn($this->level, $newEid, $block["blockId"], $block["blockDamage"] ?? 0, $x, $y, $z);
    }

    private function spawnAllBlocks($players) {
        for ($i = 0; $i < $this->blockCount; $i++) {
            if ($this->breakStates[$i] > 0) continue;

            $block = $this->blockEntities[$i];
            $a = $block["angle"];
            $orbitR = $this->radius * 0.7;
            $x = $this->cx + cos($a) * $orbitR;
            $y = $this->cy + $block["heightOffset"];
            $z = $this->cz + sin($a) * $orbitR;

            $this->blockEntities[$i]["x"] = $x;
            $this->blockEntities[$i]["y"] = $y;
            $this->blockEntities[$i]["z"] = $z;

            BlockEffects::sendSpawn($this->level, $block["eid"], $block["blockId"], $block["blockDamage"] ?? 0, $x, $y, $z);
        }
    }

    private function orbitBlocks($players) {
        if ($this->cleaned) return;

        foreach ($this->blockEntities as $i => &$block) {
            if ($this->breakStates[$i] > 0) continue;
            if ($this->chargingBlocks[$i] !== null) continue;

            $block["angle"] += $block["orbitSpeed"];
            $a = $block["angle"];
            $orbitR = $this->radius * 0.7;
            $x = $this->cx + cos($a) * $orbitR;
            $y = $this->cy + $block["heightOffset"] + sin($this->phase + $i) * 0.5;
            $z = $this->cz + sin($a) * $orbitR;

            $block["x"] = $x;
            $block["y"] = $y;
            $block["z"] = $z;

            BlockEffects::sendMove($this->level, $block["eid"], $x, $y, $z);
        }
        unset($block);
    }

    private function startCharge($players) {
        if ($this->cleaned) return;

        $owner = $this->plugin->getServer()->getPlayerExact($this->ownerName);
        if ($owner === null || !$owner->isOnline()) return;

        $targets = [];

        foreach ($this->level->getEntities() as $entity) {
            if (!$entity->isAlive()) continue;

            $isValidTarget = false;

            if ($entity instanceof Player) {
                if ($entity->getName() === $this->ownerName) continue;
                if (!$this->plugin->canTargetPlayer($this->ownerName, $entity)) continue;
                $isValidTarget = true;
            } elseif ($entity instanceof NPCEntity) {
                $isValidTarget = true;
            } elseif ($entity instanceof FactoryEntity) {
                $isValidTarget = true;
            }

            if (!$isValidTarget) continue;

            $ePos = $entity->getPosition();
            $dx = $ePos->x - $this->cx;
            $dy = $ePos->y - $this->cy;
            $dz = $ePos->z - $this->cz;
            $dist = sqrt($dx * $dx + $dy * $dy + $dz * $dz);

            if ($dist <= $this->radius) {
                $targets[] = $entity;
            }
        }

        if (empty($targets)) return;

        $availableBlock = -1;
        for ($i = 0; $i < $this->blockCount; $i++) {
            if ($this->breakStates[$i] <= 0 && $this->chargingBlocks[$i] === null) {
                $availableBlock = $i;
                break;
            }
        }

        if ($availableBlock < 0) return;

        $target = $targets[mt_rand(0, count($targets) - 1)];
        $tPos = $target->getPosition();

        $block = $this->blockEntities[$availableBlock];
        $startX = $block["x"];
        $startY = $block["y"];
        $startZ = $block["z"];

        $this->chargingBlocks[$availableBlock] = [
            "target" => $target,
            "startX" => $startX,
            "startY" => $startY,
            "startZ" => $startZ,
            "targetX" => $tPos->x,
            "targetY" => $tPos->y + 1.0,
            "targetZ" => $tPos->z,
            "step" => 0
        ];
    }

    private function updateChargingBlocks($players) {
        if ($this->cleaned) return;

        foreach ($this->chargingBlocks as $i => &$charge) {
            if ($charge === null) continue;

            $charge["step"]++;
            $progress = min(1.0, $charge["step"] / self::CHARGE_STEPS);

            $target = $charge["target"];
            if ($target !== null && $target->isAlive() && !$target->closed) {
                $tPos = $target->getPosition();
                $charge["targetX"] = $tPos->x;
                $charge["targetY"] = $tPos->y + 1.0;
                $charge["targetZ"] = $tPos->z;
            }

            $x = $charge["startX"] + ($charge["targetX"] - $charge["startX"]) * $progress;
            $y = $charge["startY"] + ($charge["targetY"] - $charge["startY"]) * $progress;
            $z = $charge["startZ"] + ($charge["targetZ"] - $charge["startZ"]) * $progress;

            $this->blockEntities[$i]["x"] = $x;
            $this->blockEntities[$i]["y"] = $y;
            $this->blockEntities[$i]["z"] = $z;

            $block = $this->blockEntities[$i];
            BlockEffects::sendMove($this->level, $block["eid"], $x, $y, $z, $progress * 360, $progress * 180);

            $this->level->addParticle(new DustParticle(
                new Vector3($x, $y, $z),
                0, 200, 255
            ));

            if ($charge["step"] % 2 === 0) {
                $trailA = $progress * M_PI * 4;
                $trailR = 0.4 * (1.0 - $progress);
                $this->level->addParticle(new InstantEnchantParticle(new Vector3(
                    $x + cos($trailA) * $trailR,
                    $y + sin($trailA) * $trailR,
                    $z + sin($trailA) * $trailR
                )));
            }

            if ($progress < 0.3 && $charge["step"] % 3 === 0) {
                for ($g = 0; $g < 3; $g++) {
                    $ga = ($g / 3) * M_PI * 2 + $progress * M_PI * 8;
                    $this->level->addParticle(new MobSpellParticle(
                        new Vector3($x + cos($ga) * 0.6, $y + sin($ga) * 0.6, $z),
                        0, 200, 255
                    ));
                }
            }

            if ($progress >= 1.0) {
                $this->hitTarget($players, $i, $charge["target"]);
                $this->chargingBlocks[$i] = null;
            }
        }
        unset($charge);
    }

    private function hitTarget($players, $blockIndex, $target) {
        if ($this->cleaned) return;

        $block = $this->blockEntities[$blockIndex];
        $tx = $block["x"];
        $ty = $block["y"];
        $tz = $block["z"];

        if ($target !== null && $target->isAlive() && !$target->closed) {
            $damage = self::HIT_DAMAGE_BASE;

            if ($target instanceof Player) {
                $statsPlugin = $this->plugin->getStatsPlugin();
                if ($statsPlugin !== null && $statsPlugin->isEnabled()) {
                    $sm = $statsPlugin->getStatManager();
                    if ($sm !== null && $sm->isLoaded($target)) {
                        $sp = $sm->getStatPlayer($target);
                        if ($sp !== null) {
                            $defense = $sp->getStat("defense");
                            $scaler = $statsPlugin->getStatScaler();
                            if ($scaler !== null) {
                                $damage = $scaler->calculatePvPDamage($damage, $defense);
                            }
                        }
                    }
                }
            }

            $target->attack($damage, new EntityDamageEvent(
                $target,
                EntityDamageEvent::CAUSE_MAGIC,
                $damage
            ));

            if ($target instanceof Player) {
                $target->sendTip(TextFormat::AQUA . "ROOM");
            }
        }

        for ($i = 0; $i < 10; $i++) {
            $a = ($i / 10) * M_PI * 2;
            $this->level->addParticle(new DustParticle(new Vector3(
                $tx + cos($a) * 1.5,
                $ty,
                $tz + sin($a) * 1.5
            ), 0, 200, 255));
        }

        for ($p = 0; $p < 10; $p++) {
            $this->level->addParticle(new CriticalParticle(new Vector3(
                $tx + (mt_rand(-12, 12) / 10),
                $ty + (mt_rand(-12, 12) / 10),
                $tz + (mt_rand(-12, 12) / 10)
            )));
        }

        for ($p = 0; $p < 6; $p++) {
            $this->level->addParticle(new SmokeParticle(new Vector3(
                $tx + (mt_rand(-8, 8) / 10),
                $ty + (mt_rand(-8, 8) / 10),
                $tz + (mt_rand(-8, 8) / 10)
            )));
        }

        for ($p = 0; $p < 4; $p++) {
            $this->level->addParticle(new InstantEnchantParticle(new Vector3(
                $tx + (mt_rand(-6, 6) / 10),
                $ty + (mt_rand(0, 12) / 10),
                $tz + (mt_rand(-6, 6) / 10)
            )));
        }

        $this->level->addParticle(new ExplodeParticle(new Vector3($tx, $ty, $tz)));
        $this->level->addSound(new PopSound(new Vector3($tx, $ty, $tz)));
        $this->level->addSound(new ClickSound(new Vector3($tx, $ty, $tz)));

        $this->exileAndRemove($block["eid"]);
        $this->blockEntities[$blockIndex]["eid"] = BlockEffects::newEid();

        $this->breakStates[$blockIndex] = 50;
    }

    private function updateBreakStates($players) {
        if ($this->cleaned) return;

        foreach ($this->breakStates as $i => &$state) {
            if ($state <= 0) continue;

            $state--;

            if ($state === 10) {
                $block = $this->blockEntities[$i];
                $a = $block["angle"];
                $orbitR = $this->radius * 0.7;
                $rx = $this->cx + cos($a) * $orbitR;
                $ry = $this->cy + $block["heightOffset"];
                $rz = $this->cz + sin($a) * $orbitR;
                for ($p = 0; $p < 4; $p++) {
                    $this->level->addParticle(new EnchantParticle(new Vector3(
                        $rx + (mt_rand(-5, 5) / 10),
                        $ry + (mt_rand(-5, 5) / 10),
                        $rz + (mt_rand(-5, 5) / 10)
                    )));
                }
            }

            if ($state <= 0) {
                $this->blockEntities[$i]["angle"] += mt_rand(0, 628) / 100;
                $this->spawnOneBlock($players, $i);

                $block = $this->blockEntities[$i];
                for ($p = 0; $p < 5; $p++) {
                    $ra = ($p / 5) * M_PI * 2;
                    $this->level->addParticle(new DustParticle(new Vector3(
                        $block["x"] + cos($ra) * 0.8,
                        $block["y"],
                        $block["z"] + sin($ra) * 0.8
                    ), 0, 200, 255));
                }
                $this->level->addParticle(new InstantEnchantParticle(new Vector3(
                    $block["x"], $block["y"] + 0.5, $block["z"]
                )));
                $this->level->addSound(new PopSound(new Vector3(
                    $block["x"], $block["y"], $block["z"]
                )));
            }
        }
        unset($state);
    }
}