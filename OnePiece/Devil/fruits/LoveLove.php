<?php

namespace OnePiece\Devil\fruits;

use OnePiece\Devil\BaseFruit;
use OnePiece\Devil\Main;
use OnePiece\Devil\BlockEffects;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\math\Vector3;
use pocketmine\entity\Entity;
use pocketmine\utils\TextFormat;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\scheduler\Task;
use pocketmine\level\Level;
use pocketmine\level\particle\HeartParticle;
use pocketmine\level\particle\PortalParticle;
use pocketmine\level\particle\InstantEnchantParticle;
use pocketmine\level\particle\TerrainParticle;
use pocketmine\level\particle\HugeExplodeParticle;
use pocketmine\level\sound\FizzSound;
use pocketmine\level\sound\ExplodeSound;
use pocketmine\level\sound\PopSound;
use pocketmine\level\sound\GhastShootSound;
use pocketmine\block\Block;
use OnePiece\NPC\NPCEntity;
use OnePieceTrades\Factory\FactoryEntity;

class LoveLove extends BaseFruit {

    public function getId() { return "love_love"; }
    public function getDisplayName() { return "Love-Love Fruit"; }
    public function getDescription() { return "Mero Mero no Mi - Control the power of attraction."; }
    public function getType() { return "paramecia"; }
    public function getRarity() { return "legendary"; }

    public function getAbilityNames() {
        return [
            "ability1" => "Mero Mero Twister",
            "ability2" => "Love Heart Zone"
        ];
    }

    public function getAbilityCooldowns() {
        return [
            "ability1" => 10.0,
            "ability2" => 25.0
        ];
    }

    public function useAbility(Player $player, $ability) {
        switch ($ability) {
            case "ability1":
                return $this->meroTwister($player);
            case "ability2":
                return $this->loveHeart($player);
        }
        return 0;
    }

    private function meroTwister(Player $player) {
        $mult = $this->getCombinedMultiplier($player);
        $damage = min(9.0, 4.0 * $mult);
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask(new LoveTornadoTask($this->plugin, $player, $damage), 1);
        $player->sendTip(TextFormat::LIGHT_PURPLE . TextFormat::BOLD . "MERO MERO TWISTER!");
        $player->getLevel()->addSound(new GhastShootSound($player->getPosition()));
        return $this->getAbilityCooldowns()["ability1"];
    }

    private function loveHeart(Player $player) {
        $radius = $this->getMasteryRange($player, 6.0);
        $mult = $this->getCombinedMultiplier($player);
        $dmg = 1.0 * $mult;
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask(new LoveHeartTask($this->plugin, $player, $radius, $dmg), 1);
        $player->sendTip(TextFormat::LIGHT_PURPLE . TextFormat::BOLD . "LOVE HEART ZONE!");
        $player->getLevel()->addSound(new PopSound($player->getPosition()));
        return $this->getAbilityCooldowns()["ability2"];
    }

    public function onEquip(Player $player) {
        $player->sendMessage(TextFormat::LIGHT_PURPLE . "=== Mero-Mero no Mi ===");
        $player->sendMessage(TextFormat::WHITE . "Love-Love Fruit - Beautiful Destruction (Legendary)");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::LIGHT_PURPLE . "[Ability 1]: " . TextFormat::WHITE . "MERO MERO TWISTER");
        $player->sendMessage(TextFormat::GRAY . "  Pink tornado that pulls and then rockets forward");
        $player->sendMessage(TextFormat::LIGHT_PURPLE . "[Ability 2]: " . TextFormat::WHITE . "LOVE HEART ZONE");
        $player->sendMessage(TextFormat::GRAY . "  Channeling zone that stuns and drains life");
        $player->sendMessage(TextFormat::LIGHT_PURPLE . "========================");
    }

    public function onUnequip(Player $player) {
        $player->sendMessage(TextFormat::GRAY . "The charm fades away...");
    }
}

class LoveTornadoTask extends Task {
    private $plugin, $player, $level, $damage;
    private $ticksRan = 0;
    private $maxTicks = 80;
    private $angle = 0.0;
    private $launched = false;
    private $x, $y, $z, $dirX, $dirZ;
    private $debris = [];
    private $trapped = [];

    public function __construct($plugin, Player $player, $damage) {
        $this->plugin = $plugin; $this->player = $player;
        $this->level = $player->getLevel(); $this->damage = $damage;
        $this->x = $player->x; $this->y = $player->y; $this->z = $player->z;
    }

