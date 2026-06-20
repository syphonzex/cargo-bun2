<?php

namespace OnePiece\Devil\fruits;

use OnePiece\Devil\BaseFruit;
use OnePiece\Devil\Main;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\math\Vector3;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\entity\Entity;
use pocketmine\entity\Effect;
use pocketmine\utils\TextFormat;
use pocketmine\item\Item;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\scheduler\Task;
use pocketmine\level\particle\DustParticle;
use pocketmine\level\particle\PortalParticle;
use pocketmine\level\particle\EnchantmentTableParticle;
use pocketmine\level\particle\CriticalParticle;
use pocketmine\level\particle\ExplodeParticle;
use pocketmine\level\particle\LargeExplodeParticle;
use pocketmine\level\particle\HugeExplodeParticle;
use pocketmine\level\particle\InstantEnchantParticle;
use pocketmine\level\particle\SmokeParticle;
use pocketmine\level\sound\EndermanTeleportSound;
use pocketmine\level\sound\AnvilUseSound;
use pocketmine\level\sound\PopSound;
use pocketmine\level\sound\ClickSound;
use OnePiece\Devil\BlockEffects;
use pocketmine\network\protocol\LevelEventPacket;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\inventory\BaseInventory;
use pocketmine\inventory\FakeBlockMenu;
use pocketmine\inventory\InventoryType;
use pocketmine\network\protocol\UpdateBlockPacket;
use pocketmine\network\protocol\BlockEntityDataPacket;
use pocketmine\network\protocol\ContainerOpenPacket;
use pocketmine\network\protocol\BlockEventPacket;
use OnePiece\NPC\NPCEntity;
use OnePieceTrades\Factory\FactoryEntity;

class GiroGiro extends BaseFruit {

    public function getId()          { return "giro_giro"; }
    public function getDisplayName() { return "Portal-Portal Fruit"; }
    public function getDescription() { return "Portal Fruit - Tear through space itself and crush reality."; }
    public function getType()        { return "paramecia"; }
    public function getRarity()      { return "legendary"; }

    const OP_ZONES = [
        "Starter_Island"  => ["name" => "Starter Island",   "x" => 39,  "y" => 69,  "z" => 126, "restrict" => 1],
        "Desert"          => ["name" => "Desert Island",    "x" => 179, "y" => 69,  "z" => 127, "restrict" => 10],
        "Lower_Skypiea"   => ["name" => "Lower Skypiea",    "x" => 107, "y" => 69,  "z" => 125, "restrict" => 20],
        "Punk-Hazard"     => ["name" => "Punk Hazard",      "x" => 131, "y" => 69,  "z" => 78,  "restrict" => 30],
        "Volcanic_Island" => ["name" => "Volcanic Island",  "x" => 131, "y" => 69,  "z" => 179, "restrict" => 40],
        "Upper_Skypiea"   => ["name" => "Upper Skypiea",    "x" => 129, "y" => 114, "z" => 114, "restrict" => 45],
    ];

    const SEA2_ZONES = [
        "Sabaody_Archapelago" => ["name" => "Sabaody Archapelago", "x" => 3,   "y" => 13, "z" => 1,   "restrict" => 60],
        "Graveyard"           => ["name" => "Graveyard",           "x" => 212, "y" => 13, "z" => 14,  "restrict" => 80],
        "Dressrosa"           => ["name" => "Dressrosa",           "x" => 242, "y" => 13, "z" => 212, "restrict" => 100],
        "Ice_Castle"          => ["name" => "Ice Castle",          "x" => 44,  "y" => 13, "z" => 250, "restrict" => 135],
        "Marineford"          => ["name" => "Marineford",          "x" => 129, "y" => 43, "z" => 128, "restrict" => 160],
        "Colosseum"           => ["name" => "Colosseum",           "x" => 121, "y" => 13, "z" => 212, "restrict" => 185],
        "Fishman_Island"      => ["name" => "Fishman Island",      "x" => -188, "y" => 7, "z" => 104, "restrict" => 205],
    ];

    const SEA3_ZONES = [
        "Port_Town"      => ["name" => "Port Town",      "x" => 131,  "y" => 9,  "z" => 274,  "restrict" => 250],
        "Amazon_Lily"    => ["name" => "Amazon Lily",    "x" => 128,  "y" => 11, "z" => -199, "restrict" => 280],
        "Sea_Castle"     => ["name" => "Sea Castle",     "x" => 159,  "y" => 9,  "z" => 128,  "restrict" => 300],
        "Thriller_Bark"  => ["name" => "Thriller Bark",  "x" => -114, "y" => 9,  "z" => 128,  "restrict" => 350],
        "Whole_Cake"     => ["name" => "Whole Cake",     "x" => 253,  "y" => 11, "z" => 86,   "restrict" => 400],
        "Wano"           => ["name" => "Wano",           "x" => -181, "y" => 30, "z" => -123, "restrict" => 450],
        "Onigashima"     => ["name" => "Onigashima",     "x" => -114, "y" => 9,  "z" => -79,  "restrict" => 500],
    ];

    private static $openMenus = [];

