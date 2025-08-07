<?php
$page_title = "Employee Self-Service Portal";

// Include authentication and database
require_once '../auth_check.php';
require_once '../db.php';

// Include layouts
require_once 'hrms_header_simple.php';
require_once 'hrms_sidebar_simple.php';

// Include HRMS UI fix
$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'] ?? 'employee';

$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'] ?? 'employee';

// Get current user information
$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'] ?? 'employee';

// Get employee profile information
$employee = [];
$employeeId = null;
$department = [];

try {
    // Find employee by user_id
    $result = $conn->query("SELECT * FROM hr_employees WHERE user_id = '$currentUserId' AND is_active = 1 LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $employee = $result->fetch_assoc();
        $employeeId = $employee['id'];
    } else {
        // Fallback: use first active employee for demo
        $result = $conn->query("SELECT * FROM hr_employees WHERE is_active = 1 ORDER BY id LIMIT 1");
        if ($result && $result->num_rows > 0) {
            $employee = $result->fetch_assoc();
            $employeeId = $employee['id'];
        }
    }

    // Get department information
    if (!empty($employee['department_id'])) {
        $deptResult = $conn->query("SELECT * FROM hr_departments WHERE id = {$employee['department_id']} LIMIT 1");
        if ($deptResult && $deptResult->num_rows > 0) {
            $department = $deptResult->fetch_assoc();
        }
    }
} catch (Exception $e) {
    error_log("Employee profile error: " . $e->getMessage());
}

// Initialize employee statistics
$employeeStats = [
    'days_present_month' => 0,
    'days_absent_month' => 0,
    'total_leave_taken' => 0,
    'remaining_leave' => 0,
    'attendance_percentage' => 0,
    'late_days_month' => 0,
    'overtime_hours' => 0,
    'pending_leaves' => 0
];

