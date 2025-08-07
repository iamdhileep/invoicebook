<?php
session_start();
// Check for either session variable for compatibility
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Include config and database

include '../config.php';
if (!isset($root_path)) 
include '../db.php';

$page_title = 'Performance Analytics - HRMS';

// Database-driven performance analytics data
$performance_metrics = [];

// Calculate overall performance metrics from database
$queries = [
    'overall_performance' => "SELECT AVG(performance_score) as value FROM employee_performance",
    'goal_completion_rate' => "SELECT AVG(goal_completion_rate) as value FROM employee_performance",
    'productivity_score' => "SELECT AVG(productivity_score) as value FROM employee_performance",
    'attendance_rate' => "SELECT AVG(attendance_rate) as value FROM employee_performance",
    'training_completion' => "SELECT AVG(training_completion_rate) as value FROM employee_performance"
];

foreach ($queries as $metric => $query) {
    $result = mysqli_query($conn, $query);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $performance_metrics[$metric] = $row['value'] ? round($row['value'], 1) : 0;
    } else {
        $performance_metrics[$metric] = 0;
    }
}

// Calculate employee satisfaction from exit interviews
$result = mysqli_query($conn, "SELECT AVG(overall_rating) as value FROM exit_interviews WHERE overall_rating IS NOT NULL");
if ($result && $row = mysqli_fetch_assoc($result)) {
    $performance_metrics['employee_satisfaction'] = $row['value'] ? round($row['value'] * 20, 1) : 75; // Convert 5-point to 100-point scale
} else {
    $performance_metrics['employee_satisfaction'] = 75;
}

// Database-driven department performance data
$department_performance = [];
$query = "SELECT 
    department_name as department,
    AVG(performance_score) as score,
    COUNT(*) as employees,
    AVG(CASE WHEN performance_trend = 'up' THEN 1 WHEN performance_trend = 'down' THEN -1 ELSE 0 END) as trend_indicator
FROM employee_performance 
GROUP BY department_name 
ORDER BY score DESC";

$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $trend = 'stable';
        if ($row['trend_indicator'] > 0.3) $trend = 'up';
        elseif ($row['trend_indicator'] < -0.3) $trend = 'down';
        
        $department_performance[] = [
            'department' => $row['department'],
            'score' => round($row['score'], 1),
            'trend' => $trend,
            'employees' => (int)$row['employees']
        ];
    }
}

// Add fallback departments if no data
if (empty($department_performance)) {
    $department_performance = [
        ['department' => 'IT', 'score' => 75.0, 'trend' => 'stable', 'employees' => 4]
    ];
}

// Database-driven top performers data
$top_performers = [];
$query = "SELECT 
    employee_name as name,
    department_name as department,
    performance_score as score,
    goals_completed
FROM employee_performance 
ORDER BY performance_score DESC 
LIMIT 5";

$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $top_performers[] = [
            'name' => $row['name'],
            'department' => $row['department'],
            'score' => round($row['score'], 1),
            'goals_completed' => (int)$row['goals_completed']
        ];
    }
}

// Add fallback top performers if no data
if (empty($top_performers)) {
    $top_performers = [
        ['name' => 'SDK', 'department' => 'IT', 'score' => 75.0, 'goals_completed' => 8]
    ];
}

$current_page = 'performance_analytics';

require_once 'hrms_header_simple.php';
if (!isset($root_path)) 
require_once 'hrms_sidebar_simple.php';

// Include HRMS UI fix
?>

