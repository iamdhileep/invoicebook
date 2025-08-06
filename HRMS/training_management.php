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

$page_title = 'Training Management - HRMS';

// Create training management tables if not exist
$createTrainingProgramsTable = "
CREATE TABLE IF NOT EXISTS training_programs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    program_name VARCHAR(255) NOT NULL,
    program_description TEXT,
    program_category ENUM('technical', 'soft_skills', 'compliance', 'leadership', 'safety', 'orientation', 'other') DEFAULT 'technical',
    duration_hours INT NOT NULL,
    max_participants INT DEFAULT NULL,
    trainer_type ENUM('internal', 'external') DEFAULT 'internal',
    trainer_name VARCHAR(255),
    trainer_contact VARCHAR(255),
    training_mode ENUM('classroom', 'online', 'hybrid', 'workshop', 'webinar') DEFAULT 'classroom',
    prerequisites TEXT,
    objectives TEXT,
    certification_provided BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'inactive', 'archived') DEFAULT 'active',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES employees(employee_id)
)";
mysqli_query($conn, $createTrainingProgramsTable);

$createTrainingSchedulesTable = "
CREATE TABLE IF NOT EXISTS training_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    batch_name VARCHAR(255),
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    venue VARCHAR(255),
    max_participants INT DEFAULT NULL,
    current_participants INT DEFAULT 0,
    status ENUM('scheduled', 'ongoing', 'completed', 'cancelled') DEFAULT 'scheduled',
    instructor_id INT NULL,
    materials_required TEXT,
    special_instructions TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES training_programs(id),
    FOREIGN KEY (instructor_id) REFERENCES employees(employee_id)
)";
mysqli_query($conn, $createTrainingSchedulesTable);

$createTrainingEnrollmentsTable = "
CREATE TABLE IF NOT EXISTS training_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT NOT NULL,
    employee_id INT NOT NULL,
    enrollment_date DATE NOT NULL,
    enrollment_status ENUM('enrolled', 'waitlisted', 'cancelled', 'completed', 'no_show') DEFAULT 'enrolled',
    attendance_percentage DECIMAL(5,2) DEFAULT 0,
    completion_status ENUM('not_started', 'in_progress', 'completed', 'failed') DEFAULT 'not_started',
    pre_assessment_score DECIMAL(5,2) DEFAULT NULL,
    post_assessment_score DECIMAL(5,2) DEFAULT NULL,
    feedback_rating DECIMAL(3,2) DEFAULT NULL,
    feedback_comments TEXT,
    certificate_issued BOOLEAN DEFAULT FALSE,
    certificate_number VARCHAR(100),
    enrolled_by INT NOT NULL,
    completion_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (schedule_id) REFERENCES training_schedules(id),
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
    FOREIGN KEY (enrolled_by) REFERENCES employees(employee_id),
    UNIQUE KEY unique_enrollment (schedule_id, employee_id)
)";
mysqli_query($conn, $createTrainingEnrollmentsTable);

$createTrainingRequirementsTable = "
CREATE TABLE IF NOT EXISTS training_requirements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    program_id INT NOT NULL,
    requirement_type ENUM('mandatory', 'recommended', 'optional') DEFAULT 'mandatory',
    required_by_date DATE NULL,
    status ENUM('pending', 'enrolled', 'completed', 'overdue') DEFAULT 'pending',
    assigned_by INT NOT NULL,
    reason TEXT,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
    FOREIGN KEY (program_id) REFERENCES training_programs(id),
    FOREIGN KEY (assigned_by) REFERENCES employees(employee_id)
)";
mysqli_query($conn, $createTrainingRequirementsTable);

// Handle form submissions
if ($_POST) {
    // Add your form handling logic here
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_program':
                // Handle create program logic
                break;
            case 'schedule_training':
                // Handle schedule training logic
                break;
            case 'enroll_employee':
                // Handle employee enrollment logic
                break;
        }
    }
}

// Sample data for demo purposes
$activePrograms = [
    [
        'id' => 1,
        'program_name' => 'Leadership Development',
        'program_description' => 'Comprehensive leadership training program for managers',
        'program_category' => 'leadership',
        'duration_hours' => 40,
        'training_mode' => 'hybrid',
        'trainer_name' => 'John Smith',
        'trainer_type' => 'external',
        'certification_provided' => true
    ],
    [
        'id' => 2,
        'program_name' => 'Safety Training',
        'program_description' => 'Workplace safety and compliance training',
        'program_category' => 'safety',
        'duration_hours' => 8,
        'training_mode' => 'classroom',
        'trainer_name' => 'Safety Officer',
        'trainer_type' => 'internal',
        'certification_provided' => true
    ]
];

