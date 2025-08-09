<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Use absolute paths to avoid any path issues
$base_dir = dirname(__DIR__);
require_once $base_dir . '/db.php';
require_once $base_dir . '/auth_check.php';

// Set compatibility variables for HRMS modules
if (isset($_SESSION['user']) && !isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = $_SESSION['user']['id'] ?? $_SESSION['user']['user_id'] ?? 1;
}

$current_user_id = $_SESSION['user_id'] ?? 1;
$current_user = $_SESSION['user']['name'] ?? $_SESSION['user']['username'] ?? 'Admin';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['action'];
        
        switch ($action) {
            
            case 'add_fnf_settlement':
                if (!$_POST['employee_id'] || !$_POST['last_working_day']) {
                    throw new Exception('Employee ID and last working day are required');
                }

                // Calculate notice period details
                $last_working_day = $_POST['last_working_day'];
                $resignation_date = $_POST['resignation_date'] ?: $last_working_day;
                $notice_period_days = $_POST['notice_period_days'] ?: 30;

                $stmt = $conn->prepare("
                    INSERT INTO fnf_settlements (
                        employee_id, employee_name, employee_code, department_name,
                        basic_salary, resignation_date, last_working_day, notice_period_days,
                        status, initiated_by, initiated_date, remarks
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'initiated', ?, NOW(), ?)
                ");
                
                $stmt->bind_param('isssdssiis',
                    $_POST['employee_id'],
                    $_POST['employee_name'],
                    $_POST['employee_code'],
                    $_POST['department_name'],
                    $_POST['basic_salary'],
                    $resignation_date,
                    $last_working_day,
                    $notice_period_days,
                    $current_user_id,
                    $_POST['remarks']
                );

                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'FNF settlement initiated successfully']);
                } else {
                    throw new Exception('Failed to initiate FNF settlement');
                }
                break;

            case 'calculate_settlement':
                if (!$_POST['id']) {
                    throw new Exception('Settlement ID is required');
                }

                $id = $_POST['id'];
                $pending_salary = $_POST['pending_salary'] ?: 0;
                $leave_encashment = $_POST['leave_encashment'] ?: 0;
                $bonus_amount = $_POST['bonus_amount'] ?: 0;
                $gratuity_amount = $_POST['gratuity_amount'] ?: 0;
                $other_benefits = $_POST['other_benefits'] ?: 0;
                $loan_deductions = $_POST['loan_deductions'] ?: 0;
                $advance_deductions = $_POST['advance_deductions'] ?: 0;
                $other_deductions = $_POST['other_deductions'] ?: 0;

                // Calculate totals
                $gross_settlement = $pending_salary + $leave_encashment + $bonus_amount + $gratuity_amount + $other_benefits;
                $total_deductions = $loan_deductions + $advance_deductions + $other_deductions;
                $net_settlement = $gross_settlement - $total_deductions;

                $stmt = $conn->prepare("
                    UPDATE fnf_settlements SET
                        pending_salary = ?, leave_encashment = ?, bonus_amount = ?, 
                        gratuity_amount = ?, other_benefits = ?, loan_deductions = ?,
                        advance_deductions = ?, other_deductions = ?, 
                        gross_settlement = ?, net_settlement = ?, status = 'calculated'
                    WHERE id = ?
                ");

                $stmt->bind_param('ddddddddddi',
                    $pending_salary, $leave_encashment, $bonus_amount,
                    $gratuity_amount, $other_benefits, $loan_deductions,
                    $advance_deductions, $other_deductions,
                    $gross_settlement, $net_settlement, $id
                );

                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Settlement calculated successfully']);
                } else {
                    throw new Exception('Failed to calculate settlement');
                }
                break;

            case 'approve_settlement':
                if (!$_POST['id'] || !$_POST['status']) {
                    throw new Exception('Settlement ID and status are required');
                }

                $stmt = $conn->prepare("
                    UPDATE fnf_settlements SET
                        status = ?, approved_by = ?, approved_date = NOW(), remarks = ?
                    WHERE id = ?
                ");

                $stmt->bind_param('sisi',
                    $_POST['status'],
                    $current_user_id,
                    $_POST['remarks'],
                    $_POST['id']
                );

                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Settlement status updated successfully']);
                } else {
                    throw new Exception('Failed to update settlement status');
                }
                break;

            case 'mark_payment':
                if (!$_POST['id'] || !$_POST['payment_date']) {
                    throw new Exception('Settlement ID and payment date are required');
                }

                $stmt = $conn->prepare("
                    UPDATE fnf_settlements SET
                        payment_date = ?, payment_remarks = ?, status = 'completed'
                    WHERE id = ?
                ");

                $stmt->bind_param('ssi',
                    $_POST['payment_date'],
                    $_POST['payment_remarks'],
                    $_POST['id']
                );

                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Payment marked successfully']);
                } else {
                    throw new Exception('Failed to mark payment');
                }
                break;

            case 'get_settlement':
                if (!$_POST['id']) {
                    throw new Exception('Settlement ID is required');
                }

                $stmt = $conn->prepare("SELECT * FROM fnf_settlements WHERE id = ?");
                $stmt->bind_param('i', $_POST['id']);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($settlement = $result->fetch_assoc()) {
                    echo json_encode(['success' => true, 'data' => $settlement]);
                } else {
                    throw new Exception('Settlement not found');
                }
                break;

            case 'schedule_exit_interview':
                if (!$_POST['settlement_id'] || !$_POST['interview_date'] || !$_POST['interviewer_name']) {
                    throw new Exception('Settlement ID, interview date, and interviewer name are required');
                }

                $stmt = $conn->prepare("
                    INSERT INTO exit_interviews (
                        settlement_id, interview_date, interviewer_name, status, created_date
                    ) VALUES (?, ?, ?, 'scheduled', NOW())
                ");

                $stmt->bind_param('iss',
                    $_POST['settlement_id'],
                    $_POST['interview_date'],
                    $_POST['interviewer_name']
                );

                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Exit interview scheduled successfully']);
                } else {
                    throw new Exception('Failed to schedule exit interview');
                }
                break;

            case 'create_clearance_checklist':
                if (!$_POST['settlement_id']) {
                    throw new Exception('Settlement ID is required');
                }

                $settlement_id = $_POST['settlement_id'];

                // Get settlement details
                $stmt = $conn->prepare("SELECT employee_name, department_name FROM fnf_settlements WHERE id = ?");
                $stmt->bind_param('i', $settlement_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $settlement = $result->fetch_assoc();

                if (!$settlement) {
                    throw new Exception('Settlement not found');
                }

                // Predefined clearance steps
                $clearance_steps = [
                    'HR - Return ID card and office keys',
                    'HR - Complete exit interview',
                    'IT - Return laptop and accessories',
                    'IT - Account deactivation',
                    'Finance - Clear pending dues',
                    'Finance - Return company credit card',
                    'Admin - Return office supplies',
                    'Admin - Update employee records',
                    'Manager - Project handover',
                    'Manager - Final approval'
                ];

                foreach ($clearance_steps as $step_description) {
                    $stmt = $conn->prepare("
                        INSERT INTO clearance_steps (
                            settlement_id, employee_name, department_name, 
                            step_description, status, created_date
                        ) VALUES (?, ?, ?, ?, 'pending', NOW())
                    ");

                    $stmt->bind_param('isss',
                        $settlement_id,
                        $settlement['employee_name'],
                        $settlement['department_name'],
                        $step_description
                    );

                    $stmt->execute();
                }

                echo json_encode(['success' => true, 'message' => 'Clearance checklist created successfully']);
                break;

            case 'delete_settlement':
                if (!$_POST['id']) {
                    throw new Exception('Settlement ID is required');
                }

                // Delete related records first
                $conn->query("DELETE FROM clearance_steps WHERE settlement_id = " . intval($_POST['id']));
                $conn->query("DELETE FROM exit_interviews WHERE settlement_id = " . intval($_POST['id']));

                $stmt = $conn->prepare("DELETE FROM fnf_settlements WHERE id = ?");
                $stmt->bind_param('i', $_POST['id']);

                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'FNF settlement deleted successfully']);
                } else {
                    throw new Exception('Failed to delete settlement');
                }
                break;

            default:
                throw new Exception('Invalid action');
        }

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    
    exit;
}

