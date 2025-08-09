<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Bulk Payslip Generation';

// Get parameters
$month = $_GET['month'] ?? date('Y-m');
$department = $_GET['department'] ?? '';

// Parse month
$monthParts = explode('-', $month);
$year = $monthParts[0];
$monthNum = $monthParts[1];

// Get departments for filter
$departments = $conn->query("SELECT DISTINCT department_name FROM employees WHERE status = 'active' ORDER BY department_name");

// Get employees based on filter
$employeeQuery = "SELECT employee_id, name, employee_code, department_name, monthly_salary FROM employees WHERE status = 'active'";
$params = [];
$types = '';

if ($department) {
    $employeeQuery .= " AND department_name = ?";
    $params[] = $department;
    $types .= 's';
}

$employeeQuery .= " ORDER BY name ASC";

$stmt = $conn->prepare($employeeQuery);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$employees = $stmt->get_result();

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="bi bi-file-earmark-spreadsheet text-primary"></i>
                            Bulk Payslip Generation
                        </h4>
                        <a href="payroll.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Payroll
                        </a>
                    </div>
                    
                    <div class="card-body">
                        <!-- Filters -->
                        <form method="GET" class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label for="month" class="form-label">Month</label>
                                <input type="month" class="form-control" id="month" name="month" value="<?= htmlspecialchars($month) ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="department" class="form-label">Department</label>
                                <select class="form-control" id="department" name="department">
                                    <option value="">All Departments</option>
                                    <?php while ($dept = $departments->fetch_assoc()): ?>
                                        <option value="<?= htmlspecialchars($dept['department_name']) ?>" <?= $department === $dept['department_name'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($dept['department_name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="bi bi-search"></i> Filter
                                </button>
                                <button type="button" class="btn btn-success" onclick="generateAllPayslips()">
                                    <i class="bi bi-file-earmark-pdf"></i> Generate All PDFs
                                </button>
                            </div>
                        </form>
                        
                        <!-- Employee List -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>
                                            <input type="checkbox" id="selectAll" onclick="toggleAllCheckboxes()">
                                        </th>
                                        <th>Employee ID</th>
                                        <th>Name</th>
                                        <th>Department</th>
                                        <th>Monthly Salary</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($emp = $employees->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="employee-checkbox" value="<?= $emp['employee_id'] ?>">
                                        </td>
                                        <td><?= htmlspecialchars($emp['employee_code']) ?></td>
                                        <td><?= htmlspecialchars($emp['name']) ?></td>
                                        <td><?= htmlspecialchars($emp['department_name'] ?? 'N/A') ?></td>
                                        <td>₹<?= number_format($emp['monthly_salary'], 2) ?></td>
                                        <td>
                                            <a href="generate_payslip.php?employee_id=<?= $emp['employee_id'] ?>&month=<?= $month ?>" 
                                               class="btn btn-sm btn-primary" target="_blank">
                                                <i class="bi bi-file-text"></i> View
                                            </a>
                                            <a href="generate_payslip.php?employee_id=<?= $emp['employee_id'] ?>&month=<?= $month ?>&print=1" 
                                               class="btn btn-sm btn-success" target="_blank">
                                                <i class="bi bi-file-pdf"></i> PDF
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Bulk Actions -->
                        <div class="mt-3">
                            <button type="button" class="btn btn-warning" onclick="generateSelectedPayslips()">
                                <i class="bi bi-file-earmark-pdf"></i> Generate Selected PDFs
                            </button>
                            <button type="button" class="btn btn-info" onclick="sendSelectedEmails()">
                                <i class="bi bi-envelope"></i> Send Selected Emails
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleAllCheckboxes() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.employee-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
}

function getSelectedEmployees() {
    const checkboxes = document.querySelectorAll('.employee-checkbox:checked');
    return Array.from(checkboxes).map(cb => cb.value);
}

function generateAllPayslips() {
    const month = document.getElementById('month').value;
    const department = document.getElementById('department').value;
    
    if (confirm('Generate payslips for all employees in the current filter?')) {
        // Get all employee checkboxes
        const checkboxes = document.querySelectorAll('.employee-checkbox');
        checkboxes.forEach(cb => cb.checked = true);
        generateSelectedPayslips();
    }
}

function generateSelectedPayslips() {
    const selectedEmployees = getSelectedEmployees();
    const month = document.getElementById('month').value;
    
    if (selectedEmployees.length === 0) {
        alert('Please select at least one employee');
        return;
    }
    
    if (confirm(`Generate payslips for ${selectedEmployees.length} selected employees?`)) {
        // Open each payslip in a new tab for PDF generation
        selectedEmployees.forEach((empId, index) => {
            setTimeout(() => {
                window.open(`generate_payslip.php?employee_id=${empId}&month=${month}&print=1`, '_blank');
            }, index * 1000); // Stagger by 1 second to avoid browser blocking
        });
        
        alert(`Opening ${selectedEmployees.length} payslip PDFs. Please allow popups for this site.`);
    }
}

function sendSelectedEmails() {
    const selectedEmployees = getSelectedEmployees();
    const month = document.getElementById('month').value;
    
    if (selectedEmployees.length === 0) {
        alert('Please select at least one employee');
        return;
    }
    
    alert(`Email functionality for ${selectedEmployees.length} employees will be implemented soon.`);
    // TODO: Implement AJAX call to send emails
}
</script>

<?php include '../../layouts/footer.php'; ?>
