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
use pocketmine\level\particle\FlameParticle;
use pocketmine\level\particle\EntityFlameParticle;
use pocketmine\level\particle\SmokeParticle;
use pocketmine\level\particle\LavaDripParticle;
use pocketmine\level\particle\CriticalParticle;
use pocketmine\level\particle\HugeExplodeParticle;
use pocketmine\level\particle\LargeExplodeParticle;
use pocketmine\level\particle\PortalParticle;
use pocketmine\level\particle\EnchantmentTableParticle;
use pocketmine\block\Block;
use pocketmine\level\sound\ExplodeSound;
use pocketmine\level\sound\BlazeShootSound;
use pocketmine\level\sound\GhastShootSound;
use pocketmine\level\sound\AnvilUseSound;
use OnePiece\Devil\BlockEffects;
use OnePiece\NPC\NPCEntity;
use OnePieceTrades\Factory\FactoryEntity;

class UoUo extends BaseFruit {

    public function getId() { return "uo_uo"; }
    public function getDisplayName() { return "Dragon-Dragon Fruit"; }
    public function getDescription() { return "Dragon Fruit - Kaido, the strongest creature. Unstoppable power."; }
    public function getType() { return "zoan"; }
    public function getRarity() { return "mythical"; }

    public function getAbilityNames() {
        return ["ability1" => "Bolo Breath", "ability2" => "Kosanze Ragnaraku"];
    }

    public function getAbilityCooldowns() {
        return ["ability1" => 8.0, "ability2" => 20.0];
    }

    public function useAbility(Player $player, $ability) {
        switch ($ability) {
            case "ability1": return $this->boloBreath($player);
            case "ability2": return $this->kosanzeRagnaraku($player);
        }
        return 0;
    }

    private function boloBreath(Player $player) {
        $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
        $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

        $mult   = min(1.5, $this->getHakiMultiplier($player));
        $damage = min(10.5, 6.5 * $mult);
        $range = 14.0;

        $player->sendTip(TextFormat::RED . TextFormat::BOLD . "BOLO BREATH!");

        $pos = $player->getPosition();
        $dir = $player->getDirectionVector();
        $lv = $player->getLevel();

        $lv->addSound(new GhastShootSound($pos));

        $task = new BoloBreathTask(
            $this->plugin,
            $lv,
            $pos->x,
            $pos->y + 1.2,
            $pos->z,
            $dir->x,
            $dir->y,
            $dir->z,
            $range,
            $damage,
            $player->getName(),
            $toggle
        );
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($task, 2);

        return $this->getAbilityCooldowns()["ability1"];
    }

    private function kosanzeRagnaraku(Player $player) {
        $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
        $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

        $mult   = min(1.5, $this->getHakiMultiplier($player));
        $damage = min(9, 6.0 * $mult);
        $radius = 14.0;

        $player->sendTip(TextFormat::DARK_RED . TextFormat::BOLD . "KOSANZE RAGNARAKU!");

        $res = Effect::getEffect(Effect::DAMAGE_RESISTANCE);
        $res->setAmplifier(4);
        $res->setDuration(120);
        $res->setVisible(false);
        $player->addEffect($res);

        $player->setMotion(new Vector3(0, 2.5, 0));

        $pos = $player->getPosition();
        $lv = $player->getLevel();

        $lv->addSound(new GhastShootSound($pos));

        $task = new KosanzeRagnarakuTask(
            $this->plugin,
            $lv,
            $player,
            $pos->x,
            $pos->y,
            $pos->z,
            $radius,
            $damage,
            $toggle
        );
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($task, 2);

        return $this->getAbilityCooldowns()["ability2"];
    }

    public function onEquip(Player $player) {
        $player->sendMessage(TextFormat::DARK_RED . "=== Uo Uo no Mi: Seiryu ===");
        $player->sendMessage(TextFormat::WHITE . "The Power of the Strongest Creature");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::RED . "[Tap]: " . TextFormat::WHITE . "Bolo Breath");
        $player->sendMessage(TextFormat::GRAY . "  Dragon fire breath incinerates forward");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::RED . "[Sneak+Tap]: " . TextFormat::WHITE . "Kosanze Ragnaraku");
        $player->sendMessage(TextFormat::GRAY . "  Leap and slam with devastating force");
        $player->sendMessage(TextFormat::DARK_RED . "===========================");
    }

    public function onUnequip(Player $player) {
        $player->sendMessage(TextFormat::GRAY . "Dragon powers recede...");
    }
}

class BoloBreathTask extends Task {

    private $plugin;
    private $level;
    private $startX;
    private $startY;
    private $startZ;
    private $dirX;
    private $dirY;
    private $dirZ;
    private $range;
    private $damage;
    private $ownerName;
    private $toggle;

    private $ticksRan = 0;
    private $maxTicks = 28;
    private $cleaned = false;

    private $fireBlocks = [];
    private $hitEntities = [];
    private $currentDist = 0.0;

    const VIEW_RANGE = 40;

    public function __construct($plugin, Level $level, $sx, $sy, $sz, $dx, $dy, $dz, $range, $damage, $ownerName, $toggle) {
        $this->plugin = $plugin;
        $this->level = $level;
        $this->startX = (float)$sx;
        $this->startY = (float)$sy;
        $this->startZ = (float)$sz;
        $this->dirX = (float)$dx;
        $this->dirY = (float)$dy * 0.3;
        $this->dirZ = (float)$dz;
        $this->range = (float)$range;
        $this->damage = $damage;
        $this->ownerName = $ownerName;
        $this->toggle = $toggle;

        $this->spawnAllBlocks();
    }

    private function spawnAllBlocks() {
        for ($i = 0; $i < 3; $i++) {
            $eid = BlockEffects::newEid();
            $offset = ($i - 1) * 0.6;
            $this->fireBlocks[$eid] = [
                "eid" => $eid, "perpOffset" => $offset,
                "x" => $this->startX, "y" => $this->startY, "z" => $this->startZ
            ];
            BlockEffects::sendSpawn($this->level, $eid, 87, 0, $this->startX, $this->startY, $this->startZ);
        }
    }

    public function onRun($currentTick) {
        if ($this->cleaned) return;

        $this->ticksRan++;

        if ($this->ticksRan > $this->maxTicks || $this->currentDist >= $this->range) {
            $this->forceCleanup();
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
            return;
        }

        $this->currentDist += 1.5;

        $centerX = $this->startX + $this->dirX * $this->currentDist;
        $centerY = $this->startY + $this->dirY * $this->currentDist;
        $centerZ = $this->startZ + $this->dirZ * $this->currentDist;

        $spread = 0.6 + ($this->currentDist / $this->range) * 2.5;

        $perpX = -$this->dirZ;
        $perpZ = $this->dirX;

        foreach ($this->fireBlocks as $eid => &$b) {
            $offset = $b["perpOffset"] * ($spread / 0.6);
            $b["x"] = $centerX + $perpX * $offset;
            $b["y"] = $centerY + (mt_rand(-5, 5) / 20);
            $b["z"] = $centerZ + $perpZ * $offset;
            BlockEffects::sendMove($this->level, $eid, $b["x"], $b["y"], $b["z"], $this->ticksRan * 25, $this->ticksRan * 20);
        }
        unset($b);

        for ($i = 0; $i < 12; $i++) {
            $angle = ($i / 12) * M_PI * 2 + ($this->ticksRan * 0.3);
            $r = $spread * 0.8;
            $ox = cos($angle) * $r;
            $oy = sin($angle) * $r * 0.5;
            $oz = sin($angle) * $r;
            $this->level->addParticle(new FlameParticle(new Vector3($centerX + $ox, $centerY + $oy, $centerZ + $oz)));
        }

        for ($i = 0; $i < 8; $i++) {
            $angle = ($i / 8) * M_PI * 2 - ($this->ticksRan * 0.4);
            $r = $spread * 0.5;
            $ox = cos($angle) * $r;
            $oy = sin($angle) * $r * 0.3 + 0.2;
            $oz = sin($angle) * $r;
            $this->level->addParticle(new FlameParticle(new Vector3($centerX + $ox, $centerY + $oy, $centerZ + $oz)));
        }

        for ($i = 0; $i < 6; $i++) {
            $ox = (mt_rand(-20, 20) / 10) * $spread;
            $oy = mt_rand(-5, 10) / 10;
            $oz = (mt_rand(-20, 20) / 10) * $spread;
            $this->level->addParticle(new EntityFlameParticle(new Vector3($centerX + $ox, $centerY + $oy, $centerZ + $oz)));
        }

        for ($layer = 0; $layer < 3; $layer++) {
            $layerSpread = $spread * (0.4 + $layer * 0.3);
            for ($i = 0; $i < 4; $i++) {
                $angle = ($i / 4) * M_PI * 2 + ($this->ticksRan * 0.2) + ($layer * M_PI / 6);
                $ox = cos($angle) * $layerSpread;
                $oz = sin($angle) * $layerSpread;
                $this->level->addParticle(new DustParticle(
                    new Vector3($centerX + $ox, $centerY + ($layer * 0.3), $centerZ + $oz),
                    255, 100 - ($layer * 30), 0
                ));
            }
        }

        for ($i = 0; $i < 4; $i++) {
            $angle = ($i / 4) * M_PI * 2 + ($this->ticksRan * 0.5);
            $r = $spread * 1.2;
            $this->level->addParticle(new DustParticle(
                new Vector3($centerX + cos($angle) * $r, $centerY + 0.5, $centerZ + sin($angle) * $r),
                128, 0, 255
            ));
        }

        for ($i = 0; $i < 3; $i++) {
            $angle = ($i / 3) * M_PI * 2 - ($this->ticksRan * 0.6);
            $r = $spread * 1.0;
            $this->level->addParticle(new DustParticle(
                new Vector3($centerX + cos($angle) * $r, $centerY + 0.8, $centerZ + sin($angle) * $r),
                60, 0, 180
            ));
        }

        for ($i = 0; $i < 4; $i++) {
            $ox = (mt_rand(-15, 15) / 10) * $spread;
            $oz = (mt_rand(-15, 15) / 10) * $spread;
            $this->level->addParticle(new SmokeParticle(new Vector3($centerX + $ox, $centerY + 1.0, $centerZ + $oz)));
        }

        for ($i = 0; $i < 3; $i++) {
            $ox = (mt_rand(-18, 18) / 10) * $spread;
            $oz = (mt_rand(-18, 18) / 10) * $spread;
            $this->level->addParticle(new PortalParticle(new Vector3($centerX + $ox, $centerY + 0.5, $centerZ + $oz)));
        }

        for ($i = 0; $i < 3; $i++) {
            $ox = (mt_rand(-12, 12) / 10) * $spread;
            $oz = (mt_rand(-12, 12) / 10) * $spread;
            $this->level->addParticle(new LavaDripParticle(new Vector3($centerX + $ox, $centerY - 0.2, $centerZ + $oz)));
        }

        $prevX = $this->startX + $this->dirX * ($this->currentDist - 1.5);
        $prevY = $this->startY + $this->dirY * ($this->currentDist - 1.5);
        $prevZ = $this->startZ + $this->dirZ * ($this->currentDist - 1.5);

        for ($i = 0; $i < 5; $i++) {
            $t = $i / 5;
            $tx = $prevX + ($centerX - $prevX) * $t;
            $ty = $prevY + ($centerY - $prevY) * $t;
            $tz = $prevZ + ($centerZ - $prevZ) * $t;

            $angle = ($this->ticksRan * 0.8) + ($t * M_PI * 2);
            $helixR = $spread * 0.3 * (1 - $t);
            $hx = cos($angle) * $helixR;
            $hz = sin($angle) * $helixR;

            $this->level->addParticle(new FlameParticle(new Vector3($tx + $hx, $ty, $tz + $hz)));
        }

        if ($this->ticksRan % 2 == 0) {
            $this->level->addSound(new BlazeShootSound(new Vector3($centerX, $centerY, $centerZ)));
        }

        $this->checkHits($centerX, $centerY, $centerZ, $spread + 2.0);
    }

