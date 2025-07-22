<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';
$page_title = 'Business Reports';

// Get date range for reports
$dateFrom = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$dateTo = $_GET['date_to'] ?? date('Y-m-d'); // Today

// Sales Summary
$salesQuery = $conn->query("
    SELECT 
        COUNT(*) as total_invoices,
        SUM(total_amount) as total_revenue,
        AVG(total_amount) as avg_invoice_value,
        MAX(total_amount) as highest_invoice
    FROM invoices 
    WHERE invoice_date BETWEEN '$dateFrom' AND '$dateTo'
");
$salesData = $salesQuery->fetch_assoc();

// Expense Summary
$expenseQuery = $conn->query("
    SELECT 
        COUNT(*) as total_expenses,
        SUM(amount) as total_amount,
        AVG(amount) as avg_expense
    FROM expenses 
    WHERE expense_date BETWEEN '$dateFrom' AND '$dateTo'
");
$expenseData = $expenseQuery->fetch_assoc();

// Employee Summary
$employeeQuery = $conn->query("
    SELECT 
        COUNT(*) as total_employees,
        SUM(monthly_salary) as total_salary_cost,
        AVG(monthly_salary) as avg_salary
    FROM employees
");
$employeeData = $employeeQuery->fetch_assoc();

// Top Products
$topProductsQuery = $conn->query("
    SELECT 
        ii.item_name,
        COUNT(ii.id) as times_sold,
        SUM(ii.quantity) as total_quantity,
        SUM(ii.quantity * ii.price) as total_revenue
    FROM invoice_items ii
    JOIN invoices i ON ii.invoice_id = i.id
    WHERE i.invoice_date BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY ii.item_name
    ORDER BY total_revenue DESC
    LIMIT 10
");

// Monthly Trends
$monthlyTrendsQuery = $conn->query("
    SELECT 
        DATE_FORMAT(invoice_date, '%Y-%m') as month,
        COUNT(*) as invoice_count,
        SUM(total_amount) as revenue
    FROM invoices 
    WHERE invoice_date >= DATE_SUB('$dateTo', INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(invoice_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
");

// Calculate profit (Revenue - Expenses)
$profit = ($salesData['total_revenue'] ?? 0) - ($expenseData['total_amount'] ?? 0);

include 'layouts/header.php';
include 'layouts/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Business Reports</h1>
            <p class="text-muted">Comprehensive business analytics and insights</p>
        </div>
        <div>
            <button class="btn btn-outline-success" onclick="exportReport()">
                <i class="bi bi-download"></i> Export Report
            </button>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-calendar-range me-2"></i>Report Period</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" name="date_from" class="form-control" value="<?= $dateFrom ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" name="date_to" class="form-control" value="<?= $dateTo ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary d-block">
                        <i class="bi bi-search"></i> Generate Report
                    </button>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="dropdown d-block">
                        <button class="btn btn-outline-secondary dropdown-toggle w-100" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-calendar"></i> Quick Periods
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="?date_from=<?= date('Y-m-d') ?>&date_to=<?= date('Y-m-d') ?>">Today</a></li>
                            <li><a class="dropdown-item" href="?date_from=<?= date('Y-m-01') ?>&date_to=<?= date('Y-m-d') ?>">This Month</a></li>
                            <li><a class="dropdown-item" href="?date_from=<?= date('Y-01-01') ?>&date_to=<?= date('Y-m-d') ?>">This Year</a></li>
                            <li><a class="dropdown-item" href="?date_from=<?= date('Y-m-01', strtotime('-1 month')) ?>&date_to=<?= date('Y-m-t', strtotime('-1 month')) ?>">Last Month</a></li>
                        </ul>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Key Metrics -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Revenue</h6>
                            <h3 class="mb-0">₹<?= number_format($salesData['total_revenue'] ?? 0, 2) ?></h3>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-currency-rupee"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Expenses</h6>
                            <h3 class="mb-0">₹<?= number_format($expenseData['total_amount'] ?? 0, 2) ?></h3>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Net Profit</h6>
                            <h3 class="mb-0">₹<?= number_format($profit, 2) ?></h3>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-graph-up"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Invoices</h6>
                            <h3 class="mb-0"><?= $salesData['total_invoices'] ?? 0 ?></h3>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-receipt"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Reports -->
    <div class="row">
        <!-- Sales Analysis -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Sales Analysis</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <h6 class="mb-1">Average Invoice</h6>
                                <h4 class="text-primary mb-0">₹<?= number_format($salesData['avg_invoice_value'] ?? 0, 2) ?></h4>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <h6 class="mb-1">Highest Invoice</h6>
                                <h4 class="text-success mb-0">₹<?= number_format($salesData['highest_invoice'] ?? 0, 2) ?></h4>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Total Invoices:</span>
                        <strong><?= $salesData['total_invoices'] ?? 0 ?></strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Revenue:</span>
                        <strong class="text-success">₹<?= number_format($salesData['total_revenue'] ?? 0, 2) ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expense Analysis -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Expense Analysis</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <h6 class="mb-1">Total Expenses</h6>
                                <h4 class="text-danger mb-0">₹<?= number_format($expenseData['total_amount'] ?? 0, 2) ?></h4>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <h6 class="mb-1">Average Expense</h6>
                                <h4 class="text-warning mb-0">₹<?= number_format($expenseData['avg_expense'] ?? 0, 2) ?></h4>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Total Entries:</span>
                        <strong><?= $expenseData['total_expenses'] ?? 0 ?></strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Total Amount:</span>
                        <strong class="text-danger">₹<?= number_format($expenseData['total_amount'] ?? 0, 2) ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Employee Costs -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-people me-2"></i>Employee Costs</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <h6 class="mb-1">Total Employees</h6>
                                <h4 class="text-info mb-0"><?= $employeeData['total_employees'] ?? 0 ?></h4>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <h6 class="mb-1">Monthly Salary</h6>
                                <h4 class="text-primary mb-0">₹<?= number_format($employeeData['total_salary_cost'] ?? 0, 0) ?></h4>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Average Salary:</span>
                        <strong>₹<?= number_format($employeeData['avg_salary'] ?? 0, 2) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Annual Cost:</span>
                        <strong class="text-warning">₹<?= number_format(($employeeData['total_salary_cost'] ?? 0) * 12, 0) ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Products -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-trophy me-2"></i>Top Products</h5>
                </div>
                <div class="card-body">
                    <?php if ($topProductsQuery && mysqli_num_rows($topProductsQuery) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Sold</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($product = $topProductsQuery->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($product['item_name']) ?></td>
                                            <td><?= $product['total_quantity'] ?></td>
                                            <td class="text-success">₹<?= number_format($product['total_revenue'], 2) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-3">No product sales data available for this period.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Trends -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Monthly Revenue Trends</h5>
        </div>
        <div class="card-body">
            <?php if ($monthlyTrendsQuery && mysqli_num_rows($monthlyTrendsQuery) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Invoices</th>
                                <th>Revenue</th>
                                <th>Avg. Invoice Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($trend = $monthlyTrendsQuery->fetch_assoc()): ?>
                                <tr>
                                    <td><?= date('F Y', strtotime($trend['month'] . '-01')) ?></td>
                                    <td><?= $trend['invoice_count'] ?></td>
                                    <td class="text-success">₹<?= number_format($trend['revenue'], 2) ?></td>
                                    <td>₹<?= number_format($trend['revenue'] / $trend['invoice_count'], 2) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted text-center py-3">No monthly trends data available.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function exportReport() {
    const params = new URLSearchParams(window.location.search);
    window.open('export_business_report.php?' + params.toString());
}

// Initialize tooltips
$(document).ready(function() {
    $('[data-bs-toggle="tooltip"]').tooltip();
});
</script>

<?php include 'layouts/footer.php'; ?>