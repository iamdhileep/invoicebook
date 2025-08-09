<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Business Reports & Analytics';

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0"><i class="fas fa-chart-line me-2"></i>Business Reports & Analytics</h1>
                <p class="text-muted mb-0">Comprehensive business insights and performance analytics</p>
            </div>
            <div>
                <button class="btn btn-success me-2" onclick="exportReport()">
                    <i class="fas fa-download me-2"></i>Export Report
                </button>
                <button class="btn btn-info me-2" onclick="scheduleReport()">
                    <i class="fas fa-clock me-2"></i>Schedule Report
                </button>
                <button class="btn btn-primary" onclick="customReport()">
                    <i class="fas fa-cog me-2"></i>Custom Report
                </button>
            </div>
        </div>

        <!-- Report Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Report Filters</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Date Range</label>
                        <select id="date-range" class="form-select" onchange="updateDateRange()">
                            <option value="today">Today</option>
                            <option value="yesterday">Yesterday</option>
                            <option value="this_week">This Week</option>
                            <option value="last_week">Last Week</option>
                            <option value="this_month" selected>This Month</option>
                            <option value="last_month">Last Month</option>
                            <option value="this_quarter">This Quarter</option>
                            <option value="this_year">This Year</option>
                            <option value="custom">Custom Range</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">From Date</label>
                        <input type="date" id="from-date" class="form-control" value="<?= date('Y-m-01') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">To Date</label>
                        <input type="date" id="to-date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <button class="btn btn-primary d-block" onclick="loadReports()">
                            <i class="fas fa-sync me-2"></i>Generate Report
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats Dashboard -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-gradient-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-0" id="total-revenue">₹0</h3>
                                <small>Total Revenue</small>
                                <div class="progress mt-2" style="height: 4px;">
                                    <div class="progress-bar bg-light" id="revenue-progress" style="width: 0%"></div>
                                </div>
                            </div>
                            <i class="fas fa-rupee-sign fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-gradient-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-0" id="net-profit">₹0</h3>
                                <small>Net Profit</small>
                                <div class="progress mt-2" style="height: 4px;">
                                    <div class="progress-bar bg-light" id="profit-progress" style="width: 0%"></div>
                                </div>
                            </div>
                            <i class="fas fa-chart-line fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-gradient-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-0" id="total-expenses">₹0</h3>
                                <small>Total Expenses</small>
                                <div class="progress mt-2" style="height: 4px;">
                                    <div class="progress-bar bg-light" id="expense-progress" style="width: 0%"></div>
                                </div>
                            </div>
                            <i class="fas fa-credit-card fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-gradient-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-0" id="total-invoices">0</h3>
                                <small>Total Invoices</small>
                                <div class="progress mt-2" style="height: 4px;">
                                    <div class="progress-bar bg-light" id="invoice-progress" style="width: 0%"></div>
                                </div>
                            </div>
                            <i class="fas fa-file-invoice fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Tabs -->
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-pills nav-fill" id="report-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="financial-tab" data-bs-toggle="pill" data-bs-target="#financial" role="tab">
                            <i class="fas fa-chart-bar me-2"></i>Financial Analysis
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="sales-tab" data-bs-toggle="pill" data-bs-target="#sales" role="tab">
                            <i class="fas fa-shopping-cart me-2"></i>Sales Performance
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="customer-tab" data-bs-toggle="pill" data-bs-target="#customer" role="tab">
                            <i class="fas fa-users me-2"></i>Customer Analytics
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="expense-tab" data-bs-toggle="pill" data-bs-target="#expense" role="tab">
                            <i class="fas fa-receipt me-2"></i>Expense Analysis
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="employee-tab" data-bs-toggle="pill" data-bs-target="#employee" role="tab">
                            <i class="fas fa-user-tie me-2"></i>Employee Reports
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="report-content">
                    <!-- Financial Analysis Tab -->
                    <div class="tab-pane fade show active" id="financial" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Revenue vs Expenses Trend</h6>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="financial-chart" height="300"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Profit Margin Analysis</h6>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="profit-margin-chart" height="300"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Financial Summary</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover" id="financial-summary-table">
                                                <thead>
                                                    <tr>
                                                        <th>Period</th>
                                                        <th>Revenue</th>
                                                        <th>Expenses</th>
                                                        <th>Gross Profit</th>
                                                        <th>Profit Margin</th>
                                                        <th>Growth</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="financial-data">
                                                    <tr>
                                                        <td colspan="6" class="text-center">
                                                            <div class="spinner-border text-primary" role="status">
                                                                <span class="visually-hidden">Loading...</span>
                                                            </div>
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

                    <!-- Sales Performance Tab -->
                    <div class="tab-pane fade" id="sales" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Top Selling Items</h6>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="top-items-chart" height="300"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Sales Trend</h6>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="sales-trend-chart" height="300"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Sales Performance Details</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover" id="sales-performance-table">
                                                <thead>
                                                    <tr>
                                                        <th>Item Name</th>
                                                        <th>Qty Sold</th>
                                                        <th>Revenue</th>
                                                        <th>Avg Price</th>
                                                        <th>Growth</th>
                                                        <th>Performance</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="sales-data">
                                                    <!-- Sales data will be loaded here -->
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Analytics Tab -->
                    <div class="tab-pane fade" id="customer" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Customer Distribution</h6>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="customer-distribution-chart" height="250"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Top Customers by Revenue</h6>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="top-customers-chart" height="250"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Customer Analytics Details</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover" id="customer-analytics-table">
                                                <thead>
                                                    <tr>
                                                        <th>Customer Name</th>
                                                        <th>Total Orders</th>
                                                        <th>Total Revenue</th>
                                                        <th>Avg Order Value</th>
                                                        <th>Last Order</th>
                                                        <th>Customer Value</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="customer-data">
                                                    <!-- Customer data will be loaded here -->
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Expense Analysis Tab -->
                    <div class="tab-pane fade" id="expense" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Expense Categories</h6>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="expense-categories-chart" height="300"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Monthly Expense Trend</h6>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="expense-trend-chart" height="300"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Expense Analysis Details</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover" id="expense-analysis-table">
                                                <thead>
                                                    <tr>
                                                        <th>Category</th>
                                                        <th>Total Amount</th>
                                                        <th>Count</th>
                                                        <th>Average</th>
                                                        <th>% of Total</th>
                                                        <th>Trend</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="expense-data">
                                                    <!-- Expense data will be loaded here -->
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Employee Reports Tab -->
                    <div class="tab-pane fade" id="employee" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Attendance Overview</h6>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="attendance-chart" height="300"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Payroll Summary</h6>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="payroll-chart" height="300"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Employee Performance Summary</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover" id="employee-performance-table">
                                                <thead>
                                                    <tr>
                                                        <th>Employee</th>
                                                        <th>Department</th>
                                                        <th>Attendance %</th>
                                                        <th>Total Salary</th>
                                                        <th>Performance</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="employee-data">
                                                    <!-- Employee data will be loaded here -->
                                                </tbody>
                                            </table>
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

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportModalLabel">
                    <i class="fas fa-download me-2"></i>Export Report
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="exportForm">
                    <div class="mb-3">
                        <label class="form-label">Report Type</label>
                        <select class="form-select" id="export-report-type" required>
                            <option value="">Select Report Type</option>
                            <option value="financial">Financial Analysis</option>
                            <option value="sales">Sales Performance</option>
                            <option value="customer">Customer Analytics</option>
                            <option value="expense">Expense Analysis</option>
                            <option value="employee">Employee Reports</option>
                            <option value="comprehensive">Comprehensive Report</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Export Format</label>
                        <div class="row">
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="export_format" id="format-pdf" value="pdf" checked>
                                    <label class="form-check-label" for="format-pdf">
                                        <i class="fas fa-file-pdf text-danger me-2"></i>PDF
                                    </label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="export_format" id="format-excel" value="excel">
                                    <label class="form-check-label" for="format-excel">
                                        <i class="fas fa-file-excel text-success me-2"></i>Excel
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="include-charts" checked>
                            <label class="form-check-label" for="include-charts">
                                Include Charts and Graphs
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="processExport()">
                    <i class="fas fa-download me-2"></i>Export Report
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Report Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scheduleModalLabel">
                    <i class="fas fa-clock me-2"></i>Schedule Report
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="scheduleForm">
                    <div class="mb-3">
                        <label class="form-label">Report Name</label>
                        <input type="text" class="form-control" id="schedule-report-name" placeholder="Monthly Business Summary" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Frequency</label>
                        <select class="form-select" id="schedule-frequency" required>
                            <option value="">Select Frequency</option>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Recipients</label>
                        <textarea class="form-control" id="schedule-emails" rows="3" 
                                  placeholder="Enter email addresses separated by commas"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="processSchedule()">
                    <i class="fas fa-clock me-2"></i>Schedule Report
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../../layouts/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../../assets/js/business_reports.js"></script>