// Calculate employee statistics
if ($employeeId) {
    try {
        $currentMonth = date('Y-m');
        $currentYear = date('Y');
        
        // Present days this month
        $result = $conn->query("
            SELECT COUNT(*) as present_days 
            FROM hr_attendance 
            WHERE employee_id = $employeeId 
            AND DATE_FORMAT(attendance_date, '%Y-%m') = '$currentMonth'
            AND status = 'present'
        ");
        if ($result && $row = $result->fetch_assoc()) {
            $employeeStats['days_present_month'] = (int)$row['present_days'];
        }

        // Absent days this month
        $result = $conn->query("
            SELECT COUNT(*) as absent_days 
            FROM hr_attendance 
            WHERE employee_id = $employeeId 
            AND DATE_FORMAT(attendance_date, '%Y-%m') = '$currentMonth'
            AND status = 'absent'
        ");
        if ($result && $row = $result->fetch_assoc()) {
            $employeeStats['days_absent_month'] = (int)$row['absent_days'];
        }

        // Late days this month
        $result = $conn->query("
            SELECT COUNT(*) as late_days 
            FROM hr_attendance 
            WHERE employee_id = $employeeId 
            AND DATE_FORMAT(attendance_date, '%Y-%m') = '$currentMonth'
            AND status = 'late'
        ");
        if ($result && $row = $result->fetch_assoc()) {
            $employeeStats['late_days_month'] = (int)$row['late_days'];
        }

        // Total leave taken this year
        $result = $conn->query("
            SELECT SUM(DATEDIFF(end_date, start_date) + 1) as leave_days 
            FROM hr_leave_applications 
            WHERE employee_id = $employeeId 
            AND status = 'approved'
            AND YEAR(start_date) = $currentYear
        ");
        if ($result && $row = $result->fetch_assoc()) {
            $employeeStats['total_leave_taken'] = (int)$row['leave_days'];
        }

        // Pending leave applications
        $result = $conn->query("
            SELECT COUNT(*) as pending 
            FROM hr_leave_applications 
            WHERE employee_id = $employeeId 
            AND status = 'pending'
        ");
        if ($result && $row = $result->fetch_assoc()) {
            $employeeStats['pending_leaves'] = (int)$row['pending'];
        }

        // Calculate remaining leave (assuming 30 days annual leave)
        $employeeStats['remaining_leave'] = max(0, 30 - $employeeStats['total_leave_taken']);

        // Calculate attendance percentage
        $totalWorkingDays = $employeeStats['days_present_month'] + $employeeStats['days_absent_month'] + $employeeStats['late_days_month'];
        if ($totalWorkingDays > 0) {
            $employeeStats['attendance_percentage'] = round((($employeeStats['days_present_month'] + $employeeStats['late_days_month']) / $totalWorkingDays) * 100, 1);
        }

    } catch (Exception $e) {
        error_log("Employee statistics error: " . $e->getMessage());
    }
}

// Recent attendance records
$recentAttendance = [];
if ($employeeId) {
    try {
        $result = $conn->query("
            SELECT * FROM hr_attendance 
            WHERE employee_id = $employeeId 
            ORDER BY attendance_date DESC 
            LIMIT 10
        ");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $recentAttendance[] = $row;
            }
        }
    } catch (Exception $e) {
        error_log("Recent attendance error: " . $e->getMessage());
    }
}

// Leave application history
$leaveHistory = [];
if ($employeeId) {
    try {
        $result = $conn->query("
            SELECT * FROM hr_leave_applications 
            WHERE employee_id = $employeeId 
            ORDER BY created_at DESC 
            LIMIT 8
        ");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $leaveHistory[] = $row;
            }
        }
    } catch (Exception $e) {
        error_log("Leave history error: " . $e->getMessage());
    }
}

// Check if employee has clocked in today
$hasClockedInToday = false;
$hasClockedOutToday = false;
$todayAttendance = null;

if ($employeeId) {
    try {
        $today = date('Y-m-d');
        $result = $conn->query("
            SELECT * FROM hr_attendance 
            WHERE employee_id = $employeeId 
            AND attendance_date = '$today' 
            LIMIT 1
        ");
        if ($result && $result->num_rows > 0) {
            $todayAttendance = $result->fetch_assoc();
            $hasClockedInToday = !empty($todayAttendance['check_in_time']);
            $hasClockedOutToday = !empty($todayAttendance['check_out_time']);
        }
    } catch (Exception $e) {
        error_log("Today attendance check error: " . $e->getMessage());
    }
}
?>

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_record':
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                // Determine main table based on file name
                $table = 'hr_employees';
                if (strpos(__FILE__, 'leave') !== false) $table = 'hr_leave_applications';
                if (strpos(__FILE__, 'attendance') !== false) $table = 'hr_attendance';
                if (strpos(__FILE__, 'payroll') !== false) $table = 'hr_payroll';
                if (strpos(__FILE__, 'performance') !== false) $table = 'hr_performance_reviews';
                
                $result = $conn->query("SELECT * FROM $table WHERE id = $id");
                if ($result && $result->num_rows > 0) {
                    echo json_encode(['success' => true, 'data' => $result->fetch_assoc()]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Record not found']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            }
            exit;
            
        case 'delete_record':
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                // Determine main table based on file name
                $table = 'hr_employees';
                if (strpos(__FILE__, 'leave') !== false) $table = 'hr_leave_applications';
                if (strpos(__FILE__, 'attendance') !== false) $table = 'hr_attendance';
                if (strpos(__FILE__, 'payroll') !== false) $table = 'hr_payroll';
                if (strpos(__FILE__, 'performance') !== false) $table = 'hr_performance_reviews';
                
                $result = $conn->query("DELETE FROM $table WHERE id = $id");
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Record deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error deleting record: ' . $conn->error]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            }
            exit;
            
        case 'update_status':
            $id = intval($_POST['id'] ?? 0);
            $status = $conn->real_escape_string($_POST['status'] ?? '');
            if ($id > 0 && $status) {
                // Determine main table based on file name
                $table = 'hr_employees';
                if (strpos(__FILE__, 'leave') !== false) $table = 'hr_leave_applications';
                if (strpos(__FILE__, 'attendance') !== false) $table = 'hr_attendance';
                if (strpos(__FILE__, 'payroll') !== false) $table = 'hr_payroll';
                if (strpos(__FILE__, 'performance') !== false) $table = 'hr_performance_reviews';
                
                $result = $conn->query("UPDATE $table SET status = '$status' WHERE id = $id");
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error updating status: ' . $conn->error]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid data']);
            }
            exit;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
            exit;
    }
}


<!-- Page Content Starts Here -->
<div class="container-fluid">
        <!-- Header Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h2 mb-1">
                            <i class="fas fa-user-circle text-primary me-2"></i>
                            Employee Self-Service Portal
                        </h1>
                        <p class="text-muted mb-0">
                            Welcome back, <?= !empty($employee) ? htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) : 'Employee' ?>
                        </p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-success fs-6 px-3 py-2">
                            <i class="fas fa-circle me-1"></i>Active Employee
                        </span>
                        <span class="text-muted small"><?= date('l, F j, Y') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Employee Profile Card -->
        <?php if (!empty($employee)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-2 text-center">
                                <div class="position-relative d-inline-block">
                                    <div class="bg-primary bg-gradient rounded-circle p-4 d-inline-block shadow">
                                        <i class="fas fa-user text-white" style="font-size: 3rem;"></i>
                                    </div>
                                    <span class="position-absolute bottom-0 end-0 bg-success border border-white rounded-circle" style="width: 20px; height: 20px;"></span>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <h3 class="mb-2"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></h3>
                                <div class="row">
                                    <div class="col-sm-6">
                                        <p class="mb-1"><strong>Employee ID:</strong> <?= htmlspecialchars($employee['employee_id']) ?></p>
                                        <p class="mb-1"><strong>Department:</strong> <?= htmlspecialchars($department['name'] ?? 'N/A') ?></p>
                                        <p class="mb-1"><strong>Position:</strong> <?= htmlspecialchars($employee['position'] ?? 'N/A') ?></p>
                                    </div>
                                    <div class="col-sm-6">
                                        <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($employee['email']) ?></p>
                                        <p class="mb-1"><strong>Phone:</strong> <?= htmlspecialchars($employee['phone'] ?? 'N/A') ?></p>
                                        <p class="mb-1"><strong>Join Date:</strong> <?= date('M j, Y', strtotime($employee['hire_date'] ?? $employee['created_at'])) ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="border-end">
                                            <h4 class="text-success mb-0"><?= $employeeStats['days_present_month'] ?></h4>
                                            <small class="text-muted">Present Days</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="border-end">
                                            <h4 class="text-primary mb-0"><?= $employeeStats['attendance_percentage'] ?>%</h4>
                                            <small class="text-muted">Attendance</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <h4 class="text-warning mb-0"><?= $employeeStats['remaining_leave'] ?></h4>
                                        <small class="text-muted">Leave Balance</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-success bg-opacity-10 rounded-3 p-3 me-3">
                                <i class="fas fa-calendar-check text-success fs-3"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h3 class="fw-bold text-success mb-0"><?= number_format($employeeStats['days_present_month']) ?></h3>
                                <p class="text-muted mb-1 small">Days Present (This Month)</p>
                                <small class="text-info">
                                    <i class="fas fa-clock"></i> <?= $employeeStats['late_days_month'] ?> Late Days
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-danger bg-opacity-10 rounded-3 p-3 me-3">
                                <i class="fas fa-calendar-times text-danger fs-3"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h3 class="fw-bold text-danger mb-0"><?= number_format($employeeStats['days_absent_month']) ?></h3>
                                <p class="text-muted mb-1 small">Days Absent (This Month)</p>
                                <small class="text-warning">
                                    <i class="fas fa-percentage"></i> <?= $employeeStats['attendance_percentage'] ?>% Rate
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-warning bg-opacity-10 rounded-3 p-3 me-3">
                                <i class="fas fa-umbrella-beach text-warning fs-3"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h3 class="fw-bold text-warning mb-0"><?= number_format($employeeStats['total_leave_taken']) ?></h3>
                                <p class="text-muted mb-1 small">Leave Taken (This Year)</p>
                                <small class="text-success">
                                    <i class="fas fa-leaf"></i> <?= $employeeStats['remaining_leave'] ?> Remaining
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-info bg-opacity-10 rounded-3 p-3 me-3">
                                <i class="fas fa-hourglass-half text-info fs-3"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h3 class="fw-bold text-info mb-0"><?= number_format($employeeStats['pending_leaves']) ?></h3>
                                <p class="text-muted mb-1 small">Pending Applications</p>
                                <small class="text-secondary">
                                    <i class="fas fa-clock"></i> Awaiting Approval
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions Panel -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom-0 py-3">
                        <h5 class="card-title mb-0 d-flex align-items-center">
                            <i class="fas fa-bolt text-primary me-2"></i>
                            Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                <button class="btn btn-outline-success w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3 position-relative" onclick="showAttendanceModal()" <?= $hasClockedOutToday ? 'disabled' : '' ?>>
                                    <i class="fas fa-clock fs-2 mb-2"></i>
                                    <span class="fw-medium">
                                        <?= $hasClockedInToday ? ($hasClockedOutToday ? 'Completed' : 'Clock Out') : 'Clock In' ?>
                                    </span>
                                    <small class="text-muted">Mark attendance</small>
                                    <?php if ($hasClockedInToday && !$hasClockedOutToday): ?>
                                        <span class="position-absolute top-0 end-0 badge bg-success">Active</span>
                                    <?php endif; ?>
                                </button>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                <button class="btn btn-outline-warning w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3" onclick="showLeaveModal()">
                                    <i class="fas fa-calendar-plus fs-2 mb-2"></i>
                                    <span class="fw-medium">Apply Leave</span>
                                    <small class="text-muted">Request time off</small>
                                </button>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                <a href="my_attendance.php" class="btn btn-outline-info w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3 text-decoration-none">
                                    <i class="fas fa-chart-line fs-2 mb-2"></i>
                                    <span class="fw-medium">My Attendance</span>
                                    <small class="text-muted">View records</small>
                                </a>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                <a href="my_leaves.php" class="btn btn-outline-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3 text-decoration-none">
                                    <i class="fas fa-list-alt fs-2 mb-2"></i>
                                    <span class="fw-medium">Leave History</span>
                                    <small class="text-muted">Track applications</small>
                                </a>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                <a href="payslips.php" class="btn btn-outline-secondary w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3 text-decoration-none">
                                    <i class="fas fa-file-invoice fs-2 mb-2"></i>
                                    <span class="fw-medium">Payslips</span>
                                    <small class="text-muted">Salary details</small>
                                </a>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                <a href="profile_settings.php" class="btn btn-outline-dark w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3 text-decoration-none">
                                    <i class="fas fa-user-edit fs-2 mb-2"></i>
                                    <span class="fw-medium">Profile</span>
                                    <small class="text-muted">Update info</small>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="row">
            <!-- Recent Attendance -->
            <div class="col-lg-8 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom-0 py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-clock text-primary me-2"></i>
                                Recent Attendance Record
                            </h5>
                            <a href="my_attendance.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recentAttendance)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Check In</th>
                                            <th>Check Out</th>
                                            <th>Working Hours</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentAttendance as $attendance): ?>
                                            <?php
                                            $checkIn = $attendance['check_in_time'] ? date('g:i A', strtotime($attendance['check_in_time'])) : '-';
                                            $checkOut = $attendance['check_out_time'] ? date('g:i A', strtotime($attendance['check_out_time'])) : '-';
                                            $workingHours = '-';
                                            
                                            if ($attendance['check_in_time'] && $attendance['check_out_time']) {
                                                $diff = strtotime($attendance['check_out_time']) - strtotime($attendance['check_in_time']);
                                                $hours = floor($diff / 3600);
                                                $minutes = floor(($diff % 3600) / 60);
                                                $workingHours = sprintf("%d:%02d", $hours, $minutes);
                                            }
                                            
                                            $statusClass = match($attendance['status']) {
                                                'present' => 'success',
                                                'late' => 'warning',
                                                'absent' => 'danger',
                                                default => 'secondary'
                                            };
                                            ?>
                                            <tr>
                                                <td><?= date('M j, Y', strtotime($attendance['attendance_date'])) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $statusClass ?>">
                                                        <?= ucfirst($attendance['status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= $checkIn ?></td>
                                                <td><?= $checkOut ?></td>
                                                <td><?= $workingHours ?></td>
                                                <td>
                                                    <?php if ($attendance['attendance_date'] === date('Y-m-d') && !$attendance['check_out_time']): ?>
                                                        <button class="btn btn-sm btn-warning" onclick="clockOut()">
                                                            <i class="fas fa-sign-out-alt"></i> Clock Out
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-muted small">Completed</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-times text-muted fs-1 mb-3 opacity-50"></i>
                                <h6 class="text-muted">No attendance records found</h6>
                                <p class="text-muted small">Start by marking your attendance</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Leave Applications -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom-0 py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-calendar-alt text-warning me-2"></i>
                                My Leave Applications
                            </h6>
                            <a href="my_leaves.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($leaveHistory)): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach (array_slice($leaveHistory, 0, 6) as $leave): ?>
                                    <?php
                                    $statusClass = match($leave['status']) {
                                        'approved' => 'success',
                                        'rejected' => 'danger',
                                        'pending' => 'warning',
                                        default => 'secondary'
                                    };
                                    ?>
                                    <div class="list-group-item border-0 py-3">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?= htmlspecialchars($leave['leave_type']) ?></h6>
                                                <p class="mb-1 small">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    <?= date('M j', strtotime($leave['start_date'])) ?> - <?= date('M j, Y', strtotime($leave['end_date'])) ?>
                                                </p>
                                                <small class="text-muted">
                                                    Applied: <?= date('M j, Y', strtotime($leave['created_at'])) ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-<?= $statusClass ?> mb-2">
                                                    <?= ucfirst($leave['status']) ?>
                                                </span>
                                                <?php if ($leave['status'] === 'pending'): ?>
                                                    <div>
                                                        <button class="btn btn-outline-danger btn-sm" onclick="cancelLeave(<?= $leave['id'] ?>)" title="Cancel">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-check text-muted fs-1 mb-3 opacity-50"></i>
                                <p class="text-muted small">No leave applications yet</p>
                                <button class="btn btn-outline-primary btn-sm" onclick="showLeaveModal()">
                                    Apply for Leave
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Modal -->
<div class="modal fade" id="attendanceModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title">Mark Attendance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div class="mb-4">
                    <div class="bg-primary bg-gradient rounded-circle p-4 d-inline-block mb-3">
                        <i class="fas fa-clock text-white fs-1"></i>
                    </div>
                    <h4 class="mb-1"><?= date('l, F j, Y') ?></h4>
                    <h2 class="text-primary mb-0" id="currentTime"><?= date('g:i:s A') ?></h2>
                </div>
                
                <?php if (!$hasClockedInToday): ?>
                    <div class="d-grid">
                        <button class="btn btn-success btn-lg py-3" onclick="clockIn()">
                            <i class="fas fa-sign-in-alt me-2"></i>Clock In
                        </button>
                    </div>
                <?php elseif (!$hasClockedOutToday): ?>
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-info-circle me-2"></i>
                        You clocked in at <?= date('g:i A', strtotime($todayAttendance['check_in_time'])) ?>
                    </div>
                    <div class="d-grid">
                        <button class="btn btn-warning btn-lg py-3" onclick="clockOut()">
                            <i class="fas fa-sign-out-alt me-2"></i>Clock Out
                        </button>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        Attendance completed for today!<br>
                        <small>In: <?= date('g:i A', strtotime($todayAttendance['check_in_time'])) ?> | 
                        Out: <?= date('g:i A', strtotime($todayAttendance['check_out_time'])) ?></small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Leave Application Modal -->
<div class="modal fade" id="leaveModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Apply for Leave</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="leaveApplicationForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Leave Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="leaveType" required>
                                <option value="">Select leave type</option>
                                <option value="sick">Sick Leave</option>
                                <option value="casual">Casual Leave</option>
                                <option value="annual">Annual Leave</option>
                                <option value="maternity">Maternity Leave</option>
                                <option value="emergency">Emergency Leave</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Duration</label>
                            <select class="form-select" id="leaveDuration">
                                <option value="full_day">Full Day</option>
                                <option value="half_day">Half Day</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="startDate" required min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="endDate" required min="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="leaveReason" rows="3" placeholder="Please provide detailed reason for leave" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Emergency Contact (Optional)</label>
                        <input type="text" class="form-control" id="emergencyContact" placeholder="Phone number for emergency contact">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitLeaveApplication()">
                    <i class="fas fa-paper-plane me-1"></i>Submit Application
                </button>
            </div>
        </div>
    </div>
</div>

<style>


@media (max-width: 768px) {
    
}

.card {
    transition: all 0.3s ease;
    border-radius: 12px;
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.95);
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.btn:hover {
    transform: translateY(-1px);
}

.table-hover tbody tr:hover {
    background-color: rgba(0,123,255,0.05);
}

.list-group-item:hover {
    background-color: rgba(0,123,255,0.02);
}
</style>

<script>
function showAttendanceModal() {
    const modal = new bootstrap.Modal(document.getElementById('attendanceModal'));
    modal.show();
    updateClock();
}

function showLeaveModal() {
    const modal = new bootstrap.Modal(document.getElementById('leaveModal'));
    modal.show();
}

function updateClock() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    });
    document.getElementById('currentTime').textContent = timeString;
}

