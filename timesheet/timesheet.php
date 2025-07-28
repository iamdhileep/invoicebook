<?php
session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'] ?? $_SESSION['admin'] ?? 1;
$user_role = $_SESSION['role'] ?? 'employee';

// Get current month and year
$current_month = $_GET['month'] ?? date('m');
$current_year = $_GET['year'] ?? date('Y');

// Get employee info - try both user_id and direct employee ID
$employee_query = "SELECT * FROM employees WHERE user_id = ? OR id = ? LIMIT 1";
$employee_stmt = $conn->prepare($employee_query);
$employee_stmt->bind_param("ii", $user_id, $user_id);
$employee_stmt->execute();
$employee = $employee_stmt->get_result()->fetch_assoc();

if (!$employee) {
    // Create a default employee record for testing
    $employee = [
        'id' => 1,
        'first_name' => 'Test',
        'last_name' => 'Employee',
        'department' => 'General'
    ];
}

// Get attendance records for the month
$attendance_query = "SELECT attendance_date as date, 
                            punch_in_time as first_check_in,
                            punch_out_time as last_check_out,
                            COUNT(*) as entries,
                            CASE 
                                WHEN punch_out_time IS NOT NULL THEN 
                                    TIMESTAMPDIFF(MINUTE, 
                                        CONCAT(attendance_date, ' ', punch_in_time), 
                                        CONCAT(attendance_date, ' ', punch_out_time)
                                    )
                                ELSE 0
                            END as total_minutes
                     FROM attendance 
                     WHERE employee_id = ? 
                     AND MONTH(attendance_date) = ? 
                     AND YEAR(attendance_date) = ?
                     AND punch_in_time IS NOT NULL
                     GROUP BY attendance_date
                     ORDER BY attendance_date";

$attendance_stmt = $conn->prepare($attendance_query);
$attendance_stmt->bind_param("iii", $employee['id'], $current_month, $current_year);
$attendance_stmt->execute();
$attendance_records = $attendance_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate monthly totals
$total_days_worked = count($attendance_records);
$total_hours = 0;
foreach ($attendance_records as $record) {
    $total_hours += $record['total_minutes'] / 60;
}

