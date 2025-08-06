<?php
$page_title = "HRMS System Validation & Testing";
require_once 'includes/hrms_config.php';

// Authentication check
if (!HRMSHelper::isLoggedIn()) {
    header('Location: ../hrms_portal.php?redirect=HRMS/system_validation.php');
    exit;
}

require_once '../layouts/header.php';
require_once '../layouts/sidebar.php';

$currentUserId = HRMSHelper::getCurrentUserId();
$currentUserRole = HRMSHelper::getCurrentUserRole();

// Validation Results
$validationResults = [];

// 1. File Existence Validation
$requiredFiles = [
    'hr_panel.php' => 'HR Management Dashboard',
    'employee_panel.php' => 'Employee Self-Service Portal', 
    'manager_panel.php' => 'Manager Team Dashboard'
];

foreach ($requiredFiles as $file => $description) {
    $filePath = __DIR__ . '/' . $file;
    $validationResults['files'][$file] = [
        'description' => $description,
        'exists' => file_exists($filePath),
        'size' => file_exists($filePath) ? filesize($filePath) : 0,
        'readable' => file_exists($filePath) ? is_readable($filePath) : false
    ];
}

// 2. Database Table Validation
$requiredTables = [
    'hr_employees' => 'Employee records',
    'hr_departments' => 'Department structure',
    'hr_attendance' => 'Attendance tracking',
    'hr_leave_applications' => 'Leave management'
];

foreach ($requiredTables as $table => $description) {
    try {
        $result = HRMSHelper::safeQuery("SHOW TABLES LIKE '$table'");
        $exists = $result && $result->num_rows > 0;
        
        $recordCount = 0;
        if ($exists) {
            $countResult = HRMSHelper::safeQuery("SELECT COUNT(*) as count FROM $table");
            if ($countResult && $row = $countResult->fetch_assoc()) {
                $recordCount = (int)$row['count'];
            }
        }
        
        $validationResults['database'][$table] = [
            'description' => $description,
            'exists' => $exists,
            'record_count' => $recordCount,
            'status' => $exists ? 'OK' : 'MISSING'
        ];
    } catch (Exception $e) {
        $validationResults['database'][$table] = [
            'description' => $description,
            'exists' => false,
            'record_count' => 0,
            'status' => 'ERROR',
            'error' => $e->getMessage()
        ];
    }
}

// 3. Authentication System Validation
$authValidation = [];
try {
    $authValidation['helper_class'] = class_exists('HRMSHelper');
    $authValidation['login_check'] = method_exists('HRMSHelper', 'isLoggedIn');
    $authValidation['permission_check'] = method_exists('HRMSHelper', 'hasPermission');
    $authValidation['user_methods'] = method_exists('HRMSHelper', 'getCurrentUserId');
    $authValidation['current_user'] = HRMSHelper::getCurrentUserId();
    $authValidation['current_role'] = HRMSHelper::getCurrentUserRole();
} catch (Exception $e) {
    $authValidation['error'] = $e->getMessage();
}

// 4. Panel Functionality Tests
$panelTests = [];

// Test HR Panel
try {
    $hrPanelContent = file_get_contents(__DIR__ . '/hr_panel.php');
    $panelTests['hr_panel'] = [
        'file_size' => strlen($hrPanelContent),
        'has_authentication' => strpos($hrPanelContent, 'HRMSHelper::isLoggedIn()') !== false,
        'has_statistics' => strpos($hrPanelContent, 'hrStats') !== false,
        'has_activities' => strpos($hrPanelContent, 'recentActivities') !== false,
        'has_modals' => strpos($hrPanelContent, 'approvalModal') !== false,
        'has_javascript' => strpos($hrPanelContent, 'approveLeave') !== false
    ];
} catch (Exception $e) {
    $panelTests['hr_panel'] = ['error' => $e->getMessage()];
}

