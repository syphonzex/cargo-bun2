<?php
namespace OnePiece\Devil;

use pocketmine\entity\Entity;
use pocketmine\level\Level;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\network\protocol\MoveEntityPacket;
use pocketmine\Player;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;

class BlockEffectEntity extends Entity {

    const NETWORK_ID = 66;

    public $width = 0.98;
    public $height = 0.98;
    public $gravity = 0;
    public $drag = 0;
    public $canCollide = false;

    public $blockId = 1;
    public $blockDamage = 0;

    public static function create(Level $level, $x, $y, $z, $blockId = 1, $blockDamage = 0) {
        $chunk = $level->getChunk($x >> 4, $z >> 4, true);
        if ($chunk === null) return null;

        $nbt = new CompoundTag("", [
            "Pos" => new ListTag("Pos", [
                new DoubleTag("", (float)$x),
                new DoubleTag("", (float)$y),
                new DoubleTag("", (float)$z)
            ]),
            "Motion" => new ListTag("Motion", [
                new DoubleTag("", 0.0),
                new DoubleTag("", 0.0),
                new DoubleTag("", 0.0)
            ]),
            "Rotation" => new ListTag("Rotation", [
                new FloatTag("", 0.0),
                new FloatTag("", 0.0)
            ])
        ]);
        $nbt->BlockID = new IntTag("BlockID", (int)$blockId | ((int)$blockDamage << 8));

        $entity = new BlockEffectEntity($chunk, $nbt);
        $entity->blockId = (int)$blockId;
        $entity->blockDamage = (int)$blockDamage;
        return $entity;
    }

    protected function initEntity() {
        parent::initEntity();
        $this->setMaxHealth(1);
        $this->setHealth(1);
    }

    public function spawnTo(Player $player) {
        $pk = new AddEntityPacket();
        $pk->eid = $this->getId();
        $pk->type = self::NETWORK_ID;
        $pk->x = $this->x;
        $pk->y = $this->y;
        $pk->z = $this->z;
        $pk->yaw = $this->yaw;
        $pk->pitch = $this->pitch;
        $pk->metadata = [
            15 => [0, 1],
            23 => [7, -1],
            24 => [0, 0]
        ];
        if (isset($this->namedtag->BlockID)) {
            $pk->metadata[20] = [2, $this->namedtag->BlockID->getValue()];
        } else {
            $pk->metadata[20] = [2, $this->blockId | ($this->blockDamage << 8)];
        }
        $player->dataPacket($pk);
        parent::spawnTo($player);
    }

    public function updateMovement() {
        if ($this->closed) return;
        $pk = new MoveEntityPacket();
        $pk->eid = $this->getId();
        $pk->x = $this->x;
        $pk->y = $this->y;
        $pk->z = $this->z;
        $pk->yaw = $this->yaw;
        $pk->headYaw = $this->yaw;
        $pk->pitch = $this->pitch;
        foreach ($this->hasSpawned as $player) {
            if ($player instanceof Player && $player->isOnline()) {
                $player->dataPacket($pk);
            }
        }
    }

    public function attack($damage, EntityDamageEvent $source) {
        $source->setCancelled(true);
    }

    public function onUpdate($currentTick) {
        return false;
    }

    public function canCollideWith(Entity $entity) {
        return false;
    }

    public function canBeCollidedWith() {
        return false;
    }

    public function saveNBT() {
    }

    public function getName() {
        return "BlockEffect";
    }

    public function entityBaseTick($tickDiff = 1) {
        return false;
    }
}