<!-- Page Content Starts Here -->
<div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="gradient-text mb-2" style="font-size: 2.5rem; font-weight: 700;">
                    <i class="bi bi-graph-up text-primary me-3"></i>Performance Analytics
                </h1>
                <p class="text-muted" style="font-size: 1.1rem;">Advanced analytics and insights on employee performance metrics</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" onclick="exportAnalytics()">
                    <i class="bi bi-download me-2"></i>Export Report
                </button>
                <button class="btn btn-primary" onclick="refreshAnalytics()">
                    <i class="bi bi-arrow-clockwise me-2"></i>Refresh Data
                </button>
            </div>
        </div>

        <!-- Key Metrics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-lg-4 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-speedometer2 display-4" style="color: #1976d2;"></i>
                        </div>
                        <h3 class="fw-bold mb-1" style="color: #1976d2;"><?= number_format($performance_metrics['overall_performance'], 1) ?>%</h3>
                        <p class="text-muted mb-0">Overall Performance</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-target display-4" style="color: #388e3c;"></i>
                        </div>
                        <h3 class="fw-bold mb-1" style="color: #388e3c;"><?= number_format($performance_metrics['goal_completion_rate'], 1) ?>%</h3>
                        <p class="text-muted mb-0">Goal Completion Rate</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fff3e0 0%, #ffcc02 100%);">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-emoji-smile display-4" style="color: #f57c00;"></i>
                        </div>
                        <h3 class="fw-bold mb-1" style="color: #f57c00;"><?= number_format($performance_metrics['employee_satisfaction'], 1) ?>%</h3>
                        <p class="text-muted mb-0">Employee Satisfaction</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fce4ec 0%, #f8bbd9 100%);">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-graph-up display-4" style="color: #c2185b;"></i>
                        </div>
                        <h3 class="fw-bold mb-1" style="color: #c2185b;"><?= number_format($performance_metrics['productivity_score'], 1) ?>%</h3>
                        <p class="text-muted mb-0">Productivity Score</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e1f5fe 0%, #b3e5fc 100%);">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-calendar-check display-4" style="color: #0277bd;"></i>
                        </div>
                        <h3 class="fw-bold mb-1" style="color: #0277bd;"><?= number_format($performance_metrics['attendance_rate'], 1) ?>%</h3>
                        <p class="text-muted mb-0">Attendance Rate</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%);">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-mortarboard display-4" style="color: #7b1fa2;"></i>
                        </div>
                        <h3 class="fw-bold mb-1" style="color: #7b1fa2;"><?= number_format($performance_metrics['training_completion'], 1) ?>%</h3>
                        <p class="text-muted mb-0">Training Completion</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Tabs -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#overview" role="tab">
                            <i class="bi bi-graph-up me-2"></i>Overview
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#departments" role="tab">
                            <i class="bi bi-building me-2"></i>Department Analysis
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#trends" role="tab">
                            <i class="bi bi-graph-down me-2"></i>Trends
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#insights" role="tab">
                            <i class="bi bi-lightbulb me-2"></i>Insights
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body">
                <div class="tab-content">
                    <!-- Overview Tab -->
                    <div class="tab-pane fade show active" id="overview" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Performance Distribution</h6>
                                    </div>
                                    <div class="card-body">
                                        <div style="height: 300px; display: flex; align-items: center; justify-content: center;">
                                            <canvas id="performanceChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <h6 class="mb-3">Top Performers</h6>
                                <?php foreach (array_slice($top_performers, 0, 5) as $index => $performer): ?>
                                <div class="card mb-3 border-0 shadow-sm">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle me-3">
                                                <span class="fw-bold text-white"><?= substr($performer['name'], 0, 2) ?></span>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="fw-semibold"><?= htmlspecialchars($performer['name']) ?></div>
                                                <div class="text-muted small"><?= $performer['department'] ?></div>
                                                <div class="d-flex justify-content-between align-items-center mt-1">
                                                    <span class="text-primary fw-semibold"><?= $performer['score'] ?>%</span>
                                                    <span class="badge bg-success"><?= $performer['goals_completed'] ?> goals</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Department Analysis Tab -->
                    <div class="tab-pane fade" id="departments" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-8">
                                <h6 class="mb-3">Department Performance Comparison</h6>
                                <?php foreach ($department_performance as $dept): ?>
                                <div class="card mb-3 border-0 shadow-sm">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div>
                                                <span class="fw-semibold"><?= htmlspecialchars($dept['department']) ?></span>
                                                <span class="text-muted ms-2">(<?= $dept['employees'] ?> employees)</span>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <span class="fw-semibold me-2"><?= $dept['score'] ?>%</span>
                                                <?php
                                                $trend_class = $dept['trend'] == 'up' ? 'badge bg-success' : ($dept['trend'] == 'down' ? 'badge bg-danger' : 'badge bg-warning');
                                                $trend_icon = $dept['trend'] == 'up' ? 'arrow-up' : ($dept['trend'] == 'down' ? 'arrow-down' : 'dash');
                                                ?>
                                                <span class="<?= $trend_class ?>">
                                                    <i class="bi bi-<?= $trend_icon ?>"></i> <?= ucfirst($dept['trend']) ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-primary" style="width: <?= $dept['score'] ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="col-lg-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Department Scores</h6>
                                    </div>
                                    <div class="card-body">
                                        <div style="height: 300px; display: flex; align-items: center; justify-content: center;">
                                            <canvas id="departmentChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Trends Tab -->
                    <div class="tab-pane fade" id="trends" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Performance Trends Over Time</h6>
                            </div>
                            <div class="card-body">
                                <div style="height: 400px;">
                                    <canvas id="trendsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Insights Tab -->
                    <div class="tab-pane fade" id="insights" role="tabpanel">
                        <div class="text-center py-5">
                            <i class="bi bi-lightbulb display-1 text-warning"></i>
                            <h5 class="mt-3">Performance Insights</h5>
                            <p class="text-muted">AI-powered insights and recommendations will be displayed here.</p>
                            <button class="btn btn-warning">
                                <i class="bi bi-robot me-2"></i>Generate AI Insights
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #2563eb, #0891b2);
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Performance Chart
    const performanceCtx = document.getElementById('performanceChart').getContext('2d');
    new Chart(performanceCtx, {
        type: 'doughnut',
        data: {
            labels: ['Excellent', 'Good', 'Average', 'Below Average'],
            datasets: [{
                data: [25, 35, 30, 10],
                backgroundColor: ['#059669', '#2563eb', '#d97706', '#dc2626'],
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

    // Department Chart
    const departmentCtx = document.getElementById('departmentChart').getContext('2d');
    new Chart(departmentCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($department_performance, 'department')) ?>,
            datasets: [{
                label: 'Performance Score',
                data: <?= json_encode(array_column($department_performance, 'score')) ?>,
                backgroundColor: '#2563eb',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });

    // Trends Chart
    const trendsCtx = document.getElementById('trendsChart').getContext('2d');
    new Chart(trendsCtx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug'],
            datasets: [{
                label: 'Overall Performance',
                data: [75, 76, 78, 80, 79, 81, 78, 78.5],
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Goal Completion',
                data: [80, 82, 83, 85, 84, 86, 85, 85.2],
                borderColor: '#059669',
                backgroundColor: 'rgba(5, 150, 105, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });

    function exportAnalytics() {
        showAlert('Exporting performance analytics report...', 'success');
    }

    function refreshAnalytics() {
        showAlert('Refreshing analytics data...', 'info');
    }

    function showAlert(message, type = 'info') {
        const alertDiv = `
            <div class="alert alert-${type} alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; max-width: 400px;">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', alertDiv);
        
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.textContent.includes(message)) {
                    alert.remove();
                }
            });
        }, 5000);
    }
</script>

<?php if (!isset($root_path)) 
require_once 'hrms_footer_simple.php'; ?>
        new Chart(departmentCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($department_performance, 'department')) ?>,
                datasets: [{
                    label: 'Performance Score',
                    data: <?= json_encode(array_column($department_performance, 'score')) ?>,
                    backgroundColor: '#2563eb',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });

        // Trends Chart
        const trendsCtx = document.getElementById('trendsChart').getContext('2d');
        new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug'],
                datasets: [{
                    label: 'Overall Performance',
                    data: [75, 76, 78, 80, 79, 81, 78, 78.5],
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Goal Completion',
                    data: [80, 82, 83, 85, 84, 86, 85, 85.2],
                    borderColor: '#059669',
                    backgroundColor: 'rgba(5, 150, 105, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });

        function exportAnalytics() {
            showAlert('Exporting performance analytics report...', 'success');
        }

        function refreshAnalytics() {
            showAlert('Refreshing analytics data...', 'info');
        }

        function showAlert(message, type = 'info') {
            const alertDiv = `
                <div class="alert alert-${type} alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; max-width: 400px;">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', alertDiv);
            
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    if (alert.textContent.includes(message)) {
                        alert.remove();
                    }
                });
            }, 5000);
        }
    </script>
</body>
</html>

<?php require_once 'hrms_footer_simple.php'; ?>