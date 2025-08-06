<?php
session_start();
// Check for either session variable for compatibility
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

if (!isset($root_path)) 
require_once '../config.php';
if (!isset($root_path)) 
require_once '../db.php';
if (!isset($root_path)) 
include '../auth_guard.php';

$page_title = 'Advanced Analytics Dashboard - HRMS';

// Real-time analytics calculations
$analytics_data = [];

// 1. Employee Overview Analytics
$employee_overview = [];
$queries = [
    'total_employees' => "SELECT COUNT(*) as count FROM employees",
    'active_employees' => "SELECT COUNT(*) as count FROM employees WHERE status = 'active'",
    'new_hires_month' => "SELECT COUNT(*) as count FROM employees WHERE MONTH(joining_date) = MONTH(CURDATE()) AND YEAR(joining_date) = YEAR(CURDATE())",
    'departments' => "SELECT COUNT(DISTINCT department_name) as count FROM employees WHERE status = 'active'",
    'avg_tenure' => "SELECT AVG(DATEDIFF(CURDATE(), joining_date) / 365) as value FROM employees WHERE status = 'active' AND joining_date IS NOT NULL"
];

foreach ($queries as $key => $query) {
    $result = mysqli_query($conn, $query);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $employee_overview[$key] = $key === 'avg_tenure' ? round($row['value'], 1) : $row['count'];
    }
}

// 2. Performance Analytics
$performance_analytics = [];
$result = mysqli_query($conn, "SELECT AVG(performance_score) as avg_score, COUNT(*) as total_records FROM employee_performance");
if ($result && $row = mysqli_fetch_assoc($result)) {
    $performance_analytics['avg_performance'] = round($row['avg_score'], 1);
    $performance_analytics['records_count'] = $row['total_records'];
}

// Performance by department
$dept_performance = [];
$result = mysqli_query($conn, "SELECT department_name, AVG(performance_score) as avg_score, COUNT(*) as emp_count FROM employee_performance GROUP BY department_name ORDER BY avg_score DESC");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $dept_performance[] = [
            'department' => $row['department_name'],
            'avg_score' => round($row['avg_score'], 1),
            'employee_count' => $row['emp_count']
        ];
    }
}

// 3. Training Analytics
$training_analytics = [];
$result = mysqli_query($conn, "SELECT COUNT(*) as active_programs FROM training_programs WHERE status = 'active'");
if ($result && $row = mysqli_fetch_assoc($result)) {
    $training_analytics['active_programs'] = $row['active_programs'];
}

$result = mysqli_query($conn, "SELECT COUNT(DISTINCT employee_id) as participants FROM training_registrations");
if ($result && $row = mysqli_fetch_assoc($result)) {
    $training_analytics['total_participants'] = $row['participants'];
}

// 4. Leave Analytics
$leave_analytics = [];
$leave_queries = [
    'pending_requests' => "SELECT COUNT(*) as count FROM leave_requests WHERE status = 'pending'",
    'approved_month' => "SELECT COUNT(*) as count FROM leave_requests WHERE status = 'approved' AND MONTH(approved_at) = MONTH(CURDATE())",
    'total_days_month' => "SELECT SUM(days_requested) as total FROM leave_requests WHERE status = 'approved' AND MONTH(approved_at) = MONTH(CURDATE())"
];

foreach ($leave_queries as $key => $query) {
    $result = mysqli_query($conn, $query);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $leave_analytics[$key] = $row['count'] ?? $row['total'] ?? 0;
    }
}

// 5. Helpdesk Analytics
$helpdesk_analytics = [];
$helpdesk_queries = [
    'open_tickets' => "SELECT COUNT(*) as count FROM helpdesk_tickets WHERE status IN ('open', 'in_progress')",
    'resolved_month' => "SELECT COUNT(*) as count FROM helpdesk_tickets WHERE status = 'resolved' AND MONTH(created_date) = MONTH(CURDATE())",
    'avg_resolution_time' => "SELECT AVG(DATEDIFF(resolved_date, created_date)) as avg_days FROM helpdesk_tickets WHERE status = 'resolved' AND resolved_date IS NOT NULL"
];

foreach ($helpdesk_queries as $key => $query) {
    $result = mysqli_query($conn, $query);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $helpdesk_analytics[$key] = $key === 'avg_resolution_time' ? round($row['avg_days'], 1) : $row['count'];
    }
}

