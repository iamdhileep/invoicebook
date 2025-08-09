<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

include '../db.php';
$page_title = 'Training Management';

// Create training management tables
$createTrainingProgramsTable = "CREATE TABLE IF NOT EXISTS hr_training_programs (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    program_name VARCHAR(200) NOT NULL,
    program_type ENUM('orientation', 'skill_development', 'compliance', 'leadership', 'technical', 'safety', 'soft_skills', 'other') DEFAULT 'other',
    description TEXT,
    instructor_name VARCHAR(100),
    instructor_type ENUM('internal', 'external') DEFAULT 'internal',
    duration_hours INT DEFAULT 0,
    max_participants INT DEFAULT 20,
    cost_per_participant DECIMAL(10,2) DEFAULT 0,
    materials_provided TEXT,
    prerequisites TEXT,
    certification_provided ENUM('yes', 'no') DEFAULT 'no',
    status ENUM('active', 'inactive', 'draft') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB";
mysqli_query($conn, $createTrainingProgramsTable);

$createTrainingSessionsTable = "CREATE TABLE IF NOT EXISTS hr_training_sessions (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    program_id INT(11) NOT NULL,
    session_name VARCHAR(200) NOT NULL,
    session_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    location VARCHAR(200),
    venue_type ENUM('classroom', 'online', 'hybrid', 'on_site') DEFAULT 'classroom',
    meeting_link VARCHAR(500),
    current_participants INT DEFAULT 0,
    status ENUM('scheduled', 'ongoing', 'completed', 'cancelled', 'postponed') DEFAULT 'scheduled',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES hr_training_programs(id) ON DELETE CASCADE
) ENGINE=InnoDB";
mysqli_query($conn, $createTrainingSessionsTable);

$createTrainingEnrollmentsTable = "CREATE TABLE IF NOT EXISTS hr_training_enrollments (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    employee_id INT(11) NOT NULL,
    session_id INT(11) NOT NULL,
    program_id INT(11) NOT NULL,
    enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    enrollment_status ENUM('enrolled', 'waitlisted', 'cancelled', 'no_show') DEFAULT 'enrolled',
    attendance_status ENUM('present', 'absent', 'partial', 'pending') DEFAULT 'pending',
    completion_status ENUM('completed', 'incomplete', 'failed', 'pending') DEFAULT 'pending',
    completion_date DATE,
    score DECIMAL(5,2),
    feedback_rating INT DEFAULT 5,
    feedback_comments TEXT,
    certificate_issued ENUM('yes', 'no') DEFAULT 'no',
    certificate_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES hr_training_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (program_id) REFERENCES hr_training_programs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (employee_id, session_id)
) ENGINE=InnoDB";
mysqli_query($conn, $createTrainingEnrollmentsTable);

