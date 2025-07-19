<?php include 'db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Monthly Attendance Preview</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .badge-present { background-color: #198754; }
    .badge-absent { background-color: #dc3545; }
  </style>
</head>
<body>
<div class="container mt-4">

  <!-- 🔙 Back Button -->
  <a href="index.php" class="btn btn-outline-secondary mb-3">← Back to Dashboard</a>

  <h4>📆 Monthly Attendance Preview</h4>

  <!-- Filter Form -->
  <form method="GET" class="row g-3 mb-4">
    <div class="col-md-3">
      <label>Select Month</label>
      <input type="month" name="month" class="form-control" value="<?= $_GET['month'] ?? date('Y-m') ?>">
    </div>
    <div class="col-md-3">
      <label>Search Employee</label>
      <input type="text" name="search" class="form-control" value="<?= $_GET['search'] ?? '' ?>" placeholder="Name">
    </div>
    <div class="col-md-2 d-flex align-items-end">
      <button type="submit" class="btn btn-primary w-100">🔍 Filter</button>
    </div>
    <div class="col-md-2 d-flex align-items-end">
      <a href="?month=<?= $_GET['month'] ?? date('Y-m') ?>&search=<?= $_GET['search'] ?? '' ?>&export=1" class="btn btn-success w-100">📤 Export</a>
    </div>
  </form>

  <?php
  // Get selected month
  $monthYear = $_GET['month'] ?? date('Y-m');
  [$year, $month] = explode('-', $monthYear);
  $search = $_GET['search'] ?? '';

  $where = "WHERE MONTH(a.attendance_date) = '$month' AND YEAR(a.attendance_date) = '$year'";
  if ($search != '') {
      $where .= " AND e.name LIKE '%$search%'";
  }

  $query = "
    SELECT a.*, e.name 
    FROM attendance a
    JOIN employees e ON a.employee_id = e.employee_id
    $where
    ORDER BY a.attendance_date DESC, e.name ASC
  ";

  $result = $conn->query($query);

  // Export to CSV
  if (isset($_GET['export']) && $result->num_rows > 0) {
      header("Content-Type: text/csv");
      header("Content-Disposition: attachment;filename=attendance_$monthYear.csv");
      $out = fopen("php://output", "w");
      fputcsv($out, ['Date', 'Employee Name', 'Status', 'Time In', 'Time Out']);
      while ($row = $result->fetch_assoc()) {
          fputcsv($out, [$row['attendance_date'], $row['name'], $row['status'], $row['time_in'], $row['time_out']]);
      }
      fclose($out);
      exit;
  }
  ?>

  <?php if ($result && $result->num_rows > 0): ?>
    <table class="table table-bordered">
      <thead class="table-dark text-center">
        <tr>
          <th>Date</th>
          <th>Employee</th>
          <th>Status</th>
          <th>Time In</th>
          <th>Time Out</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($row['attendance_date']) ?></td>
          <td><?= htmlspecialchars($row['name']) ?></td>
          <td class="text-center">
            <span class="badge <?= $row['status'] === 'Present' ? 'badge-present' : 'badge-absent' ?>">
              <?= $row['status'] ?>
            </span>
          </td>
          <td class="text-center"><?= $row['time_in'] ?: '-' ?></td>
          <td class="text-center"><?= $row['time_out'] ?: '-' ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="alert alert-warning">No attendance records found for <?= "$monthYear" ?>.</div>
  <?php endif; ?>

</div>
</body>
</html>
