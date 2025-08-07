<?php
$page_title = "Advanced Analytics Dashboard - HRMS";

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

<!-- Page Content Starts Here -->
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

<?php 
<?php require_once 'hrms_footer_simple.php'; ?>