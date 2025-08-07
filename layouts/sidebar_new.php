<?php
// Unified Sidebar Redirect
// This file now redirects to the main unified sidebar
// All menu items have been merged into layouts/sidebar.php

// Include the main unified sidebar
require_once 'sidebar.php';
?>

<!-- 
This sidebar has been unified with the main sidebar.
All functionality has been merged into layouts/sidebar.php
for consistency across the entire application.
-->
        <div class="nav-item">
            <a href="<?= $basePath ?>pages/invoice/invoice.php" class="nav-link <?= isActivePage('pages/invoice/invoice.php', $current_page) ? 'active' : '' ?>">
                <i class="bi bi-receipt-cutoff"></i>
                <span>New Invoice</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>add_item.php" class="nav-link <?= isActivePage('add_item.php', $current_page) ? 'active' : '' ?>">
                <i class="bi bi-plus-circle-fill"></i>
                <span>Add Product</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>add_employee.php" class="nav-link <?= isActivePage('add_employee.php', $current_page) ? 'active' : '' ?>">
                <i class="bi bi-person-plus-fill"></i>
                <span>Add Employee</span>
            </a>
        </div>

        <!-- HRM System -->
        <div class="nav-section">
            <div class="nav-section-title">HRM System</div>
        </div>
        
        <!-- HRM Dashboard -->
        <div class="nav-item">
            <a href="<?= $basePath ?>HRMS/index.php" class="nav-link <?= isActivePage('HRMS/index.php', $current_page) ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i>
                <span>HRMS Dashboard</span>
            </a>
        </div>
        
        <!-- HRM Portals Dropdown -->
        <div class="nav-item dropdown">
            <a href="#" class="nav-link dropdown-toggle" onclick="toggleDropdown('hrmPortals')">
                <i class="bi bi-person-workspace"></i>
                <span>HRM Portals</span>
                <i class="bi bi-chevron-right dropdown-arrow"></i>
            </a>
            <div class="dropdown-menu" id="hrmPortals">
                <a href="<?= $basePath ?>HRMS/hr_panel.php" class="dropdown-link">
                    <i class="bi bi-shield-check"></i>
                    <span>HR Portal</span>
                </a>
                <a href="<?= $basePath ?>HRMS/manager_panel.php" class="dropdown-link">
                    <i class="bi bi-person-badge"></i>
                    <span>Manager Portal</span>
                </a>
                <a href="<?= $basePath ?>HRMS/employee_panel.php" class="dropdown-link">
                    <i class="bi bi-person-circle"></i>
                    <span>Employee Portal</span>
                </a>
            </div>
        </div>

        <!-- Employee Management Dropdown -->
        <div class="nav-item dropdown">
            <a href="#" class="nav-link dropdown-toggle" onclick="toggleDropdown('employeeManagement')">
                <i class="bi bi-people-fill"></i>
                <span>Employee Management</span>
                <i class="bi bi-chevron-right dropdown-arrow"></i>
            </a>
            <div class="dropdown-menu" id="employeeManagement">
                <a href="<?= $basePath ?>HRMS/employee_directory.php" class="dropdown-link">
                    <i class="bi bi-people"></i>
                    <span>Employee Directory</span>
                </a>
                <a href="<?= $basePath ?>add_employee.php" class="dropdown-link">
                    <i class="bi bi-person-plus"></i>
                    <span>Add Employee</span>
                </a>
                <a href="<?= $basePath ?>HRMS/employee_profile.php" class="dropdown-link">
                    <i class="bi bi-person-circle"></i>
                    <span>Employee Profiles</span>
                </a>
            </div>
        </div>

        <!-- Attendance & Leave Dropdown -->
        <div class="nav-item dropdown">
            <a href="#" class="nav-link dropdown-toggle" onclick="toggleDropdown('attendance')">
                <i class="bi bi-calendar-check-fill"></i>
                <span>Attendance & Leave</span>
                <i class="bi bi-chevron-right dropdown-arrow"></i>
            </a>
            <div class="dropdown-menu" id="attendance">
                <a href="<?= $basePath ?>HRMS/attendance_management.php" class="dropdown-link">
                    <i class="bi bi-calendar-check"></i>
                    <span>Attendance Management</span>
                </a>
                <a href="<?= $basePath ?>HRMS/leave_management.php" class="dropdown-link">
                    <i class="bi bi-calendar-x"></i>
                    <span>Leave Management</span>
                </a>
                <a href="<?= $basePath ?>HRMS/time_tracking.php" class="dropdown-link">
                    <i class="bi bi-stopwatch"></i>
                    <span>Time Tracking</span>
                </a>
            </div>
        </div>

        <!-- Payroll Management -->
        <div class="nav-item dropdown">
            <a href="#" class="nav-link dropdown-toggle" onclick="toggleDropdown('payroll')">
                <i class="bi bi-currency-exchange"></i>
                <span>Payroll Management</span>
                <i class="bi bi-chevron-right dropdown-arrow"></i>
            </a>
            <div class="dropdown-menu" id="payroll">
                <a href="<?= $basePath ?>HRMS/payroll_processing.php" class="dropdown-link">
                    <i class="bi bi-calculator"></i>
                    <span>Process Payroll</span>
                </a>
                <a href="<?= $basePath ?>HRMS/salary_structure.php" class="dropdown-link">
                    <i class="bi bi-graph-up"></i>
                    <span>Salary Structure</span>
                </a>
                <a href="<?= $basePath ?>HRMS/payroll_reports.php" class="dropdown-link">
                    <i class="bi bi-file-earmark-spreadsheet"></i>
                    <span>Payroll Reports</span>
                </a>
            </div>
        </div>

        <!-- System Settings -->
        <div class="nav-section">
            <div class="nav-section-title">System</div>
        </div>
        <div class="nav-item">
            <a href="<?= $basePath ?>settings.php" class="nav-link <?= isActivePage('settings.php', $current_page) ? 'active' : '' ?>">
                <i class="bi bi-gear-fill"></i>
                <span>Settings</span>
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

