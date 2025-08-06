<?php
session_start();
// Check for either session variable for compatibility
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Include config and database

include '../config.php';
if (!isset($root_path)) 
include '../db.php';
if (!isset($root_path)) 
include '../auth_guard.php';

$page_title = 'Payroll Processing - HRMS';

// Create payroll table if not exists
$createPayrollTable = "
CREATE TABLE IF NOT EXISTS payroll (
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
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
)";
mysqli_query($conn, $createPayrollTable);

// Handle payroll actions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'process_payroll':
                $month = intval($_POST['month']);
                $year = intval($_POST['year']);
                $processed_by = $_SESSION['user_id'] ?? 1;
                
                // Get all active employees
                $employees = mysqli_query($conn, "SELECT * FROM employees WHERE status = 'active'");
                $processed_count = 0;
                
                while ($employee = mysqli_fetch_assoc($employees)) {
                    $employee_id = $employee['employee_id'];
                    $basic_salary = $employee['salary'];
                    
                    // Calculate working days for the month
                    $working_days = cal_days_in_month(CAL_GREGORIAN, $month, $year) - 8; // Assuming 8 holidays/Sundays
                    
                    // Get attendance data for the month
                    $attendanceQuery = "
                        SELECT 
                            SUM(CASE WHEN status IN ('Present', 'Late', 'Work From Home') THEN 1 ELSE 0 END) as present_days,
                            SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
                            SUM(COALESCE(overtime_hours, 0)) as total_overtime
                        FROM attendance 
                        WHERE employee_id = $employee_id 
                        AND MONTH(attendance_date) = $month 
                        AND YEAR(attendance_date) = $year
                    ";
                    
                    $attendanceResult = mysqli_query($conn, $attendanceQuery);
                    $attendance = mysqli_fetch_assoc($attendanceResult);
                    
                    $present_days = $attendance['present_days'] ?? 0;
                    $absent_days = $attendance['absent_days'] ?? 0;
                    $overtime_hours = $attendance['total_overtime'] ?? 0;
                    
                    // Calculate salary components
                    $per_day_salary = $basic_salary / $working_days;
                    $earned_salary = $per_day_salary * $present_days;
                    $overtime_amount = ($basic_salary / ($working_days * 8)) * $overtime_hours * 1.5; // 1.5x for overtime
                    $bonus = 0; // Can be configured
                    $allowances = $basic_salary * 0.10; // 10% allowances
                    
                    $gross_salary = $earned_salary + $overtime_amount + $bonus + $allowances;
                    
                    // Calculate deductions
                    $pf_deduction = $basic_salary * 0.12; // 12% PF
                    $esi_deduction = $gross_salary * 0.0075; // 0.75% ESI
                    $tax_deduction = 0; // Simplified - can be calculated based on tax slabs
                    $other_deductions = 0;
                    
                    $total_deductions = $pf_deduction + $esi_deduction + $tax_deduction + $other_deductions;
                    $net_salary = $gross_salary - $total_deductions;
                    
                    // Insert or update payroll record
                    $payrollQuery = "
                        INSERT INTO payroll (
                            employee_id, payroll_month, payroll_year, basic_salary, gross_salary,
                            working_days, present_days, absent_days, overtime_hours, overtime_amount,
                            bonus, allowances, deductions, pf_deduction, esi_deduction, tax_deduction,
                            net_salary, status, processed_by, processed_at
                        ) VALUES (
                            $employee_id, $month, $year, $basic_salary, $gross_salary,
                            $working_days, $present_days, $absent_days, $overtime_hours, $overtime_amount,
                            $bonus, $allowances, $other_deductions, $pf_deduction, $esi_deduction, $tax_deduction,
                            $net_salary, 'processed', $processed_by, NOW()
                        )
                        ON DUPLICATE KEY UPDATE
                            gross_salary = $gross_salary,
                            present_days = $present_days,
                            absent_days = $absent_days,
                            overtime_hours = $overtime_hours,
                            overtime_amount = $overtime_amount,
                            allowances = $allowances,
                            pf_deduction = $pf_deduction,
                            esi_deduction = $esi_deduction,
                            net_salary = $net_salary,
                            status = 'processed',
                            processed_by = $processed_by,
                            processed_at = NOW()
                    ";
                    
                    if (mysqli_query($conn, $payrollQuery)) {
                        $processed_count++;
                    }
                }
                
                $success_message = "Payroll processed successfully for $processed_count employees!";
                break;
                
            case 'mark_paid':
                $payroll_id = intval($_POST['payroll_id']);
                $query = "UPDATE payroll SET status = 'paid' WHERE id = $payroll_id";
                
                if (mysqli_query($conn, $query)) {
                    $success_message = "Payroll marked as paid successfully!";
                } else {
                    $error_message = "Error marking payroll as paid: " . mysqli_error($conn);
                }
                break;
        }
    }
}

