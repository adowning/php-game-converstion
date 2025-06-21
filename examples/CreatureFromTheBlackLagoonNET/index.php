<?php
// index.php for CreatureFromTheBlackLagoonNET

// Basic error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Off for production
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/creature_error.log'); // Game-specific error log

// Autoloading
require_once __DIR__ . '/Server.php';
require_once __DIR__ . '/SlotSettings.php';
require_once __DIR__ . '/GameReel.php';

// The namespace for Server class is app\games\NET\CreatureFromTheBlackLagoonNET
use app\games\NET\CreatureFromTheBlackLagoonNET\Server;

// Create an instance of the Server
$server = new Server();

// Call the handle method to process the request
$server->handle();
?>
