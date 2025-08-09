<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

include '../db.php';
$page_title = 'Career Development';

// Create career development tables
$createCareerPathsTable = "CREATE TABLE IF NOT EXISTS hr_career_paths (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    path_name VARCHAR(150) NOT NULL,
    department VARCHAR(100),
    description TEXT,
    entry_level_position VARCHAR(100),
    target_position VARCHAR(100),
    estimated_duration_months INT DEFAULT 12,
    required_skills TEXT,
    required_qualifications TEXT,
    salary_range_min DECIMAL(12,2) DEFAULT 0,
    salary_range_max DECIMAL(12,2) DEFAULT 0,
    prerequisites TEXT,
    career_progression TEXT,
    status ENUM('active', 'inactive', 'draft') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB";
mysqli_query($conn, $createCareerPathsTable);

$createCareerPlansTable = "CREATE TABLE IF NOT EXISTS hr_career_plans (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    employee_id INT(11) NOT NULL,
    career_path_id INT(11),
    current_position VARCHAR(100),
    target_position VARCHAR(100),
    target_timeline DATE,
    current_skills TEXT,
    skills_to_develop TEXT,
    development_actions TEXT,
    mentor_assigned INT(11),
    plan_status ENUM('draft', 'active', 'on_track', 'delayed', 'completed', 'cancelled') DEFAULT 'draft',
    progress_percentage INT DEFAULT 0,
    manager_notes TEXT,
    employee_notes TEXT,
    last_reviewed DATE,
    next_review_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
    FOREIGN KEY (career_path_id) REFERENCES hr_career_paths(id) ON DELETE SET NULL,
    FOREIGN KEY (mentor_assigned) REFERENCES hr_employees(id) ON DELETE SET NULL
) ENGINE=InnoDB";
mysqli_query($conn, $createCareerPlansTable);

$createSkillAssessmentsTable = "CREATE TABLE IF NOT EXISTS hr_skill_assessments (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    employee_id INT(11) NOT NULL,
    skill_name VARCHAR(100) NOT NULL,
    skill_category ENUM('technical', 'soft_skills', 'leadership', 'communication', 'analytical', 'project_management', 'other') DEFAULT 'other',
    current_level ENUM('beginner', 'intermediate', 'advanced', 'expert') DEFAULT 'beginner',
    target_level ENUM('beginner', 'intermediate', 'advanced', 'expert') DEFAULT 'intermediate',
    self_rating INT DEFAULT 3,
    manager_rating INT,
    assessment_date DATE NOT NULL,
    assessment_method ENUM('self_assessment', 'manager_review', 'peer_review', 'formal_test', '360_feedback') DEFAULT 'self_assessment',
    evidence_provided TEXT,
    development_priority ENUM('high', 'medium', 'low') DEFAULT 'medium',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
    UNIQUE KEY unique_employee_skill (employee_id, skill_name, assessment_date)
) ENGINE=InnoDB";
mysqli_query($conn, $createSkillAssessmentsTable);

$createDevelopmentActivitiesTable = "CREATE TABLE IF NOT EXISTS hr_development_activities (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    employee_id INT(11) NOT NULL,
    career_plan_id INT(11),
    activity_type ENUM('training', 'mentoring', 'job_shadowing', 'project_assignment', 'certification', 'conference', 'workshop', 'reading', 'other') DEFAULT 'training',
    activity_name VARCHAR(200) NOT NULL,
    description TEXT,
    start_date DATE,
    end_date DATE,
    completion_deadline DATE,
    status ENUM('planned', 'in_progress', 'completed', 'cancelled', 'postponed') DEFAULT 'planned',
    completion_percentage INT DEFAULT 0,
    budget_allocated DECIMAL(10,2) DEFAULT 0,
    budget_spent DECIMAL(10,2) DEFAULT 0,
    provider_name VARCHAR(150),
    completion_evidence TEXT,
    skills_developed TEXT,
    outcome_rating INT DEFAULT 5,
    manager_feedback TEXT,
    employee_feedback TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
    FOREIGN KEY (career_plan_id) REFERENCES hr_career_plans(id) ON DELETE SET NULL
) ENGINE=InnoDB";
mysqli_query($conn, $createDevelopmentActivitiesTable);

