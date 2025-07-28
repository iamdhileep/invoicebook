<?php
session_start();
header('Content-Type: application/json');

// CORS headers for API access
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Authentication check with testing mode
$isTestMode = isset($_GET['test_mode']) && $_GET['test_mode'] === 'true';
$isTestPage = isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'test_attendance_features.html') !== false;

if (!isset($_SESSION['admin']) && !$isTestMode && !$isTestPage) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'Unauthorized access',
        'hint' => 'Please login first or add ?test_mode=true for testing'
    ]);
    exit();
}

include '../../../db.php';

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'POST':
            handlePOST($conn, $action);
            break;
        case 'GET':
            handleGET($conn, $action);
            break;
        case 'PUT':
            handlePUT($conn, $action);
            break;
        case 'DELETE':
            handleDELETE($conn, $action);
            break;
        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function handlePOST($conn, $action) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'punch_in':
            punchIn($conn, $input);
            break;
        case 'punch_out':
            punchOut($conn, $input);
            break;
        case 'face_recognition':
            processFaceRecognition($conn, $input);
            break;
        case 'qr_scan':
            processQRScan($conn, $input);
            break;
        case 'geo_checkin':
            processGeoCheckin($conn, $input);
            break;
        case 'mobile_checkin':
            processMobileCheckin($conn, $input);
            break;
        default:
            throw new Exception('Invalid action');
    }
}

function handleGET($conn, $action) {
    switch ($action) {
        case 'employee_status':
            getEmployeeStatus($conn, $_GET['employee_id'] ?? null);
            break;
        case 'attendance_summary':
            getAttendanceSummary($conn, $_GET['date'] ?? date('Y-m-d'));
            break;
        case 'biometric_devices':
            getBiometricDevices($conn);
            break;
        default:
            throw new Exception('Invalid action');
    }
}

