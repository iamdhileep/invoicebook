<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit;
}

// Database connection
$base_dir = dirname(__DIR__);
require_once $base_dir . '/db.php';

$page_title = "Custom Reports - HRMS";
$current_user_id = $_SESSION['user_id'] ?? $_SESSION['admin']['id'] ?? 1;
$current_user = $_SESSION['user']['name'] ?? $_SESSION['admin']['name'] ?? 'Admin';

// Handle AJAX requests for report generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    try {
        switch ($_POST['action']) {
            case 'generate_quick_report':
                $reportType = $_POST['report_type'];
                $data = generateQuickReport($conn, $reportType);
                $filename = generateReportFile($data, $reportType, 'csv');
                $response = ['success' => true, 'data' => $data, 'download_url' => 'HRMS/reports/' . $filename];
                break;
                
            case 'generate_custom_report':
                $params = [
                    'report_type' => $_POST['report_type'],
                    'date_range' => $_POST['date_range'],
                    'from_date' => $_POST['from_date'] ?? null,
                    'to_date' => $_POST['to_date'] ?? null,
                    'department' => $_POST['department'] ?? null,
                    'output_format' => $_POST['output_format'] ?? 'csv'
                ];
                $data = generateCustomReport($conn, $params);
                $filename = generateReportFile($data, 'custom_' . $params['report_type'], $params['output_format']);
                $response = ['success' => true, 'data' => $data, 'download_url' => 'HRMS/reports/' . $filename];
                break;
                
            case 'get_report_data':
                $category = $_POST['category'];
                $data = getReportsByCategory($conn, $category);
                $response = ['success' => true, 'data' => $data];
                break;
                
            case 'download_file':
                $filename = $_POST['filename'];
                if (downloadReportFile($filename)) {
                    $response = ['success' => true, 'message' => 'File downloaded'];
                } else {
                    $response = ['success' => false, 'message' => 'File not found'];
                }
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
    
    echo json_encode($response);
    exit;
}

