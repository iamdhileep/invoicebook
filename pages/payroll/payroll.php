<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Payroll';

include '../../layouts/header.php';
include '../../layouts/sidebar.php';

// Get current month and year
$currentMonth = date('Y-m');
$monthName = date('F Y');

// Get all employees with their salary details
$employees = $conn->query("SELECT * FROM employees ORDER BY employee_name ASC");
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Payroll Management</h1>
            <p class="text-muted">Manage employee salaries and payroll for <?= $monthName ?></p>
        </div>
        <div>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#payrollModal">
                <i class="bi bi-currency-rupee"></i> Process Payroll
            </button>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-people me-2"></i>Employee Salary Details</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped" id="payrollTable">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Position</th>
                                    <th>Monthly Salary</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($employees && mysqli_num_rows($employees) > 0): ?>
                                    <?php while ($employee = $employees->fetch_assoc()): ?>
                                        <?php
                                        // Check if payroll already processed for current month
                                        $payrollQuery = $conn->prepare("SELECT * FROM payroll WHERE employee_id = ? AND month = ?");
                                        $payrollQuery->bind_param("is", $employee['id'], $currentMonth);
                                        $payrollQuery->execute();
                                        $payrollRecord = $payrollQuery->get_result()->fetch_assoc();
                                        $isProcessed = !empty($payrollRecord);
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if (!empty($employee['photo']) && file_exists('../../' . $employee['photo'])): ?>
                                                        <img src="../../<?= htmlspecialchars($employee['photo']) ?>" 
                                                             class="rounded-circle me-2" 
                                                             style="width: 40px; height: 40px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center me-2" 
                                                             style="width: 40px; height: 40px;">
                                                            <i class="bi bi-person text-white"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <strong><?= htmlspecialchars($employee['employee_name']) ?></strong>
                                                        <br><small class="text-muted"><?= htmlspecialchars($employee['employee_code']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($employee['position']) ?></td>
                                            <td class="text-success fw-bold">₹<?= number_format($employee['monthly_salary'], 2) ?></td>
                                            <td>
                                                <?php if ($isProcessed): ?>
                                                    <span class="badge bg-success">Processed</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <?php if ($isProcessed): ?>
                                                        <button class="btn btn-outline-info view-payroll" 
                                                                data-employee-id="<?= $employee['id'] ?>"
                                                                data-employee-name="<?= htmlspecialchars($employee['employee_name']) ?>">
                                                            <i class="bi bi-eye"></i> View
                                                        </button>
                                                        <button class="btn btn-outline-primary reprocess-payroll" 
                                                                data-employee-id="<?= $employee['id'] ?>"
                                                                data-salary="<?= $employee['monthly_salary'] ?>">
                                                            <i class="bi bi-arrow-clockwise"></i> Reprocess
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-outline-success process-individual" 
                                                                data-employee-id="<?= $employee['id'] ?>"
                                                                data-employee-name="<?= htmlspecialchars($employee['employee_name']) ?>"
                                                                data-salary="<?= $employee['monthly_salary'] ?>">
                                                            <i class="bi bi-currency-rupee"></i> Process
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            No employees found. <a href="../employees/employees.php">Add employees</a> first.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Payroll Summary</h6>
                </div>
                <div class="card-body">
                    <?php
                    $totalEmployees = 0;
                    $totalSalary = 0;
                    $processedCount = 0;
                    $totalProcessed = 0;

                    $result = $conn->query("SELECT COUNT(*) as count, SUM(monthly_salary) as total_salary FROM employees");
                    if ($result && $row = $result->fetch_assoc()) {
                        $totalEmployees = $row['count'] ?? 0;
                        $totalSalary = $row['total_salary'] ?? 0;
                    }

                    $result = $conn->query("SELECT COUNT(*) as count, SUM(net_salary) as total_processed FROM payroll WHERE month = '$currentMonth'");
                    if ($result && $row = $result->fetch_assoc()) {
                        $processedCount = $row['count'] ?? 0;
                        $totalProcessed = $row['total_processed'] ?? 0;
                    }

                    $pendingCount = $totalEmployees - $processedCount;
                    $pendingSalary = $totalSalary - $totalProcessed;
                    ?>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Total Employees:</span>
                            <strong><?= $totalEmployees ?></strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Processed:</span>
                            <strong class="text-success"><?= $processedCount ?></strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Pending:</span>
                            <strong class="text-warning"><?= $pendingCount ?></strong>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Total Payroll:</span>
                            <strong class="text-primary">₹<?= number_format($totalSalary, 2) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Processed Amount:</span>
                            <strong class="text-success">₹<?= number_format($totalProcessed, 2) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Pending Amount:</span>
                            <strong class="text-warning">₹<?= number_format($pendingSalary, 2) ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-success" onclick="processAllPayroll()">
                            Process All Pending
                        </button>
                        <a href="../../payroll_report.php" class="btn btn-outline-primary">
                            Generate Report
                        </a>
                        <a href="../employees/employees.php" class="btn btn-outline-secondary">
                            Manage Employees
                        </a>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">Month Selection</h6>
                </div>
                <div class="card-body">
                    <form method="GET">
                        <div class="mb-3">
                            <label class="form-label">Select Month</label>
                            <input type="month" name="month" class="form-control" value="<?= $currentMonth ?>" onchange="this.form.submit()">
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Process Payroll Modal -->
<div class="modal fade" id="payrollModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Process Payroll</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="../../process_payroll.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="month" value="<?= $currentMonth ?>">
                    <input type="hidden" name="employee_id" id="modal_employee_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Employee</label>
                        <input type="text" id="modal_employee_name" class="form-control" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Basic Salary</label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" name="basic_salary" id="modal_basic_salary" class="form-control" step="0.01" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Allowances</label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" name="allowances" class="form-control" step="0.01" value="0">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Deductions</label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" name="deductions" class="form-control" step="0.01" value="0">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Bonus</label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" name="bonus" class="form-control" step="0.01" value="0">
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <small><strong>Net Salary</strong> will be calculated as: Basic Salary + Allowances + Bonus - Deductions</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Process Payroll</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Process individual payroll
document.querySelectorAll('.process-individual').forEach(button => {
    button.addEventListener('click', function() {
        const employeeId = this.dataset.employeeId;
        const employeeName = this.dataset.employeeName;
        const salary = this.dataset.salary;
        
        document.getElementById('modal_employee_id').value = employeeId;
        document.getElementById('modal_employee_name').value = employeeName;
        document.getElementById('modal_basic_salary').value = salary;
        
        new bootstrap.Modal(document.getElementById('payrollModal')).show();
    });
});

// Process all payroll
function processAllPayroll() {
    if (confirm('Are you sure you want to process payroll for all pending employees?')) {
        // This would need to be implemented with proper backend logic
        alert('Process all payroll functionality will be implemented.');
    }
}

// View payroll details
document.querySelectorAll('.view-payroll').forEach(button => {
    button.addEventListener('click', function() {
        const employeeId = this.dataset.employeeId;
        const employeeName = this.dataset.employeeName;
        
        // This would show detailed payroll information
        alert(`View payroll details for ${employeeName} - Feature to be implemented`);
    });
});

// Reprocess payroll
document.querySelectorAll('.reprocess-payroll').forEach(button => {
    button.addEventListener('click', function() {
        const employeeId = this.dataset.employeeId;
        const salary = this.dataset.salary;
        
        if (confirm('Are you sure you want to reprocess this employee\'s payroll?')) {
            // This would reprocess the payroll
            alert('Reprocess payroll functionality will be implemented.');
        }
    });
});
</script>

<?php include '../../layouts/footer.php'; ?>