<?php
/**
 * HRMS Database Setup Script
 * Creates all necessary tables for the HRMS system
 */

if (!isset($root_path)) 
require_once '../db.php';

echo "Setting up HRMS Database Tables...\n";

// 1. Ensure employees table has all necessary columns
$updateEmployeesTable = "
ALTER TABLE employees 
ADD COLUMN IF NOT EXISTS employee_code VARCHAR(50) UNIQUE,
ADD COLUMN IF NOT EXISTS date_of_birth DATE,
ADD COLUMN IF NOT EXISTS joining_date DATE DEFAULT CURRENT_DATE,
ADD COLUMN IF NOT EXISTS manager_id INT,
ADD COLUMN IF NOT EXISTS department_id INT,
ADD COLUMN IF NOT EXISTS monthly_salary DECIMAL(10,2) DEFAULT 0,
ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive', 'terminated') DEFAULT 'active',
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
";
mysqli_query($conn, $updateEmployeesTable);

// 2. Create tickets table for employee helpdesk
$createTicketsTable = "
CREATE TABLE IF NOT EXISTS tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category ENUM('IT', 'HR', 'Facilities', 'Payroll', 'Other') DEFAULT 'Other',
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    status ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
    assigned_to INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
    FOREIGN KEY (assigned_to) REFERENCES employees(employee_id)
)";
mysqli_query($conn, $createTicketsTable);

// 3. Create surveys table
$createSurveysTable = "
CREATE TABLE IF NOT EXISTS surveys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('draft', 'active', 'completed', 'cancelled') DEFAULT 'draft',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES employees(employee_id)
)";
mysqli_query($conn, $createSurveysTable);

// 4. Create asset_allocations table
$createAssetAllocationsTable = "
CREATE TABLE IF NOT EXISTS asset_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_name VARCHAR(255) NOT NULL,
    asset_type ENUM('laptop', 'desktop', 'phone', 'tablet', 'accessories', 'other') DEFAULT 'other',
    serial_number VARCHAR(100),
    employee_id INT NOT NULL,
    allocated_date DATE NOT NULL,
    return_date DATE,
    status ENUM('allocated', 'returned', 'damaged', 'lost') DEFAULT 'allocated',
    condition_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
)";
mysqli_query($conn, $createAssetAllocationsTable);

// 5. Create attendance table (if not exists)
$createAttendanceTable = "
CREATE TABLE IF NOT EXISTS hr_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    check_in_time TIME,
    check_out_time TIME,
    status ENUM('Present', 'Absent', 'Late', 'Half Day', 'Leave') DEFAULT 'Present',
    working_hours DECIMAL(4,2) DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_employee_date (employee_id, attendance_date),
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
)";
mysqli_query($conn, $createAttendanceTable);

// 6. Create leave_requests table (if not exists)
$createLeaveRequestsTable = "
CREATE TABLE IF NOT EXISTS leave_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    leave_type ENUM('casual', 'sick', 'earned', 'comp_off', 'maternity', 'paternity', 'emergency') DEFAULT 'casual',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days_requested DECIMAL(3,1) NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    approved_by INT,
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
    FOREIGN KEY (approved_by) REFERENCES employees(employee_id)
)";
mysqli_query($conn, $createLeaveRequestsTable);

// 7. Create leave_balance table
$createLeaveBalanceTable = "
CREATE TABLE IF NOT EXISTS leave_balance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    leave_type ENUM('casual', 'sick', 'earned', 'comp_off', 'maternity', 'paternity', 'emergency') DEFAULT 'casual',
    total_days DECIMAL(4,1) NOT NULL,
    used_days DECIMAL(4,1) DEFAULT 0,
    available_days DECIMAL(4,1) GENERATED ALWAYS AS (total_days - used_days) STORED,
    year INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_employee_leave_year (employee_id, leave_type, year),
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
)";
mysqli_query($conn, $createLeaveBalanceTable);

// Insert some sample data if tables are empty
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM hr_employees");
$row = mysqli_fetch_assoc($result);

if ($row['count'] == 0) {
    echo "Inserting sample data...\n";
    
    // Insert sample departments
    $insertDepartments = "
    INSERT IGNORE INTO hr_departments (department_name, description, budget) VALUES
    ('Human Resources', 'Managing talent acquisition, employee relations, and organizational culture', 850000),
    ('Engineering', 'Product development, software engineering, and technical innovation', 4500000),
    ('Sales & Marketing', 'Revenue generation, market expansion, and customer acquisition', 2500000),
    ('Finance', 'Financial planning, accounting, budgeting, and compliance', 1200000),
    ('Operations', 'Daily operations, logistics, supply chain, and process optimization', 1800000),
    ('Customer Support', 'Customer service, technical support, and client relationship management', 1400000)
    ";
    mysqli_query($conn, $insertDepartments);
    
    // Insert sample employees
    $insertEmployees = "
    INSERT IGNORE INTO hr_employees (name, employee_code, position, department_id, email, phone, monthly_salary, joining_date, date_of_birth) VALUES
    ('John Smith', 'EMP001', 'HR Manager', 1, 'john.smith@company.com', '9876543210', 75000, '2022-01-15', '1985-03-20'),
    ('Sarah Johnson', 'EMP002', 'Senior Developer', 2, 'sarah.johnson@company.com', '9876543211', 95000, '2021-06-10', '1988-07-12'),
    ('Mike Wilson', 'EMP003', 'Sales Executive', 3, 'mike.wilson@company.com', '9876543212', 55000, '2023-02-28', '1990-11-05'),
    ('Emily Davis', 'EMP004', 'Financial Analyst', 4, 'emily.davis@company.com', '9876543213', 65000, '2022-08-22', '1987-09-18'),
    ('David Brown', 'EMP005', 'Operations Manager', 5, 'david.brown@company.com', '9876543214', 80000, '2021-11-30', '1983-12-08'),
    ('Lisa Garcia', 'EMP006', 'Support Specialist', 6, 'lisa.garcia@company.com', '9876543215', 45000, '2023-05-15', '1992-04-25')
    ";
    mysqli_query($conn, $insertEmployees);
    
    echo "Sample data inserted successfully!\n";
}

echo "HRMS Database setup completed successfully!\n";
echo "All tables created and connected to the main database.\n";

// Display summary
$tables = ['employees', 'departments', 'attendance', 'leave_requests', 'leave_balance', 'tickets', 'surveys', 'asset_allocations'];
echo "\nTable Summary:\n";
foreach ($tables as $table) {
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM $table");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        echo "- $table: {$row['count']} records\n";
    }
}

require_once '../layouts/footer.php';
?>