<?php
/**
 * Executive Summary Dashboard
 * High-level strategic insights for C-suite and senior management
 */

session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'config.php';
include 'db.php';

// Executive KPIs and Metrics
function getExecutiveMetrics($conn) {
    $metrics = [];
    
    // Financial Performance
    $result = mysqli_query($conn, "
        SELECT 
            SUM(total_amount) as total_revenue,
            COUNT(*) as total_invoices,
            AVG(total_amount) as avg_invoice_value
        FROM invoices 
        WHERE DATE(created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $financial = mysqli_fetch_assoc($result);
    
    // Workforce Analytics
    $result = mysqli_query($conn, "
        SELECT 
            COUNT(*) as total_employees,
            COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_hires,
            AVG(CASE WHEN status = 'active' THEN 1 ELSE 0 END) * 100 as retention_rate
        FROM employees
    ");
    $workforce = mysqli_fetch_assoc($result);
    
    // Operational Efficiency
    $result = mysqli_query($conn, "
        SELECT 
            COUNT(DISTINCT DATE(punch_in_time)) as active_days,
            AVG(CASE WHEN status = 'present' THEN 1 ELSE 0 END) * 100 as attendance_rate,
            SUM(hours_worked) / COUNT(*) as avg_productivity
        FROM attendance 
        WHERE DATE(punch_in_time) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $operations = mysqli_fetch_assoc($result);
    
    return [
        'financial' => $financial,
        'workforce' => $workforce,
        'operations' => $operations
    ];
}

$metrics = getExecutiveMetrics($conn);
$page_title = 'Executive Dashboard';
include 'layouts/header.php';
include 'layouts/sidebar.php';
?>

<div class="main-content animate-fade-in-up">
    <div class="container-fluid">
        <!-- Executive Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="gradient-text mb-2" style="font-size: 2.5rem; font-weight: 700;">
                    <i class="bi bi-graph-up-arrow text-primary me-3"></i>Executive Summary
                </h1>
                <p class="text-muted" style="font-size: 1.1rem;">Strategic insights and key performance indicators</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" onclick="refreshExecutiveData()">
                    <i class="bi bi-arrow-clockwise me-2"></i>Refresh
                </button>
                <button class="btn btn-primary" onclick="generateExecutiveReport()">
                    <i class="bi bi-file-earmark-pdf me-2"></i>Executive Report
                </button>
            </div>
        </div>

        <!-- Executive KPI Cards -->
        <div class="row g-4 mb-4">
            <!-- Revenue Performance -->
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-center text-white p-4">
                        <div class="icon-circle mb-3" style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                            <i class="bi bi-currency-dollar" style="font-size: 1.5rem;"></i>
                        </div>
                        <h3 class="fw-bold mb-1">₹<?= number_format($metrics['financial']['total_revenue'] ?? 0, 2) ?></h3>
                        <p class="mb-0">Monthly Revenue</p>
                        <small class="opacity-75"><?= $metrics['financial']['total_invoices'] ?? 0 ?> transactions</small>
                    </div>
                </div>
            </div>

            <!-- Workforce Growth -->
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <div class="card-body text-center text-white p-4">
                        <div class="icon-circle mb-3" style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                            <i class="bi bi-people-fill" style="font-size: 1.5rem;"></i>
                        </div>
                        <h3 class="fw-bold mb-1"><?= $metrics['workforce']['total_employees'] ?? 0 ?></h3>
                        <p class="mb-0">Total Employees</p>
                        <small class="opacity-75">+<?= $metrics['workforce']['new_hires'] ?? 0 ?> this month</small>
                    </div>
                </div>
            </div>

            <!-- Operational Excellence -->
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <div class="card-body text-center text-white p-4">
                        <div class="icon-circle mb-3" style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                            <i class="bi bi-speedometer2" style="font-size: 1.5rem;"></i>
                        </div>
                        <h3 class="fw-bold mb-1"><?= round($metrics['operations']['attendance_rate'] ?? 0, 1) ?>%</h3>
                        <p class="mb-0">Attendance Rate</p>
                        <small class="opacity-75">Operational efficiency</small>
                    </div>
                </div>
            </div>

            <!-- Growth Potential -->
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-center text-white p-4">
                        <div class="icon-circle mb-3" style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                            <i class="bi bi-graph-up" style="font-size: 1.5rem;"></i>
                        </div>
                        <h3 class="fw-bold mb-1"><?= round($metrics['workforce']['retention_rate'] ?? 0, 1) ?>%</h3>
                        <p class="mb-0">Retention Rate</p>
                        <small class="opacity-75">Workforce stability</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Strategic Insights Grid -->
        <div class="row g-4 mb-4">
            <!-- Business Performance Chart -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-bar-chart text-primary me-2"></i>Business Performance Trends</h5>
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-outline-secondary btn-sm active" onclick="updateChartView('revenue')">Revenue</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="updateChartView('workforce')">Workforce</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="updateChartView('efficiency')">Efficiency</button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="executiveChart" style="height: 300px;"></canvas>
                    </div>
                </div>
            </div>

            <!-- Strategic Alerts -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Strategic Alerts</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <div class="list-group-item d-flex align-items-start">
                                <div class="alert-icon bg-danger text-white rounded-circle me-3" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                                    <i class="bi bi-exclamation-triangle" style="font-size: 0.8rem;"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">Cash Flow Alert</h6>
                                    <p class="mb-1 small text-muted">Revenue down 15% vs last month</p>
                                    <small class="text-muted">Immediate attention required</small>
                                </div>
                            </div>
                            <div class="list-group-item d-flex align-items-start">
                                <div class="alert-icon bg-warning text-white rounded-circle me-3" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                                    <i class="bi bi-person-exclamation" style="font-size: 0.8rem;"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">Talent Retention</h6>
                                    <p class="mb-1 small text-muted">3 key employees at risk</p>
                                    <small class="text-muted">HR review recommended</small>
                                </div>
                            </div>
                            <div class="list-group-item d-flex align-items-start">
                                <div class="alert-icon bg-success text-white rounded-circle me-3" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                                    <i class="bi bi-check-circle" style="font-size: 0.8rem;"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">Efficiency Gains</h6>
                                    <p class="mb-1 small text-muted">Productivity up 8% this quarter</p>
                                    <small class="text-muted">Process optimization working</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Executive Action Items -->
        <div class="row g-4 mb-4">
            <!-- Key Decisions Pending -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0"><i class="bi bi-clipboard-check text-info me-2"></i>Key Decisions Pending</h5>
                    </div>
                    <div class="card-body">
                        <div class="decision-item d-flex justify-content-between align-items-center py-3 border-bottom">
                            <div>
                                <h6 class="mb-1">Q4 Budget Approval</h6>
                                <small class="text-muted">Department heads awaiting approval</small>
                            </div>
                            <div>
                                <span class="badge bg-danger">Urgent</span>
                            </div>
                        </div>
                        <div class="decision-item d-flex justify-content-between align-items-center py-3 border-bottom">
                            <div>
                                <h6 class="mb-1">New Hire Authorizations</h6>
                                <small class="text-muted">5 positions pending approval</small>
                            </div>
                            <div>
                                <span class="badge bg-warning">High</span>
                            </div>
                        </div>
                        <div class="decision-item d-flex justify-content-between align-items-center py-3">
                            <div>
                                <h6 class="mb-1">Policy Updates</h6>
                                <small class="text-muted">Remote work guidelines revision</small>
                            </div>
                            <div>
                                <span class="badge bg-info">Medium</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Scorecard -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0"><i class="bi bi-trophy text-success me-2"></i>Performance Scorecard</h5>
                    </div>
                    <div class="card-body">
                        <div class="performance-metric d-flex justify-content-between align-items-center py-2">
                            <span>Financial Health</span>
                            <div class="d-flex align-items-center">
                                <div class="progress me-2" style="width: 100px; height: 8px;">
                                    <div class="progress-bar bg-success" style="width: 85%;"></div>
                                </div>
                                <span class="text-success fw-bold">85%</span>
                            </div>
                        </div>
                        <div class="performance-metric d-flex justify-content-between align-items-center py-2">
                            <span>Operational Excellence</span>
                            <div class="d-flex align-items-center">
                                <div class="progress me-2" style="width: 100px; height: 8px;">
                                    <div class="progress-bar bg-info" style="width: 78%;"></div>
                                </div>
                                <span class="text-info fw-bold">78%</span>
                            </div>
                        </div>
                        <div class="performance-metric d-flex justify-content-between align-items-center py-2">
                            <span>Human Capital</span>
                            <div class="d-flex align-items-center">
                                <div class="progress me-2" style="width: 100px; height: 8px;">
                                    <div class="progress-bar bg-primary" style="width: 92%;"></div>
                                </div>
                                <span class="text-primary fw-bold">92%</span>
                            </div>
                        </div>
                        <div class="performance-metric d-flex justify-content-between align-items-center py-2">
                            <span>Innovation Index</span>
                            <div class="d-flex align-items-center">
                                <div class="progress me-2" style="width: 100px; height: 8px;">
                                    <div class="progress-bar bg-warning" style="width: 65%;"></div>
                                </div>
                                <span class="text-warning fw-bold">65%</span>
                            </div>
                        </div>
                        <div class="performance-metric d-flex justify-content-between align-items-center py-2">
                            <span>Customer Satisfaction</span>
                            <div class="d-flex align-items-center">
                                <div class="progress me-2" style="width: 100px; height: 8px;">
                                    <div class="progress-bar bg-success" style="width: 91%;"></div>
                                </div>
                                <span class="text-success fw-bold">91%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Strategic Initiatives -->
        <div class="row g-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0"><i class="bi bi-bullseye text-primary me-2"></i>Strategic Initiatives & Goals</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-md-4">
                                <div class="initiative-card p-3 border rounded">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0">Digital Transformation</h6>
                                        <span class="badge bg-primary">Q4 2025</span>
                                    </div>
                                    <div class="progress mb-2" style="height: 6px;">
                                        <div class="progress-bar bg-primary" style="width: 75%;"></div>
                                    </div>
                                    <small class="text-muted">75% complete • On track</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="initiative-card p-3 border rounded">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0">Workforce Expansion</h6>
                                        <span class="badge bg-success">Q1 2026</span>
                                    </div>
                                    <div class="progress mb-2" style="height: 6px;">
                                        <div class="progress-bar bg-success" style="width: 45%;"></div>
                                    </div>
                                    <small class="text-muted">45% complete • Ahead of schedule</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="initiative-card p-3 border rounded">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0">Market Expansion</h6>
                                        <span class="badge bg-warning">Q2 2026</span>
                                    </div>
                                    <div class="progress mb-2" style="height: 6px;">
                                        <div class="progress-bar bg-warning" style="width: 30%;"></div>
                                    </div>
                                    <small class="text-muted">30% complete • Planning phase</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.gradient-text {
    background: linear-gradient(45deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.animate-fade-in-up {
    animation: fadeInUp 0.6s ease;
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
}

.decision-item:last-child {
    border-bottom: none !important;
}

.performance-metric {
    border-bottom: 1px solid rgba(0,0,0,0.05);
}

.performance-metric:last-child {
    border-bottom: none;
}

.initiative-card {
    transition: all 0.3s ease;
}

.initiative-card:hover {
    border-color: #007bff !important;
    box-shadow: 0 4px 12px rgba(0,123,255,0.15);
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Executive Chart
let executiveChart;

document.addEventListener('DOMContentLoaded', function() {
    initializeExecutiveChart();
});

function initializeExecutiveChart() {
    const ctx = document.getElementById('executiveChart').getContext('2d');
    
    executiveChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
            datasets: [{
                label: 'Revenue (₹ Lakhs)',
                data: [12, 15, 18, 16, 20, 22, 25],
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Expenses (₹ Lakhs)',
                data: [8, 10, 12, 11, 13, 15, 16],
                borderColor: '#fa709a',
                backgroundColor: 'rgba(250, 112, 154, 0.1)',
                tension: 0.4,
                fill: true
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
                    beginAtZero: true
                }
            }
        }
    });
}

function updateChartView(view) {
    // Update active button
    document.querySelectorAll('.btn-group .btn').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    let datasets;
    
    switch(view) {
        case 'revenue':
            datasets = [{
                label: 'Revenue (₹ Lakhs)',
                data: [12, 15, 18, 16, 20, 22, 25],
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Expenses (₹ Lakhs)',
                data: [8, 10, 12, 11, 13, 15, 16],
                borderColor: '#fa709a',
                backgroundColor: 'rgba(250, 112, 154, 0.1)',
                tension: 0.4,
                fill: true
            }];
            break;
            
        case 'workforce':
            datasets = [{
                label: 'Employees',
                data: [45, 48, 52, 55, 58, 62, 65],
                borderColor: '#43e97b',
                backgroundColor: 'rgba(67, 233, 123, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Attendance %',
                data: [85, 87, 89, 91, 88, 92, 94],
                borderColor: '#38f9d7',
                backgroundColor: 'rgba(56, 249, 215, 0.1)',
                tension: 0.4,
                fill: true
            }];
            break;
            
        case 'efficiency':
            datasets = [{
                label: 'Productivity Index',
                data: [75, 78, 82, 80, 85, 88, 90],
                borderColor: '#fee140',
                backgroundColor: 'rgba(254, 225, 64, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Customer Satisfaction',
                data: [82, 84, 86, 85, 89, 91, 93],
                borderColor: '#fa709a',
                backgroundColor: 'rgba(250, 112, 154, 0.1)',
                tension: 0.4,
                fill: true
            }];
            break;
    }
    
    executiveChart.data.datasets = datasets;
    executiveChart.update();
}

function refreshExecutiveData() {
    // Show loading state
    const refreshBtn = event.target;
    const originalHTML = refreshBtn.innerHTML;
    refreshBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin me-2"></i>Refreshing...';
    refreshBtn.disabled = true;
    
    // Simulate data refresh
    setTimeout(() => {
        refreshBtn.innerHTML = originalHTML;
        refreshBtn.disabled = false;
        
        // Show success message
        showAlert('Executive data refreshed successfully!', 'success');
    }, 2000);
}

function generateExecutiveReport() {
    showAlert('Executive report is being generated and will be sent to your email shortly.', 'info');
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

// Add spin animation for refresh
const style = document.createElement('style');
style.textContent = `
    .spin { animation: spin 1s linear infinite; }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
`;
document.head.appendChild(style);
</script>

<?php include 'layouts/footer.php'; ?>
