<?php
$page_title = "Payroll Reports - HRMS";

// Include authentication and database
require_once '../auth_check.php';
require_once '../db.php';

// Include layouts
require_once 'hrms_header_simple.php';
require_once 'hrms_sidebar_simple.php';

// Include HRMS UI fix
$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'] ?? 'employee';

$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'] ?? 'employee';
?>

<!-- Page Content Starts Here -->
<div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="gradient-text mb-2" style="font-size: 2.5rem; font-weight: 700;">
                    <i class="bi bi-file-earmark-spreadsheet text-primary me-3"></i>Payroll Reports
                </h1>
                <p class="text-muted" style="font-size: 1.1rem;">Generate and view comprehensive payroll reports and analytics</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" onclick="exportAllReports()">
                    <i class="bi bi-download me-2"></i>Export All
                </button>
                <button class="btn btn-primary" onclick="generateNewReport()">
                    <i class="bi bi-plus-lg me-2"></i>New Report
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="d-flex justify-content-center mb-3">
                            <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                                <i class="bi bi-calendar-month text-primary fs-2"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-1">4</h3>
                        <p class="text-muted mb-0">Monthly Reports</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="d-flex justify-content-center mb-3">
                            <div class="bg-success bg-opacity-10 rounded-circle p-3">
                                <i class="bi bi-people text-success fs-2"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-1">174</h3>
                        <p class="text-muted mb-0">Total Employees</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="d-flex justify-content-center mb-3">
                            <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                                <i class="bi bi-currency-rupee text-warning fs-2"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-1">₹27.5L</h3>
                        <p class="text-muted mb-0">Total Payroll</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="d-flex justify-content-center mb-3">
                            <div class="bg-info bg-opacity-10 rounded-circle p-3">
                                <i class="bi bi-graph-up text-info fs-2"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-1">12%</h3>
                        <p class="text-muted mb-0">Growth Rate</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#monthly" role="tab">
                            <i class="bi bi-calendar-month me-2"></i>Monthly Reports
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#custom" role="tab">
                            <i class="bi bi-funnel me-2"></i>Custom Reports
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#analytics" role="tab">
                            <i class="bi bi-graph-up me-2"></i>Analytics
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <!-- Monthly Reports Tab -->
                    <div class="tab-pane fade show active" id="monthly" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Month</th>
                                        <th>Employees</th>
                                        <th>Total Salary</th>
                                        <th>Deductions</th>
                                        <th>Net Payroll</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($monthly_reports as $report): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($report['month']) ?></div>
                                        </td>
                                        <td><?= $report['employees'] ?></td>
                                        <td>
                                            <span class="fw-semibold text-primary">₹<?= number_format($report['total_salary']) ?></span>
                                        </td>
                                        <td>
                                            <span class="text-danger">₹<?= number_format($report['deductions']) ?></span>
                                        </td>
                                        <td>
                                            <span class="fw-semibold text-success">₹<?= number_format($report['net_payroll']) ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">Processed</span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="viewReport('<?= $report['month'] ?>')" title="View">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-success" onclick="downloadReport('<?= $report['month'] ?>')" title="Download">
                                                    <i class="bi bi-download"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-info" onclick="emailReport('<?= $report['month'] ?>')" title="Email">
                                                    <i class="bi bi-envelope"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Custom Reports Tab -->
                    <div class="tab-pane fade" id="custom" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0">Custom Report Generator</h5>
                                    </div>
                                    <div class="card-body">
                                        <form>
                                            <div class="mb-3">
                                                <label class="form-label">Report Type</label>
                                                <select class="form-select">
                                                    <option>Salary Summary</option>
                                                    <option>Department Wise</option>
                                                    <option>Tax Deductions</option>
                                                    <option>Overtime Analysis</option>
                                                    <option>Year-end Summary</option>
                                                </select>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">From Date</label>
                                                        <input type="date" class="form-control">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">To Date</label>
                                                        <input type="date" class="form-control">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Department</label>
                                                <select class="form-select">
                                                    <option>All Departments</option>
                                                    <option>HR</option>
                                                    <option>Finance</option>
                                                    <option>IT</option>
                                                    <option>Operations</option>
                                                </select>
                                            </div>
                                            <button type="button" class="btn btn-primary w-100">
                                                <i class="bi bi-file-earmark-text me-2"></i>Generate Custom Report
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0">Report Templates</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="border rounded p-3 mb-3">
                                            <h6>Monthly Payroll Summary</h6>
                                            <p class="text-muted small mb-2">Complete monthly payroll breakdown with all components</p>
                                            <button class="btn btn-outline-primary btn-sm">Use Template</button>
                                        </div>
                                        <div class="border rounded p-3 mb-3">
                                            <h6>Tax Compliance Report</h6>
                                            <p class="text-muted small mb-2">Tax deductions and compliance summary for authorities</p>
                                            <button class="btn btn-outline-primary btn-sm">Use Template</button>
                                        </div>
                                        <div class="border rounded p-3">
                                            <h6>Department Cost Analysis</h6>
                                            <p class="text-muted small mb-2">Department-wise salary and cost breakdown</p>
                                            <button class="btn btn-outline-primary btn-sm">Use Template</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Analytics Tab -->
                    <div class="tab-pane fade" id="analytics" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-8">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0">Payroll Trends</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="text-center py-5">
                                            <i class="bi bi-graph-up text-muted" style="font-size: 3rem;"></i>
                                            <p class="text-muted mt-3">Payroll analytics and trends will be displayed here</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0">Key Metrics</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-4">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="text-muted">Average Salary</span>
                                                <strong>₹45,000</strong>
                                            </div>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar bg-primary" style="width: 75%"></div>
                                            </div>
                                        </div>
                                        <div class="mb-4">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="text-muted">Overtime Hours</span>
                                                <strong>280 hrs</strong>
                                            </div>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar bg-warning" style="width: 45%"></div>
                                            </div>
                                        </div>
                                        <div class="mb-4">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="text-muted">Total Deductions</span>
                                                <strong>₹3.8L</strong>
                                            </div>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar bg-danger" style="width: 60%"></div>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="text-muted">Net Growth</span>
                                                <strong>+12%</strong>
                                            </div>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar bg-success" style="width: 85%"></div>
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

    <script>
        function viewReport(month) {
            showAlert(`Viewing ${month} report...`, 'info');
        }

        function downloadReport(month) {
            showAlert(`Downloading ${month} report...`, 'success');
        }

        function emailReport(month) {
            showAlert(`Emailing ${month} report...`, 'info');
        }

        function exportAllReports() {
            showAlert('Exporting all payroll reports...', 'info');
        }

        function generateNewReport() {
            showAlert('New report generation will be implemented soon!', 'info');
        }

        function showAlert(message, type = 'info') {
            const alertDiv = `
                <div class="alert alert-${type} alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; max-width: 400px;">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', alertDiv);
            
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    if (alert.textContent.includes(message)) {
                        alert.remove();
                    }
                });
            }, 5000);
        }
    </script>
</div>

<?php if (!isset($root_path)) 

<?php require_once 'hrms_footer_simple.php'; ?>