$createTrainingEvaluationsTable = "CREATE TABLE IF NOT EXISTS hr_training_evaluations (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    session_id INT(11) NOT NULL,
    employee_id INT(11) NOT NULL,
    overall_rating INT DEFAULT 5,
    content_quality INT DEFAULT 5,
    instructor_rating INT DEFAULT 5,
    material_quality INT DEFAULT 5,
    would_recommend ENUM('yes', 'no') DEFAULT 'yes',
    most_valuable_aspect TEXT,
    improvement_suggestions TEXT,
    additional_comments TEXT,
    evaluation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES hr_training_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
    UNIQUE KEY unique_evaluation (session_id, employee_id)
) ENGINE=InnoDB";
mysqli_query($conn, $createTrainingEvaluationsTable);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_program':
            $program_name = mysqli_real_escape_string($conn, $_POST['program_name']);
            $program_type = mysqli_real_escape_string($conn, $_POST['program_type']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $instructor_name = mysqli_real_escape_string($conn, $_POST['instructor_name']);
            $instructor_type = mysqli_real_escape_string($conn, $_POST['instructor_type']);
            $duration_hours = intval($_POST['duration_hours']);
            $max_participants = intval($_POST['max_participants']);
            $cost_per_participant = floatval($_POST['cost_per_participant']);
            $prerequisites = mysqli_real_escape_string($conn, $_POST['prerequisites']);
            $certification_provided = mysqli_real_escape_string($conn, $_POST['certification_provided']);
            
            $query = "INSERT INTO hr_training_programs (program_name, program_type, description, instructor_name, instructor_type, duration_hours, max_participants, cost_per_participant, prerequisites, certification_provided) 
                      VALUES ('$program_name', '$program_type', '$description', '$instructor_name', '$instructor_type', $duration_hours, $max_participants, $cost_per_participant, '$prerequisites', '$certification_provided')";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Training program added successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;
            
        case 'schedule_session':
            $program_id = intval($_POST['program_id']);
            $session_name = mysqli_real_escape_string($conn, $_POST['session_name']);
            $session_date = mysqli_real_escape_string($conn, $_POST['session_date']);
            $start_time = mysqli_real_escape_string($conn, $_POST['start_time']);
            $end_time = mysqli_real_escape_string($conn, $_POST['end_time']);
            $location = mysqli_real_escape_string($conn, $_POST['location']);
            $venue_type = mysqli_real_escape_string($conn, $_POST['venue_type']);
            $meeting_link = mysqli_real_escape_string($conn, $_POST['meeting_link']);
            
            $query = "INSERT INTO hr_training_sessions (program_id, session_name, session_date, start_time, end_time, location, venue_type, meeting_link) 
                      VALUES ($program_id, '$session_name', '$session_date', '$start_time', '$end_time', '$location', '$venue_type', '$meeting_link')";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Training session scheduled successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;
            
        case 'enroll_employee':
            $employee_id = intval($_POST['employee_id']);
            $session_id = intval($_POST['session_id']);
            $program_id = intval($_POST['program_id']);
            
            // Check if already enrolled
            $checkQuery = "SELECT id FROM hr_training_enrollments WHERE employee_id = $employee_id AND session_id = $session_id";
            $checkResult = mysqli_query($conn, $checkQuery);
            
            if (mysqli_num_rows($checkResult) > 0) {
                echo json_encode(['success' => false, 'message' => 'Employee is already enrolled in this session!']);
                exit;
            }
            
            // Check session capacity
            $capacityQuery = "SELECT ts.current_participants, tp.max_participants 
                             FROM hr_training_sessions ts 
                             JOIN hr_training_programs tp ON ts.program_id = tp.id 
                             WHERE ts.id = $session_id";
            $capacityResult = mysqli_query($conn, $capacityQuery);
            $capacity = mysqli_fetch_assoc($capacityResult);
            
            $enrollment_status = ($capacity['current_participants'] < $capacity['max_participants']) ? 'enrolled' : 'waitlisted';
            
            $query = "INSERT INTO hr_training_enrollments (employee_id, session_id, program_id, enrollment_status) 
                      VALUES ($employee_id, $session_id, $program_id, '$enrollment_status')";
            
            if (mysqli_query($conn, $query)) {
                // Update participant count if enrolled
                if ($enrollment_status === 'enrolled') {
                    mysqli_query($conn, "UPDATE hr_training_sessions SET current_participants = current_participants + 1 WHERE id = $session_id");
                }
                echo json_encode(['success' => true, 'message' => 'Employee enrolled successfully!', 'status' => $enrollment_status]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;
            
        case 'update_attendance':
            $enrollment_id = intval($_POST['enrollment_id']);
            $attendance_status = mysqli_real_escape_string($conn, $_POST['attendance_status']);
            
            $query = "UPDATE hr_training_enrollments SET attendance_status = '$attendance_status' WHERE id = $enrollment_id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Attendance updated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;
            
        case 'mark_completion':
            $enrollment_id = intval($_POST['enrollment_id']);
            $completion_status = mysqli_real_escape_string($conn, $_POST['completion_status']);
            $score = isset($_POST['score']) ? floatval($_POST['score']) : null;
            
            $scoreQuery = $score ? ", score = $score" : "";
            $completionDate = ($completion_status === 'completed') ? ", completion_date = CURDATE()" : "";
            
            $query = "UPDATE hr_training_enrollments SET 
                      completion_status = '$completion_status' $scoreQuery $completionDate 
                      WHERE id = $enrollment_id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Completion status updated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;
            
        case 'submit_evaluation':
            $session_id = intval($_POST['session_id']);
            $employee_id = intval($_POST['employee_id']);
            $overall_rating = intval($_POST['overall_rating']);
            $content_quality = intval($_POST['content_quality']);
            $instructor_rating = intval($_POST['instructor_rating']);
            $material_quality = intval($_POST['material_quality']);
            $would_recommend = mysqli_real_escape_string($conn, $_POST['would_recommend']);
            $most_valuable = mysqli_real_escape_string($conn, $_POST['most_valuable']);
            $improvements = mysqli_real_escape_string($conn, $_POST['improvements']);
            $comments = mysqli_real_escape_string($conn, $_POST['comments']);
            
            $query = "INSERT INTO hr_training_evaluations 
                      (session_id, employee_id, overall_rating, content_quality, instructor_rating, material_quality, would_recommend, most_valuable_aspect, improvement_suggestions, additional_comments) 
                      VALUES ($session_id, $employee_id, $overall_rating, $content_quality, $instructor_rating, $material_quality, '$would_recommend', '$most_valuable', '$improvements', '$comments')
                      ON DUPLICATE KEY UPDATE 
                      overall_rating = $overall_rating, content_quality = $content_quality, instructor_rating = $instructor_rating, 
                      material_quality = $material_quality, would_recommend = '$would_recommend', most_valuable_aspect = '$most_valuable', 
                      improvement_suggestions = '$improvements', additional_comments = '$comments'";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Training evaluation submitted successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;
            
        case 'get_program_sessions':
            $program_id = intval($_POST['program_id']);
            $query = "SELECT * FROM hr_training_sessions WHERE program_id = $program_id AND status IN ('scheduled', 'ongoing') ORDER BY session_date";
            $result = mysqli_query($conn, $query);
            $sessions = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $sessions[] = $row;
            }
            echo json_encode(['success' => true, 'sessions' => $sessions]);
            exit;
            
        case 'delete_program':
            $id = intval($_POST['id']);
            
            // Check if program has sessions
            $checkQuery = "SELECT COUNT(*) as session_count FROM hr_training_sessions WHERE program_id = $id";
            $checkResult = mysqli_query($conn, $checkQuery);
            $check = mysqli_fetch_assoc($checkResult);
            
            if ($check['session_count'] > 0) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete program with scheduled sessions!']);
                exit;
            }
            
            $query = "DELETE FROM hr_training_programs WHERE id = $id";
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Training program deleted successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;
            
        case 'delete_session':
            $id = intval($_POST['id']);
            
            // Get enrolled count
            $countQuery = "SELECT COUNT(*) as enrollment_count FROM hr_training_enrollments WHERE session_id = $id";
            $countResult = mysqli_query($conn, $countQuery);
            $count = mysqli_fetch_assoc($countResult);
            
            if ($count['enrollment_count'] > 0) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete session with enrolled participants!']);
                exit;
            }
            
            $query = "DELETE FROM hr_training_sessions WHERE id = $id";
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Training session deleted successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;
    }
}

