<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Employees';

include '../../layouts/header.php';
include '../../layouts/sidebar.php';

// Get all employees
$employees = $conn->query("SELECT * FROM employees ORDER BY employee_name ASC");
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Employee Management</h1>
            <p class="text-muted">Manage your team members and employee details</p>
        </div>
        <div>
            <a href="../../add_employee.php" class="btn btn-primary">
                <i class="bi bi-person-plus"></i> Add Employee
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="employeesTable">
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>Name</th>
                            <th>Employee Code</th>
                            <th>Position</th>
                            <th>Phone</th>
                            <th>Monthly Salary</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($employees && mysqli_num_rows($employees) > 0): ?>
                            <?php while ($employee = $employees->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($employee['photo']) && file_exists('../../' . $employee['photo'])): ?>
                                            <img src="../../<?= htmlspecialchars($employee['photo']) ?>" 
                                                 alt="Employee Photo" 
                                                 class="rounded-circle" 
                                                 style="width: 40px; height: 40px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center" 
                                                 style="width: 40px; height: 40px;">
                                                <i class="bi bi-person text-white"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($employee['employee_name']) ?></strong>
                                            <?php if (!empty($employee['email'])): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($employee['email']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-primary"><?= htmlspecialchars($employee['employee_code']) ?></span></td>
                                    <td><?= htmlspecialchars($employee['position']) ?></td>
                                    <td><?= htmlspecialchars($employee['phone']) ?></td>
                                    <td class="text-success fw-bold">₹<?= number_format($employee['monthly_salary'], 2) ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="../../edit_employee.php?id=<?= $employee['id'] ?>" class="btn btn-outline-primary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="../../view_employee.php?id=<?= $employee['id'] ?>" class="btn btn-outline-info">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="../../delete_employee.php?id=<?= $employee['id'] ?>" 
                                               class="btn btn-outline-danger" 
                                               onclick="return confirm('Are you sure you want to delete this employee?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="bi bi-people fs-1 mb-3"></i>
                                    <br>No employees found
                                    <br><a href="../../add_employee.php" class="btn btn-primary mt-2">Add First Employee</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-person-plus fs-1 text-primary mb-3"></i>
                    <h5>Add Employee</h5>
                    <p class="text-muted">Add new team members</p>
                    <a href="../../add_employee.php" class="btn btn-primary">Add Employee</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-calendar-check fs-1 text-success mb-3"></i>
                    <h5>Attendance</h5>
                    <p class="text-muted">Mark daily attendance</p>
                    <a href="../attendance/attendance.php" class="btn btn-success">Mark Attendance</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-currency-rupee fs-1 text-warning mb-3"></i>
                    <h5>Payroll</h5>
                    <p class="text-muted">Manage employee payroll</p>
                    <a href="../payroll/payroll.php" class="btn btn-warning">Manage Payroll</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-calendar3 fs-1 text-info mb-3"></i>
                    <h5>Calendar</h5>
                    <p class="text-muted">View attendance calendar</p>
                    <a href="../../attendance-calendar.php" class="btn btn-info">View Calendar</a>
                </div>
            </div>
        </div>
    </div>

    <?php
    // Get employee statistics
    $totalEmployees = 0;
    $totalSalary = 0;
    
    $result = $conn->query("SELECT COUNT(*) as count, SUM(monthly_salary) as total_salary FROM employees");
    if ($result && $row = $result->fetch_assoc()) {
        $totalEmployees = $row['count'] ?? 0;
        $totalSalary = $row['total_salary'] ?? 0;
    }
    ?>

    <div class="row mt-4">
        <div class="col-md-6">
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
        <div class="col-md-6">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Monthly Salary</h6>
                            <h2 class="mb-0">₹<?= number_format($totalSalary, 2) ?></h2>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-currency-rupee"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../layouts/footer.php'; ?>