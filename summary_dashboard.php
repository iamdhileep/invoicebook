<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';
$page_title = 'Summary by Date Range';

// Get date range parameters
$dateFrom = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$dateTo = $_GET['date_to'] ?? date('Y-m-d'); // Today
$period = $_GET['period'] ?? 'custom';

// Set date ranges based on period
switch ($period) {
    case 'today':
        $dateFrom = $dateTo = date('Y-m-d');
        break;
    case 'yesterday':
        $dateFrom = $dateTo = date('Y-m-d', strtotime('-1 day'));
        break;
    case 'this_week':
        $dateFrom = date('Y-m-d', strtotime('monday this week'));
        $dateTo = date('Y-m-d');
        break;
    case 'last_week':
        $dateFrom = date('Y-m-d', strtotime('monday last week'));
        $dateTo = date('Y-m-d', strtotime('sunday last week'));
        break;
    case 'this_month':
        $dateFrom = date('Y-m-01');
        $dateTo = date('Y-m-d');
        break;
    case 'last_month':
        $dateFrom = date('Y-m-01', strtotime('first day of last month'));
        $dateTo = date('Y-m-t', strtotime('last day of last month'));
        break;
    case 'this_quarter':
        $quarter = ceil(date('n') / 3);
        $year = date('Y');
        $dateFrom = date('Y-m-01', mktime(0, 0, 0, ($quarter - 1) * 3 + 1, 1, $year));
        $dateTo = date('Y-m-d');
        break;
    case 'this_year':
        $dateFrom = date('Y-01-01');
        $dateTo = date('Y-m-d');
        break;
    case 'last_year':
        $dateFrom = date('Y-01-01', strtotime('last year'));
        $dateTo = date('Y-12-31', strtotime('last year'));
        break;
}

