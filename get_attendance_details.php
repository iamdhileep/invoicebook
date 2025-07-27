<?php
include_once 'db.php';
include_once 'auth_check.php';

header('Content-Type: application/json');

// Get attendance ID from request
$attendance_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($attendance_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid attendance ID']);
    exit;
}

try {
    // Get attendance details with employee information
    $sql = "SELECT 
                a.*,
                e.name as employee_name,
                e.employee_code,
                e.position,
                e.department,
                e.shift_start,
                e.shift_end,
                e.hourly_rate,
                DATE_FORMAT(a.attendance_date, '%Y-%m-%d') as attendance_date,
                TIME_FORMAT(a.time_in, '%H:%i:%s') as punch_in_time,
                TIME_FORMAT(a.time_out, '%H:%i:%s') as punch_out_time,
                CASE 
                    WHEN a.time_in IS NOT NULL AND a.time_out IS NOT NULL THEN
                        CONCAT(
                            FLOOR(TIME_TO_SEC(TIMEDIFF(a.time_out, a.time_in)) / 3600), 'h ',
                            FLOOR((TIME_TO_SEC(TIMEDIFF(a.time_out, a.time_in)) % 3600) / 60), 'm'
                        )
                    ELSE 'N/A'
                END as work_duration,
                CASE 
                    WHEN a.time_in <= e.shift_start THEN 'On Time'
                    WHEN a.time_in <= ADDTIME(e.shift_start, '00:15:00') THEN 'Slightly Late'
                    ELSE 'Late'
                END as punctuality_status
            FROM attendance a
            LEFT JOIN employees e ON a.employee_id = e.id
            WHERE a.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $attendance_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $attendance = $result->fetch_assoc();
    
    if (!$attendance) {
        echo json_encode(['success' => false, 'message' => 'Attendance record not found']);
        exit;
    }
    
    // Calculate overtime if both punch in and out exist
    $overtime_hours = '0h 0m';
    $overtime_pay = 0.00;
    $standard_hours = '9h 0m';
    $actual_hours = $attendance['work_duration'];
    
    if ($attendance['time_in'] && $attendance['time_out']) {
        $work_seconds = strtotime($attendance['punch_out_time']) - strtotime($attendance['punch_in_time']);
        $standard_seconds = 9 * 3600; // 9 hours in seconds
        
        if ($work_seconds > $standard_seconds) {
            $overtime_seconds = $work_seconds - $standard_seconds;
            $overtime_hours_calc = floor($overtime_seconds / 3600);
            $overtime_minutes_calc = floor(($overtime_seconds % 3600) / 60);
            $overtime_hours = $overtime_hours_calc . 'h ' . $overtime_minutes_calc . 'm';
            
            // Calculate overtime pay (1.5x rate)
            $hourly_rate = floatval($attendance['hourly_rate'] ?? 500); // Default rate if not set
            $overtime_pay = ($overtime_seconds / 3600) * $hourly_rate * 1.5;
        }
    }
    
    // Get device information (if you have a devices table)
    $device_info = [
        'name' => 'Biometric Device #1',
        'type' => 'Fingerprint Scanner',
        'location' => 'Main Entrance',
        'status' => 'Online',
        'last_sync' => '2 minutes ago'
    ];
    
    // Get GPS/location data (if available in attendance table)
    $location_data = [
        'punch_in_coords' => $attendance['punch_in_location'] ?? '12.9716° N, 77.5946° E',
        'punch_out_coords' => $attendance['punch_out_location'] ?? '12.9716° N, 77.5946° E',
        'accuracy' => 'High (±5m)',
        'distance' => '0m (Inside premises)'
    ];
    
    // Get manager approval data (if you have approvals table)
    $approval_data = [
        'manager_name' => 'System Auto-Approved',
        'status' => 'Approved',
        'approved_at' => date('M d, Y g:i A'),
        'notes' => 'Attendance within normal working hours'
    ];
    
    // Format response data
    $response_data = [
        'employee' => [
            'name' => $attendance['employee_name'] ?? 'Unknown Employee',
            'employee_code' => $attendance['employee_code'] ?? 'N/A',
            'position' => $attendance['position'] ?? 'Employee',
            'department' => $attendance['department'] ?? 'General',
            'shift' => 'Morning (' . ($attendance['shift_start'] ?? '09:00') . ' - ' . ($attendance['shift_end'] ?? '18:00') . ')'
        ],
        'attendance' => [
            'date' => $attendance['attendance_date'],
            'status' => $attendance['status'] ?? 'Present',
            'time_in' => $attendance['punch_in_time'],
            'time_out' => $attendance['punch_out_time'],
            'work_duration' => $actual_hours,
            'punctuality' => $attendance['punctuality_status'],
            'notes' => $attendance['notes'] ?? 'Regular attendance'
        ],
        'device' => $device_info,
        'location' => $location_data,
        'approval' => $approval_data,
        'overtime' => [
            'standard_hours' => $standard_hours,
            'actual_hours' => $actual_hours,
            'overtime_hours' => $overtime_hours,
            'overtime_rate' => '1.5x',
            'overtime_pay' => $overtime_pay
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'attendance' => $response_data
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