function punchIn($conn, $data) {
    $employeeId = $data['employee_id'] ?? null;
    $method = $data['method'] ?? 'manual';
    $location = $data['location'] ?? null;
    $deviceId = $data['device_id'] ?? null;
    
    if (!$employeeId) {
        throw new Exception('Employee ID is required');
    }
    
    $today = date('Y-m-d');
    $timeIn = date('H:i:s');
    
    // Check if already punched in today
    $checkStmt = $conn->prepare("SELECT id, time_in FROM attendance WHERE employee_id = ? AND attendance_date = ?");
    $checkStmt->bind_param("is", $employeeId, $today);
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();
    
    if ($existing && !empty($existing['time_in'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Already punched in today at ' . date('h:i A', strtotime($existing['time_in'])),
            'data' => [
                'employee_id' => $employeeId,
                'time_in' => $existing['time_in'],
                'method' => $method
            ]
        ]);
        return;
    }
    
    // Determine status based on time
    $status = 'Present';
    $startTime = '09:00:00';
    if ($timeIn > $startTime) {
        $status = 'Late';
    }
    
    // Insert or update attendance
    if ($existing) {
        $stmt = $conn->prepare("UPDATE attendance SET time_in = ?, status = ?, punch_method = ?, location = ?, device_id = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $timeIn, $status, $method, $location, $deviceId, $existing['id']);
    } else {
        $stmt = $conn->prepare("INSERT INTO attendance (employee_id, attendance_date, time_in, status, punch_method, location, device_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", $employeeId, $today, $timeIn, $status, $method, $location, $deviceId);
    }
    
    if ($stmt->execute()) {
        // Log the activity
        logActivity($conn, $employeeId, 'PUNCH_IN', "Punched in at $timeIn via $method");
        
        echo json_encode([
            'success' => true,
            'message' => 'Punch in successful',
            'data' => [
                'employee_id' => $employeeId,
                'time_in' => $timeIn,
                'status' => $status,
                'method' => $method,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        throw new Exception('Failed to record punch in: ' . $conn->error);
    }
}

function punchOut($conn, $data) {
    $employeeId = $data['employee_id'] ?? null;
    $method = $data['method'] ?? 'manual';
    $location = $data['location'] ?? null;
    $deviceId = $data['device_id'] ?? null;
    
    if (!$employeeId) {
        throw new Exception('Employee ID is required');
    }
    
    $today = date('Y-m-d');
    $timeOut = date('H:i:s');
    
    // Check if record exists
    $checkStmt = $conn->prepare("SELECT id, time_in, time_out FROM attendance WHERE employee_id = ? AND attendance_date = ?");
    $checkStmt->bind_param("is", $employeeId, $today);
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();
    
    if (!$existing) {
        throw new Exception('No punch in record found for today');
    }
    
    if (!empty($existing['time_out'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Already punched out today at ' . date('h:i A', strtotime($existing['time_out'])),
            'data' => [
                'employee_id' => $employeeId,
                'time_out' => $existing['time_out'],
                'method' => $method
            ]
        ]);
        return;
    }
    
    // Update attendance with punch out
    $stmt = $conn->prepare("UPDATE attendance SET time_out = ?, punch_out_method = ?, out_location = ?, out_device_id = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $timeOut, $method, $location, $deviceId, $existing['id']);
    
    if ($stmt->execute()) {
        // Calculate work duration
        $timeIn = $existing['time_in'];
        $duration = calculateWorkDuration($timeIn, $timeOut);
        
        // Log the activity
        logActivity($conn, $employeeId, 'PUNCH_OUT', "Punched out at $timeOut via $method. Duration: $duration");
        
        echo json_encode([
            'success' => true,
            'message' => 'Punch out successful',
            'data' => [
                'employee_id' => $employeeId,
                'time_out' => $timeOut,
                'time_in' => $timeIn,
                'duration' => $duration,
                'method' => $method,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        throw new Exception('Failed to record punch out: ' . $conn->error);
    }
}

function processFaceRecognition($conn, $data) {
    $imageData = $data['image_data'] ?? null;
    $confidence = $data['confidence'] ?? 0;
    
    if (!$imageData) {
        throw new Exception('Face image data is required');
    }
    
    // Simulate face recognition processing
    sleep(2); // Simulate processing time
    
    // Mock recognition result (in real implementation, this would use ML/AI)
    $recognizedEmployeeId = $data['expected_employee_id'] ?? null;
    $recognitionConfidence = rand(85, 98);
    
    if ($recognitionConfidence >= 80) {
        // Process punch in/out based on face recognition
        $punchData = [
            'employee_id' => $recognizedEmployeeId,
            'method' => 'face_recognition',
            'confidence' => $recognitionConfidence
        ];
        
        // Determine if this should be punch in or out
        $today = date('Y-m-d');
        $checkStmt = $conn->prepare("SELECT time_in, time_out FROM attendance WHERE employee_id = ? AND attendance_date = ?");
        $checkStmt->bind_param("is", $recognizedEmployeeId, $today);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();
        
        if (!$existing || empty($existing['time_in'])) {
            punchIn($conn, $punchData);
        } else if (empty($existing['time_out'])) {
            punchOut($conn, $punchData);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Employee has already completed attendance for today',
                'data' => ['employee_id' => $recognizedEmployeeId]
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Face recognition failed. Confidence too low.',
            'data' => ['confidence' => $recognitionConfidence]
        ]);
    }
}

function processQRScan($conn, $data) {
    $qrData = $data['qr_data'] ?? null;
    
    if (!$qrData) {
        throw new Exception('QR data is required');
    }
    
    // Parse QR data (expected format: "EMP001|PUNCH_IN|timestamp")
    $qrParts = explode('|', $qrData);
    if (count($qrParts) !== 3) {
        throw new Exception('Invalid QR code format');
    }
    
    $employeeCode = $qrParts[0];
    $action = $qrParts[1];
    $qrTimestamp = $qrParts[2];
    
    // Validate QR timestamp (should be within 5 minutes)
    $currentTime = time();
    $qrTime = strtotime($qrTimestamp);
    if (($currentTime - $qrTime) > 300) { // 5 minutes
        throw new Exception('QR code has expired');
    }
    
    // Get employee ID from code
    $empStmt = $conn->prepare("SELECT employee_id FROM employees WHERE employee_code = ?");
    $empStmt->bind_param("s", $employeeCode);
    $empStmt->execute();
    $empResult = $empStmt->get_result()->fetch_assoc();
    
    if (!$empResult) {
        throw new Exception('Invalid employee code');
    }
    
    $punchData = [
        'employee_id' => $empResult['employee_id'],
        'method' => 'qr_scan'
    ];
    
    if ($action === 'PUNCH_IN') {
        punchIn($conn, $punchData);
    } else if ($action === 'PUNCH_OUT') {
        punchOut($conn, $punchData);
    } else {
        throw new Exception('Invalid QR action');
    }
}

function processGeoCheckin($conn, $data) {
    $employeeId = $data['employee_id'] ?? null;
    $latitude = $data['latitude'] ?? null;
    $longitude = $data['longitude'] ?? null;
    $accuracy = $data['accuracy'] ?? null;
    
    if (!$employeeId || !$latitude || !$longitude) {
        throw new Exception('Employee ID, latitude, and longitude are required');
    }
    
    // Define office location (example coordinates)
    $officeLatitude = 12.9716; // Bangalore coordinates
    $officeLongitude = 77.5946;
    $allowedRadius = 100; // meters
    
    // Calculate distance
    $distance = calculateDistance($latitude, $longitude, $officeLatitude, $officeLongitude);
    
    if ($distance <= $allowedRadius) {
        $punchData = [
            'employee_id' => $employeeId,
            'method' => 'geo_location',
            'location' => "$latitude,$longitude",
            'accuracy' => $accuracy
        ];
        
        // Determine punch in or out
        $today = date('Y-m-d');
        $checkStmt = $conn->prepare("SELECT time_in, time_out FROM attendance WHERE employee_id = ? AND attendance_date = ?");
        $checkStmt->bind_param("is", $employeeId, $today);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();
        
        if (!$existing || empty($existing['time_in'])) {
            punchIn($conn, $punchData);
        } else if (empty($existing['time_out'])) {
            punchOut($conn, $punchData);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Attendance already completed for today'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => "You are ${distance}m away from office. Please come closer.",
            'data' => ['distance' => $distance, 'allowed_radius' => $allowedRadius]
        ]);
    }
}

function processMobileCheckin($conn, $data) {
    $employeeId = $data['employee_id'] ?? null;
    $deviceId = $data['device_id'] ?? null;
    $appVersion = $data['app_version'] ?? null;
    
    if (!$employeeId) {
        throw new Exception('Employee ID is required');
    }
    
    $punchData = [
        'employee_id' => $employeeId,
        'method' => 'mobile_app',
        'device_id' => $deviceId
    ];
    
    // Determine punch in or out
    $today = date('Y-m-d');
    $checkStmt = $conn->prepare("SELECT time_in, time_out FROM attendance WHERE employee_id = ? AND attendance_date = ?");
    $checkStmt->bind_param("is", $employeeId, $today);
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();
    
    if (!$existing || empty($existing['time_in'])) {
        punchIn($conn, $punchData);
    } else if (empty($existing['time_out'])) {
        punchOut($conn, $punchData);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Attendance already completed for today'
        ]);
    }
}

function getEmployeeStatus($conn, $employeeId) {
    if (!$employeeId) {
        throw new Exception('Employee ID is required');
    }
    
    $today = date('Y-m-d');
    $stmt = $conn->prepare("
        SELECT a.*, e.name, e.employee_code 
        FROM attendance a 
        JOIN employees e ON a.employee_id = e.employee_id 
        WHERE a.employee_id = ? AND a.attendance_date = ?
    ");
    $stmt->bind_param("is", $employeeId, $today);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'data' => $result ?: ['message' => 'No attendance record found for today']
    ]);
}

function getAttendanceSummary($conn, $date) {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_employees,
            COUNT(CASE WHEN status = 'Present' THEN 1 END) as present,
            COUNT(CASE WHEN status = 'Absent' THEN 1 END) as absent,
            COUNT(CASE WHEN status = 'Late' THEN 1 END) as late,
            COUNT(CASE WHEN time_in IS NOT NULL THEN 1 END) as punched_in,
            COUNT(CASE WHEN time_out IS NOT NULL THEN 1 END) as punched_out
        FROM attendance 
        WHERE attendance_date = ?
    ");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $summary = $stmt->get_result()->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'data' => $summary,
        'date' => $date
    ]);
}

function getBiometricDevices($conn) {
    // Mock biometric devices data
    $devices = [
        ['id' => 'BIO001', 'name' => 'Main Entrance', 'status' => 'online', 'location' => 'Ground Floor'],
        ['id' => 'BIO002', 'name' => 'Office Floor', 'status' => 'online', 'location' => '1st Floor'],
        ['id' => 'BIO003', 'name' => 'Cafeteria', 'status' => 'offline', 'location' => '2nd Floor']
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $devices
    ]);
}

// Utility functions
function calculateWorkDuration($timeIn, $timeOut) {
    $in = strtotime($timeIn);
    $out = strtotime($timeOut);
    $duration = $out - $in;
    
    $hours = floor($duration / 3600);
    $minutes = floor(($duration % 3600) / 60);
    
    return sprintf('%02d:%02d', $hours, $minutes);
}

function calculateDistance($lat1, $lng1, $lat2, $lng2) {
    $earthRadius = 6371000; // Earth's radius in meters
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLng/2) * sin($dLng/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earthRadius * $c;
}

function logActivity($conn, $employeeId, $action, $details) {
    $stmt = $conn->prepare("INSERT INTO attendance_logs (employee_id, action, details, timestamp) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iss", $employeeId, $action, $details);
    $stmt->execute();
}

?>