    private function checkHits($cx, $cy, $cz, $hitRadius) {
        $owner = $this->plugin->getServer()->getPlayerExact($this->ownerName);

        foreach ($this->level->getEntities() as $entity) {
            if (!$entity->isAlive()) continue;
            if ($entity->closed) continue;

            $entityId = $entity->getId();
            if (isset($this->hitEntities[$entityId])) continue;

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
                $isValidTarget = true;
            } elseif ($entity instanceof FactoryEntity) {
                $isValidTarget = true;
            }

            if (!$isValidTarget) continue;

            $dx = $entity->x - $cx;
            $dy = $entity->y - $cy;
            $dz = $entity->z - $cz;
            $dist = sqrt($dx * $dx + $dy * $dy + $dz * $dz);

            if ($dist <= $hitRadius) {
                if (!($entity instanceof FactoryEntity)) {
                    $this->hitEntities[$entityId] = true;
                }

                if ($owner !== null) {
                    $ev = new EntityDamageByEntityEvent($owner, $entity, EntityDamageEvent::CAUSE_FIRE, $this->damage);
                    $entity->attack($this->damage, $ev);
                } else {
                    $ev = new EntityDamageEvent($entity, EntityDamageEvent::CAUSE_FIRE, $this->damage);
                    $entity->attack($this->damage, $ev);
                }

                BaseFruit::staticSafeSetOnFire($owner, $entity, 7);
                BaseFruit::staticSafeSetMotion($owner, $entity, new Vector3($this->dirX * 1.2, 0.5, $this->dirZ * 1.2));

                if ($entity instanceof Player) {
                    $entity->sendTip(TextFormat::RED . TextFormat::BOLD . "BOLO BREATH!");

                    $weak = Effect::getEffect(Effect::WEAKNESS);
                    $weak->setAmplifier(1);
                    $weak->setDuration(60);
                    $weak->setVisible(false);
                    BaseFruit::staticSafeAddEffect($owner, $entity, $weak);
                }

                for ($ring = 0; $ring < 3; $ring++) {
                    for ($i = 0; $i < 8; $i++) {
                        $angle = ($i / 8) * M_PI * 2;
                        $r = 0.5 + ($ring * 0.4);
                        $this->level->addParticle(new FlameParticle(new Vector3(
                            $entity->x + cos($angle) * $r,
                            $entity->y + 1 + ($ring * 0.3),
                            $entity->z + sin($angle) * $r
                        )));
                    }
                }

                for ($i = 0; $i < 6; $i++) {
                    $angle = ($i / 6) * M_PI * 2;
                    $this->level->addParticle(new DustParticle(
                        new Vector3(
                            $entity->x + cos($angle) * 1.2,
                            $entity->y + 1.5,
                            $entity->z + sin($angle) * 1.2
                        ),
                        160, 32, 240
                    ));
                }

                for ($i = 0; $i < 4; $i++) {
                    $this->level->addParticle(new CriticalParticle(new Vector3(
                        $entity->x + (mt_rand(-8, 8) / 10),
                        $entity->y + 1.8,
                        $entity->z + (mt_rand(-8, 8) / 10)
                    )));
                }

                for ($i = 0; $i < 4; $i++) {
                    $this->level->addParticle(new PortalParticle(new Vector3(
                        $entity->x + (mt_rand(-6, 6) / 10),
                        $entity->y + mt_rand(5, 15) / 10,
                        $entity->z + (mt_rand(-6, 6) / 10)
                    )));
                }

                $this->level->addParticle(new HugeExplodeParticle(new Vector3($entity->x, $entity->y + 1, $entity->z)));
            }
        }
    }

    public function forceCleanup() {
        if ($this->cleaned) return;
        $this->cleaned = true;
        BlockEffects::voidAndRemove($this->plugin, $this->level, array_keys($this->fireBlocks));
        $this->fireBlocks = [];
    }
}

class KosanzeRagnarakuTask extends Task {

    private $plugin;
    private $level;
    private $player;
    private $ownerName;
    private $startX;
    private $startY;
    private $startZ;
    private $radius;
    private $damage;
    private $toggle;

    private $phase = 0;
    private $phaseTick = 0;
    private $ticksRan = 0;
    private $maxTicks = 120;
    private $cleaned = false;

    private $slamX;
    private $slamY;
    private $slamZ;

    private $allBlocks = [];
    private $hitEntities = [];
    private $stunnedTargets = [];
    private $shockwaveRadius = 0.0;
    private $scannedBlocks = [];

    const VIEW_RANGE = 40;
    const STUN_DURATION = 100;

    public function __construct($plugin, Level $level, Player $player, $sx, $sy, $sz, $radius, $damage, $toggle) {
        $this->plugin = $plugin;
        $this->level = $level;
        $this->player = $player;
        $this->ownerName = $player->getName();
        $this->startX = (float)$sx;
        $this->startY = (float)$sy;
        $this->startZ = (float)$sz;
        $this->slamX = (float)$sx;
        $this->slamY = (float)$sy;
        $this->slamZ = (float)$sz;
        $this->radius = (float)$radius;
        $this->damage = $damage;
        $this->toggle = $toggle;

        $this->spawnAllBlocks();
    }

    private function scanGroundBlocks($cx, $cy, $cz) {
        $found = [];
        $skip = [0, 8, 9, 10, 11, 26, 30, 31, 32, 37, 38, 39, 40, 50, 51, 55, 59, 63, 64, 65, 68, 69, 70, 71, 72, 75, 76, 77, 83, 90, 93, 94, 96, 104, 105, 106, 115, 127, 131, 132, 141, 142, 143, 144, 147, 148, 149, 150, 154, 157, 167, 171, 175, 176, 177, 178, 183, 184, 185, 186, 187, 193, 194, 195, 196, 197];

        for ($bx = -3; $bx <= 3; $bx++) {
            for ($bz = -3; $bz <= 3; $bz++) {
                for ($by = -3; $by <= 1; $by++) {
                    $block = $this->level->getBlock(new Vector3((int)($cx + $bx), (int)($cy + $by), (int)($cz + $bz)));
                    $id = $block->getId();
                    $dmg = $block->getDamage();

                    if (in_array($id, $skip)) continue;

                    $key = $id . ":" . $dmg;
                    if (!isset($found[$key])) {
                        $found[$key] = ["id" => $id, "damage" => $dmg];
                    }

                    if (count($found) >= 4) {
                        return array_values($found);
                    }
                }
            }
        }

        if (empty($found)) {
            $found[] = ["id" => 4, "damage" => 0];
            $found[] = ["id" => 1, "damage" => 0];
            $found[] = ["id" => 3, "damage" => 0];
        }

        return array_values($found);
    }

