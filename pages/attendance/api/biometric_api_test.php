<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include '../db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_devices':
            $query = "SELECT * FROM biometric_devices ORDER BY device_name";
            $result = $conn->query($query);
            
            $devices = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $devices[] = $row;
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => $devices,
                'total' => count($devices)
            ]);
            break;
            
        case 'get_sync_status':
            $query = "SELECT d.*, 
                            COALESCE(MAX(l.sync_time), 'Never') as last_sync,
                            COALESCE(l.status, 'unknown') as sync_status
                     FROM biometric_devices d 
                     LEFT JOIN device_sync_logs l ON d.id = l.device_id 
                     GROUP BY d.id
                     ORDER BY d.device_name";
            $result = $conn->query($query);
            
            $syncStatus = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $syncStatus[] = $row;
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => $syncStatus,
                'total' => count($syncStatus)
            ]);
            break;
            
        case 'sync_all':
            // Simulate sync operation
            $timestamp = date('Y-m-d H:i:s');
            $affected = 0;
            
            // Update last sync for all active devices
            $updateQuery = "UPDATE biometric_devices SET last_sync = ? WHERE is_active = 1";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("s", $timestamp);
            if ($stmt->execute()) {
                $affected = $stmt->affected_rows;
            }
            
            echo json_encode([
                'success' => true,
                'message' => "Synchronized {$affected} devices",
                'synced_devices' => $affected,
                'timestamp' => $timestamp
            ]);
            break;
            
        case 'toggle_device':
            if ($method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $deviceId = $input['device_id'] ?? 0;
                $isActive = $input['is_active'] ?? false;
                
                $stmt = $conn->prepare("UPDATE biometric_devices SET is_active = ? WHERE id = ?");
                $stmt->bind_param("ii", $isActive, $deviceId);
                
                if ($stmt->execute()) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Device status updated successfully'
                    ]);
                } else {
                    throw new Exception('Failed to update device status');
                }
            }
            break;
            
        default:
            // Return sample data for testing
            echo json_encode([
                'success' => true,
                'message' => 'Biometric API is working',
                'available_actions' => ['get_devices', 'get_sync_status', 'sync_all', 'toggle_device'],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'API Error: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