    public function onRun($currentTick) {
        if ($this->player === null || !$this->player->isOnline()) { $this->cleanup(); return; }
        $this->ticksRan++;
        $this->angle += 0.5;
        if (!$this->launched) {
            $this->x = $this->player->x; $this->y = $this->player->y; $this->z = $this->player->z;
            $this->trapEntities(8.0, true);
            if ($this->ticksRan >= 30) {
                $this->launched = true;
                $dir = $this->player->getDirectionVector();
                $speed = 1.5 + ($this->plugin->getMasteryManager()->getLevel($this->player->getName()) / 100);
                $this->dirX = $dir->x * $speed;
                $this->dirZ = $dir->z * $speed;
            }
        } else {
            $this->x += $this->dirX; $this->z += $this->dirZ;
            $this->trapEntities(5.0, false);
            if ($this->checkWall() || $this->ticksRan >= $this->maxTicks) { $this->explode(); return; }
        }
        $this->spawnVFX();
        if ($this->ticksRan % 4 === 0) {
            $eid = BlockEffects::newEid();
            $blocks = [35, 95]; 
            BlockEffects::sendSpawn($this->level, $eid, $blocks[array_rand($blocks)], 6, $this->x + cos($this->angle)*3, $this->y + mt_rand(0, 20)/10, $this->z + sin($this->angle)*3);
            $this->debris[$eid] = ["eid" => $eid, "angle" => $this->angle, "radius" => 3.0, "baseY" => $this->y, "life" => 15, "tick" => 0];
        }
        if (!empty($this->debris)) {
            $toRemove = BlockEffects::tickSpiralDebris($this->debris, $this->level, $this->x, $this->z, 0.4, 0.15, 0.05);
            foreach ($toRemove as $rid) unset($this->debris[$rid]);
        }
    }

    private function spawnVFX() {
        for ($i = 0; $i < 5; $i++) {
            $a = $this->angle + ($i * M_PI / 2.5);
            $v = new Vector3($this->x + cos($a)*3.5, $this->y + mt_rand(0,3), $this->z + sin($a)*3.5);
            $this->level->addParticle(new HeartParticle($v));
            $this->level->addParticle(new InstantEnchantParticle($v));
        }
        if ($this->ticksRan % 5 === 0) $this->level->addSound(new FizzSound(new Vector3($this->x, $this->y, $this->z)));
    }

    private function trapEntities($range, $orbit) {
        $center = new Vector3($this->x, $this->y, $this->z);
        foreach ($this->level->getEntities() as $e) {
            if ($e === $this->player || !$e->isAlive()) continue;
            if ($e instanceof Player) { if (!$this->plugin->canTargetPlayer($this->player->getName(), $e)) continue; }
            elseif (!($e instanceof NPCEntity) && !($e instanceof FactoryEntity)) continue;
            if ($e->distance($center) <= $range) {
                $this->trapped[$e->getId()] = $e;
                $dx = $this->x - $e->x; $dz = $this->z - $e->z;
                $len = sqrt($dx*$dx + $dz*$dz);
                $hoverY = $this->y + 2.2; $motY = ($e->y < $hoverY) ? 0.2 : -0.1;
                if ($orbit && $len > 0.5) {
                    BaseFruit::staticSafeSetMotion($this->player, $e, new Vector3(($dx/$len)*0.35 + (-$dz/$len)*0.55, $motY, ($dz/$len)*0.35 + ($dx/$len)*0.55));
                } else { BaseFruit::staticSafeSetMotion($this->player, $e, new Vector3($this->dirX * 0.8, $motY, $this->dirZ * 0.8)); }
                if ($this->ticksRan % 10 === 0) $e->attack($this->damage * 0.4, new EntityDamageByEntityEvent($this->player, $e, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->damage * 0.4));
            }
        }
    }

    private function checkWall() {
        $block = $this->level->getBlock(new Vector3($this->x, $this->y + 1, $this->z));
        return ($block->getId() !== 0 && $block->getId() !== 8 && $block->getId() !== 9);
    }

