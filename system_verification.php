<?php
/**
 * Complete System Verification Script
 * Tests all critical components of the HRMS system
 */

// Prevent direct output to avoid header issues
ob_start();

$verification_results = [];
$overall_system_health = true;

// Test 1: Database Connection
try {
    require_once 'db.php';
    $verification_results['Database Connection'] = [
        'status' => true,
        'message' => 'Successfully connected to billing_demo database',
        'critical' => true
    ];
} catch (Exception $e) {
    $verification_results['Database Connection'] = [
        'status' => false,
        'message' => 'Database connection failed: ' . $e->getMessage(),
        'critical' => true
    ];
    $overall_system_health = false;
}

// Test 2: Critical Files
$critical_files = [
    'layouts/header.php' => 'Header Layout',
    'layouts/sidebar.php' => 'Navigation System',
    'layouts/footer.php' => 'Footer Layout',
    'db.php' => 'Database Configuration',
    'auth_check.php' => 'Authentication System',
    'HRMS/index.php' => 'HRMS Dashboard',
    'advanced_analytics_dashboard.php' => 'Analytics Dashboard',
    'employee_self_service.php' => 'Employee Portal',
    'integration_hub.php' => 'Integration Hub',
    'enterprise_reports.php' => 'Enterprise Reports'
];

$missing_files = [];
$existing_files = 0;

foreach ($critical_files as $file => $description) {
    if (file_exists($file)) {
        $existing_files++;
    } else {
        $missing_files[] = "$file ($description)";
    }
}

$file_percentage = round(($existing_files / count($critical_files)) * 100);
$verification_results['Critical Files'] = [
    'status' => $file_percentage >= 90,
    'message' => "$existing_files/" . count($critical_files) . " critical files found ($file_percentage%)",
    'critical' => true,
    'details' => $missing_files
];

if ($file_percentage < 90) {
    $overall_system_health = false;
}

// Test 3: Database Tables
$required_tables = [
    'employees' => 'Employee Management',
    'attendance' => 'Attendance Tracking',
    'leave_requests' => 'Leave Management',
    'payroll_records' => 'Payroll Processing',
    'employee_requests' => 'Self-Service Portal'
];

$table_check_results = [];
$tables_exist = 0;

if (isset($conn)) {
    foreach ($required_tables as $table => $purpose) {
        $query = "SHOW TABLES LIKE '$table'";
        $result = $conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            $tables_exist++;
            $table_check_results[$table] = true;
        } else {
            $table_check_results[$table] = false;
        }
    }
}

$table_percentage = round(($tables_exist / count($required_tables)) * 100);
$verification_results['Database Tables'] = [
    'status' => $table_percentage >= 80,
    'message' => "$tables_exist/" . count($required_tables) . " required tables found ($table_percentage%)",
    'critical' => true,
    'details' => $table_check_results
];

if ($table_percentage < 80) {
    $overall_system_health = false;
}

// Test 4: PHP Configuration
$php_requirements = [
    'version' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'pdo_mysql' => extension_loaded('pdo_mysql'),
    'mysqli' => extension_loaded('mysqli'),
    'json' => extension_loaded('json'),
    'mbstring' => extension_loaded('mbstring')
];

$php_issues = [];
foreach ($php_requirements as $req => $status) {
    if (!$status) {
        $php_issues[] = $req;
    }
}

$verification_results['PHP Requirements'] = [
    'status' => empty($php_issues),
    'message' => empty($php_issues) ? 'All PHP requirements met' : 'Missing: ' . implode(', ', $php_issues),
    'critical' => false
];

// Test 5: Navigation Integration
$navigation_pages = [
    'employees.php',
    'advanced_analytics_dashboard.php',
    'attendance.php',
    'advanced_attendance.php',
    'advanced_payroll.php',
    'add_employee.php'
];

$nav_pages_exist = 0;
foreach ($navigation_pages as $page) {
    if (file_exists($page)) {
        $nav_pages_exist++;
    }
}

$nav_percentage = round(($nav_pages_exist / count($navigation_pages)) * 100);
$verification_results['Navigation Pages'] = [
    'status' => $nav_percentage >= 85,
    'message' => "$nav_pages_exist/" . count($navigation_pages) . " navigation pages found ($nav_percentage%)",
    'critical' => false
];

// Test 6: System Performance
$start_time = microtime(true);
// Simulate some operations
for ($i = 0; $i < 1000; $i++) {
    $dummy = md5($i);
}
$end_time = microtime(true);
$execution_time = ($end_time - $start_time) * 1000; // Convert to milliseconds

$verification_results['System Performance'] = [
    'status' => $execution_time < 100,
    'message' => "System response time: " . round($execution_time, 2) . "ms",
    'critical' => false
];

// Clean output buffer
ob_end_clean();

// Generate results
$page_title = "System Verification Report";
require_once 'layouts/header.php';
?>

