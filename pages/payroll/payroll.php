<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Payroll Management';

// Get current month and year
$currentMonth = $_GET['month'] ?? date('m');
$currentYear = $_GET['year'] ?? date('Y');
$monthYear = $currentYear . '-' . str_pad($currentMonth, 2, '0', STR_PAD_LEFT);

// Get number of days in the month
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);

// Get employees with corrected column names
$employees = $conn->query("SELECT employee_id, name, employee_code, position, monthly_salary FROM employees ORDER BY name ASC");

if (!$employees) {
    die("Query Failed: " . $conn->error);
}

// Function to get attendance count
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

// Calculate total payroll
$totalPayroll = 0;
$totalEmployees = 0;

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Payroll Management</h1>
            <p class="text-muted">Calculate and manage employee salaries for <?= date('F Y', mktime(0, 0, 0, $currentMonth, 1, $currentYear)) ?></p>
        </div>
        <div>
            <a href="../../payroll_report.php?month_year=<?= $monthYear ?>" class="btn btn-outline-success">
                <i class="bi bi-file-earmark-text"></i> Generate Report
            </a>
        </div>
    </div>

    <!-- Month Selection -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-calendar me-2"></i>Select Month</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Month</label>
                    <select name="month" class="form-select">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $m == $currentMonth ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Year</label>
                    <select name="year" class="form-select">
                        <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                            <option value="<?= $y ?>" <?= $y == $currentYear ? 'selected' : '' ?>>
                                <?= $y ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary d-block">
                        <i class="bi bi-search"></i> View Payroll
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Payroll Summary -->
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
                            <h3 class="mb-0"><?= $employees->num_rows ?></h3>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-people"></i>
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
                            <h6 class="card-title mb-0">Total Payroll</h6>
                            <h3 class="mb-0" id="totalPayrollAmount">₹0.00</h3>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-currency-rupee"></i>
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
                            <h6 class="card-title mb-0">Avg. Salary</h6>
                            <h3 class="mb-0" id="avgSalary">₹0.00</h3>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-calculator"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payroll Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Employee Payroll Details</h5>
            <div>
                <button class="btn btn-outline-success btn-sm" onclick="exportPayroll()">
                    <i class="bi bi-download"></i> Export Excel
                </button>
                <button class="btn btn-outline-danger btn-sm" onclick="printPayroll()">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if ($employees->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="payrollTable">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Code</th>
                                <th>Position</th>
                                <th>Present Days</th>
                                <th>Absent Days</th>
                                <th>Monthly Salary</th>
                                <th>Daily Rate</th>
                                <th>Earned Salary</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $employees->data_seek(0); // Reset result pointer
                            while ($emp = $employees->fetch_assoc()): 
                                $presentDays = getAttendanceCount($conn, $emp['employee_id'], $currentMonth, $currentYear, 'Present');
                                $lateDays = getAttendanceCount($conn, $emp['employee_id'], $currentMonth, $currentYear, 'Late');
                                $halfDays = getAttendanceCount($conn, $emp['employee_id'], $currentMonth, $currentYear, 'Half Day');
                                
                                $totalPresentDays = $presentDays + $lateDays + ($halfDays * 0.5);
                                $absentDays = $daysInMonth - ($presentDays + $lateDays + $halfDays);
                                
                                $monthlySalary = $emp['monthly_salary'];
                                $dailyRate = $monthlySalary / $daysInMonth;
                                $earnedSalary = $totalPresentDays * $dailyRate;
                                
                                $totalPayroll += $earnedSalary;
                                $totalEmployees++;
                            ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($emp['name']) ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($emp['employee_code']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($emp['position']) ?></td>
                                    <td>
                                        <span class="badge bg-success"><?= $presentDays + $lateDays ?></span>
                                        <?php if ($halfDays > 0): ?>
                                            <br><small class="text-muted"><?= $halfDays ?> half days</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-danger"><?= $absentDays ?></span>
                                    </td>
                                    <td>
                                        <strong class="text-primary">₹<?= number_format($monthlySalary, 2) ?></strong>
                                    </td>
                                    <td>
                                        ₹<?= number_format($dailyRate, 2) ?>
                                    </td>
                                    <td>
                                        <strong class="text-success">₹<?= number_format($earnedSalary, 2) ?></strong>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" 
                                                    onclick="viewPayslip(<?= $emp['employee_id'] ?>, '<?= $monthYear ?>')">
                                                <i class="bi bi-file-earmark-text"></i>
                                            </button>
                                            <button class="btn btn-outline-success" 
                                                    onclick="printPayslip(<?= $emp['employee_id'] ?>, '<?= $monthYear ?>')">
                                                <i class="bi bi-printer"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-people fs-1 text-muted mb-3"></i>
                    <h5 class="text-muted">No employees found</h5>
                    <p class="text-muted">Add employees to generate payroll.</p>
                    <a href="../employees/employees.php" class="btn btn-primary">
                        <i class="bi bi-person-plus"></i> Add Employees
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#payrollTable').DataTable({
        pageLength: 25,
        responsive: true,
        order: [[0, "asc"]],
        columnDefs: [
            { orderable: false, targets: [8] }
        ]
    });

    // Update summary cards
    const totalPayroll = <?= $totalPayroll ?>;
    const totalEmployees = <?= $totalEmployees ?>;
    const avgSalary = totalEmployees > 0 ? totalPayroll / totalEmployees : 0;

    $('#totalPayrollAmount').text('₹' + totalPayroll.toLocaleString('en-IN', {minimumFractionDigits: 2}));
    $('#avgSalary').text('₹' + avgSalary.toLocaleString('en-IN', {minimumFractionDigits: 2}));
});

function viewPayslip(employeeId, monthYear) {
    window.open(`generate_payslip.php?employee_id=${employeeId}&month=${monthYear}`, '_blank');
}

function printPayslip(employeeId, monthYear) {
    window.open(`generate_payslip.php?employee_id=${employeeId}&month=${monthYear}&print=1`, '_blank');
}

function exportPayroll() {
    const month = <?= $currentMonth ?>;
    const year = <?= $currentYear ?>;
    window.open(`export_payroll.php?month=${month}&year=${year}`, '_blank');
}

function printPayroll() {
    window.print();
}
</script>

<?php include '../../layouts/footer.php'; ?>