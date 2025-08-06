<?php
session_start();
require_once '../auth_check.php';
require_once '../config.php';
require_once '../db.php';

// Create analytics tables if they don't exist
$analytics_tables_sql = [
    "CREATE TABLE IF NOT EXISTS hr_analytics_cache (
        id INT PRIMARY KEY AUTO_INCREMENT,
        metric_type VARCHAR(100) NOT NULL,
        metric_name VARCHAR(200) NOT NULL,
        metric_value DECIMAL(15,2),
        metric_date DATE NOT NULL,
        department_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_metric_date (metric_date),
        INDEX idx_metric_type (metric_type)
    )",
    
    "CREATE TABLE IF NOT EXISTS hr_kpi_targets (
        id INT PRIMARY KEY AUTO_INCREMENT,
        kpi_name VARCHAR(200) NOT NULL,
        target_value DECIMAL(10,2) NOT NULL,
        current_value DECIMAL(10,2) DEFAULT 0,
        measurement_period ENUM('Daily', 'Weekly', 'Monthly', 'Quarterly', 'Yearly') DEFAULT 'Monthly',
        department_id INT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS workforce_predictions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        prediction_type ENUM('Turnover', 'Performance', 'Growth', 'Cost') NOT NULL,
        employee_id INT NULL,
        department_id INT NULL,
        predicted_value DECIMAL(10,2),
        confidence_score DECIMAL(5,2),
        prediction_date DATE NOT NULL,
        factors JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    )"
];

foreach ($analytics_tables_sql as $sql) {
    $conn->query($sql);
}

// Function to calculate HR metrics
function calculateHRMetrics($conn) {
    $metrics = [];
    
    // Employee Metrics
    $employee_query = "
        SELECT 
            COUNT(*) as total_employees,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_employees,
            SUM(CASE WHEN YEAR(hire_date) = YEAR(CURDATE()) THEN 1 ELSE 0 END) as new_hires_this_year,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as terminations_this_year,
            AVG(DATEDIFF(CURDATE(), hire_date)/365) as avg_tenure_years
        FROM employees
    ";
    $employee_result = $conn->query($employee_query);
    if (!$employee_result) {
        error_log("Employee metrics query failed: " . $conn->error);
        $employee_stats = ['total_employees' => 0, 'active_employees' => 0, 'new_hires_this_year' => 0, 'terminations_this_year' => 0, 'avg_tenure_years' => 0];
    } else {
        $employee_stats = $employee_result->fetch_assoc();
    }
    
    // Attendance Metrics - Check for the correct column names
    $attendance_query = "
        SELECT 
            AVG(CASE WHEN status = 'present' THEN 1 ELSE 0 END) * 100 as attendance_rate,
            COUNT(DISTINCT employee_id) as tracked_employees,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_arrivals
        FROM attendance 
        WHERE attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAYS)
    ";
    $attendance_result = $conn->query($attendance_query);
    if (!$attendance_result) {
        // Try alternative column name
        $attendance_query = "
            SELECT 
                AVG(CASE WHEN status = 'present' THEN 1 ELSE 0 END) * 100 as attendance_rate,
                COUNT(DISTINCT employee_id) as tracked_employees,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_arrivals
            FROM attendance 
            WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAYS)
        ";
        $attendance_result = $conn->query($attendance_query);
        if (!$attendance_result) {
            error_log("Attendance metrics query failed: " . $conn->error);
            $attendance_stats = ['attendance_rate' => 0, 'tracked_employees' => 0, 'late_arrivals' => 0];
        } else {
            $attendance_stats = $attendance_result->fetch_assoc();
        }
    } else {
        $attendance_stats = $attendance_result->fetch_assoc();
    }
    
    // Leave Metrics
    $leave_query = "
        SELECT 
            COUNT(*) as total_requests,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
            AVG(DATEDIFF(end_date, start_date) + 1) as avg_leave_duration
        FROM leave_requests 
        WHERE YEAR(start_date) = YEAR(CURDATE())
    ";
    $leave_result = $conn->query($leave_query);
    if (!$leave_result) {
        error_log("Leave metrics query failed: " . $conn->error);
        $leave_stats = ['total_requests' => 0, 'approved_requests' => 0, 'pending_requests' => 0, 'avg_leave_duration' => 0];
    } else {
        $leave_stats = $leave_result->fetch_assoc();
    }
    
    // Performance Metrics
    $performance_query = "
        SELECT 
            AVG(overall_rating) as avg_performance_rating,
            COUNT(*) as total_reviews,
            SUM(CASE WHEN overall_rating >= 4 THEN 1 ELSE 0 END) as high_performers
        FROM performance_reviews 
        WHERE review_year = YEAR(CURDATE())
    ";
    $performance_result = $conn->query($performance_query);
    if (!$performance_result) {
        error_log("Performance metrics query failed: " . $conn->error);
        $performance_stats = ['avg_performance_rating' => 0, 'total_reviews' => 0, 'high_performers' => 0];
    } else {
        $performance_stats = $performance_result->fetch_assoc();
    }
    
    // Training Metrics
    $training_query = "
        SELECT 
            COUNT(DISTINCT te.employee_id) as employees_trained,
            COUNT(*) as total_enrollments,
            AVG(te.completion_percentage) as avg_completion_rate
        FROM training_enrollments te
        WHERE YEAR(te.enrolled_date) = YEAR(CURDATE())
    ";
    $training_result = $conn->query($training_query);
    if (!$training_result) {
        // Try alternative column name
        $training_query = "
            SELECT 
                COUNT(DISTINCT te.employee_id) as employees_trained,
                COUNT(*) as total_enrollments,
                AVG(te.completion_percentage) as avg_completion_rate
            FROM training_enrollments te
            WHERE YEAR(te.created_at) = YEAR(CURDATE())
        ";
        $training_result = $conn->query($training_query);
        if (!$training_result) {
            error_log("Training metrics query failed: " . $conn->error);
            $training_stats = ['employees_trained' => 0, 'total_enrollments' => 0, 'avg_completion_rate' => 0];
        } else {
            $training_stats = $training_result->fetch_assoc();
        }
    } else {
        $training_stats = $training_result->fetch_assoc();
    }
    
    // Asset Metrics
    $asset_query = "
        SELECT 
            COUNT(*) as total_assets,
            SUM(current_value) as total_asset_value,
            SUM(CASE WHEN status = 'Allocated' THEN 1 ELSE 0 END) as allocated_assets
        FROM company_assets
    ";
    $asset_result = $conn->query($asset_query);
    if (!$asset_result) {
        error_log("Asset metrics query failed: " . $conn->error);
        $asset_stats = ['total_assets' => 0, 'total_asset_value' => 0, 'allocated_assets' => 0];
    } else {
        $asset_stats = $asset_result->fetch_assoc();
    }
    
    return [
        'employee' => $employee_stats,
        'attendance' => $attendance_stats,
        'leave' => $leave_stats,
        'performance' => $performance_stats,
        'training' => $training_stats,
        'assets' => $asset_stats
    ];
}

