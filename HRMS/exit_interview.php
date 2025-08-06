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

$page_title = 'Exit Interviews - HRMS';

// Database-driven exit interview data
$exit_interviews = [];
$query = "SELECT * FROM exit_interviews ORDER BY interview_date DESC";
$result = mysqli_query($conn, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $exit_interviews[] = [
            'id' => $row['id'],
            'employee_id' => $row['employee_code'],
            'name' => $row['employee_name'],
            'department' => $row['department_name'],
            'position' => $row['position'],
            'last_working_day' => $row['last_working_day'],
            'interview_date' => $row['interview_date'],
            'interviewer' => $row['interviewer_name'],
            'status' => $row['interview_status'],
            'overall_rating' => $row['overall_rating'],
            'reason_for_leaving' => $row['reason_for_leaving']
        ];
    }
} else {
    echo "Error fetching exit interviews: " . mysqli_error($conn);
}

// Database-driven feedback categories with ratings
$feedback_categories = [];
$rating_queries = [
    ['category' => 'Work Environment', 'field' => 'work_environment_rating'],
    ['category' => 'Management Support', 'field' => 'management_rating'],
    ['category' => 'Career Development', 'field' => 'career_growth_rating'],
    ['category' => 'Compensation', 'field' => 'compensation_rating'],
    ['category' => 'Work-Life Balance', 'field' => 'work_satisfaction_rating'],
    ['category' => 'Team Collaboration', 'field' => 'overall_rating']
];

foreach ($rating_queries as $rating_query) {
    $query = "SELECT AVG({$rating_query['field']}) as avg_rating FROM exit_interviews WHERE {$rating_query['field']} IS NOT NULL";
    $result = mysqli_query($conn, $query);
    
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $avg_rating = $row['avg_rating'] ? round($row['avg_rating'], 1) : 3.5;
        $feedback_categories[] = [
            'category' => $rating_query['category'],
            'avg_rating' => $avg_rating
        ];
    } else {
        // Fallback rating if no data
        $feedback_categories[] = [
            'category' => $rating_query['category'],
            'avg_rating' => 3.5
        ];
    }
}

