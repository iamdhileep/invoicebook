<?php
// Get current page name and full path for enhanced active state detection
$current_page = basename($_SERVER['PHP_SELF'] ?? '', '.php');
$current_path = $_SERVER['REQUEST_URI'] ?? '';
$script_name = $_SERVER['SCRIPT_NAME'] ?? '';

// Function to check if a link should be active
function isActivePage($linkPath, $currentPage, $currentPath) {
    // Extract the page name from the link path
    $pageName = basename($linkPath, '.php');
    
    // Check exact page match
    if ($pageName === $currentPage) {
        return true;
    }
    
    // Check if current path contains the link path
    if (strpos($currentPath, $linkPath) !== false) {
        return true;
    }
    
    return false;
}

// Simple and reliable base URL calculation
// Get the protocol (http or https)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
// Get the host
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
// Get the base path (directory where the application is installed)
$scriptPath = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$basePath = '';

// Determine the correct base path based on current location
if (strpos($scriptPath, '/pages/') !== false) {
    // We're in pages subdirectory, go back to root
    $basePath = '../../';
} elseif (strpos($scriptPath, '/HRMS') !== false) {
    // We're in HRMS subdirectory, go back to root
    $basePath = '../';
} else {
    // We're in root directory
    $basePath = './';
}

// For absolute URLs (more reliable)
$baseURL = $protocol . $host . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/pages/') === false && strpos($_SERVER['SCRIPT_NAME'] ?? '', '/HRMS') === false) {
    $baseURL = $protocol . $host . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
}
?>

<!-- Enhanced Sidebar Styling -->
<style>
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    width: 280px;
    background: #ffffff;
    border-right: 1px solid #e2e8f0;
    overflow-y: auto;
    z-index: 1000;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
    transition: left 0.3s ease;
}

.sidebar-nav {
    padding: 1rem 0;
}

.nav-section {
    margin: 1.5rem 0 0.5rem 0;
}

.nav-section-title {
    font-size: 0.75rem;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0 1.5rem;
    margin-bottom: 0.5rem;
}

.nav-item {
    margin: 0.125rem 0.75rem;
}

.nav-link {
    display: flex;
    align-items: center;
    padding: 0.625rem 1rem;
    color: #374151;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.875rem;
    border-radius: 0.5rem;
    transition: all 0.2s ease;
    border-left: 3px solid transparent;
    position: relative;
    overflow: hidden;
}

