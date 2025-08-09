<?php
// System Settings Utilities
// Additional utility functions for system settings management

class SystemSettingsUtils {
    
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Get system health status
     */
    public function getSystemHealth() {
        $health = [
            'overall_status' => 'healthy',
            'checks' => [],
            'warnings' => [],
            'errors' => []
        ];
        
        try {
            // Database connectivity check
            if ($this->conn->ping()) {
                $health['checks'][] = ['name' => 'Database Connection', 'status' => 'OK'];
            } else {
                $health['errors'][] = ['name' => 'Database Connection', 'status' => 'FAILED'];
                $health['overall_status'] = 'critical';
            }
            
            // Disk space check
            $free_space = disk_free_space('.');
            $total_space = disk_total_space('.');
            $usage_percent = (($total_space - $free_space) / $total_space) * 100;
            
            if ($usage_percent > 90) {
                $health['errors'][] = ['name' => 'Disk Space', 'status' => 'Critical - ' . round($usage_percent, 1) . '% used'];
                $health['overall_status'] = 'critical';
            } elseif ($usage_percent > 80) {
                $health['warnings'][] = ['name' => 'Disk Space', 'status' => 'Warning - ' . round($usage_percent, 1) . '% used'];
                if ($health['overall_status'] === 'healthy') {
                    $health['overall_status'] = 'warning';
                }
            } else {
                $health['checks'][] = ['name' => 'Disk Space', 'status' => 'OK - ' . round($usage_percent, 1) . '% used'];
            }
            
            // Memory usage check
            $memory_limit = ini_get('memory_limit');
            $memory_usage = memory_get_usage(true);
            $memory_limit_bytes = $this->convertToBytes($memory_limit);
            $memory_percent = ($memory_usage / $memory_limit_bytes) * 100;
            
            if ($memory_percent > 80) {
                $health['warnings'][] = ['name' => 'Memory Usage', 'status' => 'High - ' . round($memory_percent, 1) . '%'];
                if ($health['overall_status'] === 'healthy') {
                    $health['overall_status'] = 'warning';
                }
            } else {
                $health['checks'][] = ['name' => 'Memory Usage', 'status' => 'OK - ' . round($memory_percent, 1) . '%'];
            }
            
            // Required directories check
            $required_dirs = ['uploads/', 'backups/', 'cache/', 'logs/'];
            foreach ($required_dirs as $dir) {
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                
                if (is_writable($dir)) {
                    $health['checks'][] = ['name' => "Directory: $dir", 'status' => 'OK - Writable'];
                } else {
                    $health['warnings'][] = ['name' => "Directory: $dir", 'status' => 'Not writable'];
                    if ($health['overall_status'] === 'healthy') {
                        $health['overall_status'] = 'warning';
                    }
                }
            }
            
            // PHP extensions check
            $required_extensions = ['mysqli', 'json', 'curl', 'gd', 'zip'];
            foreach ($required_extensions as $ext) {
                if (extension_loaded($ext)) {
                    $health['checks'][] = ['name' => "PHP Extension: $ext", 'status' => 'OK'];
                } else {
                    $health['warnings'][] = ['name' => "PHP Extension: $ext", 'status' => 'Missing'];
                    if ($health['overall_status'] === 'healthy') {
                        $health['overall_status'] = 'warning';
                    }
                }
            }
            
        } catch (Exception $e) {
            $health['errors'][] = ['name' => 'System Check', 'status' => 'Error: ' . $e->getMessage()];
            $health['overall_status'] = 'critical';
        }
        
        return $health;
    }
    