    private function explode() {
        $pos = new Vector3($this->x, $this->y, $this->z);
        $this->level->addSound(new ExplodeSound($pos));
        $this->level->addParticle(new HugeExplodeParticle($pos));
        foreach ($this->trapped as $e) {
            if ($e->isAlive() && $e->distance($pos) <= 7) {
                $e->attack($this->damage, new EntityDamageByEntityEvent($this->player, $e, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->damage));
                BaseFruit::staticSafeSetMotion($this->player, $e, new Vector3(0, 1.2, 0));
            }
        }
        $this->cleanup();
    }

    private function cleanup() {
        if (!empty($this->debris)) BlockEffects::voidAndRemove($this->plugin, $this->level, array_keys($this->debris));
        $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
    }
}

class LoveHeartTask extends Task {
    private $plugin, $player, $level, $cx, $cy, $cz, $radius, $tickDmg;
    private $ticksRan = 0;
    private $maxTicks = 200;
    private $heartEids = [];
    private $rotation = 0.0;
    private $offsets = [[0,2,0], [1,2,0], [-1,2,0], [0,1,0], [1,3,0], [-1,3,0], [2,3,0], [-2,3,0], [2,4,0], [-2,4,0], [1,5,0], [-1,5,0], [0,4,0]];

    public function __construct($plugin, Player $player, $radius, $tickDmg) {
        $this->plugin = $plugin; $this->player = $player; $this->level = $player->getLevel();
        $this->cx = $player->x; $this->cy = $player->y; $this->cz = $player->z;
        $this->radius = $radius; $this->tickDmg = $tickDmg;
        foreach ($this->offsets as $o) {
            $eid = BlockEffects::newEid();
            BlockEffects::sendSpawn($this->level, $eid, 35, 6, $this->cx + $o[0]*0.5, $this->cy + $o[1]*0.5 + 1, $this->cz + $o[2]*0.5);
            $this->heartEids[] = $eid;
        }
    }

    public function onRun($currentTick) {
        if ($this->player === null || !$this->player->isOnline() || $this->player->closed) { $this->cleanup(); return; }
        $this->ticksRan++;
        if ($this->ticksRan > $this->maxTicks) { $this->cleanup(); return; }
        
        $this->rotation += 0.15;
        $center = new Vector3($this->cx, $this->cy, $this->cz);

        $this->player->setMotion(new Vector3(0, 0, 0));

        foreach ($this->heartEids as $i => $eid) {
            $o = $this->offsets[$i];
            $rx = $o[0]*0.5 * cos($this->rotation) - $o[2]*0.5 * sin($this->rotation);
            $rz = $o[0]*0.5 * sin($this->rotation) + $o[2]*0.5 * cos($this->rotation);
            BlockEffects::sendMove($this->level, $eid, $this->cx + $rx, $this->cy + $o[1]*0.5 + 1, $this->cz + $rz, $this->rotation * 50);
        }

        for ($i = 0; $i < 15; $i++) {
            $a = mt_rand(0, 628) / 100; $r = mt_rand(0, (int)($this->radius * 10)) / 10;
            $v = $center->add(cos($a)*$r, 0.1, sin($a)*$r);
            $this->level->addParticle(new HeartParticle($v));
            $this->level->addParticle(new PortalParticle($v));
            if ($i % 5 === 0) $this->level->addParticle(new InstantEnchantParticle($v->add(0, mt_rand(1, 2), 0)));
        }

        foreach ($this->level->getEntities() as $e) {
            if (!$e->isAlive() || $e->closed || $e->distance($center) > $this->radius) continue;
            
            if ($e instanceof Player) {
                if ($e === $this->player || !LoveLove::pvpAllowed($this->player, $e)) {
                    if ($this->ticksRan % 20 === 0) $e->setHealth(min($e->getMaxHealth(), $e->getHealth() + 1.0));
                    continue;
                }
            } elseif (!($e instanceof NPCEntity) && !($e instanceof FactoryEntity)) {
                continue;
            }

            $e->setMotion(new Vector3(0, 0, 0));
            
            if ($this->ticksRan % 20 === 0) {
                $this->level->addParticle(new TerrainParticle($e->add(0, 1, 0), Block::get(Block::STONE)));
                $e->attack($this->tickDmg, new EntityDamageByEntityEvent($this->player, $e, EntityDamageEvent::CAUSE_MAGIC, $this->tickDmg));
            }
        }
        if ($this->ticksRan % 15 === 0) $this->level->addSound(new FizzSound($center));
    }

    private function cleanup() {
        if (!empty($this->heartEids)) BlockEffects::voidAndRemove($this->plugin, $this->level, $this->heartEids);
        $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
    }
}