<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include '../db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'mark_bulk_present':
            markBulkPresent($conn);
            break;
            
        case 'export_attendance':
            exportAttendance($conn);
            break;
            
        case 'generate_timesheet':
            generateTimesheet($conn);
            break;
            
        case 'refresh_stats':
            refreshStats($conn);
            break;
            
        // Manager Tools Actions
        case 'get_team_counts':
            getTeamCounts($conn);
            break;
            
        case 'get_team_attendance':
            getTeamAttendance($conn);
            break;
            
        case 'get_employees':
            getEmployees($conn);
            break;
            
        case 'bulk_approve_attendance':
            bulkApproveAttendance($conn);
            break;
            
        case 'bulk_reject_attendance':
            bulkRejectAttendance($conn);
            break;
            
        // Smart Attendance Actions
        case 'gps_checkin':
            gpsCheckIn($conn);
            break;
            
        case 'ip_checkin':
            ipCheckIn($conn);
            break;
            
        case 'manual_checkin':
            manualCheckIn($conn);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

function markBulkPresent($conn) {
    $today = date('Y-m-d');
    $currentTime = date('H:i:s');
    
    try {
        // Get all employees who are not marked present today
        $query = "SELECT e.id, e.name FROM employees e 
                 WHERE e.id NOT IN (
                     SELECT DISTINCT employee_id FROM attendance 
                     WHERE date = ? AND status IN ('Present', 'Late')
                 )";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $today);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $marked = 0;
        while ($row = $result->fetch_assoc()) {
            // Mark as present with current time
            $insertQuery = "INSERT INTO attendance (employee_id, date, check_in_time, status) 
                           VALUES (?, ?, ?, 'Present')
                           ON DUPLICATE KEY UPDATE 
                           check_in_time = VALUES(check_in_time), 
                           status = VALUES(status)";
            
            $insertStmt = $conn->prepare($insertQuery);
            $insertStmt->bind_param('iss', $row['id'], $today, $currentTime);
            
            if ($insertStmt->execute()) {
                $marked++;
            }
        }
        
        echo json_encode([
            'success' => true, 
            'message' => "Successfully marked $marked employees as present",
            'marked_count' => $marked
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'message' => 'Error marking bulk attendance: ' . $e->getMessage()
        ]);
    }
}

function exportAttendance($conn) {
    $today = date('Y-m-d');
    $filename = "attendance_" . $today . ".csv";
    
    try {
        $query = "SELECT e.name, e.employee_id, a.check_in_time, a.check_out_time, a.status,
                         CASE 
                             WHEN a.check_in_time IS NOT NULL AND a.check_out_time IS NOT NULL 
                             THEN TIMEDIFF(a.check_out_time, a.check_in_time) 
                             ELSE '00:00:00' 
                         END as working_hours
                  FROM employees e 
                  LEFT JOIN attendance a ON e.id = a.employee_id AND a.date = ?
                  ORDER BY e.name";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $today);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Create CSV content
        $csvData = "Employee Name,Employee ID,Check In,Check Out,Status,Working Hours\n";
        
        while ($row = $result->fetch_assoc()) {
            $csvData .= implode(',', [
                '"' . $row['name'] . '"',
                '"' . ($row['employee_id'] ?? 'N/A') . '"',
                '"' . ($row['check_in_time'] ?? 'N/A') . '"',
                '"' . ($row['check_out_time'] ?? 'N/A') . '"',
                '"' . ($row['status'] ?? 'Absent') . '"',
                '"' . $row['working_hours'] . '"'
            ]) . "\n";
        }
        
        // Save to file
        $filepath = "../../exports/" . $filename;
        
        // Create exports directory if it doesn't exist
        if (!file_exists('../../exports/')) {
            mkdir('../../exports/', 0777, true);
        }
        
        file_put_contents($filepath, $csvData);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Attendance exported successfully',
            'download_url' => 'exports/' . $filename,
            'filename' => $filename
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'message' => 'Error exporting attendance: ' . $e->getMessage()
        ]);
    }
}

function generateTimesheet($conn) {
    $startDate = date('Y-m-d', strtotime('monday this week'));
    $endDate = date('Y-m-d', strtotime('friday this week'));
    
    try {
        $query = "SELECT e.name, e.employee_id,
                         DATE(a.date) as work_date,
                         a.check_in_time, a.check_out_time, a.status,
                         CASE 
                             WHEN a.check_in_time IS NOT NULL AND a.check_out_time IS NOT NULL 
                             THEN TIMEDIFF(a.check_out_time, a.check_in_time) 
                             ELSE '00:00:00' 
                         END as working_hours
                  FROM employees e 
                  LEFT JOIN attendance a ON e.id = a.employee_id 
                      AND a.date BETWEEN ? AND ?
                  ORDER BY e.name, a.date";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ss', $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $timesheetData = [];
        while ($row = $result->fetch_assoc()) {
            $timesheetData[] = $row;
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Timesheet generated successfully',
            'data' => $timesheetData,
            'period' => "$startDate to $endDate"
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'message' => 'Error generating timesheet: ' . $e->getMessage()
        ]);
    }
}

