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
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="h5 mb-0">📊 Business Reports</h1>
                <p class="text-muted small">Comprehensive business analytics and insights</p>
            </div>
            <div>
                <button class="btn btn-outline-success btn-sm" onclick="exportReport()">
                    <i class="bi bi-download"></i> Export Report
                </button>
            </div>
        </div>

        <!-- Date Range Filter -->
        <div class="card mb-3 border-0 shadow-sm">
            <div class="card-header bg-light border-0 py-2">
                <h6 class="mb-0 text-dark"><i class="bi bi-calendar-range me-2"></i>Report Period</h6>
            </div>
            <div class="card-body p-3">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small">From Date</label>
                        <input type="date" name="date_from" class="form-control form-control-sm" value="<?= $dateFrom ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">To Date</label>
                        <input type="date" name="date_to" class="form-control form-control-sm" value="<?= $dateTo ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary btn-sm d-block">
                            <i class="bi bi-search"></i> Generate Report
                        </button>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="dropdown d-block">
                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle w-100" type="button" data-bs-toggle="dropdown">
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
        <div class="row g-2 mb-3">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-1 text-white small">Total Revenue</h6>
                                <h4 class="mb-0 fw-bold" style="color: #ffeb3b;">₹<?= number_format($salesData['total_revenue'] ?? 0, 2) ?></h4>
                                <small class="text-white-50"><?= $salesData['total_invoices'] ?? 0 ?> invoices</small>
                            </div>
                            <div class="fs-2 text-white-50">
                                <i class="bi bi-currency-rupee"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-1 text-white small">Total Expenses</h6>
                                <h4 class="mb-0 fw-bold" style="color: #4fc3f7;">₹<?= number_format($expenseData['total_amount'] ?? 0, 2) ?></h4>
                                <small class="text-white-50"><?= $expenseData['total_expenses'] ?? 0 ?> entries</small>
                            </div>
                            <div class="fs-2 text-white-50">
                                <i class="bi bi-cash-stack"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-1 text-white small">Net Profit</h6>
                                <h4 class="mb-0 fw-bold" style="color: #ffeb3b;">₹<?= number_format($profit, 2) ?></h4>
                                <small class="text-white-50"><?= $profit >= 0 ? 'Profit' : 'Loss' ?></small>
                            </div>
                            <div class="fs-2 text-white-50">
                                <i class="bi bi-graph-<?= $profit >= 0 ? 'up' : 'down' ?>"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-1 text-white small">Total Invoices</h6>
                                <h4 class="mb-0 fw-bold" style="color: #2196f3;"><?= $salesData['total_invoices'] ?? 0 ?></h4>
                                <small class="text-white-50">Invoice count</small>
                            </div>
                            <div class="fs-2 text-white-50">
                                <i class="bi bi-receipt"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Reports -->
        <div class="row g-3 mb-3">
            <!-- Sales Analysis -->
            <div class="col-lg-6 col-md-12">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-header bg-light border-0 py-2">
                        <h6 class="mb-0 text-dark"><i class="bi bi-bar-chart me-2"></i>Sales Analysis</h6>
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="text-center p-2 bg-primary bg-opacity-10 rounded">
                                    <h6 class="mb-1 small">Average Invoice</h6>
                                    <h5 class="text-primary mb-0 fw-bold">₹<?= number_format($salesData['avg_invoice_value'] ?? 0, 2) ?></h5>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center p-2 bg-success bg-opacity-10 rounded">
                                    <h6 class="mb-1 small">Highest Invoice</h6>
                                    <h5 class="text-success mb-0 fw-bold">₹<?= number_format($salesData['highest_invoice'] ?? 0, 2) ?></h5>
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
            <div class="col-lg-6 col-md-12">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-header bg-light border-0 py-2">
                        <h6 class="mb-0 text-dark"><i class="bi bi-pie-chart me-2"></i>Expense Analysis</h6>
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="text-center p-2 bg-danger bg-opacity-10 rounded">
                                    <h6 class="mb-1 small">Total Expenses</h6>
                                    <h5 class="text-danger mb-0 fw-bold">₹<?= number_format($expenseData['total_amount'] ?? 0, 2) ?></h5>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center p-2 bg-warning bg-opacity-10 rounded">
                                    <h6 class="mb-1 small">Average Expense</h6>
                                    <h5 class="text-warning mb-0 fw-bold">₹<?= number_format($expenseData['avg_expense'] ?? 0, 2) ?></h5>
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

        </div>
        
        <div class="row g-3 mb-3">
            <!-- Employee Costs -->
            <div class="col-lg-6 col-md-12">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-header bg-light border-0 py-2">
                        <h6 class="mb-0 text-dark"><i class="bi bi-people me-2"></i>Employee Costs</h6>
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="text-center p-2 bg-info bg-opacity-10 rounded">
                                    <h6 class="mb-1 small">Total Employees</h6>
                                    <h5 class="text-info mb-0 fw-bold"><?= $employeeData['total_employees'] ?? 0 ?></h5>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center p-2 bg-primary bg-opacity-10 rounded">
                                    <h6 class="mb-1 small">Monthly Salary</h6>
                                    <h5 class="text-primary mb-0 fw-bold">₹<?= number_format($employeeData['total_salary_cost'] ?? 0, 0) ?></h5>
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
            <div class="col-lg-6 col-md-12">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-header bg-light border-0 py-2">
                        <h6 class="mb-0 text-dark"><i class="bi bi-trophy me-2"></i>Top Products</h6>
                    </div>
                    <div class="card-body p-3">
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
                            <p class="text-muted text-center py-3 small">No product sales data available for this period.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Trends -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light border-0 py-2">
                <h6 class="mb-0 text-dark"><i class="bi bi-graph-up me-2"></i>Monthly Revenue Trends</h6>
            </div>
            <div class="card-body p-3">
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
                    <p class="text-muted text-center py-3 small">No monthly trends data available.</p>
                <?php endif; ?>
            </div>
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