    private function spawnAllBlocks() {
        for ($i = 0; $i < 4; $i++) {
            $angle = ($i / 4) * M_PI * 2;
            $eid = BlockEffects::newEid();
            $this->allBlocks[$eid] = [
                "eid" => $eid, "type" => "debris", "index" => $i, "angle" => $angle,
                "x" => 0.0, "y" => -100.0, "z" => 0.0,
                "vx" => 0.0, "vy" => 0.0, "vz" => 0.0,
                "rotSpeed" => 40 + mt_rand(0, 20), "blockId" => 1, "blockDamage" => 0, "active" => false
            ];
            BlockEffects::sendSpawn($this->level, $eid, 1, 0, 0.0, -100.0, 0.0);
        }

        for ($i = 0; $i < 4; $i++) {
            $angle = ($i / 4) * M_PI * 2;
            $eid = BlockEffects::newEid();
            $this->allBlocks[$eid] = [
                "eid" => $eid, "type" => "orb", "index" => $i, "angle" => $angle,
                "x" => 0.0, "y" => -100.0, "z" => 0.0,
                "vx" => 0.0, "vz" => 0.0, "prevX" => 0.0, "prevY" => 0.0, "prevZ" => 0.0,
                "orbiting" => true, "active" => false
            ];
            BlockEffects::sendSpawn($this->level, $eid, 89, 0, 0.0, -100.0, 0.0);
        }
    }

