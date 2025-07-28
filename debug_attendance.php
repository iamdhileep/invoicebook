<?php
include 'db.php';

// Check attendance table structure
$result = $conn->query('DESCRIBE attendance');
if ($result) {
    echo 'Attendance table structure:' . PHP_EOL;
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . ' - ' . $row['Type'] . ' - ' . $row['Key'] . PHP_EOL;
    }
} else {
    echo 'Error: ' . $conn->error . PHP_EOL;
}

// Check if there are any attendance records
$count = $conn->query('SELECT COUNT(*) as total FROM attendance');
if ($count) {
    $total = $count->fetch_assoc()['total'];
    echo PHP_EOL . 'Total attendance records: ' . $total . PHP_EOL;
}

// Check recent records
$recent = $conn->query('SELECT * FROM attendance ORDER BY created_at DESC LIMIT 5');
if ($recent) {
    echo PHP_EOL . 'Recent attendance records:' . PHP_EOL;
    while ($row = $recent->fetch_assoc()) {
        echo 'ID: ' . $row['id'] . ', Employee: ' . $row['employee_id'] . ', Date: ' . $row['attendance_date'] . ', Status: ' . $row['status'] . ', Time In: ' . $row['time_in'] . ', Time Out: ' . $row['time_out'] . PHP_EOL;
    }
}

// Check for today's records
$today = date('Y-m-d');
$todayRecords = $conn->query("SELECT * FROM attendance WHERE attendance_date = '$today'");
if ($todayRecords) {
    echo PHP_EOL . 'Today\'s attendance records:' . PHP_EOL;
    while ($row = $todayRecords->fetch_assoc()) {
        echo 'Employee: ' . $row['employee_id'] . ', Status: ' . $row['status'] . ', Time In: ' . $row['time_in'] . ', Time Out: ' . $row['time_out'] . PHP_EOL;
    }
}
?>
