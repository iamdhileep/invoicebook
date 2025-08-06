<?php
session_start();
// Check for either session variable for compatibility
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Include config and database

include '../config.php';
if (!isset($root_path)) 
include '../db.php';
if (!isset($root_path)) 
include '../auth_guard.php';

$page_title = 'Employee Onboarding - HRMS';

// Create onboarding tables if not exist
$createOnboardingTable = "
CREATE TABLE IF NOT EXISTS onboarding_process (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    onboarding_stage ENUM('initiated', 'documentation', 'verification', 'training', 'completed') DEFAULT 'initiated',
    start_date DATE NOT NULL,
    expected_completion DATE NOT NULL,
    actual_completion DATE NULL,
    assigned_hr INT NOT NULL,
    assigned_manager INT NULL,
    buddy_employee_id INT NULL,
    overall_progress DECIMAL(5,2) DEFAULT 0,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
    FOREIGN KEY (assigned_hr) REFERENCES employees(employee_id),
    FOREIGN KEY (assigned_manager) REFERENCES employees(employee_id),
    FOREIGN KEY (buddy_employee_id) REFERENCES employees(employee_id)
)";
mysqli_query($conn, $createOnboardingTable);

$createOnboardingTasksTable = "
CREATE TABLE IF NOT EXISTS onboarding_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    onboarding_id INT NOT NULL,
    task_name VARCHAR(255) NOT NULL,
    task_description TEXT,
    task_category ENUM('documentation', 'verification', 'training', 'orientation', 'setup') NOT NULL,
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    assigned_to INT NOT NULL,
    due_date DATE NOT NULL,
    status ENUM('pending', 'in_progress', 'completed', 'overdue') DEFAULT 'pending',
    completion_date DATE NULL,
    completion_notes TEXT,
    required BOOLEAN DEFAULT TRUE,
    order_sequence INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (onboarding_id) REFERENCES onboarding_process(id),
    FOREIGN KEY (assigned_to) REFERENCES employees(employee_id)
)";
mysqli_query($conn, $createOnboardingTasksTable);

