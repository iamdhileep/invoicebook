<?php
$page_title = "HRMS Dashboard";

// Include authentication and database
require_once '../auth_check.php';
require_once '../db.php';

// Include layouts
require_once 'hrms_header_simple.php';
require_once 'hrms_sidebar_simple.php';

// Initialize dashboard statistics with proper error handling
$stats = [
    'totalEmployees' => 0,
    'activeEmployees' => 0,
    'presentToday' => 0,
    'pendingLeaves' => 0,
    'totalDepartments' => 0
];

$upcomingBirthdays = [];
$recentJoinings = [];

try {
    // Total employees
    $result = $conn->query("SELECT COUNT(*) as total FROM hr_employees WHERE status = 'active'");
    if ($result && $result->num_rows > 0) {
        $stats['totalEmployees'] = $result->fetch_assoc()['total'];
    }
    
    // Active employees by employment type
    $result = $conn->query("SELECT COUNT(*) as active FROM hr_employees WHERE employment_type = 'full_time' AND status = 'active'");
    if ($result && $result->num_rows > 0) {
        $stats['activeEmployees'] = $result->fetch_assoc()['active'];
    }
    
    // Present today
    $today = date('Y-m-d');
    $result = $conn->query("SELECT COUNT(*) as present FROM hr_attendance WHERE date = '$today' AND clock_in IS NOT NULL");
    if ($result && $result->num_rows > 0) {
        $stats['presentToday'] = $result->fetch_assoc()['present'];
    }
    
    // Pending leave applications
    $result = $conn->query("SELECT COUNT(*) as pending FROM hr_leave_applications WHERE status = 'pending'");
    if ($result && $result->num_rows > 0) {
        $stats['pendingLeaves'] = $result->fetch_assoc()['pending'];
    }
    
    // Total departments
    $result = $conn->query("SELECT COUNT(*) as total FROM hr_departments WHERE status = 'active'");
    if ($result && $result->num_rows > 0) {
        $stats['totalDepartments'] = $result->fetch_assoc()['total'];
    }
    
    // Upcoming birthdays (next 7 days)
    $nextWeek = date('m-d', strtotime('+7 days'));
    $result = $conn->query("
        SELECT e.first_name, e.last_name, e.date_of_birth, d.department_name 
        FROM hr_employees e 
        LEFT JOIN hr_departments d ON e.department_id = d.id 
        WHERE e.status = 'active' 
        AND DATE_FORMAT(e.date_of_birth, '%m-%d') >= DATE_FORMAT(CURDATE(), '%m-%d')
        AND DATE_FORMAT(e.date_of_birth, '%m-%d') <= '$nextWeek'
        ORDER BY DATE_FORMAT(e.date_of_birth, '%m-%d')
        LIMIT 5
    ");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $upcomingBirthdays[] = $row;
        }
    }
    
    // Recent joinings (last 30 days)
    $lastMonth = date('Y-m-d', strtotime('-30 days'));
    $result = $conn->query("
        SELECT e.first_name, e.last_name, e.date_of_joining, d.department_name 
        FROM hr_employees e 
        LEFT JOIN hr_departments d ON e.department_id = d.id 
        WHERE e.date_of_joining >= '$lastMonth' AND e.status = 'active' 
        ORDER BY e.date_of_joining DESC 
        LIMIT 5
    ");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $recentJoinings[] = $row;
        }
    }
    
} catch (Exception $e) {
    error_log("HRMS Dashboard Error: " . $e->getMessage());
}
?>

