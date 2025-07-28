<?php
// Database setup script for HR and Manager portals
include 'db.php';

echo "<h1>HR & Manager Portal Database Setup</h1>";
echo "<p>Setting up required database tables and structures...</p>";

try {
    // Create leave_requests table if it doesn't exist (enhanced version)
    $leave_requests_table = "
        CREATE TABLE IF NOT EXISTS leave_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            leave_type VARCHAR(50) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            start_time TIME NULL,
            end_time TIME NULL,
            duration_days DECIMAL(4,2) NOT NULL,
            reason TEXT NOT NULL,
            reason_category VARCHAR(50) DEFAULT 'personal',
            emergency_contact VARCHAR(255) NULL,
            priority ENUM('normal', 'urgent', 'emergency') DEFAULT 'normal',
            status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
            applied_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            processed_date TIMESTAMP NULL,
            processed_by VARCHAR(100) NULL,
            manager_comments TEXT NULL,
            notify_manager TINYINT(1) DEFAULT 0,
            handover_details TEXT NULL,
            attachment_path VARCHAR(500) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_employee_id (employee_id),
            INDEX idx_status (status),
            INDEX idx_applied_date (applied_date),
            INDEX idx_leave_type (leave_type),
            FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    if ($conn->query($leave_requests_table)) {
        echo "✅ Leave requests table created/verified successfully<br>";
    } else {
        echo "❌ Error creating leave_requests table: " . $conn->error . "<br>";
    }
    
    // Create employee_leave_balance table
    $leave_balance_table = "
        CREATE TABLE IF NOT EXISTS employee_leave_balance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            year YEAR NOT NULL,
            casual_leave_balance DECIMAL(4,1) DEFAULT 12.0,
            sick_leave_balance DECIMAL(4,1) DEFAULT 7.0,
            earned_leave_balance DECIMAL(4,1) DEFAULT 21.0,
            comp_off_balance DECIMAL(4,1) DEFAULT 5.0,
            maternity_leave_balance DECIMAL(4,1) DEFAULT 180.0,
            paternity_leave_balance DECIMAL(4,1) DEFAULT 15.0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_employee_year (employee_id, year),
            INDEX idx_employee_id (employee_id),
            INDEX idx_year (year),
            FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    if ($conn->query($leave_balance_table)) {
        echo "✅ Employee leave balance table created/verified successfully<br>";
    } else {
        echo "❌ Error creating employee_leave_balance table: " . $conn->error . "<br>";
    }
    
    // Create HR activity log table
    $hr_activity_table = "
        CREATE TABLE IF NOT EXISTS hr_activity_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            activity_type VARCHAR(100) NOT NULL,
            description TEXT,
            performed_by VARCHAR(100) NOT NULL,
            target_employee_id INT NULL,
            activity_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            INDEX idx_activity_date (activity_date),
            INDEX idx_performed_by (performed_by),
            INDEX idx_activity_type (activity_type),
            INDEX idx_target_employee (target_employee_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    if ($conn->query($hr_activity_table)) {
        echo "✅ HR activity log table created/verified successfully<br>";
    } else {
        echo "❌ Error creating hr_activity_log table: " . $conn->error . "<br>";
    }
    
    // Create HR settings table
    $hr_settings_table = "
        CREATE TABLE IF NOT EXISTS hr_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            setting_type ENUM('text', 'number', 'boolean', 'json') DEFAULT 'text',
            description TEXT NULL,
            updated_by VARCHAR(100),
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_setting_key (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    if ($conn->query($hr_settings_table)) {
        echo "✅ HR settings table created/verified successfully<br>";
    } else {
        echo "❌ Error creating hr_settings table: " . $conn->error . "<br>";
    }
    
    // Create company holidays table
    $holidays_table = "
        CREATE TABLE IF NOT EXISTS company_holidays (
            id INT AUTO_INCREMENT PRIMARY KEY,
            holiday_name VARCHAR(255) NOT NULL,
            holiday_date DATE NOT NULL,
            holiday_type ENUM('national', 'religious', 'company', 'optional') DEFAULT 'national',
            description TEXT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_by VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_holiday_date (holiday_date),
            INDEX idx_holiday_type (holiday_type),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    if ($conn->query($holidays_table)) {
        echo "✅ Company holidays table created/verified successfully<br>";
    } else {
        echo "❌ Error creating company_holidays table: " . $conn->error . "<br>";
    }
    
    // Insert default HR settings
    $default_settings = [
        ['annual_casual_leave', '12', 'number', 'Annual casual leave days'],
        ['annual_sick_leave', '7', 'number', 'Annual sick leave days'],
        ['annual_earned_leave', '21', 'number', 'Annual earned leave days'],
        ['auto_approve_short_leave', '1', 'boolean', 'Auto-approve short leaves'],
        ['require_manager_approval', '1', 'boolean', 'Require manager approval for leaves'],
        ['max_consecutive_days_without_approval', '3', 'number', 'Maximum consecutive days without approval'],
        ['leave_carry_forward_enabled', '1', 'boolean', 'Enable leave carry forward'],
        ['max_carry_forward_days', '5', 'number', 'Maximum carry forward days'],
        ['email_notifications_enabled', '1', 'boolean', 'Enable email notifications'],
        ['sms_notifications_enabled', '0', 'boolean', 'Enable SMS notifications']
    ];
    
    foreach ($default_settings as $setting) {
        $insert_setting = "
            INSERT IGNORE INTO hr_settings (setting_key, setting_value, setting_type, description) 
            VALUES (?, ?, ?, ?)
        ";
        $stmt = $conn->prepare($insert_setting);
        $stmt->bind_param("ssss", $setting[0], $setting[1], $setting[2], $setting[3]);
        $stmt->execute();
    }
    echo "✅ Default HR settings inserted successfully<br>";
    
    // Insert default leave balances for existing employees
    $current_year = date('Y');
    $insert_balances = "
        INSERT IGNORE INTO employee_leave_balance (employee_id, year) 
        SELECT employee_id, $current_year FROM employees WHERE status = 'active'
    ";
    
    if ($conn->query($insert_balances)) {
        echo "✅ Default leave balances created for existing employees<br>";
    } else {
        echo "❌ Error creating default leave balances: " . $conn->error . "<br>";
    }
    
    // Insert some sample holidays
    $current_year = date('Y');
    $sample_holidays = [
        ['Republic Day', "$current_year-01-26", 'national'],
        ['Independence Day', "$current_year-08-15", 'national'],
        ['Gandhi Jayanti', "$current_year-10-02", 'national'],
        ['Diwali', "$current_year-11-12", 'religious'],
        ['Christmas', "$current_year-12-25", 'religious'],
        ['New Year', "$current_year-01-01", 'national']
    ];
    
    foreach ($sample_holidays as $holiday) {
        $insert_holiday = "
            INSERT IGNORE INTO company_holidays (holiday_name, holiday_date, holiday_type, created_by) 
            VALUES (?, ?, ?, 'SYSTEM')
        ";
        $stmt = $conn->prepare($insert_holiday);
        $stmt->bind_param("sss", $holiday[0], $holiday[1], $holiday[2]);
        $stmt->execute();
    }
    echo "✅ Sample holidays inserted successfully<br>";
    
    echo "<br><h2>✅ Database Setup Complete!</h2>";
    echo "<p><strong>Summary:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Leave requests management system</li>";
    echo "<li>✅ Employee leave balance tracking</li>";
    echo "<li>✅ HR activity logging</li>";
    echo "<li>✅ HR settings configuration</li>";
    echo "<li>✅ Company holidays management</li>";
    echo "<li>✅ Default data inserted</li>";
    echo "</ul>";
    
    echo "<br><h3>🚀 Next Steps:</h3>";
    echo "<ul>";
    echo "<li>Access <strong><a href='pages/hr/hr_dashboard.php'>HR Portal</a></strong> to manage leave requests</li>";
    echo "<li>Access <strong><a href='pages/manager/manager_dashboard.php'>Manager Portal</a></strong> to approve team requests</li>";
    echo "<li>Access <strong><a href='pages/employee/employee_portal.php'>Employee Portal</a></strong> for self-service</li>";
    echo "<li>Configure HR settings as per your organization's policies</li>";
    echo "<li>Add company-specific holidays</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "❌ <strong>Error during setup:</strong> " . $e->getMessage() . "<br>";
}

echo "<br><hr>";
echo "<p><em>Setup completed on: " . date('Y-m-d H:i:s') . "</em></p>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1 { color: #2c3e50; }
h2 { color: #27ae60; }
h3 { color: #3498db; }
ul { margin: 10px 0; }
li { margin: 5px 0; }
a { color: #3498db; text-decoration: none; }
a:hover { text-decoration: underline; }
</style>