$createOnboardingDocumentsTable = "
CREATE TABLE IF NOT EXISTS onboarding_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    onboarding_id INT NOT NULL,
    document_type VARCHAR(100) NOT NULL,
    document_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified BOOLEAN DEFAULT FALSE,
    verified_by INT NULL,
    verified_at TIMESTAMP NULL,
    verification_notes TEXT,
    required BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (onboarding_id) REFERENCES onboarding_process(id),
    FOREIGN KEY (verified_by) REFERENCES employees(employee_id)
)";
mysqli_query($conn, $createOnboardingDocumentsTable);

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'start_onboarding':
                $employee_id = intval($_POST['employee_id']);
                $start_date = $_POST['start_date'];
                $expected_completion = $_POST['expected_completion'];
                $assigned_hr = intval($_POST['assigned_hr']);
                $assigned_manager = isset($_POST['assigned_manager']) ? intval($_POST['assigned_manager']) : null;
                $buddy_employee_id = isset($_POST['buddy_employee_id']) ? intval($_POST['buddy_employee_id']) : null;
                $notes = $_POST['notes'] ?? '';
                
                // Insert onboarding process
                $query = "
                    INSERT INTO onboarding_process (
                        employee_id, start_date, expected_completion, assigned_hr, 
                        assigned_manager, buddy_employee_id, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ";
                
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, 'issiiss', $employee_id, $start_date, $expected_completion, 
                                     $assigned_hr, $assigned_manager, $buddy_employee_id, $notes);
                
                if (mysqli_stmt_execute($stmt)) {
                    $onboarding_id = mysqli_insert_id($conn);
                    
                    // Create default onboarding tasks
                    $defaultTasks = [
                        ['Document Collection', 'Collect all required documents from employee', 'documentation', 'high', $assigned_hr, 1],
                        ['ID Card Creation', 'Create employee ID card and access cards', 'setup', 'medium', $assigned_hr, 2],
                        ['IT Setup', 'Setup laptop, email, and system access', 'setup', 'high', $assigned_hr, 3],
                        ['Office Tour', 'Provide office tour and introduction to facilities', 'orientation', 'medium', $buddy_employee_id ?? $assigned_hr, 1],
                        ['Department Introduction', 'Introduce to team members and department', 'orientation', 'medium', $assigned_manager ?? $assigned_hr, 2],
                        ['Company Policies Training', 'Complete company policies and procedures training', 'training', 'high', $assigned_hr, 5],
                        ['Job Specific Training', 'Complete role-specific training modules', 'training', 'critical', $assigned_manager ?? $assigned_hr, 7],
                        ['Probation Review Setup', 'Schedule probation review meetings', 'setup', 'medium', $assigned_manager ?? $assigned_hr, 3]
                    ];
                    
                    foreach ($defaultTasks as $task) {
                        $taskQuery = "
                            INSERT INTO onboarding_tasks (
                                onboarding_id, task_name, task_description, task_category, 
                                priority, assigned_to, due_date, order_sequence
                            ) VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(?, INTERVAL ? DAY), ?)
                        ";
                        
                        $taskStmt = mysqli_prepare($conn, $taskQuery);
                        mysqli_stmt_bind_param($taskStmt, 'issssissi', 
                                             $onboarding_id, $task[0], $task[1], $task[2], 
                                             $task[3], $task[4], $start_date, $task[5], $task[5]);
                        mysqli_stmt_execute($taskStmt);
                    }
                    
                    $success_message = "Onboarding process started successfully!";
                } else {
                    $error_message = "Error starting onboarding process: " . mysqli_error($conn);
                }
                break;
                
            case 'update_task_status':
                $task_id = intval($_POST['task_id']);
                $status = $_POST['status'];
                $completion_notes = $_POST['completion_notes'] ?? '';
                
                $updateData = "status = '$status'";
                if ($status === 'completed') {
                    $updateData .= ", completion_date = CURDATE(), completion_notes = '$completion_notes'";
                }
                
                $query = "UPDATE onboarding_tasks SET $updateData WHERE id = $task_id";
                
                if (mysqli_query($conn, $query)) {
                    // Update overall progress
                    $progressQuery = "
                        SELECT onboarding_id FROM onboarding_tasks WHERE id = $task_id
                    ";
                    $result = mysqli_query($conn, $progressQuery);
                    $row = mysqli_fetch_assoc($result);
                    $onboarding_id = $row['onboarding_id'];
                    
                    $updateProgressQuery = "
                        UPDATE onboarding_process 
                        SET overall_progress = (
                            SELECT (COUNT(CASE WHEN status = 'completed' THEN 1 END) * 100.0 / COUNT(*))
                            FROM onboarding_tasks 
                            WHERE onboarding_id = $onboarding_id
                        )
                        WHERE id = $onboarding_id
                    ";
                    mysqli_query($conn, $updateProgressQuery);
                    
                    $success_message = "Task status updated successfully!";
                } else {
                    $error_message = "Error updating task status: " . mysqli_error($conn);
                }
                break;
        }
    }
}

// Get onboarding statistics
$stats = [
    'active_onboarding' => 0,
    'completed_this_month' => 0,
    'overdue_tasks' => 0,
    'avg_completion_time' => 0
];

$statsQuery = "
    SELECT 
        SUM(CASE WHEN op.status = 'active' THEN 1 ELSE 0 END) as active_onboarding,
        SUM(CASE WHEN op.status = 'completed' AND MONTH(op.actual_completion) = MONTH(CURDATE()) 
                 AND YEAR(op.actual_completion) = YEAR(CURDATE()) THEN 1 ELSE 0 END) as completed_this_month,
        AVG(CASE WHEN op.status = 'completed' THEN DATEDIFF(op.actual_completion, op.start_date) END) as avg_completion_time
    FROM onboarding_process op
";

$result = mysqli_query($conn, $statsQuery);
if ($result) {
    $stats = array_merge($stats, mysqli_fetch_assoc($result));
}

// Get overdue tasks count
$overdueQuery = "
    SELECT COUNT(*) as overdue_tasks 
    FROM onboarding_tasks ot
    JOIN onboarding_process op ON ot.onboarding_id = op.id
    WHERE ot.status IN ('pending', 'in_progress') 
    AND ot.due_date < CURDATE()
    AND op.status = 'active'
