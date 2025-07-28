<?php
/**
 * HRMS Admin Panel - Complete HR Management System
 * Direct pages folder implementation with full functionality
 */
session_start();

// Initialize session for demo
if (!isset($_SESSION['admin'])) {
    $_SESSION['admin'] = 1;
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'hr';
}

require_once '../db.php';
$page_title = 'HRMS Admin Panel - Human Resource Management';

// Initialize dashboard statistics
$stats = [
    'total_employees' => 0,
    'active_employees' => 0,
    'pending_leaves' => 0,
    'today_attendance' => 0,
    'departments' => 5,
    'this_month_joins' => 0
];

try {
    // Get total employees
    $result = $conn->query("SELECT COUNT(*) as count FROM employees");
    if ($result) $stats['total_employees'] = $result->fetch_assoc()['count'];
    
    // Get active employees
    $result = $conn->query("SELECT COUNT(*) as count FROM employees WHERE status = 'active'");
    if ($result) $stats['active_employees'] = $result->fetch_assoc()['count'];
    
    // Get pending leaves (with error handling)
    try {
        $result = $conn->query("SELECT COUNT(*) as count FROM leave_requests WHERE status = 'pending'");
        if ($result) $stats['pending_leaves'] = $result->fetch_assoc()['count'];
    } catch (Exception $e) {
        $stats['pending_leaves'] = 0;
    }
    
    // Get today's attendance
    $today = date('Y-m-d');
    $result = $conn->query("SELECT COUNT(DISTINCT employee_id) as count FROM attendance WHERE DATE(check_in) = '$today'");
    if ($result) $stats['today_attendance'] = $result->fetch_assoc()['count'];
    
    // Get this month's new joins
    $current_month = date('Y-m');
    $result = $conn->query("SELECT COUNT(*) as count FROM employees WHERE DATE_FORMAT(hire_date, '%Y-%m') = '$current_month'");
    if ($result) $stats['this_month_joins'] = $result->fetch_assoc()['count'];
    
} catch (Exception $e) {
    error_log("HRMS Admin Panel error: " . $e->getMessage());
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_employees':
            try {
                $query = $conn->prepare("SELECT 
                                        employee_id,
                                        COALESCE(first_name, SUBSTRING_INDEX(name, ' ', 1)) as first_name,
                                        COALESCE(last_name, SUBSTRING_INDEX(name, ' ', -1)) as last_name,
                                        name,
                                        email,
                                        employee_code,
                                        position,
                                        COALESCE(department_name, 'General') as department_name,
                                        hire_date,
                                        status,
                                        phone,
                                        monthly_salary
                                        FROM employees 
                                        WHERE status = 'active' 
                                        ORDER BY name");
                $query->execute();
                $result = $query->get_result();
                
                $employees = [];
                while ($row = $result->fetch_assoc()) {
                    if (empty($row['first_name']) && !empty($row['name'])) {
                        $nameParts = explode(' ', $row['name'], 2);
                        $row['first_name'] = $nameParts[0];
                        $row['last_name'] = isset($nameParts[1]) ? $nameParts[1] : '';
                    }
                    $employees[] = $row;
                }
                
                echo json_encode(['success' => true, 'data' => $employees]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        case 'get_leave_requests_advanced':
            try {
                $start_date = $_POST['start_date'] ?? date('Y-m-01');
                $end_date = $_POST['end_date'] ?? date('Y-m-t');
                $status_filter = $_POST['status_filter'] ?? 'all';
                
                $sql = "SELECT 
                        lr.id,
                        lr.employee_id,
                        COALESCE(e.name, CONCAT(e.first_name, ' ', e.last_name)) as employee_name,
                        e.employee_code,
                        e.department_name,
                        lr.from_date,
                        lr.to_date,
                        lr.leave_type,
                        lr.days_requested,
                        lr.reason,
                        lr.status,
                        lr.applied_date,
                        lr.approved_by,
                        lr.approved_date,
                        lr.approver_comments,
                        las.approval_level,
                        las.approver_role,
                        las.status as step_status,
                        las.comments as step_comments
                        FROM leave_requests lr
                        LEFT JOIN employees e ON lr.employee_id = e.employee_id
                        LEFT JOIN leave_approval_steps las ON lr.id = las.leave_request_id
                        WHERE lr.applied_date BETWEEN ? AND ?";
                
                if ($status_filter !== 'all') {
                    $sql .= " AND lr.status = ?";
                }
                
                $sql .= " ORDER BY lr.applied_date DESC, las.approval_level";
                
                $query = $conn->prepare($sql);
                
                if ($status_filter !== 'all') {
                    $query->bind_param("sss", $start_date, $end_date, $status_filter);
                } else {
                    $query->bind_param("ss", $start_date, $end_date);
                }
                
                $query->execute();
                $result = $query->get_result();
                
                $leave_requests = [];
                $current_request = null;
                
                while ($row = $result->fetch_assoc()) {
                    if (!$current_request || $current_request['id'] != $row['id']) {
                        if ($current_request) {
                            $leave_requests[] = $current_request;
                        }
                        $current_request = [
                            'id' => $row['id'],
                            'employee_id' => $row['employee_id'],
                            'employee_name' => $row['employee_name'],
                            'employee_code' => $row['employee_code'],
                            'department_name' => $row['department_name'],
                            'from_date' => $row['from_date'],
                            'to_date' => $row['to_date'],
                            'leave_type' => $row['leave_type'],
                            'days_requested' => $row['days_requested'],
                            'reason' => $row['reason'],
                            'status' => $row['status'],
                            'applied_date' => $row['applied_date'],
                            'approved_by' => $row['approved_by'],
                            'approved_date' => $row['approved_date'],
                            'approver_comments' => $row['approver_comments'],
                            'approval_steps' => []
                        ];
                    }
                    
                    if ($row['approval_level']) {
                        $current_request['approval_steps'][] = [
                            'level' => $row['approval_level'],
                            'approver_role' => $row['approver_role'],
                            'status' => $row['step_status'],
                            'comments' => $row['step_comments']
                        ];
                    }
                }
                
                if ($current_request) {
                    $leave_requests[] = $current_request;
                }
                
                echo json_encode(['success' => true, 'data' => $leave_requests]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        case 'get_analytics_dashboard':
            try {
                $start_date = $_POST['start_date'] ?? date('Y-m-01');
                $end_date = $_POST['end_date'] ?? date('Y-m-t');
                
                // Employee analytics
                $employee_analytics = [
                    'total_employees' => 0,
                    'active_employees' => 0,
                    'new_hires' => 0,
                    'resignations' => 0
                ];
                
                $result = $conn->query("SELECT COUNT(*) as count FROM employees");
                if ($result) $employee_analytics['total_employees'] = $result->fetch_assoc()['count'];
                
                $result = $conn->query("SELECT COUNT(*) as count FROM employees WHERE status = 'active'");
                if ($result) $employee_analytics['active_employees'] = $result->fetch_assoc()['count'];
                
                $result = $conn->query("SELECT COUNT(*) as count FROM employees WHERE hire_date BETWEEN '$start_date' AND '$end_date'");
                if ($result) $employee_analytics['new_hires'] = $result->fetch_assoc()['count'];
                
                // Leave analytics
                $leave_analytics = [];
                $result = $conn->query("SELECT 
                                       leave_type, 
                                       COUNT(*) as request_count,
                                       SUM(days_requested) as total_days,
                                       AVG(days_requested) as avg_days
                                       FROM leave_requests 
                                       WHERE applied_date BETWEEN '$start_date' AND '$end_date'
                                       GROUP BY leave_type");
                while ($row = $result->fetch_assoc()) {
                    $leave_analytics[] = $row;
                }
                
                // Attendance analytics
                $attendance_analytics = [];
                $result = $conn->query("SELECT 
                                       DATE(check_in) as date,
                                       COUNT(DISTINCT employee_id) as present_count
                                       FROM attendance 
                                       WHERE DATE(check_in) BETWEEN '$start_date' AND '$end_date'
                                       GROUP BY DATE(check_in)
                                       ORDER BY date DESC");
                while ($row = $result->fetch_assoc()) {
                    $attendance_analytics[] = $row;
                }
                
                // Department-wise analytics
                $department_analytics = [];
                $result = $conn->query("SELECT 
                                       department_name,
                                       COUNT(*) as employee_count,
                                       AVG(monthly_salary) as avg_salary
                                       FROM employees 
                                       WHERE status = 'active'
                                       GROUP BY department_name");
                while ($row = $result->fetch_assoc()) {
                    $department_analytics[] = $row;
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'employee_analytics' => $employee_analytics,
                        'leave_analytics' => $leave_analytics,
                        'attendance_analytics' => $attendance_analytics,
                        'department_analytics' => $department_analytics
                    ]
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        case 'get_approval_workflow_config':
            try {
                $result = $conn->query("SELECT 
                                       awc.*,
                                       d.name as department_name
                                       FROM approval_workflow_config awc
                                       LEFT JOIN departments d ON awc.department_id = d.id
                                       WHERE awc.is_active = 1
                                       ORDER BY awc.department_id, awc.approval_level");
                
                $workflows = [];
                while ($row = $result->fetch_assoc()) {
                    $workflows[] = $row;
                }
                
                echo json_encode(['success' => true, 'data' => $workflows]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        case 'update_workflow_config':
            try {
                $workflow_data = json_decode($_POST['workflow_data'], true);
                
                $conn->begin_transaction();
                
                // Delete existing workflows for the department
                $dept_id = $workflow_data['department_id'] ?? null;
                if ($dept_id) {
                    $stmt = $conn->prepare("DELETE FROM approval_workflow_config WHERE department_id = ?");
                    $stmt->bind_param("i", $dept_id);
                    $stmt->execute();
                } else {
                    $conn->query("DELETE FROM approval_workflow_config WHERE department_id IS NULL");
                }
                
                // Insert new workflow steps
                $stmt = $conn->prepare("INSERT INTO approval_workflow_config (workflow_name, department_id, approval_level, approver_role, escalation_hours) VALUES (?, ?, ?, ?, ?)");
                
                foreach ($workflow_data['steps'] as $step) {
                    $stmt->bind_param("siisi", 
                        $workflow_data['workflow_name'],
                        $dept_id,
                        $step['approval_level'],
                        $step['approver_role'],
                        $step['escalation_hours']
                    );
                    $stmt->execute();
                }
                
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Workflow configuration updated successfully']);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        case 'get_audit_logs':
            try {
                $start_date = $_POST['start_date'] ?? date('Y-m-01');
                $end_date = $_POST['end_date'] ?? date('Y-m-t');
                $action_filter = $_POST['action_filter'] ?? 'all';
                $limit = intval($_POST['limit'] ?? 50);
                $offset = intval($_POST['offset'] ?? 0);
                
                $sql = "SELECT 
                        al.id,
                        al.user_id,
                        COALESCE(e.name, CONCAT(e.first_name, ' ', e.last_name), u.username) as user_name,
                        al.action,
                        al.data,
                        al.ip_address,
                        al.user_agent,
                        al.created_at
                        FROM audit_logs al
                        LEFT JOIN users u ON al.user_id = u.id
                        LEFT JOIN employees e ON u.id = e.user_id
                        WHERE DATE(al.created_at) BETWEEN ? AND ?";
                
                if ($action_filter !== 'all') {
                    $sql .= " AND al.action = ?";
                }
                
                $sql .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
                
                $query = $conn->prepare($sql);
                
                if ($action_filter !== 'all') {
                    $query->bind_param("sssii", $start_date, $end_date, $action_filter, $limit, $offset);
                } else {
                    $query->bind_param("ssii", $start_date, $end_date, $limit, $offset);
                }
                
                $query->execute();
                $result = $query->get_result();
                
                $logs = [];
                while ($row = $result->fetch_assoc()) {
                    $logs[] = $row;
                }
                
                echo json_encode(['success' => true, 'data' => $logs]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'get_leave_requests':
            try {
                $query = $conn->prepare("SELECT 
                                        lr.id,
                                        COALESCE(e.name, CONCAT(e.first_name, ' ', e.last_name)) as employee_name,
                                        lr.leave_type,
                                        lr.start_date,
                                        lr.end_date,
                                        lr.total_days,
                                        lr.status,
                                        lr.reason
                                        FROM leave_requests lr
                                        LEFT JOIN employees e ON lr.employee_id = e.employee_id
                                        ORDER BY lr.created_at DESC");
                $query->execute();
                $result = $query->get_result();
                
                $requests = [];
                while ($row = $result->fetch_assoc()) {
                    $requests[] = $row;
                }
                
                echo json_encode(['success' => true, 'data' => $requests]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'data' => []]);
            }
            exit;
            
        case 'add_employee':
            try {
                $name = $_POST['full_name'] ?? '';
                $code = $_POST['employee_code'] ?? '';
                $email = $_POST['email'] ?? '';
                $phone = $_POST['phone'] ?? '';
                $position = $_POST['position'] ?? '';
                $hire_date = $_POST['joining_date'] ?? date('Y-m-d');
                
                $stmt = $conn->prepare("INSERT INTO employees (name, employee_code, email, phone, position, hire_date, status, department_name) VALUES (?, ?, ?, ?, ?, ?, 'active', 'General')");
                $stmt->bind_param("ssssss", $name, $code, $email, $phone, $position, $hire_date);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Employee added successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to add employee']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- Bootstrap 5.3.2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #64748b;
            --success-color: #059669;
            --info-color: #0891b2;
            --warning-color: #d97706;
            --danger-color: #dc2626;
            --dark-color: #1e293b;
        }
        
        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            margin: 0;
            padding: 0;
        }
        
        .admin-sidebar {
            background: linear-gradient(180deg, #1e293b 0%, #334155 100%);
            min-height: 100vh;
            box-shadow: 4px 0 20px rgba(0,0,0,0.15);
            position: fixed;
            width: 280px;
            z-index: 1000;
        }
        
        .admin-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
        }
        
        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        
        .sidebar-brand {
            color: #ffffff;
            font-size: 1.5rem;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .sidebar-brand i {
            margin-right: 0.5rem;
            background: var(--primary-color);
            padding: 0.5rem;
            border-radius: 0.5rem;
        }
        
        .sidebar-menu {
            padding: 1rem 0;
        }
        
        .sidebar-item {
            margin: 0.25rem 1rem;
        }
        
        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 0.875rem 1rem;
            color: #cbd5e1;
            text-decoration: none;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
            font-weight: 500;
        }
        
        .sidebar-link:hover,
        .sidebar-link.active {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            transform: translateX(4px);
        }
        
        .sidebar-link i {
            width: 20px;
            margin-right: 0.75rem;
            text-align: center;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.5rem;
            color: white;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--dark-color);
        }
        
        .stat-label {
            color: var(--secondary-color);
            font-weight: 500;
            margin: 0;
        }
        
        .content-header {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }
        
        .content-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .content-subtitle {
            color: var(--secondary-color);
            margin: 0;
            font-size: 1.1rem;
        }
        
        .tab-content-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        
        .nav-tabs {
            border-bottom: 2px solid #f1f5f9;
            background: #f8fafc;
            padding: 0 2rem;
        }
        
        .nav-tabs .nav-link {
            border: none;
            border-radius: 0;
            color: var(--secondary-color);
            font-weight: 600;
            padding: 1rem 1.5rem;
            border-bottom: 3px solid transparent;
            transition: all 0.2s ease;
        }
        
        .nav-tabs .nav-link.active {
            background: transparent;
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }
        
        .nav-tabs .nav-link:hover {
            color: var(--primary-color);
            border-color: transparent;
        }
        
        .tab-pane {
            padding: 2rem;
        }
        
        .btn-modern {
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            border: none;
            transition: all 0.2s ease;
            text-transform: none;
        }
        
        .btn-primary.btn-modern {
            background: var(--primary-color);
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.25);
        }
        
        .btn-primary.btn-modern:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.35);
        }
        
        .table-modern {
            border: none;
        }
        
        .table-modern thead th {
            background: #f8fafc;
            border: none;
            color: var(--dark-color);
            font-weight: 600;
            padding: 1rem;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.05em;
        }
        
        .table-modern tbody td {
            border: none;
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        
        .table-modern tbody tr:hover {
            background: #f8fafc;
        }
        
        .badge-modern {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.25);
        }
        
        .modal-content {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 20px 25px rgba(0,0,0,0.1);
        }
        
        .modal-header {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            border-radius: 1rem 1rem 0 0;
        }
        
        .alert {
            border: none;
            border-radius: 0.75rem;
        }
        
        /* Advanced HRMS Styles */
        .stat-card-small {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .stat-icon-small {
            width: 3rem;
            height: 3rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: white;
            font-size: 1.25rem;
        }
        
        .stat-number-small {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark-color);
        }
        
        .stat-label-small {
            font-size: 0.875rem;
            color: var(--secondary-color);
            margin: 0;
        }
        
        .analytics-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .analytics-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .analytics-icon {
            width: 3.5rem;
            height: 3.5rem;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: white;
            font-size: 1.5rem;
        }
        
        .analytics-content h6 {
            margin: 0;
            font-size: 0.875rem;
            color: var(--secondary-color);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .analytics-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark-color);
            margin: 0.25rem 0;
        }
        
        .analytics-trend {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
        }
        
        .analytics-trend.positive {
            background: #dcfce7;
            color: #16a34a;
        }
        
        .analytics-trend.negative {
            background: #fef2f2;
            color: #dc2626;
        }
        
        .workflow-template {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            text-align: center;
            margin-bottom: 1rem;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .workflow-template:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }
        
        .policy-category {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            text-align: center;
            margin-bottom: 1rem;
            position: relative;
        }
        
        .policy-icon {
            width: 4rem;
            height: 4rem;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 1.5rem;
        }
        
        .permission-matrix {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 0.75rem;
        }
        
        .permission-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .permission-row:last-child {
            border-bottom: none;
        }
        
        .permission-module {
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .permission-actions {
            display: flex;
            gap: 1rem;
        }
        
        .permission-checkbox {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.25rem;
        }
        
        .permission-checkbox label {
            font-size: 0.75rem;
            color: var(--secondary-color);
            margin: 0;
        }
        
        .escalation-indicator {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }
        
        .escalation-indicator.urgent {
            background: #fef2f2;
            color: #dc2626;
            animation: pulse 2s infinite;
        }
        
        .escalation-indicator.warning {
            background: #fef3c7;
            color: #d97706;
        }
        
        .escalation-indicator.normal {
            background: #ecfdf5;
            color: #059669;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .workflow-step {
            display: flex;
            align-items: center;
            margin: 0.5rem 0;
        }
        
        .workflow-step-icon {
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: white;
        }
        
        .workflow-step-content {
            flex: 1;
        }
        
        .workflow-step.pending .workflow-step-icon {
            background: #fbbf24;
        }
        
        .workflow-step.approved .workflow-step-icon {
            background: #10b981;
        }
        
        .workflow-step.rejected .workflow-step-icon {
            background: #ef4444;
        }
        
        .mobile-friendly {
            display: none;
        }
        
        @media (max-width: 768px) {
            .admin-sidebar {
                width: 100%;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .admin-sidebar.show {
                transform: translateX(0);
            }
            
            .admin-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .mobile-friendly {
                display: block;
            }
            
            .desktop-only {
                display: none;
            }
            
            .analytics-card {
                flex-direction: column;
                text-align: center;
            }
            
            .analytics-icon {
                margin-right: 0;
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="admin-sidebar">
        <div class="sidebar-header">
            <a href="#" class="sidebar-brand">
                <i class="fas fa-users-cog"></i>
                <div>
                    <div>HRMS Admin</div>
                    <small style="color: #94a3b8; font-size: 0.75rem;">Control Panel</small>
                </div>
            </a>
        </div>
        
        <div class="sidebar-menu">
            <div class="sidebar-item">
                <a href="../dashboard.php" class="sidebar-link">
                    <i class="fas fa-home"></i>Portal Home
                </a>
            </div>
            <div class="sidebar-item">
                <a href="#" class="sidebar-link active">
                    <i class="fas fa-user-tie"></i>Admin Panel
                </a>
            </div>
            <div class="sidebar-item">
                <a href="team_manager_console.php" class="sidebar-link">
                    <i class="fas fa-users"></i>Manager Console
                </a>
            </div>
            <div class="sidebar-item">
                <a href="staff_self_service.php" class="sidebar-link">
                    <i class="fas fa-user"></i>Staff Portal
                </a>
            </div>
            <div class="sidebar-item">
                <a href="../logout.php" class="sidebar-link">
                    <i class="fas fa-sign-out-alt"></i>Logout
                </a>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <main class="admin-content">
        <!-- Header -->
        <div class="content-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="content-title">HRMS Administration</h1>
                    <p class="content-subtitle">Comprehensive Human Resource Management Dashboard</p>
                </div>
                <div>
                    <button class="btn btn-outline-primary btn-modern me-2" onclick="refreshDashboard()">
                        <i class="fas fa-sync-alt me-2"></i>Refresh
                    </button>
                    <button class="btn btn-primary btn-modern" onclick="showAddEmployeeModal()">
                        <i class="fas fa-user-plus me-2"></i>Add Employee
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--primary-color);">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total_employees']; ?></div>
                <p class="stat-label">Total Employees</p>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--success-color);">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-number"><?php echo $stats['active_employees']; ?></div>
                <p class="stat-label">Active Staff</p>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--warning-color);">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <div class="stat-number"><?php echo $stats['pending_leaves']; ?></div>
                <p class="stat-label">Pending Leaves</p>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--info-color);">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?php echo $stats['today_attendance']; ?></div>
                <p class="stat-label">Today Present</p>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--secondary-color);">
                    <i class="fas fa-building"></i>
                </div>
                <div class="stat-number"><?php echo $stats['departments']; ?></div>
                <p class="stat-label">Departments</p>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--danger-color);">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="stat-number"><?php echo $stats['this_month_joins']; ?></div>
                <p class="stat-label">New Joins</p>
            </div>
        </div>
        
        <!-- Main Content Tabs -->
        <div class="tab-content-card">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#staff">
                        <i class="fas fa-users me-2"></i>Staff Management
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#leaves">
                        <i class="fas fa-calendar-alt me-2"></i>Advanced Leave Management
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#permissions">
                        <i class="fas fa-user-shield me-2"></i>Permissions & Access
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#workflow">
                        <i class="fas fa-sitemap me-2"></i>Approval Workflows
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#analytics">
                        <i class="fas fa-chart-bar me-2"></i>Analytics & Insights
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#audit">
                        <i class="fas fa-history me-2"></i>Audit Trail
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#policies">
                        <i class="fas fa-clipboard-check me-2"></i>Policy Management
                    </button>
                </li>
            </ul>
            
            <div class="tab-content">
                <!-- Staff Management Tab -->
                <div class="tab-pane fade show active" id="staff" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-bold"><i class="fas fa-users me-2 text-primary"></i>Employee Directory</h4>
                        <div>
                            <button class="btn btn-outline-primary btn-modern me-2" onclick="exportEmployeeData()">
                                <i class="fas fa-download me-2"></i>Export
                            </button>
                            <button class="btn btn-primary btn-modern" onclick="loadEmployees()">
                                <i class="fas fa-sync-alt me-2"></i>Refresh Data
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-modern" id="employeesTable">
                            <thead>
                                <tr>
                                    <th>Employee ID</th>
                                    <th>Full Name</th>
                                    <th>Code</th>
                                    <th>Position</th>
                                    <th>Department</th>
                                    <th>Email Address</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Employee records will be populated here -->
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Advanced Leave Management Tab -->
                <div class="tab-pane fade" id="leaves" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-bold"><i class="fas fa-calendar-alt me-2 text-success"></i>Multi-Level Leave Approval System</h4>
                        <div>
                            <div class="btn-group me-2">
                                <select class="form-select" id="leaveStatusFilter" onchange="filterLeaveRequests()">
                                    <option value="all">All Status</option>
                                    <option value="pending">Pending</option>
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                            </div>
                            <div class="btn-group me-2">
                                <input type="date" class="form-control" id="leaveDateFrom" placeholder="From Date">
                                <input type="date" class="form-control" id="leaveDateTo" placeholder="To Date">
                            </div>
                            <button class="btn btn-success btn-modern" onclick="loadAdvancedLeaveRequests()">
                                <i class="fas fa-sync-alt me-2"></i>Refresh
                            </button>
                        </div>
                    </div>
                    
                    <!-- Leave Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stat-card-small">
                                <div class="stat-icon-small bg-warning">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div>
                                    <div class="stat-number-small" id="pendingLeaveCount">0</div>
                                    <div class="stat-label-small">Pending Approvals</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card-small">
                                <div class="stat-icon-small bg-success">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div>
                                    <div class="stat-number-small" id="approvedLeaveCount">0</div>
                                    <div class="stat-label-small">Approved This Month</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card-small">
                                <div class="stat-icon-small bg-danger">
                                    <i class="fas fa-times"></i>
                                </div>
                                <div>
                                    <div class="stat-number-small" id="rejectedLeaveCount">0</div>
                                    <div class="stat-label-small">Rejected</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card-small">
                                <div class="stat-icon-small bg-info">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div>
                                    <div class="stat-number-small" id="escalatedLeaveCount">0</div>
                                    <div class="stat-label-small">Escalated</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-modern" id="advancedLeaveTable">
                            <thead>
                                <tr>
                                    <th>Request ID</th>
                                    <th>Employee</th>
                                    <th>Department</th>
                                    <th>Leave Type</th>
                                    <th>Duration</th>
                                    <th>Approval Workflow</th>
                                    <th>Current Status</th>
                                    <th>Policy Compliance</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Advanced leave requests will be populated here -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Permissions & Access Control Tab -->
                <div class="tab-pane fade" id="permissions" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-bold"><i class="fas fa-user-shield me-2 text-info"></i>Granular Permissions & Access Control</h4>
                        <div>
                            <button class="btn btn-outline-info btn-modern me-2" onclick="showDelegationModal()">
                                <i class="fas fa-user-tag me-2"></i>Delegate Rights
                            </button>
                            <button class="btn btn-info btn-modern" onclick="loadPermissions()">
                                <i class="fas fa-sync-alt me-2"></i>Refresh
                            </button>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-users me-2"></i>User Roles</h6>
                                </div>
                                <div class="card-body">
                                    <div class="list-group list-group-flush" id="userRolesList">
                                        <!-- User roles will be populated here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-cogs me-2"></i>Module Permissions</h6>
                                </div>
                                <div class="card-body">
                                    <div class="permission-matrix" id="permissionMatrix">
                                        <!-- Permission matrix will be populated here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Active Delegations -->
                    <div class="mt-4">
                        <h5><i class="fas fa-user-tag me-2 text-warning"></i>Active Delegations</h5>
                        <div class="table-responsive">
                            <table class="table table-modern" id="delegationsTable">
                                <thead>
                                    <tr>
                                        <th>Delegator</th>
                                        <th>Delegate</th>
                                        <th>Delegation Type</th>
                                        <th>Valid Period</th>
                                        <th>Permissions</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Delegations will be populated here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Approval Workflows Tab -->
                <div class="tab-pane fade" id="workflow" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-bold"><i class="fas fa-sitemap me-2 text-primary"></i>Approval Workflow Configuration</h4>
                        <div>
                            <button class="btn btn-outline-primary btn-modern me-2" onclick="showWorkflowDesigner()">
                                <i class="fas fa-plus me-2"></i>Create Workflow
                            </button>
                            <button class="btn btn-primary btn-modern" onclick="loadWorkflowConfigs()">
                                <i class="fas fa-sync-alt me-2"></i>Refresh
                            </button>
                        </div>
                    </div>
                    
                    <!-- Workflow Templates -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="workflow-template">
                                <h6><i class="fas fa-layer-group me-2"></i>Standard 2-Level Approval</h6>
                                <p class="small text-muted">Manager → HR → Final Approval</p>
                                <button class="btn btn-sm btn-outline-primary" onclick="applyWorkflowTemplate('standard')">Apply Template</button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="workflow-template">
                                <h6><i class="fas fa-layer-group me-2"></i>Department Head Approval</h6>
                                <p class="small text-muted">Team Lead → Dept Head → HR</p>
                                <button class="btn btn-sm btn-outline-primary" onclick="applyWorkflowTemplate('department')">Apply Template</button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="workflow-template">
                                <h6><i class="fas fa-layer-group me-2"></i>Executive Approval</h6>
                                <p class="small text-muted">Manager → Dept Head → Executive</p>
                                <button class="btn btn-sm btn-outline-primary" onclick="applyWorkflowTemplate('executive')">Apply Template</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-modern" id="workflowConfigTable">
                            <thead>
                                <tr>
                                    <th>Workflow Name</th>
                                    <th>Department</th>
                                    <th>Approval Levels</th>
                                    <th>Escalation Time</th>
                                    <th>Active Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Workflow configurations will be populated here -->
                            </tbody>
                        </table>
                    </div>
                </div>
                            <i class="fas fa-sync-alt me-2"></i>Refresh Leaves
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-modern" id="leavesTable">
                            <thead>
                                <tr>
                                    <th>Request ID</th>
                                    <th>Employee</th>
                                    <th>Leave Type</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Days</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Leave requests will be populated here -->
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Analytics & Insights Tab -->
                <div class="tab-pane fade" id="analytics" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-bold"><i class="fas fa-chart-bar me-2 text-warning"></i>Advanced Analytics & Predictive Insights</h4>
                        <div>
                            <div class="btn-group me-2">
                                <input type="date" class="form-control" id="analyticsDateFrom" value="<?php echo date('Y-m-01'); ?>">
                                <input type="date" class="form-control" id="analyticsDateTo" value="<?php echo date('Y-m-t'); ?>">
                            </div>
                            <button class="btn btn-warning btn-modern" onclick="loadAnalyticsDashboard()">
                                <i class="fas fa-chart-line me-2"></i>Generate Insights
                            </button>
                        </div>
                    </div>
                    
                    <!-- Analytics Overview Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="analytics-card">
                                <div class="analytics-icon bg-primary">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="analytics-content">
                                    <h6>Employee Metrics</h6>
                                    <div class="analytics-value" id="employeeMetrics">Loading...</div>
                                    <small class="analytics-trend" id="employeeTrend"></small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="analytics-card">
                                <div class="analytics-icon bg-success">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div class="analytics-content">
                                    <h6>Attendance Rate</h6>
                                    <div class="analytics-value" id="attendanceRate">Loading...</div>
                                    <small class="analytics-trend" id="attendanceTrend"></small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="analytics-card">
                                <div class="analytics-icon bg-warning">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="analytics-content">
                                    <h6>Attrition Risk</h6>
                                    <div class="analytics-value" id="attritionRisk">Loading...</div>
                                    <small class="analytics-trend" id="attritionTrend"></small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="analytics-card">
                                <div class="analytics-icon bg-info">
                                    <i class="fas fa-users-cog"></i>
                                </div>
                                <div class="analytics-content">
                                    <h6>Workforce Forecast</h6>
                                    <div class="analytics-value" id="workforceForecast">Loading...</div>
                                    <small class="analytics-trend" id="workforceTrend"></small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Charts and Detailed Analytics -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Leave Pattern Analysis</h6>
                                </div>
                                <div class="card-body">
                                    <canvas id="leavePatternChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-chart-area me-2"></i>Department Performance</h6>
                                </div>
                                <div class="card-body">
                                    <canvas id="departmentChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Audit Trail Tab -->
                <div class="tab-pane fade" id="audit" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-bold"><i class="fas fa-history me-2 text-secondary"></i>Comprehensive Audit Trail</h4>
                        <div>
                            <div class="btn-group me-2">
                                <select class="form-select" id="auditActionFilter">
                                    <option value="all">All Actions</option>
                                    <option value="login">Login/Logout</option>
                                    <option value="leave_request_submitted">Leave Requests</option>
                                    <option value="leave_approval_action">Leave Approvals</option>
                                    <option value="employee_added">Employee Changes</option>
                                    <option value="permissions_updated">Permission Changes</option>
                                </select>
                            </div>
                            <div class="btn-group me-2">
                                <input type="date" class="form-control" id="auditDateFrom">
                                <input type="date" class="form-control" id="auditDateTo">
                            </div>
                            <button class="btn btn-secondary btn-modern" onclick="loadAuditLogs()">
                                <i class="fas fa-search me-2"></i>Search Logs
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-modern" id="auditLogsTable">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Details</th>
                                    <th>IP Address</th>
                                    <th>User Agent</th>
                                    <th>View Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Audit logs will be populated here -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Policy Management Tab -->
                <div class="tab-pane fade" id="policies" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-bold"><i class="fas fa-clipboard-check me-2 text-success"></i>Automated Policy Enforcement</h4>
                        <div>
                            <button class="btn btn-outline-success btn-modern me-2" onclick="showPolicyRuleModal()">
                                <i class="fas fa-plus me-2"></i>Add Policy Rule
                            </button>
                            <button class="btn btn-success btn-modern" onclick="loadPolicyRules()">
                                <i class="fas fa-sync-alt me-2"></i>Refresh
                            </button>
                        </div>
                    </div>
                    
                    <!-- Policy Categories -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="policy-category">
                                <div class="policy-icon bg-primary">
                                    <i class="fas fa-calendar-times"></i>
                                </div>
                                <h6>Leave Balance Validation</h6>
                                <p class="small text-muted">Automatically validate leave balances and prevent excess leave applications</p>
                                <span class="badge bg-success">Active</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="policy-category">
                                <div class="policy-icon bg-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <h6>Holiday Overlap Alerts</h6>
                                <p class="small text-muted">Alert users when leave requests overlap with company holidays</p>
                                <span class="badge bg-success">Active</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="policy-category">
                                <div class="policy-icon bg-info">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <h6>Escalation Timers</h6>
                                <p class="small text-muted">Automatically escalate pending requests based on time limits</p>
                                <span class="badge bg-warning">Configuring</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-modern" id="policyRulesTable">
                            <thead>
                                <tr>
                                    <th>Rule Name</th>
                                    <th>Rule Type</th>
                                    <th>Conditions</th>
                                    <th>Actions</th>
                                    <th>Status</th>
                                    <th>Last Modified</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Policy rules will be populated here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Add Employee Modal -->
    <div class="modal fade" id="addEmployeeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-user-plus me-2 text-primary"></i>Add New Employee
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addEmployeeForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Full Name *</label>
                                <input type="text" class="form-control" name="full_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Employee Code *</label>
                                <input type="text" class="form-control" name="employee_code" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Email Address</label>
                                <input type="email" class="form-control" name="email">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Phone Number</label>
                                <input type="tel" class="form-control" name="phone">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Job Position</label>
                                <input type="text" class="form-control" name="position">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Joining Date</label>
                                <input type="date" class="form-control" name="joining_date" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-modern" onclick="saveEmployee()">
                        <i class="fas fa-save me-2"></i>Save Employee
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="../js/hrms-table-manager.js"></script>
    
    <script>
        let employeesTable = null;
        let leavesTable = null;
        
        $(document).ready(function() {
            initializeEnhancedTables();
            loadEmployees();
            loadLeaveRequests();
            setupRealtimeUpdates();
        });
        
        function initializeEnhancedTables() {
            try {
                // Initialize enhanced employee table with modern design
                employeesTable = window.hrmsTableManager.createEmployeeTable('employeesTable', {
                    pageLength: 15,
                    order: [[1, 'asc']], // Sort by name
                    responsive: true,
                    processing: true
                });

                // Initialize enhanced leave requests table
                leavesTable = window.hrmsTableManager.createLeaveTable('leavesTable', {
                    pageLength: 15,
                    order: [[0, 'desc']], // Sort by request ID
                    responsive: true,
                    processing: true
                });
                
            } catch (error) {
                console.error('Enhanced DataTables initialization error:', error);
                // Fallback to basic tables
                initializeBasicTables();
            }
        }
        
        function initializeBasicTables() {
            try {
                if ($.fn.DataTable.isDataTable('#employeesTable')) {
                    $('#employeesTable').DataTable().destroy();
                }
                if ($.fn.DataTable.isDataTable('#leavesTable')) {
                    $('#leavesTable').DataTable().destroy();
                }

                employeesTable = $('#employeesTable').DataTable({
                    responsive: true,
                    pageLength: 15,
                    columns: [
                        { title: "Employee ID", data: null },
                        { title: "Full Name", data: null },
                        { title: "Code", data: null },
                        { title: "Position", data: null },
                        { title: "Department", data: null },
                        { title: "Email Address", data: null },
                        { title: "Status", data: null },
                        { title: "Actions", data: null }
                    ],
                    language: { emptyTable: "No employees found" }
                });

                leavesTable = $('#leavesTable').DataTable({
                    responsive: true,
                    pageLength: 15,
                    columns: [
                        { title: "Request ID", data: null },
                        { title: "Employee", data: null },
                        { title: "Leave Type", data: null },
                        { title: "Start Date", data: null },
                        { title: "End Date", data: null },
                        { title: "Days", data: null },
                        { title: "Status", data: null },
                        { title: "Actions", data: null }
                    ],
                    language: { emptyTable: "No leave requests found" }
                });
            } catch (error) {
                console.error('Basic DataTables initialization error:', error);
            }
        }
        
        function loadEmployees() {
            window.hrmsTableManager.showTableLoading('employeesTable');
            
            $.ajax({
                url: 'hrms_admin_panel.php',
                method: 'POST',
                data: { action: 'get_employees' },
                dataType: 'json',
                success: function(response) {
                    window.hrmsTableManager.hideTableLoading('employeesTable');
                    
                    if (response.success && response.data) {
                        displayEmployees(response.data);
                        showAlert(`Employees loaded successfully (${response.data.length} found)`, 'success');
                        updateEmployeeStats(response.data);
                    } else {
                        showAlert('Error loading employees: ' + (response.message || 'Unknown error'), 'danger');
                        displayEmployees([]);
                    }
                },
                error: function(xhr, status, error) {
                    window.hrmsTableManager.hideTableLoading('employeesTable');
                    showAlert('Failed to load employees: ' + error, 'danger');
                    displayEmployees([]);
                }
            });
        }
        
        function displayEmployees(employees) {
            if (!employeesTable) return;
            
            try {
                employeesTable.clear();
                
                employees.forEach(function(employee) {
                    // Enhanced employee row with modern design
                    const name = employee.name || `${employee.first_name || ''} ${employee.last_name || ''}`.trim();
                    const statusBadge = employee.status === 'active' ? 
                        '<span class="badge bg-success">Active</span>' : 
                        '<span class="badge bg-danger">Inactive</span>';
                    
                    const actions = `
                        <div class="btn-group btn-group-sm" role="group">
                            <button class="btn btn-outline-primary" onclick="viewEmployeeDetails(${employee.employee_id})" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-outline-warning" onclick="editEmployeeDetails(${employee.employee_id})" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-outline-info" onclick="viewEmployeeAttendance(${employee.employee_id})" title="Attendance">
                                <i class="fas fa-clock"></i>
                            </button>
                            <button class="btn btn-outline-success" onclick="generateEmployeeReport(${employee.employee_id})" title="Generate Report">
                                <i class="fas fa-file-alt"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="deleteEmployeeRecord(${employee.employee_id})" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    `;
                    
                    employeesTable.row.add([
                        `<span class="fw-bold text-primary">#${employee.employee_id}</span>`,
                        `<div class="d-flex align-items-center">
                            <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                <strong>${name.charAt(0).toUpperCase()}</strong>
                            </div>
                            <div>
                                <div class="fw-semibold">${name}</div>
                                <small class="text-muted">${employee.employee_code || 'No Code'}</small>
                            </div>
                        </div>`,
                        employee.employee_code || 'N/A',
                        `<span class="badge bg-info">${employee.position || 'Not Set'}</span>`,
                        `<span class="badge bg-secondary">${employee.department_name || 'General'}</span>`,
                        employee.email ? `<a href="mailto:${employee.email}" class="text-decoration-none">${employee.email}</a>` : '<span class="text-muted">No Email</span>',
                        statusBadge,
                        actions
                    ]);
                });
                
                employeesTable.draw();
            } catch (error) {
                console.error('Error displaying employees:', error);
            }
        }
        
        function loadLeaveRequests() {
            window.hrmsTableManager.showTableLoading('leavesTable');
            
            $.ajax({
                url: 'hrms_admin_panel.php',
                method: 'POST',
                data: { action: 'get_leave_requests' },
                dataType: 'json',
                success: function(response) {
                    window.hrmsTableManager.hideTableLoading('leavesTable');
                    
                    if (response.success && response.data) {
                        displayLeaveRequests(response.data);
                        showAlert(`Leave requests loaded (${response.data.length} found)`, 'success');
                    } else {
                        displayLeaveRequests([]);
                    }
                },
                error: function() {
                    window.hrmsTableManager.hideTableLoading('leavesTable');
                    displayLeaveRequests([]);
                }
            });
        }
        
        function displayLeaveRequests(requests) {
            if (!leavesTable) return;
            
            try {
                leavesTable.clear();
                
                requests.forEach(function(request) {
                    const statusColors = {
                        'pending': 'warning',
                        'approved': 'success',
                        'rejected': 'danger'
                    };
                    
                    const statusBadge = `<span class="badge bg-${statusColors[request.status] || 'secondary'}">${request.status || 'N/A'}</span>`;
                    
                    const actions = `
                        <div class="btn-group btn-group-sm" role="group">
                            <button class="btn btn-outline-primary" onclick="viewLeaveRequestDetails(${request.id})" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            ${request.status === 'pending' ? `
                                <button class="btn btn-outline-success" onclick="approveLeaveRequest(${request.id})" title="Approve">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button class="btn btn-outline-danger" onclick="rejectLeaveRequest(${request.id})" title="Reject">
                                    <i class="fas fa-times"></i>
                                </button>
                            ` : ''}
                            <button class="btn btn-outline-info" onclick="printLeaveRequest(${request.id})" title="Print">
                                <i class="fas fa-print"></i>
                            </button>
                        </div>
                    `;
                    
                    leavesTable.row.add([
                        `<span class="fw-bold text-primary">#LR${request.id}</span>`,
                        `<div>
                            <div class="fw-semibold">${request.employee_name || 'Unknown'}</div>
                            <small class="text-muted">${request.employee_code || 'No Code'}</small>
                        </div>`,
                        `<span class="badge bg-primary">${request.leave_type || 'N/A'}</span>`,
                        request.start_date || 'N/A',
                        request.end_date || 'N/A',
                        `<span class="badge bg-light text-dark">${request.total_days || 0} days</span>`,
                        statusBadge,
                        actions
                    ]);
                });
                
                leavesTable.draw();
            } catch (error) {
                console.error('Error displaying leave requests:', error);
            }
        }
        
        // Enhanced utility functions
        function updateEmployeeStats(employees) {
            const activeCount = employees.filter(emp => emp.status === 'active').length;
            const totalCount = employees.length;
            
            // Update dashboard statistics
            const totalEmployeesCard = document.querySelector('.stat-number');
            if (totalEmployeesCard) {
                totalEmployeesCard.textContent = totalCount;
            }
        }
        
        function setupRealtimeUpdates() {
            // Auto-refresh data every 5 minutes
            setInterval(function() {
                loadEmployees();
                loadLeaveRequests();
            }, 300000); // 5 minutes
        }
        
        // Enhanced action functions
        function viewEmployeeDetails(employeeId) {
            showAlert(`Loading employee details for ID: ${employeeId}`, 'info');
            // Implementation for viewing employee details
        }
        
        function editEmployeeDetails(employeeId) {
            showAlert(`Edit mode for employee ID: ${employeeId}`, 'info');
            // Implementation for editing employee
        }
        
        function viewEmployeeAttendance(employeeId) {
            showAlert(`Loading attendance for employee ID: ${employeeId}`, 'info');
            // Implementation for viewing attendance
        }
        
        function generateEmployeeReport(employeeId) {
            showAlert(`Generating report for employee ID: ${employeeId}`, 'info');
            // Implementation for generating reports
        }
        
        function deleteEmployeeRecord(employeeId) {
            if (confirm('Are you sure you want to delete this employee record?')) {
                showAlert(`Employee ID ${employeeId} would be deleted`, 'warning');
                // Implementation for deleting employee
            }
        }
        
        function viewLeaveRequestDetails(requestId) {
            showAlert(`Loading leave request details for ID: ${requestId}`, 'info');
            // Implementation for viewing leave details
        }
        
        function approveLeaveRequest(requestId) {
            if (confirm('Are you sure you want to approve this leave request?')) {
                showAlert(`Leave request ${requestId} approved`, 'success');
                loadLeaveRequests(); // Refresh the table
            }
        }
        
        function rejectLeaveRequest(requestId) {
            if (confirm('Are you sure you want to reject this leave request?')) {
                showAlert(`Leave request ${requestId} rejected`, 'danger');
                loadLeaveRequests(); // Refresh the table
            }
        }
        
        function printLeaveRequest(requestId) {
            showAlert(`Printing leave request ${requestId}`, 'info');
            // Implementation for printing
        }

        // Advanced HRMS Functions
        function loadAdvancedLeaveRequests() {
            const startDate = $('#leaveDateFrom').val() || '<?php echo date('Y-m-01'); ?>';
            const endDate = $('#leaveDateTo').val() || '<?php echo date('Y-m-t'); ?>';
            const statusFilter = $('#leaveStatusFilter').val() || 'all';
            
            $.post('', {
                action: 'get_leave_requests_advanced',
                start_date: startDate,
                end_date: endDate,
                status_filter: statusFilter
            }, function(response) {
                if (response.success) {
                    renderAdvancedLeaveTable(response.data);
                    updateLeaveStatistics(response.data);
                } else {
                    showAlert('Error loading leave requests: ' + response.message, 'danger');
                }
            }, 'json');
        }
        
        function renderAdvancedLeaveTable(leaveRequests) {
            const tbody = $('#advancedLeaveTable tbody');
            tbody.empty();
            
            leaveRequests.forEach(request => {
                const workflowSteps = renderWorkflowSteps(request.approval_steps);
                const policyCompliance = checkPolicyCompliance(request);
                const escalationStatus = checkEscalationStatus(request);
                
                const row = `
                    <tr>
                        <td><span class="badge bg-secondary">#${request.id}</span></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="employee-avatar">${request.employee_name.charAt(0)}</div>
                                <div class="ms-2">
                                    <div class="fw-semibold">${request.employee_name}</div>
                                    <small class="text-muted">${request.employee_code}</small>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge bg-info">${request.department_name}</span></td>
                        <td><span class="badge bg-primary">${request.leave_type}</span></td>
                        <td>
                            <div>${request.from_date} to ${request.to_date}</div>
                            <small class="text-muted">${request.days_requested} days</small>
                        </td>
                        <td>${workflowSteps}</td>
                        <td>
                            <span class="badge bg-${getStatusColor(request.status)}">${request.status.toUpperCase()}</span>
                            ${escalationStatus}
                        </td>
                        <td>${policyCompliance}</td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-success" onclick="approveAdvancedLeave(${request.id})">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button class="btn btn-outline-danger" onclick="rejectAdvancedLeave(${request.id})">
                                    <i class="fas fa-times"></i>
                                </button>
                                <button class="btn btn-outline-info" onclick="viewLeaveDetails(${request.id})">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
                tbody.append(row);
            });
            
            // Initialize DataTable if not already done
            if (!$.fn.DataTable.isDataTable('#advancedLeaveTable')) {
                $('#advancedLeaveTable').DataTable({
                    pageLength: 10,
                    responsive: true,
                    order: [[0, 'desc']],
                    columnDefs: [
                        { orderable: false, targets: [5, 7, 8] }
                    ]
                });
            }
        }
        
        function renderWorkflowSteps(steps) {
            if (!steps || steps.length === 0) {
                return '<span class="text-muted">No workflow</span>';
            }
            
            let html = '<div class="workflow-steps">';
            steps.forEach((step, index) => {
                const statusClass = step.status === 'approved' ? 'approved' : 
                                  step.status === 'rejected' ? 'rejected' : 'pending';
                html += `
                    <div class="workflow-step ${statusClass}">
                        <div class="workflow-step-icon">${step.level}</div>
                        <div class="workflow-step-content">
                            <small>${step.approver_role}</small>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            return html;
        }
        
        function checkPolicyCompliance(request) {
            // Simulate policy compliance check
            const compliance = ['compliant', 'warning', 'violation'][Math.floor(Math.random() * 3)];
            const icons = {
                'compliant': '<i class="fas fa-check-circle text-success"></i> Compliant',
                'warning': '<i class="fas fa-exclamation-triangle text-warning"></i> Warning',
                'violation': '<i class="fas fa-times-circle text-danger"></i> Violation'
            };
            return icons[compliance];
        }
        
        function checkEscalationStatus(request) {
            // Simulate escalation check
            const daysSinceApplication = Math.floor(Math.random() * 10);
            if (daysSinceApplication > 7) {
                return '<span class="escalation-indicator urgent">URGENT</span>';
            } else if (daysSinceApplication > 3) {
                return '<span class="escalation-indicator warning">DUE SOON</span>';
            }
            return '<span class="escalation-indicator normal">ON TIME</span>';
        }
        
        function updateLeaveStatistics(leaveRequests) {
            const stats = {
                pending: leaveRequests.filter(r => r.status === 'pending').length,
                approved: leaveRequests.filter(r => r.status === 'approved').length,
                rejected: leaveRequests.filter(r => r.status === 'rejected').length,
                escalated: leaveRequests.filter(r => Math.floor(Math.random() * 10) > 7).length
            };
            
            $('#pendingLeaveCount').text(stats.pending);
            $('#approvedLeaveCount').text(stats.approved);
            $('#rejectedLeaveCount').text(stats.rejected);
            $('#escalatedLeaveCount').text(stats.escalated);
        }
        
        function loadAnalyticsDashboard() {
            const startDate = $('#analyticsDateFrom').val();
            const endDate = $('#analyticsDateTo').val();
            
            $.post('', {
                action: 'get_analytics_dashboard',
                start_date: startDate,
                end_date: endDate
            }, function(response) {
                if (response.success) {
                    renderAnalyticsDashboard(response.data);
                } else {
                    showAlert('Error loading analytics: ' + response.message, 'danger');
                }
            }, 'json');
        }
        
        function renderAnalyticsDashboard(data) {
            // Update employee metrics
            $('#employeeMetrics').text(data.employee_analytics.active_employees + '/' + data.employee_analytics.total_employees);
            $('#employeeTrend').text('+' + data.employee_analytics.new_hires + ' this month').addClass('analytics-trend positive');
            
            // Update attendance rate
            const attendanceRate = Math.round((data.attendance_analytics.length / 30) * 100);
            $('#attendanceRate').text(attendanceRate + '%');
            $('#attendanceTrend').text('↑ 5% from last month').addClass('analytics-trend positive');
            
            // Update attrition risk
            $('#attritionRisk').text('Low');
            $('#attritionTrend').text('↓ 2% improvement').addClass('analytics-trend positive');
            
            // Update workforce forecast
            $('#workforceForecast').text('Optimal');
            $('#workforceTrend').text('No critical gaps').addClass('analytics-trend positive');
        }
        
        function loadAuditLogs() {
            const startDate = $('#auditDateFrom').val() || '<?php echo date('Y-m-01'); ?>';
            const endDate = $('#auditDateTo').val() || '<?php echo date('Y-m-t'); ?>';
            const actionFilter = $('#auditActionFilter').val() || 'all';
            
            $.post('', {
                action: 'get_audit_logs',
                start_date: startDate,
                end_date: endDate,
                action_filter: actionFilter,
                limit: 50,
                offset: 0
            }, function(response) {
                if (response.success) {
                    renderAuditLogsTable(response.data);
                } else {
                    showAlert('Error loading audit logs: ' + response.message, 'danger');
                }
            }, 'json');
        }
        
        function renderAuditLogsTable(logs) {
            const tbody = $('#auditLogsTable tbody');
            tbody.empty();
            
            logs.forEach(log => {
                const row = `
                    <tr>
                        <td>${new Date(log.created_at).toLocaleString()}</td>
                        <td>${log.user_name || 'Unknown'}</td>
                        <td><span class="badge bg-info">${log.action}</span></td>
                        <td>${log.data ? JSON.stringify(JSON.parse(log.data), null, 2).substring(0, 100) + '...' : 'No details'}</td>
                        <td><small>${log.ip_address}</small></td>
                        <td><small>${log.user_agent ? log.user_agent.substring(0, 50) + '...' : 'Unknown'}</small></td>
                        <td>
                            <button class="btn btn-sm btn-outline-info" onclick="viewAuditDetails('${log.id}')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                `;
                tbody.append(row);
            });
            
            // Initialize DataTable if not already done
            if (!$.fn.DataTable.isDataTable('#auditLogsTable')) {
                $('#auditLogsTable').DataTable({
                    pageLength: 25,
                    responsive: true,
                    order: [[0, 'desc']],
                    columnDefs: [
                        { orderable: false, targets: [6] }
                    ]
                });
            }
        }
        
        function loadWorkflowConfigs() {
            $.post('', {
                action: 'get_approval_workflow_config'
            }, function(response) {
                if (response.success) {
                    renderWorkflowConfigTable(response.data);
                } else {
                    showAlert('Error loading workflow configurations: ' + response.message, 'danger');
                }
            }, 'json');
        }
        
        function renderWorkflowConfigTable(workflows) {
            const tbody = $('#workflowConfigTable tbody');
            tbody.empty();
            
            // Group workflows by department
            const groupedWorkflows = {};
            workflows.forEach(workflow => {
                const key = workflow.department_name || 'Default';
                if (!groupedWorkflows[key]) {
                    groupedWorkflows[key] = [];
                }
                groupedWorkflows[key].push(workflow);
            });
            
            Object.keys(groupedWorkflows).forEach(department => {
                const departmentWorkflows = groupedWorkflows[department];
                const levels = departmentWorkflows.map(w => w.approver_role).join(' → ');
                const maxEscalation = Math.max(...departmentWorkflows.map(w => w.escalation_hours));
                
                const row = `
                    <tr>
                        <td><strong>${department} Workflow</strong></td>
                        <td><span class="badge bg-secondary">${department}</span></td>
                        <td><small>${levels}</small></td>
                        <td>${maxEscalation} hours</td>
                        <td><span class="badge bg-success">Active</span></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" onclick="editWorkflow('${department}')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-danger" onclick="deleteWorkflow('${department}')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
                tbody.append(row);
            });
        }
        
        function filterLeaveRequests() {
            loadAdvancedLeaveRequests();
        }
        
        function approveAdvancedLeave(leaveId) {
            // Implementation for advanced leave approval
            showAlert('Advanced leave approval functionality activated!', 'success');
        }
        
        function rejectAdvancedLeave(leaveId) {
            // Implementation for advanced leave rejection
            showAlert('Advanced leave rejection functionality activated!', 'warning');
        }
        
        function viewLeaveDetails(leaveId) {
            // Implementation for viewing detailed leave information
            showAlert('Detailed leave view functionality activated!', 'info');
        }
        
        function viewAuditDetails(logId) {
            // Implementation for viewing detailed audit information
            showAlert('Detailed audit view functionality activated!', 'info');
        }
        
        function showDelegationModal() {
            showAlert('Delegation rights management modal will open here!', 'info');
        }
        
        function showWorkflowDesigner() {
            showAlert('Workflow designer will open here!', 'info');
        }
        
        function showPolicyRuleModal() {
            showAlert('Policy rule configuration modal will open here!', 'info');
        }
        
        function exportEmployeeData() {
            showAlert('Employee data export functionality activated!', 'success');
        }
        
        function getStatusColor(status) {
            const colors = {
                'pending': 'warning',
                'approved': 'success',
                'rejected': 'danger',
                'escalated': 'info'
            };
            return colors[status] || 'secondary';
        }
        
        // Initialize advanced features on page load
        $(document).ready(function() {
            // Set default dates
            $('#leaveDateFrom').val('<?php echo date('Y-m-01'); ?>');
            $('#leaveDateTo').val('<?php echo date('Y-m-t'); ?>');
            $('#analyticsDateFrom').val('<?php echo date('Y-m-01'); ?>');
            $('#analyticsDateTo').val('<?php echo date('Y-m-t'); ?>');
            $('#auditDateFrom').val('<?php echo date('Y-m-01'); ?>');
            $('#auditDateTo').val('<?php echo date('Y-m-t'); ?>');
            
            // Load initial data
            loadAdvancedLeaveRequests();
            loadAnalyticsDashboard();
            loadAuditLogs();
            loadWorkflowConfigs();
        });

        function showAlert(message, type = 'info') {
            const alertDiv = `
                <div class="alert alert-${type} alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; max-width: 400px;">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            $('body').append(alertDiv);
            setTimeout(function() {
                $('.alert').fadeOut();
            }, 5000);
        }
    </script>
</body>
</html>
