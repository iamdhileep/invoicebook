<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

include '../db.php';
$page_title = 'HRMS Dashboard';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_employee':
            $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
            $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
            $employee_id = mysqli_real_escape_string($conn, $_POST['employee_id']);
            $email = mysqli_real_escape_string($conn, $_POST['email']);
            $phone = mysqli_real_escape_string($conn, $_POST['phone']);
            $department_id = intval($_POST['department_id']);
            $position = mysqli_real_escape_string($conn, $_POST['position']);
            $date_of_joining = mysqli_real_escape_string($conn, $_POST['date_of_joining']);
            $salary = floatval($_POST['salary']);
            $employment_type = mysqli_real_escape_string($conn, $_POST['employment_type']);
            $status = 'active';
            
            $query = "INSERT INTO hr_employees (employee_id, first_name, last_name, email, phone, department_id, 
                      position, date_of_joining, salary, employment_type, status) 
                      VALUES ('$employee_id', '$first_name', '$last_name', '$email', '$phone', $department_id, 
                      '$position', '$date_of_joining', $salary, '$employment_type', '$status')";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Employee added successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;

        case 'get_employee':
            $id = intval($_POST['id']);
            $query = mysqli_query($conn, "SELECT * FROM hr_employees WHERE id = $id");
            if ($query && $row = mysqli_fetch_assoc($query)) {
                echo json_encode(['success' => true, 'data' => $row]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Employee not found']);
            }
            exit;

        case 'update_employee':
            $id = intval($_POST['id']);
            $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
            $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
            $employee_id = mysqli_real_escape_string($conn, $_POST['employee_id']);
            $email = mysqli_real_escape_string($conn, $_POST['email']);
            $phone = mysqli_real_escape_string($conn, $_POST['phone']);
            $department_id = intval($_POST['department_id']);
            $position = mysqli_real_escape_string($conn, $_POST['position']);
            $salary = floatval($_POST['salary']);
            $employment_type = mysqli_real_escape_string($conn, $_POST['employment_type']);
            $status = mysqli_real_escape_string($conn, $_POST['status']);
            
            $query = "UPDATE hr_employees SET 
                      first_name = '$first_name',
                      last_name = '$last_name',
                      employee_id = '$employee_id',
                      email = '$email',
                      phone = '$phone',
                      department_id = $department_id,
                      position = '$position',
                      salary = $salary,
                      employment_type = '$employment_type',
                      status = '$status'
                      WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Employee updated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;

        case 'delete_employee':
            $id = intval($_POST['id']);
            $query = "UPDATE hr_employees SET status = 'inactive' WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Employee deactivated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;

        case 'mark_attendance':
            $employee_id = intval($_POST['employee_id']);
            $date = date('Y-m-d');
            $check_in = date('Y-m-d H:i:s');
            
            // Check if already checked in today
            $existing = mysqli_query($conn, "SELECT * FROM hr_attendance WHERE employee_id = $employee_id AND DATE(date) = '$date'");
            
            if ($existing && mysqli_num_rows($existing) > 0) {
                // Update checkout
                $query = "UPDATE hr_attendance SET check_out = '$check_in', status = 'present' WHERE employee_id = $employee_id AND DATE(date) = '$date'";
                $action = 'checked out';
            } else {
                // Insert check in
                $query = "INSERT INTO hr_attendance (employee_id, date, check_in, status) VALUES ($employee_id, '$date', '$check_in', 'present')";
                $action = 'checked in';
            }
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => "Successfully $action!"]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;
    }
}

// Get HRMS statistics for dashboard
$totalEmployees = 0;
$activeEmployees = 0;
$presentToday = 0;
$pendingLeaves = 0;

$empQuery = mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_employees,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_employees
    FROM hr_employees
");
if ($empQuery && $row = mysqli_fetch_assoc($empQuery)) {
    $totalEmployees = $row['total_employees'];
    $activeEmployees = $row['active_employees'];
}