";
$result = mysqli_query($conn, $overdueQuery);
if ($result) {
    $overdue = mysqli_fetch_assoc($result);
    $stats['overdue_tasks'] = $overdue['overdue_tasks'];
}

// Get active onboarding processes
$activeOnboarding = [];
$query = "
    SELECT op.*, e.name as employee_name, e.employee_code, e.position,
           hr.name as hr_name, mgr.name as manager_name, buddy.name as buddy_name
    FROM onboarding_process op
    JOIN employees e ON op.employee_id = e.employee_id
    LEFT JOIN employees hr ON op.assigned_hr = hr.employee_id
    LEFT JOIN employees mgr ON op.assigned_manager = mgr.employee_id
    LEFT JOIN employees buddy ON op.buddy_employee_id = buddy.employee_id
    WHERE op.status = 'active'
    ORDER BY op.start_date DESC
";

$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $activeOnboarding[] = $row;
    }
}

// Get employees for dropdowns (exclude those with active onboarding)
$availableEmployees = [];
$query = "
    SELECT e.employee_id, e.name, e.employee_code, e.position 
    FROM employees e 
    WHERE e.status = 'active' 
    AND e.employee_id NOT IN (
        SELECT employee_id FROM onboarding_process WHERE status = 'active'
    )
    ORDER BY e.name
";
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $availableEmployees[] = $row;
    }
}

// Get HR and Manager employees
$hrEmployees = [];
$managerEmployees = [];
$query = "SELECT employee_id, name FROM employees WHERE status = 'active' ORDER BY name";
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $hrEmployees[] = $row;
        $managerEmployees[] = $row;
    }
}

include '../layouts/header.php';
if (!isset($root_path)) 
include '../layouts/sidebar.php';
?>

