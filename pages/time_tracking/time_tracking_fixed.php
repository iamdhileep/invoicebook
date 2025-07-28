<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Time Tracking & Payroll';

// Set timezone
date_default_timezone_set('Asia/Kolkata');
$today = date('Y-m-d');

include '../../layouts/header.php';
include '../../layouts/sidebar.php';

// Get employee statistics using existing tables
$employeeStats = [
    'total' => 0,
    'present' => 0,
    'avg_hours' => 0,
    'total_payroll' => 0
];

// Count total employees
$result = $conn->query("SELECT COUNT(*) as count FROM employees");
if ($result) {
    $employeeStats['total'] = $result->fetch_assoc()['count'];
}

// Count present today from attendance table
$result = $conn->query("SELECT COUNT(DISTINCT employee_id) as count FROM attendance WHERE date = '$today'");
if ($result) {
    $employeeStats['present'] = $result->fetch_assoc()['count'];
}
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="page-header mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="page-title mb-0">
                        <i class="bi bi-clock-history text-primary me-2"></i>
                        Time Tracking & Payroll
                    </h1>
                    <p class="text-muted mb-0">Manage attendance, salaries, and generate pay-slips</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" onclick="refreshStats()">
                        <i class="bi bi-arrow-clockwise me-1"></i>
                        Refresh
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#salaryConfigModal">
                        <i class="bi bi-gear me-1"></i>
                        Salary Config
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card text-center">
                    <div class="card-body">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary mb-3 mx-auto">
                            <i class="bi bi-people"></i>
                        </div>
                        <h3 class="stat-number text-primary mb-1"><?php echo $employeeStats['total']; ?></h3>
                        <p class="stat-label mb-0">Total Employees</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-center">
                    <div class="card-body">
                        <div class="stat-icon bg-success bg-opacity-10 text-success mb-3 mx-auto">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <h3 class="stat-number text-success mb-1"><?php echo $employeeStats['present']; ?></h3>
                        <p class="stat-label mb-0">Present Today</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-center">
                    <div class="card-body">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning mb-3 mx-auto">
                            <i class="bi bi-clock"></i>
                        </div>
                        <h3 class="stat-number text-warning mb-1">8.5</h3>
                        <p class="stat-label mb-0">Avg Working Hours</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-center">
                    <div class="card-body">
                        <div class="stat-icon bg-info bg-opacity-10 text-info mb-3 mx-auto">
                            <i class="bi bi-currency-rupee"></i>
                        </div>
                        <h3 class="stat-number text-info mb-1">₹2,45,000</h3>
                        <p class="stat-label mb-0">Total Payroll</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Tabs -->
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="timeTrackingTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button" role="tab">
                            <i class="bi bi-clock-history me-1"></i>
                            Attendance Overview
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="salary-tab" data-bs-toggle="tab" data-bs-target="#salary" type="button" role="tab">
                            <i class="bi bi-currency-rupee me-1"></i>
                            Salary Management
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="payslip-tab" data-bs-toggle="tab" data-bs-target="#payslip" type="button" role="tab">
                            <i class="bi bi-file-earmark-text me-1"></i>
                            Pay-slip Generation
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports" type="button" role="tab">
                            <i class="bi bi-graph-up me-1"></i>
                            Reports & Analytics
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="timeTrackingTabContent">
                    <!-- Attendance Overview Tab -->
                    <div class="tab-pane fade show active" id="attendance" role="tabpanel">
                        <div class="row">
                            <div class="col-md-8">
                                <h5 class="mb-3">Today's Attendance</h5>
                                <div class="table-responsive">
                                    <table class="table table-striped" id="attendanceTable">
                                        <thead>
                                            <tr>
                                                <th>Employee</th>
                                                <th>Check In</th>
                                                <th>Check Out</th>
                                                <th>Working Hours</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            // Get today's attendance
                                            $query = "SELECT e.name, a.check_in_time, a.check_out_time, a.status 
                                                     FROM employees e 
                                                     LEFT JOIN attendance a ON e.id = a.employee_id AND a.date = '$today'
                                                     ORDER BY e.name";
                                            $result = $conn->query($query);
                                            
                                            if ($result && $result->num_rows > 0) {
                                                while ($row = $result->fetch_assoc()) {
                                                    $checkIn = $row['check_in_time'] ? date('H:i', strtotime($row['check_in_time'])) : '-';
                                                    $checkOut = $row['check_out_time'] ? date('H:i', strtotime($row['check_out_time'])) : '-';
                                                    $status = $row['status'] ?: 'Absent';
                                                    $statusClass = $status == 'Present' ? 'success' : ($status == 'Late' ? 'warning' : 'danger');
                                                    
                                                    // Calculate working hours
                                                    $workingHours = '-';
                                                    if ($row['check_in_time'] && $row['check_out_time']) {
                                                        $start = new DateTime($row['check_in_time']);
                                                        $end = new DateTime($row['check_out_time']);
                                                        $diff = $start->diff($end);
                                                        $workingHours = $diff->format('%h:%I');
                                                    }
                                                    
                                                    echo "<tr>";
                                                    echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                                                    echo "<td>$checkIn</td>";
                                                    echo "<td>$checkOut</td>";
                                                    echo "<td>$workingHours</td>";
                                                    echo "<td><span class='badge bg-$statusClass'>$status</span></td>";
                                                    echo "</tr>";
                                                }
                                            } else {
                                                echo "<tr><td colspan='5' class='text-center'>No attendance data found</td></tr>";
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Actions - Full Width Horizontal Row -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="mb-0">
                                            <i class="bi bi-lightning-charge me-2"></i>Quick Actions
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <!-- Main Action Buttons Row -->
                                        <div class="row g-2">
                                            <!-- Quick Actions -->
                                            <div class="col-xl-2 col-lg-4 col-md-6 col-sm-6">
                                                <div class="d-grid">
                                                    <button class="btn btn-primary btn-lg" onclick="markBulkAttendance()" 
                                                            style="height: 80px; border-radius: 10px; position: relative;">
                                                        <div class="d-flex flex-column align-items-center">
                                                            <i class="bi bi-check-all fs-4 mb-1"></i>
                                                            <div class="fw-bold" style="font-size: 0.9rem;">Quick Actions</div>
                                                            <small class="opacity-75" style="font-size: 0.75rem;">Bulk Attendance</small>
                                                        </div>
                                                    </button>
                                                </div>
                                            </div>
                                            
                                            <!-- Leave Management -->
                                            <div class="col-xl-2 col-lg-4 col-md-6 col-sm-6">
                                                <div class="d-grid">
                                                    <button class="btn btn-success btn-lg" onclick="applyLeave()" 
                                                            style="height: 80px; border-radius: 10px;">
                                                        <div class="d-flex flex-column align-items-center">
                                                            <i class="bi bi-calendar-x fs-4 mb-1"></i>
                                                            <div class="fw-bold" style="font-size: 0.9rem;">Leave Management</div>
                                                            <small class="opacity-75" style="font-size: 0.75rem;">Apply Leave</small>
                                                        </div>
                                                    </button>
                                                </div>
                                            </div>
                                            
                                            <!-- AI-Powered Features -->
                                            <div class="col-xl-2 col-lg-4 col-md-6 col-sm-6">
                                                <div class="d-grid">
                                                    <button class="btn btn-info btn-lg" onclick="openAIPoweredFeatures()" 
                                                            style="height: 80px; border-radius: 10px;">
                                                        <div class="d-flex flex-column align-items-center">
                                                            <i class="bi bi-robot fs-4 mb-1"></i>
                                                            <div class="fw-bold" style="font-size: 0.9rem;">AI-Powered Features</div>
                                                            <small class="opacity-75" style="font-size: 0.75rem;">Smart Analytics</small>
                                                        </div>
                                                    </button>
                                                </div>
                                            </div>
                                            
                                            <!-- Smart Attendance Options -->
                                            <div class="col-xl-2 col-lg-4 col-md-6 col-sm-6">
                                                <div class="d-grid">
                                                    <button class="btn btn-warning btn-lg" onclick="openSmartAttendance()" 
                                                            style="height: 80px; border-radius: 10px;">
                                                        <div class="d-flex flex-column align-items-center">
                                                            <i class="bi bi-camera-fill fs-4 mb-1"></i>
                                                            <div class="fw-bold" style="font-size: 0.9rem;">Smart Attendance</div>
                                                            <small class="opacity-75" style="font-size: 0.75rem;">GPS & Face Check-in</small>
                                                        </div>
                                                    </button>
                                                </div>
                                            </div>
                                            
                                            <!-- Smart Alerts -->
                                            <div class="col-xl-2 col-lg-4 col-md-6 col-sm-6">
                                                <div class="d-grid">
                                                    <button class="btn btn-danger btn-lg" onclick="openSmartAlerts()" 
                                                            style="height: 80px; border-radius: 10px;">
                                                        <div class="d-flex flex-column align-items-center">
                                                            <i class="bi bi-bell-fill fs-4 mb-1"></i>
                                                            <div class="fw-bold" style="font-size: 0.9rem;">Smart Alerts</div>
                                                            <small class="opacity-75" style="font-size: 0.75rem;">Notifications</small>
                                                        </div>
                                                    </button>
                                                </div>
                                            </div>
                                            
                                            <!-- Manager Tools -->
                                            <div class="col-xl-2 col-lg-4 col-md-6 col-sm-6">
                                                <div class="d-grid">
                                                    <button class="btn btn-secondary btn-lg" onclick="openManagerTools()" 
                                                            style="height: 80px; border-radius: 10px;">
                                                        <div class="d-flex flex-column align-items-center">
                                                            <i class="bi bi-person-gear fs-4 mb-1"></i>
                                                            <div class="fw-bold" style="font-size: 0.9rem;">Manager Tools</div>
                                                            <small class="opacity-75" style="font-size: 0.75rem;">Team Management</small>
                                                        </div>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Secondary Actions Row -->
                                        <div class="row g-2 mt-3 pt-3 border-top">
                                            <div class="col-lg-3 col-md-6">
                                                <button class="btn btn-outline-primary btn-sm w-100" onclick="exportAttendance()">
                                                    <i class="bi bi-download me-1"></i>Export Data
                                                </button>
                                            </div>
                                            <div class="col-lg-3 col-md-6">
                                                <button class="btn btn-outline-info btn-sm w-100" onclick="loadSalaryManagement()">
                                                    <i class="bi bi-currency-rupee me-1"></i>Manage Salaries
                                                </button>
                                            </div>
                                            <div class="col-lg-3 col-md-6">
                                                <button class="btn btn-outline-secondary btn-sm w-100" onclick="generateTimeSheet()">
                                                    <i class="bi bi-calendar-week me-1"></i>Generate Timesheet
                                                </button>
                                            </div>
                                            <div class="col-lg-3 col-md-6">
                                                <button class="btn btn-outline-success btn-sm w-100" onclick="generatePayslips()">
                                                    <i class="bi bi-receipt me-1"></i>Generate Payslips
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Attendance Summary Row -->
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-md-8">
                                                <h6 class="mb-2">Today's Attendance Summary</h6>
                                                <div class="progress" style="height: 12px;">
                                                    <div class="progress-bar bg-success" role="progressbar" 
                                                         style="width: <?php echo $employeeStats['total'] > 0 ? ($employeeStats['present'] / $employeeStats['total'] * 100) : 0; ?>%"></div>
                                                </div>
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <div class="d-flex justify-content-end align-items-center">
                                                    <div class="me-4">
                                                        <small class="text-muted d-block">Present Today</small>
                                                        <h4 class="mb-0 text-success"><?php echo $employeeStats['present']; ?></h4>
                                                    </div>
                                                    <div>
                                                        <small class="text-muted d-block">Total Employees</small>
                                                        <h4 class="mb-0 text-primary"><?php echo $employeeStats['total']; ?></h4>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Salary Management Tab -->
                    <div class="tab-pane fade" id="salary" role="tabpanel">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Salary Management</strong> - Configure employee salaries, allowances, and deductions here.
                        </div>
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0">Employee Salary Configuration</h6>
                                        <button class="btn btn-primary btn-sm" onclick="loadEmployeeSalaries()">
                                            <i class="bi bi-arrow-clockwise me-1"></i>
                                            Refresh
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-striped" id="salaryTable">
                                                <thead>
                                                    <tr>
                                                        <th>Employee</th>
                                                        <th>Basic Salary</th>
                                                        <th>Allowances</th>
                                                        <th>Deductions</th>
                                                        <th>Total</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="salaryTableBody">
                                                    <tr>
                                                        <td colspan="6" class="text-center">
                                                            <div class="spinner-border text-primary" role="status">
                                                                <span class="visually-hidden">Loading...</span>
                                                            </div>
                                                            <div class="mt-2">Loading salary data...</div>
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
                                        <h6 class="mb-0">Quick Actions</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-6 mb-2">
                                                <button class="btn btn-success btn-sm w-100" onclick="showAddSalaryModal()">
                                                    <i class="bi bi-plus-circle me-1"></i>
                                                    <div style="font-size: 0.8em;">Add New</div>
                                                </button>
                                            </div>
                                            <div class="col-6 mb-2">
                                                <button class="btn btn-outline-primary btn-sm w-100" onclick="exportSalaryData()">
                                                    <i class="bi bi-download me-1"></i>
                                                    <div style="font-size: 0.8em;">Export</div>
                                                </button>
                                            </div>
                                            <div class="col-12">
                                                <button class="btn btn-outline-warning btn-sm w-100" onclick="bulkSalaryUpdate()">
                                                    <i class="bi bi-arrow-up-circle me-1"></i>
                                                    Bulk Update
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card mt-3">
                                    <div class="card-header">
                                        <h6 class="mb-0">Salary Statistics</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-6">
                                                <h5 class="text-primary mb-1" id="avgSalary">₹0</h5>
                                                <small class="text-muted">Average Salary</small>
                                            </div>
                                            <div class="col-6">
                                                <h5 class="text-success mb-1" id="totalPayroll">₹0</h5>
                                                <small class="text-muted">Total Payroll</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pay-slip Generation Tab -->
                    <div class="tab-pane fade" id="payslip" role="tabpanel">
                        <div class="alert alert-success">
                            <i class="bi bi-file-earmark-text me-2"></i>
                            <strong>Pay-slip Generation</strong> - Generate monthly pay-slips for employees.
                        </div>
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Generate Pay-slips</h6>
                                    </div>
                                    <div class="card-body">
                                        <form id="payslipForm" class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label">Month</label>
                                                <select class="form-select" id="payslipMonth">
                                                    <option value="2025-07">July 2025</option>
                                                    <option value="2025-06">June 2025</option>
                                                    <option value="2025-05">May 2025</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Employee</label>
                                                <select class="form-select" id="payslipEmployee">
                                                    <option value="all">All Employees</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">&nbsp;</label>
                                                <button type="button" class="btn btn-primary d-block" onclick="generatePayslips()">
                                                    <i class="bi bi-file-earmark-plus me-1"></i>
                                                    Generate
                                                </button>
                                            </div>
                                        </form>
                                        
                                        <hr class="my-4">
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h6 class="mb-0">Generated Pay-slips</h6>
                                            <button class="btn btn-outline-primary btn-sm" onclick="loadPayslips()">
                                                <i class="bi bi-arrow-clockwise me-1"></i>
                                                Refresh
                                            </button>
                                        </div>
                                        
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Employee</th>
                                                        <th>Month</th>
                                                        <th>Gross Salary</th>
                                                        <th>Deductions</th>
                                                        <th>Net Salary</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="payslipTableBody">
                                                    <tr>
                                                        <td colspan="6" class="text-center text-muted">
                                                            No pay-slips generated yet
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
                                        <h6 class="mb-0">Payslip Statistics</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="text-center">
                                            <h4 class="text-success mb-1" id="totalGenerated">0</h4>
                                            <small class="text-muted">Pay-slips Generated</small>
                                        </div>
                                        <hr>
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-outline-success btn-sm" onclick="bulkDownloadPayslips()">
                                                <i class="bi bi-download me-1"></i>
                                                Bulk Download
                                            </button>
                                            <button class="btn btn-outline-info btn-sm" onclick="emailPayslips()">
                                                <i class="bi bi-envelope me-1"></i>
                                                Email to Employees
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card mt-3">
                                    <div class="card-header">
                                        <h6 class="mb-0">Quick Actions</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-6 mb-2">
                                                <button class="btn btn-primary btn-sm w-100" onclick="showPayslipPreview()">
                                                    <i class="bi bi-eye me-1"></i>
                                                    <div style="font-size: 0.8em;">Preview</div>
                                                </button>
                                            </div>
                                            <div class="col-6 mb-2">
                                                <button class="btn btn-outline-warning btn-sm w-100" onclick="configurePayslipTemplate()">
                                                    <i class="bi bi-gear me-1"></i>
                                                    <div style="font-size: 0.8em;">Configure</div>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Reports Tab -->
                    <div class="tab-pane fade" id="reports" role="tabpanel">
                        <div class="alert alert-primary">
                            <i class="bi bi-graph-up me-2"></i>
                            <strong>Reports & Analytics</strong> - View comprehensive attendance and payroll reports.
                        </div>
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card report-card" onclick="loadMonthlyReport()">
                                    <div class="card-body text-center">
                                        <i class="bi bi-calendar-month text-primary" style="font-size: 2rem;"></i>
                                        <h6 class="mt-2">Monthly Reports</h6>
                                        <p class="text-muted small">Comprehensive monthly attendance analysis</p>
                                        <button class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-eye me-1"></i>
                                            View Report
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card report-card" onclick="loadEmployeeReport()">
                                    <div class="card-body text-center">
                                        <i class="bi bi-person-lines-fill text-success" style="font-size: 2rem;"></i>
                                        <h6 class="mt-2">Employee Reports</h6>
                                        <p class="text-muted small">Individual employee performance tracking</p>
                                        <button class="btn btn-outline-success btn-sm">
                                            <i class="bi bi-eye me-1"></i>
                                            View Report
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card report-card" onclick="loadPayrollReport()">
                                    <div class="card-body text-center">
                                        <i class="bi bi-currency-rupee text-info" style="font-size: 2rem;"></i>
                                        <h6 class="mt-2">Payroll Reports</h6>
                                        <p class="text-muted small">Salary and payroll analysis reports</p>
                                        <button class="btn btn-outline-info btn-sm">
                                            <i class="bi bi-eye me-1"></i>
                                            View Report
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Report Display Area -->
                        <div class="card" id="reportDisplayCard" style="display: none;">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0" id="reportTitle">Report Details</h6>
                                <div>
                                    <button class="btn btn-outline-primary btn-sm" onclick="exportCurrentReport()">
                                        <i class="bi bi-download me-1"></i>
                                        Export
                                    </button>
                                    <button class="btn btn-outline-secondary btn-sm" onclick="closeReport()">
                                        <i class="bi bi-x me-1"></i>
                                        Close
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="reportContent">
                                    <!-- Report content will be loaded here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Salary Configuration Modal -->
<div class="modal fade" id="salaryConfigModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Salary Configuration</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="salaryConfigForm">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Employee</label>
                            <select class="form-select" id="modalEmployeeId" required>
                                <option value="">Select Employee</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Basic Salary</label>
                            <input type="number" class="form-control" id="modalBasicSalary" required>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <label class="form-label">Allowances</label>
                            <input type="number" class="form-control" id="modalAllowances">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Deductions</label>
                            <input type="number" class="form-control" id="modalDeductions">
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="alert alert-info">
                                <strong>Total Salary:</strong> ₹<span id="modalTotalSalary">0</span>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="saveSalaryConfig()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Loading Modal -->
<div class="modal fade" id="loadingModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mb-0" id="loadingText">Processing...</p>
            </div>
        </div>
    </div>
</div>

<!-- Manager Tools Modal -->
<div class="modal fade" id="managerToolsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-person-gear me-2"></i>Manager Tools
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="bi bi-people me-2"></i>Team Dashboard</h6>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <i class="bi bi-graph-up text-primary" style="font-size: 3rem;"></i>
                                </div>
                                <p class="text-muted">View comprehensive team performance metrics and attendance analytics.</p>
                                <div class="row text-center">
                                    <div class="col-6">
                                        <h5 class="text-success" id="teamPresentCount">0</h5>
                                        <small>Present Today</small>
                                    </div>
                                    <div class="col-6">
                                        <h5 class="text-primary" id="teamTotalCount">0</h5>
                                        <small>Total Team</small>
                                    </div>
                                </div>
                                <button class="btn btn-primary w-100 mt-3" onclick="viewTeamDashboard()">
                                    <i class="bi bi-eye me-1"></i>View Dashboard
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-header bg-warning text-dark">
                                <h6 class="mb-0"><i class="bi bi-check-all me-2"></i>Bulk Operations</h6>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <i class="bi bi-list-check text-warning" style="font-size: 3rem;"></i>
                                </div>
                                <p class="text-muted">Perform bulk operations like attendance marking, leave approvals, and more.</p>
                                <div class="d-grid gap-2">
                                    <button class="btn btn-warning btn-sm" onclick="bulkAttendanceApproval()">
                                        <i class="bi bi-check-circle me-1"></i>Bulk Approval
                                    </button>
                                    <button class="btn btn-outline-warning btn-sm" onclick="bulkAttendanceReject()">
                                        <i class="bi bi-x-circle me-1"></i>Bulk Reject
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0"><i class="bi bi-person-plus me-2"></i>Apply on Behalf</h6>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <i class="bi bi-clipboard-plus text-info" style="font-size: 3rem;"></i>
                                </div>
                                <p class="text-muted">Apply for leaves or permissions on behalf of team members.</p>
                                <form id="applyOnBehalfForm">
                                    <div class="mb-2">
                                        <select class="form-select form-select-sm" id="behalfEmployee">
                                            <option value="">Select Employee</option>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <select class="form-select form-select-sm" id="behalfType">
                                            <option value="leave">Leave</option>
                                            <option value="permission">Permission</option>
                                        </select>
                                    </div>
                                    <button type="button" class="btn btn-info btn-sm w-100" onclick="applyOnBehalf()">
                                        <i class="bi bi-plus me-1"></i>Apply
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-table me-2"></i>Team Attendance Overview</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Employee</th>
                                                <th>Status</th>
                                                <th>Check In</th>
                                                <th>Working Hours</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="teamAttendanceTable">
                                            <tr>
                                                <td colspan="5" class="text-center">
                                                    <div class="spinner-border spinner-border-sm" role="status"></div>
                                                    Loading team data...
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
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" onclick="refreshManagerData()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh Data
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Smart Attendance Modal -->
<div class="modal fade" id="smartAttendanceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-camera-fill me-2"></i>Smart Attendance Options
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0"><i class="bi bi-camera-video me-2"></i>Face Recognition</h6>
                            </div>
                            <div class="card-body text-center">
                                <div id="faceRecognitionArea" class="border rounded p-4 mb-3" style="min-height: 200px; background: #f8f9fa;">
                                    <i class="bi bi-camera text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-2">Click to enable camera for face recognition</p>
                                </div>
                                <button class="btn btn-success" onclick="initFaceRecognition()">
                                    <i class="bi bi-camera-video me-1"></i>Start Face Recognition
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-warning text-dark">
                                <h6 class="mb-0"><i class="bi bi-geo-alt me-2"></i>GPS Location</h6>
                            </div>
                            <div class="card-body text-center">
                                <div id="locationStatus" class="mb-3">
                                    <div class="spinner-border text-warning" role="status" id="locationSpinner">
                                        <span class="visually-hidden">Getting location...</span>
                                    </div>
                                    <p class="mt-2" id="locationText">Click to get your location...</p>
                                </div>
                                <button class="btn btn-warning" onclick="getUserLocation()" id="getLocationBtn">
                                    <i class="bi bi-geo-alt-fill me-1"></i>Get Location
                                </button>
                                <button class="btn btn-success w-100 mt-2" onclick="checkInWithGPS()" id="gpsCheckInBtn" disabled>
                                    <i class="bi bi-check-circle me-1"></i>Check-in with GPS
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0"><i class="bi bi-qr-code-scan me-2"></i>QR Code Scanner</h6>
                            </div>
                            <div class="card-body text-center">
                                <div id="qrScannerArea" class="border rounded p-3 mb-3" style="min-height: 150px; background: #f8f9fa;">
                                    <i class="bi bi-qr-code text-muted" style="font-size: 2.5rem;"></i>
                                    <p class="text-muted mt-2">Scan employee QR code</p>
                                </div>
                                <button class="btn btn-info" onclick="initQRScanner()">
                                    <i class="bi bi-qr-code-scan me-1"></i>Start QR Scanner
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-secondary text-white">
                                <h6 class="mb-0"><i class="bi bi-router me-2"></i>IP-based Check-in</h6>
                            </div>
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <p class="mb-2">Your IP: <strong id="userIP">Loading...</strong></p>
                                    <p class="mb-2">Network: <span class="badge bg-success" id="networkStatus">Checking...</span></p>
                                </div>
                                <button class="btn btn-secondary w-100" onclick="checkInWithIP()">
                                    <i class="bi bi-router-fill me-1"></i>Check-in with IP
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="manualCheckIn()">
                    <i class="bi bi-hand-index me-1"></i>Manual Check-in
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Global variables
let currentEmployees = [];
let currentReportType = '';

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    loadEmployeesForDropdown();
    loadEmployeeSalaries();
    loadPayslips();
    
    // Auto-calculate total salary
    ['modalBasicSalary', 'modalAllowances', 'modalDeductions'].forEach(id => {
        document.getElementById(id).addEventListener('input', calculateTotalSalary);
    });
});

