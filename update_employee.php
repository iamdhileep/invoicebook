<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = $_POST['id'];
    $name     = $_POST['name'];
    $code     = $_POST['code'];
    $position = $_POST['position'];
    $salary   = $_POST['salary']; // This should be monthly salary

    // 👇 Check your DB column name: is it monthly_salary or salary?
    $stmt = $conn->prepare("UPDATE employees SET name=?, employee_code=?, position=?, monthly_salary=? WHERE employee_id=?");

    if (!$stmt) {
        die("SQL Prepare Error: " . $conn->error); // Shows what's wrong if SQL fails
    }

    $stmt->bind_param("sssdi", $name, $code, $position, $salary, $id);
    $stmt->execute();
    $stmt->close();

    header("Location: employee_list.php");
    exit;
}
?>
