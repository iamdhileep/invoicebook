<?php
// Include performance monitor if requested
if (isset($_GET['perf']) || isset($_COOKIE['perf_monitor'])) {
    require_once '../../includes/performance_monitor.php';
}

session_start();

// Check authentication
if (!isset($_SESSION['user_id']) && !isset($_SESSION['employee_id'])) {
    // Set demo Employee session
    $_SESSION['employee_id'] = 23; // Use existing employee ID
    $_SESSION['user_id'] = 23; // For auth compatibility
    $_SESSION['role'] = 'employee';
}

// Include optimized database connection and auth check
require_once '../../db.php';
require_once '../../auth_check.php';

$pageTitle = "Employee Portal";

// Get employee details
$employee_id = $_SESSION['employee_id'];
$employee = null;
$today_attendance = null;
$monthly_stats = [
    'days_present' => 0,
    'total_hours' => 0,
    'avg_hours' => 0,
    'leave_balance' => 15
];

try {
    // Get employee details
    $stmt = $conn->prepare("SELECT * FROM employees WHERE employee_id = ?");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $employee = $result->fetch_assoc();
    }
    $stmt->close();
    
    // Get today's attendance (not cached as it changes frequently)
    $stmt = $conn->prepare("
        SELECT * FROM attendance 
        WHERE employee_id = ? AND attendance_date = CURDATE()
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $today_attendance = $result->fetch_assoc();
    }
    $stmt->close();
    
    // Get monthly statistics (cached for 1 hour)
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT attendance_date) as days_present,
            SUM(work_duration) as total_hours,
            AVG(work_duration) as avg_hours
        FROM attendance 
        WHERE employee_id = ? 
        AND MONTH(attendance_date) = MONTH(CURDATE()) 
        AND YEAR(attendance_date) = YEAR(CURDATE())
        AND punch_in_time IS NOT NULL
    ");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $monthly_stats['days_present'] = $row['days_present'] ?? 0;
        $monthly_stats['total_hours'] = round($row['total_hours'] ?? 0, 2);
        $monthly_stats['avg_hours'] = round($row['avg_hours'] ?? 0, 2);
    }
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Employee Portal database error: " . $e->getMessage());
}
?>