function clockIn() {
    // Here you would make an AJAX call to record clock in
    showToast('Attendance marked successfully! Clock In: ' + new Date().toLocaleTimeString(), 'success');
    bootstrap.Modal.getInstance(document.getElementById('attendanceModal')).hide();
    setTimeout(() => location.reload(), 1000);
}

function clockOut() {
    // Here you would make an AJAX call to record clock out
    showToast('Clock out recorded successfully! Clock Out: ' + new Date().toLocaleTimeString(), 'info');
    bootstrap.Modal.getInstance(document.getElementById('attendanceModal')).hide();
    setTimeout(() => location.reload(), 1000);
}

function submitLeaveApplication() {
    const form = document.getElementById('leaveApplicationForm');
    const formData = new FormData(form);
    
    // Basic validation
    const leaveType = document.getElementById('leaveType').value;
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    const reason = document.getElementById('leaveReason').value;
    
    if (!leaveType || !startDate || !endDate || !reason.trim()) {
        showToast('Please fill in all required fields', 'warning');
        return;
    }
    
    if (new Date(startDate) > new Date(endDate)) {
        showToast('End date cannot be before start date', 'warning');
        return;
    }
    
    // Here you would make an AJAX call to submit the leave application
    showToast('Leave application submitted successfully! You will be notified once it is reviewed.', 'success');
    bootstrap.Modal.getInstance(document.getElementById('leaveModal')).hide();
    setTimeout(() => location.reload(), 1000);
}

