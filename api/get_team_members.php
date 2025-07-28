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

try {
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'] ?? 'employee';
    
    // Get current user's employee record to find their department
    $emp_query = "SELECT department_id FROM employees WHERE user_id = ?";
    $emp_stmt = $conn->prepare($emp_query);
    $emp_stmt->bind_param("i", $user_id);
    $emp_stmt->execute();
    $current_employee = $emp_stmt->get_result()->fetch_assoc();
    
    if (!$current_employee) {
        throw new Exception('Employee record not found');
    }
    
    // Get team members from same department
    $query = "SELECT e.id, e.first_name, e.last_name, e.email, e.position,
              CONCAT(e.first_name, ' ', e.last_name) as name,
              d.name as department
              FROM employees e
              LEFT JOIN departments d ON e.department_id = d.id
              WHERE e.department_id = ? AND e.user_id != ?
              ORDER BY e.first_name, e.last_name";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $current_employee['department_id'], $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $members = [];
    while ($row = $result->fetch_assoc()) {
        $members[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'position' => $row['position'] ?? 'Not Assigned',
            'department' => $row['department']
        ];
    }
    
    echo json_encode(['success' => true, 'members' => $members]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
