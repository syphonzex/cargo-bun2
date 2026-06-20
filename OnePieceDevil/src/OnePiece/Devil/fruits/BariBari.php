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

class BariBari extends BaseFruit {

    public function getId()          { return "bari_bari"; }
    public function getDisplayName() { return "Barrier-Barrier Fruit"; }
    public function getDescription() { return "Barrier Fruit - summon invincible barriers then launch them."; }
    public function getType()        { return "paramecia"; }
    public function getRarity()      { return "common"; }

    public function getAbilityNames() {
        return ["ability1" => "Barrier Shield", "ability2" => "Barrier Crash"];
    }
    public function getAbilityCooldowns() {
        return ["ability1" => 6.0, "ability2" => 15.0];
    }

    public function useAbility(Player $player, $ability) {
        switch ($ability) {
            case "ability1": return $this->barrierShield($player);
            case "ability2": return $this->barrierCrash($player);
        }
        return 0;
    }

    private function barrierShield(Player $player) {
        $mult = min(1.5, $this->getHakiMultiplier($player));
        $dur = (int)(60 + 30 * ($mult - 1.0));
        $res = Effect::getEffect(Effect::DAMAGE_RESISTANCE);
        $res->setAmplifier(2); $res->setDuration($dur); $res->setVisible(false);
        $player->addEffect($res);

        $player->sendTip(TextFormat::YELLOW . "BARRIER! Impenetrable shield for " . round($dur/20,1) . "s!");

        $vfx = $this->getVFX();
        if ($vfx) $vfx->getFruitVFX()->spawnBarrierEffect($player);
        return $this->getAbilityCooldowns()["ability1"];
    }

private function barrierCrash(Player $player) {
    $mult = min(1.5, $this->getHakiMultiplier($player));
    $damage = min(4.5, 3.0 * $mult);
    $radius = 5.0;
    $hits = 0;
    $pos = $player->getPosition();

    $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
    $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

    foreach ($this->getNearbyTargets($player, $radius) as $t) {
        $dist = $pos->distance($t->getPosition());
        if ($dist <= 0) continue;

        if ($t instanceof Player) {
            if (!$this->plugin->canTargetPlayer($player->getName(), $t)) {
                continue;
            }
        }

        $ev = new EntityDamageByEntityEvent($player, $t, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage);
        $t->attack($damage, $ev);

        $dx = $t->x - $pos->x;
        $dz = $t->z - $pos->z;
        $len = sqrt($dx * $dx + $dz * $dz);
        if ($len > 0) $this->safeSetMotion($player, $t, new Vector3($dx / $len * 1.5, 0.6, $dz / $len * 1.5));

        if ($t instanceof Player) {
            $t->sendTip(TextFormat::YELLOW . "BARRIER CRASH! Shattered!");
        }
        $hits++;
    }

    $player->sendTip(TextFormat::YELLOW . "BARRIER CRASH! Hit $hits!");
    $vfx = $this->getVFX();
    if ($vfx) $vfx->getFruitVFX()->spawnBarrierEffect($player);
    return $this->getAbilityCooldowns()["ability2"];
}

    private function getVFX() { return $this->plugin->getServer()->getPluginManager()->getPlugin("OnePieceFruits"); }
    public function onEquip(Player $player) {
        $player->sendMessage(TextFormat::YELLOW . "Barrier powers! " . TextFormat::GRAY . "(Shield | Barrier Crash)");
    }
    public function onUnequip(Player $player) { $player->sendMessage(TextFormat::GRAY . "Barriers fade..."); }
}