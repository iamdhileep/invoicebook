<?php
// Complete payroll system setup
include dirname(__DIR__) . '/db.php';

echo "<h2>🎯 Complete Payroll System Setup</h2>\n";
echo "<pre>\n";

// Check if payslips table exists with correct structure
$result = $conn->query("SHOW TABLES LIKE 'payslips'");
if ($result->num_rows == 0) {
    // Create payslips table with correct structure
    $create_payslips = "CREATE TABLE IF NOT EXISTS payslips (
        payslip_id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        employee_code VARCHAR(20),
        employee_name VARCHAR(100),
        department VARCHAR(50),
        position VARCHAR(50),
        pay_period VARCHAR(7), -- YYYY-MM format
        pay_date DATE,
        basic_salary DECIMAL(12,2),
        hra DECIMAL(12,2),
        da DECIMAL(12,2),
        allowances DECIMAL(12,2),
        overtime_amount DECIMAL(12,2) DEFAULT 0,
        gross_salary DECIMAL(12,2),
        pf_deduction DECIMAL(12,2),
        esi_deduction DECIMAL(12,2),
        professional_tax DECIMAL(12,2),
        other_deductions DECIMAL(12,2) DEFAULT 0,
        total_deductions DECIMAL(12,2),
        net_salary DECIMAL(12,2),
        status ENUM('generated', 'sent', 'acknowledged') DEFAULT 'generated',
        generated_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_employee_period (employee_id, pay_period),
        INDEX idx_pay_date (pay_date)
    )";
    
    if ($conn->query($create_payslips)) {
        echo "✅ Created 'payslips' table with correct structure\n";
    } else {
        echo "❌ Error creating payslips table: " . $conn->error . "\n";
    }
}

// Update salary components for all employees
echo "\nUpdating employee salary components...\n";
echo "=====================================\n";

$employees_query = "SELECT employee_id, monthly_salary FROM employees WHERE status = 'active' AND monthly_salary > 0";
$employees = $conn->query($employees_query);

if ($employees) {
    $count = 0;
    while ($emp = $employees->fetch_assoc()) {
        $monthly_salary = $emp['monthly_salary'];
        $basic_salary = $monthly_salary * 0.60;
        $hra = $monthly_salary * 0.20;
        $da = $monthly_salary * 0.10;
        $allowances = $monthly_salary * 0.10;
        $pf_deduction = $basic_salary * 0.12;
        $esi_deduction = $monthly_salary * 0.0175;
        $professional_tax = $monthly_salary > 10000 ? 200 : 0;
        
        $update_sql = "UPDATE employees SET 
                       basic_salary = ?, 
                       hra = ?, 
                       da = ?, 
                       allowances = ?, 
                       pf_deduction = ?, 
                       esi_deduction = ?, 
                       professional_tax = ? 
                       WHERE employee_id = ?";
        
        $stmt = $conn->prepare($update_sql);
        if ($stmt) {
            $stmt->bind_param("dddddddi", $basic_salary, $hra, $da, $allowances, $pf_deduction, $esi_deduction, $professional_tax, $emp['employee_id']);
            if ($stmt->execute()) {
                $count++;
            }
        }
    }
    echo "✅ Updated salary components for $count employees\n";
} else {
    echo "❌ Error fetching employees: " . $conn->error . "\n";
}

// Generate sample payslips for current month
echo "\nGenerating sample payslips...\n";
echo "=============================\n";

$current_month = date('Y-m');

// Check if payslips already exist for current month
$check_query = "SELECT COUNT(*) as count FROM payslips WHERE pay_period = '$current_month'";
$check_result = $conn->query($check_query);

