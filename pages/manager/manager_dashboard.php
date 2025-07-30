<?php
// Redirect to the new working manager dashboard
header('Location: manager_dashboard_new.php');
exit;
?>
include '../../layouts/sidebar.php';
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
        <div class="row mb-4" id="dashboardStats">
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
                        <div>
                            <button class="btn btn-primary" onclick="loadTeamMembers()">
                                <i class="fas fa-sync"></i> Refresh Team
                            </button>
                        </div>
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
                        <div>
                            <select class="form-select d-inline-block w-auto" id="approvalStatusFilter">
                                <option value="pending">Pending Approvals</option>
                                <option value="all">All Requests</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
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
                        <div class="row" id="teamReports">
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
                                    <option value="3">3 - Average</option>
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
                                    <option value="3">3 - Average</option>
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
                                    <option value="3">3 - Average</option>
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
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
$(document).ready(function() {
    loadDashboardStats();
    loadTeamMembers();
    loadTeamLeaveRequests();
    loadTeamReports();
    
    // Tab change events
    $('#managerTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        const target = e.target.getAttribute('data-bs-target');
        if (target === '#attendance') {
            loadTeamAttendance();
        } else if (target === '#performance') {
            loadPerformanceReviews();
        }
    });
    
    // Leave status filter
    $('#approvalStatusFilter').change(function() {
        loadTeamLeaveRequests($(this).val());
    });
});

// Dashboard Functions
function refreshDashboard() {
    loadDashboardStats();
    loadTeamMembers();
    loadTeamLeaveRequests();
    loadTeamReports();
    showAlert('Dashboard refreshed successfully', 'success');
}

function loadDashboardStats() {
    $.post('../../api/global_hrms_api.php', {
        module: 'manager',
        action: 'get_dashboard_stats'
    }, function(response) {
        if (response.success) {
            const stats = response.data;
            $('#teamMembers').text(stats.team_members || 0);
            $('#pendingApprovals').text(stats.pending_approvals || 0);
            $('#teamPresent').text(stats.team_present || 0);
            $('#performanceScore').text((stats.performance_score || 0) + '%');
        }
    }, 'json');
}

