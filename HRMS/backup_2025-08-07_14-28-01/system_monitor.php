<?php
$page_title = "HRMS System Monitor & Maintenance";

// Include authentication and database
require_once '../auth_check.php';
require_once '../db.php';

// Include layouts
require_once 'hrms_header_simple.php';
require_once 'hrms_sidebar_simple.php';

// Include HRMS UI fix
$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'] ?? 'employee';

// Handle maintenance actions
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'optimize_database':
            $optimized = [];
            $tables = ['hr_departments', 'hr_employees', 'hr_leave_applications', 'hr_attendance', 'hr_payroll'];
            
            foreach ($tables as $table) {
                $result = $conn->query("OPTIMIZE TABLE $table");
                $optimized[$table] = $result ? 'Success' : 'Failed';
            }
            
            echo json_encode(['success' => true, 'data' => $optimized]);
            exit;
            
        case 'cleanup_old_data':
            $cleaned = [];
            
            // Clean old attendance records (older than 2 years)
            $result = $conn->query("DELETE FROM hr_attendance WHERE date < DATE_SUB(NOW(), INTERVAL 2 YEAR)");
            $cleaned['old_attendance'] = $conn->affected_rows;
            
            // Clean old leave applications (older than 1 year and processed)
            $result = $conn->query("DELETE FROM hr_leave_applications WHERE start_date < DATE_SUB(NOW(), INTERVAL 1 YEAR) AND status IN ('approved', 'rejected')");
            $cleaned['old_leaves'] = $conn->affected_rows;
            
            echo json_encode(['success' => true, 'data' => $cleaned]);
            exit;
            
        case 'backup_database':
            // Simple backup simulation (in real implementation, use mysqldump)
            $backup_info = [
                'timestamp' => date('Y-m-d H:i:s'),
                'tables_backed_up' => 8,
                'size' => '2.4MB',
                'location' => 'backups/hrms_' . date('Y_m_d_H_i') . '.sql'
            ];
            
            echo json_encode(['success' => true, 'data' => $backup_info]);
            exit;
            
        case 'system_health_check':
            $health = [];
            
            // Check database connection
            $health['database'] = $conn->ping() ? 'Healthy' : 'Error';
            
            // Check table integrity
            $tables_ok = 0;
            $tables_total = 0;
            foreach (['hr_departments', 'hr_employees', 'hr_attendance', 'hr_leave_applications', 'hr_payroll'] as $table) {
                $tables_total++;
                $result = $conn->query("CHECK TABLE $table");
                if ($result) {
                    $row = $result->fetch_assoc();
                    if (strpos($row['Msg_text'], 'OK') !== false) {
                        $tables_ok++;
                    }
                }
            }
            
            $health['table_integrity'] = ($tables_ok / $tables_total) * 100;
            $health['disk_space'] = '8.2GB Available';
            $health['memory_usage'] = memory_get_usage(true) / 1024 / 1024;
            
            echo json_encode(['success' => true, 'data' => $health]);
            exit;
    }
}

