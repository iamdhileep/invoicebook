<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

include '../db.php';
$page_title = 'Leave Management';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'apply_leave':
            $employee_id = intval($_POST['employee_id']);
            $leave_type = mysqli_real_escape_string($conn, $_POST['leave_type']);
            $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
            $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
            $reason = mysqli_real_escape_string($conn, $_POST['reason']);
            
            // Calculate days
            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            $days = $start->diff($end)->days + 1;
            
            $query = "INSERT INTO hr_leave_requests (employee_id, leave_type, start_date, end_date, days_requested, reason) 
                      VALUES ($employee_id, '$leave_type', '$start_date', '$end_date', $days, '$reason')";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Leave application submitted successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;

        case 'approve_leave':
            $id = intval($_POST['id']);
            $status = mysqli_real_escape_string($conn, $_POST['status']);
            $comments = mysqli_real_escape_string($conn, $_POST['comments']);
            $approved_by = $_SESSION['user_id'] ?? 1; // Default admin ID
            
            $query = "UPDATE hr_leave_requests SET 
                      status = '$status', 
                      approved_by = $approved_by, 
                      approved_at = NOW(), 
                      comments = '$comments' 
                      WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Leave request updated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;

        case 'cancel_leave':
            $id = intval($_POST['id']);
            $query = "UPDATE hr_leave_requests SET status = 'rejected', comments = 'Cancelled by system' WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Leave request cancelled successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;
    }
}

// Get leave statistics
$totalLeaves = 0;
$pendingLeaves = 0;
$approvedLeaves = 0;
$rejectedLeaves = 0;

$statsQuery = mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_leaves,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_leaves,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_leaves,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_leaves
    FROM hr_leave_requests
");
if ($statsQuery && $row = mysqli_fetch_assoc($statsQuery)) {
    $totalLeaves = $row['total_leaves'];
    $pendingLeaves = $row['pending_leaves'];
    $approvedLeaves = $row['approved_leaves'];
    $rejectedLeaves = $row['rejected_leaves'];
}

// Handle filtering
$status = $_GET['status'] ?? '';
$employee = $_GET['employee'] ?? '';
$leave_type = $_GET['leave_type'] ?? '';

$where = "WHERE 1=1";
if ($status) {
    $where .= " AND lr.status = '" . mysqli_real_escape_string($conn, $status) . "'";
}
if ($employee) {
    $where .= " AND (e.first_name LIKE '%" . mysqli_real_escape_string($conn, $employee) . "%' 
                OR e.last_name LIKE '%" . mysqli_real_escape_string($conn, $employee) . "%')";
}
if ($leave_type) {
    $where .= " AND lr.leave_type = '" . mysqli_real_escape_string($conn, $leave_type) . "'";
}