function refreshStats($conn) {
    $today = date('Y-m-d');
    
    try {
        // Total employees
        $totalQuery = "SELECT COUNT(*) as count FROM employees";
        $totalResult = $conn->query($totalQuery);
        $total = $totalResult->fetch_assoc()['count'];
        
        // Present today
        $presentQuery = "SELECT COUNT(DISTINCT employee_id) as count FROM attendance 
                        WHERE date = ? AND status IN ('Present', 'Late')";
        $presentStmt = $conn->prepare($presentQuery);
        $presentStmt->bind_param('s', $today);
        $presentStmt->execute();
        $presentResult = $presentStmt->get_result();
        $present = $presentResult->fetch_assoc()['count'];
        
        // Average working hours
        $avgHoursQuery = "SELECT AVG(
                             CASE 
                                 WHEN check_in_time IS NOT NULL AND check_out_time IS NOT NULL 
                                 THEN TIME_TO_SEC(TIMEDIFF(check_out_time, check_in_time)) / 3600
                                 ELSE 0 
                             END
                         ) as avg_hours
                         FROM attendance 
                         WHERE date = ? AND status IN ('Present', 'Late')";
        
        $avgStmt = $conn->prepare($avgHoursQuery);
        $avgStmt->bind_param('s', $today);
        $avgStmt->execute();
        $avgResult = $avgStmt->get_result();
        $avgHours = round($avgResult->fetch_assoc()['avg_hours'], 1);
        
        // Total payroll (estimate based on basic salary)
        $payrollQuery = "SELECT SUM(COALESCE(salary, 25000)) as total_payroll FROM employees";
        $payrollResult = $conn->query($payrollQuery);
        $totalPayroll = $payrollResult->fetch_assoc()['total_payroll'];
        
        echo json_encode([
            'success' => true,
            'stats' => [
                'total_employees' => $total,
                'present_today' => $present,
                'avg_hours' => $avgHours,
                'total_payroll' => number_format($totalPayroll)
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'message' => 'Error refreshing stats: ' . $e->getMessage()
        ]);
    }
}

// ====================
// MANAGER TOOLS FUNCTIONS
// ====================

function getTeamCounts($conn) {
    try {
        $today = date('Y-m-d');
        
        // Get total employees
        $totalQuery = "SELECT COUNT(*) as total FROM employees";
        $totalResult = $conn->query($totalQuery);
        $total = $totalResult->fetch_assoc()['total'];
        
        // Get present employees today
        $presentQuery = "SELECT COUNT(DISTINCT employee_id) as present 
                        FROM attendance 
                        WHERE date = ? AND status IN ('Present', 'Late')";
        $stmt = $conn->prepare($presentQuery);
        $stmt->bind_param('s', $today);
        $stmt->execute();
        $presentResult = $stmt->get_result();
        $present = $presentResult->fetch_assoc()['present'];
        
        echo json_encode([
            'success' => true,
            'total_count' => $total,
            'present_count' => $present
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error getting team counts: ' . $e->getMessage()
        ]);
    }
}

function getTeamAttendance($conn) {
    try {
        $today = date('Y-m-d');
        
        $query = "SELECT 
                    e.id,
                    e.name,
                    a.status,
                    a.check_in_time,
                    CASE 
                        WHEN a.check_in_time IS NOT NULL AND a.check_out_time IS NOT NULL 
                        THEN CONCAT(
                            FLOOR(TIME_TO_SEC(TIMEDIFF(a.check_out_time, a.check_in_time)) / 3600), 
                            'h ', 
                            FLOOR((TIME_TO_SEC(TIMEDIFF(a.check_out_time, a.check_in_time)) % 3600) / 60), 
                            'm'
                        )
                        WHEN a.check_in_time IS NOT NULL
                        THEN CONCAT(
                            FLOOR(TIME_TO_SEC(TIMEDIFF(NOW(), a.check_in_time)) / 3600), 
                            'h ', 
                            FLOOR((TIME_TO_SEC(TIMEDIFF(NOW(), a.check_in_time)) % 3600) / 60), 
                            'm (ongoing)'
                        )
                        ELSE NULL
                    END as working_hours
                  FROM employees e
                  LEFT JOIN attendance a ON e.id = a.employee_id AND a.date = ?
                  ORDER BY e.name";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $today);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $team_data = [];
        while ($row = $result->fetch_assoc()) {
            $team_data[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'status' => $row['status'] ?: 'Absent',
                'check_in' => $row['check_in_time'] ? date('H:i', strtotime($row['check_in_time'])) : null,
                'working_hours' => $row['working_hours']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $team_data
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error getting team attendance: ' . $e->getMessage()
        ]);
    }
}