$attendanceQuery = mysqli_query($conn, "
    SELECT COUNT(*) as present_today 
    FROM hr_attendance 
    WHERE DATE(date) = CURDATE() AND status = 'present'
");
if ($attendanceQuery && $row = mysqli_fetch_assoc($attendanceQuery)) {
    $presentToday = $row['present_today'];
}

// Handle search and filtering
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$department = $_GET['department'] ?? '';
$employment_type = $_GET['employment_type'] ?? '';

// Build WHERE clause
$where = "WHERE 1=1";
if ($search) {
    $where .= " AND (e.first_name LIKE '%" . mysqli_real_escape_string($conn, $search) . "%' 
                OR e.last_name LIKE '%" . mysqli_real_escape_string($conn, $search) . "%'
                OR e.employee_id LIKE '%" . mysqli_real_escape_string($conn, $search) . "%'
                OR e.email LIKE '%" . mysqli_real_escape_string($conn, $search) . "%')";
}
if ($status) {
    $where .= " AND e.status = '" . mysqli_real_escape_string($conn, $status) . "'";
}
if ($department) {
    $where .= " AND d.department_name = '" . mysqli_real_escape_string($conn, $department) . "'";
}
if ($employment_type) {
    $where .= " AND e.employment_type = '" . mysqli_real_escape_string($conn, $employment_type) . "'";
}

