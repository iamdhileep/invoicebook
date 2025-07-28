<?php
require_once 'db.php';

echo "=== EMPLOYEES TABLE STRUCTURE ===\n";
$result = $conn->query('DESCRIBE employees');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "Column: " . $row['Field'] . " | Type: " . $row['Type'] . "\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}

echo "\n=== SAMPLE EMPLOYEE DATA ===\n";
$result = $conn->query('SELECT * FROM employees LIMIT 3');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "Employee ID: " . $row['employee_id'] . " | Name: " . ($row['name'] ?? 'N/A') . "\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}
?>
