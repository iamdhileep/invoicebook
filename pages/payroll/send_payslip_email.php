<?php
session_start();
if (!isset($_SESSION['admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

include '../../db.php';

// Get parameters
$employee_id = $_POST['employee_id'] ?? null;
$month = $_POST['month'] ?? date('Y-m');

if (!$employee_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Employee ID is required']);
    exit;
}

// Get employee details including email
$employeeQuery = $conn->prepare("SELECT * FROM employees WHERE employee_id = ?");
$employeeQuery->bind_param("i", $employee_id);
$employeeQuery->execute();
$employee = $employeeQuery->get_result()->fetch_assoc();

if (!$employee) {
    http_response_code(404);
    echo json_encode(['error' => 'Employee not found']);
    exit;
}

$employeeEmail = $employee['email'] ?? null;
if (!$employeeEmail) {
    http_response_code(400);
    echo json_encode(['error' => 'Employee email address not found']);
    exit;
}

// For now, return success message - email functionality can be implemented with PHPMailer
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Payslip email functionality ready for implementation',
    'employee_email' => $employeeEmail,
    'employee_name' => $employee['name'] ?? 'N/A',
    'month' => $month
]);

// TODO: Implement actual email sending using PHPMailer
// This would include:
// 1. Generate PDF version of payslip
// 2. Attach to email
// 3. Send to employee email
// 4. Log the email sending activity
?>
