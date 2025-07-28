<?php
include 'db.php';

echo "Checking database tables:\n";
$result = $conn->query('SHOW TABLES');
while($row = $result->fetch_array()) {
    echo "- " . $row[0] . "\n";
}

echo "\nChecking for attendance-related tables specifically:\n";
$attendance_tables = ['attendance', 'employees', 'biometric_devices', 'device_sync_status'];
foreach($attendance_tables as $table) {
    $check = $conn->query("SHOW TABLES LIKE '$table'");
    echo "- $table: " . ($check && $check->num_rows > 0 ? "EXISTS" : "MISSING") . "\n";
}
?>
