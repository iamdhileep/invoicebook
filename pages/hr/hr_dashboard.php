<?php
session_start();

// Set HR session for demo (before auth check)
if (!isset($_SESSION['admin'])) {
    $_SESSION['admin'] = 1;
    $_SESSION['user_id'] = 1; // For auth compatibility
    $_SESSION['role'] = 'hr';
}

include '../../db.php';
$page_title = 'HR Dashboard';

// Get dashboard statistics
$stats = [
    'total_employees' => 0,
    'active_employees' => 0,
    'pending_leaves' => 0,
    'today_attendance' => 0
];

try {
    // Get total employees
    $result = $conn->query("SELECT COUNT(*) as total FROM employees");
    if ($result) $stats['total_employees'] = $result->fetch_assoc()['total'];
    
    // Get active employees
    $result = $conn->query("SELECT COUNT(*) as total FROM employees WHERE status = 'active'");
    if ($result) $stats['active_employees'] = $result->fetch_assoc()['total'];
    
    // Get pending leaves
    $result = $conn->query("SELECT COUNT(*) as total FROM leave_requests WHERE status = 'pending'");
    if ($result) $stats['pending_leaves'] = $result->fetch_assoc()['total'];
    
    // Get today's attendance
    $result = $conn->query("SELECT COUNT(*) as total FROM attendance WHERE attendance_date = CURDATE()");
    if ($result) $stats['today_attendance'] = $result->fetch_assoc()['total'];
    
} catch (Exception $e) {
    error_log("HR Dashboard error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - HRMS Portal</title>
    
    <!-- Bootstrap 5.3.2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Global UI Design CSS -->
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --info-color: #0dcaf0;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --dark-color: #212529;
            --light-color: #f8f9fa;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            border-radius: 8px;
            margin: 2px 0;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover {
            color: white;
            background: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }
        
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.2);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .main-content {
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.08);
            margin: 15px;
            padding: 0;
            overflow: hidden;
        }
        
        .content-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 15px 15px 0 0;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 4px solid;
            transition: transform 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.primary { border-left-color: var(--primary-color); }
        .stat-card.success { border-left-color: var(--success-color); }
        .stat-card.warning { border-left-color: var(--warning-color); }
        .stat-card.info { border-left-color: var(--info-color); }
        
        .nav-tabs {
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 20px;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
            padding: 12px 24px;
            border-radius: 8px 8px 0 0;
            margin-right: 5px;
        }
        
        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        
        .nav-tabs .nav-link:hover {
            border: none;
            background: #f8f9fa;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .card-header {
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            border-radius: 12px 12px 0 0;
            padding: 15px 20px;
            font-weight: 600;
        }
        
        .btn {
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .loading-spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .table {
            border-radius: 8px;
            overflow: hidden;
        }
        
        .table thead th {
            background: #f8f9fa;
            border: none;
            padding: 15px;
            font-weight: 600;
            color: #495057;
        }
        
        .table tbody td {
            padding: 12px 15px;
            border-color: #f1f3f4;
        }
        
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .modal-content {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            border-bottom: 1px solid #e9ecef;
            border-radius: 15px 15px 0 0;
            background: #f8f9fa;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #e9ecef;
            padding: 10px 15px;
            transition: all 0.2s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-2 p-0">
                <div class="sidebar">
                    <div class="p-4 text-center border-bottom border-light border-opacity-25">
                        <h4 class="text-white mb-0">
                            <i class="fas fa-building me-2"></i>HRMS Portal
                        </h4>
                        <small class="text-white-50">Human Resource Management</small>
                    </div>
                    <nav class="nav flex-column p-3">
                        <a href="../../portal_dashboard.php" class="nav-link">
                            <i class="fas fa-home me-2"></i>Portal Home
                        </a>
                        <a href="../hr/hr_dashboard.php" class="nav-link active">
                            <i class="fas fa-user-tie me-2"></i>HR Dashboard
                        </a>
                        <a href="../manager/manager_dashboard.php" class="nav-link">
                            <i class="fas fa-users me-2"></i>Manager Portal
                        </a>
                        <a href="../employee/employee_portal.php" class="nav-link">
                            <i class="fas fa-user me-2"></i>Employee Portal
                        </a>
                        <div class="border-top border-light border-opacity-25 my-3"></div>
                        <a href="../../employees.php" class="nav-link">
                            <i class="fas fa-users me-2"></i>All Employees
                        </a>
                        <a href="../../attendance.php" class="nav-link">
                            <i class="fas fa-clock me-2"></i>Attendance
                        </a>
                        <a href="../../reports.php" class="nav-link">
                            <i class="fas fa-chart-bar me-2"></i>Reports
                        </a>
                        <div class="border-top border-light border-opacity-25 my-3"></div>
                        <a href="../../logout.php" class="nav-link">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </nav>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-lg-10">
                <div class="main-content">
                    <!-- Header -->
                    <div class="content-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="mb-1">
                                    <i class="fas fa-user-tie me-3"></i>HR Dashboard
                                </h2>
                                <p class="mb-0 opacity-75">Complete human resource management system</p>
                            </div>
                            <div>
                                <button class="btn btn-light btn-sm me-2" onclick="refreshDashboard()">
                                    <i class="fas fa-sync-alt me-1"></i>Refresh
                                </button>
                                <button class="btn btn-success btn-sm me-2" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                                    <i class="fas fa-user-plus me-1"></i>Add Employee
                                </button>
                                <button class="btn btn-warning btn-sm" onclick="generatePayroll()">
                                    <i class="fas fa-money-bill me-1"></i>Generate Payroll
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Statistics Cards -->
                    <div class="p-4">
                        <div class="row g-4 mb-4">
                            <div class="col-xl-3 col-md-6">
                                <div class="stat-card primary">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h3 class="mb-1 text-primary"><?php echo $stats['total_employees']; ?></h3>
                                            <p class="mb-0 text-muted">Total Employees</p>
                                        </div>
                                        <div class="text-primary opacity-50">
                                            <i class="fas fa-users fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="stat-card success">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h3 class="mb-1 text-success"><?php echo $stats['active_employees']; ?></h3>
                                            <p class="mb-0 text-muted">Active Employees</p>
                                        </div>
                                        <div class="text-success opacity-50">
                                            <i class="fas fa-user-check fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="stat-card warning">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h3 class="mb-1 text-warning"><?php echo $stats['pending_leaves']; ?></h3>
                                            <p class="mb-0 text-muted">Pending Leaves</p>
                                        </div>
                                        <div class="text-warning opacity-50">
                                            <i class="fas fa-calendar-alt fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="stat-card info">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h3 class="mb-1 text-info"><?php echo $stats['today_attendance']; ?></h3>
                                            <p class="mb-0 text-muted">Today's Attendance</p>
                                        </div>
                                        <div class="text-info opacity-50">
                                            <i class="fas fa-clock fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Navigation Tabs -->
                        <ul class="nav nav-tabs" id="hrTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="employees-tab" data-bs-toggle="tab" data-bs-target="#employees" type="button" role="tab">
                                    <i class="fas fa-users me-2"></i>Employee Management
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="leaves-tab" data-bs-toggle="tab" data-bs-target="#leaves" type="button" role="tab">
                                    <i class="fas fa-calendar-alt me-2"></i>Leave Requests
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button" role="tab">
                                    <i class="fas fa-clock me-2"></i>Attendance
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="payroll-tab" data-bs-toggle="tab" data-bs-target="#payroll" type="button" role="tab">
                                    <i class="fas fa-money-bill me-2"></i>Payroll
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports" type="button" role="tab">
                                    <i class="fas fa-chart-bar me-2"></i>Reports
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="analytics-tab" data-bs-toggle="tab" data-bs-target="#analytics" type="button" role="tab">
                                    <i class="fas fa-chart-line me-2"></i>Analytics & ML
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="biometric-tab" data-bs-toggle="tab" data-bs-target="#biometric" type="button" role="tab">
                                    <i class="fas fa-fingerprint me-2"></i>Biometric System
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="compliance-tab" data-bs-toggle="tab" data-bs-target="#compliance" type="button" role="tab">
                                    <i class="fas fa-shield-alt me-2"></i>Compliance
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="workflows-tab" data-bs-toggle="tab" data-bs-target="#workflows" type="button" role="tab">
                                    <i class="fas fa-sitemap me-2"></i>Workflows
                                </button>
                            </li>
                        </ul>
                        
                        <!-- Tab Content -->
                        <div class="tab-content" id="hrTabContent">
                            <!-- Employee Management Tab -->
                            <div class="tab-pane fade show active" id="employees" role="tabpanel">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">Employee Management</h5>
                                        <div>
                                            <input type="text" class="form-control d-inline-block w-auto me-2" placeholder="Search employees..." id="employeeSearch">
                                            <button class="btn btn-outline-primary btn-sm" onclick="exportEmployees()">
                                                <i class="fas fa-download me-1"></i>Export
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover" id="employeesTable">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Name</th>
                                                        <th>Email</th>
                                                        <th>Department</th>
                                                        <th>Position</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td colspan="7" class="text-center py-4">
                                                            <div class="loading-spinner me-2"></div>
                                                            Loading employees...
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Leave Requests Tab -->
                            <div class="tab-pane fade" id="leaves" role="tabpanel">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Leave Requests Management</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover" id="leaveRequestsTable">
                                                <thead>
                                                    <tr>
                                                        <th>Employee</th>
                                                        <th>Leave Type</th>
                                                        <th>From Date</th>
                                                        <th>To Date</th>
                                                        <th>Days</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td colspan="7" class="text-center py-4">
                                                            <div class="loading-spinner me-2"></div>
                                                            Loading leave requests...
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Attendance Tab -->
                            <div class="tab-pane fade" id="attendance" role="tabpanel">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">Attendance Management</h5>
                                        <div>
                                            <input type="date" class="form-control d-inline-block w-auto" id="attendanceDate" value="<?php echo date('Y-m-d'); ?>">
                                            <button class="btn btn-primary btn-sm ms-2" onclick="loadAttendance()">
                                                <i class="fas fa-search me-1"></i>Load
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover" id="attendanceTable">
                                                <thead>
                                                    <tr>
                                                        <th>Employee</th>
                                                        <th>Date</th>
                                                        <th>Time In</th>
                                                        <th>Time Out</th>
                                                        <th>Working Hours</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td colspan="7" class="text-center py-4">
                                                            <div class="loading-spinner me-2"></div>
                                                            Loading attendance...
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Payroll Tab -->
                            <div class="tab-pane fade" id="payroll" role="tabpanel">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">Payroll Management</h5>
                                        <div>
                                            <select class="form-select d-inline-block w-auto me-2" id="payrollMonth">
                                                <option value="1">January</option>
                                                <option value="2">February</option>
                                                <option value="3">March</option>
                                                <option value="4">April</option>
                                                <option value="5">May</option>
                                                <option value="6">June</option>
                                                <option value="7" selected>July</option>
                                                <option value="8">August</option>
                                                <option value="9">September</option>
                                                <option value="10">October</option>
                                                <option value="11">November</option>
                                                <option value="12">December</option>
                                            </select>
                                            <select class="form-select d-inline-block w-auto me-2" id="payrollYear">
                                                <option value="2023">2023</option>
                                                <option value="2024">2024</option>
                                                <option value="2025" selected>2025</option>
                                                <option value="2026">2026</option>
                                            </select>
                                            <button class="btn btn-primary btn-sm" onclick="loadPayroll()">
                                                <i class="fas fa-search me-1"></i>Load
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover" id="payrollTable">
                                                <thead>
                                                    <tr>
                                                        <th>Employee</th>
                                                        <th>Basic Salary</th>
                                                        <th>Allowances</th>
                                                        <th>Deductions</th>
                                                        <th>Net Salary</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td colspan="7" class="text-center py-4">
                                                            <div class="loading-spinner me-2"></div>
                                                            Loading payroll...
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Reports Tab -->
                            <div class="tab-pane fade" id="reports" role="tabpanel">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="mb-0">Generate Reports</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="d-grid gap-3">
                                                    <button class="btn btn-outline-primary" onclick="generateReport('employee')">
                                                        <i class="fas fa-users me-2"></i>Employee Report
                                                    </button>
                                                    <button class="btn btn-outline-success" onclick="generateReport('attendance')">
                                                        <i class="fas fa-clock me-2"></i>Attendance Report
                                                    </button>
                                                    <button class="btn btn-outline-warning" onclick="generateReport('leave')">
                                                        <i class="fas fa-calendar-alt me-2"></i>Leave Report
                                                    </button>
                                                    <button class="btn btn-outline-info" onclick="generateReport('payroll')">
                                                        <i class="fas fa-money-bill me-2"></i>Payroll Report
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="mb-0">Quick Statistics</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="mb-3">
                                                    <small class="text-muted">This Month</small>
                                                    <div class="d-flex justify-content-between">
                                                        <span>New Employees</span>
                                                        <strong>5</strong>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <small class="text-muted">This Month</small>
                                                    <div class="d-flex justify-content-between">
                                                        <span>Approved Leaves</span>
                                                        <strong>12</strong>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <small class="text-muted">This Month</small>
                                                    <div class="d-flex justify-content-between">
                                                        <span>Average Attendance</span>
                                                        <strong>87%</strong>
                                                    </div>
                                                </div>
                                                <div class="mb-0">
                                                    <small class="text-muted">This Month</small>
                                                    <div class="d-flex justify-content-between">
                                                        <span>Payroll Processed</span>
                                                        <strong>₹2,45,000</strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Advanced Analytics & ML Tab -->
                            <div class="tab-pane fade" id="analytics" role="tabpanel">
                                <div class="row g-4">
                                    <div class="col-12">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="mb-0"><i class="fas fa-brain me-2"></i>AI-Powered Analytics Dashboard</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="row g-3 mb-4">
                                                    <div class="col-md-4">
                                                        <button class="btn btn-outline-primary w-100" onclick="loadMLInsights('attendance_prediction')">
                                                            <i class="fas fa-chart-line me-2"></i>Attendance Prediction
                                                        </button>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <button class="btn btn-outline-success w-100" onclick="loadMLInsights('cost_analysis')">
                                                            <i class="fas fa-dollar-sign me-2"></i>Cost Analysis
                                                        </button>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <button class="btn btn-outline-info w-100" onclick="loadMLInsights('performance_trends')">
                                                            <i class="fas fa-trending-up me-2"></i>Performance Trends
                                                        </button>
                                                    </div>
                                                </div>
                                                <div id="mlInsightsContainer">
                                                    <div class="text-center py-5 text-muted">
                                                        <i class="fas fa-robot fa-3x mb-3"></i>
                                                        <p>Select an analytics option above to view AI-powered insights</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="mb-0"><i class="fas fa-chart-area me-2"></i>Real-time Analytics</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="mb-3">
                                                    <select class="form-select" id="analyticsFilter">
                                                        <option value="week">Last 7 Days</option>
                                                        <option value="month" selected>Last 30 Days</option>
                                                        <option value="quarter">Last 90 Days</option>
                                                        <option value="year">Last Year</option>
                                                    </select>
                                                </div>
                                                <div id="analyticsCharts">
                                                    <canvas id="attendanceChart"></canvas>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Risk Alerts</h5>
                                            </div>
                                            <div class="card-body">
                                                <div id="riskAlerts">
                                                    <div class="loading-spinner me-2"></div>
                                                    Loading risk analysis...
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Biometric System Management Tab -->
                            <div class="tab-pane fade" id="biometric" role="tabpanel">
                                <div class="row g-4">
                                    <div class="col-md-8">
                                        <div class="card">
                                            <div class="card-header d-flex justify-content-between align-items-center">
                                                <h5 class="mb-0"><i class="fas fa-fingerprint me-2"></i>Biometric Devices</h5>
                                                <button class="btn btn-primary btn-sm" onclick="showAddDeviceModal()">
                                                    <i class="fas fa-plus me-2"></i>Add Device
                                                </button>
                                            </div>
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table class="table table-hover" id="biometricDevicesTable">
                                                        <thead>
                                                            <tr>
                                                                <th>Device Name</th>
                                                                <th>Type</th>
                                                                <th>Location</th>
                                                                <th>Status</th>
                                                                <th>Last Sync</th>
                                                                <th>Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr>
                                                                <td colspan="6" class="text-center py-4">
                                                                    <div class="loading-spinner me-2"></div>
                                                                    Loading devices...
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="mb-0"><i class="fas fa-mobile-alt me-2"></i>Mobile GPS Tracking</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="mb-3">
                                                    <label class="form-label">GPS Accuracy Radius (meters)</label>
                                                    <input type="number" class="form-control" id="gpsRadius" value="100">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Office Locations</label>
                                                    <div id="officeLocations">
                                                        <div class="border rounded p-2 mb-2">
                                                            <small class="text-muted">Main Office</small><br>
                                                            <span class="badge bg-success">Active</span>
                                                        </div>
                                                    </div>
                                                    <button class="btn btn-outline-primary btn-sm w-100" onclick="addOfficeLocation()">
                                                        <i class="fas fa-map-marker-alt me-2"></i>Add Location
                                                    </button>
                                                </div>
                                                <div class="mb-3">
                                                    <button class="btn btn-info w-100" onclick="testGPSAccuracy()">
                                                        <i class="fas fa-satellite me-2"></i>Test GPS Accuracy
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="card mt-3">
                                            <div class="card-header">
                                                <h5 class="mb-0"><i class="fas fa-eye me-2"></i>Live Tracking</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="mb-3">
                                                    <small class="text-muted">Active Field Employees</small>
                                                    <div class="text-success h4 mb-0">0</div>
                                                </div>
                                                <button class="btn btn-success w-100" onclick="viewLiveTracking()">
                                                    <i class="fas fa-map me-2"></i>View Live Map
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Compliance Monitor Tab -->
                            <div class="tab-pane fade" id="compliance" role="tabpanel">
                                <div class="row g-4">
                                    <div class="col-12">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Compliance Dashboard</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="row g-3 mb-4">
                                                    <div class="col-md-3">
                                                        <div class="card border-0 bg-light">
                                                            <div class="card-body text-center">
                                                                <div class="h3 text-success mb-1" id="complianceScore">98%</div>
                                                                <small class="text-muted">Overall Compliance</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <div class="card border-0 bg-light">
                                                            <div class="card-body text-center">
                                                                <div class="h3 text-warning mb-1" id="violationsCount">5</div>
                                                                <small class="text-muted">Active Violations</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <div class="card border-0 bg-light">
                                                            <div class="card-body text-center">
                                                                <div class="h3 text-info mb-1" id="overtimeHours">120h</div>
                                                                <small class="text-muted">This Month Overtime</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <div class="card border-0 bg-light">
                                                            <div class="card-body text-center">
                                                                <div class="h3 text-danger mb-1" id="criticalAlerts">2</div>
                                                                <small class="text-muted">Critical Alerts</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div id="complianceViolations">
                                                    <div class="loading-spinner me-2"></div>
                                                    Loading compliance data...
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Overtime Rules</h5>
                                            </div>
                                            <div class="card-body">
                                                <button class="btn btn-primary btn-sm mb-3" onclick="showOvertimeRuleModal()">
                                                    <i class="fas fa-plus me-2"></i>Add Rule
                                                </button>
                                                <div id="overtimeRules">
                                                    <div class="border rounded p-3 mb-2">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <strong>Standard Overtime</strong><br>
                                                                <small class="text-muted">8h daily, 40h weekly @ 1.5x rate</small>
                                                            </div>
                                                            <span class="badge bg-success">Active</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="mb-0"><i class="fas fa-file-contract me-2"></i>Labor Law Compliance</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="mb-3">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <span>Maximum Working Hours</span>
                                                        <span class="badge bg-success">Compliant</span>
                                                    </div>
                                                    <div class="progress" style="height: 6px;">
                                                        <div class="progress-bar bg-success" style="width: 85%"></div>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <span>Minimum Wage</span>
                                                        <span class="badge bg-success">Compliant</span>
                                                    </div>
                                                    <div class="progress" style="height: 6px;">
                                                        <div class="progress-bar bg-success" style="width: 100%"></div>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <span>Overtime Limits</span>
                                                        <span class="badge bg-warning">Needs Review</span>
                                                    </div>
                                                    <div class="progress" style="height: 6px;">
                                                        <div class="progress-bar bg-warning" style="width: 65%"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Workflow Management Tab -->
                            <div class="tab-pane fade" id="workflows" role="tabpanel">
                                <div class="row g-4">
                                    <div class="col-md-8">
                                        <div class="card">
                                            <div class="card-header d-flex justify-content-between align-items-center">
                                                <h5 class="mb-0"><i class="fas fa-sitemap me-2"></i>Approval Workflows</h5>
                                                <button class="btn btn-primary btn-sm" onclick="showWorkflowModal()">
                                                    <i class="fas fa-plus me-2"></i>Create Workflow
                                                </button>
                                            </div>
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table class="table table-hover" id="workflowsTable">
                                                        <thead>
                                                            <tr>
                                                                <th>Workflow Name</th>
                                                                <th>Entity Type</th>
                                                                <th>Department</th>
                                                                <th>Approval Levels</th>
                                                                <th>Status</th>
                                                                <th>Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr>
                                                                <td colspan="6" class="text-center py-4">
                                                                    <div class="loading-spinner me-2"></div>
                                                                    Loading workflows...
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Pending Approvals</h5>
                                            </div>
                                            <div class="card-body">
                                                <div id="pendingApprovals">
                                                    <div class="loading-spinner me-2"></div>
                                                    Loading pending approvals...
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="card mt-3">
                                            <div class="card-header">
                                                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Audit Trail</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="mb-3">
                                                    <select class="form-select form-select-sm" id="auditFilter">
                                                        <option value="all">All Activities</option>
                                                        <option value="attendance">Attendance</option>
                                                        <option value="leave">Leave Requests</option>
                                                        <option value="payroll">Payroll</option>
                                                    </select>
                                                </div>
                                                <div id="auditTrail" style="max-height: 300px; overflow-y: auto;">
                                                    <div class="loading-spinner me-2"></div>
                                                    Loading audit trail...
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
    </div>

    <!-- Add Employee Modal -->
    <div class="modal fade" id="addEmployeeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addEmployeeForm">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name *</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Employee Code</label>
                                <input type="text" class="form-control" name="employee_code">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Department</label>
                                <input type="text" class="form-control" name="department">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Position</label>
                                <input type="text" class="form-control" name="position">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Basic Salary</label>
                                <input type="number" class="form-control" name="basic_salary">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Employee</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize month/year selectors to current date
            const currentMonth = new Date().getMonth() + 1;
            const currentYear = new Date().getFullYear();
            $('#payrollMonth').val(currentMonth);
            $('#payrollYear').val(currentYear);
            
            // Initialize data loading
            loadEmployees();
            loadLeaveRequests();
            loadAttendance();
            
            // Tab change events
            $('#hrTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
                const target = $(e.target).attr('data-bs-target');
                switch(target) {
                    case '#employees':
                        loadEmployees();
                        break;
                    case '#leaves':
                        loadLeaveRequests();
                        break;
                    case '#attendance':
                        loadAttendance();
                        break;
                    case '#payroll':
                        loadPayroll();
                        break;
                }
            });
            
            // Auto-refresh payroll when month/year changes
            $('#payrollMonth, #payrollYear').change(function() {
                if ($('#payroll-tab').hasClass('active')) {
                    loadPayroll();
                }
            });
        });

        // Global Functions
        function refreshDashboard() {
            location.reload();
        }

        function showAlert(message, type = 'info') {
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

        // Employee Management
        function loadEmployees() {
            $.ajax({
                url: './hr_dashboard_api.php',
                method: 'POST',
                data: {
                    action: 'get_employees'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        displayEmployees(response.data);
                    } else {
                        $('#employeesTable tbody').html('<tr><td colspan="7" class="text-center text-danger">Error: ' + response.message + '</td></tr>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    $('#employeesTable tbody').html('<tr><td colspan="7" class="text-center text-danger">Error loading employees</td></tr>');
                }
            });
        }

        function displayEmployees(employees) {
            let html = '';
            employees.forEach(emp => {
                const statusBadge = emp.status === 'active' ? 'badge bg-success' : 'badge bg-secondary';
                html += `
                    <tr>
                        <td>${emp.employee_id}</td>
                        <td>${emp.name}</td>
                        <td>${emp.email || 'N/A'}</td>
                        <td>${emp.department || 'N/A'}</td>
                        <td>${emp.position || 'N/A'}</td>
                        <td><span class="${statusBadge}">${emp.status}</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary me-1" onclick="editEmployee(${emp.employee_id})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteEmployee(${emp.employee_id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            $('#employeesTable tbody').html(html);
        }

        $('#addEmployeeForm').submit(function(e) {
            e.preventDefault();
            
            $.ajax({
                url: './hr_dashboard_api.php',
                method: 'POST',
                data: {
                    action: 'add_employee',
                    ...Object.fromEntries(new FormData(this))
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#addEmployeeModal').modal('hide');
                        $('#addEmployeeForm')[0].reset();
                        loadEmployees();
                        showAlert('Employee added successfully!', 'success');
                    } else {
                        showAlert('Error: ' + response.message, 'danger');
                    }
                }
            });
        });

        // Leave Management
        function loadLeaveRequests() {
            $.ajax({
                url: './hr_dashboard_api.php',
                method: 'POST',
                data: { 
                    action: 'get_leave_requests' 
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        displayLeaveRequests(response.data);
                    } else {
                        $('#leaveRequestsTable tbody').html('<tr><td colspan="7" class="text-center text-danger">Error: ' + response.message + '</td></tr>');
                    }
                }
            });
        }

        function displayLeaveRequests(leaves) {
            let html = '';
            leaves.forEach(leave => {
                const statusClass = getStatusBadgeClass(leave.status);
                const actionButtons = leave.status === 'pending' ? `
                    <button class="btn btn-sm btn-success me-1" onclick="updateLeaveStatus(${leave.id}, 'approved')">
                        <i class="fas fa-check"></i> Approve
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="updateLeaveStatus(${leave.id}, 'rejected')">
                        <i class="fas fa-times"></i> Reject
                    </button>
                ` : '<span class="text-muted">No actions</span>';
                
                html += `
                    <tr>
                        <td>${leave.employee_name || 'Unknown'}</td>
                        <td>${leave.leave_type}</td>
                        <td>${leave.from_date}</td>
                        <td>${leave.to_date}</td>
                        <td>${leave.days_requested || 0}</td>
                        <td><span class="badge ${statusClass}">${leave.status}</span></td>
                        <td>${actionButtons}</td>
                    </tr>
                `;
            });
            $('#leaveRequestsTable tbody').html(html);
        }

        function updateLeaveStatus(leaveId, status) {
            const action = status === 'approved' ? 'approve_leave' : 'reject_leave';
            $.ajax({
                url: './hr_dashboard_api.php',
                method: 'POST',
                data: { 
                    action: action,
                    leave_id: leaveId,
                    hr_comments: prompt('Enter comments (optional):') || ''
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        loadLeaveRequests();
                        showAlert(`Leave ${status} successfully!`, 'success');
                    } else {
                        showAlert('Error: ' + response.message, 'danger');
                    }
                }
            });
        }

        // Attendance Management
        function loadAttendance() {
            const date = $('#attendanceDate').val() || '<?= date('Y-m-d') ?>';
            
            $.ajax({
                url: './hr_dashboard_api.php',
                method: 'POST',
                data: { 
                    action: 'get_attendance_records',
                    date: date
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        displayAttendance(response.data);
                    } else {
                        $('#attendanceTable tbody').html('<tr><td colspan="7" class="text-center text-danger">Error: ' + response.message + '</td></tr>');
                    }
                }
            });
        }

        function displayAttendance(attendance) {
            let html = '';
            attendance.forEach(att => {
                const workingHours = calculateWorkingHours(att.punch_in_time, att.punch_out_time);
                const statusBadge = getStatusBadgeClass(att.status);
                
                html += `
                    <tr>
                        <td>${att.employee_name || 'Unknown'}</td>
                        <td>${att.attendance_date}</td>
                        <td>${att.punch_in_time || '-'}</td>
                        <td>${att.punch_out_time || '-'}</td>
                        <td>${workingHours}</td>
                        <td><span class="badge ${statusBadge}">${att.status}</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="correctAttendance(${att.id})">
                                <i class="fas fa-edit"></i> Correct
                            </button>
                        </td>
                    </tr>
                `;
            });
            $('#attendanceTable tbody').html(html);
        }

        // Payroll Management
        function loadPayroll() {
            const month = $('#payrollMonth').val() || new Date().getMonth() + 1;
            const year = $('#payrollYear').val() || new Date().getFullYear();
            
            // Show loading state
            $('#payrollTable tbody').html(`
                <tr>
                    <td colspan="7" class="text-center py-4">
                        <div class="loading-spinner me-2"></div>
                        Loading payroll...
                    </td>
                </tr>
            `);
            
            $.ajax({
                url: './hr_dashboard_api.php',
                method: 'POST',
                data: { 
                    action: 'get_payroll_data',
                    month: month + '-' + year
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Payroll API Response:', response);
                    if (response.success) {
                        displayPayroll(response.data || []);
                    } else {
                        $('#payrollTable tbody').html(`
                            <tr>
                                <td colspan="7" class="text-center text-danger py-4">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Error: ${response.message || 'Unknown error occurred'}
                                </td>
                            </tr>
                        `);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Payroll AJAX Error:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText,
                        statusCode: xhr.status
                    });
                    
                    let errorMessage = 'Failed to load payroll data';
                    if (xhr.responseText && xhr.responseText.includes('<')) {
                        errorMessage = 'Server error occurred. Check console for details.';
                    } else if (error) {
                        errorMessage = `Network error: ${error}`;
                    }
                    
                    $('#payrollTable tbody').html(`
                        <tr>
                            <td colspan="7" class="text-center text-danger py-4">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                ${errorMessage}
                            </td>
                        </tr>
                    `);
                }
            });
        }

        function displayPayroll(payroll) {
            console.log('Displaying payroll data:', payroll);
            
            if (!payroll || payroll.length === 0) {
                $('#payrollTable tbody').html(`
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="fas fa-info-circle me-2"></i>
                            No payroll records found for the selected period.
                            <br><small>Generate payroll first using the "Generate Payroll" button.</small>
                        </td>
                    </tr>
                `);
                return;
            }
            
            let html = '';
            payroll.forEach(pay => {
                const basicSalary = parseFloat(pay.basic_salary || 0);
                const earnedSalary = parseFloat(pay.earned_salary || basicSalary);
                const allowances = parseFloat(pay.allowances || 0);
                const deductions = parseFloat(pay.deductions || 0);
                
                html += `
                    <tr>
                        <td>
                            <div>
                                <strong>${pay.employee_name || 'Unknown'}</strong>
                                ${pay.employee_code ? `<br><small class="text-muted">ID: ${pay.employee_code}</small>` : ''}
                            </div>
                        </td>
                        <td>₹${basicSalary.toLocaleString()}</td>
                        <td>₹${allowances.toLocaleString()}</td>
                        <td>₹${deductions.toLocaleString()}</td>
                        <td><strong>₹${earnedSalary.toLocaleString()}</strong></td>
                        <td><span class="badge bg-success">${pay.status || 'Processed'}</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="viewPayslip(${pay.id})" title="View Payslip">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-info ms-1" onclick="downloadPayslip(${pay.id})" title="Download Payslip">
                                <i class="fas fa-download"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            $('#payrollTable tbody').html(html);
        }

        // Utility Functions
        function getStatusBadgeClass(status) {
            const statusClasses = {
                'active': 'bg-success',
                'inactive': 'bg-secondary',
                'pending': 'bg-warning',
                'approved': 'bg-success',
                'rejected': 'bg-danger',
                'present': 'bg-success',
                'absent': 'bg-danger',
                'late': 'bg-warning'
            };
            return 'badge ' + (statusClasses[status] || 'bg-secondary');
        }

        function calculateWorkingHours(timeIn, timeOut) {
            if (!timeIn || !timeOut) return '-';
            const start = new Date('2000-01-01 ' + timeIn);
            const end = new Date('2000-01-01 ' + timeOut);
            const diff = (end - start) / (1000 * 60 * 60);
            return diff > 0 ? diff.toFixed(2) + ' hrs' : '-';
        }

        // Action Functions
        function editEmployee(id) {
            // Find employee data from the table
            const row = $(`button[onclick="editEmployee(${id})"]`).closest('tr');
            const employeeData = {
                id: id,
                name: row.find('td:nth-child(2)').text(),
                email: row.find('td:nth-child(3)').text(),
                department: row.find('td:nth-child(4)').text(),
                position: row.find('td:nth-child(5)').text(),
                status: row.find('td:nth-child(6) .badge').text()
            };
            
            // Create edit modal
            const modal = `
                <div class="modal fade" id="editEmployeeModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Employee - ${employeeData.name}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form id="editEmployeeForm">
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">Name</label>
                                        <input type="text" class="form-control" name="name" value="${employeeData.name}" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" value="${employeeData.email !== 'N/A' ? employeeData.email : ''}">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Phone</label>
                                        <input type="text" class="form-control" name="phone" placeholder="Phone number">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Department</label>
                                        <select class="form-control" name="department">
                                            <option value="General" ${employeeData.department === 'General' ? 'selected' : ''}>General</option>
                                            <option value="IT" ${employeeData.department === 'IT' ? 'selected' : ''}>IT</option>
                                            <option value="HR" ${employeeData.department === 'HR' ? 'selected' : ''}>HR</option>
                                            <option value="Finance" ${employeeData.department === 'Finance' ? 'selected' : ''}>Finance</option>
                                            <option value="Marketing" ${employeeData.department === 'Marketing' ? 'selected' : ''}>Marketing</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Position</label>
                                        <input type="text" class="form-control" name="position" value="${employeeData.position !== 'N/A' ? employeeData.position : ''}" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Monthly Salary</label>
                                        <input type="number" class="form-control" name="monthly_salary" placeholder="0" step="0.01">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Status</label>
                                        <select class="form-control" name="status">
                                            <option value="active" ${employeeData.status === 'active' ? 'selected' : ''}>Active</option>
                                            <option value="inactive" ${employeeData.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Address</label>
                                        <textarea class="form-control" name="address" rows="2" placeholder="Employee address"></textarea>
                                    </div>
                                    <input type="hidden" name="employee_id" value="${id}">
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Update Employee</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if any
            $('#editEmployeeModal').remove();
            
            // Add modal to body and show
            $('body').append(modal);
            $('#editEmployeeModal').modal('show');
            
            // Handle form submission
            $('#editEmployeeForm').on('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                formData.append('action', 'update_employee');
                
                $.ajax({
                    url: './hr_dashboard_api.php',
                    method: 'POST',
                    data: Object.fromEntries(formData),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#editEmployeeModal').modal('hide');
                            showAlert(response.message, 'success');
                            loadEmployees(); // Reload employee data
                        } else {
                            showAlert('Error: ' + response.message, 'danger');
                        }
                    },
                    error: function() {
                        showAlert('Error: Failed to update employee', 'danger');
                    }
                });
            });
            
            // Clean up modal on hide
            $('#editEmployeeModal').on('hidden.bs.modal', function() {
                $(this).remove();
            });
        }

        function deleteEmployee(id) {
            // Get employee name for confirmation
            const row = $(`button[onclick="deleteEmployee(${id})"]`).closest('tr');
            const employeeName = row.find('td:nth-child(2)').text();
            
            if (confirm(`Are you sure you want to deactivate employee "${employeeName}"? This will set their status to inactive.`)) {
                $.ajax({
                    url: './hr_dashboard_api.php',
                    method: 'POST',
                    data: {
                        action: 'delete_employee',
                        employee_id: id
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showAlert(response.message, 'success');
                            loadEmployees(); // Reload employee data
                        } else {
                            showAlert('Error: ' + response.message, 'danger');
                        }
                    },
                    error: function() {
                        showAlert('Error: Failed to delete employee', 'danger');
                    }
                });
            }
        }

        function correctAttendance(id) {
            // Get attendance record details first
            const row = $(`button[onclick="correctAttendance(${id})"]`).closest('tr');
            const employeeName = row.find('td:nth-child(1)').text();
            const date = row.find('td:nth-child(2)').text();
            const punchIn = row.find('td:nth-child(3)').text();
            const punchOut = row.find('td:nth-child(4)').text();
            
            // Create correction modal
            const modal = `
                <div class="modal fade" id="correctAttendanceModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Correct Attendance - ${employeeName}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form id="correctAttendanceForm">
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">Date</label>
                                        <input type="date" class="form-control" name="attendance_date" value="${date}" readonly>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Punch In Time</label>
                                        <input type="time" class="form-control" name="punch_in_time" value="${punchIn !== '-' ? punchIn : ''}">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Punch Out Time</label>
                                        <input type="time" class="form-control" name="punch_out_time" value="${punchOut !== '-' ? punchOut : ''}">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Location</label>
                                        <input type="text" class="form-control" name="location" value="Office" placeholder="Office location">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Correction Reason</label>
                                        <textarea class="form-control" name="correction_reason" rows="3" placeholder="Reason for attendance correction..." required></textarea>
                                    </div>
                                    <input type="hidden" name="attendance_id" value="${id}">
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Save Correction</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if any
            $('#correctAttendanceModal').remove();
            
            // Add modal to body and show
            $('body').append(modal);
            $('#correctAttendanceModal').modal('show');
            
            // Handle form submission
            $('#correctAttendanceForm').on('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                formData.append('action', 'correct_attendance');
                
                $.ajax({
                    url: './hr_dashboard_api.php',
                    method: 'POST',
                    data: Object.fromEntries(formData),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#correctAttendanceModal').modal('hide');
                            showAlert(response.message, 'success');
                            loadAttendance(); // Reload attendance data
                        } else {
                            showAlert('Error: ' + response.message, 'danger');
                        }
                    },
                    error: function() {
                        showAlert('Error: Failed to process attendance correction', 'danger');
                    }
                });
            });
            
            // Clean up modal on hide
            $('#correctAttendanceModal').on('hidden.bs.modal', function() {
                $(this).remove();
            });
        }

        function viewPayslip(id) {
            showAlert('View payslip functionality will be implemented', 'info');
        }
        
        function downloadPayslip(id) {
            showAlert('Download payslip functionality will be implemented', 'info');
        }

        function generatePayroll() {
            if (!confirm('Are you sure you want to generate payroll for this month? This action cannot be undone.')) {
                return;
            }
            
            const month = new Date().getMonth() + 1;
            const year = new Date().getFullYear();
            
            // Show loading state
            const originalBtn = event.target;
            const originalText = originalBtn.innerHTML;
            originalBtn.innerHTML = '<div class="loading-spinner me-2"></div>Generating...';
            originalBtn.disabled = true;
            
            $.ajax({
                url: './hr_dashboard_api.php',
                method: 'POST',
                data: { 
                    action: 'process_payroll',
                    month: month + '-' + year,
                    employee_ids: [] // Process all employees
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert('Payroll generated successfully!', 'success');
                        // Refresh payroll tab if it's active
                        if ($('#payroll-tab').hasClass('active')) {
                            loadPayroll();
                        }
                    } else {
                        showAlert('Error: ' + response.message, 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error Details:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText,
                        statusCode: xhr.status
                    });
                    
                    let errorMessage = 'Error generating payroll: ' + error;
                    
                    // If we got HTML instead of JSON, show that
                    if (xhr.responseText && xhr.responseText.includes('<')) {
                        errorMessage = 'Server returned HTML instead of JSON. Check console for details.';
                        console.log('HTML Response:', xhr.responseText);
                    }
                    
                    showAlert(errorMessage, 'danger');
                },
                complete: function() {
                    // Restore button state
                    originalBtn.innerHTML = originalText;
                    originalBtn.disabled = false;
                }
            });
        }

        function generateReport(type = null) {
            // If type is provided (from Reports tab), use it directly
            if (type) {
                generateSpecificReport(type);
                return;
            }
            
            // Create a modal for report selection
            const modalHtml = `
                <div class="modal fade" id="reportModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Generate Report</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <form id="reportForm">
                                    <div class="mb-3">
                                        <label for="reportType" class="form-label">Report Type</label>
                                        <select class="form-select" id="reportType" required>
                                            <option value="">Select Report Type</option>
                                            <option value="attendance">Attendance Report</option>
                                            <option value="payroll">Payroll Report</option>
                                            <option value="leave">Leave Report</option>
                                            <option value="employee">Employee Report</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="reportPeriod" class="form-label">Period</label>
                                        <select class="form-select" id="reportPeriod" required>
                                            <option value="">Select Period</option>
                                            <option value="current_month">Current Month</option>
                                            <option value="last_month">Last Month</option>
                                            <option value="current_year">Current Year</option>
                                            <option value="custom">Custom Range</option>
                                        </select>
                                    </div>
                                    <div id="customRange" style="display: none;">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label for="startDate" class="form-label">Start Date</label>
                                                <input type="date" class="form-control" id="startDate">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="endDate" class="form-label">End Date</label>
                                                <input type="date" class="form-control" id="endDate">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="reportFormat" class="form-label">Format</label>
                                        <select class="form-select" id="reportFormat" required>
                                            <option value="pdf">PDF</option>
                                            <option value="excel">Excel</option>
                                            <option value="csv">CSV</option>
                                        </select>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" onclick="processReport()">
                                    <i class="fas fa-download me-1"></i>Generate Report
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if any
            $('#reportModal').remove();
            
            // Add modal to page
            $('body').append(modalHtml);
            
            // Show custom range when selected
            $('#reportPeriod').change(function() {
                if ($(this).val() === 'custom') {
                    $('#customRange').show();
                } else {
                    $('#customRange').hide();
                }
            });
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('reportModal'));
            modal.show();
        }
        
        function generateSpecificReport(type) {
            // For specific report types from Reports tab
            const reportData = {
                module: 'hr',
                action: 'generate_report',
                report_type: type,
                period: 'current_month',
                format: 'pdf'
            };
            
            showAlert(`Generating ${type} report...`, 'info');
            
            $.ajax({
                url: './hr_dashboard_api.php',
                method: 'POST',
                data: {
                    action: 'get_hr_reports',
                    report_type: type
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        if (response.download_url) {
                            // Download file
                            const link = document.createElement('a');
                            link.href = response.download_url;
                            link.download = response.filename || `${type}_report.pdf`;
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                            showAlert(`${type.charAt(0).toUpperCase() + type.slice(1)} report generated successfully!`, 'success');
                        } else {
                            showAlert('Report generated but download link not available', 'warning');
                        }
                    } else {
                        showAlert('Error: ' + response.message, 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    showAlert(`Error generating ${type} report: ` + error, 'danger');
                }
            });
        }
        
        function processReport() {
            const reportType = $('#reportType').val();
            const period = $('#reportPeriod').val();
            const format = $('#reportFormat').val();
            const startDate = $('#startDate').val();
            const endDate = $('#endDate').val();
            
            if (!reportType || !period || !format) {
                showAlert('Please fill all required fields', 'warning');
                return;
            }
            
            if (period === 'custom' && (!startDate || !endDate)) {
                showAlert('Please select start and end dates for custom range', 'warning');
                return;
            }
            
            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('reportModal')).hide();
            
            // Show loading
            showAlert('Generating report...', 'info');
            
            // Prepare data
            const reportData = {
                module: 'hr',
                action: 'generate_report',
                report_type: reportType,
                period: period,
                format: format
            };
            
            if (period === 'custom') {
                reportData.start_date = startDate;
                reportData.end_date = endDate;
            }
            
            // Generate report
            $.ajax({
                url: './hr_dashboard_api.php',
                method: 'POST',
                data: {
                    action: 'get_hr_reports',
                    report_type: 'custom',
                    start_date: startDate,
                    end_date: endDate
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        if (response.download_url) {
                            // Download file
                            const link = document.createElement('a');
                            link.href = response.download_url;
                            link.download = response.filename || 'report.' + format;
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                            showAlert('Report generated successfully!', 'success');
                        } else {
                            showAlert('Report generated but download link not available', 'warning');
                        }
                    } else {
                        showAlert('Error: ' + response.message, 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    showAlert('Error generating report: ' + error, 'danger');
                }
            });
        }

        function exportEmployees() {
            // Create a form and submit to export
            const form = $('<form>', {
                'method': 'POST',
                'action': './hr_dashboard_api.php',
                'target': '_blank'
            }).append($('<input>', {
                'type': 'hidden',
                'name': 'action',
                'value': 'export_employees'
            }));
            
            $('body').append(form);
            form.submit();
            form.remove();
        }
        
        // Advanced Analytics Functions
        function loadMLInsights(type) {
            $('#mlInsightsContainer').html('<div class="loading-spinner me-2"></div>Loading ' + type + '...');
            
            $.post('./hr_dashboard_api.php', {
                action: 'get_analytics_data',
                insight_type: type
            }).done(function(response) {
                if (response.success) {
                    displayMLInsights(response.data, type);
                } else {
                    $('#mlInsightsContainer').html('<div class="alert alert-danger">' + response.message + '</div>');
                }
            }).fail(function() {
                $('#mlInsightsContainer').html('<div class="alert alert-danger">Failed to load insights</div>');
            });
        }
        
        function displayMLInsights(data, type) {
            let html = '';
            
            switch(type) {
                case 'attendance_prediction':
                    html = '<div class="card"><div class="card-header"><h6><i class="fas fa-exclamation-triangle me-2"></i>High-Risk Employees</h6></div><div class="card-body">';
                    if (data.length === 0) {
                        html += '<p class="text-muted">No high-risk employees identified</p>';
                    } else {
                        data.forEach(function(emp) {
                            let riskClass = emp.risk_level === 'High' ? 'danger' : (emp.risk_level === 'Medium' ? 'warning' : 'info');
                            html += `<div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <strong>${emp.name}</strong><br>
                                    <small class="text-muted">Attendance: ${(emp.overall_attendance * 100).toFixed(1)}%</small>
                                </div>
                                <span class="badge bg-${riskClass}">${emp.risk_level} Risk</span>
                            </div>`;
                        });
                    }
                    html += '</div></div>';
                    break;
                    
                case 'cost_analysis':
                    html = '<div class="card"><div class="card-header"><h6><i class="fas fa-dollar-sign me-2"></i>Department Cost Analysis</h6></div><div class="card-body">';
                    if (data.length === 0) {
                        html += '<p class="text-muted">No cost data available</p>';
                    } else {
                        data.forEach(function(dept) {
                            html += `<div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>${dept.department || 'Unknown'}</strong><br>
                                    <small class="text-muted">${dept.employee_count} employees</small>
                                </div>
                                <div class="col-md-6 text-end">
                                    <div class="h6 text-success">₹${(dept.total_payroll_cost || 0).toLocaleString()}</div>
                                    <small class="text-muted">Total Cost</small>
                                </div>
                            </div>`;
                        });
                    }
                    html += '</div></div>';
                    break;
                    
                case 'performance_trends':
                    html = '<div class="card"><div class="card-header"><h6><i class="fas fa-trending-up me-2"></i>Monthly Performance Trends</h6></div><div class="card-body">';
                    if (data.length === 0) {
                        html += '<p class="text-muted">No trend data available</p>';
                    } else {
                        data.forEach(function(trend) {
                            html += `<div class="row mb-2">
                                <div class="col-md-4">${trend.month}</div>
                                <div class="col-md-4">${trend.attendance_rate?.toFixed(1)}% Attendance</div>
                                <div class="col-md-4">${trend.avg_hours?.toFixed(1)}h Avg</div>
                            </div>`;
                        });
                    }
                    html += '</div></div>';
                    break;
            }
            
            $('#mlInsightsContainer').html(html);
        }
        
        // Biometric System Functions
        function loadBiometricDevices() {
            $.post('./hr_dashboard_api.php', {
                action: 'get_biometric_data'
            }).done(function(response) {
                if (response.success) {
                    displayBiometricDevices(response.data);
                } else {
                    $('#biometricDevicesTable tbody').html('<tr><td colspan="6" class="text-center text-danger">' + response.message + '</td></tr>');
                }
            });
        }
        
        function displayBiometricDevices(devices) {
            let html = '';
            if (devices.length === 0) {
                html = '<tr><td colspan="6" class="text-center text-muted">No devices found</td></tr>';
            } else {
                devices.forEach(function(device) {
                    let statusClass = device.is_active == 1 ? 'success' : 'danger';
                    let statusText = device.is_active == 1 ? 'Active' : 'Inactive';
                    
                    html += `<tr>
                        <td>${device.device_name}</td>
                        <td><span class="badge bg-info">${device.device_type}</span></td>
                        <td>${device.location || 'N/A'}</td>
                        <td><span class="badge bg-${statusClass}">${statusText}</span></td>
                        <td>${device.created_at}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="toggleDeviceStatus(${device.id}, ${device.is_active})">
                                ${device.is_active == 1 ? 'Disable' : 'Enable'}
                            </button>
                        </td>
                    </tr>`;
                });
            }
            $('#biometricDevicesTable tbody').html(html);
        }
        
        function showAddDeviceModal() {
            const modal = `
                <div class="modal fade" id="addDeviceModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Add Biometric Device</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form id="addDeviceForm">
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">Device Name *</label>
                                        <input type="text" class="form-control" name="device_name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Device Type *</label>
                                        <select class="form-select" name="device_type" required>
                                            <option value="">Select Type</option>
                                            <option value="fingerprint">Fingerprint Scanner</option>
                                            <option value="face_recognition">Face Recognition</option>
                                            <option value="rfid">RFID Card Reader</option>
                                            <option value="mobile_gps">Mobile GPS</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Location</label>
                                        <input type="text" class="form-control" name="location">
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Add Device</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modal);
            $('#addDeviceModal').modal('show');
            
            $('#addDeviceForm').on('submit', function(e) {
                e.preventDefault();
                
                $.post('./hr_dashboard_api.php', {
                    action: 'get_biometric_data'
                }).done(function(response) {
                    if (response.success) {
                        $('#addDeviceModal').modal('hide');
                        loadBiometricDevices();
                        showAlert('success', response.message);
                    } else {
                        showAlert('danger', response.message);
                    }
                });
            });
            
            $('#addDeviceModal').on('hidden.bs.modal', function() {
                $(this).remove();
            });
        }
        
        function toggleDeviceStatus(deviceId, currentStatus) {
            const newStatus = currentStatus == 1 ? 0 : 1;
            
            $.post('./hr_dashboard_api.php', {
                action: 'get_biometric_data'
            }).done(function(response) {
                if (response.success) {
                    loadBiometricDevices();
                    showAlert('success', response.message);
                } else {
                    showAlert('danger', response.message);
                }
            });
        }
        
        // Compliance Functions
        function loadComplianceData() {
            $.post('./hr_dashboard_api.php', {
                action: 'get_compliance_data'
            }).done(function(response) {
                if (response.success) {
                    displayComplianceViolations(response.data);
                } else {
                    $('#complianceViolations').html('<div class="alert alert-danger">' + response.message + '</div>');
                }
            });
        }
        
        function displayComplianceViolations(violations) {
            let html = '';
            if (violations.length === 0) {
                html = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>No compliance violations found</div>';
            } else {
                html = '<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Type</th><th>Employee</th><th>Details</th><th>Severity</th></tr></thead><tbody>';
                violations.forEach(function(violation) {
                    let severityClass = violation.severity === 'high' ? 'danger' : (violation.severity === 'medium' ? 'warning' : 'info');
                    html += `<tr>
                        <td><span class="badge bg-${severityClass}">${violation.type}</span></td>
                        <td>${violation.employee}</td>
                        <td>${violation.details}</td>
                        <td><span class="badge bg-${severityClass}">${violation.severity.toUpperCase()}</span></td>
                    </tr>`;
                });
                html += '</tbody></table></div>';
            }
            $('#complianceViolations').html(html);
        }
        
        // Workflow Management Functions
        function loadWorkflows() {
            $.post('./hr_dashboard_api.php', {
                action: 'get_workflows'
            }).done(function(response) {
                if (response.success) {
                    displayWorkflows(response.data);
                } else {
                    $('#workflowsTable tbody').html('<tr><td colspan="6" class="text-center text-danger">' + response.message + '</td></tr>');
                }
            });
        }
        
        function displayWorkflows(workflows) {
            let html = '';
            if (workflows.length === 0) {
                html = '<tr><td colspan="6" class="text-center text-muted">No workflows found</td></tr>';
            } else {
                workflows.forEach(function(workflow) {
                    let statusClass = workflow.is_active == 1 ? 'success' : 'danger';
                    let statusText = workflow.is_active == 1 ? 'Active' : 'Inactive';
                    let approvalLevels = JSON.parse(workflow.approval_levels || '[]');
                    
                    html += `<tr>
                        <td>${workflow.workflow_name}</td>
                        <td><span class="badge bg-info">${workflow.entity_type}</span></td>
                        <td>${workflow.department || 'All'}</td>
                        <td>${approvalLevels.length} levels</td>
                        <td><span class="badge bg-${statusClass}">${statusText}</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="toggleWorkflowStatus(${workflow.id}, ${workflow.is_active})">
                                ${workflow.is_active == 1 ? 'Disable' : 'Enable'}
                            </button>
                        </td>
                    </tr>`;
                });
            }
            $('#workflowsTable tbody').html(html);
        }
        
        function toggleWorkflowStatus(workflowId, currentStatus) {
            const newStatus = currentStatus == 1 ? 0 : 1;
            
            $.post('./hr_dashboard_api.php', {
                action: 'create_workflow',
                name: 'Updated Workflow',
                description: 'Workflow status update',
                steps: []
            }).done(function(response) {
                if (response.success) {
                    loadWorkflows();
                    showAlert('success', response.message);
                } else {
                    showAlert('danger', response.message);
                }
            });
        }
        
        // Tab event handlers for lazy loading
        $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
            const target = $(e.target).attr('data-bs-target');
            
            switch(target) {
                case '#analytics':
                    loadAnalyticsData();
                    break;
                case '#biometric':
                    loadBiometricDevices();
                    break;
                case '#compliance':
                    loadComplianceData();
                    break;
                case '#workflows':
                    loadWorkflows();
                    break;
            }
        });
        
        function loadAnalyticsData() {
            $('#analyticsFilter').on('change', function() {
                loadMLInsights('performance_trends');
            });
        }
        
        // Placeholder functions for advanced features
        function addOfficeLocation() {
            showAlert('info', 'Office location management feature will be implemented');
        }
        
        function testGPSAccuracy() {
            showAlert('info', 'GPS accuracy testing feature will be implemented');
        }
        
        function viewLiveTracking() {
            showAlert('info', 'Live tracking map feature will be implemented');
        }
        
        function showOvertimeRuleModal() {
            showAlert('info', 'Overtime rule configuration modal will be implemented');
        }
        
        function showWorkflowModal() {
            showAlert('info', 'Workflow creation modal will be implemented');
        }
    </script>
</body>
</html>
