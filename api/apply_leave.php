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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $employeeId = $_POST['employee_id'] ?? '';
    $leaveType = $_POST['leave_type'] ?? '';
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $reason = $_POST['reason'] ?? '';
    
    // Validation
    if (empty($employeeId) || empty($leaveType) || empty($startDate) || empty($endDate) || empty($reason)) {
        throw new Exception('All fields are required');
    }
    
    // Validate dates
    $start = DateTime::createFromFormat('Y-m-d', $startDate);
    $end = DateTime::createFromFormat('Y-m-d', $endDate);
    
    if (!$start || !$end || $start > $end) {
        throw new Exception('Invalid date range');
    }
    
    // Calculate number of days
    $interval = $start->diff($end);
    $days = $interval->days + 1;
    
    // Insert leave application
    $stmt = $conn->prepare("
        INSERT INTO leave_applications 
        (employee_id, leave_type, start_date, end_date, days_requested, reason, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    
    $stmt->bind_param("isssds", $employeeId, $leaveType, $startDate, $endDate, $days, $reason);
    
    if ($stmt->execute()) {
        $applicationId = $conn->insert_id;
        
        // Log the application
        $auditStmt = $conn->prepare("
            INSERT INTO audit_logs (action, table_name, record_id, new_values, ip_address, created_at) 
            VALUES ('leave_applied', 'leave_applications', ?, ?, ?, NOW())
        ");
        
        $newValues = json_encode([
            'employee_id' => $employeeId,
            'leave_type' => $leaveType,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'days_requested' => $days,
            'reason' => $reason
        ]);
        
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $auditStmt->bind_param("iss", $applicationId, $newValues, $ipAddress);
        $auditStmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Leave application submitted successfully',
            'application_id' => $applicationId,
            'days_requested' => $days
        ]);
    } else {
        throw new Exception('Failed to submit leave application');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