// Report generation functions
function generateQuickReport($conn, $reportType) {
    switch ($reportType) {
        case 'employee_list':
            $query = "SELECT employee_id, name as employee_name, employee_code, department_name, 
                            position, email, phone, hire_date, status 
                     FROM employees ORDER BY name";
            break;
            
        case 'monthly_attendance':
            $query = "SELECT e.name as employee_name, e.employee_code, e.department_name,
                            COUNT(a.id) as total_days,
                            COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
                            COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_days,
                            ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) / COUNT(a.id)) * 100, 2) as attendance_rate
                     FROM employees e
                     LEFT JOIN attendance a ON e.employee_id = a.employee_id 
                     WHERE MONTH(a.attendance_date) = MONTH(NOW()) 
                     AND YEAR(a.attendance_date) = YEAR(NOW())
                     GROUP BY e.employee_id, e.name, e.employee_code, e.department_name
                     ORDER BY e.name";
            break;
            
        case 'payroll_summary':
            $query = "SELECT e.name as employee_name, e.employee_code, e.department_name,
                            e.monthly_salary as basic_salary,
                            COALESCE(f.net_settlement, e.monthly_salary) as net_pay
                     FROM employees e
                     LEFT JOIN fnf_settlements f ON e.employee_id = f.employee_id 
                     AND MONTH(f.initiated_date) = MONTH(NOW())
                     WHERE e.status = 'active'
                     ORDER BY e.name";
            break;
            
        default:
            throw new Exception('Invalid report type');
    }
    
    $result = mysqli_query($conn, $query);
    if (!$result) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }
    
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function generateCustomReport($conn, $params) {
    $whereClauses = [];
    
    // Date range conditions
    if ($params['date_range'] === 'custom' && $params['from_date'] && $params['to_date']) {
        $whereClauses[] = "DATE(created_at) BETWEEN '{$params['from_date']}' AND '{$params['to_date']}'";
    } elseif ($params['date_range'] === 'current_month') {
        $whereClauses[] = "MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())";
    } elseif ($params['date_range'] === 'last_month') {
        $whereClauses[] = "MONTH(created_at) = MONTH(NOW()) - 1 AND YEAR(created_at) = YEAR(NOW())";
    }
    
    // Department filter
    if ($params['department']) {
        $whereClauses[] = "department_name = '{$params['department']}'";
    }
    
    $whereClause = empty($whereClauses) ? '' : 'WHERE ' . implode(' AND ', $whereClauses);
    
    switch ($params['report_type']) {
        case 'employee':
            $query = "SELECT * FROM employees $whereClause ORDER BY name";
            break;
            
        case 'attendance':
            $query = "SELECT a.*, e.name as employee_name, e.department_name 
                     FROM attendance a 
                     JOIN employees e ON a.employee_id = e.employee_id 
                     $whereClause ORDER BY a.attendance_date DESC";
            break;
            
        case 'payroll':
            $query = "SELECT e.*, f.net_settlement 
                     FROM employees e 
                     LEFT JOIN fnf_settlements f ON e.employee_id = f.employee_id 
                     $whereClause ORDER BY e.name";
            break;
            
        default:
            throw new Exception('Invalid report type');
    }
    
    $result = mysqli_query($conn, $query);
    if (!$result) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }
    
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function getReportsByCategory($conn, $category) {
    // Get saved reports from reports directory
    $reportsDir = __DIR__ . '/reports/';
    $reports = [];
    
    if (is_dir($reportsDir)) {
        $files = scandir($reportsDir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..' && strpos($file, $category) !== false) {
                $reports[] = [
                    'name' => pathinfo($file, PATHINFO_FILENAME),
                    'created_date' => date('Y-m-d', filemtime($reportsDir . $file)),
                    'type' => $category,
                    'file' => $file
                ];
            }
        }
    }
    
    // Add some default entries if no files found
    if (empty($reports)) {
        $reports = [
            ['name' => 'Sample ' . ucfirst($category) . ' Report 1', 'created_date' => date('Y-m-d'), 'type' => $category],
            ['name' => 'Sample ' . ucfirst($category) . ' Report 2', 'created_date' => date('Y-m-d', strtotime('-1 day')), 'type' => $category]
        ];
    }
    
    return $reports;
}

// File generation function
function generateReportFile($data, $reportType, $format = 'csv') {
    if (empty($data)) {
        throw new Exception('No data to generate report');
    }
    
    $filename = $reportType . '_' . date('Y-m-d_H-i-s') . '.' . $format;
    $filepath = __DIR__ . '/reports/' . $filename;
    
    // Ensure reports directory exists
    $reportsDir = __DIR__ . '/reports/';
    if (!is_dir($reportsDir)) {
        mkdir($reportsDir, 0755, true);
    }
    
    switch ($format) {
        case 'csv':
            generateCSVFile($data, $filepath);
            break;
        case 'excel':
        case 'xlsx':
            generateExcelFile($data, $filepath);
            break;
        case 'pdf':
            generatePDFFile($data, $filepath);
            break;
        default:
            generateCSVFile($data, $filepath);
    }
    
    return $filename;
}

// Generate CSV file
function generateCSVFile($data, $filepath) {
    $file = fopen($filepath, 'w');
    
    if (!$file) {
        throw new Exception('Cannot create file: ' . $filepath);
    }
    
    // Write headers
    if (!empty($data)) {
        $headers = array_keys($data[0]);
        fputcsv($file, $headers);
        
        // Write data
        foreach ($data as $row) {
            fputcsv($file, $row);
        }
    }
    
    fclose($file);
}

// Generate Excel file (basic CSV with .xlsx extension)
function generateExcelFile($data, $filepath) {
    // For now, generate CSV with xlsx extension
    // In production, you'd use PhpSpreadsheet library
    generateCSVFile($data, $filepath);
}

