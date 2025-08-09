<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$page_title = "Payroll Reports - HRMS";
include '../db.php';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'generate_custom_report':
            $report_type = mysqli_real_escape_string($conn, $_POST['report_type']);
            $from_date = mysqli_real_escape_string($conn, $_POST['from_date']);
            $to_date = mysqli_real_escape_string($conn, $_POST['to_date']);
            $department_id = intval($_POST['department_id']);
            
            if (empty($from_date) || empty($to_date)) {
                echo json_encode(['success' => false, 'message' => 'From and To dates are required']);
                exit;
            }
            
            $department_filter = $department_id > 0 ? "AND he.department_id = $department_id" : "";
            
            // Generate report based on type
            switch ($report_type) {
                case 'salary_summary':
                    $query = "SELECT 
                        he.employee_id, 
                        CONCAT(he.first_name, ' ', he.last_name) as name,
                        d.department_name,
                        pr.basic_salary, 
                        pr.allowances,
                        pr.total_deductions,
                        pr.net_pay,
                        pr.pay_period_start,
                        pr.pay_period_end
                        FROM payroll_records pr 
                        LEFT JOIN hr_employees he ON pr.employee_id = he.employee_id
                        LEFT JOIN hr_departments d ON he.department_id = d.id
                        WHERE pr.pay_period_start >= '$from_date' 
                        AND pr.pay_period_end <= '$to_date'
                        $department_filter
                        ORDER BY pr.pay_period_start DESC";
                    break;
                    
                case 'department_wise':
                    $query = "SELECT 
                        d.department_name,
                        COUNT(DISTINCT he.id) as employee_count,
                        SUM(pr.basic_salary) as total_basic,
                        SUM(pr.allowances) as total_allowances,
                        SUM(pr.total_deductions) as total_deductions,
                        SUM(pr.net_pay) as total_net_pay
                        FROM payroll_records pr 
                        LEFT JOIN hr_employees he ON pr.employee_id = he.employee_id
                        LEFT JOIN hr_departments d ON he.department_id = d.id
                        WHERE pr.pay_period_start >= '$from_date' 
                        AND pr.pay_period_end <= '$to_date'
                        $department_filter
                        GROUP BY d.id, d.department_name
                        ORDER BY total_net_pay DESC";
                    break;
                    
                case 'tax_deductions':
                    $query = "SELECT 
                        he.employee_id, 
                        CONCAT(he.first_name, ' ', he.last_name) as name,
                        d.department_name,
                        pr.basic_salary,
                        pr.tax_deduction,
                        pr.insurance_deduction,
                        pr.other_deductions,
                        pr.total_deductions,
                        pr.pay_period_start
                        FROM payroll_records pr 
                        LEFT JOIN hr_employees he ON pr.employee_id = he.employee_id
                        LEFT JOIN hr_departments d ON he.department_id = d.id
                        WHERE pr.pay_period_start >= '$from_date' 
                        AND pr.pay_period_end <= '$to_date'
                        AND pr.tax_deduction > 0
                        $department_filter
                        ORDER BY pr.tax_deduction DESC";
                    break;
                    
                case 'overtime_analysis':
                    $query = "SELECT 
                        he.employee_id, 
                        CONCAT(he.first_name, ' ', he.last_name) as name,
                        d.department_name,
                        pr.hours_worked,
                        pr.overtime_hours,
                        pr.overtime_pay,
                        pr.pay_period_start
                        FROM payroll_records pr 
                        LEFT JOIN hr_employees he ON pr.employee_id = he.employee_id
                        LEFT JOIN hr_departments d ON he.department_id = d.id
                        WHERE pr.pay_period_start >= '$from_date' 
                        AND pr.pay_period_end <= '$to_date'
                        AND pr.overtime_hours > 0
                        $department_filter
                        ORDER BY pr.overtime_hours DESC";
                    break;
                    
                default:
                    echo json_encode(['success' => false, 'message' => 'Invalid report type']);
                    exit;
            }
            
            $result = mysqli_query($conn, $query);
            if ($result) {
                $data = [];
                while ($row = mysqli_fetch_assoc($result)) {
                    $data[] = $row;
                }
                echo json_encode(['success' => true, 'data' => $data, 'report_type' => $report_type]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database query failed: ' . mysqli_error($conn)]);
            }
            exit;
            
        case 'export_report':
            $report_type = mysqli_real_escape_string($conn, $_POST['report_type']);
            $data = json_decode($_POST['data'], true);
            
            if (empty($data)) {
                echo json_encode(['success' => false, 'message' => 'No data to export']);
                exit;
            }
            
            // Create CSV content
            $filename = $report_type . '_report_' . date('Y-m-d_H-i-s') . '.csv';
            $csv_content = '';
            
            // Add headers
            if (!empty($data)) {
                $headers = array_keys($data[0]);
                $csv_content .= implode(',', $headers) . "\n";
                
                // Add data rows
                foreach ($data as $row) {
                    $csv_content .= implode(',', array_map(function($value) {
                        return '"' . str_replace('"', '""', $value) . '"';
                    }, $row)) . "\n";
                }
            }
            
            // Save to temp file and return download link
            $temp_file = '../temp/reports/' . $filename;
            if (!file_exists('../temp/reports/')) {
                mkdir('../temp/reports/', 0755, true);
            }
            
            if (file_put_contents($temp_file, $csv_content)) {
                echo json_encode(['success' => true, 'download_url' => 'temp/reports/' . $filename, 'filename' => $filename]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create export file']);
            }
            exit;
    }
}

