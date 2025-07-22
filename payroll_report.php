<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';
$page_title = 'Payroll Report';

// Handle input from month picker (format: yyyy-mm)
if (isset($_GET['month_year'])) {
    $dateParts = explode('-', $_GET['month_year']);
    $year = $dateParts[0];
    $month = $dateParts[1];
} else {
    $month = date('m');
    $year = date('Y');
}

// Get number of days in the month
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

// Get employees with corrected column names
$employees = $conn->query("SELECT employee_id, name, employee_code, position, monthly_salary FROM employees ORDER BY name ASC");

if (!$employees) {
    die("Query Failed: " . $conn->error);
}

// Get attendance count for present/absent
function getAttendanceCount($conn, $empId, $month, $year, $status) {
    $query = "SELECT COUNT(*) as count FROM attendance WHERE employee_id = ? AND MONTH(attendance_date) = ? AND YEAR(attendance_date) = ? AND status = ?";
    $stmt = $conn->prepare($query);

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param("iiis", $empId, $month, $year, $status);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['count'] ?? 0;
}

// Calculate totals
$totalEmployees = 0;
$totalSalaryBudget = 0;
$totalEarnedSalary = 0;
$totalSavings = 0;

include 'layouts/header.php';
include 'layouts/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Payroll Report</h1>
            <p class="text-muted">Detailed salary calculations for <?= date('F Y', mktime(0, 0, 0, $month, 1, $year)) ?></p>
        </div>
        <div>
            <button class="btn btn-outline-success" onclick="exportReport()">
                <i class="bi bi-download"></i> Export PDF
            </button>
            <button class="btn btn-outline-primary" onclick="printReport()">
                <i class="bi bi-printer"></i> Print
            </button>
        </div>
    </div>

    <!-- Month Selection -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-calendar me-2"></i>Select Report Period</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Select Month & Year</label>
                    <input type="month" name="month_year" class="form-control" 
                           value="<?= $year ?>-<?= str_pad($month, 2, '0', STR_PAD_LEFT) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary d-block">
                        <i class="bi bi-search"></i> Generate Report
                    </button>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Quick Periods</label>
                    <div class="btn-group d-block" role="group">
                        <a href="?month_year=<?= date('Y-m') ?>" class="btn btn-outline-secondary btn-sm">This Month</a>
                        <a href="?month_year=<?= date('Y-m', strtotime('-1 month')) ?>" class="btn btn-outline-secondary btn-sm">Last Month</a>
                        <a href="?month_year=<?= date('Y-m', strtotime('-2 months')) ?>" class="btn btn-outline-secondary btn-sm">2 Months Ago</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards will be calculated after employee data -->
    <?php
    $summaryData = [];
    $employees->data_seek(0); // Reset pointer for calculations
    while ($emp = $employees->fetch_assoc()) {
        $presentDays = getAttendanceCount($conn, $emp['employee_id'], $month, $year, 'Present');
        $lateDays = getAttendanceCount($conn, $emp['employee_id'], $month, $year, 'Late');
        $halfDays = getAttendanceCount($conn, $emp['employee_id'], $month, $year, 'Half Day');
        
        $totalPresentDays = $presentDays + $lateDays + ($halfDays * 0.5);
        $monthlySalary = $emp['monthly_salary'];
        $dailyRate = $monthlySalary / $daysInMonth;
        $earnedSalary = $totalPresentDays * $dailyRate;
        
        $totalEmployees++;
        $totalSalaryBudget += $monthlySalary;
        $totalEarnedSalary += $earnedSalary;
        
        $summaryData[] = [
            'employee' => $emp,
            'presentDays' => $presentDays,
            'lateDays' => $lateDays,
            'halfDays' => $halfDays,
            'totalPresentDays' => $totalPresentDays,
            'absentDays' => $daysInMonth - ($presentDays + $lateDays + $halfDays),
            'monthlySalary' => $monthlySalary,
            'dailyRate' => $dailyRate,
            'earnedSalary' => $earnedSalary
        ];
    }
    $totalSavings = $totalSalaryBudget - $totalEarnedSalary;
    ?>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Working Days</h6>
                            <h3 class="mb-0"><?= $daysInMonth ?></h3>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-calendar-week"></i>
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
                            <h6 class="card-title mb-0">Total Employees</h6>
                            <h3 class="mb-0"><?= $totalEmployees ?></h3>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-people"></i>
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
                            <h6 class="card-title mb-0">Salary Budget</h6>
                            <h3 class="mb-0">₹<?= number_format($totalSalaryBudget, 0) ?></h3>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-wallet"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Actual Payout</h6>
                            <h3 class="mb-0">₹<?= number_format($totalEarnedSalary, 0) ?></h3>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-currency-rupee"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payroll Details -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Payroll Details - <?= date('F Y', mktime(0, 0, 0, $month, 1, $year)) ?></h5>
        </div>
        <div class="card-body">
            <?php if (!empty($summaryData)): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="payrollTable">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Code</th>
                                <th>Position</th>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>Daily Rate</th>
                                <th>Monthly Salary</th>
                                <th>Earned Salary</th>
                                <th>Efficiency</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($summaryData as $data): ?>
                                <?php 
                                $efficiency = ($data['totalPresentDays'] / $daysInMonth) * 100;
                                $efficiencyClass = $efficiency >= 90 ? 'success' : ($efficiency >= 75 ? 'warning' : 'danger');
                                ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($data['employee']['name']) ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($data['employee']['employee_code']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($data['employee']['position']) ?></td>
                                    <td>
                                        <div>
                                            <span class="badge bg-success"><?= $data['presentDays'] + $data['lateDays'] ?> days</span>
                                            <?php if ($data['lateDays'] > 0): ?>
                                                <br><small class="text-warning"><?= $data['lateDays'] ?> late</small>
                                            <?php endif; ?>
                                            <?php if ($data['halfDays'] > 0): ?>
                                                <br><small class="text-info"><?= $data['halfDays'] ?> half</small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-danger"><?= $data['absentDays'] ?> days</span>
                                    </td>
                                    <td>
                                        ₹<?= number_format($data['dailyRate'], 2) ?>
                                    </td>
                                    <td>
                                        <strong class="text-primary">₹<?= number_format($data['monthlySalary'], 2) ?></strong>
                                    </td>
                                    <td>
                                        <strong class="text-success">₹<?= number_format($data['earnedSalary'], 2) ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $efficiencyClass ?>"><?= number_format($efficiency, 1) ?>%</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-dark">
                                <th colspan="6">TOTALS</th>
                                <th>₹<?= number_format($totalSalaryBudget, 2) ?></th>
                                <th>₹<?= number_format($totalEarnedSalary, 2) ?></th>
                                <th>
                                    <?php if ($totalSavings > 0): ?>
                                        <span class="text-success">Saved: ₹<?= number_format($totalSavings, 0) ?></span>
                                    <?php else: ?>
                                        <span class="text-danger">Over: ₹<?= number_format(abs($totalSavings), 0) ?></span>
                                    <?php endif; ?>
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-people fs-1 text-muted mb-3"></i>
                    <h5 class="text-muted">No employees found</h5>
                    <p class="text-muted">Add employees to generate payroll reports.</p>
                    <a href="pages/employees/employees.php" class="btn btn-primary">
                        <i class="bi bi-person-plus"></i> Add Employees
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Summary Analysis -->
    <?php if (!empty($summaryData)): ?>
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Cost Analysis</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Budgeted Amount:</span>
                            <strong>₹<?= number_format($totalSalaryBudget, 2) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Actual Payout:</span>
                            <strong>₹<?= number_format($totalEarnedSalary, 2) ?></strong>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <span><strong>Difference:</strong></span>
                            <strong class="<?= $totalSavings >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= $totalSavings >= 0 ? '+' : '' ?>₹<?= number_format($totalSavings, 2) ?>
                            </strong>
                        </div>
                        <small class="text-muted">
                            <?= $totalSavings >= 0 ? 'Savings due to absences' : 'Over budget due to overtime/bonuses' ?>
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Attendance Summary</h6>
                    </div>
                    <div class="card-body">
                        <?php
                        $totalPossibleDays = $totalEmployees * $daysInMonth;
                        $totalActualPresent = array_sum(array_column($summaryData, 'totalPresentDays'));
                        $overallAttendance = $totalPossibleDays > 0 ? ($totalActualPresent / $totalPossibleDays) * 100 : 0;
                        ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total Possible Days:</span>
                            <strong><?= number_format($totalPossibleDays) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total Present Days:</span>
                            <strong><?= number_format($totalActualPresent, 1) ?></strong>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <span><strong>Overall Attendance:</strong></span>
                            <strong class="<?= $overallAttendance >= 90 ? 'text-success' : ($overallAttendance >= 75 ? 'text-warning' : 'text-danger') ?>">
                                <?= number_format($overallAttendance, 1) ?>%
                            </strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#payrollTable').DataTable({
        pageLength: 25,
        responsive: true,
        order: [[0, "asc"]],
        columnDefs: [
            { orderable: false, targets: [] }
        ]
    });
});

function exportReport() {
    const month = <?= $month ?>;
    const year = <?= $year ?>;
    window.open(`export_payroll_pdf.php?month=${month}&year=${year}`, '_blank');
}

function printReport() {
    window.print();
}
</script>

<style>
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
