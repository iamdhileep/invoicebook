<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

include '../db.php';
$page_title = 'Shift Management - HRMS';

// Create required tables if they don't exist
$createShiftsTable = "
CREATE TABLE IF NOT EXISTS hr_shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    working_days VARCHAR(20) DEFAULT 'Monday-Friday',
    break_duration INT DEFAULT 60 COMMENT 'Break duration in minutes',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB";
mysqli_query($conn, $createShiftsTable);

$createShiftAssignmentsTable = "
CREATE TABLE IF NOT EXISTS hr_shift_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    shift_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    status ENUM('active', 'inactive', 'pending') DEFAULT 'active',
    assigned_by INT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_employee_date (employee_id, start_date),
    INDEX idx_shift_status (shift_id, status),
    FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
    FOREIGN KEY (shift_id) REFERENCES hr_shifts(id) ON DELETE CASCADE
) ENGINE=InnoDB";
mysqli_query($conn, $createShiftAssignmentsTable);

$createShiftRequestsTable = "
CREATE TABLE IF NOT EXISTS hr_shift_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    current_shift_id INT NOT NULL,
    requested_shift_id INT NOT NULL,
    request_date DATE NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    processed_by INT NULL,
    processed_date DATE NULL,
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
    FOREIGN KEY (current_shift_id) REFERENCES hr_shifts(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_shift_id) REFERENCES hr_shifts(id) ON DELETE CASCADE
) ENGINE=InnoDB";
mysqli_query($conn, $createShiftRequestsTable);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_shift':
            $name = mysqli_real_escape_string($conn, $_POST['name']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $start_time = mysqli_real_escape_string($conn, $_POST['start_time']);
            $end_time = mysqli_real_escape_string($conn, $_POST['end_time']);
            $working_days = mysqli_real_escape_string($conn, $_POST['working_days']);
            $break_duration = intval($_POST['break_duration']);
            
            $query = "INSERT INTO hr_shifts (name, description, start_time, end_time, working_days, break_duration) VALUES ('$name', '$description', '$start_time', '$end_time', '$working_days', $break_duration)";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Shift added successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;
            
        case 'update_shift':
            $id = intval($_POST['id']);
            $name = mysqli_real_escape_string($conn, $_POST['name']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $start_time = mysqli_real_escape_string($conn, $_POST['start_time']);
            $end_time = mysqli_real_escape_string($conn, $_POST['end_time']);
            $working_days = mysqli_real_escape_string($conn, $_POST['working_days']);
            $break_duration = intval($_POST['break_duration']);
            $status = mysqli_real_escape_string($conn, $_POST['status']);
            
            $query = "UPDATE hr_shifts SET name = '$name', description = '$description', start_time = '$start_time', end_time = '$end_time', working_days = '$working_days', break_duration = $break_duration, status = '$status' WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Shift updated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;
            
        case 'delete_shift':
            $id = intval($_POST['id']);
            
            // Check if shift has assignments
            $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM hr_shift_assignments WHERE shift_id = $id AND status = 'active'");
            $result = mysqli_fetch_assoc($check);
            
            if ($result['count'] > 0) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete shift with active assignments. Please reassign employees first.']);
            } else {
                $query = "DELETE FROM hr_shifts WHERE id = $id";
                if (mysqli_query($conn, $query)) {
                    echo json_encode(['success' => true, 'message' => 'Shift deleted successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
                }
            }
            exit;
            
        case 'assign_employee':
            $employee_id = intval($_POST['employee_id']);
            $shift_id = intval($_POST['shift_id']);
            $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
            $end_date = $_POST['end_date'] ? mysqli_real_escape_string($conn, $_POST['end_date']) : NULL;
            $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
            $assigned_by = $_SESSION['user_id'] ?? 1;
            
            // Check if employee already has active assignment for this date
            $existing = mysqli_query($conn, "SELECT id FROM hr_shift_assignments WHERE employee_id = $employee_id AND status = 'active' AND (end_date IS NULL OR end_date >= '$start_date')");
            
            if ($existing && mysqli_num_rows($existing) > 0) {
                echo json_encode(['success' => false, 'message' => 'Employee already has an active shift assignment.']);
            } else {
                $end_date_sql = $end_date ? "'$end_date'" : 'NULL';
                $query = "INSERT INTO hr_shift_assignments (employee_id, shift_id, start_date, end_date, assigned_by, notes) VALUES ($employee_id, $shift_id, '$start_date', $end_date_sql, $assigned_by, '$notes')";
                
                if (mysqli_query($conn, $query)) {
                    echo json_encode(['success' => true, 'message' => 'Employee assigned to shift successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
                }
            }
            exit;
            
        case 'remove_assignment':
            $id = intval($_POST['id']);
            $query = "UPDATE hr_shift_assignments SET status = 'inactive', end_date = CURDATE() WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Employee removed from shift successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;
            
        case 'get_shift':
            $id = intval($_POST['id']);
            $query = mysqli_query($conn, "SELECT * FROM hr_shifts WHERE id = $id");
            if ($query && $row = mysqli_fetch_assoc($query)) {
                echo json_encode(['success' => true, 'data' => $row]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Shift not found']);
            }
            exit;
            
        case 'approve_shift_request':
            $id = intval($_POST['id']);
            $comments = mysqli_real_escape_string($conn, $_POST['comments'] ?? '');
            $processed_by = $_SESSION['user_id'] ?? 1;
            
            // Get request details first
            $request = mysqli_query($conn, "SELECT * FROM hr_shift_requests WHERE id = $id");
            if ($request && $row = mysqli_fetch_assoc($request)) {
                // Update current assignment to end today
                mysqli_query($conn, "UPDATE hr_shift_assignments SET status = 'inactive', end_date = CURDATE() WHERE employee_id = {$row['employee_id']} AND shift_id = {$row['current_shift_id']} AND status = 'active'");
                
                // Create new assignment
                mysqli_query($conn, "INSERT INTO hr_shift_assignments (employee_id, shift_id, start_date, assigned_by, notes) VALUES ({$row['employee_id']}, {$row['requested_shift_id']}, CURDATE(), $processed_by, 'Shift change request approved')");
                
                // Update request status
                $query = "UPDATE hr_shift_requests SET status = 'approved', processed_by = $processed_by, processed_date = CURDATE(), comments = '$comments' WHERE id = $id";
                
                if (mysqli_query($conn, $query)) {
                    echo json_encode(['success' => true, 'message' => 'Shift request approved successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Request not found']);
            }
            exit;
            
        case 'reject_shift_request':
            $id = intval($_POST['id']);
            $comments = mysqli_real_escape_string($conn, $_POST['comments'] ?? '');
            $processed_by = $_SESSION['user_id'] ?? 1;
            
            $query = "UPDATE hr_shift_requests SET status = 'rejected', processed_by = $processed_by, processed_date = CURDATE(), comments = '$comments' WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Shift request rejected.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;
    }
}

// Get statistics
$total_shifts = 0;
$shiftsQuery = mysqli_query($conn, "SELECT COUNT(*) as count FROM hr_shifts WHERE status = 'active'");
if ($shiftsQuery && $row = mysqli_fetch_assoc($shiftsQuery)) {
    $total_shifts = $row['count'];
}

$assigned_employees = 0;
$assignedQuery = mysqli_query($conn, "SELECT COUNT(DISTINCT employee_id) as count FROM hr_shift_assignments WHERE status = 'active'");
if ($assignedQuery && $row = mysqli_fetch_assoc($assignedQuery)) {
    $assigned_employees = $row['count'];
}

$total_employees = 0;
$totalEmpQuery = mysqli_query($conn, "SELECT COUNT(*) as count FROM hr_employees WHERE status = 'active'");
if ($totalEmpQuery && $row = mysqli_fetch_assoc($totalEmpQuery)) {
    $total_employees = $row['count'];
}

$coverage_percentage = $total_employees > 0 ? round(($assigned_employees / $total_employees) * 100) : 0;

$pending_requests = 0;
$pendingQuery = mysqli_query($conn, "SELECT COUNT(*) as count FROM hr_shift_requests WHERE status = 'pending'");
if ($pendingQuery && $row = mysqli_fetch_assoc($pendingQuery)) {
    $pending_requests = $row['count'];
}

// Get all shifts
$shifts = mysqli_query($conn, "
    SELECT 
        s.*,
        COUNT(sa.id) as assigned_employees
    FROM hr_shifts s
    LEFT JOIN hr_shift_assignments sa ON s.id = sa.shift_id AND sa.status = 'active'
    WHERE s.status = 'active'
    GROUP BY s.id
    ORDER BY s.name
");

// Get shift assignments
$shift_assignments = mysqli_query($conn, "
    SELECT 
        sa.*,
        CONCAT(e.first_name, ' ', e.last_name) as employee_name,
        e.employee_id as emp_id,
        s.name as shift_name,
        s.start_time,
        s.end_time,
        d.department_name
    FROM hr_shift_assignments sa
    JOIN hr_employees e ON sa.employee_id = e.id
    JOIN hr_shifts s ON sa.shift_id = s.id
    LEFT JOIN hr_departments d ON e.department_id = d.id
    WHERE sa.status = 'active'
    ORDER BY e.first_name, e.last_name
");

// Get shift requests
$shift_requests = mysqli_query($conn, "
    SELECT 
        sr.*,
        CONCAT(e.first_name, ' ', e.last_name) as employee_name,
        e.employee_id as emp_id,
        cs.name as current_shift_name,
        rs.name as requested_shift_name
    FROM hr_shift_requests sr
    JOIN hr_employees e ON sr.employee_id = e.id
    JOIN hr_shifts cs ON sr.current_shift_id = cs.id
    JOIN hr_shifts rs ON sr.requested_shift_id = rs.id
    WHERE sr.status = 'pending'
    ORDER BY sr.created_at DESC
");

// Get employees for dropdown
$employees = mysqli_query($conn, "SELECT id, first_name, last_name, employee_id FROM hr_employees WHERE status = 'active' ORDER BY first_name");

include '../layouts/header.php';
include '../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">🕒 Shift Management</h1>
                <p class="text-muted">Manage work shifts, schedules, and employee assignments</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" onclick="exportShifts()">
                    <i class="bi bi-download"></i> Export Data
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addShiftModal">
                    <i class="bi bi-plus-lg"></i> Add Shift
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
                        <h3 class="mb-1 fw-bold"><?= $total_shifts ?></h3>
                        <small class="opacity-75">Total Shifts</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-people fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $assigned_employees ?></h3>
                        <small class="opacity-75">Assigned Employees</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-calendar-week fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $coverage_percentage ?>%</h3>
                        <small class="opacity-75">Coverage</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-arrow-left-right fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $pending_requests ?></h3>
                        <small class="opacity-75">Pending Requests</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Tabs -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#shifts" role="tab">
                            <i class="bi bi-clock me-2"></i>Shift Templates
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#assignments" role="tab">
                            <i class="bi bi-people me-2"></i>Employee Assignments
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#calendar" role="tab">
                            <i class="bi bi-calendar3 me-2"></i>Shift Calendar
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#requests" role="tab">
                            <i class="bi bi-arrow-left-right me-2"></i>Shift Requests
                            <?php if ($pending_requests > 0): ?>
                                <span class="badge bg-danger ms-1"><?= $pending_requests ?></span>
                            <?php endif; ?>
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <!-- Shift Templates Tab -->
                    <div class="tab-pane fade show active" id="shifts" role="tabpanel">
                        <div class="row">
                            <?php if ($shifts && mysqli_num_rows($shifts) > 0): ?>
                                <?php while ($shift = mysqli_fetch_assoc($shifts)): ?>
                                <div class="col-lg-6 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <h5 class="card-title mb-1"><?= htmlspecialchars($shift['name']) ?></h5>
                                                    <p class="text-muted small mb-0"><?= htmlspecialchars($shift['working_days']) ?></p>
                                                </div>
                                                <span class="badge bg-primary"><?= date('g:i A', strtotime($shift['start_time'])) ?> - <?= date('g:i A', strtotime($shift['end_time'])) ?></span>
                                            </div>
                                            <?php if ($shift['description']): ?>
                                                <p class="text-muted small"><?= htmlspecialchars($shift['description']) ?></p>
                                            <?php endif; ?>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="text-success">
                                                    <i class="bi bi-people me-1"></i>
                                                    <strong><?= $shift['assigned_employees'] ?></strong> Employees
                                                </div>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-outline-primary" onclick="editShift(<?= $shift['id'] ?>)" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-success" onclick="viewShiftDetails(<?= $shift['id'] ?>)" title="View Details">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteShift(<?= $shift['id'] ?>)" title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="col-12">
                                    <div class="text-center py-5">
                                        <i class="bi bi-clock text-muted" style="font-size: 3rem;"></i>
                                        <h5 class="text-muted mt-3">No Active Shifts Found</h5>
                                        <p class="text-muted">Create your first shift template to get started.</p>
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addShiftModal">
                                            <i class="bi bi-plus-lg me-2"></i>Create Shift
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Employee Assignments Tab -->
                    <div class="tab-pane fade" id="assignments" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Current Assignments</h5>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignEmployeeModal">
                                <i class="bi bi-plus-lg me-2"></i>Assign Employee
                            </button>
                        </div>
                        
                        <?php if ($shift_assignments && mysqli_num_rows($shift_assignments) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Employee</th>
                                            <th>Department</th>
                                            <th>Assigned Shift</th>
                                            <th>Start Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($assignment = mysqli_fetch_assoc($shift_assignments)): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-size: 0.875rem;">
                                                        <?= strtoupper(substr($assignment['employee_name'], 0, 2)) ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-semibold"><?= htmlspecialchars($assignment['employee_name']) ?></div>
                                                        <small class="text-muted"><?= htmlspecialchars($assignment['emp_id']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($assignment['department_name'] ?? 'N/A') ?></td>
                                            <td>
                                                <span class="fw-semibold"><?= htmlspecialchars($assignment['shift_name']) ?></span>
                                                <br><small class="text-muted"><?= date('g:i A', strtotime($assignment['start_time'])) ?> - <?= date('g:i A', strtotime($assignment['end_time'])) ?></small>
                                            </td>
                                            <td><?= date('M j, Y', strtotime($assignment['start_date'])) ?></td>
                                            <td>
                                                <span class="badge bg-success">Active</span>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-outline-warning" onclick="changeShift(<?= $assignment['id'] ?>)" title="Change Shift">
                                                        <i class="bi bi-arrow-repeat"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="removeAssignment(<?= $assignment['id'] ?>)" title="Remove">
                                                        <i class="bi bi-x-lg"></i>
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
                                <i class="bi bi-people text-muted" style="font-size: 3rem;"></i>
                                <h5 class="text-muted mt-3">No Employee Assignments</h5>
                                <p class="text-muted">Assign employees to shifts to get started.</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignEmployeeModal">
                                    <i class="bi bi-plus-lg me-2"></i>Assign Employee
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Shift Calendar Tab -->
                    <div class="tab-pane fade" id="calendar" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="mb-0"><?= date('F Y') ?> - Shift Schedule</h5>
                            <div>
                                <button class="btn btn-outline-secondary btn-sm me-2">
                                    <i class="bi bi-chevron-left"></i>
                                </button>
                                <button class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-primary">
                                    <tr>
                                        <th>Mon</th>
                                        <th>Tue</th>
                                        <th>Wed</th>
                                        <th>Thu</th>
                                        <th>Fri</th>
                                        <th>Sat</th>
                                        <th>Sun</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $currentDate = date('Y-m-01');
                                    $daysInMonth = date('t', strtotime($currentDate));
                                    $firstDayWeekday = date('N', strtotime($currentDate)) - 1; // 0 = Monday
                                    
                                    $week = 0;
                                    $day = 1 - $firstDayWeekday;
                                    
                                    while ($day <= $daysInMonth):
                                        echo '<tr style="height: 100px;">';
                                        for ($i = 0; $i < 7; $i++):
                                            $currentDay = $day + $i;
                                            if ($currentDay > 0 && $currentDay <= $daysInMonth):
                                    ?>
                                    <td class="position-relative p-2">
                                        <div class="fw-semibold"><?= $currentDay ?></div>
                                        <?php if ($currentDay % 3 == 1): ?>
                                            <small class="badge bg-warning">Morning</small>
                                        <?php elseif ($currentDay % 3 == 2): ?>
                                            <small class="badge bg-info">Evening</small>
                                        <?php else: ?>
                                            <small class="badge bg-dark">Night</small>
                                        <?php endif; ?>
                                    </td>
                                    <?php else: ?>
                                    <td class="bg-light"></td>
                                    <?php 
                                            endif;
                                        endfor;
                                        echo '</tr>';
                                        $day += 7;
                                    endwhile;
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Shift Requests Tab -->
                    <div class="tab-pane fade" id="requests" role="tabpanel">
                        <?php if ($shift_requests && mysqli_num_rows($shift_requests) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Employee</th>
                                            <th>Current Shift</th>
                                            <th>Requested Shift</th>
                                            <th>Request Date</th>
                                            <th>Reason</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($request = mysqli_fetch_assoc($shift_requests)): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars($request['employee_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($request['emp_id']) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($request['current_shift_name']) ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?= htmlspecialchars($request['requested_shift_name']) ?></span>
                                            </td>
                                            <td><?= date('M j, Y', strtotime($request['request_date'])) ?></td>
                                            <td><?= htmlspecialchars($request['reason']) ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-outline-success" onclick="approveRequest(<?= $request['id'] ?>)" title="Approve">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="rejectRequest(<?= $request['id'] ?>)" title="Reject">
                                                        <i class="bi bi-x-lg"></i>
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
                                <i class="bi bi-calendar-check text-muted" style="font-size: 4rem;"></i>
                                <h5 class="mt-3">No Active Requests</h5>
                                <p class="text-muted">Shift change requests will appear here when employees submit them.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Shift Modal -->
<div class="modal fade" id="addShiftModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus text-primary me-2"></i>Add New Shift
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addShiftForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Shift Name *</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g., Morning Shift" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Working Days *</label>
                            <select name="working_days" class="form-select" required>
                                <option value="Monday-Friday">Monday-Friday</option>
                                <option value="Monday-Saturday">Monday-Saturday</option>
                                <option value="Monday-Sunday">Monday-Sunday</option>
                                <option value="Saturday-Sunday">Weekend Only</option>
                                <option value="Custom">Custom</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Brief description of the shift"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Start Time *</label>
                            <input type="time" name="start_time" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Time *</label>
                            <input type="time" name="end_time" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Break Duration (minutes)</label>
                            <input type="number" name="break_duration" class="form-control" value="60" min="0" max="300">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Add Shift
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Shift Modal -->
<div class="modal fade" id="editShiftModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-pencil text-warning me-2"></i>Edit Shift
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editShiftForm">
                <input type="hidden" name="id" id="editShiftId">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Shift Name *</label>
                            <input type="text" name="name" id="editShiftName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Working Days *</label>
                            <select name="working_days" id="editWorkingDays" class="form-select" required>
                                <option value="Monday-Friday">Monday-Friday</option>
                                <option value="Monday-Saturday">Monday-Saturday</option>
                                <option value="Monday-Sunday">Monday-Sunday</option>
                                <option value="Saturday-Sunday">Weekend Only</option>
                                <option value="Custom">Custom</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="editShiftDescription" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Start Time *</label>
                            <input type="time" name="start_time" id="editStartTime" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End Time *</label>
                            <input type="time" name="end_time" id="editEndTime" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Break Duration (minutes)</label>
                            <input type="number" name="break_duration" id="editBreakDuration" class="form-control" min="0" max="300">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="editStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-check-lg me-1"></i>Update Shift
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Employee Modal -->
<div class="modal fade" id="assignEmployeeModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-person-plus text-success me-2"></i>Assign Employee to Shift
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="assignEmployeeForm">
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
                        <label class="form-label">Shift *</label>
                        <select name="shift_id" class="form-select" required>
                            <option value="">Select Shift</option>
                            <?php 
                            mysqli_data_seek($shifts, 0);
                            if ($shifts): while ($shift = mysqli_fetch_assoc($shifts)): 
                            ?>
                                <option value="<?= $shift['id'] ?>"><?= htmlspecialchars($shift['name']) ?> (<?= date('g:i A', strtotime($shift['start_time'])) ?> - <?= date('g:i A', strtotime($shift['end_time'])) ?>)</option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Start Date *</label>
                        <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">End Date (Optional)</label>
                        <input type="date" name="end_date" class="form-control">
                        <small class="text-muted">Leave empty for permanent assignment</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Additional notes about this assignment"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-lg me-1"></i>Assign Employee
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Request Action Modal -->
<div class="modal fade" id="requestActionModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="requestActionTitle">
                    <i class="bi bi-check-circle text-success me-2"></i>Approve Shift Request
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="requestActionForm">
                <input type="hidden" name="id" id="requestId">
                <input type="hidden" name="action" id="requestAction">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Comments</label>
                        <textarea name="comments" class="form-control" rows="3" placeholder="Add comments about this decision..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="requestActionBtn">
                        <i class="bi bi-check-lg me-1"></i>Approve Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Form submissions
document.getElementById('addShiftForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'add_shift');
    
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
        alert('An error occurred while adding shift');
    });
});

document.getElementById('editShiftForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'update_shift');
    
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
        alert('An error occurred while updating shift');
    });
});

document.getElementById('assignEmployeeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'assign_employee');
    
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
        alert('An error occurred while assigning employee');
    });
});

document.getElementById('requestActionForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const action = document.getElementById('requestAction').value;
    formData.append('action', action === 'approve' ? 'approve_shift_request' : 'reject_shift_request');
    
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
        alert('An error occurred while processing request');
    });
});

// Utility functions
function editShift(id) {
    const formData = new FormData();
    formData.append('action', 'get_shift');
    formData.append('id', id);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const shift = data.data;
            document.getElementById('editShiftId').value = shift.id;
            document.getElementById('editShiftName').value = shift.name || '';
            document.getElementById('editShiftDescription').value = shift.description || '';
            document.getElementById('editStartTime').value = shift.start_time || '';
            document.getElementById('editEndTime').value = shift.end_time || '';
            document.getElementById('editWorkingDays').value = shift.working_days || '';
            document.getElementById('editBreakDuration').value = shift.break_duration || '';
            document.getElementById('editStatus').value = shift.status || '';
            
            new bootstrap.Modal(document.getElementById('editShiftModal')).show();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while fetching shift details');
    });
}

