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

$page_title = 'KPI Tracking - HRMS';

// Create KPI tracking tables if not exist
$createKPITable = "
CREATE TABLE IF NOT EXISTS kpi_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category ENUM('retention', 'recruitment', 'engagement', 'development', 'attendance', 'performance', 'other') DEFAULT 'other',
    target_value DECIMAL(10,2) NOT NULL,
    current_value DECIMAL(10,2) DEFAULT 0,
    unit VARCHAR(20) DEFAULT '',
    frequency ENUM('daily', 'weekly', 'monthly', 'quarterly', 'yearly') DEFAULT 'monthly',
    calculation_method TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES employees(employee_id)
)";
mysqli_query($conn, $createKPITable);

$createKPIDataTable = "
CREATE TABLE IF NOT EXISTS kpi_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kpi_id INT NOT NULL,
    value DECIMAL(10,2) NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    notes TEXT,
    recorded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kpi_id) REFERENCES kpi_metrics(id),
    FOREIGN KEY (recorded_by) REFERENCES employees(employee_id)
)";
mysqli_query($conn, $createKPIDataTable);

// Calculate real KPIs from database
$kpis = [];

// Employee Retention Rate
$totalEmployees = 0;
$activeEmployees = 0;
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM employees");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalEmployees = $row['total'];
}

$result = mysqli_query($conn, "SELECT COUNT(*) as active FROM employees WHERE status = 'active'");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $activeEmployees = $row['active'];
}

$retentionRate = $totalEmployees > 0 ? round(($activeEmployees / $totalEmployees) * 100, 1) : 0;

// Attendance Rate for this month
$currentMonth = date('Y-m');
$workingDays = 22; // Average working days per month
$totalPossibleAttendance = $activeEmployees * $workingDays;
$actualAttendance = 0;

$result = mysqli_query($conn, "
    SELECT COUNT(*) as present_days 
    FROM attendance 
    WHERE DATE_FORMAT(attendance_date, '%Y-%m') = '$currentMonth' 
    AND status IN ('Present', 'Late')
");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $actualAttendance = $row['present_days'];
}

$attendanceRate = $totalPossibleAttendance > 0 ? round(($actualAttendance / $totalPossibleAttendance) * 100, 1) : 0;

// Absenteeism Rate
$absenteeismRate = 100 - $attendanceRate;

// Average Salary
$avgSalary = 0;
$result = mysqli_query($conn, "SELECT AVG(monthly_salary) as avg_salary FROM employees WHERE status = 'active' AND monthly_salary > 0");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $avgSalary = round($row['avg_salary'], 0);
}

// Pending Leave Requests
$pendingLeaves = 0;
$result = mysqli_query($conn, "SELECT COUNT(*) as pending FROM leave_requests WHERE status = 'pending'");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $pendingLeaves = $row['pending'];
}

// New Joiners this month
$newJoiners = 0;
$result = mysqli_query($conn, "SELECT COUNT(*) as new_joiners FROM employees WHERE DATE_FORMAT(joining_date, '%Y-%m') = '$currentMonth'");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $newJoiners = $row['new_joiners'];
}

// Build KPI array with real data
$kpis = [
    [
        'id' => 1,
        'name' => 'Employee Retention Rate',
        'current_value' => $retentionRate,
        'target_value' => 95.0,
        'unit' => '%',
        'trend' => $retentionRate >= 90 ? 'up' : 'down',
        'category' => 'Retention',
        'last_updated' => date('Y-m-d')
    ],
    [
        'id' => 2,
        'name' => 'Active Employees',
        'current_value' => $activeEmployees,
        'target_value' => $totalEmployees,
        'unit' => '',
        'trend' => 'stable',
        'category' => 'Workforce',
        'last_updated' => date('Y-m-d')
    ],
    [
        'id' => 3,
        'name' => 'Monthly Attendance Rate',
        'current_value' => $attendanceRate,
        'target_value' => 95.0,
        'unit' => '%',
        'trend' => $attendanceRate >= 90 ? 'up' : 'down',
        'category' => 'Attendance',
        'last_updated' => date('Y-m-d')
    ],
    [
        'id' => 4,
        'name' => 'Absenteeism Rate',
        'current_value' => $absenteeismRate,
        'target_value' => 5.0,
        'unit' => '%',
        'trend' => $absenteeismRate <= 5 ? 'down' : 'up',
        'category' => 'Attendance',
        'last_updated' => date('Y-m-d')
    ],
    [
        'id' => 5,
        'name' => 'Pending Leave Requests',
        'current_value' => $pendingLeaves,
        'target_value' => 5,
        'unit' => '',
        'trend' => $pendingLeaves <= 5 ? 'down' : 'up',
        'category' => 'Leave Management',
        'last_updated' => date('Y-m-d')
    ],
    [
        'id' => 6,
        'name' => 'Average Monthly Salary',
        'current_value' => $avgSalary,
        'target_value' => 50000,
        'unit' => '₹',
        'trend' => 'stable',
        'category' => 'Compensation',
        'last_updated' => date('Y-m-d')
    ],
    [
        'id' => 7,
        'name' => 'New Joiners This Month',
        'current_value' => $newJoiners,
        'target_value' => 3,
        'unit' => '',
        'trend' => $newJoiners >= 2 ? 'up' : 'down',
        'category' => 'Recruitment',
        'last_updated' => date('Y-m-d')
    ]
];