    public function getAbilityNames() {
        return ["ability1" => "Void Rift", "ability2" => "World Warp"];
    }

    public function getAbilityCooldowns() {
        return ["ability1" => 6.0, "ability2" => 20.0];
    }

    public function useAbility(Player $player, $ability) {
        switch ($ability) {
            case "ability1": return $this->voidRift($player);
            case "ability2": return $this->worldWarp($player);
        }
        return 0;
    }

    private function voidRift(Player $player) {
        $mult   = min(1.5, $this->getHakiMultiplier($player));
        $damage = 4.0 * $mult;
        $range  = 13.0;

        $pos = $player->getPosition();
        $dir = $player->getDirectionVector();
        $lv  = $player->getLevel();

        $task = new VoidRiftTask(
            $this->plugin,
            $lv,
            $pos->x, $pos->y + 1.2, $pos->z,
            $dir->x, $dir->y, $dir->z,
            $range,
            $damage,
            $player->getName()
        );
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($task, 2);

        $player->sendTip(TextFormat::LIGHT_PURPLE . TextFormat::BOLD . "VOID RIFT!");
        $lv->addSound(new EndermanTeleportSound($pos));

        return $this->getAbilityCooldowns()["ability1"];
    }

    private function worldWarp(Player $player) {
        $level = $player->getLevel();
        $levelName = $level->getName();

        if ($levelName === "OP" || $levelName === "OP1") {
            $zones = self::OP_ZONES;
            $worldType = "OP";
        } elseif ($levelName === "Sea2" || $levelName === "Sea21") {
            $zones = self::SEA2_ZONES;
            $worldType = "Sea2";
        } elseif ($levelName === "sea3" || $levelName === "Sea31") {
            $zones = self::SEA3_ZONES;
            $worldType = "sea3";
        } else {
            $player->sendMessage(TextFormat::RED . "World Warp only works in OP, Sea2, or Sea3!");
            return 0;
        }

        $playerLevel = $this->getPlayerLevel($player);

        self::$openMenus[strtolower($player->getName())] = [
            "zones"  => $zones,
            "world"  => $worldType,
            "folder" => $level->getFolderName(),
            "plevel" => $playerLevel,
            "inv"    => null
        ];

        $inv = new PortalMenuInventory($player, "§5§lWorld Warp §8- §7" . ($worldType === "OP" ? "Sea 1" : ($worldType === "Sea2" ? "Sea 2" : "Sea 3")));
        $this->fillPortalMenu($player, $inv, $zones);

        self::$openMenus[strtolower($player->getName())]["inv"] = $inv;

        $player->addWindow($inv);

        $this->spawnWarpOpenVFX($player);

        return $this->getAbilityCooldowns()["ability2"];
    }

    private function fillPortalMenu(Player $player, PortalMenuInventory $inv, array $zones) {
        $pane = Item::get(102, 0, 1);
        $pane->setCustomName(" ");
        for ($i = 0; $i < 27; $i++) {
            $inv->setItem($i, clone $pane);
        }

        $titleItem = Item::get(Item::PAPER, 0, 1);
        $titleItem->setCustomName("§5§lWORLD WARP\n§7Choose a destination");
        $inv->setItem(4, $titleItem);

        $slots = [10, 11, 12, 13, 14, 15, 16];
        $i = 0;
        foreach ($zones as $key => $zone) {
            if ($i >= count($slots)) break;
            $slot     = $slots[$i];
            $restrict = $zone["restrict"];

            $zoneItem = Item::get(Item::GOLD_INGOT, 0, 1);
            $zoneItem->setCustomName("§5" . $zone["name"] . "\n§7Tap to warp\n§8Lv.§e" . $restrict . "§8+");
            $inv->setItem($slot, $zoneItem);
            $i++;
        }

        $closeItem = Item::get(Item::DYE, 1, 1);
        $closeItem->setCustomName("§cClose");
        $inv->setItem(26, $closeItem);
    }

    public static function handleMenuClick(Player $player, $slot, Main $plugin) {
        $name = strtolower($player->getName());
        if (!isset(self::$openMenus[$name])) return;

        $data   = self::$openMenus[$name];
        $zones  = $data["zones"];
        $world  = $data["world"];
        $folder = $data["folder"];
        $inv    = $data["inv"];

        if ($slot === 26) {
            if ($inv !== null) $player->removeWindow($inv);
            unset(self::$openMenus[$name]);
            return;
        }

        $slots    = [10, 11, 12, 13, 14, 15, 16];
        $zoneKeys = array_keys($zones);
        $idx      = array_search($slot, $slots);

        if ($idx === false || !isset($zoneKeys[$idx])) return;

        $zoneKey = $zoneKeys[$idx];
        $zone    = $zones[$zoneKey];

        $server = Server::getInstance();

        if (!$server->isLevelLoaded($folder)) {
            $server->loadLevel($folder);
        }
        $level = $server->getLevelByName($folder);
        if ($level === null) {
            $player->sendMessage(TextFormat::RED . "World not found!");
            return;
        }

        $plugin->getServer()->getScheduler()->scheduleRepeatingTask(
            new PortalWarpTask($plugin, $player->getName(), $folder, $zone["x"], $zone["y"], $zone["z"], $zone["name"]),
            2
        );

        if ($inv !== null) $player->removeWindow($inv);
        unset(self::$openMenus[$name]);
    }

