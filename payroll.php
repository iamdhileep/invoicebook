<?php include 'db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Payroll Report</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
  <h4 class="mb-3">📋 Advanced Payroll Report</h4>

  <form method="GET" class="row g-3 mb-4">
    <div class="col-md-3">
      <label>Start Date</label>
      <input type="date" name="start_date" class="form-control" value="<?= $_GET['start_date'] ?? '' ?>" required>
    </div>
    <div class="col-md-3">
      <label>End Date</label>
      <input type="date" name="end_date" class="form-control" value="<?= $_GET['end_date'] ?? '' ?>" required>
    </div>
    <div class="col-md-3">
      <label>Position</label>
      <select name="position" class="form-select">
        <option value="">All</option>
        <?php
        $positions = mysqli_query($conn, "SELECT DISTINCT position FROM employees");
        while ($pos = mysqli_fetch_assoc($positions)) {
          $selected = ($_GET['position'] ?? '') === $pos['position'] ? 'selected' : '';
          echo "<option value='{$pos['position']}' $selected>" . htmlspecialchars($pos['position']) . "</option>";
        }
        ?>
      </select>
    </div>
    <div class="col-md-3">
      <label>Search by Name</label>
      <input type="text" name="search" class="form-control" placeholder="e.g. John" value="<?= $_GET['search'] ?? '' ?>">
    </div>
    <div class="col-12 d-flex justify-content-between mt-2">
      <button type="submit" class="btn btn-primary">🔍 Generate Report</button>
      <a href="index.php#payroll" class="btn btn-outline-secondary">↺ Reset</a>
    </div>
  </form>

  <?php
  if (isset($_GET['start_date'], $_GET['end_date'])):
    $start = $_GET['start_date'];
    $end = $_GET['end_date'];
    $position = $_GET['position'] ?? '';
    $search = $_GET['search'] ?? '';

    echo "<p><strong>Payroll Duration:</strong> " . date("d M Y", strtotime($start)) . " to " . date("d M Y", strtotime($end)) . "</p>";

    $query = "SELECT * FROM employees WHERE 1";
    if ($position !== '') $query .= " AND position = '" . $conn->real_escape_string($position) . "'";
    if ($search !== '') $query .= " AND name LIKE '%" . $conn->real_escape_string($search) . "%'";
    $result = $conn->query($query);

    echo "<div class='table-responsive'><table class='table table-bordered table-striped table-hover'>
    <thead class='table-dark text-center'>
      <tr>
        <th>ID</th><th>Name</th><th>Position</th><th>Monthly Salary (₹)</th>
        <th>Present</th><th>Absent</th>
        <th>Present Pay (₹)</th><th>Absent Deduction (₹)</th>
        <th>Advance Salary (₹)</th>
        <th><b>Final Salary (₹)</b></th>
        <th>Bank</th><th>Account No</th><th>IFSC</th>
        <th>Action</th>
      </tr>
    </thead><tbody>";

    while ($emp = $result->fetch_assoc()) {
      $id = $emp['employee_id'];
      $monthly = $emp['monthly_salary'];
      $bank = $emp['bank_name'];
      $acc = $emp['account_number'];
      $ifsc = $emp['ifsc_code'];

      // Attendance
      $att = $conn->query("SELECT status, COUNT(*) as count FROM attendance WHERE employee_id = $id AND attendance_date BETWEEN '$start' AND '$end' GROUP BY status");
      $present = $absent = 0;
      while ($row = $att->fetch_assoc()) {
        if ($row['status'] == 'Present') $present = $row['count'];
        if ($row['status'] == 'Absent') $absent = $row['count'];
      }

      $days = cal_days_in_month(CAL_GREGORIAN, date('m', strtotime($start)), date('Y', strtotime($start)));
      $per_day = $monthly / $days;

      $present_pay = $present * $per_day;
      $absent_deduct = $absent * $per_day;

      // Advance Salary
      $advance = 0;
      $adv_res = mysqli_query($conn, "SELECT SUM(amount) AS adv FROM salary_advance WHERE employee_id = $id AND DATE(advance_date) BETWEEN '$start' AND '$end'");
      if ($adv_res && $row = mysqli_fetch_assoc($adv_res)) {
        $advance = $row['adv'] ?? 0;
      }

      $final_salary = $present_pay - $advance;

      echo "<tr class='text-center'>
        <td>$id</td>
        <td>" . htmlspecialchars($emp['name']) . "</td>
        <td>" . htmlspecialchars($emp['position']) . "</td>
        <td>₹" . number_format($monthly, 2) . "</td>
        <td>$present</td>
        <td>$absent</td>
        <td>₹" . number_format($present_pay, 2) . "</td>
        <td>-₹" . number_format($absent_deduct, 2) . "</td>
        <td>-₹" . number_format($advance, 2) . "</td>
        <td><b>₹" . number_format($final_salary, 2) . "</b></td>
        <td>$bank</td>
        <td>$acc</td>
        <td>$ifsc</td>
        <td><button class='btn btn-success btn-sm'>Transfer</button></td>
      </tr>";
    }

    echo "</tbody></table></div>";
  endif;
  ?>
</div>
</body>
</html>