<div class="main-content">
    <div class="container-fluid p-4">
        <!-- Welcome Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card welcome-header">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h3 class="mb-0">Employee Portal</h3>
                                <p class="text-muted mb-0" id="employeeWelcome">Self-service portal for all your HR needs</p>
                            </div>
                            <div class="col-md-4 text-end">
                                <button class="btn btn-primary" onclick="refreshPortal()">
                                    <i class="fas fa-sync-alt"></i> Refresh
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions & Stats -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <!-- Quick Actions -->
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="d-grid">
                                    <button class="btn btn-success btn-lg" id="punchInBtn" onclick="punchIn()">
                                        <i class="fas fa-clock"></i> Punch In
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-grid">
                                    <button class="btn btn-danger btn-lg" id="punchOutBtn" onclick="punchOut()" disabled>
                                        <i class="fas fa-clock"></i> Punch Out
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-grid">
                                    <button class="btn btn-info btn-lg" data-bs-toggle="modal" data-bs-target="#leaveModal">
                                        <i class="fas fa-calendar-alt"></i> Apply Leave
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-grid">
                                    <button class="btn btn-warning btn-lg" onclick="viewPayslip()">
                                        <i class="fas fa-file-invoice"></i> View Payslip
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="attendance-status" id="attendanceStatus">
                                <p class="mb-1"><strong>Today's Status:</strong> <span id="todayStatus">Not Punched In</span></p>
                                <p class="mb-0"><strong>Work Hours:</strong> <span id="workHours">0.00 hrs</span></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <!-- Stats Cards -->
                <div class="row g-3">
                    <div class="col-12">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6>This Month</h6>
                                        <h4 id="monthlyAttendance">-</h4>
                                        <small>Days Present</small>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-calendar-check fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="card stat-card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6>Leave Balance</h6>
                                        <h4 id="leaveBalance">-</h4>
                                        <small>Days Available</small>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-beach-ball fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs" id="employeeTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button" role="tab">
                    <i class="fas fa-clock"></i> My Attendance
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="leaves-tab" data-bs-toggle="tab" data-bs-target="#leaves" type="button" role="tab">
                    <i class="fas fa-calendar-alt"></i> Leave Management
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="payroll-tab" data-bs-toggle="tab" data-bs-target="#payroll" type="button" role="tab">
                    <i class="fas fa-money-bill"></i> Payroll & Benefits
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab">
                    <i class="fas fa-user"></i> My Profile
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="mobile-attendance-tab" data-bs-toggle="tab" data-bs-target="#mobile-attendance" type="button" role="tab">
                    <i class="fas fa-mobile-alt"></i> Mobile Attendance
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="payroll-preview-tab" data-bs-toggle="tab" data-bs-target="#payroll-preview" type="button" role="tab">
                    <i class="fas fa-calculator"></i> Payroll Preview
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="analytics-tab" data-bs-toggle="tab" data-bs-target="#analytics" type="button" role="tab">
                    <i class="fas fa-chart-pie"></i> My Analytics
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="schedule-tab" data-bs-toggle="tab" data-bs-target="#schedule" type="button" role="tab">
                    <i class="fas fa-calendar-week"></i> My Schedule
                </button>
            </li>
        </ul>

        <div class="tab-content" id="employeeTabContent">
            <!-- Attendance Tab -->
            <div class="tab-pane fade show active" id="attendance" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">My Attendance History</h5>
                        <div>
                            <input type="month" class="form-control d-inline-block w-auto" id="attendanceMonth" value="<?php echo date('Y-m'); ?>">
                            <button class="btn btn-primary" onclick="loadAttendanceHistory()">
                                <i class="fas fa-search"></i> Load
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped" id="attendanceTable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Punch In</th>
                                        <th>Punch Out</th>
                                        <th>Work Hours</th>
                                        <th>Status</th>
                                        <th>Location</th>
                                    </tr>
                                </thead>
                                <tbody id="attendanceTableBody">
                                    <tr><td colspan="6" class="text-center">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Leave Management Tab -->
            <div class="tab-pane fade" id="leaves" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">My Leave Requests</h5>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#leaveModal">
                            <i class="fas fa-plus"></i> Apply Leave
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <h5 class="text-primary" id="totalLeaveBalance">30</h5>
                                        <small class="text-muted">Total Annual Leaves</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <h5 class="text-success" id="usedLeaves">0</h5>
                                        <small class="text-muted">Leaves Used</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <h5 class="text-warning" id="remainingLeaves">30</h5>
                                        <small class="text-muted">Leaves Remaining</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped" id="leavesTable">
                                <thead>
                                    <tr>
                                        <th>Leave Type</th>
                                        <th>From Date</th>
                                        <th>To Date</th>
                                        <th>Days</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                        <th>Applied Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="leavesTableBody">
                                    <tr><td colspan="8" class="text-center">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payroll Tab -->
            <div class="tab-pane fade" id="payroll" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0">Payroll Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row" id="payrollInfo">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Current Salary Details</h6>
                                    </div>
                                    <div class="card-body" id="salaryDetails">
                                        <p class="text-center">Loading salary information...</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Recent Payslips</h6>
                                    </div>
                                    <div class="card-body" id="recentPayslips">
                                        <p class="text-center">Loading payslip history...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Tab -->
            <div class="tab-pane fade" id="profile" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0">My Profile</h5>
                    </div>
                    <div class="card-body">
                        <form id="profileForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Full Name</label>
                                        <input type="text" class="form-control" name="name" id="profileName" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Employee Code</label>
                                        <input type="text" class="form-control" id="profileCode" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Position</label>
                                        <input type="text" class="form-control" id="profilePosition" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Phone Number</label>
                                        <input type="text" class="form-control" name="phone" id="profilePhone">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address" id="profileAddress" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <button type="button" class="btn btn-primary" onclick="updateProfile()">
                                    <i class="fas fa-save"></i> Update Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Mobile Attendance Tab -->
            <div class="tab-pane fade" id="mobile-attendance" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-mobile-alt me-2"></i>Mobile Attendance System</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>GPS Attendance</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <small class="text-muted">Current Location</small>
                                            <div id="currentLocation" class="fw-bold">Detecting location...</div>
                                        </div>
                                        <div class="mb-3">
                                            <small class="text-muted">Distance from Office</small>
                                            <div id="distanceFromOffice" class="fw-bold text-info">--</div>
                                        </div>
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-success" onclick="gpsAttendance('in')" id="gpsPunchIn">
                                                <i class="fas fa-map-marker-alt me-2"></i>GPS Punch In
                                            </button>
                                            <button class="btn btn-danger" onclick="gpsAttendance('out')" id="gpsPunchOut">
                                                <i class="fas fa-map-marker-alt me-2"></i>GPS Punch Out
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-mobile-alt me-2"></i>Mobile Punch Request</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Request Type</label>
                                            <select class="form-select" id="mobileRequestType">
                                                <option value="punch_in">Punch In</option>
                                                <option value="punch_out">Punch Out</option>
                                                <option value="break_start">Break Start</option>
                                                <option value="break_end">Break End</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Reason (Optional)</label>
                                            <textarea class="form-control" id="mobileRequestReason" rows="3" placeholder="Provide reason for mobile attendance request"></textarea>
                                        </div>
                                        <button class="btn btn-primary w-100" onclick="submitMobileRequest()">
                                            <i class="fas fa-paper-plane me-2"></i>Submit Request
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-history me-2"></i>Mobile Attendance History</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover" id="mobileAttendanceHistory">
                                                <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Request Type</th>
                                                        <th>Location</th>
                                                        <th>Status</th>
                                                        <th>Processed By</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td colspan="5" class="text-center py-4">
                                                            <div class="loading-spinner me-2"></div>
                                                            Loading mobile attendance history...
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Payroll Preview Tab -->
            <div class="tab-pane fade" id="payroll-preview" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-calculator me-2"></i>Real-time Payroll Preview</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Preview Period</label>
                                <input type="month" class="form-control" id="payrollPreviewMonth" value="<?php echo date('Y-m'); ?>">
                            </div>
                            <div class="col-md-6">
                                <button class="btn btn-primary mt-4" onclick="loadPayrollPreview()">
                                    <i class="fas fa-sync me-2"></i>Refresh Preview
                                </button>
                            </div>
                        </div>
                        <div id="payrollPreviewContainer">
                            <div class="text-center py-4">
                                <div class="loading-spinner me-2"></div>
                                Loading payroll preview...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Personal Analytics Tab -->
            <div class="tab-pane fade" id="analytics" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>My Performance Analytics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <select class="form-select" id="analyticsFilter">
                                    <option value="week">Last 7 Days</option>
                                    <option value="month" selected>Last 30 Days</option>
                                    <option value="quarter">Last 90 Days</option>
                                    <option value="year">Last Year</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button class="btn btn-primary" onclick="loadPersonalAnalytics()">
                                    <i class="fas fa-chart-line me-2"></i>Generate Analytics
                                </button>
                            </div>
                        </div>
                        <div id="personalAnalyticsContainer">
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-chart-pie fa-3x mb-3"></i>
                                <p>Click "Generate Analytics" to view your performance insights</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- My Schedule Tab -->
            <div class="tab-pane fade" id="schedule" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-calendar-week me-2"></i>My Work Schedule</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Week Starting</label>
                                <input type="date" class="form-control" id="scheduleWeek" value="">
                            </div>
                            <div class="col-md-6">
                                <button class="btn btn-primary mt-4" onclick="loadMySchedule()">
                                    <i class="fas fa-calendar me-2"></i>Load Schedule
                                </button>
                            </div>
                        </div>
                        <div class="row g-4">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Weekly Schedule</h6>
                                    </div>
                                    <div class="card-body">
                                        <div id="weeklySchedule">
                                            <div class="text-center py-4">
                                                <div class="loading-spinner me-2"></div>
                                                Loading weekly schedule...
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Current Shift Info</h6>
                                    </div>
                                    <div class="card-body">
                                        <div id="currentShiftInfo">
                                            <div class="loading-spinner me-2"></div>
                                            Loading shift information...
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card mt-3">
                                    <div class="card-header">
                                        <h6 class="mb-0">Upcoming Shifts</h6>
                                    </div>
                                    <div class="card-body">
                                        <div id="upcomingShifts">
                                            <div class="loading-spinner me-2"></div>
                                            Loading upcoming shifts...
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Leave Application Modal -->
<div class="modal fade" id="leaveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Apply for Leave</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="leaveForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Leave Type *</label>
                        <select class="form-select" name="leave_type" required>
                            <option value="">Select Leave Type</option>
                            <option value="sick">Sick Leave</option>
                            <option value="casual">Casual Leave</option>
                            <option value="annual">Annual Leave</option>
                            <option value="emergency">Emergency Leave</option>
                            <option value="maternity">Maternity Leave</option>
                            <option value="paternity">Paternity Leave</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">From Date *</label>
                                <input type="date" class="form-control" name="from_date" required id="fromDate">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">To Date *</label>
                                <input type="date" class="form-control" name="to_date" required id="toDate">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Days Requested</label>
                        <input type="number" class="form-control" name="days_requested" id="daysRequested" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason *</label>
                        <textarea class="form-control" name="reason" rows="3" required placeholder="Please provide reason for leave..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Apply Leave</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.stat-card {
    transition: transform 0.2s;
}
.stat-card:hover {
    transform: translateY(-5px);
}
.stat-icon {
    opacity: 0.3;
}
.welcome-header {
    background: linear-gradient(135deg, #007bff 0%, #6610f2 100%);
    color: white;
}
.nav-tabs .nav-link {
    color: #495057;
    border: none;
    background: none;
    padding: 12px 20px;
}
.nav-tabs .nav-link.active {
    background: #007bff;
    color: white;
    border-radius: 5px;
}
.nav-tabs .nav-link:hover {
    background: #f8f9fa;
    border-radius: 5px;
}
.table th {
    background: #f8f9fa;
    border-top: none;
}
.attendance-status {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 5px;
    border-left: 4px solid #007bff;
}
.btn-lg {
    padding: 12px 24px;
    font-size: 1.1rem;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
$(document).ready(function() {
    loadEmployeeProfile();
    loadAttendanceStatus();
    loadQuickStats();
    loadAttendanceHistory();
    loadLeaveHistory();
    loadPayrollInfo();
    
    // Date change calculations for leave form
    $('#fromDate, #toDate').change(function() {
        calculateLeaveDays();
    });
});

// Portal Functions
function refreshPortal() {
    loadEmployeeProfile();
    loadAttendanceStatus();
    loadQuickStats();
    loadAttendanceHistory();
    loadLeaveHistory();
    showAlert('Portal refreshed successfully', 'success');
}

function loadEmployeeProfile() {
    $.post('employee_portal_api.php', {
        action: 'get_profile'
    }, function(response) {
        if (response.success) {
            const profile = response.data;
            $('#employeeWelcome').text(`Welcome back, ${profile.name}!`);
            $('#profileName').val(profile.name);
            $('#profileCode').val(profile.employee_code);
            $('#profilePosition').val(profile.position);
            $('#profilePhone').val(profile.phone);
            $('#profileAddress').val(profile.address);
        }
    }, 'json');
}

function loadAttendanceStatus() {
    $.post('employee_portal_api.php', {
        action: 'get_attendance_status'
    }, function(response) {
        if (response.success) {
            const status = response.data;
            $('#todayStatus').text(status.status || 'Not Punched In');
            $('#workHours').text((status.work_hours || 0) + ' hrs');
            
            // Update punch buttons
            if (status.punched_in) {
                $('#punchInBtn').prop('disabled', true).removeClass('btn-success').addClass('btn-secondary');
                $('#punchOutBtn').prop('disabled', false);
            } else {
                $('#punchInBtn').prop('disabled', false).removeClass('btn-secondary').addClass('btn-success');
                $('#punchOutBtn').prop('disabled', true);
            }
        }
    }, 'json');
}

function loadQuickStats() {
    $.post('employee_portal_api.php', {
        action: 'get_quick_stats'
    }, function(response) {
        if (response.success) {
            const stats = response.data;
            $('#monthlyAttendance').text(stats.monthly_attendance || 0);
            $('#leaveBalance').text(stats.leave_balance || 0);
            
            // Update leave balance cards
            $('#totalLeaveBalance').text(stats.total_leaves || 30);
            $('#usedLeaves').text(stats.used_leaves || 0);
            $('#remainingLeaves').text(stats.remaining_leaves || 30);
        }
    }, 'json');
}

// Attendance Functions
function punchIn() {
    if (confirm('Do you want to punch in now?')) {
        // Get location if available
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                submitPunch('in', position.coords.latitude, position.coords.longitude);
            }, function() {
                submitPunch('in', null, null);
            });
        } else {
            submitPunch('in', null, null);
        }
    }
}

