<?php
/**
 * Simple ping endpoint for connectivity checking
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store, must-revalidate');

echo json_encode([
    'status' => 'online',
    'timestamp' => time(),
    'server_time' => date('Y-m-d H:i:s'),
    'version' => '1.0.0'
]);
?>
