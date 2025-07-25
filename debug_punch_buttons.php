<?php
session_start();
$_SESSION['admin'] = 'admin'; // Set session for testing
include 'db.php';

echo "<h2>🔧 Debug Punch Button Issue</h2>";
echo "<style>body{font-family:Arial;padding:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} .debug{background:#f8f9fa;padding:10px;margin:10px 0;border-left:4px solid #17a2b8;}</style>";

// Test AJAX endpoint directly
if ($_POST['test_ajax']) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $employee_id = 23; // Test employee
    $current_time = date('Y-m-d H:i:s');
    $attendance_date = date('Y-m-d');
    
    echo "<div class='debug'>";
    echo "<h4>AJAX Test Results:</h4>";
    echo "Action: $action<br>";
    echo "Employee ID: $employee_id<br>";
    echo "Date: $attendance_date<br>";
    echo "Time: $current_time<br>";
    
    if ($action === 'punch_in') {
        // Test punch in
        $check = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?");
        $check->bind_param('is', $employee_id, $attendance_date);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        
        if ($existing && $existing['time_in']) {
            echo "<div class='error'>❌ Already punched in</div>";
        } else {
            // Insert/update
            if ($existing) {
                $stmt = $conn->prepare("UPDATE attendance SET time_in = ?, status = 'Present' WHERE employee_id = ? AND attendance_date = ?");
                $stmt->bind_param('sis', $current_time, $employee_id, $attendance_date);
            } else {
                $stmt = $conn->prepare("INSERT INTO attendance (employee_id, attendance_date, time_in, status) VALUES (?, ?, ?, 'Present')");
                $stmt->bind_param('iss', $employee_id, $attendance_date, $current_time);
            }
            
            if ($stmt->execute()) {
                echo "<div class='success'>✅ Punch In successful</div>";
            } else {
                echo "<div class='error'>❌ Database error: " . $conn->error . "</div>";
            }
        }
    } elseif ($action === 'punch_out') {
        // Test punch out
        $check = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?");
        $check->bind_param('is', $employee_id, $attendance_date);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        
        if (!$existing || !$existing['time_in']) {
            echo "<div class='error'>❌ Not punched in yet</div>";
        } elseif ($existing['time_out']) {
            echo "<div class='error'>❌ Already punched out</div>";
        } else {
            $stmt = $conn->prepare("UPDATE attendance SET time_out = ? WHERE employee_id = ? AND attendance_date = ?");
            $stmt->bind_param('sis', $current_time, $employee_id, $attendance_date);
            
            if ($stmt->execute()) {
                echo "<div class='success'>✅ Punch Out successful</div>";
            } else {
                echo "<div class='error'>❌ Database error: " . $conn->error . "</div>";
            }
        }
    }
    echo "</div>";
    exit;
}

echo "<div class='info'>";
echo "<h3>1. Test Database Operations</h3>";
echo "<form method='post'>";
echo "<input type='hidden' name='test_ajax' value='1'>";
echo "<button type='submit' name='action' value='punch_in' style='background:#28a745;color:white;padding:8px 16px;border:none;border-radius:4px;margin:5px;'>Test Punch In</button>";
echo "<button type='submit' name='action' value='punch_out' style='background:#dc3545;color:white;padding:8px 16px;border:none;border-radius:4px;margin:5px;'>Test Punch Out</button>";
echo "</form>";
echo "</div>";

echo "<div class='info'>";
echo "<h3>2. Test JavaScript Button Selection</h3>";
echo "<div id='test-buttons'>";
echo "<button id='punch-in-btn-23' onclick='testPunchIn()' style='background:#28a745;color:white;padding:8px 16px;border:none;border-radius:4px;margin:5px;'>Test Punch In JS</button>";
echo "<button id='punch-out-btn-23' onclick='testPunchOut()' style='background:#dc3545;color:white;padding:8px 16px;border:none;border-radius:4px;margin:5px;'>Test Punch Out JS</button>";
echo "</div>";
echo "<div id='js-results' style='background:#f8f9fa;padding:10px;margin:10px 0;border-radius:4px;'></div>";
echo "</div>";

echo "<script>
function testPunchIn() {
    const results = document.getElementById('js-results');
    results.innerHTML = '⏳ Testing Punch In...';
    
    const button = document.getElementById('punch-in-btn-23');
    results.innerHTML += '<br>✅ Punch In button found: ' + (button ? 'YES' : 'NO');
    
    if (button) {
        button.disabled = true;
        button.innerHTML = 'Processing...';
        results.innerHTML += '<br>✅ Button disabled and text changed';
        
        const punchOutButton = document.getElementById('punch-out-btn-23');
        results.innerHTML += '<br>✅ Punch Out button found: ' + (punchOutButton ? 'YES' : 'NO');
        
        if (punchOutButton) {
            punchOutButton.disabled = false;
            results.innerHTML += '<br>✅ Punch Out button enabled';
        }
        
        // Test AJAX call
        fetch('advanced_attendance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'punch_in',
                employee_id: 23,
                attendance_date: '" . date('Y-m-d') . "'
            })
        }).then(response => response.json())
        .then(result => {
            results.innerHTML += '<br>✅ AJAX Response: ' + JSON.stringify(result);
        }).catch(error => {
            results.innerHTML += '<br>❌ AJAX Error: ' + error.message;
        });
    }
}

function testPunchOut() {
    const results = document.getElementById('js-results');
    results.innerHTML = '⏳ Testing Punch Out...';
    
    const button = document.getElementById('punch-out-btn-23');
    results.innerHTML += '<br>✅ Punch Out button found: ' + (button ? 'YES' : 'NO');
    
    if (button) {
        button.disabled = true;
        button.innerHTML = 'Processing...';
        results.innerHTML += '<br>✅ Button disabled and text changed';
        
        // Test AJAX call
        fetch('advanced_attendance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'punch_out',
                employee_id: 23,
                attendance_date: '" . date('Y-m-d') . "'
            })
        }).then(response => response.json())
        .then(result => {
            results.innerHTML += '<br>✅ AJAX Response: ' + JSON.stringify(result);
        }).catch(error => {
            results.innerHTML += '<br>❌ AJAX Error: ' + error.message;
        });
    }
}
</script>";

echo "<div class='info'>";
echo "<h3>3. Check Current Attendance Data</h3>";
$attendance_query = $conn->query("SELECT * FROM attendance WHERE employee_id = 23 AND attendance_date = '" . date('Y-m-d') . "'");
if ($attendance_query && $attendance_query->num_rows > 0) {
    $att_data = $attendance_query->fetch_assoc();
    echo "<strong>Current Status for Employee 23:</strong><br>";
    echo "Time In: " . ($att_data['time_in'] ?? 'NULL') . "<br>";
    echo "Time Out: " . ($att_data['time_out'] ?? 'NULL') . "<br>";
    echo "Status: " . ($att_data['status'] ?? 'NULL') . "<br>";
} else {
    echo "<div class='info'>No attendance record found for today</div>";
}
echo "</div>";

echo "<br><h3>🔍 Debug Steps:</h3>";
echo "<ol>";
echo "<li>First test the database operations above</li>";
echo "<li>Then test the JavaScript button selection</li>";
echo "<li>Check browser console (F12) for any JavaScript errors</li>";
echo "<li>Try the actual attendance page and compare behavior</li>";
echo "</ol>";
?>
