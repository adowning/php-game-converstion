<?php
// index.php for WingsOfRichesNET

// Basic error reporting - adjust for production
error_reporting(E_ALL);
ini_set('display_errors', 0); // Turn off for production, use logs
ini_set('log_errors', 1);
// It's good practice to have a game-specific error log or a centralized one.
ini_set('error_log', __DIR__ . '/!error_log_wingsofriches.txt');

header('Content-Type: application/json');

// Autoloading - Ensure paths are correct relative to your autoloader or include structure
// If using a global autoloader (e.g., Composer), these might not be needed.
// For this specific task, direct requires are used as per reference.
require_once __DIR__ . '/../../app/games/NET/Base/BaseSlotSettings.php'; // Base class needed by SlotSettings
require_once __DIR__ . '/SlotSettings.php';
require_once __DIR__ . '/GameReel.php';
require_once __DIR__ . '/Server.php';


// Use the Server class from its namespace
use app\games\NET\WingsOfRichesNET\Server;

// Read raw JSON input
$rawInput = file_get_contents('php://input');
if ($rawInput === false) {
    // Handle error reading input, though file_get_contents usually returns empty string on failure with php://input
    echo json_encode(['error' => 'Could not read input stream.']);
    exit;
}

// Decode the JSON input into a PHP array
$gameStateData = json_decode($rawInput, true);

// Check if JSON decoding was successful
if (json_last_error() !== JSON_ERROR_NONE) {
    // Handle JSON decoding error
    echo json_encode([
        'error' => 'Invalid JSON input.',
        'json_error_message' => json_last_error_msg()
    ]);
    exit;
}

// Instantiate the Server
$server = new Server();

// Call the handle method with the game state data
$responseArray = $server->handle($gameStateData);

// Echo the JSON-encoded response
echo json_encode($responseArray);

exit; // Ensure script termination after output
?>
