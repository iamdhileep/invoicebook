<?php
session_start();
$page_title = "Salary Structure - HRMS";

// Include header and navigation
require_once 'hrms_header_simple.php';
require_once 'hrms_sidebar_simple.php';

// Include HRMS UI fix
include '../db.php';

// Get real-time salary statistics
$total_grades_query = "SELECT COUNT(DISTINCT grade_level) as total FROM salary_grades";
$total_grades_result = $conn->query($total_grades_query);
$total_grades = $total_grades_result ? $total_grades_result->fetch_assoc()['total'] : 0;

$total_employees_query = "SELECT COUNT(*) as total FROM hr_employees WHERE status = 'active'";
$total_employees_result = $conn->query($total_employees_query);
$total_employees = $total_employees_result ? $total_employees_result->fetch_assoc()['total'] : 0;

$avg_salary_query = "SELECT AVG(monthly_salary) as avg_salary FROM hr_employees WHERE monthly_salary > 0 AND status = 'active'";
$avg_salary_result = $conn->query($avg_salary_query);
$avg_salary = $avg_salary_result ? round($avg_salary_result->fetch_assoc()['avg_salary']) : 0;

$total_payroll_query = "SELECT SUM(monthly_salary) as total_payroll FROM hr_employees WHERE monthly_salary > 0 AND status = 'active'";
$total_payroll_result = $conn->query($total_payroll_query);
$total_payroll = $total_payroll_result ? $total_payroll_result->fetch_assoc()['total_payroll'] : 0;
?>

