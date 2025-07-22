<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Mark Attendance';

include '../../layouts/header.php';
include '../../layouts/sidebar.php';

// Get all employees
$employees = $conn->query("SELECT * FROM employees ORDER BY employee_name ASC");
$today = date('Y-m-d');
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Mark Attendance</h1>
            <p class="text-muted">Record daily attendance for employees - <?= date('F j, Y') ?></p>
        </div>
        <div>
            <a href="../../attendance-calendar.php" class="btn btn-outline-primary">
                <i class="bi bi-calendar3"></i> View Calendar
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Today's Attendance</h5>
                </div>
                <div class="card-body">
                    <form action="../../save_attendance.php" method="POST">
                        <input type="hidden" name="attendance_date" value="<?= $today ?>">
                        
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Status</th>
                                        <th>Time In</th>
                                        <th>Time Out</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($employees && mysqli_num_rows($employees) > 0): ?>
                                        <?php while ($employee = $employees->fetch_assoc()): ?>
                                            <?php
                                            // Check if attendance already marked for today
                                            $attendanceQuery = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?");
                                            $attendanceQuery->bind_param("is", $employee['id'], $today);
                                            $attendanceQuery->execute();
                                            $existingAttendance = $attendanceQuery->get_result()->fetch_assoc();
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if (!empty($employee['photo']) && file_exists('../../' . $employee['photo'])): ?>
                                                            <img src="../../<?= htmlspecialchars($employee['photo']) ?>" 
                                                                 class="rounded-circle me-2" 
                                                                 style="width: 32px; height: 32px; object-fit: cover;">
                                                        <?php else: ?>
                                                            <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center me-2" 
                                                                 style="width: 32px; height: 32px;">
                                                                <i class="bi bi-person text-white small"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <strong><?= htmlspecialchars($employee['employee_name']) ?></strong>
                                                            <br><small class="text-muted"><?= htmlspecialchars($employee['employee_code']) ?></small>
                                                        </div>
                                                    </div>
                                                    <input type="hidden" name="employee_id[]" value="<?= $employee['id'] ?>">
                                                </td>
                                                <td>
                                                    <select name="status[]" class="form-select form-select-sm" style="width: 120px;">
                                                        <option value="Present" <?= ($existingAttendance['status'] ?? '') == 'Present' ? 'selected' : '' ?>>Present</option>
                                                        <option value="Absent" <?= ($existingAttendance['status'] ?? '') == 'Absent' ? 'selected' : '' ?>>Absent</option>
                                                        <option value="Late" <?= ($existingAttendance['status'] ?? '') == 'Late' ? 'selected' : '' ?>>Late</option>
                                                        <option value="Half Day" <?= ($existingAttendance['status'] ?? '') == 'Half Day' ? 'selected' : '' ?>>Half Day</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="time" name="time_in[]" class="form-control form-control-sm" 
                                                           style="width: 120px;" 
                                                           value="<?= $existingAttendance['time_in'] ?? '09:00' ?>">
                                                </td>
                                                <td>
                                                    <input type="time" name="time_out[]" class="form-control form-control-sm" 
                                                           style="width: 120px;" 
                                                           value="<?= $existingAttendance['time_out'] ?? '18:00' ?>">
                                                </td>
                                                <td>
                                                    <input type="text" name="notes[]" class="form-control form-control-sm" 
                                                           placeholder="Optional notes..." 
                                                           value="<?= htmlspecialchars($existingAttendance['notes'] ?? '') ?>">
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">
                                                No employees found. <a href="../employees/employees.php">Add employees</a> first.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($employees && mysqli_num_rows($employees) > 0): ?>
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Save Attendance
                                </button>
                                <button type="button" class="btn btn-success" onclick="markAllPresent()">
                                    <i class="bi bi-check-all"></i> Mark All Present
                                </button>
                                <a href="../employees/employees.php" class="btn btn-secondary">
                                    Manage Employees
                                </a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Today's Summary</h6>
                </div>
                <div class="card-body">
                    <?php
                    $totalEmployees = 0;
                    $presentCount = 0;
                    $absentCount = 0;
                    $lateCount = 0;

                    $result = $conn->query("SELECT COUNT(*) as total FROM employees");
                    if ($result && $row = $result->fetch_assoc()) {
                        $totalEmployees = $row['total'] ?? 0;
                    }

                    $result = $conn->query("SELECT status, COUNT(*) as count FROM attendance WHERE attendance_date = '$today' GROUP BY status");
                    if ($result) {
                        while ($row = $result->fetch_assoc()) {
                            switch ($row['status']) {
                                case 'Present': $presentCount = $row['count']; break;
                                case 'Absent': $absentCount = $row['count']; break;
                                case 'Late': $lateCount = $row['count']; break;
                            }
                        }
                    }
                    ?>
                    
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="border rounded p-2 mb-2">
                                <h4 class="text-success mb-0"><?= $presentCount ?></h4>
                                <small class="text-muted">Present</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-2 mb-2">
                                <h4 class="text-danger mb-0"><?= $absentCount ?></h4>
                                <small class="text-muted">Absent</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-2 mb-2">
                                <h4 class="text-warning mb-0"><?= $lateCount ?></h4>
                                <small class="text-muted">Late</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-2 mb-2">
                                <h4 class="text-primary mb-0"><?= $totalEmployees ?></h4>
                                <small class="text-muted">Total</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="../../attendance-calendar.php" class="btn btn-outline-primary">View Calendar</a>
                        <a href="../../attendance_report.php" class="btn btn-outline-success">Generate Report</a>
                        <a href="../employees/employees.php" class="btn btn-outline-secondary">Manage Employees</a>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">Legend</h6>
                </div>
                <div class="card-body">
                    <div class="small">
                        <div class="mb-1"><span class="badge bg-success">Present</span> - Employee is present for full day</div>
                        <div class="mb-1"><span class="badge bg-danger">Absent</span> - Employee is not present</div>
                        <div class="mb-1"><span class="badge bg-warning">Late</span> - Employee came late</div>
                        <div class="mb-1"><span class="badge bg-info">Half Day</span> - Employee worked half day</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function markAllPresent() {
    document.querySelectorAll('select[name="status[]"]').forEach(select => {
        select.value = 'Present';
    });
    
    document.querySelectorAll('input[name="time_in[]"]').forEach(input => {
        if (!input.value) input.value = '09:00';
    });
    
    document.querySelectorAll('input[name="time_out[]"]').forEach(input => {
        if (!input.value) input.value = '18:00';
    });
}

// Auto-disable time inputs for absent employees
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('select[name="status[]"]').forEach(select => {
        select.addEventListener('change', function() {
            const row = this.closest('tr');
            const timeIn = row.querySelector('input[name="time_in[]"]');
            const timeOut = row.querySelector('input[name="time_out[]"]');
            
            if (this.value === 'Absent') {
                timeIn.disabled = true;
                timeOut.disabled = true;
                timeIn.value = '';
                timeOut.value = '';
            } else {
                timeIn.disabled = false;
                timeOut.disabled = false;
                if (!timeIn.value) timeIn.value = '09:00';
                if (!timeOut.value) timeOut.value = '18:00';
            }
        });
        
        // Trigger change event on page load
        select.dispatchEvent(new Event('change'));
    });
});
</script>

<?php include '../../layouts/footer.php'; ?>