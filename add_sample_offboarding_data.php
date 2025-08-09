<?php
require_once 'db.php';

echo "Adding sample data for testing...\n";

// Check if we have employees to work with
$emp_check = $conn->query("SELECT COUNT(*) as count FROM hr_employees WHERE status = 'active'");
$emp_count = $emp_check->fetch_assoc()['count'];

if ($emp_count == 0) {
    echo "No active employees found. Adding sample employees...\n";
    
    $sample_employees = [
        ['John Doe', 'EMP001', 'Information Technology', 75000, 'john.doe@company.com'],
        ['Jane Smith', 'EMP002', 'Human Resources', 65000, 'jane.smith@company.com'],
        ['Mike Johnson', 'EMP003', 'Finance', 70000, 'mike.johnson@company.com']
    ];
    
    $stmt = $conn->prepare("INSERT INTO hr_employees (full_name, employee_code, department_name, salary, email, status, hire_date) VALUES (?, ?, ?, ?, ?, 'active', CURDATE())");
    
    foreach ($sample_employees as $emp) {
        $stmt->bind_param('sssds', $emp[0], $emp[1], $emp[2], $emp[3], $emp[4]);
        if ($stmt->execute()) {
            echo "  ✓ Added employee: {$emp[0]}\n";
        }
    }
    
    echo "Sample employees added successfully!\n";
} else {
    echo "Found {$emp_count} active employees in the system.\n";
}

// Add a sample offboarding record if none exist
$fnf_check = $conn->query("SELECT COUNT(*) as count FROM fnf_settlements");
$fnf_count = $fnf_check->fetch_assoc()['count'];

if ($fnf_count == 0) {
    echo "Adding a sample offboarding record...\n";
    
    $emp_result = $conn->query("SELECT id FROM hr_employees WHERE status = 'active' LIMIT 1");
    if ($emp_result->num_rows > 0) {
        $employee = $emp_result->fetch_assoc();
        $employee_id = $employee['id'];
        
        $resignation_date = date('Y-m-d');
        $last_working_day = date('Y-m-d', strtotime('+30 days'));
        
        $stmt = $conn->prepare("INSERT INTO fnf_settlements (employee_id, resignation_date, last_working_day, notice_period_days, basic_salary, status, remarks, created_by) VALUES (?, ?, ?, 30, 75000, 'initiated', 'Sample offboarding record for testing', 1)");
        $stmt->bind_param('iss', $employee_id, $resignation_date, $last_working_day);
        
        if ($stmt->execute()) {
            $settlement_id = $conn->insert_id;
            echo "  ✓ Sample offboarding record created with ID: {$settlement_id}\n";
            
            // Add sample clearance steps
            $steps = [
                'Return laptop and IT equipment',
                'Clear personal belongings',
                'Complete knowledge transfer',
                'Return access cards',
                'HR documentation',
                'Final settlement approval'
            ];
            
            $step_stmt = $conn->prepare("INSERT INTO clearance_steps (settlement_id, step_description, responsible_department) VALUES (?, ?, 'HR')");
            foreach ($steps as $step) {
                $step_stmt->bind_param('is', $settlement_id, $step);
                $step_stmt->execute();
            }
            
            echo "  ✓ Added sample clearance steps\n";
        }
    }
}

echo "\nSample data setup completed! The offboarding system is ready for testing.\n";
echo "Visit: http://localhost/billbook/HRMS/offboarding_process.php\n";
?>
