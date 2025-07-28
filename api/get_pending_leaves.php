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
    $query = "SELECT lr.*, e.first_name, e.last_name, 
              CONCAT(e.first_name, ' ', e.last_name) as employee_name,
              DATEDIFF(lr.end_date, lr.start_date) + 1 as days
              FROM leave_requests lr
              JOIN employees e ON lr.employee_id = e.id
              WHERE lr.status = 'pending'
              ORDER BY lr.created_at DESC";
    
    $result = $conn->query($query);
    $leaves = [];
    
    while ($row = $result->fetch_assoc()) {
        $leaves[] = [
            'id' => $row['id'],
            'employee_name' => $row['employee_name'],
            'leave_type' => ucfirst($row['leave_type']),
            'start_date' => date('M d, Y', strtotime($row['start_date'])),
            'end_date' => date('M d, Y', strtotime($row['end_date'])),
            'days' => $row['days'],
            'reason' => $row['reason']
        ];
    }
    
    echo json_encode(['success' => true, 'leaves' => $leaves]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