.nav-link:hover {
    background: linear-gradient(90deg, #f8fafc 0%, transparent 100%);
    color: #2563eb;
    border-left-color: #3b82f6;
    transform: translateX(2px);
    text-decoration: none;
}

.nav-link.active {
    background: linear-gradient(90deg, rgba(59, 130, 246, 0.1) 0%, transparent 100%);
    color: #2563eb;
    border-left-color: #2563eb;
    font-weight: 600;
}

.nav-link i {
    width: 20px;
    margin-right: 0.75rem;
    font-size: 1rem;
    transition: all 0.2s ease;
    position: relative;
    z-index: 1;
}

.nav-link span {
    position: relative;
    z-index: 1;
}

.nav-link .badge {
    font-size: 0.625rem;
    font-weight: 600;
    margin-left: auto;
    position: relative;
    z-index: 1;
}

/* Submenu Styling */
.nav-submenu {
    margin-left: 1rem;
    border-left: 2px solid #e5e7eb;
    padding-left: 0.5rem;
}

.nav-submenu-inner {
    margin-left: 1.5rem;
    border-left: 2px solid #f3f4f6;
    padding-left: 0.5rem;
}

.nav-submenu .nav-link,
.nav-submenu-inner .nav-link {
    font-size: 0.8rem;
    padding: 0.5rem 0.75rem;
    margin: 0.05rem 0;
}

/* Dropdown Styling */
.dropdown-toggle::after {
    transition: transform 0.2s ease;
    margin-left: auto;
}

.dropdown-toggle[aria-expanded="true"]::after {
    transform: rotate(180deg);
}

.collapse {
    transition: height 0.3s ease;
}

/* Mobile Responsiveness */
@media (max-width: 768px) {
    .sidebar {
        left: -280px;
    }
    
    .sidebar:not(.collapsed) {
        left: 0;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
    }
    
    .main-content-wrapper,
    .main-content {
        margin-left: 0 !important;
        width: 100% !important;
    }
}

/* Desktop Sidebar Toggle */
.sidebar.collapsed {
    left: -280px;
}

.main-content-wrapper.expanded,
.main-content.expanded {
    margin-left: 0;
    transition: margin-left 0.3s ease;
}

/* Ripple Effect */
@keyframes ripple {
    to {
        transform: scale(4);
        opacity: 0;
    }
}

/* Professional Focus States */
.nav-link:focus {
    outline: 2px solid #3b82f6;
    outline-offset: 2px;
    background-color: rgba(59, 130, 246, 0.1);
}

/* Enhanced Badge Colors */
.badge.bg-primary { background-color: #3b82f6 !important; }
.badge.bg-success { background-color: #10b981 !important; }
.badge.bg-warning { background-color: #f59e0b !important; }
.badge.bg-danger { background-color: #ef4444 !important; }
.badge.bg-info { background-color: #06b6d4 !important; }

/* Animation Classes */
.animate-fade-in-up {
    animation: fadeInUp 0.5s ease;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Text Color Utilities */
.text-purple { color: #8b5cf6 !important; }
</style>


<!-- Sidebar -->
<nav class="sidebar animate-fade-in-up" id="sidebar">
    <div class="sidebar-nav">
        <!-- Dashboard -->
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/dashboard/dashboard.php" class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-house"></i>
                <span>Dashboard</span></a>
        </div>

        <!-- Quick Actions Section -->
        <div class="nav-section">
            <div class="nav-section-title">Quick Actions</div>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/invoice/invoice.php" class="nav-link <?= $current_page === 'invoice' ? 'active' : '' ?>">
                <i class="bi bi-receipt-cutoff"></i>
                <span>New Invoice</span></a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>add_item.php" class="nav-link <?= $current_page === 'add_item' ? 'active' : '' ?>">
                <i class="bi bi-plus-circle-fill"></i>
                <span>Add Product</span></a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>add_employee.php" class="nav-link <?= $current_page === 'add_employee' ? 'active' : '' ?>">
                <i class="bi bi-person-plus-fill"></i>
                <span>Add Employee</span></a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/expenses/expenses.php" class="nav-link <?= $current_page === 'expenses' ? 'active' : '' ?>">
                <i class="bi bi-cash-coin"></i>
                <span>Record Expense</span></a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/time_tracking/time_tracking.php" class="nav-link <?= $current_page === 'time_tracking' ? 'active' : '' ?>">
                <i class="bi bi-stopwatch-fill"></i>
                <span>Time Tracking</span></a>
        </div>

        <!-- Sales & Revenue -->
        <div class="nav-section">
            <div class="nav-section-title">Sales & Revenue</div>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>invoice_history.php" class="nav-link <?= $current_page === 'invoice_history' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-text-fill"></i>
                <span>Invoice History</span></a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>summary_dashboard.php" class="nav-link <?= $current_page === 'summary_dashboard' ? 'active' : '' ?>">
                <i class="bi bi-graph-up-arrow"></i>
                <span>Sales Summary</span></a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/customers/customers.php" class="nav-link <?= $current_page === 'customers' ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i>
                <span>Customer Management</span></a>
        </div>

        <!-- Inventory Management -->
        <div class="nav-section">
            <div class="nav-section-title">Inventory</div>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/products/products.php" class="nav-link <?= $current_page === 'products' ? 'active' : '' ?>">
                <i class="bi bi-box-seam-fill"></i>
                <span>All Products</span></a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>item-stock.php" class="nav-link <?= $current_page === 'item-stock' ? 'active' : '' ?>">
                <i class="bi bi-boxes"></i>
                <span>Stock Control</span></a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>manage_categories.php" class="nav-link <?= $current_page === 'manage_categories' ? 'active' : '' ?>">
                <i class="bi bi-tags-fill"></i>
                <span>Categories</span></a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/suppliers/suppliers.php" class="nav-link <?= $current_page === 'suppliers' ? 'active' : '' ?>">
                <i class="bi bi-truck"></i>
                <span>Suppliers</span></a>
        </div>

        <!-- Warehouse Management -->
        <div class="nav-section">
            <div class="nav-section-title">Warehouse</div>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/warehouse/warehouse_management.php" class="nav-link <?= strpos($current_path, 'warehouse') !== false ? 'active' : '' ?>">
                <i class="bi bi-building"></i>
                <span>Warehouse Operations</span></a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/warehouse/warehouse_dashboard.php" class="nav-link <?= $current_page === 'warehouse_dashboard' ? 'active' : '' ?>">
                <i class="bi bi-graph-up"></i>
                <span>Analytics Dashboard</span></a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/suppliers/purchase_orders.php" class="nav-link <?= $current_page === 'purchase_orders' ? 'active' : '' ?>">
                <i class="bi bi-clipboard-data"></i>
                <span>Purchase Orders</span></a>
        </div>

        <!-- Project Management -->
        <div class="nav-section">
            <div class="nav-section-title">Projects</div>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/projects/project_management.php" class="nav-link <?= strpos($current_path, 'projects') !== false ? 'active' : '' ?>">
                <i class="bi bi-kanban"></i>
                <span>Project Management</span></a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/projects/project_dashboard.php" class="nav-link <?= $current_page === 'project_dashboard' ? 'active' : '' ?>">
                <i class="bi bi-graph-up-arrow"></i>
                <span>Project Analytics</span></a>
        </div>

        <!-- CRM & Customer Communications -->
        <div class="nav-section">
            <div class="nav-section-title">CRM & Communications</div>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/crm/crm_system.php" class="nav-link <?= strpos($current_path, 'crm') !== false ? 'active' : '' ?>">
                <i class="bi bi-people"></i>
                <span>CRM System</span></a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/crm/crm_dashboard.php" class="nav-link <?= $current_page === 'crm_dashboard' ? 'active' : '' ?>">
                <i class="bi bi-graph-up"></i>
                <span>CRM Analytics</span></a>
        </div>

        <!-- Marketing & Customer Communications -->
        <div class="nav-section">
            <div class="nav-section-title">Marketing & Communications</div>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/marketing/marketing_system.php" class="nav-link <?= strpos($current_path, 'marketing') !== false ? 'active' : '' ?>">
                <i class="bi bi-megaphone"></i>
                <span>Marketing System</span>
                <span class="badge bg-warning ms-auto">NEW</span></a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/marketing/marketing_dashboard.php" class="nav-link <?= $current_page === 'marketing_dashboard' ? 'active' : '' ?>">
                <i class="bi bi-bar-chart-line"></i>
                <span>Marketing Analytics</span></a>
        </div>

        <!-- Advanced Procurement & Supply Chain -->
        <div class="nav-section">
            <div class="nav-section-title">Procurement & Supply Chain</div>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/procurement/procurement_system.php" class="nav-link <?= strpos($current_path, 'procurement') !== false ? 'active' : '' ?>">
                <i class="bi bi-cart-fill"></i>
                <span>Procurement System</span>
                <span class="badge bg-primary ms-auto">NEW</span></a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/procurement/procurement_dashboard.php" class="nav-link <?= $current_page === 'procurement_dashboard' ? 'active' : '' ?>">
                <i class="bi bi-graph-up-arrow"></i>
                <span>Procurement Analytics</span></a>
        </div>

        <!-- Financial Management -->
        <div class="nav-section">
            <div class="nav-section-title">Finances</div>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>expense_history.php" class="nav-link <?= $current_page === 'expense_history' ? 'active' : '' ?>">
                <i class="bi bi-receipt"></i>
                <span>Expense History</span></a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>reports.php" class="nav-link <?= $current_page === 'reports' ? 'active' : '' ?>">
                <i class="bi bi-pie-chart-fill"></i>
                <span>Financial Reports</span></a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/accounts/accounts.php" class="nav-link <?= $current_page === 'accounts' ? 'active' : '' ?>">
                <i class="bi bi-bank"></i>
                <span>Account Management</span></a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/transactions/transactions.php" class="nav-link <?= $current_page === 'transactions' ? 'active' : '' ?>">
                <i class="bi bi-arrow-left-right"></i>
                <span>Transactions</span></a>
        </div>

        <!-- Attendance & Time Management -->
        <div class="nav-section">
            <div class="nav-section-title">Attendance & Time</div>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/attendance/attendance.php" class="nav-link <?= $current_page === 'attendance' ? 'active' : '' ?>">
                <i class="bi bi-calendar-check-fill"></i>
                <span>Attendance Management</span></a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>attendance_preview.php" class="nav-link <?= $current_page === 'attendance_preview' ? 'active' : '' ?>">
                <i class="bi bi-calendar2-week-fill"></i>
                <span>Attendance Reports</span></a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/time_tracking/schedules.php" class="nav-link <?= $current_page === 'schedules' ? 'active' : '' ?>">
                <i class="bi bi-clock-fill"></i>
                <span>Work Schedules</span></a>
        </div>
        
        <!-- HRM Main Menu -->
        <div class="nav-section">
            <div class="nav-section-title">HRM System</div>
        </div>
        
        <!-- Main HRM Dropdown -->
        <div class="nav-item">
            <a href="<?= $basePath ?>HRMS/index.php" class="nav-link <?= $current_page === 'hrms_dashboard' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2 text-info"></i>
                <span>HRMS Dashboard</span>
                <span class="badge bg-info ms-auto">MAIN</span></a>
        </div>
        
        <!-- Portal Access -->
        <div class="nav-item dropdown">
            <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="collapse" data-bs-target="#hrmPortalsMenu" aria-expanded="false">
                <i class="bi bi-person-workspace text-success"></i>
                <span>HRM Portals</span></a>
            <div class="collapse" id="hrmPortalsMenu">
                <div class="nav-submenu">
                    <a href="<?= $basePath ?>HRMS/hr_panel.php" class="nav-link <?= isActivePage('HRMS/hr_panel.php', $current_page, $current_path) ? 'active' : '' ?>">
                        <i class="bi bi-shield-check"></i>
                        <span>HR Portal</span>
                        <span class="badge bg-danger ms-auto">ADMIN</span></a>
                    <a href="<?= $basePath ?>HRMS/manager_panel.php" class="nav-link <?= isActivePage('HRMS/manager_panel.php', $current_page, $current_path) ? 'active' : '' ?>">
                        <i class="bi bi-person-badge"></i>
                        <span>Manager Portal</span>
                        <span class="badge bg-warning ms-auto">MGR</span></a>
                    <a href="<?= $basePath ?>HRMS/employee_panel.php" class="nav-link <?= isActivePage('HRMS/employee_panel.php', $current_page, $current_path) ? 'active' : '' ?>">
                        <i class="bi bi-person-circle"></i>
                        <span>Employee Portal</span>
                        <span class="badge bg-info ms-auto">EMP</span></a>
                </div>
            </div>
        </div>
        
        <div class="nav-item dropdown">
            <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="collapse" data-bs-target="#hrmMainMenu" aria-expanded="false">
                <i class="bi bi-people-fill text-primary"></i>
                <span>HRM Modules</span></a>
            <div class="collapse" id="hrmMainMenu">
                <div class="nav-submenu">
                    <!-- Employee Management Submenu -->
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle submenu-toggle" data-bs-toggle="collapse" data-bs-target="#employeeManagementSubmenu" aria-expanded="false">
                            <i class="bi bi-person-lines-fill"></i>
                            <span>Employee Management</span></a>
                        <div class="collapse" id="employeeManagementSubmenu">
                            <div class="nav-submenu-inner">
                                <a href="<?= $basePath ?>HRMS/employee_directory.php" class="nav-link <?= isActivePage('HRMS/employee_directory.php', $current_page, $current_path) ? 'active' : '' ?>">
                                    <i class="bi bi-people"></i>
                                    <span>Employee Directory</span></a>
                                <a href="<?= $basePath ?>add_employee.php" class="nav-link <?= isActivePage('add_employee.php', $current_page, $current_path) ? 'active' : '' ?>">
                                    <i class="bi bi-person-plus"></i>
                                    <span>Add Employee</span></a>
                                <a href="<?= $basePath ?>HRMS/employee_profile.php" class="nav-link <?= isActivePage('HRMS/employee_profile.php', $current_page, $current_path) ? 'active' : '' ?>">
                                    <i class="bi bi-person-circle"></i>
                                    <span>Employee Profiles</span></a>
                                <a href="<?= $basePath ?>HRMS/department_management.php" class="nav-link <?= isActivePage('HRMS/department_management.php', $current_page, $current_path) ? 'active' : '' ?>">
                                    <i class="bi bi-building"></i>
                                    <span>Department Management</span></a>
                            </div>
                        </div>
                    </div>

                    <!-- Leave & Attendance Management Submenu -->
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle submenu-toggle" data-bs-toggle="collapse" data-bs-target="#leaveAttendanceSubmenu" aria-expanded="false">
                            <i class="bi bi-calendar-check-fill"></i>
                            <span>Leave & Attendance</span></a>
                        <div class="collapse" id="leaveAttendanceSubmenu">
                            <div class="nav-submenu-inner">
                                <a href="<?= $basePath ?>HRMS/attendance_management.php" class="nav-link <?= isActivePage('HRMS/attendance_management.php', $current_page, $current_path) ? 'active' : '' ?>">
                                    <i class="bi bi-calendar-check"></i>
                                    <span>Attendance Management</span></a>
                                <a href="<?= $basePath ?>pages/attendance/basic_attendance.php" class="nav-link <?= isActivePage('pages/attendance/basic_attendance.php', $current_page, $current_path) ? 'active' : '' ?>">
                                    <i class="bi bi-clock"></i>
                                    <span>Basic Attendance</span></a>
                                <a href="<?= $basePath ?>advanced_attendance.php" class="nav-link <?= isActivePage('advanced_attendance.php', $current_page, $current_path) ? 'active' : '' ?>">
                                    <i class="bi bi-clock-fill text-info"></i>
                                    <span>Advanced Attendance</span>
                                    <span class="badge bg-info ms-auto">NEW</span></a>
                                <a href="<?= $basePath ?>HRMS/leave_management.php" class="nav-link <?= isActivePage('HRMS/leave_management.php', $current_page, $current_path) ? 'active' : '' ?>">
                                    <i class="bi bi-calendar-x"></i>
                                    <span>Leave Management</span></a>
                                <a href="<?= $basePath ?>HRMS/time_tracking.php" class="nav-link <?= isActivePage('HRMS/time_tracking.php', $current_page, $current_path) ? 'active' : '' ?>">
                                    <i class="bi bi-stopwatch"></i>
                                    <span>Time Tracking</span></a>
                                <a href="<?= $basePath ?>HRMS/shift_management.php" class="nav-link <?= isActivePage('HRMS/shift_management.php', $current_page, $current_path) ? 'active' : '' ?>">
                                    <i class="bi bi-clock"></i>
                                    <span>Shift Management</span></a>
                            </div>
                        </div>
                    </div>

                    <!-- Payroll Management Submenu -->
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle submenu-toggle" data-bs-toggle="collapse" data-bs-target="#payrollSubmenu" aria-expanded="false">
                            <i class="bi bi-currency-exchange"></i>
                            <span>Payroll Management</span></a>
                        <div class="collapse" id="payrollSubmenu">
                            <div class="nav-submenu-inner">
                                <a href="<?= $basePath ?>HRMS/payroll_processing.php" class="nav-link">
                                    <i class="bi bi-calculator"></i>
                                    <span>Process Payroll</span></a>
                                <a href="<?= $basePath ?>pages/payroll/advanced_payroll.php" class="nav-link">
                                    <i class="bi bi-cash-stack text-warning"></i>
                                    <span>Advanced Payroll</span>
                                    <span class="badge bg-warning ms-auto">NEW</span></a>
                                <a href="<?= $basePath ?>HRMS/salary_structure.php" class="nav-link">
                                    <i class="bi bi-graph-up"></i>
                                    <span>Salary Structure</span></a>
                                <a href="<?= $basePath ?>HRMS/payroll_reports.php" class="nav-link">
                                    <i class="bi bi-file-earmark-spreadsheet"></i>
                                    <span>Payroll Reports</span></a>
                                <a href="<?= $basePath ?>HRMS/tax_management.php" class="nav-link">
                                    <i class="bi bi-receipt"></i>
                                    <span>Tax Management</span></a>
                                <a href="<?= $basePath ?>HRMS/benefits_management.php" class="nav-link">
                                    <i class="bi bi-heart-pulse"></i>
                                    <span>Benefits Management</span></a>
                            </div>
                        </div>
                    </div>

                    <!-- Employee Onboarding Submenu -->
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle submenu-toggle" data-bs-toggle="collapse" data-bs-target="#onboardingSubmenu" aria-expanded="false">
                            <i class="bi bi-person-check"></i>
                            <span>Employee Onboarding</span></a>
                        <div class="collapse" id="onboardingSubmenu">
                            <div class="nav-submenu-inner">
                                <a href="<?= $basePath ?>HRMS/onboarding_process.php" class="nav-link">
                                    <i class="bi bi-check-circle"></i>
                                    <span>Onboarding Process</span></a>
                                <a href="<?= $basePath ?>HRMS/document_verification.php" class="nav-link">
                                    <i class="bi bi-file-check"></i>
                                    <span>Document Verification</span></a>
                                <a href="<?= $basePath ?>HRMS/training_schedule.php" class="nav-link">
                                    <i class="bi bi-book"></i>
                                    <span>Training Schedule</span></a>
                            </div>
                        </div>
                    </div>

                    <!-- Employee Offboarding Submenu -->
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle submenu-toggle" data-bs-toggle="collapse" data-bs-target="#offboardingSubmenu" aria-expanded="false">
                            <i class="bi bi-person-x"></i>
                            <span>Employee Offboarding</span></a>
                        <div class="collapse" id="offboardingSubmenu">
                            <div class="nav-submenu-inner">
                                <a href="<?= $basePath ?>HRMS/offboarding_process.php" class="nav-link">
                                    <i class="bi bi-x-circle"></i>
                                    <span>Offboarding Process</span></a>
                                <a href="<?= $basePath ?>HRMS/fnf_settlement.php" class="nav-link">
                                    <i class="bi bi-calculator-fill"></i>
                                    <span>F&F Settlement</span></a>
                                <a href="<?= $basePath ?>HRMS/exit_interview.php" class="nav-link">
                                    <i class="bi bi-chat-square-text"></i>
                                    <span>Exit Interview</span></a>
                            </div>
                        </div>
                    </div>

                    <!-- Performance Management Submenu -->
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle submenu-toggle" data-bs-toggle="collapse" data-bs-target="#performanceSubmenu" aria-expanded="false">
                            <i class="bi bi-graph-up-arrow text-success"></i>
                            <span>Performance Management</span></a>
                        <div class="collapse" id="performanceSubmenu">
                            <div class="nav-submenu-inner">
                                <a href="<?= $basePath ?>HRMS/performance_management.php" class="nav-link">
                                    <i class="bi bi-clipboard-check"></i>
                                    <span>Performance Reviews</span></a>
                                <a href="<?= $basePath ?>HRMS/goal_management.php" class="nav-link">
                                    <i class="bi bi-bullseye"></i>
                                    <span>Goal Management</span></a>
                                <a href="<?= $basePath ?>HRMS/performance_analytics.php" class="nav-link">
                                    <i class="bi bi-bar-chart"></i>
                                    <span>Performance Analytics</span></a>
                                <a href="<?= $basePath ?>HRMS/kpi_tracking.php" class="nav-link">
                                    <i class="bi bi-graph-up"></i>
                                    <span>KPI Tracking</span></a>
                                <a href="<?= $basePath ?>HRMS/advanced_performance_review.php" class="nav-link">
                                    <i class="bi bi-star-fill text-warning"></i>
                                    <span>Advanced Performance Review</span></a>
                            </div>
                        </div>
                    </div>

                    <!-- Training & Development Submenu -->
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle submenu-toggle" data-bs-toggle="collapse" data-bs-target="#trainingSubmenu" aria-expanded="false">
                            <i class="bi bi-mortarboard-fill text-info"></i>
                            <span>Training & Development</span></a>
                        <div class="collapse" id="trainingSubmenu">
                            <div class="nav-submenu-inner">
                                <a href="<?= $basePath ?>HRMS/training_management.php" class="nav-link">
                                    <i class="bi bi-book"></i>
                                    <span>Training Management</span></a>
                                <a href="<?= $basePath ?>HRMS/training_programs.php" class="nav-link">
                                    <i class="bi bi-collection"></i>
                                    <span>Training Programs</span></a>
                                <a href="<?= $basePath ?>pages/employees/employees.php" class="nav-link">
                                    <i class="bi bi-lightning"></i>
                                    <span>Skill Development</span></a>
                                <a href="<?= $basePath ?>pages/employees/employees.php" class="nav-link">
                                    <i class="bi bi-award"></i>
                                    <span>Certification Tracking</span></a>
                            </div>
                        </div>
                    </div>

                    <!-- Asset Management Submenu -->
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle submenu-toggle" data-bs-toggle="collapse" data-bs-target="#assetSubmenu" aria-expanded="false">
                            <i class="bi bi-laptop text-warning"></i>
                            <span>Asset Management</span></a>
                        <div class="collapse" id="assetSubmenu">
                            <div class="nav-submenu-inner">
                                <a href="<?= $basePath ?>HRMS/asset_management.php" class="nav-link">
                                    <i class="bi bi-pc-display"></i>
                                    <span>Asset Management</span></a>
                                <a href="<?= $basePath ?>HRMS/asset_allocation.php" class="nav-link">
                                    <i class="bi bi-person-gear"></i>
                                    <span>Asset Allocation</span></a>
                                <a href="<?= $basePath ?>HRMS/maintenance_schedule.php" class="nav-link">
                                    <i class="bi bi-tools"></i>
                                    <span>Maintenance Schedule</span></a>
                                <a href="<?= $basePath ?>HRMS/asset_reports.php" class="nav-link">
                                    <i class="bi bi-clipboard-data"></i>
                                    <span>Asset Reports</span></a>
                            </div>
                        </div>
                    </div>

                    <!-- Advanced Analytics & Reporting Submenu -->
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle submenu-toggle" data-bs-toggle="collapse" data-bs-target="#analyticsSubmenu" aria-expanded="false">
                            <i class="bi bi-graph-up text-danger"></i>
                            <span>Advanced Analytics</span></a>
                        <div class="collapse" id="analyticsSubmenu">
                            <div class="nav-submenu-inner">
                                <a href="<?= $basePath ?>executive_summary_dashboard.php" class="nav-link">
                                    <i class="bi bi-briefcase-fill text-primary"></i>
                                    <span>Executive Summary</span>
                                    <span class="badge bg-primary ms-auto">C-SUITE</span></a>
                                <a href="<?= $basePath ?>smart_bi_center.php" class="nav-link">
                                    <i class="bi bi-robot text-success"></i>
                                    <span>Smart BI Center</span>
                                    <span class="badge bg-success ms-auto">AI</span></a>
                                <a href="<?= $basePath ?>collaboration_hub.php" class="nav-link">
                                    <i class="bi bi-share-fill text-info"></i>
                                    <span>Collaboration Hub</span>
                                    <span class="badge bg-info ms-auto">LIVE</span></a>
                                <a href="<?= $basePath ?>digital_transformation.php" class="nav-link">
                                    <i class="bi bi-lightning-charge-fill text-warning"></i>
                                    <span>Digital Transformation</span>
                                    <span class="badge bg-warning ms-auto">AI</span></a>
                                <a href="<?= $basePath ?>smart_resource_management.php" class="nav-link">
                                    <i class="bi bi-gear-wide-connected text-primary"></i>
                                    <span>Resource Management</span>
                                    <span class="badge bg-primary ms-auto">SMART</span></a>
                                <a href="<?= $basePath ?>analytics_dashboard.php" class="nav-link">
                                    <i class="bi bi-bar-chart-line"></i>
                                    <span>Analytics Dashboard</span></a>
                                <a href="<?= $basePath ?>HRMS/ai_hr_analytics.php" class="nav-link">
                                    <i class="bi bi-brain text-primary"></i>
                                    <span>AI Analytics & Insights</span>
                                    <span class="badge bg-primary ms-auto">AI</span></a>
                                <a href="<?= $basePath ?>HRMS/mobile_pwa_manager.php" class="nav-link">
                                    <i class="bi bi-phone text-success"></i>
                                    <span>Mobile PWA Manager</span>
                                    <span class="badge bg-success ms-auto">PWA</span></a>
                                <a href="<?= $basePath ?>HRMS/hr_insights.php" class="nav-link">
                                    <i class="bi bi-lightbulb"></i>
                                    <span>HR Insights</span></a>
                                <a href="<?= $basePath ?>HRMS/workforce_analytics.php" class="nav-link">
                                    <i class="bi bi-people-fill"></i>
                                    <span>Workforce Analytics</span></a>
                                <a href="<?= $basePath ?>HRMS/predictive_analytics.php" class="nav-link">
                                    <i class="bi bi-graph-up-arrow"></i>
                                    <span>Predictive Analytics</span></a>
                                <a href="<?= $basePath ?>HRMS/custom_reports.php" class="nav-link">
                                    <i class="bi bi-file-earmark-bar-graph"></i>
                                    <span>Custom Reports</span></a>
                            </div>
                        </div>
                    </div>

                    <!-- Employee Self-Service & Notifications Submenu -->
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle submenu-toggle" data-bs-toggle="collapse" data-bs-target="#selfServiceSubmenu" aria-expanded="false">
                            <i class="bi bi-bell-fill text-purple"></i>
                            <span>Self-Service & Notifications</span></a>
                        <div class="collapse" id="selfServiceSubmenu">
                            <div class="nav-submenu-inner">
                                <a href="<?= $basePath ?>HRMS/employee_self_service.php" class="nav-link">
                                    <i class="bi bi-person-workspace"></i>
                                    <span>Employee Self-Service</span></a>
                                <a href="<?= $basePath ?>HRMS/announcements.php" class="nav-link">
                                    <i class="bi bi-megaphone"></i>
                                    <span>Announcements</span></a>
                                <a href="<?= $basePath ?>pages/dashboard/dashboard.php" class="nav-link">
                                    <i class="bi bi-bell"></i>
                                    <span>Notification Center</span></a>
                                <a href="<?= $basePath ?>HRMS/employee_helpdesk.php" class="nav-link">
                                    <i class="bi bi-headset"></i>
                                    <span>Employee Helpdesk</span></a>
                                <a href="<?= $basePath ?>pages/employees/employees.php" class="nav-link">
                                    <i class="bi bi-file-text"></i>
                                    <span>Document Requests</span></a>
                                <a href="<?= $basePath ?>HRMS/employee_surveys.php" class="nav-link">
                                    <i class="bi bi-clipboard-pulse"></i>
                                    <span>Employee Surveys</span></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- HRM Portals Section -->
        <div class="nav-section">
            <div class="nav-section-title">HRM Portals</div>
        </div>
        
        <!-- HR Portal -->
        <div class="nav-item">
            <a href="<?= $basePath ?>HRMS/Hr_panel.php" class="nav-link <?= $current_page === 'Hr_panel' ? 'active' : '' ?>">
                <i class="bi bi-shield-check text-primary"></i>
                <span>HR Portal</span>
                <span class="badge bg-primary ms-auto">NEW</span></a>
        </div>
        
        <!-- Manager Portal -->
        <div class="nav-item">
            <a href="<?= $basePath ?>HRMS/Manager_panel.php" class="nav-link <?= $current_page === 'Manager_panel' ? 'active' : '' ?>">
                <i class="bi bi-person-badge text-success"></i>
                <span>Manager Portal</span>
                <span class="badge bg-success ms-auto">NEW</span></a>
        </div>
        
        <!-- Employee Portal -->
        <div class="nav-item">
            <a href="<?= $basePath ?>HRMS/Employee_panel.php" class="nav-link <?= $current_page === 'Employee_panel' ? 'active' : '' ?>">
                <i class="bi bi-person-circle text-info"></i>
                <span>Employee Portal</span>
                <span class="badge bg-info ms-auto">NEW</span></a>
        </div>

        <!-- Payroll System -->
        <div class="nav-section">
            <div class="nav-section-title">Payroll</div>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/payroll/payroll.php" class="nav-link <?= $current_page === 'payroll' ? 'active' : '' ?>">
                <i class="bi bi-currency-exchange"></i>
                <span>Process Payroll</span></a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>payroll_report.php" class="nav-link <?= $current_page === 'payroll_report' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-spreadsheet-fill"></i>
                <span>Payroll Reports</span></a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/payroll/generate_payslip.php" class="nav-link <?= $current_page === 'generate_payslip' ? 'active' : '' ?>">
                <i class="bi bi-receipt-cutoff"></i>
                <span>Generate Payslips</span></a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>advanced_payroll.php" class="nav-link <?= $current_page === 'advanced_payroll' ? 'active' : '' ?>">
                <i class="bi bi-calculator-fill"></i>
                <span>Advanced Payroll</span></a>
        </div>

        <!-- Analytics & Advanced Features -->
        <div class="nav-section">
            <div class="nav-section-title">Analytics & Advanced</div>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>analytics_dashboard.php" class="nav-link <?= $current_page === 'analytics_dashboard' ? 'active' : '' ?>">
                <i class="bi bi-bar-chart-line-fill"></i>
                <span>Analytics Dashboard</span></a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>advanced_analytics_dashboard.php" class="nav-link <?= $current_page === 'advanced_analytics_dashboard' ? 'active' : '' ?>">
                <i class="bi bi-graph-up text-danger"></i>
                <span>Advanced Analytics</span></a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>advanced_features.php" class="nav-link <?= $current_page === 'advanced_features' ? 'active' : '' ?>">
                <i class="bi bi-lightning-fill text-warning"></i>
                <span>Advanced Features</span></a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>collaboration_hub.php" class="nav-link <?= $current_page === 'collaboration_hub' ? 'active' : '' ?>">
                <i class="bi bi-share-fill text-info"></i>
                <span>Collaboration Hub</span></a>
        </div>

        <!-- System & Settings -->
        <div class="nav-section">
            <div class="nav-section-title">System</div>
        </div>
        <!-- System & Settings -->
        <div class="nav-section">
            <div class="nav-section-title">System</div>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>settings.php" class="nav-link <?= $current_page === 'settings' ? 'active' : '' ?>">
                <i class="bi bi-puzzle-fill text-success"></i>
                <span>Core Modules</span></a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/settings/system_settings.php" class="nav-link <?= $current_page === 'system_settings' ? 'active' : '' ?>">
                <i class="bi bi-gear-fill"></i>
                <span>System Settings</span></a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/users/user_management.php" class="nav-link <?= $current_page === 'user_management' ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i>
                <span>User Management</span></a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/backups/database_backup.php" class="nav-link <?= $current_page === 'database_backup' ? 'active' : '' ?>">
                <i class="bi bi-cloud-arrow-up-fill"></i>
                <span>Database Backup</span></a>
        </div>
        <div class="nav-item">
            <a href="#" class="nav-link" onclick="showHelp()">
                <i class="bi bi-question-circle-fill"></i>
                <span>Help & Support</span></a>
        </div>

        <!-- Spacer -->
        <div style="margin-top: 2rem;">
            <hr style="border-color: var(--gray-200); margin: 1rem 1.5rem;">
        </div>

        <!-- User Actions -->
        <div class="nav-item">
            <a href="#" class="nav-link" onclick="showProfile()">
                <i class="bi bi-person-circle"></i>
                <span>My Profile</span></a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>logout.php" class="nav-link text-danger" onclick="return confirm('Are you sure you want to sign out?')">
                <i class="bi bi-power"></i>
                <span>Sign Out</span></a>
        </div>
    </div>
</nav>

<!-- Enhanced Sidebar Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap components
    initializeSidebar();
    
    // Initialize dropdown management
    initializeDropdownManagement();
    
    // Add professional styling and effects
    addProfessionalStyling();
});

function initializeSidebar() {
    // Add hover effects for navigation items
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.addEventListener('mouseenter', function() {
            if (!this.classList.contains('active')) {
                this.style.transition = 'all 0.2s ease';
            }
        });
    });

    // Enhanced HRMS Active State Management
    const activeSubmenuItems = document.querySelectorAll('.nav-submenu .nav-link.active, .nav-submenu-inner .nav-link.active');
    
    activeSubmenuItems.forEach(activeItem => {
        // Find all parent dropdowns and expand them
        let currentElement = activeItem;
        while (currentElement) {
            const parentCollapse = currentElement.closest('.collapse');
            if (parentCollapse) {
                // Show the collapse element
                parentCollapse.classList.add('show');
                
                // Find and update the toggle button
                const toggleButton = document.querySelector(`[data-bs-target="#${parentCollapse.id}"]`);
                if (toggleButton) {
                    toggleButton.setAttribute('aria-expanded', 'true');
                }
            }
            currentElement = parentCollapse ? parentCollapse.parentElement : null;
        }
    });
}

function initializeDropdownManagement() {
    // Get all dropdown toggles
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const targetId = this.getAttribute('data-bs-target');
            const targetCollapse = document.querySelector(targetId);
            
            if (targetCollapse) {
                const isExpanded = targetCollapse.classList.contains('show');
                
                // Close all other dropdowns at the same level
                const parentDropdown = this.closest('.nav-item.dropdown');
                const siblingDropdowns = parentDropdown?.parentElement.querySelectorAll('.nav-item.dropdown');
                
                if (siblingDropdowns) {
                    siblingDropdowns.forEach(dropdown => {
                        if (dropdown !== parentDropdown) {
                            const collapseElement = dropdown.querySelector('.collapse');
                            const toggleElement = dropdown.querySelector('.dropdown-toggle');
                            
                            if (collapseElement && collapseElement.classList.contains('show')) {
                                collapseElement.classList.remove('show');
                                if (toggleElement) {
                                    toggleElement.setAttribute('aria-expanded', 'false');
                                }
                            }
                        }
                    });
                }
                
                // Toggle the current dropdown
                if (isExpanded) {
                    targetCollapse.classList.remove('show');
                    this.setAttribute('aria-expanded', 'false');
                } else {
                    targetCollapse.classList.add('show');
                    this.setAttribute('aria-expanded', 'true');
                }
            }
        });
    });
    
    // Handle clicks outside dropdowns to close them
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            const openDropdowns = document.querySelectorAll('.collapse.show');
            openDropdowns.forEach(dropdown => {
                const toggle = document.querySelector(`[data-bs-target="#${dropdown.id}"]`);
                if (toggle && !toggle.closest('.nav-item').contains(e.target)) {
                    dropdown.classList.remove('show');
                    toggle.setAttribute('aria-expanded', 'false');
                }
            });
        }
    });
}

