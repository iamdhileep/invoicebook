<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Advanced Time Tracking';

// Handle search and filtering
$search = $_GET['search'] ?? '';
$department = $_GET['department'] ?? '';
$date_filter = $_GET['date'] ?? date('Y-m-d');

// Set timezone
date_default_timezone_set('Asia/Kolkata');
$today = date('Y-m-d');

// Build WHERE clause for filtered queries
$where = "WHERE 1=1";
if ($search) {
    $where .= " AND (e.name LIKE '%" . mysqli_real_escape_string($conn, $search) . "%' 
                OR e.employee_code LIKE '%" . mysqli_real_escape_string($conn, $search) . "%')";
}
if ($department) {
    $where .= " AND e.position = '" . mysqli_real_escape_string($conn, $department) . "'";
}

// Get comprehensive statistics
$totalEmployees = 0;
$presentToday = 0;
$absentToday = 0;
$lateToday = 0;
$onTimeToday = 0;
$totalSalary = 0;
$avgWorkingHours = 0;
$monthlyPayroll = 0;

// Total Employees
$result = $conn->query("SELECT COUNT(*) as count FROM employees");
if ($result) {
    $totalEmployees = $result->fetch_assoc()['count'] ?? 0;
}

// Present Today from attendance table
$result = $conn->query("SELECT COUNT(DISTINCT employee_id) as count FROM attendance WHERE date = '$today'");
if ($result) {
    $presentToday = $result->fetch_assoc()['count'] ?? 0;
}

// Absent calculation
$absentToday = $totalEmployees - $presentToday;

// Total Salary
$result = $conn->query("SELECT SUM(monthly_salary) as total FROM employees WHERE monthly_salary IS NOT NULL");
if ($result) {
    $totalSalary = $result->fetch_assoc()['total'] ?? 0;
}

// Get departments for filter
$departments = $conn->query("SELECT DISTINCT position FROM employees WHERE position IS NOT NULL AND position != '' ORDER BY position");

// Recent attendance records for today
$recentAttendance = $conn->query("
    SELECT e.name, e.employee_code, e.position, a.check_in_time, a.check_out_time, a.status, a.working_hours
    FROM employees e 
    LEFT JOIN attendance a ON e.employee_id = a.employee_id AND a.date = '$today'
    ORDER BY a.check_in_time DESC 
    LIMIT 10
");

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header with Actions -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="h5 mb-0">🕒 Advanced Time Tracking</h1>
                <p class="text-muted small">Comprehensive employee time management and payroll system</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#bulkAttendanceModal">
                    <i class="bi bi-check-all"></i> Bulk Attendance
                </button>
                <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#exportModal">
                    <i class="bi bi-download"></i> Export
                </button>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#payrollConfigModal">
                    <i class="bi bi-gear"></i> Payroll Config
                </button>
            </div>
        </div>

        <!-- Search and Filter Bar -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Search Employee</label>
                        <input type="text" class="form-control form-control-sm" name="search" 
                               value="<?= htmlspecialchars($search) ?>" placeholder="Name or Employee ID">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Department</label>
                        <select class="form-select form-select-sm" name="department">
                            <option value="">All Departments</option>
                            <?php while($dept = $departments->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($dept['position']) ?>" 
                                        <?= $department == $dept['position'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['position']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Date</label>
                        <input type="date" class="form-control form-control-sm" name="date" value="<?= $date_filter ?>">
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex gap-1">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-search"></i> Filter
                            </button>
                            <a href="?" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-arrow-clockwise"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Function to get today's statistics
function getTodaysStats($conn) {
    $today = date('Y-m-d');
    $late_time = '09:30:00'; // Define what constitutes late arrival
    $stats = [
        'late' => 0,
        'on_time' => 0,
        'wfh' => 0,
        'remote_clockins' => 0
    ];
    
    // Late arrivals - using attendance table instead of time_clock
    $query = "SELECT COUNT(*) as count FROM attendance WHERE date = ? AND check_in_time > ? AND check_in_time IS NOT NULL";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $today, $late_time);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $stats['late'] = $result->fetch_assoc()['count'];
    }
    
    // On time arrivals - using attendance table
    $query = "SELECT COUNT(*) as count FROM attendance WHERE date = ? AND check_in_time <= ? AND check_in_time IS NOT NULL";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $today, $late_time);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $stats['on_time'] = $result->fetch_assoc()['count'];
    }
    
    // WFH/Remote work (using status field in attendance table)
    $query = "SELECT COUNT(*) as count FROM attendance WHERE date = ? AND (status = 'WFH' OR status = 'Remote')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $stats['wfh'] = $result->fetch_assoc()['count'];
    }
    
    // Remote clock-ins (using status field)
    $query = "SELECT COUNT(*) as count FROM attendance WHERE date = ? AND status != 'Present' AND check_in_time IS NOT NULL";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $stats['remote_clockins'] = $result->fetch_assoc()['count'];
    }
    
    return $stats;
}

