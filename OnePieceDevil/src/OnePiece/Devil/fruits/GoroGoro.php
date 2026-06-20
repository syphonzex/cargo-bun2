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
use pocketmine\level\particle\InstantEnchantParticle;
use pocketmine\level\particle\EnchantParticle;
use pocketmine\level\particle\ExplodeParticle;
use pocketmine\level\particle\CriticalParticle;
use pocketmine\level\sound\PopSound;
use pocketmine\level\sound\ClickSound;
use pocketmine\level\sound\FizzSound;
use pocketmine\level\sound\AnvilUseSound;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\network\protocol\MoveEntityPacket;
use pocketmine\network\protocol\RemoveEntityPacket;
use pocketmine\level\sound\GhastSound;
use pocketmine\level\sound\ExplodeSound;
use pocketmine\network\protocol\LevelEventPacket;
use OnePiece\NPC\NPCEntity;
use OnePieceTrades\Factory\FactoryEntity;
use OnePiece\Devil\BlockEffects;

class GoroGoro extends BaseFruit {

    const COL_YELLOW_R = 255;
    const COL_YELLOW_G = 255;
    const COL_YELLOW_B = 0;
    const COL_WHITE_R = 255;
    const COL_WHITE_G = 255;
    const COL_WHITE_B = 200;
    const COL_BLUE_R = 100;
    const COL_BLUE_G = 180;
    const COL_BLUE_B = 255;

    public function getId() { return "goro_goro"; }
    public function getDisplayName() { return "Lightning-Lightning Fruit"; }
    public function getDescription() { return "Lightning Fruit - Enel's divine power, 200 million volts."; }
    public function getType() { return "logia"; }
    public function getRarity() { return "mythical"; }

    public function getAbilityNames() {
        return ["ability1" => "El Thor", "ability2" => "Raigo"];
    }

    public function getAbilityCooldowns() {
        return ["ability1" => 6.0, "ability2" => 26.0];
    }

    public function useAbility(Player $player, $ability) {
        switch ($ability) {
            case "ability1": return $this->elThor($player);
            case "ability2": return $this->raigo($player);
        }
        return 0;
    }

    private function elThor(Player $player) {
        $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
        $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

        $mult   = min(1.5, $this->getHakiMultiplier($player));
        $damage = min(10.0, 6.0 * $mult);
        $range  = 18.0 + 4.0 * ($mult - 1.0);

        $task = new ProjectedBurstTask($this->plugin, $player, $damage, $range, $toggle);
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($task, 1);

        $player->sendTip(TextFormat::YELLOW . TextFormat::BOLD . "EL THOR!");

        return $this->getAbilityCooldowns()["ability1"];
    }

    private function raigo(Player $player) {
        $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
        $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

        $mult   = min(1.5, $this->getHakiMultiplier($player));
        $damage = min(10.0, 8.0 * $mult);
        $radius = 10.0 + 4.0 * ($mult - 1.0);

        $task = new ThunderBallTask($this->plugin, $player, $damage, $radius, $toggle);
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($task, 1);

        $player->sendTip(TextFormat::YELLOW . TextFormat::BOLD . "RAIGO!");
        $player->sendMessage(TextFormat::YELLOW . "Charging thunder ball... hold position!");

        return $this->getAbilityCooldowns()["ability2"];
    }

    private function getVFX() {
        return $this->plugin->getServer()->getPluginManager()->getPlugin("OnePieceFruits");
    }

    public function onEquip(Player $player) {
        $player->sendMessage(TextFormat::YELLOW . "=== Goro-Goro no Mi ===");
        $player->sendMessage(TextFormat::WHITE . "God of Lightning - 200 Million Volts");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::YELLOW . "[Tap]: " . TextFormat::WHITE . "El Thor - Projected Burst");
        $player->sendMessage(TextFormat::GRAY . "  Lightning bolt strikes target");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::YELLOW . "[Sneak+Tap]: " . TextFormat::WHITE . "Raigo");
        $player->sendMessage(TextFormat::GRAY . "  Create thunder domain with orbiting lightning");
        $player->sendMessage(TextFormat::YELLOW . "========================");
    }

    public function onUnequip(Player $player) {
        $player->sendMessage(TextFormat::GRAY . "Lightning fades...");
    }
}
class GoroDelayedRemoveTask extends Task {

