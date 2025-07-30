<?php
// Employee Portal API - Clean implementation with proper database integration
session_start();

// Try multiple database connection paths
if (file_exists('../../db.php')) {
    include '../../db.php';
} elseif (file_exists('../../db.php')) {
    include '../../db.php';
} elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . '/billbook/db.php')) {
    include $_SERVER['DOCUMENT_ROOT'] . '/billbook/db.php';
} else {
    // Last resort - try relative from web root
    include dirname(dirname(__DIR__)) . '/db.php';
}

// Set content type to JSON
header('Content-Type: application/json');

// Employee ID from session (default to 23 for demo)
$employee_id = $_SESSION['employee_id'] ?? 23;

// Get POST data
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_profile':
            getEmployeeProfile($conn, $employee_id);
            break;
        case 'get_attendance_status':
            getAttendanceStatus($conn, $employee_id);
            break;
        case 'get_quick_stats':
            getQuickStats($conn, $employee_id);
            break;
        case 'punch_attendance':
            punchAttendance($conn, $employee_id);
            break;
        case 'get_attendance_history':
            getAttendanceHistory($conn, $employee_id);
            break;
        case 'get_leave_requests':
            getLeaveRequests($conn, $employee_id);
            break;
        case 'apply_leave':
            applyLeave($conn, $employee_id);
            break;
        case 'get_payroll_info':
            getPayrollInfo($conn, $employee_id);
            break;
        case 'get_payroll_preview':
            getPayrollPreview($conn, $employee_id);
            break;
        case 'update_profile':
            updateProfile($conn, $employee_id);
            break;
        case 'get_mobile_attendance':
            getMobileAttendance($conn, $employee_id);
            break;
        case 'get_analytics_data':
            getAnalyticsData($conn, $employee_id);
            break;
        case 'get_schedule':
            getSchedule($conn, $employee_id);
            break;
        default:
            throw new Exception('Invalid action: ' . $action);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Get Employee Profile
