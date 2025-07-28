<?php
/**
 * Smart Leave Management API
 * Handles automated leave requests, approvals, and notifications
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

include 'db.php';

// Set timezone for consistent date handling
date_default_timezone_set('Asia/Kolkata');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'submit_leave':
        handleLeaveSubmission();
        break;
    case 'get_leave_balance':
        getLeaveBalance();
        break;
    case 'approve_leave':
        approveLeave();
        break;
    case 'reject_leave':
        rejectLeave();
        break;
    case 'get_pending_approvals':
        getPendingApprovals();
        break;
    case 'get_leave_calendar':
        getLeaveCalendar();
        break;
    case 'submit_short_leave':
        handleShortLeaveSubmission();
        break;
    case 'check_compliance':
        checkLeaveCompliance();
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

function handleLeaveSubmission() {
    global $conn;
    
    try {
        $employeeId = intval($_POST['employee_id']);
        $leaveType = mysqli_real_escape_string($conn, $_POST['leave_type']);
        $startDate = mysqli_real_escape_string($conn, $_POST['start_date']);
        $endDate = mysqli_real_escape_string($conn, $_POST['end_date']);
        $reason = mysqli_real_escape_string($conn, $_POST['reason']);
        $reasonCategory = mysqli_real_escape_string($conn, $_POST['reason_category'] ?? 'personal');
        $emergencyContact = mysqli_real_escape_string($conn, $_POST['emergency_contact'] ?? '');
        $duration = floatval($_POST['duration'] ?? calculateLeaveDays($startDate, $endDate));
        
        // Validate leave balance
        $balanceCheck = checkLeaveBalance($employeeId, $leaveType, $duration);
        if (!$balanceCheck['sufficient']) {
            echo json_encode([
                'success' => false, 
                'error' => 'Insufficient leave balance',
                'available' => $balanceCheck['available'],
                'required' => $duration
            ]);
            return;
        }
        
        // Check policy compliance
        $policyCheck = checkLeavePolicy($employeeId, $leaveType, $startDate, $endDate, $duration);
        if (!$policyCheck['compliant']) {
            echo json_encode([
                'success' => false, 
                'error' => $policyCheck['message']
            ]);
            return;
        }
        
        // Determine approval workflow
        $approver = determineApprover($employeeId, $leaveType, $duration);
        
        // Auto-approve if policy allows
        $autoApprove = shouldAutoApprove($leaveType, $duration, $reasonCategory);
        $status = $autoApprove ? 'approved' : 'pending';
        $approvedBy = $autoApprove ? 'system' : null;
        $approvedAt = $autoApprove ? date('Y-m-d H:i:s') : null;
        
        // Insert leave request
        $sql = "INSERT INTO leave_requests (
            employee_id, leave_type, start_date, end_date, duration, 
            reason, reason_category, emergency_contact, status, 
            approver_id, approved_by, approved_at, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "isssdsssssss", 
            $employeeId, $leaveType, $startDate, $endDate, $duration,
            $reason, $reasonCategory, $emergencyContact, $status,
            $approver, $approvedBy, $approvedAt
        );
        
        if ($stmt->execute()) {
            $leaveId = $conn->insert_id;
            
            // Update leave balance if approved
            if ($autoApprove) {
                updateLeaveBalance($employeeId, $leaveType, $duration, 'deduct');
                
                // Create attendance entries for approved leave
                createLeaveAttendanceEntries($employeeId, $startDate, $endDate, $leaveType);
            }
            
            // Send notifications
            sendLeaveNotification($leaveId, $status);
            
            // Log activity
            logActivity($employeeId, 'leave_request', "Submitted {$leaveType} leave for {$duration} days");
            
            echo json_encode([
                'success' => true,
                'leave_id' => $leaveId,
                'status' => $status,
                'auto_approved' => $autoApprove,
                'message' => $autoApprove ? 'Leave approved automatically' : 'Leave request submitted for approval'
            ]);
        } else {
            throw new Exception('Database error: ' . $stmt->error);
        }
        
    } catch (Exception $e) {
        error_log("Leave submission error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to submit leave request']);
    }
}

function handleShortLeaveSubmission() {
    global $conn;
    
    try {
        $employeeId = intval($_POST['employee_id']);
        $leaveType = mysqli_real_escape_string($conn, $_POST['leave_type']);
        $leaveDate = mysqli_real_escape_string($conn, $_POST['leave_date']);
        $fromTime = mysqli_real_escape_string($conn, $_POST['from_time']);
        $toTime = mysqli_real_escape_string($conn, $_POST['to_time']);
        $duration = floatval($_POST['duration']);
        $reason = mysqli_real_escape_string($conn, $_POST['reason']);
        $compensation = mysqli_real_escape_string($conn, $_POST['compensation'] ?? 'deduct-salary');
        
        // Auto-approve short leaves <= 2 hours
        $autoApprove = $duration <= 2;
        $status = $autoApprove ? 'approved' : 'pending';
        
        $sql = "INSERT INTO short_leave_requests (
            employee_id, leave_type, leave_date, from_time, to_time, 
            duration, reason, compensation_method, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "issssdss", 
            $employeeId, $leaveType, $leaveDate, $fromTime, $toTime,
            $duration, $reason, $compensation, $status
        );
        
        if ($stmt->execute()) {
            $shortLeaveId = $conn->insert_id;
            
            // Update attendance record
            updateAttendanceForShortLeave($employeeId, $leaveDate, $fromTime, $toTime, $reason);
            
            // Send notification
            sendShortLeaveNotification($shortLeaveId, $status);
            
            echo json_encode([
                'success' => true,
                'short_leave_id' => $shortLeaveId,
                'status' => $status,
                'auto_approved' => $autoApprove
            ]);
        } else {
            throw new Exception('Database error: ' . $stmt->error);
        }
        
    } catch (Exception $e) {
        error_log("Short leave submission error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to submit short leave request']);
    }
}

function getLeaveBalance() {
    global $conn;
    
    $employeeId = intval($_GET['employee_id']);
    $leaveType = mysqli_real_escape_string($conn, $_GET['leave_type']);
    
    try {
        $sql = "SELECT lb.*, lt.annual_limit, lt.carry_forward_limit 
                FROM leave_balances lb 
                JOIN leave_types lt ON lb.leave_type = lt.type_name 
                WHERE lb.employee_id = ? AND lb.leave_type = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $employeeId, $leaveType);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            echo json_encode([
                'success' => true,
                'balance' => $row
            ]);
        } else {
            // Create default balance if not exists
            $defaultBalance = getDefaultLeaveBalance($leaveType);
            createLeaveBalance($employeeId, $leaveType, $defaultBalance);
            
            echo json_encode([
                'success' => true,
                'balance' => [
                    'available_balance' => $defaultBalance,
                    'used_balance' => 0,
                    'pending_balance' => 0
                ]
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Get leave balance error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to get leave balance']);
    }
}

function approveLeave() {
    global $conn;
    
    try {
        $leaveId = intval($_POST['leave_id']);
        $approverId = $_SESSION['admin']['id'] ?? 1;
        $comments = mysqli_real_escape_string($conn, $_POST['comments'] ?? '');
        
        // Get leave details
        $leaveDetails = getLeaveDetails($leaveId);
        if (!$leaveDetails) {
            echo json_encode(['success' => false, 'error' => 'Leave request not found']);
            return;
        }
        
        // Update leave request
        $sql = "UPDATE leave_requests 
                SET status = 'approved', approved_by = ?, approved_at = NOW(), comments = ?
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isi", $approverId, $comments, $leaveId);
        
        if ($stmt->execute()) {
            // Update leave balance
            updateLeaveBalance(
                $leaveDetails['employee_id'], 
                $leaveDetails['leave_type'], 
                $leaveDetails['duration'], 
                'deduct'
            );
            
            // Create attendance entries
            createLeaveAttendanceEntries(
                $leaveDetails['employee_id'],
                $leaveDetails['start_date'],
                $leaveDetails['end_date'],
                $leaveDetails['leave_type']
            );
            
            // Send approval notification
            sendLeaveNotification($leaveId, 'approved');
            
            // Log activity
            logActivity($approverId, 'leave_approval', "Approved leave request ID: {$leaveId}");
            
            echo json_encode(['success' => true, 'message' => 'Leave approved successfully']);
        } else {
            throw new Exception('Database error: ' . $stmt->error);
        }
        
    } catch (Exception $e) {
        error_log("Leave approval error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to approve leave']);
    }
}

function getPendingApprovals() {
    global $conn;
    
    try {
        $sql = "SELECT lr.*, e.name as employee_name, e.employee_code,
                       DATEDIFF(NOW(), lr.created_at) as pending_days
                FROM leave_requests lr
                JOIN employees e ON lr.employee_id = e.employee_id
                WHERE lr.status = 'pending'
                ORDER BY lr.created_at ASC";
        
        $result = $conn->query($sql);
        $pendingRequests = [];
        
        while ($row = $result->fetch_assoc()) {
            $pendingRequests[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'pending_requests' => $pendingRequests,
            'total_pending' => count($pendingRequests)
        ]);
        
    } catch (Exception $e) {
        error_log("Get pending approvals error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to get pending approvals']);
    }
}

// Helper Functions

function calculateLeaveDays($startDate, $endDate) {
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $interval = $start->diff($end);
    return $interval->days + 1;
}

function checkLeaveBalance($employeeId, $leaveType, $requestedDays) {
    global $conn;
    
    $sql = "SELECT available_balance FROM leave_balances 
            WHERE employee_id = ? AND leave_type = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $employeeId, $leaveType);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $available = $row['available_balance'];
        return [
            'sufficient' => $available >= $requestedDays,
            'available' => $available,
            'requested' => $requestedDays
        ];
    }
    
    return ['sufficient' => false, 'available' => 0, 'requested' => $requestedDays];
}

function checkLeavePolicy($employeeId, $leaveType, $startDate, $endDate, $duration) {
    // Check advance notice requirement
    $startDateTime = new DateTime($startDate);
    $now = new DateTime();
    $advanceDays = $startDateTime->diff($now)->days;
    
    $requiredAdvanceDays = 2; // Default policy
    if ($advanceDays < $requiredAdvanceDays && $leaveType !== 'sick') {
        return [
            'compliant' => false,
            'message' => "Leave requests must be submitted {$requiredAdvanceDays} days in advance"
        ];
    }
    
    // Check maximum consecutive days without approval
    $maxConsecutive = 3;
    if ($duration > $maxConsecutive && $leaveType === 'casual') {
        return [
            'compliant' => false,
            'message' => "Casual leave cannot exceed {$maxConsecutive} consecutive days without approval"
        ];
    }
    
    return ['compliant' => true];
}

function shouldAutoApprove($leaveType, $duration, $reasonCategory) {
    // Auto-approve short leaves
    if ($duration <= 0.5) return true;
    
    // Auto-approve sick leave with medical certificate
    if ($leaveType === 'sick' && $reasonCategory === 'medical') return true;
    
    // Auto-approve comp-off
    if ($leaveType === 'comp-off') return true;
    
    return false;
}

function determineApprover($employeeId, $leaveType, $duration) {
    // Simple approval workflow - would be more complex in real implementation
    if ($duration <= 3) {
        return 1; // Direct manager
    } elseif ($duration <= 15) {
        return 2; // HR manager
    } else {
        return 3; // CEO
    }
}

function updateLeaveBalance($employeeId, $leaveType, $days, $operation) {
    global $conn;
    
    $operator = $operation === 'deduct' ? '-' : '+';
    
    $sql = "UPDATE leave_balances 
            SET available_balance = available_balance {$operator} ?,
                used_balance = used_balance " . ($operation === 'deduct' ? '+' : '-') . " ?
            WHERE employee_id = ? AND leave_type = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ddis", $days, $days, $employeeId, $leaveType);
    $stmt->execute();
}

function createLeaveAttendanceEntries($employeeId, $startDate, $endDate, $leaveType) {
    global $conn;
    
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    
    while ($start <= $end) {
        $date = $start->format('Y-m-d');
        
        // Skip weekends (optional based on policy)
        $dayOfWeek = $start->format('w');
        if ($dayOfWeek != 0 && $dayOfWeek != 6) { // Not Sunday or Saturday
            $sql = "INSERT INTO attendance (employee_id, attendance_date, status, notes, created_at)
                    VALUES (?, ?, 'On Leave', ?, NOW())
                    ON DUPLICATE KEY UPDATE 
                    status = 'On Leave', notes = ?";
            
            $stmt = $conn->prepare($sql);
            $leaveNote = ucfirst(str_replace('-', ' ', $leaveType)) . " leave";
            $stmt->bind_param("isss", $employeeId, $date, $leaveNote, $leaveNote);
            $stmt->execute();
        }
        
        $start->add(new DateInterval('P1D'));
    }
}

function sendLeaveNotification($leaveId, $status) {
    // Implementation for sending email/SMS notifications
    // This would integrate with your notification system
    
    $leaveDetails = getLeaveDetails($leaveId);
    if ($leaveDetails) {
        $message = "Leave request #{$leaveId} has been {$status}";
        // Send notification to employee and manager
        error_log("Notification: {$message}"); // Placeholder
    }
}

function sendShortLeaveNotification($shortLeaveId, $status) {
    // Similar to sendLeaveNotification but for short leaves
    $message = "Short leave request #{$shortLeaveId} has been {$status}";
    error_log("Short Leave Notification: {$message}"); // Placeholder
}

function getLeaveDetails($leaveId) {
    global $conn;
    
    $sql = "SELECT * FROM leave_requests WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $leaveId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

function logActivity($userId, $action, $description) {
    global $conn;
    
    $sql = "INSERT INTO activity_logs (user_id, action, description, created_at)
            VALUES (?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $userId, $action, $description);
    $stmt->execute();
}

function getDefaultLeaveBalance($leaveType) {
    $defaults = [
        'sick' => 12,
        'casual' => 10,
        'earned' => 21,
        'maternity' => 180,
        'paternity' => 15,
        'comp-off' => 5,
        'wfh' => 24
    ];
    
    return $defaults[$leaveType] ?? 0;
}

function createLeaveBalance($employeeId, $leaveType, $balance) {
    global $conn;
    
    $sql = "INSERT INTO leave_balances (employee_id, leave_type, available_balance, used_balance, created_at)
            VALUES (?, ?, ?, 0, NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isd", $employeeId, $leaveType, $balance);
    $stmt->execute();
}

function updateAttendanceForShortLeave($employeeId, $date, $fromTime, $toTime, $reason) {
    global $conn;
    
    $notes = "Short leave: {$fromTime} to {$toTime} - {$reason}";
    
    $sql = "UPDATE attendance 
            SET notes = CONCAT(COALESCE(notes, ''), IF(notes IS NOT NULL AND notes != '', '; ', ''), ?)
            WHERE employee_id = ? AND attendance_date = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sis", $notes, $employeeId, $date);
    $stmt->execute();
}

?>