    /**
     * Optimize system performance
     */
    public function optimizeSystem() {
        $results = [];
        
        try {
            // Clear PHP opcache if available
            if (function_exists('opcache_reset')) {
                opcache_reset();
                $results[] = 'PHP OPCache cleared';
            }
            
            // Clean temporary files
            $temp_dirs = ['cache/', 'temp/', 'logs/'];
            $cleaned_files = 0;
            
            foreach ($temp_dirs as $dir) {
                if (is_dir($dir)) {
                    $files = glob($dir . '*');
                    foreach ($files as $file) {
                        if (is_file($file) && (time() - filemtime($file)) > 86400) { // Older than 1 day
                            unlink($file);
                            $cleaned_files++;
                        }
                    }
                }
            }
            
            if ($cleaned_files > 0) {
                $results[] = "Cleaned $cleaned_files temporary files";
            }
            
            // Optimize database tables
            $result = $this->conn->query("SHOW TABLES");
            $optimized_tables = 0;
            
            while ($row = $result->fetch_array()) {
                $table = $row[0];
                $this->conn->query("OPTIMIZE TABLE `$table`");
                $optimized_tables++;
            }
            
            $results[] = "Optimized $optimized_tables database tables";
            
            // Update table statistics
            $this->conn->query("ANALYZE TABLE " . implode(', ', $this->getAllTableNames()));
            $results[] = "Updated database statistics";
            
        } catch (Exception $e) {
            $results[] = "Error during optimization: " . $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Generate system report
     */
    public function generateSystemReport() {
        $report = [
            'generated_at' => date('Y-m-d H:i:s'),
            'system_info' => $this->getSystemInfo(),
            'health_status' => $this->getSystemHealth(),
            'database_stats' => $this->getDatabaseStats(),
            'settings_summary' => $this->getSettingsSummary(),
            'recent_activity' => $this->getRecentActivity()
        ];
        
        return $report;
    }
    
    /**
     * Check for security issues
     */
    public function securityCheck() {
        $issues = [];
        
        try {
            // Check for default passwords
            $result = $this->conn->query("SELECT username FROM users WHERE password = '$2y$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'");
            if ($result && $result->num_rows > 0) {
                $issues[] = [
                    'type' => 'critical',
                    'issue' => 'Default passwords detected',
                    'description' => 'Some users are still using default passwords',
                    'recommendation' => 'Force password change for all users with default passwords'
                ];
            }
            
            // Check file permissions
            $sensitive_files = ['db.php', 'config.php', '.htaccess'];
            foreach ($sensitive_files as $file) {
                if (file_exists($file)) {
                    $perms = fileperms($file);
                    if (($perms & 0044) || ($perms & 0004)) { // World readable
                        $issues[] = [
                            'type' => 'warning',
                            'issue' => 'File permissions',
                            'description' => "$file is world readable",
                            'recommendation' => 'Set appropriate file permissions (644 or 600)'
                        ];
                    }
                }
            }
            
            // Check for maintenance mode
            $maintenance = $this->getSetting('maintenance_mode');
            if ($maintenance === '1') {
                $issues[] = [
                    'type' => 'info',
                    'issue' => 'Maintenance mode',
                    'description' => 'System is in maintenance mode',
                    'recommendation' => 'Disable maintenance mode when ready'
                ];
            }
            
        } catch (Exception $e) {
            $issues[] = [
                'type' => 'error',
                'issue' => 'Security check failed',
                'description' => $e->getMessage(),
                'recommendation' => 'Review system logs and fix underlying issues'
            ];
        }
        
        return $issues;
    }
    
    /**
     * Backup system settings
     */
    public function backupSettings() {
        try {
            $settings = [];
            $result = $this->conn->query("SELECT setting_key, setting_value FROM system_settings");
            
            while ($row = $result->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            
            $backup_data = [
                'backup_date' => date('Y-m-d H:i:s'),
                'version' => '1.0',
                'settings' => $settings
            ];
            
            $backup_dir = 'backups/settings/';
            if (!is_dir($backup_dir)) {
                mkdir($backup_dir, 0755, true);
            }
            
            $filename = $backup_dir . 'settings_backup_' . date('Y-m-d_H-i-s') . '.json';
            file_put_contents($filename, json_encode($backup_data, JSON_PRETTY_PRINT));
            
            return [
                'success' => true,
                'filename' => $filename,
                'size' => filesize($filename)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    // Private helper methods
    
    private function convertToBytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = (int) $val;
        
        switch($last) {
            case 'g': $val *= 1024;
            case 'm': $val *= 1024;
            case 'k': $val *= 1024;
        }
        
        return $val;
    }
    
    private function getSystemInfo() {
        return [
            'php_version' => PHP_VERSION,
            'mysql_version' => $this->conn->server_info,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'timezone' => date_default_timezone_get(),
            'current_time' => date('Y-m-d H:i:s')
        ];
    }
    
    private function getDatabaseStats() {
        $stats = [];
        
        try {
            // Database size
            $result = $this->conn->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb FROM information_schema.tables WHERE table_schema = DATABASE()");
            $row = $result->fetch_assoc();
            $stats['database_size_mb'] = $row['size_mb'];
            
            // Table count
            $result = $this->conn->query("SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = DATABASE()");
            $row = $result->fetch_assoc();
            $stats['table_count'] = $row['table_count'];
            
            // Record counts for main tables
            $main_tables = ['users', 'system_settings', 'backup_history'];
            foreach ($main_tables as $table) {
                $result = $this->conn->query("SELECT COUNT(*) as count FROM `$table`");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $stats[$table . '_count'] = $row['count'];
                }
            }
            
        } catch (Exception $e) {
            $stats['error'] = $e->getMessage();
        }
        
        return $stats;
    }
    
    private function getSettingsSummary() {
        $summary = [];
        
        try {
            $result = $this->conn->query("SELECT COUNT(*) as total FROM system_settings");
            $row = $result->fetch_assoc();
            $summary['total_settings'] = $row['total'];
            
            // Count by groups (if we had groups)
            $important_settings = ['maintenance_mode', 'auto_backup_enabled', 'two_factor_auth'];
            foreach ($important_settings as $setting) {
                $value = $this->getSetting($setting);
                $summary[$setting] = $value;
            }
            
        } catch (Exception $e) {
            $summary['error'] = $e->getMessage();
        }
        
        return $summary;
    }
    
    private function getRecentActivity() {
        $activity = [];
        
        try {
            // Check if settings_log table exists
            $table_check = $this->conn->query("SHOW TABLES LIKE 'settings_log'");
            if ($table_check->num_rows > 0) {
                $result = $this->conn->query("SELECT * FROM settings_log ORDER BY changed_at DESC LIMIT 10");
                while ($row = $result->fetch_assoc()) {
                    $activity[] = $row;
                }
            }
            
        } catch (Exception $e) {
            $activity[] = ['error' => $e->getMessage()];
        }
        
        return $activity;
    }
    
    private function getSetting($key) {
        try {
            $stmt = $this->conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $stmt->bind_param('s', $key);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                return $row['setting_value'];
            }
            
            return null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    private function getAllTableNames() {
        $tables = [];
        $result = $this->conn->query("SHOW TABLES");
        
        while ($row = $result->fetch_array()) {
            $tables[] = "`{$row[0]}`";
        }
        
        return $tables;
    }
}

// Standalone utility functions

function validateSystemSettings($settings) {
    $errors = [];
    
    // Validate email settings
    if (!empty($settings['company_email']) && !filter_var($settings['company_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid company email address';
    }
    
    if (!empty($settings['mail_from_address']) && !filter_var($settings['mail_from_address'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid mail from address';
    }
    
    // Validate URLs
    if (!empty($settings['site_url']) && !filter_var($settings['site_url'], FILTER_VALIDATE_URL)) {
        $errors[] = 'Invalid site URL';
    }
    
    if (!empty($settings['company_website']) && !filter_var($settings['company_website'], FILTER_VALIDATE_URL)) {
        $errors[] = 'Invalid company website URL';
    }
    
    // Validate numeric values
    $numeric_fields = ['session_timeout', 'max_login_attempts', 'lockout_duration', 'password_min_length', 'items_per_page', 'backup_retention_days'];
    
    foreach ($numeric_fields as $field) {
        if (isset($settings[$field]) && !is_numeric($settings[$field])) {
            $errors[] = "Invalid $field - must be numeric";
        }
    }
    
    // Validate ranges
    if (isset($settings['session_timeout']) && ($settings['session_timeout'] < 5 || $settings['session_timeout'] > 1440)) {
        $errors[] = 'Session timeout must be between 5 and 1440 minutes';
    }
    
    if (isset($settings['password_min_length']) && ($settings['password_min_length'] < 6 || $settings['password_min_length'] > 50)) {
        $errors[] = 'Password minimum length must be between 6 and 50 characters';
    }
    
    return $errors;
}

function getDefaultSettings() {
    return [
        'site_name' => 'BillBook',
        'site_url' => '',
        'timezone' => 'UTC',
        'date_format' => 'Y-m-d',
        'time_format' => 'H:i:s',
        'currency' => 'USD',
        'currency_symbol' => '$',
        'language' => 'en',
        'items_per_page' => '25',
        'maintenance_mode' => '0',
        'company_name' => '',
        'company_address' => '',
        'company_phone' => '',
        'company_email' => '',
        'company_website' => '',
        'company_tax_id' => '',
        'company_registration' => '',
        'session_timeout' => '30',
        'max_login_attempts' => '5',
        'lockout_duration' => '15',
        'password_min_length' => '8',
        'auto_backup_enabled' => '0',
        'backup_frequency' => 'daily',
        'backup_time' => '02:00',
        'backup_retention_days' => '30'
    ];
}
?>
