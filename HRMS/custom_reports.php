<?php
session_start();
$page_title = "Custom Reports - HRMS";

// Include header and navigation
include '../layouts/header.php';
include '../layouts/sidebar.php';
include '../db.php';
?>

<div class="main-content">
    <div class="container-fluid p-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h3 class="mb-0">
                                    <i class="fas fa-file-export me-2"></i>
                                    Custom Reports Generator
                                </h3>
                                <p class="mb-0 opacity-75">Generate tailored reports for your HR needs</p>
                            </div>
                            <div class="col-md-4 text-end">
                                <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#newReportModal">
                                    <i class="fas fa-plus"></i> Create New Report
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Categories -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card category-card text-center">
                    <div class="card-body">
                        <div class="category-icon bg-primary text-white rounded-circle mx-auto mb-3">
                            <i class="fas fa-users fa-2x"></i>
                        </div>
                        <h5>Employee Reports</h5>
                        <p class="text-muted">Personnel, demographics, and workforce data</p>
                        <button class="btn btn-primary" onclick="showReports('employee')">View Reports</button>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card category-card text-center">
                    <div class="card-body">
                        <div class="category-icon bg-success text-white rounded-circle mx-auto mb-3">
                            <i class="fas fa-calendar-check fa-2x"></i>
                        </div>
                        <h5>Attendance Reports</h5>
                        <p class="text-muted">Time tracking, attendance, and leave analysis</p>
                        <button class="btn btn-success" onclick="showReports('attendance')">View Reports</button>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card category-card text-center">
                    <div class="card-body">
                        <div class="category-icon bg-warning text-white rounded-circle mx-auto mb-3">
                            <i class="fas fa-money-bill-wave fa-2x"></i>
                        </div>
                        <h5>Payroll Reports</h5>
                        <p class="text-muted">Salary, benefits, and compensation analysis</p>
                        <button class="btn btn-warning" onclick="showReports('payroll')">View Reports</button>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card category-card text-center">
                    <div class="card-body">
                        <div class="category-icon bg-danger text-white rounded-circle mx-auto mb-3">
                            <i class="fas fa-chart-bar fa-2x"></i>
                        </div>
                        <h5>Performance Reports</h5>
                        <p class="text-muted">KPIs, reviews, and performance metrics</p>
                        <button class="btn btn-danger" onclick="showReports('performance')">View Reports</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Reports -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-bolt text-warning me-2"></i>
                            Quick Reports
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="quick-report-item">
                                    <div class="d-flex align-items-center">
                                        <div class="report-icon bg-primary text-white me-3">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Employee List</h6>
                                            <small class="text-muted">Complete employee directory</small>
                                        </div>
                                        <button class="btn btn-sm btn-outline-primary ms-auto" onclick="generateQuickReport('employee_list')">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="quick-report-item">
                                    <div class="d-flex align-items-center">
                                        <div class="report-icon bg-success text-white me-3">
                                            <i class="fas fa-calendar"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Monthly Attendance</h6>
                                            <small class="text-muted">Current month attendance summary</small>
                                        </div>
                                        <button class="btn btn-sm btn-outline-success ms-auto" onclick="generateQuickReport('monthly_attendance')">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="quick-report-item">
                                    <div class="d-flex align-items-center">
                                        <div class="report-icon bg-warning text-white me-3">
                                            <i class="fas fa-dollar-sign"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Payroll Summary</h6>
                                            <small class="text-muted">Current month payroll data</small>
                                        </div>
                                        <button class="btn btn-sm btn-outline-warning ms-auto" onclick="generateQuickReport('payroll_summary')">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Custom Report Builder -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-cog text-primary me-2"></i>
                            Report Builder
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="reportBuilderForm">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Report Type</label>
                                    <select class="form-select" id="reportType" required>
                                        <option value="">Select Report Type</option>
                                        <option value="employee">Employee Report</option>
                                        <option value="attendance">Attendance Report</option>
                                        <option value="payroll">Payroll Report</option>
                                        <option value="performance">Performance Report</option>
                                        <option value="department">Department Report</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Date Range</label>
                                    <select class="form-select" id="dateRange">
                                        <option value="current_month">Current Month</option>
                                        <option value="last_month">Last Month</option>
                                        <option value="current_quarter">Current Quarter</option>
                                        <option value="last_quarter">Last Quarter</option>
                                        <option value="current_year">Current Year</option>
                                        <option value="custom">Custom Range</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-3" id="customDateRange" style="display: none;">
                                <div class="col-md-6">
                                    <label class="form-label">From Date</label>
                                    <input type="date" class="form-control" id="fromDate">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">To Date</label>
                                    <input type="date" class="form-control" id="toDate">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Department</label>
                                    <select class="form-select" id="department">
                                        <option value="">All Departments</option>
                                        <option value="IT">IT Department</option>
                                        <option value="HR">HR Department</option>
                                        <option value="Finance">Finance</option>
                                        <option value="Marketing">Marketing</option>
                                        <option value="Operations">Operations</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Output Format</label>
                                    <select class="form-select" id="outputFormat">
                                        <option value="pdf">PDF</option>
                                        <option value="excel">Excel</option>
                                        <option value="csv">CSV</option>
                                    </select>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-secondary me-2" onclick="resetForm()">Reset</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-magic"></i> Generate Report
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Recent Reports -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-history text-success me-2"></i>
                            Recent Reports
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <div class="list-group-item d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1">Employee Directory</h6>
                                    <small class="text-muted">Generated 2 hours ago</small>
                                </div>
                                <button class="btn btn-sm btn-outline-primary" onclick="downloadReport('emp_dir_001')">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1">Monthly Attendance</h6>
                                    <small class="text-muted">Generated yesterday</small>
                                </div>
                                <button class="btn btn-sm btn-outline-success" onclick="downloadReport('att_mon_002')">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1">Payroll Summary</h6>
                                    <small class="text-muted">Generated 3 days ago</small>
                                </div>
                                <button class="btn btn-sm btn-outline-warning" onclick="downloadReport('pay_sum_003')">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.category-card {
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: transform 0.2s;
}

.category-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.category-icon {
    width: 80px;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.quick-report-item {
    padding: 15px;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    transition: background-color 0.2s;
}

.quick-report-item:hover {
    background-color: #f8f9fa;
}

.report-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.card {
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
</style>

<script>
// Date range change handler
document.getElementById('dateRange').addEventListener('change', function() {
    const customRange = document.getElementById('customDateRange');
    if (this.value === 'custom') {
        customRange.style.display = 'block';
    } else {
        customRange.style.display = 'none';
    }
});

// Form submission
document.getElementById('reportBuilderForm').addEventListener('submit', function(e) {
    e.preventDefault();
    generateCustomReport();
});

function showReports(category) {
    alert('Showing ' + category + ' reports. This would navigate to a filtered view.');
}

function generateQuickReport(reportType) {
    alert('Generating ' + reportType + ' report. This would trigger the actual report generation.');
}

function generateCustomReport() {
    const formData = new FormData(document.getElementById('reportBuilderForm'));
    alert('Generating custom report with the selected parameters.');
}

function downloadReport(reportId) {
    alert('Downloading report: ' + reportId);
}

function resetForm() {
    document.getElementById('reportBuilderForm').reset();
    document.getElementById('customDateRange').style.display = 'none';
}
</script>

<?php include '../layouts/footer.php'; ?>
