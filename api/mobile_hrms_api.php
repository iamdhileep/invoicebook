<?php
/**
 * Mobile HRMS API - Geo-tagged attendance and mobile-specific features
 * Supports mobile app integration and location-based attendance
 */
session_start();
require_once '../db.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'mobile_clock_in':
            handleMobileClockIn($conn, $input);
            break;
            
        case 'mobile_clock_out':
            handleMobileClockOut($conn, $input);
            break;
            
        case 'validate_location':
            if (validateOfficeLocation($conn, $input['latitude'], $input['longitude'])) {
                echo json_encode(['success' => true, 'location_valid' => true]);
            } else {
                echo json_encode(['success' => false, 'location_valid' => false, 'message' => 'Not within office premises']);
            }
            break;
            
        case 'get_mobile_dashboard':
            getMobileDashboard($conn, $input);
            break;
            
        case 'submit_mobile_leave':
            submitMobileLeaveRequest($conn, $input);
            break;
            
        case 'get_team_schedule':
            getTeamSchedule($conn, $input);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handleMobileClockIn($conn, $input) {
    $employee_id = $_SESSION['employee_id'] ?? null;
    $latitude = $input['latitude'] ?? null;
    $longitude = $input['longitude'] ?? null;
    $location_address = $input['location_address'] ?? '';
    $device_id = $input['device_id'] ?? '';
    $face_photo = $input['face_photo'] ?? null; // Base64 encoded face photo
    
    if (!$employee_id) {
        throw new Exception('Employee ID not found in session');
    }
    
    // Validate location
    $location_valid = validateOfficeLocation($conn, $latitude, $longitude);
    
    if (!$location_valid) {
        echo json_encode([
            'success' => false, 
            'message' => 'You are not within the approved office location. Please ensure you are at the office premises.',
            'location_error' => true
        ]);
        return;
    }
    
    $conn->begin_transaction();
    
    try {
        $current_time = date('Y-m-d H:i:s');
        
        // Check if already clocked in today
        $stmt = $conn->prepare("SELECT id FROM attendance WHERE employee_id = ? AND DATE(check_in) = CURDATE() AND check_out IS NULL");
        $stmt->bind_param("i", $employee_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        
        if ($existing) {
            throw new Exception('You have already clocked in today and have not clocked out yet.');
        }
        
        // Insert attendance record
        $stmt = $conn->prepare("INSERT INTO attendance (employee_id, check_in, location_lat, location_lng, device_id, face_photo, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt->bind_param("isddssss", $employee_id, $current_time, $latitude, $longitude, $device_id, $face_photo, $ip_address, $current_time);
        $stmt->execute();
        
        $attendance_id = $conn->insert_id;
        
        // Log mobile access
        logMobileAccess($conn, $_SESSION['user_id'], 'clock_in', $latitude, $longitude, $location_address, $device_id);
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Successfully clocked in',
            'time' => date('H:i', strtotime($current_time)),
            'attendance_id' => $attendance_id,
            'location_verified' => true
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function handleMobileClockOut($conn, $input) {
    $employee_id = $_SESSION['employee_id'] ?? null;
    $latitude = $input['latitude'] ?? null;
    $longitude = $input['longitude'] ?? null;
    $location_address = $input['location_address'] ?? '';
    $device_id = $input['device_id'] ?? '';
    
    if (!$employee_id) {
        throw new Exception('Employee ID not found in session');
    }
    
    $conn->begin_transaction();
    
    try {
        $current_time = date('Y-m-d H:i:s');
        
        // Find today's attendance record
        $stmt = $conn->prepare("SELECT id, check_in FROM attendance WHERE employee_id = ? AND DATE(check_in) = CURDATE() AND check_out IS NULL ORDER BY check_in DESC LIMIT 1");
        $stmt->bind_param("i", $employee_id);
        $stmt->execute();
        $attendance = $stmt->get_result()->fetch_assoc();
        
        if (!$attendance) {
            throw new Exception('No active clock-in found for today');
        }
        
        // Update attendance record
        $stmt = $conn->prepare("UPDATE attendance SET check_out = ?, out_location = ?, out_device_id = ? WHERE id = ?");
        $stmt->bind_param("sssi", $current_time, $location_address, $device_id, $attendance['id']);
        $stmt->execute();
        
        // Calculate working hours
        $check_in = strtotime($attendance['check_in']);
        $check_out = strtotime($current_time);
        $hours_worked = ($check_out - $check_in) / 3600;
        
        // Update working hours
        $stmt = $conn->prepare("UPDATE attendance SET work_duration = ? WHERE id = ?");
        $stmt->bind_param("di", $hours_worked, $attendance['id']);
        $stmt->execute();
        
        // Log mobile access
        logMobileAccess($conn, $_SESSION['user_id'], 'clock_out', $latitude, $longitude, $location_address, $device_id);
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Successfully clocked out',
            'time' => date('H:i', strtotime($current_time)),
            'total_hours' => round($hours_worked, 2)
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function validateOfficeLocation($conn, $latitude, $longitude) {
    // Define office locations (you can store these in database)
    $office_locations = [
        ['lat' => 12.9716, 'lng' => 77.5946, 'radius' => 100], // Bangalore office
        ['lat' => 19.0760, 'lng' => 72.8777, 'radius' => 100], // Mumbai office
        // Add more office locations as needed
    ];
    
    foreach ($office_locations as $office) {
        $distance = calculateDistance($latitude, $longitude, $office['lat'], $office['lng']);
        if ($distance <= $office['radius']) {
            return true;
        }
    }
    
    return false; // Not within any office location
}

function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371000; // Earth radius in meters
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earth_radius * $c;
}

function getMobileDashboard($conn, $input) {
    $employee_id = $_SESSION['employee_id'] ?? null;
    $user_id = $_SESSION['user_id'];
    
    // Get today's attendance status
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT check_in, check_out FROM attendance WHERE employee_id = ? AND DATE(check_in) = ? ORDER BY check_in DESC LIMIT 1");
    $stmt->bind_param("is", $employee_id, $today);
    $stmt->execute();
    $today_attendance = $stmt->get_result()->fetch_assoc();
    
    $attendance_status = 'not_clocked_in';
    $current_session = null;
    
    if ($today_attendance) {
        if ($today_attendance['check_out']) {
            $attendance_status = 'clocked_out';
            $check_in = strtotime($today_attendance['check_in']);
            $check_out = strtotime($today_attendance['check_out']);
            $current_session = [
                'check_in' => date('H:i', $check_in),
                'check_out' => date('H:i', $check_out),
                'duration' => round(($check_out - $check_in) / 3600, 2)
            ];
        } else {
            $attendance_status = 'clocked_in';
            $check_in = strtotime($today_attendance['check_in']);
            $current_session = [
                'check_in' => date('H:i', $check_in),
                'current_duration' => round((time() - $check_in) / 3600, 2)
            ];
        }
    }
    
    // Get pending leave requests
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM leave_requests WHERE employee_id = ? AND status = 'pending'");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $pending_leaves = $stmt->get_result()->fetch_assoc()['count'];
    
    // Get notifications
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notification_queue WHERE recipient_id = ? AND status = 'pending'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $notifications_count = $stmt->get_result()->fetch_assoc()['count'];
    
    // Get this month's attendance summary
    $month_start = date('Y-m-01');
    $month_end = date('Y-m-t');
    
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT DATE(check_in)) as days_present FROM attendance WHERE employee_id = ? AND DATE(check_in) BETWEEN ? AND ?");
    $stmt->bind_param("iss", $employee_id, $month_start, $month_end);
    $stmt->execute();
    $days_present = $stmt->get_result()->fetch_assoc()['days_present'];
    
    $working_days = date('j'); // Current day of month
    $attendance_percentage = $working_days > 0 ? round(($days_present / $working_days) * 100, 1) : 0;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'attendance_status' => $attendance_status,
            'current_session' => $current_session,
            'pending_leaves' => $pending_leaves,
            'notifications_count' => $notifications_count,
            'monthly_summary' => [
                'days_present' => $days_present,
                'working_days' => $working_days,
                'attendance_percentage' => $attendance_percentage
            ]
        ]
    ]);
}

function submitMobileLeaveRequest($conn, $input) {
    $employee_id = $_SESSION['employee_id'] ?? null;
    $from_date = $input['from_date'];
    $to_date = $input['to_date'];
    $leave_type = $input['leave_type'];
    $reason = $input['reason'];
    $is_emergency = $input['is_emergency'] ?? false;
    
    $conn->begin_transaction();
    
    try {
        // Calculate days
        $days_requested = (strtotime($to_date) - strtotime($from_date)) / (60 * 60 * 24) + 1;
        
        // Insert leave request with mobile flag
        $stmt = $conn->prepare("INSERT INTO leave_requests (employee_id, from_date, to_date, leave_type, reason, days_requested, status, applied_date) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt->bind_param("issssi", $employee_id, $from_date, $to_date, $leave_type, $reason, $days_requested);
        $stmt->execute();
        
        $leave_request_id = $conn->insert_id;
        
        // Create approval workflow
        $approval_level = $is_emergency ? 2 : 1; // Emergency leaves go directly to HR
        $approver_role = $is_emergency ? 'hr' : 'manager';
        
        $stmt = $conn->prepare("INSERT INTO leave_approval_steps (leave_request_id, approval_level, approver_role, status) VALUES (?, ?, ?, 'pending')");
        $stmt->bind_param("iis", $leave_request_id, $approval_level, $approver_role);
        $stmt->execute();
        
        // Update leave balance (pending)
        $current_year = date('Y');
        $stmt = $conn->prepare("INSERT INTO leave_balance_tracking (employee_id, leave_type, allocated_days, used_days, pending_days, year) VALUES (?, ?, 30, 0, ?, ?) ON DUPLICATE KEY UPDATE pending_days = pending_days + VALUES(pending_days)");
        $stmt->bind_param("isii", $employee_id, $leave_type, $days_requested, $current_year);
        $stmt->execute();
        
        // Send notification to approver
        $priority = $is_emergency ? 'urgent' : 'medium';
        $stmt = $conn->prepare("INSERT INTO notification_queue (recipient_id, notification_type, title, message, priority, data) SELECT u.id, 'leave_approval_required', 'Mobile Leave Request', CONCAT('Employee submitted leave request via mobile app'), ?, ? FROM users u WHERE u.role = ?");
        $notification_data = json_encode(['leave_request_id' => $leave_request_id, 'is_mobile' => true, 'is_emergency' => $is_emergency]);
        $stmt->bind_param("sss", $priority, $notification_data, $approver_role);
        $stmt->execute();
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Leave request submitted successfully',
            'leave_request_id' => $leave_request_id,
            'is_emergency' => $is_emergency
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function getTeamSchedule($conn, $input) {
    $date = $input['date'] ?? date('Y-m-d');
    
    // Get team members on leave
    $stmt = $conn->prepare("SELECT 
                           e.name as employee_name,
                           lr.leave_type,
                           lr.from_date,
                           lr.to_date
                           FROM leave_requests lr
                           JOIN employees e ON lr.employee_id = e.employee_id
                           WHERE lr.status = 'approved' 
                           AND ? BETWEEN lr.from_date AND lr.to_date
                           ORDER BY e.name");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $team_schedule = [];
    while ($row = $result->fetch_assoc()) {
        $team_schedule[] = [
            'employee' => $row['employee_name'],
            'status' => 'On Leave',
            'type' => $row['leave_type'],
            'duration' => $row['from_date'] . ' to ' . $row['to_date']
        ];
    }
    
    // Get team members present (clocked in)
    $stmt = $conn->prepare("SELECT 
                           e.name as employee_name,
                           TIME(a.check_in) as check_in_time
                           FROM attendance a
                           JOIN employees e ON a.employee_id = e.employee_id
                           WHERE DATE(a.check_in) = ? 
                           AND a.check_out IS NULL
                           ORDER BY a.check_in");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $team_schedule[] = [
            'employee' => $row['employee_name'],
            'status' => 'Present',
            'check_in_time' => $row['check_in_time']
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $team_schedule]);
}

function logMobileAccess($conn, $user_id, $action, $latitude, $longitude, $location_address, $device_id) {
    $stmt = $conn->prepare("INSERT INTO mobile_access_logs (user_id, device_id, device_type, action, location_lat, location_lng, location_address, ip_address, user_agent, created_at) VALUES (?, ?, 'mobile', ?, ?, ?, ?, ?, ?, NOW())");
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $stmt->bind_param("issddsss", $user_id, $device_id, $action, $latitude, $longitude, $location_address, $ip_address, $user_agent);
    $stmt->execute();
}
?>