// Get today's stats
$todaysStats = getTodaysStats($conn);

// Get time off requests - Using a simpler approach since time_off_requests table may not exist
$timeOffRequests = [];
// Check if time_off_requests table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'time_off_requests'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    $timeOffQuery = "SELECT tor.*, e.name as employee_name, e.position as department 
                     FROM time_off_requests tor 
                     LEFT JOIN employees e ON tor.employee_id = e.employee_id 
                     WHERE tor.status = 'Pending' 
                     ORDER BY tor.created_at DESC 
                     LIMIT 5";
    $timeOffResult = $conn->query($timeOffQuery);
    if ($timeOffResult) {
        while ($row = $timeOffResult->fetch_assoc()) {
            $timeOffRequests[] = $row;
        }
    }
}

// Get overtime requests - Using a simpler approach since overtime_requests table may not exist
$overtimeRequests = [];
$tableCheck = $conn->query("SHOW TABLES LIKE 'overtime_requests'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    $overtimeQuery = "SELECT or_req.*, e.name as employee_name, e.position as department 
                      FROM overtime_requests or_req 
                      LEFT JOIN employees e ON or_req.employee_id = e.employee_id 
                      WHERE or_req.status = 'Pending' 
                      ORDER BY or_req.created_at DESC 
                      LIMIT 5";
    $overtimeResult = $conn->query($overtimeQuery);
    if ($overtimeResult) {
        while ($row = $overtimeResult->fetch_assoc()) {
            $overtimeRequests[] = $row;
        }
    }
}

// Generate calendar for current month
function generateCalendar($year, $month, $conn) {
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $first_day = date('w', mktime(0, 0, 0, $month, 1, $year));
    
    $calendar = [];
    $current_day = 1;
    
    // Get attendance data for the month using the correct attendance table
    $attendance_data = [];
    $query = "SELECT date, COUNT(*) as present_count, 
                     SUM(CASE WHEN check_in_time > '09:30:00' THEN 1 ELSE 0 END) as late_count
              FROM attendance 
              WHERE YEAR(date) = ? AND MONTH(date) = ? 
              GROUP BY date";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("ii", $year, $month);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $attendance_data[$row['date']] = $row;
            }
        }
    }
    
    // Build calendar array
    for ($week = 0; $week < 6; $week++) {
        for ($day = 0; $day < 7; $day++) {
            if ($week == 0 && $day < $first_day) {
                $calendar[$week][$day] = '';
            } elseif ($current_day > $days_in_month) {
                $calendar[$week][$day] = '';
            } else {
                $date_key = sprintf('%04d-%02d-%02d', $year, $month, $current_day);
                $calendar[$week][$day] = [
                    'day' => $current_day,
                    'attendance' => $attendance_data[$date_key] ?? null
                ];
                $current_day++;
            }
        }
        if ($current_day > $days_in_month) break;
    }
    
    return $calendar;
}

