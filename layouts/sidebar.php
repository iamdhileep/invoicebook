<?php
// Get current page name for active state
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Determine the relative path to root from current location
$relativePath = '';
$currentDir = dirname($_SERVER['SCRIPT_NAME']);
$levels = substr_count(str_replace('/pages', '', $currentDir), '/');
for ($i = 0; $i < $levels; $i++) {
    $relativePath .= '../';
}
if (empty($relativePath)) $relativePath = './';
?>

<!-- Sidebar -->
<nav class="sidebar" id="sidebar">
    <div class="sidebar-nav">
        <!-- Dashboard -->
        <div class="nav-item">
            <a href="<?= $relativePath ?>pages/dashboard/dashboard.php" class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i>
                Dashboard
            </a>
        </div>

        <!-- Invoice Management -->
        <div class="nav-item">
            <a href="<?= $relativePath ?>pages/invoice/invoice.php" class="nav-link <?= $current_page === 'invoice' ? 'active' : '' ?>">
                <i class="bi bi-receipt"></i>
                Create Invoice
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $relativePath ?>invoice_history.php" class="nav-link <?= $current_page === 'invoice_history' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-text"></i>
                Invoice History
            </a>
        </div>

        <!-- Product Management -->
        <div class="nav-item">
            <a href="<?= $relativePath ?>pages/products/products.php" class="nav-link <?= $current_page === 'products' ? 'active' : '' ?>">
                <i class="bi bi-box"></i>
                Product List
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $relativePath ?>add_item.php" class="nav-link <?= $current_page === 'add_item' ? 'active' : '' ?>">
                <i class="bi bi-plus-circle"></i>
                Add Product
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $relativePath ?>item-stock.php" class="nav-link <?= $current_page === 'item-stock' ? 'active' : '' ?>">
                <i class="bi bi-boxes"></i>
                Stock Management
            </a>
        </div>

        <!-- Expense Management -->
        <div class="nav-item">
            <a href="<?= $relativePath ?>pages/expenses/expenses.php" class="nav-link <?= $current_page === 'expenses' ? 'active' : '' ?>">
                <i class="bi bi-cash-stack"></i>
                Daily Expenses
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $relativePath ?>expense_history.php" class="nav-link <?= $current_page === 'expense_history' ? 'active' : '' ?>">
                <i class="bi bi-journal-text"></i>
                Expense History
            </a>
        </div>

        <!-- Employee Management -->
        <div class="nav-item">
            <a href="<?= $relativePath ?>pages/employees/employees.php" class="nav-link <?= $current_page === 'employees' ? 'active' : '' ?>">
                <i class="bi bi-people"></i>
                Employee Details
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $relativePath ?>pages/attendance/attendance.php" class="nav-link <?= $current_page === 'attendance' ? 'active' : '' ?>">
                <i class="bi bi-calendar-check"></i>
                Mark Attendance
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $relativePath ?>attendance-calendar.php" class="nav-link <?= $current_page === 'attendance-calendar' ? 'active' : '' ?>">
                <i class="bi bi-calendar3"></i>
                Attendance Calendar
            </a>
        </div>

        <!-- Payroll -->
        <div class="nav-item">
            <a href="<?= $relativePath ?>pages/payroll/payroll.php" class="nav-link <?= $current_page === 'payroll' ? 'active' : '' ?>">
                <i class="bi bi-currency-rupee"></i>
                Payroll
            </a>
        </div>

        <!-- Reports -->
        <div class="nav-item">
            <a href="<?= $relativePath ?>reports.php" class="nav-link <?= $current_page === 'reports' ? 'active' : '' ?>">
                <i class="bi bi-graph-up"></i>
                Reports
            </a>
        </div>

        <!-- Settings -->
        <div class="nav-item">
            <a href="<?= $relativePath ?>manage_categories.php" class="nav-link <?= $current_page === 'manage_categories' ? 'active' : '' ?>">
                <i class="bi bi-tags"></i>
                Manage Categories
            </a>
        </div>

        <hr class="my-3 mx-3">

        <!-- Logout -->
        <div class="nav-item">
            <a href="<?= $relativePath ?>logout.php" class="nav-link text-danger">
                <i class="bi bi-box-arrow-right"></i>
                Logout
            </a>
        </div>
    </div>
</nav>