function viewShiftDetails(id) {
    alert('Shift details view functionality will be implemented soon!');
}

function deleteShift(id) {
    if (confirm('Are you sure you want to delete this shift? This will affect all assigned employees.')) {
        const formData = new FormData();
        formData.append('action', 'delete_shift');
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
            alert('An error occurred while deleting shift');
        });
    }
}

function changeShift(id) {
    alert('Shift change functionality will be implemented soon!');
}

function removeAssignment(id) {
    if (confirm('Are you sure you want to remove this employee from their shift?')) {
        const formData = new FormData();
        formData.append('action', 'remove_assignment');
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
            alert('An error occurred while removing assignment');
        });
    }
}

function approveRequest(id) {
    document.getElementById('requestId').value = id;
    document.getElementById('requestAction').value = 'approve';
    document.getElementById('requestActionTitle').innerHTML = '<i class="bi bi-check-circle text-success me-2"></i>Approve Shift Request';
    document.getElementById('requestActionBtn').innerHTML = '<i class="bi bi-check-lg me-1"></i>Approve Request';
    document.getElementById('requestActionBtn').className = 'btn btn-success';
    
    new bootstrap.Modal(document.getElementById('requestActionModal')).show();
}

function rejectRequest(id) {
    document.getElementById('requestId').value = id;
    document.getElementById('requestAction').value = 'reject';
    document.getElementById('requestActionTitle').innerHTML = '<i class="bi bi-x-circle text-danger me-2"></i>Reject Shift Request';
    document.getElementById('requestActionBtn').innerHTML = '<i class="bi bi-x-lg me-1"></i>Reject Request';
    document.getElementById('requestActionBtn').className = 'btn btn-danger';
    
    new bootstrap.Modal(document.getElementById('requestActionModal')).show();
}

function exportShifts() {
    window.open('export_shifts.php', '_blank');
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
