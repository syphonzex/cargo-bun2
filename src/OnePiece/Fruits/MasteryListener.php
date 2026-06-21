<?php

namespace OnePiece\Fruits;

use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\Player;

/**
 * Hooks mastery EXP gain into combat events
 */
class MasteryListener implements Listener {

    /** @var Main */
    private $plugin;

    /** @var array Track last ability user for kill credit [victimName => attackerName] */
    private $lastAbilityAttacker = [];

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    /**
     * Track ability hits for mastery EXP
     */
    public function onEntityDamage(EntityDamageByEntityEvent $event) {
        $attacker = $event->getDamager();
        $victim = $event->getEntity();

        if (!($attacker instanceof Player)) return;

        $name = $attacker->getName();
        $mm = $this->plugin->getMasteryManager();

        // Only grant EXP if player has a fruit
        if ($mm->getData($name) === null) return;

        // Check if damage was from a fruit ability (tagged by ability system)
        if ($this->isAbilityDamage($event)) {
            if ($victim instanceof Player) {
                $mm->onAbilityHitPlayer($attacker);
                $this->lastAbilityAttacker[$victim->getName()] = $name;
            } else {
                $mm->onNpcHit($attacker);
            }
        }
    }

    /**
     * Grant kill EXP
     */
    public function onPlayerDeath(PlayerDeathEvent $event) {
        $victim = $event->getEntity();
        $victimName = $victim->getName();

        if (isset($this->lastAbilityAttacker[$victimName])) {
            $attackerName = $this->lastAbilityAttacker[$victimName];
            $attacker = $this->plugin->getServer()->getPlayer($attackerName);

            if ($attacker !== null) {
                $mm = $this->plugin->getMasteryManager();
                if ($victim instanceof Player) {
                    $mm->onAbilityKill($attacker);
                } else {
                    $mm->onNpcKill($attacker);
                }
            }

            unset($this->lastAbilityAttacker[$victimName]);
        }
    }

    /**
     * Cleanup on quit
     */
    public function onPlayerQuit(PlayerQuitEvent $event) {
        $name = $event->getPlayer()->getName();
        unset($this->lastAbilityAttacker[$name]);

        // Save mastery data
        $this->plugin->getMasteryManager()->savePlayer($name);
    }

    /**
     * Check if damage event was from a fruit ability
     * Uses custom damage cause or NBT tag set by ability system
     */
    private function isAbilityDamage(EntityDamageByEntityEvent $event) {
        // Check if cause is custom (set by ability handlers)
        $cause = $event->getCause();

        // In your ability code, when dealing damage, set cause to CUSTOM (16)
        // or add a flag. For now we check if attacker is holding blaze rod
        $attacker = $event->getDamager();
        if ($attacker instanceof Player) {
            $item = $attacker->getInventory()->getItemInHand();
            if ($item->getId() === 369) { // Blaze rod = fruit ability item
                return true;
            }
        }

        return false;
    }
}