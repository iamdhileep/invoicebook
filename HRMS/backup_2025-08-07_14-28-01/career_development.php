<?php
$page_title = "Career Development & Growth";

// Include authentication and database
require_once '../auth_check.php';
require_once '../db.php';

// Include layouts
require_once 'hrms_header_simple.php';
require_once 'hrms_sidebar_simple.php';

// Include HRMS UI fix
$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'] ?? 'employee';

// Create career development tables
$createCareerPathsTable = "
CREATE TABLE IF NOT EXISTS hr_career_paths (
    id INT AUTO_INCREMENT PRIMARY KEY,
    path_name VARCHAR(100) NOT NULL,
    department VARCHAR(100),
    from_position VARCHAR(100) NOT NULL,
    to_position VARCHAR(100) NOT NULL,
    required_experience_years INT DEFAULT 0,
    required_skills TEXT,
    required_certifications TEXT,
    estimated_timeline_months INT DEFAULT 12,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($createCareerPathsTable);

$createCareerGoalsTable = "
CREATE TABLE IF NOT EXISTS hr_career_goals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    goal_title VARCHAR(200) NOT NULL,
    target_position VARCHAR(100),
    target_department VARCHAR(100),
    target_date DATE,
    current_progress INT DEFAULT 0,
    description TEXT,
    required_skills TEXT,
    development_plan TEXT,
    status ENUM('active', 'completed', 'paused', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES hr_employees(employee_id)
)";
$conn->query($createCareerGoalsTable);

$createSkillAssessmentsTable = "
CREATE TABLE IF NOT EXISTS hr_skill_assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    skill_name VARCHAR(100) NOT NULL,
    skill_category ENUM('technical', 'soft_skills', 'leadership', 'communication', 'other') DEFAULT 'technical',
    current_level ENUM('beginner', 'intermediate', 'advanced', 'expert') DEFAULT 'beginner',
    target_level ENUM('beginner', 'intermediate', 'advanced', 'expert') DEFAULT 'intermediate',
    assessment_date DATE NOT NULL,
    assessor_id INT,
    notes TEXT,
    development_needed BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES hr_employees(employee_id)
)";
$conn->query($createSkillAssessmentsTable);

$createMentorshipTable = "
CREATE TABLE IF NOT EXISTS hr_mentorship (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mentee_id INT NOT NULL,
    mentor_id INT NOT NULL,
    program_name VARCHAR(100),
    start_date DATE NOT NULL,
    end_date DATE,
    frequency ENUM('weekly', 'biweekly', 'monthly') DEFAULT 'monthly',
    focus_areas TEXT,
    goals TEXT,
    status ENUM('active', 'completed', 'paused', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mentee_id) REFERENCES hr_employees(employee_id),
    FOREIGN KEY (mentor_id) REFERENCES hr_employees(employee_id)
)";
$conn->query($createMentorshipTable);

