<?php
$page_title = "System Diagnostics - HRMS Health Check";

// Include authentication and database
require_once '../auth_check.php';
require_once '../db.php';

// Include layouts
require_once 'hrms_header_simple.php';
require_once 'hrms_sidebar_simple.php';

// System diagnostics functions
function checkDatabaseConnection($conn) {
    if ($conn->ping()) {
        return ['status' => 'success', 'message' => 'Database connection active', 'details' => 'MySQL server responding normally'];
    }
    return ['status' => 'error', 'message' => 'Database connection failed', 'details' => 'Cannot connect to MySQL server'];
}

function checkHRTables($conn) {
    $required_tables = ['hr_employees', 'hr_attendance', 'hr_leave_applications', 'hr_departments', 'hr_positions'];
    $existing_tables = [];
    $missing_tables = [];
    
    foreach ($required_tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            $existing_tables[] = $table;
        } else {
            $missing_tables[] = $table;
        }
    }
    
    if (empty($missing_tables)) {
        return ['status' => 'success', 'message' => 'All HR tables present', 'details' => count($existing_tables) . ' tables found'];
    } else {
        return ['status' => 'warning', 'message' => 'Some tables missing', 'details' => 'Missing: ' . implode(', ', $missing_tables)];
    }
}

function checkLayoutFiles() {
    $layout_files = [
        'hrms_header_simple.php',
        'hrms_sidebar_simple.php', 
        'hrms_footer_simple.php'
    ];
    
    $existing = [];
    $missing = [];
    
    foreach ($layout_files as $file) {
        if (file_exists($file)) {
            $existing[] = $file;
        } else {
            $missing[] = $file;
        }
    }
    
    if (empty($missing)) {
        return ['status' => 'success', 'message' => 'All layout files present', 'details' => count($existing) . ' files found'];
    } else {
        return ['status' => 'error', 'message' => 'Layout files missing', 'details' => 'Missing: ' . implode(', ', $missing)];
    }
}

function checkPHPVersion() {
    $version = PHP_VERSION;
    $major = (int)explode('.', $version)[0];
    $minor = (int)explode('.', $version)[1];
    
    if ($major >= 8 || ($major == 7 && $minor >= 4)) {
        return ['status' => 'success', 'message' => 'PHP version compatible', 'details' => "Running PHP $version"];
    } else {
        return ['status' => 'warning', 'message' => 'PHP version outdated', 'details' => "Running PHP $version, recommend 7.4+"];
    }
}

function getSystemStats($conn) {
    $stats = [];
    
    try {
        // Employee count
        $result = $conn->query("SELECT COUNT(*) as count FROM hr_employees WHERE status = 'active'");
        $stats['employees'] = $result ? $result->fetch_assoc()['count'] : 0;
        
        // Attendance records today
        $today = date('Y-m-d');
        $result = $conn->query("SELECT COUNT(*) as count FROM hr_attendance WHERE date = '$today'");
        $stats['attendance_today'] = $result ? $result->fetch_assoc()['count'] : 0;
        
        // Pending leave applications
        $result = $conn->query("SELECT COUNT(*) as count FROM hr_leave_applications WHERE status = 'pending'");
        $stats['pending_leaves'] = $result ? $result->fetch_assoc()['count'] : 0;
    } catch (Exception $e) {
        // Handle errors silently
        $stats['employees'] = 0;
        $stats['attendance_today'] = 0;
        $stats['pending_leaves'] = 0;
    }
    
    return $stats;
}

// Run all diagnostics
$diagnostics = [
    'database' => checkDatabaseConnection($conn),
    'tables' => checkHRTables($conn),
    'layouts' => checkLayoutFiles(),
    'php' => checkPHPVersion()
];

$system_stats = getSystemStats($conn);

// Calculate overall health score
$total_checks = count($diagnostics);
$passed_checks = 0;
foreach ($diagnostics as $check) {
    if ($check['status'] === 'success') $passed_checks++;
}
$health_score = round(($passed_checks / $total_checks) * 100);

// Determine health color
$health_color = $health_score >= 90 ? 'success' : ($health_score >= 70 ? 'warning' : 'danger');
?>

