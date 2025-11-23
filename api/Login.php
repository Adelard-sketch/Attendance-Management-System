<?php
session_start();
require __DIR__ . '/vendor/autoload.php';
use MongoDB\Client;

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$client = new Client("mongodb://localhost:27017");
$collection = $client->ashesi->users;

$data = $_POST;

// Input validation
if (!isset($data['username'], $data['password'], $data['userType'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Sanitize inputs
$username = trim($data['username']);
$password = $data['password'];
$userType = trim($data['userType']);

// Validate username format (alphanumeric, min 3 chars)
if (strlen($username) < 3 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    echo json_encode(['success' => false, 'message' => 'Invalid username format']);
    exit;
}

// Find user
$user = $collection->findOne(['username' => $username]);
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

// Verify password & role
if (password_verify($password, $user['password']) && $userType === $user['role']) {
    // Set session variables
    $_SESSION['user_id'] = (string)$user['_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['fullname'] = $user['fullname'];
    $_SESSION['email'] = $user['email'];
    
    echo json_encode([
        'success' => true, 
        'username' => $user['username'], 
        'role' => $user['role'],
        'fullname' => $user['fullname']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid credentials or role']);
}
