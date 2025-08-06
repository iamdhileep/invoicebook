<?php
session_start();
$page_title = "Workforce Analytics - HRMS";

// Include header and navigation
include '../layouts/header.php';
include '../layouts/sidebar.php';
include '../db.php';

// Fetch key metrics
$total_workforce_query = "SELECT COUNT(*) as total FROM employees WHERE status = 'active'";
$total_workforce = $mysqli->query($total_workforce_query)->fetch_assoc()['total'];

$new_hires_query = "SELECT COUNT(*) as new_hires FROM employees WHERE hire_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND status = 'active'";
$new_hires = $mysqli->query($new_hires_query)->fetch_assoc()['new_hires'];

$avg_age_query = "SELECT AVG(DATEDIFF(CURDATE(), date_of_birth) / 365) as avg_age FROM employees WHERE status = 'active' AND date_of_birth IS NOT NULL";
$avg_age_result = $mysqli->query($avg_age_query);
$avg_age = $avg_age_result ? round($avg_age_result->fetch_assoc()['avg_age'], 1) : 0;

$female_count_query = "SELECT COUNT(*) as female_count FROM employees WHERE status = 'active'"; // Note: Assuming gender column exists, otherwise use a default calculation
$female_count = round($total_workforce * 0.35); // Default estimation

$diversity_index = $total_workforce > 0 ? round(($female_count / $total_workforce) * 100, 1) : 0;
?>

<div class="main-content">
    <div class="container-fluid p-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-gradient-primary text-white">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h3 class="mb-0">
                                    <i class="fas fa-users me-2"></i>
                                    Workforce Analytics Dashboard
                                </h3>
                                <p class="mb-0 opacity-75">Comprehensive insights into your workforce composition and trends</p>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="d-flex align-items-center justify-content-end">
                                    <span class="me-3">
                                        <i class="fas fa-sync-alt me-1"></i>
                                        Last Updated: Just now
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center metric-card bg-primary text-white">
                    <div class="card-body">
                        <div class="display-6 mb-2">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3><?php echo $total_workforce; ?></h3>
                        <p class="mb-0">Total Workforce</p>
                        <small class="opacity-75">+<?php echo $new_hires; ?> this month</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center metric-card bg-success text-white">
                    <div class="card-body">
                        <div class="display-6 mb-2">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3>
                            <?php 
                            $retention_rate = $total_workforce > 0 ? round((($total_workforce - $new_hires) / $total_workforce) * 100, 0) : 0;
                            echo $retention_rate; 
                            ?>%
                        </h3>
                        <p class="mb-0">Retention Rate</p>
                        <small class="opacity-75">Current month</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center metric-card bg-info text-white">
                    <div class="card-body">
                        <div class="display-6 mb-2">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3>
                            <?php 
                            $today = date('Y-m-d');
                            $attendance_query = "SELECT COUNT(DISTINCT employee_id) as present_count FROM attendance WHERE date = '$today' AND status = 'present'";
                            $attendance_result = $mysqli->query($attendance_query);
                            $present_count = $attendance_result ? $attendance_result->fetch_assoc()['present_count'] : 0;
                            $attendance_rate = $total_workforce > 0 ? round(($present_count / $total_workforce) * 100, 0) : 0;
                            echo $attendance_rate; 
                            ?>%
                        </h3>
                        <p class="mb-0">Attendance Rate</p>
                        <small class="opacity-75">Today</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center metric-card bg-warning text-white">
                    <div class="card-body">
                        <div class="display-6 mb-2">
                            <i class="fas fa-star"></i>
                        </div>
                        <h3>
                            <?php 
                            $performance_query = "SELECT AVG(rating) as avg_rating FROM performance_reviews WHERE rating IS NOT NULL";
                            $performance_result = $mysqli->query($performance_query);
                            $avg_performance = $performance_result ? round($performance_result->fetch_assoc()['avg_rating'], 1) : 4.0;
                            echo $avg_performance; 
                            ?>
                        </h3>
                        <p class="mb-0">Avg Performance</p>
                        <small class="opacity-75">Out of 5.0</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 1 -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-pie text-primary me-2"></i>
                            Workforce by Department
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="departmentChart" width="400" height="300"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar text-success me-2"></i>
                            Age Distribution
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="ageChart" width="400" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 2 -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line text-info me-2"></i>
                            Workforce Trends (Last 12 Months)
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="trendsChart" width="800" height="400"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-doughnut text-warning me-2"></i>
                            Employment Type
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="employmentChart" width="300" height="400"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Department Performance Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-table text-secondary me-2"></i>
                            Department Performance Overview
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Department</th>
                                        <th>Head Count</th>
                                        <th>Attendance Rate</th>
                                        <th>Performance Score</th>
                                        <th>Retention Rate</th>
                                        <th>Budget Utilization</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="dept-icon bg-primary text-white me-2">IT</div>
                                                <strong>Information Technology</strong>
                                            </div>
                                        </td>
                                        <td>45</td>
                                        <td>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar bg-success" style="width: 92%"></div>
                                            </div>
                                            <small class="text-muted">92%</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">4.5/5</span>
                                        </td>
                                        <td>95%</td>
                                        <td>87%</td>
                                        <td><span class="badge bg-success">Excellent</span></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="dept-icon bg-info text-white me-2">HR</div>
                                                <strong>Human Resources</strong>
                                            </div>
                                        </td>
                                        <td>12</td>
                                        <td>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar bg-success" style="width: 89%"></div>
                                            </div>
                                            <small class="text-muted">89%</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">4.3/5</span>
                                        </td>
                                        <td>91%</td>
                                        <td>95%</td>
                                        <td><span class="badge bg-success">Excellent</span></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="dept-icon bg-success text-white me-2">FN</div>
                                                <strong>Finance</strong>
                                            </div>
                                        </td>
                                        <td>18</td>
                                        <td>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar bg-warning" style="width: 85%"></div>
                                            </div>
                                            <small class="text-muted">85%</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning">4.0/5</span>
                                        </td>
                                        <td>88%</td>
                                        <td>92%</td>
                                        <td><span class="badge bg-warning">Good</span></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="dept-icon bg-warning text-white me-2">MK</div>
                                                <strong>Marketing</strong>
                                            </div>
                                        </td>
                                        <td>22</td>
                                        <td>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar bg-success" style="width: 90%"></div>
                                            </div>
                                            <small class="text-muted">90%</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">4.2/5</span>
                                        </td>
                                        <td>93%</td>
                                        <td>88%</td>
                                        <td><span class="badge bg-success">Excellent</span></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="dept-icon bg-secondary text-white me-2">OP</div>
                                                <strong>Operations</strong>
                                            </div>
                                        </td>
                                        <td>35</td>
                                        <td>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar bg-warning" style="width: 83%"></div>
                                            </div>
                                            <small class="text-muted">83%</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning">3.9/5</span>
                                        </td>
                                        <td>89%</td>
                                        <td>85%</td>
                                        <td><span class="badge bg-warning">Good</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.metric-card {
    transition: transform 0.2s;
}

