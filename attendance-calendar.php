<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';
$page_title = 'Advanced Attendance Calendar';

// Set timezone to Indian Standard Time
date_default_timezone_set('Asia/Kolkata');

// Get current month and year
$currentMonth = $_GET['month'] ?? date('Y-m');
$currentYear = date('Y', strtotime($currentMonth . '-01'));
$currentMonthNum = date('m', strtotime($currentMonth . '-01'));
$viewType = $_GET['view'] ?? 'calendar';
$employeeFilter = $_GET['employee'] ?? '';

// Indian Government Holidays Function
function getIndianHolidays($year) {
    return [
        // Fixed National Holidays
        "$year-01-26" => ['name' => 'Republic Day', 'type' => 'national', 'category' => 'gazetted'],
        "$year-08-15" => ['name' => 'Independence Day', 'type' => 'national', 'category' => 'gazetted'],
        "$year-10-02" => ['name' => 'Gandhi Jayanti', 'type' => 'national', 'category' => 'gazetted'],
        
        // Variable Religious Holidays (approximate dates for 2024-2025)
        "$year-01-14" => ['name' => 'Makar Sankranti', 'type' => 'religious', 'category' => 'gazetted'],
        "$year-03-08" => ['name' => 'Holi', 'type' => 'religious', 'category' => 'gazetted'],
        "$year-03-29" => ['name' => 'Good Friday', 'type' => 'religious', 'category' => 'gazetted'],
        "$year-04-17" => ['name' => 'Ram Navami', 'type' => 'religious', 'category' => 'gazetted'],
        "$year-08-19" => ['name' => 'Janmashtami', 'type' => 'religious', 'category' => 'gazetted'],
        "$year-09-07" => ['name' => 'Ganesh Chaturthi', 'type' => 'religious', 'category' => 'gazetted'],
        "$year-10-12" => ['name' => 'Dussehra', 'type' => 'religious', 'category' => 'gazetted'],
        "$year-11-01" => ['name' => 'Diwali', 'type' => 'religious', 'category' => 'gazetted'],
        "$year-11-15" => ['name' => 'Guru Nanak Jayanti', 'type' => 'religious', 'category' => 'gazetted'],
        
        // Banking Holidays
        "$year-04-01" => ['name' => 'Bank Holiday', 'type' => 'banking', 'category' => 'restricted'],
        
        // Based on Islamic Calendar (approximate)
        "$year-04-11" => ['name' => 'Eid ul-Fitr', 'type' => 'religious', 'category' => 'gazetted'],
        "$year-06-17" => ['name' => 'Eid ul-Adha (Bakrid)', 'type' => 'religious', 'category' => 'gazetted'],
        "$year-07-17" => ['name' => 'Muharram', 'type' => 'religious', 'category' => 'gazetted'],
        "$year-09-16" => ['name' => 'Milad un-Nabi', 'type' => 'religious', 'category' => 'gazetted'],
    ];
}

// Tamil Nadu Specific Holidays
function getTamilNaduHolidays($year) {
    return [
        // Tamil New Year and Cultural Festivals
        "$year-04-14" => ['name' => 'Tamil New Year (Puthandu)', 'type' => 'cultural', 'category' => 'state'],
        "$year-01-15" => ['name' => 'Thai Pusam', 'type' => 'religious', 'category' => 'state'],
        "$year-04-13" => ['name' => 'Tamil New Year Eve', 'type' => 'cultural', 'category' => 'state'],
        
        // Harvest Festivals
        "$year-01-14" => ['name' => 'Pongal (Bhogi)', 'type' => 'harvest', 'category' => 'state'],
        "$year-01-15" => ['name' => 'Thai Pongal', 'type' => 'harvest', 'category' => 'state'],
        "$year-01-16" => ['name' => 'Mattu Pongal', 'type' => 'harvest', 'category' => 'state'],
        "$year-01-17" => ['name' => 'Kaanum Pongal', 'type' => 'harvest', 'category' => 'state'],
        
        // Regional Religious Festivals
        "$year-08-31" => ['name' => 'Vinayaka Chaturthi', 'type' => 'religious', 'category' => 'state'],
        "$year-09-17" => ['name' => 'Navarathri', 'type' => 'religious', 'category' => 'state'],
        "$year-12-25" => ['name' => 'Christmas', 'type' => 'religious', 'category' => 'gazetted'],
        
        // Local Observances
        "$year-05-01" => ['name' => 'May Day (Labour Day)', 'type' => 'social', 'category' => 'gazetted'],
        "$year-09-05" => ['name' => 'Teachers Day', 'type' => 'social', 'category' => 'observance'],
        "$year-11-14" => ['name' => 'Childrens Day', 'type' => 'social', 'category' => 'observance'],
    ];
}

