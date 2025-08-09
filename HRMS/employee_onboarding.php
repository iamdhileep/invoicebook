<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit;
}

include '../db.php';
$page_title = 'Employee Onboarding';

// Create required tables if they don't exist
$createOnboardingTable = "CREATE TABLE IF NOT EXISTS employee_onboarding (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(50) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(20),
    position VARCHAR(100) NOT NULL,
    department_id INT,
    manager_id INT,
    start_date DATE NOT NULL,
    employment_type ENUM('full-time', 'part-time', 'contract', 'internship') DEFAULT 'full-time',
    salary DECIMAL(12,2),
    onboarding_status ENUM('pending', 'in-progress', 'completed', 'cancelled') DEFAULT 'pending',
    documents_submitted BOOLEAN DEFAULT FALSE,
    documents_verified BOOLEAN DEFAULT FALSE,
    it_setup_requested BOOLEAN DEFAULT FALSE,
    it_setup_completed BOOLEAN DEFAULT FALSE,
    workspace_assigned BOOLEAN DEFAULT FALSE,
    orientation_scheduled BOOLEAN DEFAULT FALSE,
    orientation_completed BOOLEAN DEFAULT FALSE,
    buddy_assigned BOOLEAN DEFAULT FALSE,
    first_day_checklist BOOLEAN DEFAULT FALSE,
    week_one_checklist BOOLEAN DEFAULT FALSE,
    month_one_checklist BOOLEAN DEFAULT FALSE,
    probation_period_months INT DEFAULT 3,
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    INDEX idx_employee (employee_id),
    INDEX idx_status (onboarding_status),
    INDEX idx_department (department_id)
) ENGINE=InnoDB";
mysqli_query($conn, $createOnboardingTable);

$createDepartmentsTable = "CREATE TABLE IF NOT EXISTS hr_departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_name VARCHAR(100) NOT NULL,
    department_head INT,
    description TEXT,
    budget DECIMAL(15,2),
    location VARCHAR(100),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB";
mysqli_query($conn, $createDepartmentsTable);

$createEmployeesTable = "CREATE TABLE IF NOT EXISTS hr_employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(50) UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE,
    phone VARCHAR(20),
    position VARCHAR(100),
    department_id INT,
    manager_id INT,
    date_of_joining DATE,
    employment_type ENUM('full-time', 'part-time', 'contract', 'internship') DEFAULT 'full-time',
    salary DECIMAL(12,2),
    status ENUM('active', 'inactive', 'terminated') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES hr_departments(id),
    FOREIGN KEY (manager_id) REFERENCES hr_employees(id)
) ENGINE=InnoDB";
mysqli_query($conn, $createEmployeesTable);

$createOnboardingTasksTable = "CREATE TABLE IF NOT EXISTS onboarding_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    onboarding_id INT NOT NULL,
    task_name VARCHAR(200) NOT NULL,
    task_description TEXT,
    assigned_to INT,
    due_date DATE,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    status ENUM('pending', 'in-progress', 'completed', 'cancelled') DEFAULT 'pending',
    completed_by INT,
    completed_at TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (onboarding_id) REFERENCES employee_onboarding(id) ON DELETE CASCADE,
    INDEX idx_onboarding (onboarding_id),
    INDEX idx_status (status),
    INDEX idx_assigned (assigned_to)
) ENGINE=InnoDB";
mysqli_query($conn, $createOnboardingTasksTable);

$createOnboardingDocumentsTable = "CREATE TABLE IF NOT EXISTS onboarding_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    onboarding_id INT NOT NULL,
    document_name VARCHAR(200) NOT NULL,
    document_type VARCHAR(100),
    is_required BOOLEAN DEFAULT TRUE,
    file_path VARCHAR(500),
    uploaded_at TIMESTAMP NULL,
    verified_by INT,
    verified_at TIMESTAMP NULL,
    status ENUM('pending', 'submitted', 'verified', 'rejected') DEFAULT 'pending',
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (onboarding_id) REFERENCES employee_onboarding(id) ON DELETE CASCADE,
    INDEX idx_onboarding (onboarding_id),
    INDEX idx_status (status)
) ENGINE=InnoDB";
mysqli_query($conn, $createOnboardingDocumentsTable);

