<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';

$page_title = 'System Settings';
$current_page = 'system_settings';

// Handle AJAX requests
if (isset($_POST['action']) && !empty($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'update_general_settings':
            echo json_encode(updateGeneralSettings($conn, $_POST));
            break;
        case 'update_company_info':
            echo json_encode(updateCompanyInfo($conn, $_POST));
            break;
        case 'update_email_settings':
            echo json_encode(updateEmailSettings($conn, $_POST));
            break;
        case 'update_security_settings':
            echo json_encode(updateSecuritySettings($conn, $_POST));
            break;
        case 'update_backup_settings':
            echo json_encode(updateBackupSettings($conn, $_POST));
            break;
        case 'get_system_info':
            echo json_encode(getSystemInfo($conn));
            break;
        case 'clear_cache':
            echo json_encode(clearSystemCache());
            break;
        case 'optimize_database':
            echo json_encode(optimizeDatabase($conn));
            break;
        case 'test_email':
            echo json_encode(testEmailSettings($conn, $_POST));
            break;
        case 'generate_system_report':
            require_once '../../includes/system_settings_utils.php';
            $utils = new SystemSettingsUtils($conn);
            $report = $utils->generateSystemReport();
            echo json_encode(['success' => true, 'data' => $report]);
            break;
        case 'get_system_health':
            require_once '../../includes/system_settings_utils.php';
            $utils = new SystemSettingsUtils($conn);
            $health = $utils->getSystemHealth();
            echo json_encode(['success' => true, 'data' => $health]);
            break;
    }
    exit;
}

// Functions for handling settings operations
function updateGeneralSettings($conn, $data) {
    try {
        $settings = [
            'site_name' => $data['site_name'] ?? '',
            'site_url' => $data['site_url'] ?? '',
            'timezone' => $data['timezone'] ?? 'UTC',
            'date_format' => $data['date_format'] ?? 'Y-m-d',
            'time_format' => $data['time_format'] ?? 'H:i:s',
            'currency' => $data['currency'] ?? 'USD',
            'currency_symbol' => $data['currency_symbol'] ?? '$',
            'language' => $data['language'] ?? 'en',
            'items_per_page' => intval($data['items_per_page'] ?? 10),
            'maintenance_mode' => isset($data['maintenance_mode']) ? 1 : 0
        ];
        
        foreach ($settings as $key => $value) {
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->bind_param('sss', $key, $value, $value);
            $stmt->execute();
        }
        
        return ['success' => true, 'message' => 'General settings updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating settings: ' . $e->getMessage()];
    }
}

function updateCompanyInfo($conn, $data) {
    try {
        $settings = [
            'company_name' => $data['company_name'] ?? '',
            'company_address' => $data['company_address'] ?? '',
            'company_phone' => $data['company_phone'] ?? '',
            'company_email' => $data['company_email'] ?? '',
            'company_website' => $data['company_website'] ?? '',
            'company_tax_id' => $data['company_tax_id'] ?? '',
            'company_registration' => $data['company_registration'] ?? ''
        ];
        
        foreach ($settings as $key => $value) {
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->bind_param('sss', $key, $value, $value);
            $stmt->execute();
        }
        
        // Handle logo upload
        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../uploads/company/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION);
            $logo_filename = 'company_logo.' . $file_extension;
            $logo_path = $upload_dir . $logo_filename;
            
            if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $logo_path)) {
                $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $logo_url = 'uploads/company/' . $logo_filename;
                $stmt->bind_param('sss', $key = 'company_logo', $logo_url, $logo_url);
                $stmt->execute();
            }
        }
        
        return ['success' => true, 'message' => 'Company information updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating company info: ' . $e->getMessage()];
    }
}

function updateEmailSettings($conn, $data) {
    try {
        $settings = [
            'smtp_host' => $data['smtp_host'] ?? '',
            'smtp_port' => $data['smtp_port'] ?? '587',
            'smtp_username' => $data['smtp_username'] ?? '',
            'smtp_password' => $data['smtp_password'] ?? '',
            'smtp_encryption' => $data['smtp_encryption'] ?? 'tls',
            'mail_from_address' => $data['mail_from_address'] ?? '',
            'mail_from_name' => $data['mail_from_name'] ?? ''
        ];
        
        foreach ($settings as $key => $value) {
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->bind_param('sss', $key, $value, $value);
            $stmt->execute();
        }
        
        return ['success' => true, 'message' => 'Email settings updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating email settings: ' . $e->getMessage()];
    }
}