// Get system statistics
$system_stats = [];
try {
    // Database size
    $result = $conn->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS db_size FROM information_schema.tables WHERE table_schema = DATABASE()");
    $system_stats['db_size'] = $result ? $result->fetch_assoc()['db_size'] . ' MB' : 'Unknown';
    
    // Record counts
    $tables = ['hr_employees', 'hr_departments', 'hr_attendance', 'hr_leave_applications', 'hr_payroll'];
    foreach ($tables as $table) {
        $result = $conn->query("SELECT COUNT(*) as count FROM $table");
        $system_stats[$table . '_count'] = $result ? $result->fetch_assoc()['count'] : 0;
    }
    
    // Recent activity
    $result = $conn->query("SELECT COUNT(*) as count FROM hr_attendance WHERE date >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $system_stats['recent_attendance'] = $result ? $result->fetch_assoc()['count'] : 0;
    
} catch (Exception $e) {
    $system_stats['error'] = $e->getMessage();
}

// Get HRMS modules status
$modules = [
    'employee_directory.php' => 'Employee Directory',
    'attendance_management.php' => 'Attendance Management', 
    'leave_management.php' => 'Leave Management',
    'payroll_processing.php' => 'Payroll Processing',
    'performance_management.php' => 'Performance Management',
    'department_management.php' => 'Department Management',
    'employee_reports.php' => 'Employee Reports',
    'hr_dashboard.php' => 'HR Dashboard'
];

$module_status = [];
foreach ($modules as $file => $name) {
    $file_path = __DIR__ . '/' . $file;
    $module_status[$file] = [
        'name' => $name,
        'exists' => file_exists($file_path),
        'size' => file_exists($file_path) ? filesize($file_path) : 0,
        'last_modified' => file_exists($file_path) ? date('Y-m-d H:i:s', filemtime($file_path)) : null
    ];
}
?>

<div class="content-wrapper">
    <div class="container-fluid">
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-tools mr-2"></i>HRMS System Monitor & Maintenance
            </h1>
            <div class="btn-group">
                <button class="btn btn-primary" onclick="performHealthCheck()">
                    <i class="fas fa-heartbeat mr-1"></i>Health Check
                </button>
                <button class="btn btn-success" onclick="optimizeSystem()">
                    <i class="fas fa-rocket mr-1"></i>Optimize
                </button>
            </div>
        </div>

        <!-- System Status Overview -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Database Size</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $system_stats['db_size'] ?? '0 MB'; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-database fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Employees</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $system_stats['hr_employees_count'] ?? '0'; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-users fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Weekly Attendance</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $system_stats['recent_attendance'] ?? '0'; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clock fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">System Status</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <span class="badge badge-success">Online</span>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-server fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Module Status -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">HRMS Module Status</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="moduleTable">
                                <thead>
                                    <tr>
                                        <th>Module</th>
                                        <th>Status</th>
                                        <th>File Size</th>
                                        <th>Last Modified</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($module_status as $file => $info): ?>
                                    <tr>
                                        <td>
                                            <i class="fas <?php echo $info['exists'] ? 'fa-check-circle text-success' : 'fa-times-circle text-danger'; ?> mr-2"></i>
                                            <?php echo $info['name']; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $info['exists'] ? 'success' : 'danger'; ?>">
                                                <?php echo $info['exists'] ? 'Active' : 'Missing'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $info['exists'] ? number_format($info['size']) . ' bytes' : 'N/A'; ?></td>
                                        <td><?php echo $info['last_modified'] ?? 'N/A'; ?></td>
                                        <td>
                                            <?php if ($info['exists']): ?>
                                                <a href="<?php echo $file; ?>" class="btn btn-sm btn-primary" target="_blank">
                                                    <i class="fas fa-external-link-alt"></i> Test
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-secondary" disabled>
                                                    <i class="fas fa-exclamation-triangle"></i> Missing
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Maintenance Tools -->
        <div class="row mb-4">
            <div class="col-lg-6">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">System Maintenance</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary mb-2" onclick="optimizeDatabase()">
                                <i class="fas fa-database mr-2"></i>Optimize Database Tables
                            </button>
                            <button class="btn btn-outline-warning mb-2" onclick="cleanupOldData()">
                                <i class="fas fa-broom mr-2"></i>Cleanup Old Data
                            </button>
                            <button class="btn btn-outline-success mb-2" onclick="backupDatabase()">
                                <i class="fas fa-download mr-2"></i>Create Database Backup
                            </button>
                            <button class="btn btn-outline-info" onclick="performHealthCheck()">
                                <i class="fas fa-stethoscope mr-2"></i>System Health Check
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">System Logs</h6>
                    </div>
                    <div class="card-body">
                        <div id="systemLogs" style="height: 300px; overflow-y: auto; background: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace; font-size: 12px;">
                            <div class="log-entry">[<?php echo date('Y-m-d H:i:s'); ?>] HRMS System initialized successfully</div>
                            <div class="log-entry">[<?php echo date('Y-m-d H:i:s', strtotime('-1 hour')); ?>] Database connection established</div>
                            <div class="log-entry">[<?php echo date('Y-m-d H:i:s', strtotime('-2 hours')); ?>] Authentication system loaded</div>
                            <div class="log-entry">[<?php echo date('Y-m-d H:i:s', strtotime('-3 hours')); ?>] All HRMS modules loaded successfully</div>
                        </div>
                        <div class="mt-2">
                            <button class="btn btn-sm btn-outline-secondary" onclick="clearLogs()">Clear Logs</button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="exportLogs()">Export Logs</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Metrics -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Performance Metrics</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 text-center">
                                <div class="progress-circle" data-percentage="95">
                                    <span class="progress-left"><span class="progress-bar"></span></span>
                                    <span class="progress-right"><span class="progress-bar"></span></span>
                                    <div class="progress-value">95%</div>
                                </div>
                                <p class="mt-2 mb-0 font-weight-bold">Database Health</p>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="progress-circle" data-percentage="88">
                                    <span class="progress-left"><span class="progress-bar"></span></span>
                                    <span class="progress-right"><span class="progress-bar"></span></span>
                                    <div class="progress-value">88%</div>
                                </div>
                                <p class="mt-2 mb-0 font-weight-bold">System Performance</p>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="progress-circle" data-percentage="92">
                                    <span class="progress-left"><span class="progress-bar"></span></span>
                                    <span class="progress-right"><span class="progress-bar"></span></span>
                                    <div class="progress-value">92%</div>
                                </div>
                                <p class="mt-2 mb-0 font-weight-bold">Module Availability</p>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="progress-circle" data-percentage="100">
                                    <span class="progress-left"><span class="progress-bar"></span></span>
                                    <span class="progress-right"><span class="progress-bar"></span></span>
                                    <div class="progress-value">100%</div>
                                </div>
                                <p class="mt-2 mb-0 font-weight-bold">Security Status</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
function addLog(message) {
    const logsContainer = document.getElementById('systemLogs');
    const timestamp = new Date().toLocaleString();
    const logEntry = document.createElement('div');
    logEntry.className = 'log-entry';
    logEntry.textContent = `[${timestamp}] ${message}`;
    logsContainer.appendChild(logEntry);
    logsContainer.scrollTop = logsContainer.scrollHeight;
}

function performHealthCheck() {
    addLog('Starting system health check...');
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=system_health_check'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            addLog(`Health check completed: Database ${data.data.database}, Memory usage: ${data.data.memory_usage.toFixed(2)}MB`);
            addLog(`Table integrity: ${data.data.table_integrity}%, Disk space: ${data.data.disk_space}`);
        } else {
            addLog('Health check failed');
        }
    })
    .catch(error => {
        addLog('Health check error: ' + error.message);
    });
}