// Get current month and year
$current_month = date('n');
$current_year = date('Y');

// Get payroll statistics
$payrollStats = [
    'processed_this_month' => 0,
    'pending_payment' => 0,
    'total_payroll_amount' => 0,
    'average_salary' => 0
];

$statsQuery = "
    SELECT 
        SUM(CASE WHEN payroll_month = $current_month AND payroll_year = $current_year THEN 1 ELSE 0 END) as processed_this_month,
        SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) as pending_payment,
        SUM(CASE WHEN payroll_month = $current_month AND payroll_year = $current_year THEN net_salary ELSE 0 END) as total_payroll_amount,
        AVG(CASE WHEN payroll_month = $current_month AND payroll_year = $current_year THEN net_salary END) as average_salary
    FROM payroll
";

$result = mysqli_query($conn, $statsQuery);
if ($result) {
    $payrollStats = mysqli_fetch_assoc($result);
}

// Get recent payroll records
$recentPayrolls = [];
$query = "
    SELECT p.*, e.name as employee_name, e.employee_code, e.position, d.department_name
    FROM payroll p
    JOIN employees e ON p.employee_id = e.employee_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    ORDER BY p.created_at DESC
    LIMIT 50
";
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $recentPayrolls[] = $row;
    }
}

// Get pending payments
$pendingPayments = [];
$query = "
    SELECT p.*, e.name as employee_name, e.employee_code
    FROM payroll p
    JOIN employees e ON p.employee_id = e.employee_id
    WHERE p.status = 'processed'
    ORDER BY p.processed_at DESC
";
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $pendingPayments[] = $row;
    }
}

// Get monthly payroll trends
$monthlyTrends = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('n', strtotime("-$i months"));
    $year = date('Y', strtotime("-$i months"));
    $monthName = date('M Y', strtotime("-$i months"));
    
    $trendQuery = "
        SELECT 
            COUNT(*) as employee_count,
            SUM(net_salary) as total_amount
        FROM payroll 
        WHERE payroll_month = $month AND payroll_year = $year
    ";
    
    $result = mysqli_query($conn, $trendQuery);
    if ($result) {
        $trend = mysqli_fetch_assoc($result);
        $monthlyTrends[] = [
            'month' => $monthName,
            'count' => $trend['employee_count'],
            'amount' => $trend['total_amount'] ?? 0
        ];
    }
}

include '../layouts/header.php';
if (!isset($root_path)) 
include '../layouts/sidebar.php';
?>