<style>
/* Enhanced Reports Styling */
.main-content {
    padding: 10px;
}

.container-fluid {
    max-width: 100%;
    padding: 0 10px;
}

/* Responsive Grid Enhancements */
@media (max-width: 1200px) {
    .col-xl-3 {
        flex: 0 0 50%;
        max-width: 50%;
    }
}

@media (max-width: 768px) {
    .col-xl-3, .col-lg-6 {
        flex: 0 0 100%;
        max-width: 100%;
    }
    
    .main-content {
        padding: 5px;
    }
    
    .container-fluid {
        padding: 0 5px;
    }
    
    .row {
        margin: 0 -5px;
    }
    
    .row > * {
        padding: 0 5px;
    }
}

/* Card Hover Effects */
.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
}

/* Flexible Content Areas */
.card-body {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

/* Table Enhancements */
.table th {
    font-size: 0.875rem !important;
    font-weight: 600 !important;
    border-bottom: 2px solid var(--primary-color) !important;
    background-color: var(--gray-100) !important;
    color: var(--gray-800) !important;
}

.table td {
    font-size: 0.875rem;
    vertical-align: middle;
    color: var(--gray-800) !important;
}

/* Responsive Table */
@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.875rem;
    }
}

/* Form Control Sizing */
.form-control-sm {
    font-size: 0.875rem;
}

.form-label.small {
    font-size: 0.875rem;
    font-weight: 500;
    color: #495057;
}

/* Dropdown Menu */
.dropdown-menu {
    font-size: 0.875rem;
}

/* Empty State Styling */
.text-center.py-3 {
    padding: 2rem 0 !important;
}

.text-center.py-3.small {
    font-size: 0.875rem;
}
</style>

<?php include 'layouts/footer.php'; ?>