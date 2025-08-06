<?php
/**
 * Advanced Leave Management System
 * Comprehensive leave application, approval, and tracking
 */

$page_title = "Leave Management";
require_once 'includes/hrms_config.php';

// Authentication check
if (!HRMSHelper::isLoggedIn()) {
    header('Location: ../hrms_portal.php?redirect=HRMS/leave_management.php');
    exit;
}

require_once '../layouts/header.php';
require_once '../layouts/sidebar.php';

$currentUserId = HRMSHelper::getCurrentUserId();
// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'apply_leave':
            $employeeId = $_POST['employee_id'] ?? 0;
            $leaveTypeId = $_POST['leave_type_id'] ?? 0;
            $startDate = $_POST['start_date'] ?? '';
            $endDate = $_POST['end_date'] ?? '';
            $reason = $_POST['reason'] ?? '';
            
            // Calculate days
            $start = new DateTime($startDate);
            $end = new DateTime($endDate);
            $days = $start->diff($end)->days + 1;
            
            try {
                $stmt = $conn->prepare("
                    INSERT INTO hr_leave_applications 
                    (employee_id, leave_type_id, start_date, end_date, days_requested, reason, status, applied_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->bind_param('iissis', $employeeId, $leaveTypeId, $startDate, $endDate, $days, $reason);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Leave application submitted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to submit leave application']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
    }
}

// Get leave statistics
$leaveStats = [
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'total_this_month' => 0
];

$statsQuery = "
    SELECT 
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) THEN 1 ELSE 0 END) as total_this_month
    FROM leave_requests
";
$result = mysqli_query($conn, $statsQuery);
if ($result) {
    $leaveStats = mysqli_fetch_assoc($result);
}

// Get leave types
$leaveTypes = [];
$result = mysqli_query($conn, "SELECT * FROM leave_types WHERE is_active = 1 ORDER BY display_name");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $leaveTypes[] = $row;
    }
}

// Get upcoming leaves
$upcomingLeaves = [];
$query = "
    SELECT lr.*, e.name as employee_name, e.employee_code
    FROM leave_requests lr
    JOIN employees e ON lr.employee_id = e.employee_id
    WHERE lr.status = 'approved' 
    AND lr.from_date >= CURDATE()
    ORDER BY lr.from_date ASC
    LIMIT 10
";
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $upcomingLeaves[] = $row;
    }
}

include '../layouts/header.php';
if (!isset($root_path)) 
include '../layouts/sidebar.php';
?>

