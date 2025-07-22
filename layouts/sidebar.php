<?php
// Get current page name for active state
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>

<!-- Sidebar -->
<nav class="sidebar" id="sidebar">
    <div class="sidebar-nav">
        <!-- Dashboard -->
        <div class="nav-item">
            <a href="../../pages/dashboard/dashboard.php" class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i>
                Dashboard
            </a>
        </div>

        <!-- Invoice Management -->
        <div class="nav-item">
            <a href="../../pages/invoice/invoice.php" class="nav-link <?= $current_page === 'invoice' ? 'active' : '' ?>">
                <i class="bi bi-receipt"></i>
                Create Invoice
            </a>
        </div>
        <div class="nav-item">
            <a href="../../invoice_history.php" class="nav-link <?= $current_page === 'invoice_history' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-text"></i>
                Invoice History
            </a>
        </div>

        <!-- Product Management -->
        <div class="nav-item">
            <a href="../../pages/products/products.php" class="nav-link <?= $current_page === 'products' ? 'active' : '' ?>">
                <i class="bi bi-box"></i>
                Product List
            </a>
        </div>
        <div class="nav-item">
            <a href="../../add_item.php" class="nav-link <?= $current_page === 'add_item' ? 'active' : '' ?>">
                <i class="bi bi-plus-circle"></i>
                Add Product
            </a>
        </div>
        <div class="nav-item">
            <a href="../../item-stock.php" class="nav-link <?= $current_page === 'item-stock' ? 'active' : '' ?>">
                <i class="bi bi-boxes"></i>
                Stock Management
            </a>
        </div>

        <!-- Expense Management -->
        <div class="nav-item">
            <a href="../../pages/expenses/expenses.php" class="nav-link <?= $current_page === 'expenses' ? 'active' : '' ?>">
                <i class="bi bi-cash-stack"></i>
                Daily Expenses
            </a>
        </div>
        <div class="nav-item">
            <a href="../../expense_history.php" class="nav-link <?= $current_page === 'expense_history' ? 'active' : '' ?>">
                <i class="bi bi-journal-text"></i>
                Expense History
            </a>
        </div>

        <!-- Employee Management -->
        <div class="nav-item">
            <a href="../../pages/employees/employees.php" class="nav-link <?= $current_page === 'employees' ? 'active' : '' ?>">
                <i class="bi bi-people"></i>
                Employee Details
            </a>
        </div>
        <div class="nav-item">
            <a href="../../pages/attendance/attendance.php" class="nav-link <?= $current_page === 'attendance' ? 'active' : '' ?>">
                <i class="bi bi-calendar-check"></i>
                Mark Attendance
            </a>
        </div>
        <div class="nav-item">
            <a href="../../attendance-calendar.php" class="nav-link <?= $current_page === 'attendance-calendar' ? 'active' : '' ?>">
                <i class="bi bi-calendar3"></i>
                Attendance Calendar
            </a>
        </div>

        <!-- Payroll -->
        <div class="nav-item">
            <a href="../../pages/payroll/payroll.php" class="nav-link <?= $current_page === 'payroll' ? 'active' : '' ?>">
                <i class="bi bi-currency-rupee"></i>
                Payroll
            </a>
        </div>

        <!-- Reports -->
        <div class="nav-item">
            <a href="reports.php" class="nav-link <?= $current_page === 'reports' ? 'active' : '' ?>">
                <i class="bi bi-graph-up"></i>
                Reports
            </a>
        </div>

        <!-- Settings -->
        <div class="nav-item">
            <a href="manage_categories.php" class="nav-link <?= $current_page === 'manage_categories' ? 'active' : '' ?>">
                <i class="bi bi-tags"></i>
                Manage Categories
            </a>
        </div>

        <hr class="my-3 mx-3">

        <!-- Logout -->
        <div class="nav-item">
            <a href="logout.php" class="nav-link text-danger">
                <i class="bi bi-box-arrow-right"></i>
                Logout
            </a>
        </div>
    </div>
</nav>