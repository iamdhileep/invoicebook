<?php
/**
 * 🇮🇳 HRMS Indian Labor Law Compliance Module
 * Complete implementation of PF, ESI, Gratuity, Professional Tax, TDS
 */

if (!isset($root_path)) 
require_once '../db.php';

class IndianComplianceManager {
    private $conn;
    
    // Indian compliance rates (as of 2024-25)
    private $compliance_rates = [
        'pf' => [
            'employee_rate' => 0.12,    // 12%
            'employer_rate' => 0.12,    // 12%
            'max_salary' => 15000       // PF ceiling
        ],
        'esi' => [
            'employee_rate' => 0.0075,  // 0.75%
            'employer_rate' => 0.0325,  // 3.25%
            'max_salary' => 21000       // ESI ceiling
        ],
        'professional_tax' => [
            'maharashtra' => [
                ['min' => 0, 'max' => 5000, 'tax' => 0],
                ['min' => 5001, 'max' => 10000, 'tax' => 150],
                ['min' => 10001, 'max' => 15000, 'tax' => 300],
                ['min' => 15001, 'max' => 999999, 'tax' => 500]
            ],
            'karnataka' => [
                ['min' => 0, 'max' => 15000, 'tax' => 200],
                ['min' => 15001, 'max' => 999999, 'tax' => 300]
            ]
        ],
        'tds' => [
            'old_regime' => [
                ['min' => 0, 'max' => 250000, 'rate' => 0],
                ['min' => 250001, 'max' => 500000, 'rate' => 0.05],
                ['min' => 500001, 'max' => 1000000, 'rate' => 0.20],
                ['min' => 1000001, 'max' => 999999999, 'rate' => 0.30]
            ],
            'new_regime' => [
                ['min' => 0, 'max' => 300000, 'rate' => 0],
                ['min' => 300001, 'max' => 600000, 'rate' => 0.05],
                ['min' => 600001, 'max' => 900000, 'rate' => 0.10],
                ['min' => 900001, 'max' => 1200000, 'rate' => 0.15],
                ['min' => 1200001, 'max' => 1500000, 'rate' => 0.20],
                ['min' => 1500001, 'max' => 999999999, 'rate' => 0.30]
            ]
        ]
    ];
    
    public function __construct($connection) {
        $this->conn = $connection;
        $this->initializeComplianceTables();
    }
    
    /**
     * Create compliance tables if they don't exist
     */
    private function initializeComplianceTables() {
        $tables = [
            'employee_compliance' => "
                CREATE TABLE IF NOT EXISTS employee_compliance (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    employee_id INT NOT NULL,
                    pf_number VARCHAR(50),
                    esi_number VARCHAR(50),
                    uan_number VARCHAR(12),
                    pan_number VARCHAR(10),
                    aadhar_number VARCHAR(12),
                    bank_account VARCHAR(50),
                    ifsc_code VARCHAR(11),
                    joining_date DATE,
                    probation_period INT DEFAULT 6,
                    confirmation_date DATE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (employee_id) REFERENCES employees(id)
                )",
            
            'payroll_calculations' => "
                CREATE TABLE IF NOT EXISTS hr_payroll_calculations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    employee_id INT NOT NULL,
                    pay_period VARCHAR(7), -- YYYY-MM format
                    basic_salary DECIMAL(10,2),
                    hra DECIMAL(10,2),
                    da DECIMAL(10,2),
                    special_allowance DECIMAL(10,2),
                    overtime_amount DECIMAL(10,2),
                    gross_salary DECIMAL(10,2),
                    pf_employee DECIMAL(10,2),
                    pf_employer DECIMAL(10,2),
                    esi_employee DECIMAL(10,2),
                    esi_employer DECIMAL(10,2),
                    professional_tax DECIMAL(10,2),
                    tds_amount DECIMAL(10,2),
                    other_deductions DECIMAL(10,2),
                    net_salary DECIMAL(10,2),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (employee_id) REFERENCES employees(id)
                )",
            
            'gratuity_calculations' => "
                CREATE TABLE IF NOT EXISTS gratuity_calculations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    employee_id INT NOT NULL,
                    years_of_service DECIMAL(4,2),
                    last_drawn_salary DECIMAL(10,2),
                    gratuity_amount DECIMAL(10,2),
                    calculated_date DATE,
                    is_paid BOOLEAN DEFAULT FALSE,
                    payment_date DATE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (employee_id) REFERENCES employees(id)
                )",
            