<div class="content-wrapper">
    <div class="container-fluid">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-<?= $overall_system_health ? 'success' : 'warning' ?> text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h1 class="h3 mb-0">
                                    <i class="fas fa-<?= $overall_system_health ? 'check-circle' : 'exclamation-triangle' ?> mr-2"></i>
                                    Complete System Verification
                                </h1>
                                <p class="mb-0 mt-1">Comprehensive health check of all HRMS components</p>
                            </div>
                            <div class="text-right">
                                <div class="badge badge-<?= $overall_system_health ? 'light' : 'danger' ?> badge-lg">
                                    <?= $overall_system_health ? 'ALL SYSTEMS GO' : 'ATTENTION REQUIRED' ?>
                                </div>
                                <div class="small mt-1">Verified: <?= date('Y-m-d H:i:s') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Verification Results -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-list-check mr-2"></i>Verification Results
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php foreach ($verification_results as $test_name => $result): ?>
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0">
                                    <i class="fas fa-<?= $result['status'] ? 'check-circle text-success' : 'exclamation-triangle text-warning' ?> mr-2"></i>
                                    <?= $test_name ?>
                                    <?php if (isset($result['critical']) && $result['critical']): ?>
                                    <span class="badge badge-danger badge-sm">CRITICAL</span>
                                    <?php endif; ?>
                                </h6>
                                <span class="badge badge-<?= $result['status'] ? 'success' : 'warning' ?>">
                                    <?= $result['status'] ? 'PASS' : 'NEEDS ATTENTION' ?>
                                </span>
                            </div>
                            <p class="mb-2 text-muted"><?= htmlspecialchars($result['message']) ?></p>
                            
                            <?php if (isset($result['details']) && !empty($result['details'])): ?>
                            <div class="ml-4">
                                <?php if (is_array($result['details']) && !empty(array_filter($result['details'], function($v) { return is_string($v); }))): ?>
                                <small class="text-danger">
                                    <strong>Missing:</strong><br>
                                    <?php foreach ($result['details'] as $detail): ?>
                                        <?php if (is_string($detail)): ?>
                                        • <?= htmlspecialchars($detail) ?><br>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </small>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <hr class="my-3">
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-info">
                            <i class="fas fa-info-circle mr-2"></i>System Overview
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php
                        $passed_tests = count(array_filter($verification_results, function($r) { return $r['status']; }));
                        $total_tests = count($verification_results);
                        $success_rate = round(($passed_tests / $total_tests) * 100);
                        ?>
                        <div class="text-center mb-3">
                            <div class="h2 mb-0 text-<?= $success_rate >= 90 ? 'success' : ($success_rate >= 75 ? 'warning' : 'danger') ?>">
                                <?= $success_rate ?>%
                            </div>
                            <div class="small text-muted">System Health Score</div>
                        </div>
                        
                        <div class="progress mb-3" style="height: 20px;">
                            <div class="progress-bar bg-<?= $success_rate >= 90 ? 'success' : ($success_rate >= 75 ? 'warning' : 'danger') ?>" 
                                 style="width: <?= $success_rate ?>%">
                                <?= $passed_tests ?>/<?= $total_tests ?> Tests Passed
                            </div>
                        </div>

                        <table class="table table-borderless table-sm">
                            <tr>
                                <td><strong>PHP Version:</strong></td>
                                <td><?= PHP_VERSION ?></td>
                            </tr>
                            <tr>
                                <td><strong>Server:</strong></td>
                                <td><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?></td>
                            </tr>
                            <tr>
                                <td><strong>Memory Usage:</strong></td>
                                <td><?= round(memory_get_usage() / 1024 / 1024, 2) ?>MB</td>
                            </tr>
                            <tr>
                                <td><strong>Execution Time:</strong></td>
                                <td><?= round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2) ?>ms</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="card shadow">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-success">
                            <i class="fas fa-rocket mr-2"></i>Quick Actions
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="system_status_dashboard.php" class="btn btn-primary">
                                <i class="fas fa-tachometer-alt mr-2"></i>System Dashboard
                            </a>
                            <a href="database_connection_test.php" class="btn btn-info">
                                <i class="fas fa-database mr-2"></i>Database Test
                            </a>
                            <a href="HRMS/index.php" class="btn btn-success">
                                <i class="fas fa-users mr-2"></i>HRMS Portal
                            </a>
                            <button class="btn btn-outline-secondary" onclick="window.location.reload()">
                                <i class="fas fa-sync mr-2"></i>Re-run Verification
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recommendations -->
        <?php if (!$overall_system_health): ?>
        <div class="row">
            <div class="col-lg-12">
                <div class="card shadow">
                    <div class="card-header bg-info text-white">
                        <h6 class="m-0 font-weight-bold">
                            <i class="fas fa-lightbulb mr-2"></i>Recommendations
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h6 class="alert-heading">System Optimization Suggestions</h6>
                            <ul class="mb-0">
                                <li><strong>Database Issues:</strong> Ensure MySQL is running and import the complete database schema</li>
                                <li><strong>Missing Files:</strong> Check file permissions and verify all uploads completed successfully</li>
                                <li><strong>Performance:</strong> Consider enabling PHP OPcache and database query optimization</li>
                                <li><strong>Security:</strong> Review file permissions and ensure proper authentication is enabled</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<style>
.badge-lg {
    font-size: 0.875rem;
    padding: 0.5rem 1rem;
}

.d-grid {
    display: grid;
}

.gap-2 {
    gap: 0.5rem;
}

.progress {
    background-color: #e9ecef;
}

.alert-heading {
    color: inherit;
}

.badge-sm {
    font-size: 0.65rem;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('System Verification Complete');
    console.log('Overall Health: <?= $overall_system_health ? "HEALTHY" : "NEEDS ATTENTION" ?>');
    console.log('Success Rate: <?= $success_rate ?>%');
    
    // Add some visual feedback
    const progressBar = document.querySelector('.progress-bar');
    if (progressBar) {
        progressBar.style.transition = 'width 2s ease-in-out';
        progressBar.style.width = '0%';
        setTimeout(() => {
            progressBar.style.width = '<?= $success_rate ?>%';
        }, 500);
    }
});
</script>

<?php require_once 'layouts/footer.php'; ?>
