<?php

namespace OnePiece\Fruits;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use pocketmine\item\Item;

class Main extends PluginBase implements Listener {

    private $opWorlds = ["OP", "Sea2", "sea3"];

    /** Item IDs for activation */
    const TRANSFORM_ITEM = 288;  // Feather
    const AWAKEN_ITEM = 370;     // Ghast Tear

    /** @var LogiaSystem */
    private $logiaSystem;

    /** @var ZoanSystem */
    private $zoanSystem;

    /** @var AwakeningSystem */
    private $awakeningSystem;

    /** @var FruitVFX */
    private $fruitVFX;

    /** @var SkinManager */
    private $skinManager;

    private $devilPlugin = null;
    private $hakiPlugin = null;
    private $combatPlugin = null;
    private $statsPlugin = null;

    public function onEnable() {
        $this->getLogger()->info(TextFormat::GREEN . "Advanced Fruits System loaded!");

        @mkdir($this->getDataFolder());
        @mkdir($this->getDataFolder() . "skins/");

        $this->saveDefaultSkins();

        $this->devilPlugin = $this->getServer()->getPluginManager()->getPlugin("OnePieceDevil");
        $this->hakiPlugin = $this->getServer()->getPluginManager()->getPlugin("OnePieceHaki");
        $this->combatPlugin = $this->getServer()->getPluginManager()->getPlugin("OnePieceCombat");
        $this->statsPlugin = $this->getServer()->getPluginManager()->getPlugin("OnePieceStats");

        $this->skinManager = new SkinManager($this);
        $this->logiaSystem = new LogiaSystem($this);
        $this->zoanSystem = new ZoanSystem($this);
        $this->awakeningSystem = new AwakeningSystem($this);
        $this->fruitVFX = new FruitVFX($this);

        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $map = $this->getServer()->getCommandMap();
        $map->register("onepiece", new FruitsCommand($this, "transform", "Transform/Awaken", "/transform help"));

        $this->getServer()->getScheduler()->scheduleRepeatingTask(new FruitsTickTask($this), 10);
    }

    /**
     * Save default skin files if they don't exist
     */
    private function saveDefaultSkins() {
        $skinFiles = ["wolf.png", "leopard.png", "phoenix.png", "dragon.png"];
        $skinsPath = $this->getDataFolder() . "skins/";

        foreach ($skinFiles as $file) {
            $dest = $skinsPath . $file;
            if (!file_exists($dest)) {
                $resource = $this->getResource("skins/" . $file);
                if ($resource !== null) {
                    file_put_contents($dest, stream_get_contents($resource));
                    fclose($resource);
                    $this->getLogger()->info("Saved default skin: " . $file);
                } else {
                    $this->getLogger()->warning("Missing resource: skins/" . $file);
                }
            }
        }
    }

    public function onDisable() {
        if ($this->awakeningSystem !== null) {
            $this->awakeningSystem->saveAll();
        }
    }

public function isInOPWorld(Player $player) {
    return in_array($player->getLevel()->getName(), $this->opWorlds);
}

    public function getLogiaSystem() { return $this->logiaSystem; }
    public function getZoanSystem() { return $this->zoanSystem; }
    public function getAwakeningSystem() { return $this->awakeningSystem; }
    public function getFruitVFX() {
        if ($this->fruitVFX === null) {
            $this->fruitVFX = new FruitVFX($this);
        }
        return $this->fruitVFX;
    }
    public function getSkinManager() { return $this->skinManager; }
    public function getDevilPlugin() { return $this->devilPlugin; }
    public function getHakiPlugin() { return $this->hakiPlugin; }
    public function getCombatPlugin() { return $this->combatPlugin; }
    public function getStatsPlugin() { return $this->statsPlugin; }

