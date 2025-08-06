<?php
session_start();
// Check for either session variable for compatibility
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Include config and database
if (!isset($root_path)) 
require_once '../config.php';
if (!isset($root_path)) 
include '../db.php';

$page_title = 'Training Schedule - HRMS';

// Database-driven training data
$training_programs = [];
$query = "SELECT 
    id,
    program_name as title,
    CONCAT(duration_hours, ' Hours') as duration,
    start_date as date,
    trainer_name as trainer,
    max_participants as max_capacity,
    0 as attendees,
    status,
    category as type
FROM training_programs 
ORDER BY start_date DESC";

$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $training_programs[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'duration' => $row['duration'],
            'date' => $row['date'],
            'trainer' => $row['trainer'],
            'attendees' => $row['attendees'],
            'max_capacity' => $row['max_capacity'],
            'status' => $row['status'],
            'type' => $row['type']
        ];
    }
}

$current_page = 'training_schedule';

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
                    <i class="bi bi-calendar-event text-primary me-3"></i>Training Schedule
                </h1>
                <p class="text-muted" style="font-size: 1.1rem;">Manage training programs, schedules, and employee development</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" onclick="generateTrainingReport()">
                    <i class="bi bi-graph-up me-2"></i>Training Report
                </button>
                <button class="btn btn-primary" onclick="scheduleNewTraining()">
                    <i class="bi bi-plus-lg me-2"></i>Schedule Training
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="d-flex justify-content-center mb-3">
                            <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                                <i class="bi bi-calendar-event-fill text-primary fs-2"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-1">3</h3>
                        <p class="text-muted mb-0">Total Programs</p>
                        <small class="text-primary">Active training sessions</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="d-flex justify-content-center mb-3">
                            <div class="bg-success bg-opacity-10 rounded-circle p-3">
                                <i class="bi bi-people-fill text-success fs-2"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-1">48</h3>
                        <p class="text-muted mb-0">Total Attendees</p>
                        <small class="text-success">Enrolled participants</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="d-flex justify-content-center mb-3">
                            <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                                <i class="bi bi-clock-fill text-warning fs-2"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-1">2</h3>
                        <p class="text-muted mb-0">Upcoming</p>
                        <small class="text-warning">This month</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="d-flex justify-content-center mb-3">
                            <div class="bg-info bg-opacity-10 rounded-circle p-3">
                                <i class="bi bi-check-circle-fill text-info fs-2"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-1">85%</h3>
                        <p class="text-muted mb-0">Completion Rate</p>
                        <small class="text-info">Average attendance</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Tabs -->
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#upcoming" role="tab">
                            <i class="bi bi-calendar-event me-2"></i>Upcoming Trainings
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#calendar" role="tab">
                            <i class="bi bi-calendar3 me-2"></i>Training Calendar
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#materials" role="tab">
                            <i class="bi bi-journal-text me-2"></i>Training Materials
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#feedback" role="tab">
                            <i class="bi bi-chat-square-dots me-2"></i>Feedback
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <!-- Upcoming Trainings Tab -->
                    <div class="tab-pane fade show active" id="upcoming" role="tabpanel">
                        <div class="row g-4">
                            <?php foreach ($training_programs as $training): ?>
                            <div class="col-lg-6 col-xl-4">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <?php 
                                            $typeClass = [
                                                'Leadership' => 'bg-primary text-white',
                                                'Technical' => 'bg-success text-white',
                                                'Soft Skills' => 'bg-warning text-dark'
                                            ][$training['type']] ?? 'bg-secondary text-white';
                                            
                                            $statusClass = [
                                                'scheduled' => 'bg-primary text-white',
                                                'open' => 'bg-success text-white',
                                                'completed' => 'bg-info text-white'
                                            ][$training['status']] ?? 'bg-secondary text-white';
                                            ?>
                                            <span class="badge <?= $typeClass ?> rounded-pill">
                                                <?= htmlspecialchars($training['type']) ?>
                                            </span>
                                            <span class="badge <?= $statusClass ?> rounded-pill text-uppercase">
                                                <?= ucfirst($training['status']) ?>
                                            </span>
                                        </div>
                                        
                                        <h6 class="fw-bold mb-3"><?= htmlspecialchars($training['title']) ?></h6>
                                        
                                        <div class="d-flex align-items-center mb-2 text-muted">
                                            <i class="bi bi-calendar me-2"></i>
                                            <span><?= date('F j, Y', strtotime($training['date'])) ?></span>
                                        </div>
                                        
                                        <div class="d-flex align-items-center mb-2 text-muted">
                                            <i class="bi bi-clock me-2"></i>
                                            <span><?= $training['duration'] ?></span>
                                        </div>
                                        
                                        <div class="d-flex align-items-center mb-3 text-muted">
                                            <i class="bi bi-person me-2"></i>
                                            <span><?= htmlspecialchars($training['trainer']) ?></span>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between mb-2">
                                                <span class="small text-muted">Enrollment Progress</span>
                                                <span class="small fw-semibold"><?= $training['attendees'] ?>/<?= $training['max_capacity'] ?></span>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-gradient" 
                                                     style="width: <?= ($training['attendees'] / $training['max_capacity']) * 100 ?>%"></div>
                                            </div>
                                        </div>
                                        
                                        <div class="d-grid gap-2">
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="viewTrainingDetails(<?= $training['id'] ?>)" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-secondary" onclick="manageAttendees(<?= $training['id'] ?>)" title="Manage Attendees">
                                                    <i class="bi bi-people"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-info" onclick="editTraining(<?= $training['id'] ?>)" title="Edit Training">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Training Calendar Tab -->
                    <div class="tab-pane fade" id="calendar" role="tabpanel">
                        <div class="card shadow-sm">
                            <div class="card-header bg-gradient bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-calendar3 me-2"></i>August 2025 Training Calendar
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-center">Sun</th>
                                                <th class="text-center">Mon</th>
                                                <th class="text-center">Tue</th>
                                                <th class="text-center">Wed</th>
                                                <th class="text-center">Thu</th>
                                                <th class="text-center">Fri</th>
                                                <th class="text-center">Sat</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="p-3 text-center" style="height: 100px; width: 14.28%;"></td>
                                                <td class="p-3 text-center"></td>
                                                <td class="p-3 text-center"></td>
                                                <td class="p-3 text-center"></td>
                                                <td class="p-3 text-center">
                                                    <div class="fw-bold">1</div>
                                                </td>
                                                <td class="p-3 text-center">
                                                    <div class="fw-bold">2</div>
                                                </td>
                                                <td class="p-3 text-center">
                                                    <div class="fw-bold">3</div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="p-3 text-center" style="height: 100px;">
                                                    <div class="fw-bold">4</div>
                                                </td>
                                                <td class="p-3 text-center">
                                                    <div class="fw-bold">5</div>
                                                    <div class="badge bg-info small mt-1">Today</div>
                                                </td>
                                                <td class="p-3 text-center">
                                                    <div class="fw-bold">6</div>
                                                </td>
                                                <td class="p-3 text-center">
                                                    <div class="fw-bold">7</div>
                                                </td>
                                                <td class="p-3 text-center">
                                                    <div class="fw-bold">8</div>
                                                </td>
                                                <td class="p-3 text-center">
                                                    <div class="fw-bold">9</div>
                                                </td>
                                                <td class="p-3 text-center">
                                                    <div class="fw-bold">10</div>
                                                    <div class="badge bg-warning small mt-1">Soft Skills</div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="p-3 text-center" style="height: 100px;">
                                                    <div class="fw-bold">11</div>
                                                </td>
                                                <td class="p-3 text-center">
                                                    <div class="fw-bold">12</div>
                                                </td>
                                                <td class="p-3 text-center">
                                                    <div class="fw-bold">13</div>
                                                </td>
                                                <td class="p-3 text-center">
                                                    <div class="fw-bold">14</div>
                                                </td>
                                                <td class="p-3 text-center">
                                                    <div class="fw-bold">15</div>
                                                    <div class="badge bg-primary small mt-1">Leadership</div>
                                                </td>
                                                <td class="p-3 text-center">
                                                    <div class="fw-bold">16</div>
                                                </td>
                                                <td class="p-3 text-center">
                                                    <div class="fw-bold">17</div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="p-3 text-center" style="height: 100px;">
                                                    <div class="fw-bold">18</div>
                                                </td>
                                                <td class="p-3 text-center">
                                                    <div class="fw-bold">19</div>
                                                </td>
                                                <td class="p-3 text-center">
                                                    <div class="fw-bold">20</div>
                                                    <div class="badge bg-success small mt-1">Technical</div>
                                                </td>
                                                <td class="p-3 text-center">
                                                    <div class="fw-bold">21</div>
                                                </td>
                                                <td class="p-3 text-center">
                                                    <div class="fw-bold">22</div>
                                                </td>
                                                <td class="p-3 text-center">
                                                    <div class="fw-bold">23</div>
                                                </td>
                                                <td class="p-3 text-center">
                                                    <div class="fw-bold">24</div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="p-3 text-center" style="height: 100px;">
                                                    <div class="fw-bold">25</div>
                                                </td>
                                                <td class="p-3 text-center">
                                                    <div class="fw-bold">26</div>
                                                </td>
                                                <td class="p-3 text-center">
                                                    <div class="fw-bold">27</div>
                                                </td>
                                                <td class="p-3 text-center">
                                                    <div class="fw-bold">28</div>
                                                </td>
                                                <td class="p-3 text-center">
                                                    <div class="fw-bold">29</div>
                                                </td>
                                                <td class="p-3 text-center">
                                                    <div class="fw-bold">30</div>
                                                </td>
                                                <td class="p-3 text-center">
                                                    <div class="fw-bold">31</div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Training Materials Tab -->
                    <div class="tab-pane fade" id="materials" role="tabpanel">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header bg-gradient bg-success text-white">
                                        <h5 class="card-title mb-0">
                                            <i class="bi bi-journal-text me-2"></i>Training Materials Library
                                        </h5>
                                    </div>
                                    <div class="card-body text-center">
                                        <i class="bi bi-journal-text display-1 text-success opacity-75"></i>
                                        <h6 class="mt-3">Access Training Resources</h6>
                                        <p class="text-muted">Access training materials, resources, and documentation for all programs.</p>
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-success" onclick="viewMaterials()">
                                                <i class="bi bi-folder-open me-2"></i>Browse Materials
                                            </button>
                                            <button class="btn btn-outline-success" onclick="uploadMaterials()">
                                                <i class="bi bi-upload me-2"></i>Upload Materials
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header bg-gradient bg-info text-white">
                                        <h5 class="card-title mb-0">
                                            <i class="bi bi-file-earmark-pdf me-2"></i>Recent Materials
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="list-group list-group-flush">
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <i class="bi bi-file-pdf text-danger me-2"></i>
                                                    <span class="fw-semibold">Leadership Handbook</span>
                                                </div>
                                                <button class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-download"></i>
                                                </button>
                                            </div>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <i class="bi bi-file-text text-primary me-2"></i>
                                                    <span class="fw-semibold">Technical Guidelines</span>
                                                </div>
                                                <button class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-download"></i>
                                                </button>
                                            </div>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <i class="bi bi-file-earmark-slides text-warning me-2"></i>
                                                    <span class="fw-semibold">Communication Slides</span>
                                                </div>
                                                <button class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-download"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Feedback Tab -->
                    <div class="tab-pane fade" id="feedback" role="tabpanel">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header bg-gradient bg-warning text-dark">
                                        <h5 class="card-title mb-0">
                                            <i class="bi bi-chat-square-dots me-2"></i>Training Feedback Overview
                                        </h5>
                                    </div>
                                    <div class="card-body text-center">
                                        <i class="bi bi-chat-square-dots display-1 text-warning opacity-75"></i>
                                        <h6 class="mt-3">Feedback Analytics</h6>
                                        <p class="text-muted">Collect and analyze feedback from training participants to improve programs.</p>
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-warning" onclick="viewFeedbackReports()">
                                                <i class="bi bi-clipboard-data me-2"></i>View Feedback Reports
                                            </button>
                                            <button class="btn btn-outline-warning" onclick="createFeedbackForm()">
                                                <i class="bi bi-plus-circle me-2"></i>Create Feedback Form
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header bg-gradient bg-secondary text-white">
                                        <h5 class="card-title mb-0">
                                            <i class="bi bi-star me-2"></i>Recent Feedback
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3 p-3 bg-light rounded">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="fw-semibold">Leadership Program</span>
                                                <div>
                                                    <i class="bi bi-star-fill text-warning"></i>
                                                    <i class="bi bi-star-fill text-warning"></i>
                                                    <i class="bi bi-star-fill text-warning"></i>
                                                    <i class="bi bi-star-fill text-warning"></i>
                                                    <i class="bi bi-star text-muted"></i>
                                                </div>
                                            </div>
                                            <p class="small text-muted mb-0">"Excellent content and delivery. Very practical and applicable."</p>
                                        </div>
                                        
                                        <div class="mb-3 p-3 bg-light rounded">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="fw-semibold">Technical Skills</span>
                                                <div>
                                                    <i class="bi bi-star-fill text-warning"></i>
                                                    <i class="bi bi-star-fill text-warning"></i>
                                                    <i class="bi bi-star-fill text-warning"></i>
                                                    <i class="bi bi-star-fill text-warning"></i>
                                                    <i class="bi bi-star-fill text-warning"></i>
                                                </div>
                                            </div>
                                            <p class="small text-muted mb-0">"Outstanding hands-on training. Highly recommended!"</p>
                                        </div>
                                        
                                        <div class="text-center">
                                            <button class="btn btn-sm btn-outline-secondary">View All Feedback</button>
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

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewTrainingDetails(trainingId) {
            showAlert(`Viewing details for training ${trainingId}...`, 'info');
        }

        function manageAttendees(trainingId) {
            showAlert(`Managing attendees for training ${trainingId}...`, 'info');
        }

        function editTraining(trainingId) {
            showAlert(`Editing training ${trainingId}...`, 'warning');
        }

        function scheduleNewTraining() {
            showAlert('New training scheduling form will be implemented!', 'info');
        }

        function generateTrainingReport() {
            showAlert('Generating training report...', 'success');
        }

        function viewMaterials() {
            showAlert('Opening training materials library...', 'info');
        }

        function uploadMaterials() {
            showAlert('Material upload form will be implemented!', 'info');
        }

        function viewFeedbackReports() {
            showAlert('Opening feedback analytics dashboard...', 'info');
        }

        function createFeedbackForm() {
            showAlert('Feedback form builder will be implemented!', 'info');
        }

        function showAlert(message, type = 'info') {
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

<?php if (!isset($root_path))  include '../layouts/footer.php'; ?>
</body>
</html>
