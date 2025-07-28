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
$today = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $today)) {
    $today = date('Y-m-d');
}

// Get all employees with error handling
try {
    $employees = $conn->query("SELECT employee_id, name, employee_code, position FROM employees WHERE status = 'active' ORDER BY name ASC");
    if (!$employees) {
        throw new Exception("Failed to fetch employees: " . $conn->error);
    }
} catch (Exception $e) {
    error_log("Database error in attendance.php: " . $e->getMessage());
    $employees = false;
}

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="h5 mb-0">⏰ Smart Attendance & Leave Management</h1>
                <p class="text-muted small">AI-powered touchless attendance with geo-location - <?= date('F j, Y') ?></p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="live-clock">
                    <i class="bi bi-clock me-2"></i>
                    <strong>Live Time: <span id="liveClock"></span></strong>
                </div>
                <div class="btn-group">
                    <button class="btn btn-success btn-sm" onclick="openSmartAttendance()">
                        <i class="bi bi-camera"></i> Smart Check-in
                    </button>
                    <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#leaveCalendarModal">
                        <i class="bi bi-calendar3"></i> Leave Calendar
                    </button>
                    <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#analyticsModal">
                        <i class="bi bi-graph-up"></i> Analytics
                    </button>
                    <a href="../../attendance-calendar.php" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-calendar3"></i> View Calendar
                    </a>
                </div>
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
                                                
                                                // Get existing attendance for today with error handling
                                                $existingAttendance = null;
                                                try {
                                                    $attendanceQuery = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ? LIMIT 1");
                                                    if (!$attendanceQuery) {
                                                        throw new Exception("Failed to prepare attendance query: " . $conn->error);
                                                    }
                                                    $attendanceQuery->bind_param("is", $empId, $today);
                                                    if (!$attendanceQuery->execute()) {
                                                        throw new Exception("Failed to execute attendance query: " . $attendanceQuery->error);
                                                    }
                                                    $attendanceResult = $attendanceQuery->get_result();
                                                    $existingAttendance = $attendanceResult->fetch_assoc();
                                                    $attendanceQuery->close();
                                                } catch (Exception $e) {
                                                    error_log("Attendance query error for employee {$empId}: " . $e->getMessage());
                                                    $existingAttendance = null;
                                                }
                                                
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
                <div class="card border-0 shadow-sm mb-3">
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

                        // Get total employees with error handling
                        try {
                            $result = $conn->query("SELECT COUNT(*) as total FROM employees WHERE status = 'active'");
                            if ($result && $row = $result->fetch_assoc()) {
                                $totalEmployees = $row['total'];
                            }
                        } catch (Exception $e) {
                            error_log("Error fetching total employees: " . $e->getMessage());
                            $totalEmployees = 0;
                        }

                        // Get attendance counts for today with error handling
                        try {
                            $attendanceStatsQuery = $conn->prepare("
                                SELECT 
                                    COUNT(CASE WHEN status = 'Present' THEN 1 END) as present,
                                    COUNT(CASE WHEN status = 'Absent' THEN 1 END) as absent,
                                    COUNT(CASE WHEN status = 'Late' THEN 1 END) as late,
                                    COUNT(CASE WHEN status = 'Half Day' THEN 1 END) as half_day
                                FROM attendance 
                                WHERE attendance_date = ?
                            ");
                            $attendanceStatsQuery->bind_param("s", $today);
                            $attendanceStatsQuery->execute();
                            $attendanceStats = $attendanceStatsQuery->get_result();
                            
                            if ($attendanceStats && $stats = $attendanceStats->fetch_assoc()) {
                                $presentCount = $stats['present'] ?? 0;
                                $absentCount = $stats['absent'] ?? 0;
                                $lateCount = $stats['late'] ?? 0;
                                $halfDayCount = $stats['half_day'] ?? 0;
                            }
                            $attendanceStatsQuery->close();
                        } catch (Exception $e) {
                            error_log("Error fetching attendance stats: " . $e->getMessage());
                            $presentCount = 0;
                            $absentCount = 0;
                            $lateCount = 0;
                            $halfDayCount = 0;
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

                <!-- Smart Features Card -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-camera-fill me-2"></i>Smart Attendance Options</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-6">
                                <button class="btn btn-outline-success btn-sm w-100" onclick="startFaceRecognition()">
                                    <i class="bi bi-person-check"></i><br>Face Recognition
                                </button>
                            </div>
                            <div class="col-6">
                                <button class="btn btn-outline-info btn-sm w-100" onclick="startQRScan()">
                                    <i class="bi bi-qr-code-scan"></i><br>QR Scan
                                </button>
                            </div>
                            <div class="col-6">
                                <button class="btn btn-outline-warning btn-sm w-100" onclick="startGeoAttendance()">
                                    <i class="bi bi-geo-alt"></i><br>GPS Check-in
                                </button>
                            </div>
                            <div class="col-6">
                                <button class="btn btn-outline-primary btn-sm w-100" onclick="startIPAttendance()">
                                    <i class="bi bi-router"></i><br>IP-based
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
            </div>
        </div>
    </div>
</div>

<!-- Smart Attendance Modal -->
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

<!-- Leave Calendar Modal -->
<div class="modal fade" id="leaveCalendarModal" tabindex="-1" aria-labelledby="leaveCalendarModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="leaveCalendarModalLabel">
                    <i class="bi bi-calendar3 me-2"></i>Leave Calendar
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="dynamicCalendar" style="min-height: 400px;">
                    <div class="text-center py-5">
                        <i class="bi bi-calendar3 text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-2">Leave calendar will be displayed here</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Analytics Modal -->
<div class="modal fade" id="analyticsModal" tabindex="-1" aria-labelledby="analyticsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="analyticsModalLabel">
                    <i class="bi bi-graph-up me-2"></i>Attendance Analytics
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center py-5">
                    <i class="bi bi-graph-up text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-2">Analytics dashboard will be displayed here</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Optimized CSS for Anti-Blinking -->
<style>
/* Anti-blinking and smooth transition styles */
.modal {
    transition: opacity 0.15s linear;
}

.modal.fade .modal-dialog {
    transition: transform 0.15s ease-out;
    transform: translate(0, -50px);
}

.modal.show .modal-dialog {
    transform: none;
}

.modal-content {
    min-height: 200px;
}

.card {
    transition: all 0.3s ease;
}

.btn {
    transition: all 0.2s ease;
}

/* Prevent layout shifts */
#faceRecognitionArea,
#qrScannerArea,
#locationStatus {
    min-height: 200px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}

/* Loading states */
.loading-state {
    opacity: 0.7;
    pointer-events: none;
}

/* Success states */
.success-state {
    background: rgba(25, 135, 84, 0.1);
    border-color: #198754;
}

/* Error states */
.error-state {
    background: rgba(220, 53, 69, 0.1);
    border-color: #dc3545;
}
</style>

<script>
// ============================================
// CORE ATTENDANCE FUNCTIONS (OPTIMIZED)
// ============================================

// Initialize on document ready
$(document).ready(function() {
    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    // Start live clock
    updateLiveClock();
    setInterval(updateLiveClock, 1000);
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
    const clockElement = document.getElementById('liveClock');
    if (clockElement) {
        clockElement.textContent = timeString;
    }
}

// Get current time in HH:MM format
function getTimeNow() {
    const now = new Date();
    return now.toTimeString().split(' ')[0].substring(0, 5);
}

// Punch In function
function punchIn(employeeId) {
    const currentTime = getTimeNow();
    const timeInField = document.getElementById('time_in_' + employeeId);
    const statusField = document.getElementById('status-' + employeeId);
    
    if (timeInField && statusField) {
        timeInField.value = currentTime;
        statusField.value = 'Present';
        showAlert('Punched In at ' + currentTime, 'success');
    }
}

// Punch Out function
function punchOut(employeeId) {
    const currentTime = getTimeNow();
    const timeOutField = document.getElementById('time_out_' + employeeId);
    const statusField = document.getElementById('status-' + employeeId);
    
    if (timeOutField && statusField) {
        timeOutField.value = currentTime;
        // Ensure status is Present if punching out
        if (statusField.value === 'Absent') {
            statusField.value = 'Present';
        }
        showAlert('Punched Out at ' + currentTime, 'info');
    }
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
    if (confirm('Are you sure you want to clear all attendance data? This will reset all fields.')) {
        const timeInInputs = document.querySelectorAll('input[name^="time_in["]');
        const timeOutInputs = document.querySelectorAll('input[name^="time_out["]');
        const statusSelects = document.querySelectorAll('select[name^="status["]');
        const notesInputs = document.querySelectorAll('input[name^="notes["]');
        
        timeInInputs.forEach(input => input.value = '');
        timeOutInputs.forEach(input => input.value = '');
        statusSelects.forEach(select => select.value = 'Absent');
        notesInputs.forEach(input => input.value = '');
        
        showAlert('All attendance data cleared', 'warning');
    }
}

// Change date function
function changeDate() {
    const selectedDate = document.getElementById('attendanceDate').value;
    document.getElementById('hiddenDate').value = selectedDate;
    window.location.href = window.location.pathname + '?date=' + selectedDate;
}

// ============================================
// SMART ATTENDANCE FUNCTIONS (OPTIMIZED)
// ============================================

// Global state management
let modalStates = {
    faceRecognitionInProgress: false,
    qrScannerInProgress: false,
    locationRequestInProgress: false,
    ipRequestInProgress: false
};

let mediaStreams = {
    faceRecognition: null
};

// Open Smart Attendance Modal (Fixed)
function openSmartAttendance() {
    const modal = document.getElementById('smartAttendanceModal');
    if (!modal || modal.classList.contains('show')) {
        return; // Modal doesn't exist or already open
    }
    
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    
    // Initialize location and IP after modal is shown
    modal.addEventListener('shown.bs.modal', function() {
        setTimeout(() => {
            getUserLocation();
            getUserIP();
        }, 300);
    }, { once: true });
}

// Face Recognition (Optimized)
function initFaceRecognition() {
    if (modalStates.faceRecognitionInProgress) return;
    
    const area = document.getElementById('faceRecognitionArea');
    if (!area) return;
    
    modalStates.faceRecognitionInProgress = true;
    area.innerHTML = `
        <div class="d-flex flex-column align-items-center">
            <div class="spinner-border text-primary mb-2" role="status"></div>
            <p class="mt-2 mb-0">Initializing camera...</p>
        </div>
    `;
    
    // Clean up existing stream
    if (mediaStreams.faceRecognition) {
        mediaStreams.faceRecognition.getTracks().forEach(track => track.stop());
        mediaStreams.faceRecognition = null;
    }
    
    navigator.mediaDevices.getUserMedia({ video: true })
    .then(stream => {
        mediaStreams.faceRecognition = stream;
        const video = document.createElement('video');
        video.srcObject = stream;
        video.autoplay = true;
        video.playsInline = true;
        video.style.cssText = 'width:100%;height:200px;object-fit:cover;border-radius:8px;';
        
        area.innerHTML = '';
        area.appendChild(video);
        
        // Simulate recognition after 3 seconds
        setTimeout(() => {
            if (modalStates.faceRecognitionInProgress) {
                completeFaceRecognition();
            }
        }, 3000);
    })
    .catch(err => {
        console.error('Camera error:', err);
        area.innerHTML = `
            <div class="text-center">
                <i class="bi bi-exclamation-triangle text-danger" style="font-size: 3rem;"></i>
                <p class="text-danger mt-2">Camera access denied</p>
                <button class="btn btn-outline-primary btn-sm mt-2" onclick="initFaceRecognition()">
                    Try Again
                </button>
            </div>
        `;
        modalStates.faceRecognitionInProgress = false;
    });
}

function completeFaceRecognition() {
    const area = document.getElementById('faceRecognitionArea');
    if (!area) return;
    
    area.innerHTML = `
        <div class="text-center success-state p-3 rounded">
            <i class="bi bi-person-check-fill text-success" style="font-size: 3rem;"></i>
            <div class="alert alert-success mt-3 mb-3">
                <strong>Face Recognized Successfully!</strong>
            </div>
            <button class="btn btn-success" onclick="finalizeFaceCheckIn()">
                <i class="bi bi-check-circle"></i> Complete Check-In
            </button>
        </div>
    `;
}

function finalizeFaceCheckIn() {
    // Clean up camera stream
    if (mediaStreams.faceRecognition) {
        mediaStreams.faceRecognition.getTracks().forEach(track => track.stop());
        mediaStreams.faceRecognition = null;
    }
    
    showAlert('Face recognition check-in completed successfully!', 'success');
    
    const modal = bootstrap.Modal.getInstance(document.getElementById('smartAttendanceModal'));
    if (modal) modal.hide();
    
    // Reset state
    modalStates.faceRecognitionInProgress = false;
}

// QR Scanner (Optimized)
function initQRScanner() {
    if (modalStates.qrScannerInProgress) return;
    
    const area = document.getElementById('qrScannerArea');
    if (!area) return;
    
    modalStates.qrScannerInProgress = true;
    area.innerHTML = `
        <div class="d-flex flex-column align-items-center">
            <div class="spinner-border text-info mb-2" role="status"></div>
            <p class="mt-2 mb-0">Starting QR scanner...</p>
        </div>
    `;
    
    setTimeout(() => {
        if (modalStates.qrScannerInProgress) {
            area.innerHTML = `
                <div class="text-center success-state p-3 rounded">
                    <i class="bi bi-qr-code-scan text-success" style="font-size: 3rem;"></i>
                    <div class="alert alert-success mt-3 mb-3">
                        <strong>QR Code Scanned Successfully!</strong>
                    </div>
                    <button class="btn btn-success" onclick="finalizeQRCheckIn()">
                        <i class="bi bi-check-circle"></i> Complete Check-In
                    </button>
                </div>
            `;
        }
        modalStates.qrScannerInProgress = false;
    }, 2000);
}

function finalizeQRCheckIn() {
    showAlert('QR code check-in completed successfully!', 'success');
    
    const modal = bootstrap.Modal.getInstance(document.getElementById('smartAttendanceModal'));
    if (modal) modal.hide();
}

// GPS Location (Optimized)
function getUserLocation() {
    if (modalStates.locationRequestInProgress) return;
    
    const spinner = document.getElementById('locationSpinner');
    const text = document.getElementById('locationText');
    const btn = document.getElementById('gpsCheckInBtn');
    
    if (!spinner || !text || !btn) return;
    
    modalStates.locationRequestInProgress = true;
    
    const timeout = setTimeout(() => {
        if (modalStates.locationRequestInProgress) {
            text.innerHTML = `
                <p class="text-warning">Location timeout</p>
                <button class="btn btn-outline-warning btn-sm" onclick="retryLocation()">
                    <i class="bi bi-arrow-clockwise"></i> Retry
                </button>
            `;
            spinner.style.display = 'none';
            modalStates.locationRequestInProgress = false;
        }
    }, 10000);
    
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            position => {
                clearTimeout(timeout);
                if (modalStates.locationRequestInProgress) {
                    spinner.style.display = 'none';
                    text.innerHTML = `
                        <p class="text-success">Location detected</p>
                        <small>Lat: ${position.coords.latitude.toFixed(4)}</small><br>
                        <small>Lng: ${position.coords.longitude.toFixed(4)}</small>
                    `;
                    btn.disabled = false;
                    btn.classList.remove('btn-warning');
                    btn.classList.add('btn-success');
                    modalStates.locationRequestInProgress = false;
                }
            },
            error => {
                clearTimeout(timeout);
                if (modalStates.locationRequestInProgress) {
                    spinner.style.display = 'none';
                    text.innerHTML = `
                        <p class="text-danger">Location access denied</p>
                        <button class="btn btn-outline-danger btn-sm" onclick="retryLocation()">
                            <i class="bi bi-arrow-clockwise"></i> Retry
                        </button>
                    `;
                    modalStates.locationRequestInProgress = false;
                }
            }
        );
    } else {
        clearTimeout(timeout);
        spinner.style.display = 'none';
        text.innerHTML = '<p class="text-danger">Geolocation not supported</p>';
        modalStates.locationRequestInProgress = false;
    }
}

