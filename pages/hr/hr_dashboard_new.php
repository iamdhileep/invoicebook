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
                                                <option value="2024">2024</option>
                                                <option value="2025" selected>2025</option>
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
                url: '../../api/global_hrms_api.php',
                method: 'POST',
                data: { 
                    module: 'hr',
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
                url: '../../api/global_hrms_api.php',
                method: 'POST',
                data: {
                    module: 'hr',
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
                url: '../../api/global_hrms_api.php',
                method: 'POST',
                data: { 
                    module: 'hr',
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
            $.ajax({
                url: '../../api/global_hrms_api.php',
                method: 'POST',
                data: { 
                    module: 'hr',
                    action: 'update_leave_status',
                    leave_id: leaveId,
                    status: status
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
                url: '../../api/global_hrms_api.php',
                method: 'POST',
                data: { 
                    module: 'hr',
                    action: 'get_attendance',
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
            
            $.ajax({
                url: '../../api/global_hrms_api.php',
                method: 'POST',
                data: { 
                    module: 'hr',
                    action: 'get_payroll',
                    month: month,
                    year: year
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        displayPayroll(response.data);
                    } else {
                        $('#payrollTable tbody').html('<tr><td colspan="7" class="text-center text-danger">Error: ' + response.message + '</td></tr>');
                    }
                }
            });
        }

        function displayPayroll(payroll) {
            let html = '';
            payroll.forEach(pay => {
                html += `
                    <tr>
                        <td>${pay.employee_name || 'Unknown'}</td>
                        <td>₹${parseFloat(pay.basic_salary || 0).toLocaleString()}</td>
                        <td>₹${parseFloat(pay.allowances || 0).toLocaleString()}</td>
                        <td>₹${parseFloat(pay.deductions || 0).toLocaleString()}</td>
                        <td>₹${parseFloat(pay.earned_salary || pay.basic_salary || 0).toLocaleString()}</td>
                        <td><span class="badge bg-success">Processed</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="viewPayslip(${pay.id})">
                                <i class="fas fa-eye"></i> View
                            </button>
                        </td>
                    </tr>
                `;
            });
            $('#payrollTable tbody').html(html || '<tr><td colspan="7" class="text-center">No payroll records found</td></tr>');
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
            showAlert('Edit employee functionality - Contact HR for employee modifications', 'info');
        }

        function deleteEmployee(id) {
            showAlert('Delete employee functionality - Contact HR for employee modifications', 'info');
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
                    url: '../manager/manager_dashboard_api.php',
                    method: 'POST',
                    data: Object.fromEntries(formData),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#correctAttendanceModal').modal('hide');
                            showAlert(response.message, 'success');
                            loadTeamAttendance(); // Reload attendance data
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

        function generatePayroll() {
            showAlert('Payroll generation started...', 'info');
        }

        function generateReport(type) {
            showAlert(`${type.charAt(0).toUpperCase() + type.slice(1)} report generation will be implemented`, 'info');
        }

        function exportEmployees() {
            window.open('../../api/global_hrms_api.php?module=hr&action=export_employees', '_blank');
        }
    </script>
</body>
</html>
