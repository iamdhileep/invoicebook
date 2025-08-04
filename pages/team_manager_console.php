<?php
/**
 * Team Manager Console - Manager-specific HR functionalities
 * Direct pages folder implementation with full functionality
 */
session_start();

// Initialize session for demo
if (!isset($_SESSION['admin'])) {
    $_SESSION['admin'] = 1;
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'manager';
}

require_once '../db.php';
$page_title = 'Team Manager Console - HRMS';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_team_members':
            try {
                $query = $conn->prepare("SELECT 
                                        employee_id,
                                        COALESCE(name, CONCAT(first_name, ' ', last_name)) as full_name,
                                        employee_code,
                                        position,
                                        department_name,
                                        email,
                                        phone,
                                        status,
                                        hire_date
                                        FROM employees 
                                        WHERE status = 'active' 
                                        ORDER BY name");
                $query->execute();
                $result = $query->get_result();
                
                $members = [];
                while ($row = $result->fetch_assoc()) {
                    $members[] = $row;
                }
                
                echo json_encode(['success' => true, 'data' => $members]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'get_pending_leaves':
            try {
                $query = $conn->prepare("SELECT 
                                        lr.id,
                                        COALESCE(e.name, CONCAT(e.first_name, ' ', e.last_name)) as employee_name,
                                        lr.leave_type,
                                        lr.start_date,
                                        lr.end_date,
                                        lr.total_days,
                                        lr.status,
                                        lr.reason,
                                        lr.created_at
                                        FROM leave_requests lr
                                        LEFT JOIN employees e ON lr.employee_id = e.employee_id
                                        WHERE lr.status = 'pending'
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

        case 'get_team_leave_requests_advanced':
            try {
                $start_date = $_POST['start_date'] ?? date('Y-m-01');
                $end_date = $_POST['end_date'] ?? date('Y-m-t');
                $status_filter = $_POST['status_filter'] ?? 'all';
                
                $status_condition = '';
                if ($status_filter !== 'all') {
                    $status_condition = "AND lr.status = '$status_filter'";
                }
                
                $query = $conn->prepare("SELECT 
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
                                        las.comments as step_comments,
                                        lbt.available_days
                                        FROM leave_requests lr
                                        LEFT JOIN employees e ON lr.employee_id = e.employee_id
                                        LEFT JOIN leave_approval_steps las ON lr.id = las.leave_request_id
                                        LEFT JOIN leave_balance_tracking lbt ON lr.employee_id = lbt.employee_id 
                                            AND lr.leave_type = lbt.leave_type AND lbt.year = YEAR(CURDATE())
                                        WHERE lr.applied_date BETWEEN ? AND ?
                                        {$status_condition}
                                        ORDER BY lr.applied_date DESC, las.approval_level");
                
                $query->bind_param("ss", $start_date, $end_date);
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
                            'available_days' => $row['available_days'],
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

        case 'get_team_analytics':
            try {
                $start_date = $_POST['start_date'] ?? date('Y-m-01');
                $end_date = $_POST['end_date'] ?? date('Y-m-t');
                
                // Team performance metrics
                $team_analytics = [
                    'team_size' => 0,
                    'attendance_rate' => 0,
                    'productivity_score' => 0,
                    'leave_utilization' => 0
                ];
                
                $result = $conn->query("SELECT COUNT(*) as count FROM employees WHERE status = 'active'");
                if ($result) $team_analytics['team_size'] = $result->fetch_assoc()['count'];
                
                // Attendance rate calculation
                $result = $conn->query("SELECT 
                                       (COUNT(DISTINCT CONCAT(employee_id, DATE(check_in))) / 
                                        (COUNT(DISTINCT employee_id) * DATEDIFF('$end_date', '$start_date') + 1)) * 100 as rate
                                       FROM attendance 
                                       WHERE DATE(check_in) BETWEEN '$start_date' AND '$end_date'");
                if ($result) $team_analytics['attendance_rate'] = round($result->fetch_assoc()['rate'], 1);
                
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
                
                // Team workload forecast
                $workload_forecast = [];
                $result = $conn->query("SELECT 
                                       DATE(from_date) as leave_date,
                                       COUNT(*) as employees_on_leave
                                       FROM leave_requests 
                                       WHERE status = 'approved' 
                                       AND from_date >= CURDATE() 
                                       AND from_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                                       GROUP BY DATE(from_date)
                                       ORDER BY leave_date");
                while ($row = $result->fetch_assoc()) {
                    $workload_forecast[] = $row;
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'team_analytics' => $team_analytics,
                        'leave_analytics' => $leave_analytics,
                        'workload_forecast' => $workload_forecast
                    ]
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        case 'bulk_approve_leaves':
            try {
                $leave_ids = json_decode($_POST['leave_ids'], true);
                $comments = $_POST['comments'] ?? '';
                $manager_id = $_SESSION['user_id'];
                
                if (!is_array($leave_ids) || empty($leave_ids)) {
                    throw new Exception('No leave requests selected');
                }
                
                $conn->begin_transaction();
                
                $success_count = 0;
                $error_messages = [];
                
                foreach ($leave_ids as $leave_id) {
                    try {
                        // Update leave request
                        $stmt = $conn->prepare("UPDATE leave_requests SET status = 'approved', approved_by = ?, approved_date = NOW(), approver_comments = ? WHERE id = ? AND status = 'pending'");
                        $stmt->bind_param("isi", $manager_id, $comments, $leave_id);
                        
                        if ($stmt->execute() && $stmt->affected_rows > 0) {
                            // Update approval step
                            $stmt2 = $conn->prepare("UPDATE leave_approval_steps SET approver_id = ?, status = 'approved', comments = ?, approved_at = NOW() WHERE leave_request_id = ? AND approval_level = 1");
                            $stmt2->bind_param("isi", $manager_id, $comments, $leave_id);
                            $stmt2->execute();
                            
                            $success_count++;
                        }
                    } catch (Exception $e) {
                        $error_messages[] = "Leave ID $leave_id: " . $e->getMessage();
                    }
                }
                
                $conn->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => "$success_count leave requests approved successfully",
                    'errors' => $error_messages
                ]);
                
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'update_leave_status':
            try {
                $leave_id = $_POST['leave_id'] ?? 0;
                $status = $_POST['status'] ?? '';
                $manager_comments = $_POST['comments'] ?? '';
                
                if (!$leave_id || !in_array($status, ['approved', 'rejected'])) {
                    throw new Exception('Invalid parameters');
                }
                
                $stmt = $conn->prepare("UPDATE leave_requests SET status = ?, manager_comments = ?, approved_at = NOW() WHERE id = ?");
                $stmt->bind_param("ssi", $status, $manager_comments, $leave_id);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Leave request ' . $status . ' successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update leave request']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'get_team_schedule':
            try {
                $date = $_POST['date'] ?? date('Y-m-d');
                
                $query = $conn->prepare("SELECT 
                                        e.employee_id,
                                        COALESCE(e.name, CONCAT(e.first_name, ' ', e.last_name)) as employee_name,
                                        a.check_in,
                                        a.check_out,
                                        CASE 
                                            WHEN a.check_in IS NOT NULL AND a.check_out IS NOT NULL THEN 'Present'
                                            WHEN a.check_in IS NOT NULL AND a.check_out IS NULL THEN 'Checked In'
                                            ELSE 'Absent'
                                        END as status
                                        FROM employees e
                                        LEFT JOIN attendance a ON e.employee_id = a.employee_id AND DATE(a.check_in) = ?
                                        WHERE e.status = 'active'
                                        ORDER BY e.name");
                $query->bind_param("s", $date);
                $query->execute();
                $result = $query->get_result();
                
                $schedule = [];
                while ($row = $result->fetch_assoc()) {
                    $schedule[] = $row;
                }
                
                echo json_encode(['success' => true, 'data' => $schedule]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
    }
}

// Get dashboard statistics
$stats = [
    'team_size' => 0,
    'present_today' => 0,
    'pending_approvals' => 0,
    'on_leave' => 0
];

try {
    // Get team size
    $result = $conn->query("SELECT COUNT(*) as count FROM employees WHERE status = 'active'");
    if ($result) $stats['team_size'] = $result->fetch_assoc()['count'];
    
    // Get today's attendance
    $today = date('Y-m-d');
    $result = $conn->query("SELECT COUNT(DISTINCT employee_id) as count FROM attendance WHERE DATE(check_in) = '$today'");
    if ($result) $stats['present_today'] = $result->fetch_assoc()['count'];
    
    // Get pending leave approvals
    try {
        $result = $conn->query("SELECT COUNT(*) as count FROM leave_requests WHERE status = 'pending'");
        if ($result) $stats['pending_approvals'] = $result->fetch_assoc()['count'];
    } catch (Exception $e) {
        $stats['pending_approvals'] = 0;
    }
    
    // Get employees on leave today
    try {
        $result = $conn->query("SELECT COUNT(*) as count FROM leave_requests WHERE status = 'approved' AND '$today' BETWEEN start_date AND end_date");
        if ($result) $stats['on_leave'] = $result->fetch_assoc()['count'];
    } catch (Exception $e) {
        $stats['on_leave'] = 0;
    }
    
} catch (Exception $e) {
    error_log("Team Manager Console error: " . $e->getMessage());
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
            --primary-color: #059669;
            --secondary-color: #64748b;
            --success-color: #059669;
            --info-color: #0891b2;
            --warning-color: #d97706;
            --danger-color: #dc2626;
            --dark-color: #1e293b;
        }
        
        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            margin: 0;
            padding: 0;
        }
        
        .manager-sidebar {
            background: linear-gradient(180deg, #064e3b 0%, #065f46 100%);
            min-height: 100vh;
            box-shadow: 4px 0 20px rgba(0,0,0,0.15);
            position: fixed;
            width: 280px;
            z-index: 1000;
        }
        
        .manager-content {
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
            color: #a7f3d0;
            text-decoration: none;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
            font-weight: 500;
        }
        
        .sidebar-link:hover,
        .sidebar-link.active {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            transform: translateX(4px);
        }
        
        .sidebar-link i {
            width: 20px;
            margin-right: 0.75rem;
            text-align: center;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
        
        .btn-success.btn-modern {
            background: var(--primary-color);
            box-shadow: 0 4px 14px rgba(5, 150, 105, 0.25);
        }
        
        .btn-success.btn-modern:hover {
            background: #047857;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.35);
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
        
        .avatar-sm {
            width: 40px;
            height: 40px;
            font-size: 14px;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .table-responsive {
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        
        .table-modern {
            background: white;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        
        .table-modern thead th {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border: none;
            color: var(--dark-color);
            font-weight: 600;
            padding: 1rem;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .table-modern tbody td {
            border: none;
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .table-modern tbody tr:hover {
            background: rgba(5, 150, 105, 0.05);
        }
        
        .table-modern tbody tr:last-child td {
            border-bottom: none;
        }
        
        .badge {
            font-weight: 500;
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
        }
        
        .btn-group-sm .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border-radius: 0.375rem;
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
        
        @media (max-width: 768px) {
            .manager-sidebar {
                width: 100%;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .manager-sidebar.show {
                transform: translateX(0);
            }
            
            .manager-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="manager-sidebar">
        <div class="sidebar-header">
            <a href="#" class="sidebar-brand">
                <i class="fas fa-users"></i>
                <div>
                    <div>Team Manager</div>
                    <small style="color: #a7f3d0; font-size: 0.75rem;">Console</small>
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
                <a href="hrms_admin_panel.php" class="sidebar-link">
                    <i class="fas fa-user-tie"></i>Admin Panel
                </a>
            </div>
            <div class="sidebar-item">
                <a href="#" class="sidebar-link active">
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
    <main class="manager-content">
        <!-- Header -->
        <div class="content-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="content-title">Team Manager Console</h1>
                    <p class="content-subtitle">Manage your team, attendance, and leave requests</p>
                </div>
                <div>
                    <button class="btn btn-outline-success btn-modern me-2" onclick="refreshData()">
                        <i class="fas fa-sync-alt me-2"></i>Refresh
                    </button>
                    <button class="btn btn-success btn-modern" onclick="exportReports()">
                        <i class="fas fa-download me-2"></i>Export Reports
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
                <div class="stat-number"><?php echo $stats['team_size']; ?></div>
                <p class="stat-label">Team Size</p>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--success-color);">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-number"><?php echo $stats['present_today']; ?></div>
                <p class="stat-label">Present Today</p>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--warning-color);">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?php echo $stats['pending_approvals']; ?></div>
                <p class="stat-label">Pending Approvals</p>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--info-color);">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <div class="stat-number"><?php echo $stats['on_leave']; ?></div>
                <p class="stat-label">On Leave</p>
            </div>
        </div>
        
        <!-- Main Content Tabs -->
        <div class="tab-content-card">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#team">
                        <i class="fas fa-users me-2"></i>Team Members
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#approvals">
                        <i class="fas fa-check-circle me-2"></i>Leave Approvals
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#schedule">
                        <i class="fas fa-calendar-alt me-2"></i>Team Schedule
                    </button>
                </li>
            </ul>
            
            <div class="tab-content">
                <!-- Team Members Tab -->
                <div class="tab-pane fade show active" id="team" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-bold"><i class="fas fa-users me-2 text-success"></i>Team Members</h4>
                        <button class="btn btn-success btn-modern" onclick="loadTeamMembers()">
                            <i class="fas fa-sync-alt me-2"></i>Refresh Team
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-modern" id="teamTable">
                            <thead>
                                <tr>
                                    <th>Employee ID</th>
                                    <th>Full Name</th>
                                    <th>Position</th>
                                    <th>Department</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Sample team members for demonstration -->
                                <tr>
                                    <td><span class="fw-bold text-primary">#001</span></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                                <strong>D</strong>
                                            </div>
                                            <div>
                                                <div class="fw-semibold">Dhileepkumar</div>
                                                <small class="text-muted">Lead Developer</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-info">Senior Software Developer</span></td>
                                    <td><span class="badge bg-secondary">Engineering</span></td>
                                    <td><a href="mailto:dhileep@company.com" class="text-decoration-none">dhileep@company.com</a></td>
                                    <td><span class="badge bg-success">Active</span></td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button class="btn btn-outline-primary" onclick="viewTeamMemberDetails(1)" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-outline-info" onclick="viewAttendanceReport(1)" title="Attendance">
                                                <i class="fas fa-clock"></i>
                                            </button>
                                            <button class="btn btn-outline-success" onclick="viewPerformance(1)" title="Performance">
                                                <i class="fas fa-chart-line"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td><span class="fw-bold text-primary">#002</span></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                                <strong>A</strong>
                                            </div>
                                            <div>
                                                <div class="fw-semibold">Alex Thompson</div>
                                                <small class="text-muted">Frontend Specialist</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-info">Frontend Developer</span></td>
                                    <td><span class="badge bg-secondary">Engineering</span></td>
                                    <td><a href="mailto:alex@company.com" class="text-decoration-none">alex@company.com</a></td>
                                    <td><span class="badge bg-success">Active</span></td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button class="btn btn-outline-primary" onclick="viewTeamMemberDetails(2)" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-outline-info" onclick="viewAttendanceReport(2)" title="Attendance">
                                                <i class="fas fa-clock"></i>
                                            </button>
                                            <button class="btn btn-outline-success" onclick="viewPerformance(2)" title="Performance">
                                                <i class="fas fa-chart-line"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td><span class="fw-bold text-primary">#003</span></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-warning text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                                <strong>L</strong>
                                            </div>
                                            <div>
                                                <div class="fw-semibold">Lisa Wong</div>
                                                <small class="text-muted">QA Engineer</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-info">Quality Assurance</span></td>
                                    <td><span class="badge bg-secondary">Engineering</span></td>
                                    <td><a href="mailto:lisa@company.com" class="text-decoration-none">lisa@company.com</a></td>
                                    <td><span class="badge bg-warning">On Leave</span></td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button class="btn btn-outline-primary" onclick="viewTeamMemberDetails(3)" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-outline-info" onclick="viewAttendanceReport(3)" title="Attendance">
                                                <i class="fas fa-clock"></i>
                                            </button>
                                            <button class="btn btn-outline-success" onclick="viewPerformance(3)" title="Performance">
                                                <i class="fas fa-chart-line"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Leave Approvals Tab -->
                <div class="tab-pane fade" id="approvals" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-bold"><i class="fas fa-check-circle me-2 text-warning"></i>Pending Leave Approvals</h4>
                        <div>
                            <button class="btn btn-warning btn-modern me-2" onclick="bulkApprove()">
                                <i class="fas fa-check-double me-2"></i>Bulk Approve
                            </button>
                            <button class="btn btn-success btn-modern" onclick="loadPendingLeaves()">
                                <i class="fas fa-sync-alt me-2"></i>Refresh
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-modern" id="approvalsTable">
                            <thead>
                                <tr>
                                    <th>
                                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                    </th>
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
                                <!-- Sample leave approval requests for demonstration -->
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input" name="leaveSelect" value="1">
                                    </td>
                                    <td><span class="fw-bold text-primary">#LR001</span></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                                <strong>D</strong>
                                            </div>
                                            <div>
                                                <div class="fw-semibold">Dhileepkumar</div>
                                                <small class="text-muted">Senior Developer</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-info">Annual Leave</span></td>
                                    <td>Dec 20, 2024</td>
                                    <td>Dec 24, 2024</td>
                                    <td><span class="fw-bold">5</span></td>
                                    <td><span class="badge bg-warning">Pending</span></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-success" onclick="approveLeave(1)" title="Approve">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="rejectLeave(1)" title="Reject">
                                                <i class="fas fa-times"></i>
                                            </button>
                                            <button class="btn btn-outline-info" onclick="viewLeaveDetails(1)" title="Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input" name="leaveSelect" value="2">
                                    </td>
                                    <td><span class="fw-bold text-primary">#LR002</span></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                                <strong>A</strong>
                                            </div>
                                            <div>
                                                <div class="fw-semibold">Alex Thompson</div>
                                                <small class="text-muted">Frontend Developer</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-warning">Sick Leave</span></td>
                                    <td>Dec 18, 2024</td>
                                    <td>Dec 19, 2024</td>
                                    <td><span class="fw-bold">2</span></td>
                                    <td><span class="badge bg-warning">Pending</span></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-success" onclick="approveLeave(2)" title="Approve">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="rejectLeave(2)" title="Reject">
                                                <i class="fas fa-times"></i>
                                            </button>
                                            <button class="btn btn-outline-info" onclick="viewLeaveDetails(2)" title="Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input" name="leaveSelect" value="3">
                                    </td>
                                    <td><span class="fw-bold text-primary">#LR003</span></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-warning text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                                <strong>L</strong>
                                            </div>
                                            <div>
                                                <div class="fw-semibold">Lisa Wong</div>
                                                <small class="text-muted">QA Engineer</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-primary">Personal Leave</span></td>
                                    <td>Dec 23, 2024</td>
                                    <td>Dec 23, 2024</td>
                                    <td><span class="fw-bold">1</span></td>
                                    <td><span class="badge bg-warning">Pending</span></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-success" onclick="approveLeave(3)" title="Approve">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="rejectLeave(3)" title="Reject">
                                                <i class="fas fa-times"></i>
                                            </button>
                                            <button class="btn btn-outline-info" onclick="viewLeaveDetails(3)" title="Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Team Schedule Tab -->
                <div class="tab-pane fade" id="schedule" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-bold"><i class="fas fa-calendar-alt me-2 text-info"></i>Team Schedule</h4>
                        <div>
                            <input type="date" class="form-control d-inline-block me-2" style="width: auto;" id="scheduleDate" value="<?php echo date('Y-m-d'); ?>" onchange="loadTeamSchedule()">
                            <button class="btn btn-info btn-modern" onclick="loadTeamSchedule()">
                                <i class="fas fa-sync-alt me-2"></i>Load Schedule
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-modern" id="scheduleTable">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Check In</th>
                                    <th>Check Out</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Sample schedule data for demonstration -->
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                                <strong>D</strong>
                                            </div>
                                            <div>
                                                <div class="fw-semibold">Dhileepkumar</div>
                                                <small class="text-muted">Senior Developer</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="fw-bold text-success">09:15 AM</span></td>
                                    <td><span class="fw-bold text-danger">06:30 PM</span></td>
                                    <td><span class="badge bg-success">Present</span></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-info" onclick="viewDailyReport(1)" title="View Report">
                                                <i class="fas fa-chart-line"></i>
                                            </button>
                                            <button class="btn btn-outline-primary" onclick="editAttendance(1)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                                <strong>A</strong>
                                            </div>
                                            <div>
                                                <div class="fw-semibold">Alex Thompson</div>
                                                <small class="text-muted">Frontend Developer</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="fw-bold text-success">08:45 AM</span></td>
                                    <td><span class="fw-bold text-danger">05:45 PM</span></td>
                                    <td><span class="badge bg-success">Present</span></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-info" onclick="viewDailyReport(2)" title="View Report">
                                                <i class="fas fa-chart-line"></i>
                                            </button>
                                            <button class="btn btn-outline-primary" onclick="editAttendance(2)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-warning text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                                <strong>L</strong>
                                            </div>
                                            <div>
                                                <div class="fw-semibold">Lisa Wong</div>
                                                <small class="text-muted">QA Engineer</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="text-muted">--</span></td>
                                    <td><span class="text-muted">--</span></td>
                                    <td><span class="badge bg-warning">On Leave</span></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-secondary" disabled title="On Leave">
                                                <i class="fas fa-calendar-times"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Leave Action Modal -->
    <div class="modal fade" id="leaveActionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-check-circle me-2 text-success"></i>Leave Request Action
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="leaveActionForm">
                        <input type="hidden" id="leaveId" name="leave_id">
                        <input type="hidden" id="actionType" name="status">
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Manager Comments</label>
                            <textarea class="form-control" name="comments" rows="3" placeholder="Add your comments (optional)"></textarea>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <span id="actionMessage"></span>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success btn-modern" onclick="submitLeaveAction()">
                        <i class="fas fa-check me-2"></i>Confirm Action
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
        let teamTable = null;
        let approvalsTable = null;
        let scheduleTable = null;
        
        $(document).ready(function() {
            console.log('Team Manager Console loaded');
            initializeDataTables();
            loadTeamMembers();
            loadPendingLeaves();
            loadTeamSchedule();
        });
        
        function initializeDataTables() {
            try {
                // Initialize team table with existing static content
                if ($.fn.DataTable.isDataTable('#teamTable')) {
                    $('#teamTable').DataTable().destroy();
                }
                
                teamTable = $('#teamTable').DataTable({
                    responsive: true,
                    pageLength: 15,
                    searching: true,
                    ordering: true,
                    info: true,
                    paging: true,
                    language: { 
                        emptyTable: "No team members found",
                        search: "Search team members:",
                        lengthMenu: "Show _MENU_ members per page"
                    }
                });

                // Initialize approvals table for dynamic content
                if ($.fn.DataTable.isDataTable('#approvalsTable')) {
                    $('#approvalsTable').DataTable().destroy();
                }

                approvalsTable = $('#approvalsTable').DataTable({
                    responsive: true,
                    pageLength: 15,
                    columns: [
                        { title: "Select", data: null, orderable: false },
                        { title: "Request ID", data: null },
                        { title: "Employee", data: null },
                        { title: "Leave Type", data: null },
                        { title: "Start Date", data: null },
                        { title: "End Date", data: null },
                        { title: "Days", data: null },
                        { title: "Status", data: null },
                        { title: "Actions", data: null, orderable: false }
                    ],
                    language: { emptyTable: "No pending leave requests" }
                });

                // Initialize schedule table for dynamic content
                if ($.fn.DataTable.isDataTable('#scheduleTable')) {
                    $('#scheduleTable').DataTable().destroy();
                }

                scheduleTable = $('#scheduleTable').DataTable({
                    responsive: true,
                    pageLength: 15,
                    columns: [
                        { title: "Employee", data: null },
                        { title: "Check In", data: null },
                        { title: "Check Out", data: null },
                        { title: "Status", data: null },
                        { title: "Actions", data: null, orderable: false }
                    ],
                    language: { emptyTable: "No schedule data available" }
                });
                
                console.log('All DataTables initialized successfully');
            } catch (error) {
                console.error('DataTables initialization error:', error);
            }
        }
        
        function loadTeamMembers() {
            $.ajax({
                url: 'team_manager_console.php',
                method: 'POST',
                data: { action: 'get_team_members' },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data) {
                        displayTeamMembers(response.data);
                        showAlert('Team members loaded successfully (' + response.data.length + ' found)', 'success');
                    } else {
                        showAlert('Error loading team members', 'danger');
                        displayTeamMembers([]);
                    }
                },
                error: function() {
                    showAlert('Failed to load team members', 'danger');
                    displayTeamMembers([]);
                }
            });
        }
        
        function loadTeamMembers() {
            // Show loading state
            $('#teamTable tbody').html('<tr><td colspan="7" class="text-center"><i class="fas fa-spinner fa-spin me-2"></i>Loading team members...</td></tr>');
            
            $.ajax({
                url: 'team_manager_console.php',
                method: 'POST',
                data: { action: 'get_team_members' },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data) {
                        displayTeamMembers(response.data);
                        showAlert('Team members loaded successfully (' + response.data.length + ' found)', 'success');
                    } else {
                        showAlert('Error loading team members', 'danger');
                        // Keep the static content if API fails
                        restoreStaticTeamContent();
                    }
                },
                error: function() {
                    showAlert('Failed to load team members', 'danger');
                    // Keep the static content if API fails
                    restoreStaticTeamContent();
                }
            });
        }
        
        function restoreStaticTeamContent() {
            const staticContent = `
                <tr>
                    <td><span class="fw-bold text-primary">#001</span></td>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                <strong>D</strong>
                            </div>
                            <div>
                                <div class="fw-semibold">Dhileepkumar</div>
                                <small class="text-muted">Lead Developer</small>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge bg-info">Senior Software Developer</span></td>
                    <td><span class="badge bg-secondary">Engineering</span></td>
                    <td><a href="mailto:dhileep@company.com" class="text-decoration-none">dhileep@company.com</a></td>
                    <td><span class="badge bg-success">Active</span></td>
                    <td>
                        <div class="btn-group btn-group-sm" role="group">
                            <button class="btn btn-outline-primary" onclick="viewTeamMemberDetails(1)" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-outline-info" onclick="viewAttendanceReport(1)" title="Attendance">
                                <i class="fas fa-clock"></i>
                            </button>
                            <button class="btn btn-outline-success" onclick="viewPerformance(1)" title="Performance">
                                <i class="fas fa-chart-line"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td><span class="fw-bold text-primary">#002</span></td>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                <strong>A</strong>
                            </div>
                            <div>
                                <div class="fw-semibold">Alex Thompson</div>
                                <small class="text-muted">Frontend Specialist</small>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge bg-info">Frontend Developer</span></td>
                    <td><span class="badge bg-secondary">Engineering</span></td>
                    <td><a href="mailto:alex@company.com" class="text-decoration-none">alex@company.com</a></td>
                    <td><span class="badge bg-success">Active</span></td>
                    <td>
                        <div class="btn-group btn-group-sm" role="group">
                            <button class="btn btn-outline-primary" onclick="viewTeamMemberDetails(2)" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-outline-info" onclick="viewAttendanceReport(2)" title="Attendance">
                                <i class="fas fa-clock"></i>
                            </button>
                            <button class="btn btn-outline-success" onclick="viewPerformance(2)" title="Performance">
                                <i class="fas fa-chart-line"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td><span class="fw-bold text-primary">#003</span></td>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm bg-warning text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                <strong>L</strong>
                            </div>
                            <div>
                                <div class="fw-semibold">Lisa Wong</div>
                                <small class="text-muted">QA Engineer</small>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge bg-info">Quality Assurance</span></td>
                    <td><span class="badge bg-secondary">Engineering</span></td>
                    <td><a href="mailto:lisa@company.com" class="text-decoration-none">lisa@company.com</a></td>
                    <td><span class="badge bg-warning">On Leave</span></td>
                    <td>
                        <div class="btn-group btn-group-sm" role="group">
                            <button class="btn btn-outline-primary" onclick="viewTeamMemberDetails(3)" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-outline-info" onclick="viewAttendanceReport(3)" title="Attendance">
                                <i class="fas fa-clock"></i>
                            </button>
                            <button class="btn btn-outline-success" onclick="viewPerformance(3)" title="Performance">
                                <i class="fas fa-chart-line"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
            $('#teamTable tbody').html(staticContent);
        }
        
        function displayTeamMembers(members) {
            try {
                let html = '';
                
                members.forEach(function(member, index) {
                    const avatarColors = ['primary', 'success', 'warning', 'info', 'secondary'];
                    const avatarColor = avatarColors[index % avatarColors.length];
                    
                    const statusColors = {
                        'active': 'success',
                        'inactive': 'secondary',
                        'on_leave': 'warning'
                    };
                    
                    const statusBadge = `<span class="badge bg-${statusColors[member.status] || 'secondary'}">${member.status}</span>`;
                    
                    const actions = `
                        <div class="btn-group btn-group-sm" role="group">
                            <button class="btn btn-outline-primary" onclick="viewTeamMemberDetails(${member.employee_id})" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-outline-info" onclick="viewAttendanceReport(${member.employee_id})" title="Attendance">
                                <i class="fas fa-clock"></i>
                            </button>
                            <button class="btn btn-outline-success" onclick="viewPerformance(${member.employee_id})" title="Performance">
                                <i class="fas fa-chart-line"></i>
                            </button>
                        </div>
                    `;
                    
                    html += `
                        <tr>
                            <td><span class="fw-bold text-primary">#${member.employee_id.toString().padStart(3, '0')}</span></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm bg-${avatarColor} text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                        <strong>${(member.full_name || '').charAt(0).toUpperCase()}</strong>
                                    </div>
                                    <div>
                                        <div class="fw-semibold">${member.full_name || 'N/A'}</div>
                                        <small class="text-muted">${member.employee_code || 'No Code'}</small>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge bg-info">${member.position || 'Not Set'}</span></td>
                            <td><span class="badge bg-secondary">${member.department_name || 'General'}</span></td>
                            <td>${member.email ? `<a href="mailto:${member.email}" class="text-decoration-none">${member.email}</a>` : '<span class="text-muted">No Email</span>'}</td>
                            <td>${statusBadge}</td>
                            <td>${actions}</td>
                        </tr>
                    `;
                });
                
                $('#teamTable tbody').html(html);
            } catch (error) {
                console.error('Error displaying team members:', error);
                restoreStaticTeamContent();
            }
        }
        
        function loadPendingLeaves() {
            $.ajax({
                url: 'team_manager_console.php',
                method: 'POST',
                data: { action: 'get_pending_leaves' },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data) {
                        displayPendingLeaves(response.data);
                        showAlert('Pending leaves loaded (' + response.data.length + ' found)', 'info');
                    } else {
                        displayPendingLeaves([]);
                    }
                },
                error: function() {
                    displayPendingLeaves([]);
                }
            });
        }
        
        function displayPendingLeaves(requests) {
            if (!approvalsTable) return;
            
            try {
                approvalsTable.clear();
                
                requests.forEach(function(request) {
                    const statusBadge = `<span class="badge badge-modern bg-warning">pending</span>`;
                    
                    const actions = `
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-outline-success" onclick="showLeaveAction(${request.id}, 'approved')" title="Approve">
                                <i class="fas fa-check"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="showLeaveAction(${request.id}, 'rejected')" title="Reject">
                                <i class="fas fa-times"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-info" onclick="viewLeaveDetails(${request.id})" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    `;
                    
                    const checkbox = `<input type="checkbox" class="leave-checkbox" value="${request.id}">`;
                    
                    approvalsTable.row.add([
                        checkbox,
                        request.id || 'N/A',
                        request.employee_name || 'N/A',
                        request.leave_type || 'N/A',
                        request.start_date || 'N/A',
                        request.end_date || 'N/A',
                        request.total_days || '0',
                        statusBadge,
                        actions
                    ]);
                });
                
                approvalsTable.draw();
            } catch (error) {
                console.error('Error displaying pending leaves:', error);
            }
        }
        
        function loadTeamSchedule() {
            const date = $('#scheduleDate').val() || new Date().toISOString().split('T')[0];
            
            $.ajax({
                url: 'team_manager_console.php',
                method: 'POST',
                data: { 
                    action: 'get_team_schedule',
                    date: date 
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data) {
                        displayTeamSchedule(response.data);
                        showAlert('Schedule loaded for ' + date, 'info');
                    } else {
                        displayTeamSchedule([]);
                    }
                },
                error: function() {
                    displayTeamSchedule([]);
                }
            });
        }
        
        function displayTeamSchedule(schedule) {
            if (!scheduleTable) return;
            
            try {
                scheduleTable.clear();
                
                schedule.forEach(function(entry) {
                    const statusColors = {
                        'Present': 'success',
                        'Checked In': 'info',
                        'Absent': 'danger'
                    };
                    
                    const statusBadge = `<span class="badge badge-modern bg-${statusColors[entry.status] || 'secondary'}">${entry.status}</span>`;
                    
                    const actions = `
                        <button class="btn btn-sm btn-outline-info" onclick="viewAttendanceDetails(${entry.employee_id})" title="View Details">
                            <i class="fas fa-info-circle"></i>
                        </button>
                    `;
                    
                    scheduleTable.row.add([
                        entry.employee_name || 'N/A',
                        entry.check_in ? new Date(entry.check_in).toLocaleTimeString() : 'N/A',
                        entry.check_out ? new Date(entry.check_out).toLocaleTimeString() : 'N/A',
                        statusBadge,
                        actions
                    ]);
                });
                
                scheduleTable.draw();
            } catch (error) {
                console.error('Error displaying team schedule:', error);
            }
        }
        
        function showLeaveAction(leaveId, action) {
            $('#leaveId').val(leaveId);
            $('#actionType').val(action);
            $('#actionMessage').text(`You are about to ${action} this leave request.`);
            $('#leaveActionModal').modal('show');
        }
        
        function submitLeaveAction() {
            const formData = $('#leaveActionForm').serialize() + '&action=update_leave_status';
            
            $.ajax({
                url: 'team_manager_console.php',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert(response.message, 'success');
                        $('#leaveActionModal').modal('hide');
                        loadPendingLeaves();
                    } else {
                        showAlert('Error: ' + response.message, 'danger');
                    }
                },
                error: function() {
                    showAlert('Failed to process leave request', 'danger');
                }
            });
        }
        
        function toggleSelectAll() {
            const selectAll = $('#selectAll').prop('checked');
            $('.leave-checkbox').prop('checked', selectAll);
        }
        
        function bulkApprove() {
            const selectedLeaves = $('.leave-checkbox:checked').map(function() {
                return this.value;
            }).get();
            
            if (selectedLeaves.length === 0) {
                showAlert('Please select at least one leave request', 'warning');
                return;
            }
            
            if (confirm(`Are you sure you want to approve ${selectedLeaves.length} leave request(s)?`)) {
                // Process bulk approval
                showAlert('Bulk approval functionality coming soon!', 'info');
            }
        }
        
        function viewMemberDetails(id) {
            showAlert('Member details view coming soon!', 'info');
        }
        
        function viewAttendance(id) {
            showAlert('Attendance view coming soon!', 'info');
        }
        
        function viewLeaveDetails(id) {
            showAlert('Leave details view coming soon!', 'info');
        }
        
        function viewAttendanceDetails(id) {
            showAlert('Attendance details view coming soon!', 'info');
        }
        
        function refreshData() {
            loadTeamMembers();
            loadPendingLeaves();
            loadTeamSchedule();
            showAlert('Data refreshed successfully', 'success');
        }
        
        function exportReports() {
            showAlert('Export functionality coming soon!', 'info');
        }

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
