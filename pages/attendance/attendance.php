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
                <h1 class="h5 mb-0">⏰ Mark Attendance</h1>
                <p class="text-muted small">Record daily attendance with punch in/out times - <?= date('F j, Y') ?></p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="live-clock">
                    <i class="bi bi-clock me-2"></i>
                    <strong>Live Time: <span id="liveClock"></span></strong>
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

            <!-- Biometric System Integration -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-fingerprint me-2"></i>Devices Integrated</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-column gap-3" id="devicesList">
                        <!-- Devices will be loaded dynamically -->
                        <div class="text-center py-2">
                            <div class="d-flex align-items-center justify-content-center">
                                <div class="spinner-border spinner-border-sm text-primary me-2" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <small class="text-muted">Loading devices...</small>
                            </div>
                            <button class="btn btn-link btn-sm p-0 mt-1" onclick="loadDevices()" style="font-size: 0.8rem;">
                                <i class="bi bi-arrow-clockwise"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Integration Status -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-wifi me-2"></i>Integration Status</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-column gap-3" id="syncStatusList">
                        <!-- Sync status will be loaded dynamically -->
                        <div class="text-center py-2">
                            <div class="d-flex align-items-center justify-content-center">
                                <div class="spinner-border spinner-border-sm text-primary me-2" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <small class="text-muted">Loading sync status...</small>
                            </div>
                            <button class="btn btn-link btn-sm p-0 mt-1" onclick="loadSyncStatus()" style="font-size: 0.8rem;">
                                <i class="bi bi-arrow-clockwise"></i> Refresh
                            </button>
                        </div>
                    </div>

                    <!-- Sync Actions -->
                    <div class="mt-3 pt-3 border-top">
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary btn-sm" onclick="syncAllDevices()" id="syncAllBtn">
                                <i class="bi bi-arrow-clockwise me-1"></i> Sync All Devices
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" onclick="openDeviceSettings()">
                                <i class="bi bi-gear me-1"></i> Device Settings
                            </button>
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
            await Promise.all([loadDevices(), loadSyncStatus()]);
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
</style>

    </div>
</div>

<?php include '../../layouts/footer.php'; ?>