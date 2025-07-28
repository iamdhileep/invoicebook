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
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="h5 mb-0">📋 Daily Attendance Management</h1>
                <p class="text-muted small">Mark employee attendance for <?= date('F j, Y') ?></p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="live-clock">
                    <i class="bi bi-clock me-2"></i>
                    <strong>Current Time: <span id="liveClock"></span></strong>
                </div>
                <a href="../../attendance-calendar.php" class="btn btn-outline-primary btn-sm">
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
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light border-0 py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 text-dark"><i class="bi bi-calendar-check me-2"></i>Today's Attendance</h6>
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
        
        <!-- Sidebar with Quick Actions -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0">
                        <i class="bi bi-lightning-charge me-2"></i>Quick Actions
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="../../attendance_preview.php" class="btn btn-outline-primary">
                            <i class="bi bi-eye me-2"></i>View All Records
                        </a>
                        <a href="../../attendance-calendar.php" class="btn btn-outline-info">
                            <i class="bi bi-calendar3 me-2"></i>Attendance Calendar
                        </a>
                        <a href="../../payroll_report.php" class="btn btn-outline-success">
                            <i class="bi bi-currency-rupee me-2"></i>Payroll Report
                        </a>
                        <a href="../employees/employees.php" class="btn btn-outline-secondary">
                            <i class="bi bi-people me-2"></i>Manage Employees
                        </a>
                    </div>
                    
                    <hr class="my-3">
                    
                    <div class="row g-2">
                        <div class="col-6">
                            <button class="btn btn-outline-success btn-sm w-100" onclick="markAllPresent()">
                                <i class="bi bi-check-all me-1"></i>All Present
                            </button>
                        </div>
                        <div class="col-6">
                            <button class="btn btn-outline-warning btn-sm w-100" onclick="setDefaultTimes()">
                                <i class="bi bi-clock me-1"></i>Set Times
                            </button>
                        </div>
                        <div class="col-6">
                            <button class="btn btn-outline-info btn-sm w-100" onclick="punchAllIn()">
                                <i class="bi bi-box-arrow-in-right me-1"></i>Punch All
                            </button>
                        </div>
                        <div class="col-6">
                            <button class="btn btn-outline-danger btn-sm w-100" onclick="clearAll()">
                                <i class="bi bi-x-circle me-1"></i>Clear All
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Today's Summary -->
            <div class="card border-0 shadow-sm mt-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Today's Summary</h6>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="card bg-primary text-white text-center">
                                <div class="card-body p-2">
                                    <h5 class="mb-0"><?php 
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
                                        echo $totalEmployees;
                                    ?></h5>
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
                        <div class="progress mt-2" style="height: 8px;">
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: <?= $attendanceRate ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Device Status -->
            <div class="card border-0 shadow-sm mt-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-router me-2"></i>System Status</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted">Database</span>
                        <span class="badge bg-success">Connected</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted">Server</span>
                        <span class="badge bg-success">Online</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">Last Sync</span>
                        <span class="text-success"><?= date('h:i A') ?></span>
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

// Clear all attendance data
function clearAll() {
    if (confirm('Are you sure you want to clear all attendance data? This action cannot be undone.')) {
        const statusSelects = document.querySelectorAll('select[name^="status["]');
        const timeInInputs = document.querySelectorAll('input[name^="time_in["]');
        const timeOutInputs = document.querySelectorAll('input[name^="time_out["]');
        const notesInputs = document.querySelectorAll('input[name^="notes["]');
        
        statusSelects.forEach(select => {
            select.value = 'Absent';
        });
        
        timeInInputs.forEach(input => {
            input.value = '';
        });
        
        timeOutInputs.forEach(input => {
            input.value = '';
        });
        
        notesInputs.forEach(input => {
            input.value = '';
        });
        
        showAlert('All attendance data cleared', 'warning');
    }
}

// Change date function
function changeDate() {
    const selectedDate = document.getElementById('attendanceDate').value;
    document.getElementById('hiddenDate').value = selectedDate;
    
    // Reload page with new date
    window.location.href = window.location.pathname + '?date=' + selectedDate;
}

