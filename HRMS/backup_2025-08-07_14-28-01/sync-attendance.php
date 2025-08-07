<?php
/**
 * Mobile API - Attendance Sync Handler
 * Handles offline attendance sync and mobile-specific features
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../includes/hrms_config.php';

// Authentication check
if (!HRMSHelper::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$currentUserId = HRMSHelper::getCurrentUserId();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($method) {
        case 'POST':
            handlePost($action, $currentUserId, $conn);
            break;
            
        case 'GET':
            handleGet($action, $currentUserId, $conn);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handlePost($action, $userId, $conn) {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    
    switch ($action) {
        case 'sync':
            syncAttendanceData($input, $userId, $conn);
            break;
            
        case 'bulk_sync':
            bulkSyncAttendance($input, $userId, $conn);
            break;
            
        case 'mobile_clock':
            mobileClockAction($input, $userId, $conn);
            break;
            
        case 'location_track':
            trackLocation($input, $userId, $conn);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function handleGet($action, $userId, $conn) {
    switch ($action) {
        case 'status':
            getAttendanceStatus($userId, $conn);
            break;
            
        case 'history':
            getAttendanceHistory($userId, $conn);
            break;
            
        case 'summary':
            getAttendanceSummary($userId, $conn);
            break;
            
        case 'pending_sync':
            getPendingSyncItems($userId, $conn);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function syncAttendanceData($data, $userId, $conn) {
    $syncItems = $data['items'] ?? [];
    
    if (empty($syncItems)) {
        echo json_encode(['success' => false, 'message' => 'No sync items provided']);
        return;
    }
    
    $processed = 0;
    $failed = 0;
    $conflicts = [];
    
    foreach ($syncItems as $item) {
        try {
            $result = processSyncItem($item, $userId, $conn);
            
            if ($result['success']) {
                $processed++;
            } else {
                $failed++;
                if ($result['conflict']) {
                    $conflicts[] = $result;
                }
            }
        } catch (Exception $e) {
            $failed++;
            error_log("Sync item failed: " . $e->getMessage());
        }
    }
    
    echo json_encode([
        'success' => true,
        'processed' => $processed,
        'failed' => $failed,
        'conflicts' => $conflicts,
        'message' => "Processed {$processed} items, {$failed} failed"
    ]);
}

function processSyncItem($item, $userId, $conn) {
    $employeeId = getEmployeeIdFromUserId($userId, $conn);
    if (!$employeeId) {
        return ['success' => false, 'message' => 'Employee not found', 'conflict' => false];
    }
    
    $action = $item['action'] ?? '';
    $data = $item['data'] ?? [];
    $clientTimestamp = $item['timestamp'] ?? '';
    
    switch ($action) {
        case 'clock_in':
            return syncClockIn($data, $employeeId, $clientTimestamp, $conn);
            
        case 'clock_out':
            return syncClockOut($data, $employeeId, $clientTimestamp, $conn);
            
        case 'mark_attendance':
            return syncMarkAttendance($data, $employeeId, $clientTimestamp, $conn);
            
        default:
            return ['success' => false, 'message' => 'Unknown action', 'conflict' => false];
    }
}

function bulkSyncAttendance($data, $userId, $conn) {
    $attendanceRecords = $data['records'] ?? [];
    
    if (empty($attendanceRecords)) {
        echo json_encode(['success' => false, 'message' => 'No records provided']);
        return;
    }
    
    $processed = 0;
    $failed = 0;
    $conflicts = [];
    
    foreach ($attendanceRecords as $record) {
        try {
            $result = processSyncItem(['action' => 'bulk_import', 'data' => $record], $userId, $conn);
            
            if ($result['success']) {
                $processed++;
            } else {
                $failed++;
                if ($result['conflict']) {
                    $conflicts[] = $result;
                }
            }
        } catch (Exception $e) {
            $failed++;
            error_log("Bulk sync failed: " . $e->getMessage());
        }
    }
    
    echo json_encode([
        'success' => true,
        'processed' => $processed,
        'failed' => $failed,
        'conflicts' => $conflicts,
        'message' => "Bulk sync completed: {$processed} processed, {$failed} failed"
    ]);
}

function syncMarkAttendance($data, $employeeId, $clientTimestamp, $conn) {
    $date = $data['date'] ?? date('Y-m-d');
    $status = $data['status'] ?? 'present';
    $notes = $data['notes'] ?? '';
    $clockInTime = $data['clock_in_time'] ?? null;
    $clockOutTime = $data['clock_out_time'] ?? null;
    $hoursWorked = $data['hours_worked'] ?? null;
    
    // Check for existing record
    $stmt = $conn->prepare("
        SELECT id, status, clock_in_time, clock_out_time 
        FROM hr_attendance 
        WHERE employee_id = ? AND date = ?
    ");
    $stmt->bind_param('is', $employeeId, $date);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    
    if ($existing) {
        // Update existing record
        $stmt = $conn->prepare("
            UPDATE hr_attendance 
            SET status = ?, notes = ?, clock_in_time = COALESCE(?, clock_in_time), 
                clock_out_time = COALESCE(?, clock_out_time), 
                hours_worked = COALESCE(?, hours_worked),
                sync_status = 'synced'
            WHERE id = ?
        ");
        $stmt->bind_param('ssssdi', $status, $notes, $clockInTime, $clockOutTime, $hoursWorked, $existing['id']);
    } else {
        // Insert new record
        $stmt = $conn->prepare("
            INSERT INTO hr_attendance 
            (employee_id, date, status, notes, clock_in_time, clock_out_time, hours_worked, sync_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'synced')
        ");
        $stmt->bind_param('isssssd', $employeeId, $date, $status, $notes, $clockInTime, $clockOutTime, $hoursWorked);
    }
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Attendance marked successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to mark attendance', 'conflict' => false];
    }
}
    }
}

function syncClockIn($data, $employeeId, $clientTimestamp, $conn) {
    $date = $data['date'] ?? date('Y-m-d');
    $clockInTime = $data['clock_in_time'] ?? date('Y-m-d H:i:s');
    $location = $data['location'] ?? '';
    $notes = $data['notes'] ?? '';
    $latitude = $data['latitude'] ?? null;
    $longitude = $data['longitude'] ?? null;
    $accuracy = $data['accuracy'] ?? null;
    
    // Check for existing attendance on this date
    $stmt = $conn->prepare("
        SELECT id, clock_in_time, sync_status 
        FROM hr_attendance 
        WHERE employee_id = ? AND date = ?
    ");
    $stmt->bind_param('is', $employeeId, $date);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    
    if ($existing) {
        // Check for conflicts
        if ($existing['clock_in_time'] && $existing['clock_in_time'] !== $clockInTime) {
            return [
                'success' => false,
                'conflict' => true,
                'message' => 'Clock-in time conflict',
                'server_time' => $existing['clock_in_time'],
                'client_time' => $clockInTime
            ];
        }
        
        // Update existing record
        $stmt = $conn->prepare("
            UPDATE hr_attendance 
            SET clock_in_time = ?, clock_in_location = ?, clock_in_notes = ?, 
                gps_latitude = ?, gps_longitude = ?, gps_accuracy = ?,
                mobile_clock_in = 1, sync_status = 'synced'
            WHERE id = ?
        ");
        $stmt->bind_param('sssdddi', $clockInTime, $location, $notes, $latitude, $longitude, $accuracy, $existing['id']);
    } else {
        // Insert new record
        $stmt = $conn->prepare("
            INSERT INTO hr_attendance 
            (employee_id, date, clock_in_time, clock_in_location, clock_in_notes, 
             gps_latitude, gps_longitude, gps_accuracy, status, mobile_clock_in, sync_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'present', 1, 'synced')
        ");
        $stmt->bind_param('issssddd', $employeeId, $date, $clockInTime, $location, $notes, $latitude, $longitude, $accuracy);
    }
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Clock-in synced successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to sync clock-in', 'conflict' => false];
    }
}

function syncClockOut($data, $employeeId, $clientTimestamp, $conn) {
    $date = $data['date'] ?? date('Y-m-d');
    $clockOutTime = $data['clock_out_time'] ?? date('Y-m-d H:i:s');
    $location = $data['location'] ?? '';
    $notes = $data['notes'] ?? '';
    $hoursWorked = $data['hours_worked'] ?? null;
    $latitude = $data['latitude'] ?? null;
    $longitude = $data['longitude'] ?? null;
    $accuracy = $data['accuracy'] ?? null;
    
    // Find existing attendance record
    $stmt = $conn->prepare("
        SELECT id, clock_in_time, clock_out_time 
        FROM hr_attendance 
        WHERE employee_id = ? AND date = ?
    ");
    $stmt->bind_param('is', $employeeId, $date);
    $stmt->execute();
    $attendance = $stmt->get_result()->fetch_assoc();
    
    if (!$attendance) {
        return ['success' => false, 'message' => 'No clock-in record found for this date', 'conflict' => false];
    }
    
    // Check for conflicts
    if ($attendance['clock_out_time'] && $attendance['clock_out_time'] !== $clockOutTime) {
        return [
            'success' => false,
            'conflict' => true,
            'message' => 'Clock-out time conflict',
            'server_time' => $attendance['clock_out_time'],
            'client_time' => $clockOutTime
        ];
    }
    
    // Calculate hours worked if not provided
    if ($hoursWorked === null && $attendance['clock_in_time']) {
        $clockIn = new DateTime($attendance['clock_in_time']);
        $clockOut = new DateTime($clockOutTime);
        $diff = $clockIn->diff($clockOut);
        $hoursWorked = $diff->h + ($diff->i / 60) + ($diff->s / 3600);
    }
    
    // Update record
    $stmt = $conn->prepare("
        UPDATE hr_attendance 
        SET clock_out_time = ?, clock_out_location = ?, clock_out_notes = ?, 
            hours_worked = ?, gps_latitude = ?, gps_longitude = ?, gps_accuracy = ?,
            mobile_clock_out = 1, sync_status = 'synced'
        WHERE id = ?
    ");
    $stmt->bind_param('sssddddi', $clockOutTime, $location, $notes, $hoursWorked, $latitude, $longitude, $accuracy, $attendance['id']);
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Clock-out synced successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to sync clock-out', 'conflict' => false];
    }
}

function mobileClockAction($data, $userId, $conn) {
    $employeeId = getEmployeeIdFromUserId($userId, $conn);
    if (!$employeeId) {
        echo json_encode(['success' => false, 'message' => 'Employee not found']);
        return;
    }
    
    $action = $data['action'] ?? '';
    $timestamp = $data['timestamp'] ?? date('Y-m-d H:i:s');
    $location = $data['location'] ?? '';
    $notes = $data['notes'] ?? '';
    $latitude = $data['latitude'] ?? null;
    $longitude = $data['longitude'] ?? null;
    $accuracy = $data['accuracy'] ?? null;
    $offline = $data['offline'] ?? false;
    
    $today = date('Y-m-d');
    
    if ($action === 'clock_in') {
        // Check if already clocked in
        $stmt = $conn->prepare("
            SELECT id FROM hr_attendance 
            WHERE employee_id = ? AND date = ? AND clock_out_time IS NULL
        ");
        $stmt->bind_param('is', $employeeId, $today);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Already clocked in today']);
            return;
        }
        
        // Clock in
        $stmt = $conn->prepare("
            INSERT INTO hr_attendance 
            (employee_id, date, clock_in_time, clock_in_location, clock_in_notes, 
             gps_latitude, gps_longitude, gps_accuracy, status, mobile_clock_in, sync_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'present', 1, ?)
        ");
        $syncStatus = $offline ? 'pending' : 'synced';
        $stmt->bind_param('issssdds', $employeeId, $today, $timestamp, $location, $notes, $latitude, $longitude, $accuracy, $syncStatus);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Clocked in successfully',
                'time' => date('g:i A', strtotime($timestamp)),
                'attendance_id' => $conn->insert_id
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to clock in']);
        }
        
    } elseif ($action === 'clock_out') {
        // Find today's clock-in record
        $stmt = $conn->prepare("
            SELECT id, clock_in_time FROM hr_attendance 
            WHERE employee_id = ? AND date = ? AND clock_out_time IS NULL
        ");
        $stmt->bind_param('is', $employeeId, $today);
        $stmt->execute();
        $attendance = $stmt->get_result()->fetch_assoc();
        
        if (!$attendance) {
            echo json_encode(['success' => false, 'message' => 'No clock-in record found']);
            return;
        }
        
        // Calculate hours worked
        $clockIn = new DateTime($attendance['clock_in_time']);
        $clockOut = new DateTime($timestamp);
        $diff = $clockIn->diff($clockOut);
        $hoursWorked = $diff->h + ($diff->i / 60) + ($diff->s / 3600);
        
        // Clock out
        $stmt = $conn->prepare("
            UPDATE hr_attendance 
            SET clock_out_time = ?, clock_out_location = ?, clock_out_notes = ?, 
                hours_worked = ?, gps_latitude = ?, gps_longitude = ?, gps_accuracy = ?,
                mobile_clock_out = 1, sync_status = ?
            WHERE id = ?
        ");
        $syncStatus = $offline ? 'pending' : 'synced';
        $stmt->bind_param('sssdddsi', $timestamp, $location, $notes, $hoursWorked, $latitude, $longitude, $accuracy, $syncStatus, $attendance['id']);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Clocked out successfully',
                'time' => date('g:i A', strtotime($timestamp)),
                'hours_worked' => round($hoursWorked, 2)
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to clock out']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function getAttendanceStatus($userId, $conn) {
    $employeeId = getEmployeeIdFromUserId($userId, $conn);
    if (!$employeeId) {
        echo json_encode(['success' => false, 'message' => 'Employee not found']);
        return;
    }
    
    $today = date('Y-m-d');
    
    // Get today's attendance
    $stmt = $conn->prepare("
        SELECT * FROM hr_attendance 
        WHERE employee_id = ? AND date = ?
    ");
    $stmt->bind_param('is', $employeeId, $today);
    $stmt->execute();
    $todayAttendance = $stmt->get_result()->fetch_assoc();
    
    // Get week summary
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as days_present,
            SUM(hours_worked) as total_hours,
            AVG(hours_worked) as avg_hours
        FROM hr_attendance 
        WHERE employee_id = ? AND date >= ? AND date <= ? AND status = 'present'
    ");
    $stmt->bind_param('iss', $employeeId, $weekStart, $today);
    $stmt->execute();
    $weekSummary = $stmt->get_result()->fetch_assoc();
    
    // Get month summary
    $monthStart = date('Y-m-01');
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as days_present,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as days_absent,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as days_late,
            SUM(hours_worked) as total_hours
        FROM hr_attendance 
        WHERE employee_id = ? AND date >= ? AND date <= ?
    ");
    $stmt->bind_param('iss', $employeeId, $monthStart, $today);
    $stmt->execute();
    $monthSummary = $stmt->get_result()->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'today' => $todayAttendance,
        'week_summary' => $weekSummary,
        'month_summary' => $monthSummary,
        'is_clocked_in' => $todayAttendance && $todayAttendance['clock_in_time'] && !$todayAttendance['clock_out_time']
    ]);
}

function getAttendanceHistory($userId, $conn) {
    $employeeId = getEmployeeIdFromUserId($userId, $conn);
    if (!$employeeId) {
        echo json_encode(['success' => false, 'message' => 'Employee not found']);
        return;
    }
    
    $limit = intval($_GET['limit'] ?? 30);
    $offset = intval($_GET['offset'] ?? 0);
    $dateFrom = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
    $dateTo = $_GET['to'] ?? date('Y-m-d');
    
    $stmt = $conn->prepare("
        SELECT * FROM hr_attendance 
        WHERE employee_id = ? AND date >= ? AND date <= ?
        ORDER BY date DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param('issii', $employeeId, $dateFrom, $dateTo, $limit, $offset);
    $stmt->execute();
    $history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode(['success' => true, 'history' => $history]);
}

function getPendingSyncItems($userId, $conn) {
    $stmt = $conn->prepare("
        SELECT * FROM hr_offline_sync_queue 
        WHERE user_id = ? AND status = 'pending'
        ORDER BY created_at ASC
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $pendingItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode(['success' => true, 'pending_items' => $pendingItems]);
}

function getEmployeeIdFromUserId($userId, $conn) {
    $stmt = $conn->prepare("SELECT id FROM hr_employees WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result ? $result['id'] : null;
}

function trackLocation($data, $userId, $conn) {
    $latitude = $data['latitude'] ?? null;
    $longitude = $data['longitude'] ?? null;
    $accuracy = $data['accuracy'] ?? null;
    $timestamp = $data['timestamp'] ?? date('Y-m-d H:i:s');
    
    if ($latitude === null || $longitude === null) {
        echo json_encode(['success' => false, 'message' => 'Location data required']);
        return;
    }
    
    // Get device ID
    $deviceId = getOrCreateDevice($userId, $_SERVER['HTTP_USER_AGENT'] ?? '', $conn);
    
    // Store location data (you might want a separate location tracking table)
    $stmt = $conn->prepare("
        INSERT INTO hr_app_analytics 
        (user_id, device_id, event_type, event_name, data, timestamp)
        VALUES (?, ?, 'location', 'gps_track', ?, ?)
    ");
    $locationData = json_encode([
        'latitude' => $latitude,
        'longitude' => $longitude,
        'accuracy' => $accuracy
    ]);
    $stmt->bind_param('iiss', $userId, $deviceId, $locationData, $timestamp);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Location tracked']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to track location']);
    }
}

function getOrCreateDevice($userId, $userAgent, $conn) {
    // Try to find existing device
    $stmt = $conn->prepare("
        SELECT id FROM hr_mobile_devices 
        WHERE user_id = ? AND device_info LIKE ? 
        ORDER BY last_active DESC 
        LIMIT 1
    ");
    $devicePattern = '%' . substr($userAgent, 0, 100) . '%';
    $stmt->bind_param('is', $userId, $devicePattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $device = $result->fetch_assoc();
        return $device['id'];
    }
    
    // Create new device record
    $deviceType = detectDeviceType($userAgent);
    $stmt = $conn->prepare("
        INSERT INTO hr_mobile_devices (user_id, device_info, device_type, browser_info)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param('isss', $userId, $userAgent, $deviceType, $userAgent);
    $stmt->execute();
    
    return $conn->insert_id;
}

function detectDeviceType($userAgent) {
    $userAgent = strtolower($userAgent);
    
    if (strpos($userAgent, 'android') !== false) {
        return 'android';
    } elseif (strpos($userAgent, 'iphone') !== false || strpos($userAgent, 'ipad') !== false) {
        return 'ios';
    } elseif (strpos($userAgent, 'mobile') !== false) {
        return 'other';
    } else {
        return 'desktop';
    }
}
?>
