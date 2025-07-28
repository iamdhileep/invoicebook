<?php
include 'db.php';

$result = $conn->query("SHOW COLUMNS FROM leaves LIKE 'leave_type'");
if ($result && $row = $result->fetch_assoc()) {
    echo "Leave type enum: " . $row['Type'] . "\n";
}

$result = $conn->query("SHOW COLUMNS FROM leaves LIKE 'status'");
if ($result && $row = $result->fetch_assoc()) {
    echo "Status enum: " . $row['Type'] . "\n";
}
?>
