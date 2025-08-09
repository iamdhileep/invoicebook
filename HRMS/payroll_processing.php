<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

include '../db.php';
$page_title = 'Payroll Processing - HRMS';

// Create payroll tables if they don't exist
$createPayrollTable = "
CREATE TABLE IF NOT EXISTS hr_payroll (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    payroll_month INT NOT NULL,
    payroll_year INT NOT NULL,
    basic_salary DECIMAL(10,2) NOT NULL,
    gross_salary DECIMAL(10,2) NOT NULL,
    working_days INT NOT NULL,
    present_days INT NOT NULL,
    absent_days INT NOT NULL,
    overtime_hours DECIMAL(5,2) DEFAULT 0,
    overtime_amount DECIMAL(10,2) DEFAULT 0,
    bonus DECIMAL(10,2) DEFAULT 0,
    allowances DECIMAL(10,2) DEFAULT 0,
    deductions DECIMAL(10,2) DEFAULT 0,
    pf_deduction DECIMAL(10,2) DEFAULT 0,
    esi_deduction DECIMAL(10,2) DEFAULT 0,
    tax_deduction DECIMAL(10,2) DEFAULT 0,
    net_salary DECIMAL(10,2) NOT NULL,
    status ENUM('draft', 'processed', 'paid') DEFAULT 'draft',
    processed_by INT DEFAULT NULL,
    processed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_employee_month_year (employee_id, payroll_month, payroll_year),
    FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE
) ENGINE=InnoDB";
mysqli_query($conn, $createPayrollTable);

