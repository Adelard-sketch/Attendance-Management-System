<?php
// manageSessions.php - Session management for courses
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';
require_once 'auth_middleware.php';

// Require authentication
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGet();
            break;
        case 'POST':
            handlePost();
            break;
        case 'PUT':
            handlePut();
            break;
        case 'DELETE':
            handleDelete();
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handleGet() {
    global $db;
    $user = getCurrentUser();
    
    $action = $_GET['action'] ?? 'list';
    
    if ($action === 'list') {
        // Get sessions for instructor's courses or student's enrolled courses
        if ($user['role'] === 'student') {
            // Get enrolled course IDs
            $enrollments = $db->enrollment_requests->find([
                'student_id' => $user['user_id'],
                'status' => 'approved'
            ]);
            
            $courseIds = [];
            foreach ($enrollments as $enrollment) {
                $courseIds[] = new MongoDB\BSON\ObjectId($enrollment['course_id']);
            }
            
            if (empty($courseIds)) {
                echo json_encode(['success' => true, 'sessions' => []]);
                return;
            }
            
            $filter = ['course_id' => ['$in' => $courseIds]];
        } else {
            // Faculty/Intern: get sessions for their courses
            $filter = ['instructor_id' => $user['user_id']];
        }
        
        $sessions = $db->sessions->find($filter, [
            'sort' => ['date' => -1, 'start_time' => -1]
        ]);
        
        $sessionList = [];
        foreach ($sessions as $session) {
            // Get course details
            $course = $db->courses->findOne(['_id' => new MongoDB\BSON\ObjectId($session['course_id'])]);
            
            $sessionList[] = [
                '_id' => (string)$session['_id'],
                'course_id' => $session['course_id'],
                'course_code' => $course['course_code'] ?? '',
                'course_name' => $course['course_name'] ?? '',
                'session_number' => $session['session_number'],
                'date' => $session['date'],
                'start_time' => $session['start_time'],
                'end_time' => $session['end_time'],
                'location' => $session['location'] ?? '',
                'status' => $session['status'],
                'description' => $session['description'] ?? '',
                'created_at' => $session['created_at'],
                'total_students' => count($session['attendance'] ?? [])
            ];
        }
        
        echo json_encode([
            'success' => true,
            'sessions' => array_values($sessionList)
        ]);
        
    } elseif ($action === 'details') {
        // Get specific session details with attendance
        $sessionId = $_GET['session_id'] ?? '';
        
        if (empty($sessionId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Session ID required']);
            return;
        }
        
        $session = $db->sessions->findOne(['_id' => new MongoDB\BSON\ObjectId($sessionId)]);
        
        if (!$session) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Session not found']);
            return;
        }
        
        // Get course details
        $course = $db->courses->findOne(['_id' => new MongoDB\BSON\ObjectId($session['course_id'])]);
        
        // Get enrolled students
        $enrollments = $db->enrollment_requests->find([
            'course_id' => $session['course_id'],
            'status' => 'approved'
        ]);
        
        $enrolledStudents = [];
        foreach ($enrollments as $enrollment) {
            $enrolledStudents[] = [
                'student_id' => $enrollment['student_id'],
                'student_name' => $enrollment['student_name'],
                'student_username' => $enrollment['student_username']
            ];
        }
        
        // Prepare attendance data
        $attendance = [];
        foreach ($session['attendance'] ?? [] as $record) {
            $attendance[] = [
                'student_id' => $record['student_id'],
                'student_name' => $record['student_name'],
                'student_username' => $record['student_username'],
                'status' => $record['status'],
                'marked_at' => $record['marked_at'] ?? null,
                'marked_by' => $record['marked_by'] ?? null
            ];
        }
        
        echo json_encode([
            'success' => true,
            'session' => [
                '_id' => (string)$session['_id'],
                'course_id' => $session['course_id'],
                'course_code' => $course['course_code'] ?? '',
                'course_name' => $course['course_name'] ?? '',
                'session_number' => $session['session_number'],
                'date' => $session['date'],
                'start_time' => $session['start_time'],
                'end_time' => $session['end_time'],
                'location' => $session['location'] ?? '',
                'status' => $session['status'],
                'description' => $session['description'] ?? '',
                'created_at' => $session['created_at'],
                'enrolled_students' => array_values($enrolledStudents),
                'attendance' => array_values($attendance)
            ]
        ]);
    }
}

