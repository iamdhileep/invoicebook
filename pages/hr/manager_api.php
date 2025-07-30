<?php
include_once '../../db.php';
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in (basic check)
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_dashboard_stats':
            getDashboardStats($conn);
            break;
        case 'get_today_attendance':
            getTodayAttendance($conn);
            break;
        case 'get_notifications':
            getNotifications($conn);
            break;
        case 'get_team_members':
            getTeamMembers($conn);
            break;
        case 'add_team_member':
            addTeamMember($conn);
            break;
        case 'get_leave_requests':
            getLeaveRequests($conn);
            break;
        case 'approve_leave':
            approveLeave($conn);
            break;
        case 'reject_leave':
            rejectLeave($conn);
            break;
        case 'get_attendance_data':
            getAttendanceData($conn);
            break;
        case 'export_team_data':
            exportTeamData($conn);
            break;
        case 'export_leave_data':
            exportLeaveData($conn);
            break;
        case 'export_attendance_data':
            exportAttendanceData($conn);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function getDashboardStats($conn) {
    // Get total team members (assuming manager manages all employees for now)
    $totalQuery = "SELECT COUNT(*) as total FROM employees WHERE status = 'active'";
    $totalResult = $conn->query($totalQuery);
    $totalMembers = $totalResult->fetch_assoc()['total'] ?? 0;

    // Get present today
    $presentQuery = "SELECT COUNT(*) as present FROM attendance a 
                     JOIN employees e ON a.employee_id = e.employee_id 
                     WHERE a.attendance_date = CURDATE() AND a.status IN ('Present', 'Late') AND e.status = 'active'";
    $presentResult = $conn->query($presentQuery);
    $presentToday = $presentResult->fetch_assoc()['present'] ?? 0;

    // Get pending leaves
    $pendingQuery = "SELECT COUNT(*) as pending FROM leave_requests lr 
                     JOIN employees e ON lr.employee_id = e.employee_id 
                     WHERE lr.status = 'pending' AND e.status = 'active'";
    $pendingResult = $conn->query($pendingQuery);
    $pendingLeaves = $pendingResult->fetch_assoc()['pending'] ?? 0;

    // Calculate team performance (simplified as attendance percentage)
    $performanceQuery = "SELECT 
                         AVG(CASE WHEN a.status IN ('Present', 'Late') THEN 100 ELSE 0 END) as performance
                         FROM attendance a 
                         JOIN employees e ON a.employee_id = e.employee_id 
                         WHERE a.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
                         AND e.status = 'active'";
    $performanceResult = $conn->query($performanceQuery);
    $teamPerformance = round($performanceResult->fetch_assoc()['performance'] ?? 0);

    echo json_encode([
        'success' => true,
        'data' => [
            'total_team_members' => $totalMembers,
            'present_today' => $presentToday,
            'pending_leaves' => $pendingLeaves,
            'team_performance' => $teamPerformance
        ]
    ]);
}

function getTodayAttendance($conn) {
    $query = "SELECT e.name, a.time_in, a.time_out, a.status, a.working_hours
              FROM employees e
              LEFT JOIN attendance a ON e.employee_id = a.employee_id AND a.attendance_date = CURDATE()
              WHERE e.status = 'active'
              ORDER BY e.name";
    
    $result = $conn->query($query);
    $attendance = [];
    
    while ($row = $result->fetch_assoc()) {
        $attendance[] = [
            'name' => $row['name'],
            'time_in' => $row['time_in'],
            'time_out' => $row['time_out'],
            'status' => $row['status'] ?? 'Absent',
            'working_hours' => $row['working_hours'] ?? '0.00'
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $attendance]);
}

function getNotifications($conn) {
    // Generate sample notifications (in real app, these would come from database)
    $notifications = [
        [
            'type' => 'info',
            'message' => 'Team meeting scheduled for 2:00 PM today',
            'time' => date('H:i')
        ],
        [
            'type' => 'warning',
            'message' => '3 leave requests pending approval',
            'time' => date('H:i', strtotime('-1 hour'))
        ],
        [
            'type' => 'success',
            'message' => 'Monthly report submitted successfully',
            'time' => date('H:i', strtotime('-2 hours'))
        ]
    ];
    
    echo json_encode(['success' => true, 'data' => $notifications]);
}

function getTeamMembers($conn) {
    $query = "SELECT employee_id, employee_code, name, email, phone, position, department, salary, hire_date, status
              FROM employees 
              WHERE status = 'active'
              ORDER BY name";
    
    $result = $conn->query($query);
    $members = [];
    
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $members]);
}

function addTeamMember($conn) {
    $employeeCode = $_POST['employee_code'] ?? '';
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $position = $_POST['position'] ?? '';
    $department = $_POST['department'] ?? '';
    $salary = $_POST['salary'] ?? 0;
    $hireDate = $_POST['hire_date'] ?? '';

    if (empty($employeeCode) || empty($name) || empty($email) || empty($position) || empty($department)) {
        echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
        return;
    }

    // Check if employee code or email already exists
    $checkQuery = "SELECT employee_id FROM employees WHERE employee_code = ? OR email = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("ss", $employeeCode, $email);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Employee code or email already exists']);
        return;
    }

    $query = "INSERT INTO employees (employee_code, name, email, phone, position, department, salary, hire_date, status) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssssssds", $employeeCode, $name, $email, $phone, $position, $department, $salary, $hireDate);
    
    if ($stmt->execute()) {
        $employeeId = $conn->insert_id;
        
        // Create leave balance for new employee
        $currentYear = date('Y');
        $leaveBalanceQuery = "INSERT INTO leave_balance (employee_id, year) VALUES (?, ?)";
        $leaveStmt = $conn->prepare($leaveBalanceQuery);
        $leaveStmt->bind_param("ii", $employeeId, $currentYear);
        $leaveStmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Team member added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error adding team member']);
    }
}