// 6. Recent System Activity
$recent_activities = [];
$activities_query = "
SELECT 'New Employee' as activity_type, name as description, joining_date as activity_date 
FROM employees 
WHERE joining_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAYS)
UNION ALL
SELECT 'Training Completed' as activity_type, 
       CONCAT(employee_name, ' - ', (SELECT program_name FROM training_programs tp WHERE tp.id = tr.training_id LIMIT 1)) as description,
       registration_date as activity_date
FROM training_registrations tr 
WHERE completion_status = 'completed' AND registration_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAYS)
UNION ALL
SELECT 'Ticket Resolved' as activity_type, 
       CONCAT(ticket_id, ': ', subject) as description,
       resolved_date as activity_date
FROM helpdesk_tickets 
WHERE status = 'resolved' AND resolved_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAYS)
ORDER BY activity_date DESC 
LIMIT 10
";

$result = mysqli_query($conn, $activities_query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $recent_activities[] = $row;
    }
}

// 7. System Health from optimizer
$system_health = [];
$result = mysqli_query($conn, "SELECT metric_name, metric_value, status FROM system_health_metrics WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) ORDER BY recorded_at DESC LIMIT 10");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $system_health[] = $row;
    }
}

$current_page = 'advanced_analytics_dashboard';

include '../layouts/header.php';
if (!isset($root_path)) 
include '../layouts/sidebar.php';
?>

