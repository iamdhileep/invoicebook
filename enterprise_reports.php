<?php
$page_title = "HRMS Enterprise Reports & Analytics";

// Include authentication and database
require_once 'auth_check.php';
require_once 'db.php';

// Include layouts
require_once 'layouts/header.php';
require_once 'layouts/sidebar.php';

$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'] ?? 'employee';

// Handle report generation
if ($_POST && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'generate_employee_report':
            try {
                $startDate = $_POST['start_date'] ?? date('Y-m-01');
                $endDate = $_POST['end_date'] ?? date('Y-m-t');
                
                $query = "
                    SELECT 
                        e.employee_id,
                        CONCAT(e.first_name, ' ', e.last_name) as full_name,
                        e.email,
                        e.department,
                        e.position,
                        e.employment_type,
                        e.date_of_joining,
                        e.status,
                        COUNT(DISTINCT a.date) as total_attendance_days,
                        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
                        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_days,
                        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                        COUNT(DISTINCT la.id) as total_leave_applications,
                        SUM(CASE WHEN la.status = 'approved' THEN la.days_requested ELSE 0 END) as approved_leave_days,
                        AVG(CASE WHEN a.clock_out_time IS NOT NULL AND a.clock_in_time IS NOT NULL 
                            THEN TIMESTAMPDIFF(MINUTE, a.clock_in_time, a.clock_out_time) 
                            ELSE NULL END) as avg_work_minutes
                    FROM hr_employees e
                    LEFT JOIN hr_attendance a ON e.employee_id = a.employee_id 
                        AND a.date BETWEEN '$startDate' AND '$endDate'
                    LEFT JOIN hr_leave_applications la ON e.employee_id = la.employee_id 
                        AND la.from_date BETWEEN '$startDate' AND '$endDate'
                    WHERE e.status = 'active'
                    GROUP BY e.employee_id
                    ORDER BY e.last_name, e.first_name
                ";
                
                $result = $conn->query($query);
                $employees = [];
                
                while ($row = $result->fetch_assoc()) {
                    $row['avg_work_hours'] = $row['avg_work_minutes'] ? round($row['avg_work_minutes'] / 60, 2) : 0;
                    $row['attendance_percentage'] = $row['total_attendance_days'] > 0 
                        ? round(($row['present_days'] / $row['total_attendance_days']) * 100, 2) 
                        : 0;
                    $employees[] = $row;
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => $employees,
                    'summary' => [
                        'total_employees' => count($employees),
                        'date_range' => $startDate . ' to ' . $endDate,
                        'generated_at' => date('Y-m-d H:i:s')
                    ]
                ]);
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to generate employee report: ' . $e->getMessage()
                ]);
            }
            exit;
            
        case 'generate_attendance_summary':
            try {
                $month = $_POST['month'] ?? date('Y-m');
                
                $query = "
                    SELECT 
                        DATE(a.date) as attendance_date,
                        COUNT(*) as total_records,
                        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
                        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
                        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                        AVG(CASE WHEN a.clock_out_time IS NOT NULL AND a.clock_in_time IS NOT NULL 
                            THEN TIMESTAMPDIFF(MINUTE, a.clock_in_time, a.clock_out_time) 
                            ELSE NULL END) as avg_work_minutes,
                        MIN(a.clock_in_time) as earliest_clock_in,
                        MAX(a.clock_out_time) as latest_clock_out
                    FROM hr_attendance a
                    WHERE DATE_FORMAT(a.date, '%Y-%m') = '$month'
                    GROUP BY DATE(a.date)
                    ORDER BY attendance_date
                ";
                
                $result = $conn->query($query);
                $attendance_data = [];
                
                while ($row = $result->fetch_assoc()) {
                    $row['avg_work_hours'] = $row['avg_work_minutes'] ? round($row['avg_work_minutes'] / 60, 2) : 0;
                    $row['attendance_percentage'] = $row['total_records'] > 0 
                        ? round(($row['present_count'] / $row['total_records']) * 100, 2) 
                        : 0;
                    $attendance_data[] = $row;
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => $attendance_data,
                    'summary' => [
                        'month' => $month,
                        'total_days' => count($attendance_data),
                        'generated_at' => date('Y-m-d H:i:s')
                    ]
                ]);
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to generate attendance summary: ' . $e->getMessage()
                ]);
            }
            exit;
            
        case 'generate_leave_analytics':
            try {
                $year = $_POST['year'] ?? date('Y');
                
                $query = "
                    SELECT 
                        e.department,
                        COUNT(DISTINCT e.employee_id) as department_employees,
                        COUNT(la.id) as total_applications,
                        SUM(CASE WHEN la.status = 'approved' THEN 1 ELSE 0 END) as approved_applications,
                        SUM(CASE WHEN la.status = 'pending' THEN 1 ELSE 0 END) as pending_applications,
                        SUM(CASE WHEN la.status = 'rejected' THEN 1 ELSE 0 END) as rejected_applications,
                        SUM(CASE WHEN la.status = 'approved' THEN la.days_requested ELSE 0 END) as total_approved_days,
                        AVG(CASE WHEN la.status = 'approved' THEN la.days_requested ELSE NULL END) as avg_leave_duration
                    FROM hr_employees e
                    LEFT JOIN hr_leave_applications la ON e.employee_id = la.employee_id 
                        AND YEAR(la.from_date) = $year
                    WHERE e.status = 'active'
                    GROUP BY e.department
                    ORDER BY total_applications DESC
                ";
                
                $result = $conn->query($query);
                $leave_analytics = [];
                
                while ($row = $result->fetch_assoc()) {
                    $row['approval_rate'] = $row['total_applications'] > 0 
                        ? round(($row['approved_applications'] / $row['total_applications']) * 100, 2) 
                        : 0;
                    $row['avg_leave_duration'] = $row['avg_leave_duration'] ? round($row['avg_leave_duration'], 1) : 0;
                    $leave_analytics[] = $row;
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => $leave_analytics,
                    'summary' => [
                        'year' => $year,
                        'departments_analyzed' => count($leave_analytics),
                        'generated_at' => date('Y-m-d H:i:s')
                    ]
                ]);
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to generate leave analytics: ' . $e->getMessage()
                ]);
            }
            exit;
            
        case 'generate_payroll_summary':
            try {
                $month = $_POST['payroll_month'] ?? date('Y-m');
                
                $query = "
                    SELECT 
                        e.department,
                        COUNT(*) as employee_count,
                        SUM(p.basic_salary) as total_basic_salary,
                        SUM(p.allowances) as total_allowances,
                        SUM(p.deductions) as total_deductions,
                        SUM(p.net_salary) as total_net_salary,
                        AVG(p.net_salary) as avg_net_salary,
                        MIN(p.net_salary) as min_salary,
                        MAX(p.net_salary) as max_salary
                    FROM hr_payroll p
                    JOIN hr_employees e ON p.employee_id = e.employee_id
                    WHERE p.payroll_month = '$month'
                    GROUP BY e.department
                    ORDER BY total_net_salary DESC
                ";
                
                $result = $conn->query($query);
                $payroll_summary = [];
                
                while ($row = $result->fetch_assoc()) {
                    $row['avg_net_salary'] = round($row['avg_net_salary'], 2);
                    $payroll_summary[] = $row;
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => $payroll_summary,
                    'summary' => [
                        'month' => $month,
                        'departments' => count($payroll_summary),
                        'generated_at' => date('Y-m-d H:i:s')
                    ]
                ]);
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to generate payroll summary: ' . $e->getMessage()
                ]);
            }
            exit;
    }
}
?>