// Team Management Functions
function loadTeamMembers() {
    $.post('../../api/global_hrms_api.php', {
        module: 'manager',
        action: 'get_team_members'
    }, function(response) {
        if (response.success) {
            let html = '';
            response.data.forEach(function(member) {
                const statusBadge = member.status === 'active' ? 'bg-success' : 'bg-secondary';
                const attendanceBadge = member.today_status === 'present' ? 'bg-success' : 
                                       member.today_status === 'late' ? 'bg-warning' : 'bg-danger';
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
            $('#teamTableBody').html(html || '<tr><td colspan="7" class="text-center">No team members found</td></tr>');
            
            // Store team data for performance review modal
            sessionStorage.setItem('teamMembers', JSON.stringify(response.data));
            populateReviewEmployeeSelect(response.data);
        } else {
            $('#teamTableBody').html('<tr><td colspan="7" class="text-center text-danger">Error loading team members</td></tr>');
        }
    }, 'json');
}

function populateReviewEmployeeSelect(teamMembers) {
    let options = '<option value="">Select Employee</option>';
    teamMembers.forEach(function(member) {
        options += `<option value="${member.employee_id}">${member.name} (${member.employee_code})</option>`;
    });
    $('#reviewEmployee').html(options);
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

function viewMemberDetails(employeeId) {
    // Implementation for viewing member details
    showAlert('Member details view will be implemented', 'info');
}

function reviewMember(employeeId) {
    $('#reviewEmployee').val(employeeId);
    $('#addReviewModal').modal('show');
}

// Leave Approval Functions
function loadTeamLeaveRequests(status = 'pending') {
    $.post('../../api/global_hrms_api.php', {
        module: 'manager',
        action: 'get_team_leave_requests',
        status: status
    }, function(response) {
        if (response.success) {
            let html = '';
            response.data.forEach(function(leave) {
                const statusClass = {
                    'pending': 'bg-warning',
                    'approved': 'bg-success',
                    'rejected': 'bg-danger'
                }[leave.status] || 'bg-secondary';
                
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
            $('#approvalsTableBody').html(html || '<tr><td colspan="9" class="text-center">No leave requests found</td></tr>');
        } else {
            $('#approvalsTableBody').html('<tr><td colspan="9" class="text-center text-danger">Error loading leave requests</td></tr>');
        }
    }, 'json');
}

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

$('#leaveActionForm').submit(function(e) {
    e.preventDefault();
    
    const actionType = $('#actionType').val();
    const action = actionType === 'approve' ? 'approve_leave' : 'reject_leave';
    
    $.post('../../api/global_hrms_api.php', {
        module: 'manager',
        action: action,
        leave_id: $('#actionLeaveId').val(),
        manager_comments: $('textarea[name="manager_comments"]').val()
    }, function(response) {
        if (response.success) {
            $('#leaveActionModal').modal('hide');
            loadTeamLeaveRequests($('#approvalStatusFilter').val());
            loadDashboardStats();
            showAlert('Leave request ' + actionType + 'd successfully', 'success');
        } else {
            showAlert('Error: ' + response.message, 'danger');
        }
    }, 'json');
});

// Attendance Functions
function loadTeamAttendance() {
    const date = $('#attendanceDate').val();
    
    $.post('../../api/global_hrms_api.php', {
        module: 'manager',
        action: 'get_team_attendance',
        date: date
    }, function(response) {
        if (response.success) {
            let html = '';
            response.data.forEach(function(attendance) {
                const duration = attendance.work_duration ? parseFloat(attendance.work_duration).toFixed(2) + ' hrs' : '-';
                const lateBy = attendance.late_minutes ? attendance.late_minutes + ' min' : '-';
                const statusClass = attendance.status === 'present' ? 'bg-success' : 
                                   attendance.status === 'late' ? 'bg-warning' : 'bg-danger';
                
                html += `
                    <tr>
                        <td>${attendance.employee_name}</td>
                        <td>${attendance.attendance_date}</td>
                        <td>${attendance.punch_in_time || '-'}</td>
                        <td>${attendance.punch_out_time || '-'}</td>
                        <td>${duration}</td>
                        <td><span class="badge ${statusClass}">${attendance.status}</span></td>
                        <td>${lateBy}</td>
                        <td>
                            <button class="btn btn-sm btn-info btn-action" onclick="viewAttendanceDetail(${attendance.id})">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            $('#attendanceTableBody').html(html || '<tr><td colspan="8" class="text-center">No attendance records found</td></tr>');
        } else {
            $('#attendanceTableBody').html('<tr><td colspan="8" class="text-center text-danger">Error loading attendance</td></tr>');
        }
    }, 'json');
}

function viewAttendanceDetail(attendanceId) {
    // Implementation for viewing attendance details
    showAlert('Attendance detail view will be implemented', 'info');
}

// Performance Review Functions
function loadPerformanceReviews() {
    $.post('../../api/global_hrms_api.php', {
        module: 'manager',
        action: 'get_performance_reviews'
    }, function(response) {
        if (response.success) {
            let html = '';
            if (response.data.length === 0) {
                html = '<div class="col-12 text-center"><p>No performance reviews found. Start by adding a review!</p></div>';
            } else {
                response.data.forEach(function(review) {
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
                                            <p class="text-muted small">${review.review_period} Review</p>
                                        </div>
                                        <div class="text-end">
                                            <div class="h4 text-primary">${avgRating}/5</div>
                                            <div>${generateStars(avgRating)}</div>
                                        </div>
                                    </div>
                                    <div class="row mt-3">
                                        <div class="col-4 text-center">
                                            <small class="text-muted">Technical</small>
                                            <div class="fw-bold">${review.technical_rating || 0}/5</div>
                                        </div>
                                        <div class="col-4 text-center">
                                            <small class="text-muted">Communication</small>
                                            <div class="fw-bold">${review.communication_rating || 0}/5</div>
                                        </div>
                                        <div class="col-4 text-center">
                                            <small class="text-muted">Teamwork</small>
                                            <div class="fw-bold">${review.teamwork_rating || 0}/5</div>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <small class="text-muted">Review Date: ${new Date(review.review_date).toLocaleDateString()}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });
            }
            $('#performanceCards').html(html);
        }
    }, 'json');
}

$('#addReviewForm').submit(function(e) {
    e.preventDefault();
    
    $.post('../../api/global_hrms_api.php', {
        module: 'manager',
        action: 'add_performance_review',
        ...Object.fromEntries(new FormData(this))
    }, function(response) {
        if (response.success) {
            $('#addReviewModal').modal('hide');
            $('#addReviewForm')[0].reset();
            loadPerformanceReviews();
            showAlert('Performance review added successfully', 'success');
        } else {
            showAlert('Error: ' + response.message, 'danger');
        }
    }, 'json');
});

// Reports Functions
function loadTeamReports() {
    $.post('../../api/global_hrms_api.php', {
        module: 'manager',
        action: 'get_team_reports'
    }, function(response) {
        if (response.success) {
            const reports = response.data;
            
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
    }, 'json');
}

// Advanced Manager Functions
function loadTeamAnalytics() {
    const period = $('#analyticsFilter').val();
    
    $.post('../../api/global_hrms_api.php', {
        module: 'manager',
        action: 'get_team_analytics',
        period: period
    }).done(function(response) {
        if (response.success) {
            displayTeamAnalytics(response.data);
        } else {
            $('#teamAnalyticsContainer').html('<div class="alert alert-danger">' + response.message + '</div>');
        }
    }).fail(function() {
        $('#teamAnalyticsContainer').html('<div class="alert alert-danger">Failed to load analytics</div>');
    });
}

function displayTeamAnalytics(data) {
    let html = '<div class="row g-4">';
    
    if (data.length === 0) {
        html += '<div class="col-12 text-center"><p class="text-muted">No analytics data available</p></div>';
    } else {
        data.forEach(function(employee) {
            const attendancePercentage = employee.total_days > 0 ? 
                ((employee.present_days / employee.total_days) * 100).toFixed(1) : 0;
            
            html += `
                <div class="col-md-6 col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">${employee.name}</h6>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="h5 text-success">${attendancePercentage}%</div>
                                    <small class="text-muted">Attendance</small>
                                </div>
                                <div class="col-6">
                                    <div class="h5 text-info">${(employee.avg_hours || 0).toFixed(1)}h</div>
                                    <small class="text-muted">Avg Hours</small>
                                </div>
                                <div class="col-6 mt-2">
                                    <div class="h6 text-warning">${employee.total_overtime || 0}h</div>
                                    <small class="text-muted">Overtime</small>
                                </div>
                                <div class="col-6 mt-2">
                                    <div class="h6 text-primary">${(employee.avg_late_minutes || 0).toFixed(0)}m</div>
                                    <small class="text-muted">Avg Late</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
    }
    
    html += '</div>';
    $('#teamAnalyticsContainer').html(html);
}

function loadOvertimeRecords() {
    $.post('../../api/global_hrms_api.php', {
        module: 'manager',
        action: 'get_attendance_anomalies'
    }).done(function(response) {
        if (response.success) {
            displayOvertimeRecords(response.data);
        } else {
            $('#overtimeTable tbody').html('<tr><td colspan="7" class="text-center text-danger">' + response.message + '</td></tr>');
        }
    });
}

function displayOvertimeRecords(records) {
    let html = '';
    
    if (records.length === 0) {
        html = '<tr><td colspan="7" class="text-center text-muted">No overtime records found</td></tr>';
    } else {
        records.forEach(function(record) {
            let statusClass = 'warning';
            let statusText = 'Pending';
            
            if (record.approved_by) {
                statusClass = 'success';
                statusText = 'Approved';
            }
            
            html += `
                <tr>
                    <td><input type="checkbox" class="overtime-checkbox" value="${record.id}"></td>
                    <td>${record.employee_name}</td>
                    <td>${record.attendance_date}</td>
                    <td>${record.total_hours}h</td>
                    <td>${record.overtime_hours}h</td>
                    <td><span class="badge bg-${statusClass}">${statusText}</span></td>
                    <td>
                        ${!record.approved_by ? `
                            <button class="btn btn-sm btn-success me-1" onclick="approveOvertime(${record.id})">
                                <i class="fas fa-check"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="rejectOvertime(${record.id})">
                                <i class="fas fa-times"></i>
                            </button>
                        ` : `
                            <span class="text-muted">Processed</span>
                        `}
                    </td>
                </tr>
            `;
        });
    }
    
    $('#overtimeTable tbody').html(html);
}

function approveOvertime(attendanceId) {
    $.post('../../api/global_hrms_api.php', {
        module: 'manager',
        action: 'approve_overtime',
        attendance_id: attendanceId,
        action: 'approve'
    }).done(function(response) {
        if (response.success) {
            showAlert('Overtime approved successfully', 'success');
            loadOvertimeRecords();
        } else {
            showAlert(response.message, 'danger');
        }
    });
}

function rejectOvertime(attendanceId) {
    $.post('../../api/global_hrms_api.php', {
        module: 'manager',
        action: 'approve_overtime',
        attendance_id: attendanceId,
        action: 'reject'
    }).done(function(response) {
        if (response.success) {
            showAlert('Overtime rejected', 'warning');
            loadOvertimeRecords();
        } else {
            showAlert(response.message, 'danger');
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
    
    $.post('../../api/global_hrms_api.php', {
        module: 'manager',
        action: 'bulk_attendance_approval',
        attendance_ids: selectedIds,
        action: 'approve'
    }).done(function(response) {
        if (response.success) {
            showAlert(response.message, 'success');
            loadOvertimeRecords();
        } else {
            showAlert(response.message, 'danger');
        }
    });
}

function loadAttendanceAnomalies() {
    $.post('../../api/global_hrms_api.php', {
        module: 'manager',
        action: 'get_attendance_anomalies'
    }).done(function(response) {
        if (response.success) {
            displayAttendanceAnomalies(response.data);
        } else {
            $('#anomaliesTable tbody').html('<tr><td colspan="6" class="text-center text-danger">' + response.message + '</td></tr>');
        }
    });
}

function displayAttendanceAnomalies(anomalies) {
    let html = '';
    let lateCount = 0, earlyCount = 0, longBreakCount = 0;
    
    if (anomalies.length === 0) {
        html = '<tr><td colspan="6" class="text-center text-muted">No anomalies found</td></tr>';
    } else {
        anomalies.forEach(function(anomaly) {
            let severityClass = 'info';
            let anomalyType = 'Unknown';
            
            if (anomaly.late_minutes > 0) {
                anomalyType = 'Late Arrival';
                severityClass = 'warning';
                lateCount++;
            } else if (anomaly.early_departure_minutes > 0) {
                anomalyType = 'Early Departure';
                severityClass = 'danger';
                earlyCount++;
            } else if (anomaly.total_hours < 4) {
                anomalyType = 'Short Hours';
                severityClass = 'warning';
            }
            
            let details = '';
            if (anomaly.late_minutes > 0) {
                details = `${anomaly.late_minutes} minutes late`;
            } else if (anomaly.early_departure_minutes > 0) {
                details = `${anomaly.early_departure_minutes} minutes early`;
            } else {
                details = `Only ${anomaly.total_hours} hours worked`;
            }
            
            html += `
                <tr>
                    <td>${anomaly.employee_name}</td>
                    <td>${anomaly.attendance_date}</td>
                    <td><span class="badge bg-${severityClass}">${anomalyType}</span></td>
                    <td>${details}</td>
                    <td><span class="badge bg-${severityClass}">${severityClass.toUpperCase()}</span></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="viewAttendanceDetails(${anomaly.id})">
                            <i class="fas fa-eye"></i> View
                        </button>
                    </td>
                </tr>
            `;
        });
    }
    
    $('#anomaliesTable tbody').html(html);
    $('#lateArrivals').text(lateCount);
    $('#earlyDepartures').text(earlyCount);
    $('#longBreaks').text(longBreakCount);
}

function viewAttendanceDetails(attendanceId) {
    showAlert('Attendance details view will be implemented', 'info');
}

function showShiftScheduleModal() {
    const modal = `
        <div class="modal fade" id="shiftScheduleModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Schedule Team Shifts</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="shiftScheduleForm">
                        <div class="modal-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Employee *</label>
                                    <select class="form-select" name="employee_id" required>
                                        <option value="">Select Employee</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Shift Name *</label>
                                    <input type="text" class="form-control" name="shift_name" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Start Time *</label>
                                    <input type="time" class="form-control" name="start_time" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">End Time *</label>
                                    <input type="time" class="form-control" name="end_time" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Effective From *</label>
                                    <input type="date" class="form-control" name="effective_from" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Effective To</label>
                                    <input type="date" class="form-control" name="effective_to">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Schedule Shift</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `;
    
    $('body').append(modal);
    $('#shiftScheduleModal').modal('show');
    
    // Load team members for dropdown
    loadTeamMembersForShift();
    
    $('#shiftScheduleForm').on('submit', function(e) {
        e.preventDefault();
        
        const shiftData = [{
            employee_id: $('select[name="employee_id"]').val(),
            shift_name: $('input[name="shift_name"]').val(),
            start_time: $('input[name="start_time"]').val(),
            end_time: $('input[name="end_time"]').val(),
            effective_from: $('input[name="effective_from"]').val()
        }];
        
        $.post('../../api/global_hrms_api.php', {
            module: 'manager',
            action: 'schedule_team_shifts',
            shifts: shiftData
        }).done(function(response) {
            if (response.success) {
                $('#shiftScheduleModal').modal('hide');
                showAlert(response.message, 'success');
                loadShiftCalendar();
            } else {
                showAlert(response.message, 'danger');
            }
        });
    });
    
    $('#shiftScheduleModal').on('hidden.bs.modal', function() {
        $(this).remove();
    });
}

function loadTeamMembersForShift() {
    $.post('../../api/global_hrms_api.php', {
        module: 'manager',
        action: 'get_team_members'
    }).done(function(response) {
        if (response.success) {
            let options = '<option value="">Select Employee</option>';
            response.data.forEach(function(member) {
                options += `<option value="${member.employee_id}">${member.name}</option>`;
            });
            $('select[name="employee_id"]').html(options);
        }
    });
}

function loadShiftCalendar() {
    $('#shiftCalendar').html('<div class="text-center py-4"><p class="text-muted">Shift calendar will be implemented with full calendar integration</p></div>');
}

// Tab event handlers for lazy loading
$('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
    const target = $(e.target).attr('data-bs-target');
    
    switch(target) {
        case '#analytics':
            loadTeamAnalytics();
            break;
        case '#overtime':
            loadOvertimeRecords();
            break;
        case '#shifts':
            loadShiftCalendar();
            break;
        case '#anomalies':
            loadAttendanceAnomalies();
            break;
    }
});

// Initialize select all checkbox for overtime
$(document).on('change', '#selectAllOvertime', function() {
    $('.overtime-checkbox').prop('checked', $(this).is(':checked'));
});

// Set default date for shift week start
$(document).ready(function() {
    const today = new Date();
    const monday = new Date(today.setDate(today.getDate() - today.getDay() + 1));
    $('#shiftWeekStart').val(monday.toISOString().split('T')[0]);
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