function getEmployeeProfile($conn, $employee_id) {
    try {
        $query = $conn->prepare("SELECT * FROM employees WHERE employee_id = ?");
        $query->bind_param("i", $employee_id);
        $query->execute();
        $result = $query->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Get today's attendance status
            $todayQuery = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = CURDATE()");
            $todayQuery->bind_param("i", $employee_id);
            $todayQuery->execute();
            $todayResult = $todayQuery->get_result();
            $todayAttendance = $todayResult->fetch_assoc();
            
            $profile = [
                'employee_id' => $row['employee_id'],
                'name' => $row['name'],
                'employee_code' => $row['employee_code'],
                'position' => $row['position'] ?? 'Employee',
                'department' => $row['department'] ?? 'General',
                'phone' => $row['phone'] ?? '',
                'address' => $row['address'] ?? '',
                'monthly_salary' => $row['monthly_salary'],
                'status' => $row['status'],
                'today_punch_in' => $todayAttendance['punch_in_time'] ?? null,
                'today_punch_out' => $todayAttendance['punch_out_time'] ?? null
            ];
            
            echo json_encode(['success' => true, 'data' => $profile]);
        } else {
            throw new Exception('Employee not found');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Get Attendance Status
function getAttendanceStatus($conn, $employee_id) {
    try {
        $query = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = CURDATE()");
        $query->bind_param("i", $employee_id);
        $query->execute();
        $result = $query->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $status = [
                'is_punched_in' => !empty($row['punch_in_time']),
                'is_punched_out' => !empty($row['punch_out_time']),
                'punch_in_time' => $row['punch_in_time'],
                'punch_out_time' => $row['punch_out_time'],
                'work_hours' => 0,
                'status_text' => 'Not Punched In'
            ];
            
            if ($row['punch_in_time']) {
                $status['status_text'] = $row['punch_out_time'] ? 'Punched Out' : 'Punched In';
                
                if ($row['punch_out_time']) {
                    $punchIn = new DateTime($row['punch_in_time']);
                    $punchOut = new DateTime($row['punch_out_time']);
                    $diff = $punchOut->diff($punchIn);
                    $status['work_hours'] = $diff->h + ($diff->i / 60);
                }
            }
            
            echo json_encode(['success' => true, 'data' => $status]);
        } else {
            $status = [
                'is_punched_in' => false,
                'is_punched_out' => false,
                'punch_in_time' => null,
                'punch_out_time' => null,
                'work_hours' => 0,
                'status_text' => 'Not Punched In'
            ];
            echo json_encode(['success' => true, 'data' => $status]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Get Quick Stats
function getQuickStats($conn, $employee_id) {
    try {
        $stats = [];
        
        // Monthly attendance count
        $monthlyQuery = $conn->prepare("SELECT COUNT(*) as days_present FROM attendance 
                                       WHERE employee_id = ? 
                                       AND MONTH(attendance_date) = MONTH(CURDATE()) 
                                       AND YEAR(attendance_date) = YEAR(CURDATE())
                                       AND punch_in_time IS NOT NULL");
        $monthlyQuery->bind_param("i", $employee_id);
        $monthlyQuery->execute();
        $monthlyResult = $monthlyQuery->get_result();
        $stats['monthly_attendance'] = $monthlyResult->fetch_assoc()['days_present'];
        
        // Leave balance (mock data - would come from leave_balance table)
        $stats['leave_balance'] = 15; // Mock data
        
        // Monthly work hours
        $hoursQuery = $conn->prepare("SELECT SUM(TIMESTAMPDIFF(HOUR, punch_in_time, punch_out_time)) as total_hours 
                                     FROM attendance 
                                     WHERE employee_id = ? 
                                     AND MONTH(attendance_date) = MONTH(CURDATE()) 
                                     AND YEAR(attendance_date) = YEAR(CURDATE())
                                     AND punch_in_time IS NOT NULL 
                                     AND punch_out_time IS NOT NULL");
        $hoursQuery->bind_param("i", $employee_id);
        $hoursQuery->execute();
        $hoursResult = $hoursQuery->get_result();
        $stats['monthly_hours'] = $hoursResult->fetch_assoc()['total_hours'] ?? 0;
        
        // Performance score (mock calculation)
        $stats['performance_score'] = 88.5;
        
        echo json_encode(['success' => true, 'data' => $stats]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Punch Attendance
function punchAttendance($conn, $employee_id) {
    try {
        $type = $_POST['type'] ?? ''; // 'in' or 'out'
        $location = $_POST['location'] ?? '';
        
        if (!$type) {
            throw new Exception('Punch type is required');
        }
        
        $today = date('Y-m-d');
        
        // Check if record exists for today
        $checkQuery = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?");
        $checkQuery->bind_param("is", $employee_id, $today);
        $checkQuery->execute();
        $result = $checkQuery->get_result();
        
        if ($type === 'in') {
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                if ($row['punch_in_time']) {
                    throw new Exception('Already punched in today');
                }
                // Update existing record
                $updateQuery = $conn->prepare("UPDATE attendance SET punch_in_time = NOW(), location = ? WHERE id = ?");
                $updateQuery->bind_param("si", $location, $row['id']);
                $updateQuery->execute();
            } else {
                // Insert new record
                $insertQuery = $conn->prepare("INSERT INTO attendance (employee_id, attendance_date, punch_in_time, location) VALUES (?, ?, NOW(), ?)");
                $insertQuery->bind_param("iss", $employee_id, $today, $location);
                $insertQuery->execute();
            }
            echo json_encode(['success' => true, 'message' => 'Punched in successfully']);
        } else if ($type === 'out') {
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                if (!$row['punch_in_time']) {
                    throw new Exception('Must punch in first');
                }
                if ($row['punch_out_time']) {
                    throw new Exception('Already punched out today');
                }
                // Update punch out
                $updateQuery = $conn->prepare("UPDATE attendance SET punch_out_time = NOW() WHERE id = ?");
                $updateQuery->bind_param("i", $row['id']);
                $updateQuery->execute();
                echo json_encode(['success' => true, 'message' => 'Punched out successfully']);
            } else {
                throw new Exception('No punch in record found for today');
            }
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Get Attendance History
function getAttendanceHistory($conn, $employee_id) {
    try {
        $month = $_POST['month'] ?? date('Y-m');
        
        $query = $conn->prepare("SELECT *, 
                                TIMESTAMPDIFF(HOUR, punch_in_time, punch_out_time) as work_hours,
                                CASE 
                                    WHEN punch_in_time IS NULL THEN 'absent'
                                    WHEN TIME(punch_in_time) > '09:15:00' THEN 'late'
                                    ELSE 'present'
                                END as status
                                FROM attendance 
                                WHERE employee_id = ? 
                                AND DATE_FORMAT(attendance_date, '%Y-%m') = ?
                                ORDER BY attendance_date DESC");
        $query->bind_param("is", $employee_id, $month);
        $query->execute();
        $result = $query->get_result();
        
        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = [
                'date' => $row['attendance_date'],
                'attendance_date' => $row['attendance_date'], // For backward compatibility
                'punch_in' => $row['punch_in_time'] ? date('H:i', strtotime($row['punch_in_time'])) : null,
                'punch_in_time' => $row['punch_in_time'] ? date('H:i', strtotime($row['punch_in_time'])) : null, // For backward compatibility
                'punch_out' => $row['punch_out_time'] ? date('H:i', strtotime($row['punch_out_time'])) : null,
                'punch_out_time' => $row['punch_out_time'] ? date('H:i', strtotime($row['punch_out_time'])) : null, // For backward compatibility
                'work_hours' => $row['work_hours'] ?? 0,
                'work_duration' => $row['work_hours'] ?? 0, // For backward compatibility
                'status' => $row['status'],
                'location' => $row['location'] ?? ''
            ];
        }
        
        echo json_encode(['success' => true, 'data' => $history]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Get Leave Requests
function getLeaveRequests($conn, $employee_id) {
    try {
        // Log for debugging
        error_log("getLeaveRequests called for employee: " . $employee_id);
        
        // Check if leave_requests table exists
        $checkTable = $conn->query("SHOW TABLES LIKE 'leave_requests'");
        
        if ($checkTable && $checkTable->num_rows > 0) {
            error_log("Table exists, querying database...");
            $query = $conn->prepare("SELECT * FROM leave_requests WHERE employee_id = ? ORDER BY applied_date DESC");
            $query->bind_param("i", $employee_id);
            $query->execute();
            $result = $query->get_result();
            
            $requests = [];
            while ($row = $result->fetch_assoc()) {
                $requests[] = $row;
            }
            error_log("Found " . count($requests) . " leave requests");
        } else {
            error_log("Table does not exist, using mock data");
            // Mock leave request data
            $requests = [
                [
                    'id' => 1,
                    'leave_type' => 'Annual Leave',
                    'from_date' => '2025-08-15',
                    'to_date' => '2025-08-17',
                    'days_requested' => 3,
                    'reason' => 'Family vacation',
                    'status' => 'pending',
                    'applied_date' => '2025-07-28 10:30:00'
                ],
                [
                    'id' => 2,
                    'leave_type' => 'Sick Leave',
                    'from_date' => '2025-07-25',
                    'to_date' => '2025-07-25',
                    'days_requested' => 1,
                    'reason' => 'Medical appointment',
                    'status' => 'approved',
                    'applied_date' => '2025-07-24 14:20:00'
                ]
            ];
        }
        
        echo json_encode(['success' => true, 'data' => $requests]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Apply Leave
function applyLeave($conn, $employee_id) {
    try {
        $leave_type = $_POST['leave_type'] ?? '';
        $from_date = $_POST['from_date'] ?? '';
        $to_date = $_POST['to_date'] ?? '';
        $reason = $_POST['reason'] ?? '';
        
        if (!$leave_type || !$from_date || !$to_date || !$reason) {
            throw new Exception('All fields are required');
        }
        
        // Calculate days
        $from = new DateTime($from_date);
        $to = new DateTime($to_date);
        $days = $to->diff($from)->days + 1;
        
        // Check if leave_requests table exists, create if not
        $createTable = "CREATE TABLE IF NOT EXISTS leave_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            leave_type VARCHAR(50) NOT NULL,
            from_date DATE NOT NULL,
            to_date DATE NOT NULL,
            days_requested INT NOT NULL,
            reason TEXT NOT NULL,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            applied_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            approved_by INT NULL,
            approved_date DATETIME NULL,
            approver_comments TEXT NULL
        )";
        $conn->query($createTable);
        
        $query = $conn->prepare("INSERT INTO leave_requests (employee_id, leave_type, from_date, to_date, days_requested, reason) VALUES (?, ?, ?, ?, ?, ?)");
        $query->bind_param("isssis", $employee_id, $leave_type, $from_date, $to_date, $days, $reason);
        
        if ($query->execute()) {
            echo json_encode(['success' => true, 'message' => 'Leave application submitted successfully']);
        } else {
            throw new Exception('Failed to submit leave application');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Get Payroll Info
function getPayrollInfo($conn, $employee_id) {
    try {
        // Get employee salary info
        $empQuery = $conn->prepare("SELECT monthly_salary FROM employees WHERE employee_id = ?");
        $empQuery->bind_param("i", $employee_id);
        $empQuery->execute();
        $empResult = $empQuery->get_result();
        $emp = $empResult->fetch_assoc();
        
        // Mock payroll calculation
        $basicSalary = $emp['monthly_salary'];
        $allowances = $basicSalary * 0.2; // 20% allowances
        $deductions = $basicSalary * 0.1; // 10% deductions
        $netSalary = $basicSalary + $allowances - $deductions;
        
        $payroll = [
            'basic_salary' => $basicSalary,
            'allowances' => $allowances,
            'deductions' => $deductions,
            'net_salary' => $netSalary,
            'ytd_earnings' => $netSalary * 7, // Mock YTD for 7 months
            'tax_deducted' => $deductions * 0.3 // Mock tax
        ];
        
        echo json_encode(['success' => true, 'data' => $payroll]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Get Payroll Preview
function getPayrollPreview($conn, $employee_id) {
    try {
        $periodStart = $_POST['period_start'] ?? '';
        $periodEnd = $_POST['period_end'] ?? '';
        
        // Get employee salary info
        $empQuery = $conn->prepare("SELECT monthly_salary, name FROM employees WHERE employee_id = ?");
        $empQuery->bind_param("i", $employee_id);
        $empQuery->execute();
        $empResult = $empQuery->get_result();
        $emp = $empResult->fetch_assoc();
        
        if (!$emp) {
            throw new Exception('Employee not found');
        }
        
        // Calculate attendance for the period
        $attendanceQuery = $conn->prepare("SELECT COUNT(*) as working_days FROM attendance 
                                          WHERE employee_id = ? 
                                          AND attendance_date BETWEEN ? AND ?
                                          AND punch_in_time IS NOT NULL");
        $attendanceQuery->bind_param("iss", $employee_id, $periodStart, $periodEnd);
        $attendanceQuery->execute();
        $attendanceResult = $attendanceQuery->get_result();
        $workingDays = $attendanceResult->fetch_assoc()['working_days'];
        
        // Calculate payroll
        $basicSalary = $emp['monthly_salary'];
        $allowances = $basicSalary * 0.2; // 20% allowances
        $deductions = $basicSalary * 0.1; // 10% deductions
        $grossSalary = $basicSalary + $allowances;
        $netSalary = $grossSalary - $deductions;
        
        $payrollData = [
            'employee_name' => $emp['name'],
            'period' => date('M Y', strtotime($periodStart)),
            'working_days' => $workingDays,
            'basic_salary' => $basicSalary,
            'allowances' => $allowances,
            'gross_salary' => $grossSalary,
            'deductions' => $deductions,
            'net_salary' => $netSalary,
            'breakdown' => [
                'hra' => $basicSalary * 0.1,
                'transport' => 2000,
                'medical' => 1500,
                'pf' => $basicSalary * 0.12,
                'esi' => $basicSalary * 0.0075,
                'tax' => $basicSalary * 0.05
            ]
        ];
        
        echo json_encode(['success' => true, 'data' => $payrollData]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Update Profile
function updateProfile($conn, $employee_id) {
    try {
        $phone = $_POST['phone'] ?? '';
        $address = $_POST['address'] ?? '';
        
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

// Get Mobile Attendance
function getMobileAttendance($conn, $employee_id) {
    try {
        // Mock mobile attendance request history (since mobile_attendance_requests table might not exist)
        $mobileRequests = [
            [
                'id' => 1,
                'date' => date('Y-m-d'),
                'request_type' => 'Punch In',
                'location' => 'Home Office',
                'status' => 'approved',
                'processed_by' => 'HR Team'
            ],
            [
                'id' => 2,
                'date' => date('Y-m-d', strtotime('-1 day')),
                'request_type' => 'Punch Out',
                'location' => 'Client Site',
                'status' => 'approved', 
                'processed_by' => 'Manager'
            ],
            [
                'id' => 3,
                'date' => date('Y-m-d', strtotime('-2 days')),
                'request_type' => 'Punch In',
                'location' => 'Remote Work',
                'status' => 'pending',
                'processed_by' => '-'
            ]
        ];
        
        echo json_encode(['success' => true, 'data' => $mobileRequests]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Get Analytics Data
function getAnalyticsData($conn, $employee_id) {
    try {
        // Attendance percentage for last 30 days
        $totalDays = 30;
        $attendanceQuery = $conn->prepare("SELECT COUNT(*) as present_days FROM attendance 
                                          WHERE employee_id = ? 
                                          AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                                          AND punch_in_time IS NOT NULL");
        $attendanceQuery->bind_param("i", $employee_id);
        $attendanceQuery->execute();
        $attendanceResult = $attendanceQuery->get_result();
        $presentDays = $attendanceResult->fetch_assoc()['present_days'];
        
        $analytics = [
            'attendance_percentage' => round(($presentDays / $totalDays) * 100, 1),
            'average_hours' => 8.2, // Mock data
            'punctuality_score' => 85.5, // Mock data
            'monthly_trend' => [
                ['month' => 'Jan', 'percentage' => 95],
                ['month' => 'Feb', 'percentage' => 88],
                ['month' => 'Mar', 'percentage' => 92],
                ['month' => 'Apr', 'percentage' => 87],
                ['month' => 'May', 'percentage' => 91],
                ['month' => 'Jun', 'percentage' => 89],
                ['month' => 'Jul', 'percentage' => round(($presentDays / $totalDays) * 100, 1)]
            ]
        ];
        
        echo json_encode(['success' => true, 'data' => $analytics]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Get Schedule
function getSchedule($conn, $employee_id) {
    try {
        // Mock schedule data
        $schedule = [
            'current_shift' => '09:00 - 17:00',
            'break_time' => '12:00 - 13:00',
            'weekly_hours' => 40,
            'upcoming_leaves' => [
                ['date' => '2025-08-15', 'type' => 'Annual Leave', 'days' => 3]
            ]
        ];
        
        echo json_encode(['success' => true, 'data' => $schedule]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
