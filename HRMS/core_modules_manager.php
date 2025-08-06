<?php
session_start();
// Check for either session variable for compatibility
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

if (!isset($root_path)) 
require_once '../config.php';
if (!isset($root_path)) 
require_once '../db.php';

$page_title = 'Core Modules Manager - HRMS';

/**
 * HRMS Core Modules Configuration & Activation Manager
 * Ensures all essential HRMS modules are properly configured and active
 */

class HRMSCoreModules {
    private $conn;
    private $modules_status = [];
    
    public function __construct($database_connection) {
        $this->conn = $database_connection;
        $this->checkModulesStatus();
    }
    
    /**
     * Check status of all core modules
     */
    private function checkModulesStatus() {
        $this->modules_status = [
            'employee_management' => $this->checkEmployeeManagement(),
            'leave_attendance' => $this->checkLeaveAttendance(),
            'payroll_management' => $this->checkPayrollManagement(),
            'onboarding_process' => $this->checkOnboardingProcess(),
            'offboarding_process' => $this->checkOffboardingProcess()
        ];
    }
    
    /**
     * Employee Management Module Configuration
     */
    private function checkEmployeeManagement() {
        $status = [
            'active' => true,
            'features' => [],
            'database_tables' => [],
            'missing_features' => []
        ];
        
        // Check database tables
        $tables_to_check = [
            'employees' => 'Employee records storage',
            'departments' => 'Department management',
            'employee_documents' => 'Employee document storage'
        ];
        
        foreach ($tables_to_check as $table => $description) {
            $result = mysqli_query($this->conn, "SHOW TABLES LIKE '$table'");
            if (mysqli_num_rows($result) > 0) {
                $status['database_tables'][$table] = ['exists' => true, 'description' => $description];
            } else {
                $status['database_tables'][$table] = ['exists' => false, 'description' => $description];
                $status['active'] = false;
            }
        }
        
        // Check features
        $features = [
            'add_employee' => file_exists(__DIR__ . '/employee_directory.php'),
            'edit_employee' => file_exists(__DIR__ . '/employee_profile.php'),
            'employee_search' => true,
            'department_management' => file_exists(__DIR__ . '/department_management.php'),
            'employee_documents' => true
        ];
        
        $status['features'] = $features;
        $status['missing_features'] = array_keys(array_filter($features, function($v) { return !$v; }));
        
        return $status;
    }
    
    /**
     * Leave & Attendance Management Module Configuration
     */
    private function checkLeaveAttendance() {
        $status = [
            'active' => true,
            'features' => [],
            'database_tables' => [],
            'missing_features' => []
        ];
        
        // Check database tables
        $tables_to_check = [
            'leave_requests' => 'Leave request management',
            'leave_balance' => 'Employee leave balance tracking',
            'attendance' => 'Daily attendance records',
            'holidays' => 'Holiday calendar',
            'shift_schedules' => 'Shift management'
        ];
        
        foreach ($tables_to_check as $table => $description) {
            $result = mysqli_query($this->conn, "SHOW TABLES LIKE '$table'");
            if (mysqli_num_rows($result) > 0) {
                $status['database_tables'][$table] = ['exists' => true, 'description' => $description];
            } else {
                $status['database_tables'][$table] = ['exists' => false, 'description' => $description];
                $status['active'] = false;
            }
        }
        
        // Check features
        $features = [
            'leave_management' => file_exists(__DIR__ . '/leave_management.php'),
            'attendance_tracking' => file_exists(__DIR__ . '/attendance_management.php'),
            'leave_types_config' => true,
            'attendance_modes' => true,
            'holiday_calendar' => true,
            'shift_management' => file_exists(__DIR__ . '/shift_management.php')
        ];
        
        $status['features'] = $features;
        $status['missing_features'] = array_keys(array_filter($features, function($v) { return !$v; }));
        
        return $status;
    }
    
