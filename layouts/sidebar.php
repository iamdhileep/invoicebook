<?php
// Get current page name for active state
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Use absolute path from web root to avoid relative path issues
$basePath = '/billbook/';
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
            <a href="<?= $basePath ?>invoice_form.php" class="nav-link <?= $current_page === 'invoice_form' ? 'active' : '' ?>">
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
            <a href="<?= $basePath ?>summary_dashboard.php" class="nav-link <?= $current_page === 'summary_dashboard' ? 'active' : '' ?>" 
               title="Summary Dashboard - Sales Analytics" style="position: relative;">
                <i class="bi bi-graph-up-arrow"></i>
                <span>Sales Summary</span>
                <!-- Debug badge to verify visibility -->
                <small class="badge bg-success ms-1" style="font-size: 10px;">✓</small>
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

        <!-- Human Resources -->
        <div class="nav-section">
            <div class="nav-section-title">Human Resources</div>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/employees/employees.php" class="nav-link <?= $current_page === 'employees' ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i>
                <span>Employee Directory</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/attendance/attendance.php" class="nav-link <?= $current_page === 'attendance' ? 'active' : '' ?>">
                <i class="bi bi-calendar-check-fill"></i>
                <span>Mark Attendance</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>Employee_attendance.php" class="nav-link <?= $current_page === 'Employee_attendance' ? 'active' : '' ?>">
                <i class="bi bi-person-check-fill"></i>
                <span>Employee Attendance</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>advanced_attendance.php" class="nav-link <?= $current_page === 'advanced_attendance' ? 'active' : '' ?>">
                <i class="bi bi-stopwatch-fill"></i>
                <span>Time Tracking</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>attendance-calendar.php" class="nav-link <?= $current_page === 'attendance-calendar' ? 'active' : '' ?>">
                <i class="bi bi-calendar3"></i>
                <span>Attendance Calendar</span>
            </a>
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
            <a href="<?= $basePath ?>settings.php" class="nav-link <?= $current_page === 'settings' ? 'active' : '' ?>">
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