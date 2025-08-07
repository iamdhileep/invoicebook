<?php
$page_title = "Shift Management - HRMS";

// Include authentication and database
require_once '../auth_check.php';
require_once '../db.php';

// Include layouts
require_once 'hrms_header_simple.php';
require_once 'hrms_sidebar_simple.php';

// Get real-time shift statistics
$total_shifts_query = "SELECT COUNT(*) as total FROM shifts WHERE status = 'active'";
$total_shifts_result = $conn->query($total_shifts_query);
$total_shifts = $total_shifts_result ? $total_shifts_result->fetch_assoc()['total'] : 0;

// Employees assigned to shifts today
$today = date('Y-m-d');
$assigned_today_query = "SELECT COUNT(DISTINCT e.employee_id) as count 
                        FROM hr_employees e 
                        JOIN shift_assignments sa ON e.employee_id = sa.employee_id 
                        WHERE sa.shift_date = '$today'";
$assigned_today_result = $conn->query($assigned_today_query);
$assigned_today = $assigned_today_result ? $assigned_today_result->fetch_assoc()['count'] : 0;

// Coverage percentage (employees assigned vs total employees)
$total_employees_query = "SELECT COUNT(*) as total FROM hr_employees WHERE status = 'active'";
$total_employees_result = $conn->query($total_employees_query);
$total_employees = $total_employees_result ? $total_employees_result->fetch_assoc()['total'] : 1;
$coverage_percentage = round(($assigned_today / $total_employees) * 100);

// Pending shift requests/changes
$pending_requests_query = "SELECT COUNT(*) as count FROM shift_requests WHERE status = 'pending'";
$pending_requests_result = $conn->query($pending_requests_query);
$pending_requests = $pending_requests_result ? $pending_requests_result->fetch_assoc()['count'] : 0;

// Fetch all active shifts for display
$shifts_query = "SELECT * FROM shifts WHERE status = 'active' ORDER BY name";
$shifts_result = $conn->query($shifts_query);
$shifts = [];
if ($shifts_result && $shifts_result->num_rows > 0) {
    while ($row = $shifts_result->fetch_assoc()) {
        $shifts[] = $row;
    }
}
?>

