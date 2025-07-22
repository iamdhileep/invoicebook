<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';
$page_title = 'Advanced Payroll Calculator';

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Get current month and year
$currentMonth = $_GET['month'] ?? date('m');
$currentYear = $_GET['year'] ?? date('Y');
$monthYear = $currentYear . '-' . str_pad($currentMonth, 2, '0', STR_PAD_LEFT);

// Get number of days in the month
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);

// Payroll configuration
$payrollConfig = [
    'pf_rate' => 12, // PF percentage
    'esi_rate' => 1.75, // ESI percentage
    'tax_slab_1' => 250000, // Annual income up to 2.5L - no tax
    'tax_slab_2' => 500000, // 2.5L to 5L - 5%
    'tax_slab_3' => 1000000, // 5L to 10L - 20%
    'tax_rate_1' => 0,
    'tax_rate_2' => 5,
    'tax_rate_3' => 20,
    'tax_rate_4' => 30,
    'hra_exemption' => 40, // HRA exemption percentage
    'overtime_multiplier' => 1.5, // Overtime rate multiplier
    'bonus_eligibility_months' => 6, // Minimum months for bonus eligibility
    'bonus_percentage' => 8.33, // Annual bonus percentage
];

// Get employees with salary details
$employees = $conn->query("
    SELECT 
        employee_id, 
        name, 
        employee_code, 
        position, 
        monthly_salary,
        phone,
        created_at
    FROM employees 
    ORDER BY name ASC
");

// Function to get attendance details
function getAttendanceDetails($conn, $empId, $month, $year) {
    $query = "
        SELECT 
            COUNT(CASE WHEN status = 'Present' THEN 1 END) as present_days,
            COUNT(CASE WHEN status = 'Absent' THEN 1 END) as absent_days,
            COUNT(CASE WHEN status = 'Late' THEN 1 END) as late_days,
            COUNT(CASE WHEN status = 'Half Day' THEN 1 END) as half_days,
            SUM(CASE 
                WHEN status IN ('Present', 'Late') AND time_in IS NOT NULL AND time_out IS NOT NULL 
                THEN TIME_TO_SEC(TIMEDIFF(time_out, time_in))/3600 
                ELSE 0 
            END) as total_hours,
            SUM(CASE 
                WHEN status IN ('Present', 'Late') AND time_in IS NOT NULL AND time_out IS NOT NULL 
                AND TIME_TO_SEC(TIMEDIFF(time_out, time_in))/3600 > 8
                THEN (TIME_TO_SEC(TIMEDIFF(time_out, time_in))/3600) - 8
                ELSE 0 
            END) as overtime_hours
        FROM attendance 
        WHERE employee_id = ? AND MONTH(attendance_date) = ? AND YEAR(attendance_date) = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $empId, $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Function to calculate tax
function calculateIncomeTax($annualIncome, $config) {
    $tax = 0;
    
    if ($annualIncome <= $config['tax_slab_1']) {
        $tax = 0;
    } elseif ($annualIncome <= $config['tax_slab_2']) {
        $tax = ($annualIncome - $config['tax_slab_1']) * $config['tax_rate_2'] / 100;
    } elseif ($annualIncome <= $config['tax_slab_3']) {
        $tax = ($config['tax_slab_2'] - $config['tax_slab_1']) * $config['tax_rate_2'] / 100;
        $tax += ($annualIncome - $config['tax_slab_2']) * $config['tax_rate_3'] / 100;
    } else {
        $tax = ($config['tax_slab_2'] - $config['tax_slab_1']) * $config['tax_rate_2'] / 100;
        $tax += ($config['tax_slab_3'] - $config['tax_slab_2']) * $config['tax_rate_3'] / 100;
        $tax += ($annualIncome - $config['tax_slab_3']) * $config['tax_rate_4'] / 100;
    }
    
    return $tax / 12; // Monthly tax
}

// Function to calculate comprehensive payroll
function calculatePayroll($emp, $attendance, $daysInMonth, $config) {
    $basic_salary = $emp['monthly_salary'];
    $daily_rate = $basic_salary / $daysInMonth;
    
    // Calculate working days
    $total_present = $attendance['present_days'] + $attendance['late_days'] + ($attendance['half_days'] * 0.5);
    $absent_days = $daysInMonth - ($attendance['present_days'] + $attendance['late_days'] + $attendance['half_days']);
    
    // Basic calculations
    $earned_basic = $total_present * $daily_rate;
    
    // Allowances (typically 40-60% of basic salary)
    $hra = $basic_salary * 0.40; // 40% HRA
    $da = $basic_salary * 0.10; // 10% Dearness Allowance
    $transport_allowance = 1600; // Fixed transport allowance
    $medical_allowance = 1250; // Fixed medical allowance
    $special_allowance = $basic_salary * 0.10; // 10% Special allowance
    
    // Calculate earned allowances based on attendance
    $earned_hra = ($total_present / $daysInMonth) * $hra;
    $earned_da = ($total_present / $daysInMonth) * $da;
    $earned_transport = ($total_present / $daysInMonth) * $transport_allowance;
    $earned_medical = ($total_present / $daysInMonth) * $medical_allowance;
    $earned_special = ($total_present / $daysInMonth) * $special_allowance;
    
    // Overtime calculation
    $overtime_hours = $attendance['overtime_hours'] ?? 0;
    $overtime_rate = ($basic_salary / ($daysInMonth * 8)) * $config['overtime_multiplier'];
    $overtime_amount = $overtime_hours * $overtime_rate;
    
    // Gross salary
    $gross_salary = $earned_basic + $earned_hra + $earned_da + $earned_transport + $earned_medical + $earned_special + $overtime_amount;
    
    // Deductions
    $pf_deduction = ($earned_basic * $config['pf_rate']) / 100;
    $esi_deduction = ($gross_salary * $config['esi_rate']) / 100;
    
    // Professional tax (varies by state, example for Maharashtra)
    $professional_tax = 0;
    if ($gross_salary > 15000) {
        $professional_tax = 200;
    } elseif ($gross_salary > 10000) {
        $professional_tax = 150;
    }
    
    // Income tax calculation
    $annual_gross = $gross_salary * 12;
    $monthly_tax = calculateIncomeTax($annual_gross, $config);
    
    // Other deductions
    $loan_deduction = 0; // This could be fetched from a loans table
    $advance_deduction = 0; // This could be fetched from advances table
    
    // Total deductions
    $total_deductions = $pf_deduction + $esi_deduction + $professional_tax + $monthly_tax + $loan_deduction + $advance_deduction;
    
    // Net salary
    $net_salary = $gross_salary - $total_deductions;
    
    // Bonus calculation (if employee completed minimum months)
    $employment_months = 12; // This should be calculated from joining date
    $bonus_amount = 0;
    if ($employment_months >= $config['bonus_eligibility_months']) {
        $bonus_amount = ($basic_salary * $config['bonus_percentage']) / 100;
    }
    
    return [
        'basic_salary' => $basic_salary,
        'daily_rate' => $daily_rate,
        'total_present' => $total_present,
        'absent_days' => $absent_days,
        'earned_basic' => $earned_basic,
        'hra' => $earned_hra,
        'da' => $earned_da,
        'transport_allowance' => $earned_transport,
        'medical_allowance' => $earned_medical,
        'special_allowance' => $earned_special,
        'overtime_hours' => $overtime_hours,
        'overtime_amount' => $overtime_amount,
        'gross_salary' => $gross_salary,
        'pf_deduction' => $pf_deduction,
        'esi_deduction' => $esi_deduction,
        'professional_tax' => $professional_tax,
        'income_tax' => $monthly_tax,
        'loan_deduction' => $loan_deduction,
        'advance_deduction' => $advance_deduction,
        'total_deductions' => $total_deductions,
        'net_salary' => $net_salary,
        'bonus_amount' => $bonus_amount,
        'total_hours' => $attendance['total_hours'] ?? 0
    ];
}

include 'layouts/header.php';
include 'layouts/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Advanced Payroll Calculator</h1>
            <p class="text-muted">Comprehensive salary calculations with deductions, allowances, and overtime for <?= date('F Y', mktime(0, 0, 0, $currentMonth, 1, $currentYear)) ?></p>
        </div>
        <div>
            <button class="btn btn-outline-success" onclick="exportAdvancedPayroll()">
                <i class="bi bi-download"></i> Export Detailed Report
            </button>
            <button class="btn btn-outline-primary" onclick="printPayslips()">
                <i class="bi bi-printer"></i> Print Payslips
            </button>
        </div>
    </div>

    <!-- Payroll Configuration -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-gear me-2"></i>Payroll Configuration</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Month/Year</label>
                    <form method="GET" class="d-flex gap-2">
                        <select name="month" class="form-select form-select-sm">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= $m == $currentMonth ? 'selected' : '' ?>>
                                    <?= date('M', mktime(0, 0, 0, $m, 1)) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        <select name="year" class="form-select form-select-sm">
                            <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                                <option value="<?= $y ?>" <?= $y == $currentYear ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm">Update</button>
                    </form>
                </div>
                <div class="col-md-2">
                    <label class="form-label">PF Rate</label>
                    <div class="input-group input-group-sm">
                        <input type="number" class="form-control" value="<?= $payrollConfig['pf_rate'] ?>" readonly>
                        <span class="input-group-text">%</span>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">ESI Rate</label>
                    <div class="input-group input-group-sm">
                        <input type="number" class="form-control" value="<?= $payrollConfig['esi_rate'] ?>" readonly>
                        <span class="input-group-text">%</span>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Overtime Rate</label>
                    <div class="input-group input-group-sm">
                        <input type="number" class="form-control" value="<?= $payrollConfig['overtime_multiplier'] ?>" readonly>
                        <span class="input-group-text">x</span>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Working Days</label>
                    <input type="number" class="form-control form-control-sm" value="<?= $daysInMonth ?>" readonly>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Bonus Rate</label>
                    <div class="input-group input-group-sm">
                        <input type="number" class="form-control" value="<?= $payrollConfig['bonus_percentage'] ?>" readonly>
                        <span class="input-group-text">%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Statistics -->
    <?php
    $totalGrossSalary = 0;
    $totalNetSalary = 0;
    $totalDeductions = 0;
    $totalOvertimeHours = 0;
    $employeeCount = 0;
    
    $payrollData = [];
    if ($employees && $employees->num_rows > 0) {
        $employees->data_seek(0);
        while ($emp = $employees->fetch_assoc()) {
            $attendance = getAttendanceDetails($conn, $emp['employee_id'], $currentMonth, $currentYear);
            $payroll = calculatePayroll($emp, $attendance, $daysInMonth, $payrollConfig);
            $payroll['employee'] = $emp;
            $payroll['attendance'] = $attendance;
            $payrollData[] = $payroll;
            
            $totalGrossSalary += $payroll['gross_salary'];
            $totalNetSalary += $payroll['net_salary'];
            $totalDeductions += $payroll['total_deductions'];
            $totalOvertimeHours += $payroll['overtime_hours'];
            $employeeCount++;
        }
    }
    ?>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Gross</h6>
                            <h4 class="mb-0">₹<?= number_format($totalGrossSalary, 0) ?></h4>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-currency-rupee"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Net</h6>
                            <h4 class="mb-0">₹<?= number_format($totalNetSalary, 0) ?></h4>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-wallet"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Deductions</h6>
                            <h4 class="mb-0">₹<?= number_format($totalDeductions, 0) ?></h4>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-dash-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Overtime Hours</h6>
                            <h4 class="mb-0"><?= number_format($totalOvertimeHours, 1) ?>h</h4>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-clock"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Payroll Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Detailed Payroll Calculations</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($payrollData)): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-sm" id="advancedPayrollTable">
                        <thead class="table-dark">
                            <tr>
                                <th rowspan="2">Employee</th>
                                <th rowspan="2">Days</th>
                                <th colspan="6" class="text-center">Earnings</th>
                                <th colspan="6" class="text-center">Deductions</th>
                                <th rowspan="2">Net Pay</th>
                                <th rowspan="2">Actions</th>
                            </tr>
                            <tr>
                                <th>Basic</th>
                                <th>HRA</th>
                                <th>DA</th>
                                <th>Allowances</th>
                                <th>Overtime</th>
                                <th>Gross</th>
                                <th>PF</th>
                                <th>ESI</th>
                                <th>Tax</th>
                                <th>PT</th>
                                <th>Others</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payrollData as $data): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($data['employee']['name']) ?></strong>
                                            <br><small class="text-muted"><?= htmlspecialchars($data['employee']['employee_code']) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-success"><?= number_format($data['total_present'], 1) ?></span>
                                        <br><small class="text-muted"><?= $data['absent_days'] ?> abs</small>
                                    </td>
                                    <td>₹<?= number_format($data['earned_basic'], 0) ?></td>
                                    <td>₹<?= number_format($data['hra'], 0) ?></td>
                                    <td>₹<?= number_format($data['da'], 0) ?></td>
                                    <td>₹<?= number_format($data['transport_allowance'] + $data['medical_allowance'] + $data['special_allowance'], 0) ?></td>
                                    <td>
                                        <?php if ($data['overtime_hours'] > 0): ?>
                                            ₹<?= number_format($data['overtime_amount'], 0) ?>
                                            <br><small class="text-info"><?= number_format($data['overtime_hours'], 1) ?>h</small>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><strong class="text-success">₹<?= number_format($data['gross_salary'], 0) ?></strong></td>
                                    <td>₹<?= number_format($data['pf_deduction'], 0) ?></td>
                                    <td>₹<?= number_format($data['esi_deduction'], 0) ?></td>
                                    <td>₹<?= number_format($data['income_tax'], 0) ?></td>
                                    <td>₹<?= number_format($data['professional_tax'], 0) ?></td>
                                    <td>₹<?= number_format($data['loan_deduction'] + $data['advance_deduction'], 0) ?></td>
                                    <td><strong class="text-danger">₹<?= number_format($data['total_deductions'], 0) ?></strong></td>
                                    <td><strong class="text-primary">₹<?= number_format($data['net_salary'], 0) ?></strong></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="viewPayslip(<?= $data['employee']['employee_id'] ?>)" 
                                                    data-bs-toggle="tooltip" title="View Payslip">
                                                <i class="bi bi-receipt"></i>
                                            </button>
                                            <button class="btn btn-outline-success" onclick="downloadPayslip(<?= $data['employee']['employee_id'] ?>)" 
                                                    data-bs-toggle="tooltip" title="Download PDF">
                                                <i class="bi bi-download"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-secondary">
                            <tr>
                                <th colspan="7">TOTALS</th>
                                <th>₹<?= number_format($totalGrossSalary, 0) ?></th>
                                <th colspan="5"></th>
                                <th>₹<?= number_format($totalDeductions, 0) ?></th>
                                <th>₹<?= number_format($totalNetSalary, 0) ?></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-people fs-1 text-muted mb-3"></i>
                    <h5 class="text-muted">No employees found</h5>
                    <p class="text-muted">Add employees to generate advanced payroll calculations.</p>
                    <a href="pages/employees/employees.php" class="btn btn-primary">
                        <i class="bi bi-person-plus"></i> Add Employees
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Payslip Modal -->
<div class="modal fade" id="payslipModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Employee Payslip</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="payslipContent">
                <!-- Payslip content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="window.print()">Print</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#advancedPayrollTable').DataTable({
        pageLength: 25,
        responsive: true,
        scrollX: true,
        order: [[0, "asc"]],
        columnDefs: [
            { orderable: false, targets: [-1] }
        ]
    });

    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
});

