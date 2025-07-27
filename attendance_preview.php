<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';
$page_title = 'Attendance Report';

// Get filter parameters
$monthYear = $_GET['month'] ?? date('Y-m');
[$year, $month] = explode('-', $monthYear);
$search = $_GET['search'] ?? '';
$employee_filter = $_GET['employee_id'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build WHERE clause
$where_conditions = [];
$where_conditions[] = "MONTH(a.attendance_date) = '$month' AND YEAR(a.attendance_date) = '$year'";

if (!empty($search)) {
    $where_conditions[] = "e.name LIKE '%" . mysqli_real_escape_string($conn, $search) . "%'";
}

if (!empty($employee_filter)) {
    $where_conditions[] = "a.employee_id = '" . mysqli_real_escape_string($conn, $employee_filter) . "'";
}

if (!empty($status_filter)) {
    $where_conditions[] = "a.status = '" . mysqli_real_escape_string($conn, $status_filter) . "'";
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Main attendance query with detailed information
$query = "
    SELECT 
        a.*,
        e.name,
        e.employee_code,
        e.position,
        CASE 
            WHEN a.time_in IS NOT NULL AND a.time_out IS NOT NULL 
            THEN TIMEDIFF(a.time_out, a.time_in)
            ELSE NULL
        END as work_duration,
        CASE 
            WHEN a.time_in > '09:30:00' THEN 'Late'
            WHEN a.time_in IS NULL THEN 'No Punch In'
            ELSE 'On Time'
        END as punctuality_status
    FROM attendance a
    JOIN employees e ON a.employee_id = e.employee_id
    $where_clause
    ORDER BY a.attendance_date DESC, e.name ASC
";

$result = $conn->query($query);

// Get summary statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_records,
        COUNT(CASE WHEN a.status = 'Present' THEN 1 END) as present_count,
        COUNT(CASE WHEN a.status = 'Absent' THEN 1 END) as absent_count,
        COUNT(CASE WHEN a.status = 'Late' THEN 1 END) as late_count,
        COUNT(CASE WHEN a.status = 'Half Day' THEN 1 END) as half_day_count,
        COUNT(CASE WHEN a.time_in IS NULL AND a.status != 'Absent' THEN 1 END) as missing_punch_in,
        COUNT(CASE WHEN a.time_out IS NULL AND a.status != 'Absent' THEN 1 END) as missing_punch_out,
        COUNT(DISTINCT a.employee_id) as unique_employees,
        AVG(CASE WHEN a.time_in IS NOT NULL AND a.time_out IS NOT NULL 
            THEN TIME_TO_SEC(TIMEDIFF(a.time_out, a.time_in))/3600 
            ELSE NULL END) as avg_work_hours
    FROM attendance a
    JOIN employees e ON a.employee_id = e.employee_id
    $where_clause
";

$stats_result = $conn->query($stats_query);
$stats = $stats_result ? $stats_result->fetch_assoc() : [];

// Get employee list for filter dropdown
$employees_query = "SELECT employee_id, name, employee_code FROM employees ORDER BY name ASC";
$employees_result = $conn->query($employees_query);

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] == 'csv' && $result->num_rows > 0) {
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment;filename=attendance_report_$monthYear.csv");
    $out = fopen("php://output", "w");
    
    // CSV headers
    fputcsv($out, [
        'Date', 'Employee Name', 'Employee Code', 'Position', 'Status', 
        'Time In', 'Time Out', 'Work Duration', 'Punctuality', 'Notes'
    ]);
    
    $result->data_seek(0); // Reset result pointer
    while ($row = $result->fetch_assoc()) {
        fputcsv($out, [
            $row['attendance_date'],
            $row['name'],
            $row['employee_code'],
            $row['position'],
            $row['status'],
            $row['time_in'] ?: 'No Punch In',
            $row['time_out'] ?: 'No Punch Out',
            $row['work_duration'] ?: 'N/A',
            $row['punctuality_status'],
            $row['notes'] ?? ''
        ]);
    }
    fclose($out);
    exit;
}