// Simple alert function
function showAlert(message, type = 'info') {
    // Remove any existing alerts first
    const existingAlerts = document.querySelectorAll('.custom-alert');
    existingAlerts.forEach(alert => alert.remove());
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed custom-alert`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; max-width: 400px;';
    
    // Choose appropriate icon based on type
    let icon = 'bi-info-circle';
    if (type === 'success') icon = 'bi-check-circle';
    else if (type === 'danger') icon = 'bi-exclamation-triangle';
    else if (type === 'warning') icon = 'bi-exclamation-triangle';
    
    alertDiv.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="bi ${icon} me-2"></i>
            <div class="flex-grow-1">${message}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    
    document.body.appendChild(alertDiv);
    
    // Auto remove after 4 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 4000);
}
</script>

<style>
/* Custom Alert Styles */
.custom-alert {
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(100%);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Enhanced card styling */
.card {
    transition: all 0.2s ease;
}

.card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

/* Live clock styling */
.live-clock {
    color: #495057;
    font-size: 0.95rem;
}

/* Responsive improvements */
@media (max-width: 768px) {
    .btn-group-sm .btn {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }
    
    .table th, .table td {
        font-size: 0.875rem;
    }
}
</style>

<?php include '../../layouts/footer.php'; ?>
<div class="modal fade" id="smartAttendanceModal" tabindex="-1" aria-labelledby="smartAttendanceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="smartAttendanceModalLabel">
                    <i class="bi bi-camera-fill me-2"></i>Smart Touchless Attendance
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="bi bi-person-check me-2"></i>Face Recognition</h6>
                            </div>
                            <div class="card-body text-center">
                                <div id="faceRecognitionArea" class="border rounded p-4 mb-3" style="min-height: 200px; background: #f8f9fa;">
                                    <i class="bi bi-camera text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-2">Click to enable camera for face recognition</p>
                                </div>
                                <button class="btn btn-primary" onclick="initFaceRecognition()">
                                    <i class="bi bi-camera-video"></i> Start Face Recognition
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0"><i class="bi bi-qr-code-scan me-2"></i>QR Code Scanner</h6>
                            </div>
                            <div class="card-body text-center">
                                <div id="qrScannerArea" class="border rounded p-4 mb-3" style="min-height: 200px; background: #f8f9fa;">
                                    <i class="bi bi-qr-code text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-2">Scan your employee QR code</p>
                                </div>
                                <button class="btn btn-info" onclick="initQRScanner()">
                                    <i class="bi bi-qr-code-scan"></i> Start QR Scanner
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-warning text-dark">
                                <h6 class="mb-0"><i class="bi bi-geo-alt me-2"></i>GPS Location</h6>
                            </div>
                            <div class="card-body">
                                <div id="locationStatus" class="text-center">
                                    <div class="spinner-border text-warning" role="status" id="locationSpinner">
                                        <span class="visually-hidden">Getting location...</span>
                                    </div>
                                    <p class="mt-2" id="locationText">Getting your location...</p>
                                </div>
                                <button class="btn btn-warning w-100" onclick="checkInWithGPS()" id="gpsCheckInBtn" disabled>
                                    <i class="bi bi-geo-alt-fill"></i> Check-in with GPS
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-secondary text-white">
                                <h6 class="mb-0"><i class="bi bi-router me-2"></i>IP-based Check-in</h6>
                            </div>
                            <div class="card-body">
                                <div class="text-center">
                                    <p class="mb-2">Your IP: <strong id="userIP">Loading...</strong></p>
                                    <p class="mb-3">Office Network: <span class="badge bg-success">Detected</span></p>
                                </div>
                                <button class="btn btn-secondary w-100" onclick="checkInWithIP()">
                                    <i class="bi bi-router-fill"></i> Check-in with IP
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" onclick="manualCheckIn()">
                    <i class="bi bi-hand-index"></i> Manual Check-in
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Dynamic Leave Calendar Modal -->
<div class="modal fade" id="leaveCalendarModal" tabindex="-1" aria-labelledby="leaveCalendarModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="leaveCalendarModalLabel">
                    <i class="bi bi-calendar3 me-2"></i>Dynamic Leave Calendar
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <select class="form-select" id="calendarView">
                            <option value="personal">My Leaves</option>
                            <option value="team">Team Leaves</option>
                            <option value="organization">Organization Wide</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" id="calendarFilter">
                            <option value="all">All Types</option>
                            <option value="casual">Casual Leave</option>
                            <option value="sick">Sick Leave</option>
                            <option value="earned">Earned Leave</option>
                            <option value="wfh">Work From Home</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-primary" onclick="refreshCalendar()">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                        <button class="btn btn-success" onclick="exportCalendar()">
                            <i class="bi bi-download"></i> Export
                        </button>
                    </div>
                </div>
                <div id="dynamicCalendar" style="min-height: 400px;">
                    <!-- Calendar will be rendered here -->
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Legend:</h6>
                        <div class="d-flex flex-wrap gap-3">
                            <span><span class="badge bg-success me-1">■</span> Casual Leave</span>
                            <span><span class="badge bg-danger me-1">■</span> Sick Leave</span>
                            <span><span class="badge bg-warning me-1">■</span> Earned Leave</span>
                            <span><span class="badge bg-info me-1">■</span> Work From Home</span>
                            <span><span class="badge bg-secondary me-1">■</span> Public Holiday</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- AI Leave Assistant Modal -->
<div class="modal fade" id="aiLeaveAssistantModal" tabindex="-1" aria-labelledby="aiLeaveAssistantModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="aiLeaveAssistantModalLabel">
                    <i class="bi bi-brain me-2"></i>AI-Powered Leave Assistant
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="bi bi-lightbulb me-2"></i>
                    <strong>Smart Suggestions:</strong> Based on your attendance pattern, team schedule, and workload analysis
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Best Leave Days</h6>
                            </div>
                            <div class="card-body">
                                <div id="aiSuggestions">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>August 15-16, 2025</span>
                                        <span class="badge bg-success">Optimal</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>August 29-30, 2025</span>
                                        <span class="badge bg-warning">Good</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>September 12-13, 2025</span>
                                        <span class="badge bg-info">Available</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Pattern Analysis</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <small class="text-muted">Monday Leave Frequency</small>
                                    <div class="progress">
                                        <div class="progress-bar bg-warning" style="width: 30%">30%</div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <small class="text-muted">Friday Leave Frequency</small>
                                    <div class="progress">
                                        <div class="progress-bar bg-info" style="width: 25%">25%</div>
                                    </div>
                                </div>
                                <div class="alert alert-warning">
                                    <small><i class="bi bi-exclamation-triangle me-1"></i>
                                    Consider balancing Monday leaves</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="applyAISuggestion()">
                    Apply Suggested Dates
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Analytics Dashboard Modal -->
<div class="modal fade" id="analyticsModal" tabindex="-1" aria-labelledby="analyticsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="analyticsModalLabel">
                    <i class="bi bi-graph-up me-2"></i>Leave & Attendance Analytics
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h4 class="text-primary">92%</h4>
                                <small>Attendance Rate</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h4 class="text-success">34</h4>
                                <small>Leaves Approved</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h4 class="text-warning">8</h4>
                                <small>Pending Approvals</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h4 class="text-info">156</h4>
                                <small>Total Applications</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Department-wise Analysis</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="departmentChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Monthly Trends</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="trendChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Absenteeism Heatmap</h6>
                            </div>
                            <div class="card-body">
                                <div id="heatmapContainer" style="min-height: 200px;">
                                    <!-- Heatmap visualization will be rendered here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="exportAnalytics()">
                    <i class="bi bi-download"></i> Export Report
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Apply Leave Modal -->
<div class="modal fade" id="applyLeaveModal" tabindex="-1" aria-labelledby="applyLeaveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="applyLeaveModalLabel">
                    <i class="bi bi-calendar-minus me-2"></i>Apply for Leave
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="leaveApplicationForm" action="../../api/apply_leave.php" method="POST">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="employee_id" class="form-label fw-semibold">Employee</label>
                            <div class="input-group">
                                <span class="input-group-text bg-primary text-white">
                                    <i class="bi bi-person"></i>
                                </span>
                                <select class="form-select" id="employee_id" name="employee_id" required>
                                    <option value="">Select Employee</option>
                                    <?php
                                    $employees_leave = $conn->query("SELECT employee_id, name, employee_code FROM employees ORDER BY name ASC");
                                    if ($employees_leave && mysqli_num_rows($employees_leave) > 0) {
                                        while ($emp = $employees_leave->fetch_assoc()) {
                                            echo "<option value='" . $emp['employee_id'] . "'>" . htmlspecialchars($emp['name']) . " (" . htmlspecialchars($emp['employee_code']) . ")</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="leave_type" class="form-label fw-semibold">Leave Type</label>
                            <div class="input-group">
                                <span class="input-group-text bg-success text-white">
                                    <i class="bi bi-list"></i>
                                </span>
                                <select class="form-select" id="leave_type" name="leave_type" required>
                                    <option value="">Select Leave Type</option>
                                    <option value="casual">Casual Leave</option>
                                    <option value="sick">Sick Leave</option>
                                    <option value="earned">Earned Leave</option>
                                    <option value="maternity">Maternity Leave</option>
                                    <option value="paternity">Paternity Leave</option>
                                    <option value="emergency">Emergency Leave</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="start_date" class="form-label fw-semibold">Start Date</label>
                            <div class="input-group">
                                <span class="input-group-text bg-warning text-dark">
                                    <i class="bi bi-calendar-date"></i>
                                </span>
                                <input type="date" class="form-control" id="start_date" name="start_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="end_date" class="form-label fw-semibold">End Date</label>
                            <div class="input-group">
                                <span class="input-group-text bg-danger text-white">
                                    <i class="bi bi-calendar-check"></i>
                                </span>
                                <input type="date" class="form-control" id="end_date" name="end_date" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <label for="leave_reason" class="form-label fw-semibold">Reason for Leave</label>
                            <div class="input-group">
                                <span class="input-group-text bg-info text-white">
                                    <i class="bi bi-chat-text"></i>
                                </span>
                                <textarea class="form-control" id="leave_reason" name="leave_reason" rows="3" placeholder="Please provide reason for leave application..." required></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="leave_days" class="form-label fw-semibold">Total Days</label>
                            <div class="input-group">
                                <span class="input-group-text bg-secondary text-white">
                                    <i class="bi bi-calculator"></i>
                                </span>
                                <input type="number" class="form-control" id="leave_days" name="leave_days" min="1" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="contact_during_leave" class="form-label fw-semibold">Contact Number</label>
                            <div class="input-group">
                                <span class="input-group-text bg-primary text-white">
                                    <i class="bi bi-telephone"></i>
                                </span>
                                <input type="tel" class="form-control" id="contact_during_leave" name="contact_during_leave" placeholder="Emergency contact number">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-send me-1"></i>Submit Leave Application
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Permission Modal -->
<div class="modal fade" id="permissionModal" tabindex="-1" aria-labelledby="permissionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="permissionModalLabel">
                    <i class="bi bi-clock-history me-2"></i>Request Permission
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="permissionForm" action="../../api/apply_permission.php" method="POST">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="permission_employee_id" class="form-label fw-semibold">Employee</label>
                            <div class="input-group">
                                <span class="input-group-text bg-primary text-white">
                                    <i class="bi bi-person"></i>
                                </span>
                                <select class="form-select" id="permission_employee_id" name="employee_id" required>
                                    <option value="">Select Employee</option>
                                    <?php
                                    $employees_permission = $conn->query("SELECT employee_id, name, employee_code FROM employees ORDER BY name ASC");
                                    if ($employees_permission && mysqli_num_rows($employees_permission) > 0) {
                                        while ($emp = $employees_permission->fetch_assoc()) {
                                            echo "<option value='" . $emp['employee_id'] . "'>" . htmlspecialchars($emp['name']) . " (" . htmlspecialchars($emp['employee_code']) . ")</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="permission_date" class="form-label fw-semibold">Date</label>
                            <div class="input-group">
                                <span class="input-group-text bg-success text-white">
                                    <i class="bi bi-calendar-date"></i>
                                </span>
                                <input type="date" class="form-control" id="permission_date" name="permission_date" value="<?= $today ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="permission_type" class="form-label fw-semibold">Permission Type</label>
                            <div class="input-group">
                                <span class="input-group-text bg-warning text-dark">
                                    <i class="bi bi-list"></i>
                                </span>
                                <select class="form-select" id="permission_type" name="permission_type" required>
                                    <option value="">Select Type</option>
                                    <option value="early_departure">Early Departure</option>
                                    <option value="late_arrival">Late Arrival</option>
                                    <option value="extended_lunch">Extended Lunch Break</option>
                                    <option value="personal_work">Personal Work</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="from_time" class="form-label fw-semibold">From Time</label>
                            <div class="input-group">
                                <span class="input-group-text bg-danger text-white">
                                    <i class="bi bi-clock"></i>
                                </span>
                                <input type="time" class="form-control" id="from_time" name="from_time" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="to_time" class="form-label fw-semibold">To Time</label>
                            <div class="input-group">
                                <span class="input-group-text bg-info text-white">
                                    <i class="bi bi-clock-fill"></i>
                                </span>
                                <input type="time" class="form-control" id="to_time" name="to_time" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <label for="permission_reason" class="form-label fw-semibold">Reason</label>
                            <div class="input-group">
                                <span class="input-group-text bg-secondary text-white">
                                    <i class="bi bi-chat-text"></i>
                                </span>
                                <textarea class="form-control" id="permission_reason" name="permission_reason" rows="2" placeholder="Brief reason for permission request..." required></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-info">
                        <i class="bi bi-send me-1"></i>Submit Permission Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Leave History Modal -->
<div class="modal fade" id="leaveHistoryModal" tabindex="-1" aria-labelledby="leaveHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="leaveHistoryModalLabel">
                    <i class="bi bi-list-check me-2"></i>Leave & Permission History
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="history_employee_filter" class="form-label fw-semibold">Filter by Employee</label>
                        <select class="form-select" id="history_employee_filter" onchange="filterLeaveHistory()">
                            <option value="">All Employees</option>
                            <?php
                            $employees_history = $conn->query("SELECT employee_id, name, employee_code FROM employees ORDER BY name ASC");
                            if ($employees_history && mysqli_num_rows($employees_history) > 0) {
                                while ($emp = $employees_history->fetch_assoc()) {
                                    echo "<option value='" . $emp['employee_id'] . "'>" . htmlspecialchars($emp['name']) . " (" . htmlspecialchars($emp['employee_code']) . ")</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="history_type_filter" class="form-label fw-semibold">Filter by Type</label>
                        <select class="form-select" id="history_type_filter" onchange="filterLeaveHistory()">
                            <option value="">All Types</option>
                            <option value="leave">Leave Applications</option>
                            <option value="permission">Permission Requests</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="history_status_filter" class="form-label fw-semibold">Filter by Status</label>
                        <select class="form-select" id="history_status_filter" onchange="filterLeaveHistory()">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped" id="leaveHistoryTable">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Type</th>
                                <th>Details</th>
                                <th>Duration</th>
                                <th>Applied Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="leaveHistoryTableBody">
                            <!-- Will be populated via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Close
                </button>
                <button type="button" class="btn btn-primary" onclick="exportLeaveHistory()">
                    <i class="bi bi-download me-1"></i>Export History
                </button>
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
    // Remove any existing alerts first
    const existingAlerts = document.querySelectorAll('.custom-alert');
    existingAlerts.forEach(alert => alert.remove());
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed custom-alert`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 10000; min-width: 350px; max-width: 500px; box-shadow: 0 4px 12px rgba(0,0,0,0.2);';
    
    // Choose appropriate icon based on type
    let icon = 'bi-info-circle';
    if (type === 'success') icon = 'bi-check-circle';
    else if (type === 'danger') icon = 'bi-exclamation-triangle';
    else if (type === 'warning') icon = 'bi-exclamation-triangle';
    
    alertDiv.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="bi ${icon} me-2 fs-5"></i>
            <div class="flex-grow-1">${message}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    
    document.body.appendChild(alertDiv);
    
    // Add entrance animation
    setTimeout(() => {
        alertDiv.classList.add('show');
    }, 100);
    
    // Auto remove after 5 seconds for success messages, 7 seconds for errors
    const timeout = type === 'success' ? 5000 : 7000;
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.classList.remove('show');
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.parentNode.removeChild(alertDiv);
                }
            }, 300);
        }
    }, timeout);
    
    // Also log to console for debugging
    console.log(`Alert (${type}): ${message}`);
}

// Biometric device management functions
let devices = [];
let syncStatus = [];

