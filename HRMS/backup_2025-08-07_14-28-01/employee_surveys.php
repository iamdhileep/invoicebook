<?php
$page_title = "Employee Surveys - HRMS";

// Include authentication and database
require_once '../auth_check.php';
require_once '../db.php';

// Include layouts
require_once 'hrms_header_simple.php';
require_once 'hrms_sidebar_simple.php';

// Include HRMS UI fix
$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'] ?? 'employee';

$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'] ?? 'employee';
?>

<!-- Page Content Starts Here -->
<div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="gradient-text mb-2" style="font-size: 2.5rem; font-weight: 700;">
                    <i class="bi bi-clipboard-data-fill text-primary me-3"></i>Employee Surveys
                </h1>
                <p class="text-muted" style="font-size: 1.1rem;">Create, manage, and analyze employee feedback surveys</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-info" onclick="viewReports()">
                    <i class="bi bi-graph-up me-2"></i>Survey Reports
                </button>
                <button class="btn btn-primary" onclick="createSurvey()">
                    <i class="bi bi-plus-lg me-2"></i>Create Survey
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-primary text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-0">12</h3>
                                <p class="mb-0">Total Surveys</p>
                            </div>
                            <i class="bi bi-clipboard-data fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-success text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-0">3</h3>
                                <p class="mb-0">Active Surveys</p>
                            </div>
                            <i class="bi bi-play-circle fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-info text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-0">185</h3>
                                <p class="mb-0">Total Responses</p>
                            </div>
                            <i class="bi bi-chat-dots fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-warning text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-0">77%</h3>
                                <p class="mb-0">Response Rate</p>
                            </div>
                            <i class="bi bi-graph-up fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Tabs -->
        <div class="card">
            <ul class="nav nav-tabs nav-tabs-custom" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#surveys_list" role="tab">
                        <i class="bi bi-list-check me-2"></i>Surveys
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#create_survey" role="tab">
                        <i class="bi bi-plus-circle me-2"></i>Create Survey
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#templates" role="tab">
                        <i class="bi bi-file-earmark-text me-2"></i>Templates
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#analytics" role="tab">
                        <i class="bi bi-graph-up me-2"></i>Analytics
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <!-- Surveys List Tab -->
                <div class="tab-pane fade show active p-4" id="surveys_list" role="tabpanel">
                    <?php foreach ($surveys as $survey): ?>
                    <div class="card border-0 mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="flex-grow-1">
                                    <h5 class="card-title text-primary mb-1"><?= htmlspecialchars($survey['title']) ?></h5>
                                    <p class="text-muted mb-3"><?= htmlspecialchars($survey['description']) ?></p>
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        <span class="badge bg-<?= $survey['status'] == 'active' ? 'success' : ($survey['status'] == 'completed' ? 'info' : 'warning') ?>">
                                            <?= ucfirst($survey['status']) ?>
                                        </span>
                                        <span class="badge bg-secondary">
                                            <?= ucfirst($survey['type']) ?>
                                        </span>
                                        <?php if ($survey['is_anonymous']): ?>
                                        <span class="badge bg-secondary">Anonymous</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex-shrink-0">
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewSurvey('<?= $survey['id'] ?>')" title="View Survey">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="editSurvey('<?= $survey['id'] ?>')" title="Edit Survey">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-info" onclick="viewResponses('<?= $survey['id'] ?>')" title="View Responses">
                                            <i class="bi bi-graph-up"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row text-center mb-3">
                                <div class="col-md-4">
                                    <small class="text-muted d-block">Start Date</small>
                                    <strong><?= date('M j, Y', strtotime($survey['start_date'])) ?></strong>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted d-block">End Date</small>
                                    <strong><?= date('M j, Y', strtotime($survey['end_date'])) ?></strong>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted d-block">Created By</small>
                                    <strong><?= $survey['created_by'] ?></strong>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small class="text-muted">Completion Rate</small>
                                    <small class="fw-bold"><?= $survey['responses'] ?>/<?= $survey['total_employees'] ?> responses (<?= number_format($survey['completion_rate'], 1) ?>%)</small>
                                </div>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= $survey['completion_rate'] ?>%" aria-valuenow="<?= $survey['completion_rate'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Create Survey Tab -->
                <div class="tab-pane fade p-4" id="create_survey" role="tabpanel">
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Create New Survey</h5>
                                </div>
                                <div class="card-body">
                                    <form id="createSurveyForm">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <div class="mb-3">
                                                    <label class="form-label">Survey Title</label>
                                                    <input type="text" class="form-control" name="title" placeholder="Enter survey title..." required>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label class="form-label">Survey Type</label>
                                                    <select class="form-select" name="type" required>
                                                        <option value="">Select Type</option>
                                                        <option value="engagement">Employee Engagement</option>
                                                        <option value="training">Training Feedback</option>
                                                        <option value="culture">Culture Assessment</option>
                                                        <option value="exit">Exit Interview</option>
                                                        <option value="performance">Performance Review</option>
                                                        <option value="benefits">Benefits Survey</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Description</label>
                                            <textarea class="form-control" name="description" rows="3" placeholder="Survey description and purpose..."></textarea>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                        <label class="form-label">Start Date</label>
                                        <input type="date" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">End Date</label>
                                        <input type="date" class="form-control">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="anonymous">
                                    <label class="form-check-label" for="anonymous">
                                        Anonymous Survey (responses will not be linked to employee identities)
                                    </label>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-outline-primary btn-modern" onclick="addQuestion()">
                                    <i class="bi bi-plus me-2"></i>Add Question
                                </button>
                                <button type="button" class="btn btn-primary btn-modern" onclick="saveSurvey()">
                                    <i class="bi bi-save me-2"></i>Save Survey
                                </button>
                            </div>
                        </form>
                        
                        <div id="questionsContainer" class="mt-4">
                            <div class="question-item">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0">Question 1</h6>
                                    <select class="form-select w-auto">
                                        <option value="text">Text Response</option>
                                        <option value="rating">Rating Scale</option>
                                        <option value="multiple">Multiple Choice</option>
                                        <option value="checkbox">Checkbox</option>
                                    </select>
                                </div>
                                <input type="text" class="form-control" placeholder="Enter your question...">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Templates Tab -->
                <div class="tab-pane fade" id="templates" role="tabpanel">
                    <div class="row">
                        <?php foreach ($survey_templates as $template): ?>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="template-card" onclick="useTemplate('<?= strtolower(str_replace(' ', '_', $template)) ?>')">
                                <i class="bi bi-file-earmark-text" style="font-size: 3rem; color: var(--primary-color); margin-bottom: 1rem;"></i>
                                <h6 class="mb-2"><?= $template ?></h6>
                                <small class="text-muted">Pre-built survey template</small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Analytics Tab -->
                <div class="tab-pane fade" id="analytics" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="content-card">
                                <div class="card-header">
                                    <h6>Survey Completion Trends</h6>
                                </div>
                                <div class="p-3">
                                    <canvas id="completionTrendChart" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="content-card">
                                <div class="card-header">
                                    <h6>Response Distribution</h6>
                                </div>
                                <div class="p-3">
                                    <canvas id="responseChart" height="200"></canvas>
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
        // Initialize charts when analytics tab is shown
        document.querySelector('[data-bs-target="#analytics"]').addEventListener('shown.bs.tab', function() {
            initializeCharts();
        });

        function initializeCharts() {
            // Completion Trend Chart
            const completionCtx = document.getElementById('completionTrendChart').getContext('2d');
            new Chart(completionCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug'],
                    datasets: [{
                        label: 'Completion Rate %',
                        data: [65, 72, 78, 85, 80, 88, 92, 75],
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true, max: 100 }
                    }
                }
            });

            // Response Distribution Chart
            const responseCtx = document.getElementById('responseChart').getContext('2d');
            new Chart(responseCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Completed', 'In Progress', 'Not Started'],
                    datasets: [{
                        data: [185, 25, 40],
                        backgroundColor: ['#059669', '#d97706', '#dc2626']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }

        function viewSurvey(surveyId) {
            showAlert(`Opening survey ${surveyId} for viewing...`, 'info');
        }

        function editSurvey(surveyId) {
            showAlert(`Opening survey ${surveyId} for editing...`, 'warning');
        }

        function viewResponses(surveyId) {
            showAlert(`Loading responses for survey ${surveyId}...`, 'info');
        }

        function createSurvey() {
            const createTab = new bootstrap.Tab(document.querySelector('[data-bs-target="#create_survey"]'));
            createTab.show();
        }

        function addQuestion() {
            const container = document.getElementById('questionsContainer');
            const questionCount = container.children.length + 1;
            
            const questionHtml = `
                <div class="question-item">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Question ${questionCount}</h6>
                        <div class="d-flex gap-2">
                            <select class="form-select w-auto">
                                <option value="text">Text Response</option>
                                <option value="rating">Rating Scale</option>
                                <option value="multiple">Multiple Choice</option>
                                <option value="checkbox">Checkbox</option>
                            </select>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeQuestion(this)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                    <input type="text" class="form-control" placeholder="Enter your question...">
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', questionHtml);
        }

        function removeQuestion(button) {
            button.closest('.question-item').remove();
            updateQuestionNumbers();
        }

        function updateQuestionNumbers() {
            const questions = document.querySelectorAll('.question-item h6');
            questions.forEach((question, index) => {
                question.textContent = `Question ${index + 1}`;
            });
        }
    </script>
    </div>
</div>

<?php if (!isset($root_path)) 

<?php require_once 'hrms_footer_simple.php'; ?>