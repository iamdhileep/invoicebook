<?php
/**
 * Database and API Connection Test Script
 * Tests all connections and identifies missing functions/tables
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db.php';

class SystemHealthCheck {
    private $db;
    private $results = [];
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function runAllTests() {
        echo "<h1>🔍 BillBook System Health Check</h1>";
        echo "<hr>";
        
        $this->testDatabaseConnection();
        $this->testRequiredTables();
        $this->testAttendanceSystemFunctions();
        $this->testAPIEndpoints();
        $this->testMissingFunctions();
        $this->generateReport();
    }
    
    private function testDatabaseConnection() {
        echo "<h2>📊 Database Connection Test</h2>";
        
        try {
            if ($this->db && $this->db->ping()) {
                $this->logResult('Database Connection', 'SUCCESS', 'Connected to MySQL database successfully');
                
                // Test database info
                $result = $this->db->query("SELECT VERSION() as version");
                if ($result) {
                    $version = $result->fetch_assoc()['version'];
                    $this->logResult('MySQL Version', 'INFO', $version);
                }
                
                // Test database name
                $result = $this->db->query("SELECT DATABASE() as db_name");
                if ($result) {
                    $dbName = $result->fetch_assoc()['db_name'];
                    $this->logResult('Database Name', 'INFO', $dbName);
                }
                
            } else {
                $this->logResult('Database Connection', 'ERROR', 'Failed to connect to database');
            }
        } catch (Exception $e) {
            $this->logResult('Database Connection', 'ERROR', $e->getMessage());
        }
    }
    
    private function testRequiredTables() {
        echo "<h2>📋 Required Tables Check</h2>";
        
        $requiredTables = [
            'employees' => 'Employee management',
            'attendance' => 'Attendance records',
            'leave_requests' => 'Leave management',
            'notifications' => 'Notification system',
            'audit_trail' => 'Audit logging',
            'employee_leave_balance' => 'Leave balance tracking',
            'biometric_devices' => 'Biometric integration',
            'api_configurations' => 'API settings',
            'scheduled_notifications' => 'Scheduled notifications',
            'wfh_requests' => 'Work from home requests',
            'holidays' => 'Holiday management',
            'employee_devices' => 'Mobile device tokens',
            'notification_log' => 'Notification logging',
            'api_activity_log' => 'API activity tracking'
        ];
        
        foreach ($requiredTables as $table => $description) {
            $result = $this->db->query("SHOW TABLES LIKE '$table'");
            if ($result && $result->num_rows > 0) {
                $this->logResult("Table: $table", 'SUCCESS', $description);
                
                // Check table structure
                $columns = $this->db->query("DESCRIBE $table");
                if ($columns) {
                    $columnCount = $columns->num_rows;
                    $this->logResult("  └─ Columns", 'INFO', "$columnCount columns found");
                }
            } else {
                $this->logResult("Table: $table", 'MISSING', "Required for: $description");
                $this->createMissingTable($table);
            }
        }
    }
    
    private function createMissingTable($tableName) {
        $createQueries = [
            'notifications' => "
                CREATE TABLE notifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    employee_id INT,
                    title VARCHAR(255) NOT NULL,
                    message TEXT NOT NULL,
                    type VARCHAR(50) DEFAULT 'info',
                    is_read BOOLEAN DEFAULT FALSE,
                    read_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_employee_id (employee_id),
                    INDEX idx_created_at (created_at),
                    INDEX idx_is_read (is_read)
                )",
            
            'audit_trail' => "
                CREATE TABLE audit_trail (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    employee_id INT,
                    action VARCHAR(100) NOT NULL,
                    description TEXT,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_employee_id (employee_id),
                    INDEX idx_action (action),
                    INDEX idx_created_at (created_at)
                )",
            
            'employee_leave_balance' => "
                CREATE TABLE employee_leave_balance (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    employee_id INT NOT NULL,
                    year INT NOT NULL,
                    leave_type VARCHAR(50) NOT NULL,
                    annual_quota INT DEFAULT 0,
                    used_days INT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_employee_year_type (employee_id, year, leave_type),
                    INDEX idx_employee_id (employee_id)
                )",
            
            'biometric_devices' => "
                CREATE TABLE biometric_devices (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    device_name VARCHAR(100) NOT NULL,
                    ip VARCHAR(45) NOT NULL,
                    port INT DEFAULT 4370,
                    device_type VARCHAR(50) DEFAULT 'ZKTeco',
                    is_active BOOLEAN DEFAULT TRUE,
                    last_sync TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )",
            
            'api_configurations' => "
                CREATE TABLE api_configurations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    config_key VARCHAR(100) NOT NULL UNIQUE,
                    config_value TEXT,
                    description TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )",
            
            'scheduled_notifications' => "
                CREATE TABLE scheduled_notifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    employee_id INT,
                    title VARCHAR(255) NOT NULL,
                    message TEXT NOT NULL,
                    type VARCHAR(50) DEFAULT 'info',
                    channels JSON,
                    schedule_time TIMESTAMP NOT NULL,
                    is_sent BOOLEAN DEFAULT FALSE,
                    sent_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_schedule_time (schedule_time),
                    INDEX idx_is_sent (is_sent)
                )",
            
            'wfh_requests' => "
                CREATE TABLE wfh_requests (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    employee_id INT NOT NULL,
                    date DATE NOT NULL,
                    reason TEXT,
                    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
                    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    approved_by INT NULL,
                    approved_at TIMESTAMP NULL,
                    INDEX idx_employee_id (employee_id),
                    INDEX idx_date (date),
                    INDEX idx_status (status)
                )",
            
            'holidays' => "
                CREATE TABLE holidays (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    date DATE NOT NULL UNIQUE,
                    description VARCHAR(255) NOT NULL,
                    type VARCHAR(50) DEFAULT 'public',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )",
            
            'employee_devices' => "
                CREATE TABLE employee_devices (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    employee_id INT NOT NULL,
                    device_token VARCHAR(255) NOT NULL,
                    platform VARCHAR(20) NOT NULL,
                    is_active BOOLEAN DEFAULT TRUE,
                    last_used TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_employee_id (employee_id)
                )",
            
            'notification_log' => "
                CREATE TABLE notification_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    employee_id INT,
                    channel VARCHAR(20) NOT NULL,
                    message TEXT NOT NULL,
                    status VARCHAR(20) DEFAULT 'sent',
                    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_employee_id (employee_id),
                    INDEX idx_channel (channel)
                )",
            
            'api_activity_log' => "
                CREATE TABLE api_activity_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    action VARCHAR(100) NOT NULL,
                    details TEXT,
                    status VARCHAR(20) DEFAULT 'success',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_action (action),
                    INDEX idx_created_at (created_at)
                )"
        ];
        
        if (isset($createQueries[$tableName])) {
            try {
                $this->db->query($createQueries[$tableName]);
                $this->logResult("  └─ Create Table", 'SUCCESS', "Table $tableName created successfully");
            } catch (Exception $e) {
                $this->logResult("  └─ Create Table", 'ERROR', "Failed to create $tableName: " . $e->getMessage());
            }
        }
    }
    
    private function testAttendanceSystemFunctions() {
        echo "<h2>⚙️ Attendance System Functions Test</h2>";
        
        // Test if attendance.php exists and is accessible
        $attendanceFile = './pages/attendance/attendance.php';
        if (file_exists($attendanceFile)) {
            $this->logResult('Attendance Page', 'SUCCESS', 'attendance.php found');
        } else {
            $this->logResult('Attendance Page', 'ERROR', 'attendance.php not found');
        }
        
        // Test core PHP files
        $coreFiles = [
            'save_attendance.php' => 'Save attendance functionality',
            'mark_attendance.php' => 'Mark attendance functionality',
            'mobile_attendance_api.php' => 'Mobile API integration',
            'notification_system.php' => 'Notification system',
            'external_api_integration.php' => 'External API integration'
        ];
        
        foreach ($coreFiles as $file => $description) {
            if (file_exists($file)) {
                $this->logResult("File: $file", 'SUCCESS', $description);
            } else {
                $this->logResult("File: $file", 'MISSING', $description);
            }
        }
    }
    
    private function testAPIEndpoints() {
        echo "<h2>🌐 API Endpoints Test</h2>";
        
        // Test if mobile API is accessible
        $mobileApiFile = './mobile_attendance_api.php';
        if (file_exists($mobileApiFile)) {
            $this->logResult('Mobile API', 'SUCCESS', 'mobile_attendance_api.php accessible');
            
            // Test API syntax
            $syntaxCheck = shell_exec("php -l $mobileApiFile 2>&1");
            if (strpos($syntaxCheck, 'No syntax errors') !== false) {
                $this->logResult('  └─ Syntax Check', 'SUCCESS', 'No syntax errors found');
            } else {
                $this->logResult('  └─ Syntax Check', 'ERROR', $syntaxCheck);
            }
        }
        
        // Test notification system
        $notificationFile = './notification_system.php';
        if (file_exists($notificationFile)) {
            $this->logResult('Notification System', 'SUCCESS', 'notification_system.php accessible');
            
            $syntaxCheck = shell_exec("php -l $notificationFile 2>&1");
            if (strpos($syntaxCheck, 'No syntax errors') !== false) {
                $this->logResult('  └─ Syntax Check', 'SUCCESS', 'No syntax errors found');
            } else {
                $this->logResult('  └─ Syntax Check', 'ERROR', $syntaxCheck);
            }
        }
        
        // Test external API integration
        $externalApiFile = './external_api_integration.php';
        if (file_exists($externalApiFile)) {
            $this->logResult('External API Integration', 'SUCCESS', 'external_api_integration.php accessible');
            
            $syntaxCheck = shell_exec("php -l $externalApiFile 2>&1");
            if (strpos($syntaxCheck, 'No syntax errors') !== false) {
                $this->logResult('  └─ Syntax Check', 'SUCCESS', 'No syntax errors found');
            } else {
                $this->logResult('  └─ Syntax Check', 'ERROR', $syntaxCheck);
            }
        }
    }
    
    private function testMissingFunctions() {
        echo "<h2>🔍 Missing Functions Analysis</h2>";
        
        // Check attendance.php for missing JavaScript functions
        $attendanceFile = './pages/attendance/attendance.php';
        if (file_exists($attendanceFile)) {
            $content = file_get_contents($attendanceFile);
            
            $requiredFunctions = [
                'changeCalendarMonth' => 'Calendar navigation',
                'filterLeaveCalendar' => 'Leave filtering',
                'reviewEmployee' => 'Employee review',
                'approveLeave' => 'Leave approval',
                'bulkApproveLeaves' => 'Bulk operations',
                'savePolicySettings' => 'Policy configuration',
                'exportAuditCSV' => 'Audit export',
                'showNotification' => 'Notification display'
            ];
            
            foreach ($requiredFunctions as $function => $description) {
                if (strpos($content, "function $function") !== false || strpos($content, "$function =") !== false) {
                    $this->logResult("JS Function: $function", 'SUCCESS', $description);
                } else {
                    $this->logResult("JS Function: $function", 'MISSING', $description);
                }
            }
        }
        
        // Check for missing modal content
        if (file_exists($attendanceFile)) {
            $content = file_get_contents($attendanceFile);
            
            $requiredModals = [
                'aiSuggestionsModal' => 'AI Suggestions modal',
                'policyConfigModal' => 'Policy Configuration modal',
                'leaveCalendarModal' => 'Leave Calendar modal'
            ];
            
            foreach ($requiredModals as $modal => $description) {
                if (strpos($content, "id=\"$modal\"") !== false) {
                    $this->logResult("Modal: $modal", 'SUCCESS', $description);
                } else {
                    $this->logResult("Modal: $modal", 'MISSING', $description);
                }
            }
        }
    }
    
    private function logResult($test, $status, $message) {
        $this->results[] = [
            'test' => $test,
            'status' => $status,
            'message' => $message
        ];
        
        $color = '';
        $icon = '';
        
        switch ($status) {
            case 'SUCCESS':
                $color = 'color: green; font-weight: bold;';
                $icon = '✅';
                break;
            case 'ERROR':
                $color = 'color: red; font-weight: bold;';
                $icon = '❌';
                break;
            case 'MISSING':
                $color = 'color: orange; font-weight: bold;';
                $icon = '⚠️';
                break;
            case 'INFO':
                $color = 'color: blue;';
                $icon = 'ℹ️';
                break;
        }
        
        echo "<div style='$color margin: 5px 0;'>$icon $test: $message</div>";
    }
    
    private function generateReport() {
        echo "<hr>";
        echo "<h2>📊 Summary Report</h2>";
        
        $totalTests = count($this->results);
        $successCount = count(array_filter($this->results, fn($r) => $r['status'] === 'SUCCESS'));
        $errorCount = count(array_filter($this->results, fn($r) => $r['status'] === 'ERROR'));
        $missingCount = count(array_filter($this->results, fn($r) => $r['status'] === 'MISSING'));
        
        echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
        echo "<h3>Test Results Summary:</h3>";
        echo "<ul>";
        echo "<li><strong>Total Tests:</strong> $totalTests</li>";
        echo "<li style='color: green;'><strong>Successful:</strong> $successCount</li>";
        echo "<li style='color: red;'><strong>Errors:</strong> $errorCount</li>";
        echo "<li style='color: orange;'><strong>Missing/Issues:</strong> $missingCount</li>";
        echo "<li><strong>Success Rate:</strong> " . round(($successCount / $totalTests) * 100, 1) . "%</li>";
        echo "</ul>";
        echo "</div>";
        
        if ($errorCount > 0 || $missingCount > 0) {
            echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
            echo "<h4>⚠️ Action Required:</h4>";
            echo "<p>Some issues were found that need attention. Please review the missing tables, files, or functions above.</p>";
            echo "</div>";
        } else {
            echo "<div style='background: #d4edda; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
            echo "<h4>✅ System Health: Excellent!</h4>";
            echo "<p>All systems are working correctly. Your attendance system is fully operational.</p>";
            echo "</div>";
        }
    }
}

// Run the health check
if (isset($conn)) {
    $healthCheck = new SystemHealthCheck($conn);
    $healthCheck->runAllTests();
} else {
    echo "<h1 style='color: red;'>❌ Critical Error: Database connection not available</h1>";
    echo "<p>Please check your database configuration in db.php</p>";
}
?>