    private $plugin;
    private $level;
    private $eids;

    public function __construct($plugin, Level $level, array $eids) {
        $this->plugin = $plugin;
        $this->level  = $level;
        $this->eids   = $eids;
    }

    public function onRun($currentTick) {
        $allPlayers = [];
        try {
            if ($this->level !== null) {
                foreach ($this->level->getPlayers() as $pl) {
                    $allPlayers[spl_object_hash($pl)] = $pl;
                }
            }
        } catch (\Exception $e) {}
        try {
            foreach ($this->plugin->getServer()->getOnlinePlayers() as $pl) {
                $allPlayers[spl_object_hash($pl)] = $pl;
            }
        } catch (\Exception $e) {}
        foreach ($this->eids as $eid) {
            $movePk          = new MoveEntityPacket();
            $movePk->eid     = $eid;
            $movePk->x       = 0.0;
            $movePk->y       = 0.0;
            $movePk->z       = 0.0;
            $movePk->yaw     = 0.0;
            $movePk->pitch   = 0.0;
            $movePk->headYaw = 0.0;
            $removePk      = new RemoveEntityPacket();
            $removePk->eid = $eid;
            foreach ($allPlayers as $pl) {
                try {
                    $pl->dataPacket($movePk);
                    $pl->dataPacket($removePk);
                } catch (\Exception $e) {}
            }
        }
    }
}
class ThunderBallTask extends Task {

    private $plugin;
    private $player;
    private $damage;
    private $radius;
    private $toggle;
    private $tick       = 0;
    private $cleaned    = false;
    private $exploded   = false;
    private $explodeTick = 0;

    private $ballX; private $ballY; private $ballZ;
    private $velX = 0.0; private $velY = 0.0; private $velZ = 0.0;
    private $launched = false;

    const PHASE_CHARGE = 60;
    const LAUNCH_SPEED = 0.28;
    const GRAVITY      = 0.018;
    const MAX_TRAVEL   = 160;
    const VIEW_RANGE   = 80;

    public function __construct($plugin, Player $player, $damage, $radius, $toggle) {
        $this->plugin  = $plugin;
        $this->player  = $player;
        $this->damage  = $damage;
        $this->radius  = $radius;
        $this->toggle  = $toggle;
        $res = Effect::getEffect(Effect::DAMAGE_RESISTANCE);
        $res->setAmplifier(4); $res->setDuration(90); $res->setVisible(false);
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
        $px = $this->player->x;
        $py = $this->player->y;
        $pz = $this->player->z;

        if ($this->exploded) {
            $this->doExplosionVFX($lv, $players);
            if ($this->explodeTick >= 30) {
                $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
            }
            return;
        }

        if ($this->tick <= self::PHASE_CHARGE) {
            $this->doCharge($lv, $players, $px, $py, $pz);
        } else {
            $this->doTravel($lv, $players);
        }
    }

