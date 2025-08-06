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
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="h5 mb-0">👥 Employee Management</h1>
                <p class="text-muted small">Manage your team members and their information</p>
            </div>
            <div>
                <a href="../../add_employee.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-person-plus"></i> Add New Employee
                </a>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row g-2 mb-3">
            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-people fs-3" style="color: #1976d2;"></i>
                        </div>
                        <h5 class="mb-1 fw-bold" style="color: #1976d2;"><?= $totalEmployees ?></h5>
                        <small class="text-muted">Total Employees</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-currency-rupee fs-3" style="color: #388e3c;"></i>
                        </div>
                        <h5 class="mb-1 fw-bold" style="color: #388e3c;">₹<?= number_format($totalSalary, 0) ?></h5>
                        <small class="text-muted">Total Salary</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%);">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-calculator fs-3" style="color: #f57c00;"></i>
                        </div>
                        <h5 class="mb-1 fw-bold" style="color: #f57c00;">₹<?= number_format($avgSalary, 0) ?></h5>
                        <small class="text-muted">Average Salary</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%);">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-person-check fs-3" style="color: #7b1fa2;"></i>
                        </div>
                        <h5 class="mb-1 fw-bold" style="color: #7b1fa2;" id="activeToday">--</h5>
                        <small class="text-muted">Active Today</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-3 border-0 shadow-sm">
            <div class="card-header bg-light border-0 py-2">
                <h6 class="mb-0 text-dark"><i class="bi bi-funnel me-2"></i>Filter Employees</h6>
            </div>
            <div class="card-body p-3">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small">Search Employee</label>
                        <input type="text" name="search" class="form-control form-control-sm" 
                               placeholder="Search by name, code, or phone" 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Filter by Position</label>
                        <select name="position" class="form-select form-select-sm">
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
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-search"></i> Filter
                            </button>
                            <a href="employees.php" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-arrow-clockwise"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Employees Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light border-0 py-2 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 text-dark"><i class="bi bi-table me-2"></i>Employee List</h6>
                <div>
                    <button class="btn btn-outline-success btn-sm" onclick="exportEmployees()">
                        <i class="bi bi-download"></i> Export Excel
                    </button>
                    <button class="btn btn-outline-primary btn-sm" onclick="bulkActions()">
                        <i class="bi bi-gear"></i> Bulk Actions
                    </button>
                </div>
            </div>
            <div class="card-body p-3">
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
                                                     style="width: 40px; height: 40px; object-fit: cover;"
                                                     alt="<?= htmlspecialchars($emp['name']) ?>">
                                            <?php else: ?>
                                                <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center" 
                                                     style="width: 40px; height: 40px;">
                                                    <i class="bi bi-person text-white fs-6"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div>
                                                <strong class="small"><?= htmlspecialchars($emp['name']) ?></strong>
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
                                            <div class="small">
                                                <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($emp['phone']) ?>
                                                <?php if (!empty($emp['email'])): ?>
                                                    <br><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($emp['email']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <strong class="text-success small">₹<?= number_format($emp['monthly_salary'], 2) ?></strong>
                                            <br><small class="text-muted">per month</small>
                                        </td>
                                        <td>
                                            <span class="status-badge active">Active</span>
                                        </td>
                                        <td>
                                            <div class="dt-action-buttons">
                                                <button class="dt-btn dt-btn-view" 
                                                        onclick="viewEmployee(<?= $emp['employee_id'] ?>)"
                                                        title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <a href="../../edit_employee.php?id=<?= $emp['employee_id'] ?>" 
                                                   class="dt-btn dt-btn-edit"
                                                   title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button class="dt-btn dt-btn-delete delete-employee" 
                                                        data-id="<?= $emp['employee_id'] ?>"
                                                        data-name="<?= htmlspecialchars($emp['name']) ?>"
                                                        title="Delete">
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
                    <div class="text-center py-4">
                        <i class="bi bi-people fs-1 text-muted mb-3 d-block"></i>
                        <h6 class="text-muted mb-2">No employees found</h6>
                        <p class="text-muted small mb-3">No employees match your current filters.</p>
                        <a href="../../add_employee.php" class="btn btn-primary btn-sm">
                            <i class="bi bi-person-plus"></i> Add First Employee
                        </a>
                    </div>
                <?php endif; ?>
            </div>
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
    
    // Initialize Enhanced DataTable
    const employeesTable = initDataTable('#employeesTable', {
        pageLength: 25,
        order: [[2, "asc"]], // Sort by name
        columnDefs: [
            { orderable: false, targets: [0, 1, 8] },
            { searchable: false, targets: [0, 1, 8] }
        ]
    });
    
    // Add export buttons
    addExportButtons(employeesTable, 'employees');

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

<style>
/* Enhanced Employee Management Styling */
.main-content {
    padding: 10px;
}

.container-fluid {
    max-width: 100%;
    padding: 0 10px;
}

/* Statistics Cards Enhancements */
.statistics-card {
    transition: all 0.3s ease;
}

.statistics-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

/* Icon Animation */
.statistics-card i {
    transition: transform 0.3s ease;
}

.statistics-card:hover i {
    transform: scale(1.1);
}

/* Responsive Grid Enhancements */
@media (max-width: 1200px) {
    .col-xl-3 {
        flex: 0 0 50%;
        max-width: 50%;
    }
}

@media (max-width: 768px) {
    .col-xl-3, .col-lg-4 {
        flex: 0 0 50%;
        max-width: 50%;
    }
    
    .main-content {
        padding: 5px;
    }
    
    .container-fluid {
        padding: 0 5px;
    }
    
    .row {
        margin: 0 -5px;
    }
    
    .row > * {
        padding: 0 5px;
    }
}

@media (max-width: 576px) {
    .col-xl-3, .col-lg-4, .col-md-6 {
        flex: 0 0 100%;
        max-width: 100%;
    }
    
    .statistics-card .card-body {
        padding: 1rem !important;
    }
}

/* Card Hover Effects */
.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
}

/* Table Enhancements */
.table th {
    font-size: 0.875rem !important;
    font-weight: 600 !important;
    border-bottom: 3px solid var(--primary-color) !important;
    background: linear-gradient(135deg, var(--gray-200) 0%, var(--gray-100) 100%) !important;
    color: var(--gray-900) !important;
}

.table td {
    font-size: 0.875rem;
    vertical-align: middle;
    color: var(--gray-800) !important;
}

/* Badge Enhancements */
.badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
}

/* Form Control Sizing */
.form-control-sm, .form-select-sm {
    font-size: 0.875rem;
}

.form-label.small {
    font-size: 0.875rem;
    font-weight: 500;
    color: #495057;
}

/* Button Group Responsiveness */
.btn-group-sm > .btn {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

/* DataTable Responsive */
@media (max-width: 992px) {
    .dataTables_wrapper .dataTables_filter,
    .dataTables_wrapper .dataTables_length {
        text-align: left;
        margin-bottom: 10px;
    }
    
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_paginate {
        text-align: center;
        margin-top: 10px;
    }
}

/* Empty State Styling */
.text-center.py-4 {
    padding: 2rem 0 !important;
}

.text-center.py-4 .bi {
    color: #6c757d;
}

/* Employee Avatar Styling */
.bg-secondary.rounded-circle {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%) !important;
}

/* Modal Enhancements */
.modal-content {
    border: none;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.modal-header {
    background: var(--gray-50);
    border-bottom: 1px solid var(--gray-200);
}
</style>

<!-- DataTables JavaScript -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

<?php include '../../layouts/footer.php'; ?>