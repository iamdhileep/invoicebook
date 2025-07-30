<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Mark Attendance';

// Set timezone
date_default_timezone_set('Asia/Kolkata');
$today = date('Y-m-d');

// Get all employees with existing attendance data
$employees = $conn->query("SELECT employee_id, name, employee_code, position FROM employees ORDER BY name ASC");

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Mark Attendance</h1>
            <p class="text-muted">Record daily attendance with punch in/out times - <?= date('F j, Y') ?></p>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="live-clock">
                <i class="bi bi-clock me-2"></i>
                <strong>Live Time: <span id="liveClock"></span></strong>
            </div>
            <a href="../../attendance-calendar.php" class="btn btn-outline-primary">
                <i class="bi bi-calendar3"></i> View Calendar
            </a>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>Attendance saved successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Today's Attendance</h5>
                        <div>
                            <input type="date" id="attendanceDate" class="form-control form-control-sm" 
                                   value="<?= $today ?>" onchange="changeDate()">
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <form action="../../save_attendance.php" method="POST" id="attendanceForm">
                        <input type="hidden" name="attendance_date" id="hiddenDate" value="<?= $today ?>">
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Status</th>
                                        <th>Punch In</th>
                                        <th>Punch Out</th>
                                        <th>Quick Actions</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($employees && mysqli_num_rows($employees) > 0): ?>
                                        <?php while ($employee = $employees->fetch_assoc()): ?>
                                            <?php
                                            $empId = $employee['employee_id'];
                                            
                                            // Get existing attendance for today
                                            $attendanceQuery = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ? LIMIT 1");
                                            $attendanceQuery->bind_param("is", $empId, $today);
                                            $attendanceQuery->execute();
                                            $attendanceResult = $attendanceQuery->get_result();
                                            $existingAttendance = $attendanceResult->fetch_assoc();
                                            
                                            // Set default values
                                            $status = $existingAttendance['status'] ?? 'Absent';
                                            $timeIn = $existingAttendance['time_in'] ?? '';
                                            $timeOut = $existingAttendance['time_out'] ?? '';
                                            $notes = $existingAttendance['notes'] ?? '';
                                            
                                            // Calculate last punch time display
                                            $lastPunchTime = '';
                                            if (!empty($timeOut)) {
                                                $lastPunchTime = "Last Out: " . date("h:i A", strtotime($timeOut));
                                            } elseif (!empty($timeIn)) {
                                                $lastPunchTime = "Last In: " . date("h:i A", strtotime($timeIn));
                                            }
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" 
                                                             style="width: 35px; height: 35px;">
                                                            <i class="bi bi-person text-white"></i>
                                                        </div>
                                                        <div>
                                                            <strong><?= htmlspecialchars($employee['name']) ?></strong>
                                                            <br><small class="text-muted"><?= htmlspecialchars($employee['employee_code']) ?></small>
                                                            <?php if (!empty($lastPunchTime)): ?>
                                                                <br><small class="text-info"><?= $lastPunchTime ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <input type="hidden" name="employee_id[]" value="<?= $empId ?>">
                                                </td>
                                                <td>
                                                    <select name="status[<?= $empId ?>]" id="status-<?= $empId ?>" class="form-select form-select-sm" style="width: 120px;">
                                                        <option value="Present" <?= $status == 'Present' ? 'selected' : '' ?>>Present</option>
                                                        <option value="Absent" <?= $status == 'Absent' ? 'selected' : '' ?>>Absent</option>
                                                        <option value="Late" <?= $status == 'Late' ? 'selected' : '' ?>>Late</option>
                                                        <option value="Half Day" <?= $status == 'Half Day' ? 'selected' : '' ?>>Half Day</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="time" name="time_in[<?= $empId ?>]" id="time_in_<?= $empId ?>" 
                                                           class="form-control form-control-sm" style="width: 130px;" 
                                                           value="<?= $timeIn ?>">
                                                </td>
                                                <td>
                                                    <input type="time" name="time_out[<?= $empId ?>]" id="time_out_<?= $empId ?>" 
                                                           class="form-control form-control-sm" style="width: 130px;" 
                                                           value="<?= $timeOut ?>">
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button type="button" class="btn btn-success punch-btn" 
                                                                onclick="punchIn(<?= $empId ?>)" 
                                                                data-bs-toggle="tooltip" title="Punch In Now">
                                                            <i class="bi bi-box-arrow-in-right"></i> In
                                                        </button>
                                                        <button type="button" class="btn btn-danger punch-btn" 
                                                                onclick="punchOut(<?= $empId ?>)" 
                                                                data-bs-toggle="tooltip" title="Punch Out Now">
                                                            <i class="bi bi-box-arrow-left"></i> Out
                                                        </button>
                                                    </div>
                                                </td>
                                                <td>
                                                    <input type="text" name="notes[<?= $empId ?>]" class="form-control form-control-sm" 
                                                           placeholder="Optional notes..." style="width: 150px;"
                                                           value="<?= htmlspecialchars($notes) ?>">
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                <i class="bi bi-people fs-1 mb-3"></i>
                                                <h6>No employees found</h6>
                                                <p>Please <a href="../employees/employees.php">add employees</a> first to mark attendance.</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($employees && mysqli_num_rows($employees) > 0): ?>
                            <div class="mt-4 d-flex justify-content-between align-items-center">
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-outline-success" onclick="markAllPresent()">
                                        <i class="bi bi-check-all"></i> Mark All Present
                                    </button>
                                    <button type="button" class="btn btn-outline-warning" onclick="setDefaultTimes()">
                                        <i class="bi bi-clock"></i> Set Default Times
                                    </button>
                                    <button type="button" class="btn btn-outline-info" onclick="punchAllIn()">
                                        <i class="bi bi-box-arrow-in-right"></i> Punch All In
                                    </button>
                                </div>
                                
                                <div class="btn-group" role="group">
                                    <a href="../../attendance_preview.php" class="btn btn-secondary">
                                        <i class="bi bi-eye"></i> View All Records
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Save Attendance
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Today's Summary -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Today's Summary</h6>
                </div>
                <div class="card-body">
                    <?php
                    $totalEmployees = 0;
                    $presentCount = 0;
                    $absentCount = 0;
                    $lateCount = 0;
                    $halfDayCount = 0;

                    // Get total employees
                    $result = $conn->query("SELECT COUNT(*) as total FROM employees");
                    if ($result && $row = $result->fetch_assoc()) {
                        $totalEmployees = $row['total'];
                    }

                    // Get attendance counts for today
                    $attendanceStats = $conn->query("
                        SELECT 
                            COUNT(CASE WHEN status = 'Present' THEN 1 END) as present,
                            COUNT(CASE WHEN status = 'Absent' THEN 1 END) as absent,
                            COUNT(CASE WHEN status = 'Late' THEN 1 END) as late,
                            COUNT(CASE WHEN status = 'Half Day' THEN 1 END) as half_day
                        FROM attendance 
                        WHERE attendance_date = '$today'
                    ");
                    
                    if ($attendanceStats && $stats = $attendanceStats->fetch_assoc()) {
                        $presentCount = $stats['present'] ?? 0;
                        $absentCount = $stats['absent'] ?? 0;
                        $lateCount = $stats['late'] ?? 0;
                        $halfDayCount = $stats['half_day'] ?? 0;
                    }

                    $attendanceRate = $totalEmployees > 0 ? (($presentCount + $lateCount + $halfDayCount) / $totalEmployees) * 100 : 0;
                    ?>
                    
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="card bg-primary text-white text-center">
                                <div class="card-body p-2">
                                    <h5 class="mb-0"><?= $totalEmployees ?></h5>
                                    <small>Total</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card bg-success text-white text-center">
                                <div class="card-body p-2">
                                    <h5 class="mb-0"><?= $presentCount ?></h5>
                                    <small>Present</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card bg-danger text-white text-center">
                                <div class="card-body p-2">
                                    <h5 class="mb-0"><?= $absentCount ?></h5>
                                    <small>Absent</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card bg-warning text-dark text-center">
                                <div class="card-body p-2">
                                    <h5 class="mb-0"><?= $lateCount ?></h5>
                                    <small>Late</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <div class="d-flex justify-content-between">
                            <span>Attendance Rate:</span>
                            <strong class="<?= $attendanceRate >= 90 ? 'text-success' : ($attendanceRate >= 75 ? 'text-warning' : 'text-danger') ?>">
                                <?= number_format($attendanceRate, 1) ?>%
                            </strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-lightning me-2"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="../../attendance_preview.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-eye"></i> View All Records
                        </a>
                        <a href="../../attendance-calendar.php" class="btn btn-outline-info btn-sm">
                            <i class="bi bi-calendar3"></i> Attendance Calendar
                        </a>
                        <a href="../../payroll_report.php" class="btn btn-outline-success btn-sm">
                            <i class="bi bi-currency-rupee"></i> Payroll Report
                        </a>
                        <a href="../employees/employees.php" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-people"></i> Manage Employees
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    // Auto-refresh page every 5 minutes to keep data fresh
    setTimeout(() => {
        location.reload();
    }, 300000); // 5 minutes
});

