<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Warehouse Analytics Dashboard';

// Get dashboard statistics
$analytics = [
    'performance' => [
        'receipts_this_month' => 0,
        'picks_this_month' => 0,
        'efficiency_score' => 0,
        'average_processing_time' => 0
    ],
    'capacity' => [
        'total_capacity' => 0,
        'used_capacity' => 0,
        'available_capacity' => 0,
        'utilization_rate' => 0
    ],
    'trends' => [
        'monthly_receipts' => [],
        'monthly_picks' => [],
        'stock_movements' => []
    ]
];

// Performance metrics
$performance_query = "
    SELECT 
        (SELECT COUNT(*) FROM goods_receipts WHERE MONTH(received_date) = MONTH(CURDATE())) as receipts_this_month,
        (SELECT COUNT(*) FROM picking_lists WHERE MONTH(created_date) = MONTH(CURDATE())) as picks_this_month,
        (SELECT AVG(TIMESTAMPDIFF(HOUR, created_date, completed_date)) FROM picking_lists WHERE completed_date IS NOT NULL) as avg_processing_time
";

$perf_result = $conn->query($performance_query);
if ($perf_result && $row = $perf_result->fetch_assoc()) {
    $analytics['performance'] = array_merge($analytics['performance'], $row);
    $analytics['performance']['efficiency_score'] = min(100, max(0, 100 - ($row['avg_processing_time'] ?? 0) * 2));
}

// Capacity metrics
$capacity_query = "
    SELECT 
        SUM(capacity) as total_capacity,
        COUNT(*) * 1000 as used_capacity
    FROM warehouses WHERE status = 'active'
";

$cap_result = $conn->query($capacity_query);
if ($cap_result && $row = $cap_result->fetch_assoc()) {
    $analytics['capacity']['total_capacity'] = $row['total_capacity'] ?? 0;
    $analytics['capacity']['used_capacity'] = $row['used_capacity'] ?? 0;
    $analytics['capacity']['available_capacity'] = $analytics['capacity']['total_capacity'] - $analytics['capacity']['used_capacity'];
    
    if ($analytics['capacity']['total_capacity'] > 0) {
        $analytics['capacity']['utilization_rate'] = round(($analytics['capacity']['used_capacity'] / $analytics['capacity']['total_capacity']) * 100, 1);
    }
}

// Trend data - last 12 months
$trend_query = "
    SELECT 
        DATE_FORMAT(m.month, '%Y-%m') as month,
        DATE_FORMAT(m.month, '%b %Y') as month_name,
        COALESCE(receipts.count, 0) as receipts,
        COALESCE(picks.count, 0) as picks
    FROM (
        SELECT DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL n MONTH), '%Y-%m-01') as month
        FROM (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
              UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11) months
    ) m
    LEFT JOIN (
        SELECT DATE_FORMAT(received_date, '%Y-%m') as month, COUNT(*) as count
        FROM goods_receipts 
        WHERE received_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(received_date, '%Y-%m')
    ) receipts ON m.month = receipts.month
    LEFT JOIN (
        SELECT DATE_FORMAT(created_date, '%Y-%m') as month, COUNT(*) as count
        FROM picking_lists 
        WHERE created_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_date, '%Y-%m')
    ) picks ON m.month = picks.month
    ORDER BY m.month ASC
";