    public static function closeMenu(Player $player) {
        unset(self::$openMenus[strtolower($player->getName())]);
    }

    public static function hasOpenMenu(Player $player) {
        return isset(self::$openMenus[strtolower($player->getName())]);
    }

    private function getPlayerLevel(Player $player) {
        $statsPlugin = $this->plugin->getStatsPlugin();
        if ($statsPlugin === null) return 1;
        $sp = $statsPlugin->getStatManager()->getStatPlayer($player);
        if ($sp === null) return 1;
        return $sp->getLevel();
    }

    private function spawnWarpOpenVFX(Player $player) {
        $lv  = $player->getLevel();
        $pos = $player->getPosition();
        $px  = $pos->x;
        $py  = $pos->y + 1;
        $pz  = $pos->z;

        for ($ring = 0; $ring < 3; $ring++) {
            $r   = 1.0 + $ring * 0.6;
            $pts = 10 + $ring * 4;
            for ($i = 0; $i < $pts; $i++) {
                $a = ($i / $pts) * M_PI * 2;
                $lv->addParticle(new DustParticle(
                    new Vector3($px + cos($a) * $r, $py + $ring * 0.3, $pz + sin($a) * $r),
                    160, 0, 255
                ));
            }
        }

        for ($i = 0; $i < 12; $i++) {
            $a = ($i / 12) * M_PI * 2;
            $lv->addParticle(new PortalParticle(new Vector3(
                $px + cos($a) * 1.5,
                $py + (mt_rand(-5, 10) / 10),
                $pz + sin($a) * 1.5
            )));
        }

        for ($i = 0; $i < 6; $i++) {
            $lv->addParticle(new EnchantmentTableParticle(new Vector3(
                $px + (mt_rand(-15, 15) / 10),
                $py + (mt_rand(0, 20) / 10),
                $pz + (mt_rand(-15, 15) / 10)
            )));
        }

        $lv->addSound(new EndermanTeleportSound($pos));
    }

    public function onEquip(Player $player) {
        $player->sendMessage(TextFormat::LIGHT_PURPLE . "=== Giro Giro no Mi ===");
        $player->sendMessage(TextFormat::WHITE . "Portal Fruit - Master of Space");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::LIGHT_PURPLE . "[Tap]: " . TextFormat::WHITE . "VOID RIFT");
        $player->sendMessage(TextFormat::GRAY . "  Launch a tearing void rift forward");
        $player->sendMessage("");
        $player->sendMessage(TextFormat::LIGHT_PURPLE . "[Sneak+Tap]: " . TextFormat::WHITE . "WORLD WARP");
        $player->sendMessage(TextFormat::GRAY . "  Open a portal menu to teleport to any island");
        $player->sendMessage(TextFormat::LIGHT_PURPLE . "=======================");
    }

    public function onUnequip(Player $player) {
        self::closeMenu($player);
        $player->sendMessage(TextFormat::GRAY . "Portals close...");
    }
}

class VoidRiftTask extends Task {

    private $plugin;
    private $level;
    private $sx, $sy, $sz;
    private $dx, $dy, $dz;
    private $range;
    private $damage;
    private $ownerName;

    private $ticksRan  = 0;
    private $maxTicks  = 20;
    private $currentDist = 0.0;
    private $hitEntities = [];
    private $cleaned   = false;

    private $portalEids = [];

    public function __construct($plugin, Level $level, $sx, $sy, $sz, $dx, $dy, $dz, $range, $damage, $ownerName) {
        $this->plugin    = $plugin;
        $this->level     = $level;
        $this->sx        = (float)$sx;
        $this->sy        = (float)$sy;
        $this->sz        = (float)$sz;
        $this->dx        = (float)$dx;
        $this->dy        = (float)$dy * 0.2;
        $this->dz        = (float)$dz;
        $this->range     = (float)$range;
        $this->damage    = $damage;
        $this->ownerName = $ownerName;

        $this->spawnPortalBlocks();
    }

    private function spawnPortalBlocks() {
        for ($i = 0; $i < 5; $i++) {
            $eid = BlockEffects::newEid();
            $this->portalEids[] = $eid;
            BlockEffects::sendSpawn($this->level, $eid, 90, 0, $this->sx, $this->sy, $this->sz);
        }
    }