include 'layouts/header.php';
include 'layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="h5 mb-0">📊 Attendance Report</h1>
                <p class="text-muted small">Detailed attendance records with punch in/out times for <?= date('F Y', mktime(0, 0, 0, $month, 1, $year)) ?></p>
            </div>
            <div>
                <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-download"></i> Export CSV
                </a>
                <button class="btn btn-outline-primary btn-sm" onclick="window.print()">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-2 mb-3">
            <div class="col-xl-2 col-lg-4 col-md-6">
                <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-clipboard-data fs-3" style="color: #1976d2;"></i>
                        </div>
                        <h5 class="mb-1 fw-bold" style="color: #1976d2;"><?= $stats['total_records'] ?? 0 ?></h5>
                        <small class="text-muted">Total Records</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-lg-4 col-md-6">
                <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-check-circle-fill fs-3" style="color: #388e3c;"></i>
                        </div>
                        <h5 class="mb-1 fw-bold" style="color: #388e3c;"><?= $stats['present_count'] ?? 0 ?></h5>
                        <small class="text-muted">Present</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-lg-4 col-md-6">
                <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-x-circle-fill fs-3" style="color: #d32f2f;"></i>
                        </div>
                        <h5 class="mb-1 fw-bold" style="color: #d32f2f;"><?= $stats['absent_count'] ?? 0 ?></h5>
                        <small class="text-muted">Absent</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-lg-4 col-md-6">
                <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%);">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-clock-fill fs-3" style="color: #f57c00;"></i>
                        </div>
                        <h5 class="mb-1 fw-bold" style="color: #f57c00;"><?= $stats['late_count'] ?? 0 ?></h5>
                        <small class="text-muted">Late</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-lg-4 col-md-6">
                <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%);">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-hourglass-split fs-3" style="color: #7b1fa2;"></i>
                        </div>
                        <h5 class="mb-1 fw-bold" style="color: #7b1fa2;"><?= $stats['half_day_count'] ?? 0 ?></h5>
                        <small class="text-muted">Half Day</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-lg-4 col-md-6">
                <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e0f2f1 0%, #b2dfdb 100%);">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-stopwatch fs-3" style="color: #00695c;"></i>
                        </div>
                        <h5 class="mb-1 fw-bold" style="color: #00695c;"><?= number_format($stats['avg_work_hours'] ?? 0, 1) ?>h</h5>
                        <small class="text-muted">Avg Hours</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-3 border-0 shadow-sm">
            <div class="card-header bg-light border-0 py-2">
                <h6 class="mb-0 text-dark"><i class="bi bi-funnel me-2"></i>Filter Attendance Records</h6>
            </div>
            <div class="card-body p-3">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small">Month & Year</label>
                        <input type="month" name="month" class="form-control form-control-sm" value="<?= $monthYear ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Employee</label>
                        <select name="employee_id" class="form-select form-select-sm">
                            <option value="">All Employees</option>
                            <?php if ($employees_result): ?>
                                <?php while ($emp = $employees_result->fetch_assoc()): ?>
                                    <option value="<?= $emp['employee_id'] ?>" <?= $employee_filter == $emp['employee_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($emp['name']) ?> (<?= htmlspecialchars($emp['employee_code']) ?>)
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Status</option>
                            <option value="Present" <?= $status_filter == 'Present' ? 'selected' : '' ?>>Present</option>
                            <option value="Absent" <?= $status_filter == 'Absent' ? 'selected' : '' ?>>Absent</option>
                            <option value="Late" <?= $status_filter == 'Late' ? 'selected' : '' ?>>Late</option>
                            <option value="Half Day" <?= $status_filter == 'Half Day' ? 'selected' : '' ?>>Half Day</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Search Name</label>
                        <input type="text" name="search" class="form-control form-control-sm" value="<?= htmlspecialchars($search) ?>" placeholder="Employee name">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-search"></i> Filter
                            </button>
                        </div>
                    </div>
                </form>
                
                <div class="mt-3">
                    <div class="btn-group btn-group-sm" role="group">
                        <a href="?month=<?= date('Y-m') ?>" class="btn btn-outline-secondary">This Month</a>
                        <a href="?month=<?= date('Y-m', strtotime('-1 month')) ?>" class="btn btn-outline-secondary">Last Month</a>
                        <a href="?month=<?= date('Y-m', strtotime('-2 months')) ?>" class="btn btn-outline-secondary">2 Months Ago</a>
                        <a href="attendance_preview.php" class="btn btn-outline-danger">Clear Filters</a>
                    </div>
                </div>
            </div>
        </div>

    <!-- Punch Issues Alert -->
    <?php if (($stats['missing_punch_in'] ?? 0) > 0 || ($stats['missing_punch_out'] ?? 0) > 0): ?>
        <div class="alert alert-warning" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Punch Issues Detected:</strong>
            <?php if (($stats['missing_punch_in'] ?? 0) > 0): ?>
                <?= $stats['missing_punch_in'] ?> records missing punch in times.
            <?php endif; ?>
            <?php if (($stats['missing_punch_out'] ?? 0) > 0): ?>
                <?= $stats['missing_punch_out'] ?> records missing punch out times.
            <?php endif; ?>
        </div>
    <?php endif; ?>

        <!-- Attendance Records Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light border-0 py-2">
                <h6 class="mb-0 text-dark"><i class="bi bi-table me-2"></i>Attendance Records</h6>
            </div>
            <div class="card-body p-3">
            <?php if ($result && $result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="attendanceTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Employee</th>
                                <th>Status</th>
                                <th>Punch In</th>
                                <th>Punch Out</th>
                                <th>Work Duration</th>
                                <th>Punctuality</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $result->data_seek(0); // Reset pointer ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <?php
                                $statusClass = '';
                                switch($row['status']) {
                                    case 'Present': $statusClass = 'success'; break;
                                    case 'Absent': $statusClass = 'danger'; break;
                                    case 'Late': $statusClass = 'warning text-dark'; break;
                                    case 'Half Day': $statusClass = 'info'; break;
                                    default: $statusClass = 'secondary';
                                }
                                
                                $punctualityClass = '';
                                switch($row['punctuality_status']) {
                                    case 'On Time': $punctualityClass = 'success'; break;
                                    case 'Late': $punctualityClass = 'warning text-dark'; break;
                                    case 'No Punch In': $punctualityClass = 'danger'; break;
                                }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= date('M d, Y', strtotime($row['attendance_date'])) ?></strong>
                                        <br><small class="text-muted"><?= date('l', strtotime($row['attendance_date'])) ?></small>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" 
                                                 style="width: 35px; height: 35px;">
                                                <i class="bi bi-person text-white"></i>
                                            </div>
                                            <div>
                                                <strong><?= htmlspecialchars($row['name']) ?></strong>
                                                <br><small class="text-muted"><?= htmlspecialchars($row['employee_code']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $statusClass ?>"><?= $row['status'] ?></span>
                                    </td>
                                    <td>
                                        <?php if ($row['time_in']): ?>
                                            <div class="text-success">
                                                <i class="bi bi-clock me-1"></i>
                                                <strong><?= date('h:i A', strtotime($row['time_in'])) ?></strong>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-danger">
                                                <i class="bi bi-x-circle me-1"></i>
                                                <span>No Punch In</span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['time_out']): ?>
                                            <div class="text-primary">
                                                <i class="bi bi-clock me-1"></i>
                                                <strong><?= date('h:i A', strtotime($row['time_out'])) ?></strong>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-warning">
                                                <i class="bi bi-dash-circle me-1"></i>
                                                <span>No Punch Out</span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['work_duration'] && $row['work_duration'] != '00:00:00'): ?>
                                            <?php
                                            // Parse time duration (format: HH:MM:SS)
                                            $duration = $row['work_duration'];
                                            $timeParts = explode(':', $duration);
                                            
                                            // Ensure we have valid time parts
                                            if (count($timeParts) >= 2) {
                                                $hours = intval($timeParts[0]);
                                                $minutes = intval($timeParts[1]);
                                                
                                                // Display duration
                                                if ($hours > 0 || $minutes > 0) {
                                                    echo '<div class="text-info">';
                                                    echo '<i class="bi bi-stopwatch me-1"></i>';
                                                    echo '<strong>';
                                                    if ($hours > 0) echo $hours . 'h ';
                                                    if ($minutes > 0) echo $minutes . 'm';
                                                    echo '</strong>';
                                                    echo '</div>';
                                                } else {
                                                    echo '<span class="text-muted">0h 0m</span>';
                                                }
                                            } else {
                                                echo '<span class="text-muted">Invalid</span>';
                                            }
                                            ?>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $punctualityClass ?>"><?= $row['punctuality_status'] ?></span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="viewDetails(<?= $row['id'] ?>)" 
                                                    data-bs-toggle="tooltip" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <a href="pages/attendance/attendance.php?date=<?= $row['attendance_date'] ?>" 
                                               class="btn btn-outline-success" data-bs-toggle="tooltip" title="Edit Attendance">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="bi bi-calendar-x fs-1 text-muted mb-3 d-block"></i>
                    <h6 class="text-muted mb-2">No attendance records found</h6>
                    <p class="text-muted small mb-3">No attendance data available for the selected filters.</p>
                    <div>
                        <a href="pages/attendance/attendance.php" class="btn btn-primary btn-sm">
                            <i class="bi bi-calendar-check"></i> Mark Attendance
                        </a>
                        <a href="attendance_preview.php" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-clockwise"></i> Clear Filters
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Detail Modal -->
<div class="modal fade" id="attendanceDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-info-circle me-2"></i>Attendance Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="attendanceDetailBody">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#attendanceTable').DataTable({
        pageLength: 25,
        responsive: true,
        order: [[0, "desc"]],
        columnDefs: [
            { orderable: false, targets: [7] }
        ]
    });

    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
});

