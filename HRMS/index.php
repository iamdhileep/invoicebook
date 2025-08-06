<?php
$page_title = "HRMS Dashboard";
require_once 'includes/hrms_config.php';
require_once '../layouts/header.php';
require_once '../layouts/sidebar.php';

// Require login
HRMSHelper::requireLogin();

// Get current user info
$currentUserId = HRMSHelper::getCurrentUserId();
$currentUserRole = HRMSHelper::getCurrentUserRole();

// Fetch dashboard statistics
$totalEmployees = 0;
$activeEmployees = 0;
$presentToday = 0;
$pendingLeaves = 0;
$upcomingBirthdays = [];
$recentJoinings = [];
$totalDepartments = 0;

try {
    // Total employees
    $result = $conn->query("SELECT COUNT(*) as total FROM hr_employees WHERE is_active = 1");
    if ($result) {
        $totalEmployees = $result->fetch_assoc()['total'];
    }
    
    // Active employees (employment_status = 'active')
    $result = $conn->query("SELECT COUNT(*) as active FROM hr_employees WHERE employment_status = 'active' AND is_active = 1");
    if ($result) {
        $activeEmployees = $result->fetch_assoc()['active'];
    }
    
    // Present today
    $today = date('Y-m-d');
    $result = $conn->query("SELECT COUNT(*) as present FROM hr_attendance WHERE attendance_date = '$today' AND status = 'present'");
    if ($result) {
        $presentToday = $result->fetch_assoc()['present'];
    }
    
    // Pending leave applications
    $result = $conn->query("SELECT COUNT(*) as pending FROM hr_leave_applications WHERE status = 'pending'");
    if ($result) {
        $pendingLeaves = $result->fetch_assoc()['pending'];
    }
    
    // Total departments
    $result = $conn->query("SELECT COUNT(*) as total FROM hr_departments WHERE status = 'active'");
    if ($result) {
        $totalDepartments = $result->fetch_assoc()['total'];
    }
    
    // Upcoming birthdays (next 7 days)
    $nextWeek = date('Y-m-d', strtotime('+7 days'));
    $result = $conn->query("
        SELECT e.first_name, e.last_name, e.date_of_birth, d.department_name 
        FROM hr_employees e 
        LEFT JOIN hr_departments d ON e.department_id = d.id 
        WHERE e.is_active = 1 
        AND DATE_FORMAT(e.date_of_birth, '%m-%d') BETWEEN DATE_FORMAT(CURDATE(), '%m-%d') AND DATE_FORMAT('$nextWeek', '%m-%d')
        ORDER BY DATE_FORMAT(e.date_of_birth, '%m-%d')
        LIMIT 5
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $upcomingBirthdays[] = $row;
        }
    }
    
    // Recent joinings (last 30 days)
    $lastMonth = date('Y-m-d', strtotime('-30 days'));
    $result = $conn->query("
        SELECT e.first_name, e.last_name, e.date_of_joining, d.department_name, des.designation_name 
        FROM hr_employees e 
        LEFT JOIN hr_departments d ON e.department_id = d.id 
        LEFT JOIN hr_designations des ON e.designation_id = des.id 
        WHERE e.date_of_joining >= '$lastMonth' AND e.is_active = 1 
        ORDER BY e.date_of_joining DESC 
        LIMIT 5
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recentJoinings[] = $row;
        }
    }
    
} catch (Exception $e) {
    error_log("HRMS Dashboard Error: " . $e->getMessage());
}
?>

<style>
/* HRMS Dashboard Specific Styles */
.hrms-dashboard {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: calc(100vh - 80px);
    padding: 2rem 0;
}

.dashboard-header {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 2rem;
    color: white;
    text-align: center;
}