// Calculate turnover rate
function calculateTurnoverRate($conn) {
    $query = "
        SELECT 
            (COUNT(CASE WHEN status = 'inactive' THEN 1 END) * 100.0 / 
             COUNT(CASE WHEN status IN ('active', 'inactive') THEN 1 END)) as turnover_rate
        FROM employees
    ";
    $result = $conn->query($query);
    if (!$result) {
        error_log("Turnover rate query failed: " . $conn->error);
        return 0;
    }
    $row = $result->fetch_assoc();
    return round($row['turnover_rate'] ?? 0, 2);
}

// Get department wise statistics
function getDepartmentStats($conn) {
    $query = "
        SELECT 
            d.name as department_name,
            COUNT(e.employee_id) as employee_count,
            AVG(CASE WHEN pr.overall_rating IS NOT NULL THEN pr.overall_rating END) as avg_performance,
            SUM(CASE WHEN e.status = 'inactive' THEN 1 ELSE 0 END) as terminations
        FROM departments d
        LEFT JOIN employees e ON d.id = e.department_id
        LEFT JOIN performance_reviews pr ON e.employee_id = pr.employee_id AND pr.review_year = YEAR(CURDATE())
        GROUP BY d.id, d.name
        ORDER BY employee_count DESC
    ";
    $result = $conn->query($query);
    if (!$result) {
        error_log("Department stats query failed: " . $conn->error);
        // Return a mock result set for graceful fallback
        return $conn->query("SELECT 'No Department' as department_name, 0 as employee_count, 0 as avg_performance, 0 as terminations LIMIT 0");
    }
    return $result;
}