    public function onRun($currentTick) {
        if ($this->cleaned) return;

        $this->ticksRan++;
        $this->phaseTick++;

        if ($this->ticksRan > $this->maxTicks) {
            $this->forceCleanup();
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
            return;
        }

        $this->applyStunToAll();

        switch ($this->phase) {
            case 0:
                $this->phaseRising();
                break;
            case 1:
                $this->phaseHangTime();
                break;
            case 2:
                $this->phaseSlamDown();
                break;
            case 3:
                $this->phaseImpact();
                break;
            case 4:
                $this->phaseOrbs();
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
            "lockX"  => $entity->x,
            "lockY"  => $entity->y,
            "lockZ"  => $entity->z
        ];

        if ($entity instanceof Player) {
            $slow = Effect::getEffect(Effect::SLOWNESS);
            $slow->setAmplifier(255);
            $slow->setDuration(self::STUN_DURATION);
            $slow->setVisible(false);
            BaseFruit::staticSafeAddEffect($this->plugin->getServer()->getPlayerExact($this->ownerName), $entity, $slow);

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

    private function phaseRising() {
        if ($this->player === null || !$this->player->isOnline()) {
            $this->forceCleanup();
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
            return;
        }

        $pos = $this->player->getPosition();

        for ($helix = 0; $helix < 2; $helix++) {
            for ($i = 0; $i < 6; $i++) {
                $angle = ($this->ticksRan * 0.8) + ($i * M_PI / 3) + ($helix * M_PI);
                $r = 0.8 + ($this->phaseTick * 0.15);
                $y = ($i / 6) * 2.0;
                $this->level->addParticle(new FlameParticle(new Vector3(
                    $pos->x + cos($angle) * $r,
                    $pos->y + $y,
                    $pos->z + sin($angle) * $r
                )));
            }
        }

        for ($i = 0; $i < 8; $i++) {
            $angle = ($this->ticksRan * 0.6) + ($i * M_PI / 4);
            $r = 1.2 + sin($this->ticksRan * 0.4) * 0.3;
            $this->level->addParticle(new DustParticle(
                new Vector3($pos->x + cos($angle) * $r, $pos->y + 0.5, $pos->z + sin($angle) * $r),
                128, 0, 255
            ));
        }

        for ($i = 0; $i < 6; $i++) {
            $angle = -($this->ticksRan * 0.5) + ($i * M_PI / 3);
            $r = 1.5;
            $this->level->addParticle(new DustParticle(
                new Vector3($pos->x + cos($angle) * $r, $pos->y + 1.0, $pos->z + sin($angle) * $r),
                60, 0, 180
            ));
        }

        for ($i = 0; $i < 4; $i++) {
            $this->level->addParticle(new EntityFlameParticle(new Vector3(
                $pos->x + (mt_rand(-10, 10) / 10),
                $pos->y + (mt_rand(0, 20) / 10),
                $pos->z + (mt_rand(-10, 10) / 10)
            )));
        }

        for ($i = 0; $i < 3; $i++) {
            $this->level->addParticle(new PortalParticle(new Vector3(
                $pos->x + (mt_rand(-12, 12) / 10),
                $pos->y + (mt_rand(5, 25) / 10),
                $pos->z + (mt_rand(-12, 12) / 10)
            )));
        }

        for ($i = 0; $i < 2; $i++) {
            $this->level->addParticle(new SmokeParticle(new Vector3(
                $pos->x + (mt_rand(-8, 8) / 10),
                $pos->y - 0.3,
                $pos->z + (mt_rand(-8, 8) / 10)
            )));
        }

        if ($this->phaseTick >= 8) {
            $this->phase = 1;
            $this->phaseTick = 0;
        }
    }

    private function phaseHangTime() {
        if ($this->player === null || !$this->player->isOnline()) {
            $this->forceCleanup();
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
            return;
        }

        $pos = $this->player->getPosition();

        $this->player->setMotion(new Vector3(0, 0.08, 0));

        for ($ring = 0; $ring < 3; $ring++) {
            $ringR = 1.0 + ($ring * 0.5);
            $ringSpeed = 0.7 - ($ring * 0.15);
            $particleCount = 8 + ($ring * 2);

            for ($i = 0; $i < $particleCount; $i++) {
                $angle = ($this->ticksRan * $ringSpeed) + ($i * M_PI * 2 / $particleCount);
                $yOffset = sin($this->ticksRan * 0.3 + $ring) * 0.2;
                $this->level->addParticle(new FlameParticle(new Vector3(
                    $pos->x + cos($angle) * $ringR,
                    $pos->y + $yOffset + ($ring * 0.2),
                    $pos->z + sin($angle) * $ringR
                )));
            }
        }

        for ($i = 0; $i < 6; $i++) {
            $angle = ($this->ticksRan * 0.5) + ($i * M_PI / 3);
            $r = 2.0 + sin($this->ticksRan * 0.2) * 0.3;
            $this->level->addParticle(new DustParticle(
                new Vector3($pos->x + cos($angle) * $r, $pos->y + 0.3, $pos->z + sin($angle) * $r),
                160, 0, 255
            ));
        }

        for ($i = 0; $i < 4; $i++) {
            $angle = -($this->ticksRan * 0.4) + ($i * M_PI / 2);
            $r = 2.5;
            $this->level->addParticle(new DustParticle(
                new Vector3($pos->x + cos($angle) * $r, $pos->y - 0.2, $pos->z + sin($angle) * $r),
                40, 0, 140
            ));
        }

        for ($i = 0; $i < 4; $i++) {
            $this->level->addParticle(new LavaDripParticle(new Vector3(
                $pos->x + (mt_rand(-15, 15) / 10),
                $pos->y - 0.5,
                $pos->z + (mt_rand(-15, 15) / 10)
            )));
        }

        for ($i = 0; $i < 3; $i++) {
            $this->level->addParticle(new PortalParticle(new Vector3(
                $pos->x + (mt_rand(-18, 18) / 10),
                $pos->y + (mt_rand(-5, 10) / 10),
                $pos->z + (mt_rand(-18, 18) / 10)
            )));
        }

        for ($i = 0; $i < 2; $i++) {
            $this->level->addParticle(new EnchantmentTableParticle(new Vector3(
                $pos->x + (mt_rand(-20, 20) / 10),
                $pos->y - 1,
                $pos->z + (mt_rand(-20, 20) / 10)
            )));
        }

        if ($this->phaseTick >= 10) {
            $this->phase = 2;
            $this->phaseTick = 0;
            $this->player->setMotion(new Vector3(0, -2.5, 0));
            $this->player->sendTip(TextFormat::DARK_RED . TextFormat::BOLD . "RAGNARAKU!");
            $this->level->addSound(new GhastShootSound($pos));
        }
    }

    private function phaseSlamDown() {
        if ($this->player === null || !$this->player->isOnline()) {
            $this->forceCleanup();
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
            return;
        }

        $pos = $this->player->getPosition();

        for ($i = 0; $i < 6; $i++) {
            $this->level->addParticle(new FlameParticle(new Vector3(
                $pos->x + (mt_rand(-5, 5) / 10),
                $pos->y + 1 + ($i * 0.4),
                $pos->z + (mt_rand(-5, 5) / 10)
            )));
        }

        for ($i = 0; $i < 4; $i++) {
            $angle = ($this->ticksRan * 1.2) + ($i * M_PI / 2);
            $r = 0.5;
            $this->level->addParticle(new FlameParticle(new Vector3(
                $pos->x + cos($angle) * $r,
                $pos->y + 1.5,
                $pos->z + sin($angle) * $r
            )));
        }

        for ($i = 0; $i < 4; $i++) {
            $this->level->addParticle(new DustParticle(
                new Vector3(
                    $pos->x + (mt_rand(-6, 6) / 10),
                    $pos->y + 1.5 + ($i * 0.3),
                    $pos->z + (mt_rand(-6, 6) / 10)
                ),
                180, 0, 255
            ));
        }

        for ($i = 0; $i < 2; $i++) {
            $this->level->addParticle(new DustParticle(
                new Vector3($pos->x + (mt_rand(-4, 4) / 10), $pos->y + 2.5, $pos->z + (mt_rand(-4, 4) / 10)),
                60, 0, 180
            ));
        }

        $this->level->addParticle(new SmokeParticle(new Vector3($pos->x, $pos->y + 2.5, $pos->z)));
        $this->level->addParticle(new PortalParticle(new Vector3($pos->x, $pos->y + 1.8, $pos->z)));

        if ($this->player->isOnGround() || $this->phaseTick >= 12) {
            $this->slamX = $pos->x;
            $this->slamY = $pos->y;
            $this->slamZ = $pos->z;

            $this->scannedBlocks = $this->scanGroundBlocks($this->slamX, $this->slamY, $this->slamZ);

            $this->phase = 3;
            $this->phaseTick = 0;
            $this->shockwaveRadius = 1.0;

            $this->activateDebris();
            $this->spawnImpactVFX();
            $this->dealImpactDamage();

            $this->level->addSound(new ExplodeSound(new Vector3($this->slamX, $this->slamY, $this->slamZ)));
            $this->level->addSound(new AnvilUseSound(new Vector3($this->slamX, $this->slamY, $this->slamZ)));
        }
    }

    private function spawnImpactVFX() {
        $this->level->addParticle(new HugeExplodeParticle(new Vector3($this->slamX, $this->slamY + 0.5, $this->slamZ)));
        $this->level->addParticle(new HugeExplodeParticle(new Vector3($this->slamX, $this->slamY + 1.5, $this->slamZ)));

        for ($ring = 0; $ring < 4; $ring++) {
            $ringR = 1.5 + ($ring * 1.2);
            $particles = 12 + ($ring * 4);
            for ($i = 0; $i < $particles; $i++) {
                $angle = ($i / $particles) * M_PI * 2;
                $this->level->addParticle(new ExplodeParticle(new Vector3(
                    $this->slamX + cos($angle) * $ringR,
                    $this->slamY + 0.3 + ($ring * 0.1),
                    $this->slamZ + sin($angle) * $ringR
                )));
            }
        }

        for ($i = 0; $i < 30; $i++) {
            $angle = mt_rand(0, 628) / 100;
            $dist = mt_rand(5, 40) / 10;
            $height = mt_rand(2, 25) / 10;
            $this->level->addParticle(new FlameParticle(new Vector3(
                $this->slamX + cos($angle) * $dist,
                $this->slamY + $height,
                $this->slamZ + sin($angle) * $dist
            )));
        }

        for ($i = 0; $i < 15; $i++) {
            $angle = mt_rand(0, 628) / 100;
            $dist = mt_rand(5, 35) / 10;
            $this->level->addParticle(new EntityFlameParticle(new Vector3(
                $this->slamX + cos($angle) * $dist,
                $this->slamY + 0.3,
                $this->slamZ + sin($angle) * $dist
            )));
        }

        for ($ring = 0; $ring < 3; $ring++) {
            $ringR = 2.0 + ($ring * 1.5);
            for ($i = 0; $i < 10; $i++) {
                $angle = ($i / 10) * M_PI * 2 + ($ring * M_PI / 10);
                $this->level->addParticle(new DustParticle(
                    new Vector3($this->slamX + cos($angle) * $ringR, $this->slamY + 0.5 + ($ring * 0.3), $this->slamZ + sin($angle) * $ringR),
                    160 - ($ring * 30), 0, 255
                ));
            }
        }

        for ($i = 0; $i < 12; $i++) {
            $angle = ($i / 12) * M_PI * 2;
            $d = 3.0;
            $this->level->addParticle(new DustParticle(
                new Vector3($this->slamX + cos($angle) * $d, $this->slamY + 1.0, $this->slamZ + sin($angle) * $d),
                40, 0, 140
            ));
        }

        for ($i = 0; $i < 10; $i++) {
            $angle = mt_rand(0, 628) / 100;
            $d = mt_rand(10, 35) / 10;
            $this->level->addParticle(new PortalParticle(new Vector3(
                $this->slamX + cos($angle) * $d,
                $this->slamY + mt_rand(3, 20) / 10,
                $this->slamZ + sin($angle) * $d
            )));
        }

        for ($i = 0; $i < 8; $i++) {
            $angle = ($i / 8) * M_PI * 2;
            $d = mt_rand(12, 28) / 10;
            $this->level->addParticle(new CriticalParticle(new Vector3(
                $this->slamX + cos($angle) * $d,
                $this->slamY + mt_rand(8, 20) / 10,
                $this->slamZ + sin($angle) * $d
            )));
        }

        for ($spoke = 0; $spoke < 8; $spoke++) {
            $angle = ($spoke / 8) * M_PI * 2;
            for ($p = 0; $p < 4; $p++) {
                $dist = 1.0 + ($p * 0.8);
                $this->level->addParticle(new DustParticle(
                    new Vector3($this->slamX + cos($angle) * $dist, $this->slamY + 0.1, $this->slamZ + sin($angle) * $dist),
                    100, 50, 0
                ));
            }
        }
    }

    private function activateDebris() {
        foreach ($this->allBlocks as $eid => &$b) {
            if ($b["type"] === "debris") {
                $blockData = isset($this->scannedBlocks[$b["index"]]) ? $this->scannedBlocks[$b["index"]] : (isset($this->scannedBlocks[0]) ? $this->scannedBlocks[0] : ["id" => 1, "damage" => 0]);

                $b["active"] = true;
                $b["blockId"] = $blockData["id"];
                $b["blockDamage"] = $blockData["damage"];
                $b["x"] = $this->slamX + cos($b["angle"]) * 1.2;
                $b["y"] = $this->slamY + 0.5;
                $b["z"] = $this->slamZ + sin($b["angle"]) * 1.2;
                $b["vx"] = cos($b["angle"]) * (0.4 + mt_rand(0, 15) / 100);
                $b["vy"] = 0.7 + mt_rand(0, 30) / 100;
                $b["vz"] = sin($b["angle"]) * (0.4 + mt_rand(0, 15) / 100);

                BlockEffects::sendRemove($eid);
                BlockEffects::sendSpawn($this->level, $eid, $blockData["id"], $blockData["damage"] ?? 0, $b["x"], $b["y"], $b["z"]);
            }
        }
        unset($b);
    }

    private function dealImpactDamage() {
        $owner = $this->plugin->getServer()->getPlayerExact($this->ownerName);
        $impactRadius = 6.0;

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
                $isValidTarget = true;
            } elseif ($entity instanceof FactoryEntity) {
                $isValidTarget = true;
            }

            if (!$isValidTarget) continue;

            $dx = $entity->x - $this->slamX;
            $dz = $entity->z - $this->slamZ;
            $dist = sqrt($dx * $dx + $dz * $dz);

            if ($dist <= $impactRadius) {
                $this->hitEntities[$entity->getId()] = true;

                $scaledDamage = min(16.0, $this->damage * (1.0 - ($dist / $impactRadius) * 0.2));

                if ($owner !== null) {
                    $ev = new EntityDamageByEntityEvent($owner, $entity, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $scaledDamage);
                    $entity->attack($scaledDamage, $ev);
                } else {
                    $ev = new EntityDamageEvent($entity, EntityDamageEvent::CAUSE_EXPLOSION, $scaledDamage);
                    $entity->attack($scaledDamage, $ev);
                }

                BaseFruit::staticSafeSetOnFire($owner, $entity, 6);

                $len = $dist > 0.5 ? $dist : 0.5;
                BaseFruit::staticSafeSetMotion($owner, $entity, new Vector3(($dx / $len) * 1.8, 0.7, ($dz / $len) * 1.8));

                $this->addStunnedTarget($entity);

                if ($entity instanceof Player) {
                    $nausea = Effect::getEffect(Effect::NAUSEA);
                    $nausea->setAmplifier(2);
                    $nausea->setDuration(80);
                    $nausea->setVisible(false);
                    BaseFruit::staticSafeAddEffect($owner, $entity, $nausea);

                    $slow = Effect::getEffect(Effect::SLOWNESS);
                    $slow->setAmplifier(2);
                    $slow->setDuration(60);
                    $slow->setVisible(false);
                    BaseFruit::staticSafeAddEffect($owner, $entity, $slow);

                    $entity->sendTip(TextFormat::DARK_RED . TextFormat::BOLD . "RAGNARAKU!");
                }

                for ($ring = 0; $ring < 2; $ring++) {
                    for ($i = 0; $i < 6; $i++) {
                        $angle = ($i / 6) * M_PI * 2;
                        $r = 0.6 + ($ring * 0.4);
                        $this->level->addParticle(new FlameParticle(new Vector3(
                            $entity->x + cos($angle) * $r,
                            $entity->y + 1 + ($ring * 0.4),
                            $entity->z + sin($angle) * $r
                        )));
                    }
                }

                for ($i = 0; $i < 5; $i++) {
                    $this->level->addParticle(new DustParticle(
                        new Vector3(
                            $entity->x + (mt_rand(-10, 10) / 10),
                            $entity->y + mt_rand(10, 20) / 10,
                            $entity->z + (mt_rand(-10, 10) / 10)
                        ),
                        160, 0, 255
                    ));
                }

                $this->level->addParticle(new HugeExplodeParticle(new Vector3($entity->x, $entity->y + 1, $entity->z)));
            }
        }
    }