function punchOut() {
    if (confirm('Do you want to punch out now?')) {
        // Get location if available
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                submitPunch('out', position.coords.latitude, position.coords.longitude);
            }, function() {
                submitPunch('out', null, null);
            });
        } else {
            submitPunch('out', null, null);
        }
    }
}

function submitPunch(type, latitude, longitude) {
    $.post('employee_portal_api.php', {
        action: 'punch_attendance',
        type: type,
        latitude: latitude,
        longitude: longitude,
        location: 'Office'
    }, function(response) {
        if (response.success) {
            loadAttendanceStatus();
            loadAttendanceHistory();
            loadQuickStats();
            showAlert('Punch ' + type + ' recorded successfully', 'success');
        } else {
            showAlert('Error: ' + response.message, 'danger');
        }
    }, 'json');
}

function loadAttendanceHistory() {
    const month = $('#attendanceMonth').val();
    
    $.post('employee_portal_api.php', {
        action: 'get_attendance_history',
        month: month
    }, function(response) {
        if (response.success) {
            let html = '';
            response.data.forEach(function(record) {
                const workHours = record.work_hours ? parseFloat(record.work_hours).toFixed(2) + ' hrs' : '-';
                const statusClass = record.status === 'present' ? 'bg-success' : 
                                   record.status === 'late' ? 'bg-warning' : 'bg-danger';
                
                html += `
                    <tr>
                        <td>${record.date}</td>
                        <td>${record.punch_in || '-'}</td>
                        <td>${record.punch_out || '-'}</td>
                        <td>${workHours}</td>
                        <td><span class="badge ${statusClass}">${record.status}</span></td>
                        <td>${record.location || '-'}</td>
                    </tr>
                `;
            });
            $('#attendanceTableBody').html(html || '<tr><td colspan="6" class="text-center">No attendance records found</td></tr>');
        } else {
            $('#attendanceTableBody').html('<tr><td colspan="6" class="text-center text-danger">Error loading attendance history</td></tr>');
        }
    }, 'json');
}

