<?php
/**
 * HRMS Dashboard - Main Index Page
 * Fixed with correct database column names and modern structure
 */

$page_title = "HRMS Dashboard";
require_once 'includes/hrms_config.php';

// Authentication check
if (!HRMSHelper::isLoggedIn()) {
    header('Location: ../hrms_portal.php?redirect=HRMS/index.php');
    exit;
}

require_once 'hrms_header_simple.php';
require_once 'hrms_sidebar_simple.php';

// Include HRMS UI fix
// Get current user info
$currentUserId = HRMSHelper::getCurrentUserId();
$currentUserRole = HRMSHelper::getCurrentUserRole();

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
    // Total employees (using correct column 'status')
    $result = HRMSHelper::safeQuery("SELECT COUNT(*) as total FROM hr_employees WHERE status = 'active'");
    if ($result && $result->num_rows > 0) {
        $stats['totalEmployees'] = $result->fetch_assoc()['total'];
    }
    
    // Active employees by employment type
    $result = HRMSHelper::safeQuery("SELECT COUNT(*) as active FROM hr_employees WHERE employment_type = 'full_time' AND status = 'active'");
    if ($result && $result->num_rows > 0) {
        $stats['activeEmployees'] = $result->fetch_assoc()['active'];
    }
    
    // Present today (using correct column 'date')
    $today = date('Y-m-d');
    $result = HRMSHelper::safeQuery("SELECT COUNT(*) as present FROM hr_attendance WHERE date = '$today' AND clock_in_time IS NOT NULL");
    if ($result && $result->num_rows > 0) {
        $stats['presentToday'] = $result->fetch_assoc()['present'];
    }
    
    // Pending leave applications
    $result = HRMSHelper::safeQuery("SELECT COUNT(*) as pending FROM hr_leave_applications WHERE status = 'pending'");
    if ($result && $result->num_rows > 0) {
        $stats['pendingLeaves'] = $result->fetch_assoc()['pending'];
    }
    
    // Total departments
    $result = HRMSHelper::safeQuery("SELECT COUNT(*) as total FROM hr_departments WHERE status = 'active'");
    if ($result && $result->num_rows > 0) {
        $stats['totalDepartments'] = $result->fetch_assoc()['total'];
    }
    
    // Upcoming birthdays (next 7 days)
    $nextWeek = date('m-d', strtotime('+7 days'));
    $result = HRMSHelper::safeQuery("
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
    $result = HRMSHelper::safeQuery("
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

<style>
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --success-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    --warning-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}



.dashboard-header {
    background: var(--primary-gradient);
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 2rem;
    color: white;
    text-align: center;
}

.dashboard-header h1 {
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.welcome-message {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 15px;
    padding: 1rem 1.5rem;
    margin-top: 1rem;
    display: flex;
    justify-content: center;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.role-badge {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    box-shadow: var(--card-shadow);
    text-align: center;
    transition: all 0.3s ease;
    border: none;
    height: 100%;
}

.stat-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.stat-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    font-size: 2rem;
    color: white;
}

.stat-icon.employees { background: var(--primary-gradient); }
.stat-icon.attendance { background: var(--secondary-gradient); }
.stat-icon.leaves { background: var(--warning-gradient); }
.stat-icon.departments { background: var(--success-gradient); }

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
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: var(--card-shadow);
    height: 100%;
}

.info-card h5 {
    color: #2c3e50;
    font-weight: 600;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #f8f9fa;
}

.quick-actions {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: var(--card-shadow);
    height: 100%;
}

.quick-action-btn {
    display: flex;
    align-items: center;
    padding: 1rem;
    margin-bottom: 1rem;
    background: rgba(102, 126, 234, 0.1);
    border-radius: 15px;
    text-decoration: none;
    color: #2c3e50;
    transition: all 0.3s ease;
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

.list-item {
    display: flex;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f8f9fa;
}

.list-item:last-child {
    border-bottom: none;
}

.avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary-gradient);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    margin-right: 1rem;
}

.list-info {
    flex: 1;
}

.list-name {
    font-weight: 600;
    margin: 0;
    color: #2c3e50;
}

.list-meta {
    font-size: 0.875rem;
    color: #6c757d;
    margin: 0;
}

.list-date {
    font-size: 0.875rem;
    color: #495057;
    font-weight: 500;
}

