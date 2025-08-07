<?php
$page_title = "Leave Management";

// Include authentication and database
require_once '../auth_check.php';
require_once '../db.php';

// Include layouts
require_once 'hrms_header_simple.php';
require_once 'hrms_sidebar_simple.php';

// Include HRMS UI fix
$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'] ?? 'employee';

// Debug: Check if tables exist
$debug_mode = isset($_GET['debug']) ? true : false;
if ($debug_mode) {
    echo "<div style='background: #f8f9fa; padding: 1rem; margin: 1rem; border-radius: 5px;'>";
    echo "<h4>Debug Information:</h4>";
    
    // Check table existence
    $result = $conn->query("SHOW TABLES LIKE 'hr_leave_applications'");
    if ($result && $result->num_rows > 0) {
        echo "<p>✅ hr_leave_applications table exists</p>";
    } else {
        echo "<p>❌ hr_leave_applications table does NOT exist - <a href='setup_leave_table.php'>Create it</a></p>";
    }
    
    echo "<p>Current User ID: " . $currentUserId . "</p>";
    echo "<p>Current User Role: " . $currentUserRole . "</p>";
    echo "</div>";
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // Check if table exists before processing
    $tableExists = false;
    try {
        $result = $conn->query("SHOW TABLES LIKE 'hr_leave_applications'");
        $tableExists = ($result && $result->num_rows > 0);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
    
    if (!$tableExists) {
        echo json_encode(['success' => false, 'message' => 'Leave applications table not found. Please run setup first.']);
        exit;
    }
    
    switch ($_POST['action']) {
        case 'apply_leave':
            $employeeId = $_POST['employee_id'] ?? 0;
            $leaveTypeId = $_POST['leave_type_id'] ?? 1; // Default to annual leave
            $startDate = $_POST['start_date'] ?? '';
            $endDate = $_POST['end_date'] ?? '';
            $reason = $_POST['reason'] ?? '';
            
            // Calculate days
            $start = new DateTime($startDate);
            $end = new DateTime($endDate);
            $days = $start->diff($end)->days + 1;
            
            try {
                $result = $conn->query("
                    INSERT INTO hr_leave_applications 
                    (employee_id, leave_type, start_date, end_date, days_requested, reason, status, applied_date) 
                    VALUES ('$employeeId', '$leaveTypeId', '$startDate', '$endDate', '$days', '$reason', 'pending', NOW())
                ");
                
                if ($result) {
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
            $action = $_POST['decision'] ?? 'approve';
            $comments = $_POST['comments'] ?? '';
            
            $status = $action === 'approve' ? 'approved' : 'rejected';
            
            try {
                $result = $conn->query("
                    UPDATE hr_leave_applications 
                    SET status = '$status', approved_by = '$currentUserId', approval_date = NOW(), comments = '$comments'
                    WHERE id = '$applicationId'
                ");
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => "Leave request $status successfully"]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update leave request']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
    }
}

// Initialize statistics with error handling
$leaveStats = [
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'total_this_month' => 0
];

// Check if table exists before querying
$tableExists = false;
try {
    $result = $conn->query("SHOW TABLES LIKE 'hr_leave_applications'");
    $tableExists = ($result && $result->num_rows > 0);
} catch (Exception $e) {
    error_log("Table check error: " . $e->getMessage());
}

if ($tableExists) {
    try {
        // Get leave statistics
        $result = $conn->query("
            SELECT 
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN MONTH(applied_date) = MONTH(CURDATE()) AND YEAR(applied_date) = YEAR(CURDATE()) THEN 1 ELSE 0 END) as total_this_month
            FROM hr_leave_applications
        ");
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $leaveStats = [
                'pending' => $row['pending'] ?? 0,
                'approved' => $row['approved'] ?? 0,
                'rejected' => $row['rejected'] ?? 0,
                'total_this_month' => $row['total_this_month'] ?? 0
            ];
        }
    } catch (Exception $e) {
        error_log("Leave Stats Error: " . $e->getMessage());
        // Keep default values if query fails
    }
}

// Get employees for dropdown
$employees = [];
try {
    $result = $conn->query("SELECT id, first_name, last_name, employee_id FROM hr_employees WHERE status = 'active' ORDER BY first_name, last_name");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $employees[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Employees fetch error: " . $e->getMessage());
}

// Get recent leave applications
$recentApplications = [];
if ($tableExists) {
    try {
        $result = $conn->query("
            SELECT la.*, e.first_name, e.last_name, e.employee_id as emp_code
            FROM hr_leave_applications la
            JOIN hr_employees e ON la.employee_id = e.id
            ORDER BY la.applied_date DESC
            LIMIT 10
        ");
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $recentApplications[] = $row;
            }
        }
    } catch (Exception $e) {
        error_log("Recent applications error: " . $e->getMessage());
        // Try fallback query without JOIN if hr_employees structure is different
        try {
            $result = $conn->query("
                SELECT *, 'Unknown Employee' as first_name, '' as last_name, 'N/A' as emp_code
                FROM hr_leave_applications 
                ORDER BY applied_date DESC 
                LIMIT 10
            ");
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $recentApplications[] = $row;
                }
            }
        } catch (Exception $e2) {
            error_log("Fallback recent applications error: " . $e2->getMessage());
        }
    }
}

// Get pending approvals (for managers/HR)
$pendingApprovals = [];
if (($currentUserRole === 'admin' || $currentUserRole === 'hr' || ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'hr')) && $tableExists) {
    try {
        $result = $conn->query("
            SELECT la.*, e.first_name, e.last_name, e.employee_id as emp_code, d.department_name
            FROM hr_leave_applications la
            JOIN hr_employees e ON la.employee_id = e.id
            LEFT JOIN hr_departments d ON e.department_id = d.id
            WHERE la.status = 'pending'
            ORDER BY la.applied_date ASC
        ");
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $pendingApprovals[] = $row;
            }
        }
    } catch (Exception $e) {
        error_log("Pending approvals error: " . $e->getMessage());
        // Try fallback query without JOINs
        try {
            $result = $conn->query("
                SELECT *, 'Unknown Employee' as first_name, '' as last_name, 'N/A' as emp_code, 'N/A' as department_name
                FROM hr_leave_applications 
                WHERE status = 'pending' 
                ORDER BY applied_date ASC
            ");
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $pendingApprovals[] = $row;
                }
            }
        } catch (Exception $e2) {
            error_log("Fallback pending approvals error: " . $e2->getMessage());
        }
    }
}
?>

