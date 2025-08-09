<?php
// HR Insights Action Handlers
include '../db.php';

if (!$conn) {
    die("Database connection failed");
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'schedule_interview':
        handleScheduleInterview();
        break;
    case 'generate_report':
        handleGenerateReport();
        break;
    case 'employee_details':
        handleEmployeeDetails();
        break;
    case 'department_analytics':
        handleDepartmentAnalytics();
        break;
    case 'turnover_analysis':
        handleTurnoverAnalysis();
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
}

function handleScheduleInterview() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Process interview scheduling
        $candidate_name = $_POST['candidate_name'] ?? '';
        $position = $_POST['position'] ?? '';
        $interview_date = $_POST['interview_date'] ?? '';
        $interview_time = $_POST['interview_time'] ?? '';
        $interviewer = $_POST['interviewer'] ?? '';
        $location = $_POST['location'] ?? '';
        
        // Here you would save to database
        // For now, return success
        echo json_encode([
            'success' => true,
            'message' => 'Interview scheduled successfully!',
            'data' => [
                'candidate' => $candidate_name,
                'date' => $interview_date,
                'time' => $interview_time
            ]
        ]);
    } else {
        // Return interview scheduling form HTML
        $form_html = '
        <form id="scheduleInterviewForm" onsubmit="submitInterviewForm(event)">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="candidate_name" class="form-label">Candidate Name</label>
                        <input type="text" class="form-control" id="candidate_name" name="candidate_name" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="position" class="form-label">Position</label>
                        <select class="form-select" id="position" name="position" required>
                            <option value="">Select Position</option>
                            <option value="Software Developer">Software Developer</option>
                            <option value="Marketing Manager">Marketing Manager</option>
                            <option value="Sales Executive">Sales Executive</option>
                            <option value="HR Specialist">HR Specialist</option>
                            <option value="Data Analyst">Data Analyst</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="interview_date" class="form-label">Interview Date</label>
                        <input type="date" class="form-control" id="interview_date" name="interview_date" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="interview_time" class="form-label">Interview Time</label>
                        <input type="time" class="form-control" id="interview_time" name="interview_time" required>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="interviewer" class="form-label">Interviewer</label>
                        <select class="form-select" id="interviewer" name="interviewer" required>
                            <option value="">Select Interviewer</option>
                            <option value="John Smith">John Smith - HR Manager</option>
                            <option value="Sarah Johnson">Sarah Johnson - Team Lead</option>
                            <option value="Mike Davis">Mike Davis - Department Head</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="location" class="form-label">Location/Method</label>
                        <select class="form-select" id="location" name="location" required>
                            <option value="">Select Location</option>
                            <option value="Conference Room A">Conference Room A</option>
                            <option value="Conference Room B">Conference Room B</option>
                            <option value="Zoom Meeting">Zoom Meeting</option>
                            <option value="Google Meet">Google Meet</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="notes" class="form-label">Additional Notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Any special instructions or requirements..."></textarea>
            </div>
            
            <div class="d-flex justify-content-end">
                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Schedule Interview</button>
            </div>
        </form>
        
        <script>
        function submitInterviewForm(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            formData.append("action", "schedule_interview");
            
            fetch("hr_insights_actions.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert("Interview scheduled successfully!");
                    bootstrap.Modal.getInstance(document.getElementById("insightsModal")).hide();
                } else {
                    alert("Error: " + (data.message || "Failed to schedule interview"));
                }
            })
            .catch(error => {
                console.error("Error:", error);
                alert("An error occurred while scheduling the interview");
            });
        }
        </script>';
        
        echo json_encode([
            'success' => true,
            'html' => $form_html
        ]);
    }
}

function handleGenerateReport() {
    global $conn;
    
    $report_type = $_POST['report_type'] ?? 'summary';
    
    // Generate different types of reports
    switch ($report_type) {
        case 'attendance':
            $data = generateAttendanceReport($conn);
            break;
        case 'payroll':
            $data = generatePayrollReport($conn);
            break;
        case 'performance':
            $data = generatePerformanceReport($conn);
            break;
        default:
            $data = generateSummaryReport($conn);
    }
    
    echo json_encode([
        'success' => true,
        'report_data' => $data,
        'message' => 'Report generated successfully'
    ]);
}

