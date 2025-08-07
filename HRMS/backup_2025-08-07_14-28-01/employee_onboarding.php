<?php
$page_title = "Employee Onboarding Workflow";

// Include authentication and database
require_once '../auth_check.php';
require_once '../db.php';

// Include layouts
require_once 'hrms_header_simple.php';
require_once 'hrms_sidebar_simple.php';

// Include HRMS UI fix
$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'] ?? 'employee';

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
    FOREIGN KEY (employee_id) REFERENCES hr_employees(employee_id),
    FOREIGN KEY (assigned_buddy_id) REFERENCES hr_employees(employee_id),
    FOREIGN KEY (assigned_hr_contact) REFERENCES hr_employees(employee_id)
)";
$conn->query($createOnboardingProcessTable);

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
    FOREIGN KEY (process_id) REFERENCES hr_onboarding_process(id),
    FOREIGN KEY (depends_on_task_id) REFERENCES hr_onboarding_tasks(id)
)";
$conn->query($createOnboardingTasksTable);

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
    FOREIGN KEY (process_id) REFERENCES hr_onboarding_process(id),
    FOREIGN KEY (uploaded_by) REFERENCES hr_employees(employee_id)
)";
$conn->query($createOnboardingDocumentsTable);

// Insert sample onboarding tasks template
$checkTasks = $conn->query("SELECT COUNT(*) as count FROM hr_onboarding_tasks WHERE process_id = 0");
if ($checkTasks && $checkTasks->fetch_assoc()['count'] == 0) {
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
        $stmt = $conn->prepare("INSERT INTO hr_onboarding_tasks (process_id, task_name, task_description, task_category, assigned_to, priority, due_date, estimated_hours, order_sequence) VALUES (0, ?, ?, ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL ? DAY), ?, ?)");
        $stmt->bind_param("sssssiDI", $task[0], $task[1], $task[2], $task[3], $task[4], $task[5], $task[6], $task[7]);
        $stmt->execute();
    }
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'start_onboarding':
            $employee_id = intval($_POST['employee_id'] ?? 0);
            $start_date = $_POST['start_date'] ?? date('Y-m-d');
            $buddy_id = intval($_POST['buddy_id'] ?? 0);
            $hr_contact = intval($_POST['hr_contact'] ?? $currentUserId);
            $expected_days = intval($_POST['expected_days'] ?? 90);
            
            if ($employee_id) {
                // Check if onboarding already exists
                $checkResult = $conn->query("SELECT id FROM hr_onboarding_process WHERE employee_id = $employee_id");
                if ($checkResult && $checkResult->num_rows > 0) {
                    echo json_encode(['success' => false, 'message' => 'Onboarding process already exists for this employee']);
                    exit;
                }
                
                $expected_completion = date('Y-m-d', strtotime($start_date . " + $expected_days days"));
                
                $stmt = $conn->prepare("INSERT INTO hr_onboarding_process (employee_id, start_date, expected_completion_date, assigned_buddy_id, assigned_hr_contact, overall_status) VALUES (?, ?, ?, ?, ?, 'in_progress')");
                $stmt->bind_param("issii", $employee_id, $start_date, $expected_completion, $buddy_id ?: null, $hr_contact);
                
                if ($stmt->execute()) {
                    $process_id = $conn->insert_id;
                    
                    // Copy template tasks to this process
                    $copyTasks = $conn->query("SELECT task_name, task_description, task_category, assigned_to, priority, estimated_hours, order_sequence, is_mandatory FROM hr_onboarding_tasks WHERE process_id = 0");
                    
                    while ($task = $copyTasks->fetch_assoc()) {
                        $due_date = date('Y-m-d', strtotime($start_date . " + " . ($task['order_sequence'] - 1) . " days"));
                        
                        $taskStmt = $conn->prepare("INSERT INTO hr_onboarding_tasks (process_id, task_name, task_description, task_category, assigned_to, priority, due_date, estimated_hours, order_sequence, is_mandatory) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $taskStmt->bind_param("isssssdiil", $process_id, $task['task_name'], $task['task_description'], $task['task_category'], $task['assigned_to'], $task['priority'], $due_date, $task['estimated_hours'], $task['order_sequence'], $task['is_mandatory']);
                        $taskStmt->execute();
                    }
                    
                    echo json_encode(['success' => true, 'message' => 'Onboarding process started successfully', 'process_id' => $process_id]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error starting onboarding: ' . $conn->error]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Employee ID is required']);
            }
            exit;
            
        case 'update_task_status':
            $task_id = intval($_POST['task_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            $completion_notes = $conn->real_escape_string($_POST['notes'] ?? '');
            
            if ($task_id && in_array($status, ['pending', 'in_progress', 'completed', 'skipped'])) {
                $completion_date = $status === 'completed' ? 'NOW()' : 'NULL';
                
                $stmt = $conn->prepare("UPDATE hr_onboarding_tasks SET status = ?, completion_date = $completion_date, completion_notes = ? WHERE id = ?");
                $stmt->bind_param("ssi", $status, $completion_notes, $task_id);
                
                if ($stmt->execute()) {
                    // Update overall process completion percentage
                    $processResult = $conn->query("
                        SELECT 
                            ot.process_id,
                            COUNT(*) as total_tasks,
                            SUM(CASE WHEN ot.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
                        FROM hr_onboarding_tasks ot 
                        WHERE ot.process_id = (SELECT process_id FROM hr_onboarding_tasks WHERE id = $task_id)
                        GROUP BY ot.process_id
                    ");
                    
                    if ($processResult && $processResult->num_rows > 0) {
                        $processData = $processResult->fetch_assoc();
                        $percentage = round(($processData['completed_tasks'] / $processData['total_tasks']) * 100);
                        $overall_status = $percentage == 100 ? 'completed' : 'in_progress';
                        $completion_date = $percentage == 100 ? ', actual_completion_date = NOW()' : '';
                        
                        $conn->query("UPDATE hr_onboarding_process SET completion_percentage = $percentage, overall_status = '$overall_status' $completion_date WHERE id = " . $processData['process_id']);
                    }
                    
                    echo json_encode(['success' => true, 'message' => 'Task status updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error updating task status']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid task ID or status']);
            }
            exit;
            
        case 'get_onboarding_details':
            $process_id = intval($_POST['process_id'] ?? 0);
            if ($process_id) {
                $processResult = $conn->query("
                    SELECT 
                        op.*,
                        CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                        e.designation, e.department,
                        CONCAT(b.first_name, ' ', b.last_name) as buddy_name,
                        CONCAT(h.first_name, ' ', h.last_name) as hr_contact_name
                    FROM hr_onboarding_process op
                    JOIN hr_employees e ON op.employee_id = e.employee_id
                    LEFT JOIN hr_employees b ON op.assigned_buddy_id = b.employee_id  
                    LEFT JOIN hr_employees h ON op.assigned_hr_contact = h.employee_id
                    WHERE op.id = $process_id
                ");
                
                $tasksResult = $conn->query("
                    SELECT * FROM hr_onboarding_tasks 
                    WHERE process_id = $process_id 
                    ORDER BY order_sequence
                ");
                
                $process = $processResult ? $processResult->fetch_assoc() : null;
                $tasks = [];
                if ($tasksResult) {
                    while ($task = $tasksResult->fetch_assoc()) {
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
    }
}

// Get active onboarding processes
$activeProcesses = [];
$activeResult = $conn->query("
    SELECT 
        op.id, op.employee_id, op.start_date, op.expected_completion_date, 
        op.overall_status, op.completion_percentage,
        CONCAT(e.first_name, ' ', e.last_name) as employee_name,
        e.designation, e.department,
        CONCAT(b.first_name, ' ', b.last_name) as buddy_name
    FROM hr_onboarding_process op
    JOIN hr_employees e ON op.employee_id = e.employee_id
    LEFT JOIN hr_employees b ON op.assigned_buddy_id = b.employee_id
    WHERE op.overall_status IN ('not_started', 'in_progress', 'delayed')
    ORDER BY op.start_date DESC
");

if ($activeResult) {
    while ($row = $activeResult->fetch_assoc()) {
        $activeProcesses[] = $row;
    }
}

// Get employees without onboarding
$availableEmployees = [];
$availableResult = $conn->query("
    SELECT e.employee_id, CONCAT(e.first_name, ' ', e.last_name) as name, e.designation, e.hire_date
    FROM hr_employees e
    WHERE e.employee_id NOT IN (SELECT employee_id FROM hr_onboarding_process)
    AND e.status = 'active'
    ORDER BY e.hire_date DESC
");

if ($availableResult) {
    while ($row = $availableResult->fetch_assoc()) {
        $availableEmployees[] = $row;
    }
}

// Get potential buddies
$potentialBuddies = [];
$buddyResult = $conn->query("
    SELECT employee_id, CONCAT(first_name, ' ', last_name) as name, designation, department
    FROM hr_employees
    WHERE status = 'active' AND (designation LIKE '%Senior%' OR designation LIKE '%Lead%' OR YEAR(hire_date) <= YEAR(CURDATE()) - 1)
    ORDER BY first_name
");

if ($buddyResult) {
    while ($row = $buddyResult->fetch_assoc()) {
        $potentialBuddies[] = $row;
    }
}
?>

<div class="content-wrapper">
    <div class="container-fluid">
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-user-plus mr-2"></i>Employee Onboarding Workflow
            </h1>
            <button class="btn btn-primary" onclick="showStartOnboardingModal()">
                <i class="fas fa-play mr-1"></i>Start Onboarding
            </button>
        </div>

        <!-- Onboarding Statistics -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Active Processes</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($activeProcesses); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-tasks fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Avg Completion</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo count($activeProcesses) > 0 ? round(array_sum(array_column($activeProcesses, 'completion_percentage')) / count($activeProcesses)) : 0; ?>%
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-chart-pie fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Pending Setup</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($availableEmployees); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-user-clock fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Available Buddies</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($potentialBuddies); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-users fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Onboarding Processes -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Active Onboarding Processes</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($activeProcesses)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-user-plus fa-3x text-muted mb-3"></i>
                                <h5>No Active Onboarding Processes</h5>
                                <p class="text-muted">Start onboarding for new employees to track their integration progress.</p>
                                <button class="btn btn-primary" onclick="showStartOnboardingModal()">Start First Onboarding</button>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
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
                                                <div class="font-weight-bold"><?php echo htmlspecialchars($process['employee_name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($process['designation']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($process['department']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($process['start_date'])); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($process['expected_completion_date'])); ?></td>
                                            <td>
                                                <div class="progress" style="height: 6px;">
                                                    <div class="progress-bar bg-<?php echo getProgressColor($process['completion_percentage']); ?>" 
                                                         style="width: <?php echo $process['completion_percentage']; ?>%"></div>
                                                </div>
                                                <small class="text-muted"><?php echo $process['completion_percentage']; ?>%</small>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo getStatusColor($process['overall_status']); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $process['overall_status'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($process['buddy_name'] ?: 'Not Assigned'); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-primary" onclick="viewOnboardingDetails(<?php echo $process['id']; ?>)">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
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
        </div>

        <!-- Employees Pending Onboarding Setup -->
        <?php if (!empty($availableEmployees)): ?>
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-warning">Employees Pending Onboarding Setup</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
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
                                    <tr class="<?php echo $daysSinceHire > 7 ? 'table-warning' : ''; ?>">
                                        <td><?php echo htmlspecialchars($employee['name']); ?></td>
                                        <td><?php echo htmlspecialchars($employee['designation']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($employee['hire_date'])); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $daysSinceHire > 7 ? 'warning' : 'info'; ?>">
                                                <?php echo $daysSinceHire; ?> days
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-success" onclick="quickStartOnboarding(<?php echo $employee['employee_id']; ?>, '<?php echo htmlspecialchars($employee['name']); ?>')">
                                                <i class="fas fa-play mr-1"></i>Start Onboarding
                                            </button>
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
        <?php endif; ?>

    </div>
</div>

<!-- Start Onboarding Modal -->
<div class="modal fade" id="startOnboardingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Start Employee Onboarding</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="onboardingForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="employeeSelect" class="form-label">Select Employee *</label>
                            <select class="form-control" id="employeeSelect" name="employee_id" required>
                                <option value="">Choose Employee</option>
                                <?php foreach ($availableEmployees as $emp): ?>
                                    <option value="<?php echo $emp['employee_id']; ?>">
                                        <?php echo htmlspecialchars($emp['name']); ?> - <?php echo htmlspecialchars($emp['designation']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="startDate" class="form-label">Start Date *</label>
                            <input type="date" class="form-control" id="startDate" name="start_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="buddySelect" class="form-label">Assign Buddy</label>
                            <select class="form-control" id="buddySelect" name="buddy_id">
                                <option value="">No Buddy Assigned</option>
                                <?php foreach ($potentialBuddies as $buddy): ?>
                                    <option value="<?php echo $buddy['employee_id']; ?>">
                                        <?php echo htmlspecialchars($buddy['name']); ?> - <?php echo htmlspecialchars($buddy['designation']); ?>
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
                        <label class="form-label">Onboarding Process Will Include:</label>
                        <div class="row small text-muted">
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success mr-2"></i>Welcome & Introduction (Days 1-5)</li>
                                    <li><i class="fas fa-check text-success mr-2"></i>Documentation & Setup (Days 1-10)</li>
                                    <li><i class="fas fa-check text-success mr-2"></i>Training & Orientation (Days 5-30)</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success mr-2"></i>Department Integration (Days 1-14)</li>
                                    <li><i class="fas fa-check text-success mr-2"></i>Performance Reviews (30, 60, 90 days)</li>
                                    <li><i class="fas fa-check text-success mr-2"></i>18 Structured Tasks Total</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitOnboarding()">Start Onboarding Process</button>
            </div>
        </div>
    </div>
</div>

<!-- Onboarding Details Modal -->
<div class="modal fade" id="onboardingDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Onboarding Progress Details</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="onboardingDetailsContent">
                <!-- Dynamic content -->
            </div>
        </div>
    </div>
</div>

<script>
function showStartOnboardingModal() {
    new bootstrap.Modal(document.getElementById('startOnboardingModal')).show();
}

function quickStartOnboarding(employeeId, employeeName) {
    document.getElementById('employeeSelect').value = employeeId;
    showStartOnboardingModal();
}

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
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

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
            alert('Error loading details: ' + data.message);
        }
    });
}

function displayOnboardingDetails(process, tasks) {
    const content = `
        <div class="row mb-4">
            <div class="col-md-6">
                <h6>Employee Information</h6>
                <p><strong>Name:</strong> ${process.employee_name}</p>
                <p><strong>Department:</strong> ${process.department}</p>
                <p><strong>Position:</strong> ${process.designation}</p>
            </div>
            <div class="col-md-6">
                <h6>Process Details</h6>
                <p><strong>Start Date:</strong> ${new Date(process.start_date).toLocaleDateString()}</p>
                <p><strong>Expected Completion:</strong> ${new Date(process.expected_completion_date).toLocaleDateString()}</p>
                <p><strong>Buddy:</strong> ${process.buddy_name || 'Not Assigned'}</p>
                <p><strong>HR Contact:</strong> ${process.hr_contact_name}</p>
            </div>
        </div>
        
        <div class="mb-4">
            <h6>Overall Progress</h6>
            <div class="progress mb-2" style="height: 20px;">
                <div class="progress-bar" style="width: ${process.completion_percentage}%">
                    ${process.completion_percentage}% Complete
                </div>
            </div>
            <span class="badge badge-${getStatusColorJS(process.overall_status)} mb-3">
                ${process.overall_status.replace('_', ' ').toUpperCase()}
            </span>
        </div>
        
        <h6>Tasks Checklist</h6>
        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead>
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
                                <div class="font-weight-bold">${task.task_name}</div>
                                <small class="text-muted">${task.task_description}</small>
                            </td>
                            <td><span class="badge badge-secondary">${task.task_category}</span></td>
                            <td>${task.assigned_to.replace('_', ' ')}</td>
                            <td>${new Date(task.due_date).toLocaleDateString()}</td>
                            <td>
                                <span class="badge badge-${getTaskStatusColorJS(task.status)}">
                                    ${task.status}
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="updateTaskStatus(${task.id}, '${task.status}')">
                                    <i class="fas fa-edit"></i>
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

function updateTaskStatus(taskId, currentStatus) {
    const newStatus = prompt('Update task status (pending/in_progress/completed/skipped):', currentStatus);
    if (newStatus && ['pending', 'in_progress', 'completed', 'skipped'].includes(newStatus)) {
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
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
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
</script>

<style>
.border-left-primary { border-left: 0.25rem solid #4e73df !important; }
.border-left-success { border-left: 0.25rem solid #1cc88a !important; }
.border-left-info { border-left: 0.25rem solid #36b9cc !important; }
.border-left-warning { border-left: 0.25rem solid #f6c23e !important; }

.text-gray-800 { color: #5a5c69 !important; }
.text-gray-300 { color: #dddfeb !important; }

.card {
    border: none;
    border-radius: 0.35rem;
}

.card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white !important;
}

.table-warning {
    background-color: rgba(255, 193, 7, 0.1);
}

.progress {
    border-radius: 4px;
}

.modal-xl .table-responsive {
    max-height: 400px;
    overflow-y: auto;
}
</style>

<?php 
require_once 'hrms_footer_simple.php';

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

require_once 'hrms_footer_simple.php';
?>