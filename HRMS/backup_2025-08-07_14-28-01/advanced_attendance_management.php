<?php
$page_title = "Advanced Attendance Management";

// Include authentication and database
require_once '../auth_check.php';
require_once '../db.php';

// Include layouts
require_once 'hrms_header_simple.php';
require_once 'hrms_sidebar_simple.php';

// Include HRMS UI fix
$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'] ?? 'employee';

$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'] ?? 'employee';

$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'] ?? 'employee';

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'clock_in':
            $employeeId = $_POST['employee_id'] ?? 0;
            $notes = $_POST['notes'] ?? '';
            $location = $_POST['location'] ?? '';
            
            try {
                // Check if already clocked in today
                $today = date('Y-m-d');
                $stmt = $conn->prepare("
                    SELECT id FROM hr_attendance 
                    WHERE employee_id = ? AND date = ? AND clock_out_time IS NULL
                ");
                $stmt->bind_param('is', $employeeId, $today);
                $stmt->execute();
                
                if ($stmt->get_result()->num_rows > 0) {
                    echo json_encode(['success' => false, 'message' => 'Already clocked in today']);
                    exit;
                }
                
                // Clock in
                $clockInTime = date('Y-m-d H:i:s');
                $stmt = $conn->prepare("
                    INSERT INTO hr_attendance 
                    (employee_id, date, clock_in_time, clock_in_notes, clock_in_location, status) 
                    VALUES (?, ?, ?, ?, ?, 'present')
                ");
                $stmt->bind_param('issss', $employeeId, $today, $clockInTime, $notes, $location);
                
                if ($stmt->execute()) {
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Clocked in successfully',
                        'time' => date('g:i A', strtotime($clockInTime))
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to clock in']);
                }
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'clock_out':
            $employeeId = $_POST['employee_id'] ?? 0;
            $notes = $_POST['notes'] ?? '';
            $location = $_POST['location'] ?? '';
            
            try {
                // Find today's attendance record
                $today = date('Y-m-d');
                $stmt = $conn->prepare("
                    SELECT id, clock_in_time FROM hr_attendance 
                    WHERE employee_id = ? AND date = ? AND clock_out_time IS NULL
                ");
                $stmt->bind_param('is', $employeeId, $today);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    echo json_encode(['success' => false, 'message' => 'No clock-in record found for today']);
                    exit;
                }
                
                $attendance = $result->fetch_assoc();
                $clockOutTime = date('Y-m-d H:i:s');
                
                // Calculate hours worked
                $clockIn = new DateTime($attendance['clock_in_time']);
                $clockOut = new DateTime($clockOutTime);
                $hoursWorked = $clockIn->diff($clockOut)->h + ($clockIn->diff($clockOut)->i / 60);
                
                // Update attendance record
                $stmt = $conn->prepare("
                    UPDATE hr_attendance 
                    SET clock_out_time = ?, clock_out_notes = ?, clock_out_location = ?, hours_worked = ?
                    WHERE id = ?
                ");
                $stmt->bind_param('sssdi', $clockOutTime, $notes, $location, $hoursWorked, $attendance['id']);
                
                if ($stmt->execute()) {
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Clocked out successfully',
                        'time' => date('g:i A', strtotime($clockOutTime)),
                        'hours_worked' => round($hoursWorked, 2)
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to clock out']);
                }
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'mark_attendance':
            $employeeId = $_POST['employee_id'] ?? 0;
            $date = $_POST['date'] ?? '';
            $status = $_POST['status'] ?? '';
            $notes = $_POST['notes'] ?? '';
            
            try {
                // Check if attendance already exists
                $stmt = $conn->prepare("
                    SELECT id FROM hr_attendance WHERE employee_id = ? AND date = ?
                ");
                $stmt->bind_param('is', $employeeId, $date);
                $stmt->execute();
                
                if ($stmt->get_result()->num_rows > 0) {
                    // Update existing record
                    $stmt = $conn->prepare("
                        UPDATE hr_attendance 
                        SET status = ?, notes = ? 
                        WHERE employee_id = ? AND date = ?
                    ");
                    $stmt->bind_param('ssis', $status, $notes, $employeeId, $date);
                } else {
                    // Insert new record
                    $stmt = $conn->prepare("
                        INSERT INTO hr_attendance (employee_id, date, status, notes) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->bind_param('isss', $employeeId, $date, $status, $notes);
                }
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Attendance marked successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to mark attendance']);
                }
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'get_attendance_status':
            $employeeId = $_POST['employee_id'] ?? 0;
            $date = $_POST['date'] ?? date('Y-m-d');
            
            try {
                $stmt = $conn->prepare("
                    SELECT * FROM hr_attendance 
                    WHERE employee_id = ? AND date = ?
                ");
                $stmt->bind_param('is', $employeeId, $date);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $attendance = $result->fetch_assoc();
                    echo json_encode(['success' => true, 'attendance' => $attendance]);
                } else {
                    echo json_encode(['success' => true, 'attendance' => null]);
                }
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'get_attendance_details':
            $attendanceId = $_POST['attendance_id'] ?? 0;
            
            try {
                $stmt = $conn->prepare("
                    SELECT 
                        a.*,
                        e.first_name, e.last_name, e.employee_id as emp_id,
                        d.name as department_name
                    FROM hr_attendance a
                    LEFT JOIN hr_employees e ON a.employee_id = e.id
                    LEFT JOIN hr_departments d ON e.department_id = d.id
                    WHERE a.id = ?
                ");
                $stmt->bind_param('i', $attendanceId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $attendance = $result->fetch_assoc();
                    echo json_encode(['success' => true, 'attendance' => $attendance]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Attendance record not found']);
                }
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
    }
}

// Get current employee info
$currentEmployee = null;
if ($currentUserRole !== 'hr' && $currentUserRole !== 'admin') {
    try {
        $stmt = $conn->prepare("SELECT * FROM hr_employees WHERE user_id = ?");
        $stmt->bind_param('i', $currentUserId);
        $stmt->execute();
        $currentEmployee = $stmt->get_result()->fetch_assoc();
    } catch (Exception $e) {
        error_log("Current employee fetch error: " . $e->getMessage());
    }
}

// Get attendance records
$attendanceRecords = [];
$dateFilter = $_GET['date'] ?? date('Y-m-d');
$employeeFilter = $_GET['employee'] ?? '';

try {
    $whereClause = "WHERE a.date = '$dateFilter'";
    
    if ($currentUserRole !== 'hr' && $currentUserRole !== 'admin') {
        $whereClause .= " AND e.user_id = $currentUserId";
    } elseif ($employeeFilter) {
        $whereClause .= " AND a.employee_id = $employeeFilter";
    }
    
    $result = $conn->query("
        SELECT 
            a.*,
            e.first_name, e.last_name, e.employee_id as emp_id,
            d.name as department_name
        FROM hr_attendance a
        LEFT JOIN hr_employees e ON a.employee_id = e.id
        LEFT JOIN hr_departments d ON e.department_id = d.id
        $whereClause
        ORDER BY a.clock_in_time DESC
    ");
    
    while ($row = $result->fetch_assoc()) {
        $attendanceRecords[] = $row;
    }
} catch (Exception $e) {
    error_log("Attendance records fetch error: " . $e->getMessage());
}

// Get employees for dropdown (HR only)
$employees = [];
if ($currentUserRole === 'hr' || $currentUserRole === 'admin') {
    try {
        $result = $conn->query("
            SELECT id, employee_id, first_name, last_name 
            FROM hr_employees 
            WHERE is_active = 1 
            ORDER BY first_name, last_name
        ");
        while ($row = $result->fetch_assoc()) {
            $employees[] = $row;
        }
    } catch (Exception $e) {
        error_log("Employees fetch error: " . $e->getMessage());
    }
}

// Calculate statistics for today
$todayStats = [
    'present' => 0,
    'absent' => 0,
    'late' => 0,
    'on_leave' => 0
];

$today = date('Y-m-d');
try {
    $result = $conn->query("
        SELECT status, COUNT(*) as count 
        FROM hr_attendance 
        WHERE date = '$today' 
        GROUP BY status
    ");
    
    while ($row = $result->fetch_assoc()) {
        if (isset($todayStats[$row['status']])) {
            $todayStats[$row['status']] = $row['count'];
        }
    }
} catch (Exception $e) {
    error_log("Today stats fetch error: " . $e->getMessage());
}

// Check if current user is clocked in
$isClockedIn = false;
$todayAttendance = null;
if ($currentEmployee) {
    try {
        $stmt = $conn->prepare("
            SELECT * FROM hr_attendance 
            WHERE employee_id = ? AND date = ?
        ");
        $stmt->bind_param('is', $currentEmployee['id'], $today);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $todayAttendance = $result->fetch_assoc();
            $isClockedIn = ($todayAttendance['clock_in_time'] && !$todayAttendance['clock_out_time']);
        }
    } catch (Exception $e) {
        error_log("Clock status check error: " . $e->getMessage());
    }
}
?>

<!-- Page Content Starts Here -->
<div class="container-fluid">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h2 mb-1">
                            <i class="fas fa-clock text-primary me-2"></i>
                            Advanced Attendance Management
                        </h1>
                        <p class="text-muted mb-0">Real-time attendance tracking with comprehensive reporting</p>
                    </div>
                    <div class="d-flex gap-2">
                        <div class="current-time-display">
                            <div class="time" id="currentTime"><?= date('g:i:s A') ?></div>
                            <div class="date"><?= date('M j, Y') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions & Clock In/Out -->
        <?php if ($currentEmployee): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm clock-card">
                    <div class="card-body p-4">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="d-flex align-items-center">
                                    <div class="employee-avatar-large me-3">
                                        <?= strtoupper(substr($currentEmployee['first_name'], 0, 1) . substr($currentEmployee['last_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <h4 class="mb-1"><?= htmlspecialchars($currentEmployee['first_name'] . ' ' . $currentEmployee['last_name']) ?></h4>
                                        <p class="text-muted mb-0"><?= htmlspecialchars($currentEmployee['emp_id'] ?? 'N/A') ?></p>
                                        <div class="status-indicator mt-2">
                                            <?php if ($isClockedIn): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-circle me-1"></i>Clocked In
                                                </span>
                                                <small class="text-muted ms-2">
                                                    Since <?= date('g:i A', strtotime($todayAttendance['clock_in_time'])) ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">
                                                    <i class="fas fa-circle me-1"></i>Not Clocked In
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 text-end">
                                <div class="clock-actions">
                                    <?php if (!$isClockedIn): ?>
                                        <button class="btn btn-success btn-lg" onclick="showClockModal('in')">
                                            <i class="fas fa-sign-in-alt me-2"></i>Clock In
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-danger btn-lg" onclick="showClockModal('out')">
                                            <i class="fas fa-sign-out-alt me-2"></i>Clock Out
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                <div class="card border-0 shadow-sm stats-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-success bg-opacity-10 rounded-3 p-3 me-3">
                                <i class="fas fa-user-check text-success fs-2"></i>
                            </div>
                            <div>
                                <h3 class="fw-bold text-success mb-0"><?= $todayStats['present'] ?></h3>
                                <p class="text-muted mb-0 small">Present Today</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                <div class="card border-0 shadow-sm stats-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-danger bg-opacity-10 rounded-3 p-3 me-3">
                                <i class="fas fa-user-times text-danger fs-2"></i>
                            </div>
                            <div>
                                <h3 class="fw-bold text-danger mb-0"><?= $todayStats['absent'] ?></h3>
                                <p class="text-muted mb-0 small">Absent Today</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                <div class="card border-0 shadow-sm stats-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-warning bg-opacity-10 rounded-3 p-3 me-3">
                                <i class="fas fa-clock text-warning fs-2"></i>
                            </div>
                            <div>
                                <h3 class="fw-bold text-warning mb-0"><?= $todayStats['late'] ?></h3>
                                <p class="text-muted mb-0 small">Late Arrivals</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                <div class="card border-0 shadow-sm stats-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-info bg-opacity-10 rounded-3 p-3 me-3">
                                <i class="fas fa-calendar-alt text-info fs-2"></i>
                            </div>
                            <div>
                                <h3 class="fw-bold text-info mb-0"><?= $todayStats['on_leave'] ?></h3>
                                <p class="text-muted mb-0 small">On Leave</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Records -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-list text-secondary me-2"></i>
                                Attendance Records
                            </h5>
                            <div class="d-flex gap-2">
                                <input type="date" class="form-control form-control-sm" 
                                       value="<?= $dateFilter ?>" 
                                       onchange="filterByDate(this.value)">
                                
                                <?php if ($currentUserRole === 'hr' || $currentUserRole === 'admin'): ?>
                                    <select class="form-select form-select-sm" onchange="filterByEmployee(this.value)">
                                        <option value="">All Employees</option>
                                        <?php foreach ($employees as $emp): ?>
                                            <option value="<?= $emp['id'] ?>" <?= $employeeFilter == $emp['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    
                                    <button class="btn btn-outline-primary btn-sm" onclick="showMarkAttendanceModal()">
                                        <i class="fas fa-plus me-1"></i>Mark Attendance
                                    </button>
                                <?php endif; ?>
                                
                                <button class="btn btn-outline-success btn-sm" onclick="exportAttendanceData()">
                                    <i class="fas fa-download me-1"></i>Export
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="attendanceTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Employee</th>
                                        <th>Clock In</th>
                                        <th>Clock Out</th>
                                        <th>Hours Worked</th>
                                        <th>Status</th>
                                        <th>Location</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendanceRecords as $record): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="employee-avatar-small me-2">
                                                        <?= strtoupper(substr($record['first_name'], 0, 1) . substr($record['last_name'], 0, 1)) ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-medium"><?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) ?></div>
                                                        <small class="text-muted"><?= htmlspecialchars($record['emp_id']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($record['clock_in_time']): ?>
                                                    <div><?= date('g:i A', strtotime($record['clock_in_time'])) ?></div>
                                                    <small class="text-muted"><?= date('M j', strtotime($record['clock_in_time'])) ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">Not clocked in</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($record['clock_out_time']): ?>
                                                    <div><?= date('g:i A', strtotime($record['clock_out_time'])) ?></div>
                                                    <small class="text-muted"><?= date('M j', strtotime($record['clock_out_time'])) ?></small>
                                                <?php else: ?>
                                                    <span class="text-warning">Still working</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($record['hours_worked']): ?>
                                                    <span class="fw-medium"><?= number_format($record['hours_worked'], 2) ?>h</span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $statusClasses = [
                                                    'present' => 'success',
                                                    'absent' => 'danger',
                                                    'late' => 'warning',
                                                    'on_leave' => 'info',
                                                    'half_day' => 'secondary'
                                                ];
                                                $statusClass = $statusClasses[$record['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?= $statusClass ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $record['status'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($record['clock_in_location'] ?? 'Office') ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-outline-primary btn-sm" 
                                                            onclick="viewAttendanceDetails(<?= $record['id'] ?>)"
                                                            title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if ($currentUserRole === 'hr' || $currentUserRole === 'admin'): ?>
                                                        <button class="btn btn-outline-secondary btn-sm" 
                                                                onclick="editAttendance(<?= $record['id'] ?>)"
                                                                title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Clock In/Out Modal -->
<div class="modal fade" id="clockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="clockModalTitle">
                    <i class="fas fa-clock me-2"></i>Clock In
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="clockForm">
                    <input type="hidden" id="clockAction" name="action">
                    <input type="hidden" name="employee_id" value="<?= $currentEmployee['id'] ?? 0 ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Current Time</label>
                        <input type="text" class="form-control" value="<?= date('g:i A - M j, Y') ?>" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <select class="form-select" name="location">
                            <option value="Office">Office</option>
                            <option value="Remote">Remote/Work from Home</option>
                            <option value="Client Site">Client Site</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" name="notes" rows="2" 
                                  placeholder="Any additional notes..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="clockSubmitBtn" onclick="submitClock()">
                    <i class="fas fa-check me-1"></i>Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Mark Attendance Modal -->
<div class="modal fade" id="markAttendanceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-clipboard-check me-2"></i>Mark Attendance
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="markAttendanceForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Employee <span class="text-danger">*</span></label>
                            <select class="form-select" name="employee_id" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?= $emp['id'] ?>">
                                        <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'] . ' (' . $emp['employee_id'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="date" required value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" name="status" required onchange="updateAttendanceFields(this.value)">
                                <option value="">Select Status</option>
                                <option value="present">Present</option>
                                <option value="absent">Absent</option>
                                <option value="late">Late</option>
                                <option value="half_day">Half Day</option>
                                <option value="on_leave">On Leave</option>
                                <option value="sick_leave">Sick Leave</option>
                                <option value="work_from_home">Work from Home</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3" id="timeFields" style="display: none;">
                            <label class="form-label">Clock In Time</label>
                            <input type="time" class="form-control" name="clock_in_time">
                        </div>
                    </div>
                    
                    <div class="row" id="additionalTimeFields" style="display: none;">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Clock Out Time</label>
                            <input type="time" class="form-control" name="clock_out_time">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Break Duration (minutes)</label>
                            <input type="number" class="form-control" name="break_duration" placeholder="30" min="0">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3" 
                                  placeholder="Any additional notes or reason for absence..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <select class="form-select" name="location">
                            <option value="Office">Office</option>
                            <option value="Remote">Remote/Work from Home</option>
                            <option value="Client Site">Client Site</option>
                            <option value="Field Work">Field Work</option>
                            <option value="Meeting">External Meeting</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </form>
                
                <!-- Quick Actions -->
                <div class="mt-4 p-3 bg-light rounded">
                    <h6 class="mb-3">Quick Actions:</h6>
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn btn-outline-success btn-sm" onclick="setQuickAttendance('present', '09:00', '17:00')">
                            <i class="fas fa-check me-1"></i>Full Day Present
                        </button>
                        <button class="btn btn-outline-warning btn-sm" onclick="setQuickAttendance('half_day', '09:00', '13:00')">
                            <i class="fas fa-clock me-1"></i>Half Day
                        </button>
                        <button class="btn btn-outline-info btn-sm" onclick="setQuickAttendance('work_from_home', '09:00', '17:00')">
                            <i class="fas fa-home me-1"></i>Work from Home
                        </button>
                        <button class="btn btn-outline-danger btn-sm" onclick="setQuickAttendance('absent', '', '')">
                            <i class="fas fa-times me-1"></i>Absent
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitMarkAttendance()">
                    <i class="fas fa-save me-1"></i>Save Attendance
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Details Modal -->
<div class="modal fade" id="attendanceDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-info-circle me-2"></i>Attendance Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="attendanceDetailsContent">
                    <!-- Content will be loaded dynamically -->
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading attendance details...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>


@media (max-width: 768px) {
    
}

.current-time-display {
    text-align: right;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    padding: 1rem;
    border-radius: 12px;
    color: white;
}

.current-time-display .time {
    font-size: 1.5rem;
    font-weight: bold;
    font-family: 'Courier New', monospace;
}

.current-time-display .date {
    font-size: 0.875rem;
    opacity: 0.8;
}

.clock-card {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border-radius: 15px;
    overflow: hidden;
    position: relative;
}

.clock-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="white" opacity="0.1"/></svg>') repeat;
}

.employee-avatar-large {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
    font-weight: bold;
    border: 3px solid rgba(255, 255, 255, 0.3);
}

.employee-avatar-small {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(45deg, #007bff, #6610f2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.75rem;
    font-weight: bold;
}

.stats-card {
    transition: all 0.3s ease;
    border-radius: 12px;
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.95);
}

.stats-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.card {
    border-radius: 12px;
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.95);
}

.table th {
    border-top: none;
    font-weight: 600;
    color: #6c757d;
    font-size: 0.875rem;
}

.status-indicator .badge {
    font-size: 0.75rem;
}
</style>

<script>
// Update current time every second
function updateCurrentTime() {
    const now = new Date();
    const timeStr = now.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    });
    
    const timeElement = document.getElementById('currentTime');
    if (timeElement) {
        timeElement.textContent = timeStr;
    }
}

// Initialize time updates
setInterval(updateCurrentTime, 1000);

// Show clock modal
function showClockModal(action) {
    const modal = new bootstrap.Modal(document.getElementById('clockModal'));
    const title = document.getElementById('clockModalTitle');
    const submitBtn = document.getElementById('clockSubmitBtn');
    const actionInput = document.getElementById('clockAction');
    
    if (action === 'in') {
        title.innerHTML = '<i class="fas fa-sign-in-alt me-2"></i>Clock In';
        submitBtn.innerHTML = '<i class="fas fa-sign-in-alt me-1"></i>Clock In';
        submitBtn.className = 'btn btn-success';
        actionInput.value = 'clock_in';
    } else {
        title.innerHTML = '<i class="fas fa-sign-out-alt me-2"></i>Clock Out';
        submitBtn.innerHTML = '<i class="fas fa-sign-out-alt me-1"></i>Clock Out';
        submitBtn.className = 'btn btn-danger';
        actionInput.value = 'clock_out';
    }
    
    modal.show();
}

// Submit clock in/out
function submitClock() {
    const form = document.getElementById('clockForm');
    const formData = new FormData(form);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Network error: ' + error.message);
    });
}

// Filter functions
function filterByDate(date) {
    window.location.href = `?date=${date}`;
}

function filterByEmployee(employeeId) {
    const currentDate = new URLSearchParams(window.location.search).get('date') || '<?= date('Y-m-d') ?>';
    window.location.href = `?date=${currentDate}&employee=${employeeId}`;
}

// Other functions
function showMarkAttendanceModal() {
    const modal = new bootstrap.Modal(document.getElementById('markAttendanceModal'));
    modal.show();
}

function updateAttendanceFields(status) {
    const timeFields = document.getElementById('timeFields');
    const additionalTimeFields = document.getElementById('additionalTimeFields');
    
    if (status === 'present' || status === 'late' || status === 'half_day' || status === 'work_from_home') {
        timeFields.style.display = 'block';
        if (status === 'present' || status === 'work_from_home') {
            additionalTimeFields.style.display = 'block';
        } else {
            additionalTimeFields.style.display = 'none';
        }
    } else {
        timeFields.style.display = 'none';
        additionalTimeFields.style.display = 'none';
    }
}

function setQuickAttendance(status, clockIn, clockOut) {
    const form = document.getElementById('markAttendanceForm');
    form.querySelector('[name="status"]').value = status;
    
    updateAttendanceFields(status);
    
    if (clockIn) {
        const clockInField = form.querySelector('[name="clock_in_time"]');
        if (clockInField) clockInField.value = clockIn;
    }
    
    if (clockOut) {
        const clockOutField = form.querySelector('[name="clock_out_time"]');
        if (clockOutField) clockOutField.value = clockOut;
    }
    
    // Set appropriate location
    const locationField = form.querySelector('[name="location"]');
    if (status === 'work_from_home') {
        locationField.value = 'Remote';
    } else if (status === 'present') {
        locationField.value = 'Office';
    }
}

function submitMarkAttendance() {
    const form = document.getElementById('markAttendanceForm');
    const formData = new FormData(form);
    formData.append('action', 'mark_attendance');
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    // Calculate hours worked if both times are provided
    const clockInTime = formData.get('clock_in_time');
    const clockOutTime = formData.get('clock_out_time');
    const breakDuration = parseInt(formData.get('break_duration') || 0);
    
    if (clockInTime && clockOutTime) {
        const clockIn = new Date(`2000-01-01 ${clockInTime}`);
        const clockOut = new Date(`2000-01-01 ${clockOutTime}`);
        const hoursWorked = (clockOut - clockIn) / (1000 * 60 * 60) - (breakDuration / 60);
        formData.append('hours_worked', hoursWorked.toFixed(2));
    }
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Attendance marked successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Network error: ' + error.message);
    });
}

function viewAttendanceDetails(attendanceId) {
    const modal = new bootstrap.Modal(document.getElementById('attendanceDetailsModal'));
    
    // Show loading content
    document.getElementById('attendanceDetailsContent').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 text-muted">Loading attendance details...</p>
        </div>
    `;
    
    modal.show();
    
    // Fetch attendance details
    const formData = new FormData();
    formData.append('action', 'get_attendance_details');
    formData.append('attendance_id', attendanceId);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayAttendanceDetails(data.attendance);
        } else {
            document.getElementById('attendanceDetailsContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Failed to load attendance details: ${data.message}
                </div>
            `;
        }
    })
    .catch(error => {
        document.getElementById('attendanceDetailsContent').innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Network error: ${error.message}
            </div>
        `;
    });
}

function displayAttendanceDetails(attendance) {
    const clockInTime = attendance.clock_in_time ? new Date(attendance.clock_in_time).toLocaleString() : 'Not clocked in';
    const clockOutTime = attendance.clock_out_time ? new Date(attendance.clock_out_time).toLocaleString() : 'Not clocked out';
    const hoursWorked = attendance.hours_worked ? parseFloat(attendance.hours_worked).toFixed(2) + ' hours' : 'Not calculated';
    
    const statusClass = {
        'present': 'success',
        'absent': 'danger',
        'late': 'warning',
        'on_leave': 'info',
        'half_day': 'secondary'
    }[attendance.status] || 'secondary';
    
    document.getElementById('attendanceDetailsContent').innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <div class="card border-0 bg-light mb-3">
                    <div class="card-body">
                        <h6 class="card-title text-primary">
                            <i class="fas fa-user me-2"></i>Employee Information
                        </h6>
                        <p class="mb-1"><strong>Name:</strong> ${attendance.first_name} ${attendance.last_name}</p>
                        <p class="mb-1"><strong>Employee ID:</strong> ${attendance.emp_id}</p>
                        <p class="mb-0"><strong>Department:</strong> ${attendance.department_name || 'Not specified'}</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card border-0 bg-light mb-3">
                    <div class="card-body">
                        <h6 class="card-title text-success">
                            <i class="fas fa-calendar me-2"></i>Date & Status
                        </h6>
                        <p class="mb-1"><strong>Date:</strong> ${new Date(attendance.date).toLocaleDateString()}</p>
                        <p class="mb-0">
                            <strong>Status:</strong> 
                            <span class="badge bg-${statusClass}">
                                ${attendance.status.charAt(0).toUpperCase() + attendance.status.slice(1).replace('_', ' ')}
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-4">
                <div class="card border-0 bg-light mb-3">
                    <div class="card-body text-center">
                        <i class="fas fa-sign-in-alt text-success fs-2 mb-2"></i>
                        <h6 class="text-success">Clock In</h6>
                        <p class="mb-0">${clockInTime}</p>
                        ${attendance.clock_in_location ? `<small class="text-muted">${attendance.clock_in_location}</small>` : ''}
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-0 bg-light mb-3">
                    <div class="card-body text-center">
                        <i class="fas fa-sign-out-alt text-danger fs-2 mb-2"></i>
                        <h6 class="text-danger">Clock Out</h6>
                        <p class="mb-0">${clockOutTime}</p>
                        ${attendance.clock_out_location ? `<small class="text-muted">${attendance.clock_out_location}</small>` : ''}
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-0 bg-light mb-3">
                    <div class="card-body text-center">
                        <i class="fas fa-clock text-info fs-2 mb-2"></i>
                        <h6 class="text-info">Hours Worked</h6>
                        <p class="mb-0">${hoursWorked}</p>
                    </div>
                </div>
            </div>
        </div>
        
        ${attendance.clock_in_notes || attendance.clock_out_notes || attendance.notes ? `
            <div class="card border-0 bg-light">
                <div class="card-body">
                    <h6 class="card-title text-secondary">
                        <i class="fas fa-sticky-note me-2"></i>Notes
                    </h6>
                    ${attendance.clock_in_notes ? `<p class="mb-1"><strong>Clock In:</strong> ${attendance.clock_in_notes}</p>` : ''}
                    ${attendance.clock_out_notes ? `<p class="mb-1"><strong>Clock Out:</strong> ${attendance.clock_out_notes}</p>` : ''}
                    ${attendance.notes ? `<p class="mb-0"><strong>General:</strong> ${attendance.notes}</p>` : ''}
                </div>
            </div>
        ` : ''}
    `;
}

function editAttendance(attendanceId) {
    // For now, redirect to mark attendance with pre-filled data
    alert('Edit attendance functionality will open the mark attendance modal with pre-filled data!');
}

function exportAttendanceData() {
    // Create export URL with current filters
    const dateFilter = new URLSearchParams(window.location.search).get('date') || '<?= date('Y-m-d') ?>';
    const employeeFilter = new URLSearchParams(window.location.search).get('employee') || '';
    
    let exportUrl = `export_attendance.php?date=${dateFilter}`;
    if (employeeFilter) {
        exportUrl += `&employee=${employeeFilter}`;
    }
    
    // Open export in new window
    window.open(exportUrl, '_blank');
}
</script>

<?php require_once 'hrms_footer_simple.php'; 
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