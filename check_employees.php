<?php
require 'db.php';
echo "Employees table structure:\n";
$result = $conn->query('DESCRIBE employees');
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}

echo "\nSample employees data:\n";
$result = $conn->query('SELECT * FROM employees LIMIT 3');
while($row = $result->fetch_assoc()) {
    echo "ID: " . $row['id'] . ", Name: " . ($row['name'] ?? $row['first_name'] . ' ' . $row['last_name']) . ", Status: " . ($row['status'] ?? 'N/A') . "\n";
}
?>
