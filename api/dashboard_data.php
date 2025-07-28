<?php
// Real-time Dashboard Data API
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
    $dashboard_data = [];
    
    // Get current date info
    $today = date('Y-m-d');
    $this_month = date('Y-m');
    $this_year = date('Y');
    
    // Employee Statistics
    $emp_stats = $conn->query("
        SELECT 
            COUNT(*) as total_employees,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_employees,
            COUNT(CASE WHEN YEAR(hire_date) = $this_year THEN 1 END) as new_hires_this_year
        FROM employees
    ")->fetch_assoc();
    
    // Attendance Statistics for Today
    $att_today = $conn->query("
        SELECT 
            COUNT(DISTINCT employee_id) as present_today,
            COUNT(CASE WHEN TIME(check_in) > '09:00:00' THEN 1 END) as late_today
        FROM attendance 
        WHERE DATE(check_in) = '$today'
    ")->fetch_assoc();
    
    // Leave Statistics
    $leave_stats = $conn->query("
        SELECT 
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_leaves,
            COUNT(CASE WHEN status = 'approved' AND start_date <= '$today' AND end_date >= '$today' THEN 1 END) as on_leave_today,
            COUNT(CASE WHEN MONTH(start_date) = MONTH('$today') AND YEAR(start_date) = YEAR('$today') THEN 1 END) as leaves_this_month
        FROM leave_requests
    ")->fetch_assoc();
    
    // Department-wise Attendance
    $dept_attendance = [];
    $dept_query = $conn->query("
        SELECT d.name as department, 
               COUNT(DISTINCT e.id) as total_employees,
               COUNT(DISTINCT a.employee_id) as present_today
        FROM departments d
        LEFT JOIN employees e ON d.id = e.department_id
        LEFT JOIN attendance a ON e.id = a.employee_id AND DATE(a.check_in) = '$today'
        GROUP BY d.id, d.name
    ");
    
    while ($row = $dept_query->fetch_assoc()) {
        $dept_attendance[] = [
            'department' => $row['department'],
            'total' => intval($row['total_employees']),
            'present' => intval($row['present_today']),
            'percentage' => $row['total_employees'] > 0 ? round(($row['present_today'] / $row['total_employees']) * 100, 1) : 0
        ];
    }
    
    // Recent Activities (last 10)
    $recent_activities = [];
    $activities_query = $conn->query("
        SELECT 'attendance' as type, CONCAT(e.first_name, ' ', e.last_name) as employee_name, 
               'Checked in' as action, a.check_in as timestamp
        FROM attendance a 
        JOIN employees e ON a.employee_id = e.id 
        WHERE DATE(a.check_in) = '$today'
        UNION ALL
        SELECT 'leave' as type, CONCAT(e.first_name, ' ', e.last_name) as employee_name,
               CONCAT('Requested ', leave_type, ' leave') as action, lr.created_at as timestamp
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.id
        WHERE DATE(lr.created_at) = '$today'
        ORDER BY timestamp DESC
        LIMIT 10
    ");
    
    while ($row = $activities_query->fetch_assoc()) {
        $recent_activities[] = [
            'type' => $row['type'],
            'employee' => $row['employee_name'],
            'action' => $row['action'],
            'time' => date('H:i', strtotime($row['timestamp'])),
            'timestamp' => $row['timestamp']
        ];
    }
    
    // Weekly Attendance Trend
    $weekly_trend = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $day_name = date('D', strtotime($date));
        
        $count = $conn->query("
            SELECT COUNT(DISTINCT employee_id) as count 
            FROM attendance 
            WHERE DATE(check_in) = '$date'
        ")->fetch_assoc()['count'];
        
        $weekly_trend[] = [
            'date' => $date,
            'day' => $day_name,
            'count' => intval($count)
        ];
    }
    
    // Upcoming Events/Deadlines
    $upcoming_events = [];
    
    // Upcoming leave requests
    $upcoming_leaves = $conn->query("
        SELECT CONCAT(e.first_name, ' ', e.last_name) as employee_name,
               lr.leave_type, lr.start_date
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.id
        WHERE lr.status = 'approved' AND lr.start_date BETWEEN '$today' AND DATE_ADD('$today', INTERVAL 7 DAY)
        ORDER BY lr.start_date
        LIMIT 5
    ");
    
    while ($row = $upcoming_leaves->fetch_assoc()) {
        $upcoming_events[] = [
            'type' => 'leave',
            'title' => $row['employee_name'] . ' - ' . ucfirst($row['leave_type']) . ' Leave',
            'date' => $row['start_date'],
            'days_away' => (strtotime($row['start_date']) - strtotime($today)) / (60 * 60 * 24)
        ];
    }
    
    // Compile all data
    $dashboard_data = [
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'statistics' => [
            'employees' => $emp_stats,
            'attendance_today' => array_merge($att_today, [
                'absent_today' => $emp_stats['active_employees'] - $att_today['present_today'],
                'attendance_rate' => $emp_stats['active_employees'] > 0 ? 
                    round(($att_today['present_today'] / $emp_stats['active_employees']) * 100, 1) : 0
            ]),
            'leaves' => $leave_stats
        ],
        'department_attendance' => $dept_attendance,
        'recent_activities' => $recent_activities,
        'weekly_trend' => $weekly_trend,
        'upcoming_events' => $upcoming_events
    ];
    
    echo json_encode($dashboard_data);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