<div class="main-content animate-fade-in-up">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="gradient-text mb-2" style="font-size: 2.5rem; font-weight: 700;">
                    <i class="bi bi-currency-exchange text-primary me-3"></i>Payroll Processing
                </h1>
                <p class="text-muted" style="font-size: 1.1rem;">Process monthly payroll, manage salary calculations, and generate payslips</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-info" onclick="exportPayrollReport()">
                    <i class="bi bi-download"></i> Export Report
                </button>
                <button class="btn btn-outline-success" onclick="generatePayslips()">
                    <i class="bi bi-file-earmark-pdf"></i> Generate Payslips
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#processPayrollModal">
                    <i class="bi bi-play-circle"></i> Process Payroll
                </button>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i><?= $success_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Payroll Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-gradient-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="card-title h2 mb-2"><?= $payrollStats['processed_this_month'] ?></h3>
                                <p class="card-text opacity-90">Processed This Month</p>
                                <small class="opacity-75"><?= date('F Y') ?></small>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-check-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-gradient-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="card-title h2 mb-2"><?= $payrollStats['pending_payment'] ?></h3>
                                <p class="card-text opacity-90">Pending Payments</p>
                                <small class="opacity-75">Requires action</small>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-clock"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-gradient-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="card-title h2 mb-2">₹<?= number_format($payrollStats['total_payroll_amount'], 0) ?></h3>
                                <p class="card-text opacity-90">Total Payroll</p>
                                <small class="opacity-75">This month</small>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-cash-stack"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-gradient-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="card-title h2 mb-2">₹<?= number_format($payrollStats['average_salary'], 0) ?></h3>
                                <p class="card-text opacity-90">Average Salary</p>
                                <small class="opacity-75">This month</small>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-graph-up"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="row g-4">
            <!-- Payroll Records -->
            <div class="col-xl-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-table text-primary"></i> Payroll Records
                        </h5>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-secondary active">All</button>
                            <button class="btn btn-outline-secondary">Current Month</button>
                            <button class="btn btn-outline-secondary">Pending</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="payrollTable">
                                <thead>
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
                                    <?php foreach ($recentPayrolls as $payroll): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <h6 class="mb-0"><?= htmlspecialchars($payroll['employee_name']) ?></h6>
                                                    <small class="text-muted"><?= htmlspecialchars($payroll['employee_code']) ?></small>
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
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-info" 
                                                            onclick="viewPayslip(<?= $payroll['id'] ?>)"
                                                            data-bs-toggle="tooltip" title="View Payslip">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button class="btn btn-outline-primary" 
                                                            onclick="downloadPayslip(<?= $payroll['id'] ?>)"
                                                            data-bs-toggle="tooltip" title="Download">
                                                        <i class="bi bi-download"></i>
                                                    </button>
                                                    <?php if ($payroll['status'] === 'processed'): ?>
                                                        <button class="btn btn-outline-success" 
                                                                onclick="markAsPaid(<?= $payroll['id'] ?>)"
                                                                data-bs-toggle="tooltip" title="Mark as Paid">
                                                            <i class="bi bi-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar Info -->
            <div class="col-xl-4">
                <!-- Pending Payments -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-clock text-warning"></i> Pending Payments
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pendingPayments)): ?>
                            <div class="text-center text-muted py-3">
                                <i class="bi bi-check-circle display-6"></i>
                                <p class="mt-2 small">No pending payments!</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach (array_slice($pendingPayments, 0, 5) as $payment): ?>
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
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Monthly Trends Chart -->
                <div class="card">
                    <div class="card-header">
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
<div class="modal fade" id="processPayrollModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Process Monthly Payroll</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="process_payroll">
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
                        <ul class="mb-0">
                            <li>Basic salary will be prorated based on attendance</li>
                            <li>Overtime will be calculated at 1.5x rate</li>
                            <li>Allowances: 10% of basic salary</li>
                            <li>PF Deduction: 12% of basic salary</li>
                            <li>ESI Deduction: 0.75% of gross salary</li>
                        </ul>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        This will process payroll for all active employees for the selected month/year.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Process Payroll</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Initialize DataTable
document.addEventListener('DOMContentLoaded', function() {
    $('#payrollTable').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[1, 'desc']],
        columnDefs: [
            { orderable: false, targets: [7] }
        ]
    });
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize payroll trend chart
    initPayrollTrendChart();
});

// Payroll Trend Chart
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
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            }
        }
    });
}

// View Payslip Function
function viewPayslip(payrollId) {
    window.open('api/view_payslip.php?id=' + payrollId, '_blank');
}

// Download Payslip Function
function downloadPayslip(payrollId) {
    window.open('api/download_payslip.php?id=' + payrollId, '_blank');
}

// Mark as Paid Function
function markAsPaid(payrollId) {
    if (confirm('Are you sure you want to mark this payroll as paid?')) {
        const formData = new FormData();
        formData.append('action', 'mark_paid');
        formData.append('payroll_id', payrollId);
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            alert('Payroll marked as paid successfully!');
            location.reload();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while marking payroll as paid');
        });
    }
}

// Export Payroll Report Function
function exportPayrollReport() {
    window.open('api/export_payroll_report.php', '_blank');
}

// Generate Payslips Function
function generatePayslips() {
    const month = prompt('Enter month (1-12) for payslip generation:', new Date().getMonth() + 1);
    const year = prompt('Enter year for payslip generation:', new Date().getFullYear());
    
    if (month && year) {
        window.open(`api/generate_payslips.php?month=${month}&year=${year}`, '_blank');
    }
}
</script>

<?php if (!isset($root_path)) 
include '../layouts/footer.php'; ?>
