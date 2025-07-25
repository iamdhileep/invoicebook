<?php
session_start();
if (!isset($_SESSION['admin'])) {
    echo json_encode(['error' => 'Session not set']);
    exit;
}

include 'db.php';

// Simple punch test
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    echo json_encode([
        'success' => true,
        'message' => 'Test successful',
        'received_data' => $input,
        'current_time' => date('Y-m-d H:i:s'),
        'session_admin' => $_SESSION['admin'] ?? 'not set',
        'database_connected' => $conn ? true : false
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Punch Test</title>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
</head>
<body>
<h3>Punch Action Test</h3>
<button onclick="testPunch()">Test Punch Action</button>
<div id="result"></div>

<script>
async function testPunch() {
    console.log('Testing punch action...');
    
    try {
        const response = await fetch('test_punch_action.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'punch_in',
                employee_id: 1,
                attendance_date: '<?= date('Y-m-d') ?>'
            })
        });
        
        const result = await response.json();
        console.log('Response:', result);
        document.getElementById('result').innerHTML = '<pre>' + JSON.stringify(result, null, 2) + '</pre>';
        
    } catch (error) {
        console.error('Error:', error);
        document.getElementById('result').innerHTML = 'Error: ' + error.message;
    }
}
</script>
</body>
</html>
