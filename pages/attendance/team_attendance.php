<?php
session_start();
require_once '../../db.php';
require_once '../../auth_check.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'employee';

// Get current user's department (for team context)
$emp_query = "SELECT department_id FROM employees WHERE user_id = ?";
$emp_stmt = $conn->prepare($emp_query);
$emp_stmt->bind_param("i", $user_id);
$emp_stmt->execute();
$current_employee = $emp_stmt->get_result()->fetch_assoc();

if (!$current_employee) {
    echo "Employee record not found.";
    exit();
}

$department_id = $current_employee['department_id'];

// Get team members from the same department
$team_query = "SELECT e.*, d.name as department_name
               FROM employees e
               LEFT JOIN departments d ON e.department_id = d.id
               WHERE e.department_id = ?
               ORDER BY e.first_name, e.last_name";

$team_stmt = $conn->prepare($team_query);
$team_stmt->bind_param("i", $department_id);
$team_stmt->execute();
$team_members = $team_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get today's attendance for team
$today = date('Y-m-d');
$attendance_query = "SELECT a.*, e.first_name, e.last_name, e.id as employee_id
                     FROM attendance a
                     JOIN employees e ON a.employee_id = e.id
                     WHERE e.department_id = ? AND DATE(a.check_in) = ?
                     ORDER BY a.check_in DESC";

$att_stmt = $conn->prepare($attendance_query);
$att_stmt->bind_param("is", $department_id, $today);
$att_stmt->execute();
$today_attendance = $att_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get weekly attendance summary
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));

$weekly_query = "SELECT e.id, e.first_name, e.last_name,
                 COUNT(DISTINCT DATE(a.check_in)) as days_present,
                 COALESCE(SUM(TIMESTAMPDIFF(MINUTE, a.check_in, a.check_out)), 0) as total_minutes
                 FROM employees e
                 LEFT JOIN attendance a ON e.id = a.employee_id 
                 AND DATE(a.check_in) BETWEEN ? AND ?
                 WHERE e.department_id = ?
                 GROUP BY e.id
                 ORDER BY e.first_name, e.last_name";

