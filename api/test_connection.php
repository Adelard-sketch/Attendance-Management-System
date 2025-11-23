<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

echo json_encode([
    'success' => true,
    'message' => 'API is working!',
    'php_version' => phpversion(),
    'server_time' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD']
]);
?>
