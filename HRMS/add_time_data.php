<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

include '../db.php';

echo "<h2>Adding Sample Time Tracking Data</h2>";

// First ensure tables exist
$createTimeTrackingTable = "
CREATE TABLE IF NOT EXISTS hr_time_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    project_name VARCHAR(255) NOT NULL,
    task_description TEXT,
    date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NULL,
    total_hours DECIMAL(4,2) DEFAULT 0,
    status ENUM('in_progress', 'paused', 'completed') DEFAULT 'in_progress',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_employee_date (employee_id, date),
    INDEX idx_project (project_name),
    INDEX idx_status (status),
    FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE
) ENGINE=InnoDB";

$createProjectsTable = "
CREATE TABLE IF NOT EXISTS hr_projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_name VARCHAR(255) NOT NULL UNIQUE,
    project_description TEXT,
    client_name VARCHAR(255),
    start_date DATE,
    end_date DATE,
    status ENUM('active', 'completed', 'on_hold', 'cancelled') DEFAULT 'active',
    budget DECIMAL(12,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB";

if (mysqli_query($conn, $createTimeTrackingTable)) {
    echo "✓ Time tracking table created/verified<br>";
} else {
    echo "✗ Error creating time tracking table: " . mysqli_error($conn) . "<br>";
}

if (mysqli_query($conn, $createProjectsTable)) {
    echo "✓ Projects table created/verified<br>";
} else {
    echo "✗ Error creating projects table: " . mysqli_error($conn) . "<br>";
}

// Add sample projects
$projectsSql = "INSERT IGNORE INTO hr_projects (project_name, project_description, client_name, start_date, budget, status) VALUES 
('Website Development', 'Complete website redesign and development', 'TechCorp Ltd', '2025-01-01', 150000.00, 'active'),
('Mobile App Development', 'Native mobile application for iOS and Android', 'StartupXYZ', '2025-01-15', 200000.00, 'active'),
('Database Migration', 'Migration of legacy database to new system', 'Enterprise Solutions', '2025-02-01', 75000.00, 'active'),
('API Integration', 'Third-party API integration and testing', 'Digital Agency', '2025-01-20', 50000.00, 'active'),
('HR Management System', 'Internal HRMS development and implementation', 'Internal Project', '2025-01-01', 120000.00, 'active')";

if (mysqli_query($conn, $projectsSql)) {
    echo "✓ Sample projects added<br>";
} else {
    echo "✗ Error adding projects: " . mysqli_error($conn) . "<br>";
}

// Get existing employee IDs
$employees = mysqli_query($conn, "SELECT id FROM hr_employees WHERE status = 'active' LIMIT 5");
$employee_ids = [];
if ($employees) {
    while ($emp = mysqli_fetch_assoc($employees)) {
        $employee_ids[] = $emp['id'];
    }
}

if (empty($employee_ids)) {
    echo "⚠️ No active employees found. Please add employees first.<br>";
    exit;
}

// Add sample time tracking data
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$twoDaysAgo = date('Y-m-d', strtotime('-2 days'));

$sample_entries = [
    [$employee_ids[0] ?? 1, 'Website Development', 'Frontend components development', $today, '09:00:00', '12:30:00', 3.5, 'completed', 'Completed header and navigation components'],
    [$employee_ids[1] ?? 1, 'Mobile App Development', 'User authentication module', $today, '10:00:00', '16:00:00', 6.0, 'completed', 'Implemented login and registration'],
    [$employee_ids[0] ?? 1, 'API Integration', 'Payment gateway integration', $yesterday, '09:30:00', '17:30:00', 8.0, 'completed', 'Successfully integrated payment APIs'],
    [$employee_ids[2] ?? 1, 'Database Migration', 'Data mapping and validation', $yesterday, '08:00:00', '16:00:00', 8.0, 'completed', 'Completed customer data migration'],
    [$employee_ids[1] ?? 1, 'HR Management System', 'Employee profile pages', $twoDaysAgo, '09:15:00', '18:15:00', 9.0, 'completed', 'Built employee CRUD operations'],
    [$employee_ids[0] ?? 1, 'Website Development', 'Backend API development', $today, '14:00:00', NULL, 0, 'in_progress', NULL], // Active session
];

$timeTrackingSql = "INSERT IGNORE INTO hr_time_tracking (employee_id, project_name, task_description, date, start_time, end_time, total_hours, status, notes) VALUES ";
$values = [];

foreach ($sample_entries as $entry) {
    $employee_id = $entry[0];
    $project_name = mysqli_real_escape_string($conn, $entry[1]);
    $task_description = mysqli_real_escape_string($conn, $entry[2]);
    $date = $entry[3];
    $start_time = $entry[4];
    $end_time = $entry[5] ? "'" . $entry[5] . "'" : 'NULL';
    $total_hours = $entry[6];
    $status = $entry[7];
    $notes = $entry[8] ? "'" . mysqli_real_escape_string($conn, $entry[8]) . "'" : 'NULL';
    
    $values[] = "($employee_id, '$project_name', '$task_description', '$date', '$start_time', $end_time, $total_hours, '$status', $notes)";
}

$timeTrackingSql .= implode(', ', $values);

if (mysqli_query($conn, $timeTrackingSql)) {
    echo "✓ Sample time tracking data added<br>";
    
    // Count total entries
    $count = mysqli_query($conn, "SELECT COUNT(*) as total FROM hr_time_tracking");
    $total = $count ? mysqli_fetch_assoc($count)['total'] : 0;
    echo "Total time entries: $total<br>";
    
} else {
    echo "✗ Error adding time tracking data: " . mysqli_error($conn) . "<br>";
}

echo "<br><strong>Sample data setup complete!</strong><br>";
echo "<a href='time_tracking.php' class='btn btn-primary'>View Time Tracking Page</a>";
?>
