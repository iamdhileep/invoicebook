<?php include '../db.php'; ?>
<form method="GET">
  <input type="month" name="month" value="<?= date('Y-m') ?>" required>
  <button type="submit">View Report</button>
</form>

<?php
$month = $_GET['month'] ?? date('Y-m');

$sql = "
SELECT e.name, e.salary_per_day, COUNT(a.id) AS present_days,
       (COUNT(a.id) * e.salary_per_day) AS total_salary
FROM employees e
LEFT JOIN employee_attendance a 
  ON e.employee_id = a.employee_id 
  AND a.status = 'Present'
  AND DATE_FORMAT(a.date, '%Y-%m') = '$month'
GROUP BY e.employee_id
";

$result = mysqli_query($conn, $sql);
?>
<table>
  <tr><th>Employee</th><th>Salary / Day</th><th>Present Days</th><th>Total Salary</th></tr>
  <?php while ($row = mysqli_fetch_assoc($result)): ?>
  <tr>
    <td><?= $row['name'] ?></td>
    <td>₹<?= $row['salary_per_day'] ?></td>
    <td><?= $row['present_days'] ?></td>
    <td><strong>₹<?= number_format($row['total_salary'], 2) ?></strong></td>
  </tr>
  <?php endwhile; ?>
</table>