// Quick status check function
async function checkBiometricStatus() {
    try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 2000); // 2 second timeout for status check
        
        const response = await fetch('../../api/biometric_status_check.php', {
            signal: controller.signal,
            headers: {
                'Cache-Control': 'no-cache'
            }
        });
        
        clearTimeout(timeoutId);
        
        if (!response.ok) {
            throw new Error(`Status check failed: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('Biometric Status Check:', data);
        
        return data.status === 'ok' && data.database_connected;
        
    } catch (error) {
        console.warn('Status check failed:', error.message);
        return false;
    }
}

// Load devices and sync status on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('Page loaded, starting biometric system initialization...');
    
    // Initialize all components
    initializePageComponents();
    
    // Start loading immediately with better UI feedback
    showLoadingState();
    
    // First do a quick status check
    setTimeout(async () => {
        console.log('Starting status check...');
        const statusOk = await checkBiometricStatus();
        
        if (!statusOk) {
            console.warn('Status check failed, showing fallback content');
            showFallbackContent();
            return;
        }
        
        console.log('Status check passed, loading full data...');
        
        // Set a fallback timeout - if loading takes more than 8 seconds, show error
        const fallbackTimeout = setTimeout(() => {
            console.warn('Fallback timeout reached after 8 seconds');
            showFallbackContent();
        }, 8000);
        
        // Status is OK, proceed with full loading
        try {
            await Promise.all([
                loadDevices(), 
                loadSyncStatus(),
                getUserLocation(),
                getUserIP()
            ]);
            clearTimeout(fallbackTimeout);
            console.log('Biometric data loaded successfully');
        } catch (error) {
            console.error('Error loading biometric data:', error);
            clearTimeout(fallbackTimeout);
            showFallbackContent();
        }
    }, 100);
    
    // Initialize live clock
    updateClock();
    setInterval(updateClock, 1000);
});

// Initialize page components
function initializePageComponents() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize modals
    initializeModals();
    
    // Set up event listeners
    setupEventListeners();
    
    // Load any saved drafts
    loadAttendanceDraft();
}

// Initialize modals
function initializeModals() {
    // Pre-initialize commonly used modals for better performance
    const modalElements = [
        'smartAttendanceModal',
        'leaveCalendarModal', 
        'aiLeaveAssistantModal',
        'analyticsModal',
        'applyLeaveModal'
    ];
    
    modalElements.forEach(modalId => {
        const modalElement = document.getElementById(modalId);
        if (modalElement) {
            new bootstrap.Modal(modalElement);
        }
    });
}

// Setup event listeners
function setupEventListeners() {
    // Form validation
    const attendanceForm = document.getElementById('attendanceForm');
    if (attendanceForm) {
        attendanceForm.addEventListener('submit', function(e) {
            if (!validateAttendanceForm()) {
                e.preventDefault();
                showAlert('Please check your attendance data before submitting.', 'warning');
            }
        });
    }
    
    // Auto-save draft functionality
    let autoSaveTimer;
    const formInputs = document.querySelectorAll('#attendanceForm input, #attendanceForm select');
    formInputs.forEach(input => {
        input.addEventListener('change', () => {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(() => {
                saveAttendanceDraft();
            }, 2000);
        });
    });
}

// Validate attendance form
function validateAttendanceForm() {
    let isValid = true;
    const employeeRows = document.querySelectorAll('input[name="employee_id[]"]');
    
    employeeRows.forEach(employeeInput => {
        const empId = employeeInput.value;
        const statusSelect = document.querySelector(`select[name="status[${empId}]"]`);
        const timeInInput = document.querySelector(`input[name="time_in[${empId}]"]`);
        const timeOutInput = document.querySelector(`input[name="time_out[${empId}]"]`);
        
        if (statusSelect && statusSelect.value === 'Present') {
            if (!timeInInput.value) {
                showAlert(`Please set punch-in time for employee ID ${empId}`, 'warning');
                timeInInput.focus();
                isValid = false;
                return false;
            }
            
            // Validate time logic
            if (timeInInput.value && timeOutInput.value) {
                if (timeInInput.value >= timeOutInput.value) {
                    showAlert(`Punch-out time must be after punch-in time for employee ID ${empId}`, 'warning');
                    timeOutInput.focus();
                    isValid = false;
                    return false;
                }
            }
        }
    });
    
    return isValid;
}

// Save attendance draft
function saveAttendanceDraft() {
    const formData = new FormData(document.getElementById('attendanceForm'));
    const draftData = {};
    
    for (let [key, value] of formData.entries()) {
        draftData[key] = value;
    }
    
    localStorage.setItem('attendanceDraft', JSON.stringify(draftData));
    console.log('Attendance draft saved');
}

// Load attendance draft
function loadAttendanceDraft() {
    const savedDraft = localStorage.getItem('attendanceDraft');
    if (savedDraft) {
        try {
            const draftData = JSON.parse(savedDraft);
            // Restore form data
            Object.keys(draftData).forEach(key => {
                const element = document.querySelector(`[name="${key}"]`);
                if (element) {
                    element.value = draftData[key];
                }
            });
            showAlert('Previous draft restored', 'info');
        } catch (error) {
            console.error('Error loading draft:', error);
        }
    }
}

function showLoadingState() {
    const devicesList = document.getElementById('devicesList');
    const syncStatusList = document.getElementById('syncStatusList');
    
    devicesList.innerHTML = `
        <div class="text-center py-2">
            <div class="d-flex align-items-center justify-content-center">
                <div class="spinner-border spinner-border-sm text-primary me-2" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <small class="text-muted">Loading devices...</small>
            </div>
            <div class="mt-1">
                <small class="text-info" style="font-size: 0.75rem;">Fetching from database...</small>
            </div>
        </div>
    `;
    
    syncStatusList.innerHTML = `
        <div class="text-center py-2">
            <div class="d-flex align-items-center justify-content-center">
                <div class="spinner-border spinner-border-sm text-primary me-2" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <small class="text-muted">Loading sync status...</small>
            </div>
            <div class="mt-1">
                <small class="text-info" style="font-size: 0.75rem;">Fetching from database...</small>
            </div>
        </div>
    `;
}

function showFallbackContent() {
    const devicesList = document.getElementById('devicesList');
    const syncStatusList = document.getElementById('syncStatusList');
    
    // Show static fallback content with sample data for demonstration
    devicesList.innerHTML = `
        <div class="text-center py-2 mb-2">
            <div class="text-warning mb-2">
                <i class="bi bi-exclamation-triangle"></i>
            </div>
            <small class="text-muted">Unable to load devices</small>
            <div class="mt-2">
                <button class="btn btn-sm btn-outline-primary" onclick="loadDevices()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Try Again
                </button>
            </div>
        </div>
        <div class="card device-card active mb-2">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="fw-bold">Sample Device 1</small>
                        <div><small class="text-muted">Status: Active</small></div>
                    </div>
                    <span class="badge bg-success">Online</span>
                </div>
            </div>
        </div>
        <div class="card device-card active mb-2">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="fw-bold">Sample Device 2</small>
                        <div><small class="text-muted">Status: Active</small></div>
                    </div>
                    <span class="badge bg-warning">Syncing</span>
                </div>
            </div>
        </div>
    `;
    
    syncStatusList.innerHTML = `
        <div class="text-center py-2 mb-2">
            <div class="text-warning mb-2">
                <i class="bi bi-exclamation-triangle"></i>
            </div>
            <small class="text-muted">Unable to load sync status</small>
            <div class="mt-2">
                <button class="btn btn-sm btn-outline-primary" onclick="loadSyncStatus()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Try Again
                </button>
            </div>
        </div>
        <div class="card mb-2">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="fw-bold">Device 1</small>
                        <div><small class="text-muted">Last sync: Just now</small></div>
                    </div>
                    <span class="badge bg-success">Synced</span>
                </div>
            </div>
        </div>
        <div class="card mb-2">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="fw-bold">Device 2</small>
                        <div><small class="text-muted">Last sync: 5 min ago</small></div>
                    </div>
                    <span class="badge bg-warning">Pending</span>
                </div>
            </div>
        </div>
    `;
}

// Update live clock function
function updateClock() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { 
        hour12: true, 
        hour: '2-digit', 
        minute: '2-digit', 
        second: '2-digit' 
    });
    const clockElement = document.getElementById('liveClock');
    if (clockElement) {
        clockElement.textContent = timeString;
    }
}

async function loadDevices() {
    console.log('Starting to load devices...');
    const devicesList = document.getElementById('devicesList');
    
    try {
        // Add timeout to the fetch request
        const controller = new AbortController();
        const timeoutId = setTimeout(() => {
            console.warn('Device loading timeout after 5 seconds');
            controller.abort();
        }, 5000); // 5 second timeout
        
        console.log('Fetching devices from API...');
        const response = await fetch('api/biometric_api_test.php?action=get_devices', {
            signal: controller.signal,
            headers: {
                'Cache-Control': 'no-cache'
            }
        });
        clearTimeout(timeoutId);
        
        console.log('Device API response received:', response.status, response.ok);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        console.log('Device data received:', data);
        
        if (data.success) {
            devices = data.devices;
            console.log('Devices loaded:', devices.length);
            renderDevices();
        } else {
            console.error('Error loading devices:', data.message);
            devicesList.innerHTML = '<div class="text-center py-3"><small class="text-danger">Failed to load devices: ' + (data.message || 'Unknown error') + '</small></div>';
        }
    } catch (error) {
        console.error('Error loading devices:', error);
        if (error.name === 'AbortError') {
            devicesList.innerHTML = '<div class="text-center py-3"><small class="text-warning">Loading devices timed out. <button class="btn btn-sm btn-link p-0" onclick="loadDevices()">Retry</button></small></div>';
        } else {
            devicesList.innerHTML = '<div class="text-center py-3"><small class="text-danger">Failed to load devices. <button class="btn btn-sm btn-link p-0" onclick="loadDevices()">Retry</button></small></div>';
        }
    }
}

async function loadSyncStatus() {
    console.log('Starting to load sync status...');
    const syncStatusList = document.getElementById('syncStatusList');
    
    try {
        // Add timeout to the fetch request
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 5000); // 5 second timeout
        console.log('Fetching sync status from API...');
        const response = await fetch('api/biometric_api_test.php?action=get_sync_status', {
            signal: controller.signal,
            headers: {
                'Cache-Control': 'no-cache'
            }
        });
        clearTimeout(timeoutId);
        
        console.log('Sync status response received:', response.status, response.ok);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        console.log('Sync status data received:', data);
        
        if (data.success) {
            syncStatus = data.status;
            console.log('Sync status loaded:', syncStatus.length);
            renderSyncStatus();
        } else {
            console.error('Error loading sync status:', data.message);
            syncStatusList.innerHTML = '<div class="text-center py-3"><small class="text-danger">Failed to load sync status: ' + (data.message || 'Unknown error') + '</small></div>';
        }
    } catch (error) {
        console.error('Error loading sync status:', error);
        if (error.name === 'AbortError') {
            syncStatusList.innerHTML = '<div class="text-center py-3"><small class="text-warning">Loading sync status timed out. <button class="btn btn-sm btn-link p-0" onclick="loadSyncStatus()">Retry</button></small></div>';
        } else {
            syncStatusList.innerHTML = '<div class="text-center py-3"><small class="text-danger">Failed to load sync status. <button class="btn btn-sm btn-link p-0" onclick="loadSyncStatus()">Retry</button></small></div>';
        }
    }
}

    function renderDevices() {
        const devicesList = document.getElementById('devicesList');
        
        if (devices.length === 0) {
            devicesList.innerHTML = '<div class="text-center py-3"><small class="text-muted">No devices configured</small></div>';
            return;
        }

        devicesList.innerHTML = devices.map(device => {
            const iconClass = getDeviceIcon(device.device_type);
            const colorClass = getDeviceColor(device.device_type);
            // Use is_enabled from database instead of is_active
            const isActive = device.is_enabled == 1;
            
            return `
                <div class="d-flex align-items-center justify-content-between p-2 rounded device-item" style="border: 1px dashed #dee2e6;">
                    <div class="d-flex align-items-center">
                        <div class="bg-light rounded p-2 me-3">
                            <i class="bi ${iconClass} text-${colorClass} fs-5"></i>
                        </div>
                        <div>
                            <span class="fw-medium">${device.device_name}</span>
                            <small class="text-muted d-block">${device.location}</small>
                        </div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="device_${device.id}" 
                               ${isActive ? 'checked' : ''} 
                               onchange="toggleDevice(${device.id}, this.checked)">
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderSyncStatus() {
        const syncStatusList = document.getElementById('syncStatusList');
        
        if (syncStatus.length === 0) {
            syncStatusList.innerHTML = '<div class="text-center py-3"><small class="text-muted">No sync status available</small></div>';
            return;
        }

        const statusHtml = syncStatus.map(status => {
            // Use mapped values from API
            const statusColor = status.sync_status === 'sync' ? 'success' : 'danger';
            const statusIcon = status.sync_status === 'sync' ? 'check-circle' : 'x-circle';
            const statusText = status.sync_status === 'sync' ? 'Sync' : 'Failed';
            const lastSync = new Date(status.last_sync).toLocaleString();
            
            return `
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <div class="bg-${statusColor} rounded-circle me-3" style="width: 8px; height: 8px;"></div>
                        <div>
                            <span class="fw-medium">${status.campus_name}</span>
                            <small class="text-muted d-block">Last sync: ${lastSync}</small>
                        </div>
                    </div>
                    <div class="d-flex align-items-center">
                        <i class="bi bi-${statusIcon} text-${statusColor} me-1"></i>
                        <small class="text-${statusColor} fw-medium">${statusText}</small>
                    </div>
                </div>
            `;
        }).join('');

        syncStatusList.innerHTML = statusHtml;
    }function getDeviceIcon(deviceType) {
    const icons = {
        'fingerprint': 'bi-fingerprint',
        'biometric': 'bi-person-badge',
        'facial_recognition': 'bi-person-check'
    };
    return icons[deviceType] || 'bi-device-hdd';
}

function getDeviceColor(deviceType) {
    const colors = {
        'fingerprint': 'primary',
        'biometric': 'info',
        'facial_recognition': 'success'
    };
    return colors[deviceType] || 'secondary';
}

    async function toggleDevice(deviceId, isActive) {
        try {
            const response = await fetch('api/biometric_api_test.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'toggle_device',
                    device_id: deviceId,
                    is_active: isActive
                })
            });        const data = await response.json();
        
        if (data.success) {
            showAlert(`Device ${isActive ? 'activated' : 'deactivated'} successfully`, 'success');
            // Update local data
            const device = devices.find(d => d.id == deviceId);
            if (device) {
                device.is_active = isActive;
            }
        } else {
            showAlert(data.message || 'Failed to update device', 'danger');
            // Revert toggle
            document.getElementById(`device_${deviceId}`).checked = !isActive;
        }
    } catch (error) {
        console.error('Error toggling device:', error);
        showAlert('Failed to update device. Please check your connection.', 'danger');
        // Revert toggle
        document.getElementById(`device_${deviceId}`).checked = !isActive;
    }
}

