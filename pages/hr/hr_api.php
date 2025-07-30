<?php
session_start();
header('Content-Type: application/json');
include '../../db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check authentication
if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_employees':
            getEmployees($conn);
            break;
            
        case 'add_employee':
            addEmployee($conn);
            break;
            
        case 'delete_employee':
            deleteEmployee($conn);
            break;
            
        case 'get_leave_requests':
            getLeaveRequests($conn);
            break;
            
        case 'process_leave':
            processLeaveRequest($conn);
            break;
            
        case 'get_attendance':
            getAttendance($conn);
            break;
            
        case 'get_payroll':
            getPayroll($conn);
            break;
            
        case 'generate_payroll':
            generatePayroll($conn);
            break;
            
        case 'attendance_report':
            generateAttendanceReport($conn);
            break;
            
        case 'leave_report':
            generateLeaveReport($conn);
            break;
            
        case 'export_employees':
            exportEmployees($conn);
            break;
            
        case 'export_leaves':
            exportLeaves($conn);
            break;
            
        case 'export_payroll':
            exportPayroll($conn);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

// Employee Management Functions
function getEmployees($conn) {
    try {
        $query = "SELECT * FROM employees ORDER BY name ASC";
        $result = $conn->query($query);
        
        $employees = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $employees[] = $row;
            }
        }
        
        echo json_encode(['success' => true, 'employees' => $employees]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function addEmployee($conn) {
    try {
        $name = trim($_POST['name'] ?? '');
        $employee_code = trim($_POST['employee_code'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $salary = floatval($_POST['salary'] ?? 0);
        $hire_date = $_POST['hire_date'] ?? null;
        $address = trim($_POST['address'] ?? '');
        $emergency_contact = trim($_POST['emergency_contact'] ?? '');
        $emergency_phone = trim($_POST['emergency_phone'] ?? '');
        
        if (empty($name) || empty($employee_code)) {
            throw new Exception('Name and Employee Code are required');
        }
        
        // Check if employee code already exists
        $checkQuery = "SELECT employee_id FROM employees WHERE employee_code = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param('s', $employee_code);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            throw new Exception('Employee code already exists');
        }
        
        $query = "INSERT INTO employees (name, employee_code, email, phone, department, position, salary, hire_date, address, emergency_contact, emergency_phone, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ssssssdssss', $name, $employee_code, $email, $phone, $department, $position, $salary, $hire_date, $address, $emergency_contact, $emergency_phone);
        
        if ($stmt->execute()) {
            $employee_id = $conn->insert_id;
            
            // Create initial leave balance for the employee
            $currentYear = date('Y');
            $leaveBalanceQuery = "INSERT INTO leave_balance (employee_id, year) VALUES (?, ?)";
            $leaveStmt = $conn->prepare($leaveBalanceQuery);
            $leaveStmt->bind_param('ii', $employee_id, $currentYear);
            $leaveStmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Employee added successfully', 'employee_id' => $employee_id]);
        } else {
            throw new Exception('Failed to add employee');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteEmployee($conn) {
    try {
        $employee_id = intval($_POST['employee_id'] ?? 0);
        
        if ($employee_id <= 0) {
            throw new Exception('Invalid employee ID');
        }
        
        // Soft delete by setting status to inactive
        $query = "UPDATE employees SET status = 'inactive' WHERE employee_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $employee_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Employee deleted successfully']);
        } else {
            throw new Exception('Failed to delete employee');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Leave Management Functions
function getLeaveRequests($conn) {
    try {
        $status = $_POST['status'] ?? '';
        
        $query = "SELECT lr.*, e.name as employee_name, e.employee_code 
                  FROM leave_requests lr 
                  JOIN employees e ON lr.employee_id = e.employee_id";
        
        if (!empty($status)) {
            $query .= " WHERE lr.status = ?";
        }
        
        $query .= " ORDER BY lr.applied_date DESC";
        
        $stmt = $conn->prepare($query);
        if (!empty($status)) {
            $stmt->bind_param('s', $status);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $leaves = [];
        while ($row = $result->fetch_assoc()) {
            $leaves[] = $row;
        }
        
        echo json_encode(['success' => true, 'leaves' => $leaves]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function processLeaveRequest($conn) {
    try {
        $leave_id = intval($_POST['leave_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $comments = trim($_POST['comments'] ?? '');
        $approved_by = $_SESSION['employee_id'] ?? 1; // Default admin ID
        
        if ($leave_id <= 0 || !in_array($status, ['approved', 'rejected'])) {
            throw new Exception('Invalid leave request or status');
        }
        
        $query = "UPDATE leave_requests SET status = ?, approved_by = ?, approved_date = NOW(), approver_comments = ? WHERE leave_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('sisi', $status, $approved_by, $comments, $leave_id);
        
        if ($stmt->execute()) {
            // If approved, update leave balance
            if ($status === 'approved') {
                updateLeaveBalance($conn, $leave_id);
            }
            
            echo json_encode(['success' => true, 'message' => 'Leave request processed successfully']);
        } else {
            throw new Exception('Failed to process leave request');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateLeaveBalance($conn, $leave_id) {
    try {
        // Get leave request details
        $query = "SELECT employee_id, leave_type, days_requested FROM leave_requests WHERE leave_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $leave_id);
        $stmt->execute();
        $leave = $stmt->get_result()->fetch_assoc();
        
        if ($leave) {
            $year = date('Y');
            $column = $leave['leave_type'] . '_leave_balance';
            
            // Update leave balance
            $updateQuery = "UPDATE leave_balance SET $column = $column - ? WHERE employee_id = ? AND year = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param('iii', $leave['days_requested'], $leave['employee_id'], $year);
            $updateStmt->execute();
        }
    } catch (Exception $e) {
        error_log("Error updating leave balance: " . $e->getMessage());
    }
}

// Attendance Functions
function getAttendance($conn) {
    try {
        $date = $_POST['date'] ?? date('Y-m-d');
        
        $query = "SELECT a.*, e.name as employee_name, e.employee_code,
                         CASE 
                             WHEN a.time_in IS NOT NULL AND a.time_out IS NOT NULL 
                             THEN ROUND(TIME_TO_SEC(TIMEDIFF(a.time_out, a.time_in)) / 3600, 2)
                             ELSE 0 
                         END as working_hours
                  FROM employees e
                  LEFT JOIN attendance a ON e.employee_id = a.employee_id AND a.attendance_date = ?
                  WHERE e.status = 'active'
                  ORDER BY e.name";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $attendance = [];
        while ($row = $result->fetch_assoc()) {
            // Set default values if no attendance record
            if (!$row['attendance_id']) {
                $row['status'] = 'Absent';
                $row['time_in'] = '';
                $row['time_out'] = '';
                $row['working_hours'] = 0;
                $row['attendance_date'] = $date;
            }
            $attendance[] = $row;
        }
        
        echo json_encode(['success' => true, 'attendance' => $attendance]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Payroll Functions
function getPayroll($conn) {
    try {
        $month = intval($_POST['month'] ?? date('n'));
        $year = intval($_POST['year'] ?? date('Y'));
        
        $monthYear = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT);
        
        $query = "SELECT p.*, e.name as employee_name, e.employee_code, e.monthly_salary,
                         p.calculated_pay as basic_salary, p.calculated_pay as gross_salary,
                         0 as total_deductions, p.calculated_pay as net_salary,
                         'processed' as status
                  FROM employees e
                  LEFT JOIN payroll p ON e.employee_id = p.employee_id AND p.month_year = ?
                  WHERE e.status = 'active'
                  ORDER BY e.name";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $monthYear);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $payroll = [];
        while ($row = $result->fetch_assoc()) {
            // Set default values if no payroll record
            if (!$row['id']) {
                $row['basic_salary'] = $row['monthly_salary'] ?? 0;
                $row['gross_salary'] = $row['monthly_salary'] ?? 0;
                $row['total_deductions'] = 0;
                $row['net_salary'] = $row['monthly_salary'] ?? 0;
                $row['status'] = 'draft';
                $row['present_days'] = 0;
                $row['absent_days'] = 0;
            }
            $payroll[] = $row;
        }
        
        echo json_encode(['success' => true, 'payroll' => $payroll]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function generatePayroll($conn) {
    try {
        $month = intval($_POST['month'] ?? date('n'));
        $year = intval($_POST['year'] ?? date('Y'));
        
        $startDate = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
        $endDate = date('Y-m-t', strtotime($startDate));
        
        // Get all active employees
        $employeesQuery = "SELECT * FROM employees WHERE status = 'active'";
        $employeesResult = $conn->query($employeesQuery);
        
        $generated = 0;
        while ($employee = $employeesResult->fetch_assoc()) {
            $employee_id = $employee['employee_id'];
            $basic_salary = floatval($employee['salary']);
            
            // Check if payroll already exists for this month
            $monthYear = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT);
            $checkQuery = "SELECT id FROM payroll WHERE employee_id = ? AND month_year = ?";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param('is', $employee_id, $monthYear);
            $checkStmt->execute();
            
            if ($checkStmt->get_result()->num_rows > 0) {
                continue; // Skip if already exists
            }
            
            // Calculate working days and present days
            $workingDays = getWorkingDays($startDate, $endDate);
            $presentDays = getPresentDays($conn, $employee_id, $startDate, $endDate);
            
            // Calculate salary components
            $dailySalary = $basic_salary / $workingDays;
            $earnedSalary = $dailySalary * $presentDays;
            
            $allowances = $earnedSalary * 0.2; // 20% allowances
            $overtime_pay = 0; // Can be calculated based on overtime hours
            $bonus = 0;
            
            $gross_salary = $earnedSalary + $allowances + $overtime_pay + $bonus;
            
            // Calculate deductions
            $tax_deduction = $gross_salary * 0.1; // 10% tax
            $pf_deduction = $basic_salary * 0.12; // 12% PF
            $other_deductions = 0;
            
            $total_deductions = $tax_deduction + $pf_deduction + $other_deductions;
            $net_salary = $gross_salary - $total_deductions;
            
            // Insert payroll record using existing table structure
            $monthYear = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT);
            $absentDays = $workingDays - $presentDays;
            
            $insertQuery = "INSERT INTO payroll (employee_id, month_year, present_days, absent_days, calculated_pay) VALUES (?, ?, ?, ?, ?)";
            
            $insertStmt = $conn->prepare($insertQuery);
            $insertStmt->bind_param('isiid', $employee_id, $monthYear, $presentDays, $absentDays, $net_salary);
            
            if ($insertStmt->execute()) {
                $generated++;
            }
        }
        
        echo json_encode(['success' => true, 'message' => "Payroll generated for $generated employees"]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getWorkingDays($startDate, $endDate) {
    $workingDays = 0;
    $current = strtotime($startDate);
    $end = strtotime($endDate);
    
    while ($current <= $end) {
        $dayOfWeek = date('N', $current);
        if ($dayOfWeek < 6) { // Monday to Friday
            $workingDays++;
        }
        $current = strtotime('+1 day', $current);
    }
    
    return $workingDays;
}

function getPresentDays($conn, $employee_id, $startDate, $endDate) {
    $query = "SELECT COUNT(*) as present_days FROM attendance WHERE employee_id = ? AND attendance_date BETWEEN ? AND ? AND status IN ('Present', 'Late')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iss', $employee_id, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return intval($result['present_days'] ?? 0);
}

// Report Functions
function generateAttendanceReport($conn) {
    try {
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        
        if (empty($start_date) || empty($end_date)) {
            throw new Exception('Start date and end date are required');
        }
        
        $query = "SELECT e.name as employee_name, e.employee_code, e.department,
                         COUNT(CASE WHEN a.status = 'Present' THEN 1 END) as present_days,
                         COUNT(CASE WHEN a.status = 'Absent' THEN 1 END) as absent_days,
                         COUNT(CASE WHEN a.status = 'Late' THEN 1 END) as late_days,
                         COUNT(CASE WHEN a.status = 'Half Day' THEN 1 END) as half_days,
                         ROUND(AVG(CASE WHEN a.working_hours > 0 THEN a.working_hours END), 2) as avg_working_hours
                  FROM employees e
                  LEFT JOIN attendance a ON e.employee_id = a.employee_id 
                      AND a.attendance_date BETWEEN ? AND ?
                  WHERE e.status = 'active'
                  GROUP BY e.employee_id
                  ORDER BY e.name";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $report = [];
        while ($row = $result->fetch_assoc()) {
            $report[] = $row;
        }
        
        echo json_encode(['success' => true, 'report' => $report]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function generateLeaveReport($conn) {
    try {
        $department = $_POST['department'] ?? '';
        
        $query = "SELECT e.name as employee_name, e.employee_code, e.department,
                         COUNT(CASE WHEN lr.leave_type = 'sick' AND lr.status = 'approved' THEN 1 END) as sick_leaves,
                         COUNT(CASE WHEN lr.leave_type = 'casual' AND lr.status = 'approved' THEN 1 END) as casual_leaves,
                         COUNT(CASE WHEN lr.leave_type = 'annual' AND lr.status = 'approved' THEN 1 END) as annual_leaves,
                         COUNT(CASE WHEN lr.status = 'approved' THEN 1 END) as total_approved_leaves,
                         COUNT(CASE WHEN lr.status = 'pending' THEN 1 END) as pending_leaves
                  FROM employees e
                  LEFT JOIN leave_requests lr ON e.employee_id = lr.employee_id
                  WHERE e.status = 'active'";
        
        if (!empty($department)) {
            $query .= " AND e.department = ?";
        }
        
        $query .= " GROUP BY e.employee_id ORDER BY e.name";
        
        $stmt = $conn->prepare($query);
        if (!empty($department)) {
            $stmt->bind_param('s', $department);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $report = [];
        while ($row = $result->fetch_assoc()) {
            $report[] = $row;
        }
        
        echo json_encode(['success' => true, 'report' => $report]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Export Functions
function exportEmployees($conn) {
    try {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="employees_' . date('Y-m-d') . '.csv"');
        
        $query = "SELECT name, employee_code, email, phone, department, position, salary, hire_date, status FROM employees ORDER BY name";
        $result = $conn->query($query);
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Name', 'Employee Code', 'Email', 'Phone', 'Department', 'Position', 'Salary', 'Hire Date', 'Status']);
        
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, $row);
        }
        
        fclose($output);
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}

function exportLeaves($conn) {
    try {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="leave_requests_' . date('Y-m-d') . '.csv"');
        
        $query = "SELECT e.name as employee_name, e.employee_code, lr.leave_type, lr.from_date, lr.to_date, lr.days_requested, lr.reason, lr.status, lr.applied_date 
                  FROM leave_requests lr 
                  JOIN employees e ON lr.employee_id = e.employee_id 
                  ORDER BY lr.applied_date DESC";
        $result = $conn->query($query);
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Employee Name', 'Employee Code', 'Leave Type', 'From Date', 'To Date', 'Days', 'Reason', 'Status', 'Applied Date']);
        
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, $row);
        }
        
        fclose($output);
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}

function exportPayroll($conn) {
    try {
        $month = intval($_GET['month'] ?? date('n'));
        $year = intval($_GET['year'] ?? date('Y'));
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="payroll_' . $year . '_' . str_pad($month, 2, '0', STR_PAD_LEFT) . '.csv"');
        
        $monthYear = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT);
        
        $query = "SELECT e.name as employee_name, e.employee_code, p.calculated_pay as basic_salary, 
                         0 as allowances, p.calculated_pay as gross_salary, 0 as tax_deduction, 
                         0 as pf_deduction, 0 as total_deductions, p.calculated_pay as net_salary, 
                         30 as working_days, p.present_days, 'processed' as status
                  FROM payroll p
                  JOIN employees e ON p.employee_id = e.employee_id
                  WHERE p.month_year = ?
                  ORDER BY e.name";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $monthYear);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Employee Name', 'Employee Code', 'Basic Salary', 'Allowances', 'Gross Salary', 'Tax Deduction', 'PF Deduction', 'Total Deductions', 'Net Salary', 'Working Days', 'Present Days', 'Status']);
        
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, $row);
        }
        
        fclose($output);
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}
?>
