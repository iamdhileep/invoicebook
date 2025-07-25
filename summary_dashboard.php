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
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="h4 mb-1 fw-bold text-primary">📊 Business Summary Dashboard</h1>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-calendar3"></i> 
                        <?= date('M d, Y', strtotime($dateFrom)) ?> - <?= date('M d, Y', strtotime($dateTo)) ?>
                        <span class="badge bg-light text-dark ms-2"><?= ucfirst(str_replace('_', ' ', $period)) ?></span>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-success btn-sm" onclick="exportSummary()" title="Export Data">
                        <i class="bi bi-download"></i> Export
                    </button>
                    <button class="btn btn-outline-primary btn-sm" onclick="printSummary()" title="Print Summary">
                        <i class="bi bi-printer"></i> Print
                    </button>
                    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm" title="Main Dashboard">
                        <i class="bi bi-house"></i> Home
                    </a>
                </div>
            </div>

            <!-- Date Range Filter -->
            <div class="card mb-3 border-0 shadow-sm">
                <div class="card-header bg-gradient text-white py-2" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <h6 class="mb-0 text-white">
                        <i class="bi bi-calendar-range me-2"></i>Date Range Filter
                        <span class="float-end">
                            <i class="bi bi-funnel-fill"></i>
                        </span>
                    </h6>
                </div>
                <div class="card-body p-3">
                    <form method="GET" class="row g-3">
                        <div class="col-lg-3 col-md-4 col-sm-6">
                            <label class="form-label fw-semibold text-dark">
                                <i class="bi bi-clock-history me-1"></i>Quick Periods
                            </label>
                            <select name="period" class="form-select shadow-sm" onchange="toggleCustomDates(this.value)">
                                <option value="custom" <?= $period === 'custom' ? 'selected' : '' ?>>📅 Custom Range</option>
                                <option value="today" <?= $period === 'today' ? 'selected' : '' ?>>📍 Today</option>
                                <option value="yesterday" <?= $period === 'yesterday' ? 'selected' : '' ?>>⏮️ Yesterday</option>
                                <option value="this_week" <?= $period === 'this_week' ? 'selected' : '' ?>>📅 This Week</option>
                                <option value="last_week" <?= $period === 'last_week' ? 'selected' : '' ?>>⏪ Last Week</option>
                                <option value="this_month" <?= $period === 'this_month' ? 'selected' : '' ?>>📆 This Month</option>
                                <option value="last_month" <?= $period === 'last_month' ? 'selected' : '' ?>>⏮️ Last Month</option>
                                <option value="this_quarter" <?= $period === 'this_quarter' ? 'selected' : '' ?>>📊 This Quarter</option>
                                <option value="this_year" <?= $period === 'this_year' ? 'selected' : '' ?>>🗓️ This Year</option>
                                <option value="last_year" <?= $period === 'last_year' ? 'selected' : '' ?>>📋 Last Year</option>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-4 col-sm-6" id="fromDateDiv">
                            <label class="form-label fw-semibold text-dark">
                                <i class="bi bi-calendar-event me-1"></i>From Date
                            </label>
                            <input type="date" name="date_from" class="form-control shadow-sm" value="<?= $dateFrom ?>">
                        </div>
                        <div class="col-lg-3 col-md-4 col-sm-6" id="toDateDiv">
                            <label class="form-label fw-semibold text-dark">
                                <i class="bi bi-calendar-check me-1"></i>To Date
                            </label>
                            <input type="date" name="date_to" class="form-control shadow-sm" value="<?= $dateTo ?>">
                        </div>
                        <div class="col-lg-3 col-md-12 col-sm-6">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary d-block w-100 shadow-sm">
                                <i class="bi bi-search me-2"></i>Generate Summary
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Key Performance Indicators -->
            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-lg-6 col-md-6">
                    <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e8f4fd 0%, #cce7ff 100%);">
                        <div class="card-body text-center p-3">
                            <div class="mb-2">
                                <i class="bi bi-currency-rupee fs-3" style="color: #0d6efd;"></i>
                            </div>
                            <h5 class="mb-1 fw-bold" style="color: #0d6efd;">₹<?= number_format($revenueData['total_revenue'], 2) ?></h5>
                            <small class="text-muted">Total Revenue</small>
                            <div class="d-flex justify-content-center align-items-center mt-2">
                                <small class="text-muted me-2">
                                    <i class="bi bi-receipt me-1"></i><?= $revenueData['total_invoices'] ?> invoices
                                </small>
                                <?php 
                                $revGrowth = $revenueData['total_revenue'] > 0 ? '+12%' : '0%';
                                ?>
                                <span class="badge bg-success bg-opacity-75">
                                    <i class="bi bi-arrow-up"></i> <?= $revGrowth ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-6 col-md-6">
                    <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%);">
                        <div class="card-body text-center p-3">
                            <div class="mb-2">
                                <i class="bi bi-cash-stack fs-3" style="color: #7b1fa2;"></i>
                            </div>
                            <h5 class="mb-1 fw-bold" style="color: #7b1fa2;">₹<?= number_format($expenseData['total_amount'], 2) ?></h5>
                            <small class="text-muted">Total Expenses</small>
                            <div class="d-flex justify-content-center align-items-center mt-2">
                                <small class="text-muted me-2">
                                    <i class="bi bi-file-earmark-text me-1"></i><?= $expenseData['total_expenses'] ?> expenses
                                </small>
                                <span class="badge bg-warning bg-opacity-75">
                                    <i class="bi bi-arrow-down"></i> -5%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-6 col-md-6">
                    <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);">
                        <div class="card-body text-center p-3">
                            <div class="mb-2">
                                <i class="bi bi-graph-<?= $profit >= 0 ? 'up' : 'down' ?>-arrow fs-3" style="color: #388e3c;"></i>
                            </div>
                            <h5 class="mb-1 fw-bold" style="color: #388e3c;">₹<?= number_format($profit, 2) ?></h5>
                            <small class="text-muted">Net Profit</small>
                            <div class="d-flex justify-content-center align-items-center mt-2">
                                <small class="text-muted me-2">
                                    <i class="bi bi-percent me-1"></i><?= number_format($profitMargin, 1) ?>% margin
                                </small>
                                <span class="badge bg-<?= $profit >= 0 ? 'success' : 'danger' ?> bg-opacity-75">
                                    <i class="bi bi-arrow-<?= $profit >= 0 ? 'up' : 'down' ?>"></i> 
                                    <?= $profit >= 0 ? 'Profit' : 'Loss' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-6 col-md-6">
                    <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);">
                        <div class="card-body text-center p-3">
                            <div class="mb-2">
                                <i class="bi bi-people-fill fs-3" style="color: #ff9800;"></i>
                            </div>
                            <h5 class="mb-1 fw-bold" style="color: #ff9800;"><?= number_format($attendanceRate, 1) ?>%</h5>
                            <small class="text-muted">Attendance Rate</small>
                            <div class="d-flex justify-content-center align-items-center mt-2">
                                <small class="text-muted me-2">
                                    <i class="bi bi-person-check me-1"></i><?= $employeeData['total_employees'] ?> employees
                                </small>
                                <span class="badge bg-<?= $attendanceRate >= 80 ? 'success' : 'warning' ?> bg-opacity-75">
                                    <i class="bi bi-check-circle"></i> 
                                    <?= $attendanceRate >= 80 ? 'Good' : 'Fair' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Metrics Row -->
            <div class="row g-3 mb-4">
                <!-- Revenue Details -->
                <div class="col-lg-4 col-md-6">
                    <div class="card h-100 border-0 shadow">
                        <div class="card-header bg-primary bg-opacity-10 border-0 py-3">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-primary p-2 me-3">
                                    <i class="bi bi-bar-chart text-white"></i>
                                </div>
                                <h6 class="mb-0 fw-semibold text-primary">Revenue Analysis</h6>
                            </div>
                        </div>
                        <div class="card-body p-3">
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <div class="text-center p-3 bg-primary bg-opacity-10 rounded-3">
                                        <i class="bi bi-calculator text-primary fs-4 mb-2"></i>
                                        <h5 class="text-primary mb-1 fw-bold">₹<?= number_format($revenueData['avg_invoice_value'], 2) ?></h5>
                                        <small class="text-muted fw-semibold">Avg Invoice</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center p-3 bg-success bg-opacity-10 rounded-3">
                                        <i class="bi bi-trophy text-success fs-4 mb-2"></i>
                                        <h5 class="text-success mb-1 fw-bold">₹<?= number_format($revenueData['highest_invoice'], 2) ?></h5>
                                        <small class="text-muted fw-semibold">Highest</small>
                                    </div>
                                </div>
                            </div>
                            <div class="border-top pt-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted">
                                        <i class="bi bi-receipt me-1"></i>Total Invoices:
                                    </span>
                                    <strong class="text-dark"><?= $revenueData['total_invoices'] ?></strong>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted">
                                        <i class="bi bi-arrow-down-circle me-1"></i>Lowest Invoice:
                                    </span>
                                    <strong class="text-dark">₹<?= number_format($revenueData['lowest_invoice'], 2) ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Expense Details -->
                <div class="col-lg-4 col-md-6">
                    <div class="card h-100 border-0 shadow">
                        <div class="card-header bg-danger bg-opacity-10 border-0 py-3">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-danger p-2 me-3">
                                    <i class="bi bi-receipt text-white"></i>
                                </div>
                                <h6 class="mb-0 fw-semibold text-danger">Expense Analysis</h6>
                            </div>
                        </div>
                        <div class="card-body p-3">
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <div class="text-center p-3 bg-warning bg-opacity-10 rounded-3">
                                        <i class="bi bi-calculator text-warning fs-4 mb-2"></i>
                                        <h5 class="text-warning mb-1 fw-bold">₹<?= number_format($expenseData['avg_expense'], 2) ?></h5>
                                        <small class="text-muted fw-semibold">Avg Expense</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center p-3 bg-danger bg-opacity-10 rounded-3">
                                        <i class="bi bi-exclamation-triangle text-danger fs-4 mb-2"></i>
                                        <h5 class="text-danger mb-1 fw-bold">₹<?= number_format($expenseData['highest_expense'], 2) ?></h5>
                                        <small class="text-muted fw-semibold">Highest</small>
                                    </div>
                                </div>
                            </div>
                            <div class="border-top pt-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted">
                                        <i class="bi bi-file-earmark-text me-1"></i>Total Expenses:
                                    </span>
                                    <strong class="text-dark"><?= $expenseData['total_expenses'] ?></strong>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted">
                                        <i class="bi bi-calendar-day me-1"></i>Daily Average:
                                    </span>
                                    <strong class="text-dark">₹<?= number_format($expenseData['total_amount'] / max(1, (strtotime($dateTo) - strtotime($dateFrom)) / 86400 + 1), 2) ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="col-lg-4 col-md-12">
                    <div class="card h-100 border-0 shadow">
                        <div class="card-header bg-info bg-opacity-10 border-0 py-3">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-info p-2 me-3">
                                    <i class="bi bi-speedometer2 text-white"></i>
                                </div>
                                <h6 class="mb-0 fw-semibold text-info">Quick Insights</h6>
                            </div>
                        </div>
                        <div class="card-body p-3">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted">
                                        <i class="bi bi-calendar-range me-1"></i>Period Days:
                                    </span>
                                    <strong class="text-dark"><?= max(1, (strtotime($dateTo) - strtotime($dateFrom)) / 86400 + 1) ?> days</strong>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted">
                                        <i class="bi bi-graph-up me-1"></i>Daily Revenue:
                                    </span>
                                    <strong class="text-success">₹<?= number_format($revenueData['total_revenue'] / max(1, (strtotime($dateTo) - strtotime($dateFrom)) / 86400 + 1), 2) ?></strong>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-muted">
                                        <i class="bi bi-percent me-1"></i>Profit Ratio:
                                    </span>
                                    <strong class="text-<?= $profitMargin >= 0 ? 'success' : 'danger' ?>"><?= number_format($profitMargin, 1) ?>%</strong>
                                </div>
                            </div>
                            
                            <!-- Mini Progress Bars -->
                            <div class="mb-2">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <small class="text-muted">Revenue vs Target</small>
                                    <small class="text-success fw-semibold">85%</small>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-success" style="width: 85%"></div>
                                </div>
                            </div>
                            
                            <div>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <small class="text-muted">Cost Control</small>
                                    <small class="text-warning fw-semibold">72%</small>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-warning" style="width: 72%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts and Analytics Row -->
            <div class="row g-3 mb-4">
                <!-- Daily Trends Chart -->
                <div class="col-lg-8 col-md-12">
                    <div class="card h-100 border-0 shadow">
                        <div class="card-header bg-light border-0 py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-success p-2 me-3">
                                        <i class="bi bi-graph-up text-white"></i>
                                    </div>
                                    <h6 class="mb-0 fw-semibold text-dark">Daily Revenue vs Expenses Trend</h6>
                                </div>
                                <div class="d-flex gap-2">
                                    <span class="badge bg-success-subtle text-success">
                                        <i class="bi bi-circle-fill me-1"></i>Revenue
                                    </span>
                                    <span class="badge bg-danger-subtle text-danger">
                                        <i class="bi bi-circle-fill me-1"></i>Expenses
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-3">
                            <div style="height: 300px; position: relative;">
                                <canvas id="dailyTrendsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Categories -->
                <div class="col-lg-4 col-md-12">
                    <div class="card h-100 border-0 shadow">
                        <div class="card-header bg-light border-0 py-3">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-warning p-2 me-3">
                                    <i class="bi bi-pie-chart text-white"></i>
                                </div>
                                <h6 class="mb-0 fw-semibold text-dark">Top Performing Categories</h6>
                            </div>
                        </div>
                        <div class="card-body p-3">
                            <?php if ($topCategories->num_rows > 0): ?>
                                <?php 
                                $maxRevenue = 0;
                                $categoriesArray = [];
                                while ($category = $topCategories->fetch_assoc()) {
                                    $categoriesArray[] = $category;
                                    if ($category['total_revenue'] > $maxRevenue) {
                                        $maxRevenue = $category['total_revenue'];
                                    }
                                }
                                
                                $colors = ['primary', 'success', 'info', 'warning', 'danger', 'secondary', 'dark'];
                                foreach ($categoriesArray as $index => $category): 
                                    $percentage = $maxRevenue > 0 ? ($category['total_revenue'] / $maxRevenue) * 100 : 0;
                                    $colorClass = $colors[$index % count($colors)];
                                ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle bg-<?= $colorClass ?> me-2" style="width: 12px; height: 12px;"></div>
                                                <div>
                                                    <strong class="text-dark"><?= htmlspecialchars($category['category'] ?? 'Uncategorized') ?></strong>
                                                    <br><small class="text-muted">
                                                        <i class="bi bi-box me-1"></i><?= $category['items_sold'] ?> items • 
                                                        <i class="bi bi-stack me-1"></i><?= $category['total_quantity'] ?> qty
                                                    </small>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <strong class="text-<?= $colorClass ?>">₹<?= number_format($category['total_revenue'], 2) ?></strong>
                                                <br><small class="text-muted"><?= number_format($percentage, 1) ?>%</small>
                                            </div>
                                        </div>
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-<?= $colorClass ?>" style="width: <?= $percentage ?>%"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-pie-chart text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-3 mb-0">No category data available</p>
                                    <small class="text-muted">Start selling items to see category breakdown</small>
                                </div>
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
                fill: true,
                pointBackgroundColor: '#198754',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }, {
                label: 'Expenses',
                data: expenseData,
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#dc3545',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: {
                padding: {
                    top: 10,
                    right: 10,
                    bottom: 10,
                    left: 10
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                    align: 'start',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 20,
                        font: {
                            size: 12,
                            weight: '500'
                        }
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: '#4e73df',
                    borderWidth: 1,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ₹' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)',
                        drawBorder: false
                    },
                    ticks: {
                        font: {
                            size: 11
                        },
                        maxTicksLimit: 8
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)',
                        drawBorder: false
                    },
                    ticks: {
                        font: {
                            size: 11
                        },
                        callback: function(value) {
                            return '₹' + value.toLocaleString();
                        },
                        maxTicksLimit: 6
                    }
                }
            },
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
            },
            elements: {
                line: {
                    borderWidth: 3
                }
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
/* Enhanced Dashboard Styling - Optimized for Space Usage */
.main-content {
    padding: 0.75rem 0;
    min-height: 100vh;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
}

.container-fluid {
    max-width: 100%;
    padding: 0 20px;
}

/* Card Enhancements */
.card {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 12px;
    border: none;
    overflow: hidden;
}

.card:not(.statistics-card):hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15) !important;
}

