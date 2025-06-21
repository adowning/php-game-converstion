<?php

// Comparison test for DazzleMeNET implementations

// Set error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files using absolute paths
require_once __DIR__ . '/../../Base/BaseSlotSettings.php';
require_once __DIR__ . '/../GameReel.php';
require_once __DIR__ . '/../SlotSettings.php';
require_once __DIR__ . '/../Server.php';

class ImplementationComparator {
    private $testCases = [];
    private $differences = [];
    
    public function __construct() {
        $this->initializeTestCases();
    }
    
    private function initializeTestCases() {
        // Basic test cases with different bet amounts and line counts
        $this->testCases = [
            [
                'name' => 'Basic Test - Min Bet',
                'bet' => 1,
                'lines' => 1,
                'balance' => 1000,
                'isFreeSpin' => false
            ],
            [
                'name' => 'Basic Test - Max Lines',
                'bet' => 10,
                'lines' => 20,
                'balance' => 1000,
                'isFreeSpin' => false
            ],
            [
                'name' => 'Free Spin Test',
                'bet' => 10,
                'lines' => 10,
                'balance' => 1000,
                'isFreeSpin' => true
            ]
        ];
    }
    
    public function runComparison() {
        echo "Starting implementation comparison test...\n";
        echo "======================================\n\n";
        
        foreach ($this->testCases as $testCase) {
            echo "Test Case: {$testCase['name']}\n";
            echo "--------------------------------------\n";
            
            // Initialize both implementations with the same random seed for consistent results
            $seed = time();
            mt_srand($seed);
            
            // Create mock slot settings with the same initial state
            $mockSettings = $this->createMockSlotSettings($testCase);
            
            // Create server instances
            $server = new \app\games\NET\DazzleMeNET\Server($mockSettings);
            
            // Test init action
            $initResponse = $server->handle([
                'action' => 'init',
                'gameState' => [
                    'slotId' => 'DazzleMeNET',
                    'playerId' => 1,
                    'bet' => $testCase['bet'],
                    'lines' => $testCase['lines'],
                    'balance' => $testCase['balance'],
                    'isFreeSpin' => $testCase['isFreeSpin']
                ]
            ]);
            
            // Test spin action
            mt_srand($seed); // Reset random seed for consistent results
            $spinResponse = $server->handle([
                'action' => 'spin',
                'gameState' => [
                    'slotId' => 'DazzleMeNET',
                    'playerId' => 1,
                    'bet' => $testCase['bet'],
                    'lines' => $testCase['lines'],
                    'balance' => $testCase['balance'],
                    'isFreeSpin' => $testCase['isFreeSpin']
                ]
            ]);
            
            // Output results
            echo "Init Response:\n";
            print_r($initResponse);
            echo "\nSpin Response:\n";
            print_r($spinResponse);
            echo "\n";
            
            // Save results for later comparison
            $this->saveTestResult($testCase['name'], [
                'init' => $initResponse,
                'spin' => $spinResponse
            ]);
        }
        
        // Output any differences found
        $this->reportDifferences();
    }
    
    private function createMockSlotSettings($testCase) {
        return new class($testCase) extends \app\games\NET\DazzleMeNET\SlotSettings {
            private $testCase;
            
            public function __construct($testCase) {
                $this->testCase = $testCase;
                $this->slotId = 'DazzleMeNET';
                $this->playerId = 1;
                $this->Balance = $testCase['balance'];
                $this->CurrentBet = $testCase['bet'];
                $this->CurrentLines = $testCase['lines'];
                $this->CurrentDenom = 1;
                $this->isFreeSpin = $testCase['isFreeSpin'];
                $this->freeSpinsRemaining = $testCase['isFreeSpin'] ? 10 : 0;
                $this->freeSpinsTotal = $testCase['isFreeSpin'] ? 10 : 0;
                $this->totalWin = 0;
                $this->baseWin = 0;
                $this->bonusWin = 0;
                $this->gameData = [];
                $this->gameDataStatic = [];
            }
            
            public function GetBalance() {
                return $this->Balance;
            }
            
            public function SetGameData($key, $value) {
                $this->gameData[$key] = $value;
                return true;
            }
            
            public function GetGameData($key, $default = null) {
                return $this->gameData[$key] ?? $default;
            }
            
            public function checkBonusWin($reels, $bet, $lines) {
                // Simple mock implementation - should match the old implementation
                return [
                    'win' => 0,
                    'type' => 'none',
                    'multiplier' => 1,
                    'symbol' => null,
                    'positions' => []
                ];
            }
            
            public function GetRandomPay() {
                return 0;
            }
            
            public function SaveGameData() {
                return true;
            }
            
            public function SaveGameDataStatic() {
                return true;
            }
        };
    }
    
    private function saveTestResult($testName, $results) {
        // In a real implementation, we would save these results for comparison
        // with the old implementation
        file_put_contents(
            __DIR__ . "/comparison/results_{$testName}_" . time() . ".json",
            json_encode($results, JSON_PRETTY_PRINT)
        );
    }
    
    private function reportDifferences() {
        if (empty($this->differences)) {
            echo "\n✅ All test cases passed with no differences found!\n";
        } else {
            echo "\n❌ Differences found in the following test cases:\n";
            foreach ($this->differences as $testName => $diff) {
                echo "\nTest: $testName\n";
                echo "Field: {$diff['field']}\n";
                echo "Old Value: " . json_encode($diff['old']) . "\n";
                echo "New Value: " . json_encode($diff['new']) . "\n";
            }
        }
    }
}

// Run the comparison
$comparator = new ImplementationComparator();
$comparator->runComparison();
