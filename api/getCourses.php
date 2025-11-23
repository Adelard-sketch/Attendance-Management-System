<?php
header('Content-Type: application/json');
require 'config.php';

$courses = $db->courses->find()->toArray();

// Convert MongoDB BSON to proper JSON array
$result = array_values(array_map(function($course) {
    return [
        '_id' => (string)$course['_id'],
        'course_name' => $course['course_name'] ?? '',
        'course_code' => $course['course_code'] ?? '',
        'description' => $course['description'] ?? '',
        'credits' => $course['credits'] ?? 3,
        'instructor_id' => $course['instructor_id'] ?? '',
        'instructor_name' => $course['instructor_name'] ?? '',
        'status' => $course['status'] ?? 'active',
        'enrolled_students' => $course['enrolled_students'] ?? []
    ];
}, $courses));

echo json_encode($result, JSON_PRETTY_PRINT);
?>