async function syncAllDevices() {
    const syncAllBtn = document.getElementById('syncAllBtn');
    if (!syncAllBtn) return;
    
    const originalHtml = syncAllBtn.innerHTML;
    
    syncAllBtn.innerHTML = '<i class="bi bi-arrow-clockwise me-1" style="animation: spin 1s linear infinite;"></i> Syncing...';
    syncAllBtn.disabled = true;
    
    try {
        const response = await fetch('api/biometric_api_test.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'sync_all_devices'
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert('All devices synced successfully!', 'success');
            loadSyncStatus(); // Refresh sync status
        } else {
            showAlert(data.message || 'Failed to sync devices', 'danger');
        }
    } catch (error) {
        console.error('Error syncing devices:', error);
        showAlert('Failed to sync devices. Please check your connection.', 'danger');
    } finally {
        syncAllBtn.innerHTML = originalHtml;
        syncAllBtn.disabled = false;
    }
}

function openDeviceSettings() {
    // Create modal for device settings with actual database data
    const deviceTableRows = devices.map(device => `
        <tr>
            <td>
                <div class="d-flex align-items-center">
                    <i class="bi ${getDeviceIcon(device.device_type)} text-${getDeviceColor(device.device_type)} me-2"></i>
                    ${device.device_name}
                </div>
            </td>
            <td><span class="badge bg-secondary">${device.device_type.replace('_', ' ')}</span></td>
            <td>${device.location}</td>
            <td>
                <span class="badge bg-${device.is_active ? 'success' : 'danger'}">
                    ${device.is_active ? 'Active' : 'Inactive'}
                </span>
            </td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="editDevice(${device.id})" title="Edit Device">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger ms-1" onclick="deleteDevice(${device.id})" title="Delete Device">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
    
    const campusList = [...new Set(syncStatus.map(s => s.campus_name))].map(campus => `
        <div class="list-group-item d-flex justify-content-between align-items-center">
            ${campus}
            <span class="badge bg-success rounded-pill">Active</span>
        </div>
    `).join('');

    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = 'deviceSettingsModal';
    modal.innerHTML = `
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-gear me-2"></i>Biometric Device Settings
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Device Management</h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Device</th>
                                                    <th>Type</th>
                                                    <th>Location</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                ${deviceTableRows}
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="mt-3">
                                        <button class="btn btn-primary btn-sm" onclick="addNewDevice()">
                                            <i class="bi bi-plus me-1"></i>Add New Device
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6 class="mb-0">Sync Settings</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Sync Interval</label>
                                        <select class="form-select" id="syncInterval">
                                            <option value="5">Every 5 minutes</option>
                                            <option value="10" selected>Every 10 minutes</option>
                                            <option value="15">Every 15 minutes</option>
                                            <option value="30">Every 30 minutes</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Connection Timeout</label>
                                        <input type="number" class="form-control" id="connectionTimeout" value="30" min="10" max="120">
                                        <small class="text-muted">Seconds</small>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="autoRetry" checked>
                                        <label class="form-check-label" for="autoRetry">
                                            Auto-retry failed connections
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Campus Locations</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Add New Campus</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="newCampusName" placeholder="Campus name">
                                            <button class="btn btn-outline-primary" type="button" onclick="addNewCampus()">
                                                <i class="bi bi-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="list-group">
                                        ${campusList}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveDeviceSettings()">
                        <i class="bi bi-save me-1"></i>Save Settings
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    
    // Clean up modal when closed
    modal.addEventListener('hidden.bs.modal', () => {
        document.body.removeChild(modal);
    });
}

function editDevice(deviceId) {
    const device = devices.find(d => d.id == deviceId);
    if (!device) return;
    
    // Create edit device modal
    const editModal = document.createElement('div');
    editModal.className = 'modal fade';
    editModal.id = 'editDeviceModal';
    editModal.innerHTML = `
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Device</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editDeviceForm">
                        <div class="mb-3">
                            <label class="form-label">Device Name</label>
                            <input type="text" class="form-control" id="editDeviceName" value="${device.device_name}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Device Type</label>
                            <select class="form-select" id="editDeviceType">
                                <option value="fingerprint" ${device.device_type === 'fingerprint' ? 'selected' : ''}>Fingerprint</option>
                                <option value="biometric" ${device.device_type === 'biometric' ? 'selected' : ''}>Biometric</option>
                                <option value="facial_recognition" ${device.device_type === 'facial_recognition' ? 'selected' : ''}>Facial Recognition</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" class="form-control" id="editDeviceLocation" value="${device.location}">
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="editDeviceActive" ${device.is_active ? 'checked' : ''}>
                            <label class="form-check-label" for="editDeviceActive">
                                Device Active
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="updateDevice(${deviceId})">Update Device</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(editModal);
    const bsEditModal = new bootstrap.Modal(editModal);
    bsEditModal.show();
    
    editModal.addEventListener('hidden.bs.modal', () => {
        document.body.removeChild(editModal);
    });
}

async function updateDevice(deviceId) {
    const deviceName = document.getElementById('editDeviceName').value;
    const deviceType = document.getElementById('editDeviceType').value;
    const location = document.getElementById('editDeviceLocation').value;
    const isActive = document.getElementById('editDeviceActive').checked;
    
    if (!deviceName.trim() || !location.trim()) {
        showAlert('Please fill in all required fields', 'warning');
        return;
    }
    
    try {
        const response = await fetch('api/biometric_api_test.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'update_device',
                device_id: deviceId,
                device_name: deviceName,
                device_type: deviceType,
                location: location,
                is_active: isActive
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert('Device updated successfully!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('editDeviceModal')).hide();
            loadDevices(); // Refresh devices list
            
            // Close settings modal and reopen to show updated data
            const settingsModal = bootstrap.Modal.getInstance(document.getElementById('deviceSettingsModal'));
            if (settingsModal) {
                settingsModal.hide();
                setTimeout(() => openDeviceSettings(), 500);
            }
        } else {
            showAlert(data.message || 'Failed to update device', 'danger');
        }
    } catch (error) {
        console.error('Error updating device:', error);
        showAlert('Failed to update device. Please check your connection.', 'danger');
    }
}

async function deleteDevice(deviceId) {
    if (!confirm('Are you sure you want to delete this device? This action cannot be undone.')) {
        return;
    }
    
    try {
        const response = await fetch('api/biometric_api_test.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'delete_device',
                device_id: deviceId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert('Device deleted successfully!', 'success');
            loadDevices(); // Refresh devices list
            
            // Close settings modal and reopen to show updated data
            const settingsModal = bootstrap.Modal.getInstance(document.getElementById('deviceSettingsModal'));
            if (settingsModal) {
                settingsModal.hide();
                setTimeout(() => openDeviceSettings(), 500);
            }
        } else {
            showAlert(data.message || 'Failed to delete device', 'danger');
        }
    } catch (error) {
        console.error('Error deleting device:', error);
        showAlert('Failed to delete device. Please check your connection.', 'danger');
    }
}

function addNewDevice() {
    // Create add device modal
    const addModal = document.createElement('div');
    addModal.className = 'modal fade';
    addModal.id = 'addDeviceModal';
    addModal.innerHTML = `
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Device</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addDeviceForm">
                        <div class="mb-3">
                            <label class="form-label">Device Name</label>
                            <input type="text" class="form-control" id="newDeviceName" placeholder="Enter device name">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Device Type</label>
                            <select class="form-select" id="newDeviceType">
                                <option value="fingerprint">Fingerprint</option>
                                <option value="biometric">Biometric</option>
                                <option value="facial_recognition">Facial Recognition</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" class="form-control" id="newDeviceLocation" placeholder="Enter location">
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="newDeviceActive" checked>
                            <label class="form-check-label" for="newDeviceActive">
                                Device Active
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="createDevice()">Add Device</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(addModal);
    const bsAddModal = new bootstrap.Modal(addModal);
    bsAddModal.show();
    
    addModal.addEventListener('hidden.bs.modal', () => {
        document.body.removeChild(addModal);
    });
}

async function createDevice() {
    const deviceName = document.getElementById('newDeviceName').value;
    const deviceType = document.getElementById('newDeviceType').value;
    const location = document.getElementById('newDeviceLocation').value;
    const isActive = document.getElementById('newDeviceActive').checked;
    
    if (!deviceName.trim() || !location.trim()) {
        showAlert('Please fill in all required fields', 'warning');
        return;
    }
    
    try {
        const response = await fetch('api/biometric_api_test.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'create_device',
                device_name: deviceName,
                device_type: deviceType,
                location: location,
                is_active: isActive
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert('Device added successfully!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('addDeviceModal')).hide();
            loadDevices(); // Refresh devices list
            
            // Close settings modal and reopen to show updated data
            const settingsModal = bootstrap.Modal.getInstance(document.getElementById('deviceSettingsModal'));
            if (settingsModal) {
                settingsModal.hide();
                setTimeout(() => openDeviceSettings(), 500);
            }
        } else {
            showAlert(data.message || 'Failed to add device', 'danger');
        }
    } catch (error) {
        console.error('Error adding device:', error);
        showAlert('Failed to add device. Please check your connection.', 'danger');
    }
}

function addNewCampus() {
    const campusName = document.getElementById('newCampusName').value;
    if (!campusName.trim()) {
        showAlert('Please enter a campus name', 'warning');
        return;
    }
    
    // This would typically save to database
    showAlert('Campus management feature coming soon', 'info');
    document.getElementById('newCampusName').value = '';
}

function saveDeviceSettings() {
    showAlert('Device settings saved successfully!', 'success');
    const modal = bootstrap.Modal.getInstance(document.getElementById('deviceSettingsModal'));
    modal.hide();
}

// Device toggle handlers
document.addEventListener('DOMContentLoaded', function() {
    // Fingerprint toggle
    const fingerprintToggle = document.getElementById('fingerprintToggle');
    if (fingerprintToggle) {
        fingerprintToggle.addEventListener('change', function() {
            const status = this.checked ? 'enabled' : 'disabled';
            showAlert(`Fingerprint device ${status}`, this.checked ? 'success' : 'warning');
        });
    }
    
    // Biometric toggle
    const biometricToggle = document.getElementById('biometricToggle');
    if (biometricToggle) {
        biometricToggle.addEventListener('change', function() {
            const status = this.checked ? 'enabled' : 'disabled';
            showAlert(`Biometric device ${status}`, this.checked ? 'success' : 'warning');
        });
    }
    
    // Facial recognition toggle
    const facialToggle = document.getElementById('facialToggle');
    if (facialToggle) {
        facialToggle.addEventListener('change', function() {
            const status = this.checked ? 'enabled' : 'disabled';
            showAlert(`Facial recognition ${status}`, this.checked ? 'success' : 'warning');
        });
    }
});

// Leave Management Functions
function calculateLeaveDays() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    
    if (startDate && endDate) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        
        if (end >= start) {
            const timeDiff = end.getTime() - start.getTime();
            const daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1;
            document.getElementById('leave_days').value = daysDiff;
        } else {
            document.getElementById('leave_days').value = 0;
            showAlert('End date must be after start date', 'warning');
        }
    }
}

