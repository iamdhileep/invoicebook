<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include '../db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'monthly_report':
            getMonthlyReport($conn);
            break;
            
        case 'employee_report':
            getEmployeeReport($conn);
            break;
            
        case 'payroll_report':
            getPayrollReport($conn);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function getMonthlyReport($conn) {
    $month = $_GET['month'] ?? date('Y-m');
    
    try {
        // Get monthly attendance summary
        $startDate = $month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));
        
        $query = "SELECT 
                     e.name,
                     COUNT(a.date) as total_days,
                     SUM(CASE WHEN a.status IN ('Present', 'Late') THEN 1 ELSE 0 END) as present_days,
                     SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
                     SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late_days,
                     AVG(CASE WHEN a.check_in_time IS NOT NULL AND a.check_out_time IS NOT NULL 
                         THEN TIME_TO_SEC(TIMEDIFF(a.check_out_time, a.check_in_time)) / 3600 
                         ELSE NULL END) as avg_hours,
                     SUM(CASE WHEN a.check_in_time IS NOT NULL AND a.check_out_time IS NOT NULL 
                         THEN TIME_TO_SEC(TIMEDIFF(a.check_out_time, a.check_in_time)) / 3600 
                         ELSE 0 END) as total_hours
                  FROM employees e 
                  LEFT JOIN attendance a ON e.id = a.employee_id AND a.date BETWEEN ? AND ?
                  GROUP BY e.id, e.name
                  ORDER BY e.name";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ss', $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $report = [];
        while ($row = $result->fetch_assoc()) {
            $row['avg_hours'] = round($row['avg_hours'] ?? 0, 2);
            $row['total_hours'] = round($row['total_hours'], 2);
            $report[] = $row;
        }
        
        // Summary statistics
        $summary = [
            'total_employees' => count($report),
            'avg_attendance_rate' => 0,
            'total_working_hours' => 0
        ];
        
        if (count($report) > 0) {
            $totalPresent = array_sum(array_column($report, 'present_days'));
            $totalDays = array_sum(array_column($report, 'total_days'));
            $summary['avg_attendance_rate'] = $totalDays > 0 ? round(($totalPresent / $totalDays) * 100, 2) : 0;
            $summary['total_working_hours'] = round(array_sum(array_column($report, 'total_hours')), 2);
        }
        
        echo json_encode([
            'success' => true,
            'report' => $report,
            'summary' => $summary,
            'period' => $month
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error generating monthly report: ' . $e->getMessage()]);
    }
}

function getEmployeeReport($conn) {
    $employeeId = $_GET['employee_id'] ?? '';
    $month = $_GET['month'] ?? date('Y-m');
    
    try {
        // Get employee details
        $empQuery = "SELECT name, employee_id, email FROM employees WHERE id = ?";
        $empStmt = $conn->prepare($empQuery);
        $empStmt->bind_param('i', $employeeId);
        $empStmt->execute();
        $employee = $empStmt->get_result()->fetch_assoc();
        
        if (!$employee) {
            echo json_encode(['success' => false, 'message' => 'Employee not found']);
            return;
        }
        
        // Get daily attendance for the month
        $startDate = $month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));
        
        $query = "SELECT 
                     date, check_in_time, check_out_time, status,
                     CASE WHEN check_in_time IS NOT NULL AND check_out_time IS NOT NULL 
                         THEN TIMEDIFF(check_out_time, check_in_time) 
                         ELSE '00:00:00' END as working_hours
                  FROM attendance 
                  WHERE employee_id = ? AND date BETWEEN ? AND ?
                  ORDER BY date";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('iss', $employeeId, $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $attendance = [];
        $stats = [
            'total_days' => 0,
            'present_days' => 0,
            'absent_days' => 0,
            'late_days' => 0,
            'total_hours' => 0
        ];
        
        while ($row = $result->fetch_assoc()) {
            $attendance[] = $row;
            $stats['total_days']++;
            
            switch ($row['status']) {
                case 'Present':
                    $stats['present_days']++;
                    break;
                case 'Late':
                    $stats['present_days']++;
                    $stats['late_days']++;
                    break;
                case 'Absent':
                    $stats['absent_days']++;
                    break;
            }
            
            if ($row['working_hours'] !== '00:00:00') {
                $time = explode(':', $row['working_hours']);
                $stats['total_hours'] += $time[0] + ($time[1] / 60);
            }
        }
        
        $stats['attendance_rate'] = $stats['total_days'] > 0 ? 
            round(($stats['present_days'] / $stats['total_days']) * 100, 2) : 0;
        $stats['avg_hours'] = $stats['present_days'] > 0 ? 
            round($stats['total_hours'] / $stats['present_days'], 2) : 0;
        
        echo json_encode([
            'success' => true,
            'employee' => $employee,
            'attendance' => $attendance,
            'stats' => $stats,
            'period' => $month
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error generating employee report: ' . $e->getMessage()]);
    }
}

function getPayrollReport($conn) {
    $month = $_GET['month'] ?? date('Y-m');
    
    try {
        $query = "SELECT 
                     e.name, e.employee_id,
                     COALESCE(p.gross_salary, 0) as gross_salary,
                     COALESCE(p.total_deductions, 0) as total_deductions,
                     COALESCE(p.net_salary, 0) as net_salary,
                     COALESCE(s.basic_salary, 25000) as basic_salary,
                     COALESCE(s.allowances, 5000) as allowances
                  FROM employees e 
                  LEFT JOIN payslips p ON e.id = p.employee_id AND p.month = ?
                  LEFT JOIN salaries s ON e.id = s.employee_id
                  ORDER BY e.name";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $month);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $report = [];
        $totals = [
            'total_employees' => 0,
            'total_gross' => 0,
            'total_deductions' => 0,
            'total_net' => 0
        ];
        
        while ($row = $result->fetch_assoc()) {
            $report[] = $row;
            $totals['total_employees']++;
            $totals['total_gross'] += $row['gross_salary'];
            $totals['total_deductions'] += $row['total_deductions'];
            $totals['total_net'] += $row['net_salary'];
        }
        
        echo json_encode([
            'success' => true,
            'report' => $report,
            'totals' => $totals,
            'period' => $month
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error generating payroll report: ' . $e->getMessage()]);
    }
}
?>
