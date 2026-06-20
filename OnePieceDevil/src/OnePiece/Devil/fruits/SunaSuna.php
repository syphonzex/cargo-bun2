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

class SunaSuna extends BaseFruit {

    public function getId()          { return "suna_suna"; }
    public function getDisplayName() { return "Sand-Sand Fruit"; }
    public function getDescription() { return "Sand Fruit - drain all moisture from living things."; }
    public function getType()        { return "logia"; }
    public function getRarity()      { return "rare"; }

    public function getAbilityNames() {
        return ["ability1" => "Sables", "ability2" => "Ground Death"];
    }
    public function getAbilityCooldowns() {
        return ["ability1" => 6.0, "ability2" => 18.0];
    }

    public function useAbility(Player $player, $ability) {
        switch ($ability) {
            case "ability1": return $this->sables($player);
            case "ability2": return $this->groundDeath($player);
        }
        return 0;
    }

    // Sables – giant sandstorm vortex that pulls everyone in and slashes
private function sables(Player $player) {
    $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
    $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

    $mult   = min(1.5, $this->getHakiMultiplier($player));
    $damage = min(6.0, 2.5 * $mult);
    $radius = 7.0; $hits = 0;
    $pos    = $player->getPosition();

    foreach ($this->getNearbyTargets($player, $radius) as $t) {
        $dist = $pos->distance($t->getPosition());
        if ($dist <= 0) continue;

        if ($t instanceof Player) {
            if (!$this->plugin->canTargetPlayer($player->getName(), $t)) {
                continue;
            }
        }

        $dx = $pos->x - $t->x; $dz = $pos->z - $t->z;
        $len = sqrt($dx * $dx + $dz * $dz);
        if ($len > 0) $this->safeSetMotion($player, $t, new Vector3($dx / $len * 1.1, 0.4, $dz / $len * 1.1));

        $this->dealAbilityDamage($player, $t, $damage);

        $slow = Effect::getEffect(Effect::SLOWNESS);
        $slow->setAmplifier(1); $slow->setDuration(50); $slow->setVisible(false);
        $this->safeAddEffect($player, $t, $slow);

        if ($t instanceof Player) {
            $t->sendTip(TextFormat::YELLOW . "SABLES! Sand vortex!");
        }
        $hits++;
    }

    $player->sendTip(TextFormat::YELLOW . "SABLES! Pulled $hits into the storm!");
    $vfx = $this->getVFX();
    if ($vfx) $vfx->getFruitVFX()->spawnSandstorm($player, $radius);
    return $this->getAbilityCooldowns()["ability1"];
}

private function groundDeath(Player $player) {
    $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
    $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

    $mult   = min(1.5, $this->getHakiMultiplier($player));
    $damage = min(8.0, 4.5 * $mult);
    $radius = 5.0; $totalDrained = 0;
    $pos    = $player->getPosition();

    foreach ($this->getNearbyTargets($player, $radius) as $t) {
        if ($t instanceof Player) {
            if (!$this->plugin->canTargetPlayer($player->getName(), $t)) {
                continue;
            }
        }

        $this->dealAbilityDamage($player, $t, $damage);

        if ($t instanceof Player) {
            $food = $t->getFood(); $drain = min($food, 6);
            $t->setFood($food - $drain); $totalDrained += $drain;
        }

        $fatigue = Effect::getEffect(Effect::MINING_FATIGUE);
        $fatigue->setAmplifier(2); $fatigue->setDuration(80); $fatigue->setVisible(false);
        $this->safeAddEffect($player, $t, $fatigue);

        $slow = Effect::getEffect(Effect::SLOWNESS);
        $slow->setAmplifier(2); $slow->setDuration(70); $slow->setVisible(false);
        $this->safeAddEffect($player, $t, $slow);

        if ($t instanceof Player) {
            $t->sendTip(TextFormat::YELLOW . "GROUND DEATH! Moisture drained from your body!");
        }
    }

    if ($totalDrained > 0) {
        $heal = min((float)$totalDrained * 0.7, $player->getMaxHealth() - $player->getHealth());
        $player->setHealth($player->getHealth() + $heal);
    }

    $player->sendTip(TextFormat::YELLOW . "GROUND DEATH! Healed " . round($totalDrained * 0.7, 1) . " HP from drained moisture!");
    $vfx = $this->getVFX();
    if ($vfx) {
        $vfx->getFruitVFX()->spawnSandstorm($player, $radius);
        $vfx->getFruitVFX()->spawnSandDomain($player, $radius * 0.9, 210);
    }
    return $this->getAbilityCooldowns()["ability2"];
}

    private function getVFX() { return $this->plugin->getServer()->getPluginManager()->getPlugin("OnePieceFruits"); }
    public function onEquip(Player $player) {
        $player->sendMessage(TextFormat::YELLOW . "Sand powers! " . TextFormat::GRAY . "(Sables | Ground Death) — Logia: Sneak+Feather");
    }
    public function onUnequip(Player $player) { $player->sendMessage(TextFormat::GRAY . "Sand blows away..."); }
}
