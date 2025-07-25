<?php
// Simple test for attendance functionality
session_start();
if (!isset($_SESSION['admin'])) {
    echo "Session not set. Admin must be logged in.";
    exit;
}

include 'db.php';

echo "<h3>Attendance System Test</h3>";

// Test database connection
if ($conn) {
    echo "✅ Database connected successfully<br>";
} else {
    echo "❌ Database connection failed<br>";
    exit;
}

// Test attendance table structure
$result = $conn->query("DESCRIBE attendance");
if ($result) {
    echo "✅ Attendance table exists<br>";
    echo "<h4>Table Structure:</h4>";
    while ($row = $result->fetch_assoc()) {
        echo "- {$row['Field']} ({$row['Type']})<br>";
    }
} else {
    echo "❌ Attendance table not found<br>";
}

// Test employees table
$result = $conn->query("SELECT COUNT(*) as count FROM employees");
if ($result) {
    $row = $result->fetch_assoc();
    echo "✅ Found {$row['count']} employees in database<br>";
} else {
    echo "❌ Could not count employees<br>";
}

// Test today's attendance
$today = date('Y-m-d');
$result = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE attendance_date = '$today'");
if ($result) {
    $row = $result->fetch_assoc();
    echo "✅ Found {$row['count']} attendance records for today ($today)<br>";
} else {
    echo "❌ Could not count today's attendance<br>";
}

// Test sample insert
echo "<h4>Testing Sample Operations:</h4>";

// Find first employee
$result = $conn->query("SELECT employee_id, name FROM employees LIMIT 1");
if ($result && $row = $result->fetch_assoc()) {
    $emp_id = $row['employee_id'];
    $emp_name = $row['name'];
    echo "Testing with employee: {$emp_name} (ID: {$emp_id})<br>";
    
    // Check if already has attendance today
    $check = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?");
    $check->bind_param('is', $emp_id, $today);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    
    if ($existing) {
        echo "- Employee already has attendance record for today<br>";
        echo "- Time in: " . ($existing['time_in'] ?: 'Not set') . "<br>";
        echo "- Time out: " . ($existing['time_out'] ?: 'Not set') . "<br>";
        echo "- Status: " . ($existing['status'] ?: 'Not set') . "<br>";
    } else {
        echo "- No attendance record for today<br>";
    }
} else {
    echo "❌ No employees found in database<br>";
}

// Test JSON parsing
echo "<h4>Testing JSON Input Parsing:</h4>";
$test_json = '{"action": "punch_in", "employee_id": 1, "attendance_date": "' . $today . '"}';
$parsed = json_decode($test_json, true);
if ($parsed) {
    echo "✅ JSON parsing works<br>";
    echo "- Action: " . $parsed['action'] . "<br>";
    echo "- Employee ID: " . $parsed['employee_id'] . "<br>";
    echo "- Date: " . $parsed['attendance_date'] . "<br>";
} else {
    echo "❌ JSON parsing failed<br>";
}

echo "<hr>";
echo "<p><strong>If all tests pass, the attendance system should work correctly.</strong></p>";
echo "<p><a href='advanced_attendance.php'>Back to Attendance System</a></p>";
?>
