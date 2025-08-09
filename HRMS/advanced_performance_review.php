<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

define('HRMS_ACCESS', true);
require_once '../db.php';

$page_title = "Advanced Performance Review";

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_review':
            try {
                $stmt = $conn->prepare("INSERT INTO hr_performance_reviews (employee_id, reviewer_id, review_period_start, review_period_end, overall_rating, goals_achievement, communication_skills, technical_skills, teamwork, leadership, comments, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')");
                $stmt->bind_param("iissiiiiiis", 
                    $_POST['employee_id'], 
                    $_POST['reviewer_id'],
                    $_POST['period_start'], 
                    $_POST['period_end'],
                    $_POST['overall_rating'],
                    $_POST['goals_achievement'],
                    $_POST['communication_skills'],
                    $_POST['technical_skills'],
                    $_POST['teamwork'],
                    $_POST['leadership'],
                    $_POST['comments']
                );
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Performance review added successfully', 'review_id' => $conn->insert_id]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to add review']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;

        case 'update_review':
            try {
                $stmt = $conn->prepare("UPDATE hr_performance_reviews SET overall_rating = ?, goals_achievement = ?, communication_skills = ?, technical_skills = ?, teamwork = ?, leadership = ?, comments = ? WHERE id = ?");
                $stmt->bind_param("iiiiiiisi",
                    $_POST['overall_rating'],
                    $_POST['goals_achievement'],
                    $_POST['communication_skills'],
                    $_POST['technical_skills'],
                    $_POST['teamwork'],
                    $_POST['leadership'],
                    $_POST['comments'],
                    $_POST['review_id']
                );
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Review updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update review']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;

        case 'update_status':
            try {
                $stmt = $conn->prepare("UPDATE hr_performance_reviews SET status = ? WHERE id = ?");
                $stmt->bind_param("si", $_POST['status'], $_POST['review_id']);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Review status updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update review status']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;

        case 'delete_review':
            try {
                $stmt = $conn->prepare("DELETE FROM hr_performance_reviews WHERE id = ?");
                $stmt->bind_param("i", $_POST['review_id']);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Review deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete review']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;

        case 'get_review_details':
            try {
                $stmt = $conn->prepare("SELECT pr.*, e1.name as employee_name, e1.department_name as department, e1.position, e2.name as reviewer_name FROM hr_performance_reviews pr LEFT JOIN employees e1 ON pr.employee_id = e1.employee_id LEFT JOIN employees e2 ON pr.reviewer_id = e2.employee_id WHERE pr.id = ?");
                $stmt->bind_param("i", $_POST['review_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($review = $result->fetch_assoc()) {
                    echo json_encode(['success' => true, 'review' => $review]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Review not found']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;

        case 'generate_report':
            try {
                $employee_id = $_POST['employee_id'];
                $start_date = $_POST['start_date'];
                $end_date = $_POST['end_date'];
                
                // Get employee reviews
                $stmt = $conn->prepare("SELECT pr.*, e1.name as employee_name, e1.department_name as department, e1.position, e2.name as reviewer_name FROM hr_performance_reviews pr LEFT JOIN employees e1 ON pr.employee_id = e1.employee_id LEFT JOIN employees e2 ON pr.reviewer_id = e2.employee_id WHERE pr.employee_id = ? AND pr.created_at BETWEEN ? AND ? ORDER BY pr.created_at DESC");
                $stmt->bind_param("iss", $employee_id, $start_date, $end_date);
                $stmt->execute();
                $reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                // Get employee goals
                $stmt = $conn->prepare("SELECT * FROM employee_goals WHERE employee_id = ? AND created_at BETWEEN ? AND ?");
                $stmt->bind_param("iss", $employee_id, $start_date, $end_date);
                $stmt->execute();
                $goals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                // Get performance metrics
                $stmt = $conn->prepare("SELECT * FROM performance_metrics WHERE employee_id = ? AND created_at BETWEEN ? AND ?");
                $stmt->bind_param("iss", $employee_id, $start_date, $end_date);
                $stmt->execute();
                $metrics = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                echo json_encode(['success' => true, 'report' => ['reviews' => $reviews, 'goals' => $goals, 'metrics' => $metrics]]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
    }
}

// Fetch employees for dropdowns
$employees = [];
$result = $conn->query("SELECT employee_id as id, name, department_name as department, position FROM employees WHERE status = 'active' ORDER BY name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }
}

// Fetch performance reviews with filters
$filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$employee_filter = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;

$where_clause = "WHERE 1=1";
if ($filter !== 'all') {
    $where_clause .= " AND pr.status = '$filter'";
}
if ($employee_filter > 0) {
    $where_clause .= " AND pr.employee_id = $employee_filter";
}

$sql = "SELECT pr.*, e1.name as employee_name, e1.department_name as department, e1.position, e2.name as reviewer_name 
        FROM hr_performance_reviews pr 
        LEFT JOIN employees e1 ON pr.employee_id = e1.employee_id 
        LEFT JOIN employees e2 ON pr.reviewer_id = e2.employee_id 
        $where_clause
        ORDER BY pr.created_at DESC";

$result = $conn->query($sql);
$reviews = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $reviews[] = $row;
    }
}

// Calculate review statistics
$stats = [
    'total' => count($reviews),
    'draft' => count(array_filter($reviews, function($r) { return $r['status'] == 'draft'; })),
    'pending' => count(array_filter($reviews, function($r) { return $r['status'] == 'pending'; })),
    'completed' => count(array_filter($reviews, function($r) { return $r['status'] == 'completed'; })),
];

include '../layouts/header.php';
include '../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">📋 Advanced Performance Review</h1>
                <p class="text-muted">Comprehensive employee performance evaluation system</p>
            </div>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addReviewModal">
                    <i class="bi bi-plus-circle me-1"></i>Create Review
                </button>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#generateReportModal">
                    <i class="bi bi-file-earmark-text me-1"></i>Generate Report
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-clipboard-check fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $stats['total'] ?></h3>
                        <small class="opacity-75">Total Reviews</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-file-earmark fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $stats['draft'] ?></h3>
                        <small class="opacity-75">Draft Reviews</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-clock fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $stats['pending'] ?></h3>
                        <small class="opacity-75">Pending Reviews</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-check-circle fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $stats['completed'] ?></h3>
                        <small class="opacity-75">Completed Reviews</small>
                    </div>
                </div>
            </div>
        </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <label for="statusFilter" class="form-label">Filter by Status</label>
                            <select class="form-select" id="statusFilter" onchange="applyFilters()">
                                <option value="all" <?= $filter == 'all' ? 'selected' : '' ?>>All Reviews</option>
                                <option value="draft" <?= $filter == 'draft' ? 'selected' : '' ?>>Draft</option>
                                <option value="pending" <?= $filter == 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="completed" <?= $filter == 'completed' ? 'selected' : '' ?>>Completed</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="employeeFilter" class="form-label">Filter by Employee</label>
                            <select class="form-select" id="employeeFilter" onchange="applyFilters()">
                                <option value="0">All Employees</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?= $emp['id'] ?>" <?= $employee_filter == $emp['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($emp['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-secondary" onclick="clearFilters()">
                                    <i class="bi bi-x-circle me-1"></i>Clear Filters
                                </button>
                                <button type="button" class="btn btn-outline-info" onclick="exportReviews()">
                                    <i class="bi bi-download me-1"></i>Export
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reviews Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Performance Reviews</h5>
                    <span class="badge bg-info"><?= count($reviews) ?> Reviews</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="reviewsTable">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Reviewer</th>
                                    <th>Review Period</th>
                                    <th>Overall Rating</th>
                                    <th>Performance Areas</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reviews as $review): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($review['employee_name'] ?? 'N/A') ?></strong>
                                            <br>
                                            <small class="text-muted"><?= htmlspecialchars($review['department'] ?? '') ?> - <?= htmlspecialchars($review['position'] ?? '') ?></small>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($review['reviewer_name'] ?? 'N/A') ?></td>
                                    <td>
                                        <small>
                                            <?= date('M d, Y', strtotime($review['review_period_start'] ?? '')) ?><br>
                                            to<br>
                                            <?= date('M d, Y', strtotime($review['review_period_end'] ?? '')) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php
                                            $rating = $review['overall_rating'] ?? 0;
                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($i <= $rating) {
                                                    echo '<i class="bi bi-star-fill text-warning me-1"></i>';
                                                } else {
                                                    echo '<i class="bi bi-star text-muted me-1"></i>';
                                                }
                                            }
                                            ?>
                                            <span class="ms-1"><?= $rating ?>/5</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <small>Goals</small><br>
                                                <span class="badge bg-<?= ($review['goals_achievement'] ?? 0) >= 4 ? 'success' : (($review['goals_achievement'] ?? 0) >= 3 ? 'warning' : 'danger') ?>">
                                                    <?= $review['goals_achievement'] ?? 0 ?>
                                                </span>
                                            </div>
                                            <div class="col-4">
                                                <small>Tech</small><br>
                                                <span class="badge bg-<?= ($review['technical_skills'] ?? 0) >= 4 ? 'success' : (($review['technical_skills'] ?? 0) >= 3 ? 'warning' : 'danger') ?>">
                                                    <?= $review['technical_skills'] ?? 0 ?>
                                                </span>
                                            </div>
                                            <div class="col-4">
                                                <small>Team</small><br>
                                                <span class="badge bg-<?= ($review['teamwork'] ?? 0) >= 4 ? 'success' : (($review['teamwork'] ?? 0) >= 3 ? 'warning' : 'danger') ?>">
                                                    <?= $review['teamwork'] ?? 0 ?>
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $review['status'] == 'completed' ? 'success' : ($review['status'] == 'pending' ? 'warning' : 'secondary') ?>">
                                            <?= ucfirst($review['status'] ?? 'draft') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewReview(<?= $review['id'] ?>)" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-success" onclick="editReview(<?= $review['id'] ?>)" title="Edit Review">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <?php if ($review['status'] !== 'completed'): ?>
                                            <button class="btn btn-sm btn-outline-info" onclick="updateStatus(<?= $review['id'] ?>, 'completed')" title="Mark Complete">
                                                <i class="bi bi-check-circle"></i>
                                            </button>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteReview(<?= $review['id'] ?>)" title="Delete">
                                                <i class="bi bi-trash"></i>
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
    </div>
</div>

<!-- Add Review Modal -->
<div class="modal fade" id="addReviewModal" tabindex="-1" aria-labelledby="addReviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addReviewModalLabel">Create Performance Review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addReviewForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="review_employee_id" class="form-label">Employee *</label>
                                <select class="form-select" id="review_employee_id" name="employee_id" required>
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?> - <?= htmlspecialchars($emp['department']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="review_reviewer_id" class="form-label">Reviewer *</label>
                                <select class="form-select" id="review_reviewer_id" name="reviewer_id" required>
                                    <option value="">Select Reviewer</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?> - <?= htmlspecialchars($emp['position']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="period_start" class="form-label">Review Period Start *</label>
                                <input type="date" class="form-control" id="period_start" name="period_start" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="period_end" class="form-label">Review Period End *</label>
                                <input type="date" class="form-control" id="period_end" name="period_end" required>
                            </div>
                        </div>
                    </div>
                    
                    <h6 class="mb-3">Performance Ratings</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="overall_rating" class="form-label">Overall Rating *</label>
                                <div class="rating-input">
                                    <select class="form-select" id="overall_rating" name="overall_rating" required>
                                        <option value="">Select Rating</option>
                                        <option value="1">⭐ 1 - Poor</option>
                                        <option value="2">⭐⭐ 2 - Below Average</option>
                                        <option value="3">⭐⭐⭐ 3 - Average</option>
                                        <option value="4">⭐⭐⭐⭐ 4 - Good</option>
                                        <option value="5">⭐⭐⭐⭐⭐ 5 - Excellent</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="goals_achievement" class="form-label">Goals Achievement</label>
                                <select class="form-select" id="goals_achievement" name="goals_achievement">
                                    <option value="">Select Rating</option>
                                    <option value="1">1 - Poor</option>
                                    <option value="2">2 - Below Average</option>
                                    <option value="3">3 - Average</option>
                                    <option value="4">4 - Good</option>
                                    <option value="5">5 - Excellent</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="communication_skills" class="form-label">Communication Skills</label>
                                <select class="form-select" id="communication_skills" name="communication_skills">
                                    <option value="">Select Rating</option>
                                    <option value="1">1 - Poor</option>
                                    <option value="2">2 - Below Average</option>
                                    <option value="3">3 - Average</option>
                                    <option value="4">4 - Good</option>
                                    <option value="5">5 - Excellent</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="technical_skills" class="form-label">Technical Skills</label>
                                <select class="form-select" id="technical_skills" name="technical_skills">
                                    <option value="">Select Rating</option>
                                    <option value="1">1 - Poor</option>
                                    <option value="2">2 - Below Average</option>
                                    <option value="3">3 - Average</option>
                                    <option value="4">4 - Good</option>
                                    <option value="5">5 - Excellent</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="teamwork" class="form-label">Teamwork</label>
                                <select class="form-select" id="teamwork" name="teamwork">
                                    <option value="">Select Rating</option>
                                    <option value="1">1 - Poor</option>
                                    <option value="2">2 - Below Average</option>
                                    <option value="3">3 - Average</option>
                                    <option value="4">4 - Good</option>
                                    <option value="5">5 - Excellent</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="leadership" class="form-label">Leadership</label>
                                <select class="form-select" id="leadership" name="leadership">
                                    <option value="">Select Rating</option>
                                    <option value="1">1 - Poor</option>
                                    <option value="2">2 - Below Average</option>
                                    <option value="3">3 - Average</option>
                                    <option value="4">4 - Good</option>
                                    <option value="5">5 - Excellent</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="review_comments" class="form-label">Detailed Comments and Feedback</label>
                        <textarea class="form-control" id="review_comments" name="comments" rows="6" placeholder="Enter comprehensive review comments including strengths, areas for improvement, specific achievements, and development recommendations..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Review</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Review Modal -->
<div class="modal fade" id="editReviewModal" tabindex="-1" aria-labelledby="editReviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editReviewModalLabel">Edit Performance Review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editReviewForm">
                <input type="hidden" id="edit_review_id" name="review_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit_overall_rating" class="form-label">Overall Rating *</label>
                                <select class="form-select" id="edit_overall_rating" name="overall_rating" required>
                                    <option value="1">⭐ 1 - Poor</option>
                                    <option value="2">⭐⭐ 2 - Below Average</option>
                                    <option value="3">⭐⭐⭐ 3 - Average</option>
                                    <option value="4">⭐⭐⭐⭐ 4 - Good</option>
                                    <option value="5">⭐⭐⭐⭐⭐ 5 - Excellent</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit_goals_achievement" class="form-label">Goals Achievement</label>
                                <select class="form-select" id="edit_goals_achievement" name="goals_achievement">
                                    <option value="1">1 - Poor</option>
                                    <option value="2">2 - Below Average</option>
                                    <option value="3">3 - Average</option>
                                    <option value="4">4 - Good</option>
                                    <option value="5">5 - Excellent</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit_communication_skills" class="form-label">Communication Skills</label>
                                <select class="form-select" id="edit_communication_skills" name="communication_skills">
                                    <option value="1">1 - Poor</option>
                                    <option value="2">2 - Below Average</option>
                                    <option value="3">3 - Average</option>
                                    <option value="4">4 - Good</option>
                                    <option value="5">5 - Excellent</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit_technical_skills" class="form-label">Technical Skills</label>
                                <select class="form-select" id="edit_technical_skills" name="technical_skills">
                                    <option value="1">1 - Poor</option>
                                    <option value="2">2 - Below Average</option>
                                    <option value="3">3 - Average</option>
                                    <option value="4">4 - Good</option>
                                    <option value="5">5 - Excellent</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit_teamwork" class="form-label">Teamwork</label>
                                <select class="form-select" id="edit_teamwork" name="teamwork">
                                    <option value="1">1 - Poor</option>
                                    <option value="2">2 - Below Average</option>
                                    <option value="3">3 - Average</option>
                                    <option value="4">4 - Good</option>
                                    <option value="5">5 - Excellent</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit_leadership" class="form-label">Leadership</label>
                                <select class="form-select" id="edit_leadership" name="leadership">
                                    <option value="1">1 - Poor</option>
                                    <option value="2">2 - Below Average</option>
                                    <option value="3">3 - Average</option>
                                    <option value="4">4 - Good</option>
                                    <option value="5">5 - Excellent</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_review_comments" class="form-label">Comments</label>
                        <textarea class="form-control" id="edit_review_comments" name="comments" rows="6"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Review</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Generate Report Modal -->
<div class="modal fade" id="generateReportModal" tabindex="-1" aria-labelledby="generateReportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="generateReportModalLabel">Generate Performance Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="generateReportForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="report_employee_id" class="form-label">Employee *</label>
                        <select class="form-select" id="report_employee_id" name="employee_id" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?> - <?= htmlspecialchars($emp['department']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="report_start_date" class="form-label">Start Date *</label>
                                <input type="date" class="form-control" id="report_start_date" name="start_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="report_end_date" class="form-label">End Date *</label>
                                <input type="date" class="form-control" id="report_end_date" name="end_date" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Generate Report</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Review Modal -->
<div class="modal fade" id="viewReviewModal" tabindex="-1" aria-labelledby="viewReviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewReviewModalLabel">Performance Review Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="reviewDetails">
                <!-- Review details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form submissions
    document.getElementById('addReviewForm').addEventListener('submit', function(e) {
        e.preventDefault();
        submitForm(this, 'add_review');
    });

    document.getElementById('editReviewForm').addEventListener('submit', function(e) {
        e.preventDefault();
        submitForm(this, 'update_review');
    });

    document.getElementById('generateReportForm').addEventListener('submit', function(e) {
        e.preventDefault();
        generateReport();
    });
});

function submitForm(form, action) {
    const formData = new FormData(form);
    formData.append('action', action);
    
    fetch('advanced_performance_review.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: data.message,
                showConfirmButton: false,
                timer: 1500
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: data.message
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'An error occurred while processing the request.'
        });
    });
}

function applyFilters() {
    const status = document.getElementById('statusFilter').value;
    const employee = document.getElementById('employeeFilter').value;
    
    let url = 'advanced_performance_review.php?';
    if (status !== 'all') url += 'status=' + status + '&';
    if (employee !== '0') url += 'employee_id=' + employee + '&';
    
    window.location.href = url;
}

function clearFilters() {
    window.location.href = 'advanced_performance_review.php';
}

function exportReviews() {
    window.open('advanced_performance_review.php?export=1', '_blank');
}

function viewReview(reviewId) {
    const reviews = <?= json_encode($reviews) ?>;
    const review = reviews.find(r => r.id == reviewId);
    
    if (review) {
        const modalBody = document.getElementById('reviewDetails');
        modalBody.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <h6>Employee Information</h6>
                    <p><strong>Name:</strong> ${review.employee_name || 'N/A'}</p>
                    <p><strong>Department:</strong> ${review.department || 'N/A'}</p>
                    <p><strong>Position:</strong> ${review.position || 'N/A'}</p>
                    <p><strong>Reviewer:</strong> ${review.reviewer_name || 'N/A'}</p>
                </div>
                <div class="col-md-6">
                    <h6>Review Period</h6>
                    <p><strong>Start Date:</strong> ${new Date(review.review_period_start).toLocaleDateString()}</p>
                    <p><strong>End Date:</strong> ${new Date(review.review_period_end).toLocaleDateString()}</p>
                    <p><strong>Status:</strong> <span class="badge bg-${review.status == 'completed' ? 'success' : (review.status == 'pending' ? 'warning' : 'secondary')}">${review.status.charAt(0).toUpperCase() + review.status.slice(1)}</span></p>
                    <p><strong>Created:</strong> ${new Date(review.created_at).toLocaleDateString()}</p>
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-12">
                    <h6>Performance Ratings</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <p><strong>Overall Rating:</strong> ${generateStars(review.overall_rating)} (${review.overall_rating || 0}/5)</p>
                        </div>
                        <div class="col-md-4">
                            <p><strong>Goals Achievement:</strong> ${generateStars(review.goals_achievement)} (${review.goals_achievement || 0}/5)</p>
                        </div>
                        <div class="col-md-4">
                            <p><strong>Communication:</strong> ${generateStars(review.communication_skills)} (${review.communication_skills || 0}/5)</p>
                        </div>
                        <div class="col-md-4">
                            <p><strong>Technical Skills:</strong> ${generateStars(review.technical_skills)} (${review.technical_skills || 0}/5)</p>
                        </div>
                        <div class="col-md-4">
                            <p><strong>Teamwork:</strong> ${generateStars(review.teamwork)} (${review.teamwork || 0}/5)</p>
                        </div>
                        <div class="col-md-4">
                            <p><strong>Leadership:</strong> ${generateStars(review.leadership)} (${review.leadership || 0}/5)</p>
                        </div>
                    </div>
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-12">
                    <h6>Comments and Feedback</h6>
                    <p>${review.comments || 'No comments provided.'}</p>
                </div>
            </div>
        `;
        
        const modal = new bootstrap.Modal(document.getElementById('viewReviewModal'));
        modal.show();
    }
}

function editReview(reviewId) {
    fetch('advanced_performance_review.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_review_details&review_id=' + reviewId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const review = data.review;
            document.getElementById('edit_review_id').value = review.id;
            document.getElementById('edit_overall_rating').value = review.overall_rating || '';
            document.getElementById('edit_goals_achievement').value = review.goals_achievement || '';
            document.getElementById('edit_communication_skills').value = review.communication_skills || '';
            document.getElementById('edit_technical_skills').value = review.technical_skills || '';
            document.getElementById('edit_teamwork').value = review.teamwork || '';
            document.getElementById('edit_leadership').value = review.leadership || '';
            document.getElementById('edit_review_comments').value = review.comments || '';
            
            const modal = new bootstrap.Modal(document.getElementById('editReviewModal'));
            modal.show();
        } else {
            Swal.fire('Error!', data.message, 'error');
        }
    });
}

function updateStatus(reviewId, status) {
    Swal.fire({
        title: 'Are you sure?',
        text: `Mark this review as ${status}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, update it!'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('review_id', reviewId);
            formData.append('status', status);
            
            fetch('advanced_performance_review.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Updated!', data.message, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            });
        }
    });
}

