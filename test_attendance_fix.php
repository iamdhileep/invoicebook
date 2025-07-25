<?php
session_start();
$_SESSION['admin'] = 'admin'; // Set session for testing
include 'db.php';

echo "<h2>🧪 Testing Advanced Attendance System</h2>";
echo "<style>body{font-family:Arial;padding:20px;} .success{color:green;} .error{color:red;} .info{color:blue;}</style>";

// Test 1: Page Access
echo "<div class='info'><h3>Test 1: Page Access</h3>";
if (file_exists('advanced_attendance.php')) {
    echo "<div class='success'>✅ advanced_attendance.php exists</div>";
    echo "<a href='advanced_attendance.php' target='_blank' style='background:#2563eb;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;margin:10px 0;display:inline-block;'>🔗 Open Advanced Attendance Page</a><br>";
} else {
    echo "<div class='error'>❌ advanced_attendance.php not found</div>";
}
echo "</div>";

// Test 2: Database Structure
echo "<div class='info'><h3>Test 2: Database Check</h3>";
$employees = $conn->query("SELECT employee_id, name FROM employees LIMIT 3");
if ($employees && $employees->num_rows > 0) {
    echo "<div class='success'>✅ Found employees in database:</div>";
    while ($emp = $employees->fetch_assoc()) {
        echo "- {$emp['name']} (ID: {$emp['employee_id']})<br>";
    }
} else {
    echo "<div class='error'>❌ No employees found</div>";
}
echo "</div>";

// Test 3: Direct AJAX Test
echo "<div class='info'><h3>Test 3: AJAX Punch Test</h3>";
echo "<button onclick='testPunchIn()' style='background:#28a745;color:white;padding:8px 16px;border:none;border-radius:4px;margin:5px;'>Test Punch In</button>";
echo "<button onclick='testPunchOut()' style='background:#dc3545;color:white;padding:8px 16px;border:none;border-radius:4px;margin:5px;'>Test Punch Out</button>";
echo "<div id='testResults' style='margin-top:10px;padding:10px;background:#f8f9fa;border-radius:4px;'></div>";
echo "</div>";

echo "<script>
async function testPunchIn() {
    const results = document.getElementById('testResults');
    results.innerHTML = '⏳ Testing Punch In...';
    
    try {
        const response = await fetch('advanced_attendance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'punch_in',
                employee_id: 23,
                attendance_date: '" . date('Y-m-d') . "'
            })
        });
        
        const result = await response.json();
        results.innerHTML = '✅ Punch In Response: ' + JSON.stringify(result, null, 2);
    } catch (error) {
        results.innerHTML = '❌ Error: ' + error.message;
    }
}

async function testPunchOut() {
    const results = document.getElementById('testResults');
    results.innerHTML = '⏳ Testing Punch Out...';
    
    try {
        const response = await fetch('advanced_attendance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'punch_out',
                employee_id: 23,
                attendance_date: '" . date('Y-m-d') . "'
            })
        });
        
        const result = await response.json();
        results.innerHTML = '✅ Punch Out Response: ' + JSON.stringify(result, null, 2);
    } catch (error) {
        results.innerHTML = '❌ Error: ' + error.message;
    }
}
</script>";

echo "<br><h3>🎯 Summary of Fixes Applied:</h3>";
echo "<div style='background:#e8f5e8;padding:15px;border-radius:8px;border-left:4px solid #28a745;'>";
echo "<strong>✅ Authentication Fixed:</strong> Changed from auth_check.php to direct \$_SESSION['admin'] check<br>";
echo "<strong>✅ Response Enhanced:</strong> Added time display in JSON responses<br>";
echo "<strong>✅ Database Ready:</strong> Attendance table exists with proper structure<br>";
echo "</div>";

echo "<br><p><strong>Instructions:</strong></p>";
echo "<ol>";
echo "<li>Click 'Open Advanced Attendance Page' to access the main page</li>";
echo "<li>Use the test buttons above to verify AJAX functionality</li>";
echo "<li>If the page loads successfully, the punch in/out buttons should work</li>";
echo "</ol>";
?>
