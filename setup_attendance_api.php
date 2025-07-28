<?php
include 'db.php';

// Create attendance_logs table
$createLogsTable = "
CREATE TABLE IF NOT EXISTS attendance_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    INDEX idx_employee_id (employee_id),
    INDEX idx_timestamp (timestamp),
    INDEX idx_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    if ($conn->query($createLogsTable)) {
        echo "✅ Attendance logs table created successfully\n";
    } else {
        echo "❌ Error creating attendance logs table: " . $conn->error . "\n";
    }
    
    // Add additional columns to attendance table if not exists
    $alterAttendanceTable = [
        "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS punch_method VARCHAR(50) DEFAULT 'manual'",
        "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS location VARCHAR(255)",
        "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS device_id VARCHAR(50)",
        "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS punch_out_method VARCHAR(50)",
        "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS out_location VARCHAR(255)",
        "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS out_device_id VARCHAR(50)"
    ];
    
    foreach ($alterAttendanceTable as $sql) {
        if ($conn->query($sql)) {
            echo "✅ Added attendance table column\n";
        } else {
            // Ignore errors for columns that already exist
            if (strpos($conn->error, 'Duplicate column name') === false) {
                echo "⚠️ Warning: " . $conn->error . "\n";
            }
        }
    }
    
    echo "✅ Database setup completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

$conn->close();
?>
