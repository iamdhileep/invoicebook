<?php
/**
 * Supplier Reports and Analytics
 */
session_start();
require_once '../../db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit;
}

// Get date range for reports
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Today
$supplier_filter = $_GET['supplier'] ?? '';
$report_type = $_GET['report_type'] ?? 'overview';

// Ensure dates are properly formatted
$date_from = mysqli_real_escape_string($conn, $date_from);
$date_to = mysqli_real_escape_string($conn, $date_to);

// Get suppliers for dropdown
$suppliers_query = "SELECT id, supplier_name FROM suppliers WHERE status = 'active' ORDER BY supplier_name ASC";
$suppliers = mysqli_query($conn, $suppliers_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Reports - BillBook</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .report-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
        }
        .table-container {
            max-height: 500px;
            overflow-y: auto;
        }
        .export-btn {
            border-radius: 20px;
        }
    </style>
</head>

<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="../../dashboard.php">
                <i class="fas fa-book me-2"></i>BillBook
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="suppliers.php">
                    <i class="fas fa-truck me-1"></i>Suppliers
                </a>
                <a class="nav-link" href="purchase_orders.php">
                    <i class="fas fa-shopping-cart me-1"></i>Purchase Orders
                </a>
                <a class="nav-link" href="supplier_payments.php">
                    <i class="fas fa-money-bill-wave me-1"></i>Payments
                </a>
                <a class="nav-link active" href="supplier_reports.php">
                    <i class="fas fa-chart-bar me-1"></i>Reports
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h2><i class="fas fa-chart-bar me-2"></i>Supplier Reports & Analytics</h2>
            </div>
            <div class="col-md-6 text-end">
                <button class="btn btn-success export-btn" onclick="exportReport()">
                    <i class="fas fa-download me-2"></i>Export Report
                </button>
                <button class="btn btn-primary export-btn" onclick="printReport()">
                    <i class="fas fa-print me-2"></i>Print Report
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="card report-card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">Report Type</label>
                        <select name="report_type" class="form-select">
                            <option value="overview" <?php echo $report_type == 'overview' ? 'selected' : ''; ?>>Overview</option>
                            <option value="performance" <?php echo $report_type == 'performance' ? 'selected' : ''; ?>>Performance</option>
                            <option value="payments" <?php echo $report_type == 'payments' ? 'selected' : ''; ?>>Payments</option>
                            <option value="purchases" <?php echo $report_type == 'purchases' ? 'selected' : ''; ?>>Purchases</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Supplier</label>
                        <select name="supplier" class="form-select">
                            <option value="">All Suppliers</option>
                            <?php
                            while ($supplier = mysqli_fetch_assoc($suppliers)) {
                                $selected = $supplier_filter == $supplier['id'] ? 'selected' : '';
                                echo "<option value='{$supplier['id']}' $selected>{$supplier['supplier_name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>Generate Report
                            </button>
                        </div>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="button" class="btn btn-outline-secondary" onclick="resetFilters()">
                                <i class="fas fa-refresh"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($report_type == 'overview'): ?>
            <!-- Overview Report -->
            <?php
            // Get overview statistics
            $supplier_condition = !empty($supplier_filter) ? "AND s.id = " . intval($supplier_filter) : "";
            
            $overview_query = "
                SELECT 
                    COUNT(DISTINCT s.id) as total_suppliers,
                    COUNT(DISTINCT po.id) as total_orders,
                    COALESCE(SUM(po.total_amount), 0) as total_purchase_value,
                    COALESCE(SUM(sp.amount), 0) as total_payments,
                    AVG(s.rating) as avg_rating,
                    COUNT(DISTINCT CASE WHEN po.status = 'received' THEN po.id END) as completed_orders,
                    COUNT(DISTINCT CASE WHEN po.status = 'pending' THEN po.id END) as pending_orders
                FROM suppliers s
                LEFT JOIN purchase_orders po ON s.id = po.supplier_id 
                    AND po.order_date BETWEEN '$date_from' AND '$date_to'
                LEFT JOIN supplier_payments sp ON s.id = sp.supplier_id 
                    AND sp.payment_date BETWEEN '$date_from' AND '$date_to'
                WHERE s.status = 'active' $supplier_condition
            ";
            
            $overview_result = mysqli_query($conn, $overview_query);
            $overview = mysqli_fetch_assoc($overview_result);
            ?>

            <!-- Key Metrics -->
            <div class="row mb-4">
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="card metric-card text-white">
                        <div class="card-body text-center">
                            <i class="fas fa-truck fa-2x mb-2"></i>
                            <h4 class="mb-1"><?php echo number_format($overview['total_suppliers']); ?></h4>
                            <small>Active Suppliers</small>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="card metric-card text-white">
                        <div class="card-body text-center">
                            <i class="fas fa-shopping-cart fa-2x mb-2"></i>
                            <h4 class="mb-1"><?php echo number_format($overview['total_orders']); ?></h4>
                            <small>Total Orders</small>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="card metric-card text-white">
                        <div class="card-body text-center">
                            <i class="fas fa-rupee-sign fa-2x mb-2"></i>
                            <h4 class="mb-1"><?php echo number_format($overview['total_purchase_value'], 0); ?></h4>
                            <small>Purchase Value</small>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="card metric-card text-white">
                        <div class="card-body text-center">
                            <i class="fas fa-money-bill-wave fa-2x mb-2"></i>
                            <h4 class="mb-1"><?php echo number_format($overview['total_payments'], 0); ?></h4>
                            <small>Payments Made</small>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="card metric-card text-white">
                        <div class="card-body text-center">
                            <i class="fas fa-check-circle fa-2x mb-2"></i>
                            <h4 class="mb-1"><?php echo number_format($overview['completed_orders']); ?></h4>
                            <small>Completed</small>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="card metric-card text-white">
                        <div class="card-body text-center">
                            <i class="fas fa-star fa-2x mb-2"></i>
                            <h4 class="mb-1"><?php echo number_format($overview['avg_rating'], 1); ?></h4>
                            <small>Avg Rating</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row">
                <!-- Top Suppliers by Value -->
                <div class="col-md-6 mb-4">
                    <div class="card report-card">
                        <div class="card-header">
                            <h5><i class="fas fa-trophy me-2"></i>Top Suppliers by Purchase Value</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="topSuppliersChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly Trends -->
                <div class="col-md-6 mb-4">
                    <div class="card report-card">
                        <div class="card-header">
                            <h5><i class="fas fa-chart-line me-2"></i>Purchase Trends</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="trendsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Suppliers Table -->
            <div class="card report-card">
                <div class="card-header">
                    <h5><i class="fas fa-table me-2"></i>Supplier Performance Summary</h5>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Supplier</th>
                                    <th>Total Orders</th>
                                    <th>Purchase Value</th>
                                    <th>Payments Made</th>
                                    <th>Outstanding</th>
                                    <th>Rating</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $supplier_details_query = "
                                    SELECT 
                                        s.supplier_name,
                                        s.rating,
                                        s.status,
                                        COUNT(DISTINCT po.id) as order_count,
                                        COALESCE(SUM(po.total_amount), 0) as purchase_value,
                                        COALESCE(SUM(sp.amount), 0) as payments_made,
                                        (COALESCE(SUM(po.total_amount), 0) - COALESCE(SUM(sp.amount), 0)) as outstanding
                                    FROM suppliers s
                                    LEFT JOIN purchase_orders po ON s.id = po.supplier_id 
                                        AND po.order_date BETWEEN '$date_from' AND '$date_to'
                                    LEFT JOIN supplier_payments sp ON s.id = sp.supplier_id 
                                        AND sp.payment_date BETWEEN '$date_from' AND '$date_to'
                                    WHERE s.status = 'active' $supplier_condition
                                    GROUP BY s.id, s.supplier_name, s.rating, s.status
                                    ORDER BY purchase_value DESC
                                    LIMIT 20
                                ";
                                
                                $supplier_details = mysqli_query($conn, $supplier_details_query);
                                
                                while ($supplier = mysqli_fetch_assoc($supplier_details)) {
                                    $outstanding_class = $supplier['outstanding'] > 0 ? 'text-danger' : 'text-success';
                                    echo "<tr>";
                                    echo "<td><strong>" . htmlspecialchars($supplier['supplier_name']) . "</strong></td>";
                                    echo "<td>" . number_format($supplier['order_count']) . "</td>";
                                    echo "<td>₹" . number_format($supplier['purchase_value'], 2) . "</td>";
                                    echo "<td>₹" . number_format($supplier['payments_made'], 2) . "</td>";
                                    echo "<td class='$outstanding_class'>₹" . number_format($supplier['outstanding'], 2) . "</td>";
                                    echo "<td>";
                                    for ($i = 1; $i <= 5; $i++) {
                                        $star_class = $i <= $supplier['rating'] ? 'text-warning' : 'text-muted';
                                        echo "<i class='fas fa-star $star_class'></i>";
                                    }
                                    echo "</td>";
                                    echo "<td><span class='badge bg-success'>" . ucfirst($supplier['status']) . "</span></td>";
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($report_type == 'performance'): ?>
            <!-- Performance Report -->
            <div class="row">
                <div class="col-12">
                    <div class="card report-card">
                        <div class="card-header">
                            <h5><i class="fas fa-tachometer-alt me-2"></i>Supplier Performance Analysis</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="chart-container">
                                        <canvas id="performanceChart"></canvas>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6>Performance Metrics</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Metric</th>
                                                    <th>Value</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>On-time Delivery</td>
                                                    <td>87%</td>
                                                    <td><span class="badge bg-success">Good</span></td>
                                                </tr>
                                                <tr>
                                                    <td>Order Accuracy</td>
                                                    <td>94%</td>
                                                    <td><span class="badge bg-success">Excellent</span></td>
                                                </tr>
                                                <tr>
                                                    <td>Response Time</td>
                                                    <td>2.3 days</td>
                                                    <td><span class="badge bg-warning">Average</span></td>
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

        <?php endif; ?>
    </div>

    <!-- Get data for charts -->
    <?php
    // Get top suppliers data for charts
    $top_suppliers_query = "
        SELECT 
            s.supplier_name,
            COALESCE(SUM(po.total_amount), 0) as total_value
        FROM suppliers s
        LEFT JOIN purchase_orders po ON s.id = po.supplier_id 
            AND po.order_date BETWEEN '$date_from' AND '$date_to'
        WHERE s.status = 'active' $supplier_condition
        GROUP BY s.id, s.supplier_name
        HAVING total_value > 0
        ORDER BY total_value DESC
        LIMIT 5
    ";
    
    $top_suppliers_result = mysqli_query($conn, $top_suppliers_query);
    $top_suppliers_data = [];
    while ($row = mysqli_fetch_assoc($top_suppliers_result)) {
        $top_suppliers_data[] = $row;
    }
    
    // Get monthly trends data
    $trends_query = "
        SELECT 
            DATE_FORMAT(po.order_date, '%Y-%m') as month,
            SUM(po.total_amount) as total_amount
        FROM purchase_orders po
        LEFT JOIN suppliers s ON po.supplier_id = s.id
        WHERE po.order_date BETWEEN DATE_SUB('$date_to', INTERVAL 11 MONTH) AND '$date_to'
        AND s.status = 'active' $supplier_condition
        GROUP BY DATE_FORMAT(po.order_date, '%Y-%m')
        ORDER BY month ASC
    ";
    
    $trends_result = mysqli_query($conn, $trends_query);
    $trends_data = [];
    while ($row = mysqli_fetch_assoc($trends_result)) {
        $trends_data[] = $row;
    }
    ?>

    <script>
        // Top Suppliers Chart
        const topSuppliersCtx = document.getElementById('topSuppliersChart');
        if (topSuppliersCtx) {
            new Chart(topSuppliersCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_column($top_suppliers_data, 'supplier_name')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($top_suppliers_data, 'total_value')); ?>,
                        backgroundColor: [
                            '#FF6384',
                            '#36A2EB',
                            '#FFCE56',
                            '#4BC0C0',
                            '#9966FF'
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
        }

        // Trends Chart
        const trendsCtx = document.getElementById('trendsChart');
        if (trendsCtx) {
            new Chart(trendsCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($trends_data, 'month')); ?>,
                    datasets: [{
                        label: 'Purchase Value',
                        data: <?php echo json_encode(array_column($trends_data, 'total_amount')); ?>,
                        borderColor: '#36A2EB',
                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                        fill: true
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
        }

        // Performance Chart
        const performanceCtx = document.getElementById('performanceChart');
        if (performanceCtx) {
            new Chart(performanceCtx, {
                type: 'radar',
                data: {
                    labels: ['On-time Delivery', 'Order Accuracy', 'Response Time', 'Quality', 'Communication'],
                    datasets: [{
                        label: 'Performance Score',
                        data: [87, 94, 76, 89, 82],
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderColor: '#36A2EB',
                        pointBackgroundColor: '#36A2EB'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        r: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        }

        // Export Report
        function exportReport() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.open(`supplier_export.php?${params.toString()}`, '_blank');
        }

        // Print Report
        function printReport() {
            window.print();
        }

        // Reset Filters
        function resetFilters() {
            window.location.href = 'supplier_reports.php';
        }
    </script>
</body>
</html>
