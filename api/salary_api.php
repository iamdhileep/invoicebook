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
        case 'update_salary':
            updateSalary($conn);
            break;
            
        case 'get_salary_structure':
            getSalaryStructure($conn);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_employees':
            getEmployees($conn);
            break;
            
        case 'get_salary_config':
            getSalaryConfig($conn);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function updateSalary($conn) {
    $employeeId = $_POST['employee_id'] ?? '';
    $basicSalary = $_POST['basic_salary'] ?? 0;
    $allowances = $_POST['allowances'] ?? 0;
    $deductions = $_POST['deductions'] ?? 0;
    
    try {
        // Check if salary record exists
        $checkQuery = "SELECT COUNT(*) as count FROM salaries WHERE employee_id = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param('i', $employeeId);
        $checkStmt->execute();
        $exists = $checkStmt->get_result()->fetch_assoc()['count'] > 0;
        
        if ($exists) {
            // Update existing salary
            $query = "UPDATE salaries SET 
                     basic_salary = ?, allowances = ?, deductions = ?, 
                     total_salary = (? + ? - ?), updated_at = NOW()
                     WHERE employee_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ddddddi', $basicSalary, $allowances, $deductions, 
                             $basicSalary, $allowances, $deductions, $employeeId);
        } else {
            // Insert new salary record
            $query = "INSERT INTO salaries (employee_id, basic_salary, allowances, deductions, total_salary, created_at) 
                     VALUES (?, ?, ?, ?, (? + ? - ?), NOW())";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('idddddd', $employeeId, $basicSalary, $allowances, $deductions,
                             $basicSalary, $allowances, $deductions);
        }
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Salary updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating salary']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getEmployees($conn) {
    try {
        $query = "SELECT e.id, e.name, e.employee_id, 
                         COALESCE(s.basic_salary, 0) as basic_salary,
                         COALESCE(s.allowances, 0) as allowances,
                         COALESCE(s.deductions, 0) as deductions,
                         COALESCE(s.total_salary, 0) as total_salary
                  FROM employees e 
                  LEFT JOIN salaries s ON e.id = s.employee_id
                  ORDER BY e.name";
        
        $result = $conn->query($query);
        $employees = [];
        
        while ($row = $result->fetch_assoc()) {
            $employees[] = $row;
        }
        
        echo json_encode(['success' => true, 'employees' => $employees]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching employees: ' . $e->getMessage()]);
    }
}

function getSalaryStructure($conn) {
    try {
        $query = "SELECT e.name, s.basic_salary, s.allowances, s.deductions, s.total_salary
                  FROM employees e 
                  INNER JOIN salaries s ON e.id = s.employee_id
                  ORDER BY e.name";
        
        $result = $conn->query($query);
        $structure = [];
        
        while ($row = $result->fetch_assoc()) {
            $structure[] = $row;
        }
        
        echo json_encode(['success' => true, 'structure' => $structure]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching salary structure: ' . $e->getMessage()]);
    }
}

function getSalaryConfig($conn) {
    try {
        // Get default salary configuration
        $config = [
            'default_basic' => 25000,
            'default_allowances' => 5000,
            'default_deductions' => 2000,
            'pf_percentage' => 12,
            'esi_percentage' => 1.75,
            'tax_slabs' => [
                ['min' => 0, 'max' => 250000, 'rate' => 0],
                ['min' => 250001, 'max' => 500000, 'rate' => 5],
                ['min' => 500001, 'max' => 1000000, 'rate' => 20]
            ]
        ];
        
        echo json_encode(['success' => true, 'config' => $config]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching config: ' . $e->getMessage()]);
    }
}

// Ensure salary table exists
function createSalaryTable($conn) {
    $query = "CREATE TABLE IF NOT EXISTS salaries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        basic_salary DECIMAL(10,2) DEFAULT 0,
        allowances DECIMAL(10,2) DEFAULT 0,
        deductions DECIMAL(10,2) DEFAULT 0,
        total_salary DECIMAL(10,2) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    )";
    
    $conn->query($query);
}

// Create table if not exists
createSalaryTable($conn);
?>
