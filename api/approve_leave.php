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
    $leave_id = intval($input['leave_id'] ?? 0);
    
    if ($leave_id <= 0) {
        throw new Exception('Invalid leave request ID');
    }
    
    // Get leave request details
    $get_leave = "SELECT * FROM leave_requests WHERE id = ? AND status = 'pending'";
    $get_stmt = $conn->prepare($get_leave);
    $get_stmt->bind_param("i", $leave_id);
    $get_stmt->execute();
    $leave = $get_stmt->get_result()->fetch_assoc();
    
    if (!$leave) {
        throw new Exception('Leave request not found or already processed');
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    // Update leave request status
    $update_query = "UPDATE leave_requests SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("ii", $_SESSION['user_id'], $leave_id);
    
    if (!$update_stmt->execute()) {
        throw new Exception('Failed to approve leave request');
    }
    
    // Calculate days
    $days = (strtotime($leave['end_date']) - strtotime($leave['start_date'])) / (60 * 60 * 24) + 1;
    
    // Update leave balance
    $balance_query = "UPDATE leave_balance 
                      SET used_days = used_days + ?, remaining_days = remaining_days - ?
                      WHERE employee_id = ? AND leave_type = ?";
    $balance_stmt = $conn->prepare($balance_query);
    $balance_stmt->bind_param("iiis", $days, $days, $leave['employee_id'], $leave['leave_type']);
    
    if (!$balance_stmt->execute()) {
        throw new Exception('Failed to update leave balance');
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Leave request approved successfully']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