// Leave Functions
function loadLeaveHistory() {
    $.post('employee_portal_api.php', {
        module: 'employee',
        action: 'get_leave_requests'
    }, function(response) {
        if (response.success) {
            let html = '';
            response.data.forEach(function(leave) {
                const statusClass = {
                    'pending': 'bg-warning',
                    'approved': 'bg-success',
                    'rejected': 'bg-danger',
                    'cancelled': 'bg-secondary'
                }[leave.status] || 'bg-secondary';
                
                const actionButtons = leave.status === 'pending' ? `
                    <button class="btn btn-sm btn-danger" onclick="cancelLeave(${leave.id})">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                ` : '-';
                
                html += `
                    <tr>
                        <td>${leave.leave_type}</td>
                        <td>${leave.from_date}</td>
                        <td>${leave.to_date}</td>
                        <td>${leave.days_requested}</td>
                        <td title="${leave.reason}">${leave.reason.length > 30 ? leave.reason.substring(0, 30) + '...' : leave.reason}</td>
                        <td><span class="badge ${statusClass}">${leave.status}</span></td>
                        <td>${new Date(leave.applied_date).toLocaleDateString()}</td>
                        <td>${actionButtons}</td>
                    </tr>
                `;
            });
            $('#leavesTableBody').html(html || '<tr><td colspan="8" class="text-center">No leave requests found</td></tr>');
        } else {
            console.error('Leave API Error:', response);
            $('#leavesTableBody').html('<tr><td colspan="8" class="text-center text-danger">Error loading leave history: ' + (response.message || 'Unknown error') + '</td></tr>');
        }
    }, 'json').fail(function(xhr, status, error) {
        console.error('AJAX Error:', xhr.responseText, status, error);
        $('#leavesTableBody').html('<tr><td colspan="8" class="text-center text-danger">Connection error: ' + error + '</td></tr>');
    });
}

