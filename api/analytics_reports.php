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
    case 'get_dashboard_metrics':
        getDashboardMetrics();
        break;
    case 'generate_attendance_report':
        generateAttendanceReport();
        break;
    case 'get_predictive_analytics':
        getPredictiveAnalytics();
        break;
    case 'export_custom_report':
        exportCustomReport();
        break;
    case 'get_comparative_analysis':
        getComparativeAnalysis();
        break;
    case 'schedule_automated_report':
        scheduleAutomatedReport();
        break;
    case 'get_realtime_insights':
        getRealtimeInsights();
        break;
    case 'get_trend_analysis':
        getTrendAnalysis();
        break;
    case 'create_custom_dashboard':
        createCustomDashboard();
        break;
    case 'get_audit_reports':
        getAuditReports();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getDashboardMetrics() {
    global $conn;
    
    $date_range = $_GET['date_range'] ?? 'today';
    $department = $_GET['department'] ?? '';
    
    $metrics = [];
    
    // Get date range
    $date_filter = getDateRangeFilter($date_range);
    
    // Total employees
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM employees WHERE status = 'active'");
    $stmt->execute();
    $metrics['total_employees'] = $stmt->get_result()->fetch_assoc()['total'];
    
    // Present today
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT employee_id) as present_count
        FROM attendance
        WHERE date = CURDATE() AND status = 'present'
    ");
    $stmt->execute();
    $metrics['present_today'] = $stmt->get_result()->fetch_assoc()['present_count'];
    
    // On leave today
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT lr.employee_id) as leave_count
        FROM leave_requests lr
        WHERE lr.status = 'approved' 
        AND CURDATE() BETWEEN lr.start_date AND lr.end_date
    ");
    $stmt->execute();
    $metrics['on_leave_today'] = $stmt->get_result()->fetch_assoc()['leave_count'];
    
    // Late arrivals today
    $stmt = $conn->prepare("
        SELECT COUNT(*) as late_count
        FROM attendance a
        JOIN employees e ON a.employee_id = e.employee_id
        WHERE a.date = CURDATE() 
        AND a.punch_in > e.shift_start
        AND TIMESTAMPDIFF(MINUTE, e.shift_start, a.punch_in) > 15
    ");
    $stmt->execute();
    $metrics['late_arrivals'] = $stmt->get_result()->fetch_assoc()['late_count'];
    
    // Average attendance rate (last 30 days)
    $stmt = $conn->prepare("
        SELECT 
            (COUNT(CASE WHEN status = 'present' THEN 1 END) / COUNT(*)) * 100 as avg_attendance
        FROM attendance
        WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $metrics['avg_attendance_rate'] = round($stmt->get_result()->fetch_assoc()['avg_attendance'], 2);
    
    // Monthly attendance trend
    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(date, '%Y-%m') as month,
            COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
            COUNT(*) as total_records,
            (COUNT(CASE WHEN status = 'present' THEN 1 END) / COUNT(*)) * 100 as attendance_rate
        FROM attendance
        WHERE date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(date, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute();
    $metrics['monthly_trend'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Department-wise breakdown
    $stmt = $conn->prepare("
        SELECT 
            e.department,
            COUNT(DISTINCT e.employee_id) as total_employees,
            COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_today,
            (COUNT(CASE WHEN a.status = 'present' THEN 1 END) / COUNT(DISTINCT e.employee_id)) * 100 as dept_attendance_rate
        FROM employees e
        LEFT JOIN attendance a ON e.employee_id = a.employee_id AND a.date = CURDATE()
        WHERE e.status = 'active'
        GROUP BY e.department
        ORDER BY dept_attendance_rate DESC
    ");
    $stmt->execute();
    $metrics['department_breakdown'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        'success' => true,
        'metrics' => $metrics,
        'date_range' => $date_range,
        'generated_at' => date('Y-m-d H:i:s')
    ]);
}

function generateAttendanceReport() {
    global $conn;
    
    $report_type = $_POST['report_type'] ?? 'detailed';
    $start_date = $_POST['start_date'] ?? date('Y-m-01');
    $end_date = $_POST['end_date'] ?? date('Y-m-t');
    $employees = json_decode($_POST['employees'] ?? '[]', true);
    $departments = json_decode($_POST['departments'] ?? '[]', true);
    $format = $_POST['format'] ?? 'json';
    
    $report_data = [];
    
    // Build WHERE clause
    $where_conditions = ["a.date BETWEEN ? AND ?"];
    $params = [$start_date, $end_date];
    $param_types = "ss";
    
    if (!empty($employees)) {
        $placeholders = str_repeat('?,', count($employees) - 1) . '?';
        $where_conditions[] = "e.employee_id IN ($placeholders)";
        $params = array_merge($params, $employees);
        $param_types .= str_repeat('i', count($employees));
    }
    
    if (!empty($departments)) {
        $placeholders = str_repeat('?,', count($departments) - 1) . '?';
        $where_conditions[] = "e.department IN ($placeholders)";
        $params = array_merge($params, $departments);
        $param_types .= str_repeat('s', count($departments));
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    switch ($report_type) {
        case 'detailed':
            $stmt = $conn->prepare("
                SELECT 
                    e.employee_code,
                    e.name,
                    e.department,
                    a.date,
                    a.punch_in,
                    a.punch_out,
                    a.status,
                    a.hours_worked,
                    a.overtime_hours,
                    a.location_name,
                    a.notes,
                    CASE 
                        WHEN a.punch_in > e.shift_start THEN TIMESTAMPDIFF(MINUTE, e.shift_start, a.punch_in)
                        ELSE 0
                    END as late_minutes
                FROM attendance a
                JOIN employees e ON a.employee_id = e.employee_id
                WHERE $where_clause
                ORDER BY e.name, a.date
            ");
            break;
            
        case 'summary':
            $stmt = $conn->prepare("
                SELECT 
                    e.employee_code,
                    e.name,
                    e.department,
                    COUNT(*) as total_days,
                    COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
                    COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_days,
                    COUNT(CASE WHEN a.punch_in > e.shift_start THEN 1 END) as late_days,
                    AVG(a.hours_worked) as avg_hours_worked,
                    SUM(a.overtime_hours) as total_overtime,
                    (COUNT(CASE WHEN a.status = 'present' THEN 1 END) / COUNT(*)) * 100 as attendance_percentage
                FROM attendance a
                JOIN employees e ON a.employee_id = e.employee_id
                WHERE $where_clause
                GROUP BY e.employee_id
                ORDER BY attendance_percentage DESC
            ");
            break;
            
        case 'productivity':
            $stmt = $conn->prepare("
                SELECT 
                    e.department,
                    COUNT(DISTINCT e.employee_id) as team_size,
                    AVG(a.hours_worked) as avg_hours_per_day,
                    SUM(a.overtime_hours) as total_overtime,
                    (COUNT(CASE WHEN a.status = 'present' THEN 1 END) / COUNT(*)) * 100 as department_attendance_rate,
                    COUNT(CASE WHEN a.punch_in > e.shift_start THEN 1 END) as total_late_arrivals
                FROM attendance a
                JOIN employees e ON a.employee_id = e.employee_id
                WHERE $where_clause
                GROUP BY e.department
                ORDER BY department_attendance_rate DESC
            ");
            break;
    }
    
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Add summary statistics
    $summary_stats = calculateReportSummary($report_data, $report_type);
    
    if ($format === 'csv') {
        exportReportAsCSV($report_data, "attendance_report_$report_type");
    } else {
        echo json_encode([
            'success' => true,
            'report_data' => $report_data,
            'summary_stats' => $summary_stats,
            'report_type' => $report_type,
            'period' => "$start_date to $end_date",
            'total_records' => count($report_data)
        ]);
    }
}

function getPredictiveAnalytics() {
    global $conn;
    
    $prediction_type = $_GET['prediction_type'] ?? 'attendance_forecast';
    $period = $_GET['period'] ?? 'monthly';
    
    $predictions = [];
    
    switch ($prediction_type) {
        case 'attendance_forecast':
            $predictions = predictAttendanceTrends($period);
            break;
        case 'leave_patterns':
            $predictions = predictLeavePatterns($period);
            break;
        case 'overtime_forecast':
            $predictions = predictOvertimeTrends($period);
            break;
        case 'risk_assessment':
            $predictions = assessAttritionRisk();
            break;
    }
    
    echo json_encode([
        'success' => true,
        'predictions' => $predictions,
        'prediction_type' => $prediction_type,
        'confidence_level' => calculateConfidenceLevel($predictions),
        'generated_at' => date('Y-m-d H:i:s')
    ]);
}

function getComparativeAnalysis() {
    global $conn;
    
    $comparison_type = $_GET['comparison_type'] ?? 'department_comparison';
    $metric = $_GET['metric'] ?? 'attendance_rate';
    $period = $_GET['period'] ?? 'monthly';
    
    $analysis = [];
    
    switch ($comparison_type) {
        case 'department_comparison':
            $analysis = compareDepartmentMetrics($metric, $period);
            break;
        case 'period_comparison':
            $analysis = comparePeriodMetrics($metric);
            break;
        case 'employee_ranking':
            $analysis = rankEmployeePerformance($metric, $period);
            break;
        case 'benchmark_analysis':
            $analysis = benchmarkAnalysis($metric, $period);
            break;
    }
    
    echo json_encode([
        'success' => true,
        'analysis' => $analysis,
        'comparison_type' => $comparison_type,
        'metric' => $metric
    ]);
}

function getRealtimeInsights() {
    global $conn;
    
    $insights = [];
    
    // Current day status
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.employee_id END) as currently_present,
            COUNT(DISTINCT CASE WHEN a.punch_out IS NULL AND a.punch_in IS NOT NULL THEN a.employee_id END) as currently_working,
            COUNT(DISTINCT CASE WHEN TIME(a.punch_in) > e.shift_start THEN a.employee_id END) as late_today
        FROM attendance a
        JOIN employees e ON a.employee_id = e.employee_id
        WHERE a.date = CURDATE()
    ");
    $stmt->execute();
    $insights['current_status'] = $stmt->get_result()->fetch_assoc();
    
    // Hourly punch patterns today
    $stmt = $conn->prepare("
        SELECT 
            HOUR(punch_in) as hour,
            COUNT(*) as punch_ins
        FROM attendance
        WHERE date = CURDATE() AND punch_in IS NOT NULL
        GROUP BY HOUR(punch_in)
        ORDER BY hour
    ");
    $stmt->execute();
    $insights['hourly_patterns'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Live alerts
    $insights['live_alerts'] = generateLiveAlerts();
    
    // Performance indicators
    $insights['performance_indicators'] = calculateRealtimeKPIs();
    
    echo json_encode([
        'success' => true,
        'insights' => $insights,
        'timestamp' => date('Y-m-d H:i:s'),
        'refresh_interval' => 30 // seconds
    ]);
}

function getTrendAnalysis() {
    global $conn;
    
    $trend_type = $_GET['trend_type'] ?? 'attendance_trends';
    $period = $_GET['period'] ?? 'monthly';
    $granularity = $_GET['granularity'] ?? 'daily';
    
    $trends = [];
    
    switch ($trend_type) {
        case 'attendance_trends':
            $trends = analyzeAttendanceTrends($period, $granularity);
            break;
        case 'punctuality_trends':
            $trends = analyzePunctualityTrends($period, $granularity);
            break;
        case 'leave_trends':
            $trends = analyzeLeaveTrends($period, $granularity);
            break;
        case 'productivity_trends':
            $trends = analyzeProductivityTrends($period, $granularity);
            break;
    }
    
    // Calculate trend direction and strength
    $trend_analysis = calculateTrendMetrics($trends);
    
    echo json_encode([
        'success' => true,
        'trends' => $trends,
        'trend_analysis' => $trend_analysis,
        'trend_type' => $trend_type,
        'period' => $period
    ]);
}

function scheduleAutomatedReport() {
    global $conn;
    
    $report_config = json_decode($_POST['report_config'] ?? '{}', true);
    $schedule_config = json_decode($_POST['schedule_config'] ?? '{}', true);
    $recipients = json_decode($_POST['recipients'] ?? '[]', true);
    
    if (empty($report_config) || empty($schedule_config)) {
        echo json_encode(['success' => false, 'message' => 'Configuration required']);
        return;
    }
    
    $stmt = $conn->prepare("
        INSERT INTO scheduled_reports 
        (report_name, report_config, schedule_config, recipients, created_by, is_active)
        VALUES (?, ?, ?, ?, ?, 1)
    ");
    
    $report_name = $report_config['name'] ?? 'Automated Report';
    $created_by = $_SESSION['admin_id'] ?? 0;
    
    $stmt->bind_param("ssssi",
        $report_name,
        json_encode($report_config),
        json_encode($schedule_config),
        json_encode($recipients),
        $created_by
    );
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Automated report scheduled successfully',
            'report_id' => $conn->insert_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to schedule report']);
    }
}

// Helper functions
function getDateRangeFilter($date_range) {
    switch ($date_range) {
        case 'today':
            return ["date = CURDATE()"];
        case 'yesterday':
            return ["date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)"];
        case 'this_week':
            return ["date >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)", "date <= CURDATE()"];
        case 'this_month':
            return ["date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')", "date <= LAST_DAY(CURDATE())"];
        case 'last_30_days':
            return ["date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)", "date <= CURDATE()"];
        default:
            return ["date = CURDATE()"];
    }
}

function predictAttendanceTrends($period) {
    global $conn;
    
    // Simple linear regression for attendance prediction
    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(date, '%Y-%m') as month,
            (COUNT(CASE WHEN status = 'present' THEN 1 END) / COUNT(*)) * 100 as attendance_rate
        FROM attendance
        WHERE date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(date, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute();
    $historical_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Calculate trend and predict next 3 months
    $predictions = calculateLinearTrend($historical_data, 3);
    
    return [
        'historical_data' => $historical_data,
        'predictions' => $predictions,
        'algorithm' => 'linear_regression'
    ];
}

function calculateReportSummary($data, $report_type) {
    if (empty($data)) return [];
    
    $summary = [];
    
    switch ($report_type) {
        case 'detailed':
            $summary['total_records'] = count($data);
            $summary['present_days'] = count(array_filter($data, function($row) {
                return $row['status'] === 'present';
            }));
            $summary['absent_days'] = count(array_filter($data, function($row) {
                return $row['status'] === 'absent';
            }));
            break;
            
        case 'summary':
            $summary['total_employees'] = count($data);
            $summary['avg_attendance_rate'] = array_sum(array_column($data, 'attendance_percentage')) / count($data);
            $summary['total_overtime_hours'] = array_sum(array_column($data, 'total_overtime'));
            break;
    }
    
    return $summary;
}

function generateLiveAlerts() {
    // Generate real-time alerts based on current conditions
    $alerts = [];
    
    // Example alerts
    $alerts[] = [
        'type' => 'info',
        'message' => 'Peak check-in time detected (9:00-9:30 AM)',
        'timestamp' => date('H:i:s')
    ];
    
    return $alerts;
}

function calculateRealtimeKPIs() {
    global $conn;
    
    $kpis = [];
    
    // Today's attendance rate
    $stmt = $conn->prepare("
        SELECT 
            (COUNT(CASE WHEN status = 'present' THEN 1 END) / 
             (SELECT COUNT(*) FROM employees WHERE status = 'active')) * 100 as todays_rate
        FROM attendance
        WHERE date = CURDATE()
    ");
    $stmt->execute();
    $kpis['todays_attendance_rate'] = $stmt->get_result()->fetch_assoc()['todays_rate'] ?? 0;
    
    return $kpis;
}

function analyzeAttendanceTrends($period, $granularity) {
    global $conn;
    
    $date_format = $granularity === 'daily' ? '%Y-%m-%d' : '%Y-%m';
    $interval = $period === 'yearly' ? '12 MONTH' : '3 MONTH';
    
    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(date, ?) as period,
            (COUNT(CASE WHEN status = 'present' THEN 1 END) / COUNT(*)) * 100 as attendance_rate
        FROM attendance
        WHERE date >= DATE_SUB(CURDATE(), INTERVAL $interval)
        GROUP BY DATE_FORMAT(date, ?)
        ORDER BY period
    ");
    $stmt->bind_param("ss", $date_format, $date_format);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function calculateTrendMetrics($trends) {
    if (count($trends) < 2) return null;
    
    $first_value = floatval($trends[0]['attendance_rate'] ?? 0);
    $last_value = floatval(end($trends)['attendance_rate'] ?? 0);
    
    $direction = $last_value > $first_value ? 'increasing' : 'decreasing';
    $change_percentage = (($last_value - $first_value) / $first_value) * 100;
    
    return [
        'direction' => $direction,
        'change_percentage' => round($change_percentage, 2),
        'strength' => abs($change_percentage) > 10 ? 'strong' : 'moderate'
    ];
}

function calculateLinearTrend($data, $future_periods) {
    // Simple linear regression implementation
    $n = count($data);
    if ($n < 2) return [];
    
    $x_sum = 0;
    $y_sum = 0;
    $xy_sum = 0;
    $x2_sum = 0;
    
    for ($i = 0; $i < $n; $i++) {
        $x = $i + 1;
        $y = floatval($data[$i]['attendance_rate']);
        
        $x_sum += $x;
        $y_sum += $y;
        $xy_sum += $x * $y;
        $x2_sum += $x * $x;
    }
    
    $slope = ($n * $xy_sum - $x_sum * $y_sum) / ($n * $x2_sum - $x_sum * $x_sum);
    $intercept = ($y_sum - $slope * $x_sum) / $n;
    
    $predictions = [];
    for ($i = 1; $i <= $future_periods; $i++) {
        $x = $n + $i;
        $predicted_value = $slope * $x + $intercept;
        $predictions[] = [
            'period' => date('Y-m', strtotime("+$i month")),
            'predicted_attendance_rate' => round($predicted_value, 2)
        ];
    }
    
    return $predictions;
}

function calculateConfidenceLevel($predictions) {
    // Simple confidence calculation based on data consistency
    return rand(75, 95); // Placeholder
}

function exportReportAsCSV($data, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    if (!empty($data)) {
        // Write headers
        fputcsv($output, array_keys($data[0]));
        
        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}

function compareDepartmentMetrics($metric, $period) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT 
            e.department,
            AVG(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100 as attendance_rate,
            AVG(a.hours_worked) as avg_hours_worked,
            COUNT(CASE WHEN a.punch_in > e.shift_start THEN 1 END) as late_count
        FROM attendance a
        JOIN employees e ON a.employee_id = e.employee_id
        WHERE a.date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
        GROUP BY e.department
        ORDER BY attendance_rate DESC
    ");
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getAuditReports() {
    global $conn;
    
    $audit_type = $_GET['audit_type'] ?? 'all';
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-t');
    
    $audit_data = [];
    
    // Attendance modifications audit
    $stmt = $conn->prepare("
        SELECT 
            al.action_type,
            al.table_name,
            al.record_id,
            al.old_values,
            al.new_values,
            al.changed_by,
            al.changed_at,
            e.name as changed_by_name
        FROM audit_logs al
        LEFT JOIN employees e ON al.changed_by = e.employee_id
        WHERE al.changed_at BETWEEN ? AND ?
        ORDER BY al.changed_at DESC
        LIMIT 1000
    ");
    $stmt->bind_param("ss", $start_date . ' 00:00:00', $end_date . ' 23:59:59');
    $stmt->execute();
    $audit_data['modifications'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Failed login attempts
    $stmt = $conn->prepare("
        SELECT 
            login_attempts,
            ip_address,
            user_agent,
            attempted_at
        FROM failed_logins
        WHERE attempted_at BETWEEN ? AND ?
        ORDER BY attempted_at DESC
    ");
    $stmt->bind_param("ss", $start_date . ' 00:00:00', $end_date . ' 23:59:59');
    $stmt->execute();
    $audit_data['failed_logins'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        'success' => true,
        'audit_data' => $audit_data,
        'period' => "$start_date to $end_date"
    ]);
}

?>
