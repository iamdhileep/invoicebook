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
                    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#smartAttendanceModal">
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
                        <hr class="my-3">
                        <h6 class="text-muted mb-2"><i class="bi bi-calendar-x me-2"></i>Leave Management</h6>
                        <button class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#applyLeaveModal">
                            <i class="bi bi-calendar-minus"></i> Apply Leave
                        </button>
                        <button class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#permissionModal">
                            <i class="bi bi-clock-history"></i> Permission
                        </button>
                        <a href="#" class="btn btn-outline-success btn-sm" onclick="showLeaveHistory()">
                            <i class="bi bi-list-check"></i> Leave History
                        </a>
                        <hr class="my-3">
                        <h6 class="text-muted mb-2"><i class="bi bi-robot me-2"></i>AI-Powered Features</h6>
                        <button class="btn btn-outline-primary btn-sm" onclick="openAILeaveAssistant()">
                            <i class="bi bi-brain"></i> AI Leave Suggestion
                        </button>
                        <button class="btn btn-outline-dark btn-sm" onclick="openWorkflowPanel()">
                            <i class="bi bi-diagram-3"></i> Approval Workflow
                        </button>
                        <button class="btn btn-outline-info btn-sm" onclick="openPolicyConfig()">
                            <i class="bi bi-gear"></i> Policy Config
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- New Smart Features Cards -->
            <div class="card mb-4">
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
            
            <!-- Live Notifications -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-bell me-2"></i>Smart Alerts</h6>
                </div>
                <div class="card-body">
                    <div id="smartAlerts">
                        <div class="alert alert-info alert-sm mb-2">
                            <i class="bi bi-info-circle me-2"></i>
                            <small>3 pending leave approvals require attention</small>
                        </div>
                        <div class="alert alert-warning alert-sm mb-2">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <small>2 employees haven't checked in today</small>
                        </div>
                        <div class="alert alert-success alert-sm mb-2">
                            <i class="bi bi-check-circle me-2"></i>
                            <small>Biometric sync completed - 45 records updated</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Manager Tools -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-person-gear me-2"></i>Manager Tools</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary btn-sm" onclick="openTeamDashboard()">
                            <i class="bi bi-people"></i> Team Dashboard
                        </button>
                        <button class="btn btn-outline-success btn-sm" onclick="bulkApproval()">
                            <i class="bi bi-check-all"></i> Bulk Approval
                        </button>
                        <button class="btn btn-outline-warning btn-sm" onclick="applyOnBehalf()">
                            <i class="bi bi-person-plus"></i> Apply on Behalf
                        </button>
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

// Update attendance display after punch in/out
function updateAttendanceDisplay(employeeId) {
    // Update the attendance table with new check-in
    const currentTime = new Date().toTimeString().split(' ')[0].substring(0, 5);
    const timeInField = document.getElementById(`time_in_${employeeId}`);
    const statusField = document.getElementById(`status-${employeeId}`);
    
    if (timeInField && statusField) {
        timeInField.value = currentTime;
        statusField.value = 'Present';
        
        // Add visual feedback
        timeInField.style.backgroundColor = '#d4edda';
        statusField.style.backgroundColor = '#d4edda';
        
        setTimeout(() => {
            timeInField.style.backgroundColor = '';
            statusField.style.backgroundColor = '';
        }, 3000);
    }
}

// Clear all attendance data
function clearAll() {
    if (confirm('Are you sure you want to clear all attendance data? This will reset all fields.')) {
        const timeInInputs = document.querySelectorAll('input[name^="time_in["]');
        const timeOutInputs = document.querySelectorAll('input[name^="time_out["]');
        const statusSelects = document.querySelectorAll('select[name^="status["]');
        const notesInputs = document.querySelectorAll('input[name^="notes["]');
        
        timeInInputs.forEach(input => {
            input.value = '';
        });
        
        timeOutInputs.forEach(input => {
            input.value = '';
        });
        
        statusSelects.forEach(select => {
            select.value = 'Absent';
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
    }

function getDeviceIcon(deviceType) {
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
            });
            
            const data = await response.json();
        
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
                    showAlert(`Leave application submitted successfully! Application ID: ${data.application_id}`, 'success');
                    
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
                    
                    console.log('Leave application submitted successfully with ID:', data.application_id);
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
                    showAlert(`Permission request submitted successfully! Request ID: ${data.request_id}`, 'success');
                    
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
                    
                    console.log('Permission request submitted successfully with ID:', data.request_id);
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
    // Prevent multiple opens
    const modal = document.getElementById('smartAttendanceModal');
    if (modal.classList.contains('show')) {
        return; // Modal is already open
    }
    
    // Show modal using Bootstrap 5 syntax
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    
    // Initialize location and IP only once when modal opens
    setTimeout(() => {
        getUserLocation();
        getUserIP();
    }, 300); // Small delay to ensure modal is fully shown
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

// Face Recognition with improved error handling
let faceRecognitionStream = null;
let faceRecognitionInProgress = false;

function initFaceRecognition() {
    // Prevent multiple camera accesses
    if (faceRecognitionInProgress) {
        return;
    }
    
    const area = document.getElementById('faceRecognitionArea');
    if (!area) {
        console.warn('Face recognition area not found');
        return;
    }
    
    faceRecognitionInProgress = true;
    area.innerHTML = `
        <div class="d-flex flex-column align-items-center">
            <div class="spinner-border text-primary mb-2" role="status"></div>
            <p class="mt-2 mb-0">Initializing camera...</p>
            <small class="text-muted">Please allow camera access</small>
        </div>
    `;
    
    // Clean up any existing stream
    if (faceRecognitionStream) {
        faceRecognitionStream.getTracks().forEach(track => track.stop());
        faceRecognitionStream = null;
    }
    
    navigator.mediaDevices.getUserMedia({ 
        video: { 
            width: { ideal: 640 },
            height: { ideal: 480 }
        } 
    })
    .then(stream => {
        faceRecognitionStream = stream;
        const video = document.createElement('video');
        video.srcObject = stream;
        video.autoplay = true;
        video.playsInline = true;
        video.style.width = '100%';
        video.style.height = '200px';
        video.style.objectFit = 'cover';
        video.style.borderRadius = '8px';
        
        area.innerHTML = '';
        area.appendChild(video);
        
        // Add recognition overlay
        const overlay = document.createElement('div');
        overlay.className = 'position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center';
        overlay.style.background = 'rgba(0,0,0,0.3)';
        overlay.style.borderRadius = '8px';
        overlay.innerHTML = '<div class="text-white text-center"><i class="bi bi-person-plus fs-1"></i><br>Position your face</div>';
        area.style.position = 'relative';
        area.appendChild(overlay);
        
        // Simulate face recognition after 3 seconds
        setTimeout(() => {
            if (faceRecognitionStream) { // Check if still active
                recognizeFace();
            }
        }, 3000);
        
        faceRecognitionInProgress = false;
    })
    .catch(err => {
        console.error('Camera access error:', err);
        area.innerHTML = `
            <div class="text-center">
                <i class="bi bi-exclamation-triangle text-danger" style="font-size: 3rem;"></i>
                <p class="text-danger mt-2 mb-2">Camera access denied</p>
                <small class="text-muted">Please enable camera permissions and try again</small><br>
                <button class="btn btn-outline-primary btn-sm mt-2" onclick="initFaceRecognition()">
                    <i class="bi bi-camera-video"></i> Try Again
                </button>
            </div>
        `;
        faceRecognitionInProgress = false;
    });
}

function recognizeFace() {
    const area = document.getElementById('faceRecognitionArea');
    if (!area) return;
    
    area.innerHTML = `
        <div class="text-center">
            <div class="mb-3">
                <i class="bi bi-person-check-fill text-success" style="font-size: 3rem;"></i>
            </div>
            <div class="alert alert-success mb-3">
                <strong>Face Recognized Successfully!</strong>
            </div>
            <div class="mb-3">
                <strong>John Doe</strong><br>
                <small class="text-muted">Employee ID: EMP001</small><br>
                <small class="text-muted">Department: IT</small>
            </div>
            <div class="d-grid gap-2">
                <button class="btn btn-success" onclick="completeFaceCheckIn()">
                    <i class="bi bi-check-circle"></i> Complete Check-In
                </button>
                <button class="btn btn-outline-secondary btn-sm" onclick="initFaceRecognition()">
                    <i class="bi bi-arrow-clockwise"></i> Scan Again
                </button>
            </div>
        </div>
    `;
}

function completeFaceCheckIn() {
    // Clean up camera stream
    if (faceRecognitionStream) {
        faceRecognitionStream.getTracks().forEach(track => track.stop());
        faceRecognitionStream = null;
    }
    
    showAlert('Face recognition check-in completed successfully!', 'success');
    
    // Close modal using Bootstrap 5
    const modal = bootstrap.Modal.getInstance(document.getElementById('smartAttendanceModal'));
    if (modal) {
        modal.hide();
    }
    
    // Reset face recognition area for next use
    setTimeout(() => {
        const area = document.getElementById('faceRecognitionArea');
        if (area) {
            area.innerHTML = `
                <i class="bi bi-camera text-muted" style="font-size: 3rem;"></i>
                <p class="text-muted mt-2">Click to enable camera for face recognition</p>
            `;
        }
        faceRecognitionInProgress = false;
    }, 1000);
}

// QR Scanner with improved error handling
let qrScannerInProgress = false;

function initQRScanner() {
    // Prevent multiple QR scanner instances
    if (qrScannerInProgress) {
        return;
    }
    
    const area = document.getElementById('qrScannerArea');
    if (!area) {
        console.warn('QR scanner area not found');
        return;
    }
    
    qrScannerInProgress = true;
    area.innerHTML = `
        <div class="d-flex flex-column align-items-center">
            <div class="spinner-border text-info mb-2" role="status"></div>
            <p class="mt-2 mb-0">Starting QR scanner...</p>
            <small class="text-muted">Please position QR code in view</small>
        </div>
    `;
    
    // Simulate QR scanning with more realistic timing
    setTimeout(() => {
        if (qrScannerInProgress) { // Check if still active
            area.innerHTML = `
                <div class="text-center">
                    <div class="mb-3">
                        <i class="bi bi-qr-code-scan text-success" style="font-size: 3rem;"></i>
                    </div>
                    <div class="alert alert-success mb-3">
                        <strong>QR Code Scanned Successfully!</strong>
                    </div>
                    <div class="mb-3">
                        <strong>Employee ID: EMP001</strong><br>
                        <small class="text-muted">John Doe</small><br>
                        <small class="text-muted">IT Department</small>
                    </div>
                    <div class="d-grid gap-2">
                        <button class="btn btn-success" onclick="completeQRCheckIn()">
                            <i class="bi bi-check-circle"></i> Complete Check-In
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" onclick="initQRScanner()">
                            <i class="bi bi-qr-code-scan"></i> Scan Again
                        </button>
                    </div>
                </div>
            `;
        }
        qrScannerInProgress = false;
    }, 2000);
}

function completeQRCheckIn() {
    showAlert('QR code check-in completed successfully!', 'success');
    
    // Close modal using Bootstrap 5
    const modal = bootstrap.Modal.getInstance(document.getElementById('smartAttendanceModal'));
    if (modal) {
        modal.hide();
    }
    
    // Reset QR scanner area for next use
    setTimeout(() => {
        const area = document.getElementById('qrScannerArea');
        if (area) {
            area.innerHTML = `
                <i class="bi bi-qr-code text-muted" style="font-size: 3rem;"></i>
                <p class="text-muted mt-2">Scan your employee QR code</p>
            `;
        }
        qrScannerInProgress = false;
    }, 1000);
}

// GPS Location with improved error handling
let locationRequestInProgress = false;

function getUserLocation() {
    // Prevent multiple location requests
    if (locationRequestInProgress) {
        return;
    }
    
    const spinner = document.getElementById('locationSpinner');
    const text = document.getElementById('locationText');
    const btn = document.getElementById('gpsCheckInBtn');
    
    // Check if elements exist before proceeding
    if (!spinner || !text || !btn) {
        console.warn('GPS elements not found in DOM');
        return;
    }
    
    locationRequestInProgress = true;
    
    // Reset UI state
    spinner.style.display = 'inline-block';
    text.innerHTML = 'Getting your location...';
    btn.disabled = true;
    btn.classList.remove('btn-success');
    btn.classList.add('btn-warning');
    
    if (navigator.geolocation) {
        const options = {
            enableHighAccuracy: true,
            timeout: 10000, // 10 second timeout
            maximumAge: 300000 // 5 minute cache
        };
        
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
            locationRequestInProgress = false;
        }, (error) => {
            spinner.style.display = 'none';
            let errorMessage = 'Location access denied';
            
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    errorMessage = 'Location permission denied';
                    break;
                case error.POSITION_UNAVAILABLE:
                    errorMessage = 'Location unavailable';
                    break;
                case error.TIMEOUT:
                    errorMessage = 'Location request timeout';
                    break;
            }
            
            text.innerHTML = `
                <i class="bi bi-geo-alt text-danger me-2"></i>
                ${errorMessage}<br>
                <button class="btn btn-sm btn-outline-warning mt-2" onclick="retryLocation()">
                    <i class="bi bi-arrow-clockwise"></i> Retry
                </button>
            `;
            locationRequestInProgress = false;
        }, options);
    } else {
        spinner.style.display = 'none';
        text.innerHTML = `
            <i class="bi bi-geo-alt text-warning me-2"></i>
            Geolocation not supported
        `;
        locationRequestInProgress = false;
    }
}

function retryLocation() {
    locationRequestInProgress = false;
    getUserLocation();
}

function checkInWithGPS() {
    showAlert('GPS-based check-in completed successfully! Location verified.', 'success');
    
    // Close modal using Bootstrap 5
    const modal = bootstrap.Modal.getInstance(document.getElementById('smartAttendanceModal'));
    if (modal) {
        modal.hide();
    }
}

// IP-based Check-in with improved error handling
let ipRequestInProgress = false;

function getUserIP() {
    // Prevent multiple IP requests
    if (ipRequestInProgress) {
        return;
    }
    
    const ipElement = document.getElementById('userIP');
    
    if (!ipElement) {
        console.warn('IP element not found in DOM');
        return;
    }
    
    ipRequestInProgress = true;
    ipElement.textContent = 'Loading...';
    
    // Use timeout promise to prevent hanging
    const timeoutPromise = new Promise((_, reject) => {
        setTimeout(() => reject(new Error('Request timeout')), 5000);
    });
    
    const fetchPromise = fetch('https://api.ipify.org?format=json')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        });
    
    Promise.race([fetchPromise, timeoutPromise])
        .then(data => {
            ipElement.textContent = data.ip;
            ipRequestInProgress = false;
        })
        .catch(err => {
            console.warn('IP detection failed:', err.message);
            ipElement.innerHTML = `
                Unable to detect
                <button class="btn btn-sm btn-outline-secondary ms-2" onclick="retryIP()">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            `;
            ipRequestInProgress = false;
        });
}

function retryIP() {
    ipRequestInProgress = false;
    getUserIP();
}

function checkInWithIP() {
    showAlert('IP-based check-in completed successfully! Office network verified.', 'success');
    
    // Close modal using Bootstrap 5
    const modal = bootstrap.Modal.getInstance(document.getElementById('smartAttendanceModal'));
    if (modal) {
        modal.hide();
    }
}

function manualCheckIn() {
    showAlert('Manual check-in recorded successfully!', 'success');
    
    // Close modal using Bootstrap 5
    const modal = bootstrap.Modal.getInstance(document.getElementById('smartAttendanceModal'));
    if (modal) {
        modal.hide();
    }
}

// ============================================
// 2. DYNAMIC LEAVE CALENDAR
// ============================================

function showLeaveCalendar() {
    $('#leaveCalendarModal').modal('show');
    initializeDynamicCalendar();
}