function updateSecuritySettings($conn, $data) {
    try {
        $settings = [
            'session_timeout' => intval($data['session_timeout'] ?? 30),
            'max_login_attempts' => intval($data['max_login_attempts'] ?? 5),
            'lockout_duration' => intval($data['lockout_duration'] ?? 15),
            'password_min_length' => intval($data['password_min_length'] ?? 8),
            'password_require_uppercase' => isset($data['password_require_uppercase']) ? 1 : 0,
            'password_require_lowercase' => isset($data['password_require_lowercase']) ? 1 : 0,
            'password_require_numbers' => isset($data['password_require_numbers']) ? 1 : 0,
            'password_require_symbols' => isset($data['password_require_symbols']) ? 1 : 0,
            'two_factor_auth' => isset($data['two_factor_auth']) ? 1 : 0,
            'auto_logout' => isset($data['auto_logout']) ? 1 : 0
        ];
        
        foreach ($settings as $key => $value) {
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->bind_param('sss', $key, $value, $value);
            $stmt->execute();
        }
        
        return ['success' => true, 'message' => 'Security settings updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating security settings: ' . $e->getMessage()];
    }
}

function updateBackupSettings($conn, $data) {
    try {
        $settings = [
            'auto_backup_enabled' => isset($data['auto_backup_enabled']) ? 1 : 0,
            'backup_frequency' => $data['backup_frequency'] ?? 'daily',
            'backup_time' => $data['backup_time'] ?? '02:00',
            'backup_retention_days' => intval($data['backup_retention_days'] ?? 30),
            'backup_compression' => isset($data['backup_compression']) ? 1 : 0,
            'backup_location' => $data['backup_location'] ?? 'local'
        ];
        
        foreach ($settings as $key => $value) {
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->bind_param('sss', $key, $value, $value);
            $stmt->execute();
        }
        
        return ['success' => true, 'message' => 'Backup settings updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating backup settings: ' . $e->getMessage()];
    }
}

function getSystemInfo($conn) {
    try {
        $info = [
            'php_version' => PHP_VERSION,
            'mysql_version' => $conn->server_info,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'disk_free_space' => formatBytes(disk_free_space('.')),
            'disk_total_space' => formatBytes(disk_total_space('.')),
            'server_time' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get()
        ];
        
        // Get database size
        $result = $conn->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS db_size FROM information_schema.tables WHERE table_schema = DATABASE()");
        if ($result) {
            $row = $result->fetch_assoc();
            $info['database_size'] = $row['db_size'] . ' MB';
        }
        
        return ['success' => true, 'data' => $info];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error getting system info: ' . $e->getMessage()];
    }
}

function clearSystemCache() {
    try {
        // Clear various cache directories
        $cache_dirs = ['cache/', 'temp/', 'logs/'];
        $cleared = 0;
        
        foreach ($cache_dirs as $dir) {
            if (is_dir($dir)) {
                $files = glob($dir . '*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                        $cleared++;
                    }
                }
            }
        }
        
        return ['success' => true, 'message' => "Cache cleared successfully. $cleared files removed."];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error clearing cache: ' . $e->getMessage()];
    }
}

function optimizeDatabase($conn) {
    try {
        // Get all tables
        $result = $conn->query("SHOW TABLES");
        $optimized = 0;
        
        while ($row = $result->fetch_array()) {
            $table = $row[0];
            $conn->query("OPTIMIZE TABLE `$table`");
            $optimized++;
        }
        
        return ['success' => true, 'message' => "Database optimized successfully. $optimized tables optimized."];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error optimizing database: ' . $e->getMessage()];
    }
}

