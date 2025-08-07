<?php
$page_title = "Core Modules Manager - HRMS";

// Include authentication and database
require_once '../auth_check.php';
require_once '../db.php';

// Include layouts
require_once 'hrms_header_simple.php';
require_once 'hrms_sidebar_simple.php';

// Include HRMS UI fix
$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'] ?? 'employee';

$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'] ?? 'employee';
?>

<!-- Page Content Starts Here -->
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

<?php require_once 'hrms_footer_simple.php'; ?>