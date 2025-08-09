<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

include '../db.php';
$page_title = 'Employee Attendance - HRMS';

// Get employee ID from URL parameter
$employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : null;

if ($employee_id) {
    // Get employee details
    $employee_query = "
        SELECT e.*, d.department_name 
        FROM hr_employees e 
        LEFT JOIN hr_departments d ON e.department_id = d.id 
        WHERE e.id = $employee_id
    ";
    $employee_result = mysqli_query($conn, $employee_query);
    $employee = mysqli_fetch_assoc($employee_result);
    
    if (!$employee) {
        header("Location: employee_directory.php");
        exit;
    }
}

// Get current month and year
$current_month = $_GET['month'] ?? date('Y-m');
$month_name = date('F Y', strtotime($current_month . '-01'));

// Get attendance records for the selected month
$attendance_query = "
    SELECT * FROM hr_attendance 
    WHERE employee_id = $employee_id 
    AND DATE_FORMAT(attendance_date, '%Y-%m') = '$current_month'
    ORDER BY attendance_date DESC
";
$attendance_result = mysqli_query($conn, $attendance_query);
$attendance_records = [];
if ($attendance_result) {
    while ($row = mysqli_fetch_assoc($attendance_result)) {
        $attendance_records[] = $row;
    }
}

// Get attendance statistics
$stats_query = "
    SELECT 
        COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
        COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days,
        COUNT(CASE WHEN status = 'late' THEN 1 END) as late_days,
        COUNT(CASE WHEN status = 'half_day' THEN 1 END) as half_days,
        COUNT(*) as total_days
    FROM hr_attendance 
    WHERE employee_id = $employee_id 
    AND DATE_FORMAT(attendance_date, '%Y-%m') = '$current_month'
";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result) ?: [
    'present_days' => 0, 'absent_days' => 0, 'late_days' => 0, 
    'half_days' => 0, 'total_days' => 0
];

// Calculate attendance percentage
$attendance_percentage = $stats['total_days'] > 0 ? 
    round(($stats['present_days'] + ($stats['half_days'] * 0.5)) / $stats['total_days'] * 100, 1) : 0;

