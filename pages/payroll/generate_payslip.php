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
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }
        
        .payslip-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .payslip-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 3px solid #007bff;
        }
        
        .company-logo {
            max-width: 150px;
            height: auto;
            max-height: 60px;
            object-fit: contain;
        }
        
        .payslip-title {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 10px;
        }
        
        .payslip-details {
            padding: 20px;
        }
        
        .employee-details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .attendance-cards {
            margin-bottom: 20px;
        }
        
        .attendance-cards .card {
            border: none;
            border-radius: 8px;
            transition: transform 0.2s;
        }
        
        .attendance-cards .card:hover {
            transform: translateY(-2px);
        }
        
        .salary-table {
            margin-bottom: 20px;
        }
        
        .salary-table th {
            background: #007bff;
            color: white;
            font-weight: bold;
            text-align: center;
            padding: 10px 8px;
            border: 1px solid #dee2e6;
            white-space: nowrap;
            font-size: 11px;
            font-weight: 700;
        }
        
        .salary-table td {
            text-align: center;
            vertical-align: middle;
            padding: 8px 6px;
            border: 1px solid #dee2e6;
            white-space: nowrap;
            font-size: 10px;
        }
        
        .salary-table .item-name {
            text-align: left;
            padding-left: 12px;
        }
        
        .salary-table table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        
        .salary-table th:nth-child(1) {
            width: 35%;
        }
        
        .salary-table th:nth-child(2) {
            width: 15%;
        }
        
        .salary-table th:nth-child(3) {
            width: 35%;
        }
        
        .salary-table th:nth-child(4) {
            width: 15%;
        }
        
        /* Add better table formatting */
        .table-responsive {
            border-radius: 4px;
            overflow: hidden;
        }
        
        .salary-table .table-bordered th,
        .salary-table .table-bordered td {
            border: 1px solid #dee2e6;
        }
        
        .salary-table tbody tr:last-child td {
            border-bottom: 2px solid #007bff;
        }
        
        /* Improve table cell spacing */
        .salary-table th {
            white-space: nowrap;
            font-size: 11px;
            font-weight: 700;
        }
        
        .salary-table td {
            white-space: nowrap;
            font-size: 10px;
        }
        
        .total-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .amount-words {
            font-style: italic;
            color: #666;
            margin-top: 10px;
        }
        
        .footer-section {
            border-top: 2px solid #007bff;
            padding-top: 20px;
            margin-top: 30px;
        }
        
        .signature-section {
            margin-top: 40px;
        }
        
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .company-details {
            font-size: 11px;
            line-height: 1.3;
        }
        
        /* Single row layout styles */
        .employee-details .d-flex span {
            margin-right: 20px;
            margin-bottom: 5px;
            font-size: 12px;
            white-space: nowrap;
            flex-shrink: 0;
        }
        
        .employee-details .d-flex {
            gap: 15px;
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {
            .employee-details .d-flex {
                flex-direction: column;
                gap: 8px;
            }
            
            .employee-details .d-flex span {
                margin-right: 0;
                font-size: 11px;
            }
            
            .company-details {
                font-size: 10px !important;
                line-height: 1.2;
            }
        }
        
        /* Screen styles */
        @media screen {
            body {
                background: #e9ecef;
                padding: 20px;
            }
        }
        
        /* Print styles - Consolidated for better alignment */
        @media print {
            .no-print { 
                display: none !important; 
            }
            
            body {
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
                font-size: 11px !important;
            }
            
            .print-container,
            .payslip-container {
                width: 100% !important;
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                page-break-inside: avoid;
            }
            
            .main-content {
                padding: 0 !important;
                margin: 0 !important;
            }
            
            .container-fluid {
                padding: 0 !important;
                margin: 0 !important;
            }
            
            .payslip-header {
                padding: 15px !important;
                margin: 0 !important;
            }
            
            .payslip-details {
                padding: 15px !important;
            }
            
            .employee-details {
                padding: 12px !important;
                margin-bottom: 15px !important;
            }
            
            .attendance-cards .card-body {
                padding: 8px !important;
            }
            
            .salary-table {
                margin-bottom: 15px !important;
            }
            
            .salary-table .table-responsive {
                border: none !important;
                overflow: visible !important;
            }
            
            .salary-table table {
                width: 100% !important;
                border-collapse: collapse !important;
                table-layout: fixed !important;
                margin: 0 !important;
            }
            
            .salary-table th {
                background: #007bff !important;
                color: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                padding: 8px 6px !important;
                border: 1px solid #000 !important;
                font-size: 10px !important;
                font-weight: bold !important;
                text-align: center !important;
                vertical-align: middle !important;
                white-space: nowrap !important;
            }
            
            .salary-table td {
                padding: 6px 4px !important;
                border: 1px solid #000 !important;
                font-size: 9px !important;
                text-align: center !important;
                vertical-align: middle !important;
                line-height: 1.2 !important;
                white-space: nowrap !important;
            }
            
            .salary-table .item-name {
                text-align: left !important;
                padding-left: 8px !important;
            }
            
            .salary-table th:nth-child(1),
            .salary-table td:nth-child(1) {
                width: 35% !important;
            }
            
            .salary-table th:nth-child(2),
            .salary-table td:nth-child(2) {
                width: 15% !important;
            }
            
            .salary-table th:nth-child(3),
            .salary-table td:nth-child(3) {
                width: 35% !important;
            }
            
            .salary-table th:nth-child(4),
            .salary-table td:nth-child(4) {
                width: 15% !important;
            }
            
            /* Fix for total row styling in print */
            .salary-table tr[style*="background-color"] td {
                background-color: #f8f9fa !important;
                font-weight: bold !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            /* Ensure consistent table layout */
            .salary-table th,
            .salary-table td {
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
            }
            
            .total-section {
                padding: 12px !important;
                margin-bottom: 15px !important;
            }
            
            .footer-section {
                margin-top: 20px !important;
                padding-top: 15px !important;
            }
            
            .company-logo {
                max-width: 120px !important;
                max-height: 50px !important;
            }
            
            .payslip-title {
                font-size: 22px !important;
                margin-bottom: 8px !important;
            }
            
            .signature-section {
                margin-top: 30px !important;
            }
            
            /* Ensure proper spacing */
            .row {
                margin: 0 !important;
            }
            
            .col-md-3, .col-md-4, .col-md-6, .col-md-8, .col-md-9 {
                padding: 0 8px !important;
            }
            
            /* Fix Bootstrap column gutters for print */
            .row > * {
                padding-right: 8px !important;
                padding-left: 8px !important;
            }
            
            /* Employee details section alignment */
            .employee-details .row > * {
                padding-right: 6px !important;
                padding-left: 6px !important;
            }
            
            .employee-details .col-4 {
                padding-right: 4px !important;
            }
            
            .employee-details .col-8 {
                padding-left: 4px !important;
            }
            
            /* Single row layout for print */
            .employee-details .d-flex {
                gap: 10px !important;
                justify-content: space-between !important;
            }
            
            .employee-details .d-flex span {
                font-size: 9px !important;
                margin-right: 8px !important;
                margin-bottom: 3px !important;
                white-space: nowrap !important;
            }
            
            .company-details {
                font-size: 9px !important;
                white-space: nowrap !important;
            }
        }
        
        @page {
            margin: 10mm 15mm;
            size: A4;
        }
        
        <?php if (!$print_mode): ?>
        .main-content {
            padding: 20px;
        }
        <?php endif; ?>
    </style>
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
    <div class="payslip-container print-container">
        <!-- Header Section -->
        <div class="payslip-header">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <img src="../../assets/img/logo.png" alt="Company Logo" class="company-logo" onerror="this.style.display='none'">
                </div>
                <div class="col-md-9 text-end">
                    <div class="payslip-title">SALARY SLIP</div>
                    <div class="company-details">
                        <strong><?= htmlspecialchars($companyName) ?></strong> | 
                        <?= htmlspecialchars($companyAddress) ?> | 
                        Phone: <?= htmlspecialchars($companyPhone) ?> | Email: <?= htmlspecialchars($companyEmail) ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Employee Details Section -->
        <div class="payslip-details">
            <div class="employee-details">
                <div class="row">
                    <div class="col-md-12">
                        <h5 class="mb-2"><i class="bi bi-person-circle text-primary" style="font-size: 0.9rem; margin-right: 0.4rem;"></i> Employee Information</h5>
                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
                            <span><strong>Employee ID:</strong> <?= htmlspecialchars($employee['employee_id'] ?? 'N/A') ?></span>
                            <span><strong>Name:</strong> <?= htmlspecialchars($employee['name'] ?? 'N/A') ?></span>
                            <span><strong>Department:</strong> <?= htmlspecialchars($employee['department'] ?? 'N/A') ?></span>
                            <span><strong>Designation:</strong> <?= htmlspecialchars($employee['designation'] ?? 'N/A') ?></span>
                        </div>
                        <h5 class="mb-2"><i class="bi bi-calendar-event text-primary" style="font-size: 0.9rem; margin-right: 0.4rem;"></i> Pay Period</h5>
                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
                            <span><strong>Month:</strong> <?= strtoupper(date('F Y', mktime(0, 0, 0, $monthNum, 1, $year))) ?></span>
                            <span><strong>Pay Date:</strong> <?= date('d M Y') ?></span>
                            <span><strong>Bank Account:</strong> <?= htmlspecialchars($employee['bank_account'] ?? 'N/A') ?></span>
                            <span><strong>PAN:</strong> <?= htmlspecialchars($employee['pan_number'] ?? 'N/A') ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Attendance Summary Cards -->
            <div class="attendance-cards">
                <h5 class="mb-2"><i class="bi bi-clock-history text-primary" style="font-size: 0.9rem; margin-right: 0.4rem;"></i> Attendance Summary</h5>
                <div class="row g-2">
                    <div class="col-md-3">
                        <div class="card border-primary">
                            <div class="card-body text-center p-2">
                                <i class="bi bi-calendar-check-fill text-primary" style="font-size: 1.2rem;"></i>
                                <h5 class="mt-1 mb-0 text-primary"><?= $presentDays ?></h5>
                                <small class="text-muted">Days Present</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-danger">
                            <div class="card-body text-center p-2">
                                <i class="bi bi-calendar-x-fill text-danger" style="font-size: 1.2rem;"></i>
                                <h5 class="mt-1 mb-0 text-danger"><?= $absentDays ?></h5>
                                <small class="text-muted">Days Absent</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-info">
                            <div class="card-body text-center p-2">
                                <i class="bi bi-clock-fill text-info" style="font-size: 1.2rem;"></i>
                                <h5 class="mt-1 mb-0 text-info"><?= number_format($overtimeHours, 1) ?>h</h5>
                                <small class="text-muted">Overtime Hours</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-warning">
                            <div class="card-body text-center p-2">
                                <i class="bi bi-currency-rupee text-warning" style="font-size: 1.2rem;"></i>
                                <h5 class="mt-1 mb-0 text-warning"><?= number_format($perDaySalary, 2) ?></h5>
                                <small class="text-muted">Per Day Salary</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Salary Breakdown -->
            <div class="salary-table">
                <h5 class="mb-2"><i class="bi bi-currency-rupee text-primary" style="font-size: 0.9rem; margin-right: 0.4rem;"></i> Salary Breakdown</h5>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <colgroup>
                            <col style="width: 35%;">
                            <col style="width: 15%;">
                            <col style="width: 35%;">
                            <col style="width: 15%;">
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="item-name">EARNINGS</th>
                                <th>AMOUNT (₹)</th>
                                <th class="item-name">DEDUCTIONS</th>
                                <th>AMOUNT (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="item-name">Basic Salary</td>
                                <td><?= number_format($earnedBasic, 2) ?></td>
                                <td class="item-name">Provident Fund (PF)</td>
                                <td><?= number_format($pf, 2) ?></td>
                            </tr>
                            <tr>
                                <td class="item-name">House Rent Allowance</td>
                                <td><?= number_format($earnedHRA, 2) ?></td>
                                <td class="item-name">Professional Tax</td>
                                <td><?= number_format($professionalTax, 2) ?></td>
                            </tr>
                            <tr>
                                <td class="item-name">Other Allowances</td>
                                <td><?= number_format($earnedAllowances, 2) ?></td>
                                <td class="item-name">Income Tax</td>
                                <td><?= number_format($incomeTax, 2) ?></td>
                            </tr>
                            <tr>
                                <td class="item-name">Overtime Pay</td>
                                <td><?= number_format($overtimePay, 2) ?></td>
                                <td class="item-name">Absent Days Deduction</td>
                                <td><?= number_format($absentDeduction, 2) ?></td>
                            </tr>
                            <tr style="background-color: #f8f9fa; font-weight: bold;">
                                <td class="item-name">GROSS EARNINGS</td>
                                <td><?= number_format($grossEarnings, 2) ?></td>
                                <td class="item-name">TOTAL DEDUCTIONS</td>
                                <td><?= number_format($totalDeductions, 2) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Net Salary Section -->
            <div class="total-section">
                <div class="row">
                    <div class="col-md-8">
                        <h5 class="text-primary mb-2">Net Salary</h5>
                        <div class="amount-words">
                            <strong>In Words:</strong> <?= ucwords(convertNumberToWords($netSalary)) ?> Rupees Only
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <h5 class="text-primary">Net Amount</h5>
                        <h3 class="text-success mb-0">₹ <?= number_format($netSalary, 2) ?></h3>
                        <small class="text-muted">(Gross Earnings - Total Deductions)</small>
                    </div>
                </div>
            </div>

            <!-- Footer Section -->
            <div class="footer-section">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary mb-2">Terms & Conditions:</h6>
                        <ul class="list-unstyled small">
                            <li>• This is a computer-generated payslip and does not require a signature.</li>
                            <li>• PF and ESI deductions are as per government regulations.</li>
                            <li>• Overtime is calculated at 1.5x the hourly rate for hours worked beyond 8 hours per day.</li>
                            <li>• Professional Tax is applicable for gross salary above ₹10,000 per month.</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <div class="signature-section">
                            <div class="row text-center">
                                <div class="col-6">
                                    <div style="border-top: 1px solid #000; margin-top: 60px; padding-top: 8px;">
                                        <strong>Employee Signature</strong>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div style="border-top: 1px solid #000; margin-top: 60px; padding-top: 8px;">
                                        <strong>Authorized Signatory</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Generation Info -->
                <div class="text-center mt-4 pt-3" style="border-top: 1px solid #dee2e6;">
                    <small class="text-muted">
                        Generated on <?= date('d M Y, h:i A') ?> | 
                        This is a system generated document
                    </small>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$print_mode): ?>
            </div>
        </div>
    </div>
    
    <!-- Print Button (Only visible on screen) -->
    <button onclick="window.print()" class="btn btn-primary print-button no-print">
        <i class="bi bi-printer"></i> Print Payslip
    </button>
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
        
        // Improve print layout
        function preparePrint() {
            // Ensure all images are loaded before printing
            const images = document.querySelectorAll('img');
            let loadedImages = 0;
            
            if (images.length === 0) {
                window.print();
                return;
            }
            
            images.forEach(img => {
                if (img.complete) {
                    loadedImages++;
                } else {
                    img.onload = img.onerror = () => {
                        loadedImages++;
                        if (loadedImages === images.length) {
                            setTimeout(() => window.print(), 500);
                        }
                    };
                }
            });
            
            if (loadedImages === images.length) {
                setTimeout(() => window.print(), 500);
            }
        }
        
        // Auto-print if in print mode
        <?php if ($print_mode): ?>
        window.onload = function() {
            preparePrint();
        };
        <?php endif; ?>
        
        // Override print button to use improved function
        <?php if (!$print_mode): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const printButtons = document.querySelectorAll('[onclick*="window.print"]');
            printButtons.forEach(btn => {
                btn.onclick = preparePrint;
            });
        });
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