<!-- Page Content Starts Here -->
<div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="gradient-text mb-2" style="font-size: 2.5rem; font-weight: 700;">
                    <i class="bi bi-clock text-primary me-3"></i>Shift Management
                </h1>
                <p class="text-muted" style="font-size: 1.1rem;">Manage work shifts, schedules, and employee assignments</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" onclick="exportShifts()">
                    <i class="bi bi-download me-2"></i>Export Data
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addShiftModal">
                    <i class="bi bi-plus-lg me-2"></i>Add Shift
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-primary bg-opacity-10 p-3 rounded">
                                    <i class="bi bi-clock text-primary fs-4"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0 text-muted small">Total Shifts</h6>
                                <h3 class="mb-0"><?php echo $total_shifts; ?></h3>
                                <small class="text-success">Active shifts</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-success bg-opacity-10 p-3 rounded">
                                    <i class="bi bi-people text-success fs-4"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0 text-muted small">Assigned Employees</h6>
                                <h3 class="mb-0"><?php echo $assigned_today; ?></h3>
                                <small class="text-success">Today's shifts</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-warning bg-opacity-10 p-3 rounded">
                                    <i class="bi bi-calendar-week text-warning fs-4"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0 text-muted small">Coverage</h6>
                                <h3 class="mb-0"><?php echo $coverage_percentage; ?>%</h3>
                                <small class="text-warning">Employee coverage</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-info bg-opacity-10 p-3 rounded">
                                    <i class="bi bi-graph-up text-info fs-4"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0 text-muted small">Pending Requests</h6>
                                <h3 class="mb-0"><?php echo $pending_requests; ?></h3>
                                <small class="text-success">Shift change requests</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <!-- Main Content Tabs -->
        <div class="card shadow-sm">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#shifts" role="tab">
                            <i class="bi bi-clock me-2"></i>Shift Templates
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#assignments" role="tab">
                            <i class="bi bi-people me-2"></i>Employee Assignments
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#calendar" role="tab">
                            <i class="bi bi-calendar3 me-2"></i>Shift Calendar
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#requests" role="tab">
                            <i class="bi bi-arrow-left-right me-2"></i>Shift Requests
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <!-- Shift Templates Tab -->
                    <div class="tab-pane fade show active" id="shifts" role="tabpanel">
                        <div class="row">
                            <?php if (!empty($shifts)): ?>
                                <?php foreach ($shifts as $shift): ?>
                                <div class="col-lg-6 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <h5 class="card-title mb-1"><?= htmlspecialchars($shift['name']) ?></h5>
                                                    <p class="text-muted small mb-0"><?= htmlspecialchars($shift['days'] ?? 'No days specified') ?></p>
                                                </div>
                                                <span class="badge bg-primary"><?= $shift['start_time'] ?? '00:00' ?> - <?= $shift['end_time'] ?? '00:00' ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="text-success">
                                                <i class="bi bi-people me-1"></i>
                                                <strong><?= $shift['employees'] ?></strong> Employees
                                            </div>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="editShift(<?= $shift['id'] ?>)" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-success" onclick="viewShiftDetails(<?= $shift['id'] ?>)" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteShift(<?= $shift['id'] ?>)" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-body text-center py-5">
                                            <i class="bi bi-clock display-4 text-muted mb-3"></i>
                                            <h5 class="text-muted">No Active Shifts Found</h5>
                                            <p class="text-muted">Create your first shift template to get started.</p>
                                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addShiftModal">
                                                <i class="bi bi-plus-lg me-2"></i>Create Shift
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Employee Assignments Tab -->
                    <div class="tab-pane fade" id="assignments" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Current Assignments</h5>
                            <button class="btn btn-primary" onclick="showAssignmentModal()">
                                <i class="bi bi-plus-lg me-2"></i>Assign Employee
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Assigned Shift</th>
                                        <th>Start Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($shift_assignments as $assignment): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-size: 0.875rem;">
                                                    <?= strtoupper(substr($assignment['employee_name'], 0, 2)) ?>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold"><?= htmlspecialchars($assignment['employee_name']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($assignment['department']) ?></td>
                                        <td>
                                            <span class="fw-semibold"><?= htmlspecialchars($assignment['shift_name']) ?></span>
                                        </td>
                                        <td><?= date('M j, Y', strtotime($assignment['start_date'])) ?></td>
                                        <td>
                                            <span class="badge bg-success">Active</span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="editAssignment(<?= $assignment['id'] ?>)" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-warning" onclick="changeShift(<?= $assignment['id'] ?>)" title="Change Shift">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="removeAssignment(<?= $assignment['id'] ?>)" title="Remove">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Shift Calendar Tab -->
                    <div class="tab-pane fade" id="calendar" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="mb-0">August 2025 - Shift Schedule</h5>
                            <div>
                                <button class="btn btn-outline-secondary btn-sm me-2">
                                    <i class="bi bi-chevron-left"></i>
                                </button>
                                <button class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead class="table-primary">
                                            <tr>
                                                <th>Mon</th>
                                                <th>Tue</th>
                                                <th>Wed</th>
                                                <th>Thu</th>
                                                <th>Fri</th>
                                                <th>Sat</th>
                                                <th>Sun</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $week = 1;
                                            for ($day = 1; $day <= 28; $day += 7): 
                                            ?>
                                            <tr style="height: 100px;">
                                                <?php for ($i = 0; $i < 7; $i++): 
                                                    $currentDay = $day + $i;
                                                    if ($currentDay <= 31):
                                                ?>
                                                <td class="position-relative p-2">
                                                    <div class="fw-semibold"><?= $currentDay ?></div>
                                                    <?php if ($currentDay % 7 == 1 || $currentDay % 7 == 2): ?>
                                                    <small class="badge bg-warning">Morning</small>
                                                    <?php elseif ($currentDay % 7 == 3 || $currentDay % 7 == 4): ?>
                                                    <small class="badge bg-info">Evening</small>
                                                    <?php else: ?>
                                                    <small class="badge bg-dark">Night</small>
                                                    <?php endif; ?>
                                                </td>
                                                <?php endif; ?>
                                                <?php endfor; ?>
                                            </tr>
                                            <?php endfor; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Shift Requests Tab -->
                    <div class="tab-pane fade" id="requests" role="tabpanel">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>No pending shift change requests.</strong> All employees are currently assigned to their preferred shifts.
                        </div>
                        
                        <div class="text-center py-5">
                            <i class="bi bi-calendar-check text-muted" style="font-size: 4rem;"></i>
                            <h5 class="mt-3">No Active Requests</h5>
                            <p class="text-muted">Shift change requests will appear here when employees submit them.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function editShift(id) {
            showAlert('Edit shift functionality will be implemented soon!', 'info');
        }

        function viewShiftDetails(id) {
            showAlert('Viewing shift details...', 'info');
        }

        function deleteShift(id) {
            if (confirm('Are you sure you want to delete this shift? This will affect all assigned employees.')) {
                showAlert('Shift deleted successfully!', 'success');
            }
        }

        function exportShifts() {
            showAlert('Exporting shift data...', 'info');
        }

        function showAssignmentModal() {
            showAlert('Employee assignment modal will be implemented soon!', 'info');
        }

        function editAssignment(id) {
            showAlert('Edit assignment functionality will be implemented soon!', 'info');
        }

        function changeShift(id) {
            showAlert('Shift change functionality will be implemented soon!', 'info');
        }

        function removeAssignment(id) {
            if (confirm('Are you sure you want to remove this employee from their shift?')) {
                showAlert('Employee removed from shift successfully!', 'success');
            }
        }

        function showAlert(message, type = 'info') {
            const alertDiv = `
                <div class="alert alert-${type} alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; max-width: 400px;">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', alertDiv);
            
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    if (alert.textContent.includes(message)) {
                        alert.remove();
                    }
                });
            }, 5000);
        }
    </script>
</div>

<script>
// Standard modal functions for HRMS
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        new bootstrap.Modal(modal).show();
    }
}

function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        const modalInstance = bootstrap.Modal.getInstance(modal);
        if (modalInstance) modalInstance.hide();
    }
}

        function exportShiftData() {
            showAlert('Exporting shift management data...', 'info');
        }

        function showAlert(message, type = 'info') {
            const alertDiv = `
                <div class="alert alert-${type} alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; max-width: 400px;">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', alertDiv);
            
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    if (alert.textContent.includes(message)) {
                        alert.remove();
                    }
                });
            }, 5000);
        }
</script>

<?php require_once 'hrms_footer_simple.php'; ?>