$('#leaveForm').submit(function(e) {
    e.preventDefault();
    
    $.post('employee_portal_api.php', {
        module: 'employee',
        action: 'apply_leave',
        ...Object.fromEntries(new FormData(this))
    }, function(response) {
        if (response.success) {
            $('#leaveModal').modal('hide');
            $('#leaveForm')[0].reset();
            loadLeaveHistory();
            loadQuickStats();
            showAlert('Leave application submitted successfully', 'success');
        } else {
            showAlert('Error: ' + response.message, 'danger');
        }
    }, 'json');
});

function calculateLeaveDays() {
    const fromDate = new Date($('#fromDate').val());
    const toDate = new Date($('#toDate').val());
    
    if (fromDate && toDate && toDate >= fromDate) {
        const timeDiff = toDate.getTime() - fromDate.getTime();
        const daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1;
        $('#daysRequested').val(daysDiff);
    } else {
        $('#daysRequested').val('');
    }
}

function cancelLeave(leaveId) {
    if (confirm('Are you sure you want to cancel this leave request?')) {
        $.post('employee_portal_api.php', {
            module: 'employee',
            action: 'cancel_leave',
            leave_id: leaveId
        }, function(response) {
            if (response.success) {
                loadLeaveHistory();
                loadQuickStats();
                showAlert('Leave request cancelled successfully', 'success');
            } else {
                showAlert('Error: ' + response.message, 'danger');
            }
        }, 'json');
    }
}

// Payroll Functions
function loadPayrollInfo() {
    $.post('employee_portal_api.php', {
        module: 'employee',
        action: 'get_payroll_info'
    }, function(response) {
        if (response.success) {
            const payroll = response.data;
            
            // Salary Details
            let salaryHtml = `
                <p><strong>Basic Salary:</strong> ₹${parseFloat(payroll.basic_salary || 0).toLocaleString()}</p>
                <p><strong>Allowances:</strong> ₹${parseFloat(payroll.allowances || 0).toLocaleString()}</p>
                <p><strong>Deductions:</strong> ₹${parseFloat(payroll.deductions || 0).toLocaleString()}</p>
                <hr>
                <p><strong>Net Salary:</strong> ₹${parseFloat(payroll.net_salary || 0).toLocaleString()}</p>
            `;
            $('#salaryDetails').html(salaryHtml);
            
            // Recent Payslips
            let payslipHtml = '';
            if (payroll.recent_payslips && payroll.recent_payslips.length > 0) {
                payroll.recent_payslips.forEach(function(payslip) {
                    payslipHtml += `
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <strong>${payslip.month_year}</strong><br>
                                <small class="text-muted">₹${parseFloat(payslip.earned_salary || payslip.basic_salary || 0).toLocaleString()}</small>
                            </div>
                            <button class="btn btn-sm btn-outline-primary" onclick="downloadPayslip(${payslip.id})">
                                <i class="fas fa-download"></i>
                            </button>
                        </div>
                    `;
                });
            } else {
                payslipHtml = '<p class="text-center text-muted">No payslips available</p>';
            }
            $('#recentPayslips').html(payslipHtml);
        } else {
            console.error('Payroll API Error:', response);
            $('#salaryDetails').html('<p class="text-center text-danger">Error loading salary information</p>');
            $('#recentPayslips').html('<p class="text-center text-danger">Error loading payslip history</p>');
        }
    }, 'json').fail(function(xhr, status, error) {
        console.error('Payroll AJAX Error:', xhr.responseText, status, error);
        $('#salaryDetails').html('<p class="text-center text-danger">Connection error loading salary info</p>');
        $('#recentPayslips').html('<p class="text-center text-danger">Connection error loading payslips</p>');
    });
}