// Get statistics
$stats = [];
$stats['total_programs'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM hr_training_programs WHERE status = 'active'"))['count'];
$stats['scheduled_sessions'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM hr_training_sessions WHERE status IN ('scheduled', 'ongoing')"))['count'];
$stats['total_enrollments'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM hr_training_enrollments WHERE enrollment_status = 'enrolled'"))['count'];
$stats['completion_rate'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT ROUND(AVG(CASE WHEN completion_status = 'completed' THEN 1 ELSE 0 END) * 100, 1) as rate FROM hr_training_enrollments"))['rate'] ?? 0;

// Get training programs with session counts
$programs = mysqli_query($conn, "
    SELECT tp.*, 
           COUNT(ts.id) as session_count,
           COUNT(CASE WHEN ts.status IN ('scheduled', 'ongoing') THEN 1 END) as active_sessions
    FROM hr_training_programs tp 
    LEFT JOIN hr_training_sessions ts ON tp.id = ts.program_id 
    GROUP BY tp.id 
    ORDER BY tp.created_at DESC
");

// Get upcoming sessions
$upcoming_sessions = mysqli_query($conn, "
    SELECT ts.*, tp.program_name, tp.program_type,
           COUNT(te.id) as enrolled_count
    FROM hr_training_sessions ts 
    JOIN hr_training_programs tp ON ts.program_id = tp.id 
    LEFT JOIN hr_training_enrollments te ON ts.id = te.session_id AND te.enrollment_status = 'enrolled'
    WHERE ts.session_date >= CURDATE() AND ts.status = 'scheduled'
    GROUP BY ts.id 
    ORDER BY ts.session_date, ts.start_time 
    LIMIT 10
");

// Get employees for enrollment
$employees = mysqli_query($conn, "SELECT id, first_name, last_name, employee_id, department FROM hr_employees WHERE status = 'active' ORDER BY first_name");

include '../layouts/header.php';
include '../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">🎓 Training Management</h1>
                <p class="text-muted">Manage employee training programs and development</p>
            </div>
            <div>
                <a href="index.php" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-left"></i> Back to HRMS
                </a>
                <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addProgramModal">
                    <i class="bi bi-plus-circle"></i> Add Program
                </button>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#scheduleSessionModal">
                    <i class="bi bi-calendar-plus"></i> Schedule Session
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
                                <h6 class="card-title text-white-50 mb-2">Active Programs</h6>
                                <h3 class="mb-0"><?php echo $stats['total_programs']; ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-book fs-2 text-white-50"></i>
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
                                <h6 class="card-title text-white-50 mb-2">Scheduled Sessions</h6>
                                <h3 class="mb-0"><?php echo $stats['scheduled_sessions']; ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-calendar-event fs-2 text-white-50"></i>
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
                                <h6 class="card-title text-white-50 mb-2">Total Enrollments</h6>
                                <h3 class="mb-0"><?php echo $stats['total_enrollments']; ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-people fs-2 text-white-50"></i>
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
                                <h6 class="card-title text-white-50 mb-2">Completion Rate</h6>
                                <h3 class="mb-0"><?php echo $stats['completion_rate']; ?>%</h3>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-award fs-2 text-white-50"></i>
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
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#programs-tab">
                            <i class="bi bi-book me-2"></i>Training Programs
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#sessions-tab">
                            <i class="bi bi-calendar-event me-2"></i>Upcoming Sessions
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#enrollments-tab">
                            <i class="bi bi-people me-2"></i>Enrollments
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#analytics-tab">
                            <i class="bi bi-graph-up me-2"></i>Analytics
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <!-- Training Programs Tab -->
                    <div class="tab-pane fade show active" id="programs-tab">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Program Name</th>
                                        <th>Type</th>
                                        <th>Instructor</th>
                                        <th>Duration</th>
                                        <th>Sessions</th>
                                        <th>Max Participants</th>
                                        <th>Cost</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($program = mysqli_fetch_assoc($programs)): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($program['program_name']); ?></div>
                                            <small class="text-muted"><?php echo substr($program['description'], 0, 50) . (strlen($program['description']) > 50 ? '...' : ''); ?></small>
                                        </td>
                                        <td><span class="badge bg-secondary"><?php echo ucfirst(str_replace('_', ' ', $program['program_type'])); ?></span></td>
                                        <td>
                                            <?php echo htmlspecialchars($program['instructor_name']); ?>
                                            <br><small class="text-muted"><?php echo ucfirst($program['instructor_type']); ?></small>
                                        </td>
                                        <td><?php echo $program['duration_hours']; ?> hours</td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $program['active_sessions']; ?> Active</span>
                                            <br><small class="text-muted"><?php echo $program['session_count']; ?> Total</small>
                                        </td>
                                        <td><?php echo $program['max_participants']; ?></td>
                                        <td>$<?php echo number_format($program['cost_per_participant'], 2); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $program['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($program['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewProgram(<?php echo $program['id']; ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteProgram(<?php echo $program['id']; ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Upcoming Sessions Tab -->
                    <div class="tab-pane fade" id="sessions-tab">
                        <div class="row">
                            <?php while ($session = mysqli_fetch_assoc($upcoming_sessions)): ?>
                            <div class="col-lg-6 col-xl-4 mb-4">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="card-title mb-0">
                                            <i class="bi bi-calendar-event me-2"></i>
                                            <?php echo htmlspecialchars($session['session_name']); ?>
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <strong>Program:</strong> <?php echo htmlspecialchars($session['program_name']); ?>
                                            <span class="badge bg-secondary ms-2"><?php echo ucfirst(str_replace('_', ' ', $session['program_type'])); ?></span>
                                        </div>
                                        <div class="mb-2">
                                            <i class="bi bi-calendar3 text-muted me-2"></i>
                                            <strong><?php echo date('M d, Y', strtotime($session['session_date'])); ?></strong>
                                        </div>
                                        <div class="mb-2">
                                            <i class="bi bi-clock text-muted me-2"></i>
                                            <?php echo date('h:i A', strtotime($session['start_time'])); ?> - 
                                            <?php echo date('h:i A', strtotime($session['end_time'])); ?>
                                        </div>
                                        <div class="mb-2">
                                            <i class="bi bi-geo-alt text-muted me-2"></i>
                                            <?php echo htmlspecialchars($session['location']); ?>
                                            <span class="badge bg-info ms-2"><?php echo ucfirst($session['venue_type']); ?></span>
                                        </div>
                                        <div class="mb-3">
                                            <i class="bi bi-people text-muted me-2"></i>
                                            <strong><?php echo $session['enrolled_count']; ?></strong> enrolled
                                        </div>
                                        <?php if ($session['meeting_link']): ?>
                                        <div class="mb-3">
                                            <a href="<?php echo htmlspecialchars($session['meeting_link']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-camera-video"></i> Join Meeting
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-footer bg-light">
                                        <button class="btn btn-sm btn-primary" onclick="enrollInSession(<?php echo $session['id']; ?>, <?php echo $session['program_id']; ?>)">
                                            <i class="bi bi-person-plus"></i> Enroll Employee
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="manageAttendance(<?php echo $session['id']; ?>)">
                                            <i class="bi bi-check-square"></i> Attendance
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>

                    <!-- Enrollments Tab -->
                    <div class="tab-pane fade" id="enrollments-tab">
                        <div class="mb-3">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#enrollEmployeeModal">
                                <i class="bi bi-person-plus"></i> Enroll Employee
                            </button>
                        </div>
                        
                        <div id="enrollmentsContent">
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-people fs-1"></i>
                                <p class="mt-3">Select a session to view enrollments</p>
                            </div>
                        </div>
                    </div>

                    <!-- Analytics Tab -->
                    <div class="tab-pane fade" id="analytics-tab">
                        <div class="row">
                            <div class="col-lg-6 mb-4">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-light">
                                        <h6 class="card-title mb-0">Training Completion Trends</h6>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="completionTrendsChart" height="300"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6 mb-4">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-light">
                                        <h6 class="card-title mb-0">Program Type Distribution</h6>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="programTypeChart" height="300"></canvas>
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

<!-- Add Training Program Modal -->
<div class="modal fade" id="addProgramModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Training Program</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addProgramForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Program Name *</label>
                                <input type="text" class="form-control" name="program_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Program Type *</label>
                                <select class="form-select" name="program_type" required>
                                    <option value="">Select Type</option>
                                    <option value="orientation">Orientation</option>
                                    <option value="skill_development">Skill Development</option>
                                    <option value="compliance">Compliance</option>
                                    <option value="leadership">Leadership</option>
                                    <option value="technical">Technical</option>
                                    <option value="safety">Safety</option>
                                    <option value="soft_skills">Soft Skills</option>
                                    <option value="other">Other</option>
                                </select>
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
                                <label class="form-label">Instructor Name *</label>
                                <input type="text" class="form-control" name="instructor_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Instructor Type</label>
                                <select class="form-select" name="instructor_type">
                                    <option value="internal">Internal</option>
                                    <option value="external">External</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Duration (Hours)</label>
                                <input type="number" class="form-control" name="duration_hours" min="1" value="8">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Max Participants</label>
                                <input type="number" class="form-control" name="max_participants" min="1" value="20">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Cost per Participant</label>
                                <input type="number" step="0.01" class="form-control" name="cost_per_participant" min="0" value="0">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Prerequisites</label>
                                <textarea class="form-control" name="prerequisites" rows="2"></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Certification Provided</label>
                                <select class="form-select" name="certification_provided">
                                    <option value="no">No</option>
                                    <option value="yes">Yes</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Program</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Schedule Session Modal -->
<div class="modal fade" id="scheduleSessionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Schedule Training Session</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="scheduleSessionForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Training Program *</label>
                                <select class="form-select" name="program_id" required>
                                    <option value="">Select Program</option>
                                    <?php 
                                    mysqli_data_seek($programs, 0);
                                    while ($program = mysqli_fetch_assoc($programs)): 
                                    ?>
                                    <option value="<?php echo $program['id']; ?>"><?php echo htmlspecialchars($program['program_name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Session Name *</label>
                                <input type="text" class="form-control" name="session_name" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Session Date *</label>
                                <input type="date" class="form-control" name="session_date" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Start Time *</label>
                                <input type="time" class="form-control" name="start_time" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">End Time *</label>
                                <input type="time" class="form-control" name="end_time" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Location/Venue *</label>
                                <input type="text" class="form-control" name="location" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Venue Type</label>
                                <select class="form-select" name="venue_type">
                                    <option value="classroom">Classroom</option>
                                    <option value="online">Online</option>
                                    <option value="hybrid">Hybrid</option>
                                    <option value="on_site">On Site</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Meeting Link (for online sessions)</label>
                        <input type="url" class="form-control" name="meeting_link" placeholder="https://...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Schedule Session</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Enroll Employee Modal -->
<div class="modal fade" id="enrollEmployeeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Enroll Employee in Training</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="enrollEmployeeForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Employee *</label>
                        <select class="form-select" name="employee_id" required>
                            <option value="">Select Employee</option>
                            <?php while ($employee = mysqli_fetch_assoc($employees)): ?>
                            <option value="<?php echo $employee['id']; ?>">
                                <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?> 
                                (<?php echo htmlspecialchars($employee['employee_id']); ?>)
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Training Program *</label>
                        <select class="form-select" name="program_id_enroll" required onchange="loadProgramSessions(this.value)">
                            <option value="">Select Program</option>
                            <?php 
                            mysqli_data_seek($programs, 0);
                            while ($program = mysqli_fetch_assoc($programs)): 
                            ?>
                            <option value="<?php echo $program['id']; ?>"><?php echo htmlspecialchars($program['program_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Training Session *</label>
                        <select class="form-select" name="session_id" required>
                            <option value="">Select Session</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Enroll Employee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Form submissions
document.getElementById('addProgramForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'add_program');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Training program added successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
});

document.getElementById('scheduleSessionForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'schedule_session');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Training session scheduled successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
});

document.getElementById('enrollEmployeeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'enroll_employee');
    formData.append('program_id', document.querySelector('[name="program_id_enroll"]').value);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Employee enrolled successfully! Status: ' + data.status);
            document.getElementById('enrollEmployeeModal').querySelector('.btn-close').click();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
});

// Load sessions for selected program
function loadProgramSessions(programId) {
    if (!programId) {
        document.querySelector('[name="session_id"]').innerHTML = '<option value="">Select Session</option>';
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'get_program_sessions');
    formData.append('program_id', programId);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        const sessionSelect = document.querySelector('[name="session_id"]');
        sessionSelect.innerHTML = '<option value="">Select Session</option>';
        
        if (data.success && data.sessions.length > 0) {
            data.sessions.forEach(session => {
                sessionSelect.innerHTML += `<option value="${session.id}">
                    ${session.session_name} - ${session.session_date} (${session.start_time})
                </option>`;
            });
        } else {
            sessionSelect.innerHTML += '<option value="" disabled>No sessions available</option>';
        }
    });
}

// Enroll in session
function enrollInSession(sessionId, programId) {
    document.querySelector('[name="session_id"]').innerHTML = `<option value="${sessionId}" selected>Loading...</option>`;
    document.querySelector('[name="program_id_enroll"]').value = programId;
    loadProgramSessions(programId);
    new bootstrap.Modal(document.getElementById('enrollEmployeeModal')).show();
}

// Delete functions
function deleteProgram(id) {
    if (confirm('Are you sure you want to delete this training program?')) {
        const formData = new FormData();
        formData.append('action', 'delete_program');
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

function deleteSession(id) {
    if (confirm('Are you sure you want to delete this training session?')) {
        const formData = new FormData();
        formData.append('action', 'delete_session');
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

// View program details
function viewProgram(id) {
    // This would open a detailed view modal
    alert('Program details view - to be implemented');
}

// Manage attendance
function manageAttendance(sessionId) {
    // This would open attendance management modal
    alert('Attendance management - to be implemented');
}

// Initialize charts when analytics tab is shown
document.querySelector('[data-bs-target="#analytics-tab"]').addEventListener('click', function() {
    setTimeout(initializeCharts, 100);
});

function initializeCharts() {
    // Completion trends chart
    const completionCtx = document.getElementById('completionTrendsChart');
    if (completionCtx && !completionCtx.chart) {
        completionCtx.chart = new Chart(completionCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Completion Rate',
                    data: [85, 88, 92, 87, 90, 94],
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
    }
    
    // Program type chart
    const typeCtx = document.getElementById('programTypeChart');
    if (typeCtx && !typeCtx.chart) {
        typeCtx.chart = new Chart(typeCtx, {
            type: 'doughnut',
            data: {
                labels: ['Technical', 'Compliance', 'Leadership', 'Safety', 'Soft Skills'],
                datasets: [{
                    data: [30, 25, 20, 15, 10],
                    backgroundColor: [
                        '#667eea',
                        '#764ba2',
                        '#f093fb',
                        '#f5576c',
                        '#4facfe'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
}

// Set minimum date to today
document.querySelector('[name="session_date"]').min = new Date().toISOString().split('T')[0];
</script>

<?php include '../layouts/footer.php'; ?>
