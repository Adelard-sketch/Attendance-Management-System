<?php
// Quick database check
require 'vendor/autoload.php';
use MongoDB\Client;

header('Content-Type: application/json');

try {
    $client = new Client("mongodb://localhost:27017");
    $db = $client->ashesi;
    
    $userCount = $db->users->countDocuments([]);
    $courseCount = $db->courses->countDocuments([]);
    
    echo json_encode([
        'success' => true,
        'message' => 'MongoDB connected successfully',
        'database' => 'ashesi',
        'users_count' => $userCount,
        'courses_count' => $courseCount,
        'collections' => iterator_to_array($db->listCollections())
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