// Utility functions
function showLoading(text = 'Processing...') {
    document.getElementById('loadingText').textContent = text;
    new bootstrap.Modal(document.getElementById('loadingModal')).show();
}

function hideLoading() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('loadingModal'));
    if (modal) modal.hide();
}

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

// Main functionality
function refreshStats() {
    showLoading('Refreshing statistics...');
    fetch('../../api/time_tracking_api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=refresh_stats'
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            // Update statistics cards
            document.querySelector('.stat-number.text-primary').textContent = data.stats.total_employees;
            document.querySelector('.stat-number.text-success').textContent = data.stats.present_today;
            document.querySelector('.stat-number.text-warning').textContent = data.stats.avg_hours;
            document.querySelector('.stat-number.text-info').textContent = '₹' + data.stats.total_payroll;
            showAlert('Statistics refreshed successfully!', 'success');
        } else {
            showAlert('Error refreshing statistics: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('Network error: ' + error.message, 'danger');
    });
}

function markBulkAttendance() {
    if (!confirm('Mark all absent employees as present for today?')) return;
    
    showLoading('Marking bulk attendance...');
    fetch('../../api/time_tracking_api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=mark_bulk_present'
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showAlert(`Successfully marked ${data.marked_count} employees as present!`, 'success');
            setTimeout(() => location.reload(), 2000);
        } else {
            showAlert('Error: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('Network error: ' + error.message, 'danger');
    });
}