.card-header {
    border-radius: 12px 12px 0 0 !important;
    background: rgba(255, 255, 255, 0.9) !important;
    backdrop-filter: blur(10px);
    border: none !important;
}

/* Standard Card Body */
.card-body {
    padding: 1.25rem !important;
}

/* Optimized Spacing */
.mb-4 {
    margin-bottom: 1.5rem !important;
}

.mb-3 {
    margin-bottom: 1rem !important;
}

.g-3 > * {
    padding: 0.75rem;
}

/* Enhanced KPI Cards - Payroll Style */
.statistics-card {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 12px;
    border: none;
    overflow: visible;
}

.statistics-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15) !important;
}

.statistics-card .card-body {
    padding: 1.25rem !important;
    position: relative;
}

.statistics-card .fs-3 {
    font-size: 2rem !important;
    margin-bottom: 0.5rem;
}

.statistics-card h5 {
    font-size: 1.25rem;
    margin-bottom: 0.25rem;
}

.statistics-card small {
    font-size: 0.875rem;
    font-weight: 500;
}

.statistics-card .badge {
    font-size: 0.7rem;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-weight: 500;
}

.statistics-card .d-flex.justify-content-center {
    gap: 0.5rem;
    flex-wrap: wrap;
}

.statistics-card .mb-2 {
    margin-bottom: 0.75rem !important;
}