// Get employees with department info
$employees = mysqli_query($conn, "
    SELECT e.*, d.department_name 
    FROM hr_employees e 
    LEFT JOIN hr_departments d ON e.department_id = d.id 
    $where 
    ORDER BY e.first_name, e.last_name
");

// Get departments for filter
$departments = mysqli_query($conn, "SELECT * FROM hr_departments WHERE status = 'active' ORDER BY department_name");

include '../layouts/header.php';
include '../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">👥 HRMS Dashboard</h1>
                <p class="text-muted">Human Resource Management System</p>
            </div>
            <div>
                <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#attendanceModal">
                    <i class="bi bi-clock"></i> Mark Attendance
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                    <i class="bi bi-person-plus"></i> Add Employee
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-people fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $totalEmployees ?></h3>
                        <small class="opacity-75">Total Employees</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-person-check fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $activeEmployees ?></h3>
                        <small class="opacity-75">Active Employees</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-calendar-check fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $presentToday ?></h3>
                        <small class="opacity-75">Present Today</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-exclamation-triangle fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $pendingLeaves ?></h3>
                        <small class="opacity-75">Pending Leaves</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row g-3 mb-4">
            <div class="col-md-2">
                <a href="employee_directory.php" class="btn btn-outline-primary w-100">
                    <i class="bi bi-people me-2"></i>Employee Directory
                </a>
            </div>
            <div class="col-md-2">
                <a href="attendance_management.php" class="btn btn-outline-success w-100">
                    <i class="bi bi-calendar3 me-2"></i>Attendance
                </a>
            </div>
            <div class="col-md-2">
                <a href="time_tracking.php" class="btn btn-outline-info w-100">
                    <i class="bi bi-clock-history me-2"></i>Time Tracking
                </a>
            </div>
            <div class="col-md-2">
                <a href="leave_management.php" class="btn btn-outline-info w-100">
                    <i class="bi bi-calendar-x me-2"></i>Leave Management
                </a>
            </div>
            <div class="col-md-2">
                <a href="payroll_processing.php" class="btn btn-outline-warning w-100">
                    <i class="bi bi-currency-rupee me-2"></i>Payroll
                </a>
            </div>
            <div class="col-md-2">
                <a href="performance_management.php" class="btn btn-outline-danger w-100">
                    <i class="bi bi-graph-up me-2"></i>Performance
                </a>
            </div>
        </div>

        <!-- Second Row -->
        <div class="row g-3 mb-4">
            <div class="col-md-2">
                <a href="shift_management.php" class="btn btn-outline-primary w-100">
                    <i class="bi bi-clock me-2"></i>Shift Management
                </a>
            </div>
            <div class="col-md-2">
                <a href="hr_panel.php" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-gear me-2"></i>HR Panel
                </a>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Search Employees</label>
                        <input type="text" name="search" class="form-control" placeholder="Search by name, ID, email..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="on_leave" <?= $status === 'on_leave' ? 'selected' : '' ?>>On Leave</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Department</label>
                        <select name="department" class="form-select">
                            <option value="">All Departments</option>
                            <?php if ($departments): while ($deptRow = mysqli_fetch_assoc($departments)): ?>
                                <option value="<?= htmlspecialchars($deptRow['department_name']) ?>" <?= $department === $deptRow['department_name'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($deptRow['department_name']) ?>
                                </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Employment Type</label>
                        <select name="employment_type" class="form-select">
                            <option value="">All Types</option>
                            <option value="full_time" <?= $employment_type === 'full_time' ? 'selected' : '' ?>>Full Time</option>
                            <option value="part_time" <?= $employment_type === 'part_time' ? 'selected' : '' ?>>Part Time</option>
                            <option value="contract" <?= $employment_type === 'contract' ? 'selected' : '' ?>>Contract</option>
                            <option value="intern" <?= $employment_type === 'intern' ? 'selected' : '' ?>>Intern</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="bi bi-search"></i> Search
                        </button>
                        <a href="?" class="btn btn-outline-secondary me-2">
                            <i class="bi bi-x-circle"></i>
                        </a>
                        <button type="button" class="btn btn-outline-success" onclick="exportEmployees()">
                            <i class="bi bi-download"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Employees Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0 text-dark">
                    <i class="bi bi-table me-2"></i>Employee Database
                    <span class="badge bg-primary ms-2"><?= $employees ? mysqli_num_rows($employees) : 0 ?> employees</span>
                </h6>
            </div>
            <div class="card-body p-0">
                <?php if ($employees && mysqli_num_rows($employees) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="employeesTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Employee Info</th>
                                    <th>Contact Details</th>
                                    <th>Department</th>
                                    <th>Employment</th>
                                    <th>Salary</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($employee = mysqli_fetch_assoc($employees)): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong class="text-primary"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></strong>
                                                <br><small class="text-muted">ID: <?= htmlspecialchars($employee['employee_id']) ?></small>
                                                <?php if ($employee['date_of_joining']): ?>
                                                    <br><small class="text-info">Joined: <?= date('M Y', strtotime($employee['date_of_joining'])) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($employee['phone']): ?>
                                                <i class="bi bi-telephone text-success me-1"></i><?= htmlspecialchars($employee['phone']) ?><br>
                                            <?php endif; ?>
                                            <?php if ($employee['email']): ?>
                                                <i class="bi bi-envelope text-info me-1"></i><?= htmlspecialchars($employee['email']) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?= htmlspecialchars($employee['department_name'] ?? 'N/A') ?></span>
                                            <?php if (!empty($employee['position'] ?? '')): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($employee['position']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?= ucfirst(str_replace('_', ' ', $employee['employment_type'])) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($employee['salary']): ?>
                                                <strong class="text-success">₹<?= number_format($employee['salary']) ?></strong>
                                            <?php else: ?>
                                                <span class="text-muted">Not set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = match($employee['status']) {
                                                'active' => 'bg-success',
                                                'inactive' => 'bg-secondary',
                                                'on_leave' => 'bg-warning',
                                                default => 'bg-secondary'
                                            };
                                            ?>
                                            <span class="badge <?= $statusClass ?>"><?= ucfirst($employee['status']) ?></span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary btn-sm" onclick="viewEmployee(<?= $employee['id'] ?>)" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-warning btn-sm" onclick="editEmployee(<?= $employee['id'] ?>)" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-outline-danger btn-sm" onclick="deleteEmployee(<?= $employee['id'] ?>)" title="Deactivate">
                                                    <i class="bi bi-person-x"></i>
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
                        <i class="bi bi-people text-muted" style="font-size: 3rem;"></i>
                        <h5 class="text-muted mt-3">No employees found</h5>
                        <p class="text-muted">Add your first employee to get started</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                            <i class="bi bi-person-plus me-1"></i>Add Employee
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Employee Modal -->
<div class="modal fade" id="addEmployeeModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-person-plus text-primary me-2"></i>Add New Employee
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addEmployeeForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <!-- Basic Information -->
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-2">Basic Information</h6>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">First Name *</label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name *</label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Employee ID *</label>
                            <input type="text" name="employee_id" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        
                        <!-- Contact Information -->
                        <div class="col-12 mt-4">
                            <h6 class="text-primary border-bottom pb-2">Contact Information</h6>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone *</label>
                            <input type="text" name="phone" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department *</label>
                            <select name="department_id" class="form-select" required>
                                <option value="">Select Department</option>
                                <?php 
                                // Reset departments result
                                mysqli_data_seek($departments, 0);
                                while ($dept = mysqli_fetch_assoc($departments)): 
                                ?>
                                    <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['department_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <!-- Job Information -->
                        <div class="col-12 mt-4">
                            <h6 class="text-primary border-bottom pb-2">Job Information</h6>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Position *</label>
                            <input type="text" name="position" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Employment Type *</label>
                            <select name="employment_type" class="form-select" required>
                                <option value="full_time">Full Time</option>
                                <option value="part_time">Part Time</option>
                                <option value="contract">Contract</option>
                                <option value="intern">Intern</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date of Joining *</label>
                            <input type="date" name="date_of_joining" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Salary (₹)</label>
                            <input type="number" name="salary" class="form-control" step="0.01">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Add Employee
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Employee Modal -->
<div class="modal fade" id="editEmployeeModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-pencil text-warning me-2"></i>Edit Employee
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editEmployeeForm">
                <input type="hidden" name="id" id="editEmployeeId">
                <div class="modal-body">
                    <div class="row g-3">
                        <!-- Basic Information -->
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-2">Basic Information</h6>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">First Name *</label>
                            <input type="text" name="first_name" id="editFirstName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name *</label>
                            <input type="text" name="last_name" id="editLastName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Employee ID *</label>
                            <input type="text" name="employee_id" id="editEmployeeIdField" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" id="editEmail" class="form-control" required>
                        </div>
                        
                        <!-- Contact Information -->
                        <div class="col-12 mt-4">
                            <h6 class="text-primary border-bottom pb-2">Contact Information</h6>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone *</label>
                            <input type="text" name="phone" id="editPhone" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department *</label>
                            <select name="department_id" id="editDepartmentId" class="form-select" required>
                                <option value="">Select Department</option>
                                <?php 
                                mysqli_data_seek($departments, 0);
                                while ($dept = mysqli_fetch_assoc($departments)): 
                                ?>
                                    <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['department_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <!-- Job Information -->
                        <div class="col-12 mt-4">
                            <h6 class="text-primary border-bottom pb-2">Job Information</h6>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Position *</label>
                            <input type="text" name="position" id="editPosition" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Employment Type *</label>
                            <select name="employment_type" id="editEmploymentType" class="form-select" required>
                                <option value="full_time">Full Time</option>
                                <option value="part_time">Part Time</option>
                                <option value="contract">Contract</option>
                                <option value="intern">Intern</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" id="editStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="on_leave">On Leave</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Salary (₹)</label>
                            <input type="number" name="salary" id="editSalary" class="form-control" step="0.01">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-check-lg me-1"></i>Update Employee
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Employee Details Modal -->
<div class="modal fade" id="employeeDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-person text-info me-2"></i>Employee Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="employeeDetailsContent">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Modal -->
<div class="modal fade" id="attendanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-clock text-success me-2"></i>Mark Attendance
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="attendanceForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Employee *</label>
                        <select name="employee_id" class="form-select" required>
                            <option value="">Choose Employee</option>
                            <?php 
                            mysqli_data_seek($employees, 0);
                            if ($employees): while ($emp = mysqli_fetch_assoc($employees)): ?>
                                <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?> - <?= $emp['employee_id'] ?></option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Current Time: <?= date('Y-m-d H:i:s') ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-lg me-1"></i>Mark Attendance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Employee management functions
document.getElementById('addEmployeeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'add_employee');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding employee');
    });
});

document.getElementById('editEmployeeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'update_employee');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating employee');
    });
});

