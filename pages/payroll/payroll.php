<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Payroll Management';

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Payroll Management</h1>
            <p class="text-muted">Manage employee salaries and payroll processing</p>
        </div>
        <div>
            <a href="../../payroll_report.php" class="btn btn-outline-primary me-2">
                <i class="bi bi-file-earmark-text"></i> Payroll Report
            </a>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#processPayrollModal">
                <i class="bi bi-play-circle"></i> Process Payroll
            </button>
        </div>
    </div>

    <!-- Payroll Summary -->
    <?php
    $currentMonth = date('Y-m');
    $totalSalaries = 0;
    $processedPayroll = 0;
    $pendingPayroll = 0;
    $totalEmployees = 0;

    // Get total employees and salaries
    $result = mysqli_query($conn, "SELECT COUNT(*) as total, SUM(monthly_salary) as total_salary FROM employees");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $totalEmployees = $row['total'] ?? 0;
        $totalSalaries = $row['total_salary'] ?? 0;
    }

    // Get processed payroll for current month
    $result = mysqli_query($conn, "SELECT SUM(net_salary) as processed FROM payroll WHERE DATE_FORMAT(pay_date, '%Y-%m') = '$currentMonth'");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $processedPayroll = $row['processed'] ?? 0;
    }

    $pendingPayroll = $totalSalaries - $processedPayroll;
    ?>

    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Employees</h6>
                            <h2 class="mb-0"><?= $totalEmployees ?></h2>
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
                            <h6 class="card-title mb-0">Monthly Payroll</h6>
                            <h2 class="mb-0">₹ <?= number_format($totalSalaries, 0) ?></h2>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-currency-rupee"></i>
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
                            <h6 class="card-title mb-0">Processed This Month</h6>
                            <h2 class="mb-0">₹ <?= number_format($processedPayroll, 0) ?></h2>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-check-circle"></i>
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
                            <h6 class="card-title mb-0">Pending Payment</h6>
                            <h2 class="mb-0">₹ <?= number_format($pendingPayroll, 0) ?></h2>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-clock-history"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Employee Payroll Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Employee Payroll - <?= date('F Y') ?></h5>
            <div>
                <select id="monthFilter" class="form-select form-select-sm" style="width: auto;">
                    <?php
                    for ($i = 0; $i < 12; $i++) {
                        $month = date('Y-m', strtotime("-$i months"));
                        $monthName = date('F Y', strtotime("-$i months"));
                        $selected = ($month === $currentMonth) ? 'selected' : '';
                        echo "<option value='$month' $selected>$monthName</option>";
                    }
                    ?>
                </select>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="payrollTable" class="table table-striped data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Code</th>
                            <th>Position</th>
                            <th>Base Salary</th>
                            <th>Attendance</th>
                            <th>Deductions</th>
                            <th>Net Salary</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Get employee payroll data
                        $query = "SELECT e.employee_id, e.name, e.code, e.position, e.monthly_salary,
                                 p.gross_salary, p.deductions, p.net_salary, p.pay_date, p.status, p.payroll_id,
                                 (SELECT COUNT(*) FROM attendance a WHERE a.employee_id = e.employee_id 
                                  AND DATE_FORMAT(a.attendance_date, '%Y-%m') = '$currentMonth' 
                                  AND a.status IN ('Present', 'Late', 'Half Day')) as working_days
                                 FROM employees e
                                 LEFT JOIN payroll p ON e.employee_id = p.employee_id 
                                 AND DATE_FORMAT(p.pay_date, '%Y-%m') = '$currentMonth'
                                 ORDER BY e.name ASC";
                        
                        $result = mysqli_query($conn, $query);
                        if ($result) {
                            while ($row = mysqli_fetch_assoc($result)) {
                                $workingDays = $row['working_days'] ?? 0;
                                $totalWorkingDays = date('t'); // Total days in current month
                                $attendancePercentage = $totalWorkingDays > 0 ? round(($workingDays / $totalWorkingDays) * 100, 1) : 0;
                                
                                $baseSalary = $row['monthly_salary'];
                                $grossSalary = $row['gross_salary'] ?? $baseSalary;
                                $deductions = $row['deductions'] ?? 0;
                                $netSalary = $row['net_salary'] ?? ($grossSalary - $deductions);
                                
                                $status = $row['status'] ?? 'Pending';
                                $statusClass = '';
                                switch ($status) {
                                    case 'Paid': $statusClass = 'bg-success'; break;
                                    case 'Pending': $statusClass = 'bg-warning'; break;
                                    case 'Processing': $statusClass = 'bg-info'; break;
                                    default: $statusClass = 'bg-secondary';
                                }

                                echo "<tr>";
                                echo "<td><strong>" . htmlspecialchars($row['name']) . "</strong></td>";
                                echo "<td><span class='badge bg-secondary'>" . htmlspecialchars($row['code']) . "</span></td>";
                                echo "<td>" . htmlspecialchars($row['position']) . "</td>";
                                echo "<td><strong class='text-primary'>₹ " . number_format($baseSalary, 2) . "</strong></td>";
                                echo "<td>";
                                echo "<span class='badge bg-light text-dark'>{$workingDays}/{$totalWorkingDays} days</span><br>";
                                echo "<small class='text-muted'>{$attendancePercentage}%</small>";
                                echo "</td>";
                                echo "<td><span class='text-danger'>₹ " . number_format($deductions, 2) . "</span></td>";
                                echo "<td><strong class='text-success'>₹ " . number_format($netSalary, 2) . "</strong></td>";
                                echo "<td><span class='badge {$statusClass}'>{$status}</span></td>";
                                echo "<td>";
                                echo "<div class='btn-group btn-group-sm'>";
                                
                                if ($row['payroll_id']) {
                                    echo "<a href='../../generate_payslip.php?id={$row['payroll_id']}' class='btn btn-outline-primary' data-bs-toggle='tooltip' title='View Payslip'>";
                                    echo "<i class='bi bi-file-earmark-text'></i>";
                                    echo "</a>";
                                    if ($status !== 'Paid') {
                                        echo "<button class='btn btn-outline-success mark-paid' data-id='{$row['payroll_id']}' data-bs-toggle='tooltip' title='Mark as Paid'>";
                                        echo "<i class='bi bi-check-circle'></i>";
                                        echo "</button>";
                                    }
                                } else {
                                    echo "<button class='btn btn-outline-primary process-individual' data-employee-id='{$row['employee_id']}' data-bs-toggle='tooltip' title='Process Payroll'>";
                                    echo "<i class='bi bi-play-circle'></i>";
                                    echo "</button>";
                                }
                                
                                echo "</div>";
                                echo "</td>";
                                echo "</tr>";
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Process Payroll Modal -->
<div class="modal fade" id="processPayrollModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Process Monthly Payroll</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="processPayrollForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Pay Date</label>
                        <input type="date" name="pay_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Pay Period</label>
                        <input type="month" name="pay_period" class="form-control" value="<?= date('Y-m') ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Global Deductions (Optional)</label>
                        <input type="number" name="global_deductions" class="form-control" placeholder="0.00" step="0.01">
                        <div class="form-text">This will be applied to all employees</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Bonus/Incentive (Optional)</label>
                        <input type="number" name="global_bonus" class="form-control" placeholder="0.00" step="0.01">
                        <div class="form-text">This will be added to all employees</div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        This will process payroll for all employees for the selected period. 
                        Salary calculations will be based on attendance records.
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

<!-- Individual Payroll Modal -->
<div class="modal fade" id="individualPayrollModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Process Individual Payroll</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="individualPayrollForm">
                <div class="modal-body">
                    <input type="hidden" id="individual_employee_id" name="employee_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Pay Date</label>
                        <input type="date" name="pay_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Gross Salary</label>
                        <input type="number" id="individual_gross_salary" name="gross_salary" class="form-control" step="0.01" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Deductions</label>
                        <input type="number" name="deductions" class="form-control" placeholder="0.00" step="0.01">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Bonus/Incentive</label>
                        <input type="number" name="bonus" class="form-control" placeholder="0.00" step="0.01">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Optional notes"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Process</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$additional_scripts = '
<script>
    $(document).ready(function() {
        // Initialize DataTable
        const table = $("#payrollTable").DataTable({
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            responsive: true,
            order: [[0, "asc"]],
            columnDefs: [
                { orderable: false, targets: [8] }
            ]
        });

        // Month filter
        $("#monthFilter").change(function() {
            const selectedMonth = this.value;
            // Reload page with selected month
            window.location.href = "?month=" + selectedMonth;
        });

        // Process all payroll
        $("#processPayrollForm").submit(function(e) {
            e.preventDefault();
            
            if (confirm("Are you sure you want to process payroll for all employees? This action cannot be undone.")) {
                const formData = $(this).serialize();
                
                $.post("../../process_payroll.php", formData + "&process_all=1", function(response) {
                    if (response.success) {
                        showAlert("Payroll processed successfully for " + response.processed_count + " employees", "success");
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        showAlert("Error processing payroll: " + response.message, "danger");
                    }
                }, "json").fail(function() {
                    showAlert("Error occurred while processing payroll", "danger");
                });
                
                $("#processPayrollModal").modal("hide");
            }
        });

        // Process individual payroll
        $(".process-individual").click(function() {
            const employeeId = $(this).data("employee-id");
            const row = $(this).closest("tr");
            const baseSalary = row.find("td:nth-child(4)").text().replace(/[₹,]/g, "").trim();
            
            $("#individual_employee_id").val(employeeId);
            $("#individual_gross_salary").val(baseSalary);
            $("#individualPayrollModal").modal("show");
        });

        $("#individualPayrollForm").submit(function(e) {
            e.preventDefault();
            
            const formData = $(this).serialize();
            
            $.post("../../process_payroll.php", formData, function(response) {
                if (response.success) {
                    showAlert("Individual payroll processed successfully", "success");
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert("Error processing payroll: " + response.message, "danger");
                }
            }, "json").fail(function() {
                showAlert("Error occurred while processing payroll", "danger");
            });
            
            $("#individualPayrollModal").modal("hide");
        });

        // Mark as paid
        $(".mark-paid").click(function() {
            const payrollId = $(this).data("id");
            const row = $(this).closest("tr");
            
            if (confirm("Mark this payroll as paid?")) {
                $.post("../../update_payroll_status.php", {
                    payroll_id: payrollId,
                    status: "Paid"
                }, function(response) {
                    if (response.success) {
                        showAlert("Payroll marked as paid", "success");
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showAlert("Error updating payroll status: " + response.message, "danger");
                    }
                }, "json").fail(function() {
                    showAlert("Error occurred while updating status", "danger");
                });
            }
        });

        // Calculate net salary in individual form
        $("#individualPayrollForm input[name=gross_salary], #individualPayrollForm input[name=deductions], #individualPayrollForm input[name=bonus]").on("input", function() {
            const grossSalary = parseFloat($("#individualPayrollForm input[name=gross_salary]").val()) || 0;
            const deductions = parseFloat($("#individualPayrollForm input[name=deductions]").val()) || 0;
            const bonus = parseFloat($("#individualPayrollForm input[name=bonus]").val()) || 0;
            const netSalary = grossSalary - deductions + bonus;
            
            // Show calculated net salary (you can add a field to display this)
            console.log("Net Salary:", netSalary);
        });
    });
</script>
';

include '../../layouts/footer.php';
?>