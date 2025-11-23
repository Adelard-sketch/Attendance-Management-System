<?php
// manageAttendance.php - Attendance marking and retrieval
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
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
    
    $action = $_GET['action'] ?? 'student_attendance';
    
    if ($action === 'student_attendance') {
        // Get attendance records for a specific student
        $studentId = $_GET['student_id'] ?? $user['user_id'];
        
        // Students can only view their own attendance
        if ($user['role'] === 'student' && $studentId !== $user['user_id']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        
        // Get all sessions where student has attendance record
        $sessions = $db->sessions->find([
            'attendance.student_id' => $studentId
        ], [
            'sort' => ['date' => -1, 'start_time' => -1]
        ]);
        
        $attendanceRecords = [];
        foreach ($sessions as $session) {
            // Find student's attendance in this session
            $studentAttendance = null;
            foreach ($session['attendance'] as $record) {
                if ($record['student_id'] === $studentId) {
                    $studentAttendance = $record;
                    break;
                }
            }
            
            if ($studentAttendance) {
                $attendanceRecords[] = [
                    'session_id' => (string)$session['_id'],
                    'course_code' => $session['course_code'],
                    'course_name' => $session['course_name'],
                    'session_number' => $session['session_number'],
                    'date' => $session['date'],
                    'start_time' => $session['start_time'],
                    'end_time' => $session['end_time'],
                    'status' => $studentAttendance['status'],
                    'marked_at' => $studentAttendance['marked_at'] ?? null,
                    'marked_by' => $studentAttendance['marked_by'] ?? null
                ];
            }
        }
        
        echo json_encode([
            'success' => true,
            'attendance' => array_values($attendanceRecords)
        ]);
        
    } elseif ($action === 'session_attendance') {
        // Get attendance for a specific session
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
        
        // Faculty can view any session, students can only view their own courses
        if ($user['role'] === 'student') {
            // Check if student is enrolled
            $enrollment = $db->enrollment_requests->findOne([
                'student_id' => $user['user_id'],
                'course_id' => $session['course_id'],
                'status' => 'approved'
            ]);
            
            if (!$enrollment) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                return;
            }
        }
        
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
            'session_id' => (string)$session['_id'],
            'course_code' => $session['course_code'],
            'course_name' => $session['course_name'],
            'session_number' => $session['session_number'],
            'date' => $session['date'],
            'attendance' => array_values($attendance)
        ]);
        
    } elseif ($action === 'course_summary') {
        // Get attendance summary for a course
        $courseId = $_GET['course_id'] ?? '';
        
        if (empty($courseId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Course ID required']);
            return;
        }
        
        // Get all sessions for this course
        $sessions = $db->sessions->find(['course_id' => $courseId]);
        
        // Get enrolled students
        $enrollments = $db->enrollment_requests->find([
            'course_id' => $courseId,
            'status' => 'approved'
        ]);
        
        $studentsSummary = [];
        foreach ($enrollments as $enrollment) {
            $studentId = $enrollment['student_id'];
            $studentsSummary[$studentId] = [
                'student_id' => $studentId,
                'student_name' => $enrollment['student_name'],
                'student_username' => $enrollment['student_username'],
                'total_sessions' => 0,
                'present' => 0,
                'absent' => 0,
                'late' => 0,
                'excused' => 0,
                'attendance_rate' => 0
            ];
        }
        
        $totalSessions = 0;
        foreach ($sessions as $session) {
            $totalSessions++;
            
            foreach ($session['attendance'] ?? [] as $record) {
                if (isset($studentsSummary[$record['student_id']])) {
                    $studentsSummary[$record['student_id']]['total_sessions']++;
                    
                    $status = strtolower($record['status']);
                    if ($status === 'present') {
                        $studentsSummary[$record['student_id']]['present']++;
                    } elseif ($status === 'absent') {
                        $studentsSummary[$record['student_id']]['absent']++;
                    } elseif ($status === 'late') {
                        $studentsSummary[$record['student_id']]['late']++;
                    } elseif ($status === 'excused') {
                        $studentsSummary[$record['student_id']]['excused']++;
                    }
                }
            }
        }
        
        // Calculate attendance rates
        foreach ($studentsSummary as &$summary) {
            if ($summary['total_sessions'] > 0) {
                $summary['attendance_rate'] = round(
                    ($summary['present'] + $summary['late']) / $summary['total_sessions'] * 100,
                    1
                );
            }
        }
        
        echo json_encode([
            'success' => true,
            'total_sessions' => $totalSessions,
            'students' => array_values($studentsSummary)
        ]);
    }
}

