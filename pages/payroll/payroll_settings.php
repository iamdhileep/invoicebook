<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Payroll Settings';

// Handle form submission for settings update
if ($_POST && isset($_POST['update_settings'])) {
    $settings = [
        'basic_salary_percentage' => floatval($_POST['basic_salary_percentage']),
        'hra_percentage' => floatval($_POST['hra_percentage']),
        'da_percentage' => floatval($_POST['da_percentage']),
        'allowances_percentage' => floatval($_POST['allowances_percentage']),
        'pf_rate' => floatval($_POST['pf_rate']),
        'esi_rate' => floatval($_POST['esi_rate']),
        'professional_tax_limit' => floatval($_POST['professional_tax_limit']),
        'professional_tax_amount' => floatval($_POST['professional_tax_amount']),
        'overtime_multiplier' => floatval($_POST['overtime_multiplier']),
        'working_hours_per_day' => intval($_POST['working_hours_per_day'])
    ];
    
    // Save settings to database (create settings table if not exists)
    $conn->query("CREATE TABLE IF NOT EXISTS payroll_settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        setting_key VARCHAR(100) UNIQUE,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    foreach ($settings as $key => $value) {
        $stmt = $conn->prepare("INSERT INTO payroll_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->bind_param("sss", $key, $value, $value);
        $stmt->execute();
    }
    
    $success_message = "Payroll settings updated successfully!";
}

// Get current settings
function getSetting($conn, $key, $default = 0) {
    $stmt = $conn->prepare("SELECT setting_value FROM payroll_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['setting_value'];
    }
    return $default;
}

// Default settings
$current_settings = [
    'basic_salary_percentage' => getSetting($conn, 'basic_salary_percentage', 60),
    'hra_percentage' => getSetting($conn, 'hra_percentage', 20),
    'da_percentage' => getSetting($conn, 'da_percentage', 10),
    'allowances_percentage' => getSetting($conn, 'allowances_percentage', 10),
    'pf_rate' => getSetting($conn, 'pf_rate', 12),
    'esi_rate' => getSetting($conn, 'esi_rate', 1.75),
    'professional_tax_limit' => getSetting($conn, 'professional_tax_limit', 10000),
    'professional_tax_amount' => getSetting($conn, 'professional_tax_amount', 200),
    'overtime_multiplier' => getSetting($conn, 'overtime_multiplier', 1.5),
    'working_hours_per_day' => getSetting($conn, 'working_hours_per_day', 8)
];

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="bi bi-gear text-primary"></i>
                            Payroll Settings
                        </h4>
                        <a href="payroll.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Payroll
                        </a>
                    </div>
                    
                    <div class="card-body">
                        <?php if (isset($success_message)): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="bi bi-check-circle"></i> <?= $success_message ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" class="row g-4">
                            <!-- Salary Components -->
                            <div class="col-12">
                                <h5 class="text-primary">
                                    <i class="bi bi-currency-rupee"></i> Salary Components (% of Monthly Salary)
                                </h5>
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label for="basic_salary_percentage" class="form-label">Basic Salary (%)</label>
                                        <input type="number" class="form-control" id="basic_salary_percentage" 
                                               name="basic_salary_percentage" step="0.1" min="0" max="100" 
                                               value="<?= $current_settings['basic_salary_percentage'] ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="hra_percentage" class="form-label">HRA (%)</label>
                                        <input type="number" class="form-control" id="hra_percentage" 
                                               name="hra_percentage" step="0.1" min="0" max="100" 
                                               value="<?= $current_settings['hra_percentage'] ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="da_percentage" class="form-label">Dearness Allowance (%)</label>
                                        <input type="number" class="form-control" id="da_percentage" 
                                               name="da_percentage" step="0.1" min="0" max="100" 
                                               value="<?= $current_settings['da_percentage'] ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="allowances_percentage" class="form-label">Other Allowances (%)</label>
                                        <input type="number" class="form-control" id="allowances_percentage" 
                                               name="allowances_percentage" step="0.1" min="0" max="100" 
                                               value="<?= $current_settings['allowances_percentage'] ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Deductions -->
                            <div class="col-12">
                                <h5 class="text-primary">
                                    <i class="bi bi-calculator"></i> Deduction Rates
                                </h5>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label for="pf_rate" class="form-label">Provident Fund (%)</label>
                                        <input type="number" class="form-control" id="pf_rate" 
                                               name="pf_rate" step="0.01" min="0" max="20" 
                                               value="<?= $current_settings['pf_rate'] ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="esi_rate" class="form-label">ESI Rate (%)</label>
                                        <input type="number" class="form-control" id="esi_rate" 
                                               name="esi_rate" step="0.01" min="0" max="10" 
                                               value="<?= $current_settings['esi_rate'] ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="professional_tax_limit" class="form-label">Professional Tax Limit (₹)</label>
                                        <input type="number" class="form-control" id="professional_tax_limit" 
                                               name="professional_tax_limit" min="0" 
                                               value="<?= $current_settings['professional_tax_limit'] ?>" required>
                                    </div>
                                </div>
                                <div class="row g-3 mt-2">
                                    <div class="col-md-4">
                                        <label for="professional_tax_amount" class="form-label">Professional Tax Amount (₹)</label>
                                        <input type="number" class="form-control" id="professional_tax_amount" 
                                               name="professional_tax_amount" min="0" 
                                               value="<?= $current_settings['professional_tax_amount'] ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Working Hours & Overtime -->
                            <div class="col-12">
                                <h5 class="text-primary">
                                    <i class="bi bi-clock"></i> Working Hours & Overtime
                                </h5>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label for="working_hours_per_day" class="form-label">Working Hours per Day</label>
                                        <input type="number" class="form-control" id="working_hours_per_day" 
                                               name="working_hours_per_day" min="1" max="24" 
                                               value="<?= $current_settings['working_hours_per_day'] ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="overtime_multiplier" class="form-label">Overtime Multiplier</label>
                                        <input type="number" class="form-control" id="overtime_multiplier" 
                                               name="overtime_multiplier" step="0.1" min="1" max="5" 
                                               value="<?= $current_settings['overtime_multiplier'] ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Submit Button -->
                            <div class="col-12">
                                <hr>
                                <button type="submit" name="update_settings" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> Update Settings
                                </button>
                                <button type="button" class="btn btn-outline-warning" onclick="resetToDefaults()">
                                    <i class="bi bi-arrow-clockwise"></i> Reset to Defaults
                                </button>
                            </div>
                        </form>
                        
                        <!-- Preview Section -->
                        <div class="col-12 mt-5">
                            <h5 class="text-primary">
                                <i class="bi bi-eye"></i> Settings Preview
                            </h5>
                            <div class="card bg-light">
                                <div class="card-body">
                                    <p><strong>For a ₹50,000 monthly salary:</strong></p>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6>Earnings:</h6>
                                            <ul class="list-unstyled">
                                                <li>Basic Salary: ₹<?= number_format(50000 * ($current_settings['basic_salary_percentage']/100), 2) ?></li>
                                                <li>HRA: ₹<?= number_format(50000 * ($current_settings['hra_percentage']/100), 2) ?></li>
                                                <li>DA: ₹<?= number_format(50000 * ($current_settings['da_percentage']/100), 2) ?></li>
                                                <li>Allowances: ₹<?= number_format(50000 * ($current_settings['allowances_percentage']/100), 2) ?></li>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <h6>Deductions:</h6>
                                            <ul class="list-unstyled">
                                                <li>PF: ₹<?= number_format((50000 * ($current_settings['basic_salary_percentage']/100)) * ($current_settings['pf_rate']/100), 2) ?></li>
                                                <li>ESI: ₹<?= number_format(50000 * ($current_settings['esi_rate']/100), 2) ?></li>
                                                <li>Professional Tax: ₹<?= $current_settings['professional_tax_amount'] ?></li>
                                            </ul>
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

<script>
function resetToDefaults() {
    if (confirm('Reset all settings to default values?')) {
        document.getElementById('basic_salary_percentage').value = '60';
        document.getElementById('hra_percentage').value = '20';
        document.getElementById('da_percentage').value = '10';
        document.getElementById('allowances_percentage').value = '10';
        document.getElementById('pf_rate').value = '12';
        document.getElementById('esi_rate').value = '1.75';
        document.getElementById('professional_tax_limit').value = '10000';
        document.getElementById('professional_tax_amount').value = '200';
        document.getElementById('overtime_multiplier').value = '1.5';
        document.getElementById('working_hours_per_day').value = '8';
    }
}
</script>

<?php include '../../layouts/footer.php'; ?>
