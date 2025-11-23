<?php
/**
 * Verification Script
 * Checks if password hashing is working correctly
 */

require __DIR__ . '/vendor/autoload.php';
use MongoDB\Client;

header('Content-Type: application/json');

try {
    $client = new Client("mongodb://localhost:27017");
    $usersCollection = $client->ashesi->users;
    
    // Get a sample user
    $sampleUser = $usersCollection->findOne([], ['limit' => 1]);
    
    $checks = [];
    
    // Check 1: MongoDB connection
    $checks['mongodb_connected'] = true;
    
    // Check 2: Users collection exists
    $userCount = $usersCollection->countDocuments([]);
    $checks['users_exist'] = $userCount > 0;
    $checks['user_count'] = $userCount;
    
    // Check 3: Password hashing
    if ($sampleUser && isset($sampleUser['password'])) {
        $password = $sampleUser['password'];
        
        // Check if password is hashed (bcrypt hashes start with $2y$ and are 60 chars)
        $isHashed = (strlen($password) === 60 && substr($password, 0, 4) === '$2y$');
        
        $checks['password_hashed'] = $isHashed;
        $checks['password_format'] = substr($password, 0, 10) . '...';
        $checks['password_length'] = strlen($password);
        
        if (!$isHashed) {
            $checks['warning'] = 'Password does not appear to be hashed with bcrypt!';
        }
    } else {
        $checks['password_hashed'] = null;
        $checks['note'] = 'No users found to check password hashing';
    }
    
    // Check 4: Courses collection
    $coursesCollection = $client->ashesi->courses;
    $courseCount = $coursesCollection->countDocuments([]);
    $checks['courses_exist'] = $courseCount > 0;
    $checks['course_count'] = $courseCount;
    
    // Check 5: Enrollment requests collection
    $enrollmentsCollection = $client->ashesi->enrollment_requests;
    $enrollmentCount = $enrollmentsCollection->countDocuments([]);
    $checks['enrollments_count'] = $enrollmentCount;
    
    echo json_encode([
        'success' => true,
        'checks' => $checks,
        'summary' => [
            'users' => $userCount,
            'courses' => $courseCount,
            'enrollments' => $enrollmentCount,
            'password_hashing_working' => $checks['password_hashed'] ?? false
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
