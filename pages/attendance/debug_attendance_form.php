<?php
session_start();
$_SESSION['admin'] = true; // Set session for testing
include '../../db.php';

// Check attendance table structure
echo "<h2>🔍 Attendance System Debug Report</h2>";

echo "<h3>1. Database Connection Test</h3>";
if ($conn) {
    echo "✅ Database connection successful<br>";
} else {
    echo "❌ Database connection failed: " . mysqli_connect_error() . "<br>";
    exit;
}

echo "<h3>2. Attendance Table Structure</h3>";
$result = $conn->query("DESCRIBE attendance");
if ($result) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ Error checking attendance table: " . $conn->error . "<br>";
}

echo "<h3>3. Sample Data Check</h3>";
$result = $conn->query("SELECT COUNT(*) as count FROM attendance");
if ($result) {
    $row = $result->fetch_assoc();
    echo "📊 Total attendance records: " . $row['count'] . "<br>";
} else {
    echo "❌ Error counting attendance records: " . $conn->error . "<br>";
}

echo "<h3>4. Employees Table Check</h3>";
$result = $conn->query("SELECT COUNT(*) as count FROM employees WHERE status = 'active'");
if ($result) {
    $row = $result->fetch_assoc();
    echo "👥 Active employees: " . $row['count'] . "<br>";
    
    if ($row['count'] > 0) {
        $employees = $conn->query("SELECT employee_id, name, employee_code FROM employees WHERE status = 'active' LIMIT 3");
        echo "<br><strong>Sample employees:</strong><br>";
        while ($emp = $employees->fetch_assoc()) {
            echo "ID: " . $emp['employee_id'] . " - " . htmlspecialchars($emp['name']) . " (" . htmlspecialchars($emp['employee_code']) . ")<br>";
        }
    }
} else {
    echo "❌ Error checking employees: " . $conn->error . "<br>";
}

echo "<h3>5. Form Data Test</h3>";
echo "<p>Testing the form structure that should be submitted:</p>";

// Simulate POST data
$testData = [
    'attendance_date' => date('Y-m-d'),
    'status' => ['1' => 'Present', '2' => 'Absent'],  
    'time_in' => ['1' => '09:00', '2' => ''],
    'time_out' => ['1' => '17:00', '2' => ''],
    'notes' => ['1' => 'Test note', '2' => '']
];

echo "<strong>Sample form data structure:</strong><br>";
echo "<pre>" . print_r($testData, true) . "</pre>";

echo "<h3>6. Form Action Test</h3>";
$formAction = "../../save_attendance.php";
$fullPath = realpath($formAction);
echo "Form action: " . htmlspecialchars($formAction) . "<br>";
echo "Full path: " . ($fullPath ? htmlspecialchars($fullPath) : "❌ File not found") . "<br>";
echo "File exists: " . (file_exists($formAction) ? "✅ Yes" : "❌ No") . "<br>";

echo "<h3>7. Test Form Submission</h3>";
echo "<form action='../../save_attendance.php' method='POST' style='border: 1px solid #ccc; padding: 20px; margin: 10px 0;'>";
echo "<input type='hidden' name='attendance_date' value='" . date('Y-m-d') . "'>";
echo "<input type='hidden' name='status[1]' value='Present'>";
echo "<input type='hidden' name='time_in[1]' value='09:00'>";
echo "<input type='hidden' name='time_out[1]' value='17:00'>";
echo "<input type='hidden' name='notes[1]' value='Test submission'>";
echo "<button type='submit' style='background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px;'>🧪 Test Save Attendance</button>";
echo "</form>";

echo "<h3>8. URL Parameters Check</h3>";
if (isset($_GET['success'])) {
    echo "✅ Success parameter detected: " . htmlspecialchars($_GET['success']) . "<br>";
    if (isset($_GET['count'])) {
        echo "📊 Records saved: " . htmlspecialchars($_GET['count']) . "<br>";
    }
}
if (isset($_GET['error'])) {
    echo "❌ Error parameter detected: " . htmlspecialchars($_GET['error']) . "<br>";
    if (isset($_GET['message'])) {
        echo "📝 Error message: " . htmlspecialchars($_GET['message']) . "<br>";
    }
}

echo "<hr>";
echo "<p><a href='../attendance/attendance.php'>← Back to Attendance Page</a></p>";
?>
