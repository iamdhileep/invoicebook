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
    <i class="bi bi-people me-2"></i>
    Employee Management
  </h1>
  <p class="text-muted mb-0">Manage your employees and their information</p>
</div>

<div class="row">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
          <h5 class="card-title mb-0">
            <i class="bi bi-list-ul me-2"></i>
            Employee List
          </h5>
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
            <i class="bi bi-person-plus me-2"></i>
            Add Employee
          </button>
        </div>
      </div>
      <div class="card-body">
        <table id="employeeTable" class="table table-striped dataTable">
          <thead class="table-dark">
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Position</th>
              <th>Phone</th>
              <th>Salary</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $result = mysqli_query($conn, "SELECT * FROM employees ORDER BY id DESC");
            if (!$result) {
                echo '<tr><td colspan="7" class="text-danger text-center">Failed to fetch employees: ' . htmlspecialchars(mysqli_error($conn)) . '</td></tr>';
            } else if (mysqli_num_rows($result) === 0) {
                echo '<tr><td colspan="7" class="text-center">No employees found.</td></tr>';
            } else {
                while ($row = mysqli_fetch_assoc($result)) {
                    $statusClass = $row['status'] === 'Active' ? 'success' : 'danger';
                    echo "<tr>
                        <td>{$row['emp_id']}</td>
                        <td>{$row['name']}</td>
                        <td>{$row['position']}</td>
                        <td>{$row['phone']}</td>
                        <td>₹ " . number_format($row['salary'], 2) . "</td>
                        <td><span class='badge bg-{$statusClass}'>{$row['status']}</span></td>
                        <td>
                            <div class='btn-group' role='group'>
                                <a href='../edit_employee.php?id={$row['emp_id']}' class='btn btn-sm btn-outline-primary'>
                                    <i class='bi bi-pencil'></i>
                                </a>
                                <a href='../delete_employee.php?id={$row['emp_id']}' class='btn btn-sm btn-outline-danger' onclick='return confirm(\"Are you sure?\")'>
                                    <i class='bi bi-trash'></i>
                                </a>
                            </div>
                        </td>
                    </tr>";
                }
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="bi bi-graph-up me-2"></i>
          Employee Stats
        </h5>
      </div>
      <div class="card-body">
        <?php
        $totalEmployeesResult = mysqli_query($conn, "SELECT COUNT(*) as total FROM employees");
        if (!$totalEmployeesResult) {
            $totalEmployees = 0;
            echo '<div class="text-danger">Failed to fetch total employees: ' . htmlspecialchars(mysqli_error($conn)) . '</div>';
        } else {
            $totalEmployees = mysqli_fetch_assoc($totalEmployeesResult)['total'] ?? 0;
        }

        $activeEmployeesResult = mysqli_query($conn, "SELECT COUNT(*) as total FROM employees WHERE status = 'Active'");
        if (!$activeEmployeesResult) {
            $activeEmployees = 0;
            echo '<div class="text-danger">Failed to fetch active employees: ' . htmlspecialchars(mysqli_error($conn)) . '</div>';
        } else {
            $activeEmployees = mysqli_fetch_assoc($activeEmployeesResult)['total'] ?? 0;
        }

        $totalSalaryResult = mysqli_query($conn, "SELECT SUM(salary) as total FROM employees WHERE status = 'Active'");
        if (!$totalSalaryResult) {
            $totalSalary = 0;
            echo '<div class="text-danger">Failed to fetch total salary: ' . htmlspecialchars(mysqli_error($conn)) . '</div>';
        } else {
            $totalSalary = mysqli_fetch_assoc($totalSalaryResult)['total'] ?? 0;
        }
        ?>

        <div class="row g-3 text-center">
          <div class="col-12">
            <div class="border rounded p-3">
              <h6 class="text-muted">Total Employees</h6>
              <h4 class="text-primary"><?= $totalEmployees ?></h4>
            </div>
          </div>
          <div class="col-12">
            <div class="border rounded p-3">
              <h6 class="text-muted">Active Employees</h6>
              <h4 class="text-success"><?= $activeEmployees ?></h4>
            </div>
          </div>
          <div class="col-12">
            <div class="border rounded p-3">
              <h6 class="text-muted">Total Salary</h6>
              <h4 class="text-info">₹ <?= number_format($totalSalary, 2) ?></h4>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Add Employee Modal -->
<div class="modal fade" id="addEmployeeModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add New Employee</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form action="../save_employee.php" method="POST">
        <div class="modal-body">
          <div class="mb-3">
            <label for="name" class="form-label">Name</label>
            <input type="text" class="form-control" name="name" required>
          </div>
          <div class="mb-3">
            <label for="position" class="form-label">Position</label>
            <input type="text" class="form-control" name="position" required>
          </div>
          <div class="mb-3">
            <label for="phone" class="form-label">Phone</label>
            <input type="tel" class="form-control" name="phone" required>
          </div>
          <div class="mb-3">
            <label for="salary" class="form-label">Salary</label>
            <input type="number" class="form-control" name="salary" step="0.01" required>
          </div>
          <div class="mb-3">
            <label for="status" class="form-label">Status</label>
            <select class="form-select" name="status" required>
              <option value="Active">Active</option>
              <option value="Inactive">Inactive</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Employee</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>