<!-- Page Content Starts Here -->
<div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="h5 mb-0">💰 Salary Structure Management</h1>
                <p class="text-muted small">Manage salary grades, allowances, and deductions for your organization</p>
            </div>
            <div>
                <button class="btn btn-outline-success btn-sm" onclick="exportSalaryData()">
                    <i class="bi bi-download"></i> Export Data
                </button>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addGradeModal">
                    <i class="bi bi-plus-lg"></i> Add Grade
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-2 mb-3">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-ladder fs-3" style="color: #1976d2;"></i>
                        </div>
                        <h5 class="mb-1 fw-bold" style="color: #1976d2;"><?php echo $total_grades ?: '0'; ?></h5>
                        <small class="text-muted">Total Grades</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-people fs-3" style="color: #388e3c;"></i>
                        </div>
                        <h5 class="mb-1 fw-bold" style="color: #388e3c;"><?php echo $total_employees; ?></h5>
                        <small class="text-muted">Total Employees</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%);">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-plus-circle fs-3" style="color: #f57c00;"></i>
                        </div>
                        <h5 class="mb-1 fw-bold" style="color: #f57c00;">
                            <?php 
                            $allowances_query = "SELECT COUNT(DISTINCT allowance_type) as count FROM salary_allowances";
                            $allowances_result = $conn->query($allowances_query);
                            echo $allowances_result ? $allowances_result->fetch_assoc()['count'] : 0;
                            ?>
                        </h5>
                        <small class="text-muted">Allowances</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-dash-circle fs-3" style="color: #d32f2f;"></i>
                        </div>
                        <h5 class="mb-1 fw-bold" style="color: #d32f2f;">
                            <?php 
                            $deductions_query = "SELECT COUNT(DISTINCT deduction_type) as count FROM salary_deductions";
                            $deductions_result = $conn->query($deductions_query);
                            echo $deductions_result ? $deductions_result->fetch_assoc()['count'] : 0;
                            ?>
                        </h5>
                        <small class="text-muted">Deductions</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Tabs -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light border-0 py-2">
                <ul class="nav nav-tabs card-header-tabs border-0" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active border-0" data-bs-toggle="tab" data-bs-target="#grades" role="tab">
                            <i class="bi bi-ladder me-1"></i>Salary Grades
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link border-0" data-bs-toggle="tab" data-bs-target="#allowances" role="tab">
                            <i class="bi bi-plus-circle me-1"></i>Allowances
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link border-0" data-bs-toggle="tab" data-bs-target="#deductions" role="tab">
                            <i class="bi bi-dash-circle me-1"></i>Deductions
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link border-0" data-bs-toggle="tab" data-bs-target="#calculator" role="tab">
                            <i class="bi bi-calculator me-1"></i>Salary Calculator
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body p-3">
                <div class="tab-content">
                    <!-- Salary Grades Tab -->
                    <div class="tab-pane fade show active" id="grades" role="tabpanel">
                        <div class="row g-3">
                            <?php foreach ($salary_grades as $grade): ?>
                            <div class="col-lg-6 col-md-6">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-center mb-2">
                                                    <span class="badge bg-primary me-2"><?= htmlspecialchars($grade['grade']) ?></span>
                                                    <h6 class="mb-0 fw-bold"><?= htmlspecialchars($grade['level']) ?></h6>
                                                </div>
                                                <div class="text-muted small mb-2">
                                                    <i class="bi bi-people me-1"></i>
                                                    <span><?= $grade['employees'] ?> employees</span>
                                                </div>
                                                <div class="text-success fw-bold">
                                                    ₹<?= number_format($grade['min_salary']) ?> - ₹<?= number_format($grade['max_salary']) ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2 mt-2">
                                            <button class="btn btn-outline-primary btn-sm flex-fill" onclick="editGrade(<?= $grade['id'] ?>)">
                                                <i class="bi bi-pencil"></i> Edit
                                            </button>
                                            <button class="btn btn-outline-success btn-sm flex-fill" onclick="viewGradeDetails(<?= $grade['id'] ?>)">
                                                <i class="bi bi-eye"></i> View
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Allowances Tab -->
                    <div class="tab-pane fade" id="allowances" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0 text-dark"><i class="bi bi-plus-circle me-2"></i>Salary Allowances</h6>
                            <button class="btn btn-success btn-sm" onclick="showNewAllowanceModal()">
                                <i class="bi bi-plus-lg"></i> Add Allowance
                            </button>
                        </div>

                        <div class="row g-3">
                            <?php foreach ($allowances as $allowance): ?>
                            <div class="col-lg-6 col-md-6">
                                <div class="card border-0 shadow-sm h-100 border-start border-success border-3">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="card-title mb-1"><?= htmlspecialchars($allowance['name']) ?></h6>
                                                <small class="text-muted"><?= htmlspecialchars($allowance['applicable_to']) ?></small>
                                                <div class="text-success fw-bold mt-2">
                                                    <?php if ($allowance['type'] === 'percentage'): ?>
                                                        <?= $allowance['value'] ?>% of Basic Salary
                                                    <?php elseif ($allowance['type'] === 'fixed'): ?>
                                                        ₹<?= number_format($allowance['value']) ?> per month
                                                    <?php else: ?>
                                                        Variable Amount
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="editAllowance(<?= $allowance['id'] ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteAllowance(<?= $allowance['id'] ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Deductions Tab -->
                    <div class="tab-pane fade" id="deductions" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0 text-dark"><i class="bi bi-dash-circle me-2"></i>Salary Deductions</h6>
                            <button class="btn btn-danger btn-sm" onclick="showNewDeductionModal()">
                                <i class="bi bi-plus-lg"></i> Add Deduction
                            </button>
                        </div>

                        <div class="row g-3">
                            <?php foreach ($deductions as $deduction): ?>
                            <div class="col-lg-6 col-md-6">
                                <div class="card border-0 shadow-sm h-100 border-start border-danger border-3">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="card-title mb-1"><?= htmlspecialchars($deduction['name']) ?></h6>
                                                <small class="text-muted"><?= htmlspecialchars($deduction['applicable_to']) ?></small>
                                                <div class="text-danger fw-bold mt-2">
                                                    <?php if ($deduction['type'] === 'percentage'): ?>
                                                        <?= $deduction['value'] ?>% of Basic Salary
                                                    <?php elseif ($deduction['type'] === 'fixed'): ?>
                                                        ₹<?= number_format($deduction['value']) ?> per month
                                                    <?php else: ?>
                                                        Variable Amount
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="editDeduction(<?= $deduction['id'] ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteDeduction(<?= $deduction['id'] ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Salary Calculator Tab -->
                    <div class="tab-pane fade" id="calculator" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-lg-6">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-light border-0 py-2">
                                        <h6 class="mb-0 text-dark">Salary Calculator</h6>
                                    </div>
                                    <div class="card-body p-3">
                                        <form>
                                            <div class="mb-3">
                                                <label class="form-label small">Salary Grade</label>
                                                <select class="form-select form-select-sm" id="gradeSelect">
                                                    <option value="">Select Grade</option>
                                                    <?php foreach ($salary_grades as $grade): ?>
                                                    <option value="<?= $grade['id'] ?>"><?= $grade['grade'] ?> - <?= $grade['level'] ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small">Basic Salary (₹)</label>
                                                <input type="number" class="form-control form-control-sm" id="basicSalary" placeholder="Enter basic salary">
                                            </div>
                                            <button type="button" class="btn btn-primary btn-sm w-100" onclick="calculateSalary()">
                                                <i class="bi bi-calculator"></i> Calculate Total Salary
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-light border-0 py-2">
                                        <h6 class="mb-0 text-dark">Salary Breakdown</h6>
                                    </div>
                                    <div class="card-body p-3" id="salaryBreakdown">
                                        <div class="text-center text-muted py-4">
                                            <i class="bi bi-calculator text-muted" style="font-size: 2rem;"></i>
                                            <p class="mt-2 small">Enter basic salary to see breakdown</p>
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
        function calculateSalary() {
            const basicSalary = parseFloat(document.getElementById('basicSalary').value);
            if (!basicSalary) {
                showAlert('Please enter a valid basic salary', 'warning');
                return;
            }

            // Calculate allowances and deductions (simplified)
            const hra = basicSalary * 0.40; // 40%
            const transport = 2000;
            const meal = 1500;
            const medical = basicSalary * 0.10; // 10%
            
            const pf = basicSalary * 0.12; // 12%
            const pt = 200;
            const insurance = 500;
            
            const totalAllowances = hra + transport + meal + medical;
            const totalDeductions = pf + pt + insurance;
            const grossSalary = basicSalary + totalAllowances;
            const netSalary = grossSalary - totalDeductions;

            const breakdown = `
                <div class="salary-summary">
                    <div class="row mb-3">
                        <div class="col-6"><strong>Basic Salary:</strong></div>
                        <div class="col-6 text-end">₹${basicSalary.toLocaleString()}</div>
                    </div>
                    <hr>
                    <h6 class="text-success">Allowances:</h6>
                    <div class="row">
                        <div class="col-6">HRA (40%):</div>
                        <div class="col-6 text-end">₹${hra.toLocaleString()}</div>
                    </div>
                    <div class="row">
                        <div class="col-6">Transport:</div>
                        <div class="col-6 text-end">₹${transport.toLocaleString()}</div>
                    </div>
                    <div class="row">
                        <div class="col-6">Meal:</div>
                        <div class="col-6 text-end">₹${meal.toLocaleString()}</div>
                    </div>
                    <div class="row">
                        <div class="col-6">Medical (10%):</div>
                        <div class="col-6 text-end">₹${medical.toLocaleString()}</div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6"><strong>Total Allowances:</strong></div>
                        <div class="col-6 text-end"><strong class="text-success">₹${totalAllowances.toLocaleString()}</strong></div>
                    </div>
                    <hr>
                    <h6 class="text-danger">Deductions:</h6>
                    <div class="row">
                        <div class="col-6">PF (12%):</div>
                        <div class="col-6 text-end">₹${pf.toLocaleString()}</div>
                    </div>
                    <div class="row">
                        <div class="col-6">Professional Tax:</div>
                        <div class="col-6 text-end">₹${pt.toLocaleString()}</div>
                    </div>
                    <div class="row">
                        <div class="col-6">Insurance:</div>
                        <div class="col-6 text-end">₹${insurance.toLocaleString()}</div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6"><strong>Total Deductions:</strong></div>
                        <div class="col-6 text-end"><strong class="text-danger">₹${totalDeductions.toLocaleString()}</strong></div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-6"><strong>Gross Salary:</strong></div>
                        <div class="col-6 text-end"><strong>₹${grossSalary.toLocaleString()}</strong></div>
                    </div>
                    <div class="row">
                        <div class="col-6"><strong class="fs-5">Net Salary:</strong></div>
                        <div class="col-6 text-end"><strong class="fs-5 text-primary">₹${netSalary.toLocaleString()}</strong></div>
                    </div>
                </div>
            `;

            document.getElementById('salaryBreakdown').innerHTML = breakdown;
        }

        function editGrade(id) {
            showAlert('Edit grade functionality will be implemented soon!', 'info');
        }

        function viewGradeDetails(id) {
            showAlert('Viewing grade details...', 'info');
        }

        function showNewGradeModal() {
            showAlert('New grade creation modal will be implemented soon!', 'info');
        }

        function editAllowance(id) {
            showAlert('Edit allowance functionality will be implemented soon!', 'info');
        }

        function deleteAllowance(id) {
            if (confirm('Are you sure you want to delete this allowance?')) {
                showAlert('Allowance deleted successfully!', 'success');
            }
        }

        function showNewAllowanceModal() {
            showAlert('New allowance modal will be implemented soon!', 'info');
        }

        function editDeduction(id) {
            showAlert('Edit deduction functionality will be implemented soon!', 'info');
        }

        function deleteDeduction(id) {
            if (confirm('Are you sure you want to delete this deduction?')) {
                showAlert('Deduction deleted successfully!', 'success');
            }
        }

        function showNewDeductionModal() {
            showAlert('New deduction modal will be implemented soon!', 'info');
        }

        function exportSalaryData() {
            showAlert('Exporting salary structure data...', 'info');
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
require_once 'hrms_footer_simple.php'; 
<script>
// Standard modal functions for HRMS
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        new bootstrap.Modal(modal).show();
    }
}

function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        const modalInstance = bootstrap.Modal.getInstance(modal);
        if (modalInstance) modalInstance.hide();
    }
}

function loadRecord(id, modalId) {
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=get_record&id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Populate modal form fields
            Object.keys(data.data).forEach(key => {
                const field = document.getElementById(key) || document.querySelector('[name="' + key + '"]');
                if (field) {
                    field.value = data.data[key];
                }
            });
            showModal(modalId);
        } else {
            alert('Error loading record: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error occurred');
    });
}

function deleteRecord(id, confirmMessage = 'Are you sure you want to delete this record?') {
    if (!confirm(confirmMessage)) return;
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=delete_record&id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Record deleted successfully');
            location.reload();
        } else {
            alert('Error deleting record: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error occurred');
    });
}

function updateStatus(id, status) {
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=update_status&id=' + id + '&status=' + status
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Status updated successfully');
            location.reload();
        } else {
            alert('Error updating status: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error occurred');
    });
}

// Form submission with AJAX
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners to forms with class 'ajax-form'
    document.querySelectorAll('.ajax-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Operation completed successfully');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error occurred');
            });
        });
    });
});
</script>

require_once 'hrms_footer_simple.php';
?>