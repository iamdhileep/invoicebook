
<?php
include 'db.php';
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $conn->query("DELETE FROM employees WHERE employee_id = $id");
}
header("Location: employee_list.php,");
exit;
?>
