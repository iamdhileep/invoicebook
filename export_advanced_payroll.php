<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';

// Get parameters
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Validate month and year
$month = max(1, min(12, $month));
$year = max(2020, min(2030, $year));

$monthName = date('F', mktime(0, 0, 0, $month, 1));
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

// Payroll Configuration
$payrollConfig = [
    'pf_rate' => 0.12,
    'esi_rate' => 0.0175,
    'professional_tax' => 200,
    'overtime_multiplier' => 1.5,
    'hra_percentage' => 0.30,
    'da_percentage' => 0.15,
    'transport_allowance' => 1600,
    'medical_allowance' => 1250,
    'special_allowance' => 2000
];

// Tax slabs (annual)
$taxSlabs = [
    ['min' => 0, 'max' => 250000, 'rate' => 0],
    ['min' => 250000, 'max' => 500000, 'rate' => 0.05],
    ['min' => 500000, 'max' => 1000000, 'rate' => 0.20],
    ['min' => 1000000, 'max' => PHP_INT_MAX, 'rate' => 0.30]
];

// Get all employees from hr_employees table
$employeesQuery = "SELECT * FROM hr_employees WHERE status = 'active' ORDER BY employee_id";
$employeesResult = mysqli_query($conn, $employeesQuery);

if (!$employeesResult) {
    die("Error fetching employees: " . mysqli_error($conn));
}

// Prepare export data
$exportData = [];
$totalGrossSalary = 0;
$totalDeductions = 0;
$totalNetSalary = 0;

while ($employee = mysqli_fetch_assoc($employeesResult)) {
    $employeeId = $employee['id'];
    
    // Get attendance data
    $attendanceQuery = "SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status IN ('present', 'half_day') THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN status = 'half_day' THEN 0.5 ELSE 0 END) as half_days,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
        COALESCE(SUM(overtime_hours), 0) as overtime_hours
        FROM hr_attendance 
        WHERE employee_id = $employeeId 
        AND MONTH(date) = $month 
        AND YEAR(date) = $year";
    
    $attendanceResult = mysqli_query($conn, $attendanceQuery);
    $attendance = mysqli_fetch_assoc($attendanceResult);
    
    $totalPresent = $attendance['present_days'] + ($attendance['half_days']);
    $absentDays = $attendance['absent_days'];
    $overtimeHours = $attendance['overtime_hours'] ?: 0;
    
    // Calculate salary components
    $monthlySalary = $employee['salary'] ?: 25000;
    $perDaySalary = $monthlySalary / $daysInMonth;
    
    // Basic earnings calculation
    $earnedBasic = ($monthlySalary / $daysInMonth) * $totalPresent;
    $hra = $earnedBasic * $payrollConfig['hra_percentage'];
    $da = $earnedBasic * $payrollConfig['da_percentage'];
    $transportAllowance = $payrollConfig['transport_allowance'];
    $medicalAllowance = $payrollConfig['medical_allowance'];
    $specialAllowance = $payrollConfig['special_allowance'];
    $overtimeAmount = $overtimeHours * ($perDaySalary / 8) * $payrollConfig['overtime_multiplier'];
    
    $grossSalary = $earnedBasic + $hra + $da + $transportAllowance + $medicalAllowance + $specialAllowance + $overtimeAmount;
    
    // Deductions calculation
    $pfDeduction = $earnedBasic * $payrollConfig['pf_rate'];
    $esiDeduction = $grossSalary * $payrollConfig['esi_rate'];
    $professionalTax = $grossSalary > 21000 ? $payrollConfig['professional_tax'] : 0;
    
    // Income tax calculation (simplified)
    $annualGross = $grossSalary * 12;
    $incomeTax = 0;
    foreach ($taxSlabs as $slab) {
        if ($annualGross > $slab['min']) {
            $taxableAmount = min($annualGross, $slab['max']) - $slab['min'];
            $incomeTax += $taxableAmount * $slab['rate'];
        }
    }
    $monthlyIncomeTax = $incomeTax / 12;
    
    $loanDeduction = 0; // Can be enhanced to fetch from loans table
    $advanceDeduction = 0; // Can be enhanced to fetch from advances table
    
    $totalDeduction = $pfDeduction + $esiDeduction + $professionalTax + $monthlyIncomeTax + $loanDeduction + $advanceDeduction;
    $netSalary = $grossSalary - $totalDeduction;
    
    // Add to export data
    $exportData[] = [
        'Employee Code' => $employee['employee_id'],
        'Employee Name' => $employee['first_name'] . ' ' . $employee['last_name'],
        'Department' => $employee['department'] ?: 'N/A',
        'Position' => $employee['position'] ?: 'N/A',
        'Present Days' => number_format($totalPresent, 1),
        'Absent Days' => $absentDays,
        'Overtime Hours' => number_format($overtimeHours, 1),
        'Basic Salary' => number_format($earnedBasic, 2),
        'HRA' => number_format($hra, 2),
        'DA' => number_format($da, 2),
        'Transport Allowance' => number_format($transportAllowance, 2),
        'Medical Allowance' => number_format($medicalAllowance, 2),
        'Special Allowance' => number_format($specialAllowance, 2),
        'Overtime Amount' => number_format($overtimeAmount, 2),
        'Gross Salary' => number_format($grossSalary, 2),
        'PF Deduction' => number_format($pfDeduction, 2),
        'ESI Deduction' => number_format($esiDeduction, 2),
        'Professional Tax' => number_format($professionalTax, 2),
        'Income Tax' => number_format($monthlyIncomeTax, 2),
        'Loan Deduction' => number_format($loanDeduction, 2),
        'Advance Deduction' => number_format($advanceDeduction, 2),
        'Total Deductions' => number_format($totalDeduction, 2),
        'Net Salary' => number_format($netSalary, 2)
    ];
    
    $totalGrossSalary += $grossSalary;
    $totalDeductions += $totalDeduction;
    $totalNetSalary += $netSalary;
}

// Set headers for CSV download
$filename = "Advanced_Payroll_" . $monthName . "_" . $year . ".csv";
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Expires: 0');
header('Pragma: public');

// Open output stream
$output = fopen('php://output', 'w');

// Add header with company info
fputcsv($output, ['Advanced Payroll Report']);
fputcsv($output, ['Period: ' . $monthName . ' ' . $year]);
fputcsv($output, ['Generated on: ' . date('d-M-Y H:i:s')]);
fputcsv($output, []);

// Add column headers
if (!empty($exportData)) {
    fputcsv($output, array_keys($exportData[0]));
    
    // Add data rows
    foreach ($exportData as $row) {
        fputcsv($output, $row);
    }
    
    // Add summary row
    fputcsv($output, []);
    fputcsv($output, ['SUMMARY']);
    fputcsv($output, ['Total Employees', count($exportData)]);
    fputcsv($output, ['Total Gross Salary', number_format($totalGrossSalary, 2)]);
    fputcsv($output, ['Total Deductions', number_format($totalDeductions, 2)]);
    fputcsv($output, ['Total Net Salary', number_format($totalNetSalary, 2)]);
} else {
    fputcsv($output, ['No payroll data found for the selected period']);
}

fclose($output);
?>
