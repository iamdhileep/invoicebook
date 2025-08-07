        <div class="hrms-sidebar" id="sidebar">
            <!-- Brand Section -->
            <div class="hrms-brand">
                <h4><i class="bi bi-people-fill me-2"></i>HRMS</h4>
                <small class="text-light opacity-75">Human Resource Management</small>
            </div>

            <!-- Navigation Menu -->
            <div class="hrms-nav">
                <!-- Dashboard Section -->
                <div class="nav-section-title">Dashboard</div>
                <a href="index.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
                    <i class="bi bi-house-door"></i>
                    <span>Dashboard</span>
                </a>

                <!-- Employee Management -->
                <div class="nav-section-title">Employee Management</div>
                <a href="employee_directory.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'employee_directory.php' ? 'active' : '' ?>">
                    <i class="bi bi-people"></i>
                    <span>Employee Directory</span>
                </a>
                <a href="employee_onboarding.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'employee_onboarding.php' ? 'active' : '' ?>">
                    <i class="bi bi-person-plus"></i>
                    <span>Employee Onboarding</span>
                </a>
                <a href="employee_profile.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'employee_profile.php' ? 'active' : '' ?>">
                    <i class="bi bi-person-badge"></i>
                    <span>Employee Profiles</span>
                </a>
                <a href="department_management.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'department_management.php' ? 'active' : '' ?>">
                    <i class="bi bi-diagram-3"></i>
                    <span>Departments</span>
                </a>

                <!-- Attendance & Time -->
                <div class="nav-section-title">Attendance & Time</div>
                <a href="attendance_management.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'attendance_management.php' ? 'active' : '' ?>">
                    <i class="bi bi-clock"></i>
                    <span>Attendance</span>
                </a>
                <a href="time_tracking.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'time_tracking.php' ? 'active' : '' ?>">
                    <i class="bi bi-stopwatch"></i>
                    <span>Time Tracking</span>
                </a>
                <a href="shift_management.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'shift_management.php' ? 'active' : '' ?>">
                    <i class="bi bi-calendar2-week"></i>
                    <span>Shift Management</span>
                </a>

                <!-- Leave Management -->
                <div class="nav-section-title">Leave Management</div>
                <a href="leave_management.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'leave_management.php' ? 'active' : '' ?>">
                    <i class="bi bi-calendar-x"></i>
                    <span>Leave Applications</span>
                </a>

                <!-- Payroll -->
                <div class="nav-section-title">Payroll</div>
                <a href="payroll_processing.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'payroll_processing.php' ? 'active' : '' ?>">
                    <i class="bi bi-cash-coin"></i>
                    <span>Payroll Processing</span>
                </a>
                <a href="salary_structure.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'salary_structure.php' ? 'active' : '' ?>">
                    <i class="bi bi-calculator"></i>
                    <span>Salary Structure</span>
                </a>
                <a href="tax_management.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'tax_management.php' ? 'active' : '' ?>">
                    <i class="bi bi-receipt"></i>
                    <span>Tax Management</span>
                </a>

                <!-- Performance -->
                <div class="nav-section-title">Performance</div>
                <a href="performance_management.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'performance_management.php' ? 'active' : '' ?>">
                    <i class="bi bi-graph-up"></i>
                    <span>Performance Reviews</span>
                </a>
                <a href="goal_management.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'goal_management.php' ? 'active' : '' ?>">
                    <i class="bi bi-target"></i>
                    <span>Goal Management</span>
                </a>

                <!-- Training & Development -->
                <div class="nav-section-title">Training</div>
                <a href="training_management.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'training_management.php' ? 'active' : '' ?>">
                    <i class="bi bi-book"></i>
                    <span>Training Programs</span>
                </a>
                <a href="skill_development.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'skill_development.php' ? 'active' : '' ?>">
                    <i class="bi bi-mortarboard"></i>
                    <span>Skill Development</span>
                </a>

                <!-- Reports & Analytics -->
                <div class="nav-section-title">Analytics</div>
                <a href="advanced_analytics.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'advanced_analytics.php' ? 'active' : '' ?>">
                    <i class="bi bi-bar-chart"></i>
                    <span>Advanced Analytics</span>
                </a>
                <a href="custom_reports.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'custom_reports.php' ? 'active' : '' ?>">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>Custom Reports</span>
                </a>
                <a href="workforce_analytics.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'workforce_analytics.php' ? 'active' : '' ?>">
                    <i class="bi bi-people-fill"></i>
                    <span>Workforce Analytics</span>
                </a>

                <!-- User Panels -->
                <div class="nav-section-title">User Panels</div>
                <a href="hr_panel.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'hr_panel.php' ? 'active' : '' ?>">
                    <i class="bi bi-person-gear"></i>
                    <span>HR Panel</span>
                </a>
                <a href="manager_panel.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'manager_panel.php' ? 'active' : '' ?>">
                    <i class="bi bi-person-workspace"></i>
                    <span>Manager Panel</span>
                </a>
                <a href="employee_panel.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'employee_panel.php' ? 'active' : '' ?>">
                    <i class="bi bi-person-circle"></i>
                    <span>Employee Panel</span>
                </a>

                <!-- System -->
                <div class="nav-section-title">System</div>
                <a href="system_diagnostics.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'system_diagnostics.php' ? 'active' : '' ?>">
                    <i class="bi bi-shield-check"></i>
                    <span>System Diagnostics</span>
                </a>
                <a href="enhancement_suite.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'enhancement_suite.php' ? 'active' : '' ?>">
                    <i class="bi bi-rocket"></i>
                    <span>Enhancement Suite</span>
                </a>

                <!-- Quick Actions -->
                <div class="nav-section-title">Quick Actions</div>
                <a href="../dashboard.php" class="nav-link">
                    <i class="bi bi-arrow-left-circle"></i>
                    <span>Back to Main System</span>
                </a>
            </div>
        </div>

        <div class="hrms-main-content">
            <!-- Dark Mode Toggle -->
            <button class="dark-mode-toggle" onclick="toggleDarkMode()" title="Toggle Dark Mode">
                <i class="bi bi-moon-stars-fill"></i>
            </button>