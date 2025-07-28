<?php
include 'db.php';

// Create salaries table
$query1 = "CREATE TABLE IF NOT EXISTS salaries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    basic_salary DECIMAL(10,2) DEFAULT 0,
    allowances DECIMAL(10,2) DEFAULT 0,
    deductions DECIMAL(10,2) DEFAULT 0,
    total_salary DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_employee_id (employee_id)
)";

// Create payslips table
$query2 = "CREATE TABLE IF NOT EXISTS payslips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    month VARCHAR(7) NOT NULL,
    gross_salary DECIMAL(10,2) DEFAULT 0,
    total_deductions DECIMAL(10,2) DEFAULT 0,
    net_salary DECIMAL(10,2) DEFAULT 0,
    payslip_data TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_employee_month (employee_id, month),
    INDEX idx_month (month),
    INDEX idx_employee_id (employee_id)
)";

// Ensure attendance table has proper structure
$query3 = "CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    date DATE NOT NULL,
    check_in_time TIME NULL,
    check_out_time TIME NULL,
    status ENUM('Present', 'Absent', 'Late', 'Half Day') DEFAULT 'Absent',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_employee_date (employee_id, date),
    INDEX idx_date (date),
    INDEX idx_employee_id (employee_id)
)";

// Ensure employees table exists
$query4 = "CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    employee_id VARCHAR(50) UNIQUE,
    email VARCHAR(255),
    phone VARCHAR(20),
    department VARCHAR(100),
    position VARCHAR(100),
    salary DECIMAL(10,2) DEFAULT 0,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

$result1 = $conn->query($query1);
$result2 = $conn->query($query2);
$result3 = $conn->query($query3);
$result4 = $conn->query($query4);

if ($result1 && $result2 && $result3 && $result4) {
    echo "Database tables created successfully!\n";
    
    // Add GPS and IP columns to attendance table if they don't exist
    $alterQueries = [
        "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS gps_latitude DECIMAL(10, 8) NULL",
        "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS gps_longitude DECIMAL(11, 8) NULL",
        "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45) NULL"
    ];
    
    foreach ($alterQueries as $alterQuery) {
        $result = $conn->query($alterQuery);
        if (!$result) {
            // Column might already exist, continue
            echo "Note: " . $conn->error . "\n";
        }
    }
    echo "Attendance table structure updated for smart attendance features!\n";
    
    // Insert sample data if employees table is empty
    $checkEmployees = $conn->query("SELECT COUNT(*) as count FROM employees");
    $empCount = $checkEmployees->fetch_assoc()['count'];
    
    if ($empCount == 0) {
        echo "Adding sample employees...\n";
        $sampleEmployees = [
            ['John Doe', 'EMP001', 'john@company.com', '9876543210', 'IT', 'Developer', 45000],
            ['Jane Smith', 'EMP002', 'jane@company.com', '9876543211', 'HR', 'Manager', 55000],
            ['Mike Johnson', 'EMP003', 'mike@company.com', '9876543212', 'Finance', 'Accountant', 40000],
            ['Sarah Wilson', 'EMP004', 'sarah@company.com', '9876543213', 'Marketing', 'Executive', 35000],
            ['David Brown', 'EMP005', 'david@company.com', '9876543214', 'IT', 'Senior Developer', 60000]
        ];
        
        foreach ($sampleEmployees as $emp) {
            $insertQuery = "INSERT INTO employees (name, employee_id, email, phone, department, position, salary) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param('ssssssd', $emp[0], $emp[1], $emp[2], $emp[3], $emp[4], $emp[5], $emp[6]);
            $stmt->execute();
        }
        echo "Sample employees added successfully!\n";
    }
    
} else {
    echo "Error creating tables: " . $conn->error . "\n";
}

$conn->close();
?>
