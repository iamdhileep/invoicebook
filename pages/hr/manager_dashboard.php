<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include '../../auth_check.php';
include '../../db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - BillBook HRMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        .nav-item {
            margin: 5px 0;
        }
        .nav-link {
            color: rgba(255,255,255,0.8) !important;
            border-radius: 10px;
            margin: 2px 8px;
            transition: all 0.3s ease;
        }
        .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white !important;
            transform: translateX(5px);
        }
        .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white !important;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
            background: #f8f9fa;
            min-height: 100vh;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .tab-content {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .nav-tabs .nav-link {
            color: #667eea;
            border: none;
            border-radius: 10px 10px 0 0;
            margin-right: 5px;
        }
        .nav-tabs .nav-link.active {
            background: #667eea;
            color: white;
        }
        .table {
            border-radius: 10px;
            overflow: hidden;
        }
        .table thead th {
            background: #667eea;
            color: white;
            border: none;
            font-weight: 600;
        }
        .badge {
            font-size: 0.85em;
            padding: 0.5em 0.8em;
        }
        .btn {
            border-radius: 8px;
            font-weight: 500;
        }
        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            transition: border-color 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .modal-content {
            border-radius: 15px;
            border: none;
        }
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
        .progress {
            height: 8px;
            border-radius: 10px;
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
        }
        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        .quick-action-btn {
            background: rgba(255,255,255,0.1);
            border: 2px solid rgba(255,255,255,0.2);
            color: white;
            border-radius: 10px;
            padding: 10px 20px;
            margin: 5px;
            transition: all 0.3s ease;
        }
        .quick-action-btn:hover {
            background: rgba(255,255,255,0.2);
            color: white;
            transform: translateY(-2px);
        }
        .status-pending { color: #ffc107; }
        .status-approved { color: #28a745; }
        .status-rejected { color: #dc3545; }
        .status-present { color: #28a745; }
        .status-absent { color: #dc3545; }
        .status-late { color: #ffc107; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar position-fixed">
        <div class="p-3">
            <h4 class="text-white">Manager Portal</h4>
            <hr class="text-white">
            <ul class="nav nav-pills flex-column">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#dashboard">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#team-management">
                        <i class="fas fa-users me-2"></i>Team Management
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#leave-approval">
                        <i class="fas fa-calendar-check me-2"></i>Leave Approvals
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#attendance-overview">
                        <i class="fas fa-clock me-2"></i>Attendance Overview
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#performance">
                        <i class="fas fa-chart-line me-2"></i>Performance
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#reports">
                        <i class="fas fa-file-alt me-2"></i>Reports
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#schedule">
                        <i class="fas fa-calendar me-2"></i>Schedule
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="tab-content">
            <!-- Dashboard Tab -->
            <div class="tab-pane fade show active" id="dashboard">
                <div class="dashboard-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2><i class="fas fa-tachometer-alt me-3"></i>Manager Dashboard</h2>
                            <p class="mb-0">Welcome back! Here's your team overview for today.</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <button class="quick-action-btn" onclick="quickMarkAttendance()">
                                <i class="fas fa-user-check me-2"></i>Quick Attendance
                            </button>
                            <button class="quick-action-btn" onclick="approveLeave()">
                                <i class="fas fa-check me-2"></i>Approve Leave
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <i class="fas fa-users fa-2x mb-3"></i>
                                <h3 id="total-team-members">0</h3>
                                <p>Team Members</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <i class="fas fa-user-check fa-2x mb-3"></i>
                                <h3 id="present-today">0</h3>
                                <p>Present Today</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <i class="fas fa-calendar-times fa-2x mb-3"></i>
                                <h3 id="pending-leaves">0</h3>
                                <p>Pending Leaves</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <i class="fas fa-chart-line fa-2x mb-3"></i>
                                <h3 id="team-performance">0%</h3>
                                <p>Team Performance</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Overview -->
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-clock me-2"></i>Today's Attendance</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="today-attendance-table">
                                        <thead>
                                            <tr>
                                                <th>Employee</th>
                                                <th>Check In</th>
                                                <th>Check Out</th>
                                                <th>Status</th>
                                                <th>Working Hours</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Dynamic content -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-bell me-2"></i>Notifications</h5>
                            </div>
                            <div class="card-body">
                                <div id="notifications-list">
                                    <!-- Dynamic notifications -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Team Management Tab -->
            <div class="tab-pane fade" id="team-management">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3><i class="fas fa-users me-3"></i>Team Management</h3>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTeamMemberModal">
                        <i class="fas fa-plus me-2"></i>Add Team Member
                    </button>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <input type="text" class="form-control" id="team-search" placeholder="Search team members...">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="department-filter">
                                    <option value="">All Departments</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="status-filter">
                                    <option value="">All Status</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-outline-primary w-100" onclick="exportTeamData()">
                                    <i class="fas fa-download me-2"></i>Export
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover" id="team-table">
                                <thead>
                                    <tr>
                                        <th>Employee ID</th>
                                        <th>Name</th>
                                        <th>Position</th>
                                        <th>Department</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Dynamic content -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Leave Approval Tab -->
            <div class="tab-pane fade" id="leave-approval">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3><i class="fas fa-calendar-check me-3"></i>Leave Approvals</h3>
                    <div>
                        <button class="btn btn-success me-2" onclick="bulkApproveLeaves()">
                            <i class="fas fa-check-double me-2"></i>Bulk Approve
                        </button>
                        <button class="btn btn-outline-primary" onclick="exportLeaveData()">
                            <i class="fas fa-download me-2"></i>Export
                        </button>
                    </div>
                </div>

                <!-- Leave Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <h4 id="pending-leave-count">0</h4>
                                <p>Pending Requests</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h4 id="approved-leave-count">0</h4>
                                <p>Approved This Month</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center">
                                <h4 id="rejected-leave-count">0</h4>
                                <p>Rejected This Month</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h4 id="total-leave-days">0</h4>
                                <p>Total Leave Days</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <select class="form-select" id="leave-status-filter">
                                    <option value="">All Status</option>
                                    <option value="pending">Pending</option>
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="leave-type-filter">
                                    <option value="">All Types</option>
                                    <option value="sick">Sick Leave</option>
                                    <option value="casual">Casual Leave</option>
                                    <option value="annual">Annual Leave</option>
                                    <option value="emergency">Emergency Leave</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="date" class="form-control" id="leave-date-filter">
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-primary w-100" onclick="filterLeaveRequests()">
                                    <i class="fas fa-filter me-2"></i>Filter
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover" id="leave-requests-table">
                                <thead>
                                    <tr>
                                        <th>
                                            <input type="checkbox" id="select-all-leaves">
                                        </th>
                                        <th>Employee</th>
                                        <th>Leave Type</th>
                                        <th>From Date</th>
                                        <th>To Date</th>
                                        <th>Days</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Dynamic content -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Attendance Overview Tab -->
            <div class="tab-pane fade" id="attendance-overview">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3><i class="fas fa-clock me-3"></i>Attendance Overview</h3>
                    <div>
                        <button class="btn btn-primary me-2" onclick="markTeamAttendance()">
                            <i class="fas fa-user-check me-2"></i>Mark Attendance
                        </button>
                        <button class="btn btn-outline-primary" onclick="exportAttendanceData()">
                            <i class="fas fa-download me-2"></i>Export
                        </button>
                    </div>
                </div>

                <!-- Attendance Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h4 id="present-count">0</h4>
                                <p>Present Today</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center">
                                <h4 id="absent-count">0</h4>
                                <p>Absent Today</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <h4 id="late-count">0</h4>
                                <p>Late Today</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h4 id="avg-attendance">0%</h4>
                                <p>Avg Attendance</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <input type="date" class="form-control" id="attendance-date-filter" value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="attendance-status-filter">
                                    <option value="">All Status</option>
                                    <option value="Present">Present</option>
                                    <option value="Absent">Absent</option>
                                    <option value="Late">Late</option>
                                    <option value="Half Day">Half Day</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="attendance-employee-filter">
                                    <option value="">All Employees</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-primary w-100" onclick="filterAttendance()">
                                    <i class="fas fa-filter me-2"></i>Filter
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover" id="attendance-table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Date</th>
                                        <th>Check In</th>
                                        <th>Check Out</th>
                                        <th>Working Hours</th>
                                        <th>Overtime</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Dynamic content -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Tab -->
            <div class="tab-pane fade" id="performance">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3><i class="fas fa-chart-line me-3"></i>Team Performance</h3>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPerformanceModal">
                        <i class="fas fa-plus me-2"></i>Add Review
                    </button>
                </div>

                <!-- Performance Summary -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5>Performance Overview</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="performanceChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5>Top Performers</h5>
                            </div>
                            <div class="card-body">
                                <div id="top-performers-list">
                                    <!-- Dynamic content -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="performance-table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Review Period</th>
                                        <th>Overall Rating</th>
                                        <th>Goals Rating</th>
                                        <th>Reviewer</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Dynamic content -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reports Tab -->
            <div class="tab-pane fade" id="reports">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3><i class="fas fa-file-alt me-3"></i>Team Reports</h3>
                    <button class="btn btn-primary" onclick="generateCustomReport()">
                        <i class="fas fa-chart-bar me-2"></i>Custom Report
                    </button>
                </div>

                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-users fa-3x text-primary mb-3"></i>
                                <h5>Team Report</h5>
                                <p>Comprehensive team performance and attendance report</p>
                                <button class="btn btn-primary" onclick="generateTeamReport()">Generate</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-clock fa-3x text-success mb-3"></i>
                                <h5>Attendance Report</h5>
                                <p>Detailed attendance analysis for your team</p>
                                <button class="btn btn-success" onclick="generateAttendanceReport()">Generate</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-calendar-times fa-3x text-warning mb-3"></i>
                                <h5>Leave Report</h5>
                                <p>Leave patterns and balance analysis</p>
                                <button class="btn btn-warning" onclick="generateLeaveReport()">Generate</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5>Recent Reports</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="reports-table">
                                <thead>
                                    <tr>
                                        <th>Report Name</th>
                                        <th>Type</th>
                                        <th>Generated On</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Monthly Team Performance</td>
                                        <td><span class="badge bg-primary">Performance</span></td>
                                        <td><?= date('Y-m-d H:i') ?></td>
                                        <td><span class="badge bg-success">Ready</span></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary me-1">
                                                <i class="fas fa-download"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Schedule Tab -->
            <div class="tab-pane fade" id="schedule">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3><i class="fas fa-calendar me-3"></i>Team Schedule</h3>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                        <i class="fas fa-plus me-2"></i>Add Schedule
                    </button>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5>Weekly Schedule</h5>
                            </div>
                            <div class="card-body">
                                <div id="calendar-container">
                                    <!-- Calendar will be rendered here -->
                                    <div class="text-center py-5">
                                        <i class="fas fa-calendar fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">Schedule calendar will be displayed here</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5>Upcoming Events</h5>
                            </div>
                            <div class="card-body">
                                <div id="upcoming-events">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        No upcoming events scheduled
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Team Member Modal -->
    <div class="modal fade" id="addTeamMemberModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Team Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addTeamMemberForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Employee Code</label>
                                    <input type="text" class="form-control" name="employee_code" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" name="name" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" class="form-control" name="phone">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Position</label>
                                    <input type="text" class="form-control" name="position" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Department</label>
                                    <select class="form-select" name="department" required>
                                        <option value="">Select Department</option>
                                        <option value="IT">Information Technology</option>
                                        <option value="HR">Human Resources</option>
                                        <option value="Finance">Finance</option>
                                        <option value="Sales">Sales</option>
                                        <option value="Marketing">Marketing</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Salary</label>
                                    <input type="number" class="form-control" name="salary" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Hire Date</label>
                                    <input type="date" class="form-control" name="hire_date" required>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveTeamMember()">Save Member</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Leave Action Modal -->
    <div class="modal fade" id="leaveActionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Leave Request Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="leave-details">
                        <!-- Leave details will be populated here -->
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Comments</label>
                        <textarea class="form-control" id="approver-comments" rows="3" placeholder="Add your comments..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger me-2" onclick="rejectLeave()">Reject</button>
                    <button type="button" class="btn btn-success" onclick="approveLeaveRequest()">Approve</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        // Global variables
        let currentLeaveId = null;
        let teamData = [];
        let leaveRequests = [];
        let attendanceData = [];

        // Initialize dashboard
        $(document).ready(function() {
            loadDashboardData();
            loadTeamData();
            loadLeaveRequests();
            loadAttendanceData();
            setupEventListeners();
        });

        // Setup event listeners
        function setupEventListeners() {
            // Tab change events
            $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
                const target = $(e.target).attr("href");
                if (target === '#team-management') {
                    loadTeamData();
                } else if (target === '#leave-approval') {
                    loadLeaveRequests();
                } else if (target === '#attendance-overview') {
                    loadAttendanceData();
                } else if (target === '#performance') {
                    loadPerformanceData();
                }
            });

            // Search and filter events
            $('#team-search').on('keyup', function() {
                filterTeamData();
            });

            $('#department-filter, #status-filter').on('change', function() {
                filterTeamData();
            });

            // Leave filters
            $('#leave-status-filter, #leave-type-filter, #leave-date-filter').on('change', function() {
                filterLeaveRequests();
            });

            // Attendance filters
            $('#attendance-date-filter, #attendance-status-filter, #attendance-employee-filter').on('change', function() {
                filterAttendance();
            });

            // Select all checkbox
            $('#select-all-leaves').on('change', function() {
                $('.leave-checkbox').prop('checked', this.checked);
            });
        }

        // Load dashboard data
        function loadDashboardData() {
            $.ajax({
                url: 'manager_api.php',
                method: 'POST',
                data: { action: 'get_dashboard_stats' },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#total-team-members').text(response.data.total_team_members);
                        $('#present-today').text(response.data.present_today);
                        $('#pending-leaves').text(response.data.pending_leaves);
                        $('#team-performance').text(response.data.team_performance + '%');
                        
                        loadTodayAttendance();
                        loadNotifications();
                    }
                },
                error: function() {
                    showAlert('Error loading dashboard data', 'danger');
                }
            });
        }

        // Load today's attendance
        function loadTodayAttendance() {
            $.ajax({
                url: 'manager_api.php',
                method: 'POST',
                data: { action: 'get_today_attendance' },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        let html = '';
                        response.data.forEach(function(record) {
                            html += `<tr>
                                <td>${record.name}</td>
                                <td>${record.time_in || '-'}</td>
                                <td>${record.time_out || '-'}</td>
                                <td><span class="badge bg-${getStatusColor(record.status)}">${record.status}</span></td>
                                <td>${record.working_hours || '0.00'} hrs</td>
                            </tr>`;
                        });
                        $('#today-attendance-table tbody').html(html);
                    }
                }
            });
        }

        // Load notifications
        function loadNotifications() {
            $.ajax({
                url: 'manager_api.php',
                method: 'POST',
                data: { action: 'get_notifications' },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        let html = '';
                        response.data.forEach(function(notification) {
                            html += `<div class="alert alert-${notification.type} alert-dismissible fade show">
                                <small class="text-muted">${notification.time}</small>
                                <p class="mb-0">${notification.message}</p>
                            </div>`;
                        });
                        $('#notifications-list').html(html);
                    }
                }
            });
        }

        // Load team data
        function loadTeamData() {
            $.ajax({
                url: 'manager_api.php',
                method: 'POST',
                data: { action: 'get_team_members' },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        teamData = response.data;
                        displayTeamData(teamData);
                        populateFilters();
                    }
                },
                error: function() {
                    showAlert('Error loading team data', 'danger');
                }
            });
        }

        // Display team data
        function displayTeamData(data) {
            let html = '';
            data.forEach(function(member) {
                html += `<tr>
                    <td>${member.employee_code}</td>
                    <td>${member.name}</td>
                    <td>${member.position}</td>
                    <td>${member.department}</td>
                    <td>${member.email}</td>
                    <td>${member.phone}</td>
                    <td><span class="badge bg-${member.status === 'active' ? 'success' : 'secondary'}">${member.status}</span></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary me-1" onclick="viewTeamMember(${member.employee_id})">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-success me-1" onclick="editTeamMember(${member.employee_id})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="removeTeamMember(${member.employee_id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>`;
            });
            $('#team-table tbody').html(html);
        }

        // Load leave requests
        function loadLeaveRequests() {
            $.ajax({
                url: 'manager_api.php',
                method: 'POST',
                data: { action: 'get_leave_requests' },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        leaveRequests = response.data;
                        displayLeaveRequests(leaveRequests);
                        updateLeaveStats();
                    }
                },
                error: function() {
                    showAlert('Error loading leave requests', 'danger');
                }
            });
        }

        // Display leave requests
        function displayLeaveRequests(data) {
            let html = '';
            data.forEach(function(request) {
                html += `<tr>
                    <td><input type="checkbox" class="leave-checkbox" value="${request.leave_id}"></td>
                    <td>${request.employee_name}</td>
                    <td><span class="badge bg-info">${request.leave_type}</span></td>
                    <td>${request.from_date}</td>
                    <td>${request.to_date}</td>
                    <td>${request.days_requested}</td>
                    <td>${request.reason}</td>
                    <td><span class="badge bg-${getLeaveStatusColor(request.status)}">${request.status}</span></td>
                    <td>
                        ${request.status === 'pending' ? 
                            `<button class="btn btn-sm btn-success me-1" onclick="openLeaveActionModal(${request.leave_id}, 'approve')">
                                <i class="fas fa-check"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="openLeaveActionModal(${request.leave_id}, 'reject')">
                                <i class="fas fa-times"></i>
                            </button>` :
                            `<button class="btn btn-sm btn-outline-primary" onclick="viewLeaveDetails(${request.leave_id})">
                                <i class="fas fa-eye"></i>
                            </button>`
                        }
                    </td>
                </tr>`;
            });
            $('#leave-requests-table tbody').html(html);
        }

        // Update leave statistics
        function updateLeaveStats() {
            const pending = leaveRequests.filter(r => r.status === 'pending').length;
            const approved = leaveRequests.filter(r => r.status === 'approved').length;
            const rejected = leaveRequests.filter(r => r.status === 'rejected').length;
            const totalDays = leaveRequests.reduce((sum, r) => sum + parseInt(r.days_requested), 0);

            $('#pending-leave-count').text(pending);
            $('#approved-leave-count').text(approved);
            $('#rejected-leave-count').text(rejected);
            $('#total-leave-days').text(totalDays);
        }

        // Load attendance data
        function loadAttendanceData() {
            const date = $('#attendance-date-filter').val() || new Date().toISOString().split('T')[0];
            
            $.ajax({
                url: 'manager_api.php',
                method: 'POST',
                data: { action: 'get_attendance_data', date: date },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        attendanceData = response.data;
                        displayAttendanceData(attendanceData);
                        updateAttendanceStats();
                    }
                },
                error: function() {
                    showAlert('Error loading attendance data', 'danger');
                }
            });
        }

        // Display attendance data
        function displayAttendanceData(data) {
            let html = '';
            data.forEach(function(record) {
                html += `<tr>
                    <td>${record.employee_name}</td>
                    <td>${record.attendance_date}</td>
                    <td>${record.time_in || '-'}</td>
                    <td>${record.time_out || '-'}</td>
                    <td>${record.working_hours || '0.00'} hrs</td>
                    <td>${record.overtime_hours || '0.00'} hrs</td>
                    <td><span class="badge bg-${getStatusColor(record.status)}">${record.status}</span></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary me-1" onclick="editAttendance(${record.attendance_id})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-info" onclick="viewAttendanceDetails(${record.attendance_id})">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>`;
            });
            $('#attendance-table tbody').html(html);
        }

        // Update attendance statistics
        function updateAttendanceStats() {
            const present = attendanceData.filter(r => r.status === 'Present').length;
            const absent = attendanceData.filter(r => r.status === 'Absent').length;
            const late = attendanceData.filter(r => r.status === 'Late').length;
            const total = attendanceData.length;
            const avgAttendance = total > 0 ? Math.round((present / total) * 100) : 0;

            $('#present-count').text(present);
            $('#absent-count').text(absent);
            $('#late-count').text(late);
            $('#avg-attendance').text(avgAttendance + '%');
        }

        // Utility functions
        function getStatusColor(status) {
            switch(status) {
                case 'Present': return 'success';
                case 'Absent': return 'danger';
                case 'Late': return 'warning';
                case 'Half Day': return 'info';
                default: return 'secondary';
            }
        }

        function getLeaveStatusColor(status) {
            switch(status) {
                case 'pending': return 'warning';
                case 'approved': return 'success';
                case 'rejected': return 'danger';
                default: return 'secondary';
            }
        }

        // Action functions
        function saveTeamMember() {
            const formData = new FormData($('#addTeamMemberForm')[0]);
            formData.append('action', 'add_team_member');

            $.ajax({
                url: 'manager_api.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert('Team member added successfully', 'success');
                        $('#addTeamMemberModal').modal('hide');
                        $('#addTeamMemberForm')[0].reset();
                        loadTeamData();
                    } else {
                        showAlert(response.message, 'danger');
                    }
                },
                error: function() {
                    showAlert('Error adding team member', 'danger');
                }
            });
        }

        function openLeaveActionModal(leaveId, action) {
            currentLeaveId = leaveId;
            const leave = leaveRequests.find(l => l.leave_id == leaveId);
            
            if (leave) {
                $('#leave-details').html(`
                    <div class="row">
                        <div class="col-md-6"><strong>Employee:</strong> ${leave.employee_name}</div>
                        <div class="col-md-6"><strong>Leave Type:</strong> ${leave.leave_type}</div>
                        <div class="col-md-6"><strong>From Date:</strong> ${leave.from_date}</div>
                        <div class="col-md-6"><strong>To Date:</strong> ${leave.to_date}</div>
                        <div class="col-md-6"><strong>Days:</strong> ${leave.days_requested}</div>
                        <div class="col-md-6"><strong>Applied Date:</strong> ${leave.applied_date}</div>
                        <div class="col-12"><strong>Reason:</strong> ${leave.reason}</div>
                    </div>
                `);
                $('#leaveActionModal').modal('show');
            }
        }

        function approveLeaveRequest() {
            const comments = $('#approver-comments').val();
            
            $.ajax({
                url: 'manager_api.php',
                method: 'POST',
                data: { 
                    action: 'approve_leave', 
                    leave_id: currentLeaveId, 
                    comments: comments 
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert('Leave request approved successfully', 'success');
                        $('#leaveActionModal').modal('hide');
                        loadLeaveRequests();
                    } else {
                        showAlert(response.message, 'danger');
                    }
                },
                error: function() {
                    showAlert('Error approving leave request', 'danger');
                }
            });
        }

        function rejectLeave() {
            const comments = $('#approver-comments').val();
            
            $.ajax({
                url: 'manager_api.php',
                method: 'POST',
                data: { 
                    action: 'reject_leave', 
                    leave_id: currentLeaveId, 
                    comments: comments 
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert('Leave request rejected', 'info');
                        $('#leaveActionModal').modal('hide');
                        loadLeaveRequests();
                    } else {
                        showAlert(response.message, 'danger');
                    }
                },
                error: function() {
                    showAlert('Error rejecting leave request', 'danger');
                }
            });
        }

        // Filter functions
        function filterTeamData() {
            const search = $('#team-search').val().toLowerCase();
            const department = $('#department-filter').val();
            const status = $('#status-filter').val();

            let filtered = teamData.filter(member => {
                const matchesSearch = member.name.toLowerCase().includes(search) || 
                                    member.employee_code.toLowerCase().includes(search) ||
                                    member.email.toLowerCase().includes(search);
                const matchesDepartment = !department || member.department === department;
                const matchesStatus = !status || member.status === status;

                return matchesSearch && matchesDepartment && matchesStatus;
            });

            displayTeamData(filtered);
        }

        function filterLeaveRequests() {
            const status = $('#leave-status-filter').val();
            const type = $('#leave-type-filter').val();
            const date = $('#leave-date-filter').val();

            let filtered = leaveRequests.filter(request => {
                const matchesStatus = !status || request.status === status;
                const matchesType = !type || request.leave_type === type;
                const matchesDate = !date || request.from_date === date || request.to_date === date;

                return matchesStatus && matchesType && matchesDate;
            });

            displayLeaveRequests(filtered);
        }

        function filterAttendance() {
            const date = $('#attendance-date-filter').val();
            const status = $('#attendance-status-filter').val();
            const employee = $('#attendance-employee-filter').val();

            if (date) {
                loadAttendanceData();
            }
        }

        // Export functions
        function exportTeamData() {
            window.open('manager_api.php?action=export_team_data', '_blank');
        }

        function exportLeaveData() {
            window.open('manager_api.php?action=export_leave_data', '_blank');
        }

        function exportAttendanceData() {
            window.open('manager_api.php?action=export_attendance_data', '_blank');
        }

        // Quick action functions
        function quickMarkAttendance() {
            // Implement quick attendance marking
            showAlert('Quick attendance feature coming soon', 'info');
        }

        function approveLeave() {
            // Quick approve latest leave request
            const pendingLeaves = leaveRequests.filter(l => l.status === 'pending');
            if (pendingLeaves.length > 0) {
                openLeaveActionModal(pendingLeaves[0].leave_id, 'approve');
            } else {
                showAlert('No pending leave requests', 'info');
            }
        }

        // Utility function to show alerts
        function showAlert(message, type) {
            const alert = `<div class="alert alert-${type} alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999;">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>`;
            $('body').append(alert);
            
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        }
    </script>
</body>
</html>