$createSalaryComponentsTable = "
CREATE TABLE IF NOT EXISTS hr_salary_components (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    basic_salary DECIMAL(10,2) NOT NULL,
    hra DECIMAL(10,2) DEFAULT 0,
    transport_allowance DECIMAL(10,2) DEFAULT 0,
    medical_allowance DECIMAL(10,2) DEFAULT 0,
    special_allowance DECIMAL(10,2) DEFAULT 0,
    other_allowances DECIMAL(10,2) DEFAULT 0,
    pf_deduction DECIMAL(10,2) DEFAULT 0,
    esi_deduction DECIMAL(10,2) DEFAULT 0,
    professional_tax DECIMAL(10,2) DEFAULT 0,
    income_tax DECIMAL(10,2) DEFAULT 0,
    other_deductions DECIMAL(10,2) DEFAULT 0,
    effective_from DATE NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE
) ENGINE=InnoDB";
mysqli_query($conn, $createSalaryComponentsTable);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'process_payroll':
            $month = intval($_POST['month']);
            $year = intval($_POST['year']);
            $processed_by = $_SESSION['user_id'] ?? 1;
            
            // Get all active employees with salary info
            $employees = mysqli_query($conn, "
                SELECT e.*, 
                       COALESCE(sc.basic_salary, e.salary, 0) as basic_salary,
                       COALESCE(sc.hra, 0) as hra,
                       COALESCE(sc.transport_allowance, 0) as transport_allowance,
                       COALESCE(sc.medical_allowance, 0) as medical_allowance,
                       COALESCE(sc.special_allowance, 0) as special_allowance,
                       COALESCE(sc.other_allowances, 0) as other_allowances,
                       COALESCE(sc.pf_deduction, 0) as pf_deduction_rate,
                       COALESCE(sc.esi_deduction, 0) as esi_deduction_rate,
                       COALESCE(sc.professional_tax, 0) as professional_tax,
                       COALESCE(sc.income_tax, 0) as income_tax,
                       COALESCE(sc.other_deductions, 0) as other_deductions
                FROM hr_employees e
                LEFT JOIN hr_salary_components sc ON e.id = sc.employee_id AND sc.status = 'active'
                WHERE e.status = 'active'
            ");
            
            $processed_count = 0;
            $total_amount = 0;
            
            while ($employee = mysqli_fetch_assoc($employees)) {
                $employee_id = $employee['id'];
                $basic_salary = $employee['basic_salary'] ?: 25000; // Default if not set
                
                // Calculate working days for the month (excluding Sundays)
                $working_days = 0;
                $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                for ($day = 1; $day <= $days_in_month; $day++) {
                    $date = date('N', mktime(0, 0, 0, $month, $day, $year));
                    if ($date != 7) { // Exclude Sundays
                        $working_days++;
                    }
                }
                
                // Get attendance data for the month
                $attendanceQuery = "
                    SELECT 
                        COUNT(*) as total_days,
                        SUM(CASE WHEN status IN ('Present', 'Late', 'Work From Home') THEN 1 ELSE 0 END) as present_days,
                        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
                        SUM(COALESCE(overtime_hours, 0)) as total_overtime
                    FROM hr_attendance 
                    WHERE employee_id = $employee_id 
                    AND MONTH(attendance_date) = $month 
                    AND YEAR(attendance_date) = $year
                ";
                
                $attendanceResult = mysqli_query($conn, $attendanceQuery);
                $attendance = mysqli_fetch_assoc($attendanceResult);
                
                $present_days = $attendance['present_days'] ?: $working_days; // Default to full attendance if no records
                $absent_days = $working_days - $present_days;
                $overtime_hours = $attendance['total_overtime'] ?: 0;
                
                // Calculate salary components
                $per_day_salary = $basic_salary / $working_days;
                $earned_basic = $per_day_salary * $present_days;
                
                // Allowances (prorated)
                $hra = ($employee['hra'] ?: ($basic_salary * 0.40)) * ($present_days / $working_days);
                $transport_allowance = ($employee['transport_allowance'] ?: 2000) * ($present_days / $working_days);
                $medical_allowance = ($employee['medical_allowance'] ?: 1500) * ($present_days / $working_days);
                $special_allowance = ($employee['special_allowance'] ?: ($basic_salary * 0.10)) * ($present_days / $working_days);
                $other_allowances = $employee['other_allowances'] ?: 0;
                
                // Overtime calculation (1.5x rate)
                $hourly_rate = $basic_salary / ($working_days * 8);
                $overtime_amount = $hourly_rate * $overtime_hours * 1.5;
                
                // Bonus (can be manually set)
                $bonus = 0;
                
                $gross_salary = $earned_basic + $hra + $transport_allowance + $medical_allowance + 
                               $special_allowance + $other_allowances + $overtime_amount + $bonus;
                
                // Calculate deductions
                $pf_deduction = $employee['pf_deduction_rate'] ?: ($basic_salary * 0.12);
                $esi_deduction = $employee['esi_deduction_rate'] ?: (($gross_salary <= 21000) ? ($gross_salary * 0.0075) : 0);
                $professional_tax = $employee['professional_tax'] ?: 200;
                $income_tax = $employee['income_tax'] ?: 0;
                $other_deductions_amount = $employee['other_deductions'] ?: 0;
                
                $total_deductions = $pf_deduction + $esi_deduction + $professional_tax + $income_tax + $other_deductions_amount;
                $net_salary = $gross_salary - $total_deductions;
                
                // Insert or update payroll record
                $payrollQuery = "
                    INSERT INTO hr_payroll (
                        employee_id, payroll_month, payroll_year, basic_salary, gross_salary,
                        working_days, present_days, absent_days, overtime_hours, overtime_amount,
                        bonus, allowances, deductions, pf_deduction, esi_deduction, tax_deduction,
                        net_salary, status, processed_by, processed_at
                    ) VALUES (
                        $employee_id, $month, $year, $basic_salary, $gross_salary,
                        $working_days, $present_days, $absent_days, $overtime_hours, $overtime_amount,
                        $bonus, $hra + $transport_allowance + $medical_allowance + $special_allowance + $other_allowances, 
                        $other_deductions_amount, $pf_deduction, $esi_deduction, $income_tax,
                        $net_salary, 'processed', $processed_by, NOW()
                    )
                    ON DUPLICATE KEY UPDATE
                        basic_salary = $basic_salary,
                        gross_salary = $gross_salary,
                        present_days = $present_days,
                        absent_days = $absent_days,
                        overtime_hours = $overtime_hours,
                        overtime_amount = $overtime_amount,
                        allowances = $hra + $transport_allowance + $medical_allowance + $special_allowance + $other_allowances,
                        pf_deduction = $pf_deduction,
                        esi_deduction = $esi_deduction,
                        tax_deduction = $income_tax,
                        deductions = $other_deductions_amount,
                        net_salary = $net_salary,
                        status = 'processed',
                        processed_by = $processed_by,
                        processed_at = NOW()
                ";
                
                if (mysqli_query($conn, $payrollQuery)) {
                    $processed_count++;
                    $total_amount += $net_salary;
                }
            }
            
            echo json_encode([
                'success' => true, 
                'message' => "Payroll processed successfully for $processed_count employees! Total amount: ₹" . number_format($total_amount, 2)
            ]);
            exit;
            
        case 'mark_paid':
            $payroll_id = intval($_POST['payroll_id']);
            $query = "UPDATE hr_payroll SET status = 'paid' WHERE id = $payroll_id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Payroll marked as paid successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;
            
        case 'update_salary_component':
            $employee_id = intval($_POST['employee_id']);
            $basic_salary = floatval($_POST['basic_salary']);
            $hra = floatval($_POST['hra']);
            $transport_allowance = floatval($_POST['transport_allowance']);
            $medical_allowance = floatval($_POST['medical_allowance']);
            $special_allowance = floatval($_POST['special_allowance']);
            $other_allowances = floatval($_POST['other_allowances']);
            $pf_deduction = floatval($_POST['pf_deduction']);
            $esi_deduction = floatval($_POST['esi_deduction']);
            $professional_tax = floatval($_POST['professional_tax']);
            $income_tax = floatval($_POST['income_tax']);
            $other_deductions = floatval($_POST['other_deductions']);
            $effective_from = mysqli_real_escape_string($conn, $_POST['effective_from']);
            
            // Deactivate previous salary components
            mysqli_query($conn, "UPDATE hr_salary_components SET status = 'inactive' WHERE employee_id = $employee_id");
            
            // Insert new salary component
            $query = "
                INSERT INTO hr_salary_components (
                    employee_id, basic_salary, hra, transport_allowance, medical_allowance,
                    special_allowance, other_allowances, pf_deduction, esi_deduction,
                    professional_tax, income_tax, other_deductions, effective_from
                ) VALUES (
                    $employee_id, $basic_salary, $hra, $transport_allowance, $medical_allowance,
                    $special_allowance, $other_allowances, $pf_deduction, $esi_deduction,
                    $professional_tax, $income_tax, $other_deductions, '$effective_from'
                )
            ";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Salary component updated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;
            
        case 'get_payroll':
            $id = intval($_POST['id']);
            $query = mysqli_query($conn, "
                SELECT p.*, 
                       CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                       e.employee_id as emp_id
                FROM hr_payroll p
                JOIN hr_employees e ON p.employee_id = e.id
                WHERE p.id = $id
            ");
            
            if ($query && $row = mysqli_fetch_assoc($query)) {
                echo json_encode(['success' => true, 'data' => $row]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Payroll record not found']);
            }
            exit;
            
        case 'delete_payroll':
            $id = intval($_POST['id']);
            $query = "DELETE FROM hr_payroll WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Payroll record deleted successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;
    }
}

// Get statistics
$current_month = date('n');
$current_year = date('Y');

$processed_this_month = 0;
$pending_payment = 0;
$total_payroll_amount = 0;
$average_salary = 0;

$statsQuery = mysqli_query($conn, "
    SELECT 
        SUM(CASE WHEN payroll_month = $current_month AND payroll_year = $current_year THEN 1 ELSE 0 END) as processed_this_month,
        SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) as pending_payment,
        SUM(CASE WHEN payroll_month = $current_month AND payroll_year = $current_year THEN net_salary ELSE 0 END) as total_payroll_amount,
        AVG(CASE WHEN payroll_month = $current_month AND payroll_year = $current_year THEN net_salary END) as average_salary
    FROM hr_payroll
");

if ($statsQuery && $stats = mysqli_fetch_assoc($statsQuery)) {
    $processed_this_month = $stats['processed_this_month'] ?: 0;
    $pending_payment = $stats['pending_payment'] ?: 0;
    $total_payroll_amount = $stats['total_payroll_amount'] ?: 0;
    $average_salary = $stats['average_salary'] ?: 0;
}

// Get recent payroll records
$recentPayrolls = mysqli_query($conn, "
    SELECT p.*, 
           CONCAT(e.first_name, ' ', e.last_name) as employee_name,
           e.employee_id as emp_id,
           e.position,
           d.department_name
    FROM hr_payroll p
    JOIN hr_employees e ON p.employee_id = e.id
    LEFT JOIN hr_departments d ON e.department_id = d.id
    ORDER BY p.created_at DESC
    LIMIT 50
");

// Get pending payments
$pendingPayments = mysqli_query($conn, "
    SELECT p.*, 
           CONCAT(e.first_name, ' ', e.last_name) as employee_name,
           e.employee_id as emp_id
    FROM hr_payroll p
    JOIN hr_employees e ON p.employee_id = e.id
    WHERE p.status = 'processed'
    ORDER BY p.processed_at DESC
");

// Get employees for salary component management
$employees = mysqli_query($conn, "
    SELECT id, first_name, last_name, employee_id, salary
    FROM hr_employees 
    WHERE status = 'active' 
    ORDER BY first_name, last_name
");

// Get monthly trends
$monthlyTrends = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('n', strtotime("-$i months"));
    $year = date('Y', strtotime("-$i months"));
    $monthName = date('M Y', strtotime("-$i months"));
    
    $trendQuery = mysqli_query($conn, "
        SELECT 
            COUNT(*) as employee_count,
            COALESCE(SUM(net_salary), 0) as total_amount
        FROM hr_payroll 
        WHERE payroll_month = $month AND payroll_year = $year
    ");
    
    if ($trendQuery && $trend = mysqli_fetch_assoc($trendQuery)) {
        $monthlyTrends[] = [
            'month' => $monthName,
            'count' => $trend['employee_count'],
            'amount' => $trend['total_amount']
        ];
    }
}

include '../layouts/header.php';
include '../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">💰 Payroll Processing</h1>
                <p class="text-muted">Process monthly payroll, manage salary calculations, and generate payslips</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-info" onclick="exportPayrollReport()">
                    <i class="bi bi-download"></i> Export Report
                </button>
                <button class="btn btn-outline-success" onclick="generatePayslips()">
                    <i class="bi bi-file-earmark-pdf"></i> Generate Payslips
                </button>
                <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#salaryComponentModal">
                    <i class="bi bi-gear"></i> Salary Components
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#processPayrollModal">
                    <i class="bi bi-play-circle"></i> Process Payroll
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-check-circle fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $processed_this_month ?></h3>
                        <small class="opacity-75">Processed This Month</small>
                        <small class="d-block opacity-75"><?= date('F Y') ?></small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-clock fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $pending_payment ?></h3>
                        <small class="opacity-75">Pending Payments</small>
                        <small class="d-block opacity-75">Requires Action</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-cash-stack fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold">₹<?= number_format($total_payroll_amount, 0) ?></h3>
                        <small class="opacity-75">Total Payroll</small>
                        <small class="d-block opacity-75">This Month</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-graph-up fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold">₹<?= number_format($average_salary, 0) ?></h3>
                        <small class="opacity-75">Average Salary</small>
                        <small class="d-block opacity-75">This Month</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="row g-4">
            <!-- Payroll Records -->
            <div class="col-xl-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-table text-primary"></i> Payroll Records
                        </h5>
                        <div class="btn-group btn-group-sm" role="group">
                            <input type="radio" class="btn-check" name="filter" id="all" autocomplete="off" checked>
                            <label class="btn btn-outline-secondary" for="all">All</label>
                            
                            <input type="radio" class="btn-check" name="filter" id="current" autocomplete="off">
                            <label class="btn btn-outline-secondary" for="current">Current Month</label>
                            
                            <input type="radio" class="btn-check" name="filter" id="pending" autocomplete="off">
                            <label class="btn btn-outline-secondary" for="pending">Pending</label>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="payrollTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Employee</th>
                                        <th>Period</th>
                                        <th>Basic Salary</th>
                                        <th>Gross Salary</th>
                                        <th>Deductions</th>
                                        <th>Net Salary</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($recentPayrolls): while ($payroll = mysqli_fetch_assoc($recentPayrolls)): ?>
                                        <tr data-status="<?= $payroll['status'] ?>" data-month="<?= $payroll['payroll_month'] ?>" data-year="<?= $payroll['payroll_year'] ?>">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-size: 0.875rem;">
                                                        <?= strtoupper(substr($payroll['employee_name'], 0, 2)) ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-semibold"><?= htmlspecialchars($payroll['employee_name']) ?></div>
                                                        <small class="text-muted"><?= htmlspecialchars($payroll['emp_id']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?= date('M Y', mktime(0, 0, 0, $payroll['payroll_month'], 1, $payroll['payroll_year'])) ?>
                                            </td>
                                            <td>₹<?= number_format($payroll['basic_salary'], 0) ?></td>
                                            <td>₹<?= number_format($payroll['gross_salary'], 0) ?></td>
                                            <td>₹<?= number_format($payroll['pf_deduction'] + $payroll['esi_deduction'] + $payroll['tax_deduction'] + $payroll['deductions'], 0) ?></td>
                                            <td>
                                                <strong>₹<?= number_format($payroll['net_salary'], 0) ?></strong>
                                            </td>
                                            <td>
                                                <?php
                                                $statusClass = match($payroll['status']) {
                                                    'processed' => 'warning',
                                                    'paid' => 'success',
                                                    default => 'secondary'
                                                };
                                                ?>
                                                <span class="badge bg-<?= $statusClass ?>"><?= ucfirst($payroll['status']) ?></span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <button class="btn btn-outline-info" 
                                                            onclick="viewPayslip(<?= $payroll['id'] ?>)"
                                                            title="View Payslip">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button class="btn btn-outline-primary" 
                                                            onclick="downloadPayslip(<?= $payroll['id'] ?>)"
                                                            title="Download">
                                                        <i class="bi bi-download"></i>
                                                    </button>
                                                    <?php if ($payroll['status'] === 'processed'): ?>
                                                        <button class="btn btn-outline-success" 
                                                                onclick="markAsPaid(<?= $payroll['id'] ?>)"
                                                                title="Mark as Paid">
                                                            <i class="bi bi-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-outline-danger" 
                                                            onclick="deletePayroll(<?= $payroll['id'] ?>)"
                                                            title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar Info -->
            <div class="col-xl-4">
                <!-- Pending Payments -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-clock text-warning"></i> Pending Payments
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if ($pendingPayments && mysqli_num_rows($pendingPayments) > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php 
                                $count = 0;
                                while ($payment = mysqli_fetch_assoc($pendingPayments) && $count < 5): 
                                    $count++;
                                ?>
                                    <div class="list-group-item px-0 border-0">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($payment['employee_name']) ?></h6>
                                                <small class="text-muted">
                                                    <?= date('M Y', mktime(0, 0, 0, $payment['payroll_month'], 1, $payment['payroll_year'])) ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <strong>₹<?= number_format($payment['net_salary'], 0) ?></strong>
                                                <br>
                                                <button class="btn btn-success btn-sm" onclick="markAsPaid(<?= $payment['id'] ?>)">
                                                    Pay
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-3">
                                <i class="bi bi-check-circle display-6"></i>
                                <p class="mt-2 small">No pending payments!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Monthly Trends Chart -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-graph-up text-info"></i> Payroll Trends
                        </h6>
                    </div>
                    <div class="card-body">
                        <canvas id="payrollTrendChart" style="height: 250px;"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Process Payroll Modal -->
<div class="modal fade" id="processPayrollModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-play-circle text-primary me-2"></i>Process Monthly Payroll
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="processPayrollForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Month *</label>
                            <select class="form-select" name="month" required>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?= $i ?>" <?= $i === intval(date('n')) ? 'selected' : '' ?>>
                                        <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Year *</label>
                            <select class="form-select" name="year" required>
                                <?php for ($i = date('Y') - 1; $i <= date('Y') + 1; $i++): ?>
                                    <option value="<?= $i ?>" <?= $i === intval(date('Y')) ? 'selected' : '' ?>>
                                        <?= $i ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <h6><i class="bi bi-info-circle me-2"></i>Payroll Calculation Details:</h6>
                        <ul class="mb-0 small">
                            <li>Basic salary will be prorated based on attendance</li>
                            <li>HRA: 40% of basic salary (if not configured)</li>
                            <li>Transport Allowance: ₹2,000 (if not configured)</li>
                            <li>Medical Allowance: ₹1,500 (if not configured)</li>
                            <li>Overtime: 1.5x hourly rate</li>
                            <li>PF Deduction: 12% of basic salary</li>
                            <li>ESI Deduction: 0.75% of gross salary (if ≤ ₹21,000)</li>
                            <li>Professional Tax: ₹200 (if not configured)</li>
                        </ul>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        This will process payroll for all active employees for the selected month/year.
                        Existing records will be updated with new calculations.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-play-circle me-1"></i>Process Payroll
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Salary Component Modal -->
<div class="modal fade" id="salaryComponentModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-gear text-secondary me-2"></i>Salary Component Management
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="salaryComponentForm">
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Select Employee *</label>
                            <select class="form-select" name="employee_id" id="employeeSelect" required>
                                <option value="">Choose Employee</option>
                                <?php if ($employees): while ($emp = mysqli_fetch_assoc($employees)): ?>
                                    <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?> (<?= $emp['employee_id'] ?>)</option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Effective From *</label>
                            <input type="date" class="form-control" name="effective_from" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Basic Salary *</label>
                            <input type="number" step="0.01" class="form-control" name="basic_salary" placeholder="₹25,000" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-success">🟢 Earnings</h6>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">HRA</label>
                                    <input type="number" step="0.01" class="form-control" name="hra" placeholder="₹10,000">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Transport Allowance</label>
                                    <input type="number" step="0.01" class="form-control" name="transport_allowance" placeholder="₹2,000">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Medical Allowance</label>
                                    <input type="number" step="0.01" class="form-control" name="medical_allowance" placeholder="₹1,500">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Special Allowance</label>
                                    <input type="number" step="0.01" class="form-control" name="special_allowance" placeholder="₹2,500">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Other Allowances</label>
                                    <input type="number" step="0.01" class="form-control" name="other_allowances" placeholder="₹0">
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h6 class="text-danger">🔴 Deductions</h6>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">PF Deduction</label>
                                    <input type="number" step="0.01" class="form-control" name="pf_deduction" placeholder="₹3,000">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">ESI Deduction</label>
                                    <input type="number" step="0.01" class="form-control" name="esi_deduction" placeholder="₹300">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Professional Tax</label>
                                    <input type="number" step="0.01" class="form-control" name="professional_tax" placeholder="₹200">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Income Tax</label>
                                    <input type="number" step="0.01" class="form-control" name="income_tax" placeholder="₹0">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Other Deductions</label>
                                    <input type="number" step="0.01" class="form-control" name="other_deductions" placeholder="₹0">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Save Salary Component
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Payslip View Modal -->
<div class="modal fade" id="payslipViewModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-file-earmark-text text-info me-2"></i>Payslip View
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="payslipContent">
                <!-- Payslip content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printPayslip()">
                    <i class="bi bi-printer me-1"></i>Print
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Form submissions
document.getElementById('processPayrollForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (!confirm('This will process payroll for all active employees. Continue?')) {
        return;
    }
    
    const formData = new FormData(this);
    formData.append('action', 'process_payroll');
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="spinner-border spinner-border-sm me-2"></i>Processing...';
    submitBtn.disabled = true;
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while processing payroll');
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
});

document.getElementById('salaryComponentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'update_salary_component');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            bootstrap.Modal.getInstance(document.getElementById('salaryComponentModal')).hide();
            // Reset form
            this.reset();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while saving salary component');
    });
});

// Utility functions
function viewPayslip(payrollId) {
    const formData = new FormData();
    formData.append('action', 'get_payroll');
    formData.append('id', payrollId);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const payroll = data.data;
            const content = generatePayslipHTML(payroll);
            document.getElementById('payslipContent').innerHTML = content;
            new bootstrap.Modal(document.getElementById('payslipViewModal')).show();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while loading payslip');
    });
}