    private function spawnBallParticles($lv, $bx, $by, $bz, $radius, $spin) {
        $pts = 16;
        for ($i = 0; $i < $pts; $i++) {
            $a = ($i / $pts) * M_PI * 2 + $spin;
            $lv->addParticle(new DustParticle(new Vector3(
                $bx + cos($a) * $radius,
                $by + sin($a) * $radius * 0.6,
                $bz + sin($a) * $radius
            ), 80, 200, 255));
        }
        for ($i = 0; $i < $pts; $i++) {
            $a = ($i / $pts) * M_PI * 2 - $spin * 1.3;
            $lv->addParticle(new DustParticle(new Vector3(
                $bx + cos($a) * $radius * 0.7,
                $by + sin($a) * $radius + (mt_rand(-3,3)/10),
                $bz + sin($a) * $radius * 0.7
            ), 150, 230, 255));
        }
        for ($i = 0; $i < 6; $i++) {
            $sa = mt_rand(0, 628) / 100.0;
            $lv->addParticle(new InstantEnchantParticle(new Vector3(
                $bx + cos($sa) * $radius * (0.6 + mt_rand(0,4)/10),
                $by + sin($sa) * $radius * 0.5,
                $bz + sin($sa) * $radius * (0.6 + mt_rand(0,4)/10)
            )));
        }
    }

    private function spawnArcSpokes($lv, $bx, $by, $bz, $arcLen, $spin) {
        for ($spoke = 0; $spoke < 6; $spoke++) {
            $sa = ($spoke / 6) * M_PI * 2 + $spin;
            for ($seg = 0; $seg < 5; $seg++) {
                $sd = ($seg / 4) * $arcLen;
                $lv->addParticle(new DustParticle(new Vector3(
                    $bx + cos($sa)*$sd + (mt_rand(-12,12)/10)*($seg/4.0),
                    $by + sin($sa*2)*0.3 + (mt_rand(-3,3)/10),
                    $bz + sin($sa)*$sd + (mt_rand(-12,12)/10)*($seg/4.0)
                ), 180, 230, 255));
            }
            $lv->addParticle(new InstantEnchantParticle(new Vector3(
                $bx + cos($sa)*$arcLen, $by, $bz + sin($sa)*$arcLen
            )));
        }
    }

    private function doCharge($lv, $players, $px, $py, $pz) {
        $t    = $this->tick;
        $prog = $t / self::PHASE_CHARGE;

        if ($t <= 50) $this->player->setMotion(new Vector3(0, 0.05, 0));

        $bx = $px;
        $by = $py + 2.5 + $prog * 2.0;
        $bz = $pz;
        $this->ballX = $bx;
        $this->ballY = $by;
        $this->ballZ = $bz;

        $ballR = 0.5 + $prog * 2.5;
        $spin  = $t * 0.22;
        $this->spawnBallParticles($lv, $bx, $by, $bz, $ballR, $spin);

        if ($t % 3 === 0) {
            $arcLen = 1.0 + $prog * 3.5;
            $this->spawnArcSpokes($lv, $bx, $by, $bz, $arcLen, $spin);
            $lv->addSound(new ClickSound(new Vector3($bx, $by, $bz)));
        }

        if ($t % 2 === 0) {
            $steps = max(1, (int)(($by - $py) / 0.5));
            for ($s = 0; $s <= $steps; $s++) {
                $bp = $s / $steps;
                $lv->addParticle(new DustParticle(new Vector3(
                    $px + (mt_rand(-3,3)/10)*(1-$bp),
                    $py + ($by-$py)*$bp,
                    $pz + (mt_rand(-3,3)/10)*(1-$bp)
                ), 80, 200, 255));
            }
        }

        if ($t % 20 === 0) {
            $lv->addSound(new FizzSound(new Vector3($bx, $by, $bz)));
            $lv->addSound(new AnvilUseSound(new Vector3($bx, $by, $bz)));
        }

        if ($t === self::PHASE_CHARGE) {
            $dir  = $this->player->getDirectionVector();
            $dLen = sqrt($dir->x*$dir->x + $dir->y*$dir->y + $dir->z*$dir->z);
            if ($dLen > 0) {
                $this->velX = ($dir->x/$dLen) * self::LAUNCH_SPEED;
                $this->velY = ($dir->y/$dLen) * self::LAUNCH_SPEED;
                $this->velZ = ($dir->z/$dLen) * self::LAUNCH_SPEED;
            }
            $this->launched = true;
            $lv->addSound(new GhastSound(new Vector3($bx, $by, $bz)));
            $lv->addSound(new ExplodeSound(new Vector3($bx, $by, $bz)));
            $this->player->sendTip(TextFormat::YELLOW . TextFormat::BOLD . "RAIGO LAUNCH!");
        }
    }

