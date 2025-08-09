<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Project Management System';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'create_project':
            $name = mysqli_real_escape_string($conn, $_POST['project_name']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
            $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
            $budget = floatval($_POST['budget'] ?? 0);
            $priority = mysqli_real_escape_string($conn, $_POST['priority']);
            $project_manager = intval($_POST['project_manager']);
            $client_id = intval($_POST['client_id'] ?? 0) ?: null;
            $category = mysqli_real_escape_string($conn, $_POST['category']);
            $status = mysqli_real_escape_string($conn, $_POST['status'] ?? 'planning');
            
            // Generate project code
            $project_code = 'PRJ-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $query = "INSERT INTO projects (project_code, name, description, start_date, end_date, 
                      budget, priority, project_manager_id, client_id, category, status, created_by) 
                      VALUES ('$project_code', '$name', '$description', '$start_date', '$end_date', 
                      $budget, '$priority', $project_manager, " . ($client_id ? $client_id : 'NULL') . ", 
                      '$category', '$status', '{$_SESSION['admin']}')";
            
            if (mysqli_query($conn, $query)) {
                $project_id = mysqli_insert_id($conn);
                
                // Add team members if provided
                if (isset($_POST['team_members']) && is_array($_POST['team_members'])) {
                    foreach ($_POST['team_members'] as $member_id) {
                        $member_id = intval($member_id);
                        $role = mysqli_real_escape_string($conn, $_POST['member_roles'][$member_id] ?? 'team_member');
                        
                        $team_query = "INSERT INTO project_team (project_id, employee_id, role, assigned_date) 
                                      VALUES ($project_id, $member_id, '$role', NOW())";
                        mysqli_query($conn, $team_query);
                    }
                }
                
                echo json_encode(['success' => true, 'message' => 'Project created successfully!', 'project_code' => $project_code]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error creating project: ' . $conn->error]);
            }
            exit;
            
        case 'create_task':
            $project_id = intval($_POST['project_id']);
            $title = mysqli_real_escape_string($conn, $_POST['task_title']);
            $description = mysqli_real_escape_string($conn, $_POST['task_description']);
            $assigned_to = intval($_POST['assigned_to']);
            $priority = mysqli_real_escape_string($conn, $_POST['priority']);
            $due_date = mysqli_real_escape_string($conn, $_POST['due_date']);
            $estimated_hours = floatval($_POST['estimated_hours'] ?? 0);
            $category = mysqli_real_escape_string($conn, $_POST['task_category']);
            $dependencies = $_POST['dependencies'] ?? [];
            
            // Generate task code
            $task_code = 'TSK-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            
            $query = "INSERT INTO project_tasks (project_id, task_code, title, description, assigned_to, 
                      priority, due_date, estimated_hours, category, status, created_by, created_date) 
                      VALUES ($project_id, '$task_code', '$title', '$description', $assigned_to, 
                      '$priority', '$due_date', $estimated_hours, '$category', 'todo', '{$_SESSION['admin']}', NOW())";
            
            if (mysqli_query($conn, $query)) {
                $task_id = mysqli_insert_id($conn);
                
                // Add task dependencies
                if (!empty($dependencies) && is_array($dependencies)) {
                    foreach ($dependencies as $dep_task_id) {
                        $dep_task_id = intval($dep_task_id);
                        $dep_query = "INSERT INTO task_dependencies (task_id, depends_on_task_id) 
                                     VALUES ($task_id, $dep_task_id)";
                        mysqli_query($conn, $dep_query);
                    }
                }
                
                echo json_encode(['success' => true, 'message' => 'Task created successfully!', 'task_code' => $task_code]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error creating task: ' . $conn->error]);
            }
            exit;
            
        case 'update_task_status':
            $task_id = intval($_POST['task_id']);
            $status = mysqli_real_escape_string($conn, $_POST['status']);
            $progress = intval($_POST['progress'] ?? 0);
            $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
            
            $query = "UPDATE project_tasks SET status = '$status', progress = $progress, 
                      last_updated = NOW(), updated_by = '{$_SESSION['admin']}'";
            
            if ($status === 'completed') {
                $query .= ", completed_date = NOW()";
            }
            
            $query .= " WHERE id = $task_id";
            
            if (mysqli_query($conn, $query)) {
                // Log activity
                $activity_query = "INSERT INTO project_activities (project_id, activity_type, description, 
                                  created_by, activity_date) 
                                  SELECT project_id, 'task_update', 
                                  CONCAT('Task \"', title, '\" status changed to ', '$status'), 
                                  '{$_SESSION['admin']}', NOW() FROM project_tasks WHERE id = $task_id";
                mysqli_query($conn, $activity_query);
                
                echo json_encode(['success' => true, 'message' => 'Task status updated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating task: ' . $conn->error]);
            }
            exit;
            
        case 'log_time':
            $task_id = intval($_POST['task_id']);
            $hours = floatval($_POST['hours']);
            $description = mysqli_real_escape_string($conn, $_POST['time_description']);
            $date_logged = mysqli_real_escape_string($conn, $_POST['date_logged']);
            
            $query = "INSERT INTO time_logs (task_id, employee_id, hours, description, date_logged, logged_by) 
                      VALUES ($task_id, " . ($_POST['employee_id'] ?? $_SESSION['user_id'] ?? 1) . ", 
                      $hours, '$description', '$date_logged', '{$_SESSION['admin']}')";
            
            if (mysqli_query($conn, $query)) {
                // Update task actual hours
                $update_query = "UPDATE project_tasks SET actual_hours = 
                                (SELECT SUM(hours) FROM time_logs WHERE task_id = $task_id) 
                                WHERE id = $task_id";
                mysqli_query($conn, $update_query);
                
                echo json_encode(['success' => true, 'message' => 'Time logged successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error logging time: ' . $conn->error]);
            }
            exit;
            
        case 'update_project':
            $project_id = intval($_POST['project_id']);
            $name = mysqli_real_escape_string($conn, $_POST['project_name']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
            $budget = floatval($_POST['budget']);
            $status = mysqli_real_escape_string($conn, $_POST['status']);
            
            $query = "UPDATE projects SET name = '$name', description = '$description', 
                      end_date = '$end_date', budget = $budget, status = '$status', 
                      updated_date = NOW() WHERE id = $project_id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Project updated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating project: ' . $conn->error]);
            }
            exit;
            
        case 'delete_project':
            $project_id = intval($_POST['project_id']);
            
            // Check if project has tasks
            $check_query = "SELECT COUNT(*) as task_count FROM project_tasks WHERE project_id = $project_id";
            $result = mysqli_query($conn, $check_query);
            $task_count = $result->fetch_assoc()['task_count'];
            
            if ($task_count > 0) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete project with existing tasks. Please delete all tasks first.']);
            } else {
                $query = "UPDATE projects SET status = 'archived' WHERE id = $project_id";
                
                if (mysqli_query($conn, $query)) {
                    echo json_encode(['success' => true, 'message' => 'Project archived successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error archiving project: ' . $conn->error]);
                }
            }
            exit;
    }
}

