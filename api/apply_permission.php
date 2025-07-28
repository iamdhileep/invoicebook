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
    $permissionDate = $_POST['permission_date'] ?? '';
    $fromTime = $_POST['from_time'] ?? '';
    $toTime = $_POST['to_time'] ?? '';
    $reason = $_POST['reason'] ?? '';
    
    // Validation
    if (empty($employeeId) || empty($permissionDate) || empty($fromTime) || empty($toTime) || empty($reason)) {
        throw new Exception('All fields are required');
    }
    
    // Validate time range
    $from = DateTime::createFromFormat('H:i', $fromTime);
    $to = DateTime::createFromFormat('H:i', $toTime);
    
    if (!$from || !$to || $from >= $to) {
        throw new Exception('Invalid time range');
    }
    
    // Insert permission request
    $stmt = $conn->prepare("
        INSERT INTO permission_requests 
        (employee_id, permission_date, from_time, to_time, reason, status, created_at) 
        VALUES (?, ?, ?, ?, ?, 'pending', NOW())
    ");
    
    $stmt->bind_param("issss", $employeeId, $permissionDate, $fromTime, $toTime, $reason);
    
    if ($stmt->execute()) {
        $requestId = $conn->insert_id;
        
        // Log the request
        $auditStmt = $conn->prepare("
            INSERT INTO audit_logs (action, table_name, record_id, new_values, ip_address, created_at) 
            VALUES ('permission_requested', 'permission_requests', ?, ?, ?, NOW())
        ");
        
        $newValues = json_encode([
            'employee_id' => $employeeId,
            'permission_date' => $permissionDate,
            'from_time' => $fromTime,
            'to_time' => $toTime,
            'reason' => $reason
        ]);
        
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $auditStmt->bind_param("iss", $requestId, $newValues, $ipAddress);
        $auditStmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Permission request submitted successfully',
            'request_id' => $requestId
        ]);
    } else {
        throw new Exception('Failed to submit permission request');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
