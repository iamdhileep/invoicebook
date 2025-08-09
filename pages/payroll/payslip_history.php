<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Payslip History';

// Get parameters
$employee_id = $_GET['employee_id'] ?? null;
$search = $_GET['search'] ?? '';

// Get employee details if specific employee selected
$selectedEmployee = null;
if ($employee_id) {
    $empQuery = $conn->prepare("SELECT * FROM employees WHERE employee_id = ?");
    $empQuery->bind_param("i", $employee_id);
    $empQuery->execute();
    $selectedEmployee = $empQuery->get_result()->fetch_assoc();
}

// Get payslip history (since we don't have a payslip_history table, we'll show available months from attendance)
$historyQuery = "
    SELECT 
        e.employee_id,
        e.name,
        e.employee_code,
        e.department_name,
        DATE_FORMAT(a.attendance_date, '%Y-%m') as month,
        COUNT(a.id) as attendance_days,
        e.monthly_salary
    FROM employees e
    LEFT JOIN attendance a ON e.employee_id = a.employee_id
    WHERE e.status = 'active'
";

$params = [];
$types = '';

if ($employee_id) {
    $historyQuery .= " AND e.employee_id = ?";
    $params[] = $employee_id;
    $types .= 'i';
}

if ($search) {
    $historyQuery .= " AND (e.name LIKE ? OR e.employee_code LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'ss';
}

$historyQuery .= "
    GROUP BY e.employee_id, e.name, e.employee_code, e.department_name, DATE_FORMAT(a.attendance_date, '%Y-%m'), e.monthly_salary
    HAVING month IS NOT NULL
    ORDER BY e.name ASC, month DESC
";

$stmt = $conn->prepare($historyQuery);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$payslipHistory = $stmt->get_result();

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
                            <i class="bi bi-clock-history text-primary"></i>
                            Payslip History
                            <?php if ($selectedEmployee): ?>
                                - <?= htmlspecialchars($selectedEmployee['name']) ?>
                            <?php endif; ?>
                        </h4>
                        <div>
                            <a href="bulk_payslips.php" class="btn btn-success me-2">
                                <i class="bi bi-file-earmark-spreadsheet"></i> Bulk Generate
                            </a>
                            <a href="payroll.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Payroll
                            </a>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <!-- Filters -->
                        <form method="GET" class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label for="employee_id" class="form-label">Employee</label>
                                <select class="form-control" id="employee_id" name="employee_id">
                                    <option value="">All Employees</option>
                                    <?php
                                    $empList = $conn->query("SELECT employee_id, name, employee_code FROM employees WHERE status = 'active' ORDER BY name");
                                    while ($emp = $empList->fetch_assoc()):
                                    ?>
                                        <option value="<?= $emp['employee_id'] ?>" <?= $employee_id == $emp['employee_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($emp['name']) ?> (<?= htmlspecialchars($emp['employee_code']) ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Name or Employee Code" value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Search
                                </button>
                                <a href="payslip_history.php" class="btn btn-outline-secondary ms-2">
                                    <i class="bi bi-x-circle"></i> Clear
                                </a>
                            </div>
                        </form>
                        
                        <!-- History Table -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Month</th>
                                        <th>Attendance Days</th>
                                        <th>Monthly Salary</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($payslipHistory->num_rows > 0): ?>
                                        <?php while ($history = $payslipHistory->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($history['name']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($history['employee_code']) ?></small>
                                            </td>
                                            <td><?= htmlspecialchars($history['department_name'] ?? 'N/A') ?></td>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?= date('M Y', strtotime($history['month'] . '-01')) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success"><?= $history['attendance_days'] ?> days</span>
                                            </td>
                                            <td>₹<?= number_format($history['monthly_salary'], 2) ?></td>
                                            <td>
                                                <a href="generate_payslip.php?employee_id=<?= $history['employee_id'] ?>&month=<?= $history['month'] ?>" 
                                                   class="btn btn-sm btn-primary me-1" target="_blank">
                                                    <i class="bi bi-file-text"></i> View
                                                </a>
                                                <a href="generate_payslip.php?employee_id=<?= $history['employee_id'] ?>&month=<?= $history['month'] ?>&print=1" 
                                                   class="btn btn-sm btn-success me-1" target="_blank">
                                                    <i class="bi bi-file-pdf"></i> PDF
                                                </a>
                                                <button onclick="sendPayslip(<?= $history['employee_id'] ?>, '<?= $history['month'] ?>')" 
                                                        class="btn btn-sm btn-info">
                                                    <i class="bi bi-envelope"></i> Email
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">
                                                <i class="bi bi-inbox"></i> No payslip history found
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function sendPayslip(employeeId, month) {
    if (confirm('Send payslip via email?')) {
        fetch('../payroll/send_payslip_email.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `employee_id=${employeeId}&month=${month}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`Email sent successfully to ${data.employee_name}`);
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Email functionality is ready for implementation.');
        });
    }
}
</script>

<?php include '../../layouts/footer.php'; ?>