    private function doTravel($lv, $players) {
        $travelTick = $this->tick - self::PHASE_CHARGE;

        $this->velY -= self::GRAVITY;
        $this->ballX += $this->velX;
        $this->ballY += $this->velY;
        $this->ballZ += $this->velZ;

        $bx   = $this->ballX; $by = $this->ballY; $bz = $this->ballZ;
        $spin = $this->tick * 0.22;

        $this->spawnBallParticles($lv, $bx, $by, $bz, 2.5, $spin);

        if ($travelTick % 3 === 0) {
            $this->spawnArcSpokes($lv, $bx, $by, $bz, 4.0, $spin);
            $lv->addSound(new ClickSound(new Vector3($bx, $by, $bz)));
        }

        for ($i = 0; $i < 4; $i++) {
            $lv->addParticle(new DustParticle(new Vector3(
                $bx - $this->velX*(2+$i) + (mt_rand(-5,5)/10),
                $by - $this->velY*(2+$i),
                $bz - $this->velZ*(2+$i) + (mt_rand(-5,5)/10)
            ), 100, 220, 255));
        }

        $nonSolid = [0,8,9,10,11,26,30,31,32,37,38,39,40,50,51,55,59,63,64,65,68,69,70,71,72,75,76,77,83,90,93,94,96,104,105,106,115,127,131,132,141,142,143,144,147,148,149,150,154,157,167,171,175,176,177,178,183,184,185,186,187,193,194,195,196,197];
        $checkX = (int)floor($bx); $checkY = (int)floor($by); $checkZ = (int)floor($bz);
        $blockAt    = $lv->getBlock(new Vector3($checkX, $checkY, $checkZ));
        $blockBelow = $lv->getBlock(new Vector3($checkX, $checkY - 1, $checkZ));
        $hitGround  = (!in_array($blockAt->getId(), $nonSolid) || !in_array($blockBelow->getId(), $nonSolid))
                   || $by < 0 || $travelTick >= self::MAX_TRAVEL;

        if ($hitGround) {
            $this->triggerExplosion($lv, $players);
        }
    }

private function triggerExplosion($lv, $players) {
    if ($this->exploded) return;
    $this->exploded = true;
    $this->explodeTick = 0;

    $lv->addSound(new GhastSound(new Vector3($this->ballX, $this->ballY, $this->ballZ)));
    $lv->addSound(new ExplodeSound(new Vector3($this->ballX, $this->ballY, $this->ballZ)));
    $lv->addSound(new AnvilUseSound(new Vector3($this->ballX, $this->ballY, $this->ballZ)));
    $lv->addSound(new ExplodeSound(new Vector3($this->player->x, $this->player->y, $this->player->z)));

    $this->hitAll($lv);

    $debris = BlockEffects::spawnDebris(
        $this->plugin, $lv, $this->ballX, $this->ballY, $this->ballZ,
        8, 0.5, 1.2, 30
    );
    $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask(
        new RaigoDebrisTask($this->plugin, $lv, $debris, $this->ballY),
        1
    );
}