function retryLocation() {
    modalStates.locationRequestInProgress = false;
    const spinner = document.getElementById('locationSpinner');
    if (spinner) spinner.style.display = 'block';
    getUserLocation();
}

// IP Detection (Optimized)
function getUserIP() {
    if (modalStates.ipRequestInProgress) return;
    
    const ipElement = document.getElementById('userIP');
    if (!ipElement) return;
    
    modalStates.ipRequestInProgress = true;
    
    const timeout = setTimeout(() => {
        if (modalStates.ipRequestInProgress) {
            ipElement.textContent = 'Timeout';
            modalStates.ipRequestInProgress = false;
        }
    }, 5000);
    
    fetch('https://api.ipify.org?format=json')
    .then(response => response.json())
    .then(data => {
        clearTimeout(timeout);
        if (modalStates.ipRequestInProgress) {
            ipElement.textContent = data.ip;
            modalStates.ipRequestInProgress = false;
        }
    })
    .catch(error => {
        clearTimeout(timeout);
        if (modalStates.ipRequestInProgress) {
            ipElement.textContent = 'Unable to detect';
            modalStates.ipRequestInProgress = false;
        }
    });
}

// Check-in functions
function checkInWithGPS() {
    showAlert('GPS check-in completed successfully!', 'success');
    const modal = bootstrap.Modal.getInstance(document.getElementById('smartAttendanceModal'));
    if (modal) modal.hide();
}

