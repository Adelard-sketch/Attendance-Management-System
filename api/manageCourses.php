<?php
/**
 * Course Management API
 * Handles creating, viewing, and managing courses
 */

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/auth_middleware.php';
use MongoDB\Client;
use MongoDB\BSON\ObjectId;

header('Content-Type: application/json');

// Require authentication
requireAuth();

$client = new Client("mongodb://localhost:27017");
$coursesCollection = $client->ashesi->courses;

$method = $_SERVER['REQUEST_METHOD'];
$user = getCurrentUser();

switch ($method) {
    case 'GET':
        handleGet($coursesCollection, $user);
        break;
    case 'POST':
        handlePost($coursesCollection, $user);
        break;
    case 'PUT':
        handlePut($coursesCollection, $user);
        break;
    case 'DELETE':
        handleDelete($coursesCollection, $user);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

function handleGet($collection, $user) {
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            // Faculty/Intern: see their courses; Students: see all courses
            if ($user['role'] === 'student') {
                $courses = $collection->find()->toArray();
            } else {
                $courses = $collection->find(['instructor_id' => $user['user_id']])->toArray();
            }
            
            $result = array_values(array_map(function($doc) {
                return [
                    '_id' => (string)$doc['_id'],
                    'course_name' => $doc['course_name'] ?? '',
                    'course_code' => $doc['course_code'] ?? '',
                    'description' => $doc['description'] ?? '',
                    'credits' => $doc['credits'] ?? 3,
                    'instructor_id' => $doc['instructor_id'] ?? '',
                    'instructor_name' => $doc['instructor_name'] ?? '',
                    'status' => $doc['status'] ?? 'active',
                    'enrolled_students' => isset($doc['enrolled_students']) ? array_values((array)$doc['enrolled_students']) : []
                ];
            }, $courses));
            
            echo json_encode(['success' => true, 'courses' => $result]);
            break;
            
        case 'details':
            if (!isset($_GET['course_id'])) {
                echo json_encode(['success' => false, 'message' => 'Course ID required']);
                return;
            }
            
            try {
                $course = $collection->findOne(['_id' => new ObjectId($_GET['course_id'])]);
                if ($course) {
                    $result = [
                        '_id' => (string)$course['_id'],
                        'course_name' => $course['course_name'] ?? '',
                        'course_code' => $course['course_code'] ?? '',
                        'description' => $course['description'] ?? '',
                        'credits' => $course['credits'] ?? 3,
                        'instructor_id' => $course['instructor_id'] ?? '',
                        'instructor_name' => $course['instructor_name'] ?? '',
                        'status' => $course['status'] ?? 'active',
                        'enrolled_students' => isset($course['enrolled_students']) ? array_values((array)$course['enrolled_students']) : []
                    ];
                    echo json_encode(['success' => true, 'course' => $result]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Course not found']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Invalid course ID']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function handlePost($collection, $user) {
    // Only faculty/intern can create courses
    if (!in_array($user['role'], ['lecturer', 'intern'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only faculty can create courses']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (!isset($data['course_name'], $data['course_code'])) {
        echo json_encode(['success' => false, 'message' => 'Course name and code are required']);
        return;
    }
    
    // Sanitize inputs
    $courseName = trim($data['course_name']);
    $courseCode = strtoupper(trim($data['course_code']));
    $description = trim($data['description'] ?? '');
    $credits = intval($data['credits'] ?? 3);
    
    // Validate course code format (e.g., CS101, MATH201)
    if (!preg_match('/^[A-Z]{2,4}\d{3}$/', $courseCode)) {
        echo json_encode(['success' => false, 'message' => 'Invalid course code format (e.g., CS101)']);
        return;
    }
    
    // Check if course code already exists
    $existing = $collection->findOne(['course_code' => $courseCode]);
    if ($existing) {
        echo json_encode(['success' => false, 'message' => 'Course code already exists']);
        return;
    }
    
    // Create course document
    $courseData = [
        'course_name' => $courseName,
        'course_code' => $courseCode,
        'description' => $description,
        'credits' => $credits,
        'instructor_id' => $user['user_id'],
        'instructor_name' => $user['fullname'],
        'created_at' => new MongoDB\BSON\UTCDateTime(),
        'status' => 'active',
        'enrolled_students' => []
    ];
    
    $result = $collection->insertOne($courseData);
    
    if ($result->getInsertedCount() > 0) {
        echo json_encode([
            'success' => true, 
            'message' => 'Course created successfully',
            'course_id' => (string)$result->getInsertedId()
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create course']);
    }
}

function handlePut($collection, $user) {
    // Only faculty/intern can update their courses
    if (!in_array($user['role'], ['lecturer', 'intern'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only faculty can update courses']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['course_id'])) {
        echo json_encode(['success' => false, 'message' => 'Course ID required']);
        return;
    }
    
    try {
        $courseId = new ObjectId($data['course_id']);
        
        // Check ownership
        $course = $collection->findOne(['_id' => $courseId, 'instructor_id' => $user['user_id']]);
        if (!$course) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Course not found or access denied']);
            return;
        }
        
        // Build update document
        $updateData = [];
        if (isset($data['course_name'])) $updateData['course_name'] = trim($data['course_name']);
        if (isset($data['description'])) $updateData['description'] = trim($data['description']);
        if (isset($data['credits'])) $updateData['credits'] = intval($data['credits']);
        if (isset($data['status'])) $updateData['status'] = trim($data['status']);
        
        if (empty($updateData)) {
            echo json_encode(['success' => false, 'message' => 'No fields to update']);
            return;
        }
        
        $updateData['updated_at'] = new MongoDB\BSON\UTCDateTime();
        
        $result = $collection->updateOne(
            ['_id' => $courseId],
            ['$set' => $updateData]
        );
        
        echo json_encode([
            'success' => true, 
            'message' => 'Course updated successfully',
            'modified_count' => $result->getModifiedCount()
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Invalid course ID or update failed']);
    }
}

function handleDelete($collection, $user) {
    // Only faculty/intern can delete their courses
    if (!in_array($user['role'], ['lecturer', 'intern'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only faculty can delete courses']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['course_id'])) {
        echo json_encode(['success' => false, 'message' => 'Course ID required']);
        return;
    }
    
    try {
        $courseId = new ObjectId($data['course_id']);
        
        // Check ownership
        $result = $collection->deleteOne([
            '_id' => $courseId,
            'instructor_id' => $user['user_id']
        ]);
        
        if ($result->getDeletedCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Course deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Course not found or access denied']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Invalid course ID']);
    }
}
?>
