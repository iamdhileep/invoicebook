<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include 'db.php';

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$action = $input['action'] ?? '';
$employee_id = intval($input['employee_id'] ?? 0);
$attendance_date = $input['attendance_date'] ?? date('Y-m-d');

// Enhanced validation
if (!$employee_id) {
    echo json_encode(['success' => false, 'message' => 'Employee ID is required. Received: ' . ($input['employee_id'] ?? 'null')]);
    exit;
}

// Verify employee exists
$empCheckQuery = $conn->prepare("SELECT employee_id, name, employee_code FROM employees WHERE employee_id = ?");
$empCheckQuery->bind_param("i", $employee_id);
$empCheckQuery->execute();
$empCheckResult = $empCheckQuery->get_result();

if ($empCheckResult->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => "Employee ID {$employee_id} not found in database"]);
    exit;
}

$employeeInfo = $empCheckResult->fetch_assoc();

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $attendance_date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

// Log the request for debugging
error_log("Punch Request: " . json_encode([
    'action' => $action,
    'employee_id' => $employee_id,
    'attendance_date' => $attendance_date,
    'employee_info' => $employeeInfo
]));

try {
    $conn->begin_transaction();
    
    switch ($action) {
        case 'punch_in':
            $current_time = date('H:i:s');
            
            // Check if employee already has attendance record for today
            $checkQuery = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?");
            $checkQuery->bind_param("is", $employee_id, $attendance_date);
            $checkQuery->execute();
            $existing = $checkQuery->get_result()->fetch_assoc();
            
            if ($existing) {
                // Update existing record with punch in time
                $updateQuery = $conn->prepare("UPDATE attendance SET time_in = ?, status = 'Present' WHERE employee_id = ? AND attendance_date = ?");
                $updateQuery->bind_param("sis", $current_time, $employee_id, $attendance_date);
                if ($updateQuery->execute()) {
                    error_log("Punch In: Updated existing record for employee {$employee_id} on {$attendance_date} at {$current_time}");
                } else {
                    error_log("Punch In Update Error: " . $updateQuery->error);
                }
            } else {
                // Create new record
                $insertQuery = $conn->prepare("INSERT INTO attendance (employee_id, attendance_date, status, time_in) VALUES (?, ?, 'Present', ?)");
                $insertQuery->bind_param("iss", $employee_id, $attendance_date, $current_time);
                if ($insertQuery->execute()) {
                    error_log("Punch In: Created new record for employee {$employee_id} on {$attendance_date} at {$current_time}");
                } else {
                    error_log("Punch In Insert Error: " . $insertQuery->error);
                }
            }
            
            $conn->commit();
            echo json_encode([
                'success' => true, 
                'message' => $employeeInfo['name'] . ' (ID: ' . $employee_id . ') punched in successfully at ' . date('h:i A', strtotime($current_time)),
                'time' => date('h:i A', strtotime($current_time)),
                'time_24' => $current_time,
                'employee_name' => $employeeInfo['name'],
                'employee_code' => $employeeInfo['employee_code']
            ]);
            break;
            
        case 'punch_out':
            $current_time = date('H:i:s');
            
            // Check if employee has attendance record for today
            $checkQuery = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?");
            $checkQuery->bind_param("is", $employee_id, $attendance_date);
            $checkQuery->execute();
            $existing = $checkQuery->get_result()->fetch_assoc();
            
            if ($existing) {
                // Update existing record with punch out time
                $updateQuery = $conn->prepare("UPDATE attendance SET time_out = ? WHERE employee_id = ? AND attendance_date = ?");
                $updateQuery->bind_param("sis", $current_time, $employee_id, $attendance_date);
                if ($updateQuery->execute()) {
                    error_log("Punch Out: Updated existing record for employee {$employee_id} on {$attendance_date} at {$current_time}");
                } else {
                    error_log("Punch Out Update Error: " . $updateQuery->error);
                }
            } else {
                // Create new record with punch out (shouldn't happen, but handle it)
                $insertQuery = $conn->prepare("INSERT INTO attendance (employee_id, attendance_date, status, time_out) VALUES (?, ?, 'Present', ?)");
                $insertQuery->bind_param("iss", $employee_id, $attendance_date, $current_time);
                if ($insertQuery->execute()) {
                    error_log("Punch Out: Created new record for employee {$employee_id} on {$attendance_date} at {$current_time}");
                } else {
                    error_log("Punch Out Insert Error: " . $insertQuery->error);
                }
            }
            
            $conn->commit();
            echo json_encode([
                'success' => true, 
                'message' => $employeeInfo['name'] . ' (ID: ' . $employee_id . ') punched out successfully at ' . date('h:i A', strtotime($current_time)),
                'time' => date('h:i A', strtotime($current_time)),
                'time_24' => $current_time,
                'employee_name' => $employeeInfo['name'],
                'employee_code' => $employeeInfo['employee_code']
            ]);
            break;
            
        case 'get_status':
            // Get current attendance status for the employee
            $statusQuery = $conn->prepare("
                SELECT 
                    status, 
                    time_in, 
                    time_out,
                    CASE 
                        WHEN time_in IS NOT NULL AND time_out IS NULL THEN 'punched_in'
                        WHEN time_in IS NOT NULL AND time_out IS NOT NULL THEN 'punched_out'
                        ELSE 'not_punched'
                    END as punch_status
                FROM attendance 
                WHERE employee_id = ? AND attendance_date = ?
            ");
            $statusQuery->bind_param("is", $employee_id, $attendance_date);
            $statusQuery->execute();
            $status = $statusQuery->get_result()->fetch_assoc();
            
            if ($status) {
                echo json_encode([
                    'success' => true,
                    'status' => $status['status'],
                    'time_in' => $status['time_in'] ? date('h:i A', strtotime($status['time_in'])) : null,
                    'time_out' => $status['time_out'] ? date('h:i A', strtotime($status['time_out'])) : null,
                    'time_in_24' => $status['time_in'],
                    'time_out_24' => $status['time_out'],
                    'punch_status' => $status['punch_status']
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'status' => null,
                    'time_in' => null,
                    'time_out' => null,
                    'punch_status' => 'not_punched'
                ]);
            }
            break;
            
        case 'bulk_punch_in':
            $current_time = date('H:i:s');
            $employee_ids = $input['employee_ids'] ?? [];
            
            if (empty($employee_ids)) {
                echo json_encode(['success' => false, 'message' => 'No employees selected']);
                exit;
            }
            
            $success_count = 0;
            foreach ($employee_ids as $emp_id) {
                $emp_id = intval($emp_id);
                
                // Check if employee already has attendance record for today
                $checkQuery = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?");
                $checkQuery->bind_param("is", $emp_id, $attendance_date);
                $checkQuery->execute();
                $existing = $checkQuery->get_result()->fetch_assoc();
                
                if ($existing) {
                    // Update existing record
                    $updateQuery = $conn->prepare("UPDATE attendance SET time_in = ?, status = 'Present' WHERE employee_id = ? AND attendance_date = ?");
                    $updateQuery->bind_param("sis", $current_time, $emp_id, $attendance_date);
                    if ($updateQuery->execute()) $success_count++;
                } else {
                    // Create new record
                    $insertQuery = $conn->prepare("INSERT INTO attendance (employee_id, attendance_date, status, time_in) VALUES (?, ?, 'Present', ?)");
                    $insertQuery->bind_param("iss", $emp_id, $attendance_date, $current_time);
                    if ($insertQuery->execute()) $success_count++;
                }
            }
            
            $conn->commit();
            echo json_encode([
                'success' => true,
                'message' => $success_count . ' employees punched in successfully at ' . date('h:i A', strtotime($current_time)),
                'time' => date('h:i A', strtotime($current_time)),
                'time_24' => $current_time,
                'count' => $success_count
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>