// Insert sample data if tables are empty
$checkPaths = $conn->query("SELECT COUNT(*) as count FROM hr_career_paths");
if ($checkPaths && $checkPaths->fetch_assoc()['count'] == 0) {
    $samplePaths = [
        ['Software Developer Track', 'IT', 'Junior Developer', 'Senior Developer', 2, 'Java, Python, React, Database Design', 'AWS Certified Developer', 24, 'Path to senior development role'],
        ['Management Track', 'General', 'Team Lead', 'Department Manager', 3, 'Leadership, Communication, Strategic Planning', 'PMP Certification', 18, 'Leadership development path'],
        ['Sales Career Path', 'Sales', 'Sales Representative', 'Sales Manager', 2, 'Negotiation, CRM, Customer Relations', 'Sales Certification', 15, 'Sales leadership progression'],
        ['HR Specialist Track', 'HR', 'HR Assistant', 'HR Business Partner', 3, 'HRIS, Employment Law, Compensation', 'SHRM-CP', 30, 'HR specialization path'],
        ['Finance Analyst Path', 'Finance', 'Finance Analyst', 'Senior Finance Manager', 4, 'Financial Modeling, Excel, SAP', 'CPA or CFA', 36, 'Finance career advancement']
    ];
    
    foreach ($samplePaths as $path) {
        $stmt = $conn->prepare("INSERT INTO hr_career_paths (path_name, department, from_position, to_position, required_experience_years, required_skills, required_certifications, estimated_timeline_months, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssissis", $path[0], $path[1], $path[2], $path[3], $path[4], $path[5], $path[6], $path[7], $path[8]);
        $stmt->execute();
    }
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'create_career_goal':
            $employee_id = intval($_POST['employee_id'] ?? $currentUserId);
            $goal_title = $conn->real_escape_string($_POST['goal_title'] ?? '');
            $target_position = $conn->real_escape_string($_POST['target_position'] ?? '');
            $target_department = $conn->real_escape_string($_POST['target_department'] ?? '');
            $target_date = $_POST['target_date'] ?? null;
            $description = $conn->real_escape_string($_POST['description'] ?? '');
            $required_skills = $conn->real_escape_string($_POST['required_skills'] ?? '');
            $development_plan = $conn->real_escape_string($_POST['development_plan'] ?? '');
            
            if ($goal_title) {
                $stmt = $conn->prepare("INSERT INTO hr_career_goals (employee_id, goal_title, target_position, target_department, target_date, description, required_skills, development_plan) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssssss", $employee_id, $goal_title, $target_position, $target_department, $target_date, $description, $required_skills, $development_plan);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Career goal created successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error creating goal: ' . $conn->error]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Goal title is required']);
            }
            exit;
            
        case 'update_goal_progress':
            $goal_id = intval($_POST['goal_id'] ?? 0);
            $progress = intval($_POST['progress'] ?? 0);
            
            if ($goal_id && $progress >= 0 && $progress <= 100) {
                $status = $progress == 100 ? 'completed' : 'active';
                $stmt = $conn->prepare("UPDATE hr_career_goals SET current_progress = ?, status = ? WHERE id = ?");
                $stmt->bind_param("isi", $progress, $status, $goal_id);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Progress updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error updating progress']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
            }
            exit;
            
        case 'add_skill_assessment':
            $employee_id = intval($_POST['employee_id'] ?? $currentUserId);
            $skill_name = $conn->real_escape_string($_POST['skill_name'] ?? '');
            $skill_category = $conn->real_escape_string($_POST['skill_category'] ?? 'technical');
            $current_level = $conn->real_escape_string($_POST['current_level'] ?? 'beginner');
            $target_level = $conn->real_escape_string($_POST['target_level'] ?? 'intermediate');
            $assessment_date = $_POST['assessment_date'] ?? date('Y-m-d');
            $notes = $conn->real_escape_string($_POST['notes'] ?? '');
            
            if ($skill_name) {
                $stmt = $conn->prepare("INSERT INTO hr_skill_assessments (employee_id, skill_name, skill_category, current_level, target_level, assessment_date, assessor_id, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssssIs", $employee_id, $skill_name, $skill_category, $current_level, $target_level, $assessment_date, $currentUserId, $notes);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Skill assessment added successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error adding assessment: ' . $conn->error]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Skill name is required']);
            }
            exit;
            
        case 'get_career_paths':
            $department = $_POST['department'] ?? '';
            $position = $_POST['current_position'] ?? '';
            
            $sql = "SELECT * FROM hr_career_paths WHERE 1=1";
            $params = [];
            $types = "";
            
            if ($department) {
                $sql .= " AND (department = ? OR department = 'General')";
                $params[] = $department;
                $types .= "s";
            }
            
            if ($position) {
                $sql .= " AND from_position LIKE ?";
                $params[] = "%$position%";
                $types .= "s";
            }
            
            $sql .= " ORDER BY estimated_timeline_months";
            
            $stmt = $conn->prepare($sql);
            if ($params) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            $paths = [];
            while ($row = $result->fetch_assoc()) {
                $paths[] = $row;
            }
            
            echo json_encode(['success' => true, 'data' => $paths]);
            exit;
    }
}

