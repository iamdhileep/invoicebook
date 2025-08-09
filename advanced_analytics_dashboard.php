<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Include database connection with dynamic path resolution
$db_path = "db.php";
if (file_exists($db_path)) {
    include $db_path;
} else {
    die("Database connection file not found");
}

$page_title = 'Advanced Analytics Dashboard';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_dashboard_data':
            $startDate = $_POST['start_date'] ?? date('Y-m-01');
            $endDate = $_POST['end_date'] ?? date('Y-m-d');
            $department = $_POST['department'] ?? '';
            
            echo json_encode(getDashboardData($conn, $startDate, $endDate, $department));
            exit;
            
        case 'export_analytics':
            $format = $_POST['format'] ?? 'excel';
            exportAnalyticsData($conn, $format);
            exit;
            
        case 'schedule_report':
            $frequency = $_POST['frequency'];
            $email = $_POST['email'];
            $reportType = $_POST['report_type'];
            
            $result = scheduleReport($conn, $frequency, $email, $reportType);
            echo json_encode($result);
            exit;
            
        case 'send_alert':
            $alertType = $_POST['alert_type'];
            $message = $_POST['message'];
            $recipients = $_POST['recipients'];
            
            $result = sendAlert($conn, $alertType, $message, $recipients);
            echo json_encode($result);
            exit;
            
        case 'get_compliance_details':
            echo json_encode(getComplianceDetails($conn));
            exit;
            
        case 'get_employee_performance':
            echo json_encode(getEmployeePerformance($conn));
            exit;
            
        case 'get_attendance_trends':
            $period = $_POST['period'] ?? 'daily';
            echo json_encode(getAttendanceTrends($conn, $period));
            exit;
    }
}