// Test Employee Panel  
try {
    $empPanelContent = file_get_contents(__DIR__ . '/employee_panel.php');
    $panelTests['employee_panel'] = [
        'file_size' => strlen($empPanelContent),
        'has_authentication' => strpos($empPanelContent, 'HRMSHelper::isLoggedIn()') !== false,
        'has_statistics' => strpos($empPanelContent, 'employeeStats') !== false,
        'has_attendance' => strpos($empPanelContent, 'attendanceModal') !== false,
        'has_leave_application' => strpos($empPanelContent, 'leaveModal') !== false,
        'has_profile' => strpos($empPanelContent, 'employee_id') !== false
    ];
} catch (Exception $e) {
    $panelTests['employee_panel'] = ['error' => $e->getMessage()];
}

// Test Manager Panel
try {
    $mgmtPanelContent = file_get_contents(__DIR__ . '/manager_panel.php');
    $panelTests['manager_panel'] = [
        'file_size' => strlen($mgmtPanelContent),
        'has_authentication' => strpos($mgmtPanelContent, 'HRMSHelper::isLoggedIn()') !== false,
        'has_team_stats' => strpos($mgmtPanelContent, 'teamStats') !== false,
        'has_team_members' => strpos($mgmtPanelContent, 'teamMembers') !== false,
        'has_approvals' => strpos($mgmtPanelContent, 'pendingLeaves') !== false,
        'has_performance' => strpos($mgmtPanelContent, 'performanceMetrics') !== false
    ];
} catch (Exception $e) {
    $panelTests['manager_panel'] = ['error' => $e->getMessage()];
}

// 5. Security Validation
$securityTests = [];
foreach ($requiredFiles as $file => $description) {
    $filePath = __DIR__ . '/' . $file;
    if (file_exists($filePath)) {
        $content = file_get_contents($filePath);
        $securityTests[$file] = [
            'has_auth_check' => strpos($content, 'isLoggedIn()') !== false,
            'has_permission_check' => strpos($content, 'hasPermission') !== false,
            'has_safe_query' => strpos($content, 'safeQuery') !== false,
            'has_html_escape' => strpos($content, 'htmlspecialchars') !== false,
            'has_redirect_protection' => strpos($content, 'header(\'Location:') !== false
        ];
    }
}

// 6. Performance Metrics
$performanceMetrics = [
    'total_files' => count($requiredFiles),
    'total_file_size' => array_sum(array_column($validationResults['files'], 'size')),
    'memory_usage' => memory_get_usage(true),
    'peak_memory' => memory_get_peak_usage(true)
];

// Calculate overall system health
$healthScore = 0;
$totalChecks = 0;

// File health
foreach ($validationResults['files'] as $file) {
    $totalChecks++;
    if ($file['exists'] && $file['readable'] && $file['size'] > 1000) {
        $healthScore++;
    }
}

// Database health
foreach ($validationResults['database'] as $table) {
    $totalChecks++;
    if ($table['exists']) {
        $healthScore++;
    }
}

// Panel functionality health
foreach ($panelTests as $panel => $tests) {
    if (!isset($tests['error'])) {
        foreach ($tests as $test => $result) {
            if ($test !== 'file_size') {
                $totalChecks++;
                if ($result) {
                    $healthScore++;
                }
            }
        }
    }
}

