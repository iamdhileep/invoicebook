<?php
// Include performance monitor if requested
if (isset($_GET['perf']) || isset($_COOKIE['perf_monitor'])) {
    require_once '../../includes/performance_monitor.php';
}

session_start();

// Check authentication
if (!isset($_SESSION['user_id']) && !isset($_SESSION['employee_id'])) {
    // Set demo Manager session
    $_SESSION['employee_id'] = 2;
    $_SESSION['user_id'] = 2;
    $_SESSION['role'] = 'manager';
}

// Include optimized database connection and auth check
require_once '../../db_optimized.php';
require_once '../../auth_check.php';

$pageTitle = "Manager Dashboard";

// Initialize statistics
$stats = [
    'team_members' => 0,
    'pending_approvals' => 0,
    'team_present' => 0,
    'performance_score' => 95,
    'pending_tasks' => 0,
    'completed_projects' => 0
];

// Get manager's team data with caching
try {
    $optimizedDB = OptimizedDB::getInstance();
    
    // Team members count (cached)
    $result = $optimizedDB->query("SELECT COUNT(*) as total FROM employees WHERE status = 'active'", [], 'manager_team_count');
    if (is_array($result) && !empty($result)) {
        $stats['team_members'] = $result[0]['total'];
    }
    
    // Pending approvals (leave requests) - cached for 2 minutes
    $table_check = $conn->query("SHOW TABLES LIKE 'leave_requests'");
    if ($table_check && $table_check->num_rows > 0) {
        $result = $optimizedDB->query("SELECT COUNT(*) as total FROM leave_requests WHERE status = 'pending'", [], 'manager_pending_approvals');
        if (is_array($result) && !empty($result)) {
            $stats['pending_approvals'] = $result[0]['total'];
        }
    }
    
    // Team present today (cached for 30 minutes)
    $result = $optimizedDB->query("
        SELECT COUNT(DISTINCT a.employee_id) as total 
        FROM attendance a 
        INNER JOIN employees e ON a.employee_id = e.employee_id 
        WHERE a.attendance_date = CURDATE() AND e.status = 'active'
    ", [], 'manager_team_present');
    if (is_array($result) && !empty($result)) {
        $stats['team_present'] = $result[0]['total'];
    }
    
} catch (Exception $e) {
    error_log("Manager Dashboard database error: " . $e->getMessage());
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
                                <h3 class="mb-0">Manager Dashboard</h3>
                                <p class="text-muted mb-0">Team management and oversight system</p>
                            </div>
                            <div class="col-md-4 text-end">
                                <button class="btn btn-primary" onclick="refreshDashboard()">
                                    <i class="fas fa-sync-alt"></i> Refresh
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dashboard Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6>Team Members</h6>
                                <h3 id="teamMembers">-</h3>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-users fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6>Pending Approvals</h6>
                                <h3 id="pendingApprovals">-</h3>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-clock fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6>Team Present</h6>
                                <h3 id="teamPresent">-</h3>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-check-circle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6>Performance Score</h6>
                                <h3 id="performanceScore">-</h3>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-chart-line fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs" id="managerTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="team-tab" data-bs-toggle="tab" data-bs-target="#team" type="button" role="tab">
                    <i class="fas fa-users"></i> Team Management
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="approvals-tab" data-bs-toggle="tab" data-bs-target="#approvals" type="button" role="tab">
                    <i class="fas fa-check-square"></i> Leave Approvals
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button" role="tab">
                    <i class="fas fa-calendar-check"></i> Team Attendance
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="performance-tab" data-bs-toggle="tab" data-bs-target="#performance" type="button" role="tab">
                    <i class="fas fa-star"></i> Performance Reviews
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports" type="button" role="tab">
                    <i class="fas fa-chart-bar"></i> Team Reports
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="analytics-tab" data-bs-toggle="tab" data-bs-target="#analytics" type="button" role="tab">
                    <i class="fas fa-chart-line"></i> Team Analytics
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="overtime-tab" data-bs-toggle="tab" data-bs-target="#overtime" type="button" role="tab">
                    <i class="fas fa-clock"></i> Overtime Management
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="shifts-tab" data-bs-toggle="tab" data-bs-target="#shifts" type="button" role="tab">
                    <i class="fas fa-calendar-alt"></i> Shift Management
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="anomalies-tab" data-bs-toggle="tab" data-bs-target="#anomalies" type="button" role="tab">
                    <i class="fas fa-exclamation-triangle"></i> Attendance Anomalies
                </button>
            </li>
        </ul>

        <div class="tab-content" id="managerTabContent">
            <!-- Team Management Tab -->
            <div class="tab-pane fade show active" id="team" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Team Overview</h5>
                        <button class="btn btn-primary" onclick="loadTeamMembers()">
                            <i class="fas fa-sync"></i> Refresh Team
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped" id="teamTable">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Position</th>
                                        <th>Status</th>
                                        <th>Today's Status</th>
                                        <th>Performance</th>
                                        <th>Contact</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="teamTableBody">
                                    <tr><td colspan="7" class="text-center">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Leave Approvals Tab -->
            <div class="tab-pane fade" id="approvals" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Team Leave Requests</h5>
                        <select class="form-select d-inline-block w-auto" id="approvalStatusFilter">
                            <option value="pending">Pending Approvals</option>
                            <option value="all">All Requests</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped" id="approvalsTable">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Leave Type</th>
                                        <th>From Date</th>
                                        <th>To Date</th>
                                        <th>Days</th>
                                        <th>Reason</th>
                                        <th>Applied</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="approvalsTableBody">
                                    <tr><td colspan="9" class="text-center">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Team Attendance Tab -->
            <div class="tab-pane fade" id="attendance" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Team Attendance Monitoring</h5>
                        <div>
                            <input type="date" class="form-control d-inline-block w-auto me-2" id="attendanceDate" value="<?php echo date('Y-m-d'); ?>">
                            <button class="btn btn-primary" onclick="loadTeamAttendance()">
                                <i class="fas fa-search"></i> Load
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped" id="attendanceTable">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Date</th>
                                        <th>Check In</th>
                                        <th>Check Out</th>
                                        <th>Work Hours</th>
                                        <th>Status</th>
                                        <th>Late By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="attendanceTableBody">
                                    <tr><td colspan="8" class="text-center">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Reviews Tab -->
            <div class="tab-pane fade" id="performance" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Team Performance Management</h5>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addReviewModal">
                            <i class="fas fa-plus"></i> Add Review
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row" id="performanceCards">
                            <div class="col-12 text-center">
                                <p>Loading performance data...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Team Reports Tab -->
            <div class="tab-pane fade" id="reports" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0">Team Analytics & Reports</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Team Attendance Summary</h6>
                                    </div>
                                    <div class="card-body" id="attendanceReport">
                                        <p class="text-center">Loading...</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Leave Utilization</h6>
                                    </div>
                                    <div class="card-body" id="leaveUtilizationReport">
                                        <p class="text-center">Loading...</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mt-3">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Performance Metrics</h6>
                                    </div>
                                    <div class="card-body" id="performanceMetrics">
                                        <p class="text-center">Loading...</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mt-3">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Team Productivity</h6>
                                    </div>
                                    <div class="card-body" id="productivityReport">
                                        <p class="text-center">Loading...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Team Analytics Tab -->
            <div class="tab-pane fade" id="analytics" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Advanced Team Analytics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <select class="form-select" id="analyticsFilter">
                                    <option value="week">Last 7 Days</option>
                                    <option value="month" selected>Last 30 Days</option>
                                    <option value="quarter">Last 90 Days</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-primary" onclick="loadTeamAnalytics()">
                                    <i class="fas fa-sync me-2"></i>Refresh Analytics
                                </button>
                            </div>
                        </div>
                        <div id="teamAnalyticsContainer">
                            <div class="text-center py-4">
                                <div class="loading-spinner me-2"></div>
                                Loading team analytics...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Overtime Management Tab -->
            <div class="tab-pane fade" id="overtime" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Overtime Management</h5>
                        <button class="btn btn-success" onclick="bulkApproveOvertime()">
                            <i class="fas fa-check-double me-2"></i>Bulk Approve
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="overtimeTable">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="selectAllOvertime"></th>
                                        <th>Employee</th>
                                        <th>Date</th>
                                        <th>Regular Hours</th>
                                        <th>Overtime Hours</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <div class="loading-spinner me-2"></div>
                                            Loading overtime records...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Shift Management Tab -->
            <div class="tab-pane fade" id="shifts" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Team Shift Scheduling</h5>
                        <button class="btn btn-primary" onclick="showShiftScheduleModal()">
                            <i class="fas fa-plus me-2"></i>Schedule Shifts
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Week Starting</label>
                                <input type="date" class="form-control" id="shiftWeekStart" value="">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Shift Template</label>
                                <select class="form-select" id="shiftTemplate">
                                    <option value="">Select Template</option>
                                    <option value="standard">Standard (9 AM - 5 PM)</option>
                                    <option value="early">Early (7 AM - 3 PM)</option>
                                    <option value="late">Late (1 PM - 9 PM)</option>
                                    <option value="night">Night (10 PM - 6 AM)</option>
                                </select>
                            </div>
                        </div>
                        <div id="shiftCalendar">
                            <div class="text-center py-4">
                                <div class="loading-spinner me-2"></div>
                                Loading shift calendar...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Attendance Anomalies Tab -->
            <div class="tab-pane fade" id="anomalies" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Attendance Anomalies & Exceptions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card border-0 bg-warning bg-opacity-10">
                                    <div class="card-body text-center">
                                        <div class="h3 text-warning mb-1" id="lateArrivals">0</div>
                                        <small class="text-muted">Late Arrivals</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-0 bg-danger bg-opacity-10">
                                    <div class="card-body text-center">
                                        <div class="h3 text-danger mb-1" id="earlyDepartures">0</div>
                                        <small class="text-muted">Early Departures</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-0 bg-info bg-opacity-10">
                                    <div class="card-body text-center">
                                        <div class="h3 text-info mb-1" id="longBreaks">0</div>
                                        <small class="text-muted">Extended Breaks</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover" id="anomaliesTable">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Date</th>
                                        <th>Anomaly Type</th>
                                        <th>Details</th>
                                        <th>Severity</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <div class="loading-spinner me-2"></div>
                                            Loading anomalies...
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

<!-- Leave Action Modal -->
<div class="modal fade" id="leaveActionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="leaveActionTitle">Leave Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="leaveActionForm">
                <input type="hidden" name="leave_id" id="actionLeaveId">
                <input type="hidden" name="action_type" id="actionType">
                <div class="modal-body">
                    <div class="mb-3" id="leaveDetailsSection">
                        <!-- Leave details will be populated here -->
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Manager Comments</label>
                        <textarea class="form-control" name="manager_comments" rows="3" placeholder="Add your comments for this decision..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" id="leaveActionBtn">Action</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Performance Review Modal -->
<div class="modal fade" id="addReviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Performance Review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addReviewForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Employee *</label>
                                <select class="form-select" name="employee_id" required id="reviewEmployee">
                                    <option value="">Select Employee</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Review Period *</label>
                                <select class="form-select" name="review_period" required>
                                    <option value="">Select Period</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="quarterly">Quarterly</option>
                                    <option value="annual">Annual</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Technical Skills (1-5)</label>
                                <select class="form-select" name="technical_rating">
                                    <option value="1">1 - Poor</option>
                                    <option value="2">2 - Below Average</option>
                                    <option value="3" selected>3 - Average</option>
                                    <option value="4">4 - Good</option>
                                    <option value="5">5 - Excellent</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Communication (1-5)</label>
                                <select class="form-select" name="communication_rating">
                                    <option value="1">1 - Poor</option>
                                    <option value="2">2 - Below Average</option>
                                    <option value="3" selected>3 - Average</option>
                                    <option value="4">4 - Good</option>
                                    <option value="5">5 - Excellent</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Teamwork (1-5)</label>
                                <select class="form-select" name="teamwork_rating">
                                    <option value="1">1 - Poor</option>
                                    <option value="2">2 - Below Average</option>
                                    <option value="3" selected>3 - Average</option>
                                    <option value="4">4 - Good</option>
                                    <option value="5">5 - Excellent</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Achievements</label>
                        <textarea class="form-control" name="achievements" rows="3" placeholder="Key achievements during this period..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Areas for Improvement</label>
                        <textarea class="form-control" name="improvement_areas" rows="3" placeholder="Areas where employee can improve..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Goals for Next Period</label>
                        <textarea class="form-control" name="next_goals" rows="3" placeholder="Goals and objectives for next review period..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Review</button>
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
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
}
.nav-tabs .nav-link {
    color: #495057;
    border: none;
    background: none;
    padding: 12px 20px;
}
.nav-tabs .nav-link.active {
    background: #28a745;
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
.btn-action {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}
.performance-card {
    border-left: 4px solid #28a745;
    transition: transform 0.2s;
}
.performance-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.rating-stars {
    color: #ffc107;
}

.loading-spinner {
    display: inline-block;
    width: 14px;
    height: 14px;
    border: 2px solid rgba(0,0,0,.3);
    border-radius: 50%;
    border-top-color: #007bff;
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.overtime-checkbox {
    cursor: pointer;
}

#selectAllOvertime {
    cursor: pointer;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
$(document).ready(function() {
    loadDashboardData();
    
    // Tab change events
    $('#managerTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        const target = e.target.getAttribute('data-bs-target');
        if (target === '#attendance') {
            loadTeamAttendance();
        } else if (target === '#performance') {
            loadPerformanceReviews();
        } else if (target === '#approvals') {
            loadTeamLeaveRequests();
        } else if (target === '#reports') {
            loadTeamReports();
        }
    });
    
    // Leave status filter
    $('#approvalStatusFilter').change(function() {
        loadTeamLeaveRequests();
    });
});

// Initialize dashboard data
function loadDashboardData() {
    loadDashboardStats();
    loadTeamMembers();
    loadTeamLeaveRequests();
    loadTeamReports();
}

function refreshDashboard() {
    loadDashboardData();
    showAlert('Dashboard refreshed successfully', 'success');
}

// Dashboard Statistics
function loadDashboardStats() {
    $.ajax({
        url: 'manager_dashboard_api.php',
        method: 'POST',
        data: { action: 'get_dashboard_stats' },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const stats = response.data;
                $('#teamMembers').text(stats.team_members || 0);
                $('#pendingApprovals').text(stats.pending_approvals || 0);
                $('#teamPresent').text(stats.team_present || 0);
                $('#performanceScore').text((stats.performance_score || 0) + '%');
            } else {
                console.error('Failed to load dashboard stats:', response.message);
            }
        },
        error: function() {
            console.error('Error loading dashboard stats');
        }
    });
}

