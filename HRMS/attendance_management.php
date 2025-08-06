<?php
session_start();
// Check for either session variable for compatibility
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Include config and database

include '../config.php';
if (!isset($root_path)) 
include '../db.php';
if (!isset($root_path)) 
include '../auth_guard.php';

$page_title = 'Attendance Management - HRMS';

// Handle attendance actions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'mark_attendance':
                $employee_id = intval($_POST['employee_id']);
                $attendance_date = mysqli_real_escape_string($conn, $_POST['attendance_date']);
                $check_in_time = mysqli_real_escape_string($conn, $_POST['check_in_time']);
                $check_out_time = mysqli_real_escape_string($conn, $_POST['check_out_time']);
                $status = mysqli_real_escape_string($conn, $_POST['status']);
                $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
                
                // Calculate working hours
                $working_hours = 0;
                if ($check_in_time && $check_out_time) {
                    $in_time = new DateTime($check_in_time);
                    $out_time = new DateTime($check_out_time);
                    $diff = $out_time->diff($in_time);
                    $working_hours = $diff->h + ($diff->i / 60);
                }
                
                $query = "INSERT INTO attendance (employee_id, attendance_date, check_in, check_out, status, work_duration, notes)
                         VALUES ($employee_id, '$attendance_date', " . 
                         ($check_in_time ? "'$check_in_time'" : 'NULL') . ", " . 
                         ($check_out_time ? "'$check_out_time'" : 'NULL') . ", '$status', $working_hours, '$notes')
                         ON DUPLICATE KEY UPDATE 
                         check_in = " . ($check_in_time ? "'$check_in_time'" : 'check_in') . ",
                         check_out = " . ($check_out_time ? "'$check_out_time'" : 'check_out') . ",
                         status = '$status',
                         work_duration = $working_hours,
                         notes = '$notes'";
                
                if (mysqli_query($conn, $query)) {
                    $success_message = "Attendance marked successfully!";
                } else {
                    $error_message = "Error marking attendance: " . mysqli_error($conn);
                }
                break;
                
            case 'bulk_mark_attendance':
                $attendance_date = mysqli_real_escape_string($conn, $_POST['attendance_date']);
                $status = mysqli_real_escape_string($conn, $_POST['status']);
                
                // Get all active employees
                $employees = mysqli_query($conn, "SELECT employee_id FROM employees WHERE status = 'active'");
                $success_count = 0;
                
                while ($employee = mysqli_fetch_assoc($employees)) {
                    $query = "INSERT INTO attendance (employee_id, attendance_date, status)
                             VALUES ({$employee['employee_id']}, '$attendance_date', '$status')
                             ON DUPLICATE KEY UPDATE status = '$status'";
                    
                    if (mysqli_query($conn, $query)) {
                        $success_count++;
                    }
                }
                
                $success_message = "Bulk attendance marked for $success_count employees!";
                break;
        }
    }
}

// Get today's attendance summary
$today = date('Y-m-d');
$attendanceStats = [
    'total_employees' => 0,
    'present' => 0,
    'absent' => 0,
    'late' => 0,
    'on_leave' => 0
];

$statsQuery = "
    SELECT 
        (SELECT COUNT(*) FROM employees WHERE status = 'active') as total_employees,
        SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN a.status = 'Absent' OR a.id IS NULL THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late,
        SUM(CASE WHEN a.status = 'On Leave' THEN 1 ELSE 0 END) as on_leave
    FROM employees e
    LEFT JOIN attendance a ON e.employee_id = a.employee_id AND a.attendance_date = '$today'
    WHERE e.status = 'active'
";

$result = mysqli_query($conn, $statsQuery);
if ($result) {
    $attendanceStats = mysqli_fetch_assoc($result);
    $attendanceStats['absent'] = $attendanceStats['total_employees'] - $attendanceStats['present'] - $attendanceStats['late'] - $attendanceStats['on_leave'];
}

// Get today's detailed attendance
$todayAttendance = [];
$query = "
    SELECT e.employee_id, e.name, e.employee_code, e.position, d.department_name,
           a.check_in, a.check_out, a.status, a.work_duration, a.notes
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN attendance a ON e.employee_id = a.employee_id AND a.attendance_date = '$today'
    WHERE e.status = 'active'
    ORDER BY e.name
";

$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $row['status'] = $row['status'] ?? 'Absent';
        $todayAttendance[] = $row;
    }
}

// Get attendance trends for the last 7 days
$attendanceTrends = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dateFormatted = date('M j', strtotime($date));
    
    $trendQuery = "
        SELECT 
            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late,
            SUM(CASE WHEN a.status = 'Absent' OR a.id IS NULL THEN 1 ELSE 0 END) as absent
        FROM employees e
        LEFT JOIN attendance a ON e.employee_id = a.employee_id AND a.attendance_date = '$date'
        WHERE e.status = 'active'
    ";
    
    $result = mysqli_query($conn, $trendQuery);
    if ($result) {
        $trend = mysqli_fetch_assoc($result);
        $attendanceTrends[] = [
            'date' => $dateFormatted,
            'present' => $trend['present'],
            'late' => $trend['late'],
            'absent' => $trend['absent']
        ];
    }
}