// Get number of days in month
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $current_month, $current_year);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timesheet - HR System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .timesheet-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
        }
        .attendance-day {
            border: 1px solid #ddd;
            padding: 10px;
            margin: 2px;
            border-radius: 5px;
            background: #f8f9fa;
        }
        .attendance-day.present {
            background: #d4edda;
            border-color: #c3e6cb;
        }
        .attendance-day.absent {
            background: #f8d7da;
            border-color: #f5c6cb;
        }
        .time-entry {
            font-size: 0.875rem;
            margin: 2px 0;
        }
        .summary-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="../dashboard.php">
                <i class="fas fa-clock"></i> HR System
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../employee_portal.php">
                    <i class="fas fa-arrow-left"></i> Back to Portal
                </a>
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Header -->
    <div class="timesheet-header text-center">
        <div class="container">
            <h1><i class="fas fa-calendar-alt"></i> Timesheet</h1>
            <p class="lead">Employee: <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></p>
            <p>Department: <?php echo htmlspecialchars($employee['department'] ?? 'Not Assigned'); ?></p>
        </div>
    </div>

    <div class="container mt-4">
        <!-- Month Navigation -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h3>Timesheet for <?php echo date('F Y', mktime(0, 0, 0, $current_month, 1, $current_year)); ?></h3>
            </div>
            <div class="col-md-6 text-end">
                <div class="btn-group">
                    <a href="?month=<?php echo ($current_month > 1) ? $current_month - 1 : 12; ?>&year=<?php echo ($current_month > 1) ? $current_year : $current_year - 1; ?>" 
                       class="btn btn-outline-primary">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                    <a href="?month=<?php echo date('m'); ?>&year=<?php echo date('Y'); ?>" 
                       class="btn btn-primary">Current Month</a>
                    <a href="?month=<?php echo ($current_month < 12) ? $current_month + 1 : 1; ?>&year=<?php echo ($current_month < 12) ? $current_year : $current_year + 1; ?>" 
                       class="btn btn-outline-primary">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="summary-card text-center">
                    <h5 class="text-primary">
                        <i class="fas fa-calendar-check"></i> Days Worked
                    </h5>
                    <h2 class="mb-0"><?php echo $total_days_worked; ?></h2>
                    <small class="text-muted">out of <?php echo $days_in_month; ?> days</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card text-center">
                    <h5 class="text-success">
                        <i class="fas fa-clock"></i> Total Hours
                    </h5>
                    <h2 class="mb-0"><?php echo number_format($total_hours, 1); ?></h2>
                    <small class="text-muted">hours worked</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card text-center">
                    <h5 class="text-info">
                        <i class="fas fa-chart-line"></i> Average Hours
                    </h5>
                    <h2 class="mb-0"><?php echo $total_days_worked > 0 ? number_format($total_hours / $total_days_worked, 1) : '0'; ?></h2>
                    <small class="text-muted">per day</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card text-center">
                    <h5 class="text-warning">
                        <i class="fas fa-percentage"></i> Attendance
                    </h5>
                    <h2 class="mb-0"><?php echo number_format(($total_days_worked / $days_in_month) * 100, 1); ?>%</h2>
                    <small class="text-muted">attendance rate</small>
                </div>
            </div>
        </div>

        <!-- Timesheet Calendar -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-table"></i> Daily Attendance Details</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th>Date</th>
                                <th>Day</th>
                                <th>Check In</th>
                                <th>Check Out</th>
                                <th>Total Hours</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Create an array of attendance data indexed by date
                            $attendance_by_date = [];
                            foreach ($attendance_records as $record) {
                                $attendance_by_date[$record['date']] = $record;
                            }

                            // Generate calendar for the month
                            for ($day = 1; $day <= $days_in_month; $day++) {
                                $date = sprintf('%04d-%02d-%02d', $current_year, $current_month, $day);
                                $day_name = date('l', strtotime($date));
                                $is_weekend = in_array($day_name, ['Saturday', 'Sunday']);
                                
                                $attendance = $attendance_by_date[$date] ?? null;
                                
                                echo "<tr class='" . ($attendance ? 'table-success' : ($is_weekend ? 'table-secondary' : 'table-danger')) . "'>";
                                echo "<td><strong>" . date('j', strtotime($date)) . "</strong></td>";
                                echo "<td>" . date('D', strtotime($date)) . "</td>";
                                
                                if ($attendance) {
                                    echo "<td>" . ($attendance['first_check_in'] ? $attendance['first_check_in'] : 'N/A') . "</td>";
                                    echo "<td>" . ($attendance['last_check_out'] ? $attendance['last_check_out'] : 'N/A') . "</td>";
                                    echo "<td>" . number_format($attendance['total_minutes'] / 60, 1) . " hrs</td>";
                                    echo "<td><span class='badge bg-success'>Present</span></td>";
                                } else {
                                    echo "<td>-</td>";
                                    echo "<td>-</td>";
                                    echo "<td>-</td>";
                                    if ($is_weekend) {
                                        echo "<td><span class='badge bg-secondary'>Weekend</span></td>";
                                    } else if (strtotime($date) > time()) {
                                        echo "<td><span class='badge bg-light text-dark'>Future</span></td>";
                                    } else {
                                        echo "<td><span class='badge bg-danger'>Absent</span></td>";
                                    }
                                }
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="row mt-4">
            <div class="col-md-12 text-center">
                <button class="btn btn-primary me-2" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Timesheet
                </button>
                <button class="btn btn-success me-2" onclick="exportToCSV()">
                    <i class="fas fa-download"></i> Export CSV
                </button>
                <a href="../employee_portal.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Portal
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportToCSV() {
            // Create CSV content
            let csvContent = "Date,Day,Check In,Check Out,Total Hours,Status\n";
            
            // Get table data
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                const rowData = Array.from(cells).map(cell => cell.textContent.trim()).join(',');
                csvContent += rowData + '\n';
            });
            
            // Download CSV
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'timesheet_<?php echo date('Y-m', mktime(0, 0, 0, $current_month, 1, $current_year)); ?>.csv';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        }
    </script>
</body>
</html>