<!-- Page Content -->
<div class="container-fluid">
    <!-- System Health Overview -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-lg">
                <div class="card-header bg-<?= $health_color ?> text-white">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <h4 class="mb-0">
                                <i class="bi bi-shield-check me-2"></i>
                                HRMS System Health Check
                            </h4>
                            <small class="opacity-75">Last updated: <?= date('Y-m-d H:i:s') ?></small>
                        </div>
                        <div class="col-lg-4 text-end">
                            <div class="health-score">
                                <div class="fs-1 fw-bold"><?= $health_score ?>%</div>
                                <div class="small">Overall Health</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row g-4 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body">
                    <div class="text-primary mb-2">
                        <i class="bi bi-people-fill fs-1"></i>
                    </div>
                    <h3 class="mb-1"><?= number_format($system_stats['employees']) ?></h3>
                    <p class="text-muted mb-0 small">Active Employees</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body">
                    <div class="text-success mb-2">
                        <i class="bi bi-clock-fill fs-1"></i>
                    </div>
                    <h3 class="mb-1"><?= number_format($system_stats['attendance_today']) ?></h3>
                    <p class="text-muted mb-0 small">Attendance Today</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body">
                    <div class="text-warning mb-2">
                        <i class="bi bi-calendar-x-fill fs-1"></i>
                    </div>
                    <h3 class="mb-1"><?= number_format($system_stats['pending_leaves']) ?></h3>
                    <p class="text-muted mb-0 small">Pending Leaves</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body">
                    <div class="text-info mb-2">
                        <i class="bi bi-check-circle-fill fs-1"></i>
                    </div>
                    <h3 class="mb-1"><?= $passed_checks ?>/<?= $total_checks ?></h3>
                    <p class="text-muted mb-0 small">Checks Passed</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Diagnostics -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-clipboard-check me-2"></i>Detailed System Checks</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($diagnostics as $key => $diagnostic): ?>
                        <div class="diagnostic-item mb-3">
                            <div class="row align-items-center">
                                <div class="col-1">
                                    <?php
                                    $icon_class = $diagnostic['status'] === 'success' ? 'bi-check-circle-fill text-success' : 
                                                 ($diagnostic['status'] === 'warning' ? 'bi-exclamation-triangle-fill text-warning' : 
                                                 'bi-x-circle-fill text-danger');
                                    ?>
                                    <i class="bi <?= $icon_class ?> fs-4"></i>
                                </div>
                                <div class="col-3">
                                    <h6 class="mb-0 text-capitalize"><?= str_replace('_', ' ', $key) ?></h6>
                                </div>
                                <div class="col-4">
                                    <span class="badge bg-<?= $diagnostic['status'] === 'success' ? 'success' : ($diagnostic['status'] === 'warning' ? 'warning' : 'danger') ?>">
                                        <?= $diagnostic['message'] ?>
                                    </span>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted"><?= $diagnostic['details'] ?></small>
                                </div>
                            </div>
                        </div>
                        <?php if ($key !== array_key_last($diagnostics)): ?>
                            <hr class="my-3">
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- System Information -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>System Information</h5>
                </div>
                <div class="card-body">
                    <div class="system-info">
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <span class="small">PHP Version</span>
                            <span class="badge bg-light text-dark"><?= PHP_VERSION ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <span class="small">MySQL Version</span>
                            <span class="badge bg-light text-dark"><?= $conn->server_info ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <span class="small">Server Software</span>
                            <span class="badge bg-light text-dark"><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <span class="small">Memory Limit</span>
                            <span class="badge bg-light text-dark"><?= ini_get('memory_limit') ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-2">
                            <span class="small">Max Upload</span>
                            <span class="badge bg-light text-dark"><?= ini_get('upload_max_filesize') ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-tools me-2"></i>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary" onclick="location.reload()">
                            <i class="bi bi-arrow-clockwise me-2"></i>Refresh Diagnostics
                        </button>
                        <a href="index.php" class="btn btn-outline-success">
                            <i class="bi bi-house me-2"></i>Back to Dashboard
                        </a>
                        <a href="enhancement_suite.php" class="btn btn-outline-info">
                            <i class="bi bi-rocket me-2"></i>Enhancement Suite
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($health_score < 90): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="bi bi-lightbulb me-2"></i>Recommendations</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary mb-3">Priority Actions</h6>
                            <ul class="list-unstyled">
                                <?php foreach ($diagnostics as $key => $diagnostic): ?>
                                    <?php if ($diagnostic['status'] !== 'success'): ?>
                                        <li class="mb-2">
                                            <i class="bi bi-arrow-right text-warning me-2"></i>
                                            <span class="small">Fix <?= str_replace('_', ' ', $key) ?>: <?= $diagnostic['details'] ?></span>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-success mb-3">Performance Tips</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="bi bi-arrow-right text-success me-2"></i>
                                    <span class="small">Enable database query caching</span>
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-arrow-right text-success me-2"></i>
                                    <span class="small">Optimize images and assets</span>
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-arrow-right text-success me-2"></i>
                                    <span class="small">Regular database maintenance</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.health-score {
    text-align: center;
}

.diagnostic-item {
    padding: 12px;
    border-radius: 8px;
    background: rgba(0,0,0,0.02);
    transition: background-color 0.2s ease;
}

.diagnostic-item:hover {
    background: rgba(0,0,0,0.05);
}

.system-info {
    font-size: 0.9rem;
}

.card {
    transition: transform 0.2s ease;
}

.card:hover {
    transform: translateY(-1px);
}
</style>

<?php require_once 'hrms_footer_simple.php'; ?>