// Team Members
function loadTeamMembers() {
    $.ajax({
        url: 'manager_dashboard_api.php',
        method: 'POST',
        data: { action: 'get_team_members' },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayTeamMembers(response.data);
            } else {
                $('#teamTableBody').html('<tr><td colspan="7" class="text-center text-danger">Error loading team members</td></tr>');
            }
        },
        error: function() {
            $('#teamTableBody').html('<tr><td colspan="7" class="text-center text-danger">Failed to load team data</td></tr>');
        }
    });
}

function displayTeamMembers(members) {
    let html = '';
    
    if (members.length === 0) {
        html = '<tr><td colspan="7" class="text-center">No team members found</td></tr>';
    } else {
        members.forEach(function(member) {
            const statusBadge = member.status === 'active' ? 'bg-success' : 'bg-secondary';
            let attendanceBadge = 'bg-danger';
            
            if (member.today_status === 'present') attendanceBadge = 'bg-success';
            else if (member.today_status === 'late') attendanceBadge = 'bg-warning';
            
            const performanceStars = generateStars(member.performance_rating || 0);
            
            html += `
                <tr>
                    <td>
                        <div>
                            <strong>${member.name}</strong><br>
                            <small class="text-muted">${member.employee_code}</small>
                        </div>
                    </td>
                    <td>${member.position || '-'}</td>
                    <td><span class="badge ${statusBadge}">${member.status}</span></td>
                    <td><span class="badge ${attendanceBadge}">${member.today_status || 'absent'}</span></td>
                    <td>${performanceStars}</td>
                    <td>${member.phone || '-'}</td>
                    <td>
                        <button class="btn btn-sm btn-info btn-action" onclick="viewMemberDetails(${member.employee_id})">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-warning btn-action" onclick="reviewMember(${member.employee_id})">
                            <i class="fas fa-star"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
        
        // Store team data for performance review modal
        populateReviewEmployeeSelect(members);
    }
    
    $('#teamTableBody').html(html);
}

function generateStars(rating) {
    let stars = '';
    for (let i = 1; i <= 5; i++) {
        if (i <= rating) {
            stars += '<i class="fas fa-star rating-stars"></i>';
        } else {
            stars += '<i class="far fa-star rating-stars"></i>';
        }
    }
    return stars;
}

function populateReviewEmployeeSelect(teamMembers) {
    let options = '<option value="">Select Employee</option>';
    teamMembers.forEach(function(member) {
        options += `<option value="${member.employee_id}">${member.name} (${member.employee_code})</option>`;
    });
    $('#reviewEmployee').html(options);
}

// Team Attendance
function loadTeamAttendance() {
    const date = $('#attendanceDate').val();
    
    $.ajax({
        url: 'manager_dashboard_api.php',
        method: 'POST',
        data: { 
            action: 'get_team_attendance',
            date: date 
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayTeamAttendance(response.data);
            } else {
                $('#attendanceTableBody').html('<tr><td colspan="8" class="text-center text-danger">Error loading attendance</td></tr>');
            }
        },
        error: function() {
            $('#attendanceTableBody').html('<tr><td colspan="8" class="text-center text-danger">Failed to load attendance data</td></tr>');
        }
    });
}

function displayTeamAttendance(attendance) {
    let html = '';
    
    if (attendance.length === 0) {
        html = '<tr><td colspan="8" class="text-center">No attendance records found</td></tr>';
    } else {
        attendance.forEach(function(record) {
            const duration = record.work_duration ? parseFloat(record.work_duration).toFixed(2) + ' hrs' : '-';
            const lateBy = record.late_minutes ? record.late_minutes + ' min' : '-';
            let statusClass = 'bg-danger';
            
            if (record.status === 'present') statusClass = 'bg-success';
            else if (record.status === 'late') statusClass = 'bg-warning';
            
            html += `
                <tr>
                    <td>${record.employee_name}</td>
                    <td>${record.attendance_date}</td>
                    <td>${record.punch_in_time || '-'}</td>
                    <td>${record.punch_out_time || '-'}</td>
                    <td>${duration}</td>
                    <td><span class="badge ${statusClass}">${record.status}</span></td>
                    <td>${lateBy}</td>
                    <td>
                        <button class="btn btn-sm btn-info btn-action" onclick="viewAttendanceDetail(${record.id})">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
    }
    
    $('#attendanceTableBody').html(html);
}

// Team Leave Requests
function loadTeamLeaveRequests() {
    const status = $('#approvalStatusFilter').val();
    
    $.ajax({
        url: 'manager_dashboard_api.php',
        method: 'POST',
        data: { 
            action: 'get_team_leave_requests',
            status: status 
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayTeamLeaveRequests(response.data);
            } else {
                $('#approvalsTableBody').html('<tr><td colspan="9" class="text-center text-danger">Error loading leave requests</td></tr>');
            }
        },
        error: function() {
            $('#approvalsTableBody').html('<tr><td colspan="9" class="text-center text-danger">Failed to load leave requests</td></tr>');
        }
    });
}

function displayTeamLeaveRequests(requests) {
    let html = '';
    
    if (requests.length === 0) {
        html = '<tr><td colspan="9" class="text-center">No leave requests found</td></tr>';
    } else {
        requests.forEach(function(leave) {
            let statusClass = 'bg-secondary';
            if (leave.status === 'pending') statusClass = 'bg-warning';
            else if (leave.status === 'approved') statusClass = 'bg-success';
            else if (leave.status === 'rejected') statusClass = 'bg-danger';
            
            const actionButtons = leave.status === 'pending' ? `
                <button class="btn btn-sm btn-success btn-action" onclick="approveLeave(${leave.id}, '${leave.employee_name}', '${leave.leave_type}', '${leave.from_date}', '${leave.to_date}')">
                    <i class="fas fa-check"></i>
                </button>
                <button class="btn btn-sm btn-danger btn-action" onclick="rejectLeave(${leave.id}, '${leave.employee_name}', '${leave.leave_type}', '${leave.from_date}', '${leave.to_date}')">
                    <i class="fas fa-times"></i>
                </button>
            ` : '-';
            
            html += `
                <tr>
                    <td>${leave.employee_name}</td>
                    <td>${leave.leave_type}</td>
                    <td>${leave.from_date}</td>
                    <td>${leave.to_date}</td>
                    <td>${leave.days_requested}</td>
                    <td title="${leave.reason}">${leave.reason.length > 30 ? leave.reason.substring(0, 30) + '...' : leave.reason}</td>
                    <td>${new Date(leave.applied_date).toLocaleDateString()}</td>
                    <td><span class="badge ${statusClass}">${leave.status}</span></td>
                    <td>${actionButtons}</td>
                </tr>
            `;
        });
    }
    
    $('#approvalsTableBody').html(html);
}

// Team Reports
function loadTeamReports() {
    $.ajax({
        url: 'manager_dashboard_api.php',
        method: 'POST',
        data: { action: 'get_team_reports' },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayTeamReports(response.data);
            } else {
                console.error('Failed to load team reports:', response.message);
            }
        },
        error: function() {
            console.error('Error loading team reports');
        }
    });
}

