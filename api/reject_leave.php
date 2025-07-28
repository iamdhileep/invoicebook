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
    
    // Update leave request status
    $update_query = "UPDATE leave_requests SET status = 'rejected', approved_by = ?, approved_at = NOW() WHERE id = ? AND status = 'pending'";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ii", $_SESSION['user_id'], $leave_id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Leave request rejected successfully']);
    } else {
        throw new Exception('Leave request not found or already processed');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