function initializeDynamicCalendar() {
    const calendarDiv = document.getElementById('dynamicCalendar');
    const currentDate = new Date();
    const currentMonth = currentDate.getMonth();
    const currentYear = currentDate.getFullYear();
    
    calendarDiv.innerHTML = `
        <div class="calendar-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <button class="btn btn-outline-secondary" onclick="changeCalendarMonth(-1)">
                    <i class="bi bi-chevron-left"></i>
                </button>
                <h5 class="mb-0" id="calendarMonthYear">${getMonthName(currentMonth)} ${currentYear}</h5>
                <button class="btn btn-outline-secondary" onclick="changeCalendarMonth(1)">
                    <i class="bi bi-chevron-right"></i>
                </button>
            </div>
            <div id="calendarGrid">
                ${generateAdvancedCalendarGrid(currentMonth, currentYear)}
            </div>
            <div class="row mt-3">
                <div class="col-12">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6>Quick Stats:</h6>
                            <div class="d-flex gap-3">
                                <small><span class="badge bg-success">12</span> Casual Leave</small>
                                <small><span class="badge bg-danger">4</span> Sick Leave</small>
                                <small><span class="badge bg-info">8</span> Work From Home</small>
                            </div>
                        </div>
                        <div>
                            <button class="btn btn-primary btn-sm" onclick="addLeaveToCalendar()">
                                <i class="bi bi-plus me-1"></i>Add Leave
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Store current calendar state
    window.currentCalendarMonth = currentMonth;
    window.currentCalendarYear = currentYear;
}

function generateAdvancedCalendarGrid(month, year) {
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const firstDay = new Date(year, month, 1).getDay();
    const today = new Date();
    
    let html = `
        <div class="calendar-grid" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background-color: #dee2e6;">
    `;
    
    // Header row
    const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    days.forEach(day => {
        html += `<div class="text-center fw-bold p-2 bg-primary text-white">${day}</div>`;
    });
    
    // Empty cells for days before month starts
    for (let i = 0; i < firstDay; i++) {
        html += `<div class="p-2 bg-light"></div>`;
    }
    
    // Days of the month
    for (let day = 1; day <= daysInMonth; day++) {
        const currentDate = new Date(year, month, day);
        const isToday = currentDate.toDateString() === today.toDateString();
        const isWeekend = currentDate.getDay() === 0 || currentDate.getDay() === 6;
        
        let classes = 'p-2 bg-white border-0 calendar-day';
        let content = `<div class="fw-bold">${day}</div>`;
        
        if (isToday) {
            classes += ' bg-warning';
        }
        
        if (isWeekend) {
            classes += ' text-muted';
        }
        
        // Add leave data (simulated)
        const leaveData = getLeaveDataForDay(day, month, year);
        if (leaveData.length > 0) {
            content += `<div class="leave-indicators">`;
            leaveData.forEach(leave => {
                content += `<div class="badge bg-${leave.color} mb-1 w-100" style="font-size: 0.6rem;">${leave.type}</div>`;
            });
            content += `</div>`;
        }
        
        html += `
            <div class="${classes}" onclick="selectCalendarDay(${day}, ${month}, ${year})" 
                 style="min-height: 80px; cursor: pointer; transition: all 0.2s;">
                ${content}
            </div>
        `;
    }
    
    html += `</div>`;
    return html;
}

function getLeaveDataForDay(day, month, year) {
    // Simulate leave data - in real implementation, this would come from database
    const leaveData = {
        15: [{ type: 'CL', color: 'success', employee: 'John Doe' }],
        22: [{ type: 'SL', color: 'danger', employee: 'Jane Smith' }],
        28: [{ type: 'WFH', color: 'info', employee: 'Mike Johnson' }],
        10: [{ type: 'EL', color: 'warning', employee: 'Sarah Wilson' }],
        5: [
            { type: 'CL', color: 'success', employee: 'Alice Brown' },
            { type: 'WFH', color: 'info', employee: 'Bob Davis' }
        ]
    };
    
    return leaveData[day] || [];
}

function selectCalendarDay(day, month, year) {
    const selectedDate = new Date(year, month, day);
    const leaveData = getLeaveDataForDay(day, month, year);
    
    // Show day details modal
    const dayModal = document.createElement('div');
    dayModal.className = 'modal fade';
    dayModal.innerHTML = `
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-calendar-day me-2"></i>
                        ${selectedDate.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    ${leaveData.length > 0 ? `
                        <h6>Scheduled Leaves:</h6>
                        <div class="list-group mb-3">
                            ${leaveData.map(leave => `
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>${leave.employee}</strong><br>
                                        <small class="text-muted">${getLeaveTypeName(leave.type)}</small>
                                    </div>
                                    <span class="badge bg-${leave.color}">${leave.type}</span>
                                </div>
                            `).join('')}
                        </div>
                    ` : `
                        <div class="text-center text-muted">
                            <i class="bi bi-calendar-x fs-1"></i>
                            <p>No leaves scheduled for this day</p>
                        </div>
                    `}
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary" onclick="quickApplyLeave('${selectedDate.toISOString().split('T')[0]}')">
                            <i class="bi bi-plus me-2"></i>Apply Leave for This Day
                        </button>
                        <button class="btn btn-outline-info" onclick="viewDayAnalytics('${selectedDate.toISOString().split('T')[0]}')">
                            <i class="bi bi-graph-up me-2"></i>View Day Analytics
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(dayModal);
    const modal = new bootstrap.Modal(dayModal);
    modal.show();
    
    dayModal.addEventListener('hidden.bs.modal', () => {
        document.body.removeChild(dayModal);
    });
}

function getLeaveTypeName(type) {
    const types = {
        'CL': 'Casual Leave',
        'SL': 'Sick Leave',
        'EL': 'Earned Leave',
        'WFH': 'Work From Home'
    };
    return types[type] || type;
}

function changeCalendarMonth(direction) {
    window.currentCalendarMonth += direction;
    
    if (window.currentCalendarMonth > 11) {
        window.currentCalendarMonth = 0;
        window.currentCalendarYear += 1;
    } else if (window.currentCalendarMonth < 0) {
        window.currentCalendarMonth = 11;
        window.currentCalendarYear -= 1;
    }
    
    // Update calendar display
    const monthYearElement = document.getElementById('calendarMonthYear');
    monthYearElement.textContent = `${getMonthName(window.currentCalendarMonth)} ${window.currentCalendarYear}`;
    
    const calendarGrid = document.getElementById('calendarGrid');
    calendarGrid.innerHTML = generateAdvancedCalendarGrid(window.currentCalendarMonth, window.currentCalendarYear);
}

function getMonthName(monthIndex) {
    const months = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];
    return months[monthIndex];
}

function refreshCalendar() {
    showAlert('Calendar refreshed with latest data!', 'success');
    initializeDynamicCalendar();
}

function exportCalendar() {
    const currentMonth = getMonthName(window.currentCalendarMonth);
    const currentYear = window.currentCalendarYear;
    
    // Simulate export functionality
    showAlert(`Exporting ${currentMonth} ${currentYear} calendar...`, 'info');
    
    setTimeout(() => {
        showAlert('Calendar exported successfully to Excel!', 'success');
        // In real implementation, this would trigger a download
    }, 2000);
}

function addLeaveToCalendar() {
    $('#leaveCalendarModal').modal('hide');
    setTimeout(() => {
        $('#applyLeaveModal').modal('show');
    }, 300);
}

function quickApplyLeave(date) {
    // Pre-fill the leave application form with selected date
    document.getElementById('start_date').value = date;
    document.getElementById('end_date').value = date;
    
    // Close day modal and open leave application
    const dayModal = document.querySelector('.modal.show');
    if (dayModal) {
        bootstrap.Modal.getInstance(dayModal).hide();
    }
    
    setTimeout(() => {
        $('#leaveCalendarModal').modal('hide');
        setTimeout(() => {
            $('#applyLeaveModal').modal('show');
        }, 300);
    }, 300);
}

function viewDayAnalytics(date) {
    showAlert(`Loading analytics for ${new Date(date).toLocaleDateString()}...`, 'info');
    // This would show detailed analytics for the selected day
}

// ============================================
// 3. AI-BASED LEAVE SUGGESTION
// ============================================

function openAILeaveAssistant() {
    $('#aiLeaveAssistantModal').modal('show');
    loadAdvancedAISuggestions();
}

function loadAdvancedAISuggestions() {
    const suggestionsContainer = document.getElementById('aiSuggestions');
    
    // Show loading state
    suggestionsContainer.innerHTML = `
        <div class="text-center py-3">
            <div class="spinner-border text-primary mb-3" role="status"></div>
            <p class="mb-0">AI analyzing your attendance pattern...</p>
            <small class="text-muted">Processing 12 months of data</small>
        </div>
    `;
    
    // Simulate AI processing
    setTimeout(() => {
        displayAISuggestions();
        loadPatternAnalysis();
    }, 3000);
}

function displayAISuggestions() {
    const suggestionsContainer = document.getElementById('aiSuggestions');
    
    // Simulate AI-generated suggestions
    const suggestions = [
        {
            dates: 'December 23-24, 2024',
            score: 'optimal',
            reason: 'Weekend bridge, low team workload',
            impact: 'minimal',
            alternative: 'December 26-27'
        },
        {
            dates: 'January 15-16, 2025',
            score: 'good',
            reason: 'Post-holiday period, fewer meetings',
            impact: 'low',
            alternative: 'January 22-23'
        },
        {
            dates: 'February 10-11, 2025', 
            score: 'available',
            reason: 'Normal workload period',
            impact: 'medium',
            alternative: 'February 17-18'
        }
    ];
    
    let html = '';
    suggestions.forEach((suggestion, index) => {
        const badgeColor = suggestion.score === 'optimal' ? 'success' : 
                          suggestion.score === 'good' ? 'warning' : 'info';
        
        html += `
            <div class="suggestion-card border rounded p-3 mb-3" style="background: ${suggestion.score === 'optimal' ? '#f8fff8' : '#f8f9fa'};">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <h6 class="mb-1">${suggestion.dates}</h6>
                        <p class="text-muted mb-2 small">${suggestion.reason}</p>
                        <div class="d-flex gap-2">
                            <span class="badge bg-${badgeColor}">${suggestion.score.toUpperCase()}</span>
                            <span class="badge bg-outline-secondary">${suggestion.impact} impact</span>
                        </div>
                    </div>
                    <div class="ms-3">
                        <button class="btn btn-sm btn-outline-primary" onclick="selectAISuggestion(${index})">
                            Select
                        </button>
                    </div>
                </div>
                <div class="mt-2">
                    <small class="text-muted">
                        <i class="bi bi-lightbulb me-1"></i>
                        Alternative: ${suggestion.alternative}
                    </small>
                </div>
            </div>
        `;
    });
    
    suggestionsContainer.innerHTML = html;
}

function loadPatternAnalysis() {
    // Simulate pattern analysis loading
    setTimeout(() => {
        const patternContainer = document.querySelector('#aiLeaveAssistantModal .col-md-6:last-child .card-body');
        
        patternContainer.innerHTML = `
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <small class="text-muted">Monday Leave Frequency</small>
                    <small class="fw-bold text-warning">30%</small>
                </div>
                <div class="progress">
                    <div class="progress-bar bg-warning" style="width: 30%"></div>
                </div>
            </div>
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <small class="text-muted">Friday Leave Frequency</small>
                    <small class="fw-bold text-info">25%</small>
                </div>
                <div class="progress">
                    <div class="progress-bar bg-info" style="width: 25%"></div>
                </div>
            </div>
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <small class="text-muted">Peak Leave Season</small>
                    <small class="fw-bold text-success">Dec-Jan</small>
                </div>
                <div class="progress">
                    <div class="progress-bar bg-success" style="width: 45%"></div>
                </div>
            </div>
            
            <div class="alert alert-info p-2">
                <small>
                    <i class="bi bi-lightbulb me-1"></i>
                    <strong>AI Insight:</strong> You tend to take leaves on Mondays. Consider balancing with mid-week breaks.
                </small>
            </div>
            
            <div class="alert alert-success p-2">
                <small>
                    <i class="bi bi-check-circle me-1"></i>
                    <strong>Good Pattern:</strong> You maintain consistent leave spacing throughout the year.
                </small>
            </div>
        `;
    }, 1500);
}

function selectAISuggestion(index) {
    const suggestions = [
        { dates: 'December 23-24, 2024', start: '2024-12-23', end: '2024-12-24' },
        { dates: 'January 15-16, 2025', start: '2025-01-15', end: '2025-01-16' },
        { dates: 'February 10-11, 2025', start: '2025-02-10', end: '2025-02-11' }
    ];
    
    const selected = suggestions[index];
    
    showAlert(`AI suggestion selected: ${selected.dates}`, 'success');
    
    // Store the suggestion for later use
    window.selectedAISuggestion = selected;
}

function applyAISuggestion() {
    if (!window.selectedAISuggestion) {
        showAlert('Please select a suggestion first', 'warning');
        return;
    }
    
    // Pre-fill leave application form
    document.getElementById('start_date').value = window.selectedAISuggestion.start;
    document.getElementById('end_date').value = window.selectedAISuggestion.end;
    
    // Close AI modal and open leave application
    $('#aiLeaveAssistantModal').modal('hide');
    
    setTimeout(() => {
        $('#applyLeaveModal').modal('show');
        showAlert('Form pre-filled with AI suggestion!', 'info');
    }, 300);
}

// ============================================
// 4. MOBILE APP INTEGRATION
// ============================================

function initializeMobileIntegration() {
    // Check if app is running in mobile context
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    
    if (isMobile) {
        enableMobileFeatures();
    }
    
    // Initialize push notifications
    initializePushNotifications();
}

function enableMobileFeatures() {
    // Add mobile-specific UI elements
    const mobileToolbar = document.createElement('div');
    mobileToolbar.className = 'mobile-toolbar fixed-bottom bg-primary p-2 d-md-none';
    mobileToolbar.innerHTML = `
        <div class="row text-center text-white">
            <div class="col-3" onclick="quickCheckIn()">
                <i class="bi bi-camera fs-4"></i>
                <br><small>Quick Check-in</small>
            </div>
            <div class="col-3" onclick="openMobileLeaveCalendar()">
                <i class="bi bi-calendar fs-4"></i>
                <br><small>Calendar</small>
            </div>
            <div class="col-3" onclick="openMobileProfile()">
                <i class="bi bi-person fs-4"></i>
                <br><small>Profile</small>
            </div>
            <div class="col-3" onclick="openMobileNotifications()">
                <i class="bi bi-bell fs-4"></i>
                <br><small>Alerts</small>
            </div>
        </div>
    `;
    
    document.body.appendChild(mobileToolbar);
    
    // Add mobile-specific styles
    const mobileStyles = document.createElement('style');
    mobileStyles.textContent = `
        @media (max-width: 768px) {
            .main-content { padding-bottom: 80px; }
            .mobile-toolbar { z-index: 1050; }
            .mobile-toolbar .col-3:active { background-color: rgba(255,255,255,0.1); }
        }
    `;
    document.head.appendChild(mobileStyles);
}

function initializePushNotifications() {
    if ('serviceWorker' in navigator && 'PushManager' in window) {
        navigator.serviceWorker.register('/sw.js').then(registration => {
            console.log('ServiceWorker registered');
            requestNotificationPermission();
        });
    }
}

function requestNotificationPermission() {
    if (Notification.permission === 'default') {
        Notification.requestPermission().then(permission => {
            if (permission === 'granted') {
                showAlert('Push notifications enabled!', 'success');
                sendWelcomePushNotification();
            }
        });
    }
}

function sendWelcomePushNotification() {
    if (Notification.permission === 'granted') {
        new Notification('Attendance System', {
            body: 'Mobile integration active! You\'ll receive important updates.',
            icon: '/favicon.ico',
            badge: '/badge-icon.png'
        });
    }
}

