<?php
session_start();
header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

include '../../db.php';

// Set timezone
date_default_timezone_set('Asia/Kolkata');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_leave_requests':
        getLeaveRequests();
        break;
    case 'get_leave_request_details':
        getLeaveRequestDetails();
        break;
    case 'process_leave_request':
        processLeaveRequest();
        break;
    case 'get_employee_leave_balance':
        getEmployeeLeaveBalance();
        break;
    case 'update_leave_balance':
        updateLeaveBalance();
        break;
    case 'get_hr_analytics':
        getHRAnalytics();
        break;
    case 'bulk_approve_leaves':
        bulkApproveLeaves();
        break;
    case 'get_recent_activity':
        getRecentActivity();
        break;
    case 'save_hr_settings':
        saveHRSettings();
        break;
    case 'generate_employee_report':
        generateEmployeeReport();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function getLeaveRequests() {
    global $conn;
    
    $status_filter = $_GET['status'] ?? '';
    $limit = $_GET['limit'] ?? 50;
    $offset = $_GET['offset'] ?? 0;
    
    try {
        $query = "
            SELECT 
                lr.id,
                lr.employee_id,
                lr.leave_type,
                lr.start_date,
                lr.end_date,
                lr.duration_days,
                lr.reason,
                lr.status,
                lr.priority,
                lr.applied_date,
                lr.manager_comments,
                lr.emergency_contact,
                lr.handover_details,
                lr.attachment_path,
                e.name as employee_name,
                e.employee_code,
                e.position as department
            FROM leave_requests lr
            LEFT JOIN employees e ON lr.employee_id = e.employee_id
            WHERE 1=1
        ";
        
        if (!empty($status_filter)) {
            $query .= " AND lr.status = '" . mysqli_real_escape_string($conn, $status_filter) . "'";
        }
        
        $query .= " ORDER BY 
            CASE 
                WHEN lr.priority = 'emergency' THEN 1
                WHEN lr.priority = 'urgent' THEN 2
                ELSE 3
            END,
            CASE 
                WHEN lr.status = 'pending' THEN 1
                WHEN lr.status = 'approved' THEN 2
                ELSE 3
            END,
            lr.applied_date DESC
            LIMIT $limit OFFSET $offset
        ";
        
        $result = $conn->query($query);
        
        if (!$result) {
            throw new Exception("Database query failed: " . $conn->error);
        }
        
        $requests = [];
        while ($row = $result->fetch_assoc()) {
            // Format dates
            $row['applied_date'] = date('M j, Y', strtotime($row['applied_date']));
            $row['start_date'] = date('M j, Y', strtotime($row['start_date']));
            $row['end_date'] = date('M j, Y', strtotime($row['end_date']));
            
            $requests[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'requests' => $requests,
            'total' => count($requests)
        ]);
        
    } catch (Exception $e) {
        error_log("Error in getLeaveRequests: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch leave requests: ' . $e->getMessage()
        ]);
    }
}

function getLeaveRequestDetails() {
    global $conn;
    
    $request_id = $_GET['id'] ?? '';
    
    if (empty($request_id)) {
        echo json_encode(['success' => false, 'message' => 'Request ID is required']);
        return;
    }
    
    try {
        $query = "
            SELECT 
                lr.*,
                e.name as employee_name,
                e.employee_code,
                e.position as department,
                e.email as employee_email
            FROM leave_requests lr
            LEFT JOIN employees e ON lr.employee_id = e.employee_id
            WHERE lr.id = ?
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Format dates
            $row['applied_date'] = date('M j, Y', strtotime($row['applied_date']));
            $row['start_date'] = date('M j, Y', strtotime($row['start_date']));
            $row['end_date'] = date('M j, Y', strtotime($row['end_date']));
            
            echo json_encode([
                'success' => true,
                'request' => $row
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Leave request not found'
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Error in getLeaveRequestDetails: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch request details: ' . $e->getMessage()
        ]);
    }
}

