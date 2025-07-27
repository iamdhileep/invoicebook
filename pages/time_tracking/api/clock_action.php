<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

include '../../../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$employee_id = (int)$_POST['employee_id'];
$action = $_POST['action'];
$status = $_POST['status'] ?? 'Present';
$notes = $_POST['notes'] ?? '';
$current_date = date('Y-m-d');
$current_datetime = date('Y-m-d H:i:s');
$ip_address = $_SERVER['REMOTE_ADDR'];

try {
    if ($action === 'clock_in') {
        // Check if already clocked in today
        $checkQuery = $conn->prepare("SELECT id, clock_in FROM time_clock WHERE employee_id = ? AND clock_date = ?");
        $checkQuery->bind_param("is", $employee_id, $current_date);
        $checkQuery->execute();
        $result = $checkQuery->get_result();
        
        if ($result->num_rows > 0) {
            $existing = $result->fetch_assoc();
            if ($existing['clock_in']) {
                echo json_encode(['success' => false, 'message' => 'Employee already clocked in today']);
                exit;
            }
        }
        
        // Insert or update clock in
        $query = $conn->prepare("
            INSERT INTO time_clock (employee_id, clock_date, clock_in, status, ip_address, notes) 
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            clock_in = VALUES(clock_in), 
            status = VALUES(status), 
            ip_address = VALUES(ip_address), 
            notes = VALUES(notes)
        ");
        $query->bind_param("isssss", $employee_id, $current_date, $current_datetime, $status, $ip_address, $notes);
        
        if ($query->execute()) {
            echo json_encode(['success' => true, 'message' => 'Clocked in successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to clock in']);
        }
        
    } elseif ($action === 'clock_out') {
        // Check if clocked in today
        $checkQuery = $conn->prepare("SELECT id, clock_in FROM time_clock WHERE employee_id = ? AND clock_date = ?");
        $checkQuery->bind_param("is", $employee_id, $current_date);
        $checkQuery->execute();
        $result = $checkQuery->get_result();
        
        if ($result->num_rows === 0 || !$result->fetch_assoc()['clock_in']) {
            echo json_encode(['success' => false, 'message' => 'Employee must clock in first']);
            exit;
        }
        
        // Update clock out and calculate total hours
        $query = $conn->prepare("
            UPDATE time_clock 
            SET clock_out = ?, 
                total_hours = TIMESTAMPDIFF(MINUTE, clock_in, ?) / 60,
                overtime_hours = GREATEST(0, (TIMESTAMPDIFF(MINUTE, clock_in, ?) / 60) - 8),
                notes = CONCAT(COALESCE(notes, ''), ?, ' [Clock out: ', ?, ']')
            WHERE employee_id = ? AND clock_date = ?
        ");
        $query->bind_param("ssssis", $current_datetime, $current_datetime, $current_datetime, $notes, $current_datetime, $employee_id, $current_date);
        
        if ($query->execute()) {
            echo json_encode(['success' => true, 'message' => 'Clocked out successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to clock out']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
