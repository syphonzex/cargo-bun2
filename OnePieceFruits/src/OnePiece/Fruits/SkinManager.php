<?php

namespace OnePiece\Fruits;

use pocketmine\Player;
use pocketmine\Server;
use pocketmine\network\protocol\PlayerListPacket;

class SkinManager {

    private $plugin;
    private $skinsPath;
    private $originalSkins = [];
    private $zoanSkins = [];
    private $transformedPlayers = [];

    const ZOAN_SKIN_FILES = [
        "inu_inu"          => "wolf.png",
        "neko_neko"        => "leopard.png",
        "tori_tori"        => "phoenix.png",
        "uo_uo"            => "dragon.png",
        "tori_tori_falcon" => "falcon.png",
        "zou_zou"          => "mammoth.png",
        "trex_trex"        => "trex.png",
        "gear_fourth"      => "gear4.png",
        "okuchi_okuchi"      => "okuchi.png"
    ];

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->skinsPath = $plugin->getDataFolder() . "skins/";
        @mkdir($this->skinsPath);
        
        $this->loadZoanSkins();
    }

    private function loadZoanSkins() {
        foreach (self::ZOAN_SKIN_FILES as $fruitId => $fileName) {
            $filePath = $this->skinsPath . $fileName;
            
            if (!file_exists($filePath)) {
                $this->plugin->getLogger()->warning("Zoan skin not found: " . $fileName . " - Creating default");
                $this->createSteveSkin($filePath, $fruitId);
            }

            $skinData = $this->loadSkinFromPng($filePath);
            if ($skinData !== null) {
                $this->zoanSkins[$fruitId] = $skinData;
                $this->plugin->getLogger()->info("Loaded Zoan skin: " . $fileName);
            } else {
                $this->zoanSkins[$fruitId] = $this->generateSteveSkinData($fruitId);
                $this->plugin->getLogger()->info("Generated fallback skin for: " . $fruitId);
            }
        }
    }

    private function getZoanColors($fruitId) {
        switch ($fruitId) {
            case "inu_inu":
                return [
                    'main' => [130, 130, 140],
                    'second' => [180, 180, 190],
                    'accent' => [60, 60, 70],
                    'dark' => [40, 40, 50]
                ];
            case "neko_neko":
                return [
                    'main' => [210, 170, 70],
                    'second' => [240, 200, 100],
                    'accent' => [80, 50, 20],
                    'dark' => [50, 30, 10]
                ];
            case "tori_tori":
                return [
                    'main'   => [30, 80, 180],
                    'second' => [50, 120, 220],
                    'accent' => [255, 200, 50],
                    'dark'   => [20, 50, 120]
                ];
            case "tori_tori_falcon":
                return [
                    'main'   => [139, 90, 43],
                    'second' => [200, 150, 80],
                    'accent' => [255, 220, 100],
                    'dark'   => [80, 50, 20]
                ];
            case "uo_uo":
                return [
                    'main'   => [160, 30, 30],
                    'second' => [200, 50, 50],
                    'accent' => [255, 150, 50],
                    'dark'   => [40, 20, 20]
                ];
            case "trex_trex":
            case "zou_zou":
                return [
                    'main'   => [120, 110, 100],
                    'second' => [160, 150, 140],
                    'accent' => [80, 70, 60],
                    'dark'   => [50, 45, 40]
                ];
            case "trex_trex":
                return [
                    'main'   => [0,   60,  20],
                    'second' => [0,   120, 50],
                    'accent' => [0,   255, 80],
                    'dark'   => [0,   30,  10]
                ];
            case "gear_fourth":
                return [
                    'main'   => [160, 20,  20],
                    'second' => [210, 60,  60],
                    'accent' => [255, 200, 0],
                    'dark'   => [80,  0,   0]
                ];
            default:
                return [
                    'main' => [100, 100, 100],
                    'second' => [150, 150, 150],
                    'accent' => [200, 200, 200],
                    'dark' => [50, 50, 50]
                ];
        }
    }

    private function createSteveSkin($filePath, $fruitId) {
        $img = imagecreatetruecolor(64, 32);
        
        imagesavealpha($img, true);
        imagealphablending($img, false);
        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagefill($img, 0, 0, $transparent);
        imagealphablending($img, true);

        $colors = $this->getZoanColors($fruitId);
        
        $mainColor = imagecolorallocate($img, $colors['main'][0], $colors['main'][1], $colors['main'][2]);
        $secondColor = imagecolorallocate($img, $colors['second'][0], $colors['second'][1], $colors['second'][2]);
        $accentColor = imagecolorallocate($img, $colors['accent'][0], $colors['accent'][1], $colors['accent'][2]);
        $darkColor = imagecolorallocate($img, $colors['dark'][0], $colors['dark'][1], $colors['dark'][2]);
        $eyeWhite = imagecolorallocate($img, 255, 255, 255);
        $eyePupil = imagecolorallocate($img, 20, 20, 20);

        imagefilledrectangle($img, 8, 0, 15, 7, $mainColor);
        imagefilledrectangle($img, 16, 0, 23, 7, $darkColor);
        imagefilledrectangle($img, 0, 8, 7, 15, $mainColor);
        imagefilledrectangle($img, 8, 8, 15, 15, $mainColor);
        imagefilledrectangle($img, 16, 8, 23, 15, $mainColor);
        imagefilledrectangle($img, 24, 8, 31, 15, $secondColor);
        
        imagesetpixel($img, 10, 11, $eyeWhite);
        imagesetpixel($img, 10, 12, $eyePupil);
        imagesetpixel($img, 13, 11, $eyeWhite);
        imagesetpixel($img, 13, 12, $eyePupil);
        imagesetpixel($img, 11, 13, $accentColor);
        imagesetpixel($img, 12, 13, $accentColor);
        imagesetpixel($img, 11, 14, $darkColor);
        imagesetpixel($img, 12, 14, $darkColor);
        
        imagefilledrectangle($img, 1, 8, 2, 10, $accentColor);
        imagefilledrectangle($img, 21, 8, 22, 10, $accentColor);

        imagefilledrectangle($img, 20, 16, 27, 19, $mainColor);
        imagefilledrectangle($img, 28, 16, 35, 19, $darkColor);
        imagefilledrectangle($img, 16, 20, 19, 31, $mainColor);
        imagefilledrectangle($img, 20, 20, 27, 31, $mainColor);
        imagefilledrectangle($img, 28, 20, 31, 31, $mainColor);
        imagefilledrectangle($img, 32, 20, 39, 31, $secondColor);
        
        imagefilledrectangle($img, 22, 22, 25, 28, $secondColor);

        imagefilledrectangle($img, 4, 16, 7, 19, $mainColor);
        imagefilledrectangle($img, 8, 16, 11, 19, $darkColor);
        imagefilledrectangle($img, 0, 20, 3, 31, $mainColor);
        imagefilledrectangle($img, 4, 20, 7, 31, $mainColor);
        imagefilledrectangle($img, 8, 20, 11, 31, $mainColor);
        imagefilledrectangle($img, 12, 20, 15, 31, $secondColor);
        
        imagefilledrectangle($img, 0, 29, 15, 31, $accentColor);

        imagefilledrectangle($img, 44, 16, 47, 19, $mainColor);
        imagefilledrectangle($img, 48, 16, 51, 19, $darkColor);
        imagefilledrectangle($img, 40, 20, 43, 31, $mainColor);
        imagefilledrectangle($img, 44, 20, 47, 31, $mainColor);
        imagefilledrectangle($img, 48, 20, 51, 31, $mainColor);
        imagefilledrectangle($img, 52, 20, 55, 31, $secondColor);
        
        imagefilledrectangle($img, 40, 28, 55, 31, $accentColor);

        imagepng($img, $filePath);
        imagedestroy($img);
        
        $this->plugin->getLogger()->info("Created Steve skin: " . basename($filePath));
    }

    private function generateSteveSkinData($fruitId) {
        $img = imagecreatetruecolor(64, 32);
        
        imagesavealpha($img, true);
        imagealphablending($img, false);
        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagefill($img, 0, 0, $transparent);
        imagealphablending($img, true);

        $colors = $this->getZoanColors($fruitId);
        
        $mainColor = imagecolorallocate($img, $colors['main'][0], $colors['main'][1], $colors['main'][2]);
        $secondColor = imagecolorallocate($img, $colors['second'][0], $colors['second'][1], $colors['second'][2]);
        $accentColor = imagecolorallocate($img, $colors['accent'][0], $colors['accent'][1], $colors['accent'][2]);
        $darkColor = imagecolorallocate($img, $colors['dark'][0], $colors['dark'][1], $colors['dark'][2]);
        $eyeWhite = imagecolorallocate($img, 255, 255, 255);
        $eyePupil = imagecolorallocate($img, 20, 20, 20);

        imagefilledrectangle($img, 8, 0, 15, 7, $mainColor);
        imagefilledrectangle($img, 16, 0, 23, 7, $darkColor);
        imagefilledrectangle($img, 0, 8, 7, 15, $mainColor);
        imagefilledrectangle($img, 8, 8, 15, 15, $mainColor);
        imagefilledrectangle($img, 16, 8, 23, 15, $mainColor);
        imagefilledrectangle($img, 24, 8, 31, 15, $secondColor);
        
        imagesetpixel($img, 10, 11, $eyeWhite);
        imagesetpixel($img, 10, 12, $eyePupil);
        imagesetpixel($img, 13, 11, $eyeWhite);
        imagesetpixel($img, 13, 12, $eyePupil);
        imagesetpixel($img, 11, 13, $accentColor);
        imagesetpixel($img, 12, 13, $accentColor);

        imagefilledrectangle($img, 20, 16, 27, 19, $mainColor);
        imagefilledrectangle($img, 28, 16, 35, 19, $darkColor);
        imagefilledrectangle($img, 16, 20, 19, 31, $mainColor);
        imagefilledrectangle($img, 20, 20, 27, 31, $mainColor);
        imagefilledrectangle($img, 28, 20, 31, 31, $mainColor);
        imagefilledrectangle($img, 32, 20, 39, 31, $secondColor);
        imagefilledrectangle($img, 22, 22, 25, 28, $secondColor);

        imagefilledrectangle($img, 4, 16, 7, 19, $mainColor);
        imagefilledrectangle($img, 8, 16, 11, 19, $darkColor);
        imagefilledrectangle($img, 0, 20, 3, 31, $mainColor);
        imagefilledrectangle($img, 4, 20, 7, 31, $mainColor);
        imagefilledrectangle($img, 8, 20, 11, 31, $mainColor);
        imagefilledrectangle($img, 12, 20, 15, 31, $secondColor);
        imagefilledrectangle($img, 0, 29, 15, 31, $accentColor);

        imagefilledrectangle($img, 44, 16, 47, 19, $mainColor);
        imagefilledrectangle($img, 48, 16, 51, 19, $darkColor);
        imagefilledrectangle($img, 40, 20, 43, 31, $mainColor);
        imagefilledrectangle($img, 44, 20, 47, 31, $mainColor);
        imagefilledrectangle($img, 48, 20, 51, 31, $mainColor);
        imagefilledrectangle($img, 52, 20, 55, 31, $secondColor);
        imagefilledrectangle($img, 40, 28, 55, 31, $accentColor);

        $skinData = "";
        for ($y = 0; $y < 32; $y++) {
            for ($x = 0; $x < 64; $x++) {
                $rgba = imagecolorat($img, $x, $y);
                $a = (127 - (($rgba >> 24) & 0x7F)) * 2;
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                $skinData .= chr($r) . chr($g) . chr($b) . chr($a);
            }
        }

        imagedestroy($img);
        return $skinData;
    }

    private function loadSkinFromPng($filePath) {
        if (!file_exists($filePath)) {
            return null;
        }

        $img = @imagecreatefrompng($filePath);
        if ($img === false) {
            $this->plugin->getLogger()->error("Failed to load PNG: " . $filePath);
            return null;
        }

        $width = imagesx($img);
        $height = imagesy($img);

        $validSizes = [[64, 32], [64, 64], [128, 128]];
        $valid = false;
        foreach ($validSizes as $size) {
            if ($width === $size[0] && $height === $size[1]) {
                $valid = true;
                break;
            }
        }

        if (!$valid) {
            $this->plugin->getLogger()->error("Invalid skin size: " . $width . "x" . $height);
            imagedestroy($img);
            return null;
        }

        $skinData = "";
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($img, $x, $y);
                $a = (127 - (($rgba >> 24) & 0x7F)) * 2;
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                $skinData .= chr($r) . chr($g) . chr($b) . chr($a);
            }
        }

        imagedestroy($img);
        return $skinData;
    }

    public function saveOriginalSkin(Player $player) {
        $name = $player->getName();
        
        if (isset($this->originalSkins[$name])) {
            return;
        }

        $this->originalSkins[$name] = [
            "data" => $player->getSkinData(),
            "id" => $player->getSkinId()
        ];
    }

    public function applyZoanSkin(Player $player, $fruitId) {
        $name = $player->getName();

        if (!isset($this->zoanSkins[$fruitId])) {
            $this->plugin->getLogger()->warning("No skin for: " . $fruitId);
            return false;
        }

        $this->saveOriginalSkin($player);

        $skinData = $this->zoanSkins[$fruitId];
        $skinId = "Standard_Custom";

        $this->transformedPlayers[$name] = $fruitId;

        $this->changeSkinAndRefresh($player, $skinData, $skinId);

        return true;
    }

    public function restoreOriginalSkin(Player $player) {
        $name = $player->getName();

        if (!isset($this->originalSkins[$name])) {
            return false;
        }

        $original = $this->originalSkins[$name];
        
        unset($this->transformedPlayers[$name]);

        $this->changeSkinAndRefresh($player, $original["data"], $original["id"]);

        unset($this->originalSkins[$name]);

        return true;
    }

    private function changeSkinAndRefresh(Player $player, $skinData, $skinId) {
        $player->setSkin($skinData, $skinId);

        $viewers = $player->getViewers();

        foreach ($viewers as $viewer) {
            $player->despawnFrom($viewer);
            $this->sendPlayerListRemove($player, $viewer);
        }

        $this->plugin->getServer()->getScheduler()->scheduleDelayedTask(
            new SkinRefreshTask($this->plugin, $player->getName()),
            5
        );
    }

    private function sendPlayerListRemove(Player $target, Player $viewer) {
        $pk = new PlayerListPacket();
        $pk->type = PlayerListPacket::TYPE_REMOVE;
        $pk->entries[] = [$target->getUniqueId()];
        $viewer->dataPacket($pk);
    }

    private function sendPlayerListAdd(Player $target, Player $viewer) {
        $pk = new PlayerListPacket();
        $pk->type = PlayerListPacket::TYPE_ADD;
        $pk->entries[] = [
            $target->getUniqueId(),
            $target->getId(),
            $target->getDisplayName(),
            $target->getSkinId(),
            $target->getSkinData()
        ];
        $viewer->dataPacket($pk);
    }

    public function respawnToViewers($playerName) {
        $player = $this->plugin->getServer()->getPlayerExact($playerName);
        if ($player === null || !$player->isOnline()) {
            return;
        }

        foreach ($this->plugin->getServer()->getOnlinePlayers() as $viewer) {
            if ($viewer === $player) {
                continue;
            }
            
            if ($viewer->getLevel() !== $player->getLevel()) {
                continue;
            }

            $this->sendPlayerListAdd($player, $viewer);
        }

        $this->plugin->getServer()->getScheduler()->scheduleDelayedTask(
            new SkinSpawnTask($this->plugin, $playerName),
            5
        );
    }

    public function spawnToViewers($playerName) {
        $player = $this->plugin->getServer()->getPlayerExact($playerName);
        if ($player === null || !$player->isOnline()) {
            return;
        }

        foreach ($this->plugin->getServer()->getOnlinePlayers() as $viewer) {
            if ($viewer === $player) {
                continue;
            }

            if ($viewer->getLevel() !== $player->getLevel()) {
                continue;
            }

            $player->spawnTo($viewer);
        }
    }

    public function hasOriginalSkin(Player $player) {
        return isset($this->originalSkins[$player->getName()]);
    }

    public function hasZoanSkin($fruitId) {
        return isset($this->zoanSkins[$fruitId]);
    }

    public function isTransformed(Player $player) {
        return isset($this->transformedPlayers[$player->getName()]);
    }

    public function cleanup(Player $player) {
        $name = $player->getName();
        unset($this->originalSkins[$name]);
        unset($this->transformedPlayers[$name]);
    }
}