document.getElementById('attendanceForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'mark_attendance');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            bootstrap.Modal.getInstance(document.getElementById('attendanceModal')).hide();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while marking attendance');
    });
});

function viewEmployee(id) {
    const formData = new FormData();
    formData.append('action', 'get_employee');
    formData.append('id', id);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const emp = data.data;
            document.getElementById('employeeDetailsContent').innerHTML = `
                <div class="row g-3">
                    <div class="col-md-6">
                        <strong>Name:</strong><br>
                        <span class="text-primary">${emp.first_name} ${emp.last_name}</span>
                    </div>
                    <div class="col-md-6">
                        <strong>Employee ID:</strong><br>
                        <span class="text-muted">${emp.employee_id}</span>
                    </div>
                    <div class="col-md-6">
                        <strong>Email:</strong><br>
                        <span class="text-info">${emp.email || 'N/A'}</span>
                    </div>
                    <div class="col-md-6">
                        <strong>Phone:</strong><br>
                        <span class="text-success">${emp.phone || 'N/A'}</span>
                    </div>
                    <div class="col-md-6">
                        <strong>Position:</strong><br>
                        <span class="badge bg-info">${emp.position || 'N/A'}</span>
                    </div>
                    <div class="col-md-6">
                        <strong>Employment Type:</strong><br>
                        <span class="badge bg-secondary">${emp.employment_type ? emp.employment_type.replace('_', ' ').toUpperCase() : 'N/A'}</span>
                    </div>
                    <div class="col-md-6">
                        <strong>Date of Joining:</strong><br>
                        <span class="text-muted">${emp.date_of_joining || 'N/A'}</span>
                    </div>
                    <div class="col-md-6">
                        <strong>Salary:</strong><br>
                        <span class="text-success fw-bold">${emp.salary ? '₹' + parseFloat(emp.salary).toLocaleString() : 'Not set'}</span>
                    </div>
                    <div class="col-md-6">
                        <strong>Status:</strong><br>
                        <span class="badge ${emp.status === 'active' ? 'bg-success' : emp.status === 'inactive' ? 'bg-secondary' : 'bg-warning'}">${emp.status ? emp.status.toUpperCase() : 'N/A'}</span>
                    </div>
                </div>
            `;
            new bootstrap.Modal(document.getElementById('employeeDetailsModal')).show();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while fetching employee details');
    });
}

