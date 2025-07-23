<?php
// Database Structure Debug Script
// This script checks the actual structure of your attendance and employees tables

include 'db.php';

echo "<h2>Database Structure Debug Report</h2>";
echo "<div style='font-family: Arial, sans-serif; max-width: 900px; margin: 20px;'>";

// Check attendance table structure
echo "<h3>📊 Attendance Table Structure:</h3>";
$attendanceColumns = $conn->query("SHOW COLUMNS FROM attendance");

if ($attendanceColumns) {
    echo "<table style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
    echo "<tr style='background: #f5f5f5;'>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Field</th>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Type</th>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Null</th>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Key</th>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Default</th>";
    echo "</tr>";
    
    $attendanceFields = [];
    while ($column = $attendanceColumns->fetch_assoc()) {
        $attendanceFields[] = $column['Field'];
        echo "<tr>";
        echo "<td style='border: 1px solid #ddd; padding: 8px; font-weight: bold;'>" . $column['Field'] . "</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . $column['Type'] . "</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . $column['Null'] . "</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . $column['Key'] . "</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . ($column['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>❌ Could not retrieve attendance table structure: " . $conn->error . "</p>";
}

// Check employees table structure
echo "<h3>👥 Employees Table Structure:</h3>";
$employeesColumns = $conn->query("SHOW COLUMNS FROM employees");

if ($employeesColumns) {
    echo "<table style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
    echo "<tr style='background: #f5f5f5;'>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Field</th>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Type</th>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Null</th>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Key</th>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Default</th>";
    echo "</tr>";
    
    $employeeFields = [];
    while ($column = $employeesColumns->fetch_assoc()) {
        $employeeFields[] = $column['Field'];
        echo "<tr>";
        echo "<td style='border: 1px solid #ddd; padding: 8px; font-weight: bold;'>" . $column['Field'] . "</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . $column['Type'] . "</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . $column['Null'] . "</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . $column['Key'] . "</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . ($column['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>❌ Could not retrieve employees table structure: " . $conn->error . "</p>";
}

// Check for expected fields
echo "<h3>🔍 Field Analysis:</h3>";

$expectedAttendanceFields = ['id', 'attendance_id', 'employee_id', 'attendance_date', 'time_in', 'time_out', 'status'];
$expectedEmployeeFields = ['employee_id', 'name', 'employee_code', 'position', 'photo'];

echo "<h4>Attendance Table Expected vs Actual Fields:</h4>";
echo "<ul>";
foreach ($expectedAttendanceFields as $field) {
    $status = in_array($field, $attendanceFields ?? []) ? "✅ Found" : "❌ Missing";
    $color = in_array($field, $attendanceFields ?? []) ? "green" : "red";
    echo "<li style='color: $color;'><strong>$field</strong>: $status</li>";
}
echo "</ul>";

echo "<h4>Employees Table Expected vs Actual Fields:</h4>";
echo "<ul>";
foreach ($expectedEmployeeFields as $field) {
    $status = in_array($field, $employeeFields ?? []) ? "✅ Found" : "❌ Missing";
    $color = in_array($field, $employeeFields ?? []) ? "green" : "red";
    echo "<li style='color: $color;'><strong>$field</strong>: $status</li>";
}
echo "</ul>";

// Test the actual query that was causing issues
echo "<h3>🧪 Query Test:</h3>";

$testQuery = "
    SELECT a.*, 
           e.name as employee_name, 
           COALESCE(e.employee_code, '') as employee_code, 
           COALESCE(e.position, '') as position, 
           COALESCE(e.photo, '') as photo,
           COALESCE(a.id, a.attendance_id, 0) as attendance_id
    FROM attendance a 
    JOIN employees e ON a.employee_id = e.employee_id 
    WHERE DATE_FORMAT(a.attendance_date, '%Y-%m') = '" . date('Y-m') . "'
    LIMIT 5
";

echo "<p><strong>Test Query:</strong></p>";
echo "<code style='background: #f5f5f5; padding: 10px; display: block; white-space: pre;'>$testQuery</code>";

$testResult = $conn->query($testQuery);

if ($testResult) {
    echo "<p style='color: green;'>✅ Query executed successfully</p>";
    echo "<p><strong>Sample Results:</strong></p>";
    
    if ($testResult->num_rows > 0) {
        echo "<table style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
        echo "<tr style='background: #f5f5f5;'>";
        
        // Get field names
        $fields = $testResult->fetch_fields();
        foreach ($fields as $field) {
            echo "<th style='border: 1px solid #ddd; padding: 8px;'>" . $field->name . "</th>";
        }
        echo "</tr>";
        
        // Show sample data
        $rowCount = 0;
        while ($row = $testResult->fetch_assoc() && $rowCount < 3) {
            echo "<tr>";
            foreach ($row as $key => $value) {
                echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
            $rowCount++;
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>⚠️ Query returned no results (no attendance data for current month)</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Query failed: " . $conn->error . "</p>";
}

// Recommendations
echo "<h3>💡 Recommendations:</h3>";

echo "<div style='background: #e7f3ff; border: 1px solid #b3d9ff; padding: 15px; border-radius: 5px;'>";
echo "<h4>🔧 Potential Solutions:</h4>";
echo "<ol>";

if (!in_array('employee_code', $employeeFields ?? [])) {
    echo "<li><strong>Add employee_code column:</strong><br>";
    echo "<code>ALTER TABLE employees ADD COLUMN employee_code VARCHAR(50) DEFAULT NULL;</code></li>";
}

if (!in_array('position', $employeeFields ?? [])) {
    echo "<li><strong>Add position column:</strong><br>";
    echo "<code>ALTER TABLE employees ADD COLUMN position VARCHAR(100) DEFAULT NULL;</code></li>";
}

if (!in_array('photo', $employeeFields ?? [])) {
    echo "<li><strong>Add photo column:</strong><br>";
    echo "<code>ALTER TABLE employees ADD COLUMN photo VARCHAR(255) DEFAULT NULL;</code></li>";
}

if (!in_array('id', $attendanceFields ?? []) && !in_array('attendance_id', $attendanceFields ?? [])) {
    echo "<li><strong>Add primary key to attendance:</strong><br>";
    echo "<code>ALTER TABLE attendance ADD COLUMN id INT AUTO_INCREMENT PRIMARY KEY FIRST;</code></li>";
}

echo "<li><strong>Update attendance-calendar.php</strong> with proper field handling (already done)</li>";
echo "</ol>";
echo "</div>";

// Show current PHP error configuration
echo "<h3>⚙️ PHP Configuration:</h3>";
echo "<ul>";
echo "<li><strong>Error Reporting:</strong> " . (error_reporting() ? "Enabled" : "Disabled") . "</li>";
echo "<li><strong>Display Errors:</strong> " . (ini_get('display_errors') ? "On" : "Off") . "</li>";
echo "<li><strong>Log Errors:</strong> " . (ini_get('log_errors') ? "On" : "Off") . "</li>";
echo "</ul>";

echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin-top: 20px;'>";
echo "<h4>⚠️ To Fix the Errors:</h4>";
echo "<ol>";
echo "<li>Run the SQL commands above to add missing columns</li>";
echo "<li>The attendance-calendar.php has been updated with proper error handling</li>";
echo "<li>Consider turning off PHP error display in production</li>";
echo "<li>Check if your attendance table has the correct primary key</li>";
echo "</ol>";
echo "</div>";

echo "</div>";

$conn->close();
?> 