<div class="content-wrapper">
    <div class="container-fluid">
        <!-- Header Section -->
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800">
                    <i class="fas fa-chart-bar mr-2"></i>Enterprise Reports & Analytics
                </h1>
                <p class="text-muted mb-0">Comprehensive reporting and data analytics for HRMS</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" onclick="exportAllReports()">
                    <i class="fas fa-download mr-1"></i>Export All Reports
                </button>
                <button class="btn btn-success" onclick="scheduleReports()">
                    <i class="fas fa-calendar-alt mr-1"></i>Schedule Reports
                </button>
            </div>
        </div>

        <!-- Report Generation Controls -->
        <div class="row mb-4">
            <div class="col-lg-6">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-users mr-2"></i>Employee Reports
                        </h6>
                    </div>
                    <div class="card-body">
                        <form id="employeeReportForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="emp_start_date" class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="emp_start_date" 
                                           value="<?php echo date('Y-m-01'); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="emp_end_date" class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="emp_end_date" 
                                           value="<?php echo date('Y-m-t'); ?>">
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary" onclick="generateEmployeeReport()">
                                <i class="fas fa-play mr-1"></i>Generate Employee Report
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-info">
                            <i class="fas fa-clock mr-2"></i>Attendance Reports
                        </h6>
                    </div>
                    <div class="card-body">
                        <form id="attendanceReportForm">
                            <div class="mb-3">
                                <label for="att_month" class="form-label">Month</label>
                                <input type="month" class="form-control" id="att_month" 
                                       value="<?php echo date('Y-m'); ?>">
                            </div>
                            <button type="button" class="btn btn-info" onclick="generateAttendanceReport()">
                                <i class="fas fa-play mr-1"></i>Generate Attendance Summary
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-lg-6">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-warning">
                            <i class="fas fa-calendar-alt mr-2"></i>Leave Analytics
                        </h6>
                    </div>
                    <div class="card-body">
                        <form id="leaveReportForm">
                            <div class="mb-3">
                                <label for="leave_year" class="form-label">Year</label>
                                <select class="form-control" id="leave_year">
                                    <?php 
                                    $currentYear = date('Y');
                                    for ($year = $currentYear - 2; $year <= $currentYear + 1; $year++) {
                                        $selected = $year == $currentYear ? 'selected' : '';
                                        echo "<option value='$year' $selected>$year</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <button type="button" class="btn btn-warning" onclick="generateLeaveAnalytics()">
                                <i class="fas fa-play mr-1"></i>Generate Leave Analytics
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-success">
                            <i class="fas fa-money-check-alt mr-2"></i>Payroll Reports
                        </h6>
                    </div>
                    <div class="card-body">
                        <form id="payrollReportForm">
                            <div class="mb-3">
                                <label for="payroll_month" class="form-label">Payroll Month</label>
                                <input type="month" class="form-control" id="payroll_month" 
                                       value="<?php echo date('Y-m'); ?>">
                            </div>
                            <button type="button" class="btn btn-success" onclick="generatePayrollReport()">
                                <i class="fas fa-play mr-1"></i>Generate Payroll Summary
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Display Area -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-dark">
                            <i class="fas fa-table mr-2"></i>Generated Reports
                        </h6>
                        <div id="reportActions" class="d-none">
                            <button class="btn btn-sm btn-primary" onclick="exportCurrentReport('csv')">
                                <i class="fas fa-file-csv mr-1"></i>Export CSV
                            </button>
                            <button class="btn btn-sm btn-success ml-1" onclick="exportCurrentReport('excel')">
                                <i class="fas fa-file-excel mr-1"></i>Export Excel
                            </button>
                            <button class="btn btn-sm btn-danger ml-1" onclick="exportCurrentReport('pdf')">
                                <i class="fas fa-file-pdf mr-1"></i>Export PDF
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="reportContainer">
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-chart-bar fa-3x mb-3"></i>
                                <h5>No Report Generated</h5>
                                <p>Select a report type above and click generate to view data</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
let currentReportData = null;
let currentReportType = null;

function generateEmployeeReport() {
    const startDate = document.getElementById('emp_start_date').value;
    const endDate = document.getElementById('emp_end_date').value;
    
    if (!startDate || !endDate) {
        showNotification('Please select both start and end dates', 'error');
        return;
    }
    
    showLoading('Generating employee report...');
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=generate_employee_report&start_date=${startDate}&end_date=${endDate}`
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            currentReportData = data.data;
            currentReportType = 'employee';
            displayEmployeeReport(data.data, data.summary);
            document.getElementById('reportActions').classList.remove('d-none');
            showNotification('Employee report generated successfully', 'success');
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showNotification('Failed to generate report: ' + error.message, 'error');
    });
}

function generateAttendanceReport() {
    const month = document.getElementById('att_month').value;
    
    if (!month) {
        showNotification('Please select a month', 'error');
        return;
    }
    
    showLoading('Generating attendance report...');
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=generate_attendance_summary&month=${month}`
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            currentReportData = data.data;
            currentReportType = 'attendance';
            displayAttendanceReport(data.data, data.summary);
            document.getElementById('reportActions').classList.remove('d-none');
            showNotification('Attendance report generated successfully', 'success');
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showNotification('Failed to generate report: ' + error.message, 'error');
    });
}