<!-- Toggle Button -->
<button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()">
    <i class="bi bi-list"></i>
</button>

<!-- Main Content Wrapper -->
<div class="main-content" id="mainContent">

<style>
/* Clean Sidebar Styles */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 280px;
    height: 100vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    transform: translateX(0);
    transition: transform 0.3s ease;
    z-index: 1050;
    overflow-y: auto;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
}

.sidebar.collapsed {
    transform: translateX(-280px);
}

.sidebar-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1040;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.sidebar-overlay.show {
    opacity: 1;
    visibility: visible;
}

/* Sidebar Header */
.sidebar-header {
    padding: 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.sidebar-title {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.sidebar-close {
    background: none;
    border: none;
    color: white;
    font-size: 1.2rem;
    cursor: pointer;
    padding: 5px;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s ease;
}

.sidebar-close:hover {
    background: rgba(255,255,255,0.1);
}

/* Navigation */
.sidebar-nav {
    padding: 20px 0;
}

.nav-section {
    margin: 20px 0 10px 0;
}

.nav-section-title {
    padding: 0 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    color: rgba(255,255,255,0.6);
    margin-bottom: 10px;
}

.nav-item {
    margin: 2px 0;
}

.nav-link {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    color: rgba(255,255,255,0.8);
    text-decoration: none;
    transition: all 0.2s ease;
    position: relative;
}

.nav-link:hover {
    background: rgba(255,255,255,0.1);
    color: white;
    transform: translateX(5px);
}

.nav-link.active {
    background: rgba(255,255,255,0.2);
    color: white;
    border-right: 3px solid white;
}

.nav-link i {
    width: 20px;
    margin-right: 12px;
    font-size: 1.1rem;
}

/* Dropdown Styles */
.dropdown-toggle {
    position: relative;
}

.dropdown-arrow {
    margin-left: auto;
    font-size: 0.8rem;
    transition: transform 0.2s ease;
}

.dropdown-toggle.active .dropdown-arrow {
    transform: rotate(90deg);
}

.dropdown-menu {
    max-height: 0;
    overflow: hidden;
    background: rgba(0,0,0,0.2);
    transition: max-height 0.3s ease;
}

.dropdown-menu.show {
    max-height: 300px;
}

.dropdown-link {
    display: flex;
    align-items: center;
    padding: 10px 50px;
    color: rgba(255,255,255,0.7);
    text-decoration: none;
    transition: all 0.2s ease;
    font-size: 0.9rem;
}

.dropdown-link:hover {
    background: rgba(255,255,255,0.1);
    color: white;
    transform: translateX(5px);
}

.dropdown-link i {
    width: 16px;
    margin-right: 10px;
}

/* Toggle Button */
.sidebar-toggle {
    position: fixed;
    top: 20px;
    left: 20px;
    z-index: 1060;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 8px;
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,123,255,0.3);
    transition: all 0.2s ease;
    font-size: 1.2rem;
}

.sidebar-toggle:hover {
    background: #0056b3;
    transform: scale(1.1);
}

/* Main Content */
.main-content {
    margin-left: 280px;
    min-height: 100vh;
    background: #f8f9fa;
    transition: margin-left 0.3s ease;
    padding: 20px;
}

.main-content.expanded {
    margin-left: 0;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-280px);
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
    
    .main-content {
        margin-left: 0;
    }
    
    .sidebar-toggle {
        left: 20px;
    }
}

