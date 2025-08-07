<?php
/**
 * HRMS Database Schema Validator & Auto-Setup
 * Ensures all required database tables and columns exist
 */

require_once '../auth_check.php';
require_once '../db.php';

class DatabaseSchemaValidator {
    private $connection;
    private $results = [];
    
    public function __construct() {
        // Get database connection from config
        global $conn;
        if (isset($conn)) {
            $this->connection = $conn;
        } else {
            // Fallback connection
            require_once '../config.php';
            $this->connection = $conn;
        }
    }
    
    public function validateAndCreateSchema() {
        echo "🔍 HRMS Database Schema Validation Started\n";
        echo str_repeat("=", 60) . "\n\n";
        
        $this->checkEmployeesTable();
        $this->checkDepartmentsTable();
        $this->checkAttendanceTable();
        $this->checkLeaveApplicationsTable();
        $this->checkLeaveTypesTable();
        $this->checkPerformanceTable();
        $this->checkPayrollTable();
        $this->createIndexes();
        
        $this->printSummary();
        return $this->results;
    }
    
    private function checkEmployeesTable() {
        echo "📋 Checking hr_employees table...\n";
        
        $createTable = "
        CREATE TABLE IF NOT EXISTS hr_employees (
            id INT PRIMARY KEY AUTO_INCREMENT,
            employee_id VARCHAR(20) UNIQUE NOT NULL,
            user_id INT,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            phone VARCHAR(20),
            department_id INT,
            position VARCHAR(100),
            hire_date DATE,
            salary DECIMAL(10,2),
            manager_id INT,
            is_active BOOLEAN DEFAULT TRUE,
            profile_image VARCHAR(255),
            address TEXT,
            emergency_contact_name VARCHAR(100),
            emergency_contact_phone VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_employee_id (employee_id),
            INDEX idx_department_id (department_id),
            INDEX idx_manager_id (manager_id),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($this->connection->query($createTable)) {
            echo "✅ hr_employees table verified/created\n";
            $this->results['employees_table'] = 'OK';
        } else {
            echo "❌ Error with hr_employees table: " . $this->connection->error . "\n";
            $this->results['employees_table'] = 'ERROR';
        }
        
        // Add sample data if empty
        $result = $this->connection->query("SELECT COUNT(*) as count FROM hr_employees");
        $row = $result->fetch_assoc();
        
        if ($row['count'] == 0) {
            echo "📝 Adding sample employee data...\n";
            $sampleEmployees = [
                ['EMP001', 1, 'John', 'Doe', 'john.doe@company.com', '+1234567890', 1, 'Software Engineer', '2024-01-15', 75000.00],
                ['EMP002', 2, 'Jane', 'Smith', 'jane.smith@company.com', '+1234567891', 2, 'HR Manager', '2023-06-01', 85000.00],
                ['EMP003', 3, 'Mike', 'Johnson', 'mike.johnson@company.com', '+1234567892', 1, 'Project Manager', '2023-12-01', 95000.00],
                ['EMP004', 4, 'Sarah', 'Wilson', 'sarah.wilson@company.com', '+1234567893', 3, 'Financial Analyst', '2024-03-01', 70000.00],
                ['EMP005', 5, 'David', 'Brown', 'david.brown@company.com', '+1234567894', 1, 'Senior Developer', '2023-08-15', 90000.00]
            ];
            
            foreach ($sampleEmployees as $emp) {
                $stmt = $this->connection->prepare("INSERT INTO hr_employees (employee_id, user_id, first_name, last_name, email, phone, department_id, position, hire_date, salary) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sissssissd", $emp[0], $emp[1], $emp[2], $emp[3], $emp[4], $emp[5], $emp[6], $emp[7], $emp[8], $emp[9]);
                $stmt->execute();
            }
            echo "✅ Sample employee data added\n";
        }
        echo "\n";
    }
    
    private function checkDepartmentsTable() {
        echo "🏢 Checking hr_departments table...\n";
        
        $createTable = "
        CREATE TABLE IF NOT EXISTS hr_departments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            manager_id INT,
            status ENUM('active', 'inactive') DEFAULT 'active',
            budget DECIMAL(12,2),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_manager_id (manager_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($this->connection->query($createTable)) {
            echo "✅ hr_departments table verified/created\n";
            $this->results['departments_table'] = 'OK';
        } else {
            echo "❌ Error with hr_departments table: " . $this->connection->error . "\n";
            $this->results['departments_table'] = 'ERROR';
        }
        
        // Add sample departments if empty
        $result = $this->connection->query("SELECT COUNT(*) as count FROM hr_departments");
        $row = $result->fetch_assoc();
        
        if ($row['count'] == 0) {
            echo "📝 Adding sample department data...\n";
            $departments = [
                ['Engineering', 'Software development and technical operations', NULL, 500000.00],
                ['Human Resources', 'Employee management and organizational development', NULL, 200000.00],
                ['Finance', 'Financial planning and accounting', NULL, 300000.00],
                ['Marketing', 'Marketing and customer acquisition', NULL, 250000.00],
                ['Operations', 'Business operations and logistics', NULL, 180000.00]
            ];
            
            foreach ($departments as $dept) {
                $stmt = $this->connection->prepare("INSERT INTO hr_departments (name, description, manager_id, budget) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssid", $dept[0], $dept[1], $dept[2], $dept[3]);
                $stmt->execute();
            }
            echo "✅ Sample department data added\n";
        }
        echo "\n";
    }
    
    private function checkAttendanceTable() {
        echo "⏰ Checking hr_attendance table...\n";
        
        $createTable = "
        CREATE TABLE IF NOT EXISTS hr_attendance (
            id INT PRIMARY KEY AUTO_INCREMENT,
            employee_id INT NOT NULL,
            attendance_date DATE NOT NULL,
            clock_in_time TIME,
            clock_out_time TIME,
            break_duration INT DEFAULT 0,
            total_hours DECIMAL(4,2),
            status ENUM('present', 'absent', 'late', 'half_day') DEFAULT 'present',
            notes TEXT,
            ip_address VARCHAR(45),
            location VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_employee_date (employee_id, attendance_date),
            INDEX idx_attendance_date (attendance_date),
            INDEX idx_employee_id (employee_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($this->connection->query($createTable)) {
            echo "✅ hr_attendance table verified/created\n";
            $this->results['attendance_table'] = 'OK';
        } else {
            echo "❌ Error with hr_attendance table: " . $this->connection->error . "\n";
            $this->results['attendance_table'] = 'ERROR';
        }
        echo "\n";
    }
    
    private function checkLeaveApplicationsTable() {
        echo "📋 Checking hr_leave_applications table...\n";
        
        $createTable = "
        CREATE TABLE IF NOT EXISTS hr_leave_applications (
            id INT PRIMARY KEY AUTO_INCREMENT,
            employee_id INT NOT NULL,
            leave_type_id INT NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            days_requested INT NOT NULL,
            reason TEXT,
            status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
            approved_by INT,
            approved_at TIMESTAMP NULL,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            comments TEXT,
            INDEX idx_employee_id (employee_id),
            INDEX idx_status (status),
            INDEX idx_leave_type_id (leave_type_id),
            INDEX idx_start_date (start_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($this->connection->query($createTable)) {
            echo "✅ hr_leave_applications table verified/created\n";
            $this->results['leave_applications_table'] = 'OK';
        } else {
            echo "❌ Error with hr_leave_applications table: " . $this->connection->error . "\n";
            $this->results['leave_applications_table'] = 'ERROR';
        }
        echo "\n";
    }
    
    private function checkLeaveTypesTable() {
        echo "📝 Checking hr_leave_types table...\n";
        
        $createTable = "
        CREATE TABLE IF NOT EXISTS hr_leave_types (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            days_allowed INT DEFAULT 0,
            carry_forward BOOLEAN DEFAULT FALSE,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($this->connection->query($createTable)) {
            echo "✅ hr_leave_types table verified/created\n";
            $this->results['leave_types_table'] = 'OK';
        } else {
            echo "❌ Error with hr_leave_types table: " . $this->connection->error . "\n";
            $this->results['leave_types_table'] = 'ERROR';
        }
        
        // Add default leave types if empty
        $result = $this->connection->query("SELECT COUNT(*) as count FROM hr_leave_types");
        $row = $result->fetch_assoc();
        
        if ($row['count'] == 0) {
            echo "📝 Adding default leave types...\n";
            $leaveTypes = [
                ['Annual Leave', 'Yearly vacation days', 25, TRUE],
                ['Sick Leave', 'Medical leave', 10, FALSE],
                ['Emergency Leave', 'Emergency situations', 5, FALSE],
                ['Maternity Leave', 'Maternity leave', 90, FALSE],
                ['Paternity Leave', 'Paternity leave', 15, FALSE],
                ['Bereavement Leave', 'Family bereavement', 3, FALSE]
            ];
            
            foreach ($leaveTypes as $type) {
                $stmt = $this->connection->prepare("INSERT INTO hr_leave_types (name, description, days_allowed, carry_forward) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssii", $type[0], $type[1], $type[2], $type[3]);
                $stmt->execute();
            }
            echo "✅ Default leave types added\n";
        }
        echo "\n";
    }
    
    private function checkPerformanceTable() {
        echo "📊 Checking hr_performance_reviews table...\n";
        
        $createTable = "
        CREATE TABLE IF NOT EXISTS hr_performance_reviews (
            id INT PRIMARY KEY AUTO_INCREMENT,
            employee_id INT NOT NULL,
            reviewer_id INT NOT NULL,
            review_period_start DATE NOT NULL,
            review_period_end DATE NOT NULL,
            overall_rating DECIMAL(3,2),
            goals_achievement DECIMAL(3,2),
            communication_skills DECIMAL(3,2),
            technical_skills DECIMAL(3,2),
            teamwork DECIMAL(3,2),
            leadership DECIMAL(3,2),
            comments TEXT,
            status ENUM('draft', 'submitted', 'approved') DEFAULT 'draft',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_employee_id (employee_id),
            INDEX idx_reviewer_id (reviewer_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($this->connection->query($createTable)) {
            echo "✅ hr_performance_reviews table verified/created\n";
            $this->results['performance_table'] = 'OK';
        } else {
            echo "❌ Error with hr_performance_reviews table: " . $this->connection->error . "\n";
            $this->results['performance_table'] = 'ERROR';
        }
        echo "\n";
    }
    
    private function checkPayrollTable() {
        echo "💰 Checking hr_payroll table...\n";
        
        $createTable = "
        CREATE TABLE IF NOT EXISTS hr_payroll (
            id INT PRIMARY KEY AUTO_INCREMENT,
            employee_id INT NOT NULL,
            pay_period_start DATE NOT NULL,
            pay_period_end DATE NOT NULL,
            base_salary DECIMAL(10,2),
            overtime_hours DECIMAL(4,2) DEFAULT 0,
            overtime_rate DECIMAL(6,2) DEFAULT 0,
            bonuses DECIMAL(8,2) DEFAULT 0,
            deductions DECIMAL(8,2) DEFAULT 0,
            gross_pay DECIMAL(10,2),
            tax_deductions DECIMAL(8,2),
            net_pay DECIMAL(10,2),
            status ENUM('draft', 'processed', 'paid') DEFAULT 'draft',
            processed_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_employee_id (employee_id),
            INDEX idx_pay_period (pay_period_start, pay_period_end),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($this->connection->query($createTable)) {
            echo "✅ hr_payroll table verified/created\n";
            $this->results['payroll_table'] = 'OK';
        } else {
            echo "❌ Error with hr_payroll table: " . $this->connection->error . "\n";
            $this->results['payroll_table'] = 'ERROR';
        }
        echo "\n";
    }
    
    private function createIndexes() {
        echo "🔍 Creating performance indexes...\n";
        
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_employees_email ON hr_employees(email)",
            "CREATE INDEX IF NOT EXISTS idx_employees_active ON hr_employees(is_active)",
            "CREATE INDEX IF NOT EXISTS idx_attendance_date_employee ON hr_attendance(attendance_date, employee_id)",
            "CREATE INDEX IF NOT EXISTS idx_leave_apps_dates ON hr_leave_applications(start_date, end_date)",
            "CREATE INDEX IF NOT EXISTS idx_performance_period ON hr_performance_reviews(review_period_start, review_period_end)"
        ];
        
        foreach ($indexes as $index) {
            if ($this->connection->query($index)) {
                echo "✅ Index created successfully\n";
            }
        }
        echo "\n";
    }
    
    private function printSummary() {
        echo str_repeat("=", 60) . "\n";
        echo "📋 VALIDATION SUMMARY\n";
        echo str_repeat("=", 60) . "\n";
        
        $totalTests = count($this->results);
        $passedTests = count(array_filter($this->results, function($result) {
            return $result === 'OK';
        }));
        
        foreach ($this->results as $test => $result) {
            $icon = $result === 'OK' ? '✅' : '❌';
            echo sprintf("%-30s %s %s\n", $test, $icon, $result);
        }
        
        echo "\n";
        echo "📊 Overall Status: {$passedTests}/{$totalTests} tests passed\n";
        
        if ($passedTests === $totalTests) {
            echo "🎉 All database tables are properly configured!\n";
        } else {
            echo "⚠️  Some issues need attention. Check the logs above.\n";
        }
        
        echo str_repeat("=", 60) . "\n";
    }
}

// Run validation if accessed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    header('Content-Type: text/plain; charset=utf-8');
    
    try {
        $validator = new DatabaseSchemaValidator();
        $results = $validator->validateAndCreateSchema();
        
        echo "\n🔗 Database schema validation completed.\n";
        echo "You can now access the HRMS panels:\n";
        echo "- HR Panel: http://localhost/billbook/HRMS/hr_panel.php\n";
        echo "- Employee Panel: http://localhost/billbook/HRMS/employee_panel.php\n";
        echo "- Manager Panel: http://localhost/billbook/HRMS/manager_panel.php\n";
        echo "- Control Center: http://localhost/billbook/HRMS/control_center.php\n";
        
    } catch (Exception $e) {
        echo "❌ Error during validation: " . $e->getMessage() . "\n";
    }
}

require_once '../layouts/footer.php';
?>