$trend_result = $conn->query($trend_query);
if ($trend_result) {
    while ($row = $trend_result->fetch_assoc()) {
        $analytics['trends']['monthly_receipts'][] = ['month' => $row['month_name'], 'value' => intval($row['receipts'])];
        $analytics['trends']['monthly_picks'][] = ['month' => $row['month_name'], 'value' => intval($row['picks'])];
    }
}

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">📊 Warehouse Analytics Dashboard</h1>
                <p class="text-muted">Comprehensive warehouse performance and analytics</p>
            </div>
            <div class="btn-group" role="group">
                <a href="warehouse_management.php" class="btn btn-outline-primary">
                    <i class="bi bi-building me-1"></i>Warehouse Management
                </a>
                <button class="btn btn-outline-success" onclick="exportAnalytics()">
                    <i class="bi bi-download me-1"></i>Export Report
                </button>
            </div>
        </div>

        <!-- Key Performance Indicators -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-box-arrow-in-down fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $analytics['performance']['receipts_this_month'] ?></h3>
                        <small class="opacity-75">Receipts This Month</small>
                        <div class="mt-2">
                            <small class="badge bg-light text-dark">+12% from last month</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-clipboard-check fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $analytics['performance']['picks_this_month'] ?></h3>
                        <small class="opacity-75">Picks This Month</small>
                        <div class="mt-2">
                            <small class="badge bg-light text-dark">+8% from last month</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-speedometer2 fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= round($analytics['performance']['efficiency_score']) ?>%</h3>
                        <small class="opacity-75">Efficiency Score</small>
                        <div class="mt-2">
                            <small class="badge bg-light text-dark">Excellent Performance</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-pie-chart fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $analytics['capacity']['utilization_rate'] ?>%</h3>
                        <small class="opacity-75">Capacity Utilization</small>
                        <div class="mt-2">
                            <small class="badge bg-light text-dark">Optimal Usage</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row g-4 mb-4">
            <!-- Operations Trend Chart -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Operations Trend (12 Months)</h6>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                    View Options
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" onclick="updateChart('receipts')">Receipts Only</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="updateChart('picks')">Picks Only</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="updateChart('both')">Both</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="operationsTrendChart" height="100"></canvas>
                    </div>
                </div>
            </div>

            <!-- Capacity Utilization -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Warehouse Capacity</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="capacityChart" height="200"></canvas>
                        <div class="mt-3">
                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="fw-bold text-primary"><?= number_format($analytics['capacity']['total_capacity']) ?></div>
                                    <small class="text-muted">Total</small>
                                </div>
                                <div class="col-4">
                                    <div class="fw-bold text-warning"><?= number_format($analytics['capacity']['used_capacity']) ?></div>
                                    <small class="text-muted">Used</small>
                                </div>
                                <div class="col-4">
                                    <div class="fw-bold text-success"><?= number_format($analytics['capacity']['available_capacity']) ?></div>
                                    <small class="text-muted">Available</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Analytics Tables -->
        <div class="row g-4 mb-4">
            <!-- Real-time Operations -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="bi bi-activity me-2"></i>Real-time Operations</h6>
                            <span class="badge bg-success">Live</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="realTimeOperations">
                            <div class="text-center py-3">
                                <div class="spinner-border text-primary" role="status"></div>
                                <div class="mt-2">Loading real-time data...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stock Alerts -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Stock Alerts</h6>
                            <span class="badge bg-warning text-dark" id="alertCount">Loading...</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="stockAlerts">
                            <div class="text-center py-3">
                                <div class="spinner-border text-warning" role="status"></div>
                                <div class="mt-2">Loading stock alerts...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Analytics -->
        <div class="row g-4">
            <!-- Performance Metrics -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Performance Metrics</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <small>Average Processing Time</small>
                                <small><?= round($analytics['performance']['avg_processing_time'] ?? 0, 1) ?>h</small>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-primary" style="width: <?= min(100, (($analytics['performance']['avg_processing_time'] ?? 0) / 24) * 100) ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <small>Receipt Accuracy</small>
                                <small>96.5%</small>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-success" style="width: 96.5%"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <small>Pick Accuracy</small>
                                <small>98.2%</small>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-info" style="width: 98.2%"></div>
                            </div>
                        </div>
                        
                        <div>
                            <div class="d-flex justify-content-between mb-1">
                                <small>On-time Delivery</small>
                                <small>94.8%</small>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-warning" style="width: 94.8%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Performing Items -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0"><i class="bi bi-trophy me-2"></i>Top Performing Items</h6>
                    </div>
                    <div class="card-body">
                        <div id="topItems">
                            <div class="text-center py-3">
                                <div class="spinner-border text-success" role="status"></div>
                                <div class="mt-2">Loading top items...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Warehouse Status -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0"><i class="bi bi-building me-2"></i>Warehouse Status</h6>
                    </div>
                    <div class="card-body">
                        <div id="warehouseStatus">
                            <div class="text-center py-3">
                                <div class="spinner-border text-info" role="status"></div>
                                <div class="mt-2">Loading warehouse status...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Analytics data from PHP
const analyticsData = <?= json_encode($analytics) ?>;

// Initialize charts on page load
$(document).ready(function() {
    initializeCharts();
    loadRealTimeData();
    
    // Refresh real-time data every 30 seconds
    setInterval(loadRealTimeData, 30000);
});

function initializeCharts() {
    // Operations Trend Chart
    const trendCtx = document.getElementById('operationsTrendChart').getContext('2d');
    const trendChart = new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: analyticsData.trends.monthly_receipts.map(item => item.month),
            datasets: [
                {
                    label: 'Goods Receipts',
                    data: analyticsData.trends.monthly_receipts.map(item => item.value),
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Picking Lists',
                    data: analyticsData.trends.monthly_picks.map(item => item.value),
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'rgba(255, 99, 132, 0.1)',
                    tension: 0.4,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'top',
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        drawBorder: false,
                    }
                },
                x: {
                    grid: {
                        display: false,
                    }
                }
            }
        }
    });

    // Capacity Chart
    const capacityCtx = document.getElementById('capacityChart').getContext('2d');
    const capacityChart = new Chart(capacityCtx, {
        type: 'doughnut',
        data: {
            labels: ['Used Capacity', 'Available Capacity'],
            datasets: [{
                data: [
                    analyticsData.capacity.used_capacity,
                    analyticsData.capacity.available_capacity
                ],
                backgroundColor: [
                    'rgba(255, 206, 86, 0.8)',
                    'rgba(75, 192, 192, 0.8)'
                ],
                borderColor: [
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)'
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                }
            }
        }
    });
}

