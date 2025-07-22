<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Dashboard';

// Fetch dashboard statistics
$totalInvoices = 0;
$todayExpenses = 0;
$totalEmployees = 0;
$totalItems = 0;
$monthlyRevenue = 0;
$todayRevenue = 0;

// Total Invoices Amount
$result = mysqli_query($conn, "SELECT SUM(total_amount) AS total FROM invoices");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalInvoices = $row['total'] ?? 0;
}

// Today's Expenses
$today = date('Y-m-d');
$result = mysqli_query($conn, "SELECT SUM(amount) AS total FROM expenses WHERE DATE(created_at) = '$today'");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $todayExpenses = $row['total'] ?? 0;
}

// Today's Revenue
$result = mysqli_query($conn, "SELECT SUM(total_amount) AS total FROM invoices WHERE DATE(invoice_date) = '$today'");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $todayRevenue = $row['total'] ?? 0;
}

// Monthly Revenue
$currentMonth = date('Y-m');
$result = mysqli_query($conn, "SELECT SUM(total_amount) AS total FROM invoices WHERE DATE_FORMAT(invoice_date, '%Y-%m') = '$currentMonth'");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $monthlyRevenue = $row['total'] ?? 0;
}

// Total Employees
$result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM employees");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalEmployees = $row['total'] ?? 0;
}

// Total Items
$result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM items");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalItems = $row['total'] ?? 0;
}

// Recent Invoices
$recentInvoices = [];
$result = mysqli_query($conn, "SELECT * FROM invoices ORDER BY invoice_date DESC LIMIT 5");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $recentInvoices[] = $row;
    }
}

// Recent Expenses
$recentExpenses = [];
$result = mysqli_query($conn, "SELECT * FROM expenses ORDER BY created_at DESC LIMIT 5");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $recentExpenses[] = $row;
    }
}

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Dashboard</h1>
            <p class="text-muted">Welcome back! Here's what's happening with your business today.</p>
        </div>
        <div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#dateRangeModal">
                <i class="bi bi-calendar3"></i> Date Range Report
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Revenue</h6>
                            <h2 class="mb-0">₹ <?= number_format($totalInvoices, 2) ?></h2>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-currency-rupee"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Today's Revenue</h6>
                            <h2 class="mb-0">₹ <?= number_format($todayRevenue, 2) ?></h2>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Today's Expenses</h6>
                            <h2 class="mb-0">₹ <?= number_format($todayExpenses, 2) ?></h2>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Net Profit Today</h6>
                            <h2 class="mb-0">₹ <?= number_format($todayRevenue - $todayExpenses, 2) ?></h2>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-trophy"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Secondary Stats -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <i class="bi bi-people fs-1 text-primary mb-2"></i>
                    <h3><?= $totalEmployees ?></h3>
                    <p class="text-muted mb-0">Total Employees</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <i class="bi bi-box fs-1 text-warning mb-2"></i>
                    <h3><?= $totalItems ?></h3>
                    <p class="text-muted mb-0">Products in Stock</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <i class="bi bi-calendar-month fs-1 text-success mb-2"></i>
                    <h3>₹ <?= number_format($monthlyRevenue, 2) ?></h3>
                    <p class="text-muted mb-0">This Month's Revenue</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Invoices</h5>
                    <a href="../../invoice_history.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recentInvoices)): ?>
                        <p class="text-muted text-center py-4">No invoices found</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentInvoices as $invoice): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?= htmlspecialchars($invoice['customer_name']) ?></h6>
                                        <small class="text-muted"><?= date('M d, Y', strtotime($invoice['invoice_date'])) ?></small>
                                    </div>
                                    <span class="badge bg-success">₹ <?= number_format($invoice['total_amount'], 2) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Expenses</h5>
                    <a href="../../expense_history.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recentExpenses)): ?>
                        <p class="text-muted text-center py-4">No expenses found</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentExpenses as $expense): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?= htmlspecialchars($expense['category']) ?></h6>
                                        <small class="text-muted"><?= date('M d, Y', strtotime($expense['created_at'])) ?></small>
                                    </div>
                                    <span class="badge bg-danger">₹ <?= number_format($expense['amount'], 2) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Date Range Modal -->
<div class="modal fade" id="dateRangeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Date Range Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="GET" action="">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">From Date</label>
                            <input type="date" class="form-control" name="from_date" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">To Date</label>
                            <input type="date" class="form-control" name="to_date" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Generate Report</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Handle date range report
if (isset($_GET['from_date']) && isset($_GET['to_date'])) {
    $from = $_GET['from_date'];
    $to = $_GET['to_date'];

    // Invoices in range
    $invoiceTotal = 0;
    $invoiceQuery = "SELECT SUM(total_amount) AS total FROM invoices WHERE invoice_date BETWEEN '$from' AND '$to'";
    $invoiceResult = mysqli_query($conn, $invoiceQuery);
    if ($invoiceResult) {
        $row = mysqli_fetch_assoc($invoiceResult);
        $invoiceTotal = $row['total'] ?? 0;
    }

    // Expenses in range
    $expenseTotal = 0;
    $expenseQuery = "SELECT SUM(amount) AS total FROM expenses WHERE DATE(created_at) BETWEEN '$from' AND '$to'";
    $expenseResult = mysqli_query($conn, $expenseQuery);
    if ($expenseResult) {
        $row = mysqli_fetch_assoc($expenseResult);
        $expenseTotal = $row['total'] ?? 0;
    }

    echo "<script>
        $(document).ready(function() {
            showAlert(`
                <h6>Report for " . date('M d, Y', strtotime($from)) . " to " . date('M d, Y', strtotime($to)) . "</h6>
                <ul class='list-unstyled mb-0'>
                    <li><strong>Total Revenue:</strong> ₹ " . number_format($invoiceTotal, 2) . "</li>
                    <li><strong>Total Expenses:</strong> ₹ " . number_format($expenseTotal, 2) . "</li>
                    <li><strong>Net Profit:</strong> ₹ " . number_format($invoiceTotal - $expenseTotal, 2) . "</li>
                </ul>
            `, 'info');
        });
    </script>";
}

include '../../layouts/footer.php';
?>