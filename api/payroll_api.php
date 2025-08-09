<?php
// Payroll API for all payslip operations
session_start();

if (!isset($_SESSION['admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

include '../../db.php';
include '../../includes/payroll_functions.php';

header('Content-Type: application/json');

if (!isset($_POST['action'])) {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
    exit;
}

$action = $_POST['action'];

switch ($action) {
    case 'get_employees_for_bulk':
        $department = $_POST['department'] ?? '';
        $where_clause = "WHERE status = 'active'";
        
        if ($department) {
            $where_clause .= " AND department_name = '" . mysqli_real_escape_string($conn, $department) . "'";
        }
        
        $query = "SELECT employee_id, employee_code, name, department_name, position, monthly_salary FROM employees $where_clause ORDER BY name";
        $result = $conn->query($query);
        
        $employees = [];
        while ($row = $result->fetch_assoc()) {
            $employees[] = $row;
        }
        
        echo json_encode(['success' => true, 'employees' => $employees]);
        break;
        
    case 'generate_bulk_payslips':
        $employee_ids = $_POST['employee_ids'] ?? [];
        $pay_period = $_POST['pay_period'] ?? date('Y-m');
        
        if (empty($employee_ids)) {
            echo json_encode(['success' => false, 'message' => 'No employees selected']);
            exit;
        }
        
        $generated = 0;
        $errors = [];
        
        foreach ($employee_ids as $employee_id) {
            $result = generatePayslip($conn, intval($employee_id), $pay_period);
            if ($result['success']) {
                $generated++;
            } else {
                $errors[] = "Employee ID $employee_id: " . $result['message'];
            }
        }
        
        $message = "Generated $generated payslips successfully.";
        if (!empty($errors)) {
            $message .= " Errors: " . implode(', ', $errors);
        }
        
        echo json_encode(['success' => true, 'message' => $message, 'generated' => $generated]);
        break;
        
    case 'get_payroll_summary':
        $period = $_POST['period'] ?? date('Y-m');
        $department = $_POST['department'] ?? '';
        
        $where_clause = "WHERE pay_period = '" . mysqli_real_escape_string($conn, $period) . "'";
        if ($department) {
            $where_clause .= " AND department = '" . mysqli_real_escape_string($conn, $department) . "'";
        }
        
        // Get summary statistics
        $summary_query = "SELECT 
            COUNT(*) as total_payslips,
            SUM(gross_salary) as total_gross,
            SUM(total_deductions) as total_deductions,
            SUM(net_salary) as total_net,
            AVG(net_salary) as avg_net,
            MIN(net_salary) as min_net,
            MAX(net_salary) as max_net
            FROM payslips $where_clause";
        
        $summary = $conn->query($summary_query)->fetch_assoc();
        
        // Get department-wise breakdown
        $dept_query = "SELECT 
            department,
            COUNT(*) as emp_count,
            SUM(gross_salary) as dept_gross,
            SUM(net_salary) as dept_net
            FROM payslips $where_clause 
            GROUP BY department
            ORDER BY dept_net DESC";
        
        $departments = [];
        $dept_result = $conn->query($dept_query);
        while ($row = $dept_result->fetch_assoc()) {
            $departments[] = $row;
        }
        
        // Get individual payslips
        $payslips_query = "SELECT 
            employee_name,
            department,
            position,
            gross_salary,
            total_deductions,
            net_salary,
            status
            FROM payslips $where_clause
            ORDER BY net_salary DESC";
        
        $payslips = [];
        $payslips_result = $conn->query($payslips_query);
        while ($row = $payslips_result->fetch_assoc()) {
            $payslips[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'summary' => $summary,
            'departments' => $departments,
            'payslips' => $payslips
        ]);
        break;
        
    case 'export_payslips':
        $format = $_POST['format'] ?? 'pdf';
        $period = $_POST['period'] ?? date('Y-m');
        $department = $_POST['department'] ?? '';
        
        $where_clause = "WHERE pay_period = '" . mysqli_real_escape_string($conn, $period) . "'";
        if ($department) {
            $where_clause .= " AND department = '" . mysqli_real_escape_string($conn, $department) . "'";
        }
        
        $query = "SELECT * FROM payslips $where_clause ORDER BY employee_name";
        $result = $conn->query($query);
        
        if ($format == 'csv') {
            $filename = "payslips_" . $period . "_" . ($department ?: 'all') . ".csv";
            
            // Create CSV content
            $csv_data = "Employee Name,Department,Position,Gross Salary,Total Deductions,Net Salary,Status\n";
            
            while ($row = $result->fetch_assoc()) {
                $csv_data .= sprintf(
                    "%s,%s,%s,%.2f,%.2f,%.2f,%s\n",
                    $row['employee_name'],
                    $row['department'],
                    $row['position'],
                    $row['gross_salary'],
                    $row['total_deductions'],
                    $row['net_salary'],
                    $row['status']
                );
            }
            
            echo json_encode([
                'success' => true,
                'filename' => $filename,
                'data' => base64_encode($csv_data),
                'type' => 'text/csv'
            ]);
        } else {
            // For PDF and Excel, we'll return the data to be processed by frontend
            $payslips = [];
            while ($row = $result->fetch_assoc()) {
                $payslips[] = $row;
            }
            
            echo json_encode([
                'success' => true,
                'payslips' => $payslips,
                'format' => $format
            ]);
        }
        break;
        
    case 'get_payroll_settings':
        $query = "SELECT setting_key, setting_value FROM payroll_settings ORDER BY setting_key";
        $result = $conn->query($query);
        
        $settings = [];
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        echo json_encode(['success' => true, 'settings' => $settings]);
        break;
        
    case 'save_payroll_settings':
        $settings = $_POST['settings'] ?? [];
        
        if (empty($settings)) {
            echo json_encode(['success' => false, 'message' => 'No settings provided']);
            exit;
        }
        
        $updated = 0;
        foreach ($settings as $key => $value) {
            $query = "INSERT INTO payroll_settings (setting_key, setting_value) 
                     VALUES (?, ?) 
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ss', $key, $value);
            if ($stmt->execute()) {
                $updated++;
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Updated $updated settings successfully"
        ]);
        break;
        
    case 'send_payslip_email':
        $payslip_id = $_POST['payslip_id'] ?? '';
        $email = $_POST['email'] ?? '';
        
        if (!$payslip_id || !$email) {
            echo json_encode(['success' => false, 'message' => 'Missing payslip ID or email']);
            exit;
        }
        
        // Get payslip details
        $query = "SELECT * FROM payslips WHERE payslip_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $payslip_id);
        $stmt->execute();
        $payslip = $stmt->get_result()->fetch_assoc();
        
        if (!$payslip) {
            echo json_encode(['success' => false, 'message' => 'Payslip not found']);
            exit;
        }
        
        // Simple email simulation (you can integrate with actual email service)
        $subject = "Payslip for " . $payslip['pay_period'];
        $message = "Dear " . $payslip['employee_name'] . ",\n\n";
        $message .= "Please find your payslip for " . $payslip['pay_period'] . " attached.\n\n";
        $message .= "Net Salary: ₹" . number_format($payslip['net_salary'], 2) . "\n\n";
        $message .= "Best regards,\nHR Department";
        
        // Log the email (in real implementation, you would send actual email)
        error_log("Email sent to $email: $subject");
        
        echo json_encode([
            'success' => true,
            'message' => 'Payslip sent successfully to ' . $email
        ]);
        break;
        
    case 'delete_payslip':
        $payslip_id = $_POST['payslip_id'] ?? '';
        
        if (!$payslip_id) {
            echo json_encode(['success' => false, 'message' => 'Payslip ID required']);
            exit;
        }
        
        $query = "DELETE FROM payslips WHERE payslip_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $payslip_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Payslip deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete payslip']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

$conn->close();
?>