    private function phaseImpact() {
        foreach ($this->allBlocks as $eid => &$b) {
            if ($b["type"] === "debris" && $b["active"]) {
                $b["vy"] -= 0.06;
                $b["x"] += $b["vx"];
                $b["y"] += $b["vy"];
                $b["z"] += $b["vz"];

                if ($b["y"] < $this->slamY - 0.5) {
                    $b["y"] = $this->slamY - 0.5;
                    $b["vy"] = 0;
                    $b["vx"] *= 0.4;
                    $b["vz"] *= 0.4;
                }

                BlockEffects::sendMove($this->level, $eid, $b["x"], $b["y"], $b["z"], $this->ticksRan * $b["rotSpeed"], $this->ticksRan * ($b["rotSpeed"] * 0.8));

                $this->level->addParticle(new FlameParticle(new Vector3($b["x"], $b["y"] + 0.3, $b["z"])));
                $this->level->addParticle(new FlameParticle(new Vector3($b["x"] + (mt_rand(-3, 3) / 10), $b["y"] + 0.5, $b["z"] + (mt_rand(-3, 3) / 10))));
                $this->level->addParticle(new EntityFlameParticle(new Vector3($b["x"], $b["y"], $b["z"])));
                $this->level->addParticle(new DustParticle(
                    new Vector3($b["x"], $b["y"] + 0.6, $b["z"]),
                    140, 0, 220
                ));

                if ($this->ticksRan % 2 == 0) {
                    $this->level->addParticle(new SmokeParticle(new Vector3($b["x"], $b["y"] + 0.7, $b["z"])));
                    $this->level->addParticle(new PortalParticle(new Vector3($b["x"], $b["y"] + 0.4, $b["z"])));
                }
            }
        }
        unset($b);

        $this->shockwaveRadius += 0.9;

        for ($layer = 0; $layer < 2; $layer++) {
            $layerR = $this->shockwaveRadius - ($layer * 0.5);
            if ($layerR < 0) continue;

            $particles = 10 + ($layer * 2);
            for ($i = 0; $i < $particles; $i++) {
                $angle = ($i / $particles) * M_PI * 2 + ($this->ticksRan * 0.15) + ($layer * M_PI / $particles);
                $this->level->addParticle(new FlameParticle(new Vector3(
                    $this->slamX + cos($angle) * $layerR,
                    $this->slamY + 0.3 + ($layer * 0.2),
                    $this->slamZ + sin($angle) * $layerR
                )));
            }
        }

        for ($i = 0; $i < 5; $i++) {
            $angle = ($i / 5) * M_PI * 2 + ($this->ticksRan * 0.2);
            $this->level->addParticle(new EntityFlameParticle(new Vector3(
                $this->slamX + cos($angle) * $this->shockwaveRadius,
                $this->slamY + 0.1,
                $this->slamZ + sin($angle) * $this->shockwaveRadius
            )));
        }

        for ($i = 0; $i < 6; $i++) {
            $angle = ($i / 6) * M_PI * 2 + ($this->ticksRan * 0.18);
            $this->level->addParticle(new DustParticle(
                new Vector3(
                    $this->slamX + cos($angle) * $this->shockwaveRadius,
                    $this->slamY + 0.6,
                    $this->slamZ + sin($angle) * $this->shockwaveRadius
                ),
                160, 0, 255
            ));
        }

        for ($i = 0; $i < 4; $i++) {
            $angle = ($i / 4) * M_PI * 2 - ($this->ticksRan * 0.15);
            $this->level->addParticle(new DustParticle(
                new Vector3(
                    $this->slamX + cos($angle) * ($this->shockwaveRadius * 0.8),
                    $this->slamY + 0.9,
                    $this->slamZ + sin($angle) * ($this->shockwaveRadius * 0.8)
                ),
                60, 0, 180
            ));
        }

        for ($i = 0; $i < 3; $i++) {
            $angle = mt_rand(0, 628) / 100;
            $this->level->addParticle(new PortalParticle(new Vector3(
                $this->slamX + cos($angle) * ($this->shockwaveRadius - 0.3),
                $this->slamY + 0.5,
                $this->slamZ + sin($angle) * ($this->shockwaveRadius - 0.3)
            )));
        }

        $this->checkShockwaveHits();

        if ($this->shockwaveRadius >= $this->radius) {
            $this->phase = 4;
            $this->phaseTick = 0;

            foreach ($this->allBlocks as $eid => &$b) {
                if ($b["type"] === "debris") {
                    $b["active"] = false;

                    for ($i = 0; $i < 4; $i++) {
                        $this->level->addParticle(new FlameParticle(new Vector3(
                            $b["x"] + (mt_rand(-5, 5) / 10),
                            $b["y"] + (mt_rand(0, 8) / 10),
                            $b["z"] + (mt_rand(-5, 5) / 10)
                        )));
                    }
                    $this->level->addParticle(new SmokeParticle(new Vector3($b["x"], $b["y"] + 0.5, $b["z"])));
                    $this->level->addParticle(new DustParticle(
                        new Vector3($b["x"], $b["y"] + 0.7, $b["z"]),
                        160, 0, 255
                    ));

                    $b["y"] = -100.0;
                    BlockEffects::sendMove($this->level, $eid, 0.0, -100.0, 0.0);
                }
            }
            unset($b);

            $this->activateOrbs();
            $this->level->addSound(new BlazeShootSound(new Vector3($this->slamX, $this->slamY, $this->slamZ)));
        }
    }