// Dashboard data functions
function getDashboardData($conn, $startDate, $endDate, $department = '') {
    $data = [];
    
    // Build department filter
    $deptFilter = '';
    if (!empty($department)) {
        $deptFilter = " AND d.department_name = '" . mysqli_real_escape_string($conn, $department) . "'";
    }
    
    // Total and active employees
    $empQuery = "SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN e.status = 'active' THEN 1 END) as active
        FROM hr_employees e
        LEFT JOIN hr_departments d ON e.department_id = d.id
        WHERE 1=1 $deptFilter";
    
    $result = mysqli_query($conn, $empQuery);
    if ($result) {
        $empData = mysqli_fetch_assoc($result);
        $data['totalEmployees'] = $empData['total'];
        $data['activeEmployees'] = $empData['active'];
    } else {
        $data['totalEmployees'] = 0;
        $data['activeEmployees'] = 0;
    }
    
    // Attendance rate
    $attQuery = "SELECT 
        COUNT(*) as total_records,
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_records
        FROM hr_attendance a
        JOIN hr_employees e ON a.employee_id = e.id
        LEFT JOIN hr_departments d ON e.department_id = d.id
        WHERE DATE(a.date) BETWEEN '$startDate' AND '$endDate' $deptFilter";
    
    $result = mysqli_query($conn, $attQuery);
    if ($result) {
        $attData = mysqli_fetch_assoc($result);
        $attendanceRate = $attData['total_records'] > 0 ? 
            round(($attData['present_records'] / $attData['total_records']) * 100, 1) : 0;
        $data['attendanceRate'] = $attendanceRate . '%';
        $data['totalAttendanceRecords'] = $attData['total_records'];
        $data['presentRecords'] = $attData['present_records'];
    } else {
        $data['attendanceRate'] = '0%';
        $data['totalAttendanceRecords'] = 0;
        $data['presentRecords'] = 0;
    }
    
    // Leave statistics
    $leaveQuery = "SELECT 
        COUNT(*) as total_requests,
        COUNT(CASE WHEN lr.status = 'pending' THEN 1 END) as pending,
        COUNT(CASE WHEN lr.status = 'approved' THEN 1 END) as approved,
        SUM(CASE WHEN lr.status = 'approved' THEN lr.days_requested ELSE 0 END) as total_leave_days
        FROM hr_leave_requests lr
        JOIN hr_employees e ON lr.employee_id = e.id
        LEFT JOIN hr_departments d ON e.department_id = d.id
        WHERE lr.start_date BETWEEN '$startDate' AND '$endDate' $deptFilter";
    
    $result = mysqli_query($conn, $leaveQuery);
    if ($result) {
        $leaveData = mysqli_fetch_assoc($result);
        $data['totalLeaveRequests'] = $leaveData['total_requests'];
        $data['pendingLeaves'] = $leaveData['pending'];
        $data['approvedLeaves'] = $leaveData['approved'];
        $data['totalLeaveDays'] = $leaveData['total_leave_days'];
    } else {
        $data['totalLeaveRequests'] = 0;
        $data['pendingLeaves'] = 0;
        $data['approvedLeaves'] = 0;
        $data['totalLeaveDays'] = 0;
    }
    
    // Compliance score calculation
    $complianceIssues = 0;
    $totalChecks = 4;
    
    // Check 1: Late arrivals (more than 15 minutes late)
    $lateQuery = "SELECT COUNT(*) as late_count FROM hr_attendance 
                  WHERE DATE(date) BETWEEN '$startDate' AND '$endDate' 
                  AND TIME(clock_in) > '09:15:00' AND status = 'present'";
    $lateResult = mysqli_query($conn, $lateQuery);
    if ($lateResult && mysqli_fetch_assoc($lateResult)['late_count'] > 10) {
        $complianceIssues++;
    }
    
    // Check 2: Missing attendance records
    $missingQuery = "SELECT COUNT(*) as missing FROM hr_employees e
                     WHERE e.status = 'active' AND e.id NOT IN (
                         SELECT DISTINCT employee_id FROM hr_attendance 
                         WHERE DATE(date) BETWEEN DATE_SUB('$endDate', INTERVAL 7 DAY) AND '$endDate'
                     )";
    $missingResult = mysqli_query($conn, $missingQuery);
    if ($missingResult && mysqli_fetch_assoc($missingResult)['missing'] > 0) {
        $complianceIssues++;
    }
    
    // Check 3: Overtime violations (more than 10 hours)
    $overtimeQuery = "SELECT COUNT(*) as violations FROM hr_attendance 
                      WHERE DATE(date) BETWEEN '$startDate' AND '$endDate' 
                      AND overtime_hours > 10";
    $overtimeResult = mysqli_query($conn, $overtimeQuery);
    if ($overtimeResult && mysqli_fetch_assoc($overtimeResult)['violations'] > 0) {
        $complianceIssues++;
    }
    
    // Check 4: Unapproved leaves
    $unapprovedQuery = "SELECT COUNT(*) as unapproved FROM hr_leave_requests 
                        WHERE status = 'pending' AND start_date < CURDATE()";
    $unapprovedResult = mysqli_query($conn, $unapprovedQuery);
    if ($unapprovedResult && mysqli_fetch_assoc($unapprovedResult)['unapproved'] > 0) {
        $complianceIssues++;
    }
    
    $complianceScore = round(((($totalChecks - $complianceIssues) / $totalChecks) * 100), 1);
    $data['complianceScore'] = $complianceScore . '%';
    $data['complianceIssues'] = $complianceIssues;
    
    // Average working hours
    $hoursQuery = "SELECT AVG(total_hours) as avg_hours FROM hr_attendance 
                   WHERE DATE(date) BETWEEN '$startDate' AND '$endDate' 
                   AND total_hours > 0 AND status = 'present'";
    $hoursResult = mysqli_query($conn, $hoursQuery);
    if ($hoursResult) {
        $avgHours = mysqli_fetch_assoc($hoursResult)['avg_hours'];
        $data['avgWorkingHours'] = round($avgHours, 1) . ' hrs';
    } else {
        $data['avgWorkingHours'] = '0 hrs';
    }
    
    return $data;
}

