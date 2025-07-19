<?php
include 'db.php';

$events = [];

$sql = "SELECT a.attendance_date, a.status, e.name 
        FROM attendance a
        JOIN employees e ON a.employee_id = e.employee_id";

$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $events[] = [
        'title' => $row['name'] . ' - ' . $row['status'],
        'start' => $row['attendance_date'],
        'color' => ($row['status'] === 'Present') ? '#28a745' : '#dc3545'
    ];
}

header('Content-Type: application/json');
echo json_encode($events);
?>