$upcomingTrainings = [
    [
        'id' => 1,
        'program_name' => 'Leadership Development',
        'program_category' => 'leadership',
        'batch_name' => 'Batch 1',
        'start_date' => '2025-08-15',
        'end_date' => '2025-08-20',
        'start_time' => '09:00',
        'end_time' => '17:00',
        'venue' => 'Conference Room A',
        'current_participants' => 15,
        'max_participants' => 20,
        'instructor_name' => 'John Smith'
    ]
];

$stats = [
    'active_programs' => count($activePrograms),
    'scheduled_trainings' => count($upcomingTrainings),
    'total_enrollments' => 45,
    'completed_trainings' => 23
];

$completionTrends = [
    ['month' => 'Jan', 'completed' => 5, 'total' => 8],
    ['month' => 'Feb', 'completed' => 7, 'total' => 10],
    ['month' => 'Mar', 'completed' => 6, 'total' => 9],
    ['month' => 'Apr', 'completed' => 8, 'total' => 12],
    ['month' => 'May', 'completed' => 9, 'total' => 15],
    ['month' => 'Jun', 'completed' => 10, 'total' => 16]
];

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
                    <i class="bi bi-mortarboard-fill text-primary me-3"></i>Training Management
                </h1>
                <p class="text-muted" style="font-size: 1.1rem;">Manage training programs, schedules, and employee development</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-info" onclick="exportTrainingReport()">
                    <i class="bi bi-download"></i> Export Report
                </button>
                <button class="btn btn-outline-success" onclick="viewTrainingAnalytics()">
                    <i class="bi bi-bar-chart"></i> Analytics
                </button>
                <div class="btn-group">
                    <button class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-plus-circle"></i> New
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#createProgramModal">
                            <i class="bi bi-book"></i> Training Program
                        </a></li>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#scheduleTrainingModal">
                            <i class="bi bi-calendar-plus"></i> Schedule Training
                        </a></li>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#enrollEmployeeModal">
                            <i class="bi bi-person-plus"></i> Enroll Employee
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white text-center p-4">
                        <div class="mb-3">
                            <i class="bi bi-book fs-1"></i>
                        </div>
                        <h3 class="mb-2 fw-bold"><?= intval($stats['active_programs']) ?></h3>
                        <p class="mb-0 opacity-90">Active Programs</p>
                        <small class="opacity-75">Available</small>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body text-white text-center p-4">
                        <div class="mb-3">
                            <i class="bi bi-calendar-event fs-1"></i>
                        </div>
                        <h3 class="mb-2 fw-bold"><?= intval($stats['scheduled_trainings']) ?></h3>
                        <p class="mb-0 opacity-90">Scheduled Trainings</p>
                        <small class="opacity-75">Upcoming</small>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body text-white text-center p-4">
                        <div class="mb-3">
                            <i class="bi bi-people fs-1"></i>
                        </div>
                        <h3 class="mb-2 fw-bold"><?= intval($stats['total_enrollments']) ?></h3>
                        <p class="mb-0 opacity-90">Total Enrollments</p>
                        <small class="opacity-75">Active</small>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <div class="card-body text-white text-center p-4">
                        <div class="mb-3">
                            <i class="bi bi-award fs-1"></i>
                        </div>
                        <h3 class="mb-2 fw-bold"><?= intval($stats['completed_trainings']) ?></h3>
                        <p class="mb-0 opacity-90">Completions</p>
                        <small class="opacity-75">Total</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="row g-4">
            <!-- Training Programs & Schedules -->
            <div class="col-xl-8">
                <!-- Training Programs -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-light border-0 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-book text-primary"></i> Active Training Programs
                        </h5>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createProgramModal">
                            <i class="bi bi-plus-circle"></i> Add Program
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="programsTable">
                                <thead>
                                    <tr>
                                        <th>Program Name</th>
                                        <th>Category</th>
                                        <th>Duration</th>
                                        <th>Mode</th>
                                        <th>Trainer</th>
                                        <th>Certification</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activePrograms as $program): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <h6 class="mb-0"><?= htmlspecialchars($program['program_name']) ?></h6>
                                                    <small class="text-muted"><?= htmlspecialchars(substr($program['program_description'], 0, 50)) ?>...</small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-info bg-opacity-10 text-info"><?= ucfirst(str_replace('_', ' ', $program['program_category'])) ?></span>
                                            </td>
                                            <td><?= $program['duration_hours'] ?> hrs</td>
                                            <td>
                                                <span class="badge bg-secondary bg-opacity-10 text-secondary"><?= ucfirst($program['training_mode']) ?></span>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?= htmlspecialchars($program['trainer_name']) ?></strong>
                                                    <br><small class="text-muted"><?= ucfirst($program['trainer_type']) ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($program['certification_provided']): ?>
                                                    <i class="bi bi-award-fill text-warning"></i> Yes
                                                <?php else: ?>
                                                    <i class="bi bi-x-circle text-muted"></i> No
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-info" 
                                                            onclick="viewProgram(<?= $program['id'] ?>)"
                                                            data-bs-toggle="tooltip" title="View Details">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button class="btn btn-outline-success" 
                                                            onclick="scheduleProgram(<?= $program['id'] ?>)"
                                                            data-bs-toggle="tooltip" title="Schedule Training">
                                                        <i class="bi bi-calendar-plus"></i>
                                                    </button>
                                                    <button class="btn btn-outline-primary" 
                                                            onclick="editProgram(<?= $program['id'] ?>)"
                                                            data-bs-toggle="tooltip" title="Edit Program">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Training Schedules -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light border-0 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-calendar-event text-warning"></i> Upcoming Training Schedules
                        </h5>
                        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#scheduleTrainingModal">
                            <i class="bi bi-calendar-plus"></i> Schedule Training
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="schedulesTable">
                                <thead>
                                    <tr>
                                        <th>Program</th>
                                        <th>Batch</th>
                                        <th>Date & Time</th>
                                        <th>Venue</th>
                                        <th>Participants</th>
                                        <th>Instructor</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcomingTrainings as $training): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <h6 class="mb-0"><?= htmlspecialchars($training['program_name']) ?></h6>
                                                    <small class="text-muted"><?= ucfirst(str_replace('_', ' ', $training['program_category'])) ?></small>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($training['batch_name']) ?></td>
                                            <td>
                                                <div>
                                                    <strong><?= date('M d, Y', strtotime($training['start_date'])) ?></strong>
                                                    <br><small><?= date('h:i A', strtotime($training['start_time'])) ?> - <?= date('h:i A', strtotime($training['end_time'])) ?></small>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($training['venue']) ?></td>
                                            <td>
                                                <div class="progress mb-1" style="height: 6px;">
                                                    <?php
                                                    $percentage = $training['max_participants'] ? ($training['current_participants'] / $training['max_participants'] * 100) : 0;
                                                    ?>
                                                    <div class="progress-bar bg-info" style="width: <?= $percentage ?>%"></div>
                                                </div>
                                                <small><?= $training['current_participants'] ?>/<?= $training['max_participants'] ?? '∞' ?></small>
                                            </td>
                                            <td><?= htmlspecialchars($training['instructor_name'] ?? 'TBD') ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-info" 
                                                            onclick="viewTrainingSchedule(<?= $training['id'] ?>)"
                                                            data-bs-toggle="tooltip" title="View Details">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button class="btn btn-outline-success" 
                                                            onclick="manageEnrollments(<?= $training['id'] ?>)"
                                                            data-bs-toggle="tooltip" title="Manage Enrollments">
                                                        <i class="bi bi-people"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar Info -->
            <div class="col-xl-4">
                <!-- Training Completion Trends -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-light border-0">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-graph-up text-success"></i> Training Completion Trends
                        </h6>
                    </div>
                    <div class="card-body">
                        <canvas id="completionTrendsChart" style="height: 200px;"></canvas>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light border-0">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-lightning-charge text-warning"></i> Quick Actions
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#createProgramModal">
                                <i class="bi bi-book"></i> Create Program
                            </button>
                            <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#scheduleTrainingModal">
                                <i class="bi bi-calendar-plus"></i> Schedule Training
                            </button>
                            <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#enrollEmployeeModal">
                                <i class="bi bi-person-plus"></i> Enroll Employee
                            </button>
                            <button class="btn btn-outline-info" onclick="viewTrainingReports()">
                                <i class="bi bi-bar-chart"></i> View Reports
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Program Modal -->
<div class="modal fade" id="createProgramModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Training Program</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_program">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Program Name *</label>
                            <input type="text" class="form-control" name="program_name" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Category *</label>
                            <select class="form-select" name="program_category" required>
                                <option value="">Select Category</option>
                                <option value="technical">Technical</option>
                                <option value="soft_skills">Soft Skills</option>
                                <option value="compliance">Compliance</option>
                                <option value="leadership">Leadership</option>
                                <option value="safety">Safety</option>
                                <option value="orientation">Orientation</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Program Description</label>
                            <textarea class="form-control" name="program_description" rows="3"></textarea>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Duration (Hours) *</label>
                            <input type="number" class="form-control" name="duration_hours" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Max Participants</label>
                            <input type="number" class="form-control" name="max_participants">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Training Mode *</label>
                            <select class="form-select" name="training_mode" required>
                                <option value="classroom">Classroom</option>
                                <option value="online">Online</option>
                                <option value="hybrid">Hybrid</option>
                                <option value="workshop">Workshop</option>
                                <option value="webinar">Webinar</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Trainer Type *</label>
                            <select class="form-select" name="trainer_type" required>
                                <option value="internal">Internal</option>
                                <option value="external">External</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Trainer Name</label>
                            <input type="text" class="form-control" name="trainer_name">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Trainer Contact</label>
                            <input type="text" class="form-control" name="trainer_contact">
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" name="certification_provided" id="certification">
                                <label class="form-check-label" for="certification">
                                    Certification Provided
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Prerequisites</label>
                            <textarea class="form-control" name="prerequisites" rows="2"></textarea>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Learning Objectives</label>
                            <textarea class="form-control" name="objectives" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Program</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Schedule Training Modal -->
