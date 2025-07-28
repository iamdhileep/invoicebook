<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

include '../../db.php';

// Set timezone
date_default_timezone_set('Asia/Kolkata');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['employee_id', 'leave_type', 'start_date', 'end_date', 'reason'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required");
            }
        }

        // Sanitize input data
        $employee_id = (int)$_POST['employee_id'];
        $leave_type = mysqli_real_escape_string($conn, $_POST['leave_type']);
        $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
        $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
        $start_time = mysqli_real_escape_string($conn, $_POST['start_time'] ?? '');
        $end_time = mysqli_real_escape_string($conn, $_POST['end_time'] ?? '');
        $reason = mysqli_real_escape_string($conn, $_POST['reason']);
        $reason_category = mysqli_real_escape_string($conn, $_POST['reason_category'] ?? 'personal');
        $emergency_contact = mysqli_real_escape_string($conn, $_POST['emergency_contact'] ?? '');
        $priority = mysqli_real_escape_string($conn, $_POST['priority'] ?? 'normal');
        $notify_manager = isset($_POST['notify_manager']) ? 1 : 0;
        $handover_details = mysqli_real_escape_string($conn, $_POST['handover_details'] ?? '');

        // Calculate leave duration
        $start_timestamp = strtotime($start_date);
        $end_timestamp = strtotime($end_date);
        $duration_days = max(1, round(($end_timestamp - $start_timestamp) / (24 * 60 * 60)) + 1);

        // Special handling for different leave types
        switch ($leave_type) {
            case 'half-day':
                $duration_days = 0.5;
                break;
            case 'short-leave':
                $duration_days = 0.25;
                break;
        }

        // Validate employee exists
        $employee_check = $conn->prepare("SELECT name FROM employees WHERE employee_id = ? AND status = 'active'");
        $employee_check->bind_param("i", $employee_id);
        $employee_check->execute();
        $employee_result = $employee_check->get_result();
        
        if ($employee_result->num_rows === 0) {
            throw new Exception("Employee not found or inactive");
        }
        
        $employee_name = $employee_result->fetch_assoc()['name'];
        $employee_check->close();

        // Check for overlapping leave requests
        $overlap_check = $conn->prepare("
            SELECT id FROM leave_requests 
            WHERE employee_id = ? 
            AND status IN ('pending', 'approved') 
            AND (
                (start_date <= ? AND end_date >= ?) OR
                (start_date <= ? AND end_date >= ?) OR
                (start_date >= ? AND end_date <= ?)
            )
        ");
        $overlap_check->bind_param("issssss", 
            $employee_id, 
            $start_date, $start_date,
            $end_date, $end_date,
            $start_date, $end_date
        );
        $overlap_check->execute();
        $overlap_result = $overlap_check->get_result();
        
        if ($overlap_result->num_rows > 0) {
            throw new Exception("Leave request overlaps with existing leave");
        }
        $overlap_check->close();

        // Handle file upload if present
        $attachment_path = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../uploads/leave_attachments/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
            $filename = 'leave_' . $employee_id . '_' . time() . '.' . $file_extension;
            $attachment_path = $upload_dir . $filename;
            
            if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $attachment_path)) {
                throw new Exception("Failed to upload attachment");
            }
        }

        // Create leave request table if it doesn't exist
        $create_table_query = "
            CREATE TABLE IF NOT EXISTS leave_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                leave_type VARCHAR(50) NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                start_time TIME NULL,
                end_time TIME NULL,
                duration_days DECIMAL(3,1) NOT NULL,
                reason TEXT NOT NULL,
                reason_category VARCHAR(50) DEFAULT 'personal',
                emergency_contact VARCHAR(20) NULL,
                priority ENUM('normal', 'urgent', 'emergency') DEFAULT 'normal',
                handover_details TEXT NULL,
                attachment_path VARCHAR(255) NULL,
                status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
                applied_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                approved_date DATETIME NULL,
                approved_by INT NULL,
                manager_comments TEXT NULL,
                notify_manager BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_employee_id (employee_id),
                INDEX idx_status (status),
                INDEX idx_dates (start_date, end_date),
                FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
            )
        ";
        
        if (!$conn->query($create_table_query)) {
            error_log("Failed to create leave_requests table: " . $conn->error);
        }

        // Insert leave request
        $insert_query = $conn->prepare("
            INSERT INTO leave_requests (
                employee_id, leave_type, start_date, end_date, start_time, end_time,
                duration_days, reason, reason_category, emergency_contact, priority,
                handover_details, attachment_path, notify_manager, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        $insert_query->bind_param("isssssdssssssi",
            $employee_id, $leave_type, $start_date, $end_date, $start_time, $end_time,
            $duration_days, $reason, $reason_category, $emergency_contact, $priority,
            $handover_details, $attachment_path, $notify_manager
        );

        if ($insert_query->execute()) {
            $leave_request_id = $conn->insert_id;
            
            // Log the activity
            $activity_log = $conn->prepare("
                INSERT INTO activity_logs (user_type, user_id, action, description, timestamp) 
                VALUES ('employee', ?, 'leave_request', ?, NOW())
            ");
            $log_description = "Leave request submitted: $leave_type from $start_date to $end_date";
            $activity_log->bind_param("is", $employee_id, $log_description);
            $activity_log->execute();
            $activity_log->close();

            // Send notification if manager notification is enabled
            if ($notify_manager) {
                // In a real application, you would send email/SMS notifications here
                error_log("Manager notification requested for leave request ID: $leave_request_id");
            }

            echo json_encode([
                'success' => true,
                'message' => 'Leave request submitted successfully!',
                'request_id' => $leave_request_id,
                'employee_name' => $employee_name,
                'leave_type' => $leave_type,
                'duration' => $duration_days,
                'dates' => $start_date . ' to ' . $end_date
            ]);

        } else {
            throw new Exception("Failed to submit leave request: " . $conn->error);
        }

        $insert_query->close();

    } catch (Exception $e) {
        error_log("Leave request error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }

} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Handle GET requests for fetching leave data
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_balance':
            $employee_id = (int)($_GET['employee_id'] ?? 0);
            if ($employee_id > 0) {
                // Mock leave balance data - in real implementation, calculate from leave_requests table
                $balances = [
                    'casual' => 12,
                    'sick' => 7,
                    'earned' => 21,
                    'maternity' => 180,
                    'paternity' => 15,
                    'comp-off' => 3,
                    'wfh' => 999,
                    'half-day' => 999,
                    'short-leave' => 999,
                    'emergency' => 5
                ];
                
                echo json_encode(['success' => true, 'balances' => $balances]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
            }
            break;
            
        case 'get_recent_requests':
            $employee_id = (int)($_GET['employee_id'] ?? 0);
            $limit = (int)($_GET['limit'] ?? 5);
            
            try {
                $recent_query = $conn->prepare("
                    SELECT lr.*, e.name as employee_name 
                    FROM leave_requests lr 
                    JOIN employees e ON lr.employee_id = e.employee_id 
                    WHERE lr.employee_id = ? 
                    ORDER BY lr.applied_date DESC 
                    LIMIT ?
                ");
                $recent_query->bind_param("ii", $employee_id, $limit);
                $recent_query->execute();
                $result = $recent_query->get_result();
                
                $requests = [];
                while ($row = $result->fetch_assoc()) {
                    $requests[] = $row;
                }
                
                echo json_encode(['success' => true, 'requests' => $requests]);
                $recent_query->close();
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>
