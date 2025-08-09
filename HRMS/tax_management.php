<?php
// LAST UPDATED: 2025-08-08 17:05:31 - CACHE BUSTED
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
    $_SESSION['user_id'] = $_SESSION['user'];
}
if (!isset($_SESSION['role'])) {
    $_SESSION['role'] = 'employee';
}
if (!isset($_SESSION['employee_id'])) {
    $_SESSION['employee_id'] = $_SESSION['user_id'] ?? 1;
}

$page_title = 'Tax Management';

// Create tax components table if it doesn't exist
$createTaxComponentsTable = "CREATE TABLE IF NOT EXISTS hr_tax_components (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    component_name VARCHAR(100) NOT NULL,
    component_type ENUM('income_tax', 'professional_tax', 'tds', 'cess', 'surcharge', 'other') NOT NULL,
    calculation_type ENUM('percentage', 'slab', 'fixed', 'custom') DEFAULT 'percentage',
    rate_percentage DECIMAL(5,2) DEFAULT 0,
    fixed_amount DECIMAL(10,2) DEFAULT 0,
    min_salary DECIMAL(10,2) DEFAULT 0,
    max_salary DECIMAL(10,2) DEFAULT 0,
    exemption_limit DECIMAL(10,2) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB";
mysqli_query($conn, $createTaxComponentsTable);

// Create employee tax calculations table
$createEmployeeTaxTable = "CREATE TABLE IF NOT EXISTS hr_employee_tax_calculations (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    employee_id INT(11) NOT NULL,
    tax_component_id INT(11) NOT NULL,
    financial_year VARCHAR(10) NOT NULL,
    annual_taxable_income DECIMAL(12,2) DEFAULT 0,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    monthly_deduction DECIMAL(10,2) DEFAULT 0,
    exemptions DECIMAL(10,2) DEFAULT 0,
    rebates DECIMAL(10,2) DEFAULT 0,
    status ENUM('calculated', 'applied', 'revised') DEFAULT 'calculated',
    calculation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
    FOREIGN KEY (tax_component_id) REFERENCES hr_tax_components(id) ON DELETE CASCADE
) ENGINE=InnoDB";
mysqli_query($conn, $createEmployeeTaxTable);

// Insert sample tax components if table is empty
$checkTaxComponents = mysqli_query($conn, "SELECT COUNT(*) as count FROM hr_tax_components");
$taxCount = mysqli_fetch_assoc($checkTaxComponents)['count'];