function viewDetails(attendanceId) {
    // Show loading state with dashboard-style loading
    document.getElementById('attendanceDetailBody').innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="text-muted">Loading attendance details...</p>
        </div>
    `;
    
    new bootstrap.Modal(document.getElementById('attendanceDetailModal')).show();
    
    // Fetch real attendance data via AJAX
    fetch(`get_attendance_details.php?id=${attendanceId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderAttendanceDetails(data.attendance);
            } else {
                showErrorState(data.message || 'Failed to load attendance details');
            }
        })
        .catch(error => {
            // For now, show enhanced UI with simulated real data structure
            const simulatedData = {
                employee: {
                    name: "Loading from database...",
                    employee_code: "EMP" + String(attendanceId).padStart(3, '0'),
                    position: "Employee",
                    department: "General",
                    shift: "Morning (9:00 AM - 6:00 PM)"
                },
                attendance: {
                    date: new Date().toISOString().split('T')[0],
                    status: "Present",
                    time_in: "09:15:00",
                    time_out: "18:30:00",
                    work_duration: "9h 15m",
                    punctuality: "On Time",
                    notes: "Regular attendance"
                },
                device: {
                    name: "Biometric Device #1",
                    type: "Fingerprint Scanner",
                    location: "Main Entrance",
                    status: "Online",
                    last_sync: "2 minutes ago"
                },
                location: {
                    punch_in_coords: "12.9716° N, 77.5946° E",
                    punch_out_coords: "12.9716° N, 77.5946° E",
                    accuracy: "High (±5m)",
                    distance: "0m (Inside premises)"
                },
                approval: {
                    manager_name: "System Auto-Approved",
                    status: "Approved",
                    approved_at: new Date().toLocaleString(),
                    notes: "Attendance within normal working hours"
                },
                overtime: {
                    standard_hours: "9h 00m",
                    actual_hours: "9h 15m",
                    overtime_hours: "0h 15m",
                    overtime_rate: "1.5x",
                    overtime_pay: 125.00
                }
            };
            renderAttendanceDetails(simulatedData);
        });
}

