<?php
namespace OnePiece\Devil\fruits;

use OnePiece\Devil\BaseFruit;
use OnePiece\Devil\Main;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\math\Vector3;
use pocketmine\entity\Effect;
use pocketmine\utils\TextFormat;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\scheduler\Task;
use pocketmine\level\particle\DustParticle;
use pocketmine\level\particle\MobSpellParticle;
use pocketmine\level\particle\InstantEnchantParticle;
use pocketmine\level\particle\ExplodeParticle;
use pocketmine\level\particle\SmokeParticle;
use pocketmine\level\particle\CriticalParticle;
use pocketmine\level\sound\PopSound;
use pocketmine\level\sound\ExplodeSound;
use pocketmine\level\sound\AnvilUseSound;
use pocketmine\level\sound\GhastSound;
use pocketmine\level\sound\FizzSound;
use pocketmine\level\sound\ClickSound;

class YamiYami extends BaseFruit {

    private $blackHoleActive = [];

    public function getId()          { return "yami_yami"; }
    public function getDisplayName() { return "Dark-Dark Fruit"; }
    public function getDescription() { return "The strongest Logia - Marshal D. Teach's darkness that crushes all."; }
    public function getType()        { return "logia"; }
    public function getRarity()      { return "rare"; }

    public function getAbilityNames() {
        return ["ability1" => "Kurouzu", "ability2" => "Black Hole"];
    }

    public function getAbilityCooldowns() {
        return ["ability1" => 8.0, "ability2" => 18.0];
    }

    public function useAbility(Player $player, $ability) {
        switch ($ability) {
            case "ability1": return $this->kurouzu($player);
            case "ability2": return $this->blackHole($player);
        }
        return 0;
    }
    
