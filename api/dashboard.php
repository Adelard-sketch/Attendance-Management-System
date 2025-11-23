<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/auth_middleware.php';
use MongoDB\Client;

header('Content-Type: application/json');

// Require authentication to access dashboard
requireAuth();

$user = getCurrentUser();
$section = $_GET['section'] ?? 'courses';

$client = new Client("mongodb://localhost:27017");
$db = $client->ashesi;

$collections = [
    'courses' => 'courses',
    'sessions' => 'sessions',
    'reports' => 'reports',
    'auditors' => 'auditors'
];

if (!isset($collections[$section])) {
    echo json_encode([]);
    exit;
}

$collection = $db->{$collections[$section]};

// Filter data based on user role
if ($section === 'courses') {
    if ($user['role'] === 'student') {
        // Students see all courses (for browsing/requesting)
        $items = $collection->find()->toArray();
    } else {
        // Faculty see only their courses
        $items = $collection->find(['instructor_id' => $user['user_id']])->toArray();
    }
} else {
    // For other sections, apply appropriate filters based on role
    $items = $collection->find()->toArray();
}

// Convert BSON to JSON - ensure it's a proper array, not objects
$result = array_values(array_map(function($doc) {
    $arr = [];
    foreach ($doc as $key => $value) {
        if ($key === '_id') {
            $arr['_id'] = (string)$value;
        } else if (is_object($value)) {
            // Handle nested objects/arrays
            $arr[$key] = json_decode(json_encode($value), true);
        } else {
            $arr[$key] = $value;
        }
    }
    return $arr;
}, $items));

echo json_encode($result, JSON_PRETTY_PRINT);
