<?php
include 'db.php';

echo "=== Categories Table Structure ===\n";
$result = $conn->query('DESCRIBE categories');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
} else {
    echo 'Error: ' . $conn->error . "\n";
}
?>
