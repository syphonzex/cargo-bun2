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

class InuInu extends BaseFruit {

    public function getId()          { return "inu_inu"; }
    public function getDisplayName() { return "Wolf-Wolf Fruit"; }
    public function getDescription() { return "Wolf Fruit - hunt with predator instincts, rend with savage claws."; }
    public function getType()        { return "zoan"; }
    public function getRarity()      { return "common"; }

    public function getAbilityNames() {
        return ["ability1" => "Wolf Fang", "ability2" => "Predator Howl"];
    }
    public function getAbilityCooldowns() {
        return ["ability1" => 4.0, "ability2" => 16.0];
    }

    public function useAbility(Player $player, $ability) {
        switch ($ability) {
            case "ability1": return $this->wolfFang($player);
            case "ability2": return $this->predatorHowl($player);
        }
        return 0;
    }

    // Wolf Fang – lunge at target, claw slash
private function wolfFang(Player $player) {
    $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
    $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

    $target = $this->findFrontTarget($player, 7);
    $vfx    = $this->getVFX();

    if ($target === null) {
        $dir = $player->getDirectionVector();
        $player->setMotion(new Vector3($dir->x * 1.5, 0.2, $dir->z * 1.5));
        $player->sendTip(TextFormat::GRAY . "Wolf Fang - lunge!");
        return 1.5;
    }

    if ($target instanceof Player) {
        if (!$this->plugin->canTargetPlayer($player->getName(), $target)) {
            $dir = $player->getDirectionVector();
            $player->setMotion(new Vector3($dir->x * 1.5, 0.2, $dir->z * 1.5));
            $reason = $this->plugin->getTargetBlockReason($player->getName(), $target);
            if ($reason !== null) $player->sendTip($reason);
            return 1.5;
        }
    }

    $mult   = min(1.5, $this->getHakiMultiplier($player));
    $damage = min(4.0, 2.0 * $mult);

    $ev = new EntityDamageByEntityEvent($player, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage);
    $target->attack($damage, $ev);

    $dir = $player->getDirectionVector();
    $this->safeSetMotion($player, $target, new Vector3($dir->x * 0.9, 0.4, $dir->z * 0.9));
    $player->setMotion(new Vector3($dir->x * 0.8, 0.1, $dir->z * 0.8));

    $bleed = Effect::getEffect(Effect::WITHER);
    $bleed->setAmplifier(0); $bleed->setDuration(30); $bleed->setVisible(false);
    $this->safeAddEffect($player, $target, $bleed);

    $player->sendTip(TextFormat::GRAY . "WOLF FANG!");
    if ($target instanceof Player) {
        $target->sendTip(TextFormat::RED . "Ripped by " . $player->getName() . "'s claws!");
    }

    if ($vfx) {
        $pos = $target->getPosition();
        $vfx->getFruitVFX()->spawnWolfSlash($target->getLevel(), $pos->x, $pos->y, $pos->z, $dir->x, $dir->z);
    }
    return $this->getAbilityCooldowns()["ability1"];
}

private function predatorHowl(Player $player) {
    $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
    $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

    $radius = 7.0; $hits = 0;
    $pos = $player->getPosition();

    $speed = Effect::getEffect(Effect::SPEED);
    $speed->setAmplifier(2); $speed->setDuration(100); $speed->setVisible(false);
    $player->addEffect($speed);

    $str = Effect::getEffect(Effect::STRENGTH);
    $str->setAmplifier(1); $str->setDuration(80); $str->setVisible(false);
    $player->addEffect($str);

    foreach ($this->getNearbyTargets($player, $radius) as $t) {
        if ($t instanceof Player) {
            if (!$this->plugin->canTargetPlayer($player->getName(), $t)) {
                $reason = $this->plugin->getTargetBlockReason($player->getName(), $t);
                if ($reason !== null) $player->sendTip($reason);
                continue;
            }
        }

        $slow = Effect::getEffect(Effect::SLOWNESS);
        $slow->setAmplifier(2); $slow->setDuration(60); $slow->setVisible(false);
        $this->safeAddEffect($player, $t, $slow);

        $weak = Effect::getEffect(Effect::WEAKNESS);
        $weak->setAmplifier(1); $weak->setDuration(50); $weak->setVisible(false);
        $this->safeAddEffect($player, $t, $weak);

        $nausea = Effect::getEffect(Effect::NAUSEA);
        $nausea->setAmplifier(0); $nausea->setDuration(40); $nausea->setVisible(false);
        $this->safeAddEffect($player, $t, $nausea);

        if ($t instanceof Player) {
            $t->sendTip(TextFormat::GRAY . "AWOOOO! Frozen in fear by the Wolf's howl!");
        }
        $hits++;
    }

    $player->sendTip(TextFormat::GRAY . "PREDATOR HOWL! Panicked $hits enemies! Speed+Str buffed!");
    $vfx = $this->getVFX();
    if ($vfx) $vfx->getFruitVFX()->spawnHitEffect($player, "zoan", "common");
    return $this->getAbilityCooldowns()["ability2"];
}

private function findFront(Player $player, $maxDist) {
    $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
    $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

    $dir = $player->getDirectionVector(); $start = $player->add(0, $player->getEyeHeight(), 0);
    $best = null; $bestDist = $maxDist + 1;
    foreach ($player->getLevel()->getPlayers() as $t) {
        if ($t->getName() === $player->getName()) continue;

        if ($toggle !== null) {
            if (!$this->plugin->canTargetPlayer($player->getName(), $t)) continue;
        }

        $tp = $t->add(0, 1, 0); $dist = $start->distance($tp);
        if ($dist > $maxDist || $dist <= 0) continue;
        $to = $tp->subtract($start); $norm = new Vector3($to->x/$dist, $to->y/$dist, $to->z/$dist);
        $dot = $dir->x*$norm->x + $dir->y*$norm->y + $dir->z*$norm->z;
        if ($dot > 0.45 && $dist < $bestDist) { $bestDist = $dist; $best = $t; }
    }
    return $best;
}

    private function getVFX() { return $this->plugin->getServer()->getPluginManager()->getPlugin("OnePieceFruits"); }
    public function onEquip(Player $player) {
        $player->sendMessage(TextFormat::GRAY . "Wolf instincts! " . TextFormat::GRAY . "(Wolf Fang | Predator Howl) — Zoan: Sneak+Feather");
    }
    public function onUnequip(Player $player) { $player->sendMessage(TextFormat::GRAY . "Wolf powers recede..."); }
}