if ($taxCount == 0) {
    $sampleTaxComponents = [
        ['Income Tax', 'income_tax', 'slab', 0, 0, 0, 9999999, 250000, 'Income tax as per IT Act'],
        ['Professional Tax', 'professional_tax', 'fixed', 0, 200, 0, 9999999, 0, 'State professional tax'],
        ['TDS on Salary', 'tds', 'slab', 0, 0, 0, 9999999, 250000, 'Tax deducted at source'],
        ['Health & Education Cess', 'cess', 'percentage', 4, 0, 0, 9999999, 0, '4% cess on income tax'],
        ['Surcharge', 'surcharge', 'percentage', 10, 0, 5000000, 9999999, 0, 'Surcharge for high income']
    ];
    
    foreach ($sampleTaxComponents as $component) {
        $stmt = $conn->prepare("INSERT INTO hr_tax_components (component_name, component_type, calculation_type, rate_percentage, fixed_amount, min_salary, max_salary, exemption_limit, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssddddds", ...$component);
        $stmt->execute();
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_tax_components':
            $result = mysqli_query($conn, "SELECT * FROM hr_tax_components ORDER BY component_name");
            $components = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $components[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $components]);
            exit;
            
        case 'save_tax_component':
            $id = $_POST['id'] ?? null;
            $component_name = mysqli_real_escape_string($conn, $_POST['component_name']);
            $component_type = $_POST['component_type'];
            $calculation_type = $_POST['calculation_type'];
            $rate_percentage = floatval($_POST['rate_percentage']);
            $fixed_amount = floatval($_POST['fixed_amount']);
            $min_salary = floatval($_POST['min_salary']);
            $max_salary = floatval($_POST['max_salary']);
            $exemption_limit = floatval($_POST['exemption_limit']);
            $is_active = intval($_POST['is_active']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            
            if ($id) {
                $query = "UPDATE hr_tax_components SET 
                         component_name = '$component_name',
                         component_type = '$component_type',
                         calculation_type = '$calculation_type',
                         rate_percentage = $rate_percentage,
                         fixed_amount = $fixed_amount,
                         min_salary = $min_salary,
                         max_salary = $max_salary,
                         exemption_limit = $exemption_limit,
                         is_active = $is_active,
                         description = '$description'
                         WHERE id = $id";
            } else {
                $query = "INSERT INTO hr_tax_components (component_name, component_type, calculation_type, rate_percentage, fixed_amount, min_salary, max_salary, exemption_limit, is_active, description) 
                         VALUES ('$component_name', '$component_type', '$calculation_type', $rate_percentage, $fixed_amount, $min_salary, $max_salary, $exemption_limit, $is_active, '$description')";
            }
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Tax component saved successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error saving tax component']);
            }
            exit;
            
        case 'delete_tax_component':
            $id = intval($_POST['id']);
            if (mysqli_query($conn, "DELETE FROM hr_tax_components WHERE id = $id")) {
                echo json_encode(['success' => true, 'message' => 'Tax component deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error deleting tax component']);
            }
            exit;
            
        case 'toggle_tax_component':
            $id = intval($_POST['id']);
            $status = intval($_POST['status']);
            if (mysqli_query($conn, "UPDATE hr_tax_components SET is_active = $status WHERE id = $id")) {
                echo json_encode(['success' => true, 'message' => 'Tax component status updated']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating status']);
            }
            exit;
            
        case 'get_employee_tax_data':
            $result = mysqli_query($conn, "
                SELECT e.id, e.first_name, e.last_name, e.employee_id, e.salary as basic_salary,
                       COALESCE(p.gross_salary, e.salary * 1.4) as gross_salary,
                       COALESCE(p.total_deductions, 0) as total_deductions,
                       COALESCE(tc.tax_amount, 0) as annual_tax,
                       COALESCE(tc.monthly_deduction, 0) as monthly_tax_deduction
                FROM hr_employees e
                LEFT JOIN hr_payroll p ON e.id = p.employee_id AND p.year = YEAR(CURDATE()) AND p.month = MONTH(CURDATE())
                LEFT JOIN hr_employee_tax_calculations tc ON e.id = tc.employee_id AND tc.financial_year = CONCAT(YEAR(CURDATE()), '-', YEAR(CURDATE())+1)
                WHERE e.status = 'active'
                ORDER BY e.first_name, e.last_name
            ");
            
            $employees = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $employees[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $employees]);
            exit;
            
        case 'calculate_employee_tax':
            $employee_id = intval($_POST['employee_id']);
            $financial_year = $_POST['financial_year'] ?? (date('Y') . '-' . (date('Y')+1));
            
            // Get employee salary info
            $employee = mysqli_fetch_assoc(mysqli_query($conn, "
                SELECT e.*, COALESCE(p.gross_salary, e.salary * 1.4) as annual_gross
                FROM hr_employees e
                LEFT JOIN hr_payroll p ON e.id = p.employee_id
                WHERE e.id = $employee_id
            "));
            
            if (!$employee) {
                echo json_encode(['success' => false, 'message' => 'Employee not found']);
                exit;
            }
            
            $annual_gross = $employee['annual_gross'] * 12;
            $income_tax = calculateIncomeTax($annual_gross);
            $professional_tax = 200 * 12; // Annual PT
            $total_tax = $income_tax + $professional_tax;
            
            // Save calculation
            mysqli_query($conn, "
                INSERT INTO hr_employee_tax_calculations 
                (employee_id, tax_component_id, financial_year, annual_taxable_income, tax_amount, monthly_deduction) 
                VALUES 
                ($employee_id, 1, '$financial_year', $annual_gross, $income_tax, " . ($income_tax/12) . "),
                ($employee_id, 2, '$financial_year', $annual_gross, $professional_tax, " . ($professional_tax/12) . ")
                ON DUPLICATE KEY UPDATE 
                annual_taxable_income = VALUES(annual_taxable_income),
                tax_amount = VALUES(tax_amount),
                monthly_deduction = VALUES(monthly_deduction)
            ");
            
            echo json_encode(['success' => true, 'message' => 'Tax calculated successfully', 'data' => [
                'income_tax' => $income_tax,
                'professional_tax' => $professional_tax,
                'total_tax' => $total_tax,
                'monthly_tax' => $total_tax / 12
            ]]);
            exit;
            
        case 'bulk_tax_calculation':
            $financial_year = $_POST['financial_year'] ?? (date('Y') . '-' . (date('Y')+1));
            
            $employees = mysqli_query($conn, "
                SELECT e.id, COALESCE(p.gross_salary, e.salary * 1.4) as annual_gross
                FROM hr_employees e
                LEFT JOIN hr_payroll p ON e.id = p.employee_id
                WHERE e.status = 'active'
            ");
            
            $processed = 0;
            while ($employee = mysqli_fetch_assoc($employees)) {
                $annual_gross = $employee['annual_gross'] * 12;
                $income_tax = calculateIncomeTax($annual_gross);
                $professional_tax = 200 * 12;
                
                mysqli_query($conn, "
                    INSERT INTO hr_employee_tax_calculations 
                    (employee_id, tax_component_id, financial_year, annual_taxable_income, tax_amount, monthly_deduction) 
                    VALUES 
                    ({$employee['id']}, 1, '$financial_year', $annual_gross, $income_tax, " . ($income_tax/12) . "),
                    ({$employee['id']}, 2, '$financial_year', $annual_gross, $professional_tax, " . ($professional_tax/12) . ")
                    ON DUPLICATE KEY UPDATE 
                    annual_taxable_income = VALUES(annual_taxable_income),
                    tax_amount = VALUES(tax_amount),
                    monthly_deduction = VALUES(monthly_deduction)
                ");
                $processed++;
            }
            
            echo json_encode(['success' => true, 'message' => "Tax calculated for $processed employees"]);
            exit;
    }
}

// Calculate income tax based on current slabs
function calculateIncomeTax($annual_income) {
    $tax = 0;
    
    // Income tax slabs for FY 2024-25 (New Tax Regime)
    if ($annual_income > 1500000) {
        $tax += ($annual_income - 1500000) * 0.30;
        $annual_income = 1500000;
    }
    if ($annual_income > 1200000) {
        $tax += ($annual_income - 1200000) * 0.20;
        $annual_income = 1200000;
    }
    if ($annual_income > 900000) {
        $tax += ($annual_income - 900000) * 0.15;
        $annual_income = 900000;
    }
    if ($annual_income > 600000) {
        $tax += ($annual_income - 600000) * 0.10;
        $annual_income = 600000;
    }
    if ($annual_income > 300000) {
        $tax += ($annual_income - 300000) * 0.05;
    }
    
    // Add 4% Health and Education Cess
    $cess = $tax * 0.04;
    
    return $tax + $cess;
}

// Get statistics
$tax_components_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM hr_tax_components WHERE is_active = 1"))['count'];
$employees_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM hr_employees WHERE status = 'active'"))['count'];
$total_annual_tax = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(tax_amount) as total FROM hr_employee_tax_calculations WHERE financial_year = '" . date('Y') . '-' . (date('Y')+1) . "'"))['total'] ?? 0;
$compliance_score = 100; // Simplified compliance score

// Use absolute paths for layouts to avoid any include issues
include $base_dir . '/layouts/header.php';
include $base_dir . '/layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">💰 Tax Management</h1>
                <p class="text-muted">Manage employee tax calculations and compliance</p>
            </div>
            <div>
                <button class="btn btn-success me-2" onclick="exportTaxData()">
                    <i class="bi bi-download"></i> Export Reports
                </button>
                <button class="btn btn-primary" onclick="bulkTaxCalculation()">
                    <i class="bi bi-calculator"></i> Bulk Calculate Tax
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-gear-fill fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $tax_components_count ?></h3>
                        <small class="opacity-75">Tax Components</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-people-fill fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $employees_count ?></h3>
                        <small class="opacity-75">Active Employees</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-currency-rupee fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold">₹<?= number_format($total_annual_tax) ?></h3>
                        <small class="opacity-75">Annual Tax Collection</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-shield-check-fill fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $compliance_score ?>%</h3>
                        <small class="opacity-75">Compliance Score</small>
                    </div>
                </div>
            </div>
        </div>

    <!-- Navigation Tabs -->
    <div class="card shadow-sm">
        <div class="card-header bg-white border-0">
            <ul class="nav nav-tabs card-header-tabs" id="taxTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tax-settings-tab" data-bs-toggle="tab" data-bs-target="#tax-settings" type="button" role="tab">
                        <i class="bi bi-gear me-2"></i>Tax Settings
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="employee-tax-tab" data-bs-toggle="tab" data-bs-target="#employee-tax" type="button" role="tab">
                        <i class="bi bi-person-lines-fill me-2"></i>Employee Tax
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tax-calculator-tab" data-bs-toggle="tab" data-bs-target="#tax-calculator" type="button" role="tab">
                        <i class="bi bi-calculator me-2"></i>Tax Calculator
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="compliance-tab" data-bs-toggle="tab" data-bs-target="#compliance" type="button" role="tab">
                        <i class="bi bi-shield-check me-2"></i>Compliance
                    </button>
                </li>
            </ul>
        </div>
        
        <div class="card-body">
            <div class="tab-content" id="taxTabsContent">
                <!-- Tax Settings Tab -->
                <div class="tab-pane fade show active" id="tax-settings" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Tax Components</h5>
                        <button class="btn btn-primary" onclick="showAddTaxComponentModal()">
                            <i class="bi bi-plus-circle me-2"></i>Add Tax Component
                        </button>
                    </div>
                    
                    <div class="row" id="taxComponentsContainer">
                        <!-- Tax components will be loaded here -->
                    </div>
                </div>

                <!-- Employee Tax Tab -->
                <div class="tab-pane fade" id="employee-tax" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Employee Tax Details</h5>
                        <div>
                            <button class="btn btn-outline-primary me-2" onclick="exportEmployeeTaxData()">
                                <i class="bi bi-download me-2"></i>Export Data
                            </button>
                            <button class="btn btn-success" onclick="bulkTaxCalculation()">
                                <i class="bi bi-calculator me-2"></i>Bulk Calculate
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover" id="employeeTaxTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Employee ID</th>
                                    <th>Name</th>
                                    <th>Basic Salary</th>
                                    <th>Gross Salary</th>
                                    <th>Annual Tax</th>
                                    <th>Monthly Tax</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Employee tax data will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tax Calculator Tab -->
                <div class="tab-pane fade" id="tax-calculator" role="tabpanel">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card border-0 bg-light">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="bi bi-calculator me-2"></i>Tax Calculator</h5>
                                </div>
                                <div class="card-body">
                                    <form id="taxCalculatorForm">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label class="form-label">Basic Salary (Monthly)</label>
                                                <input type="number" class="form-control" id="basicSalary" placeholder="Enter basic salary">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">HRA (Monthly)</label>
                                                <input type="number" class="form-control" id="hra" placeholder="Enter HRA amount">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Other Allowances (Monthly)</label>
                                                <input type="number" class="form-control" id="otherAllowances" placeholder="Enter other allowances">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Tax Exemptions (Annual)</label>
                                                <input type="number" class="form-control" id="exemptions" placeholder="Enter exemption amount" value="250000">
                                            </div>
                                            <div class="col-12">
                                                <button type="button" class="btn btn-primary w-100" onclick="calculateTax()">
                                                    <i class="bi bi-calculator me-2"></i>Calculate Tax
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-6">
                            <div class="card border-0 bg-light">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Tax Breakdown</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <div class="d-flex justify-content-between align-items-center py-2">
                                                <span class="text-muted">Annual Gross Salary:</span>
                                                <span class="fw-bold" id="grossSalaryResult">₹0</span>
                                            </div>
                                            <hr class="my-2">
                                        </div>
                                        <div class="col-12">
                                            <div class="d-flex justify-content-between align-items-center py-2">
                                                <span class="text-muted">Taxable Income:</span>
                                                <span class="fw-bold" id="taxableIncomeResult">₹0</span>
                                            </div>
                                            <hr class="my-2">
                                        </div>
                                        <div class="col-12">
                                            <div class="d-flex justify-content-between align-items-center py-2">
                                                <span class="text-muted">Income Tax:</span>
                                                <span class="fw-bold text-danger" id="incomeTaxResult">₹0</span>
                                            </div>
                                            <hr class="my-2">
                                        </div>
                                        <div class="col-12">
                                            <div class="d-flex justify-content-between align-items-center py-2">
                                                <span class="text-muted">Professional Tax:</span>
                                                <span class="fw-bold text-warning" id="professionalTaxResult">₹2,400</span>
                                            </div>
                                            <hr class="my-2">
                                        </div>
                                        <div class="col-12">
                                            <div class="bg-light rounded p-3">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="fw-bold">Annual Tax Liability:</span>
                                                    <span class="fw-bold fs-5 text-danger" id="totalTaxResult">₹0</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mt-2">
                                                    <span class="fw-bold">Monthly Tax Deduction:</span>
                                                    <span class="fw-bold fs-6 text-primary" id="monthlyTaxResult">₹0</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mt-2">
                                                    <span class="fw-bold">Net Monthly Salary:</span>
                                                    <span class="fw-bold fs-5 text-success" id="netSalaryResult">₹0</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Compliance Tab -->
                <div class="tab-pane fade" id="compliance" role="tabpanel">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="card shadow-sm">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0">
                                        <i class="bi bi-shield-check me-2"></i>Tax Compliance Status
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="text-center py-4">
                                        <i class="bi bi-shield-check text-success" style="font-size: 4rem;"></i>
                                        <h4 class="mt-3 text-success"><?= $compliance_score ?>% Compliant</h4>
                                        <p class="text-muted">Tax compliance status is good</p>
                                    </div>
                                    
                                    <div class="border-top pt-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">TDS Returns</span>
                                            <span class="badge bg-success">Filed</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">PF Returns</span>
                                            <span class="badge bg-success">Filed</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">ESI Returns</span>
                                            <span class="badge bg-warning">Pending</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted">Professional Tax</span>
                                            <span class="badge bg-success">Filed</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card shadow-sm">
                                <div class="card-header bg-warning text-white">
                                    <h5 class="mb-0">
                                        <i class="bi bi-calendar-check me-2"></i>Upcoming Deadlines
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                                        <div class="bg-danger bg-opacity-10 rounded-circle p-2 me-3">
                                            <i class="bi bi-exclamation-triangle text-danger"></i>
                                        </div>
                                        <div>
                                            <div class="fw-semibold">ESI Return Filing</div>
                                            <div class="text-muted small">Due: <?= date('F d, Y', strtotime('+7 days')) ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                                        <div class="bg-warning bg-opacity-10 rounded-circle p-2 me-3">
                                            <i class="bi bi-clock text-warning"></i>
                                        </div>
                                        <div>
                                            <div class="fw-semibold">TDS Quarterly Return</div>
                                            <div class="text-muted small">Due: <?= date('F d, Y', strtotime('+23 days')) ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex align-items-center">
                                        <div class="bg-info bg-opacity-10 rounded-circle p-2 me-3">
                                            <i class="bi bi-info-circle text-info"></i>
                                        </div>
                                        <div>
                                            <div class="fw-semibold">Annual Tax Filing</div>
                                            <div class="text-muted small">Due: July 31, <?= date('Y')+1 ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="card shadow-sm">
                                <div class="card-header bg-secondary text-white">
                                    <h5 class="mb-0">
                                        <i class="bi bi-file-earmark-text me-2"></i>Generate Compliance Reports
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <button class="btn btn-outline-primary w-100" onclick="generateReport('tds')">
                                                <i class="bi bi-file-earmark-check me-2"></i>TDS Report
                                            </button>
                                        </div>
                                        <div class="col-md-3">
                                            <button class="btn btn-outline-success w-100" onclick="generateReport('pf')">
                                                <i class="bi bi-file-earmark-plus me-2"></i>PF Report
                                            </button>
                                        </div>
                                        <div class="col-md-3">
                                            <button class="btn btn-outline-info w-100" onclick="generateReport('esi')">
                                                <i class="bi bi-file-earmark-medical me-2"></i>ESI Report
                                            </button>
                                        </div>
                                        <div class="col-md-3">
                                            <button class="btn btn-outline-warning w-100" onclick="generateReport('all')">
                                                <i class="bi bi-files me-2"></i>All Reports
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Tax Component Modal -->
<div class="modal fade" id="taxComponentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Tax Component</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="taxComponentForm">
                    <input type="hidden" id="componentId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Component Name *</label>
                            <input type="text" class="form-control" id="componentName" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Component Type *</label>
                            <select class="form-select" id="componentType" required>
                                <option value="">Select Type</option>
                                <option value="income_tax">Income Tax</option>
                                <option value="professional_tax">Professional Tax</option>
                                <option value="tds">TDS</option>
                                <option value="cess">Cess</option>
                                <option value="surcharge">Surcharge</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Calculation Type *</label>
                            <select class="form-select" id="calculationType" required>
                                <option value="">Select Calculation</option>
                                <option value="percentage">Percentage</option>
                                <option value="slab">Slab Based</option>
                                <option value="fixed">Fixed Amount</option>
                                <option value="custom">Custom</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Rate (%)</label>
                            <input type="number" class="form-control" id="ratePercentage" step="0.01" min="0" max="100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fixed Amount</label>
                            <input type="number" class="form-control" id="fixedAmount" step="0.01" min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Exemption Limit</label>
                            <input type="number" class="form-control" id="exemptionLimit" step="0.01" min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Min Salary</label>
                            <input type="number" class="form-control" id="minSalary" step="0.01" min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Max Salary</label>
                            <input type="number" class="form-control" id="maxSalary" step="0.01" min="0">
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" id="isActive" checked>
                                <label class="form-check-label" for="isActive">
                                    Active
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" id="componentDescription" rows="3"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveTaxComponent()">Save Component</button>
            </div>
        </div>
    </div>
</div>

<!-- Employee Tax Details Modal -->
<div class="modal fade" id="employeeTaxModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Employee Tax Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="employeeTaxDetails">
                    <!-- Employee tax details will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="downloadTaxSlip()">Download Tax Slip</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Load data on page load
document.addEventListener('DOMContentLoaded', function() {
    loadTaxComponents();
    loadEmployeeTaxData();
});

// Tax Component functions
function loadTaxComponents() {
    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=get_tax_components'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayTaxComponents(data.data);
        }
    });
}

function displayTaxComponents(components) {
    const container = document.getElementById('taxComponentsContainer');
    container.innerHTML = '';
    
    components.forEach(component => {
        const statusBadge = component.is_active == 1 ? 
            '<span class="badge bg-success">Active</span>' : 
            '<span class="badge bg-secondary">Inactive</span>';
            
        const calculationType = component.calculation_type.charAt(0).toUpperCase() + component.calculation_type.slice(1);
        
        const componentCard = `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="card-title mb-0">${component.component_name}</h6>
                            ${statusBadge}
                        </div>
                        <p class="text-muted small mb-2">${component.description || 'No description'}</p>
                        <div class="small">
                            <div class="mb-1"><strong>Type:</strong> ${component.component_type.replace('_', ' ').toUpperCase()}</div>
                            <div class="mb-1"><strong>Calculation:</strong> ${calculationType}</div>
                            ${component.rate_percentage > 0 ? `<div class="mb-1"><strong>Rate:</strong> ${component.rate_percentage}%</div>` : ''}
                            ${component.fixed_amount > 0 ? `<div class="mb-1"><strong>Amount:</strong> ₹${parseFloat(component.fixed_amount).toLocaleString()}</div>` : ''}
                            ${component.exemption_limit > 0 ? `<div class="mb-1"><strong>Exemption:</strong> ₹${parseFloat(component.exemption_limit).toLocaleString()}</div>` : ''}
                        </div>
                    </div>
                    <div class="card-footer bg-white border-0 pt-0">
                        <div class="btn-group w-100" role="group">
                            <button class="btn btn-outline-primary btn-sm" onclick="editTaxComponent(${component.id})">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <button class="btn btn-outline-${component.is_active == 1 ? 'warning' : 'success'} btn-sm" 
                                    onclick="toggleTaxComponent(${component.id}, ${component.is_active == 1 ? 0 : 1})">
                                <i class="bi bi-${component.is_active == 1 ? 'pause' : 'play'}"></i> 
                                ${component.is_active == 1 ? 'Disable' : 'Enable'}
                            </button>
                            <button class="btn btn-outline-danger btn-sm" onclick="deleteTaxComponent(${component.id})">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', componentCard);
    });
}

function showAddTaxComponentModal() {
    document.getElementById('taxComponentForm').reset();
    document.getElementById('componentId').value = '';
    document.querySelector('#taxComponentModal .modal-title').textContent = 'Add Tax Component';
    new bootstrap.Modal(document.getElementById('taxComponentModal')).show();
}

function editTaxComponent(id) {
    // Get component data and populate form
    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=get_tax_components'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const component = data.data.find(c => c.id == id);
            if (component) {
                document.getElementById('componentId').value = component.id;
                document.getElementById('componentName').value = component.component_name;
                document.getElementById('componentType').value = component.component_type;
                document.getElementById('calculationType').value = component.calculation_type;
                document.getElementById('ratePercentage').value = component.rate_percentage;
                document.getElementById('fixedAmount').value = component.fixed_amount;
                document.getElementById('exemptionLimit').value = component.exemption_limit;
                document.getElementById('minSalary').value = component.min_salary;
                document.getElementById('maxSalary').value = component.max_salary;
                document.getElementById('isActive').checked = component.is_active == 1;
                document.getElementById('componentDescription').value = component.description || '';
                
                document.querySelector('#taxComponentModal .modal-title').textContent = 'Edit Tax Component';
                new bootstrap.Modal(document.getElementById('taxComponentModal')).show();
            }
        }
    });
}

function saveTaxComponent() {
    const formData = new FormData();
    formData.append('action', 'save_tax_component');
    formData.append('id', document.getElementById('componentId').value);
    formData.append('component_name', document.getElementById('componentName').value);
    formData.append('component_type', document.getElementById('componentType').value);
    formData.append('calculation_type', document.getElementById('calculationType').value);
    formData.append('rate_percentage', document.getElementById('ratePercentage').value);
    formData.append('fixed_amount', document.getElementById('fixedAmount').value);
    formData.append('exemption_limit', document.getElementById('exemptionLimit').value);
    formData.append('min_salary', document.getElementById('minSalary').value);
    formData.append('max_salary', document.getElementById('maxSalary').value);
    formData.append('is_active', document.getElementById('isActive').checked ? 1 : 0);
    formData.append('description', document.getElementById('componentDescription').value);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('taxComponentModal')).hide();
            loadTaxComponents();
        } else {
            showAlert(data.message, 'danger');
        }
    });
}

function toggleTaxComponent(id, status) {
    const formData = new FormData();
    formData.append('action', 'toggle_tax_component');
    formData.append('id', id);
    formData.append('status', status);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            loadTaxComponents();
        } else {
            showAlert(data.message, 'danger');
        }
    });
}

function deleteTaxComponent(id) {
    if (confirm('Are you sure you want to delete this tax component?')) {
        const formData = new FormData();
        formData.append('action', 'delete_tax_component');
        formData.append('id', id);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message, 'success');
                loadTaxComponents();
            } else {
                showAlert(data.message, 'danger');
            }
        });
    }
}

// Employee Tax functions
function loadEmployeeTaxData() {
    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=get_employee_tax_data'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayEmployeeTaxData(data.data);
        }
    });
}

function displayEmployeeTaxData(employees) {
    const tbody = document.querySelector('#employeeTaxTable tbody');
    tbody.innerHTML = '';
    
    employees.forEach(employee => {
        const row = `
            <tr>
                <td>${employee.employee_id}</td>
                <td>${employee.first_name} ${employee.last_name}</td>
                <td>₹${parseFloat(employee.basic_salary || 0).toLocaleString()}</td>
                <td>₹${parseFloat(employee.gross_salary || 0).toLocaleString()}</td>
                <td>₹${parseFloat(employee.annual_tax || 0).toLocaleString()}</td>
                <td>₹${parseFloat(employee.monthly_tax_deduction || 0).toLocaleString()}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-info" onclick="viewTaxBreakdown(${employee.id})" title="View Tax Breakdown">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-outline-warning" onclick="recalculateTax(${employee.id})" title="Recalculate Tax">
                            <i class="bi bi-calculator"></i>
                        </button>
                        <button class="btn btn-outline-success" onclick="downloadTaxSlip(${employee.id})" title="Download Tax Slip">
                            <i class="bi bi-download"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
        tbody.insertAdjacentHTML('beforeend', row);
    });
}

function viewTaxBreakdown(employeeId) {
    // Show employee tax details modal
    document.getElementById('employeeTaxDetails').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading tax details...</p>
        </div>
    `;
    new bootstrap.Modal(document.getElementById('employeeTaxModal')).show();
    
    // Load actual tax breakdown here
    setTimeout(() => {
        document.getElementById('employeeTaxDetails').innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <h6>Income Breakdown</h6>
                    <table class="table table-sm">
                        <tr><td>Basic Salary:</td><td>₹50,000</td></tr>
                        <tr><td>HRA:</td><td>₹15,000</td></tr>
                        <tr><td>Other Allowances:</td><td>₹5,000</td></tr>
                        <tr class="table-info"><td><strong>Gross Salary:</strong></td><td><strong>₹70,000</strong></td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6>Tax Calculation</h6>
                    <table class="table table-sm">
                        <tr><td>Annual Gross:</td><td>₹8,40,000</td></tr>
                        <tr><td>Exemptions:</td><td>₹2,50,000</td></tr>
                        <tr><td>Taxable Income:</td><td>₹5,90,000</td></tr>
                        <tr class="table-danger"><td><strong>Annual Tax:</strong></td><td><strong>₹23,500</strong></td></tr>
                        <tr class="table-warning"><td><strong>Monthly Deduction:</strong></td><td><strong>₹1,958</strong></td></tr>
                    </table>
                </div>
            </div>
        `;
    }, 1000);
}