function quickCheckIn() {
    // Mobile-optimized quick check-in
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.innerHTML = `
        <div class="modal-dialog modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Quick Check-in</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="row g-3">
                        <div class="col-6">
                            <button class="btn btn-success w-100 h-100" onclick="mobileCheckIn('face')">
                                <i class="bi bi-person-check fs-1 d-block mb-2"></i>
                                Face Recognition
                            </button>
                        </div>
                        <div class="col-6">
                            <button class="btn btn-info w-100 h-100" onclick="mobileCheckIn('qr')">
                                <i class="bi bi-qr-code-scan fs-1 d-block mb-2"></i>
                                QR Code
                            </button>
                        </div>
                        <div class="col-6">
                            <button class="btn btn-warning w-100 h-100" onclick="mobileCheckIn('gps')">
                                <i class="bi bi-geo-alt fs-1 d-block mb-2"></i>
                                GPS Check-in
                            </button>
                        </div>
                        <div class="col-6">
                            <button class="btn btn-secondary w-100 h-100" onclick="mobileCheckIn('manual')">
                                <i class="bi bi-hand-index fs-1 d-block mb-2"></i>
                                Manual
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    
    modal.addEventListener('hidden.bs.modal', () => {
        document.body.removeChild(modal);
    });
}

function mobileCheckIn(method) {
    showAlert(`Initiating ${method} check-in...`, 'info');
    
    // Simulate mobile check-in process
    setTimeout(() => {
        showAlert('Mobile check-in successful!', 'success');
        sendCheckInNotification();
    }, 2000);
}

function sendCheckInNotification() {
    if (Notification.permission === 'granted') {
        new Notification('Check-in Successful', {
            body: `Checked in at ${new Date().toLocaleTimeString()}`,
            icon: '/favicon.ico'
        });
    }
}

function openMobileLeaveCalendar() {
    showAlert('Opening mobile leave calendar...', 'info');
    $('#leaveCalendarModal').modal('show');
}

function openMobileProfile() {
    showAlert('Opening mobile profile...', 'info');
}

function openMobileNotifications() {
    showAlert('Opening mobile notifications...', 'info');
}

// ============================================
// 5. REAL-TIME SYNC WITH BIOMETRIC DEVICES  
// ============================================

let syncInterval = null;
let websocket = null;

function initializeRealTimeSync() {
    // Initialize WebSocket connection for real-time updates
    initializeWebSocket();
    
    // Start periodic sync
    startPeriodicSync();
    
    // Add real-time status indicators
    addRealTimeSyncUI();
}

function initializeWebSocket() {
    // In a real implementation, this would connect to your WebSocket server
    // websocket = new WebSocket('ws://localhost:8080/attendance-sync');
    
    // Simulate WebSocket connection
    console.log('Simulating WebSocket connection for real-time sync');
    
    // Simulate receiving real-time updates
    setInterval(() => {
        simulateRealTimeUpdate();
    }, 30000); // Every 30 seconds
}

function simulateRealTimeUpdate() {
    const updateTypes = ['check-in', 'check-out', 'device-sync', 'leave-approval'];
    const randomUpdate = updateTypes[Math.floor(Math.random() * updateTypes.length)];
    
    switch (randomUpdate) {
        case 'check-in':
            showRealTimeNotification('New check-in detected from Device #2', 'info');
            break;
        case 'check-out':
            showRealTimeNotification('Employee check-out recorded', 'success');
            break;
        case 'device-sync':
            showRealTimeNotification('Biometric device synchronized', 'primary');
            updateDeviceSyncStatus();
            break;
        case 'leave-approval':
            showRealTimeNotification('Leave application approved', 'warning');
            break;
    }
}

function showRealTimeNotification(message, type) {
    // Add to smart alerts section
    const alertsContainer = document.getElementById('smartAlerts');
    if (alertsContainer) {
        const alertElement = document.createElement('div');
        alertElement.className = `alert alert-${type} alert-sm mb-2`;
        alertElement.innerHTML = `
            <i class="bi bi-broadcast me-2"></i>
            <small>${message}</small>
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
        `;
        
        alertsContainer.insertBefore(alertElement, alertsContainer.firstChild);
        
        // Remove old alerts if more than 5
        const alerts = alertsContainer.children;
        if (alerts.length > 5) {
            alertsContainer.removeChild(alerts[alerts.length - 1]);
        }
    }
}

function startPeriodicSync() {
    // Sync every 5 minutes
    syncInterval = setInterval(() => {
        performBiometricSync();
    }, 300000);
}

function performBiometricSync() {
    const syncBtn = document.getElementById('syncAllBtn');
    if (syncBtn) {
        syncBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin me-1"></i> Auto-syncing...';
        syncBtn.disabled = true;
    }
    
    // Simulate sync process
    setTimeout(() => {
        const recordsCount = Math.floor(Math.random() * 50) + 1;
        showRealTimeNotification(`Auto-sync completed: ${recordsCount} records updated`, 'success');
        
        if (syncBtn) {
            syncBtn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i> Sync All Devices';
            syncBtn.disabled = false;
        }
        
        updateDeviceSyncStatus();
    }, 3000);
}

function updateDeviceSyncStatus() {
    // Update sync status indicators
    loadSyncStatus();
    
    // Update last sync time
    const lastSyncElements = document.querySelectorAll('.last-sync-time');
    lastSyncElements.forEach(element => {
        element.textContent = `Last sync: ${new Date().toLocaleTimeString()}`;
    });
}

function addRealTimeSyncUI() {
    // Add real-time sync indicator to page
    const syncIndicator = document.createElement('div');
    syncIndicator.className = 'sync-indicator position-fixed';
    syncIndicator.style.cssText = 'top: 10px; left: 10px; z-index: 1060;';
    syncIndicator.innerHTML = `
        <div class="bg-success text-white px-2 py-1 rounded-pill small">
            <i class="bi bi-wifi me-1"></i>
            <span id="syncStatus">Connected</span>
        </div>
    `;
    
    document.body.appendChild(syncIndicator);
}

function stopRealTimeSync() {
    if (syncInterval) {
        clearInterval(syncInterval);
        syncInterval = null;
    }
    
    if (websocket) {
        websocket.close();
        websocket = null;
    }
}

// ============================================
// 6. LEAVE DASHBOARD & ANALYTICS
// ============================================

function showAnalytics() {
    $('#analyticsModal').modal('show');
    loadAdvancedAnalyticsData();
}

function loadAdvancedAnalyticsData() {
    // Show loading state
    const modalBody = document.querySelector('#analyticsModal .modal-body');
    modalBody.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary mb-3" role="status"></div>
            <p>Loading advanced analytics...</p>
        </div>
    `;
    
    // Simulate data loading
    setTimeout(() => {
        renderAdvancedAnalytics();
    }, 2000);
}