function generatePayslipHTML(payroll) {
    const period = new Date(payroll.payroll_year, payroll.payroll_month - 1).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    const totalDeductions = parseFloat(payroll.pf_deduction || 0) + parseFloat(payroll.esi_deduction || 0) + parseFloat(payroll.tax_deduction || 0) + parseFloat(payroll.deductions || 0);
    
    return `
        <div class="payslip-container">
            <div class="text-center mb-4">
                <h4>PAYSLIP</h4>
                <p class="text-muted">Pay Period: ${period}</p>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <strong>Employee Name:</strong> ${payroll.employee_name}<br>
                    <strong>Employee ID:</strong> ${payroll.emp_id}<br>
                    <strong>Pay Period:</strong> ${period}
                </div>
                <div class="col-md-6 text-end">
                    <strong>Working Days:</strong> ${payroll.working_days}<br>
                    <strong>Present Days:</strong> ${payroll.present_days}<br>
                    <strong>Absent Days:</strong> ${payroll.absent_days}
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-success">EARNINGS</h6>
                    <table class="table table-sm">
                        <tr><td>Basic Salary</td><td class="text-end">₹${Number(payroll.basic_salary).toLocaleString()}</td></tr>
                        <tr><td>Allowances</td><td class="text-end">₹${Number(payroll.allowances || 0).toLocaleString()}</td></tr>
                        <tr><td>Overtime</td><td class="text-end">₹${Number(payroll.overtime_amount || 0).toLocaleString()}</td></tr>
                        <tr><td>Bonus</td><td class="text-end">₹${Number(payroll.bonus || 0).toLocaleString()}</td></tr>
                        <tr class="table-success"><td><strong>Gross Salary</strong></td><td class="text-end"><strong>₹${Number(payroll.gross_salary).toLocaleString()}</strong></td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6 class="text-danger">DEDUCTIONS</h6>
                    <table class="table table-sm">
                        <tr><td>PF Deduction</td><td class="text-end">₹${Number(payroll.pf_deduction || 0).toLocaleString()}</td></tr>
                        <tr><td>ESI Deduction</td><td class="text-end">₹${Number(payroll.esi_deduction || 0).toLocaleString()}</td></tr>
                        <tr><td>Tax Deduction</td><td class="text-end">₹${Number(payroll.tax_deduction || 0).toLocaleString()}</td></tr>
                        <tr><td>Other Deductions</td><td class="text-end">₹${Number(payroll.deductions || 0).toLocaleString()}</td></tr>
                        <tr class="table-danger"><td><strong>Total Deductions</strong></td><td class="text-end"><strong>₹${totalDeductions.toLocaleString()}</strong></td></tr>
                    </table>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-12">
                    <div class="alert alert-primary text-center">
                        <h5 class="mb-0"><strong>NET SALARY: ₹${Number(payroll.net_salary).toLocaleString()}</strong></h5>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function downloadPayslip(payrollId) {
    window.open('export_payslip.php?id=' + payrollId, '_blank');
}

function markAsPaid(payrollId) {
    if (confirm('Are you sure you want to mark this payroll as paid?')) {
        const formData = new FormData();
        formData.append('action', 'mark_paid');
        formData.append('payroll_id', payrollId);
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while marking payroll as paid');
        });
    }
}

function deletePayroll(payrollId) {
    if (confirm('Are you sure you want to delete this payroll record? This action cannot be undone.')) {
        const formData = new FormData();
        formData.append('action', 'delete_payroll');
        formData.append('id', payrollId);
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting payroll record');
        });
    }
}

function exportPayrollReport() {
    const month = prompt('Enter month (1-12) for export:', new Date().getMonth() + 1);
    const year = prompt('Enter year for export:', new Date().getFullYear());
    
    if (month && year) {
        window.open(`export_payroll_report.php?month=${month}&year=${year}`, '_blank');
    }
}

function generatePayslips() {
    const month = prompt('Enter month (1-12) for payslip generation:', new Date().getMonth() + 1);
    const year = prompt('Enter year for payslip generation:', new Date().getFullYear());
    
    if (month && year) {
        window.open(`generate_payslips.php?month=${month}&year=${year}`, '_blank');
    }
}

function printPayslip() {
    const printContent = document.getElementById('payslipContent').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Payslip</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { font-family: Arial, sans-serif; }
                @media print { 
                    .no-print { display: none; }
                    body { margin: 0; }
                }
            </style>
        </head>
        <body class="p-3">
            ${printContent}
            <script>window.print();</script>
        </body>
        </html>
    `);
    printWindow.document.close();
}

