<?php
require_once 'db.php';

echo "Creating essential HRMS tables...\n";

// Create tables in the correct order to avoid foreign key issues
$tables = [
    "CREATE TABLE IF NOT EXISTS `hr_employees` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `employee_id` varchar(20) NOT NULL,
        `first_name` varchar(50) NOT NULL,
        `last_name` varchar(50) NOT NULL,
        `email` varchar(100) NOT NULL,
        `phone` varchar(20) DEFAULT NULL,
        `address` text,
        `date_of_birth` date DEFAULT NULL,
        `date_of_joining` date NOT NULL,
        `department_id` int(11) DEFAULT NULL,
        `designation_id` int(11) DEFAULT NULL,
        `manager_id` int(11) DEFAULT NULL,
        `salary` decimal(10,2) DEFAULT NULL,
        `employment_type` enum('full_time','part_time','contract','intern') DEFAULT 'full_time',
        `status` enum('active','inactive','terminated') DEFAULT 'active',
        `profile_picture` varchar(255) DEFAULT NULL,
        `emergency_contact_name` varchar(100) DEFAULT NULL,
        `emergency_contact_phone` varchar(20) DEFAULT NULL,
        `blood_group` varchar(5) DEFAULT NULL,
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `employee_id` (`employee_id`),
        UNIQUE KEY `email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    "CREATE TABLE IF NOT EXISTS `hr_attendance` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `employee_id` int(11) NOT NULL,
        `date` date NOT NULL,
        `clock_in` time DEFAULT NULL,
        `clock_out` time DEFAULT NULL,
        `break_time` int(11) DEFAULT 0,
        `total_hours` decimal(4,2) DEFAULT NULL,
        `overtime_hours` decimal(4,2) DEFAULT 0.00,
        `status` enum('present','absent','late','half_day','holiday','leave') DEFAULT 'present',
        `notes` text,
        `ip_address` varchar(45) DEFAULT NULL,
        `location` varchar(255) DEFAULT NULL,
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `employee_date` (`employee_id`, `date`),
        KEY `employee_id` (`employee_id`),
        KEY `date` (`date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    "CREATE TABLE IF NOT EXISTS `hr_leave_applications` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `employee_id` int(11) NOT NULL,
        `leave_type_id` int(11) NOT NULL,
        `start_date` date NOT NULL,
        `end_date` date NOT NULL,
        `total_days` int(11) NOT NULL,
        `reason` text NOT NULL,
        `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
        `approved_by` int(11) DEFAULT NULL,
        `approved_at` timestamp NULL DEFAULT NULL,
        `rejection_reason` text,
        `applied_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `employee_id` (`employee_id`),
        KEY `leave_type_id` (`leave_type_id`),
        KEY `approved_by` (`approved_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    "CREATE TABLE IF NOT EXISTS `hr_payroll` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `employee_id` int(11) NOT NULL,
        `month` int(2) NOT NULL,
        `year` int(4) NOT NULL,
        `basic_salary` decimal(10,2) NOT NULL DEFAULT 0.00,
        `gross_salary` decimal(10,2) NOT NULL DEFAULT 0.00,
        `total_earnings` decimal(10,2) NOT NULL DEFAULT 0.00,
        `total_deductions` decimal(10,2) NOT NULL DEFAULT 0.00,
        `net_salary` decimal(10,2) NOT NULL DEFAULT 0.00,
        `working_days` int(3) DEFAULT 30,
        `present_days` int(3) DEFAULT 0,
        `absent_days` int(3) DEFAULT 0,
        `leave_days` int(3) DEFAULT 0,
        `overtime_hours` decimal(5,2) DEFAULT 0.00,
        `overtime_amount` decimal(10,2) DEFAULT 0.00,
        `bonus` decimal(10,2) DEFAULT 0.00,
        `incentive` decimal(10,2) DEFAULT 0.00,
        `status` enum('draft','processed','paid') DEFAULT 'draft',
        `processed_at` timestamp NULL DEFAULT NULL,
        `paid_at` timestamp NULL DEFAULT NULL,
        `notes` text,
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `employee_month_year` (`employee_id`, `month`, `year`),
        KEY `employee_id` (`employee_id`),
        KEY `month_year` (`month`, `year`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

// Insert sample data
$sampleData = [
    "INSERT IGNORE INTO `hr_employees` (`employee_id`, `first_name`, `last_name`, `email`, `phone`, `date_of_joining`, `department_id`, `designation_id`, `salary`, `employment_type`, `status`) VALUES
    ('EMP001', 'John', 'Doe', 'john.doe@company.com', '9876543210', '2023-01-15', 1, 1, 45000.00, 'full_time', 'active'),
    ('EMP002', 'Jane', 'Smith', 'jane.smith@company.com', '9876543211', '2023-02-01', 2, 2, 55000.00, 'full_time', 'active'),
    ('EMP003', 'Mike', 'Johnson', 'mike.johnson@company.com', '9876543212', '2023-03-01', 1, 1, 48000.00, 'full_time', 'active')",
    
    "INSERT IGNORE INTO `hr_attendance` (`employee_id`, `date`, `clock_in`, `clock_out`, `total_hours`, `status`) VALUES
    (1, CURDATE(), '09:00:00', '18:00:00', 8.00, 'present'),
    (2, CURDATE(), '09:15:00', '18:15:00', 8.00, 'present'),
    (3, CURDATE(), '09:30:00', '18:30:00', 8.00, 'late')",
    
    "INSERT IGNORE INTO `hr_leave_applications` (`employee_id`, `leave_type_id`, `start_date`, `end_date`, `total_days`, `reason`, `status`) VALUES
    (1, 1, DATE_ADD(CURDATE(), INTERVAL 7 DAY), DATE_ADD(CURDATE(), INTERVAL 9 DAY), 3, 'Family vacation', 'pending'),
    (2, 2, DATE_ADD(CURDATE(), INTERVAL 3 DAY), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 1, 'Medical appointment', 'approved')"
];

$success = 0;
$failed = 0;

foreach ($tables as $sql) {
    try {
        if ($conn->query($sql)) {
            echo "✓ Created table successfully\n";
            $success++;
        } else {
            echo "✗ Error creating table: " . $conn->error . "\n";
            $failed++;
        }
    } catch (Exception $e) {
        echo "✗ Exception: " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\nInserting sample data...\n";
foreach ($sampleData as $sql) {
    try {
        if ($conn->query($sql)) {
            echo "✓ Inserted sample data\n";
        } else {
            echo "✗ Error inserting data: " . $conn->error . "\n";
        }
    } catch (Exception $e) {
        echo "✗ Exception: " . $e->getMessage() . "\n";
    }
}

echo "\nVerifying tables...\n";
$tables = ['hr_departments', 'hr_employees', 'hr_attendance', 'hr_leave_types', 'hr_leave_applications', 'hr_payroll'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    echo "Table $table: " . ($result && $result->num_rows > 0 ? "✓ EXISTS" : "✗ NOT FOUND") . "\n";
}

echo "\nSummary: $success tables created successfully, $failed failed\n";
?>