// Create project management tables
$tables = [
    "CREATE TABLE IF NOT EXISTS projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_code VARCHAR(50) UNIQUE NOT NULL,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        start_date DATE,
        end_date DATE,
        budget DECIMAL(12,2) DEFAULT 0,
        priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
        project_manager_id INT,
        client_id INT NULL,
        category VARCHAR(100),
        status ENUM('planning', 'active', 'on_hold', 'completed', 'cancelled', 'archived') DEFAULT 'planning',
        progress DECIMAL(5,2) DEFAULT 0,
        created_by VARCHAR(100),
        created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_manager (project_manager_id),
        INDEX idx_client (client_id)
    )",
    
    "CREATE TABLE IF NOT EXISTS project_team (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        employee_id INT NOT NULL,
        role ENUM('project_manager', 'team_lead', 'developer', 'designer', 'tester', 'analyst', 'team_member') DEFAULT 'team_member',
        assigned_date DATE,
        removed_date DATE NULL,
        is_active BOOLEAN DEFAULT TRUE,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        UNIQUE KEY unique_active_assignment (project_id, employee_id, is_active)
    )",
    
    "CREATE TABLE IF NOT EXISTS project_tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        task_code VARCHAR(50) UNIQUE NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        assigned_to INT NOT NULL,
        priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
        status ENUM('todo', 'in_progress', 'review', 'testing', 'completed', 'blocked') DEFAULT 'todo',
        progress DECIMAL(5,2) DEFAULT 0,
        due_date DATE,
        estimated_hours DECIMAL(6,2) DEFAULT 0,
        actual_hours DECIMAL(6,2) DEFAULT 0,
        category VARCHAR(100),
        created_by VARCHAR(100),
        created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        completed_date TIMESTAMP NULL,
        updated_by VARCHAR(100),
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        INDEX idx_assigned (assigned_to),
        INDEX idx_status (status),
        INDEX idx_due_date (due_date)
    )",
    
    "CREATE TABLE IF NOT EXISTS task_dependencies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        depends_on_task_id INT NOT NULL,
        created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (task_id) REFERENCES project_tasks(id) ON DELETE CASCADE,
        FOREIGN KEY (depends_on_task_id) REFERENCES project_tasks(id) ON DELETE CASCADE,
        UNIQUE KEY unique_dependency (task_id, depends_on_task_id)
    )",
    
    "CREATE TABLE IF NOT EXISTS time_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        employee_id INT NOT NULL,
        hours DECIMAL(6,2) NOT NULL,
        description TEXT,
        date_logged DATE NOT NULL,
        logged_by VARCHAR(100),
        created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (task_id) REFERENCES project_tasks(id) ON DELETE CASCADE,
        INDEX idx_task (task_id),
        INDEX idx_employee (employee_id),
        INDEX idx_date (date_logged)
    )",
    
    "CREATE TABLE IF NOT EXISTS project_activities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        activity_type ENUM('project_created', 'task_created', 'task_update', 'status_change', 'team_change', 'comment', 'milestone') DEFAULT 'comment',
        description TEXT NOT NULL,
        created_by VARCHAR(100),
        activity_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        INDEX idx_project (project_id),
        INDEX idx_date (activity_date)
    )",
    
    "CREATE TABLE IF NOT EXISTS project_milestones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        milestone_name VARCHAR(255) NOT NULL,
        description TEXT,
        target_date DATE,
        completion_date DATE NULL,
        status ENUM('pending', 'in_progress', 'completed', 'overdue') DEFAULT 'pending',
        created_by VARCHAR(100),
        created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
    )"
];

