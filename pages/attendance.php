<?php
session_start();
if (!isset($_SESSION['admin'])) {
  header("Location: ../login.php");
  exit;
}
include '../db.php';
include '../includes/header.php';
include '../config.php';
?>
<?php
// Step 1: Validate ID
$id = isset($_GET['id']) ? $_GET['id'] : null;
if (!$id) {
    die("Error: Missing employee ID.");
}

// Step 2: Fetch employee by ID
$query = "SELECT * FROM employees WHERE employee_code = ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("s", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $row = mysqli_fetch_assoc($result)) {
    // Employee found, use $row as needed
} else {
    die("Employee not found or query failed.");
}

$stmt->close();
?>


<div class="page-header">
  <h1 class="page-title">
    <i class="bi bi-calendar-check me-2"></i>
    Attendance Management
  </h1>
  <p class="text-muted mb-0">Mark and track employee attendance</p>
</div>

<div class="row">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="bi bi-calendar-day me-2"></i>
          Mark Attendance (<?= date('d M Y') ?>)
        </h5>
      </div>
      <div class="card-body">
        <form action="../save_attendance.php" method="POST">
          <input type="hidden" name="date" value="<?= date('Y-m-d') ?>">
          <div class="table-responsive">
            <table class="table table-striped">
              <thead class="table-dark">
                <tr>
                  <th>Employee</th>
                  <th>Position</th>
                  <th>Status</th>
                  <th>Check-in Time</th>
                  <th>Check-out Time</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $employees = mysqli_query($conn, "SELECT * FROM employees WHERE status = 'Active' ORDER BY name");
                while ($employee = mysqli_fetch_assoc($employees)) {
                    // Check if attendance already marked for today
                    $attendanceCheck = mysqli_query($conn, "SELECT * FROM attendance WHERE employee_id = {$employee['id']} AND date = CURDATE()");
                    $attendance = mysqli_fetch_assoc($attendanceCheck);

                    echo "<tr>
                        <td>{$employee['name']}</td>
                        <td>{$employee['position']}</td>
                        <td>
                            <select name='status_{$employee['id']}' class='form-select form-select-sm'>
                                <option value='Present' " . ($attendance && $attendance['status'] === 'Present' ? 'selected' : '') . ">Present</option>
                                <option value='Absent' " . ($attendance && $attendance['status'] === 'Absent' ? 'selected' : '') . ">Absent</option>
                                <option value='Late' " . ($attendance && $attendance['status'] === 'Late' ? 'selected' : '') . ">Late</option>
                                <option value='Half Day' " . ($attendance && $attendance['status'] === 'Half Day' ? 'selected' : '') . ">Half Day</option>
                            </select>
                        </td>
                        <td>
                            <input type='time' name='checkin_{$employee['id']}' class='form-control form-control-sm' value='" . ($attendance['checkin_time'] ?? '') . "'>
                        </td>
                        <td>
                            <input type='time' name='checkout_{$employee['id']}' class='form-control form-control-sm' value='" . ($attendance['checkout_time'] ?? '') . "'>
                        </td>
                    </tr>";
                }
                ?>
              </tbody>
            </table>
          </div>
          <div class="mt-3">
            <button type="submit" class="btn btn-success">
              <i class="bi bi-save me-2"></i>
              Save Attendance
            </button>
            <a href="../attendance_preview.php" class="btn btn-secondary">
              <i class="bi bi-eye me-2"></i>
              View History
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="bi bi-graph-up me-2"></i>
          Today's Summary
        </h5>
      </div>
      <div class="card-body">
        <?php
        $totalEmployees = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM employees WHERE status = 'Active'"))['total'] ?? 0;
        $presentToday = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM attendance WHERE date = CURDATE() AND status = 'Present'"))['total'] ?? 0;
        $absentToday = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM attendance WHERE date = CURDATE() AND status = 'Absent'"))['total'] ?? 0;
        $lateToday = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM attendance WHERE date = CURDATE() AND status = 'Late'"))['total'] ?? 0;
        ?>

        <div class="row g-3">
          <div class="col-6">
            <div class="text-center border rounded p-3">
              <h6 class="text-muted">Present</h6>
              <h4 class="text-success"><?= $presentToday ?></h4>
            </div>
          </div>
          <div class="col-6">
            <div class="text-center border rounded p-3">
              <h6 class="text-muted">Absent</h6>
              <h4 class="text-danger"><?= $absentToday ?></h4>
            </div>
          </div>
          <div class="col-6">
            <div class="text-center border rounded p-3">
              <h6 class="text-muted">Late</h6>
              <h4 class="text-warning"><?= $lateToday ?></h4>
            </div>
          </div>
          <div class="col-6">
            <div class="text-center border rounded p-3">
              <h6 class="text-muted">Total</h6>
              <h4 class="text-primary"><?= $totalEmployees ?></h4>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="bi bi-lightning me-2"></i>
          Quick Actions
        </h5>
      </div>
      <div class="card-body">
        <div class="d-grid gap-2">
          <a href="../attendance_preview.php" class="btn btn-outline-primary">
            <i class="bi bi-calendar-week me-2"></i>
            View Attendance History
          </a>
          <a href="../attendance-calendar.php" class="btn btn-outline-info">
            <i class="bi bi-calendar me-2"></i>
            Calendar View
          </a>
          <a href="payroll.php" class="btn btn-outline-success">
            <i class="bi bi-currency-rupee me-2"></i>
            Generate Payroll
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>