$current_page = 'exit_interview';

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
                    <i class="bi bi-chat-dots-fill text-primary me-3"></i>Exit Interviews
                </h1>
                <p class="text-muted" style="font-size: 1.1rem;">Conduct exit interviews and gather valuable feedback from departing employees</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" onclick="generateExitReport()">
                    <i class="bi bi-graph-up me-2"></i>Exit Report
                </button>
                <button class="btn btn-primary" onclick="scheduleExitInterview()">
                    <i class="bi bi-plus-lg me-2"></i>Schedule Interview
                </button>
            </div>
        </div>

        <!-- Exit Interview Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-clock-history display-4" style="color: #1976d2;"></i>
                        </div>
                        <h3 class="fw-bold mb-1" style="color: #1976d2;">8</h3>
                        <p class="text-muted mb-0">Scheduled Interviews</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-check-circle display-4" style="color: #388e3c;"></i>
                        </div>
                        <h3 class="fw-bold mb-1" style="color: #388e3c;">15</h3>
                        <p class="text-muted mb-0">Completed This Month</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fff3e0 0%, #ffcc02 100%);">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-star display-4" style="color: #f57c00;"></i>
                        </div>
                        <h3 class="fw-bold mb-1" style="color: #f57c00;">4.2</h3>
                        <p class="text-muted mb-0">Average Rating</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fce4ec 0%, #f8bbd9 100%);">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-pie-chart display-4" style="color: #c2185b;"></i>
                        </div>
                        <h3 class="fw-bold mb-1" style="color: #c2185b;">87%</h3>
                        <p class="text-muted mb-0">Completion Rate</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Tabs -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#interviews" role="tab">
                            <i class="bi bi-chat-dots me-2"></i>Exit Interviews
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#questionnaire" role="tab">
                            <i class="bi bi-clipboard-check me-2"></i>Questionnaire
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#analytics" role="tab">
                            <i class="bi bi-bar-chart me-2"></i>Analytics
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#insights" role="tab">
                            <i class="bi bi-lightbulb me-2"></i>Insights
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body">
                <div class="tab-content">
                    <!-- Exit Interviews Tab -->
                    <div class="tab-pane fade show active" id="interviews" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Position</th>
                                        <th>Interview Date</th>
                                        <th>Interviewer</th>
                                        <th>Rating</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($exit_interviews as $interview): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($interview['name']) ?></div>
                                            <div class="text-muted small"><?= $interview['employee_id'] ?></div>
                                        </td>
                                        <td><?= $interview['department'] ?></td>
                                        <td><?= $interview['position'] ?></td>
                                        <td><?= date('M j, Y', strtotime($interview['interview_date'])) ?></td>
                                        <td><?= $interview['interviewer'] ?></td>
                                        <td>
                                            <?php if ($interview['overall_rating']): ?>
                                                <div class="text-warning">
                                                    <?php
                                                    $rating = $interview['overall_rating'];
                                                    $fullStars = floor($rating);
                                                    $hasHalfStar = ($rating - $fullStars) >= 0.5;
                                                    
                                                    for ($i = 1; $i <= 5; $i++) {
                                                        if ($i <= $fullStars) {
                                                            echo '<i class="bi bi-star-fill"></i>';
                                                        } elseif ($i == $fullStars + 1 && $hasHalfStar) {
                                                            echo '<i class="bi bi-star-half"></i>';
                                                        } else {
                                                            echo '<i class="bi bi-star text-muted"></i>';
                                                        }
                                                    }
                                                    ?>
                                                </div>
                                                <small class="text-muted"><?= $rating ?>/5</small>
                                            <?php else: ?>
                                                <span class="text-muted">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status_class = $interview['status'] == 'completed' ? 'bg-success' : 'bg-warning';
                                            ?>
                                            <span class="badge <?= $status_class ?> text-uppercase"><?= $interview['status'] ?></span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="viewInterviewDetails(<?= $interview['id'] ?>)" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-warning" onclick="editInterview(<?= $interview['id'] ?>)" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-success" onclick="downloadInterview(<?= $interview['id'] ?>)" title="Download">
                                                    <i class="bi bi-download"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Questionnaire Tab -->
                    <div class="tab-pane fade" id="questionnaire" role="tabpanel">
                        <div class="p-4">
                            <h5 class="mb-4">Exit Interview Questionnaire</h5>
                            
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6>1. How would you rate your overall experience working with our company?</h6>
                                    <div class="d-flex gap-2 mt-2">
                                        <input type="radio" class="btn-check" name="q1" id="q1_1" value="1">
                                        <label class="btn btn-outline-warning" for="q1_1">★</label>
                                        <input type="radio" class="btn-check" name="q1" id="q1_2" value="2">
                                        <label class="btn btn-outline-warning" for="q1_2">★★</label>
                                        <input type="radio" class="btn-check" name="q1" id="q1_3" value="3">
                                        <label class="btn btn-outline-warning" for="q1_3">★★★</label>
                                        <input type="radio" class="btn-check" name="q1" id="q1_4" value="4">
                                        <label class="btn btn-outline-warning" for="q1_4">★★★★</label>
                                        <input type="radio" class="btn-check" name="q1" id="q1_5" value="5">
                                        <label class="btn btn-outline-warning" for="q1_5">★★★★★</label>
                                    </div>
                                </div>
                            </div>

                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6>2. What is your primary reason for leaving?</h6>
                                    <select class="form-select mt-2">
                                        <option value="">Select reason</option>
                                        <option value="better_opportunity">Better Career Opportunity</option>
                                        <option value="compensation">Better Compensation</option>
                                        <option value="work_life_balance">Work-Life Balance</option>
                                        <option value="management">Management Issues</option>
                                        <option value="relocation">Relocation</option>
                                        <option value="personal">Personal Reasons</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>

                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6>3. How would you rate your immediate supervisor?</h6>
                                    <div class="d-flex gap-2 mt-2">
                                        <input type="radio" class="btn-check" name="q3" id="q3_1" value="1">
                                        <label class="btn btn-outline-warning" for="q3_1">★</label>
                                        <input type="radio" class="btn-check" name="q3" id="q3_2" value="2">
                                        <label class="btn btn-outline-warning" for="q3_2">★★</label>
                                        <input type="radio" class="btn-check" name="q3" id="q3_3" value="3">
                                        <label class="btn btn-outline-warning" for="q3_3">★★★</label>
                                        <input type="radio" class="btn-check" name="q3" id="q3_4" value="4">
                                        <label class="btn btn-outline-warning" for="q3_4">★★★★</label>
                                        <input type="radio" class="btn-check" name="q3" id="q3_5" value="5">
                                        <label class="btn btn-outline-warning" for="q3_5">★★★★★</label>
                                    </div>
                                </div>
                            </div>

                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6>4. What could the company have done to retain you?</h6>
                                    <textarea class="form-control mt-2" rows="3" placeholder="Please provide your feedback..."></textarea>
                                </div>
                            </div>

                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6>5. Would you recommend our company as a great place to work?</h6>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="radio" name="recommend" id="recommend_yes" value="yes">
                                        <label class="form-check-label" for="recommend_yes">Yes, definitely</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="recommend" id="recommend_maybe" value="maybe">
                                        <label class="form-check-label" for="recommend_maybe">Maybe</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="recommend" id="recommend_no" value="no">
                                        <label class="form-check-label" for="recommend_no">No</label>
                                    </div>
                                </div>
                            </div>

                            <button type="button" class="btn btn-primary" onclick="submitQuestionnaire()">
                                <i class="bi bi-check-lg me-2"></i>Submit Questionnaire
                            </button>
                        </div>
                    </div>

                <!-- Analytics Tab -->
                <div class="tab-pane fade" id="analytics" role="tabpanel">
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="chart-container">
                                <canvas id="feedbackChart"></canvas>
                            </div>
                        </div>
                    <!-- Analytics Tab -->
                    <div class="tab-pane fade" id="analytics" role="tabpanel">
                        <div class="p-4">
                            <div class="row">
                                <div class="col-lg-8">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0">Feedback Analysis</h6>
                                        </div>
                                        <div class="card-body">
                                            <div style="height: 300px; display: flex; align-items: center; justify-content: center;">
                                                <p class="text-muted">Chart visualization would be displayed here</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <h6 class="mb-3">Feedback Categories</h6>
                                    <?php foreach ($feedback_categories as $category): ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="small"><?= $category['category'] ?></span>
                                            <span class="small fw-semibold"><?= $category['avg_rating'] ?>/5</span>
                                        </div>
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-primary" style="width: <?= ($category['avg_rating'] / 5) * 100 ?>%"></div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Insights Tab -->
                    <div class="tab-pane fade" id="insights" role="tabpanel">
                        <div class="p-4 text-center py-5">
                            <i class="bi bi-lightbulb display-1 text-warning"></i>
                            <h5 class="mt-3">Exit Interview Insights</h5>
                            <p class="text-muted">AI-powered insights and recommendations based on exit interview data.</p>
                            <button class="btn btn-warning">
                                <i class="bi bi-robot me-2"></i>Generate Insights
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function viewInterviewDetails(interviewId) {
        showAlert(`Viewing interview details for ID ${interviewId}...`, 'info');
    }

    function editInterview(interviewId) {
        showAlert(`Editing interview ${interviewId}...`, 'warning');
    }

    function downloadInterview(interviewId) {
        showAlert(`Downloading interview report ${interviewId}...`, 'info');
    }

    function scheduleExitInterview() {
        showAlert('Exit interview scheduling form will be implemented!', 'info');
    }

    function generateExitReport() {
        showAlert('Generating exit interview report...', 'success');
    }

    function submitQuestionnaire() {
        showAlert('Exit interview questionnaire submitted successfully!', 'success');
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
include '../layouts/footer.php'; ?>;
                    }
                });
            }, 5000);
        }
    </script>
</body>
</html>
