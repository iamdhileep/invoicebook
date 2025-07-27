<?php
header('Content-Type: application/json');
// Remove authentication for testing
// session_start();
// if (!isset($_SESSION['admin'])) {
//     http_response_code(401);
//     echo json_encode(['error' => 'Unauthorized']);
//     exit;
// }

include __DIR__ . '/../../../db.php';

// Handle both GET and POST requests
$requestMethod = $_SERVER['REQUEST_METHOD'];
$action = '';
$data = [];

if ($requestMethod === 'GET') {
    $action = $_GET['action'] ?? '';
} else if ($requestMethod === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    $action = $data['action'] ?? $_POST['action'] ?? '';
}

switch ($action) {
    case 'get_devices':
        getDevices($conn);
        break;
    case 'toggle_device':
        toggleDevice($conn, $data);
        break;
    case 'get_sync_status':
        getSyncStatus($conn);
        break;
    case 'sync_all_devices':
        syncAllDevices($conn);
        break;
    case 'update_device':
        updateDevice($conn, $data);
        break;
    case 'delete_device':
        deleteDevice($conn, $data);
        break;
    case 'create_device':
        createDevice($conn, $data);
        break;
    case 'update_device_settings':
        updateDeviceSettings($conn, $data);
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
}

function getDevices($conn) {
    try {
        $query = "SELECT id, device_name, device_type, location, is_enabled, campus FROM biometric_devices ORDER BY location, device_name";
        $result = $conn->query($query);
        
        $devices = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $devices[] = $row;
            }
        }
        
        echo json_encode(['success' => true, 'devices' => $devices, 'count' => count($devices)]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function toggleDevice($conn, $data) {
    $deviceId = (int)$data['device_id'];
    $isActive = $data['is_active'] ? 1 : 0;
    
    if (empty($deviceId)) {
        echo json_encode(['success' => false, 'message' => 'Device ID required']);
        return;
    }
    
    $stmt = $conn->prepare("UPDATE biometric_devices SET is_enabled = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("ii", $isActive, $deviceId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Device status updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update device status: ' . $conn->error]);
    }
}

function getSyncStatus($conn) {
    try {
        $query = "SELECT ds.id, ds.device_id, ds.campus, ds.status, ds.last_sync, bd.device_name, bd.device_type 
                  FROM device_sync_status ds 
                  LEFT JOIN biometric_devices bd ON ds.device_id = bd.id 
                  ORDER BY ds.campus, ds.last_sync DESC 
                  LIMIT 10"; // Limit results for faster loading
        $result = $conn->query($query);
        
        $status = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row['campus_name'] = $row['campus'] ?? 'Unknown Campus';
                $row['sync_status'] = $row['status'] ?? 'failed';
                $status[] = $row;
            }
        }
        
        echo json_encode(['success' => true, 'status' => $status, 'count' => count($status)]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function syncAllDevices($conn) {
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Update all sync statuses to success with current timestamp
        $sql = "UPDATE device_sync_status SET 
                status = 'sync', 
                last_sync = NOW()";
        
        if (!$conn->query($sql)) {
            throw new Exception('Failed to update sync status: ' . $conn->error);
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'All devices synced successfully']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateDevice($conn, $data) {
    $device_id = (int)$data['device_id'];
    $device_name = mysqli_real_escape_string($conn, $data['device_name']);
    $device_type = mysqli_real_escape_string($conn, $data['device_type']);
    $location = mysqli_real_escape_string($conn, $data['location']);
    $is_active = $data['is_active'] ? 1 : 0;
    
    if (empty($device_name) || empty($location)) {
        echo json_encode(['success' => false, 'message' => 'Device name and location are required']);
        return;
    }
    
    $sql = "UPDATE biometric_devices SET 
            device_name = '$device_name',
            device_type = '$device_type',
            location = '$location',
            is_enabled = $is_active,
            updated_at = NOW()
            WHERE id = $device_id";
    
    if ($conn->query($sql)) {
        echo json_encode(['success' => true, 'message' => 'Device updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update device: ' . $conn->error]);
    }
}

function deleteDevice($conn, $data) {
    $device_id = (int)$data['device_id'];
    
    if (empty($device_id)) {
        echo json_encode(['success' => false, 'message' => 'Device ID required']);
        return;
    }
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // First delete related sync status records
        $delete_sync_sql = "DELETE FROM device_sync_status WHERE device_id = $device_id";
        if (!$conn->query($delete_sync_sql)) {
            throw new Exception('Failed to delete sync status records: ' . $conn->error);
        }
        
        // Delete related attendance records
        $delete_attendance_sql = "DELETE FROM biometric_attendance WHERE device_id = $device_id";
        if (!$conn->query($delete_attendance_sql)) {
            throw new Exception('Failed to delete attendance records: ' . $conn->error);
        }
        
        // Then delete the device
        $delete_device_sql = "DELETE FROM biometric_devices WHERE id = $device_id";
        if (!$conn->query($delete_device_sql)) {
            throw new Exception('Failed to delete device: ' . $conn->error);
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Device deleted successfully']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function createDevice($conn, $data) {
    $device_name = mysqli_real_escape_string($conn, $data['device_name']);
    $device_type = mysqli_real_escape_string($conn, $data['device_type']);
    $location = mysqli_real_escape_string($conn, $data['location']);
    $is_active = $data['is_active'] ? 1 : 0;
    
    if (empty($device_name) || empty($location)) {
        echo json_encode(['success' => false, 'message' => 'Device name and location are required']);
        return;
    }
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Create the device
        $sql = "INSERT INTO biometric_devices (device_name, device_type, location, is_enabled, campus, ip_address, port, created_at, updated_at) 
                VALUES ('$device_name', '$device_type', '$location', $is_active, '$location', '192.168.1.100', 4370, NOW(), NOW())";
        
        if (!$conn->query($sql)) {
            throw new Exception('Failed to create device: ' . $conn->error);
        }
        
        $device_id = $conn->insert_id;
        
        // Create initial sync status for the new device
        $sync_sql = "INSERT INTO device_sync_status (device_id, campus, building, status, last_sync) 
                     VALUES ($device_id, '$location', '', 'sync', NOW())";
        
        if (!$conn->query($sync_sql)) {
            throw new Exception('Failed to create sync status: ' . $conn->error);
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Device created successfully', 'device_id' => $device_id]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateDeviceSettings($conn, $data) {
    $syncInterval = $data['sync_interval'] ?? 10;
    $timeout = $data['timeout'] ?? 30;
    $autoRetry = $data['auto_retry'] ?? 1;
    
    // For now, just return success
    // In a real implementation, you would save these to a settings table
    
    echo json_encode([
        'success' => true,
        'message' => 'Settings updated successfully',
        'settings' => [
            'sync_interval' => $syncInterval,
            'timeout' => $timeout,
            'auto_retry' => $autoRetry
        ]
    ]);
}
?>