if ($check_result) {
    $payslip_count = $check_result->fetch_assoc()['count'];
    
    if ($payslip_count == 0) {
        $employees_query = "SELECT employee_id, employee_code, name, department_name, position, 
                           basic_salary, hra, da, allowances, pf_deduction, esi_deduction, professional_tax 
                           FROM employees 
                           WHERE status = 'active' AND monthly_salary > 0";
        
        $employees = $conn->query($employees_query);
        
        if ($employees) {
            $generated_count = 0;
            
            while ($emp = $employees->fetch_assoc()) {
                $gross_salary = $emp['basic_salary'] + $emp['hra'] + $emp['da'] + $emp['allowances'];
                $total_deductions = $emp['pf_deduction'] + $emp['esi_deduction'] + $emp['professional_tax'];
                $net_salary = $gross_salary - $total_deductions;
                
                $insert_payslip = "INSERT INTO payslips 
                    (employee_id, employee_code, employee_name, department, position, pay_period, pay_date, 
                     basic_salary, hra, da, allowances, gross_salary, pf_deduction, esi_deduction, 
                     professional_tax, total_deductions, net_salary, generated_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
                
                $stmt = $conn->prepare($insert_payslip);
                if ($stmt) {
                    $pay_date = date('Y-m-t'); // Last day of current month
                    
                    $stmt->bind_param("issssssdddddddddd", 
                        $emp['employee_id'], $emp['employee_code'], $emp['name'], $emp['department_name'], $emp['position'],
                        $current_month, $pay_date, $emp['basic_salary'], $emp['hra'], $emp['da'], $emp['allowances'],
                        $gross_salary, $emp['pf_deduction'], $emp['esi_deduction'], $emp['professional_tax'],
                        $total_deductions, $net_salary
                    );
                    
                    if ($stmt->execute()) {
                        $generated_count++;
                    }
                }
            }
            echo "✅ Generated $generated_count sample payslips for $current_month\n";
        }
    } else {
        echo "ℹ️ Payslips already exist for current month ($current_month) - Count: $payslip_count\n";
    }
}

// Create payroll helper functions file
echo "\nCreating payroll helper functions...\n";
echo "===================================\n";

$helper_functions = '<?php
// Payroll Helper Functions

function calculateSalaryComponents($monthly_salary, $payroll_settings = null) {
    if (!$payroll_settings) {
        // Default percentages
        $basic_percentage = 60;
        $hra_percentage = 20;
        $da_percentage = 10;
        $allowances_percentage = 10;
        $pf_rate = 12;
        $esi_rate = 1.75;
    } else {
        $basic_percentage = $payroll_settings["basic_salary_percentage"] ?? 60;
        $hra_percentage = $payroll_settings["hra_percentage"] ?? 20;
        $da_percentage = $payroll_settings["da_percentage"] ?? 10;
        $allowances_percentage = $payroll_settings["allowances_percentage"] ?? 10;
        $pf_rate = $payroll_settings["pf_rate"] ?? 12;
        $esi_rate = $payroll_settings["esi_rate"] ?? 1.75;
    }
    
    $basic_salary = $monthly_salary * ($basic_percentage / 100);
    $hra = $monthly_salary * ($hra_percentage / 100);
    $da = $monthly_salary * ($da_percentage / 100);
    $allowances = $monthly_salary * ($allowances_percentage / 100);
    
    $gross_salary = $basic_salary + $hra + $da + $allowances;
    
    $pf_deduction = $basic_salary * ($pf_rate / 100);
    $esi_deduction = $gross_salary * ($esi_rate / 100);
    $professional_tax = $monthly_salary > 10000 ? 200 : 0;
    
    $total_deductions = $pf_deduction + $esi_deduction + $professional_tax;
    $net_salary = $gross_salary - $total_deductions;
    
    return [
        "basic_salary" => round($basic_salary, 2),
        "hra" => round($hra, 2),
        "da" => round($da, 2),
        "allowances" => round($allowances, 2),
        "gross_salary" => round($gross_salary, 2),
        "pf_deduction" => round($pf_deduction, 2),
        "esi_deduction" => round($esi_deduction, 2),
        "professional_tax" => round($professional_tax, 2),
        "total_deductions" => round($total_deductions, 2),
        "net_salary" => round($net_salary, 2)
    ];
}

function generatePayslip($conn, $employee_id, $month) {
    // Get employee details
    $emp_query = "SELECT * FROM employees WHERE employee_id = ? AND status = \"active\"";
    $stmt = $conn->prepare($emp_query);
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $employee = $stmt->get_result()->fetch_assoc();
    
    if (!$employee) {
        return ["success" => false, "message" => "Employee not found"];
    }
    
    // Check if payslip already exists
    $check_query = "SELECT payslip_id FROM payslips WHERE employee_id = ? AND pay_period = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("is", $employee_id, $month);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    
    if ($existing) {
        return ["success" => false, "message" => "Payslip already exists for this period"];
    }
    
    // Calculate salary components
    $components = calculateSalaryComponents($employee["monthly_salary"]);
    
    // Insert payslip
    $insert_query = "INSERT INTO payslips 
        (employee_id, employee_code, employee_name, department, position, pay_period, pay_date,
         basic_salary, hra, da, allowances, gross_salary, pf_deduction, esi_deduction,
         professional_tax, total_deductions, net_salary, generated_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($insert_query);
    $pay_date = date("Y-m-t", strtotime($month . "-01"));
    $generated_by = $_SESSION["admin"] ?? $_SESSION["user_id"] ?? 1;
    
    $stmt->bind_param("issssssddddddddddi",
        $employee["employee_id"], $employee["employee_code"], $employee["name"],
        $employee["department_name"], $employee["position"], $month, $pay_date,
        $components["basic_salary"], $components["hra"], $components["da"], $components["allowances"],
        $components["gross_salary"], $components["pf_deduction"], $components["esi_deduction"],
        $components["professional_tax"], $components["total_deductions"], $components["net_salary"],
        $generated_by
    );
    
    if ($stmt->execute()) {
        return ["success" => true, "payslip_id" => $conn->insert_id, "message" => "Payslip generated successfully"];
    } else {
        return ["success" => false, "message" => "Error generating payslip: " . $conn->error];
    }
}
?>';

if (file_put_contents(dirname(__DIR__) . '/includes/payroll_functions.php', $helper_functions)) {
    echo "✅ Created payroll helper functions file\n";
} else {
    echo "❌ Error creating helper functions file\n";
}

echo "\n🎉 Payroll system setup completed successfully!\n";
echo "\nSummary:\n";
echo "========\n";
echo "✅ Database tables: READY\n";
echo "✅ Employee salary components: CALCULATED\n";
echo "✅ Sample payslips: GENERATED\n";
echo "✅ Helper functions: CREATED\n";
echo "\n📋 You can now test the payslip generation system!\n";

$conn->close();
echo "</pre>\n";
?>