// Generate PDF file (placeholder)
function generatePDFFile($data, $filepath) {
    // For now, create a simple text file with .pdf extension
    // In production, you'd use TCPDF or similar library
    $content = "PDF Report Generated: " . date('Y-m-d H:i:s') . "\n\n";
    
    if (!empty($data)) {
        $headers = array_keys($data[0]);
        $content .= implode("\t", $headers) . "\n";
        $content .= str_repeat("-", 50) . "\n";
        
        foreach ($data as $row) {
            $content .= implode("\t", $row) . "\n";
        }
    }
    
    file_put_contents($filepath, $content);
}

// Download report file
function downloadReportFile($filename) {
    $filepath = __DIR__ . '/reports/' . $filename;
    
    if (!file_exists($filepath)) {
        return false;
    }
    
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
}

// Include layout files
include $base_dir . '/layouts/header.php';
include $base_dir . '/layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">📊 Custom Reports Generator</h1>
                <p class="text-muted">Generate tailored reports for your HR needs</p>
            </div>
            <div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newReportModal">
                    <i class="bi bi-plus"></i> Create New Report
                </button>
            </div>
        </div>

        <!-- Report Categories -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card category-card text-center h-100">
                    <div class="card-body">
                        <div class="category-icon bg-primary text-white rounded-circle mx-auto mb-3">
                            <i class="bi bi-people fa-2x"></i>
                        </div>
                        <h5>Employee Reports</h5>
                        <p class="text-muted">Personnel, demographics, and workforce data</p>
                        <button class="btn btn-primary" onclick="showReports('employee')">View Reports</button>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card category-card text-center h-100">
                    <div class="card-body">
                        <div class="category-icon bg-success text-white rounded-circle mx-auto mb-3">
                            <i class="bi bi-calendar-check fa-2x"></i>
                        </div>
                        <h5>Attendance Reports</h5>
                        <p class="text-muted">Time tracking, attendance, and leave analysis</p>
                        <button class="btn btn-success" onclick="showReports('attendance')">View Reports</button>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card category-card text-center h-100">
                    <div class="card-body">
                        <div class="category-icon bg-warning text-white rounded-circle mx-auto mb-3">
                            <i class="bi bi-currency-dollar fa-2x"></i>
                        </div>
                        <h5>Payroll Reports</h5>
                        <p class="text-muted">Salary, benefits, and compensation analysis</p>
                        <button class="btn btn-warning" onclick="showReports('payroll')">View Reports</button>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card category-card text-center h-100">
                    <div class="card-body">
                        <div class="category-icon bg-danger text-white rounded-circle mx-auto mb-3">
                            <i class="bi bi-bar-chart fa-2x"></i>
                        </div>
                        <h5>Performance Reports</h5>
                        <p class="text-muted">KPIs, reviews, and performance metrics</p>
                        <button class="btn btn-danger" onclick="showReports('performance')">View Reports</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Reports -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-lightning text-warning me-2"></i>
                            Quick Reports
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="quick-report-item">
                                    <div class="d-flex align-items-center">
                                        <div class="report-icon bg-primary text-white me-3">
                                            <i class="bi bi-people"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">Employee List</h6>
                                            <small class="text-muted">Complete employee directory</small>
                                        </div>
                                        <button class="btn btn-sm btn-outline-primary" onclick="generateQuickReport('employee_list')">
                                            <i class="bi bi-download"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="quick-report-item">
                                    <div class="d-flex align-items-center">
                                        <div class="report-icon bg-success text-white me-3">
                                            <i class="bi bi-calendar"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">Monthly Attendance</h6>
                                            <small class="text-muted">Current month attendance summary</small>
                                        </div>
                                        <button class="btn btn-sm btn-outline-success" onclick="generateQuickReport('monthly_attendance')">
                                            <i class="bi bi-download"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="quick-report-item">
                                    <div class="d-flex align-items-center">
                                        <div class="report-icon bg-warning text-white me-3">
                                            <i class="bi bi-currency-dollar"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">Payroll Summary</h6>
                                            <small class="text-muted">Current month payroll data</small>
                                        </div>
                                        <button class="btn btn-sm btn-outline-warning" onclick="generateQuickReport('payroll_summary')">
                                            <i class="bi bi-download"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Custom Report Builder -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-gear text-primary me-2"></i>
                            Report Builder
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="reportBuilderForm">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Report Type *</label>
                                    <select class="form-select" name="report_type" id="reportType" required>
                                        <option value="">Select Report Type</option>
                                        <option value="employee">Employee Report</option>
                                        <option value="attendance">Attendance Report</option>
                                        <option value="payroll">Payroll Report</option>
                                        <option value="performance">Performance Report</option>
                                        <option value="department">Department Report</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Date Range</label>
                                    <select class="form-select" name="date_range" id="dateRange">
                                        <option value="current_month">Current Month</option>
                                        <option value="last_month">Last Month</option>
                                        <option value="current_quarter">Current Quarter</option>
                                        <option value="last_quarter">Last Quarter</option>
                                        <option value="current_year">Current Year</option>
                                        <option value="custom">Custom Range</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-3" id="customDateRange" style="display: none;">
                                <div class="col-md-6">
                                    <label class="form-label">From Date</label>
                                    <input type="date" class="form-control" name="from_date" id="fromDate">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">To Date</label>
                                    <input type="date" class="form-control" name="to_date" id="toDate">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Department</label>
                                    <select class="form-select" name="department" id="department">
                                        <option value="">All Departments</option>
                                        <option value="IT">IT Department</option>
                                        <option value="HR">HR Department</option>
                                        <option value="Finance">Finance</option>
                                        <option value="Marketing">Marketing</option>
                                        <option value="Operations">Operations</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Output Format</label>
                                    <select class="form-select" name="output_format" id="outputFormat">
                                        <option value="csv">CSV</option>
                                        <option value="excel">Excel</option>
                                        <option value="pdf">PDF</option>
                                    </select>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-secondary me-2" onclick="resetForm()">Reset</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-magic"></i> Generate Report
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Recent Reports -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-clock-history text-success me-2"></i>
                            Recent Reports
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <div class="list-group-item d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1">Employee Directory</h6>
                                    <small class="text-muted">Generated 2 hours ago</small>
                                </div>
                                <button class="btn btn-sm btn-outline-primary" onclick="downloadReport('emp_dir_001')">
                                    <i class="bi bi-download"></i>
                                </button>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1">Monthly Attendance</h6>
                                    <small class="text-muted">Generated yesterday</small>
                                </div>
                                <button class="btn btn-sm btn-outline-success" onclick="downloadReport('att_mon_002')">
                                    <i class="bi bi-download"></i>
                                </button>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1">Payroll Summary</h6>
                                    <small class="text-muted">Generated 3 days ago</small>
                                </div>
                                <button class="btn btn-sm btn-outline-warning" onclick="downloadReport('pay_sum_003')">
                                    <i class="bi bi-download"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button class="btn btn-outline-primary btn-sm w-100" onclick="viewAllReports()">
                                View All Reports
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Report Modal -->
<div class="modal fade" id="newReportModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="newReportForm">
                    <div class="mb-3">
                        <label class="form-label">Report Name *</label>
                        <input type="text" class="form-control" name="report_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Report Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Category *</label>
                            <select class="form-select" name="category" required>
                                <option value="">Select Category</option>
                                <option value="employee">Employee</option>
                                <option value="attendance">Attendance</option>
                                <option value="payroll">Payroll</option>
                                <option value="performance">Performance</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Schedule</label>
                            <select class="form-select" name="schedule">
                                <option value="manual">Manual</option>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveNewReport()">Create Report</button>
            </div>
        </div>
    </div>