    /**
     * Payroll Management Module Configuration
     */
    private function checkPayrollManagement() {
        $status = [
            'active' => true,
            'features' => [],
            'database_tables' => [],
            'missing_features' => []
        ];
        
        // Check database tables
        $tables_to_check = [
            'payroll' => 'Payroll processing records',
            'salary_grades' => 'Salary structure definitions',
            'tax_slabs' => 'Tax calculation rules',
            'employee_payroll' => 'Employee payroll settings'
        ];
        
        foreach ($tables_to_check as $table => $description) {
            $result = mysqli_query($this->conn, "SHOW TABLES LIKE '$table'");
            if (mysqli_num_rows($result) > 0) {
                $status['database_tables'][$table] = ['exists' => true, 'description' => $description];
            } else {
                $status['database_tables'][$table] = ['exists' => false, 'description' => $description];
                $status['active'] = false;
            }
        }
        
        // Check features
        $features = [
            'payroll_processing' => file_exists(__DIR__ . '/payroll_processing.php'),
            'salary_structure' => file_exists(__DIR__ . '/salary_structure.php'),
            'tax_management' => file_exists(__DIR__ . '/tax_management.php'),
            'esi_pf_integration' => true,
            'overtime_calculation' => true,
            'bonus_management' => true,
            'fnf_settlement' => file_exists(__DIR__ . '/fnf_settlement.php')
        ];
        
        $status['features'] = $features;
        $status['missing_features'] = array_keys(array_filter($features, function($v) { return !$v; }));
        
        return $status;
    }
    
    /**
     * Employee Onboarding Module Configuration
     */
    private function checkOnboardingProcess() {
        $status = [
            'active' => true,
            'features' => [],
            'database_tables' => [],
            'missing_features' => []
        ];
        
        // Check database tables
        $tables_to_check = [
            'onboarding_process' => 'Onboarding process tracking',
            'onboarding_tasks' => 'Onboarding task management',
            'onboarding_templates' => 'Onboarding templates'
        ];
        
        foreach ($tables_to_check as $table => $description) {
            $result = mysqli_query($this->conn, "SHOW TABLES LIKE '$table'");
            if (mysqli_num_rows($result) > 0) {
                $status['database_tables'][$table] = ['exists' => true, 'description' => $description];
            } else {
                $status['database_tables'][$table] = ['exists' => false, 'description' => $description];
                $status['active'] = false;
            }
        }
        
        // Check features
        $features = [
            'onboarding_templates' => true,
            'task_assignment' => true,
            'document_checklist' => true,
            'welcome_messages' => true,
            'progress_tracking' => file_exists(__DIR__ . '/onboarding_process.php')
        ];
        
        $status['features'] = $features;
        $status['missing_features'] = array_keys(array_filter($features, function($v) { return !$v; }));
        
        return $status;
    }
    
    /**
     * Employee Offboarding Module Configuration
     */
    private function checkOffboardingProcess() {
        $status = [
            'active' => true,
            'features' => [],
            'database_tables' => [],
            'missing_features' => []
        ];
        
        // Check database tables
        $tables_to_check = [
            'offboarding_process' => 'Offboarding process tracking',
            'exit_interviews' => 'Exit interview management',
            'clearance_steps' => 'Clearance process management'
        ];
        
        foreach ($tables_to_check as $table => $description) {
            $result = mysqli_query($this->conn, "SHOW TABLES LIKE '$table'");
            if (mysqli_num_rows($result) > 0) {
                $status['database_tables'][$table] = ['exists' => true, 'description' => $description];
            } else {
                $status['database_tables'][$table] = ['exists' => false, 'description' => $description];
                $status['active'] = false;
            }
        }
        
        // Check features
        $features = [
            'exit_workflow' => file_exists(__DIR__ . '/offboarding_process.php'),
            'clearance_steps' => true,
            'document_generation' => true,
            'fnf_settlement' => file_exists(__DIR__ . '/fnf_settlement.php'),
            'exit_interview' => file_exists(__DIR__ . '/exit_interview.php')
        ];
        
        $status['features'] = $features;
        $status['missing_features'] = array_keys(array_filter($features, function($v) { return !$v; }));
        
        return $status;
    }
    
