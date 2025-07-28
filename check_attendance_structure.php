<?php
require 'db.php';
echo "Current attendance table structure:\n";
$result = $conn->query('DESCRIBE attendance');
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}
?>