            'leave_entitlements' => "
                CREATE TABLE IF NOT EXISTS leave_entitlements (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    employee_id INT NOT NULL,
                    year INT,
                    earned_leave INT DEFAULT 21,
                    casual_leave INT DEFAULT 7,
                    sick_leave INT DEFAULT 7,
                    maternity_leave INT DEFAULT 182,
                    paternity_leave INT DEFAULT 15,
                    earned_leave_used INT DEFAULT 0,
                    casual_leave_used INT DEFAULT 0,
                    sick_leave_used INT DEFAULT 0,
                    maternity_leave_used INT DEFAULT 0,
                    paternity_leave_used INT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (employee_id) REFERENCES employees(id)
                )"
        ];
        
        foreach ($tables as $table_name => $sql) {
            mysqli_query($this->conn, $sql);
        }
    }
    
    /**
     * Calculate PF (Provident Fund) contribution
     */
    public function calculatePF($basic_salary, $da = 0) {
        $pf_salary = min($basic_salary + $da, $this->compliance_rates['pf']['max_salary']);
        
        $employee_pf = $pf_salary * $this->compliance_rates['pf']['employee_rate'];
        $employer_pf = $pf_salary * $this->compliance_rates['pf']['employer_rate'];
        
        return [
            'employee_pf' => round($employee_pf, 2),
            'employer_pf' => round($employer_pf, 2),
            'total_pf' => round($employee_pf + $employer_pf, 2),
            'pf_salary' => $pf_salary
        ];
    }
    
    /**
     * Calculate ESI (Employee State Insurance) contribution
     */
    public function calculateESI($gross_salary) {
        if ($gross_salary > $this->compliance_rates['esi']['max_salary']) {
            return [
                'employee_esi' => 0,
                'employer_esi' => 0,
                'total_esi' => 0,
                'applicable' => false
            ];
        }
        
        $employee_esi = $gross_salary * $this->compliance_rates['esi']['employee_rate'];
        $employer_esi = $gross_salary * $this->compliance_rates['esi']['employer_rate'];
        
        return [
            'employee_esi' => round($employee_esi, 2),
            'employer_esi' => round($employer_esi, 2),
            'total_esi' => round($employee_esi + $employer_esi, 2),
            'applicable' => true
        ];
    }
    
    /**
     * Calculate Professional Tax based on state
     */
    public function calculateProfessionalTax($monthly_gross, $state = 'maharashtra') {
        $state = strtolower($state);
        $pt_slabs = $this->compliance_rates['professional_tax'][$state] ?? 
                   $this->compliance_rates['professional_tax']['maharashtra'];
        
        foreach ($pt_slabs as $slab) {
            if ($monthly_gross >= $slab['min'] && $monthly_gross <= $slab['max']) {
                return [
                    'professional_tax' => $slab['tax'],
                    'slab_applied' => $slab
                ];
            }
        }
        
        return ['professional_tax' => 0, 'slab_applied' => null];
    }
    
    /**
     * Calculate TDS (Tax Deducted at Source)
     */
    public function calculateTDS($annual_salary, $regime = 'new_regime', $investments = 0) {
        $taxable_income = $annual_salary - $investments;
        $tds_slabs = $this->compliance_rates['tds'][$regime];
        
        $total_tax = 0;
        $remaining_income = $taxable_income;
        
        foreach ($tds_slabs as $slab) {
            if ($remaining_income <= 0) break;
            
            $slab_income = min($remaining_income, $slab['max'] - $slab['min'] + 1);
            $slab_tax = $slab_income * $slab['rate'];
            $total_tax += $slab_tax;
            
            $remaining_income -= $slab_income;
            
            if ($remaining_income <= 0) break;
        }
        
        // Add health and education cess (4%)
        $cess = $total_tax * 0.04;
        $total_tax_with_cess = $total_tax + $cess;
        
        return [
            'annual_tax' => round($total_tax_with_cess, 2),
            'monthly_tds' => round($total_tax_with_cess / 12, 2),
            'effective_rate' => round(($total_tax_with_cess / $annual_salary) * 100, 2),
            'regime_used' => $regime
        ];
    }
    
    /**
     * Calculate Gratuity
     */
    public function calculateGratuity($employee_id, $last_salary = null) {
        // Get employee details
        $query = "SELECT e.*, ec.joining_date 
                  FROM hr_employees e 
                  LEFT JOIN employee_compliance ec ON e.id = ec.employee_id 
                  WHERE e.id = ?";
        
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $employee_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (!$row = mysqli_fetch_assoc($result)) {
            return null;
        }
        
        $joining_date = new DateTime($row['joining_date'] ?? $row['created_at']);
        $current_date = new DateTime();
        $years_of_service = $current_date->diff($joining_date)->y;
        
        // Gratuity is applicable after 5 years of service
        if ($years_of_service < 5) {
            return [
                'eligible' => false,
                'years_of_service' => $years_of_service,
                'gratuity_amount' => 0,
                'reason' => 'Minimum 5 years of service required'
            ];
        }
        
        $last_drawn_salary = $last_salary ?? $this->getLastDrawnSalary($employee_id);
        
        // Gratuity calculation: (15 days salary × years of service) / 26
        // For organizations covered under Gratuity Act
        $gratuity_amount = ($last_drawn_salary * 15 * $years_of_service) / 26;
        
        // Maximum gratuity limit (as of 2024): Rs. 20,00,000
        $gratuity_amount = min($gratuity_amount, 2000000);
        
        return [
            'eligible' => true,
            'years_of_service' => $years_of_service,
            'last_drawn_salary' => $last_drawn_salary,
            'gratuity_amount' => round($gratuity_amount, 2),
            'calculated_on' => date('Y-m-d')
        ];
    }
    
    /**
     * Get last drawn salary for gratuity calculation
     */
    private function getLastDrawnSalary($employee_id) {
        $query = "SELECT basic_salary FROM hr_payroll_calculations 
                  WHERE employee_id = ? 
                  ORDER BY pay_period DESC LIMIT 1";
        
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $employee_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            return $row['basic_salary'];
        }
        
        // Fallback to employee table
        $query = "SELECT salary FROM hr_employees WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $employee_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            return $row['salary'];
        }
        
        return 0;
    }
    
    /**
     * Complete payroll calculation with all compliances
     */
    public function calculateCompletePayroll($employee_id, $pay_period, $basic_salary, $allowances = []) {
        // Calculate components
        $hra = $allowances['hra'] ?? ($basic_salary * 0.50); // 50% of basic
        $da = $allowances['da'] ?? ($basic_salary * 0.10);   // 10% of basic
        $special_allowance = $allowances['special'] ?? 0;
        $overtime = $allowances['overtime'] ?? 0;
        
        $gross_salary = $basic_salary + $hra + $da + $special_allowance + $overtime;
        
        // Calculate deductions
        $pf_calc = $this->calculatePF($basic_salary, $da);
        $esi_calc = $this->calculateESI($gross_salary);
        $pt_calc = $this->calculateProfessionalTax($gross_salary);
        
        // Annual salary for TDS
        $annual_gross = $gross_salary * 12;
        $tds_calc = $this->calculateTDS($annual_gross);
        
        $total_deductions = $pf_calc['employee_pf'] + 
                           $esi_calc['employee_esi'] + 
                           $pt_calc['professional_tax'] + 
                           $tds_calc['monthly_tds'];
        
        $net_salary = $gross_salary - $total_deductions;
        
        // Insert into database
        $query = "INSERT INTO hr_payroll_calculations 
                  (employee_id, pay_period, basic_salary, hra, da, special_allowance, 
                   overtime_amount, gross_salary, pf_employee, pf_employer, 
                   esi_employee, esi_employer, professional_tax, tds_amount, 
                   other_deductions, net_salary) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE
                  basic_salary = VALUES(basic_salary),
                  hra = VALUES(hra),
                  da = VALUES(da),
                  special_allowance = VALUES(special_allowance),
                  overtime_amount = VALUES(overtime_amount),
                  gross_salary = VALUES(gross_salary),
                  pf_employee = VALUES(pf_employee),
                  pf_employer = VALUES(pf_employer),
                  esi_employee = VALUES(esi_employee),
                  esi_employer = VALUES(esi_employer),
                  professional_tax = VALUES(professional_tax),
                  tds_amount = VALUES(tds_amount),
                  net_salary = VALUES(net_salary)";
        
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "isdddddddddddddd", 
            $employee_id, $pay_period, $basic_salary, $hra, $da, $special_allowance,
            $overtime, $gross_salary, $pf_calc['employee_pf'], $pf_calc['employer_pf'],
            $esi_calc['employee_esi'], $esi_calc['employer_esi'], 
            $pt_calc['professional_tax'], $tds_calc['monthly_tds'], 0, $net_salary
        );
        
        mysqli_stmt_execute($stmt);
        
        return [
            'employee_id' => $employee_id,
            'pay_period' => $pay_period,
            'earnings' => [
                'basic_salary' => $basic_salary,
                'hra' => $hra,
                'da' => $da,
                'special_allowance' => $special_allowance,
                'overtime' => $overtime,
                'gross_salary' => $gross_salary
            ],
            'deductions' => [
                'pf_employee' => $pf_calc['employee_pf'],
                'esi_employee' => $esi_calc['employee_esi'],
                'professional_tax' => $pt_calc['professional_tax'],
                'tds_amount' => $tds_calc['monthly_tds'],
                'total_deductions' => $total_deductions
            ],
            'employer_contributions' => [
                'pf_employer' => $pf_calc['employer_pf'],
                'esi_employer' => $esi_calc['employer_esi']
            ],
            'net_salary' => $net_salary
        ];
    }
    
    /**
     * Initialize leave entitlements for an employee
     */
    public function initializeLeaveEntitlements($employee_id, $year = null) {
        $year = $year ?? date('Y');
        
        $query = "INSERT INTO leave_entitlements (employee_id, year) 
                  VALUES (?, ?) 
                  ON DUPLICATE KEY UPDATE year = year";
        
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "ii", $employee_id, $year);
        
        return mysqli_stmt_execute($stmt);
    }
    
    /**
     * Generate compliance report
     */
    public function generateComplianceReport($employee_id, $period) {
        $query = "SELECT pc.*, e.name, e.employee_id as emp_code, ec.pf_number, ec.esi_number
                  FROM hr_payroll_calculations pc
                  JOIN hr_employees e ON pc.employee_id = e.id
                  LEFT JOIN employee_compliance ec ON e.id = ec.employee_id
                  WHERE pc.employee_id = ? AND pc.pay_period = ?";
        
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "is", $employee_id, $period);
        mysqli_stmt_execute($stmt);
        
        return mysqli_stmt_get_result($stmt);
    }
}

// Initialize compliance manager
$compliance = new IndianComplianceManager($conn);

echo "🇮🇳 Indian Labor Law Compliance System Initialized!\n";
echo "✅ PF, ESI, Gratuity, Professional Tax, TDS calculations ready.\n";


require_once '../layouts/footer.php';
?>