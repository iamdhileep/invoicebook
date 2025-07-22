<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

// Include config and database
include '../../config.php';
include '../../db.php';

$page_title = 'Dashboard';

// Fetch dashboard statistics
$totalInvoices = 0;
$todayExpenses = 0;
$totalEmployees = 0;
$totalItems = 0;

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

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Dashboard</h1>
            <p class="text-muted">Welcome back! Here's what's happening with your business today.</p>
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
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Employees</h6>
                            <h2 class="mb-0"><?= $totalEmployees ?></h2>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-people"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Products</h6>
                            <h2 class="mb-0"><?= $totalItems ?></h2>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-box"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row g-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-receipt fs-1 text-primary mb-3"></i>
                    <h5>Create Invoice</h5>
                    <p class="text-muted small">Generate new customer invoices</p>
                    <a href="../invoice/invoice.php" class="btn btn-primary">Create Now</a>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-cash-stack fs-1 text-danger mb-3"></i>
                    <h5>Add Expense</h5>
                    <p class="text-muted small">Record daily business expenses</p>
                    <a href="../expenses/expenses.php" class="btn btn-danger">Add Expense</a>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-people fs-1 text-success mb-3"></i>
                    <h5>Manage Employees</h5>
                    <p class="text-muted small">Add and manage team members</p>
                    <a href="../employees/employees.php" class="btn btn-success">Manage</a>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-box fs-1 text-warning mb-3"></i>
                    <h5>Product Inventory</h5>
                    <p class="text-muted small">Manage products and stock</p>
                    <a href="../products/products.php" class="btn btn-warning">View Products</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../layouts/footer.php'; ?>