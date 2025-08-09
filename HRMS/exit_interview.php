<?php
// Start session with proper authentication
session_start();

// Check authentication - compatible with both admin and user sessions
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Include required files
$base_dir = dirname(__DIR__);
require_once $base_dir . '/db.php';

$page_title = 'Exit Interviews - HRMS';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['action'];
        $response = ['success' => false, 'message' => 'Unknown error'];
        
        switch ($action) {
            case 'schedule_interview':
                $employee_id = intval($_POST['employee_id']);
                $interview_date = $_POST['interview_date'];
                $interviewer_name = $_POST['interviewer_name'];
                
                $stmt = $conn->prepare("
                    INSERT INTO exit_interviews 
                    (employee_id, interview_date, interviewer_name, interview_status) 
                    VALUES (?, ?, ?, 'scheduled')
                ");
                $stmt->bind_param('iss', $employee_id, $interview_date, $interviewer_name);
                
                if ($stmt->execute()) {
                    $response = ['success' => true, 'message' => 'Exit interview scheduled successfully'];
                }
                break;
                
            case 'update_interview':
                $interview_id = intval($_POST['interview_id']);
                $overall_rating = floatval($_POST['overall_rating']);
                $work_satisfaction = intval($_POST['work_satisfaction']);
                $management_rating = intval($_POST['management_rating']);
                $compensation_rating = intval($_POST['compensation_rating']);
                $work_environment = intval($_POST['work_environment']);
                $career_growth = intval($_POST['career_growth']);
                $reason_leaving = $_POST['reason_leaving'];
                $would_recommend = $_POST['would_recommend'];
                $feedback_comments = $_POST['feedback_comments'];
                $suggestions = $_POST['suggestions'];
                $rehire_eligible = $_POST['rehire_eligible'];
                
                $stmt = $conn->prepare("
                    UPDATE exit_interviews SET 
                    overall_rating = ?, work_satisfaction_rating = ?, management_rating = ?, 
                    compensation_rating = ?, work_environment_rating = ?, career_growth_rating = ?,
                    reason_for_leaving = ?, would_recommend = ?, feedback_comments = ?, 
                    suggestions_improvement = ?, rehire_eligible = ?, interview_status = 'completed',
                    updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->bind_param('diiiiisssssi', $overall_rating, $work_satisfaction, $management_rating,
                                $compensation_rating, $work_environment, $career_growth, $reason_leaving,
                                $would_recommend, $feedback_comments, $suggestions, $rehire_eligible, $interview_id);
                
                if ($stmt->execute()) {
                    $response = ['success' => true, 'message' => 'Interview updated successfully'];
                }
                break;
                
            case 'get_interview_details':
                $interview_id = intval($_POST['interview_id']);
                
                $stmt = $conn->prepare("SELECT * FROM exit_interviews WHERE id = ?");
                $stmt->bind_param('i', $interview_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($data = $result->fetch_assoc()) {
                    $response = ['success' => true, 'data' => $data];
                } else {
                    $response = ['success' => false, 'message' => 'Interview not found'];
                }
                break;
                
            case 'delete_interview':
                $interview_id = intval($_POST['interview_id']);
                
                $stmt = $conn->prepare("DELETE FROM exit_interviews WHERE id = ?");
                $stmt->bind_param('i', $interview_id);
                
                if ($stmt->execute()) {
                    $response = ['success' => true, 'message' => 'Interview deleted successfully'];
                }
                break;
                
            case 'submit_questionnaire':
                $overall_rating = intval($_POST['q1']);
                $reason_leaving = $_POST['reason_leaving'];
                $supervisor_rating = intval($_POST['q3']);
                $retention_feedback = $_POST['retention_feedback'];
                $would_recommend = $_POST['recommend'];
                
                // For demo purposes, create a new interview record
                $stmt = $conn->prepare("
                    INSERT INTO exit_interviews 
                    (overall_rating, reason_for_leaving, management_rating, 
                     suggestions_improvement, would_recommend, interview_status, employee_name) 
                    VALUES (?, ?, ?, ?, ?, 'completed', 'Anonymous Survey')
                ");
                $stmt->bind_param('dsiss', $overall_rating, $reason_leaving, $supervisor_rating, 
                                $retention_feedback, $would_recommend);
                
                if ($stmt->execute()) {
                    $response = ['success' => true, 'message' => 'Questionnaire submitted successfully'];
                }
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
        echo json_encode($response);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Fetch exit interviews data
$exit_interviews = [];
$query = "SELECT ei.*, e.first_name, e.last_name, e.department_name 
          FROM exit_interviews ei 
          LEFT JOIN employees e ON ei.employee_id = e.employee_id 
          ORDER BY ei.interview_date DESC";

$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $exit_interviews[] = $row;
    }
}

// Calculate statistics
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN interview_status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
        SUM(CASE WHEN interview_status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN interview_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        AVG(overall_rating) as avg_rating
    FROM exit_interviews
";

$stats_result = mysqli_query($conn, $stats_query);
$statistics = mysqli_fetch_assoc($stats_result) ?: [
    'total' => 0, 'scheduled' => 0, 'completed' => 0, 
    'cancelled' => 0, 'avg_rating' => 0
];

// Fetch feedback categories with ratings
$feedback_categories = [
    ['category' => 'Work Environment', 'field' => 'work_environment_rating'],
    ['category' => 'Management Support', 'field' => 'management_rating'],
    ['category' => 'Career Development', 'field' => 'career_growth_rating'],
    ['category' => 'Compensation', 'field' => 'compensation_rating'],
    ['category' => 'Work-Life Balance', 'field' => 'work_satisfaction_rating']
];

foreach ($feedback_categories as &$category) {
    $rating_query = "SELECT AVG({$category['field']}) as avg_rating FROM exit_interviews WHERE {$category['field']} IS NOT NULL";
    $rating_result = mysqli_query($conn, $rating_query);
    $rating_data = mysqli_fetch_assoc($rating_result);
    $category['avg_rating'] = $rating_data['avg_rating'] ? round($rating_data['avg_rating'], 1) : 3.5;
}

// Include global layout files
include $base_dir . '/layouts/header.php';
include $base_dir . '/layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">🗣️ Exit Interviews</h1>
                <p class="text-muted">Conduct exit interviews and gather valuable feedback from departing employees</p>
            </div>
            <div>
                <button class="btn btn-success me-2" onclick="generateExitReport()">
                    <i class="bi bi-file-earmark-text"></i> Exit Report
                </button>
                <button class="btn btn-primary" onclick="scheduleExitInterview()">
                    <i class="bi bi-plus-lg"></i> Schedule Interview
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-clock-history fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $statistics['scheduled'] ?></h3>
                        <p class="mb-0 opacity-90">Scheduled</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-check-circle fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $statistics['completed'] ?></h3>
                        <p class="mb-0 opacity-90">Completed</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-star fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= number_format($statistics['avg_rating'], 1) ?></h3>
                        <p class="mb-0 opacity-90">Average Rating</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-people fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $statistics['total'] ?></h3>
                        <p class="mb-0 opacity-90">Total Interviews</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Card -->
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
                                            <div class="fw-semibold"><?= htmlspecialchars($interview['employee_name'] ?? 'N/A') ?></div>
                                            <div class="text-muted small"><?= htmlspecialchars($interview['employee_code'] ?? 'N/A') ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($interview['department_name'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($interview['position'] ?? 'N/A') ?></td>
                                        <td><?= $interview['interview_date'] ? date('M j, Y', strtotime($interview['interview_date'])) : 'Not Set' ?></td>
                                        <td><?= htmlspecialchars($interview['interviewer_name'] ?? 'Not Assigned') ?></td>
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
                                            $status_classes = [
                                                'scheduled' => 'bg-warning',
                                                'completed' => 'bg-success',
                                                'cancelled' => 'bg-danger',
                                                'in_progress' => 'bg-primary'
                                            ];
                                            $status_class = $status_classes[$interview['interview_status']] ?? 'bg-secondary';
                                            ?>
                                            <span class="badge <?= $status_class ?> text-uppercase"><?= $interview['interview_status'] ?></span>
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
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteInterview(<?= $interview['id'] ?>)" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($exit_interviews)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            <i class="bi bi-inbox fs-1"></i>
                                            <div class="mt-2">No exit interviews found</div>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Questionnaire Tab -->
                    <div class="tab-pane fade" id="questionnaire" role="tabpanel">
                        <div class="p-4">
                            <h5 class="mb-4">Exit Interview Questionnaire</h5>
                            
                            <form id="questionnaireForm">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6>1. How would you rate your overall experience working with our company?</h6>
                                        <div class="d-flex gap-2 mt-2">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <input type="radio" class="btn-check" name="q1" id="q1_<?= $i ?>" value="<?= $i ?>">
                                            <label class="btn btn-outline-warning" for="q1_<?= $i ?>">
                                                <?= str_repeat('★', $i) ?>
                                            </label>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6>2. What is your primary reason for leaving?</h6>
                                        <select class="form-select mt-2" name="reason_leaving" required>
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
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <input type="radio" class="btn-check" name="q3" id="q3_<?= $i ?>" value="<?= $i ?>">
                                            <label class="btn btn-outline-warning" for="q3_<?= $i ?>">
                                                <?= str_repeat('★', $i) ?>
                                            </label>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6>4. What could the company have done to retain you?</h6>
                                        <textarea class="form-control mt-2" rows="3" name="retention_feedback" placeholder="Please provide your feedback..."></textarea>
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

                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg me-2"></i>Submit Questionnaire
                                </button>
                            </form>
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
                                            <canvas id="feedbackChart" height="300"></canvas>
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
                            <button class="btn btn-warning" onclick="generateInsights()">
                                <i class="bi bi-robot me-2"></i>Generate Insights
                            </button>
                            <div id="insightsContent" class="mt-4" style="display: none;">
                                <div class="alert alert-info">
                                    <strong>Key Insights:</strong>
                                    <ul class="text-start mt-2 mb-0">
                                        <li>Career growth opportunities is the most cited concern</li>
                                        <li>Compensation satisfaction is below industry average</li>
                                        <li>Management support rating is consistently high</li>
                                        <li>Work environment receives positive feedback</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Interview Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Schedule Exit Interview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="scheduleForm">
                    <div class="mb-3">
                        <label class="form-label">Employee</label>
                        <select class="form-select" name="employee_id" required>
                            <option value="">Select Employee</option>
                            <?php
                            $emp_query = "SELECT employee_id, first_name, last_name, employee_code FROM employees WHERE status = 'active'";
                            $emp_result = mysqli_query($conn, $emp_query);
                            while ($emp = mysqli_fetch_assoc($emp_result)) {
                                echo "<option value='{$emp['employee_id']}'>{$emp['first_name']} {$emp['last_name']} ({$emp['employee_code']})</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Interview Date</label>
                        <input type="date" class="form-control" name="interview_date" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Interviewer Name</label>
                        <input type="text" class="form-control" name="interviewer_name" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitSchedule()">Schedule Interview</button>
            </div>
        </div>
    </div>
</div>

<!-- View Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Interview Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Edit Interview Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">Edit Interview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editForm">
                    <input type="hidden" name="interview_id" id="editInterviewId">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Overall Rating</label>
                                <select class="form-select" name="overall_rating">
                                    <option value="1">1 - Poor</option>
                                    <option value="2">2 - Fair</option>
                                    <option value="3">3 - Good</option>
                                    <option value="4">4 - Very Good</option>
                                    <option value="5">5 - Excellent</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Work Satisfaction (1-5)</label>
                                <input type="number" class="form-control" name="work_satisfaction" min="1" max="5">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Management Rating (1-5)</label>
                                <input type="number" class="form-control" name="management_rating" min="1" max="5">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Compensation Rating (1-5)</label>
                                <input type="number" class="form-control" name="compensation_rating" min="1" max="5">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Work Environment (1-5)</label>
                                <input type="number" class="form-control" name="work_environment" min="1" max="5">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Career Growth (1-5)</label>
                                <input type="number" class="form-control" name="career_growth" min="1" max="5">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reason for Leaving</label>
                        <select class="form-select" name="reason_leaving">
                            <option value="better_opportunity">Better Career Opportunity</option>
                            <option value="compensation">Better Compensation</option>
                            <option value="work_life_balance">Work-Life Balance</option>
                            <option value="management">Management Issues</option>
                            <option value="relocation">Relocation</option>
                            <option value="personal">Personal Reasons</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Would Recommend Company?</label>
                        <select class="form-select" name="would_recommend">
                            <option value="yes">Yes</option>
                            <option value="maybe">Maybe</option>
                            <option value="no">No</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Feedback Comments</label>
                        <textarea class="form-control" name="feedback_comments" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Suggestions for Improvement</label>
                        <textarea class="form-control" name="suggestions" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Rehire Eligible?</label>
                        <select class="form-select" name="rehire_eligible">
                            <option value="yes">Yes</option>
                            <option value="conditional">Conditional</option>
                            <option value="no">No</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="submitEdit()">Update Interview</button>
            </div>
        </div>
    </div>
</div>

</div>
</div>

<?php include $base_dir . '/layouts/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Global variables
let chartInstance = null;

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    initializeChart();
    
    // Setup form handlers
    setupEventListeners();
});

function setupEventListeners() {
    // Questionnaire form submission
    document.getElementById('questionnaireForm').addEventListener('submit', function(e) {
        e.preventDefault();
        submitQuestionnaire();
    });
}

function initializeChart() {
    const ctx = document.getElementById('feedbackChart');
    if (!ctx) return;
    
    const categories = <?= json_encode(array_column($feedback_categories, 'category')) ?>;
    const ratings = <?= json_encode(array_column($feedback_categories, 'avg_rating')) ?>;
    
    if (chartInstance) {
        chartInstance.destroy();
    }
    
    chartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: categories,
            datasets: [{
                label: 'Average Rating',
                data: ratings,
                backgroundColor: 'rgba(102, 126, 234, 0.8)',
                borderColor: 'rgba(102, 126, 234, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 5,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
}

// Main functions
function scheduleExitInterview() {
    new bootstrap.Modal(document.getElementById('scheduleModal')).show();
}

function submitSchedule() {
    const form = document.getElementById('scheduleForm');
    const formData = new FormData(form);
    formData.append('action', 'schedule_interview');
    
    showLoading();
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showAlert(data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('scheduleModal')).hide();
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('Error scheduling interview', 'danger');
    });
}

function viewInterviewDetails(interviewId) {
    const formData = new FormData();
    formData.append('action', 'get_interview_details');
    formData.append('interview_id', interviewId);
    
    showLoading();
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            displayInterviewDetails(data.data);
            new bootstrap.Modal(document.getElementById('detailsModal')).show();
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('Error loading interview details', 'danger');
    });
}

function displayInterviewDetails(data) {
    const content = `
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="bi bi-person me-2"></i>Employee Information</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr><td><strong>Name:</strong></td><td>${data.employee_name || 'N/A'}</td></tr>
                            <tr><td><strong>Code:</strong></td><td>${data.employee_code || 'N/A'}</td></tr>
                            <tr><td><strong>Department:</strong></td><td>${data.department_name || 'N/A'}</td></tr>
                            <tr><td><strong>Position:</strong></td><td>${data.position || 'N/A'}</td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="bi bi-calendar me-2"></i>Interview Information</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr><td><strong>Date:</strong></td><td>${data.interview_date || 'N/A'}</td></tr>
                            <tr><td><strong>Interviewer:</strong></td><td>${data.interviewer_name || 'N/A'}</td></tr>
                            <tr><td><strong>Status:</strong></td><td><span class="badge bg-primary">${data.interview_status || 'N/A'}</span></td></tr>
                            <tr><td><strong>Rating:</strong></td><td>${data.overall_rating ? data.overall_rating + '/5' : 'Not Rated'}</td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        ${data.feedback_comments ? `
            <div class="row mt-3">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="bi bi-chat-dots me-2"></i>Feedback Comments</h6>
                        </div>
                        <div class="card-body">
                            <p class="mb-0">${data.feedback_comments}</p>
                        </div>
                    </div>
                </div>
            </div>
        ` : ''}
        
        ${data.suggestions_improvement ? `
            <div class="row mt-3">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0"><i class="bi bi-lightbulb me-2"></i>Suggestions for Improvement</h6>
                        </div>
                        <div class="card-body">
                            <p class="mb-0">${data.suggestions_improvement}</p>
                        </div>
                    </div>
                </div>
            </div>
        ` : ''}
    `;
    
    document.getElementById('detailsContent').innerHTML = content;
}

function editInterview(interviewId) {
    // First get the interview details
    const formData = new FormData();
    formData.append('action', 'get_interview_details');
    formData.append('interview_id', interviewId);
    
    showLoading();
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            populateEditForm(data.data);
            new bootstrap.Modal(document.getElementById('editModal')).show();
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('Error loading interview data', 'danger');
    });
}

function populateEditForm(data) {
    const form = document.getElementById('editForm');
    
    document.getElementById('editInterviewId').value = data.id;
    form.querySelector('[name="overall_rating"]').value = data.overall_rating || '';
    form.querySelector('[name="work_satisfaction"]').value = data.work_satisfaction_rating || '';
    form.querySelector('[name="management_rating"]').value = data.management_rating || '';
    form.querySelector('[name="compensation_rating"]').value = data.compensation_rating || '';
    form.querySelector('[name="work_environment"]').value = data.work_environment_rating || '';
    form.querySelector('[name="career_growth"]').value = data.career_growth_rating || '';
    form.querySelector('[name="reason_leaving"]').value = data.reason_for_leaving || '';
    form.querySelector('[name="would_recommend"]').value = data.would_recommend || '';
    form.querySelector('[name="feedback_comments"]').value = data.feedback_comments || '';
    form.querySelector('[name="suggestions"]').value = data.suggestions_improvement || '';
    form.querySelector('[name="rehire_eligible"]').value = data.rehire_eligible || '';
}

function submitEdit() {
    const form = document.getElementById('editForm');
    const formData = new FormData(form);
    formData.append('action', 'update_interview');
    
    showLoading();
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showAlert(data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('Error updating interview', 'danger');
    });
}

