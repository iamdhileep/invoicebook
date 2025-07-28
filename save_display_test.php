<?php
session_start();
$_SESSION['admin'] = 'test_admin';

include 'db.php';

echo "=== ATTENDANCE SAVE & DISPLAY TEST ===\n\n";

$today = date('Y-m-d');
$testEmployeeId = 24; // Dhileepkumar
$testTimeIn = date('H:i:s');
$testTimeOut = date('H:i:s', strtotime('+8 hours'));

echo "Testing with Employee ID: $testEmployeeId\n";
echo "Test Time In: $testTimeIn\n";
echo "Test Time Out: $testTimeOut\n";
echo "Date: $today\n\n";

// 1. Save test data
echo "1. SAVING TEST DATA:\n";
try {
    $query = "
        INSERT INTO attendance (employee_id, attendance_date, status, time_in, time_out, notes, marked_by, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE 
            status = VALUES(status),
            time_in = VALUES(time_in),
            time_out = VALUES(time_out),
            notes = VALUES(notes),
            marked_by = VALUES(marked_by),
            updated_at = NOW()
    ";
    
    $stmt = $conn->prepare($query);
    $status = 'Present';
    $notes = 'Test save at ' . date('H:i:s');
    $marked_by = 'test_admin';
    
    $stmt->bind_param("issssss", $testEmployeeId, $today, $status, $testTimeIn, $testTimeOut, $notes, $marked_by);
    
    if ($stmt->execute()) {
        echo "✅ Data saved successfully\n\n";
    } else {
        echo "❌ Save failed: " . $stmt->error . "\n\n";
    }
    $stmt->close();
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n\n";
}

// 2. Test immediate retrieval (like page refresh)
echo "2. IMMEDIATE RETRIEVAL TEST:\n";

// Same query as attendance.php
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

echo "Employees found: $employeeCount\n";
echo "Attendance records: " . count($attendanceData) . "\n\n";

// 3. Check specific test employee
echo "3. TEST EMPLOYEE CHECK:\n";
if (isset($attendanceData[$testEmployeeId])) {
    $data = $attendanceData[$testEmployeeId];
    echo "✅ Found attendance data for employee $testEmployeeId:\n";
    echo "   Status: {$data['status']}\n";
    echo "   Time In: {$data['time_in']}\n";
    echo "   Time Out: {$data['time_out']}\n";
    echo "   Notes: {$data['notes']}\n";
    
    // Test display formatting
    $timeIn = '';
    if (!empty($data['time_in']) && $data['time_in'] !== '00:00:00') {
        $timeIn = date('H:i', strtotime($data['time_in']));
    }
    
    $timeOut = '';
    if (!empty($data['time_out']) && $data['time_out'] !== '00:00:00') {
        $timeOut = date('H:i', strtotime($data['time_out']));
    }
    
    echo "\n   📋 FORMATTED FOR DISPLAY:\n";
    echo "   Status: {$data['status']}\n";
    echo "   Time In: " . ($timeIn ?: 'Empty') . "\n";
    echo "   Time Out: " . ($timeOut ?: 'Empty') . "\n";
    
} else {
    echo "❌ No attendance data found for employee $testEmployeeId\n";
}

// 4. Test the table loop simulation
echo "\n4. TABLE LOOP SIMULATION:\n";
if ($employees && mysqli_num_rows($employees) > 0) {
    mysqli_data_seek($employees, 0); // Reset pointer
    
    $found = false;
    while ($employee = $employees->fetch_assoc()) {
        if ($employee['employee_id'] == $testEmployeeId) {
            $found = true;
            $empId = $employee['employee_id'];
            $existingAttendance = $attendanceData[$empId] ?? null;
            
            echo "✅ Found employee in table loop: {$employee['name']} (ID: $empId)\n";
            
            if ($existingAttendance) {
                $status = $existingAttendance['status'] ?? 'Absent';
                
                $timeIn = '';
                if (!empty($existingAttendance['time_in']) && $existingAttendance['time_in'] !== '00:00:00') {
                    $timeIn = date('H:i', strtotime($existingAttendance['time_in']));
                }
                
                $timeOut = '';
                if (!empty($existingAttendance['time_out']) && $existingAttendance['time_out'] !== '00:00:00') {
                    $timeOut = date('H:i', strtotime($existingAttendance['time_out']));
                }
                
                echo "   Will display: Status=$status, TimeIn=$timeIn, TimeOut=$timeOut\n";
                echo "   ✅ TABLE ROW WILL SHOW CORRECT DATA\n";
            } else {
                echo "   ❌ No attendance data found in loop\n";
            }
            break;
        }
    }
    
    if (!$found) {
        echo "❌ Employee not found in table loop\n";
    }
} else {
    echo "❌ No employees found for table\n";
}

echo "\n=== TEST COMPLETE ===\n";
?>