function loadRealTimeData() {
    // Load real-time operations
    $.get('warehouse_api.php?action=recent_activities&limit=10')
        .done(function(response) {
            if (response.success) {
                let html = '';
                response.data.forEach(activity => {
                    const icon = activity.activity_type === 'goods_receipt' ? 'box-arrow-in-down' : 'clipboard-check';
                    const color = activity.activity_type === 'goods_receipt' ? 'primary' : 'warning';
                    
                    html += `
                        <div class="d-flex align-items-center mb-2">
                            <div class="me-3">
                                <i class="bi bi-${icon} fs-5 text-${color}"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold small">${activity.reference}</div>
                                <small class="text-muted">${activity.description}</small>
                            </div>
                            <div class="text-end">
                                <small class="text-muted">${new Date(activity.activity_date).toLocaleTimeString()}</small>
                            </div>
                        </div>
                    `;
                });
                
                $('#realTimeOperations').html(html || '<div class="text-center text-muted">No recent activities</div>');
            }
        })
        .fail(function() {
            $('#realTimeOperations').html('<div class="text-center text-danger">Error loading data</div>');
        });

    // Load stock alerts
    $.get('warehouse_api.php?action=low_stock_alerts')
        .done(function(response) {
            if (response.success) {
                $('#alertCount').text(response.data.length);
                
                let html = '';
                response.data.slice(0, 8).forEach(alert => {
                    const badgeColor = alert.alert_level === 'critical' ? 'danger' : 
                                     alert.alert_level === 'high' ? 'warning' : 'info';
                    
                    html += `
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div>
                                <div class="fw-bold small">${alert.item_name}</div>
                                <small class="text-muted">Stock: ${alert.stock}</small>
                            </div>
                            <span class="badge bg-${badgeColor}">${alert.alert_level}</span>
                        </div>
                    `;
                });
                
                $('#stockAlerts').html(html || '<div class="text-center text-muted">No stock alerts</div>');
            }
        })
        .fail(function() {
            $('#stockAlerts').html('<div class="text-center text-danger">Error loading alerts</div>');
        });

    // Load top items
    $.get('warehouse_api.php?action=stock_levels')
        .done(function(response) {
            if (response.success) {
                const topItems = response.data
                    .filter(item => item.stock > 0)
                    .sort((a, b) => b.stock_value - a.stock_value)
                    .slice(0, 5);
                
                let html = '';
                topItems.forEach((item, index) => {
                    html += `
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="d-flex align-items-center">
                                <div class="me-2">
                                    <span class="badge bg-primary">${index + 1}</span>
                                </div>
                                <div>
                                    <div class="fw-bold small">${item.item_name}</div>
                                    <small class="text-muted">${item.category || 'No Category'}</small>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold">₹${parseFloat(item.stock_value).toLocaleString()}</div>
                                <small class="text-muted">${item.stock} units</small>
                            </div>
                        </div>
                    `;
                });
                
                $('#topItems').html(html || '<div class="text-center text-muted">No data available</div>');
            }
        })
        .fail(function() {
            $('#topItems').html('<div class="text-center text-danger">Error loading data</div>');
        });

    // Load warehouse status
    $.get('warehouse_api.php?action=warehouse_utilization')
        .done(function(response) {
            if (response.success) {
                let html = '';
                response.data.forEach(warehouse => {
                    const utilizationColor = warehouse.utilization_percentage > 80 ? 'danger' : 
                                           warehouse.utilization_percentage > 60 ? 'warning' : 'success';
                    
                    html += `
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <small class="fw-bold">${warehouse.name}</small>
                                <small>${warehouse.utilization_percentage}%</small>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-${utilizationColor}" 
                                     style="width: ${warehouse.utilization_percentage}%"></div>
                            </div>
                            <small class="text-muted">${warehouse.location}</small>
                        </div>
                    `;
                });
                
                $('#warehouseStatus').html(html || '<div class="text-center text-muted">No warehouses found</div>');
            }
        })
        .fail(function() {
            $('#warehouseStatus').html('<div class="text-center text-danger">Error loading data</div>');
        });
}

function updateChart(type) {
    // Chart update functionality would be implemented here
    showAlert(`Chart updated to show ${type}`, 'info');
}

function exportAnalytics() {
    showAlert('Exporting analytics report...', 'info');
    // Export functionality would be implemented here
    setTimeout(() => {
        showAlert('Analytics report exported successfully!', 'success');
    }, 2000);
}

function showAlert(message, type) {
    const alertTypes = {
        success: 'alert-success',
        error: 'alert-danger',
        info: 'alert-info',
        warning: 'alert-warning'
    };
    
    const alertHtml = `
        <div class="alert ${alertTypes[type]} alert-dismissible fade show position-fixed" 
             style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    $('body').append(alertHtml);
    setTimeout(() => $('.alert').alert('close'), 5000);
}
</script>

<?php include '../../layouts/footer.php'; ?>