include '../layouts/header.php';
include '../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="h4 mb-1 fw-bold text-primary">📅 Employee Attendance</h1>
                    <?php if ($employee): ?>
                        <p class="text-muted small mb-0">
                            Attendance records for <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?>
                            <span class="badge bg-light text-dark ms-2"><?= $month_name ?></span>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <input type="month" class="form-control form-control-sm" id="monthSelector" value="<?= $current_month ?>" style="width: 150px;">
                    <?php if ($employee_id): ?>
                        <a href="employee_profile.php?id=<?= $employee_id ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left"></i> Back to Profile
                        </a>
                    <?php endif; ?>
                    <a href="employee_directory.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-people"></i> Directory
                    </a>
                </div>
            </div>

            <?php if ($employee): ?>
                <!-- Employee Info Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-3">
                        <div class="row align-items-center">
                            <div class="col-md-2">
                                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" 
                                     style="width: 60px; height: 60px;">
                                    <i class="bi bi-person-fill fs-3"></i>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h5 class="mb-1"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></h5>
                                <p class="text-muted mb-0">
                                    <i class="bi bi-card-text me-1"></i><?= htmlspecialchars($employee['employee_id']) ?> | 
                                    <i class="bi bi-briefcase me-1"></i><?= htmlspecialchars($employee['position']) ?> | 
                                    <i class="bi bi-building me-1"></i><?= htmlspecialchars($employee['department_name'] ?? 'N/A') ?>
                                </p>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="bg-success bg-opacity-10 p-2 rounded text-center">
                                            <h6 class="text-success mb-0"><?= $attendance_percentage ?>%</h6>
                                            <small class="text-muted">Attendance</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="bg-info bg-opacity-10 p-2 rounded text-center">
                                            <h6 class="text-info mb-0"><?= $stats['total_days'] ?></h6>
                                            <small class="text-muted">Total Days</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-lg-3 col-md-6">
                        <div class="card border-0 h-100" style="background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);">
                            <div class="card-body text-center p-3">
                                <i class="bi bi-check-circle-fill fs-2 text-success mb-2"></i>
                                <h3 class="mb-1 text-success"><?= $stats['present_days'] ?></h3>
                                <small class="text-muted">Present Days</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="card border-0 h-100" style="background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);">
                            <div class="card-body text-center p-3">
                                <i class="bi bi-x-circle-fill fs-2 text-danger mb-2"></i>
                                <h3 class="mb-1 text-danger"><?= $stats['absent_days'] ?></h3>
                                <small class="text-muted">Absent Days</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="card border-0 h-100" style="background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);">
                            <div class="card-body text-center p-3">
                                <i class="bi bi-clock-fill fs-2 text-warning mb-2"></i>
                                <h3 class="mb-1 text-warning"><?= $stats['late_days'] ?></h3>
                                <small class="text-muted">Late Days</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="card border-0 h-100" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
                            <div class="card-body text-center p-3">
                                <i class="bi bi-clock-history fs-2 text-info mb-2"></i>
                                <h3 class="mb-1 text-info"><?= $stats['half_days'] ?></h3>
                                <small class="text-muted">Half Days</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attendance Records -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="bi bi-calendar-check me-2"></i>Attendance Records - <?= $month_name ?>
                        </h6>
                        <div class="d-flex gap-2">
                            <button class="btn btn-success btn-sm" onclick="markAttendance('present')">
                                <i class="bi bi-check-circle me-1"></i>Mark Present
                            </button>
                            <button class="btn btn-warning btn-sm" onclick="markAttendance('late')">
                                <i class="bi bi-clock me-1"></i>Mark Late
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="markAttendance('absent')">
                                <i class="bi bi-x-circle me-1"></i>Mark Absent
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($attendance_records)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Day</th>
                                            <th>Status</th>
                                            <th>Check In</th>
                                            <th>Check Out</th>
                                            <th>Hours Worked</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($attendance_records as $record): ?>
                                            <tr>
                                                <td class="fw-medium">
                                                    <?= date('M j, Y', strtotime($record['attendance_date'])) ?>
                                                </td>
                                                <td class="text-muted">
                                                    <?= date('l', strtotime($record['attendance_date'])) ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status_class = 'secondary';
                                                    if ($record['status'] === 'present') $status_class = 'success';
                                                    elseif ($record['status'] === 'absent') $status_class = 'danger';
                                                    elseif ($record['status'] === 'late') $status_class = 'warning';
                                                    elseif ($record['status'] === 'half_day') $status_class = 'info';
                                                    ?>
                                                    <span class="badge bg-<?= $status_class ?>">
                                                        <?= ucfirst(str_replace('_', ' ', $record['status'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?= $record['check_in_time'] ? date('g:i A', strtotime($record['check_in_time'])) : '-' ?>
                                                </td>
                                                <td>
                                                    <?= $record['check_out_time'] ? date('g:i A', strtotime($record['check_out_time'])) : '-' ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    if ($record['check_in_time'] && $record['check_out_time']) {
                                                        $hours = (strtotime($record['check_out_time']) - strtotime($record['check_in_time'])) / 3600;
                                                        echo number_format($hours, 1) . 'h';
                                                    } else {
                                                        echo '-';
                                                    }
                                                    ?>
                                                </td>
                                                <td class="text-muted">
                                                    <?= htmlspecialchars($record['notes'] ?? '-') ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-calendar-x text-muted" style="font-size: 4rem;"></i>
                                <h5 class="text-muted mt-3">No Attendance Records</h5>
                                <p class="text-muted">No attendance records found for <?= $month_name ?></p>
                                <button class="btn btn-primary" onclick="markAttendance('present')">
                                    <i class="bi bi-plus-circle me-2"></i>Add First Record
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- No Employee Selected -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-person-x text-muted" style="font-size: 4rem;"></i>
                        <h5 class="text-muted mt-3">No Employee Selected</h5>
                        <p class="text-muted">Please select an employee to view attendance records</p>
                        <a href="employee_directory.php" class="btn btn-primary">
                            <i class="bi bi-people me-2"></i>Go to Employee Directory
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Month selector functionality
document.getElementById('monthSelector').addEventListener('change', function() {
    const selectedMonth = this.value;
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('month', selectedMonth);
    window.location.href = currentUrl.toString();
});

// Mark attendance functionality (placeholder)
function markAttendance(status) {
    <?php if ($employee_id): ?>
        alert(`This would mark attendance as "${status}" for <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?>.\n\nReal implementation would:\n- Add attendance record\n- Update database\n- Refresh the page`);
    <?php else: ?>
        alert('Please select an employee first');
    <?php endif; ?>
}

// Print functionality
function printAttendance() {
    window.print();
}

document.addEventListener('DOMContentLoaded', function() {
    // Add any initialization code here
});
</script>

<style>
.table th {
    font-weight: 600;
    color: #495057;
    font-size: 0.9rem;
}

.table td {
    vertical-align: middle;
    font-size: 0.9rem;
}

.badge {
    font-size: 0.8rem;
}

@media print {
    .btn, .form-control {
        display: none !important;
    }
    
    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
}
</style>

<?php include '../layouts/footer.php'; ?>