    private function doExplosionVFX($lv, $players) {
        $this->explodeTick++;
        $t    = $this->explodeTick;
        $prog = $t / 30.0;
        $bx   = $this->ballX; $by = $this->ballY; $bz = $this->ballZ;
        $r    = 1.5 + $prog * ($this->radius * 1.3);
        $spin = $t * 0.3;

        $this->spawnBallParticles($lv, $bx, $by, $bz, $r, $spin);

        if ($t % 2 === 0) {
            $this->spawnArcSpokes($lv, $bx, $by, $bz, $r * 1.2, $spin);
        }

        $gpts = max(10, (int)($r * 4));
        for ($i = 0; $i < $gpts; $i++) {
            $a = ($i/$gpts) * M_PI * 2;
            $lv->addParticle(new DustParticle(new Vector3(
                $bx + cos($a)*$r, $by - 0.1, $bz + sin($a)*$r
            ), 200, 240, 255));
        }

        $lv->addParticle(new InstantEnchantParticle(new Vector3($bx, $by, $bz)));
        $lv->addParticle(new ExplodeParticle(new Vector3($bx, $by + 0.5, $bz)));

        if ($t % 4 === 0) {
            $pk = new LevelEventPacket(); $pk->evid = 2002; $pk->data = 6737151;
            $pk->x = (float)$bx; $pk->y = (float)$by; $pk->z = (float)$bz;
            foreach ($players as $pl) { $pl->dataPacket($pk); }
            $lv->addSound(new ClickSound(new Vector3($bx, $by, $bz)));
        }
    }

    private function hitAll($lv) {
        $owner = $this->plugin->getServer()->getPlayerExact($this->player->getName());
        $bx = $this->ballX; $by = $this->ballY; $bz = $this->ballZ;
        foreach ($lv->getEntities() as $entity) {
            if (!$entity->isAlive() || $entity->closed || $entity === $this->player) continue;
            $isValid = false;
            if ($entity instanceof Player) {
                if ($this->toggle !== null && !$this->plugin->canTargetPlayer($this->player->getName(), $entity)) continue;
                $isValid = true;
            } elseif ($entity instanceof NPCEntity) { $isValid = true; }
            elseif ($entity instanceof FactoryEntity) { $isValid = true; }
            if (!$isValid) continue;
            $dx = $entity->x - $bx; $dy = $entity->y - $by; $dz = $entity->z - $bz;
            $dist = sqrt($dx*$dx + $dy*$dy + $dz*$dz);
            if ($dist > $this->radius) continue;
            $scale = 1 - ($dist / $this->radius) * 0.3;
            $dmg   = $this->damage * $scale;
            if ($owner !== null) {
                $ev = new EntityDamageByEntityEvent($owner, $entity, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $dmg);
                $entity->attack($dmg, $ev);
            } else {
                $ev = new EntityDamageEvent($entity, EntityDamageEvent::CAUSE_MAGIC, $dmg);
                $entity->attack($dmg, $ev);
            }
            $len2 = $dist > 0 ? $dist : 1;
            $kbH  = min(2.2, 2.5 * (1 - $dist/$this->radius));
            BaseFruit::staticSafeSetMotion($owner, $entity, new Vector3($dx/$len2 * $kbH, 1.0, $dz/$len2 * $kbH));
            $slow = Effect::getEffect(Effect::SLOWNESS);
            $slow->setAmplifier(3); $slow->setDuration(80); $slow->setVisible(false);
            if ($entity instanceof Player) {
                BaseFruit::staticSafeAddEffect($owner, $entity, $slow);
                $entity->sendTip(TextFormat::YELLOW . TextFormat::BOLD . "RAIGO!");
            }
        }
    }

    private function getNearby($lv) {
        $out = [];
        $px = $this->player->x; $pz = $this->player->z;
        foreach ($lv->getPlayers() as $p) {
            if (abs($p->x-$px) <= self::VIEW_RANGE && abs($p->z-$pz) <= self::VIEW_RANGE) $out[] = $p;
        }
        return $out;
    }
}

class ProjectedBurstTask extends Task {

    private $plugin;
    private $player;
    private $damage;
    private $range;
    private $toggle;
    private $tick    = 0;
    private $hitTargets = [];
    private $dirX; private $dirZ;
    private $headX; private $headY; private $headZ;
    private $lightningEids = [];

