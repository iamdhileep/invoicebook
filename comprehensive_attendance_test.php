<?php
session_start();
include 'db.php';

echo "=== COMPREHENSIVE ATTENDANCE SYSTEM TEST ===\n\n";

$today = date('Y-m-d');
echo "Testing Date: $today\n\n";

// Test 1: Check if employees exist
echo "1. ACTIVE EMPLOYEES TEST:\n";
$employees = $conn->query("SELECT employee_id, name, employee_code FROM employees WHERE status = 'active' ORDER BY name ASC");
if ($employees && mysqli_num_rows($employees) > 0) {
    $count = 0;
    while ($emp = $employees->fetch_assoc()) {
        $count++;
        echo "   ✅ Employee {$count}: ID={$emp['employee_id']}, Name={$emp['name']}, Code={$emp['employee_code']}\n";
    }
    echo "   📊 Total Active Employees: $count\n\n";
} else {
    echo "   ❌ NO ACTIVE EMPLOYEES FOUND!\n\n";
}

// Test 2: Check attendance data
echo "2. ATTENDANCE DATA TEST:\n";
$attendance = $conn->query("SELECT employee_id, status, time_in, time_out, notes FROM attendance WHERE attendance_date = '$today' ORDER BY employee_id");
if ($attendance && mysqli_num_rows($attendance) > 0) {
    $count = 0;
    while ($att = $attendance->fetch_assoc()) {
        $count++;
        $timeInDisplay = (!empty($att['time_in']) && $att['time_in'] !== '00:00:00') ? date('H:i', strtotime($att['time_in'])) : 'Not Set';
        $timeOutDisplay = (!empty($att['time_out']) && $att['time_out'] !== '00:00:00') ? date('H:i', strtotime($att['time_out'])) : 'Not Set';
        
        echo "   ✅ Record {$count}: Employee {$att['employee_id']}\n";
        echo "      Status: {$att['status']}\n";
        echo "      Time In: {$timeInDisplay} (Raw: {$att['time_in']})\n";
        echo "      Time Out: {$timeOutDisplay} (Raw: {$att['time_out']})\n";
        echo "      Notes: " . (!empty($att['notes']) ? $att['notes'] : 'None') . "\n\n";
    }
    echo "   📊 Total Attendance Records: $count\n\n";
} else {
    echo "   ⚠️  NO ATTENDANCE RECORDS FOR TODAY\n\n";
}

// Test 3: Simulate the page logic
echo "3. PAGE LOGIC SIMULATION:\n";
$employees = $conn->query("SELECT employee_id, name, employee_code FROM employees WHERE status = 'active' ORDER BY name ASC");
$attendanceData = [];

// Pre-fetch attendance data like the page does
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

echo "   📊 Pre-fetched Attendance Data Count: " . count($attendanceData) . "\n\n";

if ($employees && mysqli_num_rows($employees) > 0) {
    echo "   EMPLOYEE-BY-EMPLOYEE DISPLAY TEST:\n";
    $displayCount = 0;
    while ($employee = $employees->fetch_assoc()) {
        $displayCount++;
        $empId = $employee['employee_id'];
        $existingAttendance = $attendanceData[$empId] ?? null;
        
        echo "   Employee {$displayCount}: {$employee['name']} (ID: $empId)\n";
        
        if ($existingAttendance) {
            $status = $existingAttendance['status'];
            $timeIn = (!empty($existingAttendance['time_in']) && $existingAttendance['time_in'] !== '00:00:00') 
                     ? date('H:i', strtotime($existingAttendance['time_in'])) : '';
            $timeOut = (!empty($existingAttendance['time_out']) && $existingAttendance['time_out'] !== '00:00:00') 
                      ? date('H:i', strtotime($existingAttendance['time_out'])) : '';
            
            echo "      ✅ HAS DATA - Status: $status, In: " . ($timeIn ?: 'Empty') . ", Out: " . ($timeOut ?: 'Empty') . "\n";
            echo "      📋 Will display: Status=$status, TimeIn=$timeIn, TimeOut=$timeOut\n";
        } else {
            echo "      ❌ NO DATA - Will display: Status=Absent, TimeIn=Empty, TimeOut=Empty\n";
        }
        echo "\n";
    }
    echo "   📊 Total Employees Processed for Display: $displayCount\n\n";
}

// Test 4: Check table structure
echo "4. DATABASE STRUCTURE TEST:\n";
$structure = $conn->query("DESCRIBE attendance");
if ($structure) {
    echo "   ✅ Attendance table structure:\n";
    while ($col = $structure->fetch_assoc()) {
        echo "      {$col['Field']} ({$col['Type']}) - {$col['Key']}\n";
    }
} else {
    echo "   ❌ Could not get table structure: " . $conn->error . "\n";
}

echo "\n=== TEST COMPLETE ===\n";
?>
