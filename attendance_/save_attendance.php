<?php
include '../db.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $empId = $_POST['employee_id'];
    $date = $_POST['date'];
    $status = $_POST['status'];
    $hours = $_POST['working_hours'];
    $remarks = $_POST['remarks'];
    $date = $_POST['date'];
foreach ($_POST['status'] as $emp_id => $status) {
    $stmt = $conn->prepare("INSERT INTO attendance (employee_id, date, status) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $emp_id, $date, $status);
    $stmt->execute();
}

    $stmt = $conn->prepare("INSERT INTO employee_attendance (employee_id, date, status, working_hours, remarks) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issds", $empId, $date, $status, $hours, $remarks);
    $stmt->execute();

    header("Location: attendance.php?success=1");
}
?>
