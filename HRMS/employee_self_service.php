<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Employee authentication - check if employee is logged in
$is_employee_login = false;
$employee_data = null;
$employee_id = null;

// Check if admin is logged in (for demo/testing purposes)
if (isset($_SESSION['admin'])) {
    $is_admin = true;
    // For demo, we'll use the first employee in database
} else {
    // Check for employee login session
    if (!isset($_SESSION['employee_id']) && !isset($_GET['emp_id'])) {
        // Redirect to employee login
        header("Location: employee_login.php");
        exit;
    }
    $is_admin = false;
}

$page_title = "Employee Self-Service Portal";

// Include database connection and layouts
include '../db.php';
include '../layouts/header.php';
include '../layouts/sidebar.php';

// Get employee ID - from session, URL parameter, or admin demo
if (isset($_SESSION['employee_id'])) {
    $employee_id = $_SESSION['employee_id'];
} elseif (isset($_GET['emp_id'])) {
    $employee_id = intval($_GET['emp_id']);
    $_SESSION['employee_id'] = $employee_id; // Store for session
} elseif ($is_admin) {
    // For admin demo, use first available employee
    $demo_emp = $conn->query("SELECT id FROM hr_employees WHERE status = 'active' LIMIT 1");
    if ($demo_emp && $demo_emp->num_rows > 0) {
        $employee_id = $demo_emp->fetch_assoc()['id'];
    } else {
        // Create demo employee if none exists
        $conn->query("INSERT INTO hr_employees (employee_id, first_name, last_name, email, position, department_id, status, date_of_joining) VALUES ('EMP001', 'John', 'Doe', 'john.doe@company.com', 'Software Developer', 1, 'active', '2024-01-15')");
        $employee_id = $conn->insert_id;
    }
}

