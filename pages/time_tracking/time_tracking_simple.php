<?php
ob_start(); // Start output buffering

session_start();
$page_title = 'Time Tracking - Business Management';

// Database connection
require_once '../../config.php';
require_once '../../db.php';

$database = new Database();
$conn = $database->getConnection();

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
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
                    <button class="btn btn-outline-primary" onclick="refreshAllData()">
                        <i class="bi bi-arrow-clockwise me-1"></i>
                        Refresh Data
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                        <i class="bi bi-person-plus me-1"></i>
                        Add Employee
                    </button>
                </div>
            </div>
        </div>

        <!-- Summary Cards Row -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card text-center">
                    <div class="card-body">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary mb-3 mx-auto">
                            <i class="bi bi-people"></i>
                        </div>
                        <h3 class="stat-number text-primary mb-1" id="totalEmployees">0</h3>
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
                        <h3 class="stat-number text-success mb-1" id="presentToday">0</h3>
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
                        <h3 class="stat-number text-warning mb-1" id="avgWorkingHours">0</h3>
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
                        <h3 class="stat-number text-info mb-1" id="totalPayroll">₹0</h3>
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
                            Attendance Tracking
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
                            Reports
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="timeTrackingTabContent">
                    <!-- Attendance Tracking Tab -->
                    <div class="tab-pane fade show active" id="attendance" role="tabpanel">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            This is the Attendance Tracking section. Content will be loaded here.
                        </div>
                    </div>
                    
                    <!-- Salary Management Tab -->
                    <div class="tab-pane fade" id="salary" role="tabpanel">
                        <div class="alert alert-success">
                            <i class="bi bi-currency-rupee me-2"></i>
                            This is the Salary Management section. Content will be loaded here.
                        </div>
                    </div>
                    
                    <!-- Pay-slip Generation Tab -->
                    <div class="tab-pane fade" id="payslip" role="tabpanel">
                        <div class="alert alert-warning">
                            <i class="bi bi-file-earmark-text me-2"></i>
                            This is the Pay-slip Generation section. Content will be loaded here.
                        </div>
                    </div>
                    
                    <!-- Reports Tab -->
                    <div class="tab-pane fade" id="reports" role="tabpanel">
                        <div class="alert alert-primary">
                            <i class="bi bi-graph-up me-2"></i>
                            This is the Reports section. Content will be loaded here.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Simple JavaScript to update stats
function refreshAllData() {
    // Simulate loading
    document.getElementById('totalEmployees').textContent = '15';
    document.getElementById('presentToday').textContent = '12';
    document.getElementById('avgWorkingHours').textContent = '8.5';
    document.getElementById('totalPayroll').textContent = '₹2,45,000';
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    refreshAllData();
});
</script>

<?php include '../../layouts/footer.php'; ?>

<?php
ob_end_flush(); // End output buffering and send content
?>