    private function checkShockwaveHits() {
        $owner = $this->plugin->getServer()->getPlayerExact($this->ownerName);
        $innerR = $this->shockwaveRadius - 2.5;
        $outerR = $this->shockwaveRadius + 1.5;

        foreach ($this->level->getEntities() as $entity) {
            if (!$entity->isAlive()) continue;
            if ($entity->closed) continue;

            $entityId = $entity->getId();
            if (isset($this->hitEntities[$entityId])) continue;

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
                $isValidTarget = true;
            } elseif ($entity instanceof FactoryEntity) {
                $isValidTarget = true;
            }

            if (!$isValidTarget) continue;

            $dx = $entity->x - $this->slamX;
            $dz = $entity->z - $this->slamZ;
            $dist = sqrt($dx * $dx + $dz * $dz);

            if ($dist >= $innerR && $dist <= $outerR) {
                if (!($entity instanceof FactoryEntity)) {
                    $this->hitEntities[$entityId] = true;
                }

                $waveDamage = min(8.0, $this->damage * 0.5);

                if ($owner !== null) {
                    $ev = new EntityDamageByEntityEvent($owner, $entity, EntityDamageEvent::CAUSE_FIRE, $waveDamage);
                    $entity->attack($waveDamage, $ev);
                } else {
                    $ev = new EntityDamageEvent($entity, EntityDamageEvent::CAUSE_FIRE, $waveDamage);
                    $entity->attack($waveDamage, $ev);
                }

                BaseFruit::staticSafeSetOnFire($owner, $entity, 5);

                $len = $dist > 0.3 ? $dist : 0.3;
                BaseFruit::staticSafeSetMotion($owner, $entity, new Vector3(($dx / $len) * 1.0, 0.5, ($dz / $len) * 1.0));

                $this->addStunnedTarget($entity);

                if ($entity instanceof Player) {
                    $entity->sendTip(TextFormat::RED . TextFormat::BOLD . "FIRE SHOCKWAVE!");

                    $slow = Effect::getEffect(Effect::SLOWNESS);
                    $slow->setAmplifier(1);
                    $slow->setDuration(40);
                    $slow->setVisible(false);
                    BaseFruit::staticSafeAddEffect($owner, $entity, $slow);
                }

                for ($i = 0; $i < 5; $i++) {
                    $this->level->addParticle(new FlameParticle(new Vector3(
                        $entity->x + (mt_rand(-8, 8) / 10),
                        $entity->y + mt_rand(5, 15) / 10,
                        $entity->z + (mt_rand(-8, 8) / 10)
                    )));
                }

                for ($i = 0; $i < 3; $i++) {
                    $this->level->addParticle(new DustParticle(
                        new Vector3(
                            $entity->x + (mt_rand(-6, 6) / 10),
                            $entity->y + 1.5,
                            $entity->z + (mt_rand(-6, 6) / 10)
                        ),
                        180, 0, 255
                    ));
                }

                $this->level->addParticle(new CriticalParticle(new Vector3($entity->x, $entity->y + 1.8, $entity->z)));
                $this->level->addParticle(new PortalParticle(new Vector3($entity->x, $entity->y + 1.2, $entity->z)));
                $this->level->addParticle(new LargeExplodeParticle(new Vector3($entity->x, $entity->y + 1, $entity->z)));
            }
        }
    }

    private function activateOrbs() {
        foreach ($this->allBlocks as $eid => &$b) {
            if ($b["type"] === "orb") {
                $b["active"] = true;
                $b["orbiting"] = true;
                $b["x"] = $this->slamX + cos($b["angle"]) * 2.0;
                $b["y"] = $this->slamY + 1.2;
                $b["z"] = $this->slamZ + sin($b["angle"]) * 2.0;
                $b["prevX"] = $b["x"];
                $b["prevY"] = $b["y"];
                $b["prevZ"] = $b["z"];

                BlockEffects::sendMove($this->level, $eid, $b["x"], $b["y"], $b["z"]);
            }
        }
        unset($b);
    }

    private function phaseOrbs() {
        $activeOrbs = 0;

        foreach ($this->allBlocks as $eid => &$b) {
            if ($b["type"] === "orb" && $b["active"]) {
                $activeOrbs++;

                $b["prevX"] = $b["x"];
                $b["prevY"] = $b["y"];
                $b["prevZ"] = $b["z"];

                if ($b["orbiting"]) {
                    $b["angle"] += 0.3;
                    $orbitR = 2.0 + sin($this->phaseTick * 0.3) * 0.3;
                    $b["x"] = $this->slamX + cos($b["angle"]) * $orbitR;
                    $b["y"] = $this->slamY + 1.2 + sin($this->phaseTick * 0.4 + $b["index"]) * 0.2;
                    $b["z"] = $this->slamZ + sin($b["angle"]) * $orbitR;

                    for ($i = 0; $i < 3; $i++) {
                        $pAngle = $b["angle"] - ($i * 0.3);
                        $pR = $orbitR - ($i * 0.15);
                        $this->level->addParticle(new FlameParticle(new Vector3(
                            $this->slamX + cos($pAngle) * $pR,
                            $b["y"] - ($i * 0.1),
                            $this->slamZ + sin($pAngle) * $pR
                        )));
                    }

                    $this->level->addParticle(new EntityFlameParticle(new Vector3($b["x"], $b["y"] - 0.2, $b["z"])));
                    $this->level->addParticle(new DustParticle(
                        new Vector3($b["x"], $b["y"] + 0.4, $b["z"]),
                        180, 0, 255
                    ));
                    $this->level->addParticle(new PortalParticle(new Vector3($b["x"], $b["y"], $b["z"])));

                    if ($this->phaseTick >= 8) {
                        $b["orbiting"] = false;
                        $b["vx"] = cos($b["angle"]) * 1.0;
                        $b["vz"] = sin($b["angle"]) * 1.0;

                        $this->level->addSound(new BlazeShootSound(new Vector3($b["x"], $b["y"], $b["z"])));

                        for ($i = 0; $i < 6; $i++) {
                            $burstAngle = ($i / 6) * M_PI * 2;
                            $this->level->addParticle(new FlameParticle(new Vector3(
                                $b["x"] + cos($burstAngle) * 0.5,
                                $b["y"],
                                $b["z"] + sin($burstAngle) * 0.5
                            )));
                        }
                    }
                } else {
                    $b["x"] += $b["vx"];
                    $b["z"] += $b["vz"];

                    $this->checkOrbHit($b, $eid);

                    $distFromCenter = sqrt(pow($b["x"] - $this->slamX, 2) + pow($b["z"] - $this->slamZ, 2));
                    if ($distFromCenter > $this->radius + 4) {
                        $b["active"] = false;

                        for ($i = 0; $i < 8; $i++) {
                            $angle = ($i / 8) * M_PI * 2;
                            $this->level->addParticle(new FlameParticle(new Vector3(
                                $b["prevX"] + cos($angle) * 0.8,
                                $b["prevY"] + (mt_rand(-5, 10) / 10),
                                $b["prevZ"] + sin($angle) * 0.8
                            )));
                        }

                        for ($i = 0; $i < 4; $i++) {
                            $this->level->addParticle(new DustParticle(
                                new Vector3(
                                    $b["prevX"] + (mt_rand(-8, 8) / 10),
                                    $b["prevY"] + (mt_rand(0, 12) / 10),
                                    $b["prevZ"] + (mt_rand(-8, 8) / 10)
                                ),
                                180, 0, 255
                            ));
                        }

                        $this->level->addParticle(new LargeExplodeParticle(new Vector3($b["prevX"], $b["prevY"], $b["prevZ"])));

                        $b["y"] = -100.0;
                        BlockEffects::sendMove($this->level, $eid, 0.0, -100.0, 0.0);
                        continue;
                    }
                }

                BlockEffects::sendMove($this->level, $eid, $b["x"], $b["y"], $b["z"], $this->ticksRan * 35, $this->ticksRan * 30);

                if (!$b["orbiting"]) {
                    $this->level->addParticle(new FlameParticle(new Vector3($b["x"], $b["y"], $b["z"])));
                    $this->level->addParticle(new FlameParticle(new Vector3($b["x"], $b["y"] + 0.3, $b["z"])));
                    $this->level->addParticle(new EntityFlameParticle(new Vector3($b["x"], $b["y"] - 0.2, $b["z"])));

                    for ($t = 1; $t <= 3; $t++) {
                        $trailT = $t / 4;
                        $tx = $b["prevX"] + ($b["x"] - $b["prevX"]) * $trailT;
                        $ty = $b["prevY"] + ($b["y"] - $b["prevY"]) * $trailT;
                        $tz = $b["prevZ"] + ($b["z"] - $b["prevZ"]) * $trailT;
                        $this->level->addParticle(new FlameParticle(new Vector3($tx, $ty, $tz)));
                    }

                    $this->level->addParticle(new DustParticle(
                        new Vector3($b["prevX"], $b["prevY"], $b["prevZ"]),
                        160, 0, 255
                    ));

                    $this->level->addParticle(new DustParticle(
                        new Vector3(($b["x"] + $b["prevX"]) / 2, $b["y"] + 0.4, ($b["z"] + $b["prevZ"]) / 2),
                        60, 0, 180
                    ));

                    $this->level->addParticle(new PortalParticle(new Vector3(
                        $b["prevX"],
                        $b["prevY"],
                        $b["prevZ"]
                    )));

                    $this->level->addParticle(new LavaDripParticle(new Vector3(
                        $b["x"] + (mt_rand(-3, 3) / 10),
                        $b["y"] - 0.4,
                        $b["z"] + (mt_rand(-3, 3) / 10)
                    )));
                }
            }
        }
        unset($b);

        if ($activeOrbs == 0 || $this->phaseTick >= 35) {
            $this->forceCleanup();
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
        }
    }

    private function checkOrbHit($orb, $orbEid) {
        $owner = $this->plugin->getServer()->getPlayerExact($this->ownerName);
        $hitRadius = 2.8;

        $ox = $orb["x"];
        $oy = $orb["y"];
        $oz = $orb["z"];

        foreach ($this->level->getEntities() as $entity) {
            if (!$entity->isAlive()) continue;
            if ($entity->closed) continue;

            $hitKey = "orb_" . $orbEid . "_" . $entity->getId();
            if (isset($this->hitEntities[$hitKey])) continue;

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
                $isValidTarget = true;
            } elseif ($entity instanceof FactoryEntity) {
                $isValidTarget = true;
            }

            if (!$isValidTarget) continue;

            $dx = $entity->x - $ox;
            $dy = ($entity->y + 1) - $oy;
            $dz = $entity->z - $oz;
            $dist = sqrt($dx * $dx + $dy * $dy + $dz * $dz);

            if ($dist <= $hitRadius) {
                if (!($entity instanceof FactoryEntity)) {
                    $this->hitEntities[$hitKey] = true;
                }

                $orbDamage = min(6.0, $this->damage * 0.45);

                if ($owner !== null) {
                    $ev = new EntityDamageByEntityEvent($owner, $entity, EntityDamageEvent::CAUSE_FIRE, $orbDamage);
                    $entity->attack($orbDamage, $ev);
                } else {
                    $ev = new EntityDamageEvent($entity, EntityDamageEvent::CAUSE_FIRE, $orbDamage);
                    $entity->attack($orbDamage, $ev);
                }

                BaseFruit::staticSafeSetOnFire($owner, $entity, 4);

                if ($entity instanceof Player) {
                    $entity->sendTip(TextFormat::GOLD . TextFormat::BOLD . "FLAME ORB!");
                }

                for ($ring = 0; $ring < 2; $ring++) {
                    for ($i = 0; $i < 8; $i++) {
                        $angle = ($i / 8) * M_PI * 2;
                        $r = 0.5 + ($ring * 0.4);
                        $this->level->addParticle(new FlameParticle(new Vector3(
                            $entity->x + cos($angle) * $r,
                            $entity->y + 1 + ($ring * 0.3),
                            $entity->z + sin($angle) * $r
                        )));
                    }
                }

                for ($i = 0; $i < 6; $i++) {
                    $this->level->addParticle(new DustParticle(
                        new Vector3(
                            $entity->x + (mt_rand(-10, 10) / 10),
                            $entity->y + mt_rand(10, 22) / 10,
                            $entity->z + (mt_rand(-10, 10) / 10)
                        ),
                        180, 0, 255
                    ));
                }

                for ($i = 0; $i < 3; $i++) {
                    $this->level->addParticle(new DustParticle(
                        new Vector3(
                            $entity->x + (mt_rand(-8, 8) / 10),
                            $entity->y + 2.0,
                            $entity->z + (mt_rand(-8, 8) / 10)
                        ),
                        60, 0, 180
                    ));
                }

                for ($i = 0; $i < 4; $i++) {
                    $this->level->addParticle(new CriticalParticle(new Vector3(
                        $entity->x + (mt_rand(-8, 8) / 10),
                        $entity->y + 1.8,
                        $entity->z + (mt_rand(-8, 8) / 10)
                    )));
                }

                for ($i = 0; $i < 3; $i++) {
                    $this->level->addParticle(new PortalParticle(new Vector3(
                        $entity->x + (mt_rand(-6, 6) / 10),
                        $entity->y + mt_rand(8, 15) / 10,
                        $entity->z + (mt_rand(-6, 6) / 10)
                    )));
                }

                $this->level->addParticle(new HugeExplodeParticle(new Vector3($entity->x, $entity->y + 1, $entity->z)));

                $this->plugin->getServer()->getScheduler()->scheduleDelayedTask(
                    new OrbLaunchSlamTask($this->plugin, $this->level, $entity, $this->ownerName, $this->damage, $this->toggle, $this->scannedBlocks),
                    1
                );
            }
        }
    }

    public function forceCleanup() {
        if ($this->cleaned) return;
        $this->cleaned = true;
        $this->releaseAllStunned();
        BlockEffects::voidAndRemove($this->plugin, $this->level, array_keys($this->allBlocks));
        $this->allBlocks = [];
    }
}