function handleEmployeeDetails() {
    global $conn;
    
    $query = "SELECT 
        e.id, e.first_name, e.last_name, e.email, e.phone, e.department_name, e.position, e.salary, e.status,
        e.date_joined, e.date_of_birth,
        COUNT(a.id) as total_attendance,
        AVG(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100 as attendance_rate
        FROM employees e
        LEFT JOIN attendance a ON e.id = a.employee_id
        WHERE e.status = 'active'
        GROUP BY e.id
        ORDER BY e.first_name, e.last_name
        LIMIT 20";
    
    $result = $conn->query($query);
    $employees = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $employees[] = $row;
        }
    }
    
    $table_html = '<div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Position</th>
                    <th>Attendance Rate</th>
                    <th>Join Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($employees as $emp) {
        $attendance_rate = round($emp['attendance_rate'] ?: 0, 1);
        $badge_class = $attendance_rate >= 90 ? 'success' : ($attendance_rate >= 80 ? 'warning' : 'danger');
        
        $table_html .= '<tr>
            <td>' . htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) . '</td>
            <td>' . htmlspecialchars($emp['department_name'] ?: 'N/A') . '</td>
            <td>' . htmlspecialchars($emp['position'] ?: 'N/A') . '</td>
            <td><span class="badge bg-' . $badge_class . '">' . $attendance_rate . '%</span></td>
            <td>' . ($emp['date_joined'] ? date('M d, Y', strtotime($emp['date_joined'])) : 'N/A') . '</td>
            <td><span class="badge bg-success">' . ucfirst($emp['status']) . '</span></td>
        </tr>';
    }
    
    $table_html .= '</tbody></table></div>';
    
    echo json_encode([
        'success' => true,
        'html' => $table_html
    ]);
}

