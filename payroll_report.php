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
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="h5 mb-0">📄 Payroll Report</h1>
                <p class="text-muted small">Detailed salary calculations for <?= date('F Y', mktime(0, 0, 0, $month, 1, $year)) ?></p>
            </div>
            <div>
                <button class="btn btn-outline-success btn-sm" onclick="exportReport()">
                    <i class="bi bi-download"></i> Export PDF
                </button>
                <button class="btn btn-outline-primary btn-sm" onclick="printReport()">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>

        <!-- Month Selection -->
        <div class="card mb-3 border-0 shadow-sm">
            <div class="card-header bg-light border-0 py-2">
                <h6 class="mb-0 text-dark"><i class="bi bi-calendar me-2"></i>Select Report Period</h6>
            </div>
            <div class="card-body p-3">
                <form method="GET" class="row g-2">
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
                        <h5 class="mb-1 fw-bold" style="color: #7b1fa2;"><?= $totalEmployees ?></h5>
                        <small class="text-muted">Total Employees</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-wallet-fill fs-3" style="color: #ff9800;"></i>
                        </div>
                        <h5 class="mb-1 fw-bold" style="color: #ff9800;">₹<?= number_format($totalSalaryBudget, 0) ?></h5>
                        <small class="text-muted">Salary Budget</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-currency-rupee fs-3" style="color: #388e3c;"></i>
                        </div>
                        <h5 class="mb-1 fw-bold" style="color: #388e3c;">₹<?= number_format($totalEarnedSalary, 0) ?></h5>
                        <small class="text-muted">Actual Payout</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payroll Details -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light border-0 py-2">
                <h6 class="mb-0 text-dark">Payroll Details - <?= date('F Y', mktime(0, 0, 0, $month, 1, $year)) ?></h6>
            </div>
            <div class="card-body p-3">
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
            <div class="row g-2 mt-3">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-0 py-2">
                            <h6 class="mb-0 text-dark">Cost Analysis</h6>
                        </div>
                        <div class="card-body p-3">
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
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-0 py-2">
                            <h6 class="mb-0 text-dark">Attendance Summary</h6>
                        </div>
                        <div class="card-body p-3">
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
            </div>
        <?php endif; ?>
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

@media print {
    .btn, .card-header, .sidebar, .main-header {
        display: none !important;
    }
    .main-content {
        margin-left: 0 !important;
        margin-top: 0 !important;
        padding: 0 !important;
    }
    .card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
    }
    .container-fluid {
        padding: 0 !important;
    }
    .statistics-card {
        break-inside: avoid;
        page-break-inside: avoid;
    }
}
</style>

<?php include 'layouts/footer.php'; ?>