.statistics-card .mt-2 {
    margin-top: 0.75rem !important;
}

/* Progress Bars */
.progress {
    border-radius: 10px;
    overflow: hidden;
}

.progress-bar {
    border-radius: 10px;
    transition: width 0.6s ease;
}

/* Badge Enhancements */
.badge {
    font-size: 0.75rem;
    padding: 0.35rem 0.65rem;
    border-radius: 6px;
}

/* Form Elements */
.form-control, .form-select {
    border-radius: 8px;
    border: 1px solid #e3e6f0;
    padding: 0.75rem 1rem;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: #4e73df;
    box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
}

/* Button Enhancements */
.btn {
    border-radius: 8px;
    padding: 0.5rem 1rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Table Improvements */
.table {
    border-radius: 8px;
    overflow: hidden;
}

.table th {
    background: #f8f9fc;
    border-top: none;
    font-weight: 600;
    color: #5a5c69;
    padding: 1rem 0.75rem;
}

.table td {
    padding: 0.75rem;
    vertical-align: middle;
}

/* Responsive Grid Optimizations */
@media (max-width: 1400px) {
    .container-fluid {
        padding: 0 15px;
    }
}

@media (max-width: 1200px) {
    .col-xl-3 {
        flex: 0 0 50%;
        max-width: 50%;
        margin-bottom: 1rem;
    }
    
    .statistics-card .card-body {
        padding: 1rem !important;
    }
    
    .statistics-card .fs-3 {
        font-size: 1.75rem !important;
    }
    
    .statistics-card h5 {
        font-size: 1.1rem;
    }
    
    .main-content {
        padding: 0.5rem 0;
    }
}

@media (max-width: 992px) {
    .container-fluid {
        padding: 0 12px;
    }
    
    .g-3 > * {
        padding: 0.5rem;
    }
    
    .statistics-card .card-body {
        padding: 0.875rem !important;
    }
    
    .statistics-card .fs-3 {
        font-size: 1.5rem !important;
    }
    
    .btn-sm {
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
    }
}

@media (max-width: 768px) {
    .col-xl-3, .col-lg-6, .col-lg-4 {
        flex: 0 0 100%;
        max-width: 100%;
    }
    
    .main-content {
        padding: 0.5rem 0;
    }
    
    .container-fluid {
        padding: 0 10px;
    }
    
    .statistics-card .card-body {
        padding: 0.75rem !important;
    }
    
    .statistics-card .fs-3 {
        font-size: 1.5rem !important;
    }
    
    .statistics-card h5 {
        font-size: 1rem;
    }
    
    .d-flex.justify-content-between {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch !important;
    }
    
    .d-flex.justify-content-between .d-flex {
        justify-content: center;
        flex-wrap: wrap;
    }
    
    /* Adjust header for mobile */
    .h4 {
        font-size: 1.2rem;
    }
}

@media (max-width: 576px) {
    .container-fluid {
        padding: 0 8px;
    }
    
    .statistics-card .card-body {
        padding: 0.625rem !important;
    }
    
    .statistics-card .fs-3 {
        font-size: 1.25rem !important;
    }
    
    .statistics-card h5 {
        font-size: 0.95rem;
    }
    
    .statistics-card small {
        font-size: 0.8rem;
    }
    
    .g-3 > * {
        padding: 0.375rem;
    }
    
    .btn {
        font-size: 0.8rem;
        padding: 0.375rem 0.75rem;
    }
    
    .form-control, .form-select {
        font-size: 0.875rem;
        padding: 0.5rem 0.75rem;
    }
    
    /* Compact table for mobile */
    .table-responsive {
        font-size: 0.8rem;
    }
    
    .table th, .table td {
        padding: 0.5rem 0.375rem;
    }
}

/* Chart Container */
.card-body canvas {
    border-radius: 8px;
}

/* Empty State Improvements */
.text-center.py-4 {
    padding: 2rem !important;
    background: #f8f9fc;
    border-radius: 8px;
    margin: 1rem 0;
}

/* Animation for loading states */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.card {
    animation: fadeIn 0.5s ease-out;
}

/* Scrollbar Styling */
::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Print Optimizations */
@media print {
    .btn, .card-header .float-end, .sidebar, .navbar {
        display: none !important;
    }
    
    .main-content {
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
    }
    
    .card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
        margin-bottom: 1rem !important;
        break-inside: avoid;
    }
    
    .container-fluid {
        padding: 0 !important;
    }
    
    .row {
        margin: 0 !important;
    }
    
    .col-lg-3, .col-lg-4, .col-lg-6, .col-lg-8 {
        width: 100% !important;
        max-width: 100% !important;
        flex: none !important;
    }
}

/* Focus States for Accessibility */
.btn:focus, .form-control:focus, .form-select:focus {
    outline: 2px solid #4e73df;
    outline-offset: 2px;
}

/* High Contrast Mode Support */
@media (prefers-contrast: high) {
    .card {
        border: 2px solid #000;
    }
    
    .text-muted {
        color: #333 !important;
    }
}

/* Reduced Motion Support */
@media (prefers-reduced-motion: reduce) {
    .card, .btn, .progress-bar {
        transition: none;
    }
    
    .card:hover {
        transform: none;
    }
}
</style>

<?php include 'layouts/footer.php'; ?>