    /**
     * Get player's fruit type from Devil plugin
     */
    public function getPlayerFruitType(Player $player) {
        if ($this->devilPlugin === null) return null;
        try {
            $fm = $this->devilPlugin->getFruitManager();
            if ($fm === null) return null;
            $fruit = $fm->getPlayerFruit($player);
            if ($fruit === null) return null;
            return $fruit->getType();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get player's fruit ID from Devil plugin
     */
    public function getPlayerFruitId(Player $player) {
        if ($this->devilPlugin === null) return null;
        try {
            $fm = $this->devilPlugin->getFruitManager();
            if ($fm === null) return null;
            return $fm->getPlayerFruitId($player);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get player's fruit rarity
     */
    public function getPlayerFruitRarity(Player $player) {
        if ($this->devilPlugin === null) return null;
        try {
            $fm = $this->devilPlugin->getFruitManager();
            if ($fm === null) return null;
            $fruit = $fm->getPlayerFruit($player);
            if ($fruit === null) return null;
            return $fruit->getRarity();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if attacker has haki that can bypass logia
     */
    public function attackerHasHaki(Player $attacker) {
        if ($this->hakiPlugin === null) return false;
        try {
            $armament = $this->hakiPlugin->getArmament();
            if ($armament === null) return false;
            return $armament->isActive($attacker) && $armament->canHitLogia($attacker);
        } catch (\Exception $e) {
            return false;
        }
    }

    // ========================
    // Player quit cleanup
    // ========================

    public function onPlayerQuit(PlayerQuitEvent $event) {
        $player = $event->getPlayer();
        $this->zoanSystem->deactivate($player);
        $this->logiaSystem->deactivate($player);
        $this->awakeningSystem->deactivate($player);
        $this->skinManager->cleanup($player);
    }

    // ========================
    // Damage event: Logia + Zoan + Awakening
    // ========================

    public function onEntityDamage(EntityDamageEvent $event) {
        if (!($event instanceof EntityDamageByEntityEvent)) {
            return;
        }

        $damager = $event->getDamager();
        $victim = $event->getEntity();

        if (!($damager instanceof Player) || !($victim instanceof Player)) {
            return;
        }

        if (!$this->isInOPWorld($victim)) {
            return;
        }

        // Logia intangibility check
        if ($this->logiaSystem->isActive($victim)) {
            if ($this->attackerHasHaki($damager)) {
                $damager->sendTip(TextFormat::DARK_PURPLE . "Haki bypassed Logia!");
                $victim->sendTip(TextFormat::RED . "Logia pierced by Haki!");
                $this->fruitVFX->spawnHakiHitEffect($victim);
            } else {
                $event->setCancelled(true);
                $damager->sendTip(TextFormat::GRAY . "Attack passed through!");
                $victim->sendTip(TextFormat::AQUA . "Intangible!");
                $this->fruitVFX->spawnLogiaPassEffect($victim);
                return;
            }
        }

        $damage = $event->getDamage();

        // Damage boost: awakening takes priority over zoan, no stacking
        if ($this->awakeningSystem->isAwakened($damager)) {
            $damage = $damage * $this->awakeningSystem->getDamageBoost($damager);
        } elseif ($this->zoanSystem->isTransformed($damager)) {
            $damage = $damage * $this->zoanSystem->getDamageBoost($damager);
        }

        // Zoan defense boost
        if ($this->zoanSystem->isTransformed($victim)) {
            $defense = $this->zoanSystem->getDefenseBoost($victim);
            if ($defense > 1.0) {
                $damage = $damage / $defense;
            }
        }

        // Set final damage
        $event->setDamage(max(0.5, $damage));

        // VFX on hit
        $attackerType = $this->getPlayerFruitType($damager);
        if ($attackerType !== null) {
            $rarity = $this->getPlayerFruitRarity($damager);
            if ($rarity !== null) {
                $this->fruitVFX->spawnHitEffect($victim, $attackerType, $rarity);
            }
        }
    }

    // ========================
    // Interact: Transform triggers
    // ========================

    public function onPlayerInteract(PlayerInteractEvent $event) {
        $player = $event->getPlayer();
        $item = $event->getItem();

        if (!$this->isInOPWorld($player)) {
            return;
        }

        if (!$player->isSneaking()) {
            return;
        }

        $itemId = $item->getId();

        // Sneak + Feather = Toggle Logia/Zoan
        if ($itemId === self::TRANSFORM_ITEM) {
            $fruitType = $this->getPlayerFruitType($player);

            if ($fruitType === null) {
                $player->sendMessage(TextFormat::RED . "You don't have a Devil Fruit!");
                return;
            }

            switch ($fruitType) {
                case "logia":
                    $this->handleLogiaToggle($player);
                    break;
                case "zoan":
                    $this->handleZoanToggle($player);
                    break;
                case "paramecia":
                    $player->sendMessage(TextFormat::YELLOW . "Paramecia fruits use Blaze Rod for abilities.");
                    $player->sendMessage(TextFormat::GRAY . "Try /transform awaken to unlock awakening!");
                    break;
            }
            return;
        }

        // Sneak + Ghast Tear = Toggle Awakening
        if ($itemId === self::AWAKEN_ITEM) {
            $this->handleAwakeningToggle($player);
            return;
        }
    }

    private function handleLogiaToggle(Player $player) {
        if ($this->logiaSystem->isActive($player)) {
            $this->logiaSystem->deactivate($player);
            $player->sendMessage(TextFormat::GRAY . "Logia form deactivated.");
        } else {
            $this->logiaSystem->activate($player);
        }
    }

    private function handleZoanToggle(Player $player) {
        if ($this->zoanSystem->isTransformed($player)) {
            $this->zoanSystem->deactivate($player);
            $player->sendMessage(TextFormat::GRAY . "Zoan form deactivated.");
        } else {
            $this->zoanSystem->activate($player);
        }
    }

    private function handleAwakeningToggle(Player $player) {
        if (!$this->awakeningSystem->hasUnlockedAwakening($player)) {
            $player->sendMessage(TextFormat::RED . "You haven't unlocked Awakening!");
            $player->sendMessage(TextFormat::GRAY . "Use /transform awaken");
            return;
        }

        if ($this->awakeningSystem->isAwakened($player)) {
            $this->awakeningSystem->deactivate($player);
            $player->sendMessage(TextFormat::GRAY . "Awakening deactivated.");
        } else {
            $this->awakeningSystem->activate($player);
        }
    }
}