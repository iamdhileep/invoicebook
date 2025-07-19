<?php include '../db.php'; ?>
<form method="GET">
  <input type="month" name="month" required>
  <button type="submit">Show Payroll</button>
</form>

<?php
if (isset($_GET['month'])) {
  $month = $_GET['month'];
  list($year, $month_num) = explode("-", $month);
  $start = "$year-$month_num-01";
  $end = date("Y-m-t", strtotime($start));

  $res = mysqli_query($conn, "SELECT * FROM employees");
  echo "<table class='table'><tr><th>Name</th><th>Present Days</th><th>Salary</th></tr>";

  while ($emp = mysqli_fetch_assoc($res)) {
    $emp_id = $emp['employee_id'];
    $salary_per_day = $emp['salary_per_day'];

    $att = mysqli_query($conn, "SELECT COUNT(*) as days FROM attendance WHERE employee_id = $emp_id AND status='Present' AND date BETWEEN '$start' AND '$end'");
    $present = mysqli_fetch_assoc($att)['days'];
    $salary = $present * $salary_per_day;

    echo "<tr>
      <td>{$emp['name']}</td>
      <td>$present</td>
      <td>₹ " . number_format($salary, 2) . "</td>
    </tr>";
  }
  echo "</table>";
}
?>
