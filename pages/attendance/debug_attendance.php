<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';

echo "<h2>Attendance Form Debug Test</h2>";
echo "<p>This page will help debug attendance form submission issues.</p>";

// Test database connection
echo "<h3>1. Database Connection Test</h3>";
if ($conn) {
    echo "✅ Database connection successful<br>";
    echo "Database charset: " . $conn->character_set_name() . "<br><br>";
} else {
    echo "❌ Database connection failed: " . mysqli_connect_error() . "<br><br>";
}

// Test attendance table structure
echo "<h3>2. Attendance Table Structure</h3>";
try {
    $result = $conn->query("DESCRIBE attendance");
    if ($result) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . $row['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table><br>";
    } else {
        echo "❌ Cannot describe attendance table: " . $conn->error . "<br><br>";
    }
} catch (Exception $e) {
    echo "❌ Error checking table structure: " . $e->getMessage() . "<br><br>";
}

// Test employees table
echo "<h3>3. Employees Table Test</h3>";
try {
    $result = $conn->query("SELECT COUNT(*) as count FROM employees WHERE status = 'active'");
    if ($result && $row = $result->fetch_assoc()) {
        echo "✅ Found " . $row['count'] . " active employees<br><br>";
    } else {
        echo "❌ Cannot count employees: " . $conn->error . "<br><br>";
    }
} catch (Exception $e) {
    echo "❌ Error checking employees: " . $e->getMessage() . "<br><br>";
}

// Test attendance data for today
echo "<h3>4. Today's Attendance Data</h3>";
$today = date('Y-m-d');
try {
    $result = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE attendance_date = '$today'");
    if ($result && $row = $result->fetch_assoc()) {
        echo "✅ Found " . $row['count'] . " attendance records for today ($today)<br>";
    } else {
        echo "❌ Cannot count attendance records: " . $conn->error . "<br>";
    }
    
    // Show some sample data if it exists
    $result = $conn->query("SELECT a.*, e.name FROM attendance a JOIN employees e ON a.employee_id = e.employee_id WHERE a.attendance_date = '$today' LIMIT 5");
    if ($result && $result->num_rows > 0) {
        echo "<h4>Sample Records:</h4>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Employee</th><th>Status</th><th>Time In</th><th>Time Out</th><th>Notes</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['name']) . "</td>";
            echo "<td>" . $row['status'] . "</td>";
            echo "<td>" . ($row['time_in'] ?? 'Not set') . "</td>";
            echo "<td>" . ($row['time_out'] ?? 'Not set') . "</td>";
            echo "<td>" . htmlspecialchars($row['notes'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "<br>";
} catch (Exception $e) {
    echo "❌ Error checking attendance data: " . $e->getMessage() . "<br><br>";
}

// Test POST simulation
echo "<h3>5. Form POST Test</h3>";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    echo "<h4>POST Data Received:</h4>";
    echo "<pre>" . print_r($_POST, true) . "</pre>";
    
    echo "<h4>Processing Test:</h4>";
    $employeeIds = $_POST['employee_id'] ?? [];
    $statuses = $_POST['status'] ?? [];
    $timeIns = $_POST['time_in'] ?? [];
    $timeOuts = $_POST['time_out'] ?? [];
    $notes = $_POST['notes'] ?? [];
    $date = $_POST['attendance_date'] ?? date('Y-m-d');
    
    echo "Date: $date<br>";
    echo "Employee IDs: " . implode(', ', $employeeIds) . "<br>";
    echo "Status count: " . count($statuses) . "<br>";
    echo "Time In count: " . count($timeIns) . "<br>";
    echo "Time Out count: " . count($timeOuts) . "<br>";
    echo "Notes count: " . count($notes) . "<br><br>";
    
    if (!empty($employeeIds) && !empty($statuses)) {
        echo "✅ Form data structure looks correct!<br>";
    } else {
        echo "❌ Form data structure has issues!<br>";
    }
} else {
    echo "<form method='POST'>";
    echo "<input type='hidden' name='attendance_date' value='" . date('Y-m-d') . "'>";
    echo "<input type='hidden' name='employee_id[]' value='1'>";
    echo "<input type='hidden' name='employee_id[]' value='2'>";
    echo "<select name='status[1]'><option value='Present' selected>Present</option><option value='Absent'>Absent</option></select><br>";
    echo "<select name='status[2]'><option value='Absent' selected>Absent</option><option value='Present'>Present</option></select><br>";
    echo "<input type='time' name='time_in[1]' value='09:00'><br>";
    echo "<input type='time' name='time_in[2]' value='09:15'><br>";
    echo "<input type='time' name='time_out[1]' value='18:00'><br>";
    echo "<input type='time' name='time_out[2]' value=''><br>";
    echo "<input type='text' name='notes[1]' value='On time' placeholder='Notes for employee 1'><br>";
    echo "<input type='text' name='notes[2]' value='' placeholder='Notes for employee 2'><br>";
    echo "<button type='submit'>Test Form Submission</button>";
    echo "</form>";
}

echo "<hr>";
echo "<p><a href='attendance.php'>← Back to Attendance Page</a></p>";
?>
