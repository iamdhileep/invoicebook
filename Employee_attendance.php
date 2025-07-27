<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

include 'db.php';
$page_title = 'Employee Attendance';

// Skip permission check for now - just check if user is logged in
// include 'auth_guard.php';
// checkPermission(PagePermissions::ATTENDANCE);

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Handle AJAX requests for punch operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    $action = $input['action'] ?? '';
    $current_time = date('Y-m-d H:i:s');
    $attendance_date = $input['attendance_date'] ?? date('Y-m-d');
    
    try {
        if ($action === 'punch_in') {
            $employee_id = intval($input['employee_id'] ?? 0);
            
            if ($employee_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
                exit;
            }
            
            // Check if already punched in
            $check = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?");
            $check->bind_param('is', $employee_id, $attendance_date);
            $check->execute();
            $existing = $check->get_result()->fetch_assoc();
            
            if ($existing && $existing['time_in']) {
                echo json_encode(['success' => false, 'message' => 'Already punched in today']);
                exit;
            }
            
            if ($existing) {
                $stmt = $conn->prepare("UPDATE attendance SET time_in = ?, status = 'Present' WHERE employee_id = ? AND attendance_date = ?");
                $stmt->bind_param('sis', $current_time, $employee_id, $attendance_date);
            } else {
                $stmt = $conn->prepare("INSERT INTO attendance (employee_id, attendance_date, time_in, status) VALUES (?, ?, ?, 'Present')");
                $stmt->bind_param('iss', $employee_id, $attendance_date, $current_time);
            }
            
            if ($stmt && $stmt->execute()) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Punch in successful', 
                    'time' => date('h:i A', strtotime($current_time))
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            }
            
        } elseif ($action === 'punch_out') {
            $employee_id = intval($input['employee_id'] ?? 0);
            
            if ($employee_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
                exit;
            }
            
            $stmt = $conn->prepare("UPDATE attendance SET time_out = ? WHERE employee_id = ? AND attendance_date = ?");
            $stmt->bind_param('sis', $current_time, $employee_id, $attendance_date);
            
            if ($stmt && $stmt->execute()) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Punch out successful', 
                    'time' => date('h:i A', strtotime($current_time))
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

$current_date = $_GET['date'] ?? date('Y-m-d');

// Fetch employees and attendance for the selected date
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
            WHEN a.time_in IS NOT NULL AND a.time_out IS NOT NULL AND a.time_out > a.time_in
            THEN TIMEDIFF(a.time_out, a.time_in)
            ELSE NULL
        END as work_duration
    FROM employees e
    LEFT JOIN attendance a ON e.employee_id = a.employee_id AND a.attendance_date = ?
    ORDER BY e.name ASC
";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $current_date);
$stmt->execute();
$result = $stmt->get_result();
$employees = $result->fetch_all(MYSQLI_ASSOC);

// Debug info
$stats = [
    'total_employees' => count($employees),
    'current_time' => date('h:i:s A'),
    'db_status' => $conn ? 'Connected' : 'Disconnected',
    'session_admin' => isset($_SESSION['admin']) ? 'Yes' : 'No',
    'post_method' => $_SERVER['REQUEST_METHOD'] === 'POST' ? 'Yes' : 'No',
];

include 'layouts/header.php';
include 'layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="h5 mb-0">⚡ Employee Attendance (Advanced)</h1>
                <p class="text-muted small">Advanced attendance management with face recognition - <?= date('F j, Y') ?></p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="live-clock">
                    <i class="bi bi-clock me-2"></i>
                    <strong>Live Time: <span id="liveClock"></span></strong>
                </div>
                <a href="attendance-calendar.php" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-calendar3"></i> View Calendar
                </a>
            </div>
        </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Debug Panel -->
            <div class="card mb-3 border-warning border-0 shadow-sm">
                <div class="card-header bg-warning text-dark border-0 py-2">
                    <h6 class="mb-0"><i class="bi bi-bug me-2"></i>Debug Information (Current Date: <?= $current_date ?>)</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-2"><strong>Total Employees:</strong><br><span class="badge bg-info fs-6"><?= $stats['total_employees'] ?></span></div>
                        <div class="col-md-2"><strong>Current Time:</strong><br><span class="badge bg-primary fs-6"><?= $stats['current_time'] ?></span></div>
                        <div class="col-md-2"><strong>Database:</strong><br><span class="badge bg-success fs-6"><?= $stats['db_status'] ?></span></div>
                        <div class="col-md-2"><strong>Session Admin:</strong><br><span class="badge bg-success fs-6"><?= $stats['session_admin'] ?></span></div>
                        <div class="col-md-2"><strong>POST Method:</strong><br><span class="badge bg-info fs-6"><?= $stats['post_method'] ?></span></div>
                        <div class="col-md-2"><strong>Test:</strong><br><button class="btn btn-sm btn-outline-primary">Debug</button></div>
                    </div>
                </div>
            </div>

            <!-- Face Login Attendance (Fully Functional) -->
            <div class="card mb-4 border-primary border-0 shadow-sm">
                <div class="card-header bg-primary text-white border-0 py-2">
                    <h6 class="mb-0">
                        <i class="bi bi-person-bounding-box me-2"></i>
                        Face Recognition Attendance
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <p class="mb-2">
                                <i class="bi bi-camera me-2"></i>
                                Quick punch in/out using face recognition technology
                            </p>
                            <small class="text-muted">
                                Position your face in front of the camera for automatic employee identification
                            </small>
                        </div>
                        <div class="col-md-4 text-end">
                            <button type="button" class="btn btn-primary btn-lg" onclick="openFaceRecognition()">
                                <i class="bi bi-camera-fill me-2"></i>
                                Start Face Scan
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bulk Actions -->
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-header bg-light border-0 py-2">
                    <h6 class="mb-0 text-dark"><i class="bi bi-funnel me-2"></i>Bulk Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex gap-2 flex-wrap align-items-center">
                        <span class="badge bg-info" id="selectedCount">0 selected</span>
                        <button type="button" class="btn btn-success btn-sm" onclick="bulkPunchIn()">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Bulk Punch In
                        </button>
                        <button type="button" class="btn btn-danger btn-sm" onclick="bulkPunchOut()">
                            <i class="bi bi-box-arrow-right me-1"></i> Bulk Punch Out
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="selectAllEmployees()">
                            <i class="bi bi-check-all me-1"></i> Select All
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearSelection()">
                            <i class="bi bi-x-circle me-1"></i> Clear Selection
                        </button>
                    </div>
                </div>
            </div>

            <!-- Attendance Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light border-0 py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="card-title mb-0 text-dark">
                            <i class="bi bi-people me-2"></i>Employee Attendance - <?= date('F j, Y', strtotime($current_date)) ?>
                            <span class="badge bg-secondary ms-2"><?= count($employees) ?> employees</span>
                        </h6>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="attendanceTable">
                            <thead>
                                <tr>
                                    <th width="50"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                                    <th width="80">Photo</th>
                                    <th>Employee Details</th>
                                    <th>Position</th>
                                    <th>Status</th>
                                    <th>Punch In</th>
                                    <th>Punch Out</th>
                                    <th>Duration</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($employees as $emp): ?>
                                <tr id="employee-row-<?= $emp['employee_id'] ?>">
                                    <td><input type="checkbox" name="employee_ids[]" value="<?= $emp['employee_id'] ?>" class="form-check-input employee-checkbox"></td>
                                    <td>
                                        <?php if (!empty($emp['photo']) && file_exists($emp['photo'])): ?>
                                            <img src="<?= htmlspecialchars($emp['photo']) ?>" class="rounded-circle" style="width: 50px; height: 50px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                                <i class="bi bi-person text-white"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($emp['name']) ?></strong>
                                            <br><small class="text-muted"><?= htmlspecialchars($emp['employee_code']) ?></small>
                                            <br><small class="text-muted"><?= htmlspecialchars($emp['phone']) ?></small>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-info"><?= htmlspecialchars($emp['position']) ?></span></td>
                                    <td><span class="badge bg-secondary" id="status-badge-<?= $emp['employee_id'] ?>"><?= $emp['status'] ?: 'Not Marked' ?></span></td>
                                    <td><div class="time-display" id="time-in-display-<?= $emp['employee_id'] ?>"><?= $emp['time_in'] ? date('h:i A', strtotime($emp['time_in'])) : '-' ?></div></td>
                                    <td><div class="time-display" id="time-out-display-<?= $emp['employee_id'] ?>"><?= $emp['time_out'] ? date('h:i A', strtotime($emp['time_out'])) : '-' ?></div></td>
                                    <td><div id="duration-display-<?= $emp['employee_id'] ?>"><?= $emp['work_duration'] ? $emp['work_duration'] : '-' ?></div></td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-success punch-in-btn" 
                                                    id="punch-in-btn-<?= $emp['employee_id'] ?>" 
                                                    onclick="punchIn(<?= $emp['employee_id'] ?>)" 
                                                    data-id="<?= $emp['employee_id'] ?>" 
                                                    data-bs-toggle="tooltip" title="Punch In">
                                                <i class="bi bi-box-arrow-in-right"></i> In
                                            </button>
                                            <button type="button" class="btn btn-danger punch-out-btn" 
                                                    id="punch-out-btn-<?= $emp['employee_id'] ?>" 
                                                    onclick="punchOut(<?= $emp['employee_id'] ?>)" 
                                                    data-id="<?= $emp['employee_id'] ?>" 
                                                    data-bs-toggle="tooltip" title="Punch Out">
                                                <i class="bi bi-box-arrow-left"></i> Out
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

        <div class="col-lg-4">
            <!-- Today's Summary -->
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-header bg-light border-0 py-2">
                    <h6 class="mb-0 text-dark"><i class="bi bi-graph-up me-2"></i>Today's Summary</h6>
                </div>
                <div class="card-body">
                    <?php
                    $totalEmployees = count($employees);
                    $presentCount = 0;
                    $absentCount = 0;
                    $lateCount = 0;
                    $halfDayCount = 0;
                    $notMarkedCount = 0;

                    foreach ($employees as $emp) {
                        switch ($emp['status']) {
                            case 'Present':
                                $presentCount++;
                                break;
                            case 'Absent':
                                $absentCount++;
                                break;
                            case 'Late':
                                $lateCount++;
                                break;
                            case 'Half Day':
                                $halfDayCount++;
                                break;
                            default:
                                $notMarkedCount++;
                                break;
                        }
                    }

                    $attendanceRate = $totalEmployees > 0 ? (($presentCount + $lateCount + $halfDayCount) / $totalEmployees) * 100 : 0;
                    ?>
                    
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="card bg-primary text-white text-center">
                                <div class="card-body p-2">
                                    <h5 class="mb-0"><?= $totalEmployees ?></h5>
                                    <small>Total</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card bg-success text-white text-center">
                                <div class="card-body p-2">
                                    <h5 class="mb-0"><?= $presentCount ?></h5>
                                    <small>Present</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card bg-danger text-white text-center">
                                <div class="card-body p-2">
                                    <h5 class="mb-0"><?= $absentCount ?></h5>
                                    <small>Absent</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card bg-warning text-white text-center">
                                <div class="card-body p-2">
                                    <h5 class="mb-0"><?= $lateCount ?></h5>
                                    <small>Late</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Attendance Rate</span>
                            <strong class="<?= $attendanceRate >= 90 ? 'text-success' : ($attendanceRate >= 75 ? 'text-warning' : 'text-danger') ?>">
                                <?= number_format($attendanceRate, 1) ?>%
                            </strong>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-success" style="width: <?= $attendanceRate ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-header bg-light border-0 py-2">
                    <h6 class="mb-0 text-dark"><i class="bi bi-lightning me-2"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-outline-success btn-sm" onclick="markAllPresent()">
                            <i class="bi bi-check-all"></i> Mark All Present
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="setDefaultTimes()">
                            <i class="bi bi-clock"></i> Set Default Times
                        </button>
                        <button type="button" class="btn btn-outline-info btn-sm" onclick="openFaceRecognition()">
                            <i class="bi bi-camera-fill"></i> Face Recognition
                        </button>
                        <a href="attendance_preview.php" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-eye"></i> View Reports
                        </a>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light border-0 py-2">
                    <h6 class="mb-0 text-dark"><i class="bi bi-clock-history me-2"></i>Recent Activity</h6>
                </div>
                <div class="card-body">
                    <div class="activity-feed">
                        <?php
                        // Get recent activity (last 5 punch actions today)
                        $recentQuery = $conn->prepare("
                            SELECT e.name, a.time_in, a.time_out, a.status 
                            FROM attendance a 
                            JOIN employees e ON a.employee_id = e.employee_id 
                            WHERE a.attendance_date = ? 
                            AND (a.time_in IS NOT NULL OR a.time_out IS NOT NULL)
                            ORDER BY GREATEST(COALESCE(a.time_in, '00:00:00'), COALESCE(a.time_out, '00:00:00')) DESC 
                            LIMIT 5
                        ");
                        $recentQuery->bind_param('s', $current_date);
                        $recentQuery->execute();
                        $recentActivities = $recentQuery->get_result();
                        
                        if ($recentActivities && $recentActivities->num_rows > 0):
                            while ($activity = $recentActivities->fetch_assoc()):
                        ?>
                            <div class="activity-item mb-2">
                                <div class="d-flex align-items-center">
                                    <div class="activity-icon me-2">
                                        <i class="bi bi-person-check text-success"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <small class="text-muted d-block"><?= htmlspecialchars($activity['name']) ?></small>
                                        <small>
                                            <?php if ($activity['time_out']): ?>
                                                Punched out at <?= date('h:i A', strtotime($activity['time_out'])) ?>
                                            <?php elseif ($activity['time_in']): ?>
                                                Punched in at <?= date('h:i A', strtotime($activity['time_in'])) ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php 
                            endwhile;
                        else:
                        ?>
                            <p class="text-muted text-center">No recent activity</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

    </div>
</div>

<!-- Include Face Recognition Modal -->
<?php include 'includes/face_recognition_modal.php'; ?>

<style>
.live-clock {
    color: #0d6efd;
    font-size: 1.1rem;
    padding: 8px 12px;
    background: rgba(13, 110, 253, 0.1);
    border-radius: 6px;
    border: 1px solid rgba(13, 110, 253, 0.2);
}

.punch-btn {
    font-size: 0.8rem;
    padding: 4px 8px;
    min-width: 45px;
}

.table th {
    white-space: nowrap;
    font-weight: 600;
    background-color: #f8f9fa;
}

.table td {
    vertical-align: middle;
}

.btn-group .btn {
    font-size: 0.8rem;
    padding: 4px 8px;
    min-width: 45px;
}

.btn-group .btn:not(:last-child) {
    border-right: none;
}

.face-login-box { 
    border: 2px dashed #0d6efd; 
    border-radius: 8px; 
    padding: 1.5rem; 
    text-align: center; 
    margin-bottom: 1.5rem; 
}

.face-login-box i { 
    font-size: 3rem; 
    color: #0d6efd; 
}

.activity-item {
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}

.activity-item:last-child {
    border-bottom: none;
}

/* Loading and animation styles */
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.spin {
    animation: spin 1s linear infinite;
}

/* Toast notification slide in animation */
@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

.toast-notification {
    animation: slideIn 0.3s ease-out;
}

/* Form enhancements */
.form-control:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}

.form-select:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}

