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
$date = $_POST['date'];
$start_time = $_POST['start_time'];
$end_time = $_POST['end_time'];
$project_name = $_POST['project_name'] ?? '';
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

// Calculate hours requested
$start = new DateTime($start_time);
$end = new DateTime($end_time);
$interval = $start->diff($end);
$hours_requested = $interval->h + ($interval->i / 60);

try {
    $query = $conn->prepare("
        INSERT INTO overtime_requests 
        (employee_id, employee_name, department, date, start_time, end_time, hours_requested, reason, project_name) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $query->bind_param("isssssdsss", 
        $employee_id, 
        $employee['name'], 
        $employee['department'], 
        $date, 
        $start_time, 
        $end_time, 
        $hours_requested, 
        $reason, 
        $project_name
    );
    
    if ($query->execute()) {
        echo json_encode(['success' => true, 'message' => 'Overtime request submitted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit request']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