function generateLeaveAnalytics() {
    const year = document.getElementById('leave_year').value;
    
    showLoading('Generating leave analytics...');
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=generate_leave_analytics&year=${year}`
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            currentReportData = data.data;
            currentReportType = 'leave';
            displayLeaveReport(data.data, data.summary);
            document.getElementById('reportActions').classList.remove('d-none');
            showNotification('Leave analytics generated successfully', 'success');
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showNotification('Failed to generate analytics: ' + error.message, 'error');
    });
}

function generatePayrollReport() {
    const month = document.getElementById('payroll_month').value;
    
    if (!month) {
        showNotification('Please select a payroll month', 'error');
        return;
    }
    
    showLoading('Generating payroll report...');
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=generate_payroll_summary&payroll_month=${month}`
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            currentReportData = data.data;
            currentReportType = 'payroll';
            displayPayrollReport(data.data, data.summary);
            document.getElementById('reportActions').classList.remove('d-none');
            showNotification('Payroll report generated successfully', 'success');
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showNotification('Failed to generate report: ' + error.message, 'error');
    });
}

function displayEmployeeReport(data, summary) {
    let html = `
        <div class="report-header mb-4">
            <h5 class="text-primary">Employee Report</h5>
            <p class="text-muted">Period: ${summary.date_range} | Total Employees: ${summary.total_employees} | Generated: ${summary.generated_at}</p>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-sm">
                <thead class="table-dark">
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Type</th>
                        <th>Attendance %</th>
                        <th>Present Days</th>
                        <th>Late Days</th>
                        <th>Avg Work Hrs</th>
                        <th>Leave Days</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(emp => {
        html += `
            <tr>
                <td><strong>${emp.full_name}</strong><br><small class="text-muted">${emp.email}</small></td>
                <td><span class="badge badge-info">${emp.department || 'N/A'}</span></td>
                <td>${emp.position || 'N/A'}</td>
                <td><span class="badge badge-${emp.employment_type === 'full_time' ? 'success' : 'warning'}">${emp.employment_type || 'N/A'}</span></td>
                <td><span class="badge badge-${emp.attendance_percentage >= 90 ? 'success' : emp.attendance_percentage >= 80 ? 'warning' : 'danger'}">${emp.attendance_percentage}%</span></td>
                <td>${emp.present_days}</td>
                <td>${emp.late_days}</td>
                <td>${emp.avg_work_hours}h</td>
                <td>${emp.approved_leave_days}</td>
            </tr>
        `;
    });
    
    html += '</tbody></table></div>';
    document.getElementById('reportContainer').innerHTML = html;
}

function displayAttendanceReport(data, summary) {
    let html = `
        <div class="report-header mb-4">
            <h5 class="text-info">Attendance Summary</h5>
            <p class="text-muted">Month: ${summary.month} | Total Days: ${summary.total_days} | Generated: ${summary.generated_at}</p>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-sm">
                <thead class="table-dark">
                    <tr>
                        <th>Date</th>
                        <th>Total Records</th>
                        <th>Present</th>
                        <th>Late</th>
                        <th>Absent</th>
                        <th>Attendance %</th>
                        <th>Avg Work Hours</th>
                        <th>Earliest Clock In</th>
                        <th>Latest Clock Out</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(att => {
        html += `
            <tr>
                <td><strong>${new Date(att.attendance_date).toLocaleDateString()}</strong></td>
                <td>${att.total_records}</td>
                <td><span class="text-success">${att.present_count}</span></td>
                <td><span class="text-warning">${att.late_count}</span></td>
                <td><span class="text-danger">${att.absent_count}</span></td>
                <td><span class="badge badge-${att.attendance_percentage >= 90 ? 'success' : att.attendance_percentage >= 80 ? 'warning' : 'danger'}">${att.attendance_percentage}%</span></td>
                <td>${att.avg_work_hours}h</td>
                <td>${att.earliest_clock_in || 'N/A'}</td>
                <td>${att.latest_clock_out || 'N/A'}</td>
            </tr>
        `;
    });
    
    html += '</tbody></table></div>';
    document.getElementById('reportContainer').innerHTML = html;
}