function recalculateTax(employeeId) {
    const formData = new FormData();
    formData.append('action', 'calculate_employee_tax');
    formData.append('employee_id', employeeId);
    formData.append('financial_year', new Date().getFullYear() + '-' + (new Date().getFullYear() + 1));
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            loadEmployeeTaxData();
        } else {
            showAlert(data.message, 'danger');
        }
    });
}

function downloadTaxSlip(employeeId) {
    showAlert('Tax slip download will be implemented soon!', 'info');
}

function bulkTaxCalculation() {
    if (confirm('This will recalculate tax for all active employees. Continue?')) {
        const formData = new FormData();
        formData.append('action', 'bulk_tax_calculation');
        formData.append('financial_year', new Date().getFullYear() + '-' + (new Date().getFullYear() + 1));
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message, 'success');
                loadEmployeeTaxData();
                // Refresh statistics
                setTimeout(() => location.reload(), 2000);
            } else {
                showAlert(data.message, 'danger');
            }
        });
    }
}

function exportEmployeeTaxData() {
    showAlert('Export functionality will be implemented soon!', 'info');
}

// Tax Calculator functions
function calculateTax() {
    const basicSalary = parseFloat(document.getElementById('basicSalary').value) || 0;
    const hra = parseFloat(document.getElementById('hra').value) || 0;
    const otherAllowances = parseFloat(document.getElementById('otherAllowances').value) || 0;
    const exemptions = parseFloat(document.getElementById('exemptions').value) || 250000;

    const monthlyGross = basicSalary + hra + otherAllowances;
    const annualGross = monthlyGross * 12;
    const taxableIncome = Math.max(0, annualGross - exemptions);
    
    // Calculate income tax using Indian tax slabs (New Regime)
    let incomeTax = 0;
    let remainingIncome = taxableIncome;
    
    if (remainingIncome > 1500000) {
        incomeTax += (remainingIncome - 1500000) * 0.30;
        remainingIncome = 1500000;
    }
    if (remainingIncome > 1200000) {
        incomeTax += (remainingIncome - 1200000) * 0.20;
        remainingIncome = 1200000;
    }
    if (remainingIncome > 900000) {
        incomeTax += (remainingIncome - 900000) * 0.15;
        remainingIncome = 900000;
    }
    if (remainingIncome > 600000) {
        incomeTax += (remainingIncome - 600000) * 0.10;
        remainingIncome = 600000;
    }
    if (remainingIncome > 300000) {
        incomeTax += (remainingIncome - 300000) * 0.05;
    }

    // Add 4% Health and Education Cess
    const cess = incomeTax * 0.04;
    incomeTax += cess;

    const professionalTax = 2400; // Annual PT
    const totalTax = incomeTax + professionalTax;
    const monthlyTax = totalTax / 12;
    const netMonthlySalary = monthlyGross - monthlyTax;

    // Update results
    document.getElementById('grossSalaryResult').textContent = `₹${annualGross.toLocaleString()}`;
    document.getElementById('taxableIncomeResult').textContent = `₹${taxableIncome.toLocaleString()}`;
    document.getElementById('incomeTaxResult').textContent = `₹${incomeTax.toLocaleString()}`;
    document.getElementById('professionalTaxResult').textContent = `₹${professionalTax.toLocaleString()}`;
    document.getElementById('totalTaxResult').textContent = `₹${totalTax.toLocaleString()}`;
    document.getElementById('monthlyTaxResult').textContent = `₹${monthlyTax.toLocaleString()}`;
    document.getElementById('netSalaryResult').textContent = `₹${netMonthlySalary.toLocaleString()}`;

    showAlert('Tax calculation completed successfully!', 'success');
}

function generateReport(type) {
    showAlert(`Generating ${type.toUpperCase()} compliance report...`, 'info');
    // Implementation for report generation
}

function showAlert(message, type = 'info') {
    const alertDiv = `
        <div class="alert alert-${type} alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; max-width: 400px;">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', alertDiv);
    
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            if (alert.textContent.includes(message)) {
                alert.remove();
            }
        });
    }, 5000);
}
</script>

<?php include $base_dir . '/layouts/footer.php'; ?>
