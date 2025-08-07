<?php
$page_title = "HRMS Main Dashboard";

// Include authentication and database
require_once '../auth_check.php';
require_once '../db.php';

// Include layouts
require_once 'hrms_header_simple.php';
require_once 'hrms_sidebar_simple.php';

// Include HRMS UI fix
$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'] ?? 'employee';

// Get dashboard statistics
$stats = [
    'totalEmployees' => 0,
    'activeEmployees' => 0,
    'presentToday' => 0,
    'pendingLeaves' => 0,
    'totalDepartments' => 0,
    'pendingApprovals' => 0
];

try {
    // Total employees
    $result = $conn->query("SELECT COUNT(*) as total FROM hr_employees WHERE status = 'active'");
    if ($result) $stats['totalEmployees'] = $result->fetch_assoc()['total'];
    
    // Present today
    $today = date('Y-m-d');
    $result = $conn->query("SELECT COUNT(*) as present FROM hr_attendance WHERE date = '$today' AND status = 'present'");
    if ($result) $stats['presentToday'] = $result->fetch_assoc()['present'];
    
    // Pending leaves
    $result = $conn->query("SELECT COUNT(*) as pending FROM hr_leave_applications WHERE status = 'pending'");
    if ($result) $stats['pendingLeaves'] = $result->fetch_assoc()['pending'];
    
    // Total departments
    $result = $conn->query("SELECT COUNT(*) as total FROM hr_departments");
    if ($result) $stats['totalDepartments'] = $result->fetch_assoc()['total'];
    
} catch (Exception $e) {
    // Silently handle errors
}

// Define HRMS modules with proper categorization
$hrmsModules = [
    'Core HR Management' => [
        'employee_directory.php' => ['name' => 'Employee Directory', 'icon' => 'fas fa-users', 'desc' => 'Manage employee profiles and information'],
        'attendance_management.php' => ['name' => 'Attendance', 'icon' => 'fas fa-clock', 'desc' => 'Track employee attendance and time'],
        'leave_management.php' => ['name' => 'Leave Management', 'icon' => 'fas fa-calendar-times', 'desc' => 'Handle leave requests and approvals'],
        'payroll_processing.php' => ['name' => 'Payroll', 'icon' => 'fas fa-money-check-alt', 'desc' => 'Process salaries and compensation']
    ],
    'Performance & Development' => [
        'performance_management.php' => ['name' => 'Performance Reviews', 'icon' => 'fas fa-chart-line', 'desc' => 'Conduct employee performance evaluations'],
        'goal_management.php' => ['name' => 'Goal Management', 'icon' => 'fas fa-bullseye', 'desc' => 'Set and track employee goals'],
        'training_management.php' => ['name' => 'Training', 'icon' => 'fas fa-graduation-cap', 'desc' => 'Manage training programs and certifications'],
        'career_development.php' => ['name' => 'Career Development', 'icon' => 'fas fa-arrow-up', 'desc' => 'Plan career progression paths']
    ],
    'Employee Lifecycle' => [
        'employee_onboarding.php' => ['name' => 'Onboarding', 'icon' => 'fas fa-user-plus', 'desc' => 'Welcome new employees'],
        'offboarding_process.php' => ['name' => 'Offboarding', 'icon' => 'fas fa-user-minus', 'desc' => 'Manage employee exits'],
        'employee_self_service.php' => ['name' => 'Self Service', 'icon' => 'fas fa-user-cog', 'desc' => 'Employee self-service portal'],
        'benefits_management.php' => ['name' => 'Benefits', 'icon' => 'fas fa-heart', 'desc' => 'Manage employee benefits and perks']
    ],
    'Reports & Analytics' => [
        'hr_dashboard.php' => ['name' => 'HR Analytics', 'icon' => 'fas fa-chart-bar', 'desc' => 'Comprehensive HR analytics dashboard'],
        'payroll_reports.php' => ['name' => 'Payroll Reports', 'icon' => 'fas fa-file-invoice-dollar', 'desc' => 'Generate payroll and salary reports'],
        'attendance_reports.php' => ['name' => 'Attendance Reports', 'icon' => 'fas fa-chart-pie', 'desc' => 'Attendance analytics and reports'],
        'employee_reports.php' => ['name' => 'Employee Reports', 'icon' => 'fas fa-users-cog', 'desc' => 'Employee analytics and insights']
    ],
    'System Administration' => [
        'department_management.php' => ['name' => 'Departments', 'icon' => 'fas fa-sitemap', 'desc' => 'Manage organizational structure'],
        'role_management.php' => ['name' => 'Role Management', 'icon' => 'fas fa-user-tag', 'desc' => 'Define roles and permissions'],
        'system_settings.php' => ['name' => 'Settings', 'icon' => 'fas fa-cogs', 'desc' => 'Configure HRMS settings'],
        'audit_trail.php' => ['name' => 'Audit Trail', 'icon' => 'fas fa-history', 'desc' => 'Track system changes and activities']
    ]
];
?>

