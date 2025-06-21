<?php

// Simple test script to verify class loading

echo "=== DazzleMeNET Simple Test ===\n\n";

// Manually include the required files
require_once __DIR__ . '/GameReel.php';
require_once __DIR__ . '/Server.php';
require_once __DIR__ . '/SlotSettings.php';

// Test GameReel
echo "Testing GameReel...\n";

try {
    $gameReel = new app\games\NET\DazzleMeNET\GameReel();
    $reels = $gameReel->spin();
    
    echo "Spin result:\n";
    foreach ($reels as $reel => $symbols) {
        echo "$reel: " . implode(', ', $symbols) . "\n";
    }
    
    // Test Server
    echo "\nTesting Server initialization...\n";
    
    $server = new app\games\NET\DazzleMeNET\Server();
    $response = $server->handle([
        'action' => 'init',
        'gameState' => []
    ]);
    
    echo "Server response:\n";
    print_r($response);
    
    echo "\n✅ Tests completed successfully!\n";
    
} catch (\Exception $e) {
    echo "\n❌ Test failed: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " (Line: " . $e->getLine() . ")\n";
    if ($e->getPrevious()) {
        echo "Previous: " . $e->getPrevious()->getMessage() . "\n";
    }
    exit(1);
}