class OrbLaunchSlamTask extends Task {

    private $plugin;
    private $level;
    private $target;
    private $ownerName;
    private $damage;
    private $toggle;
    private $scannedBlocks;

    private $phase = 0;
    private $ticksRan = 0;
    private $maxTicks = 60;
    private $startY;
    private $peakY;
    private $targetX;
    private $targetZ;

    public function __construct($plugin, Level $level, $target, $ownerName, $damage, $toggle, $scannedBlocks) {
        $this->plugin = $plugin;
        $this->level = $level;
        $this->target = $target;
        $this->ownerName = $ownerName;
        $this->damage = $damage;
        $this->toggle = $toggle;
        $this->scannedBlocks = $scannedBlocks;

        $this->startY = $target->y;
        $this->peakY = $target->y + 10;
        $this->targetX = $target->x;
        $this->targetZ = $target->z;

        if (!($target instanceof FactoryEntity)) {
            BaseFruit::staticSafeSetMotion($this->plugin->getServer()->getPlayerExact($this->ownerName), $target, new Vector3(0, 2.5, 0));
        }
    }

    public function onRun($currentTick) {
        $this->ticksRan++;

        if ($this->ticksRan > $this->maxTicks) {
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
            return;
        }

        if ($this->target === null || $this->target->closed || !$this->target->isAlive()) {
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
            return;
        }

        if ($this->target instanceof Player) {
            if (!$this->plugin->canTargetPlayer($this->ownerName, $this->target)) {
                BaseFruit::staticSafeSetMotion($this->plugin->getServer()->getPlayerExact($this->ownerName), $this->target, new Vector3(0, 0, 0));
                $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
                return;
            }
        }

        $pos = $this->target->getPosition();

        switch ($this->phase) {
            case 0:
                $this->phaseLaunch($pos);
                break;
            case 1:
                $this->phaseHold($pos);
                break;
            case 2:
                $this->phaseSlam($pos);
                break;
            case 3:
                $this->phaseImpact($pos);
                break;
        }
    }

    private function phaseLaunch($pos) {
        if (!($this->target instanceof FactoryEntity)) {
            BaseFruit::staticSafeSetMotion($this->plugin->getServer()->getPlayerExact($this->ownerName), $this->target, new Vector3(0, 1.2, 0));
        }

        for ($i = 0; $i < 4; $i++) {
            $angle = ($this->ticksRan * 0.8) + ($i * M_PI / 2);
            $r = 0.8;
            $this->level->addParticle(new FlameParticle(new Vector3(
                $pos->x + cos($angle) * $r,
                $pos->y,
                $pos->z + sin($angle) * $r
            )));
        }

        for ($i = 0; $i < 3; $i++) {
            $this->level->addParticle(new FlameParticle(new Vector3(
                $pos->x + (mt_rand(-5, 5) / 10),
                $pos->y - 0.5 - ($i * 0.4),
                $pos->z + (mt_rand(-5, 5) / 10)
            )));
        }

        for ($i = 0; $i < 2; $i++) {
            $angle = ($this->ticksRan * 0.6) + ($i * M_PI);
            $r = 1.0;
            $this->level->addParticle(new DustParticle(
                new Vector3($pos->x + cos($angle) * $r, $pos->y + 0.5, $pos->z + sin($angle) * $r),
                180, 0, 255
            ));
        }

        $this->level->addParticle(new PortalParticle(new Vector3(
            $pos->x + (mt_rand(-8, 8) / 10),
            $pos->y - 0.3,
            $pos->z + (mt_rand(-8, 8) / 10)
        )));

        if ($this->target instanceof Player) {
            $this->target->sendTip(TextFormat::RED . "LAUNCHING!");
        }

        if ($pos->y >= $this->peakY - 0.5 || $this->ticksRan >= 18) {
            $this->phase = 1;
            $this->ticksRan = 0;
        }
    }

    private function phaseHold($pos) {
        if (!($this->target instanceof FactoryEntity)) {
            BaseFruit::staticSafeSetMotion($this->plugin->getServer()->getPlayerExact($this->ownerName), $this->target, new Vector3(0, 0.05, 0));
        }

        for ($i = 0; $i < 6; $i++) {
            $angle = ($this->ticksRan * 0.5) + ($i * M_PI / 3);
            $r = 1.2;
            $this->level->addParticle(new FlameParticle(new Vector3(
                $pos->x + cos($angle) * $r,
                $pos->y + sin($this->ticksRan * 0.4) * 0.2,
                $pos->z + sin($angle) * $r
            )));
        }

        for ($i = 0; $i < 4; $i++) {
            $angle = -($this->ticksRan * 0.4) + ($i * M_PI / 2);
            $r = 1.5;
            $this->level->addParticle(new DustParticle(
                new Vector3($pos->x + cos($angle) * $r, $pos->y, $pos->z + sin($angle) * $r),
                160, 0, 255
            ));
        }

        for ($i = 0; $i < 2; $i++) {
            $this->level->addParticle(new PortalParticle(new Vector3(
                $pos->x + (mt_rand(-12, 12) / 10),
                $pos->y + (mt_rand(-5, 5) / 10),
                $pos->z + (mt_rand(-12, 12) / 10)
            )));
        }

        $this->level->addParticle(new EntityFlameParticle(new Vector3($pos->x, $pos->y - 0.5, $pos->z)));

        if ($this->target instanceof Player) {
            $this->target->sendTip(TextFormat::DARK_RED . TextFormat::BOLD . "...");
        }

        if ($this->ticksRan >= 8) {
            $this->phase = 2;
            $this->ticksRan = 0;

            BaseFruit::staticSafeSetMotion($this->plugin->getServer()->getPlayerExact($this->ownerName), $this->target, new Vector3(0, -2.5, 0));

            $this->level->addSound(new GhastShootSound($pos));

            if ($this->target instanceof Player) {
                $this->target->sendTip(TextFormat::DARK_RED . TextFormat::BOLD . "SLAM!");
            }
        }
    }

    private function phaseSlam($pos) {
        if (!($this->target instanceof FactoryEntity)) {
            BaseFruit::staticSafeSetMotion($this->plugin->getServer()->getPlayerExact($this->ownerName), $this->target, new Vector3(0, -1.5, 0));
        }

        for ($i = 0; $i < 4; $i++) {
            $this->level->addParticle(new FlameParticle(new Vector3(
                $pos->x + (mt_rand(-5, 5) / 10),
                $pos->y + 1 + ($i * 0.5),
                $pos->z + (mt_rand(-5, 5) / 10)
            )));
        }

        for ($i = 0; $i < 3; $i++) {
            $angle = ($this->ticksRan * 1.0) + ($i * M_PI * 2 / 3);
            $r = 0.6;
            $this->level->addParticle(new DustParticle(
                new Vector3($pos->x + cos($angle) * $r, $pos->y + 1.5, $pos->z + sin($angle) * $r),
                200, 0, 255
            ));
        }

        $this->level->addParticle(new SmokeParticle(new Vector3($pos->x, $pos->y + 2, $pos->z)));

        if ($this->target->isOnGround() || $pos->y <= $this->startY + 0.5 || $this->ticksRan >= 15) {
            $this->phase = 3;
            $this->ticksRan = 0;

            $this->spawnSlamImpact($pos);
        }
    }

    private function phaseImpact($pos) {
        $this->ticksRan++;

        if ($this->ticksRan >= 5) {
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
        }
    }