    public function dealDamagePublic(Player $attacker, $target, $damage) {
    $this->dealAbilityDamage($attacker, $target, $damage);
}

private function kurouzu(Player $player) {
    if (!$this->checkMastery($player, "ability1")) return 0;

    $target = $this->findNearestTarget($player, 12.0);

    if ($target === null) {
        $player->sendTip(TextFormat::DARK_GRAY . "Kurouzu - No target nearby!");
        return 0;
    }

    $mult     = min(1.5, $this->getHakiMultiplier($player));
    $punchDmg = min(10.0, 4.5 * $mult);
    $plugin   = $this->plugin;
    $fruit    = $this;

    $player->sendTip(TextFormat::DARK_GRAY . "KUROUZU!");

    $lv = $player->getLevel();
    if ($lv !== null) {
        $lv->addSound(new GhastSound(new Vector3($target->x, $target->y, $target->z)));
    }

    Server::getInstance()->getScheduler()->scheduleRepeatingTask(
        new class($player, $target, $punchDmg, $plugin, $fruit) extends Task {

            private $attacker, $target, $punchDmg, $plugin, $fruit;
            private $tick    = 0;
            private $done    = false;
            const PULL_TICKS = 20;
            const RING_R     = 2.0;

            public function __construct($attacker, $target, $punchDmg, $plugin, $fruit) {
                $this->attacker = $attacker;
                $this->target   = $target;
                $this->punchDmg = $punchDmg;
                $this->plugin   = $plugin;
                $this->fruit    = $fruit;
            }

            public function onRun($currentTick) {
                if ($this->done) {
                    Server::getInstance()->getScheduler()->cancelTask($this->getTaskId());
                    return;
                }

                $attacker = $this->attacker;
                $target   = $this->target;

                if (!$attacker->isOnline() || $target->closed || !$target->isAlive()) {
                    Server::getInstance()->getScheduler()->cancelTask($this->getTaskId());
                    return;
                }

                $this->tick++;
                $lv       = $attacker->getLevel();
                $progress = $this->tick / self::PULL_TICKS;
                $tx       = $target->x;
                $ty       = $target->y;
                $tz       = $target->z;
                $ax       = $attacker->x;
                $ay       = $attacker->y;
                $az       = $attacker->z;

                $spinAngle = $this->tick * 0.55;
                $ringR     = self::RING_R * (1.0 - $progress * 0.5);

                if ($lv !== null) {
                    $outerPts = 20;
                    for ($i = 0; $i < $outerPts; $i++) {
                        $a   = ($i / $outerPts) * M_PI * 2 + $spinAngle;
                        $off = mt_rand(-3, 3) / 10.0;
                        $lv->addParticle(new DustParticle(
                            new Vector3(
                                $tx + cos($a) * $ringR + $off,
                                $ty + 1.2 + sin($a * 2) * 0.25 + $off,
                                $tz + sin($a) * $ringR + $off
                            ),
                            23, 29, 37
                        ));
                    }

                    $innerPts = 12;
                    $innerR   = $ringR * 0.5;
                    for ($i = 0; $i < $innerPts; $i++) {
                        $a = ($i / $innerPts) * M_PI * 2 - $spinAngle * 1.4;
                        $lv->addParticle(new DustParticle(
                            new Vector3(
                                $tx + cos($a) * $innerR,
                                $ty + 1.0,
                                $tz + sin($a) * $innerR
                            ),
                            63, 72, 78
                        ));
                    }

                    $suckPts = 8;
                    for ($i = 0; $i < $suckPts; $i++) {
                        $suckProgress = ($this->tick + $i * 2) % 10 / 10.0;
                        $sx = $tx + ($ax - $tx) * $suckProgress;
                        $sy = $ty + 1.0 + ($ay + 1.0 - $ty - 1.0) * $suckProgress;
                        $sz = $tz + ($az - $tz) * $suckProgress;
                        $lv->addParticle(new DustParticle(
                            new Vector3($sx, $sy, $sz),
                            5, 0, 15
                        ));
                    }

                    if ($this->tick % 2 === 0) {
                        for ($i = 0; $i < 5; $i++) {
                            $a  = mt_rand(0, 628) / 100.0;
                            $dr = $ringR * (1.4 + mt_rand(0, 5) / 10.0);
                            $lv->addParticle(new SmokeParticle(
                                new Vector3(
                                    $tx + cos($a) * $dr,
                                    $ty + 0.8 + mt_rand(0, 4) / 10.0,
                                    $tz + sin($a) * $dr
                                )
                            ));
                        }
                        $lv->addSound(new ClickSound(new Vector3($tx, $ty + 1.0, $tz)));
                    }
                }

                $dx   = $ax - $tx;
                $dy   = $ay - $ty;
                $dz   = $az - $tz;
                $dist = sqrt($dx * $dx + $dy * $dy + $dz * $dz);

                if ($dist > 1.8) {
                    $pullStrength = 0.3 + $progress * 0.5;
                    $nx = $dx / $dist;
                    $ny = $dy / $dist;
                    $nz = $dz / $dist;

                    BaseFruit::staticSafeSetMotion($attacker, $target, new Vector3(
                        $nx * $pullStrength,
                        $ny * $pullStrength * 0.5,
                        $nz * $pullStrength
                    ));

                    if ($target instanceof Player) {
                        $target->sendTip(TextFormat::DARK_GRAY . "Being sucked into darkness!");
                    }
                } else {
                    $this->done = true;
                    Server::getInstance()->getScheduler()->cancelTask($this->getTaskId());

                    if ($target instanceof Player && !BaseFruit::pvpAllowed($attacker, $target)) {
                        $attacker->sendTip(TextFormat::DARK_GRAY . "KUROUZU - Blocked!");
                        return;
                    }

                    $dir = $attacker->getDirectionVector();

                    $this->plugin->setAbilityDamage($attacker->getName(), $this->punchDmg);
                    $this->fruit->dealDamagePublic($attacker, $target, $this->punchDmg);

                    BaseFruit::staticSafeSetMotion($attacker, $target, new Vector3(
                        $dir->x * 2.4,
                        0.65,
                        $dir->z * 2.4
                    ));

                    $wither = Effect::getEffect(Effect::WITHER);
                    $wither->setAmplifier(1);
                    $wither->setDuration(60);
                    $wither->setVisible(false);
                    BaseFruit::staticSafeAddEffect($attacker, $target, $wither);

                    $lvv = $attacker->getLevel();
                    if ($lvv !== null) {
                        $pos = $target->getPosition();
                        $pts = 24;
                        for ($i = 0; $i < $pts; $i++) {
                            $a = ($i / $pts) * M_PI * 2;
                            $r = 0.6 + mt_rand(0, 5) / 10.0;
                            $lvv->addParticle(new DustParticle(
                                new Vector3(
                                    $pos->x + cos($a) * $r,
                                    $pos->y + 1.0,
                                    $pos->z + sin($a) * $r
                                ),
                                23, 29, 37
                            ));
                        }
                        $lvv->addParticle(new ExplodeParticle(
                            new Vector3($pos->x, $pos->y + 1.0, $pos->z)
                        ));
                        $lvv->addSound(new ExplodeSound(new Vector3($pos->x, $pos->y, $pos->z)));
                        $lvv->addSound(new AnvilUseSound(new Vector3($pos->x, $pos->y, $pos->z)));
                    }

                    $attacker->sendTip(TextFormat::DARK_GRAY . "KUROUZU! Crushing blow!");
                    if ($target instanceof Player) {
                        $target->sendTip(TextFormat::DARK_GRAY . "KUROUZU! Crushed!");
                    }

                    $this->fruit->grantMasteryExpPublic($attacker);
                    return;
                }

                if ($this->tick >= self::PULL_TICKS) {
                    $this->done = true;
                    Server::getInstance()->getScheduler()->cancelTask($this->getTaskId());

                    if ($target instanceof Player && !BaseFruit::pvpAllowed($attacker, $target)) {
                        $attacker->sendTip(TextFormat::DARK_GRAY . "KUROUZU - Blocked!");
                        return;
                    }

                    $dir = $attacker->getDirectionVector();

                    $this->plugin->setAbilityDamage($attacker->getName(), $this->punchDmg);
                    $this->fruit->dealDamagePublic($attacker, $target, $this->punchDmg);

                    BaseFruit::staticSafeSetMotion($attacker, $target, new Vector3(
                        $dir->x * 2.4,
                        0.65,
                        $dir->z * 2.4
                    ));

                    $wither = Effect::getEffect(Effect::WITHER);
                    $wither->setAmplifier(1);
                    $wither->setDuration(60);
                    $wither->setVisible(false);
                    BaseFruit::staticSafeAddEffect($attacker, $target, $wither);

                    $lvv = $attacker->getLevel();
                    if ($lvv !== null) {
                        $pos = $target->getPosition();
                        $pts = 24;
                        for ($i = 0; $i < $pts; $i++) {
                            $a = ($i / $pts) * M_PI * 2;
                            $r = 0.6 + mt_rand(0, 5) / 10.0;
                            $lvv->addParticle(new DustParticle(
                                new Vector3(
                                    $pos->x + cos($a) * $r,
                                    $pos->y + 1.0,
                                    $pos->z + sin($a) * $r
                                ),
                                23, 29, 37
                            ));
                        }
                        $lvv->addParticle(new ExplodeParticle(
                            new Vector3($pos->x, $pos->y + 1.0, $pos->z)
                        ));
                        $lvv->addSound(new ExplodeSound(new Vector3($pos->x, $pos->y, $pos->z)));
                        $lvv->addSound(new AnvilUseSound(new Vector3($pos->x, $pos->y, $pos->z)));
                    }

                    $attacker->sendTip(TextFormat::DARK_GRAY . "KUROUZU! Crushing blow!");
                    if ($target instanceof Player) {
                        $target->sendTip(TextFormat::DARK_GRAY . "KUROUZU! Crushed!");
                    }

                    $this->fruit->grantMasteryExpPublic($attacker);
                }
            }
        }, 1
    );

    return $this->getAbilityCooldowns()["ability1"];
}

private function blackHole(Player $player) {
    if (!$this->checkMastery($player, "ability2")) return 0;

    $name = $player->getName();
    if (isset($this->blackHoleActive[$name])) return 0;

    $this->blackHoleActive[$name] = true;

    $mult     = min(1.5, $this->getHakiMultiplier($player));
    $tickDmg  = min(2.0, 0.8 * $mult);
    $rx       = 5.5;
    $rz       = 5.5;
    $ry       = 2.5;
    $duration = 80;
    $plugin   = $this->plugin;
    $fruit    = $this;

    $ox = $player->x;
    $oy = $player->y;
    $oz = $player->z;

    $lv = $player->getLevel();

    $player->sendTip(TextFormat::DARK_GRAY . "BLACK HOLE!");

    if ($lv !== null) {
        $lv->addSound(new GhastSound(new Vector3($ox, $oy, $oz)));
        $lv->addSound(new AnvilUseSound(new Vector3($ox, $oy, $oz)));

        $impactPts = 20;
        for ($i = 0; $i < $impactPts; $i++) {
            $a  = ($i / $impactPts) * M_PI * 2;
            $ir = 0.5 + mt_rand(0, 8) / 10.0;
            $lv->addParticle(new DustParticle(
                new Vector3($ox + cos($a) * $ir, $oy + 0.3, $oz + sin($a) * $ir),
                23, 29, 37
            ));
        }
        $lv->addParticle(new ExplodeParticle(new Vector3($ox, $oy + 0.3, $oz)));
    }

    Server::getInstance()->getScheduler()->scheduleRepeatingTask(
        new class($player, $name, $ox, $oy, $oz, $tickDmg, $rx, $rz, $ry, $duration, $plugin, $fruit) extends Task {

            private $player, $playerName;
            private $ox, $oy, $oz;
            private $tickDamage, $rx, $rz, $ry, $duration;
            private $plugin, $fruit;
            private $tick         = 0;
            private $hitCooldowns = [];
            private $trappedEntities = [];

            public function __construct(
                $player, $name, $ox, $oy, $oz,
                $tickDmg, $rx, $rz, $ry, $duration, $plugin, $fruit
            ) {
                $this->player     = $player;
                $this->playerName = $name;
                $this->ox         = $ox;
                $this->oy         = $oy;
                $this->oz         = $oz;
                $this->tickDamage = $tickDmg;
                $this->rx         = $rx;
                $this->rz         = $rz;
                $this->ry         = $ry;
                $this->duration   = $duration;
                $this->plugin     = $plugin;
                $this->fruit      = $fruit;
            }

            public function onRun($currentTick) {
                $this->tick++;

                if ($this->tick >= $this->duration) {
                    $this->fruit->clearBlackHole($this->playerName);
                    Server::getInstance()->getScheduler()->cancelTask($this->getTaskId());
                    return;
                }

                if (!$this->player->isOnline()) {
                    $this->fruit->clearBlackHole($this->playerName);
                    Server::getInstance()->getScheduler()->cancelTask($this->getTaskId());
                    return;
                }

                $lv = $this->player->getLevel();
                if ($lv === null) {
                    $this->fruit->clearBlackHole($this->playerName);
                    Server::getInstance()->getScheduler()->cancelTask($this->getTaskId());
                    return;
                }

                $ox = $this->ox;
                $oy = $this->oy;
                $oz = $this->oz;
                $t  = $this->tick;

                $particleY = $oy + 0.35;

                $expandMult = min(1.0, $t / 15.0);
                $curRx      = $this->rx * $expandMult;
                $curRz      = $this->rz * $expandMult;

                $outerPts = 28;
                for ($i = 0; $i < $outerPts; $i++) {
                    $a   = ($i / $outerPts) * M_PI * 2 - $t * 0.16;
                    $off = mt_rand(-2, 2) / 10.0;
                    $lv->addParticle(new DustParticle(
                        new Vector3(
                            $ox + cos($a) * $curRx + $off,
                            $particleY,
                            $oz + sin($a) * $curRz + $off
                        ),
                        23, 29, 37
                    ));
                }

                $midPts = 20;
                for ($i = 0; $i < $midPts; $i++) {
                    $a = ($i / $midPts) * M_PI * 2 + $t * 0.22;
                    $lv->addParticle(new DustParticle(
                        new Vector3(
                            $ox + cos($a) * $curRx * 0.65,
                            $particleY,
                            $oz + sin($a) * $curRz * 0.65
                        ),
                        63, 72, 78
                    ));
                }

                $innerPts = 10;
                for ($i = 0; $i < $innerPts; $i++) {
                    $a = ($i / $innerPts) * M_PI * 2 - $t * 0.30;
                    $lv->addParticle(new DustParticle(
                        new Vector3(
                            $ox + cos($a) * $curRx * 0.28,
                            $particleY,
                            $oz + sin($a) * $curRz * 0.28
                        ),
                        5, 0, 15
                    ));
                }

                if ($t % 3 === 0) {
                    $fillPts = 12;
                    for ($i = 0; $i < $fillPts; $i++) {
                        $fa = mt_rand(0, 628) / 100.0;
                        $fr = mt_rand(10, 90) / 100.0 * $curRx;
                        $lv->addParticle(new DustParticle(
                            new Vector3(
                                $ox + cos($fa) * $fr,
                                $particleY + mt_rand(-5, 5) / 100.0,
                                $oz + sin($fa) * $fr
                            ),
                            10, 5, 20
                        ));
                    }
                }

                if ($t % 4 === 0) {
                    for ($i = 0; $i < 5; $i++) {
                        $sa = mt_rand(0, 628) / 100.0;
                        $sr = $curRx * (0.3 + mt_rand(0, 7) / 10.0);
                        $lv->addParticle(new SmokeParticle(
                            new Vector3(
                                $ox + cos($sa) * $sr,
                                $particleY + mt_rand(0, 3) / 10.0,
                                $oz + sin($sa) * $sr
                            )
                        ));
                    }
                }

                if ($t % 6 === 0) {
                    $lv->addSound(new FizzSound(new Vector3($ox, $oy, $oz)));
                }

                $attacker = $this->player->isOnline() ? $this->player : null;
                $rxFull   = $this->rx;
                $rzFull   = $this->rz;
                $ryFull   = $this->ry;

                foreach ($lv->getPlayers() as $target) {
                    if ($target->getName() === $this->playerName) continue;
                    if (!$target->isAlive() || $target->closed) continue;

                    $dx = $target->x - $ox;
                    $dz = $target->z - $oz;
                    $dy = $target->y - $oy;

                    $inEllipse = (($dx * $dx) / ($rxFull * $rxFull))
                               + (($dz * $dz) / ($rzFull * $rzFull));

                    if ($inEllipse > 1.0 || abs($dy) > $ryFull) {
                        $tKey = strtolower($target->getName());
                        unset($this->trappedEntities[$tKey]);
                        continue;
                    }

                    if (!BaseFruit::pvpAllowed($this->player, $target)) continue;

                    $tKey = strtolower($target->getName());
                    $this->trappedEntities[$tKey] = true;

                    BaseFruit::staticSafeSetMotion($attacker ?? $target, $target, new Vector3(0, 0, 0));

                    $slow = Effect::getEffect(Effect::SLOWNESS);
                    $slow->setAmplifier(127);
                    $slow->setDuration(5);
                    $slow->setVisible(false);
                    BaseFruit::staticSafeAddEffect($attacker ?? $target, $target, $slow);

                    if ($t % 3 === 0) {
                        if (isset($this->hitCooldowns[$tKey])
                            && ($currentTick - $this->hitCooldowns[$tKey]) < 6) continue;
                        $this->hitCooldowns[$tKey] = $currentTick;

                        if ($attacker !== null) {
                            $this->plugin->setAbilityDamage($attacker->getName(), $this->tickDamage);
                        }

                        $ev = new EntityDamageByEntityEvent(
                            $attacker ?? $target,
                            $target,
                            EntityDamageEvent::CAUSE_MAGIC,
                            $this->tickDamage
                        );
                        $target->attack($this->tickDamage, $ev);

                        $target->sendTip(TextFormat::DARK_GRAY . "Trapped in darkness!");
                    }
                }

                $npcClass     = "OnePiece\\NPC\\NPCEntity";
                $factoryClass = "OnePieceTrades\\Factory\\FactoryEntity";
                $sharkClass   = "OnePiece\\SeaEvent\\SeaSharkEntity";
                $beastClass   = "OnePiece\\SeaEvent\\SeaBeastEntity";

                foreach ($lv->getEntities() as $entity) {
                    if ($entity instanceof Player) continue;
                    if ($entity->closed || !$entity->isAlive()) continue;
                    if (!($entity instanceof $npcClass) && !($entity instanceof $factoryClass)
                        && !($entity instanceof $sharkClass) && !($entity instanceof $beastClass)) continue;

                    $dx = $entity->x - $ox;
                    $dz = $entity->z - $oz;
                    $dy = $entity->y - $oy;

                    $inEllipse = (($dx * $dx) / ($rxFull * $rxFull))
                               + (($dz * $dz) / ($rzFull * $rzFull));

                    if ($inEllipse > 1.0 || abs($dy) > $ryFull) continue;

                    $entity->setMotion(new Vector3(0, 0, 0));

                    if ($t % 3 !== 0) continue;

                    $eKey = spl_object_hash($entity);
                    if (isset($this->hitCooldowns[$eKey])
                        && ($currentTick - $this->hitCooldowns[$eKey]) < 6) continue;
                    $this->hitCooldowns[$eKey] = $currentTick;

                    if ($attacker !== null) {
                        $this->plugin->setAbilityDamage($attacker->getName(), $this->tickDamage);
                    }
                    $ev = new EntityDamageByEntityEvent(
                        $attacker ?? $entity,
                        $entity,
                        EntityDamageEvent::CAUSE_MAGIC,
                        $this->tickDamage
                    );
                    $entity->attack($this->tickDamage, $ev);
                }
            }
        }, 1
    );

    $this->grantMasteryExpPublic($player);
    return $this->getAbilityCooldowns()["ability2"];
}

    public function clearBlackHole($name) {
        unset($this->blackHoleActive[$name]);
    }

    public function grantMasteryExpPublic(Player $player) {
        $this->grantMasteryExp($player);
    }

    public function onEquip(Player $player) {
        $player->sendMessage(TextFormat::DARK_GRAY . "=== Yami-Yami no Mi ===");
        $player->sendMessage(TextFormat::GRAY . "The Darkness - Blackbeard's power that crushes all");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::DARK_GRAY . "[Tap]: " . TextFormat::WHITE . "Kurouzu");
        $player->sendMessage(TextFormat::GRAY . "  Pull the nearest enemy in then crush them");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::DARK_GRAY . "[Sneak+Tap]: " . TextFormat::WHITE . "Black Hole");
        $player->sendMessage(TextFormat::GRAY . "  Slam darkness into the ground, pool damages all inside");
        $player->sendMessage(TextFormat::DARK_GRAY . "========================");
    }

    public function onUnequip(Player $player) {
        $name = $player->getName();
        unset($this->blackHoleActive[$name]);
        $player->sendMessage(TextFormat::GRAY . "The darkness retreats...");
    }
}