function processLeaveRequest() {
    global $conn;
    
    $request_id = $_POST['request_id'] ?? '';
    $status = $_POST['status'] ?? '';
    $comments = $_POST['comments'] ?? '';
    $processed_by = $_SESSION['admin'] ?? 'HR';
    
    if (empty($request_id) || empty($status)) {
        echo json_encode(['success' => false, 'message' => 'Request ID and status are required']);
        return;
    }
    
    if (!in_array($status, ['approved', 'rejected'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        return;
    }
    
    try {
        $conn->autocommit(FALSE);
        
        // Update leave request
        $update_query = "
            UPDATE leave_requests 
            SET status = ?, 
                manager_comments = ?, 
                processed_by = ?,
                processed_date = NOW()
            WHERE id = ?
        ";
        
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("sssi", $status, $comments, $processed_by, $request_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update leave request");
        }
        
        // Get request details for further processing
        $details_query = "
            SELECT lr.*, e.name as employee_name, e.email 
            FROM leave_requests lr
            LEFT JOIN employees e ON lr.employee_id = e.employee_id
            WHERE lr.id = ?
        ";
        $details_stmt = $conn->prepare($details_query);
        $details_stmt->bind_param("i", $request_id);
        $details_stmt->execute();
        $request_details = $details_stmt->get_result()->fetch_assoc();
        
        // If approved, update leave balance
        if ($status === 'approved' && $request_details) {
            updateEmployeeLeaveBalance($request_details['employee_id'], $request_details['leave_type'], $request_details['duration_days']);
        }
        
        // Log the activity
        logHRActivity($processed_by, "Leave request {$status}", "Employee: {$request_details['employee_name']}, Type: {$request_details['leave_type']}, Days: {$request_details['duration_days']}");
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Leave request {$status} successfully!"
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error in processLeaveRequest: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to process leave request: ' . $e->getMessage()
        ]);
    } finally {
        $conn->autocommit(TRUE);
    }
}

function updateEmployeeLeaveBalance($employee_id, $leave_type, $days_taken) {
    global $conn;
    
    try {
        // Check if leave balance record exists
        $check_query = "SELECT id FROM employee_leave_balance WHERE employee_id = ? AND year = YEAR(CURDATE())";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("i", $employee_id);
        $check_stmt->execute();
        $balance_exists = $check_stmt->get_result()->num_rows > 0;
        
        if (!$balance_exists) {
            // Create new balance record with default values
            $create_query = "
                INSERT INTO employee_leave_balance 
                (employee_id, year, casual_leave_balance, sick_leave_balance, earned_leave_balance, comp_off_balance) 
                VALUES (?, YEAR(CURDATE()), 12, 7, 21, 5)
            ";
            $create_stmt = $conn->prepare($create_query);
            $create_stmt->bind_param("i", $employee_id);
            $create_stmt->execute();
        }
        
        // Update the specific leave balance
        $balance_field = '';
        switch ($leave_type) {
            case 'casual':
                $balance_field = 'casual_leave_balance';
                break;
            case 'sick':
                $balance_field = 'sick_leave_balance';
                break;
            case 'earned':
                $balance_field = 'earned_leave_balance';
                break;
            case 'comp-off':
                $balance_field = 'comp_off_balance';
                break;
            default:
                return; // Don't deduct for other leave types
        }
        
        if ($balance_field) {
            $update_query = "
                UPDATE employee_leave_balance 
                SET {$balance_field} = GREATEST(0, {$balance_field} - ?)
                WHERE employee_id = ? AND year = YEAR(CURDATE())
            ";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("di", $days_taken, $employee_id);
            $update_stmt->execute();
        }
        
    } catch (Exception $e) {
        error_log("Error updating leave balance: " . $e->getMessage());
    }
}

function getEmployeeLeaveBalance() {
    global $conn;
    
    try {
        $query = "
            SELECT 
                e.employee_id,
                e.name,
                e.employee_code,
                COALESCE(elb.casual_leave_balance, 12) as casual_balance,
                COALESCE(elb.sick_leave_balance, 7) as sick_balance,
                COALESCE(elb.earned_leave_balance, 21) as earned_balance,
                COALESCE(elb.comp_off_balance, 5) as comp_off_balance
            FROM employees e
            LEFT JOIN employee_leave_balance elb ON e.employee_id = elb.employee_id AND elb.year = YEAR(CURDATE())
            WHERE e.status = 'active'
            ORDER BY e.name
        ";
        
        $result = $conn->query($query);
        
        if (!$result) {
            throw new Exception("Database query failed: " . $conn->error);
        }
        
        $balances = [];
        while ($row = $result->fetch_assoc()) {
            $balances[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'balances' => $balances
        ]);
        
    } catch (Exception $e) {
        error_log("Error in getEmployeeLeaveBalance: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch leave balances: ' . $e->getMessage()
        ]);
    }
}