function exportAdvancedPayroll() {
    const month = <?= $currentMonth ?>;
    const year = <?= $currentYear ?>;
    window.open(`export_advanced_payroll.php?month=${month}&year=${year}`, '_blank');
}

function printPayslips() {
    window.print();
}

function viewPayslip(employeeId) {
    // Load payslip content via AJAX
    document.getElementById('payslipContent').innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading payslip...</p>
        </div>
    `;
    
    new bootstrap.Modal(document.getElementById('payslipModal')).show();
    
    // Simulate loading (replace with actual AJAX call)
    setTimeout(() => {
        document.getElementById('payslipContent').innerHTML = `
            <div class="payslip">
                <div class="text-center mb-4">
                    <h4>Company Name</h4>
                    <p>Payslip for <?= date('F Y', mktime(0, 0, 0, $currentMonth, 1, $currentYear)) ?></p>
                </div>
                <div class="row">
                    <div class="col-6">
                        <strong>Employee ID:</strong> EMP${employeeId}<br>
                        <strong>Name:</strong> Employee Name<br>
                        <strong>Position:</strong> Position<br>
                    </div>
                    <div class="col-6">
                        <strong>Pay Period:</strong> <?= date('F Y', mktime(0, 0, 0, $currentMonth, 1, $currentYear)) ?><br>
                        <strong>Working Days:</strong> <?= $daysInMonth ?><br>
                        <strong>Present Days:</strong> XX<br>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-6">
                        <h6>Earnings</h6>
                        <table class="table table-sm">
                            <tr><td>Basic Salary</td><td class="text-end">₹XX,XXX</td></tr>
                            <tr><td>HRA</td><td class="text-end">₹X,XXX</td></tr>
                            <tr><td>DA</td><td class="text-end">₹X,XXX</td></tr>
                            <tr><td>Allowances</td><td class="text-end">₹X,XXX</td></tr>
                            <tr><td>Overtime</td><td class="text-end">₹XXX</td></tr>
                            <tr class="table-success"><td><strong>Gross Salary</strong></td><td class="text-end"><strong>₹XX,XXX</strong></td></tr>
                        </table>
                    </div>
                    <div class="col-6">
                        <h6>Deductions</h6>
                        <table class="table table-sm">
                            <tr><td>PF</td><td class="text-end">₹X,XXX</td></tr>
                            <tr><td>ESI</td><td class="text-end">₹XXX</td></tr>
                            <tr><td>Income Tax</td><td class="text-end">₹XXX</td></tr>
                            <tr><td>Professional Tax</td><td class="text-end">₹XXX</td></tr>
                            <tr><td>Others</td><td class="text-end">₹XXX</td></tr>
                            <tr class="table-danger"><td><strong>Total Deductions</strong></td><td class="text-end"><strong>₹X,XXX</strong></td></tr>
                        </table>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-12">
                        <h5 class="text-center">Net Pay: <span class="text-success">₹XX,XXX</span></h5>
                    </div>
                </div>
            </div>
        `;
    }, 1000);
}

function downloadPayslip(employeeId) {
    const month = <?= $currentMonth ?>;
    const year = <?= $currentYear ?>;
    window.open(`generate_payslip_pdf.php?employee_id=${employeeId}&month=${month}&year=${year}`, '_blank');
}
</script>

<style>
.table th, .table td {
    font-size: 0.85rem;
    padding: 0.4rem;
}

.table-responsive {
    font-size: 0.9rem;
}

@media print {
    .btn, .card-header, .sidebar, .main-header {
        display: none !important;
    }
    .main-content {
        margin-left: 0 !important;
        margin-top: 0 !important;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
    }
}
</style>

<?php include 'layouts/footer.php'; ?>