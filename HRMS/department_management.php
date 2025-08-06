<?php
session_start();
$page_title = "Department Management";

// Include header and navigation
include '../layouts/header.php';
include '../layouts/sidebar.php';
include '../db.php';

// Handle department creation
if (isset($_POST['create_department'])) {
    $dept_name = $_POST['dept_name'];
    $dept_head = $_POST['dept_head'];
    $description = $_POST['description'];
    
    $stmt = $mysqli->prepare("INSERT INTO departments (department_name, department_head, description, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("sss", $dept_name, $dept_head, $description);
    
    if ($stmt->execute()) {
        $success_message = "Department created successfully!";
    } else {
        $error_message = "Error creating department: " . $mysqli->error;
    }
}

// Handle department deletion
if (isset($_POST['delete_department'])) {
    $dept_id = $_POST['dept_id'];
    
    $stmt = $mysqli->prepare("DELETE FROM departments WHERE department_id = ?");
    $stmt->bind_param("i", $dept_id);
    
    if ($stmt->execute()) {
        $success_message = "Department deleted successfully!";
    } else {
        $error_message = "Error deleting department: " . $mysqli->error;
    }
}

// Fetch departments
$departments_query = "SELECT d.*, 
                     (SELECT COUNT(*) FROM employees e WHERE e.department_name = d.department_name) as employee_count
                     FROM departments d 
                     ORDER BY d.department_name";
$departments_result = $mysqli->query($departments_query);

// Fetch employees for department head dropdown
$employees_query = "SELECT employee_id, CONCAT(first_name, ' ', last_name) as full_name FROM employees ORDER BY first_name";
$employees_result = $mysqli->query($employees_query);
?>

<div class="main-content">
    <div class="container-fluid p-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h3 class="mb-0">
                                    <i class="fas fa-building me-2"></i>
                                    Department Management
                                </h3>
                                <p class="mb-0">Manage organizational departments and structure</p>
                            </div>
                            <div class="col-md-4 text-end">
                                <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                                    <i class="fas fa-plus me-2"></i>Add Department
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Department Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6>Total Departments</h6>
                                <h3><?php echo $departments_result->num_rows; ?></h3>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-building fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6>Active Departments</h6>
                                <h3><?php echo $departments_result->num_rows; ?></h3>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-check-circle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6>Total Employees</h6>
                                <h3>
                                    <?php 
                                    $total_emp_query = "SELECT COUNT(*) as total FROM employees";
                                    $total_emp_result = $mysqli->query($total_emp_query);
                                    $total_emp = $total_emp_result->fetch_assoc();
                                    echo $total_emp['total'];
                                    ?>
                                </h3>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-users fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-secondary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6>Department Heads</h6>
                                <h3>
                                    <?php 
                                    $dept_heads_query = "SELECT COUNT(DISTINCT department_head) as heads FROM departments WHERE department_head IS NOT NULL AND department_head != ''";
                                    $dept_heads_result = $mysqli->query($dept_heads_query);
                                    $dept_heads = $dept_heads_result->fetch_assoc();
                                    echo $dept_heads['heads'];
                                    ?>
                                </h3>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-user-tie fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Departments Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Department List</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="departmentsTable">
                                <thead>
                                    <tr>
                                        <th>Department Name</th>
                                        <th>Department Head</th>
                                        <th>Employee Count</th>
                                        <th>Description</th>
                                        <th>Created Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Reset the result pointer
                                    $departments_result->data_seek(0);
                                    while ($dept = $departments_result->fetch_assoc()): 
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($dept['department_name']); ?></strong>
                                        </td>
                                        <td>
                                            <?php if ($dept['department_head']): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-user-tie me-1"></i>
                                                    <?php echo htmlspecialchars($dept['department_head']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">No Head Assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary">
                                                <?php echo $dept['employee_count']; ?> Employees
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($dept['description'] ?: 'No description'); ?>
                                        </td>
                                        <td>
                                            <?php echo date('M d, Y', strtotime($dept['created_at'])); ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editDepartmentModal"
                                                        data-id="<?php echo $dept['department_id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($dept['department_name']); ?>"
                                                        data-head="<?php echo htmlspecialchars($dept['department_head']); ?>"
                                                        data-description="<?php echo htmlspecialchars($dept['description']); ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="employee_directory.php?department=<?php echo urlencode($dept['department_name']); ?>" 
                                                   class="btn btn-sm btn-outline-info">
                                                    <i class="fas fa-users"></i>
                                                </a>
                                                <form method="POST" style="display: inline;" 
                                                      onsubmit="return confirm('Are you sure you want to delete this department?');">
                                                    <input type="hidden" name="dept_id" value="<?php echo $dept['department_id']; ?>">
                                                    <button type="submit" name="delete_department" 
                                                            class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Department Modal -->
<div class="modal fade" id="addDepartmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="dept_name" class="form-label">Department Name *</label>
                        <input type="text" class="form-control" id="dept_name" name="dept_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="dept_head" class="form-label">Department Head</label>
                        <select class="form-select" id="dept_head" name="dept_head">
                            <option value="">Select Department Head</option>
                            <?php 
                            if ($employees_result->num_rows > 0) {
                                while ($emp = $employees_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo htmlspecialchars($emp['full_name']); ?>">
                                    <?php echo htmlspecialchars($emp['full_name']); ?>
                                </option>
                            <?php 
                                endwhile;
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_department" class="btn btn-primary">Create Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Department Modal -->
<div class="modal fade" id="editDepartmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" id="edit_dept_id" name="dept_id">
                    <div class="mb-3">
                        <label for="edit_dept_name" class="form-label">Department Name *</label>
                        <input type="text" class="form-control" id="edit_dept_name" name="dept_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_dept_head" class="form-label">Department Head</label>
                        <select class="form-select" id="edit_dept_head" name="dept_head">
                            <option value="">Select Department Head</option>
                            <?php 
                            // Reset employees result for second modal
                            $employees_result->data_seek(0);
                            while ($emp = $employees_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo htmlspecialchars($emp['full_name']); ?>">
                                    <?php echo htmlspecialchars($emp['full_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_department" class="btn btn-primary">Update Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.stat-icon {
    opacity: 0.3;
}

.card {
    border-radius: 10px;
    transition: transform 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
}

.table th {
    font-weight: 600;
    background-color: #f8f9fa;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable
    $('#departmentsTable').DataTable({
        responsive: true,
        pageLength: 10,
        order: [[0, 'asc']]
    });

    // Handle edit department modal
    $('#editDepartmentModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var id = button.data('id');
        var name = button.data('name');
        var head = button.data('head');
        var description = button.data('description');

        var modal = $(this);
        modal.find('#edit_dept_id').val(id);
        modal.find('#edit_dept_name').val(name);
        modal.find('#edit_dept_head').val(head);
        modal.find('#edit_description').val(description);
    });
});
</script>

<?php include '../layouts/footer.php'; ?>