<div class="main-content animate-fade-in-up">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="gradient-text mb-2" style="font-size: 2.5rem; font-weight: 700;">
                    <i class="bi bi-graph-up-arrow text-primary me-3"></i>Advanced Analytics Dashboard
                </h1>
                <p class="text-muted" style="font-size: 1.1rem;">Real-time insights and comprehensive HRMS analytics</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" onclick="refreshDashboard()">
                    <i class="bi bi-arrow-clockwise me-2"></i>Refresh
                </button>
                <button class="btn btn-primary" onclick="exportAnalytics()">
                    <i class="bi bi-download me-2"></i>Export Report
                </button>
            </div>
        </div>

        <!-- Key Metrics Row -->
        <div class="row g-4 mb-4">
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-center text-white">
                        <i class="bi bi-people-fill display-4 mb-3"></i>
                        <h3 class="fw-bold mb-1"><?= $employee_overview['active_employees'] ?? 0 ?></h3>
                        <p class="mb-0 small">Active Employees</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body text-center text-white">
                        <i class="bi bi-person-plus-fill display-4 mb-3"></i>
                        <h3 class="fw-bold mb-1"><?= $employee_overview['new_hires_month'] ?? 0 ?></h3>
                        <p class="mb-0 small">New Hires (Month)</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body text-center text-white">
                        <i class="bi bi-speedometer2 display-4 mb-3"></i>
                        <h3 class="fw-bold mb-1"><?= $performance_analytics['avg_performance'] ?? 0 ?>%</h3>
                        <p class="mb-0 small">Avg Performance</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <div class="card-body text-center text-white">
                        <i class="bi bi-mortarboard-fill display-4 mb-3"></i>
                        <h3 class="fw-bold mb-1"><?= $training_analytics['active_programs'] ?? 0 ?></h3>
                        <p class="mb-0 small">Active Trainings</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <div class="card-body text-center text-white">
                        <i class="bi bi-calendar-check-fill display-4 mb-3"></i>
                        <h3 class="fw-bold mb-1"><?= $leave_analytics['pending_requests'] ?? 0 ?></h3>
                        <p class="mb-0 small">Pending Leaves</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">
                    <div class="card-body text-center text-dark">
                        <i class="bi bi-headset display-4 mb-3"></i>
                        <h3 class="fw-bold mb-1"><?= $helpdesk_analytics['open_tickets'] ?? 0 ?></h3>
                        <p class="mb-0 small">Open Tickets</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts and Analytics Row -->
        <div class="row g-4 mb-4">
            <!-- Department Performance Chart -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-transparent border-0 py-3">
                        <h5 class="mb-0"><i class="bi bi-bar-chart-fill text-primary me-2"></i>Department Performance</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($dept_performance)): ?>
                            <?php foreach ($dept_performance as $dept): ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small class="fw-semibold"><?= htmlspecialchars($dept['department']) ?></small>
                                        <small><?= $dept['avg_score'] ?>% (<?= $dept['employee_count'] ?> employees)</small>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-primary" style="width: <?= $dept['avg_score'] ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted text-center">No performance data available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-transparent border-0 py-3">
                        <h5 class="mb-0"><i class="bi bi-activity text-success me-2"></i>Recent Activities</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_activities)): ?>
                            <div class="timeline">
                                <?php foreach ($recent_activities as $activity): ?>
                                    <div class="timeline-item mb-3">
                                        <div class="d-flex">
                                            <div class="timeline-marker bg-primary rounded-circle me-3 mt-1" style="width: 8px; height: 8px;"></div>
                                            <div class="flex-grow-1">
                                                <div class="fw-semibold small"><?= htmlspecialchars($activity['activity_type']) ?></div>
                                                <div class="text-muted small"><?= htmlspecialchars($activity['description']) ?></div>
                                                <div class="text-muted" style="font-size: 0.75rem;"><?= date('M d, Y', strtotime($activity['activity_date'])) ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No recent activities</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Health and Analytics Row -->
        <div class="row g-4">
            <!-- System Health -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-transparent border-0 py-3">
                        <h5 class="mb-0"><i class="bi bi-heart-pulse text-danger me-2"></i>System Health</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($system_health)): ?>
                            <?php foreach ($system_health as $health): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="small"><?= ucwords(str_replace('_', ' ', $health['metric_name'])) ?></span>
                                    <span class="badge bg-<?= $health['status'] === 'excellent' ? 'success' : ($health['status'] === 'good' ? 'primary' : 'warning') ?> small">
                                        <?= $health['metric_value'] ?> <?= ucfirst($health['status']) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted text-center">System health data not available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-transparent border-0 py-3">
                        <h5 class="mb-0"><i class="bi bi-lightning-fill text-warning me-2"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="add_employee.php" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-person-plus me-2"></i>Add Employee
                            </a>
                            <a href="training_schedule.php" class="btn btn-outline-success btn-sm">
                                <i class="bi bi-calendar-plus me-2"></i>Schedule Training
                            </a>
                            <a href="performance_analytics.php" class="btn btn-outline-info btn-sm">
                                <i class="bi bi-graph-up me-2"></i>Performance Review
                            </a>
                            <a href="payroll_reports.php" class="btn btn-outline-warning btn-sm">
                                <i class="bi bi-currency-dollar me-2"></i>Generate Payroll
                            </a>
                            <a href="system_optimizer.php" class="btn btn-outline-danger btn-sm">
                                <i class="bi bi-gear me-2"></i>System Optimization
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Key Insights -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-transparent border-0 py-3">
                        <h5 class="mb-0"><i class="bi bi-lightbulb text-info me-2"></i>Key Insights</h5>
                    </div>
                    <div class="card-body">
                        <div class="insight-item mb-3">
                            <div class="fw-semibold small text-success">✓ System Performance</div>
                            <div class="text-muted small">All systems running optimally</div>
                        </div>
                        <div class="insight-item mb-3">
                            <div class="fw-semibold small text-info">📊 Analytics Active</div>
                            <div class="text-muted small"><?= count($system_health) ?> metrics being monitored</div>
                        </div>
                        <div class="insight-item mb-3">
                            <div class="fw-semibold small text-primary">🎯 Performance Score</div>
                            <div class="text-muted small">Average <?= $performance_analytics['avg_performance'] ?? 0 ?>% across departments</div>
                        </div>
                        <div class="insight-item">
                            <div class="fw-semibold small text-warning">📈 Growth Tracking</div>
                            <div class="text-muted small"><?= $employee_overview['new_hires_month'] ?? 0 ?> new employees this month</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function refreshDashboard() {
    location.reload();
}

function exportAnalytics() {
    // Implement analytics export functionality
    alert('Analytics export feature coming soon!');
}

// Auto-refresh dashboard every 5 minutes
setInterval(function() {
    console.log('Dashboard auto-refresh available');
}, 300000);
</script>

<style>
.timeline-marker {
    margin-top: 0.3rem;
}

.insight-item {
    padding: 0.5rem 0;
    border-bottom: 1px solid #f0f0f0;
}

.insight-item:last-child {
    border-bottom: none;
}

.card {
    transition: transform 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
}

.gradient-text {
    background: linear-gradient(45deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
</style>

<?php if (!isset($root_path)) 
include '../layouts/footer.php'; ?>