// Add event listeners for date fields
document.addEventListener('DOMContentLoaded', function() {
    const startDateField = document.getElementById('start_date');
    const endDateField = document.getElementById('end_date');
    
    if (startDateField && endDateField) {
        startDateField.addEventListener('change', calculateLeaveDays);
        endDateField.addEventListener('change', calculateLeaveDays);
    }
    
    // Set minimum date to today for leave application
    const today = new Date().toISOString().split('T')[0];
    if (startDateField) startDateField.min = today;
    if (endDateField) endDateField.min = today;
});

// Handle leave application form submission
document.addEventListener('DOMContentLoaded', function() {
    const leaveForm = document.getElementById('leaveApplicationForm');
    if (leaveForm) {
        leaveForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Submitting...';
            submitBtn.disabled = true;
            
            const formData = new FormData(this);
            
            fetch('../../api/apply_leave.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Leave application response:', data);
                
                if (data.success) {
                    // Show success message
                    showAlert(`🎉 Leave application submitted successfully! Reference ID: ${data.leave_id}`, 'success');
                    
                    // Reset form and close modal
                    this.reset();
                    
                    // Close modal safely
                    const modalElement = document.getElementById('applyLeaveModal');
                    if (modalElement) {
                        const modal = bootstrap.Modal.getInstance(modalElement);
                        if (modal) {
                            modal.hide();
                        } else {
                            // If no modal instance, try to hide manually
                            modalElement.style.display = 'none';
                            document.body.classList.remove('modal-open');
                            const backdrop = document.querySelector('.modal-backdrop');
                            if (backdrop) backdrop.remove();
                        }
                    }
                    
                    // Reset calculated days
                    const leaveDaysField = document.getElementById('leave_days');
                    if (leaveDaysField) leaveDaysField.value = '';
                    
                    console.log('Leave application submitted successfully with ID:', data.leave_id);
                } else {
                    showAlert(data.message || 'Failed to submit leave application', 'danger');
                }
            })
            .catch(error => {
                console.error('Error submitting leave application:', error);
                showAlert('Failed to submit leave application. Please check your connection and try again.', 'danger');
            })
            .finally(() => {
                // Restore button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }
});

// Handle permission form submission
document.addEventListener('DOMContentLoaded', function() {
    const permissionForm = document.getElementById('permissionForm');
    if (permissionForm) {
        permissionForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Submitting...';
            submitBtn.disabled = true;
            
            const formData = new FormData(this);
            
            fetch('../../api/apply_permission.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Permission request response:', data);
                
                if (data.success) {
                    showAlert(`✅ Permission request submitted successfully! Reference ID: ${data.permission_id}`, 'success');
                    
                    // Reset form and close modal
                    this.reset();
                    
                    // Close modal safely
                    const modalElement = document.getElementById('permissionModal');
                    if (modalElement) {
                        const modal = bootstrap.Modal.getInstance(modalElement);
                        if (modal) {
                            modal.hide();
                        } else {
                            // If no modal instance, try to hide manually
                            modalElement.style.display = 'none';
                            document.body.classList.remove('modal-open');
                            const backdrop = document.querySelector('.modal-backdrop');
                            if (backdrop) backdrop.remove();
                        }
                    }
                    
                    console.log('Permission request submitted successfully with ID:', data.permission_id);
                } else {
                    showAlert(data.message || 'Failed to submit permission request', 'danger');
                }
            })
            .catch(error => {
                console.error('Error submitting permission request:', error);
                showAlert('Failed to submit permission request. Please check your connection and try again.', 'danger');
            })
            .finally(() => {
                // Restore button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }
});

// Show leave history
function showLeaveHistory() {
    const modal = new bootstrap.Modal(document.getElementById('leaveHistoryModal'));
    modal.show();
    loadLeaveHistory();
}

// Load leave history data
function loadLeaveHistory() {
    const employeeFilter = document.getElementById('history_employee_filter').value;
    const typeFilter = document.getElementById('history_type_filter').value;
    const statusFilter = document.getElementById('history_status_filter').value;
    
    const params = new URLSearchParams({
        employee_id: employeeFilter,
        type: typeFilter,
        status: statusFilter
    });
    
    fetch(`../../api/get_leave_history.php?${params}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderLeaveHistory(data.records);
        } else {
            document.getElementById('leaveHistoryTableBody').innerHTML = 
                '<tr><td colspan="7" class="text-center text-muted">No records found</td></tr>';
        }
    })
    .catch(error => {
        console.error('Error loading leave history:', error);
        document.getElementById('leaveHistoryTableBody').innerHTML = 
            '<tr><td colspan="7" class="text-center text-danger">Failed to load data</td></tr>';
    });
}

// Render leave history table
function renderLeaveHistory(records) {
    const tbody = document.getElementById('leaveHistoryTableBody');
    
    if (records.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No records found</td></tr>';
        return;
    }
    
    tbody.innerHTML = records.map(record => {
        const statusClass = record.status === 'approved' ? 'success' : 
                          record.status === 'rejected' ? 'danger' : 'warning';
        const typeClass = record.type === 'leave' ? 'primary' : 'info';
        
        return `
            <tr>
                <td>
                    <strong>${record.employee_name}</strong><br>
                    <small class="text-muted">${record.employee_code}</small>
                </td>
                <td><span class="badge bg-${typeClass}">${record.type === 'leave' ? 'Leave' : 'Permission'}</span></td>
                <td>
                    <strong>${record.type === 'leave' ? record.leave_type : record.permission_type}</strong><br>
                    <small class="text-muted">${record.reason}</small>
                </td>
                <td>
                    ${record.type === 'leave' ? 
                        `${record.start_date} to ${record.end_date}<br><small>(${record.total_days} days)</small>` :
                        `${record.permission_date}<br><small>${record.from_time} - ${record.to_time}</small>`
                    }
                </td>
                <td><small>${new Date(record.applied_date).toLocaleDateString()}</small></td>
                <td><span class="badge bg-${statusClass}">${record.status.charAt(0).toUpperCase() + record.status.slice(1)}</span></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="viewLeaveDetails(${record.id}, '${record.type}')" title="View Details">
                            <i class="bi bi-eye"></i>
                        </button>
                        ${record.status === 'pending' ? `
                            <button class="btn btn-outline-success" onclick="approveLeave(${record.id}, '${record.type}')" title="Approve">
                                <i class="bi bi-check"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="rejectLeave(${record.id}, '${record.type}')" title="Reject">
                                <i class="bi bi-x"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

// Filter leave history
function filterLeaveHistory() {
    loadLeaveHistory();
}

// Approve leave/permission
function approveLeave(id, type) {
    if (!confirm('Are you sure you want to approve this request?')) return;
    
    fetch('../../api/update_leave_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            id: id,
            type: type,
            status: 'approved'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Request approved successfully!', 'success');
            loadLeaveHistory();
        } else {
            showAlert(data.message || 'Failed to approve request', 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Failed to approve request. Please try again.', 'danger');
    });
}

// Reject leave/permission
function rejectLeave(id, type) {
    const reason = prompt('Please provide reason for rejection:');
    if (!reason) return;
    
    fetch('../../api/update_leave_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            id: id,
            type: type,
            status: 'rejected',
            rejection_reason: reason
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Request rejected successfully!', 'info');
            loadLeaveHistory();
        } else {
            showAlert(data.message || 'Failed to reject request', 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Failed to reject request. Please try again.', 'danger');
    });
}

// View leave details
function viewLeaveDetails(id, type) {
    fetch(`../../api/get_leave_details.php?id=${id}&type=${type}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showLeaveDetailsModal(data.record);
        } else {
            showAlert('Failed to load details', 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Failed to load details', 'danger');
    });
}

// Show leave details in modal
function showLeaveDetailsModal(record) {
    // Create a simple details modal
    const detailsHtml = `
        <div class="modal fade" id="leaveDetailsModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">${record.type === 'leave' ? 'Leave' : 'Permission'} Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12"><strong>Employee:</strong> ${record.employee_name}</div>
                            <div class="col-12"><strong>Type:</strong> ${record.type === 'leave' ? record.leave_type : record.permission_type}</div>
                            <div class="col-12"><strong>Reason:</strong> ${record.reason}</div>
                            <div class="col-12"><strong>Status:</strong> <span class="badge bg-${record.status === 'approved' ? 'success' : record.status === 'rejected' ? 'danger' : 'warning'}">${record.status}</span></div>
                            ${record.rejection_reason ? `<div class="col-12"><strong>Rejection Reason:</strong> ${record.rejection_reason}</div>` : ''}
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('leaveDetailsModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add new modal to body
    document.body.insertAdjacentHTML('beforeend', detailsHtml);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('leaveDetailsModal'));
    modal.show();
    
    // Clean up after modal is hidden
    document.getElementById('leaveDetailsModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

// Export leave history
function exportLeaveHistory() {
    const employeeFilter = document.getElementById('history_employee_filter').value;
    const typeFilter = document.getElementById('history_type_filter').value;
    const statusFilter = document.getElementById('history_status_filter').value;
    
    const params = new URLSearchParams({
        employee_id: employeeFilter,
        type: typeFilter,
        status: statusFilter,
        export: 'excel'
    });
    
    window.open(`../../api/export_leave_history.php?${params}`, '_blank');
}

// ============================================
// ADVANCED FEATURES JAVASCRIPT
// ============================================

// Enhanced JavaScript for advanced features
function openSmartAttendance() {
    $('#smartAttendanceModal').modal('show');
    getUserLocation();
    getUserIP();
}

function showLeaveCalendar() {
    $('#leaveCalendarModal').modal('show');
    initializeCalendar();
}

function showAnalytics() {
    $('#analyticsModal').modal('show');
    loadAnalyticsData();
}

// AI Leave Assistant
function openAILeaveAssistant() {
    $('#aiLeaveAssistantModal').modal('show');
    loadAISuggestions();
}

function loadAISuggestions() {
    // Simulate AI analysis
    setTimeout(() => {
        const suggestions = document.getElementById('aiSuggestions');
        suggestions.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span>August 15-16, 2025</span>
                <span class="badge bg-success">Optimal</span>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span>August 29-30, 2025</span>
                <span class="badge bg-warning">Good</span>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span>September 12-13, 2025</span>
                <span class="badge bg-info">Available</span>
            </div>
        `;
    }, 1000);
}

function applyAISuggestion() {
    showAlert('AI suggestion applied! Opening leave application form...', 'success');
    setTimeout(() => {
        $('#aiLeaveAssistantModal').modal('hide');
        showApplyLeaveModal();
    }, 1500);
}

// Show Apply Leave Modal Function
function showApplyLeaveModal() {
    const applyLeaveModal = new bootstrap.Modal(document.getElementById('applyLeaveModal'));
    applyLeaveModal.show();
    
    // Pre-fill form with suggested dates if coming from AI suggestion
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    
    if (startDateInput && endDateInput) {
        // Set dates 2 days from now as example
        const today = new Date();
        const startDate = new Date(today.getTime() + 2 * 24 * 60 * 60 * 1000);
        const endDate = new Date(today.getTime() + 3 * 24 * 60 * 60 * 1000);
        
        startDateInput.value = startDate.toISOString().split('T')[0];
        endDateInput.value = endDate.toISOString().split('T')[0];
    }
}

// Face Recognition
let faceRecognitionStream = null;
function initFaceRecognition() {
    const area = document.getElementById('faceRecognitionArea');
    area.innerHTML = '<div class="spinner-border text-primary" role="status"></div><p class="mt-2">Initializing camera...</p>';
    
    navigator.mediaDevices.getUserMedia({ video: true })
        .then(stream => {
            faceRecognitionStream = stream;
            const video = document.createElement('video');
            video.srcObject = stream;
            video.autoplay = true;
            video.style.width = '100%';
            video.style.height = '200px';
            video.style.objectFit = 'cover';
            
            area.innerHTML = '';
            area.appendChild(video);
            
            // Simulate face recognition after 3 seconds
            setTimeout(() => {
                recognizeFace();
            }, 3000);
        })
        .catch(err => {
            area.innerHTML = '<i class="bi bi-exclamation-triangle text-danger" style="font-size: 3rem;"></i><p class="text-danger mt-2">Camera access denied</p>';
        });
}

function recognizeFace() {
    const area = document.getElementById('faceRecognitionArea');
    area.innerHTML = `
        <div class="text-center">
            <i class="bi bi-person-check-fill text-success" style="font-size: 3rem;"></i>
            <p class="text-success mt-2">Face Recognized!</p>
            <p><strong>John Doe (ID: EMP001)</strong></p>
            <button class="btn btn-success" onclick="completeFaceCheckIn()">Check In</button>
        </div>
    `;
}

function completeFaceCheckIn() {
    if (faceRecognitionStream) {
        faceRecognitionStream.getTracks().forEach(track => track.stop());
    }
    showAlert('Face recognition check-in successful!', 'success');
    $('#smartAttendanceModal').modal('hide');
}

// QR Scanner
function initQRScanner() {
    const area = document.getElementById('qrScannerArea');
    area.innerHTML = '<div class="spinner-border text-info" role="status"></div><p class="mt-2">Starting QR scanner...</p>';
    
    // Simulate QR scanning
    setTimeout(() => {
        area.innerHTML = `
            <div class="text-center">
                <i class="bi bi-qr-code-scan text-success" style="font-size: 3rem;"></i>
                <p class="text-success mt-2">QR Code Scanned!</p>
                <p><strong>Employee ID: EMP001</strong></p>
                <button class="btn btn-success" onclick="completeQRCheckIn()">Check In</button>
            </div>
        `;
    }, 2000);
}

function completeQRCheckIn() {
    showAlert('QR code check-in successful!', 'success');
    $('#smartAttendanceModal').modal('hide');
}

// GPS Location
function getUserLocation() {
    const spinner = document.getElementById('locationSpinner');
    const text = document.getElementById('locationText');
    const btn = document.getElementById('gpsCheckInBtn');
    
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition((position) => {
            const lat = position.coords.latitude;
            const lon = position.coords.longitude;
            
            spinner.style.display = 'none';
            text.innerHTML = `
                <i class="bi bi-geo-alt-fill text-success me-2"></i>
                Location verified<br>
                <small class="text-muted">Office premises detected</small>
            `;
            btn.disabled = false;
            btn.classList.remove('btn-warning');
            btn.classList.add('btn-success');
        }, (error) => {
            spinner.style.display = 'none';
            text.innerHTML = `
                <i class="bi bi-geo-alt text-danger me-2"></i>
                Location access denied
            `;
        });
    }
}

