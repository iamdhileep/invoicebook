<?php
session_start();
if (!isset($root_path)) 
require_once '../config.php';
if (!isset($root_path)) 
require_once '../db.php';

$page_title = 'Tax Management - HRMS';

// Fetch tax settings from database
$tax_settings = [];
$result = mysqli_query($conn, "
    SELECT component_name as component, tax_rate as rate, 
           CASE rate_type 
               WHEN 'percentage' THEN 'Percentage'
               WHEN 'fixed_amount' THEN 'Fixed Amount'
               WHEN 'annual' THEN 'Annual'
           END as type, status
    FROM tax_settings 
    WHERE status = 'active'
    ORDER BY component_name
");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $tax_settings[] = $row;
    }
}

// Fetch employee tax data from database
$employee_tax_data = [];
$result = mysqli_query($conn, "
    SELECT employee_id as id, employee_name as name, gross_salary, 
           total_tax, net_salary, status
    FROM employee_tax_calculations 
    WHERE calculation_month = '" . date('Y-m') . "'
    ORDER BY employee_name
");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $employee_tax_data[] = $row;
    }
}

$current_page = 'tax_management';

include '../layouts/header.php';
if (!isset($root_path)) 
include '../layouts/sidebar.php';
?>

<div class="main-content animate-fade-in-up">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="gradient-text mb-2" style="font-size: 2.5rem; font-weight: 700;">
                    <i class="bi bi-calculator-fill text-primary me-3"></i>Tax Management
                </h1>
                <p class="text-muted" style="font-size: 1.1rem;">Manage tax settings, calculations, and compliance for payroll</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" onclick="bulkTaxCalculation()">
                    <i class="bi bi-calculator me-2"></i>Bulk Calculate
                </button>
                <button class="btn btn-primary" onclick="addTaxComponent()">
                    <i class="bi bi-plus-lg me-2"></i>Add Tax Component
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
                                <i class="bi bi-gear-fill text-primary fs-2"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-1">4</h3>
                        <p class="text-muted mb-0">Tax Components</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="d-flex justify-content-center mb-3">
                            <div class="bg-success bg-opacity-10 rounded-circle p-3">
                                <i class="bi bi-people-fill text-success fs-2"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-1">3</h3>
                        <p class="text-muted mb-0">Employees</p>
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
                        <h3 class="fw-bold mb-1">₹25.5K</h3>
                        <p class="text-muted mb-0">Total Tax</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="d-flex justify-content-center mb-3">
                            <div class="bg-info bg-opacity-10 rounded-circle p-3">
                                <i class="bi bi-shield-check text-info fs-2"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-1">100%</h3>
                        <p class="text-muted mb-0">Compliance</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#settings" role="tab">
                            <i class="bi bi-gear me-2"></i>Tax Settings
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#employees" role="tab">
                            <i class="bi bi-people me-2"></i>Employee Tax
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#calculator" role="tab">
                            <i class="bi bi-calculator me-2"></i>Tax Calculator
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#compliance" role="tab">
                            <i class="bi bi-shield-check me-2"></i>Compliance
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <!-- Tax Settings Tab -->
                    <div class="tab-pane fade show active" id="settings" role="tabpanel">
                        <div class="row g-4">
                            <?php foreach ($tax_settings as $setting): ?>
                            <div class="col-lg-6 col-xl-4">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <h6 class="mb-0 fw-bold"><?= htmlspecialchars($setting['component']) ?></h6>
                                            <span class="badge bg-success"><?= ucfirst($setting['status']) ?></span>
                                        </div>
                                        <div class="mb-3">
                                            <div class="text-muted small">Rate</div>
                                            <div class="fs-4 fw-bold text-primary">
                                                <?= $setting['rate'] ?><?= $setting['type'] == 'Percentage' ? '%' : '' ?>
                                                <small class="text-muted fs-6"><?= $setting['type'] ?></small>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-outline-primary btn-sm flex-fill" onclick="editTaxComponent('<?= $setting['component'] ?>')">
                                                <i class="bi bi-pencil"></i> Edit
                                            </button>
                                            <button class="btn btn-outline-secondary btn-sm" onclick="toggleTaxComponent('<?= $setting['component'] ?>')">
                                                <i class="bi bi-power"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Employee Tax Tab -->
                    <div class="tab-pane fade" id="employees" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Employee</th>
                                        <th>Gross Salary</th>
                                        <th>Total Tax</th>
                                        <th>Net Salary</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($employee_tax_data as $employee): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($employee['name']) ?></div>
                                            <div class="text-muted small">ID: <?= $employee['id'] ?></div>
                                        </td>
                                        <td>
                                            <span class="fw-semibold">₹<?= number_format($employee['gross_salary']) ?></span>
                                        </td>
                                        <td>
                                            <span class="text-danger fw-semibold">₹<?= number_format($employee['total_tax']) ?></span>
                                        </td>
                                        <td>
                                            <span class="text-success fw-semibold">₹<?= number_format($employee['net_salary']) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($employee['status'] == 'calculated'): ?>
                                                <span class="badge bg-success">Calculated</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="viewTaxBreakdown(<?= $employee['id'] ?>)" title="View Breakdown">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-warning" onclick="recalculateTax(<?= $employee['id'] ?>)" title="Recalculate">
                                                    <i class="bi bi-arrow-clockwise"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-success" onclick="downloadTaxSlip(<?= $employee['id'] ?>)" title="Download">
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

                    <!-- Tax Calculator Tab -->
                    <div class="tab-pane fade" id="calculator" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-gradient bg-primary text-white">
                                        <h5 class="card-title mb-0">
                                            <i class="bi bi-calculator me-2"></i>Tax Calculator
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <form id="taxCalculatorForm">
                                            <div class="mb-3">
                                                <label for="employee_select" class="form-label">Select Employee</label>
                                                <select class="form-select" id="employee_select" name="employee_id" required>
                                                    <option value="">Choose employee...</option>
                                                    <?php foreach ($employees as $emp): ?>
                                                    <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="basicSalary" class="form-label">Basic Salary</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">₹</span>
                                                    <input type="number" class="form-control" id="basicSalary" placeholder="Enter basic salary">
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="hra" class="form-label">HRA</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">₹</span>
                                                    <input type="number" class="form-control" id="hra" placeholder="Enter HRA">
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="otherAllowances" class="form-label">Other Allowances</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">₹</span>
                                                    <input type="number" class="form-control" id="otherAllowances" placeholder="Enter other allowances">
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="exemptions" class="form-label">Tax Exemptions</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">₹</span>
                                                    <input type="number" class="form-control" id="exemptions" placeholder="Enter exemptions">
                                                </div>
                                            </div>
                                            
                                            <button type="button" class="btn btn-primary w-100" onclick="calculateTax()">
                                                <i class="bi bi-calculator me-2"></i>Calculate Tax
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-gradient bg-success text-white">
                                        <h5 class="card-title mb-0">
                                            <i class="bi bi-receipt me-2"></i>Tax Calculation Results
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <div class="d-flex justify-content-between align-items-center py-2">
                                                    <span class="text-muted">Gross Salary:</span>
                                                    <span class="fw-bold" id="grossSalaryResult">₹0</span>
                                                </div>
                                                <hr class="my-2">
                                            </div>
                                            <div class="col-12">
                                                <div class="d-flex justify-content-between align-items-center py-2">
                                                    <span class="text-muted">Taxable Income:</span>
                                                    <span class="fw-bold" id="taxableIncomeResult">₹0</span>
                                                </div>
                                                <hr class="my-2">
                                            </div>
                                            <div class="col-12">
                                                <div class="d-flex justify-content-between align-items-center py-2">
                                                    <span class="text-muted">Income Tax:</span>
                                                    <span class="fw-bold text-danger" id="incomeTaxResult">₹0</span>
                                                </div>
                                                <hr class="my-2">
                                            </div>
                                            <div class="col-12">
                                                <div class="d-flex justify-content-between align-items-center py-2">
                                                    <span class="text-muted">Professional Tax:</span>
                                                    <span class="fw-bold text-warning" id="professionalTaxResult">₹200</span>
                                                </div>
                                                <hr class="my-2">
                                            </div>
                                            <div class="col-12">
                                                <div class="bg-light rounded p-3">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="fw-bold">Net Salary:</span>
                                                        <span class="fw-bold fs-5 text-success" id="netSalaryResult">₹0</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Compliance Tab -->
                    <div class="tab-pane fade" id="compliance" role="tabpanel">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-gradient bg-info text-white">
                                        <h5 class="card-title mb-0">
                                            <i class="bi bi-shield-check me-2"></i>Tax Compliance Status
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="text-center py-4">
                                            <i class="bi bi-shield-check text-success" style="font-size: 4rem;"></i>
                                            <h4 class="mt-3 text-success">100% Compliant</h4>
                                            <p class="text-muted">All tax requirements are up to date</p>
                                        </div>
                                        
                                        <div class="border-top pt-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="text-muted">TDS Returns</span>
                                                <span class="badge bg-success">Filed</span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="text-muted">PF Returns</span>
                                                <span class="badge bg-success">Filed</span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="text-muted">ESI Returns</span>
                                                <span class="badge bg-warning">Pending</span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="text-muted">Professional Tax</span>
                                                <span class="badge bg-success">Filed</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-gradient bg-warning text-white">
                                        <h5 class="card-title mb-0">
                                            <i class="bi bi-calendar-check me-2"></i>Upcoming Deadlines
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                                            <div class="bg-danger bg-opacity-10 rounded-circle p-2 me-3">
                                                <i class="bi bi-exclamation-triangle text-danger"></i>
                                            </div>
                                            <div>
                                                <div class="fw-semibold">ESI Return Filing</div>
                                                <div class="text-muted small">Due: March 15, 2024</div>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                                            <div class="bg-warning bg-opacity-10 rounded-circle p-2 me-3">
                                                <i class="bi bi-clock text-warning"></i>
                                            </div>
                                            <div>
                                                <div class="fw-semibold">TDS Quarterly Return</div>
                                                <div class="text-muted small">Due: March 31, 2024</div>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex align-items-center">
                                            <div class="bg-info bg-opacity-10 rounded-circle p-2 me-3">
                                                <i class="bi bi-info-circle text-info"></i>
                                            </div>
                                            <div>
                                                <div class="fw-semibold">Annual Tax Filing</div>
                                                <div class="text-muted small">Due: July 31, 2024</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-gradient bg-secondary text-white">
                                        <h5 class="card-title mb-0">
                                            <i class="bi bi-file-earmark-text me-2"></i>Generate Compliance Reports
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-md-3">
                                                <button class="btn btn-outline-primary w-100" onclick="generateReport('tds')">
                                                    <i class="bi bi-file-earmark-check me-2"></i>TDS Report
                                                </button>
                                            </div>
                                            <div class="col-md-3">
                                                <button class="btn btn-outline-success w-100" onclick="generateReport('pf')">
                                                    <i class="bi bi-file-earmark-plus me-2"></i>PF Report
                                                </button>
                                            </div>
                                            <div class="col-md-3">
                                                <button class="btn btn-outline-info w-100" onclick="generateReport('esi')">
                                                    <i class="bi bi-file-earmark-medical me-2"></i>ESI Report
                                                </button>
                                            </div>
                                            <div class="col-md-3">
                                                <button class="btn btn-outline-warning w-100" onclick="generateReport('all')">
                                                    <i class="bi bi-files me-2"></i>All Reports
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
        function editTaxComponent(component) {
            showAlert(`Editing ${component} settings...`, 'info');
        }

        function toggleTaxComponent(component) {
            showAlert(`Toggling ${component} status...`, 'warning');
        }

        function addTaxComponent() {
            showAlert('Add new tax component form will be implemented!', 'info');
        }

        function viewTaxBreakdown(employeeId) {
            showAlert(`Viewing tax breakdown for employee ${employeeId}...`, 'info');
        }

        function recalculateTax(employeeId) {
            showAlert(`Recalculating tax for employee ${employeeId}...`, 'warning');
        }

        function downloadTaxSlip(employeeId) {
            showAlert(`Downloading tax slip for employee ${employeeId}...`, 'success');
        }

        function bulkTaxCalculation() {
            showAlert('Bulk tax calculation will be implemented!', 'info');
        }

        function generateReport(type) {
            showAlert(`Generating ${type.toUpperCase()} report...`, 'info');
        }

        function calculateTax() {
            const basicSalary = parseFloat(document.getElementById('basicSalary').value) || 0;
            const hra = parseFloat(document.getElementById('hra').value) || 0;
            const otherAllowances = parseFloat(document.getElementById('otherAllowances').value) || 0;
            const exemptions = parseFloat(document.getElementById('exemptions').value) || 0;

            const grossSalary = basicSalary + hra + otherAllowances;
            const taxableIncome = grossSalary - exemptions;
            
            // Simple tax calculation (basic rates)
            let incomeTax = 0;
            if (taxableIncome > 1000000) {
                incomeTax = taxableIncome * 0.30;
            } else if (taxableIncome > 500000) {
                incomeTax = taxableIncome * 0.20;
            } else if (taxableIncome > 250000) {
                incomeTax = taxableIncome * 0.10;
            }

            const professionalTax = 200; // Monthly
            const totalTax = incomeTax + professionalTax;
            const netSalary = grossSalary - totalTax;

            // Update results
            document.getElementById('grossSalaryResult').textContent = `₹${grossSalary.toLocaleString()}`;
            document.getElementById('taxableIncomeResult').textContent = `₹${taxableIncome.toLocaleString()}`;
            document.getElementById('incomeTaxResult').textContent = `₹${incomeTax.toLocaleString()}`;
            document.getElementById('professionalTaxResult').textContent = `₹${professionalTax}`;
            document.getElementById('netSalaryResult').textContent = `₹${netSalary.toLocaleString()}`;

            showAlert('Tax calculation completed!', 'success');
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

<?php if (!isset($root_path))  include '../layouts/footer.php'; ?>
</body>
</html>
