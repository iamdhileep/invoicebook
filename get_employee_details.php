<?php
session_start();
if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo "Unauthorized";
    exit;
}

include 'db.php';

$employeeId = $_GET['id'] ?? null;

if (!$employeeId) {
    http_response_code(400);
    echo "Employee ID is required";
    exit;
}

// Get employee details
$query = "SELECT * FROM employees WHERE employee_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $employeeId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo "Employee not found";
    exit;
}

$employee = $result->fetch_assoc();

// Get attendance statistics for current month
$currentMonth = date('m');
$currentYear = date('Y');

$attendanceQuery = "
    SELECT 
        COUNT(CASE WHEN status = 'Present' THEN 1 END) as present_days,
        COUNT(CASE WHEN status = 'Absent' THEN 1 END) as absent_days,
        COUNT(CASE WHEN status = 'Late' THEN 1 END) as late_days,
        COUNT(CASE WHEN status = 'Half Day' THEN 1 END) as half_days,
        COUNT(*) as total_records
    FROM attendance 
    WHERE employee_id = ? 
    AND MONTH(attendance_date) = ? 
    AND YEAR(attendance_date) = ?
";

$attendanceStmt = $conn->prepare($attendanceQuery);
$attendanceStmt->bind_param("iii", $employeeId, $currentMonth, $currentYear);
$attendanceStmt->execute();
$attendanceResult = $attendanceStmt->get_result();
$attendance = $attendanceResult->fetch_assoc();

// Calculate attendance percentage
$totalWorkingDays = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
$presentDays = ($attendance['present_days'] ?? 0) + ($attendance['late_days'] ?? 0) + (($attendance['half_days'] ?? 0) * 0.5);
$attendancePercentage = $totalWorkingDays > 0 ? ($presentDays / $totalWorkingDays) * 100 : 0;

// Get recent attendance records
$recentAttendanceQuery = "
    SELECT attendance_date, status, time_in, time_out 
    FROM attendance 
    WHERE employee_id = ? 
    ORDER BY attendance_date DESC 
    LIMIT 10
";

$recentStmt = $conn->prepare($recentAttendanceQuery);
$recentStmt->bind_param("i", $employeeId);
$recentStmt->execute();
$recentAttendance = $recentStmt->get_result();
?>

