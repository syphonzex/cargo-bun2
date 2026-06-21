<?php
namespace OnePiece\Devil\fruits;
use OnePiece\Devil\BaseFruit;
use OnePiece\Devil\Main;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\math\Vector3;
use pocketmine\entity\Effect;
use pocketmine\utils\TextFormat;

class GomuGomu extends BaseFruit {

    public function getId()          { return "gomu_gomu"; }
    public function getDisplayName() { return "Rubber-Rubber Fruit"; }
    public function getDescription() { return "Rubber Body - immune to lightning, stretch attacks at will."; }
    public function getType()        { return "paramecia"; }
    public function getRarity()      { return "rare"; }

    public function getAbilityNames() {
        return ["ability1" => "Gomu Gomu no Pistol", "ability2" => "Gomu Gomu no Elephant Gatling"];
    }
    public function getAbilityCooldowns() {
        return ["ability1" => 4.0, "ability2" => 14.0];
    }

    public function useAbility(Player $player, $ability) {
        switch ($ability) {
            case "ability1": return $this->pistol($player);
            case "ability2": return $this->elephantGatling($player);
        }
        return 0;
    }

    private function pistol(Player $player) {
        $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
        $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

        $target = $this->findFrontTarget($player, 14);
        if ($target === null) {
            $player->sendTip(TextFormat::RED . "Gomu Gomu no PISTOL! ...");
            return 1.5;
        }

        if ($target instanceof Player) {
            if (!$this->plugin->canTargetPlayer($player->getName(), $target)) {
                $reason = $this->plugin->getTargetBlockReason($player->getName(), $target);
                if ($reason !== null) $player->sendTip($reason);
                return 1.5;
            }
        }

        $mult = min(1.5, $this->getHakiMultiplier($player));
        $damage = min(6.5, 2.5 * $mult);

        $this->dealAbilityDamage($player, $target, $damage);

        $dir = $player->getDirectionVector();
        $this->safeSetMotion($player, $target, new Vector3($dir->x * 1.8, 0.4, $dir->z * 1.8));

        $player->sendTip(TextFormat::RED . "Gomu Gomu no PISTOL!");
        if ($target instanceof Player) {
            $target->sendTip(TextFormat::RED . "PISTOL! Hit by " . $player->getName());
        }

        $vfx = $this->getVFX();
        if ($vfx) $vfx->getFruitVFX()->spawnGomuImpact(
            $target->getLevel(), $target->x, $target->y + 1, $target->z, false
        );
        return $this->getAbilityCooldowns()["ability1"];
    }

    private function elephantGatling(Player $player) {
        $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
        $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

        $mult = min(1.5, $this->getHakiMultiplier($player));
        $damage = min(8.0, 3.5 * $mult);
        $radius = 5.0;
        $hits = 0;
        $pos = $player->getPosition();

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

            $dx = $t->x - $pos->x;
            $dz = $t->z - $pos->z;
            $len = sqrt($dx * $dx + $dz * $dz);
            if ($len > 0) $this->safeSetMotion($player, $t, new Vector3($dx / $len * 1.8, 0.65, $dz / $len * 1.8));

            if ($t instanceof Player) {
                $t->sendTip(TextFormat::RED . "ELEPHANT GATLING! Giant rubber fists!");
            }

            $vfx = $this->getVFX();
            if ($vfx) $vfx->getFruitVFX()->spawnGomuImpact(
                $t->getLevel(), $t->x, $t->y + 1, $t->z, true
            );
            $hits++;
        }
        $player->sendTip(TextFormat::RED . "Gomu Gomu no ELEPHANT GATLING! Hit " . $hits . "!");
        $vfx2 = $this->getVFX();
        if ($vfx2) {
            $vfx2->getFruitVFX()->spawnRubberDomain($player, 5.5, 150);
        }
        return $hits > 0 ? $this->getAbilityCooldowns()["ability2"] : 2.0;
    }

    private function getVFX() { return $this->plugin->getServer()->getPluginManager()->getPlugin("OnePieceFruits"); }

    public function onEquip(Player $player) {
        $player->sendMessage(TextFormat::RED . "Your body becomes rubber! " . TextFormat::GRAY . "(Pistol | Elephant Gatling)");
    }
    public function onUnequip(Player $player) {
        $player->sendMessage(TextFormat::GRAY . "Rubber powers fade...");
    }
}