<?php
include 'db.php';
$result = $conn->query("SELECT employee_id, name, employee_code, position, monthly_salary FROM employees");
if (!$result) { die("Query Failed: " . $conn->error); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <a href="index.php" class="btn btn-sm btn-outline-secondary mb-3">← Back to Dashboard</a>
    <h2 class="mb-4">Employee List</h2>
    <a href="add_employee.php" class="btn btn-success mb-3">+ Add Employee</a>
    <?php if ($result->num_rows === 0): ?>
        <div class="alert alert-warning">No employees found.</div>
    <?php else: ?>
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr><th>Name</th><th>Code</th><th>Position</th><th>Monthly Salary (₹)</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= htmlspecialchars($row['employee_code']) ?></td>
                        <td><?= htmlspecialchars($row['position']) ?></td>
                        <td>₹<?= number_format($row['monthly_salary'], 2) ?></td>
                        <td>
                            <a href="edit_employee.php?id=<?= $row['employee_id'] ?>" class="btn btn-primary btn-sm">Edit</a>
                            <a href="delete_employee.php?id=<?= $row['employee_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this employee?')">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