function displayTeamReports(reports) {
    // Attendance Report
    let attendanceHtml = `
        <p><strong>Team Size:</strong> ${reports.attendance.total_members || 0}</p>
        <p><strong>Average Attendance:</strong> ${reports.attendance.avg_attendance || 0}%</p>
        <p><strong>On Time Rate:</strong> ${reports.attendance.on_time_rate || 0}%</p>
    `;
    $('#attendanceReport').html(attendanceHtml);
    
    // Leave Utilization Report
    let leaveHtml = `
        <p><strong>Pending Requests:</strong> ${reports.leaves.pending || 0}</p>
        <p><strong>Approved This Month:</strong> ${reports.leaves.approved || 0}</p>
        <p><strong>Average Leave Days:</strong> ${reports.leaves.avg_days || 0}</p>
    `;
    $('#leaveUtilizationReport').html(leaveHtml);
    
    // Performance Metrics
    let performanceHtml = `
        <p><strong>Reviews Completed:</strong> ${reports.performance.total_reviews || 0}</p>
        <p><strong>Average Rating:</strong> ${reports.performance.avg_rating || 0}/5</p>
        <p><strong>Top Performers:</strong> ${reports.performance.top_performers || 0}</p>
    `;
    $('#performanceMetrics').html(performanceHtml);
    
    // Productivity Report
    let productivityHtml = `
        <p><strong>Team Productivity:</strong> ${reports.productivity.score || 0}%</p>
        <p><strong>Work Hours This Month:</strong> ${reports.productivity.total_hours || 0}</p>
        <p><strong>Average Daily Hours:</strong> ${reports.productivity.avg_daily || 0}</p>
    `;
    $('#productivityReport').html(productivityHtml);
}

