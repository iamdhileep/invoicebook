<?php
// Get current page name for active state
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Simple and reliable base URL calculation
// Get the protocol (http or https)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
// Get the host
$host = $_SERVER['HTTP_HOST'];
// Get the base path (directory where the application is installed)
$scriptPath = dirname($_SERVER['SCRIPT_NAME']);
$basePath = '';

// If we're in a subdirectory (like /pages/something/), get back to root
if (strpos($scriptPath, '/pages/') !== false) {
    // We're in pages subdirectory, go back to root
    $basePath = '../../';
} else {
    // We're in root directory
    $basePath = './';
}

// For absolute URLs (more reliable)
$baseURL = $protocol . $host . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
if (strpos($_SERVER['SCRIPT_NAME'], '/pages/') === false) {
    $baseURL = $protocol . $host . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
}
?>

<!-- Sidebar -->
<nav class="sidebar" id="sidebar">
    <div class="sidebar-nav">
        <!-- Dashboard -->
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/dashboard/dashboard.php" class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i>
                Dashboard
            </a>
        </div>

        <!-- Invoice Management -->
        <div class="nav-section">
            <div class="nav-section-title">Invoice Management</div>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/invoice/invoice.php" class="nav-link <?= $current_page === 'invoice' ? 'active' : '' ?>">
                <i class="bi bi-receipt"></i>
                Create Invoice
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>invoice_history.php" class="nav-link <?= $current_page === 'invoice_history' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-text"></i>
                Invoice History
            </a>
        </div>

        <!-- Product Management -->
        <div class="nav-section">
            <div class="nav-section-title">Product Management</div>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/products/products.php" class="nav-link <?= $current_page === 'products' ? 'active' : '' ?>">
                <i class="bi bi-box"></i>
                Product List
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>add_item.php" class="nav-link <?= $current_page === 'add_item' ? 'active' : '' ?>">
                <i class="bi bi-plus-circle"></i>
                Add Product
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>item-stock.php" class="nav-link <?= $current_page === 'item-stock' ? 'active' : '' ?>">
                <i class="bi bi-boxes"></i>
                Stock Management
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>manage_categories.php" class="nav-link <?= $current_page === 'manage_categories' ? 'active' : '' ?>">
                <i class="bi bi-tags"></i>
                Categories
            </a>
        </div>

        <!-- Expense Management -->
        <div class="nav-section">
            <div class="nav-section-title">Expense Management</div>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/expenses/expenses.php" class="nav-link <?= $current_page === 'expenses' ? 'active' : '' ?>">
                <i class="bi bi-cash-stack"></i>
                Daily Expenses
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>expense_history.php" class="nav-link <?= $current_page === 'expense_history' ? 'active' : '' ?>">
                <i class="bi bi-journal-text"></i>
                Expense History
            </a>
        </div>

        <!-- Employee Management -->
        <div class="nav-section">
            <div class="nav-section-title">Employee Management</div>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/employees/employees.php" class="nav-link <?= $current_page === 'employees' ? 'active' : '' ?>">
                <i class="bi bi-people"></i>
                All Employees
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>add_employee.php" class="nav-link <?= $current_page === 'add_employee' ? 'active' : '' ?>">
                <i class="bi bi-person-plus"></i>
                Add Employee
            </a>
        </div>
                        <div class="nav-item">
                    <a href="<?= $basePath ?>pages/attendance/attendance.php" class="nav-link <?= $current_page === 'attendance' ? 'active' : '' ?>">
                        <i class="bi bi-calendar-check"></i>
                        Mark Attendance
                    </a>
                </div>
                <div class="nav-item">
                    <a href="<?= $basePath ?>advanced_attendance.php" class="nav-link <?= $current_page === 'advanced_attendance' ? 'active' : '' ?>">
                        <i class="bi bi-clock-history"></i>
                        Advanced Attendance
                    </a>
                </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>attendance-calendar.php" class="nav-link <?= $current_page === 'attendance-calendar' ? 'active' : '' ?>">
                <i class="bi bi-calendar3"></i>
                Attendance Calendar
            </a>
        </div>

        <!-- Payroll -->
        <div class="nav-section">
            <div class="nav-section-title">Payroll</div>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/payroll/payroll.php" class="nav-link <?= $current_page === 'payroll' ? 'active' : '' ?>">
                <i class="bi bi-currency-rupee"></i>
                Payroll Management
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>payroll_report.php" class="nav-link <?= $current_page === 'payroll_report' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-spreadsheet"></i>
                Payroll Report
            </a>
        </div>

        <!-- Reports & Analytics -->
        <div class="nav-section">
            <div class="nav-section-title">Reports</div>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>summary_dashboard.php" class="nav-link <?= $current_page === 'summary_dashboard' ? 'active' : '' ?>">
                <i class="bi bi-bar-chart-line"></i>
                Summary by Date Range
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>reports.php" class="nav-link <?= $current_page === 'reports' ? 'active' : '' ?>">
                <i class="bi bi-graph-up"></i>
                Business Reports
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>attendance_preview.php" class="nav-link <?= $current_page === 'attendance_preview' ? 'active' : '' ?>">
                <i class="bi bi-calendar2-week"></i>
                Attendance Report
            </a>
        </div>

        <hr class="my-3 mx-3">

        <!-- Logout -->
        <div class="nav-item">
            <a href="<?= $basePath ?>logout.php" class="nav-link text-danger">
                <i class="bi bi-box-arrow-right"></i>
                Logout
            </a>
        </div>
    </div>
</nav>