function deleteReview(reviewId) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'delete_review');
            formData.append('review_id', reviewId);
            
            fetch('advanced_performance_review.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Deleted!', data.message, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            });
        }
    });
}

function generateReport() {
    const formData = new FormData(document.getElementById('generateReportForm'));
    formData.append('action', 'generate_report');
    
    fetch('advanced_performance_review.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Display the generated report in a new modal or window
            displayReport(data.report);
        } else {
            Swal.fire('Error!', data.message, 'error');
        }
    });
}

function displayReport(report) {
    // Create a new window or modal to display the report
    const reportWindow = window.open('', '_blank', 'width=800,height=600');
    reportWindow.document.write(`
        <html>
        <head>
            <title>Performance Report</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .section { margin-bottom: 25px; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .stars { color: gold; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Performance Report</h1>
                <p>Generated on ${new Date().toLocaleDateString()}</p>
            </div>
            <div class="section">
                <h2>Reviews (${report.reviews.length})</h2>
                <table>
                    <tr><th>Period</th><th>Overall Rating</th><th>Status</th><th>Comments</th></tr>
                    ${report.reviews.map(r => `<tr><td>${r.review_period_start} to ${r.review_period_end}</td><td>${r.overall_rating}/5</td><td>${r.status}</td><td>${r.comments}</td></tr>`).join('')}
                </table>
            </div>
            <div class="section">
                <h2>Goals (${report.goals.length})</h2>
                <table>
                    <tr><th>Goal</th><th>Progress</th><th>Status</th><th>Timeline</th></tr>
                    ${report.goals.map(g => `<tr><td>${g.goal_title}</td><td>${g.progress_percentage}%</td><td>${g.status}</td><td>${g.start_date} to ${g.end_date}</td></tr>`).join('')}
                </table>
            </div>
            <div class="section">
                <h2>Metrics (${report.metrics.length})</h2>
                <table>
                    <tr><th>Metric</th><th>Value</th><th>Target</th><th>Achievement</th><th>Category</th></tr>
                    ${report.metrics.map(m => `<tr><td>${m.metric_name}</td><td>${m.metric_value}</td><td>${m.target_value}</td><td>${((m.metric_value/m.target_value)*100).toFixed(1)}%</td><td>${m.metric_category}</td></tr>`).join('')}
                </table>
            </div>
        </body>
        </html>
    `);
}

function generateStars(rating) {
    let stars = '';
    for (let i = 1; i <= 5; i++) {
        if (i <= rating) {
            stars += '<i class="bi bi-star-fill text-warning"></i>';
        } else {
            stars += '<i class="bi bi-star text-muted"></i>';
        }
    }
    return stars;
}
</script>

    </div>
</div>

<?php include '../layouts/footer.php'; ?>