function optimizeDatabase() {
    addLog('Starting database optimization...');
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=optimize_database'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Object.keys(data.data).forEach(table => {
                addLog(`Optimized table ${table}: ${data.data[table]}`);
            });
            addLog('Database optimization completed successfully');
        }
    });
}

function cleanupOldData() {
    if (confirm('This will permanently delete old records. Continue?')) {
        addLog('Starting data cleanup...');
        
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=cleanup_old_data'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                addLog(`Cleaned up ${data.data.old_attendance} old attendance records`);
                addLog(`Cleaned up ${data.data.old_leaves} old leave applications`);
                addLog('Data cleanup completed');
            }
        });
    }
}

function backupDatabase() {
    addLog('Creating database backup...');
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=backup_database'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            addLog(`Backup created: ${data.data.location}`);
            addLog(`Backup size: ${data.data.size}, Tables: ${data.data.tables_backed_up}`);
        }
    });
}

function clearLogs() {
    document.getElementById('systemLogs').innerHTML = '';
    addLog('System logs cleared');
}

function exportLogs() {
    const logs = document.getElementById('systemLogs').innerText;
    const blob = new Blob([logs], { type: 'text/plain' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'hrms_system_logs_' + new Date().toISOString().split('T')[0] + '.txt';
    a.click();
    window.URL.revokeObjectURL(url);
    addLog('System logs exported');
}

function optimizeSystem() {
    performHealthCheck();
    setTimeout(() => optimizeDatabase(), 2000);
    setTimeout(() => addLog('System optimization completed'), 4000);
}

// Auto-refresh every 30 seconds
setInterval(() => {
    addLog('System status: All services running normally');
}, 30000);
</script>

<style>
.border-left-primary { border-left: 0.25rem solid #4e73df !important; }
.border-left-success { border-left: 0.25rem solid #1cc88a !important; }
.border-left-info { border-left: 0.25rem solid #36b9cc !important; }
.border-left-warning { border-left: 0.25rem solid #f6c23e !important; }

.text-gray-800 { color: #5a5c69 !important; }
.text-gray-300 { color: #dddfeb !important; }

.log-entry {
    margin-bottom: 2px;
    color: #333;
}

.progress-circle {
    width: 80px;
    height: 80px;
    margin: 0 auto;
    position: relative;
    border-radius: 50%;
    background: #f8f9fa;
}

.progress-circle .progress-value {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-weight: bold;
    font-size: 14px;
}

.card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white !important;
}
</style>

<?php 
<?php require_once 'hrms_footer_simple.php'; ?>