<style>
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --success-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    --warning-gradient: linear-gradient(135deg, #ffeaa7 0%, #fab1a0 100%);
    --danger-gradient: linear-gradient(135deg, #fd79a8 0%, #e84393 100%);
    --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}



.page-header {
    background: var(--primary-gradient);
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 2rem;
    color: white;
    text-align: center;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    box-shadow: var(--card-shadow);
    text-align: center;
    transition: all 0.3s ease;
    border: none;
    height: 100%;
}

.stat-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.stat-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    font-size: 2rem;
    color: white;
}

.stat-icon.pending { background: var(--warning-gradient); }
.stat-icon.approved { background: var(--success-gradient); }
.stat-icon.rejected { background: var(--danger-gradient); }
.stat-icon.total { background: var(--primary-gradient); }

.stat-number {
    font-size: 3rem;
    font-weight: 700;
    color: #2c3e50;
    margin: 0;
    line-height: 1;
}

.stat-label {
    font-size: 1rem;
    color: #6c757d;
    margin-top: 0.5rem;
    font-weight: 500;
}

.content-card {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    box-shadow: var(--card-shadow);
    margin-bottom: 2rem;
}

.content-card h5 {
    color: #2c3e50;
    font-weight: 600;
    margin-bottom: 1.5rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #f8f9fa;
}

