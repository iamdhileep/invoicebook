<?php
// Quick status check for biometric API - minimal response time
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Start timing
$start_time = microtime(true);

try {
    // Include database connection with absolute path
    require_once __DIR__ . '/../db.php';
    
    // Simple query to check database connectivity
    $query = "SELECT COUNT(*) as count FROM biometric_devices LIMIT 1";
    $result = $conn->query($query);
    
    if ($result) {
        $row = $result->fetch_assoc();
        $device_count = $row['count'];
    } else {
        throw new Exception("Database query failed");
    }
    
    // Check sync status table
    $sync_query = "SELECT COUNT(*) as count FROM device_sync_status LIMIT 1";
    $sync_result = $conn->query($sync_query);
    
    if ($sync_result) {
        $sync_row = $sync_result->fetch_assoc();
        $sync_count = $sync_row['count'];
    } else {
        $sync_count = 0;
    }
    
    $end_time = microtime(true);
    $response_time = round(($end_time - $start_time) * 1000, 2);
    
    echo json_encode([
        'status' => 'ok',
        'database_connected' => true,
        'device_count' => $device_count,
        'sync_entries' => $sync_count,
        'response_time_ms' => $response_time,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    $end_time = microtime(true);
    $response_time = round(($end_time - $start_time) * 1000, 2);
    
    echo json_encode([
        'status' => 'error',
        'database_connected' => false,
        'error' => $e->getMessage(),
        'response_time_ms' => $response_time,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// Close connection if it exists
if (isset($conn)) {
    $conn->close();
}
?>
