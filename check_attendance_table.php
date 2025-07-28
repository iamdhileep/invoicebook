<?php
require_once 'db.php';

echo "=== ATTENDANCE TABLE STRUCTURE ===\n";
$result = $conn->query('DESCRIBE attendance');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "Column: " . $row['Field'] . " | Type: " . $row['Type'] . "\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}
?>
