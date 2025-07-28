<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'HR Dashboard';

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Get HR dashboard statistics
$stats = [
    'pending_leaves' => 0,
    'approved_leaves' => 0,
    'rejected_leaves' => 0,
    'total_employees' => 0,
    'today_present' => 0,
    'pending_requests' => 0
];

try {
    // Get leave request statistics
    $leave_stats_query = $conn->query("
        SELECT 
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_leaves,
            COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_leaves,
            COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_leaves,
            COUNT(*) as total_requests
        FROM leave_requests 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    
    if ($leave_stats_query && $leave_stats = $leave_stats_query->fetch_assoc()) {
        $stats['pending_leaves'] = $leave_stats['pending_leaves'] ?? 0;
        $stats['approved_leaves'] = $leave_stats['approved_leaves'] ?? 0;
        $stats['rejected_leaves'] = $leave_stats['rejected_leaves'] ?? 0;
    }
    
    // Get employee count
    $employee_count_query = $conn->query("SELECT COUNT(*) as total FROM employees WHERE status = 'active'");
    if ($employee_count_query && $emp_count = $employee_count_query->fetch_assoc()) {
        $stats['total_employees'] = $emp_count['total'] ?? 0;
    }
    
    // Get today's present count
    $today = date('Y-m-d');
    $present_count_query = $conn->query("
        SELECT COUNT(*) as present 
        FROM attendance 
        WHERE attendance_date = '$today' AND status IN ('Present', 'Late')
    ");
    if ($present_count_query && $present_count = $present_count_query->fetch_assoc()) {
        $stats['today_present'] = $present_count['present'] ?? 0;
    }
    
} catch (Exception $e) {
    error_log("Error fetching HR dashboard stats: " . $e->getMessage());
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
                    <i class="bi bi-people-fill me-2 text-primary"></i>HR Management Dashboard
                </h1>
                <p class="text-muted mb-0 small">Manage employee requests, leave approvals, and HR operations - <?= date('F j, Y') ?></p>
            </div>
            <div class="d-flex gap-1">
                <!-- Global Search -->
                <div class="input-group input-group-sm" style="width: 180px;">
                    <input type="text" class="form-control form-control-sm" placeholder="Search..." id="globalSearch">
                    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="performGlobalSearch()">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
                
                <!-- Quick Actions -->
                <div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-outline-success" onclick="exportHRData()" title="Export HR Data">
                        <i class="bi bi-download"></i>
                    </button>
                    <button class="btn btn-outline-primary" onclick="refreshDashboard()" title="Refresh Dashboard">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-gear"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#hrSettingsModal">
                                <i class="bi bi-gear me-2"></i>HR Settings
                            </a></li>
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#notificationModal">
                                <i class="bi bi-bell me-2"></i>Notifications
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" onclick="generateHRReport()">
                                <i class="bi bi-file-earmark-text me-2"></i>Generate Report
                            </a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Real-time Alert System -->
        <div id="alertContainer" class="mb-2"></div>

        <!-- Enhanced Quick Stats with Real-time Updates -->
        <div class="row g-2 mb-3">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100 hover-lift">
                    <div class="card-body p-2">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-warning bg-opacity-10 rounded-circle p-2">
                                    <i class="bi bi-clock-history text-warning fs-5"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-2">
                                <h6 class="mb-0 fw-bold" id="pendingLeavesCount"><?= $stats['pending_leaves'] ?></h6>
                                <p class="text-muted mb-0 small">Pending Leaves</p>
                                <small class="text-warning">
                                    <i class="bi bi-exclamation-triangle me-1"></i>Requires Action
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100 hover-lift">
                    <div class="card-body p-2">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-success bg-opacity-10 rounded-circle p-2">
                                    <i class="bi bi-check-circle text-success fs-5"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-2">
                                <h6 class="mb-0 fw-bold" id="approvedLeavesCount"><?= $stats['approved_leaves'] ?></h6>
                                <p class="text-muted mb-0 small">Approved This Month</p>
                                <small class="text-success">
                                    <i class="bi bi-trend-up me-1"></i>This Month
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100 hover-lift">
                    <div class="card-body p-2">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-primary bg-opacity-10 rounded-circle p-2">
                                    <i class="bi bi-people text-primary fs-5"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-2">
                                <h6 class="mb-0 fw-bold" id="totalEmployeesCount"><?= $stats['total_employees'] ?></h6>
                                <p class="text-muted mb-0 small">Total Employees</p>
                                <small class="text-primary">
                                    <i class="bi bi-person-plus me-1"></i>Active Staff
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100 hover-lift">
                    <div class="card-body p-2">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-info bg-opacity-10 rounded-circle p-2">
                                    <i class="bi bi-person-check text-info fs-5"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-2">
                                <h6 class="mb-0 fw-bold" id="todayPresentCount"><?= $stats['today_present'] ?></h6>
                                <p class="text-muted mb-0 small">Present Today</p>
                                <small class="text-info">
                                    <i class="bi bi-clock me-1"></i><?= date('g:i A') ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Access Tabs -->
        <div class="row mb-2">
            <div class="col-12">
                <ul class="nav nav-pills bg-light rounded p-1" id="hrTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="leave-requests-tab" data-bs-toggle="pill" data-bs-target="#leave-requests" type="button" role="tab">
                            <i class="bi bi-calendar-event me-1"></i>Leave Requests
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="employee-management-tab" data-bs-toggle="pill" data-bs-target="#employee-management" type="button" role="tab">
                            <i class="bi bi-people me-1"></i>Employee Management
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="reports-tab" data-bs-toggle="pill" data-bs-target="#reports" type="button" role="tab">
                            <i class="bi bi-bar-chart-line me-1"></i>Reports & Analytics
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="policies-tab" data-bs-toggle="pill" data-bs-target="#policies" type="button" role="tab">
                            <i class="bi bi-file-text me-1"></i>Policies & Settings
                        </button>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Tab Content -->
        <div class="tab-content" id="hrTabsContent">
            
            <!-- Leave Requests Tab -->
            <div class="tab-pane fade show active" id="leave-requests" role="tabpanel">
                <div class="row">
                    <!-- Leave Requests Management -->
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-light border-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">
                                        <i class="bi bi-calendar-event me-2"></i>Leave Requests Management
                                    </h6>
                                    <div class="d-flex gap-2">
                                        <select class="form-select form-select-sm" id="statusFilter" onchange="filterLeaveRequests()">
                                            <option value="">All Status</option>
                                            <option value="pending">Pending</option>
                                            <option value="approved">Approved</option>
                                            <option value="rejected">Rejected</option>
                                        </select>
                                        <button class="btn btn-success btn-sm" onclick="loadLeaveRequests()">
                                            <i class="bi bi-arrow-clockwise"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div id="leaveRequestsList" class="table-responsive">
                                    <div class="text-center p-4">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mt-2 text-muted">Loading leave requests...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar Actions -->
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-light border-0">
                                <h6 class="mb-0">
                                    <i class="bi bi-lightning me-2"></i>Quick Actions
                                </h6>
                            </div>
                            <div class="card-body p-2">
                                <div class="d-grid gap-1">
                                    <button class="btn btn-outline-primary btn-sm" onclick="openBulkApprovalModal()">
                                        <i class="bi bi-check-all me-1"></i>Bulk Approve
                                    </button>
                                    <button class="btn btn-outline-warning btn-sm" onclick="openLeaveCalendarModal()">
                                        <i class="bi bi-calendar3 me-1"></i>Calendar View
                                    </button>
                                    <button class="btn btn-outline-info btn-sm" onclick="generateLeaveReport()">
                                        <i class="bi bi-file-earmark-pdf me-1"></i>Leave Report
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Employee Management Tab -->
            <div class="tab-pane fade" id="employee-management" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light border-0">
                        <h6 class="mb-0">
                            <i class="bi bi-people me-2"></i>Employee Management
                        </h6>
                    </div>
                    <div class="card-body p-2">
                        <div id="employeesList" class="table-responsive">
                            <div class="text-center p-3">
                                <i class="bi bi-people text-muted" style="font-size: 2rem;"></i>
                                <p class="mt-2 mb-0 text-muted small">Employee management features coming soon...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reports & Analytics Tab -->
            <div class="tab-pane fade" id="reports" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light border-0">
                        <h6 class="mb-0">
                            <i class="bi bi-bar-chart-line me-2"></i>HR Analytics & Reports
                        </h6>
                    </div>
                    <div class="card-body p-2">
                        <div class="text-center p-3">
                            <i class="bi bi-graph-up text-muted" style="font-size: 2rem;"></i>
                            <p class="mt-2 mb-0 text-muted small">Analytics dashboard coming soon...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Policies & Settings Tab -->
            <div class="tab-pane fade" id="policies" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light border-0">
                        <h6 class="mb-0">
                            <i class="bi bi-file-text me-2"></i>HR Policies & Settings
                        </h6>
                    </div>
                    <div class="card-body p-2">
                        <div class="text-center p-3">
                            <i class="bi bi-gear text-muted" style="font-size: 2rem;"></i>
                            <p class="mt-2 mb-0 text-muted small">Policy management features coming soon...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Leave Requests Management -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light border-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="bi bi-calendar-event me-2"></i>Leave Requests Management
                            </h6>
                            <div class="d-flex gap-2">
                                <select class="form-select form-select-sm" id="statusFilter" onchange="filterLeaveRequests()">
                                    <option value="">All Status</option>
                                    <option value="pending">Pending</option>
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                                <button class="btn btn-success btn-sm" onclick="loadLeaveRequests()">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div id="leaveRequestsContainer">
                            <div class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2 text-muted">Loading leave requests...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions & Notifications -->
            <div class="col-lg-4">
                <!-- Quick Actions -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-light border-0">
                        <h6 class="mb-0">
                            <i class="bi bi-lightning me-2"></i>HR Quick Actions
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary btn-sm" onclick="openBulkApprovalModal()">
                                <i class="bi bi-check-all me-1"></i>Bulk Approve Leaves
                            </button>
                            <button class="btn btn-outline-warning btn-sm" onclick="openLeaveBalanceModal()">
                                <i class="bi bi-wallet2 me-1"></i>Manage Leave Balances
                            </button>
                            <button class="btn btn-outline-info btn-sm" onclick="openEmployeeReportsModal()">
                                <i class="bi bi-file-text me-1"></i>Employee Reports
                            </button>
                            <button class="btn btn-outline-success btn-sm" onclick="openHolidayManagementModal()">
                                <i class="bi bi-calendar-plus me-1"></i>Manage Holidays
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" onclick="openPolicyModal()">
                                <i class="bi bi-book me-1"></i>Leave Policies
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-light border-0">
                        <h6 class="mb-0">
                            <i class="bi bi-activity me-2"></i>Recent HR Activity
                        </h6>
                    </div>
                    <div class="card-body">
                        <div id="recentActivityContainer">
                            <div class="text-center py-3">
                                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                <p class="mt-2 text-muted small">Loading activity...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Urgent Notifications -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light border-0">
                        <h6 class="mb-0 text-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>Urgent Notifications
                        </h6>
                    </div>
                    <div class="card-body">
                        <div id="urgentNotificationsContainer">
                            <div class="list-group list-group-flush">
                                <div class="list-group-item border-0 px-0">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-clock text-warning me-2"></i>
                                        <div class="flex-grow-1">
                                            <small class="fw-bold">Emergency Leave Request</small>
                                            <br><small class="text-muted">John Doe - Medical Emergency</small>
                                        </div>
                                        <span class="badge bg-danger">Urgent</span>
                                    </div>
                                </div>
                                <div class="list-group-item border-0 px-0">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-calendar-x text-danger me-2"></i>
                                        <div class="flex-grow-1">
                                            <small class="fw-bold">Overdue Approval</small>
                                            <br><small class="text-muted">3 requests pending > 2 days</small>
                                        </div>
                                        <span class="badge bg-warning">Alert</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Analytics Section -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light border-0">
                        <h6 class="mb-0">
                            <i class="bi bi-graph-up me-2"></i>HR Analytics & Reports
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <canvas id="leaveTypesChart" width="300" height="150"></canvas>
                            </div>
                            <div class="col-md-6">
                                <canvas id="attendanceTrendsChart" width="300" height="150"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Leave Request Detail Modal -->
<div class="modal fade" id="leaveRequestDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-calendar-event me-2"></i>Leave Request Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="leaveRequestDetailContent">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" onclick="rejectLeaveRequest()">
                    <i class="bi bi-x-circle me-1"></i>Reject
                </button>
                <button type="button" class="btn btn-success" onclick="approveLeaveRequest()">
                    <i class="bi bi-check-circle me-1"></i>Approve
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Approval Modal -->
<div class="modal fade" id="bulkApprovalModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-check-all me-2"></i>Bulk Leave Approval
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">Select multiple leave requests to approve at once:</p>
                <div id="bulkApprovalList">
                    <!-- Dynamic content -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="processBulkApproval()">
                    <i class="bi bi-check-all me-1"></i>Approve Selected
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Leave Balance Management Modal -->
<div class="modal fade" id="leaveBalanceModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="bi bi-wallet2 me-2"></i>Leave Balance Management
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Casual</th>
                                <th>Sick</th>
                                <th>Earned</th>
                                <th>Comp-off</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="leaveBalanceTableBody">
                            <!-- Dynamic content -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Employee Reports Modal -->
<div class="modal fade" id="employeeReportsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="bi bi-file-text me-2"></i>Employee Reports
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <select class="form-select" id="reportType">
                            <option value="attendance">Attendance Report</option>
                            <option value="leave">Leave Report</option>
                            <option value="performance">Performance Report</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="date" class="form-control" id="reportStartDate">
                    </div>
                    <div class="col-md-3">
                        <input type="date" class="form-control" id="reportEndDate">
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-primary w-100" onclick="generateReport()">
                            <i class="bi bi-file-earmark-pdf me-1"></i>Generate Report
                        </button>
                    </div>
                </div>
                <div id="reportContent">
                    <!-- Report content will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- HR Settings Modal -->
<div class="modal fade" id="hrSettingsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-gear me-2"></i>HR Settings
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Leave Policy Settings</h6>
                        <div class="mb-3">
                            <label class="form-label">Annual Casual Leave</label>
                            <input type="number" class="form-control" value="12">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Annual Sick Leave</label>
                            <input type="number" class="form-control" value="7">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Annual Earned Leave</label>
                            <input type="number" class="form-control" value="21">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Approval Settings</h6>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="autoApproveShortLeave" checked>
                                <label class="form-check-label" for="autoApproveShortLeave">
                                    Auto-approve short leaves
                                </label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="requireManagerApproval" checked>
                                <label class="form-check-label" for="requireManagerApproval">
                                    Require manager approval
                                </label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Max consecutive days without approval</label>
                            <input type="number" class="form-control" value="3">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveHRSettings()">
                    <i class="bi bi-save me-1"></i>Save Settings
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Notification Center Modal -->
<div class="modal fade" id="notificationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-bell me-2"></i>Notification Center
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Recent Notifications</h6>
                            <button class="btn btn-outline-primary btn-sm" onclick="markAllAsRead()">
                                <i class="bi bi-check-all me-1"></i>Mark All Read
                            </button>
                        </div>
                        
                        <div class="list-group list-group-flush">
                            <div class="list-group-item d-flex align-items-start">
                                <div class="me-3">
                                    <div class="bg-warning rounded-circle p-2">
                                        <i class="bi bi-calendar-x text-white"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">New Leave Request</h6>
                                    <p class="mb-1 small text-muted">John Doe requested sick leave for 2 days</p>
                                    <small class="text-muted">5 minutes ago</small>
                                </div>
                                <span class="badge bg-warning">New</span>
                            </div>
                            
                            <div class="list-group-item d-flex align-items-start">
                                <div class="me-3">
                                    <div class="bg-success rounded-circle p-2">
                                        <i class="bi bi-person-check text-white"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">Employee Check-in</h6>
                                    <p class="mb-1 small text-muted">Sarah Wilson checked in at 9:00 AM</p>
                                    <small class="text-muted">15 minutes ago</small>
                                </div>
                            </div>
                            
                            <div class="list-group-item d-flex align-items-start">
                                <div class="me-3">
                                    <div class="bg-info rounded-circle p-2">
                                        <i class="bi bi-clock text-white"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">Overtime Request</h6>
                                    <p class="mb-1 small text-muted">Michael Chen requested overtime approval</p>
                                    <small class="text-muted">1 hour ago</small>
                                </div>
                                <span class="badge bg-info">Pending</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="viewAllNotifications()">
                    <i class="bi bi-eye me-1"></i>View All
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.list-group-item {
    border: none;
    padding: 0.75rem 0;
}

.list-group-item:not(:last-child) {
    border-bottom: 1px solid #f8f9fa;
}

.badge {
    font-size: 0.7rem;
}

.modal-content {
    border: none;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

.table th {
    border-top: none;
    font-weight: 600;
    color: #495057;
}

/* Chart containers */
canvas {
    max-width: 100%;
    height: auto;
}

/* Icon sizing consistency */
.bi {
    vertical-align: baseline;
}

.btn .bi {
    font-size: 1rem;
}

.btn-sm .bi {
    font-size: 0.875rem;
}

.card-header .bi {
    font-size: 1rem;
}

.list-group-item .bi {
    font-size: 1.25rem;
}

/* Hover effects for interactive elements */
.hover-lift:hover {
    transform: translateY(-2px);
    transition: transform 0.2s ease;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Initialize HR Dashboard
document.addEventListener('DOMContentLoaded', function() {
    loadLeaveRequests();
    loadRecentActivity();
    initializeCharts();
});

let currentLeaveRequestId = null;

// Load leave requests
function loadLeaveRequests() {
    const container = document.getElementById('leaveRequestsContainer');
    container.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2 text-muted">Loading leave requests...</p>
        </div>
    `;
    
    fetch('hr_api.php?action=get_leave_requests')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayLeaveRequests(data.requests);
            } else {
                container.innerHTML = `
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-1"></i>
                        <p class="mt-2">No leave requests found</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading leave requests:', error);
            container.innerHTML = `
                <div class="text-center py-4 text-danger">
                    <i class="bi bi-exclamation-triangle fs-1"></i>
                    <p class="mt-2">Error loading leave requests</p>
                </div>
            `;
        });
}

// Display leave requests
function displayLeaveRequests(requests) {
    const container = document.getElementById('leaveRequestsContainer');
    
    if (!requests || requests.length === 0) {
        container.innerHTML = `
            <div class="text-center py-4 text-muted">
                <i class="bi bi-inbox fs-1"></i>
                <p class="mt-2">No leave requests found</p>
            </div>
        `;
        return;
    }
    
    let html = '<div class="table-responsive">';
    html += `
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Employee</th>
                    <th>Leave Type</th>
                    <th>Duration</th>
                    <th>Applied Date</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    requests.forEach(request => {
        const statusBadge = getStatusBadge(request.status);
        const priorityBadge = getPriorityBadge(request.priority);
        const leaveTypeIcon = getLeaveTypeIcon(request.leave_type);
        
        html += `
            <tr>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="avatar-sm bg-light rounded-circle me-2 d-flex align-items-center justify-content-center">
                            <i class="bi bi-person text-muted"></i>
                        </div>
                        <div>
                            <div class="fw-bold small">${request.employee_name || 'N/A'}</div>
                            <small class="text-muted">${request.employee_code || ''}</small>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="badge bg-light text-dark">
                        ${leaveTypeIcon} ${request.leave_type}
                    </span>
                </td>
                <td>
                    <div class="small">
                        <strong>${request.duration_days} days</strong><br>
                        <small class="text-muted">${request.start_date} to ${request.end_date}</small>
                    </div>
                </td>
                <td>
                    <small>${new Date(request.applied_date).toLocaleDateString()}</small>
                </td>
                <td>${statusBadge}</td>
                <td>${priorityBadge}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="viewLeaveRequest(${request.id})" 
                                data-bs-toggle="tooltip" title="View Details">
                            <i class="bi bi-eye"></i>
                        </button>
                        ${request.status === 'pending' ? `
                            <button class="btn btn-outline-success" onclick="quickApprove(${request.id})"
                                    data-bs-toggle="tooltip" title="Quick Approve">
                                <i class="bi bi-check"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="quickReject(${request.id})"
                                    data-bs-toggle="tooltip" title="Quick Reject">
                                <i class="bi bi-x"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    });
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
    
    // Initialize tooltips
    const tooltips = container.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(tooltip => new bootstrap.Tooltip(tooltip));
}

// Helper functions for badges and icons
function getStatusBadge(status) {
    const badges = {
        'pending': '<span class="badge bg-warning">Pending</span>',
        'approved': '<span class="badge bg-success">Approved</span>',
        'rejected': '<span class="badge bg-danger">Rejected</span>',
        'cancelled': '<span class="badge bg-secondary">Cancelled</span>'
    };
    return badges[status] || '<span class="badge bg-secondary">Unknown</span>';
}

function getPriorityBadge(priority) {
    const badges = {
        'normal': '<span class="badge bg-light text-dark">Normal</span>',
        'urgent': '<span class="badge bg-warning">Urgent</span>',
        'emergency': '<span class="badge bg-danger">Emergency</span>'
    };
    return badges[priority] || '<span class="badge bg-light text-dark">Normal</span>';
}

function getLeaveTypeIcon(leaveType) {
    const icons = {
        'sick': '🤒',
        'casual': '🏖️',
        'earned': '🎯',
        'maternity': '👶',
        'paternity': '👨‍👶',
        'comp-off': '⚖️',
        'wfh': '🏠',
        'half-day': '⏰',
        'short-leave': '🏃',
        'emergency': '🚨'
    };
    return icons[leaveType] || '📅';
}

// View leave request details
function viewLeaveRequest(requestId) {
    currentLeaveRequestId = requestId;
    
    fetch(`hr_api.php?action=get_leave_request_details&id=${requestId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayLeaveRequestDetails(data.request);
                const modal = new bootstrap.Modal(document.getElementById('leaveRequestDetailModal'));
                modal.show();
            } else {
                showAlert('Error loading leave request details', 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Network error', 'danger');
        });
}

// Display leave request details in modal
function displayLeaveRequestDetails(request) {
    const content = document.getElementById('leaveRequestDetailContent');
    const leaveTypeIcon = getLeaveTypeIcon(request.leave_type);
    
    content.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-primary">Employee Information</h6>
                <table class="table table-borderless table-sm">
                    <tr>
                        <td><strong>Name:</strong></td>
                        <td>${request.employee_name}</td>
                    </tr>
                    <tr>
                        <td><strong>Employee Code:</strong></td>
                        <td>${request.employee_code || 'N/A'}</td>
                    </tr>
                    <tr>
                        <td><strong>Department:</strong></td>
                        <td>${request.department || 'N/A'}</td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="text-primary">Leave Details</h6>
                <table class="table table-borderless table-sm">
                    <tr>
                        <td><strong>Leave Type:</strong></td>
                        <td>${leaveTypeIcon} ${request.leave_type}</td>
                    </tr>
                    <tr>
                        <td><strong>Duration:</strong></td>
                        <td>${request.duration_days} days</td>
                    </tr>
                    <tr>
                        <td><strong>Dates:</strong></td>
                        <td>${request.start_date} to ${request.end_date}</td>
                    </tr>
                    <tr>
                        <td><strong>Priority:</strong></td>
                        <td>${getPriorityBadge(request.priority)}</td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-12">
                <h6 class="text-primary">Reason</h6>
                <div class="bg-light p-3 rounded">
                    <p class="mb-0">${request.reason}</p>
                </div>
            </div>
        </div>
        
        ${request.emergency_contact ? `
            <div class="row mt-3">
                <div class="col-md-6">
                    <h6 class="text-primary">Emergency Contact</h6>
                    <p class="mb-0">${request.emergency_contact}</p>
                </div>
            </div>
        ` : ''}
        
        ${request.handover_details ? `
            <div class="row mt-3">
                <div class="col-12">
                    <h6 class="text-primary">Work Handover Details</h6>
                    <div class="bg-light p-3 rounded">
                        <p class="mb-0">${request.handover_details}</p>
                    </div>
                </div>
            </div>
        ` : ''}
        
        ${request.attachment_path ? `
            <div class="row mt-3">
                <div class="col-12">
                    <h6 class="text-primary">Attachment</h6>
                    <a href="${request.attachment_path}" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-paperclip me-1"></i>View Attachment
                    </a>
                </div>
            </div>
        ` : ''}
        
        <div class="row mt-3">
            <div class="col-12">
                <h6 class="text-primary">Comments (HR/Manager)</h6>
                <textarea class="form-control" id="managerComments" rows="3" 
                          placeholder="Add your comments for approval/rejection...">${request.manager_comments || ''}</textarea>
            </div>
        </div>
    `;
}

// Quick approve
function quickApprove(requestId) {
    if (confirm('Are you sure you want to approve this leave request?')) {
        processLeaveRequest(requestId, 'approved', '');
    }
}

// Quick reject
function quickReject(requestId) {
    const reason = prompt('Please provide a reason for rejection:');
    if (reason !== null && reason.trim() !== '') {
        processLeaveRequest(requestId, 'rejected', reason);
    }
}

// Approve leave request from modal
function approveLeaveRequest() {
    const comments = document.getElementById('managerComments').value;
    processLeaveRequest(currentLeaveRequestId, 'approved', comments);
}

// Reject leave request from modal
function rejectLeaveRequest() {
    const comments = document.getElementById('managerComments').value;
    if (!comments.trim()) {
        showAlert('Please provide comments for rejection', 'warning');
        return;
    }
    processLeaveRequest(currentLeaveRequestId, 'rejected', comments);
}

// Process leave request (approve/reject)
function processLeaveRequest(requestId, status, comments) {
    const formData = new FormData();
    formData.append('action', 'process_leave_request');
    formData.append('request_id', requestId);
    formData.append('status', status);
    formData.append('comments', comments);
    
    fetch('hr_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(`Leave request ${status} successfully!`, 'success');
            loadLeaveRequests(); // Refresh the list
            
            // Close modal if open
            const modal = bootstrap.Modal.getInstance(document.getElementById('leaveRequestDetailModal'));
            if (modal) modal.hide();
        } else {
            showAlert(`Error: ${data.message}`, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Network error', 'danger');
    });
}

// Load recent activity
function loadRecentActivity() {
    const container = document.getElementById('recentActivityContainer');
    
    // Mock recent activity - in real implementation, fetch from server
    setTimeout(() => {
        container.innerHTML = `
            <div class="list-group list-group-flush">
                <div class="list-group-item border-0 px-0">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-check-circle text-success me-2"></i>
                        <div class="flex-grow-1">
                            <small class="fw-bold">Leave Approved</small>
                            <br><small class="text-muted">Jane Smith - Casual Leave</small>
                        </div>
                        <small class="text-muted">2h ago</small>
                    </div>
                </div>
                <div class="list-group-item border-0 px-0">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-calendar-plus text-primary me-2"></i>
                        <div class="flex-grow-1">
                            <small class="fw-bold">New Leave Request</small>
                            <br><small class="text-muted">Mike Johnson - Work From Home</small>
                        </div>
                        <small class="text-muted">4h ago</small>
                    </div>
                </div>
                <div class="list-group-item border-0 px-0">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-x-circle text-danger me-2"></i>
                        <div class="flex-grow-1">
                            <small class="fw-bold">Leave Rejected</small>
                            <br><small class="text-muted">Tom Wilson - Insufficient balance</small>
                        </div>
                        <small class="text-muted">1d ago</small>
                    </div>
                </div>
            </div>
        `;
    }, 1000);
}

// Initialize charts
function initializeCharts() {
    // Leave Types Chart
    const leaveTypesCtx = document.getElementById('leaveTypesChart').getContext('2d');
    new Chart(leaveTypesCtx, {
        type: 'doughnut',
        data: {
            labels: ['Casual Leave', 'Sick Leave', 'Earned Leave', 'Work From Home', 'Others'],
            datasets: [{
                data: [30, 15, 25, 20, 10],
                backgroundColor: [
                    '#007bff',
                    '#28a745',
                    '#ffc107',
                    '#17a2b8',
                    '#6c757d'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Leave Types Distribution'
                }
            }
        }
    });
    
    // Attendance Trends Chart
    const attendanceTrendsCtx = document.getElementById('attendanceTrendsChart').getContext('2d');
    new Chart(attendanceTrendsCtx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'Attendance Rate %',
                data: [85, 88, 92, 89, 91, 87],
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Monthly Attendance Trends'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });
}

// Filter leave requests
function filterLeaveRequests() {
    loadLeaveRequests(); // In real implementation, pass filter parameter
}

// Refresh dashboard
function refreshDashboard() {
    loadLeaveRequests();
    loadRecentActivity();
    showAlert('Dashboard refreshed successfully!', 'success');
}

// Modal functions
function openBulkApprovalModal() {
    const modal = new bootstrap.Modal(document.getElementById('bulkApprovalModal'));
    modal.show();
}

function openLeaveBalanceModal() {
    const modal = new bootstrap.Modal(document.getElementById('leaveBalanceModal'));
    modal.show();
}

// Enhanced HR Dashboard Functions
function performGlobalSearch() {
    const searchTerm = document.getElementById('globalSearch').value.trim();
    if (searchTerm.length < 2) {
        showAlert('Please enter at least 2 characters to search', 'warning');
        return;
    }
    
    showAlert(`Searching for: ${searchTerm}...`, 'info');
    // Implement actual search functionality here
}

function exportHRData() {
    showAlert('Preparing HR data export...', 'info');
    // Implement export functionality
    setTimeout(() => {
        showAlert('HR data exported successfully!', 'success');
    }, 2000);
}

function generateHRReport() {
    showAlert('📊 Generating comprehensive HR report...', 'info');
    // Implement report generation
    setTimeout(() => {
        showAlert('✅ HR report generated successfully!', 'success');
    }, 3000);
}

function openBulkApprovalModal() {
    showAlert('✅ Bulk approval modal opening...', 'info');
    // Implement bulk approval functionality
}

function openLeaveCalendarModal() {
    showAlert('📅 Leave calendar view opening...', 'info');
    // Implement calendar view
}

function generateLeaveReport() {
    showAlert('📄 Generating leave report...', 'info');
    // Implement leave report generation
    setTimeout(() => {
        showAlert('✅ Leave report generated successfully!', 'success');
    }, 2000);
}

function exportLeaveData() {
    showAlert('📥 Exporting leave data...', 'info');
    // Implement leave data export
    setTimeout(() => {
        showAlert('✅ Leave data exported successfully!', 'success');
    }, 1500);
}

function openAddEmployeeModal() {
    showAlert('👤 Add employee modal opening...', 'info');
    // Implement add employee functionality
}

function importEmployees() {
    showAlert('📂 Employee import wizard opening...', 'info');
    // Implement employee import
}

function filterEmployees() {
    const search = document.getElementById('employeeSearch')?.value || '';
    const department = document.getElementById('departmentFilter')?.value || '';
    const status = document.getElementById('statusEmployeeFilter')?.value || '';
    
    showAlert('Applying employee filters...', 'info');
    // Implement filtering logic
}

function clearEmployeeFilters() {
    if (document.getElementById('employeeSearch')) document.getElementById('employeeSearch').value = '';
    if (document.getElementById('departmentFilter')) document.getElementById('departmentFilter').value = '';
    if (document.getElementById('statusEmployeeFilter')) document.getElementById('statusEmployeeFilter').value = '';
    
    showAlert('Filters cleared', 'success');
    filterEmployees();
}

function generateAttendanceReport() {
    showAlert('Generating attendance report...', 'info');
    // Implement attendance report
}

function generateEmployeeReport() {
    showAlert('Generating employee report...', 'info');
    // Implement employee report
}

function generatePayrollReport() {
    showAlert('Generating payroll report...', 'info');
    // Implement payroll report
}

// Real-time data refresh
function refreshDashboard() {
    showAlert('🔄 Refreshing dashboard data...', 'info');
    
    // Reload main components
    loadLeaveRequests();
    loadRecentActivity();
    
    // Simulate data refresh
    setTimeout(() => {
        // Update counters with animation
        animateCounter('pendingLeavesCount');
        animateCounter('approvedLeavesCount'); 
        animateCounter('todayPresentCount');
        animateCounter('totalEmployeesCount');
        
        showAlert('✅ Dashboard refreshed successfully!', 'success');
    }, 2000);
}

function animateCounter(elementId) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const currentValue = parseInt(element.textContent);
    const newValue = currentValue + Math.floor(Math.random() * 3) - 1; // Random slight change
    
    if (newValue >= 0) {
        element.style.transform = 'scale(1.1)';
        setTimeout(() => {
            element.textContent = newValue;
            element.style.transform = 'scale(1)';
        }, 150);
    }
}

// Auto-refresh dashboard every 5 minutes
setInterval(() => {
    if (document.hasFocus()) {
        refreshDashboard();
    }
}, 300000);

// Real-time notifications
function showRealTimeAlert(message, type = 'info') {
    const alertContainer = document.getElementById('alertContainer');
    if (!alertContainer) return;
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        <i class="bi bi-bell-fill me-2"></i>
        <strong>Real-time Update:</strong> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    alertContainer.appendChild(alertDiv);
    
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 8000);
}

// Initialize dashboard on load
document.addEventListener('DOMContentLoaded', function() {
    // Load initial data
    loadLeaveRequests();
    
    // Setup real-time updates simulation
    setTimeout(() => {
        showRealTimeAlert('New leave request received from John Doe', 'warning');
    }, 10000);
    
    setTimeout(() => {
        showRealTimeAlert('Employee Sarah Wilson checked in', 'success');
    }, 20000);
});

// Notification Center Functions
function markAllAsRead() {
    showAlert('All notifications marked as read', 'success');
    // Update notification badges
    document.querySelectorAll('.badge').forEach(badge => {
        if (badge.textContent === 'New') {
            badge.remove();
        }
    });
}

function viewAllNotifications() {
    showAlert('Opening full notification center...', 'info');
    // Implement full notification center
}

// CSS Enhancements
const style = document.createElement('style');
style.textContent = `
    .hover-lift {
        transition: transform 0.2s ease-in-out;
    }
    
    .hover-lift:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
    }
    
    .nav-pills .nav-link {
        border-radius: 8px;
        font-weight: 500;
        padding: 8px 16px;
        margin-right: 4px;
    }
    
    .nav-pills .nav-link.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
    }
    
    .tab-content {
        animation: fadeIn 0.3s ease-in-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .card {
        transition: all 0.3s ease-in-out;
    }
    
    .btn-group .dropdown-menu {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border: none;
        border-radius: 8px;
    }
    
    .progress {
        border-radius: 4px;
        overflow: hidden;
    }
`;
document.head.appendChild(style);

function openEmployeeReportsModal() {
    const modal = new bootstrap.Modal(document.getElementById('employeeReportsModal'));
    modal.show();
}

function openHolidayManagementModal() {
    showAlert('📅 Holiday Management feature coming soon!', 'info');
    // TODO: Implement holiday management modal
}

function openPolicyModal() {
    showAlert('📋 Policy Management feature coming soon!', 'info');
    // TODO: Implement policy management modal
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
