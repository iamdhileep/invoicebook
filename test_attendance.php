<?php
session_start();
$_SESSION['admin'] = ['id' => 1, 'name' => 'Test Admin']; // Set admin session for testing

include 'db.php';

echo "=== ATTENDANCE SYSTEM TEST ===\n\n";

// Test 1: Check if all required tables exist
echo "1. Checking required tables:\n";
$required_tables = ['employees', 'attendance', 'biometric_devices', 'device_sync_status', 'leaves', 'leave_requests'];
foreach($required_tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    $exists = $result && $result->num_rows > 0;
    echo "   - $table: " . ($exists ? "✓ EXISTS" : "✗ MISSING") . "\n";
    
    if ($exists) {
        // Show table structure for critical tables
        if (in_array($table, ['attendance', 'employees'])) {
            $columns = $conn->query("DESCRIBE $table");
            echo "     Columns: ";
            $cols = [];
            while($col = $columns->fetch_assoc()) {
                $cols[] = $col['Field'];
            }
            echo implode(', ', $cols) . "\n";
        }
    }
}

echo "\n2. Testing employee data:\n";
$employees = $conn->query("SELECT COUNT(*) as count FROM employees");
if ($employees && $row = $employees->fetch_assoc()) {
    echo "   - Total employees: " . $row['count'] . "\n";
}

echo "\n3. Testing attendance data:\n";
$attendance = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE attendance_date = CURDATE()");
if ($attendance && $row = $attendance->fetch_assoc()) {
    echo "   - Today's attendance records: " . $row['count'] . "\n";
}

echo "\n4. Testing API endpoints:\n";
$api_files = [
    'save_attendance.php',
    'punch_attendance.php',
    'api/biometric_status_check.php',
    'api/apply_leave.php',
    'pages/attendance/api/biometric_api_test.php'
];

foreach($api_files as $file) {
    $exists = file_exists($file);
    echo "   - $file: " . ($exists ? "✓ EXISTS" : "✗ MISSING") . "\n";
}

echo "\n5. Testing page access:\n";
$pages = [
    'pages/attendance/attendance.php',
    'attendance.php'
];

foreach($pages as $page) {
    $exists = file_exists($page);
    echo "   - $page: " . ($exists ? "✓ EXISTS" : "✗ MISSING") . "\n";
}

echo "\n=== TEST COMPLETE ===\n";
?>