// Handle CSV download
if (isset($_GET['download']) && isset($_GET['file'])) {
    $file = basename($_GET['file']);
    $filepath = '../temp/reports/' . $file;
    
    if (file_exists($filepath)) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        unlink($filepath); // Delete temp file after download
        exit;
    }
}

// Get payroll statistics
$current_month = date('Y-m');
$current_year = date('Y');

// Monthly reports data
$monthly_reports_query = "SELECT 
    DATE_FORMAT(pr.pay_period_start, '%Y-%m') as month,
    DATE_FORMAT(pr.pay_period_start, '%M %Y') as month_name,
    COUNT(DISTINCT pr.employee_id) as employees,
    SUM(pr.basic_salary) as total_salary,
    SUM(pr.total_deductions) as deductions,
    SUM(pr.net_pay) as net_payroll,
    'Processed' as status
    FROM payroll_records pr 
    WHERE pr.status = 'processed'
    GROUP BY DATE_FORMAT(pr.pay_period_start, '%Y-%m')
    ORDER BY pr.pay_period_start DESC
    LIMIT 12";

$monthly_reports_result = mysqli_query($conn, $monthly_reports_query);
$monthly_reports = [];
while ($row = mysqli_fetch_assoc($monthly_reports_result)) {
    $monthly_reports[] = $row;
}

// Statistics
$total_employees_query = "SELECT COUNT(*) as total FROM hr_employees WHERE status = 'active'";
$total_employees_result = mysqli_query($conn, $total_employees_query);
$total_employees = $total_employees_result ? mysqli_fetch_assoc($total_employees_result)['total'] : 0;

$total_payroll_query = "SELECT SUM(net_pay) as total FROM payroll_records WHERE YEAR(pay_period_start) = $current_year";
$total_payroll_result = mysqli_query($conn, $total_payroll_query);
$total_payroll = $total_payroll_result ? mysqli_fetch_assoc($total_payroll_result)['total'] : 0;

$monthly_reports_count = count($monthly_reports);

// Growth calculation
$prev_year = $current_year - 1;
$current_year_payroll_query = "SELECT SUM(net_pay) as total FROM payroll_records WHERE YEAR(pay_period_start) = $current_year";
$prev_year_payroll_query = "SELECT SUM(net_pay) as total FROM payroll_records WHERE YEAR(pay_period_start) = $prev_year";

