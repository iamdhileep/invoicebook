<?php
header('Content-Type: application/json');
session_start();

// Include database with absolute path to avoid issues
$dbPath = dirname(__FILE__) . '/../../db.php';
include $dbPath;

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_profile':
        getEmployeeProfile($conn);
        break;
    case 'get_stats':
        getEmployeeStats($conn);
        break;
    case 'get_leave_history':
        getLeaveHistory($conn);
        break;
    case 'submit_leave_request':
        submitLeaveRequest($conn);
        break;
    case 'cancel_leave':
        cancelLeaveRequest($conn);
        break;
    case 'get_attendance_history':
        getAttendanceHistory($conn);
        break;
    case 'get_today_attendance':
        getTodayAttendance($conn);
        break;
    case 'check_in':
        checkIn($conn);
        break;
    case 'check_out':
        checkOut($conn);
        break;
    case 'get_payslips':
        getPayslips($conn);
        break;
    case 'download_payslip':
        downloadPayslip($conn);
        break;
    case 'update_profile':
        updateProfile($conn);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getEmployeeProfile($conn) {
    try {
        $employee_id = $_POST['employee_id'] ?? 0;
        
        if (!$employee_id) {
            throw new Exception('Employee ID is required');
        }
        
        $query = $conn->prepare("SELECT * FROM employees WHERE employee_id = ?");
        $query->bind_param("i", $employee_id);
        $query->execute();
        $result = $query->get_result();
        
        if ($result->num_rows > 0) {
            $profile = $result->fetch_assoc();
            echo json_encode(['success' => true, 'profile' => $profile]);
        } else {
            throw new Exception('Employee not found');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getEmployeeStats($conn) {
    try {
        $employee_id = $_POST['employee_id'] ?? 0;
        
        if (!$employee_id) {
            throw new Exception('Employee ID is required');
        }
        
        // Get leave balance
        $leaveQuery = $conn->prepare("SELECT total_leave_days, used_days FROM employee_leave_balance WHERE employee_id = ?");
        $leaveQuery->bind_param("i", $employee_id);
        $leaveQuery->execute();
        $leaveResult = $leaveQuery->get_result();
        
        $totalLeaveBalance = 25; // Default
        $usedLeaves = 0;
        
        if ($leaveResult->num_rows > 0) {
            $leaveData = $leaveResult->fetch_assoc();
            $totalLeaveBalance = $leaveData['total_leave_days'];
            $usedLeaves = $leaveData['used_days'];
        }
        
        // Get monthly working hours
        $monthlyHoursQuery = $conn->prepare("SELECT SUM(TIMESTAMPDIFF(HOUR, check_in_time, check_out_time)) as total_hours 
                                            FROM attendance 
                                            WHERE employee_id = ? 
                                            AND MONTH(attendance_date) = MONTH(CURDATE()) 
                                            AND YEAR(attendance_date) = YEAR(CURDATE())
                                            AND check_in_time IS NOT NULL 
                                            AND check_out_time IS NOT NULL");
        $monthlyHoursQuery->bind_param("i", $employee_id);
        $monthlyHoursQuery->execute();
        $hoursResult = $monthlyHoursQuery->get_result()->fetch_assoc();
        $monthlyHours = $hoursResult['total_hours'] ?? 0;
        
        // Get pending requests
        $pendingQuery = $conn->prepare("SELECT COUNT(*) as total FROM leave_requests WHERE employee_id = ? AND status = 'pending'");
        $pendingQuery->bind_param("i", $employee_id);
        $pendingQuery->execute();
        $pendingResult = $pendingQuery->get_result()->fetch_assoc();
        $pendingRequests = $pendingResult['total'] ?? 0;
        
        // Get last payslip
        $payQuery = $conn->prepare("SELECT net_pay, DATE_FORMAT(pay_period, '%M %Y') as pay_month 
                                   FROM payroll 
                                   WHERE employee_id = ? 
                                   ORDER BY pay_period DESC 
                                   LIMIT 1");
        $payQuery->bind_param("i", $employee_id);
        $payQuery->execute();
        $payResult = $payQuery->get_result();
        
        $lastPayAmount = 0;
        $lastPayMonth = '-';
        
        if ($payResult->num_rows > 0) {
            $payData = $payResult->fetch_assoc();
            $lastPayAmount = $payData['net_pay'];
            $lastPayMonth = $payData['pay_month'];
        }
        
        echo json_encode([
            'success' => true,
            'stats' => [
                'total_leave_balance' => $totalLeaveBalance,
                'used_leaves' => $usedLeaves,
                'leave_balance' => $totalLeaveBalance - $usedLeaves,
                'monthly_hours' => $monthlyHours,
                'pending_requests' => $pendingRequests,
                'last_pay_amount' => $lastPayAmount,
                'last_pay_month' => $lastPayMonth
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getLeaveHistory($conn) {
    try {
        $employee_id = $_POST['employee_id'] ?? 0;
        
        if (!$employee_id) {
            throw new Exception('Employee ID is required');
        }
        
        $query = $conn->prepare("SELECT lr.*, lt.name as leave_type, 
                                DATEDIFF(lr.to_date, lr.from_date) + 1 as days,
                                DATE_FORMAT(lr.created_at, '%Y-%m-%d') as created_at
                                FROM leave_requests lr 
                                LEFT JOIN leave_types lt ON lr.leave_type_id = lt.id 
                                WHERE lr.employee_id = ? 
                                ORDER BY lr.created_at DESC");
        $query->bind_param("i", $employee_id);
        $query->execute();
        $result = $query->get_result();
        
        $leaves = [];
        while ($row = $result->fetch_assoc()) {
            $leaves[] = $row;
        }
        
        echo json_encode(['success' => true, 'leaves' => $leaves]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function submitLeaveRequest($conn) {
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

function cancelLeaveRequest($conn) {
    try {
        $leave_id = $_POST['leave_id'] ?? 0;
        
        if (!$leave_id) {
            throw new Exception('Leave ID is required');
        }
        
        // Check if leave can be cancelled (only pending leaves)
        $checkQuery = $conn->prepare("SELECT status FROM leave_requests WHERE id = ?");
        $checkQuery->bind_param("i", $leave_id);
        $checkQuery->execute();
        $result = $checkQuery->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Leave request not found');
        }
        
        $leave = $result->fetch_assoc();
        if ($leave['status'] !== 'pending') {
            throw new Exception('Only pending leave requests can be cancelled');
        }
        
        $query = $conn->prepare("UPDATE leave_requests SET status = 'cancelled' WHERE id = ?");
        $query->bind_param("i", $leave_id);
        
        if ($query->execute()) {
            echo json_encode(['success' => true, 'message' => 'Leave request cancelled successfully']);
        } else {
            throw new Exception('Failed to cancel leave request');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getAttendanceHistory($conn) {
    try {
        $employee_id = $_POST['employee_id'] ?? 0;
        $month = $_POST['month'] ?? date('Y-m');
        
        if (!$employee_id) {
            throw new Exception('Employee ID is required');
        }
        
        $query = $conn->prepare("SELECT *, 
                                DATE_FORMAT(attendance_date, '%Y-%m-%d') as attendance_date,
                                TIME_FORMAT(check_in_time, '%H:%i') as check_in,
                                TIME_FORMAT(check_out_time, '%H:%i') as check_out,
                                CASE 
                                    WHEN check_in_time IS NOT NULL AND check_out_time IS NOT NULL 
                                    THEN TIME_FORMAT(TIMEDIFF(check_out_time, check_in_time), '%H:%i')
                                    ELSE NULL 
                                END as working_hours
                                FROM attendance 
                                WHERE employee_id = ? 
                                AND DATE_FORMAT(attendance_date, '%Y-%m') = ?
                                ORDER BY attendance_date DESC");
        $query->bind_param("is", $employee_id, $month);
        $query->execute();
        $result = $query->get_result();
        
        $attendance = [];
        $presentDays = 0;
        $absentDays = 0;
        $lateDays = 0;
        $totalHours = 0;
        
        while ($row = $result->fetch_assoc()) {
            $attendance[] = $row;
            
            // Calculate stats
            if ($row['status'] === 'present') {
                $presentDays++;
                if ($row['check_in_time'] > '09:15:00') { // Assuming 9:15 AM is late
                    $lateDays++;
                }
                
                if ($row['check_in_time'] && $row['check_out_time']) {
                    $checkIn = new DateTime($row['check_in_time']);
                    $checkOut = new DateTime($row['check_out_time']);
                    $diff = $checkIn->diff($checkOut);
                    $totalHours += $diff->h + ($diff->i / 60);
                }
            } elseif ($row['status'] === 'absent') {
                $absentDays++;
            }
        }
        
        echo json_encode([
            'success' => true,
            'attendance' => $attendance,
            'stats' => [
                'present_days' => $presentDays,
                'absent_days' => $absentDays,
                'late_days' => $lateDays,
                'total_hours' => round($totalHours, 1)
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getTodayAttendance($conn) {
    try {
        $employee_id = $_POST['employee_id'] ?? 0;
        
        if (!$employee_id) {
            throw new Exception('Employee ID is required');
        }
        
        $query = $conn->prepare("SELECT *, 
                                TIME_FORMAT(check_in_time, '%H:%i') as check_in_time,
                                TIME_FORMAT(check_out_time, '%H:%i') as check_out_time
                                FROM attendance 
                                WHERE employee_id = ? AND attendance_date = CURDATE()");
        $query->bind_param("i", $employee_id);
        $query->execute();
        $result = $query->get_result();
        
        if ($result->num_rows > 0) {
            $attendance = $result->fetch_assoc();
            echo json_encode(['success' => true, 'attendance' => $attendance]);
        } else {
            echo json_encode(['success' => true, 'attendance' => null]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function checkIn($conn) {
    try {
        $employee_id = $_SESSION['employee_id'] ?? $_POST['employee_id'] ?? 0;
        
        if (!$employee_id) {
            throw new Exception('Employee ID is required');
        }
        
        // Check if already checked in today
        $checkQuery = $conn->prepare("SELECT id FROM attendance WHERE employee_id = ? AND attendance_date = CURDATE()");
        $checkQuery->bind_param("i", $employee_id);
        $checkQuery->execute();
        
        if ($checkQuery->get_result()->num_rows > 0) {
            throw new Exception('Already checked in today');
        }
        
        $query = $conn->prepare("INSERT INTO attendance (employee_id, attendance_date, punch_in_time, time_in, status, location) 
                                VALUES (?, CURDATE(), NOW(), NOW(), 'present', 'Office')");
        $query->bind_param("i", $employee_id);
        
        if ($query->execute()) {
            echo json_encode(['success' => true, 'message' => 'Checked in successfully']);
        } else {
            throw new Exception('Failed to check in');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function checkOut($conn) {
    try {
        $employee_id = $_SESSION['employee_id'] ?? $_POST['employee_id'] ?? 0;
        
        if (!$employee_id) {
            throw new Exception('Employee ID is required');
        }
        
        // Check if checked in today
        $checkQuery = $conn->prepare("SELECT id, punch_out_time FROM attendance WHERE employee_id = ? AND attendance_date = CURDATE()");
        $checkQuery->bind_param("i", $employee_id);
        $checkQuery->execute();
        $result = $checkQuery->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Not checked in today');
        }
        
        $attendance = $result->fetch_assoc();
        if ($attendance['punch_out_time']) {
            throw new Exception('Already checked out today');
        }
        
        $query = $conn->prepare("UPDATE attendance SET punch_out_time = NOW(), time_out = NOW() WHERE employee_id = ? AND attendance_date = CURDATE()");
        $query->bind_param("i", $employee_id);
        
        if ($query->execute()) {
            echo json_encode(['success' => true, 'message' => 'Checked out successfully']);
        } else {
            throw new Exception('Failed to check out');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getPayslips($conn) {
    try {
        $employee_id = $_POST['employee_id'] ?? 0;
        $year = $_POST['year'] ?? date('Y');
        
        if (!$employee_id) {
            throw new Exception('Employee ID is required');
        }
        
        $query = $conn->prepare("SELECT *, 
                                DATE_FORMAT(pay_period, '%M %Y') as pay_period_name,
                                DATE_FORMAT(pay_period, '%Y-%m') as pay_period
                                FROM payroll 
                                WHERE employee_id = ? AND YEAR(pay_period) = ?
                                ORDER BY pay_period DESC");
        $query->bind_param("ii", $employee_id, $year);
        $query->execute();
        $result = $query->get_result();
        
        $payslips = [];
        while ($row = $result->fetch_assoc()) {
            $payslips[] = $row;
        }
        
        echo json_encode(['success' => true, 'payslips' => $payslips]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function downloadPayslip($conn) {
    try {
        $payslip_id = $_GET['payslip_id'] ?? 0;
        
        if (!$payslip_id) {
            throw new Exception('Payslip ID is required');
        }
        
        // Get payslip details
        $query = $conn->prepare("SELECT p.*, e.name, e.employee_code 
                                FROM payroll p 
                                JOIN employees e ON p.employee_id = e.employee_id 
                                WHERE p.id = ?");
        $query->bind_param("i", $payslip_id);
        $query->execute();
        $result = $query->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Payslip not found');
        }
        
        $payslip = $result->fetch_assoc();
        
        // Generate PDF or return payslip data
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'payslip' => $payslip]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateProfile($conn) {
    try {
        $employee_id = $_POST['employee_id'] ?? 0;
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $address = $_POST['address'] ?? '';
        
        if (!$employee_id) {
            throw new Exception('Employee ID is required');
        }
        
        $query = $conn->prepare("UPDATE employees SET email = ?, phone = ?, address = ? WHERE employee_id = ?");
        $query->bind_param("sssi", $email, $phone, $address, $employee_id);
        
        if ($query->execute()) {
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        } else {
            throw new Exception('Failed to update profile');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