// Get employees for dropdown
$employees = [];
$result = mysqli_query($conn, "SELECT employee_id, name, employee_code FROM employees WHERE status = 'active' ORDER BY name");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $employees[] = $row;
    }
}

// Get late arrivals today
$lateArrivals = [];
$query = "
    SELECT e.name, e.employee_code, a.check_in_time, d.department_name
    FROM attendance a
    JOIN employees e ON a.employee_id = e.employee_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    WHERE a.attendance_date = '$today' 
    AND a.status = 'Late'
    ORDER BY a.check_in_time DESC
";

$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $lateArrivals[] = $row;
    }
}

include '../layouts/header.php';
if (!isset($root_path)) 
include '../layouts/sidebar.php';
?>

<div class="main-content animate-fade-in-up">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="gradient-text mb-2" style="font-size: 2.5rem; font-weight: 700;">
                    <i class="bi bi-calendar-check text-success me-3"></i>Attendance Management
                </h1>
                <p class="text-muted" style="font-size: 1.1rem;">Monitor, track, and manage employee attendance records</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-info" onclick="exportAttendanceReport()">
                    <i class="bi bi-download"></i> Export Report
                </button>
                <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#bulkAttendanceModal">
                    <i class="bi bi-people"></i> Bulk Mark
                </button>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#markAttendanceModal">
                    <i class="bi bi-plus-circle"></i> Mark Attendance
                </button>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i><?= $success_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Attendance Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-gradient-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="card-title h2 mb-2"><?= $attendanceStats['present'] ?></h3>
                                <p class="card-text opacity-90">Present Today</p>
                                <small class="opacity-75">
                                    <?= round(($attendanceStats['present'] / max($attendanceStats['total_employees'], 1)) * 100, 1) ?>% attendance
                                </small>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-person-check"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-gradient-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="card-title h2 mb-2"><?= $attendanceStats['late'] ?></h3>
                                <p class="card-text opacity-90">Late Arrivals</p>
                                <small class="opacity-75">Today's count</small>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-clock"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-gradient-danger text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="card-title h2 mb-2"><?= $attendanceStats['absent'] ?></h3>
                                <p class="card-text opacity-90">Absent Today</p>
                                <small class="opacity-75">Not marked</small>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-person-x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-gradient-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="card-title h2 mb-2"><?= $attendanceStats['on_leave'] ?></h3>
                                <p class="card-text opacity-90">On Leave</p>
                                <small class="opacity-75">Approved leaves</small>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-calendar-x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="row g-4">
            <!-- Today's Attendance -->
            <div class="col-xl-9">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-calendar-day text-primary"></i> Today's Attendance - <?= date('F j, Y') ?>
                        </h5>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-secondary active">Today</button>
                            <button class="btn btn-outline-secondary" onclick="loadAttendanceDate('yesterday')">Yesterday</button>
                            <button class="btn btn-outline-secondary" onclick="loadAttendanceDate('custom')">Custom Date</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="attendanceTable">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Check-in</th>
                                        <th>Check-out</th>
                                        <th>Working Hours</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($todayAttendance as $attendance): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm me-3">
                                                        <div class="avatar-initial rounded-circle bg-primary text-white">
                                                            <?= substr($attendance['name'], 0, 2) ?>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0"><?= htmlspecialchars($attendance['name']) ?></h6>
                                                        <small class="text-muted"><?= htmlspecialchars($attendance['employee_code']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($attendance['department_name'] ?? 'N/A') ?></td>
                                            <td>
                                                <?= $attendance['check_in'] ? date('h:i A', strtotime($attendance['check_in'])) : '<span class="text-muted">-</span>' ?>
                                            </td>
                                            <td>
                                                <?= $attendance['check_out'] ? date('h:i A', strtotime($attendance['check_out'])) : '<span class="text-muted">-</span>' ?>
                                            </td>
                                            <td>
                                                <?= $attendance['work_duration'] ? number_format($attendance['work_duration'], 1) . 'h' : '<span class="text-muted">-</span>' ?>
                                            </td>
                                            <td>
                                                <?php
                                                $statusClass = match($attendance['status']) {
                                                    'Present' => 'success',
                                                    'Late' => 'warning',
                                                    'Half Day' => 'info',
                                                    'On Leave' => 'secondary',
                                                    'Work From Home' => 'primary',
                                                    default => 'danger'
                                                };
                                                ?>
                                                <span class="badge bg-<?= $statusClass ?>"><?= $attendance['status'] ?></span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars(substr($attendance['notes'] ?? '', 0, 30)) ?>
                                                    <?= strlen($attendance['notes'] ?? '') > 30 ? '...' : '' ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" 
                                                            onclick="editAttendance(<?= $attendance['employee_id'] ?>, '<?= $today ?>')"
                                                            data-bs-toggle="tooltip" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button class="btn btn-outline-info" 
                                                            onclick="viewAttendanceHistory(<?= $attendance['employee_id'] ?>)"
                                                            data-bs-toggle="tooltip" title="History">
                                                        <i class="bi bi-clock-history"></i>
                                                    </button>
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

            <!-- Sidebar Info -->
            <div class="col-xl-3">
                <!-- Late Arrivals -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-clock text-warning"></i> Late Arrivals Today
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($lateArrivals)): ?>
                            <div class="text-center text-muted py-3">
                                <i class="bi bi-check-circle display-6"></i>
                                <p class="mt-2 small">No late arrivals today!</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($lateArrivals as $late): ?>
                                    <div class="list-group-item px-0 border-0">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($late['name']) ?></h6>
                                                <small class="text-muted"><?= htmlspecialchars($late['employee_code']) ?></small>
                                            </div>
                                            <small class="text-warning">
                                                <?= date('h:i A', strtotime($late['check_in_time'])) ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Attendance Trends Chart -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-graph-up text-info"></i> 7-Day Trend
                        </h6>
                    </div>
                    <div class="card-body">
                        <canvas id="attendanceTrendChart" style="height: 250px;"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mark Attendance Modal -->