function handlePost() {
    global $db;
    $user = getCurrentUser();
    
    // Only faculty and interns can mark attendance
    if ($user['role'] === 'student') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only instructors can mark attendance']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? 'mark';
    
    if ($action === 'mark') {
        // Mark attendance for students
        $sessionId = $data['session_id'] ?? '';
        $attendanceData = $data['attendance'] ?? [];
        
        if (empty($sessionId) || empty($attendanceData)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Session ID and attendance data required']);
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
        
        // Prepare attendance records
        $attendanceRecords = [];
        $currentTime = new MongoDB\BSON\UTCDateTime();
        
        foreach ($attendanceData as $record) {
            if (empty($record['student_id']) || empty($record['status'])) {
                continue;
            }
            
            // Get student details
            $user_doc = $db->users->findOne(['_id' => new MongoDB\BSON\ObjectId($record['student_id'])]);
            
            $attendanceRecords[] = [
                'student_id' => $record['student_id'],
                'student_name' => $user_doc['fullname'] ?? $record['student_name'] ?? '',
                'student_username' => $user_doc['username'] ?? $record['student_username'] ?? '',
                'status' => strtolower($record['status']),
                'marked_at' => $currentTime,
                'marked_by' => $user['user_id'],
                'marked_by_name' => $user['fullname']
            ];
        }
        
        // Update session with attendance
        $db->sessions->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($sessionId)],
            [
                '$set' => [
                    'attendance' => $attendanceRecords,
                    'updated_at' => $currentTime
                ]
            ]
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Attendance marked successfully',
            'marked_count' => count($attendanceRecords)
        ]);
        
    } elseif ($action === 'mark_single') {
        // Mark attendance for a single student
        $sessionId = $data['session_id'] ?? '';
        $studentId = $data['student_id'] ?? '';
        $status = $data['status'] ?? '';
        
        if (empty($sessionId) || empty($studentId) || empty($status)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Session ID, student ID, and status required']);
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
        
        // Get student details
        $student = $db->users->findOne(['_id' => new MongoDB\BSON\ObjectId($studentId)]);
        
        if (!$student) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Student not found']);
            return;
        }
        
        $currentTime = new MongoDB\BSON\UTCDateTime();
        
        // Check if student already has attendance record
        $existingAttendance = $session['attendance'] ?? [];
        $updated = false;
        
        foreach ($existingAttendance as &$record) {
            if ($record['student_id'] === $studentId) {
                $record['status'] = strtolower($status);
                $record['marked_at'] = $currentTime;
                $record['marked_by'] = $user['user_id'];
                $record['marked_by_name'] = $user['fullname'];
                $updated = true;
                break;
            }
        }
        
        if (!$updated) {
            // Add new attendance record
            $existingAttendance[] = [
                'student_id' => $studentId,
                'student_name' => $student['fullname'],
                'student_username' => $student['username'],
                'status' => strtolower($status),
                'marked_at' => $currentTime,
                'marked_by' => $user['user_id'],
                'marked_by_name' => $user['fullname']
            ];
        }
        
        $db->sessions->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($sessionId)],
            [
                '$set' => [
                    'attendance' => $existingAttendance,
                    'updated_at' => $currentTime
                ]
            ]
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Attendance updated successfully'
        ]);
    }
}

function handlePut() {
    global $db;
    $user = getCurrentUser();
    
    // Only faculty and interns can update attendance
    if ($user['role'] === 'student') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only instructors can update attendance']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $sessionId = $data['session_id'] ?? '';
    $studentId = $data['student_id'] ?? '';
    $status = $data['status'] ?? '';
    
    if (empty($sessionId) || empty($studentId) || empty($status)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Session ID, student ID, and status required']);
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
    
    $currentTime = new MongoDB\BSON\UTCDateTime();
    
    // Update specific student's attendance
    $db->sessions->updateOne(
        [
            '_id' => new MongoDB\BSON\ObjectId($sessionId),
            'attendance.student_id' => $studentId
        ],
        [
            '$set' => [
                'attendance.$.status' => strtolower($status),
                'attendance.$.marked_at' => $currentTime,
                'attendance.$.marked_by' => $user['user_id'],
                'attendance.$.marked_by_name' => $user['fullname'],
                'updated_at' => $currentTime
            ]
        ]
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Attendance updated successfully'
    ]);
}
?>