// Handle AJAX requests first
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'update_personal_info':
            $phone = mysqli_real_escape_string($conn, $_POST['phone']);
            $address = mysqli_real_escape_string($conn, $_POST['address']);
            $emergency_contact = mysqli_real_escape_string($conn, $_POST['emergency_contact']);
            $emergency_phone = mysqli_real_escape_string($conn, $_POST['emergency_phone']);
            
            $query = "UPDATE hr_employees SET 
                      phone = '$phone',
                      address = '$address',
                      emergency_contact = '$emergency_contact',
                      emergency_phone = '$emergency_phone'
                      WHERE id = $employee_id";
            
            $result = $conn->query($query);
            echo json_encode(['success' => $result, 'message' => $result ? 'Personal information updated successfully' : 'Failed to update personal information']);
            exit;
            
        case 'request_leave':
            $leave_type = mysqli_real_escape_string($conn, $_POST['leave_type']);
            $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
            $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
            $reason = mysqli_real_escape_string($conn, $_POST['reason']);
            
            // Calculate days
            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            $days = $start->diff($end)->days + 1;
            
            $query = "INSERT INTO hr_leave_requests (employee_id, leave_type, start_date, end_date, days, reason, status, applied_date) 
                      VALUES ($employee_id, '$leave_type', '$start_date', '$end_date', $days, '$reason', 'pending', CURDATE())";
            
            $result = $conn->query($query);
            echo json_encode(['success' => $result, 'message' => $result ? 'Leave request submitted successfully' : 'Failed to submit leave request']);
            exit;
            
        case 'clock_in_out':
            $action_type = $_POST['clock_action']; // 'in' or 'out'
            $current_time = date('H:i:s');
            $current_date = date('Y-m-d');
            
            // Check if attendance record exists for today
            $check_query = "SELECT * FROM hr_attendance WHERE employee_id = $employee_id AND date = '$current_date'";
            $check_result = $conn->query($check_query);
            
            if ($action_type === 'in') {
                if ($check_result && $check_result->num_rows > 0) {
                    echo json_encode(['success' => false, 'message' => 'Already clocked in today']);
                } else {
                    $query = "INSERT INTO hr_attendance (employee_id, date, clock_in, status) 
                              VALUES ($employee_id, '$current_date', '$current_time', 'present')";
                    $result = $conn->query($query);
                    echo json_encode(['success' => $result, 'message' => $result ? 'Clocked in successfully at ' . $current_time : 'Failed to clock in']);
                }
            } else { // clock out
                if ($check_result && $check_result->num_rows > 0) {
                    $query = "UPDATE hr_attendance SET clock_out = '$current_time' WHERE employee_id = $employee_id AND date = '$current_date'";
                    $result = $conn->query($query);
                    echo json_encode(['success' => $result, 'message' => $result ? 'Clocked out successfully at ' . $current_time : 'Failed to clock out']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'No clock in record found for today']);
                }
            }
            exit;
            
        case 'get_attendance_history':
            $limit = intval($_POST['limit'] ?? 30);
            $query = "SELECT * FROM hr_attendance WHERE employee_id = $employee_id ORDER BY date DESC LIMIT $limit";
            $result = $conn->query($query);
            $data = [];
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
            }
            echo json_encode(['success' => true, 'data' => $data]);
            exit;
            
        case 'get_leave_history':
            $query = "SELECT * FROM hr_leave_requests WHERE employee_id = $employee_id ORDER BY applied_date DESC";
            $result = $conn->query($query);
            $data = [];
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
            }
            echo json_encode(['success' => true, 'data' => $data]);
            exit;
            
        case 'get_payslips':
            // Create payslips table if not exists
            $conn->query("CREATE TABLE IF NOT EXISTS hr_payslips (
                id INT PRIMARY KEY AUTO_INCREMENT,
                employee_id INT NOT NULL,
                pay_period VARCHAR(50),
                gross_salary DECIMAL(10,2),
                deductions DECIMAL(10,2),
                net_salary DECIMAL(10,2),
                generated_date DATE,
                status ENUM('draft', 'final') DEFAULT 'final',
                FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE
            )");
            
            $query = "SELECT * FROM hr_payslips WHERE employee_id = $employee_id ORDER BY generated_date DESC LIMIT 12";
            $result = $conn->query($query);
            $data = [];
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
            } else {
                // Generate sample payslips if none exist
                $salary = 5000; // Default salary
                $emp_salary_query = $conn->query("SELECT salary FROM hr_employees WHERE id = $employee_id");
                if ($emp_salary_query && $emp_salary_query->num_rows > 0) {
                    $salary = $emp_salary_query->fetch_assoc()['salary'] ?: 5000;
                }
                
                for ($i = 0; $i < 6; $i++) {
                    $date = date('Y-m-01', strtotime("-$i months"));
                    $period = date('F Y', strtotime($date));
                    $deductions = round($salary * 0.12, 2); // 12% deductions
                    $net = $salary - $deductions;
                    
                    $conn->query("INSERT INTO hr_payslips (employee_id, pay_period, gross_salary, deductions, net_salary, generated_date) 
                                  VALUES ($employee_id, '$period', $salary, $deductions, $net, '$date')");
                }
                
                // Re-fetch data
                $result = $conn->query($query);
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $data[] = $row;
                    }
                }
            }
            echo json_encode(['success' => true, 'data' => $data]);
            exit;
            
        case 'update_password':
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            if ($new_password !== $confirm_password) {
                echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
                exit;
            }
            
            // For demo, we'll just update without checking current password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $query = "UPDATE hr_employees SET password = '$hashed_password' WHERE id = $employee_id";
            $result = $conn->query($query);
            
            echo json_encode(['success' => $result, 'message' => $result ? 'Password updated successfully' : 'Failed to update password']);
            exit;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
    }
}

// Create required tables if they don't exist
$conn->query("CREATE TABLE IF NOT EXISTS hr_attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    date DATE NOT NULL,
    clock_in TIME,
    clock_out TIME,
    break_start TIME,
    break_end TIME,
    status ENUM('present', 'absent', 'late', 'half-day') DEFAULT 'present',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance (employee_id, date)
)");

$conn->query("CREATE TABLE IF NOT EXISTS hr_leave_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    leave_type ENUM('annual', 'sick', 'personal', 'maternity', 'paternity', 'emergency') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days INT NOT NULL,
    reason TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    applied_date DATE DEFAULT (CURRENT_DATE),
    approved_by INT,
    approved_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE
)");

