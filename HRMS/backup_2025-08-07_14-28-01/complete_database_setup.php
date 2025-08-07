<?php
/**
 * Complete HRMS Database Setup
 * Creates all necessary tables and sample data
 */

require_once '../db.php';

echo "<h1>HRMS Database Complete Setup</h1>";

$errors = [];
$successes = [];

// List of tables to create
$tables = [
    'hr_departments' => "
        CREATE TABLE IF NOT EXISTS hr_departments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            department_name VARCHAR(100) NOT NULL,
            description TEXT,
            manager_id INT,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ",
    
    'hr_employees' => "
        CREATE TABLE IF NOT EXISTS hr_employees (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id VARCHAR(50) UNIQUE NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            phone VARCHAR(20),
            position VARCHAR(100),
            department_id INT,
            manager_id INT,
            hire_date DATE,
            salary DECIMAL(12,2),
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_employee_id (employee_id),
            INDEX idx_status (status),
            INDEX idx_department (department_id),
            FOREIGN KEY (department_id) REFERENCES hr_departments(id) ON DELETE SET NULL,
            FOREIGN KEY (manager_id) REFERENCES hr_employees(id) ON DELETE SET NULL
        ) ENGINE=InnoDB
    ",
    
    'hr_leave_types' => "
        CREATE TABLE IF NOT EXISTS hr_leave_types (
            id INT AUTO_INCREMENT PRIMARY KEY,
            leave_type_name VARCHAR(100) NOT NULL,
            description TEXT,
            max_days_per_year INT DEFAULT 0,
            carry_forward BOOLEAN DEFAULT FALSE,
            requires_approval BOOLEAN DEFAULT TRUE,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ",
    
    'hr_leave_applications' => "
        CREATE TABLE IF NOT EXISTS hr_leave_applications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            leave_type_id INT NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            days_requested INT NOT NULL,
            reason TEXT,
            status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
            approved_by INT NULL,
            approved_at TIMESTAMP NULL,
            comments TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_employee (employee_id),
            INDEX idx_status (status),
            INDEX idx_dates (start_date, end_date),
            FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
            FOREIGN KEY (leave_type_id) REFERENCES hr_leave_types(id) ON DELETE CASCADE,
            FOREIGN KEY (approved_by) REFERENCES hr_employees(id) ON DELETE SET NULL
        ) ENGINE=InnoDB
    ",
    
    'hr_leave_balances' => "
        CREATE TABLE IF NOT EXISTS hr_leave_balances (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            leave_type_id INT NOT NULL,
            year YEAR NOT NULL,
            allocated_days INT DEFAULT 0,
            used_days INT DEFAULT 0,
            remaining_days INT DEFAULT 0,
            carried_forward INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_employee_leave_year (employee_id, leave_type_id, year),
            FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
            FOREIGN KEY (leave_type_id) REFERENCES hr_leave_types(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ",
    
    'hr_attendance' => "
        CREATE TABLE IF NOT EXISTS hr_attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            attendance_date DATE NOT NULL,
            clock_in_time TIME,
            clock_out_time TIME,
            total_hours DECIMAL(4,2) DEFAULT 0,
            break_time_minutes INT DEFAULT 0,
            overtime_hours DECIMAL(4,2) DEFAULT 0,
            status ENUM('present', 'absent', 'late', 'half_day', 'holiday', 'weekend') DEFAULT 'present',
            location VARCHAR(255),
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_employee_date (employee_id, attendance_date),
            INDEX idx_date (attendance_date),
            INDEX idx_status (status),
            FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ",
    
    'hr_payroll' => "
        CREATE TABLE IF NOT EXISTS hr_payroll (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            pay_period_start DATE NOT NULL,
            pay_period_end DATE NOT NULL,
            basic_salary DECIMAL(12,2) NOT NULL,
            allowances DECIMAL(12,2) DEFAULT 0,
            overtime_amount DECIMAL(12,2) DEFAULT 0,
            deductions DECIMAL(12,2) DEFAULT 0,
            tax_amount DECIMAL(12,2) DEFAULT 0,
            net_salary DECIMAL(12,2) NOT NULL,
            status ENUM('draft', 'processed', 'paid') DEFAULT 'draft',
            processed_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_employee (employee_id),
            INDEX idx_period (pay_period_start, pay_period_end),
            INDEX idx_status (status),
            FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ",
    
    'hr_performance_reviews' => "
        CREATE TABLE IF NOT EXISTS hr_performance_reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            reviewer_id INT NOT NULL,
            review_period_start DATE NOT NULL,
            review_period_end DATE NOT NULL,
            overall_rating DECIMAL(3,2) CHECK (overall_rating >= 1 AND overall_rating <= 5),
            goals_achievement TEXT,
            technical_skills TEXT,
            communication_skills TEXT,
            teamwork TEXT,
            leadership TEXT,
            areas_of_improvement TEXT,
            goals_next_period TEXT,
            comments TEXT,
            status ENUM('draft', 'submitted', 'approved') DEFAULT 'draft',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_employee (employee_id),
            INDEX idx_period (review_period_start, review_period_end),
            FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
            FOREIGN KEY (reviewer_id) REFERENCES hr_employees(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    "
];

// Create tables
echo "<h2>Creating Tables...</h2>";
foreach ($tables as $tableName => $sql) {
    if ($conn->query($sql)) {
        $successes[] = "✅ Created/verified table: $tableName";
    } else {
        $errors[] = "❌ Error creating $tableName: " . $conn->error;
    }
}

// Insert sample data
echo "<h2>Inserting Sample Data...</h2>";

// Check if departments exist
$result = $conn->query("SELECT COUNT(*) as count FROM hr_departments");
$deptCount = $result->fetch_assoc()['count'];

if ($deptCount == 0) {
    $departments = [
        "INSERT INTO hr_departments (department_name, description) VALUES ('Human Resources', 'HR Department managing personnel')",
        "INSERT INTO hr_departments (department_name, description) VALUES ('Information Technology', 'IT Department managing technology')",
        "INSERT INTO hr_departments (department_name, description) VALUES ('Sales & Marketing', 'Sales and Marketing Department')",
        "INSERT INTO hr_departments (department_name, description) VALUES ('Finance & Accounting', 'Finance and Accounting Department')",
        "INSERT INTO hr_departments (department_name, description) VALUES ('Operations', 'Operations Department')"
    ];
    
    foreach ($departments as $sql) {
        if ($conn->query($sql)) {
            $successes[] = "✅ Added sample department";
        } else {
            $errors[] = "❌ Error adding department: " . $conn->error;
        }
    }
}

// Check if employees exist
$result = $conn->query("SELECT COUNT(*) as count FROM hr_employees");
$empCount = $result->fetch_assoc()['count'];

if ($empCount == 0) {
    $employees = [
        "INSERT INTO hr_employees (employee_id, first_name, last_name, email, position, department_id, hire_date, salary) VALUES ('EMP001', 'John', 'Doe', 'john.doe@company.com', 'Software Engineer', 2, '2024-01-15', 75000)",
        "INSERT INTO hr_employees (employee_id, first_name, last_name, email, position, department_id, hire_date, salary) VALUES ('EMP002', 'Jane', 'Smith', 'jane.smith@company.com', 'HR Manager', 1, '2023-06-20', 85000)",
        "INSERT INTO hr_employees (employee_id, first_name, last_name, email, position, department_id, hire_date, salary) VALUES ('EMP003', 'Mike', 'Johnson', 'mike.johnson@company.com', 'Sales Representative', 3, '2024-03-10', 65000)",
        "INSERT INTO hr_employees (employee_id, first_name, last_name, email, position, department_id, hire_date, salary) VALUES ('EMP004', 'Sarah', 'Wilson', 'sarah.wilson@company.com', 'Accountant', 4, '2024-02-01', 70000)",
        "INSERT INTO hr_employees (employee_id, first_name, last_name, email, position, department_id, hire_date, salary) VALUES ('EMP005', 'David', 'Brown', 'david.brown@company.com', 'Operations Manager', 5, '2023-09-15', 90000)"
    ];
    
    foreach ($employees as $sql) {
        if ($conn->query($sql)) {
            $successes[] = "✅ Added sample employee";
        } else {
            $errors[] = "❌ Error adding employee: " . $conn->error;
        }
    }
}

// Add leave types
$result = $conn->query("SELECT COUNT(*) as count FROM hr_leave_types");
$leaveTypeCount = $result->fetch_assoc()['count'];

if ($leaveTypeCount == 0) {
    $leaveTypes = [
        "INSERT INTO hr_leave_types (leave_type_name, description, max_days_per_year) VALUES ('Annual Leave', 'Annual vacation leave', 25)",
        "INSERT INTO hr_leave_types (leave_type_name, description, max_days_per_year) VALUES ('Sick Leave', 'Medical leave', 10)",
        "INSERT INTO hr_leave_types (leave_type_name, description, max_days_per_year) VALUES ('Personal Leave', 'Personal time off', 5)",
        "INSERT INTO hr_leave_types (leave_type_name, description, max_days_per_year) VALUES ('Maternity Leave', 'Maternity leave', 90)",
        "INSERT INTO hr_leave_types (leave_type_name, description, max_days_per_year) VALUES ('Emergency Leave', 'Emergency situations', 3)"
    ];
    
    foreach ($leaveTypes as $sql) {
        if ($conn->query($sql)) {
            $successes[] = "✅ Added leave type";
        } else {
            $errors[] = "❌ Error adding leave type: " . $conn->error;
        }
    }
}

// Initialize leave balances for current year
$currentYear = date('Y');
$conn->query("
    INSERT IGNORE INTO hr_leave_balances (employee_id, leave_type_id, year, allocated_days, remaining_days)
    SELECT e.id, lt.id, $currentYear, lt.max_days_per_year, lt.max_days_per_year
    FROM hr_employees e
    CROSS JOIN hr_leave_types lt
    WHERE e.status = 'active' AND lt.status = 'active'
");

if ($conn->affected_rows > 0) {
    $successes[] = "✅ Initialized leave balances for $currentYear";
}

echo "<h2>Results:</h2>";
foreach ($successes as $success) {
    echo "<p style='color: green;'>$success</p>";
}

foreach ($errors as $error) {
    echo "<p style='color: red;'>$error</p>";
}

echo "<hr>";
echo "<h3>Database Status:</h3>";
$tables_to_check = ['hr_departments', 'hr_employees', 'hr_leave_types', 'hr_leave_applications', 'hr_leave_balances', 'hr_attendance', 'hr_payroll', 'hr_performance_reviews'];

foreach ($tables_to_check as $table) {
    $result = $conn->query("SELECT COUNT(*) as count FROM $table");
    if ($result) {
        $count = $result->fetch_assoc()['count'];
        echo "<p>📊 <strong>$table:</strong> $count records</p>";
    }
}

echo "<hr>";
echo "<p><a href='employee_directory.php' class='btn'>Test Employee Directory</a> | <a href='leave_management.php'>Test Leave Management</a> | <a href='attendance_management.php'>Test Attendance</a></p>";

echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.btn { background: #007bff; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; margin-right: 10px; display: inline-block; }
.btn:hover { background: #0056b3; }
</style>";

require_once '../layouts/footer.php';
?>