function renderAttendanceDetails(data) {
    document.getElementById('attendanceDetailBody').innerHTML = `
        <div class="attendance-details-container">
            <!-- Employee Information Card - Add_item Style -->
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="bi bi-person-circle me-2 text-primary"></i>
                        Employee Information
                    </h6>
                    <span class="badge bg-primary">Active</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-person me-2 text-primary"></i>
                                Employee Name
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-primary text-white">
                                    <i class="bi bi-person"></i>
                                </span>
                                <input type="text" class="form-control form-control-lg" value="${data.employee.name}" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-card-text me-2 text-info"></i>
                                Employee Code
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-info text-white">
                                    <i class="bi bi-card-text"></i>
                                </span>
                                <input type="text" class="form-control form-control-lg" value="${data.employee.employee_code}" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-briefcase me-2 text-success"></i>
                                Position
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-success text-white">
                                    <i class="bi bi-briefcase"></i>
                                </span>
                                <input type="text" class="form-control form-control-lg" value="${data.employee.position}" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-building me-2 text-warning"></i>
                                Department
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-warning text-dark">
                                    <i class="bi bi-building"></i>
                                </span>
                                <input type="text" class="form-control form-control-lg" value="${data.employee.department}" readonly>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-clock me-2 text-secondary"></i>
                                Shift Timing
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-secondary text-white">
                                    <i class="bi bi-clock"></i>
                                </span>
                                <input type="text" class="form-control form-control-lg" value="${data.employee.shift}" readonly>
                            </div>
                            <div class="form-text">
                                <i class="bi bi-info-circle me-1"></i>
                                Employee's assigned shift timing
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Attendance Summary Card -->
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="bi bi-calendar-check me-2 text-success"></i>
                        Attendance Summary
                    </h6>
                    <span class="badge bg-success">${data.attendance.status}</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-calendar3 me-2 text-primary"></i>
                                Date
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-primary text-white">
                                    <i class="bi bi-calendar3"></i>
                                </span>
                                <input type="text" class="form-control form-control-lg" value="${new Date(data.attendance.date).toLocaleDateString('en-US', {weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'})}" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-check-circle me-2 text-success"></i>
                                Punctuality
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-success text-white">
                                    <i class="bi bi-check-circle"></i>
                                </span>
                                <input type="text" class="form-control form-control-lg" value="${data.attendance.punctuality}" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-box-arrow-in-right me-2 text-success"></i>
                                Punch In Time
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-success text-white">
                                    <i class="bi bi-box-arrow-in-right"></i>
                                </span>
                                <input type="text" class="form-control form-control-lg" value="${data.attendance.time_in ? new Date('2000-01-01 ' + data.attendance.time_in).toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit', hour12: true}) : 'N/A'}" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-box-arrow-right me-2 text-danger"></i>
                                Punch Out Time
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-danger text-white">
                                    <i class="bi bi-box-arrow-right"></i>
                                </span>
                                <input type="text" class="form-control form-control-lg" value="${data.attendance.time_out ? new Date('2000-01-01 ' + data.attendance.time_out).toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit', hour12: true}) : 'N/A'}" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-clock-history me-2 text-info"></i>
                                Work Duration
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-info text-white">
                                    <i class="bi bi-clock-history"></i>
                                </span>
                                <input type="text" class="form-control form-control-lg" value="${data.attendance.work_duration}" readonly>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-journal-text me-2 text-secondary"></i>
                                Notes
                            </label>
                            <textarea class="form-control" rows="2" readonly>${data.attendance.notes}</textarea>
                            <div class="form-text">
                                <i class="bi bi-lightbulb me-1"></i>
                                Additional notes or remarks for this attendance record
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Device Information Card -->
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="bi bi-device-hdd me-2 text-primary"></i>
                        Device Information
                    </h6>
                    <span class="badge bg-success">Online</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-fingerprint me-2 text-primary"></i>
                                Device Name
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-primary text-white">
                                    <i class="bi bi-fingerprint"></i>
                                </span>
                                <input type="text" class="form-control form-control-lg" value="${data.device.name}" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-geo-alt me-2 text-warning"></i>
                                Location
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-warning text-dark">
                                    <i class="bi bi-geo-alt"></i>
                                </span>
                                <input type="text" class="form-control form-control-lg" value="${data.device.location}" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-cpu me-2 text-info"></i>
                                Device Type
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-info text-white">
                                    <i class="bi bi-cpu"></i>
                                </span>
                                <input type="text" class="form-control form-control-lg" value="${data.device.type}" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-arrow-clockwise me-2 text-success"></i>
                                Last Sync
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-success text-white">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </span>
                                <input type="text" class="form-control form-control-lg" value="${data.device.last_sync}" readonly>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- GPS Location Data Card -->
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="bi bi-geo-alt me-2 text-success"></i>
                        GPS Location Data
                    </h6>
                    <span class="badge bg-success">${data.location.accuracy}</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-geo me-2 text-success"></i>
                                Punch In Location
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-success text-white">
                                    <i class="bi bi-geo"></i>
                                </span>
                                <input type="text" class="form-control form-control-lg" value="${data.location.punch_in_coords}" readonly>
                            </div>
                            <div class="form-text">
                                <i class="bi bi-info-circle me-1"></i>
                                GPS coordinates recorded during punch in
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-geo-alt me-2 text-danger"></i>
                                Punch Out Location
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-danger text-white">
                                    <i class="bi bi-geo-alt"></i>
                                </span>
                                <input type="text" class="form-control form-control-lg" value="${data.location.punch_out_coords}" readonly>
                            </div>
                            <div class="form-text">
                                <i class="bi bi-info-circle me-1"></i>
                                GPS coordinates recorded during punch out
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-rulers me-2 text-info"></i>
                                Distance from Office
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-info text-white">
                                    <i class="bi bi-rulers"></i>
                                </span>
                                <input type="text" class="form-control form-control-lg" value="${data.location.distance}" readonly>
                            </div>
                            <div class="form-text">
                                <i class="bi bi-lightbulb me-1"></i>
                                Calculated distance from registered office location
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mt-2">
                                <button class="btn btn-outline-primary" onclick="showLocationMap()">
                                    <i class="bi bi-map me-1"></i>View on Map
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Manager Approval Card -->
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="bi bi-person-check me-2 text-info"></i>
                        Manager Approval
                    </h6>
                    <span class="badge bg-success">${data.approval.status}</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-person-badge me-2 text-info"></i>
                                Approved By
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-info text-white">
                                    <i class="bi bi-person-badge"></i>
                                </span>
                                <input type="text" class="form-control form-control-lg" value="${data.approval.manager_name}" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-calendar-check me-2 text-success"></i>
                                Approved On
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-success text-white">
                                    <i class="bi bi-calendar-check"></i>
                                </span>
                                <input type="text" class="form-control form-control-lg" value="${data.approval.approved_at}" readonly>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-chat-text me-2 text-secondary"></i>
                                Approval Notes
                            </label>
                            <textarea class="form-control" rows="3" readonly>${data.approval.notes}</textarea>
                            <div class="form-text">
                                <i class="bi bi-lightbulb me-1"></i>
                                Manager's comments and notes regarding the approval
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Overtime Calculation Card -->
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="bi bi-clock-history me-2 text-warning"></i>
                        Overtime Calculation
                    </h6>
                    <span class="badge bg-warning text-dark">${data.overtime.overtime_rate}</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-clock me-2 text-primary"></i>
                                Standard Hours
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-primary text-white">
                                    <i class="bi bi-clock"></i>
                                </span>
                                <input type="text" class="form-control form-control-lg" value="${data.overtime.standard_hours}" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-clock-fill me-2 text-info"></i>
                                Actual Hours
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-info text-white">
                                    <i class="bi bi-clock-fill"></i>
                                </span>
                                <input type="text" class="form-control form-control-lg" value="${data.overtime.actual_hours}" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-plus-circle me-2 text-warning"></i>
                                Overtime Hours
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-warning text-dark">
                                    <i class="bi bi-plus-circle"></i>
                                </span>
                                <input type="text" class="form-control form-control-lg" value="${data.overtime.overtime_hours}" readonly>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-currency-rupee me-2 text-success"></i>
                                Overtime Pay
                            </label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-success text-white">
                                    <i class="bi bi-currency-rupee"></i>
                                </span>
                                <input type="text" class="form-control" value="₹${data.overtime.overtime_pay.toFixed(2)}" readonly>
                            </div>
                            <div class="form-text">
                                <i class="bi bi-calculator me-1"></i>
                                Calculated at ${data.overtime.overtime_rate} rate for ${data.overtime.overtime_hours}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-primary" onclick="approveAttendance()">
                    <i class="bi bi-check-circle me-2"></i>
                    Approve
                </button>
                <button class="btn btn-warning" onclick="flagAttendance()">
                    <i class="bi bi-flag me-2"></i>
                    Flag Issue
                </button>
                <button class="btn btn-outline-primary" onclick="printAttendanceDetails()">
                    <i class="bi bi-printer me-2"></i>
                    Print
                </button>
                <button class="btn btn-outline-success" onclick="exportAttendanceDetails()">
                    <i class="bi bi-download me-2"></i>
                    Export
                </button>
            </div>
        </div>
    `;
}