// Revenue Summary
$revenueQuery = $conn->prepare("
    SELECT 
        COUNT(*) as total_invoices,
        COALESCE(SUM(total_amount), 0) as total_revenue,
        COALESCE(AVG(total_amount), 0) as avg_invoice_value,
        COALESCE(MAX(total_amount), 0) as highest_invoice,
        COALESCE(MIN(total_amount), 0) as lowest_invoice
    FROM invoices 
    WHERE invoice_date BETWEEN ? AND ?
");

if ($revenueQuery) {
    $revenueQuery->bind_param("ss", $dateFrom, $dateTo);
    $revenueQuery->execute();
    $revenueData = $revenueQuery->get_result()->fetch_assoc();
} else {
    // Fallback data if query fails
    $revenueData = [
        'total_invoices' => 0,
        'total_revenue' => 0,
        'avg_invoice_value' => 0,
        'highest_invoice' => 0,
        'lowest_invoice' => 0
    ];
    error_log("Revenue query failed: " . $conn->error);
}

// Expense Summary
$expenseQuery = $conn->prepare("
    SELECT 
        COUNT(*) as total_expenses,
        COALESCE(SUM(amount), 0) as total_amount,
        COALESCE(AVG(amount), 0) as avg_expense,
        COALESCE(MAX(amount), 0) as highest_expense
    FROM expenses 
    WHERE expense_date BETWEEN ? AND ?
");

if ($expenseQuery) {
    $expenseQuery->bind_param("ss", $dateFrom, $dateTo);
    $expenseQuery->execute();
    $expenseData = $expenseQuery->get_result()->fetch_assoc();
} else {
    // Fallback data if query fails
    $expenseData = [
        'total_expenses' => 0,
        'total_amount' => 0,
        'avg_expense' => 0,
        'highest_expense' => 0
    ];
    error_log("Expense query failed: " . $conn->error);
}

// Employee & Attendance Summary
$employeeQuery = $conn->prepare("
    SELECT 
        COUNT(DISTINCT e.id) as total_employees,
        COALESCE(SUM(e.monthly_salary), 0) as total_salary_cost,
        COALESCE(AVG(e.monthly_salary), 0) as avg_salary,
        COUNT(DISTINCT a.employee_id) as employees_with_attendance,
        COALESCE(SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END), 0) as total_present_days,
        COALESCE(SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END), 0) as total_absent_days,
        COALESCE(SUM(a.work_hours), 0) as total_work_hours,
        COALESCE(SUM(a.overtime_hours), 0) as total_overtime_hours
    FROM employees e
    LEFT JOIN attendance a ON e.id = a.employee_id 
        AND a.date BETWEEN ? AND ?
    WHERE e.status = 'active'
");

if ($employeeQuery) {
    $employeeQuery->bind_param("ss", $dateFrom, $dateTo);
    $employeeQuery->execute();
    $employeeData = $employeeQuery->get_result()->fetch_assoc();
} else {
    // Fallback data if query fails
    $employeeData = [
        'total_employees' => 0,
        'total_salary_cost' => 0,
        'avg_salary' => 0,
        'employees_with_attendance' => 0,
        'total_present_days' => 0,
        'total_absent_days' => 0,
        'total_work_hours' => 0,
        'total_overtime_hours' => 0
    ];
    error_log("Employee query failed: " . $conn->error);
}

// Items & Inventory Summary
$itemsQuery = $conn->prepare("
    SELECT 
        COUNT(*) as total_items,
        COALESCE(SUM(stock), 0) as total_stock,
        COALESCE(SUM(item_price * stock), 0) as total_inventory_value,
        COALESCE(AVG(item_price), 0) as avg_item_price,
        COUNT(CASE WHEN stock <= 5 THEN 1 END) as low_stock_items,
        COUNT(CASE WHEN stock = 0 THEN 1 END) as out_of_stock_items
    FROM items 
    WHERE status = 'active'
");

if ($itemsQuery) {
    $itemsQuery->execute();
    $itemsData = $itemsQuery->get_result()->fetch_assoc();
} else {
    // Fallback data if query fails
    $itemsData = [
        'total_items' => 0,
        'total_stock' => 0,
        'total_inventory_value' => 0,
        'avg_item_price' => 0,
        'low_stock_items' => 0,
        'out_of_stock_items' => 0
    ];
    error_log("Items query failed: " . $conn->error);
}

// Calculate key metrics
$profit = $revenueData['total_revenue'] - $expenseData['total_amount'];
$profitMargin = $revenueData['total_revenue'] > 0 ? ($profit / $revenueData['total_revenue']) * 100 : 0;
$attendanceRate = ($employeeData['total_present_days'] + $employeeData['total_absent_days']) > 0 ? 
    ($employeeData['total_present_days'] / ($employeeData['total_present_days'] + $employeeData['total_absent_days'])) * 100 : 0;

// Top Categories by Revenue
$topCategoriesQuery = $conn->prepare("
    SELECT 
        i.category,
        COUNT(ii.id) as items_sold,
        COALESCE(SUM(ii.quantity), 0) as total_quantity,
        COALESCE(SUM(ii.line_total), 0) as total_revenue
    FROM invoice_items ii
    JOIN invoices inv ON ii.invoice_id = inv.id
    JOIN items i ON ii.item_name = i.item_name
    WHERE inv.invoice_date BETWEEN ? AND ?
    GROUP BY i.category
    ORDER BY total_revenue DESC
    LIMIT 10
");

if ($topCategoriesQuery) {
    $topCategoriesQuery->bind_param("ss", $dateFrom, $dateTo);
    $topCategoriesQuery->execute();
    $topCategories = $topCategoriesQuery->get_result();
} else {
    // Create empty result set if query fails
    $topCategories = new stdClass();
    $topCategories->num_rows = 0;
    error_log("Top categories query failed: " . $conn->error);
}

// Daily Trends
$dailyTrendsQuery = $conn->prepare("
    SELECT 
        DATE(invoice_date) as date,
        COUNT(*) as invoice_count,
        COALESCE(SUM(total_amount), 0) as revenue,
        (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE DATE(expense_date) = DATE(invoice_date)) as expenses
    FROM invoices 
    WHERE invoice_date BETWEEN ? AND ?
    GROUP BY DATE(invoice_date)
    ORDER BY date ASC
");

if ($dailyTrendsQuery) {
    $dailyTrendsQuery->bind_param("ss", $dateFrom, $dateTo);
    $dailyTrendsQuery->execute();
    $dailyTrends = $dailyTrendsQuery->get_result();
} else {
    // Create empty result set if query fails
    $dailyTrends = new stdClass();
    $dailyTrends->num_rows = 0;
    error_log("Daily trends query failed: " . $conn->error);
}

// Recent Transactions
$recentTransactionsQuery = $conn->prepare("
    (SELECT 'invoice' as type, id, customer_name as description, total_amount as amount, invoice_date as date
     FROM invoices WHERE invoice_date BETWEEN ? AND ? ORDER BY invoice_date DESC LIMIT 5)
    UNION ALL
    (SELECT 'expense' as type, id, title as description, amount, expense_date as date
     FROM expenses WHERE expense_date BETWEEN ? AND ? ORDER BY expense_date DESC LIMIT 5)
    ORDER BY date DESC
    LIMIT 10
");

if ($recentTransactionsQuery) {
    $recentTransactionsQuery->bind_param("ssss", $dateFrom, $dateTo, $dateFrom, $dateTo);
    $recentTransactionsQuery->execute();
    $recentTransactions = $recentTransactionsQuery->get_result();
} else {
    // Create empty result set if query fails
    $recentTransactions = new stdClass();
    $recentTransactions->num_rows = 0;
    error_log("Recent transactions query failed: " . $conn->error);
}

include 'layouts/header.php';
?>

<div class="main-content">
    <?php include 'layouts/sidebar.php'; ?>
    
    <div class="content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <h1 class="h5 mb-0">📊 Summary by Date Range</h1>
                    <p class="text-muted small mb-0">Comprehensive business analytics from <?= date('M d, Y', strtotime($dateFrom)) ?> to <?= date('M d, Y', strtotime($dateTo)) ?></p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-success btn-sm" onclick="exportSummary()">
                        <i class="bi bi-download"></i> Export Summary
                    </button>
                    <button class="btn btn-outline-primary btn-sm" onclick="printSummary()">
                        <i class="bi bi-printer"></i> Print
                    </button>
                </div>
            </div>

            <!-- Date Range Filter -->
            <div class="card mb-2 border-0 shadow-sm">
                <div class="card-header bg-light border-0 py-2">
                    <h6 class="mb-0 text-dark"><i class="bi bi-calendar-range me-2"></i>Select Date Range</h6>
                </div>
                <div class="card-body p-3">
                    <form method="GET" class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label">Quick Periods</label>
                            <select name="period" class="form-select" onchange="toggleCustomDates(this.value)">
                                <option value="custom" <?= $period === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                                <option value="today" <?= $period === 'today' ? 'selected' : '' ?>>Today</option>
                                <option value="yesterday" <?= $period === 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
                                <option value="this_week" <?= $period === 'this_week' ? 'selected' : '' ?>>This Week</option>
                                <option value="last_week" <?= $period === 'last_week' ? 'selected' : '' ?>>Last Week</option>
                                <option value="this_month" <?= $period === 'this_month' ? 'selected' : '' ?>>This Month</option>
                                <option value="last_month" <?= $period === 'last_month' ? 'selected' : '' ?>>Last Month</option>
                                <option value="this_quarter" <?= $period === 'this_quarter' ? 'selected' : '' ?>>This Quarter</option>
                                <option value="this_year" <?= $period === 'this_year' ? 'selected' : '' ?>>This Year</option>
                                <option value="last_year" <?= $period === 'last_year' ? 'selected' : '' ?>>Last Year</option>
                            </select>
                        </div>
                        <div class="col-md-3" id="fromDateDiv">
                            <label class="form-label">From Date</label>
                            <input type="date" name="date_from" class="form-control" value="<?= $dateFrom ?>">
                        </div>
                        <div class="col-md-3" id="toDateDiv">
                            <label class="form-label">To Date</label>
                            <input type="date" name="date_to" class="form-control" value="<?= $dateTo ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary d-block w-100">
                                <i class="bi bi-search"></i> Generate Summary
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Key Performance Indicators -->
            <div class="row g-2 mb-2">
                <div class="col-xl-3 col-lg-6 col-md-6">
                    <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1 text-white small">Total Revenue</h6>
                                    <h4 class="mb-0 fw-bold" style="color: #ffeb3b;">₹<?= number_format($revenueData['total_revenue'], 2) ?></h4>
                                    <small class="text-white-50"><?= $revenueData['total_invoices'] ?> invoices</small>
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
                                    <h4 class="mb-0 fw-bold" style="color: #4fc3f7;">₹<?= number_format($expenseData['total_amount'], 2) ?></h4>
                                    <small class="text-white-50"><?= $expenseData['total_expenses'] ?> expenses</small>
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
                                    <small class="text-white-50"><?= number_format($profitMargin, 1) ?>% margin</small>
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
                                    <h6 class="card-title mb-1 text-white small">Attendance Rate</h6>
                                    <h4 class="mb-0 fw-bold" style="color: #2196f3;"><?= number_format($attendanceRate, 1) ?>%</h4>
                                    <small class="text-white-50"><?= $employeeData['total_employees'] ?> employees</small>
                                </div>
                                <div class="fs-2 text-white-50">
                                    <i class="bi bi-people"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Metrics Row -->
            <div class="row g-2 mb-2">
                <!-- Revenue Details -->
                <div class="col-lg-6 col-md-12">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-light border-0 py-2">
                            <h6 class="mb-0 text-dark"><i class="bi bi-bar-chart me-2"></i>Revenue Analysis</h6>
                        </div>
                        <div class="card-body p-3">
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="text-center p-2 bg-primary bg-opacity-10 rounded">
                                        <h5 class="text-primary mb-1 fw-bold">₹<?= number_format($revenueData['avg_invoice_value'], 2) ?></h5>
                                        <small class="text-muted">Avg Invoice Value</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center p-2 bg-success bg-opacity-10 rounded">
                                        <h5 class="text-success mb-1 fw-bold">₹<?= number_format($revenueData['highest_invoice'], 2) ?></h5>
                                        <small class="text-muted">Highest Invoice</small>
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <span>Total Invoices:</span>
                                <strong><?= $revenueData['total_invoices'] ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Lowest Invoice:</span>
                                <strong>₹<?= number_format($revenueData['lowest_invoice'], 2) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Expense Details -->
                <div class="col-lg-6 col-md-12">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-light border-0 py-2">
                            <h6 class="mb-0 text-dark"><i class="bi bi-receipt me-2"></i>Expense Analysis</h6>
                        </div>
                        <div class="card-body p-3">
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="text-center p-2 bg-warning bg-opacity-10 rounded">
                                        <h5 class="text-warning mb-1 fw-bold">₹<?= number_format($expenseData['avg_expense'], 2) ?></h5>
                                        <small class="text-muted">Avg Expense</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center p-2 bg-danger bg-opacity-10 rounded">
                                        <h5 class="text-danger mb-1 fw-bold">₹<?= number_format($expenseData['highest_expense'], 2) ?></h5>
                                        <small class="text-muted">Highest Expense</small>
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <span>Total Expenses:</span>
                                <strong><?= $expenseData['total_expenses'] ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Daily Average:</span>
                                <strong>₹<?= number_format($expenseData['total_amount'] / max(1, (strtotime($dateTo) - strtotime($dateFrom)) / 86400 + 1), 2) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts and Analytics Row -->
            <div class="row g-2 mb-2">
                <!-- Daily Trends Chart -->
                <div class="col-lg-8 col-md-12">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-light border-0 py-2">
                            <h6 class="mb-0 text-dark"><i class="bi bi-graph-up me-2"></i>Daily Revenue vs Expenses</h6>
                        </div>
                        <div class="card-body p-3">
                            <canvas id="dailyTrendsChart" height="300"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Top Categories -->
                <div class="col-lg-4 col-md-12">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-light border-0 py-2">
                            <h6 class="mb-0 text-dark"><i class="bi bi-pie-chart me-2"></i>Top Categories</h6>
                        </div>
                        <div class="card-body">
                            <?php if ($topCategories->num_rows > 0): ?>
                                <?php while ($category = $topCategories->fetch_assoc()): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <strong><?= htmlspecialchars($category['category'] ?? 'Uncategorized') ?></strong>
                                            <br><small class="text-muted"><?= $category['items_sold'] ?> items sold</small>
                                        </div>
                                        <div class="text-end">
                                            <strong>₹<?= number_format($category['total_revenue'], 2) ?></strong>
                                        </div>
                                    </div>
                                    <div class="progress mb-3" style="height: 4px;">
                                        <div class="progress-bar" style="width: <?= min(100, ($category['total_revenue'] / max(1, $revenueData['total_revenue'])) * 100) ?>%"></div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p class="text-muted text-center">No category data available for this period</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Employee & Inventory Summary -->
            <div class="row g-2 mb-2">
                <!-- Employee Summary -->
                <div class="col-lg-6 col-md-12">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-light border-0 py-2">
                            <h6 class="mb-0 text-dark"><i class="bi bi-people me-2"></i>Employee & Attendance Summary</h6>
                        </div>
                        <div class="card-body p-3">
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <div class="text-center p-2 bg-success bg-opacity-10 rounded">
                                        <h5 class="text-success mb-1 fw-bold"><?= $employeeData['total_present_days'] ?></h5>
                                        <small class="text-muted">Present Days</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center p-2 bg-danger bg-opacity-10 rounded">
                                        <h5 class="text-danger mb-1 fw-bold"><?= $employeeData['total_absent_days'] ?></h5>
                                        <small class="text-muted">Absent Days</small>
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total Employees:</span>
                                <strong><?= $employeeData['total_employees'] ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total Work Hours:</span>
                                <strong><?= number_format($employeeData['total_work_hours'], 1) ?> hrs</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Overtime Hours:</span>
                                <strong><?= number_format($employeeData['total_overtime_hours'], 1) ?> hrs</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Avg Salary:</span>
                                <strong>₹<?= number_format($employeeData['avg_salary'], 2) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Inventory Summary -->
                <div class="col-lg-6 col-md-12">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-light border-0 py-2">
                            <h6 class="mb-0 text-dark"><i class="bi bi-box-seam me-2"></i>Inventory Summary</h6>
                        </div>
                        <div class="card-body p-3">
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <div class="text-center p-2 bg-primary bg-opacity-10 rounded">
                                        <h5 class="text-primary mb-1 fw-bold"><?= $itemsData['total_items'] ?></h5>
                                        <small class="text-muted">Total Items</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center p-2 bg-info bg-opacity-10 rounded">
                                        <h5 class="text-info mb-1 fw-bold">₹<?= number_format($itemsData['total_inventory_value'], 2) ?></h5>
                                        <small class="text-muted">Inventory Value</small>
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total Stock:</span>
                                <strong><?= number_format($itemsData['total_stock']) ?> units</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Avg Item Price:</span>
                                <strong>₹<?= number_format($itemsData['avg_item_price'], 2) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-warning">Low Stock Items:</span>
                                <strong class="text-warning"><?= $itemsData['low_stock_items'] ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-danger">Out of Stock:</span>
                                <strong class="text-danger"><?= $itemsData['out_of_stock_items'] ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light border-0 py-2">
                    <h6 class="mb-0 text-dark"><i class="bi bi-clock-history me-2"></i>Recent Transactions</h6>
                </div>
                <div class="card-body">
                    <?php if ($recentTransactions->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($transaction = $recentTransactions->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= date('M d, Y', strtotime($transaction['date'])) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $transaction['type'] === 'invoice' ? 'success' : 'danger' ?>">
                                                    <i class="bi bi-<?= $transaction['type'] === 'invoice' ? 'receipt' : 'cash' ?>"></i>
                                                    <?= ucfirst($transaction['type']) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($transaction['description']) ?></td>
                                            <td class="text-end">
                                                <span class="text-<?= $transaction['type'] === 'invoice' ? 'success' : 'danger' ?>">
                                                    <?= $transaction['type'] === 'invoice' ? '+' : '-' ?>₹<?= number_format($transaction['amount'], 2) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-4">No transactions found for the selected period</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function() {
    // Initialize daily trends chart
    const ctx = document.getElementById('dailyTrendsChart').getContext('2d');
    
    // Prepare data for chart
    const chartLabels = [];
    const revenueData = [];
    const expenseData = [];
    
    <?php 
    $chartData = [];
    if ($dailyTrends && $dailyTrends->num_rows > 0) {
        $dailyTrends->data_seek(0);
        while ($trend = $dailyTrends->fetch_assoc()) {
            $chartData[] = $trend;
        }
    }
    ?>
    
    const dailyData = <?= json_encode($chartData) ?>;
    
    dailyData.forEach(function(day) {
        chartLabels.push(new Date(day.date).toLocaleDateString());
        revenueData.push(parseFloat(day.revenue));
        expenseData.push(parseFloat(day.expenses));
    });
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Revenue',
                data: revenueData,
                borderColor: '#198754',
                backgroundColor: 'rgba(25, 135, 84, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Expenses',
                data: expenseData,
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ₹' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₹' + value.toLocaleString();
                        }
                    }
                }
            },
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
            }
        }
    });
});

