<?php
require __DIR__ . '/vendor/autoload.php';
use MongoDB\Client;

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$client = new Client("mongodb://localhost:27017");
$collection = $client->ashesi->users;

$data = $_POST;

// Validate required fields
if (!isset($data['fullname'], $data['email'], $data['username'], $data['role'], $data['password'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Sanitize inputs
$fullname = trim($data['fullname']);
$email = trim($data['email']);
$username = trim($data['username']);
$role = trim($data['role']);
$password = $data['password'];

// Validate fullname (at least 2 words)
if (strlen($fullname) < 3 || str_word_count($fullname) < 2) {
    echo json_encode(['success' => false, 'message' => 'Please enter your full name (first and last name)']);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

// Validate username (alphanumeric, 3-20 chars)
if (strlen($username) < 3 || strlen($username) > 20 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    echo json_encode(['success' => false, 'message' => 'Username must be 3-20 alphanumeric characters']);
    exit;
}

// Validate role
$validRoles = ['student', 'lecturer', 'intern'];
if (!in_array($role, $validRoles)) {
    echo json_encode(['success' => false, 'message' => 'Invalid role selected']);
    exit;
}

// Validate password strength (min 8 chars, includes letter and number)
if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long']);
    exit;
}
if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
    echo json_encode(['success' => false, 'message' => 'Password must contain both letters and numbers']);
    exit;
}

// Check if username/email exists
$existing = $collection->findOne([
    '$or' => [['username' => $username], ['email' => $email]]
]);

if ($existing) {
    echo json_encode(['success' => false, 'message' => 'Username or email already exists']);
    exit;
}

// Hash password securely
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Prepare user document
$userData = [
    'fullname' => $fullname,
    'email' => $email,
    'username' => $username,
    'role' => $role,
    'password' => $hashedPassword,
    'created_at' => new MongoDB\BSON\UTCDateTime(),
    'status' => 'active'
];

// Insert user
$result = $collection->insertOne($userData);
echo json_encode(['success' => $result->getInsertedCount() > 0, 'message' => 'Registration successful']);