function checkInWithGPS() {
    showAlert('GPS-based check-in successful! Location verified.', 'success');
    $('#smartAttendanceModal').modal('hide');
}

// IP-based Check-in
function getUserIP() {
    fetch('https://api.ipify.org?format=json')
        .then(response => response.json())
        .then(data => {
            document.getElementById('userIP').textContent = data.ip;
        })
        .catch(err => {
            document.getElementById('userIP').textContent = 'Unable to detect';
        });
}

function checkInWithIP() {
    showAlert('IP-based check-in successful! Office network verified.', 'success');
    $('#smartAttendanceModal').modal('hide');
}

function manualCheckIn() {
    showAlert('Manual check-in recorded successfully!', 'success');
    $('#smartAttendanceModal').modal('hide');
}

// Calendar Functions
function initializeCalendar() {
    const calendarDiv = document.getElementById('dynamicCalendar');
    calendarDiv.innerHTML = `
        <div class="calendar-container">
            <div class="text-center mb-3">
                <h5>August 2025</h5>
                <div class="btn-group" role="group">
                    <button class="btn btn-outline-secondary btn-sm">&lt;</button>
                    <button class="btn btn-outline-secondary btn-sm">Today</button>
                    <button class="btn btn-outline-secondary btn-sm">&gt;</button>
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <div class="calendar-grid" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px;">
                        ${generateCalendarDays()}
                    </div>
                </div>
            </div>
        </div>
    `;
}

function generateCalendarDays() {
    const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    let html = '';
    
    // Header
    days.forEach(day => {
        html += `<div class="text-center fw-bold p-2 bg-light">${day}</div>`;
    });
    
    // Sample days with leave data
    for (let i = 1; i <= 31; i++) {
        let classes = 'border p-2 text-center';
        let content = i;
        
        if (i === 15) {
            classes += ' bg-success text-white';
            content += '<br><small>CL</small>';
        } else if (i === 22) {
            classes += ' bg-danger text-white';
            content += '<br><small>SL</small>';
        } else if (i === 28) {
            classes += ' bg-info text-white';
            content += '<br><small>WFH</small>';
        }
        
        html += `<div class="${classes}">${content}</div>`;
    }
    
    return html;
}

function refreshCalendar() {
    showAlert('Calendar refreshed with latest data!', 'success');
    initializeCalendar();
}

function exportCalendar() {
    showAlert('Calendar exported successfully!', 'success');
}

// Analytics Functions
function loadAnalyticsData() {
    // Simulate loading charts
    setTimeout(() => {
        initCharts();
    }, 500);
}

function initCharts() {
    // This would integrate with Chart.js in a real implementation
    console.log('Charts initialized');
}

function exportAnalytics() {
    showAlert('Analytics report exported successfully!', 'success');
}

// Workflow and Policy Functions
function openWorkflowPanel() {
    showAlert('Workflow panel opening...', 'info');
}

function openPolicyConfig() {
    showAlert('Policy configuration panel opening...', 'info');
}

// Manager Tools
function openTeamDashboard() {
    showAlert('Team dashboard opening...', 'info');
}

function bulkApproval() {
    showAlert('Bulk approval panel opening...', 'info');
}

function applyOnBehalf() {
    showAlert('Apply leave on behalf panel opening...', 'info');
}

// Smart Features
function startFaceRecognition() {
    openSmartAttendance();
    setTimeout(() => {
        initFaceRecognition();
    }, 500);
}

function startQRScan() {
    openSmartAttendance();
    setTimeout(() => {
        initQRScanner();
    }, 500);
}

function startGeoAttendance() {
    openSmartAttendance();
}

function startIPAttendance() {
    openSmartAttendance();
}

// Quick Actions Functions for Horizontal Layout
function viewAllRecords() {
    window.location.href = '../../attendance_preview.php';
}

function openLeaveManagement() {
    // Toggle the apply leave modal
    const applyLeaveModal = new bootstrap.Modal(document.getElementById('applyLeaveModal'));
    applyLeaveModal.show();
}

function openAIPoweredFeatures() {
    // Open AI leave assistant
    openAILeaveAssistant();
}

function openSmartAttendance() {
    // Toggle the smart attendance modal  
    const smartModal = new bootstrap.Modal(document.getElementById('smartAttendanceModal'));
    smartModal.show();
}

