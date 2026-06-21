<?php

namespace OnePiece\Fruits;

use pocketmine\Player;
use pocketmine\entity\Effect;
use pocketmine\utils\TextFormat;

class AwakeningSystem {

    /** @var Main */
    private $plugin;

    /** @var array [playerName => true] */
    private $awakened = [];

    /** @var array [playerName => lastDrainTime] */
    private $drainTimers = [];

    /** @var array [playerName => true] */
    private $unlocked = [];

    /** @var string */
    private $savePath;

    const DRAIN_INTERVAL = 2;
    const HUNGER_COST = 3;
    const REQUIRED_LEVEL = 20;
    const REQUIRED_HAKI_STAT = 15;
    const REQUIRED_BOUNTY = 5000;
    const BASE_DAMAGE_BOOST = 1.25;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->savePath = $plugin->getDataFolder() . "awakenings.json";
        $this->loadAll();
    }

    public function loadAll() {
        if (!file_exists($this->savePath)) {
            $this->unlocked = [];
            return;
        }
        $data = json_decode(file_get_contents($this->savePath), true);
        if (is_array($data)) {
            $this->unlocked = $data;
        }
    }

    public function saveAll() {
        file_put_contents($this->savePath, json_encode($this->unlocked, JSON_PRETTY_PRINT));
    }

    public function hasUnlockedAwakening(Player $player) {
        return isset($this->unlocked[$player->getName()]);
    }

    /**
     * Try to unlock awakening
     * Returns true on success, string on failure
     */
    public function tryUnlock(Player $player) {
        $name = $player->getName();

        if (isset($this->unlocked[$name])) {
            return "Already unlocked!";
        }

        $fruitType = $this->plugin->getPlayerFruitType($player);
        if ($fruitType === null) {
            return "You need a Devil Fruit first!";
        }

        // Check stats
        $statsPlugin = $this->plugin->getStatsPlugin();
        if ($statsPlugin !== null) {
            try {
                $sm = $statsPlugin->getStatManager();
                if ($sm !== null && $sm->isLoaded($player)) {
                    $sp = $sm->getStatPlayer($player);
                    if ($sp !== null) {
                        if ($sp->getLevel() < self::REQUIRED_LEVEL) {
                            return "Need level " . self::REQUIRED_LEVEL . "! (You: " . $sp->getLevel() . ")";
                        }
                        if ($sp->getStat("haki") < self::REQUIRED_HAKI_STAT) {
                            return "Need " . self::REQUIRED_HAKI_STAT . " Haki! (You: " . $sp->getStat("haki") . ")";
                        }
                    }
                }
            } catch (\Exception $e) {}
        }

        // Check bounty
        $bountyPlugin = $this->plugin->getServer()->getPluginManager()->getPlugin("OnePieceBounty");
        if ($bountyPlugin !== null) {
            try {
                $bounty = $bountyPlugin->getBountyManager()->getBounty($name);
                if ($bounty < self::REQUIRED_BOUNTY) {
                    return "Need " . number_format(self::REQUIRED_BOUNTY) . " bounty! (You: " . number_format($bounty) . ")";
                }
            } catch (\Exception $e) {}
        }

        $this->unlocked[$name] = true;
        $this->saveAll();
        return true;
    }

    /**
     * Force unlock (for admin grant)
     */
    public function forceUnlock($playerName) {
        $this->unlocked[$playerName] = true;
        $this->saveAll();
    }

    public function activate(Player $player) {
        $name = $player->getName();

        if (!$this->hasUnlockedAwakening($player)) {
            $player->sendMessage(TextFormat::RED . "Awakening not unlocked!");
            return;
        }

        if ($player->getFood() <= 6) {
            $player->sendMessage(TextFormat::RED . "Not enough stamina for Awakening!");
            return;
        }

        // Water check
        $devilPlugin = $this->plugin->getDevilPlugin();
        if ($devilPlugin !== null) {
            try {
                if ($devilPlugin->getWaterWeakness()->isWeakened($player)) {
                    $player->sendMessage(TextFormat::AQUA . "Too weak from water!");
                    return;
                }
            } catch (\Exception $e) {}
        }

        $this->awakened[$name] = true;
        $this->drainTimers[$name] = microtime(true);

        $player->sendMessage(TextFormat::LIGHT_PURPLE . "═══════════════════════");
        $player->sendMessage(TextFormat::LIGHT_PURPLE . "  AWAKENING ACTIVATED!");
        $player->sendMessage(TextFormat::LIGHT_PURPLE . "═══════════════════════");
        $player->sendMessage(TextFormat::GRAY . "All stats massively boosted!");
        $player->sendMessage(TextFormat::RED . "Heavy stamina drain!");

        $this->plugin->getServer()->broadcastMessage(
            TextFormat::LIGHT_PURPLE . $name . " has AWAKENED their Devil Fruit!"
        );

        $this->plugin->getFruitVFX()->spawnAwakeningEffect($player);
    }

    public function deactivate(Player $player) {
        $name = $player->getName();

        if (!isset($this->awakened[$name])) {
            return;
        }

        unset($this->awakened[$name]);
        unset($this->drainTimers[$name]);

        // Remove effects safely
        $effectIds = [Effect::STRENGTH, Effect::SPEED];
        foreach ($effectIds as $id) {
            try {
                if ($player->hasEffect($id)) {
                    $player->removeEffect($id);
                }
            } catch (\Exception $e) {}
        }

        if ($player->isOnline()) {
            $player->sendMessage(TextFormat::GRAY . "Awakening deactivated.");
        }
    }

    public function isAwakened(Player $player) {
        return isset($this->awakened[$player->getName()]);
    }

    public function getDamageBoost(Player $player) {
        return self::BASE_DAMAGE_BOOST;
    }

    public function tick() {
        $now = microtime(true);

        foreach ($this->awakened as $name => $val) {
            $player = $this->plugin->getServer()->getPlayerExact($name);

            if ($player === null || !$player->isOnline()) {
                unset($this->awakened[$name]);
                unset($this->drainTimers[$name]);
                continue;
            }

            if (!$this->plugin->isInOPWorld($player)) {
                $this->deactivate($player);
                continue;
            }

            // Water check
            $devilPlugin = $this->plugin->getDevilPlugin();
            if ($devilPlugin !== null) {
                try {
                    if ($devilPlugin->getWaterWeakness()->isWeakened($player)) {
                        $this->deactivate($player);
                        $player->sendMessage(TextFormat::AQUA . "Awakening broken by water!");
                        continue;
                    }
                } catch (\Exception $e) {}
            }

            // Heavy drain
            if (isset($this->drainTimers[$name])) {
                if ($now - $this->drainTimers[$name] >= self::DRAIN_INTERVAL) {
                    $this->drainTimers[$name] = $now;

                    $food = $player->getFood();
                    $newFood = $food - self::HUNGER_COST;

                    if ($newFood <= 1) {
                        $player->setFood(1);
                        $this->deactivate($player);
                        $player->sendMessage(TextFormat::RED . "Awakening ended! No stamina!");
                        continue;
                    }

                    $player->setFood($newFood);
                }
            }

            // Refresh effects
            try {
                $str = Effect::getEffect(Effect::STRENGTH);
                $str->setAmplifier(2);
                $str->setDuration(40);
                $str->setVisible(false);
                $player->addEffect($str);

                $speed = Effect::getEffect(Effect::SPEED);
                $speed->setAmplifier(2);
                $speed->setDuration(40);
                $speed->setVisible(false);
                $player->addEffect($speed);
            } catch (\Exception $e) {}

            // VFX
            $this->plugin->getFruitVFX()->spawnAwakeningPassiveEffect($player);

            $player->sendPopup(TextFormat::LIGHT_PURPLE . "AWAKENED");
        }
    }
}