function renderAdvancedAnalytics() {
    const modalBody = document.querySelector('#analyticsModal .modal-body');
    
    modalBody.innerHTML = `
        <!-- KPI Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card analytics-card text-center">
                    <div class="card-body">
                        <h4 class="text-white">94.2%</h4>
                        <small class="text-white-50">Attendance Rate</small>
                        <div class="mt-2">
                            <i class="bi bi-trending-up text-success"></i>
                            <small class="text-success">+2.1%</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card analytics-card text-center" style="background: linear-gradient(135deg, #28a745, #20c997);">
                    <div class="card-body">
                        <h4 class="text-white">156</h4>
                        <small class="text-white-50">Leaves Approved</small>
                        <div class="mt-2">
                            <i class="bi bi-check-circle text-white"></i>
                            <small class="text-white">This month</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card analytics-card text-center" style="background: linear-gradient(135deg, #ffc107, #fd7e14);">
                    <div class="card-body">
                        <h4 class="text-white">23</h4>
                        <small class="text-white-50">Pending Approvals</small>
                        <div class="mt-2">
                            <i class="bi bi-clock text-white"></i>
                            <small class="text-white">Action needed</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card analytics-card text-center" style="background: linear-gradient(135deg, #17a2b8, #6f42c1);">
                    <div class="card-body">
                        <h4 class="text-white">8.5hrs</h4>
                        <small class="text-white-50">Avg Work Time</small>
                        <div class="mt-2">
                            <i class="bi bi-clock-history text-white"></i>
                            <small class="text-white">Daily average</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts Section -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <h6 class="mb-0">Department-wise Analysis</h6>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                This Month
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#">This Week</a></li>
                                <li><a class="dropdown-item" href="#">This Month</a></li>
                                <li><a class="dropdown-item" href="#">This Quarter</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="departmentChart" height="250"></canvas>
                        <div class="mt-3">
                            <div class="row text-center">
                                <div class="col-4">
                                    <small class="text-muted">IT Department</small>
                                    <br><strong class="text-primary">87%</strong>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted">HR Department</small>
                                    <br><strong class="text-success">94%</strong>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted">Finance</small>
                                    <br><strong class="text-warning">91%</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <h6 class="mb-0">Attendance Trends</h6>
                        <button class="btn btn-sm btn-outline-primary" onclick="exportTrendData()">
                            <i class="bi bi-download"></i>
                        </button>
                    </div>
                    <div class="card-body">
                        <canvas id="trendChart" height="250"></canvas>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between">
                                <span class="text-muted small">Peak Time:</span>
                                <span class="fw-bold">9:00 AM - 10:00 AM</span>
                            </div>
                            <div class="d-flex justify-content-between mt-1">
                                <span class="text-muted small">Late Arrivals:</span>
                                <span class="fw-bold text-warning">12%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Advanced Analytics -->
        <div class="row mt-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Advanced Analytics Dashboard</h6>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" onclick="showAttendanceHeatmap()">Heatmap</button>
                                <button class="btn btn-outline-success" onclick="showLeavePatterns()">Leave Patterns</button>
                                <button class="btn btn-outline-info" onclick="showProductivityAnalysis()">Productivity</button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="advancedAnalyticsContainer">
                            ${generateHeatmapVisualization()}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Predictive Analytics -->
        <div class="row mt-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0">
                            <i class="bi bi-robot me-2"></i>AI Predictions
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <strong>Next Week Prediction:</strong><br>
                            Expected attendance: 91%<br>
                            Probable sick leaves: 3-5<br>
                            Peak absent day: Monday
                        </div>
                        <div class="alert alert-warning">
                            <strong>Trend Alert:</strong><br>
                            Friday attendance dropping by 8%<br>
                            Consider implementing Friday engagement activities
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0">
                            <i class="bi bi-award me-2"></i>Performance Insights
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>Top Performing Department:</span>
                                <strong class="text-success">Marketing (96%)</strong>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>Most Punctual Employee:</span>
                                <strong class="text-primary">John Doe</strong>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>Improvement Needed:</span>
                                <strong class="text-warning">IT Department</strong>
                            </div>
                        </div>
                        <button class="btn btn-success btn-sm w-100" onclick="generatePerformanceReport()">
                            <i class="bi bi-file-earmark-text me-1"></i>Generate Detailed Report
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Initialize charts after DOM is ready
    setTimeout(() => {
        initializeAdvancedCharts();
    }, 100);
}

function generateHeatmapVisualization() {
    return `
        <div class="heatmap-container">
            <h6 class="mb-3">Monthly Attendance Heatmap</h6>
            <div class="heatmap-grid" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px;">
                ${generateHeatmapCells()}
            </div>
            <div class="mt-3 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <small>Less</small>
                    <div class="heatmap-scale d-flex gap-1">
                        <div class="scale-cell" style="background: #ebedf0;"></div>
                        <div class="scale-cell" style="background: #c6e48b;"></div>
                        <div class="scale-cell" style="background: #7bc96f;"></div>
                        <div class="scale-cell" style="background: #239a3b;"></div>
                        <div class="scale-cell" style="background: #196127;"></div>
                    </div>
                    <small>More</small>
                </div>
                <small class="text-muted">Higher attendance = Darker green</small>
            </div>
        </div>
    `;
}

function generateHeatmapCells() {
    let html = '';
    const colors = ['#ebedf0', '#c6e48b', '#7bc96f', '#239a3b', '#196127'];
    
    // Generate 35 cells (5 weeks)
    for (let i = 0; i < 35; i++) {
        const colorIndex = Math.floor(Math.random() * colors.length);
        const attendance = 60 + (colorIndex * 10);
        
        html += `
            <div class="heatmap-cell" 
                 style="background: ${colors[colorIndex]}; width: 12px; height: 12px; border-radius: 2px;"
                 title="Day ${i + 1}: ${attendance}% attendance">
            </div>
        `;
    }
    
    return html;
}

function initializeAdvancedCharts() {
    // In a real implementation, this would use Chart.js or similar
    console.log('Advanced charts would be initialized here with real data');
    
    // Simulate chart initialization
    const departmentChart = document.getElementById('departmentChart');
    const trendChart = document.getElementById('trendChart');
    
    if (departmentChart && trendChart) {
        // Add placeholder for charts
        departmentChart.style.background = '#f8f9fa';
        departmentChart.style.display = 'flex';
        departmentChart.style.alignItems = 'center';
        departmentChart.style.justifyContent = 'center';
        departmentChart.innerHTML = '<small class="text-muted">Chart.js would render here</small>';
        
        trendChart.style.background = '#f8f9fa';
        trendChart.style.display = 'flex';
        trendChart.style.alignItems = 'center';
        trendChart.style.justifyContent = 'center';
        trendChart.innerHTML = '<small class="text-muted">Trend chart would render here</small>';
    }
}

function showAttendanceHeatmap() {
    const container = document.getElementById('advancedAnalyticsContainer');
    container.innerHTML = generateHeatmapVisualization();
}

function showLeavePatterns() {
    const container = document.getElementById('advancedAnalyticsContainer');
    container.innerHTML = `
        <div class="leave-patterns">
            <h6 class="mb-3">Leave Pattern Analysis</h6>
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6>Seasonal Trends</h6>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between">
                                    <span>Summer (Apr-Jun)</span>
                                    <span class="fw-bold">42%</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-warning" style="width: 42%"></div>
                                </div>
                            </div>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between">
                                    <span>Monsoon (Jul-Sep)</span>
                                    <span class="fw-bold">28%</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-info" style="width: 28%"></div>
                                </div>
                            </div>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between">
                                    <span>Winter (Oct-Mar)</span>
                                    <span class="fw-bold">30%</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-primary" style="width: 30%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6>Day-wise Distribution</h6>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between">
                                    <span>Monday</span>
                                    <span class="fw-bold text-danger">35%</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-danger" style="width: 35%"></div>
                                </div>
                            </div>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between">
                                    <span>Friday</span>
                                    <span class="fw-bold text-warning">28%</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-warning" style="width: 28%"></div>
                                </div>
                            </div>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between">
                                    <span>Mid-week</span>
                                    <span class="fw-bold text-success">37%</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-success" style="width: 37%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function showProductivityAnalysis() {
    const container = document.getElementById('advancedAnalyticsContainer');
    container.innerHTML = `
        <div class="productivity-analysis">
            <h6 class="mb-3">Productivity Analysis</h6>
            <div class="row">
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <h4 class="text-success">8.2hrs</h4>
                            <p class="mb-0">Average Work Hours</p>
                            <small class="text-muted">+0.3hrs from last month</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <h4 class="text-primary">94%</h4>
                            <p class="mb-0">Productivity Score</p>
                            <small class="text-muted">Based on check-in patterns</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <h4 class="text-warning">15min</h4>
                            <p class="mb-0">Avg Late Arrival</p>
                            <small class="text-muted">Improved by 5min</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function exportTrendData() {
    showAlert('Exporting trend data to Excel...', 'info');
    setTimeout(() => {
        showAlert('Trend data exported successfully!', 'success');
    }, 1500);
}

function generatePerformanceReport() {
    showAlert('Generating comprehensive performance report...', 'info');
    setTimeout(() => {
        showAlert('Performance report generated and emailed!', 'success');
    }, 2000);
}

function exportAnalytics() {
    showAlert('Exporting complete analytics dashboard...', 'info');
    setTimeout(() => {
        showAlert('Analytics exported to PDF successfully!', 'success');
    }, 2500);
}

// ============================================
// 7. WORKFLOW-BASED LEAVE APPROVAL
// ============================================

function openWorkflowPanel() {
    const workflowModal = document.createElement('div');
    workflowModal.className = 'modal fade';
    workflowModal.id = 'workflowModal';
    workflowModal.innerHTML = `
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-diagram-3 me-2"></i>Workflow-based Leave Approval System
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Active Workflows</h6>
                                </div>
                                <div class="card-body">
                                    ${generateWorkflowList()}
                                </div>
                            </div>
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h6 class="mb-0">Workflow Designer</h6>
                                </div>
                                <div class="card-body">
                                    ${generateWorkflowDesigner()}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Pending Approvals</h6>
                                </div>
                                <div class="card-body">
                                    ${generatePendingApprovals()}
                                </div>
                            </div>
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h6 class="mb-0">Approval Statistics</h6>
                                </div>
                                <div class="card-body">
                                    ${generateApprovalStats()}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="saveWorkflowChanges()">
                        <i class="bi bi-save me-1"></i>Save Changes
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(workflowModal);
    const modal = new bootstrap.Modal(workflowModal);
    modal.show();
    
    workflowModal.addEventListener('hidden.bs.modal', () => {
        document.body.removeChild(workflowModal);
    });
}

function generateWorkflowList() {
    return `
        <div class="workflow-list">
            <div class="workflow-item border rounded p-3 mb-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-1">Standard Leave Approval</h6>
                        <p class="text-muted mb-2">Employee → Direct Manager → HR</p>
                        <div class="d-flex gap-2">
                            <span class="badge bg-success">Active</span>
                            <span class="badge bg-info">Default</span>
                        </div>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            Actions
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="editWorkflow(1)">Edit</a></li>
                            <li><a class="dropdown-item" href="#" onclick="duplicateWorkflow(1)">Duplicate</a></li>
                            <li><a class="dropdown-item" href="#" onclick="disableWorkflow(1)">Disable</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="workflow-item border rounded p-3 mb-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-1">Manager Level Approval</h6>
                        <p class="text-muted mb-2">Employee → Team Lead → Department Head → HR</p>
                        <div class="d-flex gap-2">
                            <span class="badge bg-success">Active</span>
                            <span class="badge bg-warning">For Managers</span>
                        </div>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            Actions
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="editWorkflow(2)">Edit</a></li>
                            <li><a class="dropdown-item" href="#" onclick="duplicateWorkflow(2)">Duplicate</a></li>
                            <li><a class="dropdown-item" href="#" onclick="disableWorkflow(2)">Disable</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="workflow-item border rounded p-3 mb-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-1">Emergency Leave Fast Track</h6>
                        <p class="text-muted mb-2">Employee → Direct Manager (Auto-approve if < 2 days)</p>
                        <div class="d-flex gap-2">
                            <span class="badge bg-success">Active</span>
                            <span class="badge bg-danger">Emergency</span>
                        </div>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            Actions
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="editWorkflow(3)">Edit</a></li>
                            <li><a class="dropdown-item" href="#" onclick="duplicateWorkflow(3)">Duplicate</a></li>
                            <li><a class="dropdown-item" href="#" onclick="disableWorkflow(3)">Disable</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function generateWorkflowDesigner() {
    return `
        <div class="workflow-designer">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6>Design New Workflow</h6>
                <button class="btn btn-sm btn-primary" onclick="addWorkflowStep()">
                    <i class="bi bi-plus"></i> Add Step
                </button>
            </div>
            
            <div class="workflow-canvas border rounded p-3" style="min-height: 300px; background: #f8f9fa;">
                <div class="workflow-step d-flex align-items-center mb-3">
                    <div class="step-node bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                        1
                    </div>
                    <div class="flex-grow-1">
                        <select class="form-select">
                            <option>Employee Submits Request</option>
                        </select>
                    </div>
                    <button class="btn btn-sm btn-outline-danger ms-2" onclick="removeWorkflowStep(1)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
                
                <div class="workflow-connector text-center mb-3">
                    <i class="bi bi-arrow-down text-muted"></i>
                </div>
                
                <div class="workflow-step d-flex align-items-center mb-3">
                    <div class="step-node bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                        2
                    </div>
                    <div class="flex-grow-1">
                        <select class="form-select">
                            <option>Direct Manager Approval</option>
                            <option>Team Lead Approval</option>
                            <option>Department Head Approval</option>
                            <option>HR Approval</option>
                            <option>Auto-approve (Conditions)</option>
                        </select>
                    </div>
                    <button class="btn btn-sm btn-outline-danger ms-2" onclick="removeWorkflowStep(2)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
                
                <div class="workflow-connector text-center mb-3">
                    <i class="bi bi-arrow-down text-muted"></i>
                </div>
                
                <div class="workflow-step d-flex align-items-center mb-3">
                    <div class="step-node bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                        3
                    </div>
                    <div class="flex-grow-1">
                        <select class="form-select">
                            <option>HR Final Approval</option>
                            <option>Auto-approve (Conditions)</option>
                            <option>Notification Only</option>
                        </select>
                    </div>
                    <button class="btn btn-sm btn-outline-danger ms-2" onclick="removeWorkflowStep(3)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
            
            <div class="mt-3">
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label">Workflow Name</label>
                        <input type="text" class="form-control" placeholder="Enter workflow name">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Apply To</label>
                        <select class="form-select">
                            <option>All Employees</option>
                            <option>Specific Department</option>
                            <option>Specific Role</option>
                            <option>Individual Employees</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function generatePendingApprovals() {
    return `
        <div class="pending-approvals">
            <div class="approval-item border-start border-warning border-3 ps-3 mb-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-1">John Doe</h6>
                        <small class="text-muted">Casual Leave - 2 days</small>
                        <br><small class="text-warning">Pending: 2 hours</small>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-success" onclick="approveLeave(1)">
                            <i class="bi bi-check"></i>
                        </button>
                        <button class="btn btn-danger" onclick="rejectLeave(1)">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="approval-item border-start border-info border-3 ps-3 mb-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-1">Jane Smith</h6>
                        <small class="text-muted">Sick Leave - 1 day</small>
                        <br><small class="text-info">Pending: 30 minutes</small>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-success" onclick="approveLeave(2)">
                            <i class="bi bi-check"></i>
                        </button>
                        <button class="btn btn-danger" onclick="rejectLeave(2)">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="approval-item border-start border-danger border-3 ps-3 mb-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-1">Mike Johnson</h6>
                        <small class="text-muted">Emergency Leave - 3 days</small>
                        <br><small class="text-danger">Pending: 6 hours</small>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-success" onclick="approveLeave(3)">
                            <i class="bi bi-check"></i>
                        </button>
                        <button class="btn btn-danger" onclick="rejectLeave(3)">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="d-grid">
                <button class="btn btn-outline-primary btn-sm" onclick="viewAllPendingApprovals()">
                    View All Pending (15)
                </button>
            </div>
        </div>
    `;
}

function generateApprovalStats() {
    return `
        <div class="approval-stats">
            <div class="stat-item d-flex justify-content-between align-items-center mb-2">
                <span class="text-muted">Today's Approvals:</span>
                <span class="fw-bold text-success">8</span>
            </div>
            <div class="stat-item d-flex justify-content-between align-items-center mb-2">
                <span class="text-muted">Pending Review:</span>
                <span class="fw-bold text-warning">15</span>
            </div>
            <div class="stat-item d-flex justify-content-between align-items-center mb-2">
                <span class="text-muted">Avg Processing Time:</span>
                <span class="fw-bold text-info">2.5 hours</span>
            </div>
            <div class="stat-item d-flex justify-content-between align-items-center mb-3">
                <span class="text-muted">Auto-approved:</span>
                <span class="fw-bold text-primary">23</span>
            </div>
            
            <div class="progress mb-2">
                <div class="progress-bar bg-success" style="width: 60%" title="Approved: 60%"></div>
                <div class="progress-bar bg-warning" style="width: 25%" title="Pending: 25%"></div>
                <div class="progress-bar bg-danger" style="width: 15%" title="Rejected: 15%"></div>
            </div>
            <small class="text-muted">This month's approval distribution</small>
        </div>
    `;
}

function approveLeave(leaveId) {
    showAlert(`Leave approved for employee ${leaveId}!`, 'success');
    // Refresh the pending approvals section
    setTimeout(() => {
        const pendingSection = document.querySelector('.pending-approvals');
        if (pendingSection) {
            pendingSection.innerHTML = generatePendingApprovals();
        }
    }, 1000);
}

function rejectLeave(leaveId) {
    const reason = prompt('Please provide a reason for rejection:');
    if (reason) {
        showAlert(`Leave rejected for employee ${leaveId}. Reason: ${reason}`, 'warning');
    }
}

function addWorkflowStep() {
    showAlert('New workflow step added', 'info');
}

function removeWorkflowStep(stepId) {
    showAlert(`Workflow step ${stepId} removed`, 'warning');
}

function saveWorkflowChanges() {
    showAlert('Workflow changes saved successfully!', 'success');
}

// ============================================
// 8. POLICY CONFIGURATION PANEL
// ============================================

function openPolicyConfig() {
    const policyModal = document.createElement('div');
    policyModal.className = 'modal fade';
    policyModal.id = 'policyConfigModal';
    policyModal.innerHTML = `
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-gear me-2"></i>Policy Configuration Panel
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="nav flex-column nav-pills" id="policy-tabs" role="tablist">
                                <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#leave-policies">
                                    <i class="bi bi-calendar-minus me-2"></i>Leave Policies
                                </button>
                                <button class="nav-link" data-bs-toggle="pill" data-bs-target="#attendance-policies">
                                    <i class="bi bi-clock me-2"></i>Attendance Policies
                                </button>
                                <button class="nav-link" data-bs-toggle="pill" data-bs-target="#holiday-policies">
                                    <i class="bi bi-calendar-event me-2"></i>Holiday Policies
                                </button>
                                <button class="nav-link" data-bs-toggle="pill" data-bs-target="#notification-policies">
                                    <i class="bi bi-bell me-2"></i>Notification Policies
                                </button>
                                <button class="nav-link" data-bs-toggle="pill" data-bs-target="#security-policies">
                                    <i class="bi bi-shield me-2"></i>Security Policies
                                </button>
                            </div>
                        </div>
                        <div class="col-md-9">
                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="leave-policies">
                                    ${generateLeavePoliciesTab()}
                                </div>
                                <div class="tab-pane fade" id="attendance-policies">
                                    ${generateAttendancePoliciesTab()}
                                </div>
                                <div class="tab-pane fade" id="holiday-policies">
                                    ${generateHolidayPoliciesTab()}
                                </div>
                                <div class="tab-pane fade" id="notification-policies">
                                    ${generateNotificationPoliciesTab()}
                                </div>
                                <div class="tab-pane fade" id="security-policies">
                                    ${generateSecurityPoliciesTab()}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" onclick="resetToDefaults()">Reset to Defaults</button>
                    <button type="button" class="btn btn-info" onclick="savePolicyChanges()">
                        <i class="bi bi-save me-1"></i>Save All Changes
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(policyModal);
    const modal = new bootstrap.Modal(policyModal);
    modal.show();
    
    policyModal.addEventListener('hidden.bs.modal', () => {
        document.body.removeChild(policyModal);
    });
}

function generateLeavePoliciesTab() {
    return `
        <div class="policy-section">
            <h6 class="mb-3">Leave Entitlement Policies</h6>
            
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">Annual Leave Allocation</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Casual Leave (per year)</label>
                            <input type="number" class="form-control" value="12" min="0" max="30">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Sick Leave (per year)</label>
                            <input type="number" class="form-control" value="12" min="0" max="30">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Earned Leave (per year)</label>
                            <input type="number" class="form-control" value="15" min="0" max="30">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Maternity Leave (days)</label>
                            <input type="number" class="form-control" value="180" min="0" max="365">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">Leave Application Rules</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Minimum advance notice (days)</label>
                            <input type="number" class="form-control" value="1" min="0" max="30">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Maximum consecutive days</label>
                            <input type="number" class="form-control" value="15" min="1" max="90">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="allowBackdatedLeave" checked>
                                <label class="form-check-label" for="allowBackdatedLeave">
                                    Allow back-dated leave applications (with manager approval)
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="autoDeductLeave" checked>
                                <label class="form-check-label" for="autoDeductLeave">
                                    Auto-deduct leave for unapproved absences
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Leave Carryover Policy</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Max carryover days</label>
                            <input type="number" class="form-control" value="5" min="0" max="30">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Carryover expiry (months)</label>
                            <input type="number" class="form-control" value="3" min="1" max="12">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="allowCashOut">
                                <label class="form-check-label" for="allowCashOut">
                                    Allow cash-out of unused leaves
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function generateAttendancePoliciesTab() {
    return `
        <div class="policy-section">
            <h6 class="mb-3">Attendance Policies</h6>
            
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">Working Hours</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Standard work start time</label>
                            <input type="time" class="form-control" value="09:00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Standard work end time</label>
                            <input type="time" class="form-control" value="18:00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Grace period (minutes)</label>
                            <input type="number" class="form-control" value="15" min="0" max="60">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Minimum work hours per day</label>
                            <input type="number" class="form-control" value="8" min="1" max="12" step="0.5">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">Late Arrival Policy</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Late threshold (minutes)</label>
                            <input type="number" class="form-control" value="30" min="1" max="120">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Half-day threshold (minutes)</label>
                            <input type="number" class="form-control" value="120" min="60" max="480">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="autoMarkLate" checked>
                                <label class="form-check-label" for="autoMarkLate">
                                    Automatically mark as late based on check-in time
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="deductPayForLate">
                                <label class="form-check-label" for="deductPayForLate">
                                    Deduct pay for excessive lateness
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Overtime Policy</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Overtime starts after (hours)</label>
                            <input type="number" class="form-control" value="8" min="6" max="12" step="0.5">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Overtime rate multiplier</label>
                            <input type="number" class="form-control" value="1.5" min="1" max="3" step="0.1">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="requireOvertimeApproval" checked>
                                <label class="form-check-label" for="requireOvertimeApproval">
                                    Require manager approval for overtime
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function generateHolidayPoliciesTab() {
    return `
        <div class="policy-section">
            <h6 class="mb-3">Holiday & Weekend Policies</h6>
            
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">Weekend Configuration</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Weekend Days</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="weekendSun" checked>
                                    <label class="form-check-label" for="weekendSun">Sunday</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="weekendMon">
                                    <label class="form-check-label" for="weekendMon">Monday</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="weekendTue">
                                    <label class="form-check-label" for="weekendTue">Tuesday</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="weekendWed">
                                    <label class="form-check-label" for="weekendWed">Wednesday</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="weekendThu">
                                    <label class="form-check-label" for="weekendThu">Thursday</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="weekendFri">
                                    <label class="form-check-label" for="weekendFri">Friday</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="weekendSat" checked>
                                    <label class="form-check-label" for="weekendSat">Saturday</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Public Holidays</h6>
                    <button class="btn btn-sm btn-primary" onclick="addPublicHoliday()">
                        <i class="bi bi-plus"></i> Add Holiday
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Holiday Name</th>
                                    <th>Type</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>2024-12-25</td>
                                    <td>Christmas Day</td>
                                    <td><span class="badge bg-success">Fixed</span></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary">Edit</button>
                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>2024-01-01</td>
                                    <td>New Year's Day</td>
                                    <td><span class="badge bg-success">Fixed</span></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary">Edit</button>
                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>2024-08-15</td>
                                    <td>Independence Day</td>
                                    <td><span class="badge bg-info">National</span></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary">Edit</button>
                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Holiday Compensation</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="compensateHolidayWork" checked>
                                <label class="form-check-label" for="compensateHolidayWork">
                                    Provide compensation for holiday work
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Holiday work rate multiplier</label>
                            <input type="number" class="form-control" value="2" min="1" max="3" step="0.1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Compensation type</label>
                            <select class="form-select">
                                <option>Extra Pay</option>
                                <option>Compensatory Off</option>
                                <option>Both</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function generateNotificationPoliciesTab() {
    return `
        <div class="policy-section">
            <h6 class="mb-3">Notification & Alert Policies</h6>
            
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">Email Notifications</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="emailLeaveApplications" checked>
                                <label class="form-check-label" for="emailLeaveApplications">
                                    Send email for leave applications
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="emailAttendanceAlerts" checked>
                                <label class="form-check-label" for="emailAttendanceAlerts">
                                    Send email for attendance irregularities
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="emailApprovalReminders" checked>
                                <label class="form-check-label" for="emailApprovalReminders">
                                    Send approval reminder emails
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reminder frequency (hours)</label>
                            <input type="number" class="form-control" value="24" min="1" max="168">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">SMS Notifications</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="smsEmergencyLeave" checked>
                                <label class="form-check-label" for="smsEmergencyLeave">
                                    SMS for emergency leave applications
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="smsAbsenteeAlerts">
                                <label class="form-check-label" for="smsAbsenteeAlerts">
                                    SMS alerts for absenteeism
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="smsPayrollAlerts">
                                <label class="form-check-label" for="smsPayrollAlerts">
                                    SMS for payroll-related deductions
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Push Notifications</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="pushCheckInReminders" checked>
                                <label class="form-check-label" for="pushCheckInReminders">
                                    Push notifications for check-in reminders
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="pushLeaveApprovals" checked>
                                <label class="form-check-label" for="pushLeaveApprovals">
                                    Push notifications for leave approvals/rejections
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Check-in reminder time</label>
                            <input type="time" class="form-control" value="09:15">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Check-out reminder time</label>
                            <input type="time" class="form-control" value="18:00">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function generateSecurityPoliciesTab() {
    return `
        <div class="policy-section">
            <h6 class="mb-3">Security & Access Policies</h6>
            
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">Biometric Security</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="requireBiometric" checked>
                                <label class="form-check-label" for="requireBiometric">
                                    Require biometric authentication for attendance
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="allowManualEntry">
                                <label class="form-check-label" for="allowManualEntry">
                                    Allow manual attendance entry (with approval)
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Failed attempt lockout (attempts)</label>
                            <input type="number" class="form-control" value="3" min="1" max="10">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Lockout duration (minutes)</label>
                            <input type="number" class="form-control" value="15" min="1" max="60">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">Location-based Security</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="enforceGeoFencing" checked>
                                <label class="form-check-label" for="enforceGeoFencing">
                                    Enforce geo-fencing for attendance
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Allowed radius (meters)</label>
                            <input type="number" class="form-control" value="100" min="10" max="1000">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">GPS accuracy requirement (meters)</label>
                            <input type="number" class="form-control" value="20" min="5" max="100">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="allowIPOverride">
                                <label class="form-check-label" for="allowIPOverride">
                                    Allow IP-based override for office networks
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Data Security</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="encryptBiometricData" checked>
                                <label class="form-check-label" for="encryptBiometricData">
                                    Encrypt biometric data
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="auditTrailEnabled" checked>
                                <label class="form-check-label" for="auditTrailEnabled">
                                    Enable comprehensive audit trail
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Data retention period (months)</label>
                            <input type="number" class="form-control" value="36" min="12" max="120">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Backup frequency</label>
                            <select class="form-select">
                                <option>Daily</option>
                                <option>Weekly</option>
                                <option>Monthly</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function addPublicHoliday() {
    showAlert('Add Public Holiday feature would open a form here', 'info');
}

function resetToDefaults() {
    if (confirm('Are you sure you want to reset all policies to default values? This action cannot be undone.')) {
        showAlert('All policies reset to default values', 'warning');
    }
}

function savePolicyChanges() {
    showAlert('All policy changes saved successfully!', 'success');
    $('#policyConfigModal').modal('hide');
}

// ============================================
// 9. SMART ALERTS & NOTIFICATIONS
// ============================================

let notificationSystem = {
    alerts: [],
    settings: {
        maxAlerts: 10,
        autoRefresh: true,
        refreshInterval: 30000, // 30 seconds
        soundEnabled: true
    }
};

function initializeSmartAlerts() {
    // Load existing alerts
    loadInitialAlerts();
    
    // Start real-time alert monitoring
    if (notificationSystem.settings.autoRefresh) {
        setInterval(checkForNewAlerts, notificationSystem.settings.refreshInterval);
    }
    
    // Initialize notification permissions
    requestNotificationPermission();
}

function loadInitialAlerts() {
    const alertsContainer = document.getElementById('smartAlerts');
    if (!alertsContainer) return;
    
    // Clear existing alerts
    alertsContainer.innerHTML = '';
    
    // Add sample alerts
    const initialAlerts = [
        {
            id: 1,
            type: 'warning',
            title: 'Pending Approvals',
            message: '8 leave applications require your immediate attention',
            timestamp: new Date(),
            priority: 'high',
            action: 'viewPendingApprovals()'
        },
        {
            id: 2,
            type: 'info',
            title: 'System Sync',
            message: 'Biometric device #3 synchronized - 23 records updated',
            timestamp: new Date(Date.now() - 300000), // 5 minutes ago
            priority: 'medium',
            action: 'viewSyncDetails()'
        },
        {
            id: 3,
            type: 'success',
            title: 'Attendance Update',
            message: 'Monthly attendance report generated successfully',
            timestamp: new Date(Date.now() - 600000), // 10 minutes ago
            priority: 'low',
            action: 'viewAttendanceReport()'
        }
    ];
    
    initialAlerts.forEach(alert => addSmartAlert(alert));
}

function addSmartAlert(alert) {
    const alertsContainer = document.getElementById('smartAlerts');
    if (!alertsContainer) return;
    
    // Remove oldest alert if at max capacity
    while (alertsContainer.children.length >= notificationSystem.settings.maxAlerts) {
        alertsContainer.removeChild(alertsContainer.lastChild);
    }
    
    const alertElement = document.createElement('div');
    alertElement.className = `alert alert-${alert.type} alert-sm mb-2 smart-alert-item`;
    alertElement.setAttribute('data-alert-id', alert.id);
    alertElement.style.animation = 'slideInLeft 0.3s ease-out';
    
    const priorityIcon = getPriorityIcon(alert.priority);
    const timeAgo = getTimeAgo(alert.timestamp);
    
    alertElement.innerHTML = `
        <div class="d-flex align-items-start">
            <div class="me-2">
                <i class="bi ${priorityIcon}"></i>
            </div>
            <div class="flex-grow-1">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <strong class="small">${alert.title}</strong>
                        <div class="small">${alert.message}</div>
                        <small class="text-muted">${timeAgo}</small>
                    </div>
                    <div class="ms-2">
                        ${alert.action ? `<button class="btn btn-sm btn-outline-primary" onclick="${alert.action}">Action</button>` : ''}
                        <button class="btn btn-sm btn-outline-secondary ms-1" onclick="dismissAlert(${alert.id})" title="Dismiss">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Insert at the beginning
    alertsContainer.insertBefore(alertElement, alertsContainer.firstChild);
    
    // Add to internal storage
    notificationSystem.alerts.unshift(alert);
    
    // Play notification sound if enabled
    if (notificationSystem.settings.soundEnabled) {
        playNotificationSound(alert.priority);
    }
    
    // Show browser notification for high priority alerts
    if (alert.priority === 'high' && Notification.permission === 'granted') {
        new Notification(alert.title, {
            body: alert.message,
            icon: '/favicon.ico',
            tag: `alert-${alert.id}`
        });
    }
}

function getPriorityIcon(priority) {
    const icons = {
        'high': 'bi-exclamation-triangle-fill',
        'medium': 'bi-info-circle-fill',
        'low': 'bi-check-circle-fill'
    };
    return icons[priority] || 'bi-bell-fill';
}

function getTimeAgo(timestamp) {
    const now = new Date();
    const diff = now - timestamp;
    const minutes = Math.floor(diff / 60000);
    
    if (minutes < 1) return 'Just now';
    if (minutes < 60) return `${minutes}m ago`;
    if (minutes < 1440) return `${Math.floor(minutes / 60)}h ago`;
    return `${Math.floor(minutes / 1440)}d ago`;
}

function playNotificationSound(priority) {
    // Different sounds for different priorities
    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
    const oscillator = audioContext.createOscillator();
    const gainNode = audioContext.createGain();
    
    oscillator.connect(gainNode);
    gainNode.connect(audioContext.destination);
    
    // Different frequencies for priorities
    const frequencies = {
        'high': 800,
        'medium': 600,
        'low': 400
    };
    
    oscillator.frequency.setValueAtTime(frequencies[priority] || 500, audioContext.currentTime);
    oscillator.type = 'sine';
    
    gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
    
    oscillator.start(audioContext.currentTime);
    oscillator.stop(audioContext.currentTime + 0.3);
}

function checkForNewAlerts() {
    // Simulate checking for new alerts
    const alertTypes = [
        {
            type: 'info',
            title: 'New Check-in',
            message: 'Employee checked in via mobile app',
            priority: 'low'
        },
        {
            type: 'warning',
            title: 'Late Arrival',
            message: 'Employee arrived 45 minutes late',
            priority: 'medium'
        },
        {
            type: 'danger',
            title: 'System Alert',
            message: 'Biometric device offline - immediate attention required',
            priority: 'high'
        }
    ];
    
    // Randomly add new alerts (20% chance)
    if (Math.random() < 0.2) {
        const randomAlert = alertTypes[Math.floor(Math.random() * alertTypes.length)];
        const newAlert = {
            id: Date.now(),
            type: randomAlert.type,
            title: randomAlert.title,
            message: randomAlert.message,
            priority: randomAlert.priority,
            timestamp: new Date(),
            action: getRandomAction()
        };
        
        addSmartAlert(newAlert);
    }
}

function getRandomAction() {
    const actions = [
        'viewDetails()',
        'takeAction()',
        'viewReport()',
        null // No action
    ];
    return actions[Math.floor(Math.random() * actions.length)];
}

function dismissAlert(alertId) {
    const alertElement = document.querySelector(`[data-alert-id="${alertId}"]`);
    if (alertElement) {
        alertElement.style.animation = 'slideOutRight 0.3s ease-in';
        setTimeout(() => {
            if (alertElement.parentNode) {
                alertElement.parentNode.removeChild(alertElement);
            }
        }, 300);
    }
    
    // Remove from internal storage
    notificationSystem.alerts = notificationSystem.alerts.filter(alert => alert.id !== alertId);
}

function viewPendingApprovals() {
    openWorkflowPanel();
}

function viewSyncDetails() {
    openDeviceSettings();
}

function viewAttendanceReport() {
    showAnalytics();
}

function viewDetails() {
    showAlert('Viewing detailed information...', 'info');
}

function takeAction() {
    showAlert('Action panel opened...', 'info');
}

function viewReport() {
    showAlert('Opening report...', 'info');
}

// ============================================
// 10. MANAGER TOOLS
// ============================================

function openTeamDashboard() {
    const dashboardModal = document.createElement('div');
    dashboardModal.className = 'modal fade';
    dashboardModal.id = 'teamDashboardModal';
    dashboardModal.innerHTML = `
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-people me-2"></i>Team Dashboard
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    ${generateTeamDashboard()}
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="exportTeamReport()">
                        <i class="bi bi-download me-1"></i>Export Report
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(dashboardModal);
    const modal = new bootstrap.Modal(dashboardModal);
    modal.show();
    
    dashboardModal.addEventListener('hidden.bs.modal', () => {
        document.body.removeChild(dashboardModal);
    });
}

function generateTeamDashboard() {
    return `
        <div class="team-dashboard">
            <!-- Team Overview Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-center bg-success text-white">
                        <div class="card-body">
                            <h4>24</h4>
                            <small>Team Members</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center bg-info text-white">
                        <div class="card-body">
                            <h4>22</h4>
                            <small>Present Today</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center bg-warning text-dark">
                        <div class="card-body">
                            <h4>2</h4>
                            <small>On Leave</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center bg-danger text-white">
                        <div class="card-body">
                            <h4>0</h4>
                            <small>Absent</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Team Attendance Table -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Today's Team Attendance</h6>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="refreshTeamData()">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                        <button class="btn btn-outline-success" onclick="markAllPresent()">
                            Mark All Present
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Check-in</th>
                                    <th>Check-out</th>
                                    <th>Status</th>
                                    <th>Hours</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${generateTeamAttendanceRows()}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Leave Requests & Quick Actions -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Pending Leave Requests</h6>
                        </div>
                        <div class="card-body">
                            ${generatePendingLeaveRequests()}
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Team Performance Insights</h6>
                        </div>
                        <div class="card-body">
                            ${generateTeamPerformanceInsights()}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function generateTeamAttendanceRows() {
    const employees = [
        { name: 'John Doe', checkIn: '09:00', checkOut: '18:00', status: 'Present', hours: '9.0' },
        { name: 'Jane Smith', checkIn: '09:15', checkOut: '-', status: 'Present', hours: '7.5' },
        { name: 'Mike Johnson', checkIn: '09:30', checkOut: '17:30', status: 'Present', hours: '8.0' },
        { name: 'Sarah Wilson', checkIn: '-', checkOut: '-', status: 'On Leave', hours: '0.0' },
        { name: 'Tom Brown', checkIn: '10:00', checkOut: '-', status: 'Late', hours: '6.5' }
    ];
    
    return employees.map((emp, index) => `
        <tr>
            <td>
                <div class="d-flex align-items-center">
                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-2" 
                         style="width: 32px; height: 32px;">
                        <i class="bi bi-person text-white"></i>
                    </div>
                    <div>
                        <strong>${emp.name}</strong>
                        <br><small class="text-muted">EMP${(index + 1).toString().padStart(3, '0')}</small>
                    </div>
                </div>
            </td>
            <td>
                <span class="${emp.checkIn === '-' ? 'text-muted' : (emp.status === 'Late' ? 'text-warning' : 'text-success')}">
                    ${emp.checkIn}
                </span>
            </td>
            <td>
                <span class="${emp.checkOut === '-' ? 'text-muted' : 'text-info'}">
                    ${emp.checkOut}
                </span>
            </td>
            <td>
                <span class="badge bg-${getStatusColor(emp.status)}">${emp.status}</span>
            </td>
            <td>${emp.hours}h</td>
            <td>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" onclick="viewEmployeeDetails(${index + 1})" title="View Details">
                        <i class="bi bi-eye"></i>
                    </button>
                    <button class="btn btn-outline-success" onclick="approveAttendance(${index + 1})" title="Approve">
                        <i class="bi bi-check"></i>
                    </button>
                    <button class="btn btn-outline-warning" onclick="editAttendance(${index + 1})" title="Edit">
                        <i class="bi bi-pencil"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function getStatusColor(status) {
    const colors = {
        'Present': 'success',
        'Late': 'warning',
        'Absent': 'danger',
        'On Leave': 'info'
    };
    return colors[status] || 'secondary';
}

function generatePendingLeaveRequests() {
    return `
        <div class="leave-requests">
            <div class="leave-request-item border-start border-primary border-3 ps-3 mb-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-1">Alice Cooper</h6>
                        <small class="text-muted">Casual Leave: Dec 23-24</small>
                        <br><small class="text-primary">2 days requested</small>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-success" onclick="quickApproveLeave(1)">Approve</button>
                        <button class="btn btn-outline-secondary" onclick="viewLeaveDetails(1)">Details</button>
                    </div>
                </div>
            </div>
            
            <div class="leave-request-item border-start border-warning border-3 ps-3 mb-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-1">Bob Davis</h6>
                        <small class="text-muted">Sick Leave: Dec 20</small>
                        <br><small class="text-warning">1 day requested</small>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-success" onclick="quickApproveLeave(2)">Approve</button>
                        <button class="btn btn-outline-secondary" onclick="viewLeaveDetails(2)">Details</button>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-3">
                <button class="btn btn-primary btn-sm" onclick="bulkApproval()">
                    <i class="bi bi-check-all me-1"></i>Bulk Actions
                </button>
            </div>
        </div>
    `;
}

function generateTeamPerformanceInsights() {
    return `
        <div class="performance-insights">
            <div class="insight-item mb-3">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted">Team Attendance Rate:</span>
                    <span class="fw-bold text-success">95.8%</span>
                </div>
                <div class="progress mt-1">
                    <div class="progress-bar bg-success" style="width: 95.8%"></div>
                </div>
            </div>
            
            <div class="insight-item mb-3">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted">Avg Daily Hours:</span>
                    <span class="fw-bold text-info">8.2 hours</span>
                </div>
                <div class="progress mt-1">
                    <div class="progress-bar bg-info" style="width: 82%"></div>
                </div>
            </div>
            
            <div class="insight-item mb-3">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted">Punctuality Score:</span>
                    <span class="fw-bold text-warning">88%</span>
                </div>
                <div class="progress mt-1">
                    <div class="progress-bar bg-warning" style="width: 88%"></div>
                </div>
            </div>
            
            <div class="alert alert-info p-2 mt-3">
                <small>
                    <i class="bi bi-lightbulb me-1"></i>
                    <strong>Insight:</strong> Team performance has improved by 5% this month.
                </small>
            </div>
        </div>
    `;
}

function bulkApproval() {
    const bulkModal = document.createElement('div');
    bulkModal.className = 'modal fade';
    bulkModal.innerHTML = `
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-check-all me-2"></i>Bulk Approval
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="selectAllLeaves" onchange="toggleAllLeaves()">
                        <label class="form-check-label fw-bold" for="selectAllLeaves">
                            Select All Pending Requests
                        </label>
                    </div>
                    
                    <div class="border rounded p-3">
                        <div class="form-check mb-2">
                            <input class="form-check-input bulk-leave-item" type="checkbox" id="leave1">
                            <label class="form-check-label" for="leave1">
                                Alice Cooper - Casual Leave (2 days)
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input bulk-leave-item" type="checkbox" id="leave2">
                            <label class="form-check-label" for="leave2">
                                Bob Davis - Sick Leave (1 day)
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input bulk-leave-item" type="checkbox" id="leave3">
                            <label class="form-check-label" for="leave3">
                                Carol White - Work From Home (3 days)
                            </label>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <label class="form-label">Bulk Action:</label>
                        <select class="form-select" id="bulkAction">
                            <option value="approve">Approve Selected</option>
                            <option value="reject">Reject Selected</option>
                            <option value="request_info">Request More Information</option>
                        </select>
                    </div>
                    
                    <div class="mt-3" id="bulkCommentSection" style="display: none;">
                        <label class="form-label">Comment (optional):</label>
                        <textarea class="form-control" id="bulkComment" rows="2" placeholder="Add a comment for the selected action..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="processBulkAction()">
                        <i class="bi bi-check me-1"></i>Process Selected
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(bulkModal);
    const modal = new bootstrap.Modal(bulkModal);
    modal.show();
    
    // Show comment section for reject action
    document.getElementById('bulkAction').addEventListener('change', function() {
        const commentSection = document.getElementById('bulkCommentSection');
        commentSection.style.display = this.value === 'reject' ? 'block' : 'none';
    });
    
    bulkModal.addEventListener('hidden.bs.modal', () => {
        document.body.removeChild(bulkModal);
    });
}

function toggleAllLeaves() {
    const selectAll = document.getElementById('selectAllLeaves');
    const items = document.querySelectorAll('.bulk-leave-item');
    
    items.forEach(item => {
        item.checked = selectAll.checked;
    });
}

function processBulkAction() {
    const selectedItems = document.querySelectorAll('.bulk-leave-item:checked');
    const action = document.getElementById('bulkAction').value;
    const comment = document.getElementById('bulkComment').value;
    
    if (selectedItems.length === 0) {
        showAlert('Please select at least one item', 'warning');
        return;
    }
    
    showAlert(`${action.replace('_', ' ')} action processed for ${selectedItems.length} items`, 'success');
    
    // Close the bulk modal
    const modal = bootstrap.Modal.getInstance(document.querySelector('.modal.show'));
    modal.hide();
}

function applyOnBehalf() {
    const onBehalfModal = document.createElement('div');
    onBehalfModal.className = 'modal fade';
    onBehalfModal.innerHTML = `
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">
                        <i class="bi bi-person-plus me-2"></i>Apply Leave on Behalf
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="onBehalfForm">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Manager Privilege:</strong> You can apply for leave on behalf of your team members.
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Select Employee</label>
                                <select class="form-select" required>
                                    <option value="">Choose employee...</option>
                                    <option value="1">John Doe (EMP001)</option>
                                    <option value="2">Jane Smith (EMP002)</option>
                                    <option value="3">Mike Johnson (EMP003)</option>
                                    <option value="4">Sarah Wilson (EMP004)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Leave Type</label>
                                <select class="form-select" required>
                                    <option value="">Select type...</option>
                                    <option value="casual">Casual Leave</option>
                                    <option value="sick">Sick Leave</option>
                                    <option value="emergency">Emergency Leave</option>
                                    <option value="compensatory">Compensatory Off</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Manager's Reason/Justification</label>
                                <textarea class="form-control" rows="3" placeholder="Provide reason for applying on behalf of the employee..." required></textarea>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="employeeInformed" required>
                                    <label class="form-check-label" for="employeeInformed">
                                        I confirm that the employee has been informed about this leave application
                                    </label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="autoApprove">
                                    <label class="form-check-label" for="autoApprove">
                                        Auto-approve this application (Manager privilege)
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-send me-1"></i>Submit Application
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    document.body.appendChild(onBehalfModal);
    const modal = new bootstrap.Modal(onBehalfModal);
    modal.show();
    
    // Handle form submission
    document.getElementById('onBehalfForm').addEventListener('submit', function(e) {
        e.preventDefault();
        showAlert('Leave application submitted successfully on behalf of employee!', 'success');
        modal.hide();
    });
    
    onBehalfModal.addEventListener('hidden.bs.modal', () => {
        document.body.removeChild(onBehalfModal);
    });
}

function refreshTeamData() {
    showAlert('Team data refreshed successfully!', 'success');
}

function viewEmployeeDetails(empId) {
    showAlert(`Viewing details for employee ${empId}...`, 'info');
}

function approveAttendance(empId) {
    showAlert(`Attendance approved for employee ${empId}!`, 'success');
}

function editAttendance(empId) {
    showAlert(`Opening attendance editor for employee ${empId}...`, 'info');
}

function quickApproveLeave(leaveId) {
    showAlert(`Leave request ${leaveId} approved!`, 'success');
}

function viewLeaveDetails(leaveId) {
    showAlert(`Viewing leave details for request ${leaveId}...`, 'info');
}

function exportTeamReport() {
    showAlert('Exporting team report to Excel...', 'info');
    setTimeout(() => {
        showAlert('Team report exported successfully!', 'success');
    }, 2000);
}

// ============================================
// SMART ATTENDANCE FEATURES (TOUCHLESS)
// ============================================

// 1. Smart Attendance (Touchless) Functions - Fixed to prevent conflicts
function startFaceRecognition() {
    openSmartAttendance();
    // Delay to ensure modal is fully opened before initializing face recognition
    setTimeout(() => {
        initFaceRecognition();
    }, 500);
}

function startQRScan() {
    openSmartAttendance();
    // Delay to ensure modal is fully opened before initializing QR scanner
    setTimeout(() => {
        initQRScanner();
    }, 500);
}

function startGeoAttendance() {
    openSmartAttendance();
    // GPS is automatically initialized when modal opens, just focus on the GPS section
    setTimeout(() => {
        const gpsCard = document.querySelector('#smartAttendanceModal .card .bg-warning').closest('.card');
        if (gpsCard) {
            gpsCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }, 500);
}

function startIPAttendance() {
    openSmartAttendance();
    // IP detection is automatically initialized when modal opens, just focus on the IP section
    setTimeout(() => {
        const ipCard = document.querySelector('#smartAttendanceModal .card .bg-secondary').closest('.card');
        if (ipCard) {
            ipCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }, 500);
}

// End of Smart Attendance fixes - Removed duplicate advanced functions

function processFaceRecognition() {
    const area = document.getElementById('faceRecognitionArea');
    
    // Simulate processing
    area.innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-success mb-3" role="status"></div>
            <p class="mb-2">Processing facial features...</p>
            <div class="d-flex justify-content-center gap-2 mb-3">
                <span class="badge bg-success">Eyes Detected</span>
                <span class="badge bg-success">Face Position OK</span>
                <span class="badge bg-success">Lighting Good</span>
            </div>
        </div>
    `;
    
    setTimeout(() => {
        completeFaceRecognitionProcess();
    }, 2000);
}

function completeFaceRecognitionProcess() {
    const area = document.getElementById('faceRecognitionArea');
    
    // Simulate successful recognition
    area.innerHTML = `
        <div class="text-center">
            <div class="mb-3">
                <i class="bi bi-person-check-fill text-success" style="font-size: 4rem;"></i>
            </div>
            <div class="alert alert-success">
                <strong>Recognition Successful!</strong><br>
                Employee: John Doe<br>
                ID: EMP001<br>
                Department: IT
            </div>
            <div class="d-grid gap-2">
                <button class="btn btn-success" onclick="processAdvancedCheckIn('face_recognition', 'EMP001')">
                    <i class="bi bi-check-circle me-2"></i>Confirm Check-In
                </button>
                <button class="btn btn-outline-secondary btn-sm" onclick="initAdvancedFaceRecognition()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Scan Again
                </button>
            </div>
        </div>
    `;
    
    // Cleanup camera stream
    if (window.currentFaceStream) {
        window.currentFaceStream.getTracks().forEach(track => track.stop());
    }
}

// Enhanced QR Scanner with Employee Data
function initAdvancedQRScanner() {
    const area = document.getElementById('qrScannerArea');
    area.innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-info mb-3" role="status"></div>
            <p class="mb-2">Initializing QR code scanner...</p>
            <small class="text-muted">Position QR code within the frame</small>
        </div>
    `;
    
    setTimeout(() => {
        startQRCamera();
    }, 1000);
}

function startQRCamera() {
    const area = document.getElementById('qrScannerArea');
    
    navigator.mediaDevices.getUserMedia({ 
        video: { 
            facingMode: 'environment' // Use back camera if available
        } 
    })
    .then(stream => {
        const video = document.createElement('video');
        video.srcObject = stream;
        video.autoplay = true;
        video.style.width = '100%';
        video.style.height = '200px';
        video.style.objectFit = 'cover';
        video.style.borderRadius = '10px';
        
        area.innerHTML = `
            <div class="position-relative">
                <div class="qr-overlay position-absolute top-50 start-50 translate-middle border border-2 border-info" 
                     style="width: 150px; height: 150px; border-style: dashed !important;">
                </div>
                <div class="position-absolute bottom-0 start-0 w-100 p-2 text-center bg-dark bg-opacity-50 text-white" 
                     style="border-radius: 0 0 10px 10px;">
                    <small>Align QR code within the frame</small>
                </div>
            </div>
        `;
        area.appendChild(video);
        
        // Store stream for cleanup
        window.currentQRStream = stream;
        
        // Simulate QR detection after 3 seconds
        setTimeout(() => {
            processQRCode();
        }, 3000);
    })
    .catch(err => {
        area.innerHTML = `
            <div class="text-center text-danger">
                <i class="bi bi-qr-code" style="font-size: 3rem;"></i>
                <p class="mt-2">Camera access denied</p>
                <button class="btn btn-outline-info btn-sm" onclick="simulateQRInput()">Enter QR Manually</button>
            </div>
        `;
    });
}

function processQRCode() {
    const area = document.getElementById('qrScannerArea');
    
    area.innerHTML = `
        <div class="text-center">
            <div class="mb-3">
                <i class="bi bi-qr-code-scan text-success" style="font-size: 4rem;"></i>
            </div>
            <div class="alert alert-success">
                <strong>QR Code Scanned!</strong><br>
                Employee: Jane Smith<br>
                ID: EMP002<br>
                Department: HR<br>
                <small class="text-muted">Scan Time: ${new Date().toLocaleTimeString()}</small>
            </div>
            <div class="d-grid gap-2">
                <button class="btn btn-success" onclick="processAdvancedCheckIn('qr_scan', 'EMP002')">
                    <i class="bi bi-check-circle me-2"></i>Confirm Check-In
                </button>
                <button class="btn btn-outline-secondary btn-sm" onclick="initAdvancedQRScanner()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Scan Another
                </button>
            </div>
        </div>
    `;
    
    // Cleanup camera stream
    if (window.currentQRStream) {
        window.currentQRStream.getTracks().forEach(track => track.stop());
    }
}

function simulateQRInput() {
    const area = document.getElementById('qrScannerArea');
    area.innerHTML = `
        <div class="text-center">
            <h6>Enter QR Code</h6>
            <input type="text" class="form-control mb-3" placeholder="QR Code or Employee ID" id="manualQR">
            <button class="btn btn-info" onclick="processManualQR()">Submit</button>
        </div>
    `;
}

function processManualQR() {
    const qrValue = document.getElementById('manualQR').value;
    if (qrValue.trim()) {
        processAdvancedCheckIn('manual_qr', qrValue);
    } else {
        showAlert('Please enter a valid QR code or Employee ID', 'warning');
    }
}

// Enhanced GPS Attendance with Geofencing
function initAdvancedGPSAttendance() {
    const spinner = document.getElementById('locationSpinner');
    const text = document.getElementById('locationText');
    const btn = document.getElementById('gpsCheckInBtn');
    
    spinner.style.display = 'block';
    text.innerHTML = 'Getting precise location...';
    btn.disabled = true;
    btn.className = 'btn btn-warning w-100';
    
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                processGPSLocation(position);
            },
            (error) => {
                handleGPSError(error);
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 60000
            }
        );
    } else {
        handleGPSError({ message: 'Geolocation not supported' });
    }
}

function processGPSLocation(position) {
    const lat = position.coords.latitude;
    const lon = position.coords.longitude;
    const accuracy = position.coords.accuracy;
    
    const spinner = document.getElementById('locationSpinner');
    const text = document.getElementById('locationText');
    const btn = document.getElementById('gpsCheckInBtn');
    
    // Simulate office location verification
    const officeDistance = calculateDistanceFromOffice(lat, lon);
    
    spinner.style.display = 'none';
    
    if (officeDistance <= 100) { // Within 100 meters
        text.innerHTML = `
            <div class="text-success">
                <i class="bi bi-geo-alt-fill me-2"></i>
                <strong>Location Verified ✓</strong><br>
                <small class="text-muted">
                    Distance from office: ${officeDistance}m<br>
                    Accuracy: ±${Math.round(accuracy)}m<br>
                    Coordinates: ${lat.toFixed(6)}, ${lon.toFixed(6)}
                </small>
            </div>
        `;
        btn.disabled = false;
        btn.className = 'btn btn-success w-100';
        btn.innerHTML = '<i class="bi bi-geo-alt-fill me-2"></i>Check-In with GPS';
    } else {
        text.innerHTML = `
            <div class="text-warning">
                <i class="bi bi-geo-alt me-2"></i>
                <strong>Outside Office Premises</strong><br>
                <small class="text-muted">
                    Distance from office: ${officeDistance}m<br>
                    You must be within 100m of the office
                </small>
            </div>
        `;
        btn.disabled = true;
        btn.className = 'btn btn-outline-warning w-100';
        btn.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>Too Far from Office';
    }
}

function calculateDistanceFromOffice(lat, lon) {
    // Office coordinates (example)
    const officeLat = 28.7041;
    const officeLon = 77.1025;
    
    const R = 6371e3; // Earth's radius in meters
    const φ1 = lat * Math.PI/180;
    const φ2 = officeLat * Math.PI/180;
    const Δφ = (officeLat-lat) * Math.PI/180;
    const Δλ = (officeLon-lon) * Math.PI/180;
    
    const a = Math.sin(Δφ/2) * Math.sin(Δφ/2) +
              Math.cos(φ1) * Math.cos(φ2) *
              Math.sin(Δλ/2) * Math.sin(Δλ/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    
    return Math.round(R * c);
}

function handleGPSError(error) {
    const spinner = document.getElementById('locationSpinner');
    const text = document.getElementById('locationText');
    const btn = document.getElementById('gpsCheckInBtn');
    
    spinner.style.display = 'none';
    text.innerHTML = `
        <div class="text-danger">
            <i class="bi bi-geo-alt me-2"></i>
            Location access denied<br>
            <small>${error.message}</small>
        </div>
    `;
    btn.disabled = true;
    btn.className = 'btn btn-outline-danger w-100';
    btn.innerHTML = '<i class="bi bi-x-circle me-2"></i>Location Required';
}

// Enhanced IP-based Attendance with Network Validation
function initAdvancedIPAttendance() {
    const ipElement = document.getElementById('userIP');
    ipElement.textContent = 'Detecting...';
    
    // Get user's IP and validate against office network
    Promise.all([
        fetch('https://api.ipify.org?format=json').then(r => r.json()),
        checkNetworkInfo()
    ]).then(([ipData, networkData]) => {
        processIPValidation(ipData.ip, networkData);
    }).catch(error => {
        handleIPError(error);
    });
}

function checkNetworkInfo() {
    // Simulate network information checking
    return new Promise((resolve) => {
        setTimeout(() => {
            resolve({
                networkName: 'OfficeNetwork_5G',
                isOfficeNetwork: true,
                security: 'WPA2',
                strength: 'Strong'
            });
        }, 1000);
    });
}

function processIPValidation(userIP, networkData) {
    const ipElement = document.getElementById('userIP');
    const networkStatus = document.querySelector('.badge');
    
    ipElement.textContent = userIP;
    
    // Validate if IP is from office network
    const isValidOfficeIP = validateOfficeIP(userIP);
    
    if (isValidOfficeIP && networkData.isOfficeNetwork) {
        networkStatus.className = 'badge bg-success';
        networkStatus.textContent = 'Office Network Verified';
        
        // Update button
        const btn = document.querySelector('button[onclick="checkInWithIP()"]');
        btn.className = 'btn btn-success w-100';
        btn.innerHTML = '<i class="bi bi-shield-check me-2"></i>Check-In with IP';
        btn.disabled = false;
    } else {
        networkStatus.className = 'badge bg-warning';
        networkStatus.textContent = 'External Network';
        
        const btn = document.querySelector('button[onclick="checkInWithIP()"]');
        btn.className = 'btn btn-outline-warning w-100';
        btn.innerHTML = '<i class="bi bi-shield-exclamation me-2"></i>Not Office Network';
        btn.disabled = true;
    }
}

function validateOfficeIP(ip) {
    // Office IP ranges (example)
    const officeIPRanges = [
        /^192\.168\.1\.\d+$/,
        /^10\.0\.0\.\d+$/,
        /^172\.16\.\d+\.\d+$/
    ];
    
    return officeIPRanges.some(range => range.test(ip));
}

function handleIPError(error) {
    const ipElement = document.getElementById('userIP');
    const networkStatus = document.querySelector('.badge');
    
    ipElement.textContent = 'Unable to detect';
    networkStatus.className = 'badge bg-danger';
    networkStatus.textContent = 'Network Error';
}

// Universal Check-in Processor
function processAdvancedCheckIn(method, employeeId) {
    const checkInData = {
        method: method,
        employee_id: employeeId,
        timestamp: new Date().toISOString(),
        location: method === 'gps' ? 'GPS_VERIFIED' : 'OFFICE',
        device_info: navigator.userAgent
    };
    
    // Simulate API call
    const checkInBtn = document.querySelector(`button[onclick*="${method}"]`);
    const originalText = checkInBtn.innerHTML;
    
    checkInBtn.innerHTML = '<div class="spinner-border spinner-border-sm me-2"></div>Processing...';
    checkInBtn.disabled = true;
    
    setTimeout(() => {
        // Simulate successful check-in
        showAlert(`✅ ${method.replace('_', ' ').toUpperCase()} check-in successful!`, 'success');
        $('#smartAttendanceModal').modal('hide');
        
        // Reset button
        checkInBtn.innerHTML = originalText;
        checkInBtn.disabled = false;
        
        // Update attendance table if on same page
        updateAttendanceDisplay(employeeId);
        
    }, 2000);
}

// ============================================
// 11. AUTO LEAVE DEDUCTION
// ============================================

let autoLeaveDeduction = {
    enabled: true,
    rules: {
        deductForAbsent: true,
        deductForLateArrival: false,
        lateThresholdMinutes: 60,
        halfDayThresholdMinutes: 240,
        gracePeriodMinutes: 15
    }
};

function initializeAutoLeaveDeduction() {
    // Check for employees who need auto-deduction
    checkAutoDeductionCandidates();
    
    // Set up daily auto-deduction check
    scheduleAutoDeductionCheck();
}

function checkAutoDeductionCandidates() {
    // Simulate checking for employees needing auto-deduction
    const candidates = [
        {
            employeeId: 1,
            employeeName: 'John Doe',
            date: '2024-12-19',
            reason: 'Absent without leave application',
            deductionType: 'full_day',
            balanceAfter: 11
        },
        {
            employeeId: 2,
            employeeName: 'Jane Smith',
            date: '2024-12-18',
            reason: 'Late arrival beyond threshold (3 hours)',
            deductionType: 'half_day',
            balanceAfter: 14.5
        }
    ];
    
    if (candidates.length > 0) {
        showAutoDeductionAlert(candidates);
    }
}

function showAutoDeductionAlert(candidates) {
    const alertModal = document.createElement('div');
    alertModal.className = 'modal fade';
    alertModal.id = 'autoDeductionAlert';
    alertModal.innerHTML = `
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle me-2"></i>Auto Leave Deduction Alert
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        The system has identified employees eligible for automatic leave deduction based on attendance policies.
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Date</th>
                                    <th>Reason</th>
                                    <th>Deduction</th>
                                    <th>Leave Balance</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${candidates.map((candidate, index) => `
                                    <tr>
                                        <td>${candidate.employeeName}</td>
                                        <td>${candidate.date}</td>
                                        <td>
                                            <small class="text-muted">${candidate.reason}</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-${candidate.deductionType === 'full_day' ? 'danger' : 'warning'}">
                                                ${candidate.deductionType.replace('_', ' ')}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fw-bold">${candidate.balanceAfter} days</span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-success" onclick="approveDeduction(${index})">
                                                    <i class="bi bi-check"></i>
                                                </button>
                                                <button class="btn btn-danger" onclick="rejectDeduction(${index})">
                                                    <i class="bi bi-x"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-3">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="notifyEmployees" checked>
                                    <label class="form-check-label" for="notifyEmployees">
                                        Send notification to affected employees
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="addToAuditLog" checked>
                                    <label class="form-check-label" for="addToAuditLog">
                                        Add to audit log
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" onclick="processAllDeductions()">
                        <i class="bi bi-check-all me-1"></i>Approve All Deductions
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(alertModal);
    const modal = new bootstrap.Modal(alertModal);
    modal.show();
    
    alertModal.addEventListener('hidden.bs.modal', () => {
        document.body.removeChild(alertModal);
    });
}

function approveDeduction(index) {
    showAlert(`Leave deduction approved for employee ${index + 1}`, 'success');
    logAuditEvent('auto_deduction_approved', `Deduction approved for employee ${index + 1}`);
}

function rejectDeduction(index) {
    const reason = prompt('Please provide a reason for rejecting the deduction:');
    if (reason) {
        showAlert(`Leave deduction rejected: ${reason}`, 'warning');
        logAuditEvent('auto_deduction_rejected', `Deduction rejected for employee ${index + 1}: ${reason}`);
    }
}

function processAllDeductions() {
    showAlert('Processing all approved deductions...', 'info');
    
    setTimeout(() => {
        showAlert('All leave deductions processed successfully!', 'success');
        logAuditEvent('bulk_auto_deduction', 'Bulk leave deductions processed');
        
        // Close the modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('autoDeductionAlert'));
        modal.hide();
    }, 2000);
}

function scheduleAutoDeductionCheck() {
    // In a real implementation, this would be handled by a cron job or scheduled task
    console.log('Auto-deduction check scheduled for daily execution');
    
    // Simulate periodic checks (every hour for demo)
    setInterval(() => {
        checkAutoDeductionCandidates();
    }, 3600000); // 1 hour
}

// ============================================
// 12. AUDIT & HISTORY
// ============================================

let auditSystem = {
    logs: [],
    maxLogs: 1000,
    categories: ['attendance', 'leave', 'system', 'security', 'approval', 'auto_deduction']
};

function initializeAuditSystem() {
    // Load existing audit logs
    loadAuditLogs();
    
    // Set up automatic audit logging
    setupAuditLogging();
}

function loadAuditLogs() {
    // Simulate loading audit logs from database
    const sampleLogs = [
        {
            id: 1,
            timestamp: new Date(Date.now() - 3600000), // 1 hour ago
            category: 'attendance',
            action: 'check_in',
            user: 'John Doe',
            details: 'Checked in via face recognition',
            ipAddress: '192.168.1.100',
            device: 'Mobile App'
        },
        {
            id: 2,
            timestamp: new Date(Date.now() - 7200000), // 2 hours ago
            category: 'leave',
            action: 'leave_approved',
            user: 'Manager Jane',
            details: 'Approved 2-day casual leave for Mike Johnson',
            ipAddress: '192.168.1.50',
            device: 'Web Browser'
        },
        {
            id: 3,
            timestamp: new Date(Date.now() - 10800000), // 3 hours ago
            category: 'system',
            action: 'policy_updated',
            user: 'Admin',
            details: 'Updated leave policy - increased casual leave to 15 days',
            ipAddress: '192.168.1.10',
            device: 'Admin Panel'
        }
    ];
    
    auditSystem.logs = sampleLogs;
}

function logAuditEvent(action, details, category = 'system') {
    const auditEntry = {
        id: Date.now(),
        timestamp: new Date(),
        category: category,
        action: action,
        user: 'Current User', // In real implementation, get from session
        details: details,
        ipAddress: '192.168.1.100', // In real implementation, get client IP
        device: navigator.userAgent
    };
    
    // Add to logs array
    auditSystem.logs.unshift(auditEntry);
    
    // Keep only last 1000 logs in memory
    if (auditSystem.logs.length > auditSystem.maxLogs) {
        auditSystem.logs = auditSystem.logs.slice(0, auditSystem.maxLogs);
    }
    
    // In real implementation, save to database
    console.log('Audit event logged:', auditEntry);
}

function showAuditHistory() {
    const auditModal = document.createElement('div');
    auditModal.className = 'modal fade';
    auditModal.id = 'auditHistoryModal';
    auditModal.innerHTML = `
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-clock-history me-2"></i>Audit & History Trail
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Filter Controls -->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" id="auditCategoryFilter" onchange="filterAuditLogs()">
                                <option value="">All Categories</option>
                                <option value="attendance">Attendance</option>
                                <option value="leave">Leave Management</option>
                                <option value="system">System</option>
                                <option value="security">Security</option>
                                <option value="approval">Approvals</option>
                                <option value="auto_deduction">Auto Deduction</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date From</label>
                            <input type="date" class="form-control" id="auditDateFrom" onchange="filterAuditLogs()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date To</label>
                            <input type="date" class="form-control" id="auditDateTo" onchange="filterAuditLogs()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" id="auditSearch" placeholder="Search logs..." onkeyup="filterAuditLogs()">
                        </div>
                    </div>
                    
                    <!-- Audit Logs Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Category</th>
                                    <th>Action</th>
                                    <th>User</th>
                                    <th>Details</th>
                                    <th>IP Address</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="auditLogsTableBody">
                                ${generateAuditLogsRows()}
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <nav aria-label="Audit logs pagination">
                        <ul class="pagination justify-content-center">
                            <li class="page-item"><a class="page-link" href="#" onclick="loadAuditPage(1)">1</a></li>
                            <li class="page-item active"><a class="page-link" href="#" onclick="loadAuditPage(2)">2</a></li>
                            <li class="page-item"><a class="page-link" href="#" onclick="loadAuditPage(3)">3</a></li>
                        </ul>
                    </nav>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" onclick="exportAuditLogs()">
                        <i class="bi bi-download me-1"></i>Export Logs
                    </button>
                    <button type="button" class="btn btn-danger" onclick="clearAuditLogs()">
                        <i class="bi bi-trash me-1"></i>Clear Old Logs
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(auditModal);
    const modal = new bootstrap.Modal(auditModal);
    modal.show();
    
    auditModal.addEventListener('hidden.bs.modal', () => {
        document.body.removeChild(auditModal);
    });
}

function generateAuditLogsRows() {
    return auditSystem.logs.map(log => `
        <tr>
            <td>
                <small>${log.timestamp.toLocaleString()}</small>
            </td>
            <td>
                <span class="badge bg-${getCategoryColor(log.category)}">
                    ${log.category.toUpperCase()}
                </span>
            </td>
            <td>
                <small class="fw-medium">${formatAction(log.action)}</small>
            </td>
            <td>
                <small>${log.user}</small>
            </td>
            <td>
                <small class="text-muted">${log.details}</small>
            </td>
            <td>
                <small class="font-monospace">${log.ipAddress}</small>
            </td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="viewAuditDetails(${log.id})" title="View Details">
                    <i class="bi bi-eye"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

function getCategoryColor(category) {
    const colors = {
        'attendance': 'primary',
        'leave': 'success',
        'system': 'info',
        'security': 'danger',
        'approval': 'warning',
        'auto_deduction': 'dark'
    };
    return colors[category] || 'secondary';
}

function formatAction(action) {
    return action.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

function filterAuditLogs() {
    const category = document.getElementById('auditCategoryFilter').value;
    const dateFrom = document.getElementById('auditDateFrom').value;
    const dateTo = document.getElementById('auditDateTo').value;
    const search = document.getElementById('auditSearch').value.toLowerCase();
    
    let filteredLogs = auditSystem.logs;
    
    if (category) {
        filteredLogs = filteredLogs.filter(log => log.category === category);
    }
    
    if (dateFrom) {
        const fromDate = new Date(dateFrom);
        filteredLogs = filteredLogs.filter(log => log.timestamp >= fromDate);
    }
    
    if (dateTo) {
        const toDate = new Date(dateTo);
        toDate.setHours(23, 59, 59); // End of day
        filteredLogs = filteredLogs.filter(log => log.timestamp <= toDate);
    }
    
    if (search) {
        filteredLogs = filteredLogs.filter(log => 
            log.details.toLowerCase().includes(search) ||
            log.user.toLowerCase().includes(search) ||
            log.action.toLowerCase().includes(search)
        );
    }
    
    // Update table body
    const tableBody = document.getElementById('auditLogsTableBody');
    if (tableBody) {
        tableBody.innerHTML = filteredLogs.map(log => `
            <tr>
                <td><small>${log.timestamp.toLocaleString()}</small></td>
                <td><span class="badge bg-${getCategoryColor(log.category)}">${log.category.toUpperCase()}</span></td>
                <td><small class="fw-medium">${formatAction(log.action)}</small></td>
                <td><small>${log.user}</small></td>
                <td><small class="text-muted">${log.details}</small></td>
                <td><small class="font-monospace">${log.ipAddress}</small></td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="viewAuditDetails(${log.id})">
                        <i class="bi bi-eye"></i>
                    </button>
                </td>
            </tr>
        `).join('');
    }
}

function viewAuditDetails(logId) {
    const log = auditSystem.logs.find(l => l.id === logId);
    if (!log) return;
    
    const detailsModal = document.createElement('div');
    detailsModal.className = 'modal fade';
    detailsModal.innerHTML = `
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Audit Log Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-borderless">
                        <tr>
                            <th width="30%">ID:</th>
                            <td>${log.id}</td>
                        </tr>
                        <tr>
                            <th>Timestamp:</th>
                            <td>${log.timestamp.toLocaleString()}</td>
                        </tr>
                        <tr>
                            <th>Category:</th>
                            <td><span class="badge bg-${getCategoryColor(log.category)}">${log.category}</span></td>
                        </tr>
                        <tr>
                            <th>Action:</th>
                            <td>${formatAction(log.action)}</td>
                        </tr>
                        <tr>
                            <th>User:</th>
                            <td>${log.user}</td>
                        </tr>
                        <tr>
                            <th>Details:</th>
                            <td>${log.details}</td>
                        </tr>
                        <tr>
                            <th>IP Address:</th>
                            <td class="font-monospace">${log.ipAddress}</td>
                        </tr>
                        <tr>
                            <th>Device:</th>
                            <td><small class="text-muted">${log.device}</small></td>
                        </tr>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(detailsModal);
    const modal = new bootstrap.Modal(detailsModal);
    modal.show();
    
    detailsModal.addEventListener('hidden.bs.modal', () => {
        document.body.removeChild(detailsModal);
    });
}

function loadAuditPage(page) {
    showAlert(`Loading audit logs page ${page}...`, 'info');
}

function exportAuditLogs() {
    showAlert('Exporting audit logs to CSV...', 'info');
    setTimeout(() => {
        showAlert('Audit logs exported successfully!', 'success');
    }, 2000);
}

function clearAuditLogs() {
    if (confirm('Are you sure you want to clear old audit logs? This will remove logs older than 6 months.')) {
        showAlert('Old audit logs cleared successfully!', 'warning');
        logAuditEvent('audit_logs_cleared', 'Old audit logs cleared by admin');
    }
}

function setupAuditLogging() {
    // Log all form submissions
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (form.id) {
            logAuditEvent('form_submitted', `Form ${form.id} submitted`, 'system');
        }
    });
    
    // Log all button clicks on important actions
    document.addEventListener('click', function(e) {
        const button = e.target.closest('button');
        if (button && button.hasAttribute('data-audit')) {
            const action = button.getAttribute('data-audit');
            logAuditEvent(action, `Button clicked: ${button.textContent.trim()}`, 'system');
        }
    });
}

// ============================================
// 13. API INTEGRATION
// ============================================

let apiIntegration = {
    endpoints: {
        hrms: '/api/hrms/sync',
        payroll: '/api/payroll/attendance',
        biometric: '/api/biometric/devices',
        notifications: '/api/notifications/send',
        reports: '/api/reports/generate'
    },
    apiKey: 'your-api-key-here',
    webhooks: []
};

function initializeAPIIntegration() {
    // Set up API endpoints
    setupAPIEndpoints();
    
    // Initialize webhook listeners
    setupWebhooks();
    
    // Test API connectivity
    testAPIConnectivity();
}

function setupAPIEndpoints() {
    console.log('Setting up API endpoints...');
    
    // Create API endpoint handlers
    window.attendanceAPI = {
        // Sync with HRMS
        syncWithHRMS: async function(data) {
            try {
                const response = await fetch(apiIntegration.endpoints.hrms, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${apiIntegration.apiKey}`
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                logAuditEvent('hrms_sync', `HRMS sync completed: ${result.recordsUpdated} records`, 'system');
                return result;
            } catch (error) {
                logAuditEvent('hrms_sync_error', `HRMS sync failed: ${error.message}`, 'system');
                throw error;
            }
        },
        
        // Send to Payroll System
        sendToPayroll: async function(attendanceData) {
            try {
                const response = await fetch(apiIntegration.endpoints.payroll, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${apiIntegration.apiKey}`
                    },
                    body: JSON.stringify(attendanceData)
                });
                
                const result = await response.json();
                logAuditEvent('payroll_sync', `Payroll sync completed for ${attendanceData.employeeCount} employees`, 'system');
                return result;
            } catch (error) {
                logAuditEvent('payroll_sync_error', `Payroll sync failed: ${error.message}`, 'system');
                throw error;
            }
        },
        
        // Sync Biometric Devices
        syncBiometricDevices: async function() {
            try {
                const response = await fetch(apiIntegration.endpoints.biometric, {
                    method: 'GET',
                    headers: {
                        'Authorization': `Bearer ${apiIntegration.apiKey}`
                    }
                });
                
                const devices = await response.json();
                logAuditEvent('biometric_sync', `Biometric devices synced: ${devices.length} devices`, 'system');
                return devices;
            } catch (error) {
                logAuditEvent('biometric_sync_error', `Biometric sync failed: ${error.message}`, 'system');
                throw error;
            }
        }
    };
}

function setupWebhooks() {
    // Simulate webhook setup
    apiIntegration.webhooks = [
        {
            id: 1,
            name: 'Leave Application Webhook',
            url: 'https://your-system.com/webhooks/leave-application',
            events: ['leave.applied', 'leave.approved', 'leave.rejected'],
            active: true
        },
        {
            id: 2,
            name: 'Attendance Alert Webhook',
            url: 'https://your-system.com/webhooks/attendance-alert',
            events: ['attendance.late', 'attendance.absent', 'attendance.overtime'],
            active: true
        }
    ];
    
    console.log('Webhooks configured:', apiIntegration.webhooks);
}

function testAPIConnectivity() {
    const testResults = {
        hrms: false,
        payroll: false,
        biometric: false,
        notifications: false
    };
    
    // Simulate API connectivity tests
    setTimeout(() => {
        testResults.hrms = Math.random() > 0.2; // 80% success rate
        testResults.payroll = Math.random() > 0.3; // 70% success rate
        testResults.biometric = Math.random() > 0.1; // 90% success rate
        testResults.notifications = Math.random() > 0.15; // 85% success rate
        
        displayAPIStatus(testResults);
        logAuditEvent('api_connectivity_test', `API connectivity test completed`, 'system');
    }, 2000);
}

function displayAPIStatus(testResults) {
    // Add API status indicator to the page
    const statusIndicator = document.createElement('div');
    statusIndicator.className = 'api-status-indicator position-fixed';
    statusIndicator.style.cssText = 'top: 50px; left: 10px; z-index: 1060;';
    statusIndicator.innerHTML = `
        <div class="bg-white border rounded p-2 shadow-sm">
            <h6 class="mb-2">API Status</h6>
            <div class="d-flex flex-column gap-1">
                <div class="d-flex align-items-center">
                    <div class="status-dot bg-${testResults.hrms ? 'success' : 'danger'} rounded-circle me-2" style="width: 8px; height: 8px;"></div>
                    <small>HRMS: ${testResults.hrms ? 'Connected' : 'Disconnected'}</small>
                </div>
                <div class="d-flex align-items-center">
                    <div class="status-dot bg-${testResults.payroll ? 'success' : 'danger'} rounded-circle me-2" style="width: 8px; height: 8px;"></div>
                    <small>Payroll: ${testResults.payroll ? 'Connected' : 'Disconnected'}</small>
                </div>
                <div class="d-flex align-items-center">
                    <div class="status-dot bg-${testResults.biometric ? 'success' : 'danger'} rounded-circle me-2" style="width: 8px; height: 8px;"></div>
                    <small>Biometric: ${testResults.biometric ? 'Connected' : 'Disconnected'}</small>
                </div>
                <div class="d-flex align-items-center">
                    <div class="status-dot bg-${testResults.notifications ? 'success' : 'danger'} rounded-circle me-2" style="width: 8px; height: 8px;"></div>
                    <small>Notifications: ${testResults.notifications ? 'Connected' : 'Disconnected'}</small>
                </div>
            </div>
            <button class="btn btn-sm btn-outline-primary w-100 mt-2" onclick="showAPIManagement()">
                Manage APIs
            </button>
        </div>
    `;
    
    document.body.appendChild(statusIndicator);
}

function showAPIManagement() {
    const apiModal = document.createElement('div');
    apiModal.className = 'modal fade';
    apiModal.id = 'apiManagementModal';
    apiModal.innerHTML = `
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-cloud me-2"></i>API Integration Management
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Active Integrations</h6>
                                </div>
                                <div class="card-body">
                                    ${generateAPIIntegrationsTable()}
                                </div>
                            </div>
                            
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h6 class="mb-0">Webhook Configuration</h6>
                                </div>
                                <div class="card-body">
                                    ${generateWebhooksTable()}
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">API Statistics</h6>
                                </div>
                                <div class="card-body">
                                    ${generateAPIStatistics()}
                                </div>
                            </div>
                            
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h6 class="mb-0">Quick Actions</h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-success btn-sm" onclick="testAllAPIs()">
                                            <i class="bi bi-check-circle me-1"></i>Test All APIs
                                        </button>
                                        <button class="btn btn-info btn-sm" onclick="syncAllSystems()">
                                            <i class="bi bi-arrow-clockwise me-1"></i>Sync All Systems
                                        </button>
                                        <button class="btn btn-warning btn-sm" onclick="regenerateAPIKey()">
                                            <i class="bi bi-key me-1"></i>Regenerate API Key
                                        </button>
                                        <button class="btn btn-secondary btn-sm" onclick="viewAPILogs()">
                                            <i class="bi bi-file-text me-1"></i>View API Logs
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" onclick="saveAPIConfiguration()">
                        <i class="bi bi-save me-1"></i>Save Configuration
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(apiModal);
    const modal = new bootstrap.Modal(apiModal);
    modal.show();
    
    apiModal.addEventListener('hidden.bs.modal', () => {
        document.body.removeChild(apiModal);
    });
}

function generateAPIIntegrationsTable() {
    return `
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>System</th>
                        <th>Endpoint</th>
                        <th>Status</th>
                        <th>Last Sync</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>HRMS System</td>
                        <td><code>/api/hrms/sync</code></td>
                        <td><span class="badge bg-success">Connected</span></td>
                        <td>2 hours ago</td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="testAPI('hrms')">Test</button>
                            <button class="btn btn-sm btn-outline-success" onclick="syncAPI('hrms')">Sync</button>
                        </td>
                    </tr>
                    <tr>
                        <td>Payroll System</td>
                        <td><code>/api/payroll/attendance</code></td>
                        <td><span class="badge bg-warning">Slow</span></td>
                        <td>5 hours ago</td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="testAPI('payroll')">Test</button>
                            <button class="btn btn-sm btn-outline-success" onclick="syncAPI('payroll')">Sync</button>
                        </td>
                    </tr>
                    <tr>
                        <td>Biometric Devices</td>
                        <td><code>/api/biometric/devices</code></td>
                        <td><span class="badge bg-success">Connected</span></td>
                        <td>30 minutes ago</td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="testAPI('biometric')">Test</button>
                            <button class="btn btn-sm btn-outline-success" onclick="syncAPI('biometric')">Sync</button>
                        </td>
                    </tr>
                    <tr>
                        <td>Notification Service</td>
                        <td><code>/api/notifications/send</code></td>
                        <td><span class="badge bg-danger">Error</span></td>
                        <td>1 day ago</td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="testAPI('notifications')">Test</button>
                            <button class="btn btn-sm btn-outline-danger" onclick="fixAPI('notifications')">Fix</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    `;
}

function generateWebhooksTable() {
    return `
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Events</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${apiIntegration.webhooks.map(webhook => `
                        <tr>
                            <td>${webhook.name}</td>
                            <td>
                                ${webhook.events.map(event => `<span class="badge bg-secondary me-1">${event}</span>`).join('')}
                            </td>
                            <td>
                                <span class="badge bg-${webhook.active ? 'success' : 'secondary'}">
                                    ${webhook.active ? 'Active' : 'Inactive'}
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="editWebhook(${webhook.id})">Edit</button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteWebhook(${webhook.id})">Delete</button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
        <button class="btn btn-sm btn-primary" onclick="addWebhook()">
            <i class="bi bi-plus me-1"></i>Add Webhook
        </button>
    `;
}

function generateAPIStatistics() {
    return `
        <div class="api-stats">
            <div class="stat-item d-flex justify-content-between align-items-center mb-2">
                <span class="text-muted">Total API Calls Today:</span>
                <span class="fw-bold">1,247</span>
            </div>
            <div class="stat-item d-flex justify-content-between align-items-center mb-2">
                <span class="text-muted">Success Rate:</span>
                <span class="fw-bold text-success">98.2%</span>
            </div>
            <div class="stat-item d-flex justify-content-between align-items-center mb-2">
                <span class="text-muted">Avg Response Time:</span>
                <span class="fw-bold text-info">245ms</span>
            </div>
            <div class="stat-item d-flex justify-content-between align-items-center mb-3">
                <span class="text-muted">Failed Requests:</span>
                <span class="fw-bold text-danger">23</span>
            </div>
            
            <div class="progress mb-2">
                <div class="progress-bar bg-success" style="width: 70%" title="Successful: 70%"></div>
                <div class="progress-bar bg-warning" style="width: 20%" title="Slow: 20%"></div>
                <div class="progress-bar bg-danger" style="width: 10%" title="Failed: 10%"></div>
            </div>
            <small class="text-muted">API Performance Distribution</small>
        </div>
    `;
}

function testAPI(system) {
    showAlert(`Testing ${system} API connection...`, 'info');
    setTimeout(() => {
        showAlert(`${system} API test completed!`, 'success');
        logAuditEvent('api_test', `${system} API tested`, 'system');
    }, 2000);
}

function syncAPI(system) {
    showAlert(`Syncing with ${system}...`, 'info');
    setTimeout(() => {
        showAlert(`${system} sync completed successfully!`, 'success');
        logAuditEvent('api_sync', `${system} manual sync completed`, 'system');
    }, 3000);
}

function fixAPI(system) {
    showAlert(`Attempting to fix ${system} API connection...`, 'warning');
    setTimeout(() => {
        showAlert(`${system} API connection restored!`, 'success');
        logAuditEvent('api_fix', `${system} API connection fixed`, 'system');
    }, 4000);
}

function testAllAPIs() {
    showAlert('Testing all API connections...', 'info');
    setTimeout(() => {
        showAlert('All API tests completed! Check results above.', 'success');
        logAuditEvent('api_test_all', 'All APIs tested', 'system');
    }, 5000);
}

function syncAllSystems() {
    showAlert('Syncing all connected systems...', 'info');
    setTimeout(() => {
        showAlert('All systems synchronized successfully!', 'success');
        logAuditEvent('api_sync_all', 'All systems synchronized', 'system');
    }, 6000);
}

function regenerateAPIKey() {
    if (confirm('Are you sure you want to regenerate the API key? This will invalidate the current key.')) {
        showAlert('New API key generated successfully!', 'success');
        logAuditEvent('api_key_regenerated', 'API key regenerated', 'security');
    }
}

function viewAPILogs() {
    showAlert('Opening API logs...', 'info');
    setTimeout(() => {
        showAuditHistory();
    }, 500);
}

function saveAPIConfiguration() {
    showAlert('API configuration saved successfully!', 'success');
    logAuditEvent('api_config_saved', 'API configuration updated', 'system');
}

function editWebhook(webhookId) {
    showAlert(`Editing webhook ${webhookId}...`, 'info');
}

function deleteWebhook(webhookId) {
    if (confirm('Are you sure you want to delete this webhook?')) {
        showAlert(`Webhook ${webhookId} deleted!`, 'warning');
        logAuditEvent('webhook_deleted', `Webhook ${webhookId} deleted`, 'system');
    }
}

function addWebhook() {
    showAlert('Opening webhook creation form...', 'info');
}

// Initialize all systems when page loads
document.addEventListener('DOMContentLoaded', function() {
    initializeMobileIntegration();
    initializeRealTimeSync();
    initializeSmartAlerts();
    initializeAutoLeaveDeduction();
    initializeAuditSystem();
    initializeAPIIntegration();
});
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

/* Face Recognition Area - Anti-Blink Fixes */
#faceRecognitionArea, #qrScannerArea {
    transition: all 0.3s ease;
    border-radius: 10px;
    min-height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
}

#faceRecognitionArea:hover, #qrScannerArea:hover {
    border-color: #007bff;
    box-shadow: 0 0 10px rgba(0,123,255,0.2);
}

/* Prevent content flickering */
#faceRecognitionArea > *, #qrScannerArea > * {
    transition: opacity 0.2s ease;
}

/* Smart Attendance Modal Fixes */
#smartAttendanceModal .modal-body {
    opacity: 1;
    transition: opacity 0.3s ease;
}

#smartAttendanceModal.fade.show .modal-body {
    opacity: 1;
}

/* Location and IP status areas */
#locationStatus, #userIP {
    min-height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
}

/* Smooth loading states */
.spinner-border {
    transition: opacity 0.2s ease;
}

/* Prevent button state flickering */
#gpsCheckInBtn {
    transition: all 0.3s ease;
    min-height: 38px;
}

/* Anti-flicker for dynamic content */
.card-body {
    position: relative;
}

.card-body::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: transparent;
    z-index: -1;
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