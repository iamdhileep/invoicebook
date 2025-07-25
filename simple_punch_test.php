<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Simple Punch Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-4">
    <h3>Simple Punch Test</h3>
    
    <div class="mb-3">
        <button type="button" class="btn btn-success" onclick="testPunch('punch_in')">
            Test Punch In
        </button>
        <button type="button" class="btn btn-danger" onclick="testPunch('punch_out')">
            Test Punch Out
        </button>
    </div>
    
    <div id="result" class="mt-3"></div>

<script>
async function testPunch(action) {
    console.log('Testing punch:', action);
    document.getElementById('result').innerHTML = 'Testing...';
    
    try {
        const response = await fetch('advanced_attendance.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: action,
                employee_id: 1,
                attendance_date: '<?= date('Y-m-d') ?>'
            })
        });
        
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        const result = await response.json();
        console.log('Result:', result);
        
        document.getElementById('result').innerHTML = `
            <div class="alert alert-${result.success ? 'success' : 'danger'}">
                <strong>${result.success ? 'Success' : 'Error'}:</strong> ${result.message}
                <br><small>Raw response: ${JSON.stringify(result)}</small>
            </div>
        `;
        
    } catch (error) {
        console.error('Error:', error);
        document.getElementById('result').innerHTML = `
            <div class="alert alert-danger">
                <strong>Error:</strong> ${error.message}
            </div>
        `;
    }
}
</script>
</body>
</html>
