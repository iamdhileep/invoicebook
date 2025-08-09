<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

include '../db.php';
$page_title = 'Time Tracking - HRMS';

// Create time tracking table if it doesn't exist
$createTimeTrackingTable = "
CREATE TABLE IF NOT EXISTS hr_time_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    project_name VARCHAR(255) NOT NULL,
    task_description TEXT,
    date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NULL,
    total_hours DECIMAL(4,2) DEFAULT 0,
    status ENUM('in_progress', 'paused', 'completed') DEFAULT 'in_progress',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_employee_date (employee_id, date),
    INDEX idx_project (project_name),
    INDEX idx_status (status),
    FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE
) ENGINE=InnoDB";
mysqli_query($conn, $createTimeTrackingTable);

// Create projects table if it doesn't exist
$createProjectsTable = "
CREATE TABLE IF NOT EXISTS hr_projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_name VARCHAR(255) NOT NULL UNIQUE,
    project_description TEXT,
    client_name VARCHAR(255),
    start_date DATE,
    end_date DATE,
    status ENUM('active', 'completed', 'on_hold', 'cancelled') DEFAULT 'active',
    budget DECIMAL(12,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB";
mysqli_query($conn, $createProjectsTable);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'start_tracking':
            $employee_id = intval($_POST['employee_id']);
            $project_name = mysqli_real_escape_string($conn, $_POST['project_name']);
            $task_description = mysqli_real_escape_string($conn, $_POST['task_description']);
            $start_time = mysqli_real_escape_string($conn, $_POST['start_time']);
            $date = date('Y-m-d');
            
            // Check if there's already an active tracking session
            $existing = mysqli_query($conn, "SELECT id FROM hr_time_tracking WHERE employee_id = $employee_id AND status = 'in_progress' AND date = '$date'");
            
            if ($existing && mysqli_num_rows($existing) > 0) {
                echo json_encode(['success' => false, 'message' => 'You already have an active time tracking session. Please stop it first.']);
            } else {
                $query = "INSERT INTO hr_time_tracking (employee_id, project_name, task_description, date, start_time, status) VALUES ($employee_id, '$project_name', '$task_description', '$date', '$start_time', 'in_progress')";
                
                if (mysqli_query($conn, $query)) {
                    echo json_encode(['success' => true, 'message' => 'Time tracking started successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
                }
            }
            exit;
            
        case 'stop_tracking':
            $id = intval($_POST['id']);
            $end_time = mysqli_real_escape_string($conn, $_POST['end_time']);
            $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
            
            // Get start time to calculate total hours
            $time_entry = mysqli_query($conn, "SELECT start_time FROM hr_time_tracking WHERE id = $id");
            if ($time_entry && $row = mysqli_fetch_assoc($time_entry)) {
                $start_time = $row['start_time'];
                $start_datetime = new DateTime("2025-01-01 $start_time");
                $end_datetime = new DateTime("2025-01-01 $end_time");
                $interval = $start_datetime->diff($end_datetime);
                $total_hours = $interval->h + ($interval->i / 60);
                
                $query = "UPDATE hr_time_tracking SET end_time = '$end_time', total_hours = $total_hours, status = 'completed', notes = '$notes' WHERE id = $id";
                
                if (mysqli_query($conn, $query)) {
                    echo json_encode(['success' => true, 'message' => 'Time tracking stopped successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Time entry not found.']);
            }
            exit;
            
        case 'add_manual_entry':
            $employee_id = intval($_POST['employee_id']);
            $project_name = mysqli_real_escape_string($conn, $_POST['project_name']);
            $task_description = mysqli_real_escape_string($conn, $_POST['task_description']);
            $date = mysqli_real_escape_string($conn, $_POST['date']);
            $start_time = mysqli_real_escape_string($conn, $_POST['start_time']);
            $end_time = mysqli_real_escape_string($conn, $_POST['end_time']);
            $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
            
            // Calculate total hours
            $start_datetime = new DateTime("2025-01-01 $start_time");
            $end_datetime = new DateTime("2025-01-01 $end_time");
            $interval = $start_datetime->diff($end_datetime);
            $total_hours = $interval->h + ($interval->i / 60);
            
            $query = "INSERT INTO hr_time_tracking (employee_id, project_name, task_description, date, start_time, end_time, total_hours, status, notes) VALUES ($employee_id, '$project_name', '$task_description', '$date', '$start_time', '$end_time', $total_hours, 'completed', '$notes')";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Manual time entry added successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;
            
        case 'add_project':
            $project_name = mysqli_real_escape_string($conn, $_POST['project_name']);
            $project_description = mysqli_real_escape_string($conn, $_POST['project_description']);
            $client_name = mysqli_real_escape_string($conn, $_POST['client_name'] ?? '');
            $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
            $end_date = mysqli_real_escape_string($conn, $_POST['end_date'] ?? '');
            $budget = floatval($_POST['budget'] ?? 0);
            
            $end_date_sql = $end_date ? "'$end_date'" : 'NULL';
            
            $query = "INSERT INTO hr_projects (project_name, project_description, client_name, start_date, end_date, budget) VALUES ('$project_name', '$project_description', '$client_name', '$start_date', $end_date_sql, $budget)";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Project added successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;
            
        case 'delete_time_entry':
            $id = intval($_POST['id']);
            $query = "DELETE FROM hr_time_tracking WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Time entry deleted successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;
            
        case 'get_time_entry':
            $id = intval($_POST['id']);
            $query = mysqli_query($conn, "SELECT * FROM hr_time_tracking WHERE id = $id");
            if ($query && $row = mysqli_fetch_assoc($query)) {
                echo json_encode(['success' => true, 'data' => $row]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Time entry not found']);
            }
            exit;
            
        case 'update_time_entry':
            $id = intval($_POST['id']);
            $project_name = mysqli_real_escape_string($conn, $_POST['project_name']);
            $task_description = mysqli_real_escape_string($conn, $_POST['task_description']);
            $date = mysqli_real_escape_string($conn, $_POST['date']);
            $start_time = mysqli_real_escape_string($conn, $_POST['start_time']);
            $end_time = mysqli_real_escape_string($conn, $_POST['end_time']);
            $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
            
            // Calculate total hours
            $start_datetime = new DateTime("2025-01-01 $start_time");
            $end_datetime = new DateTime("2025-01-01 $end_time");
            $interval = $start_datetime->diff($end_datetime);
            $total_hours = $interval->h + ($interval->i / 60);
            
            $query = "UPDATE hr_time_tracking SET project_name = '$project_name', task_description = '$task_description', date = '$date', start_time = '$start_time', end_time = '$end_time', total_hours = $total_hours, notes = '$notes' WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Time entry updated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;
    }
}

// Get statistics
$today = date('Y-m-d');

// Total hours today
$total_hours_today = 0;
$hoursQuery = mysqli_query($conn, "SELECT SUM(total_hours) as total FROM hr_time_tracking WHERE date = '$today'");
if ($hoursQuery && $row = mysqli_fetch_assoc($hoursQuery)) {
    $total_hours_today = $row['total'] ?? 0;
}

// Active projects
$active_projects = 0;
$projectsQuery = mysqli_query($conn, "SELECT COUNT(*) as count FROM hr_projects WHERE status = 'active'");
if ($projectsQuery && $row = mysqli_fetch_assoc($projectsQuery)) {
    $active_projects = $row['count'];
}

// Active employees (working today)
$active_employees = 0;
$activeQuery = mysqli_query($conn, "SELECT COUNT(DISTINCT employee_id) as count FROM hr_time_tracking WHERE date = '$today' AND status = 'in_progress'");
if ($activeQuery && $row = mysqli_fetch_assoc($activeQuery)) {
    $active_employees = $row['count'];
}

// Productivity score based on expected vs actual hours
$expected_hours = 8;
$productivity_score = $total_hours_today > 0 ? round(($total_hours_today / ($active_employees * $expected_hours)) * 100) : 0;

// Get time entries with filters
$date_filter = $_GET['date'] ?? $today;
$employee_filter = $_GET['employee'] ?? '';
$project_filter = $_GET['project'] ?? '';

$where = "WHERE tt.date = '$date_filter'";
if ($employee_filter) {
    $where .= " AND (e.first_name LIKE '%$employee_filter%' OR e.last_name LIKE '%$employee_filter%')";
}
if ($project_filter) {
    $where .= " AND tt.project_name LIKE '%$project_filter%'";
}

$time_entries = mysqli_query($conn, "
    SELECT 
        tt.*,
        CONCAT(e.first_name, ' ', e.last_name) as employee_name,
        e.employee_id as emp_id,
        d.department_name
    FROM hr_time_tracking tt
    JOIN hr_employees e ON tt.employee_id = e.id
    LEFT JOIN hr_departments d ON e.department_id = d.id
    $where
    ORDER BY tt.created_at DESC
");

// Get employees for dropdown
$employees = mysqli_query($conn, "SELECT id, first_name, last_name, employee_id FROM hr_employees WHERE status = 'active' ORDER BY first_name");

// Get projects for dropdown
$projects = mysqli_query($conn, "SELECT DISTINCT project_name FROM hr_projects WHERE status = 'active' ORDER BY project_name");

// Get active tracking sessions
$active_sessions = mysqli_query($conn, "
    SELECT 
        tt.*,
        CONCAT(e.first_name, ' ', e.last_name) as employee_name
    FROM hr_time_tracking tt
    JOIN hr_employees e ON tt.employee_id = e.id
    WHERE tt.status = 'in_progress' AND tt.date = '$today'
    ORDER BY tt.start_time DESC
");

include '../layouts/header.php';
include '../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">⏰ Time Tracking</h1>
                <p class="text-muted">Track work hours, projects, and productivity across your organization</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#startTrackingModal">
                    <i class="bi bi-play"></i> Start Tracking
                </button>
                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addManualEntryModal">
                    <i class="bi bi-plus"></i> Manual Entry
                </button>
                <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addProjectModal">
                    <i class="bi bi-folder-plus"></i> New Project
                </button>
                <button class="btn btn-outline-info" onclick="exportTimeData()">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-clock fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= number_format($total_hours_today, 1) ?>h</h3>
                        <small class="opacity-75">Total Hours Today</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-folder fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $active_projects ?></h3>
                        <small class="opacity-75">Active Projects</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-people fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $active_employees ?></h3>
                        <small class="opacity-75">Currently Working</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-speedometer fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $productivity_score ?>%</h3>
                        <small class="opacity-75">Productivity Score</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Tracking Sessions -->
        <?php if ($active_sessions && mysqli_num_rows($active_sessions) > 0): ?>
        <div class="alert alert-info">
            <h5 class="alert-heading mb-3">⚡ Active Tracking Sessions</h5>
            <div class="row g-2">
                <?php while ($session = mysqli_fetch_assoc($active_sessions)): ?>
                    <div class="col-md-4">
                        <div class="card border-info">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="card-title mb-1"><?= htmlspecialchars($session['employee_name']) ?></h6>
                                        <p class="card-text mb-1"><strong><?= htmlspecialchars($session['project_name']) ?></strong></p>
                                        <small class="text-muted"><?= htmlspecialchars($session['task_description']) ?></small>
                                        <p class="mb-0 mt-2"><small><i class="bi bi-clock"></i> Started at <?= date('g:i A', strtotime($session['start_time'])) ?></small></p>
                                    </div>
                                    <button class="btn btn-sm btn-outline-danger" onclick="stopTracking(<?= $session['id'] ?>)">
                                        <i class="bi bi-stop"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Search and Filters -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Date</label>
                        <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date_filter) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Employee</label>
                        <input type="text" name="employee" class="form-control" placeholder="Search employee..." value="<?= htmlspecialchars($employee_filter) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Project</label>
                        <input type="text" name="project" class="form-control" placeholder="Search project..." value="<?= htmlspecialchars($project_filter) ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="bi bi-search"></i> Search
                        </button>
                        <a href="?" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Time Entries Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0 text-dark">
                    <i class="bi bi-table me-2"></i>Time Entries
                    <span class="badge bg-primary ms-2"><?= $time_entries ? mysqli_num_rows($time_entries) : 0 ?> entries</span>
                </h6>
            </div>
            <div class="card-body p-0">
                <?php if ($time_entries && mysqli_num_rows($time_entries) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Employee</th>
                                    <th>Project</th>
                                    <th>Task</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Duration</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($entry = mysqli_fetch_assoc($time_entries)): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong class="text-primary"><?= htmlspecialchars($entry['employee_name']) ?></strong>
                                                <br><small class="text-muted">ID: <?= htmlspecialchars($entry['emp_id']) ?></small>
                                                <?php if ($entry['department_name']): ?>
                                                    <br><small class="text-info"><?= htmlspecialchars($entry['department_name']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($entry['project_name']) ?></strong>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($entry['task_description']) ?>
                                            <?php if ($entry['notes']): ?>
                                                <br><small class="text-muted"><i class="bi bi-sticky"></i> <?= htmlspecialchars($entry['notes']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= date('M j, Y', strtotime($entry['date'])) ?>
                                        </td>
                                        <td>
                                            <small>
                                                <i class="bi bi-play text-success"></i> <?= date('g:i A', strtotime($entry['start_time'])) ?>
                                                <?php if ($entry['end_time']): ?>
                                                    <br><i class="bi bi-stop text-danger"></i> <?= date('g:i A', strtotime($entry['end_time'])) ?>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($entry['total_hours'] > 0): ?>
                                                <span class="badge bg-success"><?= number_format($entry['total_hours'], 2) ?>h</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">In Progress</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = match($entry['status']) {
                                                'completed' => 'bg-success',
                                                'in_progress' => 'bg-warning',
                                                'paused' => 'bg-secondary',
                                                default => 'bg-secondary'
                                            };
                                            ?>
                                            <span class="badge <?= $statusClass ?>"><?= ucfirst(str_replace('_', ' ', $entry['status'])) ?></span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <?php if ($entry['status'] === 'in_progress'): ?>
                                                    <button class="btn btn-outline-danger btn-sm" onclick="stopTracking(<?= $entry['id'] ?>)" title="Stop">
                                                        <i class="bi bi-stop"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-outline-warning btn-sm" onclick="editTimeEntry(<?= $entry['id'] ?>)" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-outline-danger btn-sm" onclick="deleteTimeEntry(<?= $entry['id'] ?>)" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-clock-history text-muted" style="font-size: 3rem;"></i>
                        <h5 class="text-muted mt-3">No time entries found</h5>
                        <p class="text-muted">Start tracking your work to see entries here</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#startTrackingModal">
                            <i class="bi bi-play me-1"></i>Start Tracking
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Start Tracking Modal -->
<div class="modal fade" id="startTrackingModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-play text-success me-2"></i>Start Time Tracking
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="startTrackingForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Employee *</label>
                        <select name="employee_id" class="form-select" required>
                            <option value="">Select Employee</option>
                            <?php 
                            mysqli_data_seek($employees, 0);
                            while ($emp = mysqli_fetch_assoc($employees)): 
                            ?>
                                <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?> (<?= $emp['employee_id'] ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Project *</label>
                        <input type="text" name="project_name" class="form-control" list="projectsList" required>
                        <datalist id="projectsList">
                            <?php 
                            mysqli_data_seek($projects, 0);
                            if ($projects): while ($proj = mysqli_fetch_assoc($projects)): 
                            ?>
                                <option value="<?= htmlspecialchars($proj['project_name']) ?>">
                            <?php endwhile; endif; ?>
                        </datalist>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Task Description *</label>
                        <textarea name="task_description" class="form-control" rows="2" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Start Time</label>
                        <input type="time" name="start_time" class="form-control" value="<?= date('H:i') ?>" required>
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Current Time: <?= date('Y-m-d H:i:s') ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-play me-1"></i>Start Tracking
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Stop Tracking Modal -->
<div class="modal fade" id="stopTrackingModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-stop text-danger me-2"></i>Stop Time Tracking
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="stopTrackingForm">
                <input type="hidden" name="id" id="stopTrackingId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">End Time</label>
                        <input type="time" name="end_time" class="form-control" value="<?= date('H:i') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Add any additional notes..."></textarea>
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Current Time: <?= date('Y-m-d H:i:s') ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-stop me-1"></i>Stop Tracking
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Manual Entry Modal -->
<div class="modal fade" id="addManualEntryModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus text-primary me-2"></i>Add Manual Time Entry
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addManualEntryForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Employee *</label>
                            <select name="employee_id" class="form-select" required>
                                <option value="">Select Employee</option>
                                <?php 
                                mysqli_data_seek($employees, 0);
                                while ($emp = mysqli_fetch_assoc($employees)): 
                                ?>
                                    <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?> (<?= $emp['employee_id'] ?>)</option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date *</label>
                            <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Project *</label>
                            <input type="text" name="project_name" class="form-control" list="projectsList2" required>
                            <datalist id="projectsList2">
                                <?php 
                                mysqli_data_seek($projects, 0);
                                if ($projects): while ($proj = mysqli_fetch_assoc($projects)): 
                                ?>
                                    <option value="<?= htmlspecialchars($proj['project_name']) ?>">
                                <?php endwhile; endif; ?>
                            </datalist>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Task Description *</label>
                            <textarea name="task_description" class="form-control" rows="2" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Start Time *</label>
                            <input type="time" name="start_time" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Time *</label>
                            <input type="time" name="end_time" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Add any additional notes..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Add Entry
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Time Entry Modal -->
<div class="modal fade" id="editTimeEntryModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-pencil text-warning me-2"></i>Edit Time Entry
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editTimeEntryForm">
                <input type="hidden" name="id" id="editTimeEntryId">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Project *</label>
                            <input type="text" name="project_name" id="editProjectName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date *</label>
                            <input type="date" name="date" id="editDate" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Task Description *</label>
                            <textarea name="task_description" id="editTaskDescription" class="form-control" rows="2" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Start Time *</label>
                            <input type="time" name="start_time" id="editStartTime" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Time *</label>
                            <input type="time" name="end_time" id="editEndTime" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" id="editNotes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-check-lg me-1"></i>Update Entry
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Project Modal -->
<div class="modal fade" id="addProjectModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-folder-plus text-secondary me-2"></i>Add New Project
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addProjectForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Project Name *</label>
                            <input type="text" name="project_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Client Name</label>
                            <input type="text" name="client_name" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Project Description</label>
                            <textarea name="project_description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Start Date *</label>
                            <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Budget (₹)</label>
                            <input type="number" name="budget" class="form-control" step="0.01" min="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Add Project
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Form submissions
document.getElementById('startTrackingForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'start_tracking');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while starting time tracking');
    });
});

document.getElementById('stopTrackingForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'stop_tracking');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while stopping time tracking');
    });
});

