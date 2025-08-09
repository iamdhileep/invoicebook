<?php
include 'db.php';

echo "Creating employee_schedules table...\n";

$sql = "
CREATE TABLE IF NOT EXISTS employee_schedules (
    id int(11) NOT NULL AUTO_INCREMENT,
    employee_id int(11) NOT NULL,
    day_of_week varchar(20) NOT NULL,
    start_time time NOT NULL,
    end_time time NOT NULL,
    break_duration int(11) DEFAULT 0,
    is_active tinyint(1) DEFAULT 1,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY employee_id (employee_id),
    KEY day_of_week (day_of_week),
    UNIQUE KEY unique_employee_day (employee_id, day_of_week)
)";

if (mysqli_query($conn, $sql)) {
    echo "✓ employee_schedules table created successfully\n";
} else {
    echo "✗ Error creating table: " . mysqli_error($conn) . "\n";
}

// Check if employees have data
$employees_check = mysqli_query($conn, "SELECT id, name FROM employees LIMIT 5");
if ($employees_check) {
    echo "\nExisting employees:\n";
    while ($emp = mysqli_fetch_assoc($employees_check)) {
        echo "- {$emp['id']}: {$emp['name']}\n";
    }
} else {
    echo "\nError checking employees: " . mysqli_error($conn) . "\n";
}

// Verify table creation
$verify = mysqli_query($conn, "DESCRIBE employee_schedules");
if ($verify) {
    echo "\nTable structure verified:\n";
    while ($col = mysqli_fetch_assoc($verify)) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
}

echo "\nDone!\n";
?>
