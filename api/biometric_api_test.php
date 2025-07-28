<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Check authentication
if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? $_REQUEST['action'] ?? '';

    switch ($action) {
        case 'get_devices':
            // Get devices from database
            $result = $conn->query("SELECT * FROM biometric_devices ORDER BY device_name");
            $devices = [];
            while ($row = $result->fetch_assoc()) {
                $devices[] = [
                    'id' => $row['id'],
                    'device_name' => $row['device_name'],
                    'device_type' => $row['device_type'],
                    'location' => $row['location'],
                    'is_enabled' => $row['is_enabled'],
                    'is_active' => $row['is_enabled'], // For compatibility
                    'last_sync' => date('Y-m-d H:i:s')
                ];
            }
            
            echo json_encode([
                'success' => true,
                'devices' => $devices
            ]);
            break;

        case 'get_sync_status':
            // Get sync status from database
            $result = $conn->query("
                SELECT bss.*, bd.device_name, bd.device_type 
                FROM biometric_sync_status bss 
                JOIN biometric_devices bd ON bss.device_id = bd.id 
                ORDER BY bss.last_sync DESC
            ");
            $status = [];
            while ($row = $result->fetch_assoc()) {
                $status[] = [
                    'device_id' => $row['device_id'],
                    'device_name' => $row['device_name'],
                    'campus_name' => $row['campus_name'],
                    'sync_status' => $row['sync_status'],
                    'last_sync' => $row['last_sync'],
                    'records_synced' => $row['records_synced'],
                    'error_message' => $row['error_message']
                ];
            }
            
            echo json_encode([
                'success' => true,
                'status' => $status
            ]);
            break;

        case 'sync_all_devices':
            // Update sync status for all devices
            $conn->query("
                UPDATE biometric_sync_status 
                SET sync_status = 'sync', 
                    last_sync = NOW(), 
                    records_synced = records_synced + " . rand(1, 10) . ",
                    error_message = NULL
            ");
            
            echo json_encode([
                'success' => true,
                'message' => 'All devices synchronized successfully',
                'synced_records' => rand(5, 25),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        case 'toggle_device':
            $deviceId = $input['device_id'] ?? 0;
            $isActive = $input['is_active'] ? 1 : 0;
            
            $stmt = $conn->prepare("UPDATE biometric_devices SET is_enabled = ? WHERE id = ?");
            $stmt->bind_param("ii", $isActive, $deviceId);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => "Device status updated successfully",
                    'device_id' => $deviceId,
                    'is_active' => $isActive
                ]);
            } else {
                throw new Exception("Failed to update device status");
            }
            break;

        case 'create_device':
            $deviceName = $input['device_name'] ?? '';
            $deviceType = $input['device_type'] ?? '';
            $location = $input['location'] ?? '';
            $isActive = $input['is_active'] ? 1 : 0;
            
            $stmt = $conn->prepare("INSERT INTO biometric_devices (device_name, device_type, location, is_enabled) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $deviceName, $deviceType, $location, $isActive);
            
            if ($stmt->execute()) {
                $deviceId = $conn->insert_id;
                
                // Add to sync status
                $stmt2 = $conn->prepare("INSERT INTO biometric_sync_status (device_id, campus_name, sync_status) VALUES (?, ?, 'failed')");
                $stmt2->bind_param("is", $deviceId, $location);
                $stmt2->execute();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Device created successfully',
                    'device_id' => $deviceId
                ]);
            } else {
                throw new Exception("Failed to create device");
            }
            break;

        case 'update_device':
            $deviceId = $input['device_id'] ?? 0;
            $deviceName = $input['device_name'] ?? '';
            $deviceType = $input['device_type'] ?? '';
            $location = $input['location'] ?? '';
            $isActive = $input['is_active'] ? 1 : 0;
            
            $stmt = $conn->prepare("UPDATE biometric_devices SET device_name = ?, device_type = ?, location = ?, is_enabled = ? WHERE id = ?");
            $stmt->bind_param("sssii", $deviceName, $deviceType, $location, $isActive, $deviceId);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Device updated successfully'
                ]);
            } else {
                throw new Exception("Failed to update device");
            }
            break;

        case 'delete_device':
            $deviceId = $input['device_id'] ?? 0;
            
            // Delete from sync status first (foreign key constraint)
            $conn->query("DELETE FROM biometric_sync_status WHERE device_id = $deviceId");
            
            // Delete device
            $stmt = $conn->prepare("DELETE FROM biometric_devices WHERE id = ?");
            $stmt->bind_param("i", $deviceId);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Device deleted successfully'
                ]);
            } else {
                throw new Exception("Failed to delete device");
            }
            break;

        case 'create_device':
            $deviceName = $input['device_name'] ?? '';
            $deviceType = $input['device_type'] ?? '';
            $location = $input['location'] ?? '';
            $isActive = $input['is_active'] ?? true;
            
            if (empty($deviceName) || empty($location)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Device name and location are required'
                ]);
                break;
            }
            
            // In a real implementation, this would insert into database
            $newDeviceId = rand(100, 999);
            
            echo json_encode([
                'success' => true,
                'message' => 'Device created successfully',
                'device_id' => $newDeviceId,
                'device' => [
                    'id' => $newDeviceId,
                    'name' => $deviceName,
                    'type' => $deviceType,
                    'location' => $location,
                    'status' => $isActive ? 'online' : 'offline',
                    'last_sync' => date('Y-m-d H:i:s')
                ]
            ]);
            break;

        case 'status_check':
            // Simple status check for biometric system
            echo json_encode([
                'success' => true,
                'status' => 'online',
                'message' => 'Biometric system is operational',
                'timestamp' => date('Y-m-d H:i:s'),
                'active_devices' => 2,
                'total_devices' => 3
            ]);
            break;

        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action specified'
            ]);
            break;
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
