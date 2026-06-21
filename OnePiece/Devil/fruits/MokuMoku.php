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

class MokuMoku extends BaseFruit {

    public function getId()          { return "moku_moku"; }
    public function getDisplayName() { return "Smoke-Smoke Fruit"; }
    public function getDescription() { return "Smoke Fruit - choke, blind, and trap in dense smoke."; }
    public function getType()        { return "logia"; }
    public function getRarity()      { return "common"; }

    public function getAbilityNames() {
        return ["ability1" => "White Blow", "ability2" => "Smoke Screen"];
    }
    public function getAbilityCooldowns() {
        return ["ability1" => 5.0, "ability2" => 15.0];
    }

    public function useAbility(Player $player, $ability) {
        switch ($ability) {
            case "ability1": return $this->whiteBlow($player);
            case "ability2": return $this->smokeScreen($player);
        }
        return 0;
    }

    // White Blow – compressed smoke fist, blinds on hit
private function whiteBlow(Player $player) {
    $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
    $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

    $target = $this->findFrontTarget($player, 9);
    if ($target === null) {
        $player->sendTip(TextFormat::GRAY . "White Blow...");
        return 1.5;
    }

    if ($target instanceof Player) {
        if (!$this->plugin->canTargetPlayer($player->getName(), $target)) {
            $reason = $this->plugin->getTargetBlockReason($player->getName(), $target);
            if ($reason !== null) $player->sendTip($reason);
            return 1.5;
        }
    }

    $mult   = min(1.5, $this->getHakiMultiplier($player));
    $damage = min(3.5, 2.0 * $mult);

    $ev = new EntityDamageByEntityEvent($player, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage);
    $target->attack($damage, $ev);

    $blind = Effect::getEffect(Effect::BLINDNESS);
    $blind->setAmplifier(0); $blind->setDuration(60); $blind->setVisible(false);
    $this->safeAddEffect($player, $target, $blind);

    $slow = Effect::getEffect(Effect::SLOWNESS);
    $slow->setAmplifier(1); $slow->setDuration(60); $slow->setVisible(false);
    $this->safeAddEffect($player, $target, $slow);

    $dir = $player->getDirectionVector();
    $this->safeSetMotion($player, $target, new Vector3($dir->x * 0.7, 0.3, $dir->z * 0.7));

    $player->sendTip(TextFormat::GRAY . "WHITE BLOW!");
    if ($target instanceof Player) {
        $target->sendTip(TextFormat::DARK_GRAY . "Choked in smoke! Can't see!");
    }

    $vfx = $this->getVFX();
    if ($vfx) $vfx->getFruitVFX()->spawnSmokeCloud($player, 2.0);
    return $this->getAbilityCooldowns()["ability1"];
}

private function smokeScreen(Player $player) {
    $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
    $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

    $radius = 9.0; $hits = 0;
    $pos = $player->getPosition();

    foreach ($this->getNearbyTargets($player, $radius) as $t) {
        if ($t instanceof Player) {
            if (!$this->plugin->canTargetPlayer($player->getName(), $t)) {
                continue;
            }
        }

        $blind = Effect::getEffect(Effect::BLINDNESS);
        $blind->setAmplifier(1); $blind->setDuration(100); $blind->setVisible(false);
        $this->safeAddEffect($player, $t, $blind);

        $slow = Effect::getEffect(Effect::SLOWNESS);
        $slow->setAmplifier(2); $slow->setDuration(80); $slow->setVisible(false);
        $this->safeAddEffect($player, $t, $slow);

        $fatigue = Effect::getEffect(Effect::MINING_FATIGUE);
        $fatigue->setAmplifier(1); $fatigue->setDuration(60); $fatigue->setVisible(false);
        $this->safeAddEffect($player, $t, $fatigue);

        if ($t instanceof Player) {
            $t->sendTip(TextFormat::DARK_GRAY . "SMOKE SCREEN! Total whiteout!");
        }
        $hits++;
    }

    $player->sendTip(TextFormat::GRAY . "SMOKE SCREEN! Trapped $hits enemies in smoke!");
    $vfx = $this->getVFX();
    if ($vfx) $vfx->getFruitVFX()->spawnSmokeCloud($player, $radius);
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
        $player->sendMessage(TextFormat::GRAY . "Smoke powers! " . TextFormat::GRAY . "(White Blow | Smoke Screen) — Logia: Sneak+Feather");
    }
    public function onUnequip(Player $player) { $player->sendMessage(TextFormat::GRAY . "Smoke dissipates..."); }
}