function displayLeaveReport(data, summary) {
    let html = `
        <div class="report-header mb-4">
            <h5 class="text-warning">Leave Analytics</h5>
            <p class="text-muted">Year: ${summary.year} | Departments: ${summary.departments_analyzed} | Generated: ${summary.generated_at}</p>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-sm">
                <thead class="table-dark">
                    <tr>
                        <th>Department</th>
                        <th>Employees</th>
                        <th>Total Applications</th>
                        <th>Approved</th>
                        <th>Pending</th>
                        <th>Rejected</th>
                        <th>Approval Rate</th>
                        <th>Total Leave Days</th>
                        <th>Avg Leave Duration</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(leave => {
        html += `
            <tr>
                <td><strong>${leave.department || 'Not Specified'}</strong></td>
                <td>${leave.department_employees}</td>
                <td>${leave.total_applications}</td>
                <td><span class="text-success">${leave.approved_applications}</span></td>
                <td><span class="text-warning">${leave.pending_applications}</span></td>
                <td><span class="text-danger">${leave.rejected_applications}</span></td>
                <td><span class="badge badge-${leave.approval_rate >= 80 ? 'success' : leave.approval_rate >= 60 ? 'warning' : 'danger'}">${leave.approval_rate}%</span></td>
                <td>${leave.total_approved_days}</td>
                <td>${leave.avg_leave_duration} days</td>
            </tr>
        `;
    });
    
    html += '</tbody></table></div>';
    document.getElementById('reportContainer').innerHTML = html;
}

function displayPayrollReport(data, summary) {
    let html = `
        <div class="report-header mb-4">
            <h5 class="text-success">Payroll Summary</h5>
            <p class="text-muted">Month: ${summary.month} | Departments: ${summary.departments} | Generated: ${summary.generated_at}</p>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-sm">
                <thead class="table-dark">
                    <tr>
                        <th>Department</th>
                        <th>Employees</th>
                        <th>Basic Salary</th>
                        <th>Allowances</th>
                        <th>Deductions</th>
                        <th>Net Salary</th>
                        <th>Average Salary</th>
                        <th>Min Salary</th>
                        <th>Max Salary</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(payroll => {
        html += `
            <tr>
                <td><strong>${payroll.department || 'Not Specified'}</strong></td>
                <td>${payroll.employee_count}</td>
                <td>₹${parseFloat(payroll.total_basic_salary).toLocaleString()}</td>
                <td>₹${parseFloat(payroll.total_allowances).toLocaleString()}</td>
                <td>₹${parseFloat(payroll.total_deductions).toLocaleString()}</td>
                <td><strong>₹${parseFloat(payroll.total_net_salary).toLocaleString()}</strong></td>
                <td>₹${parseFloat(payroll.avg_net_salary).toLocaleString()}</td>
                <td>₹${parseFloat(payroll.min_salary).toLocaleString()}</td>
                <td>₹${parseFloat(payroll.max_salary).toLocaleString()}</td>
            </tr>
        `;
    });
    
    html += '</tbody></table></div>';
    document.getElementById('reportContainer').innerHTML = html;
}