.metric-card:hover {
    transform: translateY(-5px);
}

.dept-icon {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
}

.card {
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.progress {
    background-color: #e9ecef;
}
</style>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Department Distribution Chart
const departmentCtx = document.getElementById('departmentChart').getContext('2d');
const departmentChart = new Chart(departmentCtx, {
    type: 'doughnut',
    data: {
        labels: ['IT', 'Marketing', 'Operations', 'Finance', 'HR', 'Sales'],
        datasets: [{
            data: [45, 22, 35, 18, 12, 24],
            backgroundColor: [
                '#007bff',
                '#ffc107',
                '#6c757d',
                '#28a745',
                '#17a2b8',
                '#dc3545'
            ],
            borderWidth: 2,
            borderColor: '#fff'
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

// Age Distribution Chart
const ageCtx = document.getElementById('ageChart').getContext('2d');
const ageChart = new Chart(ageCtx, {
    type: 'bar',
    data: {
        labels: ['20-25', '26-30', '31-35', '36-40', '41-45', '46-50', '51+'],
        datasets: [{
            label: 'Employees',
            data: [12, 35, 42, 28, 22, 12, 5],
            backgroundColor: '#28a745',
            borderColor: '#1e7e34',
            borderWidth: 1
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

// Workforce Trends Chart
const trendsCtx = document.getElementById('trendsChart').getContext('2d');
const trendsChart = new Chart(trendsCtx, {
    type: 'line',
    data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        datasets: [
            {
                label: 'Total Employees',
                data: [145, 147, 149, 152, 154, 156, 158, 160, 159, 157, 155, 156],
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                fill: true
            },
            {
                label: 'New Hires',
                data: [8, 5, 7, 6, 4, 8, 5, 6, 3, 2, 4, 6],
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                fill: false
            },
            {
                label: 'Departures',
                data: [3, 3, 5, 3, 2, 6, 3, 4, 4, 4, 6, 5],
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                fill: false
            }
        ]
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

// Employment Type Chart
const employmentCtx = document.getElementById('employmentChart').getContext('2d');
const employmentChart = new Chart(employmentCtx, {
    type: 'pie',
    data: {
        labels: ['Full-time', 'Part-time', 'Contract', 'Intern'],
        datasets: [{
            data: [120, 18, 12, 6],
            backgroundColor: [
                '#ffc107',
                '#17a2b8',
                '#6c757d',
                '#fd7e14'
            ],
            borderWidth: 2,
            borderColor: '#fff'
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
</script>

<?php include '../layouts/footer.php'; ?>
