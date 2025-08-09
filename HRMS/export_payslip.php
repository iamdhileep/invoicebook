<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

include '../db.php';

$payroll_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$payroll_id) {
    die('Invalid payroll ID');
}

// Get payroll data
$query = mysqli_query($conn, "
    SELECT p.*, 
           CONCAT(e.first_name, ' ', e.last_name) as employee_name,
           e.employee_id as emp_id,
           e.position,
           e.phone,
           e.email,
           d.department_name
    FROM hr_payroll p
    JOIN hr_employees e ON p.employee_id = e.id
    LEFT JOIN hr_departments d ON e.department_id = d.id
    WHERE p.id = $payroll_id
");

if (!$query || !($payroll = mysqli_fetch_assoc($query))) {
    die('Payroll record not found');
}

$period = date('F Y', mktime(0, 0, 0, $payroll['payroll_month'], 1, $payroll['payroll_year']));
$total_deductions = $payroll['pf_deduction'] + $payroll['esi_deduction'] + $payroll['tax_deduction'] + $payroll['deductions'];

// Set headers for PDF download
header('Content-Type: text/html');
header('Content-Disposition: inline; filename="payslip_' . $payroll['emp_id'] . '_' . $payroll['payroll_month'] . '_' . $payroll['payroll_year'] . '.html"');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payslip - <?= $payroll['employee_name'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
        }
        .payslip-header {
            text-align: center;
            border-bottom: 3px solid #000;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .company-info {
            text-align: center;
            margin-bottom: 15px;
        }
        .employee-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .earnings, .deductions {
            margin-bottom: 20px;
        }
        .net-salary {
            background-color: #e3f2fd;
            padding: 15px;
            text-align: center;
            border: 2px solid #2196f3;
            border-radius: 5px;
        }
        table {
            font-size: 11px;
        }
        .table th {
            background-color: #f1f3f4;
            font-weight: bold;
        }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Company Header -->
        <div class="company-info">
            <h3>YOUR COMPANY NAME</h3>
            <p>Address Line 1, Address Line 2<br>
               Phone: +91 XXXXXXXXXX | Email: info@company.com</p>
        </div>

        <!-- Payslip Header -->
        <div class="payslip-header">
            <h2>SALARY SLIP</h2>
            <h4>Pay Period: <?= $period ?></h4>
        </div>

        <!-- Employee Information -->
        <div class="employee-info">
            <div class="row">
                <div class="col-md-6">
                    <strong>Employee Name:</strong> <?= htmlspecialchars($payroll['employee_name']) ?><br>
                    <strong>Employee ID:</strong> <?= htmlspecialchars($payroll['emp_id']) ?><br>
                    <strong>Department:</strong> <?= htmlspecialchars($payroll['department_name'] ?? 'N/A') ?><br>
                    <strong>Position:</strong> <?= htmlspecialchars($payroll['position'] ?? 'N/A') ?>
                </div>
                <div class="col-md-6">
                    <strong>Pay Period:</strong> <?= $period ?><br>
                    <strong>Working Days:</strong> <?= $payroll['working_days'] ?><br>
                    <strong>Present Days:</strong> <?= $payroll['present_days'] ?><br>
                    <strong>Absent Days:</strong> <?= $payroll['absent_days'] ?>
                </div>
            </div>
        </div>

        <!-- Earnings and Deductions -->
        <div class="row">
            <div class="col-md-6">
                <div class="earnings">
                    <h5 class="text-success">EARNINGS</h5>
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th class="text-end">Amount (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Basic Salary</td>
                                <td class="text-end"><?= number_format($payroll['basic_salary'], 2) ?></td>
                            </tr>
                            <tr>
                                <td>Allowances</td>
                                <td class="text-end"><?= number_format($payroll['allowances'], 2) ?></td>
                            </tr>
                            <?php if ($payroll['overtime_hours'] > 0): ?>
                            <tr>
                                <td>Overtime (<?= $payroll['overtime_hours'] ?> hrs)</td>
                                <td class="text-end"><?= number_format($payroll['overtime_amount'], 2) ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($payroll['bonus'] > 0): ?>
                            <tr>
                                <td>Bonus</td>
                                <td class="text-end"><?= number_format($payroll['bonus'], 2) ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr class="table-success">
                                <td><strong>GROSS SALARY</strong></td>
                                <td class="text-end"><strong><?= number_format($payroll['gross_salary'], 2) ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="deductions">
                    <h5 class="text-danger">DEDUCTIONS</h5>
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th class="text-end">Amount (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>PF Deduction</td>
                                <td class="text-end"><?= number_format($payroll['pf_deduction'], 2) ?></td>
                            </tr>
                            <tr>
                                <td>ESI Deduction</td>
                                <td class="text-end"><?= number_format($payroll['esi_deduction'], 2) ?></td>
                            </tr>
                            <tr>
                                <td>Tax Deduction</td>
                                <td class="text-end"><?= number_format($payroll['tax_deduction'], 2) ?></td>
                            </tr>
                            <?php if ($payroll['deductions'] > 0): ?>
                            <tr>
                                <td>Other Deductions</td>
                                <td class="text-end"><?= number_format($payroll['deductions'], 2) ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr class="table-danger">
                                <td><strong>TOTAL DEDUCTIONS</strong></td>
                                <td class="text-end"><strong><?= number_format($total_deductions, 2) ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Net Salary -->
        <div class="net-salary">
            <h4><strong>NET SALARY: ₹<?= number_format($payroll['net_salary'], 2) ?></strong></h4>
            <p class="mb-0"><em>Amount in words: <?= ucwords(numberToWords($payroll['net_salary'])) ?> Rupees Only</em></p>
        </div>

        <!-- Footer -->
        <div class="row mt-4">
            <div class="col-md-6">
                <p><strong>Generated on:</strong> <?= date('d M Y, h:i A') ?></p>
                <p><small>This is a computer-generated payslip and does not require a signature.</small></p>
            </div>
            <div class="col-md-6 text-end">
                <br><br>
                <p>_________________________<br>
                   <strong>Authorized Signatory</strong></p>
            </div>
        </div>
    </div>

    <!-- Print Button -->
    <div class="text-center mt-4 no-print">
        <button class="btn btn-primary" onclick="window.print()">
            <i class="bi bi-printer"></i> Print Payslip
        </button>
        <button class="btn btn-secondary" onclick="window.close()">
            Close
        </button>
    </div>
</body>
</html>

<?php
function numberToWords($number) {
    $ones = [
        0 => '', 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five',
        6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine', 10 => 'ten',
        11 => 'eleven', 12 => 'twelve', 13 => 'thirteen', 14 => 'fourteen', 15 => 'fifteen',
        16 => 'sixteen', 17 => 'seventeen', 18 => 'eighteen', 19 => 'nineteen'
    ];
    
    $tens = [
        0 => '', 2 => 'twenty', 3 => 'thirty', 4 => 'forty', 5 => 'fifty',
        6 => 'sixty', 7 => 'seventy', 8 => 'eighty', 9 => 'ninety'
    ];
    
    if ($number < 20) {
        return $ones[$number];
    } elseif ($number < 100) {
        return $tens[intval($number / 10)] . ' ' . $ones[$number % 10];
    } elseif ($number < 1000) {
        return $ones[intval($number / 100)] . ' hundred ' . numberToWords($number % 100);
    } elseif ($number < 100000) {
        return numberToWords(intval($number / 1000)) . ' thousand ' . numberToWords($number % 1000);
    } elseif ($number < 10000000) {
        return numberToWords(intval($number / 100000)) . ' lakh ' . numberToWords($number % 100000);
    } else {
        return numberToWords(intval($number / 10000000)) . ' crore ' . numberToWords($number % 10000000);
    }
}
?>
