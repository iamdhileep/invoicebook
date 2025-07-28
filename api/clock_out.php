<?php
session_start();
require_once '../db.php';
// Don't include auth_check.php as it redirects instead of returning JSON

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
    
    // Get employee ID using employee_id column
    $emp_query = "SELECT employee_id FROM employees WHERE user_id = ?";
    $emp_stmt = $conn->prepare($emp_query);
    $emp_stmt->bind_param("i", $user_id);
    $emp_stmt->execute();
    $employee = $emp_stmt->get_result()->fetch_assoc();
    
    if (!$employee) {
        throw new Exception('Employee record not found');
    }
    
    $employee_id = $employee['employee_id'];
    $current_date = date('Y-m-d');
    
    // Find today's attendance record that doesn't have checkout
    $find_query = "SELECT id, check_in FROM attendance WHERE employee_id = ? AND DATE(check_in) = ? AND check_out IS NULL ORDER BY check_in DESC LIMIT 1";
    $find_stmt = $conn->prepare($find_query);
    $find_stmt->bind_param("is", $employee_id, $current_date);
    $find_stmt->execute();
    $attendance = $find_stmt->get_result()->fetch_assoc();
    
    if (!$attendance) {
        throw new Exception('No active clock-in found for today');
    }
    
    // Update with clock out time
    $update_query = "UPDATE attendance SET check_out = ? WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $check_out_time = date('Y-m-d H:i:s', strtotime($timestamp));
    $stmt->bind_param("si", $check_out_time, $attendance['id']);
    
    if ($stmt->execute()) {
        // Calculate hours worked
        $check_in = strtotime($attendance['check_in']);
        $check_out = strtotime($check_out_time);
        $hours_worked = ($check_out - $check_in) / 3600;
        
        echo json_encode([
            'success' => true, 
            'message' => 'Successfully clocked out',
            'time' => date('H:i', strtotime($check_out_time)),
            'hours_worked' => round($hours_worked, 2)
        ]);
    } else {
        throw new Exception('Failed to clock out');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
