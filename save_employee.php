
<?php
include 'db.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $code = $_POST['code'];
    $position = $_POST['position'];
    $salary = $_POST['salary'];
    $stmt = $conn->prepare("INSERT INTO employees (name, employee_code, position, salary_per_day) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssd", $name, $code, $position, $salary);
    $stmt->execute();
    $stmt->close();
    header("Location: employee_list.php");
    exit;
}
?>