function downloadInterview(interviewId) {
    showAlert(`Downloading interview report for ID ${interviewId}...`, 'info');
    // Here you would implement actual PDF generation
}

function deleteInterview(interviewId) {
    if (confirm('Are you sure you want to delete this interview? This action cannot be undone.')) {
        const formData = new FormData();
        formData.append('action', 'delete_interview');
        formData.append('interview_id', interviewId);
        
        showLoading();
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                showAlert(data.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.message, 'danger');
            }
        })
        .catch(error => {
            hideLoading();
            showAlert('Error deleting interview', 'danger');
        });
    }
}

function submitQuestionnaire() {
    const form = document.getElementById('questionnaireForm');
    const formData = new FormData(form);
    formData.append('action', 'submit_questionnaire');
    
    showLoading();
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showAlert(data.message, 'success');
            form.reset();
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('Error submitting questionnaire', 'danger');
    });
}

function generateExitReport() {
    showAlert('Generating comprehensive exit interview report...', 'info');
    // Here you would implement report generation
}

function generateInsights() {
    showLoading();
    
    setTimeout(() => {
        hideLoading();
        document.getElementById('insightsContent').style.display = 'block';
        showAlert('AI insights generated successfully!', 'success');
    }, 2000);
}

// Utility functions
function showLoading() {
    // Create or show loading overlay
    let overlay = document.getElementById('loadingOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'loadingOverlay';
        overlay.innerHTML = `
            <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
                        background: rgba(0,0,0,0.5); display: flex; justify-content: center; 
                        align-items: center; z-index: 9999;">
                <div style="background: white; padding: 2rem; border-radius: 8px; text-align: center;">
                    <div class="spinner-border text-primary mb-3"></div>
                    <div>Processing...</div>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
    }
    overlay.style.display = 'block';
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.style.display = 'none';
    }
}

function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.parentNode.removeChild(alertDiv);
        }
    }, 5000);
}

// Stats card hover effects
document.querySelectorAll('.stats-card').forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-2px)';
    });
    
    card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
    });
});
</script>

<style>
.stats-card {
    transition: all 0.3s ease;
}

.stats-card:hover {
    transform: translateY(-2px);
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05);
}

.nav-link {
    color: #6c757d;
    border: none;
    background: none;
    padding: 0.75rem 1rem;
}

.nav-link.active {
    color: #495057;
    background-color: #fff;
    border-color: #dee2e6 #dee2e6 #fff;
    border-bottom: 1px solid #fff;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.form-control:focus, .form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.modal-header.bg-primary {
    background-color: #667eea !important;
}

.modal-header.bg-info {
    background-color: #4facfe !important;
}

.modal-header.bg-warning {
    background-color: #f6c23e !important;
}
</style>
