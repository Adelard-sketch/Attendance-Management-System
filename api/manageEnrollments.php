<?php
/**
 * Enrollment Request Management API
 * Handles student course enrollment requests and faculty approvals
 */

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/auth_middleware.php';
use MongoDB\Client;
use MongoDB\BSON\ObjectId;

header('Content-Type: application/json');

// Require authentication
requireAuth();

$client = new Client("mongodb://localhost:27017");
$requestsCollection = $client->ashesi->enrollment_requests;
$coursesCollection = $client->ashesi->courses;
$usersCollection = $client->ashesi->users;

$method = $_SERVER['REQUEST_METHOD'];
$user = getCurrentUser();

switch ($method) {
    case 'GET':
        handleGet($requestsCollection, $coursesCollection, $user);
        break;
    case 'POST':
        handlePost($requestsCollection, $coursesCollection, $usersCollection, $user);
        break;
    case 'PUT':
        handlePut($requestsCollection, $coursesCollection, $user);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

function handleGet($requestsCollection, $coursesCollection, $user) {
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            // Students: see their requests; Faculty: see requests for their courses
            if ($user['role'] === 'student') {
                $requests = $requestsCollection->find(['student_id' => $user['user_id']])->toArray();
            } else {
                // Get all courses taught by this faculty member
                $courses = $coursesCollection->find(['instructor_id' => $user['user_id']])->toArray();
                $courseIds = array_map(fn($c) => (string)$c['_id'], $courses);
                
                $requests = $requestsCollection->find([
                    'course_id' => ['$in' => $courseIds]
                ])->toArray();
            }
            
            $result = array_values(array_map(function($doc) {
                return [
                    '_id' => (string)$doc['_id'],
                    'student_id' => $doc['student_id'] ?? '',
                    'student_name' => $doc['student_name'] ?? '',
                    'student_username' => $doc['student_username'] ?? '',
                    'course_id' => $doc['course_id'] ?? '',
                    'course_name' => $doc['course_name'] ?? '',
                    'course_code' => $doc['course_code'] ?? '',
                    'instructor_id' => $doc['instructor_id'] ?? '',
                    'instructor_name' => $doc['instructor_name'] ?? '',
                    'status' => $doc['status'] ?? 'pending',
                    'requested_at' => isset($doc['requested_at']) ? $doc['requested_at'] : null,
                    'reviewed_at' => isset($doc['reviewed_at']) ? $doc['reviewed_at'] : null,
                    'reviewed_by' => $doc['reviewed_by'] ?? null
                ];
            }, $requests));
            
            echo json_encode(['success' => true, 'requests' => $result]);
            break;
            
        case 'pending':
            // Faculty: get pending requests for their courses
            if (!in_array($user['role'], ['lecturer', 'intern'])) {
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                return;
            }
            
            $courses = $coursesCollection->find(['instructor_id' => $user['user_id']])->toArray();
            $courseIds = array_map(fn($c) => (string)$c['_id'], $courses);
            
            $requests = $requestsCollection->find([
                'course_id' => ['$in' => $courseIds],
                'status' => 'pending'
            ])->toArray();
            
            $result = array_values(array_map(function($doc) {
                return [
                    '_id' => (string)$doc['_id'],
                    'student_id' => $doc['student_id'] ?? '',
                    'student_name' => $doc['student_name'] ?? '',
                    'student_username' => $doc['student_username'] ?? '',
                    'course_id' => $doc['course_id'] ?? '',
                    'course_name' => $doc['course_name'] ?? '',
                    'course_code' => $doc['course_code'] ?? '',
                    'instructor_id' => $doc['instructor_id'] ?? '',
                    'instructor_name' => $doc['instructor_name'] ?? '',
                    'status' => $doc['status'] ?? 'pending',
                    'requested_at' => isset($doc['requested_at']) ? $doc['requested_at'] : null,
                    'reviewed_at' => isset($doc['reviewed_at']) ? $doc['reviewed_at'] : null,
                    'reviewed_by' => $doc['reviewed_by'] ?? null
                ];
            }, $requests));
            
            echo json_encode(['success' => true, 'requests' => $result]);
            break;
            
        case 'enrolled':
            // Students: get their enrolled courses
            if ($user['role'] !== 'student') {
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                return;
            }
            
            $approvedRequests = $requestsCollection->find([
                'student_id' => $user['user_id'],
                'status' => 'approved'
            ])->toArray();
            
            $courseIds = array_map(fn($r) => new ObjectId($r['course_id']), $approvedRequests);
            
            if (empty($courseIds)) {
                echo json_encode(['success' => true, 'courses' => []]);
                return;
            }
            
            $courses = $coursesCollection->find([
                '_id' => ['$in' => $courseIds]
            ])->toArray();
            
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
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function handlePost($requestsCollection, $coursesCollection, $usersCollection, $user) {
    // Only students can request to join courses
    if ($user['role'] !== 'student') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only students can request to join courses']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['course_id'])) {
        echo json_encode(['success' => false, 'message' => 'Course ID required']);
        return;
    }
    
    try {
        $courseId = new ObjectId($data['course_id']);
        $courseIdStr = (string)$courseId;
        
        // Check if course exists
        $course = $coursesCollection->findOne(['_id' => $courseId]);
        if (!$course) {
            echo json_encode(['success' => false, 'message' => 'Course not found']);
            return;
        }
        
        // Check if already requested or enrolled
        $existingRequest = $requestsCollection->findOne([
            'student_id' => $user['user_id'],
            'course_id' => $courseIdStr
        ]);
        
        if ($existingRequest) {
            $status = $existingRequest['status'];
            if ($status === 'approved') {
                echo json_encode(['success' => false, 'message' => 'Already enrolled in this course']);
            } else if ($status === 'pending') {
                echo json_encode(['success' => false, 'message' => 'Request already pending']);
            } else {
                // Rejected - allow re-request
                $requestsCollection->updateOne(
                    ['_id' => $existingRequest['_id']],
                    ['$set' => [
                        'status' => 'pending',
                        'requested_at' => new MongoDB\BSON\UTCDateTime(),
                        'reviewed_at' => null,
                        'reviewed_by' => null
                    ]]
                );
                echo json_encode(['success' => true, 'message' => 'Request resubmitted successfully']);
            }
            return;
        }
        
        // Create enrollment request
        $requestData = [
            'student_id' => $user['user_id'],
            'student_name' => $user['fullname'],
            'student_username' => $user['username'],
            'course_id' => $courseIdStr,
            'course_name' => $course['course_name'],
            'course_code' => $course['course_code'],
            'instructor_id' => $course['instructor_id'],
            'instructor_name' => $course['instructor_name'],
            'status' => 'pending',
            'requested_at' => new MongoDB\BSON\UTCDateTime(),
            'reviewed_at' => null,
            'reviewed_by' => null
        ];
        
        $result = $requestsCollection->insertOne($requestData);
        
        if ($result->getInsertedCount() > 0) {
            echo json_encode([
                'success' => true, 
                'message' => 'Enrollment request submitted successfully',
                'request_id' => (string)$result->getInsertedId()
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to submit request']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Invalid course ID: ' . $e->getMessage()]);
    }
}

function handlePut($requestsCollection, $coursesCollection, $user) {
    // Only faculty can approve/reject requests
    if (!in_array($user['role'], ['lecturer', 'intern'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only faculty can review requests']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['request_id'], $data['action'])) {
        echo json_encode(['success' => false, 'message' => 'Request ID and action required']);
        return;
    }
    
    $action = $data['action']; // 'approve' or 'reject'
    if (!in_array($action, ['approve', 'reject'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid action. Use approve or reject']);
        return;
    }
    
    try {
        $requestId = new ObjectId($data['request_id']);
        
        // Get the request
        $request = $requestsCollection->findOne(['_id' => $requestId]);
        if (!$request) {
            echo json_encode(['success' => false, 'message' => 'Request not found']);
            return;
        }
        
        // Check if instructor owns this course
        $course = $coursesCollection->findOne([
            '_id' => new ObjectId($request['course_id']),
            'instructor_id' => $user['user_id']
        ]);
        
        if (!$course) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        
        // Update request status
        $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
        
        $updateResult = $requestsCollection->updateOne(
            ['_id' => $requestId],
            ['$set' => [
                'status' => $newStatus,
                'reviewed_at' => new MongoDB\BSON\UTCDateTime(),
                'reviewed_by' => $user['user_id']
            ]]
        );
        
        // If approved, add student to course's enrolled list
        if ($action === 'approve') {
            $coursesCollection->updateOne(
                ['_id' => new ObjectId($request['course_id'])],
                ['$addToSet' => [
                    'enrolled_students' => [
                        'student_id' => $request['student_id'],
                        'student_name' => $request['student_name'],
                        'enrolled_at' => new MongoDB\BSON\UTCDateTime()
                    ]
                ]]
            );
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Request ' . $newStatus . ' successfully',
            'modified_count' => $updateResult->getModifiedCount()
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to process request: ' . $e->getMessage()]);
    }
}
?>
