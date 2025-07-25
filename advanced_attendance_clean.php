<?php
session_start();
// Check for either session variable for compatibility
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include 'db.php';

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Handle AJAX requests for punch operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
        exit;
    }
    
    $action = $input['action'] ?? '';
    $employee_id = intval($input['employee_id'] ?? 0);
    $current_time = date('Y-m-d H:i:s');
    $attendance_date = $input['attendance_date'] ?? date('Y-m-d');
    
    if ($employee_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
        exit;
    }
    
    try {
        if ($action === 'punch_in') {
            // Check if already punched in
            $check = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?");
            $check->bind_param('is', $employee_id, $attendance_date);
            $check->execute();
            $existing = $check->get_result()->fetch_assoc();
            
            if ($existing && $existing['time_in']) {
                echo json_encode(['success' => false, 'message' => 'Already punched in today']);
                exit;
            }
            
            // Insert or update attendance record
            if ($existing) {
                $stmt = $conn->prepare("UPDATE attendance SET time_in = ?, status = 'Present' WHERE employee_id = ? AND attendance_date = ?");
                $stmt->bind_param('sis', $current_time, $employee_id, $attendance_date);
            } else {
                $stmt = $conn->prepare("INSERT INTO attendance (employee_id, attendance_date, time_in, status) VALUES (?, ?, ?, 'Present')");
                $stmt->bind_param('iss', $employee_id, $attendance_date, $current_time);
            }
            
            if ($stmt->execute()) {
                $time_display = date('h:i A', strtotime($current_time));
                echo json_encode([
                    'success' => true, 
                    'message' => 'Punch In successful',
                    'time' => $time_display,
                    'current_time' => $current_time
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            }
            
        } elseif ($action === 'punch_out') {
            // Check if punched in first
            $check = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?");
            $check->bind_param('is', $employee_id, $attendance_date);
            $check->execute();
            $existing = $check->get_result()->fetch_assoc();
            
            if (!$existing || !$existing['time_in']) {
                echo json_encode(['success' => false, 'message' => 'Not punched in yet']);
                exit;
            }
            
            if ($existing['time_out']) {
                echo json_encode(['success' => false, 'message' => 'Already punched out']);
                exit;
            }
            
            $stmt = $conn->prepare("UPDATE attendance SET time_out = ? WHERE employee_id = ? AND attendance_date = ?");
            $stmt->bind_param('sis', $current_time, $employee_id, $attendance_date);
            
            if ($stmt->execute()) {
                $time_display = date('h:i A', strtotime($current_time));
                echo json_encode([
                    'success' => true, 
                    'message' => 'Punch Out successful',
                    'time' => $time_display,
                    'current_time' => $current_time
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            }
            
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Get current date
$current_date = $_GET['date'] ?? date('Y-m-d');

// Get all employees with their attendance for the selected date
$query = "
    SELECT 
        e.employee_id,
        e.name,
        e.employee_code,
        e.position,
        e.phone,
        e.photo,
        a.status,
        a.time_in,
        a.time_out,
        a.attendance_date,
        CASE 
            WHEN a.time_in IS NOT NULL AND a.time_out IS NOT NULL THEN 'punched_out'
            WHEN a.time_in IS NOT NULL AND a.time_out IS NULL THEN 'punched_in'
            ELSE 'not_punched'
        END as punch_status
    FROM employees e
    LEFT JOIN attendance a ON e.employee_id = a.employee_id AND a.attendance_date = ?
    ORDER BY e.name ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param('s', $current_date);
$stmt->execute();
$result = $stmt->get_result();
$employees = $result->fetch_all(MYSQLI_ASSOC);

// Get attendance statistics
$stats_query = "
    SELECT 
        COUNT(DISTINCT e.employee_id) as total_employees,
        COUNT(CASE WHEN a.status = 'Present' THEN 1 END) as present_count,
        COUNT(CASE WHEN a.time_in IS NOT NULL AND a.time_out IS NULL THEN 1 END) as currently_in
    FROM employees e
    LEFT JOIN attendance a ON e.employee_id = a.employee_id AND a.attendance_date = ?
";

$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param('s', $current_date);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Attendance - BillBook</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .live-clock {
            font-weight: 600;
            font-family: 'Courier New', monospace;
        }
        .punch-status {
            font-size: 0.75rem;
        }
        .time-display {
            font-weight: 600;
            color: #2c5aa0;
        }
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .btn-group-sm > .btn, .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <?php include 'layouts/header.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-clock-history"></i> Advanced Attendance Management
                        </h5>
                        <div class="d-flex align-items-center gap-3">
                            <div class="live-clock text-primary" id="liveClock"></div>
                            <input type="date" class="form-control" id="attendanceDate" 
                                   value="<?= $current_date ?>" onchange="changeDate()">
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <!-- Statistics Row -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card bg-primary text-white">
                                    <div class="card-body text-center">
                                        <h3><?= $stats['total_employees'] ?></h3>
                                        <small>Total Employees</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h3><?= $stats['present_count'] ?></h3>
                                        <small>Present Today</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-warning text-white">
                                    <div class="card-body text-center">
                                        <h3><?= $stats['currently_in'] ?></h3>
                                        <small>Currently In</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body text-center">
                                        <h3><?= $stats['total_employees'] - $stats['present_count'] ?></h3>
                                        <small>Not Punched</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Employee Table -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="attendanceTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Employee</th>
                                        <th>Code</th>
                                        <th>Position</th>
                                        <th>Status</th>
                                        <th>Time In</th>
                                        <th>Time Out</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($employees as $emp): ?>
                                        <tr id="employee-row-<?= $emp['employee_id'] ?>">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if ($emp['photo']): ?>
                                                        <img src="uploads/<?= htmlspecialchars($emp['photo']) ?>" 
                                                             class="rounded-circle me-2" width="32" height="32">
                                                    <?php else: ?>
                                                        <div class="bg-secondary rounded-circle me-2 d-flex align-items-center justify-content-center" 
                                                             style="width: 32px; height: 32px;">
                                                            <i class="bi bi-person text-white"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <strong><?= htmlspecialchars($emp['name']) ?></strong>
                                                        <?php if ($emp['phone']): ?>
                                                            <br><small class="text-muted"><?= htmlspecialchars($emp['phone']) ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($emp['employee_code'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($emp['position'] ?? 'N/A') ?></td>
                                            <td>
                                                <?php 
                                                $status_class = '';
                                                $status_text = $emp['status'] ?? 'Not Punched';
                                                switch ($status_text) {
                                                    case 'Present': $status_class = 'bg-success'; break;
                                                    case 'Late': $status_class = 'bg-warning'; break;
                                                    case 'Absent': $status_class = 'bg-danger'; break;
                                                    default: $status_class = 'bg-secondary';
                                                }
                                                ?>
                                                <span class="badge <?= $status_class ?>" id="status-badge-<?= $emp['employee_id'] ?>">
                                                    <?= $status_text ?>
                                                </span>
                                                <br>
                                                <div class="punch-status" id="punch-status-<?= $emp['employee_id'] ?>">
                                                    <?php
                                                    switch ($emp['punch_status']) {
                                                        case 'punched_in':
                                                            echo '<span class="text-success">Punched In</span>';
                                                            break;
                                                        case 'punched_out':
                                                            echo '<span class="text-info">Punched Out</span>';
                                                            break;
                                                        default:
                                                            echo '<span class="text-muted">Not Punched</span>';
                                                    }
                                                    ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="time-display" id="time-in-display-<?= $emp['employee_id'] ?>">
                                                    <?= $emp['time_in'] ? date('h:i A', strtotime($emp['time_in'])) : '-' ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="time-display" id="time-out-display-<?= $emp['employee_id'] ?>">
                                                    <?= $emp['time_out'] ? date('h:i A', strtotime($emp['time_out'])) : '-' ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <button type="button" 
                                                            class="btn btn-success punch-in-btn" 
                                                            id="punch-in-btn-<?= $emp['employee_id'] ?>"
                                                            onclick="punchIn(<?= $emp['employee_id'] ?>)"
                                                            data-bs-toggle="tooltip" title="Punch In"
                                                            <?= ($emp['punch_status'] === 'punched_in' || $emp['punch_status'] === 'punched_out') ? 'disabled' : '' ?>>
                                                        <i class="bi bi-box-arrow-in-right"></i>
                                                    </button>
                                                    <button type="button" 
                                                            class="btn btn-danger punch-out-btn" 
                                                            id="punch-out-btn-<?= $emp['employee_id'] ?>"
                                                            onclick="punchOut(<?= $emp['employee_id'] ?>)"
                                                            data-bs-toggle="tooltip" title="Punch Out"
                                                            <?= ($emp['punch_status'] === 'not_punched' || $emp['punch_status'] === 'punched_out') ? 'disabled' : '' ?>>
                                                        <i class="bi bi-box-arrow-right"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Container -->
    <div id="alertContainer" class="position-fixed top-0 end-0 p-3" style="z-index: 1050;"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        const currentDate = '<?= $current_date ?>';
        
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

        // Live clock
        function updateLiveClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour12: true,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit' 
            });
            document.getElementById('liveClock').textContent = timeString;
        }
        setInterval(updateLiveClock, 1000);
        updateLiveClock(); // Initialize immediately

        // Show alert function
        function showAlert(message, type = 'info') {
            const alertContainer = document.getElementById('alertContainer');
            const alertId = 'alert-' + Date.now();
            
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" id="${alertId}" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            alertContainer.insertAdjacentHTML('beforeend', alertHtml);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                const alertElement = document.getElementById(alertId);
                if (alertElement) {
                    const bsAlert = new bootstrap.Alert(alertElement);
                    bsAlert.close();
                }
            }, 5000);
        }

        // Punch In function
        async function punchIn(employeeId) {
            if (!employeeId || employeeId <= 0) {
                showAlert('Error: Invalid Employee ID', 'danger');
                return;
            }
            
            const employeeRow = document.getElementById(`employee-row-${employeeId}`);
            const employeeName = employeeRow ? employeeRow.querySelector('strong').textContent : 'Employee';
            
            if (!confirm(`Confirm Punch In for ${employeeName}?`)) {
                return;
            }
            
            const button = document.getElementById(`punch-in-btn-${employeeId}`);
            if (!button) {
                showAlert('Error: Punch In button not found', 'danger');
                return;
            }
            
            const originalContent = button.innerHTML;
            button.innerHTML = '<i class="bi bi-hourglass-split"></i>';
            button.disabled = true;
            
            try {
                const response = await fetch('advanced_attendance_clean.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'punch_in',
                        employee_id: parseInt(employeeId),
                        attendance_date: currentDate
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Update UI
                    document.getElementById(`time-in-display-${employeeId}`).textContent = result.time;
                    document.getElementById(`status-badge-${employeeId}`).textContent = 'Present';
                    document.getElementById(`status-badge-${employeeId}`).className = 'badge bg-success';
                    document.getElementById(`punch-status-${employeeId}`).innerHTML = '<span class="text-success">Punched In</span>';
                    
                    // Enable punch out button
                    const punchOutButton = document.getElementById(`punch-out-btn-${employeeId}`);
                    if (punchOutButton) {
                        punchOutButton.disabled = false;
                    }
                    
                    button.innerHTML = '<i class="bi bi-box-arrow-in-right"></i>';
                    // Keep punch in button disabled
                    
                    showAlert(`✅ ${result.message}`, 'success');
                } else {
                    button.innerHTML = originalContent;
                    button.disabled = false;
                    showAlert(`❌ Punch In Failed: ${result.message}`, 'danger');
                }
            } catch (error) {
                button.innerHTML = originalContent;
                button.disabled = false;
                showAlert('Error: ' + error.message, 'danger');
                console.error('Punch In Error:', error);
            }
        }

        // Punch Out function
        async function punchOut(employeeId) {
            if (!employeeId || employeeId <= 0) {
                showAlert('Error: Invalid Employee ID', 'danger');
                return;
            }
            
            const employeeRow = document.getElementById(`employee-row-${employeeId}`);
            const employeeName = employeeRow ? employeeRow.querySelector('strong').textContent : 'Employee';
            
            if (!confirm(`Confirm Punch Out for ${employeeName}?`)) {
                return;
            }
            
            const button = document.getElementById(`punch-out-btn-${employeeId}`);
            if (!button) {
                showAlert('Error: Punch Out button not found', 'danger');
                return;
            }
            
            const originalContent = button.innerHTML;
            button.innerHTML = '<i class="bi bi-hourglass-split"></i>';
            button.disabled = true;
            
            try {
                const response = await fetch('advanced_attendance_clean.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'punch_out',
                        employee_id: parseInt(employeeId),
                        attendance_date: currentDate
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Update UI
                    document.getElementById(`time-out-display-${employeeId}`).textContent = result.time;
                    document.getElementById(`punch-status-${employeeId}`).innerHTML = '<span class="text-info">Punched Out</span>';
                    
                    button.innerHTML = '<i class="bi bi-box-arrow-right"></i>';
                    // Keep punch out button disabled
                    
                    showAlert(`✅ ${result.message}`, 'success');
                } else {
                    button.innerHTML = originalContent;
                    button.disabled = false;
                    showAlert(`❌ Punch Out Failed: ${result.message}`, 'danger');
                }
            } catch (error) {
                button.innerHTML = originalContent;
                button.disabled = false;
                showAlert('Error: ' + error.message, 'danger');
                console.error('Punch Out Error:', error);
            }
        }

        // Change date function
        function changeDate() {
            const selectedDate = document.getElementById('attendanceDate').value;
            if (selectedDate) {
                window.location.href = `advanced_attendance_clean.php?date=${selectedDate}`;
            }
        }
    </script>
</body>
</html>