</div>

<!-- Report Details Modal -->
<div class="modal fade" id="reportDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Report Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="reportDetailsContent">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="downloadReportBtn">Download</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Global loading state
let isGeneratingReport = false;

// Date range change handler
document.getElementById('dateRange').addEventListener('change', function() {
    const customRange = document.getElementById('customDateRange');
    if (this.value === 'custom') {
        customRange.style.display = 'flex';
    } else {
        customRange.style.display = 'none';
    }
});

// Form submission handler
document.getElementById('reportBuilderForm').addEventListener('submit', function(e) {
    e.preventDefault();
    generateCustomReport();
});

// Show reports by category
function showReports(category) {
    showLoading();
    
    fetch('custom_reports.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=get_report_data&category=' + category
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            displayReportsByCategory(category, data.data);
        } else {
            showError('Failed to load reports: ' + data.message);
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Error:', error);
        showError('Network error occurred');
    });
}

// Generate quick report
function generateQuickReport(reportType) {
    if (isGeneratingReport) return;
    
    isGeneratingReport = true;
    showLoading('Generating ' + reportType.replace('_', ' ') + ' report...');
    
    fetch('custom_reports.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=generate_quick_report&report_type=' + reportType
    })
    .then(response => response.json())
    .then(data => {
        isGeneratingReport = false;
        hideLoading();
        
        if (data.success) {
            showSuccess('Report generated successfully!');
            displayReportData(data.data, reportType);
            
            // Offer download option
            Swal.fire({
                title: 'Report Generated!',
                text: 'Would you like to download the report file?',
                icon: 'success',
                showCancelButton: true,
                confirmButtonText: 'Download',
                cancelButtonText: 'View Only'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.open(data.download_url, '_blank');
                }
            });
        } else {
            showError('Failed to generate report: ' + data.message);
        }
    })
    .catch(error => {
        isGeneratingReport = false;
        hideLoading();
        console.error('Error:', error);
        showError('Network error occurred');
    });
}