function exportAttendance() {
    showLoading('Exporting attendance data...');
    fetch('../../api/time_tracking_api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=export_attendance'
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showAlert('Attendance exported successfully!', 'success');
            // Create download link
            const link = document.createElement('a');
            link.href = '../../' + data.download_url;
            link.download = data.filename;
            link.click();
        } else {
            showAlert('Error exporting: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('Network error: ' + error.message, 'danger');
    });
}

function generateTimeSheet() {
    showLoading('Generating timesheet...');
    fetch('../../api/time_tracking_api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=generate_timesheet'
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showAlert(`Timesheet generated for period: ${data.period}`, 'success');
            console.log('Timesheet data:', data.data);
        } else {
            showAlert('Error generating timesheet: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('Network error: ' + error.message, 'danger');
    });
}

// Salary Management Functions
function loadSalaryManagement() {
    document.querySelector('#salary-tab').click();
    loadEmployeeSalaries();
}

function loadEmployeesForDropdown() {
    fetch('../../api/salary_api.php?action=get_employees')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            currentEmployees = data.employees;
            const selects = ['modalEmployeeId', 'payslipEmployee'];
            selects.forEach(selectId => {
                const select = document.getElementById(selectId);
                if (select) {
                    select.innerHTML = selectId === 'payslipEmployee' ? '<option value="all">All Employees</option>' : '<option value="">Select Employee</option>';
                    data.employees.forEach(emp => {
                        select.innerHTML += `<option value="${emp.id}">${emp.name}</option>`;
                    });
                }
            });
        }
    })
    .catch(error => console.error('Error loading employees:', error));
}

