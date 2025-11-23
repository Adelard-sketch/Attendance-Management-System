<?php
/**
 * Script to populate sample courses in the database
 * Run this once to add test courses
 */

require __DIR__ . '/vendor/autoload.php';
use MongoDB\Client;

try {
    $client = new Client("mongodb://localhost:27017");
    $coursesCollection = $client->ashesi->courses;
    
    // Clear existing courses (optional - comment out if you want to keep existing)
    // $coursesCollection->deleteMany([]);
    
    // Sample faculty user ID (you'll need to get this from a real faculty account)
    // For testing, we'll use a placeholder
    $facultyId = "test_faculty_001";
    $facultyName = "Dr. Jane Smith";
    
    // Sample courses
    $sampleCourses = [
        [
            'course_name' => 'Introduction to Computer Science',
            'course_code' => 'CS101',
            'description' => 'Fundamental concepts of computer science and programming',
            'credits' => 4,
            'instructor_id' => $facultyId,
            'instructor_name' => $facultyName,
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'status' => 'active',
            'enrolled_students' => []
        ],
        [
            'course_name' => 'Data Structures and Algorithms',
            'course_code' => 'CS201',
            'description' => 'Advanced data structures and algorithm design',
            'credits' => 4,
            'instructor_id' => $facultyId,
            'instructor_name' => $facultyName,
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'status' => 'active',
            'enrolled_students' => []
        ],
        [
            'course_name' => 'Web Development',
            'course_code' => 'CS301',
            'description' => 'Modern web application development with HTML, CSS, JavaScript, and PHP',
            'credits' => 3,
            'instructor_id' => $facultyId,
            'instructor_name' => $facultyName,
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'status' => 'active',
            'enrolled_students' => []
        ],
        [
            'course_name' => 'Database Systems',
            'course_code' => 'CS302',
            'description' => 'Database design, SQL, NoSQL, and database management',
            'credits' => 3,
            'instructor_id' => $facultyId,
            'instructor_name' => $facultyName,
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'status' => 'active',
            'enrolled_students' => []
        ],
        [
            'course_name' => 'Calculus I',
            'course_code' => 'MATH101',
            'description' => 'Differential and integral calculus',
            'credits' => 4,
            'instructor_id' => $facultyId,
            'instructor_name' => $facultyName,
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'status' => 'active',
            'enrolled_students' => []
        ]
    ];
    
    // Insert courses
    $result = $coursesCollection->insertMany($sampleCourses);
    
    echo json_encode([
        'success' => true,
        'message' => 'Sample courses inserted successfully',
        'inserted_count' => $result->getInsertedCount(),
        'inserted_ids' => array_values(array_map(fn($id) => (string)$id, $result->getInsertedIds()))
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