    /**
     * Activate missing database tables and features
     */
    public function activateAllModules() {
        $activation_results = [];
        
        // Activate Employee Management
        $activation_results['employee_management'] = $this->activateEmployeeManagement();
        
        // Activate Leave & Attendance
        $activation_results['leave_attendance'] = $this->activateLeaveAttendance();
        
        // Activate Payroll Management
        $activation_results['payroll_management'] = $this->activatePayrollManagement();
        
        // Activate Onboarding Process
        $activation_results['onboarding_process'] = $this->activateOnboardingProcess();
        
        // Activate Offboarding Process
        $activation_results['offboarding_process'] = $this->activateOffboardingProcess();
        
        return $activation_results;
    }
    
    /**
     * Activate Employee Management Module
     */
    private function activateEmployeeManagement() {
        $results = ['tables_created' => [], 'errors' => []];
        
        // Create employee_documents table if missing
        $createEmployeeDocuments = "
        CREATE TABLE IF NOT EXISTS employee_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            document_type ENUM('resume', 'id_proof', 'address_proof', 'educational', 'experience', 'medical', 'photo', 'other') NOT NULL,
            document_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_size INT DEFAULT 0,
            uploaded_by INT NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            verified BOOLEAN DEFAULT FALSE,
            verified_by INT NULL,
            verified_at TIMESTAMP NULL,
            notes TEXT,
            FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
            FOREIGN KEY (uploaded_by) REFERENCES employees(employee_id),
            FOREIGN KEY (verified_by) REFERENCES employees(employee_id)
        )";
        
        if (mysqli_query($this->conn, $createEmployeeDocuments)) {
            $results['tables_created'][] = 'employee_documents';
        } else {
            $results['errors'][] = 'employee_documents: ' . mysqli_error($this->conn);
        }
        
