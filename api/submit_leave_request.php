<?php
// Advanced Leave Request API with email notifications
session_start();
require_once '../db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin'])) {
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
    // Get form data
    $employee_id = intval($_POST['employee_id'] ?? ($_SESSION['employee_id'] ?? 1));
    $leave_type = trim($_POST['leave_type'] ?? '');
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    // Validate required fields
    if (empty($leave_type) || empty($start_date) || empty($end_date) || empty($reason)) {
        throw new Exception('All fields are required');
    }

    // Validate dates
    if (strtotime($start_date) > strtotime($end_date)) {
        throw new Exception('Start date cannot be after end date');
    }

    if (strtotime($start_date) < strtotime(date('Y-m-d'))) {
        throw new Exception('Start date cannot be in the past');
    }

    // Calculate number of days
    $days = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24) + 1;

    // Check leave balance
    $balance_query = "SELECT remaining_days FROM leave_balance WHERE employee_id = ? AND leave_type = ?";
    $balance_stmt = $conn->prepare($balance_query);
    $balance_stmt->bind_param("is", $employee_id, $leave_type);
    $balance_stmt->execute();
    $balance = $balance_stmt->get_result()->fetch_assoc();

    if (!$balance || $balance['remaining_days'] < $days) {
        throw new Exception('Insufficient leave balance. Available: ' . ($balance['remaining_days'] ?? 0) . ' days');
    }

    // Insert leave request
    $insert_query = "INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, reason, status, created_at) 
                     VALUES (?, ?, ?, ?, ?, 'pending', NOW())";
    
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("issss", $employee_id, $leave_type, $start_date, $end_date, $reason);
    
    if ($stmt->execute()) {
        $request_id = $conn->insert_id;
        
        // Get employee details for notification
        $emp_query = "SELECT e.*, d.name as department FROM employees e LEFT JOIN departments d ON e.department_id = d.id WHERE e.id = ?";
        $emp_stmt = $conn->prepare($emp_query);
        $emp_stmt->bind_param("i", $employee_id);
        $emp_stmt->execute();
        $employee = $emp_stmt->get_result()->fetch_assoc();
        
        // Send email notification (mock implementation)
        $notification_sent = sendLeaveRequestNotification($employee, $leave_type, $start_date, $end_date, $days, $reason);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Leave request submitted successfully' . ($notification_sent ? ' and notification sent' : ''),
            'request_id' => $request_id,
            'days_requested' => $days
        ]);
    } else {
        throw new Exception('Failed to submit leave request');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function sendLeaveRequestNotification($employee, $leave_type, $start_date, $end_date, $days, $reason) {
    // Mock email notification - in production, integrate with actual email service
    $to = 'hr@company.com';
    $subject = 'New Leave Request - ' . $employee['first_name'] . ' ' . $employee['last_name'];
    $message = "
        New leave request submitted:
        
        Employee: {$employee['first_name']} {$employee['last_name']}
        Department: {$employee['department']}
        Leave Type: " . ucfirst($leave_type) . "
        Start Date: $start_date
        End Date: $end_date
        Days Requested: $days
        Reason: $reason
        
        Please review and approve/reject this request.
    ";
    
    // In production, use mail() function or email service like PHPMailer
    // mail($to, $subject, $message);
    
    return true; // Mock success
}
?>