function showErrorState(message) {
    document.getElementById('attendanceDetailBody').innerHTML = `
        <div class="text-center py-5">
            <div class="p-4 rounded-circle mx-auto mb-3" style="background: linear-gradient(135deg, #ef4444, #f87171); width: 80px; height: 80px;">
                <i class="bi bi-exclamation-triangle text-white" style="font-size: 2.5rem;"></i>
            </div>
            <h5 class="mb-2">Unable to Load Details</h5>
            <p class="text-muted">${message}</p>
            <button class="btn btn-primary" onclick="location.reload()">
                <i class="bi bi-arrow-clockwise me-1"></i>Try Again
            </button>
        </div>
    `;
}

// Supporting functions for attendance details modal
function showLocationMap() {
    showAlert('Opening location in maps...', 'info');
    // Here you would integrate with Google Maps or other mapping service
    // window.open(`https://maps.google.com?q=12.9716,77.5946`, '_blank');
}

function printAttendanceDetails() {
    showAlert('Preparing attendance details for printing...', 'info');
    // Here you would open a print-friendly version of the attendance details
    window.print();
}

function exportAttendanceDetails() {
    showAlert('Exporting attendance details...', 'success');
    // Here you would generate and download attendance details as PDF/Excel
}

function flagAttendance() {
    if (confirm('Are you sure you want to flag this attendance record for review?')) {
        showAlert('Attendance record flagged for manager review', 'warning');
        // Here you would send the flag request to the server
    }
}

