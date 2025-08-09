<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

include '../db.php';

echo "<h2>🧮 Payroll System Sample Data Creation</h2>";
echo "<style>
    .test-result { padding: 10px; margin: 5px 0; border-radius: 5px; }
    .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .info { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
</style>";

$created_count = 0;

// Check if employees exist
$employees_check = mysqli_query($conn, "SELECT COUNT(*) as count FROM hr_employees WHERE status = 'active'");
$emp_count = 0;
if ($employees_check) {
    $result = mysqli_fetch_assoc($employees_check);
    $emp_count = $result['count'];
}

if ($emp_count == 0) {
    echo "<div class='test-result error'>❌ No active employees found. Please add employees first.</div>";
    echo "<a href='index.php' class='btn btn-primary mt-3'>Go to Employee Management</a>";
    exit;
}

echo "<div class='test-result info'>📊 Found $emp_count active employees</div>";

// Create sample salary components for employees
echo "<h3>💰 Creating Sample Salary Components</h3>";

$employees = mysqli_query($conn, "SELECT id, first_name, last_name, employee_id, salary FROM hr_employees WHERE status = 'active' LIMIT 10");

$salary_ranges = [
    ['basic' => 25000, 'position' => 'Junior'],
    ['basic' => 35000, 'position' => 'Senior'],
    ['basic' => 45000, 'position' => 'Lead'],
    ['basic' => 55000, 'position' => 'Manager'],
    ['basic' => 75000, 'position' => 'Senior Manager']
];

while ($employee = mysqli_fetch_assoc($employees)) {
    $random_salary = $salary_ranges[array_rand($salary_ranges)];
    $basic_salary = $random_salary['basic'];
    
    // Calculate components
    $hra = $basic_salary * 0.40; // 40% HRA
    $transport_allowance = 2500;
    $medical_allowance = 2000;
    $special_allowance = $basic_salary * 0.15; // 15% special allowance
    $other_allowances = 1000;
    
    $pf_deduction = $basic_salary * 0.12; // 12% PF
    $esi_deduction = ($basic_salary + $hra + $transport_allowance + $medical_allowance + $special_allowance + $other_allowances) * 0.0075; // 0.75% ESI
    $professional_tax = 200;
    $income_tax = ($basic_salary > 50000) ? ($basic_salary * 0.10) : 0; // 10% tax if salary > 50k
    $other_deductions = 0;
    
    // Check if salary component already exists
    $existing = mysqli_query($conn, "SELECT id FROM hr_salary_components WHERE employee_id = {$employee['id']} AND status = 'active'");
    
    if ($existing && mysqli_num_rows($existing) > 0) {
        echo "<div class='test-result info'>- Salary component already exists for {$employee['first_name']} {$employee['last_name']}</div>";
        continue;
    }
    
    $query = "
        INSERT INTO hr_salary_components (
            employee_id, basic_salary, hra, transport_allowance, medical_allowance,
            special_allowance, other_allowances, pf_deduction, esi_deduction,
            professional_tax, income_tax, other_deductions, effective_from
        ) VALUES (
            {$employee['id']}, $basic_salary, $hra, $transport_allowance, $medical_allowance,
            $special_allowance, $other_allowances, $pf_deduction, $esi_deduction,
            $professional_tax, $income_tax, $other_deductions, CURDATE()
        )
    ";
    
    if (mysqli_query($conn, $query)) {
        echo "<div class='test-result success'>✅ Created salary component for {$employee['first_name']} {$employee['last_name']} - Basic: ₹" . number_format($basic_salary) . "</div>";
        $created_count++;
    } else {
        echo "<div class='test-result error'>✗ Error creating salary component for {$employee['first_name']} {$employee['last_name']}: " . mysqli_error($conn) . "</div>";
    }
}

echo "<h3>📅 Creating Sample Attendance Data</h3>";

// Create sample attendance for current month if not exists
$current_month = date('n');
$current_year = date('Y');
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $current_month, $current_year);

$employees = mysqli_query($conn, "SELECT id, first_name, last_name FROM hr_employees WHERE status = 'active' LIMIT 5");
$attendance_created = 0;

while ($employee = mysqli_fetch_assoc($employees)) {
    for ($day = 1; $day <= min($days_in_month, 20); $day++) { // Only create for first 20 days
        $attendance_date = sprintf('%04d-%02d-%02d', $current_year, $current_month, $day);
        $day_of_week = date('N', strtotime($attendance_date));
        
        // Skip Sundays
        if ($day_of_week == 7) continue;
        
        // Check if attendance already exists
        $existing = mysqli_query($conn, "SELECT id FROM hr_attendance WHERE employee_id = {$employee['id']} AND attendance_date = '$attendance_date'");
        if ($existing && mysqli_num_rows($existing) > 0) continue;
        
        // Random attendance status (90% present, 5% late, 5% absent)
        $rand = rand(1, 100);
        if ($rand <= 90) {
            $status = 'Present';
            $check_in_time = '09:' . sprintf('%02d', rand(0, 30)) . ':00';
            $check_out_time = '18:' . sprintf('%02d', rand(0, 30)) . ':00';
            $overtime_hours = ($day % 5 == 0) ? rand(1, 3) : 0; // Overtime on every 5th day
        } elseif ($rand <= 95) {
            $status = 'Late';
            $check_in_time = '09:' . sprintf('%02d', rand(31, 59)) . ':00';
            $check_out_time = '18:' . sprintf('%02d', rand(31, 59)) . ':00';
            $overtime_hours = 0;
        } else {
            $status = 'Absent';
            $check_in_time = NULL;
            $check_out_time = NULL;
            $overtime_hours = 0;
        }
        
        $check_in_sql = $check_in_time ? "'$check_in_time'" : 'NULL';
        $check_out_sql = $check_out_time ? "'$check_out_time'" : 'NULL';
        
        $insert_attendance = "
            INSERT INTO hr_attendance (employee_id, attendance_date, status, check_in_time, check_out_time, overtime_hours)
            VALUES ({$employee['id']}, '$attendance_date', '$status', $check_in_sql, $check_out_sql, $overtime_hours)
        ";
        
        if (mysqli_query($conn, $insert_attendance)) {
            $attendance_created++;
        }
    }
}

