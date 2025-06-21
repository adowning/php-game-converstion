<?php

namespace app\games\NET\DazzleMeNET\tests;

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../Server.php';
require_once __DIR__ . '/../GameReel.php';
require_once __DIR__ . '/../SlotSettings.php';

use app\games\NET\DazzleMeNET\Server;
use app\games\NET\DazzleMeNET\GameReel;
use app\games\NET\DazzleMeNET\SlotSettings;

class ServerTest
{
    private $server;
    private $slotSettings;

    public function __construct()
    {
        $this->server = new Server();
        $this->slotSettings = new SlotSettings([]);
    }

    public function testInit()
    {
        echo "Testing init...\n";
        $response = $this->server->handle([
            'action' => 'init',
            'gameState' => []
        ]);
        
        $this->assertArrayHasKey('responseEvent', $response, 'Response should have responseEvent');
        $this->assertArrayHasKey('responseType', $response, 'Response should have responseType');
        $this->assertArrayHasKey('serverResponse', $response, 'Response should have serverResponse');
        
        $data = json_decode($response['serverResponse'], true);
        $this->assertIsArray($data, 'serverResponse should be valid JSON');
        $this->assertArrayHasKey('balance', $data, 'Response should have balance');
        $this->assertArrayHasKey('bet', $data, 'Response should have bet');
        $this->assertArrayHasKey('lines', $data, 'Response should have lines');
        
        echo "Init test passed!\n";
    }

    public function testGameReel()
    {
        echo "Testing GameReel...\n";
        $gameReel = new GameReel();
        $reels = $gameReel->spin();
        
        $this->assertIsArray($reels, 'spin() should return an array');
        $this->assertArrayHasKey('reel1', $reels, 'Reels should have reel1');
        $this->assertArrayHasKey('reel2', $reels, 'Reels should have reel2');
        $this->assertArrayHasKey('reel3', $reels, 'Reels should have reel3');
        $this->assertArrayHasKey('reel4', $reels, 'Reels should have reel4');
        $this->assertArrayHasKey('reel5', $reels, 'Reels should have reel5');
        
        echo "GameReel test passed!\n";
    }

    private function assertArrayHasKey($key, $array, $message = '')
    {
        if (!array_key_exists($key, $array)) {
            throw new \Exception($message ?: "Array does not have key: $key");
        }
    }

    private function assertIsArray($value, $message = '')
    {
        if (!is_array($value)) {
            throw new \Exception($message ?: "Value is not an array");
        }
    }
}

// Run tests
try {
    echo "Starting DazzleMeNET Tests...\n";
    echo "----------------------------\n";
    
    $test = new ServerTest();
    
    // Test initialization
    echo "\n[TEST] Testing initialization...\n";
    $test->testInit();
    
    // Test game reels
    echo "\n[TEST] Testing game reels...\n";
    $test->testGameReel();
    
    echo "\n----------------------------\n";
    echo "All tests passed!\n";
} catch (\Exception $e) {
    echo "\n[ERROR] Test failed: " . $e->getMessage() . "\n";
    if ($e->getPrevious()) {
        echo "Previous exception: " . $e->getPrevious()->getMessage() . "\n";
    }
    exit(1);
}
