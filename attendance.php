<?php include 'db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Mark Attendance</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
  <h4 class="mb-4">🗓️ Mark Employee Attendance</h4>

  <form method="POST" action="save_attendance.php">
    <div class="mb-3">
      <label for="attendance_date" class="form-label">Date</label>
      <input type="date" name="attendance_date" class="form-control" required value="<?= date('Y-m-d') ?>">
    </div>

    <table class="table table-bordered">
      <thead class="table-dark">
        <tr>
          <th>Employee Name</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $result = mysqli_query($conn, "SELECT * FROM employees");
        while ($row = mysqli_fetch_assoc($result)) {
          $id = $row['employee_id'];
          echo "<tr>
                  <td>
                    {$row['name']}
                    <input type='hidden' name='employee_ids[]' value='$id'>
                  </td>
                  <td>
                    <select name='status[$id]' class='form-select'>
                      <option value='Present'>Present</option>
                      <option value='Absent' selected>Absent</option>
                    </select>
                  </td>
                </tr>";
        }
        ?>
      </tbody>
    </table>

    <button type="submit" class="btn btn-success">💾 Submit Attendance</button>
  </form>
</div>
</body>
</html>
