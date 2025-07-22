<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

include 'db.php';

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Get current date and filters
$current_date = $_GET['date'] ?? date('Y-m-d');
$department_filter = $_GET['department'] ?? '';
$position_filter = $_GET['position'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search_filter = $_GET['search'] ?? '';

// Build WHERE clause for filters
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($department_filter)) {
    $where_conditions[] = "e.department = ?";
    $params[] = $department_filter;
    $param_types .= 's';
}

if (!empty($position_filter)) {
    $where_conditions[] = "e.position = ?";
    $params[] = $position_filter;
    $param_types .= 's';
}

if (!empty($status_filter)) {
    $where_conditions[] = "a.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if (!empty($search_filter)) {
    $where_conditions[] = "(e.employee_name LIKE ? OR e.name LIKE ? OR e.employee_code LIKE ?)";
    $search_param = '%' . $search_filter . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'AND ' . implode(' AND ', $where_conditions);
}

// Get all employees with their attendance for the selected date
$query = "
    SELECT 
        e.employee_id,
        COALESCE(e.employee_name, e.name) as name,
        e.employee_code,
        e.position,
        e.phone,
        e.monthly_salary,
        e.photo,
        e.department,
        a.status,
        a.time_in,
        a.time_out,
        a.attendance_date,
        CASE 
            WHEN a.time_in IS NOT NULL AND a.time_out IS NOT NULL 
            THEN TIMEDIFF(a.time_out, a.time_in)
            ELSE NULL
        END as work_duration,
        CASE 
            WHEN a.time_in IS NOT NULL AND a.time_out IS NULL THEN 'punched_in'
            WHEN a.time_in IS NOT NULL AND a.time_out IS NOT NULL THEN 'punched_out'
            ELSE 'not_punched'
        END as punch_status
    FROM employees e
    LEFT JOIN attendance a ON e.employee_id = a.employee_id AND a.attendance_date = ?
    WHERE 1=1 $where_clause
    ORDER BY e.employee_name ASC, e.name ASC
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $all_params = array_merge([$current_date], $params);
    $all_param_types = 's' . $param_types;
    $stmt->bind_param($all_param_types, ...$all_params);
} else {
    $stmt->bind_param('s', $current_date);
}

$stmt->execute();
$employees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get filter options
$departments = $conn->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' ORDER BY department")->fetch_all(MYSQLI_ASSOC);
$positions = $conn->query("SELECT DISTINCT position FROM employees ORDER BY position")->fetch_all(MYSQLI_ASSOC);

// Get attendance statistics for the day
$stats_query = "
    SELECT 
        COUNT(DISTINCT e.employee_id) as total_employees,
        COUNT(CASE WHEN a.status = 'Present' THEN 1 END) as present_count,
        COUNT(CASE WHEN a.status = 'Absent' THEN 1 END) as absent_count,
        COUNT(CASE WHEN a.status = 'Late' THEN 1 END) as late_count,
        COUNT(CASE WHEN a.status = 'Half Day' THEN 1 END) as half_day_count,
        COUNT(CASE WHEN a.time_in IS NOT NULL AND a.time_out IS NULL THEN 1 END) as currently_in,
        COUNT(CASE WHEN a.time_in IS NULL AND a.status != 'Absent' THEN 1 END) as missing_punch_in,
        COUNT(CASE WHEN a.time_out IS NULL AND a.status != 'Absent' AND a.time_in IS NOT NULL THEN 1 END) as missing_punch_out
    FROM employees e
    LEFT JOIN attendance a ON e.employee_id = a.employee_id AND a.attendance_date = ?
    WHERE 1=1 $where_clause
";

$stats_stmt = $conn->prepare($stats_query);
if (!empty($params)) {
    $stats_stmt->bind_param($all_param_types, ...$all_params);
} else {
    $stats_stmt->bind_param('s', $current_date);
}

$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

include 'layouts/header.php';
?>

