<?php
// index.php for NarcosNET

// Set a higher memory limit if needed, though ideally, the refactored code is efficient.
// ini_set('memory_limit', '256M'); // Example, adjust if necessary

// Basic error reporting (consider more robust logging in a production environment)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Off for production, on for debugging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log'); // Ensure this path is writable

// Autoloading - Adjust if you have a central autoloader (e.g., Composer)
// For now, a simple require should work if Server.php and SlotSettings.php are in the same directory
// and GameReel.php is also handled or included within SlotSettings.php.

require_once __DIR__ . '/Server.php';
require_once __DIR__ . '/SlotSettings.php';
require_once __DIR__ . '/GameReel.php';

// The namespace for Server class is app\games\NET\NarcosNET
use app\games\NET\NarcosNET\Server;

// Create an instance of the Server
$server = new Server();

// Call the handle method to process the request
$server->handle();