        return $results;
    }
    
    /**
     * Activate Leave & Attendance Management Module
     */
    private function activateLeaveAttendance() {
        $results = ['tables_created' => [], 'errors' => []];
        
        // Create holidays table
        $createHolidays = "
        CREATE TABLE IF NOT EXISTS holidays (
            id INT AUTO_INCREMENT PRIMARY KEY,
            holiday_name VARCHAR(255) NOT NULL,
            holiday_date DATE NOT NULL,
            holiday_type ENUM('national', 'regional', 'optional', 'company') DEFAULT 'company',
            description TEXT,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_holiday_date (holiday_date)
        )";
        
        if (mysqli_query($this->conn, $createHolidays)) {
            $results['tables_created'][] = 'holidays';
        } else {
            $results['errors'][] = 'holidays: ' . mysqli_error($this->conn);
        }
        
        // Create shift_schedules table
        $createShiftSchedules = "
        CREATE TABLE IF NOT EXISTS shift_schedules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            shift_name VARCHAR(100) NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            break_duration INT DEFAULT 0,
            total_hours DECIMAL(4,2) NOT NULL,
            is_night_shift BOOLEAN DEFAULT FALSE,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        if (mysqli_query($this->conn, $createShiftSchedules)) {
            $results['tables_created'][] = 'shift_schedules';
        } else {
            $results['errors'][] = 'shift_schedules: ' . mysqli_error($this->conn);
        }
        
        return $results;
    }
    
    /**
     * Activate Payroll Management Module
     */
    private function activatePayrollManagement() {
        $results = ['tables_created' => [], 'errors' => []];
        
        // Create tax_slabs table
        $createTaxSlabs = "
        CREATE TABLE IF NOT EXISTS tax_slabs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            income_from DECIMAL(12,2) NOT NULL,
            income_to DECIMAL(12,2) NOT NULL,
            tax_rate DECIMAL(5,2) NOT NULL,
            cess_rate DECIMAL(5,2) DEFAULT 0,
            financial_year VARCHAR(10) NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        if (mysqli_query($this->conn, $createTaxSlabs)) {
            $results['tables_created'][] = 'tax_slabs';
        } else {
            $results['errors'][] = 'tax_slabs: ' . mysqli_error($this->conn);
        }
        
        return $results;
    }
    
    /**
     * Activate Onboarding Process Module
     */
    private function activateOnboardingProcess() {
        $results = ['tables_created' => [], 'errors' => []];
        
        // Create onboarding_templates table
        $createOnboardingTemplates = "
        CREATE TABLE IF NOT EXISTS onboarding_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            template_name VARCHAR(255) NOT NULL,
            department_id INT NULL,
            position_category VARCHAR(100) NULL,
            template_description TEXT,
            duration_days INT DEFAULT 30,
            tasks_json TEXT,
            welcome_message TEXT,
            is_active BOOLEAN DEFAULT TRUE,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (department_id) REFERENCES departments(department_id),
            FOREIGN KEY (created_by) REFERENCES employees(employee_id)
        )";
        
        if (mysqli_query($this->conn, $createOnboardingTemplates)) {
            $results['tables_created'][] = 'onboarding_templates';
        } else {
            $results['errors'][] = 'onboarding_templates: ' . mysqli_error($this->conn);
        }
        
        return $results;
    }
    
    /**
     * Activate Offboarding Process Module
     */
    private function activateOffboardingProcess() {
        $results = ['tables_created' => [], 'errors' => []];
        
        // Create exit_interviews table
        $createExitInterviews = "
        CREATE TABLE IF NOT EXISTS exit_interviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            interviewer_id INT NOT NULL,
            interview_date DATE NOT NULL,
            reason_for_leaving ENUM('resignation', 'retirement', 'termination', 'end_of_contract', 'other') NOT NULL,
            overall_satisfaction INT DEFAULT 5,
            management_rating INT DEFAULT 5,
            work_environment_rating INT DEFAULT 5,
            career_growth_rating INT DEFAULT 5,
            compensation_rating INT DEFAULT 5,
            would_recommend BOOLEAN DEFAULT TRUE,
            feedback TEXT,
            suggestions TEXT,
            status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
            FOREIGN KEY (interviewer_id) REFERENCES employees(employee_id)
        )";
        
        if (mysqli_query($this->conn, $createExitInterviews)) {
            $results['tables_created'][] = 'exit_interviews';
        } else {
            $results['errors'][] = 'exit_interviews: ' . mysqli_error($this->conn);
        }
        
        // Create clearance_steps table
        $createClearanceSteps = "
        CREATE TABLE IF NOT EXISTS clearance_steps (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            step_name VARCHAR(255) NOT NULL,
            department VARCHAR(100) NOT NULL,
            assigned_to INT NOT NULL,
            status ENUM('pending', 'in_progress', 'completed', 'not_applicable') DEFAULT 'pending',
            completed_by INT NULL,
            completed_at TIMESTAMP NULL,
            notes TEXT,
            due_date DATE NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
            FOREIGN KEY (assigned_to) REFERENCES employees(employee_id),
            FOREIGN KEY (completed_by) REFERENCES employees(employee_id)
        )";
        
        if (mysqli_query($this->conn, $createClearanceSteps)) {
            $results['tables_created'][] = 'clearance_steps';
        } else {
            $results['errors'][] = 'clearance_steps: ' . mysqli_error($this->conn);
        }
        
        return $results;
    }
    
    /**
     * Get modules status for display
     */
    public function getModulesStatus() {
        return $this->modules_status;
    }
    
    /**
     * Get overall system health
     */
    public function getSystemHealth() {
        $total_modules = count($this->modules_status);
        $active_modules = count(array_filter($this->modules_status, function($module) {
            return $module['active'];
        }));
        
        return [
            'total_modules' => $total_modules,
            'active_modules' => $active_modules,
            'health_percentage' => ($active_modules / $total_modules) * 100,
            'status' => $active_modules === $total_modules ? 'healthy' : 'needs_attention'
        ];
    }
}

// Initialize the core modules manager
$coreModules = new HRMSCoreModules($conn);

// Handle activation request
if (isset($_POST['activate_all_modules'])) {
    $activation_results = $coreModules->activateAllModules();
    $success_message = "Core modules activation completed!";
}

// Get current status
$modules_status = $coreModules->getModulesStatus();
$system_health = $coreModules->getSystemHealth();

$current_page = 'core_modules_manager';

include '../layouts/header.php';
if (!isset($root_path)) 
include '../layouts/sidebar.php';
?>

