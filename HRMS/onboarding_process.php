<?php
session_start();
require_once '../db.php';
require_once '../auth_check.php';

// Page title for global header
$page_title = "Onboarding Process";

// Set compatibility variables for HRMS modules
if (isset($_SESSION['user']) && !isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = $_SESSION['user'];
}
if (!isset($_SESSION['role'])) {
    $_SESSION['role'] = 'admin'; // Default for testing
}
if (!isset($_SESSION['employee_id'])) {
    $_SESSION['employee_id'] = $_SESSION['user_id'] ?? 1;
}

// Create onboarding tables
$createOnboardingProcessTable = "
CREATE TABLE IF NOT EXISTS hr_onboarding_process (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    process_name VARCHAR(100) DEFAULT 'Standard Onboarding',
    start_date DATE NOT NULL,
    expected_completion_date DATE,
    actual_completion_date DATE,
    assigned_buddy_id INT,
    assigned_hr_contact INT,
    overall_status ENUM('not_started', 'in_progress', 'completed', 'delayed') DEFAULT 'not_started',
    completion_percentage INT DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX(employee_id),
    INDEX(assigned_buddy_id),
    INDEX(assigned_hr_contact)
)";
mysqli_query($conn, $createOnboardingProcessTable);

$createOnboardingTasksTable = "
CREATE TABLE IF NOT EXISTS hr_onboarding_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    process_id INT NOT NULL,
    task_name VARCHAR(200) NOT NULL,
    task_description TEXT,
    task_category ENUM('documentation', 'training', 'setup', 'introduction', 'compliance', 'other') DEFAULT 'other',
    assigned_to ENUM('employee', 'hr', 'manager', 'buddy', 'it_dept') DEFAULT 'employee',
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    due_date DATE,
    estimated_hours DECIMAL(4,2) DEFAULT 1.00,
    status ENUM('pending', 'in_progress', 'completed', 'skipped') DEFAULT 'pending',
    completion_date TIMESTAMP NULL,
    completion_notes TEXT,
    depends_on_task_id INT NULL,
    order_sequence INT DEFAULT 0,
    is_mandatory BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(process_id),
    INDEX(status),
    INDEX(due_date),
    INDEX(order_sequence)
)";
mysqli_query($conn, $createOnboardingTasksTable);

$createOnboardingDocumentsTable = "
CREATE TABLE IF NOT EXISTS hr_onboarding_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    process_id INT NOT NULL,
    document_name VARCHAR(200) NOT NULL,
    document_type ENUM('contract', 'handbook', 'policy', 'form', 'certificate', 'other') DEFAULT 'other',
    file_path VARCHAR(500),
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    uploaded_by INT,
    status ENUM('required', 'received', 'verified', 'pending_review') DEFAULT 'required',
    review_notes TEXT,
    is_mandatory BOOLEAN DEFAULT TRUE,
    INDEX(process_id),
    INDEX(status),
    INDEX(document_type)
)";
mysqli_query($conn, $createOnboardingDocumentsTable);