function getHRAnalytics() {
    global $conn;
    
    try {
        $analytics = [];
        
        // Leave type distribution
        $leave_types_query = "
            SELECT leave_type, COUNT(*) as count 
            FROM leave_requests 
            WHERE applied_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY leave_type
        ";
        $leave_types_result = $conn->query($leave_types_query);
        $analytics['leave_types'] = [];
        while ($row = $leave_types_result->fetch_assoc()) {
            $analytics['leave_types'][] = $row;
        }
        
        // Monthly attendance trends
        $attendance_trends_query = "
            SELECT 
                DATE_FORMAT(attendance_date, '%Y-%m') as month,
                COUNT(CASE WHEN status IN ('Present', 'Late') THEN 1 END) as present_count,
                COUNT(*) as total_count
            FROM attendance 
            WHERE attendance_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(attendance_date, '%Y-%m')
            ORDER BY month
        ";
        $attendance_trends_result = $conn->query($attendance_trends_query);
        $analytics['attendance_trends'] = [];
        while ($row = $attendance_trends_result->fetch_assoc()) {
            $row['attendance_rate'] = $row['total_count'] > 0 ? round(($row['present_count'] / $row['total_count']) * 100, 2) : 0;
            $analytics['attendance_trends'][] = $row;
        }
        
        // Department wise statistics
        $dept_stats_query = "
            SELECT 
                e.position as department,
                COUNT(DISTINCT e.employee_id) as total_employees,
                COUNT(lr.id) as leave_requests_count
            FROM employees e
            LEFT JOIN leave_requests lr ON e.employee_id = lr.employee_id 
                AND lr.applied_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
            WHERE e.status = 'active'
            GROUP BY e.position
        ";
        $dept_stats_result = $conn->query($dept_stats_query);
        $analytics['department_stats'] = [];
        while ($row = $dept_stats_result->fetch_assoc()) {
            $analytics['department_stats'][] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'analytics' => $analytics
        ]);
        
    } catch (Exception $e) {
        error_log("Error in getHRAnalytics: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch analytics: ' . $e->getMessage()
        ]);
    }
}

function bulkApproveLeaves() {
    global $conn;
    
    $request_ids = $_POST['request_ids'] ?? [];
    $comments = $_POST['comments'] ?? '';
    $processed_by = $_SESSION['admin'] ?? 'HR';
    
    if (empty($request_ids)) {
        echo json_encode(['success' => false, 'message' => 'No requests selected']);
        return;
    }
    
    try {
        $conn->autocommit(FALSE);
        
        $approved_count = 0;
        $placeholders = str_repeat('?,', count($request_ids) - 1) . '?';
        
        // Update all selected requests
        $update_query = "
            UPDATE leave_requests 
            SET status = 'approved', 
                manager_comments = ?, 
                processed_by = ?,
                processed_date = NOW()
            WHERE id IN ($placeholders) AND status = 'pending'
        ";
        
        $stmt = $conn->prepare($update_query);
        $params = array_merge([$comments, $processed_by], $request_ids);
        $types = 'ss' . str_repeat('i', count($request_ids));
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            $approved_count = $stmt->affected_rows;
        }
        
        // Log the activity
        logHRActivity($processed_by, "Bulk approval", "Approved {$approved_count} leave requests");
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Successfully approved {$approved_count} leave requests"
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error in bulkApproveLeaves: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to bulk approve leaves: ' . $e->getMessage()
        ]);
    } finally {
        $conn->autocommit(TRUE);
    }
}

function getRecentActivity() {
    global $conn;
    
    try {
        $query = "
            SELECT 
                activity_type,
                description,
                performed_by,
                activity_date
            FROM hr_activity_log 
            ORDER BY activity_date DESC 
            LIMIT 20
        ";
        
        $result = $conn->query($query);
        
        $activities = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row['time_ago'] = timeAgo($row['activity_date']);
                $activities[] = $row;
            }
        }
        
        echo json_encode([
            'success' => true,
            'activities' => $activities
        ]);
        
    } catch (Exception $e) {
        error_log("Error in getRecentActivity: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch recent activity: ' . $e->getMessage()
        ]);
    }
}

function logHRActivity($performed_by, $activity_type, $description) {
    global $conn;
    
    try {
        // Create table if it doesn't exist
        $create_table_query = "
            CREATE TABLE IF NOT EXISTS hr_activity_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                activity_type VARCHAR(100) NOT NULL,
                description TEXT,
                performed_by VARCHAR(100) NOT NULL,
                activity_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_activity_date (activity_date),
                INDEX idx_performed_by (performed_by)
            )
        ";
        $conn->query($create_table_query);
        
        $insert_query = "
            INSERT INTO hr_activity_log (activity_type, description, performed_by) 
            VALUES (?, ?, ?)
        ";
        
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("sss", $activity_type, $description, $performed_by);
        $stmt->execute();
        
    } catch (Exception $e) {
        error_log("Error logging HR activity: " . $e->getMessage());
    }
}

