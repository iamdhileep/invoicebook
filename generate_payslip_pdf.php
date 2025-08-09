<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';

// Get parameters
$employeeId = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

if (!$employeeId) {
    die("Employee ID is required");
}

// Validate month and year
$month = max(1, min(12, $month));
$year = max(2020, min(2030, $year));

$monthName = date('F', mktime(0, 0, 0, $month, 1));
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$month = max(1, min(12, $month));
$year = max(2020, min(2030, $year));

$monthName = date('F', mktime(0, 0, 0, $month, 1));
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

    // Get employee details
    $employeeQuery = "SELECT * FROM hr_employees WHERE id = ?";
    $stmt = mysqli_prepare($conn, $employeeQuery);
    mysqli_stmt_bind_param($stmt, "i", $employeeId);
    mysqli_stmt_execute($stmt);
    $employeeResult = mysqli_stmt_get_result($stmt);
    $employee = mysqli_fetch_assoc($employeeResult);if (!$employee) {
    die("Employee not found");
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
        AND YEAR(date) = ?";$stmt = mysqli_prepare($conn, $attendanceQuery);
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

// Set headers for PDF-like view
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        body { 
            font-family: 'Arial', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .payslip-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        .payslip-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 20px;
        }
        .company-info h2 {
            margin: 0 0 5px 0;
            font-size: 24px;
            font-weight: bold;
        }
        .employee-info {
            background-color: #f8f9fa;
            padding: 20px;
            border-bottom: 2px solid #e9ecef;
        }
        .salary-details {
            padding: 20px;
        }
        .earnings, .deductions {
            margin-bottom: 20px;
        }
        .net-salary {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
            border-radius: 10px;
        }
        .table th {
            background-color: #f1f3f4;
            font-weight: bold;
            font-size: 11px;
        }
        .table td {
            font-size: 11px;
        }
        .section-header {
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 5px;
            margin-bottom: 15px;
        }
        .no-print { 
            margin: 20px 0;
            text-align: center;
        }
        @media print {
            body { 
                margin: 0; 
                padding: 10px;
                background: white;
            }
            .no-print { display: none !important; }
            .payslip-container {
                box-shadow: none;
                border-radius: 0;
            }
        }
        .summary-card {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 10px 15px;
            margin: 10px 0;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn btn-primary me-2" onclick="window.print()">
            <i class="bi bi-printer"></i> Print Payslip
        </button>
        <button class="btn btn-secondary" onclick="window.close()">
            <i class="bi bi-x-circle"></i> Close
        </button>
    </div>

    <div class="payslip-container">
        <!-- Company Header -->
        <div class="payslip-header">
            <div class="company-info">
                <h2>YOUR COMPANY NAME</h2>
                <p class="mb-1">Complete HR Management System</p>
                <p class="mb-0">Email: info@company.com | Phone: +91 XXXXXXXXXX</p>
                <hr class="border-light my-3">
                <h3 class="mb-0">SALARY SLIP</h3>
                <h5 class="mb-0">Pay Period: <?= $monthName . ' ' . $year ?></h5>
            </div>
        </div>

        <!-- Employee Information -->
        <div class="employee-info">
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-primary mb-3"><i class="bi bi-person-circle"></i> Employee Details</h6>
                    <p class="mb-1"><strong>Name:</strong> <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></p>
                    <p class="mb-1"><strong>Employee Code:</strong> <?= htmlspecialchars($employee['employee_code']) ?></p>
                    <p class="mb-1"><strong>Department:</strong> <?= htmlspecialchars($employee['department'] ?: 'N/A') ?></p>
                    <p class="mb-1"><strong>Position:</strong> <?= htmlspecialchars($employee['position'] ?: 'N/A') ?></p>
                    <p class="mb-0"><strong>Date of Joining:</strong> <?= date('d-M-Y', strtotime($employee['hire_date'] ?: date('Y-m-d'))) ?></p>
                </div>
                <div class="col-md-6">
                    <h6 class="text-info mb-3"><i class="bi bi-calendar-check"></i> Attendance Summary</h6>
                    <p class="mb-1"><strong>Working Days:</strong> <?= $daysInMonth ?></p>
                    <p class="mb-1"><strong>Present Days:</strong> <span class="text-success"><?= number_format($totalPresent, 1) ?></span></p>
                    <p class="mb-1"><strong>Absent Days:</strong> <span class="text-danger"><?= $absentDays ?></span></p>
                    <p class="mb-1"><strong>Overtime Hours:</strong> <span class="text-warning"><?= number_format($overtimeHours, 1) ?></span></p>
                    <p class="mb-0"><strong>Attendance %:</strong> <span class="badge bg-primary"><?= number_format(($totalPresent / $daysInMonth) * 100, 1) ?>%</span></p>
                </div>
            </div>
        </div>

        <!-- Salary Details -->
        <div class="salary-details">
            <div class="row">
                <!-- Earnings -->
                <div class="col-md-6">
                    <div class="earnings">
                        <h5 class="text-success section-header"><i class="bi bi-plus-circle"></i> EARNINGS</h5>
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th class="text-end">Amount (₹)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Basic Salary (Earned)</td>
                                    <td class="text-end"><?= number_format($earnedBasic, 2) ?></td>
                                </tr>
                                <tr>
                                    <td>HRA (30%)</td>
                                    <td class="text-end"><?= number_format($hra, 2) ?></td>
                                </tr>
                                <tr>
                                    <td>Dearness Allowance (15%)</td>
                                    <td class="text-end"><?= number_format($da, 2) ?></td>
                                </tr>
                                <tr>
                                    <td>Transport Allowance</td>
                                    <td class="text-end"><?= number_format($transportAllowance, 2) ?></td>
                                </tr>
                                <tr>
                                    <td>Medical Allowance</td>
                                    <td class="text-end"><?= number_format($medicalAllowance, 2) ?></td>
                                </tr>
                                <tr>
                                    <td>Special Allowance</td>
                                    <td class="text-end"><?= number_format($specialAllowance, 2) ?></td>
                                </tr>
                                <?php if ($overtimeAmount > 0): ?>
                                <tr>
                                    <td>Overtime (<?= number_format($overtimeHours, 1) ?> hrs @ 1.5x)</td>
                                    <td class="text-end"><?= number_format($overtimeAmount, 2) ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr class="table-success">
                                    <td><strong>GROSS EARNINGS</strong></td>
                                    <td class="text-end"><strong>₹<?= number_format($grossSalary, 2) ?></strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Deductions -->
                <div class="col-md-6">
                    <div class="deductions">
                        <h5 class="text-danger section-header"><i class="bi bi-dash-circle"></i> DEDUCTIONS</h5>
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th class="text-end">Amount (₹)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>PF Deduction (12%)</td>
                                    <td class="text-end"><?= number_format($pfDeduction, 2) ?></td>
                                </tr>
                                <tr>
                                    <td>ESI Deduction (1.75%)</td>
                                    <td class="text-end"><?= number_format($esiDeduction, 2) ?></td>
                                </tr>
                                <tr>
                                    <td>Professional Tax</td>
                                    <td class="text-end"><?= number_format($professionalTax, 2) ?></td>
                                </tr>
                                <tr>
                                    <td>Income Tax (TDS)</td>
                                    <td class="text-end"><?= number_format($monthlyIncomeTax, 2) ?></td>
                                </tr>
                                <?php if ($loanDeduction > 0): ?>
                                <tr>
                                    <td>Loan Deduction</td>
                                    <td class="text-end"><?= number_format($loanDeduction, 2) ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($advanceDeduction > 0): ?>
                                <tr>
                                    <td>Advance Deduction</td>
                                    <td class="text-end"><?= number_format($advanceDeduction, 2) ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr class="table-danger">
                                    <td><strong>TOTAL DEDUCTIONS</strong></td>
                                    <td class="text-end"><strong>₹<?= number_format($totalDeduction, 2) ?></strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Net Salary -->
            <div class="net-salary">
                <h3 class="mb-2"><i class="bi bi-currency-rupee"></i> NET SALARY</h3>
                <h2 class="mb-0">₹<?= number_format($netSalary, 2) ?></h2>
                <p class="mb-0 mt-2">Amount in Words: <?= ucwords(numberToWords($netSalary)) ?> Rupees Only</p>
            </div>

            <!-- Summary Cards -->
            <div class="row">
                <div class="col-md-4">
                    <div class="summary-card">
                        <h6 class="mb-1 text-success"><i class="bi bi-arrow-up"></i> Total Earnings</h6>
                        <h5 class="mb-0">₹<?= number_format($grossSalary, 0) ?></h5>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="summary-card">
                        <h6 class="mb-1 text-danger"><i class="bi bi-arrow-down"></i> Total Deductions</h6>
                        <h5 class="mb-0">₹<?= number_format($totalDeduction, 0) ?></h5>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="summary-card">
                        <h6 class="mb-1 text-primary"><i class="bi bi-wallet2"></i> Take Home</h6>
                        <h5 class="mb-0">₹<?= number_format($netSalary, 0) ?></h5>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center mt-4 pt-3 border-top">
                <p class="text-muted mb-1"><small>This is a computer generated payslip and does not require signature.</small></p>
                <p class="text-muted mb-0"><small>Generated on: <?= date('d-M-Y H:i:s') ?></small></p>
            </div>
        </div>
    </div>

    <script>
        // Auto-focus for printing
        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('auto_print') === '1') {
                setTimeout(() => window.print(), 500);
            }
        }
    </script>
