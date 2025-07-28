<?php
include 'db.php';

echo "=== ATTENDANCE DATA DEBUGGING ===\n\n";

$today = date('Y-m-d');
echo "Testing for date: $today\n\n";

// Test 1: Check employees
echo "1. Active Employees:\n";
$employees = $conn->query("SELECT employee_id, name, employee_code FROM employees WHERE status = 'active' ORDER BY name ASC");
if ($employees) {
    while ($emp = $employees->fetch_assoc()) {
        echo "  - ID: {$emp['employee_id']}, Name: {$emp['name']}, Code: {$emp['employee_code']}\n";
    }
} else {
    echo "  ERROR: " . $conn->error . "\n";
}

echo "\n2. Today's Attendance Records:\n";
$attendance = $conn->query("SELECT employee_id, status, time_in, time_out, notes FROM attendance WHERE attendance_date = '$today' ORDER BY employee_id");
if ($attendance) {
    if (mysqli_num_rows($attendance) > 0) {
        while ($att = $attendance->fetch_assoc()) {
            echo "  - Employee {$att['employee_id']}: Status={$att['status']}, In={$att['time_in']}, Out={$att['time_out']}, Notes={$att['notes']}\n";
        }
    } else {
        echo "  No attendance records found for today.\n";
    }
} else {
    echo "  ERROR: " . $conn->error . "\n";
}

echo "\n3. Testing Data Fetch Logic (like in attendance.php):\n";
$attendanceData = [];
$attendanceQuery = $conn->prepare("SELECT employee_id, status, time_in, time_out, notes FROM attendance WHERE attendance_date = ?");
if ($attendanceQuery) {
    $attendanceQuery->bind_param("s", $today);
    if ($attendanceQuery->execute()) {
        $attendanceResult = $attendanceQuery->get_result();
        while ($row = $attendanceResult->fetch_assoc()) {
            $attendanceData[$row['employee_id']] = $row;
        }
        $attendanceQuery->close();
    }
}

echo "Fetched attendance data:\n";
foreach ($attendanceData as $empId => $data) {
    $timeIn = '';
    if (!empty($data['time_in']) && $data['time_in'] !== '00:00:00') {
        $timeIn = date('H:i', strtotime($data['time_in']));
    }
    
    $timeOut = '';
    if (!empty($data['time_out']) && $data['time_out'] !== '00:00:00') {
        $timeOut = date('H:i', strtotime($data['time_out']));
    }
    
    echo "  - Employee $empId: Status={$data['status']}, Display In=$timeIn, Display Out=$timeOut\n";
}

echo "\n=== END DEBUG ===\n";
?>
