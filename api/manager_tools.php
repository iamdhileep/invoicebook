<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

session_start();
require_once '../../../db.php';

if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_team_overview':
        getTeamOverview();
        break;
    case 'get_attendance_alerts':
        getAttendanceAlerts();
        break;
    case 'bulk_approve_leaves':
        bulkApproveLeaves();
        break;
    case 'generate_team_report':
        generateTeamReport();
        break;
    case 'get_performance_insights':
        getPerformanceInsights();
        break;
    case 'configure_team_settings':
        configureTeamSettings();
        break;
    case 'delegate_approvals':
        delegateApprovals();
        break;
    case 'get_team_analytics':
        getTeamAnalytics();
        break;
    case 'schedule_team_meeting':
        scheduleTeamMeeting();
        break;
    case 'export_team_data':
        exportTeamData();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getTeamOverview() {
    global $conn;
    
    $manager_id = intval($_GET['manager_id'] ?? $_SESSION['admin_id'] ?? 0);
    $date = $_GET['date'] ?? date('Y-m-d');
    
    // Get team members
    $stmt = $conn->prepare("
        SELECT e.employee_id, e.name, e.employee_code, e.position, e.department,
               e.shift_start, e.shift_end, e.email, e.phone
        FROM employees e
        WHERE e.manager_id = ? OR e.department IN (
            SELECT department FROM employees WHERE employee_id = ?
        )
        ORDER BY e.name
    ");
    $stmt->bind_param("ii", $manager_id, $manager_id);
    $stmt->execute();
    $team_members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $team_overview = [];
    
    foreach ($team_members as $member) {
        // Get today's attendance
        $stmt = $conn->prepare("
            SELECT punch_in, punch_out, status, notes, location_name
            FROM attendance
            WHERE employee_id = ? AND date = ?
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->bind_param("is", $member['employee_id'], $date);
        $stmt->execute();
        $attendance = $stmt->get_result()->fetch_assoc();
        
        // Get leave status
        $stmt = $conn->prepare("
            SELECT lr.*, lt.display_name as leave_type
            FROM leave_requests lr
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            WHERE lr.employee_id = ? AND ? BETWEEN lr.start_date AND lr.end_date
            AND lr.status = 'approved'
        ");
        $stmt->bind_param("is", $member['employee_id'], $date);
        $stmt->execute();
        $leave_info = $stmt->get_result()->fetch_assoc();
        
        // Calculate performance metrics
        $performance = calculateEmployeePerformance($member['employee_id'], $date);
        
        $team_overview[] = [
            'employee' => $member,
            'attendance' => $attendance,
            'leave_info' => $leave_info,
            'performance' => $performance,
            'status' => determineEmployeeStatus($attendance, $leave_info, $member)
        ];
    }
    
    // Get team statistics
    $stats = calculateTeamStatistics($team_members, $date);
    
    echo json_encode([
        'success' => true,
        'team_overview' => $team_overview,
        'team_stats' => $stats,
        'date' => $date
    ]);
}

function getAttendanceAlerts() {
    global $conn;
    
    $manager_id = intval($_GET['manager_id'] ?? $_SESSION['admin_id'] ?? 0);
    $priority = $_GET['priority'] ?? 'all';
    
    $alerts = [];
    
    // Late arrivals
    $stmt = $conn->prepare("
        SELECT e.name, e.employee_code, a.punch_in, e.shift_start,
               TIMESTAMPDIFF(MINUTE, e.shift_start, a.punch_in) as late_minutes
        FROM attendance a
        JOIN employees e ON a.employee_id = e.employee_id
        WHERE (e.manager_id = ? OR e.department IN (
            SELECT department FROM employees WHERE employee_id = ?
        ))
        AND a.date = CURDATE()
        AND a.punch_in > e.shift_start
        AND TIMESTAMPDIFF(MINUTE, e.shift_start, a.punch_in) > 15
        ORDER BY late_minutes DESC
    ");
    $stmt->bind_param("ii", $manager_id, $manager_id);
    $stmt->execute();
    $late_arrivals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($late_arrivals as $late) {
        $alerts[] = [
            'type' => 'late_arrival',
            'priority' => $late['late_minutes'] > 60 ? 'high' : 'medium',
            'employee' => $late['name'],
            'employee_code' => $late['employee_code'],
            'message' => "Late by {$late['late_minutes']} minutes",
            'time' => $late['punch_in']
        ];
    }
    
    // Absent employees
    $stmt = $conn->prepare("
        SELECT e.name, e.employee_code, e.shift_start
        FROM employees e
        LEFT JOIN attendance a ON e.employee_id = a.employee_id AND a.date = CURDATE()
        LEFT JOIN leave_requests lr ON e.employee_id = lr.employee_id 
            AND CURDATE() BETWEEN lr.start_date AND lr.end_date 
            AND lr.status = 'approved'
        WHERE (e.manager_id = ? OR e.department IN (
            SELECT department FROM employees WHERE employee_id = ?
        ))
        AND a.id IS NULL
        AND lr.id IS NULL
        AND e.shift_start < NOW()
    ");
    $stmt->bind_param("ii", $manager_id, $manager_id);
    $stmt->execute();
    $absent_employees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($absent_employees as $absent) {
        $alerts[] = [
            'type' => 'absent',
            'priority' => 'high',
            'employee' => $absent['name'],
            'employee_code' => $absent['employee_code'],
            'message' => 'Absent without leave',
            'time' => null
        ];
    }
    
    // Overtime alerts
    $stmt = $conn->prepare("
        SELECT e.name, e.employee_code, a.punch_in, a.punch_out, e.shift_end,
               TIMESTAMPDIFF(MINUTE, e.shift_end, a.punch_out) as overtime_minutes
        FROM attendance a
        JOIN employees e ON a.employee_id = e.employee_id
        WHERE (e.manager_id = ? OR e.department IN (
            SELECT department FROM employees WHERE employee_id = ?
        ))
        AND a.date = CURDATE()
        AND a.punch_out IS NOT NULL
        AND a.punch_out > e.shift_end
        AND TIMESTAMPDIFF(MINUTE, e.shift_end, a.punch_out) > 30
        ORDER BY overtime_minutes DESC
    ");
    $stmt->bind_param("ii", $manager_id, $manager_id);
    $stmt->execute();
    $overtime_cases = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($overtime_cases as $overtime) {
        $alerts[] = [
            'type' => 'overtime',
            'priority' => $overtime['overtime_minutes'] > 120 ? 'high' : 'medium',
            'employee' => $overtime['name'],
            'employee_code' => $overtime['employee_code'],
            'message' => "Overtime: {$overtime['overtime_minutes']} minutes",
            'time' => $overtime['punch_out']
        ];
    }
    
    // Filter by priority if specified
    if ($priority !== 'all') {
        $alerts = array_filter($alerts, function($alert) use ($priority) {
            return $alert['priority'] === $priority;
        });
    }
    
    echo json_encode([
        'success' => true,
        'alerts' => array_values($alerts),
        'total_count' => count($alerts)
    ]);
}

function bulkApproveLeaves() {
    global $conn;
    
    $manager_id = intval($_POST['manager_id'] ?? $_SESSION['admin_id'] ?? 0);
    $leave_request_ids = json_decode($_POST['leave_request_ids'] ?? '[]', true);
    $action = $_POST['bulk_action'] ?? 'approve'; // approve or reject
    $comments = $_POST['comments'] ?? '';
    
    if (empty($leave_request_ids)) {
        echo json_encode(['success' => false, 'message' => 'No leave requests selected']);
        return;
    }
    
    $conn->begin_transaction();
    
    try {
        $success_count = 0;
        $errors = [];
        
        foreach ($leave_request_ids as $leave_id) {
            // Verify manager has permission to approve this leave
            $stmt = $conn->prepare("
                SELECT lr.*, e.name as employee_name
                FROM leave_requests lr
                JOIN employees e ON lr.employee_id = e.employee_id
                WHERE lr.id = ? AND (e.manager_id = ? OR e.department IN (
                    SELECT department FROM employees WHERE employee_id = ?
                ))
            ");
            $stmt->bind_param("iii", $leave_id, $manager_id, $manager_id);
            $stmt->execute();
            $leave_request = $stmt->get_result()->fetch_assoc();
            
            if (!$leave_request) {
                $errors[] = "Leave request $leave_id not found or unauthorized";
                continue;
            }
            
            // Update leave request
            $status = $action === 'approve' ? 'approved' : 'rejected';
            $stmt = $conn->prepare("
                UPDATE leave_requests 
                SET status = ?, approved_by = ?, approved_at = NOW() 
                WHERE id = ?
            ");
            $stmt->bind_param("sii", $status, $manager_id, $leave_id);
            $stmt->execute();
            
            // Add to approval history
            $stmt = $conn->prepare("
                INSERT INTO approval_history 
                (leave_request_id, approver_id, level, action, comments)
                VALUES (?, ?, 1, ?, ?)
            ");
            $stmt->bind_param("iiss", $leave_id, $manager_id, $action, $comments);
            $stmt->execute();
            
            // Update leave balance if approved
            if ($action === 'approve') {
                updateLeaveBalance($leave_id, 'approved');
            }
            
            // Send notification
            sendLeaveNotification($leave_id, $status);
            
            $success_count++;
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "$success_count leave requests processed successfully",
            'processed_count' => $success_count,
            'errors' => $errors
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Bulk operation failed: ' . $e->getMessage()]);
    }
}

function generateTeamReport() {
    global $conn;
    
    $manager_id = intval($_POST['manager_id'] ?? $_SESSION['admin_id'] ?? 0);
    $report_type = $_POST['report_type'] ?? 'attendance_summary';
    $start_date = $_POST['start_date'] ?? date('Y-m-01');
    $end_date = $_POST['end_date'] ?? date('Y-m-t');
    $format = $_POST['format'] ?? 'json';
    
    $report_data = [];
    
    switch ($report_type) {
        case 'attendance_summary':
            $report_data = generateAttendanceSummaryReport($manager_id, $start_date, $end_date);
            break;
        case 'leave_analysis':
            $report_data = generateLeaveAnalysisReport($manager_id, $start_date, $end_date);
            break;
        case 'performance_metrics':
            $report_data = generatePerformanceMetricsReport($manager_id, $start_date, $end_date);
            break;
        case 'overtime_report':
            $report_data = generateOvertimeReport($manager_id, $start_date, $end_date);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid report type']);
            return;
    }
    
    if ($format === 'csv') {
        exportReportAsCSV($report_data, $report_type);
    } else {
        echo json_encode([
            'success' => true,
            'report_data' => $report_data,
            'report_type' => $report_type,
            'period' => "$start_date to $end_date"
        ]);
    }
}

function getPerformanceInsights() {
    global $conn;
    
    $manager_id = intval($_GET['manager_id'] ?? $_SESSION['admin_id'] ?? 0);
    $period = $_GET['period'] ?? 'monthly';
    
    $insights = [];
    
    // Team productivity trends
    $productivity_data = analyzeTeamProductivity($manager_id, $period);
    $insights['productivity'] = $productivity_data;
    
    // Punctuality analysis
    $punctuality_data = analyzePunctuality($manager_id, $period);
    $insights['punctuality'] = $punctuality_data;
    
    // Leave patterns
    $leave_patterns = analyzeLeavePatterns($manager_id, $period);
    $insights['leave_patterns'] = $leave_patterns;
    
    // Attendance consistency
    $consistency_data = analyzeAttendanceConsistency($manager_id, $period);
    $insights['consistency'] = $consistency_data;
    
    // Risk indicators
    $risk_indicators = identifyRiskIndicators($manager_id);
    $insights['risk_indicators'] = $risk_indicators;
    
    echo json_encode([
        'success' => true,
        'insights' => $insights,
        'period' => $period,
        'generated_at' => date('Y-m-d H:i:s')
    ]);
}

function configureTeamSettings() {
    global $conn;
    
    $manager_id = intval($_POST['manager_id'] ?? $_SESSION['admin_id'] ?? 0);
    $settings = json_decode($_POST['settings'] ?? '{}', true);
    
    if (empty($settings)) {
        echo json_encode(['success' => false, 'message' => 'No settings provided']);
        return;
    }
    
    $conn->begin_transaction();
    
    try {
        // Delete existing settings
        $stmt = $conn->prepare("DELETE FROM team_settings WHERE manager_id = ?");
        $stmt->bind_param("i", $manager_id);
        $stmt->execute();
        
        // Insert new settings
        $stmt = $conn->prepare("
            INSERT INTO team_settings (manager_id, setting_key, setting_value)
            VALUES (?, ?, ?)
        ");
        
        foreach ($settings as $key => $value) {
            $stmt->bind_param("iss", $manager_id, $key, json_encode($value));
            $stmt->execute();
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Team settings updated successfully']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to update settings: ' . $e->getMessage()]);
    }
}

function delegateApprovals() {
    global $conn;
    
    $manager_id = intval($_POST['manager_id'] ?? $_SESSION['admin_id'] ?? 0);
    $delegate_to = intval($_POST['delegate_to'] ?? 0);
    $delegation_type = $_POST['delegation_type'] ?? 'leave_approvals';
    $start_date = $_POST['start_date'] ?? date('Y-m-d');
    $end_date = $_POST['end_date'] ?? date('Y-m-d', strtotime('+7 days'));
    $comments = $_POST['comments'] ?? '';
    
    if (!$delegate_to) {
        echo json_encode(['success' => false, 'message' => 'Delegate recipient required']);
        return;
    }
    
    $stmt = $conn->prepare("
        INSERT INTO delegation_rules 
        (manager_id, delegate_to, delegation_type, start_date, end_date, comments, is_active)
        VALUES (?, ?, ?, ?, ?, ?, 1)
    ");
    $stmt->bind_param("iissss", $manager_id, $delegate_to, $delegation_type, $start_date, $end_date, $comments);
    
    if ($stmt->execute()) {
        // Send notification to delegate
        sendDelegationNotification($delegate_to, $manager_id, $delegation_type, $start_date, $end_date);
        
        echo json_encode([
            'success' => true,
            'message' => 'Approval delegation configured successfully',
            'delegation_id' => $conn->insert_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to configure delegation']);
    }
}

function getTeamAnalytics() {
    global $conn;
    
    $manager_id = intval($_GET['manager_id'] ?? $_SESSION['admin_id'] ?? 0);
    $metric_type = $_GET['metric_type'] ?? 'all';
    
    $analytics = [];
    
    if ($metric_type === 'all' || $metric_type === 'attendance') {
        $analytics['attendance'] = getAttendanceAnalytics($manager_id);
    }
    
    if ($metric_type === 'all' || $metric_type === 'productivity') {
        $analytics['productivity'] = getProductivityAnalytics($manager_id);
    }
    
    if ($metric_type === 'all' || $metric_type === 'engagement') {
        $analytics['engagement'] = getEngagementAnalytics($manager_id);
    }
    
    echo json_encode([
        'success' => true,
        'analytics' => $analytics,
        'metric_type' => $metric_type
    ]);
}

// Helper functions
function calculateEmployeePerformance($employee_id, $date) {
    global $conn;
    
    $performance = [
        'attendance_rate' => 0,
        'punctuality_score' => 0,
        'overtime_hours' => 0,
        'leave_utilization' => 0
    ];
    
    // Calculate attendance rate for last 30 days
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_days,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days
        FROM attendance
        WHERE employee_id = ? AND date >= DATE_SUB(?, INTERVAL 30 DAY)
    ");
    $stmt->bind_param("is", $employee_id, $date);
    $stmt->execute();
    $attendance_data = $stmt->get_result()->fetch_assoc();
    
    if ($attendance_data['total_days'] > 0) {
        $performance['attendance_rate'] = ($attendance_data['present_days'] / $attendance_data['total_days']) * 100;
    }
    
    return $performance;
}

function determineEmployeeStatus($attendance, $leave_info, $employee) {
    if ($leave_info) {
        return [
            'status' => 'on_leave',
            'status_text' => 'On ' . $leave_info['leave_type'],
            'color' => 'warning'
        ];
    }
    
    if (!$attendance) {
        $current_time = new DateTime();
        $shift_start = new DateTime($employee['shift_start']);
        
        if ($current_time > $shift_start) {
            return [
                'status' => 'absent',
                'status_text' => 'Absent',
                'color' => 'danger'
            ];
        } else {
            return [
                'status' => 'not_started',
                'status_text' => 'Shift Not Started',
                'color' => 'secondary'
            ];
        }
    }
    
    if ($attendance['punch_out']) {
        return [
            'status' => 'completed',
            'status_text' => 'Day Completed',
            'color' => 'success'
        ];
    } else {
        return [
            'status' => 'working',
            'status_text' => 'Currently Working',
            'color' => 'primary'
        ];
    }
}

function calculateTeamStatistics($team_members, $date) {
    $stats = [
        'total_members' => count($team_members),
        'present_today' => 0,
        'on_leave' => 0,
        'absent' => 0,
        'average_attendance' => 0
    ];
    
    // These would be calculated based on actual data
    // Simplified for demonstration
    return $stats;
}

function generateAttendanceSummaryReport($manager_id, $start_date, $end_date) {
    // Generate detailed attendance summary report
    return ['report_type' => 'attendance_summary', 'data' => []];
}

function analyzeTeamProductivity($manager_id, $period) {
    // Analyze team productivity metrics
    return ['productivity_score' => 85, 'trend' => 'improving'];
}

function sendDelegationNotification($delegate_to, $manager_id, $type, $start_date, $end_date) {
    // Send notification about delegation
}

// Additional helper functions would be implemented here...

?>
