<?php
// HRMS Universal AJAX and Modal Fix
// This file provides common AJAX functions and modal handlers for all HRMS modules

header('Content-Type: application/json');
require_once '../auth_check.php';
require_once '../db.php';

$response = ['success' => false, 'message' => 'Invalid request'];

if ($_POST && isset($_POST['action'])) {
    try {
        $action = $_POST['action'];
        $currentUserId = $_SESSION['user_id'];
        $currentUserRole = $_SESSION['role'] ?? 'employee';
        
        switch ($action) {
            // Generic record fetching for any HRMS table
            case 'get_record':
                $id = intval($_POST['id'] ?? 0);
                $table = $_POST['table'] ?? '';
                
                // Security: Only allow specific HRMS tables
                $allowedTables = [
                    'hr_employees',
                    'hr_departments', 
                    'hr_attendance',
                    'hr_leave_applications',
                    'hr_payroll',
                    'hr_onboarding_process',
                    'hr_benefits',
                    'hr_assets'
                ];
                
                if (!in_array($table, $allowedTables)) {
                    $response = ['success' => false, 'message' => 'Invalid table specified'];
                    break;
                }
                
                $stmt = $conn->prepare("SELECT * FROM $table WHERE id = ? LIMIT 1");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result && $result->num_rows > 0) {
                    $data = $result->fetch_assoc();
                    $response = [
                        'success' => true, 
                        'data' => $data,
                        'message' => 'Record retrieved successfully'
                    ];
                } else {
                    $response = ['success' => false, 'message' => 'Record not found'];
                }
                break;
                
            // Generic record deletion for any HRMS table
            case 'delete_record':
                $id = intval($_POST['id'] ?? 0);
                $table = $_POST['table'] ?? '';
                
                // Security checks
                $allowedTables = [
                    'hr_employees',
                    'hr_departments', 
                    'hr_attendance',
                    'hr_leave_applications',
                    'hr_payroll',
                    'hr_onboarding_tasks',
                    'hr_benefits',
                    'hr_assets'
                ];
                
                if (!in_array($table, $allowedTables)) {
                    $response = ['success' => false, 'message' => 'Invalid table specified'];
                    break;
                }
                
                // Role-based permission check
                if ($currentUserRole !== 'admin' && $currentUserRole !== 'hr') {
                    $response = ['success' => false, 'message' => 'Insufficient permissions'];
                    break;
                }
                
                $stmt = $conn->prepare("DELETE FROM $table WHERE id = ? LIMIT 1");
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $response = [
                        'success' => true,
                        'message' => 'Record deleted successfully'
                    ];
                } else {
                    $response = ['success' => false, 'message' => 'Failed to delete record'];
                }
                break;
                
            // Employee-specific actions
            case 'get_employee':
                $id = intval($_POST['id'] ?? 0);
                
                $stmt = $conn->prepare("
                    SELECT e.*, d.department_name 
                    FROM hr_employees e 
                    LEFT JOIN hr_departments d ON e.department_id = d.id 
                    WHERE e.employee_id = ? LIMIT 1
                ");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result && $result->num_rows > 0) {
                    $data = $result->fetch_assoc();
                    $response = [
                        'success' => true, 
                        'data' => $data,
                        'message' => 'Employee retrieved successfully'
                    ];
                } else {
                    $response = ['success' => false, 'message' => 'Employee not found'];
                }
                break;
                
            // Update employee status
            case 'update_employee_status':
                $id = intval($_POST['id'] ?? 0);
                $status = $_POST['status'] ?? '';
                
                if (!in_array($status, ['active', 'inactive', 'terminated'])) {
                    $response = ['success' => false, 'message' => 'Invalid status'];
                    break;
                }
                
                if ($currentUserRole !== 'admin' && $currentUserRole !== 'hr') {
                    $response = ['success' => false, 'message' => 'Insufficient permissions'];
                    break;
                }
                
                $stmt = $conn->prepare("UPDATE hr_employees SET status = ? WHERE employee_id = ?");
                $stmt->bind_param("si", $status, $id);
                
                if ($stmt->execute()) {
                    $response = [
                        'success' => true,
                        'message' => 'Employee status updated successfully'
                    ];
                } else {
                    $response = ['success' => false, 'message' => 'Failed to update status'];
                }
                break;
                
            // Attendance actions
            case 'mark_attendance':
                $employee_id = intval($_POST['employee_id'] ?? 0);
                $date = $_POST['date'] ?? date('Y-m-d');
                $clock_in = $_POST['clock_in'] ?? null;
                $clock_out = $_POST['clock_out'] ?? null;
                $status = $_POST['status'] ?? 'present';
                
                // Calculate total hours if both times provided
                $total_hours = 0;
                if ($clock_in && $clock_out) {
                    $in_time = new DateTime($clock_in);
                    $out_time = new DateTime($clock_out);
                    $diff = $out_time->diff($in_time);
                    $total_hours = $diff->h + ($diff->i / 60);
                }
                
                $stmt = $conn->prepare("
                    INSERT INTO hr_attendance (employee_id, date, clock_in_time, clock_out_time, total_hours, status) 
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    clock_in_time = COALESCE(?, clock_in_time),
                    clock_out_time = COALESCE(?, clock_out_time),
                    total_hours = ?,
                    status = ?
                ");
                
                $stmt->bind_param("isssdssds", 
                    $employee_id, $date, $clock_in, $clock_out, $total_hours, $status,
                    $clock_in, $clock_out, $total_hours, $status
                );
                
                if ($stmt->execute()) {
                    $response = [
                        'success' => true,
                        'message' => 'Attendance marked successfully'
                    ];
                } else {
                    $response = ['success' => false, 'message' => 'Failed to mark attendance'];
                }
                break;
                
            // Leave application actions
            case 'approve_leave':
                $id = intval($_POST['id'] ?? 0);
                $status = $_POST['status'] ?? 'approved'; // approved or rejected
                $remarks = $_POST['remarks'] ?? '';
                
                if ($currentUserRole !== 'admin' && $currentUserRole !== 'hr') {
                    $response = ['success' => false, 'message' => 'Insufficient permissions'];
                    break;
                }
                
                $stmt = $conn->prepare("
                    UPDATE hr_leave_applications 
                    SET status = ?, approved_by = ?, approved_date = NOW(), rejection_reason = ? 
                    WHERE id = ?
                ");
                $stmt->bind_param("sisi", $status, $currentUserId, $remarks, $id);
                
                if ($stmt->execute()) {
                    $response = [
                        'success' => true,
                        'message' => 'Leave application ' . $status . ' successfully'
                    ];
                } else {
                    $response = ['success' => false, 'message' => 'Failed to update leave application'];
                }
                break;
                
            // Get dashboard statistics
            case 'get_dashboard_stats':
                $stats = [];
                
                // Total employees
                $result = $conn->query("SELECT COUNT(*) as count FROM hr_employees WHERE status = 'active'");
                $stats['total_employees'] = $result ? $result->fetch_assoc()['count'] : 0;
                
                // Present today
                $today = date('Y-m-d');
                $result = $conn->query("SELECT COUNT(*) as count FROM hr_attendance WHERE date = '$today' AND status = 'present'");
                $stats['present_today'] = $result ? $result->fetch_assoc()['count'] : 0;
                
                // Pending leaves
                $result = $conn->query("SELECT COUNT(*) as count FROM hr_leave_applications WHERE status = 'pending'");
                $stats['pending_leaves'] = $result ? $result->fetch_assoc()['count'] : 0;
                
                // Active departments
                $result = $conn->query("SELECT COUNT(*) as count FROM hr_departments WHERE status = 'active'");
                $stats['active_departments'] = $result ? $result->fetch_assoc()['count'] : 0;
                
                $response = [
                    'success' => true,
                    'data' => $stats,
                    'message' => 'Dashboard statistics retrieved'
                ];
                break;
                
            // Export data
            case 'export_data':
                $table = $_POST['table'] ?? '';
                $format = $_POST['format'] ?? 'csv';
                
                if ($currentUserRole !== 'admin' && $currentUserRole !== 'hr') {
                    $response = ['success' => false, 'message' => 'Insufficient permissions'];
                    break;
                }
                
                // Generate export file URL or trigger download
                $response = [
                    'success' => true,
                    'message' => 'Export initiated',
                    'download_url' => 'export.php?table=' . $table . '&format=' . $format
                ];
                break;
                
            // Test database connection
            case 'test_connection':
                if ($conn && !$conn->connect_error) {
                    // Test a simple query
                    $result = $conn->query("SELECT 1 as test");
                    if ($result) {
                        $response = [
                            'success' => true,
                            'message' => 'Database connection is working',
                            'server_info' => $conn->server_info
                        ];
                    } else {
                        $response = ['success' => false, 'message' => 'Query failed: ' . $conn->error];
                    }
                } else {
                    $response = ['success' => false, 'message' => 'Database connection failed'];
                }
                break;
                
            default:
                $response = ['success' => false, 'message' => 'Unknown action: ' . $action];
                break;
        }
        
    } catch (Exception $e) {
        $response = [
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage(),
            'error_details' => $e->getTraceAsString()
        ];
    }
}

echo json_encode($response);
exit;

require_once '../layouts/footer.php';
?>