// Initialize DataTable and Chart on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize payroll trend chart
    initPayrollTrendChart();
    
    // Filter functionality
    document.querySelectorAll('input[name="filter"]').forEach(radio => {
        radio.addEventListener('change', function() {
            filterPayrollTable(this.id);
        });
    });
});

function initPayrollTrendChart() {
    const ctx = document.getElementById('payrollTrendChart').getContext('2d');
    const trendData = <?= json_encode($monthlyTrends) ?>;
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: trendData.map(d => d.month),
            datasets: [
                {
                    label: 'Total Amount (₹)',
                    data: trendData.map(d => d.amount),
                    backgroundColor: 'rgba(99, 102, 241, 0.7)',
                    borderColor: '#6366f1',
                    borderWidth: 1,
                    yAxisID: 'y'
                },
                {
                    label: 'Employee Count',
                    data: trendData.map(d => d.count),
                    type: 'line',
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Amount (₹)'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Employees'
                    },
                    grid: {
                        drawOnChartArea: false,
                    }
                }
            }
        }
    });
}

function filterPayrollTable(filter) {
    const currentMonth = <?= $current_month ?>;
    const currentYear = <?= $current_year ?>;
    const rows = document.querySelectorAll('#payrollTable tbody tr');
    
    rows.forEach(row => {
        const status = row.getAttribute('data-status');
        const month = parseInt(row.getAttribute('data-month'));
        const year = parseInt(row.getAttribute('data-year'));
        
        let show = true;
        
        switch (filter) {
            case 'current':
                show = (month === currentMonth && year === currentYear);
                break;
            case 'pending':
                show = (status === 'processed');
                break;
            case 'all':
            default:
                show = true;
                break;
        }
        
        row.style.display = show ? '' : 'none';
    });
}
</script>

<style>
.stats-card {
    transition: transform 0.2s;
}
.stats-card:hover {
    transform: translateY(-2px);
}
.payslip-container {
    font-size: 0.9rem;
}
.payslip-container h4 {
    border-bottom: 2px solid #000;
    padding-bottom: 10px;
}
.payslip-container table {
    margin-bottom: 10px;
}
.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}
</style>

<?php include '../layouts/footer.php'; ?>
