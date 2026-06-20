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
use pocketmine\level\particle\DustParticle;
use pocketmine\level\particle\CriticalParticle;
use pocketmine\level\particle\ExplodeParticle;
use pocketmine\level\particle\InstantEnchantParticle;
use pocketmine\level\sound\AnvilUseSound;
use pocketmine\level\sound\PopSound;
use pocketmine\level\sound\ClickSound;

class ToriToriModel extends BaseFruit {

    public function getId()          { return "tori_tori_falcon"; }
    public function getDisplayName() { return "Falcon-Falcon Fruit"; }
    public function getDescription() { return "Falcon Fruit - Speed and sight of the skies."; }
    public function getType()        { return "zoan"; }
    public function getRarity()      { return "common"; }

    public function getAbilityNames() {
        return ["ability1" => "Talon Dive", "ability2" => "Sky Strike"];
    }
    public function getAbilityCooldowns() {
        return ["ability1" => 5.0, "ability2" => 18.0];
    }

    public function useAbility(Player $player, $ability) {
        switch ($ability) {
            case "ability1": return $this->talonDive($player);
            case "ability2": return $this->skyStrike($player);
        }
        return 0;
    }

    private function talonDive(Player $player) {
        $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
        $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

        $mult   = min(1.5, $this->getHakiMultiplier($player));
        $damage = min(4.5, 3.0 * $mult);

        $target = $this->findFrontTarget($player, 12.0);

        if ($target === null) {
            $player->sendTip(TextFormat::WHITE . "Talon Dive! ...missed!");
            return 2.0;
        }

        if ($target instanceof Player) {
            if (!$this->plugin->canTargetPlayer($player->getName(), $target)) {
                $reason = $this->plugin->getTargetBlockReason($player->getName(), $target);
                if ($reason !== null) $player->sendTip($reason);
                return 2.0;
            }
        }

        $dx  = $target->x - $player->x;
        $dz  = $target->z - $player->z;
        $len = sqrt($dx * $dx + $dz * $dz);
        if ($len > 0) {
            $player->setMotion(new Vector3($dx / $len * 1.5, 0.2, $dz / $len * 1.5));
        }

        $ev = new EntityDamageByEntityEvent($player, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage);
        $target->attack($damage, $ev);

        $slow = Effect::getEffect(Effect::SLOWNESS);
        $slow->setAmplifier(1);
        $slow->setDuration(40);
        $slow->setVisible(false);
        $this->safeAddEffect($player, $target, $slow);

        $dir = $player->getDirectionVector();
        $this->safeSetMotion($player, $target, new Vector3($dir->x * 1.2, 0.6, $dir->z * 1.2));

        if ($target instanceof Player) $target->sendTip(TextFormat::WHITE . "TALON DIVE!");

        $player->sendTip(TextFormat::WHITE . "TALON DIVE!");
        $this->spawnTalonVFX($player, $target);
        return $this->getAbilityCooldowns()["ability1"];
    }

    private function skyStrike(Player $player) {
        $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
        $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

        $mult   = min(1.5, $this->getHakiMultiplier($player));
        $damage = min(5.5, 4.0 * $mult);
        $radius = 8.0;
        $hits   = 0;
        $pos    = $player->getPosition();

        $player->setMotion(new Vector3(0, 0.9, 0));

        foreach ($this->getNearbyTargets($player, $radius) as $t) {
            $dist = $pos->distance($t->getPosition());
            if ($dist <= 0) continue;

            if ($t instanceof Player) {
                if (!$this->plugin->canTargetPlayer($player->getName(), $t)) {
                    continue;
                }
            }

            $scaled = $damage * (1 - ($dist / $radius) * 0.2);
            $ev = new EntityDamageByEntityEvent($player, $t, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $scaled);
            $t->attack($scaled, $ev);

            $dx  = $t->x - $pos->x;
            $dz  = $t->z - $pos->z;
            $len = sqrt($dx * $dx + $dz * $dz);
            if ($len > 0) $this->safeSetMotion($player, $t, new Vector3($dx / $len * 1.5, 0.7, $dz / $len * 1.5));

            $mining = Effect::getEffect(Effect::MINING_FATIGUE);
            $mining->setAmplifier(1);
            $mining->setDuration(50);
            $mining->setVisible(false);
            $this->safeAddEffect($player, $t, $mining);

            if ($t instanceof Player) $t->sendTip(TextFormat::WHITE . "SKY STRIKE! Struck from above!");
            $hits++;
        }

        $player->sendTip(TextFormat::WHITE . "SKY STRIKE! Hit $hits!");
        $this->spawnSkyStrikeVFX($player, $radius);
        return $this->getAbilityCooldowns()["ability2"];
    }