function viewPayslip() {
    // Implementation for viewing current payslip
    showAlert('Payslip view will be implemented', 'info');
}

function downloadPayslip(payslipId) {
    // Implementation for downloading payslip
    showAlert('Payslip download will be implemented', 'info');
}

// Profile Functions
function updateProfile() {
    $.post('employee_portal_api.php', {
        module: 'employee',
        action: 'update_profile',
        phone: $('#profilePhone').val(),
        address: $('#profileAddress').val()
    }, function(response) {
        if (response.success) {
            showAlert('Profile updated successfully', 'success');
        } else {
            showAlert('Error: ' + response.message, 'danger');
        }
    }, 'json');
}

// Advanced Employee Functions
function gpsAttendance(type) {
    if (!navigator.geolocation) {
        showAlert('Geolocation is not supported by this browser', 'error');
        return;
    }
    
    navigator.geolocation.getCurrentPosition(function(position) {
        const latitude = position.coords.latitude;
        const longitude = position.coords.longitude;
        const accuracy = position.coords.accuracy;
        
        $.post('employee_portal_api.php', {
            module: 'employee',
            action: 'gps_attendance',
            punch_type: type,
            latitude: latitude,
            longitude: longitude,
            accuracy: accuracy
        }).done(function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                loadAttendanceStatus();
                updateLocationInfo(latitude, longitude);
            } else {
                showAlert(response.message, 'danger');
            }
        }).fail(function() {
            showAlert('Failed to record GPS attendance', 'danger');
        });
    }, function(error) {
        showAlert('Error getting location: ' + error.message, 'danger');
    });
}

function submitMobileRequest() {
    if (!navigator.geolocation) {
        showAlert('Geolocation is required for mobile requests', 'error');
        return;
    }
    
    navigator.geolocation.getCurrentPosition(function(position) {
        const requestType = $('#mobileRequestType').val();
        const reason = $('#mobileRequestReason').val();
        
        $.post('employee_portal_api.php', {
            module: 'employee',
            action: 'mobile_punch_request',
            request_type: requestType,
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy: position.coords.accuracy,
            reason: reason,
            device_info: {
                userAgent: navigator.userAgent,
                platform: navigator.platform,
                timestamp: new Date().toISOString()
            }
        }).done(function(response) {
            if (response.success) {
                showAlert('Mobile attendance request submitted successfully', 'success');
                $('#mobileRequestReason').val('');
                loadMobileAttendanceHistory();
            } else {
                showAlert(response.message, 'danger');
            }
        });
    });
}

function loadMobileAttendanceHistory() {
    $.post('employee_portal_api.php', {
        module: 'employee',
        action: 'get_mobile_attendance'
    }).done(function(response) {
        if (response.success && response.data) {
            displayMobileAttendanceHistory(response.data);
        } else {
            console.error('Mobile attendance error:', response);
            $('#mobileAttendanceHistory tbody').html('<tr><td colspan="5" class="text-center text-muted">No mobile attendance history found</td></tr>');
        }
    }).fail(function(xhr, status, error) {
        console.error('Mobile attendance AJAX error:', xhr.responseText, status, error);
        $('#mobileAttendanceHistory tbody').html('<tr><td colspan="5" class="text-center text-danger">Error loading mobile attendance history</td></tr>');
    });
}

function displayMobileAttendanceHistory(history) {
    let html = '';
    
    if (history.length === 0) {
        html = '<tr><td colspan="5" class="text-center text-muted">No mobile attendance requests found</td></tr>';
    } else {
        history.forEach(function(record) {
            let statusClass = record.status === 'approved' ? 'success' : 
                            record.status === 'rejected' ? 'danger' : 'warning';
            
            html += `
                <tr>
                    <td>${record.requested_time}</td>
                    <td><span class="badge bg-info">${record.request_type.replace('_', ' ')}</span></td>
                    <td>${record.location_address || 'Location not available'}</td>
                    <td><span class="badge bg-${statusClass}">${record.status.toUpperCase()}</span></td>
                    <td>${record.processed_by || 'Pending'}</td>
                </tr>
            `;
        });
    }
    
    $('#mobileAttendanceHistory tbody').html(html);
}