echo "<div class='test-result success'>✅ Created $attendance_created attendance records</div>";

echo "<h3>🧮 Creating Sample Payroll Data</h3>";

// Process payroll for last month
$last_month = ($current_month == 1) ? 12 : $current_month - 1;
$last_year = ($current_month == 1) ? $current_year - 1 : $current_year;

$employees = mysqli_query($conn, "SELECT id, first_name, last_name FROM hr_employees WHERE status = 'active' LIMIT 5");
$payroll_created = 0;

while ($employee = mysqli_fetch_assoc($employees)) {
    // Check if payroll already exists
    $existing = mysqli_query($conn, "SELECT id FROM hr_payroll WHERE employee_id = {$employee['id']} AND payroll_month = $last_month AND payroll_year = $last_year");
    if ($existing && mysqli_num_rows($existing) > 0) {
        echo "<div class='test-result info'>- Payroll already exists for {$employee['first_name']} {$employee['last_name']}</div>";
        continue;
    }
    
    // Get salary component
    $salary_comp = mysqli_query($conn, "SELECT * FROM hr_salary_components WHERE employee_id = {$employee['id']} AND status = 'active' ORDER BY id DESC LIMIT 1");
    
    if (!$salary_comp || mysqli_num_rows($salary_comp) == 0) {
        continue; // Skip if no salary component
    }
    
    $comp = mysqli_fetch_assoc($salary_comp);
    
    // Sample calculations
    $working_days = 22;
    $present_days = rand(18, 22);
    $absent_days = $working_days - $present_days;
    $overtime_hours = rand(0, 10);
    
    $basic_salary = $comp['basic_salary'];
    $earned_basic = ($basic_salary / $working_days) * $present_days;
    
    $allowances = $comp['hra'] + $comp['transport_allowance'] + $comp['medical_allowance'] + $comp['special_allowance'] + $comp['other_allowances'];
    $overtime_amount = ($basic_salary / ($working_days * 8)) * $overtime_hours * 1.5;
    $bonus = 0;
    
    $gross_salary = $earned_basic + $allowances + $overtime_amount + $bonus;
    
    $pf_deduction = $comp['pf_deduction'];
    $esi_deduction = ($gross_salary <= 21000) ? $comp['esi_deduction'] : 0;
    $tax_deduction = $comp['income_tax'];
    $other_deductions = $comp['other_deductions'];
    
    $total_deductions = $pf_deduction + $esi_deduction + $tax_deduction + $other_deductions;
    $net_salary = $gross_salary - $total_deductions;
    
    $payroll_query = "
        INSERT INTO hr_payroll (
            employee_id, payroll_month, payroll_year, basic_salary, gross_salary,
            working_days, present_days, absent_days, overtime_hours, overtime_amount,
            bonus, allowances, deductions, pf_deduction, esi_deduction, tax_deduction,
            net_salary, status, processed_by, processed_at
        ) VALUES (
            {$employee['id']}, $last_month, $last_year, $basic_salary, $gross_salary,
            $working_days, $present_days, $absent_days, $overtime_hours, $overtime_amount,
            $bonus, $allowances, $other_deductions, $pf_deduction, $esi_deduction, $tax_deduction,
            $net_salary, 'processed', 1, NOW()
        )
    ";
    
    if (mysqli_query($conn, $payroll_query)) {
        echo "<div class='test-result success'>✅ Created payroll for {$employee['first_name']} {$employee['last_name']} - Net: ₹" . number_format($net_salary, 2) . "</div>";
        $payroll_created++;
    } else {
        echo "<div class='test-result error'>✗ Error creating payroll for {$employee['first_name']} {$employee['last_name']}: " . mysqli_error($conn) . "</div>";
    }
}

echo "<br><div class='test-result info'><strong>📊 Sample Data Creation Summary:</strong><br>";
echo "- Salary Components: $created_count created<br>";
echo "- Attendance Records: $attendance_created created<br>";
echo "- Payroll Records: $payroll_created created</div>";

echo "<br><strong>✅ Sample data creation completed!</strong><br>";
echo "<br><div class='mt-4'>";
echo "<a href='payroll_processing.php' class='btn btn-primary me-2'>💰 Go to Payroll Processing</a>";
echo "<a href='index.php' class='btn btn-secondary'>🏠 Back to HRMS Dashboard</a>";
echo "</div>";
?>
