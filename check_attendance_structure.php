<?php
include 'db.php';
echo "attendance table structure:\n";
$structure = $conn->query('DESCRIBE attendance');
while ($row = $structure->fetch_assoc()) {
    echo "- " . $row['Field'] . " (" . $row['Type'] . ")" . ($row['Null'] == 'NO' ? ' NOT NULL' : '') . "\n";
}
?>
