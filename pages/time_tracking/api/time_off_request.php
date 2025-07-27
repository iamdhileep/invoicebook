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
$request_type = $_POST['request_type'];
$start_date = $_POST['start_date'];
$end_date = $_POST['end_date'];
$reason = $_POST['reason'];

// Get employee details
$empQuery = $conn->prepare("SELECT name, department FROM employees WHERE employee_id = ?");
$empQuery->bind_param("i", $employee_id);
$empQuery->execute();
$empResult = $empQuery->get_result();

if ($empResult->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Employee not found']);
    exit;
}

$employee = $empResult->fetch_assoc();

// Calculate days requested
$start = new DateTime($start_date);
$end = new DateTime($end_date);
$interval = $start->diff($end);
$days_requested = $interval->days + 1;

try {
    $query = $conn->prepare("
        INSERT INTO time_off_requests 
        (employee_id, employee_name, department, request_type, start_date, end_date, days_requested, reason) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $query->bind_param("isssssiss", 
        $employee_id, 
        $employee['name'], 
        $employee['department'], 
        $request_type, 
        $start_date, 
        $end_date, 
        $days_requested, 
        $reason
    );
    
    if ($query->execute()) {
        echo json_encode(['success' => true, 'message' => 'Time off request submitted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit request']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
