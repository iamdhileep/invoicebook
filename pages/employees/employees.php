<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Employee Management';

// Handle search and filtering
$search = $_GET['search'] ?? '';
$position = $_GET['position'] ?? '';

// Build WHERE clause
$where = "WHERE 1=1";
if ($search) {
    $where .= " AND (name LIKE '%" . mysqli_real_escape_string($conn, $search) . "%' 
                OR employee_code LIKE '%" . mysqli_real_escape_string($conn, $search) . "%' 
                OR phone LIKE '%" . mysqli_real_escape_string($conn, $search) . "%')";
}
if ($position) {
    $where .= " AND position = '" . mysqli_real_escape_string($conn, $position) . "'";
}

// Get employees with corrected column names
$employees = $conn->query("SELECT * FROM employees $where ORDER BY name ASC");

if (!$employees) {
    die("Query Failed: " . $conn->error);
}

// Get distinct positions for filter
$positions = $conn->query("SELECT DISTINCT position FROM employees WHERE position IS NOT NULL AND position != '' ORDER BY position");

// Get employee statistics
$totalEmployees = 0;
$totalSalary = 0;
$statsQuery = $conn->query("SELECT COUNT(*) as count, SUM(monthly_salary) as total_salary FROM employees $where");
if ($statsQuery && $row = $statsQuery->fetch_assoc()) {
    $totalEmployees = $row['count'] ?? 0;
    $totalSalary = $row['total_salary'] ?? 0;
}

