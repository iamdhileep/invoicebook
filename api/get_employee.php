<?php
session_start();
require_once '../db.php';
require_once '../auth_check.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$employee_id = $_GET['id'] ?? 0;

if (!$employee_id) {
    echo json_encode(['success' => false, 'message' => 'Employee ID required']);
    exit();
}

try {
    $query = "SELECT e.*, d.name as department_name
              FROM employees e
              LEFT JOIN departments d ON e.department_id = d.id
              WHERE e.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $employee = $stmt->get_result()->fetch_assoc();
    
    if ($employee) {
        echo json_encode(['success' => true, 'employee' => $employee]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Employee not found']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