<div class="main-content">
    <?php include 'layouts/sidebar.php'; ?>
    
    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <!-- Page Header -->
                    <div class="page-header d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h4 class="page-title mb-0">
                                <i class="bi bi-clock-history"></i>
                                Advanced Attendance Management
                            </h4>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="pages/dashboard/dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active">Advanced Attendance</li>
                                </ol>
                            </nav>
                        </div>
                        <div class="d-flex gap-2">
                            <div class="live-clock bg-primary text-white px-3 py-2 rounded">
                                <i class="bi bi-clock me-2"></i>
                                <span id="liveClock"><?= date('h:i:s A') ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-2">
                            <div class="card text-center bg-primary text-white">
                                <div class="card-body p-3">
                                    <h4 class="mb-0"><?= $stats['total_employees'] ?></h4>
                                    <small>Total Employees</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card text-center bg-success text-white">
                                <div class="card-body p-3">
                                    <h4 class="mb-0"><?= $stats['present_count'] ?></h4>
                                    <small>Present</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card text-center bg-danger text-white">
                                <div class="card-body p-3">
                                    <h4 class="mb-0"><?= $stats['absent_count'] ?></h4>
                                    <small>Absent</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card text-center bg-warning text-dark">
                                <div class="card-body p-3">
                                    <h4 class="mb-0"><?= $stats['late_count'] ?></h4>
                                    <small>Late</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card text-center bg-info text-white">
                                <div class="card-body p-3">
                                    <h4 class="mb-0"><?= $stats['currently_in'] ?></h4>
                                    <small>Currently In</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card text-center bg-secondary text-white">
                                <div class="card-body p-3">
                                    <h4 class="mb-0"><?= $stats['half_day_count'] ?></h4>
                                    <small>Half Day</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="bi bi-funnel me-2"></i>
                                Filters & Controls
                            </h6>
                        </div>
                        <div class="card-body">
                            <form method="GET" id="filterForm">
                                <div class="row g-3">
                                    <div class="col-md-2">
                                        <label class="form-label">Date</label>
                                        <input type="date" name="date" class="form-control" 
                                               value="<?= $current_date ?>" id="attendanceDate">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Department</label>
                                        <select name="department" class="form-select">
                                            <option value="">All Departments</option>
                                            <?php foreach ($departments as $dept): ?>
                                                <option value="<?= htmlspecialchars($dept['department']) ?>" 
                                                        <?= $department_filter === $dept['department'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($dept['department']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Position</label>
                                        <select name="position" class="form-select">
                                            <option value="">All Positions</option>
                                            <?php foreach ($positions as $pos): ?>
                                                <option value="<?= htmlspecialchars($pos['position']) ?>" 
                                                        <?= $position_filter === $pos['position'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($pos['position']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-select">
                                            <option value="">All Status</option>
                                            <option value="Present" <?= $status_filter === 'Present' ? 'selected' : '' ?>>Present</option>
                                            <option value="Absent" <?= $status_filter === 'Absent' ? 'selected' : '' ?>>Absent</option>
                                            <option value="Late" <?= $status_filter === 'Late' ? 'selected' : '' ?>>Late</option>
                                            <option value="Half Day" <?= $status_filter === 'Half Day' ? 'selected' : '' ?>>Half Day</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Search</label>
                                        <input type="text" name="search" class="form-control" 
                                               placeholder="Name, Code..." value="<?= htmlspecialchars($search_filter) ?>">
                                    </div>
                                    <div class="col-md-1">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="submit" class="btn btn-primary d-block">
                                            <i class="bi bi-search"></i>
                                        </button>
                                    </div>
                                </div>
                            </form>
                            
                            <hr class="my-3">
                            
                            <!-- Bulk Actions -->
                            <div class="d-flex gap-2 flex-wrap">
                                <button type="button" class="btn btn-success" onclick="bulkPunchIn()">
                                    <i class="bi bi-box-arrow-in-right"></i> Bulk Punch In
                                </button>
                                <button type="button" class="btn btn-outline-primary" onclick="selectAllEmployees()">
                                    <i class="bi bi-check-all"></i> Select All
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="clearSelection()">
                                    <i class="bi bi-x-circle"></i> Clear Selection
                                </button>
                                <button type="button" class="btn btn-outline-info" onclick="refreshPage()">
                                    <i class="bi bi-arrow-clockwise"></i> Refresh
                                </button>
                                <button type="button" class="btn btn-outline-success" onclick="exportAttendance()">
                                    <i class="bi bi-download"></i> Export
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Attendance Table -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0">
                                <i class="bi bi-people me-2"></i>
                                Employee Attendance - <?= date('F j, Y', strtotime($current_date)) ?>
                                <span class="badge bg-secondary ms-2"><?= count($employees) ?> employees</span>
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="attendanceTable">
                                    <thead class="table-dark">
                                        <tr>
                                            <th width="50">
                                                <input type="checkbox" id="selectAll" class="form-check-input">
                                            </th>
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
                                                <td>
                                                    <input type="checkbox" name="employee_ids[]" 
                                                           value="<?= $emp['employee_id'] ?>" 
                                                           class="form-check-input employee-checkbox">
                                                </td>
                                                <td>
                                                    <?php if (!empty($emp['photo']) && file_exists($emp['photo'])): ?>
                                                        <img src="<?= htmlspecialchars($emp['photo']) ?>" 
                                                             class="rounded-circle" 
                                                             style="width: 50px; height: 50px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center" 
                                                             style="width: 50px; height: 50px;">
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
                                                <td>
                                                    <span class="badge bg-info"><?= htmlspecialchars($emp['position']) ?></span>
                                                    <?php if (!empty($emp['department'])): ?>
                                                        <br><small class="text-muted"><?= htmlspecialchars($emp['department']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= 
                                                        $emp['status'] === 'Present' ? 'success' : 
                                                        ($emp['status'] === 'Absent' ? 'danger' : 
                                                        ($emp['status'] === 'Late' ? 'warning' : 'secondary')) 
                                                    ?>" id="status-badge-<?= $emp['employee_id'] ?>">
                                                        <?= $emp['status'] ?: 'Not Marked' ?>
                                                    </span>
                                                    <br>
                                                    <small class="text-muted punch-status" id="punch-status-<?= $emp['employee_id'] ?>">
                                                        <?php
                                                        if ($emp['punch_status'] === 'punched_in') {
                                                            echo '<span class="text-success">Punched In</span>';
                                                        } elseif ($emp['punch_status'] === 'punched_out') {
                                                            echo '<span class="text-info">Punched Out</span>';
                                                        } else {
                                                            echo '<span class="text-muted">Not Punched</span>';
                                                        }
                                                        ?>
                                                    </small>
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
                                                    <div id="duration-display-<?= $emp['employee_id'] ?>">
                                                        <?php
                                                        if ($emp['work_duration']) {
                                                            $duration = new DateTime($emp['work_duration']);
                                                            echo $duration->format('H:i') . ' hrs';
                                                        } else {
                                                            echo '-';
                                                        }
                                                        ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button type="button" class="btn btn-success punch-in-btn" 
                                                                onclick="punchIn(<?= $emp['employee_id'] ?>)"
                                                                data-bs-toggle="tooltip" title="Punch In"
                                                                <?= $emp['punch_status'] === 'punched_in' ? 'disabled' : '' ?>>
                                                            <i class="bi bi-box-arrow-in-right"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-danger punch-out-btn" 
                                                                onclick="punchOut(<?= $emp['employee_id'] ?>)"
                                                                data-bs-toggle="tooltip" title="Punch Out"
                                                                <?= $emp['punch_status'] === 'not_punched' ? 'disabled' : '' ?>>
                                                            <i class="bi bi-box-arrow-right"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-primary" 
                                                                onclick="viewEmployeeDetails(<?= $emp['employee_id'] ?>)"
                                                                data-bs-toggle="tooltip" title="View Details">
                                                            <i class="bi bi-eye"></i>
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
    </div>
</div>

<?php include 'layouts/footer.php'; ?>

<script>
// Global variables
let currentDate = '<?= $current_date ?>';

// Live clock update
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

// Update clock every second
setInterval(updateLiveClock, 1000);

// Punch In function with AJAX
async function punchIn(employeeId) {
    const button = document.querySelector(`button[onclick="punchIn(${employeeId})"]`);
    const originalContent = button.innerHTML;
    button.innerHTML = '<i class="bi bi-hourglass-split"></i>';
    button.disabled = true;
    
    try {
        const response = await fetch('punch_attendance.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'punch_in',
                employee_id: employeeId,
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
            
            // Disable punch in, enable punch out
            button.disabled = true;
            document.querySelector(`button[onclick="punchOut(${employeeId})"]`).disabled = false;
            
            showAlert(result.message, 'success');
            
            // Refresh statistics
            setTimeout(refreshStats, 1000);
        } else {
            showAlert(result.message, 'danger');
        }
    } catch (error) {
        showAlert('Error: ' + error.message, 'danger');
    } finally {
        button.innerHTML = originalContent;
        if (!button.disabled) {
            button.disabled = false;
        }
    }
}

// Punch Out function with AJAX
async function punchOut(employeeId) {
    const button = document.querySelector(`button[onclick="punchOut(${employeeId})"]`);
    const originalContent = button.innerHTML;
    button.innerHTML = '<i class="bi bi-hourglass-split"></i>';
    button.disabled = true;
    
    try {
        const response = await fetch('punch_attendance.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'punch_out',
                employee_id: employeeId,
                attendance_date: currentDate
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Update UI
            document.getElementById(`time-out-display-${employeeId}`).textContent = result.time;
            document.getElementById(`punch-status-${employeeId}`).innerHTML = '<span class="text-info">Punched Out</span>';
            
            // Calculate and update duration
            const timeInElement = document.getElementById(`time-in-display-${employeeId}`);
            if (timeInElement.textContent !== '-') {
                // Calculate duration (simplified - you may want to improve this)
                updateDuration(employeeId);
            }
            
            button.disabled = true;
            
            showAlert(result.message, 'success');
            
            // Refresh statistics
            setTimeout(refreshStats, 1000);
        } else {
            showAlert(result.message, 'danger');
        }
    } catch (error) {
        showAlert('Error: ' + error.message, 'danger');
    } finally {
        button.innerHTML = originalContent;
        if (!button.disabled) {
            button.disabled = false;
        }
    }
}

// Bulk punch in
async function bulkPunchIn() {
    const selectedEmployees = Array.from(document.querySelectorAll('.employee-checkbox:checked'))
        .map(cb => cb.value);
    
    if (selectedEmployees.length === 0) {
        showAlert('Please select employees first', 'warning');
        return;
    }
    
    if (!confirm(`Punch in ${selectedEmployees.length} selected employees?`)) {
        return;
    }
    
    try {
        const response = await fetch('punch_attendance.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'bulk_punch_in',
                employee_ids: selectedEmployees,
                attendance_date: currentDate
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert(result.message, 'success');
            // Refresh page to update all UI elements
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showAlert(result.message, 'danger');
        }
    } catch (error) {
        showAlert('Error: ' + error.message, 'danger');
    }
}

// Update duration display
function updateDuration(employeeId) {
    const timeInText = document.getElementById(`time-in-display-${employeeId}`).textContent;
    const timeOutText = document.getElementById(`time-out-display-${employeeId}`).textContent;
    
    if (timeInText !== '-' && timeOutText !== '-') {
        // Simple duration calculation (you may want to improve this)
        const timeIn = new Date(`1970-01-01 ${convertTo24Hour(timeInText)}`);
        const timeOut = new Date(`1970-01-01 ${convertTo24Hour(timeOutText)}`);
        const diff = timeOut - timeIn;
        const hours = Math.floor(diff / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        
        document.getElementById(`duration-display-${employeeId}`).textContent = 
            `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')} hrs`;
    }
}

// Helper function to convert 12-hour to 24-hour format
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

// Select all employees
function selectAllEmployees() {
    const checkboxes = document.querySelectorAll('.employee-checkbox');
    checkboxes.forEach(cb => cb.checked = true);
    document.getElementById('selectAll').checked = true;
}

// Clear selection
function clearSelection() {
    const checkboxes = document.querySelectorAll('.employee-checkbox');
    checkboxes.forEach(cb => cb.checked = false);
    document.getElementById('selectAll').checked = false;
}

// Select all checkbox functionality
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.employee-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
});

// Auto-submit form on date change
document.getElementById('attendanceDate').addEventListener('change', function() {
    document.getElementById('filterForm').submit();
});

// Refresh page
function refreshPage() {
    window.location.reload();
}

// Refresh statistics
async function refreshStats() {
    // Simple page reload for now - you could implement AJAX refresh
    setTimeout(() => {
        window.location.reload();
    }, 2000);
}

// Export attendance
function exportAttendance() {
    const params = new URLSearchParams(window.location.search);
    window.open('export_attendance.php?' + params.toString());
}

// View employee details
function viewEmployeeDetails(employeeId) {
    // Use the existing employee details modal
    $.get('get_employee_details.php', {id: employeeId}, function(data) {
        $('#employeeModalBody').html(data);
        new bootstrap.Modal(document.getElementById('employeeModal')).show();
    }).fail(function() {
        showAlert('Failed to load employee details', 'danger');
    });
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<!-- Employee Details Modal -->
<div class="modal fade" id="employeeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Employee Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="employeeModalBody">
                <!-- Employee details will be loaded here -->
            </div>
        </div>
    </div>
</div>

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

.table th {
    font-weight: 600;
    font-size: 0.875rem;
}

.btn-group-sm > .btn, .btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}

.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.badge {
    font-size: 0.75rem;
}

.employee-checkbox:checked {
    background-color: #0d6efd;
    border-color: #0d6efd;
}

#attendanceTable tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05);
}
</style>