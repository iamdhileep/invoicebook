<?php
// Advanced Payslip Generation System
// Enhanced version with multiple templates, export formats, and advanced features

// Start output buffering to prevent header issues
ob_start();

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

// Advanced error handling and logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '../../logs/payslip_errors.log');

include '../../db.php';

// Create logs directory if not exists
if (!is_dir('../../logs')) {
    mkdir('../../logs', 0755, true);
}

// Check database connection
if (!$conn) {
    error_log("Database connection failed: " . mysqli_connect_error());
    die("Database connection failed. Please contact administrator.");
}

// Advanced parameter handling with validation
$employee_id = filter_input(INPUT_GET, 'employee_id', FILTER_VALIDATE_INT);
$month = filter_input(INPUT_GET, 'month', FILTER_SANITIZE_STRING) ?? date('Y-m');
$print_mode = filter_input(INPUT_GET, 'print', FILTER_VALIDATE_BOOLEAN);
$export_format = filter_input(INPUT_GET, 'format', FILTER_SANITIZE_STRING) ?? 'html';
$template = filter_input(INPUT_GET, 'template', FILTER_SANITIZE_STRING) ?? 'modern';
$language = filter_input(INPUT_GET, 'lang', FILTER_SANITIZE_STRING) ?? 'en';

// Validate month format
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}

// Log payslip generation attempt
error_log("Payslip generation attempt - Employee: $employee_id, Month: $month, Template: $template");

// Function to get company settings
function getCompanySettings($conn) {
    $settings = [
        'company_name' => 'Business Management System',
        'company_address' => 'Business Address Line 1, City, State - 123456',
        'company_phone' => '+91 12345 67890',
        'company_email' => 'hr@company.com',
        'company_logo' => '../../assets/img/logo.png',
        'company_website' => 'www.company.com'
    ];
    
    // Try to get from database
    $result = $conn->query("SELECT * FROM company_settings LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $dbSettings = $result->fetch_assoc();
        $settings = array_merge($settings, $dbSettings);
    }
    
    return $settings;
}

// Function to get payroll settings
function getPayrollSettings($conn) {
    $settings = [
        'basic_salary_percentage' => 60,
        'hra_percentage' => 20,
        'da_percentage' => 10,
        'allowances_percentage' => 10,
        'pf_rate' => 12,
        'esi_rate' => 1.75,
        'professional_tax_limit' => 10000,
        'professional_tax_amount' => 200,
        'overtime_multiplier' => 1.5,
        'working_hours_per_day' => 8
    ];
    
    // Get from database
    $result = $conn->query("SELECT setting_key, setting_value FROM payroll_settings");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = floatval($row['setting_value']);
        }
    }
    
    return $settings;
}