function loadPayrollPreview() {
    const month = $('#payrollPreviewMonth').val();
    const [year, monthNum] = month.split('-');
    const periodStart = `${year}-${monthNum}-01`;
    const periodEnd = new Date(year, monthNum, 0).toISOString().split('T')[0];
    
    $.post('employee_portal_api.php', {
        module: 'employee',
        action: 'get_payroll_preview',
        period_start: periodStart,
        period_end: periodEnd
    }).done(function(response) {
        if (response.success) {
            displayPayrollPreview(response.data);
        } else {
            console.error('Payroll preview error:', response);
            $('#payrollPreviewContainer').html('<div class="alert alert-danger">Error: ' + (response.message || 'Unknown error') + '</div>');
        }
    }).fail(function(xhr, status, error) {
        console.error('Payroll preview AJAX error:', xhr.responseText, status, error);
        $('#payrollPreviewContainer').html('<div class="alert alert-danger">Connection error loading payroll preview</div>');
    });
}

function displayPayrollPreview(data) {
    let html = `
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0">Earnings</h6>
                    </div>
                    <div class="card-body">
                        <div class="row mb-2">
                            <div class="col-8">Basic Salary</div>
                            <div class="col-4 text-end fw-bold">₹${data.base_salary?.toLocaleString() || 0}</div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-8">Overtime</div>
                            <div class="col-4 text-end fw-bold">₹${data.overtime_amount?.toLocaleString() || 0}</div>
                        </div>
    `;
    
    if (data.allowances) {
        Object.entries(data.allowances).forEach(([key, value]) => {
            html += `
                <div class="row mb-2">
                    <div class="col-8">${key.toUpperCase()}</div>
                    <div class="col-4 text-end fw-bold">₹${value?.toLocaleString() || 0}</div>
                </div>
            `;
        });
    }
    
    html += `
                        <hr>
                        <div class="row">
                            <div class="col-8"><strong>Gross Salary</strong></div>
                            <div class="col-4 text-end fw-bold text-success">₹${data.gross_salary?.toLocaleString() || 0}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h6 class="mb-0">Deductions</h6>
                    </div>
                    <div class="card-body">
    `;
    
    if (data.deductions) {
        Object.entries(data.deductions).forEach(([key, value]) => {
            html += `
                <div class="row mb-2">
                    <div class="col-8">${key.toUpperCase()}</div>
                    <div class="col-4 text-end fw-bold">₹${value?.toLocaleString() || 0}</div>
                </div>
            `;
        });
    }
    
    html += `
                        <hr>
                        <div class="row">
                            <div class="col-8"><strong>Total Deductions</strong></div>
                            <div class="col-4 text-end fw-bold text-danger">₹${data.total_deductions?.toLocaleString() || 0}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h4 class="text-success mb-3">Net Salary: ₹${data.net_salary?.toLocaleString() || 0}</h4>
                        <div class="row">
                            <div class="col-md-3">
                                <small class="text-muted">Working Days</small>
                                <div class="fw-bold">${data.attendance?.working_days || 0}</div>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">Present Days</small>
                                <div class="fw-bold">${data.attendance?.present_days || 0}</div>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">Total Hours</small>
                                <div class="fw-bold">${data.attendance?.total_hours || 0}h</div>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">Overtime Hours</small>
                                <div class="fw-bold">${data.overtime_hours || 0}h</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('#payrollPreviewContainer').html(html);
}

function loadPersonalAnalytics() {
    const period = $('#analyticsFilter').val();
    
    $.post('employee_portal_api.php', {
        module: 'employee',
        action: 'get_attendance_analytics',
        period: period
    }).done(function(response) {
        if (response.success) {
            displayPersonalAnalytics(response.data);
        } else {
            $('#personalAnalyticsContainer').html('<div class="alert alert-danger">' + response.message + '</div>');
        }
    });
}

function displayPersonalAnalytics(data) {
    let html = `
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Attendance Overview</h6>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <div class="h2 text-success">${data.attendance_percentage}%</div>
                            <small class="text-muted">Overall Attendance</small>
                        </div>
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="h5 text-primary">${data.present_days}</div>
                                <small class="text-muted">Present</small>
                            </div>
                            <div class="col-4">
                                <div class="h5 text-danger">${data.absent_days}</div>
                                <small class="text-muted">Absent</small>
                            </div>
                            <div class="col-4">
                                <div class="h5 text-warning">${data.late_days}</div>
                                <small class="text-muted">Late</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Performance Metrics</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>Punctuality Score</span>
                                <strong>${data.punctuality_score}%</strong>
                            </div>
                            <div class="progress mt-1" style="height: 6px;">
                                <div class="progress-bar bg-success" style="width: ${data.punctuality_score}%"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>Avg Hours/Day</span>
                                <strong>${data.average_hours_per_day}h</strong>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>Total Overtime</span>
                                <strong>${data.total_overtime_hours}h</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('#personalAnalyticsContainer').html(html);
}

