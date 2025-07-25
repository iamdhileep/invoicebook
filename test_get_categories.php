<?php
session_start();
$_SESSION['admin'] = 'test';

echo "=== Testing get_categories.php ===\n";
ob_start();
include 'get_categories.php';
$response = ob_get_clean();
echo "Response: " . $response . "\n";

$data = json_decode($response, true);
if ($data) {
    echo "\nCategories found: " . count($data) . "\n";
    foreach ($data as $cat) {
        echo "  - " . $cat['name'] . "\n";
    }
} else {
    echo "Failed to parse response or no categories\n";
}
?>