    public function onRun($currentTick) {
        if ($this->cleaned) return;

        $this->ticksRan++;

        if ($this->ticksRan > $this->maxTicks || $this->currentDist >= $this->range) {
            $this->forceCleanup();
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
            return;
        }

        $this->currentDist += 1.8;

        $cx = $this->sx + $this->dx * $this->currentDist;
        $cy = $this->sy + $this->dy * $this->currentDist;
        $cz = $this->sz + $this->dz * $this->currentDist;

        $spread = 0.5 + ($this->currentDist / $this->range) * 1.8;

        foreach ($this->portalEids as $idx => $eid) {
            $angle  = ($idx / count($this->portalEids)) * M_PI * 2 + $this->ticksRan * 0.5;
            $radius = $spread * 0.7;

            $bx = $cx + cos($angle) * $radius;
            $by = $cy + sin($angle) * $spread * 0.4;
            $bz = $cz + sin($angle) * $radius;

            BlockEffects::sendMove($this->level, $eid, $bx, $by, $bz, $this->ticksRan * 40, $this->ticksRan * 30);
        }

        $pts = 10;
        for ($i = 0; $i < $pts; $i++) {
            $a  = ($i / $pts) * M_PI * 2 + $this->ticksRan * 0.4;
            $r  = $spread;
            $this->level->addParticle(new PortalParticle(new Vector3(
                $cx + cos($a) * $r,
                $cy + sin($a) * $spread * 0.5,
                $cz + sin($a) * $r
            )));
        }

        for ($i = 0; $i < 6; $i++) {
            $a = ($i / 6) * M_PI * 2 - $this->ticksRan * 0.3;
            $this->level->addParticle(new DustParticle(
                new Vector3($cx + cos($a) * $spread, $cy + 0.3, $cz + sin($a) * $spread),
                130, 0, 255
            ));
        }

        for ($i = 0; $i < 4; $i++) {
            $a = ($i / 4) * M_PI * 2 + $this->ticksRan * 0.6;
            $this->level->addParticle(new DustParticle(
                new Vector3($cx + cos($a) * ($spread * 0.6), $cy + 0.6, $cz + sin($a) * ($spread * 0.6)),
                60, 0, 180
            ));
        }

        for ($i = 0; $i < 3; $i++) {
            $this->level->addParticle(new EnchantmentTableParticle(new Vector3(
                $cx + (mt_rand(-8, 8) / 10) * $spread,
                $cy + (mt_rand(-3, 8) / 10),
                $cz + (mt_rand(-8, 8) / 10) * $spread
            )));
        }

        if ($this->ticksRan % 2 === 0) {
            $this->level->addParticle(new InstantEnchantParticle(new Vector3($cx, $cy + 0.5, $cz)));
        }

        $this->checkHits($cx, $cy, $cz, $spread + 1.5);
    }

    private function checkHits($cx, $cy, $cz, $hitRadius) {
        $owner = $this->plugin->getServer()->getPlayerExact($this->ownerName);

        foreach ($this->level->getEntities() as $entity) {
            if (!$entity->isAlive() || $entity->closed) continue;

            $eid = $entity->getId();
            if (isset($this->hitEntities[$eid])) continue;

            $valid = false;
            if ($entity instanceof Player) {
                if ($entity->getName() === $this->ownerName) continue;
                if (!$this->plugin->canTargetPlayer($this->ownerName, $entity)) continue;
                $valid = true;
            } elseif ($entity instanceof NPCEntity || $entity instanceof FactoryEntity) {
                $valid = true;
            }
            if (!$valid) continue;

            $dx   = $entity->x - $cx;
            $dy   = ($entity->y + 1) - $cy;
            $dz   = $entity->z - $cz;
            $dist = sqrt($dx * $dx + $dy * $dy + $dz * $dz);

            if ($dist <= $hitRadius) {
                if (!($entity instanceof FactoryEntity)) {
                    $this->hitEntities[$eid] = true;
                }

                if ($owner !== null) {
                    $ev = new EntityDamageByEntityEvent($owner, $entity, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->damage);
                    $entity->attack($this->damage, $ev);
                }

                BaseFruit::staticSafeSetMotion($owner, $entity, new Vector3($this->dx * 1.0, 0.5, $this->dz * 1.0));

                if ($entity instanceof Player) {
                    $slow = Effect::getEffect(Effect::SLOWNESS);
                    $slow->setAmplifier(1);
                    $slow->setDuration(40);
                    $slow->setVisible(false);
                    BaseFruit::staticSafeAddEffect($owner, $entity, $slow);
                    $entity->sendTip(TextFormat::LIGHT_PURPLE . TextFormat::BOLD . "VOID RIFT!");
                }

                for ($ring = 0; $ring < 3; $ring++) {
                    for ($i = 0; $i < 8; $i++) {
                        $a = ($i / 8) * M_PI * 2;
                        $r = 0.5 + $ring * 0.4;
                        $this->level->addParticle(new PortalParticle(new Vector3(
                            $entity->x + cos($a) * $r,
                            $entity->y + 1 + $ring * 0.3,
                            $entity->z + sin($a) * $r
                        )));
                    }
                }

                for ($i = 0; $i < 6; $i++) {
                    $this->level->addParticle(new DustParticle(
                        new Vector3(
                            $entity->x + (mt_rand(-10, 10) / 10),
                            $entity->y + (mt_rand(10, 20) / 10),
                            $entity->z + (mt_rand(-10, 10) / 10)
                        ),
                        180, 0, 255
                    ));
                }

                $this->level->addParticle(new LargeExplodeParticle(new Vector3($entity->x, $entity->y + 1, $entity->z)));
            }
        }
    }