    const PHASE_CHARGE   = 15;
    const PHASE_LAUNCH   = 50;
    const PHASE_FADE     = 60;
    const LIGHTNING_TYPE = 93;
    const TRAVEL_SPEED   = 0.9;
    const CYL_RADIUS     = 1.8;
    const VIEW_RANGE     = 60;

    private static $nextEid = 150000;
    private static function newEid() {
        $e = self::$nextEid++;
        if (self::$nextEid > 199999) self::$nextEid = 150000;
        return $e;
    }

    public function __construct($plugin, Player $player, $damage, $range, $toggle) {
        $this->plugin  = $plugin;
        $this->player  = $player;
        $this->damage  = $damage;
        $this->range   = $range;
        $this->toggle  = $toggle;
        $dir = $player->getDirectionVector();
        $len = sqrt($dir->x*$dir->x + $dir->z*$dir->z);
        $this->dirX = $len > 0 ? $dir->x/$len : 0;
        $this->dirZ = $len > 0 ? $dir->z/$len : 0;
        $this->headX = $player->x;
        $this->headY = $player->y + 1.0;
        $this->headZ = $player->z;
        $res = Effect::getEffect(Effect::DAMAGE_RESISTANCE);
        $res->setAmplifier(4); $res->setDuration(65); $res->setVisible(false);
        $player->addEffect($res);
    }

    public function onRun($currentTick) {
        if ($this->player->closed || !$this->player->isAlive()) {
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
            return;
        }
        $this->tick++;
        $lv = $this->player->getLevel();
        $players = $this->getNearby($lv);
        $px = $this->player->x; $py = $this->player->y + 1.0; $pz = $this->player->z;

        if ($this->tick <= self::PHASE_CHARGE) {
            $this->doCharge($lv, $players, $px, $py, $pz);
        } elseif ($this->tick <= self::PHASE_LAUNCH) {
            $this->doLaunch($lv, $players, $px, $py, $pz);
        } elseif ($this->tick <= self::PHASE_FADE) {
            if ($this->tick === self::PHASE_FADE) {
                $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
            }
        }
    }

    private function doCharge($lv, $players, $px, $py, $pz) {
        $t    = $this->tick;
        $prog = $t / self::PHASE_CHARGE;

        $r = 0.3 + $prog * 1.8;
        for ($ring = 0; $ring < 2; $ring++) {
            $rr  = $r * (0.6 + $ring * 0.4);
            $pts = 8 + $ring * 4;
            for ($i = 0; $i < $pts; $i++) {
                $a = ($i/$pts) * M_PI * 2 + $t * 0.3 + $ring * 1.0;
                $lv->addParticle(new DustParticle(new Vector3(
                    $px + cos($a)*$rr, $py + sin($a)*$rr*0.6, $pz + sin($a)*$rr
                ), 100, 220, 255));
            }
        }

        if ($t % 5 === 0) {
            $this->spawnLightningAt($lv, $players,
                $px + (mt_rand(-10,10)/10), $py - 0.5, $pz + (mt_rand(-10,10)/10)
            );
        }

        if ($t % 3 === 0) {
            for ($i = 0; $i < 4; $i++) {
                $a = ($i/4)*M_PI*2 + $t*0.4;
                $d = 0.5 + $prog * 1.5;
                for ($seg = 0; $seg < 3; $seg++) {
                    $sd = ($seg/2)*$d;
                    $lv->addParticle(new DustParticle(new Vector3(
                        $px + cos($a)*$sd + (mt_rand(-8,8)/10)*($seg/2.0),
                        $py - 0.8,
                        $pz + sin($a)*$sd + (mt_rand(-8,8)/10)*($seg/2.0)
                    ), 200, 240, 255));
                }
            }
            $lv->addSound(new ClickSound(new Vector3($px, $py, $pz)));
        }

        if ($t % 8 === 0) $lv->addSound(new FizzSound(new Vector3($px, $py, $pz)));

        if ($t === self::PHASE_CHARGE) {
            for ($i = 0; $i < 12; $i++) {
                $a = ($i/12)*M_PI*2;
                $lv->addParticle(new DustParticle(new Vector3(
                    $px + cos($a)*2.0, $py + sin($a)*1.0, $pz + sin($a)*2.0
                ), 255, 255, 255));
            }
            $lv->addParticle(new InstantEnchantParticle(new Vector3($px, $py, $pz)));
            $lv->addSound(new GhastSound(new Vector3($px, $py, $pz)));
            $lv->addSound(new AnvilUseSound(new Vector3($px, $py, $pz)));
            $pk = new LevelEventPacket(); $pk->evid = 2002; $pk->data = 6737151;
            $pk->x = (float)$px; $pk->y = (float)$py; $pk->z = (float)$pz;
            foreach ($players as $pl) { $pl->dataPacket($pk); }
        }
    }

