<?php
$page_title = "Employee Reports & Analytics";

// Include authentication and database
require_once '../auth_check.php';
require_once '../db.php';

// Include layouts
require_once 'hrms_header_simple.php';
require_once 'hrms_sidebar_simple.php';

// Include HRMS UI fix
$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'] ?? 'employee';

// Handle AJAX requests for report generation
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'generate_report':
            $reportType = $_POST['report_type'] ?? '';
            $dateFrom = $_POST['date_from'] ?? '';
            $dateTo = $_POST['date_to'] ?? '';
            $department = $_POST['department'] ?? '';
            
            $data = [];
            $success = false;
            
            try {
                switch ($reportType) {
                    case 'employee_summary':
                        $sql = "SELECT 
                                    e.employee_id,
                                    e.first_name,
                                    e.last_name,
                                    e.email,
                                    e.department,
                                    e.designation,
                                    e.salary,
                                    e.employment_type,
                                    e.status,
                                    e.hire_date
                                FROM hr_employees e";
                        if ($department) {
                            $sql .= " WHERE e.department = '" . $conn->real_escape_string($department) . "'";
                        }
                        break;
                        
                    case 'attendance_summary':
                        $sql = "SELECT 
                                    e.employee_id,
                                    e.first_name,
                                    e.last_name,
                                    COUNT(a.id) as total_days,
                                    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
                                    SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                                    SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_days,
                                    ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / COUNT(a.id)) * 100, 2) as attendance_percentage
                                FROM hr_employees e
                                LEFT JOIN hr_attendance a ON e.employee_id = a.employee_id";
                        if ($dateFrom && $dateTo) {
                            $sql .= " AND a.date BETWEEN '$dateFrom' AND '$dateTo'";
                        }
                        if ($department) {
                            $sql .= " AND e.department = '" . $conn->real_escape_string($department) . "'";
                        }
                        $sql .= " GROUP BY e.employee_id, e.first_name, e.last_name";
                        break;
                        
                    case 'leave_summary':
                        $sql = "SELECT 
                                    e.employee_id,
                                    e.first_name,
                                    e.last_name,
                                    e.department,
                                    COUNT(la.id) as total_leaves,
                                    SUM(CASE WHEN la.status = 'approved' THEN DATEDIFF(la.end_date, la.start_date) + 1 ELSE 0 END) as approved_days,
                                    SUM(CASE WHEN la.status = 'pending' THEN DATEDIFF(la.end_date, la.start_date) + 1 ELSE 0 END) as pending_days,
                                    SUM(CASE WHEN la.status = 'rejected' THEN DATEDIFF(la.end_date, la.start_date) + 1 ELSE 0 END) as rejected_days
                                FROM hr_employees e
                                LEFT JOIN hr_leave_applications la ON e.employee_id = la.employee_id";
                        if ($dateFrom && $dateTo) {
                            $sql .= " AND la.start_date BETWEEN '$dateFrom' AND '$dateTo'";
                        }
                        if ($department) {
                            $sql .= " AND e.department = '" . $conn->real_escape_string($department) . "'";
                        }
                        $sql .= " GROUP BY e.employee_id, e.first_name, e.last_name, e.department";
                        break;
                        
                    case 'department_wise':
                        $sql = "SELECT 
                                    d.department_name,
                                    COUNT(e.employee_id) as total_employees,
                                    SUM(CASE WHEN e.status = 'active' THEN 1 ELSE 0 END) as active_employees,
                                    AVG(e.salary) as average_salary,
                                    MIN(e.hire_date) as oldest_hire_date,
                                    MAX(e.hire_date) as newest_hire_date
                                FROM hr_departments d
                                LEFT JOIN hr_employees e ON d.department_name = e.department
                                GROUP BY d.department_name";
                        break;
                }
                
                $result = $conn->query($sql);
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $data[] = $row;
                    }
                    $success = true;
                }
                
            } catch (Exception $e) {
                $data = ['error' => $e->getMessage()];
            }
            
            echo json_encode(['success' => $success, 'data' => $data]);
            exit;
            
        case 'export_report':
            // Handle export functionality
            $reportData = json_decode($_POST['report_data'], true);
            $format = $_POST['format'] ?? 'csv';
            
            if ($format === 'csv') {
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="employee_report_' . date('Y-m-d') . '.csv"');
                
                $output = fopen('php://output', 'w');
                
                if (!empty($reportData)) {
                    // Write headers
                    fputcsv($output, array_keys($reportData[0]));
                    
                    // Write data
                    foreach ($reportData as $row) {
                        fputcsv($output, $row);
                    }
                }
                
                fclose($output);
                exit;
            }
            break;
    }
}

// Get departments for filter
$departments = [];
$deptResult = $conn->query("SELECT DISTINCT department_name FROM hr_departments ORDER BY department_name");
if ($deptResult) {
    while ($row = $deptResult->fetch_assoc()) {
        $departments[] = $row['department_name'];
    }
}
?>