    private function spawnTalonVFX(Player $player, $target) {
        $lv = $player->getLevel();
        $px = $player->x;
        $py = $player->y + 1.2;
        $pz = $player->z;
        $tx = $target->x;
        $ty = $target->y + 1;
        $tz = $target->z;

        for ($i = 0; $i <= 8; $i++) {
            $prog = $i / 8;
            $lv->addParticle(new DustParticle(
                new Vector3(
                    $px + ($tx - $px) * $prog,
                    $py + ($ty - $py) * $prog + sin($prog * M_PI) * 0.5,
                    $pz + ($tz - $pz) * $prog
                ),
                255, 255, 200
            ));
        }

        for ($i = 0; $i < 8; $i++) {
            $a = ($i / 8) * M_PI * 2;
            $lv->addParticle(new CriticalParticle(new Vector3(
                $tx + cos($a) * 0.8,
                $ty + mt_rand(0, 8) / 10,
                $tz + sin($a) * 0.8
            )));
        }

        $lv->addParticle(new ExplodeParticle(new Vector3($tx, $ty, $tz)));
        $lv->addSound(new ClickSound(new Vector3($tx, $ty, $tz)));
    }

    private function spawnSkyStrikeVFX(Player $player, $radius) {
        $lv = $player->getLevel();
        $px = $player->x;
        $py = $player->y + 1;
        $pz = $player->z;

        for ($ring = 0; $ring < 2; $ring++) {
            $rr  = $radius * (0.4 + $ring * 0.6);
            $pts = 12 + $ring * 6;
            for ($i = 0; $i < $pts; $i++) {
                $a = ($i / $pts) * M_PI * 2;
                $lv->addParticle(new DustParticle(
                    new Vector3($px + cos($a) * $rr, $py, $pz + sin($a) * $rr),
                    255, 255, 200
                ));
            }
        }

        for ($i = 0; $i < 12; $i++) {
            $a  = mt_rand(0, 628) / 100;
            $d  = mt_rand(5, (int)($radius * 8)) / 10;
            $lv->addParticle(new InstantEnchantParticle(
                new Vector3($px + cos($a) * $d, $py + mt_rand(0, 10) / 10, $pz + sin($a) * $d)
            ));
        }

        $lv->addParticle(new ExplodeParticle(new Vector3($px, $py + 2, $pz)));
        $lv->addSound(new PopSound(new Vector3($px, $py, $pz)));
    }

    public function onEquip(Player $player) {
        $player->sendMessage(TextFormat::WHITE . "=== Tori-Tori no Mi, Model: Falcon ===");
        $player->sendMessage(TextFormat::GRAY . "Falcon Fruit - Eyes and Speed of a Raptor");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::WHITE . "[Tap]: " . TextFormat::GRAY . "Talon Dive");
        $player->sendMessage(TextFormat::GRAY . "  Dash and claw at your target");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::WHITE . "[Sneak+Tap]: " . TextFormat::GRAY . "Sky Strike");
        $player->sendMessage(TextFormat::GRAY . "  Leap up and crash down with a shockwave");
        $player->sendMessage(TextFormat::WHITE . "======================================");
    }

    public function onUnequip(Player $player) {
        $player->sendMessage(TextFormat::GRAY . "Wings fold...");
    }
}
