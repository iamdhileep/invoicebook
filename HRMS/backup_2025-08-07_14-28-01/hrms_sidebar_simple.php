<?php
// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>

<!-- Sidebar Toggle (Mobile) -->
<button class="hrms-sidebar-toggle" onclick="toggleHrmsSidebar()">
    <i class="bi bi-list"></i>
</button>

<!-- HRMS Sidebar -->
<nav class="hrms-sidebar" id="hrmsSidebar">
    <div class="hrms-sidebar-header">
        <h5 class="mb-1">BillBook Pro</h5>
        <small>HRMS System</small>
    </div>
    
    <div class="hrms-sidebar-nav p-0">
        <!-- Dashboard Section -->
        <div class="hrms-nav-section">
            <div class="hrms-nav-section-title">Dashboard</div>
            <div class="hrms-nav-item">
                <a href="../pages/dashboard/dashboard.php" class="hrms-nav-link">
                    <i class="bi bi-house"></i>
                    <span>Main Dashboard</span>
                </a>
            </div>
            <div class="hrms-nav-item">
                <a href="index.php" class="hrms-nav-link <?= $current_page === 'index' ? 'active' : '' ?>">
                    <i class="bi bi-people"></i>
                    <span>HRMS Dashboard</span>
                </a>
            </div>
        </div>
        
        <!-- Employee Management -->
        <div class="hrms-nav-section">
            <div class="hrms-nav-section-title">Employee Management</div>
            <div class="hrms-nav-item">
                <a href="employee_directory.php" class="hrms-nav-link <?= $current_page === 'employee_directory' ? 'active' : '' ?>">
                    <i class="bi bi-person-lines-fill"></i>
                    <span>Employee Directory</span>
                </a>
            </div>
            <div class="hrms-nav-item">
                <a href="employee_onboarding.php" class="hrms-nav-link <?= $current_page === 'employee_onboarding' ? 'active' : '' ?>">
                    <i class="bi bi-person-plus"></i>
                    <span>Onboarding</span>
                </a>
            </div>
            <div class="hrms-nav-item">
                <a href="employee_profile.php" class="hrms-nav-link <?= $current_page === 'employee_profile' ? 'active' : '' ?>">
                    <i class="bi bi-person-badge"></i>
                    <span>Employee Profiles</span>
                </a>
            </div>
            <div class="hrms-nav-item">
                <a href="department_management.php" class="hrms-nav-link <?= $current_page === 'department_management' ? 'active' : '' ?>">
                    <i class="bi bi-diagram-3"></i>
                    <span>Departments</span>
                </a>
            </div>
        </div>
        
        <!-- Attendance & Leave -->
        <div class="hrms-nav-section">
            <div class="hrms-nav-section-title">Attendance & Leave</div>
            <div class="hrms-nav-item">
                <a href="attendance_management.php" class="hrms-nav-link <?= $current_page === 'attendance_management' ? 'active' : '' ?>">
                    <i class="bi bi-clock"></i>
                    <span>Attendance</span>
                </a>
            </div>
            <div class="hrms-nav-item">
                <a href="leave_management.php" class="hrms-nav-link <?= $current_page === 'leave_management' ? 'active' : '' ?>">
                    <i class="bi bi-calendar-x"></i>
                    <span>Leave Management</span>
                </a>
            </div>
            <div class="hrms-nav-item">
                <a href="time_tracking.php" class="hrms-nav-link <?= $current_page === 'time_tracking' ? 'active' : '' ?>">
                    <i class="bi bi-stopwatch"></i>
                    <span>Time Tracking</span>
                </a>
            </div>
            <div class="hrms-nav-item">
                <a href="shift_management.php" class="hrms-nav-link <?= $current_page === 'shift_management' ? 'active' : '' ?>">
                    <i class="bi bi-calendar2-week"></i>
                    <span>Shift Management</span>
                </a>
            </div>
        </div>
        
        <!-- Payroll & Benefits -->
        <div class="hrms-nav-section">
            <div class="hrms-nav-section-title">Payroll & Benefits</div>
            <div class="hrms-nav-item">
                <a href="payroll_processing.php" class="hrms-nav-link <?= $current_page === 'payroll_processing' ? 'active' : '' ?>">
                    <i class="bi bi-cash-coin"></i>
                    <span>Payroll</span>
                </a>
            </div>
            <div class="hrms-nav-item">
                <a href="salary_structure.php" class="hrms-nav-link <?= $current_page === 'salary_structure' ? 'active' : '' ?>">
                    <i class="bi bi-currency-dollar"></i>
                    <span>Salary Structure</span>
                </a>
            </div>
            <div class="hrms-nav-item">
                <a href="benefits_management.php" class="hrms-nav-link <?= $current_page === 'benefits_management' ? 'active' : '' ?>">
                    <i class="bi bi-heart"></i>
                    <span>Benefits</span>
                </a>
            </div>
        </div>
        
        <!-- Performance & Training -->
        <div class="hrms-nav-section">
            <div class="hrms-nav-section-title">Performance & Training</div>
            <div class="hrms-nav-item">
                <a href="performance_management.php" class="hrms-nav-link <?= $current_page === 'performance_management' ? 'active' : '' ?>">
                    <i class="bi bi-graph-up"></i>
                    <span>Performance</span>
                </a>
            </div>
            <div class="hrms-nav-item">
                <a href="goal_management.php" class="hrms-nav-link <?= $current_page === 'goal_management' ? 'active' : '' ?>">
                    <i class="bi bi-bullseye"></i>
                    <span>Goals</span>
                </a>
            </div>
            <div class="hrms-nav-item">
                <a href="training_management.php" class="hrms-nav-link <?= $current_page === 'training_management' ? 'active' : '' ?>">
                    <i class="bi bi-graduation-cap"></i>
                    <span>Training</span>
                </a>
            </div>
            <div class="hrms-nav-item">
                <a href="career_development.php" class="hrms-nav-link <?= $current_page === 'career_development' ? 'active' : '' ?>">
                    <i class="bi bi-arrow-up-right"></i>
                    <span>Career Development</span>
                </a>
            </div>
        </div>
        
        <!-- Reports & Analytics -->
        <div class="hrms-nav-section">
            <div class="hrms-nav-section-title">Reports</div>
            <div class="hrms-nav-item">
                <a href="employee_reports.php" class="hrms-nav-link <?= $current_page === 'employee_reports' ? 'active' : '' ?>">
                    <i class="bi bi-file-earmark-bar-graph"></i>
                    <span>Employee Reports</span>
                </a>
            </div>
            <div class="hrms-nav-item">
                <a href="payroll_reports.php" class="hrms-nav-link <?= $current_page === 'payroll_reports' ? 'active' : '' ?>">
                    <i class="bi bi-file-earmark-spreadsheet"></i>
                    <span>Payroll Reports</span>
                </a>
            </div>
            <div class="hrms-nav-item">
                <a href="advanced_analytics.php" class="hrms-nav-link <?= $current_page === 'advanced_analytics' ? 'active' : '' ?>">
                    <i class="bi bi-bar-chart"></i>
                    <span>Analytics</span>
                </a>
            </div>
        </div>
        
        <!-- System -->
        <div class="hrms-nav-section">
            <div class="hrms-nav-section-title">System</div>
            <div class="hrms-nav-item">
                <a href="employee_self_service.php" class="hrms-nav-link <?= $current_page === 'employee_self_service' ? 'active' : '' ?>">
                    <i class="bi bi-person-gear"></i>
                    <span>Self Service</span>
                </a>
            </div>
            <div class="hrms-nav-item">
                <a href="../logout.php" class="hrms-nav-link">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- Start Main Content Area -->
<div class="hrms-main-content">
    <div class="hrms-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0"><?= htmlspecialchars($page_title ?? 'HRMS Dashboard') ?></h4>
                <small class="text-muted">Human Resource Management System</small>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted">Welcome, <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
                <div class="dropdown">
                    <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="../settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <div class="hrms-content">
        <!-- Page content will go here -->
