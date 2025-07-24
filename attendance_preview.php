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
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Attendance Details</h5>
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
    // This would typically fetch detailed information via AJAX
    // For now, showing a placeholder
    document.getElementById('attendanceDetailBody').innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading attendance details...</p>
        </div>
    `;
    
    new bootstrap.Modal(document.getElementById('attendanceDetailModal')).show();
    
    // Simulate loading (replace with actual AJAX call)
    setTimeout(() => {
        document.getElementById('attendanceDetailBody').innerHTML = `
            <p>Detailed attendance information would be displayed here.</p>
            <p>This feature can be enhanced to show:</p>
            <ul>
                <li>GPS location data (if available)</li>
                <li>Device information</li>
                <li>Manager approvals</li>
                <li>Additional notes</li>
                <li>Overtime calculations</li>
            </ul>
        `;
    }, 1000);
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
</style>

<?php include 'layouts/footer.php'; ?>