function handleDepartmentAnalytics() {
    global $conn;
    
    $query = "SELECT 
        e.department_name as department,
        COUNT(e.id) as employee_count,
        AVG(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100 as attendance_rate,
        AVG(e.salary) as avg_salary
        FROM employees e
        LEFT JOIN attendance a ON e.id = a.employee_id AND MONTH(a.attendance_date) = MONTH(CURRENT_DATE())
        WHERE e.status = 'active'
        GROUP BY e.department_name
        ORDER BY employee_count DESC";
    
    $result = $conn->query($query);
    $departments = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $departments[] = $row;
        }
    }
    
    $chart_data = [
        'labels' => array_column($departments, 'department'),
        'employee_counts' => array_column($departments, 'employee_count'),
        'attendance_rates' => array_map('floatval', array_column($departments, 'attendance_rate'))
    ];
    
    $html_content = '<div class="row">
        <div class="col-md-6">
            <canvas id="deptEmployeeChart" width="300" height="200"></canvas>
        </div>
        <div class="col-md-6">
            <canvas id="deptAttendanceChart" width="300" height="200"></canvas>
        </div>
    </div>
    
    <div class="table-responsive mt-4">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Department</th>
                    <th>Employee Count</th>
                    <th>Attendance Rate</th>
                    <th>Avg Salary</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($departments as $dept) {
        $attendance_rate = round($dept['attendance_rate'] ?: 0, 1);
        $avg_salary = $dept['avg_salary'] ? '$' . number_format($dept['avg_salary'], 0) : 'N/A';
        $badge_class = $attendance_rate >= 90 ? 'success' : ($attendance_rate >= 80 ? 'warning' : 'danger');
        
        $html_content .= '<tr>
            <td>' . htmlspecialchars($dept['department'] ?: 'General') . '</td>
            <td><span class="badge bg-primary">' . $dept['employee_count'] . '</span></td>
            <td><span class="badge bg-' . $badge_class . '">' . $attendance_rate . '%</span></td>
            <td>' . $avg_salary . '</td>
        </tr>';
    }
    
    $html_content .= '</tbody></table></div>
    
    <script>
    setTimeout(() => {
        if (document.getElementById("deptEmployeeChart")) {
            const empCtx = document.getElementById("deptEmployeeChart").getContext("2d");
            new Chart(empCtx, {
                type: "bar",
                data: {
                    labels: ' . json_encode($chart_data['labels']) . ',
                    datasets: [{
                        label: "Employee Count",
                        data: ' . json_encode($chart_data['employee_counts']) . ',
                        backgroundColor: "#0d6efd"
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
            
            const attCtx = document.getElementById("deptAttendanceChart").getContext("2d");
            new Chart(attCtx, {
                type: "line",
                data: {
                    labels: ' . json_encode($chart_data['labels']) . ',
                    datasets: [{
                        label: "Attendance Rate (%)",
                        data: ' . json_encode($chart_data['attendance_rates']) . ',
                        borderColor: "#198754",
                        backgroundColor: "rgba(25, 135, 84, 0.1)",
                        fill: true
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }
    }, 100);
    </script>';
    
    echo json_encode([
        'success' => true,
        'html' => $html_content
    ]);
}

function handleTurnoverAnalysis() {
    global $conn;
    
    $current_month = date('Y-m');
    $last_month = date('Y-m', strtotime('-1 month'));
    
    // Get turnover data
    $query = "SELECT 
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_employees,
        COUNT(CASE WHEN status = 'inactive' AND DATE_FORMAT(updated_at, '%Y-%m') = '$current_month' THEN 1 END) as current_resignations,
        COUNT(CASE WHEN status = 'inactive' AND DATE_FORMAT(updated_at, '%Y-%m') = '$last_month' THEN 1 END) as last_resignations,
        COUNT(CASE WHEN DATE_FORMAT(date_joined, '%Y-%m') = '$current_month' THEN 1 END) as new_hires
        FROM employees";
    
    $result = $conn->query($query);
    $data = $result ? $result->fetch_assoc() : [];
    
    $turnover_rate = $data['active_employees'] > 0 ? 
        round(($data['current_resignations'] / $data['active_employees']) * 100, 2) : 0;
    
    $html_content = '<div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="text-primary">' . $data['active_employees'] . '</h5>
                    <small>Active Employees</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="text-danger">' . $data['current_resignations'] . '</h5>
                    <small>Resignations This Month</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="text-success">' . $data['new_hires'] . '</h5>
                    <small>New Hires This Month</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="text-warning">' . $turnover_rate . '%</h5>
                    <small>Turnover Rate</small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="alert alert-info">
        <h6>Analysis Summary:</h6>
        <ul>
            <li>Current turnover rate is ' . $turnover_rate . '% which is ' . 
                ($turnover_rate <= 10 ? 'excellent' : ($turnover_rate <= 15 ? 'good' : 'needs attention')) . '</li>
            <li>New hires (' . $data['new_hires'] . ') vs resignations (' . $data['current_resignations'] . ') ratio: ' . 
                ($data['new_hires'] >= $data['current_resignations'] ? 'Positive growth' : 'Net reduction') . '</li>
            <li>Recommended actions: ' . 
                ($turnover_rate > 15 ? 'Conduct exit interviews, improve retention strategies' : 'Continue current practices') . '</li>
        </ul>
    </div>';
    
    echo json_encode([
        'success' => true,
        'html' => $html_content
    ]);
}

// Helper functions for report generation
function generateSummaryReport($conn) {
    return [
        'total_employees' => 45,
        'attendance_rate' => 87.5,
        'leave_requests' => 12,
        'new_hires' => 3
    ];
}

function generateAttendanceReport($conn) {
    return [
        'report_type' => 'attendance',
        'period' => 'Current Month',
        'data' => 'Detailed attendance report data...'
    ];
}

function generatePayrollReport($conn) {
    return [
        'report_type' => 'payroll',
        'period' => 'Current Month',
        'data' => 'Detailed payroll report data...'
    ];
}

function generatePerformanceReport($conn) {
    return [
        'report_type' => 'performance',
        'period' => 'Current Quarter',
        'data' => 'Detailed performance report data...'
    ];
}
?>