</body>
</html>

<?php
// Function to convert number to words
function numberToWords($number) {
    $ones = array(
        0 => 'zero', 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five',
        6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine', 10 => 'ten',
        11 => 'eleven', 12 => 'twelve', 13 => 'thirteen', 14 => 'fourteen', 15 => 'fifteen',
        16 => 'sixteen', 17 => 'seventeen', 18 => 'eighteen', 19 => 'nineteen'
    );

    $tens = array(
        0 => '', 1 => '', 2 => 'twenty', 3 => 'thirty', 4 => 'forty', 5 => 'fifty',
        6 => 'sixty', 7 => 'seventy', 8 => 'eighty', 9 => 'ninety'
    );

    $hundreds = array('', 'thousand', 'lakh', 'crore');

    if ($number == 0) {
        return $ones[0];
    }

    $number = number_format($number, 2, '.', '');
    $number_arr = explode('.', $number);
    $wholenum = $number_arr[0];
    $decimalnum = $number_arr[1];

    $words = array();
    $numLength = strlen($wholenum);
    $levels = (int) (($numLength + 2) / 3);
    $maxLength = $levels * 3;
    $wholenum = substr('00' . $wholenum, -$maxLength);
    $numLevels = str_split($wholenum, 3);

    for ($i = 0; $i < count($numLevels); $i++) {
        $levels--;
        $hundreds_digit = (int) ($numLevels[$i] / 100);
        $tens_digit = (int) (($numLevels[$i] / 10) % 10);
        $ones_digit = (int) ($numLevels[$i] % 10);

        if ($hundreds_digit) {
            $words[] = $ones[$hundreds_digit] . ' hundred' . ($tens_digit || $ones_digit ? ' ' : '');
        }

        if ($tens_digit < 2) {
            if ($tens_digit == 1) {
                $words[] = $ones[(int) substr($numLevels[$i], -2)];
            } elseif ($ones_digit) {
                $words[] = $ones[$ones_digit];
            }
        } else {
            $words[] = $tens[$tens_digit] . ($ones_digit ? ' ' . $ones[$ones_digit] : '');
        }

        if ($levels && (int) $numLevels[$i]) {
            $words[] = $hundreds[$levels] . ' ';
        }
    }

    $commaWords = implode('', $words);

    if ($decimalnum != '00') {
        $commaWords .= ' and ' . (int)$decimalnum . '/100';
    }

    return $commaWords;
}
?>