// Get leave requests with employee details
$leaves = mysqli_query($conn, "
    SELECT lr.*, 
           CONCAT(e.first_name, ' ', e.last_name) as employee_name,
           e.employee_id as emp_id,
           CONCAT(a.first_name, ' ', a.last_name) as approved_by_name
    FROM hr_leave_requests lr 
    JOIN hr_employees e ON lr.employee_id = e.id 
    LEFT JOIN hr_employees a ON lr.approved_by = a.id 
    $where 
    ORDER BY lr.applied_at DESC
");

// Get employees for dropdown
$employees = mysqli_query($conn, "SELECT id, first_name, last_name, employee_id FROM hr_employees WHERE status = 'active' ORDER BY first_name");

include '../layouts/header.php';
include '../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">🏖️ Leave Management</h1>
                <p class="text-muted">Manage employee leave requests and approvals</p>
            </div>
            <div>
                <a href="index.php" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-left"></i> Back to HRMS
                </a>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#applyLeaveModal">
                    <i class="bi bi-plus-circle"></i> Apply Leave
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-calendar-event fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $totalLeaves ?></h3>
                        <small class="opacity-75">Total Requests</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-hourglass-split fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $pendingLeaves ?></h3>
                        <small class="opacity-75">Pending</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-check-circle fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $approvedLeaves ?></h3>
                        <small class="opacity-75">Approved</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-x-circle fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $rejectedLeaves ?></h3>
                        <small class="opacity-75">Rejected</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Search Employee</label>
                        <input type="text" name="employee" class="form-control" placeholder="Employee name..." value="<?= htmlspecialchars($employee) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Leave Type</label>
                        <select name="leave_type" class="form-select">
                            <option value="">All Types</option>
                            <option value="sick" <?= $leave_type === 'sick' ? 'selected' : '' ?>>Sick Leave</option>
                            <option value="vacation" <?= $leave_type === 'vacation' ? 'selected' : '' ?>>Vacation</option>
                            <option value="personal" <?= $leave_type === 'personal' ? 'selected' : '' ?>>Personal</option>
                            <option value="maternity" <?= $leave_type === 'maternity' ? 'selected' : '' ?>>Maternity</option>
                            <option value="paternity" <?= $leave_type === 'paternity' ? 'selected' : '' ?>>Paternity</option>
                            <option value="emergency" <?= $leave_type === 'emergency' ? 'selected' : '' ?>>Emergency</option>
                        </select>
                    </div>
                    <div class="col-md-5 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="bi bi-search"></i> Search
                        </button>
                        <a href="?" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Leave Requests Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0 text-dark">
                    <i class="bi bi-table me-2"></i>Leave Requests
                    <span class="badge bg-primary ms-2"><?= $leaves ? mysqli_num_rows($leaves) : 0 ?> requests</span>
                </h6>
            </div>
            <div class="card-body p-0">
                <?php if ($leaves && mysqli_num_rows($leaves) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Employee</th>
                                    <th>Leave Type</th>
                                    <th>Duration</th>
                                    <th>Days</th>
                                    <th>Applied Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($leave = mysqli_fetch_assoc($leaves)): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong class="text-primary"><?= htmlspecialchars($leave['employee_name']) ?></strong>
                                                <br><small class="text-muted">ID: <?= htmlspecialchars($leave['emp_id']) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?= ucfirst($leave['leave_type']) ?></span>
                                        </td>
                                        <td>
                                            <strong><?= date('M d, Y', strtotime($leave['start_date'])) ?></strong>
                                            <br><small class="text-muted">to <?= date('M d, Y', strtotime($leave['end_date'])) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?= $leave['days_requested'] ?> days</span>
                                        </td>
                                        <td>
                                            <?= date('M d, Y', strtotime($leave['applied_at'])) ?>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = match($leave['status']) {
                                                'pending' => 'bg-warning',
                                                'approved' => 'bg-success',
                                                'rejected' => 'bg-danger',
                                                default => 'bg-secondary'
                                            };
                                            ?>
                                            <span class="badge <?= $statusClass ?>"><?= ucfirst($leave['status']) ?></span>
                                            <?php if ($leave['approved_by_name']): ?>
                                                <br><small class="text-muted">by <?= htmlspecialchars($leave['approved_by_name']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary btn-sm" onclick="viewLeave(<?= $leave['id'] ?>)" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <?php if ($leave['status'] === 'pending'): ?>
                                                    <button class="btn btn-outline-success btn-sm" onclick="approveLeave(<?= $leave['id'] ?>, 'approved')" title="Approve">
                                                        <i class="bi bi-check"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger btn-sm" onclick="approveLeave(<?= $leave['id'] ?>, 'rejected')" title="Reject">
                                                        <i class="bi bi-x"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-calendar-x text-muted" style="font-size: 3rem;"></i>
                        <h5 class="text-muted mt-3">No leave requests found</h5>
                        <p class="text-muted">Start by applying for a leave request</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#applyLeaveModal">
                            <i class="bi bi-plus-circle me-1"></i>Apply Leave
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Apply Leave Modal -->
<div class="modal fade" id="applyLeaveModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle text-primary me-2"></i>Apply for Leave
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="applyLeaveForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Employee *</label>
                            <select name="employee_id" class="form-select" required>
                                <option value="">Select Employee</option>
                                <?php if ($employees): while ($emp = mysqli_fetch_assoc($employees)): ?>
                                    <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?> - <?= $emp['employee_id'] ?></option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Leave Type *</label>
                            <select name="leave_type" class="form-select" required>
                                <option value="">Select Leave Type</option>
                                <option value="sick">Sick Leave</option>
                                <option value="vacation">Vacation</option>
                                <option value="personal">Personal Leave</option>
                                <option value="maternity">Maternity Leave</option>
                                <option value="paternity">Paternity Leave</option>
                                <option value="emergency">Emergency Leave</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Start Date *</label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Date *</label>
                            <input type="date" name="end_date" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Reason</label>
                            <textarea name="reason" class="form-control" rows="3" placeholder="Please provide reason for leave..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Leave Details Modal -->
<div class="modal fade" id="leaveDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-calendar-event text-info me-2"></i>Leave Request Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="leaveDetailsContent">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Approval Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-check-circle text-success me-2"></i>Leave Request Approval
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="approvalForm">
                <input type="hidden" name="id" id="approvalLeaveId">
                <input type="hidden" name="status" id="approvalStatus">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <div id="approvalEmployeeName"></div>
                        <div id="approvalLeaveDates"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Comments</label>
                        <textarea name="comments" class="form-control" rows="3" placeholder="Add your comments about this leave request..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" id="approvalSubmitBtn">
                        <i class="bi bi-check-lg me-1"></i>Submit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Form submission handlers
document.getElementById('applyLeaveForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'apply_leave');
    
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
        alert('An error occurred while submitting leave application');
    });
});

