<?php
// Disable all PHP output errors for clean JSON
ini_set('display_errors', 0);
error_reporting(0);

// Buffer output to catch any unwanted output
ob_start();

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Clear any output that might have been generated
ob_clean();

// Now set JSON header
header('Content-Type: application/json');

// Include database connection
$conn = null;
try {
    // Try different paths for db.php
    if (file_exists('../../db.php')) {
        include '../../db.php';
    } elseif (file_exists(dirname(dirname(__DIR__)) . '/db.php')) {
        include dirname(dirname(__DIR__)) . '/db.php';
    } else {
        throw new Exception('Database configuration file not found');
    }
    
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// Get employee ID from session
$employeeId = $_SESSION['employee_id'] ?? $_SESSION['user_id'] ?? 23; // Default to 23 for testing

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_employee_data':
            getEmployeeData($conn, $employeeId);
            break;
        case 'get_dashboard_stats':
            getDashboardStats($conn, $employeeId);
            break;
        case 'get_today_attendance_status':
            getTodayAttendanceStatus($conn, $employeeId);
            break;
        case 'punch_in':
            punchIn($conn, $employeeId);
            break;
        case 'punch_out':
            punchOut($conn, $employeeId);
            break;
        case 'get_my_attendance':
            getMyAttendance($conn, $employeeId);
            break;
        case 'get_leave_balance':
            getLeaveBalance($conn, $employeeId);
            break;
        case 'get_my_leaves':
            getMyLeaves($conn, $employeeId);
            break;
        case 'apply_leave':
            applyLeave($conn, $employeeId);
            break;
        case 'cancel_leave':
            cancelLeave($conn, $employeeId);
            break;
        case 'get_payroll_data':
            getPayrollData($conn, $employeeId);
            break;
        case 'get_performance_data':
            getPerformanceData($conn, $employeeId);
            break;
        case 'update_profile':
            updateProfile($conn, $employeeId);
            break;
        case 'update_profile_picture':
            updateProfilePicture($conn, $employeeId);
            break;
        case 'export_attendance':
            exportAttendance($conn, $employeeId);
            break;
        case 'download_payslip':
            downloadPayslip($conn, $employeeId);
            break;
        case 'get_leave_details':
            getLeaveDetails($conn, $employeeId);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function getEmployeeData($conn, $employeeId) {
    $query = "SELECT employee_id, name, employee_code, position, monthly_salary, phone, photo, address, bank_name, account_number, ifsc_code, status FROM employees WHERE employee_id = ?";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database prepare error: ' . $conn->error]);
        return;
    }
    
    $stmt->bind_param("i", $employeeId);
    
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Database execute error: ' . $stmt->error]);
        return;
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $employee = $result->fetch_assoc();
        echo json_encode(['success' => true, 'data' => $employee]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Employee not found']);
    }
}

function getDashboardStats($conn, $employeeId) {
    $stats = [
        'attendance_percentage' => 0,
        'working_hours_today' => '0.00',
        'total_leave_balance' => 0,
        'performance_score' => 0
    ];
    
    // Calculate attendance percentage for current month
    $monthStart = date('Y-m-01');
    $today = date('Y-m-d');
    
    $query = "SELECT COUNT(*) as present_days FROM attendance WHERE employee_id = ? AND attendance_date BETWEEN ? AND ? AND status = 'present'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $employeeId, $monthStart, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $present = $result->fetch_assoc()['present_days'];
    
    $totalDays = date('j'); // Current day of month
    $stats['attendance_percentage'] = $totalDays > 0 ? round(($present / $totalDays) * 100) : 0;
    
    // Get today's working hours
    $query = "SELECT work_duration FROM attendance WHERE employee_id = ? AND attendance_date = CURDATE()";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $employeeId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stats['working_hours_today'] = $row['work_duration'] ?? '0.00';
    }
    
    // Get total leave balance
    $query = "SELECT (sick_leave_balance + casual_leave_balance + annual_leave_balance) as total_balance FROM leave_balance WHERE employee_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $employeeId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stats['total_leave_balance'] = $row['total_balance'] ?? 0;
    }
    
    $stats['performance_score'] = 85; // Default value
    
    echo json_encode(['success' => true, 'data' => $stats]);
}

function getTodayAttendanceStatus($conn, $employeeId) {
    $query = "SELECT punch_in_time, punch_out_time FROM attendance WHERE employee_id = ? AND attendance_date = CURDATE()";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $employeeId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $data = [
            'checked_in' => !empty($row['punch_in_time']),
            'checked_out' => !empty($row['punch_out_time']),
            'time_in' => $row['punch_in_time'],
            'time_out' => $row['punch_out_time']
        ];
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => true, 'data' => ['checked_in' => false, 'checked_out' => false]]);
    }
}

