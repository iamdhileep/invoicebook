<?php
session_start();
$_SESSION['admin'] = 'test'; // Set admin session for testing

// Simulate POST request to save_category.php
$_POST['name'] = 'TestCategory123';
$_SERVER['REQUEST_METHOD'] = 'POST';

echo "=== Testing save_category.php ===\n";
echo "Session admin: " . ($_SESSION['admin'] ?? 'NOT SET') . "\n";
echo "POST name: " . ($_POST['name'] ?? 'NOT SET') . "\n";
echo "Request method: " . $_SERVER['REQUEST_METHOD'] . "\n\n";

echo "Response:\n";
ob_start();
include 'save_category.php';
$response = ob_get_clean();
echo $response . "\n";

// Parse the JSON response
$data = json_decode($response, true);
if ($data) {
    echo "\nParsed response:\n";
    print_r($data);
} else {
    echo "\nFailed to parse JSON response\n";
}
?>