$current_year_result = mysqli_query($conn, $current_year_payroll_query);
$prev_year_result = mysqli_query($conn, $prev_year_payroll_query);

$current_year_total = $current_year_result ? mysqli_fetch_assoc($current_year_result)['total'] : 0;
$prev_year_total = $prev_year_result ? mysqli_fetch_assoc($prev_year_result)['total'] : 1;

$growth_rate = $prev_year_total > 0 ? (($current_year_total - $prev_year_total) / $prev_year_total) * 100 : 0;

// Get departments for dropdown
$departments_query = "SELECT id, department_name FROM hr_departments WHERE status = 'active' ORDER BY department_name";
$departments_result = mysqli_query($conn, $departments_query);
$departments = [];
while ($row = mysqli_fetch_assoc($departments_result)) {
    $departments[] = $row;
}

// Analytics data
$avg_salary_query = "SELECT AVG(net_pay) as avg_salary FROM payroll_records WHERE YEAR(pay_period_start) = $current_year";
$avg_salary_result = mysqli_query($conn, $avg_salary_query);
$avg_salary = $avg_salary_result ? mysqli_fetch_assoc($avg_salary_result)['avg_salary'] : 0;

$overtime_hours_query = "SELECT SUM(overtime_hours) as total_overtime FROM payroll_records WHERE YEAR(pay_period_start) = $current_year";
$overtime_hours_result = mysqli_query($conn, $overtime_hours_query);
$total_overtime_hours = $overtime_hours_result ? mysqli_fetch_assoc($overtime_hours_result)['total_overtime'] : 0;

$total_deductions_query = "SELECT SUM(total_deductions) as total_deductions FROM payroll_records WHERE YEAR(pay_period_start) = $current_year";
$total_deductions_result = mysqli_query($conn, $total_deductions_query);
$total_deductions = $total_deductions_result ? mysqli_fetch_assoc($total_deductions_result)['total_deductions'] : 0;

