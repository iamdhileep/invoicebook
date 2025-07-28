<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

// Check if user is logged in (support both session variables)
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Get form data
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $department_id = intval($_POST['department_id'] ?? 0);
    $position = trim($_POST['position'] ?? '');
    $salary = floatval($_POST['salary'] ?? 0);
    $hire_date = $_POST['hire_date'] ?? date('Y-m-d');

    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($email)) {
        throw new Exception('First name, last name, and email are required');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Check if email already exists
    $check_email = "SELECT id FROM employees WHERE email = ?";
    $check_stmt = $conn->prepare($check_email);
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        throw new Exception('Email already exists');
    }

    // Insert new employee
    $insert_query = "INSERT INTO employees (first_name, last_name, email, phone, department_id, position, salary, hire_date, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("ssssisds", $first_name, $last_name, $email, $phone, $department_id, $position, $salary, $hire_date);
    
    if ($stmt->execute()) {
        $employee_id = $conn->insert_id;
        
        // Initialize leave balance for new employee
        $leave_types = ['annual', 'sick', 'personal'];
        $leave_balances = [20, 10, 5]; // Default balances
        
        $leave_query = "INSERT INTO leave_balance (employee_id, leave_type, total_days, used_days, remaining_days) VALUES (?, ?, ?, 0, ?)";
        $leave_stmt = $conn->prepare($leave_query);
        
        for ($i = 0; $i < count($leave_types); $i++) {
            $leave_stmt->bind_param("isii", $employee_id, $leave_types[$i], $leave_balances[$i], $leave_balances[$i]);
            $leave_stmt->execute();
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Employee added successfully',
            'employee_id' => $employee_id
        ]);
    } else {
        throw new Exception('Failed to add employee');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
