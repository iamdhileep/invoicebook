<?php
// Test the exact same logic as attendance.php page
include 'db.php';

echo "=== ATTENDANCE.PHP PAGE LOGIC TEST ===\n\n";

$today = date('Y-m-d');
echo "Testing Date: $today\n\n";

// Exact same employee query as attendance.php
try {
    $employees = $conn->query("SELECT employee_id, name, employee_code, position FROM employees WHERE status = 'active' ORDER BY name ASC");
    if (!$employees) {
        throw new Exception("Failed to fetch employees: " . $conn->error);
    }
    
    $employeeCount = mysqli_num_rows($employees);
    echo "📊 Employee count: $employeeCount\n\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit;
}

// Exact same attendance query as attendance.php
$attendanceData = [];
try {
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
    
    echo "📊 Attendance data count: " . count($attendanceData) . "\n\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

// Test the display logic exactly as in attendance.php
echo "TABLE DISPLAY SIMULATION:\n";
echo "========================\n\n";

// Check condition: $employees && mysqli_num_rows($employees) > 0
$canShowTable = $employees && mysqli_num_rows($employees) > 0;
echo "Can show table: " . ($canShowTable ? "YES" : "NO") . "\n\n";

if ($canShowTable) {
    // Reset pointer exactly as fixed in attendance.php
    mysqli_data_seek($employees, 0);
    echo "🔄 Reset employees pointer to beginning\n\n";
    
    $displayCount = 0;
    while ($employee = $employees->fetch_assoc()) {
        $displayCount++;
        
        // Get attendance data for this employee (using pre-fetched data)
        $empId = $employee['employee_id'];
        $existingAttendance = $attendanceData[$empId] ?? null;
        
        echo "Row $displayCount: {$employee['name']} (ID: $empId)\n";
        
        if ($existingAttendance) {
            // Set default values with proper null/empty handling
            $status = $existingAttendance['status'] ?? 'Absent';
            
            // Handle time fields - convert empty strings to empty for display
            $timeIn = '';
            if (!empty($existingAttendance['time_in']) && $existingAttendance['time_in'] !== '00:00:00') {
                $timeIn = date('H:i', strtotime($existingAttendance['time_in']));
            }
            
            $timeOut = '';
            if (!empty($existingAttendance['time_out']) && $existingAttendance['time_out'] !== '00:00:00') {
                $timeOut = date('H:i', strtotime($existingAttendance['time_out']));
            }
            
            $notes = $existingAttendance['notes'] ?? '';
            
            echo "  📋 WILL DISPLAY IN TABLE:\n";
            echo "     Status: $status\n";
            echo "     Time In: " . ($timeIn ? $timeIn : 'Empty') . " (Raw: {$existingAttendance['time_in']})\n";
            echo "     Time Out: " . ($timeOut ? $timeOut : 'Empty') . " (Raw: {$existingAttendance['time_out']})\n";
            echo "     Notes: " . ($notes ? $notes : 'Empty') . "\n";
        } else {
            echo "  📋 WILL DISPLAY IN TABLE:\n";
            echo "     Status: Absent\n";
            echo "     Time In: Empty\n";
            echo "     Time Out: Empty\n";
            echo "     Notes: Empty\n";
        }
        echo "\n";
    }
    
    echo "📊 Total rows that will display: $displayCount\n";
} else {
    echo "❌ Table will not display - no employees found\n";
}

echo "\n=== TEST COMPLETE ===\n";
?>