    public function forceCleanup() {
        if ($this->cleaned) return;
        $this->cleaned = true;
        BlockEffects::voidAndRemove($this->plugin, $this->level, $this->portalEids);
        $this->portalEids = [];
    }
}

class PortalWarpTask extends Task {

    private $plugin;
    private $playerName;
    private $worldName;
    private $destX, $destY, $destZ;
    private $zoneName;
    private $originX, $originY, $originZ;
    private $originWorld;
    private $originYaw = 0.0;
    private $phase     = 0;
    private $phaseTick = 0;
    private $cleaned   = false;

    const WARP_DAMAGE       = 6.0;
    const WARP_DAMAGE_RADIUS = 5.0;

    public function __construct($plugin, $playerName, $worldName, $destX, $destY, $destZ, $zoneName) {
        $this->plugin      = $plugin;
        $this->playerName  = $playerName;
        $this->worldName   = $worldName;
        $this->destX       = $destX;
        $this->destY       = $destY;
        $this->destZ       = $destZ;
        $this->zoneName    = $zoneName;
    }

    public function onRun($currentTick) {
        if ($this->cleaned) return;

        $player = $this->plugin->getServer()->getPlayerExact($this->playerName);
        if ($player === null || !$player->isOnline()) {
            $this->cleanup();
            return;
        }

        $this->phaseTick++;

        if ($this->phase === 0) {
            if ($this->phaseTick === 1) {
                $this->originX     = $player->x;
                $this->originY     = $player->y;
                $this->originZ     = $player->z;
                $this->originWorld = $player->getLevel()->getFolderName();
                $this->originYaw   = deg2rad($player->getYaw());

                $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask(
                    new PortalOpenTask(
                        $this->plugin,
                        $player->getLevel(),
                        $player->x, $player->y + 1, $player->z,
                        $this->originYaw,
                        10,
                        $this->playerName,
                        $this->destX, $this->destY, $this->destZ, $this->worldName,
                        $this->originYaw + M_PI
                    ),
                    2
                );
            }
            $this->doExitVFX($player);
            if ($this->phaseTick >= 6) {
                $this->dealWarpDamage($player, true);
                $this->phase     = 1;
                $this->phaseTick = 0;
                $this->doTeleport($player);
            }
        } elseif ($this->phase === 1) {
            $this->doArrivalVFX($player);
            if ($this->phaseTick === 1) {
                $this->dealWarpDamage($player, false);
            }
            if ($this->phaseTick >= 8) {
                $this->cleanup();
            }
        }
    }

    private function dealWarpDamage(Player $player, $isExit) {
        $lv  = $player->getLevel();
        $px  = $player->x;
        $py  = $player->y;
        $pz  = $player->z;
        $r   = self::WARP_DAMAGE_RADIUS;

        foreach ($lv->getEntities() as $entity) {
            if ($entity === $player) continue;
            if (!$entity->isAlive() || $entity->closed) continue;

            $valid = false;
            if ($entity instanceof Player) {
                if (!$this->plugin->canTargetPlayer($this->playerName, $entity)) continue;
                $valid = true;
            } elseif ($entity instanceof NPCEntity || $entity instanceof FactoryEntity) {
                $valid = true;
            }
            if (!$valid) continue;

            $dx   = $entity->x - $px;
            $dz   = $entity->z - $pz;
            $dist = sqrt($dx * $dx + $dz * $dz);
            if ($dist > $r) continue;

            $scaled = self::WARP_DAMAGE * (1.0 - ($dist / $r) * 0.3);
            $ev = new EntityDamageByEntityEvent($player, $entity, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $scaled);
            $entity->attack($scaled, $ev);

            $len = max(0.1, $dist);
            BaseFruit::staticSafeSetMotion($player, $entity, new Vector3(($dx / $len) * 1.2, 0.6, ($dz / $len) * 1.2));

            if ($entity instanceof Player) {
                $entity->sendTip(TextFormat::LIGHT_PURPLE . TextFormat::BOLD . ($isExit ? "PORTAL EXPLOSION!" : "WARP IMPACT!"));
            }

            for ($i = 0; $i < 6; $i++) {
                $a = ($i / 6) * M_PI * 2;
                $lv->addParticle(new DustParticle(
                    new Vector3($entity->x + cos($a) * 0.8, $entity->y + 1, $entity->z + sin($a) * 0.8),
                    160, 0, 255
                ));
            }
            $lv->addParticle(new LargeExplodeParticle(new Vector3($entity->x, $entity->y + 1, $entity->z)));
        }
    }