function saveHRSettings() {
    global $conn;
    
    $settings = $_POST['settings'] ?? [];
    
    try {
        // Create settings table if it doesn't exist
        $create_table_query = "
            CREATE TABLE IF NOT EXISTS hr_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) UNIQUE NOT NULL,
                setting_value TEXT,
                updated_by VARCHAR(100),
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ";
        $conn->query($create_table_query);
        
        // Save each setting
        foreach ($settings as $key => $value) {
            $insert_query = "
                INSERT INTO hr_settings (setting_key, setting_value, updated_by) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    setting_value = VALUES(setting_value),
                    updated_by = VALUES(updated_by),
                    updated_at = CURRENT_TIMESTAMP
            ";
            
            $stmt = $conn->prepare($insert_query);
            $updated_by = $_SESSION['admin'] ?? 'HR';
            $stmt->bind_param("sss", $key, $value, $updated_by);
            $stmt->execute();
        }
        
        logHRActivity($_SESSION['admin'] ?? 'HR', "Settings updated", "HR system settings modified");
        
        echo json_encode([
            'success' => true,
            'message' => 'HR settings saved successfully'
        ]);
        
    } catch (Exception $e) {
        error_log("Error in saveHRSettings: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to save settings: ' . $e->getMessage()
        ]);
    }
}

function generateEmployeeReport() {
    global $conn;
    
    $report_type = $_POST['report_type'] ?? 'attendance';
    $start_date = $_POST['start_date'] ?? date('Y-m-01');
    $end_date = $_POST['end_date'] ?? date('Y-m-t');
    $employee_id = $_POST['employee_id'] ?? '';
    
    try {
        $report_data = [];
        
        switch ($report_type) {
            case 'attendance':
                $query = "
                    SELECT 
                        e.name,
                        e.employee_code,
                        COUNT(CASE WHEN a.status = 'Present' THEN 1 END) as present_days,
                        COUNT(CASE WHEN a.status = 'Absent' THEN 1 END) as absent_days,
                        COUNT(CASE WHEN a.status = 'Late' THEN 1 END) as late_days,
                        COUNT(CASE WHEN a.status = 'Half Day' THEN 1 END) as half_days
                    FROM employees e
                    LEFT JOIN attendance a ON e.employee_id = a.employee_id 
                        AND a.attendance_date BETWEEN ? AND ?
                    WHERE e.status = 'active'
                ";
                if (!empty($employee_id)) {
                    $query .= " AND e.employee_id = ?";
                }
                $query .= " GROUP BY e.employee_id ORDER BY e.name";
                
                $stmt = $conn->prepare($query);
                if (!empty($employee_id)) {
                    $stmt->bind_param("ssi", $start_date, $end_date, $employee_id);
                } else {
                    $stmt->bind_param("ss", $start_date, $end_date);
                }
                break;
                
            case 'leave':
                $query = "
                    SELECT 
                        e.name,
                        e.employee_code,
                        lr.leave_type,
                        lr.start_date,
                        lr.end_date,
                        lr.duration_days,
                        lr.status
                    FROM employees e
                    LEFT JOIN leave_requests lr ON e.employee_id = lr.employee_id 
                        AND lr.start_date BETWEEN ? AND ?
                    WHERE e.status = 'active'
                ";
                if (!empty($employee_id)) {
                    $query .= " AND e.employee_id = ?";
                }
                $query .= " ORDER BY e.name, lr.start_date DESC";
                
                $stmt = $conn->prepare($query);
                if (!empty($employee_id)) {
                    $stmt->bind_param("ssi", $start_date, $end_date, $employee_id);
                } else {
                    $stmt->bind_param("ss", $start_date, $end_date);
                }
                break;
                
            default:
                throw new Exception("Invalid report type");
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $report_data[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'report_data' => $report_data,
            'report_type' => $report_type,
            'date_range' => ['start' => $start_date, 'end' => $end_date]
        ]);
        
    } catch (Exception $e) {
        error_log("Error in generateEmployeeReport: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to generate report: ' . $e->getMessage()
        ]);
    }
}

// Utility function to calculate time ago
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    if ($time < 2592000) return floor($time/86400) . 'd ago';
    
    return date('M j, Y', strtotime($datetime));
}

// Create necessary database tables if they don't exist
function createRequiredTables() {
    global $conn;
    
    // Employee leave balance table
    $conn->query("
        CREATE TABLE IF NOT EXISTS employee_leave_balance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            year YEAR NOT NULL,
            casual_leave_balance DECIMAL(4,1) DEFAULT 12.0,
            sick_leave_balance DECIMAL(4,1) DEFAULT 7.0,
            earned_leave_balance DECIMAL(4,1) DEFAULT 21.0,
            comp_off_balance DECIMAL(4,1) DEFAULT 5.0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_employee_year (employee_id, year),
            FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
        )
    ");
}

// Initialize tables on first run
createRequiredTables();
?>
