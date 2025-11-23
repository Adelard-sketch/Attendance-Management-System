<?php
// create_test_users.php - Create sample users for testing
require 'vendor/autoload.php';
use MongoDB\Client;

header('Content-Type: application/json');

try {
    $client = new Client("mongodb://localhost:27017");
    $db = $client->ashesi;
    
    // Check if users already exist
    $existingUsers = $db->users->countDocuments([]);
    
    if ($existingUsers > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Users already exist',
            'count' => $existingUsers
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    // Create test users
    $users = [
        [
            'fullname' => 'Test Faculty',
            'email' => 'faculty@ashesi.edu.gh',
            'username' => 'faculty_test',
            'password' => password_hash('Faculty123', PASSWORD_DEFAULT),
            'role' => 'lecturer',
            'created_at' => new MongoDB\BSON\UTCDateTime()
        ],
        [
            'fullname' => 'Test Intern',
            'email' => 'intern@ashesi.edu.gh',
            'username' => 'intern_test',
            'password' => password_hash('Intern123', PASSWORD_DEFAULT),
            'role' => 'intern',
            'created_at' => new MongoDB\BSON\UTCDateTime()
        ],
        [
            'fullname' => 'Test Student',
            'email' => 'student@ashesi.edu.gh',
            'username' => 'student_test',
            'password' => password_hash('Student123', PASSWORD_DEFAULT),
            'role' => 'student',
            'created_at' => new MongoDB\BSON\UTCDateTime()
        ]
    ];
    
    $result = $db->users->insertMany($users);
    
    echo json_encode([
        'success' => true,
        'message' => 'Test users created successfully',
        'inserted' => $result->getInsertedCount(),
        'accounts' => [
            ['username' => 'faculty_test', 'password' => 'Faculty123', 'role' => 'lecturer'],
            ['username' => 'intern_test', 'password' => 'Intern123', 'role' => 'intern'],
            ['username' => 'student_test', 'password' => 'Student123', 'role' => 'student']
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
