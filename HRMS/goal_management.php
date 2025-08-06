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

$page_title = 'Goal Management - HRMS';

// Fetch goals from database
$goals = [];
$result = mysqli_query($conn, "
    SELECT g.id, g.goal_title as title, e.name as employee, 
           COALESCE(e.department_name, 'Unassigned') as department,
           g.start_date, g.end_date, g.progress_percentage as progress, 
           g.status, g.priority
    FROM employee_goals g
    JOIN employees e ON g.employee_id = e.employee_id
    ORDER BY g.priority DESC, g.end_date ASC
");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $goals[] = $row;
    }
}

$current_page = 'goal_management';

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
                    <i class="bi bi-target text-primary me-3"></i>Goal Management
                </h1>
                <p class="text-muted" style="font-size: 1.1rem;">Set, track, and manage employee goals and objectives</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" onclick="exportGoalData()">
                    <i class="bi bi-download me-2"></i>Export Goals
                </button>
                <button class="btn btn-primary" onclick="showNewGoalModal()">
                    <i class="bi bi-plus-lg me-2"></i>Create Goal
                </button>
            </div>
        </div>

        <!-- Goal Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-target display-4" style="color: #1976d2;"></i>
                        </div>
                        <h3 class="fw-bold mb-1" style="color: #1976d2;"><?= count($goals) ?></h3>
                        <p class="text-muted mb-0">Total Goals</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-check-circle display-4" style="color: #388e3c;"></i>
                        </div>
                        <h3 class="fw-bold mb-1" style="color: #388e3c;">1</h3>
                        <p class="text-muted mb-0">Completed</p>
                        <small class="text-muted">20% completion rate</small>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fff3e0 0%, #ffcc02 100%);">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-clock display-4" style="color: #f57c00;"></i>
                        </div>
                        <h3 class="fw-bold mb-1" style="color: #f57c00;">4</h3>
                        <p class="text-muted mb-0">In Progress</p>
                        <small class="text-muted">56% average progress</small>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fce4ec 0%, #f8bbd9 100%);">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-exclamation-triangle display-4" style="color: #c2185b;"></i>
                        </div>
                        <h3 class="fw-bold mb-1" style="color: #c2185b;">0</h3>
                        <p class="text-muted mb-0">Overdue</p>
                        <small class="text-muted">No overdue goals</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Tabs -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#all-goals" role="tab">
                            <i class="bi bi-list-ul me-2"></i>All Goals
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#my-goals" role="tab">
                            <i class="bi bi-person me-2"></i>My Goals
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#team-goals" role="tab">
                            <i class="bi bi-people me-2"></i>Team Goals
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#templates" role="tab">
                            <i class="bi bi-file-earmark-text me-2"></i>Goal Templates
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body">
                <div class="tab-content">
                    <!-- All Goals Tab -->
                    <div class="tab-pane fade show active" id="all-goals" role="tabpanel">
                        <!-- Filters -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <label class="form-label">Department</label>
                                <select class="form-select">
                                    <option>All Departments</option>
                                    <option>Sales</option>
                                    <option>Development</option>
                                    <option>HR</option>
                                    <option>Support</option>
                                    <option>Design</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-select">
                                    <option>All Status</option>
                                    <option>In Progress</option>
                                    <option>Completed</option>
                                    <option>Pending</option>
                                    <option>Overdue</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Priority</label>
                                <select class="form-select">
                                    <option>All Priorities</option>
                                    <option>High</option>
                                    <option>Medium</option>
                                    <option>Low</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Period</label>
                                <select class="form-select">
                                    <option>All Time</option>
                                    <option>This Quarter</option>
                                    <option>This Year</option>
                                    <option>Last 6 Months</option>
                                </select>
                            </div>
                        </div>

                        <!-- Goals List -->
                        <div class="row g-4">
                            <?php foreach ($goals as $goal): ?>
                            <div class="col-lg-6">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div class="flex-grow-1">
                                                <h6 class="card-title mb-1"><?= htmlspecialchars($goal['title']) ?></h6>
                                                <div class="text-muted small">
                                                    <i class="bi bi-person me-1"></i><?= htmlspecialchars($goal['employee']) ?> 
                                                    <span class="mx-2">•</span>
                                                    <i class="bi bi-building me-1"></i><?= htmlspecialchars($goal['department']) ?>
                                                </div>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <?php
                                                $priority_class = $goal['priority'] == 'high' ? 'badge bg-danger' : ($goal['priority'] == 'medium' ? 'badge bg-warning' : 'badge bg-info');
                                                $status_class = $goal['status'] == 'completed' ? 'badge bg-success' : 'badge bg-primary';
                                                ?>
                                                <span class="<?= $priority_class ?> text-uppercase small">
                                                    <?= ucfirst($goal['priority']) ?>
                                                </span>
                                                <span class="<?= $status_class ?> text-uppercase small">
                                                    <?= ucwords(str_replace('-', ' ', $goal['status'])) ?>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="small">Progress</span>
                                                <span class="small fw-bold"><?= $goal['progress'] ?>%</span>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar <?= $goal['progress'] >= 100 ? 'bg-success' : ($goal['progress'] >= 50 ? 'bg-primary' : 'bg-warning') ?>" 
                                                     style="width: <?= $goal['progress'] ?>%"></div>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="text-muted small">
                                                <i class="bi bi-calendar me-1"></i>
                                                <?= date('M j', strtotime($goal['start_date'])) ?> - <?= date('M j, Y', strtotime($goal['end_date'])) ?>
                                            </div>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="editGoal(<?= $goal['id'] ?>)" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-success" onclick="viewGoalDetails(<?= $goal['id'] ?>)" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-info" onclick="updateProgress(<?= $goal['id'] ?>)" title="Update Progress">
                                                    <i class="bi bi-arrow-up-circle"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- My Goals Tab -->
                    <div class="tab-pane fade" id="my-goals" role="tabpanel">
                        <div class="text-center py-5">
                            <i class="bi bi-person-check display-1 text-secondary"></i>
                            <h5 class="mt-3">Your Personal Goals</h5>
                            <p class="text-muted">This section will show goals assigned specifically to you.</p>
                            <button class="btn btn-primary" onclick="showNewGoalModal()">
                                <i class="bi bi-plus-lg me-2"></i>Create Personal Goal
                            </button>
                        </div>
                    </div>

                    <!-- Team Goals Tab -->
                    <div class="tab-pane fade" id="team-goals" role="tabpanel">
                        <div class="text-center py-5">
                            <i class="bi bi-people-fill display-1 text-secondary"></i>
                            <h5 class="mt-3">Team Goals</h5>
                            <p class="text-muted">Collaborative goals and team objectives will be displayed here.</p>
                            <button class="btn btn-primary" onclick="showTeamGoalModal()">
                                <i class="bi bi-plus-lg me-2"></i>Create Team Goal
                            </button>
                        </div>
                    </div>

                    <!-- Goal Templates Tab -->
                    <div class="tab-pane fade" id="templates" role="tabpanel">
                        <div class="row g-4">
                            <div class="col-md-4">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-body text-center">
                                        <i class="bi bi-graph-up display-4 text-primary mb-3"></i>
                                        <h6 class="card-title">Sales Performance</h6>
                                        <p class="text-muted">Increase sales revenue by X% within specified timeframe</p>
                                        <button class="btn btn-outline-primary" onclick="useTemplate('sales')">
                                            <i class="bi bi-plus me-1"></i>Use Template
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-body text-center">
                                        <i class="bi bi-kanban display-4 text-success mb-3"></i>
                                        <h6 class="card-title">Project Completion</h6>
                                        <p class="text-muted">Complete project milestones within deadline</p>
                                        <button class="btn btn-outline-primary" onclick="useTemplate('project')">
                                            <i class="bi bi-plus me-1"></i>Use Template
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-body text-center">
                                        <i class="bi bi-mortarboard display-4 text-warning mb-3"></i>
                                        <h6 class="card-title">Skill Development</h6>
                                        <p class="text-muted">Learn new skills or improve existing competencies</p>
                                        <button class="btn btn-outline-primary" onclick="useTemplate('skill')">
                                            <i class="bi bi-plus me-1"></i>Use Template
                                        </button>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function editGoal(id) {
        showAlert('Edit goal functionality will be implemented soon!', 'info');
    }

    function viewGoalDetails(id) {
        showAlert('Viewing goal details...', 'info');
    }

    function updateProgress(id) {
        showAlert('Progress update modal will be implemented soon!', 'info');
    }

    function showNewGoalModal() {
        showAlert('New goal creation modal will be implemented soon!', 'info');
    }

    function showTeamGoalModal() {
        showAlert('Team goal creation modal will be implemented soon!', 'info');
    }

    function useTemplate(type) {
        showAlert(`Using ${type} goal template...`, 'info');
    }

    function exportGoalData() {
        showAlert('Exporting goal management data...', 'info');
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

<?php if (!isset($root_path)) 
include '../layouts/footer.php'; ?>