$current_year = date('Y');
$current_month = date('n');
$calendar = generateCalendar($current_year, $current_month, $conn);
?>

<style>
    .time-tracking-container {
        padding: 20px;
        background: #f8f9fa;
        min-height: 100vh;
    }
    
    .stats-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        text-align: center;
        height: 120px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        margin-bottom: 20px;
        transition: transform 0.2s ease;
    }
    
    .stats-card:hover {
        transform: translateY(-2px);
    }
    
    .stats-card.late {
        border-left: 4px solid #e74c3c;
    }
    
    .stats-card.on-time {
        border-left: 4px solid #27ae60;
    }
    
    .stats-card.wfh {
        border-left: 4px solid #f39c12;
    }
    
    .stats-card.remote {
        border-left: 4px solid #9b59b6;
    }
    
    .stats-number {
        font-size: 2.5rem;
        font-weight: bold;
        color: #2c3e50;
        margin-bottom: 5px;
    }
    
    .stats-label {
        color: #7f8c8d;
        font-size: 0.9rem;
        font-weight: 500;
    }
    
    .section-card {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    
    .request-item {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 10px;
        border-left: 4px solid #3498db;
    }
    
    .request-item:last-child {
        margin-bottom: 0;
    }
    
    .request-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }
    
    .employee-name {
        font-weight: 600;
        color: #2c3e50;
    }
    
    .request-date {
        color: #7f8c8d;
        font-size: 0.85rem;
    }
    
    .request-reason {
        color: #34495e;
        font-size: 0.9rem;
        margin-bottom: 10px;
    }
    
    .request-actions {
        display: flex;
        gap: 8px;
    }
    
    .btn-approve {
        background: #27ae60;
        border: none;
        color: white;
        padding: 4px 12px;
        border-radius: 4px;
        font-size: 0.8rem;
    }
    
    .btn-reject {
        background: #e74c3c;
        border: none;
        color: white;
        padding: 4px 12px;
        border-radius: 4px;
        font-size: 0.8rem;
    }
    
    .calendar-container {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .calendar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .calendar-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .calendar-table th,
    .calendar-table td {
        width: 14.28%;
        height: 80px;
        vertical-align: top;
        padding: 8px;
        border: 1px solid #e9ecef;
        position: relative;
    }
    
    .calendar-table th {
        background: #f8f9fa;
        height: 40px;
        text-align: center;
        font-weight: 600;
        color: #495057;
    }
    
    .calendar-day {
        font-weight: 600;
        font-size: 0.9rem;
        color: #495057;
    }
    
    .attendance-indicator {
        position: absolute;
        bottom: 4px;
        left: 4px;
        right: 4px;
        height: 6px;
        border-radius: 3px;
    }
    
    .attendance-good {
        background: linear-gradient(90deg, #27ae60, #2ecc71);
    }
    
    .attendance-partial {
        background: linear-gradient(90deg, #f39c12, #e67e22);
    }
    
    .attendance-poor {
        background: linear-gradient(90deg, #e74c3c, #c0392b);
    }
    
    .attendance-none {
        background: #ecf0f1;
    }
    
    .modal-body {
        padding: 20px;
    }
    
    .form-floating {
        margin-bottom: 15px;
    }
</style>

<div class="main-content">
    <?php include '../../layouts/sidebar.php'; ?>
    
    <div class="content">
        <div class="time-tracking-container">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Advanced Time Tracking System</h1>
                    <p class="text-muted">Manage employee time, attendance, and requests</p>
                </div>
                <div class="btn-group">
                    <a href="schedules.php" class="btn btn-outline-primary">
                        <i class="bi bi-calendar-week"></i> Schedules
                    </a>
                    <a href="settings.php" class="btn btn-outline-secondary">
                        <i class="bi bi-gear"></i> Settings
                    </a>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#clockInModal">
                        <i class="bi bi-clock"></i> Clock In/Out
                    </button>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#timeOffModal">
                        <i class="bi bi-calendar-plus"></i> Request Time Off
                    </button>
                    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#overtimeModal">
                        <i class="bi bi-clock-fill"></i> Request Overtime
                    </button>
                </div>
            </div>

            <!-- Today's Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card late">
                        <div class="stats-number"><?= $todaysStats['late'] ?? 0 ?></div>
                        <div class="stats-label">Late arrivals</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card on-time">
                        <div class="stats-number"><?= $todaysStats['on_time'] ?? 0 ?></div>
                        <div class="stats-label">On time</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card wfh">
                        <div class="stats-number"><?= $todaysStats['wfh'] ?? 0 ?></div>
                        <div class="stats-label">WFH / On duty</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card remote">
                        <div class="stats-number"><?= $todaysStats['remote_clockins'] ?? 0 ?></div>
                        <div class="stats-label">Remote clock-ins</div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Time Off Requests -->
                <div class="col-md-6">
                    <div class="section-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Time Off Requests</h5>
                            <span class="badge bg-primary"><?= count($timeOffRequests) ?> Pending</span>
                        </div>
                        
                        <?php if (empty($timeOffRequests)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-calendar-check text-muted" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-2">No pending time off requests</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($timeOffRequests as $request): ?>
                                <div class="request-item">
                                    <div class="request-header">
                                        <div class="employee-name"><?= htmlspecialchars($request['employee_name'] ?? 'Unknown') ?></div>
                                        <div class="request-date"><?= date('M d', strtotime($request['start_date'])) ?> - <?= date('M d', strtotime($request['end_date'])) ?></div>
                                    </div>
                                    <div class="request-reason"><?= htmlspecialchars($request['reason']) ?></div>
                                    <div class="request-actions">
                                        <button class="btn-approve" onclick="updateTimeOffRequest(<?= $request['id'] ?>, 'Approved')">
                                            <i class="bi bi-check"></i> Approve
                                        </button>
                                        <button class="btn-reject" onclick="updateTimeOffRequest(<?= $request['id'] ?>, 'Rejected')">
                                            <i class="bi bi-x"></i> Reject
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Overtime Requests -->
                <div class="col-md-6">
                    <div class="section-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Overtime Requests</h5>
                            <span class="badge bg-warning"><?= count($overtimeRequests) ?> Pending</span>
                        </div>
                        
                        <?php if (empty($overtimeRequests)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-clock-fill text-muted" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-2">No pending overtime requests</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($overtimeRequests as $request): ?>
                                <div class="request-item">
                                    <div class="request-header">
                                        <div class="employee-name"><?= htmlspecialchars($request['employee_name'] ?? 'Unknown') ?></div>
                                        <div class="request-date"><?= date('M d', strtotime($request['date'])) ?> - <?= $request['hours'] ?>h</div>
                                    </div>
                                    <div class="request-reason"><?= htmlspecialchars($request['reason']) ?></div>
                                    <div class="request-actions">
                                        <button class="btn-approve" onclick="updateOvertimeRequest(<?= $request['id'] ?>, 'Approved')">
                                            <i class="bi bi-check"></i> Approve
                                        </button>
                                        <button class="btn-reject" onclick="updateOvertimeRequest(<?= $request['id'] ?>, 'Rejected')">
                                            <i class="bi bi-x"></i> Reject
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Calendar -->
            <div class="calendar-container">
                <div class="calendar-header">
                    <h5 class="mb-0"><?= date('F Y') ?> Attendance</h5>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary" onclick="previousMonth()">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <button class="btn btn-outline-secondary" onclick="nextMonth()">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                </div>
                
                <table class="calendar-table">
                    <thead>
                        <tr>
                            <th>Sun</th>
                            <th>Mon</th>
                            <th>Tue</th>
                            <th>Wed</th>
                            <th>Thu</th>
                            <th>Fri</th>
                            <th>Sat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($calendar as $week): ?>
                            <tr>
                                <?php foreach ($week as $day): ?>
                                    <td>
                                        <?php if (!empty($day)): ?>
                                            <div class="calendar-day"><?= $day['day'] ?></div>
                                            <?php if ($day['attendance']): ?>
                                                <?php 
                                                $attendance = $day['attendance'];
                                                $present_count = $attendance['present_count'];
                                                $late_count = $attendance['late_count'];
                                                
                                                if ($present_count >= 8) {
                                                    $indicator_class = 'attendance-good';
                                                } elseif ($present_count >= 4) {
                                                    $indicator_class = 'attendance-partial';
                                                } elseif ($present_count > 0) {
                                                    $indicator_class = 'attendance-poor';
                                                } else {
                                                    $indicator_class = 'attendance-none';
                                                }
                                                ?>
                                                <div class="attendance-indicator <?= $indicator_class ?>" 
                                                     title="<?= $present_count ?> present, <?= $late_count ?> late"></div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Clock In/Out Modal -->
<div class="modal fade" id="clockInModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Clock In/Out</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="clockForm">
                    <div class="form-floating mb-3">
                        <select class="form-select" id="employeeSelect" required>
                            <option value="">Select Employee</option>
                            <?php
                            $empQuery = "SELECT employee_id as id, name FROM employees ORDER BY name";
                            $empResult = $conn->query($empQuery);
                            if ($empResult) {
                                while ($emp = $empResult->fetch_assoc()) {
                                    echo "<option value='{$emp['id']}'>{$emp['name']}</option>";
                                }
                            }
                            ?>
                        </select>
                        <label for="employeeSelect">Employee</label>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <select class="form-select" id="actionSelect" required>
                            <option value="">Select Action</option>
                            <option value="clock_in">Clock In</option>
                            <option value="clock_out">Clock Out</option>
                            <option value="break_start">Start Break</option>
                            <option value="break_end">End Break</option>
                        </select>
                        <label for="actionSelect">Action</label>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <select class="form-select" id="locationSelect">
                            <option value="Office">Office</option>
                            <option value="Remote">Remote/WFH</option>
                            <option value="Field">Field Work</option>
                        </select>
                        <label for="locationSelect">Location</label>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <textarea class="form-control" id="notesInput" style="height: 80px"></textarea>
                        <label for="notesInput">Notes (Optional)</label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitClockAction()">Submit</button>
            </div>
        </div>
    </div>
</div>

<!-- Time Off Request Modal -->
<div class="modal fade" id="timeOffModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Request Time Off</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="timeOffForm">
                    <div class="form-floating mb-3">
                        <select class="form-select" id="timeOffEmployee" required>
                            <option value="">Select Employee</option>
                            <?php
                            $empResult = $conn->query("SELECT employee_id as id, name FROM employees ORDER BY name");
                            if ($empResult) {
                                while ($emp = $empResult->fetch_assoc()) {
                                    echo "<option value='{$emp['id']}'>{$emp['name']}</option>";
                                }
                            }
                            ?>
                        </select>
                        <label for="timeOffEmployee">Employee</label>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="date" class="form-control" id="startDate" required>
                                <label for="startDate">Start Date</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="date" class="form-control" id="endDate" required>
                                <label for="endDate">End Date</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <textarea class="form-control" id="timeOffReason" style="height: 100px" required></textarea>
                        <label for="timeOffReason">Reason for Time Off</label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="submitTimeOffRequest()">Submit Request</button>
            </div>
        </div>
    </div>
</div>

<!-- Overtime Request Modal -->
<div class="modal fade" id="overtimeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Request Overtime</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="overtimeForm">
                    <div class="form-floating mb-3">
                        <select class="form-select" id="overtimeEmployee" required>
                            <option value="">Select Employee</option>
                            <?php
                            $empResult = $conn->query("SELECT id, name FROM employees ORDER BY name");
                            if ($empResult) {
                                while ($emp = $empResult->fetch_assoc()) {
                                    echo "<option value='{$emp['id']}'>{$emp['name']}</option>";
                                }
                            }
                            ?>
                        </select>
                        <label for="overtimeEmployee">Employee</label>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="date" class="form-control" id="overtimeDate" required>
                                <label for="overtimeDate">Date</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="number" class="form-control" id="overtimeHours" min="1" max="12" step="0.5" required>
                                <label for="overtimeHours">Hours</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <textarea class="form-control" id="overtimeReason" style="height: 100px" required></textarea>
                        <label for="overtimeReason">Reason for Overtime</label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="submitOvertimeRequest()">Submit Request</button>
            </div>
        </div>
    </div>
</div>

<script>
// Clock action submission
function submitClockAction() {
    const employeeId = document.getElementById('employeeSelect').value;
    const action = document.getElementById('actionSelect').value;
    const location = document.getElementById('locationSelect').value;
    const notes = document.getElementById('notesInput').value;
    
    if (!employeeId || !action) {
        alert('Please fill in all required fields');
        return;
    }
    
    fetch('api/clock_action.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            employee_id: employeeId,
            action: action,
            location: location,
            notes: notes
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Clock action submitted successfully!');
            document.getElementById('clockForm').reset();
            bootstrap.Modal.getInstance(document.getElementById('clockInModal')).hide();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while submitting the clock action');
    });
}

// Time off request submission
function submitTimeOffRequest() {
    const employeeId = document.getElementById('timeOffEmployee').value;
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    const reason = document.getElementById('timeOffReason').value;
    
    if (!employeeId || !startDate || !endDate || !reason) {
        alert('Please fill in all required fields');
        return;
    }
    
    fetch('api/time_off_request.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            employee_id: employeeId,
            start_date: startDate,
            end_date: endDate,
            reason: reason
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Time off request submitted successfully!');
            document.getElementById('timeOffForm').reset();
            bootstrap.Modal.getInstance(document.getElementById('timeOffModal')).hide();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while submitting the time off request');
    });
}

// Overtime request submission
function submitOvertimeRequest() {
    const employeeId = document.getElementById('overtimeEmployee').value;
    const date = document.getElementById('overtimeDate').value;
    const hours = document.getElementById('overtimeHours').value;
    const reason = document.getElementById('overtimeReason').value;
    
    if (!employeeId || !date || !hours || !reason) {
        alert('Please fill in all required fields');
        return;
    }
    
    fetch('api/overtime_request.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            employee_id: employeeId,
            date: date,
            hours: hours,
            reason: reason
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Overtime request submitted successfully!');
            document.getElementById('overtimeForm').reset();
            bootstrap.Modal.getInstance(document.getElementById('overtimeModal')).hide();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while submitting the overtime request');
    });
}

// Update time off request status
function updateTimeOffRequest(requestId, status) {
    let reason = '';
    if (status === 'Rejected') {
        reason = prompt('Please provide a reason for rejection:');
        if (!reason) return;
    }
    
    fetch('api/update_time_off.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            request_id: requestId,
            status: status,
            rejection_reason: reason
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Request updated successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating the request');
    });
}

// Update overtime request status
function updateOvertimeRequest(requestId, status) {
    let reason = '';
    if (status === 'Rejected') {
        reason = prompt('Please provide a reason for rejection:');
        if (!reason) return;
    }
    
    fetch('api/update_overtime.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            request_id: requestId,
            status: status,
            rejection_reason: reason
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Request updated successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating the request');
    });
}

// Calendar navigation
function previousMonth() {
    // Implementation for previous month navigation
    alert('Previous month navigation - to be implemented');
}

function nextMonth() {
    // Implementation for next month navigation
    alert('Next month navigation - to be implemented');
}

// Set minimum date for time off and overtime requests to today
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('startDate').min = today;
    document.getElementById('endDate').min = today;
    document.getElementById('overtimeDate').min = today;
    
    // Update end date minimum when start date changes
    document.getElementById('startDate').addEventListener('change', function() {
        document.getElementById('endDate').min = this.value;
    });
});
</script>

<?php include '../../layouts/footer.php'; ?>