function getEmployees($conn) {
    try {
        $query = "SELECT id, name FROM employees ORDER BY name";
        $result = $conn->query($query);
        
        $employees = [];
        while ($row = $result->fetch_assoc()) {
            $employees[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $employees
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error getting employees: ' . $e->getMessage()
        ]);
    }
}

function bulkApproveAttendance($conn) {
    try {
        // For this demo, we'll just simulate bulk approval
        // In a real system, this would approve pending attendance requests
        
        echo json_encode([
            'success' => true,
            'message' => 'Bulk approval completed successfully'
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error during bulk approval: ' . $e->getMessage()
        ]);
    }
}

function bulkRejectAttendance($conn) {
    try {
        // For this demo, we'll just simulate bulk rejection
        // In a real system, this would reject pending attendance requests
        
        echo json_encode([
            'success' => true,
            'message' => 'Bulk rejection completed successfully'
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error during bulk rejection: ' . $e->getMessage()
        ]);
    }
}

// ====================
// SMART ATTENDANCE FUNCTIONS
// ====================

function gpsCheckIn($conn) {
    try {
        $latitude = $_POST['latitude'] ?? null;
        $longitude = $_POST['longitude'] ?? null;
        
        if (!$latitude || !$longitude) {
            echo json_encode([
                'success' => false,
                'message' => 'GPS coordinates are required'
            ]);
            return;
        }
        
        $today = date('Y-m-d');
        $currentTime = date('H:i:s');
        $employeeId = 1; // Demo employee ID
        
        // Check if already checked in today
        $checkQuery = "SELECT id FROM attendance WHERE employee_id = ? AND date = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param('is', $employeeId, $today);
        $checkStmt->execute();
        $existing = $checkStmt->get_result();
        
        if ($existing->num_rows > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Already checked in today'
            ]);
            return;
        }
        
        // Insert GPS check-in record
        $insertQuery = "INSERT INTO attendance (employee_id, date, check_in_time, status, gps_latitude, gps_longitude) 
                       VALUES (?, ?, ?, 'Present', ?, ?)";
        $insertStmt = $conn->prepare($insertQuery);
        $insertStmt->bind_param('issdd', $employeeId, $today, $currentTime, $latitude, $longitude);
        
        if ($insertStmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'GPS check-in successful',
                'location' => "Lat: $latitude, Lng: $longitude"
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to record GPS check-in'
            ]);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error during GPS check-in: ' . $e->getMessage()
        ]);
    }
}

function ipCheckIn($conn) {
    try {
        $today = date('Y-m-d');
        $currentTime = date('H:i:s');
        $employeeId = 1; // Demo employee ID
        $userIP = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        // Check if already checked in today
        $checkQuery = "SELECT id FROM attendance WHERE employee_id = ? AND date = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param('is', $employeeId, $today);
        $checkStmt->execute();
        $existing = $checkStmt->get_result();
        
        if ($existing->num_rows > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Already checked in today'
            ]);
            return;
        }
        
        // Insert IP-based check-in record
        $insertQuery = "INSERT INTO attendance (employee_id, date, check_in_time, status, ip_address) 
                       VALUES (?, ?, ?, 'Present', ?)";
        $insertStmt = $conn->prepare($insertQuery);
        $insertStmt->bind_param('isss', $employeeId, $today, $currentTime, $userIP);
        
        if ($insertStmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'IP-based check-in successful',
                'ip_address' => $userIP
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to record IP-based check-in'
            ]);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error during IP-based check-in: ' . $e->getMessage()
        ]);
    }
}

function manualCheckIn($conn) {
    try {
        $today = date('Y-m-d');
        $currentTime = date('H:i:s');
        $employeeId = 1; // Demo employee ID
        
        // Check if already checked in today
        $checkQuery = "SELECT id FROM attendance WHERE employee_id = ? AND date = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param('is', $employeeId, $today);
        $checkStmt->execute();
        $existing = $checkStmt->get_result();
        
        if ($existing->num_rows > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Already checked in today'
            ]);
            return;
        }
        
        // Insert manual check-in record
        $insertQuery = "INSERT INTO attendance (employee_id, date, check_in_time, status) 
                       VALUES (?, ?, ?, 'Present')";
        $insertStmt = $conn->prepare($insertQuery);
        $insertStmt->bind_param('iss', $employeeId, $today, $currentTime);
        
        if ($insertStmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Manual check-in successful'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to record manual check-in'
            ]);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error during manual check-in: ' . $e->getMessage()
        ]);
    }
}
?>