function loadEmployeeSalaries() {
    const tbody = document.getElementById('salaryTableBody');
    if (!tbody) return;
    
    tbody.innerHTML = '<tr><td colspan="6" class="text-center">Loading...</td></tr>';
    
    fetch('../../api/salary_api.php?action=get_employees')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let html = '';
            let totalSalary = 0;
            
            data.employees.forEach(emp => {
                const total = parseFloat(emp.total_salary) || (parseFloat(emp.basic_salary) + parseFloat(emp.allowances) - parseFloat(emp.deductions));
                totalSalary += total;
                
                html += `
                    <tr>
                        <td>${emp.name}</td>
                        <td>₹${parseFloat(emp.basic_salary || 0).toLocaleString()}</td>
                        <td>₹${parseFloat(emp.allowances || 0).toLocaleString()}</td>
                        <td>₹${parseFloat(emp.deductions || 0).toLocaleString()}</td>
                        <td><strong>₹${total.toLocaleString()}</strong></td>
                        <td>
                            <button class="btn btn-primary btn-sm" onclick="editSalary(${emp.id})">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html || '<tr><td colspan="6" class="text-center">No employees found</td></tr>';
            
            // Update statistics
            const avgSalary = data.employees.length > 0 ? totalSalary / data.employees.length : 0;
            if (document.getElementById('avgSalary')) {
                document.getElementById('avgSalary').textContent = '₹' + Math.round(avgSalary).toLocaleString();
            }
            if (document.getElementById('totalPayroll')) {
                document.getElementById('totalPayroll').textContent = '₹' + totalSalary.toLocaleString();
            }
        }
    })
    .catch(error => {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error loading data</td></tr>';
        console.error('Error:', error);
    });
}

function editSalary(employeeId) {
    const employee = currentEmployees.find(emp => emp.id == employeeId);
    if (!employee) return;
    
    document.getElementById('modalEmployeeId').value = employeeId;
    document.getElementById('modalBasicSalary').value = employee.basic_salary || 25000;
    document.getElementById('modalAllowances').value = employee.allowances || 5000;
    document.getElementById('modalDeductions').value = employee.deductions || 2000;
    calculateTotalSalary();
    
    new bootstrap.Modal(document.getElementById('salaryConfigModal')).show();
}

function calculateTotalSalary() {
    const basic = parseFloat(document.getElementById('modalBasicSalary').value) || 0;
    const allowances = parseFloat(document.getElementById('modalAllowances').value) || 0;
    const deductions = parseFloat(document.getElementById('modalDeductions').value) || 0;
    const total = basic + allowances - deductions;
    document.getElementById('modalTotalSalary').textContent = total.toLocaleString();
}

function saveSalaryConfig() {
    const formData = new FormData();
    formData.append('action', 'update_salary');
    formData.append('employee_id', document.getElementById('modalEmployeeId').value);
    formData.append('basic_salary', document.getElementById('modalBasicSalary').value);
    formData.append('allowances', document.getElementById('modalAllowances').value);
    formData.append('deductions', document.getElementById('modalDeductions').value);
    
    showLoading('Saving salary configuration...');
    fetch('../../api/salary_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showAlert('Salary updated successfully!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('salaryConfigModal')).hide();
            loadEmployeeSalaries();
        } else {
            showAlert('Error: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('Network error: ' + error.message, 'danger');
    });
}

// Payslip Functions
function generatePayslips() {
    const month = document.getElementById('payslipMonth').value;
    const employee = document.getElementById('payslipEmployee').value;
    
    if (!month) {
        showAlert('Please select a month', 'warning');
        return;
    }
    
    showLoading('Generating payslips...');
    
    const formData = new FormData();
    if (employee === 'all') {
        formData.append('action', 'bulk_generate');
    } else {
        formData.append('action', 'generate_payslip');
        formData.append('employee_id', employee);
    }
    formData.append('month', month);
    
    fetch('../../api/payslip_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showAlert(data.message, 'success');
            loadPayslips();
        } else {
            showAlert('Error: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('Network error: ' + error.message, 'danger');
    });
}

function loadPayslips() {
    const month = document.getElementById('payslipMonth')?.value || '2025-07';
    const tbody = document.getElementById('payslipTableBody');
    if (!tbody) return;
    
    fetch(`../../api/payslip_api.php?action=get_payslips&month=${month}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let html = '';
            data.payslips.forEach(payslip => {
                html += `
                    <tr>
                        <td>${payslip.name}</td>
                        <td>${payslip.month}</td>
                        <td>₹${parseFloat(payslip.gross_salary).toLocaleString()}</td>
                        <td>₹${parseFloat(payslip.total_deductions).toLocaleString()}</td>
                        <td><strong>₹${parseFloat(payslip.net_salary).toLocaleString()}</strong></td>
                        <td>
                            <button class="btn btn-outline-primary btn-sm" onclick="viewPayslip(${payslip.id})">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-outline-success btn-sm" onclick="downloadPayslip(${payslip.id})">
                                <i class="bi bi-download"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html || '<tr><td colspan="6" class="text-center text-muted">No payslips found</td></tr>';
            
            if (document.getElementById('totalGenerated')) {
                document.getElementById('totalGenerated').textContent = data.payslips.length;
            }
        }
    })
    .catch(error => {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error loading payslips</td></tr>';
        console.error('Error:', error);
    });
}

// Report Functions
function loadMonthlyReport() {
    currentReportType = 'monthly';
    showLoading('Loading monthly report...');
    
    fetch('../../api/reports_api.php?action=monthly_report&month=2025-07')
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            displayReport('Monthly Attendance Report', data.report, data.summary);
        } else {
            showAlert('Error loading report: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('Network error: ' + error.message, 'danger');
    });
}

function loadEmployeeReport() {
    currentReportType = 'employee';
    showAlert('Please select an employee from the dropdown to view individual report', 'info');
}

function loadPayrollReport() {
    currentReportType = 'payroll';
    showLoading('Loading payroll report...');
    
    fetch('../../api/reports_api.php?action=payroll_report&month=2025-07')
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            displayPayrollReport('Payroll Report', data.report, data.totals);
        } else {
            showAlert('Error loading report: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('Network error: ' + error.message, 'danger');
    });
}

function displayReport(title, reportData, summary) {
    document.getElementById('reportTitle').textContent = title;
    
    let html = `
        <div class="row mb-4">
            <div class="col-md-4 text-center">
                <h4 class="text-primary">${summary.total_employees}</h4>
                <small>Total Employees</small>
            </div>
            <div class="col-md-4 text-center">
                <h4 class="text-success">${summary.avg_attendance_rate}%</h4>
                <small>Average Attendance</small>
            </div>
            <div class="col-md-4 text-center">
                <h4 class="text-info">${summary.total_working_hours}h</h4>
                <small>Total Working Hours</small>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Present Days</th>
                        <th>Absent Days</th>
                        <th>Late Days</th>
                        <th>Avg Hours</th>
                        <th>Total Hours</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    reportData.forEach(row => {
        html += `
            <tr>
                <td>${row.name}</td>
                <td><span class="badge bg-success">${row.present_days}</span></td>
                <td><span class="badge bg-danger">${row.absent_days}</span></td>
                <td><span class="badge bg-warning">${row.late_days}</span></td>
                <td>${row.avg_hours}h</td>
                <td>${row.total_hours}h</td>
            </tr>
        `;
    });
    
    html += '</tbody></table></div>';
    
    document.getElementById('reportContent').innerHTML = html;
    document.getElementById('reportDisplayCard').style.display = 'block';
}

function displayPayrollReport(title, reportData, totals) {
    document.getElementById('reportTitle').textContent = title;
    
    let html = `
        <div class="row mb-4">
            <div class="col-md-3 text-center">
                <h4 class="text-primary">${totals.total_employees}</h4>
                <small>Total Employees</small>
            </div>
            <div class="col-md-3 text-center">
                <h4 class="text-success">₹${totals.total_gross.toLocaleString()}</h4>
                <small>Total Gross</small>
            </div>
            <div class="col-md-3 text-center">
                <h4 class="text-warning">₹${totals.total_deductions.toLocaleString()}</h4>
                <small>Total Deductions</small>
            </div>
            <div class="col-md-3 text-center">
                <h4 class="text-info">₹${totals.total_net.toLocaleString()}</h4>
                <small>Total Net Pay</small>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Employee ID</th>
                        <th>Gross Salary</th>
                        <th>Deductions</th>
                        <th>Net Salary</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    reportData.forEach(row => {
        html += `
            <tr>
                <td>${row.name}</td>
                <td>${row.employee_id || 'N/A'}</td>
                <td>₹${parseFloat(row.gross_salary).toLocaleString()}</td>
                <td>₹${parseFloat(row.total_deductions).toLocaleString()}</td>
                <td><strong>₹${parseFloat(row.net_salary).toLocaleString()}</strong></td>
            </tr>
        `;
    });
    
    html += '</tbody></table></div>';
    
    document.getElementById('reportContent').innerHTML = html;
    document.getElementById('reportDisplayCard').style.display = 'block';
}

function closeReport() {
    document.getElementById('reportDisplayCard').style.display = 'none';
}

// Placeholder functions for additional features
function showAddSalaryModal() {
    document.getElementById('modalEmployeeId').value = '';
    document.getElementById('modalBasicSalary').value = 25000;
    document.getElementById('modalAllowances').value = 5000;
    document.getElementById('modalDeductions').value = 2000;
    calculateTotalSalary();
    new bootstrap.Modal(document.getElementById('salaryConfigModal')).show();
}

function exportSalaryData() {
    showAlert('Salary export functionality will be implemented', 'info');
}

function bulkSalaryUpdate() {
    showAlert('Bulk salary update functionality will be implemented', 'info');
}

function viewPayslip(id) {
    showAlert('Payslip view functionality will be implemented', 'info');
}

function downloadPayslip(id) {
    showAlert('Payslip download functionality will be implemented', 'info');
}

function bulkDownloadPayslips() {
    showAlert('Bulk payslip download functionality will be implemented', 'info');
}

function emailPayslips() {
    showAlert('Email payslips functionality will be implemented', 'info');
}

function showPayslipPreview() {
    showAlert('Payslip preview functionality will be implemented', 'info');
}

function configurePayslipTemplate() {
    showAlert('Payslip template configuration will be implemented', 'info');
}

function exportCurrentReport() {
    showAlert('Report export functionality will be implemented', 'info');
}

// ====================
// MANAGER TOOLS FUNCTIONS
// ====================

function openManagerTools() {
    const modal = new bootstrap.Modal(document.getElementById('managerToolsModal'));
    modal.show();
    loadManagerData();
}

function loadManagerData() {
    // Load team counts
    loadTeamCounts();
    // Load team attendance table
    loadTeamAttendance();
    // Load employees for behalf operations
    loadEmployeesForBehalf();
}

function loadTeamCounts() {
    fetch('../../api/time_tracking_api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_team_counts'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('teamPresentCount').textContent = data.present_count || 0;
            document.getElementById('teamTotalCount').textContent = data.total_count || 0;
        }
    })
    .catch(error => console.error('Error loading team counts:', error));
}

function loadTeamAttendance() {
    const tbody = document.getElementById('teamAttendanceTable');
    tbody.innerHTML = '<tr><td colspan="5" class="text-center"><div class="spinner-border spinner-border-sm" role="status"></div> Loading team data...</td></tr>';
    
    fetch('../../api/time_tracking_api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_team_attendance'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data) {
            let html = '';
            data.data.forEach(employee => {
                const statusBadge = employee.status === 'Present' ? 'bg-success' : 
                                   employee.status === 'Absent' ? 'bg-danger' : 'bg-warning';
                
                html += `
                    <tr>
                        <td>${employee.name}</td>
                        <td><span class="badge ${statusBadge}">${employee.status}</span></td>
                        <td>${employee.check_in || '-'}</td>
                        <td>${employee.working_hours || '-'}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="viewEmployeeDetails(${employee.id})">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-warning" onclick="editEmployeeAttendance(${employee.id})">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        } else {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No team data available</td></tr>';
        }
    })
    .catch(error => {
        console.error('Error loading team attendance:', error);
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading data</td></tr>';
    });
}

function loadEmployeesForBehalf() {
    const select = document.getElementById('behalfEmployee');
    
    fetch('../../api/time_tracking_api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_employees'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data) {
            let options = '<option value="">Select Employee</option>';
            data.data.forEach(employee => {
                options += `<option value="${employee.id}">${employee.name}</option>`;
            });
            select.innerHTML = options;
        }
    })
    .catch(error => console.error('Error loading employees:', error));
}