$current_page = 'kpi_tracking';

include '../layouts/header.php';
if (!isset($root_path)) 
include '../layouts/sidebar.php';
?>

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
                    <i class="bi bi-graph-up text-primary me-3"></i>KPI Tracking
                </h1>
                <p class="text-muted" style="font-size: 1.1rem;">Monitor and track key performance indicators across all HR functions</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-info" onclick="exportKPIReport()">
                    <i class="bi bi-download"></i> Export Report
                </button>
                <button class="btn btn-primary" onclick="addNewKPI()">
                    <i class="bi bi-plus"></i> Add KPI
                </button>
            </div>
        </div>

        <!-- KPI Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <i class="bi bi-bullseye fs-1" style="color: #1976d2;"></i>
                        </div>
                        <h3 class="mb-2 fw-bold" style="color: #1976d2;"><?= count($kpis) ?></h3>
                        <p class="text-muted mb-0">Total KPIs</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <i class="bi bi-check-circle-fill fs-1" style="color: #388e3c;"></i>
                        </div>
                        <h3 class="mb-2 fw-bold" style="color: #388e3c;">4</h3>
                        <p class="text-muted mb-0">On Track</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%);">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <i class="bi bi-exclamation-triangle-fill fs-1" style="color: #f57c00;"></i>
                        </div>
                        <h3 class="mb-2 fw-bold" style="color: #f57c00;">2</h3>
                        <p class="text-muted mb-0">Needs Attention</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <i class="bi bi-x-circle-fill fs-1" style="color: #d32f2f;"></i>
                        </div>
                        <h3 class="mb-2 fw-bold" style="color: #d32f2f;">0</h3>
                        <p class="text-muted mb-0">Critical</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row g-4 mb-4">
            <?php foreach ($kpis as $kpi): ?>
            <div class="col-lg-4 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4 position-relative">
                        <?php
                        $trend_class = 'trend-' . $kpi['trend'];
                        $trend_icon = $kpi['trend'] == 'up' ? 'arrow-up' : ($kpi['trend'] == 'down' ? 'arrow-down' : 'dash');
                        $trend_color = $kpi['trend'] == 'up' ? 'success' : ($kpi['trend'] == 'down' ? 'danger' : 'warning');
                        ?>
                        <div class="position-absolute top-0 end-0 m-3">
                            <span class="badge bg-<?= $trend_color ?>">
                                <i class="bi bi-<?= $trend_icon ?>"></i> <?= ucfirst($kpi['trend']) ?>
                            </span>
                        </div>
                        
                        <div class="mb-3">
                            <?php
                            $category_colors = [
                                'retention' => 'primary',
                                'recruitment' => 'success', 
                                'engagement' => 'warning',
                                'development' => 'info',
                                'attendance' => 'danger'
                            ];
                            $category_color = $category_colors[strtolower($kpi['category'])] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $category_color ?> bg-opacity-10 text-<?= $category_color ?> fw-semibold">
                                <?= $kpi['category'] ?>
                            </span>
                        </div>
                        
                        <h5 class="card-title mb-3"><?= htmlspecialchars($kpi['name']) ?></h5>
                        
                        <div class="d-flex align-items-end mb-3">
                            <span class="display-6 fw-bold text-primary"><?= $kpi['current_value'] ?></span>
                            <span class="fs-5 text-muted ms-1 mb-2"><?= $kpi['unit'] ?></span>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted">
                                Target: <span class="fw-semibold"><?= $kpi['target_value'] ?><?= $kpi['unit'] ?></span>
                            </small>
                        </div>
                        
                        <?php
                        $progress_percentage = min(($kpi['current_value'] / $kpi['target_value']) * 100, 100);
                        if ($kpi['name'] == 'Time to Fill Positions' || $kpi['name'] == 'Absenteeism Rate' || $kpi['name'] == 'Cost per Hire') {
                            // For KPIs where lower is better
                            $progress_percentage = max(100 - (($kpi['current_value'] / $kpi['target_value']) * 100), 0);
                        }
                        $progress_color = $progress_percentage >= 80 ? 'success' : ($progress_percentage >= 60 ? 'warning' : 'danger');
                        ?>
                        <div class="progress mb-3" style="height: 8px;">
                            <div class="progress-bar bg-<?= $progress_color ?>" role="progressbar" 
                                 style="width: <?= $progress_percentage ?>%" 
                                 aria-valuenow="<?= $progress_percentage ?>" 
                                 aria-valuemin="0" aria-valuemax="100">
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="bi bi-calendar3"></i> <?= date('M j', strtotime($kpi['last_updated'])) ?>
                            </small>
                            <button class="btn btn-sm btn-outline-primary" onclick="viewKPIDetails(<?= $kpi['id'] ?>)">
                                <i class="bi bi-eye"></i> Details
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Main Content Tabs -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light border-0">
                <ul class="nav nav-tabs nav-tabs-card card-header-tabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#overview" role="tab">
                            <i class="bi bi-graph-up me-2"></i>Overview
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#trends" role="tab">
                            <i class="bi bi-graph-down me-2"></i>Trends
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#benchmarks" role="tab">
                            <i class="bi bi-bar-chart me-2"></i>Benchmarks
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#alerts" role="tab">
                            <i class="bi bi-exclamation-triangle me-2"></i>Alerts
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
                                <div class="chart-container" style="position: relative; height: 350px;">
                                    <canvas id="kpiOverviewChart"></canvas>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <h6 class="mb-3">KPI Performance Summary</h6>
                                <div class="list-group list-group-flush">
                                    <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                                        <span><i class="bi bi-check-circle text-success me-2"></i>On Track</span>
                                        <span class="badge bg-success rounded-pill">4</span>
                                    </div>
                                    <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                                        <span><i class="bi bi-exclamation-triangle text-warning me-2"></i>Needs Attention</span>
                                        <span class="badge bg-warning rounded-pill">2</span>
                                    </div>
                                    <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                                        <span><i class="bi bi-x-circle text-danger me-2"></i>Critical</span>
                                        <span class="badge bg-danger rounded-pill">0</span>
                                    </div>
                                </div>
                                
                                <div class="mt-4">
                                    <h6 class="mb-3">Recent Activity</h6>
                                    <div class="list-group list-group-flush">
                                        <div class="list-group-item border-0 px-0 py-2">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1 fs-6">Employee Retention Rate</h6>
                                                <small>2 hours ago</small>
                                            </div>
                                            <p class="mb-1 small text-muted">Updated to 92.5%</p>
                                        </div>
                                        <div class="list-group-item border-0 px-0 py-2">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1 fs-6">Training Completion</h6>
                                                <small>1 day ago</small>
                                            </div>
                                            <p class="mb-1 small text-muted">Updated to 78.3%</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Trends Tab -->
                    <div class="tab-pane fade" id="trends" role="tabpanel">
                        <div class="chart-container" style="position: relative; height: 400px;">
                            <canvas id="trendsChart"></canvas>
                        </div>
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="card-title">Best Performing KPI</h6>
                                        <p class="card-text">Employee Retention Rate</p>
                                        <span class="badge bg-success">92.5%</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="card-title">Needs Improvement</h6>
                                        <p class="card-text">Training Completion Rate</p>
                                        <span class="badge bg-warning">78.3%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Benchmarks Tab -->
                    <div class="tab-pane fade" id="benchmarks" role="tabpanel">
                        <div class="text-center py-5">
                            <i class="bi bi-bar-chart display-1 text-info"></i>
                            <h5 class="mt-3">Industry Benchmarks</h5>
                            <p class="text-muted">Compare your KPIs with industry standards and best practices.</p>
                            <button class="btn btn-info">
                                <i class="bi bi-graph-up me-2"></i>View Benchmarks
                            </button>
                        </div>
                    </div>

                    <!-- Alerts Tab -->
                    <div class="tab-pane fade" id="alerts" role="tabpanel">
                        <div class="text-center py-5">
                            <i class="bi bi-exclamation-triangle display-1 text-warning"></i>
                            <h5 class="mt-3">KPI Alerts & Notifications</h5>
                            <p class="text-muted">Set up alerts for when KPIs fall below or exceed thresholds.</p>
                            <button class="btn btn-warning">
                                <i class="bi bi-bell me-2"></i>Configure Alerts
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // KPI Overview Chart
    const overviewCtx = document.getElementById('kpiOverviewChart');
    if (overviewCtx) {
        new Chart(overviewCtx, {
            type: 'radar',
            data: {
                labels: ['Retention', 'Recruitment', 'Engagement', 'Development', 'Attendance'],
                datasets: [{
                    label: 'Current Performance',
                    data: [92.5, 82, 84, 78.3, 96.8],
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    pointBackgroundColor: '#0d6efd',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }, {
                    label: 'Target',
                    data: [95, 87, 90, 85, 97.5],
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    pointBackgroundColor: '#198754',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    r: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            stepSize: 20
                        }
                    }
                }
            }
        });
    }

    // Trends Chart
    const trendsCtx = document.getElementById('trendsChart');
    if (trendsCtx) {
        new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug'],
                datasets: [{
                    label: 'Employee Retention Rate (%)',
                    data: [90, 91, 92, 91.5, 92, 92.2, 92.1, 92.5],
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Employee Satisfaction Score',
                    data: [3.8, 3.9, 4.0, 4.1, 4.1, 4.2, 4.1, 4.2],
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        max: 5,
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
    }

    function viewKPIDetails(kpiId) {
        showAlert(`Viewing details for KPI ${kpiId}...`, 'info');
    }

    function addNewKPI() {
        showAlert('New KPI creation form will be implemented!', 'info');
    }

    function exportKPIReport() {
        showAlert('Exporting KPI report...', 'success');
    }

    function showAlert(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(alertDiv);
        
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }
</script>

<?php if (!isset($root_path)) 
include '../layouts/footer.php'; ?>
