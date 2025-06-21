<?php

// Simple test script for DazzleMeNET

echo "=== DazzleMeNET Test Script ===\n\n";

// Test GameReel
function testGameReel() {
    echo "Testing GameReel...\n";
    
    try {
        $gameReel = new \app\games\NET\DazzleMeNET\GameReel();
        $reels = $gameReel->spin();
        
        if (!is_array($reels)) {
            throw new \Exception('spin() did not return an array');
        }
        
        $requiredKeys = ['reel1', 'reel2', 'reel3', 'reel4', 'reel5'];
        foreach ($requiredKeys as $key) {
            if (!isset($reels[$key])) {
                throw new \Exception("Missing reel: $key");
            }
            echo "- $key: " . implode(', ', $reels[$key]) . "\n";
        }
        
        echo "GameReel test passed!\n";
        return true;
    } catch (\Exception $e) {
        echo "GameReel test failed: " . $e->getMessage() . "\n";
        return false;
    }
}

// Test Server initialization
function testServerInit() {
    echo "\nTesting Server initialization...\n";
    
    try {
        $server = new \app\games\NET\DazzleMeNET\Server();
        $response = $server->handle([
            'action' => 'init',
            'gameState' => []
        ]);
        
        if (!is_array($response)) {
            throw new \Exception('Server did not return an array');
        }
        
        $requiredKeys = ['responseEvent', 'responseType', 'serverResponse'];
        foreach ($requiredKeys as $key) {
            if (!isset($response[$key])) {
                throw new \Exception("Missing response key: $key");
            }
        }
        
        $data = json_decode($response['serverResponse'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON in server response');
        }
        
        $requiredDataKeys = ['balance', 'bet', 'lines'];
        foreach ($requiredDataKeys as $key) {
            if (!isset($data[$key])) {
                throw new \Exception("Missing data key in response: $key");
            }
        }
        
        echo "Server initialization test passed!\n";
        echo "Balance: " . $data['balance'] . "\n";
        echo "Bet: " . $data['bet'] . "\n";
        echo "Lines: " . $data['lines'] . "\n";
        
        return true;
    } catch (\Exception $e) {
        echo "Server initialization test failed: " . $e->getMessage() . "\n";
        if (isset($e->getTrace()[0])) {
            $trace = $e->getTrace()[0];
            echo "  in " . ($trace['file'] ?? 'unknown') . " on line " . ($trace['line'] ?? 'unknown') . "\n";
        }
        return false;
    }
}

// Run tests
$allTestsPassed = true;

// Test GameReel
if (!testGameReel()) {
    $allTestsPassed = false;
}

// Test Server initialization
if (!testServerInit()) {
    $allTestsPassed = false;
}

// Output final result
echo "\n=== Test Results ===\n";
if ($allTestsPassed) {
    echo "✅ All tests passed!\n";
} else {
    echo "❌ Some tests failed. Please check the output above for details.\n";
    exit(1);
}