document.getElementById('addManualEntryForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'add_manual_entry');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding manual entry');
    });
});

document.getElementById('editTimeEntryForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'update_time_entry');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating time entry');
    });
});

document.getElementById('addProjectForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'add_project');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding project');
    });
});

// Utility functions
function stopTracking(id) {
    document.getElementById('stopTrackingId').value = id;
    new bootstrap.Modal(document.getElementById('stopTrackingModal')).show();
}

function editTimeEntry(id) {
    const formData = new FormData();
    formData.append('action', 'get_time_entry');
    formData.append('id', id);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const entry = data.data;
            document.getElementById('editTimeEntryId').value = entry.id;
            document.getElementById('editProjectName').value = entry.project_name || '';
            document.getElementById('editTaskDescription').value = entry.task_description || '';
            document.getElementById('editDate').value = entry.date || '';
            document.getElementById('editStartTime').value = entry.start_time || '';
            document.getElementById('editEndTime').value = entry.end_time || '';
            document.getElementById('editNotes').value = entry.notes || '';
            
            new bootstrap.Modal(document.getElementById('editTimeEntryModal')).show();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while fetching time entry details');
    });
}

function deleteTimeEntry(id) {
    if (confirm('Are you sure you want to delete this time entry?')) {
        const formData = new FormData();
        formData.append('action', 'delete_time_entry');
        formData.append('id', id);
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting time entry');
        });
    }
}

function exportTimeData() {
    const date_filter = '<?= $date_filter ?>';
    const employee_filter = '<?= $employee_filter ?>';
    const project_filter = '<?= $project_filter ?>';
    
    let url = 'export_time_data.php?';
    if (date_filter) url += 'date=' + encodeURIComponent(date_filter) + '&';
    if (employee_filter) url += 'employee=' + encodeURIComponent(employee_filter) + '&';
    if (project_filter) url += 'project=' + encodeURIComponent(project_filter) + '&';
    
    window.open(url, '_blank');
}
</script>

<style>
.stats-card {
    transition: transform 0.2s;
}
.stats-card:hover {
    transform: translateY(-2px);
}
.table th {
    font-weight: 600;
    font-size: 0.9rem;
}
.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}
</style>

<?php include '../layouts/footer.php'; ?>