include '../layouts/header.php';
include '../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">
                    <i class="bi bi-file-earmark-spreadsheet text-primary"></i> Payroll Reports
                </h1>
                <p class="text-muted">Generate and view comprehensive payroll reports and analytics</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" onclick="exportAllReports()">
                    <i class="bi bi-download me-2"></i>Export All
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newReportModal">
                    <i class="bi bi-plus-lg me-2"></i>New Report
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-calendar-month fs-1" style="color: #1976d2;"></i>
                        </div>
                        <h3 class="mb-1 fw-bold" style="color: #1976d2;"><?= $monthly_reports_count ?></h3>
                        <p class="text-muted mb-0">Monthly Reports</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-people fs-1" style="color: #388e3c;"></i>
                        </div>
                        <h3 class="mb-1 fw-bold" style="color: #388e3c;"><?= $total_employees ?></h3>
                        <p class="text-muted mb-0">Total Employees</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%);">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-currency-rupee fs-1" style="color: #f57c00;"></i>
                        </div>
                        <h3 class="mb-1 fw-bold" style="color: #f57c00;">₹<?= number_format($total_payroll/100000, 1) ?>L</h3>
                        <p class="text-muted mb-0">Total Payroll</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%);">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-graph-up fs-1" style="color: #7b1fa2;"></i>
                        </div>
                        <h3 class="mb-1 fw-bold" style="color: #7b1fa2;"><?= number_format($growth_rate, 1) ?>%</h3>
                        <p class="text-muted mb-0">Growth Rate</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#monthly" role="tab">
                            <i class="bi bi-calendar-month me-2"></i>Monthly Reports
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#custom" role="tab">
                            <i class="bi bi-funnel me-2"></i>Custom Reports
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#analytics" role="tab">
                            <i class="bi bi-graph-up me-2"></i>Analytics
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <!-- Monthly Reports Tab -->
                    <div class="tab-pane fade show active" id="monthly" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Month</th>
                                        <th>Employees</th>
                                        <th>Total Salary</th>
                                        <th>Deductions</th>
                                        <th>Net Payroll</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($monthly_reports as $report): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($report['month_name']) ?></div>
                                            <small class="text-muted"><?= $report['month'] ?></small>
                                        </td>
                                        <td><?= $report['employees'] ?></td>
                                        <td>
                                            <span class="fw-semibold text-primary">₹<?= number_format($report['total_salary']) ?></span>
                                        </td>
                                        <td>
                                            <span class="text-danger">₹<?= number_format($report['deductions']) ?></span>
                                        </td>
                                        <td>
                                            <span class="fw-semibold text-success">₹<?= number_format($report['net_payroll']) ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?= $report['status'] ?></span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="viewMonthlyReport('<?= $report['month'] ?>')" title="View">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-success" onclick="downloadMonthlyReport('<?= $report['month'] ?>')" title="Download">
                                                    <i class="bi bi-download"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-info" onclick="emailReport('<?= $report['month'] ?>')" title="Email">
                                                    <i class="bi bi-envelope"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($monthly_reports)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                            No monthly reports found
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Custom Reports Tab -->
                    <div class="tab-pane fade" id="custom" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-4">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0">Custom Report Generator</h5>
                                    </div>
                                    <div class="card-body">
                                        <form id="customReportForm">
                                            <div class="mb-3">
                                                <label class="form-label">Report Type *</label>
                                                <select class="form-select" name="report_type" required>
                                                    <option value="">Select Report Type</option>
                                                    <option value="salary_summary">Salary Summary</option>
                                                    <option value="department_wise">Department Wise</option>
                                                    <option value="tax_deductions">Tax Deductions</option>
                                                    <option value="overtime_analysis">Overtime Analysis</option>
                                                </select>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">From Date *</label>
                                                        <input type="date" class="form-control" name="from_date" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">To Date *</label>
                                                        <input type="date" class="form-control" name="to_date" required>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Department</label>
                                                <select class="form-select" name="department_id">
                                                    <option value="0">All Departments</option>
                                                    <?php foreach ($departments as $dept): ?>
                                                    <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['department_name']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="bi bi-file-earmark-text me-2"></i>Generate Report
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-8">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">Report Results</h5>
                                        <button class="btn btn-outline-success btn-sm" id="exportCustomReport" style="display: none;" onclick="exportCustomReport()">
                                            <i class="bi bi-download me-1"></i>Export CSV
                                        </button>
                                    </div>
                                    <div class="card-body" id="customReportResults">
                                        <div class="text-center text-muted py-5">
                                            <i class="bi bi-file-earmark-text text-muted" style="font-size: 3rem;"></i>
                                            <p class="mt-3">Generate a custom report to see results here</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Analytics Tab -->
                    <div class="tab-pane fade" id="analytics" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-8">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0">Payroll Trends</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="payrollChart" height="100"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0">Key Metrics</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-4">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="text-muted">Average Salary</span>
                                                <strong>₹<?= number_format($avg_salary) ?></strong>
                                            </div>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar bg-primary" style="width: <?= min(($avg_salary/100000)*100, 100) ?>%"></div>
                                            </div>
                                        </div>
                                        <div class="mb-4">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="text-muted">Overtime Hours</span>
                                                <strong><?= number_format($total_overtime_hours) ?> hrs</strong>
                                            </div>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar bg-warning" style="width: <?= min(($total_overtime_hours/1000)*100, 100) ?>%"></div>
                                            </div>
                                        </div>
                                        <div class="mb-4">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="text-muted">Total Deductions</span>
                                                <strong>₹<?= number_format($total_deductions/100000, 1) ?>L</strong>
                                            </div>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar bg-danger" style="width: <?= min(($total_deductions/$total_payroll)*100, 100) ?>%"></div>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="text-muted">Net Growth</span>
                                                <strong><?= $growth_rate >= 0 ? '+' : '' ?><?= number_format($growth_rate, 1) ?>%</strong>
                                            </div>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar <?= $growth_rate >= 0 ? 'bg-success' : 'bg-danger' ?>" style="width: <?= min(abs($growth_rate), 100) ?>%"></div>
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
</div>