function getComplianceDetails($conn) {
    $details = [];
    
    // Late arrivals
    $lateQuery = "SELECT e.first_name, e.last_name, a.date, a.clock_in 
                  FROM hr_attendance a 
                  JOIN hr_employees e ON a.employee_id = e.id 
                  WHERE TIME(a.clock_in) > '09:15:00' 
                  AND a.status = 'present' 
                  AND DATE(a.date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                  ORDER BY a.date DESC LIMIT 10";
    $lateResult = mysqli_query($conn, $lateQuery);
    $details['lateArrivals'] = [];
    if ($lateResult) {
        while ($row = mysqli_fetch_assoc($lateResult)) {
            $details['lateArrivals'][] = $row;
        }
    }
    
    // Missing attendance
    $missingQuery = "SELECT e.first_name, e.last_name, e.employee_id 
                     FROM hr_employees e 
                     WHERE e.status = 'active' 
                     AND e.id NOT IN (
                         SELECT DISTINCT employee_id FROM hr_attendance 
                         WHERE DATE(date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                     ) LIMIT 10";
    $missingResult = mysqli_query($conn, $missingQuery);
    $details['missingAttendance'] = [];
    if ($missingResult) {
        while ($row = mysqli_fetch_assoc($missingResult)) {
            $details['missingAttendance'][] = $row;
        }
    }
    
    return $details;
}

function getEmployeePerformance($conn) {
    $performance = [];
    
    $query = "SELECT 
        e.first_name, e.last_name, e.employee_id,
        d.department_name,
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
        COUNT(a.id) as total_days,
        AVG(a.total_hours) as avg_hours,
        COUNT(CASE WHEN TIME(a.clock_in) > '09:15:00' THEN 1 END) as late_count
        FROM hr_employees e
        LEFT JOIN hr_attendance a ON e.id = a.employee_id 
            AND DATE(a.date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        LEFT JOIN hr_departments d ON e.department_id = d.id
        WHERE e.status = 'active'
        GROUP BY e.id
        ORDER BY present_days DESC, avg_hours DESC
        LIMIT 20";
    
    $result = mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $attendanceRate = $row['total_days'] > 0 ? 
                round(($row['present_days'] / $row['total_days']) * 100, 1) : 0;
            $row['attendance_rate'] = $attendanceRate;
            $row['avg_hours'] = round($row['avg_hours'] ?? 0, 1);
            $performance[] = $row;
        }
    }
    
    return $performance;
}

function getAttendanceTrends($conn, $period = 'daily') {
    $trends = [];
    
    switch ($period) {
        case 'weekly':
            $groupBy = "YEARWEEK(a.date)";
            $dateFormat = "CONCAT(YEAR(a.date), '-W', WEEK(a.date))";
            $interval = "INTERVAL 12 WEEK";
            break;
        case 'monthly':
            $groupBy = "DATE_FORMAT(a.date, '%Y-%m')";
            $dateFormat = "DATE_FORMAT(a.date, '%Y-%m')";
            $interval = "INTERVAL 12 MONTH";
            break;
        default: // daily
            $groupBy = "DATE(a.date)";
            $dateFormat = "DATE(a.date)";
            $interval = "INTERVAL 30 DAY";
    }
    
    $query = "SELECT 
        $dateFormat as period,
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present,
        COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent,
        COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late,
        COUNT(*) as total
        FROM hr_attendance a
        WHERE DATE(a.date) >= DATE_SUB(CURDATE(), $interval)
        GROUP BY $groupBy
        ORDER BY period DESC";
    
    $result = mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $attendanceRate = $row['total'] > 0 ? 
                round(($row['present'] / $row['total']) * 100, 1) : 0;
            $row['attendance_rate'] = $attendanceRate;
            $trends[] = $row;
        }
    }
    
    return array_reverse($trends); // Show oldest first for chart
}

function exportAnalyticsData($conn, $format = 'excel') {
    $data = getDashboardData($conn, date('Y-m-01'), date('Y-m-d'));
    
    if ($format === 'excel') {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="analytics_report_' . date('Y-m-d') . '.xls"');
        
        echo "<table border='1'>";
        echo "<tr><th>Metric</th><th>Value</th></tr>";
        foreach ($data as $key => $value) {
            echo "<tr><td>" . ucwords(str_replace('_', ' ', $key)) . "</td><td>$value</td></tr>";
        }
        echo "</table>";
    } else {
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT);
    }
    exit;
}

function scheduleReport($conn, $frequency, $email, $reportType) {
    // Create scheduled reports table if not exists
    $createTable = "CREATE TABLE IF NOT EXISTS hr_scheduled_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        frequency VARCHAR(20) NOT NULL,
        email VARCHAR(255) NOT NULL,
        report_type VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_sent DATETIME NULL,
        status ENUM('active', 'inactive') DEFAULT 'active'
    )";
    mysqli_query($conn, $createTable);
    
    $query = "INSERT INTO hr_scheduled_reports (frequency, email, report_type) 
              VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "sss", $frequency, $email, $reportType);
    
    if (mysqli_stmt_execute($stmt)) {
        return ['success' => true, 'message' => 'Report scheduled successfully!'];
    } else {
        return ['success' => false, 'message' => 'Failed to schedule report'];
    }
}

