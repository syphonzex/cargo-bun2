<?php
namespace OnePiece\Devil\fruits;
use OnePiece\Devil\BaseFruit;
use OnePiece\Devil\Main;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\math\Vector3;
use pocketmine\utils\TextFormat;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;

class MeraMera extends BaseFruit {

    public function getId()          { return "mera_mera"; }
    public function getDisplayName() { return "Flame-Flame Fruit"; }
    public function getDescription() { return "Fire Fruit - Ace's legacy, become fire and incinerate everything."; }
    public function getType()        { return "logia"; }
    public function getRarity()      { return "legendary"; }

    public function getAbilityNames() {
        return ["ability1" => "Hiken", "ability2" => "Entei"];
    }
    public function getAbilityCooldowns() {
        return ["ability1" => 6.0, "ability2" => 22.0];
    }

    public function useAbility(Player $player, $ability) {
        switch ($ability) {
            case "ability1": return $this->hiken($player);
            case "ability2": return $this->entei($player);
        }
        return 0;
    }

private function hiken(Player $player) {
    $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
    $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

    $mult   = min(1.5, $this->getHakiMultiplier($player));
    $damage = min(9.5, 3.5 * $mult);
    $range  = 13.0; $hits = 0;
    $pos    = $player->getPosition();
    $dir    = $player->getDirectionVector();

    foreach ($this->getNearbyTargets($player, $range) as $t) {
        $tp = $t->getPosition();
        $dist = $pos->distance($tp);
        if ($dist <= 0) continue;

        if ($t instanceof Player) {
            if (!$this->plugin->canTargetPlayer($player->getName(), $t)) {
                continue;
            }
        }

        $to = $tp->subtract($pos);
        $norm = new Vector3($to->x/$dist, 0, $to->z/$dist);
        $dot  = $dir->x*$norm->x + $dir->z*$norm->z;

        if ($dot > 0.5) {
            $this->dealAbilityDamage($player, $t, $damage);
            $this->safeSetOnFire($player, $t, 4);
            $this->safeSetMotion($player, $t, new Vector3($dir->x * 1.5, 0.35, $dir->z * 1.5));
            if ($t instanceof Player) {
                $t->sendTip(TextFormat::RED . "HIKEN! Fire Fist!");
            }
            $hits++;
        }
    }

    $player->sendTip(TextFormat::RED . "HIKEN! (Fire Fist) Hit $hits!");
    $vfx = $this->getVFX();
    if ($vfx) $vfx->getFruitVFX()->spawnFireLine($player, $range);
    return $this->getAbilityCooldowns()["ability1"];
}

private function entei(Player $player) {
    $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
    $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

    $mult   = min(1.5, $this->getHakiMultiplier($player));
    $damage = min(12.0, 5.0 * $mult);
    $radius = 10.0; $hits = 0;
    $pos    = $player->getPosition();

    foreach ($this->getNearbyTargets($player, $radius) as $t) {
        $dist = $pos->distance($t->getPosition());
        if ($dist <= 0) continue;

        if ($t instanceof Player) {
            if (!$this->plugin->canTargetPlayer($player->getName(), $t)) {
                continue;
            }
        }

        $scaled = $damage * (1 - ($dist / $radius) * 0.2);
        $this->dealAbilityDamage($player, $t, $scaled);
        $this->safeSetOnFire($player, $t, 7);

        $dx = $t->x - $pos->x; $dz = $t->z - $pos->z;
        $len = sqrt($dx*$dx + $dz*$dz);
        if ($len > 0) $this->safeSetMotion($player, $t, new Vector3($dx/$len * 2.0, 0.8, $dz/$len * 2.0));

        if ($t instanceof Player) {
            $t->sendTip(TextFormat::RED . "ENTEI! Great Flame Commandment!");
        }
        $hits++;
    }

    $player->sendTip(TextFormat::RED . "ENTEI! (Great Flame Commandment) Hit $hits!");
    $vfx = $this->getVFX();
    if ($vfx) {
        $vfx->getFruitVFX()->spawnFireDome($player, $radius);
        $vfx->getFruitVFX()->spawnFireDomain($player, $radius * 0.7, 240);
    }
    return $this->getAbilityCooldowns()["ability2"];
}

    private function getVFX() { return $this->plugin->getServer()->getPluginManager()->getPlugin("OnePieceFruits"); }
    public function onEquip(Player $player) {
        $player->sendMessage(TextFormat::RED . "ACE'S FIRE! " . TextFormat::GRAY . "(Hiken | Entei) — Logia: Sneak+Feather");
    }
    public function onUnequip(Player $player) { $player->sendMessage(TextFormat::GRAY . "Flames extinguish..."); }
}
