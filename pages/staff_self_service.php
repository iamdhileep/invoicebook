<?php
/**
 * Staff Self Service Portal - Employee-specific HR functionalities
 * Direct pages folder implementation with full functionality
 */
session_start();

// Initialize session for demo
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['employee_id'] = 24; // Dhileepkumar's employee_id
    $_SESSION['role'] = 'employee';
}

require_once '../db.php';
$page_title = 'Staff Self Service Portal - HRMS';

// Get employee information
$employee_info = null;
try {
    $stmt = $conn->prepare("SELECT 
                           employee_id,
                           COALESCE(name, CONCAT(first_name, ' ', last_name)) as full_name,
                           employee_code,
                           position,
                           department_name,
                           email,
                           phone,
                           hire_date,
                           status
                           FROM employees 
                           WHERE employee_id = ?");
    $stmt->bind_param("i", $_SESSION['employee_id']);
    $stmt->execute();
    $employee_info = $stmt->get_result()->fetch_assoc();
} catch (Exception $e) {
    error_log("Error fetching employee info: " . $e->getMessage());
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_attendance':
            try {
                $employee_id = $_SESSION['employee_id'];
                $start_date = $_POST['start_date'] ?? date('Y-m-01');
                $end_date = $_POST['end_date'] ?? date('Y-m-t');
                
                $query = $conn->prepare("SELECT 
                                        DATE(check_in) as attendance_date,
                                        TIME(check_in) as check_in_time,
                                        TIME(check_out) as check_out_time,
                                        CASE 
                                            WHEN check_out IS NOT NULL THEN 
                                                CONCAT(FLOOR(TIMESTAMPDIFF(MINUTE, check_in, check_out) / 60), 'h ', 
                                                      MOD(TIMESTAMPDIFF(MINUTE, check_in, check_out), 60), 'm')
                                            ELSE 'N/A'
                                        END as working_hours,
                                        '0h 0m' as break_hours,
                                        CASE 
                                            WHEN check_out IS NOT NULL THEN 'Present'
                                            WHEN check_in IS NOT NULL THEN 'Checked In'
                                            ELSE 'Absent'
                                        END as status,
                                        'Regular attendance' as notes
                                        FROM attendance 
                                        WHERE employee_id = ? 
                                        AND DATE(check_in) BETWEEN ? AND ?
                                        ORDER BY attendance_date DESC");
                $query->bind_param("iss", $employee_id, $start_date, $end_date);
                $query->execute();
                $result = $query->get_result();
                
                $attendance = [];
                while ($row = $result->fetch_assoc()) {
                    $attendance[] = $row;
                }
                
                echo json_encode(['success' => true, 'data' => $attendance]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        case 'submit_advanced_leave_request':
            try {
                $employee_id = $_SESSION['employee_id'];
                $from_date = $_POST['from_date'];
                $to_date = $_POST['to_date'];
                $leave_type = $_POST['leave_type'];
                $reason = $_POST['reason'];
                $documents = $_POST['documents'] ?? null;
                
                // Validate leave policy using our advanced API
                $policy_validation = file_get_contents('http://localhost/billbook/api/advanced_hrms_api.php', false, stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => 'Content-Type: application/json',
                        'content' => json_encode([
                            'action' => 'validate_leave_policy',
                            'employee_id' => $employee_id,
                            'from_date' => $from_date,
                            'to_date' => $to_date,
                            'leave_type' => $leave_type
                        ])
                    ]
                ]));
                
                $validation_result = json_decode($policy_validation, true);
                
                if (!$validation_result['valid']) {
                    echo json_encode(['success' => false, 'message' => $validation_result['message']]);
                    exit;
                }
                
                // Submit through advanced API
                $submit_response = file_get_contents('http://localhost/billbook/api/advanced_hrms_api.php', false, stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => 'Content-Type: application/json',
                        'content' => json_encode([
                            'action' => 'submit_leave_request',
                            'from_date' => $from_date,
                            'to_date' => $to_date,
                            'leave_type' => $leave_type,
                            'reason' => $reason,
                            'documents' => $documents
                        ])
                    ]
                ]));
                
                echo $submit_response;
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        case 'get_leave_balance':
            try {
                $employee_id = $_SESSION['employee_id'];
                $current_year = date('Y');
                
                $query = $conn->prepare("SELECT 
                                        leave_type,
                                        allocated_days,
                                        used_days,
                                        pending_days,
                                        available_days
                                        FROM leave_balance_tracking 
                                        WHERE employee_id = ? AND year = ?");
                $query->bind_param("ii", $employee_id, $current_year);
                $query->execute();
                $result = $query->get_result();
                
                $balances = [];
                while ($row = $result->fetch_assoc()) {
                    $balances[] = $row;
                }
                
                // If no balances exist, create default ones
                if (empty($balances)) {
                    $default_leaves = ['Annual Leave' => 21, 'Sick Leave' => 10, 'Personal Leave' => 5];
                    foreach ($default_leaves as $type => $days) {
                        $stmt = $conn->prepare("INSERT INTO leave_balance_tracking (employee_id, leave_type, allocated_days, year) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("isii", $employee_id, $type, $days, $current_year);
                        $stmt->execute();
                        
                        $balances[] = [
                            'leave_type' => $type,
                            'allocated_days' => $days,
                            'used_days' => 0,
                            'pending_days' => 0,
                            'available_days' => $days
                        ];
                    }
                }
                
                echo json_encode(['success' => true, 'data' => $balances]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        case 'get_notifications':
            try {
                $user_id = $_SESSION['user_id'];
                
                $query = $conn->prepare("SELECT 
                                        id,
                                        notification_type,
                                        title,
                                        message,
                                        priority,
                                        status,
                                        created_at
                                        FROM notification_queue 
                                        WHERE recipient_id = ? 
                                        ORDER BY created_at DESC 
                                        LIMIT 10");
                $query->bind_param("i", $user_id);
                $query->execute();
                $result = $query->get_result();
                
                $notifications = [];
                while ($row = $result->fetch_assoc()) {
                    $notifications[] = $row;
                }
                
                echo json_encode(['success' => true, 'data' => $notifications]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        case 'get_attendance':
            try {
                $employee_id = $_SESSION['employee_id'];
                $start_date = $_POST['start_date'] ?? date('Y-m-01');
                $end_date = $_POST['end_date'] ?? date('Y-m-t');
                
                $query = $conn->prepare("SELECT 
                                        DATE(check_in) as attendance_date,
                                        TIME(check_in) as check_in_time,
                                        TIME(check_out) as check_out_time,
                                        CASE 
                                            WHEN check_out IS NOT NULL THEN 
                                                CONCAT(FLOOR(TIMESTAMPDIFF(MINUTE, check_in, check_out) / 60), 'h ', 
                                                      MOD(TIMESTAMPDIFF(MINUTE, check_in, check_out), 60), 'm')
                                            ELSE 'N/A'
                                        END as working_hours,
                                        '0h 0m' as break_hours,
                                        CASE 
                                            WHEN check_out IS NOT NULL THEN 'Present'
                                            WHEN check_in IS NOT NULL THEN 'Checked In'
                                            ELSE 'Absent'
                                        END as status,
                                        'Regular attendance' as notes
                                        FROM attendance 
                                        WHERE employee_id = ? 
                                        AND DATE(check_in) BETWEEN ? AND ?
                                        ORDER BY attendance_date DESC");
                $query->bind_param("iss", $employee_id, $start_date, $end_date);
                $query->execute();
                $result = $query->get_result();
                
                $attendance = [];
                while ($row = $result->fetch_assoc()) {
                    $attendance[] = $row;
                }
                
                echo json_encode(['success' => true, 'data' => $attendance]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        case 'get_leave_requests':
            try {
                $employee_id = $_SESSION['employee_id'];
                
                $query = $conn->prepare("SELECT 
                                        id,
                                        leave_type,
                                        from_date as start_date,
                                        to_date as end_date,
                                        days_requested as total_days,
                                        status,
                                        reason,
                                        approver_comments as manager_comments,
                                        applied_date as created_at
                                        FROM leave_requests 
                                        WHERE employee_id = ?
                                        ORDER BY applied_date DESC");
                $query->bind_param("i", $employee_id);
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
            
        case 'submit_leave_request':
            try {
                $employee_id = $_SESSION['employee_id'];
                $leave_type = $_POST['leave_type'] ?? '';
                $start_date = $_POST['start_date'] ?? '';
                $end_date = $_POST['end_date'] ?? '';
                $reason = $_POST['reason'] ?? '';
                
                // Calculate total days
                $start = new DateTime($start_date);
                $end = new DateTime($end_date);
                $total_days = $end->diff($start)->days + 1;
                
                $stmt = $conn->prepare("INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, total_days, reason, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
                $stmt->bind_param("isssds", $employee_id, $leave_type, $start_date, $end_date, $total_days, $reason);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Leave request submitted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to submit leave request']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'clock_in':
            try {
                $employee_id = $_SESSION['employee_id'];
                $timestamp = $_POST['timestamp'] ?? date('Y-m-d H:i:s');
                
                // Check if already clocked in today
                $today = date('Y-m-d');
                $check = $conn->prepare("SELECT id FROM attendance WHERE employee_id = ? AND DATE(check_in) = ? AND check_out IS NULL");
                $check->bind_param("is", $employee_id, $today);
                $check->execute();
                
                if ($check->get_result()->num_rows > 0) {
                    echo json_encode(['success' => false, 'message' => 'Already clocked in today']);
                    exit;
                }
                
                $stmt = $conn->prepare("INSERT INTO attendance (employee_id, check_in) VALUES (?, ?)");
                $stmt->bind_param("is", $employee_id, $timestamp);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Clocked in successfully', 'time' => date('H:i', strtotime($timestamp))]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to clock in']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'clock_out':
            try {
                $employee_id = $_SESSION['employee_id'];
                $timestamp = $_POST['timestamp'] ?? date('Y-m-d H:i:s');
                
                // Find today's attendance record without checkout
                $today = date('Y-m-d');
                $find = $conn->prepare("SELECT id, check_in FROM attendance WHERE employee_id = ? AND DATE(check_in) = ? AND check_out IS NULL ORDER BY check_in DESC LIMIT 1");
                $find->bind_param("is", $employee_id, $today);
                $find->execute();
                $attendance = $find->get_result()->fetch_assoc();
                
                if (!$attendance) {
                    echo json_encode(['success' => false, 'message' => 'No active clock-in found for today']);
                    exit;
                }
                
                $stmt = $conn->prepare("UPDATE attendance SET check_out = ? WHERE id = ?");
                $stmt->bind_param("si", $timestamp, $attendance['id']);
                
                if ($stmt->execute()) {
                    // Calculate hours worked
                    $check_in = strtotime($attendance['check_in']);
                    $check_out = strtotime($timestamp);
                    $hours_worked = ($check_out - $check_in) / 3600;
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Clocked out successfully',
                        'time' => date('H:i', strtotime($timestamp)),
                        'hours_worked' => round($hours_worked, 2)
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to clock out']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
    }
}

// Get dashboard statistics
$stats = [
    'total_attendance' => 0,
    'pending_leaves' => 0,
    'approved_leaves' => 0,
    'hours_this_month' => 0
];

try {
    $employee_id = $_SESSION['employee_id'];
    $current_month = date('Y-m');
    
    // Get total attendance this month
    $result = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE employee_id = $employee_id AND DATE_FORMAT(check_in, '%Y-%m') = '$current_month'");
    if ($result) $stats['total_attendance'] = $result->fetch_assoc()['count'];
    
    // Get leave statistics
    try {
        $result = $conn->query("SELECT COUNT(*) as count FROM leave_requests WHERE employee_id = $employee_id AND status = 'pending'");
        if ($result) $stats['pending_leaves'] = $result->fetch_assoc()['count'];
        
        $result = $conn->query("SELECT COUNT(*) as count FROM leave_requests WHERE employee_id = $employee_id AND status = 'approved'");
        if ($result) $stats['approved_leaves'] = $result->fetch_assoc()['count'];
    } catch (Exception $e) {
        // Leave tables might not exist
    }
    
    // Get total hours this month
    $result = $conn->query("SELECT SUM(TIMESTAMPDIFF(HOUR, check_in, check_out)) as total_hours FROM attendance WHERE employee_id = $employee_id AND DATE_FORMAT(check_in, '%Y-%m') = '$current_month' AND check_out IS NOT NULL");
    if ($result) {
        $row = $result->fetch_assoc();
        $stats['hours_this_month'] = $row['total_hours'] ?? 0;
    }
    
} catch (Exception $e) {
    error_log("Staff portal stats error: " . $e->getMessage());
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
            --primary-color: #0891b2;
            --secondary-color: #64748b;
            --success-color: #059669;
            --info-color: #0891b2;
            --warning-color: #d97706;
            --danger-color: #dc2626;
            --dark-color: #1e293b;
        }
        
        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            margin: 0;
            padding: 0;
        }
        
        .staff-sidebar {
            background: linear-gradient(180deg, #0c4a6e 0%, #075985 100%);
            min-height: 100vh;
            box-shadow: 4px 0 20px rgba(0,0,0,0.15);
            position: fixed;
            width: 280px;
            z-index: 1000;
        }
        
        .staff-content {
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
            color: #bae6fd;
            text-decoration: none;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
            font-weight: 500;
        }
        
        .sidebar-link:hover,
        .sidebar-link.active {
            background: rgba(8, 145, 178, 0.1);
            color: #0891b2;
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
        
        .profile-card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            margin-right: 1.5rem;
        }
        
        .profile-info h3 {
            margin: 0 0 0.5rem 0;
            color: var(--dark-color);
            font-weight: 700;
        }
        
        .profile-info p {
            margin: 0;
            color: var(--secondary-color);
        }
        
        .clock-section {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            text-align: center;
        }
        
        .clock-display {
            font-size: 3rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .clock-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
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
        
        .btn-info.btn-modern {
            background: var(--primary-color);
            box-shadow: 0 4px 14px rgba(8, 145, 178, 0.25);
        }
        
        .btn-info.btn-modern:hover {
            background: #0e7490;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(8, 145, 178, 0.35);
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
            .staff-sidebar {
                width: 100%;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .staff-sidebar.show {
                transform: translateX(0);
            }
            
            .staff-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-avatar {
                margin: 0 0 1rem 0;
            }
            
            .clock-buttons {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="staff-sidebar">
        <div class="sidebar-header">
            <a href="#" class="sidebar-brand">
                <i class="fas fa-user"></i>
                <div>
                    <div>Staff Portal</div>
                    <small style="color: #bae6fd; font-size: 0.75rem;">Self Service</small>
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
                <a href="team_manager_console.php" class="sidebar-link">
                    <i class="fas fa-users"></i>Manager Console
                </a>
            </div>
            <div class="sidebar-item">
                <a href="#" class="sidebar-link active">
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
    <main class="staff-content">
        <!-- Profile Card -->
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="profile-info">
                    <h3><?php echo $employee_info['full_name'] ?? 'Employee Name'; ?></h3>
                    <p><?php echo $employee_info['position'] ?? 'Position'; ?> • <?php echo $employee_info['department_name'] ?? 'Department'; ?></p>
                    <p><i class="fas fa-id-badge me-2"></i>ID: <?php echo $employee_info['employee_code'] ?? 'N/A'; ?></p>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: var(--primary-color);">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['total_attendance']; ?></div>
                    <p class="stat-label">Days This Month</p>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: var(--warning-color);">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['pending_leaves']; ?></div>
                    <p class="stat-label">Pending Leaves</p>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: var(--success-color);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['approved_leaves']; ?></div>
                    <p class="stat-label">Approved Leaves</p>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: var(--info-color);">
                        <i class="fas fa-business-time"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['hours_this_month']; ?>h</div>
                    <p class="stat-label">Hours This Month</p>
                </div>
            </div>
        </div>
        
        <!-- Clock In/Out Section -->
        <div class="clock-section">
            <div class="clock-display" id="currentTime">00:00:00</div>
            <p class="text-muted mb-3"><?php echo date('l, F j, Y'); ?></p>
            <div class="clock-buttons">
                <button class="btn btn-success btn-modern btn-lg" onclick="clockIn()">
                    <i class="fas fa-sign-in-alt me-2"></i>Clock In
                </button>
                <button class="btn btn-danger btn-modern btn-lg" onclick="clockOut()">
                    <i class="fas fa-sign-out-alt me-2"></i>Clock Out
                </button>
            </div>
        </div>
        
        <!-- Main Content Tabs -->
        <div class="tab-content-card">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#attendance">
                        <i class="fas fa-clock me-2"></i>My Attendance
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#leaves">
                        <i class="fas fa-calendar-alt me-2"></i>Leave Management
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#profile">
                        <i class="fas fa-user-edit me-2"></i>Profile Settings
                    </button>
                </li>
            </ul>
            
            <div class="tab-content">
                <!-- Attendance Tab -->
                <div class="tab-pane fade show active" id="attendance" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-bold"><i class="fas fa-clock me-2 text-info"></i>My Attendance Records</h4>
                        <div>
                            <input type="date" class="form-control d-inline-block me-2" style="width: auto;" id="startDate" value="<?php echo date('Y-m-01'); ?>">
                            <input type="date" class="form-control d-inline-block me-2" style="width: auto;" id="endDate" value="<?php echo date('Y-m-t'); ?>">
                            <button class="btn btn-info btn-modern" onclick="loadAttendanceRecords()">
                                <i class="fas fa-sync-alt me-2"></i>Load Records
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-modern" id="attendanceTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Check In</th>
                                    <th>Check Out</th>
                                    <th>Working Hours</th>
                                    <th>Break Time</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Attendance records will be populated here -->
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Leave Management Tab -->
                <div class="tab-pane fade" id="leaves" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-bold"><i class="fas fa-calendar-alt me-2 text-success"></i>My Leave Requests</h4>
                        <button class="btn btn-success btn-modern" onclick="showNewLeaveModal()">
                            <i class="fas fa-plus me-2"></i>New Leave Request
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-modern" id="leavesTable">
                            <thead>
                                <tr>
                                    <th>Request ID</th>
                                    <th>Leave Type</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Days</th>
                                    <th>Status</th>
                                    <th>Manager Comments</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Leave requests will be populated here -->
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Profile Settings Tab -->
                <div class="tab-pane fade" id="profile" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-bold"><i class="fas fa-user-edit me-2 text-primary"></i>Profile Settings</h4>
                        <button class="btn btn-primary btn-modern">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Full Name</label>
                                <input type="text" class="form-control" value="<?php echo $employee_info['full_name'] ?? ''; ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Employee Code</label>
                                <input type="text" class="form-control" value="<?php echo $employee_info['employee_code'] ?? ''; ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Position</label>
                                <input type="text" class="form-control" value="<?php echo $employee_info['position'] ?? ''; ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Email Address</label>
                                <input type="email" class="form-control" value="<?php echo $employee_info['email'] ?? ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Phone Number</label>
                                <input type="tel" class="form-control" value="<?php echo $employee_info['phone'] ?? ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Department</label>
                                <input type="text" class="form-control" value="<?php echo $employee_info['department_name'] ?? ''; ?>" readonly>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- New Leave Request Modal -->
    <div class="modal fade" id="newLeaveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-calendar-plus me-2 text-success"></i>New Leave Request
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="leaveRequestForm">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Leave Type *</label>
                            <select class="form-select" name="leave_type" required>
                                <option value="">Select Leave Type</option>
                                <option value="Annual Leave">Annual Leave</option>
                                <option value="Sick Leave">Sick Leave</option>
                                <option value="Personal Leave">Personal Leave</option>
                                <option value="Emergency Leave">Emergency Leave</option>
                                <option value="Maternity Leave">Maternity Leave</option>
                                <option value="Paternity Leave">Paternity Leave</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Start Date *</label>
                                <input type="date" class="form-control" name="start_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">End Date *</label>
                                <input type="date" class="form-control" name="end_date" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Reason *</label>
                            <textarea class="form-control" name="reason" rows="3" placeholder="Please provide reason for leave" required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success btn-modern" onclick="submitLeaveRequest()">
                        <i class="fas fa-paper-plane me-2"></i>Submit Request
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
        let attendanceTable = null;
        let leavesTable = null;
        
        $(document).ready(function() {
            console.log('Staff Self Service Portal loaded');
            initializeEnhancedTables();
            loadAttendanceRecords();
            loadMyLeaveRequests();
            updateClock();
            setInterval(updateClock, 1000);
            setupAdvancedFeatures();
        });
        
        function initializeEnhancedTables() {
            try {
                // Initialize enhanced attendance table
                attendanceTable = window.hrmsTableManager.createAttendanceTable('attendanceTable', {
                    pageLength: 15,
                    order: [[0, 'desc']], // Sort by date
                    responsive: true,
                    processing: true
                });

                // Initialize enhanced leave requests table
                leavesTable = window.hrmsTableManager.createLeaveTable('leavesTable', {
                    pageLength: 15,
                    order: [[0, 'desc']], // Sort by request ID
                    responsive: true,
                    processing: true,
                    columns: [
                        { 
                            title: "Request ID", 
                            data: null,
                            render: function(data, type, row) {
                                return `<span class="fw-bold text-primary">#LR${row.id || 'N/A'}</span>`;
                            }
                        },
                        { 
                            title: "Leave Type", 
                            data: null,
                            render: function(data, type, row) {
                                const typeColors = {
                                    'Annual Leave': 'primary',
                                    'Sick Leave': 'warning',
                                    'Personal Leave': 'info',
                                    'Emergency Leave': 'danger',
                                    'Maternity Leave': 'success',
                                    'Paternity Leave': 'secondary'
                                };
                                const colorClass = typeColors[row.leave_type] || 'secondary';
                                return `<span class="badge bg-${colorClass} badge-modern">${row.leave_type || 'N/A'}</span>`;
                            }
                        },
                        { 
                            title: "Start Date", 
                            data: null,
                            render: function(data, type, row) {
                                return row.start_date ? new Date(row.start_date).toLocaleDateString() : 'N/A';
                            }
                        },
                        { 
                            title: "End Date", 
                            data: null,
                            render: function(data, type, row) {
                                return row.end_date ? new Date(row.end_date).toLocaleDateString() : 'N/A';
                            }
                        },
                        { 
                            title: "Days", 
                            data: null,
                            render: function(data, type, row) {
                                return `<span class="badge bg-light text-dark badge-modern">${row.total_days || 0} days</span>`;
                            }
                        },
                        { 
                            title: "Status", 
                            data: null,
                            render: function(data, type, row) {
                                const statusColors = {
                                    'pending': 'warning',
                                    'approved': 'success',
                                    'rejected': 'danger'
                                };
                                const colorClass = statusColors[row.status] || 'secondary';
                                return `<span class="badge bg-${colorClass} badge-modern status-indicator status-${row.status}">${row.status || 'N/A'}</span>`;
                            }
                        },
                        { 
                            title: "Manager Comments", 
                            data: null,
                            render: function(data, type, row) {
                                return row.manager_comments ? `<span class="text-muted">${row.manager_comments}</span>` : '<span class="text-muted">No comments</span>';
                            }
                        },
                        { 
                            title: "Actions", 
                            data: null,
                            orderable: false,
                            render: function(data, type, row) {
                                const actions = [`<button class="btn btn-sm btn-outline-primary btn-action" onclick="viewMyLeaveDetails(${row.id})" title="View Details"><i class="fas fa-eye"></i></button>`];
                                
                                if (row.status === 'pending') {
                                    actions.push(`<button class="btn btn-sm btn-outline-warning btn-action" onclick="editMyLeaveRequest(${row.id})" title="Edit"><i class="fas fa-edit"></i></button>`);
                                    actions.push(`<button class="btn btn-sm btn-outline-danger btn-action" onclick="cancelMyLeaveRequest(${row.id})" title="Cancel"><i class="fas fa-times"></i></button>`);
                                }
                                
                                actions.push(`<button class="btn btn-sm btn-outline-info btn-action" onclick="printMyLeaveRequest(${row.id})" title="Print"><i class="fas fa-print"></i></button>`);
                                
                                return `<div class="btn-group" role="group">${actions.join('')}</div>`;
                            }
                        }
                    ]
                });
                
                console.log('Enhanced staff tables initialized successfully');
            } catch (error) {
                console.error('Enhanced DataTables initialization error:', error);
                // Fallback to basic tables
                initializeBasicTables();
            }
        }
        
        function initializeBasicTables() {
            try {
                if ($.fn.DataTable.isDataTable('#attendanceTable')) {
                    $('#attendanceTable').DataTable().destroy();
                }
                if ($.fn.DataTable.isDataTable('#leavesTable')) {
                    $('#leavesTable').DataTable().destroy();
                }

                attendanceTable = $('#attendanceTable').DataTable({
                    responsive: true,
                    pageLength: 15,
                    order: [[0, 'desc']],
                    columns: [
                        { title: "Date", data: null },
                        { title: "Check In", data: null },
                        { title: "Check Out", data: null },
                        { title: "Working Hours", data: null },
                        { title: "Break Time", data: null },
                        { title: "Status", data: null },
                        { title: "Notes", data: null }
                    ],
                    language: { emptyTable: "No attendance records found" }
                });

                leavesTable = $('#leavesTable').DataTable({
                    responsive: true,
                    pageLength: 15,
                    order: [[0, 'desc']],
                    columns: [
                        { title: "Request ID", data: null },
                        { title: "Leave Type", data: null },
                        { title: "Start Date", data: null },
                        { title: "End Date", data: null },
                        { title: "Days", data: null },
                        { title: "Status", data: null },
                        { title: "Manager Comments", data: null },
                        { title: "Actions", data: null, orderable: false }
                    ],
                    language: { emptyTable: "No leave requests found" }
                });
            } catch (error) {
                console.error('DataTables initialization error:', error);
            }
        }
        
        function setupAdvancedFeatures() {
            // Add keyboard shortcuts
            $(document).on('keydown', function(e) {
                if (e.ctrlKey) {
                    switch (e.key) {
                        case 'i': // Ctrl+I for Clock In
                            e.preventDefault();
                            clockIn();
                            break;
                        case 'o': // Ctrl+O for Clock Out
                            e.preventDefault();
                            clockOut();
                            break;
                        case 'l': // Ctrl+L for Leave Request
                            e.preventDefault();
                            showNewLeaveModal();
                            break;
                    }
                }
            });
            
            // Setup auto-refresh
            setInterval(function() {
                loadAttendanceRecords();
                loadMyLeaveRequests();
            }, 300000); // 5 minutes
        }
        
        function loadAttendanceRecords() {
            const startDate = $('#startDate').val();
            const endDate = $('#endDate').val();
            
            $.ajax({
                url: 'staff_self_service.php',
                method: 'POST',
                data: { 
                    action: 'get_attendance',
                    start_date: startDate,
                    end_date: endDate
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data) {
                        displayAttendanceRecords(response.data);
                        showAlert('Attendance records loaded (' + response.data.length + ' records)', 'success');
                    } else {
                        displayAttendanceRecords([]);
                    }
                },
                error: function() {
                    displayAttendanceRecords([]);
                }
            });
        }
        
        function displayAttendanceRecords(records) {
            if (!attendanceTable) return;
            
            try {
                attendanceTable.clear();
                
                records.forEach(function(record) {
                    const statusColors = {
                        'Present': 'success',
                        'Checked In': 'info',
                        'Absent': 'danger',
                        'Half Day': 'warning'
                    };
                    
                    const statusBadge = `<span class="badge badge-modern bg-${statusColors[record.status] || 'secondary'}">${record.status}</span>`;
                    
                    attendanceTable.row.add([
                        record.attendance_date || 'N/A',
                        record.check_in_time || 'N/A',
                        record.check_out_time || 'N/A',
                        record.working_hours || '0 hrs',
                        record.break_hours || '0 hrs',
                        statusBadge,
                        record.notes || '-'
                    ]);
                });
                
                attendanceTable.draw();
            } catch (error) {
                console.error('Error displaying attendance records:', error);
            }
        }
        
        function loadMyLeaveRequests() {
            $.ajax({
                url: 'staff_self_service.php',
                method: 'POST',
                data: { action: 'get_leave_requests' },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data) {
                        displayLeaveRequests(response.data);
                    } else {
                        displayLeaveRequests([]);
                    }
                },
                error: function() {
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
                    
                    const statusBadge = `<span class="badge badge-modern bg-${statusColors[request.status] || 'secondary'}">${request.status}</span>`;
                    
                    const actions = `
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-outline-info" onclick="viewLeaveDetails(${request.id})" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            ${request.status === 'pending' ? `<button class="btn btn-sm btn-outline-danger" onclick="cancelLeaveRequest(${request.id})" title="Cancel"><i class="fas fa-times"></i></button>` : ''}
                        </div>
                    `;
                    
                    leavesTable.row.add([
                        request.id || 'N/A',
                        request.leave_type || 'N/A',
                        request.start_date || 'N/A',
                        request.end_date || 'N/A',
                        request.total_days || '0',
                        statusBadge,
                        request.manager_comments || '-',
                        actions
                    ]);
                });
                
                leavesTable.draw();
            } catch (error) {
                console.error('Error displaying leave requests:', error);
            }
        }
        
        function clockIn() {
            const timestamp = new Date().toISOString().slice(0, 19).replace('T', ' ');
            
            $.ajax({
                url: 'staff_self_service.php',
                method: 'POST',
                data: { 
                    action: 'clock_in',
                    timestamp: timestamp
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert(`Clocked in successfully at ${response.time}`, 'success');
                        loadAttendanceRecords();
                    } else {
                        showAlert('Clock in failed: ' + response.message, 'danger');
                    }
                },
                error: function() {
                    showAlert('Clock in failed', 'danger');
                }
            });
        }
        
        function clockOut() {
            const timestamp = new Date().toISOString().slice(0, 19).replace('T', ' ');
            
            $.ajax({
                url: 'staff_self_service.php',
                method: 'POST',
                data: { 
                    action: 'clock_out',
                    timestamp: timestamp
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert(`Clocked out successfully at ${response.time}. Total hours: ${response.hours_worked}`, 'success');
                        loadAttendanceRecords();
                    } else {
                        showAlert('Clock out failed: ' + response.message, 'danger');
                    }
                },
                error: function() {
                    showAlert('Clock out failed', 'danger');
                }
            });
        }
        
        function showNewLeaveModal() {
            $('#newLeaveModal').modal('show');
        }
        
        function submitLeaveRequest() {
            const formData = $('#leaveRequestForm').serialize();
            
            $.ajax({
                url: 'staff_self_service.php',
                method: 'POST',
                data: formData + '&action=submit_leave_request',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert('Leave request submitted successfully!', 'success');
                        $('#newLeaveModal').modal('hide');
                        $('#leaveRequestForm')[0].reset();
                        loadMyLeaveRequests();
                    } else {
                        showAlert('Error: ' + response.message, 'danger');
                    }
                },
                error: function() {
                    showAlert('Failed to submit leave request', 'danger');
                }
            });
        }
        
        function viewLeaveDetails(id) {
            showAlert('Leave details view coming soon!', 'info');
        }
        
        function cancelLeaveRequest(id) {
            if (confirm('Are you sure you want to cancel this leave request?')) {
                showAlert('Cancel functionality coming soon!', 'info');
            }
        }
        
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString();
            $('#currentTime').text(timeString);
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
