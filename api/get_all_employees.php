<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

// Check if user is logged in (support both session variables)
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $query = "SELECT e.*, d.name as department_name
              FROM employees e
              LEFT JOIN departments d ON e.department_id = d.id
              ORDER BY e.first_name, e.last_name";
    
    $result = $conn->query($query);
    $employees = [];
    
    while ($row = $result->fetch_assoc()) {
        $employees[] = [
            'id' => $row['id'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'department' => $row['department_name'] ?? 'Not Assigned',
            'position' => $row['position'],
            'salary' => $row['salary'],
            'hire_date' => $row['hire_date']
        ];
    }
    
    echo json_encode(['success' => true, 'employees' => $employees]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