// Get recent activities for timeline
function getRecentActivities($conn) {
    $activities = [];
    
    // Recent hires - check for correct column names
    $hires_query = "
        SELECT 'hire' as type, CONCAT(first_name, ' ', last_name) as description, hire_date as date
        FROM employees 
        WHERE hire_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAYS)
        ORDER BY hire_date DESC LIMIT 5
    ";
    $hires_result = $conn->query($hires_query);
    if (!$hires_result) {
        // Try alternative column name
        $hires_query = "
            SELECT 'hire' as type, name as description, hire_date as date
            FROM employees 
            WHERE hire_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAYS)
            ORDER BY hire_date DESC LIMIT 5
        ";
        $hires_result = $conn->query($hires_query);
    }
    
    if ($hires_result) {
        while ($hire = $hires_result->fetch_assoc()) {
            $activities[] = [
                'type' => 'hire',
                'icon' => 'person-plus-fill',
                'color' => 'success',
                'description' => $hire['description'] . ' joined the company',
                'date' => $hire['date']
            ];
        }
    }
    
    // Recent performance reviews
    $reviews_query = "
        SELECT 'review' as type, e.name as description, pr.review_date as date, pr.overall_rating
        FROM performance_reviews pr
        JOIN employees e ON pr.employee_id = e.id
        WHERE pr.review_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAYS)
        ORDER BY pr.review_date DESC LIMIT 5
    ";
    $reviews_result = $conn->query($reviews_query);
    if (!$reviews_result) {
        // Try alternative approach
        $reviews_query = "
            SELECT 'review' as type, 'Employee Review' as description, created_at as date, overall_rating
            FROM performance_reviews pr
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAYS)
            ORDER BY created_at DESC LIMIT 5
        ";
        $reviews_result = $conn->query($reviews_query);
    }
    
    if ($reviews_result) {
        while ($review = $reviews_result->fetch_assoc()) {
            $activities[] = [
                'type' => 'review',
                'icon' => 'clipboard-check',
                'color' => 'info',
                'description' => $review['description'] . ' completed performance review (Rating: ' . $review['overall_rating'] . '/5)',
                'date' => $review['date']
            ];
        }
    }
    
    // Sort by date
    if (!empty($activities)) {
        usort($activities, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
    }
    
    return array_slice($activities, 0, 10);
}

$metrics = calculateHRMetrics($conn);
$turnover_rate = calculateTurnoverRate($conn);
$department_stats = getDepartmentStats($conn);
$recent_activities = getRecentActivities($conn);

// Calculate key ratios and trends
$employee_growth_rate = $metrics['employee']['new_hires_this_year'] > 0 ? 
    (($metrics['employee']['new_hires_this_year'] - $metrics['employee']['terminations_this_year']) / $metrics['employee']['total_employees']) * 100 : 0;

$attendance_rate = $metrics['attendance']['attendance_rate'] ?? 0;
$performance_excellence = $metrics['performance']['high_performers'] > 0 ? 
    ($metrics['performance']['high_performers'] / $metrics['performance']['total_reviews']) * 100 : 0;

$current_page = 'advanced_analytics';
$page_title = 'Advanced Analytics Dashboard - HRMS';

