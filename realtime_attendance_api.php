<?php
/**
 * Real-time Attendance Tracking API
 * Handles biometric, mobile, and geo-location based attendance
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // For mobile app integration
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

include 'db.php';
date_default_timezone_set('Asia/Kolkata');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Authentication for API endpoints
if (!in_array($action, ['mobile_register', 'mobile_login']) && !isset($_SESSION['admin']) && !isset($_POST['api_key'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

switch ($action) {
    case 'punch_in':
        handlePunchIn();
        break;
    case 'punch_out':
        handlePunchOut();
        break;
    case 'biometric_verify':
        handleBiometricVerification();
        break;
    case 'mobile_punch':
        handleMobilePunch();
        break;
    case 'geo_punch':
        handleGeoPunch();
        break;
    case 'get_real_time_status':
        getRealTimeStatus();
        break;
    case 'update_mobile_status':
        updateMobileStatus();
        break;
    case 'check_geo_fence':
        checkGeoFence();
        break;
    case 'mobile_register':
        registerMobileDevice();
        break;
    case 'get_employee_schedule':
        getEmployeeSchedule();
        break;
    case 'face_recognition_punch':
        handleFaceRecognitionPunch();
        break;
    case 'qr_code_punch':
        handleQRCodePunch();
        break;
    case 'compliance_check':
        performComplianceCheck();
        break;
    case 'get_analytics_data':
        getAnalyticsData();
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

function handlePunchIn() {
    global $conn;
    
    try {
        $employeeId = intval($_POST['employee_id']);
        $punchMethod = mysqli_real_escape_string($conn, $_POST['punch_method'] ?? 'manual');
        $gpsCoordinates = mysqli_real_escape_string($conn, $_POST['gps_coordinates'] ?? '');
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $verificationScore = floatval($_POST['verification_score'] ?? 0);
        $photoPath = uploadPunchPhoto($_FILES['photo'] ?? null);
        
        $currentTime = date('H:i:s');
        $currentDate = date('Y-m-d');
        
        // Validate geo-fence if enabled
        if ($punchMethod === 'geo' || $punchMethod === 'mobile') {
            $geoValidation = validateGeoFence($gpsCoordinates);
            if (!$geoValidation['valid']) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Location verification failed',
                    'distance' => $geoValidation['distance']
                ]);
                return;
            }
        }
        
        // Check for duplicate punch
        $duplicateCheck = checkDuplicatePunch($employeeId, $currentDate, 'in');
        if ($duplicateCheck) {
            echo json_encode([
                'success' => false,
                'error' => 'Already punched in today',
                'existing_time' => $duplicateCheck['time_in']
            ]);
            return;
        }
        
        // Determine status based on time
        $status = determineAttendanceStatus($currentTime, 'in');
        
        // Insert or update attendance record
        $sql = "INSERT INTO attendance (
            employee_id, attendance_date, status, time_in, punch_method,
            punch_location, ip_address, user_agent, gps_coordinates,
            photo_path, verification_score, is_verified, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            time_in = VALUES(time_in),
            punch_method = VALUES(punch_method),
            punch_location = VALUES(punch_location),
            ip_address = VALUES(ip_address),
            gps_coordinates = VALUES(gps_coordinates),
            photo_path = VALUES(photo_path),
            verification_score = VALUES(verification_score),
            is_verified = VALUES(is_verified)";
        
        $isVerified = ($verificationScore >= 80) ? 1 : 0;
        $punchLocation = getPunchLocation($gpsCoordinates);
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "isssssssssdi",
            $employeeId, $currentDate, $status, $currentTime, $punchMethod,
            $punchLocation, $ipAddress, $userAgent, $gpsCoordinates,
            $photoPath, $verificationScore, $isVerified
        );
        
        if ($stmt->execute()) {
            // Update mobile device session
            updateMobileDeviceSession($employeeId, $gpsCoordinates);
            
            // Check compliance
            $complianceIssues = checkPunchCompliance($employeeId, $currentDate, $currentTime, 'in');
            
            // Log activity
            logPunchActivity($employeeId, 'punch_in', $punchMethod, $currentTime);
            
            // Send notifications if required
            if ($status === 'Late') {
                scheduleNotification($employeeId, 'late_arrival', "Late arrival at {$currentTime}");
            }
            
            echo json_encode([
                'success' => true,
                'status' => $status,
                'punch_time' => $currentTime,
                'punch_method' => $punchMethod,
                'verification_score' => $verificationScore,
                'is_verified' => (bool)$isVerified,
                'compliance_issues' => $complianceIssues,
                'message' => 'Punch in successful'
            ]);
        } else {
            throw new Exception('Database error: ' . $stmt->error);
        }
        
    } catch (Exception $e) {
        error_log("Punch in error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to record punch in']);
    }
}

function handlePunchOut() {
    global $conn;
    
    try {
        $employeeId = intval($_POST['employee_id']);
        $punchMethod = mysqli_real_escape_string($conn, $_POST['punch_method'] ?? 'manual');
        $gpsCoordinates = mysqli_real_escape_string($conn, $_POST['gps_coordinates'] ?? '');
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        $verificationScore = floatval($_POST['verification_score'] ?? 0);
        $photoPath = uploadPunchPhoto($_FILES['photo'] ?? null);
        
        $currentTime = date('H:i:s');
        $currentDate = date('Y-m-d');
        
        // Check if punched in
        $punchInCheck = checkExistingPunchIn($employeeId, $currentDate);
        if (!$punchInCheck) {
            echo json_encode([
                'success' => false,
                'error' => 'No punch in record found for today'
            ]);
            return;
        }
        
        // Calculate work duration and overtime
        $workDuration = calculateWorkDuration($punchInCheck['time_in'], $currentTime);
        $overtimeHours = calculateOvertime($workDuration);
        
        // Update attendance record
        $sql = "UPDATE attendance SET 
            time_out = ?, 
            punch_method = CONCAT(punch_method, ',', ?),
            ip_address = CONCAT(COALESCE(ip_address, ''), ',', ?),
            gps_coordinates = CONCAT(COALESCE(gps_coordinates, ''), ',', ?),
            photo_path = CONCAT(COALESCE(photo_path, ''), ',', ?),
            verification_score = (verification_score + ?) / 2,
            overtime_hours = ?,
            is_verified = ?
            WHERE employee_id = ? AND attendance_date = ?";
        
        $isVerified = ($verificationScore >= 80) ? 1 : 0;
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sssssddiis",
            $currentTime, $punchMethod, $ipAddress, $gpsCoordinates,
            $photoPath, $verificationScore, $overtimeHours, $isVerified,
            $employeeId, $currentDate
        );
        
        if ($stmt->execute()) {
            // Check compliance for work hours
            $complianceIssues = checkWorkHoursCompliance($employeeId, $workDuration, $overtimeHours);
            
            // Log activity  
            logPunchActivity($employeeId, 'punch_out', $punchMethod, $currentTime);
            
            // Schedule notifications
            if ($overtimeHours > 0) {
                scheduleNotification($employeeId, 'overtime_alert', "Overtime: {$overtimeHours} hours");
            }
            
            echo json_encode([
                'success' => true,
                'punch_time' => $currentTime,
                'work_duration' => $workDuration,
                'overtime_hours' => $overtimeHours,
                'punch_method' => $punchMethod,
                'verification_score' => $verificationScore,
                'compliance_issues' => $complianceIssues,
                'message' => 'Punch out successful'
            ]);
        } else {
            throw new Exception('Database error: ' . $stmt->error);
        }
        
    } catch (Exception $e) {
        error_log("Punch out error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to record punch out']);
    }
}

function handleBiometricVerification() {
    global $conn;
    
    try {
        $employeeId = intval($_POST['employee_id']);
        $biometricData = $_POST['biometric_data'] ?? '';
        $templateHash = $_POST['template_hash'] ?? '';
        
        // Simulate biometric verification (in real implementation, this would integrate with biometric SDK)
        $verificationResult = verifyBiometricTemplate($employeeId, $templateHash);
        
        if ($verificationResult['success']) {
            echo json_encode([
                'success' => true,
                'verification_score' => $verificationResult['score'],
                'confidence' => $verificationResult['confidence'],
                'message' => 'Biometric verification successful'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Biometric verification failed',
                'reason' => $verificationResult['reason']
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Biometric verification error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Biometric verification failed']);
    }
}

function handleMobilePunch() {
    global $conn;
    
    try {
        $employeeId = intval($_POST['employee_id']);
        $deviceId = mysqli_real_escape_string($conn, $_POST['device_id']);
        $punchType = mysqli_real_escape_string($conn, $_POST['punch_type']); // 'in' or 'out'
        $gpsCoordinates = mysqli_real_escape_string($conn, $_POST['gps_coordinates'] ?? '');
        $batteryLevel = intval($_POST['battery_level'] ?? 0);
        $appVersion = mysqli_real_escape_string($conn, $_POST['app_version'] ?? '');
        
        // Validate mobile device session
        $deviceValidation = validateMobileDevice($employeeId, $deviceId);
        if (!$deviceValidation['valid']) {
            echo json_encode([
                'success' => false,
                'error' => 'Device not registered or expired session'
            ]);
            return;
        }
        
        // Update device session
        updateMobileDeviceSession($employeeId, $gpsCoordinates, $batteryLevel);
        
        // Process punch based on type
        $_POST['punch_method'] = 'mobile';
        $_POST['verification_score'] = 85; // Mobile app provides good verification
        
        if ($punchType === 'in') {
            handlePunchIn();
        } else {
            handlePunchOut();
        }
        
    } catch (Exception $e) {
        error_log("Mobile punch error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Mobile punch failed']);
    }
}

function handleGeoPunch() {
    global $conn;
    
    try {
        $employeeId = intval($_POST['employee_id']);
        $latitude = floatval($_POST['latitude']);
        $longitude = floatval($_POST['longitude']);
        $accuracy = intval($_POST['accuracy'] ?? 50);
        $punchType = mysqli_real_escape_string($conn, $_POST['punch_type']);
        
        $gpsCoordinates = "{$latitude},{$longitude}";
        
        // Validate geo-fence
        $geoValidation = validateGeoFence($gpsCoordinates);
        if (!$geoValidation['valid']) {
            echo json_encode([
                'success' => false,
                'error' => 'Outside office geo-fence',
                'distance' => $geoValidation['distance'],
                'required_distance' => $geoValidation['fence_radius']
            ]);
            return;
        }
        
        // Check GPS accuracy
        if ($accuracy > 100) {
            echo json_encode([
                'success' => false,
                'error' => 'GPS accuracy too low',
                'current_accuracy' => $accuracy,
                'required_accuracy' => 100
            ]);
            return;
        }
        
        $_POST['punch_method'] = 'geo';
        $_POST['verification_score'] = min(95, 100 - ($accuracy / 2)); // Better accuracy = higher score
        $_POST['gps_coordinates'] = $gpsCoordinates;
        
        if ($punchType === 'in') {
            handlePunchIn();
        } else {
            handlePunchOut();
        }
        
    } catch (Exception $e) {
        error_log("Geo punch error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Geo punch failed']);
    }
}

function getRealTimeStatus() {
    global $conn;
    
    try {
        $date = $_GET['date'] ?? date('Y-m-d');
        
        $sql = "SELECT 
            a.employee_id,
            e.name,
            e.employee_code,
            a.status,
            a.time_in,
            a.time_out,
            a.punch_method,
            a.is_verified,
            a.overtime_hours,
            mds.is_online,
            mds.last_active,
            mds.battery_level,
            mds.location_lat,
            mds.location_lng
        FROM attendance a
        JOIN employees e ON a.employee_id = e.employee_id
        LEFT JOIN mobile_device_sessions mds ON a.employee_id = mds.employee_id
        WHERE a.attendance_date = ? AND e.status = 'active'
        ORDER BY a.time_in DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $realTimeData = [];
        while ($row = $result->fetch_assoc()) {
            $row['location_status'] = determineLocationStatus($row['location_lat'], $row['location_lng']);
            $row['work_duration'] = calculateWorkDurationFromRecord($row);
            $realTimeData[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'date' => $date,
            'real_time_data' => $realTimeData,
            'total_employees' => count($realTimeData),
            'last_updated' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        error_log("Get real-time status error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to get real-time status']);
    }
}

function registerMobileDevice() {
    global $conn;
    
    try {
        $employeeId = intval($_POST['employee_id']);
        $deviceId = mysqli_real_escape_string($conn, $_POST['device_id']);
        $deviceName = mysqli_real_escape_string($conn, $_POST['device_name'] ?? '');
        $osVersion = mysqli_real_escape_string($conn, $_POST['os_version'] ?? '');
        $appVersion = mysqli_real_escape_string($conn, $_POST['app_version'] ?? '');
        $fcmToken = mysqli_real_escape_string($conn, $_POST['fcm_token'] ?? '');
        
        $sql = "INSERT INTO mobile_device_sessions (
            employee_id, device_id, device_name, os_version, app_version, 
            fcm_token, last_active, is_online
        ) VALUES (?, ?, ?, ?, ?, ?, NOW(), 1)
        ON DUPLICATE KEY UPDATE
            device_name = VALUES(device_name),
            os_version = VALUES(os_version),
            app_version = VALUES(app_version),
            fcm_token = VALUES(fcm_token),
            last_active = NOW(),
            is_online = 1";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssss", $employeeId, $deviceId, $deviceName, $osVersion, $appVersion, $fcmToken);
        
        if ($stmt->execute()) {
            // Generate API key for mobile app
            $apiKey = generateMobileApiKey($employeeId, $deviceId);
            
            echo json_encode([
                'success' => true,
                'api_key' => $apiKey,
                'session_id' => $conn->insert_id ?: $employeeId . '_' . $deviceId,
                'message' => 'Device registered successfully'
            ]);
        } else {
            throw new Exception('Database error: ' . $stmt->error);
        }
        
    } catch (Exception $e) {
        error_log("Mobile registration error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Device registration failed']);
    }
}

// Helper Functions

function validateGeoFence($gpsCoordinates) {
    global $conn;
    
    if (empty($gpsCoordinates)) {
        return ['valid' => false, 'distance' => 9999];
    }
    
    $coords = explode(',', $gpsCoordinates);
    if (count($coords) != 2) {
        return ['valid' => false, 'distance' => 9999];
    }
    
    $lat = floatval($coords[0]);
    $lng = floatval($coords[1]);
    
    // Get office geo-fence
    $sql = "SELECT center_lat, center_lng, radius_meters 
            FROM geo_fences 
            WHERE is_office_location = 1 AND is_active = 1 
            LIMIT 1";
    
    $result = $conn->query($sql);
    if ($fence = $result->fetch_assoc()) {
        $distance = calculateDistance($lat, $lng, $fence['center_lat'], $fence['center_lng']);
        $distanceMeters = $distance * 1000;
        
        return [
            'valid' => $distanceMeters <= $fence['radius_meters'],
            'distance' => round($distanceMeters),
            'fence_radius' => $fence['radius_meters']
        ];
    }
    
    return ['valid' => true, 'distance' => 0]; // Default allow if no geo-fence configured
}

function calculateDistance($lat1, $lng1, $lat2, $lng2) {
    $earthRadius = 6371; // kilometers
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLng/2) * sin($dLng/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earthRadius * $c;
}

function determineAttendanceStatus($punchTime, $punchType) {
    // Get office start time from policy settings
    $officeStartTime = '09:00:00';
    $gracePeriod = 15; // minutes
    
    if ($punchType === 'in') {
        $punchDateTime = new DateTime($punchTime);
        $officeStartDateTime = new DateTime($officeStartTime);
        $graceDateTime = clone $officeStartDateTime;
        $graceDateTime->add(new DateInterval('PT' . $gracePeriod . 'M'));
        
        if ($punchDateTime <= $officeStartDateTime) {
            return 'Present';
        } elseif ($punchDateTime <= $graceDateTime) {
            return 'Present'; // Within grace period
        } else {
            return 'Late';
        }
    }
    
    return 'Present';
}

function checkDuplicatePunch($employeeId, $date, $punchType) {
    global $conn;
    
    $field = $punchType === 'in' ? 'time_in' : 'time_out';
    $sql = "SELECT {$field} FROM attendance 
            WHERE employee_id = ? AND attendance_date = ? AND {$field} IS NOT NULL";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $employeeId, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

function checkExistingPunchIn($employeeId, $date) {
    global $conn;
    
    $sql = "SELECT time_in FROM attendance 
            WHERE employee_id = ? AND attendance_date = ? AND time_in IS NOT NULL";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $employeeId, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

function calculateWorkDuration($timeIn, $timeOut) {
    $start = new DateTime($timeIn);
    $end = new DateTime($timeOut);
    $interval = $start->diff($end);
    
    return $interval->h + ($interval->i / 60);
}

function calculateOvertime($workDuration) {
    $standardHours = 8;
    return max(0, $workDuration - $standardHours);
}

function updateMobileDeviceSession($employeeId, $gpsCoordinates = '', $batteryLevel = null) {
    global $conn;
    
    $updates = ['last_active = NOW()', 'is_online = 1'];
    $params = [];
    $types = '';
    
    if (!empty($gpsCoordinates)) {
        $coords = explode(',', $gpsCoordinates);
        if (count($coords) == 2) {
            $updates[] = 'location_lat = ?';
            $updates[] = 'location_lng = ?';
            $params[] = floatval($coords[0]);
            $params[] = floatval($coords[1]);
            $types .= 'dd';
        }
    }
    
    if ($batteryLevel !== null) {
        $updates[] = 'battery_level = ?';
        $params[] = intval($batteryLevel);
        $types .= 'i';
    }
    
    $params[] = $employeeId;
    $types .= 'i';
    
    $sql = "UPDATE mobile_device_sessions SET " . implode(', ', $updates) . " 
            WHERE employee_id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
}

function verifyBiometricTemplate($employeeId, $templateHash) {
    // Simulate biometric verification
    // In real implementation, this would call biometric SDK
    
    $simulatedScore = rand(75, 98);
    $threshold = 80;
    
    return [
        'success' => $simulatedScore >= $threshold,
        'score' => $simulatedScore,
        'confidence' => min(99, $simulatedScore + rand(1, 5)),
        'reason' => $simulatedScore < $threshold ? 'Low confidence score' : 'Verification successful'
    ];
}

function validateMobileDevice($employeeId, $deviceId) {
    global $conn;
    
    $sql = "SELECT id, last_active FROM mobile_device_sessions 
            WHERE employee_id = ? AND device_id = ? AND is_online = 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $employeeId, $deviceId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($device = $result->fetch_assoc()) {
        $lastActive = new DateTime($device['last_active']);
        $now = new DateTime();
        $interval = $now->diff($lastActive);
        
        // Session valid for 24 hours
        return ['valid' => $interval->h < 24];
    }
    
    return ['valid' => false];
}

function uploadPunchPhoto($file) {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    $uploadDir = 'uploads/punch_photos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $fileName = date('Y-m-d_H-i-s') . '_' . uniqid() . '.jpg';
    $filePath = $uploadDir . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        return $filePath;
    }
    
    return null;
}

function logPunchActivity($employeeId, $action, $method, $time) {
    global $conn;
    
    $description = ucfirst(str_replace('_', ' ', $action)) . " via {$method} at {$time}";
    
    $sql = "INSERT INTO activity_logs (user_id, user_type, action, description, created_at)
            VALUES (?, 'employee', ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $employeeId, $action, $description);
    $stmt->execute();
}

function scheduleNotification($employeeId, $type, $message) {
    global $conn;
    
    $sql = "INSERT INTO notification_queue (
        recipient_id, recipient_type, notification_type, subject, message, channel
    ) VALUES (?, 'employee', ?, ?, ?, 'push')";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isss", $employeeId, $type, $type, $message);
    $stmt->execute();
}

function checkPunchCompliance($employeeId, $date, $time, $type) {
    $issues = [];
    
    // Check working hours compliance
    if ($type === 'in') {
        $hour = intval(substr($time, 0, 2));
        if ($hour < 6) {
            $issues[] = 'Unusually early punch in';
        } elseif ($hour > 12) {
            $issues[] = 'Very late punch in';
        }
    }
    
    return $issues;
}

function generateMobileApiKey($employeeId, $deviceId) {
    return hash('sha256', $employeeId . '_' . $deviceId . '_' . time() . '_' . rand());
}

function getPunchLocation($gpsCoordinates) {
    if (empty($gpsCoordinates)) {
        return 'Unknown';
    }
    
    // In real implementation, this would do reverse geocoding
    $coords = explode(',', $gpsCoordinates);
    if (count($coords) == 2) {
        return "Location: {$coords[0]}, {$coords[1]}";
    }
    
    return 'Unknown';
}

function checkWorkHoursCompliance($employeeId, $workDuration, $overtimeHours) {
    $issues = [];
    
    if ($workDuration > 12) {
        $issues[] = 'Exceeded maximum daily work hours (12h)';
    }
    
    if ($overtimeHours > 4) {
        $issues[] = 'Excessive overtime hours';
    }
    
    return $issues;
}

function determineLocationStatus($lat, $lng) {
    if (!$lat || !$lng) {
        return 'unknown';
    }
    
    // Simple location determination based on distance from office
    $officeCoords = [12.9716, 77.5946]; // Example office coordinates
    $distance = calculateDistance($lat, $lng, $officeCoords[0], $officeCoords[1]);
    
    if ($distance <= 0.1) return 'office';
    if ($distance <= 2) return 'nearby';
    return 'remote';
}

function calculateWorkDurationFromRecord($record) {
    if (!$record['time_in'] || !$record['time_out']) {
        return null;
    }
    
    return calculateWorkDuration($record['time_in'], $record['time_out']);
}

?>
