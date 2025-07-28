<?php
/**
 * Global HRMS API Controller
 * Handles all HR, Manager, and Employee portal operations
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

// Catch any fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && $error['type'] === E_ERROR) {
        ob_clean(); // Clear any output
        echo json_encode(['success' => false, 'message' => 'Fatal error: ' . $error['message']]);
    }
});

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

// Debug logging
error_log("API Request - Module: $module, Action: $action");

// Route requests to appropriate handlers
switch ($module) {
    case 'employee':
        handleEmployeeRequests($conn, $action);
        break;
    case 'manager':
        handleManagerRequests($conn, $action);
        break;
    case 'hr':
        handleHRRequests($conn, $action);
        break;
    default:
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid module specified']);
}

// ==============================================
// EMPLOYEE MODULE HANDLERS
// ==============================================

function handleEmployeeRequests($conn, $action) {
    switch ($action) {
        case 'punch_in':
            employeePunchIn($conn);
            break;
        case 'punch_out':
            employeePunchOut($conn);
            break;
        case 'apply_leave':
            employeeApplyLeave($conn);
            break;
        case 'get_leave_history':
            employeeGetLeaveHistory($conn);
            break;
        case 'get_attendance_history':
            employeeGetAttendanceHistory($conn);
            break;
        case 'get_payslips':
            employeeGetPayslips($conn);
            break;
        case 'get_profile':
            employeeGetProfile($conn);
            break;
        case 'update_profile':
            employeeUpdateProfile($conn);
            break;
        case 'get_dashboard_stats':
            employeeGetDashboardStats($conn);
            break;
        case 'punch_attendance':
            employeePunchAttendance($conn);
            break;
        case 'get_attendance_status':
            employeeGetAttendanceStatus($conn);
            break;
        case 'get_quick_stats':
            employeeGetQuickStats($conn);
            break;
        case 'cancel_leave':
            employeeCancelLeave($conn);
            break;
        case 'get_payroll_info':
            employeeGetPayrollInfo($conn);
            break;
        case 'mobile_punch_request':
            employeeMobilePunchRequest($conn);
            break;
        case 'get_shift_schedule':
            employeeGetShiftSchedule($conn);
            break;
        case 'get_payroll_preview':
            employeeGetPayrollPreview($conn);
            break;
        case 'get_attendance_analytics':
            employeeGetAttendanceAnalytics($conn);
            break;
        case 'biometric_punch':
            employeeBiometricPunch($conn);
            break;
        case 'gps_attendance':
            employeeGPSAttendance($conn);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid employee action']);
    }
}

function employeePunchIn($conn) {
    try {
        $employee_id = $_SESSION['employee_id'] ?? $_POST['employee_id'] ?? 0;
        
        if (!$employee_id) {
            throw new Exception('Employee ID is required');
        }

        // Check if already punched in today
        $checkQuery = $conn->prepare("SELECT id FROM attendance WHERE employee_id = ? AND attendance_date = CURDATE()");
        $checkQuery->bind_param("i", $employee_id);
        $checkQuery->execute();
        
        if ($checkQuery->get_result()->num_rows > 0) {
            throw new Exception('Already punched in today');
        }

        $location = $_POST['location'] ?? 'Office';
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        
        $query = $conn->prepare("INSERT INTO attendance (employee_id, attendance_date, punch_in_time, time_in, status, location, ip_address, created_at) 
                                VALUES (?, CURDATE(), NOW(), NOW(), 'present', ?, ?, NOW())");
        $query->bind_param("iss", $employee_id, $location, $ip_address);
        
        if ($query->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Punched in successfully',
                'time' => date('H:i:s'),
                'date' => date('Y-m-d')
            ]);
        } else {
            throw new Exception('Failed to punch in');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function employeePunchOut($conn) {
    try {
        $employee_id = $_SESSION['employee_id'] ?? $_POST['employee_id'] ?? 0;
        
        if (!$employee_id) {
            throw new Exception('Employee ID is required');
        }

        // Check if punched in today
        $checkQuery = $conn->prepare("SELECT id, punch_in_time FROM attendance WHERE employee_id = ? AND attendance_date = CURDATE()");
        $checkQuery->bind_param("i", $employee_id);
        $checkQuery->execute();
        $result = $checkQuery->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Not punched in today');
        }

        $attendance = $result->fetch_assoc();
        $location = $_POST['location'] ?? 'Office';
        
        // Calculate work duration
        $punch_in = new DateTime($attendance['punch_in_time']);
        $punch_out = new DateTime();
        $duration = $punch_out->diff($punch_in);
        $work_hours = $duration->h + ($duration->i / 60);
        
        $query = $conn->prepare("UPDATE attendance SET punch_out_time = NOW(), time_out = NOW(), work_duration = ?, out_location = ?, updated_at = NOW() WHERE employee_id = ? AND attendance_date = CURDATE()");
        $query->bind_param("dsi", $work_hours, $location, $employee_id);
        
        if ($query->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Punched out successfully',
                'time' => date('H:i:s'),
                'duration' => number_format($work_hours, 2) . ' hours'
            ]);
        } else {
            throw new Exception('Failed to punch out');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function employeeApplyLeave($conn) {
    try {
        $employee_id = $_SESSION['employee_id'] ?? $_POST['employee_id'] ?? 0;
        $leave_type = $_POST['leave_type'] ?? '';
        $from_date = $_POST['from_date'] ?? '';
        $to_date = $_POST['to_date'] ?? '';
        $reason = $_POST['reason'] ?? '';
        
        if (!$employee_id || !$leave_type || !$from_date || !$to_date || !$reason) {
            throw new Exception('All fields are required');
        }

        // Calculate days
        $from = new DateTime($from_date);
        $to = new DateTime($to_date);
        $days = $from->diff($to)->days + 1;

        $query = $conn->prepare("INSERT INTO leave_requests (employee_id, leave_type, from_date, to_date, days_requested, reason, status, applied_date) 
                                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
        $query->bind_param("isssis", $employee_id, $leave_type, $from_date, $to_date, $days, $reason);
        
        if ($query->execute()) {
            echo json_encode(['success' => true, 'message' => 'Leave request submitted successfully']);
        } else {
            throw new Exception('Failed to submit leave request');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function employeeGetLeaveHistory($conn) {
    try {
        $employee_id = $_SESSION['employee_id'] ?? $_POST['employee_id'] ?? 0;
        
        if (!$employee_id) {
            throw new Exception('Employee ID is required');
        }

        $query = $conn->prepare("SELECT * FROM leave_requests WHERE employee_id = ? ORDER BY applied_date DESC LIMIT 50");
        $query->bind_param("i", $employee_id);
        $query->execute();
        $result = $query->get_result();
        
        $leaves = [];
        while ($row = $result->fetch_assoc()) {
            $leaves[] = $row;
        }
        
        echo json_encode(['success' => true, 'data' => $leaves]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function employeeGetAttendanceHistory($conn) {
    try {
        $employee_id = $_SESSION['employee_id'] ?? $_POST['employee_id'] ?? 0;
        
        if (!$employee_id) {
            throw new Exception('Employee ID is required');
        }

        $query = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? ORDER BY attendance_date DESC LIMIT 30");
        $query->bind_param("i", $employee_id);
        $query->execute();
        $result = $query->get_result();
        
        $attendance = [];
        while ($row = $result->fetch_assoc()) {
            $attendance[] = $row;
        }
        
        echo json_encode(['success' => true, 'data' => $attendance]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function employeeGetPayslips($conn) {
    try {
        $employee_id = $_SESSION['employee_id'] ?? $_POST['employee_id'] ?? 0;
        
        if (!$employee_id) {
            throw new Exception('Employee ID is required');
        }

        $query = $conn->prepare("SELECT * FROM payroll WHERE employee_id = ? ORDER BY pay_period_start DESC LIMIT 12");
        $query->bind_param("i", $employee_id);
        $query->execute();
        $result = $query->get_result();
        
        $payslips = [];
        while ($row = $result->fetch_assoc()) {
            $payslips[] = $row;
        }
        
        echo json_encode(['success' => true, 'data' => $payslips]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function employeeGetProfile($conn) {
    try {
        $employee_id = $_SESSION['employee_id'] ?? $_POST['employee_id'] ?? 0;
        
        if (!$employee_id) {
            throw new Exception('Employee ID is required');
        }

        $query = $conn->prepare("SELECT * FROM employees WHERE employee_id = ?");
        $query->bind_param("i", $employee_id);
        $query->execute();
        $result = $query->get_result();
        
        if ($employee = $result->fetch_assoc()) {
            echo json_encode(['success' => true, 'data' => $employee]);
        } else {
            throw new Exception('Employee not found');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function employeeUpdateProfile($conn) {
    try {
        $employee_id = $_SESSION['employee_id'] ?? $_POST['employee_id'] ?? 0;
        $phone = $_POST['phone'] ?? '';
        $address = $_POST['address'] ?? '';
        
        if (!$employee_id) {
            throw new Exception('Employee ID is required');
        }

        $query = $conn->prepare("UPDATE employees SET phone = ?, address = ? WHERE employee_id = ?");
        $query->bind_param("ssi", $phone, $address, $employee_id);
        
        if ($query->execute()) {
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        } else {
            throw new Exception('Failed to update profile');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function employeeGetDashboardStats($conn) {
    try {
        $employee_id = $_SESSION['employee_id'] ?? $_POST['employee_id'] ?? 0;
        
        if (!$employee_id) {
            throw new Exception('Employee ID is required');
        }

        // Get various stats
        $stats = [];
        
        // Total leaves this year
        $leaveQuery = $conn->prepare("SELECT COUNT(*) as total_leaves, 
                                     SUM(CASE WHEN status = 'approved' THEN days_requested ELSE 0 END) as approved_days
                                     FROM leave_requests WHERE employee_id = ? AND YEAR(applied_date) = YEAR(NOW())");
        $leaveQuery->bind_param("i", $employee_id);
        $leaveQuery->execute();
        $leaveResult = $leaveQuery->get_result()->fetch_assoc();
        $stats['leaves'] = $leaveResult;
        
        // Attendance this month
        $attendanceQuery = $conn->prepare("SELECT COUNT(*) as days_present FROM attendance WHERE employee_id = ? AND MONTH(attendance_date) = MONTH(NOW()) AND YEAR(attendance_date) = YEAR(NOW())");
        $attendanceQuery->bind_param("i", $employee_id);
        $attendanceQuery->execute();
        $attendanceResult = $attendanceQuery->get_result()->fetch_assoc();
        $stats['attendance'] = $attendanceResult;
        
        // Today's status
        $todayQuery = $conn->prepare("SELECT punch_in_time, punch_out_time FROM attendance WHERE employee_id = ? AND attendance_date = CURDATE()");
        $todayQuery->bind_param("i", $employee_id);
        $todayQuery->execute();
        $todayResult = $todayQuery->get_result();
        $stats['today'] = $todayResult->num_rows > 0 ? $todayResult->fetch_assoc() : null;
        
        echo json_encode(['success' => true, 'data' => $stats]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function employeePunchAttendance($conn) {
    try {
        $employee_id = $_SESSION['employee_id'] ?? $_POST['employee_id'] ?? 0;
        $punch_type = $_POST['punch_type'] ?? '';
        
        if (!$employee_id || !$punch_type) {
            throw new Exception('Employee ID and punch type are required');
        }

        if ($punch_type === 'in') {
            employeePunchIn($conn);
        } elseif ($punch_type === 'out') {
            employeePunchOut($conn);
        } else {
            throw new Exception('Invalid punch type');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function employeeGetAttendanceStatus($conn) {
    try {
        $employee_id = $_SESSION['employee_id'] ?? $_POST['employee_id'] ?? 0;
        
        if (!$employee_id) {
            throw new Exception('Employee ID is required');
        }

        $query = $conn->prepare("SELECT punch_in_time, punch_out_time, work_duration, status FROM attendance WHERE employee_id = ? AND attendance_date = CURDATE()");
        $query->bind_param("i", $employee_id);
        $query->execute();
        $result = $query->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $status = [
                'punched_in' => !empty($row['punch_in_time']),
                'punched_out' => !empty($row['punch_out_time']),
                'work_hours' => $row['work_duration'] ?? 0,
                'status' => $row['status'] ?? 'Not Punched In'
            ];
        } else {
            $status = [
                'punched_in' => false,
                'punched_out' => false,
                'work_hours' => 0,
                'status' => 'Not Punched In'
            ];
        }
        
        echo json_encode(['success' => true, 'data' => $status]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function employeeGetQuickStats($conn) {
    try {
        $employee_id = $_SESSION['employee_id'] ?? $_POST['employee_id'] ?? 0;
        
        if (!$employee_id) {
            throw new Exception('Employee ID is required');
        }

        $stats = [];
        
        // Monthly attendance
        $attendanceQuery = $conn->prepare("SELECT COUNT(*) as monthly_attendance FROM attendance WHERE employee_id = ? AND MONTH(attendance_date) = MONTH(NOW()) AND YEAR(attendance_date) = YEAR(NOW())");
        $attendanceQuery->bind_param("i", $employee_id);
        $attendanceQuery->execute();
        $stats['monthly_attendance'] = $attendanceQuery->get_result()->fetch_assoc()['monthly_attendance'];
        
        // Leave balance calculation
        $leaveQuery = $conn->prepare("SELECT SUM(days_requested) as used_leaves FROM leave_requests WHERE employee_id = ? AND status = 'approved' AND YEAR(applied_date) = YEAR(NOW())");
        $leaveQuery->bind_param("i", $employee_id);
        $leaveQuery->execute();
        $used_leaves = $leaveQuery->get_result()->fetch_assoc()['used_leaves'] ?? 0;
        
        $total_leaves = 30; // Assuming 30 annual leaves
        $stats['total_leaves'] = $total_leaves;
        $stats['used_leaves'] = $used_leaves;
        $stats['remaining_leaves'] = $total_leaves - $used_leaves;
        $stats['leave_balance'] = $stats['remaining_leaves'];
        
        echo json_encode(['success' => true, 'data' => $stats]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function employeeCancelLeave($conn) {
    try {
        $employee_id = $_SESSION['employee_id'] ?? $_POST['employee_id'] ?? 0;
        $leave_id = $_POST['leave_id'] ?? 0;
        
        if (!$employee_id || !$leave_id) {
            throw new Exception('Employee ID and Leave ID are required');
        }

        // Check if leave belongs to employee and is still pending
        $checkQuery = $conn->prepare("SELECT status FROM leave_requests WHERE id = ? AND employee_id = ?");
        $checkQuery->bind_param("ii", $leave_id, $employee_id);
        $checkQuery->execute();
        $result = $checkQuery->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Leave request not found');
        }
        
        $leave = $result->fetch_assoc();
        if ($leave['status'] !== 'pending') {
            throw new Exception('Only pending leave requests can be cancelled');
        }

        $query = $conn->prepare("UPDATE leave_requests SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND employee_id = ?");
        $query->bind_param("ii", $leave_id, $employee_id);
        
        if ($query->execute()) {
            echo json_encode(['success' => true, 'message' => 'Leave request cancelled successfully']);
        } else {
            throw new Exception('Failed to cancel leave request');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function employeeGetPayrollInfo($conn) {
    try {
        $employee_id = $_SESSION['employee_id'] ?? $_POST['employee_id'] ?? 0;
        
        if (!$employee_id) {
            throw new Exception('Employee ID is required');
        }

        // Get employee salary info
        $empQuery = $conn->prepare("SELECT monthly_salary FROM employees WHERE employee_id = ?");
        $empQuery->bind_param("i", $employee_id);
        $empQuery->execute();
        $empResult = $empQuery->get_result()->fetch_assoc();
        
        $basic_salary = $empResult['monthly_salary'] ?? 0;
        $allowances = $basic_salary * 0.2; // 20% allowances
        $deductions = $basic_salary * 0.1; // 10% deductions
        $net_salary = $basic_salary + $allowances - $deductions;
        
        // Get recent payslips
        $payslipQuery = $conn->prepare("SELECT * FROM payroll WHERE employee_id = ? ORDER BY pay_year DESC, pay_month DESC LIMIT 6");
        $payslipQuery->bind_param("i", $employee_id);
        $payslipQuery->execute();
        $payslipResult = $payslipQuery->get_result();
        
        $recent_payslips = [];
        while ($row = $payslipResult->fetch_assoc()) {
            $row['month_year'] = date('F Y', mktime(0, 0, 0, $row['pay_month'], 1, $row['pay_year']));
            $recent_payslips[] = $row;
        }
        
        $payroll_info = [
            'basic_salary' => $basic_salary,
            'allowances' => $allowances,
            'deductions' => $deductions,
            'net_salary' => $net_salary,
            'recent_payslips' => $recent_payslips
        ];
        
        echo json_encode(['success' => true, 'data' => $payroll_info]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ==============================================
// MANAGER MODULE HANDLERS
// ==============================================

function handleManagerRequests($conn, $action) {
    switch ($action) {
        case 'get_team_members':
            managerGetTeamMembers($conn);
            break;
        case 'get_team_leave_requests':
            managerGetTeamLeaveRequests($conn);
            break;
        case 'approve_leave':
            managerApproveLeave($conn);
            break;
        case 'reject_leave':
            managerRejectLeave($conn);
            break;
        case 'get_team_attendance':
            managerGetTeamAttendance($conn);
            break;
        case 'get_team_reports':
            managerGetTeamReports($conn);
            break;
        case 'get_dashboard_stats':
            managerGetDashboardStats($conn);
            break;
        case 'get_performance_reviews':
            managerGetPerformanceReviews($conn);
            break;
        case 'add_performance_review':
            managerAddPerformanceReview($conn);
            break;
        case 'get_team_analytics':
            managerGetTeamAnalytics($conn);
            break;
        case 'approve_overtime':
            managerApproveOvertime($conn);
            break;
        case 'bulk_attendance_approval':
            managerBulkAttendanceApproval($conn);
            break;
        case 'get_attendance_anomalies':
            managerGetAttendanceAnomalies($conn);
            break;
        case 'schedule_team_shifts':
            managerScheduleTeamShifts($conn);
            break;
        case 'get_payroll_preview':
            managerGetPayrollPreview($conn);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid manager action']);
    }
}

function managerGetTeamMembers($conn) {
    try {
        $manager_id = $_SESSION['employee_id'] ?? $_POST['manager_id'] ?? 2; // Set default manager ID
        
        // Get team members (for demo, get all employees)
        $query = $conn->prepare("SELECT e.*, 
                                COALESCE(a.status, 'absent') as today_status,
                                COALESCE(pr.avg_rating, 3.5) as performance_rating
                                FROM employees e 
                                LEFT JOIN attendance a ON e.employee_id = a.employee_id AND a.attendance_date = CURDATE()
                                LEFT JOIN (
                                    SELECT employee_id, 
                                           AVG((technical_rating + communication_rating + teamwork_rating) / 3) as avg_rating
                                    FROM performance_reviews 
                                    GROUP BY employee_id
                                ) pr ON e.employee_id = pr.employee_id
                                WHERE e.status = 'active'
                                ORDER BY e.name");
        $query->execute();
        $result = $query->get_result();
        
        $team = [];
        while ($row = $result->fetch_assoc()) {
            $team[] = [
                'employee_id' => $row['employee_id'],
                'name' => $row['name'],
                'employee_code' => $row['employee_code'],
                'position' => $row['position'] ?? 'Employee',
                'status' => $row['status'],
                'today_status' => $row['today_status'],
                'performance_rating' => round($row['performance_rating'], 1),
                'phone' => $row['phone'] ?? '',
                'email' => $row['email'] ?? '',
                'monthly_salary' => $row['monthly_salary']
            ];
        }
        
        echo json_encode(['success' => true, 'data' => $team]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function managerGetTeamLeaveRequests($conn) {
    try {
        $status = $_POST['status'] ?? 'pending';
        
        // Get leave requests - if leave_requests table exists, otherwise create mock data
        $checkTable = $conn->query("SHOW TABLES LIKE 'leave_requests'");
        
        if ($checkTable && $checkTable->num_rows > 0) {
            $whereClause = $status === 'all' ? '' : "WHERE lr.status = '$status'";
            $query = $conn->prepare("SELECT lr.*, e.name as employee_name 
                                    FROM leave_requests lr
                                    JOIN employees e ON lr.employee_id = e.employee_id
                                    $whereClause 
                                    ORDER BY lr.applied_date DESC");
            $query->execute();
            $result = $query->get_result();
            
            $requests = [];
            while ($row = $result->fetch_assoc()) {
                $requests[] = $row;
            }
        } else {
            // Create mock leave request data
            $requests = [
                [
                    'id' => 1,
                    'employee_name' => 'John Doe',
                    'leave_type' => 'Annual Leave',
                    'from_date' => '2025-08-01',
                    'to_date' => '2025-08-03',
                    'days_requested' => 3,
                    'reason' => 'Family vacation',
                    'applied_date' => '2025-07-20 10:30:00',
                    'status' => 'pending'
                ],
                [
                    'id' => 2,
                    'employee_name' => 'Jane Smith',
                    'leave_type' => 'Sick Leave',
                    'from_date' => '2025-07-28',
                    'to_date' => '2025-07-29',
                    'days_requested' => 2,
                    'reason' => 'Medical appointment',
                    'applied_date' => '2025-07-27 14:20:00',
                    'status' => 'approved'
                ]
            ];
            
            // Filter by status if not 'all'
            if ($status !== 'all') {
                $requests = array_filter($requests, function($req) use ($status) {
                    return $req['status'] === $status;
                });
                $requests = array_values($requests); // Re-index array
            }
        }
        
        echo json_encode(['success' => true, 'data' => $requests]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function managerApproveLeave($conn) {
    try {
        $manager_id = $_SESSION['employee_id'] ?? $_POST['manager_id'] ?? 0;
        $leave_id = $_POST['leave_id'] ?? 0;
        $comments = $_POST['comments'] ?? '';
        
        if (!$manager_id || !$leave_id) {
            throw new Exception('Manager ID and Leave ID are required');
        }

        $query = $conn->prepare("UPDATE leave_requests SET status = 'approved', approved_by = ?, approved_date = NOW(), approver_comments = ? WHERE id = ?");
        $query->bind_param("isi", $manager_id, $comments, $leave_id);
        
        if ($query->execute()) {
            echo json_encode(['success' => true, 'message' => 'Leave request approved']);
        } else {
            throw new Exception('Failed to approve leave request');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function managerRejectLeave($conn) {
    try {
        $manager_id = $_SESSION['employee_id'] ?? $_POST['manager_id'] ?? 0;
        $leave_id = $_POST['leave_id'] ?? 0;
        $comments = $_POST['comments'] ?? '';
        
        if (!$manager_id || !$leave_id) {
            throw new Exception('Manager ID and Leave ID are required');
        }

        $query = $conn->prepare("UPDATE leave_requests SET status = 'rejected', approved_by = ?, approved_date = NOW(), approver_comments = ? WHERE id = ?");
        $query->bind_param("isi", $manager_id, $comments, $leave_id);
        
        if ($query->execute()) {
            echo json_encode(['success' => true, 'message' => 'Leave request rejected']);
        } else {
            throw new Exception('Failed to reject leave request');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function managerGetTeamAttendance($conn) {
    try {
        $date = $_POST['date'] ?? date('Y-m-d');
        
        // Get attendance data for the specified date
        $query = $conn->prepare("SELECT a.*, e.name as employee_name,
                                TIMESTAMPDIFF(HOUR, a.punch_in_time, a.punch_out_time) as work_duration,
                                CASE 
                                    WHEN a.punch_in_time IS NULL THEN 'absent'
                                    WHEN TIME(a.punch_in_time) > '09:15:00' THEN 'late'
                                    ELSE 'present'
                                END as status,
                                CASE 
                                    WHEN TIME(a.punch_in_time) > '09:00:00' THEN TIMESTAMPDIFF(MINUTE, TIME('09:00:00'), TIME(a.punch_in_time))
                                    ELSE 0
                                END as late_minutes
                                FROM employees e 
                                LEFT JOIN attendance a ON e.employee_id = a.employee_id AND a.attendance_date = ?
                                WHERE e.status = 'active'
                                ORDER BY e.name");
        $query->bind_param("s", $date);
        $query->execute();
        $result = $query->get_result();
        
        $attendance = [];
        while ($row = $result->fetch_assoc()) {
            $attendance[] = [
                'id' => $row['id'] ?? 0,
                'employee_name' => $row['employee_name'],
                'attendance_date' => $date,
                'punch_in_time' => $row['punch_in_time'] ? date('H:i', strtotime($row['punch_in_time'])) : null,
                'punch_out_time' => $row['punch_out_time'] ? date('H:i', strtotime($row['punch_out_time'])) : null,
                'work_duration' => $row['work_duration'] ?? 0,
                'status' => $row['status'],
                'late_minutes' => $row['late_minutes'] ?? 0
            ];
        }
        
        echo json_encode(['success' => true, 'data' => $attendance]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function managerGetTeamReports($conn) {
    try {
        $reports = [];
        
        // Team attendance summary
        $totalEmployees = $conn->query("SELECT COUNT(*) as count FROM employees WHERE status = 'active'")->fetch_assoc()['count'];
        $monthlyAttendance = $conn->query("SELECT COUNT(DISTINCT a.employee_id) as present_employees,
                                          COUNT(a.id) as total_records
                                          FROM attendance a 
                                          JOIN employees e ON a.employee_id = e.employee_id
                                          WHERE MONTH(a.attendance_date) = MONTH(NOW()) 
                                          AND YEAR(a.attendance_date) = YEAR(NOW())
                                          AND e.status = 'active'
                                          AND a.punch_in_time IS NOT NULL");
        $attendanceData = $monthlyAttendance ? $monthlyAttendance->fetch_assoc() : ['present_employees' => 0, 'total_records' => 0];
        
        $reports['attendance'] = [
            'total_members' => $totalEmployees,
            'avg_attendance' => $totalEmployees > 0 ? round(($attendanceData['present_employees'] / $totalEmployees) * 100, 1) : 0,
            'on_time_rate' => 85 // Mock data
        ];
        
        // Leave utilization
        $checkLeaveTable = $conn->query("SHOW TABLES LIKE 'leave_requests'");
        if ($checkLeaveTable && $checkLeaveTable->num_rows > 0) {
            $leaveData = $conn->query("SELECT 
                                     COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                                     COUNT(CASE WHEN status = 'approved' AND MONTH(applied_date) = MONTH(NOW()) THEN 1 END) as approved,
                                     AVG(days_requested) as avg_days
                                     FROM leave_requests 
                                     WHERE YEAR(applied_date) = YEAR(NOW())");
            $reports['leaves'] = $leaveData ? $leaveData->fetch_assoc() : ['pending' => 0, 'approved' => 0, 'avg_days' => 0];
        } else {
            $reports['leaves'] = ['pending' => 2, 'approved' => 8, 'avg_days' => 2.5];
        }
        
        // Performance metrics
        $checkPerfTable = $conn->query("SHOW TABLES LIKE 'performance_reviews'");
        if ($checkPerfTable && $checkPerfTable->num_rows > 0) {
            $perfData = $conn->query("SELECT 
                                     COUNT(id) as total_reviews,
                                     AVG((technical_rating + communication_rating + teamwork_rating) / 3) as avg_rating,
                                     COUNT(CASE WHEN (technical_rating + communication_rating + teamwork_rating) / 3 >= 4 THEN 1 END) as top_performers
                                     FROM performance_reviews");
            $reports['performance'] = $perfData ? $perfData->fetch_assoc() : ['total_reviews' => 0, 'avg_rating' => 0, 'top_performers' => 0];
        } else {
            $reports['performance'] = ['total_reviews' => 5, 'avg_rating' => 4.2, 'top_performers' => 3];
        }
        
        // Productivity metrics
        $productivityData = $conn->query("SELECT 
                                         COUNT(DISTINCT e.employee_id) as team_size,
                                         COALESCE(SUM(TIMESTAMPDIFF(HOUR, a.punch_in_time, a.punch_out_time)), 0) as total_hours,
                                         COALESCE(AVG(TIMESTAMPDIFF(HOUR, a.punch_in_time, a.punch_out_time)), 0) as avg_daily
                                         FROM employees e 
                                         LEFT JOIN attendance a ON e.employee_id = a.employee_id 
                                         AND MONTH(a.attendance_date) = MONTH(NOW()) 
                                         AND YEAR(a.attendance_date) = YEAR(NOW())
                                         WHERE e.status = 'active'");
        $prodData = $productivityData ? $productivityData->fetch_assoc() : ['team_size' => 0, 'total_hours' => 0, 'avg_daily' => 0];
        $reports['productivity'] = [
            'score' => 85, // Mock productivity score
            'total_hours' => round($prodData['total_hours'] ?? 0),
            'avg_daily' => round($prodData['avg_daily'] ?? 0, 1)
        ];
        
        echo json_encode(['success' => true, 'data' => $reports]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function managerGetDashboardStats($conn) {
    try {
        // Get dashboard statistics
        $stats = [];
        
        // Team size - count active employees
        $teamQuery = $conn->query("SELECT COUNT(*) as team_size FROM employees WHERE status = 'active'");
        $stats['team_members'] = $teamQuery ? $teamQuery->fetch_assoc()['team_size'] : 0;
        
        // Pending leave requests - check if table exists
        $checkLeaveTable = $conn->query("SHOW TABLES LIKE 'leave_requests'");
        if ($checkLeaveTable && $checkLeaveTable->num_rows > 0) {
            $pendingQuery = $conn->query("SELECT COUNT(*) as pending_requests FROM leave_requests WHERE status = 'pending'");
            $stats['pending_approvals'] = $pendingQuery ? $pendingQuery->fetch_assoc()['pending_requests'] : 0;
        } else {
            $stats['pending_approvals'] = 2; // Mock data
        }
        
        // Today's attendance
        $todayQuery = $conn->prepare("SELECT COUNT(*) as present_today FROM attendance a
                                     JOIN employees e ON a.employee_id = e.employee_id
                                     WHERE e.status = 'active' AND a.attendance_date = CURDATE()
                                     AND a.punch_in_time IS NOT NULL");
        $todayQuery->execute();
        $result = $todayQuery->get_result();
        $stats['team_present'] = $result ? $result->fetch_assoc()['present_today'] : 0;
        
        // Performance score - calculate based on attendance
        $totalEmployees = $stats['team_members'];
        $presentToday = $stats['team_present'];
        $stats['performance_score'] = $totalEmployees > 0 ? round(($presentToday / $totalEmployees) * 100) : 85;
        
        echo json_encode(['success' => true, 'data' => $stats]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function managerGetPerformanceReviews($conn) {
    try {
        $manager_id = $_SESSION['employee_id'] ?? $_POST['manager_id'] ?? 0;
        
        if (!$manager_id) {
            throw new Exception('Manager ID is required');
        }

        // Get performance reviews for team members
        $query = $conn->prepare("SELECT pr.*, e.name as employee_name FROM performance_reviews pr
                                JOIN employees e ON pr.employee_id = e.employee_id
                                WHERE e.manager_id = ? OR e.department_id IN (
                                    SELECT department_id FROM employees WHERE employee_id = ?
                                ) ORDER BY pr.review_date DESC");
        $query->bind_param("ii", $manager_id, $manager_id);
        $query->execute();
        $result = $query->get_result();
        
        $reviews = [];
        while ($row = $result->fetch_assoc()) {
            $reviews[] = $row;
        }
        
        echo json_encode(['success' => true, 'data' => $reviews]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function managerAddPerformanceReview($conn) {
    try {
        $manager_id = $_SESSION['employee_id'] ?? $_POST['manager_id'] ?? 0;
        $employee_id = $_POST['employee_id'] ?? 0;
        $review_period = $_POST['review_period'] ?? '';
        $technical_rating = $_POST['technical_rating'] ?? 3;
        $communication_rating = $_POST['communication_rating'] ?? 3;
        $teamwork_rating = $_POST['teamwork_rating'] ?? 3;
        $achievements = $_POST['achievements'] ?? '';
        $improvement_areas = $_POST['improvement_areas'] ?? '';
        $next_goals = $_POST['next_goals'] ?? '';
        
        if (!$manager_id || !$employee_id || !$review_period) {
            throw new Exception('Manager ID, Employee ID, and Review Period are required');
        }

        // Check if performance_reviews table exists, if not create it
        $createTable = "CREATE TABLE IF NOT EXISTS performance_reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            manager_id INT NOT NULL,
            review_period VARCHAR(20) NOT NULL,
            technical_rating INT DEFAULT 3,
            communication_rating INT DEFAULT 3,
            teamwork_rating INT DEFAULT 3,
            achievements TEXT,
            improvement_areas TEXT,
            next_goals TEXT,
            review_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        $conn->query($createTable);

        $query = $conn->prepare("INSERT INTO performance_reviews (employee_id, manager_id, review_period, technical_rating, communication_rating, teamwork_rating, achievements, improvement_areas, next_goals, review_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $query->bind_param("iisiissss", $employee_id, $manager_id, $review_period, $technical_rating, $communication_rating, $teamwork_rating, $achievements, $improvement_areas, $next_goals);
        
        if ($query->execute()) {
            echo json_encode(['success' => true, 'message' => 'Performance review added successfully']);
        } else {
            throw new Exception('Failed to add performance review');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Advanced Manager Functions
function managerGetTeamAnalytics($conn) {
    try {
        $period = $_POST['period'] ?? 'month';
        
        // Calculate date range based on period
        $dateCondition = match($period) {
            'week' => "AND a.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
            'quarter' => "AND a.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)",
            default => "AND a.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
        };
        
        $query = $conn->prepare("SELECT e.employee_id, e.name as employee_name,
                                COUNT(a.id) as total_days,
                                COUNT(CASE WHEN a.punch_in_time IS NOT NULL THEN 1 END) as present_days,
                                COUNT(CASE WHEN TIME(a.punch_in_time) > '09:15:00' THEN 1 END) as late_days,
                                AVG(TIMESTAMPDIFF(HOUR, a.punch_in_time, a.punch_out_time)) as avg_hours,
                                SUM(CASE WHEN TIMESTAMPDIFF(HOUR, a.punch_in_time, a.punch_out_time) > 8 THEN TIMESTAMPDIFF(HOUR, a.punch_in_time, a.punch_out_time) - 8 ELSE 0 END) as overtime_hours
                                FROM employees e 
                                LEFT JOIN attendance a ON e.employee_id = a.employee_id $dateCondition
                                WHERE e.status = 'active'
                                GROUP BY e.employee_id, e.name
                                ORDER BY e.name");
        $query->execute();
        $result = $query->get_result();
        
        $analytics = [];
        while ($row = $result->fetch_assoc()) {
            $analytics[] = [
                'employee_id' => $row['employee_id'],
                'employee_name' => $row['employee_name'],
                'total_days' => $row['total_days'] ?? 0,
                'present_days' => $row['present_days'] ?? 0,
                'late_days' => $row['late_days'] ?? 0,
                'avg_hours' => round($row['avg_hours'] ?? 0, 1),
                'overtime_hours' => round($row['overtime_hours'] ?? 0, 1),
                'attendance_percentage' => $row['total_days'] > 0 ? round(($row['present_days'] / $row['total_days']) * 100, 1) : 0
            ];
        }
        
        echo json_encode(['success' => true, 'data' => $analytics]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function managerGetAttendanceAnomalies($conn) {
    try {
        $query = $conn->prepare("SELECT a.*, e.name as employee_name,
                                CASE 
                                    WHEN TIME(a.punch_in_time) > '09:15:00' THEN TIMESTAMPDIFF(MINUTE, TIME('09:00:00'), TIME(a.punch_in_time))
                                    ELSE 0
                                END as late_minutes,
                                CASE 
                                    WHEN a.punch_out_time IS NOT NULL AND TIME(a.punch_out_time) < '17:30:00' THEN TIMESTAMPDIFF(MINUTE, TIME(a.punch_out_time), TIME('18:00:00'))
                                    ELSE 0
                                END as early_departure_minutes,
                                TIMESTAMPDIFF(HOUR, a.punch_in_time, a.punch_out_time) as total_hours
                                FROM attendance a
                                JOIN employees e ON a.employee_id = e.employee_id
                                WHERE a.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                                AND e.status = 'active'
                                AND (TIME(a.punch_in_time) > '09:15:00' 
                                     OR (a.punch_out_time IS NOT NULL AND TIME(a.punch_out_time) < '17:30:00')
                                     OR TIMESTAMPDIFF(HOUR, a.punch_in_time, a.punch_out_time) < 4)
                                ORDER BY a.attendance_date DESC");
        $query->execute();
        $result = $query->get_result();
        
        $anomalies = [];
        while ($row = $result->fetch_assoc()) {
            $anomalies[] = [
                'employee_name' => $row['employee_name'],
                'attendance_date' => $row['attendance_date'],
                'late_minutes' => $row['late_minutes'],
                'early_departure_minutes' => $row['early_departure_minutes'],
                'total_hours' => $row['total_hours'] ?? 0
            ];
        }
        
        echo json_encode(['success' => true, 'data' => $anomalies]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function managerApproveOvertime($conn) {
    try {
        $attendance_id = $_POST['attendance_id'] ?? 0;
        $action = $_POST['action'] ?? 'approve';
        
        if (!$attendance_id) {
            throw new Exception('Attendance ID is required');
        }
        
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $approved_by = $_SESSION['employee_id'] ?? 2;
        
        // Update attendance record with approval status
        $query = $conn->prepare("UPDATE attendance SET 
                                overtime_approved = ?, 
                                approved_by = ?, 
                                approved_at = NOW() 
                                WHERE id = ?");
        $query->bind_param("sii", $status, $approved_by, $attendance_id);
        
        if ($query->execute()) {
            $message = $action === 'approve' ? 'Overtime approved successfully' : 'Overtime rejected';
            echo json_encode(['success' => true, 'message' => $message]);
        } else {
            throw new Exception('Failed to update overtime status');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function managerBulkAttendanceApproval($conn) {
    try {
        $attendance_ids = $_POST['attendance_ids'] ?? [];
        $action = $_POST['action'] ?? 'approve';
        
        if (empty($attendance_ids)) {
            throw new Exception('No attendance records selected');
        }
        
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $approved_by = $_SESSION['employee_id'] ?? 2;
        
        $placeholders = str_repeat('?,', count($attendance_ids) - 1) . '?';
        $query = $conn->prepare("UPDATE attendance SET 
                                overtime_approved = ?, 
                                approved_by = ?, 
                                approved_at = NOW() 
                                WHERE id IN ($placeholders)");
        
        $types = str_repeat('i', count($attendance_ids));
        $query->bind_param("si$types", $status, $approved_by, ...$attendance_ids);
        
        if ($query->execute()) {
            $count = $query->affected_rows;
            $message = $action === 'approve' ? 
                      "$count overtime records approved successfully" : 
                      "$count overtime records rejected";
            echo json_encode(['success' => true, 'message' => $message]);
        } else {
            throw new Exception('Failed to bulk update attendance records');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function managerScheduleTeamShifts($conn) {
    try {
        $week_start = $_POST['week_start'] ?? date('Y-m-d');
        $shift_template = $_POST['shift_template'] ?? 'standard';
        $employee_ids = $_POST['employee_ids'] ?? [];
        
        if (empty($employee_ids)) {
            throw new Exception('No employees selected for shift scheduling');
        }
        
        // Define shift templates
        $templates = [
            'standard' => ['start' => '09:00:00', 'end' => '17:00:00'],
            'early' => ['start' => '07:00:00', 'end' => '15:00:00'],
            'late' => ['start' => '13:00:00', 'end' => '21:00:00'],
            'night' => ['start' => '22:00:00', 'end' => '06:00:00']
        ];
        
        $shift = $templates[$shift_template] ?? $templates['standard'];
        
        // Create or update shift schedules
        $checkTable = "CREATE TABLE IF NOT EXISTS employee_shifts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            shift_name VARCHAR(50) NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            effective_from DATE NOT NULL,
            effective_to DATE NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $conn->query($checkTable);
        
        $success_count = 0;
        foreach ($employee_ids as $employee_id) {
            $query = $conn->prepare("INSERT INTO employee_shifts 
                                    (employee_id, shift_name, start_time, end_time, effective_from) 
                                    VALUES (?, ?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE
                                    shift_name = VALUES(shift_name),
                                    start_time = VALUES(start_time),
                                    end_time = VALUES(end_time),
                                    effective_from = VALUES(effective_from)");
            $query->bind_param("issss", $employee_id, $shift_template, $shift['start'], $shift['end'], $week_start);
            
            if ($query->execute()) {
                $success_count++;
            }
        }
        
        echo json_encode([
            'success' => true, 
            'message' => "$success_count employees scheduled successfully",
            'scheduled_count' => $success_count
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function managerGetPayrollPreview($conn) {
    try {
        $period_start = $_POST['period_start'] ?? date('Y-m-01');
        $period_end = $_POST['period_end'] ?? date('Y-m-t');
        
        $query = $conn->prepare("SELECT e.employee_id, e.name, e.monthly_salary,
                                COUNT(a.id) as working_days,
                                COUNT(CASE WHEN a.punch_in_time IS NOT NULL THEN 1 END) as present_days,
                                SUM(TIMESTAMPDIFF(HOUR, a.punch_in_time, a.punch_out_time)) as total_hours,
                                SUM(CASE WHEN TIMESTAMPDIFF(HOUR, a.punch_in_time, a.punch_out_time) > 8 
                                    THEN TIMESTAMPDIFF(HOUR, a.punch_in_time, a.punch_out_time) - 8 ELSE 0 END) as overtime_hours
                                FROM employees e 
                                LEFT JOIN attendance a ON e.employee_id = a.employee_id 
                                AND a.attendance_date BETWEEN ? AND ?
                                WHERE e.status = 'active'
                                GROUP BY e.employee_id, e.name, e.monthly_salary
                                ORDER BY e.name");
        $query->bind_param("ss", $period_start, $period_end);
        $query->execute();
        $result = $query->get_result();
        
        $payroll_data = [];
        while ($row = $result->fetch_assoc()) {
            $gross_salary = $row['monthly_salary'];
            $overtime_amount = ($row['overtime_hours'] ?? 0) * 50; // ₹50 per overtime hour
            $total_earnings = $gross_salary + $overtime_amount;
            $deductions = $total_earnings * 0.12; // 12% total deductions
            $net_salary = $total_earnings - $deductions;
            
            $payroll_data[] = [
                'employee_id' => $row['employee_id'],
                'employee_name' => $row['name'],
                'base_salary' => $gross_salary,
                'overtime_hours' => $row['overtime_hours'] ?? 0,
                'overtime_amount' => $overtime_amount,
                'gross_salary' => $total_earnings,
                'deductions' => $deductions,
                'net_salary' => $net_salary,
                'working_days' => $row['working_days'] ?? 0,
                'present_days' => $row['present_days'] ?? 0,
                'total_hours' => $row['total_hours'] ?? 0
            ];
        }
        
        echo json_encode(['success' => true, 'data' => $payroll_data]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ==============================================
// HR MODULE HANDLERS
// ==============================================

function handleHRRequests($conn, $action) {
    switch ($action) {
        case 'get_employees':
        case 'get_all_employees':
            hrGetAllEmployees($conn);
            break;
        case 'add_employee':
            hrAddEmployee($conn);
            break;
        case 'update_employee':
            hrUpdateEmployee($conn);
            break;
        case 'delete_employee':
            hrDeleteEmployee($conn);
            break;
        case 'get_leave_requests':
        case 'get_all_leave_requests':
            hrGetAllLeaveRequests($conn);
            break;
        case 'update_leave_status':
        case 'approve_leave':
            hrApproveLeave($conn);
            break;
        case 'reject_leave':
            hrRejectLeave($conn);
            break;
        case 'get_attendance':
        case 'get_attendance_reports':
            hrGetAttendanceReports($conn);
            break;
        case 'correct_attendance':
            hrCorrectAttendance($conn);
            break;
        case 'generate_payroll':
            hrGeneratePayroll($conn);
            break;
        case 'generate_report':
            hrGenerateReport($conn);
            break;
        case 'get_payroll':
            hrGetPayroll($conn);
            break;
        case 'get_system_reports':
            hrGetSystemReports($conn);
            break;
        case 'get_dashboard_stats':
            hrGetDashboardStats($conn);
            break;
        case 'process_bulk_attendance':
            hrProcessBulkAttendance($conn);
            break;
        case 'configure_overtime_rules':
            hrConfigureOvertimeRules($conn);
            break;
        case 'setup_payroll_structures':
            hrSetupPayrollStructures($conn);
            break;
        case 'analytics_dashboard':
            hrAnalyticsDashboard($conn);
            break;
        case 'compliance_monitor':
            hrComplianceMonitor($conn);
            break;
        case 'biometric_device_management':
            hrBiometricDeviceManagement($conn);
            break;
        case 'advanced_reporting':
            hrAdvancedReporting($conn);
            break;
        case 'workflow_management':
            hrWorkflowManagement($conn);
            break;
        case 'ml_insights':
            hrMLInsights($conn);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid HR action']);
    }
}

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
        
        echo json_encode(['success' => true, 'data' => $employees]);
    } catch (Exception $e) {
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
            echo json_encode(['success' => true, 'message' => 'Employee added successfully', 'employee_id' => $conn->insert_id]);
        } else {
            throw new Exception('Failed to add employee');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function hrUpdateEmployee($conn) {
    try {
        $employee_id = $_POST['employee_id'] ?? 0;
        $name = $_POST['name'] ?? '';
        $position = $_POST['position'] ?? '';
        $monthly_salary = $_POST['monthly_salary'] ?? 0;
        $phone = $_POST['phone'] ?? '';
        $address = $_POST['address'] ?? '';
        $status = $_POST['status'] ?? 'active';
        
        if (!$employee_id || !$name || !$position) {
            throw new Exception('Employee ID, name, and position are required');
        }

        $query = $conn->prepare("UPDATE employees SET name = ?, position = ?, monthly_salary = ?, phone = ?, address = ?, status = ? WHERE employee_id = ?");
        $query->bind_param("ssdssi", $name, $position, $monthly_salary, $phone, $address, $status, $employee_id);
        
        if ($query->execute()) {
            echo json_encode(['success' => true, 'message' => 'Employee updated successfully']);
        } else {
            throw new Exception('Failed to update employee');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function hrDeleteEmployee($conn) {
    try {
        $employee_id = $_POST['employee_id'] ?? 0;
        
        if (!$employee_id) {
            throw new Exception('Employee ID is required');
        }

        // Soft delete - set status to inactive
        $query = $conn->prepare("UPDATE employees SET status = 'inactive' WHERE employee_id = ?");
        $query->bind_param("i", $employee_id);
        
        if ($query->execute()) {
            echo json_encode(['success' => true, 'message' => 'Employee deactivated successfully']);
        } else {
            throw new Exception('Failed to deactivate employee');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function hrGetAllLeaveRequests($conn) {
    try {
        $status = $_POST['status'] ?? 'all';
        
        if ($status === 'all') {
            $query = $conn->prepare("SELECT lr.*, e.name as employee_name FROM leave_requests lr JOIN employees e ON lr.employee_id = e.employee_id ORDER BY lr.applied_date DESC");
        } else {
            $query = $conn->prepare("SELECT lr.*, e.name as employee_name FROM leave_requests lr JOIN employees e ON lr.employee_id = e.employee_id WHERE lr.status = ? ORDER BY lr.applied_date DESC");
            $query->bind_param("s", $status);
        }
        
        $query->execute();
        $result = $query->get_result();
        
        $requests = [];
        while ($row = $result->fetch_assoc()) {
            $requests[] = $row;
        }
        
        echo json_encode(['success' => true, 'data' => $requests]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function hrApproveLeave($conn) {
    try {
        $hr_id = $_SESSION['employee_id'] ?? $_POST['hr_id'] ?? 1;
        $leave_id = $_POST['leave_id'] ?? 0;
        $status = $_POST['status'] ?? 'approved'; // Can be 'approved' or 'rejected'
        $comments = $_POST['comments'] ?? '';
        
        if (!$leave_id) {
            throw new Exception('Leave ID is required');
        }

        if ($status === 'approved') {
            $query = $conn->prepare("UPDATE leave_requests SET status = 'approved', approved_by = ?, approved_date = NOW(), approver_comments = ? WHERE id = ?");
            $query->bind_param("isi", $hr_id, $comments, $leave_id);
        } else {
            $query = $conn->prepare("UPDATE leave_requests SET status = 'rejected', approved_by = ?, approved_date = NOW(), approver_comments = ? WHERE id = ?");
            $query->bind_param("isi", $hr_id, $comments, $leave_id);
        }
        
        if ($query->execute()) {
            echo json_encode(['success' => true, 'message' => "Leave request $status successfully"]);
        } else {
            throw new Exception("Failed to $status leave request");
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function hrRejectLeave($conn) {
    try {
        $hr_id = $_SESSION['employee_id'] ?? $_POST['hr_id'] ?? 0;
        $leave_id = $_POST['leave_id'] ?? 0;
        $comments = $_POST['comments'] ?? '';
        
        if (!$hr_id || !$leave_id) {
            throw new Exception('HR ID and Leave ID are required');
        }

        $query = $conn->prepare("UPDATE leave_requests SET status = 'rejected', approved_by = ?, approved_date = NOW(), approver_comments = ? WHERE id = ?");
        $query->bind_param("isi", $hr_id, $comments, $leave_id);
        
        if ($query->execute()) {
            echo json_encode(['success' => true, 'message' => 'Leave request rejected']);
        } else {
            throw new Exception('Failed to reject leave request');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function hrGetAttendanceReports($conn) {
    try {
        $start_date = $_POST['start_date'] ?? date('Y-m-01');
        $end_date = $_POST['end_date'] ?? date('Y-m-t');
        
        $query = $conn->prepare("SELECT a.*, e.name as employee_name FROM attendance a 
                                JOIN employees e ON a.employee_id = e.employee_id 
                                WHERE a.attendance_date BETWEEN ? AND ? 
                                ORDER BY a.attendance_date DESC, e.name");
        $query->bind_param("ss", $start_date, $end_date);
        $query->execute();
        $result = $query->get_result();
        
        $attendance = [];
        while ($row = $result->fetch_assoc()) {
            $attendance[] = $row;
        }
        
        echo json_encode(['success' => true, 'data' => $attendance]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function hrCorrectAttendance($conn) {
    try {
        $attendance_id = $_POST['attendance_id'] ?? 0;
        $punch_in_time = $_POST['punch_in_time'] ?? '';
        $punch_out_time = $_POST['punch_out_time'] ?? '';
        $status = $_POST['status'] ?? 'present';
        $remarks = $_POST['remarks'] ?? '';
        
        if (!$attendance_id) {
            throw new Exception('Attendance ID is required');
        }

        $query = $conn->prepare("UPDATE attendance SET punch_in_time = ?, punch_out_time = ?, time_in = ?, time_out = ?, status = ?, remarks = ?, updated_at = NOW() WHERE id = ?");
        $query->bind_param("ssssssi", $punch_in_time, $punch_out_time, $punch_in_time, $punch_out_time, $status, $remarks, $attendance_id);
        
        if ($query->execute()) {
            echo json_encode(['success' => true, 'message' => 'Attendance corrected successfully']);
        } else {
            throw new Exception('Failed to correct attendance');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function hrGeneratePayroll($conn) {
    try {
        // Clean any previous output
        if (ob_get_level()) ob_clean();
        
        error_log("hrGeneratePayroll function called");
        
        $month = $_POST['month'] ?? date('m');
        $year = $_POST['year'] ?? date('Y');
        
        error_log("Generating payroll for month: $month, year: $year");
        
        // Check if employees table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'employees'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            throw new Exception('Employees table does not exist');
        }
        
        // Check if payroll table exists, create if not
        $payrollCheck = $conn->query("SHOW TABLES LIKE 'payroll'");
        if (!$payrollCheck || $payrollCheck->num_rows === 0) {
            error_log("Creating payroll table");
            $createPayroll = "
                CREATE TABLE payroll (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    employee_id INT NOT NULL,
                    pay_month INT NOT NULL,
                    pay_year INT NOT NULL,
                    basic_salary DECIMAL(10,2) NOT NULL DEFAULT 0,
                    earned_salary DECIMAL(10,2) NOT NULL DEFAULT 0,
                    days_worked INT NOT NULL DEFAULT 0,
                    working_days INT NOT NULL DEFAULT 0,
                    pay_date DATETIME NOT NULL,
                    status VARCHAR(20) DEFAULT 'processed',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
            
            if (!$conn->query($createPayroll)) {
                throw new Exception('Failed to create payroll table: ' . $conn->error);
            }
        }
        
        // Get all active employees
        $employeeQuery = $conn->prepare("SELECT * FROM employees WHERE status = 'active'");
        if (!$employeeQuery) {
            throw new Exception('Failed to prepare employee query: ' . $conn->error);
        }
        
        $employeeQuery->execute();
        $employees = $employeeQuery->get_result();
        
        if (!$employees) {
            throw new Exception('Failed to get employees: ' . $conn->error);
        }
        
        $generated = 0;
        $skipped = 0;
        
        while ($employee = $employees->fetch_assoc()) {
            $employee_id = $employee['employee_id'];
            $basic_salary = $employee['monthly_salary'] ?? 0;
            
            error_log("Processing employee ID: $employee_id, Salary: $basic_salary");
            
            // Check if payroll already exists
            $checkQuery = $conn->prepare("SELECT id FROM payroll WHERE employee_id = ? AND pay_month = ? AND pay_year = ?");
            if (!$checkQuery) {
                error_log("Failed to prepare check query: " . $conn->error);
                continue;
            }
            
            $checkQuery->bind_param("iii", $employee_id, $month, $year);
            $checkQuery->execute();
            
            if ($checkQuery->get_result()->num_rows > 0) {
                $skipped++;
                continue; // Skip if already generated
            }
            
            // Calculate working days and actual days worked
            $attendanceQuery = $conn->prepare("SELECT COUNT(*) as days_worked FROM attendance WHERE employee_id = ? AND MONTH(attendance_date) = ? AND YEAR(attendance_date) = ?");
            if (!$attendanceQuery) {
                error_log("Failed to prepare attendance query: " . $conn->error);
                $days_worked = 0;
            } else {
                $attendanceQuery->bind_param("iii", $employee_id, $month, $year);
                $attendanceQuery->execute();
                $result = $attendanceQuery->get_result();
                $days_worked = $result ? $result->fetch_assoc()['days_worked'] : 0;
            }
            
            $working_days = date('t', mktime(0, 0, 0, $month, 1, $year));
            $per_day_salary = $working_days > 0 ? $basic_salary / $working_days : 0;
            $earned_salary = $per_day_salary * $days_worked;
            
            // Insert payroll record
            $payrollQuery = $conn->prepare("INSERT INTO payroll (employee_id, pay_month, pay_year, basic_salary, earned_salary, days_worked, working_days, pay_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'processed')");
            if (!$payrollQuery) {
                error_log("Failed to prepare payroll insert query: " . $conn->error);
                continue;
            }
            
            $payrollQuery->bind_param("iiiddii", $employee_id, $month, $year, $basic_salary, $earned_salary, $days_worked, $working_days);
            
            if ($payrollQuery->execute()) {
                $generated++;
                error_log("Payroll generated for employee ID: $employee_id");
            } else {
                error_log("Failed to insert payroll for employee ID $employee_id: " . $conn->error);
            }
        }
        
        $message = "Payroll generated for $generated employees";
        if ($skipped > 0) {
            $message .= ", $skipped employees skipped (already processed)";
        }
        
        error_log("Payroll generation completed: $message");
        
        // Ensure clean output
        if (ob_get_level()) ob_clean();
        echo json_encode(['success' => true, 'message' => $message, 'generated' => $generated, 'skipped' => $skipped]);
        
    } catch (Exception $e) {
        error_log("hrGeneratePayroll error: " . $e->getMessage());
        // Ensure clean output
        if (ob_get_level()) ob_clean();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function hrGetSystemReports($conn) {
    try {
        $reports = [];
        
        // Employee summary
        $empQuery = $conn->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active FROM employees");
        $empQuery->execute();
        $reports['employees'] = $empQuery->get_result()->fetch_assoc();
        
        // Leave summary
        $leaveQuery = $conn->prepare("SELECT COUNT(*) as total, status FROM leave_requests WHERE YEAR(applied_date) = YEAR(NOW()) GROUP BY status");
        $leaveQuery->execute();
        $leaveResult = $leaveQuery->get_result();
        $reports['leaves'] = [];
        while ($row = $leaveResult->fetch_assoc()) {
            $reports['leaves'][$row['status']] = $row['total'];
        }
        
        // Attendance summary
        $attQuery = $conn->prepare("SELECT COUNT(*) as total FROM attendance WHERE MONTH(attendance_date) = MONTH(NOW()) AND YEAR(attendance_date) = YEAR(NOW())");
        $attQuery->execute();
        $reports['attendance'] = $attQuery->get_result()->fetch_assoc();
        
        // Payroll summary
        $payQuery = $conn->prepare("SELECT COUNT(*) as total, SUM(earned_salary) as total_amount FROM payroll WHERE pay_month = MONTH(NOW()) AND pay_year = YEAR(NOW())");
        $payQuery->execute();
        $reports['payroll'] = $payQuery->get_result()->fetch_assoc();
        
        echo json_encode(['success' => true, 'data' => $reports]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function hrGetDashboardStats($conn) {
    try {
        $stats = [];
        
        // Total employees
        $empQuery = $conn->prepare("SELECT COUNT(*) as total FROM employees WHERE status = 'active'");
        $empQuery->execute();
        $stats['total_employees'] = $empQuery->get_result()->fetch_assoc()['total'];
        
        // Pending leave requests
        $pendingQuery = $conn->prepare("SELECT COUNT(*) as pending FROM leave_requests WHERE status = 'pending'");
        $pendingQuery->execute();
        $stats['pending_leaves'] = $pendingQuery->get_result()->fetch_assoc()['pending'];
        
        // Today's attendance
        $todayQuery = $conn->prepare("SELECT COUNT(*) as present FROM attendance WHERE attendance_date = CURDATE()");
        $todayQuery->execute();
        $stats['present_today'] = $todayQuery->get_result()->fetch_assoc()['present'];
        
        // This month's payroll
        $payrollQuery = $conn->prepare("SELECT COUNT(*) as processed FROM payroll WHERE pay_month = MONTH(NOW()) AND pay_year = YEAR(NOW())");
        $payrollQuery->execute();
        $stats['payroll_processed'] = $payrollQuery->get_result()->fetch_assoc()['processed'];
        
        echo json_encode(['success' => true, 'data' => $stats]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function hrGetPayroll($conn) {
    try {
        // Clean any previous output
        if (ob_get_level()) ob_clean();
        
        $month = $_POST['month'] ?? date('n');
        $year = $_POST['year'] ?? date('Y');
        
        error_log("hrGetPayroll called - Month: $month, Year: $year");
        
        // Check if payroll table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'payroll'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            error_log("Payroll table does not exist");
            echo json_encode(['success' => true, 'data' => [], 'message' => 'No payroll table found. Generate payroll first.']);
            return;
        }
        
        // Check payroll table structure and columns
        $structureResult = $conn->query("DESCRIBE payroll");
        $columns = [];
        while ($row = $structureResult->fetch_assoc()) {
            $columns[] = $row['Field'];    
        }
        
        $hasPayMonth = in_array('pay_month', $columns);
        $hasPayYear = in_array('pay_year', $columns);
        
        error_log("Payroll table columns: " . implode(', ', $columns));
        error_log("Has pay_month: " . ($hasPayMonth ? 'yes' : 'no') . ", Has pay_year: " . ($hasPayYear ? 'yes' : 'no'));
        
        // Add missing columns if needed
        if (!$hasPayMonth) {
            error_log("Adding missing pay_month column");
            $conn->query("ALTER TABLE payroll ADD COLUMN pay_month INT NOT NULL DEFAULT " . intval($month));
            $hasPayMonth = true;
        }
        
        if (!$hasPayYear) {
            error_log("Adding missing pay_year column");
            $conn->query("ALTER TABLE payroll ADD COLUMN pay_year INT NOT NULL DEFAULT " . intval($year));
            $hasPayYear = true;
        }
        
        // Check if employees table exists  
        $empTableCheck = $conn->query("SHOW TABLES LIKE 'employees'");
        if (!$empTableCheck || $empTableCheck->num_rows === 0) {
            error_log("Employees table does not exist");
            throw new Exception('Employees table does not exist');
        }
        
        // Build query based on available columns
        if ($hasPayMonth && $hasPayYear) {
            $query = $conn->prepare("
                SELECT p.*, e.name as employee_name, e.employee_code 
                FROM payroll p 
                JOIN employees e ON p.employee_id = e.employee_id 
                WHERE p.pay_month = ? AND p.pay_year = ? 
                ORDER BY e.name
            ");
            
            if (!$query) {
                throw new Exception('Failed to prepare payroll query: ' . $conn->error);
            }
            
            $query->bind_param("ii", $month, $year);
        } else {
            // Fallback query without month/year filter
            $query = $conn->prepare("
                SELECT p.*, e.name as employee_name, e.employee_code 
                FROM payroll p 
                JOIN employees e ON p.employee_id = e.employee_id 
                ORDER BY e.name LIMIT 50
            ");
            
            if (!$query) {
                throw new Exception('Failed to prepare payroll query: ' . $conn->error);
            }
        }
        
        if (!$query->execute()) {
            throw new Exception('Failed to execute payroll query: ' . $conn->error);
        }
        
        $result = $query->get_result();
        if (!$result) {
            throw new Exception('Failed to get payroll results: ' . $conn->error);
        }
        
        $payroll = [];
        while ($row = $result->fetch_assoc()) {
            $payroll[] = $row;
        }
        
        error_log("Found " . count($payroll) . " payroll records for month $month, year $year");
        
        // Ensure clean output
        if (ob_get_level()) ob_clean();
        echo json_encode(['success' => true, 'data' => $payroll, 'count' => count($payroll)]);
        
    } catch (Exception $e) {
        error_log("hrGetPayroll error: " . $e->getMessage());
        // Ensure clean output
        if (ob_get_level()) ob_clean();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function hrGenerateReport($conn) {
    try {
        $report_type = $_POST['report_type'] ?? '';
        $period = $_POST['period'] ?? 'current_month';
        $format = $_POST['format'] ?? 'pdf';
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        
        if (empty($report_type)) {
            throw new Exception('Report type is required');
        }
        
        // Determine date range
        switch ($period) {
            case 'current_month':
                $start_date = date('Y-m-01');
                $end_date = date('Y-m-t');
                break;
            case 'last_month':
                $start_date = date('Y-m-01', strtotime('last month'));
                $end_date = date('Y-m-t', strtotime('last month'));
                break;
            case 'current_year':
                $start_date = date('Y-01-01');
                $end_date = date('Y-12-31');
                break;
            case 'custom':
                if (empty($start_date) || empty($end_date)) {
                    throw new Exception('Start and end dates are required for custom period');
                }
                break;
        }
        
        $data = [];
        $filename = '';
        
        // Generate report data based on type
        switch ($report_type) {
            case 'attendance':
                $query = $conn->prepare("
                    SELECT a.*, e.name as employee_name, e.employee_code 
                    FROM attendance a 
                    JOIN employees e ON a.employee_id = e.employee_id 
                    WHERE a.attendance_date BETWEEN ? AND ?
                    ORDER BY a.attendance_date DESC, e.name
                ");
                $query->bind_param("ss", $start_date, $end_date);
                $query->execute();
                $result = $query->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
                $filename = "attendance_report_" . date('Y-m-d') . "." . $format;
                break;
                
            case 'payroll':
                $query = $conn->prepare("
                    SELECT p.*, e.name as employee_name, e.employee_code 
                    FROM payroll p 
                    JOIN employees e ON p.employee_id = e.employee_id 
                    WHERE p.pay_date BETWEEN ? AND ?
                    ORDER BY p.pay_date DESC, e.name
                ");
                $query->bind_param("ss", $start_date, $end_date);
                $query->execute();
                $result = $query->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
                $filename = "payroll_report_" . date('Y-m-d') . "." . $format;
                break;
                
            case 'leave':
                $query = $conn->prepare("
                    SELECT lr.*, e.name as employee_name, e.employee_code 
                    FROM leave_requests lr 
                    JOIN employees e ON lr.employee_id = e.employee_id 
                    WHERE lr.applied_date BETWEEN ? AND ?
                    ORDER BY lr.applied_date DESC, e.name
                ");
                $query->bind_param("ss", $start_date, $end_date);
                $query->execute();
                $result = $query->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
                $filename = "leave_report_" . date('Y-m-d') . "." . $format;
                break;
                
            case 'employee':
                $query = $conn->prepare("
                    SELECT * FROM employees 
                    WHERE created_at BETWEEN ? AND ? OR created_at IS NULL
                    ORDER BY name
                ");
                $query->bind_param("ss", $start_date, $end_date);
                $query->execute();
                $result = $query->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
                $filename = "employee_report_" . date('Y-m-d') . "." . $format;
                break;
                
            default:
                throw new Exception('Invalid report type');
        }
        
        // Generate file based on format
        $reports_dir = '../../reports/';
        if (!is_dir($reports_dir)) {
            mkdir($reports_dir, 0755, true);
        }
        
        $file_path = $reports_dir . $filename;
        
        switch ($format) {
            case 'csv':
                $output = generateCSV($data, $report_type);
                file_put_contents($file_path, $output);
                break;
                
            case 'excel':
                $output = generateExcel($data, $report_type);
                file_put_contents($file_path, $output);
                break;
                
            case 'pdf':
            default:
                $output = generatePDF($data, $report_type, $start_date, $end_date);
                file_put_contents($file_path, $output);
                break;
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Report generated successfully',
            'filename' => $filename,
            'download_url' => '../reports/' . $filename,
            'record_count' => count($data)
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function generateCSV($data, $report_type) {
    if (empty($data)) {
        return "No data available for the selected period\n";
    }
    
    $output = '';
    
    // Add headers
    $headers = array_keys($data[0]);
    $output .= implode(',', $headers) . "\n";
    
    // Add data rows
    foreach ($data as $row) {
        $output .= implode(',', array_map(function($value) {
            return '"' . str_replace('"', '""', $value) . '"';
        }, $row)) . "\n";
    }
    
    return $output;
}

function generateExcel($data, $report_type) {
    // For now, return CSV format (can be enhanced with actual Excel library)
    return generateCSV($data, $report_type);
}

function generatePDF($data, $report_type, $start_date, $end_date) {
    // Simple HTML to PDF conversion (basic implementation)
    $html = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .header { text-align: center; margin-bottom: 20px; }
            .period { color: #666; font-size: 11px; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h2>" . ucfirst($report_type) . " Report</h2>
            <div class='period'>Period: " . date('M d, Y', strtotime($start_date)) . " to " . date('M d, Y', strtotime($end_date)) . "</div>
        </div>";
    
    if (empty($data)) {
        $html .= "<p>No data available for the selected period.</p>";
    } else {
        $html .= "<table>";
        
        // Headers
        $html .= "<tr>";
        foreach (array_keys($data[0]) as $header) {
            $html .= "<th>" . ucfirst(str_replace('_', ' ', $header)) . "</th>";
        }
        $html .= "</tr>";
        
        // Data rows
        foreach ($data as $row) {
            $html .= "<tr>";
            foreach ($row as $value) {
                $html .= "<td>" . htmlspecialchars($value) . "</td>";
            }
            $html .= "</tr>";
        }
        
        $html .= "</table>";
    }
    
    $html .= "
        <div style='margin-top: 20px; font-size: 10px; color: #666;'>
            Generated on: " . date('Y-m-d H:i:s') . "
        </div>
    </body>
    </html>";
    
    return $html; // In a real implementation, this would be converted to PDF
}

// ==============================================
// ADVANCED EMPLOYEE FUNCTIONS
// ==============================================

function employeeMobilePunchRequest($conn) {
    try {
        $employee_id = $_SESSION['employee_id'] ?? $_POST['employee_id'] ?? 0;
        $request_type = $_POST['request_type'] ?? '';
        $lat = $_POST['latitude'] ?? null;
        $lng = $_POST['longitude'] ?? null;
        $accuracy = $_POST['accuracy'] ?? null;
        $address = $_POST['address'] ?? '';
        $device_info = json_encode($_POST['device_info'] ?? []);
        
        if (!$employee_id || !$request_type) {
            throw new Exception('Employee ID and request type are required');
        }
        
        $query = $conn->prepare("INSERT INTO mobile_attendance_requests 
                               (employee_id, request_type, requested_time, location_lat, 
                                location_lng, location_accuracy, location_address, device_info) 
                               VALUES (?, ?, NOW(), ?, ?, ?, ?, ?)");
        $query->bind_param("issdds", $employee_id, $request_type, $lat, $lng, $accuracy, $address, $device_info);
        
        if ($query->execute()) {
            $request_id = $conn->insert_id;
            echo json_encode([
                'success' => true, 
                'message' => 'Mobile attendance request submitted successfully',
                'request_id' => $request_id
            ]);
        } else {
            throw new Exception('Failed to submit mobile attendance request');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function employeeGetShiftSchedule($conn) {
    try {
        $employee_id = $_SESSION['employee_id'] ?? $_POST['employee_id'] ?? 0;
        
        if (!$employee_id) {
            throw new Exception('Employee ID is required');
        }
        
        $query = $conn->prepare("SELECT * FROM employee_shifts 
                               WHERE employee_id = ? AND (effective_to IS NULL OR effective_to >= CURDATE()) 
                               ORDER BY effective_from DESC");
        $query->bind_param("i", $employee_id);
        $query->execute();
        $result = $query->get_result();
        
        $shifts = [];
        while ($row = $result->fetch_assoc()) {
            $shifts[] = $row;
        }
        
        echo json_encode(['success' => true, 'data' => $shifts]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function employeeGetPayrollPreview($conn) {
    try {
        $employee_id = $_SESSION['employee_id'] ?? $_POST['employee_id'] ?? 0;
        $period_start = $_POST['period_start'] ?? date('Y-m-01');
        $period_end = $_POST['period_end'] ?? date('Y-m-t');
        
        if (!$employee_id) {
            throw new Exception('Employee ID is required');
        }
        
        // Get current payroll structure
        $query = $conn->prepare("SELECT * FROM payroll_structures 
                               WHERE employee_id = ? AND is_active = 1 
                               ORDER BY effective_from DESC LIMIT 1");
        $query->bind_param("i", $employee_id);
        $query->execute();
        $structure = $query->get_result()->fetch_assoc();
        
        if (!$structure) {
            throw new Exception('No payroll structure found for employee');
        }
        
        // Calculate attendance data
        $att_query = $conn->prepare("SELECT 
                                   COUNT(*) as working_days,
                                   SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
                                   SUM(total_hours) as total_hours,
                                   SUM(overtime_hours) as overtime_hours
                                   FROM enhanced_attendance 
                                   WHERE employee_id = ? AND attendance_date BETWEEN ? AND ?");
        $att_query->bind_param("iss", $employee_id, $period_start, $period_end);
        $att_query->execute();
        $attendance = $att_query->get_result()->fetch_assoc();
        
        // Calculate preview
        $base_salary = $structure['base_salary'];
        $allowances = json_decode($structure['allowances'], true) ?? [];
        $deductions = json_decode($structure['deductions'], true) ?? [];
        
        $total_allowances = array_sum($allowances);
        $total_deductions = array_sum($deductions);
        $overtime_amount = ($attendance['overtime_hours'] ?? 0) * ($structure['hourly_rate'] ?? 0) * 1.5;
        
        $gross_salary = $base_salary + $total_allowances + $overtime_amount;
        $net_salary = $gross_salary - $total_deductions;
        
        $preview = [
            'period' => $period_start . ' to ' . $period_end,
            'base_salary' => $base_salary,
            'allowances' => $allowances,
            'total_allowances' => $total_allowances,
            'overtime_hours' => $attendance['overtime_hours'] ?? 0,
            'overtime_amount' => $overtime_amount,
            'gross_salary' => $gross_salary,
            'deductions' => $deductions,
            'total_deductions' => $total_deductions,
            'net_salary' => $net_salary,
            'attendance' => $attendance
        ];
        
        echo json_encode(['success' => true, 'data' => $preview]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function employeeGetAttendanceAnalytics($conn) {
    try {
        $employee_id = $_SESSION['employee_id'] ?? $_POST['employee_id'] ?? 0;
        $period = $_POST['period'] ?? 'month'; // month, quarter, year
        
        if (!$employee_id) {
            throw new Exception('Employee ID is required');
        }
        
        $date_condition = '';
        switch ($period) {
            case 'week':
                $date_condition = "attendance_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $date_condition = "attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
            case 'quarter':
                $date_condition = "attendance_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
                break;
            case 'year':
                $date_condition = "attendance_date >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)";
                break;
        }
        
        $query = $conn->prepare("SELECT 
                               COUNT(*) as total_days,
                               SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
                               SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                               SUM(CASE WHEN late_minutes > 0 THEN 1 ELSE 0 END) as late_days,
                               AVG(total_hours) as avg_hours_per_day,
                               SUM(total_hours) as total_hours,
                               SUM(overtime_hours) as total_overtime,
                               AVG(late_minutes) as avg_late_minutes
                               FROM enhanced_attendance 
                               WHERE employee_id = ? AND $date_condition");
        $query->bind_param("i", $employee_id);
        $query->execute();
        $stats = $query->get_result()->fetch_assoc();
        
        $attendance_percentage = $stats['total_days'] > 0 ? 
            ($stats['present_days'] / $stats['total_days']) * 100 : 0;
        
        $analytics = [
            'period' => $period,
            'attendance_percentage' => round($attendance_percentage, 2),
            'total_days' => $stats['total_days'],
            'present_days' => $stats['present_days'],
            'absent_days' => $stats['absent_days'],
            'late_days' => $stats['late_days'],
            'punctuality_score' => max(0, 100 - ($stats['avg_late_minutes'] ?? 0)),
            'average_hours_per_day' => round($stats['avg_hours_per_day'] ?? 0, 2),
            'total_hours_worked' => round($stats['total_hours'] ?? 0, 2),
            'total_overtime_hours' => round($stats['total_overtime'] ?? 0, 2)
        ];
        
        echo json_encode(['success' => true, 'data' => $analytics]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function employeeBiometricPunch($conn) {
    try {
        $employee_id = $_POST['employee_id'] ?? 0;
        $device_id = $_POST['device_id'] ?? 0;
        $punch_type = $_POST['punch_type'] ?? 'in'; // in, out, break_start, break_end
        $biometric_data = $_POST['biometric_data'] ?? '';
        
        if (!$employee_id || !$device_id) {
            throw new Exception('Employee ID and Device ID are required');
        }
        
        $today = date('Y-m-d');
        
        // Check if attendance record exists for today
        $check_query = $conn->prepare("SELECT * FROM enhanced_attendance 
                                     WHERE employee_id = ? AND attendance_date = ?");
        $check_query->bind_param("is", $employee_id, $today);
        $check_query->execute();
        $existing = $check_query->get_result()->fetch_assoc();
        
        $current_time = date('Y-m-d H:i:s');
        
        if ($existing) {
            // Update existing record
            $update_field = '';
            switch ($punch_type) {
                case 'in':
                    $update_field = 'punch_in_time';
                    break;
                case 'out':
                    $update_field = 'punch_out_time';
                    break;
                case 'break_start':
                    $update_field = 'break_start_time';
                    break;
                case 'break_end':
                    $update_field = 'break_end_time';
                    break;
            }
            
            $update_query = $conn->prepare("UPDATE enhanced_attendance 
                                          SET $update_field = ?, verification_method = 'biometric', 
                                              device_id = ?, updated_at = NOW() 
                                          WHERE id = ?");
            $update_query->bind_param("sii", $current_time, $device_id, $existing['id']);
            $success = $update_query->execute();
        } else {
            // Create new record
            $field_name = $punch_type === 'in' ? 'punch_in_time' : 'punch_out_time';
            $insert_query = $conn->prepare("INSERT INTO enhanced_attendance 
                                          (employee_id, device_id, attendance_date, $field_name, 
                                           verification_method, status) 
                                          VALUES (?, ?, ?, ?, 'biometric', 'present')");
            $insert_query->bind_param("iiss", $employee_id, $device_id, $today, $current_time);
            $success = $insert_query->execute();
        }
        
        if ($success) {
            echo json_encode([
                'success' => true, 
                'message' => 'Biometric punch recorded successfully',
                'punch_time' => $current_time
            ]);
        } else {
            throw new Exception('Failed to record biometric punch');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function employeeGPSAttendance($conn) {
    try {
        $employee_id = $_SESSION['employee_id'] ?? $_POST['employee_id'] ?? 0;
        $lat = $_POST['latitude'] ?? null;
        $lng = $_POST['longitude'] ?? null;
        $accuracy = $_POST['accuracy'] ?? null;
        $punch_type = $_POST['punch_type'] ?? 'in';
        
        if (!$employee_id || !$lat || !$lng) {
            throw new Exception('Employee ID, latitude, and longitude are required');
        }
        
        $today = date('Y-m-d');
        $current_time = date('Y-m-d H:i:s');
        
        // Check if attendance record exists
        $check_query = $conn->prepare("SELECT * FROM enhanced_attendance 
                                     WHERE employee_id = ? AND attendance_date = ?");
        $check_query->bind_param("is", $employee_id, $today);
        $check_query->execute();
        $existing = $check_query->get_result()->fetch_assoc();
        
        if ($existing) {
            $field_name = $punch_type === 'in' ? 'punch_in_time' : 'punch_out_time';
            $update_query = $conn->prepare("UPDATE enhanced_attendance 
                                          SET $field_name = ?, location_lat = ?, location_lng = ?, 
                                              verification_method = 'gps', updated_at = NOW() 
                                          WHERE id = ?");
            $update_query->bind_param("sddi", $current_time, $lat, $lng, $existing['id']);
            $success = $update_query->execute();
        } else {
            $field_name = $punch_type === 'in' ? 'punch_in_time' : 'punch_out_time';
            $insert_query = $conn->prepare("INSERT INTO enhanced_attendance 
                                          (employee_id, attendance_date, $field_name, location_lat, 
                                           location_lng, verification_method, status) 
                                          VALUES (?, ?, ?, ?, ?, 'gps', 'present')");
            $insert_query->bind_param("issdd", $employee_id, $today, $current_time, $lat, $lng);
            $success = $insert_query->execute();
        }
        
        if ($success) {
            echo json_encode([
                'success' => true, 
                'message' => 'GPS attendance recorded successfully',
                'punch_time' => $current_time,
                'location' => ['lat' => $lat, 'lng' => $lng]
            ]);
        } else {
            throw new Exception('Failed to record GPS attendance');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ==============================================
// ADVANCED MANAGER FUNCTIONS
// ==============================================

function managerApproveOvertime($conn) {
    try {
        $attendance_id = $_POST['attendance_id'] ?? 0;
        $manager_id = $_SESSION['employee_id'] ?? $_POST['manager_id'] ?? 0;
        $action = $_POST['action'] ?? 'approve'; // approve, reject
        
        if (!$attendance_id || !$manager_id) {
            throw new Exception('Attendance ID and Manager ID are required');
        }
        
        $status = $action === 'approve' ? 'approved' : 'rejected';
        
        $query = $conn->prepare("UPDATE enhanced_attendance 
                               SET approved_by = ?, approved_at = NOW() 
                               WHERE id = ? AND overtime_hours > 0");
        $query->bind_param("ii", $manager_id, $attendance_id);
        
        if ($query->execute()) {
            // Log the approval in audit trail
            $audit_query = $conn->prepare("INSERT INTO audit_trail 
                                         (entity_type, entity_id, action, performed_by) 
                                         VALUES ('attendance', ?, ?, ?)");
            $audit_query->bind_param("isi", $attendance_id, $action, $manager_id);
            $audit_query->execute();
            
            echo json_encode([
                'success' => true, 
                'message' => "Overtime $action successfully"
            ]);
        } else {
            throw new Exception("Failed to $action overtime");
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function managerBulkAttendanceApproval($conn) {
    try {
        $attendance_ids = $_POST['attendance_ids'] ?? [];
        $manager_id = $_SESSION['employee_id'] ?? $_POST['manager_id'] ?? 0;
        $action = $_POST['action'] ?? 'approve';
        
        if (empty($attendance_ids) || !$manager_id) {
            throw new Exception('Attendance IDs and Manager ID are required');
        }
        
        $ids_string = implode(',', array_map('intval', $attendance_ids));
        
        $query = $conn->prepare("UPDATE enhanced_attendance 
                               SET approved_by = ?, approved_at = NOW() 
                               WHERE id IN ($ids_string)");
        $query->bind_param("i", $manager_id);
        
        if ($query->execute()) {
            $affected_rows = $query->affected_rows;
            echo json_encode([
                'success' => true, 
                'message' => "$affected_rows attendance records $action successfully"
            ]);
        } else {
            throw new Exception("Failed to $action attendance records");
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function managerGetAttendanceAnomalies($conn) {
    try {
        $manager_id = $_SESSION['employee_id'] ?? $_POST['manager_id'] ?? 0;
        
        if (!$manager_id) {
            throw new Exception('Manager ID is required');
        }
        
        // Get team members
        $team_query = $conn->prepare("SELECT employee_id FROM employees 
                                    WHERE manager_id = ? OR department_id IN (
                                        SELECT department_id FROM employees WHERE employee_id = ?
                                    )");
        $team_query->bind_param("ii", $manager_id, $manager_id);
        $team_query->execute();
        $team_result = $team_query->get_result();
        
        $team_ids = [];
        while ($row = $team_result->fetch_assoc()) {
            $team_ids[] = $row['employee_id'];
        }
        
        if (empty($team_ids)) {
            echo json_encode(['success' => true, 'data' => []]);
            return;
        }
        
        $ids_string = implode(',', $team_ids);
        
        $anomalies_query = $conn->prepare("SELECT 
                                         ea.id,
                                         e.name as employee_name,
                                         ea.attendance_date,
                                         ea.punch_in_time,
                                         ea.punch_out_time,
                                         ea.total_hours,
                                         ea.overtime_hours,
                                         ea.late_minutes,
                                         ea.anomaly_flags,
                                         ea.status
                                         FROM enhanced_attendance ea
                                         JOIN employees e ON ea.employee_id = e.employee_id
                                         WHERE ea.employee_id IN ($ids_string) 
                                         AND (ea.anomaly_flags IS NOT NULL 
                                              OR ea.late_minutes > 30 
                                              OR ea.overtime_hours > 4
                                              OR ea.total_hours < 4)
                                         ORDER BY ea.attendance_date DESC
                                         LIMIT 50");
        $anomalies_query->execute();
        $anomalies = $anomalies_query->get_result()->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $anomalies]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function managerScheduleTeamShifts($conn) {
    try {
        $manager_id = $_SESSION['employee_id'] ?? $_POST['manager_id'] ?? 0;
        $shifts_data = $_POST['shifts'] ?? [];
        
        if (!$manager_id || empty($shifts_data)) {
            throw new Exception('Manager ID and shifts data are required');
        }
        
        $success_count = 0;
        
        foreach ($shifts_data as $shift) {
            $employee_id = $shift['employee_id'] ?? 0;
            $shift_name = $shift['shift_name'] ?? '';
            $start_time = $shift['start_time'] ?? '';
            $end_time = $shift['end_time'] ?? '';
            $effective_from = $shift['effective_from'] ?? date('Y-m-d');
            
            if ($employee_id && $shift_name && $start_time && $end_time) {
                $query = $conn->prepare("INSERT INTO employee_shifts 
                                       (employee_id, shift_name, start_time, end_time, effective_from) 
                                       VALUES (?, ?, ?, ?, ?)");
                $query->bind_param("issss", $employee_id, $shift_name, $start_time, $end_time, $effective_from);
                
                if ($query->execute()) {
                    $success_count++;
                }
            }
        }
        
        echo json_encode([
            'success' => true, 
            'message' => "$success_count shifts scheduled successfully"
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function managerGetPayrollPreview($conn) {
    try {
        $manager_id = $_SESSION['employee_id'] ?? $_POST['manager_id'] ?? 0;
        $period_start = $_POST['period_start'] ?? date('Y-m-01');
        $period_end = $_POST['period_end'] ?? date('Y-m-t');
        
        if (!$manager_id) {
            throw new Exception('Manager ID is required');
        }
        
        // Get team members payroll data
        $team_query = $conn->prepare("SELECT employee_id FROM employees 
                                    WHERE manager_id = ? OR department_id IN (
                                        SELECT department_id FROM employees WHERE employee_id = ?
                                    )");
        $team_query->bind_param("ii", $manager_id, $manager_id);
        $team_query->execute();
        $team_result = $team_query->get_result();
        
        $payroll_previews = [];
        
        while ($team_member = $team_result->fetch_assoc()) {
            $emp_id = $team_member['employee_id'];
            
            // Get payroll structure
            $structure_query = $conn->prepare("SELECT * FROM payroll_structures 
                                             WHERE employee_id = ? AND is_active = 1 
                                             ORDER BY effective_from DESC LIMIT 1");
            $structure_query->bind_param("i", $emp_id);
            $structure_query->execute();
            $structure = $structure_query->get_result()->fetch_assoc();
            
            if ($structure) {
                // Calculate attendance
                $att_query = $conn->prepare("SELECT 
                                           COUNT(*) as working_days,
                                           SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
                                           SUM(total_hours) as total_hours,
                                           SUM(overtime_hours) as overtime_hours
                                           FROM enhanced_attendance 
                                           WHERE employee_id = ? AND attendance_date BETWEEN ? AND ?");
                $att_query->bind_param("iss", $emp_id, $period_start, $period_end);
                $att_query->execute();
                $attendance = $att_query->get_result()->fetch_assoc();
                
                $payroll_previews[] = [
                    'employee_id' => $emp_id,
                    'base_salary' => $structure['base_salary'],
                    'attendance' => $attendance,
                    'estimated_net_salary' => $structure['base_salary'] * ($attendance['present_days'] / $attendance['working_days'])
                ];
            }
        }
        
        echo json_encode(['success' => true, 'data' => $payroll_previews]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ==============================================
// ADVANCED HR FUNCTIONS
// ==============================================

function hrProcessBulkAttendance($conn) {
    try {
        $attendance_data = $_POST['attendance_data'] ?? [];
        $processed_by = $_SESSION['employee_id'] ?? $_POST['processed_by'] ?? 0;
        
        if (empty($attendance_data) || !$processed_by) {
            throw new Exception('Attendance data and processor ID are required');
        }
        
        $success_count = 0;
        $errors = [];
        
        foreach ($attendance_data as $record) {
            $employee_id = $record['employee_id'] ?? 0;
            $attendance_date = $record['attendance_date'] ?? '';
            $punch_in = $record['punch_in_time'] ?? null;
            $punch_out = $record['punch_out_time'] ?? null;
            $status = $record['status'] ?? 'present';
            
            if ($employee_id && $attendance_date) {
                // Check if record exists
                $check_query = $conn->prepare("SELECT id FROM enhanced_attendance 
                                             WHERE employee_id = ? AND attendance_date = ?");
                $check_query->bind_param("is", $employee_id, $attendance_date);
                $check_query->execute();
                $existing = $check_query->get_result()->fetch_assoc();
                
                if ($existing) {
                    // Update existing
                    $update_query = $conn->prepare("UPDATE enhanced_attendance 
                                                  SET punch_in_time = ?, punch_out_time = ?, 
                                                      status = ?, verification_method = 'manual' 
                                                  WHERE id = ?");
                    $update_query->bind_param("sssi", $punch_in, $punch_out, $status, $existing['id']);
                    
                    if ($update_query->execute()) {
                        $success_count++;
                    } else {
                        $errors[] = "Failed to update attendance for employee $employee_id on $attendance_date";
                    }
                } else {
                    // Insert new
                    $insert_query = $conn->prepare("INSERT INTO enhanced_attendance 
                                                  (employee_id, attendance_date, punch_in_time, 
                                                   punch_out_time, status, verification_method) 
                                                  VALUES (?, ?, ?, ?, ?, 'manual')");
                    $insert_query->bind_param("issss", $employee_id, $attendance_date, $punch_in, $punch_out, $status);
                    
                    if ($insert_query->execute()) {
                        $success_count++;
                    } else {
                        $errors[] = "Failed to insert attendance for employee $employee_id on $attendance_date";
                    }
                }
            }
        }
        
        echo json_encode([
            'success' => true, 
            'message' => "$success_count records processed successfully",
            'errors' => $errors
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function hrConfigureOvertimeRules($conn) {
    try {
        $rule_name = $_POST['rule_name'] ?? '';
        $department = $_POST['department'] ?? null;
        $daily_threshold = $_POST['daily_threshold_hours'] ?? 8.0;
        $weekly_threshold = $_POST['weekly_threshold_hours'] ?? 40.0;
        $overtime_multiplier = $_POST['overtime_multiplier'] ?? 1.5;
        $double_overtime_threshold = $_POST['double_overtime_threshold'] ?? 12.0;
        $double_overtime_multiplier = $_POST['double_overtime_multiplier'] ?? 2.0;
        
        if (!$rule_name) {
            throw new Exception('Rule name is required');
        }
        
        if (isset($_POST['rule_id']) && $_POST['rule_id']) {
            // Update existing rule
            $rule_id = $_POST['rule_id'];
            $query = $conn->prepare("UPDATE overtime_rules 
                                   SET rule_name = ?, department = ?, daily_threshold_hours = ?, 
                                       weekly_threshold_hours = ?, overtime_multiplier = ?, 
                                       double_overtime_threshold = ?, double_overtime_multiplier = ? 
                                   WHERE id = ?");
            $query->bind_param("ssdddddi", $rule_name, $department, $daily_threshold, 
                             $weekly_threshold, $overtime_multiplier, $double_overtime_threshold, 
                             $double_overtime_multiplier, $rule_id);
        } else {
            // Create new rule
            $query = $conn->prepare("INSERT INTO overtime_rules 
                                   (rule_name, department, daily_threshold_hours, weekly_threshold_hours, 
                                    overtime_multiplier, double_overtime_threshold, double_overtime_multiplier) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?)");
            $query->bind_param("ssddddd", $rule_name, $department, $daily_threshold, 
                             $weekly_threshold, $overtime_multiplier, $double_overtime_threshold, 
                             $double_overtime_multiplier);
        }
        
        if ($query->execute()) {
            echo json_encode(['success' => true, 'message' => 'Overtime rule configured successfully']);
        } else {
            throw new Exception('Failed to configure overtime rule');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function hrSetupPayrollStructures($conn) {
    try {
        $employee_id = $_POST['employee_id'] ?? 0;
        $pay_type = $_POST['pay_type'] ?? 'monthly';
        $base_salary = $_POST['base_salary'] ?? 0;
        $hourly_rate = $_POST['hourly_rate'] ?? null;
        $allowances = json_encode($_POST['allowances'] ?? []);
        $deductions = json_encode($_POST['deductions'] ?? []);
        $effective_from = $_POST['effective_from'] ?? date('Y-m-d');
        
        if (!$employee_id || !$base_salary) {
            throw new Exception('Employee ID and base salary are required');
        }
        
        // Deactivate existing structures
        $deactivate_query = $conn->prepare("UPDATE payroll_structures 
                                          SET is_active = 0, effective_to = DATE_SUB(?, INTERVAL 1 DAY) 
                                          WHERE employee_id = ? AND is_active = 1");
        $deactivate_query->bind_param("si", $effective_from, $employee_id);
        $deactivate_query->execute();
        
        // Insert new structure
        $insert_query = $conn->prepare("INSERT INTO payroll_structures 
                                      (employee_id, pay_type, base_salary, hourly_rate, 
                                       allowances, deductions, effective_from) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?)");
        $insert_query->bind_param("isddsss", $employee_id, $pay_type, $base_salary, 
                                $hourly_rate, $allowances, $deductions, $effective_from);
        
        if ($insert_query->execute()) {
            echo json_encode(['success' => true, 'message' => 'Payroll structure configured successfully']);
        } else {
            throw new Exception('Failed to configure payroll structure');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function hrAnalyticsDashboard($conn) {
    try {
        $period = $_POST['period'] ?? 'month';
        
        $date_condition = '';
        switch ($period) {
            case 'week':
                $date_condition = "attendance_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $date_condition = "attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
            case 'quarter':
                $date_condition = "attendance_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
                break;
            case 'year':
                $date_condition = "attendance_date >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)";
                break;
        }
        
        // Overall attendance statistics
        $overall_query = $conn->prepare("SELECT 
                                       COUNT(DISTINCT employee_id) as total_employees,
                                       COUNT(*) as total_records,
                                       SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
                                       SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                                       SUM(CASE WHEN late_minutes > 0 THEN 1 ELSE 0 END) as late_count,
                                       AVG(total_hours) as avg_hours_per_day,
                                       SUM(overtime_hours) as total_overtime_hours
                                       FROM enhanced_attendance 
                                       WHERE $date_condition");
        $overall_query->execute();
        $overall_stats = $overall_query->get_result()->fetch_assoc();
        
        // Department-wise statistics
        $dept_query = $conn->prepare("SELECT 
                                    e.department,
                                    COUNT(DISTINCT ea.employee_id) as dept_employees,
                                    AVG(CASE WHEN ea.status = 'present' THEN 1 ELSE 0 END) * 100 as attendance_percentage,
                                    AVG(ea.total_hours) as avg_hours,
                                    SUM(ea.overtime_hours) as dept_overtime
                                    FROM employees e
                                    LEFT JOIN enhanced_attendance ea ON e.employee_id = ea.employee_id
                                    WHERE $date_condition
                                    GROUP BY e.department");
        $dept_query->execute();
        $dept_stats = $dept_query->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Trend analysis
        $trend_query = $conn->prepare("SELECT 
                                     DATE(attendance_date) as date,
                                     COUNT(*) as total_punches,
                                     SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
                                     AVG(total_hours) as avg_hours
                                     FROM enhanced_attendance 
                                     WHERE $date_condition
                                     GROUP BY DATE(attendance_date)
                                     ORDER BY date");
        $trend_query->execute();
        $trends = $trend_query->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $analytics = [
            'period' => $period,
            'overall_stats' => $overall_stats,
            'department_stats' => $dept_stats,
            'trends' => $trends,
            'attendance_percentage' => $overall_stats['total_records'] > 0 ? 
                ($overall_stats['present_count'] / $overall_stats['total_records']) * 100 : 0
        ];
        
        echo json_encode(['success' => true, 'data' => $analytics]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function hrComplianceMonitor($conn) {
    try {
        // Check for compliance violations
        $violations = [];
        
        // 1. Excessive overtime violations
        $overtime_query = $conn->prepare("SELECT 
                                        e.name,
                                        e.employee_id,
                                        SUM(ea.overtime_hours) as total_overtime,
                                        COUNT(*) as days_with_overtime
                                        FROM employees e
                                        JOIN enhanced_attendance ea ON e.employee_id = ea.employee_id
                                        WHERE ea.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                                        AND ea.overtime_hours > 0
                                        GROUP BY e.employee_id, e.name
                                        HAVING total_overtime > 40 OR days_with_overtime > 15");
        $overtime_query->execute();
        $overtime_violations = $overtime_query->get_result()->fetch_all(MYSQLI_ASSOC);
        
        foreach ($overtime_violations as $violation) {
            $violations[] = [
                'type' => 'Excessive Overtime',
                'employee' => $violation['name'],
                'details' => "Total overtime: {$violation['total_overtime']} hours in 30 days",
                'severity' => 'high'
            ];
        }
        
        // 2. Poor attendance violations
        $attendance_query = $conn->prepare("SELECT 
                                          e.name,
                                          e.employee_id,
                                          COUNT(*) as total_days,
                                          SUM(CASE WHEN ea.status = 'present' THEN 1 ELSE 0 END) as present_days
                                          FROM employees e
                                          LEFT JOIN enhanced_attendance ea ON e.employee_id = ea.employee_id
                                          WHERE ea.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                                          GROUP BY e.employee_id, e.name
                                          HAVING (present_days / total_days) < 0.8");
        $attendance_query->execute();
        $attendance_violations = $attendance_query->get_result()->fetch_all(MYSQLI_ASSOC);
        
        foreach ($attendance_violations as $violation) {
            $percentage = ($violation['present_days'] / $violation['total_days']) * 100;
            $violations[] = [
                'type' => 'Poor Attendance',
                'employee' => $violation['name'],
                'details' => "Attendance: " . round($percentage, 1) . "% in last 30 days",
                'severity' => $percentage < 60 ? 'high' : 'medium'
            ];
        }
        
        // 3. Unapproved overtime
        $unapproved_query = $conn->prepare("SELECT 
                                          e.name,
                                          COUNT(*) as unapproved_overtime_days
                                          FROM employees e
                                          JOIN enhanced_attendance ea ON e.employee_id = ea.employee_id
                                          WHERE ea.overtime_hours > 0 
                                          AND ea.approved_by IS NULL
                                          AND ea.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                                          GROUP BY e.employee_id, e.name");
        $unapproved_query->execute();
        $unapproved_violations = $unapproved_query->get_result()->fetch_all(MYSQLI_ASSOC);
        
        foreach ($unapproved_violations as $violation) {
            $violations[] = [
                'type' => 'Unapproved Overtime',
                'employee' => $violation['name'],
                'details' => "{$violation['unapproved_overtime_days']} days of unapproved overtime",
                'severity' => 'medium'
            ];
        }
        
        echo json_encode(['success' => true, 'data' => $violations]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function hrBiometricDeviceManagement($conn) {
    try {
        $action = $_POST['device_action'] ?? 'list';
        
        switch ($action) {
            case 'list':
                $query = $conn->prepare("SELECT * FROM attendance_devices ORDER BY device_name");
                $query->execute();
                $devices = $query->get_result()->fetch_all(MYSQLI_ASSOC);
                echo json_encode(['success' => true, 'data' => $devices]);
                break;
                
            case 'add':
                $device_name = $_POST['device_name'] ?? '';
                $device_type = $_POST['device_type'] ?? '';
                $location = $_POST['location'] ?? '';
                
                if (!$device_name || !$device_type) {
                    throw new Exception('Device name and type are required');
                }
                
                $insert_query = $conn->prepare("INSERT INTO attendance_devices 
                                              (device_name, device_type, location) 
                                              VALUES (?, ?, ?)");
                $insert_query->bind_param("sss", $device_name, $device_type, $location);
                
                if ($insert_query->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Device added successfully']);
                } else {
                    throw new Exception('Failed to add device');
                }
                break;
                
            case 'update_status':
                $device_id = $_POST['device_id'] ?? 0;
                $is_active = $_POST['is_active'] ?? 1;
                
                $update_query = $conn->prepare("UPDATE attendance_devices 
                                              SET is_active = ? WHERE id = ?");
                $update_query->bind_param("ii", $is_active, $device_id);
                
                if ($update_query->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Device status updated']);
                } else {
                    throw new Exception('Failed to update device status');
                }
                break;
                
            default:
                throw new Exception('Invalid device action');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function hrAdvancedReporting($conn) {
    try {
        $report_type = $_POST['report_type'] ?? 'attendance_summary';
        $period_start = $_POST['period_start'] ?? date('Y-m-01');
        $period_end = $_POST['period_end'] ?? date('Y-m-t');
        $department = $_POST['department'] ?? null;
        
        $report_data = [];
        
        switch ($report_type) {
            case 'attendance_summary':
                $query = "SELECT 
                         e.name,
                         e.employee_code,
                         e.department,
                         COUNT(ea.id) as total_days,
                         SUM(CASE WHEN ea.status = 'present' THEN 1 ELSE 0 END) as present_days,
                         SUM(CASE WHEN ea.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                         SUM(CASE WHEN ea.late_minutes > 0 THEN 1 ELSE 0 END) as late_days,
                         AVG(ea.total_hours) as avg_hours_per_day,
                         SUM(ea.overtime_hours) as total_overtime
                         FROM employees e
                         LEFT JOIN enhanced_attendance ea ON e.employee_id = ea.employee_id
                         WHERE ea.attendance_date BETWEEN ? AND ?";
                
                if ($department) {
                    $query .= " AND e.department = ?";
                }
                
                $query .= " GROUP BY e.employee_id ORDER BY e.name";
                
                $stmt = $conn->prepare($query);
                if ($department) {
                    $stmt->bind_param("sss", $period_start, $period_end, $department);
                } else {
                    $stmt->bind_param("ss", $period_start, $period_end);
                }
                
                $stmt->execute();
                $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                break;
                
            case 'payroll_summary':
                $query = "SELECT 
                         e.name,
                         e.employee_code,
                         ps.base_salary,
                         ep.gross_salary,
                         ep.net_salary,
                         ep.total_working_days,
                         ep.actual_working_days,
                         ep.attendance_percentage
                         FROM employees e
                         LEFT JOIN payroll_structures ps ON e.employee_id = ps.employee_id AND ps.is_active = 1
                         LEFT JOIN enhanced_payroll ep ON e.employee_id = ep.employee_id 
                         AND ep.payroll_period_start = ? AND ep.payroll_period_end = ?";
                
                if ($department) {
                    $query .= " WHERE e.department = ?";
                }
                
                $query .= " ORDER BY e.name";
                
                $stmt = $conn->prepare($query);
                if ($department) {
                    $stmt->bind_param("sss", $period_start, $period_end, $department);
                } else {
                    $stmt->bind_param("ss", $period_start, $period_end);
                }
                
                $stmt->execute();
                $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                break;
                
            case 'overtime_analysis':
                $query = "SELECT 
                         e.name,
                         e.department,
                         SUM(ea.overtime_hours) as total_overtime,
                         COUNT(CASE WHEN ea.overtime_hours > 0 THEN 1 END) as overtime_days,
                         AVG(ea.overtime_hours) as avg_overtime_per_day,
                         SUM(CASE WHEN ea.approved_by IS NOT NULL THEN ea.overtime_hours ELSE 0 END) as approved_overtime
                         FROM employees e
                         JOIN enhanced_attendance ea ON e.employee_id = ea.employee_id
                         WHERE ea.attendance_date BETWEEN ? AND ? AND ea.overtime_hours > 0";
                
                if ($department) {
                    $query .= " AND e.department = ?";
                }
                
                $query .= " GROUP BY e.employee_id ORDER BY total_overtime DESC";
                
                $stmt = $conn->prepare($query);
                if ($department) {
                    $stmt->bind_param("sss", $period_start, $period_end, $department);
                } else {
                    $stmt->bind_param("ss", $period_start, $period_end);
                }
                
                $stmt->execute();
                $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                break;
        }
        
        echo json_encode([
            'success' => true, 
            'data' => $report_data,
            'report_type' => $report_type,
            'period' => "$period_start to $period_end"
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function hrWorkflowManagement($conn) {
    try {
        $action = $_POST['workflow_action'] ?? 'list';
        
        switch ($action) {
            case 'list':
                $query = $conn->prepare("SELECT * FROM approval_workflows ORDER BY workflow_name");
                $query->execute();
                $workflows = $query->get_result()->fetch_all(MYSQLI_ASSOC);
                echo json_encode(['success' => true, 'data' => $workflows]);
                break;
                
            case 'create':
                $workflow_name = $_POST['workflow_name'] ?? '';
                $entity_type = $_POST['entity_type'] ?? '';
                $department = $_POST['department'] ?? null;
                $approval_levels = json_encode($_POST['approval_levels'] ?? []);
                
                if (!$workflow_name || !$entity_type) {
                    throw new Exception('Workflow name and entity type are required');
                }
                
                $insert_query = $conn->prepare("INSERT INTO approval_workflows 
                                              (workflow_name, entity_type, department, approval_levels) 
                                              VALUES (?, ?, ?, ?)");
                $insert_query->bind_param("ssss", $workflow_name, $entity_type, $department, $approval_levels);
                
                if ($insert_query->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Workflow created successfully']);
                } else {
                    throw new Exception('Failed to create workflow');
                }
                break;
                
            case 'update_status':
                $workflow_id = $_POST['workflow_id'] ?? 0;
                $is_active = $_POST['is_active'] ?? 1;
                
                $update_query = $conn->prepare("UPDATE approval_workflows 
                                              SET is_active = ? WHERE id = ?");
                $update_query->bind_param("ii", $is_active, $workflow_id);
                
                if ($update_query->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Workflow status updated']);
                } else {
                    throw new Exception('Failed to update workflow status');
                }
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function hrMLInsights($conn) {
    try {
        $insight_type = $_POST['insight_type'] ?? 'attendance_prediction';
        
        switch ($insight_type) {
            case 'attendance_prediction':
                // Simple predictive analytics based on historical data
                $prediction_query = $conn->prepare("SELECT 
                                                  e.employee_id,
                                                  e.name,
                                                  AVG(CASE WHEN DAYOFWEEK(ea.attendance_date) = 2 THEN 
                                                      CASE WHEN ea.status = 'present' THEN 1 ELSE 0 END END) as monday_attendance,
                                                  AVG(CASE WHEN DAYOFWEEK(ea.attendance_date) = 6 THEN 
                                                      CASE WHEN ea.status = 'present' THEN 1 ELSE 0 END END) as friday_attendance,
                                                  AVG(CASE WHEN ea.status = 'present' THEN 1 ELSE 0 END) as overall_attendance,
                                                  VARIANCE(CASE WHEN ea.status = 'present' THEN 1 ELSE 0 END) as attendance_variance
                                                  FROM employees e
                                                  LEFT JOIN enhanced_attendance ea ON e.employee_id = ea.employee_id
                                                  WHERE ea.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                                                  GROUP BY e.employee_id, e.name
                                                  HAVING overall_attendance < 0.9
                                                  ORDER BY attendance_variance DESC");
                $prediction_query->execute();
                $predictions = $prediction_query->get_result()->fetch_all(MYSQLI_ASSOC);
                
                // Add risk scores
                foreach ($predictions as &$prediction) {
                    $risk_score = 0;
                    if ($prediction['overall_attendance'] < 0.7) $risk_score += 40;
                    if ($prediction['monday_attendance'] < 0.8) $risk_score += 30;
                    if ($prediction['friday_attendance'] < 0.8) $risk_score += 20;
                    if ($prediction['attendance_variance'] > 0.1) $risk_score += 10;
                    
                    $prediction['risk_score'] = min(100, $risk_score);
                    $prediction['risk_level'] = $risk_score > 70 ? 'High' : ($risk_score > 40 ? 'Medium' : 'Low');
                }
                
                echo json_encode(['success' => true, 'data' => $predictions, 'type' => 'attendance_prediction']);
                break;
                
            case 'cost_analysis':
                $cost_query = $conn->prepare("SELECT 
                                            e.department,
                                            COUNT(DISTINCT e.employee_id) as employee_count,
                                            AVG(ps.base_salary) as avg_salary,
                                            SUM(ep.gross_salary) as total_payroll_cost,
                                            SUM(ea.overtime_hours * (ps.hourly_rate * 1.5)) as overtime_cost,
                                            AVG(ea.total_hours) as avg_hours_per_employee
                                            FROM employees e
                                            LEFT JOIN payroll_structures ps ON e.employee_id = ps.employee_id AND ps.is_active = 1
                                            LEFT JOIN enhanced_payroll ep ON e.employee_id = ep.employee_id 
                                            AND ep.payroll_period_start >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                                            LEFT JOIN enhanced_attendance ea ON e.employee_id = ea.employee_id
                                            AND ea.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                                            GROUP BY e.department
                                            ORDER BY total_payroll_cost DESC");
                $cost_query->execute();
                $cost_analysis = $cost_query->get_result()->fetch_all(MYSQLI_ASSOC);
                
                echo json_encode(['success' => true, 'data' => $cost_analysis, 'type' => 'cost_analysis']);
                break;
                
            case 'performance_trends':
                $trends_query = $conn->prepare("SELECT 
                                              DATE_FORMAT(ea.attendance_date, '%Y-%m') as month,
                                              AVG(CASE WHEN ea.status = 'present' THEN 1 ELSE 0 END) * 100 as attendance_rate,
                                              AVG(ea.total_hours) as avg_hours,
                                              SUM(ea.overtime_hours) as total_overtime,
                                              COUNT(DISTINCT ea.employee_id) as active_employees
                                              FROM enhanced_attendance ea
                                              WHERE ea.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                                              GROUP BY DATE_FORMAT(ea.attendance_date, '%Y-%m')
                                              ORDER BY month");
                $trends_query->execute();
                $trends = $trends_query->get_result()->fetch_all(MYSQLI_ASSOC);
                
                echo json_encode(['success' => true, 'data' => $trends, 'type' => 'performance_trends']);
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

?>