$avgSalary = $totalEmployees > 0 ? $totalSalary / $totalEmployees : 0;

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Employee Management</h1>
            <p class="text-muted">Manage your team members and their information</p>
        </div>
        <div>
            <a href="../../add_employee.php" class="btn btn-primary">
                <i class="bi bi-person-plus"></i> Add New Employee
            </a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Employees</h6>
                            <h3 class="mb-0"><?= $totalEmployees ?></h3>
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
                            <h6 class="card-title mb-0">Total Salary</h6>
                            <h3 class="mb-0">₹<?= number_format($totalSalary, 0) ?></h3>
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
                            <h6 class="card-title mb-0">Average Salary</h6>
                            <h3 class="mb-0">₹<?= number_format($avgSalary, 0) ?></h3>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-calculator"></i>
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
                            <h6 class="card-title mb-0">Active Today</h6>
                            <h3 class="mb-0" id="activeToday">--</h3>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-person-check"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filters</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Search Employee</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search by name, code, or phone" 
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Filter by Position</label>
                    <select name="position" class="form-select">
                        <option value="">All Positions</option>
                        <?php if ($positions && mysqli_num_rows($positions) > 0): ?>
                            <?php while ($pos = $positions->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($pos['position']) ?>" 
                                        <?= $position === $pos['position'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pos['position']) ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Filter
                        </button>
                        <a href="employees.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-clockwise"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Employees Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Employee List</h5>
            <div>
                <button class="btn btn-outline-success btn-sm" onclick="exportEmployees()">
                    <i class="bi bi-download"></i> Export Excel
                </button>
                <button class="btn btn-outline-primary btn-sm" onclick="bulkActions()">
                    <i class="bi bi-gear"></i> Bulk Actions
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if ($employees->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="employeesTable">
                        <thead>
                            <tr>
                                <th width="30">
                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                </th>
                                <th>Photo</th>
                                <th>Employee</th>
                                <th>Code</th>
                                <th>Position</th>
                                <th>Contact</th>
                                <th>Salary</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($emp = $employees->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_ids[]" value="<?= $emp['employee_id'] ?>" class="form-check-input employee-checkbox">
                                    </td>
                                    <td>
                                        <?php if (!empty($emp['photo']) && file_exists('../../' . $emp['photo'])): ?>
                                            <img src="../../<?= htmlspecialchars($emp['photo']) ?>" 
                                                 class="rounded-circle" 
                                                 style="width: 50px; height: 50px; object-fit: cover;"
                                                 alt="<?= htmlspecialchars($emp['name']) ?>">
                                        <?php else: ?>
                                            <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center" 
                                                 style="width: 50px; height: 50px;">
                                                <i class="bi bi-person text-white fs-4"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($emp['name']) ?></strong>
                                            <?php if (!empty($emp['address'])): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars(substr($emp['address'], 0, 30)) ?>...</small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?= htmlspecialchars($emp['employee_code']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($emp['position']) ?></span>
                                    </td>
                                    <td>
                                        <div>
                                            <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($emp['phone']) ?>
                                            <?php if (!empty($emp['email'])): ?>
                                                <br><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($emp['email']) ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <strong class="text-success">₹<?= number_format($emp['monthly_salary'], 2) ?></strong>
                                        <br><small class="text-muted">per month</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-success">Active</span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-info" 
                                                    onclick="viewEmployee(<?= $emp['employee_id'] ?>)"
                                                    data-bs-toggle="tooltip" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <a href="../../edit_employee.php?id=<?= $emp['employee_id'] ?>" 
                                               class="btn btn-outline-primary"
                                               data-bs-toggle="tooltip" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button class="btn btn-outline-danger delete-employee" 
                                                    data-id="<?= $emp['employee_id'] ?>"
                                                    data-name="<?= htmlspecialchars($emp['name']) ?>"
                                                    data-bs-toggle="tooltip" title="Delete">
                                                <i class="bi bi-trash"></i>
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
                    <i class="bi bi-people fs-1 text-muted mb-3"></i>
                    <h5 class="text-muted">No employees found</h5>
                    <p class="text-muted">No employees match your current filters.</p>
                    <a href="../../add_employee.php" class="btn btn-primary">
                        <i class="bi bi-person-plus"></i> Add First Employee
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Employee Details Modal -->
<div class="modal fade" id="employeeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Employee Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="employeeModalBody">
                <!-- Employee details will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    
    // Initialize DataTable
    $('#employeesTable').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        responsive: true,
        order: [[2, "asc"]], // Sort by name
        columnDefs: [
            { orderable: false, targets: [0, 1, 8] }
        ]
    });

    // Select all functionality
    $('#selectAll').change(function() {
        $('.employee-checkbox').prop('checked', this.checked);
    });

    // Individual checkbox change - using event delegation
    $(document).on('change', '.employee-checkbox', function() {
        const totalCheckboxes = $('.employee-checkbox').length;
        const checkedCheckboxes = $('.employee-checkbox:checked').length;
        
        $('#selectAll').prop('checked', totalCheckboxes === checkedCheckboxes);
    });

    // Delete employee - using event delegation for DataTables compatibility
    $(document).on('click', '.delete-employee', function() {
        const employeeId = $(this).data('id');
        const employeeName = $(this).data('name');
        const row = $(this).closest('tr');
        const button = $(this);
        
        if (confirm(`Are you sure you want to delete employee "${employeeName}"? This action cannot be undone.`)) {
            // Show loading state
            const originalContent = button.html();
            button.html('<i class="bi bi-hourglass-split"></i>').prop('disabled', true);
            
            $.post('../../delete_employee.php', {
                id: employeeId
            }, function(response) {
                if (response.success) {
                    row.fadeOut(300, function() {
                        $(this).remove();
                        // Reload page to update totals
                        setTimeout(() => location.reload(), 500);
                    });
                    showAlert(response.message || 'Employee deleted successfully', 'success');
                } else {
                    showAlert(response.message || 'Failed to delete employee', 'danger');
                    // Restore button
                    button.html(originalContent).prop('disabled', false);
                }
            }, 'json').fail(function(xhr, status, error) {
                showAlert('Error occurred while deleting employee: ' + error, 'danger');
                // Restore button
                button.html(originalContent).prop('disabled', false);
            });
        }
    });

    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();

    // Load active employees count for today
    loadActiveToday();
});

function viewEmployee(employeeId) {
    // Load employee details via AJAX
    $.get('../../get_employee_details.php', {id: employeeId}, function(data) {
        $('#employeeModalBody').html(data);
        new bootstrap.Modal(document.getElementById('employeeModal')).show();
    }).fail(function() {
        showAlert('Failed to load employee details', 'danger');
    });
}

function exportEmployees() {
    const params = new URLSearchParams(window.location.search);
    window.open('../../export_employees.php?' + params.toString());
}

function bulkActions() {
    const selectedIds = $('.employee-checkbox:checked').map(function() {
        return this.value;
    }).get();

    if (selectedIds.length === 0) {
        showAlert('Please select at least one employee', 'warning');
        return;
    }

    // Show bulk actions modal or menu
    showAlert(`Selected ${selectedIds.length} employee(s). Bulk actions coming soon!`, 'info');
}

function loadActiveToday() {
    $.get('../../get_active_employees.php', {date: '<?= date('Y-m-d') ?>'}, function(data) {
        $('#activeToday').text(data.count || '--');
    }, 'json').fail(function() {
        $('#activeToday').text('--');
    });
}

function showAlert(message, type) {
    const alertDiv = $(`
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            <i class="bi bi-info-circle me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `);
    $('.main-content').prepend(alertDiv);
    
    setTimeout(() => {
        alertDiv.fadeOut();
    }, 5000);
}
</script>

<?php include '../../layouts/footer.php'; ?>