    private function doLaunch($lv, $players, $px, $py, $pz) {
        $t = $this->tick - self::PHASE_CHARGE;

        $this->headX += $this->dirX * self::TRAVEL_SPEED;
        $this->headZ += $this->dirZ * self::TRAVEL_SPEED;
        $this->headY  = $py;

        $hx = $this->headX; $hy = $this->headY; $hz = $this->headZ;

        $traveled = $t * self::TRAVEL_SPEED;
        $cylSteps = min((int)($traveled / 1.5) + 1, 8);
        for ($s = 0; $s <= $cylSteps; $s++) {
            $prog2 = $s / max(1, $cylSteps);
            $rx    = $px + $this->dirX * $traveled * $prog2;
            $rz    = $pz + $this->dirZ * $traveled * $prog2;
            $perpX = -$this->dirZ;
            $perpZ =  $this->dirX;
            for ($i = 0; $i < 8; $i++) {
                $a   = ($i/8) * M_PI * 2 + $t * 0.4;
                $ox  = cos($a) * self::CYL_RADIUS;
                $oy  = sin($a) * self::CYL_RADIUS * 0.6;
                $wx  = $rx + $perpX * $ox;
                $wy  = $hy + $oy;
                $wz  = $rz + $perpZ * $ox;
                $lv->addParticle(new DustParticle(new Vector3($wx, $wy, $wz), 80, 200, 255));
            }
        }

        if ($t % 2 === 0) {
            $this->spawnLightningAt($lv, $players, $hx, $hy - 0.5, $hz);
        }
        if ($t % 3 === 0) {
            $perpX = -$this->dirZ; $perpZ = $this->dirX;
            $off   = (mt_rand(-8,8)/10);
            $this->spawnLightningAt($lv, $players,
                $hx + $perpX * $off, $hy - 0.5, $hz + $perpZ * $off
            );
        }

        $lv->addParticle(new InstantEnchantParticle(new Vector3($hx, $hy, $hz)));
        $lv->addParticle(new DustParticle(new Vector3($hx, $hy, $hz), 255, 255, 255));

        if ($t % 4 === 0) $lv->addSound(new ClickSound(new Vector3($hx, $hy, $hz)));

        $this->checkHits($lv, $hx, $hy, $hz);

        $distTraveled = sqrt(($hx-$px)**2 + ($hz-$pz)**2);
        if ($distTraveled >= $this->range) {
            for ($i = 0; $i < 14; $i++) {
                $a = ($i/14)*M_PI*2;
                $lv->addParticle(new DustParticle(new Vector3(
                    $hx + cos($a)*2.0, $hy, $hz + sin($a)*2.0
                ), 200, 240, 255));
            }
            $lv->addSound(new ExplodeSound(new Vector3($hx, $hy, $hz)));
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
        }
    }