    private function spawnSlamImpact($pos) {
        $cx = $pos->x;
        $cy = $pos->y;
        $cz = $pos->z;

        $owner = $this->plugin->getServer()->getPlayerExact($this->ownerName);
        $slamDamage = min(9.0, $this->damage * 0.6);

        if ($this->target->isAlive()) {
            if ($owner !== null) {
                $cause = ($this->target instanceof FactoryEntity) ? EntityDamageEvent::CAUSE_ENTITY_ATTACK : EntityDamageEvent::CAUSE_FALL;
                $ev = new EntityDamageByEntityEvent($owner, $this->target, $cause, $slamDamage);
                $this->target->attack($slamDamage, $ev);
            } else {
                $cause = ($this->target instanceof FactoryEntity) ? EntityDamageEvent::CAUSE_MAGIC : EntityDamageEvent::CAUSE_FALL;
                $ev = new EntityDamageEvent($this->target, $cause, $slamDamage);
                $this->target->attack($slamDamage, $ev);
            }
        }

        BaseFruit::staticSafeSetMotion($owner, $this->target, new Vector3(0, 0, 0));

        if ($this->target instanceof Player) {
            $slow = Effect::getEffect(Effect::SLOWNESS);
            $slow->setAmplifier(3);
            $slow->setDuration(40);
            $slow->setVisible(false);
            BaseFruit::staticSafeAddEffect($owner, $this->target, $slow);

            $nausea = Effect::getEffect(Effect::NAUSEA);
            $nausea->setAmplifier(2);
            $nausea->setDuration(40);
            $nausea->setVisible(false);
            BaseFruit::staticSafeAddEffect($owner, $this->target, $nausea);

            $this->target->sendTip(TextFormat::DARK_RED . TextFormat::BOLD . "CRATER!");
        }

        $this->level->addSound(new ExplodeSound(new Vector3($cx, $cy, $cz)));
        $this->level->addSound(new AnvilUseSound(new Vector3($cx, $cy, $cz)));

        $this->level->addParticle(new HugeExplodeParticle(new Vector3($cx, $cy + 0.5, $cz)));
        $this->level->addParticle(new HugeExplodeParticle(new Vector3($cx, $cy + 1.5, $cz)));
        $this->level->addParticle(new LargeExplodeParticle(new Vector3($cx + 1, $cy + 0.5, $cz)));
        $this->level->addParticle(new LargeExplodeParticle(new Vector3($cx - 1, $cy + 0.5, $cz)));
        $this->level->addParticle(new LargeExplodeParticle(new Vector3($cx, $cy + 0.5, $cz + 1)));
        $this->level->addParticle(new LargeExplodeParticle(new Vector3($cx, $cy + 0.5, $cz - 1)));

        for ($ring = 0; $ring < 4; $ring++) {
            $ringR = 1.0 + ($ring * 1.2);
            $particles = 10 + ($ring * 4);
            for ($i = 0; $i < $particles; $i++) {
                $angle = ($i / $particles) * M_PI * 2;
                $this->level->addParticle(new ExplodeParticle(new Vector3(
                    $cx + cos($angle) * $ringR,
                    $cy + 0.2 + ($ring * 0.1),
                    $cz + sin($angle) * $ringR
                )));
            }
        }

        for ($i = 0; $i < 24; $i++) {
            $angle = mt_rand(0, 628) / 100;
            $dist = mt_rand(5, 40) / 10;
            $this->level->addParticle(new FlameParticle(new Vector3(
                $cx + cos($angle) * $dist,
                $cy + mt_rand(2, 20) / 10,
                $cz + sin($angle) * $dist
            )));
        }

        for ($i = 0; $i < 12; $i++) {
            $angle = mt_rand(0, 628) / 100;
            $dist = mt_rand(5, 30) / 10;
            $this->level->addParticle(new EntityFlameParticle(new Vector3(
                $cx + cos($angle) * $dist,
                $cy + 0.2,
                $cz + sin($angle) * $dist
            )));
        }

        for ($ring = 0; $ring < 3; $ring++) {
            $ringR = 1.5 + ($ring * 1.5);
            for ($i = 0; $i < 10; $i++) {
                $angle = ($i / 10) * M_PI * 2 + ($ring * M_PI / 10);
                $this->level->addParticle(new DustParticle(
                    new Vector3($cx + cos($angle) * $ringR, $cy + 0.5 + ($ring * 0.25), $cz + sin($angle) * $ringR),
                    180 - ($ring * 30), 0, 255
                ));
            }
        }

        for ($i = 0; $i < 8; $i++) {
            $angle = ($i / 8) * M_PI * 2;
            $d = 2.5;
            $this->level->addParticle(new DustParticle(
                new Vector3($cx + cos($angle) * $d, $cy + 1.0, $cz + sin($angle) * $d),
                60, 0, 180
            ));
        }

        for ($i = 0; $i < 10; $i++) {
            $angle = mt_rand(0, 628) / 100;
            $d = mt_rand(8, 35) / 10;
            $this->level->addParticle(new PortalParticle(new Vector3(
                $cx + cos($angle) * $d,
                $cy + mt_rand(3, 20) / 10,
                $cz + sin($angle) * $d
            )));
        }

        for ($i = 0; $i < 8; $i++) {
            $angle = ($i / 8) * M_PI * 2;
            $d = mt_rand(10, 25) / 10;
            $this->level->addParticle(new CriticalParticle(new Vector3(
                $cx + cos($angle) * $d,
                $cy + mt_rand(8, 22) / 10,
                $cz + sin($angle) * $d
            )));
        }

        for ($spoke = 0; $spoke < 8; $spoke++) {
            $angle = ($spoke / 8) * M_PI * 2;
            for ($p = 0; $p < 4; $p++) {
                $dist = 0.6 + ($p * 0.7);
                $this->level->addParticle(new DustParticle(
                    new Vector3($cx + cos($angle) * $dist, $cy + 0.1, $cz + sin($angle) * $dist),
                    120, 60, 0
                ));
            }
        }

        $this->spawnDebrisBlocks($cx, $cy, $cz);
    }

    private function spawnDebrisBlocks($cx, $cy, $cz) {
        $debrisEids = [];

        for ($i = 0; $i < 3; $i++) {
            $angle = ($i / 3) * M_PI * 2 + (mt_rand(-30, 30) / 100);
            $eid = BlockEffects::newEid();

            $blockData = !empty($this->scannedBlocks) ? $this->scannedBlocks[$i % count($this->scannedBlocks)] : ["id" => 4, "damage" => 0];

            $debrisEids[] = [
                "eid" => $eid,
                "x" => $cx + cos($angle) * 0.8,
                "y" => $cy + 0.5,
                "z" => $cz + sin($angle) * 0.8,
                "vx" => cos($angle) * (0.25 + mt_rand(0, 15) / 100),
                "vy" => 0.5 + mt_rand(0, 25) / 100,
                "vz" => sin($angle) * (0.25 + mt_rand(0, 15) / 100),
                "blockId" => $blockData["id"],
                "blockDamage" => $blockData["damage"],
                "rotSpeed" => 30 + mt_rand(0, 20)
            ];

            BlockEffects::sendSpawn($this->level, $eid, $blockData["id"], $blockData["damage"] ?? 0,
                $cx + cos($angle) * 0.8, $cy + 0.5, $cz + sin($angle) * 0.8);
        }

        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask(
            new SlamDebrisTask($this->plugin, $this->level, $debrisEids, $cy),
            2
        );
    }
}

class SlamDebrisTask extends Task {

    private $plugin;
    private $level;
    private $debris;
    private $groundY;
    private $ticksRan = 0;
    private $maxTicks = 30;
    private $cleaned = false;

    public function __construct($plugin, Level $level, array $debris, $groundY) {
        $this->plugin = $plugin;
        $this->level = $level;
        $this->groundY = $groundY;
        $this->debris = [];
        foreach ($debris as $d) {
            $this->debris[$d["eid"]] = [
                "blockId" => $d["blockId"], "blockDamage" => $d["blockDamage"],
                "x" => $d["x"], "y" => $d["y"], "z" => $d["z"],
                "vx" => $d["vx"], "vy" => $d["vy"], "vz" => $d["vz"],
                "life" => 28 + mt_rand(0, 8), "rotSpeed" => $d["rotSpeed"], "tick" => 0
            ];
        }
    }

    public function onRun($currentTick) {
        if ($this->cleaned) return;
        $this->ticksRan++;

        if ($this->ticksRan > $this->maxTicks || empty($this->debris)) {
            $this->cleanup();
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
            return;
        }

        $toRemove = BlockEffects::tickDebris($this->debris, $this->level, $this->groundY - 0.5, 0.06, 0.95);
        foreach ($toRemove as $eid) {
            unset($this->debris[$eid]);
        }
    }

    private function cleanup() {
        if ($this->cleaned) return;
        $this->cleaned = true;

        foreach ($this->debris as $d) {
            $this->level->addParticle(new FlameParticle(new Vector3($d["x"], $d["y"] + 0.3, $d["z"])));
            $this->level->addParticle(new SmokeParticle(new Vector3($d["x"], $d["y"] + 0.5, $d["z"])));
        }

        BlockEffects::voidAndRemove($this->plugin, $this->level, array_keys($this->debris));
        $this->debris = [];
    }

    public function forceCleanup() {
        $this->cleanup();
    }
}