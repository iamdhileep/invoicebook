<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit;
}

define('HRMS_ACCESS', true);
require_once '../db.php';

$page_title = "Performance Management";

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_review':
            try {
                $stmt = $conn->prepare("INSERT INTO hr_performance_reviews (employee_id, reviewer_id, review_period_start, review_period_end, overall_rating, goals_achievement, communication_skills, technical_skills, teamwork, leadership, comments, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->bind_param("iissiiiiiss", 
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
                    echo json_encode(['success' => true, 'message' => 'Performance review added successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to add review']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;

        case 'add_goal':
            try {
                $stmt = $conn->prepare("INSERT INTO employee_goals (employee_id, goal_title, description, start_date, end_date, target_value, measurement_unit, assigned_by, priority, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
                $stmt->bind_param("issssisss",
                    $_POST['employee_id'],
                    $_POST['goal_title'],
                    $_POST['description'],
                    $_POST['start_date'],
                    $_POST['end_date'],
                    $_POST['target_value'],
                    $_POST['measurement_unit'],
                    $_POST['assigned_by'],
                    $_POST['priority']
                );
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Goal added successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to add goal']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;

        case 'add_metric':
            try {
                $stmt = $conn->prepare("INSERT INTO performance_metrics (employee_id, metric_name, metric_value, target_value, unit, measurement_period, metric_category, recorded_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isddsssis",
                    $_POST['employee_id'],
                    $_POST['metric_name'],
                    $_POST['metric_value'],
                    $_POST['target_value'],
                    $_POST['unit'],
                    $_POST['measurement_period'],
                    $_POST['metric_category'],
                    $_POST['recorded_by'],
                    $_POST['notes']
                );
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Performance metric added successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to add metric']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;

        case 'update_goal_progress':
            try {
                $progress = min(100, max(0, floatval($_POST['progress'])));
                $status = $progress >= 100 ? 'completed' : 'in_progress';
                
                $stmt = $conn->prepare("UPDATE employee_goals SET progress_percentage = ?, current_value = ?, status = ?, notes = ? WHERE id = ?");
                $stmt->bind_param("dissi", $progress, $_POST['current_value'], $status, $_POST['notes'], $_POST['goal_id']);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Goal progress updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update goal progress']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;

        case 'update_review_status':
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

        case 'delete_goal':
            try {
                $stmt = $conn->prepare("DELETE FROM employee_goals WHERE id = ?");
                $stmt->bind_param("i", $_POST['goal_id']);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Goal deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete goal']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;

        case 'get_employee_details':
            try {
                $stmt = $conn->prepare("SELECT employee_id as id, name, department_name as department, position FROM employees WHERE employee_id = ? AND status = 'active'");
                $stmt->bind_param("i", $_POST['employee_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($employee = $result->fetch_assoc()) {
                    echo json_encode(['success' => true, 'employee' => $employee]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Employee not found']);
                }
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

// Fetch performance reviews
$reviews = [];
$sql = "SELECT pr.*, e1.name as employee_name, e1.department_name as department, e2.name as reviewer_name 
        FROM hr_performance_reviews pr 
        LEFT JOIN employees e1 ON pr.employee_id = e1.employee_id 
        LEFT JOIN employees e2 ON pr.reviewer_id = e2.employee_id 
        ORDER BY pr.created_at DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $reviews[] = $row;
    }
}

// Fetch goals
$goals = [];
$sql = "SELECT g.*, e1.name as employee_name, e1.department_name as department, e2.name as assigned_by_name 
        FROM employee_goals g 
        LEFT JOIN employees e1 ON g.employee_id = e1.employee_id 
        LEFT JOIN employees e2 ON g.assigned_by = e2.employee_id 
        ORDER BY g.created_at DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $goals[] = $row;
    }
}

// Fetch performance metrics
$metrics = [];
$sql = "SELECT pm.*, e1.name as employee_name, e1.department_name as department, e2.name as recorded_by_name 
        FROM performance_metrics pm 
        LEFT JOIN employees e1 ON pm.employee_id = e1.employee_id 
        LEFT JOIN employees e2 ON pm.recorded_by = e2.employee_id 
        ORDER BY pm.created_at DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $metrics[] = $row;
    }
}

include '../layouts/header.php';
include '../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">📊 Performance Management</h1>
                <p class="text-muted">Manage employee performance reviews, goals, and metrics</p>
            </div>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addReviewModal">
                    <i class="bi bi-plus-circle me-1"></i>Add Review
                </button>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addGoalModal">
                    <i class="bi bi-bullseye me-1"></i>Add Goal
                </button>
                <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#addMetricModal">
                    <i class="bi bi-speedometer2 me-1"></i>Add Metric
                </button>
            </div>
        </div>

        <!-- Performance Dashboard Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-clipboard-check fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= count($reviews) ?></h3>
                        <small class="opacity-75">Total Reviews</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-bullseye fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= count($goals) ?></h3>
                        <small class="opacity-75">Active Goals</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-speedometer2 fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= count($metrics) ?></h3>
                        <small class="opacity-75">Performance Metrics</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-people fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= count($employees) ?></h3>
                        <small class="opacity-75">Active Employees</small>
                    </div>
                </div>
            </div>
        </div>

            <!-- Tabs Navigation -->
            <ul class="nav nav-tabs" id="performanceTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews" type="button" role="tab">
                        <i class="bi bi-clipboard-check me-1"></i>Performance Reviews
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="goals-tab" data-bs-toggle="tab" data-bs-target="#goals" type="button" role="tab">
                        <i class="bi bi-bullseye me-1"></i>Employee Goals
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="metrics-tab" data-bs-toggle="tab" data-bs-target="#metrics" type="button" role="tab">
                        <i class="bi bi-speedometer2 me-1"></i>Performance Metrics
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="analytics-tab" data-bs-toggle="tab" data-bs-target="#analytics" type="button" role="tab">
                        <i class="bi bi-graph-up me-1"></i>Analytics
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="performanceTabsContent">
                <!-- Performance Reviews Tab -->
                <div class="tab-pane fade show active" id="reviews" role="tabpanel">
                    <div class="card mt-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Performance Reviews</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addReviewModal">
                                <i class="bi bi-plus-circle me-1"></i>Add Review
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped" id="reviewsTable">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Department</th>
                                            <th>Reviewer</th>
                                            <th>Period</th>
                                            <th>Overall Rating</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reviews as $review): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($review['employee_name'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($review['department'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($review['reviewer_name'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($review['review_period_start'] ?? '') ?> to <?= htmlspecialchars($review['review_period_end'] ?? '') ?></td>
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
                                                <span class="badge bg-<?= $review['status'] == 'completed' ? 'success' : ($review['status'] == 'in_progress' ? 'warning' : 'secondary') ?>">
                                                    <?= ucfirst($review['status'] ?? 'pending') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-outline-primary" onclick="viewReview(<?= $review['id'] ?>)" title="View Details">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-success" onclick="updateReviewStatus(<?= $review['id'] ?>, 'completed')" title="Mark Complete">
                                                        <i class="bi bi-check-circle"></i>
                                                    </button>
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

                <!-- Employee Goals Tab -->
                <div class="tab-pane fade" id="goals" role="tabpanel">
                    <div class="card mt-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Employee Goals</h5>
                            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addGoalModal">
                                <i class="bi bi-plus-circle me-1"></i>Add Goal
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped" id="goalsTable">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Goal Title</th>
                                            <th>Priority</th>
                                            <th>Progress</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($goals as $goal): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($goal['employee_name'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($goal['goal_title'] ?? '') ?></td>
                                            <td>
                                                <span class="badge bg-<?= $goal['priority'] == 'high' ? 'danger' : ($goal['priority'] == 'medium' ? 'warning' : 'info') ?>">
                                                    <?= ucfirst($goal['priority'] ?? 'low') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar" role="progressbar" style="width: <?= $goal['progress_percentage'] ?? 0 ?>%" 
                                                         aria-valuenow="<?= $goal['progress_percentage'] ?? 0 ?>" aria-valuemin="0" aria-valuemax="100">
                                                        <?= $goal['progress_percentage'] ?? 0 ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($goal['start_date'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($goal['end_date'] ?? '') ?></td>
                                            <td>
                                                <span class="badge bg-<?= $goal['status'] == 'completed' ? 'success' : ($goal['status'] == 'in_progress' ? 'warning' : 'secondary') ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $goal['status'] ?? 'active')) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-outline-primary" onclick="viewGoal(<?= $goal['id'] ?>)" title="View Details">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-info" onclick="updateProgress(<?= $goal['id'] ?>)" title="Update Progress">
                                                        <i class="bi bi-arrow-up-circle"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteGoal(<?= $goal['id'] ?>)" title="Delete">
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

                <!-- Performance Metrics Tab -->
                <div class="tab-pane fade" id="metrics" role="tabpanel">
                    <div class="card mt-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Performance Metrics</h5>
                            <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#addMetricModal">
                                <i class="bi bi-plus-circle me-1"></i>Add Metric
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped" id="metricsTable">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Metric Name</th>
                                            <th>Category</th>
                                            <th>Current Value</th>
                                            <th>Target Value</th>
                                            <th>Achievement</th>
                                            <th>Period</th>
                                            <th>Recorded Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($metrics as $metric): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($metric['employee_name'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($metric['metric_name'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($metric['metric_category'] ?? '') ?></td>
                                            <td><?= number_format($metric['metric_value'] ?? 0, 2) ?> <?= htmlspecialchars($metric['unit'] ?? '') ?></td>
                                            <td><?= number_format($metric['target_value'] ?? 0, 2) ?> <?= htmlspecialchars($metric['unit'] ?? '') ?></td>
                                            <td>
                                                <?php 
                                                $achievement = $metric['target_value'] > 0 ? ($metric['metric_value'] / $metric['target_value'] * 100) : 0;
                                                $color = $achievement >= 100 ? 'success' : ($achievement >= 75 ? 'warning' : 'danger');
                                                ?>
                                                <span class="badge bg-<?= $color ?>"><?= number_format($achievement, 1) ?>%</span>
                                            </td>
                                            <td><?= htmlspecialchars($metric['measurement_period'] ?? '') ?></td>
                                            <td><?= date('Y-m-d', strtotime($metric['created_at'] ?? '')) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Analytics Tab -->
                <div class="tab-pane fade" id="analytics" role="tabpanel">
                    <div class="mt-3">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Performance Overview</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="performanceChart" width="400" height="200"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Goal Progress Distribution</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="goalProgressChart" width="400" height="200"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Department Performance Summary</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php
                                        // Calculate department statistics
                                        $dept_stats = [];
                                        foreach ($employees as $emp) {
                                            $dept = $emp['department'] ?? 'Unknown';
                                            if (!isset($dept_stats[$dept])) {
                                                $dept_stats[$dept] = ['employees' => 0, 'reviews' => 0, 'goals' => 0, 'avg_rating' => 0];
                                            }
                                            $dept_stats[$dept]['employees']++;
                                        }
                                        
                                        foreach ($reviews as $review) {
                                            $dept = $review['department'] ?? 'Unknown';
                                            if (isset($dept_stats[$dept])) {
                                                $dept_stats[$dept]['reviews']++;
                                                $dept_stats[$dept]['avg_rating'] += $review['overall_rating'] ?? 0;
                                            }
                                        }
                                        
                                        foreach ($goals as $goal) {
                                            $dept = $goal['department'] ?? 'Unknown';
                                            if (isset($dept_stats[$dept])) {
                                                $dept_stats[$dept]['goals']++;
                                            }
                                        }
                                        
                                        // Calculate averages
                                        foreach ($dept_stats as $dept => &$stats) {
                                            if ($stats['reviews'] > 0) {
                                                $stats['avg_rating'] = $stats['avg_rating'] / $stats['reviews'];
                                            }
                                        }
                                        ?>
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Department</th>
                                                        <th>Employees</th>
                                                        <th>Reviews</th>
                                                        <th>Goals</th>
                                                        <th>Avg Rating</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($dept_stats as $dept => $stats): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($dept) ?></td>
                                                        <td><?= $stats['employees'] ?></td>
                                                        <td><?= $stats['reviews'] ?></td>
                                                        <td><?= $stats['goals'] ?></td>
                                                        <td>
                                                            <?php if ($stats['avg_rating'] > 0): ?>
                                                                <?= number_format($stats['avg_rating'], 1) ?>/5
                                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                    <i class="bi bi-star<?= $i <= $stats['avg_rating'] ? '-fill text-warning' : ' text-muted' ?>"></i>
                                                                <?php endfor; ?>
                                                            <?php else: ?>
                                                                <span class="text-muted">No ratings</span>
                                                            <?php endif; ?>
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
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Performance Review Modal -->
<div class="modal fade" id="addReviewModal" tabindex="-1" aria-labelledby="addReviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addReviewModalLabel">Add Performance Review</h5>
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
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="overall_rating" class="form-label">Overall Rating *</label>
                                <select class="form-select" id="overall_rating" name="overall_rating" required>
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
                        <label for="review_comments" class="form-label">Comments</label>
                        <textarea class="form-control" id="review_comments" name="comments" rows="4" placeholder="Enter detailed review comments..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Review</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Goal Modal -->
<div class="modal fade" id="addGoalModal" tabindex="-1" aria-labelledby="addGoalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addGoalModalLabel">Add Employee Goal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addGoalForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="goal_employee_id" class="form-label">Employee *</label>
                                <select class="form-select" id="goal_employee_id" name="employee_id" required>
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?> - <?= htmlspecialchars($emp['department']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="goal_assigned_by" class="form-label">Assigned By *</label>
                                <select class="form-select" id="goal_assigned_by" name="assigned_by" required>
                                    <option value="">Select Assigner</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?> - <?= htmlspecialchars($emp['position']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="goal_title" class="form-label">Goal Title *</label>
                        <input type="text" class="form-control" id="goal_title" name="goal_title" required placeholder="Enter goal title...">
                    </div>
                    <div class="mb-3">
                        <label for="goal_description" class="form-label">Description</label>
                        <textarea class="form-control" id="goal_description" name="description" rows="3" placeholder="Detailed description of the goal..."></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="goal_start_date" class="form-label">Start Date *</label>
                                <input type="date" class="form-control" id="goal_start_date" name="start_date" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="goal_end_date" class="form-label">End Date *</label>
                                <input type="date" class="form-control" id="goal_end_date" name="end_date" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="goal_priority" class="form-label">Priority *</label>
                                <select class="form-select" id="goal_priority" name="priority" required>
                                    <option value="">Select Priority</option>
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="target_value" class="form-label">Target Value</label>
                                <input type="number" step="0.01" class="form-control" id="target_value" name="target_value" placeholder="e.g., 100">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="measurement_unit" class="form-label">Measurement Unit</label>
                                <input type="text" class="form-control" id="measurement_unit" name="measurement_unit" placeholder="e.g., sales, projects, hours">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Goal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Metric Modal -->
<div class="modal fade" id="addMetricModal" tabindex="-1" aria-labelledby="addMetricModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addMetricModalLabel">Add Performance Metric</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addMetricForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="metric_employee_id" class="form-label">Employee *</label>
                                <select class="form-select" id="metric_employee_id" name="employee_id" required>
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?> - <?= htmlspecialchars($emp['department']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="metric_recorded_by" class="form-label">Recorded By *</label>
                                <select class="form-select" id="metric_recorded_by" name="recorded_by" required>
                                    <option value="">Select Recorder</option>
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
                                <label for="metric_name" class="form-label">Metric Name *</label>
                                <input type="text" class="form-control" id="metric_name" name="metric_name" required placeholder="e.g., Sales Target">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="metric_category" class="form-label">Category *</label>
                                <select class="form-select" id="metric_category" name="metric_category" required>
                                    <option value="">Select Category</option>
                                    <option value="Sales">Sales</option>
                                    <option value="Quality">Quality</option>
                                    <option value="Productivity">Productivity</option>
                                    <option value="Customer Service">Customer Service</option>
                                    <option value="Leadership">Leadership</option>
                                    <option value="Innovation">Innovation</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="metric_value" class="form-label">Current Value *</label>
                                <input type="number" step="0.01" class="form-control" id="metric_value" name="metric_value" required placeholder="Current achievement">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="metric_target_value" class="form-label">Target Value *</label>
                                <input type="number" step="0.01" class="form-control" id="metric_target_value" name="target_value" required placeholder="Target to achieve">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="metric_unit" class="form-label">Unit</label>
                                <input type="text" class="form-control" id="metric_unit" name="unit" placeholder="e.g., %, units, hours">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="measurement_period" class="form-label">Measurement Period *</label>
                        <select class="form-select" id="measurement_period" name="measurement_period" required>
                            <option value="">Select Period</option>
                            <option value="Daily">Daily</option>
                            <option value="Weekly">Weekly</option>
                            <option value="Monthly">Monthly</option>
                            <option value="Quarterly">Quarterly</option>
                            <option value="Yearly">Yearly</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="metric_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="metric_notes" name="notes" rows="3" placeholder="Additional notes about the metric..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">Add Metric</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Update Progress Modal -->
<div class="modal fade" id="updateProgressModal" tabindex="-1" aria-labelledby="updateProgressModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateProgressModalLabel">Update Goal Progress</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="updateProgressForm">
                <div class="modal-body">
                    <input type="hidden" id="progress_goal_id" name="goal_id">
                    <div class="mb-3">
                        <label for="progress_percentage" class="form-label">Progress Percentage *</label>
                        <input type="range" class="form-range" id="progress_percentage" name="progress" min="0" max="100" step="1">
                        <div class="d-flex justify-content-between">
                            <small>0%</small>
                            <small id="progressValue">50%</small>
                            <small>100%</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="current_value" class="form-label">Current Value</label>
                        <input type="number" step="0.01" class="form-control" id="current_value" name="current_value" placeholder="Current achievement">
                    </div>
                    <div class="mb-3">
                        <label for="progress_notes" class="form-label">Progress Notes</label>
                        <textarea class="form-control" id="progress_notes" name="notes" rows="3" placeholder="Notes about the progress..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Progress</button>
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

<!-- View Goal Modal -->
<div class="modal fade" id="viewGoalModal" tabindex="-1" aria-labelledby="viewGoalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewGoalModalLabel">Goal Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="goalDetails">
                <!-- Goal details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize charts
    initializeCharts();
    
    // Progress range input
    const progressRange = document.getElementById('progress_percentage');
    const progressValue = document.getElementById('progressValue');
    
    if (progressRange && progressValue) {
        progressRange.addEventListener('input', function() {
            progressValue.textContent = this.value + '%';
        });
    }

    // Form submissions
    document.getElementById('addReviewForm').addEventListener('submit', function(e) {
        e.preventDefault();
        submitForm(this, 'add_review');
    });

    document.getElementById('addGoalForm').addEventListener('submit', function(e) {
        e.preventDefault();
        submitForm(this, 'add_goal');
    });

    document.getElementById('addMetricForm').addEventListener('submit', function(e) {
        e.preventDefault();
        submitForm(this, 'add_metric');
    });

    document.getElementById('updateProgressForm').addEventListener('submit', function(e) {
        e.preventDefault();
        submitForm(this, 'update_goal_progress');
    });
});

function submitForm(form, action) {
    const formData = new FormData(form);
    formData.append('action', action);
    
    fetch('performance_management.php', {
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
                    <p><strong>Reviewer:</strong> ${review.reviewer_name || 'N/A'}</p>
                </div>
                <div class="col-md-6">
                    <h6>Review Period</h6>
                    <p><strong>Start Date:</strong> ${review.review_period_start || 'N/A'}</p>
                    <p><strong>End Date:</strong> ${review.review_period_end || 'N/A'}</p>
                    <p><strong>Status:</strong> <span class="badge bg-${review.status == 'completed' ? 'success' : (review.status == 'in_progress' ? 'warning' : 'secondary')}">${review.status ? review.status.charAt(0).toUpperCase() + review.status.slice(1) : 'Pending'}</span></p>
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
                    <h6>Comments</h6>
                    <p>${review.comments || 'No comments provided.'}</p>
                </div>
            </div>
        `;
        
        const modal = new bootstrap.Modal(document.getElementById('viewReviewModal'));
        modal.show();
    }
}

function viewGoal(goalId) {
    const goals = <?= json_encode($goals) ?>;
    const goal = goals.find(g => g.id == goalId);
    
    if (goal) {
        const modalBody = document.getElementById('goalDetails');
        modalBody.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <h6>Goal Information</h6>
                    <p><strong>Title:</strong> ${goal.goal_title || 'N/A'}</p>
                    <p><strong>Employee:</strong> ${goal.employee_name || 'N/A'}</p>
                    <p><strong>Department:</strong> ${goal.department || 'N/A'}</p>
                    <p><strong>Assigned By:</strong> ${goal.assigned_by_name || 'N/A'}</p>
                </div>
                <div class="col-md-6">
                    <h6>Timeline & Progress</h6>
                    <p><strong>Start Date:</strong> ${goal.start_date || 'N/A'}</p>
                    <p><strong>End Date:</strong> ${goal.end_date || 'N/A'}</p>
                    <p><strong>Priority:</strong> <span class="badge bg-${goal.priority == 'high' ? 'danger' : (goal.priority == 'medium' ? 'warning' : 'info')}">${goal.priority ? goal.priority.charAt(0).toUpperCase() + goal.priority.slice(1) : 'Low'}</span></p>
                    <p><strong>Status:</strong> <span class="badge bg-${goal.status == 'completed' ? 'success' : (goal.status == 'in_progress' ? 'warning' : 'secondary')}">${goal.status ? goal.status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) : 'Active'}</span></p>
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-12">
                    <h6>Progress</h6>
                    <div class="progress mb-3" style="height: 25px;">
                        <div class="progress-bar" role="progressbar" style="width: ${goal.progress_percentage || 0}%" 
                             aria-valuenow="${goal.progress_percentage || 0}" aria-valuemin="0" aria-valuemax="100">
                            ${goal.progress_percentage || 0}%
                        </div>
                    </div>
                    ${goal.target_value ? `<p><strong>Target:</strong> ${goal.target_value} ${goal.measurement_unit || ''}</p>` : ''}
                    ${goal.current_value ? `<p><strong>Current:</strong> ${goal.current_value} ${goal.measurement_unit || ''}</p>` : ''}
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-12">
                    <h6>Description</h6>
                    <p>${goal.description || 'No description provided.'}</p>
                    ${goal.notes ? `<h6>Notes</h6><p>${goal.notes}</p>` : ''}
                </div>
            </div>
        `;
        
        const modal = new bootstrap.Modal(document.getElementById('viewGoalModal'));
        modal.show();
    }
}

function updateProgress(goalId) {
    const goals = <?= json_encode($goals) ?>;
    const goal = goals.find(g => g.id == goalId);
    
    if (goal) {
        document.getElementById('progress_goal_id').value = goalId;
        document.getElementById('progress_percentage').value = goal.progress_percentage || 0;
        document.getElementById('progressValue').textContent = (goal.progress_percentage || 0) + '%';
        document.getElementById('current_value').value = goal.current_value || '';
        document.getElementById('progress_notes').value = goal.notes || '';
        
        const modal = new bootstrap.Modal(document.getElementById('updateProgressModal'));
        modal.show();
    }
}

function updateReviewStatus(reviewId, status) {
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
            formData.append('action', 'update_review_status');
            formData.append('review_id', reviewId);
            formData.append('status', status);
            
            fetch('performance_management.php', {
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
            
            fetch('performance_management.php', {
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

function deleteGoal(goalId) {
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
            formData.append('action', 'delete_goal');
            formData.append('goal_id', goalId);
            
            fetch('performance_management.php', {
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

function initializeCharts() {
    // Performance Overview Chart
    const performanceCtx = document.getElementById('performanceChart');
    if (performanceCtx) {
        const reviews = <?= json_encode($reviews) ?>;
        const ratingData = [0, 0, 0, 0, 0]; // 1-5 star ratings
        
        reviews.forEach(review => {
            if (review.overall_rating) {
                ratingData[review.overall_rating - 1]++;
            }
        });
        
        new Chart(performanceCtx, {
            type: 'bar',
            data: {
                labels: ['1 Star', '2 Stars', '3 Stars', '4 Stars', '5 Stars'],
                datasets: [{
                    label: 'Number of Reviews',
                    data: ratingData,
                    backgroundColor: [
                        '#ff6384',
                        '#ff9f40',
                        '#ffcd56',
                        '#4bc0c0',
                        '#36a2eb'
                    ]
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }
    
    // Goal Progress Chart
    const goalProgressCtx = document.getElementById('goalProgressChart');
    if (goalProgressCtx) {
        const goals = <?= json_encode($goals) ?>;
        const progressData = [0, 0, 0, 0]; // 0-25%, 26-50%, 51-75%, 76-100%
        
        goals.forEach(goal => {
            const progress = goal.progress_percentage || 0;
            if (progress <= 25) progressData[0]++;
            else if (progress <= 50) progressData[1]++;
            else if (progress <= 75) progressData[2]++;
            else progressData[3]++;
        });
        
        new Chart(goalProgressCtx, {
            type: 'doughnut',
            data: {
                labels: ['0-25%', '26-50%', '51-75%', '76-100%'],
                datasets: [{
                    data: progressData,
                    backgroundColor: [
                        '#ff6384',
                        '#ff9f40',
                        '#ffcd56',
                        '#4bc0c0'
                    ]
                }]
            },
            options: {
                responsive: true
            }
        });
    }
}
</script>

    </div>
</div>

<?php include '../layouts/footer.php'; ?>
