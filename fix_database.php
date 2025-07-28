<?php
// Fix missing tables and foreign key issues
include 'db.php';

echo "Fixing missing tables and relationships...\n";

// Create missing core tables first
$core_tables = [
    "CREATE TABLE IF NOT EXISTS smart_attendance_methods (
        id INT PRIMARY KEY AUTO_INCREMENT,
        method_name VARCHAR(50) NOT NULL,
        method_type ENUM('face_recognition', 'qr_code', 'gps', 'ip_based', 'biometric') NOT NULL,
        is_enabled BOOLEAN DEFAULT TRUE,
        configuration JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS face_recognition_data (
        id INT PRIMARY KEY AUTO_INCREMENT,
        employee_id INT NOT NULL,
        face_encoding TEXT NOT NULL,
        face_image_path VARCHAR(255),
        confidence_threshold DECIMAL(3,2) DEFAULT 0.85,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS smart_attendance_logs (
        id INT PRIMARY KEY AUTO_INCREMENT,
        employee_id INT NOT NULL,
        attendance_method ENUM('face_recognition', 'qr_code', 'gps', 'ip_based', 'biometric', 'manual') NOT NULL,
        punch_type ENUM('in', 'out') NOT NULL,
        punch_time DATETIME NOT NULL,
        location_data JSON,
        device_info JSON,
        confidence_score DECIMAL(3,2),
        verification_status ENUM('verified', 'pending', 'failed') DEFAULT 'verified',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS leave_types (
        id INT PRIMARY KEY AUTO_INCREMENT,
        leave_name VARCHAR(50) NOT NULL,
        leave_code VARCHAR(10) UNIQUE NOT NULL,
        color_code VARCHAR(7) DEFAULT '#007bff',
        max_days_per_year INT DEFAULT 365,
        can_carry_forward BOOLEAN DEFAULT FALSE,
        carry_forward_limit INT DEFAULT 0,
        can_encash BOOLEAN DEFAULT FALSE,
        requires_approval BOOLEAN DEFAULT TRUE,
        advance_days_required INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS employee_leave_balances (
        id INT PRIMARY KEY AUTO_INCREMENT,
        employee_id INT NOT NULL,
        leave_type_id INT NOT NULL,
        year INT NOT NULL,
        opening_balance DECIMAL(4,1) DEFAULT 0,
        earned_balance DECIMAL(4,1) DEFAULT 0,
        used_balance DECIMAL(4,1) DEFAULT 0,
        carry_forward DECIMAL(4,1) DEFAULT 0,
        encashed DECIMAL(4,1) DEFAULT 0,
        remaining_balance DECIMAL(4,1) DEFAULT 0,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
        FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE,
        UNIQUE KEY unique_employee_leave_year (employee_id, leave_type_id, year)
    )",

    "CREATE TABLE IF NOT EXISTS mobile_devices (
        id INT PRIMARY KEY AUTO_INCREMENT,
        employee_id INT NOT NULL,
        device_token VARCHAR(255) UNIQUE NOT NULL,
        device_type ENUM('android', 'ios') NOT NULL,
        device_model VARCHAR(100),
        app_version VARCHAR(20),
        is_active BOOLEAN DEFAULT TRUE,
        last_sync TIMESTAMP NULL,
        push_enabled BOOLEAN DEFAULT TRUE,
        location_enabled BOOLEAN DEFAULT TRUE,
        registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS mobile_attendance_logs (
        id INT PRIMARY KEY AUTO_INCREMENT,
        employee_id INT NOT NULL,
        device_id INT NOT NULL,
        punch_type ENUM('in', 'out') NOT NULL,
        punch_time DATETIME NOT NULL,
        gps_latitude DECIMAL(10, 8),
        gps_longitude DECIMAL(11, 8),
        gps_accuracy DECIMAL(6,2),
        selfie_path VARCHAR(255),
        is_offline_sync BOOLEAN DEFAULT FALSE,
        sync_status ENUM('pending', 'synced', 'failed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
        FOREIGN KEY (device_id) REFERENCES mobile_devices(id) ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS notification_templates (
        id INT PRIMARY KEY AUTO_INCREMENT,
        template_name VARCHAR(100) NOT NULL,
        template_type ENUM('email', 'sms', 'push', 'whatsapp') NOT NULL,
        subject VARCHAR(200),
        message_body TEXT NOT NULL,
        variables JSON,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS audit_trail (
        id INT PRIMARY KEY AUTO_INCREMENT,
        table_name VARCHAR(50) NOT NULL,
        record_id INT NOT NULL,
        action ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
        old_values JSON,
        new_values JSON,
        changed_by INT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        geo_location JSON,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_table_record (table_name, record_id),
        INDEX idx_timestamp (timestamp)
    )",

    "CREATE TABLE IF NOT EXISTS api_integrations (
        id INT PRIMARY KEY AUTO_INCREMENT,
        integration_name VARCHAR(100) NOT NULL,
        integration_type ENUM('hrms', 'payroll', 'slack', 'teams', 'webhook') NOT NULL,
        api_endpoint VARCHAR(255),
        api_key_hash VARCHAR(255),
        configuration JSON,
        is_active BOOLEAN DEFAULT TRUE,
        last_sync TIMESTAMP NULL,
        sync_frequency INT DEFAULT 3600,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
];

$success_count = 0;
$error_count = 0;

foreach ($core_tables as $sql) {
    try {
        if ($conn->query($sql)) {
            $success_count++;
            echo "✓ Created table successfully\n";
        } else {
            $error_count++;
            echo "✗ Error: " . $conn->error . "\n";
        }
    } catch (Exception $e) {
        $error_count++;
        echo "✗ Exception: " . $e->getMessage() . "\n";
    }
}

// Insert default data
$default_data = [
    "INSERT IGNORE INTO smart_attendance_methods (method_name, method_type, configuration) VALUES
    ('Face Recognition', 'face_recognition', '{\"confidence_threshold\": 0.85, \"max_attempts\": 3}'),
    ('QR Code Scanning', 'qr_code', '{\"code_expiry_minutes\": 15, \"location_required\": true}'),
    ('GPS Location', 'gps', '{\"accuracy_threshold\": 50, \"allowed_locations\": []}'),
    ('IP Based Check-in', 'ip_based', '{\"allowed_networks\": [], \"strict_mode\": false}'),
    ('Biometric Device', 'biometric', '{\"sync_interval\": 300, \"auto_process\": true}')",

    "INSERT IGNORE INTO leave_types (leave_name, leave_code, color_code, max_days_per_year, can_carry_forward, carry_forward_limit) VALUES
    ('Casual Leave', 'CL', '#28a745', 12, TRUE, 5),
    ('Sick Leave', 'SL', '#dc3545', 12, FALSE, 0),
    ('Earned Leave', 'EL', '#007bff', 21, TRUE, 15),
    ('Work From Home', 'WFH', '#17a2b8', 365, FALSE, 0),
    ('Maternity Leave', 'ML', '#e83e8c', 180, FALSE, 0),
    ('Paternity Leave', 'PL', '#fd7e14', 15, FALSE, 0),
    ('Comp Off', 'CO', '#6f42c1', 365, FALSE, 0),
    ('Loss of Pay', 'LOP', '#6c757d', 365, FALSE, 0)",

    "INSERT IGNORE INTO notification_templates (template_name, template_type, subject, message_body) VALUES
    ('Attendance Missing Alert', 'email', 'Attendance Missing - {date}', 'Dear {employee_name}, your attendance is missing for {date}. Please mark your attendance or apply for leave.'),
    ('Leave Approval Request', 'email', 'Leave Approval Request - {employee_name}', 'Dear {manager_name}, {employee_name} has applied for {leave_type} from {start_date} to {end_date}.'),
    ('Monthly Attendance Summary', 'email', 'Monthly Attendance Summary - {month} {year}', 'Dear {employee_name}, here is your attendance summary for {month} {year}: Present: {present_days}, Absent: {absent_days}, Leave: {leave_days}'),
    ('Punch Reminder', 'push', 'Punch Reminder', 'Don\\'t forget to mark your attendance!')"
];

foreach ($default_data as $sql) {
    try {
        if ($conn->query($sql)) {
            $success_count++;
            echo "✓ Inserted default data successfully\n";
        } else {
            $error_count++;
            echo "✗ Error: " . $conn->error . "\n";
        }
    } catch (Exception $e) {
        $error_count++;
        echo "✗ Exception: " . $e->getMessage() . "\n";
    }
}

echo "\nDatabase fixes completed!\n";
echo "Successfully executed: $success_count statements\n";
echo "Errors encountered: $error_count statements\n";

// Verify critical tables exist
$critical_tables = [
    'smart_attendance_methods',
    'smart_attendance_logs', 
    'face_recognition_data',
    'leave_types',
    'employee_leave_balances',
    'mobile_devices',
    'notification_templates',
    'audit_trail'
];

echo "\nVerifying critical tables:\n";
foreach ($critical_tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "✓ $table exists\n";
    } else {
        echo "✗ $table missing\n";
    }
}

echo "\nAdvanced features database ready!\n";
?>
