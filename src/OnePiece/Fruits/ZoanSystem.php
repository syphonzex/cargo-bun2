<?php

namespace OnePiece\Fruits;

use pocketmine\Player;
use pocketmine\math\Vector3;
use pocketmine\entity\Effect;
use pocketmine\utils\TextFormat;
use pocketmine\level\sound\AnvilFallSound;

class ZoanSystem {

    private $plugin;
    private $transformed = [];
    private $drainTimers = [];
    private $originalHealth = [];
    private $transformedFruit = [];
    private $cooldowns = [];
    private $bounceTick = [];

    const DRAIN_INTERVAL = 4;
    const HUNGER_COST = 1;
    const BASE_DAMAGE_BOOST = 1.10;
    const BASE_DEFENSE_BOOST = 1.10;
    const EXTRA_HEALTH = 8;
    const TRANSFORM_COOLDOWN = 30;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function isOnCooldown(Player $player) {
        $name = $player->getName();
        if (!isset($this->cooldowns[$name])) {
            return false;
        }
        return (microtime(true) - $this->cooldowns[$name]) < self::TRANSFORM_COOLDOWN;
    }

    public function getCooldownRemaining(Player $player) {
        $name = $player->getName();
        if (!isset($this->cooldowns[$name])) {
            return 0;
        }
        $remaining = self::TRANSFORM_COOLDOWN - (microtime(true) - $this->cooldowns[$name]);
        return $remaining > 0 ? $remaining : 0;
    }

    public function activate(Player $player) {
        $name = $player->getName();

        if (isset($this->transformed[$name])) {
            $player->sendMessage(TextFormat::YELLOW . "Already transformed!");
            return;
        }

        if ($this->isOnCooldown($player)) {
            $remaining = ceil($this->getCooldownRemaining($player));
            $player->sendMessage(TextFormat::RED . "Zoan transformation on cooldown! " . $remaining . "s remaining.");
            return;
        }

        $fruitType = $this->plugin->getPlayerFruitType($player);
        if ($fruitType !== "zoan") {
            $player->sendMessage(TextFormat::RED . "You don't have a Zoan fruit!");
            return;
        }

        if ($player->getFood() <= 4) {
            $player->sendMessage(TextFormat::RED . "Not enough stamina to transform!");
            return;
        }

        $devilPlugin = $this->plugin->getDevilPlugin();
        if ($devilPlugin !== null) {
            try {
                $mm = $devilPlugin->getMasteryManager();
                if ($mm !== null && !$mm->canUseAbility($player->getName(), "zoan_transform")) {
                    $req = \OnePiece\Devil\MasteryManager::ABILITY_UNLOCK["zoan_transform"];
                    $cur = $mm->getLevel($player->getName());
                    $player->sendMessage(TextFormat::RED . "Mastery too low! Need Lv.$req to transform. (You: Lv.$cur)");
                    return;
                }
            } catch (\Exception $e) {}
        }

        if ($devilPlugin !== null) {
            try {
                if ($devilPlugin->getWaterWeakness()->isWeakened($player)) {
                    $player->sendMessage(TextFormat::AQUA . "Too weak from water!");
                    return;
                }
            } catch (\Exception $e) {}
        }

        $fruitId = $this->plugin->getPlayerFruitId($player);

        $this->originalHealth[$name] = $player->getMaxHealth();

        $this->transformed[$name] = true;
        $this->drainTimers[$name] = microtime(true);
        $this->transformedFruit[$name] = $fruitId;

        $currentHealth = $player->getHealth();
        $currentMax = $player->getMaxHealth();
        $newMax = $currentMax + self::EXTRA_HEALTH;

        $player->setMaxHealth($newMax);

        $healthRatio = $currentHealth / $currentMax;
        $newHealth = $healthRatio * $newMax;
        $player->setHealth($newHealth);

        if ($fruitId !== null) {
            $skinManager = $this->plugin->getSkinManager();
            if ($skinManager->hasZoanSkin($fruitId)) {
                $skinManager->applyZoanSkin($player, $fruitId);
                $player->sendMessage(TextFormat::GREEN . "ZOAN TRANSFORMATION!");
            } else {
                $player->sendMessage(TextFormat::GREEN . "ZOAN TRANSFORMATION!");
                $player->sendMessage(TextFormat::GRAY . "(No custom skin for this form)");
            }
        } else {
            $player->sendMessage(TextFormat::GREEN . "ZOAN TRANSFORMATION!");
        }

        $player->sendMessage(TextFormat::GRAY . "+10% DMG, +10% DEF, +8 Max HP, Jump Boost");

        $this->plugin->getFruitVFX()->spawnZoanTransformEffect($player);

        if ($fruitId === "gear_fourth") {
            $this->plugin->getFruitVFX()->spawnGearFourthDomain($player, 2.5, 400);
        }
    }