.form-control, .form-select {
    border-radius: 15px;
    border: 2px solid #e9ecef;
    padding: 0.75rem 1rem;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.btn-primary {
    background: var(--primary-gradient);
    border: none;
    border-radius: 15px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
}

.btn-success {
    background: var(--success-gradient);
    border: none;
    border-radius: 15px;
    padding: 0.5rem 1rem;
}

.btn-danger {
    background: var(--danger-gradient);
    border: none;
    border-radius: 15px;
    padding: 0.5rem 1rem;
}

.table-container {
    border-radius: 20px;
    overflow: hidden;
    box-shadow: var(--card-shadow);
}

.table th {
    background: var(--primary-gradient);
    color: white;
    font-weight: 600;
    border: none;
    padding: 1rem;
}

.table td {
    padding: 1rem;
    vertical-align: middle;
    border-bottom: 1px solid #f8f9fa;
}

.status-badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 600;
}

.status-pending { background: #ffeaa7; color: #d63031; }
.status-approved { background: #55efc4; color: #00b894; }
.status-rejected { background: #fab1a0; color: #e17055; }

.empty-state {
    text-align: center;
    padding: 3rem;
    color: #6c757d;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

@media (max-width: 768px) {
    
    
    .content-card {
        padding: 1.5rem;
        margin-bottom: 1rem;
    }
    
    .stat-card {
        padding: 1.5rem;
        margin-bottom: 1rem;
    }
    
    .stat-number {
        font-size: 2.5rem;
    }
}
</style>

<!-- Page Content Starts Here -->
<div class="container-fluid">
        <?php if (!$tableExists): ?>
        <div class="alert alert-warning" role="alert">
            <h4 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i>Setup Required</h4>
            <p>The leave applications table is not set up yet. Please run the setup to create the required database table.</p>
            <hr>
            <p class="mb-0"><a href="setup_leave_management.php" class="btn btn-primary">Setup Leave Management Table</a></p>
        </div>
        <?php endif; ?>
        
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="bi bi-calendar-x-fill me-3"></i>Leave Management</h1>
            <p class="mb-0">Comprehensive leave application, approval, and tracking system</p>
            <?php if ($debug_mode): ?>
                <small class="text-light">Debug mode enabled - <a href="?" class="text-light">Disable</a></small>
            <?php else: ?>
                <small class="text-light"><a href="?debug=1" class="text-light">Enable debug mode</a></small>
            <?php endif; ?>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon pending">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <h2 class="stat-number"><?= $leaveStats['pending'] ?></h2>
                    <p class="stat-label">Pending Requests</p>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon approved">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <h2 class="stat-number"><?= $leaveStats['approved'] ?></h2>
                    <p class="stat-label">Approved Requests</p>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon rejected">
                        <i class="bi bi-x-circle-fill"></i>
                    </div>
                    <h2 class="stat-number"><?= $leaveStats['rejected'] ?></h2>
                    <p class="stat-label">Rejected Requests</p>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon total">
                        <i class="bi bi-calendar3"></i>
                    </div>
                    <h2 class="stat-number"><?= $leaveStats['total_this_month'] ?></h2>
                    <p class="stat-label">This Month</p>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Leave Application Form -->
            <div class="col-lg-6">
                <div class="content-card">
                    <h5><i class="bi bi-plus-circle-fill me-2 text-primary"></i>Apply for Leave</h5>
                    
                    <form id="leaveApplicationForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Employee</label>
                                <select class="form-select" name="employee_id" required>
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $employee): ?>
                                        <option value="<?= $employee['id'] ?>" <?= ($employee['id'] == $currentUserId) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?> 
                                            (<?= htmlspecialchars($employee['employee_id']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Leave Type</label>
                                <select class="form-select" name="leave_type_id" required>
                                    <option value="1">Annual Leave</option>
                                    <option value="2">Sick Leave</option>
                                    <option value="3">Personal Leave</option>
                                    <option value="4">Emergency Leave</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control" name="end_date" required>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Reason</label>
                                <textarea class="form-control" name="reason" rows="3" placeholder="Please provide reason for leave..." required></textarea>
                            </div>
                            
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-send-fill me-2"></i>Submit Application
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Recent Applications -->
            <div class="col-lg-6">
                <div class="content-card">
                    <h5><i class="bi bi-clock-history me-2 text-info"></i>Recent Applications</h5>
                    
                    <?php if (!empty($recentApplications)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Dates</th>
                                        <th>Days</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($recentApplications, 0, 5) as $application): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($application['first_name'] . ' ' . $application['last_name']) ?></strong>
                                                <br><small class="text-muted"><?= htmlspecialchars($application['emp_code']) ?></small>
                                            </td>
                                            <td>
                                                <small>
                                                    <?= date('M j', strtotime($application['start_date'])) ?> - 
                                                    <?= date('M j, Y', strtotime($application['end_date'])) ?>
                                                </small>
                                            </td>
                                            <td><?= $application['days_requested'] ?></td>
                                            <td>
                                                <span class="status-badge status-<?= $application['status'] ?>">
                                                    <?= ucfirst($application['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-inbox display-4 d-block"></i>
                            <p>No recent leave applications</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Pending Approvals (Admin/HR Only) -->
        <?php if (!empty($pendingApprovals) && ($currentUserRole === 'admin' || $currentUserRole === 'hr')): ?>
        <div class="row g-4 mt-2">
            <div class="col-12">
                <div class="content-card">
                    <h5><i class="bi bi-clipboard-check me-2 text-warning"></i>Pending Approvals</h5>
                    
                    <div class="table-container">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Department</th>
                                    <th>Leave Dates</th>
                                    <th>Days</th>
                                    <th>Reason</th>
                                    <th>Applied Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingApprovals as $approval): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($approval['first_name'] . ' ' . $approval['last_name']) ?></strong>
                                            <br><small class="text-muted"><?= htmlspecialchars($approval['emp_code']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($approval['department_name'] ?? 'N/A') ?></td>
                                        <td>
                                            <?= date('M j', strtotime($approval['start_date'])) ?> - 
                                            <?= date('M j, Y', strtotime($approval['end_date'])) ?>
                                        </td>
                                        <td><?= $approval['days_requested'] ?></td>
                                        <td><?= htmlspecialchars(substr($approval['reason'], 0, 50)) ?>...</td>
                                        <td><?= date('M j, Y', strtotime($approval['applied_date'])) ?></td>
                                        <td>
                                            <button class="btn btn-success btn-sm me-1" onclick="approveLeave(<?= $approval['id'] ?>, 'approve')">
                                                <i class="bi bi-check"></i> Approve
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="approveLeave(<?= $approval['id'] ?>, 'reject')">
                                                <i class="bi bi-x"></i> Reject
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
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Leave application form
    const leaveForm = document.getElementById('leaveApplicationForm');
    if (leaveForm) {
        leaveForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'apply_leave');
            
            fetch('leave_management.php', {
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
                console.error('Error:', error);
                alert('An error occurred while submitting the application');
            });
        });
    }
    
    console.log('✅ Leave Management loaded successfully');
});

function approveLeave(applicationId, decision) {
    const comments = prompt(`Please enter comments for ${decision}ing this leave request:`);
    if (comments === null) return; // User canceled
    
    const formData = new FormData();
    formData.append('action', 'approve_leave');
    formData.append('application_id', applicationId);
    formData.append('decision', decision);
    formData.append('comments', comments);
    
    fetch('leave_management.php', {
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
        alert('An error occurred while processing the request');
    });
}
</script>

<?php 
<?php require_once 'hrms_footer_simple.php'; ?>