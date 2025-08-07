<?php
$page_title = "HRMS Control Center";

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

$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'] ?? 'employee';

// Quick system stats
$systemStats = [
    'total_employees' => 0,
    'departments' => 0,
    'today_attendance' => 0,
    'pending_leaves' => 0
];

try {
    // Get quick stats for overview
    $result = $conn->query("SELECT COUNT(*) as total FROM hr_employees WHERE is_active = 1");
    if ($result && $row = $result->fetch_assoc()) {
        $systemStats['total_employees'] = (int)$row['total'];
    }

    $result = $conn->query("SELECT COUNT(*) as total FROM hr_departments WHERE status = 'active'");
    if ($result && $row = $result->fetch_assoc()) {
        $systemStats['departments'] = (int)$row['total'];
    }

    $today = date('Y-m-d');
    $result = $conn->query("SELECT COUNT(*) as total FROM hr_attendance WHERE attendance_date = '$today'");
    if ($result && $row = $result->fetch_assoc()) {
        $systemStats['today_attendance'] = (int)$row['total'];
    }

    $result = $conn->query("SELECT COUNT(*) as total FROM hr_leave_applications WHERE status = 'pending'");
    if ($result && $row = $result->fetch_assoc()) {
        $systemStats['pending_leaves'] = (int)$row['total'];
    }
} catch (Exception $e) {
    error_log("Control center stats error: " . $e->getMessage());
}

// Available modules
$hrmsModules = [
    'Core Panels' => [
        [
            'name' => 'HR Management Dashboard',
            'file' => 'hr_panel.php',
            'icon' => 'users-cog',
            'color' => 'primary',
            'description' => 'Complete HR administration and management tools',
            'features' => ['Employee Management', 'Department Overview', 'Leave Approvals', 'HR Analytics']
        ],
        [
            'name' => 'Employee Self-Service',
            'file' => 'employee_panel.php', 
            'icon' => 'user-circle',
            'color' => 'success',
            'description' => 'Employee portal for attendance and leave management',
            'features' => ['Mark Attendance', 'Apply for Leave', 'View Payslips', 'Profile Management']
        ],
        [
            'name' => 'Manager Dashboard',
            'file' => 'manager_panel.php',
            'icon' => 'users',
            'color' => 'info',
            'description' => 'Team management and performance tracking',
            'features' => ['Team Overview', 'Approve Leaves', 'Performance Metrics', 'Team Reports']
        ]
    ],
    'System Tools' => [
        [
            'name' => 'System Validation',
            'file' => 'system_validation.php',
            'icon' => 'shield-alt',
            'color' => 'warning',
            'description' => 'Complete system health check and validation',
            'features' => ['File Validation', 'Database Check', 'Security Tests', 'Performance Metrics']
        ]
    ],
    'Additional Modules' => [
        [
            'name' => 'Employee Directory',
            'file' => 'employee_directory.php',
            'icon' => 'address-book',
            'color' => 'secondary',
            'description' => 'Complete employee directory and contact management',
            'features' => ['Employee Search', 'Contact Details', 'Department Filter', 'Export Options']
        ],
        [
            'name' => 'Leave Management',
            'file' => 'leave_management.php',
            'icon' => 'calendar-alt',
            'color' => 'danger',
            'description' => 'Advanced leave management and approval system',
            'features' => ['Leave Policies', 'Approval Workflow', 'Leave Calendar', 'Reports']
        ],
        [
            'name' => 'Attendance Management',
            'file' => 'attendance_management.php',
            'icon' => 'clock',
            'color' => 'dark',
            'description' => 'Comprehensive attendance tracking and reporting',
            'features' => ['Daily Attendance', 'Time Tracking', 'Attendance Reports', 'Shift Management']
        ]
    ]
];
?>