    public function deactivate(Player $player) {
        $name = $player->getName();

        if (!isset($this->transformed[$name])) {
            return;
        }

        unset($this->transformed[$name]);
        unset($this->drainTimers[$name]);
        unset($this->transformedFruit[$name]);
        unset($this->bounceTick[$name]);

        $this->cooldowns[$name] = microtime(true);

        $originalMax = isset($this->originalHealth[$name]) ? $this->originalHealth[$name] : 20;
        unset($this->originalHealth[$name]);

        $player->setMaxHealth($originalMax);
        if ($player->getHealth() > $originalMax) {
            $player->setHealth($originalMax);
        }

        $this->plugin->getSkinManager()->restoreOriginalSkin($player);

        $effectIds = [Effect::STRENGTH, Effect::JUMP];
        foreach ($effectIds as $id) {
            try {
                if ($player->hasEffect($id)) {
                    $player->removeEffect($id);
                }
            } catch (\Exception $e) {}
        }

        if ($player->isOnline()) {
            $player->sendMessage(TextFormat::GRAY . "Zoan form deactivated.");
        }
    }

    public function isTransformed(Player $player) {
        return isset($this->transformed[$player->getName()]);
    }

    public function getTransformedFruitId(Player $player) {
        $name = $player->getName();
        return isset($this->transformedFruit[$name]) ? $this->transformedFruit[$name] : null;
    }

    public function getDamageBoost(Player $player) {
        $boost = self::BASE_DAMAGE_BOOST;
        if ($this->plugin->getAwakeningSystem()->isAwakened($player)) {
            $boost = 1.15;
        }
        return $boost;
    }

    public function getDefenseBoost(Player $player) {
        $boost = self::BASE_DEFENSE_BOOST;
        if ($this->plugin->getAwakeningSystem()->isAwakened($player)) {
            $boost = 1.15;
        }
        return $boost;
    }

    public function clearCooldown(Player $player) {
        unset($this->cooldowns[$player->getName()]);
    }

    public function cleanup(Player $player) {
        $name = $player->getName();
        unset($this->transformed[$name]);
        unset($this->drainTimers[$name]);
        unset($this->originalHealth[$name]);
        unset($this->transformedFruit[$name]);
        unset($this->cooldowns[$name]);
        unset($this->bounceTick[$name]);
    }

    public function tick() {
        $now = microtime(true);

        foreach ($this->transformed as $name => $val) {
            $player = $this->plugin->getServer()->getPlayerExact($name);

            if ($player === null || !$player->isOnline()) {
                unset($this->transformed[$name]);
                unset($this->drainTimers[$name]);
                unset($this->originalHealth[$name]);
                unset($this->transformedFruit[$name]);
                continue;
            }

            if (!$this->plugin->isInOPWorld($player)) {
                $this->deactivate($player);
                continue;
            }

            $devilPlugin = $this->plugin->getDevilPlugin();
            if ($devilPlugin !== null) {
                try {
                    if ($devilPlugin->getWaterWeakness()->isWeakened($player)) {
                        $this->deactivate($player);
                        $player->sendMessage(TextFormat::AQUA . "Transformation broken by water!");
                        continue;
                    }
                } catch (\Exception $e) {}
            }

            if (isset($this->drainTimers[$name])) {
                if ($now - $this->drainTimers[$name] >= self::DRAIN_INTERVAL) {
                    $this->drainTimers[$name] = $now;

                    $food = $player->getFood();
                    $newFood = $food - self::HUNGER_COST;

                    if ($newFood <= 1) {
                        $player->setFood(1);
                        $this->deactivate($player);
                        $player->sendMessage(TextFormat::RED . "Transformation ended! No stamina!");
                        continue;
                    }

                    $player->setFood($newFood);
                }
            }

            try {
                $str = Effect::getEffect(Effect::STRENGTH);
                $str->setAmplifier(0);
                $str->setDuration(40);
                $str->setVisible(false);
                $player->addEffect($str);

                $jump = Effect::getEffect(Effect::JUMP);
                $jump->setAmplifier(1);
                $jump->setDuration(40);
                $jump->setVisible(false);
                $player->addEffect($jump);
            } catch (\Exception $e) {}

            $this->plugin->getFruitVFX()->spawnZoanPassiveEffect($player);

            $fruitIdNow = isset($this->transformedFruit[$name]) ? $this->transformedFruit[$name] : null;

            if ($fruitIdNow === "gear_fourth") {
                if (!isset($this->bounceTick[$name])) $this->bounceTick[$name] = 0;

                $this->bounceTick[$name]++;

                if ($this->bounceTick[$name] % 4 === 0) {
                    if ($player->isOnGround()) {
                        $current = $player->getMotion();
                        $player->setMotion(new Vector3($current->x * 0.1, 0.42, $current->z * 0.1));
                        $player->getLevel()->addSound(new AnvilFallSound(new Vector3($player->x, $player->y, $player->z)));
                    }
                }

                $player->sendPopup(TextFormat::RED . "Gear Fourth ACTIVE");
            } else {
                $player->sendPopup(TextFormat::GREEN . "Zoan Form ACTIVE");
            }
        }
    }
}