function editEmployee(id) {
    const formData = new FormData();
    formData.append('action', 'get_employee');
    formData.append('id', id);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const emp = data.data;
            document.getElementById('editEmployeeId').value = emp.id;
            document.getElementById('editFirstName').value = emp.first_name || '';
            document.getElementById('editLastName').value = emp.last_name || '';
            document.getElementById('editEmployeeIdField').value = emp.employee_id || '';
            document.getElementById('editEmail').value = emp.email || '';
            document.getElementById('editPhone').value = emp.phone || '';
            document.getElementById('editDepartmentId').value = emp.department_id || '';
            document.getElementById('editPosition').value = emp.position || '';
            document.getElementById('editEmploymentType').value = emp.employment_type || '';
            document.getElementById('editStatus').value = emp.status || '';
            document.getElementById('editSalary').value = emp.salary || '';
            
            new bootstrap.Modal(document.getElementById('editEmployeeModal')).show();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while fetching employee details');
    });
}

function deleteEmployee(id) {
    if (confirm('Are you sure you want to deactivate this employee?')) {
        const formData = new FormData();
        formData.append('action', 'delete_employee');
        formData.append('id', id);
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deactivating employee');
        });
    }
}

function exportEmployees() {
    window.open('export_employees.php', '_blank');
}
</script>

<style>
.stats-card {
    transition: transform 0.2s;
}
.stats-card:hover {
    transform: translateY(-2px);
}
.table th {
    font-weight: 600;
    font-size: 0.9rem;
}
.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}
</style>

<?php include '../layouts/footer.php'; ?>
