<?php
// config.php — MongoDB connection setup

require 'vendor/autoload.php'; 
use MongoDB\Client;

// MongoDB connection
try {
    // Database name: ashesi
    $mongoClient = new Client("mongodb://localhost:27017");
    $db = $mongoClient->ashesi; 
} catch (Exception $e) {
    die(json_encode([
        "success" => false,
        "message" => "MongoDB connection failed: " . $e->getMessage()
    ]));
}
?>