    private function doExitVFX(Player $player) {
        $lv  = $player->getLevel();
        $pos = $player->getPosition();
        $px  = $pos->x;
        $py  = $pos->y + 1;
        $pz  = $pos->z;

        $pts = 12;
        for ($i = 0; $i < $pts; $i++) {
            $a = ($i / $pts) * M_PI * 2 + $this->phaseTick * 0.5;
            $r = max(0.1, 1.5 - $this->phaseTick * 0.2);
            $lv->addParticle(new DustParticle(
                new Vector3($px + cos($a) * $r, $py, $pz + sin($a) * $r),
                160, 0, 255
            ));
        }

        for ($i = 0; $i < 8; $i++) {
            $lv->addParticle(new PortalParticle(new Vector3(
                $px + (mt_rand(-15, 15) / 10),
                $py + (mt_rand(-5, 15) / 10),
                $pz + (mt_rand(-15, 15) / 10)
            )));
        }

        for ($i = 0; $i < 4; $i++) {
            $lv->addParticle(new EnchantmentTableParticle(new Vector3(
                $px + (mt_rand(-10, 10) / 10),
                $py + (mt_rand(0, 20) / 10),
                $pz + (mt_rand(-10, 10) / 10)
            )));
        }

        if ($this->phaseTick === 1) {
            $lv->addParticle(new HugeExplodeParticle(new Vector3($px, $py, $pz)));
            $lv->addSound(new EndermanTeleportSound($pos));
        }
    }

    private function doTeleport(Player $player) {
        $server = $this->plugin->getServer();

        if (!$server->isLevelLoaded($this->worldName)) {
            $server->loadLevel($this->worldName);
        }
        $level = $server->getLevelByName($this->worldName);
        if ($level === null) {
            $player->sendMessage(TextFormat::RED . "World not found!");
            $this->cleanup();
            return;
        }

        $player->teleport(new Position($this->destX, $this->destY, $this->destZ, $level));

        $destYaw = $this->originYaw + M_PI;

        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask(
            new PortalOpenTask(
                $this->plugin,
                $level,
                $this->destX, $this->destY + 1, $this->destZ,
                $destYaw,
                10,
                $this->playerName,
                $this->originX, $this->originY, $this->originZ, $this->originWorld,
                $this->originYaw
            ),
            2
        );

        $player->sendMessage(TextFormat::LIGHT_PURPLE . "Warped to " . TextFormat::WHITE . $this->zoneName . TextFormat::LIGHT_PURPLE . "!");
    }

    private function doArrivalVFX(Player $player) {
        $lv  = $player->getLevel();
        $pos = $player->getPosition();
        $px  = $pos->x;
        $py  = $pos->y + 1;
        $pz  = $pos->z;

        $pts = 12;
        for ($i = 0; $i < $pts; $i++) {
            $a = ($i / $pts) * M_PI * 2 + $this->phaseTick * 0.4;
            $r = 0.3 + $this->phaseTick * 0.25;
            $lv->addParticle(new DustParticle(
                new Vector3($px + cos($a) * $r, $py, $pz + sin($a) * $r),
                160, 0, 255
            ));
        }

        for ($i = 0; $i < 6; $i++) {
            $lv->addParticle(new PortalParticle(new Vector3(
                $px + (mt_rand(-15, 15) / 10),
                $py + (mt_rand(-5, 15) / 10),
                $pz + (mt_rand(-15, 15) / 10)
            )));
        }

        if ($this->phaseTick === 1) {
            $lv->addParticle(new LargeExplodeParticle(new Vector3($px, $py, $pz)));
            $lv->addSound(new PopSound($pos));
            $lv->addSound(new EndermanTeleportSound($pos));
        }
    }

    private function cleanup() {
        if ($this->cleaned) return;
        $this->cleaned = true;
        $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
    }
}

class PortalOpenTask extends Task {

    private $plugin;
    private $level;
    private $cx, $cy, $cz;
    private $yaw;
    private $phase      = 0.0;
    private $ticksRan   = 0;
    private $totalTicks;
    private $cleaned    = false;

    private $ownerName;
    private $linkedX, $linkedY, $linkedZ, $linkedWorld, $linkedYaw;

    private $portalEids = [];

    private static $globalCooldown = [];

    const VIEW_RANGE              = 48;
    const STEP_RADIUS             = 1.8;
    const TELEPORT_COOLDOWN_TICKS = 60;

    public function __construct(
        $plugin, Level $level,
        $cx, $cy, $cz, $yaw,
        $durationSeconds,
        $ownerName,
        $linkedX, $linkedY, $linkedZ, $linkedWorld, $linkedYaw
    ) {
        $this->plugin      = $plugin;
        $this->level       = $level;
        $this->cx          = (float)$cx;
        $this->cy          = (float)$cy;
        $this->cz          = (float)$cz;
        $this->yaw         = (float)$yaw;
        $this->totalTicks  = (int)($durationSeconds * 10);
        $this->ownerName   = $ownerName;
        $this->linkedX     = (float)$linkedX;
        $this->linkedY     = (float)$linkedY;
        $this->linkedZ     = (float)$linkedZ;
        $this->linkedWorld = $linkedWorld;
        $this->linkedYaw   = (float)$linkedYaw;

        $this->spawnPortalBlocks();
    }

