<?php
$page_title = "Full & Final Settlement - HRMS";

// Include authentication and database
require_once '../auth_check.php';
require_once '../db.php';

// Include layouts
require_once 'hrms_header_simple.php';
require_once 'hrms_sidebar_simple.php';

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
                    <i class="bi bi-calculator-fill text-primary me-3"></i>Full & Final Settlement
                </h1>
                <p class="text-muted" style="font-size: 1.1rem;">Manage employee settlement processes and final payments</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" onclick="generateSettlementReport()">
                    <i class="bi bi-file-earmark-text me-2"></i>Settlement Report
                </button>
                <button class="btn btn-primary" onclick="initiateNewSettlement()">
                    <i class="bi bi-plus-lg me-2"></i>New Settlement
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="d-flex justify-content-center mb-3">
                            <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                                <i class="bi bi-clock-fill text-warning fs-2"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-1">2</h3>
                        <p class="text-muted mb-0">Pending Settlements</p>
                        <small class="text-warning">Awaiting approval</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="d-flex justify-content-center mb-3">
                            <div class="bg-success bg-opacity-10 rounded-circle p-3">
                                <i class="bi bi-check-circle-fill text-success fs-2"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-1">1</h3>
                        <p class="text-muted mb-0">Completed</p>
                        <small class="text-success">This month</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="d-flex justify-content-center mb-3">
                            <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                                <i class="bi bi-currency-rupee text-primary fs-2"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-1">₹3.79L</h3>
                        <p class="text-muted mb-0">Total Settlement</p>
                        <small class="text-primary">Amount processed</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="d-flex justify-content-center mb-3">
                            <div class="bg-info bg-opacity-10 rounded-circle p-3">
                                <i class="bi bi-calendar-check text-info fs-2"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-1">5.2</h3>
                        <p class="text-muted mb-0">Avg Days</p>
                        <small class="text-info">Processing time</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Tabs -->
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#pending" role="tab">
                            <i class="bi bi-clock me-2"></i>Pending Settlements
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#calculator" role="tab">
                            <i class="bi bi-calculator me-2"></i>Settlement Calculator
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#completed" role="tab">
                            <i class="bi bi-check-circle me-2"></i>Completed
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#reports" role="tab">
                            <i class="bi bi-graph-up me-2"></i>Reports
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <!-- Pending Settlements Tab -->
                    <div class="tab-pane fade show active" id="pending" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Last Working Day</th>
                                        <th>Settlement Amount</th>
                                        <th>Status</th>
                                        <th>Initiated By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_settlements as $settlement): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($settlement['name']) ?></div>
                                            <div class="text-muted small"><?= $settlement['employee_id'] ?></div>
                                        </td>
                                        <td><?= $settlement['department'] ?></td>
                                        <td><?= date('M j, Y', strtotime($settlement['last_working_day'])) ?></td>
                                        <td>
                                            <span class="fw-semibold text-success">₹<?= number_format($settlement['settlement_amount']) ?></span>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = [
                                                'pending' => 'bg-warning text-dark',
                                                'approved' => 'bg-primary text-white',
                                                'completed' => 'bg-success text-white'
                                            ][$settlement['status']] ?? 'bg-secondary text-white';
                                            ?>
                                            <span class="badge <?= $statusClass ?> rounded-pill text-uppercase"><?= $settlement['status'] ?></span>
                                        </td>
                                        <td>
                                            <div class="text-muted small"><?= $settlement['initiated_by'] ?></div>
                                            <div class="text-muted small"><?= date('M j, Y', strtotime($settlement['initiated_date'])) ?></div>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="viewSettlementDetails(<?= $settlement['id'] ?>)" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-warning" onclick="editSettlement(<?= $settlement['id'] ?>)" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-success" onclick="approveSettlement(<?= $settlement['id'] ?>)" title="Approve">
                                                    <i class="bi bi-check"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-info" onclick="downloadSettlement(<?= $settlement['id'] ?>)" title="Download">
                                                    <i class="bi bi-download"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Settlement Calculator Tab -->
                    <div class="tab-pane fade" id="calculator" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-8">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-gradient bg-primary text-white">
                                        <h5 class="mb-0">
                                            <i class="bi bi-calculator me-2"></i>Settlement Calculator
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <form id="settlementCalculatorForm">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Employee ID</label>
                                                    <input type="text" class="form-control" id="employeeId" placeholder="Enter employee ID">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Last Working Date</label>
                                                    <input type="date" class="form-control" id="lastWorkingDate">
                                                </div>
                                            </div>
                                            
                                            <div class="card mt-4 border-light">
                                                <div class="card-header bg-light">
                                                    <h6 class="mb-0 text-primary">
                                                        <i class="bi bi-currency-rupee me-2"></i>Salary Components
                                                    </h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Basic Salary</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text">₹</span>
                                                                <input type="number" class="form-control" id="basicSalary" placeholder="Monthly basic salary">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Pending Salary Days</label>
                                                            <input type="number" class="form-control" id="pendingSalaryDays" placeholder="Number of days">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="card mt-3 border-light">
                                                <div class="card-header bg-light">
                                                    <h6 class="mb-0 text-success">
                                                        <i class="bi bi-calendar-check me-2"></i>Leave Balance
                                                    </h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Pending Leave Days</label>
                                                            <input type="number" class="form-control" id="pendingLeaveDays" placeholder="Unused leave days">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Notice Period Shortfall</label>
                                                            <input type="number" class="form-control" id="noticePeriodShortfall" placeholder="Days short of notice">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="card mt-3 border-light">
                                                <div class="card-header bg-light">
                                                    <h6 class="mb-0 text-warning">
                                                        <i class="bi bi-plus-circle me-2"></i>Other Components
                                                    </h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Bonus Amount</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text">₹</span>
                                                                <input type="number" class="form-control" id="bonusAmount" placeholder="Pending bonus">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Other Deductions</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text">₹</span>
                                                                <input type="number" class="form-control" id="otherDeductions" placeholder="Loan, advance etc.">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mt-4">
                                                <button type="button" class="btn btn-primary btn-lg w-100" onclick="calculateSettlement()">
                                                    <i class="bi bi-calculator me-2"></i>Calculate Settlement
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-gradient bg-success text-white">
                                        <h5 class="mb-0">
                                            <i class="bi bi-receipt me-2"></i>Settlement Summary
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between align-items-center py-2">
                                                <span>Pending Salary:</span>
                                                <span class="fw-bold" id="pendingSalaryAmount">₹0</span>
                                            </div>
                                            <hr class="my-2">
                                            <div class="d-flex justify-content-between align-items-center py-2">
                                                <span>Leave Encashment:</span>
                                                <span class="fw-bold text-success" id="leaveEncashmentAmount">₹0</span>
                                            </div>
                                            <hr class="my-2">
                                            <div class="d-flex justify-content-between align-items-center py-2">
                                                <span>Bonus:</span>
                                                <span class="fw-bold text-primary" id="bonusAmountDisplay">₹0</span>
                                            </div>
                                            <hr class="my-2">
                                            <div class="d-flex justify-content-between align-items-center py-2">
                                                <span>Notice Period Recovery:</span>
                                                <span class="fw-bold text-danger" id="noticePeriodRecovery">-₹0</span>
                                            </div>
                                            <hr class="my-2">
                                            <div class="d-flex justify-content-between align-items-center py-2">
                                                <span>Other Deductions:</span>
                                                <span class="fw-bold text-danger" id="otherDeductionsDisplay">-₹0</span>
                                            </div>
                                            <hr class="my-3">
                                            <div class="bg-light rounded p-3">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="fw-bold">Net Settlement:</span>
                                                    <span class="fw-bold fs-5 text-success" id="netSettlementAmount">₹0</span>
                                                </div>
                                            </div>
                                        </div>
                                        <button class="btn btn-outline-primary w-100 mt-3" onclick="generateSettlementLetter()">
                                            <i class="bi bi-file-earmark-text me-2"></i>Generate Letter
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Completed Tab -->
                    <div class="tab-pane fade" id="completed" role="tabpanel">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header bg-gradient bg-success text-white">
                                        <h5 class="card-title mb-0">
                                            <i class="bi bi-check-circle me-2"></i>Completed Settlements
                                        </h5>
                                    </div>
                                    <div class="card-body text-center">
                                        <i class="bi bi-check-circle display-1 text-success opacity-75"></i>
                                        <h6 class="mt-3">Settlement Archive</h6>
                                        <p class="text-muted">View and manage completed settlement records with detailed history and documentation.</p>
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-success" onclick="viewCompletedSettlements()">
                                                <i class="bi bi-archive me-2"></i>View Archive
                                            </button>
                                            <button class="btn btn-outline-success" onclick="exportCompletedData()">
                                                <i class="bi bi-download me-2"></i>Export Data
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header bg-gradient bg-info text-white">
                                        <h5 class="card-title mb-0">
                                            <i class="bi bi-clock-history me-2"></i>Recent Completions
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="list-group list-group-flush">
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <div class="fw-semibold">Mike Johnson</div>
                                                    <div class="text-muted small">Operations - ₹1,56,000</div>
                                                </div>
                                                <span class="badge bg-success rounded-pill">Completed</span>
                                            </div>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <div class="fw-semibold">Jane Smith</div>
                                                    <div class="text-muted small">HR - ₹98,500</div>
                                                </div>
                                                <span class="badge bg-success rounded-pill">Completed</span>
                                            </div>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <div class="fw-semibold">Robert Davis</div>
                                                    <div class="text-muted small">Finance - ₹1,25,000</div>
                                                </div>
                                                <span class="badge bg-success rounded-pill">Completed</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Reports Tab -->
                    <div class="tab-pane fade" id="reports" role="tabpanel">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header bg-gradient bg-primary text-white">
                                        <h5 class="card-title mb-0">
                                            <i class="bi bi-graph-up me-2"></i>Settlement Analytics
                                        </h5>
                                    </div>
                                    <div class="card-body text-center">
                                        <i class="bi bi-graph-up display-1 text-primary opacity-75"></i>
                                        <h6 class="mt-3">Comprehensive Reports</h6>
                                        <p class="text-muted">Generate detailed settlement reports and analytics for management insights.</p>
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-primary" onclick="generateAnalyticsReport()">
                                                <i class="bi bi-file-earmark-bar-graph me-2"></i>Generate Report
                                            </button>
                                            <button class="btn btn-outline-primary" onclick="viewDashboard()">
                                                <i class="bi bi-speedometer2 me-2"></i>View Dashboard
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header bg-gradient bg-secondary text-white">
                                        <h5 class="card-title mb-0">
                                            <i class="bi bi-file-text me-2"></i>Quick Reports
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-grid gap-3">
                                            <button class="btn btn-outline-secondary d-flex justify-content-between align-items-center" onclick="generateQuickReport('monthly')">
                                                <span><i class="bi bi-calendar me-2"></i>Monthly Summary</span>
                                                <i class="bi bi-arrow-right"></i>
                                            </button>
                                            <button class="btn btn-outline-secondary d-flex justify-content-between align-items-center" onclick="generateQuickReport('departmental')">
                                                <span><i class="bi bi-building me-2"></i>Department-wise</span>
                                                <i class="bi bi-arrow-right"></i>
                                            </button>
                                            <button class="btn btn-outline-secondary d-flex justify-content-between align-items-center" onclick="generateQuickReport('pending')">
                                                <span><i class="bi bi-clock me-2"></i>Pending Status</span>
                                                <i class="bi bi-arrow-right"></i>
                                            </button>
                                            <button class="btn btn-outline-secondary d-flex justify-content-between align-items-center" onclick="generateQuickReport('financial')">
                                                <span><i class="bi bi-currency-rupee me-2"></i>Financial Summary</span>
                                                <i class="bi bi-arrow-right"></i>
                                            </button>
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

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewSettlementDetails(settlementId) {
            showAlert(`Viewing settlement details for ID ${settlementId}...`, 'info');
        }

        function editSettlement(settlementId) {
            showAlert(`Editing settlement ${settlementId}...`, 'warning');
        }

        function approveSettlement(settlementId) {
            showAlert(`Approving settlement ${settlementId}...`, 'success');
        }

        function downloadSettlement(settlementId) {
            showAlert(`Downloading settlement document ${settlementId}...`, 'info');
        }

        function initiateNewSettlement() {
            showAlert('New settlement initiation form will be implemented!', 'info');
        }

        function generateSettlementReport() {
            showAlert('Generating settlement report...', 'success');
        }

        function viewCompletedSettlements() {
            showAlert('Loading completed settlements archive...', 'success');
        }

        function exportCompletedData() {
            showAlert('Exporting completed settlement data...', 'info');
        }

        function generateAnalyticsReport() {
            showAlert('Generating comprehensive analytics report...', 'primary');
        }

        function viewDashboard() {
            showAlert('Opening settlement analytics dashboard...', 'primary');
        }

        function generateQuickReport(type) {
            const reportTypes = {
                'monthly': 'Monthly Settlement Summary',
                'departmental': 'Department-wise Settlement Report',
                'pending': 'Pending Settlements Status Report',
                'financial': 'Financial Settlement Summary'
            };
            showAlert(`Generating ${reportTypes[type]}...`, 'info');
        }

        function calculateSettlement() {
            const basicSalary = parseFloat(document.getElementById('basicSalary').value) || 0;
            const pendingSalaryDays = parseFloat(document.getElementById('pendingSalaryDays').value) || 0;
            const pendingLeaveDays = parseFloat(document.getElementById('pendingLeaveDays').value) || 0;
            const noticePeriodShortfall = parseFloat(document.getElementById('noticePeriodShortfall').value) || 0;
            const bonusAmount = parseFloat(document.getElementById('bonusAmount').value) || 0;
            const otherDeductions = parseFloat(document.getElementById('otherDeductions').value) || 0;

            // Calculate components
            const dailySalary = basicSalary / 30;
            const pendingSalaryAmount = dailySalary * pendingSalaryDays;
            const leaveEncashmentAmount = dailySalary * pendingLeaveDays;
            const noticePeriodRecovery = dailySalary * noticePeriodShortfall;

            const netSettlement = pendingSalaryAmount + leaveEncashmentAmount + bonusAmount - noticePeriodRecovery - otherDeductions;

            // Update display
            document.getElementById('pendingSalaryAmount').textContent = `₹${pendingSalaryAmount.toLocaleString()}`;
            document.getElementById('leaveEncashmentAmount').textContent = `₹${leaveEncashmentAmount.toLocaleString()}`;
            document.getElementById('bonusAmountDisplay').textContent = `₹${bonusAmount.toLocaleString()}`;
            document.getElementById('noticePeriodRecovery').textContent = `-₹${noticePeriodRecovery.toLocaleString()}`;
            document.getElementById('otherDeductionsDisplay').textContent = `-₹${otherDeductions.toLocaleString()}`;
            document.getElementById('netSettlementAmount').textContent = `₹${netSettlement.toLocaleString()}`;

            showAlert('Settlement calculation completed!', 'success');
        }

        function generateSettlementLetter() {
            showAlert('Generating settlement letter...', 'info');
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

<?php require_once 'hrms_footer_simple.php'; ?>