<div class="main-content animate-fade-in-up">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="gradient-text mb-2" style="font-size: 2.5rem; font-weight: 700;">
                    <i class="bi bi-calendar-x text-warning me-3"></i>Leave Management
                </h1>
                <p class="text-muted" style="font-size: 1.1rem;">Manage employee leave requests, approvals, and policies</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-info" onclick="exportLeaveReport()">
                    <i class="bi bi-download"></i> Export Report
                </button>
                <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#addLeaveTypeModal">
                    <i class="bi bi-plus-circle"></i> Add Leave Type
                </button>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i><?= $success_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Leave Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-gradient-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="card-title h2 mb-2"><?= isset($leaveStats['pending']) ? $leaveStats['pending'] : 0 ?></h3>
                                <p class="card-text opacity-90">Pending Approvals</p>
                                <small class="opacity-75">Requires attention</small>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-clock"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-gradient-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="card-title h2 mb-2"><?= isset($leaveStats['approved']) ? $leaveStats['approved'] : 0 ?></h3>
                                <p class="card-text opacity-90">Approved Leaves</p>
                                <small class="opacity-75">All time</small>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-check-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-gradient-danger text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="card-title h2 mb-2"><?= isset($leaveStats['rejected']) ? $leaveStats['rejected'] : 0 ?></h3>
                                <p class="card-text opacity-90">Rejected Leaves</p>
                                <small class="opacity-75">All time</small>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-x-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-gradient-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="card-title h2 mb-2"><?= isset($leaveStats['total_this_month']) ? $leaveStats['total_this_month'] : 0 ?></h3>
                                <p class="card-text opacity-90">This Month</p>
                                <small class="opacity-75">Total requests</small>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-calendar-month"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="row g-4">
            <!-- Pending Approvals -->
            <div class="col-xl-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-clock text-warning"></i> Pending Approvals
                        </h5>
                        <span class="badge bg-warning"><?= count($pendingLeaves) ?> pending</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pendingLeaves)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-check-circle display-4"></i>
                                <p class="mt-3">No pending leave requests</p>
                                <small>All requests have been processed</small>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Leave Type</th>
                                            <th>Dates</th>
                                            <th>Days</th>
                                            <th>Reason</th>
                                            <th>Applied On</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pendingLeaves as $leave): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <h6 class="mb-0"><?= isset($leave['employee_name']) ? htmlspecialchars($leave['employee_name']) : 'N/A' ?></h6>
                                                        <small class="text-muted">
                                                            <?= isset($leave['employee_code']) ? htmlspecialchars($leave['employee_code']) : 'N/A' ?> • 
                                                            <?= isset($leave['position']) ? htmlspecialchars($leave['position']) : 'N/A' ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?= isset($leave['leave_type']) ? ucfirst($leave['leave_type']) : 'N/A' ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?= isset($leave['from_date']) ? date('M j', strtotime($leave['from_date'])) : 'N/A' ?></strong>
                                                        <?php if (isset($leave['from_date']) && isset($leave['to_date']) && $leave['from_date'] !== $leave['to_date']): ?>
                                                            to <strong><?= date('M j', strtotime($leave['to_date'])) ?></strong>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td><?= isset($leave['days_requested']) ? $leave['days_requested'] : 0 ?> days</td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?= isset($leave['reason']) ? htmlspecialchars(substr($leave['reason'], 0, 50)) : 'No reason provided' ?>
                                                        <?= isset($leave['reason']) && strlen($leave['reason']) > 50 ? '...' : '' ?>
                                                    </small>
                                                </td>
                                                <td><?= isset($leave['created_at']) ? date('M j, Y', strtotime($leave['created_at'])) : 'N/A' ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-success" 
                                                                onclick="approveLeave(<?= isset($leave['id']) ? $leave['id'] : 0 ?>)"
                                                                data-bs-toggle="tooltip" title="Approve">
                                                            <i class="bi bi-check"></i>
                                                        </button>
                                                        <button class="btn btn-danger" 
                                                                onclick="rejectLeave(<?= isset($leave['id']) ? $leave['id'] : 0 ?>)"
                                                                data-bs-toggle="tooltip" title="Reject">
                                                            <i class="bi bi-x"></i>
                                                        </button>
                                                        <button class="btn btn-info" 
                                                                onclick="viewLeaveDetails(<?= isset($leave['id']) ? $leave['id'] : 0 ?>)"
                                                                data-bs-toggle="tooltip" title="View Details">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Upcoming Leaves -->
            <div class="col-xl-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-calendar-event text-info"></i> Upcoming Leaves
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($upcomingLeaves)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-calendar-check display-4"></i>
                                <p class="mt-2">No upcoming leaves</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($upcomingLeaves as $leave): ?>
                                    <div class="list-group-item px-0 border-0">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?= isset($leave['employee_name']) ? htmlspecialchars($leave['employee_name']) : 'N/A' ?></h6>
                                                <p class="text-muted small mb-1">
                                                    <?= isset($leave['leave_type']) ? ucfirst($leave['leave_type']) : 'N/A' ?> • 
                                                    <?= isset($leave['days_requested']) ? $leave['days_requested'] : 0 ?> days
                                                </p>
                                                <small class="text-muted">
                                                    <?= isset($leave['from_date']) ? date('M j', strtotime($leave['from_date'])) : 'N/A' ?>
                                                    <?php if (isset($leave['from_date']) && isset($leave['to_date']) && $leave['from_date'] !== $leave['to_date']): ?>
                                                        - <?= date('M j', strtotime($leave['to_date'])) ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                            <small class="text-muted">
                                                <?= isset($leave['from_date']) ? date('M j', strtotime($leave['from_date'])) : 'N/A' ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Leave History -->
        <div class="row g-4 mt-2">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-clock-history text-primary"></i> Leave History
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="leaveHistoryTable">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Leave Type</th>
                                        <th>Dates</th>
                                        <th>Days</th>
                                        <th>Status</th>
                                        <th>Approved By</th>
                                        <th>Applied On</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allLeaves as $leave): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <h6 class="mb-0"><?= isset($leave['employee_name']) ? htmlspecialchars($leave['employee_name']) : 'N/A' ?></h6>
                                                    <small class="text-muted"><?= isset($leave['employee_code']) ? htmlspecialchars($leave['employee_code']) : 'N/A' ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?= isset($leave['leave_type']) ? ucfirst($leave['leave_type']) : 'N/A' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= isset($leave['from_date']) ? date('M j', strtotime($leave['from_date'])) : 'N/A' ?>
                                                <?php if (isset($leave['from_date']) && isset($leave['to_date']) && $leave['from_date'] !== $leave['to_date']): ?>
                                                    - <?= date('M j', strtotime($leave['to_date'])) ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= isset($leave['days_requested']) ? $leave['days_requested'] : 0 ?></td>
                                            <td>
                                                <?php
                                                $statusClass = isset($leave['status']) ? match($leave['status']) {
                                                    'approved' => 'success',
                                                    'rejected' => 'danger',
                                                    'pending' => 'warning',
                                                    default => 'secondary'
                                                } : 'secondary';
                                                ?>
                                                <span class="badge bg-<?= $statusClass ?>">
                                                    <?= isset($leave['status']) ? ucfirst($leave['status']) : 'Unknown' ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($leave['approver_name'] ?? 'N/A') ?></td>
                                            <td><?= isset($leave['created_at']) ? date('M j, Y', strtotime($leave['created_at'])) : 'N/A' ?></td>
                                            <td>
                                                <button class="btn btn-outline-info btn-sm" 
                                                        onclick="viewLeaveDetails(<?= isset($leave['id']) ? $leave['id'] : 0 ?>)"
                                                        data-bs-toggle="tooltip" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Approve Leave Modal -->