// Handle report generation
if (isset($_GET['generate_report'])) {
    $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
    $dateTo = $_GET['date_to'] ?? date('Y-m-t');
    
    $report_query = "
        SELECT fs.*, 
               DATE_FORMAT(fs.initiated_date, '%d-%m-%Y') as initiated_date_formatted,
               DATE_FORMAT(fs.approved_date, '%d-%m-%Y') as approved_date_formatted,
               DATE_FORMAT(fs.payment_date, '%d-%m-%Y') as payment_date_formatted
        FROM fnf_settlements fs 
        WHERE DATE(fs.initiated_date) BETWEEN '$dateFrom' AND '$dateTo'
        ORDER BY fs.initiated_date DESC
    ";
    
    $report_result = $conn->query($report_query);
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="fnf_settlements_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'Settlement ID', 'Employee Name', 'Employee Code', 'Department',
        'Basic Salary', 'Resignation Date', 'Last Working Day', 'Notice Period Days',
        'Gross Settlement', 'Net Settlement', 'Status', 'Initiated Date',
        'Approved Date', 'Payment Date', 'Remarks'
    ]);
    
    while ($row = $report_result->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['employee_name'],
            $row['employee_code'],
            $row['department_name'],
            $row['basic_salary'],
            $row['resignation_date'],
            $row['last_working_day'],
            $row['notice_period_days'],
            $row['gross_settlement'],
            $row['net_settlement'],
            $row['status'],
            $row['initiated_date_formatted'],
            $row['approved_date_formatted'],
            $row['payment_date_formatted'],
            $row['remarks']
        ]);
    }
    
    fclose($output);
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_fnf_settlement':
            $employee_id = intval($_POST['employee_id']);
            $employee_name = mysqli_real_escape_string($conn, $_POST['employee_name']);
            $employee_code = mysqli_real_escape_string($conn, $_POST['employee_code']);
            $department_name = mysqli_real_escape_string($conn, $_POST['department_name']);
            $last_working_day = mysqli_real_escape_string($conn, $_POST['last_working_day']);
            $resignation_date = mysqli_real_escape_string($conn, $_POST['resignation_date']);
            $notice_period_days = intval($_POST['notice_period_days']);
            $basic_salary = floatval($_POST['basic_salary']);
            $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);
            
            $query = "INSERT INTO fnf_settlements 
                     (employee_id, employee_name, employee_code, department_name, last_working_day, 
                      resignation_date, notice_period_days, basic_salary, initiated_by, initiated_date, remarks) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?)";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("isssssidss", $employee_id, $employee_name, $employee_code, $department_name, 
                             $last_working_day, $resignation_date, $notice_period_days, $basic_salary, $current_user, $remarks);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'FNF settlement initiated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to initiate FNF settlement: ' . $conn->error]);
            }
            exit;
            
        case 'calculate_settlement':
            $id = intval($_POST['id']);
            $pending_salary = floatval($_POST['pending_salary']);
            $leave_encashment = floatval($_POST['leave_encashment']);
            $bonus_amount = floatval($_POST['bonus_amount']);
            $gratuity_amount = floatval($_POST['gratuity_amount']);
            $other_benefits = floatval($_POST['other_benefits']);
            $loan_deductions = floatval($_POST['loan_deductions']);
            $advance_deductions = floatval($_POST['advance_deductions']);
            $other_deductions = floatval($_POST['other_deductions']);
            
            // Calculate totals
            $gross_settlement = $pending_salary + $leave_encashment + $bonus_amount + $gratuity_amount + $other_benefits;
            $total_deductions = $loan_deductions + $advance_deductions + $other_deductions;
            $net_settlement = $gross_settlement - $total_deductions;
            
            $query = "UPDATE fnf_settlements 
                     SET pending_salary = ?, leave_encashment = ?, bonus_amount = ?, gratuity_amount = ?, 
                         other_benefits = ?, loan_deductions = ?, advance_deductions = ?, other_deductions = ?,
                         gross_settlement = ?, net_settlement = ?, status = 'calculated'
                     WHERE id = ?";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ddddddddddi", $pending_salary, $leave_encashment, $bonus_amount, $gratuity_amount,
                             $other_benefits, $loan_deductions, $advance_deductions, $other_deductions,
                             $gross_settlement, $net_settlement, $id);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Settlement calculated successfully',
                    'gross_settlement' => $gross_settlement,
                    'net_settlement' => $net_settlement
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to calculate settlement: ' . $conn->error]);
            }
            exit;
            
        case 'approve_settlement':
            $id = intval($_POST['id']);
            $status = mysqli_real_escape_string($conn, $_POST['status']);
            $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);
            
            $query = "UPDATE fnf_settlements 
                     SET status = ?, approved_by = ?, approved_date = CURDATE(), remarks = ?
                     WHERE id = ?";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssi", $status, $current_user, $remarks, $id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Settlement status updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update settlement status: ' . $conn->error]);
            }
            exit;
            
        case 'mark_payment':
            $id = intval($_POST['id']);
            $payment_date = mysqli_real_escape_string($conn, $_POST['payment_date']);
            $payment_remarks = mysqli_real_escape_string($conn, $_POST['payment_remarks']);
            
            $query = "UPDATE fnf_settlements 
                     SET status = 'paid', payment_date = ?, remarks = CONCAT(IFNULL(remarks, ''), '\nPayment: ', ?)
                     WHERE id = ?";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssi", $payment_date, $payment_remarks, $id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Payment marked successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to mark payment: ' . $conn->error]);
            }
            exit;
            
        case 'delete_settlement':
            $id = intval($_POST['id']);
            
            $query = "DELETE FROM fnf_settlements WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'FNF settlement deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete settlement: ' . $conn->error]);
            }
            exit;
            
        case 'get_settlement':
            $id = intval($_POST['id']);
            
            $query = "SELECT * FROM fnf_settlements WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                echo json_encode(['success' => true, 'data' => $row]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Settlement not found']);
            }
            exit;
            
        case 'schedule_exit_interview':
            $settlement_id = intval($_POST['settlement_id']);
            $interview_date = mysqli_real_escape_string($conn, $_POST['interview_date']);
            $interviewer_name = mysqli_real_escape_string($conn, $_POST['interviewer_name']);
            
            // Get employee details from settlement
            $settlement_query = "SELECT employee_id, employee_name, employee_code, department_name, last_working_day, resignation_date FROM fnf_settlements WHERE id = ?";
            $stmt = $conn->prepare($settlement_query);
            $stmt->bind_param("i", $settlement_id);
            $stmt->execute();
            $settlement = $stmt->get_result()->fetch_assoc();
            
            if ($settlement) {
                $query = "INSERT INTO exit_interviews 
                         (employee_id, employee_name, employee_code, department_name, last_working_day, resignation_date, 
                          interview_date, interviewer_name, interview_status) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')";
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param("isssssss", $settlement['employee_id'], $settlement['employee_name'], 
                                 $settlement['employee_code'], $settlement['department_name'], 
                                 $settlement['last_working_day'], $settlement['resignation_date'],
                                 $interview_date, $interviewer_name);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Exit interview scheduled successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to schedule exit interview: ' . $conn->error]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Settlement not found']);
            }
            exit;
            
        case 'create_clearance_checklist':
            $settlement_id = intval($_POST['settlement_id']);
            
            // Get employee details
            $settlement_query = "SELECT employee_id FROM fnf_settlements WHERE id = ?";
            $stmt = $conn->prepare($settlement_query);
            $stmt->bind_param("i", $settlement_id);
            $stmt->execute();
            $settlement = $stmt->get_result()->fetch_assoc();
            
            if ($settlement) {
                $employee_id = $settlement['employee_id'];
                
                // Default clearance steps
                $clearance_steps = [
                    ['IT Department - Return laptop/equipment', 'IT', 1],
                    ['HR Department - Complete documentation', 'HR', 1],
                    ['Finance Department - Clear advances/loans', 'Finance', 1],
                    ['Admin Department - Return ID card/access card', 'Admin', 1],
                    ['Direct Manager - Handover responsibilities', 'Management', 1],
                    ['Security - Return security passes', 'Security', 1],
                    ['Library - Return books/materials', 'Library', 1]
                ];
                
                foreach ($clearance_steps as $step) {
                    $query = "INSERT INTO clearance_steps (employee_id, step_name, department, assigned_to, due_date) 
                             VALUES (?, ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 7 DAY))";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("issi", $employee_id, $step[0], $step[1], $step[2]);
                    $stmt->execute();
                }
                
                echo json_encode(['success' => true, 'message' => 'Clearance checklist created successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Settlement not found']);
            }
            exit;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
    }
}