<div class="main-content animate-fade-in-up">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="gradient-text mb-2" style="font-size: 2.5rem; font-weight: 700;">
                    <i class="bi bi-person-plus-fill text-primary me-3"></i>Employee Onboarding
                </h1>
                <p class="text-muted" style="font-size: 1.1rem;">Streamline new employee integration with structured onboarding workflows</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-info" onclick="exportOnboardingReport()">
                    <i class="bi bi-download"></i> Export Report
                </button>
                <button class="btn btn-outline-success" onclick="viewOnboardingTemplate()">
                    <i class="bi bi-file-earmark-text"></i> Templates
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#startOnboardingModal">
                    <i class="bi bi-play-circle"></i> Start Onboarding
                </button>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i><?= $success_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-gradient-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="card-title h2 mb-2"><?= intval($stats['active_onboarding']) ?></h3>
                                <p class="card-text opacity-90">Active Onboarding</p>
                                <small class="opacity-75">In progress</small>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-person-plus"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-gradient-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="card-title h2 mb-2"><?= intval($stats['completed_this_month']) ?></h3>
                                <p class="card-text opacity-90">Completed This Month</p>
                                <small class="opacity-75"><?= date('F Y') ?></small>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-check-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-gradient-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="card-title h2 mb-2"><?= intval($stats['overdue_tasks']) ?></h3>
                                <p class="card-text opacity-90">Overdue Tasks</p>
                                <small class="opacity-75">Needs attention</small>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-exclamation-triangle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-gradient-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="card-title h2 mb-2"><?= intval($stats['avg_completion_time']) ?></h3>
                                <p class="card-text opacity-90">Avg. Completion</p>
                                <small class="opacity-75">Days</small>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-clock"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="row g-4">
            <!-- Active Onboarding List -->
            <div class="col-xl-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-list-check text-primary"></i> Active Onboarding Processes
                        </h5>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-secondary active">All</button>
                            <button class="btn btn-outline-secondary">This Week</button>
                            <button class="btn btn-outline-secondary">Overdue</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($activeOnboarding)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-person-plus display-1 text-muted"></i>
                                <h4 class="mt-3 text-muted">No Active Onboarding</h4>
                                <p class="text-muted">Start a new onboarding process for your new employees</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#startOnboardingModal">
                                    <i class="bi bi-plus-circle"></i> Start Onboarding
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover" id="onboardingTable">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Start Date</th>
                                            <th>Expected Completion</th>
                                            <th>Progress</th>
                                            <th>Stage</th>
                                            <th>Assigned HR</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($activeOnboarding as $onboarding): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <h6 class="mb-0"><?= htmlspecialchars($onboarding['employee_name']) ?></h6>
                                                        <small class="text-muted"><?= htmlspecialchars($onboarding['employee_code']) ?> - <?= htmlspecialchars($onboarding['position']) ?></small>
                                                    </div>
                                                </td>
                                                <td><?= date('M d, Y', strtotime($onboarding['start_date'])) ?></td>
                                                <td><?= date('M d, Y', strtotime($onboarding['expected_completion'])) ?></td>
                                                <td>
                                                    <div class="progress" style="height: 8px;">
                                                        <div class="progress-bar" role="progressbar" 
                                                             style="width: <?= $onboarding['overall_progress'] ?>%"
                                                             aria-valuenow="<?= $onboarding['overall_progress'] ?>" 
                                                             aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                    <small class="text-muted"><?= number_format($onboarding['overall_progress'], 1) ?>%</small>
                                                </td>
                                                <td>
                                                    <?php
                                                    $stageClass = match($onboarding['onboarding_stage']) {
                                                        'initiated' => 'secondary',
                                                        'documentation' => 'warning',
                                                        'verification' => 'info',
                                                        'training' => 'primary',
                                                        'completed' => 'success',
                                                        default => 'secondary'
                                                    };
                                                    ?>
                                                    <span class="badge bg-<?= $stageClass ?>"><?= ucfirst($onboarding['onboarding_stage']) ?></span>
                                                </td>
                                                <td><?= htmlspecialchars($onboarding['hr_name']) ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-info" 
                                                                onclick="viewOnboardingDetails(<?= $onboarding['id'] ?>)"
                                                                data-bs-toggle="tooltip" title="View Details">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <button class="btn btn-outline-primary" 
                                                                onclick="manageOnboardingTasks(<?= $onboarding['id'] ?>)"
                                                                data-bs-toggle="tooltip" title="Manage Tasks">
                                                            <i class="bi bi-list-task"></i>
                                                        </button>
                                                        <button class="btn btn-outline-success" 
                                                                onclick="viewOnboardingProgress(<?= $onboarding['id'] ?>)"
                                                                data-bs-toggle="tooltip" title="Progress Report">
                                                            <i class="bi bi-graph-up"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar Info -->
            <div class="col-xl-4">
                <!-- Onboarding Checklist Template -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-check2-square text-success"></i> Standard Onboarding Checklist
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="checklist-items">
                            <div class="checklist-item">
                                <i class="bi bi-file-earmark-text text-primary"></i>
                                <span>Document Collection & Verification</span>
                            </div>
                            <div class="checklist-item">
                                <i class="bi bi-laptop text-info"></i>
                                <span>IT Setup & System Access</span>
                            </div>
                            <div class="checklist-item">
                                <i class="bi bi-building text-warning"></i>
                                <span>Office Tour & Facilities</span>
                            </div>
                            <div class="checklist-item">
                                <i class="bi bi-people text-success"></i>
                                <span>Team Introduction</span>
                            </div>
                            <div class="checklist-item">
                                <i class="bi bi-book text-danger"></i>
                                <span>Company Policies Training</span>
                            </div>
                            <div class="checklist-item">
                                <i class="bi bi-tools text-secondary"></i>
                                <span>Job Specific Training</span>
                            </div>
                            <div class="checklist-item">
                                <i class="bi bi-calendar-check text-primary"></i>
                                <span>Probation Review Setup</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-lightning text-warning"></i> Quick Actions
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#startOnboardingModal">
                                <i class="bi bi-plus-circle"></i> Start New Onboarding
                            </button>
                            <button class="btn btn-outline-info" onclick="viewOnboardingReports()">
                                <i class="bi bi-bar-chart"></i> View Reports
                            </button>
                            <button class="btn btn-outline-success" onclick="manageOnboardingTemplates()">
                                <i class="bi bi-file-earmark-plus"></i> Manage Templates
                            </button>
                            <button class="btn btn-outline-warning" onclick="viewOverdueTasks()">
                                <i class="bi bi-exclamation-triangle"></i> Overdue Tasks
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Start Onboarding Modal -->
<div class="modal fade" id="startOnboardingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Start Employee Onboarding</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="start_onboarding">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Employee *</label>
                            <select class="form-select" name="employee_id" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($availableEmployees as $employee): ?>
                                    <option value="<?= $employee['employee_id'] ?>">
                                        <?= htmlspecialchars($employee['name']) ?> - <?= htmlspecialchars($employee['employee_code']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Start Date *</label>
                            <input type="date" class="form-control" name="start_date" 
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Expected Completion *</label>
                            <input type="date" class="form-control" name="expected_completion" 
                                   value="<?= date('Y-m-d', strtotime('+14 days')) ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Assigned HR *</label>
                            <select class="form-select" name="assigned_hr" required>
                                <option value="">Select HR Representative</option>
                                <?php foreach ($hrEmployees as $hr): ?>
                                    <option value="<?= $hr['employee_id'] ?>">
                                        <?= htmlspecialchars($hr['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Assigned Manager</label>
                            <select class="form-select" name="assigned_manager">
                                <option value="">Select Manager (Optional)</option>
                                <?php foreach ($managerEmployees as $manager): ?>
                                    <option value="<?= $manager['employee_id'] ?>">
                                        <?= htmlspecialchars($manager['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Buddy Employee</label>
                            <select class="form-select" name="buddy_employee_id">
                                <option value="">Select Buddy (Optional)</option>
                                <?php foreach ($managerEmployees as $buddy): ?>
                                    <option value="<?= $buddy['employee_id'] ?>">
                                        <?= htmlspecialchars($buddy['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Initial Notes</label>
                            <textarea class="form-control" name="notes" rows="3" 
                                    placeholder="Add any initial notes or special instructions..."></textarea>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <h6><i class="bi bi-info-circle me-2"></i>Default Tasks Will Be Created:</h6>
                        <ul class="mb-0 small">
                            <li>Document Collection & Verification</li>
                            <li>IT Setup & System Access</li>
                            <li>Office Tour & Team Introduction</li>
                            <li>Company Policies Training</li>
                            <li>Job Specific Training</li>
                            <li>Probation Review Setup</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Start Onboarding Process</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.stat-card {
    border: none;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.15);
}

.stat-icon {
    font-size: 2.5rem;
    opacity: 0.8;
}

.bg-gradient-primary {
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
}

.bg-gradient-success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.bg-gradient-warning {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.bg-gradient-info {
    background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
}

.gradient-text {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.animate-fade-in-up {
    animation: fadeInUp 0.6s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.checklist-items {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.checklist-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px;
    background: rgba(0,0,0,0.02);
    border-radius: 8px;
    font-size: 0.9rem;
}

.checklist-item i {
    font-size: 1.1rem;
}
</style>

<script>
// Initialize DataTable
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('onboardingTable')) {
        $('#onboardingTable').DataTable({
            responsive: true,
            pageLength: 25,
            order: [[1, 'desc']],
            columnDefs: [
                { orderable: false, targets: [6] }
            ]
        });
    }
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// View Onboarding Details
function viewOnboardingDetails(onboardingId) {
    window.open('onboarding_details.php?id=' + onboardingId, '_blank');
}

// Manage Onboarding Tasks
function manageOnboardingTasks(onboardingId) {
    window.open('onboarding_tasks.php?id=' + onboardingId, '_blank');
}

// View Onboarding Progress
function viewOnboardingProgress(onboardingId) {
    window.open('onboarding_progress.php?id=' + onboardingId, '_blank');
}

// Export Onboarding Report
function exportOnboardingReport() {
    window.open('api/export_onboarding_report.php', '_blank');
}

// View Onboarding Template
function viewOnboardingTemplate() {
    window.open('onboarding_templates.php', '_blank');
}

// View Onboarding Reports
function viewOnboardingReports() {
    window.open('onboarding_reports.php', '_blank');
}

// Manage Onboarding Templates
function manageOnboardingTemplates() {
    window.open('onboarding_templates.php', '_blank');
}

// View Overdue Tasks
function viewOverdueTasks() {
    window.open('onboarding_overdue_tasks.php', '_blank');
}
</script>

<?php if (!isset($root_path)) 
include '../layouts/footer.php'; ?>