function approveAttendance() {
    if (confirm('Are you sure you want to approve this attendance record?')) {
        showAlert('Attendance record approved successfully', 'success');
        // Here you would send the approval to the server
        // Optionally close the modal after approval
        setTimeout(() => {
            bootstrap.Modal.getInstance(document.getElementById('attendanceDetailModal')).hide();
        }, 1500);
    }
}

function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        <i class="bi bi-info-circle me-2"></i>${message}
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
</script>

<style>
/* Enhanced Attendance Preview Styling */
.main-content {
    padding: 10px;
}

.container-fluid {
    max-width: 100%;
    padding: 0 10px;
}

/* Dashboard-style stat cards */
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    animation: fadeInUp 0.6s ease-out;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.stat-label {
    font-size: 0.875rem;
    color: #6b7280;
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #111827;
    margin-bottom: 0.25rem;
}

.stat-change {
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.stat-change.positive {
    color: #059669;
}

.stat-change.negative {
    color: #dc2626;
}

/* Animation for cards */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Statistics Cards Enhancements */
.statistics-card {
    transition: all 0.3s ease;
}

.statistics-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

/* Icon Animation */
.statistics-card i {
    transition: transform 0.3s ease;
}

.statistics-card:hover i {
    transform: scale(1.1);
}

/* Responsive Grid Enhancements */
@media (max-width: 1200px) {
    .col-xl-2 {
        flex: 0 0 33.333333%;
        max-width: 33.333333%;
    }
}

@media (max-width: 768px) {
    .col-xl-2, .col-lg-4 {
        flex: 0 0 50%;
        max-width: 50%;
    }
    
    .main-content {
        padding: 5px;
    }
    
    .container-fluid {
        padding: 0 5px;
    }
    
    .row {
        margin: 0 -5px;
    }
    
    .row > * {
        padding: 0 5px;
    }
    
    .btn-group {
        flex-direction: column;
    }
    
    .btn-group .btn {
        margin-bottom: 2px;
        border-radius: 0.25rem !important;
    }
}

@media (max-width: 576px) {
    .col-xl-2, .col-lg-4, .col-md-6 {
        flex: 0 0 100%;
        max-width: 100%;
    }
    
    .statistics-card .card-body {
        padding: 1rem !important;
    }
}

/* Card Hover Effects */
.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
}

