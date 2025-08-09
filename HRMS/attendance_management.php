<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

include '../db.php';
$page_title = 'Attendance Management';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'mark_attendance':
            $employee_id = intval($_POST['employee_id']);
            $attendance_date = mysqli_real_escape_string($conn, $_POST['attendance_date']);
            $clock_in = mysqli_real_escape_string($conn, $_POST['check_in']); // Using check_in from form but storing as clock_in
            $clock_out = $_POST['check_out'] ?? null; // Using check_out from form but storing as clock_out
            $status = mysqli_real_escape_string($conn, $_POST['status']);
            
            // Check if attendance already exists for this date
            $existing = mysqli_query($conn, "SELECT id FROM hr_attendance WHERE employee_id = $employee_id AND DATE(date) = '$attendance_date'");
            
            if ($existing && mysqli_num_rows($existing) > 0) {
                // Update existing record
                $clock_out_sql = $clock_out ? ", clock_out = '$clock_out'" : "";
                $query = "UPDATE hr_attendance SET clock_in = '$clock_in', status = '$status' $clock_out_sql WHERE employee_id = $employee_id AND DATE(date) = '$attendance_date'";
            } else {
                // Insert new record
                $clock_out_sql = $clock_out ? "'$clock_out'" : "NULL";
                $query = "INSERT INTO hr_attendance (employee_id, date, clock_in, clock_out, status) VALUES ($employee_id, '$attendance_date', '$clock_in', $clock_out_sql, '$status')";
            }
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Attendance marked successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;
    }
}

// Get today's attendance summary
$today = date('Y-m-d');
$todayStats = mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_present,
        COUNT(CASE WHEN clock_out IS NULL THEN 1 END) as still_in_office
    FROM hr_attendance 
    WHERE DATE(date) = '$today' AND status = 'present'
");

$present_today = 0;
$still_in_office = 0;
if ($todayStats && $row = mysqli_fetch_assoc($todayStats)) {
    $present_today = $row['total_present'];
    $still_in_office = $row['still_in_office'];
}

$total_employees = 0;
$empCount = mysqli_query($conn, "SELECT COUNT(*) as count FROM hr_employees WHERE status = 'active'");
if ($empCount && $row = mysqli_fetch_assoc($empCount)) {
    $total_employees = $row['count'];
}

$absent_today = $total_employees - $present_today;

