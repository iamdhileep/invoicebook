<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';

// Get parameters
$employee_id = $_GET['employee_id'] ?? null;
$month = $_GET['month'] ?? date('Y-m');
$print_mode = isset($_GET['print']) && $_GET['print'] == '1';

if (!$employee_id) {
    die("Employee ID is required");
}

// Parse month (format: YYYY-MM)
$monthParts = explode('-', $month);
$year = $monthParts[0];
$monthNum = $monthParts[1];

// Get employee details with fallback for different column names
$employeeQuery = $conn->prepare("SELECT * FROM employees WHERE employee_id = ?");
$employeeQuery->bind_param("i", $employee_id);
$employeeQuery->execute();
$employee = $employeeQuery->get_result()->fetch_assoc();

if (!$employee) {
    die("Employee not found");
}

// Ensure we have the right column names
$employeeName = $employee['name'] ?? $employee['employee_name'] ?? 'N/A';
$employeeCode = $employee['employee_code'] ?? $employee['code'] ?? 'N/A';
$position = $employee['position'] ?? $employee['designation'] ?? 'N/A';
$monthlySalary = $employee['monthly_salary'] ?? $employee['salary'] ?? 0;
$joiningDate = $employee['joining_date'] ?? $employee['created_at'] ?? date('Y-m-d');

// Calculate payroll details
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $monthNum, $year);

// Get attendance data
$presentDays = 0;
$absentDays = 0;
$lateDays = 0;
$overtimeHours = 0;

// Check attendance table structure and get data
$attendanceQuery = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? AND MONTH(attendance_date) = ? AND YEAR(attendance_date) = ?");
if ($attendanceQuery) {
    $attendanceQuery->bind_param("iii", $employee_id, $monthNum, $year);
    $attendanceQuery->execute();
    $attendanceResult = $attendanceQuery->get_result();
    
    while ($att = $attendanceResult->fetch_assoc()) {
        if (($att['status'] ?? '') == 'Present' || ($att['punch_in'] ?? '') != '') {
            $presentDays++;
            
            // Calculate overtime if punch_in and punch_out exist
            if (!empty($att['punch_in']) && !empty($att['punch_out'])) {
                $punchIn = strtotime($att['punch_in']);
                $punchOut = strtotime($att['punch_out']);
                $hoursWorked = ($punchOut - $punchIn) / 3600;
                
                if ($hoursWorked > 8) {
                    $overtimeHours += ($hoursWorked - 8);
                }
            }
        } else {
            $absentDays++;
        }
    }
}

$absentDays = $daysInMonth - $presentDays;

// Calculate salary components
$perDaySalary = $monthlySalary / $daysInMonth;
$basicSalary = $monthlySalary * 0.6; // 60% basic
$hra = $monthlySalary * 0.2; // 20% HRA
$da = $monthlySalary * 0.1; // 10% DA
$allowances = $monthlySalary * 0.1; // 10% other allowances

// Calculate earnings
$earnedBasic = ($basicSalary / $daysInMonth) * $presentDays;
$earnedHRA = ($hra / $daysInMonth) * $presentDays;
$earnedDA = ($da / $daysInMonth) * $presentDays;
$earnedAllowances = ($allowances / $daysInMonth) * $presentDays;
$overtimePay = $overtimeHours * ($perDaySalary / 8) * 1.5; // 1.5x for overtime

$grossEarnings = $earnedBasic + $earnedHRA + $earnedDA + $earnedAllowances + $overtimePay;

// Calculate deductions
$pf = $earnedBasic * 0.12; // 12% PF on basic
$esi = $grossEarnings * 0.0175; // 1.75% ESI
$professionalTax = $grossEarnings > 10000 ? 200 : 0;
$incomeTax = 0; // Simplified - can be enhanced
$absentDeduction = $perDaySalary * $absentDays;

$totalDeductions = $pf + $esi + $professionalTax + $incomeTax + $absentDeduction;
$netSalary = $grossEarnings - $totalDeductions;

// Company details
$companyName = "Business Management System";
$companyAddress = "Business Address Line 1, City, State - 123456";
$companyPhone = "+91 12345 67890";
$companyEmail = "hr@company.com";

$page_title = "Payslip - " . $employeeName;

