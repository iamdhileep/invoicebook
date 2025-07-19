
<?php
include 'db.php';
if (!isset($_GET['id'])) { die("Missing ID"); }
$id = $_GET['id'];
$result = $conn->query("SELECT * FROM employees WHERE employee_id = $id");
$emp = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Edit Employee</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body><div class="container mt-5"><h2>Edit Employee</h2>
<form method="POST" action="update_employee.php" class="row g-3">
    <input type="hidden" name="id" value="<?= $emp['employee_id'] ?>">
    <div class="col-md-6"><label>Name</label><input type="text" name="name" class="form-control" value="<?= $emp['name'] ?>" required></div>
    <div class="col-md-6"><label>Code</label><input type="text" name="code" class="form-control" value="<?= $emp['employee_code'] ?>" required></div>
    <div class="col-md-6"><label>Position</label><input type="text" name="position" class="form-control" value="<?= $emp['position'] ?>" required></div>
    <div class="col-md-6"><label>Salary / Monthly (₹)</label><input type="number" name="salary" class="form-control" value="<?= htmlspecialchars($employee['monthly_salary']) ?>" required></div>
    <div class="col-12"><button type="submit" class="btn btn-primary">Update</button></div>
</form></div></body></html>
