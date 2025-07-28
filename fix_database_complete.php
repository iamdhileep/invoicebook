<?php
// Database fix script for attendance system
include 'db.php';

try {
    echo "<h2>🔧 Database Fix Script</h2>\n";
    
    // 1. Fix attendance table - add missing columns
    echo "<h3>1. Fixing attendance table...</h3>\n";
    
    // Check if marked_by column exists
    $result = $conn->query("SHOW COLUMNS FROM attendance LIKE 'marked_by'");
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE attendance ADD COLUMN marked_by INT(11) DEFAULT NULL AFTER notes");
        echo "✅ Added marked_by column<br>\n";
    } else {
        echo "✅ marked_by column already exists<br>\n";
    }
    
    // Check if created_at column exists
    $result = $conn->query("SHOW COLUMNS FROM attendance LIKE 'created_at'");
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE attendance ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER marked_by");
        echo "✅ Added created_at column<br>\n";
    } else {
        echo "✅ created_at column already exists<br>\n";
    }
    
    // Check if updated_at column exists
    $result = $conn->query("SHOW COLUMNS FROM attendance LIKE 'updated_at'");
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE attendance ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
        echo "✅ Added updated_at column<br>\n";
    } else {
        echo "✅ updated_at column already exists<br>\n";
    }
    
    // 2. Fix employees table - add status column
    echo "<h3>2. Fixing employees table...</h3>\n";
    
    $result = $conn->query("SHOW COLUMNS FROM employees LIKE 'status'");
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE employees ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active' AFTER ifsc_code");
        echo "✅ Added status column to employees<br>\n";
    } else {
        echo "✅ status column already exists in employees<br>\n";
    }
    
    // 3. Create biometric_sync_status table
    echo "<h3>3. Creating biometric_sync_status table...</h3>\n";
    
    $result = $conn->query("SHOW TABLES LIKE 'biometric_sync_status'");
    if ($result->num_rows == 0) {
        $sql = "
        CREATE TABLE biometric_sync_status (
            id INT(11) PRIMARY KEY AUTO_INCREMENT,
            device_id INT(11) NOT NULL,
            campus_name VARCHAR(100) NOT NULL,
            sync_status ENUM('sync', 'failed') DEFAULT 'failed',
            last_sync TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            records_synced INT(11) DEFAULT 0,
            error_message TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (device_id) REFERENCES biometric_devices(id) ON DELETE CASCADE
        )";
        $conn->query($sql);
        echo "✅ Created biometric_sync_status table<br>\n";
    } else {
        echo "✅ biometric_sync_status table already exists<br>\n";
    }
    
    // 4. Create device_settings table
    echo "<h3>4. Creating device_settings table...</h3>\n";
    
    $result = $conn->query("SHOW TABLES LIKE 'device_settings'");
    if ($result->num_rows == 0) {
        $sql = "
        CREATE TABLE device_settings (
            id INT(11) PRIMARY KEY AUTO_INCREMENT,
            setting_name VARCHAR(100) NOT NULL,
            setting_value TEXT NOT NULL,
            description TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $conn->query($sql);
        echo "✅ Created device_settings table<br>\n";
        
        // Insert default settings
        $defaultSettings = [
            ['sync_interval', '10', 'Sync interval in minutes'],
            ['connection_timeout', '30', 'Connection timeout in seconds'],
            ['auto_retry', '1', 'Auto-retry failed connections (1=yes, 0=no)']
        ];
        
        $stmt = $conn->prepare("INSERT INTO device_settings (setting_name, setting_value, description) VALUES (?, ?, ?)");
        foreach ($defaultSettings as $setting) {
            $stmt->bind_param("sss", $setting[0], $setting[1], $setting[2]);
            $stmt->execute();
        }
        echo "✅ Added default device settings<br>\n";
    } else {
        echo "✅ device_settings table already exists<br>\n";
    }
    
    // 5. Create leave_history table
    echo "<h3>5. Creating leave_history table...</h3>\n";
    
    $result = $conn->query("SHOW TABLES LIKE 'leave_history'");
    if ($result->num_rows == 0) {
        $sql = "
        CREATE TABLE leave_history (
            id INT(11) PRIMARY KEY AUTO_INCREMENT,
            employee_id INT(11) NOT NULL,
            employee_name VARCHAR(100) NOT NULL,
            employee_code VARCHAR(20) NOT NULL,
            type ENUM('leave', 'permission') NOT NULL,
            leave_type VARCHAR(50) NULL,
            permission_type VARCHAR(50) NULL,
            start_date DATE NULL,
            end_date DATE NULL,
            permission_date DATE NULL,
            from_time TIME NULL,
            to_time TIME NULL,
            total_days INT(11) NULL,
            reason TEXT NOT NULL,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            applied_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            approved_by INT(11) NULL,
            approved_date TIMESTAMP NULL,
            rejection_reason TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
        )";
        $conn->query($sql);
        echo "✅ Created leave_history table<br>\n";
    } else {
        echo "✅ leave_history table already exists<br>\n";
    }
    
    // 6. Add unique constraint to attendance table if not exists
    echo "<h3>6. Adding unique constraint to attendance table...</h3>\n";
    
    $result = $conn->query("SHOW INDEX FROM attendance WHERE Key_name = 'unique_employee_date'");
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE attendance ADD UNIQUE KEY unique_employee_date (employee_id, attendance_date)");
        echo "✅ Added unique constraint for employee_id and attendance_date<br>\n";
    } else {
        echo "✅ Unique constraint already exists<br>\n";
    }
    
    // 7. Insert sample biometric devices if table is empty
    echo "<h3>7. Adding sample biometric devices...</h3>\n";
    
    $result = $conn->query("SELECT COUNT(*) as count FROM biometric_devices");
    $row = $result->fetch_assoc();
    if ($row['count'] == 0) {
        $devices = [
            ['Main Entrance Scanner', 'fingerprint', 'Main Office Entrance', 1],
            ['Reception Biometric', 'biometric', 'Reception Desk', 1],
            ['Conference Room Scanner', 'facial_recognition', 'Conference Room', 1]
        ];
        
        $stmt = $conn->prepare("INSERT INTO biometric_devices (device_name, device_type, location, is_enabled) VALUES (?, ?, ?, ?)");
        foreach ($devices as $device) {
            $stmt->bind_param("sssi", $device[0], $device[1], $device[2], $device[3]);
            $stmt->execute();
        }
        echo "✅ Added sample biometric devices<br>\n";
    } else {
        echo "✅ Biometric devices already exist<br>\n";
    }
    
    // 8. Insert sample sync status data
    echo "<h3>8. Adding sample sync status data...</h3>\n";
    
    $result = $conn->query("SELECT COUNT(*) as count FROM biometric_sync_status");
    $row = $result->fetch_assoc();
    if ($row['count'] == 0) {
        $syncData = [
            [1, 'Main Campus', 'sync', 0, NULL],
            [2, 'Branch Office', 'sync', 0, NULL],
            [3, 'Remote Location', 'failed', 0, 'Connection timeout']
        ];
        
        $stmt = $conn->prepare("INSERT INTO biometric_sync_status (device_id, campus_name, sync_status, records_synced, error_message) VALUES (?, ?, ?, ?, ?)");
        foreach ($syncData as $data) {
            $stmt->bind_param("issis", $data[0], $data[1], $data[2], $data[3], $data[4]);
            $stmt->execute();
        }
        echo "✅ Added sample sync status data<br>\n";
    } else {
        echo "✅ Sync status data already exists<br>\n";
    }
    
    // 9. Add default leave types if none exist
    echo "<h3>9. Adding default leave types...</h3>\n";
    
    $result = $conn->query("SELECT COUNT(*) as count FROM leave_types");
    $row = $result->fetch_assoc();
    if ($row['count'] == 0) {
        $leaveTypes = [
            ['casual', 'Casual Leave', 12, 'General casual leave'],
            ['sick', 'Sick Leave', 10, 'Medical leave'],
            ['earned', 'Earned Leave', 15, 'Annual earned leave'],
            ['maternity', 'Maternity Leave', 180, 'Maternity leave for female employees'],
            ['paternity', 'Paternity Leave', 15, 'Paternity leave for male employees'],
            ['emergency', 'Emergency Leave', 5, 'Emergency situations']
        ];
        
        $stmt = $conn->prepare("INSERT INTO leave_types (leave_code, leave_name, default_days, description) VALUES (?, ?, ?, ?)");
        foreach ($leaveTypes as $type) {
            $stmt->bind_param("ssis", $type[0], $type[1], $type[2], $type[3]);
            $stmt->execute();
        }
        echo "✅ Added default leave types<br>\n";
    } else {
        echo "✅ Leave types already exist<br>\n";
    }
    
    echo "<h2>✅ Database fix completed successfully!</h2>\n";
    
} catch (Exception $e) {
    echo "<h2>❌ Error: " . $e->getMessage() . "</h2>\n";
}

$conn->close();
?>