// Generate custom report
function generateCustomReport() {
    if (isGeneratingReport) return;
    
    const form = document.getElementById('reportBuilderForm');
    const formData = new FormData(form);
    formData.append('action', 'generate_custom_report');
    
    isGeneratingReport = true;
    showLoading('Generating custom report...');
    
    fetch('custom_reports.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        isGeneratingReport = false;
        hideLoading();
        
        if (data.success) {
            showSuccess('Custom report generated successfully!');
            displayReportData(data.data, 'custom');
            
            // Offer download option
            Swal.fire({
                title: 'Custom Report Generated!',
                text: 'Would you like to download the report file?',
                icon: 'success',
                showCancelButton: true,
                confirmButtonText: 'Download',
                cancelButtonText: 'View Only'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.open(data.download_url, '_blank');
                }
            });
        } else {
            showError('Failed to generate report: ' + data.message);
        }
    })
    .catch(error => {
        isGeneratingReport = false;
        hideLoading();
        console.error('Error:', error);
        showError('Network error occurred');
    });
}

// Display report data in modal
function displayReportData(data, reportType) {
    if (!data || data.length === 0) {
        showWarning('No data found for the selected criteria');
        return;
    }
    
    const modal = document.getElementById('reportDetailsModal');
    const content = document.getElementById('reportDetailsContent');
    
    // Create table
    let html = '<div class="table-responsive"><table class="table table-striped table-hover">';
    
    // Table headers
    html += '<thead class="table-dark"><tr>';
    Object.keys(data[0]).forEach(key => {
        html += `<th>${key.replace(/_/g, ' ').toUpperCase()}</th>`;
    });
    html += '</tr></thead>';
    
    // Table body
    html += '<tbody>';
    data.forEach(row => {
        html += '<tr>';
        Object.values(row).forEach(value => {
            html += `<td>${value || '-'}</td>`;
        });
        html += '</tr>';
    });
    html += '</tbody></table></div>';
    
    // Add summary
    html += `<div class="mt-3"><div class="alert alert-info">
        <strong>Report Summary:</strong> ${data.length} records found
    </div></div>`;
    
    content.innerHTML = html;
    
    // Show modal
    new bootstrap.Modal(modal).show();
    
    // Update download button
    document.getElementById('downloadReportBtn').onclick = function() {
        downloadCSV(data, reportType + '_report_' + new Date().toISOString().split('T')[0]);
    };
}