function sendAlert($conn, $alertType, $message, $recipients) {
    // Create alerts table if not exists
    $createTable = "CREATE TABLE IF NOT EXISTS hr_alerts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        alert_type VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        recipients TEXT NOT NULL,
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('sent', 'failed') DEFAULT 'sent'
    )";
    mysqli_query($conn, $createTable);
    
    $query = "INSERT INTO hr_alerts (alert_type, message, recipients) 
              VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "sss", $alertType, $message, $recipients);
    
    if (mysqli_stmt_execute($stmt)) {
        // In a real system, you would send actual emails/notifications here
        return ['success' => true, 'message' => 'Alert sent successfully!'];
    } else {
        return ['success' => false, 'message' => 'Failed to send alert'];
    }
}

// Get departments for filter dropdown
$departments = [];
$deptQuery = "SELECT DISTINCT department_name FROM hr_departments WHERE status = 'active' ORDER BY department_name";
$deptResult = mysqli_query($conn, $deptQuery);
if ($deptResult) {
    while ($dept = mysqli_fetch_assoc($deptResult)) {
        $departments[] = $dept['department_name'];
    }
}

include 'layouts/header.php';
include 'layouts/sidebar.php';
?>

<div class="main-content">
    <div class="content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h4 mb-1 fw-bold text-primary">📊 Advanced Analytics Dashboard</h1>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-graph-up"></i> 
                        Comprehensive insights into attendance patterns and compliance metrics
                        <span class="badge bg-light text-dark ms-2">Real-time Data</span>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary btn-sm" onclick="refreshDashboard()" title="Refresh Data">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                    <button class="btn btn-outline-success btn-sm" onclick="showExportModal()" title="Export Data">
                        <i class="bi bi-download"></i> Export
                    </button>
                    <button class="btn btn-outline-warning btn-sm" onclick="showScheduleModal()" title="Schedule Reports">
                        <i class="bi bi-calendar"></i> Schedule
                    </button>
                    <button class="btn btn-outline-danger btn-sm" onclick="showAlertModal()" title="Send Alerts">
                        <i class="bi bi-bell"></i> Alert
                    </button>
                </div>
            </div>

            <!-- Date Range and Filter Controls -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-3">
                    <div class="row g-3" id="filterControls">
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Start Date</label>
                            <input type="date" class="form-control form-control-sm" id="startDate" value="<?= date('Y-m-01') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">End Date</label>
                            <input type="date" class="form-control form-control-sm" id="endDate" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Department Filter</label>
                            <select class="form-select form-select-sm" id="departmentFilter">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button" class="btn btn-primary btn-sm w-100" onclick="applyFilters()">
                                <i class="bi bi-funnel"></i> Apply
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Key Performance Metrics -->
            <div class="row g-3 mb-4" id="metricsCards">
                <div class="col-xl-3 col-lg-6">
                    <div class="card border-0 h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <div class="card-body text-white p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h4 class="mb-1 fw-bold" id="totalEmployees">-</h4>
                                    <p class="mb-1 small">Total Employees</p>
                                    <small class="opacity-75">Active: <span id="activeEmployees">-</span></small>
                                </div>
                                <div>
                                    <i class="bi bi-people-fill" style="font-size: 2.5rem; opacity: 0.7;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-6">
                    <div class="card border-0 h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <div class="card-body text-white p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h4 class="mb-1 fw-bold" id="attendanceRate">-</h4>
                                    <p class="mb-1 small">Attendance Rate</p>
                                    <small class="opacity-75">Present: <span id="presentRecords">-</span></small>
                                </div>
                                <div>
                                    <i class="bi bi-calendar-check" style="font-size: 2.5rem; opacity: 0.7;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-6">
                    <div class="card border-0 h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <div class="card-body text-white p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h4 class="mb-1 fw-bold" id="complianceScore">-</h4>
                                    <p class="mb-1 small">Compliance Score</p>
                                    <small class="opacity-75">Issues: <span id="complianceIssues">-</span></small>
                                </div>
                                <div>
                                    <i class="bi bi-shield-check" style="font-size: 2.5rem; opacity: 0.7;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-6">
                    <div class="card border-0 h-100" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                        <div class="card-body text-white p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h4 class="mb-1 fw-bold" id="avgWorkingHours">-</h4>
                                    <p class="mb-1 small">Avg Working Hours</p>
                                    <small class="opacity-75">Pending: <span id="pendingLeaves">-</span></small>
                                </div>
                                <div>
                                    <i class="bi bi-clock" style="font-size: 2.5rem; opacity: 0.7;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts and Analytics -->
            <div class="row g-3 mb-4">
                <!-- Attendance Trends Chart -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light border-0 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-semibold">📈 Attendance Trends</h6>
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-primary active" data-period="daily" onclick="changeTrendPeriod('daily')">Daily</button>
                                <button type="button" class="btn btn-outline-primary" data-period="weekly" onclick="changeTrendPeriod('weekly')">Weekly</button>
                                <button type="button" class="btn btn-outline-primary" data-period="monthly" onclick="changeTrendPeriod('monthly')">Monthly</button>
                            </div>
                        </div>
                        <div class="card-body">
                            <canvas id="attendanceTrendChart" height="300"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light border-0">
                            <h6 class="mb-0 fw-semibold">📋 Quick Statistics</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-3">
                                <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">
                                    <span class="small">Total Leave Requests</span>
                                    <span class="badge bg-primary" id="totalLeaveRequests">-</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">
                                    <span class="small">Approved Leaves</span>
                                    <span class="badge bg-success" id="approvedLeaves">-</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">
                                    <span class="small">Total Leave Days</span>
                                    <span class="badge bg-info" id="totalLeaveDays">-</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">
                                    <span class="small">Attendance Records</span>
                                    <span class="badge bg-secondary" id="totalAttendanceRecords">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Analytics Tables -->
            <div class="row g-3">
                <!-- Compliance Details -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-0 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-semibold">⚠️ Compliance Issues</h6>
                            <button class="btn btn-sm btn-outline-primary" onclick="showComplianceDetails()">
                                <i class="bi bi-eye"></i> View Details
                            </button>
                        </div>
                        <div class="card-body">
                            <div id="complianceTable">
                                <div class="text-center py-3">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Employee Performance -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-0 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-semibold">🏆 Top Performers</h6>
                            <button class="btn btn-sm btn-outline-primary" onclick="showEmployeePerformance()">
                                <i class="bi bi-eye"></i> View All
                            </button>
                        </div>
                        <div class="card-body">
                            <div id="performanceTable">
                                <div class="text-center py-3">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-download"></i> Export Analytics Data
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="exportForm">
                    <div class="mb-3">
                        <label class="form-label">Export Format</label>
                        <select class="form-select" name="format" required>
                            <option value="excel">Excel (.xls)</option>
                            <option value="json">JSON (.json)</option>
                            <option value="csv">CSV (.csv)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Include Data</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="includeMetrics" checked>
                            <label class="form-check-label" for="includeMetrics">Key Metrics</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="includeTrends" checked>
                            <label class="form-check-label" for="includeTrends">Attendance Trends</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="includeCompliance">
                            <label class="form-check-label" for="includeCompliance">Compliance Details</label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="exportAnalytics()">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Report Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="bi bi-calendar"></i> Schedule Report
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="scheduleForm">
                    <div class="mb-3">
                        <label class="form-label">Report Frequency</label>
                        <select class="form-select" name="frequency" required>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control" name="email" required 
                               placeholder="Enter email to receive reports">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Report Type</label>
                        <select class="form-select" name="report_type" required>
                            <option value="summary">Summary Report</option>
                            <option value="detailed">Detailed Analytics</option>
                            <option value="compliance">Compliance Report</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="scheduleReport()">
                    <i class="bi bi-calendar-plus"></i> Schedule
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Alert Modal -->
<div class="modal fade" id="alertModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="bi bi-bell"></i> Send Alert
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="alertForm">
                    <div class="mb-3">
                        <label class="form-label">Alert Type</label>
                        <select class="form-select" name="alert_type" required>
                            <option value="compliance">Compliance Warning</option>
                            <option value="attendance">Attendance Alert</option>
                            <option value="performance">Performance Notice</option>
                            <option value="general">General Alert</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea class="form-control" name="message" rows="3" required 
                                  placeholder="Enter alert message"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Recipients</label>
                        <input type="text" class="form-control" name="recipients" required 
                               placeholder="Enter email addresses (comma separated)">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="sendAlert()">
                    <i class="bi bi-send"></i> Send Alert
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Compliance Details Modal -->
<div class="modal fade" id="complianceDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="bi bi-shield-exclamation"></i> Compliance Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="complianceDetailsContent">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Employee Performance Modal -->
<div class="modal fade" id="performanceModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="bi bi-trophy"></i> Employee Performance Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="performanceDetailsContent">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let attendanceTrendChart = null;
let currentTrendPeriod = 'daily';