function toggleCustomDates(period) {
    const fromDiv = document.getElementById('fromDateDiv');
    const toDiv = document.getElementById('toDateDiv');
    
    if (period === 'custom') {
        fromDiv.style.display = 'block';
        toDiv.style.display = 'block';
    } else {
        fromDiv.style.display = 'none';
        toDiv.style.display = 'none';
    }
}

function exportSummary() {
    const params = new URLSearchParams(window.location.search);
    window.open('export_summary.php?' + params.toString(), '_blank');
}

function printSummary() {
    window.print();
}

// Initialize on page load
toggleCustomDates('<?= $period ?>');
</script>

<style>
/* Enhanced Dashboard Styling */
.main-content {
    padding: 0.5rem 0;
}

.container-fluid {
    max-width: 100%;
    padding: 0 15px;
}

/* Card Hover Effects */
.card {
    transition: all 0.3s ease;
    border-radius: 10px;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1) !important;
}

.card-header {
    border-radius: 10px 10px 0 0 !important;
}

/* Compact Spacing */
.mb-4 {
    margin-bottom: 1rem !important;
}

.mb-3 {
    margin-bottom: 0.75rem !important;
}

.mb-2 {
    margin-bottom: 0.5rem !important;
}

.p-3 {
    padding: 0.75rem !important;
}