$weekly_stmt = $conn->prepare($weekly_query);
$weekly_stmt->bind_param("ssi", $week_start, $week_end, $department_id);
$weekly_stmt->execute();
$weekly_attendance = $weekly_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Group today's attendance by employee
$today_by_employee = [];
foreach ($today_attendance as $record) {
    $emp_id = $record['employee_id'];
    if (!isset($today_by_employee[$emp_id])) {
        $today_by_employee[$emp_id] = [];
    }
    $today_by_employee[$emp_id][] = $record;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Attendance - HR System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .team-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 2rem 0;
        }
        .status-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: transform 0.2s;
        }
        .status-card:hover {
            transform: translateY(-2px);
        }
        .member-card {
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        .member-card.present {
            border-left-color: #28a745;
            background-color: #f8fff9;
        }
        .member-card.absent {
            border-left-color: #dc3545;
            background-color: #fff8f8;
        }
        .member-card.late {
            border-left-color: #ffc107;
            background-color: #fffdf5;
        }
        .attendance-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="../../dashboard.php">
                <i class="fas fa-users"></i> HR System
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../manager/manager_dashboard.php">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <a class="nav-link" href="../../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Header -->
    <div class="team-header text-center">
        <div class="container">
            <h1><i class="fas fa-users"></i> Team Attendance</h1>
            <p class="lead">Today: <?php echo date('l, F j, Y'); ?></p>
        </div>
    </div>

    <div class="container mt-4">
        <!-- Quick Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="status-card text-center">
                    <h5 class="text-primary">
                        <i class="fas fa-users"></i> Team Size
                    </h5>
                    <h2 class="mb-0"><?php echo count($team_members); ?></h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="status-card text-center">
                    <h5 class="text-success">
                        <i class="fas fa-check-circle"></i> Present Today
                    </h5>
                    <h2 class="mb-0"><?php echo count($today_by_employee); ?></h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="status-card text-center">
                    <h5 class="text-danger">
                        <i class="fas fa-times-circle"></i> Absent Today
                    </h5>
                    <h2 class="mb-0"><?php echo count($team_members) - count($today_by_employee); ?></h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="status-card text-center">
                    <h5 class="text-info">
                        <i class="fas fa-percentage"></i> Attendance Rate
                    </h5>
                    <h2 class="mb-0">
                        <?php 
                        $rate = count($team_members) > 0 ? (count($today_by_employee) / count($team_members)) * 100 : 0;
                        echo number_format($rate, 1); 
                        ?>%
                    </h2>
                </div>
            </div>
        </div>

        <!-- Today's Attendance -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-clock"></i> Today's Attendance Status</h5>
                <button class="btn btn-sm btn-primary" onclick="refreshAttendance()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($team_members as $member): 
                        $member_attendance = $today_by_employee[$member['id']] ?? [];
                        $is_present = !empty($member_attendance);
                        $check_in_time = null;
                        $check_out_time = null;
                        $is_late = false;
                        
                        if ($is_present) {
                            $first_entry = $member_attendance[0];
                            $check_in_time = $first_entry['check_in'];
                            $check_out_time = $first_entry['check_out'];
                            
                            // Consider late if check-in is after 9:00 AM
                            $check_in_hour = date('H', strtotime($check_in_time));
                            $is_late = $check_in_hour >= 9;
                        }
                        
                        $status_class = $is_present ? ($is_late ? 'late' : 'present') : 'absent';
                    ?>
                    <div class="col-md-6 mb-3">
                        <div class="card member-card <?php echo $status_class; ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="card-title mb-1">
                                            <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                        </h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($member['position'] ?? 'Employee'); ?></small>
                                    </div>
                                    <div class="text-end">
                                        <?php if ($is_present): ?>
                                            <span class="badge <?php echo $is_late ? 'bg-warning' : 'bg-success'; ?> attendance-badge">
                                                <?php echo $is_late ? 'Late' : 'Present'; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger attendance-badge">Absent</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if ($is_present): ?>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <i class="fas fa-sign-in-alt text-success"></i> 
                                        Check-in: <?php echo date('H:i', strtotime($check_in_time)); ?>
                                    </small>
                                    <?php if ($check_out_time): ?>
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-sign-out-alt text-danger"></i> 
                                        Check-out: <?php echo date('H:i', strtotime($check_out_time)); ?>
                                    </small>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Weekly Summary -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-chart-bar"></i> This Week's Summary (<?php echo date('M j', strtotime($week_start)) . ' - ' . date('M j', strtotime($week_end)); ?>)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Days Present</th>
                                <th>Total Hours</th>
                                <th>Average Hours/Day</th>
                                <th>Attendance Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($weekly_attendance as $weekly): 
                                $working_days = 5; // Assume 5 working days per week
                                $attendance_rate = ($weekly['days_present'] / $working_days) * 100;
                                $total_hours = $weekly['total_minutes'] / 60;
                                $avg_hours = $weekly['days_present'] > 0 ? $total_hours / $weekly['days_present'] : 0;
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($weekly['first_name'] . ' ' . $weekly['last_name']); ?></strong>
                                </td>
                                <td><?php echo $weekly['days_present']; ?>/<?php echo $working_days; ?></td>
                                <td><?php echo number_format($total_hours, 1); ?> hrs</td>
                                <td><?php echo number_format($avg_hours, 1); ?> hrs</td>
                                <td>
                                    <span class="badge <?php echo $attendance_rate >= 90 ? 'bg-success' : ($attendance_rate >= 75 ? 'bg-warning' : 'bg-danger'); ?>">
                                        <?php echo number_format($attendance_rate, 1); ?>%
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="row">
            <div class="col-md-12 text-center">
                <a href="attendance_report.php" class="btn btn-primary me-2">
                    <i class="fas fa-chart-line"></i> Detailed Report
                </a>
                <button class="btn btn-success me-2" onclick="exportTeamData()">
                    <i class="fas fa-download"></i> Export Data
                </button>
                <button class="btn btn-info me-2" onclick="sendAttendanceReminder()">
                    <i class="fas fa-bell"></i> Send Reminder
                </button>
                <a href="../manager/manager_dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function refreshAttendance() {
            location.reload();
        }

        function exportTeamData() {
            // Create CSV content
            let csvContent = "Employee,Days Present,Total Hours,Average Hours per Day,Attendance Rate\n";
            
            // Get table data
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                const employee = cells[0].textContent.trim();
                const daysPresent = cells[1].textContent.trim();
                const totalHours = cells[2].textContent.trim();
                const avgHours = cells[3].textContent.trim();
                const attendanceRate = cells[4].textContent.trim();
                
                csvContent += `"${employee}","${daysPresent}","${totalHours}","${avgHours}","${attendanceRate}"\n`;
            });
            
            // Download CSV
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'team_attendance_<?php echo date('Y-m-d'); ?>.csv';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        }

        function sendAttendanceReminder() {
            // Mock function - in real implementation, this would send notifications
            alert('Attendance reminder sent to team members!');
        }
    </script>
</body>
</html>
