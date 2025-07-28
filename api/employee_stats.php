<?php
// Employee Statistics API for Mobile Portal
session_start();
require_once '../db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $employee_id = $_SESSION['user_id'] ?? $_SESSION['admin'];
    $today = date('Y-m-d');
    $this_month = date('Y-m');
    
    // Monthly hours worked
    $monthly_hours_query = $conn->query("
        SELECT 
            SUM(
                CASE 
                    WHEN check_out IS NOT NULL THEN 
                        TIMESTAMPDIFF(SECOND, check_in, check_out) / 3600
                    ELSE 0
                END
            ) as total_hours
        FROM attendance 
        WHERE employee_id = $employee_id 
        AND DATE_FORMAT(check_in, '%Y-%m') = '$this_month'
    ");
    $monthly_hours = round($monthly_hours_query->fetch_assoc()['total_hours'] ?? 0, 1);
    
    // Leave balance
    $leave_balance_query = $conn->query("
        SELECT annual_leave_balance 
        FROM leave_balance 
        WHERE employee_id = $employee_id
    ");
    $leave_balance = $leave_balance_query->fetch_assoc()['annual_leave_balance'] ?? 0;
    
    // Attendance rate for current month
    $total_work_days = $conn->query("
        SELECT COUNT(DISTINCT DATE(check_in)) as work_days
        FROM attendance 
        WHERE DATE_FORMAT(check_in, '%Y-%m') = '$this_month'
    ")->fetch_assoc()['work_days'];
    
    $employee_work_days = $conn->query("
        SELECT COUNT(DISTINCT DATE(check_in)) as employee_days
        FROM attendance 
        WHERE employee_id = $employee_id 
        AND DATE_FORMAT(check_in, '%Y-%m') = '$this_month'
    ")->fetch_assoc()['employee_days'];
    
    $attendance_rate = $total_work_days > 0 ? 
        round(($employee_work_days / $total_work_days) * 100, 1) : 0;
    
    // Pending leave requests
    $pending_requests = $conn->query("
        SELECT COUNT(*) as count 
        FROM leave_requests 
        WHERE employee_id = $employee_id AND status = 'pending'
    ")->fetch_assoc()['count'];
    
    // Notifications count (pending leaves + announcements)
    $notifications = $pending_requests;
    
    // Recent activity summary
    $recent_activity = [];
    
    // Recent attendance
    $recent_attendance = $conn->query("
        SELECT DATE(check_in) as date, 
               TIME(check_in) as check_in_time,
               TIME(check_out) as check_out_time
        FROM attendance 
        WHERE employee_id = $employee_id 
        ORDER BY check_in DESC 
        LIMIT 5
    ");
    
    while ($row = $recent_attendance->fetch_assoc()) {
        $recent_activity[] = [
            'type' => 'attendance',
            'date' => $row['date'],
            'check_in' => $row['check_in_time'],
            'check_out' => $row['check_out_time']
        ];
    }
    
    // Recent leave requests
    $recent_leaves = $conn->query("
        SELECT leave_type, start_date, end_date, status, created_at
        FROM leave_requests 
        WHERE employee_id = $employee_id 
        ORDER BY created_at DESC 
        LIMIT 3
    ");
    
    while ($row = $recent_leaves->fetch_assoc()) {
        $recent_activity[] = [
            'type' => 'leave',
            'leave_type' => $row['leave_type'],
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'status' => $row['status'],
            'created_at' => $row['created_at']
        ];
    }
    
    // Current week attendance
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $week_attendance = [];
    
    for ($i = 0; $i < 7; $i++) {
        $date = date('Y-m-d', strtotime($week_start . " +$i days"));
        $day_name = date('D', strtotime($date));
        
        $attendance = $conn->query("
            SELECT check_in, check_out 
            FROM attendance 
            WHERE employee_id = $employee_id AND DATE(check_in) = '$date'
        ")->fetch_assoc();
        
        $week_attendance[] = [
            'date' => $date,
            'day' => $day_name,
            'present' => $attendance ? true : false,
            'check_in' => $attendance['check_in'] ?? null,
            'check_out' => $attendance['check_out'] ?? null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'monthly_hours' => $monthly_hours,
        'leave_balance' => $leave_balance,
        'attendance_rate' => $attendance_rate,
        'pending_requests' => $pending_requests,
        'notifications' => $notifications,
        'recent_activity' => $recent_activity,
        'week_attendance' => $week_attendance,
        'current_month' => date('F Y'),
        'employee_id' => $employee_id
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