if (!$employee_id) {
    // Enhanced employee selection page with global UI integration
    $page_title = "Generate Payslip";
    include '../../layouts/header.php';
    include '../../layouts/sidebar.php';
    ?>
    
<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">💰 Payslip Generator</h1>
                <p class="text-muted">Generate and manage employee payslips</p>
            </div>
            <div>
                <button class="btn btn-success me-2" onclick="generateBulkPayslips()">
                    <i class="bi bi-files"></i> Bulk Generate
                </button>
                <a href="../../pages/payroll/" class="btn btn-primary">
                    <i class="bi bi-arrow-left"></i> Back to Payroll
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-people fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?php 
                            $totalEmp = $conn->query("SELECT COUNT(*) as count FROM employees WHERE status = 'active'")->fetch_assoc()['count'];
                            echo $totalEmp;
                        ?></h3>
                        <small class="opacity-75">Active Employees</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-receipt fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?php 
                            $currentMonth = date('Y-m');
                            $monthlyPayslips = $conn->query("SELECT COUNT(*) as count FROM payslips WHERE DATE_FORMAT(pay_date, '%Y-%m') = '$currentMonth'")->fetch_assoc()['count'] ?? 0;
                            echo $monthlyPayslips;
                        ?></h3>
                        <small class="opacity-75">This Month's Payslips</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-currency-rupee fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold">₹<?php 
                            $totalSalary = $conn->query("SELECT SUM(monthly_salary) as total FROM employees WHERE status = 'active'")->fetch_assoc()['total'] ?? 0;
                            echo number_format($totalSalary, 0);
                        ?></h3>
                        <small class="opacity-75">Monthly Payroll</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-calendar-check fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= date('M Y') ?></h3>
                        <small class="opacity-75">Current Period</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <button class="btn btn-outline-primary w-100" onclick="showPayrollSummary()">
                    <i class="bi bi-bar-chart me-2"></i>Payroll Summary
                </button>
            </div>
            <div class="col-md-3">
                <button class="btn btn-outline-success w-100" onclick="exportAllPayslips()">
                    <i class="bi bi-download me-2"></i>Export All
                </button>
            </div>
            <div class="col-md-3">
                <button class="btn btn-outline-info w-100" onclick="showTemplateOptions()">
                    <i class="bi bi-file-earmark-text me-2"></i>Templates
                </button>
            </div>
            <div class="col-md-3">
                <button class="btn btn-outline-warning w-100" onclick="showPayrollSettings()">
                    <i class="bi bi-gear me-2"></i>Settings
                </button>
            </div>
        </div>
        <!-- Payslip Generation Form -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light">
                <div class="row align-items-center">
                    <div class="col">
                        <h5 class="mb-0">
                            <i class="bi bi-receipt me-2"></i>Generate Payslip
                        </h5>
                        <small class="text-muted">Select employee and period to generate payslip</small>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" id="payslipForm">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label for="employee_id" class="form-label fw-semibold">
                                <i class="bi bi-person-circle text-primary me-1"></i> Select Employee
                            </label>
                            <select class="form-select" id="employee_id" name="employee_id" required>
                                        <option value="">Choose an employee...</option>
                                        <?php
                                        $employees = $conn->query("SELECT employee_id, name, employee_code, department_name, monthly_salary FROM employees WHERE status = 'active' ORDER BY name");
                                        while ($emp = $employees->fetch_assoc()):
                                        ?>
                                        <option value="<?= $emp['employee_id'] ?>" 
                                                data-department="<?= htmlspecialchars($emp['department_name'] ?? 'N/A') ?>"
                                                data-salary="<?= number_format($emp['monthly_salary'], 2) ?>">
                                            <?= htmlspecialchars($emp['name']) ?> (<?= htmlspecialchars($emp['employee_code']) ?>) - ₹<?= number_format($emp['monthly_salary'], 2) ?>
                                        </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="month" class="form-label fw-semibold">
                                        <i class="bi bi-calendar3 text-primary me-1"></i> Pay Period
                                    </label>
                                    <input type="month" class="form-control" id="month" name="month" value="<?= date('Y-m') ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="template" class="form-label fw-semibold">
                                        <i class="bi bi-palette text-primary me-1"></i> Template Style
                                    </label>
                                    <select class="form-select" id="template" name="template">
                                        <option value="modern">Modern Professional</option>
                                        <option value="classic">Classic Corporate</option>
                                        <option value="minimal">Minimal Clean</option>
                                        <option value="colorful">Colorful Creative</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="format" class="form-label fw-semibold">
                                        <i class="bi bi-file-earmark text-primary me-1"></i> Output Format
                                    </label>
                                    <select class="form-select" id="format" name="format">
                                        <option value="html">HTML Preview</option>
                                        <option value="pdf">PDF Download</option>
                                        <option value="excel">Excel Export</option>
                                        <option value="csv">CSV Export</option>
                                    </select>
                                    </div>
                                </div>
                                
                                <!-- Employee Preview Card -->
                                <div id="employeePreview" class="card mt-4 d-none">
                                    <div class="card-body">
                                        <h6 class="card-title text-primary">Employee Preview</h6>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <small class="text-muted">Name:</small><br>
                                                <span id="preview-name">-</span>
                                            </div>
                                            <div class="col-md-4">
                                                <small class="text-muted">Department:</small><br>
                                                <span id="preview-department">-</span>
                                            </div>
                                            <div class="col-md-4">
                                                <small class="text-muted">Monthly Salary:</small><br>
                                                <span id="preview-salary" class="fw-bold text-success">-</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Generate Button -->
                                <div class="col-12">
                                    <div class="row g-3 mt-4">
                                        <div class="col-md-6">
                                            <div class="d-grid">
                                                <button type="submit" class="btn btn-primary btn-lg">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    Generate Payslip
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-grid">
                                                <button type="button" class="btn btn-outline-secondary btn-lg" onclick="generateSample()">
                                                    <i class="bi bi-eye me-2"></i>
                                                    Preview Sample
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/css/select2.min.css" rel="stylesheet">

<script>
$(document).ready(function() {
    // Initialize Select2 for better employee selection
    $('#employee_id').select2({
        placeholder: 'Search for an employee...',
        allowClear: true
    });
    
    // Employee preview
    $('#employee_id').on('change', function() {
        const selectedOption = $(this).find(':selected');
        if (selectedOption.val()) {
            $('#preview-name').text(selectedOption.text().split(' (')[0]);
            $('#preview-department').text(selectedOption.data('department'));
            $('#preview-salary').text('₹' + selectedOption.data('salary'));
            $('#employeePreview').removeClass('d-none');
        } else {
            $('#employeePreview').addClass('d-none');
        }
    });
    
    // Form submission with loading state
    $('#payslipForm').on('submit', function() {
        const submitBtn = $(this).find('button[type="submit"]');
        submitBtn.html('<i class="bi bi-hourglass-split me-2"></i>Generating...');
        submitBtn.prop('disabled', true);
    });
});

function generateSample() {
    const firstEmployee = $('#employee_id option:nth-child(2)').val();
    if (firstEmployee) {
        window.open(`generate_payslip.php?employee_id=${firstEmployee}&month=${$('#month').val()}&template=modern`, '_blank');
    } else {
        alert('No employees found in the system.');
    }
}

function generateBulkPayslips() {
    alert('Bulk payslip generation feature coming soon!');
    // Here you can implement bulk generation logic
}

function showPayrollSummary() {
    alert('Payroll summary report feature coming soon!');
    // Here you can implement payroll summary modal/page
}

function exportAllPayslips() {
    alert('Export all payslips feature coming soon!');
    // Here you can implement export functionality
}

function showTemplateOptions() {
    alert('Template selection feature coming soon!');
    // Here you can implement template selection modal
}

function showPayrollSettings() {
    window.open('payroll_settings.php', '_blank');
}
</script>

<?php
include '../../layouts/footer.php';
exit;
}

