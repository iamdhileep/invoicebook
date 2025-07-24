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
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="h5 mb-0">💰 Payroll Management</h1>
                <p class="text-muted small">Calculate and manage employee salaries for <?= date('F Y', mktime(0, 0, 0, $currentMonth, 1, $currentYear)) ?></p>
            </div>
            <div>
                <a href="../../payroll_report.php?month_year=<?= $monthYear ?>" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-file-earmark-text"></i> Generate Report
                </a>
            </div>
        </div>

        <!-- Month Selection -->
        <div class="card mb-3 border-0 shadow-sm">
            <div class="card-header bg-light border-0 py-2">
                <h6 class="mb-0 text-dark"><i class="bi bi-calendar me-2"></i>Select Month</h6>
            </div>
            <div class="card-body p-3">
                <form method="GET" class="row g-2">
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
        <div class="row g-2 mb-3">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e8f4fd 0%, #cce7ff 100%);">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-calendar-week-fill fs-3" style="color: #0d6efd;"></i>
                        </div>
                        <h5 class="mb-1 fw-bold" style="color: #0d6efd;"><?= $daysInMonth ?></h5>
                        <small class="text-muted">Working Days</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%);">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-people-fill fs-3" style="color: #7b1fa2;"></i>
                        </div>
                        <h5 class="mb-1 fw-bold" style="color: #7b1fa2;"><?= $employees->num_rows ?></h5>
                        <small class="text-muted">Total Employees</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-currency-rupee fs-3" style="color: #388e3c;"></i>
                        </div>
                        <h5 class="mb-1 fw-bold" style="color: #388e3c;" id="totalPayrollAmount">₹0.00</h5>
                        <small class="text-muted">Total Payroll</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-calculator-fill fs-3" style="color: #ff9800;"></i>
                        </div>
                        <h5 class="mb-1 fw-bold" style="color: #ff9800;" id="avgSalary">₹0.00</h5>
                        <small class="text-muted">Avg. Salary</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payroll Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light border-0 py-2 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 text-dark">Employee Payroll Details</h6>
                <div>
                    <button class="btn btn-outline-success btn-sm" onclick="exportPayroll()">
                        <i class="bi bi-download"></i> Export Excel
                    </button>
                    <button class="btn btn-outline-danger btn-sm" onclick="printPayroll()">
                        <i class="bi bi-printer"></i> Print
                    </button>
                </div>
            </div>
            <div class="card-body p-3">
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

<style>
/* Statistics Cards Styling */
.statistics-card {
    transition: all 0.3s ease;
    border-radius: 12px;
    overflow: hidden;
}

.statistics-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15) !important;
}

.statistics-card .card-body {
    position: relative;
    overflow: hidden;
}

.statistics-card .card-body::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    transition: all 0.3s ease;
    opacity: 0;
}

.statistics-card:hover .card-body::before {
    opacity: 1;
    transform: scale(1.2);
}

.statistics-card i {
    transition: all 0.3s ease;
}

.statistics-card:hover i {
    transform: scale(1.1);
}

/* Custom Card Styling */
.card {
    border-radius: 10px;
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.card-header {
    border-radius: 10px 10px 0 0 !important;
}

/* Page Content Spacing */
.main-content {
    padding: 1rem 0;
}

.main-content .container-fluid {
    padding: 0 15px;
}

/* Compact spacing for better space utilization */
.mb-4 {
    margin-bottom: 1rem !important;
}

.mb-3 {
    margin-bottom: 0.75rem !important;
}

.p-3 {
    padding: 0.75rem !important;
}

.py-2 {
    padding-top: 0.5rem !important;
    padding-bottom: 0.5rem !important;
}

.g-2 > * {
    padding: 0.25rem;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .statistics-card .card-body {
        padding: 0.75rem;
    }
    
    .statistics-card h5 {
        font-size: 1.1rem;
    }
    
    .statistics-card i {
        font-size: 1.5rem !important;
    }
}

@media (max-width: 992px) {
    .main-content .container-fluid {
        padding: 0 10px;
    }
    
    .statistics-card .card-body {
        padding: 0.65rem;
    }
    
    .d-flex.gap-2 {
        gap: 0.5rem !important;
    }
    
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.8rem;
    }
}

@media (max-width: 768px) {
    .main-content {
        padding: 0.5rem 0;
    }
    
    .main-content .container-fluid {
        padding: 0 5px;
    }
    
    .statistics-card .card-body {
        padding: 0.5rem;
        text-align: center;
    }
    
    .statistics-card h5 {
        font-size: 1rem;
        margin-bottom: 0.25rem;
    }
    
    .statistics-card small {
        font-size: 0.7rem;
    }
    
    .statistics-card i {
        font-size: 1.3rem !important;
        margin-bottom: 0.25rem;
    }
    
    .d-flex.justify-content-between {
        flex-direction: column;
        gap: 1rem;
    }
    
    .d-flex.justify-content-between .d-flex {
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .form-control, .form-select {
        font-size: 0.8rem;
    }
    
    .btn-sm {
        padding: 0.2rem 0.4rem;
        font-size: 0.75rem;
    }
    
    .card-body {
        padding: 0.75rem !important;
    }
}

@media (max-width: 576px) {
    .statistics-card {
        margin-bottom: 0.5rem;
    }
    
    .statistics-card .card-body {
        padding: 0.4rem;
    }
    
    .statistics-card h5 {
        font-size: 0.9rem;
    }
    
    .statistics-card small {
        font-size: 0.65rem;
    }
    
    .col-xl-3 {
        flex: 0 0 50%;
        max-width: 50%;
    }
    
    .card-header h6 {
        font-size: 0.9rem;
    }
    
    .card-body {
        padding: 0.5rem !important;
    }
    
    .table-responsive {
        font-size: 0.8rem;
    }
    
    .btn-group-sm .btn {
        padding: 0.15rem 0.3rem;
        font-size: 0.7rem;
    }
}

/* Smooth Transitions */
* {
    transition: all 0.2s ease;
}

/* Table Improvements */
.table-responsive {
    border-radius: 8px;
}

.table th {
    font-weight: 600;
    font-size: 0.9rem;
}

@media (max-width: 768px) {
    .table th, .table td {
        padding: 0.5rem 0.25rem;
        font-size: 0.8rem;
    }
    
    .badge {
        font-size: 0.7rem;
    }
}
</style>

<?php include '../../layouts/footer.php'; ?>