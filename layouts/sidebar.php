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
<nav class="sidebar animate-fade-in-up" id="sidebar">
    <div class="sidebar-nav">
        <!-- Dashboard -->
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/dashboard/dashboard.php" class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-house"></i>
                <span>Dashboard</span>
            </a>
        </div>

        <!-- Quick Actions Section -->
        <div class="nav-section">
            <div class="nav-section-title">Quick Actions</div>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/invoice/invoice.php" class="nav-link <?= $current_page === 'invoice' ? 'active' : '' ?>">
                <i class="bi bi-receipt-cutoff"></i>
                <span>New Invoice</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>add_item.php" class="nav-link <?= $current_page === 'add_item' ? 'active' : '' ?>">
                <i class="bi bi-plus-circle-fill"></i>
                <span>Add Product</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>add_employee.php" class="nav-link <?= $current_page === 'add_employee' ? 'active' : '' ?>">
                <i class="bi bi-person-plus-fill"></i>
                <span>Add Employee</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/expenses/expenses.php" class="nav-link <?= $current_page === 'expenses' ? 'active' : '' ?>">
                <i class="bi bi-cash-coin"></i>
                <span>Record Expense</span>
            </a>
        </div>

        <!-- Sales & Revenue -->
        <div class="nav-section">
            <div class="nav-section-title">Sales & Revenue</div>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>invoice_history.php" class="nav-link <?= $current_page === 'invoice_history' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-text-fill"></i>
                <span>Invoice History</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>summary_dashboard.php" class="nav-link <?= $current_page === 'summary_dashboard' ? 'active' : '' ?>">
                <i class="bi bi-graph-up-arrow"></i>
                <span>Sales Summary</span>
            </a>
        </div>

        <!-- Inventory Management -->
        <div class="nav-section">
            <div class="nav-section-title">Inventory</div>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/products/products.php" class="nav-link <?= $current_page === 'products' ? 'active' : '' ?>">
                <i class="bi bi-box-seam-fill"></i>
                <span>All Products</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>item-stock.php" class="nav-link <?= $current_page === 'item-stock' ? 'active' : '' ?>">
                <i class="bi bi-boxes"></i>
                <span>Stock Control</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>manage_categories.php" class="nav-link <?= $current_page === 'manage_categories' ? 'active' : '' ?>">
                <i class="bi bi-tags-fill"></i>
                <span>Categories</span>
            </a>
        </div>

        <!-- Financial Management -->
        <div class="nav-section">
            <div class="nav-section-title">Finances</div>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>expense_history.php" class="nav-link <?= $current_page === 'expense_history' ? 'active' : '' ?>">
                <i class="bi bi-receipt"></i>
                <span>Expense History</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>reports.php" class="nav-link <?= $current_page === 'reports' ? 'active' : '' ?>">
                <i class="bi bi-pie-chart-fill"></i>
                <span>Financial Reports</span>
            </a>
        </div>

        <!-- Portal Management -->
        <div class="nav-section">
            <div class="nav-section-title">Portal Management</div>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/hr/hrms_admin_panel.php" class="nav-link <?= in_array($current_page, ['hrms_admin_panel']) ? 'active' : '' ?>">
                <i class="bi bi-person-workspace"></i>
                <span>HR Portal</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/hr/team_manager_console.php" class="nav-link <?= in_array($current_page, ['team_manager_console']) ? 'active' : '' ?>">
                <i class="bi bi-person-gear"></i>
                <span>Manager Portal</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/employee/staff_self_service.php" class="nav-link <?= in_array($current_page, ['staff_self_service']) ? 'active' : '' ?>">
                <i class="bi bi-person-badge"></i>
                <span>Employee Portal</span>
            </a>
        </div>

        <!-- Human Resources -->
        <div class="nav-section">
            <div class="nav-section-title">Human Resources</div>
        </div>
        
        <!-- HR & Manager Portals -->
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/hr/hrms_admin_panel.php" class="nav-link <?= $current_page === 'hrms_admin_panel' ? 'active' : '' ?>">
                <i class="bi bi-shield-check text-primary"></i>
                <span>HR Portal</span>
                <span class="badge bg-primary ms-auto">NEW</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/hr/team_manager_console.php" class="nav-link <?= $current_page === 'team_manager_console' ? 'active' : '' ?>">
                <i class="bi bi-person-badge text-success"></i>
                <span>Manager Portal</span>
                <span class="badge bg-success ms-auto">NEW</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/employee/staff_self_service.php" class="nav-link <?= $current_page === 'staff_self_service' ? 'active' : '' ?>">
                <i class="bi bi-person-circle text-info"></i>
                <span>Employee Portal</span>
                <span class="badge bg-info ms-auto">NEW</span>
            </a>
        </div>
        
        <!-- Employee Management -->
        <div class="nav-item dropdown">
            <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="collapse" data-bs-target="#employeeSubmenu" aria-expanded="false">
                <i class="bi bi-people-fill"></i>
                <span>Employee Management</span>
                <i class="bi bi-chevron-down ms-auto"></i>
            </a>
            <div class="collapse" id="employeeSubmenu">
                <div class="nav-submenu">
                    <a href="<?= $basePath ?>pages/employees/employees.php" class="nav-link <?= $current_page === 'employees' ? 'active' : '' ?>">
                        <i class="bi bi-person-lines-fill"></i>
                        <span>Employee Directory</span>
                    </a>
                    <a href="<?= $basePath ?>add_employee.php" class="nav-link <?= $current_page === 'add_employee' ? 'active' : '' ?>">
                        <i class="bi bi-person-plus"></i>
                        <span>Add New Employee</span>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Attendance & Time Tracking -->
        <div class="nav-item dropdown">
            <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="collapse" data-bs-target="#attendanceSubmenu" aria-expanded="false">
                <i class="bi bi-calendar-check-fill"></i>
                <span>Attendance & Leaves</span>
                <i class="bi bi-chevron-down ms-auto"></i>
            </a>
            <div class="collapse" id="attendanceSubmenu">
                <div class="nav-submenu">
                    <a href="<?= $basePath ?>pages/attendance/attendance.php" class="nav-link <?= $current_page === 'attendance' ? 'active' : '' ?>">
                        <i class="bi bi-calendar-check"></i>
                        <span>Mark Attendance</span>
                    </a>
                    <a href="<?= $basePath ?>attendance-calendar.php" class="nav-link <?= $current_page === 'attendance-calendar' ? 'active' : '' ?>">
                        <i class="bi bi-calendar3"></i>
                        <span>Attendance Calendar</span>
                    </a>
                    <a href="<?= $basePath ?>advanced_attendance.php" class="nav-link <?= $current_page === 'advanced_attendance' ? 'active' : '' ?>">
                        <i class="bi bi-stopwatch-fill"></i>
                        <span>Time Tracking</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Payroll System -->
        <div class="nav-section">
            <div class="nav-section-title">Payroll</div>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/payroll/payroll.php" class="nav-link <?= $current_page === 'payroll' ? 'active' : '' ?>">
                <i class="bi bi-currency-exchange"></i>
                <span>Process Payroll</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>payroll_report.php" class="nav-link <?= $current_page === 'payroll_report' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-spreadsheet-fill"></i>
                <span>Payroll Reports</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>attendance_preview.php" class="nav-link <?= $current_page === 'attendance_preview' ? 'active' : '' ?>">
                <i class="bi bi-calendar2-week-fill"></i>
                <span>Attendance Reports</span>
            </a>
        </div>

        <!-- System & Settings -->
        <div class="nav-section">
            <div class="nav-section-title">System</div>
        </div>
        <div class="nav-item">
            <a href="#" class="nav-link" onclick="showSettings()">
                <i class="bi bi-gear-fill"></i>
                <span>Settings</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="#" class="nav-link" onclick="showHelp()">
                <i class="bi bi-question-circle-fill"></i>
                <span>Help & Support</span>
            </a>
        </div>

        <!-- Spacer -->
        <div style="margin-top: 2rem;">
            <hr style="border-color: var(--gray-200); margin: 1rem 1.5rem;">
        </div>

        <!-- User Actions -->
        <div class="nav-item">
            <a href="#" class="nav-link" onclick="showProfile()">
                <i class="bi bi-person-circle"></i>
                <span>My Profile</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>logout.php" class="nav-link text-danger" onclick="return confirm('Are you sure you want to sign out?')">
                <i class="bi bi-power"></i>
                <span>Sign Out</span>
            </a>
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

    // Add CSS for ripple animation
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