<!-- New Report Modal -->
<div class="modal fade" id="newReportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Generate New Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body text-center">
                                <i class="bi bi-calendar-month text-primary fs-1 mb-3"></i>
                                <h6>Monthly Payroll Report</h6>
                                <p class="text-muted small mb-3">Generate monthly payroll summary</p>
                                <button class="btn btn-outline-primary btn-sm" onclick="generateMonthlyReport()">Generate</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body text-center">
                                <i class="bi bi-funnel text-success fs-1 mb-3"></i>
                                <h6>Custom Report</h6>
                                <p class="text-muted small mb-3">Create custom filtered reports</p>
                                <button class="btn btn-outline-success btn-sm" onclick="openCustomReportTab()">Create</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body text-center">
                                <i class="bi bi-graph-up text-warning fs-1 mb-3"></i>
                                <h6>Analytics Report</h6>
                                <p class="text-muted small mb-3">View payroll analytics and trends</p>
                                <button class="btn btn-outline-warning btn-sm" onclick="openAnalyticsTab()">View</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Report Details Modal -->
<div class="modal fade" id="reportDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reportModalTitle">Report Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="reportModalBody">
                <!-- Report content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printReport()">Print</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let currentCustomReportData = [];

$(document).ready(function() {
    // Initialize payroll chart
    initializePayrollChart();
    
    // Handle custom report form submission
    $('#customReportForm').on('submit', function(e) {
        e.preventDefault();
        generateCustomReport();
    });
});

function initializePayrollChart() {
    const ctx = document.getElementById('payrollChart').getContext('2d');
    
    // Get chart data from PHP
    const monthlyData = <?= json_encode(array_reverse($monthly_reports)) ?>;
    const labels = monthlyData.map(item => item.month_name);
    const netPayrollData = monthlyData.map(item => parseFloat(item.net_payroll));
    const deductionsData = monthlyData.map(item => parseFloat(item.deductions));
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Net Payroll',
                data: netPayrollData,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.1)',
                tension: 0.1
            }, {
                label: 'Total Deductions',
                data: deductionsData,
                borderColor: 'rgb(255, 99, 132)',
                backgroundColor: 'rgba(255, 99, 132, 0.1)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₹' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}