function handlePost() {
    global $db;
    $user = getCurrentUser();
    
    // Only faculty and interns can create sessions
    if ($user['role'] === 'student') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only instructors can create sessions']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['course_id', 'session_number', 'date', 'start_time', 'end_time'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Field '$field' is required"]);
            return;
        }
    }
    
    // Verify course belongs to instructor
    $course = $db->courses->findOne([
        '_id' => new MongoDB\BSON\ObjectId($data['course_id']),
        'instructor_id' => $user['user_id']
    ]);
    
    if (!$course) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Course not found or access denied']);
        return;
    }
    
    // Check for duplicate session number
    $existing = $db->sessions->findOne([
        'course_id' => $data['course_id'],
        'session_number' => (int)$data['session_number']
    ]);
    
    if ($existing) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Session number already exists for this course']);
        return;
    }
    
    // Create session
    $sessionData = [
        'course_id' => $data['course_id'],
        'course_code' => $course['course_code'],
        'course_name' => $course['course_name'],
        'instructor_id' => $user['user_id'],
        'instructor_name' => $user['fullname'],
        'session_number' => (int)$data['session_number'],
        'date' => $data['date'],
        'start_time' => $data['start_time'],
        'end_time' => $data['end_time'],
        'location' => $data['location'] ?? '',
        'description' => $data['description'] ?? '',
        'status' => $data['status'] ?? 'scheduled',
        'attendance' => [],
        'created_at' => new MongoDB\BSON\UTCDateTime(),
        'updated_at' => new MongoDB\BSON\UTCDateTime()
    ];
    
    $result = $db->sessions->insertOne($sessionData);
    
    echo json_encode([
        'success' => true,
        'message' => 'Session created successfully',
        'session_id' => (string)$result->getInsertedId()
    ]);
}

function handlePut() {
    global $db;
    $user = getCurrentUser();
    
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    
    if ($action === 'update_status') {
        // Update session status (scheduled, in-progress, completed, cancelled)
        $sessionId = $data['session_id'] ?? '';
        $status = $data['status'] ?? '';
        
        if (empty($sessionId) || empty($status)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Session ID and status required']);
            return;
        }
        
        // Verify session belongs to instructor
        $session = $db->sessions->findOne([
            '_id' => new MongoDB\BSON\ObjectId($sessionId),
            'instructor_id' => $user['user_id']
        ]);
        
        if (!$session) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Session not found or access denied']);
            return;
        }
        
        $db->sessions->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($sessionId)],
            ['$set' => [
                'status' => $status,
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ]]
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Session status updated successfully'
        ]);
        
    } elseif ($action === 'update_details') {
        // Update session details
        $sessionId = $data['session_id'] ?? '';
        
        if (empty($sessionId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Session ID required']);
            return;
        }
        
        // Verify session belongs to instructor
        $session = $db->sessions->findOne([
            '_id' => new MongoDB\BSON\ObjectId($sessionId),
            'instructor_id' => $user['user_id']
        ]);
        
        if (!$session) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Session not found or access denied']);
            return;
        }
        
        $updateData = ['updated_at' => new MongoDB\BSON\UTCDateTime()];
        
        if (isset($data['date'])) $updateData['date'] = $data['date'];
        if (isset($data['start_time'])) $updateData['start_time'] = $data['start_time'];
        if (isset($data['end_time'])) $updateData['end_time'] = $data['end_time'];
        if (isset($data['location'])) $updateData['location'] = $data['location'];
        if (isset($data['description'])) $updateData['description'] = $data['description'];
        
        $db->sessions->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($sessionId)],
            ['$set' => $updateData]
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Session updated successfully'
        ]);
    }
}

function handleDelete() {
    global $db;
    $user = getCurrentUser();
    
    $data = json_decode(file_get_contents('php://input'), true);
    $sessionId = $data['session_id'] ?? '';
    
    if (empty($sessionId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Session ID required']);
        return;
    }
    
    // Verify session belongs to instructor
    $session = $db->sessions->findOne([
        '_id' => new MongoDB\BSON\ObjectId($sessionId),
        'instructor_id' => $user['user_id']
    ]);
    
    if (!$session) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Session not found or access denied']);
        return;
    }
    
    $db->sessions->deleteOne(['_id' => new MongoDB\BSON\ObjectId($sessionId)]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Session deleted successfully'
    ]);
}
?>