.dashboard-header h1 {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.dashboard-header p {
    font-size: 1.1rem;
    opacity: 0.9;
    margin: 0;
}

.stat-card {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 20px;
    padding: 2rem;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    border: none;
    height: 100%;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.stat-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 1rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: white;
}

.stat-icon.employees { background: linear-gradient(135deg, #667eea, #764ba2); }
.stat-icon.attendance { background: linear-gradient(135deg, #f093fb, #f5576c); }
.stat-icon.leaves { background: linear-gradient(135deg, #4facfe, #00f2fe); }
.stat-icon.departments { background: linear-gradient(135deg, #43e97b, #38f9d7); }

.stat-number {
    font-size: 3rem;
    font-weight: 700;
    color: #2c3e50;
    margin: 0;
    line-height: 1;
}

.stat-label {
    font-size: 1rem;
    color: #6c757d;
    margin-top: 0.5rem;
    font-weight: 500;
}

.info-card {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    height: 100%;
}

.info-card h5 {
    color: #2c3e50;
    font-weight: 600;
    margin-bottom: 1rem;
}

.birthday-item, .joining-item {
    display: flex;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid #e9ecef;
}

.birthday-item:last-child, .joining-item:last-child {
    border-bottom: none;
}

.birthday-avatar, .joining-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    margin-right: 1rem;
    flex-shrink: 0;
}

.birthday-info, .joining-info {
    flex: 1;
}

.birthday-name, .joining-name {
    font-weight: 600;
    color: #2c3e50;
    margin: 0;
}

.birthday-dept, .joining-dept {
    font-size: 0.875rem;
    color: #6c757d;
    margin: 0;
}

.birthday-date, .joining-date {
    font-size: 0.875rem;
    color: #28a745;
    font-weight: 500;
}

.quick-actions {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

.quick-action-btn {
    display: flex;
    align-items: center;
    padding: 1rem;
    border-radius: 15px;
    text-decoration: none;
    color: #2c3e50;
    transition: all 0.3s ease;
    margin-bottom: 0.75rem;
    background: #f8f9fa;
    border: 2px solid transparent;
}

.quick-action-btn:hover {
    transform: translateX(5px);
    background: white;
    border-color: #667eea;
    color: #667eea;
    text-decoration: none;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.quick-action-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    font-size: 1.5rem;
    color: white;
}

.welcome-message {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 15px;
    padding: 1rem 1.5rem;
    margin-bottom: 1rem;
    color: white;
}

.role-badge {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
}
</style>

<div class="main-content hrms-dashboard">
    <div class="container-fluid">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <h1><i class="bi bi-people-fill me-3"></i>HRMS Dashboard</h1>
            <p>Comprehensive Human Resource Management System</p>
            
            <div class="welcome-message">
                <span>Welcome back, <?= $_SESSION['username'] ?? 'User' ?>!</span>
                <span class="role-badge ms-2"><?= ucfirst($currentUserRole) ?></span>
                <span class="ms-3"><i class="bi bi-calendar3 me-1"></i><?= date('l, F j, Y') ?></span>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-lg-6">
                <div class="stat-card">
                    <div class="stat-icon employees">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <h2 class="stat-number"><?= $totalEmployees ?></h2>
                    <p class="stat-label">Total Employees</p>
                    <small class="text-success">
                        <i class="bi bi-arrow-up me-1"></i><?= $activeEmployees ?> Active
                    </small>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6">
                <div class="stat-card">
                    <div class="stat-icon attendance">
                        <i class="bi bi-calendar-check-fill"></i>
                    </div>
                    <h2 class="stat-number"><?= $presentToday ?></h2>
                    <p class="stat-label">Present Today</p>
                    <small class="text-info">
                        <i class="bi bi-clock me-1"></i>As of <?= date('H:i') ?>
                    </small>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6">
                <div class="stat-card">
                    <div class="stat-icon leaves">
                        <i class="bi bi-calendar-x-fill"></i>
                    </div>
                    <h2 class="stat-number"><?= $pendingLeaves ?></h2>
                    <p class="stat-label">Pending Leaves</p>
                    <small class="text-warning">
                        <i class="bi bi-hourglass-split me-1"></i>Requires Action
                    </small>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6">
                <div class="stat-card">
                    <div class="stat-icon departments">
                        <i class="bi bi-building"></i>
                    </div>
                    <h2 class="stat-number"><?= $totalDepartments ?></h2>
                    <p class="stat-label">Active Departments</p>
                    <small class="text-success">
                        <i class="bi bi-check-circle me-1"></i>All Active
                    </small>
                </div>
            </div>
        </div>

        <!-- Main Dashboard Content -->
        <div class="row g-4">
            <!-- Quick Actions -->
            <div class="col-xl-4 col-lg-6">
                <div class="quick-actions">
                    <h5 class="mb-3"><i class="bi bi-lightning-fill me-2 text-warning"></i>Quick Actions</h5>
                    
                    <?php if (HRMSHelper::hasPermission('employee_management')): ?>
                    <a href="employee_directory.php" class="quick-action-btn">
                        <div class="quick-action-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                            <i class="bi bi-people"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">Employee Directory</div>
                            <small class="text-muted">Manage employee records</small>
                        </div>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (HRMSHelper::hasPermission('attendance_management')): ?>
                    <a href="attendance_management.php" class="quick-action-btn">
                        <div class="quick-action-icon" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">Attendance</div>
                            <small class="text-muted">Track attendance records</small>
                        </div>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (HRMSHelper::hasPermission('leave_management')): ?>
                    <a href="leave_management.php" class="quick-action-btn">
                        <div class="quick-action-icon" style="background: linear-gradient(135deg, #4facfe, #00f2fe);">
                            <i class="bi bi-calendar-x"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">Leave Management</div>
                            <small class="text-muted">Handle leave requests</small>
                        </div>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (HRMSHelper::hasPermission('payroll_management')): ?>
                    <a href="payroll_processing.php" class="quick-action-btn">
                        <div class="quick-action-icon" style="background: linear-gradient(135deg, #43e97b, #38f9d7);">
                            <i class="bi bi-currency-exchange"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">Payroll</div>
                            <small class="text-muted">Process monthly payroll</small>
                        </div>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Upcoming Birthdays -->
            <div class="col-xl-4 col-lg-6">
                <div class="info-card">
                    <h5><i class="bi bi-gift-fill me-2 text-danger"></i>Upcoming Birthdays</h5>
                    
                    <?php if (!empty($upcomingBirthdays)): ?>
                        <?php foreach ($upcomingBirthdays as $birthday): ?>
                            <div class="birthday-item">
                                <div class="birthday-avatar">
                                    <?= strtoupper(substr($birthday['first_name'], 0, 1) . substr($birthday['last_name'], 0, 1)) ?>
                                </div>
                                <div class="birthday-info">
                                    <p class="birthday-name"><?= htmlspecialchars($birthday['first_name'] . ' ' . $birthday['last_name']) ?></p>
                                    <p class="birthday-dept"><?= htmlspecialchars($birthday['department_name'] ?? 'N/A') ?></p>
                                </div>
                                <div class="birthday-date">
                                    <?= date('M j', strtotime($birthday['date_of_birth'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted text-center py-3">
                            <i class="bi bi-calendar-x display-4 d-block mb-2 opacity-50"></i>
                            No upcoming birthdays this week
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Joinings -->
            <div class="col-xl-4 col-lg-12">
                <div class="info-card">
                    <h5><i class="bi bi-person-plus-fill me-2 text-success"></i>Recent Joinings</h5>
                    
                    <?php if (!empty($recentJoinings)): ?>
                        <?php foreach ($recentJoinings as $joining): ?>
                            <div class="joining-item">
                                <div class="joining-avatar">
                                    <?= strtoupper(substr($joining['first_name'], 0, 1) . substr($joining['last_name'], 0, 1)) ?>
                                </div>
                                <div class="joining-info">
                                    <p class="joining-name"><?= htmlspecialchars($joining['first_name'] . ' ' . $joining['last_name']) ?></p>
                                    <p class="joining-dept"><?= htmlspecialchars($joining['designation_name'] ?? 'N/A') ?></p>
                                </div>
                                <div class="joining-date">
                                    <?= date('M j', strtotime($joining['date_of_joining'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted text-center py-3">
                            <i class="bi bi-person-x display-4 d-block mb-2 opacity-50"></i>
                            No recent joinings in the last 30 days
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Portal Access Section -->
        <?php if (HRMSHelper::hasPermission('all') || $currentUserRole === 'hr'): ?>
        <div class="row g-4 mt-4">
            <div class="col-12">
                <div class="quick-actions">
                    <h5 class="mb-3"><i class="bi bi-door-open-fill me-2 text-primary"></i>Portal Access</h5>
                    
                    <div class="row g-3">
                        <div class="col-md-4">
                            <a href="hr_panel.php" class="quick-action-btn">
                                <div class="quick-action-icon" style="background: linear-gradient(135deg, #dc3545, #c82333);">
                                    <i class="bi bi-shield-check"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold">HR Portal</div>
                                    <small class="text-muted">Complete HR management access</small>
                                </div>
                            </a>
                        </div>
                        
                        <div class="col-md-4">
                            <a href="manager_panel.php" class="quick-action-btn">
                                <div class="quick-action-icon" style="background: linear-gradient(135deg, #ffc107, #e0a800);">
                                    <i class="bi bi-person-badge"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold">Manager Portal</div>
                                    <small class="text-muted">Team management and approvals</small>
                                </div>
                            </a>
                        </div>
                        
                        <div class="col-md-4">
                            <a href="employee_panel.php" class="quick-action-btn">
                                <div class="quick-action-icon" style="background: linear-gradient(135deg, #17a2b8, #138496);">
                                    <i class="bi bi-person-circle"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold">Employee Portal</div>
                                    <small class="text-muted">Self-service employee portal</small>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../layouts/footer.php'; ?>
    trackPagePerformance();
    initDashboardClock();
    initAutoRefresh();
}, 100);

// Add keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+R or F5 for refresh
    if ((e.ctrlKey && e.key === 'r') || e.key === 'F5') {
        showToast('Refreshing dashboard...', 'info', 1000);
    }
    
    // Escape to close any open modals or overlays
    if (e.key === 'Escape') {
        const activeModal = document.querySelector('.modal.show');
        if (activeModal) {
            // Close modal if Bootstrap is available
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                const modal = bootstrap.Modal.getInstance(activeModal);
                if (modal) modal.hide();
            }
        }
    }
});

// Error handling for failed AJAX requests
window.addEventListener('error', function(e) {
    console.error('Dashboard error:', e.error);
    showToast('An error occurred. Please refresh the page.', 'error');
});

// Service worker registration for offline capabilities (if needed)
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        // navigator.serviceWorker.register('/sw.js'); // Uncomment if you add a service worker
    });
}
</script>

<?php include '../layouts/footer.php'; ?>