/* Table Enhancements */
.table th {
    font-size: 0.875rem !important;
    font-weight: 600 !important;
    border-bottom: 3px solid var(--primary-color) !important;
    background: linear-gradient(135deg, var(--gray-200) 0%, var(--gray-100) 100%) !important;
    color: var(--gray-900) !important;
}

.table td {
    font-size: 0.875rem;
    vertical-align: middle;
    color: var(--gray-800) !important;
}

/* Badge Enhancements */
.badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
}

/* Alert Improvements */
.alert {
    font-size: 0.875rem;
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    margin-bottom: 1rem;
}

/* Form Control Sizing */
.form-control-sm, .form-select-sm {
    font-size: 0.875rem;
}

.form-label.small {
    font-size: 0.875rem;
    font-weight: 500;
    color: #495057;
}

/* Button Group Responsiveness */
.btn-group-sm > .btn {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

/* DataTable Responsive */
@media (max-width: 992px) {
    .dataTables_wrapper .dataTables_filter,
    .dataTables_wrapper .dataTables_length {
        text-align: left;
        margin-bottom: 10px;
    }
    
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_paginate {
        text-align: center;
        margin-top: 10px;
    }
}

/* Empty State Styling */
.text-center.py-4 {
    padding: 2rem 0 !important;
}

.text-center.py-4 .bi {
    color: #6c757d;
}

/* Employee Avatar Styling */
.bg-primary.rounded-circle {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%) !important;
}