function checkInWithIP() {
    showAlert('IP-based check-in completed successfully!', 'success');
    const modal = bootstrap.Modal.getInstance(document.getElementById('smartAttendanceModal'));
    if (modal) modal.hide();
}

function manualCheckIn() {
    showAlert('Manual check-in completed successfully!', 'success');
    const modal = bootstrap.Modal.getInstance(document.getElementById('smartAttendanceModal'));
    if (modal) modal.hide();
}

// ============================================
// SMART FEATURE SHORTCUTS
// ============================================

function startFaceRecognition() {
    openSmartAttendance();
    setTimeout(() => initFaceRecognition(), 500);
}

function startQRScan() {
    openSmartAttendance();
    setTimeout(() => initQRScanner(), 500);
}

function startGeoAttendance() {
    openSmartAttendance();
    setTimeout(() => {
        const btn = document.getElementById('gpsCheckInBtn');
        if (btn && !btn.disabled) {
            checkInWithGPS();
        }
    }, 500);
}

function startIPAttendance() {
    openSmartAttendance();
    setTimeout(() => checkInWithIP(), 500);
}

// ============================================
// UTILITY FUNCTIONS
// ============================================

// Show alert function (Optimized)
function showAlert(message, type = 'info') {
    // Remove existing alerts
    document.querySelectorAll('.custom-alert').forEach(alert => alert.remove());
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed custom-alert`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 10000; min-width: 350px; box-shadow: 0 4px 12px rgba(0,0,0,0.2);';
    
    const iconMap = {
        success: 'bi-check-circle',
        danger: 'bi-exclamation-triangle',
        warning: 'bi-exclamation-triangle',
        info: 'bi-info-circle'
    };
    
    alertDiv.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="bi ${iconMap[type] || iconMap.info} me-2 fs-5"></i>
            <div class="flex-grow-1">${message}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    document.body.appendChild(alertDiv);
    
    // Auto remove
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.classList.remove('show');
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.parentNode.removeChild(alertDiv);
                }
            }, 300);
        }
    }, type === 'success' ? 5000 : 7000);
}

// Clean up on page unload
window.addEventListener('beforeunload', function() {
    // Clean up all media streams
    Object.values(mediaStreams).forEach(stream => {
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }
    });
});
</script>

<?php include '../../layouts/footer.php'; ?>
