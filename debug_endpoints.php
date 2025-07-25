<?php
session_start();

// Set admin session for testing
if (!isset($_SESSION['admin'])) {
    $_SESSION['admin'] = 'test_admin';
    echo "Session created: " . $_SESSION['admin'] . "<br>";
} else {
    echo "Session exists: " . $_SESSION['admin'] . "<br>";
}

echo "<h3>Testing AJAX Endpoints</h3>";

// Test get_categories.php
echo "<h4>1. Testing get_categories.php:</h4>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/billbook/get_categories.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode<br>";
echo "Response: " . htmlspecialchars($result) . "<br><br>";

// Test save_category.php
echo "<h4>2. Testing save_category.php:</h4>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/billbook/save_category.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, 'name=TestCategoryFromBrowser');
curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode<br>";
echo "Response: " . htmlspecialchars($result) . "<br><br>";

echo "<h3>JavaScript Test</h3>";
?>
<!DOCTYPE html>
<html>
<head>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
<button onclick="testAjax()">Test AJAX Category Loading</button>
<div id="result"></div>

<script>
function testAjax() {
    console.log('Testing AJAX...');
    $('#result').html('Testing AJAX...');
    
    $.ajax({
        url: 'get_categories.php',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            console.log('AJAX Success:', data);
            $('#result').html('<strong>SUCCESS:</strong> Loaded ' + data.length + ' categories<br>' + JSON.stringify(data));
        },
        error: function(xhr, status, error) {
            console.log('AJAX Error:', error, xhr.responseText);
            $('#result').html('<strong>ERROR:</strong> ' + error + '<br>Response: ' + xhr.responseText);
        }
    });
}
</script>
</body>
</html>
