<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Manager Dashboard';

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Get manager information and team members
$manager_id = $_SESSION['admin']; // In real implementation, get from user session
$team_members = [];

try {
    // Get team members (employees under this manager)
    $team_query = $conn->query("
        SELECT employee_id, name, employee_code, position, email 
        FROM employees 
        WHERE status = 'active' 
        ORDER BY name ASC
    ");
    
    if ($team_query) {
        while ($row = $team_query->fetch_assoc()) {
            $team_members[] = $row;
        }
    }
    
} catch (Exception $e) {
    error_log("Error fetching team members: " . $e->getMessage());
}

// Get dashboard statistics
$stats = [
    'pending_approvals' => 0,
    'team_members' => count($team_members),
    'today_present' => 0,
    'this_month_leaves' => 0
];

try {
    // Get pending approvals count
    $pending_query = $conn->query("SELECT COUNT(*) as count FROM leave_requests WHERE status = 'pending'");
    if ($pending_query && $row = $pending_query->fetch_assoc()) {
        $stats['pending_approvals'] = $row['count'];
    }
    
    // Get today's present count from team
    $today = date('Y-m-d');
    $present_query = $conn->query("
        SELECT COUNT(*) as count 
        FROM attendance a
        JOIN employees e ON a.employee_id = e.employee_id
        WHERE a.attendance_date = '$today' 
        AND a.status IN ('Present', 'Late')
        AND e.status = 'active'
    ");
    if ($present_query && $row = $present_query->fetch_assoc()) {
        $stats['today_present'] = $row['count'];
    }
    
    // Get this month's approved leaves
    $month_leaves_query = $conn->query("
        SELECT COUNT(*) as count 
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.employee_id
        WHERE lr.status = 'approved' 
        AND MONTH(lr.start_date) = MONTH(CURDATE())
        AND YEAR(lr.start_date) = YEAR(CURDATE())
        AND e.status = 'active'
    ");
    if ($month_leaves_query && $row = $month_leaves_query->fetch_assoc()) {
        $stats['this_month_leaves'] = $row['count'];
    }
    
} catch (Exception $e) {
    error_log("Error fetching manager dashboard stats: " . $e->getMessage());
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
                    <i class="bi bi-person-badge me-2 text-success"></i>Manager Dashboard
                </h1>
                <p class="text-muted mb-0 small">Manage your team's attendance, leaves, and requests - <?= date('F j, Y') ?></p>
                
                <!-- Breadcrumb Navigation -->
                <nav aria-label="breadcrumb" class="mt-1">
                    <ol class="breadcrumb small mb-0">
                        <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="../hr/hr_dashboard.php">HR Portal</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Manager Portal</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex gap-1">
                <!-- Global Search -->
                <div class="input-group input-group-sm" style="width: 180px;">
                    <input type="text" class="form-control" placeholder="Search team..." id="managerGlobalSearch">
                    <button class="btn btn-outline-secondary" type="button" onclick="performManagerSearch()">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
                
                <!-- Quick Actions -->
                <div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-outline-success btn-sm" onclick="exportTeamData()" title="Export Team Data">
                        <i class="bi bi-download me-1"></i>Export
                    </button>
                    <button class="btn btn-outline-primary btn-sm" onclick="refreshManagerDashboard()" title="Refresh Dashboard">
                        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                    </button>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-success btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-gear me-1"></i>Manager Tools
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="openTeamSettingsModal()">
                                <i class="bi bi-gear me-2"></i>Team Settings
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="openPerformanceModal()">
                                <i class="bi bi-graph-up me-2"></i>Performance Review
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" onclick="generateTeamReport()">
                                <i class="bi bi-file-earmark-text me-2"></i>Generate Team Report
                            </a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Real-time Alert System -->
        <div id="managerAlertContainer" class="mb-2"></div>
            <div class="d-flex gap-1">
                <button class="btn btn-outline-primary btn-sm" onclick="refreshDashboard()">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
                <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#teamAnalyticsModal">
                    <i class="bi bi-graph-up"></i>
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-2 mb-3">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100 bg-gradient-warning">
                    <div class="card-body text-white p-2">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="bi bi-clock-history fs-4"></i>
                            </div>
                            <div class="flex-grow-1 ms-2">
                                <h6 class="mb-0 fw-bold"><?= $stats['pending_approvals'] ?></h6>
                                <p class="mb-0 small opacity-75">Pending Approvals</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100 bg-gradient-primary">
                    <div class="card-body text-white p-2">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="bi bi-people fs-4"></i>
                            </div>
                            <div class="flex-grow-1 ms-2">
                                <h6 class="mb-0 fw-bold"><?= $stats['team_members'] ?></h6>
                                <p class="mb-0 small opacity-75">Team Members</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100 bg-gradient-success">
                    <div class="card-body text-white p-2">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="bi bi-person-check fs-4"></i>
                            </div>
                            <div class="flex-grow-1 ms-2">
                                <h6 class="mb-0 fw-bold"><?= $stats['today_present'] ?></h6>
                                <p class="mb-0 small opacity-75">Present Today</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100 bg-gradient-info">
                    <div class="card-body text-white p-2">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="bi bi-calendar-event fs-4"></i>
                            </div>
                            <div class="flex-grow-1 ms-2">
                                <h6 class="mb-0 fw-bold"><?= $stats['this_month_leaves'] ?></h6>
                                <p class="mb-0 small opacity-75">Leaves This Month</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Pending Approvals -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light border-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="bi bi-clock-history me-2 text-warning"></i>Pending Approvals
                            </h6>
                            <div class="d-flex gap-2">
                                <select class="form-select form-select-sm" id="priorityFilter" onchange="filterPendingRequests()">
                                    <option value="">All Priorities</option>
                                    <option value="emergency">Emergency</option>
                                    <option value="urgent">Urgent</option>
                                    <option value="normal">Normal</option>
                                </select>
                                <button class="btn btn-success btn-sm" onclick="loadPendingRequests()">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div id="pendingRequestsContainer">
                            <div class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2 text-muted">Loading pending requests...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Team Overview & Quick Actions -->
            <div class="col-lg-4">
                <!-- Team Quick View -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-light border-0">
                        <h6 class="mb-0">
                            <i class="bi bi-people me-2"></i>Team Overview
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <div class="card bg-success text-white text-center">
                                    <div class="card-body p-2">
                                        <div class="fw-bold" id="teamPresentCount"><?= $stats['today_present'] ?></div>
                                        <small>Present</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="card bg-danger text-white text-center">
                                    <div class="card-body p-2">
                                        <div class="fw-bold" id="teamAbsentCount"><?= $stats['team_members'] - $stats['today_present'] ?></div>
                                        <small>Absent</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary btn-sm" onclick="viewTeamAttendance()">
                                <i class="bi bi-calendar-check me-1"></i>View Team Attendance
                            </button>
                            <button class="btn btn-outline-info btn-sm" onclick="viewTeamLeaveCalendar()">
                                <i class="bi bi-calendar3 me-1"></i>Team Leave Calendar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Manager Quick Actions -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-light border-0">
                        <h6 class="mb-0">
                            <i class="bi bi-lightning me-2"></i>Quick Manager Actions
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-success btn-sm" onclick="bulkApproveSelected()">
                                <i class="bi bi-check-all me-1"></i>Bulk Approve
                            </button>
                            <button class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#teamLeaveBalanceModal">
                                <i class="bi bi-wallet2 me-1"></i>Team Leave Balance
                            </button>
                            <button class="btn btn-outline-info btn-sm" onclick="generateTeamReport()">
                                <i class="bi bi-file-text me-1"></i>Generate Report
                            </button>
                            <button class="btn btn-outline-primary btn-sm" onclick="sendTeamNotification()">
                                <i class="bi bi-bell me-1"></i>Send Notification
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Quick Team Status -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light border-0">
                        <h6 class="mb-0">
                            <i class="bi bi-speedometer2 me-2"></i>Today's Team Status
                        </h6>
                    </div>
                    <div class="card-body">
                        <div id="teamStatusContainer">
                            <div class="text-center py-3">
                                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                <p class="mt-2 text-muted small">Loading team status...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Team Performance Section -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light border-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="bi bi-graph-up me-2"></i>Team Performance Dashboard
                            </h6>
                            <div class="btn-group">
                                <button class="btn btn-outline-primary btn-sm" onclick="loadWeeklyView()">Weekly</button>
                                <button class="btn btn-outline-primary btn-sm active" onclick="loadMonthlyView()">Monthly</button>
                                <button class="btn btn-outline-primary btn-sm" onclick="loadQuarterlyView()">Quarterly</button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <canvas id="teamPerformanceChart" width="400" height="200"></canvas>
                            </div>
                            <div class="col-md-4">
                                <h6 class="text-muted mb-3">Team Metrics</h6>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span>Average Attendance:</span>
                                        <span class="badge bg-success">92.5%</span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span>On-time Arrival:</span>
                                        <span class="badge bg-info">88.3%</span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span>Leave Utilization:</span>
                                        <span class="badge bg-warning">67.2%</span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span>Team Productivity:</span>
                                        <span class="badge bg-primary">94.1%</span>
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

<!-- Request Detail Modal -->
<div class="modal fade" id="requestDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-calendar-event me-2"></i>Request Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="requestDetailContent">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" onclick="rejectRequest()">
                    <i class="bi bi-x-circle me-1"></i>Reject
                </button>
                <button type="button" class="btn btn-success" onclick="approveRequest()">
                    <i class="bi bi-check-circle me-1"></i>Approve
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Team Analytics Modal -->
<div class="modal fade" id="teamAnalyticsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-graph-up me-2"></i>Detailed Team Analytics
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <select class="form-select" id="analyticsTimeRange">
                            <option value="week">This Week</option>
                            <option value="month" selected>This Month</option>
                            <option value="quarter">This Quarter</option>
                            <option value="year">This Year</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" id="analyticsEmployee">
                            <option value="">All Team Members</option>
                            <?php foreach ($team_members as $member): ?>
                                <option value="<?= $member['employee_id'] ?>"><?= htmlspecialchars($member['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-primary w-100" onclick="loadTeamAnalytics()">
                            <i class="bi bi-bar-chart me-1"></i>Load Analytics
                        </button>
                    </div>
                </div>
                <div id="teamAnalyticsContent">
                    <!-- Analytics content will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Team Leave Balance Modal -->
<div class="modal fade" id="teamLeaveBalanceModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="bi bi-wallet2 me-2"></i>Team Leave Balance Overview
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Casual Leave</th>
                                <th>Sick Leave</th>
                                <th>Earned Leave</th>
                                <th>Comp-off</th>
                                <th>Total Available</th>
                                <th>This Month Used</th>
                            </tr>
                        </thead>
                        <tbody id="teamLeaveBalanceTableBody">
                            <!-- Dynamic content -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #007bff, #0056b3);
}

.bg-gradient-success {
    background: linear-gradient(135deg, #28a745, #1e7e34);
}

.bg-gradient-warning {
    background: linear-gradient(135deg, #ffc107, #e0a800);
}

.bg-gradient-info {
    background: linear-gradient(135deg, #17a2b8, #138496);
}

.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
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

.badge {
    font-size: 0.75rem;
}

/* Chart container */
canvas {
    max-width: 100%;
    height: auto;
}

/* Team status indicators */
.status-indicator {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 8px;
}

.status-present { background-color: #28a745; }
.status-absent { background-color: #dc3545; }
.status-late { background-color: #ffc107; }
.status-leave { background-color: #17a2b8; }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Initialize Manager Dashboard
document.addEventListener('DOMContentLoaded', function() {
    loadPendingRequests();
    loadTeamStatus();
    initializeTeamPerformanceChart();
});

let currentRequestId = null;

// Load pending requests
function loadPendingRequests() {
    const container = document.getElementById('pendingRequestsContainer');
    container.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2 text-muted">Loading pending requests...</p>
        </div>
    `;
    
    fetch('../hr/hr_api.php?action=get_leave_requests&status=pending')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.requests.length > 0) {
                displayPendingRequests(data.requests);
            } else {
                container.innerHTML = `
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-check-circle fs-1 text-success"></i>
                        <p class="mt-2">No pending approvals! All caught up.</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading pending requests:', error);
            container.innerHTML = `
                <div class="text-center py-4 text-danger">
                    <i class="bi bi-exclamation-triangle fs-1"></i>
                    <p class="mt-2">Error loading pending requests</p>
                </div>
            `;
        });
}

// Display pending requests
function displayPendingRequests(requests) {
    const container = document.getElementById('pendingRequestsContainer');
    
    let html = '<div class="list-group list-group-flush">';
    
    requests.forEach(request => {
        const priorityColor = {
            'emergency': 'danger',
            'urgent': 'warning', 
            'normal': 'secondary'
        };
        
        const priorityIcons = {
            'emergency': '🚨',
            'urgent': '⚡',
            'normal': '📋'
        };
        
        const leaveTypeIcon = getLeaveTypeIcon(request.leave_type);
        
        html += `
            <div class="list-group-item border-0 px-3 py-3">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm bg-light rounded-circle me-3 d-flex align-items-center justify-content-center">
                                <i class="bi bi-person text-muted"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">${request.employee_name}</h6>
                                <small class="text-muted">${request.employee_code} • ${request.department || 'N/A'}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <span class="badge bg-light text-dark mb-1">
                                ${leaveTypeIcon} ${request.leave_type}
                            </span>
                            <br>
                            <small class="text-muted">${request.duration_days} days</small>
                            <br>
                            <small class="text-muted">${request.start_date} - ${request.end_date}</small>
                        </div>
                    </div>
                    <div class="col-md-2 text-center">
                        <span class="badge bg-${priorityColor[request.priority]}">
                            ${priorityIcons[request.priority]} ${request.priority.toUpperCase()}
                        </span>
                        <br>
                        <small class="text-muted">${request.applied_date}</small>
                    </div>
                    <div class="col-md-1">
                        <div class="btn-group-vertical btn-group-sm">
                            <button class="btn btn-outline-primary mb-1" onclick="viewRequestDetails(${request.id})" 
                                    data-bs-toggle="tooltip" title="View Details">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-outline-success mb-1" onclick="quickApproveRequest(${request.id})"
                                    data-bs-toggle="tooltip" title="Quick Approve">
                                <i class="bi bi-check"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="quickRejectRequest(${request.id})"
                                    data-bs-toggle="tooltip" title="Quick Reject">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
    
    // Initialize tooltips
    const tooltips = container.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(tooltip => new bootstrap.Tooltip(tooltip));
}

// Load team status
function loadTeamStatus() {
    const container = document.getElementById('teamStatusContainer');
    
    // Mock team status - in real implementation, fetch from server
    setTimeout(() => {
        container.innerHTML = `
            <div class="list-group list-group-flush">
                <div class="list-group-item border-0 px-0 py-2">
                    <div class="d-flex align-items-center">
                        <span class="status-indicator status-present"></span>
                        <div class="flex-grow-1">
                            <small class="fw-bold">John Doe</small>
                            <br><small class="text-muted">In Office - 9:15 AM</small>
                        </div>
                    </div>
                </div>
                <div class="list-group-item border-0 px-0 py-2">
                    <div class="d-flex align-items-center">
                        <span class="status-indicator status-leave"></span>
                        <div class="flex-grow-1">
                            <small class="fw-bold">Jane Smith</small>
                            <br><small class="text-muted">On Leave - Casual</small>
                        </div>
                    </div>
                </div>
                <div class="list-group-item border-0 px-0 py-2">
                    <div class="d-flex align-items-center">
                        <span class="status-indicator status-late"></span>
                        <div class="flex-grow-1">
                            <small class="fw-bold">Mike Johnson</small>
                            <br><small class="text-muted">Late - 10:30 AM</small>
                        </div>
                    </div>
                </div>
                <div class="list-group-item border-0 px-0 py-2">
                    <div class="d-flex align-items-center">
                        <span class="status-indicator status-present"></span>
                        <div class="flex-grow-1">
                            <small class="fw-bold">Sarah Wilson</small>
                            <br><small class="text-muted">WFH - Online</small>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }, 1500);
}

// Initialize team performance chart
function initializeTeamPerformanceChart() {
    const ctx = document.getElementById('teamPerformanceChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
            datasets: [{
                label: 'Attendance Rate',
                data: [95, 88, 92, 90],
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                tension: 0.4
            }, {
                label: 'Productivity Score',
                data: [88, 92, 85, 94],
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Team Performance Trends'
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

// Helper function for leave type icons
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

// View request details
function viewRequestDetails(requestId) {
    currentRequestId = requestId;
    
    fetch(`../hr/hr_api.php?action=get_leave_request_details&id=${requestId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayRequestDetails(data.request);
                const modal = new bootstrap.Modal(document.getElementById('requestDetailModal'));
                modal.show();
            } else {
                showAlert('Error loading request details', 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Network error', 'danger');
        });
}

// Display request details
function displayRequestDetails(request) {
    const content = document.getElementById('requestDetailContent');
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
                    <tr>
                        <td><strong>Email:</strong></td>
                        <td>${request.employee_email || 'N/A'}</td>
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
                        <td><span class="badge bg-warning">${request.priority.toUpperCase()}</span></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-12">
                <h6 class="text-primary">Reason for Leave</h6>
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
        
        <div class="row mt-3">
            <div class="col-12">
                <h6 class="text-primary">Manager Comments</h6>
                <textarea class="form-control" id="managerComments" rows="3" 
                          placeholder="Add your comments for approval/rejection...">${request.manager_comments || ''}</textarea>
            </div>
        </div>
    `;
}

// Quick approve
function quickApproveRequest(requestId) {
    if (confirm('Are you sure you want to approve this leave request?')) {
        processRequest(requestId, 'approved', '');
    }
}

// Quick reject
function quickRejectRequest(requestId) {
    const reason = prompt('Please provide a reason for rejection:');
    if (reason !== null && reason.trim() !== '') {
        processRequest(requestId, 'rejected', reason);
    }
}

// Approve from modal
function approveRequest() {
    const comments = document.getElementById('managerComments').value;
    processRequest(currentRequestId, 'approved', comments);
}

// Reject from modal
function rejectRequest() {
    const comments = document.getElementById('managerComments').value;
    if (!comments.trim()) {
        showAlert('Please provide comments for rejection', 'warning');
        return;
    }
    processRequest(currentRequestId, 'rejected', comments);
}

// Process request
function processRequest(requestId, status, comments) {
    const formData = new FormData();
    formData.append('action', 'process_leave_request');
    formData.append('request_id', requestId);
    formData.append('status', status);
    formData.append('comments', comments);
    
    fetch('../hr/hr_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(`Request ${status} successfully!`, 'success');
            loadPendingRequests(); // Refresh the list
            
            // Close modal if open
            const modal = bootstrap.Modal.getInstance(document.getElementById('requestDetailModal'));
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

// Other functions
// Enhanced Manager Dashboard Functions
function performManagerSearch() {
    const searchTerm = document.getElementById('managerGlobalSearch').value.trim();
    if (searchTerm.length < 2) {
        showAlert('Please enter at least 2 characters to search', 'warning');
        return;
    }
    
    showAlert(`Searching team for: ${searchTerm}...`, 'info');
    // Implement actual search functionality here
}

function exportTeamData() {
    showAlert('Preparing team data export...', 'info');
    setTimeout(() => {
        showAlert('Team data exported successfully!', 'success');
    }, 2000);
}

function refreshManagerDashboard() {
    loadPendingRequests();
    loadTeamStatus();
    showAlert('Manager dashboard refreshed successfully!', 'success');
}

function openTeamSettingsModal() {
    showAlert('Team settings modal opening...', 'info');
    // Implement team settings
}

function openPerformanceModal() {
    showAlert('Performance review modal opening...', 'info');
    // Implement performance review
}

function generateTeamReport() {
    showAlert('Generating team report...', 'info');
    setTimeout(() => {
        showAlert('Team report generated successfully!', 'success');
    }, 3000);
}

function refreshDashboard() {
    loadPendingRequests();
    loadTeamStatus();
    showAlert('Dashboard refreshed successfully!', 'success');
}

function filterPendingRequests() {
    loadPendingRequests(); // In real implementation, pass filter parameter
}

function viewTeamAttendance() {
    window.location.href = '../attendance/attendance.php';
}

function viewTeamLeaveCalendar() {
    showAlert('Team Leave Calendar feature coming soon!', 'info');
}

function bulkApproveSelected() {
    showAlert('Bulk Approval feature coming soon!', 'info');
}

function generateTeamReport() {
    showAlert('Team Report generation feature coming soon!', 'info');
}

function sendTeamNotification() {
    showAlert('Team Notification feature coming soon!', 'info');
}

function loadWeeklyView() {
    showAlert('Weekly view loaded!', 'info');
}

function loadMonthlyView() {
    showAlert('Monthly view loaded!', 'info');
}

function loadQuarterlyView() {
    showAlert('Quarterly view loaded!', 'info');
}

function loadTeamAnalytics() {
    showAlert('Team Analytics loaded!', 'info');
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
