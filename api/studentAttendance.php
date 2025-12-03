<?php
// studentAttendance.php - Students self-mark attendance using code
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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
$user = getCurrentUser();

// Only students can use this endpoint
if ($user['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'This endpoint is for students only']);
    exit;
}

try {
    if ($method === 'POST') {
        markAttendanceWithCode();
    } elseif ($method === 'GET') {
        getAvailableSessions();
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function getAvailableSessions() {
    global $db, $user;
    
    // Get student's enrolled courses
    $enrollments = $db->enrollment_requests->find([
        'student_id' => $user['user_id'],
        'status' => 'approved'
    ]);
    
    $courseIds = [];
    foreach ($enrollments as $enrollment) {
        $courseIds[] = $enrollment['course_id'];
    }
    
    if (empty($courseIds)) {
        echo json_encode(['success' => true, 'sessions' => []]);
        return;
    }
    
    // Get today's sessions for enrolled courses
    $today = date('Y-m-d');
    
    $sessions = $db->sessions->find([
        'course_id' => ['$in' => $courseIds],
        'date' => $today,
        'status' => ['$in' => ['in-progress', 'scheduled']]
    ]);
    
    $sessionList = [];
    foreach ($sessions as $session) {
        // Check if student already marked attendance
        $alreadyMarked = false;
        foreach ($session['attendance'] ?? [] as $record) {
            if ($record['student_id'] === $user['user_id']) {
                $alreadyMarked = true;
                break;
            }
        }
        
        $sessionList[] = [
            '_id' => (string)$session['_id'],
            'course_code' => $session['course_code'],
            'course_name' => $session['course_name'],
            'session_number' => $session['session_number'],
            'start_time' => $session['start_time'],
            'end_time' => $session['end_time'],
            'location' => $session['location'] ?? '',
            'status' => $session['status'],
            'already_marked' => $alreadyMarked
        ];
    }
    
    echo json_encode([
        'success' => true,
        'sessions' => array_values($sessionList)
    ]);
}

function markAttendanceWithCode() {
    global $db, $user;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $code = strtoupper(trim($data['code'] ?? ''));
    
    if (empty($code)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Attendance code is required']);
        return;
    }
    
    // Find session with this code
    $session = $db->sessions->findOne(['attendance_code' => $code]);
    
    if (!$session) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Invalid attendance code']);
        return;
    }
    
    // Check if session is today and in progress
    $today = date('Y-m-d');
    if ($session['date'] !== $today) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'This session is not scheduled for today']);
        return;
    }
    
    if ($session['status'] !== 'in-progress') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Attendance marking is not active for this session']);
        return;
    }
    
    // Check if student is enrolled in the course
    $enrollment = $db->enrollment_requests->findOne([
        'student_id' => $user['user_id'],
        'course_id' => $session['course_id'],
        'status' => 'approved'
    ]);
    
    if (!$enrollment) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You are not enrolled in this course']);
        return;
    }
    
    // Check if already marked
    $existingAttendance = $session['attendance'] ?? [];
    foreach ($existingAttendance as $record) {
        if ($record['student_id'] === $user['user_id']) {
            echo json_encode([
                'success' => true,
                'message' => 'Attendance already marked for this session',
                'status' => $record['status']
            ]);
            return;
        }
    }
    
    // Determine attendance status based on time
    $currentTime = date('H:i');
    $sessionStartTime = $session['start_time'];
    $lateThreshold = date('H:i', strtotime($sessionStartTime) + 900); // 15 minutes late threshold
    
    $status = 'present';
    if ($currentTime > $lateThreshold) {
        $status = 'late';
    }
    
    // Add attendance record
    $attendanceRecord = [
        'student_id' => $user['user_id'],
        'student_name' => $user['fullname'],
        'student_username' => $user['username'],
        'status' => $status,
        'marked_at' => new MongoDB\BSON\UTCDateTime(),
        'marked_by' => $user['user_id'],
        'marked_by_name' => 'Self',
        'method' => 'code'
    ];
    
    $existingAttendance[] = $attendanceRecord;
    
    $db->sessions->updateOne(
        ['_id' => $session['_id']],
        [
            '$set' => [
                'attendance' => $existingAttendance,
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ]
        ]
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Attendance marked successfully!',
        'status' => $status,
        'session' => [
            'course_code' => $session['course_code'],
            'course_name' => $session['course_name'],
            'session_number' => $session['session_number'],
            'date' => $session['date'],
            'time' => $session['start_time'] . ' - ' . $session['end_time']
        ]
    ]);
}
?>
