<?php
/**
 * Advanced Leave Management System
 * Comprehensive leave application, approval, and tracking
 */

$page_title = "Advanced Leave Management";
require_once 'includes/hrms_config.php';

// Authentication check
if (!HRMSHelper::isLoggedIn()) {
    header('Location: ../hrms_portal.php?redirect=HRMS/advanced_leave_management.php');
    exit;
}

require_once '../layouts/header.php';
require_once '../layouts/sidebar.php';

$currentUserId = HRMSHelper::getCurrentUserId();
$currentUserRole = HRMSHelper::getCurrentUserRole();

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
            
        case 'approve_leave':
            $applicationId = $_POST['application_id'] ?? 0;
            $action = $_POST['approve_action'] ?? '';
            $comments = $_POST['comments'] ?? '';
            
            $status = ($action === 'approve') ? 'approved' : 'rejected';
            
            try {
                $stmt = $conn->prepare("
                    UPDATE hr_leave_applications 
                    SET status = ?, approved_by = ?, approved_at = NOW(), comments = ?
                    WHERE id = ?
                ");
                $stmt->bind_param('sisi', $status, $currentUserId, $comments, $applicationId);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Leave application ' . $status]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update leave application']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'get_leave_balance':
            $employeeId = $_POST['employee_id'] ?? 0;
            $leaveTypeId = $_POST['leave_type_id'] ?? 0;
            
            try {
                // Get leave type details
                $stmt = $conn->prepare("SELECT * FROM hr_leave_types WHERE id = ?");
                $stmt->bind_param('i', $leaveTypeId);
                $stmt->execute();
                $leaveType = $stmt->get_result()->fetch_assoc();
                
                // Calculate used days this year
                $stmt = $conn->prepare("
                    SELECT SUM(days_requested) as used_days 
                    FROM hr_leave_applications 
                    WHERE employee_id = ? AND leave_type_id = ? 
                    AND status = 'approved' 
                    AND YEAR(start_date) = YEAR(CURDATE())
                ");
                $stmt->bind_param('ii', $employeeId, $leaveTypeId);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                $usedDays = $result['used_days'] ?? 0;
                
                $availableDays = $leaveType['days_allowed'] - $usedDays;
                
                echo json_encode([
                    'success' => true,
                    'leave_type' => $leaveType,
                    'used_days' => $usedDays,
                    'available_days' => max(0, $availableDays)
                ]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
    }
}

// Get leave applications based on user role
$leaveApplications = [];
try {
    if ($currentUserRole === 'hr' || $currentUserRole === 'admin') {
        // HR can see all leave applications
        $result = HRMSHelper::safeQuery("
            SELECT 
                la.*,
                e.first_name, e.last_name, e.employee_id,
                lt.name as leave_type_name,
                d.name as department_name,
                approver.first_name as approver_first_name,
                approver.last_name as approver_last_name
            FROM hr_leave_applications la
            LEFT JOIN hr_employees e ON la.employee_id = e.id
            LEFT JOIN hr_leave_types lt ON la.leave_type_id = lt.id
            LEFT JOIN hr_departments d ON e.department_id = d.id
            LEFT JOIN hr_employees approver ON la.approved_by = approver.id
            ORDER BY la.applied_at DESC
            LIMIT 50
        ");
    } else {
        // Employees see only their applications
        $result = HRMSHelper::safeQuery("
            SELECT 
                la.*,
                e.first_name, e.last_name, e.employee_id,
                lt.name as leave_type_name,
                d.name as department_name,
                approver.first_name as approver_first_name,
                approver.last_name as approver_last_name
            FROM hr_leave_applications la
            LEFT JOIN hr_employees e ON la.employee_id = e.id
            LEFT JOIN hr_leave_types lt ON la.leave_type_id = lt.id
            LEFT JOIN hr_departments d ON e.department_id = d.id
            LEFT JOIN hr_employees approver ON la.approved_by = approver.id
            WHERE e.user_id = $currentUserId
            ORDER BY la.applied_at DESC
        ");
    }
    
    while ($row = $result->fetch_assoc()) {
        $leaveApplications[] = $row;
    }
} catch (Exception $e) {
    error_log("Leave applications fetch error: " . $e->getMessage());
}

// Get leave types
$leaveTypes = [];
try {
    $result = HRMSHelper::safeQuery("SELECT * FROM hr_leave_types WHERE is_active = 1 ORDER BY name");
    while ($row = $result->fetch_assoc()) {
        $leaveTypes[] = $row;
    }
} catch (Exception $e) {
    error_log("Leave types fetch error: " . $e->getMessage());
}

// Get employees (for HR users)
$employees = [];
if ($currentUserRole === 'hr' || $currentUserRole === 'admin') {
    try {
        $result = HRMSHelper::safeQuery("SELECT id, employee_id, first_name, last_name, department_id FROM hr_employees WHERE is_active = 1 ORDER BY first_name, last_name");
        while ($row = $result->fetch_assoc()) {
            $employees[] = $row;
        }
    } catch (Exception $e) {
        error_log("Employees fetch error: " . $e->getMessage());
    }
}

// Calculate statistics
$stats = [
    'total_applications' => count($leaveApplications),
    'pending_applications' => count(array_filter($leaveApplications, function($app) { return $app['status'] === 'pending'; })),
    'approved_applications' => count(array_filter($leaveApplications, function($app) { return $app['status'] === 'approved'; })),
    'rejected_applications' => count(array_filter($leaveApplications, function($app) { return $app['status'] === 'rejected'; }))
];
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h2 mb-1">
                            <i class="fas fa-calendar-alt text-primary me-2"></i>
                            Advanced Leave Management
                        </h1>
                        <p class="text-muted mb-0">Comprehensive leave application, approval, and tracking system</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-info" onclick="showLeaveCalendar()">
                            <i class="fas fa-calendar me-1"></i>Leave Calendar
                        </button>
                        <button class="btn btn-primary" onclick="showApplyLeaveModal()">
                            <i class="fas fa-plus me-1"></i>Apply for Leave
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                <div class="card border-0 shadow-sm stats-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-primary bg-opacity-10 rounded-3 p-3 me-3">
                                <i class="fas fa-file-alt text-primary fs-2"></i>
                            </div>
                            <div>
                                <h3 class="fw-bold text-primary mb-0"><?= $stats['total_applications'] ?></h3>
                                <p class="text-muted mb-0 small">Total Applications</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                <div class="card border-0 shadow-sm stats-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-warning bg-opacity-10 rounded-3 p-3 me-3">
                                <i class="fas fa-clock text-warning fs-2"></i>
                            </div>
                            <div>
                                <h3 class="fw-bold text-warning mb-0"><?= $stats['pending_applications'] ?></h3>
                                <p class="text-muted mb-0 small">Pending Approval</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                <div class="card border-0 shadow-sm stats-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-success bg-opacity-10 rounded-3 p-3 me-3">
                                <i class="fas fa-check-circle text-success fs-2"></i>
                            </div>
                            <div>
                                <h3 class="fw-bold text-success mb-0"><?= $stats['approved_applications'] ?></h3>
                                <p class="text-muted mb-0 small">Approved</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                <div class="card border-0 shadow-sm stats-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-danger bg-opacity-10 rounded-3 p-3 me-3">
                                <i class="fas fa-times-circle text-danger fs-2"></i>
                            </div>
                            <div>
                                <h3 class="fw-bold text-danger mb-0"><?= $stats['rejected_applications'] ?></h3>
                                <p class="text-muted mb-0 small">Rejected</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Leave Applications Table -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-list text-secondary me-2"></i>
                                Leave Applications
                            </h5>
                            <div class="d-flex gap-2">
                                <select class="form-select form-select-sm" id="statusFilter" onchange="filterApplications()">
                                    <option value="">All Status</option>
                                    <option value="pending">Pending</option>
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                                <button class="btn btn-outline-primary btn-sm" onclick="exportLeaveData()">
                                    <i class="fas fa-download me-1"></i>Export
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="leaveApplicationsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Employee</th>
                                        <th>Leave Type</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Days</th>
                                        <th>Status</th>
                                        <th>Applied Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($leaveApplications as $application): ?>
                                        <tr data-status="<?= $application['status'] ?>">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="employee-avatar-small me-2">
                                                        <?= strtoupper(substr($application['first_name'], 0, 1) . substr($application['last_name'], 0, 1)) ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-medium"><?= htmlspecialchars($application['first_name'] . ' ' . $application['last_name']) ?></div>
                                                        <small class="text-muted"><?= htmlspecialchars($application['employee_id']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-info bg-opacity-10 text-info">
                                                    <?= htmlspecialchars($application['leave_type_name']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('M j, Y', strtotime($application['start_date'])) ?></td>
                                            <td><?= date('M j, Y', strtotime($application['end_date'])) ?></td>
                                            <td>
                                                <span class="fw-medium"><?= $application['days_requested'] ?></span>
                                                <small class="text-muted">days</small>
                                            </td>
                                            <td>
                                                <?php
                                                $statusClasses = [
                                                    'pending' => 'warning',
                                                    'approved' => 'success',
                                                    'rejected' => 'danger',
                                                    'cancelled' => 'secondary'
                                                ];
                                                $statusClass = $statusClasses[$application['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?= $statusClass ?>">
                                                    <?= ucfirst($application['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div><?= date('M j, Y', strtotime($application['applied_at'])) ?></div>
                                                <small class="text-muted"><?= date('g:i A', strtotime($application['applied_at'])) ?></small>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-outline-primary btn-sm" 
                                                            onclick="viewLeaveDetails(<?= $application['id'] ?>)"
                                                            title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if (($currentUserRole === 'hr' || $currentUserRole === 'admin') && $application['status'] === 'pending'): ?>
                                                        <button class="btn btn-outline-success btn-sm" 
                                                                onclick="approveLeave(<?= $application['id'] ?>, 'approve')"
                                                                title="Approve">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                        <button class="btn btn-outline-danger btn-sm" 
                                                                onclick="approveLeave(<?= $application['id'] ?>, 'reject')"
                                                                title="Reject">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
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

<!-- Apply Leave Modal -->
<div class="modal fade" id="applyLeaveModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i>Apply for Leave
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="applyLeaveForm">
                    <div class="row">
                        <?php if ($currentUserRole === 'hr' || $currentUserRole === 'admin'): ?>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Employee <span class="text-danger">*</span></label>
                                <select class="form-select" name="employee_id" required>
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?= $emp['id'] ?>">
                                            <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'] . ' (' . $emp['employee_id'] . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="employee_id" value="<?= $currentUserId ?>">
                        <?php endif; ?>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Leave Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="leave_type_id" required onchange="updateLeaveBalance()">
                                <option value="">Select Leave Type</option>
                                <?php foreach ($leaveTypes as $type): ?>
                                    <option value="<?= $type['id'] ?>" data-days="<?= $type['days_allowed'] ?>">
                                        <?= htmlspecialchars($type['name']) ?> (<?= $type['days_allowed'] ?> days)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="start_date" required onchange="calculateDays()">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="end_date" required onchange="calculateDays()">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Total Days</label>
                            <input type="number" class="form-control" id="totalDays" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div id="leaveBalanceInfo" class="mt-4">
                                <!-- Leave balance will be shown here -->
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="reason" rows="3" required 
                                  placeholder="Please provide reason for leave..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitLeaveApplication()">
                    <i class="fas fa-paper-plane me-1"></i>Submit Application
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.main-content {
    margin-left: 250px;
    padding: 2rem;
    min-height: 100vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        padding: 1rem;
    }
}

.stats-card {
    transition: all 0.3s ease;
    border-radius: 12px;
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.95);
}

.stats-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.employee-avatar-small {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(45deg, #007bff, #6610f2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.75rem;
    font-weight: bold;
}

.card {
    border-radius: 12px;
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.95);
}

.table th {
    border-top: none;
    font-weight: 600;
    color: #6c757d;
    font-size: 0.875rem;
}
</style>

<script>
// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Set minimum date to today for leave applications
    const today = new Date().toISOString().split('T')[0];
    document.querySelector('input[name="start_date"]').min = today;
    document.querySelector('input[name="end_date"]').min = today;
});

// Show apply leave modal
function showApplyLeaveModal() {
    const modal = new bootstrap.Modal(document.getElementById('applyLeaveModal'));
    modal.show();
}

// Calculate days between start and end date
function calculateDays() {
    const startDate = document.querySelector('input[name="start_date"]').value;
    const endDate = document.querySelector('input[name="end_date"]').value;
    
    if (startDate && endDate) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        const days = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
        
        if (days > 0) {
            document.getElementById('totalDays').value = days;
        } else {
            document.getElementById('totalDays').value = '';
            alert('End date must be after start date');
        }
    }
}

// Submit leave application
function submitLeaveApplication() {
    const form = document.getElementById('applyLeaveForm');
    const formData = new FormData(form);
    formData.append('action', 'apply_leave');
    
    // Validate form
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Leave application submitted successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Network error: ' + error.message);
    });
}

// Approve/Reject leave
function approveLeave(applicationId, action) {
    const comments = prompt(`Please enter comments for ${action}ing this leave application:`);
    
    if (comments !== null) {
        const formData = new FormData();
        formData.append('action', 'approve_leave');
        formData.append('application_id', applicationId);
        formData.append('approve_action', action);
        formData.append('comments', comments);
        
        fetch(window.location.href, {
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
            alert('Network error: ' + error.message);
        });
    }
}

// Filter applications by status
function filterApplications() {
    const filter = document.getElementById('statusFilter').value;
    const rows = document.querySelectorAll('#leaveApplicationsTable tbody tr');
    
    rows.forEach(row => {
        const status = row.dataset.status;
        if (filter === '' || status === filter) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Other functions
function viewLeaveDetails(applicationId) {
    alert('Leave details view will be implemented next!');
}

function showLeaveCalendar() {
    alert('Leave calendar view will be implemented next!');
}

function exportLeaveData() {
    alert('Export functionality will be implemented next!');
}
</script>

<?php require_once '../layouts/footer.php'; ?>