// Fetch employees for dropdown
$employees_query = "SELECT e.id, CONCAT(e.first_name, ' ', e.last_name) as full_name, e.employee_code, d.department_name, e.salary
                   FROM hr_employees e 
                   LEFT JOIN hr_departments d ON e.department_id = d.id 
                   WHERE e.status = 'active'
                   ORDER BY e.first_name, e.last_name";
$employees_result = $conn->query($employees_query);

// Fetch FNF settlements with filters
$filter_status = $_GET['status'] ?? '';
$search_query = $_GET['search'] ?? '';

$where_conditions = [];
$params = [];
$types = '';

if ($filter_status && $filter_status !== 'all') {
    $where_conditions[] = "status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

if ($search_query) {
    $where_conditions[] = "(employee_name LIKE ? OR employee_code LIKE ? OR department_name LIKE ?)";
    $search_param = '%' . $search_query . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

$where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);
$settlements_query = "SELECT * FROM fnf_settlements $where_clause ORDER BY created_at DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($settlements_query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $settlements_result = $stmt->get_result();
} else {
    $settlements_result = $conn->query($settlements_query);
}

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'calculated' THEN 1 ELSE 0 END) as calculated,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid,
    SUM(net_settlement) as total_settlement_amount
    FROM fnf_settlements";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FNF Settlement Management - HRMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .status-pending { background-color: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .status-calculated { background-color: #e2e3e5; color: #41464b; border: 1px solid #d6d8db; }
        .status-approved { background-color: #d1edff; color: #0c5460; border: 1px solid #b6effb; }
        .status-rejected { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .status-completed { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status-paid { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        
        .stats-card {
            transition: transform 0.2s;
            border: none !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1) !important;
            background: white;
            position: relative;
            z-index: 5;
        }
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
        }
        
        .fnf-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            border-top: 2px solid #007bff;
        }
        
        .action-buttons .btn {
            margin: 0 2px;
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        .main-content-wrapper {
            margin-left: 280px;
            padding: 20px;
            background-color: #f4f6f9;
            min-height: 100vh;
            position: relative;
            z-index: 1;
            padding-top: 100px;
        }
        
        .header-section {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            padding: 25px 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            position: relative;
            z-index: 100;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .button-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end;
        }
        
        .button-group .btn {
            white-space: nowrap;
            min-width: auto;
            font-size: 14px;
            padding: 10px 16px;
            font-weight: 500;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
        }
        
        .button-group .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .settlement-amount {
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .amount-positive {
            color: #28a745;
        }
        
        .amount-negative {
            color: #dc3545;
        }
        
        @media (max-width: 768px) {
            .main-content-wrapper {
                margin-left: 0;
                padding: 15px;
                padding-top: 120px;
            }
            
            .header-section {
                padding: 15px;
                margin-bottom: 15px;
            }
            
            .d-flex.justify-content-between {
                flex-direction: column;
                gap: 15px;
                align-items: stretch !important;
            }
            
            .header-text {
                text-align: center;
                margin-bottom: 15px;
            }
            
            .button-group {
                justify-content: center;
                width: 100%;
            }
            
            .button-group .btn {
                flex: 1;
                min-width: 100px;
                max-width: 140px;
            }
        }
    </style>
</head>
<body>
    <?php include $base_dir . '/layouts/header.php'; ?>
    <?php include $base_dir . '/layouts/sidebar.php'; ?>
    
    <div class="main-content-wrapper">
        <div class="container-fluid">
            <!-- Header Section -->
            <div class="header-section">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="header-text">
                        <h1 class="h3 mb-1">💰 Full & Final Settlement</h1>
                        <p class="text-muted mb-0">Manage employee exit settlements and clearance</p>
                    </div>
                    <div class="button-group">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSettlementModal">
                            <i class="fas fa-plus"></i> <span class="d-none d-sm-inline">New Settlement</span>
                        </button>
                        <button class="btn btn-info" id="generateReportBtn">
                            <i class="fas fa-file-export"></i> <span class="d-none d-sm-inline">Export Report</span>
                        </button>
                        <button class="btn btn-success" id="bulkProcessBtn" disabled>
                            <i class="fas fa-check-double"></i> <span class="d-none d-sm-inline">Bulk Process</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-2 col-md-4 col-6 mb-3">
                    <div class="card stats-card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="text-primary mb-2">
                                <i class="fas fa-file-invoice-dollar fa-2x"></i>
                            </div>
                            <h3 class="mb-1 text-primary"><?= $stats['total'] ?></h3>
                            <p class="text-muted mb-0 small">Total Settlements</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-6 mb-3">
                    <div class="card stats-card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="text-warning mb-2">
                                <i class="fas fa-hourglass-half fa-2x"></i>
                            </div>
                            <h3 class="mb-1 text-warning"><?= $stats['pending'] ?></h3>
                            <p class="text-muted mb-0 small">Pending</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-6 mb-3">
                    <div class="card stats-card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="text-info mb-2">
                                <i class="fas fa-calculator fa-2x"></i>
                            </div>
                            <h3 class="mb-1 text-info"><?= $stats['calculated'] ?></h3>
                            <p class="text-muted mb-0 small">Calculated</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-6 mb-3">
                    <div class="card stats-card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="text-success mb-2">
                                <i class="fas fa-check-circle fa-2x"></i>
                            </div>
                            <h3 class="mb-1 text-success"><?= $stats['approved'] ?></h3>
                            <p class="text-muted mb-0 small">Approved</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-6 mb-3">
                    <div class="card stats-card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="text-success mb-2">
                                <i class="fas fa-money-bill-wave fa-2x"></i>
                            </div>
                            <h3 class="mb-1 text-success"><?= $stats['paid'] ?></h3>
                            <p class="text-muted mb-0 small">Paid</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-6 mb-3">
                    <div class="card stats-card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="text-primary mb-2">
                                <i class="fas fa-rupee-sign fa-2x"></i>
                            </div>
                            <h4 class="mb-1 text-primary">₹<?= number_format($stats['total_settlement_amount'], 0) ?></h4>
                            <p class="text-muted mb-0 small">Total Amount</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="status" class="form-label">Status</label>
                            <select name="status" id="status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="calculated" <?= $filter_status === 'calculated' ? 'selected' : '' ?>>Calculated</option>
                                <option value="approved" <?= $filter_status === 'approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="rejected" <?= $filter_status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="paid" <?= $filter_status === 'paid' ? 'selected' : '' ?>>Paid</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" name="search" id="search" class="form-control" 
                                   placeholder="Search by employee name, code, department..." value="<?= htmlspecialchars($search_query) ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="fnf_settlement.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- FNF Settlements Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">FNF Settlement Records</h5>
                        <small class="text-muted">Total: <?= $settlements_result->num_rows ?> records</small>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover fnf-table">
                            <thead>
                                <tr>
                                    <th width="40">
                                        <input type="checkbox" id="selectAll" class="form-check-input">
                                    </th>
                                    <th>Employee</th>
                                    <th>Department</th>
                                    <th>Last Working Day</th>
                                    <th>Settlement Amount</th>
                                    <th>Status</th>
                                    <th>Initiated By</th>
                                    <th width="160">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($settlements_result && $settlements_result->num_rows > 0): ?>
                                    <?php while ($row = $settlements_result->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="form-check-input settlement-checkbox" value="<?= $row['id'] ?>">
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-2" 
                                                         style="width: 32px; height: 32px; font-size: 14px; color: white;">
                                                        <?= strtoupper(substr($row['employee_name'], 0, 1)) ?>
                                                    </div>
                                                    <div>
                                                        <strong><?= htmlspecialchars($row['employee_name']) ?></strong>
                                                        <br><small class="text-muted"><?= htmlspecialchars($row['employee_code']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark">
                                                    <?= htmlspecialchars($row['department_name']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?= date('M j, Y', strtotime($row['last_working_day'])) ?></strong>
                                                <?php if ($row['resignation_date']): ?>
                                                    <br><small class="text-muted">Resigned: <?= date('M j', strtotime($row['resignation_date'])) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($row['net_settlement'] > 0): ?>
                                                    <span class="settlement-amount amount-positive">
                                                        ₹<?= number_format($row['net_settlement'], 2) ?>
                                                    </span>
                                                    <?php if ($row['gross_settlement'] > 0): ?>
                                                        <br><small class="text-muted">Gross: ₹<?= number_format($row['gross_settlement'], 2) ?></small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Not calculated</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?= $row['status'] ?>">
                                                    <?= ucfirst($row['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small>
                                                    <?= htmlspecialchars($row['initiated_by']) ?><br>
                                                    <span class="text-muted"><?= date('M j, Y', strtotime($row['initiated_date'])) ?></span>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            onclick="viewSettlement(<?= $row['id'] ?>)"
                                                            data-bs-toggle="modal" data-bs-target="#viewSettlementModal">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if ($row['status'] === 'pending'): ?>
                                                        <button class="btn btn-sm btn-outline-info" 
                                                                onclick="calculateSettlement(<?= $row['id'] ?>)"
                                                                data-bs-toggle="modal" data-bs-target="#calculateSettlementModal">
                                                            <i class="fas fa-calculator"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if (in_array($row['status'], ['calculated', 'approved'])): ?>
                                                        <button class="btn btn-sm btn-outline-success" 
                                                                onclick="approveSettlement(<?= $row['id'] ?>)"
                                                                data-bs-toggle="modal" data-bs-target="#approveSettlementModal">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($row['status'] === 'approved'): ?>
                                                        <button class="btn btn-sm btn-outline-warning" 
                                                                onclick="markPayment(<?= $row['id'] ?>)"
                                                                data-bs-toggle="modal" data-bs-target="#paymentModal">
                                                            <i class="fas fa-money-bill"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <div class="btn-group">
                                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                                data-bs-toggle="dropdown">
                                                            <i class="fas fa-ellipsis-h"></i>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li><a class="dropdown-item" href="#" onclick="scheduleExitInterview(<?= $row['id'] ?>)">
                                                                <i class="fas fa-calendar me-2"></i>Schedule Exit Interview</a></li>
                                                            <li><a class="dropdown-item" href="#" onclick="createClearanceChecklist(<?= $row['id'] ?>)">
                                                                <i class="fas fa-list-check me-2"></i>Create Clearance List</a></li>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li><a class="dropdown-item text-danger" href="#" onclick="deleteSettlement(<?= $row['id'] ?>)">
                                                                <i class="fas fa-trash me-2"></i>Delete</a></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                                <h5>No FNF Settlements Found</h5>
                                                <p>Start by adding a new settlement record.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Settlement Modal -->
    <div class="modal fade" id="addSettlementModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus text-primary"></i>
                        Initiate FNF Settlement
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addSettlementForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="addEmployeeId" class="form-label">Employee *</label>
                                    <select id="addEmployeeId" name="employee_id" class="form-select" required>
                                        <option value="">Select Employee</option>
                                        <?php if ($employees_result): ?>
                                            <?php while ($emp = $employees_result->fetch_assoc()): ?>
                                                <option value="<?= $emp['id'] ?>" 
                                                        data-name="<?= htmlspecialchars($emp['full_name']) ?>"
                                                        data-code="<?= htmlspecialchars($emp['employee_code']) ?>"
                                                        data-department="<?= htmlspecialchars($emp['department_name']) ?>"
                                                        data-salary="<?= $emp['salary'] ?>">
                                                    <?= htmlspecialchars($emp['full_name']) ?> (<?= htmlspecialchars($emp['employee_code']) ?>)
                                                </option>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="addEmployeeCode" class="form-label">Employee Code</label>
                                    <input type="text" id="addEmployeeCode" name="employee_code" class="form-control" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="addDepartment" class="form-label">Department</label>
                                    <input type="text" id="addDepartment" name="department_name" class="form-control" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="addBasicSalary" class="form-label">Basic Salary</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" id="addBasicSalary" name="basic_salary" class="form-control" step="0.01" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="addResignationDate" class="form-label">Resignation Date</label>
                                    <input type="date" id="addResignationDate" name="resignation_date" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="addLastWorkingDay" class="form-label">Last Working Day *</label>
                                    <input type="date" id="addLastWorkingDay" name="last_working_day" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="addNoticePeriod" class="form-label">Notice Period (Days)</label>
                                    <input type="number" id="addNoticePeriod" name="notice_period_days" class="form-control" value="30">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="addRemarks" class="form-label">Initial Remarks</label>
                            <textarea id="addRemarks" name="remarks" class="form-control" rows="3" 
                                      placeholder="Any initial notes or remarks..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Initiate Settlement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Calculate Settlement Modal -->
    <div class="modal fade" id="calculateSettlementModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-calculator text-info"></i>
                        Calculate Settlement Amount
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="calculateSettlementForm">
                    <input type="hidden" id="calculateSettlementId" name="id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-success mb-3">💰 Earnings</h6>
                                <div class="mb-3">
                                    <label for="calcPendingSalary" class="form-label">Pending Salary</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" id="calcPendingSalary" name="pending_salary" class="form-control" step="0.01" value="0">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="calcLeaveEncashment" class="form-label">Leave Encashment</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" id="calcLeaveEncashment" name="leave_encashment" class="form-control" step="0.01" value="0">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="calcBonusAmount" class="form-label">Bonus Amount</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" id="calcBonusAmount" name="bonus_amount" class="form-control" step="0.01" value="0">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="calcGratuityAmount" class="form-label">Gratuity Amount</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" id="calcGratuityAmount" name="gratuity_amount" class="form-control" step="0.01" value="0">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="calcOtherBenefits" class="form-label">Other Benefits</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" id="calcOtherBenefits" name="other_benefits" class="form-control" step="0.01" value="0">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-danger mb-3">💳 Deductions</h6>
                                <div class="mb-3">
                                    <label for="calcLoanDeductions" class="form-label">Loan Deductions</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" id="calcLoanDeductions" name="loan_deductions" class="form-control" step="0.01" value="0">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="calcAdvanceDeductions" class="form-label">Advance Deductions</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" id="calcAdvanceDeductions" name="advance_deductions" class="form-control" step="0.01" value="0">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="calcOtherDeductions" class="form-label">Other Deductions</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" id="calcOtherDeductions" name="other_deductions" class="form-control" step="0.01" value="0">
                                    </div>
                                </div>
                                <hr>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <strong>Gross Settlement:</strong>
                                        <span id="grossSettlement" class="text-success">₹0.00</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <strong>Total Deductions:</strong>
                                        <span id="totalDeductions" class="text-danger">₹0.00</span>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between">
                                        <strong class="h5">Net Settlement:</strong>
                                        <span id="netSettlement" class="text-primary h5">₹0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-calculator"></i> Calculate & Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Approve Settlement Modal -->
    <div class="modal fade" id="approveSettlementModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-check text-success"></i>
                        Approve Settlement
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="approveSettlementForm">
                    <input type="hidden" id="approveSettlementId" name="id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="approveStatus" class="form-label">Action *</label>
                            <select id="approveStatus" name="status" class="form-select" required>
                                <option value="">Select Action</option>
                                <option value="approved">Approve Settlement</option>
                                <option value="rejected">Reject Settlement</option>
                                <option value="completed">Mark as Completed</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="approveRemarks" class="form-label">Approval Remarks</label>
                            <textarea id="approveRemarks" name="remarks" class="form-control" rows="4" 
                                      placeholder="Add your approval/rejection remarks..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check"></i> Update Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-money-bill text-success"></i>
                        Mark Payment
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="paymentForm">
                    <input type="hidden" id="paymentSettlementId" name="id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="paymentDate" class="form-label">Payment Date *</label>
                            <input type="date" id="paymentDate" name="payment_date" class="form-control" 
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="paymentRemarks" class="form-label">Payment Remarks</label>
                            <textarea id="paymentRemarks" name="payment_remarks" class="form-control" rows="3" 
                                      placeholder="Payment method, transaction details, etc..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-money-bill"></i> Mark as Paid
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Settlement Modal -->
    <div class="modal fade" id="viewSettlementModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-eye text-info"></i>
                        Settlement Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="settlementDetailsContent">
                        <!-- Content will be loaded dynamically -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="printSettlement">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Exit Interview Scheduling Modal -->
    <div class="modal fade" id="exitInterviewModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-calendar text-warning"></i>
                        Schedule Exit Interview
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="exitInterviewForm">
                    <input type="hidden" id="exitSettlementId" name="settlement_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="interviewDate" class="form-label">Interview Date *</label>
                            <input type="date" id="interviewDate" name="interview_date" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="interviewerName" class="form-label">Interviewer Name *</label>
                            <input type="text" id="interviewerName" name="interviewer_name" class="form-control" 
                                   placeholder="Enter interviewer's name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-calendar"></i> Schedule Interview
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Include Bootstrap JS and other dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
        // Employee selection auto-fill
        $('#addEmployeeId').change(function() {
            const selectedOption = $(this).find('option:selected');
            $('#addEmployeeCode').val(selectedOption.data('code') || '');
            $('#addDepartment').val(selectedOption.data('department') || '');
            $('#addBasicSalary').val(selectedOption.data('salary') || '');
            $('input[name="employee_name"]').val(selectedOption.data('name') || selectedOption.text());
        });

        // Add settlement form submission
        $('#addSettlementForm').on('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'add_fnf_settlement');
            
            // Get form data
            const employeeId = $('#addEmployeeId').val();
            const employeeName = $('#addEmployeeId option:selected').data('name') || $('#addEmployeeId option:selected').text();
            
            formData.append('employee_id', employeeId);
            formData.append('employee_name', employeeName);
            formData.append('employee_code', $('#addEmployeeCode').val());
            formData.append('department_name', $('#addDepartment').val());
            formData.append('last_working_day', $('#addLastWorkingDay').val());
            formData.append('resignation_date', $('#addResignationDate').val());
            formData.append('notice_period_days', $('#addNoticePeriod').val());
            formData.append('basic_salary', $('#addBasicSalary').val());
            formData.append('remarks', $('#addRemarks').val());
            
            $.ajax({
                url: 'fnf_settlement.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.success) {
                            alert('FNF settlement initiated successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + result.message);
                        }
                    } catch (e) {
                        alert('Error processing request');
                        console.error('Parse error:', e, response);
                    }
                },
                error: function() {
                    alert('Error submitting form');
                }
            });
        });

        // Calculate settlement amounts dynamically
        $('#calculateSettlementModal input[type="number"]').on('input', function() {
            calculateSettlementAmounts();
        });

        function calculateSettlementAmounts() {
            const earnings = [
                parseFloat($('#calcPendingSalary').val()) || 0,
                parseFloat($('#calcLeaveEncashment').val()) || 0,
                parseFloat($('#calcBonusAmount').val()) || 0,
                parseFloat($('#calcGratuityAmount').val()) || 0,
                parseFloat($('#calcOtherBenefits').val()) || 0
            ];
            
            const deductions = [
                parseFloat($('#calcLoanDeductions').val()) || 0,
                parseFloat($('#calcAdvanceDeductions').val()) || 0,
                parseFloat($('#calcOtherDeductions').val()) || 0
            ];
            
            const grossSettlement = earnings.reduce((a, b) => a + b, 0);
            const totalDeductions = deductions.reduce((a, b) => a + b, 0);
            const netSettlement = grossSettlement - totalDeductions;
            
            $('#grossSettlement').text('₹' + grossSettlement.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
            $('#totalDeductions').text('₹' + totalDeductions.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
            $('#netSettlement').text('₹' + netSettlement.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        }

        // Calculate settlement function
        function calculateSettlement(id) {
            $('#calculateSettlementId').val(id);
            // Reset form
            $('#calculateSettlementModal input[type="number"]').val(0);
            calculateSettlementAmounts();
        }

        // Calculate settlement form submission
        $('#calculateSettlementForm').on('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'calculate_settlement');
            formData.append('id', $('#calculateSettlementId').val());
            formData.append('pending_salary', $('#calcPendingSalary').val());
            formData.append('leave_encashment', $('#calcLeaveEncashment').val());
            formData.append('bonus_amount', $('#calcBonusAmount').val());
            formData.append('gratuity_amount', $('#calcGratuityAmount').val());
            formData.append('other_benefits', $('#calcOtherBenefits').val());
            formData.append('loan_deductions', $('#calcLoanDeductions').val());
            formData.append('advance_deductions', $('#calcAdvanceDeductions').val());
            formData.append('other_deductions', $('#calcOtherDeductions').val());
            
            $.ajax({
                url: 'fnf_settlement.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.success) {
                            alert('Settlement calculated successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + result.message);
                        }
                    } catch (e) {
                        alert('Error processing request');
                        console.error('Parse error:', e, response);
                    }
                },
                error: function() {
                    alert('Error calculating settlement');
                }
            });
        });

        // Approve settlement function
        function approveSettlement(id) {
            $('#approveSettlementId').val(id);
        }

        // Approve settlement form submission
        $('#approveSettlementForm').on('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'approve_settlement');
            formData.append('id', $('#approveSettlementId').val());
            formData.append('status', $('#approveStatus').val());
            formData.append('remarks', $('#approveRemarks').val());
            
            $.ajax({
                url: 'fnf_settlement.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.success) {
                            alert('Settlement status updated successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + result.message);
                        }
                    } catch (e) {
                        alert('Error processing request');
                        console.error('Parse error:', e, response);
                    }
                },
                error: function() {
                    alert('Error updating settlement status');
                }
            });
        });

        // Mark payment function
        function markPayment(id) {
            $('#paymentSettlementId').val(id);
        }

        // Payment form submission
        $('#paymentForm').on('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'mark_payment');
            formData.append('id', $('#paymentSettlementId').val());
            formData.append('payment_date', $('#paymentDate').val());
            formData.append('payment_remarks', $('#paymentRemarks').val());
            
            $.ajax({
                url: 'fnf_settlement.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.success) {
                            alert('Payment marked successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + result.message);
                        }
                    } catch (e) {
                        alert('Error processing request');
                        console.error('Parse error:', e, response);
                    }
                },
                error: function() {
                    alert('Error marking payment');
                }
            });
        });

        // View settlement function
        function viewSettlement(id) {
            $.post('fnf_settlement.php', {
                action: 'get_settlement',
                id: id
            }, function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        const data = result.data;
                        const html = `
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-primary mb-3">Employee Information</h6>
                                    <table class="table table-borderless">
                                        <tr><td><strong>Employee Name:</strong></td><td>${data.employee_name}</td></tr>
                                        <tr><td><strong>Employee Code:</strong></td><td>${data.employee_code || 'N/A'}</td></tr>
                                        <tr><td><strong>Department:</strong></td><td>${data.department_name || 'N/A'}</td></tr>
                                        <tr><td><strong>Basic Salary:</strong></td><td>₹${parseFloat(data.basic_salary).toLocaleString('en-IN')}</td></tr>
                                        <tr><td><strong>Resignation Date:</strong></td><td>${data.resignation_date ? new Date(data.resignation_date).toLocaleDateString() : 'N/A'}</td></tr>
                                        <tr><td><strong>Last Working Day:</strong></td><td>${new Date(data.last_working_day).toLocaleDateString()}</td></tr>
                                        <tr><td><strong>Notice Period:</strong></td><td>${data.notice_period_days} days</td></tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-success mb-3">Settlement Details</h6>
                                    <table class="table table-borderless">
                                        <tr class="table-light"><td colspan="2"><strong>Earnings</strong></td></tr>
                                        <tr><td>Pending Salary:</td><td>₹${parseFloat(data.pending_salary).toLocaleString('en-IN')}</td></tr>
                                        <tr><td>Leave Encashment:</td><td>₹${parseFloat(data.leave_encashment).toLocaleString('en-IN')}</td></tr>
                                        <tr><td>Bonus Amount:</td><td>₹${parseFloat(data.bonus_amount).toLocaleString('en-IN')}</td></tr>
                                        <tr><td>Gratuity Amount:</td><td>₹${parseFloat(data.gratuity_amount).toLocaleString('en-IN')}</td></tr>
                                        <tr><td>Other Benefits:</td><td>₹${parseFloat(data.other_benefits).toLocaleString('en-IN')}</td></tr>
                                        <tr class="table-warning"><td><strong>Gross Settlement:</strong></td><td><strong>₹${parseFloat(data.gross_settlement).toLocaleString('en-IN')}</strong></td></tr>
                                    </table>
                                    <table class="table table-borderless">
                                        <tr class="table-light"><td colspan="2"><strong>Deductions</strong></td></tr>
                                        <tr><td>Loan Deductions:</td><td>₹${parseFloat(data.loan_deductions).toLocaleString('en-IN')}</td></tr>
                                        <tr><td>Advance Deductions:</td><td>₹${parseFloat(data.advance_deductions).toLocaleString('en-IN')}</td></tr>
                                        <tr><td>Other Deductions:</td><td>₹${parseFloat(data.other_deductions).toLocaleString('en-IN')}</td></tr>
                                        <tr class="table-success"><td><strong>Net Settlement:</strong></td><td><strong class="text-success">₹${parseFloat(data.net_settlement).toLocaleString('en-IN')}</strong></td></tr>
                                    </table>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <h6 class="text-info mb-3">Process Information</h6>
                                    <table class="table table-borderless">
                                        <tr><td><strong>Status:</strong></td><td><span class="status-badge status-${data.status}">${data.status.charAt(0).toUpperCase() + data.status.slice(1)}</span></td></tr>
                                        <tr><td><strong>Initiated By:</strong></td><td>${data.initiated_by || 'N/A'}</td></tr>
                                        <tr><td><strong>Initiated Date:</strong></td><td>${data.initiated_date ? new Date(data.initiated_date).toLocaleDateString() : 'N/A'}</td></tr>
                                        <tr><td><strong>Approved By:</strong></td><td>${data.approved_by || 'N/A'}</td></tr>
                                        <tr><td><strong>Approved Date:</strong></td><td>${data.approved_date ? new Date(data.approved_date).toLocaleDateString() : 'N/A'}</td></tr>
                                        <tr><td><strong>Payment Date:</strong></td><td>${data.payment_date ? new Date(data.payment_date).toLocaleDateString() : 'N/A'}</td></tr>
                                    </table>
                                </div>
                            </div>
                            ${data.remarks ? `
                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <h6 class="text-secondary mb-2">Remarks:</h6>
                                        <div class="border rounded p-3 bg-light">
                                            ${data.remarks.replace(/\n/g, '<br>')}
                                        </div>
                                    </div>
                                </div>
                            ` : ''}
                        `;
                        $('#settlementDetailsContent').html(html);
                    } else {
                        alert('Error: ' + result.message);
                    }
                } catch (e) {
                    alert('Error loading settlement details');
                    console.error('Parse error:', e, response);
                }
            });
        }

        // Schedule exit interview function
        function scheduleExitInterview(settlementId) {
            $('#exitSettlementId').val(settlementId);
            $('#exitInterviewModal').modal('show');
        }

        // Exit interview form submission
        $('#exitInterviewForm').on('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'schedule_exit_interview');
            formData.append('settlement_id', $('#exitSettlementId').val());
            formData.append('interview_date', $('#interviewDate').val());
            formData.append('interviewer_name', $('#interviewerName').val());
            
            $.ajax({
                url: 'fnf_settlement.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.success) {
                            alert('Exit interview scheduled successfully!');
                            $('#exitInterviewModal').modal('hide');
                        } else {
                            alert('Error: ' + result.message);
                        }
                    } catch (e) {
                        alert('Error processing request');
                        console.error('Parse error:', e, response);
                    }
                },
                error: function() {
                    alert('Error scheduling exit interview');
                }
            });
        });

        // Create clearance checklist function
        function createClearanceChecklist(settlementId) {
            if (confirm('Create a clearance checklist for this employee?')) {
                $.post('fnf_settlement.php', {
                    action: 'create_clearance_checklist',
                    settlement_id: settlementId
                }, function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.success) {
                            alert('Clearance checklist created successfully!');
                        } else {
                            alert('Error: ' + result.message);
                        }
                    } catch (e) {
                        alert('Error creating clearance checklist');
                        console.error('Parse error:', e, response);
                    }
                });
            }
        }

        // Delete settlement function
        function deleteSettlement(id) {
            if (confirm('Are you sure you want to delete this FNF settlement record?')) {
                $.post('fnf_settlement.php', {
                    action: 'delete_settlement',
                    id: id
                }, function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.success) {
                            alert('FNF settlement deleted successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + result.message);
                        }
                    } catch (e) {
                        alert('Error processing request');
                        console.error('Parse error:', e, response);
                    }
                });
            }
        }

        // Select All functionality
        $('#selectAll').change(function() {
            $('.settlement-checkbox').prop('checked', this.checked);
            updateBulkProcessButton();
        });

        $('.settlement-checkbox').change(function() {
            updateBulkProcessButton();
        });

        function updateBulkProcessButton() {
            const selectedCount = $('.settlement-checkbox:checked').length;
            $('#bulkProcessBtn').prop('disabled', selectedCount === 0);
        }

        // Generate report function
        $('#generateReportBtn').click(function() {
            window.open('fnf_settlement.php?generate_report=1', '_blank');
        });

        // Print settlement function
        $('#printSettlement').click(function() {
            window.print();
        });

        // Set minimum date to today for date inputs
        const today = new Date().toISOString().split('T')[0];
        $('#interviewDate').attr('min', today);
    </script>

    <?php include $base_dir . '/layouts/footer.php'; ?>
</body>
</html>