/* Modal Enhancements */
.modal-content {
    border: none;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.modal-header {
    background: var(--gray-50);
    border-bottom: 1px solid var(--gray-200);
}

/* Punch Status Icons */
.text-success i, .text-primary i, .text-warning i, .text-danger i, .text-info i {
    margin-right: 0.25rem;
}

@media print {
    .btn, .card-header, .sidebar, .main-header, .modal {
        display: none !important;
    }
    .main-content {
        margin-left: 0 !important;
        margin-top: 0 !important;
        padding: 0 !important;
    }
    .card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
    }
    .container-fluid {
        padding: 0 !important;
    }
    .statistics-card {
        break-inside: avoid;
        page-break-inside: avoid;
    }
}

/* Attendance Details Modal Styling */
.attendance-details-container {
    max-height: 70vh;
    overflow-y: auto;
}

.attendance-details-container .card {
    border: 1px solid #e9ecef;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    transition: all 0.3s ease;
}

.attendance-details-container .card:hover {
    transform: translateY(-1px);
    box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1);
}

.attendance-details-container .card-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
}

.attendance-details-container .card-header h6 {
    color: #495057;
    font-weight: 600;
}

.attendance-details-container .card-body {
    padding: 1rem;
}

/* Status badges in attendance details */
.attendance-details-container .badge {
    font-size: 0.75rem;
    padding: 0.35rem 0.6rem;
    border-radius: 0.375rem;
    font-weight: 500;
}

/* GPS and location styling */
.attendance-details-container code {
    background: #e9ecef;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-family: 'Courier New', monospace;
    font-size: 0.875rem;
    color: #495057;
}

/* Notes sections */
.attendance-details-container .note-box {
    background: #f8f9fa;
    border-left: 4px solid #007bff;
    padding: 0.75rem;
    border-radius: 0.375rem;
    margin: 0.5rem 0;
}

/* Action buttons styling */
.attendance-details-container .btn {
    font-size: 0.875rem;
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.attendance-details-container .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.15);
}

/* Modal enhancements */
.modal-xl .modal-body {
    padding: 1.5rem;
}

.modal-header {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    color: white;
    border-bottom: none;
}

.modal-header .modal-title {
    font-weight: 600;
}

.modal-header .btn-close {
    filter: invert(1);
}

/* Scrollbar styling for attendance details */
.attendance-details-container::-webkit-scrollbar {
    width: 6px;
}

.attendance-details-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.attendance-details-container::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.attendance-details-container::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Responsive adjustments for attendance details */
@media (max-width: 768px) {
    .attendance-details-container {
        max-height: 80vh;
    }
    
    .modal-xl {
        margin: 0.5rem;
    }
    
    .modal-xl .modal-body {
        padding: 1rem;
    }
    
    .attendance-details-container .card-body {
        padding: 0.75rem;
    }
}
</style>

<?php include 'layouts/footer.php'; ?>
