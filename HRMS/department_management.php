<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: " . dirname(__DIR__) . "/login.php");
    exit;
}

// Include database connection with dynamic path resolution
$db_path = dirname(__DIR__) . "/db.php";
if (file_exists($db_path)) {
    include $db_path;
} else {
    include "../db.php"; // Fallback
}

$page_title = 'Department Management - HRMS';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_department':
            $department_name = mysqli_real_escape_string($conn, $_POST['department_name']);
            $department_code = mysqli_real_escape_string($conn, $_POST['department_code']);
            $head_of_department = intval($_POST['head_of_department']);
            $budget = floatval($_POST['budget']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            
            // Check if department code already exists
            $existing = mysqli_query($conn, "SELECT id FROM hr_departments WHERE department_code = '$department_code'");
            if ($existing && mysqli_num_rows($existing) > 0) {
                echo json_encode(['success' => false, 'message' => 'Department code already exists!']);
                exit;
            }
            
            $query = "INSERT INTO hr_departments (department_name, department_code, head_of_department, budget, description, status) 
                      VALUES ('$department_name', '$department_code', $head_of_department, $budget, '$description', 'active')";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Department added successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;

        case 'get_department':
            $id = intval($_POST['id']);
            $query = mysqli_query($conn, "
                SELECT d.*, CONCAT(e.first_name, ' ', e.last_name) as hod_name
                FROM hr_departments d 
                LEFT JOIN hr_employees e ON d.head_of_department = e.id 
                WHERE d.id = $id
            ");
            if ($query && $row = mysqli_fetch_assoc($query)) {
                echo json_encode(['success' => true, 'data' => $row]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Department not found']);
            }
            exit;

        case 'update_department':
            $id = intval($_POST['id']);
            $department_name = mysqli_real_escape_string($conn, $_POST['department_name']);
            $department_code = mysqli_real_escape_string($conn, $_POST['department_code']);
            $head_of_department = intval($_POST['head_of_department']);
            $budget = floatval($_POST['budget']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $status = mysqli_real_escape_string($conn, $_POST['status']);
            
            // Check if department code exists for other departments
            $existing = mysqli_query($conn, "SELECT id FROM hr_departments WHERE department_code = '$department_code' AND id != $id");
            if ($existing && mysqli_num_rows($existing) > 0) {
                echo json_encode(['success' => false, 'message' => 'Department code already exists!']);
                exit;
            }
            
            $query = "UPDATE hr_departments SET 
                      department_name = '$department_name',
                      department_code = '$department_code',
                      head_of_department = $head_of_department,
                      budget = $budget,
                      description = '$description',
                      status = '$status'
                      WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Department updated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;

        case 'delete_department':
            $id = intval($_POST['id']);
            
            // Check if department has employees
            $employeeCheck = mysqli_query($conn, "SELECT COUNT(*) as count FROM hr_employees WHERE department_id = $id AND status = 'active'");
            $empCount = mysqli_fetch_assoc($employeeCheck)['count'];
            
            if ($empCount > 0) {
                echo json_encode(['success' => false, 'message' => "Cannot delete department with $empCount active employees!"]);
                exit;
            }
            
            $query = "UPDATE hr_departments SET status = 'inactive' WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Department deactivated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;

        case 'get_department_stats':
            $id = intval($_POST['id']);
            
            // Get department statistics
            $stats = [];
            
            // Employee count by status
            $empStats = mysqli_query($conn, "
                SELECT status, COUNT(*) as count 
                FROM hr_employees 
                WHERE department_id = $id 
                GROUP BY status
            ");
            $stats['employees'] = [];
            while ($row = mysqli_fetch_assoc($empStats)) {
                $stats['employees'][$row['status']] = $row['count'];
            }
            
            // Average salary
            $salaryQuery = mysqli_query($conn, "
                SELECT AVG(salary) as avg_salary, SUM(salary) as total_salary 
                FROM hr_employees 
                WHERE department_id = $id AND status = 'active'
            ");
            $salaryData = mysqli_fetch_assoc($salaryQuery);
            $stats['avg_salary'] = round($salaryData['avg_salary'] ?? 0, 2);
            $stats['total_salary'] = $salaryData['total_salary'] ?? 0;
            
            // Recent hires (last 30 days)
            $recentHires = mysqli_query($conn, "
                SELECT COUNT(*) as count 
                FROM hr_employees 
                WHERE department_id = $id 
                AND date_of_joining >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stats['recent_hires'] = mysqli_fetch_assoc($recentHires)['count'];
            
            echo json_encode(['success' => true, 'stats' => $stats]);
            exit;
    }
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'active';

// Build WHERE clause
$where_conditions = [];
if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $where_conditions[] = "(d.department_name LIKE '%$search%' OR d.department_code LIKE '%$search%')";
}
if (!empty($status) && $status !== 'all') {
    $where_conditions[] = "d.status = '$status'";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get departments with statistics
$departments_query = "
    SELECT 
        d.*,
        CONCAT(e.first_name, ' ', e.last_name) as hod_name,
        COUNT(emp.id) as employee_count,
        AVG(emp.salary) as avg_salary,
        SUM(emp.salary) as total_salary_cost
    FROM hr_departments d
    LEFT JOIN hr_employees e ON d.head_of_department = e.id
    LEFT JOIN hr_employees emp ON d.id = emp.department_id AND emp.status = 'active'
    $where_clause
    GROUP BY d.id, d.department_name, d.department_code, d.head_of_department, d.budget, d.description, d.status, d.created_at, d.updated_at
    ORDER BY d.department_name ASC
";

$departments = mysqli_query($conn, $departments_query);

// Get total counts for statistics
$total_departments = mysqli_query($conn, "SELECT COUNT(*) as count FROM hr_departments WHERE status = 'active'");
$total_dept_count = mysqli_fetch_assoc($total_departments)['count'];

$total_employees = mysqli_query($conn, "
    SELECT COUNT(*) as count 
    FROM hr_employees e 
    JOIN hr_departments d ON e.department_id = d.id 
    WHERE e.status = 'active' AND d.status = 'active'
");
$total_emp_count = mysqli_fetch_assoc($total_employees)['count'];

$total_budget = mysqli_query($conn, "SELECT SUM(budget) as total FROM hr_departments WHERE status = 'active'");
$total_budget_amount = mysqli_fetch_assoc($total_budget)['total'] ?? 0;

// Get employees for HOD dropdown
$employees = mysqli_query($conn, "SELECT id, first_name, last_name, employee_id FROM hr_employees WHERE status = 'active' ORDER BY first_name");

// Include layout files with dynamic path resolution
$header_path = dirname(__DIR__) . "/layouts/header.php";
$sidebar_path = dirname(__DIR__) . "/layouts/sidebar.php";

if (file_exists($header_path)) {
    include $header_path;
} else {
    include "../layouts/header.php";
}

if (file_exists($sidebar_path)) {
    include $sidebar_path;
} else {
    include "../layouts/sidebar.php";
}
?>

<div class="main-content">
    <div class="content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="h4 mb-1 fw-bold text-primary">🏢 Department Management</h1>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-building"></i> 
                        Manage organizational departments and structures
                        <span class="badge bg-light text-dark ms-2"><?= $total_dept_count ?> Active Departments</span>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-success btn-sm" onclick="exportDepartments()" title="Export Department Data">
                        <i class="bi bi-download"></i> Export
                    </button>
                    <button class="btn btn-outline-primary btn-sm" onclick="printDepartments()" title="Print Directory">
                        <i class="bi bi-printer"></i> Print
                    </button>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDepartmentModal" title="Add New Department">
                        <i class="bi bi-plus-circle"></i> Add Department
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm" title="Back to HRMS">
                        <i class="bi bi-arrow-left"></i> Back
                    </a>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row g-2 mb-3">
                <div class="col-xl-3 col-lg-6 col-md-6">
                    <div class="card border-0 h-100" style="background: linear-gradient(135deg, #e8f4fd 0%, #cce7ff 100%);">
                        <div class="card-body text-center p-3">
                            <div class="mb-2">
                                <i class="bi bi-building-fill fs-3" style="color: #0d6efd;"></i>
                            </div>
                            <h5 class="mb-1 fw-bold" style="color: #0d6efd;"><?= $total_dept_count ?></h5>
                            <small class="text-muted">Active Departments</small>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-6 col-md-6">
                    <div class="card border-0 h-100" style="background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%);">
                        <div class="card-body text-center p-3">
                            <div class="mb-2">
                                <i class="bi bi-people-fill fs-3" style="color: #7b1fa2;"></i>
                            </div>
                            <h5 class="mb-1 fw-bold" style="color: #7b1fa2;"><?= $total_emp_count ?></h5>
                            <small class="text-muted">Total Employees</small>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-6 col-md-6">
                    <div class="card border-0 h-100" style="background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);">
                        <div class="card-body text-center p-3">
                            <div class="mb-2">
                                <i class="bi bi-currency-rupee fs-3" style="color: #388e3c;"></i>
                            </div>
                            <h5 class="mb-1 fw-bold" style="color: #388e3c;">₹<?= number_format($total_budget_amount) ?></h5>
                            <small class="text-muted">Total Budget</small>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-6 col-md-6">
                    <div class="card border-0 h-100" style="background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);">
                        <div class="card-body text-center p-3">
                            <div class="mb-2">
                                <i class="bi bi-graph-up fs-3" style="color: #f57c00;"></i>
                            </div>
                            <h5 class="mb-1 fw-bold" style="color: #f57c00;"><?= round($total_emp_count / max($total_dept_count, 1), 1) ?></h5>
                            <small class="text-muted">Avg Employees/Dept</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-3">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="bi bi-search text-muted"></i>
                                </span>
                                <input type="text" class="form-control border-start-0" 
                                       placeholder="Search departments by name or code..." 
                                       value="<?= htmlspecialchars($search) ?>" 
                                       onkeyup="searchDepartments(this.value)">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" onchange="filterByStatus(this.value)">
                                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Status</option>
                                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-outline-secondary w-100" onclick="resetFilters()">
                                <i class="bi bi-arrow-clockwise"></i> Reset Filters
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Departments Grid -->
            <div class="row g-3" id="departmentsGrid">
                <?php if ($departments && mysqli_num_rows($departments) > 0): ?>
                    <?php while ($dept = mysqli_fetch_assoc($departments)): ?>
                        <div class="col-xl-4 col-lg-6 col-md-6">
                            <div class="card border-0 shadow-sm h-100 department-card">
                                <div class="card-header bg-gradient text-white p-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1 fw-bold"><?= htmlspecialchars($dept['department_name']) ?></h6>
                                            <small class="opacity-75">Code: <?= htmlspecialchars($dept['department_code']) ?></small>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn btn-link text-white p-0" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="#" onclick="viewDepartment(<?= $dept['id'] ?>)">
                                                    <i class="bi bi-eye"></i> View Details</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="editDepartment(<?= $dept['id'] ?>)">
                                                    <i class="bi bi-pencil"></i> Edit</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="showDepartmentStats(<?= $dept['id'] ?>)">
                                                    <i class="bi bi-bar-chart"></i> Statistics</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="#" onclick="deleteDepartment(<?= $dept['id'] ?>)">
                                                    <i class="bi bi-trash"></i> Deactivate</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body p-3">
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <div class="text-center">
                                                <div class="h5 mb-0 text-primary fw-bold"><?= $dept['employee_count'] ?></div>
                                                <small class="text-muted">Employees</small>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-center">
                                                <div class="h6 mb-0 text-success fw-bold">₹<?= number_format($dept['budget']) ?></div>
                                                <small class="text-muted">Budget</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <small class="text-muted d-block">Head of Department:</small>
                                        <div class="fw-semibold"><?= $dept['hod_name'] ?: 'Not Assigned' ?></div>
                                    </div>
                                    
                                    <?php if ($dept['description']): ?>
                                    <div class="mb-2">
                                        <small class="text-muted d-block">Description:</small>
                                        <div class="small"><?= htmlspecialchars(substr($dept['description'], 0, 100)) ?><?= strlen($dept['description']) > 100 ? '...' : '' ?></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <span class="badge <?= $dept['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?> px-2 py-1">
                                            <?= ucfirst($dept['status']) ?>
                                        </span>
                                        <?php if ($dept['avg_salary']): ?>
                                        <small class="text-muted">
                                            Avg Salary: ₹<?= number_format($dept['avg_salary']) ?>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="text-center py-5">
                            <div class="mb-3">
                                <i class="bi bi-building" style="font-size: 3rem; color: #dee2e6;"></i>
                            </div>
                            <h5 class="text-muted">No departments found</h5>
                            <p class="text-muted">Create your first department to get started.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                                <i class="bi bi-plus-circle"></i> Add Department
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Department Modal -->
<div class="modal fade" id="addDepartmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle"></i> Add New Department
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addDepartmentForm">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="department_name" class="form-label">Department Name</label>
                            <input type="text" class="form-control" id="department_name" name="department_name" required>
                        </div>
                        <div class="col-md-4">
                            <label for="department_code" class="form-label">Department Code</label>
                            <input type="text" class="form-control" id="department_code" name="department_code" required maxlength="10">
                        </div>
                        <div class="col-md-6">
                            <label for="head_of_department" class="form-label">Head of Department</label>
                            <select class="form-select" id="head_of_department" name="head_of_department" required>
                                <option value="">Select HOD</option>
                                <?php
                                mysqli_data_seek($employees, 0);
                                while ($emp = mysqli_fetch_assoc($employees)): ?>
                                    <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?> (<?= $emp['employee_id'] ?>)</option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="budget" class="form-label">Annual Budget (₹)</label>
                            <input type="number" class="form-control" id="budget" name="budget" step="0.01" min="0">
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="addDepartment()">
                    <i class="bi bi-check-circle"></i> Add Department
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Department Modal -->
<div class="modal fade" id="editDepartmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="bi bi-pencil-square"></i> Edit Department
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editDepartmentForm">
                    <input type="hidden" id="edit_department_id" name="id">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="edit_department_name" class="form-label">Department Name</label>
                            <input type="text" class="form-control" id="edit_department_name" name="department_name" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_department_code" class="form-label">Department Code</label>
                            <input type="text" class="form-control" id="edit_department_code" name="department_code" required maxlength="10">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_head_of_department" class="form-label">Head of Department</label>
                            <select class="form-select" id="edit_head_of_department" name="head_of_department" required>
                                <option value="">Select HOD</option>
                                <?php
                                mysqli_data_seek($employees, 0);
                                while ($emp = mysqli_fetch_assoc($employees)): ?>
                                    <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?> (<?= $emp['employee_id'] ?>)</option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_budget" class="form-label">Annual Budget (₹)</label>
                            <input type="number" class="form-control" id="edit_budget" name="budget" step="0.01" min="0">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-select" id="edit_status" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="updateDepartment()">
                    <i class="bi bi-check-circle"></i> Update Department
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Department Statistics Modal -->
<div class="modal fade" id="departmentStatsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="bi bi-bar-chart"></i> Department Statistics
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="departmentStatsContent">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Department management functions
function searchDepartments(query) {
    const url = new URL(window.location);
    if (query) {
        url.searchParams.set('search', query);
    } else {
        url.searchParams.delete('search');
    }
    window.location.href = url;
}

function filterByStatus(status) {
    const url = new URL(window.location);
    if (status && status !== 'all') {
        url.searchParams.set('status', status);
    } else {
        url.searchParams.delete('status');
    }
    window.location.href = url;
}

function resetFilters() {
    window.location.href = window.location.pathname;
}

function addDepartment() {
    const form = document.getElementById('addDepartmentForm');
    const formData = new FormData(form);
    formData.append('action', 'add_department');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('addDepartmentModal')).hide();
            showAlert('success', data.message);
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert('danger', data.message);
        }
    })
    .catch(error => {
        showAlert('danger', 'Error adding department');
        console.error('Error:', error);
    });
}

function editDepartment(id) {
    const formData = new FormData();
    formData.append('action', 'get_department');
    formData.append('id', id);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const dept = data.data;
            document.getElementById('edit_department_id').value = dept.id;
            document.getElementById('edit_department_name').value = dept.department_name;
            document.getElementById('edit_department_code').value = dept.department_code;
            document.getElementById('edit_head_of_department').value = dept.head_of_department;
            document.getElementById('edit_budget').value = dept.budget;
            document.getElementById('edit_description').value = dept.description || '';
            document.getElementById('edit_status').value = dept.status;
            
            new bootstrap.Modal(document.getElementById('editDepartmentModal')).show();
        } else {
            showAlert('danger', data.message);
        }
    })
    .catch(error => {
        showAlert('danger', 'Error loading department details');
        console.error('Error:', error);
    });
}