function punchIn($conn, $employeeId) {
    $currentTime = date('H:i:s');
    $currentDate = date('Y-m-d');
    
    // Check if already punched in today
    $checkQuery = "SELECT id FROM attendance WHERE employee_id = ? AND attendance_date = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("is", $employeeId, $currentDate);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        // Update existing record
        $query = "UPDATE attendance SET punch_in_time = ?, time_in = ?, status = 'present' WHERE employee_id = ? AND attendance_date = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssis", $currentTime, $currentTime, $employeeId, $currentDate);
    } else {
        // Insert new record
        $query = "INSERT INTO attendance (employee_id, attendance_date, date, punch_in_time, time_in, status, created_at) VALUES (?, ?, ?, ?, ?, 'present', NOW())";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("issss", $employeeId, $currentDate, $currentDate, $currentTime, $currentTime);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Punch in recorded successfully', 'time' => $currentTime]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to record punch in: ' . $stmt->error]);
    }
}

function punchOut($conn, $employeeId) {
    $currentTime = date('H:i:s');
    $currentDate = date('Y-m-d');
    
    // Calculate work duration
    $query = "UPDATE attendance SET punch_out_time = ?, time_out = ?, work_duration = TIME_TO_SEC(TIMEDIFF(?, punch_in_time))/3600 WHERE employee_id = ? AND attendance_date = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssis", $currentTime, $currentTime, $currentTime, $employeeId, $currentDate);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Punch out recorded successfully', 'time' => $currentTime]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to record punch out or no punch in found']);
    }
}

function getMyAttendance($conn, $employeeId) {
    $fromDate = $_POST['from_date'] ?? date('Y-m-01');
    $toDate = $_POST['to_date'] ?? date('Y-m-d');
    
    $query = "SELECT attendance_date, punch_in_time as time_in, punch_out_time as time_out, work_duration as working_hours, '0.00' as overtime_hours, status, remarks, notes FROM attendance WHERE employee_id = ? AND attendance_date BETWEEN ? AND ? ORDER BY attendance_date DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $employeeId, $fromDate, $toDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $attendance = [];
    while ($row = $result->fetch_assoc()) {
        $attendance[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $attendance]);
}

function getLeaveBalance($conn, $employeeId) {
    $query = "SELECT * FROM leave_balance WHERE employee_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $employeeId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $balance = $result->fetch_assoc();
        echo json_encode(['success' => true, 'data' => $balance]);
    } else {
        // Create default leave balance if not exists
        $defaultBalance = [
            'sick_leave_balance' => 12,
            'casual_leave_balance' => 12,
            'annual_leave_balance' => 21,
            'emergency_leave_balance' => 3,
            'maternity_leave_balance' => 0,
            'paternity_leave_balance' => 0
        ];
        echo json_encode(['success' => true, 'data' => $defaultBalance]);
    }
}

function getMyLeaves($conn, $employeeId) {
    $query = "SELECT leave_id, leave_type, from_date, to_date, days_requested, reason, applied_date, status, manager_comments FROM leave_requests WHERE employee_id = ? ORDER BY applied_date DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $employeeId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $leaves = [];
    while ($row = $result->fetch_assoc()) {
        $leaves[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $leaves]);
}

function applyLeave($conn, $employeeId) {
    $leaveType = $_POST['leave_type'] ?? '';
    $fromDate = $_POST['from_date'] ?? '';
    $toDate = $_POST['to_date'] ?? '';
    $reason = $_POST['reason'] ?? '';
    $daysRequested = $_POST['days_requested'] ?? 1;
    
    if (empty($leaveType) || empty($fromDate) || empty($toDate) || empty($reason)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }
    
    $query = "INSERT INTO leave_requests (employee_id, leave_type, from_date, to_date, days_requested, reason, status, applied_date) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isssis", $employeeId, $leaveType, $fromDate, $toDate, $daysRequested, $reason);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Leave application submitted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit leave application: ' . $stmt->error]);
    }
}

function cancelLeave($conn, $employeeId) {
    $leaveId = $_POST['leave_id'] ?? 0;
    
    $query = "UPDATE leave_requests SET status = 'cancelled' WHERE id = ? AND employee_id = ? AND status = 'pending'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $leaveId, $employeeId);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Leave request cancelled successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to cancel leave request or leave not found']);
    }
}

