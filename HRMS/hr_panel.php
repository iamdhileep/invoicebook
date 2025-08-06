<?php
$page_title = "HR Management Dashboard";
require_once 'includes/hrms_config.php';

// Authentication and authorization
if (!HRMSHelper::isLoggedIn()) {
    header('Location: ../hrms_portal.php?redirect=HRMS/hr_panel.php');
    exit;
}

if (!HRMSHelper::hasPermission('all') && !HRMSHelper::hasPermission('hr_view')) {
    header('Location: ../hrms_portal.php?error=access_denied');
    exit;
}

require_once '../layouts/header.php';
require_once '../layouts/sidebar.php';

// Get current user information
$currentUserId = HRMSHelper::getCurrentUserId();
$currentUserRole = HRMSHelper::getCurrentUserRole();

// Initialize dashboard statistics
$hrStats = [
    'total_employees' => 0,
    'active_employees' => 0,
    'departments' => 0,
    'present_today' => 0,
    'on_leave_today' => 0,
    'pending_applications' => 0,
    'new_hires_month' => 0,
    'attendance_rate' => 0
];

// Fetch comprehensive HR statistics
try {
    // Total employees count
    $result = HRMSHelper::safeQuery("SELECT COUNT(*) as total FROM hr_employees");
    if ($result && $row = $result->fetch_assoc()) {
        $hrStats['total_employees'] = (int)$row['total'];
    }

    // Active employees count
    $result = HRMSHelper::safeQuery("SELECT COUNT(*) as active FROM hr_employees WHERE is_active = 1");
    if ($result && $row = $result->fetch_assoc()) {
        $hrStats['active_employees'] = (int)$row['active'];
    }

    // Active departments count
    $result = HRMSHelper::safeQuery("SELECT COUNT(*) as total FROM hr_departments WHERE status = 'active'");
    if ($result && $row = $result->fetch_assoc()) {
        $hrStats['departments'] = (int)$row['total'];
    }

    // Today's attendance
    $today = date('Y-m-d');
    $result = HRMSHelper::safeQuery("SELECT COUNT(*) as present FROM hr_attendance WHERE attendance_date = '$today' AND status IN ('present', 'late')");
    if ($result && $row = $result->fetch_assoc()) {
        $hrStats['present_today'] = (int)$row['present'];
    }

    // Employees on leave today
    $result = HRMSHelper::safeQuery("
        SELECT COUNT(*) as on_leave 
        FROM hr_leave_applications 
        WHERE status = 'approved' 
        AND '$today' BETWEEN start_date AND end_date
    ");
    if ($result && $row = $result->fetch_assoc()) {
        $hrStats['on_leave_today'] = (int)$row['on_leave'];
    }

    // Pending leave applications
    $result = HRMSHelper::safeQuery("SELECT COUNT(*) as pending FROM hr_leave_applications WHERE status = 'pending'");
    if ($result && $row = $result->fetch_assoc()) {
        $hrStats['pending_applications'] = (int)$row['pending'];
    }

    // New hires this month
    $currentMonth = date('Y-m');
    $result = HRMSHelper::safeQuery("SELECT COUNT(*) as new_hires FROM hr_employees WHERE DATE_FORMAT(created_at, '%Y-%m') = '$currentMonth'");
    if ($result && $row = $result->fetch_assoc()) {
        $hrStats['new_hires_month'] = (int)$row['new_hires'];
    }

    // Calculate attendance rate
    if ($hrStats['active_employees'] > 0) {
        $hrStats['attendance_rate'] = round(($hrStats['present_today'] / $hrStats['active_employees']) * 100, 1);
    }

} catch (Exception $e) {
    error_log("HR Dashboard stats error: " . $e->getMessage());
}

// Recent HR activities
$recentActivities = [];
try {
    $result = HRMSHelper::safeQuery("
        (SELECT 'employee_added' as activity_type, 
                CONCAT('New employee ', first_name, ' ', last_name, ' was added') as description,
                created_at as activity_date,
                'success' as status
         FROM hr_employees 
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))
        UNION ALL
        (SELECT 'leave_applied' as activity_type,
                CONCAT(e.first_name, ' ', e.last_name, ' applied for ', la.leave_type, ' leave') as description,
                la.created_at as activity_date,
                'info' as status
         FROM hr_leave_applications la
         LEFT JOIN hr_employees e ON la.employee_id = e.id
         WHERE la.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))
        ORDER BY activity_date DESC
        LIMIT 10
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recentActivities[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Recent activities error: " . $e->getMessage());
}

// Pending leave applications for approval
$pendingLeaves = [];
try {
    $result = HRMSHelper::safeQuery("
        SELECT la.*, e.first_name, e.last_name, e.employee_id, e.department_id,
               d.name as department_name
        FROM hr_leave_applications la
        LEFT JOIN hr_employees e ON la.employee_id = e.id
        LEFT JOIN hr_departments d ON e.department_id = d.id
        WHERE la.status = 'pending'
        ORDER BY la.created_at DESC
        LIMIT 8
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $pendingLeaves[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Pending leaves error: " . $e->getMessage());
}

// Department-wise employee distribution
$departmentStats = [];
try {
    $result = HRMSHelper::safeQuery("
        SELECT d.name as department_name, COUNT(e.id) as employee_count,
               d.id as department_id
        FROM hr_departments d
        LEFT JOIN hr_employees e ON d.id = e.department_id AND e.is_active = 1
        WHERE d.status = 'active'
        GROUP BY d.id, d.name
        ORDER BY employee_count DESC
        LIMIT 6
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $departmentStats[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Department stats error: " . $e->getMessage());
}
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h2 mb-1">
                            <i class="fas fa-users-cog text-primary me-2"></i>
                            HR Management Dashboard
                        </h1>
                        <p class="text-muted mb-0">Comprehensive human resources management and analytics</p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary fs-6 px-3 py-2">
                            <i class="fas fa-shield-alt me-1"></i>HR Admin
                        </span>
                        <span class="text-muted small"><?= date('l, F j, Y') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Key Metrics Cards -->
        <div class="row mb-4">
            <!-- Total Employees -->
            <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-primary bg-opacity-10 rounded-3 p-3 me-3">
                                <i class="fas fa-users text-primary fs-3"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h3 class="fw-bold text-primary mb-0"><?= number_format($hrStats['total_employees']) ?></h3>
                                <p class="text-muted mb-1 small">Total Employees</p>
                                <small class="text-success">
                                    <i class="fas fa-arrow-up"></i> <?= $hrStats['active_employees'] ?> Active
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Present Today -->
            <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-success bg-opacity-10 rounded-3 p-3 me-3">
                                <i class="fas fa-user-check text-success fs-3"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h3 class="fw-bold text-success mb-0"><?= number_format($hrStats['present_today']) ?></h3>
                                <p class="text-muted mb-1 small">Present Today</p>
                                <small class="text-info">
                                    <i class="fas fa-percentage"></i> <?= $hrStats['attendance_rate'] ?>% Rate
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- On Leave Today -->
            <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-warning bg-opacity-10 rounded-3 p-3 me-3">
                                <i class="fas fa-calendar-times text-warning fs-3"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h3 class="fw-bold text-warning mb-0"><?= number_format($hrStats['on_leave_today']) ?></h3>
                                <p class="text-muted mb-1 small">On Leave Today</p>
                                <small class="text-danger">
                                    <i class="fas fa-clock"></i> <?= $hrStats['pending_applications'] ?> Pending
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Departments -->
            <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-info bg-opacity-10 rounded-3 p-3 me-3">
                                <i class="fas fa-building text-info fs-3"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h3 class="fw-bold text-info mb-0"><?= number_format($hrStats['departments']) ?></h3>
                                <p class="text-muted mb-1 small">Active Departments</p>
                                <small class="text-primary">
                                    <i class="fas fa-plus"></i> <?= $hrStats['new_hires_month'] ?> New Hires
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions Panel -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom-0 py-3">
                        <h5 class="card-title mb-0 d-flex align-items-center">
                            <i class="fas fa-bolt text-primary me-2"></i>
                            Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                <a href="add_employee.php" class="btn btn-outline-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3 text-decoration-none position-relative overflow-hidden">
                                    <i class="fas fa-user-plus fs-2 mb-2"></i>
                                    <span class="fw-medium">Add Employee</span>
                                    <small class="text-muted">Register new staff</small>
                                </a>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                <a href="manage_departments.php" class="btn btn-outline-success w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3 text-decoration-none">
                                    <i class="fas fa-building fs-2 mb-2"></i>
                                    <span class="fw-medium">Departments</span>
                                    <small class="text-muted">Manage structure</small>
                                </a>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                <a href="attendance_overview.php" class="btn btn-outline-info w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3 text-decoration-none">
                                    <i class="fas fa-chart-bar fs-2 mb-2"></i>
                                    <span class="fw-medium">Attendance</span>
                                    <small class="text-muted">View reports</small>
                                </a>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                <a href="payroll_dashboard.php" class="btn btn-outline-warning w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3 text-decoration-none">
                                    <i class="fas fa-money-bill-wave fs-2 mb-2"></i>
                                    <span class="fw-medium">Payroll</span>
                                    <small class="text-muted">Salary management</small>
                                </a>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                <a href="leave_approvals.php" class="btn btn-outline-danger w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3 text-decoration-none">
                                    <i class="fas fa-calendar-check fs-2 mb-2"></i>
                                    <span class="fw-medium">Leave Approvals</span>
                                    <small class="text-muted">Review requests</small>
                                </a>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                <a href="hr_analytics.php" class="btn btn-outline-secondary w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3 text-decoration-none">
                                    <i class="fas fa-chart-line fs-2 mb-2"></i>
                                    <span class="fw-medium">Analytics</span>
                                    <small class="text-muted">HR insights</small>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="row">
            <!-- Recent Activities -->
            <div class="col-lg-8 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom-0 py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-history text-primary me-2"></i>
                                Recent HR Activities
                            </h5>
                            <a href="activity_log.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recentActivities)): ?>
                            <div class="activity-timeline">
                                <?php foreach ($recentActivities as $index => $activity): ?>
                                    <div class="timeline-item <?= $index === count($recentActivities) - 1 ? 'last' : '' ?>">
                                        <div class="timeline-marker bg-<?= $activity['status'] ?>"></div>
                                        <div class="timeline-content">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <p class="mb-1"><?= htmlspecialchars($activity['description']) ?></p>
                                                    <small class="text-muted">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?= date('M j, Y g:i A', strtotime($activity['activity_date'])) ?>
                                                    </small>
                                                </div>
                                                <span class="badge bg-<?= $activity['status'] ?> bg-opacity-10 text-<?= $activity['status'] ?>">
                                                    <?= ucfirst($activity['activity_type']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-history text-muted fs-1 mb-3 opacity-50"></i>
                                <h6 class="text-muted">No recent activities</h6>
                                <p class="text-muted small">HR activities will appear here</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Sidebar -->
            <div class="col-lg-4">
                <!-- Pending Leave Approvals -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom-0 py-3">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-clock text-warning me-2"></i>
                            Pending Leave Approvals
                            <?php if (count($pendingLeaves) > 0): ?>
                                <span class="badge bg-warning ms-2"><?= count($pendingLeaves) ?></span>
                            <?php endif; ?>
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($pendingLeaves)): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach (array_slice($pendingLeaves, 0, 5) as $leave): ?>
                                    <div class="list-group-item border-0 py-3">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?= htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']) ?></h6>
                                                <p class="mb-1 small text-muted">
                                                    <i class="fas fa-briefcase me-1"></i>
                                                    <?= htmlspecialchars($leave['department_name'] ?? 'Unknown Dept') ?>
                                                </p>
                                                <p class="mb-1 small">
                                                    <strong><?= htmlspecialchars($leave['leave_type']) ?></strong>
                                                </p>
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    <?= date('M j', strtotime($leave['start_date'])) ?> - <?= date('M j, Y', strtotime($leave['end_date'])) ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-warning text-dark mb-2">Pending</span>
                                                <div class="btn-group-vertical" role="group">
                                                    <button class="btn btn-success btn-sm" onclick="approveLeave(<?= $leave['id'] ?>)" title="Approve">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button class="btn btn-danger btn-sm" onclick="rejectLeave(<?= $leave['id'] ?>)" title="Reject">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($pendingLeaves) > 5): ?>
                                <div class="card-footer bg-light">
                                    <a href="leave_approvals.php" class="btn btn-outline-primary btn-sm w-100">
                                        View All <?= count($pendingLeaves) ?> Applications
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-check-circle text-success fs-1 mb-3 opacity-50"></i>
                                <p class="text-muted small">No pending approvals</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Department Overview -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom-0 py-3">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-sitemap text-info me-2"></i>
                            Department Overview
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($departmentStats)): ?>
                            <?php foreach ($departmentStats as $dept): ?>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <h6 class="mb-0"><?= htmlspecialchars($dept['department_name']) ?></h6>
                                        <small class="text-muted"><?= $dept['employee_count'] ?> employees</small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-primary"><?= $dept['employee_count'] ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="fas fa-building text-muted fs-2 mb-2 opacity-50"></i>
                                <p class="text-muted small">No departments found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Approval Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Leave Application Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="approvalContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmAction">Confirm</button>
            </div>
        </div>
    </div>
</div>

<style>
.main-content {
    margin-left: 250px;
    padding: 2rem;
    min-height: 100vh;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        padding: 1rem;
    }
}

.card {
    transition: all 0.3s ease;
    border-radius: 12px;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.btn:hover {
    transform: translateY(-1px);
}

.activity-timeline {
    position: relative;
}

.timeline-item {
    position: relative;
    padding-left: 30px;
    margin-bottom: 20px;
}

.timeline-item:not(.last)::before {
    content: '';
    position: absolute;
    left: 8px;
    top: 20px;
    bottom: -20px;
    width: 2px;
    background: #e9ecef;
}

.timeline-marker {
    position: absolute;
    left: 0;
    top: 5px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 3px solid white;
}

.timeline-content {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 12px;
}

.list-group-item:hover {
    background-color: #f8f9fa;
}

.btn-group-vertical .btn {
    border-radius: 4px;
    margin-bottom: 2px;
}

.btn-group-vertical .btn:last-child {
    margin-bottom: 0;
}
</style>

<script>
function approveLeave(leaveId) {
    document.getElementById('approvalContent').innerHTML = `
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>
            Are you sure you want to approve this leave application?
        </div>
        <div class="mb-3">
            <label class="form-label">Approval Comments (Optional)</label>
            <textarea class="form-control" id="approvalComments" rows="2" placeholder="Add any comments..."></textarea>
        </div>
    `;
    
    document.getElementById('confirmAction').onclick = function() {
        // Here you would make an AJAX call to approve the leave
        showToast('Leave application approved successfully!', 'success');
        bootstrap.Modal.getInstance(document.getElementById('approvalModal')).hide();
        setTimeout(() => location.reload(), 1000);
    };
    
    new bootstrap.Modal(document.getElementById('approvalModal')).show();
}

function rejectLeave(leaveId) {
    document.getElementById('approvalContent').innerHTML = `
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Are you sure you want to reject this leave application?
        </div>
        <div class="mb-3">
            <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
            <textarea class="form-control" id="rejectionReason" rows="3" placeholder="Please provide a reason for rejection..." required></textarea>
        </div>
    `;
    
    document.getElementById('confirmAction').onclick = function() {
        const reason = document.getElementById('rejectionReason').value.trim();
        if (!reason) {
            alert('Please provide a reason for rejection');
            return;
        }
        
        // Here you would make an AJAX call to reject the leave
        showToast('Leave application rejected', 'warning');
        bootstrap.Modal.getInstance(document.getElementById('approvalModal')).hide();
        setTimeout(() => location.reload(), 1000);
    };
    
    new bootstrap.Modal(document.getElementById('approvalModal')).show();
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} position-fixed top-0 end-0 m-3`;
    toast.style.zIndex = '9999';
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check' : type === 'warning' ? 'exclamation' : 'info'}-circle me-2"></i>
        ${message}
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

// Auto-refresh data every 5 minutes
setInterval(() => {
    location.reload();
}, 300000);
</script>

<?php require_once '../layouts/footer.php'; ?>