// Live Clock Function
function updateLiveClock() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', {
        hour12: true,
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
    document.getElementById("liveClock").textContent = timeString;
}

// Start live clock
setInterval(updateLiveClock, 1000);
updateLiveClock();

// Get current time in HH:MM format
function getTimeNow() {
    const now = new Date();
    return now.toTimeString().split(' ')[0].substring(0, 5);
}

// Punch In function
function punchIn(employeeId) {
    const currentTime = getTimeNow();
    document.getElementById('time_in_' + employeeId).value = currentTime;
    document.getElementById('status-' + employeeId).value = 'Present';
    
    // Visual feedback
    showAlert('Punched In at ' + currentTime, 'success');
}

// Punch Out function
function punchOut(employeeId) {
    const currentTime = getTimeNow();
    document.getElementById('time_out_' + employeeId).value = currentTime;
    
    // Ensure status is Present if punching out
    const statusSelect = document.getElementById('status-' + employeeId);
    if (statusSelect.value === 'Absent') {
        statusSelect.value = 'Present';
    }
    
    // Visual feedback
    showAlert('Punched Out at ' + currentTime, 'info');
}

// Mark all employees as present
function markAllPresent() {
    const statusSelects = document.querySelectorAll('select[name^="status["]');
    statusSelects.forEach(select => {
        select.value = 'Present';
    });
    
    showAlert('All employees marked as Present', 'success');
}

