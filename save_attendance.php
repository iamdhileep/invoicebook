<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';

// Set timezone
date_default_timezone_set('Asia/Kolkata');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Debug logging
        error_log("Attendance save started - POST data received");
        error_log("POST data: " . print_r($_POST, true));
        
        if (!isset($_POST['attendance_date']) || empty($_POST['attendance_date'])) {
            throw new Exception("Attendance date is required.");
        }

        $date = $_POST['attendance_date'];
        $employeeIds = $_POST['employee_id'] ?? [];  // Array of employee IDs
        $statuses = $_POST['status'] ?? [];
        $timeIns = $_POST['time_in'] ?? [];
        $timeOuts = $_POST['time_out'] ?? [];
        $notes = $_POST['notes'] ?? [];

        // Debug logging
        error_log("Date: $date");
        error_log("Employee IDs: " . print_r($employeeIds, true));
        error_log("Statuses: " . print_r($statuses, true));

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new Exception('Invalid date format');
        }

        // Check if we have any data to process
        if (empty($statuses)) {
            throw new Exception('No attendance data received');
        }

        // Start transaction
        $conn->begin_transaction();

        // Enhanced INSERT or UPDATE using UNIQUE constraint with error handling
        $query = "
            INSERT INTO attendance (employee_id, attendance_date, status, time_in, time_out, notes, marked_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE 
                status = VALUES(status),
                time_in = IF(VALUES(time_in) != '' AND VALUES(time_in) IS NOT NULL, VALUES(time_in), time_in),
                time_out = IF(VALUES(time_out) != '' AND VALUES(time_out) IS NOT NULL, VALUES(time_out), time_out),
                notes = VALUES(notes),
                marked_by = VALUES(marked_by),
                updated_at = NOW()
        ";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $successCount = 0;
        $marked_by = $_SESSION['admin'] ?? 'system';
        
        // Debug the arrays
        error_log("Employee IDs array: " . print_r($employeeIds, true));
        error_log("Statuses array keys: " . print_r(array_keys($statuses), true));

        // Process each employee's attendance
        // Use employee_id array as the primary source and match with status keys
        foreach ($employeeIds as $empId) {
            // Validate employee ID
            if (!is_numeric($empId) || $empId <= 0) {
                error_log("Invalid employee ID: $empId");
                continue;
            }
            
            // Check if we have status data for this employee
            if (!isset($statuses[$empId])) {
                error_log("No status data found for employee: $empId");
                continue;
            }
            
            $status = $statuses[$empId];
            // Handle time fields properly - convert empty strings to null
            $timeIn = (!empty($timeIns[$empId]) && trim($timeIns[$empId]) !== '') ? $timeIns[$empId] : null;
            $timeOut = (!empty($timeOuts[$empId]) && trim($timeOuts[$empId]) !== '') ? $timeOuts[$empId] : null;
            $note = $notes[$empId] ?? '';
            
            // Debug logging for each employee
            error_log("Processing employee $empId: status=$status, timeIn=$timeIn, timeOut=$timeOut");
            
            // Validate time format if provided
            if ($timeIn && !preg_match('/^\d{2}:\d{2}$/', $timeIn)) {
                error_log("Invalid time_in format for employee $empId: $timeIn");
                continue;
            }
            if ($timeOut && !preg_match('/^\d{2}:\d{2}$/', $timeOut)) {
                error_log("Invalid time_out format for employee $empId: $timeOut");
                continue;
            }
            
            // Validate status - updated to include all valid options
            $validStatuses = ['Present', 'Absent', 'Late', 'Half Day', 'WFH', 'On Leave', 'Short Leave'];
            if (!in_array($status, $validStatuses)) {
                error_log("Invalid status for employee $empId: $status");
                continue;
            }
            
            $stmt->bind_param("issssss", $empId, $date, $status, $timeIn, $timeOut, $note, $marked_by);
            
            if ($stmt->execute()) {
                $successCount++;
                error_log("Successfully saved attendance for employee $empId");
            } else {
                error_log("Failed to save attendance for employee $empId: " . $stmt->error);
            }
        }

        $stmt->close();
        $conn->commit();

        // Redirect with success flag
        if ($successCount > 0) {
            header("Location: pages/attendance/attendance.php?success=1&count=$successCount&date=$date");
        } else {
            throw new Exception("No attendance records were saved");
        }

    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        error_log("Attendance save error: " . $e->getMessage());
        header("Location: pages/attendance/attendance.php?error=1&message=" . urlencode($e->getMessage()));
    }
} else {
    header("Location: pages/attendance/attendance.php");
}

exit;
?>
