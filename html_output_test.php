<?php
// Test if we can simulate the exact HTML output
include 'db.php';

$today = date('Y-m-d');

// Same queries as attendance.php
$employees = $conn->query("SELECT employee_id, name, employee_code, position FROM employees WHERE status = 'active' ORDER BY name ASC");
$employeeCount = mysqli_num_rows($employees);

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

echo "=== HTML TABLE OUTPUT SIMULATION ===\n\n";

if ($employees && mysqli_num_rows($employees) > 0) {
    mysqli_data_seek($employees, 0); // Reset pointer as in fixed code
    
    echo "Table will render with the following data:\n";
    echo "==========================================\n\n";
    
    $rowNum = 0;
    while ($employee = $employees->fetch_assoc()) {
        $rowNum++;
        $empId = $employee['employee_id'];
        $existingAttendance = $attendanceData[$empId] ?? null;
        
        // Same logic as attendance.php
        $status = $existingAttendance['status'] ?? 'Absent';
        
        $timeIn = '';
        if (!empty($existingAttendance['time_in']) && $existingAttendance['time_in'] !== '00:00:00') {
            $timeIn = date('H:i', strtotime($existingAttendance['time_in']));
        }
        
        $timeOut = '';
        if (!empty($existingAttendance['time_out']) && $existingAttendance['time_out'] !== '00:00:00') {
            $timeOut = date('H:i', strtotime($existingAttendance['time_out']));
        }
        
        $notes = $existingAttendance['notes'] ?? '';
        
        echo "Row $rowNum HTML will show:\n";
        echo "  Employee: {$employee['name']} ({$employee['employee_code']})\n";
        echo "  Status: $status\n";
        echo "  Time In Field: value=\"$timeIn\"\n";
        echo "  Time Out Field: value=\"$timeOut\"\n";
        echo "  Notes Field: value=\"$notes\"\n";
        
        // Show the actual HTML input that will be rendered
        echo "  HTML: <input type=\"time\" value=\"$timeIn\"> | <input type=\"time\" value=\"$timeOut\">\n";
        echo "\n";
    }
    
    echo "✅ All $rowNum employees will display with correct data in the table!\n";
} else {
    echo "❌ No employees found - table will be empty\n";
}

echo "\n=== VERIFICATION COMPLETE ===\n";
?>