// Insert sample data if tables are empty
$checkDepartments = mysqli_query($conn, "SELECT COUNT(*) as count FROM hr_departments");
if ($checkDepartments && mysqli_fetch_assoc($checkDepartments)['count'] == 0) {
    mysqli_query($conn, "INSERT INTO hr_departments (department_name, description, location) VALUES 
        ('Information Technology', 'Software development and IT infrastructure', 'Floor 3'),
        ('Human Resources', 'Employee relations and talent management', 'Floor 2'),
        ('Marketing', 'Brand marketing and digital campaigns', 'Floor 1'),
        ('Sales', 'Business development and client relations', 'Floor 1'),
        ('Finance', 'Financial planning and accounting', 'Floor 2'),
        ('Operations', 'Business operations and logistics', 'Floor 3')");
}

$checkEmployees = mysqli_query($conn, "SELECT COUNT(*) as count FROM hr_employees");
if ($checkEmployees && mysqli_fetch_assoc($checkEmployees)['count'] == 0) {
    mysqli_query($conn, "INSERT INTO hr_employees (employee_id, first_name, last_name, email, position, department_id, date_of_joining) VALUES 
        ('MGR001', 'Sarah', 'Johnson', 'sarah.johnson@company.com', 'IT Manager', 1, '2022-01-15'),
        ('MGR002', 'Michael', 'Davis', 'michael.davis@company.com', 'HR Manager', 2, '2021-11-20'),
        ('MGR003', 'Emily', 'Wilson', 'emily.wilson@company.com', 'Marketing Manager', 3, '2022-03-10'),
        ('TL001', 'David', 'Brown', 'david.brown@company.com', 'Senior Developer', 1, '2022-08-05'),
        ('HR001', 'Lisa', 'Martinez', 'lisa.martinez@company.com', 'HR Specialist', 2, '2023-02-01')");
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_onboarding':
            $employee_id = mysqli_real_escape_string($conn, $_POST['employee_id']);
            $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
            $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
            $email = mysqli_real_escape_string($conn, $_POST['email']);
            $phone = mysqli_real_escape_string($conn, $_POST['phone']);
            $position = mysqli_real_escape_string($conn, $_POST['position']);
            $department_id = intval($_POST['department_id']);
            $manager_id = intval($_POST['manager_id']);
            $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
            $employment_type = mysqli_real_escape_string($conn, $_POST['employment_type']);
            $salary = floatval($_POST['salary']);
            $probation_period = intval($_POST['probation_period']);
            $notes = mysqli_real_escape_string($conn, $_POST['notes']);
            $created_by = $_SESSION['user_id'] ?? 1;
            
            // Begin transaction
            mysqli_begin_transaction($conn);
            
            try {
                // Insert onboarding record
                $insertQuery = "INSERT INTO employee_onboarding 
                    (employee_id, first_name, last_name, email, phone, position, department_id, manager_id, 
                     start_date, employment_type, salary, probation_period_months, notes, created_by) 
                    VALUES ('$employee_id', '$first_name', '$last_name', '$email', '$phone', '$position', 
                     $department_id, $manager_id, '$start_date', '$employment_type', $salary, $probation_period, '$notes', $created_by)";
                
                if (!mysqli_query($conn, $insertQuery)) {
                    throw new Exception(mysqli_error($conn));
                }
                
                $onboarding_id = mysqli_insert_id($conn);
                
                // Add default onboarding tasks
                $defaultTasks = [
                    ['name' => 'Document Collection', 'description' => 'Collect all required documents from new employee', 'due_days' => -1, 'priority' => 'high'],
                    ['name' => 'IT Setup Request', 'description' => 'Request laptop, email account, and system access', 'due_days' => -2, 'priority' => 'high'],
                    ['name' => 'Workspace Assignment', 'description' => 'Assign desk and workspace to new employee', 'due_days' => -1, 'priority' => 'medium'],
                    ['name' => 'Welcome Email', 'description' => 'Send welcome email with first-day information', 'due_days' => -1, 'priority' => 'medium'],
                    ['name' => 'First Day Orientation', 'description' => 'Conduct company orientation and introduction', 'due_days' => 0, 'priority' => 'high'],
                    ['name' => 'Buddy Assignment', 'description' => 'Assign buddy/mentor for initial support', 'due_days' => 0, 'priority' => 'medium'],
                    ['name' => 'Department Introduction', 'description' => 'Introduce to team and department members', 'due_days' => 1, 'priority' => 'medium'],
                    ['name' => 'First Week Check-in', 'description' => 'Check progress and address any concerns', 'due_days' => 5, 'priority' => 'medium'],
                    ['name' => 'First Month Review', 'description' => 'Conduct first month performance review', 'due_days' => 30, 'priority' => 'high']
                ];
                
                foreach ($defaultTasks as $task) {
                    $due_date = date('Y-m-d', strtotime("$start_date + {$task['due_days']} days"));
                    $taskQuery = "INSERT INTO onboarding_tasks 
                        (onboarding_id, task_name, task_description, due_date, priority, assigned_to) 
                        VALUES ($onboarding_id, '{$task['name']}', '{$task['description']}', '$due_date', '{$task['priority']}', $manager_id)";
                    
                    if (!mysqli_query($conn, $taskQuery)) {
                        throw new Exception(mysqli_error($conn));
                    }
                }
                
                // Add default document requirements
                $requiredDocuments = [
                    'Resume/CV',
                    'Photo ID (Driver\'s License/Passport)',
                    'Social Security Card',
                    'Educational Certificates',
                    'Previous Employment Letters',
                    'Bank Details for Payroll',
                    'Emergency Contact Information',
                    'Health Insurance Forms',
                    'Tax Forms (W-4)'
                ];
                
                foreach ($requiredDocuments as $docName) {
                    $docQuery = "INSERT INTO onboarding_documents 
                        (onboarding_id, document_name, document_type, is_required) 
                        VALUES ($onboarding_id, '$docName', 'required', TRUE)";
                    
                    if (!mysqli_query($conn, $docQuery)) {
                        throw new Exception(mysqli_error($conn));
                    }
                }
                
                mysqli_commit($conn);
                echo json_encode(['success' => true, 'message' => 'Onboarding process created successfully!']);
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'update_task_status':
            $task_id = intval($_POST['task_id']);
            $status = mysqli_real_escape_string($conn, $_POST['status']);
            $notes = mysqli_real_escape_string($conn, $_POST['notes']);
            $completed_by = $_SESSION['user_id'] ?? 1;
            
            $updateFields = "status = '$status', notes = '$notes'";
            if ($status === 'completed') {
                $updateFields .= ", completed_by = $completed_by, completed_at = NOW()";
            }
            
            $query = "UPDATE onboarding_tasks SET $updateFields WHERE id = $task_id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Task updated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;
            
        case 'update_onboarding_status':
            $onboarding_id = intval($_POST['onboarding_id']);
            $status = mysqli_real_escape_string($conn, $_POST['status']);
            $field = mysqli_real_escape_string($conn, $_POST['field']);
            $value = ($_POST['value'] === 'true') ? 1 : 0;
            
            $query = "UPDATE employee_onboarding SET $field = $value WHERE id = $onboarding_id";
            
            if (mysqli_query($conn, $query)) {
                // Check if onboarding is complete
                $checkQuery = "SELECT * FROM employee_onboarding WHERE id = $onboarding_id";
                $result = mysqli_query($conn, $checkQuery);
                $record = mysqli_fetch_assoc($result);
                
                $isComplete = $record['documents_verified'] && $record['it_setup_completed'] && 
                             $record['workspace_assigned'] && $record['orientation_completed'] && 
                             $record['first_day_checklist'] && $record['week_one_checklist'];
                
                if ($isComplete && $record['onboarding_status'] !== 'completed') {
                    mysqli_query($conn, "UPDATE employee_onboarding SET onboarding_status = 'completed', completed_at = NOW() WHERE id = $onboarding_id");
                    
                    // Transfer to main employees table
                    $transferQuery = "INSERT IGNORE INTO hr_employees 
                        (employee_id, first_name, last_name, email, phone, position, department_id, manager_id, 
                         date_of_joining, employment_type, salary, status) 
                        SELECT employee_id, first_name, last_name, email, phone, position, department_id, manager_id, 
                               start_date, employment_type, salary, 'active' 
                        FROM employee_onboarding WHERE id = $onboarding_id";
                    mysqli_query($conn, $transferQuery);
                }
                
                echo json_encode(['success' => true, 'message' => 'Status updated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;
            
        case 'get_onboarding_details':
            $id = intval($_POST['id']);
            $query = "SELECT eo.*, d.department_name, 
                             CONCAT(m.first_name, ' ', m.last_name) as manager_name
                      FROM employee_onboarding eo
                      LEFT JOIN hr_departments d ON eo.department_id = d.id
                      LEFT JOIN hr_employees m ON eo.manager_id = m.id
                      WHERE eo.id = $id";
            
            $result = mysqli_query($conn, $query);
            if ($result && $row = mysqli_fetch_assoc($result)) {
                // Get tasks
                $tasksQuery = "SELECT ot.*, CONCAT(COALESCE(e.first_name, 'Unassigned'), ' ', COALESCE(e.last_name, '')) as assigned_to_name,
                                      CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, '')) as completed_by_name
                               FROM onboarding_tasks ot
                               LEFT JOIN hr_employees e ON ot.assigned_to = e.id
                               LEFT JOIN hr_employees c ON ot.completed_by = c.id
                               WHERE ot.onboarding_id = $id
                               ORDER BY ot.due_date ASC";
                
                $tasksResult = mysqli_query($conn, $tasksQuery);
                $tasks = [];
                if ($tasksResult) {
                    while ($task = mysqli_fetch_assoc($tasksResult)) {
                        $tasks[] = $task;
                    }
                }
                
                // Get documents
                $docsQuery = "SELECT * FROM onboarding_documents WHERE onboarding_id = $id ORDER BY document_name";
                $docsResult = mysqli_query($conn, $docsQuery);
                $documents = [];
                if ($docsResult) {
                    while ($doc = mysqli_fetch_assoc($docsResult)) {
                        $documents[] = $doc;
                    }
                }
                
                $row['tasks'] = $tasks;
                $row['documents'] = $documents;
                
                echo json_encode(['success' => true, 'data' => $row]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Record not found']);
            }
            exit;
            
        case 'delete_onboarding':
            $id = intval($_POST['id']);
            
            // Begin transaction
            mysqli_begin_transaction($conn);
            
            try {
                // Delete related records first
                mysqli_query($conn, "DELETE FROM onboarding_documents WHERE onboarding_id = $id");
                mysqli_query($conn, "DELETE FROM onboarding_tasks WHERE onboarding_id = $id");
                mysqli_query($conn, "DELETE FROM employee_onboarding WHERE id = $id");
                
                mysqli_commit($conn);
                echo json_encode(['success' => true, 'message' => 'Onboarding record deleted successfully!']);
            } catch (Exception $e) {
                mysqli_rollback($conn);
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
    }
}

// Get filter data
$departments = mysqli_query($conn, "SELECT * FROM hr_departments ORDER BY department_name");
$managers = mysqli_query($conn, "SELECT id, CONCAT(first_name, ' ', last_name) as full_name FROM hr_employees ORDER BY first_name");

// Get onboarding statistics
$stats = [];

$result1 = mysqli_query($conn, "SELECT COUNT(*) as count FROM employee_onboarding");
$stats['total'] = $result1 ? mysqli_fetch_assoc($result1)['count'] : 0;

$result2 = mysqli_query($conn, "SELECT COUNT(*) as count FROM employee_onboarding WHERE onboarding_status = 'pending'");
$stats['pending'] = $result2 ? mysqli_fetch_assoc($result2)['count'] : 0;

$result3 = mysqli_query($conn, "SELECT COUNT(*) as count FROM employee_onboarding WHERE onboarding_status = 'in-progress'");
$stats['in_progress'] = $result3 ? mysqli_fetch_assoc($result3)['count'] : 0;

$result4 = mysqli_query($conn, "SELECT COUNT(*) as count FROM employee_onboarding WHERE onboarding_status = 'completed'");
$stats['completed'] = $result4 ? mysqli_fetch_assoc($result4)['count'] : 0;

$result5 = mysqli_query($conn, "SELECT COUNT(*) as count FROM employee_onboarding WHERE start_date = CURDATE()");
$stats['starting_today'] = $result5 ? mysqli_fetch_assoc($result5)['count'] : 0;

// Get onboarding list with filters
$whereClause = "WHERE 1=1";
$status_filter = $_GET['status'] ?? '';
$department_filter = $_GET['department'] ?? '';

if ($status_filter) {
    $whereClause .= " AND eo.onboarding_status = '" . mysqli_real_escape_string($conn, $status_filter) . "'";
}
if ($department_filter) {
    $whereClause .= " AND eo.department_id = " . intval($department_filter);
}

$onboardingQuery = "SELECT eo.*, d.department_name, 
                           CONCAT(COALESCE(m.first_name, 'Unassigned'), ' ', COALESCE(m.last_name, '')) as manager_name,
                           DATEDIFF(eo.start_date, CURDATE()) as days_until_start
                    FROM employee_onboarding eo
                    LEFT JOIN hr_departments d ON eo.department_id = d.id
                    LEFT JOIN hr_employees m ON eo.manager_id = m.id
                    $whereClause
                    ORDER BY eo.start_date ASC, eo.created_at DESC";

$onboardingList = mysqli_query($conn, $onboardingQuery);

// Get recent activity
$recentActivity = mysqli_query($conn, "
    SELECT 'onboarding' as type, eo.first_name, eo.last_name, eo.position, eo.onboarding_status, eo.created_at
    FROM employee_onboarding eo
    ORDER BY eo.updated_at DESC
    LIMIT 5
");

include '../layouts/header.php';
include '../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">🎯 Employee Onboarding</h1>
                <p class="text-muted">Streamline new employee integration process</p>
            </div>
            <div>
                <a href="index.php" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-left"></i> Back to HRMS
                </a>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addOnboardingModal">
                    <i class="bi bi-person-plus"></i> New Onboarding
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-white-50 mb-2">Total Onboarding</h6>
                                <h3 class="mb-0"><?php echo $stats['total']; ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-people fs-2 text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body text-white">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-white-50 mb-2">Pending</h6>
                                <h3 class="mb-0"><?php echo $stats['pending']; ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-clock fs-2 text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body text-white">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-white-50 mb-2">In Progress</h6>
                                <h3 class="mb-0"><?php echo $stats['in_progress']; ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-arrow-clockwise fs-2 text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <div class="card-body text-white">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-white-50 mb-2">Starting Today</h6>
                                <h3 class="mb-0"><?php echo $stats['starting_today']; ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-calendar-check fs-2 text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Actions -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Status Filter</label>
                                <select name="status" class="form-select" onchange="this.form.submit()">
                                    <option value="">All Statuses</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="in-progress" <?php echo $status_filter === 'in-progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Department Filter</label>
                                <select name="department" class="form-select" onchange="this.form.submit()">
                                    <option value="">All Departments</option>
                                    <?php 
                                    if ($departments && mysqli_num_rows($departments) > 0) {
                                        while ($dept = mysqli_fetch_assoc($departments)): 
                                    ?>
                                    <option value="<?php echo $dept['id']; ?>" <?php echo $department_filter == $dept['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['department_name']); ?>
                                    </option>
                                    <?php 
                                        endwhile; 
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <a href="?" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-clockwise"></i> Clear Filters
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light">
                        <h6 class="card-title mb-0">Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary btn-sm" onclick="exportOnboarding()">
                                <i class="bi bi-download"></i> Export List
                            </button>
                            <button class="btn btn-outline-info btn-sm" onclick="sendReminders()">
                                <i class="bi bi-bell"></i> Send Reminders
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Onboarding List -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h5 class="card-title mb-0">
                    <i class="bi bi-list-check me-2"></i>Onboarding Pipeline
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Employee</th>
                                <th>Position</th>
                                <th>Department</th>
                                <th>Start Date</th>
                                <th>Manager</th>
                                <th>Progress</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($onboardingList && mysqli_num_rows($onboardingList) > 0) {
                                while ($record = mysqli_fetch_assoc($onboardingList)): 
                                
                                // Calculate progress percentage
                                $completed_items = 0;
                                $total_items = 8; // Total checkable items
                                
                                if ($record['documents_verified']) $completed_items++;
                                if ($record['it_setup_completed']) $completed_items++;
                                if ($record['workspace_assigned']) $completed_items++;
                                if ($record['orientation_completed']) $completed_items++;
                                if ($record['buddy_assigned']) $completed_items++;
                                if ($record['first_day_checklist']) $completed_items++;
                                if ($record['week_one_checklist']) $completed_items++;
                                if ($record['month_one_checklist']) $completed_items++;
                                
                                $progress_percent = round(($completed_items / $total_items) * 100);
                                
                                // Status badge colors
                                $status_colors = [
                                    'pending' => 'warning',
                                    'in-progress' => 'info',
                                    'completed' => 'success',
                                    'cancelled' => 'danger'
                                ];
                                $badge_color = $status_colors[$record['onboarding_status']] ?? 'secondary';
                                
                                // Days until start
                                $days_text = '';
                                if ($record['days_until_start'] > 0) {
                                    $days_text = "in {$record['days_until_start']} days";
                                } elseif ($record['days_until_start'] == 0) {
                                    $days_text = "Today";
                                } else {
                                    $days_text = abs($record['days_until_start']) . " days ago";
                                }
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm bg-primary rounded-circle text-white d-flex align-items-center justify-content-center me-3">
                                            <?php echo strtoupper(substr($record['first_name'], 0, 1) . substr($record['last_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($record['employee_id']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="fw-medium"><?php echo htmlspecialchars($record['position']); ?></span>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($record['employment_type']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($record['department_name'] ?? 'Not Assigned'); ?></td>
                                <td>
                                    <div class="fw-bold"><?php echo date('M d, Y', strtotime($record['start_date'])); ?></div>
                                    <small class="text-muted"><?php echo $days_text; ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($record['manager_name']); ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                            <div class="progress-bar bg-primary" style="width: <?php echo $progress_percent; ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?php echo $progress_percent; ?>%</small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $badge_color; ?>">
                                        <?php echo ucfirst(str_replace('-', ' ', $record['onboarding_status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" onclick="viewOnboarding(<?php echo $record['id']; ?>)" title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-outline-success" onclick="editOnboarding(<?php echo $record['id']; ?>)" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" onclick="deleteOnboarding(<?php echo $record['id']; ?>)" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php 
                                endwhile; 
                            } else {
                                echo '<tr><td colspan="8" class="text-center text-muted py-4">No onboarding records found</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Onboarding Modal -->
<div class="modal fade" id="addOnboardingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-person-plus me-2"></i>Create New Onboarding
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addOnboardingForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Employee ID *</label>
                            <input type="text" class="form-control" name="employee_id" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Employment Type *</label>
                            <select class="form-select" name="employment_type" required>
                                <option value="full-time">Full-time</option>
                                <option value="part-time">Part-time</option>
                                <option value="contract">Contract</option>
                                <option value="internship">Internship</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">First Name *</label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name *</label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Position *</label>
                            <input type="text" class="form-control" name="position" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department *</label>
                            <select class="form-select" name="department_id" required>
                                <option value="">Select Department</option>
                                <?php 
                                mysqli_data_seek($departments, 0);
                                while ($dept = mysqli_fetch_assoc($departments)): 
                                ?>
                                <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['department_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Manager</label>
                            <select class="form-select" name="manager_id">
                                <option value="">Select Manager</option>
                                <?php 
                                if ($managers && mysqli_num_rows($managers) > 0) {
                                    while ($manager = mysqli_fetch_assoc($managers)): 
                                ?>
                                <option value="<?php echo $manager['id']; ?>"><?php echo htmlspecialchars($manager['full_name']); ?></option>
                                <?php 
                                    endwhile; 
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Start Date *</label>
                            <input type="date" class="form-control" name="start_date" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Salary</label>
                            <input type="number" class="form-control" name="salary" step="0.01" min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Probation Period (months)</label>
                            <input type="number" class="form-control" name="probation_period" value="3" min="0" max="12">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Additional notes or special instructions..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Create Onboarding
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Onboarding Details Modal -->
<div class="modal fade" id="viewOnboardingModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-person-badge me-2"></i>Onboarding Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="onboardingDetails">
                <!-- Details will be loaded here -->
            </div>
        </div>
    </div>
</div>

<style>
.avatar-sm {
    width: 40px;
    height: 40px;
}

.checklist-item {
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 8px;
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
}

.checklist-item.completed {
    border-color: #28a745;
    background-color: #f8f9fa;
}

.checklist-item .form-check-input:checked {
    background-color: #28a745;
    border-color: #28a745;
}

.task-item {
    border-left: 4px solid #dee2e6;
    padding-left: 15px;
    margin-bottom: 15px;
}

.task-item.high-priority {
    border-left-color: #dc3545;
}

.task-item.medium-priority {
    border-left-color: #ffc107;
}

.task-item.low-priority {
    border-left-color: #28a745;
}

.task-item.completed {
    border-left-color: #6c757d;
    opacity: 0.7;
}

.progress {
    border-radius: 10px;
}

.progress-bar {
    border-radius: 10px;
}
</style>

<script>
// Add new onboarding
document.getElementById('addOnboardingForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'add_onboarding');
    
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
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while creating onboarding record');
    });
});

// View onboarding details
function viewOnboarding(id) {
    const formData = new FormData();
    formData.append('action', 'get_onboarding_details');
    formData.append('id', id);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayOnboardingDetails(data.data);
            new bootstrap.Modal(document.getElementById('viewOnboardingModal')).show();
        } else {
            alert('Error loading details');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while loading details');
    });
}

// Display onboarding details
function displayOnboardingDetails(data) {
    const detailsDiv = document.getElementById('onboardingDetails');
    
    const progress = calculateProgress(data);
    
    detailsDiv.innerHTML = `
        <div class="row">
            <div class="col-lg-8">
                <!-- Employee Information -->
                <div class="card border-0 bg-light mb-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2">Employee Information</h6>
                                <p class="mb-1"><strong>${data.first_name} ${data.last_name}</strong></p>
                                <p class="mb-1">${data.employee_id}</p>
                                <p class="mb-1">${data.email}</p>
                                <p class="mb-1">${data.phone || 'Not provided'}</p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2">Job Details</h6>
                                <p class="mb-1"><strong>${data.position}</strong></p>
                                <p class="mb-1">${data.department_name || 'Not assigned'}</p>
                                <p class="mb-1">Manager: ${data.manager_name || 'Not assigned'}</p>
                                <p class="mb-1">Start: ${new Date(data.start_date).toLocaleDateString()}</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Progress Checklist -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">Onboarding Progress</h6>
                        <div class="progress mt-2" style="height: 10px;">
                            <div class="progress-bar bg-success" style="width: ${progress}%"></div>
                        </div>
                    </div>
                    <div class="card-body">
                        ${generateChecklistHtml(data)}
                    </div>
                </div>
                
                <!-- Tasks -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header">
                        <h6 class="mb-0">Onboarding Tasks</h6>
                    </div>
                    <div class="card-body">
                        ${generateTasksHtml(data.tasks)}
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Status -->
                <div class="card border-0 bg-light mb-3">
                    <div class="card-body text-center">
                        <h6 class="text-muted">Current Status</h6>
                        <span class="badge bg-${getStatusColor(data.onboarding_status)} fs-6">
                            ${data.onboarding_status.replace('-', ' ').toUpperCase()}
                        </span>
                        <div class="mt-2">
                            <small class="text-muted">Progress: ${progress}%</small>
                        </div>
                    </div>
                </div>
                
                <!-- Documents -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">Required Documents</h6>
                    </div>
                    <div class="card-body">
                        ${generateDocumentsHtml(data.documents)}
                    </div>
                </div>
                
                <!-- Actions -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header">
                        <h6 class="mb-0">Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary btn-sm" onclick="editOnboarding(${data.id})">
                                <i class="bi bi-pencil"></i> Edit Details
                            </button>
                            <button class="btn btn-success btn-sm" onclick="sendWelcomeEmail(${data.id})">
                                <i class="bi bi-envelope"></i> Send Welcome Email
                            </button>
                            <button class="btn btn-info btn-sm" onclick="generateReport(${data.id})">
                                <i class="bi bi-file-text"></i> Generate Report
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Generate checklist HTML
function generateChecklistHtml(data) {
    const checklist = [
        { key: 'documents_submitted', label: 'Documents Submitted', description: 'All required documents received' },
        { key: 'documents_verified', label: 'Documents Verified', description: 'Documents reviewed and approved' },
        { key: 'it_setup_requested', label: 'IT Setup Requested', description: 'IT equipment and accounts requested' },
        { key: 'it_setup_completed', label: 'IT Setup Completed', description: 'Laptop, email, and system access ready' },
        { key: 'workspace_assigned', label: 'Workspace Assigned', description: 'Desk and workspace allocated' },
        { key: 'orientation_scheduled', label: 'Orientation Scheduled', description: 'Company orientation scheduled' },
        { key: 'orientation_completed', label: 'Orientation Completed', description: 'Company orientation completed' },
        { key: 'buddy_assigned', label: 'Buddy Assigned', description: 'Mentor/buddy assigned for support' },
        { key: 'first_day_checklist', label: 'First Day Checklist', description: 'First day tasks completed' },
        { key: 'week_one_checklist', label: 'Week One Checklist', description: 'First week milestones completed' },
        { key: 'month_one_checklist', label: 'Month One Checklist', description: 'First month review completed' }
    ];
    
    let html = '<div class="row">';
    
    checklist.forEach((item, index) => {
        const isChecked = data[item.key] == 1;
        const colClass = index % 2 === 0 ? 'col-md-6' : 'col-md-6';
        
        html += `
            <div class="${colClass}">
                <div class="checklist-item ${isChecked ? 'completed' : ''}">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" ${isChecked ? 'checked' : ''} 
                               onchange="updateOnboardingStatus(${data.id}, '${item.key}', this.checked)">
                        <label class="form-check-label">
                            <div class="fw-bold">${item.label}</div>
                            <small class="text-muted">${item.description}</small>
                        </label>
                    </div>
                </div>
            </div>
        `;
        
        if (index % 2 === 1 || index === checklist.length - 1) {
            // Close row after every 2 items or at the end
        }
    });
    
    html += '</div>';
    return html;
}

// Generate tasks HTML
function generateTasksHtml(tasks) {
    if (!tasks || tasks.length === 0) {
        return '<p class="text-muted text-center">No tasks assigned</p>';
    }
    
    let html = '';
    tasks.forEach(task => {
        const priorityClass = task.priority === 'high' ? 'high-priority' : 
                             task.priority === 'medium' ? 'medium-priority' : 'low-priority';
        const statusClass = task.status === 'completed' ? 'completed' : '';
        const dueDate = new Date(task.due_date).toLocaleDateString();
        
        html += `
            <div class="task-item ${priorityClass} ${statusClass}">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <h6 class="mb-1">${task.task_name}</h6>
                        <p class="mb-1 text-muted small">${task.task_description}</p>
                        <div class="d-flex gap-3">
                            <small class="text-muted">Due: ${dueDate}</small>
                            <small class="text-muted">Assigned: ${task.assigned_to_name}</small>
                            ${task.status === 'completed' ? `<small class="text-success">✓ Completed by ${task.completed_by_name}</small>` : ''}
                        </div>
                    </div>
                    <div class="ms-2">
                        <span class="badge bg-${task.priority === 'high' ? 'danger' : task.priority === 'medium' ? 'warning' : 'success'}">${task.priority}</span>
                        <span class="badge bg-${task.status === 'completed' ? 'success' : task.status === 'in-progress' ? 'info' : 'secondary'} ms-1">${task.status}</span>
                    </div>
                </div>
                ${task.status !== 'completed' ? `
                <div class="mt-2">
                    <button class="btn btn-sm btn-outline-success" onclick="updateTaskStatus(${task.id}, 'completed')">
                        <i class="bi bi-check-lg"></i> Mark Complete
                    </button>
                </div>
                ` : ''}
            </div>
        `;
    });
    
    return html;
}

// Generate documents HTML
function generateDocumentsHtml(documents) {
    if (!documents || documents.length === 0) {
        return '<p class="text-muted text-center">No documents required</p>';
    }
    
    let html = '<div class="list-group list-group-flush">';
    documents.forEach(doc => {
        const statusIcon = doc.status === 'verified' ? 'bi-check-circle text-success' :
                          doc.status === 'submitted' ? 'bi-clock text-warning' :
                          doc.status === 'rejected' ? 'bi-x-circle text-danger' : 'bi-circle text-muted';
        
        html += `
            <div class="list-group-item border-0 px-0">
                <div class="d-flex align-items-center">
                    <i class="bi ${statusIcon} me-2"></i>
                    <div class="flex-grow-1">
                        <div class="fw-bold small">${doc.document_name}</div>
                        <small class="text-muted">${doc.document_type || 'Document'}</small>
                    </div>
                    ${doc.is_required ? '<span class="badge bg-danger ms-2">Required</span>' : ''}
                </div>
            </div>
        `;
    });
    html += '</div>';
    
    return html;
}

// Calculate progress percentage
function calculateProgress(data) {
    const items = [
        'documents_verified', 'it_setup_completed', 'workspace_assigned', 
        'orientation_completed', 'buddy_assigned', 'first_day_checklist', 
        'week_one_checklist', 'month_one_checklist'
    ];
    
    const completed = items.filter(item => data[item] == 1).length;
    return Math.round((completed / items.length) * 100);
}

// Get status color
function getStatusColor(status) {
    const colors = {
        'pending': 'warning',
        'in-progress': 'info',
        'completed': 'success',
        'cancelled': 'danger'
    };
    return colors[status] || 'secondary';
}

// Update onboarding status
function updateOnboardingStatus(onboardingId, field, value) {
    const formData = new FormData();
    formData.append('action', 'update_onboarding_status');
    formData.append('onboarding_id', onboardingId);
    formData.append('field', field);
    formData.append('value', value);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Refresh the view
            viewOnboarding(onboardingId);
        } else {
            alert('Error updating status');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating status');
    });
}

// Update task status
function updateTaskStatus(taskId, status) {
    const notes = prompt('Add notes (optional):') || '';
    
    const formData = new FormData();
    formData.append('action', 'update_task_status');
    formData.append('task_id', taskId);
    formData.append('status', status);
    formData.append('notes', notes);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            // Refresh current view or reload if needed
            location.reload();
        } else {
            alert('Error updating task');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating task');
    });
}

// Placeholder functions for additional features
function editOnboarding(id) {
    alert('Edit functionality will be implemented in the next iteration');
}

function deleteOnboarding(id) {
    if (confirm('Are you sure you want to delete this onboarding record? This action cannot be undone.')) {
        const formData = new FormData();
        formData.append('action', 'delete_onboarding');
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
                alert('Error deleting record');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting record');
        });
    }
}

function exportOnboarding() {
    alert('Export functionality will be implemented in the next iteration');
}

function sendReminders() {
    alert('Reminder functionality will be implemented in the next iteration');
}

function sendWelcomeEmail(id) {
    alert('Welcome email functionality will be implemented in the next iteration');
}

function generateReport(id) {
    alert('Report generation functionality will be implemented in the next iteration');
}
</script>

<?php include '../layouts/footer.php'; ?>
