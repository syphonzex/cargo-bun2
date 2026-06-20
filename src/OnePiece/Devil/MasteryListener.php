<?php

namespace OnePiece\Devil;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\Player;

class MasteryListener implements Listener {

    /** @var Main */
    private $plugin;

    /** @var array [victimName => attackerName] */
    private $lastAbilityAttacker = [];

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    /**
     * Track who last hit who with a fruit ability (for kill credit)
     * @EventHandler
     * @priority MONITOR
     */
    public function onDamageTrack(EntityDamageEvent $event) {
        if (!($event instanceof EntityDamageByEntityEvent)) return;
        $attacker = $event->getDamager();
        $victim = $event->getEntity();

        if (!($attacker instanceof Player)) return;
        if (!($victim instanceof Player)) return;

        // Only track if ability was active
        if ($this->plugin->isAbilityActive($attacker)) {
            $this->lastAbilityAttacker[$victim->getName()] = $attacker->getName();
        }
    }

    /**
     * Grant kill EXP
     * @EventHandler
     */
    public function onPlayerDeath(PlayerDeathEvent $event) {
        $victim = $event->getEntity();
        $victimName = $victim->getName();

        if (!isset($this->lastAbilityAttacker[$victimName])) return;

        $attackerName = $this->lastAbilityAttacker[$victimName];
        $attacker = $this->plugin->getServer()->getPlayer($attackerName);

        if ($attacker !== null) {
            $this->plugin->getMasteryManager()->onAbilityKill($attacker);
        }

        unset($this->lastAbilityAttacker[$victimName]);
    }

    /**
     * Cleanup
     * @EventHandler
     */
    public function onPlayerQuit(PlayerQuitEvent $event) {
        $name = $event->getPlayer()->getName();
        unset($this->lastAbilityAttacker[$name]);
    }
}