// Ensure hr_employees table has required columns
$conn->query("ALTER TABLE hr_employees 
    ADD COLUMN IF NOT EXISTS department VARCHAR(100),
    ADD COLUMN IF NOT EXISTS position VARCHAR(100)");

// Update employees with missing department/position data
$conn->query("UPDATE hr_employees SET 
    department = CASE 
        WHEN department IS NULL OR department = '' THEN 
            CASE department_id
                WHEN 1 THEN 'Information Technology'
                WHEN 2 THEN 'Human Resources' 
                WHEN 3 THEN 'Sales'
                WHEN 4 THEN 'Marketing'
                WHEN 5 THEN 'Operations'
                WHEN 6 THEN 'Finance'
                ELSE 'General'
            END
        ELSE department
    END,
    position = CASE 
        WHEN position IS NULL OR position = '' THEN 'Employee'
        ELSE position
    END
    WHERE id = $employee_id");

// Get employee data
$employee_query = "SELECT 
    id, employee_id, first_name, last_name, email, phone, 
    COALESCE(position, 'Employee') as position,
    COALESCE(department, 'General') as department,
    date_of_joining, salary, status, address, emergency_contact, emergency_phone
    FROM hr_employees WHERE id = $employee_id";
$employee_result = $conn->query($employee_query);

if (!$employee_result || $employee_result->num_rows === 0) {
    die("Employee not found");
}

$employee = $employee_result->fetch_assoc();

// Get today's attendance status
$today = date('Y-m-d');
$attendance_query = "SELECT * FROM hr_attendance WHERE employee_id = $employee_id AND date = '$today'";
$attendance_result = $conn->query($attendance_query);
$today_attendance = $attendance_result ? $attendance_result->fetch_assoc() : null;

// Get leave balance (assume 30 days annual leave per year)
$leave_used_query = "SELECT COALESCE(SUM(days), 0) as used_days FROM hr_leave_requests 
                     WHERE employee_id = $employee_id AND status = 'approved' 
                     AND YEAR(start_date) = YEAR(CURDATE())";
$leave_used_result = $conn->query($leave_used_query);
$leave_used = $leave_used_result ? $leave_used_result->fetch_assoc()['used_days'] : 0;
$leave_balance = 30 - $leave_used; // 30 days total - used days

// Get pending leave requests count
$pending_leaves = $conn->query("SELECT COUNT(*) as count FROM hr_leave_requests WHERE employee_id = $employee_id AND status = 'pending'")->fetch_assoc()['count'];

// Calculate work hours this month
$work_hours_query = "SELECT 
    COUNT(*) as working_days,
    AVG(TIMESTAMPDIFF(HOUR, clock_in, clock_out)) as avg_hours
    FROM hr_attendance 
    WHERE employee_id = $employee_id 
    AND MONTH(date) = MONTH(CURDATE()) 
    AND YEAR(date) = YEAR(CURDATE())
    AND clock_in IS NOT NULL 
    AND clock_out IS NOT NULL";
$work_hours_result = $conn->query($work_hours_query);
$work_hours_data = $work_hours_result ? $work_hours_result->fetch_assoc() : ['working_days' => 0, 'avg_hours' => 8];
?>

<style>
.employee-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    border-radius: 15px;
    color: white;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.employee-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
}

.metric-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    border-left: 4px solid;
    transition: transform 0.2s ease;
}

.metric-card:hover {
    transform: translateY(-3px);
}