function getLeaveRequests($conn) {
    $query = "SELECT lr.leave_id, lr.employee_id, e.name as employee_name, lr.leave_type, 
                     lr.from_date, lr.to_date, lr.days_requested, lr.reason, lr.status, 
                     lr.applied_date, lr.approver_comments
              FROM leave_requests lr
              JOIN employees e ON lr.employee_id = e.employee_id
              WHERE e.status = 'active'
              ORDER BY lr.applied_date DESC";
    
    $result = $conn->query($query);
    $requests = [];
    
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $requests]);
}

function approveLeave($conn) {
    $leaveId = $_POST['leave_id'] ?? 0;
    $comments = $_POST['comments'] ?? '';
    $approverId = $_SESSION['user_id'] ?? 1; // Default to user 1 for now

    if (!$leaveId) {
        echo json_encode(['success' => false, 'message' => 'Leave ID is required']);
        return;
    }

    $query = "UPDATE leave_requests 
              SET status = 'approved', approved_by = ?, approved_date = NOW(), approver_comments = ?
              WHERE leave_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isi", $approverId, $comments, $leaveId);
    
    if ($stmt->execute()) {
        // Update leave balance
        $leaveDetailsQuery = "SELECT employee_id, leave_type, days_requested FROM leave_requests WHERE leave_id = ?";
        $detailsStmt = $conn->prepare($leaveDetailsQuery);
        $detailsStmt->bind_param("i", $leaveId);
        $detailsStmt->execute();
        $leaveDetails = $detailsStmt->get_result()->fetch_assoc();
        
        if ($leaveDetails) {
            $balanceColumn = $leaveDetails['leave_type'] . '_leave_balance';
            $updateBalanceQuery = "UPDATE leave_balance 
                                   SET $balanceColumn = $balanceColumn - ? 
                                   WHERE employee_id = ? AND year = YEAR(CURDATE())";
            $balanceStmt = $conn->prepare($updateBalanceQuery);
            $balanceStmt->bind_param("ii", $leaveDetails['days_requested'], $leaveDetails['employee_id']);
            $balanceStmt->execute();
        }
        
        echo json_encode(['success' => true, 'message' => 'Leave request approved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error approving leave request']);
    }
}

function rejectLeave($conn) {
    $leaveId = $_POST['leave_id'] ?? 0;
    $comments = $_POST['comments'] ?? '';
    $approverId = $_SESSION['user_id'] ?? 1; // Default to user 1 for now

    if (!$leaveId) {
        echo json_encode(['success' => false, 'message' => 'Leave ID is required']);
        return;
    }

    $query = "UPDATE leave_requests 
              SET status = 'rejected', approved_by = ?, approved_date = NOW(), approver_comments = ?
              WHERE leave_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isi", $approverId, $comments, $leaveId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Leave request rejected']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error rejecting leave request']);
    }
}

function getAttendanceData($conn) {
    $date = $_POST['date'] ?? date('Y-m-d');
    
    $query = "SELECT a.attendance_id, a.employee_id, e.name as employee_name, a.attendance_date,
                     a.time_in, a.time_out, a.working_hours, a.overtime_hours, a.status, a.notes
              FROM attendance a
              JOIN employees e ON a.employee_id = e.employee_id
              WHERE a.attendance_date = ? AND e.status = 'active'
              ORDER BY e.name";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $attendance = [];
    while ($row = $result->fetch_assoc()) {
        $attendance[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $attendance]);
}

function exportTeamData($conn) {
    $query = "SELECT employee_code, name, email, phone, position, department, salary, hire_date, status
              FROM employees 
              WHERE status = 'active'
              ORDER BY name";
    
    $result = $conn->query($query);
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="team_members_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Employee Code', 'Name', 'Email', 'Phone', 'Position', 'Department', 'Salary', 'Hire Date', 'Status']);
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
    
    fclose($output);
}

function exportLeaveData($conn) {
    $query = "SELECT e.name, lr.leave_type, lr.from_date, lr.to_date, lr.days_requested, 
                     lr.reason, lr.status, lr.applied_date
              FROM leave_requests lr
              JOIN employees e ON lr.employee_id = e.employee_id
              WHERE e.status = 'active'
              ORDER BY lr.applied_date DESC";
    
    $result = $conn->query($query);
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="leave_requests_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Employee Name', 'Leave Type', 'From Date', 'To Date', 'Days Requested', 'Reason', 'Status', 'Applied Date']);
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
    
    fclose($output);
}

function exportAttendanceData($conn) {
    $date = $_GET['date'] ?? date('Y-m-d');
    
    $query = "SELECT e.name, a.attendance_date, a.time_in, a.time_out, 
                     a.working_hours, a.overtime_hours, a.status
              FROM attendance a
              JOIN employees e ON a.employee_id = e.employee_id
              WHERE a.attendance_date = ? AND e.status = 'active'
              ORDER BY e.name";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_' . $date . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Employee Name', 'Date', 'Check In', 'Check Out', 'Working Hours', 'Overtime Hours', 'Status']);
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
    
    fclose($output);
}
?>