// Initialize dashboard
document.addEventListener('DOMContentLoaded', function() {
    loadDashboardData();
    loadCompliancePreview();
    loadPerformancePreview();
    
    // Auto-refresh every 5 minutes
    setInterval(loadDashboardData, 300000);
});

// Load main dashboard data
function loadDashboardData() {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    const department = document.getElementById('departmentFilter').value;
    
    const formData = new FormData();
    formData.append('action', 'get_dashboard_data');
    formData.append('start_date', startDate);
    formData.append('end_date', endDate);
    formData.append('department', department);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        updateMetricsCards(data);
        loadAttendanceTrends();
    })
    .catch(error => {
        console.error('Error loading dashboard data:', error);
        showAlert('danger', 'Error loading dashboard data');
    });
}

// Update metrics cards
function updateMetricsCards(data) {
    document.getElementById('totalEmployees').textContent = data.totalEmployees || '0';
    document.getElementById('activeEmployees').textContent = data.activeEmployees || '0';
    document.getElementById('attendanceRate').textContent = data.attendanceRate || '0%';
    document.getElementById('presentRecords').textContent = data.presentRecords || '0';
    document.getElementById('complianceScore').textContent = data.complianceScore || '0%';
    document.getElementById('complianceIssues').textContent = data.complianceIssues || '0';
    document.getElementById('avgWorkingHours').textContent = data.avgWorkingHours || '0 hrs';
    document.getElementById('pendingLeaves').textContent = data.pendingLeaves || '0';
    document.getElementById('totalLeaveRequests').textContent = data.totalLeaveRequests || '0';
    document.getElementById('approvedLeaves').textContent = data.approvedLeaves || '0';
    document.getElementById('totalLeaveDays').textContent = data.totalLeaveDays || '0';
    document.getElementById('totalAttendanceRecords').textContent = data.totalAttendanceRecords || '0';
}

