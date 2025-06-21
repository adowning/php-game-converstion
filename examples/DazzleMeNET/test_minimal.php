<?php

// Minimal test script to verify basic functionality

echo "=== DazzleMeNET Minimal Test ===\n\n";

// Set include path
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ . '/..');

// Manually include the required files
require_once __DIR__ . '/GameReel.php';

// Test GameReel
echo "Testing GameReel...\n";

try {
    $gameReel = new \app\games\NET\DazzleMeNET\GameReel();
    $reels = $gameReel->spin();
    
    echo "Spin result:\n";
    foreach ($reels as $reel => $symbols) {
        echo "$reel: " . implode(', ', $symbols) . "\n";
    }
    
    echo "\n✅ GameReel test passed!\n";
    
} catch (\Exception $e) {
    echo "\n❌ Test failed: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " (Line: " . $e->getLine() . ")\n";
    if ($e->getPrevious()) {
        echo "Previous: " . $e->getPrevious()->getMessage() . "\n";
    }
    exit(1);
}