.empty-state {
    text-align: center;
    padding: 2rem;
    color: #6c757d;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

@media (max-width: 768px) {
    
    
    .dashboard-header {
        padding: 1.5rem;
        margin-bottom: 1rem;
    }
    
    .welcome-message {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .stat-card {
        padding: 1.5rem;
        margin-bottom: 1rem;
    }
    
    .stat-number {
        font-size: 2.5rem;
    }
}
</style>

<!-- Page Content Starts Here -->
<div class="container-fluid">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <h1><i class="bi bi-people-fill me-3"></i>HRMS Dashboard</h1>
            <p class="mb-0">Comprehensive Human Resource Management System</p>
            
            <div class="welcome-message">
                <span>Welcome back, <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>!</span>
                <span class="role-badge"><?= ucfirst($currentUserRole) ?></span>
                <span><i class="bi bi-calendar3 me-1"></i><?= date('l, F j, Y') ?></span>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon employees">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <h2 class="stat-number"><?= $stats['totalEmployees'] ?></h2>
                    <p class="stat-label">Total Employees</p>
                    <small class="text-success">
                        <i class="bi bi-arrow-up me-1"></i><?= $stats['activeEmployees'] ?> Full-time
                    </small>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon attendance">
                        <i class="bi bi-calendar-check-fill"></i>
                    </div>
                    <h2 class="stat-number"><?= $stats['presentToday'] ?></h2>
                    <p class="stat-label">Present Today</p>
                    <small class="text-info">
                        <i class="bi bi-clock me-1"></i>As of <?= date('H:i') ?>
                    </small>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon leaves">
                        <i class="bi bi-calendar-x-fill"></i>
                    </div>
                    <h2 class="stat-number"><?= $stats['pendingLeaves'] ?></h2>
                    <p class="stat-label">Pending Leaves</p>
                    <small class="text-warning">
                        <i class="bi bi-hourglass-split me-1"></i>Requires Action
                    </small>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon departments">
                        <i class="bi bi-building"></i>
                    </div>
                    <h2 class="stat-number"><?= $stats['totalDepartments'] ?></h2>
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
                    
                    <?php if (HRMSHelper::hasPermission('employee_management') || $currentUserRole === 'admin'): ?>
                    <a href="employee_directory.php" class="quick-action-btn">
                        <div class="quick-action-icon" style="background: var(--primary-gradient);">
                            <i class="bi bi-people"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">Employee Directory</div>
                            <small class="text-muted">Manage employee records</small>
                        </div>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (HRMSHelper::hasPermission('attendance_management') || $currentUserRole === 'admin'): ?>
                    <a href="attendance_management.php" class="quick-action-btn">
                        <div class="quick-action-icon" style="background: var(--secondary-gradient);">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">Attendance</div>
                            <small class="text-muted">Track attendance records</small>
                        </div>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (HRMSHelper::hasPermission('leave_management') || $currentUserRole === 'admin'): ?>
                    <a href="leave_management.php" class="quick-action-btn">
                        <div class="quick-action-icon" style="background: var(--warning-gradient);">
                            <i class="bi bi-calendar-x"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">Leave Management</div>
                            <small class="text-muted">Handle leave requests</small>
                        </div>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (HRMSHelper::hasPermission('payroll_management') || $currentUserRole === 'admin'): ?>
                    <a href="payroll_processing.php" class="quick-action-btn">
                        <div class="quick-action-icon" style="background: var(--success-gradient);">
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
                            <div class="list-item">
                                <div class="avatar">
                                    <?= strtoupper(substr($birthday['first_name'], 0, 1) . substr($birthday['last_name'], 0, 1)) ?>
                                </div>
                                <div class="list-info">
                                    <p class="list-name"><?= htmlspecialchars($birthday['first_name'] . ' ' . $birthday['last_name']) ?></p>
                                    <p class="list-meta"><?= htmlspecialchars($birthday['department_name'] ?? 'N/A') ?></p>
                                </div>
                                <div class="list-date">
                                    <?= date('M j', strtotime($birthday['date_of_birth'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-calendar-x display-4 d-block"></i>
                            <p>No upcoming birthdays this week</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Joinings -->
            <div class="col-xl-4 col-lg-12">
                <div class="info-card">
                    <h5><i class="bi bi-person-plus-fill me-2 text-success"></i>Recent Joinings</h5>
                    
                    <?php if (!empty($recentJoinings)): ?>
                        <?php foreach ($recentJoinings as $joining): ?>
                            <div class="list-item">
                                <div class="avatar">
                                    <?= strtoupper(substr($joining['first_name'], 0, 1) . substr($joining['last_name'], 0, 1)) ?>
                                </div>
                                <div class="list-info">
                                    <p class="list-name"><?= htmlspecialchars($joining['first_name'] . ' ' . $joining['last_name']) ?></p>
                                    <p class="list-meta"><?= htmlspecialchars($joining['department_name'] ?? 'N/A') ?></p>
                                </div>
                                <div class="list-date">
                                    <?= date('M j', strtotime($joining['date_of_joining'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-person-x display-4 d-block"></i>
                            <p>No recent joinings in the last 30 days</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Portal Access Section (Admin/HR Only) -->
        <?php if ($currentUserRole === 'admin' || $currentUserRole === 'hr'): ?>
        <div class="row g-4 mt-2">
            <div class="col-12">
                <div class="quick-actions">
                    <h5 class="mb-3"><i class="bi bi-door-open-fill me-2 text-primary"></i>Management Portals</h5>
                    
                    <div class="row g-3">
                        <div class="col-md-4">
                            <a href="advanced_analytics_dashboard.php" class="quick-action-btn">
                                <div class="quick-action-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                                    <i class="bi bi-graph-up"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold">Analytics Dashboard</div>
                                    <small class="text-muted">Advanced HR analytics and insights</small>
                                </div>
                            </a>
                        </div>
                        
                        <div class="col-md-4">
                            <a href="mobile_pwa_manager.php" class="quick-action-btn">
                                <div class="quick-action-icon" style="background: linear-gradient(135deg, #43e97b, #38f9d7);">
                                    <i class="bi bi-phone"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold">Mobile PWA Manager</div>
                                    <small class="text-muted">Mobile app management</small>
                                </div>
                            </a>
                        </div>
                        
                        <div class="col-md-4">
                            <a href="employee_self_service.php" class="quick-action-btn">
                                <div class="quick-action-icon" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add loading animation to stat cards
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
    
    // Auto-refresh stats every 5 minutes
    setInterval(function() {
        // Refresh page to update statistics
        if (document.visibilityState === 'visible') {
            console.log('Auto-refreshing dashboard stats...');
            location.reload();
        }
    }, 300000); // 5 minutes
    
    // Add keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+R for refresh
        if (e.ctrlKey && e.key === 'r') {
            e.preventDefault();
            location.reload();
        }
    });
    
    console.log('✅ HRMS Dashboard loaded successfully');
});
</script>

<?php 
require_once 'hrms_footer_simple.php';
// Clean up test file
if (file_exists('test_hrms_queries.php')) {
    unlink('test_hrms_queries.php');
}

require_once 'hrms_footer_simple.php';
?>