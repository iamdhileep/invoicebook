<?php
include 'db.php';

echo "<h2>🔍 Database Structure Analysis</h2>";
echo "<style>body{font-family:Arial;padding:20px;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background-color:#f2f2f2;}</style>";

// Check attendance table structure
echo "<h3>1. Attendance Table Structure</h3>";
$structure_query = $conn->query("DESCRIBE attendance");
if ($structure_query) {
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $structure_query->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td><strong>" . htmlspecialchars($row['Type']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red;'>❌ Error describing attendance table: " . $conn->error . "</p>";
}

// Check current attendance data
echo "<h3>2. Current Attendance Data (Today)</h3>";
$today = date('Y-m-d');
$data_query = $conn->query("SELECT * FROM attendance WHERE attendance_date = '$today'");
if ($data_query && $data_query->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Employee ID</th><th>Date</th><th>Time In</th><th>Time Out</th><th>Status</th></tr>";
    while ($row = $data_query->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($row['employee_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['attendance_date']) . "</td>";
        echo "<td><strong>" . htmlspecialchars($row['time_in'] ?? 'NULL') . "</strong></td>";
        echo "<td><strong>" . htmlspecialchars($row['time_out'] ?? 'NULL') . "</strong></td>";
        echo "<td>" . htmlspecialchars($row['status'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:orange;'>⚠️ No attendance records found for today ($today)</p>";
}

// Test different time formats
echo "<h3>3. Time Format Testing</h3>";
$current_time = date('Y-m-d H:i:s');
$time_only = date('H:i:s');
$datetime_obj = new DateTime();

echo "<table>";
echo "<tr><th>Format</th><th>Value</th><th>Description</th></tr>";
echo "<tr><td>Full DateTime</td><td><strong>$current_time</strong></td><td>What debug script uses</td></tr>";
echo "<tr><td>Time Only</td><td><strong>$time_only</strong></td><td>What new script tries to use</td></tr>";
echo "<tr><td>DateTime Object</td><td><strong>" . $datetime_obj->format('Y-m-d H:i:s') . "</strong></td><td>Alternative approach</td></tr>";
echo "</table>";

// Test which format works with the database
echo "<h3>4. Database Compatibility Test</h3>";
$test_employee_id = 23;

// Try full datetime format (what debug script uses)
echo "<h4>Testing Full DateTime Format:</h4>";
try {
    $stmt = $conn->prepare("INSERT INTO attendance (employee_id, attendance_date, time_in, status) VALUES (?, ?, ?, 'Test') ON DUPLICATE KEY UPDATE time_in = VALUES(time_in), status = VALUES(status)");
    $stmt->bind_param('iss', $test_employee_id, $today, $current_time);
    if ($stmt->execute()) {
        echo "<p style='color:green;'>✅ Full DateTime format WORKS</p>";
        
        // Check what was actually stored
        $check = $conn->prepare("SELECT time_in FROM attendance WHERE employee_id = ? AND attendance_date = ?");
        $check->bind_param('is', $test_employee_id, $today);
        $check->execute();
        $result = $check->get_result()->fetch_assoc();
        echo "<p>Stored value: <strong>" . ($result['time_in'] ?? 'NULL') . "</strong></p>";
    } else {
        echo "<p style='color:red;'>❌ Full DateTime format FAILED: " . $conn->error . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Exception with Full DateTime: " . $e->getMessage() . "</p>";
}

// Clean up test data
$conn->query("DELETE FROM attendance WHERE employee_id = $test_employee_id AND status = 'Test'");

// Try time only format (what new script tries to use)
echo "<h4>Testing Time Only Format:</h4>";
try {
    $stmt = $conn->prepare("INSERT INTO attendance (employee_id, attendance_date, time_in, status) VALUES (?, ?, ?, 'Test') ON DUPLICATE KEY UPDATE time_in = VALUES(time_in), status = VALUES(status)");
    $stmt->bind_param('iss', $test_employee_id, $today, $time_only);
    if ($stmt->execute()) {
        echo "<p style='color:green;'>✅ Time Only format WORKS</p>";
        
        // Check what was actually stored
        $check = $conn->prepare("SELECT time_in FROM attendance WHERE employee_id = ? AND attendance_date = ?");
        $check->bind_param('is', $test_employee_id, $today);
        $check->execute();
        $result = $check->get_result()->fetch_assoc();
        echo "<p>Stored value: <strong>" . ($result['time_in'] ?? 'NULL') . "</strong></p>";
    } else {
        echo "<p style='color:red;'>❌ Time Only format FAILED: " . $conn->error . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Exception with Time Only: " . $e->getMessage() . "</p>";
}

// Clean up test data
$conn->query("DELETE FROM attendance WHERE employee_id = $test_employee_id AND status = 'Test'");

echo "<h3>5. Recommendations</h3>";
echo "<ul>";
echo "<li>If both formats work, the database column is likely <strong>DATETIME</strong> or <strong>TIMESTAMP</strong></li>";
echo "<li>If only full datetime works, use <code>date('Y-m-d H:i:s')</code></li>";
echo "<li>If only time works, use <code>date('H:i:s')</code></li>";
echo "<li>Check which format the debug script uses vs the new advanced_attendance.php</li>";
echo "</ul>";
?>