// Get attendance records with filters
$date_filter = $_GET['date'] ?? $today;
$employee_filter = $_GET['employee'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where = "WHERE DATE(a.date) = '$date_filter'";
if ($employee_filter) {
    $where .= " AND (e.first_name LIKE '%$employee_filter%' OR e.last_name LIKE '%$employee_filter%')";
}
if ($status_filter) {
    $where .= " AND a.status = '$status_filter'";
}

$attendance_records = mysqli_query($conn, "
    SELECT 
        a.*,
        CONCAT(e.first_name, ' ', e.last_name) as employee_name,
        e.employee_id as emp_id,
        d.department_name
    FROM hr_attendance a
    JOIN hr_employees e ON a.employee_id = e.id
    LEFT JOIN hr_departments d ON e.department_id = d.id
    $where
    ORDER BY a.clock_in DESC
");

// Get employees for dropdown
$employees = mysqli_query($conn, "SELECT id, first_name, last_name, employee_id FROM hr_employees WHERE status = 'active' ORDER BY first_name");

include '../layouts/header.php';
include '../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">⏰ Attendance Management</h1>
                <p class="text-muted">Track and manage employee attendance</p>
            </div>
            <div>
                <a href="index.php" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-left"></i> Back to HRMS
                </a>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#markAttendanceModal">
                    <i class="bi bi-clock"></i> Mark Attendance
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-people fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $total_employees ?></h3>
                        <small class="opacity-75">Total Employees</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-check-circle fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $present_today ?></h3>
                        <small class="opacity-75">Present Today</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-x-circle fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $absent_today ?></h3>
                        <small class="opacity-75">Absent Today</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-building fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $still_in_office ?></h3>
                        <small class="opacity-75">Still in Office</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Date</label>
                        <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date_filter) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Employee</label>
                        <input type="text" name="employee" class="form-control" placeholder="Employee name..." value="<?= htmlspecialchars($employee_filter) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="present" <?= $status_filter === 'present' ? 'selected' : '' ?>>Present</option>
                            <option value="absent" <?= $status_filter === 'absent' ? 'selected' : '' ?>>Absent</option>
                            <option value="late" <?= $status_filter === 'late' ? 'selected' : '' ?>>Late</option>
                            <option value="half_day" <?= $status_filter === 'half_day' ? 'selected' : '' ?>>Half Day</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="bi bi-search"></i> Filter
                        </button>
                        <a href="?" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Attendance Records -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0 text-dark">
                    <i class="bi bi-table me-2"></i>Attendance Records - <?= date('M d, Y', strtotime($date_filter)) ?>
                    <span class="badge bg-primary ms-2"><?= $attendance_records ? mysqli_num_rows($attendance_records) : 0 ?> records</span>
                </h6>
            </div>
            <div class="card-body p-0">
                <?php if ($attendance_records && mysqli_num_rows($attendance_records) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Employee</th>
                                    <th>Department</th>
                                    <th>Check In</th>
                                    <th>Check Out</th>
                                    <th>Total Hours</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($record = mysqli_fetch_assoc($attendance_records)): ?>
                                    <?php
                                    $total_hours = '';
                                    if ($record['clock_in'] && $record['clock_out']) {
                                        $clock_in = new DateTime($record['clock_in']);
                                        $clock_out = new DateTime($record['clock_out']);
                                        $diff = $clock_in->diff($clock_out);
                                        $total_hours = $diff->format('%h:%I');
                                    }
                                    ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong class="text-primary"><?= htmlspecialchars($record['employee_name']) ?></strong>
                                                <br><small class="text-muted">ID: <?= htmlspecialchars($record['emp_id']) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?= htmlspecialchars($record['department_name'] ?? 'N/A') ?></span>
                                        </td>
                                        <td>
                                            <?= $record['clock_in'] ? date('H:i', strtotime($record['clock_in'])) : '-' ?>
                                        </td>
                                        <td>
                                            <?= $record['clock_out'] ? date('H:i', strtotime($record['clock_out'])) : 
                                                ($record['status'] == 'present' ? '<span class="text-warning">Still working</span>' : '-') ?>
                                        </td>
                                        <td>
                                            <?= $total_hours ? '<span class="badge bg-secondary">' . $total_hours . '</span>' : '-' ?>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = match($record['status']) {
                                                'present' => 'bg-success',
                                                'absent' => 'bg-danger',
                                                'late' => 'bg-warning',
                                                'half_day' => 'bg-info',
                                                default => 'bg-secondary'
                                            };
                                            ?>
                                            <span class="badge <?= $statusClass ?>"><?= ucfirst(str_replace('_', ' ', $record['status'])) ?></span>
                                        </td>
                                        <td>
                                            <button class="btn btn-outline-primary btn-sm" onclick="editAttendance(<?= $record['id'] ?>)" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-calendar-x text-muted" style="font-size: 3rem;"></i>
                        <h5 class="text-muted mt-3">No attendance records found</h5>
                        <p class="text-muted">No attendance records for selected date and filters</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#markAttendanceModal">
                            <i class="bi bi-clock me-1"></i>Mark Attendance
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Mark Attendance Modal -->
<div class="modal fade" id="markAttendanceModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-clock text-primary me-2"></i>Mark Attendance
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="markAttendanceForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Employee *</label>
                            <select name="employee_id" class="form-select" required>
                                <option value="">Select Employee</option>
                                <?php if ($employees): while ($emp = mysqli_fetch_assoc($employees)): ?>
                                    <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?> - <?= $emp['employee_id'] ?></option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date *</label>
                            <input type="date" name="attendance_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status *</label>
                            <select name="status" class="form-select" required>
                                <option value="present">Present</option>
                                <option value="absent">Absent</option>
                                <option value="late">Late</option>
                                <option value="half_day">Half Day</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Check In Time *</label>
                            <input type="time" name="check_in" class="form-control" value="<?= date('H:i') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Check Out Time</label>
                            <input type="time" name="check_out" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Mark Attendance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Form submission
document.getElementById('markAttendanceForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'mark_attendance');
    
    fetch('', {
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
        console.error('Error:', error);
        alert('An error occurred while marking attendance');
    });
});

function editAttendance(id) {
    // Functionality to edit attendance record
    alert('Edit attendance functionality would be implemented here for record ID: ' + id);
}
</script>

<style>
.stats-card {
    transition: transform 0.2s;
}
.stats-card:hover {
    transform: translateY(-2px);
}
</style>

<?php include '../layouts/footer.php'; ?>