function generateCustomReport() {
    const formData = new FormData(document.getElementById('customReportForm'));
    formData.append('action', 'generate_custom_report');
    
    const submitBtn = document.querySelector('#customReportForm button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Generating...';
    
    $.post(window.location.href, formData)
        .done(function(response) {
            if (response.success) {
                displayCustomReportResults(response.data, response.report_type);
                currentCustomReportData = response.data;
                document.getElementById('exportCustomReport').style.display = 'block';
                showAlert('Report generated successfully!', 'success');
            } else {
                showAlert(response.message, 'danger');
            }
        })
        .fail(function(xhr, status, error) {
            showAlert('Network error: ' + error, 'danger');
        })
        .always(function() {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-file-earmark-text me-2"></i>Generate Report';
        });
}

function displayCustomReportResults(data, reportType) {
    const resultsDiv = document.getElementById('customReportResults');
    
    if (!data || data.length === 0) {
        resultsDiv.innerHTML = `
            <div class="text-center text-muted py-5">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                No data found for the selected criteria
            </div>
        `;
        return;
    }
    
    // Create table based on report type
    let tableHtml = '<div class="table-responsive"><table class="table table-striped table-hover"><thead class="table-light"><tr>';
    
    // Add headers based on data keys
    const headers = Object.keys(data[0]);
    headers.forEach(header => {
        tableHtml += `<th>${header.replace('_', ' ').toUpperCase()}</th>`;
    });
    tableHtml += '</tr></thead><tbody>';
    
    // Add data rows
    data.forEach(row => {
        tableHtml += '<tr>';
        headers.forEach(header => {
            let value = row[header];
            // Format currency values
            if (header.includes('salary') || header.includes('pay') || header.includes('deduction') || header.includes('allowance')) {
                if (typeof value === 'string') {
                    const numValue = parseFloat(value);
                    if (!isNaN(numValue)) {
                        value = '₹' + numValue.toLocaleString();
                    }
                } else if (typeof value === 'number') {
                    value = '₹' + value.toLocaleString();
                }
            }
            tableHtml += `<td>${value || '-'}</td>`;
        });
        tableHtml += '</tr>';
    });
    
    tableHtml += '</tbody></table></div>';
    
    resultsDiv.innerHTML = tableHtml;
}

function exportCustomReport() {
    if (currentCustomReportData.length === 0) {
        showAlert('No data to export', 'warning');
        return;
    }
    
    const reportType = document.querySelector('select[name="report_type"]').value;
    const formData = new FormData();
    formData.append('action', 'export_report');
    formData.append('report_type', reportType);
    formData.append('data', JSON.stringify(currentCustomReportData));
    
    $.post(window.location.href, formData)
        .done(function(response) {
            if (response.success) {
                // Trigger download
                window.open(`${window.location.href}?download=1&file=${response.filename}`, '_blank');
                showAlert('Report exported successfully!', 'success');
            } else {
                showAlert(response.message, 'danger');
            }
        })
        .fail(function() {
            showAlert('Export failed', 'danger');
        });
}

function viewMonthlyReport(month) {
    // Generate monthly report view
    const formData = new FormData();
    formData.append('action', 'generate_custom_report');
    formData.append('report_type', 'salary_summary');
    formData.append('from_date', month + '-01');
    formData.append('to_date', month + '-31');
    formData.append('department_id', '0');
    
    $.post(window.location.href, formData)
        .done(function(response) {
            if (response.success) {
                document.getElementById('reportModalTitle').textContent = `Monthly Report - ${month}`;
                
                let modalContent = '<div class="table-responsive"><table class="table table-striped"><thead><tr>';
                if (response.data.length > 0) {
                    Object.keys(response.data[0]).forEach(header => {
                        modalContent += `<th>${header.replace('_', ' ').toUpperCase()}</th>`;
                    });
                    modalContent += '</tr></thead><tbody>';
                    
                    response.data.forEach(row => {
                        modalContent += '<tr>';
                        Object.values(row).forEach(value => {
                            modalContent += `<td>${value || '-'}</td>`;
                        });
                        modalContent += '</tr>';
                    });
                } else {
                    modalContent += '<th>No Data</th></tr></thead><tbody><tr><td>No records found</td></tr>';
                }
                modalContent += '</tbody></table></div>';
                
                document.getElementById('reportModalBody').innerHTML = modalContent;
                new bootstrap.Modal(document.getElementById('reportDetailsModal')).show();
            } else {
                showAlert(response.message, 'danger');
            }
        });
}

function downloadMonthlyReport(month) {
    showAlert(`Downloading report for ${month}...`, 'info');
    // Implementation would generate and download PDF/Excel report
}

function emailReport(month) {
    showAlert(`Email feature for ${month} report will be implemented soon`, 'info');
}

function exportAllReports() {
    showAlert('Exporting all payroll reports...', 'info');
    // Implementation would export all reports as ZIP file
}

function generateMonthlyReport() {
    showAlert('Monthly report generation initiated...', 'success');
    bootstrap.Modal.getInstance(document.getElementById('newReportModal')).hide();
}

function openCustomReportTab() {
    bootstrap.Modal.getInstance(document.getElementById('newReportModal')).hide();
    document.querySelector('[data-bs-target="#custom"]').click();
}

function openAnalyticsTab() {
    bootstrap.Modal.getInstance(document.getElementById('newReportModal')).hide();
    document.querySelector('[data-bs-target="#analytics"]').click();
}

function printReport() {
    window.print();
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

<?php include '../layouts/footer.php'; ?>