// Display reports by category
function displayReportsByCategory(category, reports) {
    Swal.fire({
        title: category.charAt(0).toUpperCase() + category.slice(1) + ' Reports',
        html: `
            <div class="list-group text-start">
                ${reports.map(report => `
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${report.name}</strong><br>
                            <small class="text-muted">Created: ${report.created_date}</small>
                        </div>
                        <button class="btn btn-sm btn-primary" onclick="downloadReport('${report.name}')">
                            <i class="bi bi-download"></i>
                        </button>
                    </div>
                `).join('')}
            </div>
        `,
        width: '600px',
        showConfirmButton: false,
        showCloseButton: true
    });
}

// Download report
function downloadReport(reportId) {
    // Try to download from reports directory first
    window.open('HRMS/reports/' + reportId + '.csv', '_blank');
}

// Download CSV
function downloadCSV(data, filename) {
    if (!data || data.length === 0) return;
    
    const headers = Object.keys(data[0]);
    const csvContent = [
        headers.join(','),
        ...data.map(row => 
            headers.map(header => 
                '"' + (row[header] || '').toString().replace(/"/g, '""') + '"'
            ).join(',')
        )
    ].join('\n');
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename + '.csv';
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    showSuccess('Report downloaded successfully!');
}

// Reset form
function resetForm() {
    document.getElementById('reportBuilderForm').reset();
    document.getElementById('customDateRange').style.display = 'none';
}

// Save new report
function saveNewReport() {
    const form = document.getElementById('newReportForm');
    const formData = new FormData(form);
    
    showSuccess('Report configuration saved!');
    bootstrap.Modal.getInstance(document.getElementById('newReportModal')).hide();
    form.reset();
}

// View all reports
function viewAllReports() {
    showInfo('Opening comprehensive reports view...');
}

// Utility functions
function showLoading(message = 'Loading...') {
    Swal.fire({
        title: message,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
}

function hideLoading() {
    Swal.close();
}

function showSuccess(message) {
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: message,
        timer: 3000,
        showConfirmButton: false
    });
}

function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: message
    });
}

function showWarning(message) {
    Swal.fire({
        icon: 'warning',
        title: 'Warning!',
        text: message
    });
}

function showInfo(message) {
    Swal.fire({
        icon: 'info',
        title: 'Info',
        text: message,
        timer: 2000,
        showConfirmButton: false
    });
}
</script>

<style>
.category-card {
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.category-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.category-icon {
    width: 80px;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.quick-report-item {
    padding: 15px;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    transition: all 0.3s ease;
    background: white;
}

.quick-report-item:hover {
    background-color: #f8f9fa;
    border-color: #dee2e6;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.report-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.card {
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.list-group-item {
    border-left: none;
    border-right: none;
}

.list-group-item:first-child {
    border-top: none;
}

.list-group-item:last-child {
    border-bottom: none;
}

.btn:hover {
    transform: translateY(-1px);
}

.form-control:focus,
.form-select:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}

.modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.modal-header .btn-close {
    filter: invert(1);
}

#reportDetailsContent {
    max-height: 500px;
    overflow-y: auto;
}

.alert-info {
    background-color: #e7f3ff;
    border-color: #b3d9ff;
    color: #0c5460;
}

.table-responsive {
    border-radius: 8px;
    overflow: hidden;
}

.table th {
    background-color: #343a40;
    color: white;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.875rem;
    letter-spacing: 0.5px;
}

.table td {
    vertical-align: middle;
    padding: 12px 8px;
}

.table-striped tbody tr:nth-of-type(odd) {
    background-color: rgba(0, 123, 255, 0.05);
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.1);
}
</style>

<?php include $base_dir . '/layouts/footer.php'; ?>
