<?php
require_once 'HRMS/includes/hrms_config.php';

echo "Creating maintenance schedule tables..." . PHP_EOL;

// Create maintenance_schedules table
$create_maintenance_schedules = "
CREATE TABLE IF NOT EXISTS maintenance_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    maintenance_type VARCHAR(100) NOT NULL,
    scheduled_date DATE NOT NULL,
    due_date DATE NOT NULL,
    status ENUM('Scheduled', 'In Progress', 'Completed', 'Cancelled', 'Overdue') DEFAULT 'Scheduled',
    priority ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Medium',
    description TEXT,
    assigned_technician VARCHAR(100),
    estimated_duration VARCHAR(50),
    estimated_cost DECIMAL(10,2) DEFAULT 0.00,
    actual_cost DECIMAL(10,2) DEFAULT 0.00,
    completion_notes TEXT,
    parts_used TEXT,
    completed_date DATETIME NULL,
    last_service_date DATE NULL,
    next_service_date DATE NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_asset_id (asset_id),
    INDEX idx_due_date (due_date),
    INDEX idx_status (status),
    INDEX idx_priority (priority)
)
";

// Create maintenance_logs table
$create_maintenance_logs = "
CREATE TABLE IF NOT EXISTS maintenance_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_schedule_id (schedule_id)
)
";

// Execute table creation
if (mysqli_query($conn, $create_maintenance_schedules)) {
    echo "✓ maintenance_schedules table created successfully" . PHP_EOL;
} else {
    echo "✗ Error creating maintenance_schedules table: " . mysqli_error($conn) . PHP_EOL;
}

if (mysqli_query($conn, $create_maintenance_logs)) {
    echo "✓ maintenance_logs table created successfully" . PHP_EOL;
} else {
    echo "✗ Error creating maintenance_logs table: " . mysqli_error($conn) . PHP_EOL;
}

// Add maintenance columns to asset_management table
$add_maintenance_columns = "
ALTER TABLE asset_management 
ADD COLUMN IF NOT EXISTS last_maintenance_date DATE NULL,
ADD COLUMN IF NOT EXISTS next_maintenance_due DATE NULL
";

if (mysqli_query($conn, $add_maintenance_columns)) {
    echo "✓ Added maintenance columns to asset_management table" . PHP_EOL;
} else {
    echo "✗ Error adding maintenance columns: " . mysqli_error($conn) . PHP_EOL;
}

echo "Database setup complete!" . PHP_EOL;
?>