// Load attendance trends chart
function loadAttendanceTrends() {
    const formData = new FormData();
    formData.append('action', 'get_attendance_trends');
    formData.append('period', currentTrendPeriod);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        updateAttendanceTrendChart(data);
    })
    .catch(error => {
        console.error('Error loading attendance trends:', error);
    });
}

// Update attendance trend chart
function updateAttendanceTrendChart(data) {
    const ctx = document.getElementById('attendanceTrendChart').getContext('2d');
    
    if (attendanceTrendChart) {
        attendanceTrendChart.destroy();
    }
    
    const labels = data.map(item => item.period);
    const attendanceRates = data.map(item => item.attendance_rate);
    const presentCounts = data.map(item => item.present);
    const absentCounts = data.map(item => item.absent);
    
    attendanceTrendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Attendance Rate (%)',
                data: attendanceRates,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1
            }, {
                label: 'Present',
                data: presentCounts,
                borderColor: 'rgb(54, 162, 235)',
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                type: 'bar',
                yAxisID: 'y1'
            }, {
                label: 'Absent',
                data: absentCounts,
                borderColor: 'rgb(255, 99, 132)',
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                type: 'bar',
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Attendance Rate (%)'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Count'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: `Attendance Trends (${currentTrendPeriod.charAt(0).toUpperCase() + currentTrendPeriod.slice(1)})`
                }
            }
        }
    });
}

