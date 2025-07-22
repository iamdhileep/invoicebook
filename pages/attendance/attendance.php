<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Attendance Management';

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Attendance Management</h1>
            <p class="text-muted">Track employee attendance and working hours</p>
        </div>
        <div>
            <a href="../../attendance-calendar.php" class="btn btn-outline-primary me-2">
                <i class="bi bi-calendar3"></i> Attendance Calendar
            </a>
            <a href="../../attendance_preview.php" class="btn btn-outline-info">
                <i class="bi bi-eye"></i> View Reports
            </a>
        </div>
    </div>

    <!-- Today's Summary -->
    <?php
    $today = date('Y-m-d');
    $totalEmployees = 0;
    $presentCount = 0;
    $absentCount = 0;
    $lateCount = 0;

    // Get total employees
    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM employees");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $totalEmployees = $row['total'] ?? 0;
    }

    // Get today's attendance stats
    $result = mysqli_query($conn, "SELECT status, COUNT(*) as count FROM attendance WHERE DATE(attendance_date) = '$today' GROUP BY status");
    $attendanceStats = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $attendanceStats[$row['status']] = $row['count'];
        }
    }
    
    $presentCount = ($attendanceStats['Present'] ?? 0) + ($attendanceStats['Late'] ?? 0);
    $absentCount = $attendanceStats['Absent'] ?? 0;
    $lateCount = $attendanceStats['Late'] ?? 0;
    ?>

    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Employees</h6>
                            <h2 class="mb-0"><?= $totalEmployees ?></h2>
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
                            <h2 class="mb-0"><?= $presentCount ?></h2>
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
                            <h2 class="mb-0"><?= $absentCount ?></h2>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-person-x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Late Arrivals</h6>
                            <h2 class="mb-0"><?= $lateCount ?></h2>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-clock"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <!-- Mark Attendance Form -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-person-check me-2"></i>Mark Attendance</h5>
                </div>
                <div class="card-body">
                    <form action="../../save_attendance.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Date *</label>
                            <input type="date" name="attendance_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Employee *</label>
                            <select name="employee_id" class="form-select" required>
                                <option value="">-- Select Employee --</option>
                                <?php
                                $employees = mysqli_query($conn, "SELECT employee_id, name, code FROM employees ORDER BY name ASC");
                                while ($emp = mysqli_fetch_assoc($employees)) {
                                    echo "<option value='{$emp['employee_id']}'>{$emp['name']} ({$emp['code']})</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status *</label>
                            <select name="status" class="form-select" required>
                                <option value="">-- Select Status --</option>
                                <option value="Present">Present</option>
                                <option value="Absent">Absent</option>
                                <option value="Late">Late</option>
                                <option value="Half Day">Half Day</option>
                                <option value="Holiday">Holiday</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Check-in Time</label>
                            <input type="time" name="check_in_time" class="form-control" value="<?= date('H:i') ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Check-out Time</label>
                            <input type="time" name="check_out_time" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Optional notes about attendance"></textarea>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Mark Attendance
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="markAllPresent">
                                <i class="bi bi-people-fill"></i> Mark All Present
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-lightning me-2"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="../../attendance-calendar.php" class="btn btn-outline-primary">
                            <i class="bi bi-calendar3"></i> View Calendar
                        </a>
                        <a href="../../attendance_preview.php" class="btn btn-outline-info">
                            <i class="bi bi-graph-up"></i> Attendance Report
                        </a>
                        <button class="btn btn-outline-success" onclick="exportAttendance()">
                            <i class="bi bi-download"></i> Export Data
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <!-- Today's Attendance -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Today's Attendance (<?= date('M d, Y') ?>)</h5>
                    <div>
                        <input type="text" id="attendanceSearch" class="form-control form-control-sm" placeholder="Search employees..." style="width: 200px;">
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="attendanceTable" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Code</th>
                                    <th>Status</th>
                                    <th>Check-in</th>
                                    <th>Check-out</th>
                                    <th>Hours</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Get all employees with their attendance for today
                                $query = "SELECT e.employee_id, e.name, e.code, 
                                         a.status, a.check_in_time, a.check_out_time, a.notes, a.attendance_id
                                         FROM employees e
                                         LEFT JOIN attendance a ON e.employee_id = a.employee_id 
                                         AND DATE(a.attendance_date) = '$today'
                                         ORDER BY e.name ASC";
                                
                                $result = mysqli_query($conn, $query);
                                if ($result) {
                                    while ($row = mysqli_fetch_assoc($result)) {
                                        $status = $row['status'] ?? 'Not Marked';
                                        $statusClass = '';
                                        switch ($status) {
                                            case 'Present': $statusClass = 'bg-success'; break;
                                            case 'Late': $statusClass = 'bg-warning'; break;
                                            case 'Absent': $statusClass = 'bg-danger'; break;
                                            case 'Half Day': $statusClass = 'bg-info'; break;
                                            case 'Holiday': $statusClass = 'bg-secondary'; break;
                                            default: $statusClass = 'bg-light text-dark';
                                        }

                                        $checkIn = $row['check_in_time'] ?? '--';
                                        $checkOut = $row['check_out_time'] ?? '--';
                                        
                                        // Calculate hours worked
                                        $hoursWorked = '--';
                                        if ($row['check_in_time'] && $row['check_out_time']) {
                                            $start = new DateTime($row['check_in_time']);
                                            $end = new DateTime($row['check_out_time']);
                                            $diff = $start->diff($end);
                                            $hoursWorked = $diff->format('%h:%I');
                                        }

                                        echo "<tr>";
                                        echo "<td><strong>" . htmlspecialchars($row['name']) . "</strong></td>";
                                        echo "<td><span class='badge bg-secondary'>" . htmlspecialchars($row['code']) . "</span></td>";
                                        echo "<td><span class='badge {$statusClass}'>{$status}</span></td>";
                                        echo "<td>{$checkIn}</td>";
                                        echo "<td>{$checkOut}</td>";
                                        echo "<td><strong>{$hoursWorked}</strong></td>";
                                        echo "<td>";
                                        
                                        if ($row['attendance_id']) {
                                            echo "<div class='btn-group btn-group-sm'>";
                                            echo "<button class='btn btn-outline-primary edit-attendance' data-id='{$row['attendance_id']}' data-bs-toggle='tooltip' title='Edit'>";
                                            echo "<i class='bi bi-pencil'></i>";
                                            echo "</button>";
                                            echo "<button class='btn btn-outline-danger delete-attendance' data-id='{$row['attendance_id']}' data-bs-toggle='tooltip' title='Delete'>";
                                            echo "<i class='bi bi-trash'></i>";
                                            echo "</button>";
                                            echo "</div>";
                                        } else {
                                            echo "<button class='btn btn-sm btn-outline-primary mark-individual' data-employee-id='{$row['employee_id']}'>";
                                            echo "<i class='bi bi-plus'></i> Mark";
                                            echo "</button>";
                                        }
                                        
                                        echo "</td>";
                                        echo "</tr>";
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Attendance Modal -->
<div class="modal fade" id="editAttendanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Attendance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editAttendanceForm">
                <div class="modal-body">
                    <input type="hidden" id="edit_attendance_id" name="attendance_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select id="edit_status" name="status" class="form-select" required>
                            <option value="Present">Present</option>
                            <option value="Absent">Absent</option>
                            <option value="Late">Late</option>
                            <option value="Half Day">Half Day</option>
                            <option value="Holiday">Holiday</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Check-in Time</label>
                        <input type="time" id="edit_check_in" name="check_in_time" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Check-out Time</label>
                        <input type="time" id="edit_check_out" name="check_out_time" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea id="edit_notes" name="notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Attendance</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$additional_scripts = '
<script>
    $(document).ready(function() {
        // Search functionality
        $("#attendanceSearch").on("input", function() {
            const searchTerm = this.value.toLowerCase();
            $("#attendanceTable tbody tr").each(function() {
                const text = $(this).text().toLowerCase();
                $(this).toggle(text.indexOf(searchTerm) > -1);
            });
        });

        // Mark all present functionality
        $("#markAllPresent").click(function() {
            if (confirm("Are you sure you want to mark all employees as present for today?")) {
                $.post("../../save_attendance.php", {
                    mark_all_present: 1,
                    attendance_date: $("input[name=attendance_date]").val()
                }, function(response) {
                    if (response.success) {
                        showAlert("All employees marked as present", "success");
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showAlert("Error marking attendance: " + response.message, "danger");
                    }
                }, "json");
            }
        });

        // Individual mark attendance
        $(".mark-individual").click(function() {
            const employeeId = $(this).data("employee-id");
            const date = $("input[name=attendance_date]").val();
            
            $.post("../../save_attendance.php", {
                employee_id: employeeId,
                attendance_date: date,
                status: "Present",
                check_in_time: new Date().toTimeString().slice(0,5)
            }, function(response) {
                if (response.success) {
                    showAlert("Attendance marked successfully", "success");
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert("Error marking attendance: " + response.message, "danger");
                }
            }, "json");
        });

        // Edit attendance
        $(".edit-attendance").click(function() {
            const attendanceId = $(this).data("id");
            // Load attendance data and show modal
            $("#editAttendanceModal").modal("show");
            $("#edit_attendance_id").val(attendanceId);
        });

        // Delete attendance
        $(".delete-attendance").click(function() {
            const attendanceId = $(this).data("id");
            if (confirm("Are you sure you want to delete this attendance record?")) {
                $.post("../../delete_attendance.php", {id: attendanceId}, function(response) {
                    if (response.success) {
                        showAlert("Attendance record deleted", "success");
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showAlert("Error deleting attendance: " + response.message, "danger");
                    }
                }, "json");
            }
        });

        // Auto-set check-out time when status changes
        $("select[name=status]").change(function() {
            if (this.value === "Present" || this.value === "Late") {
                if (!$("input[name=check_out_time]").val()) {
                    // Set default check-out time (9 hours after check-in)
                    const checkIn = $("input[name=check_in_time]").val();
                    if (checkIn) {
                        const checkInTime = new Date("1970-01-01T" + checkIn + ":00");
                        checkInTime.setHours(checkInTime.getHours() + 9);
                        $("input[name=check_out_time]").val(checkInTime.toTimeString().slice(0,5));
                    }
                }
            }
        });
    });

    function exportAttendance() {
        showAlert("Export functionality will be implemented soon", "info");
    }
</script>
';

include '../../layouts/footer.php';
?>