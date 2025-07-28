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
        case 'get_attendance_report':
            $startDate = $input['start_date'] ?? date('Y-m-01');
            $endDate = $input['end_date'] ?? date('Y-m-t');
            $employeeId = $input['employee_id'] ?? null;
            $department = $input['department'] ?? null;

            $whereClause = ["a.date BETWEEN ? AND ?"];
            $params = [$startDate, $endDate];
            $types = 'ss';

            if ($employeeId) {
                $whereClause[] = "a.employee_id = ?";
                $params[] = $employeeId;
                $types .= 'i';
            }

            if ($department) {
                $whereClause[] = "e.department = ?";
                $params[] = $department;
                $types .= 's';
            }

            $whereSQL = implode(' AND ', $whereClause);

            $sql = "
                SELECT a.*, e.first_name, e.last_name, e.employee_id as emp_code, e.department,
                       CASE 
                           WHEN a.punch_in_time IS NULL THEN 'Absent'
                           WHEN a.punch_out_time IS NULL THEN 'Present (No Out)'
                           ELSE 'Present'
                       END as status,
                       CASE 
                           WHEN a.punch_in_time > '09:30:00' THEN 'Late'
                           ELSE 'On Time'
                       END as punctuality
                FROM attendance a
                RIGHT JOIN employees e ON a.employee_id = e.id
                WHERE {$whereSQL}
                ORDER BY a.date DESC, e.first_name ASC
            ";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();

            $attendance = [];
            while ($row = $result->fetch_assoc()) {
                $workHours = 0;
                if ($row['punch_in_time'] && $row['punch_out_time']) {
                    $punchIn = strtotime($row['date'] . ' ' . $row['punch_in_time']);
                    $punchOut = strtotime($row['date'] . ' ' . $row['punch_out_time']);
                    $workHours = round(($punchOut - $punchIn) / 3600, 2);
                }

                $attendance[] = [
                    'employee_id' => $row['employee_id'],
                    'employee_name' => $row['first_name'] . ' ' . $row['last_name'],
                    'employee_code' => $row['emp_code'],
                    'department' => $row['department'],
                    'date' => $row['date'],
                    'punch_in' => $row['punch_in_time'],
                    'punch_out' => $row['punch_out_time'],
                    'work_hours' => $workHours,
                    'status' => $row['status'],
                    'punctuality' => $row['punctuality'],
                    'location' => $row['location'],
                    'punch_method' => $row['punch_method']
                ];
            }

            echo json_encode([
                'success' => true,
                'attendance' => $attendance,
                'period' => ['start_date' => $startDate, 'end_date' => $endDate]
            ]);
            break;

        case 'get_dashboard_stats':
            $date = $input['date'] ?? date('Y-m-d');
            
            // Get total employees
            $totalEmployees = $conn->query("SELECT COUNT(*) as count FROM employees WHERE status = 'active'")->fetch_assoc()['count'];
            
            // Get present employees
            $presentEmployees = $conn->query("
                SELECT COUNT(*) as count FROM attendance 
                WHERE date = '{$date}' AND punch_in_time IS NOT NULL
            ")->fetch_assoc()['count'];
            
            // Get absent employees
            $absentEmployees = $totalEmployees - $presentEmployees;
            
            // Get late employees
            $lateEmployees = $conn->query("
                SELECT COUNT(*) as count FROM attendance 
                WHERE date = '{$date}' AND punch_in_time > '09:30:00'
            ")->fetch_assoc()['count'];
            
            // Get early departures
            $earlyDepartures = $conn->query("
                SELECT COUNT(*) as count FROM attendance 
                WHERE date = '{$date}' AND punch_out_time < '17:00:00' AND punch_out_time IS NOT NULL
            ")->fetch_assoc()['count'];

            // Get overtime employees
            $overtimeEmployees = $conn->query("
                SELECT COUNT(*) as count FROM attendance 
                WHERE date = '{$date}' AND punch_out_time > '18:00:00'
            ")->fetch_assoc()['count'];

            echo json_encode([
                'success' => true,
                'stats' => [
                    'date' => $date,
                    'total_employees' => $totalEmployees,
                    'present_employees' => $presentEmployees,
                    'absent_employees' => $absentEmployees,
                    'late_employees' => $lateEmployees,
                    'early_departures' => $earlyDepartures,
                    'overtime_employees' => $overtimeEmployees,
                    'attendance_percentage' => $totalEmployees > 0 ? round(($presentEmployees / $totalEmployees) * 100, 2) : 0
                ]
            ]);
            break;

        case 'get_monthly_summary':
            $month = $input['month'] ?? date('Y-m');
            $employeeId = $input['employee_id'] ?? null;

            $whereClause = "WHERE DATE_FORMAT(a.date, '%Y-%m') = ?";
            $params = [$month];
            $types = 's';

            if ($employeeId) {
                $whereClause .= " AND a.employee_id = ?";
                $params[] = $employeeId;
                $types .= 'i';
            }

            $sql = "
                SELECT 
                    e.id, e.first_name, e.last_name, e.employee_id as emp_code,
                    COUNT(a.date) as days_present,
                    SUM(CASE WHEN a.punch_in_time > '09:30:00' THEN 1 ELSE 0 END) as late_days,
                    SUM(CASE WHEN a.punch_out_time < '17:00:00' AND a.punch_out_time IS NOT NULL THEN 1 ELSE 0 END) as early_departures,
                    SUM(CASE WHEN a.punch_out_time > '18:00:00' THEN 1 ELSE 0 END) as overtime_days,
                    ROUND(AVG(
                        CASE 
                            WHEN a.punch_in_time IS NOT NULL AND a.punch_out_time IS NOT NULL 
                            THEN TIME_TO_SEC(TIMEDIFF(a.punch_out_time, a.punch_in_time)) / 3600 
                            ELSE 0 
                        END
                    ), 2) as avg_work_hours,
                    SUM(
                        CASE 
                            WHEN a.punch_in_time IS NOT NULL AND a.punch_out_time IS NOT NULL 
                            THEN TIME_TO_SEC(TIMEDIFF(a.punch_out_time, a.punch_in_time)) / 3600 
                            ELSE 0 
                        END
                    ) as total_work_hours
                FROM employees e
                LEFT JOIN attendance a ON e.id = a.employee_id {$whereClause}
                WHERE e.status = 'active'
                GROUP BY e.id
                ORDER BY e.first_name
            ";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();

            $summary = [];
            $totalWorkingDays = date('t', strtotime($month . '-01')); // Days in month

            while ($row = $result->fetch_assoc()) {
                $summary[] = [
                    'employee_id' => $row['id'],
                    'employee_name' => $row['first_name'] . ' ' . $row['last_name'],
                    'employee_code' => $row['emp_code'],
                    'days_present' => $row['days_present'],
                    'days_absent' => $totalWorkingDays - $row['days_present'],
                    'late_days' => $row['late_days'],
                    'early_departures' => $row['early_departures'],
                    'overtime_days' => $row['overtime_days'],
                    'avg_work_hours' => $row['avg_work_hours'],
                    'total_work_hours' => round($row['total_work_hours'], 2),
                    'attendance_percentage' => round(($row['days_present'] / $totalWorkingDays) * 100, 2)
                ];
            }

            echo json_encode([
                'success' => true,
                'summary' => $summary,
                'month' => $month,
                'working_days' => $totalWorkingDays
            ]);
            break;

        case 'bulk_attendance_update':
            $updates = $input['updates'] ?? [];
            
            if (empty($updates)) {
                throw new Exception('No updates provided');
            }

            $conn->begin_transaction();
            
            try {
                foreach ($updates as $update) {
                    $employeeId = $update['employee_id'];
                    $date = $update['date'];
                    $punchIn = $update['punch_in'] ?? null;
                    $punchOut = $update['punch_out'] ?? null;
                    $remarks = $update['remarks'] ?? '';

                    // Check if record exists
                    $checkStmt = $conn->prepare("SELECT id FROM attendance WHERE employee_id = ? AND date = ?");
                    $checkStmt->bind_param("is", $employeeId, $date);
                    $checkStmt->execute();
                    $exists = $checkStmt->get_result()->num_rows > 0;

                    if ($exists) {
                        $updateStmt = $conn->prepare("
                            UPDATE attendance 
                            SET punch_in_time = ?, punch_out_time = ?, remarks = ?, updated_at = NOW() 
                            WHERE employee_id = ? AND date = ?
                        ");
                        $updateStmt->bind_param("sssis", $punchIn, $punchOut, $remarks, $employeeId, $date);
                        $updateStmt->execute();
                    } else {
                        $insertStmt = $conn->prepare("
                            INSERT INTO attendance (employee_id, date, punch_in_time, punch_out_time, remarks, marked_by, created_at, updated_at)
                            VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())
                        ");
                        $insertStmt->bind_param("issss", $employeeId, $date, $punchIn, $punchOut, $remarks);
                        $insertStmt->execute();
                    }
                }

                $conn->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Bulk attendance updated successfully',
                    'updated_records' => count($updates)
                ]);
                
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
            break;

        case 'export_attendance':
            $startDate = $input['start_date'] ?? date('Y-m-01');
            $endDate = $input['end_date'] ?? date('Y-m-t');
            $format = $input['format'] ?? 'csv';

            $sql = "
                SELECT a.date, e.first_name, e.last_name, e.employee_id as emp_code,
                       a.punch_in_time, a.punch_out_time, a.location, a.punch_method,
                       CASE 
                           WHEN a.punch_in_time IS NULL THEN 'Absent'
                           WHEN a.punch_out_time IS NULL THEN 'Present (No Out)'
                           ELSE 'Present'
                       END as status
                FROM attendance a
                JOIN employees e ON a.employee_id = e.id
                WHERE a.date BETWEEN ? AND ?
                ORDER BY a.date, e.first_name
            ";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $startDate, $endDate);
            $stmt->execute();
            $result = $stmt->get_result();

            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }

            echo json_encode([
                'success' => true,
                'data' => $data,
                'format' => $format,
                'period' => ['start' => $startDate, 'end' => $endDate],
                'total_records' => count($data)
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