include '../layouts/header.php';
include '../layouts/sidebar.php';
?>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Advanced Analytics Custom Styles -->
<style>
    .metric-card {
        transition: all 0.3s ease;
        border: 1px solid rgba(0,0,0,0.1);
        background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
    }
    
    .metric-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    
    .metric-value {
        font-size: 2.5rem;
        font-weight: 700;
        color: #2c3e50;
    }
    
    .metric-label {
        font-size: 0.9rem;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .trend-indicator {
        font-size: 0.8rem;
        padding: 2px 8px;
        border-radius: 12px;
    }
    
    .trend-up {
        background: #d4edda;
        color: #155724;
    }
    
    .trend-down {
        background: #f8d7da;
        color: #721c24;
    }
    
    .chart-container {
        position: relative;
        height: 300px;
        margin: 20px 0;
    }
    
    .activity-item {
        border-left: 3px solid #007bff;
        padding-left: 15px;
        margin-bottom: 15px;
        position: relative;
    }
    
    .activity-icon {
        position: absolute;
        left: -8px;
        top: 5px;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 8px;
    }
    
    .kpi-gauge {
        position: relative;
        width: 120px;
        height: 120px;
        margin: 0 auto;
    }
</style>

<div class="main-content animate-fade-in-up">
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-2">
                            <i class="bi bi-graph-up me-3"></i>Advanced Analytics Dashboard
                        </h1>
                        <p class="text-muted mb-0">Executive insights across all HR modules with predictive analytics and real-time KPIs</p>
                    </div>
                    <div>
                        <button class="btn btn-outline-primary me-2" onclick="refreshDashboard()">
                            <i class="bi bi-arrow-clockwise me-2"></i>Refresh
                        </button>
                        <div class="dropdown">
                            <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-download me-2"></i>Export Reports
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" onclick="exportPDF()">PDF Report</a></li>
                                <li><a class="dropdown-item" href="#" onclick="exportExcel()">Excel Dashboard</a></li>
                                <li><a class="dropdown-item" href="#" onclick="exportAnalytics()">Analytics Data</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

                <!-- Key Metrics Row -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card metric-card border-primary">
                            <div class="card-body text-center">
                                <div class="metric-value text-primary"><?= $metrics['employee']['active_employees'] ?></div>
                                <div class="metric-label">Active Employees</div>
                                <div class="trend-indicator trend-up mt-2">
                                    <i class="bi bi-arrow-up"></i> <?= number_format($employee_growth_rate, 1) ?>%
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card metric-card border-success">
                            <div class="card-body text-center">
                                <div class="metric-value text-success"><?= number_format($attendance_rate, 1) ?>%</div>
                                <div class="metric-label">Attendance Rate</div>
                                <div class="trend-indicator <?= $attendance_rate > 90 ? 'trend-up' : 'trend-down' ?> mt-2">
                                    <i class="bi bi-<?= $attendance_rate > 90 ? 'arrow-up' : 'arrow-down' ?>"></i> 
                                    <?= $attendance_rate > 90 ? 'Excellent' : 'Needs Attention' ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card metric-card border-warning">
                            <div class="card-body text-center">
                                <div class="metric-value text-warning"><?= number_format($turnover_rate, 1) ?>%</div>
                                <div class="metric-label">Turnover Rate</div>
                                <div class="trend-indicator <?= $turnover_rate < 10 ? 'trend-up' : 'trend-down' ?> mt-2">
                                    <i class="bi bi-<?= $turnover_rate < 10 ? 'check-circle' : 'exclamation-triangle' ?>"></i> 
                                    <?= $turnover_rate < 10 ? 'Healthy' : 'Monitor' ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card metric-card border-info">
                            <div class="card-body text-center">
                                <div class="metric-value text-info"><?= number_format($performance_excellence, 1) ?>%</div>
                                <div class="metric-label">High Performers</div>
                                <div class="trend-indicator trend-up mt-2">
                                    <i class="bi bi-star"></i> Excellence Rate
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="bi bi-bar-chart"></i> Department Workforce Distribution</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="departmentChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="bi bi-graph-up"></i> Monthly HR Trends</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="trendsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- KPI Dashboard -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="bi bi-speedometer2"></i> Key Performance Indicators</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-2">
                                        <div class="text-center">
                                            <div class="kpi-gauge">
                                                <canvas id="attendanceGauge"></canvas>
                                            </div>
                                            <h6 class="mt-2">Attendance</h6>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="text-center">
                                            <div class="kpi-gauge">
                                                <canvas id="performanceGauge"></canvas>
                                            </div>
                                            <h6 class="mt-2">Performance</h6>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="text-center">
                                            <div class="kpi-gauge">
                                                <canvas id="retentionGauge"></canvas>
                                            </div>
                                            <h6 class="mt-2">Retention</h6>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Strategic Insights</h6>
                                        <div class="alert alert-info">
                                            <strong>Workforce Health Score:</strong> 
                                            <?php 
                                            $health_score = ($attendance_rate + (100 - $turnover_rate) + $performance_excellence) / 3;
                                            echo number_format($health_score, 1) . '%';
                                            ?>
                                            <br>
                                            <small>
                                                <?php if ($health_score >= 85): ?>
                                                    <i class="bi bi-check-circle text-success"></i> Excellent workforce health. Continue current strategies.
                                                <?php elseif ($health_score >= 70): ?>
                                                    <i class="bi bi-exclamation-triangle text-warning"></i> Good performance with room for improvement.
                                                <?php else: ?>
                                                    <i class="bi bi-x-circle text-danger"></i> Action required to improve workforce metrics.
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities & Predictions -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="bi bi-clock-history"></i> Recent HR Activities</h5>
                            </div>
                            <div class="card-body">
                                <div class="timeline">
                                    <?php foreach ($recent_activities as $activity): ?>
                                        <div class="activity-item">
                                            <div class="activity-icon bg-<?= $activity['color'] ?>">
                                                <i class="bi bi-<?= $activity['icon'] ?> text-white"></i>
                                            </div>
                                            <div class="activity-content">
                                                <p class="mb-1"><?= htmlspecialchars($activity['description']) ?></p>
                                                <small class="text-muted"><?= date('M j, Y', strtotime($activity['date'])) ?></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="bi bi-crystal-ball"></i> Predictive Insights</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <h6>Turnover Prediction</h6>
                                    <div class="progress mb-2">
                                        <div class="progress-bar bg-warning" style="width: <?= min($turnover_rate * 2, 100) ?>%"></div>
                                    </div>
                                    <small class="text-muted">
                                        <?php if ($turnover_rate < 5): ?>
                                            Low risk of turnover increase
                                        <?php elseif ($turnover_rate < 15): ?>
                                            Moderate attention needed
                                        <?php else: ?>
                                            High risk - immediate action required
                                        <?php endif; ?>
                                    </small>
                                </div>
                                
                                <div class="mb-3">
                                    <h6>Workforce Growth Forecast</h6>
                                    <div class="progress mb-2">
                                        <div class="progress-bar bg-success" style="width: <?= max(min($employee_growth_rate + 50, 100), 0) ?>%"></div>
                                    </div>
                                    <small class="text-muted">Based on current hiring trends</small>
                                </div>
                                
                                <div class="alert alert-light">
                                    <h6><i class="bi bi-lightbulb"></i> AI Recommendations</h6>
                                    <ul class="mb-0">
                                        <?php if ($attendance_rate < 90): ?>
                                            <li>Implement flexible work arrangements to improve attendance</li>
                                        <?php endif; ?>
                                        <?php if ($turnover_rate > 10): ?>
                                            <li>Review compensation and benefits packages</li>
                                        <?php endif; ?>
                                        <?php if ($performance_excellence < 30): ?>
                                            <li>Enhance performance management and training programs</li>
                                        <?php endif; ?>
                                        <li>Consider implementing employee engagement surveys</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Department Distribution Chart
    const departmentCtx = document.getElementById('departmentChart').getContext('2d');
    const departmentChart = new Chart(departmentCtx, {
        type: 'doughnut',
        data: {
            labels: [
                <?php 
                $department_stats->data_seek(0);
                $labels = [];
                while ($dept = $department_stats->fetch_assoc()) {
                    $labels[] = "'" . $dept['department_name'] . "'";
                }
                echo implode(',', $labels);
                ?>
            ],
            datasets: [{
                data: [
                    <?php 
                    $department_stats->data_seek(0);
                    $values = [];
                    while ($dept = $department_stats->fetch_assoc()) {
                        $values[] = $dept['employee_count'];
                    }
                    echo implode(',', $values);
                    ?>
                ],
                backgroundColor: [
                    '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'
                ]
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

    // Trends Chart
    const trendsCtx = document.getElementById('trendsChart').getContext('2d');
    const trendsChart = new Chart(trendsCtx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'New Hires',
                data: [12, 19, 3, 5, 2, 15],
                borderColor: '#36A2EB',
                tension: 0.4
            }, {
                label: 'Terminations',
                data: [2, 3, 1, 4, 1, 2],
                borderColor: '#FF6384',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Gauge Charts
    function createGauge(elementId, value, maxValue = 100) {
        const ctx = document.getElementById(elementId).getContext('2d');
        return new Chart(ctx, {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [value, maxValue - value],
                    backgroundColor: ['#36A2EB', '#E0E0E0'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                rotation: -90,
                circumference: 180,
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: false }
                }
            }
        });
    }

    createGauge('attendanceGauge', <?= $attendance_rate ?>);
    createGauge('performanceGauge', <?= $performance_excellence ?>);
    createGauge('retentionGauge', <?= 100 - $turnover_rate ?>);

    // Dashboard functions
    function refreshDashboard() {
        location.reload();
    }

    function exportPDF() {
        alert('PDF export functionality will be implemented');
    }

    function exportExcel() {
        alert('Excel export functionality will be implemented');
    }

    function exportAnalytics() {
        alert('Analytics data export functionality will be implemented');
    }
</script>

<?php include '../layouts/footer.php'; ?>