<!-- Dashboard Content -->
<div class="container-fluid">
    <!-- Welcome Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-lg bg-primary text-white">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <h2 class="mb-2 fw-bold">
                                <i class="bi bi-speedometer2 me-3"></i>
                                Welcome to HRMS Dashboard
                            </h2>
                            <p class="mb-0 opacity-90">
                                Professional Human Resource Management System - 
                                <?= isset($_SESSION['username']) ? 'Hello ' . ucfirst($_SESSION['username']) : 'Welcome' ?>
                                <?php if (isset($_SESSION['role'])): ?>
                                    <span class="badge bg-light text-dark ms-2"><?= ucfirst($_SESSION['role']) ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-lg-4 text-end">
                            <div class="text-white">
                                <div class="fs-5"><?= date('l, F j, Y') ?></div>
                                <div class="fs-6 opacity-75"><?= date('g:i A') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-primary mb-3">
                        <i class="bi bi-people-fill" style="font-size: 3rem;"></i>
                    </div>
                    <h3 class="mb-1 text-primary fw-bold"><?= number_format($stats['totalEmployees']) ?></h3>
                    <p class="text-muted mb-0">Total Employees</p>
                    <div class="progress mt-2" style="height: 4px;">
                        <div class="progress-bar bg-primary" style="width: 100%"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-success mb-3">
                        <i class="bi bi-check-circle-fill" style="font-size: 3rem;"></i>
                    </div>
                    <h3 class="mb-1 text-success fw-bold"><?= number_format($stats['presentToday']) ?></h3>
                    <p class="text-muted mb-0">Present Today</p>
                    <div class="progress mt-2" style="height: 4px;">
                        <div class="progress-bar bg-success" style="width: <?= $stats['totalEmployees'] > 0 ? round(($stats['presentToday'] / $stats['totalEmployees']) * 100) : 0 ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-warning mb-3">
                        <i class="bi bi-calendar-x-fill" style="font-size: 3rem;"></i>
                    </div>
                    <h3 class="mb-1 text-warning fw-bold"><?= number_format($stats['pendingLeaves']) ?></h3>
                    <p class="text-muted mb-0">Pending Leaves</p>
                    <div class="progress mt-2" style="height: 4px;">
                        <div class="progress-bar bg-warning" style="width: <?= $stats['pendingLeaves'] > 0 ? 75 : 0 ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-info mb-3">
                        <i class="bi bi-diagram-3-fill" style="font-size: 3rem;"></i>
                    </div>
                    <h3 class="mb-1 text-info fw-bold"><?= number_format($stats['totalDepartments']) ?></h3>
                    <p class="text-muted mb-0">Departments</p>
                    <div class="progress mt-2" style="height: 4px;">
                        <div class="progress-bar bg-info" style="width: 100%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Dashboard Content -->
    <div class="row g-4">
        <!-- Quick Actions -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="bi bi-lightning-fill text-warning me-2"></i>
                        Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <a href="employee_directory.php" class="text-decoration-none">
                                <div class="card bg-primary-subtle border-0 h-100">
                                    <div class="card-body text-center">
                                        <i class="bi bi-people-fill fs-1 text-primary mb-3"></i>
                                        <h6 class="fw-semibold">Employee Directory</h6>
                                        <small class="text-muted">View all employees</small>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="attendance_management.php" class="text-decoration-none">
                                <div class="card bg-success-subtle border-0 h-100">
                                    <div class="card-body text-center">
                                        <i class="bi bi-clock-fill fs-1 text-success mb-3"></i>
                                        <h6 class="fw-semibold">Attendance</h6>
                                        <small class="text-muted">Manage attendance</small>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="leave_management.php" class="text-decoration-none">
                                <div class="card bg-warning-subtle border-0 h-100">
                                    <div class="card-body text-center">
                                        <i class="bi bi-calendar-x-fill fs-1 text-warning mb-3"></i>
                                        <h6 class="fw-semibold">Leave Management</h6>
                                        <small class="text-muted">Process leave requests</small>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="payroll_processing.php" class="text-decoration-none">
                                <div class="card bg-info-subtle border-0 h-100">
                                    <div class="card-body text-center">
                                        <i class="bi bi-cash-coin fs-1 text-info mb-3"></i>
                                        <h6 class="fw-semibold">Payroll</h6>
                                        <small class="text-muted">Process payroll</small>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="performance_management.php" class="text-decoration-none">
                                <div class="card bg-danger-subtle border-0 h-100">
                                    <div class="card-body text-center">
                                        <i class="bi bi-graph-up fs-1 text-danger mb-3"></i>
                                        <h6 class="fw-semibold">Performance</h6>
                                        <small class="text-muted">Employee reviews</small>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="advanced_analytics.php" class="text-decoration-none">
                                <div class="card bg-secondary-subtle border-0 h-100">
                                    <div class="card-body text-center">
                                        <i class="bi bi-bar-chart-fill fs-1 text-secondary mb-3"></i>
                                        <h6 class="fw-semibold">Analytics</h6>
                                        <small class="text-muted">Advanced reports</small>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activities & Notifications -->
        <div class="col-lg-4">
            <div class="row g-4">
                <!-- Upcoming Birthdays -->
                <?php if (!empty($upcomingBirthdays)): ?>
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0">
                                <i class="bi bi-gift-fill me-2"></i>
                                Upcoming Birthdays
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <?php foreach ($upcomingBirthdays as $birthday): ?>
                            <div class="d-flex align-items-center p-3 border-bottom">
                                <div class="flex-shrink-0">
                                    <div class="bg-warning-subtle text-warning rounded-circle p-2">
                                        <i class="bi bi-calendar-event"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1"><?= htmlspecialchars($birthday['first_name'] . ' ' . $birthday['last_name']) ?></h6>
                                    <small class="text-muted"><?= htmlspecialchars($birthday['department_name'] ?? 'No Department') ?></small>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted"><?= date('M j', strtotime($birthday['date_of_birth'])) ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Recent Joinings -->
                <?php if (!empty($recentJoinings)): ?>
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0">
                                <i class="bi bi-person-plus-fill me-2"></i>
                                Recent Joinings
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <?php foreach ($recentJoinings as $joining): ?>
                            <div class="d-flex align-items-center p-3 border-bottom">
                                <div class="flex-shrink-0">
                                    <div class="bg-success-subtle text-success rounded-circle p-2">
                                        <i class="bi bi-person-check"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1"><?= htmlspecialchars($joining['first_name'] . ' ' . $joining['last_name']) ?></h6>
                                    <small class="text-muted"><?= htmlspecialchars($joining['department_name'] ?? 'No Department') ?></small>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted"><?= date('M j', strtotime($joining['date_of_joining'])) ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- System Status -->
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0">
                                <i class="bi bi-shield-check me-2"></i>
                                System Status
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small">Database</span>
                                <span class="badge bg-success">Online</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small">Layout System</span>
                                <span class="badge bg-success">Active</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small">Last Updated</span>
                                <span class="badge bg-light text-dark"><?= date('H:i') ?></span>
                            </div>
                            <hr>
                            <div class="text-center">
                                <a href="system_diagnostics.php" class="btn btn-sm btn-outline-info">
                                    <i class="bi bi-gear me-1"></i>Full Diagnostics
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add loading animation to stat cards
    const statCards = document.querySelectorAll('.card');
    statCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
    
    console.log('✅ HRMS Dashboard loaded successfully');
});
</script>

<?php require_once 'hrms_footer_simple.php'; ?>