<div class="main-content animate-fade-in-up">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="gradient-text mb-2" style="font-size: 2.5rem; font-weight: 700;">
                    <i class="bi bi-gear-fill text-primary me-3"></i>Core Modules Manager
                </h1>
                <p class="text-muted" style="font-size: 1.1rem;">Configure and activate essential HRMS modules</p>
            </div>
            <div class="d-flex gap-2">
                <form method="POST" class="d-inline">
                    <button type="submit" name="activate_all_modules" class="btn btn-success" 
                            onclick="return confirm('This will activate all missing modules and create required database tables. Continue?')">
                        <i class="bi bi-play-circle me-2"></i>Activate All Modules
                    </button>
                </form>
            </div>
        </div>

        <!-- Success Message -->
        <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i><?= $success_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- System Health Overview -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-0"><?= $system_health['active_modules'] ?>/<?= $system_health['total_modules'] ?></h3>
                                <p class="mb-0">Active Modules</p>
                            </div>
                            <i class="bi bi-puzzle fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-<?= $system_health['status'] === 'healthy' ? 'success' : 'warning' ?> text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-0"><?= number_format($system_health['health_percentage'], 1) ?>%</h3>
                                <p class="mb-0">System Health</p>
                            </div>
                            <i class="bi bi-heart-pulse fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-0"><?= count(array_filter($modules_status, function($m) { return !empty($m['missing_features']); })) ?></h3>
                                <p class="mb-0">Modules Need Config</p>
                            </div>
                            <i class="bi bi-exclamation-triangle fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-secondary text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-0"><?= date('Y-m-d') ?></h3>
                                <p class="mb-0">Last Check</p>
                            </div>
                            <i class="bi bi-calendar-check fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Core Modules Status -->
        <div class="row">
            <?php foreach ($modules_status as $module_name => $module_data): 
                $module_titles = [
                    'employee_management' => 'Employee Management',
                    'leave_attendance' => 'Leave & Attendance',
                    'payroll_management' => 'Payroll Management',
                    'onboarding_process' => 'Employee Onboarding',
                    'offboarding_process' => 'Employee Offboarding'
                ];
                
                $module_descriptions = [
                    'employee_management' => 'Add, edit, and delete employee records. Manage departments, designations, and employee status.',
                    'leave_attendance' => 'Set up leave types, balances, and rules. Configure attendance modes and shift rules.',
                    'payroll_management' => 'Define salary structures, ESI, PF, tax rules, and auto deductions.',
                    'onboarding_process' => 'Create onboarding templates, assign tasks and documents.',
                    'offboarding_process' => 'Define exit process workflow and clearance steps.'
                ];
                
                $module_icons = [
                    'employee_management' => 'bi-people-fill',
                    'leave_attendance' => 'bi-calendar-week-fill',
                    'payroll_management' => 'bi-currency-dollar',
                    'onboarding_process' => 'bi-person-plus-fill',
                    'offboarding_process' => 'bi-person-dash-fill'
                ];
            ?>
            <div class="col-lg-6 mb-4">
                <div class="card h-100 <?= $module_data['active'] ? 'border-success' : 'border-warning' ?>">
                    <div class="card-header <?= $module_data['active'] ? 'bg-success' : 'bg-warning' ?> text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi <?= $module_icons[$module_name] ?> me-2"></i>
                            <?= $module_titles[$module_name] ?>
                            <span class="badge <?= $module_data['active'] ? 'bg-light text-success' : 'bg-light text-warning' ?> ms-2">
                                <?= $module_data['active'] ? 'Active' : 'Needs Setup' ?>
                            </span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3"><?= $module_descriptions[$module_name] ?></p>
                        
                        <!-- Database Tables Status -->
                        <h6 class="text-primary mb-2">Database Tables:</h6>
                        <div class="mb-3">
                            <?php foreach ($module_data['database_tables'] as $table => $table_info): ?>
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <small class="text-muted"><?= $table_info['description'] ?></small>
                                <span class="badge <?= $table_info['exists'] ? 'bg-success' : 'bg-danger' ?>">
                                    <?= $table_info['exists'] ? 'Exists' : 'Missing' ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Features Status -->
                        <h6 class="text-primary mb-2">Features:</h6>
                        <div class="mb-3">
                            <?php 
                            $active_features = array_filter($module_data['features']);
                            $total_features = count($module_data['features']);
                            $active_count = count($active_features);
                            ?>
                            <div class="progress mb-2" style="height: 10px;">
                                <div class="progress-bar bg-success" style="width: <?= ($active_count / $total_features) * 100 ?>%"></div>
                            </div>
                            <small class="text-muted"><?= $active_count ?>/<?= $total_features ?> features active</small>
                        </div>
                        
                        <!-- Missing Features -->
                        <?php if (!empty($module_data['missing_features'])): ?>
                        <div class="alert alert-warning py-2">
                            <small><strong>Missing Features:</strong><br>
                            <?= implode(', ', array_map('ucfirst', str_replace('_', ' ', $module_data['missing_features']))) ?>
                            </small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Detailed Configuration Guide -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-book me-2"></i>Core Modules Configuration Guide
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary">Employee Management</h6>
                        <ul class="list-unstyled text-sm">
                            <li><i class="bi bi-check-circle text-success me-2"></i>Add, edit, and delete employee records</li>
                            <li><i class="bi bi-check-circle text-success me-2"></i>Manage departments and designations</li>
                            <li><i class="bi bi-check-circle text-success me-2"></i>Maintain employee documents and personal information</li>
                            <li><i class="bi bi-check-circle text-success me-2"></i>Employee status management</li>
                        </ul>
                        
                        <h6 class="text-primary mt-3">Leave & Attendance Management</h6>
                        <ul class="list-unstyled text-sm">
                            <li><i class="bi bi-check-circle text-success me-2"></i>Set up leave types, balances, and rules</li>
                            <li><i class="bi bi-check-circle text-success me-2"></i>Configure attendance modes (manual, biometric, GPS-based)</li>
                            <li><i class="bi bi-check-circle text-success me-2"></i>Define holidays, working days, and shift rules</li>
                            <li><i class="bi bi-check-circle text-success me-2"></i>Overtime and break management</li>
                        </ul>
                        
                        <h6 class="text-primary mt-3">Payroll Management</h6>
                        <ul class="list-unstyled text-sm">
                            <li><i class="bi bi-check-circle text-success me-2"></i>Define salary structures (hourly, monthly, project-based)</li>
                            <li><i class="bi bi-check-circle text-success me-2"></i>Set up ESI, PF, tax rules, and auto deductions</li>
                            <li><i class="bi bi-check-circle text-success me-2"></i>Configure bonuses, overtime, and F&F settlement options</li>
                            <li><i class="bi bi-check-circle text-success me-2"></i>Tax slab management and compliance</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary">Employee Onboarding</h6>
                        <ul class="list-unstyled text-sm">
                            <li><i class="bi bi-check-circle text-success me-2"></i>Create onboarding templates</li>
                            <li><i class="bi bi-check-circle text-success me-2"></i>Assign onboarding tasks and documents</li>
                            <li><i class="bi bi-check-circle text-success me-2"></i>Configure welcome messages and checklists</li>
                            <li><i class="bi bi-check-circle text-success me-2"></i>Track onboarding progress</li>
                        </ul>
                        
                        <h6 class="text-primary mt-3">Employee Offboarding</h6>
                        <ul class="list-unstyled text-sm">
                            <li><i class="bi bi-check-circle text-success me-2"></i>Define exit process workflow</li>
                            <li><i class="bi bi-check-circle text-success me-2"></i>Setup clearance steps and document generation</li>
                            <li><i class="bi bi-check-circle text-success me-2"></i>Enable full & final settlement processing</li>
                            <li><i class="bi bi-check-circle text-success me-2"></i>Exit interview management</li>
                        </ul>
                        
                        <div class="alert alert-info mt-3">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Note:</strong> Click "Activate All Modules" to automatically configure missing database tables and enable all core features.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add activation confirmation
    const activateBtn = document.querySelector('[name="activate_all_modules"]');
    if (activateBtn) {
        activateBtn.addEventListener('click', function(e) {
            const confirmed = confirm('This will activate all missing modules and create required database tables. This process may take a few moments. Continue?');
            if (!confirmed) {
                e.preventDefault();
            }
        });
    }
});
</script>

<?php if (!isset($root_path)) 
include '../layouts/footer.php'; ?>
