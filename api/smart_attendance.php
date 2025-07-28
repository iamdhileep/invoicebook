<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config.php';
require_once '../auth_check.php';

try {
    // Database connection
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Get input data
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? $_GET;
    $action = $input['action'] ?? $_REQUEST['action'] ?? '';

    switch ($action) {
        case 'punch_attendance':
            $employeeId = $input['employee_id'] ?? 0;
            $punchType = $input['punch_type'] ?? 'in'; // 'in' or 'out'
            $location = $input['location'] ?? '';
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $gpsLat = $input['gps_lat'] ?? null;
            $gpsLng = $input['gps_lng'] ?? null;
            $method = $input['method'] ?? 'manual'; // 'manual', 'face', 'qr', 'biometric'

            // Validation
            if (!$employeeId) {
                throw new Exception('Employee ID is required');
            }

            // Check if employee exists and is active
            $empCheck = $conn->prepare("SELECT id, first_name, last_name, status FROM employees WHERE id = ? AND status = 'active'");
            $empCheck->bind_param("i", $employeeId);
            $empCheck->execute();
            $employee = $empCheck->get_result()->fetch_assoc();
            
            if (!$employee) {
                throw new Exception('Employee not found or inactive');
            }

            $currentDate = date('Y-m-d');
            $currentTime = date('H:i:s');
            $currentDateTime = date('Y-m-d H:i:s');

            // Check for existing punch today
            $existingPunch = $conn->prepare("
                SELECT punch_in_time, punch_out_time 
                FROM attendance 
                WHERE employee_id = ? AND date = ?
            ");
            $existingPunch->bind_param("is", $employeeId, $currentDate);
            $existingPunch->execute();
            $todayAttendance = $existingPunch->get_result()->fetch_assoc();

            if ($punchType === 'in') {
                if ($todayAttendance && $todayAttendance['punch_in_time']) {
                    throw new Exception('Already punched in for today');
                }

                // Insert or update attendance record
                if ($todayAttendance) {
                    $stmt = $conn->prepare("
                        UPDATE attendance 
                        SET punch_in_time = ?, location = ?, ip_address = ?, gps_lat = ?, gps_lng = ?, 
                            punch_method = ?, marked_by = ?, updated_at = NOW()
                        WHERE employee_id = ? AND date = ?
                    ");
                    $stmt->bind_param("sssddsiis", $currentTime, $location, $ipAddress, $gpsLat, $gpsLng, $method, $employeeId, $employeeId, $currentDate);
                } else {
                    $stmt = $conn->prepare("
                        INSERT INTO attendance (employee_id, date, punch_in_time, location, ip_address, gps_lat, gps_lng, punch_method, marked_by, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $stmt->bind_param("isssddsis", $employeeId, $currentDate, $currentTime, $location, $ipAddress, $gpsLat, $gpsLng, $method, $employeeId);
                }
            } else { // punch out
                if (!$todayAttendance || !$todayAttendance['punch_in_time']) {
                    throw new Exception('Must punch in first');
                }
                
                if ($todayAttendance['punch_out_time']) {
                    throw new Exception('Already punched out for today');
                }

                // Calculate work duration
                $punchInDateTime = $currentDate . ' ' . $todayAttendance['punch_in_time'];
                $punchOutDateTime = $currentDateTime;
                $workDuration = strtotime($punchOutDateTime) - strtotime($punchInDateTime);
                $workHours = round($workDuration / 3600, 2);

                $stmt = $conn->prepare("
                    UPDATE attendance 
                    SET punch_out_time = ?, work_duration = ?, updated_at = NOW()
                    WHERE employee_id = ? AND date = ?
                ");
                $stmt->bind_param("sdis", $currentTime, $workHours, $employeeId, $currentDate);
            }

            if ($stmt->execute()) {
                $message = $punchType === 'in' ? 'Punched in successfully' : 'Punched out successfully';
                
                echo json_encode([
                    'success' => true,
                    'message' => $message,
                    'data' => [
                        'employee_id' => $employeeId,
                        'employee_name' => $employee['first_name'] . ' ' . $employee['last_name'],
                        'punch_type' => $punchType,
                        'time' => $currentTime,
                        'date' => $currentDate,
                        'location' => $location,
                        'method' => $method
                    ]
                ]);
            } else {
                throw new Exception('Failed to record attendance');
            }
            break;

        case 'get_attendance_status':
            $employeeId = $input['employee_id'] ?? 0;
            $date = $input['date'] ?? date('Y-m-d');

            if (!$employeeId) {
                throw new Exception('Employee ID is required');
            }

            // Get today's attendance
            $stmt = $conn->prepare("
                SELECT a.*, e.first_name, e.last_name
                FROM attendance a
                JOIN employees e ON a.employee_id = e.id
                WHERE a.employee_id = ? AND a.date = ?
            ");
            $stmt->bind_param("is", $employeeId, $date);
            $stmt->execute();
            $attendance = $stmt->get_result()->fetch_assoc();

            if ($attendance) {
                $workDuration = 0;
                if ($attendance['punch_in_time'] && $attendance['punch_out_time']) {
                    $punchIn = strtotime($date . ' ' . $attendance['punch_in_time']);
                    $punchOut = strtotime($date . ' ' . $attendance['punch_out_time']);
                    $workDuration = round(($punchOut - $punchIn) / 3600, 2);
                }

                echo json_encode([
                    'success' => true,
                    'attendance' => [
                        'employee_id' => $attendance['employee_id'],
                        'employee_name' => $attendance['first_name'] . ' ' . $attendance['last_name'],
                        'date' => $attendance['date'],
                        'punch_in_time' => $attendance['punch_in_time'],
                        'punch_out_time' => $attendance['punch_out_time'],
                        'work_duration' => $workDuration,
                        'location' => $attendance['location'],
                        'punch_method' => $attendance['punch_method'],
                        'status' => $attendance['punch_out_time'] ? 'completed' : 'checked_in'
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'attendance' => null,
                    'status' => 'not_checked_in'
                ]);
            }
            break;

        case 'verify_face':
            $employeeId = $input['employee_id'] ?? 0;
            $faceData = $input['face_data'] ?? '';

            // Simulate face verification
            if (!$employeeId || !$faceData) {
                throw new Exception('Employee ID and face data are required');
            }

            // In a real implementation, this would use actual face recognition
            $confidence = rand(85, 99); // Simulate confidence score
            $verified = $confidence > 80;

            echo json_encode([
                'success' => true,
                'verified' => $verified,
                'confidence' => $confidence,
                'message' => $verified ? 'Face verified successfully' : 'Face verification failed'
            ]);
            break;

        case 'verify_qr':
            $qrData = $input['qr_data'] ?? '';
            $employeeId = $input['employee_id'] ?? 0;

            if (!$qrData) {
                throw new Exception('QR data is required');
            }

            // Decode QR data (assuming format: employee_id|timestamp|hash)
            $qrParts = explode('|', $qrData);
            if (count($qrParts) !== 3) {
                throw new Exception('Invalid QR code format');
            }

            list($qrEmployeeId, $timestamp, $hash) = $qrParts;

            // Verify QR code is not expired (5 minutes)
            if (time() - intval($timestamp) > 300) {
                throw new Exception('QR code has expired');
            }

            // Verify hash (simple verification)
            $expectedHash = md5($qrEmployeeId . $timestamp . 'secret_key');
            if ($hash !== $expectedHash) {
                throw new Exception('Invalid QR code');
            }

            // Check if employee matches
            if ($employeeId && $employeeId != $qrEmployeeId) {
                throw new Exception('QR code does not match selected employee');
            }

            echo json_encode([
                'success' => true,
                'verified' => true,
                'employee_id' => $qrEmployeeId,
                'message' => 'QR code verified successfully'
            ]);
            break;

        case 'generate_qr':
            $employeeId = $input['employee_id'] ?? 0;

            if (!$employeeId) {
                throw new Exception('Employee ID is required');
            }

            // Generate QR data
            $timestamp = time();
            $hash = md5($employeeId . $timestamp . 'secret_key');
            $qrData = $employeeId . '|' . $timestamp . '|' . $hash;

            echo json_encode([
                'success' => true,
                'qr_data' => $qrData,
                'expires_at' => date('Y-m-d H:i:s', $timestamp + 300),
                'message' => 'QR code generated successfully'
            ]);
            break;

        case 'get_location_validation':
            $lat = floatval($input['lat'] ?? 0);
            $lng = floatval($input['lng'] ?? 0);

            // Office location (example coordinates)
            $officeLat = 23.8103; // Example: Dhaka coordinates
            $officeLng = 90.4125;
            $allowedRadius = 500; // 500 meters

            // Calculate distance using Haversine formula
            $earthRadius = 6371000; // meters
            $dLat = deg2rad($lat - $officeLat);
            $dLng = deg2rad($lng - $officeLng);
            $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($officeLat)) * cos(deg2rad($lat)) * sin($dLng/2) * sin($dLng/2);
            $c = 2 * atan2(sqrt($a), sqrt(1-$a));
            $distance = $earthRadius * $c;

            $isValid = $distance <= $allowedRadius;

            echo json_encode([
                'success' => true,
                'location_valid' => $isValid,
                'distance' => round($distance, 2),
                'allowed_radius' => $allowedRadius,
                'message' => $isValid ? 'Location is valid' : 'You are outside the allowed area'
            ]);
            break;

        default:
            throw new Exception('Invalid action specified');
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>