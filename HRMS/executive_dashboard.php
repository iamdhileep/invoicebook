<?php
/**
 * HRMS Executive Dashboard
 * Comprehensive overview with real-time analytics and insights
 */

$page_title = "HRMS Executive Dashboard";
require_once 'includes/hrms_config.php';

// Authentication check
if (!HRMSHelper::isLoggedIn()) {
    header('Location: ../hrms_portal.php?redirect=HRMS/executive_dashboard.php');
    exit;
}

require_once '../layouts/header.php';
require_once '../layouts/sidebar.php';

$currentUserId = HRMSHelper::getCurrentUserId();
$currentUserRole = HRMSHelper::getCurrentUserRole();

// Get comprehensive dashboard data
$dashboardData = [];

try {
    // Employee Statistics
    $result = HRMSHelper::safeQuery("
        SELECT 
            COUNT(*) as total_employees,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_employees,
            SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_employees
        FROM hr_employees
    ");
    $dashboardData['employees'] = $result->fetch_assoc();
    
    // Department Statistics
    $result = HRMSHelper::safeQuery("
        SELECT 
            d.name as department_name,
            COUNT(e.id) as employee_count
        FROM hr_departments d
        LEFT JOIN hr_employees e ON d.id = e.department_id AND e.is_active = 1
        GROUP BY d.id, d.name
        ORDER BY employee_count DESC
    ");
    $dashboardData['departments'] = [];
    while ($row = $result->fetch_assoc()) {
        $dashboardData['departments'][] = $row;
    }
    
    // Today's Attendance
    $today = date('Y-m-d');
    $result = HRMSHelper::safeQuery("
        SELECT 
            status,
            COUNT(*) as count
        FROM hr_attendance 
        WHERE date = '$today'
        GROUP BY status
    ");
    $dashboardData['attendance_today'] = [];
    while ($row = $result->fetch_assoc()) {
        $dashboardData['attendance_today'][$row['status']] = $row['count'];
    }
    
    // Current Month Attendance Trends
    $currentMonth = date('Y-m');
    $result = HRMSHelper::safeQuery("
        SELECT 
            DATE(date) as attendance_date,
            status,
            COUNT(*) as count
        FROM hr_attendance 
        WHERE date LIKE '$currentMonth%'
        GROUP BY DATE(date), status
        ORDER BY attendance_date DESC
        LIMIT 30
    ");
    $dashboardData['attendance_trends'] = [];
    while ($row = $result->fetch_assoc()) {
        $dashboardData['attendance_trends'][] = $row;
    }
    
    // Leave Statistics
    $result = HRMSHelper::safeQuery("
        SELECT 
            status,
            COUNT(*) as count
        FROM hr_leave_applications 
        WHERE YEAR(applied_at) = YEAR(CURDATE())
        GROUP BY status
    ");
    $dashboardData['leave_stats'] = [];
    while ($row = $result->fetch_assoc()) {
        $dashboardData['leave_stats'][$row['status']] = $row['count'];
    }
    
    // Performance Review Statistics
    $result = HRMSHelper::safeQuery("
        SELECT 
            status,
            COUNT(*) as count,
            AVG(overall_rating) as avg_rating
        FROM hr_performance_reviews 
        WHERE YEAR(created_at) = YEAR(CURDATE())
        GROUP BY status
    ");
    $dashboardData['performance_stats'] = [];
    while ($row = $result->fetch_assoc()) {
        $dashboardData['performance_stats'][$row['status']] = [
            'count' => $row['count'],
            'avg_rating' => round($row['avg_rating'], 2)
        ];
    }
    
    // Recent Activities
    $result = HRMSHelper::safeQuery("
        SELECT 
            'attendance' as activity_type,
            CONCAT(e.first_name, ' ', e.last_name) as employee_name,
            CONCAT('Clocked ', IF(a.clock_out_time IS NULL, 'in', 'out')) as activity,
            COALESCE(a.clock_out_time, a.clock_in_time) as activity_time
        FROM hr_attendance a
        LEFT JOIN hr_employees e ON a.employee_id = e.id
        WHERE DATE(a.date) = CURDATE()
        
        UNION ALL
        
        SELECT 
            'leave' as activity_type,
            CONCAT(e.first_name, ' ', e.last_name) as employee_name,
            CONCAT('Applied for ', lt.name) as activity,
            la.applied_at as activity_time
        FROM hr_leave_applications la
        LEFT JOIN hr_employees e ON la.employee_id = e.id
        LEFT JOIN hr_leave_types lt ON la.leave_type_id = lt.id
        WHERE DATE(la.applied_at) = CURDATE()
        
        ORDER BY activity_time DESC
        LIMIT 10
    ");
    $dashboardData['recent_activities'] = [];
    while ($row = $result->fetch_assoc()) {
        $dashboardData['recent_activities'][] = $row;
    }
    
    // Top Performers
    $result = HRMSHelper::safeQuery("
        SELECT 
            e.first_name,
            e.last_name,
            e.employee_id,
            AVG(pr.overall_rating) as avg_rating,
            COUNT(pr.id) as review_count
        FROM hr_employees e
        LEFT JOIN hr_performance_reviews pr ON e.id = pr.employee_id
        WHERE pr.status = 'completed' AND pr.overall_rating IS NOT NULL
        GROUP BY e.id
        HAVING review_count > 0
        ORDER BY avg_rating DESC
        LIMIT 5
    ");
    $dashboardData['top_performers'] = [];
    while ($row = $result->fetch_assoc()) {
        $dashboardData['top_performers'][] = $row;
    }
    
    // Upcoming Birthdays
    $result = HRMSHelper::safeQuery("
        SELECT 
            first_name,
            last_name,
            date_of_birth,
            DATEDIFF(
                DATE_ADD(
                    MAKEDATE(YEAR(CURDATE()), 1),
                    INTERVAL DAYOFYEAR(date_of_birth) - 1 DAY
                ),
                CURDATE()
            ) as days_until_birthday
        FROM hr_employees 
        WHERE is_active = 1 AND date_of_birth IS NOT NULL
        HAVING days_until_birthday BETWEEN 0 AND 30
        ORDER BY days_until_birthday ASC
        LIMIT 5
    ");
    $dashboardData['upcoming_birthdays'] = [];
    while ($row = $result->fetch_assoc()) {
        $dashboardData['upcoming_birthdays'][] = $row;
    }
    
} catch (Exception $e) {
    error_log("Dashboard data fetch error: " . $e->getMessage());
}

// Calculate key metrics
$totalEmployees = $dashboardData['employees']['total_employees'] ?? 0;
$presentToday = $dashboardData['attendance_today']['present'] ?? 0;
$attendanceRate = $totalEmployees > 0 ? round(($presentToday / $totalEmployees) * 100, 1) : 0;

$totalLeaveApps = array_sum($dashboardData['leave_stats'] ?? []);
$pendingLeaves = $dashboardData['leave_stats']['pending'] ?? 0;

$totalReviews = array_sum(array_column($dashboardData['performance_stats'] ?? [], 'count'));
$completedReviews = $dashboardData['performance_stats']['completed']['count'] ?? 0;
$avgPerformanceRating = $dashboardData['performance_stats']['completed']['avg_rating'] ?? 0;
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h2 mb-1">
                            <i class="fas fa-tachometer-alt text-primary me-2"></i>
                            HRMS Executive Dashboard
                        </h1>
                        <p class="text-muted mb-0">Comprehensive HR analytics and real-time insights</p>
                    </div>
                    <div class="d-flex gap-2">
                        <div class="dashboard-time-display">
                            <div class="time" id="currentTime"><?= date('g:i:s A') ?></div>
                            <div class="date"><?= date('l, M j, Y') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Key Metrics Row -->
        <div class="row mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6 mb-3">
                <div class="card border-0 shadow-sm metric-card metric-primary">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h3 class="fw-bold text-primary mb-1"><?= $totalEmployees ?></h3>
                                <p class="text-muted mb-0 small">Total Employees</p>
                                <small class="text-success">
                                    <i class="fas fa-arrow-up me-1"></i><?= $dashboardData['employees']['active_employees'] ?> Active
                                </small>
                            </div>
                            <div class="metric-icon bg-primary">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-6 col-md-6 mb-3">
                <div class="card border-0 shadow-sm metric-card metric-success">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h3 class="fw-bold text-success mb-1"><?= $attendanceRate ?>%</h3>
                                <p class="text-muted mb-0 small">Attendance Rate Today</p>
                                <small class="text-info">
                                    <i class="fas fa-user-check me-1"></i><?= $presentToday ?> Present
                                </small>
                            </div>
                            <div class="metric-icon bg-success">
                                <i class="fas fa-chart-line"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-6 col-md-6 mb-3">
                <div class="card border-0 shadow-sm metric-card metric-warning">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h3 class="fw-bold text-warning mb-1"><?= $pendingLeaves ?></h3>
                                <p class="text-muted mb-0 small">Pending Leave Requests</p>
                                <small class="text-secondary">
                                    <i class="fas fa-calendar me-1"></i><?= $totalLeaveApps ?> Total This Year
                                </small>
                            </div>
                            <div class="metric-icon bg-warning">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-6 col-md-6 mb-3">
                <div class="card border-0 shadow-sm metric-card metric-info">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h3 class="fw-bold text-info mb-1"><?= $avgPerformanceRating ?></h3>
                                <p class="text-muted mb-0 small">Avg Performance Rating</p>
                                <small class="text-success">
                                    <i class="fas fa-star me-1"></i><?= $completedReviews ?> Reviews Completed
                                </small>
                            </div>
                            <div class="metric-icon bg-info">
                                <i class="fas fa-trophy"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts and Analytics Row -->
        <div class="row mb-4">
            <!-- Department Distribution -->
            <div class="col-xl-6 col-lg-6 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-building text-primary me-2"></i>
                            Department Distribution
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="departmentChart" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- Attendance Trends -->
            <div class="col-xl-6 col-lg-6 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-chart-area text-success me-2"></i>
                            Attendance Trends (Last 7 Days)
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="attendanceChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Information Panels Row -->
        <div class="row">
            <!-- Recent Activities -->
            <div class="col-xl-4 col-lg-6 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-bell text-warning me-2"></i>
                            Recent Activities
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="activity-list">
                            <?php foreach (array_slice($dashboardData['recent_activities'], 0, 6) as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon <?= $activity['activity_type'] === 'attendance' ? 'bg-success' : 'bg-info' ?>">
                                        <i class="fas <?= $activity['activity_type'] === 'attendance' ? 'fa-clock' : 'fa-calendar' ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-text">
                                            <strong><?= htmlspecialchars($activity['employee_name']) ?></strong>
                                            <?= htmlspecialchars($activity['activity']) ?>
                                        </div>
                                        <small class="text-muted">
                                            <?= date('g:i A', strtotime($activity['activity_time'])) ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Performers -->
            <div class="col-xl-4 col-lg-6 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-star text-warning me-2"></i>
                            Top Performers
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($dashboardData['top_performers'] as $index => $performer): ?>
                            <div class="performer-item <?= $index < count($dashboardData['top_performers']) - 1 ? 'border-bottom' : '' ?> pb-3 mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="performer-rank">
                                        <span class="rank-number"><?= $index + 1 ?></span>
                                    </div>
                                    <div class="performer-info flex-grow-1 ms-3">
                                        <div class="fw-medium">
                                            <?= htmlspecialchars($performer['first_name'] . ' ' . $performer['last_name']) ?>
                                        </div>
                                        <small class="text-muted"><?= htmlspecialchars($performer['employee_id']) ?></small>
                                    </div>
                                    <div class="performer-rating">
                                        <div class="rating-stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?= $i <= $performer['avg_rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <small class="text-muted"><?= number_format($performer['avg_rating'], 1) ?>/5</small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Upcoming Birthdays -->
            <div class="col-xl-4 col-lg-6 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-birthday-cake text-primary me-2"></i>
                            Upcoming Birthdays
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($dashboardData['upcoming_birthdays'])): ?>
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-calendar-day fs-2 mb-2"></i>
                                <p class="mb-0">No upcoming birthdays in the next 30 days</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($dashboardData['upcoming_birthdays'] as $birthday): ?>
                                <div class="birthday-item d-flex align-items-center mb-3">
                                    <div class="birthday-avatar me-3">
                                        <?= strtoupper(substr($birthday['first_name'], 0, 1) . substr($birthday['last_name'], 0, 1)) ?>
                                    </div>
                                    <div class="birthday-info flex-grow-1">
                                        <div class="fw-medium">
                                            <?= htmlspecialchars($birthday['first_name'] . ' ' . $birthday['last_name']) ?>
                                        </div>
                                        <small class="text-muted">
                                            <?php if ($birthday['days_until_birthday'] == 0): ?>
                                                🎉 Today!
                                            <?php elseif ($birthday['days_until_birthday'] == 1): ?>
                                                Tomorrow
                                            <?php else: ?>
                                                In <?= $birthday['days_until_birthday'] ?> days
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <div class="birthday-date">
                                        <small class="text-primary">
                                            <?= date('M j', strtotime($birthday['date_of_birth'])) ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions Row -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-rocket text-primary me-2"></i>
                            Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 col-sm-6 mb-3">
                                <a href="advanced_analytics.php" class="btn btn-outline-primary btn-lg w-100 d-flex align-items-center">
                                    <i class="fas fa-chart-bar me-2"></i>
                                    <div class="text-start">
                                        <div class="fw-medium">Analytics</div>
                                        <small>View detailed reports</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <a href="employee_directory.php" class="btn btn-outline-success btn-lg w-100 d-flex align-items-center">
                                    <i class="fas fa-users me-2"></i>
                                    <div class="text-start">
                                        <div class="fw-medium">Employees</div>
                                        <small>Manage employee data</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <a href="advanced_attendance_management.php" class="btn btn-outline-warning btn-lg w-100 d-flex align-items-center">
                                    <i class="fas fa-clock me-2"></i>
                                    <div class="text-start">
                                        <div class="fw-medium">Attendance</div>
                                        <small>Track time & presence</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <a href="advanced_leave_management.php" class="btn btn-outline-info btn-lg w-100 d-flex align-items-center">
                                    <i class="fas fa-calendar-alt me-2"></i>
                                    <div class="text-start">
                                        <div class="fw-medium">Leave</div>
                                        <small>Manage leave requests</small>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.main-content {
    margin-left: 250px;
    padding: 2rem;
    min-height: 100vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        padding: 1rem;
    }
}

.dashboard-time-display {
    text-align: right;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    padding: 1rem 1.5rem;
    border-radius: 15px;
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.dashboard-time-display .time {
    font-size: 1.5rem;
    font-weight: bold;
    font-family: 'Courier New', monospace;
}

.dashboard-time-display .date {
    font-size: 0.875rem;
    opacity: 0.9;
}

.metric-card {
    transition: all 0.3s ease;
    border-radius: 15px;
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.95);
    overflow: hidden;
    position: relative;
}

.metric-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
}

.metric-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
}

.metric-primary::before { background: #007bff; }
.metric-success::before { background: #28a745; }
.metric-warning::before { background: #ffc107; }
.metric-info::before { background: #17a2b8; }

.metric-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
}

.card {
    border-radius: 15px;
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.95);
    border: none;
}

.activity-list {
    max-height: 400px;
    overflow-y: auto;
}

.activity-item {
    display: flex;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid #f1f3f4;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.875rem;
}

.activity-content {
    margin-left: 1rem;
    flex-grow: 1;
}

.performer-rank {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(45deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
}

.rating-stars {
    font-size: 0.875rem;
}

.birthday-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(45deg, #ff6b6b, #feca57);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 0.875rem;
}

.btn-lg {
    padding: 0.75rem 1rem;
    min-height: 60px;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Update current time every second
function updateCurrentTime() {
    const now = new Date();
    const timeStr = now.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    });
    
    const timeElement = document.getElementById('currentTime');
    if (timeElement) {
        timeElement.textContent = timeStr;
    }
}

// Initialize time updates
setInterval(updateCurrentTime, 1000);

// Initialize Charts
document.addEventListener('DOMContentLoaded', function() {
    // Department Distribution Chart
    const departmentData = <?= json_encode($dashboardData['departments']) ?>;
    const deptCtx = document.getElementById('departmentChart').getContext('2d');
    
    new Chart(deptCtx, {
        type: 'doughnut',
        data: {
            labels: departmentData.map(d => d.department_name),
            datasets: [{
                data: departmentData.map(d => d.employee_count),
                backgroundColor: [
                    '#667eea', '#764ba2', '#f093fb', '#f5576c',
                    '#4facfe', '#00f2fe', '#43e97b', '#38f9d7'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // Attendance Trends Chart
    const attendanceData = <?= json_encode($dashboardData['attendance_trends']) ?>;
    
    // Process data for last 7 days
    const last7Days = [];
    const presentData = [];
    const absentData = [];
    
    for (let i = 6; i >= 0; i--) {
        const date = new Date();
        date.setDate(date.getDate() - i);
        const dateStr = date.toISOString().split('T')[0];
        
        last7Days.push(date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' }));
        
        const dayData = attendanceData.filter(a => a.attendance_date === dateStr);
        const presentCount = dayData.find(d => d.status === 'present')?.count || 0;
        const absentCount = dayData.find(d => d.status === 'absent')?.count || 0;
        
        presentData.push(presentCount);
        absentData.push(absentCount);
    }
    
    const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
    
    new Chart(attendanceCtx, {
        type: 'line',
        data: {
            labels: last7Days,
            datasets: [{
                label: 'Present',
                data: presentData,
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Absent',
                data: absentData,
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
});
</script>

<?php require_once '../layouts/footer.php'; ?>
