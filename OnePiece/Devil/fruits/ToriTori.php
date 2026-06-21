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

class ToriTori extends BaseFruit {

    public function getId()          { return "tori_tori"; }
    public function getDisplayName() { return "Phoenix-Phoenix Fruit"; }
    public function getDescription() { return "Phoenix Fruit - Marco's undying blue flames, regenerate from any wound."; }
    public function getType()        { return "zoan"; }
    public function getRarity()      { return "legendary"; }

    public function getAbilityNames() {
        return ["ability1" => "Phoenix Brand", "ability2" => "Blue Flames of Resurrection"];
    }
    public function getAbilityCooldowns() {
        return ["ability1" => 8.0, "ability2" => 35.0];
    }

    public function useAbility(Player $player, $ability) {
        switch ($ability) {
            case "ability1": return $this->phoenixBrand($player);
            case "ability2": return $this->blueFlamesOfResurrection($player);
        }
        return 0;
    }

    // Phoenix Brand – blue fire strike that burns but also heals the user
private function phoenixBrand(Player $player) {
    $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
    $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

    $target = $this->findFrontTarget($player, 9);
    $vfx    = $this->getVFX();

    if ($target === null) {
        $heal = 2.0;
        $player->setHealth(min($player->getMaxHealth(), $player->getHealth() + $heal));
        $player->sendTip(TextFormat::AQUA . "Blue flames mend your wounds...");
        if ($vfx) $vfx->getFruitVFX()->spawnPhoenixFlames($player);
        return 3.0;
    }

    if ($target instanceof Player) {
        if (!$this->plugin->canTargetPlayer($player->getName(), $target)) {
            $heal = 2.0;
            $player->setHealth(min($player->getMaxHealth(), $player->getHealth() + $heal));
            $player->sendTip(TextFormat::AQUA . "Blue flames mend your wounds...");
            if ($vfx) $vfx->getFruitVFX()->spawnPhoenixFlames($player);
            $reason = $this->plugin->getTargetBlockReason($player->getName(), $target);
            if ($reason !== null) $player->sendTip($reason);
            return 3.0;
        }
    }

    $mult   = min(1.5, $this->getHakiMultiplier($player));
    $damage = min(8.5, 3.5 * $mult);

$this->dealAbilityDamage($player, $target, $damage);

    $heal = 2.0;
    $player->setHealth(min($player->getMaxHealth(), $player->getHealth() + $heal));

    $player->sendTip(TextFormat::AQUA . "PHOENIX BRAND! Healed " . $heal . " HP!");
    if ($target instanceof Player) {
        $target->sendTip(TextFormat::AQUA . "Branded by undying blue flames!");
    }

    if ($vfx) {
        if ($target instanceof Player) {
            $vfx->getFruitVFX()->spawnPhoenixFlames($target);
        } else {
            $vfx->getFruitVFX()->spawnPhoenixFlames($player);
        }
    }
    return $this->getAbilityCooldowns()["ability1"];
}

private function blueFlamesOfResurrection(Player $player) {
    $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
    $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

    $mult = min(1.5, $this->getHakiMultiplier($player));

    $heal = $player->getMaxHealth() * 0.7;
    $player->setHealth(min($player->getMaxHealth(), $player->getHealth() + $heal));

    $regen = Effect::getEffect(Effect::REGENERATION);
    $regen->setAmplifier(2); $regen->setDuration(100); $regen->setVisible(false);
    $player->addEffect($regen);

    $res = Effect::getEffect(Effect::DAMAGE_RESISTANCE);
    $res->setAmplifier(2); $res->setDuration(60); $res->setVisible(false);
    $player->addEffect($res);

    $radius = 6.0;
    $damage = min(7.0, 2.0 * $mult);
    $pos    = $player->getPosition();

    foreach ($this->getNearbyTargets($player, $radius) as $t) {
        if ($t instanceof Player) {
            if (!$this->plugin->canTargetPlayer($player->getName(), $t)) {
                $reason = $this->plugin->getTargetBlockReason($player->getName(), $target);
            if ($reason !== null) $player->sendTip($reason);
                continue;
            }
        }

        $this->dealAbilityDamage($player, $t, $damage);
        $this->safeSetOnFire($player, $t, 4);

        $dx = $t->x - $pos->x; $dz = $t->z - $pos->z;
        $len = sqrt($dx * $dx + $dz * $dz);
        if ($len > 0) $this->safeSetMotion($player, $t, new Vector3($dx / $len * 1.4, 0.6, $dz / $len * 1.4));

        if ($t instanceof Player) {
            $t->sendTip(TextFormat::AQUA . "PHOENIX WINGS! Blown back by blue fire!");
        }
    }

    $player->sendTip(TextFormat::AQUA . "BLUE FLAMES OF RESURRECTION! +" . round($heal, 1) . " HP!");

    $vfx = $this->getVFX();
    if ($vfx) {
        $vfx->getFruitVFX()->spawnPhoenixFlames($player);
        $vfx->getFruitVFX()->spawnPhoenixDomain($player, 5.0, 300);
    }
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
        $player->sendMessage(TextFormat::AQUA . "MARCO's undying phoenix! " . TextFormat::GRAY . "(Phoenix Brand | Resurrection) — Zoan: Sneak+Feather");
    }
    public function onUnequip(Player $player) { $player->sendMessage(TextFormat::GRAY . "Phoenix flames fade..."); }
}