// Performance Reviews
function loadPerformanceReviews() {
    $.ajax({
        url: 'manager_dashboard_api.php',
        method: 'POST',
        data: { action: 'get_performance_reviews' },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayPerformanceReviews(response.data);
            } else {
                $('#performanceCards').html('<div class="col-12 text-center text-danger">Error loading performance reviews</div>');
            }
        },
        error: function() {
            $('#performanceCards').html('<div class="col-12 text-center text-danger">Failed to load performance data</div>');
        }
    });
}

function displayPerformanceReviews(reviews) {
    let html = '';
    
    if (reviews.length === 0) {
        html = '<div class="col-12 text-center"><p>No performance reviews found. Start by adding a review!</p></div>';
    } else {
        reviews.forEach(function(review) {
            const avgRating = ((parseInt(review.technical_rating || 0) + 
                               parseInt(review.communication_rating || 0) + 
                               parseInt(review.teamwork_rating || 0)) / 3).toFixed(1);
            
            html += `
                <div class="col-md-6 mb-3">
                    <div class="card performance-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-title">${review.employee_name}</h6>
                                    <p class="card-text">
                                        <small class="text-muted">${review.review_period} review</small><br>
                                        <strong>Average Rating: ${avgRating}/5</strong>
                                    </p>
                                    <p class="card-text">${review.achievements || 'No achievements recorded'}</p>
                                </div>
                                <div>
                                    ${generateStars(avgRating)}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
    }
    
    $('#performanceCards').html(html);
}

// Leave Actions
function approveLeave(leaveId, employeeName, leaveType, fromDate, toDate) {
    $('#actionLeaveId').val(leaveId);
    $('#actionType').val('approve');
    $('#leaveActionTitle').text('Approve Leave Request');
    $('#leaveActionBtn').removeClass('btn-danger').addClass('btn-success').text('Approve');
    
    $('#leaveDetailsSection').html(`
        <div class="alert alert-info">
            <strong>Employee:</strong> ${employeeName}<br>
            <strong>Leave Type:</strong> ${leaveType}<br>
            <strong>Period:</strong> ${fromDate} to ${toDate}
        </div>
    `);
    
    $('#leaveActionModal').modal('show');
}

function rejectLeave(leaveId, employeeName, leaveType, fromDate, toDate) {
    $('#actionLeaveId').val(leaveId);
    $('#actionType').val('reject');
    $('#leaveActionTitle').text('Reject Leave Request');
    $('#leaveActionBtn').removeClass('btn-success').addClass('btn-danger').text('Reject');
    
    $('#leaveDetailsSection').html(`
        <div class="alert alert-warning">
            <strong>Employee:</strong> ${employeeName}<br>
            <strong>Leave Type:</strong> ${leaveType}<br>
            <strong>Period:</strong> ${fromDate} to ${toDate}
        </div>
    `);
    
    $('#leaveActionModal').modal('show');
}

// Form Submissions
$('#leaveActionForm').submit(function(e) {
    e.preventDefault();
    
    const actionType = $('#actionType').val();
    const action = actionType === 'approve' ? 'approve_leave' : 'reject_leave';
    
    $.ajax({
        url: 'manager_dashboard_api.php',
        method: 'POST',
        data: {
            action: action,
            leave_id: $('#actionLeaveId').val(),
            manager_comments: $('textarea[name="manager_comments"]').val()
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#leaveActionModal').modal('hide');
                loadTeamLeaveRequests();
                loadDashboardStats();
                showAlert(response.message, 'success');
            } else {
                showAlert('Error: ' + response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Failed to process leave request', 'danger');
        }
    });
});

$('#addReviewForm').submit(function(e) {
    e.preventDefault();
    
    // Debug: log form data and check if employee is selected
    const formData = $(this).serialize();
    const employeeId = $('#reviewEmployee').val();
    console.log('Form data:', formData);
    console.log('Selected employee ID:', employeeId);
    
    if (!employeeId) {
        showAlert('Please select an employee for the review', 'warning');
        return;
    }
    
    $.ajax({
        url: './manager_dashboard_api.php',
        method: 'POST',
        data: formData + '&action=add_performance_review',
        dataType: 'json',
        success: function(response) {
            console.log('API Response:', response);
            if (response.success) {
                $('#addReviewModal').modal('hide');
                $('#addReviewForm')[0].reset();
                loadPerformanceReviews();
                showAlert(response.message, 'success');
            } else {
                showAlert('Error: ' + response.message, 'danger');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', xhr.responseText, status, error);
            console.error('Status:', status);
            console.error('Error:', error);
            showAlert('Failed to add performance review. Error: ' + (xhr.responseText || error), 'danger');
        }
    });
});

// Utility Functions
function viewMemberDetails(employeeId) {
    showAlert('Member details view will be implemented', 'info');
}

function reviewMember(employeeId) {
    $('#reviewEmployee').val(employeeId);
    $('#addReviewModal').modal('show');
}

function viewAttendanceDetail(attendanceId) {
    showAlert('Attendance detail view will be implemented', 'info');
}

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

// Team Analytics Functions
function loadTeamAnalytics() {
    const period = $('#analyticsFilter').val();
    
    $.ajax({
        url: 'manager_dashboard_api.php',
        method: 'POST',
        data: { 
            action: 'get_team_analytics',
            period: period 
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayTeamAnalytics(response.data);
            } else {
                $('#teamAnalyticsContainer').html('<div class="text-center text-danger py-4">Error loading analytics</div>');
            }
        },
        error: function() {
            $('#teamAnalyticsContainer').html('<div class="text-center text-danger py-4">Failed to load team analytics</div>');
        }
    });
}

function displayTeamAnalytics(analytics) {
    let html = `
        <div class="row">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-primary">${analytics.productivity_score}%</h4>
                        <small>Productivity Score</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-success">${analytics.efficiency_rating}/5</h4>
                        <small>Efficiency Rating</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-info">${analytics.team_satisfaction}/10</h4>
                        <small>Team Satisfaction</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-warning">${analytics.attendance_trend.length}</h4>
                        <small>Data Points</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Attendance Trend</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="attendanceChart" height="100"></canvas>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('#teamAnalyticsContainer').html(html);
}

// Overtime Management Functions
function loadOvertimeRecords() {
    $.ajax({
        url: 'manager_dashboard_api.php',
        method: 'POST',
        data: { action: 'get_overtime_records' },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayOvertimeRecords(response.data);
            } else {
                $('#overtimeTable tbody').html('<tr><td colspan="7" class="text-center text-danger">Error loading overtime records</td></tr>');
            }
        },
        error: function() {
            $('#overtimeTable tbody').html('<tr><td colspan="7" class="text-center text-danger">Failed to load overtime records</td></tr>');
        }
    });
}

function displayOvertimeRecords(records) {
    let html = '';
    
    if (records.length === 0) {
        html = '<tr><td colspan="7" class="text-center">No overtime records found</td></tr>';
    } else {
        records.forEach(function(record) {
            const statusClass = record.status === 'approved' ? 'bg-success' : 'bg-warning';
            html += `
                <tr>
                    <td><input type="checkbox" class="overtime-checkbox" value="${record.id}"></td>
                    <td>${record.employee_name}</td>
                    <td>${record.date}</td>
                    <td>${record.regular_hours} hrs</td>
                    <td>${record.overtime_hours} hrs</td>
                    <td><span class="badge ${statusClass}">${record.status}</span></td>
                    <td>
                        ${record.status === 'pending' ? `
                            <button class="btn btn-sm btn-success" onclick="approveOvertime(${record.id})">
                                <i class="fas fa-check"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="rejectOvertime(${record.id})">
                                <i class="fas fa-times"></i>
                            </button>
                        ` : '-'}
                    </td>
                </tr>
            `;
        });
    }
    
    $('#overtimeTable tbody').html(html);
}

function approveOvertime(overtimeId) {
    $.ajax({
        url: 'manager_dashboard_api.php',
        method: 'POST',
        data: { 
            action: 'approve_overtime',
            overtime_id: overtimeId 
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                loadOvertimeRecords();
                showAlert(response.message, 'success');
            } else {
                showAlert('Error: ' + response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Failed to approve overtime', 'danger');
        }
    });
}

function bulkApproveOvertime() {
    const selectedIds = [];
    $('.overtime-checkbox:checked').each(function() {
        selectedIds.push($(this).val());
    });
    
    if (selectedIds.length === 0) {
        showAlert('Please select overtime records to approve', 'warning');
        return;
    }
    
    $.ajax({
        url: 'manager_dashboard_api.php',
        method: 'POST',
        data: { 
            action: 'bulk_approve_overtime',
            overtime_ids: selectedIds 
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                loadOvertimeRecords();
                showAlert(response.message, 'success');
            } else {
                showAlert('Error: ' + response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Failed to bulk approve overtime', 'danger');
        }
    });
}

// Shift Management Functions
function loadShiftSchedule() {
    const weekStart = $('#shiftWeekStart').val();
    
    $.ajax({
        url: 'manager_dashboard_api.php',
        method: 'POST',
        data: { 
            action: 'get_shift_schedule',
            week_start: weekStart 
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayShiftSchedule(response.data);
            } else {
                $('#shiftCalendar').html('<div class="text-center text-danger py-4">Error loading shift schedule</div>');
            }
        },
        error: function() {
            $('#shiftCalendar').html('<div class="text-center text-danger py-4">Failed to load shift schedule</div>');
        }
    });
}

function displayShiftSchedule(schedule) {
    let html = `
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Monday</th>
                        <th>Tuesday</th>
                        <th>Wednesday</th>
                        <th>Thursday</th>
                        <th>Friday</th>
                        <th>Saturday</th>
                        <th>Sunday</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    schedule.shifts.forEach(function(shift) {
        html += `
            <tr>
                <td><strong>${shift.employee_name}</strong></td>
                <td>${shift.monday}</td>
                <td>${shift.tuesday}</td>
                <td>${shift.wednesday}</td>
                <td>${shift.thursday}</td>
                <td>${shift.friday}</td>
                <td>${shift.saturday}</td>
                <td>${shift.sunday}</td>
            </tr>
        `;
    });
    
    html += '</tbody></table></div>';
    $('#shiftCalendar').html(html);
}

function showShiftScheduleModal() {
    showAlert('Shift scheduling modal will be implemented', 'info');
}

// Attendance Anomalies Functions
function loadAttendanceAnomalies() {
    $.ajax({
        url: 'manager_dashboard_api.php',
        method: 'POST',
        data: { action: 'get_attendance_anomalies' },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayAttendanceAnomalies(response.data);
            } else {
                $('#anomaliesTable tbody').html('<tr><td colspan="6" class="text-center text-danger">Error loading anomalies</td></tr>');
            }
        },
        error: function() {
            $('#anomaliesTable tbody').html('<tr><td colspan="6" class="text-center text-danger">Failed to load anomalies</td></tr>');
        }
    });
}

function displayAttendanceAnomalies(data) {
    // Update counters
    $('#lateArrivals').text(data.counts.late_arrivals);
    $('#earlyDepartures').text(data.counts.early_departures);
    $('#longBreaks').text(data.counts.long_breaks);
    
    // Display anomalies table
    let html = '';
    
    if (data.anomalies.length === 0) {
        html = '<tr><td colspan="6" class="text-center">No anomalies found</td></tr>';
    } else {
        data.anomalies.forEach(function(anomaly) {
            let severityClass = 'bg-info';
            if (anomaly.severity === 'high') severityClass = 'bg-danger';
            else if (anomaly.severity === 'medium') severityClass = 'bg-warning';
            
            html += `
                <tr>
                    <td>${anomaly.employee_name}</td>
                    <td>${anomaly.date}</td>
                    <td>${anomaly.type}</td>
                    <td>${anomaly.details}</td>
                    <td><span class="badge ${severityClass}">${anomaly.severity}</span></td>
                    <td>
                        <button class="btn btn-sm btn-info" onclick="viewAnomalyDetail('${anomaly.employee_name}', '${anomaly.date}')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
    }
    
    $('#anomaliesTable tbody').html(html);
}

function viewAnomalyDetail(employeeName, date) {
    showAlert(`Viewing anomaly detail for ${employeeName} on ${date}`, 'info');
}

// Initialize new tab functions when tabs are shown
$('#managerTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
    const target = e.target.getAttribute('data-bs-target');
    
    if (target === '#attendance') {
        loadTeamAttendance();
    } else if (target === '#performance') {
        loadPerformanceReviews();
    } else if (target === '#analytics') {
        loadTeamAnalytics();
    } else if (target === '#overtime') {
        loadOvertimeRecords();
    } else if (target === '#shifts') {
        loadShiftSchedule();
        // Set default week start to current Monday
        $('#shiftWeekStart').val(getMonday(new Date()).toISOString().split('T')[0]);
    } else if (target === '#anomalies') {
        loadAttendanceAnomalies();
    }
});

// Helper function to get Monday of current week
function getMonday(date) {
    const d = new Date(date);
    const day = d.getDay();
    const diff = d.getDate() - day + (day === 0 ? -6 : 1);
    return new Date(d.setDate(diff));
}
</script>

<?php include '../../layouts/footer.php'; ?>