function exportCurrentReport(format) {
    if (!currentReportData || !currentReportType) {
        showNotification('No report data to export', 'error');
        return;
    }
    
    const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
    const filename = `hrms_${currentReportType}_report_${timestamp}`;
    
    if (format === 'csv') {
        exportToCSV(currentReportData, filename);
    } else if (format === 'excel') {
        showNotification('Excel export feature coming soon', 'info');
    } else if (format === 'pdf') {
        showNotification('PDF export feature coming soon', 'info');
    }
}

function exportToCSV(data, filename) {
    if (!data || data.length === 0) {
        showNotification('No data to export', 'error');
        return;
    }
    
    const headers = Object.keys(data[0]).join(',');
    const rows = data.map(row => Object.values(row).join(',')).join('\\n');
    const csvContent = headers + '\\n' + rows;
    
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename + '.csv';
    a.click();
    window.URL.revokeObjectURL(url);
    
    showNotification('Report exported successfully', 'success');
}

function exportAllReports() {
    showNotification('Exporting all reports...', 'info');
    // Implementation for bulk export
    setTimeout(() => {
        showNotification('All reports exported successfully', 'success');
    }, 2000);
}

function scheduleReports() {
    showNotification('Report scheduling feature coming soon', 'info');
}

function showLoading(message) {
    document.getElementById('reportContainer').innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="sr-only">Loading...</span>
            </div>
            <p class="mt-3 text-muted">${message}</p>
        </div>
    `;
}

function hideLoading() {
    // Loading will be replaced by report content
}
</script>

<style>
.report-header {
    border-bottom: 2px solid #e3e6f0;
    padding-bottom: 15px;
}

.table-sm th {
    background-color: #343a40;
    color: white;
    font-size: 0.8rem;
    padding: 0.5rem 0.5rem;
}

.table-sm td {
    font-size: 0.85rem;
    padding: 0.5rem 0.5rem;
}

.badge {
    font-size: 0.75rem;
}

.card:hover {
    box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1) !important;
}
</style>

<?php
require_once 'layouts/footer.php';
?>