<div class="employee-details">
    <!-- Employee Header -->
    <div class="row mb-4">
        <div class="col-md-3 text-center">
            <?php if (!empty($employee['photo']) && file_exists($employee['photo'])): ?>
                <img src="<?= htmlspecialchars($employee['photo']) ?>" 
                     class="rounded-circle img-fluid mb-3" 
                     style="width: 120px; height: 120px; object-fit: cover;">
            <?php else: ?>
                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" 
                     style="width: 120px; height: 120px;">
                    <i class="bi bi-person text-white" style="font-size: 3rem;"></i>
                </div>
            <?php endif; ?>
            
            <div class="badge bg-<?= $attendancePercentage >= 90 ? 'success' : ($attendancePercentage >= 75 ? 'warning' : 'danger') ?> fs-6">
                <?= number_format($attendancePercentage, 1) ?>% Attendance
            </div>
        </div>
        
        <div class="col-md-9">
            <h4 class="mb-1"><?= htmlspecialchars($employee['name']) ?></h4>
            <p class="text-muted mb-2"><?= htmlspecialchars($employee['position']) ?></p>
            
            <div class="row g-3">
                <div class="col-sm-6">
                    <div class="border rounded p-2">
                        <small class="text-muted">Employee Code</small>
                        <div class="fw-bold"><?= htmlspecialchars($employee['employee_code']) ?></div>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="border rounded p-2">
                        <small class="text-muted">Monthly Salary</small>
                        <div class="fw-bold text-success">₹<?= number_format($employee['monthly_salary'], 2) ?></div>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="border rounded p-2">
                        <small class="text-muted">Phone</small>
                        <div class="fw-bold"><?= htmlspecialchars($employee['phone']) ?></div>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="border rounded p-2">
                        <small class="text-muted">Email</small>
                        <div class="fw-bold"><?= htmlspecialchars($employee['email'] ?? 'N/A') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Summary for Current Month -->
    <div class="row g-3 mb-4">
        <div class="col-md-12">
            <h6 class="mb-3">Attendance Summary - <?= date('F Y') ?></h6>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white text-center">
                <div class="card-body p-2">
                    <h4 class="mb-0"><?= $attendance['present_days'] ?? 0 ?></h4>
                    <small>Present Days</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white text-center">
                <div class="card-body p-2">
                    <h4 class="mb-0"><?= $attendance['absent_days'] ?? 0 ?></h4>
                    <small>Absent Days</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark text-center">
                <div class="card-body p-2">
                    <h4 class="mb-0"><?= $attendance['late_days'] ?? 0 ?></h4>
                    <small>Late Days</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white text-center">
                <div class="card-body p-2">
                    <h4 class="mb-0"><?= $attendance['half_days'] ?? 0 ?></h4>
                    <small>Half Days</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Personal Information -->
    <div class="row mb-4">
        <div class="col-md-12">
            <h6 class="mb-3">Personal Information</h6>
            <div class="table-responsive">
                <table class="table table-sm">
                    <tr>
                        <td><strong>Full Name:</strong></td>
                        <td><?= htmlspecialchars($employee['name']) ?></td>
                        <td><strong>Employee Code:</strong></td>
                        <td><?= htmlspecialchars($employee['employee_code']) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Position:</strong></td>
                        <td><?= htmlspecialchars($employee['position']) ?></td>
                        <td><strong>Phone:</strong></td>
                        <td><?= htmlspecialchars($employee['phone']) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Email:</strong></td>
                        <td><?= htmlspecialchars($employee['email'] ?? 'N/A') ?></td>
                        <td><strong>Monthly Salary:</strong></td>
                        <td class="text-success"><strong>₹<?= number_format($employee['monthly_salary'], 2) ?></strong></td>
                    </tr>
                    <tr>
                        <td><strong>Address:</strong></td>
                        <td colspan="3"><?= htmlspecialchars($employee['address'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <td><strong>Joining Date:</strong></td>
                        <td><?= $employee['created_at'] ? date('F j, Y', strtotime($employee['created_at'])) : 'N/A' ?></td>
                        <td><strong>Employee ID:</strong></td>
                        <td><?= $employee['employee_id'] ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Attendance -->
    <div class="row">
        <div class="col-md-12">
            <h6 class="mb-3">Recent Attendance</h6>
            <?php if ($recentAttendance->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($record = $recentAttendance->fetch_assoc()): ?>
                                <?php
                                $statusClass = '';
                                switch($record['status']) {
                                    case 'Present': $statusClass = 'success'; break;
                                    case 'Absent': $statusClass = 'danger'; break;
                                    case 'Late': $statusClass = 'warning'; break;
                                    case 'Half Day': $statusClass = 'info'; break;
                                }
                                
                                $duration = '';
                                if ($record['time_in'] && $record['time_out']) {
                                    $timeIn = new DateTime($record['time_in']);
                                    $timeOut = new DateTime($record['time_out']);
                                    $diff = $timeIn->diff($timeOut);
                                    $duration = $diff->format('%h:%I');
                                }
                                ?>
                                <tr>
                                    <td><?= date('M j, Y', strtotime($record['attendance_date'])) ?></td>
                                    <td><span class="badge bg-<?= $statusClass ?>"><?= $record['status'] ?></span></td>
                                    <td><?= $record['time_in'] ? date('h:i A', strtotime($record['time_in'])) : '-' ?></td>
                                    <td><?= $record['time_out'] ? date('h:i A', strtotime($record['time_out'])) : '-' ?></td>
                                    <td><?= $duration ?: '-' ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-3 text-muted">
                    <i class="bi bi-calendar-x fs-1 mb-2"></i>
                    <p>No attendance records found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="row mt-4">
        <div class="col-md-12">
            <h6 class="mb-3">Quick Actions</h6>
            
            <!-- Primary Actions -->
            <div class="d-flex gap-2 justify-content-center mb-3 flex-wrap">
                <button type="button" class="btn btn-primary" 
                        onclick="openEditEmployee(<?= $employee['employee_id'] ?>)">
                    <i class="bi bi-pencil me-2"></i> Edit Employee
                </button>
                <button type="button" class="btn btn-info" 
                        onclick="openAttendancePreview(<?= $employee['employee_id'] ?>)">
                    <i class="bi bi-calendar-check me-2"></i> View Attendance
                </button>
                <button type="button" class="btn btn-success" 
                        onclick="openPayrollReport(<?= $employee['employee_id'] ?>)">
                    <i class="bi bi-currency-rupee me-2"></i> Payroll Details
                </button>
            </div>
            
            <!-- Secondary Actions -->
            <div class="d-flex gap-2 justify-content-center flex-wrap">
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-primary btn-sm" 
                            onclick="openEditEmployeeNewTab(<?= $employee['employee_id'] ?>)">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Edit (New Tab)
                    </button>
                    <button type="button" class="btn btn-outline-info btn-sm" 
                            onclick="openAttendancePreviewNewTab(<?= $employee['employee_id'] ?>)">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Attendance (New Tab)
                    </button>
                    <button type="button" class="btn btn-outline-success btn-sm" 
                            onclick="openPayrollReportNewTab(<?= $employee['employee_id'] ?>)">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Payroll (New Tab)
                    </button>
                </div>
                
                <button type="button" class="btn btn-outline-danger btn-sm" 
                        onclick="confirmDeleteEmployee(<?= $employee['employee_id'] ?>)">
                    <i class="bi bi-trash me-1"></i> Delete Employee
                </button>
            </div>
            
            <hr class="my-3">
            <div class="text-center">
                <small class="text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    Click main buttons to navigate in current tab, or use "New Tab" buttons to keep this modal open
                </small>
            </div>
        </div>
    </div>
</div>

<style>
.employee-details .table td {
    padding: 0.5rem;
    border-top: 1px solid #dee2e6;
}

.employee-details .card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.employee-details .border {
    border-color: #dee2e6 !important;
}
</style>

<script>
// Detect if we're in a subdirectory and adjust paths accordingly
function getBasePath() {
    const currentPath = window.location.pathname;
    if (currentPath.includes('/pages/')) {
        return '../../';
    }
    return './';
}

// Function to open edit employee page
function openEditEmployee(employeeId) {
    // Show loading state
    const button = event.target.closest('button');
    const originalContent = button.innerHTML;
    button.innerHTML = '<i class="bi bi-hourglass-split me-2"></i> Loading...';
    button.disabled = true;
    
    // Close the modal first
    const modal = bootstrap.Modal.getInstance(document.getElementById('employeeModal'));
    if (modal) {
        modal.hide();
    }
    
    // Navigate to edit page after modal closes
    setTimeout(() => {
        window.location.href = getBasePath() + 'edit_employee.php?id=' + employeeId;
    }, 300);
}

// Function to open attendance preview
function openAttendancePreview(employeeId) {
    // Show loading state
    const button = event.target.closest('button');
    const originalContent = button.innerHTML;
    button.innerHTML = '<i class="bi bi-hourglass-split me-2"></i> Loading...';
    button.disabled = true;
    
    // Close the modal first
    const modal = bootstrap.Modal.getInstance(document.getElementById('employeeModal'));
    if (modal) {
        modal.hide();
    }
    
    // Navigate to attendance preview after modal closes
    setTimeout(() => {
        window.location.href = getBasePath() + 'attendance_preview.php?employee_id=' + employeeId;
    }, 300);
}

// Function to open payroll report
function openPayrollReport(employeeId) {
    // Show loading state
    const button = event.target.closest('button');
    const originalContent = button.innerHTML;
    button.innerHTML = '<i class="bi bi-hourglass-split me-2"></i> Loading...';
    button.disabled = true;
    
    // Close the modal first
    const modal = bootstrap.Modal.getInstance(document.getElementById('employeeModal'));
    if (modal) {
        modal.hide();
    }
    
    // Navigate to payroll report after modal closes
    setTimeout(() => {
        window.location.href = getBasePath() + 'payroll_report.php?employee_id=' + employeeId;
    }, 300);
}

// Function to confirm employee deletion
function confirmDeleteEmployee(employeeId) {
    if (confirm('Are you sure you want to delete this employee? This action cannot be undone.')) {
        // Close the modal first
        const modal = bootstrap.Modal.getInstance(document.getElementById('employeeModal'));
        if (modal) {
            modal.hide();
        }
        
        // Navigate to delete after modal closes
        setTimeout(() => {
            window.location.href = getBasePath() + 'delete_employee.php?id=' + employeeId;
        }, 300);
    }
}

// Alternative functions for opening in new tab (if preferred)
function openEditEmployeeNewTab(employeeId) {
    window.open(getBasePath() + 'edit_employee.php?id=' + employeeId, '_blank');
}

function openAttendancePreviewNewTab(employeeId) {
    window.open(getBasePath() + 'attendance_preview.php?employee_id=' + employeeId, '_blank');
}

function openPayrollReportNewTab(employeeId) {
    window.open(getBasePath() + 'payroll_report.php?employee_id=' + employeeId, '_blank');
}
</script>