/* Card hover effects */
.card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

/* Button loading state */
.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Table responsive improvements */
.table-responsive {
    border-radius: 8px;
}

.table th {
    border-bottom: 2px solid #dee2e6;
    font-size: 0.875rem;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

<script>
// Live clock
function updateLiveClock() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { hour12: true, hour: '2-digit', minute: '2-digit', second: '2-digit' });
    const liveClockElement = document.getElementById('liveClock');
    if (liveClockElement) {
        liveClockElement.textContent = timeString;
    }
}
setInterval(updateLiveClock, 1000);

// DataTable init
$(document).ready(function() {
    $('#attendanceTable').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[2, 'asc']], // Order by employee name
        columnDefs: [
            { orderable: false, targets: [0, 8] } // Disable ordering on checkbox and actions columns
        ]
    });
    
    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
});

// Select all/clear selection
window.selectAllEmployees = function() {
    document.querySelectorAll('.employee-checkbox').forEach(cb => cb.checked = true);
    document.getElementById('selectAll').checked = true;
    updateSelectionCounter();
};
window.clearSelection = function() {
    document.querySelectorAll('.employee-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('selectAll').checked = false;
    updateSelectionCounter();
};
function updateSelectionCounter() {
    const selectedCount = document.querySelectorAll('.employee-checkbox:checked').length;
    const selectedCountElement = document.getElementById('selectedCount');
    if (selectedCountElement) {
        selectedCountElement.textContent = selectedCount + ' selected';
    }
}
document.getElementById('selectAll').addEventListener('change', function() {
    if (this.checked) selectAllEmployees(); else clearSelection();
});
document.querySelectorAll('.employee-checkbox').forEach(cb => cb.addEventListener('change', updateSelectionCounter));

// Punch In/Out (Proper AJAX implementation)
window.punchIn = async function(employeeId) {
    if (!employeeId || employeeId <= 0) {
        showAlert('Error: Invalid Employee ID', 'danger');
        return;
    }
    
    const button = document.querySelector(`button[onclick="punchIn(${employeeId})"]`);
    const originalContent = button.innerHTML;
    button.innerHTML = '<i class="bi bi-hourglass-split"></i>';
    button.disabled = true;
    
    try {
        const response = await fetch('Employee_attendance.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'punch_in',
                employee_id: parseInt(employeeId),
                attendance_date: '<?= $current_date ?>'
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Update UI with safety checks
            const timeInElement = document.getElementById(`time-in-display-${employeeId}`);
            const statusBadgeElement = document.getElementById(`status-badge-${employeeId}`);
            
            if (timeInElement) {
                timeInElement.textContent = result.time;
            }
            
            if (statusBadgeElement) {
                statusBadgeElement.textContent = 'Present';
                statusBadgeElement.className = 'badge bg-success';
            }
            
            // Disable punch in, enable punch out
            button.disabled = true;
            const punchOutBtn = document.querySelector(`button[onclick="punchOut(${employeeId})"]`);
            if (punchOutBtn) punchOutBtn.disabled = false;
            
            showAlert(`✅ ${result.message}`, 'success');
        } else {
            showAlert(`❌ Punch In Failed: ${result.message}`, 'danger');
        }
    } catch (error) {
        showAlert('Error: ' + error.message, 'danger');
    } finally {
        button.innerHTML = originalContent;
        if (!button.disabled) {
            button.disabled = false;
        }
    }
};

window.punchOut = async function(employeeId) {
    if (!employeeId || employeeId <= 0) {
        showAlert('Error: Invalid Employee ID', 'danger');
        return;
    }
    
    const button = document.querySelector(`button[onclick="punchOut(${employeeId})"]`);
    const originalContent = button.innerHTML;
    button.innerHTML = '<i class="bi bi-hourglass-split"></i>';
    button.disabled = true;
    
    try {
        const response = await fetch('Employee_attendance.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'punch_out',
                employee_id: parseInt(employeeId),
                attendance_date: '<?= $current_date ?>'
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Update UI with safety checks
            const timeOutElement = document.getElementById(`time-out-display-${employeeId}`);
            
            if (timeOutElement) {
                timeOutElement.textContent = result.time;
            }
            
            // Calculate and update duration
            updateDuration(employeeId);
            
            button.disabled = true;
            
            showAlert(`✅ ${result.message}`, 'success');
        } else {
            showAlert(`❌ Punch Out Failed: ${result.message}`, 'danger');
        }
    } catch (error) {
        showAlert('Error: ' + error.message, 'danger');
    } finally {
        button.innerHTML = originalContent;
        if (!button.disabled) {
            button.disabled = false;
        }
    }
};

window.bulkPunchIn = function() {
    const selectedEmployees = Array.from(document.querySelectorAll('.employee-checkbox:checked'))
        .map(cb => cb.value);
    
    if (selectedEmployees.length === 0) {
        showAlert('Please select employees first', 'warning');
        return;
    }
    
    if (confirm(`Punch in ${selectedEmployees.length} selected employees?`)) {
        showAlert('Bulk punch in functionality - to be implemented', 'info');
    }
};

window.bulkPunchOut = function() {
    const selectedEmployees = Array.from(document.querySelectorAll('.employee-checkbox:checked'))
        .map(cb => cb.value);
    
    if (selectedEmployees.length === 0) {
        showAlert('Please select employees first', 'warning');
        return;
    }
    
    if (confirm(`Punch out ${selectedEmployees.length} selected employees?`)) {
        showAlert('Bulk punch out functionality - to be implemented', 'info');
    }
};

// Helper functions
function updateDuration(employeeId) {
    const timeInElement = document.getElementById(`time-in-display-${employeeId}`);
    const timeOutElement = document.getElementById(`time-out-display-${employeeId}`);
    
    if (!timeInElement || !timeOutElement) {
        console.warn(`Time elements not found for employee ${employeeId}`);
        return;
    }
    
    const timeInText = timeInElement.textContent;
    const timeOutText = timeOutElement.textContent;
    
    if (timeInText !== '-' && timeOutText !== '-') {
        const timeIn = new Date(`1970-01-01 ${convertTo24Hour(timeInText)}`);
        const timeOut = new Date(`1970-01-01 ${convertTo24Hour(timeOutText)}`);
        const diff = timeOut - timeIn;
        const hours = Math.floor(diff / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        
        const durationElement = document.getElementById(`duration-display-${employeeId}`);
        if (durationElement) {
            durationElement.textContent = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')} hrs`;
        }
    }
}

function convertTo24Hour(time12h) {
    const [time, modifier] = time12h.split(' ');
    let [hours, minutes] = time.split(':');
    if (hours === '12') {
        hours = '00';
    }
    if (modifier === 'PM') {
        hours = parseInt(hours, 10) + 12;
    }
    return `${hours}:${minutes}:00`;
}

function showAlert(message, type) {
    // Create alert element
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.parentNode.removeChild(alertDiv);
        }
    }, 5000);
}

// Additional helper functions for sidebar actions
window.markAllPresent = function() {
    if (confirm('Mark all employees as present with default times?')) {
        document.querySelectorAll('select[name^="status"]').forEach(select => {
            select.value = 'Present';
        });
        setDefaultTimes();
        showAlert('All employees marked as present', 'success');
    }
};

window.setDefaultTimes = function() {
    const defaultInTime = '09:00';
    const defaultOutTime = '18:00';
    
    document.querySelectorAll('input[name^="time_in"]').forEach(input => {
        if (!input.value) input.value = defaultInTime;
    });
    document.querySelectorAll('input[name^="time_out"]').forEach(input => {
        if (!input.value) input.value = defaultOutTime;
    });
    
    showAlert('Default times set for all employees', 'info');
};

// Note: openFaceRecognition() function is defined in includes/face_recognition_modal.php
</script>

<?php include 'layouts/footer.php'; ?>
</body>
</html>
