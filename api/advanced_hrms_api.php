<?php
/**
 * Advanced HRMS API - Core functionality for enhanced features
 * Handles multi-level approvals, permissions, policy enforcement, etc.
 */
session_start();
require_once '../db.php';

header('Content-Type: application/json');

// Check authentication
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

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'submit_leave_request':
            handleLeaveRequest($conn, $input);
            break;
            
        case 'approve_leave_request':
            handleLeaveApproval($conn, $input);
            break;
            
        case 'delegate_permissions':
            handlePermissionDelegation($conn, $input);
            break;
            
        case 'validate_leave_policy':
            validateLeavePolicy($conn, $input);
            break;
            
        case 'get_approval_workflow':
            getApprovalWorkflow($conn, $input);
            break;
            
        case 'escalate_request':
            escalateRequest($conn, $input);
            break;
            
        case 'get_analytics_data':
            getAnalyticsData($conn, $input);
            break;
            
        case 'log_mobile_access':
            logMobileAccess($conn, $input);
            break;
            
        case 'get_notifications':
            getNotifications($conn, $input);
            break;
            
        case 'update_permissions':
            updateUserPermissions($conn, $input);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handleLeaveRequest($conn, $input) {
    $employee_id = $_SESSION['employee_id'] ?? null;
    $from_date = $input['from_date'];
    $to_date = $input['to_date'];
    $leave_type = $input['leave_type'];
    $reason = $input['reason'];
    $documents = $input['documents'] ?? null;
    
    // Validate leave policy
    $policy_check = validateLeavePolicy($conn, [
        'employee_id' => $employee_id,
        'from_date' => $from_date,
        'to_date' => $to_date,
        'leave_type' => $leave_type
    ]);
    
    if (!$policy_check['valid']) {
        echo json_encode(['success' => false, 'message' => $policy_check['message']]);
        return;
    }
    
    $conn->begin_transaction();
    
    try {
        // Calculate days requested
        $days_requested = (strtotime($to_date) - strtotime($from_date)) / (60 * 60 * 24) + 1;
        
        // Insert leave request
        $stmt = $conn->prepare("INSERT INTO leave_requests (employee_id, from_date, to_date, leave_type, reason, days_requested, documents, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("issssss", $employee_id, $from_date, $to_date, $leave_type, $reason, $days_requested, $documents);
        $stmt->execute();
        
        $leave_request_id = $conn->insert_id;
        
        // Get approval workflow
        $workflow = getApprovalWorkflowSteps($conn, $employee_id);
        
        // Create approval steps
        foreach ($workflow as $step) {
            $stmt = $conn->prepare("INSERT INTO leave_approval_steps (leave_request_id, approval_level, approver_role, status) VALUES (?, ?, ?, 'pending')");
            $stmt->bind_param("iis", $leave_request_id, $step['approval_level'], $step['approver_role']);
            $stmt->execute();
        }
        
        // Update leave balance (pending)
        updateLeaveBalance($conn, $employee_id, $leave_type, 0, $days_requested);
        
        // Create notifications for first level approvers
        createNotification($conn, [
            'recipient_role' => $workflow[0]['approver_role'],
            'type' => 'leave_approval_required',
            'title' => 'New Leave Request Pending Approval',
            'message' => "Leave request from employee ID {$employee_id} requires your approval",
            'data' => json_encode(['leave_request_id' => $leave_request_id]),
            'priority' => 'medium'
        ]);
        
        // Log audit trail
        logAuditAction($conn, $_SESSION['user_id'], 'leave_request_submitted', [
            'leave_request_id' => $leave_request_id,
            'employee_id' => $employee_id,
            'from_date' => $from_date,
            'to_date' => $to_date
        ]);
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Leave request submitted successfully',
            'leave_request_id' => $leave_request_id,
            'approval_workflow' => $workflow
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function handleLeaveApproval($conn, $input) {
    $leave_request_id = $input['leave_request_id'];
    $approval_level = $input['approval_level'];
    $status = $input['status']; // 'approved' or 'rejected'
    $comments = $input['comments'] ?? '';
    $approver_id = $_SESSION['user_id'];
    
    $conn->begin_transaction();
    
    try {
        // Update approval step
        $stmt = $conn->prepare("UPDATE leave_approval_steps SET approver_id = ?, status = ?, comments = ?, approved_at = NOW() WHERE leave_request_id = ? AND approval_level = ?");
        $stmt->bind_param("issii", $approver_id, $status, $comments, $leave_request_id, $approval_level);
        $stmt->execute();
        
        if ($status === 'approved') {
            // Check if this is the final approval level
            $stmt = $conn->prepare("SELECT COUNT(*) as total_levels FROM leave_approval_steps WHERE leave_request_id = ?");
            $stmt->bind_param("i", $leave_request_id);
            $stmt->execute();
            $total_levels = $stmt->get_result()->fetch_assoc()['total_levels'];
            
            $stmt = $conn->prepare("SELECT COUNT(*) as approved_levels FROM leave_approval_steps WHERE leave_request_id = ? AND status = 'approved'");
            $stmt->bind_param("i", $leave_request_id);
            $stmt->execute();
            $approved_levels = $stmt->get_result()->fetch_assoc()['approved_levels'];
            
            if ($approved_levels >= $total_levels) {
                // Final approval - update leave request status
                $stmt = $conn->prepare("UPDATE leave_requests SET status = 'approved', approved_by = ?, approved_date = NOW(), approver_comments = ? WHERE id = ?");
                $stmt->bind_param("isi", $approver_id, $comments, $leave_request_id);
                $stmt->execute();
                
                // Update leave balance (convert pending to used)
                $stmt = $conn->prepare("SELECT employee_id, leave_type, days_requested FROM leave_requests WHERE id = ?");
                $stmt->bind_param("i", $leave_request_id);
                $stmt->execute();
                $leave_data = $stmt->get_result()->fetch_assoc();
                
                updateLeaveBalance($conn, $leave_data['employee_id'], $leave_data['leave_type'], $leave_data['days_requested'], -$leave_data['days_requested']);
                
                // Notify employee
                createNotification($conn, [
                    'recipient_id' => $leave_data['employee_id'],
                    'type' => 'leave_approved',
                    'title' => 'Leave Request Approved',
                    'message' => 'Your leave request has been approved',
                    'data' => json_encode(['leave_request_id' => $leave_request_id]),
                    'priority' => 'medium'
                ]);
                
            } else {
                // Move to next approval level
                $next_level = $approval_level + 1;
                $stmt = $conn->prepare("SELECT approver_role FROM leave_approval_steps WHERE leave_request_id = ? AND approval_level = ?");
                $stmt->bind_param("ii", $leave_request_id, $next_level);
                $stmt->execute();
                $next_approver = $stmt->get_result()->fetch_assoc();
                
                if ($next_approver) {
                    createNotification($conn, [
                        'recipient_role' => $next_approver['approver_role'],
                        'type' => 'leave_approval_required',
                        'title' => 'Leave Request Requires Your Approval',
                        'message' => "Leave request (Level {$next_level}) requires your approval",
                        'data' => json_encode(['leave_request_id' => $leave_request_id]),
                        'priority' => 'medium'
                    ]);
                }
            }
        } else {
            // Rejected - update main request
            $stmt = $conn->prepare("UPDATE leave_requests SET status = 'rejected', approved_by = ?, approved_date = NOW(), approver_comments = ? WHERE id = ?");
            $stmt->bind_param("isi", $approver_id, $comments, $leave_request_id);
            $stmt->execute();
            
            // Remove pending leave balance
            $stmt = $conn->prepare("SELECT employee_id, leave_type, days_requested FROM leave_requests WHERE id = ?");
            $stmt->bind_param("i", $leave_request_id);
            $stmt->execute();
            $leave_data = $stmt->get_result()->fetch_assoc();
            
            updateLeaveBalance($conn, $leave_data['employee_id'], $leave_data['leave_type'], 0, -$leave_data['days_requested']);
            
            // Notify employee
            createNotification($conn, [
                'recipient_id' => $leave_data['employee_id'],
                'type' => 'leave_rejected',
                'title' => 'Leave Request Rejected',
                'message' => 'Your leave request has been rejected. Comments: ' . $comments,
                'data' => json_encode(['leave_request_id' => $leave_request_id]),
                'priority' => 'high'
            ]);
        }
        
        // Log audit trail
        logAuditAction($conn, $approver_id, 'leave_approval_action', [
            'leave_request_id' => $leave_request_id,
            'approval_level' => $approval_level,
            'status' => $status,
            'comments' => $comments
        ]);
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Leave request {$status} successfully",
            'status' => $status
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function validateLeavePolicy($conn, $input) {
    $employee_id = $input['employee_id'];
    $from_date = $input['from_date'];
    $to_date = $input['to_date'];
    $leave_type = $input['leave_type'];
    
    // Check leave balance
    $days_requested = (strtotime($to_date) - strtotime($from_date)) / (60 * 60 * 24) + 1;
    $current_year = date('Y');
    
    $stmt = $conn->prepare("SELECT available_days FROM leave_balance_tracking WHERE employee_id = ? AND leave_type = ? AND year = ?");
    $stmt->bind_param("isi", $employee_id, $leave_type, $current_year);
    $stmt->execute();
    $balance = $stmt->get_result()->fetch_assoc();
    
    if (!$balance || $balance['available_days'] < $days_requested) {
        return [
            'valid' => false,
            'message' => 'Insufficient leave balance. Available: ' . ($balance['available_days'] ?? 0) . ' days, Requested: ' . $days_requested . ' days'
        ];
    }
    
    // Check for holiday overlaps
    $stmt = $conn->prepare("SELECT COUNT(*) as holiday_count FROM company_holidays WHERE holiday_date BETWEEN ? AND ?");
    $stmt->bind_param("ss", $from_date, $to_date);
    $stmt->execute();
    $holiday_count = $stmt->get_result()->fetch_assoc()['holiday_count'];
    
    if ($holiday_count > 0) {
        return [
            'valid' => true,
            'warning' => "Your leave dates overlap with {$holiday_count} company holiday(s). Please confirm if you still want to proceed."
        ];
    }
    
    return ['valid' => true];
}

function getApprovalWorkflowSteps($conn, $employee_id) {
    // Get employee's department and position for workflow determination
    $stmt = $conn->prepare("SELECT department_id, position FROM employees WHERE employee_id = ?");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $employee = $stmt->get_result()->fetch_assoc();
    
    // Get workflow configuration
    $stmt = $conn->prepare("SELECT approval_level, approver_role, escalation_hours FROM approval_workflow_config WHERE (department_id = ? OR department_id IS NULL) AND is_active = 1 ORDER BY approval_level");
    $stmt->bind_param("i", $employee['department_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $workflow = [];
    while ($row = $result->fetch_assoc()) {
        $workflow[] = $row;
    }
    
    // Default workflow if none configured
    if (empty($workflow)) {
        $workflow = [
            ['approval_level' => 1, 'approver_role' => 'manager', 'escalation_hours' => 24],
            ['approval_level' => 2, 'approver_role' => 'hr', 'escalation_hours' => 48]
        ];
    }
    
    return $workflow;
}

function updateLeaveBalance($conn, $employee_id, $leave_type, $used_days, $pending_days) {
    $current_year = date('Y');
    
    $stmt = $conn->prepare("INSERT INTO leave_balance_tracking (employee_id, leave_type, allocated_days, used_days, pending_days, year) VALUES (?, ?, 30, ?, ?, ?) ON DUPLICATE KEY UPDATE used_days = used_days + VALUES(used_days), pending_days = pending_days + VALUES(pending_days)");
    $stmt->bind_param("isiii", $employee_id, $leave_type, $used_days, $pending_days, $current_year);
    $stmt->execute();
}

function createNotification($conn, $data) {
    if (isset($data['recipient_id'])) {
        $stmt = $conn->prepare("INSERT INTO notification_queue (recipient_id, notification_type, title, message, data, priority) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $data['recipient_id'], $data['type'], $data['title'], $data['message'], $data['data'], $data['priority']);
        $stmt->execute();
    } elseif (isset($data['recipient_role'])) {
        // Get all users with the specified role
        $stmt = $conn->prepare("SELECT user_id FROM employees e JOIN users u ON e.user_id = u.id WHERE u.role = ?");
        $stmt->bind_param("s", $data['recipient_role']);
        $stmt->execute();
        $users = $stmt->get_result();
        
        $stmt = $conn->prepare("INSERT INTO notification_queue (recipient_id, notification_type, title, message, data, priority) VALUES (?, ?, ?, ?, ?, ?)");
        while ($user = $users->fetch_assoc()) {
            $stmt->bind_param("isssss", $user['user_id'], $data['type'], $data['title'], $data['message'], $data['data'], $data['priority']);
            $stmt->execute();
        }
    }
}

function logAuditAction($conn, $user_id, $action, $data) {
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, data, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $data_json = json_encode($data);
    $stmt->bind_param("issss", $user_id, $action, $data_json, $ip_address, $user_agent);
    $stmt->execute();
}

function getNotifications($conn, $input) {
    $user_id = $_SESSION['user_id'];
    $limit = $input['limit'] ?? 10;
    $offset = $input['offset'] ?? 0;
    
    $stmt = $conn->prepare("SELECT id, notification_type, title, message, data, priority, status, created_at FROM notification_queue WHERE recipient_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("iii", $user_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    echo json_encode(['success' => true, 'notifications' => $notifications]);
}

function getAnalyticsData($conn, $input) {
    $metric_type = $input['metric_type'];
    $start_date = $input['start_date'] ?? date('Y-m-01');
    $end_date = $input['end_date'] ?? date('Y-m-t');
    
    $stmt = $conn->prepare("SELECT metric_name, metric_value, metric_data, date_recorded FROM hrms_analytics WHERE metric_type = ? AND date_recorded BETWEEN ? AND ? ORDER BY date_recorded");
    $stmt->bind_param("sss", $metric_type, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $analytics = [];
    while ($row = $result->fetch_assoc()) {
        $analytics[] = $row;
    }
    
    echo json_encode(['success' => true, 'analytics' => $analytics]);
}

function updateUserPermissions($conn, $input) {
    $user_id = $input['user_id'];
    $permissions = $input['permissions'];
    
    $conn->begin_transaction();
    
    try {
        // Delete existing permissions
        $stmt = $conn->prepare("DELETE FROM user_permissions WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        // Insert new permissions
        $stmt = $conn->prepare("INSERT INTO user_permissions (user_id, module_id, can_add, can_edit, can_view, can_delete, can_approve) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($permissions as $permission) {
            $stmt->bind_param("iiiiiii", 
                $user_id, 
                $permission['module_id'],
                $permission['can_add'],
                $permission['can_edit'],
                $permission['can_view'],
                $permission['can_delete'],
                $permission['can_approve']
            );
            $stmt->execute();
        }
        
        logAuditAction($conn, $_SESSION['user_id'], 'permissions_updated', ['target_user_id' => $user_id]);
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Permissions updated successfully']);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}
?>
