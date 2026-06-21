<?php

namespace OnePiece\Fruits;

use pocketmine\Player;
use pocketmine\entity\Effect;
use pocketmine\utils\TextFormat;

class LogiaSystem {

    private $plugin;
    private $active = [];
    private $drainTimers = [];
    private $originalHealth = [];
    private $cooldowns = [];

    const DRAIN_INTERVAL = 3;
    const HUNGER_COST = 2;
    const BASE_INTANGIBILITY = 0.85;
    const BASE_DAMAGE_BOOST = 1.08;
    const BASE_DEFENSE_BOOST = 1.08;
    const EXTRA_HEALTH = 6;
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

        if (isset($this->active[$name])) {
            $player->sendMessage(TextFormat::YELLOW . "Already in Logia form!");
            return;
        }

        if ($this->isOnCooldown($player)) {
            $remaining = ceil($this->getCooldownRemaining($player));
            $player->sendMessage(TextFormat::RED . "Logia form on cooldown! " . $remaining . "s remaining.");
            return;
        }

        $fruitType = $this->plugin->getPlayerFruitType($player);
        if ($fruitType !== "logia") {
            $player->sendMessage(TextFormat::RED . "You don't have a Logia fruit!");
            return;
        }

        if ($player->getFood() <= 4) {
            $player->sendMessage(TextFormat::RED . "Not enough stamina for Logia form!");
            return;
        }

        $devilPlugin = $this->plugin->getDevilPlugin();
        if ($devilPlugin !== null) {
            try {
                $mm = $devilPlugin->getMasteryManager();
                if ($mm !== null && !$mm->canUseAbility($player->getName(), "logia_transform")) {
                    $req = \OnePiece\Devil\MasteryManager::ABILITY_UNLOCK["logia_transform"];
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

        $this->originalHealth[$name] = $player->getMaxHealth();

        $this->active[$name] = true;
        $this->drainTimers[$name] = microtime(true);

        $currentHealth = $player->getHealth();
        $currentMax = $player->getMaxHealth();
        $newMax = $currentMax + self::EXTRA_HEALTH;

        $player->setMaxHealth($newMax);

        $healthRatio = $currentHealth / $currentMax;
        $newHealth = $healthRatio * $newMax;
        $player->setHealth($newHealth);

        $player->sendMessage(TextFormat::GOLD . "LOGIA FORM ACTIVATED!");
        $player->sendMessage(TextFormat::GRAY . "Physical attacks pass through you.");
        $player->sendMessage(TextFormat::GRAY . "+8% DMG, +8% DEF, +6 Max HP, Speed Boost");
        $player->sendMessage(TextFormat::RED . "Armament Haki can still hit you!");

        $this->plugin->getFruitVFX()->spawnLogiaActivateEffect($player);
    }

    public function deactivate(Player $player) {
        $name = $player->getName();
        if (!isset($this->active[$name])) {
            return;
        }

        unset($this->active[$name]);
        unset($this->drainTimers[$name]);

        $this->cooldowns[$name] = microtime(true);

        $originalMax = isset($this->originalHealth[$name]) ? $this->originalHealth[$name] : 20;
        unset($this->originalHealth[$name]);

        $player->setMaxHealth($originalMax);
        if ($player->getHealth() > $originalMax) {
            $player->setHealth($originalMax);
        }

        $effectIds = [Effect::SPEED];
        foreach ($effectIds as $id) {
            try {
                if ($player->hasEffect($id)) {
                    $player->removeEffect($id);
                }
            } catch (\Exception $e) {}
        }

        if ($player->isOnline()) {
            $player->sendMessage(TextFormat::GRAY . "Logia form deactivated.");
        }
    }

    public function isActive(Player $player) {
        return isset($this->active[$player->getName()]);
    }

    public function getDamageBoost(Player $player) {
        $boost = self::BASE_DAMAGE_BOOST;
        if ($this->plugin->getAwakeningSystem()->isAwakened($player)) {
            $boost = 1.12;
        }
        return $boost;
    }

    public function getDefenseBoost(Player $player) {
        $boost = self::BASE_DEFENSE_BOOST;
        if ($this->plugin->getAwakeningSystem()->isAwakened($player)) {
            $boost = 1.12;
        }
        return $boost;
    }

    public function clearCooldown(Player $player) {
        unset($this->cooldowns[$player->getName()]);
    }

    public function cleanup(Player $player) {
        $name = $player->getName();
        unset($this->active[$name]);
        unset($this->drainTimers[$name]);
        unset($this->originalHealth[$name]);
        unset($this->cooldowns[$name]);
    }

    public function tick() {
        $now = microtime(true);

        foreach ($this->active as $name => $val) {
            $player = $this->plugin->getServer()->getPlayerExact($name);

            if ($player === null || !$player->isOnline()) {
                unset($this->active[$name]);
                unset($this->drainTimers[$name]);
                unset($this->originalHealth[$name]);
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
                        $player->sendMessage(TextFormat::AQUA . "Logia form broken by water!");
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
                        $player->sendMessage(TextFormat::RED . "Logia form deactivated! No stamina!");
                        continue;
                    }

                    $player->setFood($newFood);
                }
            }

            try {
                $speed = Effect::getEffect(Effect::SPEED);
                $speed->setAmplifier(1);
                $speed->setDuration(40);
                $speed->setVisible(false);
                $player->addEffect($speed);
            } catch (\Exception $e) {}

            $this->plugin->getFruitVFX()->spawnLogiaPassiveEffect($player);

            $player->sendPopup(TextFormat::GOLD . "Logia Form ACTIVE");
        }
    }
}