    private function spawnLightningAt($lv, $players, $x, $y, $z) {
        $eid = self::newEid();
        $this->lightningEids[] = $eid;
        $pk = new AddEntityPacket();
        $pk->eid  = $eid;
        $pk->type = self::LIGHTNING_TYPE;
        $pk->x    = (float)$x; $pk->y = (float)$y; $pk->z = (float)$z;
        $pk->speedX = 0.0; $pk->speedY = 0.0; $pk->speedZ = 0.0;
        $pk->yaw = 0.0; $pk->pitch = 0.0;
        $pk->metadata = [];
        foreach ($players as $pl) { $pl->dataPacket($pk); }
        $this->plugin->getServer()->getScheduler()->scheduleDelayedTask(
            new GoroDelayedRemoveTask($this->plugin, $lv, [$eid]), 15
        );
    }

    private function checkHits($lv, $hx, $hy, $hz) {
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
            $dist = sqrt(($entity->x-$hx)**2 + ($entity->y-$hy)**2 + ($entity->z-$hz)**2);
            if ($dist > self::CYL_RADIUS + 0.5) continue;
            $this->hitTargets[$eId] = true;
            $ev = new EntityDamageByEntityEvent($this->player, $entity, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->damage);
            $entity->attack($this->damage, $ev);
            $slow = Effect::getEffect(Effect::SLOWNESS);
            $slow->setAmplifier(3); $slow->setDuration(60); $slow->setVisible(false);
            $fatigue = Effect::getEffect(Effect::MINING_FATIGUE);
            $fatigue->setAmplifier(2); $fatigue->setDuration(50); $fatigue->setVisible(false);
            if ($entity instanceof Player) {
                BaseFruit::staticSafeAddEffect($this->player, $entity, $slow);
                BaseFruit::staticSafeAddEffect($this->player, $entity, $fatigue);
                $entity->sendTip(TextFormat::YELLOW . TextFormat::BOLD . "EL THOR! STRUCK!");
            }
            $dx = $entity->x-$hx; $dz = $entity->z-$hz;
            $len = sqrt($dx*$dx+$dz*$dz);
            if ($len > 0) BaseFruit::staticSafeSetMotion($this->player, $entity, new Vector3($dx/$len*1.5, 0.7, $dz/$len*1.5));
        }
    }

    private function getNearby($lv) {
        $out = [];
        $px = $this->player->x; $pz = $this->player->z;
        foreach ($lv->getPlayers() as $p) {
            if (abs($p->x-$px) <= self::VIEW_RANGE && abs($p->z-$pz) <= self::VIEW_RANGE) $out[] = $p;
        }
        return $out;
    }
}

class RaigoDebrisTask extends Task {

    private $plugin;
    private $level;
    private $debris;
    private $groundY;
    private $tick = 0;
    private $maxTicks = 35;
    private $cleaned = false;

    public function __construct($plugin, Level $level, array $debris, $groundY) {
        $this->plugin = $plugin;
        $this->level = $level;
        $this->debris = $debris;
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
                $this->level->addParticle(new DustParticle(
                    new Vector3($d["x"], $d["y"] + 0.2, $d["z"]),
                    100, 200, 255
                ));
            }
            if ($this->tick % 3 === 0) {
                $this->level->addParticle(new InstantEnchantParticle(
                    new Vector3($d["x"], $d["y"] + 0.3, $d["z"])
                ));
            }
            if ($this->tick % 5 === 0) {
                $this->level->addParticle(new DustParticle(
                    new Vector3($d["x"] + (mt_rand(-3, 3) / 10), $d["y"] + 0.5, $d["z"] + (mt_rand(-3, 3) / 10)),
                    200, 240, 255
                ));
            }
        }

        $toRemove = BlockEffects::tickDebris($this->debris, $this->level, $this->groundY, 0.06, 0.95);
        foreach ($toRemove as $eid) {
            unset($this->debris[$eid]);
        }
    }

    private function cleanup() {
        if ($this->cleaned) return;
        $this->cleaned = true;
        BlockEffects::voidAndRemove($this->plugin, $this->level, array_keys($this->debris));
        $this->debris = [];
    }
}