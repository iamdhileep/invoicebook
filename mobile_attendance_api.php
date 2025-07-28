<?php
/**
 * Mobile Attendance API
 * Complete mobile integration for attendance system
 * Supports all mobile app features with comprehensive functionality
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once 'config.php';
require_once 'auth_check.php';

class MobileAttendanceAPI {
    private $db;
    private $employee_id;
    
    public function __construct($database) {
        $this->db = $database;
        $this->employee_id = $_SESSION['employee_id'] ?? null;
    }
    
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $path_parts = explode('/', trim($path, '/'));
        $action = end($path_parts);
        
        try {
            switch ($method) {
                case 'GET':
                    return $this->handleGet($action);
                case 'POST':
                    return $this->handlePost($action);
                case 'PUT':
                    return $this->handlePut($action);
                case 'DELETE':
                    return $this->handleDelete($action);
                default:
                    return $this->errorResponse('Method not allowed', 405);
            }
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
    
    // GET Endpoints
    private function handleGet($action) {
        switch ($action) {
            case 'dashboard':
                return $this->getMobileDashboard();
            case 'attendance_status':
                return $this->getAttendanceStatus();
            case 'leave_balance':
                return $this->getLeaveBalance();
            case 'team_status':
                return $this->getTeamStatus();
            case 'notifications':
                return $this->getNotifications();
            case 'attendance_history':
                return $this->getAttendanceHistory();
            case 'leave_requests':
                return $this->getLeaveRequests();
            case 'policy_info':
                return $this->getPolicyInfo();
            default:
                return $this->errorResponse('Endpoint not found', 404);
        }
    }
    
    // POST Endpoints
    private function handlePost($action) {
        $input = json_decode(file_get_contents('php://input'), true);
        
        switch ($action) {
            case 'punch_in':
                return $this->punchIn($input);
            case 'punch_out':
                return $this->punchOut($input);
            case 'smart_attendance':
                return $this->processSmartAttendance($input);
            case 'apply_leave':
                return $this->applyLeave($input);
            case 'upload_selfie':
                return $this->uploadSelfie($input);
            case 'request_wfh':
                return $this->requestWorkFromHome($input);
            case 'emergency_checkin':
                return $this->emergencyCheckIn($input);
            default:
                return $this->errorResponse('Endpoint not found', 404);
        }
    }
    
    // Mobile Dashboard
    private function getMobileDashboard() {
        $today = date('Y-m-d');
        
        // Get today's attendance
        $attendance_query = "SELECT * FROM attendance 
                           WHERE employee_id = ? AND attendance_date = ?";
        $stmt = $this->db->prepare($attendance_query);
        $stmt->bind_param("is", $this->employee_id, $today);
        $stmt->execute();
        $attendance = $stmt->get_result()->fetch_assoc();
        
        // Get employee info
        $employee_query = "SELECT name, position FROM employees WHERE employee_id = ?";
        $stmt = $this->db->prepare($employee_query);
        $stmt->bind_param("i", $this->employee_id);
        $stmt->execute();
        $employee = $stmt->get_result()->fetch_assoc();
        
        // Add default department if not present
        if ($employee) {
            $employee['department'] = 'General'; // Default department
        }
        
        // Get this month's stats
        $month_start = date('Y-m-01');
        $stats_query = "SELECT 
                          COUNT(*) as total_days,
                          SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
                          SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
                          SUM(CASE WHEN status = 'Leave' THEN 1 ELSE 0 END) as leave_days
                        FROM attendance 
                        WHERE employee_id = ? AND attendance_date >= ?";
        $stmt = $this->db->prepare($stats_query);
        $stmt->bind_param("is", $this->employee_id, $month_start);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
        
        return $this->successResponse([
            'employee' => $employee,
            'today_attendance' => $attendance,
            'monthly_stats' => $stats,
            'current_time' => date('Y-m-d H:i:s'),
            'is_punched_in' => !empty($attendance['punch_in_time']) && empty($attendance['punch_out_time']),
            'working_hours_today' => $this->calculateWorkingHours($attendance),
            'notifications_count' => $this->getUnreadNotificationsCount()
        ]);
    }
    
    // Smart Attendance Processing
    private function processSmartAttendance($input) {
        $method = $input['method'] ?? '';
        $employee_id = $this->employee_id;
        $today = date('Y-m-d');
        $current_time = date('H:i:s');
        
        // Validate input
        if (!in_array($method, ['face_recognition', 'qr_code', 'gps', 'ip_based', 'manual'])) {
            return $this->errorResponse('Invalid attendance method', 400);
        }
        
        // Check if already punched in today
        $check_query = "SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?";
        $stmt = $this->db->prepare($check_query);
        $stmt->bind_param("is", $employee_id, $today);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        
        $data = [
            'employee_id' => $employee_id,
            'attendance_date' => $today,
            'method' => $method,
            'location' => $input['location'] ?? 'Office',
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'device_info' => $input['device_info'] ?? 'Mobile App',
            'selfie_path' => $input['selfie_path'] ?? null,
            'gps_coordinates' => $input['gps_coordinates'] ?? null
        ];
        
        if (!$existing) {
            // Punch In
            $data['punch_in_time'] = $current_time;
            $data['status'] = 'Present';
            
            $query = "INSERT INTO attendance (employee_id, attendance_date, punch_in_time, status, method, location, ip_address, device_info, selfie_path, gps_coordinates) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("isssssssss", 
                $data['employee_id'], $data['attendance_date'], $data['punch_in_time'], 
                $data['status'], $data['method'], $data['location'], 
                $data['ip_address'], $data['device_info'], $data['selfie_path'], $data['gps_coordinates']
            );
            
            if ($stmt->execute()) {
                // Log audit trail
                $this->logAuditTrail('PUNCH_IN', "Employee punched in via {$method}");
                
                return $this->successResponse([
                    'action' => 'punch_in',
                    'time' => $current_time,
                    'method' => $method,
                    'message' => 'Punch in successful!'
                ]);
            }
        } else if (!empty($existing['punch_in_time']) && empty($existing['punch_out_time'])) {
            // Punch Out
            $punch_out_time = $current_time;
            $working_hours = $this->calculateHoursDifference($existing['punch_in_time'], $punch_out_time);
            
            $update_query = "UPDATE attendance SET punch_out_time = ?, work_duration = ?, updated_at = NOW() 
                           WHERE employee_id = ? AND attendance_date = ?";
            $stmt = $this->db->prepare($update_query);
            $stmt->bind_param("sdis", $punch_out_time, $working_hours, $employee_id, $today);
            
            if ($stmt->execute()) {
                // Log audit trail
                $this->logAuditTrail('PUNCH_OUT', "Employee punched out via {$method}");
                
                return $this->successResponse([
                    'action' => 'punch_out',
                    'time' => $punch_out_time,
                    'working_hours' => $working_hours,
                    'method' => $method,
                    'message' => 'Punch out successful!'
                ]);
            }
        } else {
            return $this->errorResponse('Already completed attendance for today', 400);
        }
        
        return $this->errorResponse('Failed to process attendance', 500);
    }
    
    // Leave Application
    private function applyLeave($input) {
        $required_fields = ['leave_type', 'start_date', 'end_date', 'reason'];
        foreach ($required_fields as $field) {
            if (empty($input[$field])) {
                return $this->errorResponse("Missing required field: {$field}", 400);
            }
        }
        
        // Calculate leave days
        $start_date = new DateTime($input['start_date']);
        $end_date = new DateTime($input['end_date']);
        $leave_days = $start_date->diff($end_date)->days + 1;
        
        // Check leave balance
        $balance = $this->getLeaveBalanceForType($input['leave_type']);
        if ($balance < $leave_days) {
            return $this->errorResponse('Insufficient leave balance', 400);
        }
        
        // Insert leave request
        $query = "INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, 
                                            total_days, reason, status, applied_date, applied_via) 
                 VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW(), 'Mobile App')";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("isssds", 
            $this->employee_id, $input['leave_type'], $input['start_date'], 
            $input['end_date'], $leave_days, $input['reason']
        );
        
        if ($stmt->execute()) {
            $leave_id = $this->db->insert_id;
            
            // Log audit trail
            $this->logAuditTrail('LEAVE_APPLIED', "Leave application submitted for {$leave_days} days");
            
            // Send notification to manager
            $this->sendNotificationToManager($leave_id);
            
            return $this->successResponse([
                'leave_id' => $leave_id,
                'total_days' => $leave_days,
                'status' => 'Pending',
                'message' => 'Leave application submitted successfully!'
            ]);
        }
        
        return $this->errorResponse('Failed to submit leave application', 500);
    }
    
    // Get Attendance Status
    private function getAttendanceStatus() {
        $today = date('Y-m-d');
        
        $query = "SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("is", $this->employee_id, $today);
        $stmt->execute();
        $attendance = $stmt->get_result()->fetch_assoc();
        
        if (!$attendance) {
            return $this->successResponse([
                'status' => 'Not Punched In',
                'can_punch_in' => true,
                'can_punch_out' => false,
                'working_hours' => 0
            ]);
        }
        
        $can_punch_out = !empty($attendance['punch_in_time']) && empty($attendance['punch_out_time']);
        $working_hours = $this->calculateWorkingHours($attendance);
        
        return $this->successResponse([
            'status' => $attendance['status'],
            'punch_in_time' => $attendance['punch_in_time'],
            'punch_out_time' => $attendance['punch_out_time'],
            'can_punch_in' => empty($attendance['punch_in_time']),
            'can_punch_out' => $can_punch_out,
            'working_hours' => $working_hours,
            'location' => $attendance['location'],
            'method' => $attendance['method']
        ]);
    }
    
    // Get Leave Balance
    private function getLeaveBalance() {
        $current_year = date('Y');
        
        $query = "SELECT 
                    leave_type,
                    annual_quota,
                    used_days,
                    (annual_quota - used_days) as remaining_days
                  FROM employee_leave_balance 
                  WHERE employee_id = ? AND year = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ii", $this->employee_id, $current_year);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $balances = [];
        while ($row = $result->fetch_assoc()) {
            $balances[] = $row;
        }
        
        return $this->successResponse($balances);
    }
    
    // Get Team Status (for managers)
    private function getTeamStatus() {
        $today = date('Y-m-d');
        
        // Check if user is a manager
        $manager_query = "SELECT COUNT(*) as count FROM employees WHERE manager_id = ?";
        $stmt = $this->db->prepare($manager_query);
        $stmt->bind_param("i", $this->employee_id);
        $stmt->execute();
        $is_manager = $stmt->get_result()->fetch_assoc()['count'] > 0;
        
        if (!$is_manager) {
            return $this->errorResponse('Access denied: Manager role required', 403);
        }
        
        // Get team attendance
        $team_query = "SELECT 
                         e.name, e.employee_id,
                         a.status, a.punch_in_time, a.punch_out_time,
                         a.location, a.work_duration as working_hours
                       FROM employees e
                       LEFT JOIN attendance a ON e.employee_id = a.employee_id AND a.attendance_date = ?
                       WHERE e.manager_id = ?
                       ORDER BY e.name";
        
        $stmt = $this->db->prepare($team_query);
        $stmt->bind_param("si", $today, $this->employee_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $team_members = [];
        $stats = ['present' => 0, 'absent' => 0, 'on_leave' => 0, 'wfh' => 0];
        
        while ($row = $result->fetch_assoc()) {
            $team_members[] = $row;
            
            switch ($row['status']) {
                case 'Present':
                    $stats['present']++;
                    break;
                case 'Absent':
                    $stats['absent']++;
                    break;
                case 'Leave':
                    $stats['on_leave']++;
                    break;
                case 'WFH':
                    $stats['wfh']++;
                    break;
            }
        }
        
        return $this->successResponse([
            'team_members' => $team_members,
            'statistics' => $stats,
            'total_members' => count($team_members)
        ]);
    }
    
    // Get Notifications
    private function getNotifications() {
        $query = "SELECT * FROM notifications 
                 WHERE employee_id = ? OR employee_id IS NULL 
                 ORDER BY created_at DESC 
                 LIMIT 50";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $this->employee_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        
        return $this->successResponse($notifications);
    }
    
    // Work From Home Request
    private function requestWorkFromHome($input) {
        $date = $input['date'] ?? date('Y-m-d');
        $reason = $input['reason'] ?? 'Work From Home';
        
        // Check if already requested
        $check_query = "SELECT * FROM wfh_requests WHERE employee_id = ? AND date = ?";
        $stmt = $this->db->prepare($check_query);
        $stmt->bind_param("is", $this->employee_id, $date);
        $stmt->execute();
        
        if ($stmt->get_result()->fetch_assoc()) {
            return $this->errorResponse('WFH request already exists for this date', 400);
        }
        
        // Insert WFH request
        $insert_query = "INSERT INTO wfh_requests (employee_id, date, reason, status, requested_at) 
                        VALUES (?, ?, ?, 'Pending', NOW())";
        $stmt = $this->db->prepare($insert_query);
        $stmt->bind_param("iss", $this->employee_id, $date, $reason);
        
        if ($stmt->execute()) {
            // Log audit trail
            $this->logAuditTrail('WFH_REQUESTED', "Work from home requested for {$date}");
            
            return $this->successResponse([
                'message' => 'Work from home request submitted successfully!',
                'date' => $date,
                'status' => 'Pending'
            ]);
        }
        
        return $this->errorResponse('Failed to submit WFH request', 500);
    }
    
    // Helper Methods
    private function calculateWorkingHours($attendance) {
        if (empty($attendance) || empty($attendance['punch_in_time'])) {
            return 0;
        }
        
        $punch_out = $attendance['punch_out_time'] ?: date('H:i:s');
        return $this->calculateHoursDifference($attendance['punch_in_time'], $punch_out);
    }
    
    private function calculateHoursDifference($start_time, $end_time) {
        $start = new DateTime($start_time);
        $end = new DateTime($end_time);
        $diff = $start->diff($end);
        return $diff->h + ($diff->i / 60);
    }
    
    private function getLeaveBalanceForType($leave_type) {
        $current_year = date('Y');
        
        $query = "SELECT (annual_quota - used_days) as remaining 
                 FROM employee_leave_balance 
                 WHERE employee_id = ? AND year = ? AND leave_type = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("iis", $this->employee_id, $current_year, $leave_type);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return $result['remaining'] ?? 0;
    }
    
    private function logAuditTrail($action, $description) {
        $query = "INSERT INTO audit_trail (employee_id, action, description, ip_address, user_agent, created_at) 
                 VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("issss", 
            $this->employee_id, $action, $description, 
            $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? 'Mobile App'
        );
        $stmt->execute();
    }
    
    private function sendNotificationToManager($leave_id) {
        // Get manager ID
        $manager_query = "SELECT manager_id FROM employees WHERE employee_id = ?";
        $stmt = $this->db->prepare($manager_query);
        $stmt->bind_param("i", $this->employee_id);
        $stmt->execute();
        $manager_id = $stmt->get_result()->fetch_assoc()['manager_id'];
        
        if ($manager_id) {
            $notification_query = "INSERT INTO notifications (employee_id, title, message, type, created_at) 
                                  VALUES (?, 'New Leave Request', 'A team member has submitted a leave request', 'leave_request', NOW())";
            $stmt = $this->db->prepare($notification_query);
            $stmt->bind_param("i", $manager_id);
            $stmt->execute();
        }
    }
    
    private function getUnreadNotificationsCount() {
        $query = "SELECT COUNT(*) as count FROM notifications 
                 WHERE employee_id = ? AND is_read = 0";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $this->employee_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()['count'];
    }
    
    private function successResponse($data) {
        return json_encode([
            'success' => true,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    private function errorResponse($message, $code = 400) {
        http_response_code($code);
        return json_encode([
            'success' => false,
            'error' => $message,
            'code' => $code,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}

// Initialize and handle request
try {
    $api = new MobileAttendanceAPI($conn);
    echo $api->handleRequest();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
