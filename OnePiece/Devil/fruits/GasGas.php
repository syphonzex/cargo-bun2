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

class GasGas extends BaseFruit {

    public function getId()          { return "gas_gas"; }
    public function getDisplayName() { return "Gas-Gas Fruit"; }
    public function getDescription() { return "Chop Fruit - Buggy's power, separate and fly your body parts."; }
    public function getType()        { return "logia"; }
    public function getRarity()      { return "mythical"; }

    public function getAbilityNames() {
        return ["ability1" => "Bara Bara Cannon", "ability2" => "Bara Bara Festival"];
    }
    public function getAbilityCooldowns() {
        return ["ability1" => 6.0, "ability2" => 20.0];
    }

    public function useAbility(Player $player, $ability) {
        switch ($ability) {
            case "ability1": return $this->baraCannon($player);
            case "ability2": return $this->baraFestival($player);
        }
        return 0;
    }

    private function baraCannon(Player $player) {
        $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
        $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

        $mult   = min(1.5, $this->getHakiMultiplier($player));
        $damage = min(6.5, 3.5 * $mult);
        $range  = 14.0;

        $target = $this->findFrontTarget($player, $range);

        if ($target === null) {
            $player->sendTip(TextFormat::RED . "Bara Bara Cannon! ...missed!");
            return 2.0;
        }

        if ($target instanceof Player) {
            if (!$this->plugin->canTargetPlayer($player->getName(), $target)) {
                $reason = $this->plugin->getTargetBlockReason($player->getName(), $target);
                if ($reason !== null) $player->sendTip($reason);
                return 2.0;
            }
        }

        $this->dealAbilityDamage($player, $target, $damage);

        $dir = $player->getDirectionVector();
        $this->safeSetMotion($player, $target, new Vector3($dir->x * 1.8, 0.5, $dir->z * 1.8));

        if ($target instanceof Player) $target->sendTip(TextFormat::RED . "BARA BARA CANNON!");

        $player->sendTip(TextFormat::RED . "BARA BARA CANNON!");
        $this->spawnCannonVFX($player, $target);
        return $this->getAbilityCooldowns()["ability1"];
    }

    private function baraFestival(Player $player) {
        $combatPlugin = Server::getInstance()->getPluginManager()->getPlugin("OnePieceCombat");
        $toggle = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatToggle() : null;

        $mult   = min(1.5, $this->getHakiMultiplier($player));
        $damage = min(7.5, 4.5 * $mult);
        $radius = 9.0;
        $hits   = 0;
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
            $this->dealAbilityDamage($player, $t, $damage);

            $dx  = $t->x - $pos->x;
            $dz  = $t->z - $pos->z;
            $len = sqrt($dx * $dx + $dz * $dz);
            if ($len > 0) $this->safeSetMotion($player, $t, new Vector3($dx / $len * 1.5, 0.6, $dz / $len * 1.5));

            $slow = Effect::getEffect(Effect::SLOWNESS);
            $slow->setAmplifier(1);
            $slow->setDuration(50);
            $slow->setVisible(false);
            $this->safeAddEffect($player, $t, $slow);

            if ($t instanceof Player) $t->sendTip(TextFormat::RED . "BARA BARA FESTIVAL! Sliced apart!");
            $hits++;
        }

        $player->sendTip(TextFormat::RED . "BARA BARA FESTIVAL! Hit $hits!");
        $this->spawnFestivalVFX($player, $radius);
        return $this->getAbilityCooldowns()["ability2"];
    }

    private function spawnCannonVFX(Player $player, $target) {
        $lv = $player->getLevel();
        $px = $player->x;
        $py = $player->y + 1.2;
        $pz = $player->z;
        $tx = $target->x;
        $ty = $target->y + 1;
        $tz = $target->z;

        for ($i = 0; $i <= 10; $i++) {
            $prog = $i / 10;
            $lv->addParticle(new DustParticle(
                new Vector3(
                    $px + ($tx - $px) * $prog + (mt_rand(-4, 4) / 10),
                    $py + ($ty - $py) * $prog,
                    $pz + ($tz - $pz) * $prog + (mt_rand(-4, 4) / 10)
                ),
                255, 80, 0
            ));
        }

        for ($i = 0; $i < 10; $i++) {
            $a = ($i / 10) * M_PI * 2;
            $lv->addParticle(new CriticalParticle(new Vector3(
                $tx + cos($a) * 1.2,
                $ty + mt_rand(0, 10) / 10,
                $tz + sin($a) * 1.2
            )));
        }

        $lv->addParticle(new ExplodeParticle(new Vector3($tx, $ty, $tz)));
        $lv->addSound(new ClickSound(new Vector3($px, $py, $pz)));
    }

    private function spawnFestivalVFX(Player $player, $radius) {
        $lv = $player->getLevel();
        $px = $player->x;
        $py = $player->y + 1;
        $pz = $player->z;

        for ($ring = 0; $ring < 3; $ring++) {
            $rr  = $radius * (0.3 + $ring * 0.35);
            $pts = 12 + $ring * 6;
            for ($i = 0; $i < $pts; $i++) {
                $a = ($i / $pts) * M_PI * 2;
                $lv->addParticle(new DustParticle(
                    new Vector3($px + cos($a) * $rr, $py, $pz + sin($a) * $rr),
                    255, 80, 0
                ));
            }
        }

        for ($i = 0; $i < 16; $i++) {
            $a  = mt_rand(0, 628) / 100;
            $d  = mt_rand(5, (int)($radius * 8)) / 10;
            $lv->addParticle(new InstantEnchantParticle(
                new Vector3($px + cos($a) * $d, $py + mt_rand(0, 15) / 10, $pz + sin($a) * $d)
            ));
        }

        $lv->addParticle(new ExplodeParticle(new Vector3($px, $py, $pz)));
        $lv->addSound(new AnvilUseSound(new Vector3($px, $py, $pz)));
    }

    public function onEquip(Player $player) {
        $player->sendMessage(TextFormat::RED . "=== Bara-Bara no Mi ===");
        $player->sendMessage(TextFormat::WHITE . "Chop-Chop Fruit - Buggy's Legacy");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::RED . "[Tap]: " . TextFormat::WHITE . "Bara Bara Cannon");
        $player->sendMessage(TextFormat::GRAY . "  Launch a body-part cannon at your target");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::RED . "[Sneak+Tap]: " . TextFormat::WHITE . "Bara Bara Festival");
        $player->sendMessage(TextFormat::GRAY . "  Explode body parts in all directions");
        $player->sendMessage(TextFormat::RED . "======================");
    }

    public function onUnequip(Player $player) {
        $player->sendMessage(TextFormat::GRAY . "Pieces reassemble...");
    }
}