// Set default times for all employees
function setDefaultTimes() {
    const timeInInputs = document.querySelectorAll('input[name^="time_in["]');
    const timeOutInputs = document.querySelectorAll('input[name^="time_out["]');
    
    timeInInputs.forEach(input => {
        if (!input.value) {
            input.value = '09:00';
        }
    });
    
    timeOutInputs.forEach(input => {
        if (!input.value) {
            input.value = '18:00';
        }
    });
    
    showAlert('Default times set (9:00 AM - 6:00 PM)', 'info');
}

// Punch all employees in at current time
function punchAllIn() {
    const currentTime = getTimeNow();
    const timeInInputs = document.querySelectorAll('input[name^="time_in["]');
    const statusSelects = document.querySelectorAll('select[name^="status["]');
    
    timeInInputs.forEach(input => {
        input.value = currentTime;
    });
    
    statusSelects.forEach(select => {
        select.value = 'Present';
    });
    
    showAlert('All employees punched in at ' + currentTime, 'success');
}

// Change date function
function changeDate() {
    const selectedDate = document.getElementById('attendanceDate').value;
    document.getElementById('hiddenDate').value = selectedDate;
    
    // Reload page with new date
    window.location.href = window.location.pathname + '?date=' + selectedDate;
}

// Show alert function
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        <i class="bi bi-check-circle me-2"></i>${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.parentNode.removeChild(alertDiv);
        }
    }, 3000);
}
</script>

<style>
.live-clock {
    color: #0d6efd;
    font-size: 1.1rem;
    padding: 8px 12px;
    background: rgba(13, 110, 253, 0.1);
    border-radius: 6px;
    border: 1px solid rgba(13, 110, 253, 0.2);
}

.punch-btn {
    font-size: 0.8rem;
    padding: 4px 8px;
    min-width: 45px;
}

.table th {
    white-space: nowrap;
    font-weight: 600;
    background-color: #f8f9fa;
}

.table td {
    vertical-align: middle;
}

.btn-group .btn {
    border-radius: 0.375rem !important;
}

.btn-group .btn:not(:last-child) {
    margin-right: 2px;
}
</style>

<?php include '../../layouts/footer.php'; ?>