$createSuccessionPlansTable = "CREATE TABLE IF NOT EXISTS hr_succession_plans (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    position_title VARCHAR(150) NOT NULL,
    department VARCHAR(100),
    current_incumbent INT(11),
    criticality ENUM('high', 'medium', 'low') DEFAULT 'medium',
    risk_level ENUM('high', 'medium', 'low') DEFAULT 'medium',
    retirement_timeline DATE,
    key_responsibilities TEXT,
    required_competencies TEXT,
    identified_successors TEXT,
    development_needs TEXT,
    succession_timeline DATE,
    plan_status ENUM('draft', 'active', 'under_review', 'completed') DEFAULT 'draft',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (current_incumbent) REFERENCES hr_employees(id) ON DELETE SET NULL
) ENGINE=InnoDB";
mysqli_query($conn, $createSuccessionPlansTable);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_career_path':
            $path_name = mysqli_real_escape_string($conn, $_POST['path_name']);
            $department = mysqli_real_escape_string($conn, $_POST['department']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $entry_level = mysqli_real_escape_string($conn, $_POST['entry_level_position']);
            $target_position = mysqli_real_escape_string($conn, $_POST['target_position']);
            $duration = intval($_POST['estimated_duration_months']);
            $required_skills = mysqli_real_escape_string($conn, $_POST['required_skills']);
            $qualifications = mysqli_real_escape_string($conn, $_POST['required_qualifications']);
            $salary_min = floatval($_POST['salary_range_min']);
            $salary_max = floatval($_POST['salary_range_max']);
            
            $query = "INSERT INTO hr_career_paths (path_name, department, description, entry_level_position, target_position, estimated_duration_months, required_skills, required_qualifications, salary_range_min, salary_range_max) 
                      VALUES ('$path_name', '$department', '$description', '$entry_level', '$target_position', $duration, '$required_skills', '$qualifications', $salary_min, $salary_max)";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Career path created successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;
            
        case 'create_career_plan':
            $employee_id = intval($_POST['employee_id']);
            $career_path_id = intval($_POST['career_path_id']) ?: null;
            $current_position = mysqli_real_escape_string($conn, $_POST['current_position']);
            $target_position = mysqli_real_escape_string($conn, $_POST['target_position']);
            $target_timeline = mysqli_real_escape_string($conn, $_POST['target_timeline']);
            $current_skills = mysqli_real_escape_string($conn, $_POST['current_skills']);
            $skills_to_develop = mysqli_real_escape_string($conn, $_POST['skills_to_develop']);
            $development_actions = mysqli_real_escape_string($conn, $_POST['development_actions']);
            $mentor_assigned = intval($_POST['mentor_assigned']) ?: null;
            
            // Calculate next review date (3 months from now)
            $next_review = date('Y-m-d', strtotime('+3 months'));
            
            $careerPathQuery = $career_path_id ? ", career_path_id = $career_path_id" : "";
            $mentorQuery = $mentor_assigned ? ", mentor_assigned = $mentor_assigned" : "";
            
            $query = "INSERT INTO hr_career_plans (employee_id, current_position, target_position, target_timeline, current_skills, skills_to_develop, development_actions, next_review_date $careerPathQuery $mentorQuery) 
                      VALUES ($employee_id, '$current_position', '$target_position', '$target_timeline', '$current_skills', '$skills_to_develop', '$development_actions', '$next_review')";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Career plan created successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;
            
        case 'add_skill_assessment':
            $employee_id = intval($_POST['employee_id']);
            $skill_name = mysqli_real_escape_string($conn, $_POST['skill_name']);
            $skill_category = mysqli_real_escape_string($conn, $_POST['skill_category']);
            $current_level = mysqli_real_escape_string($conn, $_POST['current_level']);
            $target_level = mysqli_real_escape_string($conn, $_POST['target_level']);
            $self_rating = intval($_POST['self_rating']);
            $assessment_date = mysqli_real_escape_string($conn, $_POST['assessment_date']);
            $assessment_method = mysqli_real_escape_string($conn, $_POST['assessment_method']);
            $development_priority = mysqli_real_escape_string($conn, $_POST['development_priority']);
            $evidence = mysqli_real_escape_string($conn, $_POST['evidence_provided']);
            
            $query = "INSERT INTO hr_skill_assessments (employee_id, skill_name, skill_category, current_level, target_level, self_rating, assessment_date, assessment_method, development_priority, evidence_provided) 
                      VALUES ($employee_id, '$skill_name', '$skill_category', '$current_level', '$target_level', $self_rating, '$assessment_date', '$assessment_method', '$development_priority', '$evidence')";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Skill assessment added successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;
            
        case 'add_development_activity':
            $employee_id = intval($_POST['employee_id']);
            $career_plan_id = intval($_POST['career_plan_id']) ?: null;
            $activity_type = mysqli_real_escape_string($conn, $_POST['activity_type']);
            $activity_name = mysqli_real_escape_string($conn, $_POST['activity_name']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
            $completion_deadline = mysqli_real_escape_string($conn, $_POST['completion_deadline']);
            $budget_allocated = floatval($_POST['budget_allocated']);
            $provider_name = mysqli_real_escape_string($conn, $_POST['provider_name']);
            
            $careerPlanQuery = $career_plan_id ? ", career_plan_id = $career_plan_id" : "";
            
            $query = "INSERT INTO hr_development_activities (employee_id, activity_type, activity_name, description, start_date, completion_deadline, budget_allocated, provider_name $careerPlanQuery) 
                      VALUES ($employee_id, '$activity_type', '$activity_name', '$description', '$start_date', '$completion_deadline', $budget_allocated, '$provider_name')";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Development activity added successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;
            
        case 'update_plan_progress':
            $plan_id = intval($_POST['plan_id']);
            $progress = intval($_POST['progress_percentage']);
            $status = mysqli_real_escape_string($conn, $_POST['plan_status']);
            $manager_notes = mysqli_real_escape_string($conn, $_POST['manager_notes']);
            
            $query = "UPDATE hr_career_plans SET 
                      progress_percentage = $progress,
                      plan_status = '$status',
                      manager_notes = '$manager_notes',
                      last_reviewed = CURDATE()
                      WHERE id = $plan_id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Career plan updated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;
            
        case 'update_activity_status':
            $activity_id = intval($_POST['activity_id']);
            $status = mysqli_real_escape_string($conn, $_POST['status']);
            $completion_percentage = intval($_POST['completion_percentage']);
            $skills_developed = mysqli_real_escape_string($conn, $_POST['skills_developed']);
            $employee_feedback = mysqli_real_escape_string($conn, $_POST['employee_feedback']);
            
            $query = "UPDATE hr_development_activities SET 
                      status = '$status',
                      completion_percentage = $completion_percentage,
                      skills_developed = '$skills_developed',
                      employee_feedback = '$employee_feedback'
                      WHERE id = $activity_id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Development activity updated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;
            
        case 'get_employee_plans':
            $employee_id = intval($_POST['employee_id']);
            $query = "SELECT * FROM hr_career_plans WHERE employee_id = $employee_id ORDER BY created_at DESC";
            $result = mysqli_query($conn, $query);
            $plans = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $plans[] = $row;
            }
            echo json_encode(['success' => true, 'plans' => $plans]);
            exit;
            
        case 'delete_career_path':
            $id = intval($_POST['id']);
            
            // Check if path is being used
            $checkQuery = "SELECT COUNT(*) as plan_count FROM hr_career_plans WHERE career_path_id = $id";
            $checkResult = mysqli_query($conn, $checkQuery);
            $check = mysqli_fetch_assoc($checkResult);
            
            if ($check['plan_count'] > 0) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete career path that is being used in career plans!']);
                exit;
            }
            
            $query = "DELETE FROM hr_career_paths WHERE id = $id";
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Career path deleted successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;
    }
}