function getPayrollData($conn, $employeeId) {
    $query = "SELECT id, month_year, present_days, absent_days, calculated_pay, calculated_pay as basic_salary, 0 as allowances, 0 as deductions FROM payroll WHERE employee_id = ? ORDER BY month_year DESC LIMIT 12";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $employeeId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $payroll = [];
    while ($row = $result->fetch_assoc()) {
        $payroll[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $payroll]);
}

function getPerformanceData($conn, $employeeId) {
    $query = "SELECT * FROM performance_reviews WHERE employee_id = ? ORDER BY review_date DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $employeeId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $performance = [];
    while ($row = $result->fetch_assoc()) {
        $performance[] = $row;
    }
    
    // If no performance data, create sample data
    if (empty($performance)) {
        $performance = [[
            'review_id' => 1,
            'review_period' => date('Y') . ' Q1',
            'overall_rating' => '4.2/5',
            'goals_achieved' => '85%',
            'areas_improvement' => 'Time management',
            'review_date' => date('Y-m-d')
        ]];
    }
    
    echo json_encode(['success' => true, 'data' => $performance]);
}

function updateProfile($conn, $employeeId) {
    $name = $_POST['name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $bankName = $_POST['bank_name'] ?? '';
    $accountNumber = $_POST['account_number'] ?? '';
    $ifscCode = $_POST['ifsc_code'] ?? '';

    $query = "UPDATE employees SET name = ?, phone = ?, address = ?, bank_name = ?, account_number = ?, ifsc_code = ? WHERE employee_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssssssi", $name, $phone, $address, $bankName, $accountNumber, $ifscCode, $employeeId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update profile: ' . $stmt->error]);
    }
}

function updateProfilePicture($conn, $employeeId) {
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
        return;
    }

    $uploadDir = '../../uploads/employees/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $file = $_FILES['photo'];
    $fileName = 'emp_' . $employeeId . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
    $uploadPath = $uploadDir . $fileName;
    $relativePath = 'uploads/employees/' . $fileName;

    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        $query = "UPDATE employees SET photo = ? WHERE employee_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $relativePath, $employeeId);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Profile picture updated successfully', 'photo_path' => $relativePath]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update database']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
    }
}

function exportAttendance($conn, $employeeId) {
    $fromDate = $_GET['from_date'] ?? date('Y-m-01');
    $toDate = $_GET['to_date'] ?? date('Y-m-d');
    
    $query = "SELECT attendance_date, punch_in_time, punch_out_time, work_duration, status, remarks FROM attendance WHERE employee_id = ? AND attendance_date BETWEEN ? AND ? ORDER BY attendance_date DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $employeeId, $fromDate, $toDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_' . $fromDate . '_' . $toDate . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Punch In', 'Punch Out', 'Work Duration', 'Status', 'Remarks']);
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['attendance_date'],
            $row['punch_in_time'] ?? '',
            $row['punch_out_time'] ?? '',
            $row['work_duration'] ?? '',
            $row['status'],
            $row['remarks'] ?? ''
        ]);
    }
    
    fclose($output);
    exit;
}

function downloadPayslip($conn, $employeeId) {
    $month = $_GET['month'] ?? date('Y-m');
    
    $query = "SELECT p.*, e.name, e.employee_code, e.position FROM payroll p JOIN employees e ON p.employee_id = e.employee_id WHERE p.employee_id = ? AND p.month_year LIKE ?";
    $monthLike = $month . '%';
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $employeeId, $monthLike);
    $stmt->execute();
    $result = $stmt->get_result();
    $payroll = $result->fetch_assoc();
    
    if (!$payroll) {
        echo json_encode(['success' => false, 'message' => 'Payslip not found']);
        return;
    }
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="payslip_' . $month . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Payslip for: ' . $payroll['name'] . ' (' . $payroll['employee_code'] . ')']);
    fputcsv($output, ['Month/Year', $payroll['month_year']]);
    fputcsv($output, ['Position', $payroll['position']]);
    fputcsv($output, ['Present Days', $payroll['present_days']]);
    fputcsv($output, ['Absent Days', $payroll['absent_days']]);
    fputcsv($output, ['Calculated Pay', $payroll['calculated_pay']]);
    
    fclose($output);
    exit;
}

function getLeaveDetails($conn, $employeeId) {
    $leaveId = $_POST['leave_id'] ?? 0;
    
    $query = "SELECT * FROM leave_requests WHERE leave_id = ? AND employee_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $leaveId, $employeeId);
    $stmt->execute();
    $result = $stmt->get_result();
    $leave = $result->fetch_assoc();
    
    if ($leave) {
        echo json_encode(['success' => true, 'data' => $leave]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Leave request not found']);
    }
}
?>