function viewTeamDashboard() {
    showAlert('Team Dashboard feature will be implemented soon!', 'info');
}

function bulkAttendanceApproval() {
    if (confirm('Are you sure you want to approve all pending attendance requests?')) {
        fetch('../../api/time_tracking_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=bulk_approve_attendance'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Bulk approval completed successfully!', 'success');
                loadTeamAttendance();
            } else {
                showAlert('Error: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error performing bulk approval', 'danger');
        });
    }
}

function bulkAttendanceReject() {
    if (confirm('Are you sure you want to reject all pending attendance requests?')) {
        fetch('../../api/time_tracking_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=bulk_reject_attendance'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Bulk rejection completed successfully!', 'success');
                loadTeamAttendance();
            } else {
                showAlert('Error: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error performing bulk rejection', 'danger');
        });
    }
}

function applyOnBehalf() {
    const employeeId = document.getElementById('behalfEmployee').value;
    const type = document.getElementById('behalfType').value;
    
    if (!employeeId) {
        showAlert('Please select an employee', 'warning');
        return;
    }
    
    showAlert(`${type.charAt(0).toUpperCase() + type.slice(1)} application on behalf feature will be implemented soon!`, 'info');
}

function viewEmployeeDetails(employeeId) {
    showAlert('Employee details view will be implemented soon!', 'info');
}