.py-2 {
    padding-top: 0.5rem !important;
    padding-bottom: 0.5rem !important;
}

.g-2 > * {
    padding: 0.25rem;
}

/* Responsive Grid Enhancements */
@media (max-width: 1200px) {
    .col-xl-3 {
        flex: 0 0 50%;
        max-width: 50%;
    }
    
    .card-body {
        padding: 0.65rem !important;
    }
}

@media (max-width: 992px) {
    .main-content {
        padding: 0.5rem 0;
    }
    
    .container-fluid {
        padding: 0 10px;
    }
    
    .card-body {
        padding: 0.6rem !important;
    }
    
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.8rem;
    }
    
    .d-flex.gap-2 {
        gap: 0.5rem !important;
    }
}

@media (max-width: 768px) {
    .col-xl-3, .col-lg-6 {
        flex: 0 0 100%;
        max-width: 100%;
    }
    
    .main-content {
        padding: 0.25rem 0;
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
    
    .card-body {
        padding: 0.5rem !important;
    }
    
    .d-flex.justify-content-between {
        flex-direction: column;
        gap: 1rem;
    }
    
    .d-flex.justify-content-between .d-flex {
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .form-control, .form-select {
        font-size: 0.85rem;
    }
    
    .btn-sm {
        padding: 0.2rem 0.4rem;
        font-size: 0.75rem;
    }
}

@media (max-width: 576px) {
    .card-body {
        padding: 0.4rem !important;
    }
    
    .h5 {
        font-size: 1.1rem;
    }
    
    .small {
        font-size: 0.8rem;
    }
    
    .card h4 {
        font-size: 1.2rem;
    }
    
    .card h5 {
        font-size: 1rem;
    }
    
    .card h6 {
        font-size: 0.9rem;
    }
    
    .form-control, .form-select {
        font-size: 0.8rem;
        padding: 0.375rem 0.5rem;
    }
    
    .btn {
        font-size: 0.8rem;
        padding: 0.25rem 0.5rem;
    }
}

/* Flexible Content Areas */
.card-body {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

/* Responsive Table */
@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.875rem;
    }
    
    .table th, .table td {
        padding: 0.5rem 0.25rem;
    }
}

/* Smooth Transitions */
* {
    transition: all 0.2s ease;
}

@media print {
    .btn, .card-header, .sidebar, .navbar {
        display: none !important;
    }
    .main-content {
        margin: 0 !important;
        padding: 0 !important;
    }
    .card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
    }
}
</style>

<?php include 'layouts/footer.php'; ?>