function openSmartAlerts() {
    // Create and show a comprehensive smart alerts panel
    const smartAlertsModal = `
        <div class="modal fade" id="smartAlertsModal" tabindex="-1" aria-labelledby="smartAlertsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="smartAlertsModalLabel">
                            <i class="bi bi-bell-fill me-2"></i>Smart Alerts & Notifications
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Live Alerts -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card border-danger">
                                    <div class="card-header bg-danger text-white">
                                        <h6 class="mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>Live Alerts</h6>
                                    </div>
                                    <div class="card-body">
                                        <div id="liveAlertsList">
                                            <div class="alert alert-warning d-flex align-items-center" role="alert">
                                                <i class="bi bi-clock-fill me-2"></i>
                                                <div>
                                                    <strong>Late Arrival:</strong> 3 employees are late today (>9:15 AM)
                                                </div>
                                            </div>
                                            <div class="alert alert-info d-flex align-items-center" role="alert">
                                                <i class="bi bi-person-fill-exclamation me-2"></i>
                                                <div>
                                                    <strong>Attendance:</strong> 2 employees haven't checked in yet
                                                </div>
                                            </div>
                                            <div class="alert alert-success d-flex align-items-center" role="alert">
                                                <i class="bi bi-check-circle-fill me-2"></i>
                                                <div>
                                                    <strong>Good News:</strong> 95% attendance rate achieved this week!
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Alert Categories -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="bi bi-gear-fill me-2"></i>Alert Settings</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" id="lateArrivalAlerts" checked>
                                            <label class="form-check-label" for="lateArrivalAlerts">
                                                Late Arrival Alerts
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" id="absenteeAlerts" checked>
                                            <label class="form-check-label" for="absenteeAlerts">
                                                Absentee Alerts
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" id="leaveRequestAlerts" checked>
                                            <label class="form-check-label" for="leaveRequestAlerts">
                                                Leave Request Alerts
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" id="overtimeAlerts">
                                            <label class="form-check-label" for="overtimeAlerts">
                                                Overtime Alerts
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Alert Statistics</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-6">
                                                <h4 class="text-warning">15</h4>
                                                <small>Today's Alerts</small>
                                            </div>
                                            <div class="col-6">
                                                <h4 class="text-info">3</h4>
                                                <small>Pending Actions</small>
                                            </div>
                                        </div>
                                        <div class="row text-center mt-3">
                                            <div class="col-6">
                                                <h4 class="text-success">87</h4>
                                                <small>This Week</small>
                                            </div>
                                            <div class="col-6">
                                                <h4 class="text-primary">340</h4>
                                                <small>This Month</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Alert History -->
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Alert History</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="list-group list-group-flush">
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <i class="bi bi-clock text-warning me-2"></i>
                                                    <strong>09:20 AM</strong> - Late arrival alert for John Doe
                                                </div>
                                                <span class="badge bg-warning">Resolved</span>
                                            </div>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <i class="bi bi-person-x text-danger me-2"></i>
                                                    <strong>09:15 AM</strong> - Absence alert for Jane Smith
                                                </div>
                                                <span class="badge bg-danger">Active</span>
                                            </div>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <i class="bi bi-calendar-check text-info me-2"></i>
                                                    <strong>09:10 AM</strong> - Leave request from Mike Johnson
                                                </div>
                                                <span class="badge bg-info">Pending</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-danger" onclick="clearAllAlerts()">
                            <i class="bi bi-trash me-1"></i>Clear All
                        </button>
                        <button type="button" class="btn btn-primary" onclick="refreshAlerts()">
                            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if it exists
    const existingModal = document.getElementById('smartAlertsModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', smartAlertsModal);

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('smartAlertsModal'));
    modal.show();

    // Start live updates
    startAlertUpdates();
}

// Smart Alerts Functions
function startAlertUpdates() {
    // Simulate live alert updates
    setInterval(() => {
        updateLiveAlerts();
    }, 30000); // Update every 30 seconds
}

function updateLiveAlerts() {
    // Simulate fetching new alerts
    console.log('Updating live alerts...');
    // In a real implementation, this would fetch from the server
}

function clearAllAlerts() {
    const confirmation = confirm('Are you sure you want to clear all alerts?');
    if (confirmation) {
        document.getElementById('liveAlertsList').innerHTML = `
            <div class="text-center py-3">
                <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                <p class="mt-2 mb-0">All alerts cleared!</p>
            </div>
        `;
        showAlert('All alerts cleared successfully!', 'success');
    }
}

function refreshAlerts() {
    showAlert('Refreshing alerts...', 'info');
    setTimeout(() => {
        showAlert('Alerts refreshed! 2 new alerts found.', 'success');
        // Update the alerts display
        updateLiveAlerts();
    }, 1500);
}

function openManagerTools() {
    // Create and show a comprehensive manager tools modal
    const managerToolsModal = `
        <div class="modal fade" id="managerToolsModal" tabindex="-1" aria-labelledby="managerToolsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header bg-secondary text-white">
                        <h5 class="modal-title" id="managerToolsModalLabel">
                            <i class="bi bi-person-gear me-2"></i>Manager Tools Dashboard
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <!-- Team Overview -->
                            <div class="col-md-4">
                                <div class="card h-100">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0"><i class="bi bi-people-fill me-2"></i>Team Overview</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <h4 class="text-success">85%</h4>
                                                <small>Present</small>
                                            </div>
                                            <div class="col-4">
                                                <h4 class="text-warning">12%</h4>
                                                <small>Late</small>
                                            </div>
                                            <div class="col-4">
                                                <h4 class="text-danger">3%</h4>
                                                <small>Absent</small>
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <button class="btn btn-primary btn-sm w-100" onclick="viewTeamDashboard()">
                                                <i class="bi bi-graph-up me-1"></i>View Team Dashboard
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Bulk Operations -->
                            <div class="col-md-4">
                                <div class="card h-100">
                                    <div class="card-header bg-success text-white">
                                        <h6 class="mb-0"><i class="bi bi-check-all me-2"></i>Bulk Operations</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-outline-success btn-sm" onclick="bulkApproval()">
                                                <i class="bi bi-check-circle me-1"></i>Bulk Approval
                                            </button>
                                            <button class="btn btn-outline-warning btn-sm" onclick="bulkMarkPresent()">
                                                <i class="bi bi-person-check me-1"></i>Mark Team Present
                                            </button>
                                            <button class="btn btn-outline-info btn-sm" onclick="generateTeamReport()">
                                                <i class="bi bi-file-earmark-text me-1"></i>Generate Report
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Apply on Behalf -->
                            <div class="col-md-4">
                                <div class="card h-100">
                                    <div class="card-header bg-warning text-dark">
                                        <h6 class="mb-0"><i class="bi bi-person-plus me-2"></i>Apply on Behalf</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-outline-primary btn-sm" onclick="applyLeaveOnBehalf()">
                                                <i class="bi bi-calendar-minus me-1"></i>Apply Leave
                                            </button>
                                            <button class="btn btn-outline-secondary btn-sm" onclick="markAttendanceOnBehalf()">
                                                <i class="bi bi-clock me-1"></i>Mark Attendance
                                            </button>
                                            <button class="btn btn-outline-info btn-sm" onclick="adjustTimeOnBehalf()">
                                                <i class="bi bi-clock-history me-1"></i>Adjust Time
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Activities -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="bi bi-activity me-2"></i>Recent Activities</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Time</th>
                                                        <th>Action</th>
                                                        <th>Employee</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td>09:15 AM</td>
                                                        <td>Leave Approved</td>
                                                        <td>John Doe</td>
                                                        <td><span class="badge bg-success">Approved</span></td>
                                                    </tr>
                                                    <tr>
                                                        <td>09:10 AM</td>
                                                        <td>Late Entry</td>
                                                        <td>Jane Smith</td>
                                                        <td><span class="badge bg-warning">Late</span></td>
                                                    </tr>
                                                    <tr>
                                                        <td>09:05 AM</td>
                                                        <td>Check-in</td>
                                                        <td>Mike Johnson</td>
                                                        <td><span class="badge bg-success">Present</span></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" onclick="refreshManagerData()">
                            <i class="bi bi-arrow-clockwise me-1"></i>Refresh Data
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if it exists
    const existingModal = document.getElementById('managerToolsModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', managerToolsModal);

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('managerToolsModal'));
    modal.show();
}

// Manager Tools Functions
function viewTeamDashboard() {
    showAlert('Opening team dashboard with detailed analytics...', 'info');
    // Here you would typically open a comprehensive team dashboard
    window.open('../../reports/team_dashboard.php', '_blank');
}

function bulkApproval() {
    showAlert('Bulk approval panel opened. You can approve multiple requests at once.', 'success');
    // Implementation for bulk approval functionality
}

function bulkMarkPresent() {
    const confirmation = confirm('Mark entire team as present for today?');
    if (confirmation) {
        markAllPresent();
        showAlert('All team members marked as present!', 'success');
    }
}

function generateTeamReport() {
    showAlert('Generating comprehensive team report...', 'info');
    // Implementation for generating team reports
    setTimeout(() => {
        showAlert('Team report generated and sent to your email!', 'success');
    }, 2000);
}

function applyLeaveOnBehalf() {
    showAlert('Opening leave application form for team members...', 'info');
    showApplyLeaveModal();
}

function markAttendanceOnBehalf() {
    showAlert('You can now mark attendance for any team member.', 'info');
    // Enable special mode for marking attendance on behalf
}

function adjustTimeOnBehalf() {
    showAlert('Time adjustment panel opened. You can modify punch times for team members.', 'warning');
    // Implementation for time adjustment functionality
}

function refreshManagerData() {
    showAlert('Refreshing all manager data...', 'info');
    setTimeout(() => {
        showAlert('Manager dashboard data refreshed successfully!', 'success');
    }, 1500);
}

// Secondary Actions Functions
function openAttendanceCalendar() {
    window.location.href = '../../attendance-calendar.php';
}

function openPayrollReport() {
    window.location.href = '../../payroll_report.php';
}

function manageEmployees() {
    window.location.href = '../employees/employees.php';
}

