<?php
/**
 * Advanced Performance Review System
 * Comprehensive performance evaluation, goal tracking, and 360-degree feedback
 */

$page_title = "Advanced Performance Review";
require_once 'includes/hrms_config.php';

// Authentication check
if (!HRMSHelper::isLoggedIn()) {
    header('Location: ../hrms_portal.php?redirect=HRMS/advanced_performance_review.php');
    exit;
}

require_once '../layouts/header.php';
require_once '../layouts/sidebar.php';

$currentUserId = HRMSHelper::getCurrentUserId();
$currentUserRole = HRMSHelper::getCurrentUserRole();

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'create_review':
            $employeeId = $_POST['employee_id'] ?? 0;
            $reviewPeriod = $_POST['review_period'] ?? '';
            $reviewType = $_POST['review_type'] ?? '';
            $dueDate = $_POST['due_date'] ?? '';
            
            try {
                $stmt = $conn->prepare("
                    INSERT INTO hr_performance_reviews 
                    (employee_id, reviewer_id, review_period, review_type, due_date, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, 'draft', NOW())
                ");
                $stmt->bind_param('iisss', $employeeId, $currentUserId, $reviewPeriod, $reviewType, $dueDate);
                
                if ($stmt->execute()) {
                    $reviewId = $conn->insert_id;
                    echo json_encode(['success' => true, 'message' => 'Performance review created successfully', 'review_id' => $reviewId]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to create performance review']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'submit_review':
            $reviewId = $_POST['review_id'] ?? 0;
            $ratings = $_POST['ratings'] ?? [];
            $comments = $_POST['comments'] ?? [];
            $goals = $_POST['goals'] ?? [];
            $overallRating = $_POST['overall_rating'] ?? 0;
            $summary = $_POST['summary'] ?? '';
            
            try {
                $conn->begin_transaction();
                
                // Update review
                $stmt = $conn->prepare("
                    UPDATE hr_performance_reviews 
                    SET overall_rating = ?, summary = ?, status = 'completed', completed_at = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param('dsi', $overallRating, $summary, $reviewId);
                $stmt->execute();
                
                // Insert ratings
                $stmt = $conn->prepare("
                    INSERT INTO hr_review_ratings (review_id, criteria, rating, comments) 
                    VALUES (?, ?, ?, ?)
                ");
                
                foreach ($ratings as $criteria => $rating) {
                    $comment = $comments[$criteria] ?? '';
                    $stmt->bind_param('isds', $reviewId, $criteria, $rating, $comment);
                    $stmt->execute();
                }
                
                // Insert goals
                $stmt = $conn->prepare("
                    INSERT INTO hr_employee_goals (employee_id, review_id, goal_title, goal_description, target_date, priority) 
                    VALUES ((SELECT employee_id FROM hr_performance_reviews WHERE id = ?), ?, ?, ?, ?, ?)
                ");
                
                foreach ($goals as $goal) {
                    $stmt->bind_param('iissss', $reviewId, $reviewId, $goal['title'], $goal['description'], $goal['target_date'], $goal['priority']);
                    $stmt->execute();
                }
                
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Performance review submitted successfully']);
                
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'get_review_data':
            $reviewId = $_POST['review_id'] ?? 0;
            
            try {
                // Get review details
                $stmt = $conn->prepare("
                    SELECT pr.*, e.first_name, e.last_name, e.employee_id as emp_id
                    FROM hr_performance_reviews pr
                    LEFT JOIN hr_employees e ON pr.employee_id = e.id
                    WHERE pr.id = ?
                ");
                $stmt->bind_param('i', $reviewId);
                $stmt->execute();
                $review = $stmt->get_result()->fetch_assoc();
                
                // Get ratings
                $stmt = $conn->prepare("SELECT * FROM hr_review_ratings WHERE review_id = ?");
                $stmt->bind_param('i', $reviewId);
                $stmt->execute();
                $ratings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                // Get goals
                $stmt = $conn->prepare("SELECT * FROM hr_employee_goals WHERE review_id = ?");
                $stmt->bind_param('i', $reviewId);
                $stmt->execute();
                $goals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'review' => $review,
                    'ratings' => $ratings,
                    'goals' => $goals
                ]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'update_goal_progress':
            $goalId = $_POST['goal_id'] ?? 0;
            $progress = $_POST['progress'] ?? 0;
            $notes = $_POST['notes'] ?? '';
            
            try {
                $stmt = $conn->prepare("
                    UPDATE hr_employee_goals 
                    SET progress = ?, progress_notes = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param('dsi', $progress, $notes, $goalId);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Goal progress updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update goal progress']);
                }
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
    }
}

// Get performance reviews
$reviews = [];
try {
    $whereClause = "";
    if ($currentUserRole !== 'hr' && $currentUserRole !== 'admin') {
        $whereClause = "WHERE e.user_id = $currentUserId OR pr.reviewer_id = $currentUserId";
    }
    
    $result = HRMSHelper::safeQuery("
        SELECT 
            pr.*,
            e.first_name, e.last_name, e.employee_id as emp_id,
            d.name as department_name,
            reviewer.first_name as reviewer_first_name,
            reviewer.last_name as reviewer_last_name
        FROM hr_performance_reviews pr
        LEFT JOIN hr_employees e ON pr.employee_id = e.id
        LEFT JOIN hr_departments d ON e.department_id = d.id
        LEFT JOIN hr_employees reviewer ON pr.reviewer_id = reviewer.id
        $whereClause
        ORDER BY pr.created_at DESC
        LIMIT 50
    ");
    
    while ($row = $result->fetch_assoc()) {
        $reviews[] = $row;
    }
} catch (Exception $e) {
    error_log("Performance reviews fetch error: " . $e->getMessage());
}

// Get employees for dropdown (HR only)
$employees = [];
if ($currentUserRole === 'hr' || $currentUserRole === 'admin') {
    try {
        $result = HRMSHelper::safeQuery("
            SELECT e.id, e.employee_id, e.first_name, e.last_name, d.name as department_name
            FROM hr_employees e
            LEFT JOIN hr_departments d ON e.department_id = d.id
            WHERE e.is_active = 1 
            ORDER BY e.first_name, e.last_name
        ");
        while ($row = $result->fetch_assoc()) {
            $employees[] = $row;
        }
    } catch (Exception $e) {
        error_log("Employees fetch error: " . $e->getMessage());
    }
}

// Get current user's goals
$myGoals = [];
try {
    $currentEmployeeResult = HRMSHelper::safeQuery("SELECT id FROM hr_employees WHERE user_id = $currentUserId");
    if ($currentEmployeeResult->num_rows > 0) {
        $currentEmployeeId = $currentEmployeeResult->fetch_assoc()['id'];
        
        $result = HRMSHelper::safeQuery("
            SELECT g.*, pr.review_period
            FROM hr_employee_goals g
            LEFT JOIN hr_performance_reviews pr ON g.review_id = pr.id
            WHERE g.employee_id = $currentEmployeeId
            ORDER BY g.target_date ASC
        ");
        
        while ($row = $result->fetch_assoc()) {
            $myGoals[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Goals fetch error: " . $e->getMessage());
}

// Calculate statistics
$stats = [
    'total_reviews' => count($reviews),
    'pending_reviews' => count(array_filter($reviews, function($r) { return $r['status'] === 'pending'; })),
    'completed_reviews' => count(array_filter($reviews, function($r) { return $r['status'] === 'completed'; })),
    'average_rating' => 0
];

$totalRating = 0;
$ratedReviews = 0;
foreach ($reviews as $review) {
    if ($review['overall_rating'] > 0) {
        $totalRating += $review['overall_rating'];
        $ratedReviews++;
    }
}

if ($ratedReviews > 0) {
    $stats['average_rating'] = $totalRating / $ratedReviews;
}

// Performance criteria
$performanceCriteria = [
    'job_knowledge' => 'Job Knowledge & Skills',
    'quality_of_work' => 'Quality of Work',
    'productivity' => 'Productivity & Efficiency',
    'communication' => 'Communication Skills',
    'teamwork' => 'Teamwork & Collaboration',
    'leadership' => 'Leadership & Initiative',
    'problem_solving' => 'Problem Solving',
    'dependability' => 'Dependability & Reliability',
    'adaptability' => 'Adaptability & Flexibility',
    'professional_development' => 'Professional Development'
];
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h2 mb-1">
                            <i class="fas fa-chart-line text-primary me-2"></i>
                            Advanced Performance Review
                        </h1>
                        <p class="text-muted mb-0">Comprehensive performance evaluation and goal tracking system</p>
                    </div>
                    <div class="d-flex gap-2">
                        <?php if ($currentUserRole === 'hr' || $currentUserRole === 'admin'): ?>
                            <button class="btn btn-outline-info" onclick="showPerformanceAnalytics()">
                                <i class="fas fa-analytics me-1"></i>Analytics
                            </button>
                            <button class="btn btn-primary" onclick="showCreateReviewModal()">
                                <i class="fas fa-plus me-1"></i>Create Review
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                <div class="card border-0 shadow-sm stats-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-primary bg-opacity-10 rounded-3 p-3 me-3">
                                <i class="fas fa-clipboard-list text-primary fs-2"></i>
                            </div>
                            <div>
                                <h3 class="fw-bold text-primary mb-0"><?= $stats['total_reviews'] ?></h3>
                                <p class="text-muted mb-0 small">Total Reviews</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                <div class="card border-0 shadow-sm stats-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-warning bg-opacity-10 rounded-3 p-3 me-3">
                                <i class="fas fa-hourglass-half text-warning fs-2"></i>
                            </div>
                            <div>
                                <h3 class="fw-bold text-warning mb-0"><?= $stats['pending_reviews'] ?></h3>
                                <p class="text-muted mb-0 small">Pending Reviews</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                <div class="card border-0 shadow-sm stats-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-success bg-opacity-10 rounded-3 p-3 me-3">
                                <i class="fas fa-check-circle text-success fs-2"></i>
                            </div>
                            <div>
                                <h3 class="fw-bold text-success mb-0"><?= $stats['completed_reviews'] ?></h3>
                                <p class="text-muted mb-0 small">Completed</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                <div class="card border-0 shadow-sm stats-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-info bg-opacity-10 rounded-3 p-3 me-3">
                                <i class="fas fa-star text-info fs-2"></i>
                            </div>
                            <div>
                                <h3 class="fw-bold text-info mb-0"><?= number_format($stats['average_rating'], 1) ?></h3>
                                <p class="text-muted mb-0 small">Average Rating</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Tabs -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <ul class="nav nav-tabs card-header-tabs" id="performanceTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="reviews-tab" data-bs-toggle="tab" 
                                        data-bs-target="#reviews" type="button" role="tab">
                                    <i class="fas fa-clipboard-list me-2"></i>Performance Reviews
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="goals-tab" data-bs-toggle="tab" 
                                        data-bs-target="#goals" type="button" role="tab">
                                    <i class="fas fa-bullseye me-2"></i>Goals & Objectives
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="feedback-tab" data-bs-toggle="tab" 
                                        data-bs-target="#feedback" type="button" role="tab">
                                    <i class="fas fa-comments me-2"></i>360° Feedback
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="performanceTabContent">
                            <!-- Performance Reviews Tab -->
                            <div class="tab-pane fade show active" id="reviews" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Employee</th>
                                                <th>Review Period</th>
                                                <th>Type</th>
                                                <th>Reviewer</th>
                                                <th>Due Date</th>
                                                <th>Overall Rating</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reviews as $review): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="employee-avatar-small me-2">
                                                                <?= strtoupper(substr($review['first_name'], 0, 1) . substr($review['last_name'], 0, 1)) ?>
                                                            </div>
                                                            <div>
                                                                <div class="fw-medium"><?= htmlspecialchars($review['first_name'] . ' ' . $review['last_name']) ?></div>
                                                                <small class="text-muted"><?= htmlspecialchars($review['emp_id']) ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info bg-opacity-10 text-info">
                                                            <?= htmlspecialchars($review['review_period']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= ucfirst(str_replace('_', ' ', $review['review_type'])) ?></td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?= htmlspecialchars($review['reviewer_first_name'] . ' ' . $review['reviewer_last_name']) ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <?php if ($review['due_date']): ?>
                                                            <div><?= date('M j, Y', strtotime($review['due_date'])) ?></div>
                                                            <?php
                                                            $daysLeft = (strtotime($review['due_date']) - time()) / (60 * 60 * 24);
                                                            if ($daysLeft < 0 && $review['status'] !== 'completed'): ?>
                                                                <small class="text-danger">Overdue</small>
                                                            <?php elseif ($daysLeft <= 3 && $review['status'] !== 'completed'): ?>
                                                                <small class="text-warning">Due soon</small>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">Not set</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($review['overall_rating'] > 0): ?>
                                                            <div class="rating-display">
                                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                    <i class="fas fa-star <?= $i <= $review['overall_rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                                                                <?php endfor; ?>
                                                                <span class="ms-2"><?= number_format($review['overall_rating'], 1) ?>/5</span>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="text-muted">Not rated</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $statusClasses = [
                                                            'draft' => 'secondary',
                                                            'pending' => 'warning',
                                                            'in_progress' => 'info',
                                                            'completed' => 'success',
                                                            'overdue' => 'danger'
                                                        ];
                                                        $statusClass = $statusClasses[$review['status']] ?? 'secondary';
                                                        ?>
                                                        <span class="badge bg-<?= $statusClass ?>">
                                                            <?= ucfirst(str_replace('_', ' ', $review['status'])) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <button class="btn btn-outline-primary btn-sm" 
                                                                    onclick="viewReview(<?= $review['id'] ?>)"
                                                                    title="View Review">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                            <?php if ($review['status'] !== 'completed'): ?>
                                                                <button class="btn btn-outline-success btn-sm" 
                                                                        onclick="editReview(<?= $review['id'] ?>)"
                                                                        title="Edit Review">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Goals & Objectives Tab -->
                            <div class="tab-pane fade" id="goals" role="tabpanel">
                                <div class="row">
                                    <?php foreach ($myGoals as $goal): ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="card goal-card h-100">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <h6 class="card-title fw-bold"><?= htmlspecialchars($goal['goal_title']) ?></h6>
                                                        <span class="badge bg-<?= $goal['priority'] === 'high' ? 'danger' : ($goal['priority'] === 'medium' ? 'warning' : 'success') ?>">
                                                            <?= ucfirst($goal['priority']) ?>
                                                        </span>
                                                    </div>
                                                    <p class="card-text text-muted small mb-3"><?= htmlspecialchars($goal['goal_description']) ?></p>
                                                    
                                                    <div class="progress mb-2" style="height: 8px;">
                                                        <div class="progress-bar" role="progressbar" 
                                                             style="width: <?= $goal['progress'] ?? 0 ?>%"
                                                             aria-valuenow="<?= $goal['progress'] ?? 0 ?>" 
                                                             aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <small class="text-muted"><?= ($goal['progress'] ?? 0) ?>% Complete</small>
                                                        <small class="text-muted">Due: <?= date('M j', strtotime($goal['target_date'])) ?></small>
                                                    </div>
                                                </div>
                                                <div class="card-footer bg-transparent border-0">
                                                    <button class="btn btn-outline-primary btn-sm w-100" 
                                                            onclick="updateGoalProgress(<?= $goal['id'] ?>)">
                                                        <i class="fas fa-tasks me-1"></i>Update Progress
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <?php if (empty($myGoals)): ?>
                                        <div class="col-12 text-center py-5">
                                            <i class="fas fa-bullseye text-muted" style="font-size: 3rem;"></i>
                                            <h5 class="text-muted mt-3">No Goals Set</h5>
                                            <p class="text-muted">Goals will appear here after performance reviews</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- 360 Feedback Tab -->
                            <div class="tab-pane fade" id="feedback" role="tabpanel">
                                <div class="text-center py-5">
                                    <i class="fas fa-comments text-muted" style="font-size: 3rem;"></i>
                                    <h5 class="text-muted mt-3">360° Feedback System</h5>
                                    <p class="text-muted">Multi-source feedback collection coming soon!</p>
                                    <button class="btn btn-outline-primary" onclick="alert('360° feedback system will be implemented next!')">
                                        <i class="fas fa-plus me-1"></i>Request Feedback
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

<!-- Create Review Modal -->
<div class="modal fade" id="createReviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i>Create Performance Review
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createReviewForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Employee <span class="text-danger">*</span></label>
                            <select class="form-select" name="employee_id" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?= $emp['id'] ?>">
                                        <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'] . ' (' . $emp['employee_id'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Review Period <span class="text-danger">*</span></label>
                            <select class="form-select" name="review_period" required>
                                <option value="">Select Period</option>
                                <option value="Q1 2025">Q1 2025</option>
                                <option value="Q2 2025">Q2 2025</option>
                                <option value="Q3 2025">Q3 2025</option>
                                <option value="Q4 2025">Q4 2025</option>
                                <option value="Annual 2025">Annual 2025</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Review Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="review_type" required>
                                <option value="">Select Type</option>
                                <option value="quarterly">Quarterly Review</option>
                                <option value="annual">Annual Review</option>
                                <option value="probationary">Probationary Review</option>
                                <option value="mid_year">Mid-Year Review</option>
                                <option value="project_based">Project-Based Review</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Due Date</label>
                            <input type="date" class="form-control" name="due_date">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitCreateReview()">
                    <i class="fas fa-plus me-1"></i>Create Review
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.main-content {
    margin-left: 250px;
    padding: 2rem;
    min-height: 100vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        padding: 1rem;
    }
}

.stats-card {
    transition: all 0.3s ease;
    border-radius: 12px;
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.95);
}

.stats-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.employee-avatar-small {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(45deg, #007bff, #6610f2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.75rem;
    font-weight: bold;
}

.card {
    border-radius: 12px;
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.95);
}

.table th {
    border-top: none;
    font-weight: 600;
    color: #6c757d;
    font-size: 0.875rem;
}

.rating-display .fa-star {
    font-size: 0.875rem;
}

.goal-card {
    border-left: 4px solid #007bff;
    transition: all 0.3s ease;
}

.goal-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.nav-tabs .nav-link {
    border: none;
    color: #6c757d;
    font-weight: 500;
}

.nav-tabs .nav-link.active {
    color: #007bff;
    border-bottom: 2px solid #007bff;
    background: transparent;
}
</style>

<script>
// Show create review modal
function showCreateReviewModal() {
    const modal = new bootstrap.Modal(document.getElementById('createReviewModal'));
    modal.show();
}

// Submit create review
function submitCreateReview() {
    const form = document.getElementById('createReviewForm');
    const formData = new FormData(form);
    formData.append('action', 'create_review');
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Performance review created successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Network error: ' + error.message);
    });
}

// View review details
function viewReview(reviewId) {
    alert('Review details view will be implemented next!');
}

// Edit review
function editReview(reviewId) {
    alert('Review editing interface will be implemented next!');
}

// Update goal progress
function updateGoalProgress(goalId) {
    const progress = prompt('Enter progress percentage (0-100):');
    const notes = prompt('Enter progress notes (optional):');
    
    if (progress !== null && progress >= 0 && progress <= 100) {
        const formData = new FormData();
        formData.append('action', 'update_goal_progress');
        formData.append('goal_id', goalId);
        formData.append('progress', progress);
        formData.append('notes', notes || '');
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Goal progress updated successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            alert('Network error: ' + error.message);
        });
    }
}

// Show performance analytics
function showPerformanceAnalytics() {
    alert('Performance analytics dashboard will be implemented next!');
}
</script>

<?php require_once '../layouts/footer.php'; ?>