// Parse month (format: YYYY-MM)
$monthParts = explode('-', $month);
$year = $monthParts[0];
$monthNum = $monthParts[1];

// Get company and payroll settings
$companySettings = getCompanySettings($conn);
$payrollSettings = getPayrollSettings($conn);

// Enhanced employee query with more details
$employeeQuery = $conn->prepare("
    SELECT e.*, d.department_name as dept_name 
    FROM employees e 
    LEFT JOIN departments d ON e.department_id = d.department_id 
    WHERE e.employee_id = ?
");

if (!$employeeQuery) {
    error_log("Query preparation failed: " . $conn->error);
    die("System error occurred. Please contact administrator.");
}

$employeeQuery->bind_param("i", $employee_id);
if (!$employeeQuery->execute()) {
    error_log("Query execution failed: " . $employeeQuery->error);
    die("Failed to fetch employee data.");
}

$employee = $employeeQuery->get_result()->fetch_assoc();

if (!$employee) {
    die("Employee not found with ID: $employee_id");
}

// Enhanced employee data processing
$employeeName = $employee['name'] ?? $employee['employee_name'] ?? 'N/A';
$employeeCode = $employee['employee_code'] ?? $employee['code'] ?? 'N/A';
$position = $employee['position'] ?? $employee['designation'] ?? 'N/A';
$department = $employee['department_name'] ?? $employee['dept_name'] ?? 'N/A';
$monthlySalary = floatval($employee['monthly_salary'] ?? $employee['salary'] ?? 0);
$joiningDate = $employee['joining_date'] ?? $employee['created_at'] ?? date('Y-m-d');
$bankAccount = $employee['bank_account'] ?? $employee['account_number'] ?? 'N/A';
$panNumber = $employee['pan_number'] ?? 'N/A';
$employeePhoto = $employee['photo'] ?? '';

// Calculate payroll details with advanced logic
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $monthNum, $year);

// Enhanced attendance calculation
$presentDays = 0;
$absentDays = 0;
$lateDays = 0;
$halfDays = 0;
$overtimeHours = 0;
$totalWorkingHours = 0;