// Load compliance preview
function loadCompliancePreview() {
    const formData = new FormData();
    formData.append('action', 'get_compliance_details');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        let content = '';
        if (data.lateArrivals && data.lateArrivals.length > 0) {
            content += '<h6 class="text-warning mb-2">Recent Late Arrivals</h6>';
            data.lateArrivals.slice(0, 3).forEach(item => {
                content += `<div class="d-flex justify-content-between align-items-center p-2 mb-2 bg-light rounded">
                    <span class="small">${item.first_name} ${item.last_name}</span>
                    <span class="badge bg-warning text-dark">${item.date} - ${item.clock_in}</span>
                </div>`;
            });
        }
        
        if (data.missingAttendance && data.missingAttendance.length > 0) {
            content += '<h6 class="text-danger mb-2 mt-3">Missing Attendance</h6>';
            data.missingAttendance.slice(0, 3).forEach(item => {
                content += `<div class="d-flex justify-content-between align-items-center p-2 mb-2 bg-light rounded">
                    <span class="small">${item.first_name} ${item.last_name}</span>
                    <span class="badge bg-danger">${item.employee_id}</span>
                </div>`;
            });
        }
        
        if (!content) {
            content = '<div class="text-center text-success"><i class="bi bi-check-circle"></i> No compliance issues found</div>';
        }
        
        document.getElementById('complianceTable').innerHTML = content;
    })
    .catch(error => {
        document.getElementById('complianceTable').innerHTML = '<div class="text-danger">Error loading compliance data</div>';
        console.error('Error loading compliance preview:', error);
    });
}

// Load performance preview
function loadPerformancePreview() {
    const formData = new FormData();
    formData.append('action', 'get_employee_performance');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        let content = '';
        if (data && data.length > 0) {
            data.slice(0, 5).forEach((employee, index) => {
                const badgeClass = employee.attendance_rate >= 95 ? 'bg-success' : 
                                 employee.attendance_rate >= 85 ? 'bg-warning' : 'bg-danger';
                content += `<div class="d-flex justify-content-between align-items-center p-2 mb-2 bg-light rounded">
                    <div>
                        <span class="small fw-semibold">${employee.first_name} ${employee.last_name}</span>
                        <br><small class="text-muted">${employee.department_name || 'N/A'}</small>
                    </div>
                    <div class="text-end">
                        <span class="badge ${badgeClass}">${employee.attendance_rate}%</span>
                        <br><small class="text-muted">${employee.avg_hours}h avg</small>
                    </div>
                </div>`;
            });
        } else {
            content = '<div class="text-center text-muted">No performance data available</div>';
        }
        
        document.getElementById('performanceTable').innerHTML = content;
    })
    .catch(error => {
        document.getElementById('performanceTable').innerHTML = '<div class="text-danger">Error loading performance data</div>';
        console.error('Error loading performance preview:', error);
    });
}

// Event handlers
function applyFilters() {
    loadDashboardData();
}

function refreshDashboard() {
    loadDashboardData();
    loadCompliancePreview();
    loadPerformancePreview();
    showAlert('success', 'Dashboard refreshed successfully!');
}