    private function spawnPortalBlocks() {
        $blocks = [95, 95, 95, 95, 95, 95, 90, 90];
        $metas  = [3, 11, 5, 1, 14, 6, 0, 0];
        
        for ($i = 0; $i < count($blocks); $i++) {
            $eid = BlockEffects::newEid();
            $this->portalEids[] = $eid;
            BlockEffects::sendSpawn($this->level, $eid, $blocks[$i], $metas[$i], $this->cx, $this->cy, $this->cz);
        }
    }

    public function onRun($currentTick) {
        if ($this->cleaned) return;

        $this->ticksRan++;
        if ($this->ticksRan > $this->totalTicks) {
            $this->cleanup();
            return;
        }

        $this->drawPortal();

        if ($this->ticksRan % 5 === 0) {
            $this->checkStepIn($currentTick);
        }
    }

    private function checkStepIn($currentTick) {
        $combatPlugin = $this->plugin->getServer()->getPluginManager()->getPlugin("OnePieceCombat");
        $combatTag    = ($combatPlugin !== null && $combatPlugin->isEnabled()) ? $combatPlugin->getCombatTag() : null;

        foreach ($this->level->getPlayers() as $player) {
            $name = strtolower($player->getName());

            if (isset(self::$globalCooldown[$name]) && $currentTick < self::$globalCooldown[$name]) continue;

            $dx   = $player->x - $this->cx;
            $dz   = $player->z - $this->cz;
            $dist = sqrt($dx * $dx + $dz * $dz);
            if ($dist > self::STEP_RADIUS) continue;

            $isOwner = ($name === strtolower($this->ownerName));

            if (!$isOwner) {
                if ($combatTag === null || !$combatTag->isTagged($player)) continue;
            }

            self::$globalCooldown[$name] = $currentTick + self::TELEPORT_COOLDOWN_TICKS;
            $this->doPortalTeleport($player);
        }
    }

    private function doPortalTeleport(Player $player) {
        $server = $this->plugin->getServer();
        if (!$server->isLevelLoaded($this->linkedWorld)) {
            $server->loadLevel($this->linkedWorld);
        }
        $level = $server->getLevelByName($this->linkedWorld);
        if ($level === null) return;

        $fwdX  = -sin($this->linkedYaw);
        $fwdZ  =  cos($this->linkedYaw);
        $destX = $this->linkedX + $fwdX;
        $destY = $this->linkedY;
        $destZ = $this->linkedZ + $fwdZ;

        $yawDeg = rad2deg($this->linkedYaw);
        $player->teleport(new Position($destX, $destY, $destZ, $level), $yawDeg, $player->getPitch());

        $this->level->addParticle(new HugeExplodeParticle(new Vector3($this->cx, $this->cy + 1, $this->cz)));
        $this->level->addSound(new EndermanTeleportSound(new Vector3($this->cx, $this->cy, $this->cz)));
        $level->addParticle(new LargeExplodeParticle(new Vector3($destX, $destY + 1, $destZ)));
        $level->addSound(new PopSound(new Vector3($destX, $destY, $destZ)));
        $player->sendTip(TextFormat::LIGHT_PURPLE . TextFormat::BOLD . "PORTAL!");
    }

    private function drawPortal() {
        $this->phase += 0.22;
        $t      = $this->phase;
        $cx     = $this->cx;
        $cy     = $this->cy;
        $cz     = $this->cz;

        $rightX = cos($this->yaw);
        $rightZ = sin($this->yaw);
        $outerR = 1.4;
        $innerR = 0.8;

        $scale = 1.0 + sin($t * 0.5) * 0.15;

        foreach ($this->portalEids as $idx => $eid) {
            $angle = ($idx / count($this->portalEids)) * M_PI * 2 + $t * 0.4;
            $layer = ($idx < 6) ? 0 : 1;
            $r     = ($layer === 0 ? $outerR : $innerR) * $scale;
            $w     = cos($angle) * $r;
            $h     = sin($angle) * 1.6 * $scale;
            
            $bx = $cx + $rightX * $w;
            $by = $cy + $h;
            $bz = $cz + $rightZ * $w;
            
            $yawRot = $this->ticksRan * 15;
            $pitchRot = $this->ticksRan * 10;
            
            BlockEffects::sendMove($this->level, $eid, $bx, $by, $bz, $yawRot, $pitchRot);
        }

        $outerPts = 14;
        for ($i = 0; $i < $outerPts; $i++) {
            $a  = ($i / $outerPts) * M_PI * 2 + $t * 0.35;
            $w  = cos($a) * $outerR * $scale;
            $h  = sin($a) * 1.8 * $scale;
            $this->level->addParticle(new DustParticle(new Vector3($cx + $rightX * $w, $cy + $h, $cz + $rightZ * $w), 130, 0, 255));
        }

        $innerPts = 10;
        for ($i = 0; $i < $innerPts; $i++) {
            $a  = ($i / $innerPts) * M_PI * 2 - $t * 0.5;
            $w  = cos($a) * $innerR * $scale;
            $h  = sin($a) * 1.4 * $scale;
            $this->level->addParticle(new DustParticle(new Vector3($cx + $rightX * $w, $cy + $h, $cz + $rightZ * $w), 80, 0, 200));
        }

        $portalPts = 8;
        for ($i = 0; $i < $portalPts; $i++) {
            $a  = ($i / $portalPts) * M_PI * 2 + $t * 0.6;
            $w  = cos($a) * ($outerR * 0.6) * $scale;
            $h  = sin($a) * 1.2 * $scale;
            $this->level->addParticle(new PortalParticle(new Vector3($cx + $rightX * $w, $cy + $h, $cz + $rightZ * $w)));
        }

        if ($this->ticksRan % 3 === 0) {
            $a  = mt_rand(0, 628) / 100.0;
            $w  = cos($a) * (mt_rand(6, 14) / 10.0) * 0.6 * $scale;
            $h  = sin($a) * 1.5 * $scale;
            $this->level->addParticle(new EnchantmentTableParticle(new Vector3($cx + $rightX * $w, $cy + $h, $cz + $rightZ * $w)));
        }

        if ($this->ticksRan % 5 === 0) {
            $this->level->addParticle(new DustParticle(new Vector3($cx, $cy + 1.8 * $scale + sin($t) * 0.2, $cz), 160, 0, 255));
            $this->level->addParticle(new DustParticle(new Vector3($cx, $cy + 0.2, $cz), 160, 0, 255));
        }
    }