/* Text Colors */
.text-danger {
    color: #ff6b6b !important;
}

.text-danger:hover {
    color: #ff5252 !important;
}
</style>

<script>
// Clean Sidebar JavaScript
let currentDropdown = null;

// Toggle sidebar visibility
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const overlay = document.getElementById('sidebarOverlay');
    
    const isMobile = window.innerWidth <= 768;
    
    if (isMobile) {
        // Mobile behavior
        if (sidebar.classList.contains('show')) {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        } else {
            sidebar.classList.add('show');
            overlay.classList.add('show');
        }
    } else {
        // Desktop behavior
        if (sidebar.classList.contains('collapsed')) {
            sidebar.classList.remove('collapsed');
            mainContent.classList.remove('expanded');
            localStorage.setItem('sidebarCollapsed', 'false');
        } else {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
            localStorage.setItem('sidebarCollapsed', 'true');
        }
    }
}

// Close sidebar (mobile)
function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    sidebar.classList.remove('show');
    overlay.classList.remove('show');
    
    // Close any open dropdowns
    if (currentDropdown) {
        currentDropdown.classList.remove('show');
        const toggle = document.querySelector(`[onclick="toggleDropdown('${currentDropdown.id}')"]`);
        if (toggle) {
            toggle.classList.remove('active');
        }
        currentDropdown = null;
    }
}

// Toggle dropdown menus
function toggleDropdown(dropdownId) {
    const dropdown = document.getElementById(dropdownId);
    const toggle = document.querySelector(`[onclick="toggleDropdown('${dropdownId}')"]`);
    
    // Close current dropdown if different one is clicked
    if (currentDropdown && currentDropdown !== dropdown) {
        currentDropdown.classList.remove('show');
        const currentToggle = document.querySelector(`[onclick="toggleDropdown('${currentDropdown.id}')"]`);
        if (currentToggle) {
            currentToggle.classList.remove('active');
        }
    }
    
    // Toggle the clicked dropdown
    if (dropdown.classList.contains('show')) {
        dropdown.classList.remove('show');
        toggle.classList.remove('active');
        currentDropdown = null;
    } else {
        dropdown.classList.add('show');
        toggle.classList.add('active');
        currentDropdown = dropdown;
    }
}

// Initialize sidebar
document.addEventListener('DOMContentLoaded', function() {
    // Restore sidebar state
    const savedState = localStorage.getItem('sidebarCollapsed');
    if (savedState === 'true' && window.innerWidth > 768) {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        sidebar.classList.add('collapsed');
        mainContent.classList.add('expanded');
    }
    
    // Handle window resize
    window.addEventListener('resize', function() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const overlay = document.getElementById('sidebarOverlay');
        
        if (window.innerWidth > 768) {
            // Desktop mode
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            
            const savedState = localStorage.getItem('sidebarCollapsed');
            if (savedState === 'true') {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
            } else {
                sidebar.classList.remove('collapsed');
                mainContent.classList.remove('expanded');
            }
        } else {
            // Mobile mode
            sidebar.classList.remove('collapsed');
            sidebar.classList.remove('show');
            mainContent.classList.remove('expanded');
            overlay.classList.remove('show');
        }
    });
});
</script>