function addProfessionalStyling() {
    // Add ripple effect to nav items
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Skip ripple for dropdown toggles
            if (this.classList.contains('dropdown-toggle')) return;
            
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.cssText = `
                position: absolute;
                width: ${size}px;
                height: ${size}px;
                left: ${x}px;
                top: ${y}px;
                background: rgba(99, 102, 241, 0.3);
                border-radius: 50%;
                transform: scale(0);
                animation: ripple 0.6s linear;
                pointer-events: none;
                z-index: 0;
            `;
            
            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });

    // Add enhanced CSS styles
    if (!document.getElementById('sidebar-enhanced-styles')) {
        const style = document.createElement('style');
        style.id = 'sidebar-enhanced-styles';
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
            
            .nav-link span {
                position: relative;
                z-index: 1;
            }
            
            .nav-link i {
                position: relative;
                z-index: 1;
                transition: color 0.2s ease;
            }
            
            .text-purple {
                color: #6f42c1 !important;
            }
            
            /* Enhanced dropdown styling */
            .dropdown-toggle::after {
                transition: transform 0.2s ease;
            }
            
            .dropdown-toggle[aria-expanded="true"]::after {
                transform: rotate(180deg);
            }
            
            /* Smooth collapse animations */
            .collapse {
                transition: height 0.3s ease;
            }
            
            /* Professional focus states */
            .nav-submenu .nav-link:focus,
            .nav-submenu-inner .nav-link:focus {
                outline: 2px solid #3b82f6;
                outline-offset: 2px;
                background-color: rgba(59, 130, 246, 0.1);
            }
            
            /* Sidebar toggle enhancements */
            .sidebar.collapsed {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar:not(.collapsed) {
                transform: translateX(0);
                transition: transform 0.3s ease;
            }
            
            .main-content-wrapper.expanded {
                margin-left: 0;
                transition: margin-left 0.3s ease;
            }
            
            .main-content.expanded {
                margin-left: 0;
                transition: margin-left 0.3s ease;
            }
            
            @media (max-width: 768px) {
                .sidebar {
                    position: fixed;
                    top: 0;
                    left: 0;
                    height: 100vh;
                    z-index: 1050;
                    transform: translateX(-100%);
                }
                
                .sidebar:not(.collapsed) {
                    transform: translateX(0);
                    box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
                }
                
                .main-content-wrapper,
                .main-content {
                    margin-left: 0 !important;
                    width: 100% !important;
                }
            }
        `;
        document.head.appendChild(style);
    }
}

// Event-based sidebar toggle functionality

function initializeSidebarToggle() {
    console.log('Sidebar: Initializing sidebar toggle');
    
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content-wrapper, .main-content');
    
    console.log('Sidebar: Elements found:', {
        sidebar: !!sidebar,
        mainContent: !!mainContent
    });
    
    // Listen for header toggle events
    document.addEventListener('sidebarToggle', function() {
        console.log('Sidebar: sidebarToggle event received');
        
        if (sidebar) {
            const isCurrentlyCollapsed = sidebar.classList.contains('collapsed');
            console.log('Sidebar: Current state - collapsed:', isCurrentlyCollapsed);
            
            if (isCurrentlyCollapsed) {
                sidebar.classList.remove('collapsed');
                localStorage.setItem('sidebarCollapsed', 'false');
                console.log('Sidebar: Expanded sidebar');
            } else {
                sidebar.classList.add('collapsed');
                localStorage.setItem('sidebarCollapsed', 'true');
                console.log('Sidebar: Collapsed sidebar');
            }
            
            // Update main content
            if (mainContent) {
                if (isCurrentlyCollapsed) {
                    // Sidebar is being expanded, so main content should not be expanded
                    mainContent.classList.remove('expanded');
                } else {
                    // Sidebar is being collapsed, so main content should be expanded
                    mainContent.classList.add('expanded');
                }
                console.log('Sidebar: Updated main content classes:', mainContent.className);
            }
            
            // Emit state change event back to header with the NEW state
            const newCollapsedState = sidebar.classList.contains('collapsed');
            const stateEvent = new CustomEvent('sidebarStateChanged', {
                detail: { collapsed: newCollapsedState }
            });
            document.dispatchEvent(stateEvent);
            console.log('Sidebar: sidebarStateChanged event dispatched', { collapsed: newCollapsedState });
        } else {
            console.error('Sidebar: Sidebar element not found!');
        }
    });
    
    // Handle window resize
    function handleResize() {
        if (window.innerWidth <= 768) {
            if (sidebar) sidebar.classList.add('collapsed');
            if (mainContent) mainContent.classList.add('expanded');
        } else {
            const savedState = localStorage.getItem('sidebarCollapsed');
            if (savedState === 'true') {
                // Sidebar should be collapsed, main content expanded
                if (sidebar) sidebar.classList.add('collapsed');
                if (mainContent) mainContent.classList.add('expanded');
            } else {
                // Sidebar should be visible, main content normal
                if (sidebar) sidebar.classList.remove('collapsed');
                if (mainContent) mainContent.classList.remove('expanded');
            }
        }
    }
    
    window.addEventListener('resize', handleResize);
    handleResize(); // Initial call
}

function initializeDropdownManagement() {
    // Get all dropdown toggles
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const targetId = this.getAttribute('data-bs-target');
            const targetCollapse = document.querySelector(targetId);
            
            if (targetCollapse) {
                const isExpanded = targetCollapse.classList.contains('show');
                
                // Close all other dropdowns at the same level
                const parentDropdown = this.closest('.nav-item.dropdown');
                const siblingDropdowns = parentDropdown?.parentElement.querySelectorAll('.nav-item.dropdown');
                
                if (siblingDropdowns) {
                    siblingDropdowns.forEach(dropdown => {
                        if (dropdown !== parentDropdown) {
                            const collapseElement = dropdown.querySelector('.collapse');
                            const toggleElement = dropdown.querySelector('.dropdown-toggle');
                            
                            if (collapseElement && collapseElement.classList.contains('show')) {
                                collapseElement.classList.remove('show');
                                if (toggleElement) {
                                    toggleElement.setAttribute('aria-expanded', 'false');
                                }
                            }
                        }
                    });
                }
                
                // Toggle current dropdown
                if (isExpanded) {
                    targetCollapse.classList.remove('show');
                    this.setAttribute('aria-expanded', 'false');
                } else {
                    targetCollapse.classList.add('show');
                    this.setAttribute('aria-expanded', 'true');
                }
            }
        });
    });
}

// Helper functions for future implementation
function showSettings() {
    // Placeholder for settings modal/page
    alert('Settings panel will be implemented soon!');
}

function showHelp() {
    // Placeholder for help system
    alert('Help & Support system coming soon!');
}

function showProfile() {
    // Placeholder for profile management
    alert('Profile management will be available soon!');
}
</script>

<!-- End of Sidebar -->