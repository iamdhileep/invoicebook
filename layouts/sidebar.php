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
                                <a href="<?= $basePath ?>HRMS/salary_structure.php" class="nav-link">
                                    <i class="bi bi-graph-up"></i>
                                    <span>Salary Structure</span></a>
                                <a href="<?= $basePath ?>HRMS/payroll_reports.php" class="nav-link">
                                    <i class="bi bi-file-earmark-spreadsheet"></i>
                                    <span>Payroll Reports</span></a>
                                <a href="<?= $basePath ?>HRMS/tax_management.php" class="nav-link">
                                    <i class="bi bi-receipt"></i>
                                    <span>Tax Management</span></a>
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
                                <a href="<?= $basePath ?>pages/employees/employees.php" class="nav-link">
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
                                <a href="<?= $basePath ?>item-stock.php" class="nav-link">
                                    <i class="bi bi-tools"></i>
                                    <span>Maintenance Schedule</span></a>
                                <a href="<?= $basePath ?>reports.php" class="nav-link">
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
                                <a href="<?= $basePath ?>analytics_dashboard.php" class="nav-link">
                                    <i class="bi bi-bar-chart-line"></i>
                                    <span>Executive Dashboard</span></a>
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
            <a href="<?= $basePath ?>attendance_preview.php" class="nav-link <?= $current_page === 'attendance_preview' ? 'active' : '' ?>">
                <i class="bi bi-calendar2-week-fill"></i>
                <span>Attendance Reports</span></a>
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
            <a href="#" class="nav-link" onclick="showSettings()">
                <i class="bi bi-gear-fill"></i>
                <span>Settings</span></a>
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

<!-- Sidebar Enhancement Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effects for navigation items
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.addEventListener('mouseenter', function() {
            if (!this.classList.contains('active')) {
                this.style.transition = 'all 0.2s ease';
            }
        });
    });

    // Add ripple effect to nav items
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
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
            `;
            
            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });

    // Enhanced HRMS Active State Management
    const activeSubmenuItems = document.querySelectorAll('.nav-submenu .nav-link.active');
    
    activeSubmenuItems.forEach(activeItem => {
        // Find parent dropdown and expand it
        const parentSubmenu = activeItem.closest('.collapse');
        const parentDropdown = parentSubmenu ? parentSubmenu.closest('.dropdown') : null;
        
        if (parentSubmenu && parentDropdown) {
            // Show the parent submenu
            parentSubmenu.classList.add('show');
            
            // Update the toggle button state
            const toggleButton = parentDropdown.querySelector('.dropdown-toggle');
            if (toggleButton) {
                toggleButton.setAttribute('aria-expanded', 'true');
            }
            
            // Also expand the main HRM menu if this is inside it
            const mainHrmMenu = document.getElementById('hrmMainMenu');
            if (mainHrmMenu && mainHrmMenu.contains(activeItem)) {
                mainHrmMenu.classList.add('show');
                const mainToggle = document.querySelector('[data-bs-target="#hrmMainMenu"]');
                if (mainToggle) {
                    mainToggle.setAttribute('aria-expanded', 'true');
                }
            }
        }
    });

    // Add CSS for professional sidebar styling
    if (!document.getElementById('sidebar-styles')) {
        const style = document.createElement('style');
        style.id = 'sidebar-styles';
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
            
            /* Professional focus states */
            .nav-submenu .nav-link:focus {
                outline: 2px solid #3b82f6;
                outline-offset: 2px;
            }
        `;
        document.head.appendChild(style);
    }
});

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

<!-- Main Content Wrapper starts here -->
<div class="main-content-wrapper flex-grow-1">