<?php
/**
 * Authentication Middleware
 * Include this file at the top of any protected page
 */

session_start();

function requireAuth() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['username']) || !isset($_SESSION['role'])) {
        // User not authenticated
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            // API request - return JSON
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
            exit;
        } else {
            // Regular page request - redirect to login
            header('Location: /Activity3/frontend/Login.html');
            exit;
        }
    }
}

function requireRole($allowedRoles) {
    requireAuth();
    
    if (!is_array($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }
    
    if (!in_array($_SESSION['role'], $allowedRoles)) {
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden. Insufficient permissions.']);
            exit;
        } else {
            header('Location: /Activity3/frontend/Dashboard.html');
            exit;
        }
    }
}

function getCurrentUser() {
    requireAuth();
    return [
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role' => $_SESSION['role'],
        'fullname' => $_SESSION['fullname'] ?? '',
        'email' => $_SESSION['email'] ?? ''
    ];
}
?>