function updateDepartment() {
    const form = document.getElementById('editDepartmentForm');
    const formData = new FormData(form);
    formData.append('action', 'update_department');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('editDepartmentModal')).hide();
            showAlert('success', data.message);
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert('danger', data.message);
        }
    })
    .catch(error => {
        showAlert('danger', 'Error updating department');
        console.error('Error:', error);
    });
}

function deleteDepartment(id) {
    if (confirm('Are you sure you want to deactivate this department? This action cannot be undone.')) {
        const formData = new FormData();
        formData.append('action', 'delete_department');
        formData.append('id', id);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert('danger', data.message);
            }
        })
        .catch(error => {
            showAlert('danger', 'Error deactivating department');
            console.error('Error:', error);
        });
    }
}

function showDepartmentStats(id) {
    const modal = new bootstrap.Modal(document.getElementById('departmentStatsModal'));
    modal.show();
    
    const formData = new FormData();
    formData.append('action', 'get_department_stats');
    formData.append('id', id);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const stats = data.stats;
            let content = `
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h4>${(stats.employees.active || 0)}</h4>
                                <small>Active Employees</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-secondary text-white">
                            <div class="card-body text-center">
                                <h4>${(stats.employees.inactive || 0)}</h4>
                                <small>Inactive Employees</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h4>₹${Number(stats.avg_salary || 0).toLocaleString()}</h4>
                                <small>Average Salary</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-warning text-dark">
                            <div class="card-body text-center">
                                <h4>${stats.recent_hires || 0}</h4>
                                <small>Recent Hires (30 days)</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h4>₹${Number(stats.total_salary || 0).toLocaleString()}</h4>
                                <small>Total Salary Cost</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('departmentStatsContent').innerHTML = content;
        } else {
            document.getElementById('departmentStatsContent').innerHTML = '<div class="alert alert-danger">Error loading statistics</div>';
        }
    })
    .catch(error => {
        document.getElementById('departmentStatsContent').innerHTML = '<div class="alert alert-danger">Error loading statistics</div>';
        console.error('Error:', error);
    });
}

function exportDepartments() {
    window.open(window.location.href + '?export=csv', '_blank');
}

function printDepartments() {
    window.print();
}

function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const container = document.querySelector('.container-fluid');
    container.insertBefore(alertDiv, container.firstChild);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

// Auto-generate department code based on name
document.getElementById('department_name')?.addEventListener('input', function() {
    const name = this.value;
    const code = name.split(' ').map(word => word.charAt(0).toUpperCase()).join('').substring(0, 10);
    document.getElementById('department_code').value = code;
});

// Print styles
const style = document.createElement('style');
style.textContent = `
    @media print {
        .btn, .dropdown, .modal { display: none !important; }
        .card { break-inside: avoid; }
        .department-card { margin-bottom: 20px; }
    }
`;
document.head.appendChild(style);
</script>

<?php include '../layouts/footer.php'; ?>
