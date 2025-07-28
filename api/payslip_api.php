<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include '../db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'generate_payslip':
            generatePayslip($conn);
            break;
            
        case 'bulk_generate':
            bulkGeneratePayslips($conn);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_payslips':
            getPayslips($conn);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function generatePayslip($conn) {
    $employeeId = $_POST['employee_id'] ?? '';
    $month = $_POST['month'] ?? date('Y-m');
    
    try {
        // Get employee and salary details
        $query = "SELECT e.name, e.employee_id, e.email,
                         COALESCE(s.basic_salary, 25000) as basic_salary,
                         COALESCE(s.allowances, 5000) as allowances,
                         COALESCE(s.deductions, 2000) as deductions,
                         COALESCE(s.total_salary, 28000) as total_salary
                  FROM employees e 
                  LEFT JOIN salaries s ON e.id = s.employee_id
                  WHERE e.id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $employeeId);
        $stmt->execute();
        $employee = $stmt->get_result()->fetch_assoc();
        
        if (!$employee) {
            echo json_encode(['success' => false, 'message' => 'Employee not found']);
            return;
        }
        
        // Calculate attendance for the month
        $startDate = $month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));
        
        $attendanceQuery = "SELECT 
                               COUNT(*) as total_days,
                               SUM(CASE WHEN status IN ('Present', 'Late') THEN 1 ELSE 0 END) as present_days,
                               SUM(CASE WHEN check_in_time IS NOT NULL AND check_out_time IS NOT NULL 
                                   THEN TIME_TO_SEC(TIMEDIFF(check_out_time, check_in_time)) / 3600 
                                   ELSE 0 END) as total_hours
                           FROM attendance 
                           WHERE employee_id = ? AND date BETWEEN ? AND ?";
        
        $attStmt = $conn->prepare($attendanceQuery);
        $attStmt->bind_param('iss', $employeeId, $startDate, $endDate);
        $attStmt->execute();
        $attendance = $attStmt->get_result()->fetch_assoc();
        
        // Calculate pro-rated salary based on attendance
        $workingDays = 22; // Assume 22 working days per month
        $presentDays = $attendance['present_days'] ?? 0;
        $salaryMultiplier = $presentDays / $workingDays;
        
        $payslipData = [
            'employee' => $employee,
            'month' => $month,
            'attendance' => $attendance,
            'earnings' => [
                'basic_salary' => round($employee['basic_salary'] * $salaryMultiplier, 2),
                'allowances' => round($employee['allowances'] * $salaryMultiplier, 2),
            ],
            'deductions' => [
                'pf' => round($employee['basic_salary'] * $salaryMultiplier * 0.12, 2),
                'esi' => round($employee['basic_salary'] * $salaryMultiplier * 0.0175, 2),
                'other' => round($employee['deductions'] * $salaryMultiplier, 2)
            ]
        ];
        
        $grossSalary = $payslipData['earnings']['basic_salary'] + $payslipData['earnings']['allowances'];
        $totalDeductions = $payslipData['deductions']['pf'] + $payslipData['deductions']['esi'] + $payslipData['deductions']['other'];
        $netSalary = $grossSalary - $totalDeductions;
        
        $payslipData['summary'] = [
            'gross_salary' => $grossSalary,
            'total_deductions' => $totalDeductions,
            'net_salary' => $netSalary
        ];
        
        // Save payslip to database
        $saveQuery = "INSERT INTO payslips (employee_id, month, gross_salary, total_deductions, net_salary, payslip_data, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE 
                     gross_salary = VALUES(gross_salary),
                     total_deductions = VALUES(total_deductions),
                     net_salary = VALUES(net_salary),
                     payslip_data = VALUES(payslip_data),
                     updated_at = NOW()";
        
        $saveStmt = $conn->prepare($saveQuery);
        $payslipJson = json_encode($payslipData);
        $saveStmt->bind_param('isddds', $employeeId, $month, $grossSalary, $totalDeductions, $netSalary, $payslipJson);
        $saveStmt->execute();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Payslip generated successfully',
            'payslip' => $payslipData
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error generating payslip: ' . $e->getMessage()]);
    }
}

function bulkGeneratePayslips($conn) {
    $month = $_POST['month'] ?? date('Y-m');
    
    try {
        // Get all employees
        $query = "SELECT id FROM employees";
        $result = $conn->query($query);
        
        $generated = 0;
        $errors = [];
        
        while ($row = $result->fetch_assoc()) {
            // Generate payslip for each employee
            $_POST['employee_id'] = $row['id'];
            $_POST['month'] = $month;
            
            ob_start();
            generatePayslip($conn);
            $response = ob_get_clean();
            
            $responseData = json_decode($response, true);
            if ($responseData['success']) {
                $generated++;
            } else {
                $errors[] = "Employee ID {$row['id']}: " . $responseData['message'];
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Generated $generated payslips for $month",
            'generated_count' => $generated,
            'errors' => $errors
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error in bulk generation: ' . $e->getMessage()]);
    }
}

function getPayslips($conn) {
    $month = $_GET['month'] ?? date('Y-m');
    
    try {
        $query = "SELECT p.*, e.name, e.employee_id 
                  FROM payslips p 
                  INNER JOIN employees e ON p.employee_id = e.id 
                  WHERE p.month = ? 
                  ORDER BY e.name";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $month);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $payslips = [];
        while ($row = $result->fetch_assoc()) {
            $payslips[] = $row;
        }
        
        echo json_encode(['success' => true, 'payslips' => $payslips]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching payslips: ' . $e->getMessage()]);
    }
}

// Create payslips table if not exists
function createPayslipsTable($conn) {
    $query = "CREATE TABLE IF NOT EXISTS payslips (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        month VARCHAR(7) NOT NULL,
        gross_salary DECIMAL(10,2) DEFAULT 0,
        total_deductions DECIMAL(10,2) DEFAULT 0,
        net_salary DECIMAL(10,2) DEFAULT 0,
        payslip_data TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_employee_month (employee_id, month),
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    )";
    
    $conn->query($query);
}

// Create table if not exists
createPayslipsTable($conn);
?>