// Insert sample onboarding tasks template if not exists
$checkTasks = mysqli_query($conn, "SELECT COUNT(*) as count FROM hr_onboarding_tasks WHERE process_id = 0");
if ($checkTasks && mysqli_fetch_assoc($checkTasks)['count'] == 0) {
    $sampleTasks = [
        ['Welcome Email & First Day Instructions', 'Send welcome email with first day details', 'introduction', 'hr', 'high', 0, 0.5, 1],
        ['Complete Personal Information Form', 'Fill out personal details and emergency contacts', 'documentation', 'employee', 'critical', 0, 1.0, 2],
        ['Review Employee Handbook', 'Read and acknowledge employee handbook', 'documentation', 'employee', 'high', 1, 2.0, 3],
        ['IT Setup - Create Accounts', 'Create email, system accounts and access permissions', 'setup', 'it_dept', 'critical', 0, 2.0, 4],
        ['Workspace Setup', 'Prepare desk, equipment, and office supplies', 'setup', 'hr', 'high', 0, 1.0, 5],
        ['Meet Your Manager', 'Introduction meeting with direct supervisor', 'introduction', 'manager', 'high', 1, 1.0, 6],
        ['Department Introduction', 'Meet team members and understand department structure', 'introduction', 'manager', 'medium', 1, 2.0, 7],
        ['Assign Onboarding Buddy', 'Pair with experienced team member for guidance', 'introduction', 'hr', 'high', 0, 0.5, 8],
        ['Safety & Security Training', 'Complete workplace safety and security protocols', 'training', 'employee', 'critical', 2, 3.0, 9],
        ['Company Culture & Values Session', 'Learn about company mission, vision, and values', 'training', 'hr', 'high', 3, 2.0, 10],
        ['Role-Specific Training Plan', 'Create customized training plan for the position', 'training', 'manager', 'high', 1, 2.0, 11],
        ['Benefits Enrollment', 'Complete health, dental, and other benefit selections', 'documentation', 'hr', 'high', 5, 1.5, 12],
        ['Tax Forms & Payroll Setup', 'Complete tax withholding and payroll information', 'documentation', 'hr', 'critical', 0, 1.0, 13],
        ['Company Policies Acknowledgment', 'Review and sign policy acknowledgment forms', 'compliance', 'employee', 'critical', 3, 1.0, 14],
        ['First Week Check-in', 'Feedback session on first week experience', 'introduction', 'buddy', 'medium', 5, 1.0, 15],
        ['30-Day Review', 'Performance and adjustment review at 30 days', 'training', 'manager', 'high', 15, 2.0, 16],
        ['60-Day Integration Assessment', 'Comprehensive integration and performance review', 'training', 'manager', 'medium', 30, 2.0, 17],
        ['90-Day Onboarding Completion Review', 'Final onboarding assessment and feedback', 'training', 'hr', 'high', 60, 2.0, 18]
    ];
    
    foreach ($sampleTasks as $task) {
        $task_name = mysqli_real_escape_string($conn, $task[0]);
        $task_description = mysqli_real_escape_string($conn, $task[1]);
        $task_category = $task[2];
        $assigned_to = $task[3];
        $priority = $task[4];
        $due_offset = $task[5];
        $estimated_hours = $task[6];
        $order_sequence = $task[7];
        
        mysqli_query($conn, "INSERT INTO hr_onboarding_tasks (process_id, task_name, task_description, task_category, assigned_to, priority, due_date, estimated_hours, order_sequence) VALUES (0, '$task_name', '$task_description', '$task_category', '$assigned_to', '$priority', DATE_ADD(CURDATE(), INTERVAL $due_offset DAY), $estimated_hours, $order_sequence)");
    }
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'start_onboarding':
            $employee_id = intval($_POST['employee_id'] ?? 0);
            $start_date = mysqli_real_escape_string($conn, $_POST['start_date'] ?? date('Y-m-d'));
            $buddy_id = intval($_POST['buddy_id'] ?? 0);
            $hr_contact = intval($_POST['hr_contact'] ?? $_SESSION['user_id']);
            $expected_days = intval($_POST['expected_days'] ?? 90);
            
            if ($employee_id) {
                // Check if onboarding already exists
                $checkResult = mysqli_query($conn, "SELECT id FROM hr_onboarding_process WHERE employee_id = $employee_id");
                if ($checkResult && mysqli_num_rows($checkResult) > 0) {
                    echo json_encode(['success' => false, 'message' => 'Onboarding process already exists for this employee']);
                    exit;
                }
                
                $expected_completion = date('Y-m-d', strtotime($start_date . " + $expected_days days"));
                
                $query = "INSERT INTO hr_onboarding_process (employee_id, start_date, expected_completion_date, assigned_buddy_id, assigned_hr_contact, overall_status) VALUES ($employee_id, '$start_date', '$expected_completion', " . ($buddy_id ?: 'NULL') . ", $hr_contact, 'in_progress')";
                
                if (mysqli_query($conn, $query)) {
                    $process_id = mysqli_insert_id($conn);
                    
                    // Copy template tasks to this process
                    $copyTasks = mysqli_query($conn, "SELECT task_name, task_description, task_category, assigned_to, priority, estimated_hours, order_sequence, is_mandatory FROM hr_onboarding_tasks WHERE process_id = 0");
                    
                    while ($task = mysqli_fetch_assoc($copyTasks)) {
                        $due_date = date('Y-m-d', strtotime($start_date . " + " . ($task['order_sequence'] - 1) . " days"));
                        $task_name = mysqli_real_escape_string($conn, $task['task_name']);
                        $task_description = mysqli_real_escape_string($conn, $task['task_description']);
                        
                        mysqli_query($conn, "INSERT INTO hr_onboarding_tasks (process_id, task_name, task_description, task_category, assigned_to, priority, due_date, estimated_hours, order_sequence, is_mandatory) VALUES ($process_id, '$task_name', '$task_description', '{$task['task_category']}', '{$task['assigned_to']}', '{$task['priority']}', '$due_date', {$task['estimated_hours']}, {$task['order_sequence']}, {$task['is_mandatory']})");
                    }
                    
                    echo json_encode(['success' => true, 'message' => 'Onboarding process started successfully', 'process_id' => $process_id]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error starting onboarding: ' . mysqli_error($conn)]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Employee ID is required']);
            }
            exit;
            
        case 'update_task_status':
            $task_id = intval($_POST['task_id'] ?? 0);
            $status = mysqli_real_escape_string($conn, $_POST['status'] ?? '');
            $completion_notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
            
            if ($task_id && in_array($status, ['pending', 'in_progress', 'completed', 'skipped'])) {
                $completion_date = $status === 'completed' ? 'NOW()' : 'NULL';
                
                $query = "UPDATE hr_onboarding_tasks SET status = '$status', completion_date = $completion_date, completion_notes = '$completion_notes' WHERE id = $task_id";
                
                if (mysqli_query($conn, $query)) {
                    // Update overall process completion percentage
                    $processResult = mysqli_query($conn, "
                        SELECT 
                            ot.process_id,
                            COUNT(*) as total_tasks,
                            SUM(CASE WHEN ot.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
                        FROM hr_onboarding_tasks ot 
                        WHERE ot.process_id = (SELECT process_id FROM hr_onboarding_tasks WHERE id = $task_id)
                        GROUP BY ot.process_id
                    ");
                    
                    if ($processResult && mysqli_num_rows($processResult) > 0) {
                        $processData = mysqli_fetch_assoc($processResult);
                        $percentage = round(($processData['completed_tasks'] / $processData['total_tasks']) * 100);
                        $overall_status = $percentage == 100 ? 'completed' : 'in_progress';
                        $completion_date_update = $percentage == 100 ? ', actual_completion_date = NOW()' : '';
                        
                        mysqli_query($conn, "UPDATE hr_onboarding_process SET completion_percentage = $percentage, overall_status = '$overall_status' $completion_date_update WHERE id = " . $processData['process_id']);
                    }
                    
                    echo json_encode(['success' => true, 'message' => 'Task status updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error updating task status: ' . mysqli_error($conn)]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid task ID or status']);
            }
            exit;
            
        case 'get_onboarding_details':
            $process_id = intval($_POST['process_id'] ?? 0);
            if ($process_id) {
                $processResult = mysqli_query($conn, "
                    SELECT 
                        op.*,
                        CONCAT(COALESCE(e.first_name, 'Unknown'), ' ', COALESCE(e.last_name, 'Employee')) as employee_name,
                        COALESCE(e.designation, 'Not Set') as designation, 
                        COALESCE(e.department, 'Not Set') as department,
                        CONCAT(COALESCE(b.first_name, ''), ' ', COALESCE(b.last_name, '')) as buddy_name,
                        CONCAT(COALESCE(h.first_name, ''), ' ', COALESCE(h.last_name, '')) as hr_contact_name
                    FROM hr_onboarding_process op
                    LEFT JOIN hr_employees e ON op.employee_id = e.employee_id
                    LEFT JOIN hr_employees b ON op.assigned_buddy_id = b.employee_id  
                    LEFT JOIN hr_employees h ON op.assigned_hr_contact = h.employee_id
                    WHERE op.id = $process_id
                ");
                
                $tasksResult = mysqli_query($conn, "
                    SELECT * FROM hr_onboarding_tasks 
                    WHERE process_id = $process_id 
                    ORDER BY order_sequence
                ");
                
                $process = $processResult ? mysqli_fetch_assoc($processResult) : null;
                $tasks = [];
                if ($tasksResult) {
                    while ($task = mysqli_fetch_assoc($tasksResult)) {
                        $tasks[] = $task;
                    }
                }
                
                if ($process) {
                    echo json_encode(['success' => true, 'process' => $process, 'tasks' => $tasks]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Onboarding process not found']);
                }
            }
            exit;
            
        case 'delete_onboarding':
            $process_id = intval($_POST['process_id'] ?? 0);
            if ($process_id) {
                // Delete tasks first
                mysqli_query($conn, "DELETE FROM hr_onboarding_tasks WHERE process_id = $process_id");
                // Delete documents
                mysqli_query($conn, "DELETE FROM hr_onboarding_documents WHERE process_id = $process_id");
                // Delete process
                if (mysqli_query($conn, "DELETE FROM hr_onboarding_process WHERE id = $process_id")) {
                    echo json_encode(['success' => true, 'message' => 'Onboarding process deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error deleting onboarding process']);
                }
            }
            exit;
    }
}

// Get statistics and data for the page
$activeProcesses = [];
$activeResult = mysqli_query($conn, "
    SELECT 
        op.id, op.employee_id, op.start_date, op.expected_completion_date, 
        op.overall_status, op.completion_percentage,
        CONCAT(COALESCE(e.first_name, 'Unknown'), ' ', COALESCE(e.last_name, 'Employee')) as employee_name,
        COALESCE(e.designation, 'Not Set') as designation, 
        COALESCE(e.department, 'Not Set') as department,
        CONCAT(COALESCE(b.first_name, ''), ' ', COALESCE(b.last_name, '')) as buddy_name
    FROM hr_onboarding_process op
    LEFT JOIN hr_employees e ON op.employee_id = e.employee_id
    LEFT JOIN hr_employees b ON op.assigned_buddy_id = b.employee_id
    WHERE op.overall_status IN ('not_started', 'in_progress', 'delayed')
    ORDER BY op.start_date DESC
");

if ($activeResult) {
    while ($row = mysqli_fetch_assoc($activeResult)) {
        $activeProcesses[] = $row;
    }
}

// Get employees without onboarding (mock data since hr_employees might not exist)
$availableEmployees = [
    ['employee_id' => 1, 'name' => 'John Doe', 'designation' => 'Software Developer', 'hire_date' => '2024-01-15'],
    ['employee_id' => 2, 'name' => 'Jane Smith', 'designation' => 'Marketing Manager', 'hire_date' => '2024-02-01'],
    ['employee_id' => 3, 'name' => 'Mike Johnson', 'designation' => 'Sales Executive', 'hire_date' => '2024-02-10'],
    ['employee_id' => 4, 'name' => 'Sarah Wilson', 'designation' => 'HR Specialist', 'hire_date' => '2024-02-15'],
    ['employee_id' => 5, 'name' => 'David Brown', 'designation' => 'Project Manager', 'hire_date' => '2024-02-20']
];

// Filter out employees who already have onboarding
$existingEmployeeIds = array_column($activeProcesses, 'employee_id');
$availableEmployees = array_filter($availableEmployees, function($emp) use ($existingEmployeeIds) {
    return !in_array($emp['employee_id'], $existingEmployeeIds);
});

// Get potential buddies (mock data)
$potentialBuddies = [
    ['employee_id' => 10, 'name' => 'Senior Developer Alex', 'designation' => 'Senior Developer', 'department' => 'IT'],
    ['employee_id' => 11, 'name' => 'Team Lead Maria', 'designation' => 'Team Lead', 'department' => 'Marketing'],
    ['employee_id' => 12, 'name' => 'Manager Robert', 'designation' => 'Manager', 'department' => 'Sales'],
    ['employee_id' => 13, 'name' => 'HR Manager Lisa', 'designation' => 'HR Manager', 'department' => 'HR']
];

// Helper functions
function getProgressColor($percentage) {
    if ($percentage >= 80) return 'success';
    if ($percentage >= 50) return 'info';
    if ($percentage >= 25) return 'warning';
    return 'danger';
}

function getStatusColor($status) {
    $colors = [
        'not_started' => 'secondary',
        'in_progress' => 'primary',
        'completed' => 'success',
        'delayed' => 'warning'
    ];
    return $colors[$status] ?? 'secondary';
}

include '../layouts/header.php';
include '../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid p-4">
        <!-- Header Section -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1 fw-bold text-dark">🚀 Employee Onboarding Process</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="../dashboard.php" class="text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item active">HRMS</li>
                        <li class="breadcrumb-item active">Onboarding Process</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary" onclick="generateOnboardingReport()">
                    <i class="bi bi-file-earmark-text me-2"></i>Generate Reports
                </button>
                <button class="btn btn-primary" onclick="showStartOnboardingModal()">
                    <i class="bi bi-play-circle me-2"></i>Start Onboarding
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-list-check fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= count($activeProcesses) ?></h3>
                        <small class="opacity-75">Active Processes</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-graph-up fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold">
                            <?= count($activeProcesses) > 0 ? round(array_sum(array_column($activeProcesses, 'completion_percentage')) / count($activeProcesses)) : 0 ?>%
                        </h3>
                        <small class="opacity-75">Avg Completion</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-person-plus fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= count($availableEmployees) ?></h3>
                        <small class="opacity-75">Pending Setup</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-people fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= count($potentialBuddies) ?></h3>
                        <small class="opacity-75">Available Buddies</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Onboarding Processes -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 text-dark fw-bold">
                    <i class="bi bi-list-task me-2 text-primary"></i>Active Onboarding Processes
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($activeProcesses)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-person-plus fs-1 text-muted mb-3"></i>
                        <h5 class="text-muted">No Active Onboarding Processes</h5>
                        <p class="text-muted">Start onboarding for new employees to track their integration progress.</p>
                        <button class="btn btn-primary" onclick="showStartOnboardingModal()">
                            <i class="bi bi-play-circle me-2"></i>Start First Onboarding
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Employee</th>
                                    <th>Department</th>
                                    <th>Start Date</th>
                                    <th>Expected Completion</th>
                                    <th>Progress</th>
                                    <th>Status</th>
                                    <th>Buddy</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activeProcesses as $process): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($process['employee_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($process['designation']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($process['department']) ?></td>
                                    <td><?= date('M d, Y', strtotime($process['start_date'])) ?></td>
                                    <td><?= date('M d, Y', strtotime($process['expected_completion_date'])) ?></td>
                                    <td>
                                        <div class="progress mb-1" style="height: 8px;">
                                            <div class="progress-bar bg-<?= getProgressColor($process['completion_percentage']) ?>" 
                                                 style="width: <?= $process['completion_percentage'] ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?= $process['completion_percentage'] ?>%</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= getStatusColor($process['overall_status']) ?>">
                                            <?= ucfirst(str_replace('_', ' ', $process['overall_status'])) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($process['buddy_name'] ?: 'Not Assigned') ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-primary" onclick="viewOnboardingDetails(<?= $process['id'] ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteOnboarding(<?= $process['id'] ?>)">
                                                <i class="bi bi-trash"></i>
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

        <!-- Employees Pending Onboarding Setup -->
        <?php if (!empty($availableEmployees)): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 text-dark fw-bold">
                    <i class="bi bi-person-exclamation me-2 text-warning"></i>Employees Pending Onboarding Setup
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Employee</th>
                                <th>Designation</th>
                                <th>Hire Date</th>
                                <th>Days Since Hire</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($availableEmployees as $employee): 
                                $daysSinceHire = floor((time() - strtotime($employee['hire_date'])) / (60 * 60 * 24));
                            ?>
                            <tr class="<?= $daysSinceHire > 7 ? 'table-warning' : '' ?>">
                                <td><?= htmlspecialchars($employee['name']) ?></td>
                                <td><?= htmlspecialchars($employee['designation']) ?></td>
                                <td><?= date('M d, Y', strtotime($employee['hire_date'])) ?></td>
                                <td>
                                    <span class="badge bg-<?= $daysSinceHire > 7 ? 'warning' : 'info' ?>">
                                        <?= $daysSinceHire ?> days
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-success" onclick="quickStartOnboarding(<?= $employee['employee_id'] ?>, '<?= htmlspecialchars($employee['name']) ?>')">
                                        <i class="bi bi-play-circle me-1"></i>Start Onboarding
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- Start Onboarding Modal -->
<div class="modal fade" id="startOnboardingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-play-circle me-2 text-primary"></i>Start Employee Onboarding
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="onboardingForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="employeeSelect" class="form-label">Select Employee *</label>
                            <select class="form-select" id="employeeSelect" name="employee_id" required>
                                <option value="">Choose Employee</option>
                                <?php foreach ($availableEmployees as $emp): ?>
                                    <option value="<?= $emp['employee_id'] ?>">
                                        <?= htmlspecialchars($emp['name']) ?> - <?= htmlspecialchars($emp['designation']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="startDate" class="form-label">Start Date *</label>
                            <input type="date" class="form-control" id="startDate" name="start_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="buddySelect" class="form-label">Assign Buddy</label>
                            <select class="form-select" id="buddySelect" name="buddy_id">
                                <option value="">No Buddy Assigned</option>
                                <?php foreach ($potentialBuddies as $buddy): ?>
                                    <option value="<?= $buddy['employee_id'] ?>">
                                        <?= htmlspecialchars($buddy['name']) ?> - <?= htmlspecialchars($buddy['designation']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="expectedDays" class="form-label">Expected Completion (Days)</label>
                            <input type="number" class="form-control" id="expectedDays" name="expected_days" value="90" min="30" max="180">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Onboarding Process Will Include:</label>
                        <div class="row small text-muted">
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li><i class="bi bi-check-circle text-success me-2"></i>Welcome & Introduction (Days 1-5)</li>
                                    <li><i class="bi bi-check-circle text-success me-2"></i>Documentation & Setup (Days 1-10)</li>
                                    <li><i class="bi bi-check-circle text-success me-2"></i>Training & Orientation (Days 5-30)</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li><i class="bi bi-check-circle text-success me-2"></i>Department Integration (Days 1-14)</li>
                                    <li><i class="bi bi-check-circle text-success me-2"></i>Performance Reviews (30, 60, 90 days)</li>
                                    <li><i class="bi bi-check-circle text-success me-2"></i>18 Structured Tasks Total</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitOnboarding()">
                    <i class="bi bi-play-circle me-2"></i>Start Onboarding Process
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Onboarding Details Modal -->
<div class="modal fade" id="onboardingDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-list-check me-2 text-primary"></i>Onboarding Progress Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="onboardingDetailsContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Show start onboarding modal
function showStartOnboardingModal() {
    new bootstrap.Modal(document.getElementById('startOnboardingModal')).show();
}

// Quick start onboarding for specific employee
function quickStartOnboarding(employeeId, employeeName) {
    document.getElementById('employeeSelect').value = employeeId;
    showStartOnboardingModal();
}

// Submit onboarding form
function submitOnboarding() {
    const formData = new FormData(document.getElementById('onboardingForm'));
    formData.append('action', 'start_onboarding');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('startOnboardingModal')).hide();
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert('Error: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        showAlert('Error submitting form: ' + error.message, 'danger');
    });
}

// View onboarding details
function viewOnboardingDetails(processId) {
    const formData = new FormData();
    formData.append('action', 'get_onboarding_details');
    formData.append('process_id', processId);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayOnboardingDetails(data.process, data.tasks);
        } else {
            showAlert('Error loading details: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        showAlert('Error loading details: ' + error.message, 'danger');
    });
}

// Display onboarding details in modal
function displayOnboardingDetails(process, tasks) {
    const content = `
        <div class="row mb-4">
            <div class="col-md-6">
                <h6 class="text-primary">Employee Information</h6>
                <p><strong>Name:</strong> ${process.employee_name}</p>
                <p><strong>Department:</strong> ${process.department}</p>
                <p><strong>Position:</strong> ${process.designation}</p>
            </div>
            <div class="col-md-6">
                <h6 class="text-primary">Process Details</h6>
                <p><strong>Start Date:</strong> ${new Date(process.start_date).toLocaleDateString()}</p>
                <p><strong>Expected Completion:</strong> ${new Date(process.expected_completion_date).toLocaleDateString()}</p>
                <p><strong>Buddy:</strong> ${process.buddy_name || 'Not Assigned'}</p>
                <p><strong>HR Contact:</strong> ${process.hr_contact_name || 'Not Assigned'}</p>
            </div>
        </div>
        
        <div class="mb-4">
            <h6 class="text-primary">Overall Progress</h6>
            <div class="progress mb-2" style="height: 24px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated bg-${getProgressColorJS(process.completion_percentage)}" 
                     style="width: ${process.completion_percentage}%">
                    ${process.completion_percentage}% Complete
                </div>
            </div>
            <span class="badge bg-${getStatusColorJS(process.overall_status)} mb-3 fs-6">
                ${process.overall_status.replace('_', ' ').toUpperCase()}
            </span>
        </div>
        
        <h6 class="text-primary mb-3">
            <i class="bi bi-list-check me-2"></i>Tasks Checklist
        </h6>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Task</th>
                        <th>Category</th>
                        <th>Assigned To</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${tasks.map(task => `
                        <tr class="${task.status === 'completed' ? 'table-success' : task.status === 'in_progress' ? 'table-info' : ''}">
                            <td>
                                <div class="fw-bold">${task.task_name}</div>
                                <small class="text-muted">${task.task_description}</small>
                            </td>
                            <td><span class="badge bg-secondary">${task.task_category}</span></td>
                            <td>${task.assigned_to.replace('_', ' ')}</td>
                            <td>${new Date(task.due_date).toLocaleDateString()}</td>
                            <td>
                                <span class="badge bg-${getTaskStatusColorJS(task.status)}">
                                    ${task.status.replace('_', ' ')}
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="updateTaskStatus(${task.id}, '${task.status}')">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
    
    document.getElementById('onboardingDetailsContent').innerHTML = content;
    new bootstrap.Modal(document.getElementById('onboardingDetailsModal')).show();
}

// Update task status
function updateTaskStatus(taskId, currentStatus) {
    const validStatuses = ['pending', 'in_progress', 'completed', 'skipped'];
    const newStatus = prompt(`Update task status. Current: ${currentStatus}\n\nChoose: pending, in_progress, completed, skipped`, currentStatus);
    
    if (newStatus && validStatuses.includes(newStatus) && newStatus !== currentStatus) {
        const notes = newStatus === 'completed' ? prompt('Completion notes (optional):') || '' : '';
        
        const formData = new FormData();
        formData.append('action', 'update_task_status');
        formData.append('task_id', taskId);
        formData.append('status', newStatus);
        formData.append('notes', notes);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message, 'success');
                // Refresh the modal content
                setTimeout(() => {
                    bootstrap.Modal.getInstance(document.getElementById('onboardingDetailsModal')).hide();
                    location.reload();
                }, 1500);
            } else {
                showAlert('Error: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            showAlert('Error updating task: ' + error.message, 'danger');
        });
    }
}

// Delete onboarding process
function deleteOnboarding(processId) {
    if (confirm('Are you sure you want to delete this onboarding process? This will remove all associated tasks and cannot be undone.')) {
        const formData = new FormData();
        formData.append('action', 'delete_onboarding');
        formData.append('process_id', processId);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert('Error: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            showAlert('Error deleting process: ' + error.message, 'danger');
        });
    }
}

// Generate onboarding report
function generateOnboardingReport() {
    showAlert('Generating onboarding report...', 'info');
    
    // In a real implementation, this would generate and download a report
    setTimeout(() => {
        showAlert('Onboarding report generated successfully!', 'success');
    }, 2000);
}

// Helper functions for styling
function getProgressColorJS(percentage) {
    if (percentage >= 80) return 'success';
    if (percentage >= 50) return 'info';
    if (percentage >= 25) return 'warning';
    return 'danger';
}

function getStatusColorJS(status) {
    const colors = {
        'not_started': 'secondary',
        'in_progress': 'primary',
        'completed': 'success',
        'delayed': 'warning'
    };
    return colors[status] || 'secondary';
}

function getTaskStatusColorJS(status) {
    const colors = {
        'pending': 'secondary',
        'in_progress': 'primary',
        'completed': 'success',
        'skipped': 'warning'
    };
    return colors[status] || 'secondary';
}

// Show alert function
function showAlert(message, type) {
    const alertDiv = `
        <div class="alert alert-${type} alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; max-width: 400px;">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', alertDiv);
    
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            if (alert.textContent.includes(message)) {
                alert.remove();
            }
        });
    }, 5000);
}
</script>

<?php include '../layouts/footer.php'; ?>