// Advanced attendance query
$attendanceQuery = $conn->prepare("
    SELECT 
        attendance_date,
        check_in, check_out,
        punch_in_time, punch_out_time,
        time_in, time_out,
        status,
        work_duration,
        overtime_hours,
        break_time
    FROM attendance 
    WHERE employee_id = ? 
    AND MONTH(attendance_date) = ? 
    AND YEAR(attendance_date) = ?
    ORDER BY attendance_date
");

if ($attendanceQuery) {
    $attendanceQuery->bind_param("iii", $employee_id, $monthNum, $year);
    $attendanceQuery->execute();
    $attendanceResult = $attendanceQuery->get_result();
    
    while ($att = $attendanceResult->fetch_assoc()) {
        $checkIn = $att['check_in'] ?? $att['punch_in_time'] ?? $att['time_in'] ?? '';
        $checkOut = $att['check_out'] ?? $att['punch_out_time'] ?? $att['time_out'] ?? '';
        $status = strtolower($att['status'] ?? '');
        $workDuration = floatval($att['work_duration'] ?? 0);
        $overtimeFromDb = floatval($att['overtime_hours'] ?? 0);
        
        if (!empty($checkIn) || in_array($status, ['present', 'half day', 'late'])) {
            if ($status == 'half day' || $status == 'half-day') {
                $halfDays++;
                $presentDays += 0.5;
            } else {
                $presentDays++;
            }
            
            if ($status == 'late') {
                $lateDays++;
            }
            
            // Calculate working hours and overtime
            if (!empty($checkIn) && !empty($checkOut)) {
                try {
                    $punchIn = new DateTime($checkIn);
                    $punchOut = new DateTime($checkOut);
                    $hoursWorked = $punchIn->diff($punchOut)->h + ($punchIn->diff($punchOut)->i / 60);
                    
                    $totalWorkingHours += $hoursWorked;
                    
                    if ($hoursWorked > $payrollSettings['working_hours_per_day']) {
                        $overtimeHours += ($hoursWorked - $payrollSettings['working_hours_per_day']);
                    }
                } catch (Exception $e) {
                    error_log("Date parsing error for employee $employee_id: " . $e->getMessage());
                }
            }
            
            if ($workDuration > 0) {
                $totalWorkingHours += $workDuration;
            }
            
            if ($overtimeFromDb > 0) {
                $overtimeHours += $overtimeFromDb;
            }
        }
    }
}

$absentDays = $daysInMonth - $presentDays;

// Advanced salary calculations
$perDaySalary = $monthlySalary / $daysInMonth;
$perHourSalary = $perDaySalary / $payrollSettings['working_hours_per_day'];

// Salary components based on settings
$basicSalary = $monthlySalary * ($payrollSettings['basic_salary_percentage'] / 100);
$hra = $monthlySalary * ($payrollSettings['hra_percentage'] / 100);
$da = $monthlySalary * ($payrollSettings['da_percentage'] / 100);
$allowances = $monthlySalary * ($payrollSettings['allowances_percentage'] / 100);

// Calculate earned amounts
$earnedBasic = ($basicSalary / $daysInMonth) * $presentDays;
$earnedHRA = ($hra / $daysInMonth) * $presentDays;
$earnedDA = ($da / $daysInMonth) * $presentDays;
$earnedAllowances = ($allowances / $daysInMonth) * $presentDays;

// Advanced overtime calculation
$overtimePay = $overtimeHours * $perHourSalary * $payrollSettings['overtime_multiplier'];

// Bonus calculations (can be enhanced)
$performanceBonus = 0;
$festivalBonus = 0;
$attendanceBonus = ($presentDays >= ($daysInMonth * 0.95)) ? ($monthlySalary * 0.02) : 0;

$totalEarnings = $earnedBasic + $earnedHRA + $earnedDA + $earnedAllowances + $overtimePay + $performanceBonus + $festivalBonus + $attendanceBonus;

// Advanced deductions
$pf = $earnedBasic * ($payrollSettings['pf_rate'] / 100);
$esi = ($totalEarnings <= 21000) ? ($totalEarnings * ($payrollSettings['esi_rate'] / 100)) : 0;
$professionalTax = ($totalEarnings > $payrollSettings['professional_tax_limit']) ? $payrollSettings['professional_tax_amount'] : 0;

// Advanced income tax calculation (basic slab system)
$incomeTax = 0;
$annualSalary = $monthlySalary * 12;
if ($annualSalary > 250000) {
    $taxableIncome = $annualSalary - 150000; // Standard deduction
    if ($taxableIncome > 250000 && $taxableIncome <= 500000) {
        $incomeTax = ($taxableIncome - 250000) * 0.05 / 12;
    } elseif ($taxableIncome > 500000 && $taxableIncome <= 1000000) {
        $incomeTax = (250000 * 0.05 + ($taxableIncome - 500000) * 0.20) / 12;
    } elseif ($taxableIncome > 1000000) {
        $incomeTax = (250000 * 0.05 + 500000 * 0.20 + ($taxableIncome - 1000000) * 0.30) / 12;
    }
}

$absentDeduction = $perDaySalary * $absentDays;
$lateDeduction = $lateDays * ($perDaySalary * 0.1); // 10% deduction for late days

// Loan deductions (can be enhanced with database)
$loanDeduction = 0;
$advanceDeduction = 0;

$totalDeductions = $pf + $esi + $professionalTax + $incomeTax + $absentDeduction + $lateDeduction + $loanDeduction + $advanceDeduction;
$netSalary = $totalEarnings - $totalDeductions;

$page_title = "Advanced Payslip - " . $employeeName . " - " . date('M Y', strtotime($month . '-01'));

// Template selection
$templateClass = '';
$templateStyle = '';

switch ($template) {
    case 'classic':
        $templateClass = 'classic-template';
        $templateStyle = '
            .classic-template { font-family: "Times New Roman", serif; }
            .classic-template .payslip-header { background: #f8f9fa; border-bottom: 3px solid #333; }
            .classic-template .section-title { color: #333; border-bottom: 2px solid #333; }
        ';
        break;
    case 'minimal':
        $templateClass = 'minimal-template';
        $templateStyle = '
            .minimal-template { font-family: "Arial", sans-serif; }
            .minimal-template .payslip-header { background: white; border-bottom: 1px solid #ddd; }
            .minimal-template .section-title { color: #666; font-weight: 300; }
        ';
        break;
    case 'colorful':
        $templateClass = 'colorful-template';
        $templateStyle = '
            .colorful-template { font-family: "Segoe UI", sans-serif; }
            .colorful-template .payslip-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
            .colorful-template .section-title { color: #667eea; }
        ';
        break;
    default: // modern
        $templateClass = 'modern-template';
        $templateStyle = '
            .modern-template { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; }
            .modern-template .payslip-header { background: #f8f9fa; border-bottom: 3px solid #007bff; }
            .modern-template .section-title { color: #007bff; }
        ';
}

// Handle different export formats
if ($export_format === 'pdf') {
    $print_mode = true;
} elseif ($export_format === 'excel') {
    // Excel export logic would go here
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="payslip_' . $employee_id . '_' . $month . '.xls"');
} elseif ($export_format === 'csv') {
    // CSV export logic would go here
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="payslip_' . $employee_id . '_' . $month . '.csv"');
}

// For print mode or PDF export, use standalone HTML
if ($print_mode || $export_format === 'pdf') {
?>
<!DOCTYPE html>
<html lang="<?= $language ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    
    <!-- Bootstrap 5 CSS for print mode -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
<?php
} else {
    // Use global layout for web view
    $page_title = "Payslip - " . $employeeName . " (" . date('M Y', strtotime($month)) . ")";
    include '../../layouts/header.php';
    include '../../layouts/sidebar.php';
    echo '<div class="main-content"><div class="container-fluid">';
    echo '<style>';
}
?>
        <?= $templateStyle ?>
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }
        
        .payslip-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .payslip-header {
            padding: 30px;
            position: relative;
        }
        
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 100px;
            color: rgba(0,0,0,0.05);
            font-weight: bold;
            z-index: 1;
            pointer-events: none;
        }
        
        .company-logo {
            max-width: 180px;
            height: auto;
            max-height: 80px;
            object-fit: contain;
        }
        
        .payslip-title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 2px solid #007bff;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .info-item {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 4px solid #007bff;
        }
        
        .info-label {
            font-size: 10px;
            color: #666;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .info-value {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-top: 2px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .stat-card {
            text-align: center;
            padding: 20px;
            border-radius: 8px;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card.primary { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .stat-card.success { background: linear-gradient(135deg, #56ab2f, #a8e6cf); color: white; }
        .stat-card.danger { background: linear-gradient(135deg, #ff6b6b, #ffa8a8); color: white; }
        .stat-card.warning { background: linear-gradient(135deg, #ffd93d, #ff6b6b); color: white; }
        .stat-card.info { background: linear-gradient(135deg, #74b9ff, #0984e3); color: white; }
        
        .stat-icon {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 12px;
            opacity: 0.9;
        }
        
        .salary-breakdown {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .table-advanced {
            margin: 0;
            font-size: 11px;
        }
        
        .table-advanced th {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            font-weight: 600;
            padding: 15px 12px;
            border: none;
            text-align: center;
            font-size: 12px;
        }
        
        .table-advanced td {
            padding: 12px;
            border: 1px solid #e9ecef;
            vertical-align: middle;
        }
        
        .table-advanced .item-name {
            text-align: left;
            font-weight: 500;
        }
        
        .table-advanced .amount {
            text-align: right;
            font-family: 'Courier New', monospace;
            font-weight: 600;
        }
        
        .total-row {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            font-weight: bold;
        }
        
        .net-salary-section {
            background: linear-gradient(135deg, #56ab2f, #a8e6cf);
            color: white;
            padding: 30px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
        }
        
        .net-amount {
            font-size: 36px;
            font-weight: bold;
            margin: 15px 0;
        }
        
        .amount-words {
            font-style: italic;
            opacity: 0.9;
            margin-top: 10px;
            font-size: 14px;
        }
        
        .footer-section {
            padding: 30px;
            background: #f8f9fa;
        }
        
        .signature-area {
            border-top: 2px solid #333;
            padding-top: 10px;
            margin-top: 60px;
            text-align: center;
            font-weight: bold;
        }
        
        .qr-code {
            width: 100px;
            height: 100px;
            background: #f0f0f0;
            border: 2px solid #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: #666;
        }
        
        /* Print styles */
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; padding: 0 !important; }
            .payslip-container { box-shadow: none !important; max-width: none !important; }
            .stat-card { break-inside: avoid; }
            .salary-breakdown { break-inside: avoid; }
            @page { margin: 15mm; size: A4; }
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .info-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .payslip-header { padding: 20px; }
            .net-amount { font-size: 28px; }
        }
        
        /* Animation for screen viewing */
        @media screen {
            .payslip-container { animation: fadeInUp 0.6s ease-out; }
            @keyframes fadeInUp {
                from { opacity: 0; transform: translateY(30px); }
                to { opacity: 1; transform: translateY(0); }
            }
        }
    </style>
</head>

<body class="<?= $templateClass ?>">
    
    <?php if (!($print_mode || $export_format === 'pdf')): ?>
        <!-- Web View Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">💰 Payslip Details</h1>
                <p class="text-muted"><?= htmlspecialchars($employeeName) ?> - <?= date('F Y', strtotime($month . '-01')) ?></p>
            </div>
            <div class="btn-group">
                <a href="generate_payslip.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left"></i> Back to Selection
                </a>
                <button onclick="window.print()" class="btn btn-success">
                    <i class="bi bi-printer"></i> Print
                </button>
                <div class="dropdown">
                    <button class="btn btn-info dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-download"></i> Export
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="?employee_id=<?= $employee_id ?>&month=<?= $month ?>&format=pdf&template=<?= $template ?>">PDF</a></li>
                        <li><a class="dropdown-item" href="?employee_id=<?= $employee_id ?>&month=<?= $month ?>&format=excel&template=<?= $template ?>">Excel</a></li>
                        <li><a class="dropdown-item" href="?employee_id=<?= $employee_id ?>&month=<?= $month ?>&format=csv&template=<?= $template ?>">CSV</a></li>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Advanced Payslip Content -->
    <div class="payslip-container">
        <!-- Watermark -->
        <div class="watermark">PAYSLIP</div>
        
        <!-- Header Section -->
        <div class="payslip-header">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <?php if (!empty($companySettings['company_logo'])): ?>
                    <img src="<?= $companySettings['company_logo'] ?>" alt="Company Logo" class="company-logo" onerror="this.style.display='none'">
                    <?php endif; ?>
                </div>
                <div class="col-md-6 text-center">
                    <div class="payslip-title"><?= $companySettings['company_name'] ?></div>
                    <div class="h5 text-primary">SALARY SLIP</div>
                    <div class="text-muted"><?= date('F Y', strtotime($month . '-01')) ?></div>
                </div>
                <div class="col-md-3 text-end">
                    <div class="qr-code">
                        QR CODE<br>
                        <small>Scan to Verify</small>
                    </div>
                </div>
            </div>
            
            <div class="mt-3 small text-muted text-center">
                <?= $companySettings['company_address'] ?> | 
                Phone: <?= $companySettings['company_phone'] ?> | 
                Email: <?= $companySettings['company_email'] ?>
            </div>
        </div>
        
        <!-- Employee Information -->
        <div class="p-4">
            <h5 class="section-title">
                <i class="bi bi-person-circle me-2"></i>Employee Information
            </h5>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Employee ID</div>
                    <div class="info-value"><?= htmlspecialchars($employeeCode) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Employee Name</div>
                    <div class="info-value"><?= htmlspecialchars($employeeName) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Designation</div>
                    <div class="info-value"><?= htmlspecialchars($position) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Department</div>
                    <div class="info-value"><?= htmlspecialchars($department) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Date of Joining</div>
                    <div class="info-value"><?= date('d M Y', strtotime($joiningDate)) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Pay Period</div>
                    <div class="info-value"><?= date('01 M Y', strtotime($month . '-01')) ?> to <?= date('t M Y', strtotime($month . '-01')) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Bank Account</div>
                    <div class="info-value"><?= htmlspecialchars($bankAccount) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">PAN Number</div>
                    <div class="info-value"><?= htmlspecialchars($panNumber) ?></div>
                </div>
            </div>
            
            <!-- Attendance Statistics -->
            <h5 class="section-title">
                <i class="bi bi-calendar-check me-2"></i>Attendance Summary
            </h5>
            
            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-icon"><i class="bi bi-calendar-check-fill"></i></div>
                    <div class="stat-value"><?= $presentDays ?></div>
                    <div class="stat-label">Days Present</div>
                </div>
                <div class="stat-card danger">
                    <div class="stat-icon"><i class="bi bi-calendar-x-fill"></i></div>
                    <div class="stat-value"><?= $absentDays ?></div>
                    <div class="stat-label">Days Absent</div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-icon"><i class="bi bi-clock-fill"></i></div>
                    <div class="stat-value"><?= $lateDays ?></div>
                    <div class="stat-label">Late Days</div>
                </div>
                <div class="stat-card info">
                    <div class="stat-icon"><i class="bi bi-stopwatch-fill"></i></div>
                    <div class="stat-value"><?= number_format($overtimeHours, 1) ?>h</div>
                    <div class="stat-label">Overtime Hours</div>
                </div>
                <div class="stat-card success">
                    <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                    <div class="stat-value"><?= number_format($totalWorkingHours, 1) ?>h</div>
                    <div class="stat-label">Total Hours</div>
                </div>
            </div>
            
            <!-- Advanced Salary Breakdown -->
            <h5 class="section-title">
                <i class="bi bi-currency-rupee me-2"></i>Detailed Salary Breakdown
            </h5>
            
            <div class="salary-breakdown">
                <table class="table table-advanced">
                    <thead>
                        <tr>
                            <th style="width: 35%;">EARNINGS</th>
                            <th style="width: 15%;">AMOUNT (₹)</th>
                            <th style="width: 35%;">DEDUCTIONS</th>
                            <th style="width: 15%;">AMOUNT (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="item-name">Basic Salary</td>
                            <td class="amount"><?= number_format($earnedBasic, 2) ?></td>
                            <td class="item-name">Provident Fund (<?= $payrollSettings['pf_rate'] ?>%)</td>
                            <td class="amount"><?= number_format($pf, 2) ?></td>
                        </tr>
                        <tr>
                            <td class="item-name">House Rent Allowance</td>
                            <td class="amount"><?= number_format($earnedHRA, 2) ?></td>
                            <td class="item-name">ESI (<?= $payrollSettings['esi_rate'] ?>%)</td>
                            <td class="amount"><?= number_format($esi, 2) ?></td>
                        </tr>
                        <tr>
                            <td class="item-name">Dearness Allowance</td>
                            <td class="amount"><?= number_format($earnedDA, 2) ?></td>
                            <td class="item-name">Professional Tax</td>
                            <td class="amount"><?= number_format($professionalTax, 2) ?></td>
                        </tr>
                        <tr>
                            <td class="item-name">Other Allowances</td>
                            <td class="amount"><?= number_format($earnedAllowances, 2) ?></td>
                            <td class="item-name">Income Tax (TDS)</td>
                            <td class="amount"><?= number_format($incomeTax, 2) ?></td>
                        </tr>
                        <tr>
                            <td class="item-name">Overtime Pay (<?= number_format($overtimeHours, 1) ?>h × ₹<?= number_format($perHourSalary * $payrollSettings['overtime_multiplier'], 2) ?>)</td>
                            <td class="amount"><?= number_format($overtimePay, 2) ?></td>
                            <td class="item-name">Absent Days Deduction</td>
                            <td class="amount"><?= number_format($absentDeduction, 2) ?></td>
                        </tr>
                        <?php if ($attendanceBonus > 0): ?>
                        <tr>
                            <td class="item-name">Attendance Bonus</td>
                            <td class="amount"><?= number_format($attendanceBonus, 2) ?></td>
                            <td class="item-name">Late Day Penalty</td>
                            <td class="amount"><?= number_format($lateDeduction, 2) ?></td>
                        </tr>
                        <?php else: ?>
                        <tr>
                            <td class="item-name">-</td>
                            <td class="amount">-</td>
                            <td class="item-name">Late Day Penalty</td>
                            <td class="amount"><?= number_format($lateDeduction, 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        <tr class="total-row">
                            <td class="item-name"><strong>GROSS EARNINGS</strong></td>
                            <td class="amount"><strong><?= number_format($totalEarnings, 2) ?></strong></td>
                            <td class="item-name"><strong>TOTAL DEDUCTIONS</strong></td>
                            <td class="amount"><strong><?= number_format($totalDeductions, 2) ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Net Salary Section -->
            <div class="net-salary-section">
                <h5 class="mb-0">NET SALARY FOR <?= strtoupper(date('F Y', strtotime($month . '-01'))) ?></h5>
                <div class="net-amount">₹ <?= number_format($netSalary, 2) ?></div>
                <div class="amount-words">
                    <strong>In Words:</strong> <?= ucwords(convertNumberToWords($netSalary)) ?> Rupees Only
                </div>
                <div class="mt-3">
                    <small>Gross Earnings: ₹<?= number_format($totalEarnings, 2) ?> | Total Deductions: ₹<?= number_format($totalDeductions, 2) ?></small>
                </div>
            </div>
        </div>
        
        <!-- Footer Section -->
        <div class="footer-section">
            <div class="row">
                <div class="col-md-6">
                    <h6 class="section-title">Important Notes</h6>
                    <ul class="list-unstyled small">
                        <li>• This is a system-generated payslip and does not require a physical signature.</li>
                        <li>• PF and ESI deductions are as per government regulations.</li>
                        <li>• Overtime is calculated at <?= $payrollSettings['overtime_multiplier'] ?>x the hourly rate.</li>
                        <li>• Professional Tax is applicable for gross salary above ₹<?= number_format($payrollSettings['professional_tax_limit']) ?>.</li>
                        <li>• Income tax is calculated as per current tax slabs.</li>
                        <li>• For any queries, contact HR department.</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="signature-area">
                                Employee Signature
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="signature-area">
                                Authorized Signatory
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <small class="text-muted">
                            Generated on <?= date('d M Y, h:i A') ?> | 
                            Payslip ID: PAY<?= $employee_id ?><?= date('Ym', strtotime($month . '-01')) ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$print_mode): ?>
            </div>
        </div>
    </div>
    
    <!-- Floating Action Button -->
    <div class="position-fixed bottom-0 end-0 p-3 no-print" style="z-index: 1000;">
        <div class="btn-group-vertical">
            <button onclick="window.print()" class="btn btn-primary btn-sm mb-2" title="Print">
                <i class="bi bi-printer"></i>
            </button>
            <button onclick="downloadPDF()" class="btn btn-success btn-sm mb-2" title="Download PDF">
                <i class="bi bi-file-pdf"></i>
            </button>
            <button onclick="sendEmail()" class="btn btn-warning btn-sm" title="Send Email">
                <i class="bi bi-envelope"></i>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Scripts -->
    <?php if ($print_mode): ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php endif; ?>
    
    <script>
        // Advanced PDF download with loading state
        function downloadPDF() {
            const employee_id = <?= $employee_id ?>;
            const month = '<?= $month ?>';
            const template = '<?= $template ?>';
            
            // Show loading indicator
            const downloadBtn = document.querySelector('[onclick="downloadPDF()"]');
            if (downloadBtn) {
                const originalText = downloadBtn.innerHTML;
                downloadBtn.innerHTML = '<i class="bi bi-hourglass-split bi-spin"></i> Generating...';
                downloadBtn.disabled = true;
                
                setTimeout(() => {
                    downloadBtn.innerHTML = originalText;
                    downloadBtn.disabled = false;
                }, 3000);
            }
            
            // Open print-friendly version
            const printUrl = `generate_payslip.php?employee_id=${employee_id}&month=${month}&template=${template}&print=1&format=pdf`;
            window.open(printUrl, '_blank');
        }
        
        // Excel export
        function downloadExcel() {
            const employee_id = <?= $employee_id ?>;
            const month = '<?= $month ?>';
            window.open(`generate_payslip.php?employee_id=${employee_id}&month=${month}&format=excel`, '_blank');
        }
        
        // Enhanced email functionality
        function sendEmail() {
            const employee_id = <?= $employee_id ?>;
            const month = '<?= $month ?>';
            
            if (confirm('Send this payslip to employee\'s registered email address?')) {
                const emailBtn = document.querySelector('[onclick="sendEmail()"]');
                if (emailBtn) {
                    const originalText = emailBtn.innerHTML;
                    emailBtn.innerHTML = '<i class="bi bi-hourglass-split bi-spin"></i> Sending...';
                    emailBtn.disabled = true;
                    
                    fetch('send_payslip_email.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: `employee_id=${employee_id}&month=${month}&template=<?= $template ?>`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(`✓ Email sent successfully to ${data.employee_name} (${data.employee_email})`);
                        } else {
                            alert('⚠️ ' + data.error);
                        }
                    })
                    .catch(error => {
                        alert('📧 Email functionality ready for implementation.');
                    })
                    .finally(() => {
                        emailBtn.innerHTML = originalText;
                        emailBtn.disabled = false;
                    });
                }
            }
        }
        
        // Share functionality
        function sharePayslip() {
            if (navigator.share) {
                navigator.share({
                    title: 'Payslip - <?= $employeeName ?>',
                    text: 'Payslip for <?= date('F Y', strtotime($month . '-01')) ?>',
                    url: window.location.href
                });
            } else {
                // Fallback to copy URL
                navigator.clipboard.writeText(window.location.href).then(() => {
                    alert('📋 Payslip URL copied to clipboard!');
                });
            }
        }
        
        // Enhanced print functionality
        function enhancedPrint() {
            // Hide non-printable elements
            const noPrintElements = document.querySelectorAll('.no-print');
            noPrintElements.forEach(el => el.style.display = 'none');
            
            // Wait for images to load
            const images = document.querySelectorAll('img');
            let loadedImages = 0;
            
            function checkAllImagesLoaded() {
                loadedImages++;
                if (loadedImages === images.length || images.length === 0) {
                    setTimeout(() => {
                        window.print();
                        setTimeout(() => {
                            noPrintElements.forEach(el => el.style.display = '');
                        }, 1000);
                    }, 500);
                }
            }
            
            if (images.length === 0) {
                checkAllImagesLoaded();
                return;
            }
            
            images.forEach(img => {
                if (img.complete) {
                    checkAllImagesLoaded();
                } else {
                    img.onload = img.onerror = checkAllImagesLoaded;
                }
            });
        }
        
        // Auto-print if in print mode
        <?php if ($print_mode): ?>
        window.onload = function() {
            enhancedPrint();
        };
        <?php endif; ?>
        
        // Keyboard shortcuts
        <?php if (!$print_mode): ?>
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                enhancedPrint();
            }
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                downloadPDF();
            }
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                sendEmail();
            }
        });
        <?php endif; ?>
        
        // Initialize tooltips if Bootstrap is available
        if (typeof bootstrap !== 'undefined') {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
    </script>

    <?php if ($print_mode || $export_format === 'pdf') { ?>
</body>
</html>
    <?php } else { ?>
        </div>
    </div>
    
    <!-- Include Payroll Modals -->
    <?php include 'payroll_modals.html'; ?>
    
    <!-- Include Payroll Modal JavaScript -->
    <script src="../../assets/js/payroll_modals.js"></script>
    
    <?php include '../../layouts/footer.php'; ?>
    <?php } ?>

<?php
// Enhanced number to words conversion function
function convertNumberToWords($number) {
    $number = (int) $number;
    
    if ($number == 0) return 'zero';
    
    $ones = [
        '', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine',
        'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen',
        'seventeen', 'eighteen', 'nineteen'
    ];
    
    $tens = [
        '', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'
    ];
    
    $result = '';
    
    // Handle crores
    if ($number >= 10000000) {
        $crores = (int)($number / 10000000);
        $result .= convertNumberToWords($crores) . ' crore ';
        $number %= 10000000;
    }
    
    // Handle lakhs
    if ($number >= 100000) {
        $lakhs = (int)($number / 100000);
        $result .= convertNumberToWords($lakhs) . ' lakh ';
        $number %= 100000;
    }
    
    // Handle thousands
    if ($number >= 1000) {
        $thousands = (int)($number / 1000);
        $result .= convertNumberToWords($thousands) . ' thousand ';
        $number %= 1000;
    }
    
    // Handle hundreds
    if ($number >= 100) {
        $hundreds = (int)($number / 100);
        $result .= $ones[$hundreds] . ' hundred ';
        $number %= 100;
    }
    
    // Handle tens and ones
    if ($number >= 20) {
        $tensDigit = (int)($number / 10);
        $onesDigit = $number % 10;
        $result .= $tens[$tensDigit];
        if ($onesDigit > 0) {
            $result .= ' ' . $ones[$onesDigit];
        }
    } elseif ($number > 0) {
        $result .= $ones[$number];
    }
    
    return trim($result);
}

// Log successful generation
error_log("Payslip successfully generated for Employee: $employee_id, Month: $month");

// Clean up output buffer
ob_end_flush();
?>
