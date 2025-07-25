<?php
session_start();
include 'db.php';

echo "<h2>🔧 Complete System Diagnostics</h2>";
echo "<style>body{font-family:Arial;padding:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} .warning{color:orange;}</style>";

echo "<div class='info'><h3>1. Current Session Status</h3>";
echo "Session ID: " . session_id() . "<br>";
echo "Session admin: " . (isset($_SESSION['admin']) ? $_SESSION['admin'] : 'NOT SET') . "<br>";
echo "Session user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT SET') . "<br>";
echo "</div>";

echo "<div class='info'><h3>2. File Accessibility Test</h3>";
$critical_files = [
    'login.php' => 'Login page',
    'index.php' => 'Main entry point',
    'dashboard.php' => 'Dashboard redirect',
    'pages/dashboard/dashboard.php' => 'Main dashboard',
    'advanced_attendance.php' => 'Advanced attendance'
];

foreach ($critical_files as $file => $desc) {
    if (file_exists($file)) {
        echo "<div class='success'>✅ $desc ($file) - EXISTS</div>";
    } else {
        echo "<div class='error'>❌ $desc ($file) - MISSING</div>";
    }
}
echo "</div>";

echo "<div class='info'><h3>3. Database Connection Test</h3>";
if ($conn) {
    echo "<div class='success'>✅ Database connected</div>";
    
    // Test admin table
    $admin_test = $conn->query("SELECT COUNT(*) as count FROM admin");
    if ($admin_test) {
        $admin_count = $admin_test->fetch_assoc()['count'];
        echo "<div class='success'>✅ Admin table accessible - $admin_count records</div>";
    } else {
        echo "<div class='error'>❌ Admin table error: " . $conn->error . "</div>";
    }
    
    // Test employees table
    $emp_test = $conn->query("SELECT COUNT(*) as count FROM employees");
    if ($emp_test) {
        $emp_count = $emp_test->fetch_assoc()['count'];
        echo "<div class='success'>✅ Employees table accessible - $emp_count records</div>";
    } else {
        echo "<div class='error'>❌ Employees table error: " . $conn->error . "</div>";
    }
} else {
    echo "<div class='error'>❌ Database connection failed</div>";
}
echo "</div>";

echo "<div class='info'><h3>4. Login Process Simulation</h3>";
// Simulate login
$username = 'admin';
$password = 'admin123';

$query = "SELECT * FROM admin WHERE username='$username' AND password='$password'";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) === 1) {
    echo "<div class='success'>✅ Login credentials valid</div>";
    
    // Set session
    $_SESSION['admin'] = $username;
    echo "<div class='success'>✅ Session set: \$_SESSION['admin'] = '$username'</div>";
    
    // Test auth checks
    if (isset($_SESSION['admin'])) {
        echo "<div class='success'>✅ Auth check would PASS for index.php</div>";
        echo "<div class='success'>✅ Auth check would PASS for dashboard.php</div>";
        echo "<div class='success'>✅ Auth check would PASS for advanced_attendance.php</div>";
    }
} else {
    echo "<div class='error'>❌ Login credentials invalid</div>";
}
echo "</div>";

echo "<div class='info'><h3>5. Advanced Attendance Test</h3>";
if (file_exists('advanced_attendance.php')) {
    // Test AJAX endpoint
    echo "<button onclick='testAjax()' style='background:#007bff;color:white;padding:8px 16px;border:none;border-radius:4px;'>Test AJAX Endpoint</button>";
    echo "<div id='ajax-result' style='margin-top:10px;padding:10px;background:#f8f9fa;border-radius:4px;'></div>";
} else {
    echo "<div class='error'>❌ Advanced attendance file not found</div>";
}
echo "</div>";

echo "<script>
async function testAjax() {
    const resultDiv = document.getElementById('ajax-result');
    resultDiv.innerHTML = '⏳ Testing AJAX...';
    
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
        
        if (!response.ok) {
            throw new Error('HTTP ' + response.status + ': ' + response.statusText);
        }
        
        const result = await response.json();
        resultDiv.innerHTML = '<strong>✅ AJAX Response:</strong><br><pre>' + JSON.stringify(result, null, 2) + '</pre>';
    } catch (error) {
        resultDiv.innerHTML = '<strong>❌ AJAX Error:</strong><br>' + error.message;
    }
}
</script>";

echo "<br><h3>🧪 Quick Tests</h3>";
echo "<a href='login.php' target='_blank' style='background:#007bff;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;margin:5px;'>🔑 Test Login Page</a>";
echo "<a href='index.php' target='_blank' style='background:#28a745;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;margin:5px;'>🏠 Test Index Page</a>";
echo "<a href='advanced_attendance.php' target='_blank' style='background:#6f42c1;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;margin:5px;'>⏰ Test Attendance Page</a>";

echo "<br><br><h3>📋 What to Check:</h3>";
echo "<ol>";
echo "<li><strong>Login Page:</strong> Can you see the login form?</li>";
echo "<li><strong>Login Process:</strong> Does admin/admin123 work?</li>";
echo "<li><strong>Dashboard Access:</strong> After login, do you reach the dashboard?</li>";
echo "<li><strong>Attendance Page:</strong> Can you access advanced_attendance.php?</li>";
echo "<li><strong>Punch Buttons:</strong> Do punch in/out buttons respond?</li>";
echo "</ol>";

echo "<br><p><strong>If something specific is not working, please tell me:</strong></p>";
echo "<ul>";
echo "<li>What page are you trying to access?</li>";
echo "<li>What error message do you see?</li>";
echo "<li>At what step does it fail?</li>";
echo "</ul>";
?>