<div class="modal fade" id="scheduleTrainingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Schedule Training</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="schedule_training">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Training Program *</label>
                            <select class="form-select" name="program_id" required>
                                <option value="">Select Program</option>
                                <?php foreach ($activePrograms as $program): ?>
                                    <option value="<?= $program['id'] ?>">
                                        <?= htmlspecialchars($program['program_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Batch Name</label>
                            <input type="text" class="form-control" name="batch_name" placeholder="e.g., Batch 1, Morning Session">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Start Date *</label>
                            <input type="date" class="form-control" name="start_date" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">End Date *</label>
                            <input type="date" class="form-control" name="end_date" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Start Time *</label>
                            <input type="time" class="form-control" name="start_time" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">End Time *</label>
                            <input type="time" class="form-control" name="end_time" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Venue</label>
                            <input type="text" class="form-control" name="venue" placeholder="e.g., Conference Room A, Online">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Max Participants</label>
                            <input type="number" class="form-control" name="max_participants">
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Materials Required</label>
                            <textarea class="form-control" name="materials_required" rows="2"></textarea>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Special Instructions</label>
                            <textarea class="form-control" name="special_instructions" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Schedule Training</button>
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
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="enroll_employee">
                    
                    <div class="mb-3">
                        <label class="form-label">Training Schedule *</label>
                        <select class="form-select" name="schedule_id" required>
                            <option value="">Select Training Schedule</option>
                            <?php foreach ($upcomingTrainings as $training): ?>
                                <option value="<?= $training['id'] ?>">
                                    <?= htmlspecialchars($training['program_name']) ?> - <?= htmlspecialchars($training['batch_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Employee *</label>
                        <select class="form-select" name="employee_id" required>
                            <option value="">Select Employee</option>
                            <!-- Employee options would be loaded from database -->
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Enrollment Status</label>
                        <select class="form-select" name="enrollment_status">
                            <option value="enrolled">Enrolled</option>
                            <option value="waitlisted">Waitlisted</option>
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
// Training Completion Trends Chart
const trendsCtx = document.getElementById('completionTrendsChart');
if (trendsCtx) {
    new Chart(trendsCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($completionTrends, 'month')) ?>,
            datasets: [{
                label: 'Completed',
                data: <?= json_encode(array_column($completionTrends, 'completed')) ?>,
                borderColor: '#198754',
                backgroundColor: 'rgba(25, 135, 84, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Total Enrollments',
                data: <?= json_encode(array_column($completionTrends, 'total')) ?>,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                tension: 0.4,
                fill: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

// Initialize DataTables
$(document).ready(function() {
    $('#programsTable').DataTable({
        responsive: true,
        pageLength: 10,
        order: [[0, 'asc']]
    });
    
    $('#schedulesTable').DataTable({
        responsive: true,
        pageLength: 10,
        order: [[2, 'asc']]
    });
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// View Program Function
function viewProgram(programId) {
    window.open('training_program_details.php?id=' + programId, '_blank');
}

// Schedule Program Function
function scheduleProgram(programId) {
    // Pre-select the program in the schedule modal
    const programSelect = document.querySelector('select[name="program_id"]');
    if (programSelect) {
        programSelect.value = programId;
    }
    const modal = new bootstrap.Modal(document.getElementById('scheduleTrainingModal'));
    modal.show();
}

// Edit Program Function
function editProgram(programId) {
    window.open('edit_training_program.php?id=' + programId, '_blank');
}

// View Training Schedule Function
function viewTrainingSchedule(scheduleId) {
    window.open('training_schedule_details.php?id=' + scheduleId, '_blank');
}

// Manage Enrollments Function
function manageEnrollments(scheduleId) {
    window.open('manage_training_enrollments.php?id=' + scheduleId, '_blank');
}

// Export Training Report
function exportTrainingReport() {
    window.open('api/export_training_report.php', '_blank');
}

// View Training Analytics
function viewTrainingAnalytics() {
    window.open('training_analytics.php', '_blank');
}

// View Training Reports
function viewTrainingReports() {
    window.open('training_reports.php', '_blank');
}
</script>

<?php if (!isset($root_path)) 
include '../layouts/footer.php'; ?>