document.getElementById('approvalForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'approve_leave');
    
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
        alert('An error occurred while processing approval');
    });
});

function viewLeave(id) {
    // This would fetch and display detailed leave information
    document.getElementById('leaveDetailsContent').innerHTML = '<div class="text-center p-3">Loading leave details...</div>';
    new bootstrap.Modal(document.getElementById('leaveDetailsModal')).show();
}

function approveLeave(id, status) {
    document.getElementById('approvalLeaveId').value = id;
    document.getElementById('approvalStatus').value = status;
    
    const submitBtn = document.getElementById('approvalSubmitBtn');
    if (status === 'approved') {
        submitBtn.className = 'btn btn-success';
        submitBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Approve';
        document.querySelector('#approvalModal .modal-title').innerHTML = '<i class="bi bi-check-circle text-success me-2"></i>Approve Leave Request';
    } else {
        submitBtn.className = 'btn btn-danger';
        submitBtn.innerHTML = '<i class="bi bi-x-lg me-1"></i>Reject';
        document.querySelector('#approvalModal .modal-title').innerHTML = '<i class="bi bi-x-circle text-danger me-2"></i>Reject Leave Request';
    }
    
    new bootstrap.Modal(document.getElementById('approvalModal')).show();
}

// Auto-calculate days when dates change
document.addEventListener('DOMContentLoaded', function() {
    const startDateInput = document.querySelector('input[name="start_date"]');
    const endDateInput = document.querySelector('input[name="end_date"]');
    
    function calculateDays() {
        if (startDateInput.value && endDateInput.value) {
            const start = new Date(startDateInput.value);
            const end = new Date(endDateInput.value);
            const diffTime = Math.abs(end - start);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
            
            if (diffDays > 0) {
                // Show calculated days somewhere if needed
                console.log('Days calculated:', diffDays);
            }
        }
    }
    
    startDateInput.addEventListener('change', calculateDays);
    endDateInput.addEventListener('change', calculateDays);
});
</script>

<style>
.stats-card {
    transition: transform 0.2s;
}
.stats-card:hover {
    transform: translateY(-2px);
}
</style>

<?php include '../layouts/footer.php'; ?>
