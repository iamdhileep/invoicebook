<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Time Tracking & Payroll';

// Set timezone
date_default_timezone_set('Asia/Kolkata');
$today = date('Y-m-d');

include '../../layouts/header.php';
include '../../layouts/sidebar.php';

// Get employee statistics using existing tables
$employeeStats = [
    'total' => 0,
    'present' => 0,
    'avg_hours' => 0,
    'total_payroll' => 0
];

// Count total employees
$result = $conn->query("SELECT COUNT(*) as count FROM employees");
if ($result) {
    $employeeStats['total'] = $result->fetch_assoc()['count'];
}

// Count present today from attendance table
$result = $conn->query("SELECT COUNT(DISTINCT employee_id) as count FROM attendance WHERE date = '$today'");
if ($result) {
    $employeeStats['present'] = $result->fetch_assoc()['count'];
}
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="page-header mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="page-title mb-0">
                        <i class="bi bi-clock-history text-primary me-2"></i>
                        Time Tracking & Payroll
                    </h1>
                    <p class="text-muted mb-0">Manage attendance, salaries, and generate pay-slips</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" onclick="refreshStats()">
                        <i class="bi bi-arrow-clockwise me-1"></i>
                        Refresh
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#salaryConfigModal">
                        <i class="bi bi-gear me-1"></i>
                        Salary Config
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card text-center">
                    <div class="card-body">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary mb-3 mx-auto">
                            <i class="bi bi-people"></i>
                        </div>
                        <h3 class="stat-number text-primary mb-1"><?php echo $employeeStats['total']; ?></h3>
                        <p class="stat-label mb-0">Total Employees</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-center">
                    <div class="card-body">
                        <div class="stat-icon bg-success bg-opacity-10 text-success mb-3 mx-auto">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <h3 class="stat-number text-success mb-1"><?php echo $employeeStats['present']; ?></h3>
                        <p class="stat-label mb-0">Present Today</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-center">
                    <div class="card-body">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning mb-3 mx-auto">
                            <i class="bi bi-clock"></i>
                        </div>
                        <h3 class="stat-number text-warning mb-1">8.5</h3>
                        <p class="stat-label mb-0">Avg Working Hours</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-center">
                    <div class="card-body">
                        <div class="stat-icon bg-info bg-opacity-10 text-info mb-3 mx-auto">
                            <i class="bi bi-currency-rupee"></i>
                        </div>
                        <h3 class="stat-number text-info mb-1">₹2,45,000</h3>
                        <p class="stat-label mb-0">Total Payroll</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Tabs -->
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="timeTrackingTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button" role="tab">
                            <i class="bi bi-clock-history me-1"></i>
                            Attendance Overview
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="salary-tab" data-bs-toggle="tab" data-bs-target="#salary" type="button" role="tab">
                            <i class="bi bi-currency-rupee me-1"></i>
                            Salary Management
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="payslip-tab" data-bs-toggle="tab" data-bs-target="#payslip" type="button" role="tab">
                            <i class="bi bi-file-earmark-text me-1"></i>
                            Pay-slip Generation
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports" type="button" role="tab">
                            <i class="bi bi-graph-up me-1"></i>
                            Reports & Analytics
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="timeTrackingTabContent">
                    <!-- Attendance Overview Tab -->
                    <div class="tab-pane fade show active" id="attendance" role="tabpanel">
                        <div class="row">
                            <div class="col-md-8">
                                <h5 class="mb-3">Today's Attendance</h5>
                                <div class="table-responsive">
                                    <table class="table table-striped" id="attendanceTable">
                                        <thead>
                                            <tr>
                                                <th>Employee</th>
                                                <th>Check In</th>
                                                <th>Check Out</th>
                                                <th>Working Hours</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            // Get today's attendance
                                            $query = "SELECT e.name, a.check_in_time, a.check_out_time, a.status 
                                                     FROM employees e 
                                                     LEFT JOIN attendance a ON e.id = a.employee_id AND a.date = '$today'
                                                     ORDER BY e.name";
                                            $result = $conn->query($query);
                                            
                                            if ($result && $result->num_rows > 0) {
                                                while ($row = $result->fetch_assoc()) {
                                                    $checkIn = $row['check_in_time'] ? date('H:i', strtotime($row['check_in_time'])) : '-';
                                                    $checkOut = $row['check_out_time'] ? date('H:i', strtotime($row['check_out_time'])) : '-';
                                                    $status = $row['status'] ?: 'Absent';
                                                    $statusClass = $status == 'Present' ? 'success' : ($status == 'Late' ? 'warning' : 'danger');
                                                    
                                                    // Calculate working hours
                                                    $workingHours = '-';
                                                    if ($row['check_in_time'] && $row['check_out_time']) {
                                                        $start = new DateTime($row['check_in_time']);
                                                        $end = new DateTime($row['check_out_time']);
                                                        $diff = $start->diff($end);
                                                        $workingHours = $diff->format('%h:%I');
                                                    }
                                                    
                                                    echo "<tr>";
                                                    echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                                                    echo "<td>$checkIn</td>";
                                                    echo "<td>$checkOut</td>";
                                                    echo "<td>$workingHours</td>";
                                                    echo "<td><span class='badge bg-$statusClass'>$status</span></td>";
                                                    echo "</tr>";
                                                }
                                            } else {
                                                echo "<tr><td colspan='5' class='text-center'>No attendance data found</td></tr>";
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <h5 class="mb-3">Quick Actions</h5>
                                <div class="d-grid gap-2">
                                    <button class="btn btn-primary" onclick="markBulkAttendance()">
                                        <i class="bi bi-check-all me-1"></i>
                                        Mark Bulk Attendance
                                    </button>
                                    <button class="btn btn-outline-primary" onclick="exportAttendance()">
                                        <i class="bi bi-download me-1"></i>
                                        Export Today's Data
                                    </button>
                                    <button class="btn btn-outline-secondary" onclick="generateTimeSheet()">
                                        <i class="bi bi-calendar-week me-1"></i>
                                        Generate Timesheet
                                    </button>
                                </div>
                                
                                <div class="mt-4">
                                    <h6>Attendance Summary</h6>
                                    <div class="progress mb-2">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $employeeStats['total'] > 0 ? ($employeeStats['present'] / $employeeStats['total'] * 100) : 0; ?>%"></div>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo $employeeStats['present']; ?> of <?php echo $employeeStats['total']; ?> employees present
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Salary Management Tab -->
                    <div class="tab-pane fade" id="salary" role="tabpanel">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Salary Management</strong> - Configure employee salaries, allowances, and deductions here.
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Current Salary Configuration</h6>
                                    </div>
                                    <div class="card-body">
                                        <p>Manage base salaries, allowances, and benefits for all employees.</p>
                                        <button class="btn btn-primary btn-sm">Configure Salaries</button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Revised Salary Structure</h6>
                                    </div>
                                    <div class="card-body">
                                        <p>Review and approve salary revisions and increments.</p>
                                        <button class="btn btn-warning btn-sm">Review Changes</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pay-slip Generation Tab -->
                    <div class="tab-pane fade" id="payslip" role="tabpanel">
                        <div class="alert alert-success">
                            <i class="bi bi-file-earmark-text me-2"></i>
                            <strong>Pay-slip Generation</strong> - Generate monthly pay-slips for employees.
                        </div>
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Generate Pay-slips</h6>
                                    </div>
                                    <div class="card-body">
                                        <form class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label">Month</label>
                                                <select class="form-select">
                                                    <option>July 2025</option>
                                                    <option>June 2025</option>
                                                    <option>May 2025</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Employee</label>
                                                <select class="form-select">
                                                    <option>All Employees</option>
                                                    <option>John Doe</option>
                                                    <option>Jane Smith</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">&nbsp;</label>
                                                <button type="button" class="btn btn-primary d-block">Generate</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Recent Pay-slips</h6>
                                    </div>
                                    <div class="card-body">
                                        <small class="text-muted">No recent pay-slips generated</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Reports Tab -->
                    <div class="tab-pane fade" id="reports" role="tabpanel">
                        <div class="alert alert-primary">
                            <i class="bi bi-graph-up me-2"></i>
                            <strong>Reports & Analytics</strong> - View comprehensive attendance and payroll reports.
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <i class="bi bi-calendar-month text-primary" style="font-size: 2rem;"></i>
                                        <h6 class="mt-2">Monthly Reports</h6>
                                        <button class="btn btn-outline-primary btn-sm">View</button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <i class="bi bi-person-lines-fill text-success" style="font-size: 2rem;"></i>
                                        <h6 class="mt-2">Employee Reports</h6>
                                        <button class="btn btn-outline-success btn-sm">View</button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <i class="bi bi-currency-rupee text-info" style="font-size: 2rem;"></i>
                                        <h6 class="mt-2">Payroll Reports</h6>
                                        <button class="btn btn-outline-info btn-sm">View</button>
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

<!-- Salary Configuration Modal -->
<div class="modal fade" id="salaryConfigModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Salary Configuration</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Configure salary settings and policies here.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script>
// JavaScript functions for interactivity
function refreshStats() {
    location.reload();
}

function markBulkAttendance() {
    alert('Bulk attendance marking feature - to be implemented');
}

function exportAttendance() {
    alert('Export attendance feature - to be implemented');
}

function generateTimeSheet() {
    alert('Timesheet generation feature - to be implemented');
}

// Initialize DataTable for attendance
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('attendanceTable')) {
        // Simple table enhancement - no external dependencies
        console.log('Time Tracking page loaded successfully');
    }
});
</script>

<style>
.stat-card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    transition: all 0.2s ease-in-out;
}

.stat-card:hover {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    transform: translateY(-2px);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
}

.stat-label {
    font-size: 0.875rem;
    color: #6c757d;
}

.page-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    border-radius: 0.5rem;
    margin-bottom: 2rem;
}
</style>

<?php include '../../layouts/footer.php'; ?>