function cancelLeave(leaveId) {
    if (confirm('Are you sure you want to cancel this leave application?')) {
        // Here you would make an AJAX call to cancel the leave
        showToast('Leave application cancelled successfully', 'info');
        setTimeout(() => location.reload(), 1000);
    }
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} position-fixed top-0 end-0 m-3`;
    toast.style.zIndex = '9999';
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check' : type === 'warning' ? 'exclamation' : 'info'}-circle me-2"></i>
        ${message}
        <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        if (toast.parentElement) {
            toast.remove();
        }
    }, 5000);
}

// Update clock every second when modal is open
setInterval(() => {
    if (document.getElementById('attendanceModal').classList.contains('show')) {
        updateClock();
    }
}, 1000);

// Set minimum date for leave application
document.addEventListener('DOMContentLoaded', function() {
    const startDateInput = document.getElementById('startDate');
    const endDateInput = document.getElementById('endDate');
    
    startDateInput.addEventListener('change', function() {
        endDateInput.min = this.value;
        if (endDateInput.value && endDateInput.value < this.value) {
            endDateInput.value = this.value;
        }
    });
});
</script>

<?php require_once 'hrms_footer_simple.php'; 
<script>
// Standard modal functions for HRMS
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        new bootstrap.Modal(modal).show();
    }
}

function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        const modalInstance = bootstrap.Modal.getInstance(modal);
        if (modalInstance) modalInstance.hide();
    }
}

function loadRecord(id, modalId) {
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=get_record&id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Populate modal form fields
            Object.keys(data.data).forEach(key => {
                const field = document.getElementById(key) || document.querySelector('[name="' + key + '"]');
                if (field) {
                    field.value = data.data[key];
                }
            });
            showModal(modalId);
        } else {
            alert('Error loading record: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error occurred');
    });
}

function deleteRecord(id, confirmMessage = 'Are you sure you want to delete this record?') {
    if (!confirm(confirmMessage)) return;
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=delete_record&id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Record deleted successfully');
            location.reload();
        } else {
            alert('Error deleting record: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error occurred');
    });
}

function updateStatus(id, status) {
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=update_status&id=' + id + '&status=' + status
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Status updated successfully');
            location.reload();
        } else {
            alert('Error updating status: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error occurred');
    });
}

// Form submission with AJAX
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners to forms with class 'ajax-form'
    document.querySelectorAll('.ajax-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Operation completed successfully');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error occurred');
            });
        });
    });
});
</script>

require_once 'hrms_footer_simple.php';
?>