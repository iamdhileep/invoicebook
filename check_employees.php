<?php
include 'db.php';
$result = $conn->query('SELECT employee_id, full_name FROM employees LIMIT 5');
echo "Available employees:\n";
while ($row = $result->fetch_assoc()) {
    echo "ID: " . $row['employee_id'] . " - " . $row['full_name'] . "\n";
}
?>