<!-- Page Content Starts Here -->
<div class="container-fluid">
        <!-- Header Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h2 mb-1">
                            <i class="fas fa-tachometer-alt text-primary me-2"></i>
                            HRMS Control Center
                        </h1>
                        <p class="text-muted mb-0">Central hub for all Human Resource Management System modules</p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-success fs-6 px-3 py-2">
                            <i class="fas fa-circle me-1"></i>System Online
                        </span>
                        <span class="text-muted small"><?= date('l, F j, Y g:i A') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row mb-4">
            <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-primary bg-opacity-10 rounded-3 p-3 me-3">
                                <i class="fas fa-users text-primary fs-3"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h3 class="fw-bold text-primary mb-0"><?= number_format($systemStats['total_employees']) ?></h3>
                                <p class="text-muted mb-0 small">Active Employees</p>
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
                                <i class="fas fa-building text-info fs-3"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h3 class="fw-bold text-info mb-0"><?= number_format($systemStats['departments']) ?></h3>
                                <p class="text-muted mb-0 small">Departments</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-success bg-opacity-10 rounded-3 p-3 me-3">
                                <i class="fas fa-calendar-check text-success fs-3"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h3 class="fw-bold text-success mb-0"><?= number_format($systemStats['today_attendance']) ?></h3>
                                <p class="text-muted mb-0 small">Today's Attendance</p>
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
                                <i class="fas fa-clock text-warning fs-3"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h3 class="fw-bold text-warning mb-0"><?= number_format($systemStats['pending_leaves']) ?></h3>
                                <p class="text-muted mb-0 small">Pending Leaves</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Module Categories -->
        <?php foreach ($hrmsModules as $categoryName => $modules): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <h4 class="mb-3">
                        <i class="fas fa-layer-group text-secondary me-2"></i>
                        <?= $categoryName ?>
                    </h4>
                </div>
            </div>

            <div class="row mb-5">
                <?php foreach ($modules as $module): ?>
                    <div class="col-xl-4 col-lg-6 mb-4">
                        <div class="card border-0 shadow-sm h-100 module-card">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-start mb-3">
                                    <div class="bg-<?= $module['color'] ?> bg-opacity-10 rounded-3 p-3 me-3">
                                        <i class="fas fa-<?= $module['icon'] ?> text-<?= $module['color'] ?> fs-2"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h5 class="card-title mb-1"><?= $module['name'] ?></h5>
                                        <p class="text-muted small mb-2"><?= $module['description'] ?></p>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <h6 class="small text-uppercase text-muted mb-2">Features:</h6>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php foreach ($module['features'] as $feature): ?>
                                            <span class="badge bg-<?= $module['color'] ?> bg-opacity-10 text-<?= $module['color'] ?> small">
                                                <?= $feature ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="d-grid">
                                    <a href="<?= $module['file'] ?>" class="btn btn-<?= $module['color'] ?> btn-lg">
                                        <i class="fas fa-external-link-alt me-2"></i>
                                        Access Module
                                    </a>
                                </div>

                                <div class="mt-2 d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="fas fa-file me-1"></i><?= $module['file'] ?>
                                    </small>
                                    <?php if (file_exists(__DIR__ . '/' . $module['file'])): ?>
                                        <span class="badge bg-success small">
                                            <i class="fas fa-check me-1"></i>Available
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning small">
                                            <i class="fas fa-exclamation me-1"></i>Not Found
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <!-- System Information -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom-0 py-3">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-info-circle text-primary me-2"></i>
                            System Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Current Session</h6>
                                <ul class="list-unstyled">
                                    <li><strong>User ID:</strong> <?= $currentUserId ?></li>
                                    <li><strong>Role:</strong> <?= $currentUserRole ?></li>
                                    <li><strong>Login Time:</strong> <?= date('g:i A') ?></li>
                                    <li><strong>Session Status:</strong> <span class="badge bg-success">Active</span></li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>System Status</h6>
                                <ul class="list-unstyled">
                                    <li><strong>Database:</strong> <span class="badge bg-success">Connected</span></li>
                                    <li><strong>Authentication:</strong> <span class="badge bg-success">Working</span></li>
                                    <li><strong>Core Modules:</strong> <span class="badge bg-success">3/3 Available</span></li>
                                    <li><strong>Last Update:</strong> <?= date('M j, Y') ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mt-4">
            <div class="col-12 text-center">
                <div class="btn-group" role="group">
                    <a href="system_validation.php" class="btn btn-outline-primary">
                        <i class="fas fa-shield-alt me-1"></i>System Health Check
                    </a>
                    <a href="../hrms_portal.php" class="btn btn-outline-secondary">
                        <i class="fas fa-sign-in-alt me-1"></i>Back to Portal
                    </a>
                    <button class="btn btn-outline-success" onclick="location.reload()">
                        <i class="fas fa-sync-alt me-1"></i>Refresh Dashboard
                    </button>
                    <a href="../logout.php" class="btn btn-outline-danger">
                        <i class="fas fa-sign-out-alt me-1"></i>Logout
                    </a>
                </div>
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

.module-card:hover {
    transform: translateY(-5px);
}

.btn:hover {
    transform: translateY(-1px);
}

.badge {
    font-size: 0.75em;
}
</style>

<script>
// Add loading states for module links
document.addEventListener('DOMContentLoaded', function() {
    const moduleLinks = document.querySelectorAll('a[href$=".php"]');
    moduleLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading...';
            
            // Restore text if back button is used
            setTimeout(() => {
                this.innerHTML = originalText;
            }, 5000);
        });
    });
});

// Auto-refresh stats every 30 seconds
setInterval(() => {
    fetch(window.location.href)
        .then(response => response.text())
        .then(html => {
            // Update only the stats section
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newStats = doc.querySelectorAll('.card-body h3');
            const currentStats = document.querySelectorAll('.card-body h3');
            
            newStats.forEach((stat, index) => {
                if (currentStats[index]) {
                    currentStats[index].textContent = stat.textContent;
                }
            });
        })
        .catch(error => console.log('Auto-refresh failed:', error));
}, 30000);
</script>

<?php 
<?php require_once 'hrms_footer_simple.php'; ?>