<div class="modal fade" id="approveLeaveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Approve Leave Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="approveLeaveForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="approve_leave">
                    <input type="hidden" name="leave_id" id="approve_leave_id">
                    <div class="mb-3">
                        <label class="form-label">Comments (Optional)</label>
                        <textarea class="form-control" name="comments" rows="3" placeholder="Add any comments or conditions for approval"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve Leave</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Leave Modal -->
<div class="modal fade" id="rejectLeaveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Leave Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="rejectLeaveForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="reject_leave">
                    <input type="hidden" name="leave_id" id="reject_leave_id">
                    <div class="mb-3">
                        <label class="form-label">Reason for Rejection *</label>
                        <textarea class="form-control" name="comments" rows="3" placeholder="Please provide reason for rejection" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Leave</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Leave Type Modal -->
<div class="modal fade" id="addLeaveTypeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Leave Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_leave_type">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Type Name *</label>
                            <input type="text" class="form-control" name="type_name" required>
                            <small class="text-muted">Internal name (lowercase, no spaces)</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Display Name *</label>
                            <input type="text" class="form-control" name="display_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Annual Limit (Days) *</label>
                            <input type="number" class="form-control" name="annual_limit" step="0.5" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Min Days Advance Notice</label>
                            <input type="number" class="form-control" name="min_days_advance" value="2">
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="requires_approval" value="1" checked>
                                <label class="form-check-label">Requires Approval</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="carry_forward_allowed" value="1">
                                <label class="form-check-label">Allow Carry Forward</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Add Leave Type</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Initialize DataTable
document.addEventListener('DOMContentLoaded', function() {
    $('#leaveHistoryTable').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[6, 'desc']],
        columnDefs: [
            { orderable: false, targets: [7] }
        ]
    });
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Approve Leave Function
function approveLeave(leaveId) {
    document.getElementById('approve_leave_id').value = leaveId;
    const modal = new bootstrap.Modal(document.getElementById('approveLeaveModal'));
    modal.show();
}

// Reject Leave Function
function rejectLeave(leaveId) {
    document.getElementById('reject_leave_id').value = leaveId;
    const modal = new bootstrap.Modal(document.getElementById('rejectLeaveModal'));
    modal.show();
}

// View Leave Details Function
function viewLeaveDetails(leaveId) {
    // Implement leave details modal
    alert('View leave details for ID: ' + leaveId);
}

// Export Leave Report Function
function exportLeaveReport() {
    window.open('api/export_leave_report.php', '_blank');
}
</script>

<?php if (!isset($root_path)) 
include '../layouts/footer.php'; ?>
