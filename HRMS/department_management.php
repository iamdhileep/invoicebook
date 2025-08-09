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

// Function to initialize HRMS tables
function initializeHRMSTables($conn) {
    // Create hr_departments table
    $create_departments = "CREATE TABLE IF NOT EXISTS `hr_departments` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `department_name` varchar(255) NOT NULL,
        `department_code` varchar(50) UNIQUE NOT NULL,
        `head_of_department` int(11) NULL,
        `budget` decimal(15,2) DEFAULT 0.00,
        `description` text NULL,
        `status` enum('active','inactive') DEFAULT 'active',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_department_code` (`department_code`),
        INDEX `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    mysqli_query($conn, $create_departments);
    
    // Create hr_employees table
    $create_employees = "CREATE TABLE IF NOT EXISTS `hr_employees` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `employee_id` varchar(50) UNIQUE NOT NULL,
        `first_name` varchar(100) NOT NULL,
        `last_name` varchar(100) NOT NULL,
        `email` varchar(255) UNIQUE NOT NULL,
        `phone` varchar(20) NULL,
        `department_id` int(11) NULL,
        `position` varchar(100) NULL,
        `salary` decimal(10,2) DEFAULT 0.00,
        `hire_date` date NULL,
        `status` enum('active','inactive','terminated') DEFAULT 'active',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_employee_id` (`employee_id`),
        INDEX `idx_department_id` (`department_id`),
        INDEX `idx_status` (`status`),
        FOREIGN KEY (`department_id`) REFERENCES `hr_departments`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    mysqli_query($conn, $create_employees);
    
    // Add some sample data if tables are empty
    $dept_count = mysqli_query($conn, "SELECT COUNT(*) as count FROM hr_departments");
    if ($dept_count && mysqli_fetch_assoc($dept_count)['count'] == 0) {
        // Add sample departments
        $sample_departments = [
            ['Human Resources', 'HR', 50000.00, 'Manages employee relations, recruitment, and HR policies'],
            ['Information Technology', 'IT', 100000.00, 'Handles all technology infrastructure and software development'],
            ['Finance', 'FIN', 75000.00, 'Manages financial planning, accounting, and budgets'],
            ['Marketing', 'MKT', 60000.00, 'Handles marketing campaigns, brand management, and sales support'],
            ['Operations', 'OPS', 80000.00, 'Manages daily business operations and process improvements']
        ];
        
        foreach ($sample_departments as $dept) {
            mysqli_query($conn, "INSERT INTO hr_departments (department_name, department_code, budget, description) VALUES ('{$dept[0]}', '{$dept[1]}', {$dept[2]}, '{$dept[3]}')");
        }
    }
}

// Initialize tables if they don't exist
initializeHRMSTables($conn);

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
            
            // Check if department code already exists (excluding current department)
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
                      description = '$description' 
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
            $employees_check = mysqli_query($conn, "SELECT COUNT(*) as count FROM hr_employees WHERE department_id = $id AND status = 'active'");
            $employee_count = 0;
            if ($employees_check) {
                $result = mysqli_fetch_assoc($employees_check);
                $employee_count = $result ? $result['count'] : 0;
            }
            
            if ($employee_count > 0) {
                echo json_encode(['success' => false, 'message' => "Cannot delete department. It has $employee_count active employees."]);
                exit;
            }
            
            $query = "UPDATE hr_departments SET status = 'inactive' WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Department deleted successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
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
$total_departments_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM hr_departments WHERE status = 'active'");
$total_dept_count = 0;
if ($total_departments_query) {
    $result = mysqli_fetch_assoc($total_departments_query);
    $total_dept_count = $result ? $result['count'] : 0;
}

$total_employees_query = mysqli_query($conn, "
    SELECT COUNT(*) as count 
    FROM hr_employees e 
    JOIN hr_departments d ON e.department_id = d.id 
    WHERE e.status = 'active' AND d.status = 'active'
");
$total_emp_count = 0;
if ($total_employees_query) {
    $result = mysqli_fetch_assoc($total_employees_query);
    $total_emp_count = $result ? $result['count'] : 0;
}

$total_budget_query = mysqli_query($conn, "SELECT SUM(budget) as total FROM hr_departments WHERE status = 'active'");
$total_budget_amount = 0;
if ($total_budget_query) {
    $result = mysqli_fetch_assoc($total_budget_query);
    $total_budget_amount = $result ? ($result['total'] ?? 0) : 0;
}

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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Department Management</h1>
                    <p class="text-muted mb-0">Manage organizational departments and structure</p>
                </div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#departmentModal">
                    <i class="bi bi-plus-circle"></i> Add Department
                </button>
            </div>

            <!-- Statistics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card border-0 bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Total Departments</h6>
                                    <h4 class="mb-0"><?php echo $total_dept_count; ?></h4>
                                </div>
                                <i class="bi bi-building fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Total Employees</h6>
                                    <h4 class="mb-0"><?php echo $total_emp_count; ?></h4>
                                </div>
                                <i class="bi bi-people-fill fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Total Budget</h6>
                                    <h4 class="mb-0">$<?php echo number_format($total_budget_amount, 0); ?></h4>
                                </div>
                                <i class="bi bi-currency-dollar fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 bg-warning text-dark">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Avg Budget/Dept</h6>
                                    <h4 class="mb-0">$<?php echo $total_dept_count > 0 ? number_format($total_budget_amount / $total_dept_count, 0) : 0; ?></h4>
                                </div>
                                <i class="bi bi-calculator fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Search Departments</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="Search by name or code..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label>&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-outline-primary">
                                    <i class="bi bi-search"></i> Filter
                                </button>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label>&nbsp;</label>
                            <div class="d-grid">
                                <a href="department_management.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-clockwise"></i> Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Departments Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Departments List</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Department</th>
                                    <th>Code</th>
                                    <th>Head of Department</th>
                                    <th>Employees</th>
                                    <th>Budget</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($departments && mysqli_num_rows($departments) > 0): ?>
                                    <?php while ($dept = mysqli_fetch_assoc($departments)): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <div class="fw-medium"><?php echo htmlspecialchars($dept['department_name']); ?></div>
                                                    <div class="text-muted small"><?php echo htmlspecialchars($dept['description'] ?? ''); ?></div>
                                                </div>
                                            </td>
                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($dept['department_code']); ?></span></td>
                                            <td><?php echo htmlspecialchars($dept['hod_name'] ?? 'Not assigned'); ?></td>
                                            <td><?php echo intval($dept['employee_count']); ?></td>
                                            <td>$<?php echo number_format($dept['budget'], 0); ?></td>
                                            <td>
                                                <?php if ($dept['status'] === 'active'): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <button type="button" class="btn btn-outline-primary" 
                                                            onclick="editDepartment(<?php echo $dept['id']; ?>)">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            onclick="deleteDepartment(<?php echo $dept['id']; ?>, '<?php echo htmlspecialchars($dept['department_name']); ?>')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5">
                                            <div class="text-muted">
                                                <i class="bi bi-building fs-1"></i>
                                                <div class="mt-2">No departments found</div>
                                                <small>Click "Add Department" to create your first department</small>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Department Modal -->
<div class="modal fade" id="departmentModal" tabindex="-1" aria-labelledby="departmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="departmentModalLabel">Add Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="departmentForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="department_name" class="form-label">Department Name *</label>
                            <input type="text" class="form-control" id="department_name" name="department_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="department_code" class="form-label">Department Code *</label>
                            <input type="text" class="form-control" id="department_code" name="department_code" required>
                            <div class="form-text">Unique identifier (e.g., HR, IT, FIN)</div>
                        </div>
                        <div class="col-md-6">
                            <label for="head_of_department" class="form-label">Head of Department</label>
                            <select class="form-select" id="head_of_department" name="head_of_department">
                                <option value="0">Select Employee</option>
                                <?php if ($employees && mysqli_num_rows($employees) > 0): ?>
                                    <?php while ($emp = mysqli_fetch_assoc($employees)): ?>
                                        <option value="<?php echo $emp['id']; ?>">
                                            <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'] . ' (' . $emp['employee_id'] . ')'); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="budget" class="form-label">Budget ($)</label>
                            <input type="number" class="form-control" id="budget" name="budget" step="0.01" min="0">
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Add Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let editingDepartmentId = null;

document.getElementById('departmentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const action = editingDepartmentId ? 'update_department' : 'add_department';
    formData.append('action', action);
    
    if (editingDepartmentId) {
        formData.append('id', editingDepartmentId);
    }
    
    fetch('department_management.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            bootstrap.Modal.getInstance(document.getElementById('departmentModal')).hide();
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert('danger', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'An error occurred. Please try again.');
    });
});

function editDepartment(id) {
    editingDepartmentId = id;
    
    const formData = new FormData();
    formData.append('action', 'get_department');
    formData.append('id', id);
    
    fetch('department_management.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('departmentModalLabel').textContent = 'Edit Department';
            document.getElementById('submitBtn').textContent = 'Update Department';
            
            document.getElementById('department_name').value = data.data.department_name;
            document.getElementById('department_code').value = data.data.department_code;
            document.getElementById('head_of_department').value = data.data.head_of_department || 0;
            document.getElementById('budget').value = data.data.budget;
            document.getElementById('description').value = data.data.description || '';
            
            new bootstrap.Modal(document.getElementById('departmentModal')).show();
        } else {
            showAlert('danger', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'Error loading department data.');
    });
}

function deleteDepartment(id, name) {
    if (confirm(`Are you sure you want to delete the department "${name}"?\n\nThis action cannot be undone.`)) {
        const formData = new FormData();
        formData.append('action', 'delete_department');
        formData.append('id', id);
        
        fetch('department_management.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert('danger', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', 'An error occurred. Please try again.');
        });
    }
}

// Reset modal when hidden
document.getElementById('departmentModal').addEventListener('hidden.bs.modal', function () {
    editingDepartmentId = null;
    document.getElementById('departmentModalLabel').textContent = 'Add Department';
    document.getElementById('submitBtn').textContent = 'Add Department';
    document.getElementById('departmentForm').reset();
});

function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        if (alertDiv && alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}
</script>

<?php 
// Include footer with dynamic path resolution
$footer_path = dirname(__DIR__) . "/layouts/footer.php";
if (file_exists($footer_path)) {
    include $footer_path;
} else {
    include "../layouts/footer.php"; // Fallback
}
?>
