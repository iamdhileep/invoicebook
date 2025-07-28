<?php
include 'db.php';

$result = $conn->query('SELECT COUNT(*) as count FROM employees');
if($result) {
    $row = $result->fetch_assoc();
    echo 'Total employees: ' . $row['count'] . PHP_EOL;
} else {
    echo 'Error: ' . $conn->error . PHP_EOL;
}

// Also check leave_requests table
$result2 = $conn->query('SELECT COUNT(*) as count FROM leave_requests');
if($result2) {
    $row2 = $result2->fetch_assoc();
    echo 'Total leave requests: ' . $row2['count'] . PHP_EOL;
} else {
    echo 'Error with leave_requests: ' . $conn->error . PHP_EOL;
}
?>