// Get employee's current goals
$myGoals = [];
$goalsResult = $conn->query("SELECT * FROM hr_career_goals WHERE employee_id = $currentUserId ORDER BY created_at DESC");
if ($goalsResult) {
    while ($row = $goalsResult->fetch_assoc()) {
        $myGoals[] = $row;
    }
}

// Get employee's skill assessments
$mySkills = [];
$skillsResult = $conn->query("SELECT * FROM hr_skill_assessments WHERE employee_id = $currentUserId ORDER BY assessment_date DESC");
if ($skillsResult) {
    while ($row = $skillsResult->fetch_assoc()) {
        $mySkills[] = $row;
    }
}

// Get career paths
$careerPaths = [];
$pathsResult = $conn->query("SELECT * FROM hr_career_paths ORDER BY department, estimated_timeline_months");
if ($pathsResult) {
    while ($row = $pathsResult->fetch_assoc()) {
        $careerPaths[] = $row;
    }
}

// Get mentorship opportunities (for display)
$mentorshipOpps = [];
$mentorResult = $conn->query("
    SELECT CONCAT(first_name, ' ', last_name) as mentor_name, designation, department, email 
    FROM hr_employees 
    WHERE employee_id != $currentUserId AND (designation LIKE '%Senior%' OR designation LIKE '%Manager%' OR designation LIKE '%Lead%') 
    LIMIT 10
");
if ($mentorResult) {
    while ($row = $mentorResult->fetch_assoc()) {
        $mentorshipOpps[] = $row;
    }
}
?>

<div class="content-wrapper">
    <div class="container-fluid">
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-arrow-up mr-2"></i>Career Development & Growth
            </h1>
            <div class="btn-group">
                <button class="btn btn-primary" onclick="showCreateGoalModal()">
                    <i class="fas fa-bullseye mr-1"></i>New Goal
                </button>
                <button class="btn btn-success" onclick="showSkillAssessmentModal()">
                    <i class="fas fa-chart-line mr-1"></i>Add Skill
                </button>
            </div>
        </div>

        <!-- Career Overview Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Active Goals</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo count(array_filter($myGoals, function($g) { return $g['status'] === 'active'; })); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-bullseye fa-2x text-gray-300"></i>
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
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Completed Goals</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo count(array_filter($myGoals, function($g) { return $g['status'] === 'completed'; })); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-check-circle fa-2x text-gray-300"></i>
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
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Skills Tracked</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($mySkills); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-chart-line fa-2x text-gray-300"></i>
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
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Career Paths</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($careerPaths); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-route fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- My Career Goals -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">My Career Goals</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($myGoals)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-bullseye fa-3x text-muted mb-3"></i>
                                <h5>No Career Goals Set</h5>
                                <p class="text-muted">Start planning your career growth by setting your first goal.</p>
                                <button class="btn btn-primary" onclick="showCreateGoalModal()">Create First Goal</button>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($myGoals as $goal): ?>
                                <div class="col-lg-6 mb-4">
                                    <div class="card border-left-<?php echo getGoalStatusColor($goal['status']); ?> h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="card-title mb-0"><?php echo htmlspecialchars($goal['goal_title']); ?></h6>
                                                <span class="badge badge-<?php echo getGoalStatusColor($goal['status']); ?>">
                                                    <?php echo ucfirst($goal['status']); ?>
                                                </span>
                                            </div>
                                            
                                            <?php if ($goal['target_position']): ?>
                                                <p class="mb-2"><strong>Target:</strong> <?php echo htmlspecialchars($goal['target_position']); ?>
                                                <?php if ($goal['target_department']): ?>
                                                    in <?php echo htmlspecialchars($goal['target_department']); ?>
                                                <?php endif; ?>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <?php if ($goal['target_date']): ?>
                                                <p class="mb-2"><strong>Target Date:</strong> <?php echo date('M d, Y', strtotime($goal['target_date'])); ?></p>
                                            <?php endif; ?>
                                            
                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <small class="text-muted">Progress</small>
                                                    <small class="text-muted"><?php echo $goal['current_progress']; ?>%</small>
                                                </div>
                                                <div class="progress" style="height: 8px;">
                                                    <div class="progress-bar bg-<?php echo getGoalStatusColor($goal['status']); ?>" 
                                                         style="width: <?php echo $goal['current_progress']; ?>%"></div>
                                                </div>
                                            </div>
                                            
                                            <?php if ($goal['description']): ?>
                                                <p class="card-text text-muted small"><?php echo htmlspecialchars($goal['description']); ?></p>
                                            <?php endif; ?>
                                            
                                            <div class="mt-3">
                                                <button class="btn btn-sm btn-outline-primary" onclick="updateGoalProgress(<?php echo $goal['id']; ?>, <?php echo $goal['current_progress']; ?>)">
                                                    <i class="fas fa-edit"></i> Update Progress
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Skills Development -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Skills Development Tracker</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($mySkills)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                                <h5>No Skills Assessed</h5>
                                <p class="text-muted">Track your skill development and identify areas for growth.</p>
                                <button class="btn btn-success" onclick="showSkillAssessmentModal()">Add First Skill</button>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Skill</th>
                                            <th>Category</th>
                                            <th>Current Level</th>
                                            <th>Target Level</th>
                                            <th>Progress</th>
                                            <th>Last Updated</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($mySkills as $skill): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($skill['skill_name']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo getSkillCategoryColor($skill['skill_category']); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $skill['skill_category'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo ucfirst($skill['current_level']); ?></td>
                                            <td><?php echo ucfirst($skill['target_level']); ?></td>
                                            <td>
                                                <?php 
                                                $levels = ['beginner' => 1, 'intermediate' => 2, 'advanced' => 3, 'expert' => 4];
                                                $currentNum = $levels[$skill['current_level']];
                                                $targetNum = $levels[$skill['target_level']];
                                                $progress = $targetNum > $currentNum ? (($currentNum / $targetNum) * 100) : 100;
                                                ?>
                                                <div class="progress" style="height: 6px; width: 100px;">
                                                    <div class="progress-bar" style="width: <?php echo $progress; ?>%"></div>
                                                </div>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($skill['assessment_date'])); ?></td>
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

        <!-- Career Paths Explorer -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Career Paths Explorer</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($careerPaths as $path): ?>
                            <div class="col-lg-6 col-xl-4 mb-4">
                                <div class="card border-left-info h-100">
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($path['path_name']); ?></h6>
                                        <div class="mb-2">
                                            <strong><?php echo htmlspecialchars($path['from_position']); ?></strong>
                                            <i class="fas fa-arrow-right mx-2 text-muted"></i>
                                            <strong><?php echo htmlspecialchars($path['to_position']); ?></strong>
                                        </div>
                                        
                                        <div class="small text-muted mb-2">
                                            <div><i class="fas fa-building mr-1"></i><?php echo htmlspecialchars($path['department']); ?></div>
                                            <div><i class="fas fa-clock mr-1"></i><?php echo $path['estimated_timeline_months']; ?> months</div>
                                            <div><i class="fas fa-user-clock mr-1"></i><?php echo $path['required_experience_years']; ?> years experience</div>
                                        </div>
                                        
                                        <?php if ($path['required_skills']): ?>
                                            <div class="mb-2">
                                                <small class="text-muted">Key Skills:</small>
                                                <div class="small"><?php echo htmlspecialchars($path['required_skills']); ?></div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="mt-3">
                                            <button class="btn btn-sm btn-outline-primary" onclick="exploreCareerPath(<?php echo $path['id']; ?>)">
                                                <i class="fas fa-search"></i> Explore
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Create Goal Modal -->
<div class="modal fade" id="createGoalModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Career Goal</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="goalForm">
                    <input type="hidden" name="employee_id" value="<?php echo $currentUserId; ?>">
                    
                    <div class="mb-3">
                        <label for="goalTitle" class="form-label">Goal Title *</label>
                        <input type="text" class="form-control" id="goalTitle" name="goal_title" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="targetPosition" class="form-label">Target Position</label>
                            <input type="text" class="form-control" id="targetPosition" name="target_position">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="targetDepartment" class="form-label">Target Department</label>
                            <input type="text" class="form-control" id="targetDepartment" name="target_department">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="targetDate" class="form-label">Target Date</label>
                        <input type="date" class="form-control" id="targetDate" name="target_date">
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="requiredSkills" class="form-label">Required Skills</label>
                        <textarea class="form-control" id="requiredSkills" name="required_skills" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="developmentPlan" class="form-label">Development Plan</label>
                        <textarea class="form-control" id="developmentPlan" name="development_plan" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitGoal()">Create Goal</button>
            </div>
        </div>
    </div>
</div>

<!-- Skill Assessment Modal -->
<div class="modal fade" id="skillModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Skill Assessment</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="skillForm">
                    <input type="hidden" name="employee_id" value="<?php echo $currentUserId; ?>">
                    
                    <div class="mb-3">
                        <label for="skillName" class="form-label">Skill Name *</label>
                        <input type="text" class="form-control" id="skillName" name="skill_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="skillCategory" class="form-label">Category</label>
                        <select class="form-control" id="skillCategory" name="skill_category">
                            <option value="technical">Technical</option>
                            <option value="soft_skills">Soft Skills</option>
                            <option value="leadership">Leadership</option>
                            <option value="communication">Communication</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="currentLevel" class="form-label">Current Level</label>
                            <select class="form-control" id="currentLevel" name="current_level">
                                <option value="beginner">Beginner</option>
                                <option value="intermediate">Intermediate</option>
                                <option value="advanced">Advanced</option>
                                <option value="expert">Expert</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="targetLevel" class="form-label">Target Level</label>
                            <select class="form-control" id="targetLevel" name="target_level">
                                <option value="beginner">Beginner</option>
                                <option value="intermediate">Intermediate</option>
                                <option value="advanced">Advanced</option>
                                <option value="expert">Expert</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="assessmentDate" class="form-label">Assessment Date</label>
                        <input type="date" class="form-control" id="assessmentDate" name="assessment_date" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="submitSkillAssessment()">Add Skill</button>
            </div>
        </div>
    </div>
</div>

<script>
function showCreateGoalModal() {
    new bootstrap.Modal(document.getElementById('createGoalModal')).show();
}

function showSkillAssessmentModal() {
    new bootstrap.Modal(document.getElementById('skillModal')).show();
}

function submitGoal() {
    const formData = new FormData(document.getElementById('goalForm'));
    formData.append('action', 'create_career_goal');
    
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

function submitSkillAssessment() {
    const formData = new FormData(document.getElementById('skillForm'));
    formData.append('action', 'add_skill_assessment');
    
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

function updateGoalProgress(goalId, currentProgress) {
    const newProgress = prompt('Enter progress percentage (0-100):', currentProgress);
    
    if (newProgress !== null && !isNaN(newProgress) && newProgress >= 0 && newProgress <= 100) {
        const formData = new FormData();
        formData.append('action', 'update_goal_progress');
        formData.append('goal_id', goalId);
        formData.append('progress', newProgress);
        
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

function exploreCareerPath(pathId) {
    alert('Career path exploration feature - this would show detailed requirements, timeline, and next steps for this career path.');
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
</style>

<?php 
require_once 'hrms_footer_simple.php';

function getGoalStatusColor($status) {
    $colors = [
        'active' => 'primary',
        'completed' => 'success',
        'paused' => 'warning',
        'cancelled' => 'danger'
    ];
    return $colors[$status] ?? 'secondary';
}

function getSkillCategoryColor($category) {
    $colors = [
        'technical' => 'primary',
        'soft_skills' => 'success',
        'leadership' => 'warning',
        'communication' => 'info',
        'other' => 'secondary'
    ];
    return $colors[$category] ?? 'secondary';
}

require_once 'hrms_footer_simple.php';
?>