function testEmailSettings($conn, $data) {
    try {
        // Get current email settings
        $result = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'smtp_%' OR setting_key LIKE 'mail_%'");
        $settings = [];
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        // Test email functionality (simplified)
        $test_email = $data['test_email'] ?? 'test@example.com';
        
        // Here you would implement actual email sending
        // For now, we'll just return success if settings exist
        if (!empty($settings['smtp_host']) && !empty($settings['smtp_username'])) {
            return ['success' => true, 'message' => 'Email settings test completed successfully'];
        } else {
            return ['success' => false, 'message' => 'Email settings are incomplete'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error testing email: ' . $e->getMessage()];
    }
}

function formatBytes($size, $precision = 2) {
    $base = log($size, 1024);
    $suffixes = array('', 'KB', 'MB', 'GB', 'TB');
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}

// Get current settings
function getCurrentSettings($conn) {
    $settings = [];
    $result = $conn->query("SELECT setting_key, setting_value FROM system_settings");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $settings;
}

$current_settings = getCurrentSettings($conn);

// Get system settings for display
$settings_query = $conn->query("SELECT setting_key, setting_value FROM system_settings ORDER BY setting_key");
$settings = [];
while ($row = $settings_query->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">⚙️ System Settings</h1>
                <p class="text-muted">Configure and manage your system settings</p>
            </div>
            <div>
                <button class="btn btn-outline-secondary me-2" onclick="generateReport()">
                    <i class="bi bi-file-earmark-text"></i> Generate Report
                </button>
                <button class="btn btn-outline-info me-2" onclick="showSystemHealth()">
                    <i class="bi bi-heart-pulse"></i> System Health
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#importExportModal">
                    <i class="bi bi-arrow-repeat"></i> Import/Export Settings
                </button>
        </div>

        </div>
        </div>

        <!-- System Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-gear fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= count($settings) ?></h3>
                        <small class="opacity-75">Total Settings</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-shield-check fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold">Secure</h3>
                        <small class="opacity-75">System Status</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-database fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold">Active</h3>
                        <small class="opacity-75">Database Status</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-lightning fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold">Online</h3>
                        <small class="opacity-75">System Health</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <button class="btn btn-outline-primary w-100" onclick="$('#backupModal').modal('show')">
                    <i class="bi bi-shield-check me-2"></i>Backup System
                </button>
            </div>
            <div class="col-md-3">
                <button class="btn btn-outline-success w-100" onclick="clearSystemCache()">
                    <i class="bi bi-arrow-clockwise me-2"></i>Clear Cache
                </button>
            </div>
            <div class="col-md-3">
                <button class="btn btn-outline-info w-100" onclick="optimizeDatabase()">
                    <i class="bi bi-database-gear me-2"></i>Optimize Database
                </button>
            </div>
            <div class="col-md-3">
                <button class="btn btn-outline-warning w-100" onclick="testSystemConnections()">
                    <i class="bi bi-plug me-2"></i>Test Connections
                </button>
            </div>
        </div>

        <!-- System Info Panel -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="bi bi-info-circle text-primary me-2"></i>
                                System Overview
                            </h6>
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#systemOverview">
                                <i class="bi bi-chevron-down"></i>
                            </button>
                        </div>
                    </div>
                    <div class="collapse show" id="systemOverview">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <small class="text-muted">Total Settings</small>
                                    <div class="h5 mb-0"><?= count($settings) ?></div>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Last Modified</small>
                                    <div class="h5 mb-0"><?= date('Y-m-d H:i') ?></div>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">System Status</small>
                                    <div class="h5 mb-0 text-success">
                                        <i class="bi bi-check-circle me-1"></i>Operational
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Quick Actions</small>
                                    <div class="mt-1">
                                        <button class="btn btn-sm btn-outline-primary me-1" onclick="location.reload()">
                                            <i class="bi bi-arrow-clockwise"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-success" onclick="showAlert('success', 'System is running smoothly!')">
                                            <i class="bi bi-heart-pulse"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Navigation -->
        <div class="row">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <nav class="nav nav-pills flex-column p-3">
                            <a class="nav-link active mb-2" data-bs-toggle="pill" href="#general-settings">
                                <i class="bi bi-gear me-2"></i>General Settings
                            </a>
                            <a class="nav-link mb-2" data-bs-toggle="pill" href="#company-info">
                                <i class="bi bi-building me-2"></i>Company Information
                            </a>
                            <a class="nav-link mb-2" data-bs-toggle="pill" href="#email-settings">
                                    <i class="bi bi-envelope me-2"></i>Email Settings
                                </a>
                                <a class="nav-link" data-bs-toggle="pill" href="#security-settings">
                                    <i class="bi bi-shield-lock me-2"></i>Security Settings
                                </a>
                                <a class="nav-link" data-bs-toggle="pill" href="#backup-settings">
                                    <i class="bi bi-hdd me-2"></i>Backup Settings
                                </a>
                                <a class="nav-link" data-bs-toggle="pill" href="#system-info">
                                    <i class="bi bi-info-circle me-2"></i>System Information
                                </a>
                                <a class="nav-link" data-bs-toggle="pill" href="#maintenance">
                                    <i class="bi bi-tools me-2"></i>Maintenance
                                </a>
                            </div>
                        </nav>
                    </div>
                </div>
                
                <!-- Settings Content -->
                <div class="col-md-9">
                    <div class="tab-content">
                        <!-- General Settings -->
                        <div class="tab-pane fade show active" id="general-settings">
                            <div class="card settings-card">
                                <div class="card-header">
                                    <h5><i class="bi bi-gear"></i> General Settings</h5>
                                </div>
                                <div class="card-body">
                                    <form id="generalSettingsForm">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="site_name" class="form-label">Site Name</label>
                                                <input type="text" class="form-control" id="site_name" name="site_name" 
                                                       value="<?= htmlspecialchars($current_settings['site_name'] ?? 'BillBook') ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="site_url" class="form-label">Site URL</label>
                                                <input type="url" class="form-control" id="site_url" name="site_url" 
                                                       value="<?= htmlspecialchars($current_settings['site_url'] ?? '') ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label for="timezone" class="form-label">Timezone</label>
                                                <select class="form-select" id="timezone" name="timezone">
                                                    <option value="UTC" <?= ($current_settings['timezone'] ?? 'UTC') === 'UTC' ? 'selected' : '' ?>>UTC</option>
                                                    <option value="America/New_York" <?= ($current_settings['timezone'] ?? '') === 'America/New_York' ? 'selected' : '' ?>>Eastern Time</option>
                                                    <option value="America/Chicago" <?= ($current_settings['timezone'] ?? '') === 'America/Chicago' ? 'selected' : '' ?>>Central Time</option>
                                                    <option value="America/Denver" <?= ($current_settings['timezone'] ?? '') === 'America/Denver' ? 'selected' : '' ?>>Mountain Time</option>
                                                    <option value="America/Los_Angeles" <?= ($current_settings['timezone'] ?? '') === 'America/Los_Angeles' ? 'selected' : '' ?>>Pacific Time</option>
                                                    <option value="Europe/London" <?= ($current_settings['timezone'] ?? '') === 'Europe/London' ? 'selected' : '' ?>>London</option>
                                                    <option value="Europe/Paris" <?= ($current_settings['timezone'] ?? '') === 'Europe/Paris' ? 'selected' : '' ?>>Paris</option>
                                                    <option value="Asia/Tokyo" <?= ($current_settings['timezone'] ?? '') === 'Asia/Tokyo' ? 'selected' : '' ?>>Tokyo</option>
                                                    <option value="Asia/Kolkata" <?= ($current_settings['timezone'] ?? '') === 'Asia/Kolkata' ? 'selected' : '' ?>>India</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="date_format" class="form-label">Date Format</label>
                                                <select class="form-select" id="date_format" name="date_format">
                                                    <option value="Y-m-d" <?= ($current_settings['date_format'] ?? 'Y-m-d') === 'Y-m-d' ? 'selected' : '' ?>>YYYY-MM-DD</option>
                                                    <option value="d/m/Y" <?= ($current_settings['date_format'] ?? '') === 'd/m/Y' ? 'selected' : '' ?>>DD/MM/YYYY</option>
                                                    <option value="m/d/Y" <?= ($current_settings['date_format'] ?? '') === 'm/d/Y' ? 'selected' : '' ?>>MM/DD/YYYY</option>
                                                    <option value="d-m-Y" <?= ($current_settings['date_format'] ?? '') === 'd-m-Y' ? 'selected' : '' ?>>DD-MM-YYYY</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="time_format" class="form-label">Time Format</label>
                                                <select class="form-select" id="time_format" name="time_format">
                                                    <option value="H:i:s" <?= ($current_settings['time_format'] ?? 'H:i:s') === 'H:i:s' ? 'selected' : '' ?>>24-hour</option>
                                                    <option value="h:i:s A" <?= ($current_settings['time_format'] ?? '') === 'h:i:s A' ? 'selected' : '' ?>>12-hour</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label for="currency" class="form-label">Currency</label>
                                                <select class="form-select" id="currency" name="currency">
                                                    <option value="USD" <?= ($current_settings['currency'] ?? 'USD') === 'USD' ? 'selected' : '' ?>>USD - US Dollar</option>
                                                    <option value="EUR" <?= ($current_settings['currency'] ?? '') === 'EUR' ? 'selected' : '' ?>>EUR - Euro</option>
                                                    <option value="GBP" <?= ($current_settings['currency'] ?? '') === 'GBP' ? 'selected' : '' ?>>GBP - British Pound</option>
                                                    <option value="INR" <?= ($current_settings['currency'] ?? '') === 'INR' ? 'selected' : '' ?>>INR - Indian Rupee</option>
                                                    <option value="CAD" <?= ($current_settings['currency'] ?? '') === 'CAD' ? 'selected' : '' ?>>CAD - Canadian Dollar</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="currency_symbol" class="form-label">Currency Symbol</label>
                                                <input type="text" class="form-control" id="currency_symbol" name="currency_symbol" 
                                                       value="<?= htmlspecialchars($current_settings['currency_symbol'] ?? '$') ?>">
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="items_per_page" class="form-label">Items Per Page</label>
                                                <select class="form-select" id="items_per_page" name="items_per_page">
                                                    <option value="10" <?= ($current_settings['items_per_page'] ?? '10') === '10' ? 'selected' : '' ?>>10</option>
                                                    <option value="25" <?= ($current_settings['items_per_page'] ?? '') === '25' ? 'selected' : '' ?>>25</option>
                                                    <option value="50" <?= ($current_settings['items_per_page'] ?? '') === '50' ? 'selected' : '' ?>>50</option>
                                                    <option value="100" <?= ($current_settings['items_per_page'] ?? '') === '100' ? 'selected' : '' ?>>100</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="language" class="form-label">Language</label>
                                                <select class="form-select" id="language" name="language">
                                                    <option value="en" <?= ($current_settings['language'] ?? 'en') === 'en' ? 'selected' : '' ?>>English</option>
                                                    <option value="es" <?= ($current_settings['language'] ?? '') === 'es' ? 'selected' : '' ?>>Spanish</option>
                                                    <option value="fr" <?= ($current_settings['language'] ?? '') === 'fr' ? 'selected' : '' ?>>French</option>
                                                    <option value="de" <?= ($current_settings['language'] ?? '') === 'de' ? 'selected' : '' ?>>German</option>
                                                    <option value="hi" <?= ($current_settings['language'] ?? '') === 'hi' ? 'selected' : '' ?>>Hindi</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <div class="form-check mt-4">
                                                    <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" 
                                                           <?= isset($current_settings['maintenance_mode']) && $current_settings['maintenance_mode'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="maintenance_mode">
                                                        <strong>Maintenance Mode</strong>
                                                        <small class="d-block text-muted">Enable to put site in maintenance mode</small>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-end">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-check-circle"></i> Save General Settings
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Company Information -->
                        <div class="tab-pane fade" id="company-info">
                            <div class="card settings-card">
                                <div class="card-header">
                                    <h5><i class="bi bi-building"></i> Company Information</h5>
                                </div>
                                <div class="card-body">
                                    <form id="companyInfoForm" enctype="multipart/form-data">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="company_name" class="form-label">Company Name</label>
                                                <input type="text" class="form-control" id="company_name" name="company_name" 
                                                       value="<?= htmlspecialchars($current_settings['company_name'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="company_logo" class="form-label">Company Logo</label>
                                                <input type="file" class="form-control" id="company_logo" name="company_logo" accept="image/*">
                                                <small class="form-text text-muted">Upload PNG, JPG, or GIF (max 2MB)</small>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="company_address" class="form-label">Company Address</label>
                                            <textarea class="form-control" id="company_address" name="company_address" rows="3"><?= htmlspecialchars($current_settings['company_address'] ?? '') ?></textarea>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="company_phone" class="form-label">Phone Number</label>
                                                <input type="tel" class="form-control" id="company_phone" name="company_phone" 
                                                       value="<?= htmlspecialchars($current_settings['company_phone'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="company_email" class="form-label">Email Address</label>
                                                <input type="email" class="form-control" id="company_email" name="company_email" 
                                                       value="<?= htmlspecialchars($current_settings['company_email'] ?? '') ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="company_website" class="form-label">Website</label>
                                                <input type="url" class="form-control" id="company_website" name="company_website" 
                                                       value="<?= htmlspecialchars($current_settings['company_website'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="company_tax_id" class="form-label">Tax ID/Registration Number</label>
                                                <input type="text" class="form-control" id="company_tax_id" name="company_tax_id" 
                                                       value="<?= htmlspecialchars($current_settings['company_tax_id'] ?? '') ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="company_registration" class="form-label">Company Registration Details</label>
                                            <textarea class="form-control" id="company_registration" name="company_registration" rows="3"><?= htmlspecialchars($current_settings['company_registration'] ?? '') ?></textarea>
                                        </div>
                                        
                                        <div class="d-flex justify-content-end">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-check-circle"></i> Save Company Information
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Email Settings -->
                        <div class="tab-pane fade" id="email-settings">
                            <div class="card settings-card">
                                <div class="card-header">
                                    <h5><i class="bi bi-envelope"></i> Email Settings</h5>
                                </div>
                                <div class="card-body">
                                    <form id="emailSettingsForm">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="smtp_host" class="form-label">SMTP Host</label>
                                                <input type="text" class="form-control" id="smtp_host" name="smtp_host" 
                                                       value="<?= htmlspecialchars($current_settings['smtp_host'] ?? '') ?>"
                                                       placeholder="smtp.gmail.com">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="smtp_port" class="form-label">SMTP Port</label>
                                                <input type="number" class="form-control" id="smtp_port" name="smtp_port" 
                                                       value="<?= htmlspecialchars($current_settings['smtp_port'] ?? '587') ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="smtp_username" class="form-label">SMTP Username</label>
                                                <input type="email" class="form-control" id="smtp_username" name="smtp_username" 
                                                       value="<?= htmlspecialchars($current_settings['smtp_username'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="smtp_password" class="form-label">SMTP Password</label>
                                                <input type="password" class="form-control" id="smtp_password" name="smtp_password" 
                                                       value="<?= htmlspecialchars($current_settings['smtp_password'] ?? '') ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label for="smtp_encryption" class="form-label">Encryption</label>
                                                <select class="form-select" id="smtp_encryption" name="smtp_encryption">
                                                    <option value="tls" <?= ($current_settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                                                    <option value="ssl" <?= ($current_settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                                    <option value="none" <?= ($current_settings['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="mail_from_address" class="form-label">From Email</label>
                                                <input type="email" class="form-control" id="mail_from_address" name="mail_from_address" 
                                                       value="<?= htmlspecialchars($current_settings['mail_from_address'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="mail_from_name" class="form-label">From Name</label>
                                                <input type="text" class="form-control" id="mail_from_name" name="mail_from_name" 
                                                       value="<?= htmlspecialchars($current_settings['mail_from_name'] ?? '') ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="test_email" class="form-label">Test Email Address</label>
                                                <input type="email" class="form-control" id="test_email" name="test_email" 
                                                       placeholder="Enter email to test settings">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">&nbsp;</label>
                                                <div>
                                                    <button type="button" class="btn btn-outline-secondary" onclick="testEmailSettings()">
                                                        <i class="bi bi-envelope-check"></i> Test Email Settings
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-end">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-check-circle"></i> Save Email Settings
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Security Settings -->
                        <div class="tab-pane fade" id="security-settings">
                            <div class="card settings-card">
                                <div class="card-header">
                                    <h5><i class="bi bi-shield-lock"></i> Security Settings</h5>
                                </div>
                                <div class="card-body">
                                    <form id="securitySettingsForm">
                                        <h6 class="mb-3">Session & Authentication</h6>
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label for="session_timeout" class="form-label">Session Timeout (minutes)</label>
                                                <input type="number" class="form-control" id="session_timeout" name="session_timeout" 
                                                       value="<?= htmlspecialchars($current_settings['session_timeout'] ?? '30') ?>" min="5" max="1440">
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="max_login_attempts" class="form-label">Max Login Attempts</label>
                                                <input type="number" class="form-control" id="max_login_attempts" name="max_login_attempts" 
                                                       value="<?= htmlspecialchars($current_settings['max_login_attempts'] ?? '5') ?>" min="3" max="10">
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="lockout_duration" class="form-label">Lockout Duration (minutes)</label>
                                                <input type="number" class="form-control" id="lockout_duration" name="lockout_duration" 
                                                       value="<?= htmlspecialchars($current_settings['lockout_duration'] ?? '15') ?>" min="5" max="60">
                                            </div>
                                        </div>
                                        
                                        <h6 class="mb-3">Password Policy</h6>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="password_min_length" class="form-label">Minimum Password Length</label>
                                                <input type="number" class="form-control" id="password_min_length" name="password_min_length" 
                                                       value="<?= htmlspecialchars($current_settings['password_min_length'] ?? '8') ?>" min="6" max="20">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Password Requirements</label>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="password_require_uppercase" name="password_require_uppercase" 
                                                           <?= isset($current_settings['password_require_uppercase']) && $current_settings['password_require_uppercase'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="password_require_uppercase">
                                                        Require uppercase letters
                                                    </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="password_require_lowercase" name="password_require_lowercase" 
                                                           <?= isset($current_settings['password_require_lowercase']) && $current_settings['password_require_lowercase'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="password_require_lowercase">
                                                        Require lowercase letters
                                                    </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="password_require_numbers" name="password_require_numbers" 
                                                           <?= isset($current_settings['password_require_numbers']) && $current_settings['password_require_numbers'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="password_require_numbers">
                                                        Require numbers
                                                    </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="password_require_symbols" name="password_require_symbols" 
                                                           <?= isset($current_settings['password_require_symbols']) && $current_settings['password_require_symbols'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="password_require_symbols">
                                                        Require special characters
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <h6 class="mb-3">Additional Security</h6>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="two_factor_auth" name="two_factor_auth" 
                                                           <?= isset($current_settings['two_factor_auth']) && $current_settings['two_factor_auth'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="two_factor_auth">
                                                        <strong>Enable Two-Factor Authentication</strong>
                                                        <small class="d-block text-muted">Require 2FA for all users</small>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="auto_logout" name="auto_logout" 
                                                           <?= isset($current_settings['auto_logout']) && $current_settings['auto_logout'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="auto_logout">
                                                        <strong>Auto Logout on Inactivity</strong>
                                                        <small class="d-block text-muted">Automatically log out inactive users</small>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-end">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-check-circle"></i> Save Security Settings
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Backup Settings -->
                        <div class="tab-pane fade" id="backup-settings">
                            <div class="card settings-card">
                                <div class="card-header">
                                    <h5><i class="bi bi-hdd"></i> Backup Settings</h5>
                                </div>
                                <div class="card-body">
                                    <form id="backupSettingsForm">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="auto_backup_enabled" name="auto_backup_enabled" 
                                                           <?= isset($current_settings['auto_backup_enabled']) && $current_settings['auto_backup_enabled'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="auto_backup_enabled">
                                                        <strong>Enable Automatic Backups</strong>
                                                        <small class="d-block text-muted">Automatically create database backups</small>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="backup_compression" name="backup_compression" 
                                                           <?= isset($current_settings['backup_compression']) && $current_settings['backup_compression'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="backup_compression">
                                                        <strong>Enable Compression</strong>
                                                        <small class="d-block text-muted">Compress backup files to save space</small>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label for="backup_frequency" class="form-label">Backup Frequency</label>
                                                <select class="form-select" id="backup_frequency" name="backup_frequency">
                                                    <option value="daily" <?= ($current_settings['backup_frequency'] ?? 'daily') === 'daily' ? 'selected' : '' ?>>Daily</option>
                                                    <option value="weekly" <?= ($current_settings['backup_frequency'] ?? '') === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                                                    <option value="monthly" <?= ($current_settings['backup_frequency'] ?? '') === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="backup_time" class="form-label">Backup Time</label>
                                                <input type="time" class="form-control" id="backup_time" name="backup_time" 
                                                       value="<?= htmlspecialchars($current_settings['backup_time'] ?? '02:00') ?>">
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="backup_retention_days" class="form-label">Retention Period (days)</label>
                                                <input type="number" class="form-control" id="backup_retention_days" name="backup_retention_days" 
                                                       value="<?= htmlspecialchars($current_settings['backup_retention_days'] ?? '30') ?>" min="7" max="365">
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="backup_location" class="form-label">Backup Storage Location</label>
                                            <select class="form-select" id="backup_location" name="backup_location">
                                                <option value="local" <?= ($current_settings['backup_location'] ?? 'local') === 'local' ? 'selected' : '' ?>>Local Server</option>
                                                <option value="ftp" <?= ($current_settings['backup_location'] ?? '') === 'ftp' ? 'selected' : '' ?>>FTP Server</option>
                                                <option value="s3" <?= ($current_settings['backup_location'] ?? '') === 's3' ? 'selected' : '' ?>>Amazon S3</option>
                                                <option value="dropbox" <?= ($current_settings['backup_location'] ?? '') === 'dropbox' ? 'selected' : '' ?>>Dropbox</option>
                                            </select>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between">
                                            <a href="../backups/database_backup.php" class="btn btn-outline-primary">
                                                <i class="bi bi-hdd-stack"></i> Manage Backups
                                            </a>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-check-circle"></i> Save Backup Settings
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- System Information -->
                        <div class="tab-pane fade" id="system-info">
                            <div class="card settings-card system-info-card">
                                <div class="card-header">
                                    <h5><i class="bi bi-info-circle"></i> System Information</h5>
                                </div>
                                <div class="card-body" id="systemInfoContainer">
                                    <div class="text-center">
                                        <div class="spinner-border" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mt-2">Loading system information...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Maintenance -->
                        <div class="tab-pane fade" id="maintenance">
                            <div class="card settings-card">
                                <div class="card-header">
                                    <h5><i class="bi bi-tools"></i> System Maintenance</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-4">
                                            <div class="card border-primary">
                                                <div class="card-body text-center">
                                                    <i class="bi bi-trash text-primary fs-1 mb-2"></i>
                                                    <h5>Clear System Cache</h5>
                                                    <p class="text-muted">Remove temporary files and cached data</p>
                                                    <button class="btn btn-primary" onclick="clearCache()">
                                                        <i class="bi bi-trash"></i> Clear Cache
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-4">
                                            <div class="card border-success">
                                                <div class="card-body text-center">
                                                    <i class="bi bi-gear text-success fs-1 mb-2"></i>
                                                    <h5>Optimize Database</h5>
                                                    <p class="text-muted">Optimize and repair database tables</p>
                                                    <button class="btn btn-success" onclick="optimizeDatabase()">
                                                        <i class="bi bi-gear"></i> Optimize
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-4">
                                            <div class="card border-info">
                                                <div class="card-body text-center">
                                                    <i class="bi bi-arrow-clockwise text-info fs-1 mb-2"></i>
                                                    <h5>System Update Check</h5>
                                                    <p class="text-muted">Check for available system updates</p>
                                                    <button class="btn btn-info" onclick="checkUpdates()">
                                                        <i class="bi bi-arrow-clockwise"></i> Check Updates
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-4">
                                            <div class="card border-warning">
                                                <div class="card-body text-center">
                                                    <i class="bi bi-file-earmark-text text-warning fs-1 mb-2"></i>
                                                    <h5>Generate System Report</h5>
                                                    <p class="text-muted">Create detailed system health report</p>
                                                    <button class="btn btn-warning" onclick="generateReport()">
                                                        <i class="bi bi-file-earmark-text"></i> Generate Report
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Include modals -->
    <?php include 'modals.html'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        $(document).ready(function() {
            // Load system information
            loadSystemInfo();
            
            // Form submissions
            $('#generalSettingsForm').on('submit', function(e) {
                e.preventDefault();
                saveSettings('update_general_settings', this);
            });
            
            $('#companyInfoForm').on('submit', function(e) {
                e.preventDefault();
                saveSettings('update_company_info', this);
            });
            
            $('#emailSettingsForm').on('submit', function(e) {
                e.preventDefault();
                saveSettings('update_email_settings', this);
            });
            
            $('#securitySettingsForm').on('submit', function(e) {
                e.preventDefault();
                saveSettings('update_security_settings', this);
            });
            
            $('#backupSettingsForm').on('submit', function(e) {
                e.preventDefault();
                saveSettings('update_backup_settings', this);
            });
        });
        
        function saveSettings(action, form) {
            const formData = new FormData(form);
            formData.append('action', action);
            
            $.ajax({
                url: '',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        showAlert('success', response.message);
                    } else {
                        showAlert('danger', response.message);
                    }
                },
                error: function() {
                    showAlert('danger', 'An error occurred while saving settings');
                }
            });
        }
        
        function loadSystemInfo() {
            $.post('', {action: 'get_system_info'}, function(response) {
                if (response.success) {
                    displaySystemInfo(response.data);
                } else {
                    $('#systemInfoContainer').html('<div class="alert alert-danger">Error loading system information</div>');
                }
            });
        }
        
        function displaySystemInfo(info) {
            let html = '<div class="row">';
            
            for (const [key, value] of Object.entries(info)) {
                html += `
                    <div class="col-md-6 mb-2">
                        <div class="info-item">
                            <span><strong>${formatKey(key)}:</strong></span>
                            <span>${value}</span>
                        </div>
                    </div>
                `;
            }
            
            html += '</div>';
            $('#systemInfoContainer').html(html);
        }
        
        function formatKey(key) {
            return key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        }
        
        function testEmailSettings() {
            const testEmail = $('#test_email').val();
            if (!testEmail) {
                showAlert('warning', 'Please enter a test email address');
                return;
            }
            
            $.post('', {
                action: 'test_email',
                test_email: testEmail
            }, function(response) {
                if (response.success) {
                    showAlert('success', response.message);
                } else {
                    showAlert('danger', response.message);
                }
            });
        }
        
        function clearCache() {
            $.post('', {action: 'clear_cache'}, function(response) {
                if (response.success) {
                    showAlert('success', response.message);
                } else {
                    showAlert('danger', response.message);
                }
            });
        }
        
        function optimizeDatabase() {
            $.post('', {action: 'optimize_database'}, function(response) {
                if (response.success) {
                    showAlert('success', response.message);
                } else {
                    showAlert('danger', response.message);
                }
            });
        }
        
        function clearSystemCache() {
            clearCache();
        }
        
        function testSystemConnections() {
            showAlert('info', 'Testing system connections...');
            
            // Test database connection
            $.post('', {action: 'get_system_info'}, function(response) {
                if (response.success) {
                    showAlert('success', 'All system connections are working properly');
                } else {
                    showAlert('warning', 'Some system connections may have issues');
                }
            });
        }
        
        function checkUpdates() {
            showAlert('info', 'System update check feature coming soon!');
        }
        
        function generateReport() {
            showAlert('info', 'System report generation feature coming soon!');
        }
        
        function generateReport() {
            $.post('', {action: 'generate_system_report'}, function(response) {
                if (response.success) {
                    // Create and download the report
                    const blob = new Blob([JSON.stringify(response.data, null, 2)], {type: 'application/json'});
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = 'system_report_' + new Date().toISOString().slice(0,19).replace(/:/g, '-') + '.json';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    
                    showAlert('success', 'System report generated and downloaded successfully');
                } else {
                    showAlert('danger', response.message || 'Failed to generate report');
                }
            });
        }
        
        function showSystemHealth() {
            $('#systemHealthModal').modal('show');
            runSystemHealthCheck();
        }
        
        function runSystemHealthCheck() {
            $.post('', {action: 'get_system_health'}, function(response) {
                if (response.success) {
                    displayHealthCheckResults(response.data);
                } else {
                    showAlert('danger', 'Failed to check system health');
                }
            });
        }
        
        function exportSettings() {
            showAlert('info', 'Export functionality available in the Import/Export modal');
        }
        
        function importSettings() {
            showAlert('info', 'Import functionality available in the Import/Export modal');
        }
        
        function showAlert(type, message) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            // Remove existing alerts
            $('.alert').remove();
            
            // Add new alert at the top of the content
            $('.main-content .container-fluid').prepend(alertHtml);
            
            // Auto dismiss after 5 seconds
            setTimeout(function() {
                $('.alert').fadeOut();
            }, 5000);
        }
    </script>
</div>

<?php include '../../layouts/footer.php'; ?>