    private function cleanup() {
        if ($this->cleaned) return;
        $this->cleaned = true;
        BlockEffects::voidAndRemove($this->plugin, $this->level, $this->portalEids);
        $this->portalEids = [];
        $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
    }
}

class PortalMenuInventory extends BaseInventory {

    public $customName;
    public $pos;

    public function __construct(Player $player, $name = "World Warp") {
        $this->customName = $name;
        $this->pos        = Position::fromObject($player->add(0, 2), $player->level);
        $this->holder     = new FakeBlockMenu($this, $this->pos);
        parent::__construct($this->holder, InventoryType::get(InventoryType::CHEST));
    }

    public function sendChestState(Player $player, $open = true) {
        $pk         = new BlockEventPacket();
        $pk->x      = (int)$this->pos->x;
        $pk->y      = (int)$this->pos->y;
        $pk->z      = (int)$this->pos->z;
        $pk->case1  = 1;
        $pk->case2  = $open ? 1 : 0;
        $player->dataPacket($pk);
    }

    public function onOpen(Player $who) {
        if (!$who->isOnline()) return;

        $pk          = new UpdateBlockPacket();
        $pk->x       = $this->pos->x;
        $pk->y       = $this->pos->y;
        $pk->z       = $this->pos->z;
        $pk->blockId = 54;
        $pk->blockData = 0;
        $pk->flags   = UpdateBlockPacket::FLAG_ALL;
        $who->dataPacket($pk);

        $compound              = new CompoundTag();
        $compound->id          = new StringTag("id", "Chest");
        $compound->CustomName  = new StringTag("CustomName", $this->customName);
        $compound->x           = new IntTag("x", (int)$this->pos->x);
        $compound->y           = new IntTag("y", (int)$this->pos->y);
        $compound->z           = new IntTag("z", (int)$this->pos->z);

        $nbt = new NBT(NBT::LITTLE_ENDIAN);
        $nbt->setData($compound);

        $pk2            = new BlockEntityDataPacket();
        $pk2->x         = $this->pos->x;
        $pk2->y         = $this->pos->y;
        $pk2->z         = $this->pos->z;
        $pk2->namedtag  = $nbt->write();
        $who->dataPacket($pk2);

        parent::onOpen($who);

        $inv = $this;
        Server::getInstance()->getScheduler()->scheduleDelayedTask(
            new PortalMenuOpenTask($who, $inv),
            2
        );
    }

    public function onClose(Player $who) {
        if (!$who->isOnline()) return;
        $this->sendChestState($who, false);

        $pk            = new UpdateBlockPacket();
        $pk->x         = $this->pos->x;
        $pk->y         = $this->pos->y;
        $pk->z         = $this->pos->z;
        $pk->blockId   = $who->getLevel()->getBlockIdAt($this->pos->x, $this->pos->y, $this->pos->z);
        $pk->blockData = $who->getLevel()->getBlockDataAt($this->pos->x, $this->pos->y, $this->pos->z);
        $pk->flags     = UpdateBlockPacket::FLAG_ALL;
        $who->dataPacket($pk);

        parent::onClose($who);
    }
}

class PortalMenuOpenTask extends Task {
    private $player;
    private $inv;
    
    public function __construct(Player $p, $i) {
        $this->player = $p;
        $this->inv = $i;
    }
    
    public function onRun($t) {
        if (!$this->player->isOnline()) return;
        $this->inv->sendChestState($this->player, true);
        $pk            = new ContainerOpenPacket();
        $pk->windowid  = $this->player->getWindowId($this->inv);
        $pk->type      = $this->inv->getType()->getNetworkType();
        $pk->slots     = $this->inv->getSize();
        $pk->x         = $this->inv->pos->x;
        $pk->y         = $this->inv->pos->y;
        $pk->z         = $this->inv->pos->z;
        $this->player->dataPacket($pk);
        $this->inv->sendContents($this->player);
    }
}