function editEmployeeAttendance(employeeId) {
    showAlert('Employee attendance edit feature will be implemented soon!', 'info');
}

function refreshManagerData() {
    loadManagerData();
    showAlert('Manager data refreshed successfully!', 'success');
}

// ====================
// SMART ATTENDANCE FUNCTIONS
// ====================

function openSmartAttendance() {
    const modal = new bootstrap.Modal(document.getElementById('smartAttendanceModal'));
    modal.show();
    initSmartAttendanceData();
}

function initSmartAttendanceData() {
    // Get user IP
    getUserIP();
    // Check network status
    checkNetworkStatus();
    // Initialize location status
    document.getElementById('locationSpinner').style.display = 'none';
    document.getElementById('locationText').textContent = 'Click to get your location...';
}

function getUserIP() {
    fetch('https://api.ipify.org?format=json')
    .then(response => response.json())
    .then(data => {
        document.getElementById('userIP').textContent = data.ip;
    })
    .catch(error => {
        console.error('Error getting IP:', error);
        document.getElementById('userIP').textContent = 'Unable to detect';
    });
}

function checkNetworkStatus() {
    // This would check if the user is on company network
    // For demo purposes, we'll show "Office Network"
    document.getElementById('networkStatus').textContent = 'Office Network';
    document.getElementById('networkStatus').className = 'badge bg-success';
}

function initFaceRecognition() {
    const area = document.getElementById('faceRecognitionArea');
    area.innerHTML = `
        <div class="alert alert-info mb-0">
            <i class="bi bi-camera-video me-2"></i>
            Face recognition feature requires camera access and additional setup.
            <br><small>This feature will be implemented with WebRTC and face detection libraries.</small>
        </div>
    `;
    showAlert('Face recognition feature will be implemented soon with proper camera integration!', 'info');
}

function getUserLocation() {
    const spinner = document.getElementById('locationSpinner');
    const text = document.getElementById('locationText');
    const btn = document.getElementById('getLocationBtn');
    const checkInBtn = document.getElementById('gpsCheckInBtn');
    
    spinner.style.display = 'inline-block';
    text.textContent = 'Getting your location...';
    btn.disabled = true;
    
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                
                spinner.style.display = 'none';
                text.innerHTML = `
                    <strong>Location Found!</strong><br>
                    <small>Lat: ${lat.toFixed(6)}, Lng: ${lng.toFixed(6)}</small>
                `;
                btn.disabled = false;
                checkInBtn.disabled = false;
                checkInBtn.classList.remove('btn-success');
                checkInBtn.classList.add('btn-success');
            },
            function(error) {
                spinner.style.display = 'none';
                text.innerHTML = '<span class="text-danger">Location access denied or unavailable</span>';
                btn.disabled = false;
                showAlert('Location access is required for GPS check-in', 'warning');
            }
        );
    } else {
        spinner.style.display = 'none';
        text.innerHTML = '<span class="text-danger">Geolocation not supported</span>';
        btn.disabled = false;
        showAlert('Geolocation is not supported by this browser', 'danger');
    }
}

function checkInWithGPS() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                
                const formData = new FormData();
                formData.append('action', 'gps_checkin');
                formData.append('latitude', lat);
                formData.append('longitude', lng);
                
                fetch('../../api/time_tracking_api.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('GPS Check-in successful!', 'success');
                        refreshStats();
                        bootstrap.Modal.getInstance(document.getElementById('smartAttendanceModal')).hide();
                    } else {
                        showAlert('GPS Check-in failed: ' + data.message, 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Error during GPS check-in', 'danger');
                });
            },
            function(error) {
                showAlert('Location access is required for GPS check-in', 'warning');
            }
        );
    }
}

function initQRScanner() {
    const area = document.getElementById('qrScannerArea');
    area.innerHTML = `
        <div class="alert alert-info mb-0">
            <i class="bi bi-qr-code-scan me-2"></i>
            QR Code scanner feature requires camera access and QR scanning library.
            <br><small>This feature will be implemented with QuaggaJS or similar library.</small>
        </div>
    `;
    showAlert('QR Scanner feature will be implemented soon with proper camera integration!', 'info');
}

function checkInWithIP() {
    fetch('../../api/time_tracking_api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=ip_checkin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('IP-based Check-in successful!', 'success');
            refreshStats();
            bootstrap.Modal.getInstance(document.getElementById('smartAttendanceModal')).hide();
        } else {
            showAlert('IP-based Check-in failed: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error during IP-based check-in', 'danger');
    });
}

