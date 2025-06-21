<?php

// Simple test script for the Server class

echo "=== DazzleMeNET Server Test ===\n\n";

// Set error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define base path
define('BASE_PATH', dirname(__DIR__, 2)); // Go up 2 levels from DazzleMeNET to reach the app/games directory

// Manually include the required files
require_once BASE_PATH . '/NET/Base/BaseSlotSettings.php';
require_once __DIR__ . '/GameReel.php';
require_once __DIR__ . '/SlotSettings.php';
require_once __DIR__ . '/Server.php';

// Check if all required classes exist
if (!class_exists('app\\games\\NET\\Base\\BaseSlotSettings')) {
    die("Error: BaseSlotSettings class not found\n");
}
if (!class_exists('app\\games\\NET\\DazzleMeNET\\SlotSettings')) {
    die("Error: SlotSettings class not found\n");
}
if (!class_exists('app\\games\\NET\\DazzleMeNET\\GameReel')) {
    die("Error: GameReel class not found\n");
}
if (!class_exists('app\\games\\NET\\DazzleMeNET\\Server')) {
    die("Error: Server class not found\n");
}

// Test Server
echo "Testing Server initialization...\n";

try {
    // Mock the required dependencies for testing
    $slotSettings = new class('DazzleMeNET', 1) extends \app\games\NET\DazzleMeNET\SlotSettings {
        public function __construct($sid, $playerId) {
            // Skip parent constructor to avoid database dependencies
            $this->slotId = $sid;
            $this->playerId = $playerId;
            $this->Balance = 1000; // Set a default balance for testing
            $this->CurrentBet = 10;
            $this->CurrentLines = 20;
            $this->CurrentDenom = 1;
            $this->isFreeSpin = false;
            $this->freeSpinsRemaining = 0;
            $this->freeSpinsTotal = 0;
            $this->totalWin = 0;
            $this->baseWin = 0;
            $this->bonusWin = 0;
            $this->gameData = [];
            $this->gameDataStatic = [];
        }
        
        // Mock the GetBalance method
        public function GetBalance() {
            return $this->Balance;
        }
        
        // Mock the SetGameData method
        public function SetGameData($key, $value) {
            $this->gameData[$key] = $value;
            return true;
        }
        
        // Mock the GetGameData method
        public function GetGameData($key, $default = null) {
            return $this->gameData[$key] ?? $default;
        }
        
        // Mock the checkBonusWin method
        public function checkBonusWin($reels, $bet, $lines) {
            // Simple mock implementation
            return [
                'win' => 0,
                'type' => 'none',
                'multiplier' => 1,
                'symbol' => null,
                'positions' => []
            ];
        }
        
        // Mock the GetRandomPay method
        public function GetRandomPay() {
            return 0;
        }
    };
    
    // Create server with mocked slot settings
    $server = new \app\games\NET\DazzleMeNET\Server($slotSettings);
    
    // Test init action
    echo "\nTesting init action...\n";
    $response = $server->handle([
        'action' => 'init',
        'gameState' => [
            'slotId' => 'DazzleMeNET',
            'playerId' => 1
        ]
    ]);
    
    echo "Init response:\n";
    print_r($response);
    
    // Test spin action
    if (isset($response['serverResponse'])) {
        $data = json_decode($response['serverResponse'], true);
        if ($data && isset($data['balance'])) {
            echo "\nTesting spin action...\n";
            $response = $server->handle([
                'action' => 'spin',
                'postData' => [
                    'bet' => $data['bet'],
                    'lines' => $data['lines']
                ],
                'gameState' => []
            ]);
            
            echo "Spin response:\n";
            print_r($response);
        }
    }
    
    echo "\n✅ Server tests completed successfully!\n";
    
} catch (\Exception $e) {
    echo "\n❌ Test failed: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " (Line: " . $e->getLine() . ")\n";
    if ($e->getPrevious()) {
        echo "Previous: " . $e->getPrevious()->getMessage() . "\n";
    }
    exit(1);
}