<div class="modal fade" id="markAttendanceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Mark Attendance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="mark_attendance">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Employee *</label>
                            <select class="form-select" name="employee_id" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?= $emp['employee_id'] ?>"><?= htmlspecialchars($emp['name']) ?> (<?= htmlspecialchars($emp['employee_code']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date *</label>
                            <input type="date" class="form-control" name="attendance_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Check-in Time</label>
                            <input type="time" class="form-control" name="check_in_time">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Check-out Time</label>
                            <input type="time" class="form-control" name="check_out_time">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status *</label>
                            <select class="form-select" name="status" required>
                                <option value="Present">Present</option>
                                <option value="Late">Late</option>
                                <option value="Half Day">Half Day</option>
                                <option value="Absent">Absent</option>
                                <option value="On Leave">On Leave</option>
                                <option value="Work From Home">Work From Home</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Add any notes or comments"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Mark Attendance</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Mark Attendance Modal -->
<div class="modal fade" id="bulkAttendanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Mark Attendance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="bulk_mark_attendance">
                    <div class="mb-3">
                        <label class="form-label">Date *</label>
                        <input type="date" class="form-control" name="attendance_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status for All Employees *</label>
                        <select class="form-select" name="status" required>
                            <option value="">Select Status</option>
                            <option value="Present">Present</option>
                            <option value="Absent">Absent</option>
                            <option value="On Leave">On Leave</option>
                            <option value="Work From Home">Work From Home</option>
                        </select>
                    </div>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        This will mark attendance for all active employees with the selected status.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Bulk Mark</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Initialize DataTable
document.addEventListener('DOMContentLoaded', function() {
    $('#attendanceTable').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[0, 'asc']],
        columnDefs: [
            { orderable: false, targets: [7] }
        ]
    });
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize attendance trend chart
    initAttendanceTrendChart();
});

// Attendance Trend Chart
function initAttendanceTrendChart() {
    const ctx = document.getElementById('attendanceTrendChart').getContext('2d');
    const trendData = <?= json_encode($attendanceTrends) ?>;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: trendData.map(d => d.date),
            datasets: [
                {
                    label: 'Present',
                    data: trendData.map(d => d.present),
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4
                },
                {
                    label: 'Late',
                    data: trendData.map(d => d.late),
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    tension: 0.4
                },
                {
                    label: 'Absent',
                    data: trendData.map(d => d.absent),
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

// Edit Attendance Function
function editAttendance(employeeId, date) {
    // Implement edit attendance modal
    alert('Edit attendance for Employee ID: ' + employeeId + ' on ' + date);
}

// View Attendance History Function
function viewAttendanceHistory(employeeId) {
    // Implement attendance history modal
    alert('View attendance history for Employee ID: ' + employeeId);
}

// Load Attendance by Date
function loadAttendanceDate(period) {
    if (period === 'custom') {
        const date = prompt('Enter date (YYYY-MM-DD):');
        if (date) {
            window.location.href = '?date=' + date;
        }
    } else if (period === 'yesterday') {
        const yesterday = new Date();
        yesterday.setDate(yesterday.getDate() - 1);
        const dateStr = yesterday.toISOString().split('T')[0];
        window.location.href = '?date=' + dateStr;
    }
}

// Export Attendance Report Function
function exportAttendanceReport() {
    window.open('api/export_attendance_report.php', '_blank');
}
</script>

<?php if (!isset($root_path)) 
include '../layouts/footer.php'; ?>