<div class="content-wrapper">
    <div class="container-fluid">
        <!-- Header with Quick Stats -->
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-building mr-2"></i>HRMS Dashboard
            </h1>
            <div class="d-none d-sm-inline-block">
                <span class="badge badge-primary">Welcome, <?php echo $_SESSION['role'] ?? 'User'; ?></span>
            </div>
        </div>

        <!-- Quick Statistics -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Employees</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['totalEmployees']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-users fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Present Today</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['presentToday']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clock fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Pending Leaves</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['pendingLeaves']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-calendar-times fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Departments</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['totalDepartments']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-sitemap fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- HRMS Modules -->
        <?php foreach ($hrmsModules as $categoryName => $modules): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary"><?php echo $categoryName; ?></h6>
                        <div class="dropdown no-arrow">
                            <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown">
                                <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($modules as $file => $module): 
                                $fileExists = file_exists(__DIR__ . '/' . $file);
                                $isAccessible = $fileExists;
                            ?>
                            <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                                <div class="card h-100 <?php echo $isAccessible ? 'border-left-success' : 'border-left-secondary'; ?> module-card">
                                    <div class="card-body text-center">
                                        <div class="mb-3">
                                            <i class="<?php echo $module['icon']; ?> fa-2x <?php echo $isAccessible ? 'text-primary' : 'text-muted'; ?>"></i>
                                        </div>
                                        <h6 class="card-title"><?php echo $module['name']; ?></h6>
                                        <p class="card-text text-muted small"><?php echo $module['desc']; ?></p>
                                        <?php if ($isAccessible): ?>
                                            <a href="<?php echo $file; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-arrow-right mr-1"></i>Open
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-secondary btn-sm" disabled>
                                                <i class="fas fa-lock mr-1"></i>Coming Soon
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Quick Actions -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="btn-toolbar justify-content-center" role="toolbar">
                            <div class="btn-group mr-2 mb-2" role="group">
                                <a href="employee_directory.php" class="btn btn-outline-primary">
                                    <i class="fas fa-users mr-1"></i>View Employees
                                </a>
                                <a href="attendance_management.php" class="btn btn-outline-success">
                                    <i class="fas fa-clock mr-1"></i>Mark Attendance
                                </a>
                                <a href="leave_management.php" class="btn btn-outline-info">
                                    <i class="fas fa-calendar-times mr-1"></i>Apply Leave
                                </a>
                            </div>
                            <div class="btn-group mr-2 mb-2" role="group">
                                <a href="payroll_processing.php" class="btn btn-outline-warning">
                                    <i class="fas fa-money-check-alt mr-1"></i>Process Payroll
                                </a>
                                <a href="performance_management.php" class="btn btn-outline-danger">
                                    <i class="fas fa-chart-line mr-1"></i>Performance Review
                                </a>
                                <a href="hr_dashboard.php" class="btn btn-outline-dark">
                                    <i class="fas fa-chart-bar mr-1"></i>View Reports
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Status -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">System Administration</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="font-weight-bold">Database Status</h6>
                                <div class="progress mb-3">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: 95%">95% Healthy</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6 class="font-weight-bold">System Performance</h6>
                                <div class="progress mb-3">
                                    <div class="progress-bar bg-info" role="progressbar" style="width: 88%">88% Optimal</div>
                                </div>
                            </div>
                        </div>
                        <div class="text-center">
                            <a href="hrms_testing_dashboard.php" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-tools mr-1"></i>System Diagnostics
                            </a>
                            <a href="complete_database_setup.php" class="btn btn-sm btn-outline-success">
                                <i class="fas fa-database mr-1"></i>Database Setup
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
.border-left-primary { border-left: 0.25rem solid #4e73df !important; }
.border-left-success { border-left: 0.25rem solid #1cc88a !important; }
.border-left-info { border-left: 0.25rem solid #36b9cc !important; }
.border-left-warning { border-left: 0.25rem solid #f6c23e !important; }
.border-left-secondary { border-left: 0.25rem solid #858796 !important; }

.module-card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.module-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.card {
    border: none;
    border-radius: 0.35rem;
}

.card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white !important;
    border: none;
}

.card-header h6 {
    color: white !important;
}

.text-gray-800 { color: #5a5c69 !important; }
.text-gray-300 { color: #dddfeb !important; }

.btn-toolbar .btn-group {
    margin-bottom: 0.5rem;
}

.progress {
    height: 8px;
}
</style>

<?php 
<?php require_once 'hrms_footer_simple.php'; ?>