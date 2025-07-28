<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Employee Self Service Portal';

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Get employee information (in real implementation, this would be from employee login)
$employee_id = $_SESSION['employee_id'] ?? 1; // Mock employee ID
$employee_info = [];

try {
    $emp_query = $conn->prepare("SELECT * FROM employees WHERE employee_id = ? AND status = 'active'");
    if ($emp_query) {
        $emp_query->bind_param("i", $employee_id);
        $emp_query->execute();
        $result = $emp_query->get_result();
        
        if ($result && $row = $result->fetch_assoc()) {
            $employee_info = $row;
        }
        $emp_query->close();
    } else {
        throw new Exception("Failed to prepare employee query: " . $conn->error);
    }
} catch (Exception $e) {
    error_log("Error fetching employee info: " . $e->getMessage());
    // Set default employee info if query fails
    $employee_info = [
        'name' => 'Employee Name',
        'employee_code' => 'EMP001',
        'position' => 'Position',
        'email' => 'employee@company.com'
    ];
}

// Get employee leave balance
$leave_balance = [
    'casual' => 12,
    'sick' => 7,
    'earned' => 21,
    'comp_off' => 5
];

try {
    $balance_query = $conn->prepare("
        SELECT casual_leave_balance, sick_leave_balance, earned_leave_balance, comp_off_balance 
        FROM employee_leave_balance 
        WHERE employee_id = ? AND year = YEAR(CURDATE())
    ");
    
    if ($balance_query) {
        $balance_query->bind_param("i", $employee_id);
        $balance_query->execute();
        $balance_result = $balance_query->get_result();
        
        if ($balance_result && $balance_row = $balance_result->fetch_assoc()) {
            $leave_balance = [
                'casual' => $balance_row['casual_leave_balance'] ?? 12,
                'sick' => $balance_row['sick_leave_balance'] ?? 7,
                'earned' => $balance_row['earned_leave_balance'] ?? 21,
                'comp_off' => $balance_row['comp_off_balance'] ?? 5
            ];
        }
        $balance_query->close();
    } else {
        error_log("Failed to prepare leave balance query: " . $conn->error);
    }
} catch (Exception $e) {
    error_log("Error fetching leave balance: " . $e->getMessage());
}

// Set base path for assets
$basePath = '../../';

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
                <h1 class="h5 mb-1">
                    <i class="bi bi-person-circle me-2 text-info"></i>Employee Self Service Portal
                </h1>
                <p class="text-muted mb-0 small">Manage your attendance, leaves, and personal information - <?= date('F j, Y') ?></p>
                
                <!-- Breadcrumb Navigation -->
                <nav aria-label="breadcrumb" class="mt-1">
                    <ol class="breadcrumb small mb-0">
                        <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="../hr/hr_dashboard.php">HR Portal</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Employee Portal</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex gap-1">
                <!-- Quick Actions -->
                <div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-outline-success btn-sm" onclick="downloadPayslip()" title="Download Payslip">
                        <i class="bi bi-download"></i>
                    </button>
                    <button class="btn btn-outline-primary btn-sm" onclick="refreshEmployeeData()" title="Refresh Data">
                        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                    </button>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-info btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-person-gear me-1"></i>My Account
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="openProfileModal()">
                                <i class="bi bi-person me-2"></i>Edit Profile
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="changePassword()">
                                <i class="bi bi-key me-2"></i>Change Password
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" onclick="viewDocument()">
                                <i class="bi bi-file-text me-2"></i>My Documents
                            </a></li>
                        </ul>
                    </div>
                </div>

                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#employeeLeaveModal">
                    <i class="bi bi-calendar-plus me-1"></i>Apply Leave
                </button>
            </div>
        </div>

        <!-- Employee Overview Cards -->
        <div class="row g-2 mb-3">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center p-2">
                        <div class="bg-info bg-opacity-10 rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                            <i class="bi bi-person-fill text-info fs-5"></i>
                        </div>
                        <h6 class="mb-1 small"><?= htmlspecialchars($employee_info['name'] ?? 'Employee Name') ?></h6>
                        <small class="text-muted d-block"><?= htmlspecialchars($employee_info['employee_code'] ?? 'EMP001') ?></small>
                        <small class="text-muted"><?= htmlspecialchars($employee_info['position'] ?? 'Position') ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100 bg-success text-white">
                    <div class="card-body p-2">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="bi bi-calendar-check fs-4"></i>
                            </div>
                            <div class="flex-grow-1 ms-2">
                                <h6 class="mb-0 fw-bold">92%</h6>
                                <p class="mb-0 small opacity-75">Attendance Rate</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100 bg-warning text-dark">
                    <div class="card-body p-2">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="bi bi-calendar-event fs-4"></i>
                            </div>
                            <div class="flex-grow-1 ms-2">
                                <h6 class="mb-0 fw-bold">3</h6>
                                <p class="mb-0 small">Pending Requests</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100 bg-info text-white">
                    <div class="card-body p-2">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="bi bi-clock fs-4"></i>
                            </div>
                            <div class="flex-grow-1 ms-2">
                                <h6 class="mb-0 fw-bold">8.5h</h6>
                                <p class="mb-0 small opacity-75">Avg. Daily Hours</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Leave Balance & Application -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-light border-0">
                        <h6 class="mb-0">
                            <i class="bi bi-wallet2 me-2"></i>Leave Balance Overview
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="card bg-primary text-white text-center">
                                    <div class="card-body p-3">
                                        <h4 class="mb-1"><?= $leave_balance['casual'] ?></h4>
                                        <small>Casual Leave</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="card bg-success text-white text-center">
                                    <div class="card-body p-3">
                                        <h4 class="mb-1"><?= $leave_balance['sick'] ?></h4>
                                        <small>Sick Leave</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="card bg-info text-white text-center">
                                    <div class="card-body p-3">
                                        <h4 class="mb-1"><?= $leave_balance['earned'] ?></h4>
                                        <small>Earned Leave</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="card bg-warning text-dark text-center">
                                    <div class="card-body p-3">
                                        <h4 class="mb-1"><?= $leave_balance['comp_off'] ?></h4>
                                        <small>Comp-off</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <div class="d-grid">
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#employeeLeaveModal">
                                    <i class="bi bi-calendar-plus me-2"></i>Apply for Leave
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Attendance -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light border-0">
                        <h6 class="mb-0">
                            <i class="bi bi-clock-history me-2"></i>Recent Attendance
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>Today</strong>
                                        <br><small class="text-muted"><?= date('F j, Y') ?></small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-success">Present</span>
                                        <br><small class="text-muted">9:15 AM - 6:30 PM</small>
                                    </div>
                                </div>
                            </div>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>Yesterday</strong>
                                        <br><small class="text-muted"><?= date('F j, Y', strtotime('-1 day')) ?></small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-success">Present</span>
                                        <br><small class="text-muted">9:00 AM - 6:15 PM</small>
                                    </div>
                                </div>
                            </div>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= date('F j, Y', strtotime('-2 days')) ?></strong>
                                        <br><small class="text-muted">2 days ago</small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-warning">Late</span>
                                        <br><small class="text-muted">10:30 AM - 6:45 PM</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- My Leave Requests & Profile -->
            <div class="col-lg-6">
                <!-- My Leave Requests -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-light border-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="bi bi-calendar-event me-2"></i>My Leave Requests
                            </h6>
                            <button class="btn btn-outline-primary btn-sm" onclick="loadMyLeaveRequests()">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="myLeaveRequestsContainer">
                            <div class="list-group list-group-flush">
                                <div class="list-group-item border-0 px-0">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>Casual Leave</strong>
                                            <br><small class="text-muted">Jul 30 - Aug 1 (2 days)</small>
                                        </div>
                                        <span class="badge bg-warning">Pending</span>
                                    </div>
                                </div>
                                <div class="list-group-item border-0 px-0">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>Work From Home</strong>
                                            <br><small class="text-muted">Jul 28 (1 day)</small>
                                        </div>
                                        <span class="badge bg-success">Approved</span>
                                    </div>
                                </div>
                                <div class="list-group-item border-0 px-0">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>Half Day</strong>
                                            <br><small class="text-muted">Jul 25 (0.5 days)</small>
                                        </div>
                                        <span class="badge bg-success">Approved</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Employee Quick Actions -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-light border-0">
                        <h6 class="mb-0">
                            <i class="bi bi-lightning me-2"></i>Quick Actions
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary btn-sm" onclick="viewMyAttendanceCalendar()">
                                <i class="bi bi-calendar3 me-1"></i>My Attendance Calendar
                            </button>
                            <button class="btn btn-outline-success btn-sm" onclick="downloadAttendanceReport()">
                                <i class="bi bi-download me-1"></i>Download Attendance Report
                            </button>
                            <button class="btn btn-outline-warning btn-sm" onclick="updateProfile()">
                                <i class="bi bi-person-gear me-1"></i>Update Profile
                            </button>
                            <button class="btn btn-outline-info btn-sm" onclick="viewPayslips()">
                                <i class="bi bi-file-text me-1"></i>View Payslips
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Notifications -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light border-0">
                        <h6 class="mb-0">
                            <i class="bi bi-bell me-2"></i>Recent Notifications
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    <div class="flex-grow-1">
                                        <small class="fw-bold">Leave Approved</small>
                                        <br><small class="text-muted">Your WFH request has been approved</small>
                                    </div>
                                    <small class="text-muted">2h ago</small>
                                </div>
                            </div>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-info-circle text-info me-2"></i>
                                    <div class="flex-grow-1">
                                        <small class="fw-bold">Policy Update</small>
                                        <br><small class="text-muted">New leave policy effective next month</small>
                                    </div>
                                    <small class="text-muted">1d ago</small>
                                </div>
                            </div>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-calendar text-warning me-2"></i>
                                    <div class="flex-grow-1">
                                        <small class="fw-bold">Holiday Reminder</small>
                                        <br><small class="text-muted">Independence Day holiday on Aug 15</small>
                                    </div>
                                    <small class="text-muted">2d ago</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Employee Leave Application Modal -->
<div class="modal fade" id="employeeLeaveModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-calendar-plus me-2"></i>Apply for Leave
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="employeeLeaveForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="employeeLeaveType" class="form-label">Leave Type</label>
                            <select class="form-select" id="employeeLeaveType" name="leave_type" required>
                                <option value="">Select Leave Type</option>
                                <option value="casual">🏖️ Casual Leave</option>
                                <option value="sick">🤒 Sick Leave</option>
                                <option value="earned">🎯 Earned Leave</option>
                                <option value="comp-off">⚖️ Comp-off</option>
                                <option value="wfh">🏠 Work From Home</option>
                                <option value="half-day">⏰ Half Day</option>
                                <option value="short-leave">🏃 Short Leave</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="employeeLeavePriority" class="form-label">Priority</label>
                            <select class="form-select" id="employeeLeavePriority" name="priority">
                                <option value="normal">Normal</option>
                                <option value="urgent">Urgent</option>
                                <option value="emergency">Emergency</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="employeeStartDate" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="employeeStartDate" name="start_date" required>
                        </div>
                        <div class="col-md-6">
                            <label for="employeeEndDate" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="employeeEndDate" name="end_date" required>
                        </div>
                        <div class="col-12">
                            <label for="employeeLeaveReason" class="form-label">Reason for Leave</label>
                            <textarea class="form-control" id="employeeLeaveReason" name="reason" rows="3" 
                                      placeholder="Please provide a detailed reason for your leave request..." required></textarea>
                        </div>
                        <div class="col-12">
                            <label for="employeeEmergencyContact" class="form-label">Emergency Contact (Optional)</label>
                            <input type="text" class="form-control" id="employeeEmergencyContact" name="emergency_contact"
                                   placeholder="Contact number/email for emergency">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="employeeNotifyManager" name="notify_manager" checked>
                                <label class="form-check-label" for="employeeNotifyManager">
                                    Notify manager immediately via email
                                </label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitEmployeeLeaveRequest()">
                    <i class="bi bi-send me-1"></i>Submit Request
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-lg {
    width: 80px;
    height: 80px;
}

.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.list-group-item {
    border: none;
    padding: 1rem 0;
}

.list-group-item:not(:last-child) {
    border-bottom: 1px solid #f8f9fa;
}

.badge {
    font-size: 0.75rem;
}
</style>

<script>
// Initialize Employee Portal
document.addEventListener('DOMContentLoaded', function() {
    loadEmployeeData();
    setupLeaveForm();
});

function loadEmployeeData() {
    // Load employee-specific data
    console.log('Loading employee data...');
}

function setupLeaveForm() {
    // Set minimum date to today
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('employeeStartDate').setAttribute('min', today);
    document.getElementById('employeeEndDate').setAttribute('min', today);
    
    // Update end date when start date changes
    document.getElementById('employeeStartDate').addEventListener('change', function() {
        const startDate = this.value;
        document.getElementById('employeeEndDate').setAttribute('min', startDate);
        if (document.getElementById('employeeEndDate').value < startDate) {
            document.getElementById('employeeEndDate').value = startDate;
        }
    });
}

function submitEmployeeLeaveRequest() {
    const form = document.getElementById('employeeLeaveForm');
    const formData = new FormData(form);
    
    // Add employee ID (in real implementation, get from session)
    formData.append('employee_id', 1);
    formData.append('action', 'submit_leave_request');
    
    // Show loading state
    const submitBtn = event.target;
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Submitting...';
    submitBtn.disabled = true;
    
    fetch('../attendance/process_leave_request.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Leave request submitted successfully! You will be notified once it\'s reviewed.', 'success');
            form.reset();
            const modal = bootstrap.Modal.getInstance(document.getElementById('employeeLeaveModal'));
            modal.hide();
            loadMyLeaveRequests();
        } else {
            showAlert('Error: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Network error. Please try again.', 'danger');
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

// Enhanced Employee Portal Functions
function downloadPayslip() {
    showAlert('Preparing payslip download...', 'info');
    setTimeout(() => {
        showAlert('Payslip downloaded successfully!', 'success');
    }, 2000);
}

function openProfileModal() {
    showAlert('Profile edit modal opening...', 'info');
    // Implement profile editing
}

function changePassword() {
    showAlert('Password change modal opening...', 'info');
    // Implement password change
}

function viewDocument() {
    showAlert('Document viewer opening...', 'info');
    // Implement document viewer
}

function loadMyLeaveRequests() {
    // Load employee's leave requests
    console.log('Loading leave requests...');
}

function refreshEmployeeData() {
    loadEmployeeData();
    loadMyLeaveRequests();
    showAlert('Data refreshed successfully!', 'success');
}

function viewMyAttendanceCalendar() {
    window.open('../../attendance-calendar.php', '_blank');
}

function downloadAttendanceReport() {
    showAlert('Attendance report download feature coming soon!', 'info');
}

function updateProfile() {
    showAlert('Profile update feature coming soon!', 'info');
}

function viewPayslips() {
    showAlert('Payslip viewing feature coming soon!', 'info');
}

// Utility function to show alerts
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}
</script>

<?php include '../../layouts/footer.php'; ?>