// If not in print mode, include header and sidebar
if (!$print_mode) {
    include '../../layouts/header.php';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    
    <?php if ($print_mode): ?>
    <!-- Bootstrap 5 CSS for print mode -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <?php endif; ?>
    
    <style>
        .payslip-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        
        .company-header {
            text-align: center;
            border-bottom: 2px solid #007bff;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 5px;
        }
        
        .payslip-title {
            font-size: 18px;
            font-weight: bold;
            text-align: center;
            margin: 20px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .employee-info, .salary-details {
            margin-bottom: 25px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dotted #ccc;
        }
        
        .info-label {
            font-weight: 600;
            color: #495057;
        }
        
        .info-value {
            color: #212529;
        }
        
        .salary-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .salary-table th,
        .salary-table td {
            padding: 12px;
            text-align: left;
            border: 1px solid #dee2e6;
        }
        
        .salary-table th {
            background-color: #007bff;
            color: white;
            font-weight: 600;
        }
        
        .salary-table .section-header {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .amount {
            text-align: right;
            font-family: 'Courier New', monospace;
        }
        
        .total-row {
            background-color: #e9ecef;
            font-weight: bold;
        }
        
        .net-salary-row {
            background-color: #d4edda;
            font-weight: bold;
            font-size: 16px;
        }
        
        .footer-note {
            margin-top: 30px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
            font-size: 12px;
            color: #6c757d;
        }
        
        .signature-section {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }
        
        .signature-box {
            text-align: center;
            padding: 20px;
        }
        
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 50px;
            padding-top: 5px;
        }
        
        @media print {
            body { margin: 0; }
            .payslip-container { 
                border: none; 
                box-shadow: none;
                max-width: none;
            }
            .no-print { display: none !important; }
            .page-break { page-break-after: always; }
        }
        
        <?php if (!$print_mode): ?>
        .main-content {
            padding: 20px;
        }
        <?php endif; ?>
    </style>
</head>

<body>
    <?php if (!$print_mode): ?>
    <div class="main-content">
        <?php include '../../layouts/sidebar.php'; ?>
        
        <div class="content">
            <div class="container-fluid">
                <!-- Action Buttons -->
                <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                    <div>
                        <h1 class="h3 mb-0">Generate Payslip</h1>
                        <p class="text-muted">Employee payslip for <?= date('F Y', mktime(0, 0, 0, $monthNum, 1, $year)) ?></p>
                    </div>
                    <div>
                        <button onclick="window.print()" class="btn btn-primary me-2">
                            <i class="bi bi-printer"></i> Print
                        </button>
                        <button onclick="downloadPDF()" class="btn btn-success me-2">
                            <i class="bi bi-file-pdf"></i> Download PDF
                        </button>
                        <a href="payroll.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Payroll
                        </a>
                    </div>
                </div>
    <?php endif; ?>

    <!-- Payslip Content -->
    <div class="payslip-container">
        <!-- Company Header -->
        <div class="company-header">
            <div class="company-name"><?= htmlspecialchars($companyName) ?></div>
            <div><?= htmlspecialchars($companyAddress) ?></div>
            <div>Phone: <?= htmlspecialchars($companyPhone) ?> | Email: <?= htmlspecialchars($companyEmail) ?></div>
        </div>

        <!-- Payslip Title -->
        <div class="payslip-title">
            SALARY SLIP FOR <?= strtoupper(date('F Y', mktime(0, 0, 0, $monthNum, 1, $year))) ?>
        </div>

        <!-- Employee Information -->
        <div class="employee-info">
            <h5 class="mb-3">Employee Information</h5>
            <div class="row">
                <div class="col-md-6">
                    <div class="info-row">
                        <span class="info-label">Employee Name:</span>
                        <span class="info-value"><?= htmlspecialchars($employeeName) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Employee Code:</span>
                        <span class="info-value"><?= htmlspecialchars($employeeCode) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Designation:</span>
                        <span class="info-value"><?= htmlspecialchars($position) ?></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-row">
                        <span class="info-label">Pay Period:</span>
                        <span class="info-value"><?= date('F Y', mktime(0, 0, 0, $monthNum, 1, $year)) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Days in Month:</span>
                        <span class="info-value"><?= $daysInMonth ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Days Present:</span>
                        <span class="info-value"><?= $presentDays ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Summary -->
        <div class="salary-details">
            <h5 class="mb-3">Attendance Summary</h5>
            <div class="row">
                <div class="col-md-3 text-center">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h4><?= $presentDays ?></h4>
                            <small>Present Days</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 text-center">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <h4><?= $absentDays ?></h4>
                            <small>Absent Days</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 text-center">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h4><?= number_format($overtimeHours, 1) ?></h4>
                            <small>Overtime Hours</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 text-center">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h4>₹<?= number_format($perDaySalary, 2) ?></h4>
                            <small>Per Day Salary</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Salary Breakdown -->
        <table class="salary-table">
            <thead>
                <tr>
                    <th style="width: 50%">EARNINGS</th>
                    <th style="width: 25%">AMOUNT (₹)</th>
                    <th style="width: 50%">DEDUCTIONS</th>
                    <th style="width: 25%">AMOUNT (₹)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Basic Salary</td>
                    <td class="amount"><?= number_format($earnedBasic, 2) ?></td>
                    <td>Provident Fund (12%)</td>
                    <td class="amount"><?= number_format($pf, 2) ?></td>
                </tr>
                <tr>
                    <td>House Rent Allowance</td>
                    <td class="amount"><?= number_format($earnedHRA, 2) ?></td>
                    <td>ESI (1.75%)</td>
                    <td class="amount"><?= number_format($esi, 2) ?></td>
                </tr>
                <tr>
                    <td>Dearness Allowance</td>
                    <td class="amount"><?= number_format($earnedDA, 2) ?></td>
                    <td>Professional Tax</td>
                    <td class="amount"><?= number_format($professionalTax, 2) ?></td>
                </tr>
                <tr>
                    <td>Other Allowances</td>
                    <td class="amount"><?= number_format($earnedAllowances, 2) ?></td>
                    <td>Income Tax</td>
                    <td class="amount"><?= number_format($incomeTax, 2) ?></td>
                </tr>
                <tr>
                    <td>Overtime Pay</td>
                    <td class="amount"><?= number_format($overtimePay, 2) ?></td>
                    <td>Absent Days Deduction</td>
                    <td class="amount"><?= number_format($absentDeduction, 2) ?></td>
                </tr>
                <tr class="total-row">
                    <td><strong>GROSS EARNINGS</strong></td>
                    <td class="amount"><strong><?= number_format($grossEarnings, 2) ?></strong></td>
                    <td><strong>TOTAL DEDUCTIONS</strong></td>
                    <td class="amount"><strong><?= number_format($totalDeductions, 2) ?></strong></td>
                </tr>
                <tr class="net-salary-row">
                    <td colspan="3"><strong>NET SALARY (Gross Earnings - Total Deductions)</strong></td>
                    <td class="amount"><strong>₹ <?= number_format($netSalary, 2) ?></strong></td>
                </tr>
            </tbody>
        </table>

        <!-- Net Salary in Words -->
        <div class="alert alert-info">
            <strong>Net Salary in Words:</strong> 
            <?= ucwords(convertNumberToWords($netSalary)) ?> Rupees Only
        </div>

        <!-- Footer Note -->
        <div class="footer-note">
            <p><strong>Note:</strong></p>
            <ul class="mb-0">
                <li>This is a computer-generated payslip and does not require a signature.</li>
                <li>PF and ESI deductions are as per government regulations.</li>
                <li>Overtime is calculated at 1.5x the hourly rate for hours worked beyond 8 hours per day.</li>
                <li>Professional Tax is applicable for gross salary above ₹10,000 per month.</li>
            </ul>
        </div>

        <!-- Signature Section -->
        <div class="signature-section no-print">
            <div class="signature-box">
                <div class="signature-line">
                    <strong>Employee Signature</strong>
                </div>
            </div>
            <div class="signature-box">
                <div class="signature-line">
                    <strong>HR Manager</strong>
                </div>
            </div>
            <div class="signature-box">
                <div class="signature-line">
                    <strong>Authorized Signatory</strong>
                </div>
            </div>
        </div>

        <!-- Generation Info -->
        <div class="text-center mt-4" style="font-size: 12px; color: #6c757d;">
            Generated on: <?= date('d-m-Y H:i:s') ?> | Generated by: Business Management System
        </div>
    </div>

    <?php if (!$print_mode): ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Scripts -->
    <?php if ($print_mode): ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php endif; ?>
    
    <script>
        function downloadPDF() {
            // Open print-friendly version for PDF generation
            const printUrl = window.location.href + (window.location.href.includes('?') ? '&' : '?') + 'print=1';
            window.open(printUrl, '_blank');
        }
        
        // Auto-print if in print mode
        <?php if ($print_mode): ?>
        window.onload = function() {
            window.print();
        };
        <?php endif; ?>
    </script>

    <?php if (!$print_mode) include '../../layouts/footer.php'; ?>
</body>
</html>

<?php
// Function to convert number to words
function convertNumberToWords($number) {
    $number = (int) $number;
    
    $ones = array(
        '', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine',
        'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen',
        'seventeen', 'eighteen', 'nineteen'
    );
    
    $tens = array(
        '', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'
    );
    
    if ($number == 0) {
        return 'zero';
    }
    
    $result = '';
    
    // Handle crores
    if ($number >= 10000000) {
        $crores = (int)($number / 10000000);
        $result .= convertNumberToWords($crores) . ' crore ';
        $number %= 10000000;
    }
    
    // Handle lakhs
    if ($number >= 100000) {
        $lakhs = (int)($number / 100000);
        $result .= convertNumberToWords($lakhs) . ' lakh ';
        $number %= 100000;
    }
    
    // Handle thousands
    if ($number >= 1000) {
        $thousands = (int)($number / 1000);
        $result .= convertNumberToWords($thousands) . ' thousand ';
        $number %= 1000;
    }
    
    // Handle hundreds
    if ($number >= 100) {
        $hundreds = (int)($number / 100);
        $result .= $ones[$hundreds] . ' hundred ';
        $number %= 100;
    }
    
    // Handle tens and ones
    if ($number >= 20) {
        $tensDigit = (int)($number / 10);
        $onesDigit = $number % 10;
        $result .= $tens[$tensDigit];
        if ($onesDigit > 0) {
            $result .= ' ' . $ones[$onesDigit];
        }
    } elseif ($number > 0) {
        $result .= $ones[$number];
    }
    
    return trim($result);
}
?>