// Get combined holidays
$indianHolidays = getIndianHolidays($currentYear);
$tamilNaduHolidays = getTamilNaduHolidays($currentYear);
$allHolidays = array_merge($indianHolidays, $tamilNaduHolidays);

// Get attendance data for calendar
$attendanceData = [];
$employeeCondition = $employeeFilter ? "AND e.employee_id = " . intval($employeeFilter) : "";

$attendanceQuery = $conn->query("
    SELECT a.*, 
           e.name as employee_name, 
           COALESCE(e.employee_code, '') as employee_code, 
           COALESCE(e.position, '') as position, 
           COALESCE(e.photo, '') as photo,
           COALESCE(a.id, a.attendance_id, 0) as attendance_id
    FROM attendance a 
    JOIN employees e ON a.employee_id = e.employee_id 
    WHERE DATE_FORMAT(a.attendance_date, '%Y-%m') = '$currentMonth' $employeeCondition
    ORDER BY a.attendance_date DESC, e.name ASC
");

if ($attendanceQuery) {
    while ($row = $attendanceQuery->fetch_assoc()) {
        $date = $row['attendance_date'] ?? date('Y-m-d');
        if (!isset($attendanceData[$date])) {
            $attendanceData[$date] = [];
        }
        $attendanceData[$date][] = $row;
    }
} else {
    // Handle query error
    error_log("Attendance query failed: " . $conn->error);
}

// Get enhanced statistics
$statsQuery = $conn->query("
    SELECT 
        COUNT(DISTINCT e.employee_id) as total_employees,
        COUNT(CASE WHEN a.status = 'Present' AND a.attendance_date = CURDATE() THEN 1 END) as present_today,
        COUNT(CASE WHEN a.status = 'Absent' AND a.attendance_date = CURDATE() THEN 1 END) as absent_today,
        COUNT(CASE WHEN a.status = 'Late' AND a.attendance_date = CURDATE() THEN 1 END) as late_today,
        COUNT(CASE WHEN a.status = 'Half Day' AND a.attendance_date = CURDATE() THEN 1 END) as half_day_today,
        AVG(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) * 100 as avg_attendance,
        COUNT(CASE WHEN a.status = 'Present' AND MONTH(a.attendance_date) = MONTH(CURDATE()) THEN 1 END) as month_present,
        COUNT(CASE WHEN a.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as week_total
    FROM employees e 
    LEFT JOIN attendance a ON e.employee_id = a.employee_id $employeeCondition
");

$stats = $statsQuery ? $statsQuery->fetch_assoc() : [
    'total_employees' => 0,
    'present_today' => 0,
    'absent_today' => 0,
    'late_today' => 0,
    'half_day_today' => 0,
    'avg_attendance' => 0
];

// Get employees for filter
$employeesQuery = $conn->query("SELECT employee_id, name, employee_code FROM employees ORDER BY name");
$employees = $employeesQuery ? $employeesQuery->fetch_all(MYSQLI_ASSOC) : [];

// Get leave applications (if leave system exists)
$leaveData = [];
// Assuming a leaves table exists
$leaveQuery = $conn->query("
    SELECT l.*, e.name as employee_name 
    FROM leaves l 
    JOIN employees e ON l.employee_id = e.employee_id 
    WHERE YEAR(l.start_date) = $currentYear AND l.status = 'approved'
") or [];

if ($leaveQuery) {
    while ($row = $leaveQuery->fetch_assoc()) {
        $startDate = $row['start_date'];
        $endDate = $row['end_date'];
        $current = $startDate;
        
        while (strtotime($current) <= strtotime($endDate)) {
            $leaveData[$current][] = $row;
            $current = date('Y-m-d', strtotime($current . ' +1 day'));
        }
    }
}

include 'layouts/header.php';
include 'layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="h5 mb-0">
                    📅 Advanced Attendance Calendar
                </h1>
                <p class="text-muted small">Complete attendance management with holidays and analytics</p>
            </div>
            <div class="d-flex gap-2">
                <div class="dropdown">
                    <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-download"></i> Export
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="exportCalendar('pdf')">
                            <i class="bi bi-file-pdf"></i> Export as PDF
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="exportCalendar('excel')">
                            <i class="bi bi-file-excel"></i> Export as Excel
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="exportCalendar('csv')">
                            <i class="bi bi-file-csv"></i> Export as CSV
                        </a></li>
                    </ul>
                </div>
                <button class="btn btn-success btn-sm" onclick="showHolidayManager()">
                    <i class="bi bi-calendar-event"></i> Manage Holidays
                </button>
                <a href="pages/attendance/attendance.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-calendar-check"></i> Mark Attendance
                </a>
            </div>
        </div>

        <!-- Enhanced Statistics Cards -->
        <div class="row g-2 mb-3">
            <div class="col-xl-2 col-lg-4 col-md-6">
                <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-people fs-3" style="color: #1976d2;"></i>
                        </div>
                        <h5 class="mb-1 fw-bold" style="color: #1976d2;"><?= $stats['total_employees'] ?? 0 ?></h5>
                        <small class="text-muted">Total Employees</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-lg-4 col-md-6">
                <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-person-check-fill fs-3" style="color: #388e3c;"></i>
                        </div>
                        <h5 class="mb-1 fw-bold" style="color: #388e3c;"><?= $stats['present_today'] ?? 0 ?></h5>
                        <small class="text-muted">Present Today</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-lg-4 col-md-6">
                <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-person-x-fill fs-3" style="color: #d32f2f;"></i>
                        </div>
                        <h5 class="mb-1 fw-bold" style="color: #d32f2f;"><?= $stats['absent_today'] ?? 0 ?></h5>
                        <small class="text-muted">Absent Today</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-lg-4 col-md-6">
                <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%);">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-clock-fill fs-3" style="color: #f57c00;"></i>
                        </div>
                        <h5 class="mb-1 fw-bold" style="color: #f57c00;"><?= $stats['late_today'] ?? 0 ?></h5>
                        <small class="text-muted">Late Today</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-lg-4 col-md-6">
                <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%);">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-graph-up-arrow fs-3" style="color: #7b1fa2;"></i>
                        </div>
                        <h5 class="mb-1 fw-bold" style="color: #7b1fa2;"><?= number_format($stats['avg_attendance'] ?? 0, 1) ?>%</h5>
                        <small class="text-muted">Avg Attendance</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-lg-4 col-md-6">
                <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e0f2f1 0%, #b2dfdb 100%);">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-calendar-event-fill fs-3" style="color: #00695c;"></i>
                        </div>
                        <h5 class="mb-1 fw-bold" style="color: #00695c;"><?= count($allHolidays) ?></h5>
                        <small class="text-muted">Holidays (<?= $currentYear ?>)</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Calendar Controls and Filters -->
        <div class="card mb-3 border-0 shadow-sm">
            <div class="card-header bg-light border-0 py-2">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <h6 class="mb-0 text-dark">
                            <i class="bi bi-calendar3 me-2"></i>
                            Calendar View - <?= date('F Y', strtotime($currentMonth . '-01')) ?>
                        </h6>
                    </div>
                    <div class="col-md-8">
                        <form method="GET" class="d-flex align-items-center gap-2 justify-content-end">
                            <div class="d-flex align-items-center gap-2">
                                <label class="form-label mb-0 fw-bold small">Month:</label>
                                <input type="month" name="month" class="form-control form-control-sm" value="<?= $currentMonth ?>" 
                                       onchange="this.form.submit()" style="width: 140px;">
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <label class="form-label mb-0 fw-bold small">Employee:</label>
                                <select name="employee" class="form-select form-select-sm" onchange="this.form.submit()" style="width: 180px;">
                                    <option value="">All Employees</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?= $emp['employee_id'] ?>" 
                                                <?= $employeeFilter == $emp['employee_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($emp['name']) ?> (<?= $emp['employee_code'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="btn-group btn-group-sm" role="group">
                                <input type="radio" class="btn-check" name="view" id="calendar-view" value="calendar" 
                                       <?= $viewType === 'calendar' ? 'checked' : '' ?> onchange="this.form.submit()">
                                <label class="btn btn-outline-primary" for="calendar-view">
                                    <i class="bi bi-calendar3"></i> Calendar
                                </label>
                                
                                <input type="radio" class="btn-check" name="view" id="list-view" value="list" 
                                       <?= $viewType === 'list' ? 'checked' : '' ?> onchange="this.form.submit()">
                                <label class="btn btn-outline-primary" for="list-view">
                                    <i class="bi bi-list-ul"></i> List
                                </label>
                                
                                <input type="radio" class="btn-check" name="view" id="analytics-view" value="analytics" 
                                       <?= $viewType === 'analytics' ? 'checked' : '' ?> onchange="this.form.submit()">
                                <label class="btn btn-outline-primary" for="analytics-view">
                                    <i class="bi bi-graph-up"></i> Analytics
                                </label>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="card-body p-3">
                <?php if ($viewType === 'calendar'): ?>
                    <div id="calendar"></div>
                <?php elseif ($viewType === 'list'): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="attendanceListTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Employee</th>
                                    <th>Status</th>
                                    <th>Time In</th>
                                    <th>Time Out</th>
                                    <th>Duration</th>
                                    <th>Holiday/Leave</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                        <tbody>
                            <?php foreach ($attendanceData as $date => $records): ?>
                                <?php foreach ($records as $record): ?>
                                    <tr>
                                        <td>
                                            <strong><?= date('d M Y', strtotime($date)) ?></strong><br>
                                            <small class="text-muted"><?= date('l', strtotime($date)) ?></small>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if (!empty($record['photo']) && file_exists($record['photo'])): ?>
                                                    <img src="<?= htmlspecialchars($record['photo']) ?>" class="rounded-circle me-2" 
                                                         style="width: 32px; height: 32px; object-fit: cover;">
                                                <?php else: ?>
                                                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center me-2" 
                                                         style="width: 32px; height: 32px;">
                                                        <i class="bi bi-person text-white"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <strong><?= htmlspecialchars($record['employee_name'] ?? 'Unknown') ?></strong><br>
                                                    <small class="text-muted"><?= htmlspecialchars($record['employee_code'] ?? '') ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = '';
                                            switch($record['status'] ?? '') {
                                                case 'Present': $statusClass = 'bg-success'; break;
                                                case 'Absent': $statusClass = 'bg-danger'; break;
                                                case 'Late': $statusClass = 'bg-warning text-dark'; break;
                                                case 'Half Day': $statusClass = 'bg-info'; break;
                                                default: $statusClass = 'bg-secondary';
                                            }
                                            ?>
                                            <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($record['status'] ?? 'Unknown') ?></span>
                                        </td>
                                        <td><?= !empty($record['time_in']) ? date('h:i A', strtotime($record['time_in'])) : '-' ?></td>
                                        <td><?= !empty($record['time_out']) ? date('h:i A', strtotime($record['time_out'])) : '-' ?></td>
                                        <td>
                                            <?php if (!empty($record['time_in']) && !empty($record['time_out'])): ?>
                                                <?php
                                                try {
                                                    $timeIn = new DateTime($record['time_in']);
                                                    $timeOut = new DateTime($record['time_out']);
                                                    $duration = $timeIn->diff($timeOut);
                                                    echo $duration->format('%H:%I hrs');
                                                } catch (Exception $e) {
                                                    echo '-';
                                                }
                                                ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (isset($allHolidays[$date])): ?>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="bi bi-calendar-event"></i> <?= $allHolidays[$date]['name'] ?>
                                                </span>
                                            <?php elseif (isset($leaveData[$date])): ?>
                                                <span class="badge bg-info">
                                                    <i class="bi bi-calendar-x"></i> On Leave
                                                </span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" onclick="editAttendance(<?= intval($record['attendance_id'] ?? 0) ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-outline-info" onclick="viewDetails(<?= intval($record['attendance_id'] ?? 0) ?>)">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($viewType === 'analytics'): ?>
                <div class="row">
                    <div class="col-md-6">
                        <canvas id="attendanceChart" height="300"></canvas>
                    </div>
                    <div class="col-md-6">
                        <canvas id="monthlyTrendChart" height="300"></canvas>
                    </div>
                </div>
                <div class="row mt-4">
                    <div class="col-md-12">
                        <canvas id="employeeWiseChart" height="200"></canvas>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

        <!-- Enhanced Legend with Holidays -->
        <div class="row g-2">
            <div class="col-md-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light border-0 py-2">
                        <h6 class="mb-0 text-dark"><i class="bi bi-info-circle me-2"></i>Legend</h6>
                    </div>
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-md-3 col-6">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="bg-success rounded me-2" style="width: 20px; height: 20px;"></div>
                                    <small>Present</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="bg-danger rounded me-2" style="width: 20px; height: 20px;"></div>
                                    <small>Absent</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="bg-warning rounded me-2" style="width: 20px; height: 20px;"></div>
                                    <small>Late</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="bg-info rounded me-2" style="width: 20px; height: 20px;"></div>
                                    <small>Half Day</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="rounded me-2" style="width: 20px; height: 20px; background: #6c757d;"></div>
                                    <small>National Holiday</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="rounded me-2" style="width: 20px; height: 20px; background: #adb5bd;"></div>
                                    <small>State Holiday</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="rounded me-2" style="width: 20px; height: 20px; background: #ff6b6b;"></div>
                                    <small>Weekend</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="rounded me-2" style="width: 20px; height: 20px; background: #4ecdc4;"></div>
                                    <small>On Leave</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light border-0 py-2">
                        <h6 class="mb-0 text-dark"><i class="bi bi-calendar-event me-2"></i>Upcoming Holidays</h6>
                    </div>
                    <div class="card-body p-3">
                        <div class="list-group list-group-flush">
                            <?php 
                            $upcomingHolidays = array_filter($allHolidays, function($date) {
                                return strtotime($date) >= strtotime(date('Y-m-d'));
                            }, ARRAY_FILTER_USE_KEY);
                            
                            $upcomingHolidays = array_slice($upcomingHolidays, 0, 5, true);
                            
                            foreach ($upcomingHolidays as $date => $holiday): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center p-2 border-0">
                                    <div>
                                        <strong class="small"><?= $holiday['name'] ?></strong><br>
                                        <small class="text-muted"><?= date('j M Y, l', strtotime($date)) ?></small>
                                    </div>
                                    <span class="badge <?= $holiday['type'] === 'national' ? 'bg-primary' : 'bg-secondary' ?> badge-sm">
                                        <?= ucfirst($holiday['type']) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Detail Modal -->
<div class="modal fade" id="attendanceModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Attendance Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="attendanceModalBody">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Holiday Manager Modal -->
<div class="modal fade" id="holidayModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Holiday Manager</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Holiday Name</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allHolidays as $date => $holiday): ?>
                                <tr>
                                    <td><?= date('j M Y', strtotime($date)) ?></td>
                                    <td><?= $holiday['name'] ?></td>
                                    <td>
                                        <span class="badge <?= $holiday['type'] === 'national' ? 'bg-primary' : 'bg-secondary' ?>">
                                            <?= ucfirst($holiday['type']) ?>
                                        </span>
                                    </td>
                                    <td><?= ucfirst($holiday['category']) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="editHoliday('<?= $date ?>')">
                                            <i class="bi bi-pencil"></i>
                                        </button>
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

<!-- Include FullCalendar CSS and JS -->
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css' rel='stylesheet' />
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.js'></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($viewType === 'calendar'): ?>
        initializeCalendar();
    <?php elseif ($viewType === 'analytics'): ?>
        initializeCharts();
    <?php endif; ?>
    
    // Initialize DataTable for list view
    if (document.getElementById('attendanceListTable')) {
        $('#attendanceListTable').DataTable({
            pageLength: 25,
            order: [[0, 'desc']],
            responsive: true
        });
    }
});

function initializeCalendar() {
    var calendarEl = document.getElementById('calendar');
    
    // Prepare events data
    var events = [];
    
    // Add holidays
    <?php foreach ($allHolidays as $date => $holiday): ?>
        events.push({
            title: '🎉 <?= addslashes($holiday['name']) ?>',
            start: '<?= $date ?>',
            backgroundColor: '<?= $holiday['type'] === 'national' ? '#343a40' : '#6c757d' ?>',
            borderColor: '<?= $holiday['type'] === 'national' ? '#343a40' : '#6c757d' ?>',
            textColor: 'white',
            allDay: true,
            extendedProps: {
                type: 'holiday',
                holidayData: <?= json_encode($holiday) ?>
            }
        });
    <?php endforeach; ?>
    
    // Add attendance events
    <?php foreach ($attendanceData as $date => $attendanceRecords): ?>
        var dateAttendance = <?= json_encode($attendanceRecords) ?>;
        var presentCount = 0;
        var absentCount = 0;
        var lateCount = 0;
        var halfDayCount = 0;
        
        dateAttendance.forEach(function(record) {
            switch(record.status) {
                case 'Present': presentCount++; break;
                case 'Absent': absentCount++; break;
                case 'Late': lateCount++; break;
                case 'Half Day': halfDayCount++; break;
            }
        });
        
        // Determine the primary status and color
        var primaryStatus = 'Present';
        var color = '#28a745'; // Green for present
        
        if (absentCount > presentCount + lateCount + halfDayCount) {
            primaryStatus = 'Mostly Absent';
            color = '#dc3545'; // Red for absent
        } else if (lateCount > 0) {
            primaryStatus = 'Some Late';
            color = '#ffc107'; // Yellow for late
        } else if (halfDayCount > 0) {
            primaryStatus = 'Half Day';
            color = '#17a2b8'; // Blue for half day
        }
        
        events.push({
            title: presentCount + '/' + dateAttendance.length + ' Present',
            start: '<?= $date ?>',
            backgroundColor: color,
            borderColor: color,
            textColor: 'white',
            extendedProps: {
                type: 'attendance',
                date: '<?= $date ?>',
                attendance: dateAttendance,
                summary: {
                    present: presentCount,
                    absent: absentCount,
                    late: lateCount,
                    halfDay: halfDayCount,
                    total: dateAttendance.length
                }
            }
        });
    <?php endforeach; ?>
    
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        initialDate: '<?= $currentMonth ?>-01',
        height: 700,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,listMonth,dayGridWeek'
        },
        events: events,
        eventClick: function(info) {
            if (info.event.extendedProps.type === 'attendance') {
                showAttendanceDetails(info.event.extendedProps);
            } else if (info.event.extendedProps.type === 'holiday') {
                showHolidayDetails(info.event.extendedProps.holidayData, info.event.startStr);
            }
        },
        dateClick: function(info) {
            // Optionally handle date clicks for adding attendance
            console.log('Date clicked:', info.dateStr);
        },
        dayMaxEvents: 3,
        moreLinkClick: 'popover'
    });
    
    calendar.render();
}