<div class="content-wrapper">
    <div class="container-fluid">
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-chart-bar mr-2"></i>Employee Reports & Analytics
            </h1>
        </div>

        <!-- Report Generation Form -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Generate Reports</h6>
                    </div>
                    <div class="card-body">
                        <form id="reportForm" class="row">
                            <div class="col-md-3 mb-3">
                                <label for="reportType" class="form-label">Report Type</label>
                                <select class="form-control" id="reportType" name="report_type" required>
                                    <option value="">Select Report Type</option>
                                    <option value="employee_summary">Employee Summary</option>
                                    <option value="attendance_summary">Attendance Summary</option>
                                    <option value="leave_summary">Leave Summary</option>
                                    <option value="department_wise">Department Wise</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2 mb-3">
                                <label for="dateFrom" class="form-label">From Date</label>
                                <input type="date" class="form-control" id="dateFrom" name="date_from">
                            </div>
                            
                            <div class="col-md-2 mb-3">
                                <label for="dateTo" class="form-label">To Date</label>
                                <input type="date" class="form-control" id="dateTo" name="date_to">
                            </div>
                            
                            <div class="col-md-2 mb-3">
                                <label for="department" class="form-label">Department</label>
                                <select class="form-control" id="department" name="department">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-flex">
                                    <button type="submit" class="btn btn-primary mr-2">
                                        <i class="fas fa-play mr-1"></i>Generate
                                    </button>
                                    <button type="button" class="btn btn-success" id="exportBtn" disabled>
                                        <i class="fas fa-download mr-1"></i>Export CSV
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Results -->
        <div class="row" id="reportResults" style="display: none;">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3 d-flex justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">Report Results</h6>
                        <small class="text-muted" id="reportInfo"></small>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="reportTable">
                                <thead class="thead-light">
                                    <!-- Dynamic headers -->
                                </thead>
                                <tbody>
                                    <!-- Dynamic content -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Analytics Cards -->
        <div class="row">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Reports Generated</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800" id="totalReports">0</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-file-alt fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Data Accuracy</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">98.5%</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Active Filters</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800" id="activeFilters">0</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-filter fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Export Downloads</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800" id="totalDownloads">0</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-download fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
let currentReportData = [];
let reportCounter = parseInt(localStorage.getItem('totalReports') || '0');
let downloadCounter = parseInt(localStorage.getItem('totalDownloads') || '0');

document.getElementById('totalReports').textContent = reportCounter;
document.getElementById('totalDownloads').textContent = downloadCounter;

document.getElementById('reportForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'generate_report');
    
    // Show loading
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Generating...';
    submitBtn.disabled = true;
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data.length > 0) {
            displayReport(data.data);
            currentReportData = data.data;
            document.getElementById('exportBtn').disabled = false;
            
            // Update counters
            reportCounter++;
            localStorage.setItem('totalReports', reportCounter);
            document.getElementById('totalReports').textContent = reportCounter;
        } else {
            alert('No data found for the selected criteria');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error generating report');
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
});

function displayReport(data) {
    const table = document.getElementById('reportTable');
    const thead = table.querySelector('thead');
    const tbody = table.querySelector('tbody');
    
    // Clear existing content
    thead.innerHTML = '';
    tbody.innerHTML = '';
    
    if (data.length === 0) return;
    
    // Create headers
    const headers = Object.keys(data[0]);
    const headerRow = document.createElement('tr');
    headers.forEach(header => {
        const th = document.createElement('th');
        th.textContent = header.replace(/_/g, ' ').toUpperCase();
        headerRow.appendChild(th);
    });
    thead.appendChild(headerRow);
    
    // Create rows
    data.forEach(row => {
        const tr = document.createElement('tr');
        headers.forEach(header => {
            const td = document.createElement('td');
            td.textContent = row[header] || '';
            tr.appendChild(td);
        });
        tbody.appendChild(tr);
    });
    
    // Show results section
    document.getElementById('reportResults').style.display = 'block';
    document.getElementById('reportInfo').textContent = `${data.length} records found`;
    
    // Update active filters count
    const filters = document.querySelectorAll('#reportForm select, #reportForm input[type="date"]');
    let activeCount = 0;
    filters.forEach(filter => {
        if (filter.value) activeCount++;
    });
    document.getElementById('activeFilters').textContent = activeCount;
}

document.getElementById('exportBtn').addEventListener('click', function() {
    if (currentReportData.length === 0) return;
    
    const formData = new FormData();
    formData.append('action', 'export_report');
    formData.append('report_data', JSON.stringify(currentReportData));
    formData.append('format', 'csv');
    
    // Create a form and submit for download
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    Array.from(formData.entries()).forEach(([key, value]) => {
        const input = document.createElement('input');
        input.name = key;
        input.value = value;
        form.appendChild(input);
    });
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
    
    // Update download counter
    downloadCounter++;
    localStorage.setItem('totalDownloads', downloadCounter);
    document.getElementById('totalDownloads').textContent = downloadCounter;
});

// Set default date range (last 30 days)
document.getElementById('dateTo').value = new Date().toISOString().split('T')[0];
const fromDate = new Date();
fromDate.setDate(fromDate.getDate() - 30);
document.getElementById('dateFrom').value = fromDate.toISOString().split('T')[0];
</script>

<style>
.border-left-primary { border-left: 0.25rem solid #4e73df !important; }
.border-left-success { border-left: 0.25rem solid #1cc88a !important; }
.border-left-info { border-left: 0.25rem solid #36b9cc !important; }
.border-left-warning { border-left: 0.25rem solid #f6c23e !important; }

.text-gray-800 { color: #5a5c69 !important; }
.text-gray-300 { color: #dddfeb !important; }

.card {
    border: none;
    border-radius: 0.35rem;
}

.card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white !important;
    border: none;
}

.table th {
    background-color: #f8f9fa;
    border-top: none;
}

#reportResults {
    animation: fadeIn 0.5s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<?php 
<?php require_once 'hrms_footer_simple.php'; ?>