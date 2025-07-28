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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Get JSON data
    $input = json_decode(file_get_contents('php://input'), true);
    $timestamp = $input['timestamp'] ?? null;
    
    if (!$timestamp) {
        throw new Exception('Invalid timestamp');
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Get employee ID
    $emp_query = "SELECT id FROM employees WHERE user_id = ?";
    $emp_stmt = $conn->prepare($emp_query);
    $emp_stmt->bind_param("i", $user_id);
    $emp_stmt->execute();
    $employee = $emp_stmt->get_result()->fetch_assoc();
    
    if (!$employee) {
        throw new Exception('Employee record not found');
    }
    
    $employee_id = $employee['id'];
    $current_date = date('Y-m-d');
    
    // Check if already clocked in today
    $check_query = "SELECT id FROM attendance WHERE employee_id = ? AND DATE(check_in) = ? AND check_out IS NULL";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("is", $employee_id, $current_date);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        throw new Exception('You are already clocked in today');
    }
    
    // Insert clock in record
    $insert_query = "INSERT INTO attendance (employee_id, check_in, created_at) VALUES (?, ?, NOW())";
    $stmt = $conn->prepare($insert_query);
    $check_in_time = date('Y-m-d H:i:s', strtotime($timestamp));
    $stmt->bind_param("is", $employee_id, $check_in_time);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Successfully clocked in',
            'time' => date('H:i', strtotime($check_in_time))
        ]);
    } else {
        throw new Exception('Failed to clock in');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