function changeTrendPeriod(period) {
    currentTrendPeriod = period;
    
    // Update button states
    document.querySelectorAll('[data-period]').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-period="${period}"]`).classList.add('active');
    
    loadAttendanceTrends();
}

function showExportModal() {
    new bootstrap.Modal(document.getElementById('exportModal')).show();
}

function showScheduleModal() {
    new bootstrap.Modal(document.getElementById('scheduleModal')).show();
}

function showAlertModal() {
    new bootstrap.Modal(document.getElementById('alertModal')).show();
}

function exportAnalytics() {
    const form = document.getElementById('exportForm');
    const formData = new FormData(form);
    formData.append('action', 'export_analytics');
    
    // Create a temporary form for file download
    const downloadForm = document.createElement('form');
    downloadForm.method = 'POST';
    downloadForm.style.display = 'none';
    
    for (let [key, value] of formData) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value;
        downloadForm.appendChild(input);
    }
    
    document.body.appendChild(downloadForm);
    downloadForm.submit();
    document.body.removeChild(downloadForm);
    
    bootstrap.Modal.getInstance(document.getElementById('exportModal')).hide();
    showAlert('success', 'Export started successfully!');
}

function scheduleReport() {
    const form = document.getElementById('scheduleForm');
    const formData = new FormData(form);
    formData.append('action', 'schedule_report');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('scheduleModal')).hide();
            showAlert('success', data.message);
            form.reset();
        } else {
            showAlert('danger', data.message);
        }
    })
    .catch(error => {
        showAlert('danger', 'Error scheduling report');
        console.error('Error:', error);
    });
}

function sendAlert() {
    const form = document.getElementById('alertForm');
    const formData = new FormData(form);
    formData.append('action', 'send_alert');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('alertModal')).hide();
            showAlert('success', data.message);
            form.reset();
        } else {
            showAlert('danger', data.message);
        }
    })
    .catch(error => {
        showAlert('danger', 'Error sending alert');
        console.error('Error:', error);
    });
}

function showComplianceDetails() {
    const modal = new bootstrap.Modal(document.getElementById('complianceDetailsModal'));
    modal.show();
    
    const formData = new FormData();
    formData.append('action', 'get_compliance_details');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        let content = '<div class="row g-3">';
        
        if (data.lateArrivals && data.lateArrivals.length > 0) {
            content += '<div class="col-md-6"><h6 class="text-warning">Late Arrivals</h6>';
            content += '<div class="table-responsive"><table class="table table-sm">';
            content += '<thead><tr><th>Employee</th><th>Date</th><th>Clock In</th></tr></thead><tbody>';
            data.lateArrivals.forEach(item => {
                content += `<tr><td>${item.first_name} ${item.last_name}</td><td>${item.date}</td><td>${item.clock_in}</td></tr>`;
            });
            content += '</tbody></table></div></div>';
        }
        
        if (data.missingAttendance && data.missingAttendance.length > 0) {
            content += '<div class="col-md-6"><h6 class="text-danger">Missing Attendance</h6>';
            content += '<div class="table-responsive"><table class="table table-sm">';
            content += '<thead><tr><th>Employee</th><th>ID</th><th>Status</th></tr></thead><tbody>';
            data.missingAttendance.forEach(item => {
                content += `<tr><td>${item.first_name} ${item.last_name}</td><td>${item.employee_id}</td><td><span class="badge bg-warning">Missing</span></td></tr>`;
            });
            content += '</tbody></table></div></div>';
        }
        
        content += '</div>';
        
        if (!data.lateArrivals?.length && !data.missingAttendance?.length) {
            content = '<div class="text-center text-success"><i class="bi bi-check-circle-fill fs-1"></i><h5>No Compliance Issues</h5><p>All compliance metrics are within acceptable ranges.</p></div>';
        }
        
        document.getElementById('complianceDetailsContent').innerHTML = content;
    })
    .catch(error => {
        document.getElementById('complianceDetailsContent').innerHTML = '<div class="alert alert-danger">Error loading compliance details</div>';
        console.error('Error:', error);
    });
}

function showEmployeePerformance() {
    const modal = new bootstrap.Modal(document.getElementById('performanceModal'));
    modal.show();
    
    const formData = new FormData();
    formData.append('action', 'get_employee_performance');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        let content = '<div class="table-responsive">';
        content += '<table class="table table-hover">';
        content += '<thead class="table-dark"><tr><th>Employee</th><th>Department</th><th>Attendance Rate</th><th>Avg Hours</th><th>Late Count</th><th>Performance</th></tr></thead><tbody>';
        
        if (data && data.length > 0) {
            data.forEach(employee => {
                const rateClass = employee.attendance_rate >= 95 ? 'success' : 
                                employee.attendance_rate >= 85 ? 'warning' : 'danger';
                const performanceIcon = employee.attendance_rate >= 95 ? '🏆' : 
                                      employee.attendance_rate >= 85 ? '👍' : '⚠️';
                
                content += `<tr>
                    <td><strong>${employee.first_name} ${employee.last_name}</strong><br><small class="text-muted">${employee.employee_id}</small></td>
                    <td>${employee.department_name || 'N/A'}</td>
                    <td><span class="badge bg-${rateClass}">${employee.attendance_rate}%</span></td>
                    <td>${employee.avg_hours} hrs</td>
                    <td>${employee.late_count}</td>
                    <td>${performanceIcon}</td>
                </tr>`;
            });
        } else {
            content += '<tr><td colspan="6" class="text-center">No performance data available</td></tr>';
        }
        
        content += '</tbody></table></div>';
        document.getElementById('performanceDetailsContent').innerHTML = content;
    })
    .catch(error => {
        document.getElementById('performanceDetailsContent').innerHTML = '<div class="alert alert-danger">Error loading performance details</div>';
        console.error('Error:', error);
    });
}

function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        if (alertDiv && alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

// Print styles
const printStyles = document.createElement('style');
printStyles.textContent = `
    @media print {
        .btn, .modal { display: none !important; }
        .main-content { margin: 0 !important; padding: 0 !important; }
        .card { break-inside: avoid; margin-bottom: 20px; }
        .row { break-inside: avoid; }
    }
`;
document.head.appendChild(printStyles);
</script>

<?php include 'layouts/footer.php'; ?>
