<?php
session_start();
if (!isset($_SESSION['admin'])) {
  header("Location: ../login.php");
  exit;
}
include '../db.php';
include '../includes/header.php';
include '../config.php';
?>

<div class="page-header">
  <h1 class="page-title">
    <i class="bi bi-currency-rupee me-2"></i>
    Payroll Management
  </h1>
  <p class="text-muted mb-0">Calculate and manage employee payroll</p>
</div>

<div class="row">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="bi bi-calculator me-2"></i>
          Generate Payroll
        </h5>
      </div>
      <div class="card-body">
        <form method="GET" class="mb-4">
          <div class="row g-3">
            <div class="col-md-4">
              <label for="month" class="form-label">Select Month</label>
              <input type="month" name="month" id="month" class="form-control" value="<?= $_GET['month'] ?? date('Y-m') ?>" required>
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-search me-2"></i>
                Generate Payroll
              </button>
            </div>
          </div>
        </form>

        <?php
        if (isset($_GET['month'])):
            $month = $_GET['month'];
            $startDate = $month . '-01';
            $endDate = date("Y-m-t", strtotime($startDate));

            echo "<div class='alert alert-info'>";
            echo "<strong>Payroll for:</strong> " . date("F Y", strtotime($startDate));
            echo "</div>";

            // Get employees and calculate payroll
            $employees = mysqli_query($conn, "SELECT * FROM employees WHERE status = 'Active' ORDER BY name");

            if ($employees) {
                if (mysqli_num_rows($employees) > 0):
                    $totalPayroll = 0;
                    while ($employee = mysqli_fetch_assoc($employees)):
                        $empId = $employee['employee_id'];
                        $basicSalary = $employee['salary'];

                        // Calculate attendance
                        $presentQuery = "SELECT COUNT(*) as days FROM attendance WHERE employee_id = $empId AND date BETWEEN '$startDate' AND '$endDate' AND status IN ('Present', 'Late')";
                        $presentResult = mysqli_query($conn, $presentQuery);
                        $presentDays = ($presentResult && ($row = mysqli_fetch_assoc($presentResult))) ? $row['days'] : 0;

                        $absentQuery = "SELECT COUNT(*) as days FROM attendance WHERE employee_id = $empId AND date BETWEEN '$startDate' AND '$endDate' AND status = 'Absent'";
                        $absentResult = mysqli_query($conn, $absentQuery);
                        $absentDays = ($absentResult && ($row = mysqli_fetch_assoc($absentResult))) ? $row['days'] : 0;

                        $totalDays = date('t', strtotime($startDate));
                        $dailyRate = $basicSalary / $totalDays;
                        $deductions = $absentDays * $dailyRate;
                        $netSalary = $basicSalary - $deductions;

                        $totalPayroll += $netSalary;

                        echo "<tr>
                            <td>{$employee['name']}</td>
                            <td>{$employee['position']}</td>
                            <td>₹ " . number_format($basicSalary, 2) . "</td>
                            <td><span class='badge bg-success'>{$presentDays}</span></td>
                            <td><span class='badge bg-danger'>{$absentDays}</span></td>
                            <td>₹ " . number_format($deductions, 2) . "</td>
                            <td class='fw-bold'>₹ " . number_format($netSalary, 2) . "</td>
                            <td>
                                <a href='../payroll_report.php?emp_id={$empId}&month={$month}' class='btn btn-sm btn-outline-primary'>
                                    <i class='bi bi-file-earmark-pdf'></i>
                                </a>
                            </td>
                        </tr>";
                    endwhile;
                else:
                    echo "<div class='alert alert-warning'>No active employees found.</div>";
                endif;
            } else {
                echo '<tr><td colspan="8" class="text-danger text-center">Failed to fetch employees: ' . htmlspecialchars(mysqli_error($conn)) . '</td></tr>';
            }
        endif;
        ?>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="bi bi-graph-up me-2"></i>
          Payroll Summary
        </h5>
      </div>
      <div class="card-body">
        <?php
        $currentMonth = date('Y-m');
        $lastMonth = date('Y-m', strtotime('-1 month'));

        // Current month payroll
        $currentPayroll = 0;
        $empQuery = mysqli_query($conn, "SELECT SUM(salary) as total FROM employees WHERE status = 'Active'");
        $currentPayroll = mysqli_fetch_assoc($empQuery)['total'] ?? 0;

        // Average monthly payroll
        $avgQuery = mysqli_query($conn, "SELECT AVG(salary) as avg FROM employees WHERE status = 'Active'");
        $avgSalary = mysqli_fetch_assoc($avgQuery)['avg'] ?? 0;

        // Total employees
        $totalEmp = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM employees WHERE status = 'Active'"))['total'] ?? 0;
        ?>

        <div class="row g-3 text-center">
          <div class="col-12">
            <div class="border rounded p-3">
              <h6 class="text-muted">Current Month</h6>
              <h4 class="text-primary">₹ <?= number_format($currentPayroll, 2) ?></h4>
            </div>
          </div>
          <div class="col-12">
            <div class="border rounded p-3">
              <h6 class="text-muted">Average Salary</h6>
              <h4 class="text-success">₹ <?= number_format($avgSalary, 2) ?></h4>
            </div>
          </div>
          <div class="col-12">
            <div class="border rounded p-3">
              <h6 class="text-muted">Total Employees</h6>
              <h4 class="text-info"><?= $totalEmp ?></h4>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="bi bi-lightning me-2"></i>
          Quick Actions
        </h5>
      </div>
      <div class="card-body">
        <div class="d-grid gap-2">
          <a href="../payroll_report.php" class="btn btn-outline-primary">
            <i class="bi bi-file-earmark-text me-2"></i>
            View All Reports
          </a>
          <a href="attendance.php" class="btn btn-outline-success">
            <i class="bi bi-calendar-check me-2"></i>
            Mark Attendance
          </a>
          <a href="employees.php" class="btn btn-outline-info">
            <i class="bi bi-people me-2"></i>
            Manage Employees
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>