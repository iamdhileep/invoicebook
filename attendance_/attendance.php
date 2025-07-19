<?php include '../db.php'; ?>
<form method="POST" action="save_attendance.php">
  <h4>Mark Attendance for <?= date('Y-m-d') ?></h4>
  <input type="hidden" name="date" value="<?= date('Y-m-d') ?>">
  <table class="table">
    <tr><th>Name</th><th>Status</th></tr>
    <?php
    $res = mysqli_query($conn, "SELECT * FROM employees");
    while ($row = mysqli_fetch_assoc($res)) {
      echo "<tr>
        <td>{$row['name']}</td>
        <td>
          <select name='status[{$row['employee_id']}]' class='form-select'>
            <option value='Present'>Present</option>
            <option value='Absent'>Absent</option>
            <option value='Leave'>Leave</option>
          </select>
        </td>
      </tr>";
    }
    ?>
  </table>
  <button class="btn btn-success">Save Attendance</button>
</form>
