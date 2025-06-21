<?php
// index.php for SpaceWarsNET

// Set content type to JSON
header('Content-Type: application/json');

// Basic error reporting - adjust for production
error_reporting(E_ALL);
ini_set('display_errors', 0); // Turn off for production, use logs instead
ini_set('log_errors', 1);
// Define a game-specific error log file or use a centralized one
ini_set('error_log', __DIR__ . '/!error_log_spacewars.txt');

// Autoloading - Ensure paths are correct relative to your autoloader or include structure.
// For this task, direct requires are used as per the established pattern.
// BaseSlotSettings is required by SlotSettings
require_once __DIR__ . '/../../app/games/NET/Base/BaseSlotSettings.php';
require_once __DIR__ . '/SlotSettings.php';
require_once __DIR__ . '/GameReel.php';
require_once __DIR__ . '/Server.php';

// Use the Server class from its specific namespace
use app\games\NET\SpaceWarsNET\Server;

// Read raw JSON input from the request body
$rawInput = file_get_contents('php://input');
if ($rawInput === false) {
    // This case is unlikely with php://input but good for robustness
    echo json_encode(['error' => 'Failed to read input stream.']);
    exit;
}

// Decode the JSON input into a PHP array
$gameStateData = json_decode($rawInput, true);

// Check if JSON decoding was successful
if (json_last_error() !== JSON_ERROR_NONE) {
    // Handle JSON decoding error by returning a JSON error message
    echo json_encode([
        'error' => 'Invalid JSON input provided.',
        'json_error_message' => json_last_error_msg()
    ]);
    exit;
}

// Instantiate the Server
$server = new Server();

// Call the handle method with the decoded game state data
$responseArray = $server->handle($gameStateData);

// Echo the JSON-encoded response array
echo json_encode($responseArray);

// Ensure script termination after outputting the response
exit;
?>
