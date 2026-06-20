<?php
namespace OnePiece\Devil\fruits;
use OnePiece\Devil\BaseFruit;
use OnePiece\Devil\Main;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\math\Vector3;
use pocketmine\entity\Effect;
use pocketmine\utils\TextFormat;

class NekoNeko extends BaseFruit {

    public function getId()          { return "neko_neko"; }
    public function getDisplayName() { return "Leopard-Leopard Fruit"; }
    public function getDescription() { return "Leopard Fruit - Rob Lucci's power, the ultimate predator."; }
    public function getType()        { return "zoan"; }
    public function getRarity()      { return "rare"; }

    public function getAbilityNames() {
        return ["ability1" => "Leopard Claw", "ability2" => "Rokuougan"];
    }
    public function getAbilityCooldowns() {
        return ["ability1" => 5.0, "ability2" => 16.0];
    }

    public function useAbility(Player $player, $ability) {
        switch ($ability) {
            case "ability1": return $this->leopardClaw($player);
            case "ability2": return $this->rokuougan($player);
        }
        return 0;
    }

    private function leopardClaw(Player $player) {
        $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
        $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

        $target = $this->findFrontTarget($player, 6);
        $vfx = $this->getVFX();

        if ($target === null) {
            $dir = $player->getDirectionVector();
            $player->setMotion(new Vector3($dir->x * 1.4, 0.2, $dir->z * 1.4));
            $player->sendTip(TextFormat::GOLD . "Geppo! Speed dash!");
            if ($vfx) {
                $pos = $player->getPosition();
                $vfx->getFruitVFX()->spawnLeopardSlash($player->getLevel(), $pos->x, $pos->y, $pos->z);
            }
            return 1.5;
        }

        if ($target instanceof Player) {
            if (!$this->plugin->canTargetPlayer($player->getName(), $target)) {
                $reason = $this->plugin->getTargetBlockReason($player->getName(), $target);
                if ($reason !== null) $player->sendTip($reason);
                $dir = $player->getDirectionVector();
                $player->setMotion(new Vector3($dir->x * 1.4, 0.2, $dir->z * 1.4));
                $player->sendTip(TextFormat::GOLD . "Geppo! Speed dash!");
                if ($vfx) {
                    $pos = $player->getPosition();
                    $vfx->getFruitVFX()->spawnLeopardSlash($player->getLevel(), $pos->x, $pos->y, $pos->z);
                }
                return 1.5;
            }
        }

        $mult = min(1.5, $this->getHakiMultiplier($player));
        $damage = min(6.5, 3.0 * $mult);

        $this->dealAbilityDamage($player, $target, $damage);

        $bleed = Effect::getEffect(Effect::WITHER);
        $bleed->setAmplifier(0);
        $bleed->setDuration(30);
        $bleed->setVisible(false);
        $this->safeAddEffect($player, $target, $bleed);

        $dir = $player->getDirectionVector();
        $this->safeSetMotion($player, $target, new Vector3($dir->x * 1.2, 0.35, $dir->z * 1.2));
        $player->setMotion(new Vector3($dir->x * 0.6, 0.1, $dir->z * 0.6));

        $player->sendTip(TextFormat::GOLD . "Leopard CLAW!");
        if ($target instanceof Player) {
            $target->sendTip(TextFormat::RED . "CLAW! Slashed by " . $player->getName());
        }

        if ($vfx) {
            $pos = $target->getPosition();
            $vfx->getFruitVFX()->spawnLeopardSlash($target->getLevel(), $pos->x, $pos->y, $pos->z);
        }
        return $this->getAbilityCooldowns()["ability1"];
    }

    private function rokuougan(Player $player) {
        $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
        $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

        $target = $this->findFrontTarget($player, 5);
        $vfx = $this->getVFX();

        if ($target === null) {
            $player->sendTip(TextFormat::GOLD . "Rokuougan - no target.");
            return 3.0;
        }

        if ($target instanceof Player) {
            if (!$this->plugin->canTargetPlayer($player->getName(), $target)) {
                $reason = $this->plugin->getTargetBlockReason($player->getName(), $target);
                if ($reason !== null) $player->sendTip($reason);
                return 3.0;
            }
        }

        $mult = min(1.5, $this->getHakiMultiplier($player));
        $damage = min(8.0, 4.0 * $mult);

        $this->dealAbilityDamage($player, $target, $damage);

        $slow = Effect::getEffect(Effect::SLOWNESS);
        $slow->setAmplifier(2);
        $slow->setDuration(40);
        $slow->setVisible(false);
        $this->safeAddEffect($player, $target, $slow);

        $fatigue = Effect::getEffect(Effect::MINING_FATIGUE);
        $fatigue->setAmplifier(1);
        $fatigue->setDuration(40);
        $fatigue->setVisible(false);
        $this->safeAddEffect($player, $target, $fatigue);

        $nausea = Effect::getEffect(Effect::NAUSEA);
        $nausea->setAmplifier(0);
        $nausea->setDuration(30);
        $nausea->setVisible(false);
        $this->safeAddEffect($player, $target, $nausea);

        $dir = $player->getDirectionVector();
        $this->safeSetMotion($player, $target, new Vector3($dir->x * 1.5, 0.35, $dir->z * 1.5));

        $player->sendTip(TextFormat::GOLD . "ROKUOUGAN! Six King Gun!");
        if ($target instanceof Player) {
            $target->sendTip(TextFormat::RED . "ROKUOUGAN! Hit by " . $player->getName());
        }

        if ($vfx) {
            if ($target instanceof Player) {
                $vfx->getFruitVFX()->spawnGammaKnife($target);
            }
            $vfx->getFruitVFX()->spawnSlashDomain($player, 4.0, 180);
        }
        return $this->getAbilityCooldowns()["ability2"];
    }

    private function getVFX() { return $this->plugin->getServer()->getPluginManager()->getPlugin("OnePieceFruits"); }

    public function onEquip(Player $player) {
        $player->sendMessage(TextFormat::GOLD . "Rob Lucci's power! " . TextFormat::GRAY . "(Leopard Claw | Rokuougan) -- Zoan: Sneak+Feather");
    }
    public function onUnequip(Player $player) { $player->sendMessage(TextFormat::GRAY . "Leopard powers recede..."); }
}