<?php
include 'db.php';

if (!isset($_POST['attendance_date']) || empty($_POST['attendance_date'])) {
    die("Attendance date is required.");
}

$date = $_POST['attendance_date'];
$statuses = $_POST['status'];
$timeIns = $_POST['time_in'];
$timeOuts = $_POST['time_out'];

// ✅ INSERT or UPDATE using UNIQUE constraint
$query = "
    INSERT INTO attendance (employee_id, attendance_date, status, time_in, time_out)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE 
        status = VALUES(status),
        time_in = IF(VALUES(time_in) != '', VALUES(time_in), time_in),
        time_out = IF(VALUES(time_out) != '', VALUES(time_out), time_out)
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

foreach ($statuses as $empId => $status) {
    $timeIn = $timeIns[$empId] ?? '';
    $timeOut = $timeOuts[$empId] ?? '';
    $stmt->bind_param("issss", $empId, $date, $status, $timeIn, $timeOut);
    $stmt->execute();
}
$stmt->close();

// ✅ Redirect with success flag — prevents resubmission
header("Location: mark_attendance.php?success=1");
exit;
?>
