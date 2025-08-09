<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

include 'db.php';

// Get parameters
$employeeId = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

if (!$employeeId) {
    http_response_code(400);
    echo json_encode(['error' => 'Employee ID is required']);
    exit;
}

// Validate month and year
$month = max(1, min(12, $month));
$year = max(2020, min(2030, $year));

$monthName = date('F', mktime(0, 0, 0, $month, 1));
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

try {
    // Get employee details
    $employeeQuery = "SELECT * FROM hr_employees WHERE id = ?";
    $stmt = mysqli_prepare($conn, $employeeQuery);
    mysqli_stmt_bind_param($stmt, "i", $employeeId);
    mysqli_stmt_execute($stmt);
    $employeeResult = mysqli_stmt_get_result($stmt);
    $employee = mysqli_fetch_assoc($employeeResult);

    if (!$employee) {
        http_response_code(404);
        echo json_encode(['error' => 'Employee not found']);
        exit;
    }

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

    // Get attendance data
    $attendanceQuery = "SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status IN ('present', 'half_day') THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN status = 'half_day' THEN 0.5 ELSE 0 END) as half_days,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
        COALESCE(SUM(overtime_hours), 0) as overtime_hours
        FROM hr_attendance 
        WHERE employee_id = ? 
        AND MONTH(date) = ? 
        AND YEAR(date) = ?";

    $stmt = mysqli_prepare($conn, $attendanceQuery);
    mysqli_stmt_bind_param($stmt, "iii", $employeeId, $month, $year);
    mysqli_stmt_execute($stmt);
    $attendanceResult = mysqli_stmt_get_result($stmt);
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

    // Generate HTML content
    ob_start();
    ?>
    <div class="payslip-content">
        <div class="text-center mb-4">
            <h4 class="text-primary mb-1">Advanced Payroll System</h4>
            <h5 class="text-muted">Payslip for <?= $monthName . ' ' . $year ?></h5>
        </div>
        
        <!-- Employee Info -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card bg-light">
                    <div class="card-body py-2">
                        <h6 class="card-title text-primary mb-2"><i class="bi bi-person-circle"></i> Employee Details</h6>
                        <p class="mb-1"><strong>Name:</strong> <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></p>
                        <p class="mb-1"><strong>Code:</strong> <?= htmlspecialchars($employee['employee_id']) ?></p>
                        <p class="mb-1"><strong>Department:</strong> <?= htmlspecialchars($employee['department'] ?: 'N/A') ?></p>
                        <p class="mb-0"><strong>Position:</strong> <?= htmlspecialchars($employee['position'] ?: 'N/A') ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-light">
                    <div class="card-body py-2">
                        <h6 class="card-title text-info mb-2"><i class="bi bi-calendar-check"></i> Attendance Summary</h6>
                        <p class="mb-1"><strong>Working Days:</strong> <?= $daysInMonth ?></p>
                        <p class="mb-1"><strong>Present:</strong> <span class="text-success"><?= number_format($totalPresent, 1) ?></span></p>
                        <p class="mb-1"><strong>Absent:</strong> <span class="text-danger"><?= $absentDays ?></span></p>
                        <p class="mb-0"><strong>Overtime:</strong> <span class="text-warning"><?= number_format($overtimeHours, 1) ?> hrs</span></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Salary Breakdown -->
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-success mb-3"><i class="bi bi-plus-circle"></i> Earnings</h6>
                <table class="table table-sm table-bordered">
                    <tbody>
                        <tr>
                            <td>Basic Salary (Earned)</td>
                            <td class="text-end">₹<?= number_format($earnedBasic, 0) ?></td>
                        </tr>
                        <tr>
                            <td>HRA (30%)</td>
                            <td class="text-end">₹<?= number_format($hra, 0) ?></td>
                        </tr>
                        <tr>
                            <td>DA (15%)</td>
                            <td class="text-end">₹<?= number_format($da, 0) ?></td>
                        </tr>
                        <tr>
                            <td>Transport Allowance</td>
                            <td class="text-end">₹<?= number_format($transportAllowance, 0) ?></td>
                        </tr>
                        <tr>
                            <td>Medical Allowance</td>
                            <td class="text-end">₹<?= number_format($medicalAllowance, 0) ?></td>
                        </tr>
                        <tr>
                            <td>Special Allowance</td>
                            <td class="text-end">₹<?= number_format($specialAllowance, 0) ?></td>
                        </tr>
                        <?php if ($overtimeAmount > 0): ?>
                        <tr>
                            <td>Overtime (<?= number_format($overtimeHours, 1) ?>h)</td>
                            <td class="text-end">₹<?= number_format($overtimeAmount, 0) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr class="table-success">
                            <td><strong>Gross Earnings</strong></td>
                            <td class="text-end"><strong>₹<?= number_format($grossSalary, 0) ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="col-md-6">
                <h6 class="text-danger mb-3"><i class="bi bi-dash-circle"></i> Deductions</h6>
                <table class="table table-sm table-bordered">
                    <tbody>
                        <tr>
                            <td>PF Deduction (12%)</td>
                            <td class="text-end">₹<?= number_format($pfDeduction, 0) ?></td>
                        </tr>
                        <tr>
                            <td>ESI Deduction (1.75%)</td>
                            <td class="text-end">₹<?= number_format($esiDeduction, 0) ?></td>
                        </tr>
                        <tr>
                            <td>Professional Tax</td>
                            <td class="text-end">₹<?= number_format($professionalTax, 0) ?></td>
                        </tr>
                        <tr>
                            <td>Income Tax (TDS)</td>
                            <td class="text-end">₹<?= number_format($monthlyIncomeTax, 0) ?></td>
                        </tr>
                        <?php if ($loanDeduction > 0): ?>
                        <tr>
                            <td>Loan Deduction</td>
                            <td class="text-end">₹<?= number_format($loanDeduction, 0) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($advanceDeduction > 0): ?>
                        <tr>
                            <td>Advance Deduction</td>
                            <td class="text-end">₹<?= number_format($advanceDeduction, 0) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr class="table-danger">
                            <td><strong>Total Deductions</strong></td>
                            <td class="text-end"><strong>₹<?= number_format($totalDeduction, 0) ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Net Salary -->
        <div class="alert alert-success text-center mt-4">
            <h5 class="mb-2"><i class="bi bi-currency-rupee"></i> NET SALARY</h5>
            <h3 class="text-success mb-0">₹<?= number_format($netSalary, 0) ?></h3>
        </div>

        <!-- Quick Stats -->
        <div class="row mt-3">
            <div class="col-md-4">
                <div class="text-center p-2 bg-light rounded">
                    <small class="text-muted">Attendance</small><br>
                    <strong><?= number_format(($totalPresent / $daysInMonth) * 100, 1) ?>%</strong>
                </div>
            </div>
            <div class="col-md-4">
                <div class="text-center p-2 bg-light rounded">
                    <small class="text-muted">Effective Rate</small><br>
                    <strong>₹<?= number_format($perDaySalary, 0) ?>/day</strong>
                </div>
            </div>
            <div class="col-md-4">
                <div class="text-center p-2 bg-light rounded">
                    <small class="text-muted">Tax Rate</small><br>
                    <strong><?= number_format(($totalDeduction / $grossSalary) * 100, 1) ?>%</strong>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-3">
            <small class="text-muted">Generated on: <?= date('d-M-Y H:i:s') ?></small>
        </div>
    </div>
    <?php
    $content = ob_get_clean();

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'html' => $content,
        'employee' => [
            'id' => $employee['id'],
            'name' => $employee['first_name'] . ' ' . $employee['last_name'],
            'code' => $employee['employee_id']
        ],
        'salary_data' => [
            'gross_salary' => $grossSalary,
            'total_deductions' => $totalDeduction,
            'net_salary' => $netSalary,
            'attendance_percentage' => ($totalPresent / $daysInMonth) * 100
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
