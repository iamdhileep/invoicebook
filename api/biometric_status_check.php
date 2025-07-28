<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include '../db.php';

try {
    $conn->query("SELECT 1 FROM employees LIMIT 1");
    
    echo json_encode([
        'success' => true,
        'status' => 'ok',
        'database_connected' => true,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'database_connected' => false,
        'message' => 'Database connection failed',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