function initializeCharts() {
    // Attendance Distribution Chart
    const ctx1 = document.getElementById('attendanceChart').getContext('2d');
    new Chart(ctx1, {
        type: 'doughnut',
        data: {
            labels: ['Present', 'Absent', 'Late', 'Half Day'],
            datasets: [{
                data: [
                    <?= $stats['present_today'] ?? 0 ?>,
                    <?= $stats['absent_today'] ?? 0 ?>,
                    <?= $stats['late_today'] ?? 0 ?>,
                    <?= $stats['half_day_today'] ?? 0 ?>
                ],
                backgroundColor: ['#28a745', '#dc3545', '#ffc107', '#17a2b8']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Today\'s Attendance Distribution'
                },
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    
    // Monthly Trend Chart
    const ctx2 = document.getElementById('monthlyTrendChart').getContext('2d');
    new Chart(ctx2, {
        type: 'line',
        data: {
            labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
            datasets: [{
                label: 'Attendance Rate',
                data: [85, 92, 88, 90], // Sample data
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Monthly Attendance Trend'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                }
            }
        }
    });
    
    // Employee-wise Chart
    const ctx3 = document.getElementById('employeeWiseChart').getContext('2d');
    new Chart(ctx3, {
        type: 'bar',
        data: {
            labels: ['Employee 1', 'Employee 2', 'Employee 3', 'Employee 4', 'Employee 5'], // Sample data
            datasets: [{
                label: 'Attendance Rate (%)',
                data: [95, 88, 92, 85, 97], // Sample data
                backgroundColor: 'rgba(54, 162, 235, 0.8)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Employee-wise Attendance Rate'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                }
            }
        }
    });
}

function showAttendanceDetails(eventProps) {
    var modalBody = document.getElementById('attendanceModalBody');
    var attendance = eventProps.attendance;
    var summary = eventProps.summary;
    var date = eventProps.date;
    
    // Check if it's a holiday
    var holidayInfo = '';
    <?php foreach ($allHolidays as $hDate => $holiday): ?>
        if ('<?= $hDate ?>' === date) {
            holidayInfo = `
                <div class="alert alert-warning mb-3">
                    <i class="bi bi-calendar-event"></i>
                    <strong><?= addslashes($holiday['name']) ?></strong> - <?= ucfirst($holiday['type']) ?> Holiday
                </div>
            `;
        }
    <?php endforeach; ?>
    
    var html = `
        <div class="row mb-3">
            <div class="col-md-6">
                <h6>Date: <strong>${new Date(date).toLocaleDateString('en-IN', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                })}</strong></h6>
            </div>
            <div class="col-md-6">
                <h6>Total Employees: <strong>${summary.total}</strong></h6>
            </div>
        </div>
        
        ${holidayInfo}
        
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card bg-success text-white text-center">
                    <div class="card-body">
                        <h4>${summary.present}</h4>
                        <small>Present</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white text-center">
                    <div class="card-body">
                        <h4>${summary.absent}</h4>
                        <small>Absent</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark text-center">
                    <div class="card-body">
                        <h4>${summary.late}</h4>
                        <small>Late</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white text-center">
                    <div class="card-body">
                        <h4>${summary.halfDay}</h4>
                        <small>Half Day</small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Employee</th>
                        <th>Position</th>
                        <th>Status</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Duration</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    attendance.forEach(function(record) {
        var statusClass = '';
        switch(record.status) {
            case 'Present': statusClass = 'bg-success'; break;
            case 'Absent': statusClass = 'bg-danger'; break;
            case 'Late': statusClass = 'bg-warning text-dark'; break;
            case 'Half Day': statusClass = 'bg-info'; break;
        }
        
        var duration = '-';
        if (record.time_in && record.time_out) {
            var timeIn = new Date('2000-01-01 ' + record.time_in);
            var timeOut = new Date('2000-01-01 ' + record.time_out);
            var diffMs = timeOut - timeIn;
            var diffHrs = Math.floor(diffMs / (1000 * 60 * 60));
            var diffMins = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
            duration = diffHrs + ':' + diffMins.toString().padStart(2, '0') + ' hrs';
        }
        
        var photoHtml = record.photo ? 
            `<img src="${record.photo}" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover;">` :
            `<div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                <i class="bi bi-person text-white"></i>
             </div>`;
        
        html += `
            <tr>
                <td>${photoHtml}</td>
                <td>
                    <strong>${record.employee_name}</strong><br>
                    <small class="text-muted">${record.employee_code}</small>
                </td>
                <td>${record.position || '-'}</td>
                <td><span class="badge ${statusClass}">${record.status}</span></td>
                <td>${record.time_in || '-'}</td>
                <td>${record.time_out || '-'}</td>
                <td>${duration}</td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    modalBody.innerHTML = html;
    new bootstrap.Modal(document.getElementById('attendanceModal')).show();
}

function showHolidayDetails(holidayData, date) {
    var modalBody = document.getElementById('attendanceModalBody');
    
    var html = `
        <div class="text-center mb-4">
            <div class="display-1 mb-3">🎉</div>
            <h3>${holidayData.name}</h3>
            <p class="lead">${new Date(date).toLocaleDateString('en-IN', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            })}</p>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <h5>Holiday Type</h5>
                        <span class="badge ${holidayData.type === 'national' ? 'bg-primary' : 'bg-secondary'} fs-6">
                            ${holidayData.type.charAt(0).toUpperCase() + holidayData.type.slice(1)}
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <h5>Category</h5>
                        <span class="badge bg-info fs-6">
                            ${holidayData.category.charAt(0).toUpperCase() + holidayData.category.slice(1)}
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="alert alert-info mt-3">
            <i class="bi bi-info-circle"></i>
            This is a ${holidayData.type} ${holidayData.category} holiday. 
            ${holidayData.type === 'national' ? 'All government offices and most private organizations remain closed.' : 
              'This is observed primarily in Tamil Nadu and surrounding regions.'}
        </div>
    `;
    
    modalBody.innerHTML = html;
    new bootstrap.Modal(document.getElementById('attendanceModal')).show();
}

function showHolidayManager() {
    new bootstrap.Modal(document.getElementById('holidayModal')).show();
}

function exportCalendar(format) {
    var currentMonth = '<?= $currentMonth ?>';
    var employeeFilter = '<?= $employeeFilter ?>';
    
    var url = `export_attendance.php?format=${format}&month=${currentMonth}`;
    if (employeeFilter) {
        url += `&employee=${employeeFilter}`;
    }
    
    window.open(url, '_blank');
}

function editAttendance(attendanceId) {
    // Implement attendance editing functionality
    console.log('Edit attendance:', attendanceId);
}

function viewDetails(attendanceId) {
    // Implement detailed view functionality
    console.log('View details:', attendanceId);
}

function editHoliday(date) {
    // Implement holiday editing functionality
    console.log('Edit holiday:', date);
}
</script>

<style>
/* Statistics Cards Styling */
.statistics-card {
    transition: all 0.3s ease;
    border-radius: 12px;
    overflow: hidden;
}

.statistics-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15) !important;
}

.statistics-card .card-body {
    position: relative;
    overflow: hidden;
}

.statistics-card .card-body::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    transition: all 0.3s ease;
    opacity: 0;
}

.statistics-card:hover .card-body::before {
    opacity: 1;
    transform: scale(1.2);
}

.statistics-card i {
    transition: all 0.3s ease;
}

.statistics-card:hover i {
    transform: scale(1.1);
}

/* Custom Card Styling */
.card {
    border-radius: 10px;
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.card-header {
    border-radius: 10px 10px 0 0 !important;
}

/* FullCalendar Styling */
.fc-event {
    border-radius: 4px;
    font-size: 12px;
}

.fc-daygrid-event {
    margin: 1px 0;
}

/* Button Group Styling */
.btn-group .btn-check:checked + .btn {
    background-color: #0d6efd;
    border-color: #0d6efd;
    color: white;
}

/* Modal Styling */
.modal-xl {
    max-width: 90%;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .statistics-card .card-body {
        padding: 0.75rem;
    }
    
    .statistics-card h5 {
        font-size: 1.1rem;
    }
    
    .statistics-card i {
        font-size: 1.5rem !important;
    }
}

@media (max-width: 992px) {
    .main-content .container-fluid {
        padding: 0 10px;
    }
    
    .statistics-card .card-body {
        padding: 0.65rem;
    }
    
    .d-flex.gap-2 {
        gap: 0.5rem !important;
    }
    
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.8rem;
    }
}

@media (max-width: 768px) {
    .modal-xl {
        max-width: 95%;
    }
    
    .card-body {
        padding: 0.75rem !important;
    }
    
    .statistics-card .card-body {
        padding: 0.5rem;
        text-align: center;
    }
    
    .statistics-card h5 {
        font-size: 1rem;
        margin-bottom: 0.25rem;
    }
    
    .statistics-card small {
        font-size: 0.7rem;
    }
    
    .statistics-card i {
        font-size: 1.3rem !important;
        margin-bottom: 0.25rem;
    }
    
    .d-flex.justify-content-between {
        flex-direction: column;
        gap: 1rem;
    }
    
    .d-flex.justify-content-between .d-flex {
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .form-control-sm, .form-select-sm {
        font-size: 0.8rem;
    }
    
    .btn-group-sm .btn {
        padding: 0.2rem 0.4rem;
        font-size: 0.75rem;
    }
}

@media (max-width: 576px) {
    .statistics-card {
        margin-bottom: 0.5rem;
    }
    
    .statistics-card .card-body {
        padding: 0.4rem;
    }
    
    .statistics-card h5 {
        font-size: 0.9rem;
    }
    
    .statistics-card small {
        font-size: 0.65rem;
    }
    
    .col-xl-2 {
        flex: 0 0 50%;
        max-width: 50%;
    }
    
    .card-header h6 {
        font-size: 0.9rem;
    }
    
    .legend-section .col-6 {
        margin-bottom: 0.5rem;
    }
    
    .upcoming-holidays .list-group-item {
        padding: 0.5rem !important;
    }
    
    .upcoming-holidays .small, .upcoming-holidays small {
        font-size: 0.7rem;
    }
}

/* Additional Utility Classes */
.badge-sm {
    font-size: 0.7rem;
    padding: 0.25rem 0.5rem;
}

.shadow-soft {
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08) !important;
}

.border-radius-lg {
    border-radius: 12px;
}

/* Smooth Transitions */
* {
    transition: all 0.2s ease;
}

/* Page Content Spacing */
.main-content {
    padding: 1rem 0;
}

.main-content .container-fluid {
    padding: 0 15px;
}

/* Compact spacing for better space utilization */
.mb-4 {
    margin-bottom: 1rem !important;
}

.mb-3 {
    margin-bottom: 0.75rem !important;
}

.p-3 {
    padding: 0.75rem !important;
}

.py-2 {
    padding-top: 0.5rem !important;
    padding-bottom: 0.5rem !important;
}

.g-2 > * {
    padding: 0.25rem;
}
</style>

<?php include 'layouts/footer.php'; ?>