.metric-card.success { border-left-color: #28a745; }
.metric-card.info { border-left-color: #17a2b8; }
.metric-card.warning { border-left-color: #ffc107; }
.metric-card.danger { border-left-color: #dc3545; }

.clock-btn {
    border-radius: 50px;
    padding: 1rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
    border: none;
    color: white;
}

.clock-in { background: linear-gradient(45deg, #28a745, #20c997); }
.clock-out { background: linear-gradient(45deg, #dc3545, #fd7e14); }

.clock-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.3);
    color: white;
}

.action-card {
    background: white;
    border-radius: 15px;
    border: 1px solid rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    overflow: hidden;
}

.action-card:hover {
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    transform: translateY(-5px);
}

.action-card .card-header {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    padding: 1rem 1.5rem;
}

.btn-action {
    border-radius: 25px;
    padding: 0.75rem 1.5rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.modal-content {
    border-radius: 15px;
    border: none;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
}

.modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px 15px 0 0;
    border: none;
}

.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.table-hover tbody tr:hover {
    background-color: rgba(102, 126, 234, 0.1);
    transform: translateX(3px);
    transition: all 0.2s ease;
}

.status-badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.profile-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(45deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: white;
    margin: 0 auto;
}
</style>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card employee-card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-2 text-center">
                                <div class="profile-avatar">
                                    <?php echo strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1)); ?>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <h2 class="mb-0">Welcome, <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>!</h2>
                                <p class="mb-0 opacity-75">Employee ID: <?php echo htmlspecialchars($employee['employee_id']); ?></p>
                                <p class="mb-0 opacity-75"><?php echo htmlspecialchars($employee['position'] ?? 'Position not set'); ?> • <?php echo htmlspecialchars($employee['department'] ?? 'Department not set'); ?></p>
                                <small class="opacity-75">Last login: <?php echo date('M d, Y g:i A'); ?></small>
                            </div>
                            <div class="col-md-2 text-center">
                                <div class="d-grid gap-2">
                                    <?php if ($today_attendance && !$today_attendance['clock_out']): ?>
                                        <button class="btn clock-out clock-btn" onclick="clockAction('out')">
                                            <i class="fas fa-clock me-2"></i>Clock Out
                                        </button>
                                        <small class="text-white opacity-75">Clocked in at <?php echo date('g:i A', strtotime($today_attendance['clock_in'])); ?></small>
                                    <?php elseif ($today_attendance && $today_attendance['clock_out']): ?>
                                        <button class="btn btn-secondary clock-btn" disabled>
                                            <i class="fas fa-check me-2"></i>Day Complete
                                        </button>
                                        <small class="text-white opacity-75">
                                            <?php echo date('g:i A', strtotime($today_attendance['clock_in'])); ?> - 
                                            <?php echo date('g:i A', strtotime($today_attendance['clock_out'])); ?>
                                        </small>
                                    <?php else: ?>
                                        <button class="btn clock-in clock-btn" onclick="clockAction('in')">
                                            <i class="fas fa-clock me-2"></i>Clock In
                                        </button>
                                        <small class="text-white opacity-75">Ready to start your day</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Metrics -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="metric-card success">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="fas fa-calendar-check fa-2x text-success"></i>
                        </div>
                        <div>
                            <h4 class="mb-0"><?php echo $leave_balance; ?></h4>
                            <p class="text-muted mb-0">Leave Balance</p>
                            <small class="text-success">Days available</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="metric-card info">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="fas fa-clock fa-2x text-info"></i>
                        </div>
                        <div>
                            <h4 class="mb-0"><?php echo round($work_hours_data['avg_hours'] ?: 8, 1); ?>h</h4>
                            <p class="text-muted mb-0">Avg Daily Hours</p>
                            <small class="text-info">This month</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="metric-card warning">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="fas fa-hourglass-half fa-2x text-warning"></i>
                        </div>
                        <div>
                            <h4 class="mb-0"><?php echo $pending_leaves; ?></h4>
                            <p class="text-muted mb-0">Pending Requests</p>
                            <small class="text-warning">Awaiting approval</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="metric-card <?php echo $work_hours_data['working_days'] >= 20 ? 'success' : 'danger'; ?>">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="fas fa-calendar-day fa-2x text-<?php echo $work_hours_data['working_days'] >= 20 ? 'success' : 'danger'; ?>"></i>
                        </div>
                        <div>
                            <h4 class="mb-0"><?php echo $work_hours_data['working_days']; ?></h4>
                            <p class="text-muted mb-0">Working Days</p>
                            <small class="text-<?php echo $work_hours_data['working_days'] >= 20 ? 'success' : 'danger'; ?>">This month</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Actions -->
        <div class="row g-4 mb-4">
            <!-- Personal Information -->
            <div class="col-lg-6">
                <div class="action-card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-user me-2"></i>Personal Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-6 mb-3">
                                <label class="text-muted small">Email</label>
                                <p class="mb-0"><?php echo htmlspecialchars($employee['email']); ?></p>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <label class="text-muted small">Phone</label>
                                <p class="mb-0"><?php echo htmlspecialchars($employee['phone'] ?? 'Not provided'); ?></p>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <label class="text-muted small">Date of Joining</label>
                                <p class="mb-0"><?php echo $employee['date_of_joining'] ? date('M d, Y', strtotime($employee['date_of_joining'])) : 'Not available'; ?></p>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <label class="text-muted small">Status</label>
                                <p class="mb-0">
                                    <span class="badge <?php echo $employee['status'] == 'active' ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo ucfirst($employee['status']); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                        <div class="text-end">
                            <button class="btn btn-outline-primary btn-action" onclick="editPersonalInfo()">
                                <i class="fas fa-edit me-1"></i>Update Information
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="col-lg-6">
                <div class="action-card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-bolt me-2"></i>Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-3">
                            <button class="btn btn-success btn-action" onclick="requestLeave()">
                                <i class="fas fa-calendar-plus me-2"></i>Request Leave
                            </button>
                            <button class="btn btn-info btn-action" onclick="viewAttendanceHistory()">
                                <i class="fas fa-history me-2"></i>Attendance History
                            </button>
                            <button class="btn btn-primary btn-action" onclick="viewPayslips()">
                                <i class="fas fa-file-invoice-dollar me-2"></i>View Payslips
                            </button>
                            <button class="btn btn-warning btn-action" onclick="changePassword()">
                                <i class="fas fa-key me-2"></i>Change Password
                            </button>
                            <a href="../employee_logout.php" class="btn btn-danger btn-action">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row">
            <div class="col-12">
                <div class="action-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line me-2"></i>Recent Activity & Insights
                        </h5>
                        <div>
                            <button class="btn btn-light btn-sm" onclick="viewLeaveHistory()">
                                <i class="fas fa-calendar-alt me-1"></i>Leave History
                            </button>
                            <button class="btn btn-light btn-sm" onclick="viewReports()">
                                <i class="fas fa-chart-bar me-1"></i>My Reports
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h6 class="text-primary mb-3">This Week's Summary</h6>
                                <div class="row">
                                    <div class="col-md-4 text-center">
                                        <div class="mb-2">
                                            <i class="fas fa-clock fa-2x text-success"></i>
                                        </div>
                                        <h6>40 Hours</h6>
                                        <small class="text-muted">Total Work Hours</small>
                                    </div>
                                    <div class="col-md-4 text-center">
                                        <div class="mb-2">
                                            <i class="fas fa-check-circle fa-2x text-info"></i>
                                        </div>
                                        <h6>100%</h6>
                                        <small class="text-muted">Attendance Rate</small>
                                    </div>
                                    <div class="col-md-4 text-center">
                                        <div class="mb-2">
                                            <i class="fas fa-calendar-check fa-2x text-warning"></i>
                                        </div>
                                        <h6>5 Days</h6>
                                        <small class="text-muted">Present Days</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <h6 class="text-primary mb-3">Upcoming</h6>
                                <div class="list-group list-group-flush">
                                    <div class="list-group-item d-flex justify-content-between align-items-center border-0">
                                        <div>
                                            <h6 class="mb-1">Team Meeting</h6>
                                            <small class="text-muted">Tomorrow, 10:00 AM</small>
                                        </div>
                                        <i class="fas fa-users text-muted"></i>
                                    </div>
                                    <div class="list-group-item d-flex justify-content-between align-items-center border-0">
                                        <div>
                                            <h6 class="mb-1">Performance Review</h6>
                                            <small class="text-muted">Next Week</small>
                                        </div>
                                        <i class="fas fa-star text-muted"></i>
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

<!-- Universal Modal -->
<div class="modal fade" id="universalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="universalModalTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="universalModalBody">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer" id="universalModalFooter">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Global modal reference
let universalModal;

document.addEventListener('DOMContentLoaded', function() {
    universalModal = new bootstrap.Modal(document.getElementById('universalModal'));
});

// Utility function to show modal with content
function showModal(title, content, footerButtons = null) {
    document.getElementById('universalModalTitle').textContent = title;
    document.getElementById('universalModalBody').innerHTML = content;
    
    if (footerButtons) {
        document.getElementById('universalModalFooter').innerHTML = footerButtons;
    } else {
        document.getElementById('universalModalFooter').innerHTML = '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>';
    }
    
    universalModal.show();
}

// Utility function for AJAX requests
function makeAjaxRequest(action, data = {}) {
    const formData = new FormData();
    formData.append('action', action);
    
    for (const key in data) {
        formData.append(key, data[key]);
    }
    
    return fetch(window.location.href, {
        method: 'POST',
        body: formData
    }).then(response => response.json());
}

// Clock In/Out functionality
function clockAction(action) {
    const actionText = action === 'in' ? 'Clock In' : 'Clock Out';
    
    Swal.fire({
        title: `Confirm ${actionText}`,
        text: `Are you sure you want to ${action === 'in' ? 'clock in' : 'clock out'} now?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: `Yes, ${actionText}`,
        confirmButtonColor: action === 'in' ? '#28a745' : '#dc3545'
    }).then((result) => {
        if (result.isConfirmed) {
            makeAjaxRequest('clock_in_out', { clock_action: action })
                .then(data => {
                    if (data.success) {
                        Swal.fire('Success!', data.message, 'success').then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error!', data.message, 'error');
                    }
                });
        }
    });
}

// Edit Personal Information
function editPersonalInfo() {
    const content = `
        <form id="personalInfoForm">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="emergency_contact" class="form-label">Emergency Contact Name</label>
                    <input type="text" class="form-control" id="emergency_contact" name="emergency_contact" value="<?php echo htmlspecialchars($employee['emergency_contact'] ?? ''); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="emergency_phone" class="form-label">Emergency Contact Phone</label>
                    <input type="text" class="form-control" id="emergency_phone" name="emergency_phone" value="<?php echo htmlspecialchars($employee['emergency_phone'] ?? ''); ?>">
                </div>
                <div class="col-md-12 mb-3">
                    <label for="address" class="form-label">Address</label>
                    <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($employee['address'] ?? ''); ?></textarea>
                </div>
            </div>
        </form>
    `;
    
    const footerButtons = `
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="savePersonalInfo()">Update Information</button>
    `;
    
    showModal('Update Personal Information', content, footerButtons);
}

function savePersonalInfo() {
    const form = document.getElementById('personalInfoForm');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    makeAjaxRequest('update_personal_info', data)
        .then(response => {
            if (response.success) {
                universalModal.hide();
                Swal.fire('Success!', response.message, 'success').then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('Error!', response.message, 'error');
            }
        });
}

// Request Leave
function requestLeave() {
    const content = `
        <form id="leaveRequestForm">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="leave_type" class="form-label">Leave Type</label>
                    <select class="form-select" id="leave_type" name="leave_type" required>
                        <option value="">Select Leave Type</option>
                        <option value="annual">Annual Leave</option>
                        <option value="sick">Sick Leave</option>
                        <option value="personal">Personal Leave</option>
                        <option value="emergency">Emergency Leave</option>
                        <option value="maternity">Maternity Leave</option>
                        <option value="paternity">Paternity Leave</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Available Balance</label>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i><?php echo $leave_balance; ?> days remaining
                    </div>
                </div>
                <div class="col-12 mb-3">
                    <label for="reason" class="form-label">Reason</label>
                    <textarea class="form-control" id="reason" name="reason" rows="3" placeholder="Please provide reason for leave request..." required></textarea>
                </div>
            </div>
        </form>
    `;
    
    const footerButtons = `
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" onclick="submitLeaveRequest()">Submit Request</button>
    `;
    
    showModal('Request Leave', content, footerButtons);
    
    // Add date validation
    document.getElementById('start_date').addEventListener('change', function() {
        document.getElementById('end_date').min = this.value;
    });
}

function submitLeaveRequest() {
    const form = document.getElementById('leaveRequestForm');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    // Basic validation
    if (!data.leave_type || !data.start_date || !data.end_date || !data.reason) {
        Swal.fire('Validation Error!', 'Please fill in all required fields.', 'warning');
        return;
    }
    
    makeAjaxRequest('request_leave', data)
        .then(response => {
            if (response.success) {
                universalModal.hide();
                Swal.fire('Success!', response.message, 'success');
            } else {
                Swal.fire('Error!', response.message, 'error');
            }
        });
}

// View Attendance History
function viewAttendanceHistory() {
    showModal('Attendance History', '<div class="text-center"><div class="spinner-border text-primary"></div><p class="mt-2">Loading attendance data...</p></div>');
    
    makeAjaxRequest('get_attendance_history', { limit: 30 })
        .then(data => {
            if (data.success) {
                let tableHTML = `
                    <div class="table-responsive">
                        <table class="table table-hover" id="attendanceTable">
                            <thead class="table-primary">
                                <tr>
                                    <th>Date</th>
                                    <th>Clock In</th>
                                    <th>Clock Out</th>
                                    <th>Hours Worked</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                if (data.data.length > 0) {
                    data.data.forEach(record => {
                        const clockIn = record.clock_in || '--';
                        const clockOut = record.clock_out || '--';
                        let hoursWorked = '--';
                        
                        if (record.clock_in && record.clock_out) {
                            const start = new Date(`2000-01-01 ${record.clock_in}`);
                            const end = new Date(`2000-01-01 ${record.clock_out}`);
                            const hours = (end - start) / (1000 * 60 * 60);
                            hoursWorked = hours.toFixed(1) + 'h';
                        }
                        
                        const statusClass = record.status === 'present' ? 'success' : record.status === 'late' ? 'warning' : 'danger';
                        
                        tableHTML += `
                            <tr>
                                <td><strong>${new Date(record.date).toLocaleDateString()}</strong></td>
                                <td>${clockIn}</td>
                                <td>${clockOut}</td>
                                <td>${hoursWorked}</td>
                                <td><span class="badge bg-${statusClass}">${record.status}</span></td>
                            </tr>
                        `;
                    });
                } else {
                    tableHTML += `
                        <tr><td colspan="5" class="text-center py-4">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No attendance records found</p>
                        </td></tr>
                    `;
                }
                
                tableHTML += '</tbody></table></div>';
                showModal('Attendance History - Last 30 Days', tableHTML);
                
                // Initialize DataTable if data exists
                if (data.data.length > 0) {
                    setTimeout(() => {
                        $('#attendanceTable').DataTable({
                            pageLength: 15,
                            order: [[0, 'desc']],
                            responsive: true
                        });
                    }, 100);
                }
            }
        });
}

// View Payslips
function viewPayslips() {
    showModal('Payslips', '<div class="text-center"><div class="spinner-border text-primary"></div><p class="mt-2">Loading payslip data...</p></div>');
    
    makeAjaxRequest('get_payslips')
        .then(data => {
            if (data.success) {
                let content = `
                    <div class="row">
                        <div class="col-12">
                            <h6 class="text-primary mb-3">Recent Payslips</h6>
                            <div class="row">
                `;
                
                if (data.data.length > 0) {
                    data.data.forEach(payslip => {
                        content += `
                            <div class="col-md-4 mb-3">
                                <div class="card border-primary">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0">${payslip.pay_period}</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-muted">Gross Salary</small>
                                                <p class="mb-1"><strong>$${parseFloat(payslip.gross_salary).toLocaleString()}</strong></p>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Deductions</small>
                                                <p class="mb-1"><strong>$${parseFloat(payslip.deductions).toLocaleString()}</strong></p>
                                            </div>
                                        </div>
                                        <hr>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <small class="text-muted">Net Salary</small>
                                                <h5 class="text-success mb-0">$${parseFloat(payslip.net_salary).toLocaleString()}</h5>
                                            </div>
                                            <button class="btn btn-sm btn-outline-primary" onclick="downloadPayslip(${payslip.id})">
                                                <i class="fas fa-download"></i> PDF
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                } else {
                    content += `
                        <div class="col-12 text-center py-4">
                            <i class="fas fa-file-invoice-dollar fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No payslips available</p>
                        </div>
                    `;
                }
                
                content += `
                            </div>
                        </div>
                    </div>
                `;
                
                showModal('Payslips & Salary Information', content);
            }
        });
}

function downloadPayslip(payslipId) {
    Swal.fire('Success!', 'Payslip download will begin shortly', 'success');
}

// View Leave History
function viewLeaveHistory() {
    showModal('Leave History', '<div class="text-center"><div class="spinner-border text-primary"></div><p class="mt-2">Loading leave history...</p></div>');
    
    makeAjaxRequest('get_leave_history')
        .then(data => {
            if (data.success) {
                let tableHTML = `
                    <div class="table-responsive">
                        <table class="table table-hover" id="leaveTable">
                            <thead class="table-primary">
                                <tr>
                                    <th>Leave Type</th>
                                    <th>Period</th>
                                    <th>Days</th>
                                    <th>Applied Date</th>
                                    <th>Status</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                if (data.data.length > 0) {
                    data.data.forEach(leave => {
                        const statusClass = leave.status === 'approved' ? 'success' : leave.status === 'rejected' ? 'danger' : 'warning';
                        
                        tableHTML += `
                            <tr>
                                <td><span class="badge bg-secondary">${leave.leave_type}</span></td>
                                <td>${leave.start_date} to ${leave.end_date}</td>
                                <td><strong>${leave.days}</strong></td>
                                <td>${new Date(leave.applied_date).toLocaleDateString()}</td>
                                <td><span class="badge bg-${statusClass}">${leave.status}</span></td>
                                <td>${leave.reason}</td>
                            </tr>
                        `;
                    });
                } else {
                    tableHTML += `
                        <tr><td colspan="6" class="text-center py-4">
                            <i class="fas fa-calendar-plus fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No leave requests found</p>
                        </td></tr>
                    `;
                }
                
                tableHTML += '</tbody></table></div>';
                showModal('Leave Request History', tableHTML);
                
                // Initialize DataTable if data exists
                if (data.data.length > 0) {
                    setTimeout(() => {
                        $('#leaveTable').DataTable({
                            pageLength: 10,
                            order: [[3, 'desc']],
                            responsive: true
                        });
                    }, 100);
                }
            }
        });
}

// Change Password
function changePassword() {
    const content = `
        <form id="passwordForm">
            <div class="mb-3">
                <label for="current_password" class="form-label">Current Password</label>
                <input type="password" class="form-control" id="current_password" name="current_password" required>
            </div>
            <div class="mb-3">
                <label for="new_password" class="form-label">New Password</label>
                <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                <small class="form-text text-muted">Password must be at least 6 characters long</small>
            </div>
            <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirm New Password</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
            </div>
        </form>
    `;
    
    const footerButtons = `
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-warning" onclick="updatePassword()">Change Password</button>
    `;
    
    showModal('Change Password', content, footerButtons);
}

function updatePassword() {
    const form = document.getElementById('passwordForm');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    if (data.new_password !== data.confirm_password) {
        Swal.fire('Error!', 'New passwords do not match', 'error');
        return;
    }
    
    if (data.new_password.length < 6) {
        Swal.fire('Error!', 'Password must be at least 6 characters long', 'error');
        return;
    }
    
    makeAjaxRequest('update_password', data)
        .then(response => {
            if (response.success) {
                universalModal.hide();
                Swal.fire('Success!', response.message, 'success');
            } else {
                Swal.fire('Error!', response.message, 'error');
            }
        });
}

// View Reports
function viewReports() {
    const content = `
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-chart-line fa-2x text-primary mb-3"></i>
                        <h6>Monthly Report</h6>
                        <p class="text-muted small">Attendance and performance summary</p>
                        <button class="btn btn-outline-primary btn-sm" onclick="generateReport('monthly')">Generate</button>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-calendar-check fa-2x text-success mb-3"></i>
                        <h6>Leave Summary</h6>
                        <p class="text-muted small">Leave balance and usage report</p>
                        <button class="btn btn-outline-success btn-sm" onclick="generateReport('leave')">Generate</button>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-file-invoice-dollar fa-2x text-warning mb-3"></i>
                        <h6>Salary History</h6>
                        <p class="text-muted small">Payroll and earnings report</p>
                        <button class="btn btn-outline-warning btn-sm" onclick="generateReport('salary')">Generate</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    showModal('Generate Reports', content);
}

function generateReport(type) {
    Swal.fire('Report Generated!', `Your ${type} report will be available for download shortly.`, 'success');
    universalModal.hide();
}

// Initialize tooltips and popovers
document.addEventListener('DOMContentLoaded', function() {
    // Initialize any tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php include '../layouts/footer.php'; ?>
