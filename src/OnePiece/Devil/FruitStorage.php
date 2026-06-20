<?php

namespace OnePiece\Devil;

class FruitStorage {

    /** @var Main */
    private $plugin;

    /** @var string */
    private $dataPath;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->dataPath = $plugin->getDataFolder() . "players/";
        @mkdir($this->dataPath);
    }

    private function getPlayerFile($playerName) {
        return $this->dataPath . strtolower($playerName) . ".json";
    }

    /**
     * Load player fruit data
     * @return array|null
     */
    public function loadPlayerFruit($playerName) {
        $file = $this->getPlayerFile($playerName);

        if (!file_exists($file)) {
            return null;
        }

        $content = file_get_contents($file);
        $data = json_decode($content, true);

        if (!is_array($data)) {
            return null;
        }

        return $data;
    }

    /**
     * Save player fruit data
     */
    public function savePlayerFruit($playerName, array $data) {
        $file = $this->getPlayerFile($playerName);
        $content = json_encode($data, JSON_PRETTY_PRINT);
        file_put_contents($file, $content);
    }

    /**
     * Delete player fruit data
     */
    public function deletePlayerFruit($playerName) {
        $file = $this->getPlayerFile($playerName);
        if (file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * Check if player has saved fruit data
     */
    public function playerHasFruit($playerName) {
        return file_exists($this->getPlayerFile($playerName));
    }
}