function manualCheckIn() {
    if (confirm('Are you sure you want to perform manual check-in?')) {
        fetch('../../api/time_tracking_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=manual_checkin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Manual Check-in successful!', 'success');
                refreshStats();
                bootstrap.Modal.getInstance(document.getElementById('smartAttendanceModal')).hide();
            } else {
                showAlert('Manual Check-in failed: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error during manual check-in', 'danger');
        });
    }
    }

    // ====================
    // ADDITIONAL QUICK ACTION FUNCTIONS
    // ====================
    
    function applyLeave() {
        showAlert('Leave Management: This feature will open the leave application system with calendar integration and approval workflow.', 'info');
        // You can implement a leave modal here
        // const leaveModal = new bootstrap.Modal(document.getElementById('leaveModal'));
        // leaveModal.show();
    }
    
    function openAIPoweredFeatures() {
        showAlert('AI-Powered Features: This will include smart attendance predictions, pattern analysis, and automated reporting.', 'info');
        // Open AI features panel
        openAIFeaturesPanel();
    }
    
    function openSmartAlerts() {
        showAlert('Smart Alerts: This feature will show real-time notifications for attendance anomalies, late arrivals, and system alerts.', 'info');
        // Open smart alerts sidebar or modal
        displaySmartAlerts();
    }
    
    function openManagerTools() {
        // Open the Manager Tools modal
        const managerModal = new bootstrap.Modal(document.getElementById('managerToolsModal'));
        managerModal.show();
        loadManagerData();
    }
    
    function openSmartAttendance() {
        // Open the Smart Attendance modal
        const smartModal = new bootstrap.Modal(document.getElementById('smartAttendanceModal'));
        smartModal.show();
        initializeSmartAttendance();
    }
    
    // Manager Tools Functions
    function loadManagerData() {
        // Load team dashboard data
        loadTeamDashboard();
        loadBulkOperations();
        loadApplyOnBehalf();
    }
    
    function viewTeamDashboard() {
        showAlert('Opening Team Dashboard with comprehensive team performance metrics...', 'info');
        // Load team performance data
        fetch('../../api/time_tracking_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=get_team_dashboard'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateTeamDashboard(data.dashboard);
            }
        })
        .catch(error => console.error('Error loading team dashboard:', error));
    }
    
    function bulkOperations() {
        showAlert('Bulk Operations: Mass approve leave requests, bulk attendance marking, and batch operations.', 'info');
        // Implement bulk operations interface
    }
    
    function applyOnBehalf() {
        showAlert('Apply on Behalf: Submit attendance, leave requests, or permissions for team members.', 'info');
        // Open apply on behalf interface
    }
    
    function refreshManagerData() {
        showLoading('Refreshing manager data...');
        viewTeamDashboard();
        setTimeout(() => {
            hideLoading();
            showAlert('Manager data refreshed successfully!', 'success');
        }, 1500);
    }
    
    // Smart Attendance Functions
    function initializeSmartAttendance() {
        // Initialize all smart attendance features
        getUserIP();
        checkNetworkStatus();
        setupFaceRecognition();
        document.getElementById('locationSpinner').style.display = 'none';
    }
    
    function setupFaceRecognition() {
        // Setup face recognition interface
        const area = document.getElementById('faceRecognitionArea');
        if (area) {
            area.innerHTML = `
                <div class="text-center">
                    <i class="bi bi-camera text-primary" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-2">Click to enable camera for face recognition</p>
                    <button class="btn btn-primary btn-sm" onclick="initFaceRecognition()">
                        <i class="bi bi-camera-video me-1"></i>Enable Camera
                    </button>
                </div>
            `;
        }
    }
    
    // AI Features Functions
    function openAIFeaturesPanel() {
        const aiFeatures = `
            <div class="modal fade" id="aiModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-info text-white">
                            <h5 class="modal-title"><i class="bi bi-robot me-2"></i>AI-Powered Features</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <i class="bi bi-graph-up text-success" style="font-size: 2rem;"></i>
                                            <h6 class="mt-2">Smart Analytics</h6>
                                            <p class="text-muted small">AI-powered attendance pattern analysis</p>
                                            <button class="btn btn-outline-success btn-sm">View Analytics</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <i class="bi bi-brain text-primary" style="font-size: 2rem;"></i>
                                            <h6 class="mt-2">Predictive Insights</h6>
                                            <p class="text-muted small">Predict attendance trends and anomalies</p>
                                            <button class="btn btn-outline-primary btn-sm">Get Insights</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        if (!document.getElementById('aiModal')) {
            document.body.insertAdjacentHTML('beforeend', aiFeatures);
        }
        
        const aiModal = new bootstrap.Modal(document.getElementById('aiModal'));
        aiModal.show();
    }
    
    // Smart Alerts Functions
    function displaySmartAlerts() {
        const alertsData = [
            { type: 'warning', message: '3 employees are running late today', time: '5 mins ago' },
            { type: 'info', message: 'Monthly attendance report is ready', time: '1 hour ago' },
            { type: 'success', message: 'Payroll processing completed', time: '2 hours ago' },
            { type: 'danger', message: '2 pending leave approvals require attention', time: '3 hours ago' }
        ];
        
        let alertsHtml = '<div class="list-group">';
        alertsData.forEach(alert => {
            alertsHtml += `
                <div class="list-group-item d-flex justify-content-between align-items-start">
                    <div class="ms-2 me-auto">
                        <div class="fw-bold">
                            <i class="bi bi-${alert.type === 'warning' ? 'exclamation-triangle text-warning' : 
                                                alert.type === 'info' ? 'info-circle text-info' : 
                                                alert.type === 'success' ? 'check-circle text-success' : 
                                                'x-circle text-danger'}"></i>
                            ${alert.message}
                        </div>
                        <small class="text-muted">${alert.time}</small>
                    </div>
                </div>
            `;
        });
        alertsHtml += '</div>';
        
        const alertModal = `
            <div class="modal fade" id="alertsModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title"><i class="bi bi-bell-fill me-2"></i>Smart Alerts</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            ${alertsHtml}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-danger">Mark All Read</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        if (!document.getElementById('alertsModal')) {
            document.body.insertAdjacentHTML('beforeend', alertModal);
        }
        
        const modal = new bootstrap.Modal(document.getElementById('alertsModal'));
        modal.show();
    }
    
    // Additional Payslip Functions (Missing implementations)
    function showAddSalaryModal() {
        // Clear form and show salary modal
        document.getElementById('modalEmployeeId').value = '';
        document.getElementById('modalBasicSalary').value = '';
        document.getElementById('modalAllowances').value = '';
        document.getElementById('modalDeductions').value = '';
        calculateTotalSalary();
        
        const modal = new bootstrap.Modal(document.getElementById('salaryConfigModal'));
        modal.show();
    }
    
    function exportSalaryData() {
        showLoading('Exporting salary data...');
        fetch('../../api/salary_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=export_salary'
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                showAlert('Salary data exported successfully!', 'success');
                // Create download link
                const link = document.createElement('a');
                link.href = data.download_url;
                link.download = data.filename;
                link.click();
            } else {
                showAlert('Error exporting salary data: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            hideLoading();
            showAlert('Network error: ' + error.message, 'danger');
        });
    }
    
    function bulkSalaryUpdate() {
        if (confirm('Apply bulk salary update? This will update all employee salaries based on the configured rules.')) {
            showLoading('Processing bulk salary update...');
            fetch('../../api/salary_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=bulk_update'
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showAlert(`Bulk update completed! ${data.updated_count} salaries updated.`, 'success');
                    loadEmployeeSalaries();
                } else {
                    showAlert('Error in bulk update: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                hideLoading();
                showAlert('Network error: ' + error.message, 'danger');
            });
        }
    }
    
    // Payslip Generation Functions
    function bulkDownloadPayslips() {
        showLoading('Preparing bulk download...');
        fetch('../../api/payslip_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=bulk_download'
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                showAlert('Bulk download ready!', 'success');
                // Create download link for ZIP file
                const link = document.createElement('a');
                link.href = data.zip_url;
                link.download = data.filename;
                link.click();
            } else {
                showAlert('Error preparing bulk download: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            hideLoading();
            showAlert('Network error: ' + error.message, 'danger');
        });
    }
    
    function emailPayslips() {
        if (confirm('Send payslips to all employees via email?')) {
            showLoading('Sending payslips via email...');
            fetch('../../api/payslip_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=email_payslips'
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showAlert(`Payslips sent successfully to ${data.sent_count} employees!`, 'success');
                } else {
                    showAlert('Error sending payslips: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                hideLoading();
                showAlert('Network error: ' + error.message, 'danger');
            });
        }
    }
    
    function showPayslipPreview() {
        showAlert('Payslip preview feature will show a sample payslip with current template and formatting.', 'info');
        // Open payslip preview modal
    }
    
    function configurePayslipTemplate() {
        showAlert('Payslip template configuration allows you to customize the layout, add company logo, and modify fields.', 'info');
        // Open template configuration modal
    }
    
    // Reports Functions
    function loadMonthlyReport() {
        showAlert('Loading comprehensive monthly attendance analysis...', 'info');
        document.getElementById('reportDisplayCard').style.display = 'block';
        document.getElementById('reportTitle').textContent = 'Monthly Attendance Report';
        document.getElementById('reportContent').innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-2">Generating monthly report...</p>
            </div>
        `;
        
        // Simulate report loading
        setTimeout(() => {
            document.getElementById('reportContent').innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Attendance Summary</h6>
                        <canvas id="attendanceChart" width="400" height="200"></canvas>
                    </div>
                    <div class="col-md-6">
                        <h6>Key Metrics</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <tr><td>Average Attendance Rate</td><td><strong>92.5%</strong></td></tr>
                                <tr><td>Total Working Days</td><td><strong>22</strong></td></tr>
                                <tr><td>Late Arrivals</td><td><strong>15</strong></td></tr>
                                <tr><td>Early Departures</td><td><strong>8</strong></td></tr>
                            </table>
                        </div>
                    </div>
                </div>
            `;
        }, 2000);
    }
    
    function loadEmployeeReport() {
        showAlert('Loading individual employee performance tracking...', 'info');
        document.getElementById('reportDisplayCard').style.display = 'block';
        document.getElementById('reportTitle').textContent = 'Employee Performance Report';
        document.getElementById('reportContent').innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-success" role="status"></div>
                <p class="mt-2">Analyzing employee data...</p>
            </div>
        `;
        
        // Simulate employee report loading
        setTimeout(() => {
            document.getElementById('reportContent').innerHTML = `
                <div class="row">
                    <div class="col-12">
                        <h6>Top Performers</h6>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Attendance Rate</th>
                                        <th>Avg Working Hours</th>
                                        <th>Performance Score</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>John Doe</td>
                                        <td><span class="badge bg-success">98%</span></td>
                                        <td>8.5 hrs</td>
                                        <td><strong>95/100</strong></td>
                                    </tr>
                                    <tr>
                                        <td>Jane Smith</td>
                                        <td><span class="badge bg-success">96%</span></td>
                                        <td>8.2 hrs</td>
                                        <td><strong>92/100</strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
        }, 2000);
    }
    
    function loadPayrollReport() {
        showAlert('Loading salary and payroll analysis reports...', 'info');
        document.getElementById('reportDisplayCard').style.display = 'block';
        document.getElementById('reportTitle').textContent = 'Payroll Analysis Report';
        document.getElementById('reportContent').innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-info" role="status"></div>
                <p class="mt-2">Calculating payroll analytics...</p>
            </div>
        `;
        
        // Simulate payroll report loading
        setTimeout(() => {
            document.getElementById('reportContent').innerHTML = `
                <div class="row">
                    <div class="col-md-4">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h4>₹2,45,000</h4>
                                <small>Total Payroll</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h4>₹32,500</h4>
                                <small>Average Salary</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h4>15%</h4>
                                <small>Tax Deduction</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }, 2000);
    }
    
    function exportCurrentReport() {
        const reportTitle = document.getElementById('reportTitle').textContent;
        showAlert(`Exporting ${reportTitle} as PDF...`, 'info');
        // Implement PDF export functionality
    }
    
    function closeReport() {
        document.getElementById('reportDisplayCard').style.display = 'none';
    }
    
    // Team Dashboard Functions
    function loadTeamDashboard() {
        const tbody = document.getElementById('teamAttendanceTable');
        if (tbody) {
            tbody.innerHTML = `
                <tr><td>John Doe</td><td><span class="badge bg-success">Present</span></td><td>09:00 AM</td><td>7.5 hrs</td><td><button class="btn btn-sm btn-outline-primary">View</button></td></tr>
                <tr><td>Jane Smith</td><td><span class="badge bg-warning">Late</span></td><td>09:30 AM</td><td>7.0 hrs</td><td><button class="btn btn-sm btn-outline-primary">View</button></td></tr>
                <tr><td>Mike Johnson</td><td><span class="badge bg-danger">Absent</span></td><td>-</td><td>-</td><td><button class="btn btn-sm btn-outline-secondary">Contact</button></td></tr>
            `;
        }
    }
    
    function updateTeamDashboard(dashboardData) {
        // Update team dashboard with real data
        if (dashboardData && dashboardData.employees) {
            const tbody = document.getElementById('teamAttendanceTable');
            let html = '';
            dashboardData.employees.forEach(emp => {
                html += `
                    <tr>
                        <td>${emp.name}</td>
                        <td><span class="badge bg-${emp.status === 'Present' ? 'success' : emp.status === 'Late' ? 'warning' : 'danger'}">${emp.status}</span></td>
                        <td>${emp.check_in || '-'}</td>
                        <td>${emp.working_hours || '-'}</td>
                        <td><button class="btn btn-sm btn-outline-primary" onclick="viewEmployeeDetails(${emp.id})">View</button></td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }
    }
    
    function viewEmployeeDetails(employeeId) {
        showAlert(`Loading detailed information for employee ID: ${employeeId}`, 'info');
        // Open employee details modal or panel
    }
</script><style>
.stat-card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    transition: all 0.2s ease-in-out;
}

.stat-card:hover {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    transform: translateY(-2px);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
}

.stat-label {
    font-size: 0.875rem;
    color: #6c757d;
}

.page-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    border-radius: 0.5rem;
    margin-bottom: 2rem;
}

/* Report cards hover effect */
.report-card {
    transition: all 0.3s ease;
    cursor: pointer;
}

.report-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

/* Custom table styling */
.table th {
    background-color: #f8f9fa;
    font-weight: 600;
    border-top: none;
}

.table td {
    vertical-align: middle;
}

/* Modal enhancements */
.modal-header {
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: white;
    border-bottom: none;
}

.modal-header .btn-close {
    filter: invert(1);
}

/* Loading states */
.loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255,255,255,0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
}

/* Custom alerts positioning */
.position-fixed {
    z-index: 9999;
}

/* Tab content animation */
.tab-content .tab-pane {
    animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Button hover effects */
.btn {
    transition: all 0.2s ease;
}

.btn:hover {
    transform: translateY(-1px);
}

/* Card animations */
.card {
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

/* Statistics cards enhancements */
.stat-number {
    font-family: 'Segoe UI', system-ui, sans-serif;
    font-weight: 700;
    letter-spacing: -0.5px;
}

/* Table responsiveness */
@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.875rem;
    }
    
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }
    
    .card-body {
        padding: 1rem;
    }
}

/* Custom spinner */
.spinner-border-sm {
    width: 1rem;
    height: 1rem;
}

/* Form enhancements */
.form-control:focus, .form-select:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
}

/* Badge styling */
.badge {
    font-size: 0.75em;
    padding: 0.35em 0.65em;
}

/* Progress bar styling */
.progress {
    height: 8px;
    border-radius: 4px;
}

.progress-bar {
    border-radius: 4px;
}

/* Quick Actions Row Layout Styling */
.quick-action-btn {
    min-height: 60px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 8px;
    transition: all 0.3s ease;
    border-radius: 8px;
}

.quick-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.quick-action-btn i {
    font-size: 1.2em;
    margin-bottom: 2px;
}

.quick-action-btn small {
    font-weight: 600;
    opacity: 0.8;
}

.quick-action-btn div[style*="font-size: 0.9em"] {
    font-weight: 500;
    line-height: 1.1;
}

/* Responsive adjustments for quick actions */
@media (max-width: 768px) {
    .quick-action-btn {
        min-height: 50px;
        font-size: 0.85em;
    }
    
    .quick-action-btn i {
        font-size: 1em;
    }
}
</style>

<?php include '../../layouts/footer.php'; ?>