foreach ($tables as $table) {
    mysqli_query($conn, $table);
}

// Get project statistics
$stats = [
    'total_projects' => 0,
    'active_projects' => 0,
    'completed_projects' => 0,
    'overdue_tasks' => 0,
    'total_tasks' => 0,
    'completed_tasks' => 0
];

$project_stats = mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_projects,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_projects,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_projects
    FROM projects WHERE status != 'archived'
");

if ($project_stats && $row = $project_stats->fetch_assoc()) {
    $stats = array_merge($stats, $row);
}

$task_stats = mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_tasks,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
        SUM(CASE WHEN due_date < CURDATE() AND status NOT IN ('completed') THEN 1 ELSE 0 END) as overdue_tasks
    FROM project_tasks pt
    INNER JOIN projects p ON pt.project_id = p.id
    WHERE p.status != 'archived'
");

if ($task_stats && $row = $task_stats->fetch_assoc()) {
    $stats = array_merge($stats, $row);
}

// Get projects for display
$projects = mysqli_query($conn, "
    SELECT p.*, 
           CONCAT(e.name) as manager_name,
           c.customer_name as client_name,
           (SELECT COUNT(*) FROM project_tasks WHERE project_id = p.id) as task_count,
           (SELECT COUNT(*) FROM project_tasks WHERE project_id = p.id AND status = 'completed') as completed_tasks
    FROM projects p
    LEFT JOIN employees e ON p.project_manager_id = e.employee_id
    LEFT JOIN customers c ON p.client_id = c.id
    WHERE p.status != 'archived'
    ORDER BY p.created_date DESC
    LIMIT 50
");

// Get recent tasks
$recent_tasks = mysqli_query($conn, "
    SELECT pt.*, p.name as project_name, e.name as assignee_name, p.project_code
    FROM project_tasks pt
    INNER JOIN projects p ON pt.project_id = p.id
    LEFT JOIN employees e ON pt.assigned_to = e.employee_id
    WHERE p.status != 'archived'
    ORDER BY pt.created_date DESC
    LIMIT 20
");

// Get employees for dropdowns
$employees = mysqli_query($conn, "SELECT employee_id, name, email FROM employees WHERE status = 'active' ORDER BY name");

// Get customers for client dropdown
$customers = mysqli_query($conn, "SELECT id, customer_name FROM customers ORDER BY customer_name");

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">🚀 Project Management System</h1>
                <p class="text-muted">Comprehensive project and task management solution</p>
            </div>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createProjectModal">
                    <i class="bi bi-plus-circle me-1"></i>New Project
                </button>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                    <i class="bi bi-check-square me-1"></i>New Task
                </button>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-three-dots me-1"></i>More
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="showReports()"><i class="bi bi-graph-up me-2"></i>Reports</a></li>
                        <li><a class="dropdown-item" href="#" onclick="showTimesheets()"><i class="bi bi-clock me-2"></i>Timesheets</a></li>
                        <li><a class="dropdown-item" href="#" onclick="exportProjects()"><i class="bi bi-download me-2"></i>Export</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Statistics Dashboard -->
        <div class="row g-3 mb-4">
            <div class="col-xl-2 col-lg-4 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-folder fs-2"></i>
                        </div>
                        <h4 class="mb-1 fw-bold"><?= $stats['total_projects'] ?></h4>
                        <small class="opacity-75">Total Projects</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-lg-4 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-play-circle fs-2"></i>
                        </div>
                        <h4 class="mb-1 fw-bold"><?= $stats['active_projects'] ?></h4>
                        <small class="opacity-75">Active Projects</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-lg-4 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-check-circle fs-2"></i>
                        </div>
                        <h4 class="mb-1 fw-bold"><?= $stats['completed_projects'] ?></h4>
                        <small class="opacity-75">Completed</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-lg-4 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-list-task fs-2"></i>
                        </div>
                        <h4 class="mb-1 fw-bold"><?= $stats['total_tasks'] ?></h4>
                        <small class="opacity-75">Total Tasks</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-lg-4 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-check-all fs-2"></i>
                        </div>
                        <h4 class="mb-1 fw-bold"><?= $stats['completed_tasks'] ?></h4>
                        <small class="opacity-75">Completed Tasks</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-lg-4 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #ff6b6b 0%, #ffa500 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-exclamation-triangle fs-2"></i>
                        </div>
                        <h4 class="mb-1 fw-bold"><?= $stats['overdue_tasks'] ?></h4>
                        <small class="opacity-75">Overdue Tasks</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Tabs -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <ul class="nav nav-tabs card-header-tabs" id="projectTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#projects" type="button">
                                    <i class="bi bi-folder me-2"></i>Projects
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tasks" type="button">
                                    <i class="bi bi-list-task me-2"></i>Tasks
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#gantt-chart" type="button">
                                    <i class="bi bi-bar-chart me-2"></i>Timeline
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#team-view" type="button">
                                    <i class="bi bi-people me-2"></i>Team View
                                </button>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="card-body">
                        <div class="tab-content">
                            <!-- Projects Tab -->
                            <div class="tab-pane fade show active" id="projects" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="projectsTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Project</th>
                                                <th>Manager</th>
                                                <th>Client</th>
                                                <th>Status</th>
                                                <th>Progress</th>
                                                <th>Tasks</th>
                                                <th>Budget</th>
                                                <th>Due Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($project = $projects->fetch_assoc()): 
                                                $progress = $project['task_count'] > 0 ? 
                                                    round(($project['completed_tasks'] / $project['task_count']) * 100, 1) : 0;
                                            ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <div class="fw-bold"><?= htmlspecialchars($project['name']) ?></div>
                                                        <small class="text-muted"><?= $project['project_code'] ?></small>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($project['manager_name'] ?? 'Unassigned') ?></td>
                                                <td><?= htmlspecialchars($project['client_name'] ?? 'Internal') ?></td>
                                                <td>
                                                    <span class="badge bg-<?= 
                                                        $project['status'] === 'active' ? 'success' : 
                                                        ($project['status'] === 'completed' ? 'primary' : 
                                                        ($project['status'] === 'on_hold' ? 'warning' : 'secondary')) 
                                                    ?>">
                                                        <?= ucfirst(str_replace('_', ' ', $project['status'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="progress flex-grow-1 me-2" style="height: 6px;">
                                                            <div class="progress-bar" style="width: <?= $progress ?>%"></div>
                                                        </div>
                                                        <small><?= $progress ?>%</small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?= $project['completed_tasks'] ?>/<?= $project['task_count'] ?></span>
                                                </td>
                                                <td>₹<?= number_format($project['budget'], 0) ?></td>
                                                <td>
                                                    <?php if ($project['end_date']): ?>
                                                        <span class="<?= strtotime($project['end_date']) < time() && $project['status'] !== 'completed' ? 'text-danger' : '' ?>">
                                                            <?= date('M j, Y', strtotime($project['end_date'])) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not set</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-outline-primary view-project" 
                                                                data-id="<?= $project['id'] ?>">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-warning edit-project" 
                                                                data-project='<?= json_encode($project) ?>'>
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger delete-project" 
                                                                data-id="<?= $project['id'] ?>"
                                                                data-name="<?= htmlspecialchars($project['name']) ?>">
                                                            <i class="bi bi-archive"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Tasks Tab -->
                            <div class="tab-pane fade" id="tasks" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="tasksTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Task</th>
                                                <th>Project</th>
                                                <th>Assignee</th>
                                                <th>Priority</th>
                                                <th>Status</th>
                                                <th>Progress</th>
                                                <th>Due Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($task = $recent_tasks->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <div class="fw-bold"><?= htmlspecialchars($task['title']) ?></div>
                                                        <small class="text-muted"><?= $task['task_code'] ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?= htmlspecialchars($task['project_code']) ?></span>
                                                </td>
                                                <td><?= htmlspecialchars($task['assignee_name'] ?? 'Unassigned') ?></td>
                                                <td>
                                                    <span class="badge bg-<?= 
                                                        $task['priority'] === 'urgent' ? 'danger' : 
                                                        ($task['priority'] === 'high' ? 'warning' : 
                                                        ($task['priority'] === 'medium' ? 'info' : 'secondary'))
                                                    ?>">
                                                        <?= ucfirst($task['priority']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= 
                                                        $task['status'] === 'completed' ? 'success' : 
                                                        ($task['status'] === 'in_progress' ? 'primary' : 
                                                        ($task['status'] === 'blocked' ? 'danger' : 'secondary'))
                                                    ?>">
                                                        <?= str_replace('_', ' ', ucfirst($task['status'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="progress flex-grow-1 me-2" style="height: 6px;">
                                                            <div class="progress-bar" style="width: <?= $task['progress'] ?>%"></div>
                                                        </div>
                                                        <small><?= $task['progress'] ?>%</small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($task['due_date']): ?>
                                                        <span class="<?= strtotime($task['due_date']) < time() && $task['status'] !== 'completed' ? 'text-danger' : '' ?>">
                                                            <?= date('M j', strtotime($task['due_date'])) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-outline-primary view-task" 
                                                                data-id="<?= $task['id'] ?>">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-success update-task-status" 
                                                                data-id="<?= $task['id'] ?>">
                                                            <i class="bi bi-check"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Timeline Tab -->
                            <div class="tab-pane fade" id="gantt-chart" role="tabpanel">
                                <div class="text-center py-5">
                                    <i class="bi bi-bar-chart fs-1 text-muted"></i>
                                    <h5 class="text-muted mt-3">Project Timeline View</h5>
                                    <p class="text-muted">Interactive Gantt chart visualization coming soon</p>
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle me-2"></i>
                                        Timeline view will show project schedules, dependencies, and critical path analysis
                                    </div>
                                </div>
                            </div>

                            <!-- Team View Tab -->
                            <div class="tab-pane fade" id="team-view" role="tabpanel">
                                <div class="text-center py-5">
                                    <i class="bi bi-people fs-1 text-muted"></i>
                                    <h5 class="text-muted mt-3">Team Resource View</h5>
                                    <p class="text-muted">Team workload and resource allocation view</p>
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle me-2"></i>
                                        Team view will show individual workloads, availability, and task assignments
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Project Modal -->
<div class="modal fade" id="createProjectModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createProjectForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Project Name <span class="text-danger">*</span></label>
                            <input type="text" name="project_name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Budget</label>
                            <input type="number" name="budget" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select">
                                <option value="web_development">Web Development</option>
                                <option value="mobile_app">Mobile App</option>
                                <option value="design">Design</option>
                                <option value="marketing">Marketing</option>
                                <option value="research">Research</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Project Manager</label>
                            <select name="project_manager" class="form-select" required>
                                <option value="">Select Manager</option>
                                <?php 
                                $employees->data_seek(0);
                                while ($emp = $employees->fetch_assoc()): 
                                ?>
                                <option value="<?= $emp['employee_id'] ?>"><?= htmlspecialchars($emp['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Client</label>
                            <select name="client_id" class="form-select">
                                <option value="">Internal Project</option>
                                <?php while ($customer = $customers->fetch_assoc()): ?>
                                <option value="<?= $customer['id'] ?>"><?= htmlspecialchars($customer['customer_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Team Members</label>
                            <div class="row" id="teamMembersContainer">
                                <?php 
                                $employees->data_seek(0);
                                while ($emp = $employees->fetch_assoc()): 
                                ?>
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="team_members[]" 
                                               value="<?= $emp['employee_id'] ?>" id="member_<?= $emp['employee_id'] ?>">
                                        <label class="form-check-label" for="member_<?= $emp['employee_id'] ?>">
                                            <?= htmlspecialchars($emp['name']) ?>
                                        </label>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Project</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Create Task Modal -->
<div class="modal fade" id="createTaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createTaskForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Project <span class="text-danger">*</span></label>
                            <select name="project_id" class="form-select" required>
                                <option value="">Select Project</option>
                                <?php 
                                $projects->data_seek(0);
                                while ($project = $projects->fetch_assoc()): 
                                ?>
                                <option value="<?= $project['id'] ?>"><?= htmlspecialchars($project['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Task Title <span class="text-danger">*</span></label>
                            <input type="text" name="task_title" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="task_description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Assigned To</label>
                            <select name="assigned_to" class="form-select" required>
                                <option value="">Select Assignee</option>
                                <?php 
                                $employees->data_seek(0);
                                while ($emp = $employees->fetch_assoc()): 
                                ?>
                                <option value="<?= $emp['employee_id'] ?>"><?= htmlspecialchars($emp['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Due Date</label>
                            <input type="date" name="due_date" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Estimated Hours</label>
                            <input type="number" name="estimated_hours" class="form-control" step="0.5" min="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Category</label>
                            <select name="task_category" class="form-select">
                                <option value="development">Development</option>
                                <option value="design">Design</option>
                                <option value="testing">Testing</option>
                                <option value="documentation">Documentation</option>
                                <option value="meeting">Meeting</option>
                                <option value="review">Review</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Create Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTables
    $('#projectsTable, #tasksTable').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[0, "asc"]]
    });

    // Create Project Form
    $('#createProjectForm').submit(function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'create_project');
        
        $.ajax({
            url: '',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert(`${response.message} Project Code: ${response.project_code}`, 'success');
                    $('#createProjectModal').modal('hide');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(response.message, 'error');
                }
            }
        });
    });

    // Create Task Form
    $('#createTaskForm').submit(function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'create_task');
        
        $.ajax({
            url: '',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert(`${response.message} Task Code: ${response.task_code}`, 'success');
                    $('#createTaskModal').modal('hide');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(response.message, 'error');
                }
            }
        });
    });

    // View Project
    $(document).on('click', '.view-project', function() {
        const id = $(this).data('id');
        showAlert('Detailed project view will be implemented next!', 'info');
    });

    // View Task
    $(document).on('click', '.view-task', function() {
        const id = $(this).data('id');
        showAlert('Detailed task view will be implemented next!', 'info');
    });

    // Update Task Status
    $(document).on('click', '.update-task-status', function() {
        const id = $(this).data('id');
        showAlert('Task status update modal will be implemented next!', 'info');
    });

    // Delete Project
    $(document).on('click', '.delete-project', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        
        if (confirm(`Are you sure you want to archive project "${name}"?`)) {
            const formData = new FormData();
            formData.append('action', 'delete_project');
            formData.append('project_id', id);
            
            $.ajax({
                url: '',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert(response.message, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showAlert(response.message, 'error');
                    }
                }
            });
        }
    });
});

// Additional functions
function showReports() {
    showAlert('Project reports and analytics coming soon!', 'info');
}

function showTimesheets() {
    showAlert('Time tracking and timesheets coming soon!', 'info');
}

function exportProjects() {
    showAlert('Export functionality will be available soon!', 'info');
}

function showAlert(message, type) {
    const alertTypes = {
        success: 'alert-success',
        error: 'alert-danger',
        info: 'alert-info',
        warning: 'alert-warning'
    };
    
    const alertHtml = `
        <div class="alert ${alertTypes[type]} alert-dismissible fade show position-fixed" 
             style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    $('body').append(alertHtml);
    setTimeout(() => $('.alert').alert('close'), 5000);
}
</script>

<?php include '../../layouts/footer.php'; ?>