// Get statistics
$stats = [];
$stats['active_plans'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM hr_career_plans WHERE plan_status IN ('active', 'on_track')"))['count'];
$stats['career_paths'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM hr_career_paths WHERE status = 'active'"))['count'];
$stats['development_activities'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM hr_development_activities WHERE status IN ('planned', 'in_progress')"))['count'];
$stats['avg_progress'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT ROUND(AVG(progress_percentage), 1) as avg FROM hr_career_plans WHERE plan_status IN ('active', 'on_track')"))['avg'] ?? 0;

// Get career paths
$career_paths = mysqli_query($conn, "
    SELECT cp.*, 
           COUNT(pl.id) as assigned_employees
    FROM hr_career_paths cp 
    LEFT JOIN hr_career_plans pl ON cp.id = pl.career_path_id 
    GROUP BY cp.id 
    ORDER BY cp.created_at DESC
");

// Get active career plans with employee details
$career_plans = mysqli_query($conn, "
    SELECT pl.*, 
           CONCAT(e.first_name, ' ', e.last_name) as employee_name,
           e.employee_id as emp_id,
           e.department,
           cp.path_name,
           CONCAT(m.first_name, ' ', m.last_name) as mentor_name
    FROM hr_career_plans pl 
    JOIN hr_employees e ON pl.employee_id = e.id 
    LEFT JOIN hr_career_paths cp ON pl.career_path_id = cp.id 
    LEFT JOIN hr_employees m ON pl.mentor_assigned = m.id 
    ORDER BY pl.created_at DESC
    LIMIT 20
");

// Get recent development activities
$recent_activities = mysqli_query($conn, "
    SELECT da.*, 
           CONCAT(e.first_name, ' ', e.last_name) as employee_name,
           e.employee_id as emp_id
    FROM hr_development_activities da 
    JOIN hr_employees e ON da.employee_id = e.id 
    ORDER BY da.created_at DESC 
    LIMIT 15
");

// Get employees for dropdowns
$employees = mysqli_query($conn, "SELECT id, first_name, last_name, employee_id, department, position FROM hr_employees WHERE status = 'active' ORDER BY first_name");
$mentors = mysqli_query($conn, "SELECT id, first_name, last_name, employee_id FROM hr_employees WHERE status = 'active' AND position LIKE '%manager%' OR position LIKE '%lead%' OR position LIKE '%senior%' ORDER BY first_name");

include '../layouts/header.php';
include '../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">🚀 Career Development</h1>
                <p class="text-muted">Manage employee career paths and development planning</p>
            </div>
            <div>
                <a href="index.php" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-left"></i> Back to HRMS
                </a>
                <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addCareerPathModal">
                    <i class="bi bi-plus-circle"></i> Add Career Path
                </button>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createCareerPlanModal">
                    <i class="bi bi-diagram-3"></i> Create Career Plan
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
                                <h6 class="card-title text-white-50 mb-2">Active Career Plans</h6>
                                <h3 class="mb-0"><?php echo $stats['active_plans']; ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-diagram-3 fs-2 text-white-50"></i>
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
                                <h6 class="card-title text-white-50 mb-2">Career Paths</h6>
                                <h3 class="mb-0"><?php echo $stats['career_paths']; ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-signpost fs-2 text-white-50"></i>
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
                                <h6 class="card-title text-white-50 mb-2">Development Activities</h6>
                                <h3 class="mb-0"><?php echo $stats['development_activities']; ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-activity fs-2 text-white-50"></i>
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
                                <h6 class="card-title text-white-50 mb-2">Average Progress</h6>
                                <h3 class="mb-0"><?php echo $stats['avg_progress']; ?>%</h3>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-speedometer2 fs-2 text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Tabs -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#career-paths-tab">
                            <i class="bi bi-signpost me-2"></i>Career Paths
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#career-plans-tab">
                            <i class="bi bi-diagram-3 me-2"></i>Career Plans
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#skills-tab">
                            <i class="bi bi-mortarboard me-2"></i>Skill Assessments
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#activities-tab">
                            <i class="bi bi-activity me-2"></i>Development Activities
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <!-- Career Paths Tab -->
                    <div class="tab-pane fade show active" id="career-paths-tab">
                        <div class="row">
                            <?php while ($path = mysqli_fetch_assoc($career_paths)): ?>
                            <div class="col-lg-6 col-xl-4 mb-4">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="card-title mb-0">
                                            <i class="bi bi-signpost me-2"></i>
                                            <?php echo htmlspecialchars($path['path_name']); ?>
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-2">
                                            <strong>Department:</strong> <?php echo htmlspecialchars($path['department']); ?>
                                        </div>
                                        <div class="mb-2">
                                            <strong>Entry Level:</strong> <?php echo htmlspecialchars($path['entry_level_position']); ?>
                                        </div>
                                        <div class="mb-2">
                                            <strong>Target Position:</strong> <?php echo htmlspecialchars($path['target_position']); ?>
                                        </div>
                                        <div class="mb-2">
                                            <strong>Duration:</strong> <?php echo $path['estimated_duration_months']; ?> months
                                        </div>
                                        <div class="mb-3">
                                            <strong>Salary Range:</strong> 
                                            $<?php echo number_format($path['salary_range_min']); ?> - 
                                            $<?php echo number_format($path['salary_range_max']); ?>
                                        </div>
                                        <div class="mb-3">
                                            <small class="text-muted">
                                                <?php echo substr($path['description'], 0, 100) . (strlen($path['description']) > 100 ? '...' : ''); ?>
                                            </small>
                                        </div>
                                        <div class="text-center">
                                            <span class="badge bg-info"><?php echo $path['assigned_employees']; ?> employees assigned</span>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-light">
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewCareerPath(<?php echo $path['id']; ?>)">
                                            <i class="bi bi-eye"></i> View Details
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteCareerPath(<?php echo $path['id']; ?>)">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>

                    <!-- Career Plans Tab -->
                    <div class="tab-pane fade" id="career-plans-tab">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Employee</th>
                                        <th>Current Position</th>
                                        <th>Target Position</th>
                                        <th>Career Path</th>
                                        <th>Progress</th>
                                        <th>Status</th>
                                        <th>Timeline</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($plan = mysqli_fetch_assoc($career_plans)): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($plan['employee_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($plan['emp_id']); ?> • <?php echo htmlspecialchars($plan['department']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($plan['current_position']); ?></td>
                                        <td><?php echo htmlspecialchars($plan['target_position']); ?></td>
                                        <td>
                                            <?php echo $plan['path_name'] ? htmlspecialchars($plan['path_name']) : '<span class="text-muted">Custom Plan</span>'; ?>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar" role="progressbar" 
                                                     style="width: <?php echo $plan['progress_percentage']; ?>%" 
                                                     aria-valuenow="<?php echo $plan['progress_percentage']; ?>" 
                                                     aria-valuemin="0" aria-valuemax="100">
                                                    <?php echo $plan['progress_percentage']; ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $plan['plan_status'] === 'on_track' ? 'success' : ($plan['plan_status'] === 'delayed' ? 'warning' : 'primary'); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $plan['plan_status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($plan['target_timeline']): ?>
                                                <?php echo date('M Y', strtotime($plan['target_timeline'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="updatePlanProgress(<?php echo $plan['id']; ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-success" onclick="viewPlanDetails(<?php echo $plan['id']; ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Skills Assessment Tab -->
                    <div class="tab-pane fade" id="skills-tab">
                        <div class="mb-3">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSkillAssessmentModal">
                                <i class="bi bi-plus-circle"></i> Add Skill Assessment
                            </button>
                        </div>
                        
                        <div id="skillsContent">
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-mortarboard fs-1"></i>
                                <p class="mt-3">Select an employee to view skill assessments</p>
                            </div>
                        </div>
                    </div>

                    <!-- Development Activities Tab -->
                    <div class="tab-pane fade" id="activities-tab">
                        <div class="mb-3">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDevelopmentActivityModal">
                                <i class="bi bi-plus-circle"></i> Add Development Activity
                            </button>
                        </div>
                        
                        <div class="row">
                            <?php while ($activity = mysqli_fetch_assoc($recent_activities)): ?>
                            <div class="col-lg-6 col-xl-4 mb-3">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="card-title mb-0">
                                                <i class="bi bi-activity me-2 text-primary"></i>
                                                <?php echo htmlspecialchars($activity['activity_name']); ?>
                                            </h6>
                                            <span class="badge bg-<?php echo $activity['status'] === 'completed' ? 'success' : ($activity['status'] === 'in_progress' ? 'primary' : 'secondary'); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $activity['status'])); ?>
                                            </span>
                                        </div>
                                        <div class="mb-2">
                                            <strong>Employee:</strong> <?php echo htmlspecialchars($activity['employee_name']); ?>
                                        </div>
                                        <div class="mb-2">
                                            <strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $activity['activity_type'])); ?>
                                        </div>
                                        <?php if ($activity['start_date']): ?>
                                        <div class="mb-2">
                                            <strong>Start Date:</strong> <?php echo date('M d, Y', strtotime($activity['start_date'])); ?>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($activity['completion_deadline']): ?>
                                        <div class="mb-2">
                                            <strong>Deadline:</strong> 
                                            <span class="<?php echo strtotime($activity['completion_deadline']) < time() && $activity['status'] !== 'completed' ? 'text-danger' : ''; ?>">
                                                <?php echo date('M d, Y', strtotime($activity['completion_deadline'])); ?>
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                        <div class="progress mb-2" style="height: 15px;">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?php echo $activity['completion_percentage']; ?>%" 
                                                 aria-valuenow="<?php echo $activity['completion_percentage']; ?>" 
                                                 aria-valuemin="0" aria-valuemax="100">
                                                <?php echo $activity['completion_percentage']; ?>%
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-light">
                                        <button class="btn btn-sm btn-outline-primary" onclick="updateActivityStatus(<?php echo $activity['id']; ?>)">
                                            <i class="bi bi-pencil"></i> Update
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Career Path Modal -->
<div class="modal fade" id="addCareerPathModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Career Path</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addCareerPathForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Path Name *</label>
                                <input type="text" class="form-control" name="path_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Department</label>
                                <input type="text" class="form-control" name="department">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Entry Level Position</label>
                                <input type="text" class="form-control" name="entry_level_position">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Target Position</label>
                                <input type="text" class="form-control" name="target_position">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Duration (Months)</label>
                                <input type="number" class="form-control" name="estimated_duration_months" min="1" value="12">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Salary Min ($)</label>
                                <input type="number" class="form-control" name="salary_range_min" min="0" step="1000">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Salary Max ($)</label>
                                <input type="number" class="form-control" name="salary_range_max" min="0" step="1000">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Required Skills</label>
                                <textarea class="form-control" name="required_skills" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Required Qualifications</label>
                                <textarea class="form-control" name="required_qualifications" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Career Path</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Create Career Plan Modal -->
<div class="modal fade" id="createCareerPlanModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Career Plan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createCareerPlanForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Employee *</label>
                                <select class="form-select" name="employee_id" required>
                                    <option value="">Select Employee</option>
                                    <?php 
                                    mysqli_data_seek($employees, 0);
                                    while ($employee = mysqli_fetch_assoc($employees)): 
                                    ?>
                                    <option value="<?php echo $employee['id']; ?>">
                                        <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?> 
                                        (<?php echo htmlspecialchars($employee['employee_id']); ?>)
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Career Path (Optional)</label>
                                <select class="form-select" name="career_path_id">
                                    <option value="">Custom Plan</option>
                                    <?php 
                                    mysqli_data_seek($career_paths, 0);
                                    while ($path = mysqli_fetch_assoc($career_paths)): 
                                    ?>
                                    <option value="<?php echo $path['id']; ?>"><?php echo htmlspecialchars($path['path_name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Current Position</label>
                                <input type="text" class="form-control" name="current_position">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Target Position *</label>
                                <input type="text" class="form-control" name="target_position" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Target Timeline</label>
                                <input type="date" class="form-control" name="target_timeline">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Assign Mentor</label>
                                <select class="form-select" name="mentor_assigned">
                                    <option value="">Select Mentor</option>
                                    <?php while ($mentor = mysqli_fetch_assoc($mentors)): ?>
                                    <option value="<?php echo $mentor['id']; ?>">
                                        <?php echo htmlspecialchars($mentor['first_name'] . ' ' . $mentor['last_name']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Current Skills</label>
                                <textarea class="form-control" name="current_skills" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Skills to Develop</label>
                                <textarea class="form-control" name="skills_to_develop" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Development Actions</label>
                        <textarea class="form-control" name="development_actions" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Create Career Plan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Skill Assessment Modal -->
<div class="modal fade" id="addSkillAssessmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Skill Assessment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addSkillAssessmentForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Employee *</label>
                        <select class="form-select" name="employee_id" required>
                            <option value="">Select Employee</option>
                            <?php 
                            mysqli_data_seek($employees, 0);
                            while ($employee = mysqli_fetch_assoc($employees)): 
                            ?>
                            <option value="<?php echo $employee['id']; ?>">
                                <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Skill Name *</label>
                        <input type="text" class="form-control" name="skill_name" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Skill Category</label>
                                <select class="form-select" name="skill_category">
                                    <option value="technical">Technical</option>
                                    <option value="soft_skills">Soft Skills</option>
                                    <option value="leadership">Leadership</option>
                                    <option value="communication">Communication</option>
                                    <option value="analytical">Analytical</option>
                                    <option value="project_management">Project Management</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Assessment Date</label>
                                <input type="date" class="form-control" name="assessment_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Current Level</label>
                                <select class="form-select" name="current_level">
                                    <option value="beginner">Beginner</option>
                                    <option value="intermediate" selected>Intermediate</option>
                                    <option value="advanced">Advanced</option>
                                    <option value="expert">Expert</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Target Level</label>
                                <select class="form-select" name="target_level">
                                    <option value="beginner">Beginner</option>
                                    <option value="intermediate">Intermediate</option>
                                    <option value="advanced" selected>Advanced</option>
                                    <option value="expert">Expert</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Self Rating (1-5)</label>
                                <input type="range" class="form-range" name="self_rating" min="1" max="5" value="3" oninput="this.nextElementSibling.value=this.value">
                                <output>3</output>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Development Priority</label>
                                <select class="form-select" name="development_priority">
                                    <option value="high">High</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="low">Low</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Assessment Method</label>
                        <select class="form-select" name="assessment_method">
                            <option value="self_assessment" selected>Self Assessment</option>
                            <option value="manager_review">Manager Review</option>
                            <option value="peer_review">Peer Review</option>
                            <option value="formal_test">Formal Test</option>
                            <option value="360_feedback">360 Feedback</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Evidence/Examples</label>
                        <textarea class="form-control" name="evidence_provided" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Assessment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Development Activity Modal -->
<div class="modal fade" id="addDevelopmentActivityModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Development Activity</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addDevelopmentActivityForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Employee *</label>
                                <select class="form-select" name="employee_id" required>
                                    <option value="">Select Employee</option>
                                    <?php 
                                    mysqli_data_seek($employees, 0);
                                    while ($employee = mysqli_fetch_assoc($employees)): 
                                    ?>
                                    <option value="<?php echo $employee['id']; ?>">
                                        <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Activity Type</label>
                                <select class="form-select" name="activity_type">
                                    <option value="training" selected>Training</option>
                                    <option value="mentoring">Mentoring</option>
                                    <option value="job_shadowing">Job Shadowing</option>
                                    <option value="project_assignment">Project Assignment</option>
                                    <option value="certification">Certification</option>
                                    <option value="conference">Conference</option>
                                    <option value="workshop">Workshop</option>
                                    <option value="reading">Reading</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Activity Name *</label>
                        <input type="text" class="form-control" name="activity_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Completion Deadline</label>
                                <input type="date" class="form-control" name="completion_deadline">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Budget Allocated ($)</label>
                                <input type="number" step="0.01" class="form-control" name="budget_allocated" min="0" value="0">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Provider/Vendor</label>
                        <input type="text" class="form-control" name="provider_name">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Activity</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Form submissions
document.getElementById('addCareerPathForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'add_career_path');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Career path created successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
});

document.getElementById('createCareerPlanForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'create_career_plan');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Career plan created successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
});

document.getElementById('addSkillAssessmentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'add_skill_assessment');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Skill assessment added successfully!');
            document.getElementById('addSkillAssessmentModal').querySelector('.btn-close').click();
        } else {
            alert('Error: ' + data.message);
        }
    });
});

document.getElementById('addDevelopmentActivityForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'add_development_activity');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Development activity added successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
});

// Helper functions
function viewCareerPath(id) {
    alert('Career path details view - to be implemented');
}

function deleteCareerPath(id) {
    if (confirm('Are you sure you want to delete this career path?')) {
        const formData = new FormData();
        formData.append('action', 'delete_career_path');
        formData.append('id', id);
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                location.reload();
            }
        });
    }
}

function updatePlanProgress(planId) {
    // This would open a progress update modal
    alert('Progress update modal - to be implemented');
}

function viewPlanDetails(planId) {
    alert('Plan details view - to be implemented');
}

function updateActivityStatus(activityId) {
    alert('Activity status update - to be implemented');
}

// Set minimum dates
document.querySelector('[name="target_timeline"]').min = new Date().toISOString().split('T')[0];
document.querySelector('[name="start_date"]').min = new Date().toISOString().split('T')[0];
document.querySelector('[name="completion_deadline"]').min = new Date().toISOString().split('T')[0];
</script>

<?php include '../layouts/footer.php'; ?>