$healthPercentage = $totalChecks > 0 ? round(($healthScore / $totalChecks) * 100, 1) : 0;
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h2 mb-1">
                            <i class="fas fa-shield-alt text-primary me-2"></i>
                            HRMS System Validation
                        </h1>
                        <p class="text-muted mb-0">Complete system health check and validation report</p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-<?= $healthPercentage >= 90 ? 'success' : ($healthPercentage >= 70 ? 'warning' : 'danger') ?> fs-6 px-3 py-2">
                            <i class="fas fa-heartbeat me-1"></i><?= $healthPercentage ?>% Healthy
                        </span>
                        <span class="text-muted small"><?= date('F j, Y g:i A') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Overall Health Status -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-2 text-center">
                                <div class="bg-<?= $healthPercentage >= 90 ? 'success' : ($healthPercentage >= 70 ? 'warning' : 'danger') ?> bg-opacity-10 rounded-circle p-4 d-inline-block">
                                    <i class="fas fa-<?= $healthPercentage >= 90 ? 'check-circle' : ($healthPercentage >= 70 ? 'exclamation-triangle' : 'times-circle') ?> text-<?= $healthPercentage >= 90 ? 'success' : ($healthPercentage >= 70 ? 'warning' : 'danger') ?> fs-1"></i>
                                </div>
                            </div>
                            <div class="col-md-10">
                                <h3 class="mb-2">System Health: <?= $healthPercentage ?>%</h3>
                                <div class="progress mb-2" style="height: 10px;">
                                    <div class="progress-bar bg-<?= $healthPercentage >= 90 ? 'success' : ($healthPercentage >= 70 ? 'warning' : 'danger') ?>" 
                                         style="width: <?= $healthPercentage ?>%"></div>
                                </div>
                                <p class="mb-0 text-muted">
                                    <?= $healthScore ?> out of <?= $totalChecks ?> system checks passed successfully
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Validation Results -->
        <div class="row">
            <!-- File Validation -->
            <div class="col-lg-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom-0 py-3">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-file-code text-primary me-2"></i>
                            File System Validation
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($validationResults['files'] as $file => $data): ?>
                            <div class="d-flex justify-content-between align-items-center mb-3 p-3 border rounded">
                                <div>
                                    <h6 class="mb-1"><?= $file ?></h6>
                                    <small class="text-muted"><?= $data['description'] ?></small>
                                    <br>
                                    <small class="text-info"><?= number_format($data['size']) ?> bytes</small>
                                </div>
                                <div class="text-end">
                                    <?php if ($data['exists'] && $data['readable'] && $data['size'] > 1000): ?>
                                        <span class="badge bg-success">✓ OK</span>
                                    <?php elseif ($data['exists']): ?>
                                        <span class="badge bg-warning">⚠ Issues</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">✗ Missing</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Database Validation -->
            <div class="col-lg-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom-0 py-3">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-database text-info me-2"></i>
                            Database Validation
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($validationResults['database'] as $table => $data): ?>
                            <div class="d-flex justify-content-between align-items-center mb-3 p-3 border rounded">
                                <div>
                                    <h6 class="mb-1"><?= $table ?></h6>
                                    <small class="text-muted"><?= $data['description'] ?></small>
                                    <br>
                                    <small class="text-info"><?= number_format($data['record_count']) ?> records</small>
                                </div>
                                <div class="text-end">
                                    <?php if ($data['status'] === 'OK'): ?>
                                        <span class="badge bg-success">✓ OK</span>
                                    <?php elseif ($data['status'] === 'MISSING'): ?>
                                        <span class="badge bg-danger">✗ Missing</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">⚠ Error</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel Functionality Tests -->
        <div class="row">
            <div class="col-12 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom-0 py-3">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-cogs text-warning me-2"></i>
                            Panel Functionality Tests
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($panelTests as $panel => $tests): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="border rounded p-3 h-100">
                                        <h6 class="text-capitalize mb-2"><?= str_replace('_', ' ', $panel) ?></h6>
                                        <?php if (isset($tests['error'])): ?>
                                            <span class="badge bg-danger">Error: <?= $tests['error'] ?></span>
                                        <?php else: ?>
                                            <div class="small">
                                                <div>File Size: <?= number_format($tests['file_size']) ?> bytes</div>
                                                <?php foreach ($tests as $test => $result): ?>
                                                    <?php if ($test !== 'file_size'): ?>
                                                        <div class="d-flex justify-content-between">
                                                            <span><?= ucwords(str_replace('_', ' ', $test)) ?>:</span>
                                                            <span class="badge bg-<?= $result ? 'success' : 'danger' ?>">
                                                                <?= $result ? '✓' : '✗' ?>
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Security & Performance -->
        <div class="row">
            <!-- Security Tests -->
            <div class="col-lg-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom-0 py-3">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-shield-alt text-danger me-2"></i>
                            Security Validation
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($securityTests as $file => $tests): ?>
                            <div class="mb-3">
                                <h6><?= $file ?></h6>
                                <div class="small">
                                    <?php foreach ($tests as $test => $result): ?>
                                        <div class="d-flex justify-content-between">
                                            <span><?= ucwords(str_replace('_', ' ', $test)) ?>:</span>
                                            <span class="badge bg-<?= $result ? 'success' : 'danger' ?>">
                                                <?= $result ? '✓' : '✗' ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Performance Metrics -->
            <div class="col-lg-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom-0 py-3">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-tachometer-alt text-success me-2"></i>
                            Performance Metrics
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <h4 class="text-primary"><?= $performanceMetrics['total_files'] ?></h4>
                                <small class="text-muted">Total Files</small>
                            </div>
                            <div class="col-6 mb-3">
                                <h4 class="text-info"><?= number_format($performanceMetrics['total_file_size'] / 1024, 1) ?>KB</h4>
                                <small class="text-muted">Total Size</small>
                            </div>
                            <div class="col-6 mb-3">
                                <h4 class="text-warning"><?= number_format($performanceMetrics['memory_usage'] / 1024 / 1024, 1) ?>MB</h4>
                                <small class="text-muted">Memory Usage</small>
                            </div>
                            <div class="col-6 mb-3">
                                <h4 class="text-secondary"><?= number_format($performanceMetrics['peak_memory'] / 1024 / 1024, 1) ?>MB</h4>
                                <small class="text-muted">Peak Memory</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Authentication Status -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom-0 py-3">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-user-shield text-info me-2"></i>
                            Authentication System Status
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>System Components</h6>
                                <?php foreach ($authValidation as $key => $value): ?>
                                    <?php if ($key !== 'current_user' && $key !== 'current_role' && $key !== 'error'): ?>
                                        <div class="d-flex justify-content-between">
                                            <span><?= ucwords(str_replace('_', ' ', $key)) ?>:</span>
                                            <span class="badge bg-<?= $value ? 'success' : 'danger' ?>">
                                                <?= $value ? '✓' : '✗' ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <div class="col-md-6">
                                <h6>Current Session</h6>
                                <div class="d-flex justify-content-between">
                                    <span>User ID:</span>
                                    <span class="badge bg-primary"><?= $authValidation['current_user'] ?? 'N/A' ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>User Role:</span>
                                    <span class="badge bg-secondary"><?= $authValidation['current_role'] ?? 'N/A' ?></span>
                                </div>
                                <?php if (isset($authValidation['error'])): ?>
                                    <div class="alert alert-danger mt-2">
                                        <small><?= $authValidation['error'] ?></small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="row mt-4">
            <div class="col-12 text-center">
                <div class="btn-group" role="group">
                    <a href="hr_panel.php" class="btn btn-primary">
                        <i class="fas fa-users-cog me-1"></i>Test HR Panel
                    </a>
                    <a href="employee_panel.php" class="btn btn-success">
                        <i class="fas fa-user-circle me-1"></i>Test Employee Panel
                    </a>
                    <a href="manager_panel.php" class="btn btn-info">
                        <i class="fas fa-users me-1"></i>Test Manager Panel
                    </a>
                    <button class="btn btn-warning" onclick="location.reload()">
                        <i class="fas fa-sync-alt me-1"></i>Refresh Validation
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.main-content {
    margin-left: 250px;
    padding: 2rem;
    min-height: 100vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        padding: 1rem;
    }
}

.card {
    transition: all 0.3s ease;
    border-radius: 12px;
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.95);
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.progress {
    border-radius: 10px;
}

.badge {
    font-size: 0.75em;
}
</style>

<script>
// Auto-refresh validation every 2 minutes
setTimeout(() => {
    location.reload();
}, 120000);

// Add click tracking for testing
document.addEventListener('DOMContentLoaded', function() {
    const testButtons = document.querySelectorAll('a[href$="_panel.php"]');
    testButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const panel = this.getAttribute('href');
            console.log(`Testing panel: ${panel}`);
            
            // Add loading state
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Loading...';
            this.disabled = true;
        });
    });
});
</script>

<?php require_once '../layouts/footer.php'; ?>
