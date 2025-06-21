<?php
// index.php for WildWaterNET

// Basic error reporting (turn off display_errors in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Should be 0 in production
ini_set('log_errors', 1);
// It's good practice to have a specific error log for each game or a shared one
ini_set('error_log', __DIR__ . '/wildwater_error.log');

header('Content-Type: application/json');

// Autoloading or direct requires for the game classes
// Assuming the files are in a 'src' subdirectory relative to this index.php
require_once __DIR__ . '/src/Base/BaseSlotSettings.php'; // Include the placeholder Base
require_once __DIR__ . '/src/GameReel.php';
require_once __DIR__ . '/src/SlotSettings.php';
require_once __DIR__ . '/src/Server.php';

// Use the Server class from the game's namespace
use app\games\NET\WildWaterNET\Server;

// Read raw JSON input from the request body
$rawJsonInput = file_get_contents('php://input');
if ($rawJsonInput === false) {
    echo json_encode([
        'error' => true,
        'message' => 'Failed to read input stream.'
    ]);
    exit;
}

// Decode the JSON input into a PHP array
$gameStateData = json_decode($rawJsonInput, true);

// Check if JSON decoding was successful
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'error' => true,
        'message' => 'Invalid JSON input: ' . json_last_error_msg(),
        'received_input' => mb_substr($rawJsonInput, 0, 500) // Log part of the input for debugging
    ]);
    exit;
}

// Instantiate the Server
$server = new Server();

// Call the handle method with the decoded game state data
$responseArray = $server->handle($gameStateData);

// Encode the returned array as JSON and echo it
echo json_encode($responseArray);

exit; // Ensure script termination after output
?>
