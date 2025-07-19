<?php
include 'db.php';
date_default_timezone_set('Asia/Kolkata');
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Advanced Attendance</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .clock { font-weight: bold; color: #0d6efd; font-size: 1.1rem; }
    .punch-btn { font-size: 0.8rem; padding: 3px 10px; }
    .last-punch { font-size: 0.8rem; color: #888; }
  </style>
</head>
<body>
<div class="container mt-4">
  <a href="index.php" class="btn btn-sm btn-outline-secondary mb-3">← Back to Dashboard</a>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4>🕒 Mark Attendance</h4>
    <div class="clock">Live Time: <span id="liveClock"></span></div>
  </div>

  <form action="save_attendance.php" method="POST">
    <input type="date" name="attendance_date" class="form-control" value="<?= date('Y-m-d') ?>" required>


    <table class="table table-bordered align-middle">
      <thead class="table-dark text-center">
        <tr>
          <th>Employee</th>
          <th>Status</th>
          <th>Time In</th>
          <th>Time Out</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $result = $conn->query("SELECT * FROM employees");
        while ($row = $result->fetch_assoc()):
          $id = $row['employee_id'];
          $att = $conn->query("SELECT * FROM attendance WHERE employee_id=$id AND attendance_date='$today' LIMIT 1");
          $time_in = '';
          $time_out = '';
          $status = 'Absent';
          $lastTime = '';
          if ($att && $att->num_rows > 0) {
              $data = $att->fetch_assoc();
              $time_in = $data['time_in'];
              $time_out = $data['time_out'];
              $status = $data['status'];
              if (!empty($time_out)) {
                  $lastTime = "Last Out: " . date("h:i A", strtotime($time_out));
              } elseif (!empty($time_in)) {
                  $lastTime = "Last In: " . date("h:i A", strtotime($time_in));
              }
          }
        ?>
        <tr>
          <td>
            <?= htmlspecialchars($row['name']) ?>
            <input type="hidden" name="employee_id[]" value="<?= $id ?>">
            <div class="last-punch"><?= $lastTime ?></div>
          </td>
          <td class="text-center">
            <select name="status[<?= $id ?>]" id="status-<?= $id ?>" class="form-select form-select-sm">
              <option value="Present" <?= $status === 'Present' ? 'selected' : '' ?>>Present</option>
              <option value="Absent" <?= $status === 'Absent' ? 'selected' : '' ?>>Absent</option>
            </select>
          </td>
          <td><input type="time" name="time_in[<?= $id ?>]" id="time_in_<?= $id ?>" value="<?= $time_in ?>" class="form-control form-control-sm"></td>
          <td><input type="time" name="time_out[<?= $id ?>]" id="time_out_<?= $id ?>" value="<?= $time_out ?>" class="form-control form-control-sm"></td>
          <td class="text-center">
            <button type="button" class="btn btn-success btn-sm punch-btn" onclick="punchIn(<?= $id ?>)">In</button>
            <button type="button" class="btn btn-danger btn-sm punch-btn" onclick="punchOut(<?= $id ?>)">Out</button>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>

    <div class="text-end">
      <a href="attendance_preview.php" class="btn btn-secondary">👁️ View Attendance Records</a>

      <button type="submit" class="btn btn-primary">💾 Save Attendance</button>
    </div>
    <?php if (isset($_GET['success'])): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    ✅ Attendance saved successfully!
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

  </form>
</div>

<script>
// Live Clock
function updateLiveClock() {
  const now = new Date();
  document.getElementById("liveClock").innerText = now.toLocaleTimeString();
}
setInterval(updateLiveClock, 1000);
updateLiveClock();

// Punch Button Scripts
function getTimeNow() {
  const now = new Date();
  return now.toTimeString().split(' ')[0].substring(0, 5);
}

function punchIn(id) {
  document.getElementById('time_in_' + id).value = getTimeNow();
  document.getElementById('status-' + id).value = 'Present';
}

function punchOut(id) {
  document.getElementById('time_out_' + id).value = getTimeNow();
  document.getElementById('status-' + id).value = 'Present';
}
</script>
</body>
</html>
