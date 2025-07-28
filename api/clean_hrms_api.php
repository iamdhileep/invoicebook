<?php
/**
 * Clean HRMS API for Employee Management
 */

// Start output buffering to catch any unwanted output
ob_start();

// Enable error reporting for debugging but don't display
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Clean any previous output
ob_clean();

header('Content-Type: application/json');
session_start();

// Include database connection
$dbPath = dirname(__FILE__) . '/../db.php';
if (!file_exists($dbPath)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Database connection file not found']);
    exit;
}

// Start a new output buffer for database inclusion
ob_start();
include $dbPath;
ob_end_clean(); // Discard any output from db.php

// Check database connection
if (!$conn) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$module = $_POST['module'] ?? $_GET['module'] ?? '';

try {
    if ($module === 'hr') {
        switch ($action) {
            case 'get_employees':
            case 'get_all_employees':
                hrGetAllEmployees($conn);
                break;
            case 'get_leave_requests':
            case 'get_all_leave_requests':
                hrGetAllLeaveRequests($conn);
                break;
            case 'add_employee':
                hrAddEmployee($conn);
                break;
            default:
                throw new Exception('Unknown HR action: ' . $action);
        }
    } elseif ($module === 'manager') {
        switch ($action) {
            case 'get_team_members':
                managerGetTeamMembers($conn);
                break;
            case 'get_pending_leaves':
                managerGetPendingLeaves($conn);
                break;
            case 'get_team_schedule':
                managerGetTeamSchedule($conn);
                break;
            case 'update_leave_status':
                managerUpdateLeaveStatus($conn);
                break;
            case 'bulk_approve_leaves':
                managerBulkApproveLeaves($conn);
                break;
            default:
                throw new Exception('Unknown Manager action: ' . $action);
        }
    } else {
        throw new Exception('Unknown module: ' . $module);
    }
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// HR Functions
function hrGetAllEmployees($conn) {
    try {
        $query = $conn->prepare("SELECT 
                                employee_id,
                                COALESCE(first_name, SUBSTRING_INDEX(name, ' ', 1)) as first_name,
                                COALESCE(last_name, SUBSTRING_INDEX(name, ' ', -1)) as last_name,
                                name,
                                email,
                                employee_code,
                                position,
                                COALESCE(department_name, 'General') as department_name,
                                hire_date,
                                status,
                                phone,
                                monthly_salary
                                FROM employees 
                                WHERE status = 'active' 
                                ORDER BY name");
        $query->execute();
        $result = $query->get_result();
        
        $employees = [];
        while ($row = $result->fetch_assoc()) {
            // Ensure we have first_name and last_name
            if (empty($row['first_name']) && !empty($row['name'])) {
                $nameParts = explode(' ', $row['name'], 2);
                $row['first_name'] = $nameParts[0];
                $row['last_name'] = isset($nameParts[1]) ? $nameParts[1] : '';
            }
            
            $employees[] = $row;
        }
        
        ob_clean();
        echo json_encode(['success' => true, 'data' => $employees]);
    } catch (Exception $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function hrGetAllLeaveRequests($conn) {
    try {
        // Check if leave_requests table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'leave_requests'");
        if ($tableCheck->num_rows == 0) {
            // Create the table if it doesn't exist
            $createTable = "CREATE TABLE leave_requests (
                id int(11) AUTO_INCREMENT PRIMARY KEY,
                employee_id int(11) NOT NULL,
                leave_type varchar(50) NOT NULL,
                start_date date NOT NULL,
                end_date date NOT NULL,
                days_requested int(11) NOT NULL,
                reason text,
                status enum('pending','approved','rejected') DEFAULT 'pending',
                applied_date timestamp DEFAULT CURRENT_TIMESTAMP,
                approved_by int(11) NULL,
                approved_date timestamp NULL,
                comments text NULL
            )";
            $conn->query($createTable);
        }
        
        $query = $conn->prepare("SELECT lr.*, e.name as employee_name 
                                FROM leave_requests lr 
                                LEFT JOIN employees e ON lr.employee_id = e.employee_id 
                                ORDER BY lr.applied_date DESC");
        $query->execute();
        $result = $query->get_result();
        
        $leaves = [];
        while ($row = $result->fetch_assoc()) {
            $leaves[] = $row;
        }
        
        ob_clean();
        echo json_encode(['success' => true, 'data' => $leaves]);
    } catch (Exception $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function hrAddEmployee($conn) {
    try {
        $name = $_POST['name'] ?? '';
        $employee_code = $_POST['employee_code'] ?? '';
        $position = $_POST['position'] ?? '';
        $monthly_salary = $_POST['monthly_salary'] ?? 0;
        $phone = $_POST['phone'] ?? '';
        $address = $_POST['address'] ?? '';
        
        if (!$name || !$employee_code || !$position) {
            throw new Exception('Name, employee code, and position are required');
        }

        $query = $conn->prepare("INSERT INTO employees (name, employee_code, position, monthly_salary, phone, address, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
        $query->bind_param("sssdss", $name, $employee_code, $position, $monthly_salary, $phone, $address);
        
        if ($query->execute()) {
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Employee added successfully', 'employee_id' => $conn->insert_id]);
        } else {
            throw new Exception('Failed to add employee');
        }
    } catch (Exception $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Manager Functions
function managerGetTeamMembers($conn) {
    try {
        // For demo purposes, get all active employees as team members
        $query = $conn->prepare("SELECT 
                                employee_id,
                                COALESCE(first_name, SUBSTRING_INDEX(name, ' ', 1)) as first_name,
                                COALESCE(last_name, SUBSTRING_INDEX(name, ' ', -1)) as last_name,
                                name,
                                email,
                                employee_code,
                                position,
                                COALESCE(department_name, 'General') as department_name,
                                hire_date,
                                status,
                                phone,
                                monthly_salary,
                                COALESCE(joining_date, hire_date, CURDATE()) as last_activity,
                                FLOOR(RAND() * 40) + 60 as performance
                                FROM employees 
                                WHERE status = 'active' 
                                ORDER BY name");
        $query->execute();
        $result = $query->get_result();
        
        $team = [];
        while ($row = $result->fetch_assoc()) {
            // Ensure we have first_name and last_name
            if (empty($row['first_name']) && !empty($row['name'])) {
                $nameParts = explode(' ', $row['name'], 2);
                $row['first_name'] = $nameParts[0];
                $row['last_name'] = isset($nameParts[1]) ? $nameParts[1] : '';
            }
            
            $team[] = $row;
        }
        
        ob_clean();
        echo json_encode(['success' => true, 'data' => $team]);
    } catch (Exception $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function managerGetPendingLeaves($conn) {
    try {
        // Check if leave_requests table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'leave_requests'");
        if ($tableCheck->num_rows == 0) {
            // Create the table if it doesn't exist
            $createTable = "CREATE TABLE leave_requests (
                id int(11) AUTO_INCREMENT PRIMARY KEY,
                employee_id int(11) NOT NULL,
                leave_type varchar(50) NOT NULL,
                start_date date NOT NULL,
                end_date date NOT NULL,
                days_requested int(11) NOT NULL,
                reason text,
                status enum('pending','approved','rejected') DEFAULT 'pending',
                applied_date timestamp DEFAULT CURRENT_TIMESTAMP,
                approved_by int(11) NULL,
                approved_date timestamp NULL,
                comments text NULL
            )";
            $conn->query($createTable);
        }
        
        $query = $conn->prepare("SELECT lr.*, e.name as employee_name 
                                FROM leave_requests lr 
                                LEFT JOIN employees e ON lr.employee_id = e.employee_id 
                                WHERE lr.status = 'pending'
                                ORDER BY lr.applied_date DESC");
        $query->execute();
        $result = $query->get_result();
        
        $leaves = [];
        while ($row = $result->fetch_assoc()) {
            $leaves[] = $row;
        }
        
        ob_clean();
        echo json_encode(['success' => true, 'data' => $leaves]);
    } catch (Exception $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function managerGetTeamSchedule($conn) {
    try {
        $date = $_POST['date'] ?? date('Y-m-d');
        
        // Get team schedule for the specified date
        $query = $conn->prepare("SELECT 
                                e.employee_id,
                                e.name,
                                e.position,
                                COALESCE(a.check_in, 'Not Checked In') as check_in,
                                COALESCE(a.check_out, 'Not Checked Out') as check_out,
                                CASE 
                                    WHEN a.status = 'present' THEN 'Present'
                                    WHEN a.status = 'absent' THEN 'Absent'
                                    WHEN a.status = 'late' THEN 'Late'
                                    WHEN a.status = 'half_day' THEN 'Half Day'
                                    ELSE 'Unknown'
                                END as status,
                                e.shift_timing as shift
                                FROM employees e
                                LEFT JOIN attendance a ON e.employee_id = a.employee_id AND DATE(a.check_in) = ?
                                WHERE e.status = 'active'
                                ORDER BY e.name");
        $query->bind_param("s", $date);
        $query->execute();
        $result = $query->get_result();
        
        $schedule = [];
        while ($row = $result->fetch_assoc()) {
            $schedule[] = $row;
        }
        
        ob_clean();
        echo json_encode(['success' => true, 'data' => $schedule]);
    } catch (Exception $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function managerUpdateLeaveStatus($conn) {
    try {
        $leave_id = $_POST['leave_id'] ?? 0;
        $status = $_POST['status'] ?? '';
        
        if (!$leave_id || !in_array($status, ['approved', 'rejected'])) {
            throw new Exception('Invalid leave ID or status');
        }
        
        // Update leave status
        $query = $conn->prepare("UPDATE leave_requests SET status = ?, approved_date = NOW() WHERE id = ?");
        $query->bind_param("si", $status, $leave_id);
        
        if ($query->execute()) {
            ob_clean();
            echo json_encode(['success' => true, 'message' => "Leave request $status successfully"]);
        } else {
            throw new Exception('Failed to update leave status');
        }
    } catch (Exception $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function managerBulkApproveLeaves($conn) {
    try {
        $leave_ids = $_POST['leave_ids'] ?? [];
        
        if (empty($leave_ids) || !is_array($leave_ids)) {
            throw new Exception('No leave requests selected');
        }
        
        // Create placeholders for the IN clause
        $placeholders = str_repeat('?,', count($leave_ids) - 1) . '?';
        $query = $conn->prepare("UPDATE leave_requests SET status = 'approved', approved_date = NOW() WHERE id IN ($placeholders)");
        
        // Bind parameters
        $types = str_repeat('i', count($leave_ids));
        $query->bind_param($types, ...$leave_ids);
        
        if ($query->execute()) {
            $affected_rows = $query->affected_rows;
            ob_clean();
            echo json_encode(['success' => true, 'message' => "$affected_rows leave requests approved successfully"]);
        } else {
            throw new Exception('Failed to approve leave requests');
        }
    } catch (Exception $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

$conn->close();
?>
