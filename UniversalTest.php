<?php

// A Universal Test Runner for any converted game.
// It MUST be located in the project root directory.
// Usage from project root: php UniversalTest.php <GameName>

error_reporting(E_ALL);
ini_set('display_errors', 1);

class UniversalTestRunner
{
    private $gameName;
    private $server;
    private $tests_passed = 0;
    private $tests_failed = 0;

    public function __construct($gameName)
    {
        if (empty($gameName)) {
            $this->bail("ERROR: Game name is required. Usage: php UniversalTest.php <GameName>");
        }
        $this->gameName = $gameName;

        $this->loadGameFiles();
        
        $serverClass = "app\\games\\NET\\{$this->gameName}\\Server";
        if (!class_exists($serverClass)) {
            $this->bail("ERROR: Server class '{$serverClass}' not found. Check the namespace in the generated Server.php file.");
        }
        $this->server = new $serverClass();
    }

    private function loadGameFiles()
    {
        $projectRoot = __DIR__;

        $baseDir = "{$projectRoot}/examples/Base";
        $gameDir = "{$projectRoot}/examples/{$this->gameName}";

        // Define all necessary base files
        $baseFiles = ['BaseSlotSettings.php', 'WinCalculator.php']; // <-- ADDED WinCalculator.php HERE
        
        foreach($baseFiles as $file) {
            $path = "{$baseDir}/{$file}";
            if (!file_exists($path)) {
                $this->bail("FATAL ERROR: A required base file was not found. Script expected it at: {$path}");
            }
            require_once $path;
        }

        // Load the game-specific files
        $gameFiles = ['GameReel.php', 'SlotSettings.php', 'Server.php'];
        foreach ($gameFiles as $file) {
            $path = "{$gameDir}/{$file}";
            if (!file_exists($path)) {
                $this->bail("FATAL ERROR: A required game file was not found for '{$this->gameName}'. Script expected it at: {$path}");
            }
            require_once $path;
        }
    }

    public function run()
    {
        echo "==================================\n";
        echo "   RUNNING TESTS FOR {$this->gameName}   \n";
        echo "==================================\n";

        $this->test_init_action();
        $this->test_spin_action_no_win();
        $this->test_spin_action_with_win();
        $this->test_spin_action_for_bonus();

        echo "\n----------------------------------\n";
        echo "Test Results for {$this->gameName}:\n";
        echo "PASSED: {$this->tests_passed}\n";
        echo "FAILED: {$this->tests_failed}\n";
        echo "==================================\n";

        if ($this->tests_failed > 0) {
            exit(1);
        }
        exit(0);
    }
    
    private function bail($message) {
        echo "\n{$message}\n\n";
        exit(1);
    }

    private function assert($condition, $message)
    {
        if ($condition) {
            echo "[PASS] {$message}\n";
            $this->tests_passed++;
        } else {
            echo "[FAIL] {$message}\n";
            $this->tests_failed++;
        }
    }

    private function get_initial_state()
    {
        return [
            'action' => '',
            'postData' => [],
            'gameState' => [
                'slotId' => $this->gameName,
                'playerId' => 'test_player',
                'balance' => 10000,
                'user' => ['balance' => 10000, 'shop_id' => 1],
                'game' => ['denomination' => 0.01, 'bet_values' => [1], 'lines_values' => [20]],
                'shop' => ['percent' => 90]
            ]
        ];
    }

    // --- TEST CASES ---
    public function test_init_action()
    {
        echo "\n--- Testing Init Action ---\n";
        $gameState = $this->get_initial_state();
        $gameState['action'] = 'init';
        $response = $this->server->handle($gameState);
        $this->assert(is_array($response), 'Response should be an array.');
        $this->assert(isset($response['newBalance']), 'Response must have a newBalance key.');
        $this->assert($response['newBalance'] == 10000, 'Initial balance should be 10000.');
    }
    
    public function test_spin_action_no_win()
    {
        echo "\n--- Testing Spin Action (No Win) ---\n";
        $gameState = $this->get_initial_state();
        $gameState['action'] = 'spin';
        $gameState['postData'] = ['slotEvent' => 'bet', 'bet_betlevel' => 1];
        $gameState['gameState']['desiredWinType'] = 'none';
        $response = $this->server->handle($gameState);
        $this->assert(is_array($response), 'Response should be an array.');
        $this->assert(isset($response['totalWin']), 'Response must have a totalWin key.');
        $this->assert($response['totalWin'] === 0, 'Total win should be 0 for a no-win spin.');
    }

    public function test_spin_action_with_win()
    {
        echo "\n--- Testing Spin Action (With Win) ---\n";
        $gameState = $this->get_initial_state();
        $gameState['action'] = 'spin';
        $gameState['postData'] = ['slotEvent' => 'bet', 'bet_betlevel' => 1];
        $gameState['gameState']['desiredWinType'] = 'win';
        $response = $this->server->handle($gameState);
        $this->assert(is_array($response), 'Response should be an array.');
        $this->assert(isset($response['totalWin']), 'Response must have a totalWin key.');
        $this->assert($response['totalWin'] > 0, 'Total win should be greater than 0 for a winning spin.');
        $this->assert(isset($response['winLines']) && is_array($response['winLines']) && count($response['winLines']) > 0, 'Winning spin must have winLines.');
    }

    public function test_spin_action_for_bonus()
    {
        echo "\n--- Testing Spin Action (Bonus Trigger) ---\n";
        $gameState = $this->get_initial_state();
        $gameState['action'] = 'spin';
        $gameState['postData'] = ['slotEvent' => 'bet', 'bet_betlevel' => 1];
        $gameState['gameState']['desiredWinType'] = 'bonus';
        $response = $this->server->handle($gameState);
        $this->assert(is_array($response), 'Response should be an array.');
        $this->assert(isset($response['totalFreeGames']), 'Response must have totalFreeGames key.');
        $this->assert($response['totalFreeGames'] > 0, 'Bonus spin should award free games.');
    }
}

// Main execution
$gameNameToTest = $argv[1] ?? '';
$test_runner = new UniversalTestRunner($gameNameToTest);
$test_runner->run();