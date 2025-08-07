<?php
$page_title = "Manager Dashboard - Team Management";

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

// Get manager information
$manager = [];
$managerId = null;
$managerDepartmentId = null;

try {
    // Find manager by user_id
    $result = $conn->query("SELECT * FROM hr_employees WHERE user_id = '$currentUserId' AND is_active = 1 LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $manager = $result->fetch_assoc();
        $managerId = $manager['id'];
        $managerDepartmentId = $manager['department_id'];
    } else {
        // Fallback: use first active employee for demo
        $result = $conn->query("SELECT * FROM hr_employees WHERE is_active = 1 ORDER BY id LIMIT 1");
        if ($result && $result->num_rows > 0) {
            $manager = $result->fetch_assoc();
            $managerId = $manager['id'];
            $managerDepartmentId = $manager['department_id'];
        }
    }
} catch (Exception $e) {
    error_log("Manager lookup error: " . $e->getMessage());
}

// Initialize team statistics
$teamStats = [
    'total_team_members' => 0,
    'present_today' => 0,
    'absent_today' => 0,
    'late_today' => 0,
    'on_leave_today' => 0,
    'pending_approvals' => 0,
    'team_attendance_rate' => 0,
    'department_count' => 0
];

// Fetch team statistics
try {
    // Team members (if manager has department, show department team, otherwise show all)
    $teamCondition = $managerDepartmentId ? "WHERE e.department_id = $managerDepartmentId AND e.is_active = 1" : "WHERE e.is_active = 1";
    $result = $conn->query("SELECT COUNT(*) as total FROM hr_employees e $teamCondition");
    if ($result && $row = $result->fetch_assoc()) {
        $teamStats['total_team_members'] = (int)$row['total'];
    }

    // Today's attendance statistics
    $today = date('Y-m-d');
    
    // Present today
    $result = $conn->query("
        SELECT COUNT(*) as present 
        FROM hr_attendance a
        LEFT JOIN hr_employees e ON a.employee_id = e.id
        WHERE a.attendance_date = '$today' 
        AND a.status = 'present'
        " . ($managerDepartmentId ? "AND e.department_id = $managerDepartmentId" : "") . "
    ");
    if ($result && $row = $result->fetch_assoc()) {
        $teamStats['present_today'] = (int)$row['present'];
    }

    // Late today
    $result = $conn->query("
        SELECT COUNT(*) as late 
        FROM hr_attendance a
        LEFT JOIN hr_employees e ON a.employee_id = e.id
        WHERE a.attendance_date = '$today' 
        AND a.status = 'late'
        " . ($managerDepartmentId ? "AND e.department_id = $managerDepartmentId" : "") . "
    ");
    if ($result && $row = $result->fetch_assoc()) {
        $teamStats['late_today'] = (int)$row['late'];
    }

    // Absent today
    $result = $conn->query("
        SELECT COUNT(*) as absent 
        FROM hr_attendance a
        LEFT JOIN hr_employees e ON a.employee_id = e.id
        WHERE a.attendance_date = '$today' 
        AND a.status = 'absent'
        " . ($managerDepartmentId ? "AND e.department_id = $managerDepartmentId" : "") . "
    ");
    if ($result && $row = $result->fetch_assoc()) {
        $teamStats['absent_today'] = (int)$row['absent'];
    }

    // On leave today
    $result = $conn->query("
        SELECT COUNT(*) as on_leave 
        FROM hr_leave_applications la
        LEFT JOIN hr_employees e ON la.employee_id = e.id
        WHERE la.status = 'approved' 
        AND '$today' BETWEEN la.start_date AND la.end_date
        " . ($managerDepartmentId ? "AND e.department_id = $managerDepartmentId" : "") . "
    ");
    if ($result && $row = $result->fetch_assoc()) {
        $teamStats['on_leave_today'] = (int)$row['on_leave'];
    }

    // Pending leave approvals
    $result = $conn->query("
        SELECT COUNT(*) as pending 
        FROM hr_leave_applications la
        LEFT JOIN hr_employees e ON la.employee_id = e.id
        WHERE la.status = 'pending'
        " . ($managerDepartmentId ? "AND e.department_id = $managerDepartmentId" : "") . "
    ");
    if ($result && $row = $result->fetch_assoc()) {
        $teamStats['pending_approvals'] = (int)$row['pending'];
    }

    // Calculate attendance rate
    if ($teamStats['total_team_members'] > 0) {
        $totalPresent = $teamStats['present_today'] + $teamStats['late_today'];
        $teamStats['team_attendance_rate'] = round(($totalPresent / $teamStats['total_team_members']) * 100, 1);
    }

    // Department count
    $result = $conn->query("SELECT COUNT(*) as total FROM hr_departments WHERE status = 'active'");
    if ($result && $row = $result->fetch_assoc()) {
        $teamStats['department_count'] = (int)$row['total'];
    }

} catch (Exception $e) {
    error_log("Team stats error: " . $e->getMessage());
}

// Team members overview
$teamMembers = [];
try {
    $teamCondition = $managerDepartmentId ? "AND e.department_id = $managerDepartmentId" : "";
    $result = $conn->query("
        SELECT e.*, d.name as department_name,
               (SELECT status FROM hr_attendance WHERE employee_id = e.id AND attendance_date = '$today' LIMIT 1) as today_status,
               (SELECT check_in_time FROM hr_attendance WHERE employee_id = e.id AND attendance_date = '$today' LIMIT 1) as check_in_time
        FROM hr_employees e
        LEFT JOIN hr_departments d ON e.department_id = d.id
        WHERE e.is_active = 1 $teamCondition
        ORDER BY e.first_name, e.last_name
        LIMIT 12
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $teamMembers[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Team members error: " . $e->getMessage());
}

// Pending leave approvals for manager review
$pendingLeaves = [];
try {
    $teamCondition = $managerDepartmentId ? "AND e.department_id = $managerDepartmentId" : "";
    $result = $conn->query("
        SELECT la.*, e.first_name, e.last_name, e.employee_id, e.department_id,
               d.name as department_name
        FROM hr_leave_applications la
        LEFT JOIN hr_employees e ON la.employee_id = e.id
        LEFT JOIN hr_departments d ON e.department_id = d.id
        WHERE la.status = 'pending' $teamCondition
        ORDER BY la.created_at DESC
        LIMIT 8
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $pendingLeaves[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Pending leaves error: " . $e->getMessage());
}

// Team performance metrics
$performanceMetrics = [];
try {
    $teamCondition = $managerDepartmentId ? "AND e.department_id = $managerDepartmentId" : "";
    $currentMonth = date('Y-m');
    $result = $conn->query("
        SELECT e.first_name, e.last_name, e.employee_id,
               COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
               COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_days,
               COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_days,
               ROUND(
                   (COUNT(CASE WHEN a.status IN ('present', 'late') THEN 1 END) / 
                    NULLIF(COUNT(a.id), 0)) * 100, 1
               ) as attendance_rate
        FROM hr_employees e
        LEFT JOIN hr_attendance a ON e.id = a.employee_id 
            AND DATE_FORMAT(a.attendance_date, '%Y-%m') = '$currentMonth'
        WHERE e.is_active = 1 $teamCondition
        GROUP BY e.id, e.first_name, e.last_name, e.employee_id
        ORDER BY attendance_rate DESC
        LIMIT 6
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $performanceMetrics[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Performance metrics error: " . $e->getMessage());
}

// Recent team activities
$recentActivities = [];
try {
    $teamCondition = $managerDepartmentId ? "AND e.department_id = $managerDepartmentId" : "";
    $result = $conn->query("
        (SELECT 'attendance' as activity_type, 
                CONCAT(e.first_name, ' ', e.last_name, ' marked attendance') as description,
                a.attendance_date as activity_date,
                a.status as activity_status
         FROM hr_attendance a
         LEFT JOIN hr_employees e ON a.employee_id = e.id
         WHERE a.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 3 DAY) $teamCondition)
        UNION ALL
        (SELECT 'leave_applied' as activity_type,
                CONCAT(e.first_name, ' ', e.last_name, ' applied for ', la.leave_type, ' leave') as description,
                la.created_at as activity_date,
                la.status as activity_status
         FROM hr_leave_applications la
         LEFT JOIN hr_employees e ON la.employee_id = e.id
         WHERE la.created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY) $teamCondition)
        ORDER BY activity_date DESC
        LIMIT 8
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recentActivities[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Recent activities error: " . $e->getMessage());
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
                            <i class="fas fa-users-cog text-primary me-2"></i>
                            Team Management Dashboard
                        </h1>
                        <p class="text-muted mb-0">
                            Welcome, <?= !empty($manager) ? htmlspecialchars($manager['first_name'] . ' ' . $manager['last_name']) : 'Manager' ?>
                            <?php if ($managerDepartmentId): ?>
                                - Managing your department team
                            <?php else: ?>
                                - Managing all teams
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-success fs-6 px-3 py-2">
                            <i class="fas fa-users me-1"></i>Team Manager
                        </span>
                        <span class="text-muted small"><?= date('l, F j, Y') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Key Metrics Cards -->
        <div class="row mb-4">
            <!-- Team Members -->
            <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-primary bg-opacity-10 rounded-3 p-3 me-3">
                                <i class="fas fa-users text-primary fs-3"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h3 class="fw-bold text-primary mb-0"><?= number_format($teamStats['total_team_members']) ?></h3>
                                <p class="text-muted mb-1 small">Team Members</p>
                                <small class="text-info">
                                    <i class="fas fa-building"></i> <?= $teamStats['department_count'] ?> Departments
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Present Today -->
            <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-success bg-opacity-10 rounded-3 p-3 me-3">
                                <i class="fas fa-user-check text-success fs-3"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h3 class="fw-bold text-success mb-0"><?= number_format($teamStats['present_today']) ?></h3>
                                <p class="text-muted mb-1 small">Present Today</p>
                                <small class="text-warning">
                                    <i class="fas fa-clock"></i> <?= $teamStats['late_today'] ?> Late
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- On Leave -->
            <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-warning bg-opacity-10 rounded-3 p-3 me-3">
                                <i class="fas fa-calendar-times text-warning fs-3"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h3 class="fw-bold text-warning mb-0"><?= number_format($teamStats['on_leave_today']) ?></h3>
                                <p class="text-muted mb-1 small">On Leave Today</p>
                                <small class="text-danger">
                                    <i class="fas fa-user-times"></i> <?= $teamStats['absent_today'] ?> Absent
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Attendance Rate -->
            <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-info bg-opacity-10 rounded-3 p-3 me-3">
                                <i class="fas fa-percentage text-info fs-3"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h3 class="fw-bold text-info mb-0"><?= $teamStats['team_attendance_rate'] ?>%</h3>
                                <p class="text-muted mb-1 small">Attendance Rate</p>
                                <small class="text-danger">
                                    <i class="fas fa-exclamation-circle"></i> <?= $teamStats['pending_approvals'] ?> Pending
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
                            Management Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                <a href="team_attendance.php" class="btn btn-outline-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3 text-decoration-none">
                                    <i class="fas fa-calendar-check fs-2 mb-2"></i>
                                    <span class="fw-medium">Team Attendance</span>
                                    <small class="text-muted">View daily records</small>
                                </a>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                <button class="btn btn-outline-success w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3" onclick="showApprovalModal()">
                                    <i class="fas fa-check-circle fs-2 mb-2"></i>
                                    <span class="fw-medium">Approve Leaves</span>
                                    <small class="text-muted">Review requests</small>
                                    <?php if ($teamStats['pending_approvals'] > 0): ?>
                                        <span class="position-absolute top-0 end-0 badge bg-danger"><?= $teamStats['pending_approvals'] ?></span>
                                    <?php endif; ?>
                                </button>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                <a href="team_performance.php" class="btn btn-outline-info w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3 text-decoration-none">
                                    <i class="fas fa-chart-line fs-2 mb-2"></i>
                                    <span class="fw-medium">Performance</span>
                                    <small class="text-muted">Team analytics</small>
                                </a>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                <a href="team_reports.php" class="btn btn-outline-warning w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3 text-decoration-none">
                                    <i class="fas fa-file-alt fs-2 mb-2"></i>
                                    <span class="fw-medium">Reports</span>
                                    <small class="text-muted">Generate reports</small>
                                </a>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                <a href="team_schedule.php" class="btn btn-outline-secondary w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3 text-decoration-none">
                                    <i class="fas fa-calendar-plus fs-2 mb-2"></i>
                                    <span class="fw-medium">Schedule</span>
                                    <small class="text-muted">Manage shifts</small>
                                </a>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                <a href="team_settings.php" class="btn btn-outline-dark w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3 text-decoration-none">
                                    <i class="fas fa-cog fs-2 mb-2"></i>
                                    <span class="fw-medium">Settings</span>
                                    <small class="text-muted">Team config</small>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="row">
            <!-- Team Overview -->
            <div class="col-lg-8 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom-0 py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-users text-primary me-2"></i>
                                Team Overview
                            </h5>
                            <a href="team_details.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($teamMembers)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Employee</th>
                                            <th>Department</th>
                                            <th>Today's Status</th>
                                            <th>Check In</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($teamMembers as $member): ?>
                                            <?php
                                            $statusClass = match($member['today_status']) {
                                                'present' => 'success',
                                                'late' => 'warning',
                                                'absent' => 'danger',
                                                default => 'secondary'
                                            };
                                            $checkInTime = $member['check_in_time'] ? date('g:i A', strtotime($member['check_in_time'])) : '-';
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-2">
                                                            <i class="fas fa-user text-primary"></i>
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-0"><?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?></h6>
                                                            <small class="text-muted"><?= htmlspecialchars($member['employee_id']) ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark"><?= htmlspecialchars($member['department_name'] ?? 'N/A') ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($member['today_status']): ?>
                                                        <span class="badge bg-<?= $statusClass ?>">
                                                            <?= ucfirst($member['today_status']) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Not marked</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= $checkInTime ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-outline-primary" onclick="viewEmployeeDetails(<?= $member['id'] ?>)" title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-info" onclick="sendMessage(<?= $member['id'] ?>)" title="Send Message">
                                                            <i class="fas fa-comment"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-users text-muted fs-1 mb-3 opacity-50"></i>
                                <h6 class="text-muted">No team members found</h6>
                                <p class="text-muted small">Team members will appear here</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Sidebar -->
            <div class="col-lg-4">
                <!-- Pending Approvals -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom-0 py-3">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-clock text-warning me-2"></i>
                            Pending Approvals
                            <?php if (count($pendingLeaves) > 0): ?>
                                <span class="badge bg-warning ms-2"><?= count($pendingLeaves) ?></span>
                            <?php endif; ?>
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($pendingLeaves)): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach (array_slice($pendingLeaves, 0, 4) as $leave): ?>
                                    <div class="list-group-item border-0 py-3">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?= htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']) ?></h6>
                                                <p class="mb-1 small text-muted">
                                                    <i class="fas fa-briefcase me-1"></i>
                                                    <?= htmlspecialchars($leave['department_name'] ?? 'Unknown') ?>
                                                </p>
                                                <p class="mb-1 small">
                                                    <strong><?= htmlspecialchars($leave['leave_type']) ?></strong>
                                                </p>
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    <?= date('M j', strtotime($leave['start_date'])) ?> - <?= date('M j', strtotime($leave['end_date'])) ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <div class="btn-group-vertical" role="group">
                                                    <button class="btn btn-success btn-sm mb-1" onclick="approveLeave(<?= $leave['id'] ?>)" title="Approve">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button class="btn btn-danger btn-sm" onclick="rejectLeave(<?= $leave['id'] ?>)" title="Reject">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($pendingLeaves) > 4): ?>
                                <div class="card-footer bg-light">
                                    <button class="btn btn-outline-primary btn-sm w-100" onclick="showApprovalModal()">
                                        View All <?= count($pendingLeaves) ?> Applications
                                    </button>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-check-circle text-success fs-1 mb-3 opacity-50"></i>
                                <p class="text-muted small">No pending approvals</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Team Performance -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom-0 py-3">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-chart-bar text-info me-2"></i>
                            Team Performance (This Month)
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($performanceMetrics)): ?>
                            <?php foreach ($performanceMetrics as $metric): ?>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <h6 class="mb-0"><?= htmlspecialchars($metric['first_name'] . ' ' . $metric['last_name']) ?></h6>
                                        <small class="text-muted">
                                            P: <?= $metric['present_days'] ?> | L: <?= $metric['late_days'] ?> | A: <?= $metric['absent_days'] ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-<?= $metric['attendance_rate'] >= 90 ? 'success' : ($metric['attendance_rate'] >= 75 ? 'warning' : 'danger') ?>">
                                            <?= $metric['attendance_rate'] ?? 0 ?>%
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="mt-3">
                                <a href="team_performance.php" class="btn btn-outline-info btn-sm w-100">
                                    <i class="fas fa-chart-line me-1"></i>Detailed Analytics
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="fas fa-chart-bar text-muted fs-2 mb-2 opacity-50"></i>
                                <p class="text-muted small">Performance data will appear here</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom-0 py-3">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-history text-primary me-2"></i>
                            Recent Team Activities
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recentActivities)): ?>
                            <div class="row">
                                <?php foreach (array_slice($recentActivities, 0, 6) as $activity): ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="border rounded p-3 h-100">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-<?= $activity['activity_type'] === 'attendance' ? 'primary' : 'warning' ?> bg-opacity-10 rounded-circle p-2 me-3">
                                                    <i class="fas fa-<?= $activity['activity_type'] === 'attendance' ? 'clock' : 'calendar' ?> text-<?= $activity['activity_type'] === 'attendance' ? 'primary' : 'warning' ?>"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1 small"><?= htmlspecialchars($activity['description']) ?></h6>
                                                    <small class="text-muted">
                                                        <?= date('M j, g:i A', strtotime($activity['activity_date'])) ?>
                                                    </small>
                                                    <?php if ($activity['activity_status']): ?>
                                                        <br><span class="badge bg-light text-dark small"><?= ucfirst($activity['activity_status']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-history text-muted fs-1 mb-3 opacity-50"></i>
                                <p class="text-muted">No recent activities</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Approval Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Leave Application Review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="approvalContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmAction">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- Employee Details Modal -->
<div class="modal fade" id="employeeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Employee Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="employeeContent"></div>
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

.btn-group-vertical .btn {
    border-radius: 4px;
    margin-bottom: 2px;
}

.btn-group-vertical .btn:last-child {
    margin-bottom: 0;
}
</style>

<script>
function showApprovalModal() {
    // Scroll to pending approvals or show all
    const approvalsCard = document.querySelector('h6:contains("Pending Approvals")');
    if (approvalsCard) {
        approvalsCard.closest('.card').scrollIntoView({ behavior: 'smooth' });
    }
}

function approveLeave(leaveId) {
    document.getElementById('approvalContent').innerHTML = `
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>
            Are you sure you want to approve this leave application?
        </div>
        <div class="mb-3">
            <label class="form-label">Manager Comments (Optional)</label>
            <textarea class="form-control" id="managerComments" rows="2" placeholder="Add any comments for the employee..."></textarea>
        </div>
    `;
    
    document.getElementById('confirmAction').onclick = function() {
        // Here you would make an AJAX call to approve the leave
        showToast('Leave application approved successfully!', 'success');
        bootstrap.Modal.getInstance(document.getElementById('approvalModal')).hide();
        setTimeout(() => location.reload(), 1000);
    };
    
    new bootstrap.Modal(document.getElementById('approvalModal')).show();
}

function rejectLeave(leaveId) {
    document.getElementById('approvalContent').innerHTML = `
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Are you sure you want to reject this leave application?
        </div>
        <div class="mb-3">
            <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
            <textarea class="form-control" id="rejectionReason" rows="3" placeholder="Please provide a clear reason for rejection..." required></textarea>
        </div>
    `;
    
    document.getElementById('confirmAction').onclick = function() {
        const reason = document.getElementById('rejectionReason').value.trim();
        if (!reason) {
            alert('Please provide a reason for rejection');
            return;
        }
        
        // Here you would make an AJAX call to reject the leave
        showToast('Leave application rejected', 'warning');
        bootstrap.Modal.getInstance(document.getElementById('approvalModal')).hide();
        setTimeout(() => location.reload(), 1000);
    };
    
    new bootstrap.Modal(document.getElementById('approvalModal')).show();
}

function viewEmployeeDetails(employeeId) {
    document.getElementById('employeeContent').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading employee details...</p>
        </div>
    `;
    
    new bootstrap.Modal(document.getElementById('employeeModal')).show();
    
    // Here you would load employee details via AJAX
    setTimeout(() => {
        document.getElementById('employeeContent').innerHTML = `
            <div class="row">
                <div class="col-md-4 text-center">
                    <div class="bg-primary bg-gradient rounded-circle p-4 d-inline-block mb-3">
                        <i class="fas fa-user text-white fs-1"></i>
                    </div>
                    <h5>Employee Details</h5>
                </div>
                <div class="col-md-8">
                    <div class="row">
                        <div class="col-sm-6 mb-2"><strong>Employee ID:</strong> EMP-001</div>
                        <div class="col-sm-6 mb-2"><strong>Department:</strong> IT</div>
                        <div class="col-sm-6 mb-2"><strong>Position:</strong> Developer</div>
                        <div class="col-sm-6 mb-2"><strong>Join Date:</strong> Jan 15, 2023</div>
                        <div class="col-sm-6 mb-2"><strong>Email:</strong> employee@company.com</div>
                        <div class="col-sm-6 mb-2"><strong>Phone:</strong> +1234567890</div>
                    </div>
                    <hr>
                    <div class="row text-center">
                        <div class="col-4">
                            <h6 class="text-success">15</h6>
                            <small>Present Days</small>
                        </div>
                        <div class="col-4">
                            <h6 class="text-warning">2</h6>
                            <small>Late Days</small>
                        </div>
                        <div class="col-4">
                            <h6 class="text-danger">1</h6>
                            <small>Absent Days</small>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }, 1000);
}

function sendMessage(employeeId) {
    showToast('Message feature will be implemented soon', 'info');
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

// Auto-refresh team data every 5 minutes
setInterval(() => {
    location.reload();
}, 300000);
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