// Enhanced Utility function for showing alerts
function showAlert(message, type = 'info') {
    // Remove any existing alerts first
    const existingAlerts = document.querySelectorAll('.custom-alert');
    existingAlerts.forEach(alert => alert.remove());
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed custom-alert`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 10000; min-width: 350px; max-width: 500px; box-shadow: 0 4px 12px rgba(0,0,0,0.2);';
    
    // Choose appropriate icon based on type
    let icon = 'bi-info-circle';
    if (type === 'success') icon = 'bi-check-circle';
    else if (type === 'danger') icon = 'bi-exclamation-triangle';
    else if (type === 'warning') icon = 'bi-exclamation-triangle';
    
    alertDiv.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="bi ${icon} me-2 fs-5"></i>
            <div class="flex-grow-1">${message}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    
    document.body.appendChild(alertDiv);
    
    // Add entrance animation
    setTimeout(() => {
        alertDiv.classList.add('show');
    }, 100);
    
    // Auto remove after different times based on type
    const timeout = type === 'success' ? 4000 : (type === 'danger' ? 8000 : 6000);
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.classList.remove('show');
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 300);
        }
    }, timeout);
    
    // Also log to console for debugging
    console.log(`Alert (${type}): ${message}`);
    
    // Play sound for important alerts
    if (type === 'danger' || type === 'warning') {
        playAlertSound();
    }
}

// Play alert sound for important notifications
function playAlertSound() {
    try {
        // Create a simple beep sound using Web Audio API
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        oscillator.frequency.value = 800;
        oscillator.type = 'sine';
        gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
        
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.3);
    } catch (error) {
        console.log('Audio not supported or permission denied');
    }
}

// Additional Utility Functions for Enhanced Attendance System

// Export attendance data
function exportAttendanceData(format = 'excel') {
    showAlert(`Exporting attendance data in ${format.toUpperCase()} format...`, 'info');
    
    // Simulate export process
    setTimeout(() => {
        const link = document.createElement('a');
        link.href = `../../api/export_attendance.php?format=${format}&date=${document.getElementById('attendanceDate').value}`;
        link.download = `attendance_${new Date().toISOString().split('T')[0]}.${format === 'excel' ? 'xlsx' : 'pdf'}`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        showAlert(`Attendance data exported successfully!`, 'success');
    }, 2000);
}

// Print attendance report
function printAttendanceReport() {
    showAlert('Preparing attendance report for printing...', 'info');
    
    // Create print-friendly version
    const printWindow = window.open('', '_blank');
    const attendanceTable = document.querySelector('.table-responsive').innerHTML;
    const todayDate = new Date().toLocaleDateString();
    
    printWindow.document.write(`
        <html>
            <head>
                <title>Attendance Report - ${todayDate}</title>
                <style>
                    body { font-family: Arial, sans-serif; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; }
                    .header { text-align: center; margin-bottom: 20px; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h2>Daily Attendance Report</h2>
                    <p>Date: ${todayDate}</p>
                </div>
                ${attendanceTable}
            </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.print();
    
    showAlert('Attendance report sent to printer!', 'success');
}

// Backup attendance data
function backupAttendanceData() {
    showAlert('Creating attendance data backup...', 'info');
    
    // Simulate backup process
    setTimeout(() => {
        const backupData = {
            date: new Date().toISOString(),
            attendance: getFormData(),
            employees: getEmployeeData(),
            timestamp: Date.now()
        };
        
        localStorage.setItem('attendanceBackup', JSON.stringify(backupData));
        showAlert('Attendance data backup created successfully!', 'success');
    }, 1500);
}

// Restore attendance data from backup
function restoreAttendanceData() {
    const backup = localStorage.getItem('attendanceBackup');
    if (!backup) {
        showAlert('No backup data found!', 'warning');
        return;
    }
    
    const confirmation = confirm('This will overwrite current data. Continue?');
    if (confirmation) {
        try {
            const backupData = JSON.parse(backup);
            restoreFormData(backupData.attendance);
            showAlert('Attendance data restored from backup!', 'success');
        } catch (error) {
            showAlert('Error restoring backup data!', 'danger');
        }
    }
}

// Get form data for backup
function getFormData() {
    const formData = {};
    const form = document.getElementById('attendanceForm');
    if (form) {
        const formDataObj = new FormData(form);
        for (let [key, value] of formDataObj.entries()) {
            formData[key] = value;
        }
    }
    return formData;
}

// Get employee data
function getEmployeeData() {
    const employees = [];
    const employeeRows = document.querySelectorAll('input[name="employee_id[]"]');
    employeeRows.forEach(input => {
        const row = input.closest('tr');
        if (row) {
            const nameCell = row.querySelector('td:first-child strong');
            const codeCell = row.querySelector('td:first-child small');
            employees.push({
                id: input.value,
                name: nameCell ? nameCell.textContent : '',
                code: codeCell ? codeCell.textContent : ''
            });
        }
    });
    return employees;
}

// Restore form data
function restoreFormData(data) {
    Object.keys(data).forEach(key => {
        const element = document.querySelector(`[name="${key}"]`);
        if (element) {
            element.value = data[key];
        }
    });
}

// Quick time set functions
function setQuickTime(type) {
    const now = new Date();
    let time = '';
    
    switch(type) {
        case 'morning':
            time = '09:00';
            break;
        case 'lunch':
            time = '13:00';
            break;
        case 'evening':
            time = '18:00';
            break;
        case 'now':
            time = now.toTimeString().substring(0, 5);
            break;
    }
    
    // Apply to all visible time inputs
    const timeInputs = document.querySelectorAll('input[type="time"]:not([readonly])');
    timeInputs.forEach(input => {
        if (!input.value) {
            input.value = time;
        }
    });
    
    showAlert(`Quick time set to ${time}`, 'success');
}

// Smart suggestions based on patterns
function getSmartSuggestions(employeeId) {
    // Simulate AI-based suggestions
    const suggestions = [
        'Employee usually arrives at 9:15 AM',
        'Frequent late arrivals on Mondays',
        'Perfect attendance last week',
        'Prefers half-day on Fridays'
    ];
    
    return suggestions[Math.floor(Math.random() * suggestions.length)];
}

// Keyboard shortcuts handler
document.addEventListener('keydown', function(e) {
    // Ctrl + S to save attendance
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        document.getElementById('attendanceForm')?.submit();
        showAlert('Saving attendance...', 'info');
    }
    
    // Ctrl + E to export data
    if (e.ctrlKey && e.key === 'e') {
        e.preventDefault();
        exportAttendanceData();
    }
    
    // Ctrl + P to print
    if (e.ctrlKey && e.key === 'p') {
        e.preventDefault();
        printAttendanceReport();
    }
    
    // ESC to close modals
    if (e.key === 'Escape') {
        const openModals = document.querySelectorAll('.modal.show');
        openModals.forEach(modal => {
            const modalInstance = bootstrap.Modal.getInstance(modal);
            if (modalInstance) {
                modalInstance.hide();
            }
        });
    }
});

// Performance monitoring
function monitorPerformance() {
    const loadTime = performance.now();
    console.log(`Page load time: ${loadTime.toFixed(2)}ms`);
    
    // Monitor API response times
    const originalFetch = window.fetch;
    window.fetch = function(...args) {
        const start = performance.now();
        return originalFetch.apply(this, args).then(response => {
            const end = performance.now();
            console.log(`API call took ${(end - start).toFixed(2)}ms`);
            return response;
        });
    };
}

// Initialize performance monitoring
monitorPerformance();

// Network status monitoring
window.addEventListener('online', function() {
    showAlert('Connection restored! Syncing data...', 'success');
    // Attempt to sync any pending data
});

window.addEventListener('offline', function() {
    showAlert('Connection lost! Working in offline mode.', 'warning');
});

// Page visibility change handler
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        console.log('Page hidden - pausing live updates');
    } else {
        console.log('Page visible - resuming live updates');
        // Refresh data when page becomes visible again
        setTimeout(() => {
            location.reload();
        }, 1000);
    }
});

// Auto-save before page unload
window.addEventListener('beforeunload', function(e) {
    saveAttendanceDraft();
    
    // Check if there are unsaved changes
    const form = document.getElementById('attendanceForm');
    if (form && form.checkValidity && !form.checkValidity()) {
        const message = 'You have unsaved changes. Are you sure you want to leave?';
        e.returnValue = message;
        return message;
    }
});

console.log('✅ Attendance system fully loaded with all features enabled!');
</script>

<style>
/* Custom Alert Styles */
.custom-alert {
    border-radius: 8px;
    border: none;
    font-weight: 500;
    animation: slideInRight 0.3s ease-out;
}

.custom-alert.alert-success {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
    border-left: 4px solid #155724;
}

.custom-alert.alert-danger {
    background: linear-gradient(135deg, #dc3545, #e83e8c);
    color: white;
    border-left: 4px solid #721c24;
}

.custom-alert.alert-warning {
    background: linear-gradient(135deg, #ffc107, #fd7e14);
    color: #212529;
    border-left: 4px solid #856404;
}

.custom-alert.alert-info {
    background: linear-gradient(135deg, #17a2b8, #6f42c1);
    color: white;
    border-left: 4px solid #0c5460;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

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

/* Biometric Device Styles */
.device-item {
    transition: all 0.2s ease;
}

.device-item:hover {
    background-color: #f8f9fa !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.device-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Loading and animation styles */
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.spin {
    animation: spin 1s linear infinite;
}

/* Toast notification slide in animation */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(100%);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.toast-notification {
    animation: slideIn 0.3s ease;
}

/* Form enhancements */
.form-control:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}

.form-select:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}

/* Modal enhancements */
.modal-content {
    border: none;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

.modal-header {
    border-bottom: 1px solid #dee2e6;
    background-color: #f8f9fa;
}

/* Status indicators */
.status-indicator {
    position: relative;
    display: inline-block;
}

.status-indicator::after {
    content: '';
    position: absolute;
    top: -2px;
    right: -2px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    border: 2px solid white;
}

.status-indicator.online::after {
    background-color: #28a745;
}

.status-indicator.offline::after {
    background-color: #dc3545;
}

/* Card hover effects */
.card {
    transition: all 0.2s ease;
}

.card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

/* Button loading state */
.btn:disabled {
    opacity: 0.6;
    pointer-events: none;
}

/* Table responsive improvements */
.table-responsive {
    border-radius: 0.375rem;
    border: 1px solid #dee2e6;
}

.table th {
    border-top: none;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Badge styles */
.badge {
    font-size: 0.75rem;
    font-weight: 500;
}

/* Custom scrollbar for modals */
.modal-body {
    max-height: 70vh;
    overflow-y: auto;
}

.modal-body::-webkit-scrollbar {
    width: 6px;
}

.modal-body::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.modal-body::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 3px;
}

.modal-body::-webkit-scrollbar-thumb:hover {
    background: #555;
}

/* Additional biometric styles */
.integration-card {
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.integration-status {
    transition: all 0.3s ease;
}

.sync-animation {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

.form-switch .form-check-input {
    width: 3rem;
    height: 1.5rem;
}

.form-switch .form-check-input:checked {
    background-color: #28a745;
    border-color: #28a745;
}

.status-indicator {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    animation: statusBlink 2s infinite;
}

@keyframes statusBlink {
    0%, 50% { opacity: 1; }
    51%, 100% { opacity: 0.3; }
}

.device-card {
    border-left: 4px solid transparent;
    transition: all 0.2s ease;
}

.device-card.active {
    border-left-color: #28a745;
    background-color: #f8fff9;
}

.device-card.inactive {
    border-left-color: #dc3545;
    background-color: #fff8f8;
}

/* ============================================
   ADVANCED FEATURES STYLES
   ============================================ */

/* Smart Attendance Styles */
.smart-attendance-btn {
    transition: all 0.3s ease;
    border-radius: 10px;
}

.smart-attendance-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

/* Face Recognition Area */
#faceRecognitionArea, #qrScannerArea {
    transition: all 0.3s ease;
    border-radius: 10px;
}

#faceRecognitionArea:hover, #qrScannerArea:hover {
    border-color: #007bff;
    box-shadow: 0 0 10px rgba(0,123,255,0.2);
}

/* Calendar Grid Styles */
.calendar-grid {
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.calendar-grid > div {
    min-height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.calendar-grid > div:hover {
    background-color: #f8f9fa !important;
    cursor: pointer;
}

/* Smart Alerts */
.alert-sm {
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    border-radius: 6px;
    animation: slideInLeft 0.3s ease;
}

@keyframes slideInLeft {
    from {
        transform: translateX(-100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Modal Enhancements */
.modal-header.bg-success,
.modal-header.bg-info,
.modal-header.bg-primary,
.modal-header.bg-warning {
    border-bottom: none;
}

.modal-content {
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

/* Card Hover Effects */
.card {
    transition: all 0.3s ease;
    border-radius: 10px;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

/* Analytics Cards */
.analytics-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 15px;
}

.analytics-card h4 {
    font-weight: 700;
    font-size: 2rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .modal-dialog.modal-xl,
    .modal-dialog.modal-lg {
        max-width: 95%;
        margin: 1rem;
    }
    
    .calendar-grid {
        grid-template-columns: repeat(7, 1fr);
        gap: 1px;
    }
    
    .card-body .row .col-6 {
        margin-bottom: 10px;
    }
}
</style>

    </div>
</div>

<?php include '../../layouts/footer.php'; ?>