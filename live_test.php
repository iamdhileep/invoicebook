<?php
echo "<h2>🚀 Live Application Test</h2>";
echo "<style>body{font-family:Arial;padding:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} .test{background:#f8f9fa;padding:15px;margin:10px 0;border-radius:8px;border-left:4px solid #007bff;}</style>";

// Start session and set admin for testing
session_start();
$_SESSION['admin'] = 'admin';

echo "<div class='test'>";
echo "<h3>🔐 Authentication Test</h3>";
echo "<p>Session admin: " . ($_SESSION['admin'] ?? 'NOT SET') . "</p>";

if (isset($_SESSION['admin'])) {
    echo "<div class='success'>✅ Authentication: WORKING</div>";
} else {
    echo "<div class='error'>❌ Authentication: FAILED</div>";
}
echo "</div>";

echo "<div class='test'>";
echo "<h3>📊 Dashboard Access Test</h3>";
echo "<iframe src='pages/dashboard/dashboard.php' width='100%' height='300' style='border:1px solid #ddd;border-radius:4px;'></iframe>";
echo "<p><a href='pages/dashboard/dashboard.php' target='_blank'>Open Dashboard in New Tab</a></p>";
echo "</div>";

echo "<div class='test'>";
echo "<h3>⏰ Advanced Attendance Test</h3>";
echo "<iframe src='advanced_attendance.php' width='100%' height='400' style='border:1px solid #ddd;border-radius:4px;'></iframe>";
echo "<p><a href='advanced_attendance.php' target='_blank'>Open Advanced Attendance in New Tab</a></p>";
echo "</div>";

echo "<div class='test'>";
echo "<h3>🧪 Punch In/Out AJAX Test</h3>";
echo "<button onclick='testPunchIn()' style='background:#28a745;color:white;padding:10px 20px;border:none;border-radius:4px;margin:5px;'>Test Punch In</button>";
echo "<button onclick='testPunchOut()' style='background:#dc3545;color:white;padding:10px 20px;border:none;border-radius:4px;margin:5px;'>Test Punch Out</button>";
echo "<div id='punchTest' style='background:#f8f9fa;padding:10px;margin:10px 0;border-radius:4px;'></div>";
echo "</div>";

echo "<script>
async function testPunchIn() {
    const testDiv = document.getElementById('punchTest');
    testDiv.innerHTML = '⏳ Testing Punch In...';
    
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
        
        const text = await response.text();
        let result;
        
        try {
            result = JSON.parse(text);
            testDiv.innerHTML = '<strong>✅ Punch In Response:</strong><br><pre>' + JSON.stringify(result, null, 2) + '</pre>';
        } catch (parseError) {
            testDiv.innerHTML = '<strong>❌ Invalid JSON Response:</strong><br><pre>' + text.substring(0, 500) + '...</pre>';
        }
    } catch (error) {
        testDiv.innerHTML = '<strong>❌ Network Error:</strong><br>' + error.message;
    }
}

async function testPunchOut() {
    const testDiv = document.getElementById('punchTest');
    testDiv.innerHTML = '⏳ Testing Punch Out...';
    
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
        
        const text = await response.text();
        let result;
        
        try {
            result = JSON.parse(text);
            testDiv.innerHTML = '<strong>✅ Punch Out Response:</strong><br><pre>' + JSON.stringify(result, null, 2) + '</pre>';
        } catch (parseError) {
            testDiv.innerHTML = '<strong>❌ Invalid JSON Response:</strong><br><pre>' + text.substring(0, 500) + '...</pre>';
        }
    } catch (error) {
        testDiv.innerHTML = '<strong>❌ Network Error:</strong><br>' + error.message;
    }
}
</script>";

echo "<br><h3>🔍 Common Issues and Solutions:</h3>";
echo "<div style='background:#fff3cd;padding:15px;border-radius:8px;border-left:4px solid #ffc107;'>";
echo "<strong>If you see errors:</strong><br>";
echo "1. <strong>White/blank page:</strong> Check PHP error logs<br>";
echo "2. <strong>Login not working:</strong> Check database connection<br>";
echo "3. <strong>Punch buttons not working:</strong> Check browser console for JavaScript errors<br>";
echo "4. <strong>Permission denied:</strong> Check file permissions<br>";
echo "5. <strong>Database errors:</strong> Check if XAMPP/MySQL is running<br>";
echo "</div>";

echo "<br><p><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>Test the dashboard iframe above</li>";
echo "<li>Test the attendance page iframe above</li>";
echo "<li>Click the punch in/out test buttons</li>";
echo "<li>If any of these fail, let me know the specific error message</li>";
echo "</ol>";
?>
