<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';
$page_title = 'Attendance Calendar';

// Get attendance data for calendar
$currentMonth = $_GET['month'] ?? date('Y-m');
$attendanceData = [];

$attendanceQuery = $conn->query("
    SELECT a.*, e.name as employee_name 
    FROM attendance a 
    JOIN employees e ON a.employee_id = e.employee_id 
    WHERE DATE_FORMAT(a.attendance_date, '%Y-%m') = '$currentMonth'
    ORDER BY a.attendance_date DESC
");

if ($attendanceQuery) {
    while ($row = $attendanceQuery->fetch_assoc()) {
        $date = $row['attendance_date'];
        if (!isset($attendanceData[$date])) {
            $attendanceData[$date] = [];
        }
        $attendanceData[$date][] = $row;
    }
}

// Get summary statistics
$totalEmployees = 0;
$presentToday = 0;
$absentToday = 0;
$avgAttendance = 0;

$statsQuery = $conn->query("
    SELECT 
        COUNT(DISTINCT e.employee_id) as total_employees,
        COUNT(CASE WHEN a.status = 'Present' AND a.attendance_date = CURDATE() THEN 1 END) as present_today,
        COUNT(CASE WHEN a.status = 'Absent' AND a.attendance_date = CURDATE() THEN 1 END) as absent_today
    FROM employees e 
    LEFT JOIN attendance a ON e.employee_id = a.employee_id
");

if ($statsQuery && $row = $statsQuery->fetch_assoc()) {
    $totalEmployees = $row['total_employees'] ?? 0;
    $presentToday = $row['present_today'] ?? 0;
    $absentToday = $row['absent_today'] ?? 0;
    $avgAttendance = $totalEmployees > 0 ? ($presentToday / $totalEmployees) * 100 : 0;
}

include 'layouts/header.php';
include 'layouts/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Attendance Calendar</h1>
            <p class="text-muted">View employee attendance patterns and history</p>
        </div>
        <div>
            <a href="pages/attendance/attendance.php" class="btn btn-primary">
                <i class="bi bi-calendar-check"></i> Mark Attendance
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Employees</h6>
                            <h3 class="mb-0"><?= $totalEmployees ?></h3>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-people"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Present Today</h6>
                            <h3 class="mb-0"><?= $presentToday ?></h3>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-person-check"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Absent Today</h6>
                            <h3 class="mb-0"><?= $absentToday ?></h3>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-person-x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Attendance Rate</h6>
                            <h3 class="mb-0"><?= number_format($avgAttendance, 1) ?>%</h3>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-graph-up"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Calendar Controls -->
    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-calendar3 me-2"></i>Attendance Calendar</h5>
                <div>
                    <form method="GET" class="d-flex align-items-center gap-3">
                        <label class="form-label mb-0">Month:</label>
                        <input type="month" name="month" class="form-control" value="<?= $currentMonth ?>" onchange="this.form.submit()">
                    </form>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div id="calendar"></div>
        </div>
    </div>

    <!-- Legend -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Legend</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <div class="d-flex align-items-center mb-2">
                        <div class="bg-success rounded me-2" style="width: 20px; height: 20px;"></div>
                        <span>Present</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="d-flex align-items-center mb-2">
                        <div class="bg-danger rounded me-2" style="width: 20px; height: 20px;"></div>
                        <span>Absent</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="d-flex align-items-center mb-2">
                        <div class="bg-warning rounded me-2" style="width: 20px; height: 20px;"></div>
                        <span>Late</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="d-flex align-items-center mb-2">
                        <div class="bg-info rounded me-2" style="width: 20px; height: 20px;"></div>
                        <span>Half Day</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Detail Modal -->
<div class="modal fade" id="attendanceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
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

<!-- Include FullCalendar CSS and JS -->
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css' rel='stylesheet' />
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.js'></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    
    // Prepare events data
    var events = [];
    
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
        height: 600,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,listMonth'
        },
        events: events,
        eventClick: function(info) {
            showAttendanceDetails(info.event.extendedProps);
        },
        dateClick: function(info) {
            // Optionally handle date clicks for adding attendance
        }
    });
    
    calendar.render();
});

function showAttendanceDetails(eventProps) {
    var modalBody = document.getElementById('attendanceModalBody');
    var attendance = eventProps.attendance;
    var summary = eventProps.summary;
    
    var html = `
        <div class="row mb-3">
            <div class="col-md-6">
                <h6>Date: <strong>${eventProps.date}</strong></h6>
            </div>
            <div class="col-md-6">
                <h6>Total Employees: <strong>${summary.total}</strong></h6>
            </div>
        </div>
        
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
                        <th>Employee</th>
                        <th>Status</th>
                        <th>Time In</th>
                        <th>Time Out</th>
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
        
        html += `
            <tr>
                <td><strong>${record.employee_name}</strong></td>
                <td><span class="badge ${statusClass}">${record.status}</span></td>
                <td>${record.time_in || '-'}</td>
                <td>${record.time_out || '-'}</td>
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
</script>

<?php include 'layouts/footer.php'; ?>