function loadMySchedule() {
    $.post('employee_portal_api.php', {
        module: 'employee',
        action: 'get_shift_schedule'
    }).done(function(response) {
        if (response.success) {
            displayMySchedule(response.data);
        } else {
            $('#weeklySchedule').html('<div class="alert alert-info">No schedule information available</div>');
            $('#currentShiftInfo').html('<div class="alert alert-info">No current shift assigned</div>');
            $('#upcomingShifts').html('<div class="alert alert-info">No upcoming shifts</div>');
        }
    });
}

function displayMySchedule(schedules) {
    if (schedules.length === 0) {
        $('#weeklySchedule').html('<div class="text-center py-4"><p class="text-muted">No schedule assigned for this week</p></div>');
        $('#currentShiftInfo').html('<div class="text-center py-3"><p class="text-muted">No current shift</p></div>');
        $('#upcomingShifts').html('<div class="text-center py-3"><p class="text-muted">No upcoming shifts</p></div>');
        return;
    }
    
    // Display current/latest shift
    const currentShift = schedules[0];
    let shiftInfo = `
        <div class="text-center">
            <h5 class="text-primary">${currentShift.shift_name}</h5>
            <div class="mb-2">
                <i class="fas fa-clock text-success"></i>
                ${currentShift.start_time} - ${currentShift.end_time}
            </div>
            <div class="text-muted">
                <small>Effective from: ${currentShift.effective_from}</small>
            </div>
        </div>
    `;
    $('#currentShiftInfo').html(shiftInfo);
    
    // Display weekly schedule (placeholder for now)
    let weeklyHtml = `
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Day</th>
                        <th>Shift</th>
                        <th>Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    days.forEach(day => {
        weeklyHtml += `
            <tr>
                <td>${day}</td>
                <td>${currentShift.shift_name}</td>
                <td>${currentShift.start_time} - ${currentShift.end_time}</td>
                <td><span class="badge bg-success">Scheduled</span></td>
            </tr>
        `;
    });
    
    weeklyHtml += '</tbody></table></div>';
    $('#weeklySchedule').html(weeklyHtml);
    
    // Display upcoming shifts
    let upcomingHtml = '<div class="text-muted text-center">Next 7 days following current shift pattern</div>';
    $('#upcomingShifts').html(upcomingHtml);
}

function updateLocationInfo(lat, lng) {
    $('#currentLocation').text(`${lat.toFixed(6)}, ${lng.toFixed(6)}`);
    
    // Calculate distance from office (placeholder coordinates)
    const officeLat = 12.9716;
    const officeLng = 77.5946;
    const distance = calculateDistance(lat, lng, officeLat, officeLng);
    
    $('#distanceFromOffice').text(distance.toFixed(0) + ' meters');
    
    if (distance <= 100) {
        $('#distanceFromOffice').removeClass('text-danger').addClass('text-success');
    } else {
        $('#distanceFromOffice').removeClass('text-success').addClass('text-danger');
    }
}

function calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371e3; // Earth's radius in meters
    const φ1 = lat1 * Math.PI/180;
    const φ2 = lat2 * Math.PI/180;
    const Δφ = (lat2-lat1) * Math.PI/180;
    const Δλ = (lon2-lon1) * Math.PI/180;

    const a = Math.sin(Δφ/2) * Math.sin(Δφ/2) +
              Math.cos(φ1) * Math.cos(φ2) *
              Math.sin(Δλ/2) * Math.sin(Δλ/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));

    return R * c;
}

// Tab event handlers for lazy loading
$('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
    const target = $(e.target).attr('data-bs-target');
    
    switch(target) {
        case '#mobile-attendance':
            loadMobileAttendanceHistory();
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    updateLocationInfo(position.coords.latitude, position.coords.longitude);
                });
            }
            break;
        case '#payroll-preview':
            loadPayrollPreview();
            break;
        case '#analytics':
            // loadPersonalAnalytics(); // Load on button click instead
            break;
        case '#schedule':
            loadMySchedule();
            break;
    }
});

// Set default date for schedule week
$(document).ready(function() {
    const today = new Date();
    const monday = new Date(today.setDate(today.getDate() - today.getDay() + 1));
    $('#scheduleWeek').val(monday.toISOString().split('T')[0]);
});

// Update payroll preview when month changes
$('#payrollPreviewMonth').on('change', function() {
    loadPayrollPreview();
});

// Update analytics when filter changes
$('#analyticsFilter').on('change', function() {
    if ($('#analytics').hasClass('active')) {
        loadPersonalAnalytics();
    }
});

// Utility Functions
function showAlert(message, type) {
    const alertDiv = `
        <div class="alert alert-${type} alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999;">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    $('body').append(alertDiv);
    
    setTimeout(function() {
        $('.alert').fadeOut();
    }, 5000);
}
</script>

<?php include '../../layouts/footer.php'; ?>
