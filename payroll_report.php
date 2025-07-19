<?php
include 'db.php';

// Handle input from month picker (format: yyyy-mm)
if (isset($_GET['month_year'])) {
    $dateParts = explode('-', $_GET['month_year']);
    $year = $dateParts[0];
    $month = $dateParts[1];
} else {
    $month = date('m');
    $year = date('Y');
}

// Get number of days in the month
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

// Get employees with corrected column aliases
$employees = $conn->query("SELECT employee_id AS id, name, employee_code, position, salary_per_day FROM employees");


if (!$employees) {
    die("Query Failed: " . $conn->error);
}

// Get attendance count for present/absent
function getAttendanceCount($conn, $empId, $month, $year, $status) {
    $query = "SELECT COUNT(*) FROM attendance WHERE employee_id = ? AND MONTH(attendance_date) = ? AND YEAR(attendance_date) = ? AND status = ?";
    $stmt = $conn->prepare($query);

    if (!$stmt) {
        die("SQL Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("iiss", $empId, $month, $year, $status);
    $stmt->execute();
    $count = 0;
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payroll Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <a href="index.php" class="btn btn-sm btn-outline-secondary mb-3">← Back to Dashboard</a>
    <h2 class="mb-4">Payroll Report</h2>

    <form method="GET" class="row g-3 mb-4">
        <div class="col-md-4">
            <label>Select Month</label>
            <input type="month" name="month_year" class="form-control"
                   value="<?= $year ?>-<?= str_pad($month, 2, '0', STR_PAD_LEFT) ?>">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary mt-4">View</button>
        </div>
    </form>

    <?php if ($employees->num_rows > 0): ?>
        <table class="table table-bordered">
            <thead class="table-dark">
            <tr>
                <th>Employee</th>
                <th>Code</th>
                <th>Position</th>
                <th>Present Days</th>
                <th>Absent Days</th>
                <th>Salary/Day (₹)</th>
                <th>Total Salary (₹)</th>
            </tr>
            </thead>
            <tbody>
            <?php while ($emp = $employees->fetch_assoc()): 
                $present = getAttendanceCount($conn, $emp['id'], $month, $year, 'Present');
                $absent = $daysInMonth - $present;
                $salaryPerDay = $emp['salary_per_day'];
                $totalSalary = $present * $salaryPerDay;
            ?>
            <tr>
                <td><?= htmlspecialchars($emp['name']) ?></td>
                <td><?= htmlspecialchars($emp['employee_code']) ?></td>
                <td><?= htmlspecialchars($emp['position']) ?></td>
                <td><?= $present ?></td>
                <td><?= $absent ?></td>
                <td>₹<?= number_format($salaryPerDay, 2) ?></td>
                <td><strong>₹<?= number_format($totalSalary, 2) ?></strong></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="alert alert-warning">No employee records found.</div>
    <?php endif; ?>
</div>
</body>
</html>
